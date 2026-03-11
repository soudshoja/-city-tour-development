<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Services\DotwAuditService;
use App\Services\DotwService;
use Illuminate\Support\Facades\Log;

/**
 * Resolver for the bookItinerary GraphQL mutation.
 *
 * Confirms a previously saved itinerary via the DOTW bookitinerary command.
 * This is step 2 of the APR (non-refundable) booking flow:
 *   saveBooking → bookItinerary
 *
 * The bookItinerary method in DotwService already exists and is called
 * directly here — no re-implementation of API logic.
 *
 * Key rules:
 * - BOOK-01: Called after saveBooking returns an itinerary_code.
 * - Receives itinerary_code: String! from saveBooking response.
 * - Calls DotwService::bookItinerary() which wraps DOTW bookitinerary XML command.
 * - Returns booking_code (the final DOTW confirmation code) and booking_status.
 * - On failure: returns API_ERROR with action RETRY.
 *
 * @see graphql/dotw.graphql BookItineraryResponse, BookItineraryInput, BookItineraryData
 * @see \App\Services\DotwService::bookItinerary()
 * @see \App\GraphQL\Mutations\DotwSaveBooking
 */
class DotwBookItinerary
{
    public function __construct(
        private readonly DotwAuditService $auditService,
    ) {}

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args): array
    {
        $itineraryCode = (string) ($args['itinerary_code'] ?? '');

        if ($itineraryCode === '') {
            return $this->errorResponse('VALIDATION_ERROR', 'itinerary_code is required.', 'RETRY');
        }

        // Extract Resayil context from request attributes
        $request = request();
        $resayilMessageId = $request->attributes->get('resayil_message_id')
            ?? $request->header('X-Resayil-Message-ID');
        $resayilQuoteId = $request->attributes->get('resayil_quote_id')
            ?? $request->header('X-Resayil-Quote-ID');

        $companyId = auth()->user()?->company?->id;

        try {
            $dotwService = new DotwService($companyId);

            // bookItinerary() already exists in DotwService — wraps bookitinerary XML command
            $confirmation = $dotwService->bookItinerary(
                $itineraryCode,
                $resayilMessageId,
                $resayilQuoteId,
                $companyId
            );

            $bookingCode = (string) ($confirmation['bookingCode'] ?? '');
            $bookingStatus = (string) ($confirmation['status'] ?? 'confirmed');

            // Supplementary audit log
            try {
                $this->auditService->log(
                    DotwAuditService::OP_BOOK,
                    ['itinerary_code' => $itineraryCode],
                    ['booking_code' => $bookingCode, 'status' => $bookingStatus, 'type' => 'book_itinerary'],
                    $resayilMessageId,
                    $resayilQuoteId,
                    $companyId
                );
            } catch (\Throwable) {
                // Fail-silent
            }

            return [
                'success' => true,
                'error' => null,
                'meta' => $this->buildMeta($companyId ?? 0),
                'cached' => false,
                'data' => [
                    'booking_code' => $bookingCode,
                    'booking_status' => $bookingStatus,
                ],
            ];

        } catch (\RuntimeException $e) {
            Log::channel('dotw')->error('bookItinerary credentials error', [
                'itinerary_code' => $itineraryCode,
                'error' => $e->getMessage(),
                'company_id' => $companyId,
            ]);

            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'DOTW credentials not configured for this company.',
                'RECONFIGURE_CREDENTIALS'
            );

        } catch (\Exception $e) {
            Log::channel('dotw')->error('bookItinerary failed', [
                'itinerary_code' => $itineraryCode,
                'error' => $e->getMessage(),
                'company_id' => $companyId,
            ]);

            return $this->errorResponse(
                'API_ERROR',
                'Failed to confirm itinerary booking. Please try again.',
                'RETRY'
            );
        }
    }

    /**
     * Build a structured error response matching BookItineraryResponse shape.
     *
     * @return array<string, mixed>
     */
    private function errorResponse(string $code, string $message, string $action): array
    {
        return [
            'success' => false,
            'error' => [
                'error_code' => $code,
                'error_message' => $message,
                'error_details' => null,
                'action' => $action,
            ],
            'meta' => $this->buildMeta(0),
            'cached' => false,
            'data' => null,
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
            'trace_id' => app('dotw.trace_id'),
            'request_id' => app('dotw.trace_id'),
            'timestamp' => now()->toIso8601String(),
            'company_id' => $companyId,
        ];
    }
}
