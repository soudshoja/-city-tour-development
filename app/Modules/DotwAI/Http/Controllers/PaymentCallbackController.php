<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Http\Controllers;

use App\Http\Controllers\WhatsappController;
use App\Models\Payment;
use App\Modules\DotwAI\Jobs\ConfirmBookingAfterPaymentJob;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Support\PaymentGateway\MyFatoorah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Payment callback handler for MyFatoorah webhooks on DOTW hotel bookings.
 *
 * This controller is registered OUTSIDE the dotwai.resolve middleware group
 * because payment callbacks come from the payment gateway, not from WhatsApp
 * users, so there is no phone number to resolve.
 *
 * Flow:
 * 1. Receive redirect/webhook from MyFatoorah with paymentId query param
 * 2. Verify payment status via MyFatoorah::getPaymentStatus API
 * 3. Extract prebook_key from UserDefinedField (set by PaymentBridgeService)
 * 4. Find the DotwAIBooking and verify it is ours (process='dotwai_hotel')
 * 5. If paid: update booking, dispatch ConfirmBookingAfterPaymentJob
 * 6. If failed: update booking, notify customer via WhatsApp
 *
 * CRITICAL: Always return HTTP 200 to the payment gateway regardless of
 * internal errors. Non-200 responses cause the gateway to retry indefinitely.
 *
 * Idempotency: bookings already confirmed or confirming are ignored gracefully.
 *
 * @see B2C-02 Confirm after payment; refund if re-block fails
 * @see B2B-02 B2B agent without credit receives payment link
 */
