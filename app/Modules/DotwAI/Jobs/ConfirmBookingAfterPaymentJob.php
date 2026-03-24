<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Jobs;

use App\Http\Controllers\WhatsappController;
use App\Models\Invoice;
use App\Models\Task;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Modules\DotwAI\Services\BookingService;
use App\Modules\DotwAI\Services\CreditService;
use App\Modules\DotwAI\Services\MessageBuilderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued job to re-block the DOTW rate and confirm the booking after payment.
 *
 * This job bridges the payment gateway confirmation gap. When a customer/agent
 * pays via MyFatoorah, we cannot immediately call DOTW because:
 * 1. DOTW has a 25s response time (payment gateway timeout is shorter)
 * 2. The original allocation window may have expired
 *
 * Flow:
 * 1. Re-block rate with DOTW (getfresh allocation)
 * 2. Confirm booking via DOTW API
 * 3. Create Task and Invoice records (B2C-04)
 * 4. Send WhatsApp voucher/confirmation to customer
 *
 * On failure: notify customer via WhatsApp and mark payment_status=refund_pending.
 *
 * Retry policy: 4 attempts with exponential backoff [30s, 120s, 300s]
 * Final failure: status=failed, admin handles manually (no auto-refund on final fail)
 *
 * @see B2C-02 Re-block after payment, refund on rate_unavailable
 * @see B2C-04 Task + Invoice creation after confirmation
 * @see B2B-07 Voucher delivery via WhatsApp after confirmation
 * @see RESEARCH Pitfall 3: Idempotency gate prevents duplicate confirmations
 * @see RESEARCH Pitfall 4: Queue handles DOTW 25s response time
 */
class ConfirmBookingAfterPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before the job is marked failed.
     */
    public int $tries = 4;

    /**
     * Seconds to wait between retry attempts.
     * Matches backoff: 30s, 2min, 5min.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Create a new job instance.
     *
     * @param string $prebookKey The DotwAIBooking prebook_key to confirm
     */
    public function __construct(
        private string $prebookKey,
    ) {}

    /**
     * Execute the job: re-block rate, confirm with DOTW, create task/invoice, send WhatsApp.
     */
    public function handle(): void
    {
        // Load the booking
        $booking = DotwAIBooking::where('prebook_key', $this->prebookKey)->first();

        if ($booking === null) {
            Log::warning('[DotwAI][ConfirmJob] Booking not found', [
                'prebook_key' => $this->prebookKey,
            ]);

            return;
        }

        // Idempotency gate (Pitfall 3): already confirmed, nothing to do
        if (! empty($booking->confirmation_no)) {
            Log::info('[DotwAI][ConfirmJob] Already confirmed, skipping', [
                'prebook_key'     => $this->prebookKey,
                'confirmation_no' => $booking->confirmation_no,
            ]);

            return;
        }

        // Mark as confirming to prevent concurrent job runs
        $booking->update(['status' => DotwAIBooking::STATUS_CONFIRMING]);

        Log::info('[DotwAI][ConfirmJob] Starting confirmation', [
            'prebook_key' => $this->prebookKey,
            'booking_id'  => $booking->id,
            'track'       => $booking->track,
        ]);

        // Delegate to BookingService::confirmAfterPayment (re-block + DOTW confirm)
        /** @var BookingService $bookingService */
        $bookingService = app(BookingService::class);
        $result         = $bookingService->confirmAfterPayment($booking);

        // Reload booking after service updates it
        $booking->refresh();

        if (isset($result['error']) && $result['error'] === true) {
            $this->handleConfirmationFailure($booking, $result);

            return;
        }

        // Successful confirmation -- create Task and Invoice records
        $this->createTaskAndInvoice($booking);

        // Send WhatsApp confirmation/voucher
        $this->sendConfirmationWhatsApp($booking);

        Log::info('[DotwAI][ConfirmJob] Confirmation complete', [
            'prebook_key'     => $this->prebookKey,
            'confirmation_no' => $booking->confirmation_no,
            'task_id'         => $booking->task_id,
            'invoice_id'      => $booking->invoice_id,
        ]);
    }

    /**
     * Handle job final failure (all retries exhausted).
     *
     * Marks booking as failed. Does NOT auto-send WhatsApp or
     * initiate refund on final failure -- admin handles manually.
     */
    public function failed(\Throwable $e): void
    {
        Log::critical('[DotwAI][ConfirmJob] Job permanently failed after all retries', [
            'prebook_key' => $this->prebookKey,
            'error'       => $e->getMessage(),
            'trace'       => $e->getTraceAsString(),
        ]);

        // Mark booking as failed for admin review
        $booking = DotwAIBooking::where('prebook_key', $this->prebookKey)->first();

        if ($booking !== null) {
            $booking->update(['status' => DotwAIBooking::STATUS_FAILED]);
        }
    }

    /**
     * Handle confirmation failure (rate unavailable or booking rejected by DOTW).
     *
     * Initiates refund flow and notifies customer via WhatsApp.
     *
     * @param DotwAIBooking        $booking The failed booking
     * @param array<string, mixed> $result  Error result from BookingService::confirmAfterPayment
     */
    private function handleConfirmationFailure(DotwAIBooking $booking, array $result): void
    {
        $code = $result['code'] ?? 'BOOKING_FAILED';

        Log::critical('[DotwAI][ConfirmJob] Re-block/confirm failed after payment, refund initiated', [
            'prebook_key' => $this->prebookKey,
            'booking_id'  => $booking->id,
            'code'        => $code,
        ]);

        // Determine reason for message formatting
        $reason = ($code === 'RATE_UNAVAILABLE') ? 'rate_unavailable' : 'booking_failed';

        // Update booking status
        $booking->update([
            'status'         => DotwAIBooking::STATUS_FAILED,
            'payment_status' => 'refund_pending',
        ]);

        // Send WhatsApp notification to customer/agent
        $phone = $booking->client_phone ?? $booking->agent_phone;

        if (! empty($phone)) {
            try {
                $message  = MessageBuilderService::formatBookingFailed($booking, $reason);
                $whatsapp = app(WhatsappController::class);
                $whatsapp->sendToResayil($phone, $message);
            } catch (\Throwable $e) {
                Log::error('[DotwAI][ConfirmJob] Failed to send WhatsApp failure notification', [
                    'prebook_key' => $this->prebookKey,
                    'phone'       => $phone,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create Task and Invoice records after a successful DOTW confirmation.
     *
     * @param DotwAIBooking $booking The confirmed booking
     */
    private function createTaskAndInvoice(DotwAIBooking $booking): void
    {
        try {
            // Create Task record (B2C-04)
            // Map to Task model's fillable fields (supplier_id not known, use reference instead)
            $task = Task::create([
                'company_id'  => $booking->company_id,
                'type'        => 'hotel',
                'status'      => 'issued',
                'client_name' => $this->extractGuestName($booking),
                'reference'   => $booking->confirmation_no,
                'total'       => $booking->display_total_fare,
                'is_n8n_booking' => true,
            ]);

            Log::info('[DotwAI][ConfirmJob] Task created', [
                'prebook_key' => $this->prebookKey,
                'task_id'     => $task->id,
            ]);

            // Create Invoice record (B2C-04)
            // Invoice requires: invoice_number, client_id (if available), currency, amount, status
            $invoice = Invoice::create([
                'invoice_number' => 'DOTWAI-INV-' . $booking->id,
                'client_id'      => null, // No direct client for B2C/B2B gateway via WhatsApp
                'agent_id'       => null,
                'currency'       => $booking->display_currency ?? 'KWD',
                'sub_amount'     => $booking->display_total_fare,
                'amount'         => $booking->display_total_fare,
                'status'         => 'paid', // Payment already received via gateway
                'invoice_date'   => now(),
                'label'          => "DOTW Hotel: {$booking->hotel_name}",
            ]);

            Log::info('[DotwAI][ConfirmJob] Invoice created', [
                'prebook_key' => $this->prebookKey,
                'invoice_id'  => $invoice->id,
            ]);

            // Link task and invoice to booking
            $booking->update([
                'task_id'    => $task->id,
                'invoice_id' => $invoice->id,
            ]);
        } catch (\Throwable $e) {
            // Log but do not fail the job -- booking is confirmed, task/invoice can be created manually
            Log::error('[DotwAI][ConfirmJob] Failed to create task/invoice', [
                'prebook_key' => $this->prebookKey,
                'booking_id'  => $booking->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send WhatsApp confirmation message to customer/agent after successful booking.
     *
     * @param DotwAIBooking $booking The confirmed booking
     */
    private function sendConfirmationWhatsApp(DotwAIBooking $booking): void
    {
        $phone = $booking->client_phone ?? $booking->agent_phone;

        if (empty($phone)) {
            Log::warning('[DotwAI][ConfirmJob] No phone number to send WhatsApp confirmation', [
                'prebook_key' => $this->prebookKey,
            ]);

            return;
        }

        try {
            $message = MessageBuilderService::formatBookingConfirmation([
                'confirmation_no'       => $booking->confirmation_no,
                'booking_ref'           => $booking->booking_ref,
                'hotel_name'            => $booking->hotel_name,
                'check_in'              => $booking->check_in?->format('Y-m-d'),
                'check_out'             => $booking->check_out?->format('Y-m-d'),
                'guest_details'         => $booking->guest_details ?? [],
                'payment_guaranteed_by' => $booking->payment_guaranteed_by,
                'display_total_fare'    => $booking->display_total_fare,
                'display_currency'      => $booking->display_currency,
            ]);

            $whatsapp = app(WhatsappController::class);
            $whatsapp->sendToResayil($phone, $message);

            // Record that voucher was sent
            $booking->update(['voucher_sent_at' => now()]);

            Log::info('[DotwAI][ConfirmJob] WhatsApp confirmation sent', [
                'prebook_key' => $this->prebookKey,
                'phone'       => $phone,
            ]);
        } catch (\Throwable $e) {
            Log::error('[DotwAI][ConfirmJob] Failed to send WhatsApp confirmation', [
                'prebook_key' => $this->prebookKey,
                'phone'       => $phone,
                'error'       => $e->getMessage(),
            ]);
        }
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
