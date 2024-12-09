<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\MobileController;
use App\Http\Controllers\Auth\TwoFAController;

        Route::post('/login2', [MobileController::class, 'login2']);
        Route::post('/verifytwofa', [MobileController::class, 'verifytwofa']);
        // Agents
        Route::get('/agents', [MobileController::class, 'agent']);
        Route::get('/agents/{userId}', [MobileController::class, 'getAgentByUserId']);


        Route::get('/companies', [MobileController::class, 'company']);
        Route::get('/companies/{id}', [CompanyController::class, 'show'])->name('companiesshow.show');

        Route::get('/tasks/{agentId}', [MobileController::class, 'getTasksByAgentId']);
        Route::get('/tasks', [MobileController::class, 'task']);


        Route::post('/invoice', [MobileController::class, 'store']);
        Route::get('/invoice/{agentId}', [MobileController::class, 'getInvoiceByAgentId']);
        Route::get('/transaction/{agentId}', [MobileController::class, 'getTransactionByAgentId']);


        Route::get('/clients', [MobileController::class, 'client']);  
        Route::get('/clients/{agentId}', [MobileController::class, 'getClientByAgentId']);

        Route::get('pin', function(){
            return view('auth.pin');
        })->name('pin');

require __DIR__.'/auth.php';

