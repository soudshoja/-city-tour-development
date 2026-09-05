<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\Feature\Accounting\Concerns\GrantsAccountingModule;
use Tests\TestCase;

/**
 * W6.U "Settings addition" + verify criterion 1 (w6-brief.md "W6.U -- UI"):
 * "Submitting the bulk_void_mode setting persists it, and the bulk-void route honours it (feature
 * test: set per_task_report, submit a batch with one failing task, assert partial success +
 * result list; set atomic, same batch, assert full rollback -- zero tasks voided)."
 *
 * Uses `SettingController::storeAccountingSettings()`/`getAccountingSettings()` (already wired by
 * W6.S to read/write `accounting.bulk_void_mode`) as the persistence layer under test, then
 * confirms `TaskController::bulkVoid()` -> `TaskStatusService::bulkVoidMode()` reads back exactly
 * what was persisted when the request omits its own explicit `bulk_void_mode` override.
 */
class TaskBulkVoidModeSettingUITest extends TestCase
{
    use RefreshDatabase;
    use GrantsAccountingModule;

    private Company $company;
    private User $companyOwner;

    protected function setUp(): void
    {
        parent::setUp();
        Company::forgetModuleCache();

        $country = Country::factory()->create();
        $this->companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $this->company = Company::factory()->create([
            'user_id' => $this->companyOwner->id,
            'country_id' => $country->id,
        ]);
        $this->grantAccountingModule($this->company);

        session(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();
        parent::tearDown();
    }

    private function userWithPermission(string $permission): User
    {
        SpatieRole::firstOrCreate(['name' => 'w6u-bv-role-' . $permission, 'guard_name' => 'web']);
        $role = SpatieRole::where('name', 'w6u-bv-role-' . $permission)->first();
        $role->givePermissionTo($permission);

        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->assignRole('w6u-bv-role-' . $permission);

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

    public function test_submitting_bulk_void_mode_setting_persists_it(): void
    {
        // SettingPolicy::manageAccountingSettings() short-circuits true for role_id===ADMIN --
        // same convention SettingControllerAccountingSettingsTest (W4.U) already uses, no Spatie
        // permission needed.
        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        Setting::query()->where('company_id', $this->company->id)->delete();
        $this->grantAccountingModule($this->company);

        $response = $this->actingAs($user)->postJson(route('settings.accounting-settings.store'), [
            'invoice_overpay_cancel_policy' => 'credit',
            'unclaimed_writeback_months' => 12,
            'refund_send_on_post' => true,
            'agent_unearn_notice' => true,
            'bulk_void_mode' => 'per_task_report',
        ] + ['company_id' => $this->company->id]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertSame(
            'per_task_report',
            Setting::where('company_id', $this->company->id)->where('key', 'accounting.bulk_void_mode')->value('value')
        );

        $getResponse = $this->actingAs($user)->getJson(route('settings.accounting-settings') . '?company_id=' . $this->company->id);
        $getResponse->assertOk();
        $this->assertSame('per_task_report', $getResponse->json('settings.bulk_void_mode'));
    }

    public function test_bulk_void_route_honours_the_persisted_per_task_report_default(): void
    {
        Setting::updateOrCreate(
            ['key' => 'accounting.bulk_void_mode', 'company_id' => $this->company->id],
            ['value' => 'per_task_report', 'type' => 'string']
        );

        $taskGood = $this->issuedTask();
        $taskBad = $this->issuedTask(['status' => 'on hold', 'ticket_status' => null]);

        $voidUser = $this->userWithPermission('bulk void task');

        // No explicit bulk_void_mode in the request -- the route must fall back to the setting
        // just persisted above.
        $response = $this->actingAs($voidUser)->postJson(route('tasks.bulk-void'), [
            'task_ids' => [$taskGood->id, $taskBad->id],
        ]);

        $response->assertOk();
        $json = $response->json();

        $this->assertSame('per_task_report', $json['mode']);
        $this->assertSame(1, $json['voided_count']);
        $this->assertSame(1, $json['failed_count']);
        $this->assertCount(2, $json['results']);
        $this->assertSame('void', $taskGood->fresh()->status);
        $this->assertSame('on hold', $taskBad->fresh()->status);
    }

    public function test_bulk_void_route_honours_the_persisted_atomic_default(): void
    {
        Setting::updateOrCreate(
            ['key' => 'accounting.bulk_void_mode', 'company_id' => $this->company->id],
            ['value' => 'atomic', 'type' => 'string']
        );

        $taskGood = $this->issuedTask();
        $taskBad = $this->issuedTask(['status' => 'on hold', 'ticket_status' => null]);

        $voidUser = $this->userWithPermission('bulk void task');

        $response = $this->actingAs($voidUser)->postJson(route('tasks.bulk-void'), [
            'task_ids' => [$taskGood->id, $taskBad->id],
        ]);

        $response->assertStatus(422);
        $json = $response->json();

        $this->assertFalse($json['success']);
        $this->assertSame(0, $json['voided_count']);
        $this->assertNotSame('void', $taskGood->fresh()->status, 'atomic mode (from the persisted setting) must roll back the whole batch.');
        $this->assertSame('on hold', $taskBad->fresh()->status);
    }
}
