<?php

use App\Http\Controllers\Auth\TwoFAController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController; // Add this line if you create a SearchController
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\TaskController;

// Home route
Route::get('/', function () {
    return view('welcome');
})->name('welcome');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [ItemController::class, 'index'])->name('dashboard');

    Route::post('verify2fa', function () {
        return redirect()->route('dashboard');
    })->name('verify2fa');
});

// Routes requiring authentication
Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('pin', function () {
        return view('auth.pin');
    })->name('pin');

    Route::get('set-up-authenticator', [TwoFAController::class, 'twofa'])->name('2fa');

    // Add a route for search functionality
    Route::get('/search', [SearchController::class, 'search'])->name('search'); // Assuming you will create this controller
});

Route::get('enable2fa', [TwoFAController::class, 'twofaEnable'])->name('enable2fa');
// Agents list
Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
Route::get('/agentsnew', [AgentController::class, 'new'])->name('agentsnew.new');
// Route to handle form submission and store the new agent
Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
Route::get('/agentsupload', [AgentController::class, 'upload'])->name('agentsupload.upload');
Route::post('/agentsupload', [AgentController::class, 'import'])->name('agentsupload.import');
// Route::post('/agentsupload', [AgentController::class, 'upload'])->name('agents.upload');
// Include routes for authentication
Route::get('/agents/{id}', [AgentController::class, 'show'])->name('agentsshow.show');
Route::get('/agents/{id}/edit', [AgentController::class, 'edit'])->name('agents.edit');
Route::put('/agents/{id}', [AgentController::class, 'update'])->name('agents.update');
Route::post('/create-agent-profile', [AgentController::class, 'createAgentProfile'])->name('create.agent.profile');


Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
Route::get('/companiesnew', [CompanyController::class, 'new'])->name('companiesnew.new');
// Route to handle form submission and store the new agent
Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
Route::get('/companiesupload', [CompanyController::class, 'upload'])->name('companiesupload.upload');
Route::post('/companiesupload', [CompanyController::class, 'import'])->name('companiesupload.import');
// Route::post('/agentsupload', [AgentController::class, 'upload'])->name('agents.upload');
// Include routes for authentication
Route::get('/companies/{id}', [CompanyController::class, 'show'])->name('companiesshow.show');
Route::get('/companies/{id}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
Route::put('/companies/{id}', [CompanyController::class, 'update'])->name('companies.update');
Route::post('/company/{company}/toggle-status', [CompanyController::class, 'toggleStatus']);

Route::get('/tasks/{id}', [TaskController::class, 'index'])->name('tasks.index');
Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
Route::get('/tasksupload', [TaskController::class, 'upload'])->name('tasksupload.upload');
Route::post('/tasksupload', [TaskController::class, 'import'])->name('tasksupload.import');
// Route::middleware(['auth', 'throttle:60,1'])->group(function () {
//     Route::get('login/otp', [OTPController::class, 'show'])->name('login.otp');
//     Route::post('login/otp', [OTPController::class, 'check']);
// });


Route::get('pin', function () {
    return view('auth.pin');
})->name('pin');

// ITEMS
Route::get('/items', [ItemController::class, 'index'])->name('items.index');
Route::post('/items', [ItemController::class, 'store'])->name('items.store');
Route::get('/items/{id}', [ItemController::class, 'show'])->name('items.show');

// TASKS
Route::group([
    'prefix' => 'agent',
    'as' => 'agent.',
], function () {
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
});

// INVOICE
Route::get('/invoice', [InvoiceController::class, 'index'])->name('invoice.index');
Route::get('/invoice/create', [InvoiceController::class, 'create'])->name('invoice.create');
Route::post('/invoice', [InvoiceController::class, 'store'])->name('invoice.store');
Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.updateStatus');
Route::get('/invoice/{invoiceNumber}', [InvoiceController::class, 'show'])->name('invoice.show');


// PAYMENT
Route::post('/payment/process/{invoiceNumber}', [PaymentController::class, 'processPayment'])->name('payment.process');

// Create a client form
Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');

// Store a new client
Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');

// List clients
Route::get('/clients/list', [ClientController::class, 'list'])->name('clients.list');

// Show client details (dynamic route)
Route::get('clients/{id}', [ClientController::class, 'show'])->name('clients.show');

// Edit client (dynamic route)
Route::get('clients/{id}/edit', [ClientController::class, 'edit'])->name('clients.edit');

// Update client (dynamic route)
Route::put('clients/{id}', [ClientController::class, 'update'])->name('clients.update');







require __DIR__ . '/auth.php';