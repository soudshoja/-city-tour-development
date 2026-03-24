<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Modules\DotwAI\DTOs\DotwAIContext;
use App\Modules\DotwAI\Models\DotwAICity;
use App\Services\DotwService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Hotel search orchestration service for the DotwAI module.
 *
 * Coordinates city resolution, DOTW API calls, post-search filtering,
 * result numbering, and per-phone caching. This service wraps DotwService
 * (composition, not modification) and delegates fuzzy matching to
 * FuzzyMatcherService.
 *
 * @see SRCH-01 Search hotels by city/name
 * @see SRCH-02 Get hotel details (browse mode)
 * @see SRCH-03 Get city list
 * @see SRCH-04 Numbered results + caching per phone
 * @see SRCH-05 Multi-room occupancy
 * @see SRCH-06 Filters (star, meal, price, refundable, hotel name)
 */
class HotelSearchService
{
    /**
     * @param FuzzyMatcherService $fuzzyMatcher Fuzzy name resolver for hotels/cities/countries
     */
    public function __construct(
        private readonly FuzzyMatcherService $fuzzyMatcher,
    ) {}

    /**
     * Search hotels by city/name with filters, numbering, and caching.
     *
     * Orchestration flow:
     * 1. Resolve city name to DOTW city code
     * 2. Resolve nationality/residence to DOTW country codes
     * 3. Optionally filter by hotel name (fuzzy match to DOTW hotel IDs)
     * 4. Call DotwService::searchHotels()
     * 5. Apply post-search filters (star, meal, price, refundable, name)
     * 6. Sort by star rating desc, then price asc
     * 7. Limit and number results 1..N
     * 8. Cache per phone number
     *
     * @param DotwAIContext       $context  Resolved company/agent context
     * @param array<string,mixed> $input    Validated search input
     * @return array{error?: bool, code?: string, message?: string, suggestedAction?: string,
     *               hotels?: array, total_found?: int, showing?: int,
     *               city_name?: string, check_in?: string, check_out?: string}
     */
    public function searchHotels(DotwAIContext $context, array $input): array
    {
        // 1. Resolve city code
        $city = $this->fuzzyMatcher->resolveCity($input['city']);

        if ($city === null) {
            // Fallback: try LIKE search directly on DotwAICity
            $city = DotwAICity::where('name', 'LIKE', "%{$input['city']}%")->first();
        }

        if ($city === null) {
            return [
                'error' => true,
                'code' => DotwAIResponse::CITY_NOT_FOUND,
                'message' => "Could not resolve city: {$input['city']}",
                'suggestedAction' => 'Ask the user to provide a different city name or check spelling.',
            ];
        }

        // 2. Resolve nationality and residence codes
        $nationalityCode = $this->resolveNationalityCode($input['nationality'] ?? null);
        $residenceCode = config('dotwai.default_residence', '66');

        // 3. Hotel name filter (fuzzy match to DOTW hotel IDs)
        $hotelIdFilter = [];
        $hotelNameForPostFilter = null;

        if (!empty($input['hotel'])) {
            $matchedHotels = $this->fuzzyMatcher->findHotels($input['hotel'], $city->name);

            if ($matchedHotels->isNotEmpty()) {
                $hotelIdFilter = $matchedHotels->pluck('dotw_hotel_id')->toArray();
            } else {
                // No local matches -- will filter results by name string after DOTW returns
                $hotelNameForPostFilter = $input['hotel'];
            }
        }

        // 4. Build DotwService params
        $params = [
            'fromDate' => $input['check_in'],
            'toDate' => $input['check_out'],
            'currency' => config('dotwai.default_currency', '520'),
            'city' => $city->code,
            'rooms' => $this->buildRoomsArray(
                $input['occupancy'],
                $nationalityCode,
                $residenceCode
            ),
            'filters' => $this->buildFilters($input, $hotelIdFilter),
        ];

        // 5. Call DotwService::searchHotels()
        try {
            $dotwService = new DotwService($context->companyId);
            $apiResults = $dotwService->searchHotels($params, null, null, $context->companyId);
        } catch (\Exception $e) {
            Log::channel('dotw')->error('[DotwAI] Search API error', [
                'error' => $e->getMessage(),
                'city' => $input['city'],
                'company_id' => $context->companyId,
            ]);

            return [
                'error' => true,
                'code' => DotwAIResponse::DOTW_API_ERROR,
                'message' => 'DOTW API error: ' . $e->getMessage(),
                'suggestedAction' => 'Retry the request. If persistent, contact technical support.',
            ];
        }

        if (empty($apiResults)) {
            return [
                'error' => true,
                'code' => DotwAIResponse::NO_RESULTS,
                'message' => 'No hotels found for the given criteria',
                'suggestedAction' => 'Suggest trying different dates, a different city, or removing filters.',
            ];
        }

        // 6. Post-search filtering
        $filtered = $this->applyPostSearchFilters($apiResults, $input, $hotelNameForPostFilter);

        if (empty($filtered)) {
            return [
                'error' => true,
                'code' => DotwAIResponse::NO_RESULTS,
                'message' => 'No hotels match the applied filters',
                'suggestedAction' => 'Suggest trying different dates, a different city, or removing filters.',
            ];
        }

        // 7. Sort by star rating desc, then cheapest price asc
        usort($filtered, function (array $a, array $b) {
            $starA = $a['star_rating'] ?? 0;
            $starB = $b['star_rating'] ?? 0;

            if ($starB !== $starA) {
                return $starB <=> $starA;
            }

            return ($a['cheapest_price'] ?? PHP_FLOAT_MAX) <=> ($b['cheapest_price'] ?? PHP_FLOAT_MAX);
        });

        // 8. Limit results
        $limit = (int) config('dotwai.search_results_limit', 10);
        $totalFound = count($filtered);
        $limited = array_slice($filtered, 0, $limit);

        // 9. Number results 1..N
        $numbered = [];
        foreach ($limited as $index => $hotel) {
            $hotel['option_number'] = $index + 1;
            $numbered[] = $hotel;
        }

        // 10. Apply B2C markup to display prices if applicable
        if ($context->isB2C()) {
            $numbered = $this->applyMarkupToResults($numbered, $context);
        }

        // 11. Cache results per phone number
        $phone = $this->normalizePhone($input['telephone'] ?? '');
        if (!empty($phone)) {
            Cache::put(
                "dotwai:search:{$phone}",
                $numbered,
                (int) config('dotwai.search_cache_ttl', 600)
            );
        }

        return [
            'hotels' => $numbered,
            'total_found' => $totalFound,
            'showing' => count($numbered),
            'city_name' => $city->name,
            'check_in' => $input['check_in'],
            'check_out' => $input['check_out'],
        ];
    }

