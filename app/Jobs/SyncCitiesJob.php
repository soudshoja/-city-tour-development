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
        if ($this->countryId !== null && !is_int($this->countryId)) {
            Log::channel('mapping')->error('Invalid country_id: must be integer', [
                'country_id' => $this->countryId
            ]);
            return;
        }
        try {
            Log::channel('mapping')->info('Starting city sync job', [
                'full_sync' => $this->fullSync,
                'country_id' => $this->countryId
            ]);
            
            // If no specific country ID is provided, process all countries
           
            // Process cities for a specific country
            $page = 1;
            $perPage = 100;
            $hasMorePages = true;
            $totalSynced = 0;
            
            while ($hasMorePages) {
                $query = [
                    'page' => $page,
                    'perPage' => $perPage,
                ];
                if ($this->countryId) {
                    $query['countryId'] = $this->countryId;
                }
                $response = $magicHoliday->getCities($query);

                Log::channel('mapping')->info('Fetched cities', [
                    'country_id' => $this->countryId,
                    'page' => $response['_page'],
                    'count' => count($response['_embedded']['cities'] ?? [])
                ]);
                
                if (!isset($response['_embedded']['cities'])) {
                    Log::channel('mapping')->error('Invalid API response format', [
                        'country_id' => $this->countryId,
                        'response' => $response
                    ]);
                    break;
                }

                Log::channel('mapping')->info('Cities got from country', [
                    'country_id' => $this->countryId,
                    'count' => count($response['_embedded']['cities'])
                ]);

                $cities = $response['_embedded']['cities'] ?? [];

                DB::beginTransaction();
                try {
                    foreach ($cities as $cityData) {
                        MapCity::updateOrCreate(
                            ['id' => $cityData['id']],
                            [
                                'name' => $cityData['name'],
                                'country_id' => $cityData['countryId'],
                                'services' => json_encode($cityData['services'] ?? []),
                                'code' => $cityData['code'] ?? null,
                            ]
                        );
                        $totalSynced++;
                        DB::commit();

                        Log::channel('mapping')->info('Synced city', [
                            'id' => $cityData['id'],
                            'name' => $cityData['name'],
                            'country_id' => $cityData['countryId']
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::channel('mapping')->error('Failed to sync cities', [
                        'country_id' => $this->countryId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
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