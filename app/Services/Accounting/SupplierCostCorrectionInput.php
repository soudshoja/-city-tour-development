<?php

declare(strict_types=1);

namespace App\Services\Accounting;

/**
 * W4.C (w4-brief.md — "supplier cost posts in the SALE's own period"). Input to
 * {@see SupplierCostCorrectionDraftBuilder::build()} — a single correction EVENT: the supplier
 * cost the engine already knows for one {@see \App\Models\InvoiceDetail} (posted at sale time by
 * {@see SaleDraftBuilder}, possibly $0.00 for a principal-basis sale where cost was not yet known —
 * see that class's own docblock) turns out to be wrong or newly known, and must be corrected by a
 * DELTA document rather than a full reverse+repost of the original sale.
 *
 * ── Why a delta document, not reverse()/repost() of the whole sale ───────────────────────────────
 * The original sale document's receivable/revenue/margin lines are still correct — only the
 * supplier-cost figure changed. Reversing and reposting the entire sale would needlessly touch the
 * receivable leg (already applied against, possibly already partially receipted) for a correction
 * that is, economically, ONLY about the cost side. A two-line delta document — see
 * {@see SupplierCostCorrectionDraftBuilder::build()} — books exactly the change and nothing else,
 * linked to the original sale via `DocumentDraft::$invoiceId`/`LineDraft::$invoiceDetailId` (never
 * re-derived from a description string).
 *
 * ── The period rule (this class's other half of W4.C) ─────────────────────────────────────────────
 * `$saleDocDate` (the ORIGINAL sale's `docDate`) and `$correctionDate` (when this correction is
 * being entered — normally `now()`, injected rather than read inside the builder so this stays a
 * pure, testable function of its inputs) are BOTH required. See
 * {@see SupplierCostCorrectionDraftBuilder::resolveDocDate()} for exactly how they decide whether
 * the correction posts dated to the sale's own (still-open) period or forward, dated today, as a
 * correction into the currently open period — never a silent backdate into a period that has
 * already closed.
 *
 * P2.5.D fix (verify finding): the original P2.5.D delivery gave {@see SaleDraftBuilder} full
 * recognition-timing awareness but left THIS builder -- the class
 * `TaskController::updateAdminFinancial()`'s late/corrected supplier-cost branch actually uses --
 * with zero awareness of it: `buildPrincipalBasisLines()` unconditionally posted
 * `Dr SERVICE_COST / Cr SERVICE_PAYABLE` regardless of whether the sale being corrected is still
 * sitting in `DEFERRED_REVENUE`/`PREPAID_SUPPLIER_COST`, un-recognised. For the four
 * default-`at_travel` service types (tour/cruise/car/event) that meant a late cost correction was
 * expensed immediately, invisibly to `RevenueRecognitionService` (which only ever reads the
 * deferred/prepaid leaves), defeating the deferral. `$recognitionTiming` + `$alreadyRecognized`
 * close that gap -- see {@see SupplierCostCorrectionDraftBuilder::build()}'s own docblock for
 * exactly how they change which purpose codes the cost/margin leg posts to.
 */
final class SupplierCostCorrectionInput
{
    public function __construct(
        /** One of config('accounting.purpose_codes.service_types') — same value the original sale
         *  document carried for this InvoiceDetail. */
        public readonly string $serviceType,
        /** {@see SaleDraftInput::BASIS_AGENT} | {@see SaleDraftInput::BASIS_PRINCIPAL} — MUST be the
         *  SAME posting basis the original sale was built with; a correction never changes which
         *  shape a sale posted under, only the cost figure within that shape. */
        public readonly string $postingBasis,
        /** What the engine already booked as supplier cost for this InvoiceDetail — the sale's own
         *  `SaleDraftInput::$costAmount` (may legitimately be 0.0: a principal-basis sale whose cost
         *  was not yet known at sale time, per {@see SaleDraftBuilder}'s own docblock — this is
         *  exactly the "genuinely late-arriving cost" case W4.C's residual scope targets). */
        public readonly float $originalCostAmount,
        /** The now-known-correct supplier cost. Must differ from $originalCostAmount by more than
         *  the engine's balance tolerance — see {@see SupplierCostCorrectionDraftBuilder::build()}. */
        public readonly float $correctedCostAmount,
        public readonly int $companyId,
        public readonly int $branchId,
        /** The ORIGINAL sale document's own `docDate` (i.e. the invoice's `invoice_date`) — the
         *  period this correction belongs to when that period is still open. */
        public readonly \DateTimeInterface $saleDocDate,
        /** When this correction is being entered — injected, never read internally, so the period
         *  rule stays a pure function of its inputs. Normally `now()` at the call site. */
        public readonly \DateTimeInterface $correctionDate,
        public readonly ?int $invoiceId = null,
        public readonly ?int $invoiceDetailId = null,
        public readonly ?int $taskId = null,
        public readonly ?int $supplierId = null,
        public readonly ?string $supplierName = null,
        public readonly ?string $taskReference = null,
        /** Defaults to config('accounting.engine.base_currency') inside the builder when null —
         *  same convention as {@see SaleDraftInput}. This builder does not do multi-currency
         *  conversion (matching both SaleDraftBuilder call sites' own base-currency-only convention). */
        public readonly ?string $currency = null,
        public readonly float $exchangeRate = 1.0,
        /** {@see SaleDraftInput::RECOGNITION_AT_ISSUE} | {@see SaleDraftInput::RECOGNITION_AT_TRAVEL}
         *  | null. Must be the SAME recognition timing the original sale posted under -- the
         *  caller resolves {@see SaleDraftBuilder::resolveRecognitionTiming()}, exactly the way
         *  $postingBasis itself is already resolved and passed. `null` (the default, matching
         *  every pre-P2.5.D-fix call site) behaves exactly like `RECOGNITION_AT_ISSUE` -- always
         *  books the real SERVICE_COST/SERVICE_REVENUE leg, preserving this class's original
         *  behaviour byte for byte. */
        public readonly ?string $recognitionTiming = null,
        /** Only consulted when $recognitionTiming === RECOGNITION_AT_TRAVEL. True when
         *  {@see \App\Services\Accounting\RevenueRecognitionService} has already released this
         *  task's deferred sale (its travel/check-in date has passed and
         *  `accounting:recognize-revenue` has run) -- the correction then targets the REAL
         *  SERVICE_COST/SERVICE_REVENUE accounts (same as at_issue), because that is where the
         *  money now lives. `false` (the default) targets PREPAID_SUPPLIER_COST/DEFERRED_REVENUE
         *  instead -- the sale is still deferred, so a late cost correction must correct the
         *  deferred balance, not an expense/margin that has not been recognised yet. The caller
         *  resolves this by checking whether the task still appears in
         *  `RevenueRecognitionService::outstandingByTask()`/`isDeferredOutstanding()`. */
        public readonly bool $alreadyRecognized = false,
    ) {
        if ($recognitionTiming !== null
            && ! in_array($recognitionTiming, [SaleDraftInput::RECOGNITION_AT_ISSUE, SaleDraftInput::RECOGNITION_AT_TRAVEL], true)
        ) {
            throw new \InvalidArgumentException(sprintf(
                "SupplierCostCorrectionInput::\$recognitionTiming must be null, '%s' or '%s'; got %s.",
                SaleDraftInput::RECOGNITION_AT_ISSUE,
                SaleDraftInput::RECOGNITION_AT_TRAVEL,
                var_export($recognitionTiming, true)
            ));
        }
    }
}
