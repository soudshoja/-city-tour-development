<?php

namespace Tests\Feature\CompanyProvisioning;

use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Path A (operator manual entry) — POST /users/companies (route name
 * companies.store) -> AdminUsersController::store() ->
 * CompanyController::store() -> CoaSeeder::run() -> [wired here, right
 * after CoaSeeder] ApplyCompanyModulePreset::apply().
 *
 * Complement to tests/Feature/CompanyProvisioning/CompanyProvisionerModulePresetTest.php
 * (Path B). Company::create() inside CompanyController::store() always
 * inserts a brand-new row — no firstOrCreate ambiguity, no repair variant
 * of this method exists — so this path has no "existing company" case to
 * guard against, unlike Path B.
 */
class AdminUsersControllerModulePresetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipPermissionSeeder = true;

        parent::setUp();

        Company::forgetModuleCache();
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();

        parent::tearDown();
    }

    public function test_admin_creating_a_company_applies_the_package_preset(): void
    {
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);

        // Merge fixture (T-1, MERGE-PLAN-DEV-INTO-LAUNCH-2026-09-04.md §2.5): under
        // ours' Gate-based guard, AdminUsersController::store() requires the
        // 'create company' permission, not just role_id === ADMIN. This test class
        // sets skipPermissionSeeder = true, so the permission rows are created here
        // directly rather than via PermissionSeeder.
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create company', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view company', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])
            ->givePermissionTo(['create company', 'view company']);
        $admin->assignRole('admin');

        // CompanyController::store() (called internally by AdminUsersController::store())
        // unconditionally calls $user->assignRole('company') on the new company owner —
        // same pre-existing Spatie role dependency AdminUsersControllerCoaTransactionTest's
        // setUp() already creates for the identical reason. Without it, store() throws
        // Spatie's RoleDoesNotExist, caught by AdminUsersController::store()'s try/catch,
        // and the company is silently never created.
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'company', 'guard_name' => 'web']);

        $country = Country::factory()->create();
        $code = 'ADMTEST-'.uniqid();

        $this->actingAs($admin)->post(route('companies.store'), [
            'name' => 'Admin-Created Co',
            'email' => 'admco-'.uniqid().'@example.test',
            'password' => 'password123',
            'phone' => '+96500000000',
            'code' => $code,
            'country_id' => $country->id,
            'address' => 'Somewhere',
            'status' => 1,
        ]);

        $company = Company::where('code', $code)->first();
        $this->assertNotNull($company, 'Expected the company to have been created.');

        foreach (config('modules.package_preset') as $module => $enabled) {
            $this->assertDatabaseHas('settings', [
                'company_id' => $company->id,
                'key' => Modules::settingKey($module),
                'type' => 'boolean',
            ]);
        }

        $this->assertTrue($company->hasModule(Modules::TASK_UPLOADER));
        $this->assertTrue($company->hasModule(Modules::PAYMENT_GATEWAY));
        $this->assertTrue($company->hasModule(Modules::CRM));
        $this->assertTrue($company->hasModule(Modules::AGENT_PROFIT));
        $this->assertTrue($company->hasModule(Modules::RESAYIL));
        $this->assertFalse($company->hasModule(Modules::ACCOUNTING));
    }

    public function test_non_admin_cannot_reach_the_route_and_no_company_is_created(): void
    {
        $agent = User::factory()->create(['role_id' => Role::AGENT]);
        $country = Country::factory()->create();
        $code = 'NOADM-'.uniqid();

        $response = $this->actingAs($agent)->post(route('companies.store'), [
            'name' => 'Should Not Exist',
            'email' => 'noadm-'.uniqid().'@example.test',
            'password' => 'password123',
            'code' => $code,
            'country_id' => $country->id,
            'status' => 1,
        ]);

        $response->assertForbidden();
        $this->assertNull(Company::where('code', $code)->first());
    }
}
