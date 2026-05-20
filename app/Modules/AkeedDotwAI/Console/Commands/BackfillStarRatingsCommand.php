<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Console\Commands;

use App\Modules\AkeedDotwAI\Models\DotwCatalog;
use App\Modules\AkeedDotwAI\Services\StarRatingResolver;
use App\Modules\DotwAI\Models\DotwAIHotel;
use App\Services\DotwService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-shot drain of NULL dotwai_hotels.star_rating rows using DOTW classification codes.
 *
 * Iterates every distinct city that has at least one NULL star_rating row, calls
 * DotwService::searchHotelsWithMetadata for each city, decodes the returned
 * <rating> codes via StarRatingResolver, and writes the resolved 1-5 star int.
 *
 * Safe characteristics:
 * - Never overwrites existing non-null star_rating (whereNull guard on every UPDATE).
 * - Idempotent: re-running produces the same final state.
 * - Resumable: --from-city=<code> skips cities lexicographically below the value.
 * - Per-city try/catch: one failed city logs a warning and processing continues.
 * - 200ms pacing between city HTTP requests (mirrors SyncHotelsCommand convention).
 *
 * The command runs regardless of config('akeed_dotwai.enabled') — operators may want
 * to pre-populate star_rating ahead of the B2C launch. (Phase 34 D8 pattern mirror.)
 *
 * Prerequisites:
 * - DOTW credentials: company_dotw_credentials row for company_id=1 must exist.
 * - Classification catalog: dotw_catalogs(type='classification') must be populated.
 *   The command auto-triggers akeed-dotwai:sync-catalogs --type=classification if empty.
 *
 * @see \App\Modules\AkeedDotwAI\Services\StarRatingResolver
 * @see \App\Modules\AkeedDotwAI\Console\Commands\SyncHotelsCommand (pacing pattern)
 */
class BackfillStarRatingsCommand extends Command
{
    protected $signature = 'akeed-dotwai:backfill-star-ratings
                            {--from-city= : Resume from this DOTW city code (lexicographic skip)}
                            {--limit-cities= : Stop after processing N cities (debug aid)}
                            {--dry-run : Resolve and log decisions without writing to the DB}';

    protected $description = 'Backfill dotwai_hotels.star_rating by decoding DOTW classification codes.';

    public function handle(StarRatingResolver $resolver): int
    {
        // Precondition 1: DOTW credentials must be available.
        try {
            // Resolve company_id: null means "legacy env path" (DotwService backwards compat).
            // Cast to int only when a non-null value is configured so we pass null rather
            // than 0 (DotwService checks `$companyId !== null`, and 0 would trigger the
            // B2B credential lookup path with no row for company_id=0).
            $rawCompanyId = config('akeed_dotwai.company_id');
            $companyId = $rawCompanyId !== null ? (int) $rawCompanyId : null;
            $dotw = new DotwService($companyId);
        } catch (\RuntimeException $e) {
            $this->error('DOTW credentials problem: '.$e->getMessage());

            return self::FAILURE;
        }

        // Precondition 2: classification catalog must be populated.
        if (DotwCatalog::ofType(DotwCatalog::TYPE_CLASSIFICATION)->count() === 0) {
            $this->warn('Classification catalog empty — triggering akeed-dotwai:sync-catalogs.');
            Artisan::call('akeed-dotwai:sync-catalogs', ['--type' => ['classification']]);

            if (DotwCatalog::ofType(DotwCatalog::TYPE_CLASSIFICATION)->count() === 0) {
                $this->error('Classification catalog still empty after sync. Aborting.');

                return self::FAILURE;
            }

            // Clear resolver cache so the newly-synced rows are used.
            StarRatingResolver::clearCache();
        }

        $fromCity = $this->option('from-city');
        $limitCities = $this->option('limit-cities');
        $dryRun = (bool) $this->option('dry-run');

        // Build distinct city code list from hotels with NULL star_rating.
        // dotwai_hotels.city stores the city NAME (string), not the numeric code.
        // JOIN dotwai_cities to resolve name → DOTW city code for the API call.
        $cities = DB::table('dotwai_hotels as h')
            ->join('dotwai_cities as c', 'h.city', '=', 'c.name')
            ->whereNull('h.star_rating')
            ->when($fromCity !== null, fn ($q) => $q->where('c.code', '>=', $fromCity))
            ->select('c.code')
            ->distinct()
            ->orderBy('c.code')
            ->pluck('c.code')
            ->all();

        if ($limitCities !== null) {
            $cities = array_slice($cities, 0, (int) $limitCities);
        }

        if (empty($cities)) {
            $this->info('No cities with NULL star_rating found. Nothing to do.');

            return self::SUCCESS;
        }

        $delayMs = 200; // 200ms between city calls — mirrors SyncHotelsCommand:135 convention
        $rowsUpdated = 0;
        $errors = 0;
        $cityCount = count($cities);

        $bar = $this->output->createProgressBar($cityCount);
        $bar->start();

        foreach ($cities as $cityCode) {
            try {
                // searchHotelsWithMetadata always requests the rating field (DotwService.php:298-306).
                // Each city call returns ALL hotels for that city in a single DOTW HTTP request —
                // the 200ms delay applies per-city, not per-hotel. (F2 resolution: no per-hotel slicing.)
                $hotels = $dotw->searchHotelsWithMetadata(
                    [
                        'fromDate' => now()->addDay()->toDateString(),
                        'toDate' => now()->addDays(2)->toDateString(),
                        'currency' => (string) config('akeed_dotwai.default_currency', '769'),
                        'rooms' => [[
                            'no' => 1,
                            'adultsCode' => 2,
                            'children' => [],
                            'passengerNationality' => '66',           // 66 = Kuwait — matches SyncHotelsCommand convention
                            'passengerCountryOfResidence' => '66',    // 66 = Kuwait — matches SyncHotelsCommand convention
                        ]],
                        'filters' => ['city' => (string) $cityCode],
                    ],
                    (string) $cityCode
                );

                foreach ($hotels as $h) {
                    $code = (string) ($h['rating'] ?? '');   // F1 fix: same key name as parseHotels
                    if ($code === '') {
                        continue;
                    }

                    $stars = $resolver->resolve($code);
                    if ($stars === null) {
                        continue;
                    }

                    if ($dryRun) {
                        Log::channel('dotw')->info('[BackfillStarRatings] dry-run', [
                            'hotel_id' => $h['hotel_id'],
                            'code' => $code,
                            'stars' => $stars,
                        ]);
                        continue;
                    }

                    // whereNull guard: never overwrite an existing non-null star_rating.
                    $affected = DotwAIHotel::where('dotw_hotel_id', $h['hotel_id'])
                        ->whereNull('star_rating')
                        ->update(['star_rating' => $stars]);

                    $rowsUpdated += $affected;
                }
            } catch (\Exception $e) {
                $errors++;
                Log::channel('dotw')->warning('[BackfillStarRatings] city failed', [
                    'city_code' => $cityCode,
                    'error' => $e->getMessage(),
                ]);
            }

            usleep($delayMs * 1000);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info(sprintf(
            'Backfill complete: %d cities processed, %d rows updated, %d errors.',
            $cityCount,
            $rowsUpdated,
            $errors
        ));

        return self::SUCCESS;
    }
}
