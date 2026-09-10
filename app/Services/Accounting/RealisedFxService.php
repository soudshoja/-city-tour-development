<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Exceptions\Accounting\CrossCurrencyApplyException;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * accounting-builds T1 (Lane A — realised FX on apply). PLAN.md §2 L3-L5, §5 Lane A.
 *
 * Posts the realised exchange gain/loss produced when a foreign-currency payment/credit is
 * APPLIED against a foreign-currency invoice at a rate that differs from the rate the money was
 * originally recorded at. Wired (this task) to the AR apply event only — one call per
 * `payment_applications` row, from {@see \App\Services\PaymentApplicationService::createCreditPaymentCOA()}
 * right after the credit-apply JV itself posts. AP wiring is deferred (L5 — no AP apply registry
 * exists today); {@see self::compute()} is generic over source side so the same class serves AP
 * once that registry exists, with no change to this class.
 *
 * ── Why the amount cannot come from the credit-apply JV's own two lines ─────────────────────────
 * {@see CreditApplicationDraftBuilder} resolves ONE exchange rate per `build()` call and uses it
 * for every line in that document (both the `CLIENT_ADVANCE` debit legs and the `RECEIVABLE_CONTROL`
 * credit leg) — that JV is, by construction, self-consistent at a single rate, so comparing its own
 * two lines against each other can never produce a real difference. The realised FX this class
 * computes instead compares:
 *   - the SOURCE line — the payment/credit's own ORIGINAL posted `CLIENT_ADVANCE`/
 *     `RECEIVABLE_CONTROL` line, from whenever that money first entered the ledger (a TOPUP's own
 *     `Cr CLIENT_ADVANCE` at receipt time, found via `transactions.payment_id`) — against
 *   - the APPLIED line — the invoice's own posted `INV`-document `RECEIVABLE_CONTROL` line, from
 *     whenever the invoice itself was raised (found via `journal_entries.invoice_id` on the
 *     invoice's `INV` transaction).
 * Both are POSTED, historical rates (L4) — never a fresh {@see \App\Http\Traits\CurrencyExchangeTrait::getExchangeRate()}
 * lookup. `D = round(a·r_s − a·r_t, 3)` where `a` is the invoice-currency amount this one
 * application moved.
 *
 * ── DC-aware side mapping (PLAN.md §2 spec, verbatim) ────────────────────────────────────────────
 * The party line's side flips with the SOURCE line's own debit/credit side:
 *   - debit-sourced apply (payment→invoice): D>0 → party Cr / FX_LOSS Dr; D<0 → party Dr / FX_GAIN Cr.
 *   - credit-sourced apply (receipt→invoice): the mapping FLIPS — D>0 → party Dr / FX_GAIN Cr;
 *     D<0 → party Cr / FX_LOSS Dr.
 * Equivalently (see {@see self::compute()}): `isGain = sourceSide==='debit' ? (D<0) : (D>0)`; once
 * `isGain` is known, the shape is constant — `party Dr / FX_GAIN Cr` on a gain, `party Cr /
 * FX_LOSS Dr` on a loss — which is what makes the four-cell census a real oracle: a DC-blind
 * implementation that always applies ONE of the two tables regardless of `sourceSide` passes
 * exactly two of the four cells and fails the other two (MP-1-1).
 *
 * ── L4 skip rule ──────────────────────────────────────────────────────────────────────────────
 * Either line missing, either line's `exchange_rate <= 0` (never engine-posted, or a legacy-era
 * row), or a non-canonical source line (neither/both of debit/credit positive) → no document,
 * `Log::info('accounting.fx_apply_skipped_no_posted_rate', …)`. A zero (or sub-fils) `D` is
 * likewise a no-op (nothing to book), logged separately as it is not a data gap.
 */
final class RealisedFxService
{
    private const FEEDER = 'payment-application.realised-fx';

    public function __construct(
        private readonly AccountResolver $accounts,
        private readonly PostingSeam $seam,
        private readonly PostingService $postingService,
    ) {}

