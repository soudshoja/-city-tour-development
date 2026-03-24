<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Controllers;

use App\Modules\DotwAI\Http\Requests\ConfirmBookingRequest;
use App\Modules\DotwAI\Http\Requests\PaymentLinkRequest;
use App\Modules\DotwAI\Http\Requests\PrebookRequest;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Modules\DotwAI\Services\BookingService;
use App\Modules\DotwAI\Services\DotwAIResponse;
use App\Modules\DotwAI\Services\MessageBuilderService;
use App\Modules\DotwAI\Services\PaymentBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Booking controller for the DotwAI module.
 *
 * Thin controller that delegates all business logic to BookingService.
 * Every response is wrapped in DotwAIResponse::success() or DotwAIResponse::error()
 * so n8n AI agents always receive a pre-formatted whatsappMessage.
 *
 * Endpoints:
 * - POST /api/dotwai/prebook_hotel    — Lock a rate and create prebook record
 * - POST /api/dotwai/confirm_booking  — Confirm using credit (B2B) or check payment status
 * - GET  /api/dotwai/balance          — Get company credit balance (B2B only)
 *
 * @see B2B-03 Rate locking via prebook_hotel
 * @see B2B-04 confirm_booking endpoint
 * @see B2B-05 get_company_balance endpoint
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly PaymentBridgeService $paymentBridge,
    ) {}

    /**
     * Prebook a hotel rate from cached search results or direct input.
     *
     * POST /api/dotwai/prebook_hotel
     *
     * Locks the rate via DotwService::getRooms(blocking=true), stores the prebook,
     * and returns a prebook_key with pricing and cancellation details.
     * For B2B agents: options are "Confirm booking" and "Cancel".
     * For B2B gateway / B2C: options are "Get payment link" and "Cancel".
     */
    public function prebookHotel(PrebookRequest $request): JsonResponse
    {
        try {
            /** @var \App\Modules\DotwAI\DTOs\DotwAIContext $context */
            $context = $request->attributes->get('dotwai_context');

            $result = $this->bookingService->prebook($context, $request->validated());

            if (isset($result['error']) && $result['error'] === true) {
                return DotwAIResponse::error(
                    $result['code'],
                    $result['message'] ?? 'Prebook failed',
                );
            }

            $whatsappMessage = MessageBuilderService::formatPrebookConfirmation($result);

            // Options depend on whether payment is needed
            $whatsappOptions = ($result['needs_payment'] ?? false)
                ? ['Get payment link', 'Cancel booking']
                : ['Confirm booking', 'Cancel booking'];

            return DotwAIResponse::success($result, $whatsappMessage, $whatsappOptions);
        } catch (\Throwable $e) {
            Log::channel('dotw')->error('[DotwAI] prebookHotel exception', [
                'error' => $e->getMessage(),
            ]);

            return DotwAIResponse::error(
                DotwAIResponse::DOTW_API_ERROR,
                'Unexpected error: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Confirm a booking using credit (B2B) or check payment status before confirming.
     *
     * POST /api/dotwai/confirm_booking
     *
     * B2B credit track: deducts credit atomically and calls DOTW immediately.
     * B2B gateway / B2C: returns PAYMENT_REQUIRED if payment not yet received.
     */
    public function confirmBooking(ConfirmBookingRequest $request): JsonResponse
    {
        try {
            /** @var \App\Modules\DotwAI\DTOs\DotwAIContext $context */
            $context = $request->attributes->get('dotwai_context');

            // Find the booking
            $booking = DotwAIBooking::where('prebook_key', $request->prebook_key)->first();

            if ($booking === null) {
                return DotwAIResponse::error(
                    DotwAIResponse::PREBOOK_NOT_FOUND,
                    "Booking not found: {$request->prebook_key}",
                );
            }

            // Check if already confirmed (idempotency)
            if ($booking->status === DotwAIBooking::STATUS_CONFIRMED) {
                $confirmation = [
                    'prebook_key'     => $booking->prebook_key,
                    'confirmation_no' => $booking->confirmation_no,
                    'booking_ref'     => $booking->booking_ref,
                    'hotel_name'      => $booking->hotel_name,
                    'check_in'        => $booking->check_in?->format('Y-m-d'),
                    'check_out'       => $booking->check_out?->format('Y-m-d'),
                    'status'          => $booking->status,
                    'guest_details'   => $booking->guest_details ?? [],
                ];

                $whatsappMessage = MessageBuilderService::formatBookingConfirmation($confirmation);

                return DotwAIResponse::error(
                    DotwAIResponse::ALREADY_CONFIRMED,
                    'Booking already confirmed',
                    $whatsappMessage,
                );
            }

            // Check expiry
            if ($booking->isExpired()) {
                return DotwAIResponse::error(
                    DotwAIResponse::PREBOOK_EXPIRED,
                    "Booking {$request->prebook_key} has expired",
                );
            }

            // Route by track
            if ($booking->track === DotwAIBooking::TRACK_B2B) {
                // Credit flow: confirm immediately
                $result = $this->bookingService->confirmWithCredit(
                    $booking,
                    $context,
                    $request->passengers,
                    $request->email,
                );

                if (isset($result['error']) && $result['error'] === true) {
                    $whatsappMsg = null;
                    if ($result['code'] === DotwAIResponse::INSUFFICIENT_CREDIT && isset($result['available'])) {
                        $whatsappMsg = "رصيد الائتمان غير كافٍ. الرصيد المتاح: " . $result['available']
                            . "\nInsufficient credit. Available balance: " . $result['available'];
                    }

                    return DotwAIResponse::error(
                        $result['code'],
                        $result['message'] ?? 'Confirmation failed',
                        $whatsappMsg,
                    );
                }

                $whatsappMessage = MessageBuilderService::formatBookingConfirmation($result);

                return DotwAIResponse::success(
                    $result,
                    $whatsappMessage,
                    ['View booking details', 'Download voucher'],
                );
            }

            // Gateway / B2C track: require payment first
            if ($booking->payment_status !== 'paid') {
                return DotwAIResponse::error(
                    DotwAIResponse::PAYMENT_REQUIRED,
                    'Payment has not been received for this booking',
                );
            }

            // Payment received but no confirmation yet -- inform caller to use the async job
            return DotwAIResponse::error(
                DotwAIResponse::BOOKING_FAILED,
                'Payment received but confirmation is being processed. Please check back shortly.',
            );
        } catch (\Throwable $e) {
            Log::channel('dotw')->error('[DotwAI] confirmBooking exception', [
                'prebook_key' => $request->prebook_key ?? null,
                'error'       => $e->getMessage(),
            ]);

            return DotwAIResponse::error(
                DotwAIResponse::DOTW_API_ERROR,
                'Unexpected error: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Generate a MyFatoorah payment link for a prebooked DOTW hotel.
     *
     * POST /api/dotwai/payment_link
     *
     * Creates a Payment record for accounting traceability, calls the
     * MyFatoorah ExecutePayment API directly (so the module owns the
     * CallBackUrl), tags the payment with a UserDefinedField so the
     * callback controller can look up the booking.
     *
     * Idempotent: returns the existing link if payment_status is still 'pending'.
     *
     * @see B2B-02 B2B agent without credit receives payment link via WhatsApp
     * @see B2C-01 B2C customer receives payment link with markup applied
     */
    public function paymentLink(PaymentLinkRequest $request): JsonResponse
    {
        try {
            /** @var \App\Modules\DotwAI\DTOs\DotwAIContext $context */
            $context = $request->attributes->get('dotwai_context');

            // Find the booking
            $booking = DotwAIBooking::where('prebook_key', $request->prebook_key)->first();

            if ($booking === null) {
                return DotwAIResponse::error(
                    DotwAIResponse::PREBOOK_NOT_FOUND,
                    "Booking not found: {$request->prebook_key}",
                );
            }

            // B2B credit track does not need a payment link
            if ($booking->track === DotwAIBooking::TRACK_B2B) {
                return DotwAIResponse::error(
                    DotwAIResponse::TRACK_DISABLED,
                    'B2B credit bookings do not require a payment link. Use confirm_booking instead.',
                    "حجز الائتمان لا يحتاج دفعة مسبقة. استخدم confirm_booking بدلاً من ذلك.\nB2B credit bookings do not require payment. Use confirm_booking.",
                );
            }

            // Idempotency: return existing link if still pending
            if (! empty($booking->payment_link) && $booking->payment_status === 'pending') {
                $paymentData = [
                    'payment_url' => $booking->payment_link,
                    'expiry'      => null,
                    'amount'      => (float) $booking->display_total_fare,
                    'currency'    => $booking->display_currency ?? 'KWD',
                ];
                $whatsappMessage = MessageBuilderService::formatPaymentLink($paymentData, $booking);

                return DotwAIResponse::success(
                    $paymentData,
                    $whatsappMessage,
                    ['Complete payment', 'Cancel booking'],
                );
            }

            // Check booking is not expired
            if ($booking->isExpired()) {
                return DotwAIResponse::error(
                    DotwAIResponse::PREBOOK_EXPIRED,
                    "Booking {$request->prebook_key} has expired",
                );
            }

            // Check not already confirmed
            if ($booking->status === DotwAIBooking::STATUS_CONFIRMED) {
                return DotwAIResponse::error(
                    DotwAIResponse::ALREADY_CONFIRMED,
                    'This booking is already confirmed',
                );
            }

            // Generate payment link via PaymentBridgeService
            $result = $this->paymentBridge->createPaymentLink($booking);

            if (isset($result['error']) && $result['error'] === true) {
                return DotwAIResponse::error(
                    DotwAIResponse::DOTW_API_ERROR,
                    $result['message'] ?? 'Failed to create payment link',
                );
            }

            $whatsappMessage = MessageBuilderService::formatPaymentLink($result, $booking);

            return DotwAIResponse::success(
                $result,
                $whatsappMessage,
                ['Complete payment', 'Cancel booking'],
            );
        } catch (\Throwable $e) {
            Log::channel('dotw')->error('[DotwAI] paymentLink exception', [
                'prebook_key' => $request->prebook_key ?? null,
                'error'       => $e->getMessage(),
            ]);

            return DotwAIResponse::error(
                DotwAIResponse::DOTW_API_ERROR,
                'Unexpected error: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Get the current credit balance for a B2B company.
     *
     * GET /api/dotwai/balance
     *
     * Returns credit_limit, used_credit, and available_credit.
     * Only available for B2B track agents.
     */
    public function getCompanyBalance(Request $request): JsonResponse
    {
        try {
            /** @var \App\Modules\DotwAI\DTOs\DotwAIContext $context */
            $context = $request->attributes->get('dotwai_context');

            if (!$context->isB2B()) {
                return DotwAIResponse::error(
                    DotwAIResponse::TRACK_DISABLED,
                    'Balance endpoint is only available for B2B agents',
                    "رصيد الائتمان متاح فقط لوكلاء B2B.\nCredit balance is only available for B2B agents.",
                );
            }

            $balance = $this->bookingService->getCompanyBalance($context);

            $whatsappMessage = MessageBuilderService::formatBalanceSummary($balance);

            return DotwAIResponse::success($balance, $whatsappMessage);
        } catch (\Throwable $e) {
            Log::channel('dotw')->error('[DotwAI] getCompanyBalance exception', [
                'error' => $e->getMessage(),
            ]);

            return DotwAIResponse::error(
                DotwAIResponse::DOTW_API_ERROR,
                'Unexpected error: ' . $e->getMessage(),
            );
        }
    }
}
