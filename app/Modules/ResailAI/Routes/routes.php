<?php

use Illuminate\Support\Facades\Route;
use App\Modules\ResailAI\Http\Controllers\CallbackController;
use App\Http\Controllers\Api\ResailAIAdminController;

/*
|--------------------------------------------------------------------------
| ResailAI Module Routes
|--------------------------------------------------------------------------
|
| Routes for the ResailAI module including webhook callbacks and admin API.
|
*/

// Webhook callback route (for ResailAI n8n processing results)
Route::post('/modules/resailai/callback', [CallbackController::class, 'handle'])
    ->middleware('verify.resailai.token')
    ->name('modules.resailai.callback');

// Admin API routes for API key management
Route::prefix('admin/resailai')->middleware(['auth:api'])->group(function () {
    Route::get('/api-keys', [ResailAIAdminController::class, 'index'])->name('admin.resailai.api-keys.index');
    Route::post('/api-keys/generate', [ResailAIAdminController::class, 'generate'])->name('admin.resailai.api-keys.generate');
    Route::delete('/api-keys/{id}', [ResailAIAdminController::class, 'revoke'])->name('admin.resailai.api-keys.revoke');
});