    /**
     * Get hotel room details in browse mode (no rate blocking).
     *
     * Calls DotwService::getRooms with blocking=false to get room types,
     * rates, cancellation policies, specials, and tariff notes.
     *
     * @param DotwAIContext       $context  Resolved company/agent context
     * @param string              $hotelId  DOTW hotel/product ID
     * @param array<string,mixed> $input    Validated input (dates, occupancy, telephone)
     * @return array{error?: bool, code?: string, message?: string,
     *               hotel?: array, rooms?: array}
     *
     * @see SRCH-02
     */
    public function getHotelDetails(DotwAIContext $context, string $hotelId, array $input): array
    {
        $nationalityCode = $this->resolveNationalityCode($input['nationality'] ?? null);
        $residenceCode = config('dotwai.default_residence', '66');

        $params = [
            'fromDate' => $input['check_in'],
            'toDate' => $input['check_out'],
            'currency' => config('dotwai.default_currency', '520'),
            'productId' => $hotelId,
            'rooms' => $this->buildRoomsArray(
                $input['occupancy'],
                $nationalityCode,
                $residenceCode
            ),
        ];

        try {
            $dotwService = new DotwService($context->companyId);
            $apiResult = $dotwService->getRooms($params, false, null, null, $context->companyId);
        } catch (\Exception $e) {
            Log::channel('dotw')->error('[DotwAI] getRooms API error', [
                'error' => $e->getMessage(),
                'hotel_id' => $hotelId,
                'company_id' => $context->companyId,
            ]);

            return [
                'error' => true,
                'code' => DotwAIResponse::DOTW_API_ERROR,
                'message' => 'DOTW API error: ' . $e->getMessage(),
                'suggestedAction' => 'Retry the request. If persistent, contact technical support.',
            ];
        }

        if (empty($apiResult)) {
            return [
                'error' => true,
                'code' => DotwAIResponse::NO_RESULTS,
                'message' => 'No rooms available for this hotel',
                'suggestedAction' => 'Try different dates or a different hotel.',
            ];
        }

        // Parse rooms into structured format
        $rooms = $this->parseRoomDetails($apiResult, $context);

        // Try to get hotel info from local database
        $hotelInfo = $this->getHotelInfo($hotelId);

        return [
            'hotel' => $hotelInfo,
            'rooms' => $rooms,
            'hotel_id' => $hotelId,
            'check_in' => $input['check_in'],
            'check_out' => $input['check_out'],
        ];
    }

