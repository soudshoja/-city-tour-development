<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Accounting;

use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\JournalEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\FixedAssets\DepreciationRunService;
use App\Services\Accounting\FixedAssets\FixedAssetService;
use Database\Seeders\CoaSeeder;
use Database\Seeders\SystemAccountsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccountingTestCase;

/**
 * ADVERSARIAL VERIFICATION (verifier pass) of accounting-builds T2/T3/T4 — written independently
 * of the builder's own suite to pin the boundaries that suite does not cover: fils-scale and
 * non-divisible schedules, out-of-order and cross-company runs, DB-level idempotency, soft-delete
 * respect, and the disposal edge cases (zero depreciation, fully depreciated, proceeds == NBV,
 * run-after-disposal).
 */
class FixedAssetAdversarialVerifyTest extends AccountingTestCase
{
    private function assets(): FixedAssetService
    {
        return app(FixedAssetService::class);
    }

    private function runner(): DepreciationRunService
    {
        return app(DepreciationRunService::class);
    }

    private function engineOnCompany(): Company
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

    private function asset(Company $company, array $overrides = []): FixedAsset
    {
        return $this->assets()->create(array_merge([
            'company_id' => $company->id,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'Adversarial Asset',
            'cost' => 1000.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => 5,
            'status' => FixedAsset::STATUS_ACTIVE,
        ], $overrides));
    }

    /** Unsaved model — scheduleFor() is pure, no DB round-trip needed for the math sweep. */
    private function ghost(float $cost, float $salvage, int $life): FixedAsset
    {
        return new FixedAsset([
            'company_id' => 1,
            'asset_class' => 'CAPITAL_EQUIPMENT',
            'name' => 'ghost',
            'cost' => $cost,
            'salvage' => $salvage,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => $life,
            'status' => FixedAsset::STATUS_ACTIVE,
        ]);
    }

    // -- 1. MATH ------------------------------------------------------------------------------

    /** Sum must be EXACTLY cost - salvage, and no month may ever be negative. */
    public function test_schedule_sum_is_exact_and_no_month_is_negative_across_adversarial_inputs(): void
    {
        $lives = [1, 2, 3, 5, 6, 7, 8, 11, 12, 13, 24, 36, 60, 120];
        $bases = [];
        for ($fils = 1; $fils <= 60; $fils++) {   // 0.001 .. 0.060 KWD depreciable base
            $bases[] = $fils / 1000;
        }
        $bases = array_merge($bases, [1.0, 1.234, 7.777, 1000.0, 1234.567, 1234.444, 99999.999]);

        $negatives = [];
        $mismatches = [];

        foreach ($lives as $life) {
            foreach ($bases as $base) {
                $cost = round($base + 0.500, 3);   // salvage 0.500 -> depreciable base == $base
                $schedule = $this->assets()->scheduleFor($this->ghost($cost, 0.500, $life));

                $this->assertCount($life, $schedule);

                $sum = 0.0;
                foreach ($schedule as $row) {
                    $sum = round($sum + $row['amount'], 3);
                    if ($row['amount'] < 0) {
                        $negatives[] = sprintf('base=%.3f life=%d amount=%.3f', $base, $life, $row['amount']);
                    }
                }

                if (abs($sum - $base) > 0.0000001) {
                    $mismatches[] = sprintf('base=%.3f life=%d sum=%.3f', $base, $life, $sum);
                }
            }
        }

        $this->assertSame([], $mismatches, 'Schedule must sum EXACTLY to cost - salvage for every input.');
        $this->assertSame([], array_slice($negatives, 0, 10), 'No scheduled month may ever be negative.');
    }

    public function test_life_of_one_month_charges_the_whole_depreciable_base(): void
    {
        $schedule = $this->assets()->scheduleFor($this->ghost(1234.567, 34.567, 1));
        $this->assertCount(1, $schedule);
        $this->assertSame(1200.0, $schedule[0]['amount']);
    }

    public function test_cost_of_one_fil_is_schedulable_and_exact(): void
    {
        $schedule = $this->assets()->scheduleFor($this->ghost(0.001, 0.0, 12));
        $this->assertCount(12, $schedule);
        $this->assertEqualsWithDelta(0.001, array_sum(array_column($schedule, 'amount')), 0.0000001);
        foreach ($schedule as $row) {
            $this->assertGreaterThanOrEqual(0.0, $row['amount']);
        }
    }

    public function test_salvage_greater_than_cost_is_rejected(): void
    {
        $company = $this->engineOnCompany();

        $this->expectException(\InvalidArgumentException::class);
        $this->asset($company, ['cost' => 100.000, 'salvage' => 100.001]);
    }

