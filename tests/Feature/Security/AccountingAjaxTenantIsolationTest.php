<?php

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\Branch;
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

    public function test_get_branches_by_company_ignores_another_companys_id(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

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

        // Either company A has no journal-linked invoices of its own (404,
        // the endpoint's documented "not found" shape) or it returns only
        // its own invoice -- either way company B's invoice must never
        // appear.
        if ($response->status() === 200) {
            $ids = collect($response->json('invoices'))->pluck('id');
            $this->assertFalse($ids->contains($invoiceB->id));
        } else {
            $response->assertNotFound();
        }
    }
}
