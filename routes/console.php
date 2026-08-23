<?php

use App\Console\Commands\TboTask;
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
// app:process-files and mail:ingest-inbound are driven by DEDICATED CRONTAB
// LINES (flock-guarded) — do NOT also schedule them here. When schedule:run
// went live (2026-08-03) the duplicate scheduler instance raced the cron one
// over the same files_unprocessed folder and LOST 095547_00.AIR mid-rename
// (runInBackground children DO start under cron-invoked CLI; the old
// "exec disabled" note only held for web-context invocations).
Schedule::command('wa:dispatch-results')->everyMinute();
Schedule::command('ai:health-check')->everyTenMinutes();

// Nightly read-only books health check (Step 1, 2026-06-05)
// NOTE (dev-sync 2026-08-24): 'accounting:verify' has no implementing command
// class anywhere in the prod codebase either — this scheduled job is a dangling
// reference on prod itself (would log "command not defined" if schedule:run
// ever actually invokes it). Carried over verbatim for fidelity with prod; not
// fixed here since inventing the missing command is out of scope for this sync.
Schedule::command('accounting:verify')->dailyAt('06:00')->runInBackground();

// Poll IATA EasyPay wallet balances; WhatsApp opted-in agents on any change (2026-06-14)
Schedule::command('app:check-iata-wallet')->everyFifteenMinutes()->runInBackground()->withoutOverlapping();

// Ask attributed agents (WhatsApp) for the cost price of no-fare 0-price tickets (2026-06-23)
Schedule::command('price-requests:tick')->everyFiveMinutes()->withoutOverlapping()->runInBackground();

// MyFatoorah reconciler: driven by its dedicated flock-guarded crontab line
// (*/30) — intentionally NOT scheduled here (see the process-files note above).

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
