<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\ReportController;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for HF-9: profitLoss() had no authorization check at
 * all; journalEntriesByDate() silently returned EVERY company's journal
 * entries whenever getCompanyId() resolved falsy (fail-open); and
 * SupplierController::ledgerByDateRange() had no gate and no company
 * scoping whatsoever, leaking any company's task/booking data for any
 * supplier_id to any authenticated user.
 */
class ReportTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_profit_loss_route_denies_a_user_without_the_permission(): void
    {
        // No permissions granted at all -- this used to be a completely
        // open endpoint for any authenticated user who could resolve a
        // companyId.
        $tenant = $this->createTenant();

        $response = $this->actingAs($tenant['user'])->get(route('reports.profit-loss'));

        $response->assertForbidden();
    }

    public function test_profit_loss_route_allows_a_user_with_the_permission(): void
    {
        $tenant = $this->createTenant(['view profit loss']);

        $response = $this->actingAs($tenant['user'])->get(route('reports.profit-loss'));

        $response->assertOk();
    }

    public function test_journal_entries_by_date_fails_closed_for_unresolvable_company(): void
    {
        // A Role::AGENT user with NO Agent row at all: getCompanyId()
        // returns null for this (every case in its switch requires a
        // relation that doesn't exist here), reproducing the exact
        // "orphaned companyId" fail-open scenario this hotfix closes.
        $orphan = User::factory()->create(['role_id' => Role::AGENT]);

        $this->actingAs($orphan);

        // Called directly: the route itself now also carries
        // middleware('module:accounting'), which independently 404s this
        // same orphaned-company case before the controller is even
        // reached (a second, route-layer defense added since this
        // hotfix's discovery). Calling the controller method directly
        // proves THIS hotfix's own abort_unless() still fails closed on
        // its own, in case the method is ever reachable without that
        // middleware in front of it.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ReportController::class)->journalEntriesByDate(
            \Illuminate\Http\Request::create('/reports/settlements/entries/by-date', 'GET', ['date' => now()->toDateString()])
        );
    }

    public function test_journal_entries_by_date_only_returns_the_callers_company(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $date = now()->toDateString();

        $accountA = \App\Models\Account::create(['name' => 'A account', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantA['company']->id]);
        $accountB = \App\Models\Account::create(['name' => 'B account', 'level' => 4, 'actual_balance' => 0, 'budget_balance' => 0, 'variance' => 0, 'company_id' => $tenantB['company']->id]);

        JournalEntry::create([
            'company_id' => $tenantA['company']->id,
            'account_id' => $accountA->id,
            'branch_id' => $tenantA['branch']->id,
            'name' => 'A entry',
            'description' => 'A entry',
            'debit' => 0, 'credit' => 0, 'balance' => 0,
            'type' => 'invoice',
            'transaction_date' => $date,
        ]);
        JournalEntry::create([
            'company_id' => $tenantB['company']->id,
            'account_id' => $accountB->id,
            'branch_id' => $tenantB['branch']->id,
            'name' => 'B entry',
            'description' => 'B entry',
            'debit' => 0, 'credit' => 0, 'balance' => 0,
            'type' => 'invoice',
            'transaction_date' => $date,
        ]);

        $response = $this->actingAs($tenantA['user'])->getJson(route('reports.settlements.entries.by_date', ['date' => $date]));

        $response->assertOk();
        $descriptions = collect($response->json('entries'))->pluck('description');
        $this->assertTrue($descriptions->contains('A entry'));
        $this->assertFalse($descriptions->contains('B entry'));
    }

    public function test_supplier_ledger_by_date_range_only_returns_the_callers_company_tasks(): void
    {
        $tenantA = $this->createTenant(['view supplier']);
        $tenantB = $this->createTenant();

        $supplier = \App\Models\Supplier::factory()->create();

        $from = now()->subDays(5)->toDateString();
        $to = now()->addDays(5)->toDateString();

        Task::factory()->create([
            'company_id' => $tenantA['company']->id,
            'agent_id' => $tenantA['agent']->id,
            'supplier_id' => $supplier->id,
            'supplier_pay_date' => now(),
        ]);
        Task::factory()->create([
            'company_id' => $tenantB['company']->id,
            'agent_id' => $tenantB['agent']->id,
            'supplier_id' => $supplier->id,
            'supplier_pay_date' => now(),
        ]);

        $response = $this->actingAs($tenantA['user'])->getJson(
            route('suppliers.suppliers.ledger-by-date', ['supplierId' => $supplier->id, 'fromDate' => $from, 'toDate' => $to])
        );

        $response->assertOk();
        $entries = collect($response->json('entries'));
        $this->assertTrue($entries->every(fn ($t) => $t['agent_id'] === $tenantA['agent']->id));
        $this->assertFalse($entries->contains(fn ($t) => $t['agent_id'] === $tenantB['agent']->id));
    }

    public function test_supplier_ledger_by_date_range_denies_a_user_without_the_permission(): void
    {
        $tenant = $this->createTenant();
        $supplier = \App\Models\Supplier::factory()->create();

        $response = $this->actingAs($tenant['user'])->getJson(
            route('suppliers.suppliers.ledger-by-date', ['supplierId' => $supplier->id, 'fromDate' => now()->toDateString(), 'toDate' => now()->toDateString()])
        );

        $response->assertForbidden();
    }
}
