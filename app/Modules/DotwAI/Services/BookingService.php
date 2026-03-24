<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Models\Agent;
use App\Modules\DotwAI\DTOs\DotwAIContext;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Services\DotwService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Booking orchestration service for the DotwAI module.
 *
 * Handles three booking tracks:
 * - B2B credit (b2b):         Prebook -> confirm with credit deduction in same call
 * - B2B gateway (b2b_gateway): Prebook -> wait for payment -> re-block -> confirm
 * - B2C (b2c):                Prebook -> wait for payment -> re-block -> confirm
 *
 * Key locked decisions (per CONTEXT.md):
 * - Rate blocking always uses getRooms(blocking=true)
 * - confirmAfterPayment always re-blocks before confirming
 * - APR rates use saveBooking + bookItinerary instead of confirmBooking
 * - MSP is enforced on all B2C display prices
 *
 * @see B2B-03 Rate locking via blocking=true
 * @see B2B-06 Pessimistic credit locking via CreditService
 * @see B2C-05 MSP enforcement on display price
 */
class BookingService
{
    public function __construct(
        private readonly CreditService $creditService,
        private readonly HotelSearchService $searchService,
    ) {}

    /**
     * Prebook a hotel rate -- lock the rate and create a DotwAIBooking record.
     *
     * Flow:
     * 1. Resolve hotel from cached search results (by option_number) or direct input
     * 2. Call DotwService::getRooms(blocking=true) to lock the rate
     * 3. Enforce MSP for B2C track
     * 4. Create DotwAIBooking with status='prebooked'
     * 5. Return prebook data with prebook_key
     *
     * @param DotwAIContext $context Resolved company/agent/track context
     * @param array         $input   Validated PrebookRequest data
     * @return array Result array with prebook data or ['error' => true, 'code' => ...]
     */
    public function prebook(DotwAIContext $context, array $input): array
    {
        // 1. Resolve hotel and room data
        $resolved = $this->resolveHotelAndRoom($context, $input);
        if (isset($resolved['error'])) {
            return $resolved;
        }

        $hotelId   = $resolved['hotel_id'];
        $hotelName = $resolved['hotel_name'];
        $roomData  = $resolved['room_data'];  // room_type_code, rate_basis_id, etc.

        // 2. Block the rate via DotwService::getRooms(blocking=true)
        try {
            $dotwService = new DotwService($context->companyId);
            $blockResult = $dotwService->getRooms([
                'fromDate'   => $input['check_in'],
                'toDate'     => $input['check_out'],
                'currency'   => config('dotwai.default_currency', '520'),
                'productId'  => $hotelId,
                'rooms'      => $this->buildRoomSelections($roomData, $input['occupancy'] ?? [[]]),
            ], true, null, null, $context->companyId);
        } catch (\Throwable $e) {
            Log::channel('dotw')->error('[DotwAI] Rate blocking failed', [
                'hotel_id'   => $hotelId,
                'company_id' => $context->companyId,
                'error'      => $e->getMessage(),
            ]);

            return [
                'error'   => true,
                'code'    => DotwAIResponse::RATE_UNAVAILABLE,
                'message' => 'Rate blocking failed: ' . $e->getMessage(),
            ];
        }

        if (empty($blockResult)) {
            return [
                'error'   => true,
                'code'    => DotwAIResponse::RATE_UNAVAILABLE,
                'message' => 'No rooms available after blocking',
            ];
        }

        // Extract the first blocked room's details
        $blockedRoom = $this->extractFirstRoom($blockResult);
        if ($blockedRoom === null) {
            return [
                'error'   => true,
                'code'    => DotwAIResponse::RATE_UNAVAILABLE,
                'message' => 'Could not extract blocked room details',
            ];
        }

        // 3. Calculate prices
        $originalFare = (float) ($blockedRoom['price'] ?? 0);
        $markupMultiplier = $context->getMarkupMultiplier();
        $displayFare = $context->isB2C()
            ? (float) ceil($originalFare * $markupMultiplier)
            : $originalFare;

        // Enforce MSP for B2C (B2C-05)
        $msp = (float) ($roomData['minimum_selling_price'] ?? 0);
        if ($context->isB2C() && $msp > 0 && $displayFare < $msp) {
            $displayFare = $msp;
        }

        // 4. Determine track
        $track = $this->determineTrack($context);

        // 5. Extract cancellation deadline
        $cancellationRules = $blockedRoom['cancellation_rules'] ?? [];
        $cancellationDeadline = $this->extractCancellationDeadline($cancellationRules);

        // 6. Create DotwAIBooking
        $booking = new DotwAIBooking();
        $prebookKey = $booking->generatePrebookKey();

        $nationalityCode = $roomData['nationality_code'] ?? config('dotwai.default_nationality', '66');
        $residenceCode   = $roomData['residence_code'] ?? config('dotwai.default_residence', '66');

        $booking->fill([
            'prebook_key'          => $prebookKey,
            'track'                => $track,
            'status'               => DotwAIBooking::STATUS_PREBOOKED,
            'company_id'           => $context->companyId,
            'agent_phone'          => $input['telephone'] ?? '',
            'hotel_id'             => $hotelId,
            'hotel_name'           => $hotelName,
            'check_in'             => $input['check_in'],
            'check_out'            => $input['check_out'],
            'original_total_fare'  => $originalFare,
            'original_currency'    => config('dotwai.display_currency', 'KWD'),
            'display_total_fare'   => $displayFare,
            'display_currency'     => config('dotwai.display_currency', 'KWD'),
            'markup_percentage'    => $context->markupPercent,
            'minimum_selling_price' => $msp > 0 ? $msp : null,
            'is_refundable'        => $blockedRoom['is_refundable'] ?? true,
            'is_apr'               => $blockedRoom['is_apr'] ?? false,
            'cancellation_deadline' => $cancellationDeadline,
            'cancellation_rules'   => $cancellationRules,
            'allocation_details'   => $blockedRoom['allocation_details'] ?? '',
            'room_type_code'       => $blockedRoom['room_type_code'] ?? ($roomData['room_type_code'] ?? null),
            'rate_basis_id'        => $blockedRoom['rate_basis_id'] ?? ($roomData['rate_basis_id'] ?? null),
            'nationality_code'     => $nationalityCode,
            'residence_code'       => $residenceCode,
            'rooms_data'           => $input['occupancy'] ?? [],
        ]);
        $booking->save();

        $needsPayment = in_array($track, [DotwAIBooking::TRACK_B2B_GATEWAY, DotwAIBooking::TRACK_B2C]);

        return [
            'prebook_key'           => $prebookKey,
            'hotel_name'            => $hotelName,
            'check_in'              => $input['check_in'],
            'check_out'             => $input['check_out'],
            'display_total_fare'    => $displayFare,
            'original_total_fare'   => $originalFare,
            'currency'              => config('dotwai.display_currency', 'KWD'),
            'is_refundable'         => $blockedRoom['is_refundable'] ?? true,
            'is_apr'                => $blockedRoom['is_apr'] ?? false,
            'cancellation_rules'    => $cancellationRules,
            'cancellation_deadline' => $cancellationDeadline,
            'track'                 => $track,
            'needs_payment'         => $needsPayment,
        ];
    }

