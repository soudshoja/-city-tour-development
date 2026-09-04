<?php

namespace Tests\Feature\Console;

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers `php artisan modules:apply-preset` (retrofit/override) and
 * `php artisan modules:show` (read-only inspection) —
 * App\Console\Commands\ApplyCompanyModulePresetCommand and
 * App\Console\Commands\ShowCompanyModulesCommand.
 */
class ApplyCompanyModulePresetCommandTest extends TestCase
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

    private function moduleRowCount(Company $company): int
    {
        return Setting::where('company_id', $company->id)->where('key', 'like', 'module.%')->count();
    }

    public function test_default_is_dry_run_and_writes_nothing(): void
    {
        $company = $this->makeCompany();

        $this->artisan('modules:apply-preset', ['company' => (string) $company->id])
            ->assertExitCode(0);

        $this->assertSame(0, $this->moduleRowCount($company));
    }

    public function test_explicit_dry_run_zero_without_force_refuses_to_write(): void
    {
        $company = $this->makeCompany();

        // Merge fixup (MERGE-PLAN-DEV-INTO-LAUNCH-2026-09-04.md §3): under ours' fail-closed
        // default_disabled (see ApplyCompanyModulePresetCommand's own merge fixup), a fresh
        // company with zero rows already effectively matches the DEFAULT preset exactly
        // (accounting off, the rest on) — applying it plain would compute changeCount=0 and
        // exit SUCCESS with 'Nothing to change', never reaching confirm() at all. --set is
        // added here so there is a genuine pending change to confirm, exercising the actual
        // "refuses to write without --force" contract this test is named for.
        //
        // Command::confirm() (Illuminate\Console\Concerns\InteractsWithIO) does not itself
        // check input interactivity before delegating to OutputStyle::confirm() ->
        // askQuestion() — passing '--no-interaction' alone does not stop Laravel's test
        // harness from routing the prompt to a mocked OutputStyle with no expectation
        // configured (BadMethodCallException). The idiomatic Artisan-testing way to answer a
        // confirm() prompt is expectsConfirmation(); 'no' matches the command's own confirm()
        // default.
        $this->artisan('modules:apply-preset', [
            'company' => (string) $company->id,
            '--dry-run' => '0',
            '--set' => ['accounting=true'],
        ])
            ->expectsConfirmation(
                "Write 1 settings row(s) for company #{$company->id} ({$company->name}) now? This changes what that company's users can see.",
                'no'
            )
            ->assertExitCode(1);

        $this->assertSame(0, $this->moduleRowCount($company));
    }

    public function test_force_write_applies_the_full_preset(): void
    {
        $company = $this->makeCompany();

        $this->artisan('modules:apply-preset', [
            'company' => (string) $company->id,
            '--dry-run' => '0',
            '--force' => true,
        ])->assertExitCode(0);

        Company::forgetModuleCache();

        foreach (config('modules.package_preset') as $module => $enabled) {
            $this->assertSame($enabled, $company->hasModule($module), "Module '{$module}' did not match the configured preset.");
        }
    }

    public function test_apply_by_company_code_is_idempotent(): void
    {
        $company = $this->makeCompany();

        $write = fn () => $this->artisan('modules:apply-preset', [
            'company' => $company->code,
            '--dry-run' => '0',
            '--force' => true,
        ])->assertExitCode(0);

        $write();
        $write();

        $this->assertSame(
            count(config('modules.package_preset')),
            $this->moduleRowCount($company),
            'Re-running must update existing rows in place, never duplicate them.'
        );
    }

    public function test_set_override_changes_a_single_module_without_touching_others(): void
    {
        $company = $this->makeCompany();

        $this->artisan('modules:apply-preset', [
            'company' => (string) $company->id,
            '--dry-run' => '0',
            '--force' => true,
            '--set' => ['accounting=true'],
        ])->assertExitCode(0);

        Company::forgetModuleCache();

        $this->assertTrue($company->hasModule(Modules::ACCOUNTING));
        $this->assertTrue($company->hasModule(Modules::TASK_UPLOADER));
    }

    public function test_unknown_company_identifier_fails_cleanly_with_no_writes(): void
    {
        $this->artisan('modules:apply-preset', ['company' => '999999999'])
            ->assertExitCode(1);

        $this->assertSame(0, Setting::where('key', 'like', 'module.%')->count());
    }

    public function test_show_command_reports_fail_open_state_for_a_company_with_no_rows(): void
    {
        $company = $this->makeCompany();

        $this->artisan('modules:show', ['company' => (string) $company->id])
            ->assertExitCode(0);

        $this->assertSame(0, $this->moduleRowCount($company));
    }

    public function test_show_command_json_output_reflects_applied_preset(): void
    {
        $company = $this->makeCompany();

        $this->artisan('modules:apply-preset', [
            'company' => (string) $company->id,
            '--dry-run' => '0',
            '--force' => true,
        ])->assertExitCode(0);

        Company::forgetModuleCache();

        $this->artisan('modules:show', ['company' => (string) $company->id, '--json' => true])
            ->assertExitCode(0);

        $this->assertSame(count(Modules::ALL), $this->moduleRowCount($company));
    }
}
