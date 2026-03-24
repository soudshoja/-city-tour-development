<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Providers;

use App\Modules\DotwAI\Commands\ImportHotelsCommand;
use App\Modules\DotwAI\Commands\ProcessDeadlinesCommand;
use App\Modules\DotwAI\Commands\SyncStaticDataCommand;
use Illuminate\Support\ServiceProvider;

/**
 * DotwAI Module Service Provider.
 *
 * Bootstraps the DotwAI module: registers config, routes, migrations,
 * and artisan commands. This is the single entry point that wires the
 * self-contained module into the Laravel application.
 *
 * Registered in bootstrap/providers.php.
 */
class DotwAIServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Merges the module config into the application config under the 'dotwai' key.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/dotwai.php',
            'dotwai'
        );
    }

    /**
     * Bootstrap any application services.
     *
     * Loads routes, migrations, and registers artisan commands.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportHotelsCommand::class,
                SyncStaticDataCommand::class,
                ProcessDeadlinesCommand::class,
            ]);
        }
    }
}
