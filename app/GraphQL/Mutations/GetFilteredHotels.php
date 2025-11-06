<?php

namespace App\GraphQL\Mutations;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\MapCity;
use App\Services\MagicHolidayService;

class GetFilteredHotels
{
    public function __invoke($_, array $args)
    {
        $input = $args['input'] ?? [];

        $cityName = $input['destination']['city']['name'] ?? null;
        $cityId = $input['destination']['city']['id'] ?? null;

        if (!$cityId && $cityName) {
            $cityRecord = MapCity::where('name', 'like', '%' . $cityName . '%')->first();

            if ($cityRecord) {
                $input['destination']['city']['id'] = $cityRecord->id;
                $cityId = $cityRecord->id;
            } else {
                return [
                    'success' => false,
                    'message' => "City '{$cityName}' not found in database",
                    'hotels' => [],
                ];
            }
        }

        $validator = Validator::make($input, [
            'destination.city.id' => 'required|integer',
            'checkin' => 'required|date',
            'checkout' => 'required|date|after:checkin',
        ]);

        if ($validator->fails()) {
            $message = 'Validation failed: ' . $validator->errors()->first();
            Log::warning('GetHotelsStarFiltered: validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return [
                'success' => false,
                'message' => $message,
                'hotels' => [],
            ];
        }

        try {
            $magicService = new MagicHolidayService();

            $payload = [
                'destination' => $input['destination'],
                'checkIn' => $input['checkin'],
                'checkOut' => $input['checkout'],
                'occupancy' => $input['occupancy'] ?? [],
                'sellingChannel' => $input['sellingChannel'] ?? 'B2B',
                'language' => $input['language'] ?? 'en_GB',
                'timeout' => $input['timeout'] ?? 20,
                'providers' => $input['providers'] ?? ['expediarapid'],
                'filters' => $input['filters'] ?? [],
            ];

            $searchResponse = $magicService->findByCity($payload);

            $srk = $searchResponse['data']['srk'] ?? null;
            $resultsToken = $searchResponse['data']['tokens']['results'] ?? null;

            if (!$srk || !$resultsToken) {
                return [
                    'success' => false,
                    'message' => 'Missing SRK or Results Token from MagicHoliday response',
                    'raw_response' => $searchResponse,
                ];
            }

            sleep(10);

            $hotelResults = $magicService->getSearchResults($srk, $resultsToken);

            $filteredHotels = [];

            $minPrice = $payload['filters']['minPrice']['value'];
            $maxPrice = $payload['filters']['maxPrice']['value'];

            foreach ($hotelResults['data']['hotels'] ?? [] as $hotel) {
                $starsFilter = $payload['filters']['classification'] ?? [];
                $nameFilter = strtolower($payload['filters']['name'] ?? '');
                $hotelPrice = $hotel['minPrice']['value'];

                $hotelStars = $hotel['stars'] ?? null;
                $hotelName = strtolower($hotel['name'] ?? '');

                $starsMatch = empty($starsFilter) || in_array($hotelStars, $starsFilter);
                $nameMatch = empty($nameFilter) || str_contains($hotelName, $nameFilter);
                $priceMatch = (
                    (is_null($minPrice) || $hotelPrice >= $minPrice) &&
                    (is_null($maxPrice) || $hotelPrice <= $maxPrice)

                );

                if ($starsMatch && $nameMatch && $priceMatch) {
                    $filteredHotels[] = [
                        'index' => $hotel['index'] ?? null,
                        'name' => $hotel['name'] ?? null,
                        'stars' => $hotel['stars'] ?? null,
                        'recommended' => $hotel['recommended'] ?? null,
                        'specialDeal' => $hotel['specialDeal'] ?? null,
                        'price' => [
                                        'value' => $hotelPrice ?? null,
                                        'currency' => $hotel['minPrice']['currency'] ?? null,
                                   ],
                        'rateTags' => $hotel['rateTags'] ?? null,
                    ];
                    
                }
            }

            return [
                'success' => true,
                'message' => 'Filtered hotels fetched successfully',
                'hotels' => $filteredHotels,
            ];
        } catch (\Exception $e) {
            Log::error('GetHotelsStarFiltered: exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error fetching filtered hotels: ' . $e->getMessage(),
                'hotels' => [],
            ];
        }
    }
}
