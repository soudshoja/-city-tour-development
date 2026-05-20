<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AkeedDotwAI\Services\HotelSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * REST endpoint for AkeedDotwAI hotel search.
 *
 * Module-owned URL (api/akeed-dotwai/search-hotels) — does NOT touch the
 * shared /graphql endpoint. n8n calls this directly. Response shape mirrors
 * the design contract from /dotwai skill.
 *
 * Phase 31 scope: search + browse only. Zero DB writes. No prebookKey.
 * Phase 33 will add a parallel /confirm-booking endpoint that owns block
 * mode and dotw_prebooks creation.
 */
class SearchController extends Controller
{
    public function __construct(
        private readonly HotelSearchService $search,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! config('akeed_dotwai.enabled', false)) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'AkeedDotwAI module is disabled',
            ], 503);
        }

        $validated = $request->validate([
            'telephone' => 'required|string',
            'city' => 'required|string',
            'hotel' => 'nullable|string',
            'guestNationality' => 'required|string',
            'checkIn' => 'required|string',
            'checkOut' => 'required|string',
            'occupancy' => 'required|array|min:1',
            'occupancy.*.adults' => 'required|integer|min:1',
            'occupancy.*.childrenAges' => 'array',
            'bookingType' => 'nullable|string|in:b2b,b2c',
        ]);

        $validated['telephone'] = $this->normalizePhone($validated['telephone']);

        try {
            return response()->json($this->resolveSearch($validated));
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::channel('dotw')->error('[AkeedDotwAI] search controller error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An unexpected error occurred. Please try again.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function resolveSearch(array $input): array
    {
        $searchResult = $this->search->search($input);

        if (($searchResult['status'] ?? null) === 'city_not_found') {
            return [
                'success' => false,
                'status' => 'city_not_found',
                'message' => $searchResult['message'] ?? 'City not found.',
            ];
        }

        if (($searchResult['status'] ?? null) === 'error') {
            return [
                'success' => false,
                'status' => 'error',
                'message' => $searchResult['message'] ?? 'Search failed.',
            ];
        }

        $hotels = $searchResult['hotels'] ?? [];

        if (empty($hotels)) {
            return [
                'success' => true,
                'status' => 'no_results',
                'message' => 'No hotels found matching your criteria.',
            ];
        }

        if (count($hotels) > 1) {
            return [
                'success' => true,
                'status' => 'multiple_hotels_found',
                'message' => 'Multiple hotels matched. Please select one.',
                'hotelOptions' => array_map(
                    fn (array $h): array => [
                        'name' => (string) ($h['hotel_name'] ?? ''),
                        'hotel_id' => (string) ($h['hotel_id'] ?? ''),
                        'city_name' => $h['city_name'] ?? null,
                    ],
                    $hotels
                ),
            ];
        }

        $hotel = $hotels[0];
        $browseResult = $this->search->browse(
            (string) ($hotel['hotel_id'] ?? ''),
            $input,
            (string) ($searchResult['nationality_code'] ?? ''),
            (string) ($searchResult['residence_code'] ?? '')
        );

        if (($browseResult['status'] ?? '') === 'error' || empty($browseResult['rates'])) {
            return [
                'success' => true,
                'status' => 'no_availability',
                'message' => $browseResult['message'] ?? 'No rooms available for the selected dates.',
            ];
        }

        return [
            'success' => true,
            'status' => 'hotel_found',
            'data' => [
                'hotel_name' => (string) ($hotel['hotel_name'] ?? ''),
                'hotel_id' => (string) ($hotel['hotel_id'] ?? ''),
                'hotel_address' => $hotel['hotel_address'] ?? null,
                'star_rating' => isset($hotel['star_rating']) ? (int) $hotel['star_rating'] : null,
                'city_name' => $hotel['city_name'] ?? null,
                'rooms' => $this->shapeRooms($browseResult['rates']),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rates
     * @return array<int, array<string, mixed>>
     */
    private function shapeRooms(array $rates): array
    {
        return array_map(function (array $rate): array {
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
