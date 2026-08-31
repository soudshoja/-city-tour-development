<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyHasModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // hasModule() is exercised directly here without any HTTP/policy
        // layer involved, so the permission seeder this base TestCase
        // would otherwise run is unnecessary overhead.
        $this->skipPermissionSeeder = true;

        parent::setUp();

        // The per-request module memo is a class-level static, so it can
        // leak between test methods within the same PHPUnit process.
        // Every test in this file starts from a clean slate.
        Company::forgetModuleCache();
    }

    protected function tearDown(): void
    {
        Company::forgetModuleCache();

        parent::tearDown();
    }

    /**
     * companies.user_id is a real foreign key (see the `companies`
     * migration), so every Company needs an owning User rather than
     * relying on CompanyFactory's hardcoded `user_id => 1` stub.
     */
    private function makeCompany(): Company
    {
        return Company::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);
    }

    /**
     * The core fail-safe contract: a company with NO settings rows at all
     * (every company that predates the entitlement layer) must have every
     * module reporting as enabled — EXCEPT the ones listed in
     * config('modules.default_disabled'), which fail closed instead and
     * are covered by their own tests below.
     */
    public function test_module_defaults_to_enabled_when_no_setting_row_exists(): void
    {
        $company = $this->makeCompany();

        $defaultDisabled = (array) config('modules.default_disabled', []);

        foreach (Modules::ALL as $module) {
            if (in_array($module, $defaultDisabled, true)) {
                continue;
            }

            $this->assertTrue(
                $company->hasModule($module),
                "Expected module [{$module}] to default to enabled for a company with no settings rows."
            );
        }
    }

    /**
     * The inverse contract, and the reason the list exists: a module the
     * product does not sell must be OFF for a company with no explicit
     * row, so it stays hidden from every tenant that was never granted it
     * rather than being exposed to exactly the pre-entitlement companies.
     */
    public function test_module_in_default_disabled_defaults_to_off_when_no_setting_row_exists(): void
    {
        $company = $this->makeCompany();

        $defaultDisabled = (array) config('modules.default_disabled', []);

        $this->assertContains(
            Modules::ACCOUNTING,
            $defaultDisabled,
            'accounting is expected to ship fail-closed; see config/modules.php.'
        );

        foreach ($defaultDisabled as $module) {
            $this->assertFalse(
                $company->hasModule($module),
                "Expected module [{$module}] to default to DISABLED for a company with no settings rows."
            );
        }
    }

    /**
     * The grant path: an explicit `module.accounting = 1` row must beat
     * the fail-closed default, or the one company that genuinely runs
     * accounting could never be given it back.
     */
    public function test_explicit_true_row_overrides_a_default_disabled_module(): void
    {
        $company = $this->makeCompany();

        $this->assertFalse($company->hasModule(Modules::ACCOUNTING));

        Setting::create([
            'company_id' => $company->id,
            'key' => Modules::settingKey(Modules::ACCOUNTING),
            'type' => 'boolean',
            'value' => true,
        ]);

        Company::forgetModuleCache();

        $this->assertTrue($company->hasModule(Modules::ACCOUNTING));
    }

    /**
     * The behaviour is driven by config, not hardcoded against the
     * accounting key — so removing a module from the list re-opens it.
     */
    public function test_default_disabled_list_is_read_from_config(): void
    {
        $company = $this->makeCompany();

        config(['modules.default_disabled' => []]);
        Company::forgetModuleCache();

        $this->assertTrue(
            $company->hasModule(Modules::ACCOUNTING),
            'With an empty default_disabled list, accounting should fall back to the fail-open default.'
        );

        config(['modules.default_disabled' => [Modules::TASK_UPLOADER]]);
        Company::forgetModuleCache();

        $this->assertFalse($company->hasModule(Modules::TASK_UPLOADER));
        $this->assertTrue($company->hasModule(Modules::ACCOUNTING));
    }

    public function test_module_is_disabled_when_explicitly_set_to_false(): void
    {
        $company = $this->makeCompany();

        Setting::create([
            'company_id' => $company->id,
            'key' => Modules::settingKey(Modules::ACCOUNTING),
            'type' => 'boolean',
            'value' => false,
        ]);

        $this->assertFalse($company->hasModule(Modules::ACCOUNTING));

        // Untouched modules on the same company are still unaffected.
        $this->assertTrue($company->hasModule(Modules::TASK_UPLOADER));
    }

    public function test_module_is_enabled_when_explicitly_set_to_true(): void
    {
        $company = $this->makeCompany();

        Setting::create([
            'company_id' => $company->id,
            'key' => Modules::settingKey(Modules::ACCOUNTING),
            'type' => 'boolean',
            'value' => true,
        ]);

        $this->assertTrue($company->hasModule(Modules::ACCOUNTING));
    }

    public function test_flags_are_isolated_per_company(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();

        Setting::create([
            'company_id' => $companyA->id,
            'key' => Modules::settingKey(Modules::ACCOUNTING),
            'type' => 'boolean',
            'value' => true,
        ]);

        $this->assertTrue($companyA->hasModule(Modules::ACCOUNTING));
        // companyB has no row for this key at all, and accounting is
        // fail-closed, so A's explicit grant must not leak to B.
        $this->assertFalse($companyB->hasModule(Modules::ACCOUNTING));
    }

    public function test_result_is_memoized_until_forgetModuleCache_is_called(): void
    {
        $company = $this->makeCompany();

        // TASK_UPLOADER rather than ACCOUNTING: this test needs a module
        // whose no-row default is ON, and accounting is fail-closed.
        $this->assertTrue($company->hasModule(Modules::TASK_UPLOADER));

        // Flip the flag directly in the DB, bypassing hasModule()'s own
        // write path (ApplyCompanyModulePreset already busts the memo
        // itself) to prove the memo — not a fresh query — is what is
        // being returned on the next call.
        Setting::create([
            'company_id' => $company->id,
            'key' => Modules::settingKey(Modules::TASK_UPLOADER),
            'type' => 'boolean',
            'value' => false,
        ]);

        $this->assertTrue(
            $company->hasModule(Modules::TASK_UPLOADER),
            'Expected the memoized true from the first call, not a fresh read.'
        );

        Company::forgetModuleCache();

        $this->assertFalse(
            $company->hasModule(Modules::TASK_UPLOADER),
            'Expected a fresh read to pick up the flag flipped after the first call.'
        );
    }
}