    public static function idempotencyKeyFor(string $idSource, int $id): string
    {
        return "fx-apply:{$idSource}:{$id}";
    }

    /**
     * Pure (read-only — reads the two named journal_entries rows, writes nothing): given the
     * already-resolved (source line, applied line) pair, decide whether a realised-FX document is
     * owed and build its two lines. Returns null for every "nothing to post" case (see class
     * docblock's L4 skip rule and zero-diff rule) — never throws for those, only for genuinely
     * invalid input ({@see ApplyFxInput}'s own constructor guard).
     */
    public function compute(ApplyFxInput $input): ?RealisedFxDraft
    {
        $decimals = (int) config('accounting.engine.base_decimals', 3);
        $baseCurrency = (string) config('accounting.engine.base_currency');

        $sourceLine = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->find($input->sourceLineId);
        $appliedLine = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->find($input->appliedLineId);

        if ($sourceLine === null || $appliedLine === null) {
            $this->logSkip($input, $sourceLine === null ? 'source_line_missing' : 'applied_line_missing');

            return null;
        }

        $sourceRate = (float) $sourceLine->exchange_rate;
        $appliedRate = (float) $appliedLine->exchange_rate;

        if ($sourceRate <= 0.0 || $appliedRate <= 0.0) {
            $this->logSkip($input, $sourceRate <= 0.0 ? 'source_rate_zero' : 'applied_rate_zero');

            return null;
        }

        $sourceDebit = (float) $sourceLine->debit;
        $sourceCredit = (float) $sourceLine->credit;
        $sourceIsDebit = $sourceDebit > 0;
        $sourceIsCredit = $sourceCredit > 0;

        if ($sourceIsDebit === $sourceIsCredit) {
            // Non-canonical shape (both zero or both positive) -- nothing to key the DC-aware
            // mapping off. Skip rather than guess (mirrors PostingService::reverse()'s own
            // NonCanonicalJournalLineException guard, but this class refuses quietly -- a bad
            // legacy row must not block the apply that is already committing).
            $this->logSkip($input, 'source_line_non_canonical');

            return null;
        }

        $sourceSide = $sourceIsDebit ? 'debit' : 'credit';

        // Coordinator steer 2026-09-02, point 2 (same-currency apply rule): AccountResolver has
        // no currency dimension -- per-currency separation lives on the LINE
        // (original_currency), never the leaf -- so nothing upstream of this class refuses a
        // payment recorded in one currency being applied against an invoice denominated in a
        // DIFFERENT one. That is NOT realised FX (same currency, two rates at two points in
        // time) -- it is a genuinely different, disallowed scenario, and D computed across two
        // currencies would be meaningless noise, not a real gain/loss. Loud, rejected, never a
        // silent skip (contrast the L4 skip rule above, which is for genuinely MISSING data).
        $sourceCurrency = strtoupper(trim((string) $sourceLine->original_currency));
        $appliedCurrency = strtoupper(trim((string) $appliedLine->original_currency));

        if ($sourceCurrency !== $appliedCurrency) {
            throw new CrossCurrencyApplyException(
                sourceLineId: (int) $sourceLine->id,
                appliedLineId: (int) $appliedLine->id,
                sourceCurrency: $sourceCurrency,
                appliedCurrency: $appliedCurrency,
                idSource: $input->idSource,
                id: $input->id,
            );
        }

        $a = round($input->appliedFcAmount, $decimals);
        $difference = round(($a * $sourceRate) - ($a * $appliedRate), $decimals);

        // Half the smallest representable unit at $decimals -- rules out float noise from the two
        // multiplications above manufacturing a phantom sub-fils document, without masking a real
        // one-fils difference.
        $epsilon = (1 / (10 ** $decimals)) / 2;

        if (abs($difference) < $epsilon) {
            Log::info('accounting.fx_apply_zero_difference', [
                'feeder' => self::FEEDER,
                'company_id' => $input->companyId,
                'id_source' => $input->idSource,
                'id' => $input->id,
            ]);

            return null;
        }

        // DC-aware mapping (class docblock). $isGain is the ONE place source side and D's sign
        // combine; once known, the debit/credit shape below is constant.
        $isGain = $sourceSide === 'debit' ? ($difference < 0.0) : ($difference > 0.0);
        $amount = round(abs($difference), $decimals);

        $partySide = $isGain ? 'debit' : 'credit';
        $fxSide = $isGain ? 'credit' : 'debit';
        $fxPurpose = $isGain ? 'FX_GAIN_REALISED' : 'FX_LOSS_REALISED';

        $description = sprintf(
            'Realised exchange %s on apply %s:%d',
            $isGain ? 'gain' : 'loss',
            $input->idSource,
            $input->id
        );

        $partyLine = new LineDraft(
            purposeCode: '', // explicit accountId path -- the SOURCE line's own account.
            accountId: (int) $sourceLine->account_id,
            side: $partySide,
            amount: $amount,
            currency: $baseCurrency,
            originalAmount: $amount,
            exchangeRate: 1.0,
            transactionType: 'REALISEDFX',
            partyAccountRef: $sourceLine->type_reference_id,
            description: $description,
            invoiceId: $input->invoiceId,
            ledgerType: $sourceLine->type,
            partyName: $sourceLine->name,
        );

        $fxLine = new LineDraft(
            purposeCode: $fxPurpose,
            accountId: null,
            side: $fxSide,
            amount: $amount,
            currency: $baseCurrency,
            originalAmount: $amount,
            exchangeRate: 1.0,
            transactionType: 'REALISEDFX',
            description: $description,
            invoiceId: $input->invoiceId,
            ledgerType: $isGain ? 'income' : 'expense',
        );

        return new RealisedFxDraft(
            partyLine: $partyLine,
            fxLine: $fxLine,
            amount: $amount,
            isGain: $isGain,
            sourceSide: $sourceSide,
            signedDifference: $difference,
        );
    }

