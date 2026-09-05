<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\User;
use App\Services\Accounting\FixedAssets\DepreciationRunService;
use App\Services\Accounting\FixedAssets\FixedAssetService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\AccountingTestCase;

/**
 * accounting-builds T3 (Lane B): {@see DepreciationRunService} — straight-line schedule totals,
 * final-month rounding absorption, re-run idempotency (MP-3-1), locked-period refusal (MP-3-3),
 * and engine-OFF no-op.
 */
class DepreciationRunServiceTest extends AccountingTestCase
{
    private function assets(): FixedAssetService
    {
        return app(FixedAssetService::class);
    }

    private function runner(): DepreciationRunService
    {
        return app(DepreciationRunService::class);
    }

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

    private function makeActiveAsset(Company $company, array $overrides = []): FixedAsset
    {
        return $this->assets()->create(array_merge([
            'company_id' => $company->id,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'Depreciation Test Asset',
            'cost' => 1000.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => 7, // deliberately not evenly divisible: 1000/7 = 142.857142...
            'status' => FixedAsset::STATUS_ACTIVE,
        ], $overrides));
    }

    public function test_schedule_totals_exactly_to_the_depreciable_base(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeActiveAsset($company);

        $schedule = $this->assets()->scheduleFor($asset);

        $this->assertCount(7, $schedule);
        $sum = array_sum(array_column($schedule, 'amount'));
        $this->assertEqualsWithDelta(1000.0, $sum, 0.0001);

        // Final month absorbs the residual — not equal to the other six.
        $this->assertSame(142.857, $schedule[0]['amount']);
        $this->assertNotSame($schedule[0]['amount'], $schedule[6]['amount']);
    }

