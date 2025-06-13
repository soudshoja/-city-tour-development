<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SyncCountriesJob;
use App\Jobs\SyncCitiesJob;
use App\Jobs\SyncHotelsJob;
use App\Models\MapCity;
use App\Models\MapCountry;
use PhpParser\Node\Expr\Throw_;

class SyncMappingData extends Command
{
    protected $signature = 'mapping:sync {type?} {--full} {--date=} {--countries=} {--cities=}';
    protected $description = 'Sync mapping data from Magic Holidays API';

    public function handle()
    {
        $type = $this->argument('type');
        $isFull = $this->option('full');
        $date = $this->option('date');
        $countries = $this->option('countries');
        $cities = $this->option('cities');
        
        $this->info('Starting sync process...');
        
        switch ($type) {
            case 'countries':
                $this->info('Dispatching countries sync job...');
                dispatch(new SyncCountriesJob($isFull))
                    ->delay(now()->addSeconds(rand(5, 30))); // Random delay to avoid API rate limits
                break;
                
            case 'cities':
                $this->info('Dispatching cities sync job...');

                if($cities){
                    $this->error('The --cities option is not applicable for cities sync. Please specify countries or leave it empty.');
                    return;
                }

                if ($countries) {
                    $countryNames = explode(',', $countries);
                    $countryNames = array_map('trim', $countryNames);
                    $countryIds = MapCountry::whereIn('name', $countryNames)
                        ->pluck('id')
                        ->toArray();

                    if (empty($countryIds)) {
                        $this->error('No valid country IDs found for the provided names.');
                        return;
                    }
                    $this->info('Country IDs found: ' . implode(', ', $countryIds));

                    foreach ($countryIds as $countryId) {
                        dispatch(new SyncCitiesJob($isFull, $countryId))
                            ->delay(now()->addSeconds(rand(5, 30))); // Random delay to avoid API rate limits
                        $this->info("Dispatched SyncCitiesJob for country ID: $countryId");
                    }

                } else {
                    dispatch(new SyncCitiesJob($isFull))
                        ->delay(now()->addSeconds(rand(5, 30))); // Random delay to avoid API rate limits
                }
                break;
            case 'hotels':
                $this->info('Dispatching hotels sync job...');

                if($countries){
                   $this->error('The --countries option is not applicable for hotels sync. Please specify cities or leave it empty.');
                     return;
                }

                if ($cities) {
                    $cityName = explode(',', $cities);
                    $cityName = array_map('trim', $cityName);

                    $cityIds = MapCity::whereIn('name', $cityName)
                        ->pluck('id')
                        ->toArray();
                    if (empty($cityIds)) {
                        $this->error('No valid city IDs found for the provided names.');
                        return;
                    }

                    foreach ($cityIds as $cityId) {
                        dispatch(new SyncHotelsJob($isFull, $cityId, $date));
                        $this->info("Dispatched SyncHotelsJob for city ID: $cityId");
                    }

                } else {
                    dispatch(new SyncHotelsJob($isFull, null, $date))
                        ->delay(now()->addSeconds(rand(10, 60))); // Random delay to avoid API rate limits
                }
                break;

            case 'language':
                $this->error('Language sync is not implemented yet. Please use countries, cities, or hotels.');
                return;
      
            default:
                // Sync all with appropriate delays to prevent API rate limiting
                $this->info('Dispatching all sync jobs...');

                // Sync Countries
                dispatch(new SyncCountriesJob($isFull));
                $this->info('Countries sync job dispatched');

                // Sync Cities for each country
                $countryIds = MapCountry::orderBy('id')->pluck('id')->toArray();
                
                if (!empty($countryIds)) {
                    foreach ($countryIds as $countryId) {
                        dispatch(new SyncCitiesJob($isFull, $countryId));
                        $this->info("Dispatched SyncCitiesJob for country ID: $countryId");
                    }
                } else {
                    $this->error('No country IDs found for cities sync.');
                }

                // Sync Hotels for each city
                $cityIds = MapCity::pluck('id')->toArray();
                if (!empty($cityIds)) {
                    foreach ($cityIds as $cityId) {
                        dispatch(new SyncHotelsJob($isFull, $cityId, $date))
                            ->delay(now()->addSeconds(rand(10, 60)));
                        $this->info("Dispatched SyncHotelsJob for city ID: $cityId");
                    }
                } else {
                    $this->error('No city IDs found for hotels sync.');
                }
                break;

        }
        
        $this->info('Sync jobs have been dispatched. Run queue worker to process them.');
    }
}