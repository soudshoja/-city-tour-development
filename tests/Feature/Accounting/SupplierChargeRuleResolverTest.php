<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\SupplierChargeOverridePendingApprovalException;
use App\Models\Company;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierChargeRule;
use App\Models\SupplierChargeRuleFiring;
use App\Services\Accounting\SaleDraftInput;
use App\Services\Accounting\SupplierChargeRuleResolver;
use App\Services\Accounting\SupplierCostCorrectionDraftBuilder;
use Illuminate\Support\Carbon;
use Tests\Support\AccountingTestCase;

/**
 * W6.C (w6-brief.md "W6.C — Supplier-side charges"). DB-backed coverage for
 * {@see SupplierChargeRuleResolver}: precedence resolution, channel filtering,
 * effective-date windows, the once_per_reference dedup ledger, and the manual-override approval
 * gate. Pure LineDraft-shape coverage lives in
 * {@see \Tests\Unit\Services\Accounting\SupplierChargeLineBuilderTest}.
 */
class SupplierChargeRuleResolverTest extends AccountingTestCase
{
    private function makeRule(Company $company, Supplier $supplier, array $overrides = []): SupplierChargeRule
    {
        return SupplierChargeRule::query()->create(array_merge([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'service_type' => null,
            'channel' => null,
            'charge_kind' => 'iata_fee',
            'basis' => SupplierChargeRule::BASIS_FIXED,
            'amount' => 1.000,
            'recharge_policy' => SupplierChargeRule::RECHARGE_ABSORB,
            'commissionable' => false,
            'active' => true,
            'once_per_reference' => false,
        ], $overrides));
    }

    // ── Precedence ─────────────────────────────────────────────────────────────────────────────────

    public function test_supplier_and_service_type_row_beats_supplier_only_beats_service_type_only_beats_company_wide(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $companyWide = $this->makeRule($company, $supplier, ['supplier_id' => null, 'service_type' => null, 'amount' => 1.0]);
        $serviceTypeOnly = $this->makeRule($company, $supplier, ['supplier_id' => null, 'service_type' => 'flight', 'amount' => 2.0]);
        $supplierOnly = $this->makeRule($company, $supplier, ['service_type' => null, 'amount' => 3.0]);
        $supplierAndServiceType = $this->makeRule($company, $supplier, ['service_type' => 'flight', 'amount' => 4.0]);

        $resolver = new SupplierChargeRuleResolver();

        // All four active for company/supplier/flight -- the most specific must win.
        $winners = $resolver->resolveApplicable($company->id, $supplier->id, 'flight', null, Carbon::now());
        $this->assertSame($supplierAndServiceType->id, $winners['iata_fee']->id);

        $supplierAndServiceType->update(['active' => false]);
        $winners = $resolver->resolveApplicable($company->id, $supplier->id, 'flight', null, Carbon::now());
        $this->assertSame($supplierOnly->id, $winners['iata_fee']->id);

        $supplierOnly->update(['active' => false]);
        $winners = $resolver->resolveApplicable($company->id, $supplier->id, 'flight', null, Carbon::now());
        $this->assertSame($serviceTypeOnly->id, $winners['iata_fee']->id);

        $serviceTypeOnly->update(['active' => false]);
        $winners = $resolver->resolveApplicable($company->id, $supplier->id, 'flight', null, Carbon::now());
        $this->assertSame($companyWide->id, $winners['iata_fee']->id);
    }

    public function test_channel_is_a_filter_not_a_precedence_tier(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $cardRule = $this->makeRule($company, $supplier, ['charge_kind' => 'card_surcharge', 'channel' => 'card', 'amount' => 5.0]);
        $this->makeRule($company, $supplier, ['charge_kind' => 'card_surcharge', 'channel' => 'cash', 'amount' => 9.0]);

        $resolver = new SupplierChargeRuleResolver();
        $winners = $resolver->resolveApplicable($company->id, $supplier->id, 'flight', 'card', Carbon::now());

        $this->assertSame($cardRule->id, $winners['card_surcharge']->id);
    }

