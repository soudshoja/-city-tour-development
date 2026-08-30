<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Accounting\GatewayRefundStatusChanged;
use App\Listeners\Accounting\HandleGatewayRefundStatusChanged;
use App\Models\Account;
use App\Observers\AccountObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the P1 accounting-engine plumbing. Pure wiring — no business logic lives here.
 *
 * The AccountObserver this registers is itself flag-gated on its OWN independent config key,
 * config('accounting.account_observer.enabled') (default false — see AccountObserver's own
 * docblock for the round-4 fix that decoupled it from config('accounting.engine.enabled'), and
 * config/accounting.php's 'account_observer' block for the operational rule). It is a no-op for
 * every company until that flag is turned on, so registering this provider does not change any
 * existing behavior today (mission scope: pure addition, flag-OFF, wired to nothing) — and,
 * post round-4, remains a no-op even after 'accounting.engine.enabled' is turned on for P2's
 * first feeder, since the two flags are now independent.
 *
 * W4.R fix (verify finding #3): this codebase has no `EventServiceProvider`/`withEvents()`
 * auto-discovery (confirmed by grep — `AppServiceProvider::boot()` used to be the only other
 * place any `Event::listen()` call existed, for the unrelated `CheckConfirmedOrIssuedTask` ->
 * `ProcessTaskFinancials` pair; W6.S deleted that pair as dead code with zero emitters, see
 * App\Services\TaskStatusService::dispatchFinancial() for its live replacement — this provider's
 * own registration below is unaffected), so `GatewayRefundStatusChanged` ->
 * `HandleGatewayRefundStatusChanged` must be registered explicitly, following that same
 * convention, in this accounting-specific provider rather than the generic `AppServiceProvider`.
 */
class AccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Account::observe(AccountObserver::class);

        Event::listen(
            GatewayRefundStatusChanged::class,
            HandleGatewayRefundStatusChanged::class
        );
    }
}
