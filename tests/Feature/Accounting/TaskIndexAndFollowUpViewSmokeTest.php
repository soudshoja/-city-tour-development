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
 * W6.U -- render-only smoke test for the Blade edits this sub-wave makes to
 * tasks/index.blade.php (ticket_status/client_status badges, Follow-up nav link, bulk-void
 * modal) and tasks/follow-up.blade.php. Blade::compileString() only proves the template compiles;
 * this proves it actually EXECUTES against real data without a runtime error (undefined
 * variable/property, etc.) -- no existing test in this codebase renders tasks.index at all.
 */
class TaskIndexAndFollowUpViewSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function userWithViewTaskPermission(): User
    {
        SpatieRole::firstOrCreate(['name' => 'w6u-smoke-role', 'guard_name' => 'web']);
        $role = SpatieRole::where('name', 'w6u-smoke-role')->first();
        $role->givePermissionTo('view task');
        // Also the Spatie 'admin' role -- TaskController::followUp()'s own agent-ownership
        // scoping (TaskPolicy::viewFollowUp()'s sibling logic) treats hasRole('admin') as "sees
        // every task", the same distinction TaskFollowUpTabHttpTest exercises directly; a plain
        // custom-permission role with no agent record would otherwise be scoped to agent_id=0.
        SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->assignRole(['w6u-smoke-role', 'admin']);

        return $user;
    }

    public function test_tasks_index_renders_with_ticket_and_client_status_badges(): void
    {
        Company::forgetModuleCache();
        $country = Country::factory()->create();
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $companyOwner->id, 'country_id' => $country->id]);
        \Database\Seeders\CoaSeeder::run($company->id);
        session(['company_id' => $company->id]);

        $supplier = Supplier::factory()->create();
        Task::factory()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'status' => 'void',
            'ticket_status' => 'void',
            'client_status' => 'credited',
        ]);

        $admin = $this->userWithViewTaskPermission();

        // index() self-redirects once to append its own '?invoiced=0&view_type=invoice' default
        // when that query param is absent (pre-existing behaviour, unrelated to this sub-wave) --
        // pass it directly rather than following the redirect.
        $response = $this->actingAs($admin)->get(route('tasks.index', ['invoiced' => 0, 'view_type' => 'invoice']));
        $response->assertOk();
        $response->assertSee('Follow-up');
        $response->assertSee('Bulk void');
    }

    public function test_follow_up_page_renders_with_a_real_row(): void
    {
        Company::forgetModuleCache();
        $country = Country::factory()->create();
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $companyOwner->id, 'country_id' => $country->id]);
        \Database\Seeders\CoaSeeder::run($company->id);
        session(['company_id' => $company->id]);

        $supplier = Supplier::factory()->create();
        Task::factory()->create([
            'company_id' => $company->id,
            'supplier_id' => $supplier->id,
            'status' => 'on hold',
            'deadline_at' => now()->addHours(5),
            'passenger_name' => 'Smoke Test Passenger',
        ]);

        $admin = $this->userWithViewTaskPermission();

        $response = $this->actingAs($admin)->get(route('tasks.follow-up'));

        $response->assertOk();
        $response->assertSee('Smoke Test Passenger');
    }
}
