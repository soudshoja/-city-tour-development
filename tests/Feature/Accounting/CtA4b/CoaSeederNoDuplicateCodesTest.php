<?php

namespace Tests\Feature\Accounting\CtA4b;

use App\Models\Account;
use App\Models\Company;
use Database\Seeders\CoaSeeder;
use Tests\Support\AccountingTestCase;

/**
 * CT-A4b — CoaSeeder itself used to ship exactly one known, deliberately-tolerated duplicate:
 * code '2130' assigned to both 'Suppliers (Hotels)' AND 'Suppliers (Ferry)'
 * (tests/Support/AccountingInvariants.php::assertNoDuplicateAccountCodes() carried a named
 * exception for it). Ferry's Suppliers pool now seeds at '2131', its own free slot in that
 * family, and the tolerated-pair exception is removed — a freshly seeded chart has zero
 * duplicate codes, full stop.
 */
class CoaSeederNoDuplicateCodesTest extends AccountingTestCase
{
    public function test_a_freshly_seeded_chart_has_zero_duplicate_codes(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);

        $duplicates = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->pluck('code')
            ->countBy()
            ->filter(fn (int $n) => $n > 1);

        $this->assertTrue($duplicates->isEmpty(), 'Duplicate codes on a fresh CoaSeeder chart: '.$duplicates->keys()->implode(', '));
    }

    public function test_ferry_and_hotels_supplier_pools_have_distinct_codes(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);

        $hotels = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Suppliers (Hotels)')->first();
        $ferry = Account::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'Suppliers (Ferry)')->first();

        $this->assertNotNull($hotels);
        $this->assertNotNull($ferry);
        $this->assertSame('2130', $hotels->code);
        $this->assertSame('2131', $ferry->code);
    }

    public function test_running_the_seeder_twice_is_idempotent_and_still_zero_duplicates(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        CoaSeeder::run($company->id);
        $countAfterFirst = Account::withoutGlobalScopes()->where('company_id', $company->id)->count();

        CoaSeeder::run($company->id);
        $countAfterSecond = Account::withoutGlobalScopes()->where('company_id', $company->id)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond);

        $duplicates = Account::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->pluck('code')
            ->countBy()
            ->filter(fn (int $n) => $n > 1);

        $this->assertTrue($duplicates->isEmpty());
    }
}
