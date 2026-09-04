<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run the email processing command every 10 minutes
        $schedule->command('emails:process')->everyTenMinutes();
        $schedule->command('app:payment-release-to-company-bankacc-process')->dailyAt('0:00');
        $schedule->command('app:sync-myfatoorah-methods')->twiceDaily(0, 12);
        $schedule->command('app:delete-expired-offers')->everyFifteenMinutes();
        $schedule->command('app:update-hotel-status')->everyFifteenMinutes();
        $schedule->command('app:calculate-agent-commission')->monthlyOn(1, '00:10');
        $schedule->command('autobill:run')->everyMinute();

        // Once-an-hour reminder check for TaskActionRequests pending >2 days.
        // Each request is escalated at most once (escalated_at column).
        $schedule->command('task-action-request:notify-stale')
            ->hourly()
            ->withoutOverlapping();

        // Twice-daily reminder for paid payment links not yet invoiced (12:00 + 19:00 KW).
        $schedule->command('reminder:uninvoiced-payment-links')
            ->twiceDailyAt(12, 19, 0)
            ->timezone('Asia/Kuwait')
            ->withoutOverlapping();

        // KWIKT2843-creator reminder fires synchronously inside the Task::created
        // observer (TaskObserver::created → SendKwikt2843CreatorReminderJob::handle).
        // The artisan command 'reminders:kwikt2843-creator' is kept for manual
        // catch-up sweeps but not scheduled. The monthly summary command
        // 'reminders:kwikt2843-monthly-report' is also kept but not scheduled —
        // invoke manually if a per-agent month count is wanted.

        // Process expired confirmed tasks every 5 minutes
        $schedule->command('tasks:process-expired-confirmed')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Countries - weekly full sync
        $schedule->command('mapping:sync countries --full')
            ->weekly()
            ->sundays()
            ->at('01:00')
            ->withoutOverlapping();

        // Cities - weekly incremental sync
        $schedule->command('mapping:sync cities')
            ->weekly()
            ->mondays()
            ->at('01:00')
            ->withoutOverlapping();

        // Hotels - daily incremental sync
        $schedule->command('mapping:sync hotels')
            ->dailyAt('02:00')
            ->withoutOverlapping();

        // Process DOTW AI booking deadlines daily at 3 AM
        // Dispatches SendReminderJob (3/2/1 day reminders) and AutoInvoiceDeadlineJob (past deadlines)
        $schedule->command('dotwai:process-deadlines')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Clean stale DotwAI agent sessions daily at 3 AM
        $schedule->command('dotwai:clean-sessions')
            ->dailyAt('03:00');

        // Make sure to run the queue worker
        $schedule->command('queue:work --queue=api_sync --stop-when-empty')
            ->everyMinute()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
