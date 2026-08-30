<?php

namespace Tests\Feature\Suppliers;

use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierChargeRule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * W6.C.U "Supplier charge rule editor" (w6-brief.md "W6.U -- UI"). Add/edit/deactivate row
 * actions (gated by SupplierPolicy::update, deactivate = soft toggle never delete) and the
 * "test a task" preview (pick a task or enter supplier/service_type/channel manually -- computed
 * amounts, nothing posts).
 */
class SupplierChargeRuleUiTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create(['user_id' => $companyOwner->id, 'country_id' => $country->id]);
        $this->supplier = Supplier::factory()->create(['name' => 'W6U Charge Rule Supplier']);

        session(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function userWithPermission(): User
    {
        SpatieRole::firstOrCreate(['name' => 'w6u-chargerule-role', 'guard_name' => 'web']);
        $role = SpatieRole::where('name', 'w6u-chargerule-role')->first();
        $role->givePermissionTo('update supplier');

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->assignRole('w6u-chargerule-role');

        return $user;
    }

    public function test_add_row_returns_403_without_permission(): void
    {
        $user = User::factory()->create(['role_id' => Role::BRANCH]);

        $response = $this->actingAs($user)->post(route('suppliers.charge-rules.store', $this->supplier->id), [
            'charge_kind' => 'iata_fee', 'basis' => 'fixed', 'amount' => 1.5, 'recharge_policy' => 'absorb',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, SupplierChargeRule::count());
    }

    public function test_add_edit_deactivate_row(): void
    {
        $user = $this->userWithPermission();

        $storeResponse = $this->actingAs($user)->post(route('suppliers.charge-rules.store', $this->supplier->id), [
            'charge_kind' => 'iata_fee',
            'basis' => 'fixed',
            'amount' => 2.5,
            'recharge_policy' => 'recharge_client',
            'commissionable' => '1',
            'service_type' => 'flight',
        ]);
        $storeResponse->assertRedirect();

        $rule = SupplierChargeRule::where('supplier_id', $this->supplier->id)->first();
        $this->assertNotNull($rule);
        $this->assertEqualsWithDelta(2.5, (float) $rule->amount, 0.001);
        $this->assertTrue((bool) $rule->commissionable);
        $this->assertTrue((bool) $rule->active);

        $this->actingAs($user)->put(route('suppliers.charge-rules.update', $rule->id), [
            'charge_kind' => 'iata_fee', 'basis' => 'fixed', 'amount' => 4.0, 'recharge_policy' => 'absorb',
        ])->assertRedirect();
        $this->assertEqualsWithDelta(4.0, (float) $rule->fresh()->amount, 0.001);
        $this->assertSame('absorb', $rule->fresh()->recharge_policy);

        $this->actingAs($user)->post(route('suppliers.charge-rules.deactivate', $rule->id))->assertRedirect();
        $this->assertFalse((bool) $rule->fresh()->active, 'Deactivate must soft-toggle active, never delete.');
        $this->assertNotNull(SupplierChargeRule::find($rule->id));
    }

    public function test_test_a_task_preview_manual_input_computes_percent_of_fare(): void
    {
        $user = $this->userWithPermission();

        SupplierChargeRule::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'service_type' => 'flight',
            'charge_kind' => 'iata_fee',
            'basis' => 'percent_of_fare',
            'amount' => 10, // 10%
            'recharge_policy' => 'absorb',
            'active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('suppliers.charge-rules.test'), [
            'supplier_id' => $this->supplier->id,
            'service_type' => 'flight',
            'fare_amount' => 200,
            'total_amount' => 250,
        ]);

        $response->assertOk();
        $rules = $response->json('rules');
        $this->assertCount(1, $rules);
        $this->assertEqualsWithDelta(20.0, (float) $rules[0]['amount'], 0.001);
        $this->assertSame('iata_fee', $rules[0]['charge_kind']);
        $this->assertSame(0, Task::count(), 'The preview must never post anything or need a real task.');
    }

    public function test_test_a_task_preview_by_task_id_uses_the_tasks_own_fare(): void
    {
        $user = $this->userWithPermission();

        SupplierChargeRule::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'service_type' => 'flight',
            'charge_kind' => 'rounding',
            'basis' => 'fixed',
            'amount' => 0.5,
            'recharge_policy' => 'absorb',
            'active' => true,
        ]);

        $task = Task::factory()->create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'type' => 'flight',
            'price' => 300.0,
            'total' => 350.0,
        ]);

        $response = $this->actingAs($user)->postJson(route('suppliers.charge-rules.test'), [
            'task_id' => $task->id,
        ]);

        $response->assertOk();
        $rules = $response->json('rules');
        $this->assertCount(1, $rules);
        $this->assertEqualsWithDelta(0.5, (float) $rules[0]['amount'], 0.001);
    }

    public function test_company_defaults_page_and_create_default_route(): void
    {
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)->post(route('suppliers.charge-rules.create-default'), [
            'charge_kind' => 'service_fee', 'basis' => 'fixed', 'amount' => 3.0, 'recharge_policy' => 'absorb',
        ]);
        $response->assertRedirect();

        $rule = SupplierChargeRule::whereNull('supplier_id')->first();
        $this->assertNotNull($rule);
        $this->assertSame($this->company->id, $rule->company_id);

        $defaultsResponse = $this->actingAs($user)->get(route('suppliers.charge-rules.defaults'));
        $defaultsResponse->assertOk();
        $defaultsResponse->assertSee('service_fee');
    }
}
