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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(CoaCategory::class, COAPolicy::class);

        Gate::define('manage-system-settings', [SystemSettingPolicy::class, 'viewAny']);
        Gate::define('manage-email-tester', [SystemSettingPolicy::class, 'manageEmailTester']);

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
