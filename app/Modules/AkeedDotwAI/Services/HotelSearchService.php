<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Services;

use App\Services\DotwService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * B2C hotel search and browse service for the AkeedDotwAI module.
 *
 * Phase 31 scope: search + browse + cache only. NO block mode. NO DB writes.
 * Block-late pattern: Phase 33 owns block at "click pay" + re-block fallback.
 *
 * Both public methods are wrapped in Cache::remember() so repeated interactive
 * shopping calls within a session avoid redundant DOTW round-trips. Browse
 * results (allocationDetails) remain valid bookmarks for Phase 33's fresh
 * block call; if the rate drifts, Phase 33's block returns a non-'checked'
 * status and triggers the customer-facing error path independently.
 *
 * DotwService is instantiated inside each method to match the B2B pattern used
 * by App\Modules\DotwAI\Services\HotelSearchService.
 */
class HotelSearchService
{
    /**
     * @param  FuzzyMatcherService  $fuzzy  City/country fuzzy resolver
     */
    public function __construct(
        private readonly FuzzyMatcherService $fuzzy,
    ) {}

    /**
     * Search hotels by city, date range, and occupancy.
     *
     * Flow:
     *  1. assertEnabled gate
     *  2. Resolve city name → DOTW city code (null → city_not_found envelope)
     *  3. Resolve guestNationality → DOTW country code (fallback to input value)
     *  4. Normalise phone for cache key
     *  5. Cache::remember → DotwService::searchHotels
     *  6. Optional hotel-name post-filter
     *
     * @param array{
     *   city: string,
     *   checkIn: string,
     *   checkOut: string,
     *   occupancy: array<int, array{adults: int, childrenAges?: int[]}>,
     *   guestNationality: string,
     *   telephone?: string,
     *   hotel?: string,
     * } $input
     * @return array{
     *   status: string,
     *   hotels?: array,
     *   city_code?: string,
     *   nationality_code?: string,
     *   residence_code?: string,
     *   message?: string,
     * }
     *
     * @throws \RuntimeException when module is disabled
     */
    public function search(array $input): array
    {
        $this->assertEnabled();

        $cityCode = $this->fuzzy->resolveCityCode($input['city']);
        if ($cityCode === null) {
            return [
                'status' => 'city_not_found',
                'message' => "Could not resolve city: {$input['city']}",
            ];
        }

        $nationality = $this->fuzzy->resolveCountryCode($input['guestNationality']) ?? $input['guestNationality'];
        $residence = $nationality;

        $phone = $this->normalizePhone($input['telephone'] ?? '');
        $cacheKey = "akeed:search:{$phone}:{$cityCode}:{$input['checkIn']}:{$input['checkOut']}";
        $ttl = (int) config('akeed_dotwai.search_cache_ttl_seconds', 600);
        $companyId = (int) config('akeed_dotwai.company_id');

        return Cache::remember(
            $cacheKey,
            $ttl,
            function () use ($input, $cityCode, $nationality, $residence, $companyId): array {
                $params = [
                    'fromDate' => $input['checkIn'],
                    'toDate' => $input['checkOut'],
                    'currency' => config('dotw.default_currency', '520'),
                    'city' => $cityCode,
                    'rooms' => $this->buildRoomsArray($input['occupancy'], $nationality, $residence),
                    'filters' => ['city' => $cityCode],
                ];

                try {
                    $dotw = new DotwService($companyId);
                    $hotels = $dotw->searchHotels($params, null, null, $companyId);
                } catch (\RuntimeException $e) {
                    return ['status' => 'error', 'message' => $e->getMessage()];
                } catch (\Exception $e) {
                    Log::channel('dotw')->error('[AkeedDotwAI] search error', [
                        'error' => $e->getMessage(),
                        'city' => $input['city'],
                        'company_id' => $companyId,
                    ]);

                    return ['status' => 'error', 'message' => 'DOTW search failed'];
                }

                // Optional hotel-name post-filter (case-insensitive substring)
                if (! empty($input['hotel'])) {
                    $hotels = array_values(
                        array_filter(
                            $hotels,
                            fn (array $h) => stripos($h['hotel_name'] ?? '', $input['hotel']) !== false
                        )
                    );
                }

                return [
                    'status' => 'hotel_found',
                    'hotels' => $hotels,
                    'city_code' => $cityCode,
                    'nationality_code' => $nationality,
                    'residence_code' => $residence,
                ];
            }
        );
    }

