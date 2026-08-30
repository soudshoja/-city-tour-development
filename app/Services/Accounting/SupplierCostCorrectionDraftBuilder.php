<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Support\Carbon;

/**
 * W4.C (w4-brief.md, target-spec.md §B "W4.C — supplier cost posts in the sale's own period",
 * orchestrator ruling O14). W3d's {@see SaleDraftBuilder} already posts real supplier cost INSIDE
 * the sale document itself for the normal case (cost known at sale time), which makes "cost posts
 * in the sale's own period" trivially true — see that class's own docblock and
 * `.planning/accounting-waves/w3/sale-shape-audit.md`. This class is W4.C's now-narrower residual
 * scope: a GENUINELY late-arriving or corrected supplier cost — discovered or corrected AFTER the
 * sale already posted — must still land in the sale's own period when that period is still open,
 * and must NEVER be silently backdated into a period that has already closed; a late correction
 * against a closed period posts today instead, forward-dated, explicitly linked to the original
 * sale (never re-derived from a description string — see {@see SupplierCostCorrectionInput}).
 *
 * ── The interim `5221` accrual this class replaces ────────────────────────────────────────────────
 * Per target-spec.md §B ("W4.C — supplier cost posts in the sale's own period") and
 * `Accounting Gap/22-plan-amendments.md` §2.2/§4.1: until this mechanism existed, a company-borne
 * negative margin discovered late kept an interim `Dr 5221 Company Loss on Sales / Cr <supplier
 * payable accrual>` so the loss still landed in the correct period. This class's whole point is to
 * make that interim accrual unnecessary for the timing problem it existed to solve: a late cost
 * correction now posts the REAL delta (`SERVICE_COST`/`SERVICE_PAYABLE` or the sign-aware
 * `SERVICE_REVENUE` margin leg — see {@see self::build()}) dated correctly, so nothing needs to be
 * temporarily parked in `5221` to keep the period right. This class therefore NEVER references
 * `5221`/`COMPANY_LOSS_ON_SALES` under any purpose code, account id, or description — see the ON-
 * path test asserting exactly that. (The SEPARATE double-booking bug — `addJournalEntry()`'s own
 * `$isSupplierLoss` branch still calling `createSupplierLossEntries()`, which posts `5221` for a
 * negative-margin sale AT SALE TIME, not as a late correction — is explicitly W4.A's own scope per
 * w4-brief.md's "Known gap carried into this lane" note, not touched here.)
 *
 * ── The period rule ────────────────────────────────────────────────────────────────────────────────
 * `accounting_periods`/a real period-close table does not exist yet — {@see PeriodGuard} is still
 * the documented P5.1 no-op stub (see that class's own docblock) — so there is no live "is this
 * month closed" fact this builder can query. Per `.planning/accounting-waves/period-lock-design.md`
 * §3, periods close MONTHLY; this class uses that as its own, self-contained, documented proxy:
 * the sale's period is treated as "still open" when the correction is being entered in the SAME
 * calendar month (year+month) as the original sale's own `docDate`, and "closed" otherwise. This is
 * a deliberate, narrow business rule inside THIS builder only — it does not touch `PeriodGuard` or
 * `PostingService`, and does not claim to enforce anything (w4-brief.md's binding "Period model"
 * section: "W4 does not add its own period check" — this is choosing the correct `docDate` to HAND
 * to the existing seam, not a second gate). Once a real `accounting_periods` table/PeriodGuard
 * ships, {@see self::resolveDocDate()} is the one place a future change would swap the calendar-
 * month proxy for a real period lookup, without touching this class's public contract or its
 * caller.
 *
 * ── What this class deliberately does NOT do ──────────────────────────────────────────────────────
 *   - It does not call {@see PostingSeam} or {@see PostingService} — it only builds the
 *     {@see DocumentDraft}, matching {@see CreditApplicationDraftBuilder}'s own convention. Wiring a
 *     feeder onto it is that feeder's own call site's job.
 *   - It does not resolve `$companyId`/`$branchId` from `Auth::user()` — both are plain,
 *     already-resolved {@see SupplierCostCorrectionInput} fields, same queue/webhook-safety
 *     convention as every other engine-layer class in this codebase.
 *   - It does not do multi-currency conversion (see {@see SupplierCostCorrectionInput}'s own
 *     docblock).
 *   - It does not decide WHETHER a correction is needed (that is the caller's job: compare old vs.
 *     new cost and only construct a {@see SupplierCostCorrectionInput} when they genuinely differ)
 *     — {@see self::build()} throws when the two amounts are within the engine's own balance
 *     tolerance of each other, since a delta document with nothing to post is not representable.
 *   - It does not query the ledger to decide whether the sale is still deferred (that would break
 *     the "pure function of its inputs" convention every other class in this file follows) —
 *     {@see SupplierCostCorrectionInput::$recognitionTiming}/`$alreadyRecognized` carry that fact
 *     in from the caller, which resolves it via {@see RevenueRecognitionService}.
 *
 * ── P2.5.D fix (verify finding) — recognition-timing purpose-code substitution ────────────────────
 * When `$input->recognitionTiming === SaleDraftInput::RECOGNITION_AT_TRAVEL` AND
 * `$input->alreadyRecognized === false`, the cost/margin leg this class posts is substituted
 * exactly the same way {@see SaleDraftBuilder::buildLines()} substitutes them at sale time — same
 * line count, same amounts/sides, only the purpose code (and `serviceType: null` on that one line,
 * since these are GLOBAL leaves) differs:
 *   - Agent basis ({@see self::buildAgentBasisLines()}): the sign-aware `SERVICE_REVENUE` margin
 *     leg becomes `DEFERRED_REVENUE`. `SERVICE_PAYABLE` is UNCHANGED (agent basis never defers
 *     cost — see {@see SaleDraftBuilder}'s own docblock; the same reasoning applies verbatim here).
 *   - Principal basis ({@see self::buildPrincipalBasisLines()}): `SERVICE_COST` becomes
 *     `PREPAID_SUPPLIER_COST`. `SERVICE_PAYABLE` is UNCHANGED (the liability to the actual
 *     supplier is real regardless of when the agency recognises its own cost).
 * Any other combination (`recognitionTiming` null/`RECOGNITION_AT_ISSUE`, or `alreadyRecognized`
 * true — the sale has already been released by `accounting:recognize-revenue`) posts to the REAL
 * accounts exactly as before this fix, since that is genuinely where the money now lives.
 */
