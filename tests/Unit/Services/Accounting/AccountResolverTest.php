<?php

namespace Tests\Unit\Services\Accounting;

use App\Exceptions\Accounting\CrossTenantAccountException;
use App\Exceptions\Accounting\UnmappedPurposeException;
use App\Models\Account;
use App\Models\Company;
use App\Services\Accounting\AccountResolver;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * P1 acceptance tests for AccountResolver (Accounting Gap/11-technical-implementation-plan.md
 * L294-301, §"Acceptance tests (P1)"). Test names pinned verbatim to the
 * build contract.
 */
class AccountResolverTest extends AccountingTestCase
{
    /**
     * Pins: AccountResolver::resolve() "THROWS UnmappedPurposeException if
     * no row — the engine must never silently skip a leg." A company with
     * zero matching system_accounts rows for the requested purpose must
     * throw rather than return null / a fallback account.
     */
    public function test_throws_on_unmapped_purpose(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        // Deliberately no system_accounts row is inserted for this company/purpose.
        $this->expectException(UnmappedPurposeException::class);

        app(AccountResolver::class)->resolve('SUSPENSE', $company->id);
    }

    /**
     * Pins: AccountResolver::resolve() "WITHOUT relying on Auth global
     * scope (safe in unauthenticated gateway/queue context)." No user is
     * authenticated anywhere in this test — auth()->check() is false
     * throughout, exactly the webhook/queue condition file 11 calls out —
     * yet resolve() must still find and return the mapped leaf account.
     */
    public function test_resolves_without_auth_context(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $leafAccount = Account::factory()->create(['company_id' => $company->id]);

        DB::table('system_accounts')->insert([
            'company_id' => $company->id,
            'purpose_code' => 'RECEIVABLE_CONTROL',
            'service_type' => null,
            'account_id' => $leafAccount->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(auth()->check(), 'Precondition: this test must run fully unauthenticated to prove the webhook/queue-safe path.');

        $resolved = app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);

        $this->assertInstanceOf(Account::class, $resolved);
        $this->assertSame($leafAccount->id, $resolved->id);
        $this->assertSame($company->id, $resolved->company_id);

        $this->assertFalse(auth()->check(), 'resolve() must not have triggered any authentication side effect.');
    }

    /**
     * Extra coverage (not a separately named acceptance test, folded into
     * the same class): the service_type dimension must correctly
     * disambiguate two rows sharing the same purpose_code for one company
     * (the per-service REVENUE/PAYABLE/COST shape, file 11 L92/L177) —
     * resolving with the wrong/omitted service_type for a service-scoped
     * purpose must not silently return the other service's account.
     */
    public function test_resolves_correct_account_for_service_type_dimension(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $flightRevenue = Account::factory()->create(['company_id' => $company->id]);
        $hotelRevenue = Account::factory()->create(['company_id' => $company->id]);

        DB::table('system_accounts')->insert([
            [
                'company_id' => $company->id,
                'purpose_code' => 'SERVICE_REVENUE',
                'service_type' => 'flight',
                'account_id' => $flightRevenue->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'purpose_code' => 'SERVICE_REVENUE',
                'service_type' => 'hotel',
                'account_id' => $hotelRevenue->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $resolvedFlight = app(AccountResolver::class)->resolve('SERVICE_REVENUE', $company->id, 'flight');
        $resolvedHotel = app(AccountResolver::class)->resolve('SERVICE_REVENUE', $company->id, 'hotel');

        $this->assertSame($flightRevenue->id, $resolvedFlight->id);
        $this->assertSame($hotelRevenue->id, $resolvedHotel->id);
    }

    /**
     * BLOCKER 1 regression pin (.planning/P1-VERIFICATION-FINDINGS.json blockers[0]):
     * "AccountResolver never asserts the resolved account belongs to the requesting company —
     * the purposeCode path can write cross-tenant journal lines." Nothing at the DB level ties
     * system_accounts.account_id's company to system_accounts.company_id (the migration's own
     * comment admits this: "Must be a leaf, same company as company_id above — enforced in code
     * ... not by a DB constraint") — so a row that got there some other way than
     * SystemAccountsSeeder (an import, a tinker fix, a mis-restored row, a future admin UI) can
     * point a company's purpose code at a leaf account owned by a DIFFERENT company. resolve()
     * itself must reject that mapping rather than silently handing back a foreign account for the
     * caller to post against — this is the exact corruption shape behind prod check 7.1 (JE
     * 16953/16954).
     */
    public function test_resolve_rejects_a_mapping_that_points_at_another_companys_account(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $this->trackCompanyForInvariants($otherCompany->id);

        $foreignLeaf = Account::factory()->create(['company_id' => $otherCompany->id]);

        // The mis-seeded/corrupted row: company_id = $company, but account_id belongs to
        // $otherCompany. No API in this build produces this row — inserted directly, exactly as
        // the finding's own failure scenario describes.
        DB::table('system_accounts')->insert([
            'company_id' => $company->id,
            'purpose_code' => 'RECEIVABLE_CONTROL',
            'service_type' => null,
            'account_id' => $foreignLeaf->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(CrossTenantAccountException::class);

        app(AccountResolver::class)->resolve('RECEIVABLE_CONTROL', $company->id);
    }
}
