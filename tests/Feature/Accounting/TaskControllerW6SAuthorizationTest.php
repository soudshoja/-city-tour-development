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
 * W6.S item (3) (w6-brief.md "Consolidation + fixes" -- "Gate::authorize in every mutating
 * action"). Pins that TaskController::update()/toggleStatus()/bulkUpdate()/updateMulti()/
 * switchInvoiceTask() actually call Gate::authorize() at the HTTP layer -- ct-void-map.md §6/§7
 * bug 2 ("update, updateMulti, bulkUpdate, toggleStatus, voidTask, switchInvoiceTask -- zero
 * authorization"). A user with none of the new 'update task'/'switch invoice task' Spatie
 * permissions must get 403 on every one of these routes; a user with the permission must NOT be
 * blocked by authorization (whatever else that route's own validation/business logic then does).
 */
class TaskControllerW6SAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Task $task;

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

        // Role::ADMIN's getCompanyId() reads session('company_id'), independent of any
        // company/branch/agent relation on the user -- the simplest way to give an
        // arbitrary test user a resolvable company (and therefore a passing module gate)
        // without needing each of them to literally own $this->company.
        session(['company_id' => $this->company->id]);

        $supplier = Supplier::factory()->create();

        $this->task = Task::factory()->create([
            'company_id' => $this->company->id,
            'supplier_id' => $supplier->id,
            'status' => 'issued',
        ]);
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
        SpatieRole::firstOrCreate(['name' => 'w6s-test-role', 'guard_name' => 'web']);
        $role = SpatieRole::where('name', 'w6s-test-role')->first();
        $role->givePermissionTo($permission);

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->assignRole('w6s-test-role');

        return $user;
    }

    public function test_update_route_returns_403_without_update_task_permission(): void
    {
        $user = $this->userWithNoPermission();

        $response = $this->actingAs($user)->put(route('tasks.update', $this->task->id), []);

        $response->assertForbidden();
    }

    public function test_update_route_is_not_blocked_by_authorization_with_permission(): void
    {
        $user = $this->userWithPermission('update task');

        $response = $this->actingAs($user)->put(route('tasks.update', $this->task->id), []);

        $response->assertStatus(302); // update() redirects back either way; never 403.
    }

    public function test_toggle_status_route_returns_403_without_permission(): void
    {
        $user = $this->userWithNoPermission();

        $response = $this->actingAs($user)->post(route('tasks.toggleStatus', $this->task->id), [
            'is_enabled' => false,
        ]);

        $response->assertForbidden();
    }

    public function test_toggle_status_route_is_not_blocked_by_authorization_with_permission(): void
    {
        $user = $this->userWithPermission('update task');

        $response = $this->actingAs($user)->post(route('tasks.toggleStatus', $this->task->id), [
            'is_enabled' => false,
        ]);

        $this->assertNotEquals(403, $response->getStatusCode());
    }

    public function test_bulk_update_route_returns_403_without_permission(): void
    {
        $user = $this->userWithNoPermission();

        $response = $this->actingAs($user)->post(route('tasks.bulkUpdate'), [
            'task_ids' => [$this->task->id],
        ]);

        $response->assertForbidden();
    }

    public function test_update_multi_route_returns_403_without_permission(): void
    {
        $user = $this->userWithNoPermission();

        $response = $this->actingAs($user)->put(route('tasks.updateMulti'), [
            'drafts' => json_encode([]),
        ]);

        $response->assertForbidden();
    }

    public function test_switch_invoice_route_returns_403_without_switch_invoice_task_permission(): void
    {
        $user = $this->userWithNoPermission();

        $response = $this->actingAs($user)->post(route('tasks.switchInvoice', $this->task->id), []);

        $response->assertForbidden();
    }

    public function test_admin_role_bypasses_every_new_ability(): void
    {
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $this->assertTrue($admin->can('update', $this->task));
        $this->assertTrue($admin->can('void', $this->task));
        $this->assertTrue($admin->can('reissue', $this->task));
        $this->assertTrue($admin->can('bulkVoid', Task::class));
        $this->assertTrue($admin->can('switchInvoice', $this->task));
    }

    public function test_bare_company_owner_with_no_permission_is_denied_every_new_ability(): void
    {
        $user = $this->userWithNoPermission();

        $this->assertFalse($user->can('update', $this->task));
        $this->assertFalse($user->can('void', $this->task));
        $this->assertFalse($user->can('reissue', $this->task));
        $this->assertFalse($user->can('bulkVoid', Task::class));
        $this->assertFalse($user->can('switchInvoice', $this->task));
    }
}
