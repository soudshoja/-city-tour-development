<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\MagicHolidayService;

use App\Models\MapCity;
use App\Models\MapHotel;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncHotelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // protected $fullSync;
    protected $cityId;
    // protected $incrementalFromDate;
    
    public function __construct($cityId)
    {
        $this->cityId = $cityId;
        
        // If incremental sync and no date provided, use 7 days ago
        // if (!$fullSync && !$incrementalFromDate) {
        //     $this->incrementalFromDate = Carbon::now()->subDays(7)->format('Y-m-d');
        // } else {
        //     $this->incrementalFromDate = $incrementalFromDate;
        // }
        
        $this->onQueue('api_sync');
    }

    public function handle(MagicHolidayService $magicHoliday)
    {
        try {
            Log::channel('mapping')->info('Starting hotel sync job', [
                'city_id' => $this->cityId,
            ]);
            
            // Process hotels for a specific city
            $page = 1;
            $perPage = 100;
            $hasMorePages = true;
            $totalSynced = 0;
            $xRateLimit = 0;
            $xRateLimitRemaining = 0;
            $xRateLimitReset = 0;

            while ($hasMorePages) {

                $response = $magicHoliday->getHotels($this->cityId, $page, $perPage);
                
                if ($response['status'] !== 200) {
                    Log::channel('mapping')->error('Invalid API response format', ['response' => $response]);
                    break;
                }
                $headers = $response['headers'];
                $data = $response['data'];

                $xRateLimit = $headers['X-RateLimit-Limit'][0] ?? 0;
                $xRateLimitRemaining = $headers['X-RateLimit-Remaining'][0] ?? 0;
                $xRateLimitReset = $headers['X-RateLimit-Reset'][0] ?? 0;

                $hotels = $data['_embedded']['hotels'] ?? [];
                
                DB::beginTransaction();
                try {
                    foreach ($hotels as $hotelData) {
                        $hotel = MapHotel::updateOrCreate(
                            ['id' => $hotelData['id']],
                            [
                                'name' => $hotelData['name'],
                                'type' => $hotelData['type'] ?? null,
                                'address' => $hotelData['address'] ?? null,
                                'telephone' => $hotelData['telephone'] ?? null,
                                'fax' => $hotelData['fax'] ?? null,
                                'email' => $hotelData['email'] ?? null,
                                'zipCode' => $hotelData['zipCode'] ?? null,
                                'stars' => $hotelData['stars'] ?? null,
                                'recommended' => $hotelData['recommended'] ?? false,
                                'specialDeal' => $hotelData['specialDeal'] ?? false,
                                'city_id' => $hotelData['city']['id'],
                                // 'latitude' => $hotelData['geolocation']['latitude'] ?? null,
                                // 'longitude' => $hotelData['geolocation']['longitude'] ?? null,
                            ]
                        );
                        
                        // Queue a job to fetch hotel details, images, and descriptions
                        // SyncHotelDetailsJob::dispatch($hotel->id)
                        //     ->delay(now()->addSeconds(rand(5, 30)));
                            
                        $totalSynced++;

                        Log::channel('mapping')->info('Synced hotel', [
                            'hotel_id' => $hotel->id,
                            'city_id' => $hotel->city_id,
                            'name' => $hotel->name
                        ]);
                    }
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
                
                // Check if there are more pages
                $page++;
                $hasMorePages = $page <= $data['_page_count'];

                if($xRateLimitRemaining <= 0) {
                    Log::channel('mapping')->warning('SyncHotelsJob: Rate limit exceeded', [
                        'city_id' => $this->cityId,
                        'xRateLimit' => $xRateLimit,
                        'xRateLimitRemaining' => $xRateLimitRemaining,
                        'xRateLimitReset' => $xRateLimitReset
                    ]);

                    $waitTime = max(0, $xRateLimitReset - time());
                    Log::channel('mapping')->info('SyncHotelsJob: Rate limit reset time', [
                        'wait_seconds' => $waitTime,
                        'current_time' => time(),
                        'xRateLimitReset' => $xRateLimitReset
                    ]);
                    if ($waitTime > 0) {
                        Log::channel('mapping')->warning('SyncHotelsJob: Sleeping for rate limit reset', ['wait_seconds' => $waitTime]);
                        sleep($waitTime);
                    }
                }

            }
            
            Log::channel('mapping')->info('Hotel sync completed for city', [
                'city_id' => $this->cityId,
                'total_synced' => $totalSynced
            ]);
        } catch (\Exception $e) {
            Log::channel('mapping')->error('Hotel sync failed', [
                'city_id' => $this->cityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    // protected function syncHotelsFromArchive(MagicHolidayService $magicHoliday)
    // {
    //     try {
    //         Log::channel('mapping')->info('Starting hotel sync from archive', [
    //             'incremental_from_date' => $this->incrementalFromDate
    //         ]);
            
    //         $response = $magicHoliday->getArchiveExport($this->incrementalFromDate);
            
    //         if (!isset($response['downloadUrl'])) {
    //             Log::channel('mapping')->error('Invalid archive export response', ['response' => $response]);
    //             return;
    //         }
            
    //         // Download and process the archive file
    //         $archiveUrl = $response['downloadUrl'];
    //         $tempFile = tempnam(sys_get_temp_dir(), 'hotel_archive_');
    //         file_put_contents($tempFile, file_get_contents($archiveUrl));
            
    //         // Process the archive file (assuming it's a JSON file)
    //         $archiveData = json_decode(file_get_contents($tempFile), true);
    //         unlink($tempFile); // Clean up temp file
            
    //         if (!isset($archiveData['hotels'])) {
    //             Log::channel('mapping')->error('Invalid archive data format', ['archive' => $archiveData]);
    //             return;
    //         }
            
    //         $totalSynced = 0;
    //         $batchSize = 100;
    //         $hotelBatches = array_chunk($archiveData['hotels'], $batchSize);
            
    //         foreach ($hotelBatches as $batch) {
    //             DB::beginTransaction();
    //             try {
    //                 foreach ($batch as $hotelData) {
    //                     $hotel = MapHotel::updateOrCreate(
    //                         ['id' => $hotelData['id']],
    //                         [
    //                             'name' => $hotelData['name'],
    //                             'city_id' => $hotelData['cityId'],
    //                             'address' => $hotelData['address'] ?? null,
    //                             'stars' => $hotelData['stars'] ?? null,
    //                             'type' => $hotelData['type'] ?? null,
    //                             'latitude' => $hotelData['latitude'] ?? null,
    //                             'longitude' => $hotelData['longitude'] ?? null,
    //                         ]
    //                     );
                        
    //                     // Process hotel details if included in archive
    //                     if (isset($hotelData['descriptions'])) {
    //                         // Process descriptions...
    //                     }
                        
    //                     if (isset($hotelData['images'])) {
    //                         // Process images...
    //                     }
                        
    //                     $totalSynced++;
    //                 }
    //                 DB::commit();
    //             } catch (\Exception $e) {
    //                 DB::rollBack();
    //                 throw $e;
    //             }
    //         }
            
    //         Log::channel('mapping')->info('Hotel sync from archive completed', [
    //             'total_synced' => $totalSynced
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::channel('mapping')->error('Hotel sync from archive failed', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
            
    //         throw $e;
    //     }
    // }

    public function failed(\Throwable $exception)
    {
        Log::channel('mapping')->error('Hotel sync job failed', [
            'city_id' => $this->cityId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}