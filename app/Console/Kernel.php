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
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
