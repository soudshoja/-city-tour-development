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
            $xRateLimit = 0;
            $xRateLimitRemaining = 0;
            $xRateLimitReset = 0;
            
            while ($hasMorePages) {
                $query = [
                    'page' => $page,
                    'per_page' => $perPage,
                ];
                $response = $magicHoliday->getCountries($query);
                
                if ($response['status'] !== 200) {
                    Log::channel('mapping')->error('Invalid API response format', ['response' => $response]);
                    break;
                }

                $headers = $response['headers'];
                $data = $response['data'];

                // Update rate limit headers
                $xRateLimit = $headers['X-RateLimit-Limit'][0] ?? 0;
                $xRateLimitRemaining = $headers['X-RateLimit-Remaining'][0] ?? 0;
                $xRateLimitReset = $headers['X-RateLimit-Reset'][0] ?? 0;

                Log::channel('mapping')->info('Fetched countries', [
                    'page' => $data['_page'],
                    'count' => count($data['_embedded']['countries']),
                ]);
                foreach ($data['_embedded']['countries'] as $countryData) {
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
                $hasMorePages = $page <= $data['_page_count'];

                if ($xRateLimitRemaining <= 0) {
                    Log::channel('mapping')->warning('SyncCountriesJob: Rate limit exceeded, waiting for reset', [
                        'xRateLimit' => $xRateLimit,
                        'xRateLimitRemaining' => $xRateLimitRemaining,
                        'xRateLimitReset' => $xRateLimitReset
                    ]);
                    $waitTime = max(0, $xRateLimitReset - time());

                    Log::channel('mapping')->info('SyncCountriesJob: Rate limit reset time', [
                        'wait_seconds' => $waitTime,
                        'current_time' => time(),
                        'xRateLimitReset' => $xRateLimitReset
                    ]);

                    if ($waitTime > 0) {
                        Log::channel('mapping')->warning('SyncCountriesJob: Sleeping for rate limit reset', ['wait_seconds' => $waitTime]);
                        sleep($waitTime);
                    }
                }
            }
            
            Log::channel('mapping')->info('Country sync completed', ['total_synced' => $totalSynced]);
        } catch (\Exception $e) {
            Log::channel('mapping')->error('Country sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::channel('mapping')->error('Country sync job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}