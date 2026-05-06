<?php

use Illuminate\Support\Facades\Route;

Route::prefix('api/akeed-dotwai')
    ->middleware(['akeed.dotw.context'])
    ->group(function () {
        // Endpoints added in Phase 31+
    });
