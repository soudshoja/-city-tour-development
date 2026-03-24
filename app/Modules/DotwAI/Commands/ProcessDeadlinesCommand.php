<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Commands;

use App\Modules\DotwAI\Jobs\AutoInvoiceDeadlineJob;
use App\Modules\DotwAI\Jobs\SendReminderJob;
use App\Modules\DotwAI\Services\LifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scheduler entry point for DOTW AI booking lifecycle management.
 *
 * Runs daily at 03:00 (registered in app/Console/Kernel.php).
 * Queries the database for bookings requiring action, then dispatches
 * queue jobs for non-blocking execution.
 *
 * Responsibilities:
 * 1. Find confirmed bookings with upcoming cancellation deadlines (3/2/1 days)
 *    → Dispatch SendReminderJob per booking
 *
 * 2. Find confirmed bookings with past cancellation deadlines, not yet invoiced
 *    → Dispatch AutoInvoiceDeadlineJob per booking
 *
 * This command does NO heavy work itself — it is purely a dispatcher.
 * All side effects (WhatsApp sends, DB writes) happen in queue jobs.
 *
 * Usage:
 *   php artisan dotwai:process-deadlines
 *   php artisan dotwai:process-deadlines --batch-size=100
 *
 * @see LIFE-02 Reminder dispatch logic
 * @see LIFE-03 Auto-invoice dispatch logic
 * @see LIFE-05 Daily scheduler execution
 * @see SendReminderJob Queue job for WhatsApp reminders
 * @see AutoInvoiceDeadlineJob Queue job for deadline-pass invoicing
 */
class ProcessDeadlinesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dotwai:process-deadlines
                            {--batch-size=50 : Maximum bookings to dispatch per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process booking deadlines: send reminders at 3/2/1 days, auto-invoice when deadline passes';

    /**
     * Execute the console command.
     *
     * @param LifecycleService $service Injected by Laravel's service container
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function handle(LifecycleService $service): int
    {
        $this->info('Starting DOTW AI deadline processor...');

        // ── Phase 1: Dispatch reminder jobs ──────────────────────────────
        $reminderCount = 0;
        $bookingsDue   = $service->findBookingsDueForReminder();

        foreach ($bookingsDue as $booking) {
            SendReminderJob::dispatch($booking->id);
            $reminderCount++;
        }

        if ($reminderCount > 0) {
            $this->info("Dispatched {$reminderCount} reminder job(s).");
            Log::info('[DotwAI] Scheduler dispatched reminder jobs', [
                'count' => $reminderCount,
            ]);
        } else {
            $this->line('No bookings due for reminders.');
        }

        // ── Phase 2: Dispatch auto-invoice jobs ───────────────────────────
        $invoiceCount    = 0;
        $passedDeadlines = $service->findBookingsWithPassedDeadline();

        foreach ($passedDeadlines as $booking) {
            AutoInvoiceDeadlineJob::dispatch($booking->id);
            $invoiceCount++;
        }

        if ($invoiceCount > 0) {
            $this->info("Dispatched {$invoiceCount} auto-invoice job(s).");
            Log::info('[DotwAI] Scheduler dispatched auto-invoice jobs', [
                'count' => $invoiceCount,
            ]);
        } else {
            $this->line('No bookings with passed deadlines.');
        }

        $this->info('Deadline processor completed.');
        Log::info('[DotwAI] Deadline processor cycle complete', [
            'reminders_dispatched' => $reminderCount,
            'invoices_dispatched'  => $invoiceCount,
        ]);

        return self::SUCCESS;
    }
}
