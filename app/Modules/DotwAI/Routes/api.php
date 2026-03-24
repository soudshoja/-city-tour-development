<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DotwAI Module API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/dotwai/.
| The 'dotwai.resolve' middleware resolves phone numbers to company context.
|
| Search and booking endpoints are added in Plan 02.
|
*/

// Health check -- no authentication or middleware required
Route::get('api/dotwai/health', function () {
    return response()->json([
        'status' => 'ok',
        'module' => 'dotwai',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Protected routes -- phone resolution middleware
Route::prefix('api/dotwai')->middleware(['dotwai.resolve'])->group(function () {
    // Endpoints added in Plan 02:
    // - POST /search (search_hotels)
    // - POST /hotel-details (get_hotel_details)
    // - GET  /cities (get_cities)
});
