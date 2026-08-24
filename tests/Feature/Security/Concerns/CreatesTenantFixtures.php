<?php

namespace Tests\Feature\Security\Concerns;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;

/**
 * Shared fixture builder for the P0 launch-hotfix regression tests
 * (tests/Feature/Security/*TenantIsolationTest.php and friends).
 *
 * Builds a full Company -> Branch -> Agent -> Client tenant, plus a
 * Role::COMPANY-owning authenticated User for it, mirroring the exact
 * "user directly owns a company" resolution path getCompanyId() and
 * every controller in the app already use (see
 * tests/Feature/Security/AccountingRouteGateTest.php::createCompanyOwner(),
 * which this is modelled on). Every module defaults ON for a plain
 * company with no module.* rows (see RequiresCompanyModule), so these
 * fixtures never need an explicit module preset.
 */
trait CreatesTenantFixtures
{
    /**
     * @return array{user: User, company: Company, branch: Branch, agent: Agent, client: Client}
     */
    private function createTenant(array $permissions = []): array
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $user->id]);
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'Salary']);
        $agent = Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $user->id, 'type_id' => $agentType->id]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);

        if (! empty($permissions)) {
            $role = Role::create([
                'name' => 'company-'.$company->id,
                'guard_name' => 'web',
                'company_id' => $company->id,
            ]);
            $user->assignRole($role);
            $role->givePermissionTo($permissions);
        }

        return compact('user', 'company', 'branch', 'agent', 'client');
    }

    /**
     * Creates a second, Role::AGENT-level user attached to an existing
     * tenant (from createTenant()) via a brand-new Agent row of their own.
     * Used to prove a legitimate, permissioned user of company A cannot
     * reach into company B's data merely by supplying company B's ids.
     */
    private function createAgentUserFor(array $tenant, array $permissions = []): User
    {
        $user = User::factory()->create(['role_id' => Role::AGENT]);
        $agentType = AgentType::firstOrCreate(['name' => 'Salary']);
        Agent::factory()->create(['branch_id' => $tenant['branch']->id, 'user_id' => $user->id, 'type_id' => $agentType->id]);

        if (! empty($permissions)) {
            $role = Role::firstOrCreate(
                ['name' => 'agent-'.$tenant['company']->id, 'guard_name' => 'web', 'company_id' => $tenant['company']->id]
            );
            $user->assignRole($role);
            $role->givePermissionTo($permissions);
        }

        return $user->fresh();
    }

    protected function tearDownTenantFixtures(): void
    {
        // hasModule() memoizes per company+module for the life of the PHP
        // process; tests reuse one process across many cases, so the memo
        // must be dropped between tests (see AccountingRouteGateTest).
        Company::forgetModuleCache();
    }
}
