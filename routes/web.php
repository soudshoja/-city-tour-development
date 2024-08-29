<?php

use App\Http\Controllers\Auth\TwoFAController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::middleware(['auth', 'verified','check2fa', '2fa'])->group(function () {
    Route::get('dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::post('verify2fa', function(){
        return redirect()->route('dashboard');
    })->name('verify2fa');

});

Route::middleware('auth')->group(function () {
    
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('pin', function(){
        return view('auth.pin');
    })->name('pin');
    
    Route::get('set-up-authenticator',[TwoFAController::class, 'twofa'])->name('2fa');

});

Route::get('enable2fa',[TwoFAController::class, 'twofaEnable'])->name('enable2fa');

// Route::middleware(['auth', 'throttle:60,1'])->group(function () {
//     Route::get('login/otp', [OTPController::class, 'show'])->name('login.otp');
//     Route::post('login/otp', [OTPController::class, 'check']);
// });


Route::get('pin', function(){
    return view('auth.pin');
})->name('pin');

require __DIR__.'/auth.php';
