<?php

namespace Tests\Feature\Accounting;

use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaskPendingAction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TaskStatusService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Support\AccountingTestCase;

/**
 * W6.U "Task actions" (w6-brief.md "W6.U -- UI"). HTTP-layer coverage for
 * `POST /tasks/{task}/void`, `POST /tasks/{task}/void` with a fee override, the fee-override
 * approval gate (`POST /tasks/pending-actions/{id}/approve|reject`), and
 * `GET /tasks/{task}/reissue-preview` + `POST /tasks/{task}/reissue`.
 *
 * Verify criteria covered (w6-brief.md "W6.U -- UI" -> "Verify criteria"):
 *  2. Void-with-fee posts exactly the W6.V worked-example shape when submitted with an override
 *     fee and required approval, and is blocked pending approval until the approver acts.
 *  3. Reissue preview amount equals what actually posts.
 *  4. Unauthorized users hit 403; a locked invoice refuses (controller-level, not just hidden
 *     client-side).
 */
class TaskControllerW6UVoidReissueActionsTest extends AccountingTestCase
{
    private TaskStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Company::forgetModuleCache();
        $this->service = new TaskStatusService;
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        Company::forgetModuleCache();
        parent::tearDown();
    }

    /** @return array{0: Company, 1: Agent, 2: Client, 3: Supplier} */
    private function makeCompanyAgentClientSupplier(): array
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);

        $branchOwner = User::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'user_id' => $branchOwner->id]);
        $agentType = AgentType::firstOrCreate(['name' => 'w6u-test-type']);
        $agent = Agent::factory()->create([
            'branch_id' => $branch->id,
            'type_id' => $agentType->id,
            'user_id' => User::factory()->create()->id,
            'commission' => 0.15,
        ]);
        $client = Client::factory()->create(['agent_id' => $agent->id]);
        $supplier = Supplier::factory()->create(['name' => 'W6U Test Supplier']);

        return [$company, $agent, $client, $supplier];
    }

    private function enableEngine(Company $company): void
    {
        config(['accounting.engine.enabled' => true]);
        (new SystemAccountsSeeder())->run();
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);
    }

    private function makeIssuedTask(Company $company, Agent $agent, Client $client, Supplier $supplier, array $overrides = []): Task
    {
        return Task::factory()->create(array_merge([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'client_id' => $client->id,
            'supplier_id' => $supplier->id,
            'type' => 'flight',
            'status' => 'issued',
            'reference' => 'PNR-' . uniqid(),
            'price' => 500.0,
            'total' => 350.0,
        ], $overrides));
    }

    /** @return array{0: Invoice, 1: InvoiceDetail} */
    private function issueTask(Task $task): array
    {
        $result = $this->service->issue($task);
        $this->assertTrue($result['success'] ?? false, json_encode($result));

        $invoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
        $invoice = Invoice::find($invoiceDetail->invoice_id);

        return [$invoice, $invoiceDetail];
    }

    private function userWithPermission(string $permission): User
    {
        SpatieRole::firstOrCreate(['name' => 'w6u-test-role-' . $permission, 'guard_name' => 'web']);
        $role = SpatieRole::where('name', 'w6u-test-role-' . $permission)->first();
        $role->givePermissionTo($permission);

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->assignRole('w6u-test-role-' . $permission);

        return $user;
    }

    // ---------------------------------------------------------------------------------------
    // Authorization (verify criterion 4)
    // ---------------------------------------------------------------------------------------

    public function test_void_route_returns_403_without_permission(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        session(['company_id' => $company->id]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        // No permission granted at all -- TaskPolicy::void() falls through to $user->can(), which
        // is false with no role/permission assignment.
        $user->syncRoles([]);

        $response = $this->actingAs($user)->postJson(route('tasks.void', $task->id), []);

        $response->assertForbidden();
        $this->assertNotSame('void', $task->fresh()->ticket_status);
    }

    public function test_reissue_route_returns_403_without_permission(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        session(['company_id' => $company->id]);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($oldTask);
        $newTask = Task::factory()->create([
            'company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id,
            'supplier_id' => $supplier->id, 'type' => 'flight', 'status' => 'reissued',
            'reference' => $oldTask->reference, 'price' => 600.0,
        ]);

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->syncRoles([]);

        $response = $this->actingAs($user)->postJson(route('tasks.reissue', $oldTask->id), ['new_task_id' => $newTask->id]);

        $response->assertForbidden();
    }

    public function test_void_route_refuses_on_a_locked_invoice(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        session(['company_id' => $company->id]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        [$invoice] = $this->issueTask($task);
        $invoice->update(['is_locked' => true]);

        $user = $this->userWithPermission('void task');

        $response = $this->actingAs($user)->postJson(route('tasks.void', $task->id), []);

        $response->assertStatus(422);
        $this->assertNotSame('void', $task->fresh()->ticket_status, 'A locked invoice must refuse at the controller level, not only be hidden client-side.');
    }

    // ---------------------------------------------------------------------------------------
    // Void / void-with-fee (verify criterion 2)
    // ---------------------------------------------------------------------------------------

    public function test_void_route_happy_path_reverses_the_sale(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        session(['company_id' => $company->id]);

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);

        $user = $this->userWithPermission('void task');

        $response = $this->actingAs($user)->postJson(route('tasks.void', $task->id), []);

        $response->assertOk();
        $response->assertJson(['success' => true, 'ticket_status' => 'void']);
        $this->assertSame('void', $task->fresh()->ticket_status);
    }

    public function test_void_with_fee_posts_immediately_when_override_matches_schedule(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        session(['company_id' => $company->id]);

        // 'free' override policy -- schedule fee always resolves to 0, so ANY caller-supplied fee
        // is a genuine override needing approval EXCEPT when the caller passes 0 too. Use a flat
        // fee-schedule amount instead so the schedule fee is a known non-zero figure the test can
        // submit verbatim (no override -> posts immediately).
        Setting::updateOrCreate(
            ['key' => 'accounting.refund.fee_schedule.flight.amount', 'company_id' => $company->id],
            ['value' => 15.0, 'type' => 'string']
        );
        Setting::updateOrCreate(
            ['key' => 'accounting.refund.fee_schedule.flight.override', 'company_id' => $company->id],
            ['value' => 'needs_approval', 'type' => 'string']
        );

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);

        $user = $this->userWithPermission('void task');

        $response = $this->actingAs($user)->postJson(route('tasks.void', $task->id), ['fee' => 15.0]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertSame('void', $task->fresh()->ticket_status);

        $feeDoc = Transaction::withoutGlobalScopes()->where('idempotency_key', 'void:' . $task->id . ':fee')->first();
        $this->assertNotNull($feeDoc, 'The fee DBN must have posted when the submitted fee equals the schedule fee (no approval needed).');
    }

    public function test_void_with_fee_override_requires_approval_then_posts_on_approve(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        session(['company_id' => $company->id]);

        // No fee_schedule.flight.amount/.percent configured -- resolveFeeFromSchedule() falls
        // through to the CALLER'S own figure in that case (schedule_fee preview resolves to 0),
        // so any non-zero fee the operator types is a genuine override needing approval (the
        // shipped default override policy is already 'needs_approval', not set explicitly here
        // to also prove the DEFAULT itself gates, not just an explicitly-configured value).
        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);

        $requester = $this->userWithPermission('void task');

        // Override fee (30) differs from the schedule fee (0, unconfigured) -- must be blocked
        // pending approval.
        $response = $this->actingAs($requester)->postJson(route('tasks.void', $task->id), ['fee' => 30.0]);

        $response->assertStatus(202);
        $response->assertJson(['success' => false, 'pending_approval' => true]);
        $this->assertNotSame('void', $task->fresh()->ticket_status, 'Nothing may post until the override is approved.');

        $pendingAction = TaskPendingAction::first();
        $this->assertNotNull($pendingAction);
        $this->assertSame(TaskPendingAction::STATUS_PENDING, $pendingAction->status);
        $this->assertEqualsWithDelta(30.0, (float) $pendingAction->payload['fee'], 0.001);

        $approver = $this->userWithPermission('approve task fee override');

        $approveResponse = $this->actingAs($approver)->postJson(route('tasks.pending-actions.approve', $pendingAction->id));

        $approveResponse->assertOk();
        $approveResponse->assertJson(['success' => true]);
        $this->assertSame('void', $task->fresh()->ticket_status, 'Approval must actually trigger the posting.');
        $this->assertSame(TaskPendingAction::STATUS_APPROVED, $pendingAction->fresh()->status);

        $feeDoc = Transaction::withoutGlobalScopes()->where('idempotency_key', 'void:' . $task->id . ':fee')->first();
        $this->assertNotNull($feeDoc);
        $this->assertEqualsWithDelta(30.0, (float) $feeDoc->journalEntries->first()->debit, 0.01);
    }

    public function test_void_with_fee_override_can_be_rejected_and_never_posts(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        session(['company_id' => $company->id]);

        Setting::updateOrCreate(
            ['key' => 'accounting.refund.fee_schedule.flight.amount', 'company_id' => $company->id],
            ['value' => 15.0, 'type' => 'string']
        );

        $task = $this->makeIssuedTask($company, $agent, $client, $supplier);
        $this->issueTask($task);

        $requester = $this->userWithPermission('void task');
        $this->actingAs($requester)->postJson(route('tasks.void', $task->id), ['fee' => 50.0])->assertStatus(202);

        $pendingAction = TaskPendingAction::first();
        $approver = $this->userWithPermission('approve task fee override');

        $rejectResponse = $this->actingAs($approver)->postJson(route('tasks.pending-actions.reject', $pendingAction->id), ['reason' => 'not authorized']);

        $rejectResponse->assertOk();
        $this->assertSame(TaskPendingAction::STATUS_REJECTED, $pendingAction->fresh()->status);
        $this->assertNotSame('void', $task->fresh()->ticket_status);
    }

    // ---------------------------------------------------------------------------------------
    // Reissue preview (verify criterion 3)
    // ---------------------------------------------------------------------------------------

    public function test_reissue_preview_matches_what_actually_posts(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        session(['company_id' => $company->id]);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 500.0]);
        $this->issueTask($oldTask);

        $newTask = Task::factory()->create([
            'company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id,
            'supplier_id' => $supplier->id, 'type' => 'flight', 'status' => 'reissued',
            'reference' => $oldTask->reference, 'passenger_name' => $oldTask->passenger_name,
            'original_task_id' => $oldTask->id, 'price' => 650.0, 'total' => 400.0,
        ]);

        $user = $this->userWithPermission('reissue task');

        $previewResponse = $this->actingAs($user)->getJson(route('tasks.reissue-preview', $oldTask->id) . '?new_task_id=' . $newTask->id);
        $previewResponse->assertOk();
        $previewJson = $previewResponse->json();

        $this->assertSame('dbn', $previewJson['fare_difference']['type']);
        $this->assertEqualsWithDelta(150.0, (float) $previewJson['fare_difference']['amount'], 0.01);

        $postResponse = $this->actingAs($user)->postJson(route('tasks.reissue', $oldTask->id), ['new_task_id' => $newTask->id]);
        $postResponse->assertOk();
        $postJson = $postResponse->json();

        $this->assertSame(
            $previewJson['fare_difference']['amount'],
            $postJson['fare_difference']['amount'],
            'The preview must equal what actually posted.'
        );
        $this->assertSame($previewJson['fare_difference']['type'], $postJson['fare_difference']['type']);
        $this->assertSame('reissued', $oldTask->fresh()->ticket_status);
    }

    public function test_reissue_with_fee_override_also_requires_approval_then_posts_on_approve(): void
    {
        [$company, $agent, $client, $supplier] = $this->makeCompanyAgentClientSupplier();
        $this->enableEngine($company);
        $this->trackCompanyForInvariants($company->id);
        session(['company_id' => $company->id]);

        $oldTask = $this->makeIssuedTask($company, $agent, $client, $supplier, ['price' => 500.0]);
        $this->issueTask($oldTask);

        $newTask = Task::factory()->create([
            'company_id' => $company->id, 'agent_id' => $agent->id, 'client_id' => $client->id,
            'supplier_id' => $supplier->id, 'type' => 'flight', 'status' => 'reissued',
            'reference' => $oldTask->reference, 'passenger_name' => $oldTask->passenger_name,
            'original_task_id' => $oldTask->id, 'price' => 650.0, 'total' => 400.0,
        ]);

        $requester = $this->userWithPermission('reissue task');

        $response = $this->actingAs($requester)->postJson(route('tasks.reissue', $oldTask->id), [
            'new_task_id' => $newTask->id,
            'fee' => 25.0,
        ]);

        $response->assertStatus(202);
        $response->assertJson(['success' => false, 'pending_approval' => true]);
        $this->assertNotSame('reissued', $oldTask->fresh()->ticket_status, 'Nothing may post until the fee override is approved.');

        $pendingAction = TaskPendingAction::first();
        $this->assertSame(TaskPendingAction::ACTION_REISSUE_WITH_FEE, $pendingAction->action);
        $this->assertSame($newTask->id, $pendingAction->payload['new_task_id']);

        $approver = $this->userWithPermission('approve task fee override');
        $approveResponse = $this->actingAs($approver)->postJson(route('tasks.pending-actions.approve', $pendingAction->id));

        $approveResponse->assertOk();
        $this->assertSame('reissued', $oldTask->fresh()->ticket_status, 'Approval must actually trigger the reissue posting.');

        $feeDoc = Transaction::withoutGlobalScopes()->where('idempotency_key', 'reissue:' . $oldTask->id . ':' . $newTask->id . ':fee')->first();
        $this->assertNotNull($feeDoc, 'The reissue fee DBN must have posted after approval.');
    }
}
