<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\CompanyDotwCredential;
use App\Services\DotwCacheService;
use App\Services\DotwService;
use RuntimeException;

/**
 * Resolver for the searchHotels GraphQL query.
 *
 * Primary B2B hotel availability search resolver for the DOTW integration.
 * N8N workflows and Resayil WhatsApp agents use this query to get a list of
 * hotels with rates that can then be selected for rate blocking (Phase 5).
 *
 * Key responsibilities:
 * - Resolve per-company credentials via DotwService($companyId) — never falls
 *   back to legacy env credentials (B2B path always requires authenticated company).
 * - Check 2.5-minute per-company search cache before calling DOTW API.
 * - Apply per-company markup to every rate via DotwService::applyMarkup().
 * - Return SearchHotelsResponse shape matching graphql/dotw.graphql schema.
 * - Audit logging is handled internally by DotwService::searchHotels() — this
 *   resolver does NOT call DotwAuditService directly (avoids double-logging).
 *
 * @see graphql/dotw.graphql SearchHotelsResponse, SearchHotelsInput, RoomTypeRate, RateMarkup
 * @see \App\Services\DotwService::searchHotels()
 * @see \App\Services\DotwCacheService::isCached()
 * @see \App\Services\DotwCacheService::remember()
 */
class DotwSearchHotels
{
    /**
     * Cache service for per-company search result caching.
     */
    public function __construct(
        private readonly DotwCacheService $cache,
    ) {}

    /**
     * Resolve the searchHotels query.
     *
     * Flow:
     * 1. Extract Resayil IDs from request attributes (set by ResayilContextMiddleware).
     * 2. Resolve company from authenticated user — returns CREDENTIALS_NOT_CONFIGURED if absent.
     * 3. Build cache key and call isCached() BEFORE remember() to detect cache hit.
     * 4. Inside remember() closure: instantiate DotwService($companyId) and call searchHotels().
     * 5. Apply per-company markup to every rate via formatHotels().
     * 6. Return SearchHotelsResponse with cached flag correctly annotated.
     *
     * @param  mixed  $root  Unused GraphQL root value
     * @param  array  $args  GraphQL arguments — expects 'input' with SearchHotelsInput fields
     * @param  mixed|null  $context  Lighthouse context — provides request() for attribute access
     * @return array SearchHotelsResponse shape
     */
    public function __invoke($root, array $args, $context = null): array
    {
        $input = $args['input'] ?? [];
        $request = $context?->request();

        // Extract Resayil IDs from request attributes (set by ResayilContextMiddleware)
        $resayilMessageId = $request?->attributes->get('resayil_message_id');
        $resayilQuoteId = $request?->attributes->get('resayil_quote_id');

        // Resolve company from authenticated user (B2B always requires auth)
        $companyId = auth()->user()?->company?->id;

        // Guard: B2B path requires authenticated company (Pitfall 3 in RESEARCH.md)
        if ($companyId === null) {
            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'No authenticated company context. Company credentials are required.',
                'RECONFIGURE_CREDENTIALS'
            );
        }

        $destination = trim($input['destination'] ?? '');
        $checkin = trim($input['checkin'] ?? '');
        $checkout = trim($input['checkout'] ?? '');
        $currency = trim($input['currency'] ?? '') ?: (CompanyDotwCredential::where('company_id', $companyId)->value('currency') ?? 'USD');
        $rooms = $this->buildRoomsFromInput($input['rooms'] ?? []);
        $filters = $this->buildFilters($destination, $input['filters'] ?? []);

        // Build cache key — company_id embedded for per-company isolation (Pitfall 4 in RESEARCH.md)
        $cacheKey = $this->cache->buildKey($companyId, $destination, $checkin, $checkout, $rooms);
        $wasCached = $this->cache->isCached($cacheKey); // MUST be called BEFORE remember()

