<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Setting;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\Concerns\CreatesTenantFixtures;
use Tests\TestCase;

/**
 * Regression coverage for AP-1/AP-2/AP-3/AP-7/AP-9 (Agent Profit Calculation module
 * authorization wave, feat/travelerp-launch): before this wave, only
 * AgentController::index() called Gate::authorize -- show(), update(),
 * updateCommission(), getTasks(), getClients(), getInvoices(), store(), upload(),
 * import() and exportCsv() had no check at all, and reports/profit-agent had neither
 * a module gate nor any authorization. Any authenticated user of any company could
 * open another company's agent detail page (full profit/loss/commission/bonus
 * detail), edit another company's agent's salary, or pull another company's agent
 * tasks/clients/invoices by id.
 */
class AgentProfitTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenantFixtures;

    protected function tearDown(): void
    {
        $this->tearDownTenantFixtures();
        parent::tearDown();
    }

    public function test_company_b_user_cannot_view_company_as_agent_detail_page(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $response = $this->actingAs($tenantB['user'])->get(route('agents.show', $tenantA['agent']->id));

        $this->assertNotEquals(200, $response->status(), 'Company B must not be able to open company A\'s agent detail page.');
    }

    public function test_company_b_user_cannot_edit_company_as_agent_salary(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $originalSalary = $tenantA['agent']->salary;

        $response = $this->actingAs($tenantB['user'])->put(route('agents.update', $tenantA['agent']->id), [
            'name' => $tenantA['agent']->name,
            'email' => $tenantA['agent']->email,
            'password' => 'Secret123!',
            'salary' => (float) $originalSalary + 999,
        ]);

        $this->assertNotEquals(200, $response->status(), 'Company B must not be able to PUT-edit company A\'s agent.');
        $this->assertEquals(
            (float) $originalSalary,
            (float) $tenantA['agent']->fresh()->salary,
            'A denied cross-tenant update must never persist -- the salary must be unchanged.'
        );
    }

    public function test_company_b_user_cannot_read_company_as_agent_json_endpoints(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        foreach (['agents.tasks', 'agents.clients', 'agents.invoices'] as $routeName) {
            $response = $this->actingAs($tenantB['user'])->get(route($routeName, $tenantA['agent']->id));

            $this->assertNotEquals(200, $response->status(), "{$routeName} must not leak company A's agent data to company B.");
        }
    }

    public function test_module_off_hides_agents_index_and_profit_report_behind_404(): void
    {
        $tenant = $this->createTenant(['view agent']);

        Setting::updateOrCreate(
            ['company_id' => $tenant['company']->id, 'key' => Modules::settingKey(Modules::AGENT_PROFIT)],
            ['type' => 'boolean', 'value' => false]
        );
        Company::forgetModuleCache();

        $indexResponse = $this->actingAs($tenant['user'])->get(route('agents.index'));
        $reportResponse = $this->actingAs($tenant['user'])->get(route('reports.profit-agent'));

        $indexResponse->assertNotFound();
        $reportResponse->assertNotFound();
    }

    public function test_module_on_permissioned_user_can_view_own_agent(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAs($tenant['user'])->get(route('agents.show', $tenant['agent']->id));

        $response->assertOk();
    }

    public function test_removed_edit_and_agent_report_routes_are_gone(): void
    {
        $tenant = $this->createTenant();

        $editResponse = $this->actingAs($tenant['user'])->get('/agents/'.$tenant['agent']->id.'/edit');
        $reportAgentResponse = $this->actingAs($tenant['user'])->get('/reports/agent');

        $editResponse->assertNotFound();
        $reportAgentResponse->assertNotFound();
    }
}
