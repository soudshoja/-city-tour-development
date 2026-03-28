<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Controllers;

use App\Modules\DotwAI\DTOs\DotwAIContext;
use App\Modules\DotwAI\Http\Requests\AgentRequest;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Modules\DotwAI\Services\AgentSessionService;
use App\Modules\DotwAI\Services\BookingService;
use App\Modules\DotwAI\Services\CancellationService;
use App\Modules\DotwAI\Services\DotwAIResponse;
use App\Modules\DotwAI\Services\HotelSearchService;
use App\Modules\DotwAI\Services\MessageBuilderService;
use App\Modules\DotwAI\Services\PaymentBridgeService;
use App\Modules\DotwAI\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Agent facade controller for the DotwAI module.
 *
 * Single unified endpoint for n8n AI agents: POST /api/dotwai/agent-b2c and agent-b2b.
 *
 * This controller manages per-phone session state and routes all 8 actions
 * (search, details, book, pay, cancel, status, history, voucher) to appropriate services.
 * Every response includes sessionContext to track the customer's journey state.
 *
 * @see AGEN-01 Single agent endpoint routes all actions
 * @see AGEN-02 Per-phone session state management
 */
class AgentController extends Controller
{
    public function __construct(
        private readonly AgentSessionService $sessionService,
        private readonly HotelSearchService $searchService,
        private readonly BookingService $bookingService,
        private readonly CancellationService $cancellationService,
        private readonly PaymentBridgeService $paymentBridge,
    ) {}

