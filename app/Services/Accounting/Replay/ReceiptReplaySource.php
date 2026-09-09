<?php

declare(strict_types=1);

namespace App\Services\Accounting\Replay;

use App\Http\Controllers\ReceiptVoucherController;
use App\Models\InvoiceReceipt;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\ReceiptPostingRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * CT-A3 wave 2 (W2-1 + W2-2) — the `receipt` class: one `RV` document per `invoice_receipts` row,
 * under `ReceiptVoucherController::buildVoucherDraft()`'s own key `rv:{id}`.
 *
 * The draft comes from that method itself (made public in wave 2 for exactly this reason — a
 * backfill that re-implemented the RV shape would be a second definition of the receipt document,
 * which is how CT-A1 §1.7's twenty refund writers came to disagree with each other). Whether a
 * given row's status posts at all is {@see ReceiptPostingRule}'s decision, not this source's:
 * W2-2 put receipts on R-CT3's configured-status pattern, so `approved` posts, `bounced` /
 * `reversed` / `rejected` do not, and the vocabulary lives in `config('accounting.receipt')`
 * rather than in an `if` here.
 *
 * CT-A2 §3.1 replayed 0 of 109 receipt vouchers; CT-A3 wave 1 replayed 100 of 109 with the 9
 * refusals fully accounted for (3 company-2 rows the engine gate correctly refuses, 3
 * unresolvable-company rows, 3 zero-amount rows).
 *
 * ── Ledger only ─────────────────────────────────────────────────────────────────────────────────
 * This source posts the DOCUMENT. It deliberately does not run
 * `applyAllocationsToInvoices()` — a historical backfill must not rewrite `invoices.status` /
 * `invoice_partials` that the source system already settled years ago. The open-item apply belongs
 * to the live `approve()` path (W2-2), not to a replay of history.
 */
final class ReceiptReplaySource implements ReplaySource
{
    use ChecksExistingDocument;

    /**
     * CT-A3 R2-4. The company {@see self::rows()} was last called for, so {@see self::replay()} can
     * REFUSE a row that resolves elsewhere instead of trusting its caller to have filtered.
     *
     * The report named the structural reason this assertion was missing: `ReplaySource::replay(
     * Model $row)` does not carry `$companyId`. Rather than widen that interface for all six
     * sources (five of which filter in SQL and need nothing), the one source that cannot filter in
     * SQL remembers its own scope. Null means "unscoped" — a direct caller that never went through
     * rows(), for which the pre-existing behaviour stands.
     */
    private ?int $scopedCompanyId = null;

    public function __construct(
        private readonly PostingService $posting,
        private readonly ReceiptPostingRule $rule,
    ) {}

    public function name(): string
    {
        return 'receipt';
    }

    public function idempotencyKeyFor(Model $row): string
    {
        return 'rv:'.$row->getKey();
    }

    public function describe(Model $row): string
    {
        return 'invoice_receipt #'.$row->getKey().' ('.$row->status.')';
    }

    public function rows(int $companyId, ?Carbon $from, ?Carbon $to, ?int $limit): iterable
    {
        // ── CT-A3 R2-4 — VERIFY-CT-A3-STACK-R1 §3.2 D8 (BLOCKER AT THE CUTOVER) ──────────────
        // This method took $companyId and NEVER USED IT. The old docblock argued that was safe:
        // `invoice_receipts.company_id` is NULL on every legacy row (CT-F35), so the whole
        // population is walked and "a row belonging to another company is refused by the engine
        // gate exactly as it would be live". The premise is true and the conclusion is wrong --
        // THE ENGINE GATE IS A PER-COMPANY FEATURE FLAG, NOT A TENANT BOUNDARY. replay() posts
        // through PostingService::post() directly, bypassing the command's own
        // assertEngineGate($companyId), so the moment `accounting:engine 2 --enable` runs -- the
        // documented next cutover step, which the command itself echoes -- a company-1 replay
        // writes real balanced RV documents into COMPANY 2's ledger, counted in company 1's POSTED
        // tally. A later legitimate --company=2 run then reports them `already_posted`, so even the
        // double-run check looks clean.
        //
        // The fix is NOT a `where('company_id', …)` (that column really is NULL on the legacy
        // population, and filtering on it would yield nothing). It is to resolve each row's company
        // through the DOCUMENT'S OWN resolution chain -- the same public, static
        // ReceiptVoucherController::resolveReceiptCompanyId() that buildVoucherDraft() and
        // `accounting:repair-receipt-company` both use, so all three can never disagree about
        // which company a row belongs to -- and yield only the rows that resolve HERE. Never
        // Auth: this runs from the console with no session, exactly like AccountResolver.
        //
        // Cost: one resolution per row rather than one WHERE clause. Measured against what replay()
        // itself then does per row (build a full draft, resolve every account, post a balanced
        // document), it is noise -- and it is the only honest way to filter a five-step precedence
        // chain that no single JOIN expresses.
        $this->scopedCompanyId = $companyId;

        // WINDOW: `COALESCE(doc_date, created_at)`, the SAME expression the ordering uses. It used
        // to be a bare `whereDate('doc_date', …)` against a `COALESCE(...)` ordering (D8's own
        // "Secondary" finding), so a row with a NULL doc_date -- 5 of the 109 on the City Travelers
        // data -- was silently dropped from every --from/--to run with no report line.
        $query = InvoiceReceipt::query()
            ->when($from, fn ($q) => $q->whereRaw('DATE(COALESCE(doc_date, created_at)) >= ?', [$from->toDateString()]))
            ->when($to, fn ($q) => $q->whereRaw('DATE(COALESCE(doc_date, created_at)) <= ?', [$to->toDateString()]))
            ->orderByRaw('COALESCE(doc_date, created_at)')
            ->orderBy('id');

        // --limit is applied to the rows this source actually YIELDS, not to the rows it walks:
        // capping the SQL first would make `--limit 10` on a mixed-tenant table return fewer than
        // ten of this company's rows (possibly none), which is not what an operator smoke-testing a
        // backfill is asking for.
        $yielded = 0;

        foreach ($query->cursor() as $row) {
            if ($this->companyIdFor($row) !== $companyId) {
                continue;
            }

            yield $row;

            $yielded++;

            if ($limit !== null && $yielded >= $limit) {
                return;
            }
        }
    }

    /**
     * CT-A3 R2-4 — which company a receipt row belongs to, by the ONE resolution chain this
     * codebase has.
     *
     * Mirrors {@see ReceiptVoucherController::buildVoucherDraft()}'s own precedence exactly: the
     * row's own `company_id` when it is positive, otherwise the public static resolution chain
     * (invoice -> client/agent -> task -> account -> branch). Null when nothing resolves — such a
     * row is yielded to NO company, which is right: `buildVoucherDraft()` would refuse it with
     * {@see \App\Exceptions\Accounting\UnresolvedReceiptCompanyException} anyway, and refusing it
     * once, under the company that owns the run, is more honest than refusing it under all of them.
     */
    private function companyIdFor(InvoiceReceipt $row): ?int
    {
        $own = (int) ($row->company_id ?? 0);

        if ($own > 0) {
            return $own;
        }

        $resolved = ReceiptVoucherController::resolveReceiptCompanyId($row);

        return $resolved !== null && $resolved > 0 ? $resolved : null;
    }

    public function replay(Model $row): ReplayOutcome
    {
        /** @var InvoiceReceipt $row */
        $amount = round((float) ($row->amount ?? 0), 3);
        // decideFor(), not decide(): decide() takes a STATUS STRING (the live approve() path
        // asks about the status it is moving TO). Passing the row itself was a TypeError that
        // refused all 109 receipts on the first server dry run -- see this wave's report §5.
        $decision = $this->rule->decideFor($row);

        if (! $decision->shouldPost) {
            return ReplayOutcome::skipped($row->id, $decision->reason, $amount);
        }

        try {
            $draft = app(ReceiptVoucherController::class)->buildVoucherDraft($row);

            // CT-A3 R2-4, D8's structural half. rows() already filters, but the tenant boundary
            // must not depend on the caller having used it: assert the DRAFT'S OWN resolved
            // company against the scope this source was asked for, and refuse otherwise. Refuse,
            // not skip -- a foreign row reaching a scoped source is a caller defect worth a line in
            // the run report, not an ordinary "nothing to post".
            if ($this->scopedCompanyId !== null && (int) $draft->companyId !== $this->scopedCompanyId) {
                return ReplayOutcome::refused(
                    $row->id,
                    sprintf(
                        'invoice_receipt #%d resolves to company #%d, but this replay is scoped to company #%d '
                        .'-- refusing rather than posting into another tenant\'s ledger (the engine gate is a '
                        .'feature flag, not a tenant boundary).',
                        (int) $row->getKey(),
                        (int) $draft->companyId,
                        $this->scopedCompanyId
                    ),
                    null,
                    $amount
                );
            }

            $existing = $this->existingDocument($draft->companyId, $draft->idempotencyKey);

            if ($existing !== null) {
                $this->linkDocumentToRow($row, (int) $existing->id);

                return ReplayOutcome::posted($row->id, (int) $existing->id, null, true);
            }

            $posted = $this->posting->post($draft);

            $this->linkDocumentToRow($row, (int) $posted->transaction->id);

            return ReplayOutcome::posted($row->id, (int) $posted->transaction->id, $amount);
        } catch (\Throwable $e) {
            return ReplayOutcome::refused($row->id, $e->getMessage(), $e, $amount);
        }
    }

    /**
     * CT-A3 R2-3 — VERIFY-CT-A3-STACK-R1 §3.2 D7 (BLOCKER). Write the posted document back onto
     * the source row, exactly as the LIVE feeder does
     * ({@see ReceiptVoucherController::postVoucher()}: `$invoiceReceipt->transaction_id =
     * $transaction->id`).
     *
     * WHY THIS IS NOT "the replay mutating history". The class docblock's rule — a historical
     * backfill must not rewrite `invoices.status` / `invoice_partials` that the source system
     * already settled — is about BUSINESS STATE. `transaction_id` is not business state, it is the
     * row's LINKAGE to its own ledger document, and leaving it NULL while a live `rv:{id}` document
     * exists is precisely what made every backfilled receipt un-bounceable and un-deletable: D7's
     * `bounce()` gated on this column and simply skipped the reversal, leaving a collected
     * receivable for money that never arrived, while `destroy()` threw on it.
     *
     * `status` is deliberately NOT touched: whether a row's status should change is
     * {@see ReceiptPostingRule}'s decision and the row already carries a posting status the rule
     * agreed with, or `replay()` would not have reached here. Written with a bare query builder
     * update rather than `$row->save()` so no model event, observer or `updated_at` touch fires on
     * a historical row — and it participates in `--dry-run`'s outer transaction like every other
     * write, so a dry run still writes nothing.
     */
    private function linkDocumentToRow(InvoiceReceipt $row, int $transactionId): void
    {
        if ((int) ($row->transaction_id ?? 0) === $transactionId) {
            return;
        }

        InvoiceReceipt::withoutGlobalScopes()
            ->whereKey($row->getKey())
            ->update(['transaction_id' => $transactionId]);

        $row->transaction_id = $transactionId;
        $row->syncOriginalAttribute('transaction_id');
    }
}
