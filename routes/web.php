<?php

use App\Http\Controllers\Auth\TwoFAController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController; // Add this line if you create a SearchController
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\AdminUsersController;
use App\Http\Controllers\ChargeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CoaController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\ToDoListController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\OpenAiController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\ChatController;
use App\Livewire\Notification;
use App\Livewire\NotificationIndex;
use App\Models\Role;
use App\Models\Task;
use App\Models\Charge;



// Home route
// Route::get('/', function () {
//     return view('dashboard');
// })->name('welcome');

Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/adminsList', [AdminUsersController::class, 'index'])->name('admin.users.index');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('pin', function () {
        return view('auth.pin');
    })->name('pin');

    Route::post('verify2fa', function () {
        return redirect()->route('dashboard');
    })->name('verify2fa');




    Route::get('set-up-authenticator', [TwoFAController::class, 'twofa'])->name('2fa');

    // Add a route for search functionality
    Route::get('/search', [SearchController::class, 'search'])->name('search'); // Assuming you will create this controller

    Route::get('enable2fa', [TwoFAController::class, 'twofaEnable'])->name('enable2fa');


    // Display a list of companies
    Route::get('/companies', [AdminUsersController::class, 'ShowCompanies'])->name('companies.index');

    // Show the form to create a new company
    Route::get('/companies/new', [AdminUsersController::class, 'newCompany'])->name('companiesnew.new');

    // Store a new company
    Route::post('/companies', [AdminUsersController::class, 'store'])->name('companies.store');



    // Agents list
    Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
    Route::get('/agentsnew', [AgentController::class, 'new'])->name('agentsnew.new');
    Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
    Route::get('/agentsupload', [AgentController::class, 'upload'])->name('agentsupload.upload');
    Route::post('/agentsupload', [AgentController::class, 'import'])->name('agentsupload.import');
    Route::get('/agents/{id}', [AgentController::class, 'show'])->name('agentsshow.show');
    Route::get('/agents/{id}/edit', [AgentController::class, 'edit'])->name('agents.edit');
    Route::put('/agents/{id}', [AgentController::class, 'update'])->name('agents.update');
    Route::post('/create-agent-profile', [AgentController::class, 'createAgentProfile'])->name('create.agent.profile');
    Route::get('/agents/{id}/tasks', [AgentController::class, 'getTasks']);
    Route::get('/agents/{id}/clients', [AgentController::class, 'getClients']);
    Route::get('/agents/{id}/invoices', [AgentController::class, 'getInvoices']);



    // Routes for creating new records
    Route::get('/companies/create', [CompanyController::class, 'showCreateOptions'])->name('companies.showCreateOptions');
    Route::post('/companies/create-branch', [CompanyController::class, 'createBranch'])->name('companies.createBranch');
    Route::post('/companies/create-agent', [CompanyController::class, 'createAgent'])->name('companies.createAgent');
    Route::post('/companies/create-accountant', [CompanyController::class, 'createAccountant'])->name('companies.createAccountant');
    Route::post('/companies/create-client', [CompanyController::class, 'createClient'])->name('companies.createClient');

    Route::get('/agentsettings', [CompanyController::class, 'showAgentTypeForm'])->name('agentsetting');
    Route::post('/agent-types', [CompanyController::class, 'createAgentType'])->name('agent-types.create');

    // Route to show the delete form (GET request)
    Route::get('/agent-types/delete', [CompanyController::class, 'showDeleteAgentTypeForm'])->name('agent-types.delete.form');

    // Route to handle the delete request (DELETE request)
    Route::delete('/agent-types/delete', [CompanyController::class, 'deleteAgentType'])->name('agent-types.delete');

    Route::get('/companiesupload', [CompanyController::class, 'upload'])->name('companiesupload.upload');
    Route::post('/companiesupload', [CompanyController::class, 'import'])->name('companiesupload.import');
    Route::get('/companies/{id}', [CompanyController::class, 'show'])->name('companiesshow.show');
    Route::get('/companies/{id}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::put('/companies/{id}', [CompanyController::class, 'update'])->name('companies.update');
    Route::post('/company/{company}/toggle-status', [CompanyController::class, 'toggleStatus']);


    // task routes
    Route::get('/task/{id}', [TaskController::class, 'show'])->name('task.show');
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks-voucher', [TaskController::class, 'voucher'])->name('tasks.voucher');
    Route::put('/tasks-update/{id}', [TaskController::class, 'update'])->name('tasks.update');
    Route::get('/tasks/{id}', [TaskController::class, 'index'])->name('tasks.agent.index');
    Route::get('/tasksupload', [TaskController::class, 'upload'])->name('tasksupload.upload');
    Route::post('/tasksupload', [TaskController::class, 'import'])->name('tasksupload.import');
    Route::get('/tasks/agents/{agentId}', [TaskController::class, 'getAgentTask'])->name('tasks.agent');

    // verdors routes
    Route::get('/supplierslist', [SupplierController::class, 'index'])->name('supplierslist.index');


    //ROLE
    Route::get('/role', [RoleController::class, 'index'])->name('role.index');
    Route::get('/create-role', [RoleController::class, 'create'])->name('role.create');
    Route::post('/role', [RoleController::class, 'store'])->name('role.store');
    Route::get('/edit-role/{role}', [RoleController::class, 'edit'])->name('role.edit');
    Route::put('/role/{role}', [RoleController::class, 'update'])->name('role.update');
    Route::get('/permission/{role}', [RoleController::class, 'permission'])->name('role.permission');


    // Account
    Route::get('/coa', action: [CoaController::class, 'index'])->name('coa.index');
    Route::post('/coa/create', [CoaController::class, 'createAccounts'])->name('coa.create');
    Route::delete('/api/coa/{id}', [CoaController::class, 'dstry'])->name('coa.destroy');
    Route::post('/updateCode/{id}', [CoaController::class, 'updateCode']);
    Route::get('/coa/payment-voucher', [CoaController::class, 'payment'])->name('coa.payment');

    Route::get('/get-level1-accounts', [CoaController::class, 'getLevel1Accounts']);
    Route::get('/get-level2-accounts/{level1Id}', [CoaController::class, 'getLevel2Accounts']);
    Route::get('/get-level3-accounts/{level2Id}', [CoaController::class, 'getLevel3Accounts']);
    Route::get('/get-level4-accounts/{level3Id}', [CoaController::class, 'getLevel4Accounts']);
    Route::get('/get-account', [CoaController::class, 'getTransactionsByLevel4']);
    Route::post('/submit-voucher', [CoaController::class, 'submitVoucher']);
    Route::get('/coa/transactions', [CoaController::class, 'transaction'])->name('coa.transaction');

    //    / Route::get('/accounting-summary', [AccountingController::class, 'index'])->name('accounting.index');
    Route::get('/accounting-summary', [AccountingController::class, 'showCompanySummary'])->name('accounting.index');
    Route::get('/transaction', [AccountingController::class, 'index'])->name('accounting.transaction');
    Route::post('/filter-ledgers', [AccountingController::class, 'filterLedgers']);
    Route::post('/export-excel', [AccountingController::class, 'exportExcel']);

    // Branches routes
    Route::group([
        'as' => 'branches.',
    ], function () {

        Route::get('/branches', [BranchController::class, 'index'])->name('index');
        Route::post('/branches', [BranchController::class, 'store'])->name('store');
        Route::get('/branches/create', [BranchController::class, 'create'])->name('create');
    });

    Route::get('/branches/{id}', [BranchController::class, 'show']);



    // whatsapp
    Route::post('/whatsapp/send', [WhatsappController::class, 'sendMessage'])->name('whatsapp.send');
    Route::get('/invoice/send/{invoiceNumber}', [InvoiceController::class, 'sendInvoice']);

    // open api
    Route::get('/open-ai', [OpenAiController::class, 'index'])->name('open-ai.index');
    Route::post('/open-ai', [OpenAiController::class, 'store'])->name('open-ai.store');
    Route::get('/fine-tuning', [OpenAiController::class, 'fineTuningView'])->name('fine-tuning');
    Route::get('/testclient',[OpenAiController::class, 'getClient']);
    Route::get('/openai/steps', [OpenAiController::class, 'steps'])->name('steps');
    Route::get('/openai/function-tools', [OpenAiController::class, 'addFunctionTool'])->name('function-tools');

    Route::post('/chat', [ChatController::class, 'chat'])->name('chat.send');;
});

