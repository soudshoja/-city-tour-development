<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Controllers;

use App\Modules\DotwAI\Http\Requests\GetHotelDetailsRequest;
use App\Modules\DotwAI\Http\Requests\SearchHotelsRequest;
use App\Modules\DotwAI\Services\DotwAIResponse;
use App\Modules\DotwAI\Services\HotelSearchService;
use App\Modules\DotwAI\Services\MessageBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Search controller for the DotwAI module.
 *
 * Thin controller that delegates to HotelSearchService for search logic
 * and MessageBuilderService for WhatsApp message formatting. Every response
 * goes through DotwAIResponse::success() or DotwAIResponse::error() to
 * guarantee the whatsappMessage field is always present.
 *
 * Extends Illuminate\Routing\Controller for module isolation (not the
 * app's base Controller).
 *
 * Endpoints:
 * - POST /api/dotwai/search_hotels   — Search hotels by city/name with filters
 * - POST /api/dotwai/get_hotel_details — Get room details for a specific hotel
 * - GET  /api/dotwai/get_cities       — Get city list for a country
 *
 * @see SRCH-01 Search hotels
 * @see SRCH-02 Hotel details
 * @see SRCH-03 City list
 */
class SearchController extends Controller
{
    /**
     * @param HotelSearchService  $searchService  Search orchestration service
     */
    public function __construct(
        private readonly HotelSearchService $searchService,
    ) {}

    /**
     * Search hotels by city/name with optional filters.
     *
     * POST /api/dotwai/search_hotels
     *
     * Request body:
     * - city (string, required): City name
     * - check_in (string, required): YYYY-MM-DD
     * - check_out (string, required): YYYY-MM-DD
     * - occupancy (array, required): [{adults: int, children_ages: int[]}]
     * - telephone (string, required): Phone number for agent resolution
     * - hotel (string, optional): Hotel name filter
     * - star_rating (int, optional): 1-5
     * - meal_type (string, optional): All|RoomOnly|Breakfast|HalfBoard|FullBoard|AllInclusive
     * - price_min (float, optional): Minimum price
     * - price_max (float, optional): Maximum price
     * - refundable (bool, optional): Only show refundable rates
     * - nationality (string, optional): Guest nationality country name
     *
     * Response:
     * - success: true
     * - data: {hotels: [...], total_found, showing, city_name, check_in, check_out}
     * - whatsappMessage: Bilingual numbered hotel list
     * - whatsappOptions: Suggested follow-up actions
     *
     * @param SearchHotelsRequest $request Validated search request
     * @return JsonResponse
     *
     * @see SRCH-01
     */
    public function searchHotels(SearchHotelsRequest $request): JsonResponse
    {
        try {
            $context = $request->attributes->get('dotwai_context');

            $result = $this->searchService->searchHotels($context, $request->validated());

            if (!empty($result['error'])) {
                return DotwAIResponse::error(
                    $result['code'] ?? DotwAIResponse::NO_RESULTS,
                    $result['message'] ?? 'Search failed',
                    MessageBuilderService::formatError($result['code'] ?? DotwAIResponse::NO_RESULTS),
                    $result['suggestedAction'] ?? null
                );
            }

            $whatsapp = MessageBuilderService::formatSearchResults(
                $result['hotels'] ?? [],
                (string) config('dotwai.display_currency', 'KWD')
            );
            $options = MessageBuilderService::buildWhatsAppOptions('search');

            return DotwAIResponse::success($result, $whatsapp, $options);
        } catch (\Exception $e) {
            Log::channel('dotw')->error('[DotwAI] Unexpected error in searchHotels', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return DotwAIResponse::error(
                'INTERNAL_ERROR',
                'An unexpected error occurred',
                MessageBuilderService::formatError('INTERNAL_ERROR'),
                'Please try again'
            );
        }
    }

    /**
     * Get room details for a specific hotel (browse mode, no rate blocking).
     *
     * POST /api/dotwai/get_hotel_details
     *
     * Request body:
     * - hotel_id (string, required): DOTW hotel/product ID
     * - check_in (string, required): YYYY-MM-DD
     * - check_out (string, required): YYYY-MM-DD
     * - occupancy (array, required): [{adults: int, children_ages: int[]}]
     * - telephone (string, required): Phone number for agent resolution
     *
     * Response:
     * - success: true
     * - data: {hotel: {...}, rooms: [...], hotel_id, check_in, check_out}
     * - whatsappMessage: Bilingual hotel details with room list
     * - whatsappOptions: Suggested follow-up actions
     *
     * @param GetHotelDetailsRequest $request Validated hotel details request
     * @return JsonResponse
     *
     * @see SRCH-02
     */
    public function getHotelDetails(GetHotelDetailsRequest $request): JsonResponse
    {
        try {
            $context = $request->attributes->get('dotwai_context');

            $result = $this->searchService->getHotelDetails(
                $context,
                $request->input('hotel_id'),
                $request->validated()
            );

            if (!empty($result['error'])) {
                return DotwAIResponse::error(
                    $result['code'] ?? DotwAIResponse::HOTEL_NOT_FOUND,
                    $result['message'] ?? 'Could not get hotel details',
                    MessageBuilderService::formatError($result['code'] ?? DotwAIResponse::HOTEL_NOT_FOUND),
                    $result['suggestedAction'] ?? null
                );
            }

            $whatsapp = MessageBuilderService::formatHotelDetails(
                $result['hotel'] ?? [],
                $result['rooms'] ?? [],
                (string) config('dotwai.display_currency', 'KWD')
            );
            $options = MessageBuilderService::buildWhatsAppOptions('details');

            return DotwAIResponse::success($result, $whatsapp, $options);
        } catch (\Exception $e) {
            Log::channel('dotw')->error('[DotwAI] Unexpected error in getHotelDetails', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return DotwAIResponse::error(
                'INTERNAL_ERROR',
                'An unexpected error occurred',
                MessageBuilderService::formatError('INTERNAL_ERROR'),
                'Please try again'
            );
        }
    }

    /**
     * Get city list for a country.
     *
     * GET /api/dotwai/get_cities?country=Kuwait&telephone=+96599800027
     *
     * Query parameters:
     * - country (string, required): Country name
     * - telephone (string, required): Phone number for agent resolution
     *
     * Response:
     * - success: true
     * - data: {cities: [{code, name, country_code}]}
     * - whatsappMessage: Bilingual numbered city list
     * - whatsappOptions: Suggested follow-up actions
     *
     * @param Request $request
     * @return JsonResponse
     *
     * @see SRCH-03
     */
    public function getCities(Request $request): JsonResponse
    {
        try {
            // Validate country parameter
            $request->validate([
                'country' => ['required', 'string', 'min:2'],
            ]);

            $result = $this->searchService->getCities($request->input('country'));

            // getCities returns either an error array or a flat array of cities
            if (is_array($result) && !empty($result['error'])) {
                return DotwAIResponse::error(
                    $result['code'] ?? DotwAIResponse::CITY_NOT_FOUND,
                    $result['message'] ?? 'Could not get cities',
                    MessageBuilderService::formatError($result['code'] ?? DotwAIResponse::CITY_NOT_FOUND),
                    $result['suggestedAction'] ?? null
                );
            }

            // result is a flat array of cities when successful
            $cities = $result;

            if (empty($cities)) {
                return DotwAIResponse::error(
                    DotwAIResponse::CITY_NOT_FOUND,
                    'No cities found for this country',
                    MessageBuilderService::formatError(DotwAIResponse::CITY_NOT_FOUND),
                    'Try a different country name.'
                );
            }

            $whatsapp = MessageBuilderService::formatCityList($cities);
            $options = MessageBuilderService::buildWhatsAppOptions('cities');

            return DotwAIResponse::success(['cities' => $cities], $whatsapp, $options);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return DotwAIResponse::error(
                DotwAIResponse::VALIDATION_ERROR,
                'Country parameter is required',
                MessageBuilderService::formatError(DotwAIResponse::VALIDATION_ERROR),
                'Provide a country name (e.g., country=Kuwait).'
            );
        } catch (\Exception $e) {
            Log::channel('dotw')->error('[DotwAI] Unexpected error in getCities', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return DotwAIResponse::error(
                'INTERNAL_ERROR',
                'An unexpected error occurred',
                MessageBuilderService::formatError('INTERNAL_ERROR'),
                'Please try again'
            );
        }
    }
}