    /**
     * Posts {@see self::compute()}'s draft (when non-null) through {@see PostingSeam}, keyed
     * `"fx-apply:{idSource}:{id}"` — one FXR document per apply event, idempotent (PostingSeam/
     * PostingService's own idempotency-key dedup, same mechanism every other feeder relies on: a
     * retried call returns the SAME PostedDocument, never a second one). The `$legacy` closure
     * passed to the seam is a pure no-op (L2 — there is no legacy FX-on-apply behaviour to
     * preserve): it logs `accounting.feature_skipped_engine_off` and returns null.
     */
    public function postForApply(ApplyFxInput $input): ?PostedDocument
    {
        $draft = $this->compute($input);

        if ($draft === null) {
            return null;
        }

        $document = new DocumentDraft(
            companyId: $input->companyId,
            branchId: $input->branchId,
            docType: 'FXR',
            subType: null,
            docDate: $input->docDate,
            narration: sprintf(
                'Realised exchange %s on apply %s:%d',
                $draft->isGain ? 'gain' : 'loss',
                $input->idSource,
                $input->id
            ),
            lines: [$draft->partyLine, $draft->fxLine],
            idempotencyKey: self::idempotencyKeyFor($input->idSource, $input->id),
            sourceType: 'payment_application',
            sourceId: $input->id,
            invoiceId: $input->invoiceId,
            userId: $input->userId,
        );

        $legacy = function () use ($input) {
            Log::info('accounting.feature_skipped_engine_off', [
                'feeder' => self::FEEDER,
                'company_id' => $input->companyId,
                'id_source' => $input->idSource,
                'id' => $input->id,
            ]);

            return null;
        };

        $posted = $this->seam->post($document, $legacy, self::FEEDER);

        return $posted instanceof PostedDocument ? $posted : null;
    }

