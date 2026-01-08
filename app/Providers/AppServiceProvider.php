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
    public function register(): void
    {
       
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

    }
    
}