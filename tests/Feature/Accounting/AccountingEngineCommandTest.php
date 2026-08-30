<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * Feature test for `php artisan accounting:engine` — the W0 operator gesture for the
 * per-company posting-engine kill switch (see App\Console\Commands\AccountingEngine and
 * PostingEngineGateTest's FACT 1 regression guard for the fake-lever bug this replaces).
 */
class AccountingEngineCommandTest extends AccountingTestCase
{
    private function rawFlag(int $companyId): int
    {
        return (int) DB::table('companies')->where('id', $companyId)->value('posting_engine_enabled');
    }

    public function test_enable_sets_the_db_column_true(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $this->assertSame(0, $this->rawFlag($company->id));

        $exitCode = Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, $this->rawFlag($company->id));
    }

    public function test_disable_sets_the_db_column_false(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $company->forceFill(['posting_engine_enabled' => true])->save();

        $this->assertSame(1, $this->rawFlag($company->id));

        $exitCode = Artisan::call('accounting:engine', ['company' => $company->id, '--disable' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, $this->rawFlag($company->id));
    }

    public function test_status_only_does_not_write(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);
        $company->forceFill(['posting_engine_enabled' => true])->save();

        $exitCode = Artisan::call('accounting:engine', ['company' => $company->id, '--status' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, $this->rawFlag($company->id), 'Status should not mutate the flag.');
    }

    public function test_ambiguous_flags_are_refused(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $exitCode = Artisan::call('accounting:engine', [
            'company' => $company->id,
            '--enable' => true,
            '--disable' => true,
        ]);

        $this->assertNotSame(0, $exitCode);
    }

    public function test_no_flags_is_refused(): void
    {
        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $exitCode = Artisan::call('accounting:engine', ['company' => $company->id]);

        $this->assertNotSame(0, $exitCode);
    }

    public function test_unknown_company_fails_without_writing_anything(): void
    {
        $nonexistentId = (int) (DB::table('companies')->max('id') ?? 0) + 1_000_000;

        $exitCode = Artisan::call('accounting:engine', ['company' => $nonexistentId, '--enable' => true]);

        $this->assertNotSame(0, $exitCode);
        $this->assertSame(0, DB::table('companies')->where('id', $nonexistentId)->count());
    }

    /**
     * Uses $this->artisan() (Laravel's mocked-console-output test helper) rather than
     * Artisan::call() + a raw BufferedOutput: Tests\TestCase::setUp() calls
     * $this->artisan('db:seed', ['--class' => 'PermissionSeeder']) for every RefreshDatabase
     * test, which permanently rebinds OutputStyle::class in the container to a Mockery mock
     * wrapping ITS OWN internal buffer for the rest of the test — a raw
     * Artisan::call($cmd, $params, $ourBuffer) call made afterwards silently never reaches
     * $ourBuffer because the container's OutputStyle::class factory ignores the constructor
     * args on every subsequent resolution. $this->artisan()->expectsOutputToContain() is
     * built for exactly this rebound mock and is therefore the only reliable way to assert on
     * a command's console output once RefreshDatabase's setUp() has run.
     */
    public function test_output_prints_before_after_and_global_config_state(): void
    {
        config(['accounting.engine.enabled' => true]);

        $company = Company::factory()->create();
        $this->trackCompanyForInvariants($company->id);

        $this->artisan('accounting:engine', ['company' => $company->id, '--enable' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('before')
            ->expectsOutputToContain('after')
            ->expectsOutputToContain('accounting.engine.enabled');
    }
}
