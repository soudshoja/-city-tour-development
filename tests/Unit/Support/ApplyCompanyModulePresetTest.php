<?php

namespace Tests\Unit\Support;

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use App\Support\Entitlements\ApplyCompanyModulePreset;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyCompanyModulePresetTest extends TestCase
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

    private function makeCompany(): Company
    {
        return Company::factory()->create([
            'user_id' => User::factory()->create()->id,
        ]);
    }

    public function test_apply_writes_the_configured_package_preset(): void
    {
        $company = $this->makeCompany();

        (new ApplyCompanyModulePreset())->apply($company);

        foreach (config('modules.package_preset') as $module => $enabled) {
            $this->assertDatabaseHas('settings', [
                'company_id' => $company->id,
                'key' => Modules::settingKey($module),
                'type' => 'boolean',
            ]);

            $this->assertSame(
                $enabled,
                $company->hasModule($module),
                "Expected hasModule('{$module}') to reflect the applied preset."
            );
        }

        // Explicitly re-assert the headline shape the deliverable calls
        // for: 5 package modules on, accounting off.
        $this->assertTrue($company->hasModule(Modules::TASK_UPLOADER));
        $this->assertTrue($company->hasModule(Modules::PAYMENT_GATEWAY));
        $this->assertTrue($company->hasModule(Modules::CRM));
        $this->assertTrue($company->hasModule(Modules::AGENT_PROFIT));
        $this->assertTrue($company->hasModule(Modules::RESAYIL));
        $this->assertFalse($company->hasModule(Modules::ACCOUNTING));
    }

    public function test_apply_is_idempotent_and_updates_rather_than_duplicates(): void
    {
        $company = $this->makeCompany();
        $preset = new ApplyCompanyModulePreset();

        $preset->apply($company);
        $preset->apply($company);

        $this->assertSame(
            count(config('modules.package_preset')),
            Setting::where('company_id', $company->id)->count(),
            'Re-applying the preset must update existing rows, not insert duplicates.'
        );
    }

    public function test_apply_accepts_a_custom_preset_override(): void
    {
        $company = $this->makeCompany();

        (new ApplyCompanyModulePreset())->apply($company, [
            Modules::ACCOUNTING => true,
            Modules::CRM => false,
        ]);

        $this->assertTrue($company->hasModule(Modules::ACCOUNTING));
        $this->assertFalse($company->hasModule(Modules::CRM));
        // Untouched by the override -> still the fail-safe default.
        $this->assertTrue($company->hasModule(Modules::TASK_UPLOADER));
    }

    public function test_apply_only_touches_the_target_company(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();

        (new ApplyCompanyModulePreset())->apply($companyA);

        $this->assertFalse($companyA->hasModule(Modules::ACCOUNTING));

        // Proving B was untouched can no longer lean on
        // hasModule(ACCOUNTING) alone: accounting now fails CLOSED by
        // default, and the preset also sets it to false, so a leaked
        // apply() would land B on the exact same false value as an
        // untouched B -- the two cases would be indistinguishable through
        // hasModule() for ANY preset module, since every preset value
        // above now simply matches its module's own default (open or
        // closed). The settings-row count is the only assertion that
        // still discriminates "isolated" from "leaked": a leak would have
        // written B's own copy of the preset rows.
        $this->assertSame(0, Setting::where('company_id', $companyB->id)->count());
        // Belt-and-braces behavioural check: a module that still fails
        // open reads exactly as it would for any company nobody ever
        // touched.
        $this->assertTrue($companyB->hasModule(Modules::TASK_UPLOADER));
    }
}
