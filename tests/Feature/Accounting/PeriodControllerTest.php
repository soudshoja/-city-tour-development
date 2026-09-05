<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\Support\AccountingTestCase;

/**
 * P2.5.C (p2_5-brief.md §P2.5.C): "HTTP feature tests for every screen action" — the
 * period-control screen's index view and its four AJAX actions (checklist/close/reopen/close-year),
 * against {@see \App\Http\Controllers\Accounting\PeriodController} + {@see
 * \App\Policies\AccountingPeriodPolicy}.
 */
class PeriodControllerTest extends AccountingTestCase
{
    use GrantsAccountingModule;

    private function makeCompanyAndAdmin(): array
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $company->id]);
        $this->trackCompanyForInvariants($company->id);

        return [$company, $admin];
    }

    /**
     * A non-admin AGENT resolves its company via `agent->branch->company` (getCompanyId(), not
     * session('company_id') -- that fallback is ADMIN-only), so this fixture builds a real Agent
     * inside the target company's own branch. Without this, the AGENT resolves to NO company at
     * all and `module:accounting` middleware 404s the request before the Policy is ever reached --
     * a 404 the module gate is SUPPOSED to produce for an unrelated user, not the 403 these tests
     * mean to exercise for a same-company user who simply lacks the permission.
     */
    private function makeAgentInCompany(Company $company): User
    {
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['id' => 1], ['name' => 'type-1']);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentType->id]);

        return $agentUser;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        $this->trackCompanyForInvariants($company->id);

        $response = $this->get(route('accounting.periods.index', ['company_id' => $company->id]));

        $response->assertRedirect(route('login'));
    }

    public function test_index_renders_for_an_authorized_admin(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $response = $this->actingAs($admin)->get(route('accounting.periods.index', ['year' => 2026]));

        $response->assertOk();
        $response->assertViewIs('accounting.periods.index');
        $response->assertViewHas('year', 2026);
    }

    public function test_checklist_endpoint_returns_json_result(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $response = $this->actingAs($admin)->postJson(route('accounting.periods.checklist'), [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('checklist.can_close', true);
    }

    public function test_close_endpoint_locks_the_period_for_an_authorized_admin(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $response = $this->actingAs($admin)->postJson(route('accounting.periods.close'), [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => 'locked',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('accounting_periods', [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => 'locked',
        ]);
    }

    public function test_close_endpoint_returns_422_when_checklist_blocks(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        Transaction::forceCreate([
            'company_id' => $company->id, 'branch_id' => 1, 'entity_id' => $company->id, 'entity_type' => 'company',
            'transaction_type' => 'JV', 'amount' => 10, 'description' => 'draft', 'reference_type' => 'Invoice',
            'reference_number' => 'D-'.uniqid(), 'name' => 'draft', 'transaction_date' => '2026-03-05',
            'doc_type' => 'JV', 'doc_year' => 2026, 'posting_status' => 'draft',
            'total_debit' => 10, 'total_credit' => 10, 'idempotency_key' => uniqid('draft:'),
        ]);

        $response = $this->actingAs($admin)->postJson(route('accounting.periods.close'), [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => 'locked',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_close_endpoint_forbids_a_role_with_no_permission(): void
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        $agent = $this->makeAgentInCompany($company);
        $this->trackCompanyForInvariants($company->id);

        $response = $this->actingAs($agent)->postJson(route('accounting.periods.close'), [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => 'locked',
        ]);

        $response->assertStatus(403);
    }

    public function test_reopen_endpoint_reopens_a_locked_period_and_records_reason(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);

        $response = $this->actingAs($admin)->postJson(route('accounting.periods.reopen'), [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'reason' => 'audit correction',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertDatabaseHas('accounting_periods', [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_OPEN,
            'reopen_reason' => 'audit correction',
        ]);
    }

    public function test_reopen_endpoint_returns_422_for_a_blank_reason(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);

        $response = $this->actingAs($admin)->postJson(route('accounting.periods.reopen'), [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'reason' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertDatabaseHas('accounting_periods', [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED,
        ]);
    }

    public function test_reopen_endpoint_forbids_a_role_with_no_permission(): void
    {
        $company = Company::factory()->create();
        $this->grantAccountingModule($company);
        $agent = $this->makeAgentInCompany($company);
        $this->trackCompanyForInvariants($company->id);
        AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED]);

        $response = $this->actingAs($agent)->postJson(route('accounting.periods.reopen'), [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'reason' => 'x',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('accounting_periods', [
            'company_id' => $company->id, 'year' => 2026, 'month' => 3, 'status' => AccountingPeriod::STATUS_LOCKED,
        ]);
    }

    public function test_close_year_endpoint_returns_422_when_periods_not_locked(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();

        $response = $this->actingAs($admin)->postJson(route('accounting.periods.close-year'), [
            'company_id' => $company->id, 'year' => 2026,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_close_year_endpoint_succeeds_as_a_no_op_when_every_month_is_locked_with_no_activity(): void
    {
        [$company, $admin] = $this->makeCompanyAndAdmin();
        for ($m = 1; $m <= 12; $m++) {
            AccountingPeriod::create(['company_id' => $company->id, 'year' => 2026, 'month' => $m, 'status' => AccountingPeriod::STATUS_LOCKED]);
        }

        $response = $this->actingAs($admin)->postJson(route('accounting.periods.close-year'), [
            'company_id' => $company->id, 'year' => 2026,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }
}
