<?php

namespace Tests\Feature\Security;

use App\Models\Account;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\Entitlements\ApplyCompanyModulePreset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the ROUTE-LAYER half of the accounting URL-bypass fix: typing an
 * accounting URL directly must now 404 for a company with `module.accounting`
 * off — whether that's a package client with an explicit preset, or a
 * LEGACY company with no `module.*` rows at all, since accounting fails
 * CLOSED by default (config('modules.default_disabled') — see
 * Company::hasModule()). Every package-module URL, meanwhile, keeps working
 * exactly as before for both kinds of company — the accounting gate must
 * never bleed into them.
 *
 * This exercises real HTTP requests through the full middleware stack
 * (App\Http\Middleware\EnsureModuleEnabled, aliased 'module' in
 * bootstrap/app.php) rather than calling Policy::can() directly, so it is
 * the complement of tests/Feature/Entitlements/ModuleEntitlementPolicyTest.php
 * (which proves the same contract one layer down, at the Policy/Gate
 * layer) — together they cover both places Phase 1 added an enforcement
 * check.
 */
class AccountingRouteGateTest extends TestCase
{
    use RefreshDatabase;

    private const ACCOUNTING_PERMISSIONS = [
        'view coa',
        'view account',
        'view credit',
        'view currency exchange',
        'view profit loss',
        'view report',
    ];

    private const PACKAGE_PERMISSIONS = [
        'view task',
        'view payment',
        'view client',
        'view agent',
    ];

    protected function tearDown(): void
    {
        // hasModule() memoizes per company+module for the life of the PHP
        // process — tests reuse one process across many "requests", so the
        // memo must be dropped between tests the same way
        // ApplyCompanyModulePreset::apply() drops it after writing.
        Company::forgetModuleCache();

        parent::tearDown();
    }

    /**
     * Creates a company owned directly by a fresh Role::COMPANY user (so
     * both User::company() — used by the menu — and getCompanyId() — used
     * by RequiresCompanyModule::moduleEnabled() and every controller in
     * this test — resolve to the same company via the exact same
     * "Case 1: user directly owns a company" path). Grants every
     * permission these tests touch so a 404/redirect can only be
     * explained by the module gate, never by a missing permission.
     *
     * @return array{0: User, 1: Company}
     */
    private function createCompanyOwner(bool $applyPackagePreset): array
    {
        $user = User::factory()->create(['role_id' => Role::COMPANY]);
        $company = Company::factory()->create(['user_id' => $user->id]);

        $role = Role::create([
            'name' => 'company',
            'guard_name' => 'web',
            'company_id' => $company->id,
        ]);
        $user->assignRole($role);
        $role->givePermissionTo([...self::ACCOUNTING_PERMISSIONS, ...self::PACKAGE_PERMISSIONS]);

        // Seed the minimal Chart of Accounts that tasks.index hard-requires
        // before it will render at all: TaskController::index()
        // (app/Http/Controllers/TaskController.php:~248-263) redirects
        // back() with a flashed error whenever the acting company has no
        // "Liabilities" root account, and again whenever it has no
        // "Creditors" account whose root_id points at that Liabilities row —
        // regardless of the module gate this test exists to prove. Root-
        // caused via a throwaway probe test (deleted) that read
        // session('error') off the 302: confirmed the redirect fires at the
        // Liabilities check (line ~255), not the later invoiced-default
        // redirect (line ~320). This fixture previously seeded no accounts
        // at all, so every route-gate assertion touching tasks.index failed
        // here for a reason unrelated to the thing under test. Seed the
        // other four roots too so a partially-seeded COA never becomes a
        // second confound for this suite.
        $liabilities = Account::factory()->group()->create([
            'company_id' => $company->id,
            'name' => 'Liabilities',
        ]);
        Account::factory()->create([
            'company_id' => $company->id,
            'name' => 'Creditors',
            'parent_id' => $liabilities->id,
            'root_id' => $liabilities->id,
        ]);
        foreach (['Assets', 'Equity', 'Income', 'Expenses'] as $rootName) {
            Account::factory()->group()->create([
                'company_id' => $company->id,
                'name' => $rootName,
            ]);
        }

        if ($applyPackagePreset) {
            // The real onboarding shape for a TravelERP package client: the
            // 4 package modules explicitly on, `module.accounting`
            // explicitly off — see config/modules.php.
            (new ApplyCompanyModulePreset())->apply($company);
        }

        Company::forgetModuleCache();

        return [$user, $company];
    }

