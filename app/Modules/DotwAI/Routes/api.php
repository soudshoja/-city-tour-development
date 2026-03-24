<?php

declare(strict_types=1);

use App\Modules\DotwAI\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DotwAI Module API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/dotwai/.
| The 'dotwai.resolve' middleware resolves phone numbers to company context.
|
| Phase 18 endpoints:
| - GET  /api/dotwai/health          — Health check (no middleware)
| - POST /api/dotwai/search_hotels   — Search hotels by city/name/filters
| - POST /api/dotwai/get_hotel_details — Get room details for a hotel
| - GET  /api/dotwai/get_cities      — Get city list for a country
|
*/

// Health check -- no authentication or middleware required
Route::get('api/dotwai/health', function () {
    return response()->json([
        'status' => 'ok',
        'module' => 'dotwai',
        'version' => '1.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// All DotwAI endpoints require phone resolution
Route::prefix('api/dotwai')->middleware(['dotwai.resolve'])->group(function () {
    // Search endpoints (Phase 18)
    Route::post('search_hotels', [SearchController::class, 'searchHotels']);
    Route::post('get_hotel_details', [SearchController::class, 'getHotelDetails']);
    Route::get('get_cities', [SearchController::class, 'getCities']);
});