Route::get('enable2fa', [TwoFAController::class, 'twofaEnable'])->name('enable2fa');

// ITEMS
// Route::get('/items', [ItemController::class, 'index'])->name('items.index');
// Route::post('/items', [ItemController::class, 'store'])->name('items.store');
// Route::get('/items/{id}', [ItemController::class, 'show'])->name('items.show');

// TASKS
Route::group([
    'prefix' => 'agent',
    'as' => 'agent.',
], function () {
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
});


// Route for fetching task details
Route::get('/tasks/{id}', function ($id) {
    $task = Task::with('client', 'supplier', 'agent.branch', 'invoiceDetail.invoice')->find($id);
    if ($task) {
        return response()->json($task);
    }
    return response()->json(['error' => 'Task not found'], 404);
});



// INVOICE
Route::middleware('auth')->group(function () {
    Route::get('/sale-invoice', [InvoiceController::class, 'salelist'])->name('invoice.salelist');
    Route::get('/invoice/create', [InvoiceController::class, 'create'])->name('invoice.create');
    Route::get('/company/agents/invoices', [InvoiceController::class, 'companyAgentsInvoices'])->name('invoices.company.agents');
});

Route::get('/invoice/{invoiceNumber}', [InvoiceController::class, 'show'])->name('invoice.show');
Route::post('/invoice/store', [InvoiceController::class, 'store'])->name('invoice.store');
Route::put('/invoice/{id}', [InvoiceController::class, 'update'])->name('invoice.update');
Route::patch('/invoices/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('invoices.updateStatus');
Route::post('/invoices/clientadd', [InvoiceController::class, 'clientAdd'])->name('invoices.clientAdd');
Route::get('/invoice/edit/{invoiceNumber}', [InvoiceController::class, 'edit'])->name('invoice.edit');
Route::post('/invoice/partial', [InvoiceController::class, 'savePartial'])->name('invoice.partial');   
Route::post('/invoice/remove/partial', [InvoiceController::class, 'removePartial'])->name('invoice.removepartial'); 
Route::get('/invoice/partial/{invoiceNumber}/{clientId}', [InvoiceController::class, 'split'])->name('invoice.split');


// search for invoice creation

// branch 
Route::get('/search-branch', [InvoiceController::class, 'searchBranch'])->name('search.branch');
Route::post('/select-branch', [InvoiceController::class, 'selectBranch'])->name('select.branch');

// agent
Route::get('/search-agent', [InvoiceController::class, 'searchAgent'])->name('search.agent');
Route::post('/select-agent', [InvoiceController::class, 'selectAgent'])->name('select.agent');

// client
Route::get('/search-client', [InvoiceController::class, 'searchClient'])->name('search.client');
Route::post('/select-client', [InvoiceController::class, 'selectClient'])->name('select.client');


// items 
Route::get('/search-item', [InvoiceController::class, 'searchItems'])->name('search.item');
Route::post('/select-item', [InvoiceController::class, 'selectItems'])->name('select.item');




// PAYMENT
Route::group([
    'middleware' => ['auth'],
], function () {

    Route::get('/payment', [PaymentController::class, 'showPaymentPage'])->name('payment.choose');
    Route::get('/payment/process', [PaymentController::class, 'process'])->name('payment.process');
    Route::post('/payment-create/{invoiceNumber}', [PaymentController::class, 'create'])->name('payment.create');
    Route::post('/payment-webhook', [PaymentController::class, 'webhook'])->name('payment.webhook');
    Route::get('/payment-check', [PaymentController::class, 'check'])->name('payment.check');
    Route::get('/payment-clients/{invoiceNumber}', [PaymentController::class, 'paymentClientRedirect'])->name('payment.clients');
    Route::get('/payment-clients-process', [PaymentController::class, 'paymentClientProcess'])->name('payment.clients.process');
    Route::get('/clients/create', action: [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/list', [ClientController::class, 'list'])->name('clients.list');
    Route::get('clients/{id}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('clients/{id}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('clients/{id}', [ClientController::class, 'update'])->name('clients.update');
    Route::post('/clientsupload', [ClientController::class, 'import'])->name('clientsupload.import');
    Route::put('/client/{id}/change-agent', [ClientController::class, 'changeAgent'])->name('client.changeAgent');
});

// REPORTS
Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::post('/upload-pdf', [TaskController::class, 'uploadPdf']);


Route::get('/reports/agent', [ReportController::class, 'agentReport'])->name('reports.agent');
Route::get('/reports/client', [ReportController::class, 'clientReport'])->name('reports.client');
Route::get('/reports/clientmgmnt', [ReportController::class, 'clientMgmnt'])->name('reports.clientmgmnt');
Route::get('/reports/performance', [ReportController::class, 'performance'])->name('reports.performance');
Route::get('/reports/summary', [ReportController::class, 'summary'])->name('reports.summary');
Route::get('/reports/accsummary', [ReportController::class, 'accsummary'])->name('reports.accsummary');

// EXPORT
Route::get('/download-company', [ExportController::class, 'downloadCompany'])->name('download.company');
Route::get('/download-agent', [ExportController::class, 'downloadAgent'])->name('download.agent');
Route::get('/download-task', [ExportController::class, 'downloadTask'])->name('download.tasks');
Route::get('/download-client', [ExportController::class, 'downloadClient'])->name('download.client');
Route::get('export-companies', [CompanyController::class, 'exportCsv'])->name('companies.exportCsv');
Route::get('export-agents', [AgentController::class, 'exportCsv'])->name('agents.exportCsv');
Route::get('export-tasks', [TaskController::class, 'exportCsv'])->name('tasks.exportCsv');

Route::get('export-clients', [TaskController::class, 'exportCsv'])->name('clients.exportCsv');

// todolist routes
Route::get('/todolist', [ToDoListController::class, 'index'])->name('todolist.index');
Route::post('/todolist', [ToDoListController::class, 'store'])->name('todolist.store');
Route::get('/todolist/{id}', [ToDoListController::class, 'show'])->name('todolist.show');
Route::get('/todolist/{id}/edit', [ToDoListController::class, 'edit'])->name('todolist.edit');

//CHARGES
Route::get('/charges', [ChargeController::class, 'index'])->name('charges.index');
Route::get('/charges/create', [ChargeController::class, 'create'])->name('charges.create');
Route::get('/charges/{id}/edit', [ChargeController::class, 'edit'])->name('charges.edit');
Route::delete('/charges/{id}', [ChargeController::class, 'destroy'])->name('charges.destroy');
Route::put('/charges/{id}', [ChargeController::class, 'update'])->name('charges.update');
Route::get('/charges/{id}', [ChargeController::class, 'show']);


// NOIFICATIONS
Route::group([
    'middleware' => ['auth'],
    'prefix' => 'notifications',
    'as' => 'notifications.',
], function () {
    Route::get('/', NotificationIndex::class)->name('index');
});

require __DIR__ . '/auth.php';
