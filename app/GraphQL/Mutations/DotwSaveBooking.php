<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\DotwPrebook;
use App\Services\DotwAuditService;
use App\Services\DotwService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resolver for the saveBooking GraphQL mutation.
 *
 * Saves a hotel booking as an itinerary via the DOTW savebooking command.
 * This is step 1 of the APR (non-refundable) booking flow:
 *   saveBooking → bookItinerary
 *
 * The saveBooking and bookItinerary methods in DotwService already exist and
 * are called directly here — no re-implementation of API logic.
 *
 * Key rules:
 * - BOOK-01: Called when prebook has is_refundable=false (APR flow).
 * - Loads prebook by prebook_key — returns error if not found or expired.
 * - Builds confirmParams identical to DotwCreatePreBooking (same param structure).
 * - Calls DotwService::saveBooking() which wraps DOTW savebooking XML command.
 * - Returns itinerary_code (the itinerary code to pass to bookItinerary).
 * - Expires prebook after successful save (same pattern as createPreBooking).
 *
 * @see graphql/dotw.graphql SaveBookingResponse, SaveBookingInput, SaveBookingData
 * @see \App\Services\DotwService::saveBooking()
 * @see \App\GraphQL\Mutations\DotwBookItinerary
 */
class DotwSaveBooking
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
        $input = $args;
        $prebookKey = (string) ($input['prebook_key'] ?? '');
        $checkin = (string) ($input['checkin'] ?? '');
        $checkout = (string) ($input['checkout'] ?? '');
        $passengers = (array) ($input['passengers'] ?? []);
        $rooms = (array) ($input['rooms'] ?? []);

        // Extract Resayil context from request attributes (set by ResayilContextMiddleware)
        $request = request();
        $resayilMessageId = $request->attributes->get('resayil_message_id')
            ?? $request->header('X-Resayil-Message-ID');
        $resayilQuoteId = $request->attributes->get('resayil_quote_id')
            ?? $request->header('X-Resayil-Quote-ID');

        // --- Step 1: Load and validate prebook ---
        $prebook = DotwPrebook::where('prebook_key', $prebookKey)->first();

        if ($prebook === null) {
            return $this->errorResponse('VALIDATION_ERROR', 'Prebook not found.', 'RESEARCH');
        }

        $companyId = $prebook->company_id;

        // Check expiry BEFORE any DOTW API call
        if (! $prebook->isValid()) {
            return $this->errorResponse(
                'ALLOCATION_EXPIRED',
                'Rate offer expired, please search again.',
                'RESEARCH'
            );
        }

        // --- Step 2: Build saveBooking params (same structure as confirmBooking) ---
        $dotwService = new DotwService($companyId);
        $leadPassengerEmail = (string) ($passengers[0]['email'] ?? '');
        $customerReference = (string) Str::uuid();

        $formattedPassengers = array_map(fn (array $p) => [
            'salutation' => (int) ($p['salutation'] ?? 1),
            'firstName' => (string) ($p['firstName'] ?? ''),
            'lastName' => (string) ($p['lastName'] ?? ''),
        ], $passengers);

        $firstRoom = $rooms[0] ?? [];
        $adultsCount = (int) ($firstRoom['adultsCode'] ?? $firstRoom['adults'] ?? 2);
        $childrenAges = (array) ($firstRoom['children'] ?? []);

        $saveParams = [
            'fromDate' => $checkin,
            'toDate' => $checkout,
            'currency' => $prebook->original_currency ?: 'USD',
            'productId' => $prebook->hotel_code,
            'sendCommunicationTo' => $leadPassengerEmail,
            'customerReference' => $customerReference,
            'rooms' => [
                [
                    'roomTypeCode' => $prebook->room_type,
                    'selectedRateBasis' => $prebook->room_rate_basis,
                    'allocationDetails' => $prebook->allocation_details,
                    'adultsCode' => $adultsCount,
                    'actualAdults' => $adultsCount,
                    'children' => $childrenAges,
                    'actualChildren' => $childrenAges,
                    'beddingPreference' => 0,
                    'passengers' => $formattedPassengers,
                ],
            ],
        ];

        // --- Step 3: Call DOTW savebooking ---
        try {
            $itinerary = $dotwService->saveBooking(
                $saveParams,
                $resayilMessageId,
                $resayilQuoteId,
                $companyId
            );

            $itineraryCode = (string) ($itinerary['itineraryCode'] ?? $itinerary['bookingCode'] ?? '');

            // Expire prebook to prevent double-use
            $prebook->update(['expired_at' => now()]);

            // Supplementary audit log
            try {
                $this->auditService->log(
                    DotwAuditService::OP_BOOK,
                    ['prebook_key' => $prebookKey, 'customer_reference' => $customerReference],
                    ['itinerary_code' => $itineraryCode, 'type' => 'save_booking'],
                    $resayilMessageId,
                    $resayilQuoteId,
                    $companyId
                );
            } catch (\Throwable) {
                // Fail-silent — audit failure must never break the booking response
            }

            return [
                'success' => true,
                'error' => null,
                'meta' => $this->buildMeta($companyId ?? 0),
                'cached' => false,
                'data' => [
                    'itinerary_code' => $itineraryCode,
                    'hotel_code' => $prebook->hotel_code,
                    'is_apr' => true,
                ],
            ];

        } catch (\RuntimeException $e) {
            Log::channel('dotw')->error('saveBooking credentials error', [
                'prebook_key' => $prebookKey,
                'error' => $e->getMessage(),
                'company_id' => $companyId,
            ]);

            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'DOTW credentials not configured for this company.',
                'RECONFIGURE_CREDENTIALS'
            );

        } catch (\Exception $e) {
            Log::channel('dotw')->error('saveBooking failed', [
                'prebook_key' => $prebookKey,
                'error' => $e->getMessage(),
                'company_id' => $companyId,
            ]);

            return $this->errorResponse(
                'RATE_UNAVAILABLE',
                'APR booking save failed. Please search again.',
                'RESEARCH'
            );
        }
    }

    /**
     * Build a structured error response matching SaveBookingResponse shape.
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