    /**
     * Get city list for a country.
     *
     * Fast path: returns from local dotwai_cities table if data exists.
     * Slow path: calls DotwService::getCityList and upserts results.
     *
     * @param string $countryName Country name to resolve
     * @return array{error?: bool, code?: string, message?: string, cities?: array}
     *
     * @see SRCH-03
     */
    public function getCities(string $countryName): array
    {
        // Resolve country name to DOTW country code
        $country = $this->fuzzyMatcher->resolveCountry($countryName);

        if ($country === null) {
            return [
                'error' => true,
                'code' => DotwAIResponse::CITY_NOT_FOUND,
                'message' => "Could not resolve country: {$countryName}",
                'suggestedAction' => 'Ask the user to provide a different country name or check spelling.',
            ];
        }

        // Fast path: check local table
        $localCities = DotwAICity::where('country_code', $country->code)
            ->orderBy('name')
            ->get();

        if ($localCities->isNotEmpty()) {
            return $localCities->map(fn (DotwAICity $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'country_code' => $c->country_code,
            ])->values()->all();
        }

        // Slow path: call DOTW API and upsert
        try {
            $dotwService = new DotwService();
            $apiCities = $dotwService->getCityList($country->code);
        } catch (\Exception $e) {
            Log::channel('dotw')->error('[DotwAI] getCityList API error', [
                'error' => $e->getMessage(),
                'country' => $countryName,
                'country_code' => $country->code,
            ]);

            return [
                'error' => true,
                'code' => DotwAIResponse::DOTW_API_ERROR,
                'message' => 'DOTW API error: ' . $e->getMessage(),
                'suggestedAction' => 'Retry the request. If persistent, contact technical support.',
            ];
        }

        // Upsert into local table for future use
        foreach ($apiCities as $cityData) {
            DotwAICity::updateOrCreate(
                ['code' => $cityData['code']],
                [
                    'name' => $cityData['name'],
                    'country_code' => $country->code,
                ]
            );
        }

        return array_map(fn (array $c) => [
            'code' => $c['code'],
            'name' => $c['name'],
            'country_code' => $country->code,
        ], $apiCities);
    }

    /**
     * Get cached search results for a phone number.
     *
     * @param string $phone Phone number
     * @return array|null Cached numbered results, or null if not cached
     *
     * @see SRCH-04
     */
    public function getCachedResults(string $phone): ?array
    {
        $normalized = $this->normalizePhone($phone);

        return Cache::get("dotwai:search:{$normalized}");
    }

    /**
     * Build DOTW rooms array from occupancy input.
     *
     * Maps each room's adults/children to DOTW format with nationality
     * and residence codes.
     *
     * @param array<int, array{adults: int, children_ages?: int[]}> $occupancy
     * @param string $nationalityCode DOTW country code for nationality
     * @param string $residenceCode   DOTW country code for residence
     * @return array<int, array{no: int, adultsCode: int, children: int[], passengerNationality: string, passengerCountryOfResidence: string}>
     *
     * @see SRCH-05
     */
    private function buildRoomsArray(array $occupancy, string $nationalityCode, string $residenceCode): array
    {
        $rooms = [];

        foreach ($occupancy as $index => $room) {
            $rooms[] = [
                'no' => $index + 1,
                'adultsCode' => (int) ($room['adults'] ?? 2),
                'children' => $room['children_ages'] ?? [],
                'passengerNationality' => $nationalityCode,
                'passengerCountryOfResidence' => $residenceCode,
            ];
        }

        return $rooms;
    }