    /**
     * Browse a specific hotel for available room rates (no allocation block).
     *
     * Calls DotwService::getRooms with blocking=false. The `blocking` argument
     * is hard-coded to false here and MUST NOT be changed to true in Phase 31.
     * Phase 33 owns block mode entirely.
     *
     * Returned rates include `browse_allocation_details` which Phase 33 uses as
     * a bookmark for its fresh browse + block call.
     *
     * @param  string  $hotelId  DOTW hotel / product ID
     * @param array{
     *   checkIn: string,
     *   checkOut: string,
     *   occupancy: array<int, array{adults: int, childrenAges?: int[]}>,
     *   telephone?: string,
     * } $input
     * @param  string  $nationality  DOTW country code (from search() result)
     * @param  string  $residence  DOTW country code (from search() result)
     * @return array{
     *   status: string,
     *   rates?: array,
     *   message?: string,
     * }
     *
     * @throws \RuntimeException when module is disabled
     */
    public function browse(string $hotelId, array $input, string $nationality, string $residence): array
    {
        $this->assertEnabled();

        $phone = $this->normalizePhone($input['telephone'] ?? '');
        $cacheKey = "akeed:browse:{$phone}:{$hotelId}:{$input['checkIn']}:{$input['checkOut']}";
        $ttl = (int) config('akeed_dotwai.search_cache_ttl_seconds', 600);
        $companyId = (int) config('akeed_dotwai.company_id');

        return Cache::remember(
            $cacheKey,
            $ttl,
            function () use ($hotelId, $input, $nationality, $residence, $companyId): array {
                $params = [
                    'fromDate' => $input['checkIn'],
                    'toDate' => $input['checkOut'],
                    'currency' => config('dotw.default_currency', '520'),
                    'rooms' => $this->buildRoomsArray($input['occupancy'], $nationality, $residence),
                    'productId' => $hotelId,
                ];

                try {
                    $dotw = new DotwService($companyId);
                    // Phase 31 = browse mode only. blocking=false is intentional and MUST remain false.
                    // Block lives in Phase 33. Never change this to true here.
                    $rawRates = $dotw->getRooms($params, false, null, null, $companyId);
                } catch (\RuntimeException $e) {
                    return ['status' => 'error', 'message' => $e->getMessage()];
                } catch (\Exception $e) {
                    Log::channel('dotw')->error('[AkeedDotwAI] browse error', [
                        'error' => $e->getMessage(),
                        'hotel_id' => $hotelId,
                        'company_id' => $companyId,
                    ]);

                    return ['status' => 'error', 'message' => 'DOTW browse failed'];
                }

                if (empty($rawRates)) {
                    return ['status' => 'no_availability', 'rates' => [], 'message' => null];
                }

                $rates = $this->parseRates($rawRates);

                return ['status' => 'rates_found', 'rates' => $rates, 'message' => null];
            }
        );
    }

    /**
     * Guard: throw immediately when the module is switched off.
     *
     * @throws \RuntimeException
     */
    private function assertEnabled(): void
    {
        if (! config('akeed_dotwai.enabled', false)) {
            throw new \RuntimeException('AkeedDotwAI module is disabled');
        }
    }

