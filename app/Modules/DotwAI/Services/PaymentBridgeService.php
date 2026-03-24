<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Models\Agent;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Services\GatewayConfigService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Payment link generation service for DotwAI bookings.
 *
 * Wraps MyFatoorah's ExecutePayment API directly (without using
 * the existing MyFatoorah::createCharge() method) to gain full
 * control over CallBackUrl and UserDefinedField, while creating
 * a Payment record for accounting traceability.
 *
 * Key design decisions:
 * - Direct API call allows module-owned callback URL
 * - UserDefinedField tags payment as 'dotwai_hotel' with prebook_key
 * - Payment record is created first for audit trail (Pitfall 6)
 * - No modification of existing MyFatoorah.php
 *
 * @see B2B-02 B2B agent without credit receives payment link
 * @see B2C-01 B2C customer receives payment link with markup
 * @see B2C-02 Payment link expires and triggers re-block
 */
class PaymentBridgeService
{
    /**
     * Create a MyFatoorah payment link for a DOTW booking.
     *
     * Steps:
     * 1. Create Payment record (required for accounting traceability)
     * 2. Resolve MyFatoorah credentials via GatewayConfigService
     * 3. Find active MyFatoorah payment method
     * 4. Call ExecutePayment API directly with module callback URL
     * 5. Update booking with payment link details
     *
     * @param DotwAIBooking $booking The pending booking that needs payment
     * @return array{payment_url: string, expiry: string, amount: float, currency: string}|array{error: bool, message: string}
     */
    public function createPaymentLink(DotwAIBooking $booking): array
    {
        try {
            // Step 1: Create Payment record for accounting traceability (Pitfall 6)
            $agentId  = $this->resolveAgentId($booking->agent_phone);
            $clientId = $this->resolveClientId($booking);

            $payment = Payment::create([
                'client_id'       => $clientId,
                'agent_id'        => $agentId,
                'voucher_number'  => 'DOTWAI-' . $booking->id,
                'amount'          => $booking->display_total_fare,
                'currency'        => $booking->display_currency,
                'status'          => 'pending',
                'payment_gateway' => config('dotwai.default_payment_gateway', 'myfatoorah'),
                'notes'           => "DOTW Hotel: {$booking->hotel_name} ({$booking->check_in?->format('Y-m-d')} - {$booking->check_out?->format('Y-m-d')})",
            ]);

            // Step 2: Resolve MyFatoorah credentials
            $configService    = new GatewayConfigService();
            $myfatoorahResult = $configService->getMyFatoorahConfig();

            if ($myfatoorahResult['status'] === 'error') {
                Log::error('[DotwAI][PaymentBridge] MyFatoorah config error', [
                    'booking_id' => $booking->id,
                    'message'    => $myfatoorahResult['message'] ?? 'Config not found',
                ]);

                return ['error' => true, 'message' => 'Payment gateway not configured'];
            }

            $mfConfig = $myfatoorahResult['data'];

            // Step 3: Find active MyFatoorah payment method
            // Use withoutGlobalScopes to bypass Auth-based company scope
            $paymentMethod = PaymentMethod::withoutGlobalScopes()
                ->where('type', 'myfatoorah')
                ->where('is_active', true)
                ->first();

            if ($paymentMethod === null) {
                // Fallback: try by gateway name variant
                $paymentMethod = PaymentMethod::withoutGlobalScopes()
                    ->whereNotNull('myfatoorah_id')
                    ->where('is_active', true)
                    ->first();
            }

            if ($paymentMethod === null) {
                Log::error('[DotwAI][PaymentBridge] No active MyFatoorah payment method found', [
                    'booking_id' => $booking->id,
                ]);

                return ['error' => true, 'message' => 'No active payment method found for MyFatoorah'];
            }

            // Step 4: Build direct ExecutePayment payload
            $guestName   = $this->extractGuestName($booking);
            $clientEmail = $booking->client_email ?? 'booking@citytravelers.co';
            $clientPhone = $booking->client_phone ?? $booking->agent_phone ?? '50000000';

            // Strip country code prefix for MyFatoorah's CustomerMobile field
            $mobileNumber = preg_replace('/^\+\d{1,3}/', '', $clientPhone);
            $mobileNumber = ltrim($mobileNumber, '0');

            $expiryDate = now()->addHours((int) config('dotwai.payment_link_expiry_hours', 48))
                ->format('Y-m-d H:i:s');

            $userDefinedField = json_encode([
                'dotwai_booking_id' => $booking->id,
                'prebook_key'       => $booking->prebook_key,
                'process'           => 'dotwai_hotel',
            ]);

            $payload = [
                'PaymentMethodId'  => $paymentMethod->myfatoorah_id,
                'InvoiceValue'     => (float) $booking->display_total_fare,
                'CustomerName'     => $guestName,
                'CustomerEmail'    => $clientEmail,
                'MobileCountryCode' => '+965',
                'CustomerMobile'   => $mobileNumber,
                'DisplayCurrencyIso' => $booking->display_currency ?? 'KWD',
                'ExpiryDate'       => $expiryDate,
                'CallBackUrl'      => url('/api/dotwai/payment_callback'),
                'ErrorUrl'         => url('/api/dotwai/payment_callback') . '?error=1',
                'Language'         => 'en',
                'UserDefinedField' => $userDefinedField,
                'InvoiceItems'     => [
                    [
                        'ItemName'  => "Hotel: {$booking->hotel_name}",
                        'Quantity'  => 1,
                        'UnitPrice' => (float) $booking->display_total_fare,
                    ],
                ],
            ];

            Log::info('[DotwAI][PaymentBridge] ExecutePayment request', [
                'booking_id' => $booking->id,
                'prebook_key' => $booking->prebook_key,
                'amount'     => $booking->display_total_fare,
                'currency'   => $booking->display_currency,
            ]);

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$mfConfig['api_key']}",
                'Content-Type'  => 'application/json',
            ])->post("{$mfConfig['base_url']}/ExecutePayment", $payload);

            Log::info('[DotwAI][PaymentBridge] ExecutePayment response', [
                'booking_id' => $booking->id,
                'status'     => $response->status(),
                'response'   => $response->json(),
            ]);

            if (! $response->successful()) {
                $errorBody = $response->json();
                $message   = $errorBody['Message'] ?? 'Payment initiation failed';

                Log::error('[DotwAI][PaymentBridge] ExecutePayment failed', [
                    'booking_id' => $booking->id,
                    'response'   => $errorBody,
                ]);

                return ['error' => true, 'message' => $message];
            }

            $resData = $response->json();

            if (! isset($resData['Data']['InvoiceId'], $resData['Data']['PaymentURL'])) {
                Log::error('[DotwAI][PaymentBridge] Invalid ExecutePayment response structure', [
                    'booking_id' => $booking->id,
                    'response'   => $resData,
                ]);

                return ['error' => true, 'message' => 'Invalid response from payment gateway'];
            }

            $paymentUrl    = $resData['Data']['PaymentURL'];
            $invoiceId     = $resData['Data']['InvoiceId'];
            $responseExpiry = $resData['Data']['ExpiryDate'] ?? $expiryDate;

            // Step 5: Update Payment record with gateway reference
            $payment->update([
                'payment_url' => $paymentUrl,
                'expiry_date' => $responseExpiry,
            ]);

            // Update booking with payment link details
            $booking->update([
                'payment_id'          => $payment->id,
                'payment_link'        => $paymentUrl,
                'payment_status'      => 'pending',
                'payment_gateway_ref' => (string) $invoiceId,
                'status'              => DotwAIBooking::STATUS_PENDING_PAYMENT,
            ]);

            Log::info('[DotwAI][PaymentBridge] Payment link created', [
                'booking_id'  => $booking->id,
                'prebook_key' => $booking->prebook_key,
                'payment_id'  => $payment->id,
                'invoice_id'  => $invoiceId,
            ]);

            return [
                'payment_url' => $paymentUrl,
                'expiry'      => $responseExpiry,
                'amount'      => (float) $booking->display_total_fare,
                'currency'    => $booking->display_currency ?? 'KWD',
            ];
        } catch (\Throwable $e) {
            Log::error('[DotwAI][PaymentBridge] Unexpected error in createPaymentLink', [
                'booking_id' => $booking->id ?? null,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return ['error' => true, 'message' => 'Payment link creation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Resolve agent_id from a phone number.
     *
     * @param string|null $phone WhatsApp phone number
     * @return int|null Agent ID or null if not found
     */
    private function resolveAgentId(?string $phone): ?int
    {
        if (empty($phone)) {
            return null;
        }

        $agent = Agent::where('phone_number', $phone)
            ->orWhere('phone_number', ltrim($phone, '+'))
            ->first();

        return $agent?->id;
    }

    /**
     * Resolve client_id for the booking.
     *
     * Tries to find a client by phone number.
     * Returns null if not found (Payment can be created without client_id).
     *
     * @param DotwAIBooking $booking The booking record
     * @return int|null Client ID or null
     */
    private function resolveClientId(DotwAIBooking $booking): ?int
    {
        $phone = $booking->client_phone ?? $booking->agent_phone;

        if (empty($phone)) {
            return null;
        }

        // Try to find client by phone through agent's clients
        $agentId = $this->resolveAgentId($booking->agent_phone);

        if ($agentId !== null) {
            $agent  = Agent::with('clients')->find($agentId);
            $client = $agent?->clients()
                ->where(function ($q) use ($phone) {
                    $q->where('phone', $phone)
                      ->orWhere('phone', ltrim($phone, '+'));
                })
                ->first();

            if ($client !== null) {
                return $client->id;
            }
        }

        return null;
    }

    /**
     * Extract the primary guest name from booking guest_details.
     *
     * @param DotwAIBooking $booking The booking record
     * @return string Full name of first guest, or 'Guest'
     */
    private function extractGuestName(DotwAIBooking $booking): string
    {
        $guestDetails = $booking->guest_details;

        if (! empty($guestDetails) && is_array($guestDetails)) {
            $firstGuest = $guestDetails[0] ?? null;

            if ($firstGuest !== null) {
                $firstName = trim($firstGuest['first_name'] ?? '');
                $lastName  = trim($firstGuest['last_name'] ?? '');
                $fullName  = trim("{$firstName} {$lastName}");

                if (! empty($fullName)) {
                    return $fullName;
                }
            }
        }

        return 'Guest';
    }
}