    /**
     * Build DOTW API filter elements.
     *
     * @param array<string,mixed> $input      Search input
     * @param array<int, string>  $hotelIds   Matched DOTW hotel IDs from fuzzy search
     * @return array<string,mixed>
     */
    private function buildFilters(array $input, array $hotelIds = []): array
    {
        $filters = [];

        // Star rating filter
        if (!empty($input['star_rating'])) {
            $filters['starRating'] = (int) $input['star_rating'];
        }

        // Hotel ID filter (from fuzzy name match, batches of 50)
        if (!empty($hotelIds)) {
            $filters['hotelIds'] = array_slice($hotelIds, 0, 50);
        }

        return $filters;
    }

    /**
     * Apply post-search filters to DOTW API results.
     *
     * Filters: star rating, meal type, price range, refundable, hotel name.
     *
     * @param array               $hotels              Raw hotel results from DOTW API
     * @param array<string,mixed> $input               Search input with filter values
     * @param string|null         $hotelNameForPostFilter Hotel name to match against (if not filtered at API level)
     * @return array Filtered hotel results with enriched data
     *
     * @see SRCH-06
     */
    private function applyPostSearchFilters(array $hotels, array $input, ?string $hotelNameForPostFilter): array
    {
        $filtered = [];

        foreach ($hotels as $hotel) {
            $hotelId = $hotel['hotelId'] ?? '';

            // Get hotel info from local database for enrichment
            $localHotel = $this->getHotelInfo($hotelId);
            $hotelName = $localHotel['name'] ?? "Hotel #{$hotelId}";
            $starRating = $localHotel['star_rating'] ?? null;
            $city = $localHotel['city'] ?? '';
            $address = $localHotel['address'] ?? '';

            // Hotel name post-filter (if not filtered at API level)
            if ($hotelNameForPostFilter !== null) {
                if (stripos($hotelName, $hotelNameForPostFilter) === false) {
                    continue;
                }
            }

            // Star rating filter
            if (!empty($input['star_rating']) && $starRating !== null) {
                if ((int) $starRating !== (int) $input['star_rating']) {
                    continue;
                }
            }

            // Process rooms to find cheapest price and apply room-level filters
            $cheapestPrice = PHP_FLOAT_MAX;
            $cheapestMealType = 'Room Only';
            $isRefundable = false;
            $hasMatchingRooms = false;

            foreach ($hotel['rooms'] ?? [] as $room) {
                foreach ($room['roomTypes'] ?? [] as $roomType) {
                    $price = (float) ($roomType['total'] ?? 0);
                    $msp = (float) ($roomType['totalMinimumSelling'] ?? 0);
                    $nonRefundable = strtolower($roomType['nonRefundable'] ?? 'no');
                    $rateBasisId = $roomType['rateBasisId'] ?? '';

                    // Meal type filter
                    if (!empty($input['meal_type']) && $input['meal_type'] !== 'All') {
                        $mealLabel = $this->mapRateBasisToMealType($rateBasisId);
                        if (stripos($mealLabel, $input['meal_type']) === false) {
                            continue;
                        }
                    }

                    // Refundable filter
                    if (!empty($input['refundable']) && $nonRefundable === 'yes') {
                        continue;
                    }

                    // Price range filter
                    if (!empty($input['price_min']) && $price < (float) $input['price_min']) {
                        continue;
                    }
                    if (!empty($input['price_max']) && $price > (float) $input['price_max']) {
                        continue;
                    }

                    $hasMatchingRooms = true;

                    if ($price < $cheapestPrice) {
                        $cheapestPrice = $price;
                        $cheapestMealType = $this->mapRateBasisToMealType($rateBasisId);
                    }

                    if ($nonRefundable !== 'yes') {
                        $isRefundable = true;
                    }
                }
            }

            if (!$hasMatchingRooms) {
                continue;
            }

            $filtered[] = [
                'hotel_id' => $hotelId,
                'name' => $hotelName,
                'star_rating' => $starRating,
                'city' => $city,
                'address' => $address,
                'cheapest_price' => $cheapestPrice === PHP_FLOAT_MAX ? 0 : $cheapestPrice,
                'meal_type' => $cheapestMealType,
                'is_refundable' => $isRefundable,
                'currency' => config('dotwai.display_currency', 'KWD'),
                'room_count' => count($hotel['rooms'] ?? []),
            ];
        }

        return $filtered;
    }

