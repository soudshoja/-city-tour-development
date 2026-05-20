<?php

use App\Modules\AkeedDotwAI\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/akeed-dotwai')
    ->middleware(['akeed.dotw.context'])
    ->group(function () {
        Route::post('search-hotels', SearchController::class)
            ->name('akeed-dotwai.search-hotels');
    });
