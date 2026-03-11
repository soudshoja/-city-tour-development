<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ResailAIAdminController;
use App\Http\Controllers\Api\ResailAISuppliersController;

/*
|--------------------------------------------------------------------------
| ResailAI Admin Routes
|--------------------------------------------------------------------------
|
| These routes are for admin-only API key management and feature flag
| configuration for the ResailAI module.
|
*/

// API Key Management Routes
Route::prefix('admin/resailai')->middleware(['auth:api'])->group(function () {
    // List all API keys
    Route::get('/api-keys', [ResailAIAdminController::class, 'index'])->name('admin.resailai.api-keys.index');

    // Generate new API key
    Route::post('/api-keys', [ResailAIAdminController::class, 'generate'])->name('admin.resailai.api-keys.generate');

    // Revoke API key
    Route::delete('/api-keys/{id}', [ResailAIAdminController::class, 'revoke'])->name('admin.resailai.api-keys.revoke');

    // Toggle auto_process_pdf flag for supplier
    Route::post('/suppliers/{supplierId}/toggle', [ResailAISuppliersController::class, 'toggle'])->name('admin.resailai.suppliers.toggle');
});

// Supplier List Route (accessible to any authenticated user)
Route::get('/admin/resailai/suppliers', [ResailAISuppliersController::class, 'index'])->name('admin.resailai.suppliers.index');
