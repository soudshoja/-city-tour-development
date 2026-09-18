<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Accounting\BeforeImageOwnership;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CT-TALLY (2026-09-18) — repair the individual `journal_entries` lines that legacy writers put on
 * the wrong side, or zeroed, leaving their document unbalanced.
 *
 * ── What is actually wrong ─────────────────────────────────────────────────────────────────────
 * Measured read-only on `citycomm_city-tour-test` company 1 on 2026-09-18: `SUM(debit) -
 * SUM(credit)` over 110,912 live journal lines is **KWD -721.270** and must be 0.000. Decomposed:
 *
 *   ENGINE lines (`transactions.doc_type IS NOT NULL AND transactions.posting_date IS NOT NULL`):
 *     36,510 lines, Dr 2,517,305.033 = Cr 2,517,305.033, **0 individually unbalanced documents**.
 *   LEGACY lines: 74,384, off by exactly -721.270, in exactly **THREE** documents.
 *
 * The engine did not write any of them. `PostingService::post()` refuses an unbalanced document
 * outright (step 4, `UnbalancedDocumentException`), and every line it wrote passes that check.
 *
 * The three, and the two writers that made them:
 *
 *   #36575 (task 20845 / RCJ75K)  Dr 0.000 / Cr 235.320  = -235.320
 *   #36588 (task 20854 / U99GHW)  Dr 0.000 / Cr 165.100  = -165.100
 *     Both: `TaskController::handleAmountChange()` restated an INERT issuance pair
 *     (`unbilled_cost` 0.000 / `payable` 0.000) and chose each line's column with
 *     `$entry->debit > 0` — a test of the line's VALUE, not its SIDE. The `unbilled_cost` DEBIT leg
 *     failed that test and was rewritten as a second CREDIT. Off by 2x. FIXED FORWARD in that
 *     method (see its `legacyRestatementSide()`); these two rows are the history it left behind.
 *
 *   #36590 (invoice 1966 / INV-2026-02072)  Dr 0.150 / Cr 321.000 = -320.850
 *     `InvoiceController::updateOrCreateEntryByAccount()` matched on (invoice_detail_id,
 *     account_id) alone and, finding exactly one row on that account, claimed the payment receipt's
 *     own gateway-asset DEBIT leg of 320.850 — a line of a DIFFERENT document — and overwrote it
 *     with the gateway-profit writer's own 0.000. FIXED FORWARD by scoping that lookup to its own
 *     `transaction_id`; this row is the history.
 *
 * 100 receipts carry that same overwrite. 99 of them do not show up here because
 * `accounting:repair` has already papered them over into 1654 Suspense / Adjustments — KWD
 * 246,315.070 across 2,320 lines as of 2026-09-13. That suspense balance is NOT in this command's
 * scope: unwinding it is an owner decision about real money, not a tally repair.
 *
 * ── The predicate, and why it cannot guess ─────────────────────────────────────────────────────
 * A line is repaired ONLY when the document it belongs to determines the repair uniquely — i.e.
 * when the single proposed write makes that document balance EXACTLY (within 0.0005). Two kinds:
 *
 *   SIDE_FLIP  — a live line whose `type` carries a fixed side (`unbilled_cost` = debit,
 *                `payable` = credit) is sitting on the other side, and moving its amount across
 *                closes the document's imbalance exactly. Covers #36575 and #36588.
 *   ZEROED_LEG — the document contains EXACTLY ONE live line at debit 0.000 AND credit 0.000, and
 *                writing |imbalance| onto the side the imbalance's sign demands closes the document
 *                exactly. Covers #36590 (the zeroed bank leg takes back its 320.850).
 *
 * Everything else is REFUSED and reported by reason. A document with two candidate lines, a
 * document whose arithmetic does not close, a line whose type says nothing — none of those is
 * recoverable from the ledger alone, and a repair that guessed would be moving money on a hunch.
 *
 * Paging is by `transaction_id > :lastSeen`, never by re-querying "is this document unbalanced":
 * refused documents stay unbalanced forever, so a re-querying loop hands back the same refused
 * batch and spins (the CT-A7 R3 paging defect, inherited deliberately as a fix).
 *
 * ── What this command deliberately does NOT do ─────────────────────────────────────────────────
 * It writes `journal_entries.debit` / `.credit` and nothing else. It never inserts a balancing
 * line, never touches a header, never unwinds a suspense posting, and never touches a document the
 * engine wrote (those are excluded by the LedgerSource conjunction in the selection query, and
 * none is unbalanced anyway).
 *
 * ── Ownership ──────────────────────────────────────────────────────────────────────────────────
 * Before-images go to `coa_linkage_changes` under (`journal_entries`, `debit`) and
 * (`journal_entries`, `credit`), both registered to THIS command in
 * {@see \App\Services\Accounting\BeforeImageOwnership}. `--rollback` filters on the PAIR, so it can
 * never restore another command's before-images as if they were its own.
 *
 * MOVING MONEY IN A LEDGER IS THE OWNER'S CALL. This command is dry-run by default and is not to
 * be run with --apply without that decision.
 */
