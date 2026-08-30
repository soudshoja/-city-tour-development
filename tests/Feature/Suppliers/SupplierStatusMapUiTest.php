<?php

namespace Tests\Feature\Suppliers;

use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierStatusMap;
use App\Models\Task;
use App\Models\TaskStatusEvent;
use App\Models\User;
use App\Services\TaskStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * W6.U "Supplier status map" (owner addition, 2026-08-28). "UI HTTP tests: SupplierPolicy::update-
 * gated add/edit/deactivate ... return 403 for a user without the ability; the 'test a raw status'
 * preview returns the correct canonical result and resolving row without writing a task; the
 * 'Unmapped statuses seen' one-click create-mapping flow creates the row and it is immediately
 * usable by mapStatus()."
 */
class SupplierStatusMapUiTest extends TestCase
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
        $this->supplier = Supplier::factory()->create(['name' => 'W6U Status Map Supplier']);

        session(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function userWithoutPermission(): User
    {
        return User::factory()->create(['role_id' => Role::ADMIN]);
    }

    private function userWithPermission(): User
    {
        SpatieRole::firstOrCreate(['name' => 'w6u-statusmap-role', 'guard_name' => 'web']);
        $role = SpatieRole::where('name', 'w6u-statusmap-role')->first();
        $role->givePermissionTo('update supplier');

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->assignRole('w6u-statusmap-role');

        return $user;
    }

    // ---------------------------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------------------------

    public function test_add_row_returns_403_without_permission(): void
    {
        $user = User::factory()->create(['role_id' => Role::BRANCH]);

        $response = $this->actingAs($user)->post(route('suppliers.status-map.store', $this->supplier->id), [
            'channel' => 'air',
            'raw_status' => 'OK',
            'canonical_status' => 'issued',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, SupplierStatusMap::count());
    }

    public function test_update_and_deactivate_return_403_without_permission(): void
    {
        $row = SupplierStatusMap::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'channel' => 'air',
            'raw_status' => 'OK',
            'canonical_status' => 'issued',
            'active' => true,
        ]);
        $user = User::factory()->create(['role_id' => Role::BRANCH]);

        $this->actingAs($user)->put(route('suppliers.status-map.update', $row->id), [
            'channel' => 'air', 'raw_status' => 'OK2', 'canonical_status' => 'confirmed',
        ])->assertForbidden();

        $this->actingAs($user)->post(route('suppliers.status-map.deactivate', $row->id))->assertForbidden();

        $this->assertSame('OK', $row->fresh()->raw_status);
        $this->assertTrue((bool) $row->fresh()->active);
    }

    // ---------------------------------------------------------------------------------------
    // Add / edit / deactivate
    // ---------------------------------------------------------------------------------------

    public function test_add_edit_deactivate_row(): void
    {
        $user = $this->userWithPermission();

        $storeResponse = $this->actingAs($user)->post(route('suppliers.status-map.store', $this->supplier->id), [
            'channel' => 'air',
            'raw_status' => 'OK',
            'canonical_status' => 'issued',
        ]);
        $storeResponse->assertRedirect();

        $row = SupplierStatusMap::where('supplier_id', $this->supplier->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('OK', $row->raw_status);
        $this->assertTrue((bool) $row->active);

        $this->actingAs($user)->put(route('suppliers.status-map.update', $row->id), [
            'channel' => 'air', 'raw_status' => 'OK', 'canonical_status' => 'confirmed',
        ])->assertRedirect();
        $this->assertSame('confirmed', $row->fresh()->canonical_status);

        $this->actingAs($user)->post(route('suppliers.status-map.deactivate', $row->id))->assertRedirect();
        $this->assertFalse((bool) $row->fresh()->active, 'Deactivate must soft-toggle active, never delete the row.');
        $this->assertNotNull(SupplierStatusMap::find($row->id), 'The row must still exist after deactivation.');
    }

    // ---------------------------------------------------------------------------------------
    // "Test a raw status" preview
    // ---------------------------------------------------------------------------------------

    public function test_test_a_raw_status_preview_resolves_correctly(): void
    {
        $user = $this->userWithPermission();

        SupplierStatusMap::create([
            'company_id' => $this->company->id,
            'supplier_id' => $this->supplier->id,
            'channel' => 'air',
            'raw_status' => 'CNF',
            'canonical_status' => 'confirmed',
            'active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('suppliers.status-map.test'), [
            'supplier_id' => $this->supplier->id,
            'channel' => 'air',
            'raw_status' => 'CNF',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'canonical_status' => 'confirmed']);
        $this->assertNotNull($response->json('matched_row_id'));
        $this->assertSame(0, Task::count(), 'The preview must never write a task.');
    }

    public function test_test_a_raw_status_preview_reports_needs_review_when_unmapped(): void
    {
        $user = $this->userWithPermission();

        $response = $this->actingAs($user)->postJson(route('suppliers.status-map.test'), [
            'supplier_id' => $this->supplier->id,
            'channel' => 'air',
            'raw_status' => 'TOTALLY_UNKNOWN_CODE',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'canonical_status' => 'needs_review']);
    }

    // ---------------------------------------------------------------------------------------
    // "Unmapped statuses seen" -> one-click create-mapping
    // ---------------------------------------------------------------------------------------

    public function test_create_from_unmapped_creates_a_row_immediately_usable_by_map_status(): void
    {
        $user = $this->userWithPermission();

        // Simulate the exact channel+raw_status an unmapped resolution already recorded (via
        // TaskStatusService::mapStatus() itself, the one real writer of this audit event).
        $service = new TaskStatusService();
        $mapped = $service->mapStatus($this->supplier, 'air', 'WEIRD_CODE', $this->company->id);
        $this->assertSame('needs_review', $mapped->canonicalStatus);
        $this->assertNotNull(TaskStatusEvent::where('event', 'status_unmapped')->where('raw_status', 'WEIRD_CODE')->first());

        $response = $this->actingAs($user)->post(route('suppliers.status-map.create-from-unmapped'), [
            'supplier_id' => $this->supplier->id,
            'channel' => 'air',
            'raw_status' => 'WEIRD_CODE',
            'canonical_status' => 'confirmed',
        ]);
        $response->assertRedirect();

        $row = SupplierStatusMap::where('raw_status', 'WEIRD_CODE')->first();
        $this->assertNotNull($row);
        $this->assertSame('confirmed', $row->canonical_status);

        // Re-processing (re-calling mapStatus()) must now resolve via the new row -- no separate
        // re-processing pipeline needed.
        $reMapped = $service->mapStatus($this->supplier, 'air', 'WEIRD_CODE', $this->company->id);
        $this->assertSame('confirmed', $reMapped->canonicalStatus);
    }

    public function test_company_defaults_page_is_gated_and_shows_channel_wide_rows(): void
    {
        $user = User::factory()->create(['role_id' => Role::BRANCH]);
        $this->actingAs($user)->get(route('suppliers.status-map.defaults'))->assertForbidden();

        $allowedUser = $this->userWithPermission();

        SupplierStatusMap::create([
            'company_id' => $this->company->id,
            'supplier_id' => null,
            'channel' => 'air',
            'raw_status' => 'DEFAULT_CODE',
            'canonical_status' => 'issued',
            'active' => true,
        ]);

        $response = $this->actingAs($allowedUser)->get(route('suppliers.status-map.defaults'));
        $response->assertOk();
        $response->assertSee('DEFAULT_CODE');
    }
}
