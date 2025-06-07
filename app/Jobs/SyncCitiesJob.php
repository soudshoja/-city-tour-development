<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\MapCountry;
use App\Models\MapCity;

use App\Services\MagicHolidayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncCitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fullSync;
    protected $countryId;
    public $timeout = 600; // 10 minutes
    public $tries = 3;
    
    public function __construct($fullSync = false, $countryId = null)
    {
        $this->fullSync = $fullSync;
        $this->countryId = $countryId;
        $this->onQueue('api_sync');
    }

    public function handle(MagicHolidayService $magicHoliday)
    {
        try {
            Log::info('Starting city sync job', [
                'full_sync' => $this->fullSync,
                'country_id' => $this->countryId
            ]);
            
            // If no specific country ID is provided, process all countries
            if (!$this->countryId) {
                $countries = MapCountry::all();
                
                foreach ($countries as $country) {
                    // Dispatch a new job for each country to avoid timeouts
                    SyncCitiesJob::dispatch($this->fullSync, $country->id)
                        ->delay(now()->addSeconds(rand(5, 30))); // Add random delay to avoid API rate limits
                }
                
                Log::info('Dispatched city sync jobs for all countries', [
                    'country_count' => $countries->count()
                ]);
                
                return;
            }
            
            // Process cities for a specific country
            $page = 1;
            $perPage = 100;
            $hasMorePages = true;
            $totalSynced = 0;
            
            while ($hasMorePages) {
                $response = $magicHoliday->getCities($this->countryId, $page, $perPage);
                
                if (!isset($response['_embedded']['city'])) {
                    Log::error('Invalid API response format', ['response' => $response]);
                    break;
                }
                
                DB::beginTransaction();
                try {
                    foreach ($response['_embedded']['city'] as $cityData) {
                        MapCity::updateOrCreate(
                            ['id' => $cityData['id']],
                            [
                                'name' => $cityData['name'],
                                'country_id' => $this->countryId,
                                'latitude' => $cityData['latitude'] ?? null,
                                'longitude' => $cityData['longitude'] ?? null,
                                'code' => $cityData['code'] ?? null,
                            ]
                        );
                        $totalSynced++;
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
                
                // Check if there are more pages
                $page++;
                $hasMorePages = $page <= $response['_page_count'];
            }
            
            Log::info('City sync completed for country', [
                'country_id' => $this->countryId,
                'total_synced' => $totalSynced
            ]);
        } catch (\Exception $e) {
            Log::error('City sync failed', [
                'country_id' => $this->countryId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('City sync job failed', [
            'country_id' => $this->countryId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}