    /**
     * Confirm a booking using the agent's B2B credit line.
     *
     * Pessimistic credit locking via CreditService::checkAndDeductCredit.
     * If credit check passes, immediately calls DOTW confirmBooking (or
     * saveBooking + bookItinerary for APR rates).
     * On DOTW failure, credit is refunded automatically.
     *
     * Idempotent: if booking is already confirmed, returns existing confirmation.
     *
     * @param DotwAIBooking $booking    The prebooked booking record
     * @param DotwAIContext $context    Resolved company/agent/track context
     * @param array         $passengers Passenger details [{first_name, last_name, salutation}]
     * @param string|null   $email      Guest email for DOTW communication
     * @return array Confirmation data or ['error' => true, 'code' => ...]
     */
    public function confirmWithCredit(
        DotwAIBooking $booking,
        DotwAIContext $context,
        array $passengers,
        ?string $email,
    ): array {
        // Idempotency gate
        if (!empty($booking->confirmation_no)) {
            return $this->buildConfirmationResponse($booking);
        }

        // Resolve client_id
        $clientId = $this->creditService->getClientIdForCompany($context->companyId);
        if ($clientId === null) {
            return [
                'error'   => true,
                'code'    => DotwAIResponse::BOOKING_FAILED,
                'message' => 'Could not resolve client for credit deduction',
            ];
        }

        // Check and deduct credit (pessimistic locking)
        $deducted = $this->creditService->checkAndDeductCredit(
            $clientId,
            $context->companyId,
            (float) $booking->original_total_fare,
            $booking->prebook_key,
        );

        if (!$deducted) {
            $balance = $this->creditService->getBalance($clientId);

            return [
                'error'     => true,
                'code'      => DotwAIResponse::INSUFFICIENT_CREDIT,
                'message'   => 'Insufficient credit. Available: ' . $balance['available_credit'],
                'available' => $balance['available_credit'],
            ];
        }

        // Update status to confirming
        $booking->update(['status' => DotwAIBooking::STATUS_CONFIRMING]);

        // Build confirm params
        $confirmParams = $this->buildConfirmParams($booking, $passengers, $email);

        // Call DOTW
        try {
            $dotwService = new DotwService($context->companyId);
            $confirmation = $this->callDotwConfirm($dotwService, $booking, $confirmParams, $context->companyId);
        } catch (\Throwable $e) {
            Log::channel('dotw')->error('[DotwAI] confirmWithCredit DOTW call failed', [
                'prebook_key' => $booking->prebook_key,
                'error'       => $e->getMessage(),
            ]);

            // Refund credit on DOTW failure
            $this->creditService->refundCredit(
                $clientId,
                $context->companyId,
                (float) $booking->original_total_fare,
                $booking->prebook_key,
            );

            $booking->update(['status' => DotwAIBooking::STATUS_FAILED]);

            return [
                'error'   => true,
                'code'    => DotwAIResponse::BOOKING_FAILED,
                'message' => 'DOTW confirmation failed: ' . $e->getMessage(),
            ];
        }

        // Update booking on success
        $guestDetails = array_map(fn (array $p) => [
            'first_name' => $p['first_name'],
            'last_name'  => $p['last_name'],
            'salutation' => $p['salutation'] ?? 'Mr',
        ], $passengers);

        $booking->update([
            'confirmation_no'     => $confirmation['confirmationNumber'] ?? ($confirmation['bookingCode'] ?? null),
            'booking_ref'         => $confirmation['bookingCode'] ?? null,
            'payment_guaranteed_by' => $confirmation['paymentGuaranteedBy'] ?? null,
            'status'              => DotwAIBooking::STATUS_CONFIRMED,
            'payment_status'      => 'credit_applied',
            'guest_details'       => $guestDetails,
            'client_email'        => $email,
        ]);

        return $this->buildConfirmationResponse($booking->fresh());
    }