class RepairJournalSide extends Command
{
    protected $signature = 'accounting:repair-journal-side
                            {--company= : Company id to process (default: every company with accounts)}
                            {--dry-run : Report the full change list without writing anything (the default whenever --apply is absent)}
                            {--apply : Actually write the corrected sides}
                            {--rollback= : Undo a previous --apply run by its run id. One run id PER --apply invocation.}
                            {--limit= : Cap the number of unbalanced DOCUMENTS considered per company, for a staged rollout}
                            {--batch-size=100 : Documents per transaction. One batch = one transaction = one commit.}';

    protected $description = 'CT-TALLY — repair journal lines a legacy writer put on the wrong side or zeroed, where the document determines the repair uniquely. Dry-run by default; records a per-row before-image; refuses every document whose arithmetic does not close exactly.';

    public const SUBJECT_TABLE = 'journal_entries';

    public const COLUMN_DEBIT = 'debit';

    public const COLUMN_CREDIT = 'credit';

    private const TOLERANCE = 0.0005;

    /** `journal_entries.type` values that carry a fixed side. Nothing else is inferable. */
    private const FIXED_SIDE_TYPES = [
        'unbilled_cost' => self::COLUMN_DEBIT,
        'payable' => self::COLUMN_CREDIT,
    ];

    private const REASON_NO_CANDIDATE = 'no line in the document carries a repairable shape (no fixed-side line on the wrong side, no single zeroed line)';

    private const REASON_AMBIGUOUS = 'more than one line could be the repair — the document does not determine which';

    private const REASON_DOES_NOT_CLOSE = 'the candidate repair does not close the imbalance exactly — something else is also wrong with this document';

    public function handle(): int
    {
        $rollbackRunId = $this->option('rollback');

        if ($rollbackRunId !== null && $rollbackRunId !== '') {
            if ($this->option('apply') || $this->option('dry-run')) {
                $this->error('--rollback cannot be combined with --apply or --dry-run.');

                return self::FAILURE;
            }

            return $this->rollback((string) $rollbackRunId);
        }

        $apply = (bool) $this->option('apply');

        if ($apply && $this->option('dry-run')) {
            $this->error('--dry-run and --apply are mutually exclusive. Omit both to dry-run.');

            return self::FAILURE;
        }

        $companyIds = $this->companyIds();

        if ($companyIds === []) {
            $this->warn('No companies with accounts found.');

            return self::SUCCESS;
        }

        $runId = (string) Str::ulid();
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        $batchSize = max(1, (int) $this->option('batch-size'));

        $totals = ['repaired' => 0, 'refused' => 0, 'closed' => 0.0];

        foreach ($companyIds as $companyId) {
            $result = $this->processCompany($companyId, $apply, $runId, $limit, $batchSize);
            $totals['repaired'] += $result['repaired'];
            $totals['refused'] += $result['refused'];
            $totals['closed'] += $result['closed'];
        }

        $this->newLine();
        $this->line(sprintf(
            '%s: %d line(s) %s, %s %s of imbalance %s, %d document(s) refused.',
            $apply ? 'APPLIED' : 'DRY RUN',
            $totals['repaired'],
            $apply ? 'repaired' : 'would be repaired',
            number_format($totals['closed'], 3),
            $this->baseCurrency(),
            $apply ? 'closed' : 'would be closed',
            $totals['refused']
        ));

        if ($apply && $totals['repaired'] > 0) {
            $this->line("  run id: {$runId}");
            $this->line("  undo with: php artisan accounting:repair-journal-side --rollback={$runId}");
            $this->line('  NOTE: each --apply invocation gets its OWN run id. A repair run in several '
                .'bounded passes needs one --rollback per id to undo it all.');
        }

        if (! $apply) {
            $this->newLine();
            $this->line('  Nothing was written. MOVING MONEY IN A LEDGER IS THE OWNER\'S CALL —');
            $this->line('  --apply is for after that decision, not before it.');
        }

        // A refusal is a REPORT, not a failure — same rule as accounting:repair-currency-label.
        return self::SUCCESS;
    }