        try {
            $hotels = $this->cache->remember($cacheKey, function () use (
                $companyId, $checkin, $checkout, $currency, $rooms, $filters,
                $resayilMessageId, $resayilQuoteId
            ) {
                // Instantiate DotwService with company_id (Phase 1 B2B constructor path)
                $dotwService = new DotwService($companyId);

                $searchParams = [
                    'fromDate' => $checkin,
                    'toDate' => $checkout,
                    'currency' => $currency,
                    'rooms' => $rooms,
                    'filters' => $filters,
                ];

                // DotwService::searchHotels() handles audit logging internally (MSG-07/SEARCH-07).
                // Do NOT call DotwAuditService here — that would double-log the operation.
                return $dotwService->searchHotels(
                    $searchParams,
                    $resayilMessageId,
                    $resayilQuoteId,
                    $companyId
                );
            });
        } catch (RuntimeException $e) {
            // Credential errors thrown by DotwService constructor (CRED-05)
            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'DOTW credentials not configured for this company.',
                'RECONFIGURE_CREDENTIALS',
                $e->getMessage()
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'API_ERROR',
                'Hotel search failed. Please try again.',
                'RETRY',
                $e->getMessage()
            );
        }

        // Format hotels — apply per-company markup to each rate, build SearchHotelsResponse shape
        $formattedHotels = $this->formatHotels($hotels, $companyId);

        return [
            'success' => true,
            'error' => null,
            'cached' => $wasCached,
            'data' => [
                'hotels' => $formattedHotels,
                'total_count' => count($formattedHotels),
            ],
            'meta' => $this->buildMeta($companyId),
        ];
    }

    /**
     * Format raw hotel search results from DotwService::parseHotels() into SearchHotelsResponse shape.
     *
     * Applies per-company markup to every rate via DotwService::applyMarkup().
     * DotwService is instantiated once and reused across all hotels and rooms (not per-hotel).
     *
     * parseHotels() key mapping (confirmed from DotwService.php lines 1297-1344):
     * - hotel['hotelId']          → hotel_code
     * - room['adults']            → adults
     * - room['children']          → children
     * - room['childrenAges']      → children_ages
     * - rt['code']                → code
     * - rt['name']                → name
     * - rt['rateBasisId']         → rate_basis_id
     * - rt['rateType']            → currency_id  (= currencyid from DOTW XML)
     * - rt['nonRefundable']       → non_refundable (string 'yes'/'no' cast to bool)
     * - rt['total']               → total
     * - rt['totalTaxes']          → total_taxes
     * - rt['totalMinimumSelling'] → total_minimum_selling
     *
     * @param  array  $hotels  Raw hotels array from DotwService::searchHotels()
     * @param  int  $companyId  Company ID for per-company markup resolution
     * @return array Formatted hotels array matching [HotelSearchResult!]! schema type
     */
    private function formatHotels(array $hotels, int $companyId): array
    {
        // Instantiate DotwService once at start — reused across all hotels (not per-hotel instance)
        $dotwService = new DotwService($companyId);

        return array_map(function (array $hotel) use ($dotwService) {
            return [
                'hotel_code' => $hotel['hotelId'],
                'rooms' => array_map(function (array $room) use ($dotwService) {
                    return [
                        'adults' => $room['adults'],
                        'children' => $room['children'],
                        'children_ages' => $room['childrenAges'] ?? '',
                        'room_types' => array_map(function (array $rt) use ($dotwService) {
                            // Apply per-company markup to total fare (MARKUP-01, MARKUP-03)
                            $markup = $dotwService->applyMarkup((float) $rt['total']);

                            return [
                                'code' => $rt['code'],
                                'name' => $rt['name'],
                                'rate_basis_id' => $rt['rateBasisId'],
                                'currency_id' => $rt['rateType'],  // rateType = currencyid from DOTW XML
                                'non_refundable' => $rt['nonRefundable'] === 'yes',
                                'total' => (float) $rt['total'],
                                'markup' => $markup,           // RateMarkup type
                                'total_taxes' => (float) ($rt['totalTaxes'] ?? 0),
                                'total_minimum_selling' => (float) ($rt['totalMinimumSelling'] ?? 0),
                            ];
                        }, $room['roomTypes'] ?? []),
                    ];
                }, $hotel['rooms'] ?? []),
            ];
        }, $hotels);
    }

    /**
     * Map GraphQL SearchHotelRoomInput to DotwService rooms array format.
     *
     * Translates the camelCase GraphQL input fields to the array keys expected
     * by DotwService::buildRoomsXml() and the cache key normalizer.
     *
     * @param  array  $roomInputs  Array of SearchHotelRoomInput objects from GraphQL args
     * @return array Rooms array in DotwService format
     */
    private function buildRoomsFromInput(array $roomInputs): array
    {
        return array_map(fn (array $room) => [
            'adultsCode' => $room['adultsCode'],
            'children' => $room['children'] ?? [],
            'passengerNationality' => $room['passengerNationality'] ?? null,
            'passengerCountryOfResidence' => $room['passengerCountryOfResidence'] ?? null,
        ], $roomInputs);
    }

    /**
     * Map SearchHotelsFiltersInput to DotwService filter condition array.
     *
     * Builds the filter array structure consumed by DotwService::buildFilterXml().
     * City is always set from destination. Additional conditions are appended when
     * the corresponding filter input fields are present.
     *
     * DOTW filter semantics:
     * - Rating uses 'equals' (not 'between') — single value only
     * - Price uses 'between' — requires both min and max
     * - propertyType, mealplantype, cancellation use 'equals' with a single string value
     * - amenities uses 'equals' with an array of values
     *
     * @param  string  $destination  City/destination code (always set as city filter)
     * @param  array  $filterInput  SearchHotelsFiltersInput fields from GraphQL args
     * @return array DotwService filter array
     */
    private function buildFilters(string $destination, array $filterInput): array
    {
        $filters = ['city' => $destination];
        $conditions = [];

        // Rating filter — 'equals' for single value; 'between' not supported by DOTW for rating
        if (isset($filterInput['minRating'])) {
            $conditions[] = [
                'fieldName' => 'rating',
                'fieldTest' => 'equals',
                'fieldValues' => [(int) $filterInput['minRating']],
            ];
        } elseif (isset($filterInput['maxRating'])) {
            $conditions[] = [
                'fieldName' => 'rating',
                'fieldTest' => 'equals',
                'fieldValues' => [(int) $filterInput['maxRating']],
            ];
        }

        // Price filter — 'between' requires both min and max
        if (isset($filterInput['minPrice']) && isset($filterInput['maxPrice'])) {
            $conditions[] = [
                'fieldName' => 'price',
                'fieldTest' => 'between',
                'fieldValues' => [(float) $filterInput['minPrice'], (float) $filterInput['maxPrice']],
            ];
        }

        if (! empty($filterInput['propertyType'])) {
            $conditions[] = [
                'fieldName' => 'propertytype',
                'fieldTest' => 'equals',
                'fieldValues' => [$filterInput['propertyType']],
            ];
        }

        if (! empty($filterInput['mealPlanType'])) {
            $conditions[] = [
                'fieldName' => 'mealplantype',
                'fieldTest' => 'equals',
                'fieldValues' => [$filterInput['mealPlanType']],
            ];
        }

        if (! empty($filterInput['amenities'])) {
            $conditions[] = [
                'fieldName' => 'amenities',
                'fieldTest' => 'equals',
                'fieldValues' => $filterInput['amenities'],
            ];
        }

        if (! empty($filterInput['cancellationPolicy'])) {
            $conditions[] = [
                'fieldName' => 'cancellation',
                'fieldTest' => 'equals',
                'fieldValues' => [$filterInput['cancellationPolicy']],
            ];
        }

        if (! empty($conditions)) {
            $filters['conditions'] = $conditions;
        }

        return $filters;
    }

    /**
     * Build the DotwMeta array for this response.
     *
     * Uses app('dotw.trace_id') which is bound by DotwTraceMiddleware for every GraphQL request.
     *
     * @param  int  $companyId  The authenticated company identifier
     * @return array DotwMeta shape
     */
    private function buildMeta(int $companyId): array
    {
        return [
            'trace_id' => app('dotw.trace_id'),
            'request_id' => app('dotw.trace_id'),
            'timestamp' => now()->toIso8601String(),
            'company_id' => $companyId,
        ];
    }

    /**
     * Build a structured error response matching SearchHotelsResponse shape.
     *
     * @param  string  $code  DotwErrorCode enum value (e.g. CREDENTIALS_NOT_CONFIGURED, API_ERROR)
     * @param  string  $message  User-friendly error message suitable for WhatsApp display
     * @param  string  $action  DotwErrorAction enum value (e.g. RETRY, RECONFIGURE_CREDENTIALS)
     * @param  string|null  $details  Technical error details for debugging — never shown to end users
     * @return array SearchHotelsResponse shape with success: false
     */
    private function errorResponse(
        string $code,
        string $message,
        string $action,
        ?string $details = null
    ): array {
        return [
            'success' => false,
            'error' => [
                'error_code' => $code,
                'error_message' => $message,
                'error_details' => $details,
                'action' => $action,
            ],
            'cached' => false,
            'meta' => [
                'trace_id' => app('dotw.trace_id'),
                'request_id' => app('dotw.trace_id'),
                'timestamp' => now()->toIso8601String(),
                'company_id' => 0,
            ],
            'data' => null,
        ];
    }
}
