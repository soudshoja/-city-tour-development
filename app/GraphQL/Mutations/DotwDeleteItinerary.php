<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Services\DotwService;
use Illuminate\Support\Facades\Log;

/**
 * Resolver for the deleteItinerary GraphQL mutation.
 *
 * Calls DOTW deleteitinerary command to remove a saved (unconfirmed) itinerary
 * created by the saveBooking step in the APR booking flow (CANCEL-03).
 *
 * Key rules:
 * - Only unconfirmed (saved) itineraries can be deleted — use cancelBooking for confirmed bookings.
 * - The itinerary_code is the value returned by saveBooking (from DOTW savebooking response).
 * - DotwService instantiated in __invoke (per-request credential resolution).
 *
 * @see graphql/dotw.graphql DeleteItineraryResponse, DeleteItineraryInput, DeleteItineraryData
 * @see \App\Services\DotwService::deleteItinerary()
 */
class DotwDeleteItinerary
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args): array
    {
        $itineraryCode = (string) ($args['itinerary_code'] ?? '');
        $companyId     = (int) (auth()->user()?->company?->id ?? 0);

        if (empty($itineraryCode)) {
            return $this->errorResponse('VALIDATION_ERROR', 'itinerary_code is required.', 'NONE');
        }

        try {
            $dotwService = new DotwService($companyId ?: null);

            $result = $dotwService->deleteItinerary($itineraryCode);

            return [
                'success' => true,
                'error'   => null,
                'meta'    => $this->buildMeta($companyId),
                'cached'  => false,
                'data'    => [
                    'itinerary_code' => $itineraryCode,
                    'deleted'        => (bool) ($result['deleted'] ?? true),
                ],
            ];

        } catch (\RuntimeException $e) {
            Log::channel('dotw')->error('deleteItinerary credentials error', [
                'itinerary_code' => $itineraryCode,
                'error'          => $e->getMessage(),
                'company_id'     => $companyId,
            ]);

            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'DOTW credentials not configured for this company.',
                'RECONFIGURE_CREDENTIALS'
            );

        } catch (\Exception $e) {
            Log::channel('dotw')->error('deleteItinerary failed', [
                'itinerary_code' => $itineraryCode,
                'error'          => $e->getMessage(),
                'company_id'     => $companyId,
            ]);

            return $this->errorResponse('API_ERROR', 'Failed to delete itinerary. Please try again or contact support.', 'RETRY');
        }
    }

    /**
     * Build a structured error response matching DeleteItineraryResponse shape.
     *
     * @return array<string, mixed>
     */
    private function errorResponse(string $code, string $message, string $action): array
    {
        return [
            'success' => false,
            'error'   => [
                'error_code'    => $code,
                'error_message' => $message,
                'error_details' => null,
                'action'        => $action,
            ],
            'meta'   => $this->buildMeta(0),
            'cached' => false,
            'data'   => null,
        ];
    }

    /**
     * Build DotwMeta array — identical pattern across all DOTW resolvers.
     *
     * @return array<string, mixed>
     */
    private function buildMeta(int $companyId): array
    {
        return [
            'trace_id'   => app('dotw.trace_id'),
            'request_id' => app('dotw.trace_id'),
            'timestamp'  => now()->toIso8601String(),
            'company_id' => $companyId,
        ];
    }
}