    /**
     * ── THE SELECTION PREDICATE, stated exactly ─────────────────────────────────────────────────
     *
     *   SELECT je.transaction_id, SUM(je.debit) - SUM(je.credit) AS imbalance
     *     FROM journal_entries je
     *     LEFT JOIN transactions t ON t.id = je.transaction_id
     *    WHERE je.company_id = :companyId
     *      AND je.deleted_at IS NULL
     *      AND NOT (t.doc_type IS NOT NULL AND t.posting_date IS NOT NULL)   -- LEGACY only
     *      AND je.transaction_id > :lastSeen
     *    GROUP BY je.transaction_id
     *   HAVING ABS(SUM(je.debit) - SUM(je.credit)) > 0.0005
     *    ORDER BY je.transaction_id
     *    LIMIT :batchSize
     *
     * The LedgerSource discriminator is the CONJUNCTION. The one-column form
     * (`doc_type IS NOT NULL` alone) misclassifies engine-OFF vouchers, and this command must
     * never propose a write against a document the engine owns.
     *
     * @return array{repaired: int, refused: int, closed: float}
     */
    private function processCompany(int $companyId, bool $apply, string $runId, ?int $limit, int $batchSize): array
    {
        $repaired = 0;
        $refused = 0;
        $closed = 0.0;
        $refusedByReason = [];
        $lastSeen = 0;
        $considered = 0;

        while (true) {
            $take = $batchSize;

            if ($limit !== null) {
                $take = min($take, $limit - $considered);

                if ($take <= 0) {
                    break;
                }
            }

            $documents = DB::table('journal_entries as je')
                ->leftJoin('transactions as t', 't.id', '=', 'je.transaction_id')
                ->where('je.company_id', $companyId)
                ->whereNull('je.deleted_at')
                ->whereNotNull('je.transaction_id')
                ->whereRaw('NOT (t.doc_type IS NOT NULL AND t.posting_date IS NOT NULL)')
                ->where('je.transaction_id', '>', $lastSeen)
                ->groupBy('je.transaction_id')
                ->havingRaw('ABS(SUM(je.debit) - SUM(je.credit)) > ?', [self::TOLERANCE])
                ->orderBy('je.transaction_id')
                ->limit($take)
                ->get(['je.transaction_id', DB::raw('SUM(je.debit) - SUM(je.credit) AS imbalance')]);

            if ($documents->isEmpty()) {
                break;
            }

            $considered += $documents->count();
            $lastSeen = (int) $documents->last()->transaction_id;

            $writes = [];

            foreach ($documents as $document) {
                $plan = $this->planFor((int) $document->transaction_id, (float) $document->imbalance);

                if (is_string($plan)) {
                    $refused++;
                    $refusedByReason[$plan] = ($refusedByReason[$plan] ?? 0) + 1;
                    $this->line(sprintf(
                        '  REFUSED document #%d (off by %s): %s.',
                        (int) $document->transaction_id,
                        number_format((float) $document->imbalance, 3),
                        $plan
                    ));

                    continue;
                }

                $repaired++;
                $closed += abs((float) $document->imbalance);
                $writes[] = $plan;

                $this->line(sprintf(
                    '  document #%d (off by %s): %s journal entry #%d — debit %s -> %s, credit %s -> %s.',
                    (int) $document->transaction_id,
                    number_format((float) $document->imbalance, 3),
                    $plan['kind'],
                    $plan['id'],
                    number_format($plan['before_debit'], 3),
                    number_format($plan['after_debit'], 3),
                    number_format($plan['before_credit'], 3),
                    number_format($plan['after_credit'], 3)
                ));
            }

            if (! $apply || $writes === []) {
                continue;
            }

            // ONE BATCH = ONE TRANSACTION. Before-images first, in the same transaction as the
            // writes they describe, so a crash can never leave a repaired row with no way back.
            DB::transaction(function () use ($writes, $companyId, $runId) {
                $beforeImages = [];

                foreach ($writes as $write) {
                    foreach ([
                        self::COLUMN_DEBIT => ['before' => $write['before_debit'], 'after' => $write['after_debit']],
                        self::COLUMN_CREDIT => ['before' => $write['before_credit'], 'after' => $write['after_credit']],
                    ] as $column => $values) {
                        $beforeImages[] = [
                            'run_id' => $runId,
                            'company_id' => $companyId,
                            'subject_table' => self::SUBJECT_TABLE,
                            'subject_id' => $write['id'],
                            'column_name' => $column,
                            // Recorded, not assumed: the rollback restores what was there, not
                            // what this command believed was there.
                            'before_value' => number_format($values['before'], 3, '.', ''),
                            'after_value' => number_format($values['after'], 3, '.', ''),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                DB::table('coa_linkage_changes')->insert($beforeImages);

                foreach ($writes as $write) {
                    DB::table('journal_entries')
                        ->where('id', $write['id'])
                        // Re-asserted at write time: if anything moved this line between the read
                        // and the write, this update matches nothing rather than overwriting
                        // someone else's later, deliberate value.
                        ->whereRaw('ABS(debit - ?) < ?', [$write['before_debit'], self::TOLERANCE])
                        ->whereRaw('ABS(credit - ?) < ?', [$write['before_credit'], self::TOLERANCE])
                        ->update([
                            'debit' => $write['after_debit'],
                            'credit' => $write['after_credit'],
                            'updated_at' => now(),
                        ]);
                }
            });
        }

        if ($considered === 0) {
            $this->line("company {$companyId}: no unbalanced legacy document.");

            return ['repaired' => 0, 'refused' => 0, 'closed' => 0.0];
        }

        $this->line(sprintf(
            'company %d: %d unbalanced legacy document(s) considered, %d repairable, %d refused.',
            $companyId,
            $considered,
            $repaired,
            $refused
        ));

        foreach ($refusedByReason as $reason => $count) {
            $this->line(sprintf('  REFUSED %d document(s): %s.', $count, $reason));
            $this->line('    Nothing written; nothing guessed.');
        }

        return ['repaired' => $repaired, 'refused' => $refused, 'closed' => $closed];
    }

    /**
     * The single write that closes this document exactly, or the reason there isn't one.
     *
     * @return array{id: int, kind: string, before_debit: float, before_credit: float, after_debit: float, after_credit: float}|string
     */
    private function planFor(int $transactionId, float $imbalance)
    {
        $lines = DB::table('journal_entries')
            ->where('transaction_id', $transactionId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'type', 'debit', 'credit']);

        $candidates = [];

        // SIDE_FLIP — a fixed-side line sitting on the wrong side.
        foreach ($lines as $line) {
            $expected = self::FIXED_SIDE_TYPES[(string) $line->type] ?? null;

            if ($expected === null) {
                continue;
            }

            $debit = (float) $line->debit;
            $credit = (float) $line->credit;

            if ($expected === self::COLUMN_DEBIT && $credit > self::TOLERANCE && abs($debit) < self::TOLERANCE) {
                $candidates[] = [
                    'id' => (int) $line->id,
                    'kind' => 'SIDE_FLIP',
                    'before_debit' => $debit,
                    'before_credit' => $credit,
                    'after_debit' => $credit,
                    'after_credit' => 0.0,
                ];
            }

            if ($expected === self::COLUMN_CREDIT && $debit > self::TOLERANCE && abs($credit) < self::TOLERANCE) {
                $candidates[] = [
                    'id' => (int) $line->id,
                    'kind' => 'SIDE_FLIP',
                    'before_debit' => $debit,
                    'before_credit' => $credit,
                    'after_debit' => 0.0,
                    'after_credit' => $debit,
                ];
            }
        }

        // ZEROED_LEG — exactly one inert line, and only when no SIDE_FLIP candidate exists (a
        // document offering both shapes does not determine which one is the repair).
        if ($candidates === []) {
            $inert = $lines->filter(
                fn ($line) => abs((float) $line->debit) < self::TOLERANCE && abs((float) $line->credit) < self::TOLERANCE
            )->values();

            if ($inert->count() === 1) {
                $line = $inert->first();
                $candidates[] = [
                    'id' => (int) $line->id,
                    'kind' => 'ZEROED_LEG',
                    'before_debit' => 0.0,
                    'before_credit' => 0.0,
                    // A NEGATIVE imbalance means the document is credit-heavy, so the missing
                    // amount belongs on the DEBIT side, and vice versa.
                    'after_debit' => $imbalance < 0 ? abs($imbalance) : 0.0,
                    'after_credit' => $imbalance > 0 ? abs($imbalance) : 0.0,
                ];
            } elseif ($inert->count() > 1) {
                return self::REASON_AMBIGUOUS;
            }
        }

        if ($candidates === []) {
            return self::REASON_NO_CANDIDATE;
        }

        if (count($candidates) > 1) {
            return self::REASON_AMBIGUOUS;
        }

        $plan = $candidates[0];

        // THE SAFETY. Not "this looks like the repair" — "this write, and only this write, makes
        // the document balance". Computed from the document's own arithmetic, so a document with a
        // second, unrelated defect is refused rather than half-repaired.
        $delta = ($plan['after_debit'] - $plan['before_debit']) - ($plan['after_credit'] - $plan['before_credit']);

        if (abs($imbalance + $delta) >= self::TOLERANCE) {
            return self::REASON_DOES_NOT_CLOSE;
        }

        return $plan;
    }

    private function rollback(string $runId): int
    {
        $ownedColumns = [self::COLUMN_DEBIT, self::COLUMN_CREDIT];

        $rows = DB::table('coa_linkage_changes')
            ->where('run_id', $runId)
            ->where('subject_table', self::SUBJECT_TABLE)
            ->whereIn('column_name', $ownedColumns)
            ->whereNull('rolled_back_at')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            // The SYMMETRIC guard, in the shape CT-A7 R3-3 established and CT-A8 keyed by the pair:
            // three distinct outcomes, only one of them success.
            $anyForRun = DB::table('coa_linkage_changes')->where('run_id', $runId);

            if ((clone $anyForRun)->where('subject_table', self::SUBJECT_TABLE)->whereIn('column_name', $ownedColumns)->exists()) {
                $this->line("Run {$runId}: every recorded line was already rolled back. Nothing to do.");

                return self::SUCCESS;
            }

            $foreignPairs = (clone $anyForRun)
                ->distinct()
                ->get(['subject_table', 'column_name'])
                ->map(fn ($r) => $r->subject_table.'.'.$r->column_name)
                ->unique()
                ->values();

            if ($foreignPairs->isNotEmpty()) {
                $this->error("Run '{$runId}' contains before-images this command does not own and must not restore.");
                $this->line('  Run contains before-images for: '.$foreignPairs->implode(', '));
                $this->line('  Undo it with: '.BeforeImageOwnership::rollbackHint($foreignPairs->all(), $runId));
                $this->line('  Nothing was restored.');

                return self::FAILURE;
            }

            $this->error("Run '{$runId}' is not a run id this command recorded.");
            $this->line('  Run ids are echoed by every --apply run and stored in coa_linkage_changes.run_id.');
            $this->line('  Nothing was restored.');

            return self::FAILURE;
        }

        $restored = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, &$restored, &$skipped) {
            foreach ($rows as $row) {
                $column = (string) $row->column_name;
                $current = DB::table('journal_entries')->where('id', $row->subject_id)->value($column);

                // Refuse a row that has MOVED since the run. Putting a before-image back over
                // someone else's later, deliberate value is not an undo.
                if (abs((float) $current - (float) $row->after_value) >= self::TOLERANCE) {
                    $skipped[] = sprintf(
                        'journal_entries #%d.%s: now %s, this run wrote %s — left alone',
                        (int) $row->subject_id,
                        $column,
                        $current === null ? 'NULL' : (string) $current,
                        (string) $row->after_value
                    );

                    continue;
                }

                DB::table('journal_entries')
                    ->where('id', $row->subject_id)
                    ->update([
                        $column => $row->before_value,
                        'updated_at' => now(),
                    ]);

                DB::table('coa_linkage_changes')->where('id', $row->id)->update([
                    'rolled_back_at' => now(),
                    'updated_at' => now(),
                ]);

                $restored++;
            }
        });

        $this->line("Rolled back run {$runId}: {$restored} value(s) restored.");

        foreach ($skipped as $note) {
            $this->warn('  '.$note);
        }

        return $skipped === [] ? self::SUCCESS : self::FAILURE;
    }

    private function baseCurrency(): string
    {
        return strtoupper(trim((string) config('accounting.engine.base_currency', 'KWD')));
    }

    /** @return int[] */
    private function companyIds(): array
    {
        $option = $this->option('company');

        if ($option !== null && $option !== '') {
            return [(int) $option];
        }

        return DB::table('accounts')
            ->whereNull('deleted_at')
            ->distinct()
            ->orderBy('company_id')
            ->pluck('company_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** The subject table this command owns, for the shared ownership check. */
    public static function subjectTable(): string
    {
        return self::SUBJECT_TABLE;
    }

    /** @return string[] */
    public static function beforeImageColumns(): array
    {
        return [self::COLUMN_DEBIT, self::COLUMN_CREDIT];
    }
}
