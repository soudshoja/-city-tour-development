<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Modules\DotwAI\Models\DotwAIBooking;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lifecycle deadline detection service for DOTW AI bookings.
 *
 * Queries the database for bookings that need lifecycle actions:
 * - Reminders due (3/2/1 days before cancellation deadline)
 * - Deadlines that have passed (for auto-invoicing)
 *
 * This service is query-only (no API calls, no side effects).
 * Called by ProcessDeadlinesCommand to get lists for job dispatch.
 *
 * Reminder window: Only refundable (is_apr = false) confirmed bookings are
 * eligible. APR bookings are non-refundable and auto-invoiced at confirmation.
 *
 * @see LIFE-02 Reminder dispatch logic
 * @see LIFE-03 Deadline-pass auto-invoice logic
 * @see LIFE-05 Scheduler integration
 */
class LifecycleService
{
    /**
     * Find confirmed bookings due for a cancellation deadline reminder.
     *
     * Returns bookings where:
     * - status = 'confirmed'
     * - is_apr = false (refundable bookings only; APR never need reminders)
     * - reminder_sent_at IS NULL (no reminder sent yet)
     * - cancellation_deadline is between now and +3 days (the 3/2/1 day window)
     *
     * The 3-day window captures all urgency levels in a single daily scan.
     * The queue job calculates the exact days_left at send time.
     *
     * @return Collection<int, DotwAIBooking>
     */
    public function findBookingsDueForReminder(): Collection
    {
        return DotwAIBooking::where('status', DotwAIBooking::STATUS_CONFIRMED)
            ->where('is_apr', false)
            ->whereNull('reminder_sent_at')
            ->where('cancellation_deadline', '>=', now())
            ->where('cancellation_deadline', '<=', now()->addDays(3))
            ->get();
    }

    /**
     * Find confirmed bookings whose cancellation deadline has already passed.
     *
     * Returns bookings where:
     * - status = 'confirmed'
     * - cancellation_deadline < now() (deadline has passed)
     * - auto_invoiced_at IS NULL (not yet auto-invoiced)
     *
     * Both refundable and APR bookings are included here because an APR booking
     * that somehow still has confirmed status with a past deadline also needs
     * invoicing. Normal APR flow (auto-invoice at confirmation) is handled
     * by BookingService (Phase 21 Plan 02).
     *
     * @return Collection<int, DotwAIBooking>
     */
    public function findBookingsWithPassedDeadline(): Collection
    {
        return DotwAIBooking::where('status', DotwAIBooking::STATUS_CONFIRMED)
            ->where('cancellation_deadline', '<', now())
            ->whereNull('auto_invoiced_at')
            ->get();
    }

    /**
     * Mark a booking's reminder as sent.
     *
     * Called by SendReminderJob after successfully dispatching the WhatsApp
     * reminder. Sets reminder_sent_at = now() to prevent duplicate reminders
     * on subsequent scheduler cycles.
     *
     * @param int $bookingId The DotwAIBooking primary key
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function markReminderSent(int $bookingId): void
    {
        DotwAIBooking::findOrFail($bookingId)->update(['reminder_sent_at' => now()]);
    }
}
