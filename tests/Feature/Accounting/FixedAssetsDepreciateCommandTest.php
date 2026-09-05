<?php

namespace Tests\Feature\Accounting;

use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\User;
use App\Services\Accounting\FixedAssets\FixedAssetService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T3 (Lane B): the `fixed-assets:depreciate` CLI wrapper — argument parsing
 * (explicit company/month, --all-companies, --dry-run) and the engine-OFF "nothing posted" exit
 * path (L2).
 */
class FixedAssetsDepreciateCommandTest extends AccountingTestCase
{
    private function makeEngineOnCompany(): Company
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        User::factory()->create();

        config(['accounting.engine.enabled' => true]);
        Artisan::call('accounting:engine', ['company' => $company->id, '--enable' => true]);

        $this->trackCompanyForInvariants($company->id);

        return $company;
    }

    protected function tearDown(): void
    {
        config(['accounting.engine.enabled' => false]);
        parent::tearDown();
    }

    private function makeActiveAsset(Company $company): FixedAsset
    {
        return app(FixedAssetService::class)->create([
            'company_id' => $company->id,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'Command Test Asset',
            'cost' => 600.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => 6,
            'status' => FixedAsset::STATUS_ACTIVE,
        ]);
    }

    public function test_command_posts_for_an_explicit_company_and_month(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeActiveAsset($company);

        $exitCode = Artisan::call('fixed-assets:depreciate', [
            'company' => $company->id,
            'month' => '2026-01',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(
            1,
            FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->forPeriod(2026, 1)->count()
        );
    }

    public function test_command_dry_run_never_posts(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeActiveAsset($company);

        $exitCode = Artisan::call('fixed-assets:depreciate', [
            'company' => $company->id,
            'month' => '2026-01',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->count());
        // Not asserting on Artisan::output() here — RefreshDatabase's own internal artisan calls
        // permanently rebind the console output mock for the rest of the test (see
        // AccountingVerifyCommandTest's own docblock for the identical, already-diagnosed gotcha
        // in this suite), so the exit code + DB-state assertions above are the reliable oracle.
    }

    public function test_command_all_companies_covers_every_company_with_a_fixed_asset(): void
    {
        $companyA = $this->makeEngineOnCompany();
        $companyB = $this->makeEngineOnCompany();
        $assetA = $this->makeActiveAsset($companyA);
        $assetB = $this->makeActiveAsset($companyB);

        $exitCode = Artisan::call('fixed-assets:depreciate', [
            'month' => '2026-01',
            '--all-companies' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, FixedAssetDepreciation::where('fixed_asset_id', $assetA->id)->forPeriod(2026, 1)->count());
        $this->assertSame(1, FixedAssetDepreciation::where('fixed_asset_id', $assetB->id)->forPeriod(2026, 1)->count());
    }

    public function test_command_requires_company_or_all_companies(): void
    {
        $exitCode = Artisan::call('fixed-assets:depreciate', ['month' => '2026-01']);

        $this->assertSame(1, $exitCode);
    }

    /**
     * Engine OFF (L2): the command prints a clear "nothing posted" line and exits 0 — never a
     * raw write, never a non-zero failure exit for an ordinary OFF company.
     */
    public function test_command_engine_off_prints_nothing_posted_and_exits_zero(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();

        $exitCode = Artisan::call('fixed-assets:depreciate', [
            'company' => $company->id,
            'month' => '2026-01',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, FixedAssetDepreciation::count());
        // See test_command_dry_run_never_posts()'s own comment on why Artisan::output() is not
        // asserted on in this suite.
    }

    public function test_command_rejects_a_malformed_month(): void
    {
        $company = $this->makeEngineOnCompany();

        $exitCode = Artisan::call('fixed-assets:depreciate', [
            'company' => $company->id,
            'month' => 'not-a-month',
        ]);

        $this->assertSame(1, $exitCode);
    }
}
