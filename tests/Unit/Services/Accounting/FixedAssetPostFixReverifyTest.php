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
use Tests\Support\AccountingTestCase;

/**
 * POST-FIX RE-VERIFICATION (second, independent adversarial pass) of the three fixes commit
 * `1c67949f` applied to accounting-builds T2/T3/T4:
 *
 *   D1 — scheduleFor() floors instead of rounds when round() would overshoot the depreciable base.
 *   D2 — every enumeration of `fixed_assets` explicitly excludes soft-deleted rows.
 *   D3 — nbv() of a disposed asset is 0.0, not its full original cost.
 *
 * Each fix is re-derived here from first principles rather than re-asserting the fixing pass's own
 * examples: an exhaustive (base, life) sweep for D1, the RESTORE half of the soft-delete lifecycle
 * for D2, and the consumer contract (the re-disposal guard must key on status, never on nbv) for
 * D3. The final test pins the arrears remedy for the "missed intermediate month" hole §9 listed
 * but did not fix — a month the scheduled job never ran for can be caught up by an explicit
 * `{yyyy-mm}` run at any later time, and the asset then lands exactly on salvage.
 */
class FixedAssetPostFixReverifyTest extends AccountingTestCase
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
            'name' => 'Reverify Asset',
            'cost' => 1000.000,
            'salvage' => 0.000,
            'acquisition_date' => Carbon::create(2026, 1, 1),
            'in_service_date' => Carbon::create(2026, 1, 1),
            'useful_life_months' => 5,
            'status' => FixedAsset::STATUS_ACTIVE,
        ], $overrides));
    }

    /** Unsaved model — scheduleFor() is pure, so the math sweep needs no DB round-trip. */
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

    // -- D1 -----------------------------------------------------------------------------------

    /**
     * The D1 floor fallback must never break the four invariants the schedule exists to hold, for
     * ANY (depreciable base, life) pair: exactly `life` rows; Σ == base to the fils; no month
     * negative; and the RUNNING total never exceeds the base (the invariant that keeps NBV from
     * dipping below salvage part-way through life, which a naive "floor everything" fix would
     * satisfy but a "round everything" one would not).
     *
     * Sweep: every life 1..120 x every sub-0.100 base 0.001..0.099 (the whole pathological region
     * where round() can overshoot) plus 20 large/awkward bases up to 99,999.999 = 14,280 pairs.
     */
    public function test_floored_schedule_holds_every_invariant_across_a_full_base_life_sweep(): void
    {
        $bases = [];
        for ($fils = 1; $fils <= 99; $fils++) {
            $bases[] = $fils / 1000;                       // 0.001 .. 0.099, all of them
        }
        foreach ([0.100, 0.101, 0.999, 1.000, 1.001, 1.234, 7.777, 10.003, 99.999, 100.000,
            333.333, 1000.000, 1234.567, 1234.444, 3333.001, 9999.999, 10000.000,
            49999.501, 99999.998, 99999.999] as $wide) {
            $bases[] = $wide;
        }

        $negatives = [];
        $mismatches = [];
        $overshoots = [];
        $wrongLength = [];
        $pairs = 0;

        for ($life = 1; $life <= 120; $life++) {
            foreach ($bases as $base) {
                $cost = round($base + 0.500, 3);           // salvage 0.500 => depreciable base == $base
                $schedule = $this->assets()->scheduleFor($this->ghost($cost, 0.500, $life));
                $pairs++;

                if (count($schedule) !== $life) {
                    $wrongLength[] = sprintf('base=%.3f life=%d rows=%d', $base, $life, count($schedule));
                }

                $running = 0.0;
                foreach ($schedule as $i => $row) {
                    if ($row['amount'] < 0.0) {
                        $negatives[] = sprintf('base=%.3f life=%d month=%d amount=%.3f', $base, $life, $i + 1, $row['amount']);
                    }
                    $running = round($running + $row['amount'], 3);
                    if (round($running - $base, 3) > 0.0) {
                        $overshoots[] = sprintf('base=%.3f life=%d month=%d running=%.3f', $base, $life, $i + 1, $running);
                    }
                }

                if (abs($running - $base) > 0.0000001) {
                    $mismatches[] = sprintf('base=%.3f life=%d sum=%.6f', $base, $life, $running);
                }
            }
        }

        $this->assertSame(14280, $pairs, 'The sweep must actually cover 120 lives x 119 bases.');
        $this->assertSame([], array_slice($wrongLength, 0, 10), 'Every schedule must have exactly `life` rows.');
        $this->assertSame([], array_slice($negatives, 0, 10), 'No scheduled month may ever be negative.');
        $this->assertSame([], array_slice($overshoots, 0, 10), 'The running total may never exceed the depreciable base.');
        $this->assertSame([], array_slice($mismatches, 0, 10), 'Sigma(schedule) must equal cost - salvage to the fils.');
    }

    /**
     * The exact shape of the floored fallback, spelled out so a future "simplification" cannot
     * quietly change it: base 0.003 over 5 months charges nothing for four months and the whole
     * 0.003 in the fifth — never 0.001/month with a -0.001 residual (the D1 defect).
     */
    public function test_the_floored_fallback_defers_the_residual_it_never_goes_negative(): void
    {
        $schedule = $this->assets()->scheduleFor($this->ghost(0.003, 0.000, 5));

        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.003], array_column($schedule, 'amount'));
    }

    // -- D2 -----------------------------------------------------------------------------------

    /**
     * The RESTORE half of the soft-delete lifecycle the D2 fix implies but does not itself test:
     * an archived asset must not depreciate WHILE archived, must resume the moment it is restored,
     * and must not retroactively double-post the month that was skipped while it was archived.
     */
    public function test_a_restored_asset_resumes_depreciation_with_no_gap_double_post(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, ['cost' => 1000.000, 'useful_life_months' => 5]);

        $this->runner()->runForMonth($company->id, 2026, 1);          // 200 posted

        $asset->delete();
        $archivedRun = $this->runner()->runForMonth($company->id, 2026, 2);
        $this->assertSame(0, $archivedRun['posted'], 'An archived asset must not depreciate.');

        $asset->restore();
        $resumedRun = $this->runner()->runForMonth($company->id, 2026, 2);
        $this->assertSame(1, $resumedRun['posted'], 'A restored asset must resume depreciating.');

        // Re-running the resumed month must not double-post it.
        $rerun = $this->runner()->runForMonth($company->id, 2026, 2);
        $this->assertSame(0, $rerun['posted']);

        $this->assertSame(1, Transaction::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('idempotency_key', "fa-dep:{$asset->id}:2026-02")->count());
        $this->assertSame(2, FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->count());

        $asset->refresh();
        $this->assertSame(600.0, $this->assets()->nbv($asset), 'Exactly two months charged: 1000 - 400.');
    }

    // -- D3 -----------------------------------------------------------------------------------

    /**
     * D3 consumer contract. nbv() now returns 0.0 for a disposed asset, so anything that guarded
     * on "NBV is still positive" would silently change behaviour. dispose()'s re-disposal guard
     * must key on `status + disposal_transaction_id` (it does) and NOT on nbv: a second call with
     * DIFFERENT proceeds must return the original document and post nothing new.
     */
    public function test_re_disposal_guard_keys_on_status_not_on_the_now_zero_nbv(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, ['cost' => 1000.000, 'useful_life_months' => 5]);
        $this->runner()->runForMonth($company->id, 2026, 1);

        $asset->refresh();
        $first = $this->assets()->dispose($asset, Carbon::create(2026, 2, 10), 700.000);
        $this->assertNotNull($first);

        $asset->refresh();
        $this->assertSame(0.0, $this->assets()->nbv($asset));

        // Different proceeds on the second call: must be ignored, not re-derived off NBV = 0.
        $second = $this->assets()->dispose($asset, Carbon::create(2026, 3, 15), 1_000_000.000);

        $this->assertNotNull($second);
        $this->assertSame($first->transaction->id, $second->transaction->id);
        $this->assertSame(1, Transaction::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('idempotency_key', "fa-dsp:{$asset->id}")->count());

        $asset->refresh();
        $this->assertSame(700.0, (float) $asset->disposal_proceeds, 'The original proceeds must stand.');

        // And the ledger still balances on the one and only DSP document.
        $lines = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
            ->where('transaction_id', $first->transaction->id)->get();
        $this->assertEqualsWithDelta((float) $lines->sum('debit'), (float) $lines->sum('credit'), 0.0000001);
    }

    /**
     * The other D3 consumer: DepreciationRunService::maybeFlipToFullyDepreciated() compares nbv()
     * to salvage. A disposed asset must never be reachable by that comparison (it is filtered out
     * by `status = active` before nbv() is ever called), so NBV = 0.0 can never flip a disposed
     * asset back to `fully_depreciated`.
     */
    public function test_a_disposed_asset_keeps_its_disposed_status_through_later_runs(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, ['cost' => 1000.000, 'useful_life_months' => 5]);
        $this->runner()->runForMonth($company->id, 2026, 1);

        $asset->refresh();
        $this->assets()->dispose($asset, Carbon::create(2026, 2, 10), 700.000);

        for ($m = 2; $m <= 5; $m++) {
            $this->runner()->runForMonth($company->id, 2026, $m);
        }

        $asset->refresh();
        $this->assertSame(FixedAsset::STATUS_DISPOSED, $asset->status);
        $this->assertSame(1, FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->count());
    }

    // -- §9 item 5: the missed intermediate month --------------------------------------------

    /**
     * §9 observation 5 ("a missed intermediate month leaves the asset permanently short of
     * salvage") is a VISIBILITY gap, not a correctness hole: the arrears remedy already exists and
     * is exact. `fixed-assets:depreciate {company} {yyyy-mm}` for the missed month can be run at
     * any later time — after the asset's final scheduled month has already passed — and it lands
     * the asset exactly on salvage and flips its status. This test pins both halves: the shortfall
     * while the month is missing, and the exact recovery once it is run.
     */
    public function test_a_missed_month_is_recoverable_by_an_explicit_later_run_and_lands_on_salvage(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, [
            'cost' => 1234.567,
            'salvage' => 34.567,
            'useful_life_months' => 5,
        ]);

        // The scheduled job is down for March: months 1, 2, 4, 5 run, month 3 never does.
        foreach ([1, 2, 4, 5] as $m) {
            $this->runner()->runForMonth($company->id, 2026, $m);
        }

        $asset->refresh();
        $missed = 240.0;   // (1234.567 - 34.567) / 5
        $this->assertSame(round(34.567 + $missed, 3), $this->assets()->nbv($asset),
            'A missed month leaves the asset exactly one months charge short of salvage.');
        $this->assertSame(FixedAsset::STATUS_ACTIVE, $asset->status,
            'And it never flips to fully_depreciated, which is how the shortfall stays visible.');

        // Arrears run for the missed month, executed after the asset's final month has passed.
        $catchUp = $this->runner()->runForMonth($company->id, 2026, 3);
        $this->assertSame(1, $catchUp['posted']);

        $asset->refresh();
        $this->assertSame(34.567, $this->assets()->nbv($asset), 'Catch-up must land exactly on salvage.');
        $this->assertSame(FixedAsset::STATUS_FULLY_DEPRECIATED, $asset->status);
        $this->assertSame(5, FixedAssetDepreciation::where('fixed_asset_id', $asset->id)->count());
    }

    // -- §9 item 2, promoted to defect D4: editing the basis after depreciation started ---------

    /**
     * D4. §9 listed "update() accepts a changed cost/salvage/useful_life_months after depreciation
     * has begun" as cosmetic. It is not: scheduleFor() re-derives the entire schedule from the
     * asset's CURRENT basis and the run never reconciles against what was already posted, so the
     * remaining months charge the NEW base on top of the OLD charges. Measured before the fix:
     * cost 1000 / life 5, three months posted (600.000), cost edited to 500.000 => month 4 still
     * posted 100.000 and NBV landed at **-200.000** — a negative book value, accumulated
     * depreciation exceeding cost, and 200.000 KWD of overstated depreciation expense.
     */
    public function test_the_depreciation_basis_is_frozen_once_depreciation_has_posted(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, ['cost' => 1000.000, 'useful_life_months' => 5]);

        for ($m = 1; $m <= 3; $m++) {
            $this->runner()->runForMonth($company->id, 2026, $m);
        }

        $asset->refresh();
        $this->assertSame(400.0, $this->assets()->nbv($asset));

        foreach ([
            ['cost' => 500.000],
            ['salvage' => 100.000],
            ['useful_life_months' => 10],
            ['in_service_date' => Carbon::create(2026, 6, 1)],
            ['asset_class' => 'SOFTWARE'],
        ] as $basisEdit) {
            try {
                $this->assets()->update($asset, $basisEdit);
                $this->fail('Editing '.implode(',', array_keys($basisEdit)).' after depreciation posted must be refused.');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('already has posted depreciation', $e->getMessage());
            }
            $asset->refresh();
        }

        // Nothing leaked through: the basis and the derived NBV are untouched.
        $this->assertSame(1000.0, (float) $asset->cost);
        $this->assertSame(5, (int) $asset->useful_life_months);
        $this->assertSame(400.0, $this->assets()->nbv($asset));

        // Descriptive fields stay editable at any time.
        $this->assets()->update($asset, ['name' => 'Renamed after depreciation', 'notes' => 'ok']);
        $this->assertSame('Renamed after depreciation', $asset->refresh()->name);
    }

    /** The basis stays editable right up until the first DEP document posts. */
    public function test_the_basis_is_still_editable_before_any_depreciation_has_posted(): void
    {
        $company = $this->engineOnCompany();
        $asset = $this->asset($company, ['cost' => 1000.000, 'useful_life_months' => 5]);

        $this->assets()->update($asset, ['cost' => 500.000, 'salvage' => 50.000, 'useful_life_months' => 9]);

        $asset->refresh();
        $this->assertSame(500.0, (float) $asset->cost);
        $this->assertCount(9, $this->assets()->scheduleFor($asset));

        // And a no-op "re-save" of the same basis values is never refused, even after posting.
        $this->runner()->runForMonth($company->id, 2026, 1);
        $asset->refresh();
        $this->assets()->update($asset, ['cost' => 500.000, 'useful_life_months' => 9, 'name' => 'Same basis']);
        $this->assertSame('Same basis', $asset->refresh()->name);
    }
}