    /**
     * Wiring convenience for {@see \App\Services\PaymentApplicationService::createCreditPaymentCOA()}:
     * resolves `$application`'s source line (via `transactions.payment_id` -- L5's "credit
     * linkage") and `$invoice`'s own posted `INV` receivable line, then delegates to
     * {@see self::postForApply()}. Returns null (no document, already logged) whenever either
     * side cannot be resolved -- a refund-sourced application (`Credit::REFUND` carries no posted-
     * transaction FK today) always takes this path, by design (see class docblock's L4 rule).
     */
    public function postForApplication(
        CreditApplicationInput $application,
        Invoice $invoice,
        int $companyId,
        ?int $branchId,
        \DateTimeInterface $docDate,
        ?int $userId = null,
    ): ?PostedDocument {
        if ($application->amountApplied <= 0.0) {
            // Mirrors CreditApplicationDraftBuilder's own `if ($amountApplied <= 0) continue;` --
            // nothing was actually applied for this row, so there is no FX to realise either.
            return null;
        }

        $sourceLineId = $this->resolveSourceLineId($application, $companyId);
        $appliedLineId = $sourceLineId !== null ? $this->resolveAppliedLineId($invoice, $companyId) : null;

        if ($sourceLineId === null || $appliedLineId === null) {
            Log::info('accounting.fx_apply_skipped_no_posted_rate', [
                'feeder' => self::FEEDER,
                'company_id' => $companyId,
                'invoice_id' => $invoice->id,
                'id_source' => $application->idSource,
                'id' => $application->id,
                'reason' => $sourceLineId === null ? 'source_line_unresolved' : 'applied_line_unresolved',
            ]);

            return null;
        }

        $decimals = (int) config('accounting.engine.base_decimals', 3);

        $input = new ApplyFxInput(
            companyId: $companyId,
            branchId: $branchId,
            sourceLineId: $sourceLineId,
            appliedLineId: $appliedLineId,
            appliedFcAmount: round($application->amountApplied, $decimals),
            idSource: $application->idSource,
            id: $application->id,
            docDate: $docDate,
            invoiceId: $invoice->id,
            userId: $userId,
        );

        return $this->postForApply($input);
    }

    /**
     * Un-apply wiring (L5): when the `payment_applications` row(s) behind one apply event are
     * soft-deleted (today: {@see \App\Http\Controllers\InvoiceController::delete()}'s
     * `reverseInvoiceLedger()`, which already sweeps every LIVE posted transaction whose
     * `invoice_id` matches -- this class's FXR documents are swept there for free because
     * {@see self::postForApply()} always sets `DocumentDraft::$invoiceId`), reverse the matching
     * FXR document if one was ever posted. Idempotent: a second call after the document is already
     * reversed returns {@see PostingService::reverse()}'s own idempotent result (the existing
     * reversal), never double-reverses. Returns null when no live FXR document exists for this
     * apply event (nothing was ever posted, or it is already reversed and gone -- see
     * `PostingService::reverse()`'s own `posting_status='posted'` filter below).
     */
    public function reverseForApply(
        int $companyId,
        string $idSource,
        int $id,
        \DateTimeInterface $reversalDate,
        ?int $userId = null,
    ): ?PostedDocument {
        $transaction = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', self::idempotencyKeyFor($idSource, $id))
            ->where('posting_status', 'posted')
            ->first();

        if ($transaction === null) {
            return null;
        }

        return $this->postingService->reverse($transaction, $reversalDate, $userId);
    }

    /**
     * Resolves `$application`'s SOURCE line -- the payment's own original posted
     * `CLIENT_ADVANCE`/`RECEIVABLE_CONTROL` line -- via `transactions.payment_id` (L5). Only
     * `$application->sourceType === 'payment'` is resolvable today: a refund-sourced application
     * (`Credit::REFUND`) has no posted-transaction FK to walk (`Refund` carries no
     * `transactions.payment_id`-equivalent linkage in this schema) and is left unresolved, hitting
     * the L4 skip path in the caller -- an honest, documented gap rather than a guessed linkage.
     */
    private function resolveSourceLineId(CreditApplicationInput $application, int $companyId): ?int
    {
        if ($application->sourceType !== 'payment' || $application->sourceId === null) {
            return null;
        }

        $transaction = $this->resolveLiveSourceTransaction((int) $application->sourceId, $companyId);

        if ($transaction === null) {
            return null;
        }

        $accountIds = $this->sourcePurposeAccountIds($companyId);

        $line = JournalEntry::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('transaction_id', $transaction->id)
            ->whereIn('account_id', $accountIds)
            ->orderBy('id')
            ->first(['id']);

        return $line?->id;
    }

