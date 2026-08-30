<?php

namespace Tests\Feature\Accounting;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskStatusEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * W6.U "Follow-up tab" (owner addition, 2026-08-28). "Follow-up tab HTTP tests: an authorized
 * admin/accountant sees all hold/confirmed tasks; an agent sees only their own; an agent hitting
 * another agent's task row action (issue/extend/cancel) gets 403 per the new TaskPolicy ability."
 */
class TaskFollowUpTabHttpTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create([
            'user_id' => $companyOwner->id,
            'country_id' => $country->id,
        ]);
        $branchOwner = User::factory()->create();
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id, 'user_id' => $branchOwner->id]);

        session(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function makeAgent(string $suffix): Agent
    {
        $agentType = AgentType::firstOrCreate(['name' => 'w6u-followup-type']);
        $agentUser = User::factory()->create(['role_id' => Role::AGENT]);

        // TaskPolicy::viewFollowUp()'s non-admin/accountant branch is `$user->can('view task')`
        // (the SAME pre-existing permission viewAny() already requires) -- an agent needs it
        // granted to even open the tab at all; manageFollowUp() (the per-record ability the row
        // actions use) then separately scopes them to their own tasks.
        $role = $this->makeSpatieRole('w6u-followup-agent-' . $suffix);
        $role->givePermissionTo('view task');
        $agentUser->assignRole($role);

        $agent = Agent::factory()->create([
            'branch_id' => $this->branch->id,
            'type_id' => $agentType->id,
            'user_id' => $agentUser->id,
            'name' => 'Agent ' . $suffix,
        ]);

        // TaskPolicy::manageFollowUp()'s agent branch resolves ownership via $user->agent -- the
        // User<->Agent link, not role_id alone.
        return $agent;
    }

    private function holdTask(Agent $agent, array $overrides = []): Task
    {
        $supplier = Supplier::factory()->create();

        return Task::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'agent_id' => $agent->id,
            'supplier_id' => $supplier->id,
            'status' => 'on hold',
            'deadline_at' => now()->addHours(20),
        ], $overrides));
    }

    // ---------------------------------------------------------------------------------------
    // Visibility scoping
    // ---------------------------------------------------------------------------------------

    public function test_admin_sees_all_hold_confirmed_tasks(): void
    {
        $agentA = $this->makeAgent('A');
        $agentB = $this->makeAgent('B');
        $taskA = $this->holdTask($agentA, ['passenger_name' => 'Passenger Alpha ' . uniqid()]);
        $taskB = $this->holdTask($agentB, ['passenger_name' => 'Passenger Bravo ' . uniqid()]);

        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $admin->assignRole($this->makeSpatieRole('admin'));

        $response = $this->actingAs($admin)->get(route('tasks.follow-up'));

        $response->assertOk();
        $response->assertSee($taskA->passenger_name);
        $response->assertSee($taskB->passenger_name);
    }

    public function test_agent_sees_only_their_own_tasks(): void
    {
        $agentA = $this->makeAgent('A');
        $agentB = $this->makeAgent('B');
        $taskA = $this->holdTask($agentA, ['passenger_name' => 'Passenger Alpha ' . uniqid()]);
        $taskB = $this->holdTask($agentB, ['passenger_name' => 'Passenger Bravo ' . uniqid()]);

        $agentAUser = $agentA->user;

        $response = $this->actingAs($agentAUser)->get(route('tasks.follow-up'));

        $response->assertOk();
        $response->assertSee($taskA->passenger_name);
        $response->assertDontSee($taskB->passenger_name);
    }

    public function test_follow_up_count_endpoint_scopes_to_the_agent_too(): void
    {
        $agentA = $this->makeAgent('A');
        $agentB = $this->makeAgent('B');
        $this->holdTask($agentA);
        $this->holdTask($agentB);
        $this->holdTask($agentB);

        $response = $this->actingAs($agentA->user)->getJson(route('tasks.follow-up.count'));

        $response->assertOk();
        $response->assertJson(['success' => true, 'count' => 1]);
    }

    // ---------------------------------------------------------------------------------------
    // Row actions -- ownership-scoped 403
    // ---------------------------------------------------------------------------------------

    public function test_agent_hitting_another_agents_task_gets_403_on_issue(): void
    {
        $agentA = $this->makeAgent('A');
        $agentB = $this->makeAgent('B');
        $taskB = $this->holdTask($agentB);

        $response = $this->actingAs($agentA->user)->postJson(route('tasks.follow-up.issue', $taskB->id));

        $response->assertForbidden();
        $this->assertSame('on hold', $taskB->fresh()->status);
    }

    public function test_agent_hitting_another_agents_task_gets_403_on_extend(): void
    {
        $agentA = $this->makeAgent('A');
        $agentB = $this->makeAgent('B');
        $taskB = $this->holdTask($agentB);

        $response = $this->actingAs($agentA->user)->postJson(route('tasks.follow-up.extend', $taskB->id), [
            'deadline_at' => now()->addDays(2)->toDateTimeString(),
            'reason' => 'trying to extend a task that is not mine',
        ]);

        $response->assertForbidden();
    }

    public function test_agent_hitting_another_agents_task_gets_403_on_cancel(): void
    {
        $agentA = $this->makeAgent('A');
        $agentB = $this->makeAgent('B');
        $taskB = $this->holdTask($agentB);

        $response = $this->actingAs($agentA->user)->postJson(route('tasks.follow-up.cancel', $taskB->id));

        $response->assertForbidden();
        $this->assertSame('on hold', $taskB->fresh()->status);
    }

    public function test_agent_can_act_on_their_own_task(): void
    {
        $agentA = $this->makeAgent('A');
        $taskA = $this->holdTask($agentA);

        $response = $this->actingAs($agentA->user)->postJson(route('tasks.follow-up.extend', $taskA->id), [
            'deadline_at' => now()->addDays(3)->toDateTimeString(),
            'reason' => 'client asked for more time',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $event = TaskStatusEvent::where('task_id', $taskA->id)->where('event', 'follow_up_extend_deadline')->first();
        $this->assertNotNull($event, 'The extend action must write an audit row.');
        $this->assertSame('client asked for more time', $event->meta['reason']);
    }

    public function test_note_action_is_stored_and_audited(): void
    {
        $agentA = $this->makeAgent('A');
        $taskA = $this->holdTask($agentA);

        $response = $this->actingAs($agentA->user)->postJson(route('tasks.follow-up.note', $taskA->id), [
            'note' => 'Client confirmed payment will arrive tomorrow.',
        ]);

        $response->assertOk();

        $event = TaskStatusEvent::where('task_id', $taskA->id)->where('event', 'follow_up_note')->first();
        $this->assertNotNull($event);
        $this->assertSame('Client confirmed payment will arrive tomorrow.', $event->meta['note']);
    }

    public function test_cancel_action_flips_status_and_is_a_no_op_on_ledger(): void
    {
        $agentA = $this->makeAgent('A');
        $taskA = $this->holdTask($agentA);

        $response = $this->actingAs($agentA->user)->postJson(route('tasks.follow-up.cancel', $taskA->id), [
            'reason' => 'client withdrew',
        ]);

        $response->assertOk();
        $this->assertSame('cancelled', $taskA->fresh()->status);
    }

    private function makeSpatieRole(string $name): SpatieRole
    {
        return SpatieRole::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
}
