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
        
        // Register event listeners
        Event::listen(
            CheckConfirmedOrIssuedTask::class,
            ProcessTaskFinancials::class
        );
        View::composer(['components.application-logo', 'layouts.navigation'], function ($view) {
        $companyLogo = asset('images/UserPic.svg'); // Default logo
        $user = auth()->user();

        if ($user) {
            $company = null;
            switch ($user->role->id) {
                case Role::COMPANY:
                    $company = $user->company;
                    break;
                case Role::BRANCH:
                    $company = $user->branch->company;
                    break;
                case Role::AGENT:
                    $company = $user->agent->branch->company;
                    break;
            }

            if ($company && $company->logo) {
                $companyLogo = asset('storage/' . $company->logo);
            }
        }
        $view->with('companyLogo', $companyLogo);
    });
    }
    
}