    public function test_different_charge_kinds_each_resolve_independently(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $iata = $this->makeRule($company, $supplier, ['charge_kind' => 'iata_fee']);
        $card = $this->makeRule($company, $supplier, ['charge_kind' => 'card_surcharge']);

        $winners = (new SupplierChargeRuleResolver())->resolveApplicable($company->id, $supplier->id, 'flight', null, Carbon::now());

        $this->assertCount(2, $winners);
        $this->assertSame($iata->id, $winners['iata_fee']->id);
        $this->assertSame($card->id, $winners['card_surcharge']->id);
    }

    public function test_inactive_rule_never_resolves(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $this->makeRule($company, $supplier, ['active' => false]);

        $winners = (new SupplierChargeRuleResolver())->resolveApplicable($company->id, $supplier->id, 'flight', null, Carbon::now());

        $this->assertSame([], $winners);
    }

    public function test_effective_date_window_is_respected(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $rule = $this->makeRule($company, $supplier, [
            'effective_from' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        $resolver = new SupplierChargeRuleResolver();

        $this->assertSame([], $resolver->resolveApplicable($company->id, $supplier->id, 'flight', null, Carbon::now()));
        $this->assertSame(
            $rule->id,
            $resolver->resolveApplicable($company->id, $supplier->id, 'flight', null, Carbon::now()->addDays(10))['iata_fee']->id
        );
    }

    // ── once_per_reference dedup ───────────────────────────────────────────────────────────────────

    public function test_once_per_reference_fires_once_across_a_reissue_chain(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $rule = $this->makeRule($company, $supplier, ['once_per_reference' => true]);
        $resolver = new SupplierChargeRuleResolver();

        $this->assertFalse($resolver->hasAlreadyFired($rule, 'PNR-1'));

        $originalTask = \App\Models\Task::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'reference' => 'PNR-1']);
        $reissuedTask = \App\Models\Task::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'reference' => 'PNR-1', 'original_task_id' => $originalTask->id]);

        // First task in the chain: fires, caller records the firing after a successful post.
        $resolver->recordFiring($rule, 'PNR-1', $company->id, $originalTask->id, Carbon::now());

        $this->assertTrue($resolver->hasAlreadyFired($rule, 'PNR-1'), 'Second occurrence for the same reference must be a no-op.');
        $this->assertFalse($resolver->hasAlreadyFired($rule, 'PNR-2'), 'A DIFFERENT reference is unaffected.');

        $this->assertSame(1, SupplierChargeRuleFiring::query()->where('supplier_charge_rule_id', $rule->id)->count());

        // Re-recording for the same (rule, reference) pair (e.g. the reissued task in the same
        // chain reaching issue() again) must not throw or duplicate.
        $resolver->recordFiring($rule, 'PNR-1', $company->id, $reissuedTask->id, Carbon::now());
        $this->assertSame(1, SupplierChargeRuleFiring::query()->where('supplier_charge_rule_id', $rule->id)->count());
    }

    public function test_once_per_reference_false_never_writes_a_firing_row(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $rule = $this->makeRule($company, $supplier, ['once_per_reference' => false]);
        $resolver = new SupplierChargeRuleResolver();

        $resolver->recordFiring($rule, 'PNR-1', $company->id, 111, Carbon::now());

        $this->assertSame(0, SupplierChargeRuleFiring::query()->count());
        $this->assertFalse($resolver->hasAlreadyFired($rule, 'PNR-1'), 'once_per_reference=false always fires -- never dedups.');
    }

    // ── Manual override + approval gate ──────────────────────────────────────────────────────────────

    public function test_null_override_returns_resolved_amount_unchanged(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $rule = $this->makeRule($company, $supplier, ['amount' => 1.500]);

        $result = (new SupplierChargeRuleResolver())->applyManualOverride($rule, 1.500, null, $company->id);

        $this->assertEqualsWithDelta(1.500, $result, 0.0005);
    }

    public function test_needs_approval_policy_blocks_an_unapproved_override(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $rule = $this->makeRule($company, $supplier, ['amount' => 1.500]);

        Setting::create(['company_id' => $company->id, 'key' => 'accounting.supplier_charge_override_policy', 'value' => 'needs_approval', 'type' => 'string']);

        $this->expectException(SupplierChargeOverridePendingApprovalException::class);

        (new SupplierChargeRuleResolver())->applyManualOverride($rule, 1.500, 5.000, $company->id, approved: false);
    }

    public function test_needs_approval_policy_allows_an_approved_override(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $rule = $this->makeRule($company, $supplier, ['amount' => 1.500]);

        Setting::create(['company_id' => $company->id, 'key' => 'accounting.supplier_charge_override_policy', 'value' => 'needs_approval', 'type' => 'string']);

        $result = (new SupplierChargeRuleResolver())->applyManualOverride($rule, 1.500, 5.000, $company->id, approved: true);

        $this->assertEqualsWithDelta(5.000, $result, 0.0005);
    }

    public function test_free_policy_allows_an_override_without_approval(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $rule = $this->makeRule($company, $supplier, ['amount' => 1.500]);

        Setting::create(['company_id' => $company->id, 'key' => 'accounting.supplier_charge_override_policy', 'value' => 'free', 'type' => 'string']);

        $result = (new SupplierChargeRuleResolver())->applyManualOverride($rule, 1.500, 5.000, $company->id, approved: false);

        $this->assertEqualsWithDelta(5.000, $result, 0.0005);
    }

    public function test_default_policy_with_no_setting_row_requires_approval(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $rule = $this->makeRule($company, $supplier, ['amount' => 1.500]);

        $this->expectException(SupplierChargeOverridePendingApprovalException::class);

        (new SupplierChargeRuleResolver())->applyManualOverride($rule, 1.500, 5.000, $company->id, approved: false);
    }

    public function test_override_within_tolerance_of_resolved_amount_is_not_a_real_override(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $rule = $this->makeRule($company, $supplier, ['amount' => 1.500]);

        // No Setting row (defaults to needs_approval) -- but the override is within tolerance of
        // the resolved amount, so no exception should be thrown at all.
        $result = (new SupplierChargeRuleResolver())->applyManualOverride($rule, 1.500, 1.5001, $company->id, approved: false);

        $this->assertEqualsWithDelta(1.500, $result, 0.0005);
    }

    // ── Post-sale discovery caller (item 6): wraps SupplierCostCorrectionDraftBuilder's own input,
    //    never rebuilds it ─────────────────────────────────────────────────────────────────────────

    public function test_build_cost_correction_input_is_consumed_correctly_by_the_existing_w4c_builder(): void
    {
        $company = Company::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $rule = $this->makeRule($company, $supplier, ['amount' => 1.000]);

        $saleDate = Carbon::now()->startOfMonth()->addDay();

        $input = (new SupplierChargeRuleResolver())->buildCostCorrectionInput(
            rule: $rule,
            serviceType: 'flight',
            postingBasis: SaleDraftInput::BASIS_AGENT,
            originalAmount: 1.000,
            correctedAmount: 2.500,
            companyId: $company->id,
            branchId: 1,
            saleDocDate: $saleDate,
            correctionDate: Carbon::now(),
            invoiceId: 77,
            invoiceDetailId: 88,
            taskId: 99,
            taskReference: 'PNR-CORRECTION',
        );

        $draft = (new SupplierCostCorrectionDraftBuilder())->build($input);

        $this->assertSame('JV', $draft->docType);
        $this->assertSame('COST_CORRECTION', $draft->subType);
        $this->assertSame(77, $draft->invoiceId);
        $this->assertCount(2, $draft->lines);

        $debitTotal = array_sum(array_map(fn ($l) => $l->side === 'debit' ? $l->amount : 0.0, $draft->lines));
        $creditTotal = array_sum(array_map(fn ($l) => $l->side === 'credit' ? $l->amount : 0.0, $draft->lines));
        $this->assertEqualsWithDelta($debitTotal, $creditTotal, 0.0005, 'The delta document this caller feeds must self-balance.');
        $this->assertEqualsWithDelta(1.500, $debitTotal, 0.0005, 'Delta = corrected (2.500) - original (1.000).');
    }
}
