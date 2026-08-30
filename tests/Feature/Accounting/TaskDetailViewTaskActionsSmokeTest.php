<?php

namespace Tests\Feature\Accounting;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * W6.U "Task actions" -- render-only smoke test for resources/views/tasks/detail.blade.php,
 * the full-page task-detail view named explicitly in w6-brief.md's own "W6.U -- UI" file list
 * (`detail.blade.php`/`singleTask.blade.php`/`partial/view-task-modal.blade.php`). A previous
 * verify pass flagged this as an undisclosed scope gap -- the Void / Void-with-fee / Reissue
 * task-action UI had landed only on the view-task modal, not on this full-page route. This proves
 * the new server-rendered @can()-gated "Task actions" card actually EXECUTES against real data
 * (not just Blade::compileString(), which only proves the template compiles) and shows the
 * ticket_status/client_status badges alongside the legacy status badge.
 */
class TaskDetailViewTaskActionsSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    public function test_task_detail_page_renders_task_actions_card_and_status_badges(): void
    {
        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $companyOwner->id, 'country_id' => $country->id]);
        CoaSeeder::run($company->id);
        session(['company_id' => $company->id]);

        $branchUser = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchUser->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'w6u-detail-smoke-type']);
        $agentUser = User::factory()->create();
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => $agentUser->id,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id, 'company_id' => $company->id]);
        $supplier = Supplier::factory()->create();

        $task = Task::factory()->create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'ticket_status' => 'issued',
            'client_status' => 'open',
            'reference' => 'PNR-SMOKE-' . uniqid(),
        ]);

        // The company owner IS the task's owning company here, so detail()'s own
        // `$companyId = $user->company->id` branch (Role::COMPANY) matches the task's
        // company_id -- unlike the Role::ADMIN branch, which hardcodes companyId=1
        // regardless of the task's real company and would fail this test's ownership check
        // for an unrelated reason.
        $role = SpatieRole::firstOrCreate(['name' => 'w6u-detail-smoke-role', 'guard_name' => 'web']);
        $role->givePermissionTo(['view task', 'void task', 'reissue task']);
        $companyOwner->assignRole('w6u-detail-smoke-role');

        $response = $this->actingAs($companyOwner)->get(route('tasks.detail', ['tasks' => $task->id]));

        $response->assertOk();
        $response->assertSee('Task actions');
        $response->assertSee('Void with fee');
        $response->assertSee('Reissue');
        $response->assertSee('Ticket status');
        $response->assertSee('Client status');
    }
}
