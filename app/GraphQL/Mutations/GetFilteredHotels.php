<?php

namespace App\GraphQL\Mutations;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\MapCity;
use App\Models\MapHotel;
use App\Services\MagicHolidayService;
use Exception;

class GetFilteredHotels
{

    protected $logger;

    public function __construct()
    {
        $this->logger = Log::channel('magic_holidays');
    }

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
            'classification' => 'nullable|string',
            'occupancy' => 'required|array',
            'occupancy.rooms' => 'required|string',
        ]);

        if ($validator->fails()) {
            $message = 'Validation failed: ' . $validator->errors()->first();
            $this->logger->warning('GetHotelsStarFiltered: validation failed', [
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

            $this->logger->info("Before decode classification", ['classification' => $input['filters']['classification'] ?? null]);

            if (isset($input['filters']['classification'])) {
                if (is_string($input['filters']['classification'])) {
                    $classificationArray = array_map('intval', array_map('trim', explode(',', $input['filters']['classification'])));
                } elseif (is_array($input['filters']['classification'])) {
                    $classificationArray = array_map('intval', $input['filters']['classification']);
                } else {
                    $classificationArray = [(int)$input['filters']['classification']];
                }
            }

            $this->logger->info("After decode classification", ['classification' => $classificationArray ?? null]);


            $this->logger->info("Before decode occupancy rooms", ['rooms' => $input['occupancy']['rooms'] ?? null]);
            $rooms = [];
            $leaderNationality = $input['occupancy']['leaderNationality'] ?? 1;

            if (!empty($input['occupancy']['rooms'])) {
                $roomsString = $input['occupancy']['rooms'];
                $rooms = $this->parseRoomsString($roomsString);
            }

            $this->logger->info("After decode occupancy rooms", ['rooms' => $rooms]);

            if (empty($rooms)) {
                $rooms = [
                    ['adults' => 2, 'childrenAges' => []]
                ];
            }

            $occupancyPayload = [
                'leaderNationality' => $leaderNationality,
                'rooms' => $rooms
            ];

            $payload = [
                'destination' => $input['destination'],
                'checkIn' => $input['checkin'],
                'checkOut' => $input['checkout'],
                'occupancy' => $occupancyPayload,
                'sellingChannel' => $input['sellingChannel'] ?? 'B2B',
                'availableOnly' => true,
                'language' => $input['language'] ?? 'en_GB',
                'timeout' => $input['timeout'] ?? 20,
                'providers' => $input['providers'] ?? ['expediarapid'],
                'filters' => [
                    'classification' => $classificationArray ?? [],
                    'name' => $input['filters']['name'] ?? null,
                    'minPrice' => $input['filters']['minPrice'] ?? null,
                    'maxPrice' => $input['filters']['maxPrice'] ?? null,
                ],
            ];

            $searchResponse = $magicService->startHotelSearch($payload);

            $srk = $searchResponse['data']['srk'] ?? null;
            $progressToken = $searchResponse['data']['tokens']['progress'];
            $resultsToken = $searchResponse['data']['tokens']['results'] ?? null;

            if (!$srk || !$resultsToken) {
                return [
                    'success' => false,
                    'message' => 'Missing SRK or Results Token from MagicHoliday response',
                    'raw_response' => $searchResponse,
                ];
            }

            $this->pollSearchProgress($magicService, $progressToken);

            $hotelResults = $magicService->getSearchResults($srk, $resultsToken, [
                'includeHotelDetails' => 1,
                'priceRange[min]' => $input['filters']['minPrice']['value'] ?? null,
                'priceRange[max]' => $input['filters']['maxPrice']['value'] ?? null,
                'priceRange[currency]' => $input['filters']['minPrice']['currency'] ?? 'KWD',
            ]);

            $hotels = $hotelResults['data']['hotels'] ?? [];

            if (empty($hotels)) {
                return [
                    'success' => false,
                    'message' => 'No hotels found matching your filters. Try adjusting your price range, dates, or star rating.',
                    'hotels' => [],
                ];
            }

            return [
                'success' => true,
                'message' => 'Filtered hotels fetched successfully',
                'hotels' => array_map(fn($hotel) => [
                    'index' => $hotel['index'] ?? null,
                    'name' => $hotel['name'] ?? null,
                    'address' => $hotel['details']['address'] ?? null,
                    'stars' => $hotel['stars'] ?? null,
                    'specialDeal' => $hotel['specialDeal'] ?? false,
                    'price' => $hotel['minPrice'] ?? null,
                ], $hotels)
            ];
        } catch (\Exception $e) {
            $this->logger->error('GetHotelsStarFiltered: exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error fetching filtered hotels: ' . $e->getMessage(),
                'hotels' => [],
            ];
        }
    }

    public function pollSearchProgress(MagicHolidayService $magicService, string $progressToken, int $maxAttempts = 60, int $delaySeconds = 2): array
    {
        $this->logger->info('Polling search progress', ['progress_token' => substr($progressToken, 0, 20) . '...']);

        $attempts = 0;
        while ($attempts < $maxAttempts) {
            $response = $magicService->checkSearchProgress($progressToken);

            $status = $response['data']['status'] ?? null;

            $this->logger->info('Search progress', [
                'progress_token' => substr($progressToken, 0, 20) . '...',
                'attempt' => $attempts + 1,
                'status' => $status
            ]);

            if ($status === 'COMPLETED') {
                return $response;
            }

            if ($status === 'FAILED' || $status === 'ERROR') {
                throw new Exception('Hotel search failed with status: ' . $status);
            }

            sleep($delaySeconds);
            $attempts++;
        }

        throw new Exception('Hotel search timeout after ' . $maxAttempts . ' attempts');
    }

    protected function parseRoomsString(string $roomsString): array
    {
        $this->logger->info('Parsing rooms string', ['raw_string' => $roomsString]);

        $normalizedString = str_replace("'", '"', $roomsString);

        $decoded = json_decode($normalizedString, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['adults'])) {
                $this->logger->info('Rooms string decoded successfully', ['decoded' => $decoded]);
                return $decoded;
            }
        }

        $normalizedString = trim($normalizedString);

        if (preg_match('/^\[(.*)\]$/', $normalizedString, $matches)) {
            $inner = $matches[1];
            $parts = preg_split('/\]\s*,\s*\[/', $inner);

            $rooms = [];
            foreach ($parts as $part) {
                $part = trim($part);

                if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                    $room = json_decode($part, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($room)) {
                        $rooms[] = $room;
                    }
                }
            }

            if (!empty($rooms)) {
                $this->logger->info('Rooms string parsed successfully', ['parsed' => $rooms]);
                return $rooms;
            }
        }

        $this->logger->error('Failed to parse rooms string', [
            'raw_string' => $roomsString,
            'normalized_string' => $normalizedString,
            'json_error' => json_last_error_msg()
        ]);

        return [];
    }
}
