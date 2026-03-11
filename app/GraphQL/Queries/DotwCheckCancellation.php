<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Services\DotwService;
use Illuminate\Support\Facades\Log;

/**
 * Resolver for the checkCancellation GraphQL query.
 *
 * Implements step 1 of the two-step DOTW cancellation flow (CANCEL-01).
 * Calls DOTW cancelbooking with confirm=no to return the penalty charge
 * WITHOUT committing the cancellation. The caller must pass the returned
 * charge value as penalty_applied to cancelBooking (step 2).
 *
 * Key rules:
 * - confirm=no means this is a non-destructive charge query — no booking is cancelled.
 * - bookingType defaults to '1' (Hotel) per DOTW V4 cancelbooking spec.
 * - is_outside_deadline = true when charge is 0.0 (free cancellation window).
 * - DotwService instantiated in __invoke (per-request credential resolution).
 *
 * @see graphql/dotw.graphql CheckCancellationResponse, CheckCancellationInput, CancellationChargeData
 * @see \App\Services\DotwService::cancelBooking()
 */
class DotwCheckCancellation
{
    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args): array
    {
        $bookingCode = (string) ($args['booking_code'] ?? '');
        $companyId = (int) (auth()->user()?->company?->id ?? 0);

        if (empty($bookingCode)) {
            return $this->errorResponse('VALIDATION_ERROR', 'booking_code is required.', 'NONE');
        }

        try {
            $dotwService = new DotwService($companyId ?: null);

            // Step 1 of two-step cancel: confirm=no queries charge without committing
            $result = $dotwService->cancelBooking([
                'bookingCode'  => $bookingCode,
                'confirm'      => 'no',
                'bookingType'  => '1',
            ]);

            // parseCancellation returns: bookingCode, refund, charge, status
            $charge = (float) ($result['charge'] ?? 0.0);

            return [
                'success' => true,
                'error'   => null,
                'meta'    => $this->buildMeta($companyId),
                'cached'  => false,
                'data'    => [
                    'booking_code'       => $bookingCode,
                    'charge'             => $charge,
                    'currency'           => '',  // DOTW does not return currency in confirm=no response
                    'is_outside_deadline' => $charge === 0.0,
                ],
            ];

        } catch (\RuntimeException $e) {
            Log::channel('dotw')->error('checkCancellation credentials error', [
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
            Log::channel('dotw')->error('checkCancellation failed', [
                'booking_code' => $bookingCode,
                'error'        => $e->getMessage(),
                'company_id'   => $companyId,
            ]);

            return $this->errorResponse('API_ERROR', 'Failed to retrieve cancellation charge.', 'NONE');
        }
    }

    /**
     * Build a structured error response matching CheckCancellationResponse shape.
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