    /**
     * Handle all agent actions via a single unified endpoint.
     *
     * Routes to appropriate service based on action parameter.
     * Maintains per-phone session state across all actions.
     */
    public function handle(AgentRequest $request): JsonResponse
    {
        try {
            /** @var DotwAIContext $context */
            $context = $request->attributes->get('dotwai_context');

            $phone = $request->input('telephone');
            $action = $request->input('action');
            $params = $request->input('params') ?? [];

            // Load current session
            $session = $this->sessionService->getSession($phone);

            Log::channel('dotw')->info('[AgentFacade] action', [
                'phone'  => $phone,
                'action' => $action,
                'stage'  => $session['stage'] ?? 'idle',
            ]);

            // Route to appropriate handler
            $result = match ($action) {
                'search'  => $this->handleSearch($context, $phone, $params, $session),
                'details' => $this->handleDetails($context, $phone, $params, $session),
                'book'    => $this->handleBook($context, $phone, $params, $session),
                'pay'     => $this->handlePay($context, $phone, $params, $session),
                'cancel'  => $this->handleCancel($context, $phone, $params, $session),
                'status'  => $this->handleStatus($context, $phone, $params, $session),
                'history' => $this->handleHistory($context, $phone, $params, $session),
                'voucher' => $this->handleVoucher($context, $phone, $params, $session),
                default   => throw new \InvalidArgumentException("Unknown action: $action"),
            };

            return $result;
        } catch (\Throwable $e) {
            Log::channel('dotw')->error('[AgentFacade] exception', [
                'error' => $e->getMessage(),
            ]);

            return DotwAIResponse::error(
                DotwAIResponse::DOTW_API_ERROR,
                'Unexpected error: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Handle 'search' action: search for hotels and cache results.
     */
    private function handleSearch(DotwAIContext $context, string $phone, array $params, array $session): JsonResponse
    {
        // Validate required params
        if (empty($params['city']) || empty($params['check_in']) || empty($params['check_out']) || empty($params['occupancy'])) {
            return DotwAIResponse::error(
                DotwAIResponse::VALIDATION_ERROR,
                'Missing required search params: city, check_in, check_out, occupancy',
            );
        }

        $result = $this->searchService->searchHotels($context, $params);

        if (isset($result['error']) && $result['error'] === true) {
            return DotwAIResponse::error(
                $result['code'],
                $result['message'] ?? 'Search failed',
            );
        }

        // Update session with search results reference
        $session = [
            'stage' => 'searching',
            'search_cached_at' => now()->toIso8601String(),
            'search_city' => $params['city'],
            'search_hotel_count' => count($result['hotels'] ?? []),
            'last_search_params' => $params,
        ];
        $this->sessionService->saveSession($phone, $session);

        $whatsappMessage = MessageBuilderService::formatSearchResults($result['hotels'] ?? [], $context->track === 'b2c' ? 'KWD' : 'KWD');
        $stageContext = $this->sessionService->getStageContext($session);
        $result['sessionContext'] = $stageContext;

        return DotwAIResponse::success($result, $whatsappMessage);
    }

    /**
     * Handle 'details' action: get hotel details from cached search.
     */
    private function handleDetails(DotwAIContext $context, string $phone, array $params, array $session): JsonResponse
    {
        // Require active session
        if (empty($session)) {
            $emptySession = [];
            $stageContext = $this->sessionService->getStageContext($emptySession);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::SESSION_EMPTY,
                'No active session found',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Check if search expired
        if ($this->sessionService->isSearchExpired($phone)) {
            $session = [];
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::SEARCH_EXPIRED,
                'Search results expired',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Resolve hotel from session cache using option number
        $optionNumber = $params['option'] ?? 1;
        $cachedSearch = Cache::get("dotwai_search_{$phone}");

        if (!$cachedSearch || empty($cachedSearch['hotels'][$optionNumber - 1])) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::HOTEL_NOT_FOUND,
                "Hotel at option {$optionNumber} not found in cache",
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        $cachedHotel = $cachedSearch['hotels'][$optionNumber - 1];
        $hotelId = $cachedHotel['id'] ?? $cachedHotel['hotel_id'] ?? '';

        if (empty($hotelId)) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::HOTEL_NOT_FOUND,
                'Hotel ID not found in cached result',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Get details from cached search params
        $detailsInput = [
            'hotel_id' => $hotelId,
            'check_in' => $session['last_search_params']['check_in'],
            'check_out' => $session['last_search_params']['check_out'],
            'occupancy' => $session['last_search_params']['occupancy'],
        ];

        $result = $this->searchService->getHotelDetails($context, $hotelId, $detailsInput);

        if (isset($result['error']) && $result['error'] === true) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                $result['code'],
                $result['message'] ?? 'Get details failed',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Update session to viewing_details
        $session['stage'] = 'viewing_details';
        $session['selected_hotel_id'] = $hotelId;
        $session['selected_hotel_name'] = $result['hotel']['name'] ?? '';
        $this->sessionService->saveSession($phone, $session);

        $whatsappMessage = MessageBuilderService::formatHotelDetails($result['hotel'] ?? [], $result['rooms'] ?? [], 'KWD');
        $stageContext = $this->sessionService->getStageContext($session);
        $result['sessionContext'] = $stageContext;

        return DotwAIResponse::success($result, $whatsappMessage);
    }

    /**
     * Handle 'book' action: prebook a hotel room from cached search.
     */
    private function handleBook(DotwAIContext $context, string $phone, array $params, array $session): JsonResponse
    {
        // Require active session
        if (empty($session)) {
            $emptySession = [];
            $stageContext = $this->sessionService->getStageContext($emptySession);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::SESSION_EMPTY,
                'No active session found',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Check if search expired
        if ($this->sessionService->isSearchExpired($phone)) {
            $session = [];
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::SEARCH_EXPIRED,
                'Search results expired',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Get option_number from params or session
        $optionNumber = $params['option'] ?? $session['last_option'] ?? 1;

        // Prepare prebook input from session
        $prebookInput = [
            ...$session['last_search_params'],
            'option_number' => $optionNumber,
        ];

        $result = $this->bookingService->prebook($context, $prebookInput);

        if (isset($result['error']) && $result['error'] === true) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                $result['code'],
                $result['message'] ?? 'Prebook failed',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Update session to prebooked
        $session['stage'] = 'prebooked';
        $session['prebook_key'] = $result['prebook_key'];
        $session['prebook_expires_at'] = now()->addMinutes(30)->toIso8601String();
        $session['selected_hotel_name'] = $result['hotel_name'] ?? '';
        $this->sessionService->saveSession($phone, $session);

        $whatsappMessage = MessageBuilderService::formatPrebookConfirmation($result);
        $whatsappOptions = ($result['needs_payment'] ?? false)
            ? ['Get payment link', 'Cancel booking']
            : ['Confirm booking', 'Cancel booking'];
        $stageContext = $this->sessionService->getStageContext($session);
        $result['sessionContext'] = $stageContext;

        return DotwAIResponse::success($result, $whatsappMessage, $whatsappOptions);
    }

    /**
     * Handle 'pay' action: generate payment link for prebooked booking.
     */
    private function handlePay(DotwAIContext $context, string $phone, array $params, array $session): JsonResponse
    {
        // Require prebook_key in session
        if (empty($session['prebook_key'])) {
            $emptySession = [];
            $stageContext = $this->sessionService->getStageContext($emptySession);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::SESSION_EMPTY,
                'No active prebook in session',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Check if prebook expired
        if ($this->sessionService->isPrebookExpired($phone)) {
            // Reset session
            $session = ['stage' => 'idle'];
            $this->sessionService->saveSession($phone, $session);
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::PREBOOK_EXPIRED,
                'Prebook allocation expired (30 min limit)',
                null,
                'Ask the user to search again — the rate lock has expired after 30 minutes.'
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Find booking by prebook_key
        $booking = DotwAIBooking::where('prebook_key', $session['prebook_key'])
            ->where('phone', $phone)
            ->first();

        if (!$booking) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::PREBOOK_NOT_FOUND,
                'Booking not found for prebook key',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Generate payment link
        $result = $this->paymentBridge->createPaymentLink($booking);

        if (isset($result['error']) && $result['error'] === true) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                $result['code'],
                $result['message'] ?? 'Payment link generation failed',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Update session to awaiting_payment
        $session['stage'] = 'awaiting_payment';
        $this->sessionService->saveSession($phone, $session);

        $whatsappMessage = MessageBuilderService::formatPaymentLink($result, $booking);
        $stageContext = $this->sessionService->getStageContext($session);
        $result['sessionContext'] = $stageContext;

        return DotwAIResponse::success($result, $whatsappMessage);
    }

    /**
     * Handle 'cancel' action: initiate or confirm cancellation.
     */
    private function handleCancel(DotwAIContext $context, string $phone, array $params, array $session): JsonResponse
    {
        // Get prebook_key from session or params
        $prebookKey = $params['prebook_key'] ?? $session['prebook_key'] ?? null;

        if (empty($prebookKey)) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::SESSION_EMPTY,
                'No prebook_key found',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        $result = $this->cancellationService->cancel($context, [
            'prebook_key' => $prebookKey,
            'phone' => $phone,
            'confirm' => $params['confirm'] ?? 'no',
            'penalty_amount' => $params['penalty_amount'] ?? null,
        ]);

        if (isset($result['error']) && $result['error'] === true) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                $result['code'],
                $result['message'] ?? 'Cancellation failed',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Update session based on cancellation step
        if (($params['confirm'] ?? 'no') === 'yes') {
            // Confirmed cancellation
            $session = ['stage' => 'idle'];
            $whatsappMessage = MessageBuilderService::formatCancellationConfirmed($result);
        } else {
            // Pending cancellation
            $session['stage'] = 'cancelling';
            $whatsappMessage = MessageBuilderService::formatCancellationPending($result);
        }
        $this->sessionService->saveSession($phone, $session);

        $stageContext = $this->sessionService->getStageContext($session);
        $result['sessionContext'] = $stageContext;

        return DotwAIResponse::success($result, $whatsappMessage);
    }

    /**
     * Handle 'status' action: get booking status.
     */
    private function handleStatus(DotwAIContext $context, string $phone, array $params, array $session): JsonResponse
    {
        // Get prebook_key from params or session
        $prebookKey = $params['prebook_key'] ?? $session['prebook_key'] ?? null;

        if (empty($prebookKey)) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::SESSION_EMPTY,
                'No prebook_key found',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Find booking
        $booking = DotwAIBooking::where('prebook_key', $prebookKey)
            ->where('phone', $phone)
            ->first();

        if (!$booking) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::PREBOOK_NOT_FOUND,
                'Booking not found',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Format status
        $result = [
            'prebook_key' => $booking->prebook_key,
            'status' => $booking->status,
            'hotel_name' => $booking->hotel_name,
            'check_in' => $booking->check_in,
            'check_out' => $booking->check_out,
            'total_amount' => $booking->total_amount,
            'currency' => $booking->currency,
        ];

        $whatsappMessage = MessageBuilderService::formatBookingStatusMessage($result);
        $stageContext = $this->sessionService->getStageContext($session);
        $result['sessionContext'] = $stageContext;

        return DotwAIResponse::success($result, $whatsappMessage);
    }

    /**
     * Handle 'history' action: get booking history for the phone.
     */
    private function handleHistory(DotwAIContext $context, string $phone, array $params, array $session): JsonResponse
    {
        $status = $params['status'] ?? null;
        $page = $params['page'] ?? 1;
        $perPage = $params['per_page'] ?? 10;

        $query = DotwAIBooking::where('phone', $phone)
            ->where('company_id', $context->companyId)
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $bookings = $query->paginate($perPage, ['*'], 'page', $page)->items();

        $result = [
            'bookings' => array_map(function ($booking) {
                return [
                    'prebook_key' => $booking->prebook_key,
                    'hotel_name' => $booking->hotel_name,
                    'status' => $booking->status,
                    'check_in' => $booking->check_in,
                    'check_out' => $booking->check_out,
                    'created_at' => $booking->created_at->toIso8601String(),
                ];
            }, $bookings),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];

        $whatsappMessage = MessageBuilderService::formatBookingHistoryMessage($result['bookings'], $total);
        $stageContext = $this->sessionService->getStageContext($session);
        $result['sessionContext'] = $stageContext;

        return DotwAIResponse::success($result, $whatsappMessage);
    }

    /**
     * Handle 'voucher' action: resend booking voucher.
     */
    private function handleVoucher(DotwAIContext $context, string $phone, array $params, array $session): JsonResponse
    {
        // Get prebook_key from params or session
        $prebookKey = $params['prebook_key'] ?? $session['prebook_key'] ?? null;

        if (empty($prebookKey)) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::SESSION_EMPTY,
                'No prebook_key found',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Find booking
        $booking = DotwAIBooking::where('prebook_key', $prebookKey)
            ->where('phone', $phone)
            ->first();

        if (!$booking) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::PREBOOK_NOT_FOUND,
                'Booking not found',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Validate booking is confirmed
        if ($booking->status !== DotwAIBooking::STATUS_CONFIRMED) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::BOOKING_FAILED,
                'Voucher can only be resent for confirmed bookings',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        // Resend voucher
        $voucherService = new VoucherService();
        $success = $voucherService->resendVoucher($booking);

        if (!$success) {
            $stageContext = $this->sessionService->getStageContext($session);
            $errorResponse = DotwAIResponse::error(
                DotwAIResponse::DOTW_API_ERROR,
                'Voucher resend failed',
            );
            $responseData = json_decode($errorResponse->getContent(), true);
            $responseData['sessionContext'] = $stageContext;
            return response()->json($responseData, $errorResponse->getStatusCode());
        }

        $result = [
            'prebook_key' => $booking->prebook_key,
            'success' => true,
        ];

        $whatsappMessage = MessageBuilderService::formatVoucherResendConfirmation($booking);
        $stageContext = $this->sessionService->getStageContext($session);
        $result['sessionContext'] = $stageContext;

        return DotwAIResponse::success($result, $whatsappMessage);
    }
}
