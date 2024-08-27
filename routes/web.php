<?php

use App\Http\Controllers\Auth\OTPController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\Verify2FA;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified',Verify2FA::class])->name('dashboard');

Route::middleware('auth')->group(function () {
    define('PROFILE_PATH', '/profile');
    
    Route::get(PROFILE_PATH, [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch(PROFILE_PATH, [ProfileController::class, 'update'])->name('profile.update');
    Route::delete(PROFILE_PATH, [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::get('login/otp', [OTPController::class, 'show'])->name('login.otp');
    Route::post('login/otp', [OTPController::class, 'check']);
});

require __DIR__.'/auth.php';
