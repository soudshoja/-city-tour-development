<?php

namespace App\Providers;

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
use App\Policies\ReceiptVoucherPolicy;
use App\Models\InvoiceReceipt;
use App\Policies\BankPaymentPolicy;
use App\Models\BankPayment;

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
        // P2.5.F (p2_5-brief.md §P2.5.F): the Accounting Log Center.
        Livewire::component('accounting.audit-log-index', \App\Http\Livewire\Accounting\AuditLogIndex::class);

        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(CoaCategory::class, COAPolicy::class);
        // W5.R / W5.X: no model is literally named "ReceiptVoucher" (the RV document is the
        // pre-existing InvoiceReceipt row -- w5-state.md §1 "Dedicated tables: None"), so Laravel's
        // convention-based policy discovery (App\Policies\{Model}Policy) cannot find
        // ReceiptVoucherPolicy on its own; registered explicitly, same as every policy on this list.
        Gate::policy(InvoiceReceipt::class, ReceiptVoucherPolicy::class);
        // W5.P / W5.X: registered explicitly for consistency with ReceiptVoucherPolicy above, even
        // though Laravel's convention-based discovery would find this one on its own (BankPayment
        // is literally named "BankPayment") -- see BankPaymentPolicy's own docblock.
        Gate::policy(BankPayment::class, BankPaymentPolicy::class);

        Gate::define('manage-system-settings', [SystemSettingPolicy::class, 'viewAny']);
        Gate::define('manage-email-tester', [SystemSettingPolicy::class, 'manageEmailTester']);

        // W6.S item (5) (ct-void-map.md §7 bug 9): the CheckConfirmedOrIssuedTask ->
        // ProcessTaskFinancials event/listener pair used to be registered here, but
        // TaskController::triggerCheckTaskEvent() -- the ONLY place that ever fired this event --
        // had zero call sites anywhere in the codebase (grep-confirmed before deleting). Dead code
        // with no emitter is not "wired through the service", it is unreachable; deleted rather
        // than kept registered for an event nothing ever dispatches. See
        // App\Services\TaskStatusService::dispatchFinancial() for the single live call site every
        // real caller now uses instead.

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
