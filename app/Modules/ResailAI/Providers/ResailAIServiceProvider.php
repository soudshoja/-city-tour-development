<?php

namespace App\Modules\ResailAI\Providers;

use Illuminate\Support\ServiceProvider;

class ResailAIServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register config file
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/resailai.php',
            'resailai'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register routes file
        $this->loadRoutesFrom(__DIR__ . '/../Routes/routes.php');

        // Register middleware (if needed for future use)
        // Middleware can be registered in app/Http/Kernel.php or bootstrap/app.php
        // if Laravel version requires it
    }
}