final class SupplierCostCorrectionDraftBuilder
{
    /**
     * @throws \InvalidArgumentException When $input->correctedCostAmount does not differ from
     *                                   $input->originalCostAmount by more than the engine's
     *                                   balance tolerance — a correction with no real cost change
     *                                   has nothing to post.
     */
    public function build(SupplierCostCorrectionInput $input): DocumentDraft
    {
        $tolerance = $this->resolveTolerance();
        $delta = round($input->correctedCostAmount - $input->originalCostAmount, $this->resolveDecimals());

        if (abs($delta) <= $tolerance) {
            throw new \InvalidArgumentException(sprintf(
                'SupplierCostCorrectionDraftBuilder::build() requires a real cost change: '
                .'originalCostAmount (%.3f) and correctedCostAmount (%.3f) are within the engine '
                .'balance tolerance of each other — there is nothing to post.',
                $input->originalCostAmount,
                $input->correctedCostAmount
            ));
        }

        $docDate = $this->resolveDocDate($input->saleDocDate, $input->correctionDate);
        $isForwardCorrection = $this->isForwardCorrection($input->saleDocDate, $input->correctionDate);

        // P2.5.D fix (verify finding) — see class docblock's own "recognition-timing
        // purpose-code substitution" note.
        $isDeferred = $input->recognitionTiming === SaleDraftInput::RECOGNITION_AT_TRAVEL
            && ! $input->alreadyRecognized;

        $lines = $input->postingBasis === SaleDraftInput::BASIS_PRINCIPAL
            ? $this->buildPrincipalBasisLines($input, $delta, $isDeferred)
            : $this->buildAgentBasisLines($input, $delta, $isDeferred);

        $narration = sprintf(
            '%s supplier cost correction for %s (%.3f -> %.3f)',
            $isForwardCorrection ? 'Forward-dated' : 'Same-period',
            $input->taskReference ?? ('task #'.($input->taskId ?? 'n/a')),
            $input->originalCostAmount,
            $input->correctedCostAmount
        );

        return new DocumentDraft(
            companyId: $input->companyId,
            branchId: $input->branchId,
            docType: 'JV',
            // transactions.sub_type is varchar(16) (migration 2026_08_24_120004) -- 'COST_CORRECTION'
            // (15 chars) fits; the more descriptive 'SUPPLIER_COST_CORRECTION' does not.
            subType: 'COST_CORRECTION',
            docDate: $docDate,
            narration: $narration,
            lines: $lines,
            idempotencyKey: PaymentIdempotencyKey::forSupplierCostCorrection(
                (int) ($input->invoiceDetailId ?? 0),
                $input->correctedCostAmount
            ),
            sourceType: 'Invoice',
            sourceId: $input->invoiceId,
            invoiceId: $input->invoiceId,
        );
    }

