<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Jobs;

use App\Http\Controllers\WhatsappController;
use App\Modules\DotwAI\Models\DotwAIBooking;
use App\Modules\DotwAI\Services\LifecycleService;
use App\Modules\DotwAI\Services\MessageBuilderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queue job to send a WhatsApp cancellation deadline reminder.
 *
 * Dispatched by ProcessDeadlinesCommand for bookings within the 3/2/1 day window
 * before their cancellation deadline. Sends a bilingual AR/EN reminder message
 * and marks reminder_sent_at to prevent duplicate sends.
 *
 * Idempotency: Checks reminder_sent_at before sending. If already set (e.g.,
 * duplicate dispatch), the job exits silently without resending.
 *
 * Retry policy: 3 attempts with exponential backoff [30s, 120s].
 * On final failure: reminder_sent_at stays NULL; scheduler will retry next cycle.
 *
 * @see LIFE-02 Reminder dispatch logic
 * @see ProcessDeadlinesCommand Scheduler entry point
 * @see LifecycleService::markReminderSent
 */
class SendReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts before the job is dead-lettered.
     */
    public int $tries = 3;

    /**
     * Seconds between retry attempts (exponential backoff: 30s, 2m).
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120];

    /**
     * Maximum seconds this job may run before timing out.
     */
    public int $timeout = 10;

    /**
     * Create a new job instance.
     *
     * @param int $bookingId The DotwAIBooking primary key
     */
    public function __construct(private int $bookingId) {}

    /**
     * Execute the job: send WhatsApp reminder and mark reminder_sent_at.
     *
     * @param LifecycleService $service Injected by Laravel's service container
     */
    public function handle(LifecycleService $service): void
    {
        $booking = DotwAIBooking::find($this->bookingId);

        // Booking deleted or already reminded (idempotency gate)
        if ($booking === null || $booking->reminder_sent_at !== null) {
            Log::info('[DotwAI][ReminderJob] Skipping — booking missing or already reminded', [
                'booking_id'       => $this->bookingId,
                'reminder_sent_at' => $booking?->reminder_sent_at,
            ]);

            return;
        }

        // Calculate days until deadline (positive = future, negative = past)
        $daysLeft = (int) now()->diffInDays($booking->cancellation_deadline, false);

        $message = MessageBuilderService::formatReminderMessage($booking, abs($daysLeft));
        $phone   = $booking->client_phone ?? $booking->agent_phone;

        if (empty($phone)) {
            Log::warning('[DotwAI][ReminderJob] No phone number for booking', [
                'booking_id' => $this->bookingId,
            ]);

            return;
        }

        try {
            /** @var WhatsappController $whatsapp */
            $whatsapp = app(WhatsappController::class);
            $whatsapp->sendToResayil($phone, $message);

            // Mark reminder sent only after successful WhatsApp dispatch
            $service->markReminderSent($this->bookingId);

            Log::info('[DotwAI][ReminderJob] Reminder sent', [
                'booking_id' => $this->bookingId,
                'phone'      => $phone,
                'days_left'  => $daysLeft,
            ]);
        } catch (Throwable $e) {
            Log::warning('[DotwAI][ReminderJob] Reminder send failed, will retry', [
                'booking_id' => $this->bookingId,
                'error'      => $e->getMessage(),
            ]);

            throw $e;  // Trigger retry via $backoff
        }
    }

    /**
     * Handle job failure after all retries are exhausted.
     *
     * reminder_sent_at remains NULL so the scheduler's next cycle will
     * re-dispatch the job (if the booking is still in the 3-day window).
     */
    public function failed(Throwable $exception): void
    {
        Log::error('[DotwAI][ReminderJob] Job failed after all retries', [
            'booking_id' => $this->bookingId,
            'error'      => $exception->getMessage(),
        ]);
        // Intentionally no status update — scheduler retries on next cycle
    }
}