    /**
     * Resolves the invoice's own posted `INV`-document `RECEIVABLE_CONTROL` line -- never the
     * credit-apply JV's own credit line, which also carries `invoice_id = $invoice->id` (the
     * `transactions.doc_type = 'INV'` filter is what tells the two apart). Earliest such line wins
     * when more than one exists (there should be exactly one for a normal single-INV invoice).
     *
     * accounting-builds T1 POST-FIX RE-VERIFY (defect V-6). `posting_status = 'posted'` and the
     * `transactions.deleted_at` exclusion are LOAD-BEARING, not tidiness. V-1 fixed the SOURCE
     * side's repost blindness; the applied side had the same blindness pointing the other way.
     * `PostingService::repost()` leaves the reversed ORIGINAL row intact -- same `doc_type = 'INV'`,
     * same `invoice_id` on its lines, and holding the LOWEST `journal_entries.id` -- so
     * "earliest wins" silently returned the DEAD line and computed the whole realised-FX
     * difference against a rate the invoice no longer carries. (The reversal document itself is
     * `doc_type = 'REV'` and was already excluded; the reversed original was not.) Reached in
     * production by {@see \App\Http\Controllers\InvoiceController::repostInvoiceTransactionsWithNewDate()}
     * and the sale-document repost paths. Legacy pre-engine rows are unaffected: the
     * `posting_status` column's migration default is `'posted'` precisely so existing rows keep
     * matching. Pinned by
     * RealisedFxRepostChainTest::test_applied_line_uses_the_live_invoice_document_after_a_repost().
     */
    private function resolveAppliedLineId(Invoice $invoice, int $companyId): ?int
    {
        $receivableAccountId = $this->accounts->resolve('RECEIVABLE_CONTROL', $companyId)->id;

        $line = JournalEntry::withoutGlobalScopes()
            ->whereNull('journal_entries.deleted_at')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.posting_status', 'posted')
            ->where('transactions.doc_type', 'INV')
            ->where('transactions.company_id', $companyId)
            ->where('journal_entries.invoice_id', $invoice->id)
            ->where('journal_entries.account_id', $receivableAccountId)
            ->orderBy('journal_entries.id')
            ->select('journal_entries.id')
            ->first();

        return $line?->id;
    }

