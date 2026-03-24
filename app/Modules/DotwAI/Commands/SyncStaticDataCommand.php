<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Commands;

use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAICountry;
use App\Services\DotwService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sync DOTW static data (cities, countries) from DOTW API.
 *
 * Calls DotwService::getCountryList() and DotwService::getCityList()
 * to populate the dotwai_countries and dotwai_cities tables.
 * Uses updateOrCreate to upsert without truncating existing data.
 *
 * On API error: logs a warning, keeps existing data, does NOT truncate.
 *
 * Usage:
 *   php artisan dotwai:sync-static                  # Sync both
 *   php artisan dotwai:sync-static --countries-only  # Countries only
 *   php artisan dotwai:sync-static --cities-only     # Cities only
 *
 * @see FOUND-05, SRCH-03
 */
class SyncStaticDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dotwai:sync-static
                            {--countries-only : Sync only countries}
                            {--cities-only : Sync only cities}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync DOTW static data (cities, countries) from DOTW API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $dotwService = new DotwService();

        $countriesOnly = $this->option('countries-only');
        $citiesOnly = $this->option('cities-only');

        // Default: sync both
        $syncCountries = !$citiesOnly;
        $syncCities = !$countriesOnly;

        $countryCount = 0;
        $cityCount = 0;

        // Sync countries
        if ($syncCountries) {
            $countryCount = $this->syncCountries($dotwService);

            if ($countryCount === -1) {
                return self::FAILURE;
            }
        }

        // Sync cities
        if ($syncCities) {
            $cityCount = $this->syncCities($dotwService);

            if ($cityCount === -1) {
                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info("Sync complete: {$countryCount} countries, {$cityCount} cities synced.");

        return self::SUCCESS;
    }

    /**
     * Sync countries from DOTW API.
     *
     * @param DotwService $dotwService
     * @return int Number of countries synced, or -1 on failure
     */
    private function syncCountries(DotwService $dotwService): int
    {
        $this->info('Syncing countries from DOTW API...');

        try {
            $countries = $dotwService->getCountryList();
        } catch (Exception $e) {
            $this->error("DOTW API error fetching countries: {$e->getMessage()}");
            Log::channel('dotw')->warning('[DotwAI] sync-static countries failed', [
                'error' => $e->getMessage(),
            ]);
            return -1;
        }

        $count = 0;
        $bar = $this->output->createProgressBar(count($countries));
        $bar->start();

        foreach ($countries as $country) {
            $code = $country['code'] ?? '';
            $name = $country['name'] ?? '';

            if (empty($code)) {
                $bar->advance();
                continue;
            }

            DotwAICountry::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'nationality_name' => $country['nationality_name'] ?? null,
                ]
            );

            $count++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  {$count} countries synced.");

        return $count;
    }

    /**
     * Sync cities from DOTW API for all synced countries.
     *
     * Iterates over all countries in the dotwai_countries table and
     * calls getCityList() for each. On per-country API error, logs
     * a warning and continues to the next country.
     *
     * @param DotwService $dotwService
     * @return int Number of cities synced, or -1 on total failure
     */
    private function syncCities(DotwService $dotwService): int
    {
        $this->info('Syncing cities from DOTW API...');

        $countries = DotwAICountry::all();

        if ($countries->isEmpty()) {
            $this->warn('No countries in database. Run with --countries-only first, or sync both.');
            return 0;
        }

        $count = 0;
        $errors = 0;
        $bar = $this->output->createProgressBar($countries->count());
        $bar->start();

        foreach ($countries as $country) {
            try {
                $cities = $dotwService->getCityList($country->code);

                foreach ($cities as $city) {
                    $code = $city['code'] ?? '';
                    $name = $city['name'] ?? '';

                    if (empty($code)) {
                        continue;
                    }

                    DotwAICity::updateOrCreate(
                        ['code' => $code],
                        [
                            'name' => $name,
                            'country_code' => $country->code,
                        ]
                    );

                    $count++;
                }
            } catch (Exception $e) {
                $errors++;
                Log::channel('dotw')->warning('[DotwAI] sync-static cities failed for country', [
                    'country_code' => $country->code,
                    'country_name' => $country->name,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  {$count} cities synced.");

        if ($errors > 0) {
            $this->warn("  {$errors} countries had API errors (logged). Existing data preserved.");
        }

        return $count;
    }
}