    /**
     * The period rule (class docblock): the sale's own docDate when its calendar month (year+month)
     * still matches the correction date's; otherwise the correction date itself — a forward
     * correction into the currently open period, never a backdate into a month that has rolled
     * over. Pure function of its two inputs; never reads the clock itself.
     */
    public function resolveDocDate(\DateTimeInterface $saleDocDate, \DateTimeInterface $correctionDate): \DateTimeInterface
    {
        return $this->isForwardCorrection($saleDocDate, $correctionDate)
            ? Carbon::instance($correctionDate)
            : Carbon::instance($saleDocDate);
    }

    /**
     * True when the sale's own calendar month has already rolled over by the time this correction
     * is entered — i.e. this correction cannot be dated into the sale's own period and must instead
     * post today, forward-dated, linked to the sale. See class docblock's "period rule" section for
     * why calendar-month is this builder's own documented proxy for "period closed".
     */
    public function isForwardCorrection(\DateTimeInterface $saleDocDate, \DateTimeInterface $correctionDate): bool
    {
        $sale = Carbon::instance($saleDocDate);
        $correction = Carbon::instance($correctionDate);

        return $sale->format('Y-m') !== $correction->format('Y-m');
    }

    /**
     * Agent (NET) basis: the cost figure lives on `SERVICE_PAYABLE` (the supplier's open item),
     * with margin on `SERVICE_REVENUE` netting sell − cost, sign-aware (see
     * {@see SaleDraftBuilder::buildAgentBasisLines()}). A cost increase ($delta > 0) means MORE is
     * owed to the supplier and LESS margin was actually earned: Cr `SERVICE_PAYABLE` / Dr
     * `SERVICE_REVENUE`, both abs($delta). A cost decrease is the mirror image. Exactly 2 lines,
     * self-balancing; NEVER touches `5221`/`COMPANY_LOSS_ON_SALES` regardless of sign (class
     * docblock).
     *
     * @return LineDraft[]
     */
    private function buildAgentBasisLines(SupplierCostCorrectionInput $input, float $delta, bool $isDeferred): array
    {
        $currency = $this->resolveCurrency($input);
        $amount = abs($delta);
        $costIncreased = $delta > 0;

        $payableSide = $costIncreased ? 'credit' : 'debit';
        $revenueSide = $costIncreased ? 'debit' : 'credit';

        // P2.5.D fix: agent basis never defers cost (SERVICE_PAYABLE below is always real — same
        // reasoning as SaleDraftBuilder::buildAgentBasisLines()); only the margin leg's purpose
        // code is affected.
        $marginPurposeCode = $isDeferred ? 'DEFERRED_REVENUE' : 'SERVICE_REVENUE';
        $marginServiceType = $isDeferred ? null : $input->serviceType;

        return [
            new LineDraft(
                purposeCode: 'SERVICE_PAYABLE',
                accountId: null,
                side: $payableSide,
                amount: $amount,
                currency: $currency,
                originalAmount: $amount,
                exchangeRate: $input->exchangeRate,
                transactionType: $costIncreased ? 'SUPPLIERCREDITED' : 'SUPPLIERDEBITED',
                partyAccountRef: $input->supplierId,
                description: $this->payableDescription($input, $costIncreased),
                serviceType: $input->serviceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'payable',
                partyName: $input->supplierName,
            ),
            new LineDraft(
                purposeCode: $marginPurposeCode,
                accountId: null,
                side: $revenueSide,
                amount: $amount,
                currency: $currency,
                originalAmount: $amount,
                exchangeRate: $input->exchangeRate,
                transactionType: $costIncreased ? 'CONTRA_INCOME' : 'INCOME',
                description: $this->marginDescription($input, $costIncreased),
                serviceType: $marginServiceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                // Matches SaleDraftBuilder's own convention: both directions of this leg are
                // 'income' (a contra-income debit is not a real expense leg), even when
                // $isDeferred swaps the purpose code to DEFERRED_REVENUE (a liability) — see
                // SaleDraftBuilder::buildAgentBasisLines()'s own note on this same convention.
                ledgerType: 'income',
            ),
        ];
    }

