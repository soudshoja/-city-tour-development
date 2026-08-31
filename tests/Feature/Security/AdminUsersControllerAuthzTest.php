<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Dev-branch hardening (account takeover). Before this fix, AdminUsersController::updateInfo()
 * and ::storeRole() had `auth` middleware but NO role/ownership check at all: any authenticated
 * user of any role could reset any other user's password (updateInfo) or reassign any other
 * user's role, including promoting to Admin (storeRole). Fixed by gating both with the same
 * Role::ADMIN/Role::COMPANY check their sibling GET, editRole() -- which renders the very form
 * that posts to both -- already used (see AdminUsersController's own docblocks). Also covers the
 * four other ungated methods found while auditing this controller: newCompany(), create(),
 * store() and ShowCompanies(), each gated with the matching pre-existing Policy ability
 * (UserPolicy::create / CompanyPolicy::create / CompanyPolicy::viewAny).
 */
class AdminUsersControllerAuthzTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // PermissionSeeder is auto-run by Tests\TestCase for RefreshDatabase tests; the spatie
        // roles themselves are not, so create the ones these tests need directly (same pattern as
        // tests/Feature/TaskTest.php's setUp()).
        SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => 'company', 'guard_name' => 'web']);
        SpatieRole::firstOrCreate(['name' => 'agent', 'guard_name' => 'web']);
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->assignRole('admin');

        return $user;
    }

    private function makeCompanyOwner(): array
    {
        $owner = User::factory()->create(['role_id' => Role::COMPANY]);
        $owner->assignRole('company');
        $country = Country::factory()->create();
        $company = Company::factory()->create(['user_id' => $owner->id, 'country_id' => $country->id]);

        return [$owner, $company];
    }

    private function makeUnprivilegedUser(): User
    {
        // Role::AGENT with no spatie permissions granted at all -- the "any authenticated user"
        // attacker in the vulnerability report.
        $user = User::factory()->create(['role_id' => Role::AGENT]);
        $user->assignRole('agent');

        return $user;
    }

    // --- storeRole() -----------------------------------------------------------------------

    public function test_non_admin_cannot_change_another_users_role(): void
    {
        $attacker = $this->makeUnprivilegedUser();
        [$victim] = $this->makeCompanyOwner();
        $originalRoleId = $victim->role_id;

        $response = $this->actingAs($attacker)->put(route('users.role'), [
            'role_id' => $victim->role_id,
            'user_id' => $victim->id,
            'company_id' => Company::first()->id,
        ]);

        $response->assertForbidden();
        $this->assertSame($originalRoleId, $victim->fresh()->role_id);
    }

    public function test_non_admin_cannot_self_promote_to_admin_via_store_role(): void
    {
        $attacker = $this->makeUnprivilegedUser();
        [, $company] = $this->makeCompanyOwner();

        $response = $this->actingAs($attacker)->put(route('users.role'), [
            'role_id' => Role::ADMIN,
            'user_id' => $attacker->id,
            'company_id' => $company->id,
        ]);

        $response->assertForbidden();
    }

    public function test_company_role_user_cannot_modify_an_admin_users_role(): void
    {
        $admin = $this->makeAdmin();
        [$companyActor, $company] = $this->makeCompanyOwner();

        // A real, existing spatie roles.id (required by storeRole()'s own
        // 'role_id' => 'exists:roles,id' validation) -- which role doesn't matter, since the
        // check under test looks at the TARGET user's Role::ADMIN *tier* (users.role_id), not
        // which spatie role is being assigned.
        $companyScopedRole = SpatieRole::create([
            'name' => 'company-scoped-' . $company->id,
            'guard_name' => 'web',
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($companyActor)->put(route('users.role'), [
            'role_id' => $companyScopedRole->id,
            'user_id' => $admin->id,
            'company_id' => $company->id,
        ]);

        $response->assertForbidden();
        $this->assertSame(Role::ADMIN, $admin->fresh()->role_id);
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $admin = $this->makeAdmin();
        [$targetUser, $company] = $this->makeCompanyOwner();

        $companyScopedRole = SpatieRole::create([
            'name' => 'company-scoped-' . $company->id,
            'guard_name' => 'web',
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($admin)->put(route('users.role'), [
            'role_id' => $companyScopedRole->id,
            'user_id' => $targetUser->id,
            'company_id' => $company->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertTrue($targetUser->fresh()->hasRole($companyScopedRole));
    }

    // --- updateInfo() ----------------------------------------------------------------------

    public function test_non_admin_cannot_reset_another_users_password(): void
    {
        $attacker = $this->makeUnprivilegedUser();
        [$victim] = $this->makeCompanyOwner();
        $originalHash = $victim->password;

        $response = $this->actingAs($attacker)->put(route('users.updateInfo', $victim), [
            'name' => $victim->name,
            'email' => $victim->email,
            'info-new-password' => 'AttackerChosenPassw0rd!',
            'info-new-password_confirmation' => 'AttackerChosenPassw0rd!',
        ]);

        $response->assertForbidden();
        $this->assertSame($originalHash, $victim->fresh()->password);
    }

    public function test_company_role_user_cannot_modify_an_admin_users_info(): void
    {
        $admin = $this->makeAdmin();
        [$companyActor] = $this->makeCompanyOwner();
        $originalHash = $admin->password;

        $response = $this->actingAs($companyActor)->put(route('users.updateInfo', $admin), [
            'name' => 'Renamed Admin',
            'email' => $admin->email,
            'info-new-password' => 'CompanyChosenPassw0rd!',
            'info-new-password_confirmation' => 'CompanyChosenPassw0rd!',
        ]);

        $response->assertForbidden();
        $this->assertSame($originalHash, $admin->fresh()->password);
    }

    public function test_admin_can_reset_a_users_password(): void
    {
        $admin = $this->makeAdmin();
        [$targetUser] = $this->makeCompanyOwner();

        $response = $this->actingAs($admin)->put(route('users.updateInfo', $targetUser), [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'info-new-password' => 'LegitimateNewPassw0rd!',
            'info-new-password_confirmation' => 'LegitimateNewPassw0rd!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertTrue(Hash::check('LegitimateNewPassw0rd!', $targetUser->fresh()->password));
    }

    // --- other gaps found in the same controller: newCompany() / create() / store() /
    //     ShowCompanies() -----------------------------------------------------------------

    public function test_unprivileged_user_cannot_view_new_company_form(): void
    {
        $user = $this->makeUnprivilegedUser();

        $this->actingAs($user)->get(route('companiesnew.new'))->assertForbidden();
    }

    public function test_unprivileged_user_cannot_view_add_user_console(): void
    {
        $user = $this->makeUnprivilegedUser();

        $this->actingAs($user)->get(route('users.create'))->assertForbidden();
    }

    public function test_unprivileged_user_cannot_list_all_companies(): void
    {
        $user = $this->makeUnprivilegedUser();
        [, $company] = $this->makeCompanyOwner();

        $this->actingAs($user)->get(route('companies.index'))->assertForbidden();
    }

    public function test_unprivileged_user_cannot_create_a_company(): void
    {
        $user = $this->makeUnprivilegedUser();
        $country = Country::factory()->create();

        $response = $this->actingAs($user)->post(route('companies.store'), [
            'name' => 'Rogue Company',
            'email' => 'rogue@example.test',
            'password' => 'Password123!',
            'phone' => '1234567',
            'code' => 'ROGUE-1',
            'country_id' => $country->id,
            'address' => '123 Nowhere',
            'status' => 1,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('companies', ['code' => 'ROGUE-1']);
    }

    public function test_admin_can_create_a_company(): void
    {
        $admin = $this->makeAdmin();
        SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])
            ->givePermissionTo(['create company', 'view company']);
        $country = Country::factory()->create();

        $response = $this->actingAs($admin)->post(route('companies.store'), [
            'name' => 'Legit Company',
            'email' => 'legit@example.test',
            'password' => 'Password123!',
            'phone' => '1234567',
            'code' => 'LEGIT-1',
            'country_id' => $country->id,
            'address' => '123 Somewhere',
            'status' => 1,
        ]);

        $response->assertRedirect(route('companies.index'));
        $this->assertDatabaseHas('companies', ['code' => 'LEGIT-1']);
    }

    public function test_admin_can_list_all_companies(): void
    {
        $admin = $this->makeAdmin();
        SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])
            ->givePermissionTo(['view company']);
        [, $company] = $this->makeCompanyOwner();

        // App\View\Components\AppLayout::render() -- rendered by the view this route returns --
        // resolves the sidebar's "current company" for an Admin via getCompanyId(), which
        // defaults to session('company_id', 1) when unset; a fresh RefreshDatabase test has no
        // guarantee any company has id 1. Pre-existing, unrelated to this authorization fix
        // (every Admin-rendered full-page view has the same dependency) -- set explicitly here,
        // same as tests/Feature/TaskTest.php's setUp() does for its own company-owning user.
        session(['company_id' => $company->id]);

        $this->actingAs($admin)->get(route('companies.index'))->assertOk();
    }
}
