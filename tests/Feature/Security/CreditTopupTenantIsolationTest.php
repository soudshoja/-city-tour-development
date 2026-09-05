<?php

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\Credit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for the credits.topup IDOR (W40): CreditController::creditTopup()
 * validated `client_id` / `agent_id` with 'exists:clients,id' / 'exists:agents,id' only --
 * proof the ids are *real* rows, not that they belong to the caller's own company -- and the
 * company_id written onto every resulting Credit / Transaction / JournalEntry row was taken
 * from $agent->branch->company->id, i.e. from the ATTACKER-SUPPLIED agent. Any authenticated
 * user of any company could post a client_id/agent_id pair belonging to a different company and
 * inject real ledger rows into that company's books. The fix derives the acting company from
 * the authenticated user (getCompanyId()) and requires the supplied client and agent to both
 * resolve to that same company (or, for an admin with no company selected -- the same
 * "unscoped admin" exception ReceiptVoucherController/BankPaymentController already use -- to
 * simply match each other), aborting 403 otherwise.
 */
class CreditTopupTenantIsolationTest extends TestCase
{
    use CreatesTenantFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * Builds the Liabilities -> Client -> Payment Gateway and Assets -> Clients account chain
     * creditTopup() posts against, mirroring
     * tests/Feature/Accounting/ClientControllerAddCreditLedgerBalanceTest.php's fixture for the
     * same account tree.
     */
    private function seedTopupAccounts(int $companyId): void
    {
        $liabilities = Account::create([
            'name' => 'Liabilities', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId,
        ]);
        $clientAdvance = Account::create([
            'name' => 'Client', 'level' => 2, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId,
            'parent_id' => $liabilities->id, 'root_id' => $liabilities->id,
        ]);
        Account::create([
            'name' => 'Payment Gateway', 'level' => 3, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId,
            'parent_id' => $clientAdvance->id, 'root_id' => $liabilities->id,
        ]);

        $assets = Account::create([
            'name' => 'Assets', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId,
        ]);
        Account::create([
            'name' => 'Clients', 'level' => 2, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $companyId,
            'parent_id' => $assets->id, 'root_id' => $assets->id,
        ]);
    }

    public function test_cannot_topup_a_client_and_agent_both_belonging_to_another_company(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->seedTopupAccounts($tenantB['company']->id);

        $response = $this->actingAs($tenantA['user'])
            ->post(route('credits.topup'), [
                'client_id' => $tenantB['client']->id,
                'agent_id' => $tenantB['agent']->id,
                'amount' => 50,
                'description' => 'cross-tenant attack attempt',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('credits', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_cannot_topup_own_client_paired_with_another_companys_agent(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->seedTopupAccounts($tenantA['company']->id);

        $response = $this->actingAs($tenantA['user'])
            ->post(route('credits.topup'), [
                'client_id' => $tenantA['client']->id,
                'agent_id' => $tenantB['agent']->id,
                'amount' => 50,
                'description' => 'mismatched client/agent attempt',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('credits', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_cannot_topup_own_agent_paired_with_another_companys_client(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->seedTopupAccounts($tenantA['company']->id);

        $response = $this->actingAs($tenantA['user'])
            ->post(route('credits.topup'), [
                'client_id' => $tenantB['client']->id,
                'agent_id' => $tenantA['agent']->id,
                'amount' => 50,
                'description' => 'mismatched client/agent attempt',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('credits', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_same_company_topup_still_works_unchanged(): void
    {
        $tenant = $this->createTenant();
        $this->seedTopupAccounts($tenant['company']->id);

        // buildCreditTopupDraft() resolves CASH_IN_HAND unconditionally, even on this OFF path
        // (posting_engine_enabled stays false, so PostingSeam::isEnabledFor() still routes to
        // the $legacy closure below) -- mirrors
        // CreditControllerW7KTest::test_off_path_matches_legacy_exactly()'s identical
        // comment/setup. Map a dedicated leaf (kept separate from seedTopupAccounts()'s hand
        // -built chain, which the $legacy closure looks up by name) to that purpose code so the
        // lookup resolves.
        $cashInHand = Account::create([
            'name' => 'Cash In Hand', 'level' => 1, 'actual_balance' => 0,
            'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenant['company']->id,
        ]);
        SystemAccount::create([
            'company_id' => $tenant['company']->id,
            'purpose_code' => 'CASH_IN_HAND',
            'service_type' => null,
            'account_id' => $cashInHand->id,
        ]);

        // creditTopup() records `topup_by` from the caller's Spatie role name (an enum column
        // restricted to Client/Branch/Company) -- CompanyController::store() assigns the
        // 'company' Spatie role to every real Role::COMPANY user, so mirror that here rather
        // than relying on createTenant()'s default (no Spatie role at all).
        Role::firstOrCreate(['name' => 'company', 'guard_name' => 'web']);
        $tenant['user']->assignRole('company');

        // CreditPolicy::create() (Wave A) now gates creditTopup() on 'create credit' -- see
        // database/seeders/PermissionSeeder.php's own grant for this permission; mirrors
        // tests/Feature/Accounting/CreditControllerW7KTest.php's makeCompanyWithAdmin().
        Permission::firstOrCreate(['name' => 'create credit', 'group' => 'credit']);
        $tenant['user']->givePermissionTo('create credit');

        $response = $this->actingAs($tenant['user'])
            ->post(route('credits.topup'), [
                'client_id' => $tenant['client']->id,
                'agent_id' => $tenant['agent']->id,
                'amount' => 75.5,
                'description' => 'legitimate same-company topup',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');

        $this->assertDatabaseHas('credits', [
            'client_id' => $tenant['client']->id,
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'type' => Credit::TOPUP,
            'amount' => 75.5,
        ]);
    }
}