    public function test_monthly_run_posts_the_scheduled_amount_and_flips_status_on_final_month(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeActiveAsset($company);

        for ($m = 1; $m <= 6; $m++) {
            $result = $this->runner()->runForMonth($company->id, 2026, $m);
            $this->assertSame(1, $result['posted'], "Month {$m} should post exactly one document.");
            $this->assertSame(FixedAsset::STATUS_ACTIVE, $asset->fresh()->status);
        }

        $result = $this->runner()->runForMonth($company->id, 2026, 7);
        $this->assertSame(1, $result['posted']);

        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_FULLY_DEPRECIATED, $asset->status);
        $this->assertEqualsWithDelta(0.0, $this->assets()->nbv($asset), 0.0005);
    }

    /**
     * MP-3-1: re-running the same (company, year, month) must post ZERO new documents.
     */
    public function test_rerun_same_month_is_idempotent(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeActiveAsset($company);

        $first = $this->runner()->runForMonth($company->id, 2026, 1);
        $this->assertSame(1, $first['posted']);

        $second = $this->runner()->runForMonth($company->id, 2026, 1);
        $this->assertSame(0, $second['posted'], 'A second run for the same month must post nothing new.');
        $this->assertSame(1, $second['skipped']);

        $this->assertSame(
            1,
            FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->forPeriod(2026, 1)->count(),
            'Exactly one depreciation row must exist for the month, not two.'
        );
    }

    public function test_run_skips_draft_and_disposed_assets(): void
    {
        $company = $this->makeEngineOnCompany();
        $draftAsset = $this->assets()->create([
            'company_id' => $company->id,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'Still Draft',
            'cost' => 500.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => 5,
            // status omitted -> defaults to 'draft'
        ]);

        $result = $this->runner()->runForMonth($company->id, 2026, 1);

        $this->assertSame(0, $result['posted']);
        $this->assertSame(FixedAsset::STATUS_DRAFT, $draftAsset->fresh()->status);
    }

    /**
     * MP-3-3 (real engine mechanic — see DepreciationRunService's own docblock deviation note):
     * a locked month does not make PostingService refuse outright for an unprivileged post — it
     * silently SHIFTS `posting_date` forward to the next open period while leaving `docDate`
     * (the asset's own month-end) untouched. This asserts that shift actually lands the document
     * OUTSIDE the locked month — proving DepreciationRunService never sets
     * `allowLockedPeriods: true` to force it back into the locked one. The mutation (flip that
     * flag to true) makes `posting_date` land back in the originally-locked January instead of
     * the shifted February, which this exact assertion catches.
     */
    public function test_locked_month_shifts_posting_date_to_the_next_open_period(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeActiveAsset($company);

        AccountingPeriod::create([
            'company_id' => $company->id,
            'year' => 2026,
            'month' => 1,
            'status' => AccountingPeriod::STATUS_LOCKED,
        ]);
        // February has no row -> "no row = open" (PeriodGuard's own docblock), so the forward
        // search lands there.

        $result = $this->runner()->runForMonth($company->id, 2026, 1);

        $this->assertSame(1, $result['posted'], 'The document still posts — a locked month is not an outright refusal for an unprivileged post.');

        $depRow = FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->forPeriod(2026, 1)->firstOrFail();
        $transaction = \App\Models\Transaction::withoutGlobalScopes()->findOrFail($depRow->transaction_id);

        $this->assertSame('2026-02-01', Carbon::parse($transaction->posting_date)->toDateString(), 'posting_date must be shifted to February, never left in the locked January.');
        $this->assertSame('2026-01-31', Carbon::parse($transaction->transaction_date)->toDateString(), 'docDate/transaction_date stays the asset\'s own January month-end regardless of the shift.');
    }

    /**
     * MP-3-3 (the genuinely-refusing case): every period this company has initialised, for as
     * far forward as PeriodGuard will ever search, is locked — the forward shift search itself
     * exhausts and NoOpenPeriodFoundException propagates. THIS is what DepreciationRunService
     * catches and reports as a per-asset blocking line.
     */
    public function test_no_open_period_anywhere_ahead_is_reported_per_asset_and_does_not_post(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeActiveAsset($company);

        $rows = [];
        $cursor = Carbon::create(2026, 1, 1);
        for ($i = 0; $i <= 241; $i++) {
            $rows[] = [
                'company_id' => $company->id,
                'year' => $cursor->year,
                'month' => $cursor->month,
                'status' => AccountingPeriod::STATUS_LOCKED,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $cursor->addMonthNoOverflow();
        }
        AccountingPeriod::insert($rows);

        $result = $this->runner()->runForMonth($company->id, 2026, 1);

        $this->assertSame(0, $result['posted']);
        $this->assertCount(1, $result['blocked']);
        $this->assertStringContainsString((string) $asset->id, $result['blocked'][0]);

        $this->assertSame(
            0,
            FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->forPeriod(2026, 1)->count(),
            'A refused post must not leave a depreciation row behind.'
        );
    }

    public function test_engine_off_is_a_logged_noop(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        // Engine deliberately left OFF.

        $asset = FixedAsset::create([
            'company_id' => $company->id,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'Off Path Asset',
            'cost' => 500.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => 5,
            'status' => FixedAsset::STATUS_ACTIVE,
        ]);

        $result = $this->runner()->runForMonth($company->id, 2026, 1);

        $this->assertFalse($result['engine_enabled']);
        $this->assertSame(0, $result['posted']);
        $this->assertSame(
            0,
            FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->count(),
            'Engine OFF must never write a depreciation row.'
        );
    }

    public function test_dry_run_never_posts_even_when_engine_is_on(): void
    {
        $company = $this->makeEngineOnCompany();
        $asset = $this->makeActiveAsset($company);

        $result = $this->runner()->runForMonth($company->id, 2026, 1, dryRun: true);

        $this->assertSame(0, $result['posted']);
        $this->assertCount(1, $result['lines']);
        $this->assertSame($asset->id, $result['lines'][0]['fixed_asset_id']);
        $this->assertSame(
            0,
            FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->count(),
            'A dry run must never write a depreciation row.'
        );
    }

    public function test_dry_run_previews_even_when_engine_is_off(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        // Engine deliberately left OFF.

        $asset = FixedAsset::create([
            'company_id' => $company->id,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'Preview Asset',
            'cost' => 500.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => 5,
            'status' => FixedAsset::STATUS_ACTIVE,
        ]);

        $result = $this->runner()->runForMonth($company->id, 2026, 1, dryRun: true);

        $this->assertFalse($result['engine_enabled']);
        $this->assertCount(1, $result['lines']);
    }
}
