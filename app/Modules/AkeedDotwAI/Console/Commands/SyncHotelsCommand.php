<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Console\Commands;

use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAIHotel;
use App\Services\DotwService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pre-load hotel entries from DOTW for a set of priority cities.
 *
 * Uses DotwService::searchHotelsWithMetadata which sends a searchhotels request
 * with <noPrice>true</noPrice> and a <fields> block so DOTW returns real hotel
 * names, addresses, city/country names, and geo-coordinates alongside each
 * hotel element. A placeholder name "Hotel {hotelId}" is used only when DOTW
 * returns an unexpectedly empty <hotelName>.
 *
 * City name in the dotwai_cities fallback lookup is no longer the primary
 * source — <cityName> from the DOTW response takes precedence. The fallback
 * resolves from dotwai_cities by numeric code and, if absent, falls back to
 * "City {code}".
 *
 * Usage:
 *   php artisan akeed-dotwai:sync-hotels --city=364
 *   php artisan akeed-dotwai:sync-hotels --city=364 --city=410 --force
 *   php artisan akeed-dotwai:sync-hotels --all
 */
class SyncHotelsCommand extends Command
{
    protected $signature = 'akeed-dotwai:sync-hotels
                            {--city=* : One or more DOTW city codes}
                            {--all : Sync all priority cities from config}
                            {--force : Re-sync existing hotel rows}';

    protected $description = 'Pre-load hotel entries from DOTW for priority cities into dotwai_hotels.';

    public function handle(): int
    {
        $cityCodes = $this->resolveCityCodes();

        if (empty($cityCodes)) {
            $this->warn('No city codes given. Use --city=<code> or --all (requires hotel_sync_priority_cities in config).');

            return self::SUCCESS;
        }

        $delayMs = (int) config('akeed_dotwai.hotel_sync_delay_ms', 200);
        $companyId = (int) config('akeed_dotwai.company_id');
        $dotw = new DotwService($companyId);

        $totalAdded = 0;
        $totalUpdated = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar(count($cityCodes));
        $bar->start();

        foreach ($cityCodes as $cityCode) {
            $cityCodeStr = (string) $cityCode;

            // Resolve human-readable city name from dotwai_cities for placeholder.
            // Falls back to "City {code}" if the row is not yet present.
            /** @phpstan-ignore-next-line staticMethod.notFound */
            $cityName = DotwAICity::where('code', $cityCodeStr)->value('name') ?? "City {$cityCodeStr}";

            try {
                $hotels = $dotw->searchHotelsWithMetadata(
                    [
                        'fromDate' => now()->addDay()->toDateString(),
                        'toDate' => now()->addDays(2)->toDateString(),
                        'currency' => (string) config('akeed_dotwai.default_currency', '769'),
                        'rooms' => [[
                            'no' => 1,
                            'adultsCode' => 2,
                            'children' => [],
                            'passengerNationality' => '66',
                            'passengerCountryOfResidence' => '66',
                        ]],
                        'filters' => ['city' => $cityCodeStr],
                    ],
                    (string) $cityName
                );

                foreach ($hotels as $h) {
                    if (empty($h['hotel_id'])) {
                        continue;
                    }

                    // Skip if already present and --force is not set.
                    /** @phpstan-ignore-next-line staticMethod.notFound */
                    if (! $this->option('force') && DotwAIHotel::where('dotw_hotel_id', $h['hotel_id'])->exists()) {
                        continue;
                    }

                    // searchHotelsWithMetadata now returns real hotel names from DOTW via
                    // the <fields> + <noPrice> request (T31.1-03 fix). Fall back to a
                    // placeholder only when the name field is unexpectedly empty.
                    $name = $h['name'] !== '' ? $h['name'] : "Hotel {$h['hotel_id']}";

                    /** @phpstan-ignore-next-line staticMethod.notFound */
                    $row = DotwAIHotel::updateOrCreate(
                        ['dotw_hotel_id' => $h['hotel_id']],
                        [
                            'name' => $name,
                            'city' => (string) $h['city_name'],
                            'country' => (string) ($h['country_name'] ?? ''),
                            'star_rating' => null,
                            'address' => $h['address'] ?? null,
                            'latitude' => $h['latitude'] ?? null,
                            'longitude' => $h['longitude'] ?? null,
                        ]
                    );

                    if ($row->wasRecentlyCreated) {
                        $totalAdded++;
                    } else {
                        $totalUpdated++;
                    }
                }
            } catch (Exception $e) {
                $errors++;
                Log::channel('dotw')->warning('[AkeedDotwAI] sync-hotels city failed', [
                    'city_code' => $cityCode,
                    'error' => $e->getMessage(),
                ]);
                $this->newLine();
                $this->warn("  City {$cityCodeStr} failed: {$e->getMessage()}");
            }

            usleep($delayMs * 1000);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Sync complete: {$totalAdded} added, {$totalUpdated} updated, {$errors} city errors.");

        return self::SUCCESS;
    }

    /**
     * Resolve the list of city codes to sync.
     *
     * @return array<int|string>
     */
    private function resolveCityCodes(): array
    {
        /** @var array<int|string> $cities */
        $cities = $this->option('city');

        if (! empty($cities)) {
            return $cities;
        }

        if ($this->option('all')) {
            /** @var array<int|string> $priority */
            $priority = (array) config('akeed_dotwai.hotel_sync_priority_cities', []);

            return $priority;
        }

        return [];
    }
}