    private function assertAccountingRoutes404(): void
    {
        $this->get(route('coa.index'))->assertNotFound();
        $this->get(route('reports.trial-balance'))->assertNotFound();
        $this->get(route('reports.profit-loss'))->assertNotFound();
        $this->get(route('journal-entries.index', ['transactionId' => 1]))->assertNotFound();
        $this->get(route('receipt-voucher.index'))->assertNotFound();
        $this->get(route('bank-payments.index'))->assertNotFound();
        $this->get(route('reports.settlements'))->assertNotFound();
    }

    private function assertPackageRoutesOk(): void
    {
        // tasks.index depends on TaskController::index() finding a
        // "Liabilities" root account and a "Creditors" account whose
        // root_id points at it, for the acting company (app/Http/
        // Controllers/TaskController.php:~255) — nothing to do with the
        // module gate this test exists to prove. createCompanyOwner() seeds
        // that minimal COA for every company this suite creates (see its
        // seeding block above), so this reflects real production behavior
        // for a package client rather than an artifact of an unseeded test
        // fixture. Root-caused via a throwaway probe test (deleted) that
        // dumped session()->all() and the redirect Location off the 302:
        // with the COA seeded, the response is 302 to
        // "/tasks?invoiced=0&view_type=invoice" with an empty session
        // flash — i.e. TaskController.php's *separate*, unconditional
        // `if (!$request->has('invoiced'))` redirect (~line 314), which
        // fires for ANY bare visit to tasks.index regardless of Chart of
        // Accounts state (a real browser hitting a bare "/tasks" link
        // gets the exact same redirect and lands on the invoiced=0 URL
        // one hop later). Passing `invoiced` here reproduces that second,
        // real hop instead of asserting on an intermediate redirect that
        // was never the page under test. TaskController.php itself is
        // intentionally left untouched by this fix.
        $this->get(route('tasks.index', ['invoiced' => 0]))->assertOk();
        $this->get(route('payment.outstanding'))->assertOk();
        $this->get(route('clients.index'))->assertOk();
        $this->get(route('agents.index'))->assertOk();
    }

    // ------------------------------------------------------------------
    // 1. Package client (module.accounting OFF): 404 on every major
    //    accounting URL, even typed directly — this is the exact bypass
    //    the task closes.
    // ------------------------------------------------------------------

    public function test_package_client_gets_404_on_every_major_accounting_route(): void
    {
        [$user] = $this->createCompanyOwner(applyPackagePreset: true);

        $this->actingAs($user);

        $this->assertAccountingRoutes404();
    }

    // ------------------------------------------------------------------
    // 2. The SAME package client keeps full 200 access to the 4 package
    //    modules — the accounting gate must never bleed into them.
    // ------------------------------------------------------------------

    public function test_package_client_keeps_200_on_package_module_routes(): void
    {
        [$user] = $this->createCompanyOwner(applyPackagePreset: true);

        $this->actingAs($user);

        $this->assertPackageRoutesOk();
    }

    // ------------------------------------------------------------------
    // 3. A legacy company (no module.* settings rows at all — exactly the
    //    3 real companies as of Phase 1) now gets the SAME fail-closed
    //    accounting default as a package client — it was never sold
    //    accounting either, so leaving it open would be exactly the leak
    //    config('modules.default_disabled') exists to close. Every
    //    package-module route is still completely unaffected: those keep
    //    returning 200 exactly as before this change existed, for legacy
    //    companies same as package ones.
    // ------------------------------------------------------------------

    public function test_legacy_company_user_is_denied_accounting_but_unaffected_on_package_routes(): void
    {
        [$user] = $this->createCompanyOwner(applyPackagePreset: false);

        $this->actingAs($user);

        $this->assertAccountingRoutes404();
        $this->assertPackageRoutesOk();
    }
}