    /** A full posted run must accumulate EXACTLY cost - salvage, month by month, at fils scale. */
    public function test_full_posted_run_accumulates_exactly_the_depreciable_base_with_fils_cost(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, [
            'cost' => 1234.567,
            'salvage' => 34.567,
            'useful_life_months' => 7,
        ]);

        for ($m = 1; $m <= 7; $m++) {
            $this->runner()->runForMonth($company->id, 2026, $m);
        }

        $asset->refresh();
        $this->assertSame(34.567, $this->assets()->nbv($asset), 'NBV after the final month must equal salvage exactly.');
        $this->assertSame(FixedAsset::STATUS_FULLY_DEPRECIATED, $asset->status);
        $this->assertSame(7, FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->count());
    }

    // -- 2. IDEMPOTENCY + CONCURRENCY ---------------------------------------------------------

    public function test_out_of_order_month_runs_still_total_exactly_and_flip_status(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, ['cost' => 1000.000, 'useful_life_months' => 3]);

        // N+1 before N before N+2.
        $this->runner()->runForMonth($company->id, 2026, 2);
        $this->runner()->runForMonth($company->id, 2026, 1);
        $this->runner()->runForMonth($company->id, 2026, 3);

        $asset->refresh();
        $this->assertSame(0.0, $this->assets()->nbv($asset));
        $this->assertSame(FixedAsset::STATUS_FULLY_DEPRECIATED, $asset->status);
        $this->assertSame(3, FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->count());
    }

    public function test_two_assets_in_the_same_month_get_distinct_documents_and_keys(): void
    {
        $company = $this->engineOnCompany();
        $a = $this->asset($company, ['name' => 'A']);
        $b = $this->asset($company, ['name' => 'B', 'asset_class' => 'SOFTWARE']);

        $result = $this->runner()->runForMonth($company->id, 2026, 1);
        $this->assertSame(2, $result['posted']);

        $keys = Transaction::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('idempotency_key', 'like', 'fa-dep:%')->pluck('idempotency_key')->all();

        sort($keys);
        $expected = ["fa-dep:{$a->id}:2026-01", "fa-dep:{$b->id}:2026-01"];
        sort($expected);
        $this->assertSame($expected, $keys);
    }

    /**
     * Race simulation: the application-level pre-check (fixed_asset_depreciations row) is removed
     * between two runs, so only the ENGINE's own idempotency key can stop a double post.
     */
    public function test_engine_level_idempotency_key_alone_prevents_a_double_post(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company);

        $this->runner()->runForMonth($company->id, 2026, 1);
        DB::table('fixed_asset_depreciations')->where('fixed_asset_id', $asset->id)->delete();
        $this->runner()->runForMonth($company->id, 2026, 1);

        $this->assertSame(1, Transaction::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('idempotency_key', "fa-dep:{$asset->id}:2026-01")->count());
        $asset->refresh();
        $this->assertSame(800.0, $this->assets()->nbv($asset), 'One month only: 1000 - 200.');
    }

    public function test_asset_period_uniqueness_is_enforced_at_the_database_level(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company);
        $this->runner()->runForMonth($company->id, 2026, 1);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('fixed_asset_depreciations')->insert([
            'fixed_asset_id' => $asset->id,
            'period_year' => 2026,
            'period_month' => 1,
            'amount' => 200.000,
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_transaction_idempotency_key_is_unique_at_the_database_level(): void
    {
        $unique = collect(DB::select('SHOW INDEX FROM transactions'))
            ->filter(fn ($i) => (int) $i->Non_unique === 0)
            ->groupBy('Key_name')
            ->map(fn ($rows) => $rows->pluck('Column_name')->all());

        $found = $unique->contains(fn ($cols) => in_array('idempotency_key', $cols, true));
        $this->assertTrue($found, 'transactions must carry a UNIQUE index covering idempotency_key.');
    }

    // -- 3. COMPANY SCOPING / SOFT DELETE -----------------------------------------------------

    public function test_an_asset_of_another_company_is_never_depreciated_by_this_companys_run(): void
    {
        $companyA = $this->engineOnCompany();
        $companyB = $this->engineOnCompany();

        $assetA = $this->asset($companyA, ['name' => 'A-owned']);
        $assetB = $this->asset($companyB, ['name' => 'B-owned']);

        $result = $this->runner()->runForMonth($companyA->id, 2026, 1);

        $this->assertSame(1, $result['posted']);
        $this->assertSame(1, FixedAssetDepreciation::where('fixed_asset_id', $assetA->id)->count());
        $this->assertSame(0, FixedAssetDepreciation::where('fixed_asset_id', $assetB->id)->count());
        $assetB->refresh();
        $this->assertSame(1000.0, $this->assets()->nbv($assetB), "Company B's asset must be untouched.");
    }

    public function test_a_soft_deleted_asset_is_never_depreciated(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company);
        $asset->delete();

        $result = $this->runner()->runForMonth($company->id, 2026, 1);

        $this->assertSame(0, $result['posted'], 'A soft-deleted (archived) asset must not depreciate.');
        $this->assertSame(0, Transaction::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('idempotency_key', 'like', 'fa-dep:%')->count());
    }

    // -- 4. DISPOSAL --------------------------------------------------------------------------

    public function test_dispose_an_asset_with_zero_depreciation_yet(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, ['cost' => 700.500]);

        $posted = $this->assets()->dispose($asset, Carbon::create(2026, 1, 20), 100.250);
        $this->assertNotNull($posted);

        $lines = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('transaction_id', $posted->transaction->id)->get();

        $this->assertCount(3, $lines, 'No accumulated-depreciation line when nothing was ever depreciated.');
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.0000001);
        $this->assertEqualsWithDelta(600.250, (float) $lines->sum('debit') - 100.250, 0.0000001, 'Loss = cost - proceeds.');
    }

    public function test_dispose_after_full_depreciation_leaves_nbv_at_salvage(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, ['cost' => 1000.000, 'salvage' => 100.000, 'useful_life_months' => 3]);

        for ($m = 1; $m <= 3; $m++) {
            $this->runner()->runForMonth($company->id, 2026, $m);
        }

        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_FULLY_DEPRECIATED, $asset->status);
        $this->assertSame(100.0, $this->assets()->nbv($asset), 'NBV after full depreciation must equal salvage.');

        $posted = $this->assets()->dispose($asset, Carbon::create(2026, 4, 5), 120.000);
        $this->assertNotNull($posted);

        $lines = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('transaction_id', $posted->transaction->id)->get();
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.0000001);
    }

    public function test_dispose_at_proceeds_exactly_equal_to_nbv_posts_no_gain_or_loss_line(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, ['cost' => 1000.000, 'useful_life_months' => 5]);
        $this->runner()->runForMonth($company->id, 2026, 1);
        $this->runner()->runForMonth($company->id, 2026, 2);

        $asset->refresh();
        $nbv = $this->assets()->nbv($asset);
        $this->assertSame(600.0, $nbv);

        $posted = $this->assets()->dispose($asset, Carbon::create(2026, 3, 1), $nbv);
        $this->assertNotNull($posted);

        $lines = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('transaction_id', $posted->transaction->id)->get();

        $this->assertCount(3, $lines, 'Zero gain/loss must be SUPPRESSED, not posted as a 0.000 line.');
        $this->assertSame(0, $lines->filter(fn ($l) => (float) $l->debit === 0.0 && (float) $l->credit === 0.0)->count());
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.0000001);
    }

    public function test_depreciation_run_after_disposal_skips_the_asset(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, ['cost' => 1000.000, 'useful_life_months' => 5]);
        $this->runner()->runForMonth($company->id, 2026, 1);

        $asset->refresh();
        $this->assertNotNull($this->assets()->dispose($asset, Carbon::create(2026, 2, 10), 700.000));

        $result = $this->runner()->runForMonth($company->id, 2026, 2);
        $this->assertSame(0, $result['posted']);
        $this->assertSame(0, Transaction::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('idempotency_key', "fa-dep:{$asset->id}:2026-02")->count());
    }

    public function test_nbv_of_a_disposed_asset_is_zero_not_the_full_cost(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, ['cost' => 1000.000, 'useful_life_months' => 5]);
        $this->runner()->runForMonth($company->id, 2026, 1);

        $asset->refresh();
        $this->assets()->dispose($asset, Carbon::create(2026, 2, 10), 700.000);
        $asset->refresh();

        $this->assertSame(0.0, $this->assets()->nbv($asset),
            'A disposed asset carries no book value - its cost and contra were both cleared by the DSP document.');
    }

    // -- 5. ENGINE OFF ------------------------------------------------------------------------

    public function test_register_writes_are_allowed_while_the_engine_is_off_but_nothing_posts(): void
    {
        $company = Company::factory()->create();
        CoaSeeder::run($company->id);
        (new SystemAccountsSeeder)->run();
        User::factory()->create();
        config(['accounting.engine.enabled' => false]);

        $asset = $this->asset($company);
        $this->assertNotNull($asset->id, 'Register writes must not depend on the engine flag.');

        $result = $this->runner()->runForMonth($company->id, 2026, 1);
        $this->assertFalse($result['engine_enabled']);
        $this->assertSame(0, $result['posted']);
        $this->assertSame(0, FixedAssetDepreciation::count());
        $this->assertSame(0, Transaction::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('idempotency_key', 'like', 'fa-%')->count());
    }
}
