<?php

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Setting;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for HF-7: 8 AccountingController AJAX endpoints used
 * to trust a client-supplied `company_id` query param with zero ownership
 * check, letting any authenticated user (of any role, any company) read
 * another company's chart-of-accounts, branches, bank accounts, and
 * journal-linked invoices simply by passing a different company_id. Now
 * every one of them derives the company from the acting user (or, for
 * Role::ADMIN, still honours the request value -- admins already manage
 * an explicit "current company" via session).
 *
 * The whole AccountingController AJAX group also carries the newer
 * 'module:accounting' route gate (see EnsureModuleEnabled), and accounting
 * now fails CLOSED for a company with no `module.*` rows
 * (config('modules.default_disabled') -- see Company::hasModule()).
 * CreatesTenantFixtures::createTenant() builds exactly that kind of
 * legacy-shaped company, so the CALLING tenant in each test below is
 * granted `module.accounting` explicitly via grantAccountingModule() --
 * otherwise the route 404s at the middleware before the tenant-isolation
 * check this suite actually tests ever runs.
 */
class AccountingAjaxTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    /**
     * Grants `module.accounting` to $company so its requests clear the
     * 'module:accounting' route gate and reach the tenant-isolation logic
     * under test, rather than being turned away earlier by the fail-closed
     * default (see config/modules.php).
     */
    private function grantAccountingModule(Company $company): void
    {
        Setting::updateOrCreate(
            ['company_id' => $company->id, 'key' => Modules::settingKey(Modules::ACCOUNTING)],
            ['type' => 'boolean', 'value' => true]
        );

        Company::forgetModuleCache();
    }

    public function test_get_branches_by_company_ignores_another_companys_id(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        // Only the caller (tenant A) needs the grant -- the module gate
        // resolves purely off the *requesting* user's company, never the
        // company_id param under test, so tenant B is deliberately left as
        // a legacy (no module rows) company.
        $this->grantAccountingModule($tenantA['company']);

        // A second branch for company B, so a leak would be unambiguous.
        Branch::factory()->create(['company_id' => $tenantB['company']->id, 'user_id' => $tenantB['user']->id]);

        $response = $this->actingAs($tenantA['user'])
            ->getJson(route('get.branches.by.company', ['company_id' => $tenantB['company']->id]));

        // Company A has exactly one branch (its own, from createTenant());
        // if the fix were absent this would return company B's branches.
        $response->assertOk();
        $branchIds = collect($response->json('branches'))->pluck('id');
        $this->assertTrue($branchIds->every(fn ($id) => $id === $tenantA['branch']->id));
        $this->assertFalse($branchIds->contains($tenantB['branch']->id));
    }

    public function test_get_bank_accounts_by_company_ignores_another_companys_id(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        // Only the caller (tenant A) needs the grant -- same reasoning as
        // the branches test above.
        $this->grantAccountingModule($tenantA['company']);

        $parentA = Account::create(['name' => 'Bank Accounts', 'level' => 3, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantA['company']->id]);
        Account::create(['name' => 'Company A Main Bank', 'level' => 4, 'actual_balance' => 100, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantA['company']->id, 'parent_id' => $parentA->id]);

        $parentB = Account::create(['name' => 'Bank Accounts', 'level' => 3, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantB['company']->id]);
        Account::create(['name' => 'Company B Secret Bank', 'level' => 4, 'actual_balance' => 999, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantB['company']->id, 'parent_id' => $parentB->id]);

        $response = $this->actingAs($tenantA['user'])
            ->getJson(route('get.bank.accounts.by.company', ['company_id' => $tenantB['company']->id]));

        $response->assertOk();
        $names = collect($response->json('bankaccounts'))->pluck('name');
        $this->assertTrue($names->contains('Company A Main Bank'));
        $this->assertFalse($names->contains('Company B Secret Bank'));
    }

    public function test_get_accounts_by_company_receivable_ignores_another_companys_id(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        // Only the caller (tenant A) needs the grant -- same reasoning as
        // the branches test above.
        $this->grantAccountingModule($tenantA['company']);

        $assetsA = Account::create(['name' => 'Assets', 'level' => 1, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantA['company']->id]);
        Account::create(['name' => 'Company A Receivable', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantA['company']->id, 'parent_id' => $assetsA->id]);

        $assetsB = Account::create(['name' => 'Assets', 'level' => 1, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantB['company']->id]);
        Account::create(['name' => 'Company B Receivable', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantB['company']->id, 'parent_id' => $assetsB->id]);

        $response = $this->actingAs($tenantA['user'])
            ->getJson(route('get.accounts.by.company.receivable', ['company_id' => $tenantB['company']->id]));

        $response->assertOk();
        $names = collect($response->json('accounts'))->pluck('name');
        $this->assertTrue($names->contains('Company A Receivable'));
        $this->assertFalse($names->contains('Company B Receivable'));
    }

    public function test_get_invoices_by_journal_entry_ignores_another_companys_id(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        // Same grant as the three sibling tests above, and for the same reason: without it
        // accounting fails closed for a legacy fixture company, the request 404s at the
        // module middleware, and this test's own branch treats that blanket 404 as if it
        // were the controller's legitimate "no invoices" 404 -- so the HF-7 isolation
        // assertion below never executes and the test passes while proving nothing.
        $this->grantAccountingModule($tenantA['company']);

        $invoiceA = \App\Models\Invoice::factory()->create([
            'client_id' => $tenantA['client']->id,
            'agent_id' => $tenantA['agent']->id,
        ]);
        $invoiceB = \App\Models\Invoice::factory()->create([
            'client_id' => $tenantB['client']->id,
            'agent_id' => $tenantB['agent']->id,
        ]);

        \App\Models\JournalEntry::create([
            'company_id' => $tenantA['company']->id,
            'account_id' => Account::create(['name' => 'JE Account A', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantA['company']->id])->id,
            'branch_id' => $tenantA['branch']->id,
            'invoice_id' => $invoiceA->id,
            'transaction_date' => now(),
            'name' => 'A entry',
            'description' => 'A entry',
            'debit' => 0,
            'credit' => 0,
            'balance' => 0,
            'type' => 'invoice',
        ]);
        \App\Models\JournalEntry::create([
            'company_id' => $tenantB['company']->id,
            'account_id' => Account::create(['name' => 'JE Account B', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantB['company']->id])->id,
            'branch_id' => $tenantB['branch']->id,
            'invoice_id' => $invoiceB->id,
            'transaction_date' => now(),
            'name' => 'B entry',
            'description' => 'B entry',
            'debit' => 0,
            'credit' => 0,
            'balance' => 0,
            'type' => 'invoice',
        ]);

        $response = $this->actingAs($tenantA['user'])
            ->getJson(route('get.invoices.by.JournalEntry', ['company_id' => $tenantB['company']->id]));

        // The isolation assertion this test exists for can only run on a 200. The endpoint
        // has TWO separate "not found" exits (AccountingController::getInvoicesByJournalEntry
        // returns 404 both when the company has no journal entries and when those entries
        // resolve to no invoices), and the fixture currently lands on one of them -- so the
        // assertion below never executed and this test passed while proving nothing.
        //
        // Deliberately NOT re-asserting the 404 here. Treating the endpoint's blanket "not
        // found" as evidence of tenant isolation is exactly the false green that hid this:
        // if getInvoicesByJournalEntry() ever regressed to trusting $request->company_id for
        // non-admins (the original HF-7 bug), a 404-asserting test would stay green through
        // the regression. Marked incomplete instead, so the missing coverage is visible in
        // every run until the fixture is built out to reach a 200.
        if ($response->status() !== 200) {
            $this->markTestIncomplete(sprintf(
                'Fixture cannot reach the endpoint 200 path (got %d), so the HF-7 cross-tenant '
                .'assertion below never runs. Build the fixture out until company A has a '
                .'journal-linked invoice this endpoint returns.',
                $response->status()
            ));
        }

        $ids = collect($response->json('invoices'))->pluck('id');
        $this->assertFalse($ids->contains($invoiceB->id), 'Company B invoice leaked to company A.');
    }
}
