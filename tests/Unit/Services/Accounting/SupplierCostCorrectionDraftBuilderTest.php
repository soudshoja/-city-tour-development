<?php

namespace Tests\Unit\Services\Accounting;

use App\Services\Accounting\SaleDraftInput;
use App\Services\Accounting\SupplierCostCorrectionDraftBuilder;
use App\Services\Accounting\SupplierCostCorrectionInput;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * W4.C (w4-brief.md — "supplier cost posts in the sale's own period"). Pure unit tests for
 * {@see SupplierCostCorrectionDraftBuilder} — no DB, no PostingService, no controller. This class
 * only builds a {@see \App\Services\Accounting\DocumentDraft}; the Feature-level test in
 * {@see \Tests\Feature\Accounting\TaskControllerSupplierCostCorrectionTest} proves the same shapes
 * post correctly through the real engine.
 */
class SupplierCostCorrectionDraftBuilderTest extends TestCase
{
    private function input(
        float $originalCost,
        float $correctedCost,
        \DateTimeInterface $saleDocDate,
        \DateTimeInterface $correctionDate,
        string $postingBasis = SaleDraftInput::BASIS_AGENT,
        ?string $recognitionTiming = null,
        bool $alreadyRecognized = false,
    ): SupplierCostCorrectionInput {
        return new SupplierCostCorrectionInput(
            serviceType: 'flight',
            postingBasis: $postingBasis,
            originalCostAmount: $originalCost,
            correctedCostAmount: $correctedCost,
            companyId: 1,
            branchId: 1,
            saleDocDate: $saleDocDate,
            correctionDate: $correctionDate,
            invoiceId: 10,
            invoiceDetailId: 20,
            taskId: 30,
            supplierId: 40,
            supplierName: 'Test Supplier',
            taskReference: 'REF-1',
            recognitionTiming: $recognitionTiming,
            alreadyRecognized: $alreadyRecognized,
        );
    }

    // ── Period rule ────────────────────────────────────────────────────────────────────────────

    public function test_correction_in_the_same_calendar_month_dates_to_the_sale_document(): void
    {
        $saleDate = Carbon::parse('2026-08-05');
        $correctionDate = Carbon::parse('2026-08-20');

        $builder = new SupplierCostCorrectionDraftBuilder;

        $this->assertFalse($builder->isForwardCorrection($saleDate, $correctionDate));

        $draft = $builder->build($this->input(100.0, 120.0, $saleDate, $correctionDate));

        $this->assertTrue(Carbon::instance($draft->docDate)->isSameDay($saleDate), 'Same-period correction must date to the sale document, not today.');
    }

    public function test_correction_after_the_sale_month_has_rolled_over_dates_to_today_never_backdated(): void
    {
        $saleDate = Carbon::parse('2026-07-20');
        $correctionDate = Carbon::parse('2026-09-03');

        $builder = new SupplierCostCorrectionDraftBuilder;

        $this->assertTrue($builder->isForwardCorrection($saleDate, $correctionDate));

        $draft = $builder->build($this->input(100.0, 120.0, $saleDate, $correctionDate));

        $this->assertTrue(Carbon::instance($draft->docDate)->isSameDay($correctionDate), 'Late correction must post today, never backdated into the closed sale period.');
        $this->assertFalse(Carbon::instance($draft->docDate)->isSameMonth($saleDate), 'Must never land back in the closed month.');
    }

    public function test_forward_correction_is_still_linked_to_the_original_sale(): void
    {
        $saleDate = Carbon::parse('2026-07-20');
        $correctionDate = Carbon::parse('2026-09-03');

        $draft = (new SupplierCostCorrectionDraftBuilder)->build($this->input(100.0, 120.0, $saleDate, $correctionDate));

        $this->assertSame(10, $draft->invoiceId);
        foreach ($draft->lines as $line) {
            $this->assertSame(10, $line->invoiceId);
            $this->assertSame(20, $line->invoiceDetailId);
            $this->assertSame(30, $line->taskId);
        }
    }

    // ── No 5221 accrual on any path ───────────────────────────────────────────────────────────────