    /**
     * Parse room details from getRooms API response.
     *
     * Extracts room types with rateBasis info, cancellation rules,
     * specials, tariff notes, and applies B2C markup when applicable.
     *
     * @param array        $apiResult Raw getRooms response
     * @param DotwAIContext $context   Company/agent context
     * @return array Structured room details
     */
    private function parseRoomDetails(array $apiResult, DotwAIContext $context): array
    {
        $rooms = [];

        foreach ($apiResult as $room) {
            $roomTypeCode = $room['roomTypeCode'] ?? '';
            $roomName = $room['roomName'] ?? '';
            $roomSpecials = $room['specials'] ?? [];

            foreach ($room['details'] ?? [] as $detail) {
                $price = (float) ($detail['price'] ?? 0);
                $taxes = (float) ($detail['taxes'] ?? 0);
                $msp = 0; // MSP not directly in getRooms parsed output
                $rateBasisId = $detail['id'] ?? '';
                $tariffNotes = $detail['tariffNotes'] ?? '';
                $cancellationRules = $detail['cancellationRules'] ?? [];
                $minStay = $detail['minStay'] ?? '';
                $minStayDate = $detail['dateApplyMinStay'] ?? '';
                $allocationDetails = $detail['allocationDetails'] ?? '';
                $specialsApplied = $detail['specialsApplied'] ?? [];
                $propertyFees = $detail['propertyFees'] ?? [];

                // Determine refundability
                $isAPR = false;
                $isRefundable = true;
                // Check cancellation rules for full restriction
                $allRestricted = !empty($cancellationRules) && collect($cancellationRules)
                    ->every(fn (array $rule) => ($rule['cancelRestricted'] ?? false) === true);
                if ($allRestricted) {
                    $isRefundable = false;
                    $isAPR = true;
                }

                // Apply B2C markup
                $displayPrice = $price;
                if ($context->isB2C()) {
                    $displayPrice = (float) ceil($price * $context->getMarkupMultiplier());
                    if ($msp > 0) {
                        $displayPrice = max($displayPrice, $msp);
                    }
                }

                // Map specials
                $mappedSpecials = $this->mapSpecials($roomSpecials, $specialsApplied);

                // Map cancellation rules to structured format
                $mappedRules = array_map(fn (array $rule) => [
                    'fromDate' => $rule['fromDate'] ?? '',
                    'toDate' => $rule['toDate'] ?? '',
                    'charge' => (float) ($rule['charge'] ?? $rule['cancelCharge'] ?? 0),
                    'chargeType' => !empty($rule['charge']) ? 'Fixed' : 'Percentage',
                    'cancelRestricted' => $rule['cancelRestricted'] ?? false,
                ], $cancellationRules);

                $rooms[] = [
                    'room_type_code' => $roomTypeCode,
                    'room_name' => $roomName,
                    'rate_basis_id' => $rateBasisId,
                    'meal_type' => $this->mapRateBasisToMealType($rateBasisId),
                    'price' => $price,
                    'display_price' => $displayPrice,
                    'taxes' => $taxes,
                    'currency' => config('dotwai.display_currency', 'KWD'),
                    'is_refundable' => $isRefundable,
                    'is_apr' => $isAPR,
                    'tariff_notes' => $tariffNotes,
                    'cancellation_rules' => $mappedRules,
                    'specials' => $mappedSpecials,
                    'min_stay' => !empty($minStay) ? (int) $minStay : null,
                    'min_stay_date' => $minStayDate ?: null,
                    'allocation_details' => $allocationDetails,
                    'property_fees' => $propertyFees,
                ];
            }
        }

        return $rooms;
    }