    /**
     * Confirm a booking after payment has been received.
     *
     * Called by the payment callback job (Plan 02). Re-blocks the rate before
     * confirming to ensure the allocation is still valid.
     *
     * Idempotent: returns existing confirmation if already confirmed.
     *
     * @param DotwAIBooking $booking The booking in pending_payment status with payment_status='paid'
     * @return array Confirmation data or ['error' => true, 'code' => ..., 'needs_refund' => bool]
     */
    public function confirmAfterPayment(DotwAIBooking $booking): array
    {
        // Idempotency gate
        if (!empty($booking->confirmation_no)) {
            return $this->buildConfirmationResponse($booking);
        }

        // Re-block the rate (CONTEXT.md locked decision: always re-block after payment)
        try {
            $dotwService = new DotwService($booking->company_id);
            $blockResult = $dotwService->getRooms([
                'fromDate'  => $booking->check_in->format('Y-m-d'),
                'toDate'    => $booking->check_out->format('Y-m-d'),
                'currency'  => config('dotwai.default_currency', '520'),
                'productId' => $booking->hotel_id,
                'rooms'     => $this->buildRoomSelectionsFromBooking($booking),
            ], true, null, null, $booking->company_id);
        } catch (\Throwable $e) {
            Log::channel('dotw')->error('[DotwAI] confirmAfterPayment re-block failed', [
                'prebook_key' => $booking->prebook_key,
                'error'       => $e->getMessage(),
            ]);

            $booking->update(['status' => DotwAIBooking::STATUS_FAILED]);

            return [
                'error'        => true,
                'code'         => DotwAIResponse::RATE_UNAVAILABLE,
                'message'      => 'Rate re-block failed after payment: ' . $e->getMessage(),
                'needs_refund' => true,
            ];
        }

        if (empty($blockResult)) {
            $booking->update(['status' => DotwAIBooking::STATUS_FAILED]);

            return [
                'error'        => true,
                'code'         => DotwAIResponse::RATE_UNAVAILABLE,
                'message'      => 'Rate no longer available after payment',
                'needs_refund' => true,
            ];
        }

        // Update allocation with fresh token
        $reBlockedRoom = $this->extractFirstRoom($blockResult);
        if ($reBlockedRoom !== null && !empty($reBlockedRoom['allocation_details'])) {
            $booking->update(['allocation_details' => $reBlockedRoom['allocation_details']]);
        }

        // Build confirm params from guest_details stored at prebook time
        $passengers = $booking->guest_details ?? [];
        $confirmParams = $this->buildConfirmParams($booking, $passengers, $booking->client_email);

        $booking->update(['status' => DotwAIBooking::STATUS_CONFIRMING]);

        try {
            $confirmation = $this->callDotwConfirm($dotwService, $booking, $confirmParams, $booking->company_id);
        } catch (\Throwable $e) {
            Log::channel('dotw')->error('[DotwAI] confirmAfterPayment DOTW call failed', [
                'prebook_key' => $booking->prebook_key,
                'error'       => $e->getMessage(),
            ]);

            $booking->update(['status' => DotwAIBooking::STATUS_FAILED]);

            return [
                'error'        => true,
                'code'         => DotwAIResponse::BOOKING_FAILED,
                'message'      => 'DOTW confirmation failed after payment: ' . $e->getMessage(),
                'needs_refund' => true,
            ];
        }

        $booking->update([
            'confirmation_no'     => $confirmation['confirmationNumber'] ?? ($confirmation['bookingCode'] ?? null),
            'booking_ref'         => $confirmation['bookingCode'] ?? null,
            'payment_guaranteed_by' => $confirmation['paymentGuaranteedBy'] ?? null,
            'status'              => DotwAIBooking::STATUS_CONFIRMED,
        ]);

        return $this->buildConfirmationResponse($booking->fresh());
    }