    public function test_no_line_ever_references_the_company_loss_purpose_code(): void
    {
        $saleDate = Carbon::parse('2026-08-05');

        $sameMonth = (new SupplierCostCorrectionDraftBuilder)->build($this->input(100.0, 120.0, $saleDate, Carbon::parse('2026-08-20')));
        $forward = (new SupplierCostCorrectionDraftBuilder)->build($this->input(100.0, 120.0, $saleDate, Carbon::parse('2026-09-03')));
        $decrease = (new SupplierCostCorrectionDraftBuilder)->build($this->input(120.0, 90.0, $saleDate, Carbon::parse('2026-08-20')));
        $principal = (new SupplierCostCorrectionDraftBuilder)->build($this->input(0.0, 150.0, $saleDate, Carbon::parse('2026-08-20'), SaleDraftInput::BASIS_PRINCIPAL));

        foreach ([...$sameMonth->lines, ...$forward->lines, ...$decrease->lines, ...$principal->lines] as $line) {
            $this->assertNotSame('COMPANY_LOSS_ON_SALES', $line->purposeCode);
            $this->assertStringNotContainsStringIgnoringCase('5221', (string) $line->purposeCode);
            $this->assertStringNotContainsStringIgnoringCase('company loss', (string) $line->description);
        }
    }

    // ── Agent (NET) basis delta shape ─────────────────────────────────────────────────────────────

    public function test_agent_basis_cost_increase_debits_service_revenue_and_credits_service_payable(): void
    {
        $saleDate = Carbon::parse('2026-08-05');
        $draft = (new SupplierCostCorrectionDraftBuilder)->build($this->input(100.0, 120.0, $saleDate, $saleDate));

        $this->assertCount(2, $draft->lines);

        $payable = $draft->lines[0];
        $this->assertSame('SERVICE_PAYABLE', $payable->purposeCode);
        $this->assertSame('credit', $payable->side);
        $this->assertEqualsWithDelta(20.0, $payable->amount, 0.0005);

        $revenue = $draft->lines[1];
        $this->assertSame('SERVICE_REVENUE', $revenue->purposeCode);
        $this->assertSame('debit', $revenue->side, 'A cost increase reduces margin -- SERVICE_REVENUE flips to debit.');
        $this->assertEqualsWithDelta(20.0, $revenue->amount, 0.0005);

        $debit = array_sum(array_map(fn ($l) => $l->side === 'debit' ? $l->amount : 0.0, $draft->lines));
        $credit = array_sum(array_map(fn ($l) => $l->side === 'credit' ? $l->amount : 0.0, $draft->lines));
        $this->assertEqualsWithDelta($debit, $credit, 0.0005);
    }

    public function test_agent_basis_cost_decrease_credits_service_revenue_and_debits_service_payable(): void
    {
        $saleDate = Carbon::parse('2026-08-05');
        $draft = (new SupplierCostCorrectionDraftBuilder)->build($this->input(120.0, 90.0, $saleDate, $saleDate));

        $payable = $draft->lines[0];
        $this->assertSame('SERVICE_PAYABLE', $payable->purposeCode);
        $this->assertSame('debit', $payable->side, 'A cost decrease reduces the supplier payable.');
        $this->assertEqualsWithDelta(30.0, $payable->amount, 0.0005);

        $revenue = $draft->lines[1];
        $this->assertSame('SERVICE_REVENUE', $revenue->purposeCode);
        $this->assertSame('credit', $revenue->side, 'A cost decrease increases margin.');
        $this->assertEqualsWithDelta(30.0, $revenue->amount, 0.0005);
    }

    // ── Principal (GROSS) basis delta shape ───────────────────────────────────────────────────────

