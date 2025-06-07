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

    protected $fullSync;
    protected $cityId;
    protected $incrementalFromDate;
    public $timeout = 1200; // 20 minutes
    public $tries = 3;
    
    public function __construct($fullSync = false, $cityId = null, $incrementalFromDate = null)
    {
        $this->fullSync = $fullSync;
        $this->cityId = $cityId;
        
        // If incremental sync and no date provided, use 7 days ago
        if (!$fullSync && !$incrementalFromDate) {
            $this->incrementalFromDate = Carbon::now()->subDays(7)->format('Y-m-d');
        } else {
            $this->incrementalFromDate = $incrementalFromDate;
        }
        
        $this->onQueue('api_sync');
    }

    public function handle(MagicHolidayService $magicHoliday)
    {
        try {
            Log::info('Starting hotel sync job', [
                'full_sync' => $this->fullSync,
                'city_id' => $this->cityId,
                'incremental_from_date' => $this->incrementalFromDate
            ]);
            
            // If no specific city ID is provided, process all cities or use archive export
            if (!$this->cityId) {
                if ($this->fullSync) {
                    // For full sync, process city by city
                    $cities = MapCity::all();
                    
                    foreach ($cities as $city) {
                        // Dispatch a new job for each city to avoid timeouts
                        SyncHotelsJob::dispatch($this->fullSync, $city->id)
                            ->delay(now()->addSeconds(rand(10, 60))); // Add random delay to avoid API rate limits
                    }
                    
                    Log::info('Dispatched hotel sync jobs for all cities', [
                        'city_count' => $cities->count()
                    ]);
                } else {
                    // For incremental sync, use archive export API
                    // $this->syncHotelsFromArchive($magicHoliday);
                }
                
                return;
            }
            
            // Process hotels for a specific city
            $page = 1;
            $perPage = 100;
            $hasMorePages = true;
            $totalSynced = 0;
            
            while ($hasMorePages) {
                $response = $magicHoliday->getHotels($this->cityId, $page, $perPage);
                
                if (!isset($response['_embedded']['hotel'])) {
                    Log::error('Invalid API response format', ['response' => $response]);
                    break;
                }
                
                DB::beginTransaction();
                try {
                    foreach ($response['_embedded']['hotels'] as $hotelData) {
                        $hotel = MapHotel::updateOrCreate(
                            ['id' => $hotelData['id']],
                            [
                                'name' => $hotelData['name'],
                                'city_id' => $this->cityId,
                                'address' => $hotelData['address'] ?? null,
                                'stars' => $hotelData['stars'] ?? null,
                                'type' => $hotelData['type'] ?? null,
                                'latitude' => $hotelData['geolocation']['latitude'] ?? null,
                                'longitude' => $hotelData['geolocation']['longitude'] ?? null,
                            ]
                        );
                        
                        // Queue a job to fetch hotel details, images, and descriptions
                        SyncHotelDetailsJob::dispatch($hotel->id)
                            ->delay(now()->addSeconds(rand(5, 30)));
                            
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
            
            Log::info('Hotel sync completed for city', [
                'city_id' => $this->cityId,
                'total_synced' => $totalSynced
            ]);
        } catch (\Exception $e) {
            Log::error('Hotel sync failed', [
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
    //         Log::info('Starting hotel sync from archive', [
    //             'incremental_from_date' => $this->incrementalFromDate
    //         ]);
            
    //         $response = $magicHoliday->getArchiveExport($this->incrementalFromDate);
            
    //         if (!isset($response['downloadUrl'])) {
    //             Log::error('Invalid archive export response', ['response' => $response]);
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
    //             Log::error('Invalid archive data format', ['archive' => $archiveData]);
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
            
    //         Log::info('Hotel sync from archive completed', [
    //             'total_synced' => $totalSynced
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Hotel sync from archive failed', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
            
    //         throw $e;
    //     }
    // }

    public function failed(\Throwable $exception)
    {
        Log::error('Hotel sync job failed', [
            'city_id' => $this->cityId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}