    /**
     * Get the current credit balance for a B2B company.
     *
     * @param DotwAIContext $context Resolved company/agent/track context
     * @return array{credit_limit: float, used_credit: float, available_credit: float}
     */
    public function getCompanyBalance(DotwAIContext $context): array
    {
        $clientId = $this->creditService->getClientIdForCompany($context->companyId);

        if ($clientId === null) {
            return [
                'credit_limit'     => 0.0,
                'used_credit'      => 0.0,
                'available_credit' => 0.0,
            ];
        }

        return $this->creditService->getBalance($clientId);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Normalize a phone number to digits only.
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Determine the booking track from context.
     *
     * B2B agents get 'b2b' if the context track is b2b, 'b2b_gateway' otherwise.
     * B2C agents always get 'b2c'.
     */
    private function determineTrack(DotwAIContext $context): string
    {
        if ($context->isB2C()) {
            return DotwAIBooking::TRACK_B2C;
        }

        if ($context->isB2B()) {
            return DotwAIBooking::TRACK_B2B;
        }

        return DotwAIBooking::TRACK_B2B_GATEWAY;
    }

    /**
     * Resolve hotel and room data from cached search or direct input.
     *
     * @param DotwAIContext $context
     * @param array         $input   Validated PrebookRequest data
     * @return array Either ['hotel_id' => ..., 'hotel_name' => ..., 'room_data' => ...] or ['error' => true, ...]
     */
    private function resolveHotelAndRoom(DotwAIContext $context, array $input): array
    {
        if (isset($input['option_number'])) {
            // Load from cached search results
            $phone = $this->normalizePhone($input['telephone'] ?? '');
            $cached = Cache::get("dotwai:search:{$phone}");

            if (empty($cached)) {
                return [
                    'error'   => true,
                    'code'    => DotwAIResponse::NO_RESULTS,
                    'message' => 'No cached search results for this phone. Please search first.',
                ];
            }

            $optionIndex = (int) $input['option_number'] - 1;
            $hotel = $cached[$optionIndex] ?? null;

            if ($hotel === null) {
                return [
                    'error'   => true,
                    'code'    => DotwAIResponse::HOTEL_NOT_FOUND,
                    'message' => "Option {$input['option_number']} not found in search results",
                ];
            }

            return [
                'hotel_id'   => $hotel['hotel_id'],
                'hotel_name' => $hotel['name'],
                'room_data'  => [
                    'room_type_code'      => $input['room_type_code'] ?? null,
                    'rate_basis_id'       => $input['rate_basis_id'] ?? null,
                    'nationality_code'    => config('dotwai.default_nationality', '66'),
                    'residence_code'      => config('dotwai.default_residence', '66'),
                    'minimum_selling_price' => 0,
                ],
            ];
        }

        // Direct hotel_id provided
        return [
            'hotel_id'   => $input['hotel_id'],
            'hotel_name' => $input['hotel_id'],  // Name will be resolved from DB or API response
            'room_data'  => [
                'room_type_code'      => $input['room_type_code'] ?? null,
                'rate_basis_id'       => $input['rate_basis_id'] ?? null,
                'nationality_code'    => config('dotwai.default_nationality', '66'),
                'residence_code'      => config('dotwai.default_residence', '66'),
                'minimum_selling_price' => 0,
            ],
        ];
    }

    /**
     * Build room selections array for DotwService::getRooms blocking call.
     *
     * @param array $roomData    Resolved room metadata (room_type_code, rate_basis_id, etc.)
     * @param array $occupancy   [{adults: int, children_ages: ?array}]
     * @return array DOTW room selections
     */
    private function buildRoomSelections(array $roomData, array $occupancy): array
    {
        $nationalityCode = $roomData['nationality_code'] ?? config('dotwai.default_nationality', '66');
        $residenceCode   = $roomData['residence_code'] ?? config('dotwai.default_residence', '66');
        $roomTypeCode    = $roomData['room_type_code'] ?? null;
        $rateBasisId     = $roomData['rate_basis_id'] ?? null;
        $allocationDetails = $roomData['allocation_details'] ?? '';

        $rooms = [];

        foreach ($occupancy as $roomOccupancy) {
            $adults    = (int) ($roomOccupancy['adults'] ?? 2);
            $childAges = $roomOccupancy['children_ages'] ?? [];

            $room = [
                'adultsCode'   => $adults,
                'childrenAges' => $childAges,
                'nationality'  => $nationalityCode,
                'residence'    => $residenceCode,
            ];

            // For blocking, include rate selection if known
            if ($roomTypeCode !== null) {
                $room['roomTypeCode'] = $roomTypeCode;
            }
            if ($rateBasisId !== null) {
                $room['rateBasisId'] = $rateBasisId;
            }
            if (!empty($allocationDetails)) {
                $room['allocationDetails'] = $allocationDetails;
            }

            $rooms[] = $room;
        }

        return $rooms;
    }

    /**
     * Build room selections from a stored booking (for re-block on confirmAfterPayment).
     */
    private function buildRoomSelectionsFromBooking(DotwAIBooking $booking): array
    {
        $occupancy = $booking->rooms_data ?? [['adults' => 2, 'children_ages' => []]];

        $roomData = [
            'room_type_code'  => $booking->room_type_code,
            'rate_basis_id'   => $booking->rate_basis_id,
            'allocation_details' => '',  // Fresh block -- do not pass old allocation
            'nationality_code' => $booking->nationality_code,
            'residence_code'  => $booking->residence_code,
        ];

        return $this->buildRoomSelections($roomData, $occupancy);
    }

    /**
     * Extract the first room's details from a parsed getRooms response.
     *
     * @param array $blockResult Raw parsed rooms from DotwService::getRooms
     * @return array|null Room data or null if not found
     */
    private function extractFirstRoom(array $blockResult): ?array
    {
        // blockResult is array of rooms. Each has roomTypeCode, roomName, details[]
        $firstRoom = $blockResult[0] ?? null;
        if ($firstRoom === null) {
            return null;
        }

        // If it has 'details' key (from parseRooms structure)
        if (isset($firstRoom['details'])) {
            $detail = $firstRoom['details'][0] ?? null;
            if ($detail === null) {
                return null;
            }

            $cancellationRules = $detail['cancellationRules'] ?? [];
            $allRestricted = !empty($cancellationRules) && collect($cancellationRules)
                ->every(fn (array $rule) => ($rule['cancelRestricted'] ?? false) === true);

            return [
                'room_type_code'   => $firstRoom['roomTypeCode'] ?? '',
                'rate_basis_id'    => $detail['id'] ?? '',
                'price'            => (float) ($detail['price'] ?? 0),
                'allocation_details' => $detail['allocationDetails'] ?? '',
                'cancellation_rules' => $cancellationRules,
                'is_refundable'    => !$allRestricted,
                'is_apr'           => $allRestricted,
            ];
        }

        // Flat room structure (already parsed by parseRoomDetails)
        return $firstRoom;
    }

    /**
     * Extract the earliest cancellation deadline from cancellation rules.
     *
     * The deadline is the first fromDate where a charge > 0 applies.
     *
     * @param array $rules Cancellation rules array
     * @return string|null ISO datetime or null if fully refundable
     */
    private function extractCancellationDeadline(array $rules): ?string
    {
        foreach ($rules as $rule) {
            $charge = (float) ($rule['charge'] ?? $rule['cancelCharge'] ?? 0);
            $restricted = $rule['cancelRestricted'] ?? false;

            if ($charge > 0 || $restricted) {
                return $rule['fromDate'] ?? null;
            }
        }

        return null;
    }

    /**
     * Build DOTW confirm booking parameters.
     *
     * @param DotwAIBooking $booking    The booking record
     * @param array         $passengers Passenger list [{first_name, last_name, salutation?}]
     * @param string|null   $email      Guest email
     * @return array DOTW confirmBooking params
     */
    private function buildConfirmParams(
        DotwAIBooking $booking,
        array $passengers,
        ?string $email,
    ): array {
        $occupancy = $booking->rooms_data ?? [['adults' => 2, 'children_ages' => []]];

        $rooms = [];
        foreach ($occupancy as $roomIdx => $roomOccupancy) {
            $adults    = (int) ($roomOccupancy['adults'] ?? 2);
            $childAges = $roomOccupancy['children_ages'] ?? [];

            // Assign passengers to this room (simple sequential assignment)
            $roomPassengers = array_slice($passengers, $roomIdx * $adults, $adults);

            $passengerList = [];
            foreach ($roomPassengers as $passenger) {
                $passengerList[] = [
                    'salutation' => $passenger['salutation'] ?? 'Mr',
                    'firstName'  => $this->sanitizePassengerName($passenger['first_name'] ?? 'Guest'),
                    'lastName'   => $this->sanitizePassengerName($passenger['last_name'] ?? 'Guest'),
                    'type'       => 'adult',
                ];
            }

            // If no specific passengers, use a placeholder
            if (empty($passengerList)) {
                $passengerList[] = [
                    'salutation' => 'Mr',
                    'firstName'  => 'Guest',
                    'lastName'   => 'Guest',
                    'type'       => 'adult',
                ];
            }

            $rooms[] = [
                'passengers'       => $passengerList,
                'allocationDetails' => $booking->allocation_details ?? '',
                'roomTypeCode'     => $booking->room_type_code ?? '',
                'rateBasisId'      => $booking->rate_basis_id ?? '',
                'adultsCode'       => $adults,
                'actualAdults'     => $adults,
                'children'         => count($childAges),
                'nationality'      => $booking->nationality_code ?? config('dotwai.default_nationality', '66'),
                'residence'        => $booking->residence_code ?? config('dotwai.default_residence', '66'),
            ];
        }

        return [
            'productId'           => $booking->hotel_id,
            'fromDate'            => $booking->check_in->format('Y-m-d'),
            'toDate'              => $booking->check_out->format('Y-m-d'),
            'currency'            => config('dotwai.default_currency', '520'),
            'sendCommunicationTo' => $email ?? '',
            'customerReference'   => 'DOTWAI-' . $booking->id,
            'rooms'               => $rooms,
        ];
    }

    /**
     * Call DOTW confirm (or saveBooking + bookItinerary for APR rates).
     *
     * @param DotwService   $dotwService  Instantiated DotwService
     * @param DotwAIBooking $booking      The booking record
     * @param array         $confirmParams DOTW params
     * @param int           $companyId    Company context
     * @return array DOTW confirmation response
     * @throws \Throwable on DOTW API error
     */
    private function callDotwConfirm(
        DotwService $dotwService,
        DotwAIBooking $booking,
        array $confirmParams,
        int $companyId,
    ): array {
        if ($booking->is_apr) {
            // APR rates: saveBooking first, then bookItinerary
            $itinerary = $dotwService->saveBooking($confirmParams, null, null, $companyId);
            $bookingCode = $itinerary['bookingCode'] ?? ($itinerary['itineraryCode'] ?? null);

            if (empty($bookingCode)) {
                throw new \RuntimeException('saveBooking did not return a bookingCode');
            }

            return $dotwService->bookItinerary($bookingCode, null, null, $companyId);
        }

        return $dotwService->confirmBooking($confirmParams, null, null, $companyId);
    }

    /**
     * Sanitize a passenger name for DOTW submission.
     *
     * Removes spaces and non-alphabetic characters. Mirror of DotwService::sanitizePassengerName
     * (which is private, so duplicated here for module self-containment).
     *
     * @param string $name Raw passenger name
     * @return string Sanitized alphabetic-only name
     */
    private function sanitizePassengerName(string $name): string
    {
        $sanitized = preg_replace('/\s+/', '', $name) ?? '';
        $sanitized = preg_replace('/[^A-Za-z]/', '', $sanitized) ?? '';

        return strlen($sanitized) >= 2 ? $sanitized : 'Guest';
    }

    /**
     * Build the confirmation response array from a confirmed booking.
     *
     * @param DotwAIBooking $booking Confirmed booking record
     * @return array Confirmation data
     */
    private function buildConfirmationResponse(DotwAIBooking $booking): array
    {
        return [
            'prebook_key'          => $booking->prebook_key,
            'confirmation_no'      => $booking->confirmation_no,
            'booking_ref'          => $booking->booking_ref,
            'hotel_name'           => $booking->hotel_name,
            'check_in'             => $booking->check_in?->format('Y-m-d'),
            'check_out'            => $booking->check_out?->format('Y-m-d'),
            'original_total_fare'  => (float) $booking->original_total_fare,
            'display_total_fare'   => (float) $booking->display_total_fare,
            'currency'             => $booking->display_currency,
            'track'                => $booking->track,
            'status'               => $booking->status,
            'payment_guaranteed_by' => $booking->payment_guaranteed_by,
            'guest_details'        => $booking->guest_details ?? [],
        ];
    }
}
