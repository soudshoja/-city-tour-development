<?php

namespace Tests\Feature\CompanyProvisioning;

use App\Models\Company;
use App\Models\Country;
use App\Models\Setting;
use App\Services\CompanyProvisioner;
use App\Support\CompanyRegistrationData;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Path B (invite-token self-registration) — CompanyRegistrationController
 * ::store() -> CompanyProvisioner::provision() -> [wired here, right after
 * seedChartOfAccounts()] ApplyCompanyModulePreset::apply(), but ONLY for a
 * genuinely new company.
 *
 * provision() is also the engine behind `company:provision --repair`
 * (App\Console\Commands\ProvisionCompany), which always targets an
 * EXISTING company. These tests prove both halves of that contract: a
 * fresh registration gets the preset, and a repair run on an existing
 * company — same code path, same method — does not silently move it off
 * the fail-open default.
 *
 * Complement to tests/Feature/CompanyProvisioning/AdminUsersControllerModulePresetTest.php
 * (Path A) and tests/Unit/Support/ApplyCompanyModulePresetTest.php (the
 * preset-writer itself, exercised directly).
 */
class CompanyProvisionerModulePresetTest extends TestCase
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

    private function registrationData(array $overrides = []): CompanyRegistrationData
    {
        $unique = uniqid();

        // Merge fixture: companies.country_id is a required (non-nullable) foreignId
        // (pre-existing schema, unrelated to this merge) — a real Country row is needed
        // here, not null, or CompanyProvisioner::provision()'s Company::create() throws
        // a QueryException before ever reaching the module-preset assertion this suite
        // is actually testing.
        return CompanyRegistrationData::fromArray(array_merge([
            'company_name' => 'Test Package Co',
            'company_code' => 'TPC-'.$unique,
            'country_id' => Country::factory()->create()->id,
            'address' => null,
            'phone' => null,
            'company_email' => "owner-{$unique}@example.test",
            'owner_name' => 'Test Owner',
            'owner_email' => "owner-{$unique}@example.test",
            'owner_password' => 'password12345',
            'currency' => 'KWD',
        ], $overrides));
    }

    public function test_provisioning_a_new_company_applies_the_package_preset(): void
    {
        $company = app(CompanyProvisioner::class)->provision($this->registrationData());

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

    public function test_repair_provisioning_an_existing_company_does_not_reapply_the_preset(): void
    {
        // Simulates company:provision --repair: the company already exists
        // (like the 3 pre-Phase-1 companies) with zero module rows, and
        // provision() runs again for the SAME code. Company::firstOrCreate()
        // in createOwnerAndCompany() finds it rather than creating it, so
        // wasRecentlyCreated is false and the preset must NOT be (re)applied
        // — a repair run must never silently move an existing company off
        // the fail-open default.
        $data = $this->registrationData(['company_code' => 'REPAIR-'.uniqid()]);

        $firstCompany = app(CompanyProvisioner::class)->provision($data);
        $this->assertDatabaseHas('settings', [
            'company_id' => $firstCompany->id,
            'key' => Modules::settingKey(Modules::ACCOUNTING),
        ]);

        // Strip back to "pre-Phase-1" shape (no module rows at all) so this
        // test isolates exactly one thing: does a SECOND provision() call
        // against the SAME already-existing company write anything.
        Setting::where('company_id', $firstCompany->id)->where('key', 'like', 'module.%')->delete();
        Company::forgetModuleCache();

        $repaired = app(CompanyProvisioner::class)->provision($data);

        $this->assertSame($firstCompany->id, $repaired->id);
        $this->assertFalse($repaired->wasRecentlyCreated);
        $this->assertSame(
            0,
            Setting::where('company_id', $repaired->id)->where('key', 'like', 'module.%')->count(),
            'A repair run on an existing company must not write module.* rows.'
        );

        // Merge fixture (T-2, MERGE-PLAN-DEV-INTO-LAUNCH-2026-09-04.md §2.5): under
        // ours' fail-closed default (config('modules.default_disabled')), a module
        // listed there (accounting) reads OFF with no explicit row, not ON.
        $defaultDisabled = (array) config('modules.default_disabled', []);
        foreach (Modules::ALL as $module) {
            if (in_array($module, $defaultDisabled, true)) {
                $this->assertFalse($repaired->hasModule($module), "Expected fail-CLOSED for '{$module}' with no rows.");
                continue;
            }
            $this->assertTrue($repaired->hasModule($module), "Expected fail-open ON for '{$module}' after a repair run with no rows.");
        }
    }

    public function test_the_actual_repair_console_command_does_not_apply_the_preset(): void
    {
        // Same guarantee as above, but through the real
        // `company:provision --repair` entry point rather than calling
        // CompanyProvisioner::provision() directly, so the console
        // command's own wiring is covered too.
        $data = $this->registrationData(['company_code' => 'CMDREPAIR-'.uniqid()]);
        $company = app(CompanyProvisioner::class)->provision($data);

        Setting::where('company_id', $company->id)->where('key', 'like', 'module.%')->delete();
        Company::forgetModuleCache();

        $this->artisan('company:provision', ['--company' => $company->id, '--repair' => true])
            ->assertExitCode(0);

        $this->assertSame(
            0,
            Setting::where('company_id', $company->id)->where('key', 'like', 'module.%')->count(),
            'company:provision --repair must not write module.* rows for an existing company.'
        );
    }
}
