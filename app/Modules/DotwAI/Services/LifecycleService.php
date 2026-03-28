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
 * Reminder window: All confirmed bookings with a cancellation deadline are
 * eligible for reminders. APR rates were removed by DOTW (Olga Chicu, March 2026),
 * so the old is_apr=false filter is no longer needed.
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
     * - reminder_sent_at IS NULL (no reminder sent yet)
     * - cancellation_deadline is between now and +3 days (the 3/2/1 day window)
     *
     * The 3-day window captures all urgency levels in a single daily scan.
     * The queue job calculates the exact days_left at send time.
     *
     * Note: The former is_apr=false filter has been removed. DOTW confirmed APRs
     * are no longer part of their API (Olga Chicu, March 2026). All confirmed
     * bookings with a deadline receive reminders.
     *
     * @return Collection<int, DotwAIBooking>
     */
    public function findBookingsDueForReminder(): Collection
    {
        return DotwAIBooking::where('status', DotwAIBooking::STATUS_CONFIRMED)
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
     * All confirmed bookings past their deadline are included for auto-invoicing.
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
