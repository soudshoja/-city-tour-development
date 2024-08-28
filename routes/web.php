<?php

use App\Http\Controllers\Auth\OTPController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\Verify2FA;
use Illuminate\Support\Facades\Route;
// use Illuminate\Support\Facades\Auth;

// Auth::routes();

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::middleware(['auth', 'verified','2fa'])->group(function () {
    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    Route::post('twofa', function(){
        return redirect(route('dashboard'));
    })->name('test');
});

Route::middleware('auth')->group(function () {
    define('PROFILE_PATH', '/profile');
    
    Route::get(PROFILE_PATH, [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch(PROFILE_PATH, [ProfileController::class, 'update'])->name('profile.update');
    Route::delete(PROFILE_PATH, [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Route::middleware(['auth', 'throttle:60,1'])->group(function () {
//     Route::get('login/otp', [OTPController::class, 'show'])->name('login.otp');
//     Route::post('login/otp', [OTPController::class, 'check']);
// });


Route::get('pin', function(){
    return view('auth.pin');
})->name('pin');

require __DIR__.'/auth.php';
