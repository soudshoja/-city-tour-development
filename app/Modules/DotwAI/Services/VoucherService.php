<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Modules\DotwAI\Models\DotwAIBooking;
use Illuminate\Support\Facades\Log;

/**
 * Voucher delivery service for DotwAI module.
 *
 * Formats booking confirmations as bilingual Arabic/English WhatsApp messages
 * and delivers them via the existing WhatsApp (Resayil) integration.
 *
 * Vouchers are text-based (not PDF attachments) for maximum WhatsApp reliability.
 *
 * @see B2B-07 Voucher sent via WhatsApp after booking confirmation
 */
class VoucherService
{
    /**
     * Format and send a booking voucher via WhatsApp.
     *
     * Sends to client_phone if available, falls back to agent_phone.
     * Updates voucher_sent_at on the booking after successful delivery.
     *
     * @param DotwAIBooking $booking The confirmed booking to voucher
     * @return bool True on success, false on failure
     */
    public function sendVoucher(DotwAIBooking $booking): bool
    {
        try {
            $message = MessageBuilderService::formatVoucherMessage($booking);
            $phone = $booking->client_phone ?? $booking->agent_phone;

            /** @var \App\Http\Controllers\WhatsappController $whatsapp */
            $whatsapp = app(\App\Http\Controllers\WhatsappController::class);
            $whatsapp->sendToResayil(
                $phone,
                $message,
                'Booking Confirmation | تأكيد الحجز',
                'City Travelers',
                null,
            );

            $booking->update(['voucher_sent_at' => now()]);

            Log::info('[DotwAI] Voucher sent', [
                'prebook_key' => $booking->prebook_key,
                'phone'       => $phone,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[DotwAI] Voucher send failed', [
                'prebook_key' => $booking->prebook_key,
                'error'       => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Re-send a booking voucher via WhatsApp (ignores voucher_sent_at).
     *
     * Same as sendVoucher but always re-sends regardless of whether a voucher
     * was already sent. Logs as "resent" for audit trail.
     *
     * @param DotwAIBooking $booking The confirmed booking to re-voucher
     * @return bool True on success, false on failure
     */
    public function resendVoucher(DotwAIBooking $booking): bool
    {
        try {
            $message = MessageBuilderService::formatVoucherMessage($booking);
            $phone = $booking->client_phone ?? $booking->agent_phone;

            /** @var \App\Http\Controllers\WhatsappController $whatsapp */
            $whatsapp = app(\App\Http\Controllers\WhatsappController::class);
            $whatsapp->sendToResayil(
                $phone,
                $message,
                'Booking Confirmation | تأكيد الحجز',
                'City Travelers',
                null,
            );

            $booking->update(['voucher_sent_at' => now()]);

            Log::info('[DotwAI] Voucher resent', [
                'prebook_key' => $booking->prebook_key,
                'phone'       => $phone,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[DotwAI] Voucher resend failed', [
                'prebook_key' => $booking->prebook_key,
                'error'       => $e->getMessage(),
            ]);

            return false;
        }
    }
}
