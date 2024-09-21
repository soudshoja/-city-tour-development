<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\MobileController;

// Agents list
Route::get('/agents', [MobileController::class, 'agent']);
Route::get('/agents/{id}', [AgentsController::class, 'show'])->name('agentsshow.show');
Route::get('/agents/{id}/edit', [AgentsController::class, 'edit'])->name('agents.edit');
Route::put('/agents/{id}', [AgentsController::class, 'update'])->name('agents.update');


Route::get('/companies', [MobileController::class, 'company']);
Route::get('/companies/{id}', [CompanyController::class, 'show'])->name('companiesshow.show');
Route::get('/companies/{id}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
Route::put('/companies/{id}', [CompanyController::class, 'update'])->name('companies.update');


Route::get('/tasks/{id}', [TaskController::class, 'index'])->name('tasks.index');
Route::get('/tasks', [MobileController::class, 'task']);

Route::get('/clients', [MobileController::class, 'client']);

Route::get('pin', function(){
    return view('auth.pin');
})->name('pin');

require __DIR__.'/auth.php';

