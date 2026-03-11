<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\DotwBooking;
use App\Services\DotwService;
use Illuminate\Support\Facades\Log;

/**
 * Resolver for the cancelBooking GraphQL mutation.
 *
 * Implements step 2 of the two-step DOTW cancellation flow (CANCEL-02).
 * Calls DOTW cancelbooking with confirm=yes and the penaltyApplied amount
 * that was returned by checkCancellation (step 1).
 *
 * Key rules:
 * - VALID-02: APR (non-refundable) bookings are rejected BEFORE any DOTW API call.
 *   Check hotel_details['is_apr'] on the DotwBooking record. If true, return VALIDATION_ERROR.
 * - If no DotwBooking record is found by confirmation_code (pre-v2.0 booking), skip APR check.
 * - penaltyApplied must be passed back verbatim — it was the charge returned by checkCancellation.
 * - productsLeftOnItinerary defaults to 0 (DotwService parseCancellation does not currently
 *   parse this DOTW field; 0 indicates full cancellation).
 * - DotwService instantiated in __invoke (per-request credential resolution).
 *
 * @see graphql/dotw.graphql CancelBookingResponse, CancelBookingInput, CancelBookingData
 * @see \App\Services\DotwService::cancelBooking()
 * @see \App\Models\DotwBooking
 */
class DotwCancelBooking
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args): array
    {
        $bookingCode    = (string) ($args['booking_code'] ?? '');
        $penaltyApplied = (float) ($args['penalty_applied'] ?? 0.0);
        $companyId      = (int) (auth()->user()?->company?->id ?? 0);

        if (empty($bookingCode)) {
            return $this->errorResponse('VALIDATION_ERROR', 'booking_code is required.', 'NONE');
        }

        // VALID-02: Reject APR (non-refundable) bookings before any DOTW API call
        $booking = DotwBooking::where('confirmation_code', $bookingCode)->first();

        if ($booking !== null) {
            $hotelDetails = (array) ($booking->hotel_details ?? []);
            $isApr        = (bool) ($hotelDetails['is_apr'] ?? false);

            if ($isApr) {
                return $this->errorResponse(
                    'VALIDATION_ERROR',
                    'APR (non-refundable) bookings cannot be cancelled. Cancel and amend are not available for advance purchase rates.',
                    'NONE'
                );
            }
        }
        // If booking record not found (pre-v2.0 booking), skip APR check and proceed

        try {
            $dotwService = new DotwService($companyId ?: null);

            // Step 2 of two-step cancel: confirm=yes with penaltyApplied commits the cancellation
            $result = $dotwService->cancelBooking([
                'bookingCode'    => $bookingCode,
                'confirm'        => 'yes',
                'penaltyApplied' => $penaltyApplied,
                'bookingType'    => '1',
            ]);

            // parseCancellation returns: bookingCode, refund, charge, status
            // productsLeftOnItinerary is not currently parsed by DotwService; default to 0
            $productsLeft = (int) ($result['productsLeftOnItinerary'] ?? 0);

            return [
                'success' => true,
                'error'   => null,
                'meta'    => $this->buildMeta($companyId),
                'cached'  => false,
                'data'    => [
                    'booking_code'               => $bookingCode,
                    'cancelled'                  => true,
                    'penalty_applied'            => $penaltyApplied,
                    'products_left_on_itinerary' => $productsLeft,
                ],
            ];

        } catch (\RuntimeException $e) {
            Log::channel('dotw')->error('cancelBooking credentials error', [
                'booking_code' => $bookingCode,
                'error'        => $e->getMessage(),
                'company_id'   => $companyId,
            ]);

            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'DOTW credentials not configured for this company.',
                'RECONFIGURE_CREDENTIALS'
            );

        } catch (\Exception $e) {
            Log::channel('dotw')->error('cancelBooking failed', [
                'booking_code'   => $bookingCode,
                'penalty_applied' => $penaltyApplied,
                'error'          => $e->getMessage(),
                'company_id'     => $companyId,
            ]);

            return $this->errorResponse('API_ERROR', 'Cancellation failed. Please try again or contact support.', 'RETRY');
        }
    }

    /**
     * Build a structured error response matching CancelBookingResponse shape.
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
