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
     * (every one of the 3 live companies as of Phase 1) must have every
     * module reporting as enabled, for every module key — including
     * `accounting`, which is only ever turned off by an explicit row.
     */
    public function test_module_defaults_to_enabled_when_no_setting_row_exists(): void
    {
        $company = $this->makeCompany();

        foreach (Modules::ALL as $module) {
            $this->assertTrue(
                $company->hasModule($module),
                "Expected module [{$module}] to default to enabled for a company with no settings rows."
            );
        }
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
            'value' => false,
        ]);

        $this->assertFalse($companyA->hasModule(Modules::ACCOUNTING));
        // companyB has no row for this key at all -> fail-safe default.
        $this->assertTrue($companyB->hasModule(Modules::ACCOUNTING));
    }

    public function test_result_is_memoized_until_forgetModuleCache_is_called(): void
    {
        $company = $this->makeCompany();

        $this->assertTrue($company->hasModule(Modules::ACCOUNTING));

        // Flip the flag directly in the DB, bypassing hasModule()'s own
        // write path (ApplyCompanyModulePreset already busts the memo
        // itself) to prove the memo — not a fresh query — is what is
        // being returned on the next call.
        Setting::create([
            'company_id' => $company->id,
            'key' => Modules::settingKey(Modules::ACCOUNTING),
            'type' => 'boolean',
            'value' => false,
        ]);

        $this->assertTrue(
            $company->hasModule(Modules::ACCOUNTING),
            'Expected the memoized true from the first call, not a fresh read.'
        );

        Company::forgetModuleCache();

        $this->assertFalse(
            $company->hasModule(Modules::ACCOUNTING),
            'Expected a fresh read to pick up the flag flipped after the first call.'
        );
    }
}
