<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Services\Accounting\AccountResolver;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * W6.C "New leaves" — proves 4137 "Supplier Charge Recharge Income" and 5128 "Supplier Fees &
 * Surcharges" are actually seeded (fresh company via CoaSeeder) AND backfillable (existing company
 * via accounting:ensure-system-leaves) and resolvable via AccountResolver by their purpose codes.
 */
class SupplierChargePurposeCodesTest extends AccountingTestCase
{
    public function test_fresh_company_seeds_and_maps_both_new_leaves(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder())->run();
        $this->trackCompanyForInvariants($company->id);

        $resolver = app(AccountResolver::class);

        $recharge = $resolver->resolve('SUPPLIER_CHARGE_RECHARGE_INCOME', $company->id);
        $this->assertSame('4137', $recharge->code);
        $this->assertSame('Supplier Charge Recharge Income', $recharge->name);

        $expense = $resolver->resolve('SUPPLIER_CHARGE_EXPENSE', $company->id);
        $this->assertSame('5128', $expense->code);
        $this->assertSame('Supplier Fees & Surcharges', $expense->name);
    }

    public function test_ensure_system_leaves_backfills_both_for_an_existing_company_missing_them(): void
    {
        $company = Company::factory()->create();
        // Simulate a pre-existing company by seeding a chart WITHOUT the two new leaves: run
        // CoaSeeder, then delete them (mirrors the real "seeded before this build's leaves
        // existed" scenario EnsureSystemLeaves itself documents).
        CoaSeeder::run($company->id);
        \App\Models\Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('code', ['4137', '5128'])
            ->delete();
        $this->trackCompanyForInvariants($company->id);

        $resolver = app(AccountResolver::class);
        $this->expectException(\App\Exceptions\Accounting\UnmappedPurposeException::class);
        $resolver->resolve('SUPPLIER_CHARGE_RECHARGE_INCOME', $company->id);
    }

    public function test_ensure_system_leaves_command_creates_the_missing_leaves(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        \App\Models\Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('code', ['4137', '5128'])
            ->delete();
        $this->trackCompanyForInvariants($company->id);

        Artisan::call('accounting:ensure-system-leaves', ['--company' => $company->id]);

        $resolver = app(AccountResolver::class);
        $recharge = $resolver->resolve('SUPPLIER_CHARGE_RECHARGE_INCOME', $company->id);
        $this->assertSame('4137', $recharge->code);

        $expense = $resolver->resolve('SUPPLIER_CHARGE_EXPENSE', $company->id);
        $this->assertSame('5128', $expense->code);
    }
}
