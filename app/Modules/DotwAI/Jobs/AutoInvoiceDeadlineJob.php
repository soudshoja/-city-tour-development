<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Jobs;

use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Modules\DotwAI\Services\AccountingService;
use App\Modules\DotwAI\Services\VoucherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queue job to auto-invoice a booking after its cancellation deadline passes.
 *
 * Dispatched by ProcessDeadlinesCommand when a confirmed booking's cancellation
 * deadline is in the past and no auto_invoiced_at is set.
 *
 * Flow:
 * 1. Re-verify deadline has actually passed (clock skew guard)
 * 2. Create Invoice + JournalEntry via AccountingService (inside DB::transaction)
 * 3. Send voucher via WhatsApp (VoucherService)
 * 4. Mark auto_invoiced_at = now() to prevent duplicate invoicing
 *
 * Idempotency: Checks auto_invoiced_at before processing. If already set,
 * the job exits silently.
 *
 * Retry policy: 3 attempts with exponential backoff [60s, 300s].
 * On final failure: logged as critical for admin review.
 *
 * @see LIFE-03 Auto-invoice dispatched by scheduler, executed by queue job
 * @see ProcessDeadlinesCommand Scheduler entry point
 * @see AccountingService::createAutoInvoiceForDeadline
 */
class AutoInvoiceDeadlineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before the job is dead-lettered.
     */
    public int $tries = 3;

    /**
     * Seconds between retry attempts (exponential backoff: 1m, 5m).
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    /**
     * Maximum seconds this job may run before timing out.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     *
     * @param int $bookingId The DotwAIBooking primary key
     */
    public function __construct(private int $bookingId) {}

    /**
     * Execute the job: create accounting entries, send voucher, mark auto_invoiced_at.
     */
    public function handle(): void
    {
        $booking = DotwAIBooking::find($this->bookingId);

        // Booking deleted or already auto-invoiced (idempotency gate)
        if ($booking === null || $booking->auto_invoiced_at !== null) {
            Log::info('[DotwAI][AutoInvoiceJob] Skipping — booking missing or already invoiced', [
                'booking_id'      => $this->bookingId,
                'auto_invoiced_at' => $booking?->auto_invoiced_at,
            ]);

            return;
        }

        // Re-verify deadline has actually passed (clock skew guard)
        // If the deadline was updated after the job was dispatched, defer.
        if ($booking->cancellation_deadline !== null
            && $booking->cancellation_deadline >= now()) {
            Log::warning('[DotwAI][AutoInvoiceJob] Deadline not yet passed, deferring', [
                'booking_id' => $this->bookingId,
                'deadline'   => $booking->cancellation_deadline,
            ]);

            throw new \RuntimeException('Cancellation deadline has not yet passed; deferring auto-invoice.');
        }

        try {
            DB::transaction(function () use ($booking) {
                // Create Invoice + JournalEntry for the booking amount
                $accountingService = new AccountingService();
                $accountingService->createAutoInvoiceForDeadline($booking);

                // Send voucher via WhatsApp to confirm booking is now locked
                $voucherService = new VoucherService();
                $voucherService->sendVoucher($booking);

                // Mark as auto-invoiced (idempotency marker)
                $booking->update(['auto_invoiced_at' => now()]);
            });

            Log::info('[DotwAI][AutoInvoiceJob] Auto-invoiced after deadline', [
                'booking_id' => $this->bookingId,
                'hotel'      => $booking->hotel_name,
                'deadline'   => $booking->cancellation_deadline,
                'invoice_id' => $booking->fresh()->invoice_id,
            ]);
        } catch (Throwable $e) {
            Log::error('[DotwAI][AutoInvoiceJob] Auto-invoice failed', [
                'booking_id' => $this->bookingId,
                'error'      => $e->getMessage(),
            ]);

            throw $e;  // Trigger retry via $backoff
        }
    }

    /**
     * Handle job failure after all retries are exhausted.
     *
     * Logged as critical so admin is alerted immediately.
     * auto_invoiced_at remains NULL — booking will be picked up on next scheduler cycle.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('[DotwAI][AutoInvoiceJob] Job dead-lettered after all retries', [
            'booking_id' => $this->bookingId,
            'error'      => $exception->getMessage(),
        ]);
        // No status change — admin must manually review and reconcile
    }
}
