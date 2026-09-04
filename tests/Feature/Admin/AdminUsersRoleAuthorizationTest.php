<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fix 4 (2026-08-25 pre-pilot defect list, spec correction PKG-1):
 * routes/web.php's `/users` route group had its `role:admin` middleware
 * commented out. Investigation (see the comment now above that route
 * group) found:
 *
 *  - Most routes in the group were ALREADY correctly gated inline
 *    (companies.store, companies.index, companiesnew.new,
 *    users.set-company -> role_id === ADMIN; users.edit -> role_id in
 *    [ADMIN, COMPANY]; company-invites.* -> authorizeAdmin()).
 *  - TWO routes had NO authorization check at all: `users.role`
 *    (storeRole — arbitrary role assignment to any user in any company)
 *    and `users.updateInfo` (updateInfo — rename/re-email/reset password
 *    for any other user). Both are fixed here with the SAME
 *    [ADMIN, COMPANY] gate their shared page (users/edit.blade.php,
 *    itself gated the same way) already implies.
 *  - Restoring the literal commented-out `role:admin` (Spatie
 *    RoleMiddleware, checking $user->hasRole('admin')) was deliberately
 *    NOT done: a live DB check found a real admin (role_id === ADMIN)
 *    with no Spatie 'admin' role assigned, who would have been locked
 *    out — worse than the prior state, and exactly the trap this fix was
 *    briefed to avoid.
 *
 * This test proves both directions on the two newly-gated routes, plus a
 * companion check that the COMPANY role (deliberately still allowed,
 * matching the page's existing access level) is not regressed.
 *
 * Dispatches real PUT requests through the full HTTP kernel
 * (TestCase::put() -> Illuminate\Contracts\Http\Kernel::handle()) as real
 * authenticated users (actingAs() sets the user on the `web` guard).
 */
class AdminUsersRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->skipPermissionSeeder = true;

        parent::setUp();
    }

    private function makeTarget(Company $company): User
    {
        $target = User::factory()->create(['role_id' => Role::AGENT]);
        \App\Models\Branch::factory()->create(['company_id' => $company->id, 'user_id' => $target->id]);

        return $target->fresh();
    }

    // --- users.role (storeRole) ---------------------------------------

    public function test_admin_can_reach_users_role_route(): void
    {
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $company = Company::factory()->create(['user_id' => $admin->id]);
        $target = $this->makeTarget($company);
        $roleRow = Role::create(['name' => 'agent', 'guard_name' => 'web', 'company_id' => $company->id]);

        $response = $this->actingAs($admin)->put(route('users.role'), [
            'user_id' => $target->id,
            'company_id' => $company->id,
            'role_id' => $roleRow->id,
        ]);

        $response->assertStatus(302); // redirect on success, NOT 403
        $response->assertSessionHas('success');
    }

    public function test_company_owner_can_still_reach_users_role_route(): void
    {
        // Regression guard: this route's ONLY UI entry point
        // (users/edit.blade.php) is gated to [ADMIN, COMPANY], and a
        // company owner currently uses this "Assign Role" form. Proves
        // the fix did not newly lock this out.
        $owner = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $target = $this->makeTarget($company);
        $roleRow = Role::create(['name' => 'agent', 'guard_name' => 'web', 'company_id' => $company->id]);

        $response = $this->actingAs($owner)->put(route('users.role'), [
            'user_id' => $target->id,
            'company_id' => $company->id,
            'role_id' => $roleRow->id,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');
    }

    public function test_agent_is_blocked_from_users_role_route(): void
    {
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $companyOwner->id]);
        $agentCaller = User::factory()->create(['role_id' => Role::AGENT]);
        \App\Models\Branch::factory()->create(['company_id' => $company->id, 'user_id' => $agentCaller->id]);
        $target = $this->makeTarget($company);
        $roleRow = Role::create(['name' => 'admin', 'guard_name' => 'web', 'company_id' => $company->id]);

        $response = $this->actingAs($agentCaller)->put(route('users.role'), [
            'user_id' => $target->id,
            'company_id' => $company->id,
            // The attack this closes: an unprivileged agent trying to grant
            // themselves (or anyone) an admin-named role.
            'role_id' => $roleRow->id,
        ]);

        $response->assertForbidden();
        $this->assertFalse($target->fresh()->hasRole($roleRow), 'An agent must not be able to assign roles.');
    }

    // --- users.updateInfo (updateInfo) ---------------------------------

    public function test_admin_can_reach_users_update_info_route(): void
    {
        $admin = User::factory()->create(['role_id' => Role::ADMIN]);
        $company = Company::factory()->create(['user_id' => $admin->id]);
        $target = $this->makeTarget($company);

        $response = $this->actingAs($admin)->put(route('users.updateInfo', $target), [
            'name' => 'Renamed By Admin',
            'email' => $target->email,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertSame('Renamed By Admin', $target->fresh()->name);
    }

    public function test_company_owner_can_still_reach_users_update_info_route(): void
    {
        $owner = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $owner->id]);
        $target = $this->makeTarget($company);

        $response = $this->actingAs($owner)->put(route('users.updateInfo', $target), [
            'name' => 'Renamed By Owner',
            'email' => $target->email,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');
        $this->assertSame('Renamed By Owner', $target->fresh()->name);
    }

    public function test_agent_is_blocked_from_users_update_info_route(): void
    {
        $companyOwner = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $companyOwner->id]);
        $agentCaller = User::factory()->create(['role_id' => Role::AGENT]);
        \App\Models\Branch::factory()->create(['company_id' => $company->id, 'user_id' => $agentCaller->id]);
        $target = $this->makeTarget($company);
        $originalName = $target->name;
        $originalPasswordHash = $target->password;

        $response = $this->actingAs($agentCaller)->put(route('users.updateInfo', $target), [
            'name' => 'Hijacked Name',
            'email' => $target->email,
            'info-new-password' => 'new-password-123',
            'info-new-password_confirmation' => 'new-password-123',
        ]);

        $response->assertForbidden();
        $target->refresh();
        $this->assertSame($originalName, $target->name, 'An agent must not be able to rename another user.');
        $this->assertSame($originalPasswordHash, $target->password, 'An agent must not be able to reset another user\'s password.');
    }
}