    public function test_principal_basis_cost_increase_debits_service_cost_and_credits_service_payable(): void
    {
        $saleDate = Carbon::parse('2026-08-05');
        $draft = (new SupplierCostCorrectionDraftBuilder)->build(
            $this->input(150.0, 170.0, $saleDate, $saleDate, SaleDraftInput::BASIS_PRINCIPAL)
        );

        $this->assertCount(2, $draft->lines);

        $cost = $draft->lines[0];
        $this->assertSame('SERVICE_COST', $cost->purposeCode);
        $this->assertSame('debit', $cost->side);
        $this->assertEqualsWithDelta(20.0, $cost->amount, 0.0005);

        $payable = $draft->lines[1];
        $this->assertSame('SERVICE_PAYABLE', $payable->purposeCode);
        $this->assertSame('credit', $payable->side);
        $this->assertEqualsWithDelta(20.0, $payable->amount, 0.0005);
    }

    public function test_principal_basis_cost_unknown_at_sale_time_now_corrected_posts_the_full_cost_pair(): void
    {
        // originalCostAmount = 0.0 -- the exact shape SaleDraftBuilder's principal basis omits
        // at sale time when cost isn't known yet. The correction delta equals the full corrected
        // cost, producing the same 2-line pair the sale document would have carried had the cost
        // been known at sale time -- the primary "genuinely late-arriving cost" case (target-spec.md).
        $saleDate = Carbon::parse('2026-08-05');
        $draft = (new SupplierCostCorrectionDraftBuilder)->build(
            $this->input(0.0, 150.0, $saleDate, $saleDate, SaleDraftInput::BASIS_PRINCIPAL)
        );

        $this->assertCount(2, $draft->lines);
        $this->assertSame('SERVICE_COST', $draft->lines[0]->purposeCode);
        $this->assertEqualsWithDelta(150.0, $draft->lines[0]->amount, 0.0005);
        $this->assertSame('SERVICE_PAYABLE', $draft->lines[1]->purposeCode);
        $this->assertEqualsWithDelta(150.0, $draft->lines[1]->amount, 0.0005);
    }

    // ── P2.5.D fix: recognition-timing purpose-code substitution ─────────────────────────────────────

    public function test_principal_basis_at_travel_and_not_yet_recognized_defers_cost_leg_to_prepaid(): void
    {
        $saleDate = Carbon::parse('2026-08-05');
        $draft = (new SupplierCostCorrectionDraftBuilder)->build($this->input(
            0.0, 150.0, $saleDate, $saleDate, SaleDraftInput::BASIS_PRINCIPAL,
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_TRAVEL,
            alreadyRecognized: false,
        ));

        $this->assertCount(2, $draft->lines);

        $cost = $draft->lines[0];
        $this->assertSame('PREPAID_SUPPLIER_COST', $cost->purposeCode, 'Deferred sale: cost leg must NOT hit SERVICE_COST (a P&L expense) before recognition.');
        $this->assertNull($cost->serviceType, 'Global leaf -- no per-service-type tagging.');
        $this->assertSame('debit', $cost->side);
        $this->assertEqualsWithDelta(150.0, $cost->amount, 0.0005);

        $payable = $draft->lines[1];
        $this->assertSame('SERVICE_PAYABLE', $payable->purposeCode, 'The real supplier liability is unaffected by recognition timing.');
        $this->assertEqualsWithDelta(150.0, $payable->amount, 0.0005);
    }

    public function test_principal_basis_at_travel_but_already_recognized_posts_to_real_service_cost(): void
    {
        $saleDate = Carbon::parse('2026-08-05');
        $draft = (new SupplierCostCorrectionDraftBuilder)->build($this->input(
            150.0, 170.0, $saleDate, $saleDate, SaleDraftInput::BASIS_PRINCIPAL,
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_TRAVEL,
            alreadyRecognized: true,
        ));

        $this->assertSame('SERVICE_COST', $draft->lines[0]->purposeCode, 'Already released by RevenueRecognitionService -- correction targets the REAL expense account, same as at_issue.');
        $this->assertSame('flight', $draft->lines[0]->serviceType);
    }

    public function test_principal_basis_at_issue_is_unaffected_by_the_recognition_timing_fields(): void
    {
        $saleDate = Carbon::parse('2026-08-05');
        $draft = (new SupplierCostCorrectionDraftBuilder)->build($this->input(
            150.0, 170.0, $saleDate, $saleDate, SaleDraftInput::BASIS_PRINCIPAL,
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_ISSUE,
            alreadyRecognized: false,
        ));

        $this->assertSame('SERVICE_COST', $draft->lines[0]->purposeCode);
    }

