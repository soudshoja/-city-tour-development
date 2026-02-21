<?php

namespace App\GraphQL\Queries;

use App\Services\DotwService;
use App\Models\DotwPrebook;
use App\Models\DotwRoom;
use App\Models\Country;
use App\Http\Traits\CurrencyExchangeTrait;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GraphQL Query: SearchDotwHotels
 *
 * Comprehensive DOTW hotel search with automatic dual getRooms pattern,
 * rate blocking, and pre-booking storage
 *
 * This query handles the mandatory V4 flow:
 * 1. searchhotels - Find available hotels and cheapest rates
 * 2. getRooms (browse) - Get full rate details without blocking
 * 3. getRooms (blocking) - Lock rate for 3 minutes with allocationDetails
 *
 * Applies B2C markup (20% default) and supports multi-currency
 *
 * @package App\GraphQL\Queries
 */
class SearchDotwHotels
{
    use CurrencyExchangeTrait;

    protected $dotwService;
    protected $logger;

    public function __construct()
    {
        $this->dotwService = new DotwService();
        $this->logger = Log::channel('dotw');
    }

    /**
     * Execute hotel search query
     *
     * @param mixed $_ Parent resolver value (unused)
     * @param array $args GraphQL query arguments with 'input' field
     *
     * @return array Search results with hotels, prebookings, and metadata
     */
    public function __invoke($_, array $args)
    {
        $input = $args['input'] ?? [];

        // Validate input
        $validator = Validator::make($input, [
            'telephone' => 'required|string',
            'hotelCode' => 'nullable|integer',
            'hotelName' => 'nullable|string',
            'city' => 'required|string',
            'guestNationality' => 'required|string',
            'checkIn' => 'required|date|after_or_equal:today',
            'checkOut' => 'required|date|after:checkIn',
            'occupancy' => 'required',
            'noOfRooms' => 'nullable|integer|min:1|max:9',
            'currency' => 'nullable|string|size:3',
            'bookingType' => 'required|string|in:b2b,b2c',
            'priceMin' => 'nullable|numeric|min:0',
            'priceMax' => 'nullable|numeric|min:0',
            'maxStay' => 'nullable|integer|min:1|max:365',
            'nonRefundable' => 'nullable|boolean',
            'starRating' => 'nullable|integer|min:1|max:5',
        ], [
            'telephone.required' => 'Telephone number is required.',
            'city.required' => 'City code is required.',
            'guestNationality.required' => 'Guest nationality is required.',
            'checkIn.required' => 'Check-in date is required.',
            'checkIn.after_or_equal' => 'Check-in date must be today or later.',
            'checkOut.required' => 'Check-out date is required.',
            'checkOut.after' => 'Check-out date must be after check-in date.',
            'occupancy.required' => 'Occupancy details are required.',
            'bookingType.required' => 'Booking type is required.',
            'bookingType.in' => 'Booking type must be either b2b or b2c.',
        ]);

        $this->logger->info('DOTW hotel search initiated', [
            'city' => $input['city'] ?? null,
            'check_in' => $input['checkIn'] ?? null,
            'booking_type' => $input['bookingType'] ?? null,
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'status' => 'validation_error',
                'message' => $validator->errors()->first(),
                'hotels' => [],
                'prebookings' => [],
            ];
        }

        try {
            // Parse guest nationality to DOTW country code
            $guestNationalityCode = $this->resolveGuestNationality($input['guestNationality']);

            if (!$guestNationalityCode) {
                return [
                    'success' => false,
                    'status' => 'nationality_not_found',
                    'message' => "Guest nationality '{$input['guestNationality']}' not found",
                    'hotels' => [],
                    'prebookings' => [],
                ];
            }

            // Parse occupancy array
            $rooms = $this->parseOccupancy($input['occupancy'], $guestNationalityCode);

            if (empty($rooms)) {
                return [
                    'success' => false,
                    'status' => 'invalid_occupancy',
                    'message' => 'Invalid occupancy specification',
                    'hotels' => [],
                    'prebookings' => [],
                ];
            }

            // Default currency to AED if not specified
            $currency = $input['currency'] ?? 'AED';

            // Step 1: Search for hotels
            $searchParams = [
                'fromDate' => $input['checkIn'],
                'toDate' => $input['checkOut'],
                'currency' => $currency,
                'rooms' => $rooms,
            ];

            // Add city filter
            if (!empty($input['city'])) {
                $searchParams['filters'] = [
                    'city' => $input['city'],
                ];

                // Add optional conditions
                $conditions = [];

                if (!empty($input['starRating'])) {
                    $conditions[] = [
                        'fieldName' => 'rating',
                        'fieldTest' => 'equals',
                        'fieldValues' => [(int)$input['starRating']],
                    ];
                }

                if (!empty($input['priceMin']) || !empty($input['priceMax'])) {
                    $fieldValues = [];
                    if (!empty($input['priceMin']) && !empty($input['priceMax'])) {
                        $fieldValues = [
                            (float)$input['priceMin'],
                            (float)$input['priceMax'],
                        ];
                        $conditions[] = [
                            'fieldName' => 'price',
                            'fieldTest' => 'between',
                            'fieldValues' => $fieldValues,
                        ];
                    }
                }

                if (!empty($conditions)) {
                    $searchParams['filters']['conditions'] = $conditions;
                }
            }

            // Execute search
            $searchResults = $this->dotwService->searchHotels($searchParams);

            if (empty($searchResults)) {
                return [
                    'success' => false,
                    'status' => 'no_results',
                    'message' => 'No hotels found matching your criteria',
                    'hotels' => [],
                    'prebookings' => [],
                ];
            }

            // Step 2-3: Get rooms and perform blocking for each hotel
            $hotels = [];
            $prebookings = [];

            foreach ($searchResults as $hotel) {
                $hotelId = $hotel['hotelId'];

                try {
                    // First getRooms call: browse rates (no blocking)
                    $browseParams = [
                        'fromDate' => $input['checkIn'],
                        'toDate' => $input['checkOut'],
                        'currency' => $currency,
                        'productId' => $hotelId,
                        'rooms' => $rooms,
                        'fields' => [
                            'cancellation',
                            'allocationDetails',
                            'name',
                            'tariffNotes',
                        ],
                    ];

                    $browseRooms = $this->dotwService->getRooms($browseParams, false);

                    if (empty($browseRooms)) {
                        continue; // Skip hotel if no rooms available
                    }

                    // Process first room for blocking (simplified for single room example)
                    $firstRoom = $browseRooms[0] ?? null;

                    if (!$firstRoom || empty($firstRoom['details'])) {
                        continue;
                    }

                    $firstDetail = $firstRoom['details'][0] ?? null;

                    if (!$firstDetail) {
                        continue;
                    }

                    // Second getRooms call: perform blocking to lock rate
                    $blockingParams = [
                        'fromDate' => $input['checkIn'],
                        'toDate' => $input['checkOut'],
                        'currency' => $currency,
                        'productId' => $hotelId,
                        'rooms' => $rooms,
                        'roomTypeSelected' => [
                            'code' => $firstRoom['roomTypeCode'],
                            'selectedRateBasis' => $firstDetail['id'],
                            'allocationDetails' => $firstDetail['allocationDetails'],
                            'rateBasis' => DotwService::RATE_BASIS_ALL,
                        ],
                    ];

                    $blockedRooms = $this->dotwService->getRooms($blockingParams, true);

                    // Create hotel entry with blocked rate
                    $basePrice = $firstDetail['price'];
                    $appliedMarkup = 0;

                    // Apply B2C markup if requested
                    if ($input['bookingType'] === 'b2c') {
                        $markupPercentage = config('dotw.b2c_markup_percentage', 20);
                        $appliedMarkup = $basePrice * ($markupPercentage / 100);
                    }

                    $finalPrice = $basePrice + $appliedMarkup;

                    $hotels[] = [
                        'id' => $hotelId,
                        'name' => $hotel['hotelId'], // Note: API doesn't return name in simple response
                        'roomType' => $firstRoom['roomName'],
                        'basePrice' => round($basePrice, 2),
                        'markup' => round($appliedMarkup, 2),
                        'finalPrice' => round($finalPrice, 2),
                        'currency' => $currency,
                        'adults' => $rooms[0]['adultsCode'] ?? 2,
                        'children' => $rooms[0]['children'] ?? [],
                        'checkIn' => $input['checkIn'],
                        'checkOut' => $input['checkOut'],
                    ];

                    // Create prebook record
                    $prebookKey = Str::uuid()->toString();

                    $prebook = DotwPrebook::create([
                        'prebook_key' => $prebookKey,
                        'allocation_details' => $firstDetail['allocationDetails'],
                        'hotel_code' => $hotelId,
                        'hotel_name' => $firstRoom['roomName'],
                        'room_type' => $firstRoom['roomTypeCode'],
                        'room_quantity' => 1,
                        'total_fare' => $finalPrice,
                        'total_tax' => $firstDetail['taxes'] ?? 0,
                        'original_currency' => $currency,
                        'exchange_rate' => 1.0,
                        'room_rate_basis' => $firstDetail['id'],
                        'is_refundable' => true, // Set based on response if available
                        'customer_reference' => $input['telephone'] ?? null,
                        'booking_details' => [
                            'cancellation_rules' => $firstDetail['cancellationRules'] ?? [],
                            'guest_nationality' => $guestNationalityCode,
                            'base_price' => $basePrice,
                            'markup_percentage' => $input['bookingType'] === 'b2c' ? config('dotw.b2c_markup_percentage', 20) : 0,
                        ],
                    ]);

                    // Set allocation expiry
                    $prebook->setExpiry();

                    // Create room record
                    DotwRoom::create([
                        'dotw_preboot_id' => $prebook->id,
                        'room_number' => 0,
                        'adults_count' => $rooms[0]['adultsCode'] ?? 2,
                        'children_count' => count($rooms[0]['children'] ?? []),
                        'children_ages' => $rooms[0]['children'] ?? [],
                        'passenger_nationality' => $guestNationalityCode,
                        'passenger_residence' => $guestNationalityCode,
                    ]);

                    $prebookings[] = [
                        'id' => $prebook->id,
                        'prebookKey' => $prebookKey,
                        'hotelId' => $hotelId,
                        'allocationExpiry' => $prebook->expired_at,
                    ];
                } catch (Exception $e) {
                    // Log hotel-specific errors but continue with others
                    $this->logger->warning('Error processing hotel', [
                        'hotel_id' => $hotelId,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            return [
                'success' => true,
                'status' => 'success',
                'message' => count($hotels) . ' hotels found',
                'hotels' => $hotels,
                'prebookings' => $prebookings,
                'totalCount' => count($hotels),
            ];
        } catch (Exception $e) {
            $this->logger->error('DOTW hotel search failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'status' => 'search_error',
                'message' => 'Hotel search failed: ' . $e->getMessage(),
                'hotels' => [],
                'prebookings' => [],
            ];
        }
    }

    /**
     * Resolve guest nationality to DOTW country code
     *
     * @param string $nationalityInput Country name or ISO code
     *
     * @return string|null DOTW country code or null if not found
     */
    private function resolveGuestNationality(string $nationalityInput): ?string
    {
        $nationalityInput = trim($nationalityInput);

        // If 2-character ISO code, assume it's valid
        if (strlen($nationalityInput) === 2) {
            return strtoupper($nationalityInput);
        }

        // Try to find by country name
        $country = Country::where('name', 'LIKE', '%' . $nationalityInput . '%')
            ->orWhere('iso_code', strtoupper($nationalityInput))
            ->first();

        if ($country) {
            return $country->iso_code;
        }

        // Try fuzzy matching
        $allCountries = Country::all();
        $bestMatch = null;
        $lowestDistance = PHP_INT_MAX;
        $threshold = 3;

        foreach ($allCountries as $c) {
            $distance = levenshtein(
                strtolower($nationalityInput),
                strtolower($c->name)
            );

            if ($distance < $lowestDistance && $distance <= $threshold) {
                $lowestDistance = $distance;
                $bestMatch = $c->iso_code;
            }
        }

        return $bestMatch;
    }

    /**
     * Parse occupancy specification into rooms array
     *
     * Converts various occupancy formats into standardized rooms array
     * for DOTW API consumption
     *
     * @param mixed $occupancy Occupancy specification (array or string)
     * @param string $nationalityCode DOTW country code
     *
     * @return array Rooms array or empty if invalid
     */
    private function parseOccupancy($occupancy, string $nationalityCode): array
    {
        $rooms = [];

        if (is_array($occupancy)) {
            // Expected format: [{'adults': 2, 'children': [8, 12]}, ...]
            foreach ($occupancy as $room) {
                $rooms[] = [
                    'no' => count($rooms) + 1,
                    'adultsCode' => (int)($room['adults'] ?? 2),
                    'children' => array_map('intval', $room['children'] ?? []),
                    'rateBasis' => DotwService::RATE_BASIS_ALL,
                    'passengerNationality' => $nationalityCode,
                    'passengerCountryOfResidence' => $nationalityCode,
                ];
            }
        } elseif (is_string($occupancy)) {
            // Parse string format: "2A,1C8" = 2 adults, 1 child age 8
            $pattern = '/(\d+)A/';
            $matches = [];
            preg_match_all($pattern, $occupancy, $matches);

            if (!empty($matches[1])) {
                $adults = (int)$matches[1][0];

                // Parse children
                $children = [];
                $childPattern = '/(\d+)C(\d+)/';
                $childMatches = [];
                preg_match_all($childPattern, $occupancy, $childMatches);

                if (!empty($childMatches[2])) {
                    $children = array_map('intval', $childMatches[2]);
                }

                $rooms[] = [
                    'no' => 1,
                    'adultsCode' => $adults,
                    'children' => $children,
                    'rateBasis' => DotwService::RATE_BASIS_ALL,
                    'passengerNationality' => $nationalityCode,
                    'passengerCountryOfResidence' => $nationalityCode,
                ];
            }
        }

        return $rooms;
    }
}
