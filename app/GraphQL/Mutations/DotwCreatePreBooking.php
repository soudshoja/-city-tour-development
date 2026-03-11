<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\DotwBooking;
use App\Models\DotwPrebook;
use App\Services\DotwAuditService;
use App\Services\DotwService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resolver for the createPreBooking GraphQL mutation.
 *
 * Converts a locked prebook (from Phase 5 blockRates) into a confirmed DOTW hotel booking.
 *
 * Key rules:
 * - ERROR-03: Validate prebook expiry BEFORE any DOTW API call — return ALLOCATION_EXPIRED + RESEARCH.
 * - BOOK-02: Validate passenger count (matches total adults across rooms) and all required fields.
 * - BOOK-04: Call DotwService::confirmBooking() with reconstructed params (allocationDetails raw — no encoding).
 * - BOOK-06: Create dotw_bookings record atomically with prebook expiry via DB::transaction().
 * - BOOK-07: Supplementary audit log (fail-silent) after transaction captures prebook_key + confirmation_code.
 * - ERROR-04: Rate unavailable exception → search up to 3 alternative hotels (fail-silent).
 * - DotwService instantiated in __invoke (per-request credential resolution — not constructor injection).
 * - action field values MUST be valid DotwErrorAction enum members: RETRY, RESEARCH, RECONFIGURE_CREDENTIALS, etc.
 *   Do NOT use RESUBMIT — it is NOT in the enum (latent bug in DotwBlockRates — do not repeat).
 *
 * @see graphql/dotw.graphql CreatePreBookingResponse, CreatePreBookingInput, CreatePreBookingData
 * @see \App\Models\DotwPrebook::isValid()
 * @see \App\Services\DotwService::confirmBooking()
 * @see \App\Services\DotwAuditService
 */
