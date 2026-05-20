<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * AkeedDotwAI Module Service Provider.
 *
 * Bootstraps the AkeedDotwAI B2C hotel booking module. The module is
 * entirely inert when `config('akeed_dotwai.enabled')` is false — no routes,
 * no migrations, no middleware aliases are registered.
 *
 * The companion B2B module (app/Modules/DotwAI/) is frozen and independent;
 * this provider does not touch it in any way.
 *
 * Registered in bootstrap/providers.php.
 */
class AkeedDotwAIServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Config is placed in config/akeed_dotwai.php (project root) following
     * the existing app convention (see config/dotw.php). No mergeConfigFrom
     * is needed — Laravel auto-loads files from the config/ directory.
     * Module-specific bindings will be added in Phase 31+.
     */
    public function register(): void
    {
        // Config is at config/akeed_dotwai.php and loaded by Laravel automatically.
        // Phase 31+ will register concrete service bindings here.
    }

    /**
     * Bootstrap any application services.
     *
     * All registration is gated on the enabled flag so the module produces
     * zero side-effects when disabled (default in .env baseline).
     */
    public function boot(): void
    {
        if (! config('akeed_dotwai.enabled', false)) {
            return;
        }

        /** @var Router $router */
        $router = $this->app->make(Router::class);

        // Load migrations — only when module is enabled so the tables are
        // not present on deploys that have not opted in.
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        // Load routes if the files exist. Both api.php and graphql.php are
        // created in T30-06; guard with file_exists so this provider is
        // fully standalone in T30-01.
        $apiRoutes = __DIR__.'/Routes/api.php';
        if (file_exists($apiRoutes)) {
            $this->loadRoutesFrom($apiRoutes);
        }

        $graphqlRoutes = __DIR__.'/Routes/graphql.php';
        if (file_exists($graphqlRoutes)) {
            $this->loadRoutesFrom($graphqlRoutes);
        }

        // Register middleware alias only when the class exists.
        // AttachDotwContext is created in T30-06; this guard keeps T30-01
        // self-contained and prevents a class-not-found crash at boot.
        $middlewareClass = 'App\\Modules\\AkeedDotwAI\\Http\\Middleware\\AttachDotwContext';
        if (class_exists($middlewareClass)) {
            $router->aliasMiddleware('akeed.dotw.context', $middlewareClass);
        }

        // Register Artisan commands for this module.
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Modules\AkeedDotwAI\Console\Commands\SyncHotelsCommand::class,
                \App\Modules\AkeedDotwAI\Console\Commands\SyncCatalogsCommand::class,
                \App\Modules\AkeedDotwAI\Console\Commands\BackfillStarRatingsCommand::class,   // T35.7
            ]);
        }
    }
}