    /**
     * Build DOTW rooms array from B2C occupancy input.
     *
     * Maps each room's adults/childrenAges to DOTW-accepted field names and
     * attaches the resolved nationality / residence codes.
     *
     * @param  array<int, array{adults?: int, childrenAges?: int[]}>  $occupancy
     * @param  string  $nationality  DOTW country code
     * @param  string  $residence  DOTW country code
     * @return array<int, array{adults: int, childrenAges: int[], nationality: string, residence: string}>
     */
    private function buildRoomsArray(array $occupancy, string $nationality, string $residence): array
    {
        $rooms = [];
        foreach ($occupancy as $index => $occ) {
            $rooms[] = [
                'no' => $index + 1,
                'adultsCode' => (int) ($occ['adults'] ?? 2),
                'children' => $occ['childrenAges'] ?? [],
                'passengerNationality' => $nationality,
                'passengerCountryOfResidence' => $residence,
            ];
        }

        return $rooms;
    }

    /**
     * Normalise a phone number into E.164 format for use as a cache key segment.
     *
     * Strips non-digit characters, removes leading zeros, and prepends the
     * configured country code (default 965 = Kuwait) when the remaining digits
     * look like a local 8-digit number.
     *
     * @param  string  $phone  Raw phone string from input
     * @return string E.164-ish phone, e.g. "+96512345678"
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phone = ltrim($phone, '0');

        if (strlen($phone) === 8) {
            $phone = config('akeed_dotwai.default_country_code', '965').$phone;
        }

        return '+'.$phone;
    }

    /**
     * Parse raw getRooms API response into the AkeedDotwAI browse output shape.
     *
     * Each rate entry contains the fields that Phase 33 needs to issue a block
     * request: room_type_code, rate_basis_id, browse_allocation_details, and
     * displayed_price (post-markup ceiling). No prebook key is stored; the
     * caller holds these values conversationally.
     *
     * @param  array  $rawRates  Raw response from DotwService::getRooms
     * @return array<int, array{
     *   room_name: string,
     *   room_type_code: string,
     *   rate_basis_id: string,
     *   rate_basis_desc: string,
     *   browse_allocation_details: string,
     *   meal_type: string,
     *   max_occupancy: int|null,
     *   twin: bool,
     *   original_total_fare: float,
     *   currency: string,
     *   displayed_price: float,
     *   is_refundable: bool,
     *   is_apr: bool,
     *   cancellation_rules: array,
     *   specials: array,
     *   tariff_notes: string,
     *   min_stay: int|null,
     *   min_stay_date: string|null,
     * }>
     */
    private function parseRates(array $rawRates): array
    {
        $markupMul = (float) config('akeed_dotwai.b2c_markup', 0.20);
        $currency = config('akeed_dotwai.display_currency', config('dotw.default_currency', '520'));
        $parsed = [];

        foreach ($rawRates as $room) {
            $roomName = $room['roomName'] ?? ($room['room_name'] ?? '');
            $roomTypeCode = $room['roomTypeCode'] ?? ($room['room_type_code'] ?? '');
            $roomSpecials = $room['specials'] ?? [];

            // Details may live under 'details' (B2B parseRooms shape) or directly
            $details = $room['details'] ?? [$room];

            foreach ($details as $detail) {
                $rateBasisId = (string) ($detail['id'] ?? $detail['rateBasisId'] ?? $detail['rate_basis_id'] ?? '');
                $rateBasisDesc = (string) ($detail['rateBasisDesc'] ?? $detail['rate_basis_desc'] ?? '');
                $allocationDetails = (string) ($detail['allocationDetails'] ?? $detail['browse_allocation_details'] ?? '');
                $originalFare = (float) ($detail['price'] ?? $detail['total'] ?? $detail['original_total_fare'] ?? 0.0);
                $tariffNotes = (string) ($detail['tariffNotes'] ?? $detail['tariff_notes'] ?? '');
                $cancellationRules = (array) ($detail['cancellationRules'] ?? $detail['cancellation_rules'] ?? []);
                $minStay = isset($detail['minStay']) && $detail['minStay'] !== '' ? (int) $detail['minStay'] : null;
                $minStayDate = isset($detail['dateApplyMinStay']) && $detail['dateApplyMinStay'] !== ''
                    ? (string) $detail['dateApplyMinStay']
                    : null;
                $maxOccupancy = isset($detail['maxOccupancy']) ? (int) $detail['maxOccupancy'] : null;
                $twin = (bool) ($detail['twin'] ?? false);
                $specialsApplied = (array) ($detail['specialsApplied'] ?? []);

                // Refundability: non-refundable when all cancellation rules restrict cancel
                $isRefundable = true;
                if (! empty($cancellationRules)) {
                    $allRestricted = array_reduce(
                        $cancellationRules,
                        fn (bool $carry, array $rule): bool => $carry && (($rule['cancelRestricted'] ?? false) === true),
                        true
                    );
                    if ($allRestricted) {
                        $isRefundable = false;
                    }
                }

                // B2C markup: ceil(original * (1 + markupMul))
                $displayedPrice = (float) ceil($originalFare * (1.0 + $markupMul));

                // Meal type from rateBasisId (mirrors B2B mapping)
                $mealType = $this->mapRateBasisToMealType($rateBasisId);

                // Specials
                $specials = $this->mapSpecials($roomSpecials, $specialsApplied);

                // Structured cancellation rules
                $mappedRules = array_map(
                    fn (array $rule): array => [
                        'fromDate' => $rule['fromDate'] ?? '',
                        'toDate' => $rule['toDate'] ?? '',
                        'charge' => (float) ($rule['charge'] ?? $rule['cancelCharge'] ?? 0),
                        'chargeType' => ! empty($rule['charge']) ? 'Fixed' : 'Percentage',
                        'cancelRestricted' => $rule['cancelRestricted'] ?? false,
                    ],
                    $cancellationRules
                );

                $parsed[] = [
                    'room_name' => $roomName,
                    'room_type_code' => $roomTypeCode,
                    'rate_basis_id' => $rateBasisId,
                    'rate_basis_desc' => $rateBasisDesc,
                    'browse_allocation_details' => $allocationDetails,
                    'meal_type' => $mealType,
                    'max_occupancy' => $maxOccupancy,
                    'twin' => $twin,
                    'original_total_fare' => $originalFare,
                    'currency' => $currency,
                    'displayed_price' => $displayedPrice,
                    'is_refundable' => $isRefundable,
                    'is_apr' => false, // APR removed by DOTW (Olga Chicu, March 2026)
                    'cancellation_rules' => $mappedRules,
                    'specials' => $specials,
                    'tariff_notes' => $tariffNotes,
                    'min_stay' => $minStay,
                    'min_stay_date' => $minStayDate,
                ];
            }
        }

        return $parsed;
    }

