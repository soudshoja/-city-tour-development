<?php

namespace Tests\Feature\Accounting;

use App\Models\SupplierChargeRule;
use App\Models\SupplierCompany;
use App\Models\SupplierSurcharge;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * W6.C item 2 (w6-brief.md "W6.C — Supplier-side charges"). Feature coverage for
 * {@see \App\Console\Commands\BackfillSupplierChargeRules} — the one-time migration of every
 * `supplier_surcharges` row onto `supplier_charge_rules`.
 */
class BackfillSupplierChargeRulesTest extends AccountingTestCase
{
    /**
     * SupplierCompany extends Eloquent's Pivot base class, which defaults `$incrementing = false`
     * even though `supplier_companies.id` really is an auto-increment column — so
     * `SupplierCompany::factory()->create()->id` comes back null (create() never fetches
     * lastInsertId when incrementing() is false). Re-querying by the fresh row's own unique
     * supplier_id/company_id pair (guaranteed unique here — each call mints brand new nested
     * Supplier/Company factories) gets the real, DB-assigned id.
     */
    private function makeSupplierCompany(bool $active = true): SupplierCompany
    {
        $created = SupplierCompany::factory()->create(['is_active' => $active]);

        return SupplierCompany::query()
            ->where('supplier_id', $created->supplier_id)
            ->where('company_id', $created->company_id)
            ->firstOrFail();
    }

    public function test_task_mode_becomes_once_per_reference_false(): void
    {
        $supplierCompany = $this->makeSupplierCompany();
        $legacy = SupplierSurcharge::create([
            'supplier_company_id' => $supplierCompany->id,
            'label' => 'Handling fee',
            'amount' => 0.500,
            'charge_mode' => 'task',
            'charge_behavior' => null,
            'is_issued' => true,
        ]);

        Artisan::call('supplier-charges:backfill-rules');

        $rule = SupplierChargeRule::query()->where('legacy_supplier_surcharge_id', $legacy->id)->first();

        $this->assertNotNull($rule);
        $this->assertSame($supplierCompany->company_id, $rule->company_id);
        $this->assertSame($supplierCompany->supplier_id, $rule->supplier_id);
        $this->assertEqualsWithDelta(0.500, $rule->amount, 0.0005);
        $this->assertSame(SupplierChargeRule::BASIS_FIXED, $rule->basis);
        $this->assertFalse($rule->once_per_reference);
        $this->assertSame(SupplierChargeRule::RECHARGE_ABSORB, $rule->recharge_policy);
        $this->assertFalse((bool) $rule->commissionable, 'Rule 1e: every backfilled rule is non-commissionable.');
        $this->assertSame('Handling fee', $rule->label);
    }

    public function test_reference_single_becomes_once_per_reference_true(): void
    {
        $supplierCompany = $this->makeSupplierCompany();
        $legacy = SupplierSurcharge::create([
            'supplier_company_id' => $supplierCompany->id,
            'label' => 'One-time booking fee',
            'amount' => 1.000,
            'charge_mode' => 'reference',
            'charge_behavior' => 'single',
        ]);

        Artisan::call('supplier-charges:backfill-rules');

        $rule = SupplierChargeRule::query()->where('legacy_supplier_surcharge_id', $legacy->id)->first();

        $this->assertNotNull($rule);
        $this->assertTrue($rule->once_per_reference);
    }

    public function test_reference_repetitive_becomes_once_per_reference_false(): void
    {
        $supplierCompany = $this->makeSupplierCompany();
        $legacy = SupplierSurcharge::create([
            'supplier_company_id' => $supplierCompany->id,
            'label' => 'Repetitive fee',
            'amount' => 2.000,
            'charge_mode' => 'reference',
            'charge_behavior' => 'repetitive',
        ]);

        Artisan::call('supplier-charges:backfill-rules');

        $rule = SupplierChargeRule::query()->where('legacy_supplier_surcharge_id', $legacy->id)->first();

        $this->assertNotNull($rule);
        $this->assertFalse($rule->once_per_reference);
    }

    public function test_inactive_supplier_company_backfills_an_inactive_rule(): void
    {
        $supplierCompany = $this->makeSupplierCompany(active: false);
        $legacy = SupplierSurcharge::create([
            'supplier_company_id' => $supplierCompany->id,
            'label' => 'Dormant fee',
            'amount' => 1.000,
            'charge_mode' => 'task',
        ]);

        Artisan::call('supplier-charges:backfill-rules');

        $rule = SupplierChargeRule::query()->where('legacy_supplier_surcharge_id', $legacy->id)->first();

        $this->assertNotNull($rule);
        $this->assertFalse((bool) $rule->active);
    }

    public function test_row_count_parity_across_multiple_legacy_rows(): void
    {
        $companyA = $this->makeSupplierCompany();
        $companyB = $this->makeSupplierCompany();

        SupplierSurcharge::create(['supplier_company_id' => $companyA->id, 'label' => 'Fee A', 'amount' => 0.250, 'charge_mode' => 'task']);
        SupplierSurcharge::create(['supplier_company_id' => $companyA->id, 'label' => 'Fee B', 'amount' => 0.500, 'charge_mode' => 'reference', 'charge_behavior' => 'single']);
        SupplierSurcharge::create(['supplier_company_id' => $companyB->id, 'label' => 'Fee C', 'amount' => 1.000, 'charge_mode' => 'reference', 'charge_behavior' => 'repetitive']);

        Artisan::call('supplier-charges:backfill-rules');

        $this->assertSame(
            SupplierSurcharge::query()->count(),
            SupplierChargeRule::query()->whereNotNull('legacy_supplier_surcharge_id')->count(),
            'Every existing supplier_surcharges row must produce exactly one supplier_charge_rules row.'
        );
    }

    public function test_idempotent_rerun_creates_nothing_new(): void
    {
        $supplierCompany = $this->makeSupplierCompany();
        SupplierSurcharge::create(['supplier_company_id' => $supplierCompany->id, 'label' => 'Fee', 'amount' => 0.500, 'charge_mode' => 'task']);

        Artisan::call('supplier-charges:backfill-rules');
        $countAfterFirst = SupplierChargeRule::query()->count();

        Artisan::call('supplier-charges:backfill-rules');
        $countAfterSecond = SupplierChargeRule::query()->count();

        $this->assertSame($countAfterFirst, $countAfterSecond, 'Re-running the backfill must never duplicate rows.');
        $this->assertSame(1, $countAfterFirst);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $supplierCompany = $this->makeSupplierCompany();
        SupplierSurcharge::create(['supplier_company_id' => $supplierCompany->id, 'label' => 'Fee', 'amount' => 0.500, 'charge_mode' => 'task']);

        Artisan::call('supplier-charges:backfill-rules', ['--dry-run' => true]);

        $this->assertSame(0, SupplierChargeRule::query()->count());
    }
}
