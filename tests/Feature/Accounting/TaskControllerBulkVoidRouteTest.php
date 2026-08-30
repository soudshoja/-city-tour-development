<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * W6.B (w6-brief.md "## Kinds" 5 -- "BULK VOID"). HTTP-layer coverage for
 * `POST /tasks/bulk-void` -> `TaskController::bulkVoid()` -> `TaskPolicy::bulkVoid()` ->
 * `TaskStatusService::bulkVoid()`. Uses tasks with no engine-posted sale document (status=
 * 'issued' but never routed through issue()) -- {@see \App\Services\TaskStatusService::void()}'s
 * own precondition/normalization logic still exercises for real (ticket_status normalizes from
 * the legacy status column, the reversal/fee/commission/disposition steps are all real no-ops
 * when there is nothing to reverse) -- the deeper, real-ledger-reversal cases are covered by
 * {@see TaskStatusServiceBulkVoidTest}, which enables the full posting engine.
 *
 * Also covers the sub-wave's `updateMulti()` fix: a failing task in a multi-update batch no
 * longer reports success (ct-void-map.md §5 bug).
 */
class TaskControllerBulkVoidRouteTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

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

        session(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();

        parent::tearDown();
    }

    private function userWithNoPermission(): User
    {
        return User::factory()->create(['role_id' => Role::ADMIN]);
    }

    private function userWithPermission(string $permission): User
    {
        SpatieRole::firstOrCreate(['name' => 'w6b-test-role', 'guard_name' => 'web']);
        $role = SpatieRole::where('name', 'w6b-test-role')->first();
        $role->givePermissionTo($permission);

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->assignRole('w6b-test-role');

        return $user;
    }

    private function issuedTask(array $overrides = []): Task
    {
        $supplier = Supplier::factory()->create();

        return Task::factory()->create(array_merge([
            'company_id' => $this->company->id,
            'supplier_id' => $supplier->id,
            'status' => 'issued',
        ], $overrides));
    }

    // ---------------------------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------------------------

    public function test_bulk_void_route_returns_403_without_permission(): void
    {
        $task = $this->issuedTask();
        $user = $this->userWithNoPermission();

        $response = $this->actingAs($user)->post(route('tasks.bulk-void'), [
            'task_ids' => [$task->id],
        ]);

        $response->assertForbidden();

        $this->assertNotSame('void', $task->fresh()->status, 'A forbidden request must never mutate anything.');
    }

    // ---------------------------------------------------------------------------------------
    // Happy paths
    // ---------------------------------------------------------------------------------------

    public function test_bulk_void_route_atomic_mode_voids_every_task(): void
    {
        $taskA = $this->issuedTask();
        $taskB = $this->issuedTask();
        $user = $this->userWithPermission('bulk void task');

        $response = $this->actingAs($user)->post(route('tasks.bulk-void'), [
            'task_ids' => [$taskA->id, $taskB->id],
            'bulk_void_mode' => 'atomic',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'mode' => 'atomic',
            'voided_count' => 2,
            'failed_count' => 0,
        ]);

        $this->assertSame('void', $taskA->fresh()->status);
        $this->assertSame('void', $taskB->fresh()->status);
    }

    public function test_bulk_void_route_atomic_mode_with_a_bad_task_rolls_back_the_whole_batch(): void
    {
        $taskGood = $this->issuedTask();
        // Never issued -- fails void()'s own ticket_status precondition.
        $taskBad = $this->issuedTask(['status' => 'on hold', 'ticket_status' => null]);
        $user = $this->userWithPermission('bulk void task');

        $response = $this->actingAs($user)->post(route('tasks.bulk-void'), [
            'task_ids' => [$taskGood->id, $taskBad->id],
            'bulk_void_mode' => 'atomic',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'voided_count' => 0,
        ]);

        $this->assertNotSame('void', $taskGood->fresh()->status, 'atomic mode must roll back a task that would otherwise have succeeded.');
        $this->assertSame('on hold', $taskBad->fresh()->status);
    }

    public function test_bulk_void_route_per_task_report_mode_reports_partial_success(): void
    {
        $taskGood1 = $this->issuedTask();
        $taskBad = $this->issuedTask(['status' => 'on hold', 'ticket_status' => null]);
        $taskGood2 = $this->issuedTask();
        $user = $this->userWithPermission('bulk void task');

        $response = $this->actingAs($user)->post(route('tasks.bulk-void'), [
            'task_ids' => [$taskGood1->id, $taskBad->id, $taskGood2->id],
            'bulk_void_mode' => 'per_task_report',
        ]);

        $response->assertOk();
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertSame('per_task_report', $json['mode']);
        $this->assertSame(2, $json['voided_count']);
        $this->assertSame(1, $json['failed_count']);
        $this->assertCount(3, $json['results']);

        $byId = collect($json['results'])->keyBy('task_id');
        $this->assertTrue($byId[$taskGood1->id]['success']);
        $this->assertFalse($byId[$taskBad->id]['success']);
        $this->assertNotNull($byId[$taskBad->id]['error']);
        $this->assertTrue($byId[$taskGood2->id]['success']);

        $this->assertSame('void', $taskGood1->fresh()->status);
        $this->assertSame('void', $taskGood2->fresh()->status);
        $this->assertSame('on hold', $taskBad->fresh()->status);
    }

    // ---------------------------------------------------------------------------------------
    // updateMulti() -- no more success count on failure (ct-void-map.md §5 bug)
    // ---------------------------------------------------------------------------------------

    public function test_update_multi_no_longer_reports_success_when_one_task_in_the_batch_fails(): void
    {
        $taskA = $this->issuedTask([
            'total' => 100.0,
            'supplier_pay_date' => now()->subDay()->toDateString(),
        ]);
        $taskB = $this->issuedTask([
            'total' => 200.0,
            'supplier_pay_date' => now()->subDay()->toDateString(),
        ]);
        $user = $this->userWithPermission('update task');

        $drafts = [
            (string) $taskA->id => [
                'status' => $taskA->status,
                'supplier_id' => $taskA->supplier_id,
                'total' => 999.99,
                'supplier_pay_date' => $taskA->supplier_pay_date,
            ],
            (string) $taskB->id => [
                'status' => $taskB->status,
                'supplier_id' => $taskB->supplier_id,
                'total' => 888.88,
                'supplier_pay_date' => $taskB->supplier_pay_date,
                // Nonexistent client -- fails getValidationRules()'s 'exists:clients,id' rule,
                // thrown by applyTaskUpdate()'s own $request->validate() call.
                'client_id' => 999999999,
            ],
        ];

        $response = $this->actingAs($user)->put(route('tasks.updateMulti'), [
            'drafts' => json_encode($drafts),
        ]);

        $response->assertSessionHasNoErrors(); // not a validation-redirect, a caught-then-flashed error
        $response->assertSessionHas('error');
        $response->assertSessionMissing('success');

        // The real proof: taskA's change (which would have succeeded on its own) was rolled back
        // along with taskB's failure -- the whole batch shares ONE outer transaction.
        $this->assertEqualsWithDelta(100.0, (float) $taskA->fresh()->total, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $taskB->fresh()->total, 0.001);
    }

    public function test_update_multi_still_succeeds_when_every_task_in_the_batch_is_valid(): void
    {
        $taskA = $this->issuedTask([
            'total' => 100.0,
            'supplier_pay_date' => now()->subDay()->toDateString(),
        ]);
        $user = $this->userWithPermission('update task');

        $drafts = [
            (string) $taskA->id => [
                'status' => $taskA->status,
                'supplier_id' => $taskA->supplier_id,
                'total' => 999.99,
                'supplier_pay_date' => $taskA->supplier_pay_date,
            ],
        ];

        $response = $this->actingAs($user)->put(route('tasks.updateMulti'), [
            'drafts' => json_encode($drafts),
        ]);

        $response->assertSessionHas('success');
        $this->assertEqualsWithDelta(999.99, (float) $taskA->fresh()->total, 0.001);
    }
}