class DotwCreatePreBooking
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
        $destination = isset($input['destination']) ? (string) $input['destination'] : null;

        // Extract Resayil context from request attributes (set by ResayilContextMiddleware)
        $request = request();
        $resayilMessageId = $request->attributes->get('resayil_message_id')
            ?? $request->header('X-Resayil-Message-ID');
        $resayilQuoteId = $request->attributes->get('resayil_quote_id')
            ?? $request->header('X-Resayil-Quote-ID');

        // --- Step 1: Load and validate prebook (BOOK-03, ERROR-03) ---
        $prebook = DotwPrebook::where('prebook_key', $prebookKey)->first();

        if ($prebook === null) {
            return $this->errorResponse('VALIDATION_ERROR', 'Prebook not found.', 'RESEARCH');
        }

        $companyId = $prebook->company_id;

        // ERROR-03: Check expiry BEFORE any DOTW API call — return ALLOCATION_EXPIRED + RESEARCH
        if (! $prebook->isValid()) {
            return $this->errorResponse(
                'ALLOCATION_EXPIRED',
                'Rate offer expired, please search again.',
                'RESEARCH'
            );
        }

        // --- Step 2: Validate passengers (BOOK-02) ---
        $expectedPassengerCount = array_sum(array_column($rooms, 'adults'));
        $validationError = $this->validatePassengers($passengers, $expectedPassengerCount);
        if ($validationError !== null) {
            return $validationError;
        }

        // --- Step 3: Build confirmBooking params (BOOK-04) ---
        $dotwService = new DotwService($companyId);
        $leadPassengerEmail = (string) ($passengers[0]['email'] ?? '');
        $customerReference = (string) Str::uuid();

        // Format passengers for DOTW XML (salutation, firstName, lastName only — nationality stored per-room)
        $formattedPassengers = array_map(fn (array $p) => [
            'salutation' => (int) ($p['salutation'] ?? 1),
            'firstName' => (string) ($p['firstName'] ?? ''),
            'lastName' => (string) ($p['lastName'] ?? ''),
        ], $passengers);

        // Reconstruct room occupancy from input rooms — DotwPrebook does not store adultsCode/children (Pitfall 5)
        $firstRoom = $rooms[0] ?? [];
        $adultsCount = (int) ($firstRoom['adultsCode'] ?? $firstRoom['adults'] ?? 2);
        $childrenAges = (array) ($firstRoom['children'] ?? []);

        $confirmParams = [
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
                    'allocationDetails' => $prebook->allocation_details,  // raw — no encoding (Pitfall 1)
                    'adultsCode' => $adultsCount,
                    'actualAdults' => $adultsCount,
                    'children' => $childrenAges,
                    'actualChildren' => $childrenAges,
                    'beddingPreference' => 0,
                    // COMPLY-02: XSD requires passengerNationality + passengerCountryOfResidence.
                    // Use lead passenger values — already validated non-empty by validatePassengers().
                    'passengerNationality' => (string) ($passengers[0]['nationality'] ?? ''),
                    'passengerCountryOfResidence' => (string) ($passengers[0]['residenceCountry'] ?? ''),
                    'passengers' => $formattedPassengers,
                ],
            ],
        ];

        // BOOK-01: APR detection — non-refundable rates must use savebooking+bookitinerary flow
        // VALID-02: APR bookings (is_refundable=false) cannot be cancelled after confirmation.
        //           cancel/amend UI enforcement is delegated to DotwCancelBooking resolver (09-02).
        //           is_apr is stored in booking_details JSON so the cancel resolver can check it.
        $isApr = ! $prebook->is_refundable;

        // --- Step 4: Call DOTW booking API + create booking atomically (BOOK-04, BOOK-06) ---
        try {
            if ($isApr) {
                // APR flow: savebooking → bookitinerary (two-step, both methods pre-exist in DotwService)
                $itinerary = $dotwService->saveBooking(
                    $confirmParams,
                    $resayilMessageId,
                    $resayilQuoteId,
                    $companyId
                );
                $itineraryCode = (string) ($itinerary['itineraryCode'] ?? $itinerary['bookingCode'] ?? '');

                $raw = $dotwService->bookItinerary(
                    $itineraryCode,
                    $resayilMessageId,
                    $resayilQuoteId,
                    $companyId
                );
                $confirmation = $raw;
            } else {
                // Standard refundable flow: confirmbooking
                $confirmation = $dotwService->confirmBooking(
                    $confirmParams,
                    $resayilMessageId,
                    $resayilQuoteId,
                    $companyId
                );
            }
            // confirmBooking/bookItinerary returns: ['bookingCode', 'confirmationNumber', 'status', ...]

            $confirmationCode = (string) ($confirmation['bookingCode'] ?? '');
            $confirmationNumber = (string) ($confirmation['confirmationNumber'] ?? '');
            $bookingStatus = (string) ($confirmation['status'] ?? 'confirmed');

            $hotelDetails = [
                'hotel_code' => $prebook->hotel_code,
                'hotel_name' => $prebook->hotel_name ?? $prebook->hotel_code,
                'checkin' => $checkin,
                'checkout' => $checkout,
                'room_type' => $prebook->room_type,
                'total_fare' => $prebook->total_fare,
                'currency' => $prebook->original_currency ?: 'USD',
                'is_refundable' => (bool) $prebook->is_refundable,
                // VALID-02: is_apr stored so DotwCancelBooking resolver can block cancel for APR bookings
                'is_apr' => $isApr,
            ];

            // BOOK-06: Atomic — expire prebook + create booking record (same pattern as BLOCK-08)
            $booking = null;
            DB::transaction(function () use (
                $prebook, $prebookKey, $confirmationCode, $confirmationNumber,
                $customerReference, $bookingStatus, $passengers, $hotelDetails,
                $resayilMessageId, $resayilQuoteId, $companyId, &$booking
            ) {
                // Expire prebook to prevent double-booking
                $prebook->update(['expired_at' => now()]);

                $booking = DotwBooking::create([
                    'prebook_key' => $prebookKey,
                    'confirmation_code' => $confirmationCode,
                    'confirmation_number' => $confirmationNumber,
                    'customer_reference' => $customerReference,
                    'booking_status' => $bookingStatus,
                    'passengers' => $passengers,
                    'hotel_details' => $hotelDetails,
                    'resayil_message_id' => $resayilMessageId,
                    'resayil_quote_id' => $resayilQuoteId,
                    'company_id' => $companyId,
                ]);
            });

            // BOOK-07: Supplementary audit log — two-phase pattern (same as BLOCK-07).
            // DotwService::confirmBooking() already logged Phase A (raw API call with $confirmation).
            // Phase B captures the prebook_key <-> confirmation_code association explicitly.
            try {
                $this->auditService->log(
                    DotwAuditService::OP_BOOK,
                    ['prebook_key' => $prebookKey, 'customer_reference' => $customerReference],
                    ['confirmation_code' => $confirmationCode, 'booking_status' => $bookingStatus],
                    $resayilMessageId,
                    $resayilQuoteId,
                    $companyId
                );
            } catch (\Throwable) {
                // Fail-silent — audit failure must NEVER break the booking response
            }

            // BOOK-05: Build and return success response
            $leadPassenger = $passengers[0] ?? [];
            $leadGuestName = trim(
                ($leadPassenger['firstName'] ?? '').' '.($leadPassenger['lastName'] ?? '')
            );

            return [
                'success' => true,
                'error' => null,
                'meta' => $this->buildMeta($companyId ?? 0),
                'cached' => false,
                'data' => [
                    'booking_confirmation_code' => $confirmationCode,
                    'booking_status' => $bookingStatus,
                    'itinerary_details' => [
                        'hotel_code' => $prebook->hotel_code,
                        'hotel_name' => $prebook->hotel_name ?? $prebook->hotel_code,
                        'checkin' => $checkin,
                        'checkout' => $checkout,
                        'room_type' => $prebook->room_type,
                        'rate_basis' => $prebook->room_rate_basis ?? '',
                        'total_fare' => (float) $prebook->total_fare,
                        'currency' => $prebook->original_currency ?: 'USD',
                        'is_refundable' => (bool) $prebook->is_refundable,
                        'lead_guest_name' => $leadGuestName,
                        'customer_reference' => $customerReference,
                        'confirmation_number' => $confirmationNumber,
                    ],
                    'alternatives' => [],
                ],
            ];

        } catch (\RuntimeException $e) {
            // BOOK-08 / ERROR-03 / ERROR-04: DOTW credentials not configured
            $message = $e->getMessage();
            Log::channel('dotw')->error('createPreBooking credentials error', [
                'prebook_key' => $prebookKey,
                'error' => $message,
                'company_id' => $companyId,
            ]);

            return $this->errorResponse(
                'CREDENTIALS_NOT_CONFIGURED',
                'DOTW credentials not configured for this company.',
                'RECONFIGURE_CREDENTIALS'
            );

        } catch (\Exception $e) {
            // BOOK-08 / ERROR-04: Rate unavailable, hotel sold out, or other DOTW booking error
            $message = $e->getMessage();
            Log::channel('dotw')->error('createPreBooking failed', [
                'prebook_key' => $prebookKey,
                'error' => $message,
                'company_id' => $companyId,
            ]);

            $isRateUnavailable = str_contains(strtolower($message), 'unavailable')
                || str_contains(strtolower($message), 'sold out')
                || str_contains(strtolower($message), 'no longer available');

            // ERROR-04 + ERROR-06: Rate unavailable or hotel sold out — suggest up to 3 alternatives
            $alternatives = [];
            if ($isRateUnavailable && $destination !== null && $companyId !== null) {
                try {
                    $rawHotels = $dotwService->searchHotels([
                        'fromDate' => $checkin,
                        'toDate' => $checkout,
                        'currency' => $prebook->original_currency ?: 'USD',
                        'filters' => ['city' => $destination],
                        'rooms' => [['adults' => $adultsCount, 'children' => $childrenAges]],
                    ]);
                    $alternatives = $this->formatAlternatives(
                        array_slice($rawHotels, 0, 3),
                        $dotwService
                    );
                } catch (\Throwable) {
                    $alternatives = [];  // Fail-silent — alternative search must not cascade
                }
            }

            $errorCode = $isRateUnavailable ? 'RATE_UNAVAILABLE' : 'API_ERROR';
            $errorMessage = $isRateUnavailable
                ? 'This hotel/rate no longer available. Please search again or choose an alternative.'
                : 'Booking failed. Please try again or contact support.';

            return [
                'success' => false,
                'error' => [
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'error_details' => $message,
                    'action' => 'RESEARCH',
                ],
                'meta' => $this->buildMeta($companyId ?? 0),
                'cached' => false,
                'data' => [
                    'booking_confirmation_code' => '',
                    'booking_status' => 'failed',
                    'itinerary_details' => $this->emptyItinerary($prebook, $checkin, $checkout),
                    'alternatives' => $alternatives,
                ],
            ];
        }
    }

    /**
     * Validate passenger count and required field completeness.
     * Returns error response array on failure, null on success.
     *
     * @param  array<array<string, mixed>>  $passengers
     * @return array<string, mixed>|null
     */
    private function validatePassengers(array $passengers, int $expectedCount): ?array
    {
        if (count($passengers) !== $expectedCount) {
            return $this->errorResponse(
                'VALIDATION_ERROR',
                'Expected '.$expectedCount.' passenger(s), received '.count($passengers).'.',
                'RETRY'
            );
        }

        $required = ['salutation', 'firstName', 'lastName', 'nationality', 'residenceCountry', 'email'];

        foreach ($passengers as $i => $p) {
            foreach ($required as $field) {
                if (empty($p[$field])) {
                    return $this->errorResponse(
                        'PASSENGER_VALIDATION_FAILED',
                        'Please provide passenger '.$field.' for passenger '.($i + 1).'.',
                        'RETRY'
                    );
                }
            }
            if (! filter_var($p['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->errorResponse(
                    'PASSENGER_VALIDATION_FAILED',
                    'Passenger '.($i + 1).' email format is invalid.',
                    'RETRY'
                );
            }
        }

        return null;
    }

    /**
     * Format raw hotels array from DotwService::searchHotels() into HotelSearchResult schema shape.
     * Used for alternative hotel suggestions on RATE_UNAVAILABLE errors (ERROR-04).
     *
     * @param  array<array<string, mixed>>  $rawHotels
     * @return array<array<string, mixed>>
     */
    private function formatAlternatives(array $rawHotels, DotwService $dotwService): array
    {
        return array_map(function (array $hotel) use ($dotwService): array {
            return [
                'hotel_code' => $hotel['hotelId'],
                'rooms' => array_map(function (array $room) use ($dotwService): array {
                    return [
                        'adults' => $room['adults'],
                        'children' => $room['children'],
                        'children_ages' => $room['childrenAges'] ?? '',
                        'room_types' => array_map(function (array $rt) use ($dotwService): array {
                            $markup = $dotwService->applyMarkup((float) ($rt['total'] ?? 0));

                            return [
                                'code' => $rt['code'],
                                'name' => $rt['name'],
                                'rate_basis_id' => $rt['rateBasisId'],
                                'currency_id' => $rt['rateType'] ?? '',
                                'non_refundable' => ($rt['nonRefundable'] ?? 'no') === 'yes',
                                'total' => (float) ($rt['total'] ?? 0),
                                'markup' => $markup,
                                'total_taxes' => (float) ($rt['totalTaxes'] ?? 0),
                                'total_minimum_selling' => (float) ($rt['totalMinimumSelling'] ?? 0),
                            ];
                        }, $room['roomTypes'] ?? []),
                    ];
                }, $hotel['rooms'] ?? []),
            ];
        }, $rawHotels);
    }

    /**
     * Build an empty itinerary for error responses where the data structure is still required.
     *
     * @return array<string, mixed>
     */
    private function emptyItinerary(DotwPrebook $prebook, string $checkin, string $checkout): array
    {
        return [
            'hotel_code' => $prebook->hotel_code,
            'hotel_name' => $prebook->hotel_name ?? $prebook->hotel_code,
            'checkin' => $checkin,
            'checkout' => $checkout,
            'room_type' => $prebook->room_type,
            'rate_basis' => $prebook->room_rate_basis ?? '',
            'total_fare' => (float) $prebook->total_fare,
            'currency' => $prebook->original_currency ?: 'USD',
            'is_refundable' => (bool) $prebook->is_refundable,
            'lead_guest_name' => '',
            'customer_reference' => '',
            'confirmation_number' => '',
        ];
    }

    /**
     * Build a structured error response matching CreatePreBookingResponse shape.
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
