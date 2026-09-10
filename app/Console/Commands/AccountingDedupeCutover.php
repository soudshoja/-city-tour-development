<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\AccountingLog;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\PostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * CT-A5a — **drift repair for a cutover window.**
 *
 * ── The defect this exists for ───────────────────────────────────────────────────────────────
 * CT-D1 (§0.4i) cut the City Travelers dev site over to the engine while documents were still
 * arriving. Every document that landed between the pre-deploy dump and the `accounting:replay` got
 * a LEGACY inline posting at write time and an ENGINE posting on the replay — the same real-world
 * event standing on the ledger twice. Four invoices (ids 2061–2064, KWD 1,065.000 of revenue) were
 * measured in exactly that state and left there, because there was no safe way to take one of the
 * two back off.
 *
 * `CT-A5a 1` closes the forward hole (no legacy write happens while the engine is on). This command
 * is the BACKWARD half: it finds documents that already carry both postings and reverses the LEGACY
 * side.
 *
 * ── The rules it obeys ───────────────────────────────────────────────────────────────────────
 *   - **Never delete.** A posted line is immutable (CT-A3's own contract, and the reason
 *     `revertFinancialsForTask()` was rewritten off `delete()` in W6.V). The legacy set is taken
 *     off by a NEW, dated, balanced reversing document whose lines mirror it Dr<->Cr.
 *   - **Named reason.** Every reversing document carries
 *     `sub_type = CUTOVER_DEDUPE` and a narration naming the legacy transaction it reverses and
 *     the engine document that supersedes it.
 *   - **Before-image.** The full legacy row set is written to `accounting_audit_log` (durable
 *     connection) as the `before` payload of one `cutover_dedupe_reversed` row per legacy
 *     transaction, BEFORE the reversal posts. No new table: this is what that log is for, and it
 *     is the one store that already survives a rollback of its own writer's transaction.
 *   - **Idempotent.** The reversing document's key is `dedupe-cutover:legacy-tx:{id}`, so a second
 *     `--apply` finds the existing document and posts nothing (`PostingService::post()`'s own
 *     step-1 idempotency lookup returns it rather than throwing).
 *   - **Refuses rather than plugs.** A legacy set that does not balance, or that names an account
 *     the engine will not post to (group, frozen, cross-tenant), is REPORTED BY NAME and skipped.
 *     Inventing a balancing leg is what `accounting:repair`'s 3900 suspense plug did (CT-A1 §1.7,
 *     CT-F4: KWD 233,630.305 parked in an Equity account); this command will not repeat it.
 *
 * ── What counts as "both postings" ───────────────────────────────────────────────────────────
 * A DOCUMENT here is an `invoice_detail_id`, else an `invoice_id`, else a `task_id` — the three
 * keys the legacy writers actually stamp. It is dual-posted when, for one of those keys, there is
 * at least one legacy line (`posting_date IS NULL`) created at or after `--from`, AND at least one
 * engine line (`posting_date IS NOT NULL`) on the same key. `posting_date` is written by exactly
 * one writer in the tree ({@see PostingService::post()}), which is what makes this a fact rather
 * than a heuristic.
 *
 * Reversing documents this command itself posts are excluded from the engine side of that test, so
 * a re-run cannot mistake its own output for the engine posting that justified the first one.
 *
 * ── CAVEAT an operator must read before choosing `--from` ────────────────────────────────────
 * `journal_entries.created_at` is the time the ROW WAS WRITTEN BY WHOEVER WROTE IT — which is not
 * always the time it appeared in the database you are running this against. On the City Travelers
 * dev site the rows arrive by an hourly live->dev mirror (`/home/citycomm/sync/bin/sync-engine.php`,
 * root's crontab) that preserves the LIVE row's own id and timestamps, so a row can be absent from
 * a dump taken at 06:38 UTC and still carry an earlier `created_at` — exactly the "backdated
 * created_at" CT-D1 §0.4i could not explain. Choose `--from` from when the SOURCE system's writes
 * stopped being covered by your rollback point, not from your deploy clock.
 *
 * Measured on that dev database, read-only, 2026-09-10: bounding at the start of the cutover day
 * finds **37 legacy transactions / 167 rows / KWD 19,535.794 of debits** already dual-posted —
 * not the 4 invoices §0.4i counted, because the mirror keeps delivering LIVE's legacy postings
 * hourly for documents the engine replay has already covered. Unbounded, the same query returns
 * 2,081 transactions, which is the whole replayed history and is NOT drift: that is the cutover
 * working as designed. The window is what separates the two, which is why `--from` is required.
 */
class AccountingDedupeCutover extends Command
{
    protected $signature = 'accounting:dedupe-cutover
                            {--company= : Company id to process (required)}
                            {--from= : Only consider legacy rows created at or after this timestamp (required)}
                            {--to= : Optional upper bound on the same column}
                            {--dry-run : Report what would be reversed and write nothing (the default whenever --apply is absent)}
                            {--apply : Actually post the reversing documents}';

    protected $description = 'CT-A5a — reverse the LEGACY half of any document that carries both a legacy and an engine posting after a cutover timestamp. Never deletes; records a before-image; refuses an unbalanced or unpostable legacy set by name.';

    /** Document sub-type stamped on every reversing document this command posts. */
    public const SUB_TYPE = 'CUTOVER_DEDUPE';

    /** Idempotency key prefix — one reversing document per legacy transaction, forever. */
    public const KEY_PREFIX = 'dedupe-cutover:legacy-tx:';

    private int $reversed = 0;

    private int $alreadyReversed = 0;

    /** @var array<int, array{transaction_id: int, reason: string, detail: string}> */
    private array $refused = [];

    public function handle(PostingService $posting, PostingSeam $seam): int
    {
        $companyId = (int) $this->option('company');
        $from = (string) $this->option('from');
        $to = $this->option('to') !== null ? (string) $this->option('to') : null;
        $apply = (bool) $this->option('apply');

        if ($companyId <= 0) {
            $this->error('--company is required and must be a positive company id.');

            return self::FAILURE;
        }

        if ($from === '') {
            $this->error('--from is required: this command only ever looks at a bounded cutover window, never at all of history.');

            return self::FAILURE;
        }

        try {
            $fromAt = Carbon::parse($from);
            $toAt = $to !== null ? Carbon::parse($to) : null;
        } catch (Throwable $e) {
            $this->error('--from/--to must parse as timestamps: '.$e->getMessage());

            return self::FAILURE;
        }

        // The engine must be ON for this company: the reversal is itself an engine document, and
        // PostingService::post() refuses outright when the kill switch is off. Failing here, with
        // a sentence, beats failing later with a PostingEngineDisabledException per document.
        if ($apply && ! $seam->isEnabledFor($companyId)) {
            $this->error('The posting engine is OFF for company '.$companyId.'; the reversing document cannot be posted. Enable it (both halves of the gate) and re-run.');

            return self::FAILURE;
        }

        $this->line(sprintf(
            'accounting:dedupe-cutover — company %d, window %s .. %s, mode %s',
            $companyId,
            $fromAt->toDateTimeString(),
            $toAt?->toDateTimeString() ?? 'now',
            $apply ? 'APPLY' : 'DRY RUN'
        ));

        $legacyTxIds = $this->dualPostedLegacyTransactionIds($companyId, $fromAt, $toAt);

        $this->line(sprintf('  legacy transactions carrying a dual posting: %d', count($legacyTxIds)));

        foreach ($legacyTxIds as $legacyTxId) {
            $this->processOne($companyId, $legacyTxId, $apply, $posting);
        }

        $this->newLine();
        $this->line(sprintf('  reversed:          %d', $this->reversed));
        $this->line(sprintf('  already reversed:  %d', $this->alreadyReversed));
        $this->line(sprintf('  refused:           %d', count($this->refused)));

        foreach ($this->refused as $r) {
            $this->warn(sprintf('    legacy tx %d refused: %s', $r['transaction_id'], $r['reason']));
            $this->warn(sprintf('      %s', $r['detail']));
        }

        // One compact, unwrapped, machine-readable summary line — the console pads and wraps the
        // human-readable lines above, so a script (or a test) that needs the figures reads this.
        $this->line(sprintf(
            'summary reversed=%d already_reversed=%d refused=%d dual_posted=%d',
            $this->reversed,
            $this->alreadyReversed,
            count($this->refused),
            count($legacyTxIds)
        ));

        if (! $apply) {
            $this->newLine();
            $this->line('  DRY RUN — nothing was written. Re-run with --apply to post the reversing documents.');
        }

        // A refusal is a finding the operator must act on, not a soft warning: exit non-zero so a
        // deploy script cannot mistake "reversed 3 of 7" for a clean run. Same convention as
        // accounting:coa-linkage's own blocking-findings exit (CT-A3 R2-5).
        return $this->refused === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Every legacy transaction id, in the window, at least one of whose lines names a document key
     * that ALSO carries an engine line. Deliberately one query per key shape rather than a single
     * clever join: the three keys are independent, a line may carry more than one of them, and the
     * union is what "this document was posted twice" means.
     *
     * @return array<int, int>
     */
    private function dualPostedLegacyTransactionIds(int $companyId, Carbon $fromAt, ?Carbon $toAt): array
    {
        $ids = [];

        foreach (['invoice_detail_id', 'invoice_id', 'task_id'] as $key) {
            $legacy = DB::table('journal_entries')
                ->where('company_id', $companyId)
                ->whereNull('posting_date')
                ->whereNotNull($key)
                ->where('created_at', '>=', $fromAt);

            if ($toAt !== null) {
                $legacy->where('created_at', '<=', $toAt);
            }

            $rows = $legacy->get(['transaction_id', $key]);

            if ($rows->isEmpty()) {
                continue;
            }

            $keyValues = $rows->pluck($key)->filter()->unique()->values()->all();

            $enginePosted = DB::table('journal_entries as je')
                ->join('transactions as t', 't.id', '=', 'je.transaction_id')
                ->where('je.company_id', $companyId)
                ->whereNotNull('je.posting_date')
                ->whereIn('je.'.$key, $keyValues)
                // Never let this command's own reversing documents count as "the engine already
                // posted this" — otherwise a second run would find every document it just fixed.
                ->where(function ($q) {
                    $q->whereNull('t.idempotency_key')
                        ->orWhere('t.idempotency_key', 'not like', self::KEY_PREFIX.'%');
                })
                ->pluck('je.'.$key)
                ->unique()
                ->all();

            if ($enginePosted === []) {
                continue;
            }

            foreach ($rows as $row) {
                if (in_array($row->{$key}, $enginePosted, false) && $row->transaction_id !== null) {
                    $ids[(int) $row->transaction_id] = true;
                }
            }
        }

        $out = array_keys($ids);
        sort($out);

        return $out;
    }

    private function processOne(int $companyId, int $legacyTxId, bool $apply, PostingService $posting): void
    {
        $lines = DB::table('journal_entries')
            ->where('transaction_id', $legacyTxId)
            ->whereNull('posting_date')
            ->orderBy('id')
            ->get();

        if ($lines->isEmpty()) {
            return;
        }

        $debit = round((float) $lines->sum('debit'), 3);
        $credit = round((float) $lines->sum('credit'), 3);

        if (abs($debit - $credit) >= 0.0005) {
            // CT-A1 §1.7 catalogues several one-sided legacy writers (AgentController:427,
            // RefundController:1223, InvoiceController:2588/:2961). Reversing an unbalanced set
            // would require inventing the missing leg. Named and skipped instead.
            $this->refused[] = [
                'transaction_id' => $legacyTxId,
                'reason' => 'UNBALANCED_LEGACY_SET',
                'detail' => sprintf('Dr %.3f vs Cr %.3f over %d lines — reversing it would require inventing the missing leg', $debit, $credit, $lines->count()),
            ];

            return;
        }

        $key = self::KEY_PREFIX.$legacyTxId;

        $existing = DB::table('transactions')
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->whereNull('deleted_at')
            ->first(['id']);

        if ($existing !== null) {
            $this->alreadyReversed++;

            return;
        }

        $header = DB::table('transactions')->where('id', $legacyTxId)->first();

        $reversalLines = [];

        foreach ($lines as $line) {
            $isDebit = round((float) $line->credit, 3) > 0.0;   // mirrored: their credit is our debit
            $amount = round((float) ($isDebit ? $line->credit : $line->debit), 3);

            if ($amount <= 0.0) {
                // A zero-amount legacy line (CT-F20 counted 629 of them) carries no money and has
                // nothing to reverse. Dropping it is safe precisely because the SET balances.
                continue;
            }

            $reversalLines[] = new LineDraft(
                purposeCode: '',
                accountId: (int) $line->account_id,
                side: $isDebit ? 'debit' : 'credit',
                amount: $amount,
                currency: (string) ($line->currency ?: config('accounting.base_currency', 'KWD')),
                originalAmount: $amount,
                exchangeRate: 1.0,
                transactionType: 'CUTOVER_DEDUPE',
                partyAccountRef: $line->type_reference_id !== null ? (int) $line->type_reference_id : null,
                description: 'Cutover dedupe: reversal of legacy line '.$line->id.' — '.(string) $line->description,
                invoiceId: $line->invoice_id !== null ? (int) $line->invoice_id : null,
                invoiceDetailId: $line->invoice_detail_id !== null ? (int) $line->invoice_detail_id : null,
                taskId: $line->task_id !== null ? (int) $line->task_id : null,
                ledgerType: (string) $line->type,
            );
        }

        if ($reversalLines === []) {
            $this->refused[] = [
                'transaction_id' => $legacyTxId,
                'reason' => 'NOTHING_TO_REVERSE',
                'detail' => 'every legacy line on this transaction carries a zero amount',
            ];

            return;
        }

        $narration = sprintf(
            'Cutover dedupe — reversal of legacy transaction %d (%s). The engine already carries this document; the legacy posting is being taken back off the ledger, not deleted.',
            $legacyTxId,
            (string) ($header->description ?? 'no description')
        );

        if (! $apply) {
            $this->reversed++;
            $this->line(sprintf('    would reverse legacy tx %d — %d lines, %.3f', $legacyTxId, count($reversalLines), $debit));

            return;
        }

        // BEFORE-IMAGE FIRST, and durable: if the post below throws, the record of what was about
        // to be touched must survive the rollback that throw causes (CT-A3 R3 §3.4).
        AccountingLog::eventDurable('cutover_dedupe_reversed', [
            'company_id' => $companyId,
            'legacy_transaction_id' => $legacyTxId,
            'idempotency_key' => $key,
            'reason' => 'legacy half of a cutover dual posting',
            'before' => $lines->map(fn ($l) => (array) $l)->all(),
        ]);

        $draft = new DocumentDraft(
            companyId: $companyId,
            branchId: $header->branch_id !== null ? (int) $header->branch_id : null,
            docType: 'REV',
            subType: self::SUB_TYPE,
            docDate: Carbon::parse((string) ($header->transaction_date ?? $lines->first()->transaction_date)),
            narration: $narration,
            lines: $reversalLines,
            idempotencyKey: $key,
            sourceType: 'CutoverDedupe',
            sourceId: $legacyTxId,
            // A locked period must not stop a correction the operator has explicitly asked for on a
            // named window — and a cutover window is, by construction, recent.
            allowLockedPeriods: true,
            overrideReason: 'CT-A5a cutover dedupe of legacy transaction '.$legacyTxId,
        );

        try {
            $posting->post($draft);
            $this->reversed++;
        } catch (Throwable $e) {
            $this->refused[] = [
                'transaction_id' => $legacyTxId,
                'reason' => class_basename($e),
                'detail' => $e->getMessage(),
            ];
        }
    }
}
