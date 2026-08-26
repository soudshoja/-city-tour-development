<?php

namespace App\Providers;

use App\Events\CheckConfirmedOrIssuedTask;
use App\Listeners\ProcessTaskFinancials;
use App\Models\Client;
use App\Models\CoaCategory;
use App\Models\Company;
use App\Models\Item;
use App\Models\Task;
use App\Policies\CompanyPolicy;
use App\Policies\ClientPolicy;
use App\Policies\COAPolicy;
use App\Policies\ItemPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use App\Models\Role;
use App\Models\Branch;
use App\Models\Agent;
use App\Policies\AccountPolicy;
use App\Policies\SystemSettingPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Livewire components from App\Http\Livewire (Livewire 3 default is App\Livewire)
        Livewire::component('admin.dotw-admin-index', \App\Http\Livewire\Admin\DotwAdminIndex::class);
        Livewire::component('admin.dotw-dashboard-tab', \App\Http\Livewire\Admin\DotwDashboardTab::class);
        Livewire::component('admin.dotw-booking-lifecycle-tab', \App\Http\Livewire\Admin\DotwBookingLifecycleTab::class);
        Livewire::component('admin.dotw-error-tracker-tab', \App\Http\Livewire\Admin\DotwErrorTrackerTab::class);
        Livewire::component('admin.dotw-audit-log-index', \App\Http\Livewire\Admin\DotwAuditLogIndex::class);
        Livewire::component('admin.dotw-api-token-index', \App\Http\Livewire\Admin\DotwApiTokenIndex::class);

        \App\Services\AiConfigOverride::apply();

        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(CoaCategory::class, COAPolicy::class);

        Task::observe(\App\Observers\TaskObserver::class);
        Agent::observe(\App\Observers\AgentObserver::class);
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);
        \App\Models\InvoicePartial::observe(\App\Observers\InvoicePartialObserver::class);

        Gate::define('manage-system-settings', [SystemSettingPolicy::class, 'viewAny']);
        Gate::define('manage-email-tester', [SystemSettingPolicy::class, 'manageEmailTester']);

        /*
         * Resayil Admin Center (Settings -> WhatsApp), plan §9.2.
         *
         * ADMIN (1, the platform operator) and COMPANY (2, the agency
         * owner) only. BRANCH/AGENT/ACCOUNTANT/CLIENT get nothing in v1 —
         * agents consume WhatsApp through the drawer and are provisioned
         * automatically, so they have no reason to reach the workspace,
         * subscription, or the operator pause lever. Extending to
         * BRANCH (read-only Panel 1) or ACCOUNTANT (read-only Panel 4) is
         * open decision U-8 and is a one-line change to this array.
         *
         * Applied as `can:manage-resayil` on the whole route group, layered
         * UNDER `module:resayil` so an un-entitled company gets a 404
         * (invisible) before this gate can 403 (which would confirm the
         * section exists).
         */
        Gate::define('manage-resayil', fn (\App\Models\User $user) => in_array(
            (int) $user->role_id,
            [Role::ADMIN, Role::COMPANY],
            true
        ));

        /*
         * Abuse surface fix — `GET /settings/whatsapp/overview-data?refresh=1`
         * bypasses the panel's own 60 s cache on purpose (it is the explicit
         * Refresh-button gesture), but that means it was ALSO unthrottled:
         * every hit is one real call to the shared reseller API plus one
         * `resayil_accounts` UPDATE, and one client could hammer it fast
         * enough to affect every OTHER company on the platform. The routine
         * 60 s poll (no `refresh` param) costs nothing extra and is served
         * straight from cache, so it stays completely unthrottled here —
         * only a forced refresh is limited.
         *
         * 6/minute per user (about one every 10 s) is generous for a human
         * clicking Refresh — including someone watching a pause/resume take
         * effect and re-checking a few times in a row — while cutting the
         * demonstrated abuse (12 requests in 30 s, ~24/min) by 4x and
         * capping it hard regardless of how much faster an attacker pushes.
         * Applied to the `/overview-data` route only in routes/web.php.
         */
        RateLimiter::for('resayil-overview-refresh', function (Request $request) {
            if (! $request->boolean('refresh')) {
                return Limit::none();
            }

            $subject = optional($request->user())->id ?? $request->ip();

            return Limit::perMinute(6)->by('resayil-refresh:'.$subject);
        });

        // Register event listeners
        Event::listen(
            CheckConfirmedOrIssuedTask::class,
            ProcessTaskFinancials::class
        );

        View::composer('*', function ($view) {
            $user = Auth::user();

            if ($user) {
                $isAdmin = $user->role_id == Role::ADMIN;
                $companyId = getCompanyId($user);

                $view->with([
                    'currentCompanyId' => $companyId,
                    'globalIsAdmin' => $isAdmin,
                    'sidebarCompanies' => $isAdmin ? Company::orderBy('name')->get() : collect(),
                ]);
            }
        });
    }
}