    /**
     * Get hotel info from local database.
     *
     * @param string $hotelId DOTW hotel/product ID
     * @return array{name: string, star_rating: int|null, city: string, address: string}
     */
    private function getHotelInfo(string $hotelId): array
    {
        $hotel = \App\Modules\DotwAI\Models\DotwAIHotel::where('dotw_hotel_id', $hotelId)->first();

        if ($hotel) {
            return [
                'name' => $hotel->name,
                'star_rating' => $hotel->star_rating,
                'city' => $hotel->city ?? '',
                'address' => $hotel->address ?? '',
                'country' => $hotel->country ?? '',
            ];
        }

        return [
            'name' => "Hotel #{$hotelId}",
            'star_rating' => null,
            'city' => '',
            'address' => '',
            'country' => '',
        ];
    }

    /**
     * Resolve nationality input to DOTW country code.
     *
     * @param string|null $nationality Country name from input
     * @return string DOTW country code
     */
    private function resolveNationalityCode(?string $nationality): string
    {
        if ($nationality !== null && $nationality !== '') {
            $country = $this->fuzzyMatcher->resolveCountry($nationality);
            if ($country !== null) {
                return $country->code;
            }
        }

        return (string) config('dotwai.default_nationality', '66');
    }

    /**
     * Map DOTW rate basis ID to human-readable meal type label.
     *
     * @param string $rateBasisId DOTW rateBasis ID
     * @return string Meal type label
     */
    private function mapRateBasisToMealType(string $rateBasisId): string
    {
        return match ($rateBasisId) {
            '0' => 'Room Only',
            '1331' => 'Breakfast',
            '1332' => 'Breakfast', // BB variant
            '1333' => 'Half Board',
            '1334' => 'Half Board',
            '1335' => 'Full Board',
            '1336' => 'All Inclusive',
            default => 'Room Only',
        };
    }

    /**
     * Map specials from room-level specials and rate-level specialsApplied.
     *
     * Uses the dotw-mapping.md promotion type mapping pattern.
     *
     * @param array $roomSpecials       Room-level specials (string descriptions)
     * @param array $specialsApplied    Rate-level applied special references
     * @return array<int, array{type: string, name: string, description: string}>
     */
    private function mapSpecials(array $roomSpecials, array $specialsApplied): array
    {
        $mapped = [];

        // Room-level specials are typically string descriptions from DOTW
        foreach ($roomSpecials as $special) {
            if (is_string($special) && !empty($special)) {
                $mapped[] = [
                    'type' => 'promotion',
                    'name' => $special,
                    'description' => $special,
                ];
            }
        }

        // Rate-level specials applied (references to room specials by runno)
        foreach ($specialsApplied as $ref) {
            if (is_string($ref) && !empty($ref)) {
                $mapped[] = [
                    'type' => 'applied',
                    'name' => $ref,
                    'description' => $ref,
                ];
            }
        }

        return $mapped;
    }

    /**
     * Apply B2C markup to search result display prices.
     *
     * @param array        $hotels  Numbered hotel results
     * @param DotwAIContext $context Company context with markup config
     * @return array Hotels with adjusted prices
     */
    private function applyMarkupToResults(array $hotels, DotwAIContext $context): array
    {
        return array_map(function (array $hotel) use ($context) {
            $originalPrice = (float) ($hotel['cheapest_price'] ?? 0);
            $hotel['cheapest_price'] = (float) ceil($originalPrice * $context->getMarkupMultiplier());
            $hotel['original_price'] = $originalPrice;
            return $hotel;
        }, $hotels);
    }

    /**
     * Normalize a phone number by removing non-digit characters.
     *
     * @param string $phone Raw phone number
     * @return string Digits-only phone number
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}