    /**
     * Map a DOTW rate basis ID to a human-readable meal type label.
     *
     * Mirrors the mapping in App\Modules\DotwAI\Services\HotelSearchService.
     *
     * @param  string  $rateBasisId  DOTW rateBasis ID
     * @return string Meal type label (e.g. "Breakfast", "Room Only")
     */
    private function mapRateBasisToMealType(string $rateBasisId): string
    {
        return match ($rateBasisId) {
            '0' => 'Room Only',
            '1331' => 'Breakfast',
            '1332' => 'Breakfast',
            '1333' => 'Half Board',
            '1334' => 'Half Board',
            '1335' => 'Full Board',
            '1336' => 'All Inclusive',
            default => 'Room Only',
        };
    }

    /**
     * Merge room-level and rate-level specials into a uniform shape.
     *
     * @param  array  $roomSpecials  Room-level specials (strings from DOTW)
     * @param  array  $specialsApplied  Rate-level applied special references
     * @return array<int, array{type: string, name: string, description: string}>
     */
    private function mapSpecials(array $roomSpecials, array $specialsApplied): array
    {
        $mapped = [];

        foreach ($roomSpecials as $special) {
            if (is_string($special) && $special !== '') {
                $mapped[] = ['type' => 'promotion', 'name' => $special, 'description' => $special];
            }
        }

        foreach ($specialsApplied as $ref) {
            if (is_string($ref) && $ref !== '') {
                $mapped[] = ['type' => 'applied', 'name' => $ref, 'description' => $ref];
            }
        }

        return $mapped;
    }
}
