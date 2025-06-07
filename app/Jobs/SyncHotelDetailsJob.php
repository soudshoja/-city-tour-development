<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\MagicHolidayService;

use App\Models\MapHotel;
use App\Models\HotelImage;
use App\Models\HotelDescription;
use App\Models\MapHotelDescription;
use App\Models\MapHotelImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncHotelDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $hotelId;
    public $timeout = 300; // 5 minutes
    public $tries = 3;
    
    public function __construct($hotelId)
    {
        $this->hotelId = $hotelId;
        $this->onQueue('api_sync');
    }

    public function handle(MagicHolidayService $mappingApi)
    {
        try {
            Log::info('Starting hotel details sync job', [
                'hotel_id' => $this->hotelId
            ]);
           
            $hotel = MapHotel::find($this->hotelId);
            
            if (!$hotel) {
                Log::error('Hotel not found', ['hotel_id' => $this->hotelId]);
                return;
            }
            
            // Sync hotel images
            $this->syncHotelImages($mappingApi, $hotel);
            
            // Sync hotel descriptions
            $this->syncHotelDescriptions($mappingApi, $hotel);
            
            // Sync hotel facilities/categories
            // $this->syncHotelCategories($mappingApi, $hotel);
            
            Log::info('Hotel details sync completed', [
                'hotel_id' => $this->hotelId
            ]);
        } catch (\Exception $e) {
            Log::error('Hotel details sync failed', [
                'hotel_id' => $this->hotelId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    protected function syncHotelImages(MagicHolidayService $mappingApi, MapHotel $hotel)
    {
        $response = $mappingApi->getHotelImages($hotel->id);
        
        if (!isset($response['_embedded']['image'])) {
            Log::warning('No images found for hotel', ['hotel_id' => $hotel->id]);
            return;
        }
        
        DB::beginTransaction();
        try {
            // Delete existing images if they're not in the new set
            $newImageIds = collect($response['_embedded']['gallery'])->pluck('id')->toArray();
            MapHotelImage::where('hotel_id', $hotel->id)
                ->whereNotIn('id', $newImageIds)
                ->delete();
            
            foreach ($response['_embedded']['gallery'] as $imageData) {
                MapHotelImage::updateOrCreate(
                    [
                        'id' => $imageData['id'],
                        'hotel_id' => $hotel->id
                    ],
                    [
                        'url' => $imageData['url'],
                        'source' => $imageData['source'] ?? null,
                        'is_main' => $imageData['isMain'] ?? false,
                        'order' => $imageData['order'] ?? 0,
                    ]
                );
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    protected function syncHotelDescriptions(MagicHolidayService $mappingApi, MapHotel $hotel)
    {
        // Get descriptions in multiple languages
        $languages = ['en', 'fr', 'es', 'de']; // Add more as needed
        
        foreach ($languages as $language) {
            $response = $mappingApi->getHotelDescriptions($hotel->id, $language);
            
            if (!isset($response['_embedded']['description'])) {
                Log::warning('No descriptions found for hotel in language', [
                    'hotel_id' => $hotel->id,
                    'language' => $language
                ]);
                continue;
            }
            
            DB::beginTransaction();
            try {
                foreach ($response['_embedded']['description'] as $descriptionData) {
                    MapHotelDescription::updateOrCreate(
                        [
                            'hotel_id' => $hotel->id,
                            'language' => $language,
                            'type' => $descriptionData['type']
                        ],
                        [
                            'content' => $descriptionData['content'],
                        ]
                    );
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }
    }
    
    // protected function syncHotelCategories(MagicHolidayService $mappingApi, MapHotel $hotel)
    // {
    //     $response = $mappingApi->getHotelCategories($hotel->id);
        
    //     if (!isset($response['_embedded']['category'])) {
    //         Log::warning('No categories found for hotel', ['hotel_id' => $hotel->id]);
    //         return;
    //     }
        
    //     DB::beginTransaction();
    //     try {
    //         // Sync categories using Laravel's sync method
    //         $categoryIds = collect($response['_embedded']['category'])->pluck('id')->toArray();
    //         $hotel->categories()->sync($categoryIds);
    //         DB::commit();
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }
    // }

    public function failed(\Throwable $exception)
    {
        Log::error('Hotel details sync job failed', [
            'hotel_id' => $this->hotelId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}