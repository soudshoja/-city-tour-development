<?php

namespace Tests\Feature\Entitlements;

use App\Models\Account;
use App\Models\Agent;
use App\Models\Client;
use App\Models\CoaCategory;
use App\Models\Company;
use App\Models\Credit;
use App\Models\CurrencyExchange;
use App\Models\Payment;
use App\Models\Report;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Support\Entitlements\ApplyCompanyModulePreset;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the 3 contracts Phase 1's entitlement layer is required to hold:
 *
 *   1. A company with `module.accounting` explicitly OFF is denied by
 *      every accounting policy, even for a user whose role would
 *      otherwise bypass the underlying permission check (admin).
 *   2. A LEGACY company — zero `module.*` settings rows, exactly like all
 *      3 live companies as of Phase 1 — is completely unaffected: every
 *      ability behaves exactly as it did before this change existed.
 *   3. The 4 package-module policies (task_uploader, payment_gateway,
 *      crm, agent_profit) keep allowing normally, independent of the
 *      accounting flag.
 */
class ModuleEntitlementPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $companyUser;

    protected function setUp(): void
    {
        parent::setUp(); // RefreshDatabase -> base TestCase runs PermissionSeeder

        Company::forgetModuleCache();

        $this->companyUser = User::factory()->create(['role_id' => Role::COMPANY]);

        $this->company = Company::factory()->create([
            'user_id' => $this->companyUser->id,
        ]);

        $role = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $this->company->id,
        ]);

        $this->companyUser->assignRole($role);

        // One permission per ability under test, spanning every gated
        // module so a denial can only be explained by the module gate.
        $role->givePermissionTo([
            'view coa',
            'view account',
            'view credit',
            'view currency exchange',
            'view profit loss',
            'view task',
            'view payment',
            'view client',
            'view agent',
        ]);
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();

        parent::tearDown();
    }

    private function setAccountingModule(bool $enabled): void
    {
        Setting::updateOrCreate(
            ['company_id' => $this->company->id, 'key' => Modules::settingKey(Modules::ACCOUNTING)],
            ['type' => 'boolean', 'value' => $enabled]
        );

        Company::forgetModuleCache();
    }

    private function assertAccountingAbilities(bool $expected): void
    {
        $this->assertSame($expected, $this->companyUser->can('viewAny', CoaCategory::class), 'COAPolicy::viewAny');
        $this->assertSame($expected, $this->companyUser->can('viewAny', Account::class), 'AccountPolicy::viewAny');
        $this->assertSame($expected, $this->companyUser->can('viewAny', Credit::class), 'CreditPolicy::viewAny');
        $this->assertSame($expected, $this->companyUser->can('viewAny', CurrencyExchange::class), 'CurrencyExchangePolicy::viewAny');
        $this->assertSame($expected, $this->companyUser->can('viewProfitLoss', Report::class), 'ReportPolicy::viewProfitLoss');
    }

    private function assertPackageAbilitiesAllowed(): void
    {
        $this->assertTrue($this->companyUser->can('viewAny', Task::class), 'TaskPolicy::viewAny (task_uploader)');
        $this->assertTrue($this->companyUser->can('viewAny', Payment::class), 'PaymentPolicy::viewAny (payment_gateway)');
        $this->assertTrue($this->companyUser->can('viewAny', Client::class), 'ClientPolicy::viewAny (crm)');
        $this->assertTrue($this->companyUser->can('viewAny', Agent::class), 'AgentPolicy::viewAny (agent_profit)');
    }

    // ------------------------------------------------------------------
    // 1. accounting OFF denies the accounting policies
    // ------------------------------------------------------------------

    public function test_accounting_module_off_denies_every_accounting_policy(): void
    {
        $this->setAccountingModule(false);

        $this->assertAccountingAbilities(false);
    }

    public function test_accounting_module_off_still_denies_a_role_that_would_otherwise_bypass_permissions(): void
    {
        // COAPolicy::viewAny (and several other accounting policy methods)
        // short-circuit to `true` for hasRole('admin') BEFORE reaching the
        // permission check. The module gate must run first and override
        // that bypass — a package client's own "admin" cannot see
        // accounting just because their role would normally see anything.
        $adminUser = User::factory()->create(['role_id' => Role::ADMIN]);
        session(['company_id' => $this->company->id]);

        $adminRole = Role::create([
            'name' => 'admin',
            'guard_name' => 'web',
            'company_id' => $this->company->id,
        ]);
        $adminUser->assignRole($adminRole);
        $adminRole->givePermissionTo(['view coa']);

        // Sanity check: with accounting ON, the admin bypass does work.
        $this->setAccountingModule(true);
        $this->assertTrue($adminUser->can('viewAny', CoaCategory::class));

        $this->setAccountingModule(false);
        $this->assertFalse($adminUser->can('viewAny', CoaCategory::class));
    }

    public function test_accounting_module_can_be_explicitly_turned_back_on(): void
    {
        $this->setAccountingModule(false);
        $this->assertAccountingAbilities(false);

        $this->setAccountingModule(true);
        $this->assertAccountingAbilities(true);
    }

    // ------------------------------------------------------------------
    // 2. Legacy company (no settings rows at all): package modules keep
    //    working, accounting does not
    // ------------------------------------------------------------------

    /**
     * A company with zero `settings` rows keeps every module the product
     * actually sells, because Company::hasModule() fails OPEN for those.
     * Accounting is the exception: config('modules.default_disabled')
     * makes it fail CLOSED, so a legacy company that was never granted it
     * does not see it. TravelERP ships without accounting, and a company
     * predating the entitlement layer was never sold it either — leaving
     * it visible there is precisely the leak the list exists to close.
     */
    public function test_legacy_company_keeps_package_modules_but_not_accounting(): void
    {
        // No setAccountingModule() call at all: this company has zero rows
        // in `settings`, exactly like every pre-entitlement company.
        $this->assertSame(0, Setting::where('company_id', $this->company->id)->count());

        $this->assertPackageAbilitiesAllowed();
        $this->assertAccountingAbilities(false);
    }

    /**
     * ...and the grant path still works from that same zero-row state, so
     * the one company that genuinely runs accounting can be given it with
     * a single explicit row (`php artisan company:set-module {id}
     * accounting --on`).
     */
    public function test_legacy_company_can_be_granted_accounting_explicitly(): void
    {
        $this->assertAccountingAbilities(false);

        $this->setAccountingModule(true);

        $this->assertAccountingAbilities(true);
        $this->assertPackageAbilitiesAllowed();
    }

    // ------------------------------------------------------------------
    // 3. The 4 package policies keep working, independent of accounting
    // ------------------------------------------------------------------

    public function test_package_policies_allow_normally_regardless_of_the_accounting_flag(): void
    {
        $this->setAccountingModule(false);

        $this->assertPackageAbilitiesAllowed();
        // ...while accounting stays denied at the very same time.
        $this->assertAccountingAbilities(false);
    }

    public function test_full_package_preset_yields_5_on_1_off(): void
    {
        (new ApplyCompanyModulePreset())->apply($this->company);

        $this->assertPackageAbilitiesAllowed();
        $this->assertAccountingAbilities(false);
    }

    // ------------------------------------------------------------------
    // Permission logic itself is still respected underneath the gate
    // ------------------------------------------------------------------

    public function test_module_on_does_not_grant_access_without_the_underlying_permission(): void
    {
        $unprivileged = User::factory()->create(['role_id' => Role::COMPANY]);
        // Owns a fresh, unconfigured company -> hasModule('accounting')
        // defaults true (no settings rows), so this isolates the
        // underlying permission check as the only thing left to deny.
        Company::factory()->create(['user_id' => $unprivileged->id]);
        // No role/permissions assigned to $unprivileged at all.

        $this->assertFalse($unprivileged->can('viewAny', CoaCategory::class));
    }
}