class PaymentCallbackController extends Controller
{
    /**
     * Handle the MyFatoorah payment callback.
     *
     * MyFatoorah redirects to this URL after payment with ?paymentId=...
     * It is also used as the ErrorUrl with ?error=1.
     *
     * ALWAYS returns HTTP 200 -- non-200 causes gateway retries.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        try {
            // Handle error redirect from MyFatoorah (ErrorUrl with ?error=1)
            if ($request->query('error')) {
                return $this->handlePaymentError($request);
            }

            $paymentId = $request->query('paymentId');

            if (empty($paymentId)) {
                Log::warning('[DotwAI][Callback] No paymentId in callback request', [
                    'query' => $request->query(),
                ]);

                return response()->json(['status' => 'ignored', 'reason' => 'no_payment_id'], 200);
            }

            // Verify payment status with MyFatoorah
            $gateway      = new MyFatoorah();
            $statusResult = $gateway->getPaymentStatus('payment', $paymentId);

            if (! ($statusResult['success'] ?? false)) {
                Log::error('[DotwAI][Callback] Failed to get payment status', [
                    'payment_id' => $paymentId,
                    'result'     => $statusResult,
                ]);

                // Return 200 -- we cannot retry this ourselves; log for admin review
                return response()->json(['status' => 'error', 'reason' => 'status_check_failed'], 200);
            }

            $invoiceData = $statusResult['data'] ?? [];

            // Extract UserDefinedField to identify this as a DotwAI booking
            $userDefinedField = $invoiceData['UserDefinedField'] ?? null;
            $userData         = [];

            if (! empty($userDefinedField)) {
                $userData = json_decode($userDefinedField, true) ?? [];
            }

            $prebookKey = $userData['prebook_key'] ?? null;
            $process    = $userData['process'] ?? null;

            // If not tagged as dotwai_hotel, ignore gracefully (may be a different module)
            if (empty($prebookKey) || $process !== 'dotwai_hotel') {
                Log::info('[DotwAI][Callback] Not a DotwAI payment, ignoring', [
                    'payment_id'  => $paymentId,
                    'process'     => $process,
                    'prebook_key' => $prebookKey,
                ]);

                return response()->json(['status' => 'ignored', 'reason' => 'not_dotwai'], 200);
            }

            // Find the booking
            $booking = DotwAIBooking::where('prebook_key', $prebookKey)->first();

            if ($booking === null) {
                Log::warning('[DotwAI][Callback] Booking not found for prebook_key', [
                    'prebook_key' => $prebookKey,
                    'payment_id'  => $paymentId,
                ]);

                return response()->json(['status' => 'error', 'reason' => 'booking_not_found'], 200);
            }

            // Idempotency: already confirmed or confirming -- nothing to do
            if (in_array($booking->status, [
                DotwAIBooking::STATUS_CONFIRMED,
                DotwAIBooking::STATUS_CONFIRMING,
            ], true)) {
                Log::info('[DotwAI][Callback] Booking already confirmed/confirming, ignoring', [
                    'prebook_key' => $prebookKey,
                    'status'      => $booking->status,
                ]);

                return response()->json(['status' => 'already_processing'], 200);
            }

            // Determine if payment was successful
            $invoiceStatus = $invoiceData['InvoiceStatus'] ?? '';
            $transactions  = $invoiceData['InvoiceTransactions'] ?? [];
            $isPaid        = $this->isPaymentSuccessful($invoiceStatus, $transactions);

            if ($isPaid) {
                // Update booking and Payment record, then dispatch async job
                $booking->update([
                    'payment_status'      => 'paid',
                    'payment_gateway_ref' => (string) $paymentId,
                ]);

                // Update the linked Payment record if it exists
                if (! empty($booking->payment_id)) {
                    Payment::where('id', $booking->payment_id)
                        ->update(['status' => 'paid', 'completed' => true]);
                }

                // Dispatch queued job to re-block and confirm with DOTW
                ConfirmBookingAfterPaymentJob::dispatch($prebookKey);

                Log::info('[DotwAI][Callback] Payment confirmed, job dispatched', [
                    'prebook_key' => $prebookKey,
                    'payment_id'  => $paymentId,
                ]);

                return response()->json(['status' => 'processing'], 200);
            }

            // Payment failed -- notify customer and mark as failed
            $booking->update(['payment_status' => 'failed']);

            $phone = $booking->client_phone ?? $booking->agent_phone;

            if (! empty($phone)) {
                try {
                    $failMessage = "عذراً، لم تنجح عملية الدفع. يرجى المحاولة مرة أخرى.\n"
                        . "Sorry, your payment was not successful. Please try again.";

                    $whatsapp = app(WhatsappController::class);
                    $whatsapp->sendToResayil($phone, $failMessage);
                } catch (\Throwable $e) {
                    Log::error('[DotwAI][Callback] Failed to send WhatsApp payment failure notice', [
                        'prebook_key' => $prebookKey,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            Log::info('[DotwAI][Callback] Payment failed, booking updated', [
                'prebook_key'   => $prebookKey,
                'invoice_status' => $invoiceStatus,
            ]);

            return response()->json(['status' => 'payment_failed'], 200);
        } catch (\Throwable $e) {
            // CRITICAL: Always return 200 to the payment gateway
            Log::critical('[DotwAI][Callback] Unexpected exception in handleCallback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'query' => $request->query(),
            ]);

            return response()->json(['status' => 'error', 'reason' => 'internal_error'], 200);
        }
    }

    /**
     * Handle error redirect from MyFatoorah (ErrorUrl with ?error=1).
     *
     * @param Request $request The incoming error redirect request
     * @return JsonResponse Always HTTP 200
     */
    private function handlePaymentError(Request $request): JsonResponse
    {
        Log::warning('[DotwAI][Callback] Payment error redirect received', [
            'query' => $request->query(),
        ]);

        // MyFatoorah error redirects may or may not include paymentId
        $paymentId = $request->query('paymentId');

        if (! empty($paymentId)) {
            // Try to find and update the booking
            try {
                $gateway      = new MyFatoorah();
                $statusResult = $gateway->getPaymentStatus('payment', $paymentId);

                if ($statusResult['success'] ?? false) {
                    $userData   = json_decode($statusResult['data']['UserDefinedField'] ?? '{}', true) ?? [];
                    $prebookKey = $userData['prebook_key'] ?? null;

                    if (! empty($prebookKey) && ($userData['process'] ?? null) === 'dotwai_hotel') {
                        DotwAIBooking::where('prebook_key', $prebookKey)
                            ->update(['payment_status' => 'failed']);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('[DotwAI][Callback] Error handling payment error redirect', [
                    'payment_id' => $paymentId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['status' => 'payment_error_received'], 200);
    }

    /**
     * Determine if a MyFatoorah invoice was successfully paid.
     *
     * Checks InvoiceStatus and falls back to InvoiceTransactions for confirmation.
     *
     * @param string                    $invoiceStatus The InvoiceStatus field from MyFatoorah
     * @param array<int, array<mixed>>  $transactions  The InvoiceTransactions array
     * @return bool True if payment is confirmed successful
     */
    private function isPaymentSuccessful(string $invoiceStatus, array $transactions): bool
    {
        if (strtolower($invoiceStatus) === 'paid') {
            return true;
        }

        // Fallback: check transactions for a successful one
        foreach ($transactions as $tx) {
            $txStatus = strtolower($tx['TransactionStatus'] ?? '');
            if (in_array($txStatus, ['succss', 'success', 'successful'], true)) {
                return true;
            }
        }

        return false;
    }
}
