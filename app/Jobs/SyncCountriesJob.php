<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\MappingApiService;
use App\Models\MapCountry;
use App\Services\MagicHolidayService;
use Illuminate\Support\Facades\Log;

class SyncCountriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fullSync;
    public $timeout = 300; // 5 minutes
    public $tries = 3;
    
    public function __construct($fullSync = false)
    {
        $this->fullSync = $fullSync;
        $this->onQueue('api_sync');
    }

    public function handle(MagicHolidayService $magicHoliday)
    {
        try {
            Log::info('Starting country sync job', ['full_sync' => $this->fullSync]);
            
            $page = 1;
            $perPage = 100;
            $hasMorePages = true;
            $totalSynced = 0;
            
            while ($hasMorePages) {
                $query = [
                    'page' => $page,
                    'per_page' => $perPage,
                ];
                $response = $magicHoliday->getCountries($query);
                
                if (!isset($response['_embedded']['countries'])) {
                    Log::error('Invalid API response format', ['response' => $response]);
                    break;
                }
                Log::channel('mapping')->info('Fetched countries', [
                    'page' => $response['_page'],
                    'count' => count($response['_embedded']['countries']),
                ]);
                foreach ($response['_embedded']['countries'] as $countryData) {
                    Log::channel('mapping')->info('Processing country', [
                        'id' => $countryData['id'],
                        'name' => $countryData['name'],
                        'iso' => $countryData['iso'] ?? null,
                        'services' => $countryData['services'] ?? []
                    ]);
                    MapCountry::updateOrCreate(
                        ['id' => $countryData['id']],
                        [
                            'name' => $countryData['name'],
                            'iso' => $countryData['iso'] ?? null,
                            'services' => json_encode($countryData['services'] ?? []),
                        ]
                    );
                    $totalSynced++;
                }
                
                // Check if there are more pages
                $page++;
                $hasMorePages = $page <= $response['_page_count'];
            }
            
            Log::info('Country sync completed', ['total_synced' => $totalSynced]);
        } catch (\Exception $e) {
            Log::error('Country sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Country sync job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}