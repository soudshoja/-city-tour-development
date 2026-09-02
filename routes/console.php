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
// Fix (verified against PaymentReleaseToCompanyBankAccProcess.php:55): this entry named
// a command that does not exist ('perform:...' -- grep of app/ finds zero hits), so it
// silently failed every scheduled run (Sunday-Thursday at midnight) since this line was
// written. The real signature is 'app:payment-release-to-company-bankacc-process'.
Schedule::command('app:payment-release-to-company-bankacc-process')
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

// Agent Profit Calculation module (AP): ProcessAgentCommission was only scheduled in the
// dead app/Console/Kernel.php (monthlyOn(1, '00:10')), which is never loaded -- monthly
// agent commissions have never auto-run. Cadence matched from that Kernel entry.
Schedule::command('app:calculate-agent-commission')
    ->monthlyOn(1, '00:10')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/agent-commission.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('app:calculate-agent-commission monthly run failed');
    });

// Agent Profit Calculation module (AP): ProcessExpiredConfirmedTasks (the confirmed ->
// auto-void trigger) was also only scheduled in the dead Kernel.php (everyFiveMinutes()),
// so confirmed tasks have never auto-voided. Cadence matched from that Kernel entry.
Schedule::command('tasks:process-expired-confirmed')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/expired-confirmed-tasks.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('tasks:process-expired-confirmed run failed');
    });

// P2.5.G (p2_5-brief.md §P2.5.G; reconciliation-design.md §9): nightly internal auto-
// reconciliation — "registered in routes/console.php, running daily; per-company timing via
// option reconciliation.auto_schedule (default: daily at 02:00 company timezone)". Company-
// timezone-per-run is a v1 refinement (external adapters, P5.10) — v0 runs once, daily, at a
// single fixed time for every company, since the internal-only detectors this build ships have
// no timezone-sensitive external feed to stagger.
Schedule::command('accounting:reconcile --auto')
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
Schedule::command('akeed-dotwai:sync-hotels --all')
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

// ============================================================================================
// P2.5.I "Reminder engine v2" (p2_5-brief.md §P2.5.I; doc 22 §16.7). CREATE-only generators
// (reminder:generate --kind=X) each scheduled at the brief's own stated cadence, all with
// withoutOverlapping()->onOneServer() per that section's own wording -- onOneServer() is safe
// here: config('cache.default') is 'database' (see config/cache.php), which backs Laravel's
// atomic-lock cache driver requirement. The SEND step (process:reminder --proceed) is the
// separate, unconditional everyMinute() entry below it, exactly as it already ran in prod
// (2026-08-29 audit: "process:reminder --proceed every minute") but now WITH
// withoutOverlapping() -- the audit's own flagged gap ("no flock/withoutOverlapping ... 1,074,151
// no-op runs recorded") is what this entry fixes.
//
// DEPLOY NOTE -- USER-GO ACTION, NEVER EXECUTED BY AN AGENT (doc 22 §16.7's own instruction:
// "crontab reminder lines moved to the scheduler ... deletions documented for a user-go deploy
// step, never executed by an agent"). Once this file is live on prod (i.e. `php artisan
// schedule:run` is the one cron entry driving everything, per this project's own
// "Scheduled Processing" convention in CLAUDE.md), an operator must remove these now-redundant
// DIRECT crontab lines by hand (documented here, not run by anyone/anything automatically; these
// are the lines the 2026-08-29 audit recorded, reproduced verbatim -- and CONFIRMED 2026-08-31 by
// reading the actual crontab on the shared cPanel box: they live under the `citycomm` account,
// i.e. /home/citycomm/tour.citycommerce.group -- the pre-fork upstream this repo was hard-forked
// from on 2026-05-10 -- NOT under this repo's own `akeed` account, which runs no direct reminder
// cron lines at all today):
//   * * * * *   cd /path/to/tour.citycommerce.group && php artisan process:reminder --proceed
//   0 7,19 * * *  cd /path/to/tour.citycommerce.group && php artisan reminder:unassigned-tasks
//   0 7,19 * * *  cd /path/to/tour.citycommerce.group && php artisan reminder:uninvoiced-tasks
//   0 9,16 * * *  cd /path/to/tour.citycommerce.group && php artisan reminder:uninvoiced-payment-links
//   0 * * * *     cd /path/to/tour.citycommerce.group && php artisan task-action-request:notify-stale
// ALL FIVE now have an equivalent Schedule:: entry in this file (the first three above; the last
// two below) so their direct cron line should be deleted at the SAME deploy that ships this repo
// to prod -- an operator action on the citycomm/tour.citycommerce.group box, never on akeed's own
// crontab (which never carried these lines to begin with).
// 'SendKwikt2843CreatorReminder' (the third prod-only command doc 22 §16.7 names) has NO crontab
// line of its own recorded in the audit and no equivalent needed here: this build's decision
// (source unavailable to inspect at the time; confirmed still true 2026-08-31 -- the class exists
// on tour.citycommerce.group but is genuinely Kwikt-specific and unscheduled there) is RETIRE, not
// generalise -- see the build report for why.
//
// task-action-request:notify-stale / reminder:uninvoiced-payment-links -- PORTED 2026-08-31
// (App\Console\Commands\NotifyStaleTaskActionRequests / SendAgentUninvoicedPaymentLinkReminders,
// verbatim from tour.citycommerce.group per the brief's "port ... as-is" instruction). Both are
// scheduled below at the SAME times the 2026-08-29 audit recorded for their direct crontab lines.
// NotifyStaleTaskActionRequests' own producer feature (the cross-agent refund/void/reissue
// owner-acknowledgment workflow that writes task_action_requests rows -- see that migration's own
// docblock) was NOT ported: it is a separate, much larger epic than P2.5.I's reminder-engine scope
// and nothing in this repo creates task_action_requests rows yet, so this command is a real,
// scheduled, additive no-op today (0 stale rows, always) rather than a fabricated stand-in.
// Skipping this command entirely was rejected: doc 22 §16.7 named it specifically, so the
// model/migration/notifier/command it depends on are ported; only the separate acknowledgment
// workflow that would populate task_action_requests is out of scope here and untracked.
Schedule::command('reminder:generate', ['--kind' => 'overdue_invoice'])
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reminder-generate-overdue-invoice.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('reminder:generate --kind=overdue_invoice run failed');
    });

