<?php

declare(strict_types=1);

use App\Modules\DotwAI\Http\Controllers\BookingController;
use App\Modules\DotwAI\Http\Controllers\PaymentCallbackController;
use App\Modules\DotwAI\Http\Controllers\SearchController;
use App\Modules\DotwAI\Http\Controllers\StatementController;
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

// Payment callback from MyFatoorah -- no dotwai.resolve middleware (comes from gateway, not WhatsApp)
Route::any('api/dotwai/payment_callback', [PaymentCallbackController::class, 'handleCallback']);

// All DotwAI endpoints require phone resolution
Route::prefix('api/dotwai')->middleware(['dotwai.resolve'])->group(function () {
    // Search endpoints (Phase 18)
    Route::post('search_hotels', [SearchController::class, 'searchHotels']);
    Route::post('get_hotel_details', [SearchController::class, 'getHotelDetails']);
    Route::get('get_cities', [SearchController::class, 'getCities']);

    // Booking endpoints (Phase 19)
    Route::post('prebook_hotel', [BookingController::class, 'prebookHotel']);
    Route::post('confirm_booking', [BookingController::class, 'confirmBooking']);
    Route::get('balance', [BookingController::class, 'getCompanyBalance']);

    // Payment link endpoint (Phase 19-02)
    Route::post('payment_link', [BookingController::class, 'paymentLink']);

    // Cancellation endpoint (Phase 20)
    Route::post('cancel_booking', [BookingController::class, 'cancelBooking']);

    // Statement endpoint (Phase 20)
    Route::get('statement', [StatementController::class, 'getStatement']);

    // Booking status, history, and voucher resend endpoints (Phase 21)
    Route::get('booking_status', [BookingController::class, 'bookingStatus']);
    Route::get('booking_history', [BookingController::class, 'bookingHistory']);
    Route::post('resend_voucher', [BookingController::class, 'resendVoucher']);
});