    public function test_agent_basis_at_travel_and_not_yet_recognized_defers_margin_leg_to_deferred_revenue(): void
    {
        $saleDate = Carbon::parse('2026-08-05');
        $draft = (new SupplierCostCorrectionDraftBuilder)->build($this->input(
            100.0, 120.0, $saleDate, $saleDate, SaleDraftInput::BASIS_AGENT,
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_TRAVEL,
            alreadyRecognized: false,
        ));

        $payable = $draft->lines[0];
        $this->assertSame('SERVICE_PAYABLE', $payable->purposeCode, 'Agent basis never defers cost -- the payable leg is always real.');

        $margin = $draft->lines[1];
        $this->assertSame('DEFERRED_REVENUE', $margin->purposeCode);
        $this->assertNull($margin->serviceType, 'Global leaf -- no per-service-type tagging.');
        $this->assertSame('debit', $margin->side, 'A cost increase still reduces margin, sign-aware, regardless of recognition timing.');
        $this->assertEqualsWithDelta(20.0, $margin->amount, 0.0005);
    }

    public function test_deferred_correction_still_balances(): void
    {
        $saleDate = Carbon::parse('2026-08-05');
        $draft = (new SupplierCostCorrectionDraftBuilder)->build($this->input(
            0.0, 150.0, $saleDate, $saleDate, SaleDraftInput::BASIS_PRINCIPAL,
            recognitionTiming: SaleDraftInput::RECOGNITION_AT_TRAVEL,
            alreadyRecognized: false,
        ));

        $debit = array_sum(array_map(fn ($l) => $l->side === 'debit' ? $l->amount : 0.0, $draft->lines));
        $credit = array_sum(array_map(fn ($l) => $l->side === 'credit' ? $l->amount : 0.0, $draft->lines));
        $this->assertEqualsWithDelta($debit, $credit, 0.0005);
    }

    public function test_invalid_recognition_timing_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SupplierCostCorrectionInput(
            serviceType: 'flight',
            postingBasis: SaleDraftInput::BASIS_AGENT,
            originalCostAmount: 100.0,
            correctedCostAmount: 120.0,
            companyId: 1,
            branchId: 1,
            saleDocDate: Carbon::parse('2026-08-05'),
            correctionDate: Carbon::parse('2026-08-05'),
            recognitionTiming: 'bogus',
        );
    }

    // ── Idempotency ────────────────────────────────────────────────────────────────────────────────

    public function test_idempotency_key_is_stable_for_retries_of_the_identical_correction(): void
    {
        $saleDate = Carbon::parse('2026-08-05');
        $first = (new SupplierCostCorrectionDraftBuilder)->build($this->input(100.0, 120.0, $saleDate, $saleDate));
        $retry = (new SupplierCostCorrectionDraftBuilder)->build($this->input(100.0, 120.0, $saleDate, $saleDate));

        $this->assertSame($first->idempotencyKey, $retry->idempotencyKey);
    }

    public function test_idempotency_key_differs_for_a_second_distinct_correction(): void
    {
        $saleDate = Carbon::parse('2026-08-05');
        $first = (new SupplierCostCorrectionDraftBuilder)->build($this->input(100.0, 120.0, $saleDate, $saleDate));
        // A second, later correction of the same InvoiceDetail to a DIFFERENT final cost.
        $second = (new SupplierCostCorrectionDraftBuilder)->build($this->input(120.0, 135.0, $saleDate, $saleDate));

        $this->assertNotSame($first->idempotencyKey, $second->idempotencyKey);
    }

    // ── Refuses a no-op correction ─────────────────────────────────────────────────────────────────

    public function test_zero_delta_correction_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $saleDate = Carbon::parse('2026-08-05');
        (new SupplierCostCorrectionDraftBuilder)->build($this->input(100.0, 100.0, $saleDate, $saleDate));
    }
}
