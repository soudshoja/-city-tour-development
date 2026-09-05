<?php

declare(strict_types=1);

namespace App\Services\Accounting\FixedAssets;

use App\Exceptions\Accounting\NoOpenPeriodFoundException;
use App\Models\Company;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Services\Accounting\DocumentDraft;
use App\Services\Accounting\LineDraft;
use App\Services\Accounting\PostedDocument;
use App\Services\Accounting\PostingSeam;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * accounting-builds T3 (Lane B, L8): posts one balanced `DEP` document per (active) asset, per
 * calendar month, for every asset due a depreciation charge in the requested period.
 *
 * ── Straight-line rounding rule ───────────────────────────────────────────────────────────────
 * Monthly amount = `round((cost − salvage) / useful_life_months, 3)` for every month EXCEPT the
 * asset's own final scheduled month, which instead absorbs whatever residual is left so that
 * Σ(all months) == `cost − salvage` EXACTLY (never off by a fils from compounding per-month
 * rounding). {@see FixedAssetService::scheduleFor()} implements this rule once; this service
 * reuses it verbatim (via {@see self::dueAmountFor()}) rather than recomputing it, so the preview
 * schedule a caller sees before posting and the amount actually posted can never drift apart.
 *
 * ── Idempotency (MP-3-1) ──────────────────────────────────────────────────────────────────────
 * Each asset-month document is keyed `fa-dep:{asset_id}:{yyyy}-{mm}`. A second run for the same
 * (company, year, month) finds every asset already has a `FixedAssetDepreciation` row for that
 * period (checked BEFORE attempting to post) and skips it — belt-and-braces on top of
 * `PostingService::post()`'s own idempotency-key short-circuit (which would return the SAME
 * transaction rather than double-post even if this pre-check were skipped).
 *
 * ── Locked periods (MP-3-3) — DEVIATION from the plan's own assumed mechanic, verified in code ──
 * `DocumentDraft::$allowLockedPeriods` is deliberately left at its default `false` on every draft
 * this service builds. The PLAN (T3) assumed a locked month makes `PeriodGuard::assertOpen()`
 * throw `PeriodLockedException` straight out to the caller ("PeriodGuard refusal on locked months
 * surfaces as a per-asset blocking line"). Verified against the LIVE engine
 * (`PostingService::post()` step 5, P2.5.B): that is no longer what happens for an unprivileged,
 * no-override post (the only kind this service ever makes). `PeriodLockedException` from a locked
 * `docDate` is caught INSIDE `PostingService::post()` itself and silently resolved by shifting
 * `posting_date` (a value distinct from `docDate`/`transaction_date`, which stays the asset's own
 * month-end) forward to `PeriodGuard::earliestOpenOnOrAfter()` — the document still posts, just
 * dated (for period-lock purposes only) into the next open period. `PeriodLockedException`
 * therefore never reaches this service at all with `$allowLockedPeriods = false`; the ONE
 * `PostingException` that legitimately can is `NoOpenPeriodFoundException`, thrown when even that
 * forward search (240-period bound) finds nothing open — an operator having locked or soft-closed
 * every period ever initialised. THAT is caught here and recorded as a per-asset blocking line, so
 * the rest of the run for other assets still completes; it is never swallowed silently and this
 * service never sets `$allowLockedPeriods = true` to force a document into a still-locked month
 * (MP-3-3's mutation: doing so would make the resulting document's `posting_date` land in the
 * ORIGINALLY locked month instead of the shifted one — see
 * `DepreciationRunServiceTest::test_locked_month_shifts_posting_date_to_the_next_open_period()`).
 *
 * ── Engine OFF (L2) ───────────────────────────────────────────────────────────────────────────
 * A real (non-dry-run) call for a company the engine is not live for is a logged no-op —
 * `accounting.feature_skipped_engine_off` — nothing is posted, nothing is written to
 * `fixed_asset_depreciations`. `--dry-run` is exempt from this gate (see {@see self::runForMonth()}):
 * it never calls {@see PostingSeam::post()} at all, so previewing what a future run WOULD post is
 * safe and meaningful even while the engine is off for this company.
 */
final class DepreciationRunService
{
    public function __construct(
        private readonly PostingSeam $seam,
        private readonly FixedAssetService $assets,
    ) {}

    /**
     * @return array{
     *     engine_enabled: bool,
     *     dry_run: bool,
     *     posted: int,
     *     skipped: int,
     *     blocked: list<string>,
     *     lines: list<array{fixed_asset_id:int, year:int, month:int, amount:float}>,
     * }
     */
    public function runForMonth(int $companyId, int $year, int $month, bool $dryRun = false, ?int $userId = null): array
    {
        $engineEnabled = $this->seam->isEnabledFor($companyId);

        $result = [
            'engine_enabled' => $engineEnabled,
            'dry_run' => $dryRun,
            'posted' => 0,
            'skipped' => 0,
            'blocked' => [],
            'lines' => [],
        ];

        if (! $dryRun && ! $engineEnabled) {
            Log::info('accounting.feature_skipped_engine_off', [
                'feeder' => 'fixed-assets.depreciate',
                'company_id' => $companyId,
                'period' => sprintf('%04d-%02d', $year, $month),
            ]);

            return $result;
        }

        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth();

        $assets = FixedAsset::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', FixedAsset::STATUS_ACTIVE)
            ->get();

        foreach ($assets as $asset) {
            $due = $this->dueAmountFor($asset, $year, $month);

            if ($due === null || $due <= 0.0) {
                $result['skipped']++;

                continue;
            }

            $alreadyPosted = FixedAssetDepreciation::where('fixed_asset_id', $asset->id)
                ->forPeriod($year, $month)
                ->where('status', FixedAssetDepreciation::STATUS_POSTED)
                ->exists();

            if ($alreadyPosted) {
                $result['skipped']++;

                continue;
            }

            if ($dryRun) {
                $result['lines'][] = [
                    'fixed_asset_id' => $asset->id,
                    'year' => $year,
                    'month' => $month,
                    'amount' => $due,
                ];

                continue;
            }

            try {
                $posted = $this->postOneMonth($asset, $year, $month, $monthEnd, $due, $userId);
            } catch (NoOpenPeriodFoundException $e) {
                $result['blocked'][] = "Asset #{$asset->id} ({$asset->name}): {$e->getMessage()}";

                continue;
            }

            if ($posted === null) {
                // Engine-disabled race on the seam's own gate, or a legacy_skip_already_posted
                // short circuit -- not an error, just nothing new posted this call.
                $result['skipped']++;

                continue;
            }

            $result['posted']++;
            $this->maybeFlipToFullyDepreciated($asset);
        }

        return $result;
    }

    private function dueAmountFor(FixedAsset $asset, int $year, int $month): ?float
    {
        foreach ($this->assets->scheduleFor($asset) as $row) {
            if ($row['year'] === $year && $row['month'] === $month) {
                return $row['amount'];
            }
        }

        return null;
    }

    private function postOneMonth(FixedAsset $asset, int $year, int $month, Carbon $monthEnd, float $amount, ?int $userId): ?PostedDocument
    {
        $period = sprintf('%04d-%02d', $year, $month);
        $branchId = $asset->branch_id ?? Company::find($asset->company_id)?->branches()->first()?->id;

        $expenseLine = new LineDraft(
            purposeCode: 'DEPRECIATION_EXPENSE',
            accountId: null,
            side: 'debit',
            amount: $amount,
            currency: 'KWD',
            originalAmount: $amount,
            exchangeRate: 1.0,
            transactionType: 'FIXED_ASSET_DEPRECIATION',
            description: "Depreciation for {$asset->name} (#{$asset->id}), {$period}",
            taskId: $asset->id,
        );

        $contraLine = new LineDraft(
            purposeCode: "FA_ACCUM_DEP_{$asset->asset_class}",
            accountId: null,
            side: 'credit',
            amount: $amount,
            currency: 'KWD',
            originalAmount: $amount,
            exchangeRate: 1.0,
            transactionType: 'FIXED_ASSET_DEPRECIATION',
            description: "Accumulated depreciation for {$asset->name} (#{$asset->id}), {$period}",
            taskId: $asset->id,
        );

        $draft = new DocumentDraft(
            companyId: $asset->company_id,
            branchId: $branchId,
            docType: 'DEP',
            subType: null,
            docDate: $monthEnd,
            narration: "Monthly depreciation for fixed asset #{$asset->id} ({$asset->name}), {$period}.",
            lines: [$expenseLine, $contraLine],
            idempotencyKey: "fa-dep:{$asset->id}:{$period}",
            userId: $userId,
        );

        $legacy = function () use ($asset, $period) {
            Log::info('accounting.feature_skipped_engine_off', [
                'feeder' => 'fixed-assets.depreciate',
                'fixed_asset_id' => $asset->id,
                'period' => $period,
            ]);

            return null;
        };

        $result = $this->seam->post($draft, $legacy, 'fixed-assets.depreciate');

        if ($result instanceof PostedDocument) {
            FixedAssetDepreciation::updateOrCreate(
                ['fixed_asset_id' => $asset->id, 'period_year' => $year, 'period_month' => $month],
                [
                    'amount' => $amount,
                    'transaction_id' => $result->transaction->id,
                    'status' => FixedAssetDepreciation::STATUS_POSTED,
                ]
            );

            return $result;
        }

        return null;
    }

    private function maybeFlipToFullyDepreciated(FixedAsset $asset): void
    {
        $tolerance = (float) config('accounting.engine.balance_tolerance', 0.0005);
        $nbv = $this->assets->nbv($asset);

        if ($nbv <= (float) $asset->salvage + $tolerance) {
            $asset->status = FixedAsset::STATUS_FULLY_DEPRECIATED;
            $asset->save();
        }
    }
}
