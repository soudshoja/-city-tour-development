<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\GraphQL\Queries;

use App\Modules\AkeedDotwAI\Services\HotelSearchService;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Log;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Lighthouse resolver for `searchDotwHotelRooms`.
 *
 * Delegates to HotelSearchService::search() → ::browse() and shapes the
 * output into the DotwSearchResult GraphQL type.
 *
 * Phase 31 scope: search + browse only. Zero DB writes. No PrebookService.
 * Phase 33 owns block mode and dotw_prebooks creation.
 *
 * @see graphql/akeed-dotwai.graphql — canonical output shape
 */
class SearchDotwHotelRooms
{
    public function __construct(
        private readonly HotelSearchService $search,
    ) {}

    /**
     * Resolve the `searchDotwHotelRooms` query.
     *
     * @param  mixed  $_  Root value (unused for top-level queries)
     * @param  array<string, mixed>  $args  GraphQL field arguments
     * @param  GraphQLContext|null  $context  Lighthouse context
     * @param  ResolveInfo|null  $resolveInfo  Resolve info (unused)
     * @return array<string, mixed> DotwSearchResult shape
     */
    public function __invoke(
        mixed $_,
        array $args,
        ?GraphQLContext $context = null,
        ?ResolveInfo $resolveInfo = null
    ): array {
        // First-line gate: module must be enabled
        if (! config('akeed_dotwai.enabled', false)) {
            throw new \RuntimeException('AkeedDotwAI module is disabled');
        }

        $input = $args['input'] ?? [];

        // Inline phone normalization — mirrors AttachDotwContext::normalize()
        if (isset($input['telephone'])) {
            $input['telephone'] = $this->normalizePhone($input['telephone']);
        }

        try {
            return $this->resolveSearch($input);
        } catch (\RuntimeException $e) {
            // Credential / configuration errors — surface message to caller
            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::channel('dotw')->error('[AkeedDotwAI] resolver error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'status' => 'error',
                'message' => 'An unexpected error occurred. Please try again.',
            ];
        }
    }

    /**
     * Core search + browse flow (separated so exceptions can bubble cleanly).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> DotwSearchResult shape
     */
    private function resolveSearch(array $input): array
    {
        // Step 1: search
        $searchResult = $this->search->search($input);

        // Step 2: propagate city-not-found / search errors
        if ($searchResult['status'] === 'city_not_found') {
            return [
                'success' => false,
                'status' => 'city_not_found',
                'message' => $searchResult['message'] ?? 'City not found.',
            ];
        }

        if ($searchResult['status'] === 'error') {
            return [
                'success' => false,
                'status' => 'error',
                'message' => $searchResult['message'] ?? 'Search failed.',
            ];
        }

        $hotels = $searchResult['hotels'] ?? [];

        // Step 3: no results
        if (empty($hotels)) {
            return [
                'success' => true,
                'status' => 'no_results',
                'message' => 'No hotels found matching your criteria.',
            ];
        }

        // Step 4: multiple hotels matched — ask caller to disambiguate
        if (count($hotels) > 1) {
            return [
                'success' => true,
                'status' => 'multiple_hotels_found',
                'message' => 'Multiple hotels matched. Please select one.',
                'hotelOptions' => array_map(
                    fn (array $h): array => [
                        'name' => $h['hotel_name'] ?? '',
                        'city_name' => $h['city_name'] ?? null,
                        'hotel_id' => isset($h['hotel_id']) ? (string) $h['hotel_id'] : null,
                    ],
                    $hotels
                ),
            ];
        }

        // Step 5: exactly one hotel — browse for room rates
        $hotel = $hotels[0];
        $browseResult = $this->search->browse(
            (string) ($hotel['hotel_id'] ?? ''),
            $input,
            $searchResult['nationality_code'] ?? '',
            $searchResult['residence_code'] ?? ''
        );

        // Step 6: no availability / browse error
        if (($browseResult['status'] ?? '') === 'error' || empty($browseResult['rates'])) {
            return [
                'success' => true,
                'status' => 'no_availability',
                'message' => $browseResult['message'] ?? 'No rooms available for the selected dates.',
            ];
        }

        // Step 7: shape rates for the GraphQL type
        $rooms = $this->shapeRooms($browseResult['rates']);

        // Step 8: return hotel_found envelope
        return [
            'success' => true,
            'status' => 'hotel_found',
            'data' => [
                'hotel_name' => $hotel['hotel_name'] ?? '',
                'hotel_id' => (string) ($hotel['hotel_id'] ?? ''),
                'hotel_address' => $hotel['hotel_address'] ?? null,
                'star_rating' => isset($hotel['star_rating']) ? (int) $hotel['star_rating'] : null,
                'city_name' => $hotel['city_name'] ?? null,
                'rooms' => $rooms,
            ],
        ];
    }

    /**
     * Shape parsed rates from HotelSearchService::parseRates() into the
     * DotwRoomResult GraphQL type. Fields are passed through unchanged since
     * parseRates() already produces the canonical shape.
     *
     * @param  array<int, array<string, mixed>>  $rates
     * @return array<int, array<string, mixed>>
     */
    private function shapeRooms(array $rates): array
    {
        return array_map(function (array $rate): array {
            // Map cancellation_rules → cancel_policies (GraphQL field name)
            $cancelPolicies = array_map(
                fn (array $rule): array => [
                    'fromDate' => $rule['fromDate'] ?? '',
                    'toDate' => $rule['toDate'] ?? null,
                    'chargeType' => $rule['chargeType'] ?? null,
                    'charge' => (float) ($rule['charge'] ?? 0),
                    'cancelRestricted' => $rule['cancelRestricted'] ?? null,
                    'amendRestricted' => $rule['amendRestricted'] ?? null,
                ],
                $rate['cancellation_rules'] ?? []
            );

            return [
                'room_name' => $rate['room_name'] ?? '',
                'room_type_code' => $rate['room_type_code'] ?? '',
                'rate_basis_id' => $rate['rate_basis_id'] ?? '',
                'rate_basis_desc' => $rate['rate_basis_desc'] ?? '',
                'meal_type' => $rate['meal_type'] ?? '',
                'max_occupancy' => isset($rate['max_occupancy']) ? (int) $rate['max_occupancy'] : null,
                'twin' => (bool) ($rate['twin'] ?? false),
                'browse_allocation_details' => $rate['browse_allocation_details'] ?? '',
                'displayed_price' => (float) ($rate['displayed_price'] ?? 0.0),
                'original_total_fare' => (float) ($rate['original_total_fare'] ?? 0.0),
                'currency' => $rate['currency'] ?? '',
                'is_refundable' => (bool) ($rate['is_refundable'] ?? true),
                'is_apr' => (bool) ($rate['is_apr'] ?? false),
                'cancel_policies' => $cancelPolicies,
                'tariff_notes' => $rate['tariff_notes'] ?? null,
                'specials' => array_map(
                    fn (array $s): array => [
                        'type' => $s['type'] ?? '',
                        'name' => $s['name'] ?? '',
                        'description' => $s['description'] ?? null,
                        'condition' => $s['condition'] ?? null,
                    ],
                    $rate['specials'] ?? []
                ),
                'min_stay' => isset($rate['min_stay']) ? (int) $rate['min_stay'] : null,
                'min_stay_date' => $rate['min_stay_date'] ?? null,
            ];
        }, $rates);
    }

    /**
     * Normalize a phone number for cache key use — mirrors AttachDotwContext::normalize().
     *
     * @param  string  $phone  Raw phone from GraphQL input
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
}
