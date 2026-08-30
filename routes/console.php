<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('app:tbo-task')->everyMinute()->runInBackground();
Schedule::command('app:update-exchange-rate')->daily()->runInBackground();
Schedule::command('perform:payment-release-to-company-bankacc-process')
    ->cron('0 0 * * 0-4') // Sunday (0) to Thursday (4) at 12:00 AM
    ->runInBackground();
Schedule::command('app:process-files')->everyMinute()->runInBackground();

// P2.5.G (p2_5-brief.md §P2.5.G; reconciliation-design.md §9): nightly internal auto-
// reconciliation — "registered in routes/console.php, running daily; per-company timing via
// option reconciliation.auto_schedule (default: daily at 02:00 company timezone)". Company-
// timezone-per-run is a v1 refinement (external adapters, P5.10) — v0 runs once, daily, at a
// single fixed time for every company, since the internal-only detectors this build ships have
// no timezone-sensitive external feed to stagger.
Schedule::command('accounting:reconcile', ['--auto' => true])
    ->dailyAt('02:00')
    ->withoutOverlapping(120)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/accounting-reconcile-auto.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('accounting:reconcile --auto nightly run failed');
    });

// Akeed-DOTW static data sync (countries + cities) — every Sunday at 03:00 KWT
Schedule::command('dotwai:sync-static')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->timezone('Asia/Kuwait')
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/sync-static.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('dotwai:sync-static weekly run failed');
    });

// Akeed-DOTW hotel catalog sync (14 priority cities) — every Sunday at 03:30 KWT
// Guarded by config('akeed_dotwai.enabled') so disabled deployments skip it
Schedule::command('akeed-dotwai:sync-hotels', ['--all' => true])
    ->weekly()
    ->sundays()
    ->at('03:30')
    ->timezone('Asia/Kuwait')
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/sync-hotels.log'))
    ->when(fn () => config('akeed_dotwai.enabled'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('akeed-dotwai:sync-hotels weekly run failed');
    });

// Akeed-DOTW reference catalog sync (classifications, chains, locations, amenities, etc.)
// — every Sunday at 04:00 KWT. Runs after sync-static (03:00) and sync-hotels (03:30).
// Guarded by config('akeed_dotwai.enabled') so disabled deployments skip it.
Schedule::command('akeed-dotwai:sync-catalogs')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->timezone('Asia/Kuwait')
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/sync-catalogs.log'))
    ->when(fn () => config('akeed_dotwai.enabled'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('akeed-dotwai:sync-catalogs weekly run failed');
    });