    /**
     * Principal (GROSS) basis: cost lives on its own cost-of-sales pair, `Dr SERVICE_COST / Cr
     * SERVICE_PAYABLE` (see {@see SaleDraftBuilder::buildPrincipalBasisLines()}), independent of
     * revenue (which stays the full sell price regardless of cost). A cost increase books MORE
     * cost-of-sales and MORE payable: Dr `SERVICE_COST` / Cr `SERVICE_PAYABLE`, both abs($delta). A
     * cost decrease is the mirror image. This is also the exact shape for the "cost was not known
     * at sale time" case ($input->originalCostAmount === 0.0, the cost pair {@see SaleDraftBuilder}
     * itself omitted at sale time) — the delta there IS the full corrected cost, so this produces
     * precisely the pair the sale document would have carried had the cost been known at sale time.
     *
     * @return LineDraft[]
     */
    private function buildPrincipalBasisLines(SupplierCostCorrectionInput $input, float $delta, bool $isDeferred): array
    {
        $currency = $this->resolveCurrency($input);
        $amount = abs($delta);
        $costIncreased = $delta > 0;

        $costSide = $costIncreased ? 'debit' : 'credit';
        $payableSide = $costIncreased ? 'credit' : 'debit';

        // P2.5.D fix: SERVICE_PAYABLE below is always real (the liability to the actual supplier
        // — same reasoning as SaleDraftBuilder::buildPrincipalBasisLines()); only the cost leg's
        // purpose code is affected.
        $costPurposeCode = $isDeferred ? 'PREPAID_SUPPLIER_COST' : 'SERVICE_COST';
        $costServiceType = $isDeferred ? null : $input->serviceType;

        return [
            new LineDraft(
                purposeCode: $costPurposeCode,
                accountId: null,
                side: $costSide,
                amount: $amount,
                currency: $currency,
                originalAmount: $amount,
                exchangeRate: $input->exchangeRate,
                transactionType: 'COSTOFSALES',
                description: $this->costDescription($input, $costIncreased),
                serviceType: $costServiceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'expense',
            ),
            new LineDraft(
                purposeCode: 'SERVICE_PAYABLE',
                accountId: null,
                side: $payableSide,
                amount: $amount,
                currency: $currency,
                originalAmount: $amount,
                exchangeRate: $input->exchangeRate,
                transactionType: $costIncreased ? 'SUPPLIERCREDITED' : 'SUPPLIERDEBITED',
                partyAccountRef: $input->supplierId,
                description: $this->payableDescription($input, $costIncreased),
                serviceType: $input->serviceType,
                invoiceId: $input->invoiceId,
                invoiceDetailId: $input->invoiceDetailId,
                taskId: $input->taskId,
                ledgerType: 'payable',
                partyName: $input->supplierName,
            ),
        ];
    }

    private function payableDescription(SupplierCostCorrectionInput $input, bool $costIncreased): string
    {
        $reference = $input->taskReference ?? ('task #'.($input->taskId ?? 'n/a'));

        return $costIncreased
            ? sprintf('Supplier cost correction (increase) for %s', $reference)
            : sprintf('Supplier cost correction (decrease) for %s', $reference);
    }

    private function marginDescription(SupplierCostCorrectionInput $input, bool $costIncreased): string
    {
        $reference = $input->taskReference ?? ('task #'.($input->taskId ?? 'n/a'));

        return $costIncreased
            ? sprintf('Margin reduced by late supplier cost correction on %s', $reference)
            : sprintf('Margin increased by late supplier cost correction on %s', $reference);
    }

    private function costDescription(SupplierCostCorrectionInput $input, bool $costIncreased): string
    {
        $reference = $input->taskReference ?? ('task #'.($input->taskId ?? 'n/a'));

        return $costIncreased
            ? sprintf('Cost-of-sales correction (increase) for %s', $reference)
            : sprintf('Cost-of-sales correction (decrease) for %s', $reference);
    }

    private function resolveCurrency(SupplierCostCorrectionInput $input): string
    {
        return $input->currency ?? (string) config('accounting.engine.base_currency');
    }

    private function resolveTolerance(): float
    {
        return (float) config('accounting.engine.balance_tolerance', 0.0005);
    }

    private function resolveDecimals(): int
    {
        return (int) config('accounting.engine.base_decimals', 3);
    }
}
