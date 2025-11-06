<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\MapHotel;
use App\Models\TemporaryOffer;
use App\Models\OfferedRoom;
use App\Models\Prebooking;
use App\Models\RequestBookingRoom;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class HotelSearchService
{
    protected $logger;

    public function __construct()
    {
        $this->logger = Log::channel('magic_holidays');
    }

    public function findCompanyIdByPhone(string $telephone): ?int
    {
        $agent = Agent::where('phone_number', $telephone)
            ->orWhere(DB::raw("CONCAT(country_code, phone_number)"), $telephone)
            ->first();

        if (!$agent) {
            $this->logger->info('No agent found for phone number, using default credentials', [
                'telephone' => $telephone
            ]);
            return null;
        }

        $companyId = $agent->branch?->company_id;

        if (!$companyId) {
            $this->logger->warning('Agent found but no company linked, using default credentials', [
                'agent_id' => $agent->id,
                'telephone' => $telephone
            ]);
            return null;
        }

        $this->logger->info('Found company for phone number', [
            'telephone' => $telephone,
            'agent_id' => $agent->id,
            'company_id' => $companyId
        ]);

        return $companyId;
    }

    public function findHotelByName(string $hotelName, ?string $cityName = null): ?array
    {
        $normalizedHotelName = strtolower(str_replace(',', '', trim($hotelName)));

        if (empty($normalizedHotelName)) {
            $this->logger->warning('Empty hotel name provided');
            return null;
        }

        $query = MapHotel::with('city')
            ->whereRaw('LOWER(REPLACE(name, ",", "")) = ?', [$normalizedHotelName]);

        if ($cityName) {
            $query->whereHas('city', function ($q) use ($cityName) {
                $q->whereRaw('LOWER(name) = ?', [strtolower(trim($cityName))]);
            });
        }

        $hotels = $query->get();

        if ($hotels->isEmpty()) {
            $this->logger->warning('No hotels found with exact match', [
                'hotel_name' => $hotelName,
                'city_name' => $cityName
            ]);
            return null;
        }

        if ($hotels->count() > 1) {
            $this->logger->info('Multiple hotels found, using first match', [
                'hotel_name' => $hotelName,
                'city_name' => $cityName,
                'matches_count' => $hotels->count(),
                'matches' => $hotels->map(fn($h) => [
                    'hotel' => $h->name,
                    'city' => $h->city?->name
                ])->toArray()
            ]);
        }

        $hotel = $hotels->first();

        $this->logger->info('Hotel found', [
            'search_term' => $hotelName,
            'city_search_term' => $cityName,
            'matched_hotel' => $hotel->name,
            'matched_city' => $hotel->city?->name
        ]);

        return [
            'hotel_id' => $hotel->id,
            'hotel_name' => $hotel->name,
            'city_id' => $hotel->city?->id,
            'city_name' => $hotel->city?->name,
        ];
    }

    public function saveBookingRequest(string $telephone, array $bookingData): void
    {
        $normalizedHotelName = trim(str_replace(',', '', $bookingData['hotel_name']));

        $existing = RequestBookingRoom::where('phone_number', $telephone)
            ->whereRaw('REPLACE(hotel, ",", "") = ?', [$normalizedHotelName])
            ->first();

        $newData = [
            'hotel' => $normalizedHotelName,
            'city_id' => $bookingData['city_id'],
            'city' => $bookingData['city_name'],
            'check_in' => $bookingData['check_in'],
            'check_out' => $bookingData['check_out'],
            'occupancy' => $bookingData['occupancy'],
        ];

        if (!$existing) {
            RequestBookingRoom::create(array_merge(['phone_number' => $telephone], $newData));
            $this->logger->info('Created new booking request', [
                'telephone' => $telephone,
                'hotel' => $normalizedHotelName
            ]);
        } elseif ($existing->only(array_keys($newData)) != $newData) {
            $existing->update($newData);
            $this->logger->info('Updated booking request', [
                'telephone' => $telephone,
                'hotel' => $normalizedHotelName
            ]);
        }
    }

    public function startSearch(MagicHolidayService $magicService, array $searchParams): array
    {
        $this->logger->info('Starting hotel search', $searchParams);

        $response = $magicService->startHotelSearch($searchParams);

        if (!isset($response['data']['srk'])) {
            throw new Exception('Failed to start hotel search: ' . json_encode($response));
        }

        // Extract all tokens from response
        return [
            'srk' => $response['data']['srk'],
            'enquiry_id' => $response['data']['enquiry_id'] ?? null,
            'progress_token' => $response['data']['tokens']['progress'] ?? null,
            'async_token' => $response['data']['tokens']['async'] ?? null,
            'results_token' => $response['data']['tokens']['results'] ?? null,
        ];
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

    public function getSearchSummary(MagicHolidayService $magicService, string $progressToken): array
    {
        $this->logger->info('Getting search summary', ['progress_token' => substr($progressToken, 0, 20) . '...']);

        $response = $magicService->getSearchSummary($progressToken);

        if (!isset($response['data'])) {
            throw new Exception('Failed to get search summary');
        }

        return $response['data'];
    }

    public function saveOffersAndGetCheapest(string $telephone, string $srk, string $resultsToken, ?string $enquiryId, array $summaryData, ?bool $nonRefundable = null, ?string $boardBasis = null): ?array
    {
        $this->logger->info('Processing summary data', [
            'telephone' => $telephone,
            'srk' => $srk,
            'results_token' => substr($resultsToken, 0, 20) . '...',
            'enquiry_id' => $enquiryId,
            'summary_data' => $summaryData,
        ]);

        $allRooms = [];

        foreach ($summaryData['hotels'] ?? [] as $hotel) {
            $hotelIndex = $hotel['id'] ?? $hotel['index'] ?? $hotel['hotelIndex'] ?? null;
            $hotelName = $hotel['name'] ?? $hotel['hotelName'] ?? 'Unknown';

            if (!$hotelIndex) {
                $this->logger->warning('Hotel missing id/index', ['hotel' => $hotel]);
                continue;
            }

            $this->logger->info('Processing hotel from results', [
                'hotel_index' => $hotelIndex,
                'hotel_name' => $hotelName,
                'hotel_data' => $hotel,
            ]);

            foreach ($hotel['offers'] ?? [] as $offer) {
                $offerIndex = $offer['index'] ?? $offer['id'] ?? $offer['offerIndex'] ?? null;

                if (!$offerIndex) {
                    $this->logger->warning('Offer missing index', ['offer' => $offer]);
                    continue;
                }

                $tempOffer = TemporaryOffer::create([
                    'telephone' => $telephone,
                    'srk' => $srk,
                    'hotel_index' => $hotelIndex,
                    'hotel_name' => $hotelName,
                    'offer_index' => $offerIndex,
                    'result_token' => $resultsToken,
                    'enquiry_id' => $enquiryId ?? '',
                ]);

                $roomModels = [];
                $roomsMap = [];
                foreach ($offer['rooms'] ?? [] as $room) {
                    $roomIndex = $room['index'] ?? $room['roomIndex'] ?? null;
                    if ($roomIndex !== null) {
                        $roomsMap[$roomIndex] = $room;
                    }
                }

                // Process packages (which contain the actual booking tokens)
                foreach ($offer['packages'] ?? [] as $package) {
                    $packageToken = $package['packageToken'] ?? null;
                    $packagePrice = $package['price']['selling']['value']
                        ?? $package['price']['value']
                        ?? 0;
                    $packageCurrency = $package['price']['selling']['currency']
                        ?? $package['price']['currency']
                        ?? 'KWD';

                    if (!$packageToken || $packagePrice <= 0) {
                        $this->logger->warning('Package missing token or price', ['package' => $package]);
                        continue;
                    }

                    foreach ($package['packageRooms'] ?? [] as $packageRoom) {
                        $occupancyData = $packageRoom['occupancy'] ?? [];
                        $occupancyString = json_encode($occupancyData);

                        foreach ($packageRoom['roomReferences'] ?? [] as $roomRef) {
                            $roomCode = $roomRef['roomCode'] ?? null;
                            $roomToken = $roomRef['roomToken'] ?? null;

                            if (!$roomCode || !isset($roomsMap[$roomCode])) continue;

                            $roomData = $roomsMap[$roomCode];

                            $offeredRoom = OfferedRoom::create([
                                'temp_offer_id' => $tempOffer->id,
                                'room_name' => $roomData['name'] ?? $roomData['roomName'] ?? 'Unknown',
                                'board_basis' => $roomData['boardBasis'] ?? $roomData['board_basis'] ?? '',
                                'non_refundable' => $roomData['nonRefundable'] ?? $roomData['non_refundable'] ?? false,
                                'info' => $roomData['info'] ?? '',
                                'occupancy' => $occupancyString,
                                'price' => $packagePrice,
                                'currency' => $packageCurrency,
                                'room_token' => $roomToken,
                                'package_token' => $packageToken,
                            ]);

                            $roomModels[] = $offeredRoom;
                        }
                    }
                }

                $this->logger->info('Created TemporaryOffer with multiple rooms', [
                    'offer_index' => $offerIndex,
                    'room_count' => count($roomModels),
                ]);

                foreach ($roomModels as $room) {
                    $allRooms[] = [
                        'room' => $room,
                        'temp_offer' => $tempOffer,
                        'price' => $room->price,
                    ];
                }
            }
        }

        if (empty($allRooms)) {
            $this->logger->warning('No valid rooms found', ['telephone' => $telephone]);
            return null;
        }

        $allRooms = collect($allRooms)
            ->filter(function ($item) use ($nonRefundable, $boardBasis) {
                $room = $item['room'];
                $matches = true;

                if (!is_null($nonRefundable)) {
                    $matches = $matches && ((bool)$room->non_refundable === (bool)$nonRefundable);
                }

                if (!empty($boardBasis)) {
                    $matches = $matches && (strtoupper(trim($room->board_basis)) === strtoupper(trim($boardBasis)));
                }

                return $matches;
            })
            ->values()
            ->all();

        if (empty($allRooms)) {
            $this->logger->warning('No rooms matched filter criteria', [
                'telephone' => $telephone,
            ]);
            return null;
        }

        $sortedRooms = collect($allRooms)->sortBy('price')->values();

        return $sortedRooms->map(function ($item) {
            return [
                'offered_room' => $item['room'],
                'temp_offer'   => $item['temp_offer'],
            ];
        })->values()->all();
    }

    public function getCheapestFromDatabase(string $telephone, ?bool $nonRefundable = null, ?string $boardBasis = null): ?array
    {
        $this->logger->info('Finding sorted rooms from cached database offers', [
            'telephone' => $telephone,
            'non_refundable' => $nonRefundable,
            'board_basis' => $boardBasis,
        ]);

        $filteredRooms = OfferedRoom::whereHas('temporaryOffer', function ($q) use ($telephone) {
            $q->where('telephone', $telephone);
        })
            ->when(!is_null($nonRefundable), fn($q) => $q->where('non_refundable', $nonRefundable ? 1 : 0))
            ->when(!empty($boardBasis), fn($q) => $q->whereRaw('UPPER(TRIM(board_basis)) = ?', [strtoupper(trim($boardBasis))]))
            ->orderBy('price')
            ->get();

        if ($filteredRooms->isEmpty()) {
            $this->logger->warning('No matching cached rooms found for criteria', [
                'telephone' => $telephone,
            ]);
            return null;
        }

        $this->logger->info('Sorted cached rooms by price', [
            'telephone' => $telephone,
            'total_rooms' => $filteredRooms->count(),
            'cheapest_price' => $filteredRooms->first()->price,
        ]);

        return $filteredRooms->map(function ($room) {
            return [
                'offered_room' => $room,
                'temp_offer' => $room->temporaryOffer,
            ];
        })->values()->all();
    }

    public function prebookOffer(MagicHolidayService $magicService, array $prebookData): array
    {
        $this->logger->info('Pre-booking offer', [
            'srk' => $prebookData['srk'],
            'hotel_id' => $prebookData['hotel_id'],
            'offer_index' => $prebookData['offer_index']
        ]);

        try {
            $response = $magicService->prebookHotel(
                $prebookData['srk'],
                $prebookData['hotel_id'],
                $prebookData['offer_index'],
                $prebookData['packageToken'],
                $prebookData['roomTokens'],
                $prebookData['results_token']
            );

            if (isset($response['status']) && $response['status'] >= 400) {
                $detail = $response['data']['detail'] ?? $response['body'] ?? 'Unknown error from Magic Holiday';
                throw new Exception($detail);
            }

            if (!isset($response['data'])) {
                throw new Exception('Failed to prebook offer: ' . json_encode($response));
            }

            return $response['data'];
        } catch (Exception $e) {
            $this->logger->error('Prebook failed', [
                'error' => $e->getMessage(),
                'data' => $prebookData
            ]);

            throw new Exception('Prebooking failed: ' . $e->getMessage());
        }
    }

    public function storePrebook(array $data): array
    {
        $this->logger->info('StorePrebook: Incoming request', $data);

        try {
            $prebookKey = 'PB-' . substr(uniqid(), -5);

            $prebook = Prebooking::create([
                'prebook_key'  => $prebookKey,
                'telephone' => $data['telephone'],
                'availability_token' => $data['availability_token'],
                'srk' => $data['srk'],
                'package_token' => $data['package_token'],
                'hotel_id' => $data['hotel_id'],
                'offer_index' => $data['offer_index'],
                'result_token' => $data['result_token'],
                'rooms' => $data['rooms'],
                'checkin' => $data['checkin'],
                'checkout' => $data['checkout'],
                'duration' => $data['duration'] ?? null,
                'autocancel_date' => $data['autocancel_date'] ?? null,
                'cancel_policy' => isset($data['cancel_policy']) ? json_encode($data['cancel_policy']) : null,
                'remarks' => isset($data['remarks']) ? json_encode($data['remarks']) : null,
                'service_dates' => $data['service_dates'] ?? [],
                'package' => $data['package'] ?? [],
                'payment_methods' => $data['payment_methods'] ?? [],
                'booking_options' => $data['booking_options'] ?? [],
                'price_breakdown' => $data['price_breakdown'] ?? [],
                'taxes' => $data['taxes'] ?? [],
            ]);

            $response = [
                'success' => true,
                'prebook_key' => $prebookKey,
                'prebooking_id' => $prebook->id,
                'message' => 'Prebook record successfully created.',
            ];

            $this->logger->info('StorePrebook: Successfully saved prebook', $response);

            return $response;
        } catch (Exception $e) {
            $this->logger->error('StorePrebook: Failed to save prebook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while saving prebook data: ' . $e->getMessage(),
            ];
        }
    }

    protected function clearPreviousOffers(string $telephone): void
    {
        $this->logger->info('Clearing previous offers before new search', ['telephone' => $telephone]);

        $offerIds = TemporaryOffer::where('telephone', $telephone)->pluck('id');

        if ($offerIds->isNotEmpty()) {
            OfferedRoom::whereIn('temp_offer_id', $offerIds)->delete();
            TemporaryOffer::whereIn('id', $offerIds)->delete();

            $this->logger->info('Cleared previous offers successfully', [
                'telephone' => $telephone,
                'deleted_offer_count' => $offerIds->count()
            ]);
        } else {
            $this->logger->info('No previous offers found to clear', ['telephone' => $telephone]);
        }
    }

    public function searchHotelRooms(
        string $telephone,
        string $hotelName,
        string $checkIn,
        string $checkOut,
        array $occupancy,
        ?string $cityName = null,
        int $roomCount = 1,
        ?bool $nonRefundable = null,
        ?string $boardBasis = null
    ): array {
        try {
            $this->logger->info('Starting hotel room search flow', [
                'telephone' => $telephone,
                'hotel' => $hotelName,
                'city' => $cityName,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'room_count_requested' => $roomCount,
                'occupancy_rooms_count' => count($occupancy['rooms'] ?? []),
            ]);

            $checkIn = date('Y-m-d', strtotime($checkIn));
            $checkOut = date('Y-m-d', strtotime($checkOut));

            $companyId = $this->findCompanyIdByPhone($telephone);

            $hotelData = $this->findHotelByName($hotelName, $cityName);
            if (!$hotelData) {
                $message = $cityName
                    ? 'Hotel not found with the provided name and city.'
                    : 'Hotel not found with the provided name.';
                return [
                    'success' => false,
                    'message' => $message
                ];
            }
            $isReused = false;
            $normalizedHotelName = trim(str_replace(',', '', $hotelName));

            $existingRequest = RequestBookingRoom::where('phone_number', $telephone)
                ->whereRaw('REPLACE(hotel, ",", "") = ?', [$normalizedHotelName])
                ->whereDate('check_in', date('Y-m-d', strtotime($checkIn)))
                ->whereDate('check_out', date('Y-m-d', strtotime($checkOut)))
                ->first();

            $shouldFetchNew = false;

            if ($existingRequest) {
                $existingOccupancy = is_array($existingRequest->occupancy) ? $existingRequest->occupancy : json_decode($existingRequest->occupancy ?? '[]', true);
                $sameOccupancy = json_encode($existingOccupancy) === json_encode($occupancy);

                $latestOffer = TemporaryOffer::where('telephone', $telephone)
                    ->where('hotel_name', $normalizedHotelName)
                    ->latest('updated_at')
                    ->first();

                $offerIsFresh = $latestOffer && $latestOffer->updated_at >= now()->subMinutes(30);

                if (
                    strcasecmp(trim($existingRequest->hotel), $normalizedHotelName) !== 0 ||
                    !$sameOccupancy ||
                    !$offerIsFresh
                ) {
                    $shouldFetchNew = true;
                }

                if (!$shouldFetchNew) {
                    $isReused = true;
                    $this->logger->info('Reusing cached offers (hotel, occupancy, and offers are fresh)', [
                        'telephone' => $telephone,
                        'hotel' => $normalizedHotelName,
                    ]);
                } else {
                    $this->logger->info('Fetching new offers — reason:', [
                        'hotel_changed' => strcasecmp(trim($existingRequest->hotel), $normalizedHotelName) !== 0,
                        'occupancy_changed' => !$sameOccupancy,
                        'offers_expired' => !$offerIsFresh,
                    ]);
                }
            } else {
                $shouldFetchNew = true;
                $this->logger->info('No existing booking request found, will fetch new offers.', [
                    'telephone' => $telephone,
                    'hotel' => $normalizedHotelName,
                ]);
            }

            if ($shouldFetchNew) {
                $this->clearPreviousOffers($telephone);
            }

            $magicService = new MagicHolidayService($companyId);
            $srk = null;
            $enquiryId = null;
            $resultsToken = null;
            $allOffers = [];

            if (!$isReused) {
                $this->logger->info('No cached offers found — fetching from Magic Holiday API');

                $searchParams = [
                    'destination' => [
                        'city' => [
                            'id' => $hotelData['city_id'],
                        ]
                    ],
                    'checkIn' => $checkIn,
                    'checkOut' => $checkOut,
                    'occupancy' => $occupancy,
                    'filters' => [
                        'name' => $hotelData['hotel_name'],
                    ],
                    'language' => 'en_GB',
                    'timeout' => 30,
                    'sellingChannel' => 'B2C',
                    'availableOnly' => true,
                ];

                $searchResult = $this->startSearch($magicService, $searchParams);
                $srk = $searchResult['srk'];
                $enquiryId = $searchResult['enquiry_id'];
                $progressToken = $searchResult['progress_token'];
                $resultsToken = $searchResult['results_token'];

                $this->pollSearchProgress($magicService, $progressToken);

                $summary = $this->getSearchSummary($magicService, $progressToken);
                if (empty($summary['hotels']) || ($summary['count'] ?? 0) === 0) {
                    $this->logger->warning('No hotels found in search results', [
                        'telephone' => $telephone,
                        'hotel' => $hotelName,
                        'summary' => $summary
                    ]);

                    return [
                        'success' => false,
                        'message' => 'No available hotels or rooms found for the specified dates and hotel name.'
                    ];
                }

                $this->logger->info('Fetching detailed search results', ['srk' => $srk]);
                $resultsResponse = $magicService->getSearchResults($srk, $resultsToken);

                if (!isset($resultsResponse['data']['hotels'])) {
                    $this->logger->error('Failed to get detailed results', ['response' => $resultsResponse]);
                    return [
                        'success' => false,
                        'message' => 'Failed to retrieve detailed hotel offers.'
                    ];
                }

                $hotels = $resultsResponse['data']['hotels'];
                if (empty($hotels)) {
                    $this->logger->warning('No hotels in results response', ['srk' => $srk]);
                    return [
                        'success' => false,
                        'message' => 'No hotels found in search results.'
                    ];
                }

                $this->logger->info('Processing hotels and fetching offers', [
                    'hotel_count' => count($hotels),
                    'srk' => $srk
                ]);

                foreach ($hotels as $hotel) {
                    $hotelIndex = $hotel['id'] ?? $hotel['index'] ?? null;

                    if (!$hotelIndex) {
                        $this->logger->warning('Hotel missing id/index', ['hotel' => $hotel]);
                        continue;
                    }

                    $this->logger->info('Fetching offers for hotel', [
                        'hotel_index' => $hotelIndex,
                        'hotel_name' => $hotel['name'] ?? 'Unknown'
                    ]);

                    $offersResponse = $magicService->getHotelOffers($srk, $hotelIndex, $resultsToken);

                    if (isset($offersResponse['data']['offers'])) {
                        $hotel['offers'] = $offersResponse['data']['offers'];
                        $allOffers[] = $hotel;
                    } else {
                        $this->logger->warning('No offers found for hotel', [
                            'hotel_index' => $hotelIndex,
                            'response' => $offersResponse
                        ]);
                    }
                }

                if (empty($allOffers)) {
                    $this->logger->warning('No offers found for any hotel', ['srk' => $srk]);
                    return [
                        'success' => false,
                        'message' => 'No available rooms found for the specified dates.'
                    ];
                }

                $this->saveBookingRequest($telephone, [
                    'hotel_name' => $hotelData['hotel_name'],
                    'city_id' => $hotelData['city_id'],
                    'city_name' => $hotelData['city_name'],
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'occupancy' => $occupancy
                ]);
            }

            // Get all sorted cheapest rooms from API or DB
            $allCheapestData = $isReused
                ? $this->getCheapestFromDatabase($telephone, $nonRefundable, $boardBasis)
                : $this->saveOffersAndGetCheapest($telephone, $srk, $resultsToken, $enquiryId, ['hotels' => $allOffers], $nonRefundable, $boardBasis);

            if (empty($allCheapestData)) {
                return ['success' => false, 'message' => 'No rooms are currently available for the selected criteria. Please adjust your search and try again.'];
            }

            $roomsGroupedByPackage = collect($allCheapestData)
                ->groupBy(fn($item) => $item['offered_room']->package_token);

            $validPackages = [];
            $requiredOccupancies = $occupancy['rooms'] ?? [];

            foreach ($roomsGroupedByPackage as $packageToken => $roomsInPackage) {
                $roomsInPackage = $roomsInPackage->sortBy(fn($item) => $item['offered_room']->price);
                $currentPackageRooms = [];
                $availableRooms = $roomsInPackage->toArray();

                $isPackageValid = true;

                foreach ($requiredOccupancies as $requiredOcc) {
                    $matchIndex = -1;

                    foreach ($availableRooms as $index => $item) {
                        $roomOcc = json_decode($item['offered_room']->occupancy, true);
                        $sameAdults = ($roomOcc['adults'] ?? 0) == ($requiredOcc['adults'] ?? 0);
                        $sameChildrenCount = count($roomOcc['childrenAges'] ?? []) == count($requiredOcc['childrenAges'] ?? []);

                        if ($sameAdults && $sameChildrenCount) {
                            $matchIndex = $index;
                            break;
                        }
                    }

                    if ($matchIndex !== -1) {
                        $match = $availableRooms[$matchIndex];
                        $currentPackageRooms[] = $match;
                        unset($availableRooms[$matchIndex]);
                        $availableRooms = array_values($availableRooms);
                    } else {
                        $isPackageValid = false;
                        break;
                    }
                }

                if ($isPackageValid && count($currentPackageRooms) === count($requiredOccupancies)) {
                    $validPackages[] = [
                        'rooms' => $currentPackageRooms,
                        'total_price' => array_sum(array_map(fn($r) => (float)$r['offered_room']->price, $currentPackageRooms)),
                    ];
                }
            }

            if (empty($validPackages)) {
                return ['success' => false, 'message' => 'No packages found matching all required occupancies.'];
            }

            // Sort packages by total price (cheapest first)
            usort($validPackages, fn($a, $b) => $a['total_price'] <=> $b['total_price']);

            // Now pick as many packages as requested roomCount
            $selectedPackages = array_slice($validPackages, 0, $roomCount);
            $selectedRooms = collect($selectedPackages)->flatMap(fn($pkg) => $pkg['rooms'])->values()->all();

            $groupedByPackage = collect($selectedRooms)
                ->groupBy(function ($item) {
                    $tempOffer = $item['temp_offer'];
                    $offeredRoom = $item['offered_room'];
                    return $tempOffer->offer_index . '-' . $offeredRoom->package_token;
                });

            $finalRoomsData = [];

            foreach ($groupedByPackage as $group) {
                $first = $group->first();
                $tempOffer = $first['temp_offer'];
                $packageToken = $first['offered_room']->package_token;

                // Sort by cheapest and pick unique rooms (cheapest -> next cheapest)
                $allAvailableRooms = $group->sortBy(fn($r) => $r['offered_room']->price)
                    ->values()
                    ->unique(fn($r) => $r['offered_room']->room_token)
                    ->toArray();

                // Use only as many as needed for prebooks (first cheapest sets)
                $availableRoomsPool = array_slice($allAvailableRooms, 0, $roomCount * count($occupancy['rooms']));

                $roomsUsedForPrebooks = [];
                $numPrebooks = $roomCount;

                for ($p = 0; $p < $numPrebooks; $p++) {
                    $roomTokens = [];
                    $currentPrebookRooms = [];
                    $availableRooms = $availableRoomsPool;
                    $usedIndexes = [];

                    foreach ($occupancy['rooms'] as $occIndex => $requiredOcc) {
                        $matched = null;
                        $matchIndex = -1;

                        foreach ($availableRooms as $index => $item) {
                            if (in_array($index, $usedIndexes)) continue;

                            $roomOcc = json_decode($item['offered_room']->occupancy, true);
                            $sameAdults = ($roomOcc['adults'] ?? 0) == ($requiredOcc['adults'] ?? 0);
                            $sameChildren = count($roomOcc['childrenAges'] ?? []) == count($requiredOcc['childrenAges'] ?? []);

                            if ($sameAdults && $sameChildren) {
                                $matched = $item['offered_room'];
                                $matchIndex = $index;
                                break;
                            }
                        }

                        if ($matched) {
                            $roomTokens[] = $matched->room_token;
                            $currentPrebookRooms[] = $availableRooms[$matchIndex];
                            $usedIndexes[] = $matchIndex;
                        }
                    }

                    if (count($roomTokens) < count($occupancy['rooms'])) {
                        continue;
                    }
                    // After building one prebook, remove those rooms from the pool
                    $availableRoomsPool = array_slice(
                        $availableRoomsPool,
                        count($occupancy['rooms'])
                    );

                    $roomsUsedForPrebooks[] = $currentPrebookRooms;

                    $prebookData = [
                        'srk' => $tempOffer->srk,
                        'hotel_id' => $tempOffer->hotel_index,
                        'offer_index' => $tempOffer->offer_index,
                        'results_token' => $tempOffer->result_token,
                        'packageToken' => $packageToken,
                        'roomTokens' => $roomTokens,
                    ];

                    $prebookResponse = $this->prebookOffer($magicService, $prebookData);
                    $storePrebookResponse = $this->storePrebook([
                        'telephone' => $telephone,
                        'availability_token' => $prebookResponse['availabilityToken'] ?? null,
                        'srk' => $tempOffer->srk,
                        'package_token' => $packageToken,
                        'hotel_id' => $tempOffer->hotel_index,
                        'offer_index' => $tempOffer->offer_index,
                        'result_token' => $tempOffer->result_token,
                        'rooms' => array_map(function ($token) use ($currentPrebookRooms) {
                            $matched = collect($currentPrebookRooms)->first(fn($r) => $r['offered_room']->room_token === $token);
                            $r = $matched['offered_room'];
                            return [
                                'room_token' => $r->room_token,
                                'room_name' => $r->room_name,
                                'board_basis' => $r->board_basis,
                                'non_refundable' => (bool)$r->non_refundable,
                                'price' => (float)$r->price,
                                'currency' => $r->currency,
                                'occupancy' => json_decode($r->occupancy, true),
                            ];
                        }, $roomTokens),
                        'service_dates' => $prebookResponse['serviceDates'] ?? null,
                        'checkin' => $prebookResponse['serviceDates']['startDate'] ?? $checkIn,
                        'checkout' => $prebookResponse['serviceDates']['endDate'] ?? $checkOut,
                        'duration' => $prebookResponse['serviceDates']['duration'] ?? null,
                        'autocancel_date' => $prebookResponse['autocancelDate'] ?? $prebookResponse['autoCancelDate'] ?? null,
                        'cancel_policy' => $prebookResponse['cancellationPolicy'] ?? [],
                        'remarks' => $prebookResponse['remarks'] ?? [],
                        'package' => $prebookResponse['package'] ?? [],
                        'payment_methods' => $prebookResponse['paymentMethods'] ?? [],
                        'booking_options' => $prebookResponse['bookingOptions'] ?? [],
                        'price_breakdown' => $prebookResponse['priceBreakdown'] ?? [],
                        'taxes' => $prebookResponse['taxes'] ?? [],
                    ]);

                    $finalRoomsData[] = [
                        'room' => collect($currentPrebookRooms)->map(fn($room) => [
                            'room_name' => $room['offered_room']->room_name,
                            'board_basis' => $room['offered_room']->board_basis,
                            'non_refundable' => (bool)$room['offered_room']->non_refundable,
                            'price' => (float)$room['offered_room']->price,
                            'currency' => $room['offered_room']->currency,
                        ])->values()->all(),
                        'prebook' => [
                            'prebookKey' => $storePrebookResponse['prebook_key'] ?? null,
                            'serviceDates' => $prebookResponse['serviceDates'] ?? [],
                            'package' => [
                                'status' => $prebookResponse['package']['status'] ?? null,
                                'complete' => $prebookResponse['package']['complete'] ?? null,
                                'price' => $prebookResponse['package']['price'] ?? [],
                                'rate' => $prebookResponse['package']['rate'] ?? [],
                                'packageRooms' => array_map(function ($room) {
                                    return [
                                        'occupancy' => $room['occupancy'] ?? [],
                                    ];
                                }, $prebookResponse['package']['packageRooms'] ?? []),
                            ],
                            'paymentMethods' => [
                                'prepaid' => $prebookResponse['paymentMethods']['prepaid'] ?? [],
                            ],
                            'bookingOptions' => $prebookResponse['bookingOptions'] ?? [],
                            'autocancelDate' => $prebookResponse['autocancelDate'] ?? $prebookResponse['autoCancelDate'] ?? null,
                            'cancelPolicy' => $prebookResponse['cancellationPolicy'] ?? [],
                            'priceBreakdown' => $prebookResponse['priceBreakdown'] ?? [],
                            'remarks' => $prebookResponse['remarks'] ?? [],
                            'taxes' => $prebookResponse['taxes'] ?? [],
                        ],
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'B2B booking flow completed successfully.',
                'data' => [
                    'telephone' => $telephone,
                    'hotel_name' => $hotelData['hotel_name'],
                    'room_count' => count($finalRoomsData),
                    'rooms' => $finalRoomsData,
                ]
            ];
        } catch (Exception $e) {
            $this->logger->error('Hotel search flow failed', [
                'telephone' => $telephone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $message = $e->getMessage();

            $message = preg_replace('/^An error occurred during hotel search:\s*/', '', $message);
            $message = preg_replace('/^Prebooking failed:\s*/', '', $message);
            $message = preg_replace('/^API request failed:\s*/', '', $message);

            if (preg_match('/"detail":"([^"]+)"/', $message, $m)) {
                $message = $m[1];
            }

            return [
                'success' => false,
                'message' => trim($message)
            ];
        }
    }
}
