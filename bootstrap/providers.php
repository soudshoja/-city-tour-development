<?php

return [
    App\Providers\AIServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    // App\Providers\AuthServiceProvider::class,
    App\Providers\AccountingServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
    App\Modules\DotwAI\Providers\DotwAIServiceProvider::class,
    App\Modules\AkeedDotwAI\AkeedDotwAIServiceProvider::class,
];
