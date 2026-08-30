<?php

namespace Tests\Unit\Policies;

use App\Models\Accountant;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * KEY: invoice-policy-edit-gates. W3a EDIT PERMISSION GATES (owner decision 2026-08-27: staff DO
 * edit amounts/lines post-issue and edit dates in real practice, but this must be role/permission
 * gated — admin/accountant only — not free-for-all). Pins the two new InvoicePolicy abilities,
 * editAfterIssue() and editDates(), including:
 *
 *  - the dual-check convention (Spatie hasRole('admin'|'accountant') OR the legacy
 *    role_id === Role::ADMIN|ACCOUNTANT integer) — a seeded admin/accountant row must pass even
 *    when only ONE of the two markers is present;
 *  - fail-closed denial for a bare agent and for a user with no resolvable company;
 *  - the module gate (Modules::PAYMENT_GATEWAY switched off) denying even an admin;
 *  - the exact production invocation shape: controllers call
 *    Gate::authorize('edit-after-issue', $invoice) — kebab-case ability strings with a MODEL
 *    INSTANCE argument — which Gate must still resolve to the camelCase policy methods.
 */
class InvoicePolicyEditGatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp(); // runs PermissionSeeder (RefreshDatabase is in use)

        Company::forgetModuleCache();
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();

        parent::tearDown();
    }

    /**
     * @return array{0: Company, 1: Branch, 2: int}
     */
    private function makeCompanyBranch(): array
    {
        // AgentFactory defaults type_id = 1 and agents.type_id is an FK into agent_type — seed the
        // row before any Agent fixture is created. Note: AgentType::$fillable is ['name'], so an
        // 'id' attribute is silently DROPPED on create — never force an id; use the row's real id.
        $agentType = AgentType::firstOrCreate(['name' => 'type-1']);

        $company = Company::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);

        $branch = Branch::factory()->create([
            'company_id' => $company->id,
            'user_id' => User::factory()->create()->id,
        ]);

        return [$company, $branch, $agentType->id];
    }

    public function test_admin_role_id_passes_both_abilities_and_the_kebab_instance_invocation(): void
    {
        [$company, $branch, $agentTypeId] = $this->makeCompanyBranch();
        session(['company_id' => $company->id]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $this->assertTrue($admin->can('editAfterIssue', Invoice::class));
        $this->assertTrue($admin->can('editDates', Invoice::class));

        // The exact call shape the controller uses (kebab-case ability + model instance) must
        // resolve to the camelCase policy methods too.
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create()->id,
            'type_id' => $agentTypeId,
        ]);
        $clientUser = \App\Models\Client::factory()->create(['agent_id' => $agent->id]);
        $invoice = Invoice::factory()->create(['agent_id' => $agent->id, 'client_id' => $clientUser->id]);

        $this->assertTrue(Gate::forUser($admin)->allows('edit-after-issue', $invoice));
        $this->assertTrue(Gate::forUser($admin)->allows('edit-dates', $invoice));
    }

    public function test_spatie_admin_role_alone_passes_even_without_the_legacy_role_id(): void
    {
        [$company, $branch, $agentTypeId] = $this->makeCompanyBranch();

        // A bare agent (legacy role) who has ONLY the Spatie 'admin' role — the OR in the
        // ability must pick up hasRole('admin') even though role_id is Role::AGENT.
        Role::create(['name' => 'admin', 'guard_name' => 'web', 'company_id' => $company->id]);

        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentTypeId]);
        $agentUser->assignRole('admin');

        $this->assertTrue($agentUser->can('editAfterIssue', Invoice::class));
        $this->assertTrue($agentUser->can('editDates', Invoice::class));
    }

    public function test_accountant_role_id_passes_both_abilities(): void
    {
        [$company, $branch] = $this->makeCompanyBranch();

        $accountantUser = User::factory()->create(['role_id' => Role::ACCOUNTANT]);
        Accountant::create([
            'user_id' => $accountantUser->id,
            'name' => 'Test Accountant',
            'email' => 'accountant@example.test',
            'country_code' => '+965',
            'phone_number' => '55500000',
            'branch_id' => $branch->id,
        ]);

        $this->assertTrue($accountantUser->can('editAfterIssue', Invoice::class));
        $this->assertTrue($accountantUser->can('editDates', Invoice::class));
    }

    public function test_bare_agent_is_denied_both_abilities(): void
    {
        [$company, $branch, $agentTypeId] = $this->makeCompanyBranch();

        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);
        Agent::factory()->create(['branch_id' => $branch->id, 'user_id' => $agentUser->id, 'type_id' => $agentTypeId]);

        $this->assertFalse($agentUser->can('editAfterIssue', Invoice::class));
        $this->assertFalse($agentUser->can('editDates', Invoice::class));
    }

    public function test_user_with_no_resolvable_company_is_denied_even_as_admin_role(): void
    {
        // A Role::CLIENT user has no case in getCompanyId() and owns no company/branch/agent/
        // accountant relation -> moduleEnabled() fails closed -> both abilities deny, even though
        // the module defaults ON for companies that do resolve.
        $user = User::factory()->create(['role_id' => Role::CLIENT]);

        $this->assertFalse($user->can('editAfterIssue', Invoice::class));
        $this->assertFalse($user->can('editDates', Invoice::class));
    }

    public function test_payment_gateway_module_disabled_denies_even_an_admin(): void
    {
        [$company] = $this->makeCompanyBranch();
        session(['company_id' => $company->id]);

        // 'type' must precede 'value' so Setting::setValueAttribute sees the boolean type (the
        // mutator keys off $this->type, and fill follows the input array's key order).
        Setting::create([
            'company_id' => $company->id,
            'key' => Modules::settingKey(Modules::PAYMENT_GATEWAY),
            'type' => 'boolean',
            'value' => false,
        ]);
        Company::forgetModuleCache();

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        $this->assertFalse($admin->can('editAfterIssue', Invoice::class));
        $this->assertFalse($admin->can('editDates', Invoice::class));
    }
}