Schedule::command('reminder:generate', ['--kind' => 'statement_balance'])
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reminder-generate-statement-balance.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('reminder:generate --kind=statement_balance run failed');
    });

Schedule::command('reminder:generate', ['--kind' => 'ticketing_deadline'])
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reminder-generate-ticketing-deadline.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('reminder:generate --kind=ticketing_deadline run failed');
    });

Schedule::command('reminder:generate', ['--kind' => 'payment_link_uninvoiced'])
    ->twiceDaily(9, 16)
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reminder-generate-payment-link-uninvoiced.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('reminder:generate --kind=payment_link_uninvoiced run failed');
    });

Schedule::command('process:reminder --proceed')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/process-reminder.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('process:reminder --proceed run failed');
    });

// P2.5.I prod-drift fold-in: reminder:unassigned-tasks / reminder:uninvoiced-tasks (both already
// in git -- App\Console\Commands\SendUnassignedTaskReminders / SendAgentUninvoicedTaskReminders)
// ran on prod ONLY via a direct crontab line (doc 22 §16.7's 2026-08-29 audit: "07:00/19:00"),
// with no Schedule:: entry in this file at all before this wave. Registered here at the same
// stated times so `php artisan schedule:run` alone now drives them -- see this block's own DEPLOY
// NOTE above for the matching direct-crontab-line removal this enables (user-go).
Schedule::command('reminder:unassigned-tasks')
    ->twiceDaily(7, 19)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reminder-unassigned-tasks.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('reminder:unassigned-tasks run failed');
    });

Schedule::command('reminder:uninvoiced-tasks')
    ->twiceDaily(7, 19)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reminder-uninvoiced-tasks.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('reminder:uninvoiced-tasks run failed');
    });

// P2.5.I prod-drift port (App\Console\Commands\SendAgentUninvoicedPaymentLinkReminders,
// ported verbatim 2026-08-31 -- see this block's own DEPLOY NOTE above). Same 09:00/16:00 times
// the 2026-08-29 audit recorded for its direct `reminder:uninvoiced-payment-links` crontab line.
Schedule::command('reminder:uninvoiced-payment-links')
    ->twiceDaily(9, 16)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/reminder-uninvoiced-payment-links.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('reminder:uninvoiced-payment-links run failed');
    });

// P2.5.I prod-drift port (App\Console\Commands\NotifyStaleTaskActionRequests, ported verbatim
// 2026-08-31 -- see this block's own DEPLOY NOTE above for why its producer workflow was not
// ported: this command runs hourly exactly as prod did but finds 0 stale rows until that separate
// workflow exists). Same hourly cadence the 2026-08-29 audit recorded for its direct
// `task-action-request:notify-stale` crontab line.
Schedule::command('task-action-request:notify-stale')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/task-action-request-notify-stale.log'))
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('task-action-request:notify-stale run failed');
    });