    /**
     * accounting-builds T1 VERIFY ROUND (defect V-1). Finds the payment's LIVE posted receipt
     * document.
     *
     * `WHERE payment_id = P AND posting_status = 'posted'` alone is NOT sufficient, and
     * {@see PostingService}'s own class docblock says so verbatim: `repost()` forces the
     * REPLACEMENT draft's `paymentId` to NULL unconditionally, so after a repost the only row that
     * ever carried `payment_id = P` is the ORIGINAL, now flipped to `posting_status = 'reversed'`
     * — "a query such as `WHERE payment_id = P AND posting_status = 'posted'` finds NEITHER the
     * reversal nor the replacement". Reposting a receipt is an ordinary production operation
     * ({@see \App\Http\Controllers\ReceiptVoucherController::update()},
     * {@see \App\Http\Controllers\BankPaymentController::update()}), so without this fallback every
     * realised-FX document for an edited receipt was silently skipped — the books quietly missing
     * a real gain/loss, with only an `accounting.fx_apply_skipped_no_posted_rate` info log.
     *
     * The fallback follows the ONLY linkage PostingService itself sanctions ("The only reliable
     * way to find a repost's REPLACEMENT is by its idempotency key").
     *
     * CT-A3 R2-2 UPDATE. `repost()` used to suffix the replacement's key with
     * `":repost:{$old->id}"` ONLY when it collided with `$old`'s own key, which nested on a chained
     * repost and — the D6 blocker this lane fixed — silently stopped happening from the SECOND
     * edit onwards. It now always mints `"{base}:rev{n}"` from the document's own base key, so a
     * document's revisions are a FLAT family (`K`, `K:rev1`, `K:rev2`, …) instead of a nested
     * chain. That makes this resolution strictly simpler and strictly more robust: the live
     * document is the one member of the base key's family still at `posting_status = 'posted'`,
     * found in ONE query, with no hop bound to exceed and no chain to break halfway. Legacy
     * `":repost:{id}"` members — every document edited before R2-2 — are matched by the same
     * family predicate, including the nested ones, so nothing already on a ledger becomes
     * unfollowable.
     *
     * When no live family member exists (every revision reversed, or the replacement used a
     * genuinely unrelated key) the caller's skip stands, but it is logged DISTINCTLY
     * (`reason: 'source_document_reposted_unresolvable'`) so the miss is triageable rather than
     * indistinguishable from "this payment was never engine-posted at all".
     */
    private function resolveLiveSourceTransaction(int $paymentId, int $companyId): ?Transaction
    {
        $live = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('payment_id', $paymentId)
            ->where('posting_status', 'posted')
            ->orderBy('id')
            ->first(['id']);

        if ($live !== null) {
            return $live;
        }

        $node = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('payment_id', $paymentId)
            ->where('posting_status', 'reversed')
            ->orderByDesc('id')
            ->first(['id', 'idempotency_key', 'posting_status']);

        $reversedOriginalExists = $node !== null;

        $key = $node !== null ? (string) $node->idempotency_key : '';

        if ($key !== '') {
            // The document's BASE key: strip one revision marker in either convention, so this
            // works whether the reversed row we found is the original itself or an intermediate
            // revision. Same normalisation PostingService::repostBaseKey() applies when it mints
            // the next revision, restated here rather than reached into because that method is
            // private to the engine and this is a read-only consumer.
            $base = (string) preg_replace('/(?::rev\d+|:repost:\d+)$/', '', $key);
            $prefix = addcslashes($base, '%_\\');

            $replacement = Transaction::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('company_id', $companyId)
                ->where('posting_status', 'posted')
                ->where(function ($q) use ($base, $prefix) {
                    $q->where('idempotency_key', $base)
                        ->orWhere('idempotency_key', 'like', $prefix.':rev%')
                        ->orWhere('idempotency_key', 'like', $prefix.':repost:%');
                })
                ->orderByDesc('id')
                ->first(['id', 'idempotency_key', 'posting_status']);

            if ($replacement !== null) {
                return $replacement;
            }
        }

        if ($reversedOriginalExists) {
            // A reposted receipt whose replacement could not be followed — distinct from "this
            // payment was never engine-posted at all", which the caller logs as
            // 'source_line_unresolved'.
            Log::info('accounting.fx_apply_skipped_no_posted_rate', [
                'feeder' => self::FEEDER,
                'company_id' => $companyId,
                'payment_id' => $paymentId,
                'reason' => 'source_document_reposted_unresolvable',
            ]);
        }

        return null;
    }

    /** @return int[] */
    private function sourcePurposeAccountIds(int $companyId): array
    {
        return array_values(array_unique([
            $this->accounts->resolve('CLIENT_ADVANCE', $companyId)->id,
            $this->accounts->resolve('RECEIVABLE_CONTROL', $companyId)->id,
        ]));
    }

    private function logSkip(ApplyFxInput $input, string $reason): void
    {
        Log::info('accounting.fx_apply_skipped_no_posted_rate', [
            'feeder' => self::FEEDER,
            'company_id' => $input->companyId,
            'id_source' => $input->idSource,
            'id' => $input->id,
            'reason' => $reason,
        ]);
    }
}
