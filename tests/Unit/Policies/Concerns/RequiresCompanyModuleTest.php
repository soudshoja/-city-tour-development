<?php

namespace Tests\Unit\Policies\Concerns;

use App\Models\Account;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises RequiresCompanyModule::moduleEnabled() through a real gated
 * policy (AccountPolicy) rather than via reflection, since the trait is
 * protected-only and only ever meant to be called from inside a Policy.
 */
class RequiresCompanyModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp(); // runs PermissionSeeder (RefreshDatabase is in use)

        Company::forgetModuleCache();
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();

        parent::tearDown();
    }

    public function test_a_user_with_no_resolvable_company_is_denied_even_with_permission(): void
    {
        // Role::CLIENT has no case in getCompanyId() (app/Helper/helper.php)
        // and this user owns no company, branch, agent, or accountant
        // relation either -> getCompanyId() returns null -> moduleEnabled()
        // must fail CLOSED.
        $user = User::factory()->create(['role_id' => Role::CLIENT]);
        $user->givePermissionTo('view account');

        $this->assertFalse($user->can('viewAny', Account::class));
    }

    public function test_the_same_user_and_permission_succeed_once_a_company_resolves(): void
    {
        // Isolates the variable above: identical user/permission, but now
        // getCompanyId() resolves (via the Role::ADMIN session fallback)
        // to a real, unconfigured (legacy) company, so the module defaults
        // on and the underlying permission check decides the outcome.
        $user = User::factory()->create(['role_id' => Role::ADMIN]);
        $user->givePermissionTo('view account');

        $company = Company::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);
        session(['company_id' => $company->id]);

        $this->assertTrue($user->can('viewAny', Account::class));
    }
}
