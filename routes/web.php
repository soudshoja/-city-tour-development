<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AdminUsersController;
use App\Http\Controllers\AgentController; // Add this line if you create a SearchController
use App\Http\Controllers\Auth\TwoFAController;
use App\Http\Controllers\AutoBillingController;
use App\Http\Controllers\BankPaymentController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BulkInvoiceController;
use App\Http\Controllers\ChargeController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CoaController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\CurrencyExchangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\MyFatoorahController;
use App\Http\Controllers\OpenAiController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptVoucherController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResayilController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SupplierCompanyController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierCredentialController;
use App\Http\Controllers\SupplierProcedureController;
use App\Http\Controllers\SystemExchangeRateController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TBOController;
use App\Http\Controllers\TermController;
use App\Http\Controllers\ToDoListController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserSettingController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\WhatsappController;
use App\Livewire\NotificationIndex;
use App\Models\Role;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/iata', [ProfileController::class, 'updateIataSettings'])->name('profile.iata.update');

    Route::prefix('profile/password')->name('profile.password.')->group(function () {
        Route::post('/request', [ProfileController::class, 'requestPasswordUpdate'])->name('request-update');
        Route::get('/verify-code', [ProfileController::class, 'showConfirmCodeForm'])->name('confirm-code');
        Route::post('/verify-code', [ProfileController::class, 'verifyCode'])->name('verify-code');
        Route::get('/update', [ProfileController::class, 'showPasswordForm'])->name('update-password-form');
        Route::put('/update', [ProfileController::class, 'updatePassword'])->name('update');
    });

    // ROUTE THAT DOESN'T HAVE CONTROLLER
    Route::get('pin', function () {
        return view('auth.pin');
    })->name('pin');

    Route::post('verify2fa', function () {
        return redirect()->route('dashboard');
    })->name('verify2fa');

    // 2FA
    Route::get('set-up-authenticator', [TwoFAController::class, 'twofa'])->name('2fa');
    Route::get('enable2fa', [TwoFAController::class, 'twofaEnable'])->name('enable2fa');

    // Add a route for search functionality
    Route::get('/search', [SearchController::class, 'search'])->name('search'); // Assuming you will create this controller

    // Admin users
    Route::group([
        'prefix' => 'users',
        // 'as' => 'users.',
        // 'middleware' => ['role:admin'],
    ], function () {
        Route::get('/adminsList', [AdminUsersController::class, 'index'])->name('users.index');
        Route::get('/companies', [AdminUsersController::class, 'ShowCompanies'])->name('companies.index');
        Route::get('/companies/new', [AdminUsersController::class, 'newCompany'])->name('companiesnew.new');
        Route::get('/create', [AdminUsersController::class, 'create'])->name('users.create');
        Route::post('/companies', [AdminUsersController::class, 'store'])->name('companies.store');
        Route::get('/edit/{userId}', [AdminUsersController::class, 'editRole'])->name('users.edit');
        Route::put('/update-role', [AdminUsersController::class, 'storeRole'])->name('users.role');
        Route::put('/{user}/update-info', [AdminUsersController::class, 'updateInfo'])->name('users.updateInfo');
        Route::post('/set-company', [AdminUsersController::class, 'setCompany'])->name('users.set-company');
    });

    Route::group([
        'prefix' => 'system-settings',
        'as' => 'system-settings.',
    ], function () {
        Route::get('/', [SystemSettingController::class, 'index'])->name('index');
        Route::post('/send-test-email', [SystemSettingController::class, 'sendTestEmail'])->name('send-test-email');
        Route::get('/preview-email', [SystemSettingController::class, 'previewEmail'])->name('preview-email');
        Route::post('/send-whatsapp-pdf', [SystemSettingController::class, 'sendWhatsAppPdf'])->name('send-whatsapp-pdf');
        Route::get('/download-pdf', [SystemSettingController::class, 'downloadPdf'])->name('download-pdf');
        Route::post('/save-tab', [SystemSettingController::class, 'saveTab'])->name('save-tab');
        Route::post('/check-file-status', [SystemSettingController::class, 'checkFileStatus'])->name('check-file-status');
        Route::get('/hotels/list', [SystemSettingController::class, 'hotelsList'])->name('hotels.list');
        Route::post('/hotels', [SystemSettingController::class, 'storeHotel'])->name('hotels.store');
        Route::put('/hotels/{id}', [SystemSettingController::class, 'updateHotel'])->name('hotels.update');
        Route::delete('/hotels/{id}', [SystemSettingController::class, 'deleteHotel'])->name('hotels.delete');
        Route::get('/countries/search', [SystemSettingController::class, 'searchCountries'])->name('countries.search');
        Route::post('/countries', [SystemSettingController::class, 'storeCountry'])->name('countries.store');
    });

    // Agents list
    Route::group([
        'prefix' => 'agents',
        'as' => 'agents.',
    ], function () {
        Route::get('/', [AgentController::class, 'index'])->name('index');
        // Route::get('/new', [AgentController::class, 'new'])->name('new');
        Route::post('/', [AgentController::class, 'store'])->name('store');
        Route::get('/upload', [AgentController::class, 'upload'])->name('upload');
        Route::post('/upload', [AgentController::class, 'import'])->name('import');
        Route::get('/{id}', [AgentController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [AgentController::class, 'edit'])->name('edit');
        Route::put('/{id}', [AgentController::class, 'update'])->name('update');
        Route::put('/update-commision/{id}', [AgentController::class, 'updateCommission'])->name('update-commission');
        // Route::post('/create-profile', [AgentController::class, 'createAgentProfile'])->name('create.profile');
        Route::get('/{id}/tasks', [AgentController::class, 'getTasks'])->name('tasks');
        Route::get('/{id}/clients', [AgentController::class, 'getClients'])->name('clients');
        Route::get('/{id}/invoices', [AgentController::class, 'getInvoices'])->name('invoices');
    });

    // Route::get('/companies/create', [CompanyController::class, 'showCreateOptions'])->name('companies.showCreateOptions');
    Route::post('/companies/create-branch', [CompanyController::class, 'createBranch'])->name('companies.createBranch');
    Route::post('/companies/create-agent', [CompanyController::class, 'createAgent'])->name('companies.createAgent');
    Route::post('/companies/create-accountant', [CompanyController::class, 'createAccountant'])->name('companies.createAccountant');
    Route::post('/companies/create-client', [CompanyController::class, 'createClient'])->name('companies.createClient');

    // Route to show the delete form (GET request)
    Route::get('/agent-types/delete', [CompanyController::class, 'showDeleteAgentTypeForm'])->name('agent-types.delete.form');

    // Route to handle the delete request (DELETE request)
    Route::delete('/agent-types/delete', [CompanyController::class, 'deleteAgentType'])->name('agent-types.delete');

    Route::get('/companiesupload', [CompanyController::class, 'upload'])->name('companiesupload.upload');
    Route::post('/companiesupload', [CompanyController::class, 'import'])->name('companiesupload.import');
    // Route::get('/companies/{id}', [CompanyController::class, 'show'])->name('.show');
    Route::get('/companies/{id}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::put('/companies/{id}', [CompanyController::class, 'update'])->name('companies.update');
    Route::post('/company/{company}/toggle-status', [CompanyController::class, 'toggleStatus']);

    //COMPANY
    Route::group([
        'prefix' => 'companies',
        'as' => 'companies.',
        'middleware' => ['auth', 'role:admin'],
    ], function () {
        Route::get('/', [CompanyController::class, 'index'])->name('list');
        Route::get('/{id}', [CompanyController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [CompanyController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CompanyController::class, 'update'])->name('update');
    });

    //TASKS
    Route::group([
        'prefix' => 'tasks',
        'as' => 'tasks.',
    ], function () {
        Route::post('/{task}/toggle-status', [TaskController::class, 'toggleStatus'])->name('toggleStatus');
        Route::get('/', [TaskController::class, 'index'])->name('index');
        Route::get('detail', [TaskController::class, 'detail'])->name('detail');
        Route::get('/get-tasks', [TaskController::class, 'getTasks'])->name('get-tasks');
        Route::get('/search-original-tasks', [TaskController::class, 'searchOriginalTasks'])->name('search-original-tasks');
        Route::get('/show/{id}', [TaskController::class, 'show'])->name('show');
        Route::get('/voucher', [TaskController::class, 'voucher'])->name('voucher');
        Route::put('/update/{id}', [TaskController::class, 'update'])->name('update');
        Route::post('/upload', [TaskController::class, 'upload'])->name('upload');
        Route::get('/agents/{agentId}', [TaskController::class, 'getAgentTask'])->name('agent');
        Route::get('/all/queue', [TaskController::class, 'queue'])->name('queue');
        Route::get('/supplier-task/{id}', [TaskController::class, 'supplierTask'])->name('supplier');
        Route::post('/agent/upload', [TaskController::class, 'supplierTaskForAgent'])->name('agent.upload');
        Route::get('/get-tbo/{companyId}', [TaskController::class, 'getTboTask'])->name('get-tbo');
        Route::get('/pdf/flight/{taskId}', [TaskController::class, 'flightPdf'])->name('pdf.flight')->withoutMiddleware(['auth']);
        Route::get('/pdf/hotel/{taskId}', [TaskController::class, 'hotelPdf'])->name('pdf.hotel')->withoutMiddleware(['auth']);
        Route::get('/pdf/receipt/{taskId}', [TaskController::class, 'receiptPdf'])->name('pdf.receipt');
        Route::get('/pdf/receipt/{taskId}/download', [TaskController::class, 'receiptPdfDownload'])->name('pdf.receipt.download');
        Route::post('/upload', [TaskController::class, 'clientPassport'])->name('upload.passport');
        Route::delete('/{id}', [TaskController::class, 'destroy'])->name('destroy');
        Route::post('/columns/save', [TaskController::class, 'saveColumnPrefs'])->name('columns.save');
        Route::post('/bulk-update', [TaskController::class, 'bulkUpdate'])->name('bulkUpdate');
        Route::post('/tasks/bulk-update', [TaskController::class, 'bulkUpdate'])->name('bulk-update');
        Route::post('/store-manual', [TaskController::class, 'storeManualHotel'])->name('store.manual');
        Route::put('/update-financial/{task}', [TaskController::class, 'updateAdminFinancial'])->name('update.financial');
        Route::post('/{task}/switch-invoice', [TaskController::class, 'switchInvoiceTask'])->name('switchInvoice');
        Route::put('/update-multi', [TaskController::class, 'updateMulti'])->name('updateMulti');
        Route::get('/{task}/details', [TaskController::class, 'getTaskDetails'])->name('getDetails');
        Route::post('/{task}/update-details', [TaskController::class, 'updateTaskDetails'])->name('updateDetails');

    });

    // SUPPLIERS
    Route::group([
        'prefix' => 'suppliers',
        'as' => 'suppliers.',
    ], function () {
        Route::get('/{suppliersId}/export-excel', [SupplierController::class, 'exportExcel'])->name('suppliers.export.excel');
        Route::get('/{suppliersId}/export-pdf', [SupplierController::class, 'exportPdf'])->name('suppliers.export.pdf');
        Route::post('/store', [SupplierController::class, 'store'])->name('store');
        Route::put('/update/{id}', [SupplierController::class, 'update'])->name('update');
        Route::post('/{supplierCompany}/update-surcharges', [SupplierController::class, 'updateSurcharges'])->name('update.surcharges');
        Route::get('/{suppliersId}', [SupplierController::class, 'show'])->name('show');
        Route::get('/total-ledger/{supplierId}/date/{endDate}', [SupplierController::class, 'getTotalDebitCredit'])->name('total-ledger');
        Route::get('/magic/get', [SupplierController::class, 'getMagicHoliday'])->name('magic.get');
        Route::get('/magic/credential', [SupplierController::class, 'getClientCredential'])->name('magic-credential');
        Route::get('/magic/request', [SupplierController::class, 'makeApiRequest'])->name('magic-request');
        Route::get('/magic/callback', [SupplierController::class, 'handleAuthorizationCallback'])->name('magic-callback');
        Route::get('/magic/provider', [SupplierController::class, 'redirectToAuthorization'])->name('magic-provider');
        Route::get('/magic/webhook-initiate/{id}', [SupplierController::class, 'magicReserveWebhook'])->name('magic-webhook');
        Route::get('/ledger-by-date/{supplierId}', [SupplierController::class, 'ledgerByDateRange'])->name('suppliers.ledger-by-date');
        Route::get('/', [SupplierController::class, 'index'])->name('index');

        Route::group([
            'prefix' => 'tbo',
            'as' => 'tbo.',
        ], function () {
            Route::get('index', [TBOController::class, 'index'])->name('index');
            Route::get('book/index', [TBOController::class, 'bookIndex'])->name('book.index');
            Route::post('search', [TBOController::class, 'search'])->name('search');
            Route::get('country', [TBOController::class, 'countryList'])->name('country-list');
            Route::get('country/{countryCode}/city', [TBOController::class, 'cityListPage'])->name('city-list');
            Route::get('city/{cityCode}/hotel', [TBOController::class, 'hotelCityList'])->name('hotel-list');
            Route::get('hotel', [TBOController::class, 'hotelCodeList'])->name('hotel-code-list');
            Route::get('hotel/{hotelCode}', [TBOController::class, 'hotelDetails'])->name('hotel-details');
            Route::get('booking-detail', [TBOController::class, 'bookingDetail'])->name('booking-detail');
            Route::get('booking-details-by-date', [TBOController::class, 'bookingDetailByDate'])->name('booking-details-by-date');
            Route::get('prebook/index', [TBOController::class, 'preBookIndex'])->name('prebook.index');
            Route::post('prebook', [TBOController::class, 'preBookStore'])->name('prebook.store');
            Route::get('prebook/{tboId}', [TBOController::class, 'preBookShow'])->name('prebook.show');
            Route::post('book', [TBOController::class, 'book'])->name('book');
            Route::get('cancel-booking/{confirmationNo}', [TBOController::class, 'cancel'])->name('cancel-booking');
            Route::post('credentials', [TBOController::class, 'setCredentials'])->name('credentials');
            Route::get('reset-tbo-credentials', [TBOController::class, 'destroyTBOSession'])->name('reset');
            Route::get('get-all-destinations', [TBOController::class, 'getAllDestinations'])->name('all-destinations');
        });
    });

    //ROLE
    Route::group([
        'prefix' => 'role',
        'as' => 'role.',
    ], function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::get('/permission', [RoleController::class, 'getAllPermission'])->name('all-permission');
        Route::get('/create', [RoleController::class, 'create'])->name('create');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::get('/{roleId}', [RoleController::class, 'edit'])->name('edit');
        Route::put('/', [RoleController::class, 'update'])->name('update');
        Route::delete('/{roleId}', [RoleController::class, 'destroy'])->name('destroy');
        Route::get('/permission/{role}', [RoleController::class, 'permission'])->name('permission');
    });

    // COA
    Route::group([
        'prefix' => 'coa',
        'as' => 'coa.',
    ], function () {
        Route::get('/', [CoaController::class, 'index'])->name('index');
        Route::post('/create', [CoaController::class, 'createAccounts'])->name('create');
        Route::delete('/api/{id}', [CoaController::class, 'dstry'])->name('destroy');
        Route::post('/updateCode/{id}', [CoaController::class, 'updateCode'])->name('updateCode');
        Route::match(['get', 'post'], '/transactions', [CoaController::class, 'transaction'])->name('transaction');
        Route::post('/addCategory', [CoaController::class, 'addCategory'])->name('addCategory');
        Route::get('/export', [CoaController::class, 'exportAccounts'])->name('export');
        Route::post('/import', [CoaController::class, 'importAccounts'])->name('import');
        Route::post('/delegate-price', [CoaController::class, 'delegatePriceAmadeus'])->name('delegate-price');
        Route::delete('/{id}', [CoaController::class, 'deleteTransaction'])->name('deleteTransaction');
    });

    //    / Route::get('/accounting-summary', [AccountingController::class, 'index'])->name('accounting.index');
    Route::get('/accounting-summary', [AccountingController::class, 'showCompanySummary'])->name('accounting.index');
    Route::get('/transaction', [AccountingController::class, 'index'])->name('accounting.transaction');
    Route::post('/filter-ledgers', [AccountingController::class, 'filterLedgers']);
    Route::post('/export-excel', [AccountingController::class, 'exportExcel']);

    Route::get('/payable-details/payable-create', [AccountingController::class, 'createPayableDetail'])->name('payable-details.payable-create');
    Route::post('/payable-details/payable-store', [AccountingController::class, 'storePayableDetail'])->name('payable-details.payable-store');
    Route::get('/receivable-details/receivable-create', [AccountingController::class, 'createReceivableDetail'])->name('receivable-details.receivable-create');
    Route::post('/receivable-details/receivable-store', [AccountingController::class, 'storeReceivableDetail'])->name('receivable-details.receivable-store');
    Route::get('/get-accounts-by-company-payable', [AccountingController::class, 'getAccountsByCompanyPayable'])->name('get.accounts.by.company.payable');
    Route::get('/get-accounts-by-company-receivable', [AccountingController::class, 'getAccountsByCompanyReceivable'])->name('get.accounts.by.company.receivable');
    Route::get('/get-branches-by-company', [AccountingController::class, 'getBranchByCompany'])->name('get.branches.by.company');
    Route::get('/get-agents-by-branch-company', [AccountingController::class, 'getAgentByBranchCompany'])->name('get.agents.by.branch.company');
    Route::get('/get-suppliers-by-company', [AccountingController::class, 'getSupplierByCompany'])->name('get.suppliers.by.company');
    Route::get('/get-agents-clients-by-company', [AccountingController::class, 'getAgentClientByCompany'])->name('get.agents.clients.by.company');
    Route::get('/get-bank-accounts-by-company', [AccountingController::class, 'getBankAccountByCompany'])->name('get.bank.accounts.by.company');
    Route::get('/get-invoices-by-JournalEntry', [AccountingController::class, 'getInvoicesByJournalEntry'])->name('get.invoices.by.JournalEntry');

    //BRANCHES
    Route::group([
        'prefix' => 'branches',
        'as' => 'branches.',
    ], function () {
        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::post('/', [BranchController::class, 'store'])->name('store');
        Route::get('/create', [BranchController::class, 'create'])->name('create');
        Route::get('/{id}/edit', [BranchController::class, 'edit'])->name('edit');
        Route::get('/{id}', [BranchController::class, 'show'])->name('show');
        Route::put('/{id}', [BranchController::class, 'update'])->name('update');
    });

    // whatsapp
    Route::post('/whatsapp/send', [WhatsappController::class, 'sendMessage'])->name('whatsapp.send');
    Route::post('/whatsapp/send1', [WhatsappController::class, 'sendMessage1'])->name('whatsapp.send1');
    Route::post('/whatsapp/sendpdf', [WhatsappController::class, 'sendMessagepdf'])->name('whatsapp.sendpdf');

    Route::match(['get', 'post'], '/whatsapp/whatsapp-webhook', [WhatsappController::class, 'handleWebhook'])->withoutMiddleware(['auth']);
    Route::get('/invoice/send/{invoiceNumber}', [InvoiceController::class, 'sendInvoice']);

    // open api
    Route::get('/open-ai', [OpenAiController::class, 'index'])->name('open-ai.index');
    Route::post('/open-ai', [OpenAiController::class, 'store'])->name('open-ai.store');
    Route::get('/fine-tuning', [OpenAiController::class, 'fineTuningView'])->name('fine-tuning');
    Route::get('/testclient', [OpenAiController::class, 'getClient']);
    Route::get('/openai/steps', [OpenAiController::class, 'steps'])->name('steps');
    Route::get('/openai/function-tools', [OpenAiController::class, 'addFunctionTool'])->name('function-tools');

    Route::post('/chat', [ChatController::class, 'chat'])->name('chat.process');
    Route::post('/chat/tasks/select', [ChatController::class, 'sendprocessTaskSelection'])->name('chat.select');
    Route::post('/chat/invoices/create', [ChatController::class, 'handleTaskPricing'])->name('chat.create');
    Route::post('/chat/client', [ChatController::class, 'createClient'])->name('chat.client');
    Route::post('/chat/agent', [ChatController::class, 'createAgent'])->name('chat.agent');
    Route::post('/chat/branch', [ChatController::class, 'createBranch'])->name('chat.branch');
    Route::post('/chat/payment', [ChatController::class, 'processPayment'])->name('chat.processPayment');
    Route::post('/chat/upload', [ChatController::class, 'handleFileUpload'])->name('chat.handleFileUpload');

    // MyMyFatoorah
    Route::get('callback', [MyFatoorahController::class, 'callback'])->name('myfatoorah.callback');
    Route::get('/myfatoorah/pay-now', [MyFatoorahController::class, 'index'])->name('myfatoorah.paynow');
    Route::get('checkout', [MyFatoorahController::class, 'checkout'])->name('myfatoorah.checkout');

    Route::get('suppliers/{supplier}/exchange-rates', [SupplierController::class, 'exchangeRates'])->name('suppliers.exchange-rates');
    Route::post('suppliers/{supplier}/exchange-rates', [SupplierController::class, 'updateExchangeRates'])->name('suppliers.exchange-rates.update');
    //TRANSACTION
    Route::group([
        'prefix' => 'transactions',
        'as' => 'transactions.',
    ], function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
    });

    //JOURNAL ENTRY
    Route::group([
        'prefix' => 'journal-entries',
        'as' => 'journal-entries.',
    ], function () {
        Route::get('/{transactionId}', [JournalEntryController::class, 'index'])->name('index');
        Route::get('/{accountId}/account', [JournalEntryController::class, 'show'])->name('show');
        Route::get('/{accountId}/export/pdf', [JournalEntryController::class, 'exportPdf'])->name('export.pdf');
    });

    Route::group([
        'prefix' => 'reports',
        'as' => 'reports.',
    ], function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/agent', [ReportController::class, 'agentReport'])->name('agent');
        Route::match(['get', 'post'], '/client', [ReportController::class, 'clientReport'])->name('client');
        Route::match(['get', 'post'], '/client/pdf', [ReportController::class, 'clientReportPdf'])->name('client.pdf');
        Route::get('/clientmgmnt', [ReportController::class, 'clientMgmnt'])->name('clientmgmnt');
        Route::get('/performance', [ReportController::class, 'performance'])->name('performance');
        Route::get('/summary', [ReportController::class, 'summary'])->name('summary');
        Route::get('/accsummary', [ReportController::class, 'accsummary'])->name('accsummary');
        Route::get('/unpaid-report', [ReportController::class, 'unpaidaccountsPayableReceivableReport'])->name('unpaid-report');
        Route::get('/paid-report', [ReportController::class, 'paidaccountsPayableReceivableReport'])->name('paid-report');
        Route::get('/payable_supplier', [ReportController::class, 'payableSupplier'])->name('payable-supplier');
        Route::get('/profit-agent', [ReportController::class, 'profitAgent'])->name('profit-agent');
        Route::get('/total-receivable', [ReportController::class, 'receivable'])->name('total-receivable');
        Route::get('/total-bank', [ReportController::class, 'totalBank'])->name('total-bank');
        Route::get('/gateway-receivable', [ReportController::class, 'gatewayReceivable'])->name('gateway-receivable');
        Route::get('/account-list', [ReportController::class, 'getAccounts'])->name('account-list');
        Route::get('/acc-reconcile', [ReportController::class, 'accountsReconciliationReport'])->name('acc-reconcile');
        Route::get('/settlements', [ReportController::class, 'settlementsReport'])->name('settlements');
        Route::get('/settlements/entries/by-date', [ReportController::class, 'journalEntriesByDate'])
            ->name('settlements.entries.by_date');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/creditors', [ReportController::class, 'creditors'])->name('creditors');
        Route::get('/creditors/pdf', [ReportController::class, 'creditorsPdf'])->name('creditors.pdf');
        Route::match(['get', 'post'], '/daily-sales', [ReportController::class, 'dailySalesReport'])->name('daily-sales');
        Route::get('/daily-sales/pdf', [ReportController::class, 'dailySalesPdf'])->name('daily-sales.pdf');
        Route::get('/daily-sales/pdf/download', [ReportController::class, 'dailySalesPdfDownload'])->name('daily-sales.pdf.download');
        Route::match(['get', 'post'], '/tasks', [ReportController::class, 'tasksReport'])->name('tasks');
        Route::match(['get', 'post'], '/tasks/pdf', [ReportController::class, 'tasksReportPdf'])->name('tasks.pdf');

        Route::group([
            'prefix' => 'ajax',
            'as' => 'ajax.',
        ], function () {
            Route::get('/dashboard-stats', [ReportController::class, 'getDashboardStats'])->name('dashboard-stats');
        });
    });

    // INVOICE
    Route::group([
        'prefix' => 'invoices',
        'as' => 'invoices.',
    ], function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/create', [InvoiceController::class, 'create'])->name('create');
        Route::get('/link', [InvoiceController::class, 'link'])->name('link');
        Route::post('/clientAdd', [InvoiceController::class, 'clientAdd'])->name('clientAdd');
    });

    Route::group([
        'prefix' => 'invoice',
        'as' => 'invoice.',
    ], function () {
        Route::get('/{companyId}/{invoiceNumber}/arabic', [InvoiceController::class, 'showArabic'])->name('show-arabic')->withoutMiddleware(['auth']);
        Route::post('/store', [InvoiceController::class, 'store'])->name('store');
        Route::put('/{id}', [InvoiceController::class, 'update'])->name('update');
        Route::delete('/delete/{id}', [InvoiceController::class, 'delete'])->name('delete');
        // Route::patch('/invoice/{invoice}/status', [InvoiceController::class, 'updateStatus'])->name('updateStatus');
        Route::get('/edit/{companyId}/{invoiceNumber}', [InvoiceController::class, 'edit'])->name('edit');
        Route::post('/update-type', [InvoiceController::class, 'updatePaymentType'])->name('update-type');
        Route::post('/update-partial-gateway', [InvoiceController::class, 'updatePartialGateway'])->name('update-partial-gateway');
        Route::post('/update-gateway', [InvoiceController::class, 'updatePaymentGateway'])->name('update-gateway');
        Route::post('/add-task', [InvoiceController::class, 'addTask'])->name('add-task');
        Route::post('/remove-task', [InvoiceController::class, 'removeTask'])->name('remove-task');
        Route::post('/partial', [InvoiceController::class, 'savePartial'])->name('partial');
        Route::post('/remove/partial', [InvoiceController::class, 'removePartial'])->name('removepartial');
        Route::get('/partial/{invoiceNumber}/{clientId}/{partialId}', [InvoiceController::class, 'split'])->name('split')->withoutMiddleware(['auth']);
        Route::get('/partial/{invoiceNumber}/{clientId}/{partialId}/arabic', [InvoiceController::class, 'splitarabic'])->name('split-arabic')->withoutMiddleware(['auth']);
        Route::post('/client-credit', [InvoiceController::class, 'createInvoiceLinkWithClientCredit'])->name('client-credit');
        Route::post('/{companyId}/{invoiceNumber}/send-email', [InvoiceController::class, 'sendInvoiceEmail'])->name('send-email');

        // Payment Application Routes (Credit payment with payment selection)
        Route::post('/available-payments', [InvoiceController::class, 'getAvailablePayments'])->name('available-payments');
        Route::post('/apply-payments', [InvoiceController::class, 'applyPaymentsToInvoice'])->name('apply-payments');
        Route::post('/validate-payment-selection', [InvoiceController::class, 'validatePaymentSelection'])->name('validate-payment-selection');
        Route::get('/payment-history/{invoiceId}', [InvoiceController::class, 'getInvoicePaymentHistory'])->name('payment-history');

        Route::get('/{invoiceNumber}', function () {
            return redirect()->route('invoice.show', ['companyId' => 1, 'invoiceNumber' => request()->invoiceNumber]);
        })->withoutMiddleware(['auth']);

        Route::group([ // make sure to put this route before the route with {companyId}/{invoiceNumber} route as it may conflict because of the dynamic parameters
            'prefix' => 'accountant',
            'as' => 'accountant.',
            'middleware' => 'accountant',
        ], function () {
            Route::get('{companyId}/edit/{invoiceNumber}', [InvoiceController::class, 'accountantEdit'])->name('edit');
            Route::put('/update', [InvoiceController::class, 'accountantUpdate'])->name('update');
            Route::post('/create-payment-link-shortage', [InvoiceController::class, 'createPaymentLinkForShortage'])->name('create.payment.link.shortage');
        });

        Route::get('/{companyId}/{invoiceNumber}', [InvoiceController::class, 'show'])->name('show')->withoutMiddleware(['auth']);
        Route::get('/{companyId}/{invoiceNumber}/pdf', [InvoiceController::class, 'generatePdf'])->name('pdf')->withoutMiddleware(['auth']);
        Route::get('/{companyId}/{invoiceNumber}/proforma', [InvoiceController::class, 'proforma'])->name('proforma')->withoutMiddleware(['auth']);
        Route::get('/{companyId}/{invoiceNumber}/proforma-pdf', [InvoiceController::class, 'proformaGeneratePdf'])->name('proforma.pdf')->withoutMiddleware(['auth']);
        Route::put('/{companyId}/{invoiceNumber}/date', [InvoiceController::class, 'updateDate'])->name('updateDate');
        Route::put('/{companyId}/{invoiceNumber}/amount', [InvoiceController::class, 'updateAmount'])->name('updateAmount');
        Route::post('/update-task-price', [InvoiceController::class, 'updateTaskPrice'])->name('updateTaskPrice');
        Route::get('/details/{companyId}/{invoiceNumber}', [InvoiceController::class, 'showDetails'])->name('details');
    });

    // Bulk Invoice Upload Routes
    Route::group([
        'prefix' => 'bulk-invoices',
        'as' => 'bulk-invoices.',
        'middleware' => ['auth'],
    ], function () {
        Route::get('/upload', [BulkInvoiceController::class, 'index'])->name('index');
        Route::get('/template', [BulkInvoiceController::class, 'downloadTemplate'])->name('template');
        Route::post('/upload', [BulkInvoiceController::class, 'upload'])->name('upload');
        Route::get('/{id}/error-report', [BulkInvoiceController::class, 'downloadErrorReport'])->name('error-report');
        Route::get('/{id}/preview', [BulkInvoiceController::class, 'preview'])->name('preview');
        Route::post('/{id}/approve', [BulkInvoiceController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [BulkInvoiceController::class, 'reject'])->name('reject');
        Route::get('/{id}/success', [BulkInvoiceController::class, 'success'])->name('success');
    });

    // REFUND
    Route::group([
        'prefix' => 'refunds',
        'as' => 'refunds.',
    ], function () {
        Route::get('/', [RefundController::class, 'index'])->name('index');
        Route::get('/create', [RefundController::class, 'create'])->name('create');
        Route::post('/', [RefundController::class, 'store'])->name('store');
        Route::post('/unpaid-invoice', [RefundController::class, 'storeForUnpaidInvoice'])->name('store-unpaid');
        Route::get('/{refund}/edit', [RefundController::class, 'edit'])->name('edit');
        Route::put('/{refund}', [RefundController::class, 'update'])->name('update');
        Route::post('/{refund}/complete-process', [RefundController::class, 'completeProcess'])->name('complete_process');
        Route::get('/{refundClientId}/complete', [RefundController::class, 'completeRefundClient'])->name('refund-client.complete');
        Route::delete('/{refundClientId}', [RefundController::class, 'deleteRefundClient'])->name('refund-client.delete');
        Route::get('/{companyId}/{refundNumber}', [RefundController::class, 'show'])->name('show')->withoutMiddleware(['auth']);
        Route::get('/eligible-tasks', [RefundController::class, 'getEligibleTasks'])->name('eligible-tasks');
    });

    Route::group([
        'prefix' => 'payment',
        'as' => 'payment.',
    ], function () {
        Route::get('/{id}/details', [PaymentController::class, 'show'])->name('show');
        Route::get('/{id}/partials', [PaymentController::class, 'getPartials'])->name('partials');
        Route::get('/{id}/transactions', [PaymentController::class, 'getTransactions'])->name('transactions');
        Route::put('/{id}/items', [PaymentController::class, 'updatePaymentItems'])->name('items.update');
        Route::patch('/{id}/receipt', [PaymentController::class, 'updateReceipt'])->name('receipt.update');
        Route::post('/create/{companyId}/{invoiceNumber}', [PaymentController::class, 'create'])->name('create')->withoutMiddleware(['auth']);
        //Route::match(['get', 'post'], '/create/{invoiceNumber}', [PaymentController::class, 'create'])->name('create')->withoutMiddleware(['auth']);
        Route::post('/webhook', [PaymentController::class, 'webhook'])->name('webhook');
        Route::get('/check', [PaymentController::class, 'check'])->name('check');
        Route::get('/success', [PaymentController::class, 'success'])->name('success')->withoutMiddleware(['auth']);
        Route::get('/failed', [PaymentController::class, 'failed'])->name('failed')->withoutMiddleware(['auth']);
        Route::get('/outstanding', [PaymentController::class, 'outstanding'])->name('outstanding');

        Route::group([
            'prefix' => 'link',
            'as' => 'link.',
        ], function () {

            Route::get('/', [PaymentController::class, 'paymentLink'])->name('index');
            Route::get('/create', [PaymentController::class, 'paymentCreateLink'])->name('create');
            Route::post('/store', [PaymentController::class, 'paymentStoreLink'])->name('store');
            Route::get('/show/{companyId}/{voucherNumber}', [PaymentController::class, 'paymentShowLink'])->name('show')->withoutMiddleware(['auth']);
            Route::get('/show/{voucherNumber}', function () {
                return redirect()->route('payment.link.show', ['companyId' => 1, 'voucherNumber' => request()->voucherNumber]);
            })->withoutMiddleware(['auth']);
            Route::put('/update/{paymentId}', [PaymentController::class, 'paymentUpdateLink'])->name('update');
            Route::delete('/delete/{paymentId}', [PaymentController::class, 'paymentDeleteLink'])->name('delete');
            Route::post('/initiate', [PaymentController::class, 'paymentLinkInitiate'])->name('initiate')->withoutMiddleware(['auth']);
            Route::post('/webhook', [PaymentController::class, 'paymentLinkWebhook'])->name('webhook');
            Route::post('/reinitiate', [PaymentController::class, 'paymentLinkReInitiate'])->name('reinitiate')->withoutMiddleware(['auth']);
            Route::post('/import/invoice', [PaymentController::class, 'importFromInvoice'])->name('import.invoice');
            Route::post('/import/payment', [PaymentController::class, 'importFromPayment'])->name('import.payment');
            Route::post('/payment-activation/{paymentId}', [PaymentController::class, 'paymentLinkActivation'])->name('payment.activation');
            Route::post('/multi-initiate', [PaymentController::class, 'multiPaymentLinkInitiate'])->name('multi-initiate')->withoutMiddleware(['auth']);
        });
        Route::get('/tap-callback', [PaymentController::class, 'handleTapCallback'])->name('tap.callback')->withoutMiddleware(['auth']);

        Route::get('/uPayment-callback', [PaymentController::class, 'handleUPaymentCallback'])->name('uPayment.callback')->withoutMiddleware(['auth']);
        Route::get('/uPayment-error', [PaymentController::class, 'handleUPaymentError'])->name('uPayment.error')->withoutMiddleware(['auth']);
        Route::get('/uPayment-noti', [PaymentController::class, 'handleUPaymentNoti'])->name('uPayment.notifications')->withoutMiddleware(['auth']);

        Route::get('/hesabe-callback', [PaymentController::class, 'handleHesabeResponse'])->name('hesabe.response');
        Route::get('/hesabe-error', [PaymentController::class, 'handleHesabeFailure'])->name('hesabe.failure');

        Route::get('/knet-response', [PaymentController::class, 'handleKnetResponse'])->name('knet.response')->withoutMiddleware(['auth']);
        Route::get('/knet-error', [PaymentController::class, 'handleKnetError'])->name('knet.error')->withoutMiddleware(['auth']);
    });

    Route::group([
        'prefix' => 'clients',
        'as' => 'clients.',
    ], function () {
        Route::get('/', [ClientController::class, 'index'])->name('index');
        Route::get('/create', [ClientController::class, 'create'])->name('create');
        Route::post('/', [ClientController::class, 'store'])->name('store');
        Route::get('/{id}', [ClientController::class, 'show'])->name('show');
        // Route::get('/{id}/edit', [ClientController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ClientController::class, 'update'])->name('update');
        Route::post('/upload', [ClientController::class, 'import'])->name('upload');
        // Route::put('/{id}/change-agent', [ClientController::class, 'changeAgent'])->name('change-agent');
        Route::post('/refund/{id}', [ClientController::class, 'refund'])->name('refund');

        // Routes for Client Group Management
        Route::post('/group/add', [ClientController::class, 'addToGroup'])->name('group.add');
        Route::post('/group/remove', [ClientController::class, 'removeFromGroup'])->name('group.remove');
        Route::get('/{parentClientId}/subclients', [ClientController::class, 'getSubClients'])
            ->name('sub');
        Route::get('/{childClientId}/parent', [ClientController::class, 'getParClients'])
            ->name('parent');
        Route::put('/{id}/update-group', [ClientController::class, 'updateGroup'])->name('group.update');
        Route::get('/{id}/details', [ClientController::class, 'getDetails'])->name('details');
        Route::get('/{id}/agent', [ClientController::class, 'getAgent'])->name('get-agent');
        Route::get('/{id}/credit-balance', [ClientController::class, 'getCreditBalance']);
        Route::get('/{id}/credits', [ClientController::class, 'showCredit'])->name('credits')->withoutMiddleware(['auth']);

        // Assignment request routes
        Route::post('/request-assignment', [ClientController::class, 'requestAssignment'])->name('request-assignment');
        Route::get('/assignment/approve/{token}', [ClientController::class, 'approveAssignment'])->name('assignment.approve');
        Route::get('/assignment/deny/{token}', [ClientController::class, 'denyAssignment'])->name('assignment.deny');

        Route::group([
            'prefix' => 'ajax',
            'as' => 'ajax.',
        ], function () {
            Route::get('/search', [ClientController::class, 'searchClient'])->name('search');
        });
    });

    Route::group([
        'prefix' => 'exchange',
        'as' => 'exchange.',
    ], function () {
        Route::get('index', [CurrencyExchangeController::class, 'index'])->name('index');
        Route::post('store', [CurrencyExchangeController::class, 'store'])->name('store');
        Route::put('update-manual', [CurrencyExchangeController::class, 'updateManual'])->name('update.manual');
        Route::put('update-auto', [CurrencyExchangeController::class, 'updateAuto'])->name('update.auto');
        Route::put('update-method/{id}', [CurrencyExchangeController::class, 'updateMethod'])->name('update.method');
        Route::post('convert', [CurrencyExchangeController::class, 'convertFromSidebar'])->name('convert');
        Route::get('histories', [CurrencyExchangeController::class, 'allHistories'])->name('histories.all');
    });

    Route::get('update-rate', [SystemExchangeRateController::class, 'updateExchangeRate'])->name('update-rate');

    Route::post('credentials', [SupplierCredentialController::class, 'store'])->name('credentials.store');

    //CHARGES
    Route::group([
        'prefix' => 'charges',
        'as' => 'charges.',
    ], function () {
        Route::get('/', [ChargeController::class, 'index'])->name('index');
        Route::get('/create', [ChargeController::class, 'create'])->name('create');
        Route::post('/store', [ChargeController::class, 'store'])->name('store');
        Route::get('/edit/{id}', [ChargeController::class, 'edit'])->name('edit');
        Route::delete('/{id}', [ChargeController::class, 'destroy'])->name('destroy');
        Route::put('/{id}', [ChargeController::class, 'update'])->name('update');
        Route::get('/{id}', [ChargeController::class, 'show'])->name('show');
        Route::delete('/{id}', [ChargeController::class, 'destroy'])->name('destroy');
        Route::put('/{id}/credentials', [ChargeController::class, 'updateCredentials'])->name('credentials.update');
    });

    //Auto Billing
    Route::group([
        'prefix' => 'auto-billing',
        'as' => 'auto-billing.',
    ], function () {
        Route::get('/', [AutoBillingController::class, 'index'])->name('index');
        Route::post('/store', [AutoBillingController::class, 'store'])->name('store');
        Route::put('/update/{id}', [AutoBillingController::class, 'update'])->name('update');
        Route::delete('/{rule}', [AutoBillingController::class, 'destroy'])->name('destroy');
    });

    Route::group([
        'prefix' => 'supplier-company',
        'as' => 'supplier-company.',
    ], function () {
        Route::get('/edit/{id}', [SupplierCompanyController::class, 'edit'])->name('edit');
        Route::get('/activate', [SupplierCompanyController::class, 'activateSupplier'])->name('activate');
        Route::get('/deactivate', [SupplierCompanyController::class, 'deactivateSupplier'])->name('deactivate');
    });

    // NOIFICATIONS
    Route::group([
        'prefix' => 'notifications',
        'as' => 'notifications.',
    ], function () {
        Route::get('/', NotificationIndex::class)->name('index');
    });

    // CREDITS
    Route::group([
        'prefix' => 'credits',
        'as' => 'credits.',
    ], function () {
        Route::get('/', [CreditController::class, 'index'])->name('index');
        Route::get('/filter', [CreditController::class, 'filter'])->name('filter');
        Route::post('/use-credit-now/{invoice}/{invoicePartial}/{balanceCredit}', [CreditController::class, 'useCreditNow'])->name('useCreditNow');
        Route::post('/topup', [CreditController::class, 'creditTopup'])->name('topup');
    });

    Route::group([
        'prefix' => 'settings',
        'as' => 'settings.',
    ], function () {
        Route::get('/', [SettingController::class, 'index'])->name('index');

        Route::group([
            'prefix' => 'invoice',
            'as' => 'invoice.',
        ], function () {
            Route::post('/update-expiry', [SettingController::class, 'updateInvoiceExpiry'])->name('update-expiry');
        });

        Route::post('/save-tab', [SettingController::class, 'saveTab'])->name('save-tab');
        Route::get('/charges', [SettingController::class, 'getCharges'])->name('charges');
        Route::get('/payment-methods', [SettingController::class, 'getPaymentMethods'])->name('payment-methods');
        Route::get('/agent-charges', [SettingController::class, 'getAgentCharges'])->name('agent-charges');
        Route::post('/agent-charges', [SettingController::class, 'storeAgentCharge'])->name('agent-charges.store');
        Route::post('/agent-charges/bulk-update', [SettingController::class, 'bulkUpdateAgentCharges'])->name('agent-charges.bulk-update');
        Route::delete('/agent-charges/{id}', [SettingController::class, 'deleteAgentCharge'])->name('agent-charges.delete');
    });

    Route::group([
        'prefix' => 'user-settings',
        'as' => 'user-settings.',
    ], function () {
        Route::post('update', [UserSettingController::class, 'update'])->name('update');
        Route::post('get', [UserSettingController::class, 'getSetting'])->name('get');
    });

    //Payment Method
    Route::group([
        'prefix' => 'payment-method',
        'as' => 'payment-method.',
    ], function () {
        Route::get('/', [PaymentMethodController::class, 'index'])->name('index');
        Route::get('/{id}', [PaymentMethodController::class, 'show'])->name('show');
        Route::put('/{id}', [PaymentMethodController::class, 'update'])->name('update');
        Route::delete('/{id}', [PaymentMethodController::class, 'destroy'])->name('destroy');
        Route::post('/set-group', [PaymentMethodController::class, 'setGroup'])->name('set-group');
        Route::post('/toggle-enable/{id}', [PaymentMethodController::class, 'toggleEnable'])->name('toggle-enable');
    });

    Route::group([
        'prefix' => 'reminder',
        'as' => 'reminder.',
    ], function () {
        Route::get('/', [ReminderController::class, 'index'])->name('index');
        Route::post('/reminders', [ReminderController::class, 'store'])->name('store');
        Route::post('/reminders/bulk', [ReminderController::class, 'bulk'])->name('bulk');
    });

    Route::group([
        'prefix' => 'hotel',
        'as' => 'hotel.',
    ], function () {

        Route::group([
            'prefix' => 'ajax',
            'as' => 'ajax.',
        ], function () {
            Route::get('/search', [HotelController::class, 'searchHotel'])->name('search');
        });

    });

}); // auth middleware end

Route::get('/download-pdf/{path}', function ($path) {
    $fullPath = 'city_travelers/'.$path;
    if (! Storage::exists($fullPath)) {
        abort(404, 'File not found');
    }

    return response()->file(storage_path('app/'.$fullPath));
})->where('path', '.*');

Route::get('/admin', [VersionController::class, 'login'])->name('version.login');
//VERSION
Route::get('/version', [VersionController::class, 'index'])->name('version.index');
Route::get('/version/{versionId}', [VersionController::class, 'edit'])->name('version.edit');
Route::post('/version', [VersionController::class, 'store'])->name('version.store');
Route::put('/version/update/{id}', [VersionController::class, 'update'])->name('version.update');
Route::post('/version/update/current', [VersionController::class, 'updateCurrent'])->name('version.current');
Route::post('/version/updateMaster', [VersionController::class, 'updateMaster'])->name('version.updateMaster');
Route::get('/current', [VersionController::class, 'getCurrent'])->name('version.getCurrent');

Route::get('/monitor-versions', [VersionController::class, 'monitorVersions']);

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

Route::group([
    'prefix' => 'receipt-voucher',
    'as' => 'receipt-voucher.',
], function () {
    Route::get('/', [ReceiptVoucherController::class, 'index'])->name('index');
    Route::get('/create', [ReceiptVoucherController::class, 'create'])->name('create');
    Route::post('/store', [ReceiptVoucherController::class, 'store'])->name('store');
    Route::get('/edit/{id}', [ReceiptVoucherController::class, 'edit'])->name('edit');
    Route::put('/update/{id}', [ReceiptVoucherController::class, 'update'])->name('update');
    Route::post('/approve/{id}', [ReceiptVoucherController::class, 'approve'])->name('approve');
    Route::get('/fetch-journals-by-date', [ReceiptVoucherController::class, 'fetchPaymentsByDate'])->name('fetchPaymentsByDate');
    Route::get('/fetch-journals-view', [ReceiptVoucherController::class, 'fetchJournalEntriesByIds'])->name('fetch-journals');
    Route::post('/{id}/decline-reconcile', [ReceiptVoucherController::class, 'declineReconcile'])->name('decline-reconcile');
    Route::post('/import', [ReceiptVoucherController::class, 'import'])->name('import');
    Route::get('/{companyId}/{voucherNumber}', [ReceiptVoucherController::class, 'show'])->name('show')->withoutMiddleware(['auth']);
});

Route::group([
    'prefix' => 'bank-payments',
    'as' => 'bank-payments.',
], function () {
    Route::get('/create', [BankPaymentController::class, 'create'])->name('create');
    Route::post('/store', [BankPaymentController::class, 'store'])->name('store');
    Route::get('/edit/{id}', [BankPaymentController::class, 'edit'])->name('edit');
    Route::put('/edit/{id}', [BankPaymentController::class, 'update'])->name('update');
    Route::get('/', [BankPaymentController::class, 'index'])->name('index');
    Route::get('/fetch-journals-by-date', [BankPaymentController::class, 'fetchPaymentsByDate'])->name('fetchPaymentsByDate');
    Route::get('/fetch-journals-view', [BankPaymentController::class, 'fetchJournalEntriesByIds'])->name('fetch-journals');
    Route::post('/{id}/decline-reconcile', [BankPaymentController::class, 'declineReconcile'])->name('decline-reconcile');
});

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
route::get('/todolist', [ToDoListController::class, 'index'])->name('todolist.index');
route::post('/todolist', [ToDoListController::class, 'store'])->name('todolist.store');
route::get('/todolist/{id}', [ToDoListController::class, 'show'])->name('todolist.show');
route::get('/todolist/{id}/edit', [ToDoListController::class, 'edit'])->name('todolist.edit');

// Route to show the test form
Route::get('/payment/test', function () {
    return view('payment_test');
});

Route::match(['get', 'post'], '/payments/callback', [PaymentController::class, 'handleMyFatoorahCallback'])->name('payments.callback');
Route::match(['get', 'post'], '/payments/error', [PaymentController::class, 'handleMyFatoorahError'])->name('payments.error');

Route::get('docs/magic-webhook', [SupplierController::class, 'magicReserveWebhookDocs'])->name('magic-webhook-docs');
Route::get('/docs/developer', function () {
    return view('docs.developer-documentation');
})->name('docs.developer-documentation');
Route::get('/docs/n8n', [\App\Http\Controllers\Docs\N8nDocumentationController::class, 'hub'])->name('docs.n8n-hub');
Route::get('/docs/n8n-user-guide', [\App\Http\Controllers\Docs\N8nDocumentationController::class, 'userGuide'])->name('docs.n8n-user-guide');
Route::get('/docs/n8n-processing', [\App\Http\Controllers\Docs\N8nDocumentationController::class, 'index'])->name('docs.n8n-processing');
Route::get('/docs/n8n-complete', [\App\Http\Controllers\Docs\N8nDocumentationController::class, 'complete'])->name('docs.n8n-complete');
Route::get('/docs/n8n-changelog', [\App\Http\Controllers\Docs\N8nDocumentationController::class, 'changelog'])->name('docs.n8n-changelog');
Route::get('/docs/n8n-testing', [\App\Http\Controllers\N8nTestingDocumentationController::class, 'index'])->name('docs.n8n-testing');
Route::get('/docs/postman/download', function () {
    $filePath = resource_path('postman/Task_Webhook_API.postman_collection.json');
    if (! file_exists($filePath)) {
        abort(404, 'Postman collection file not found');
    }

    return response()->download($filePath, 'Task_Webhook_API.postman_collection.json');
})->name('docs.postman.download');

Route::post('/whatsapp/sendToResayilSimple', [WhatsappController::class, 'sendToResayilSimple'])->name('whatsapp.sendToResayilSimple');
Route::post('/webhook/resayil', [WhatsappController::class, 'handleResayilWebhook'])->name('whatsapp.resayil-webhook');

Route::group([
    'prefix' => 'resayil',
    'as' => 'resayil.',
], function () {
    Route::post('/share-invoice', [ResayilController::class, 'shareInvoiceLink'])->name('share-invoice-link');
    Route::post('/share-payment-link', [ResayilController::class, 'sharePaymentLink'])->name('share-payment-link');
    Route::post('/share-partial-link', [ResayilController::class, 'shareInvoicePartialLink'])->name('share-partial-link');
});

Route::group([
    'prefix' => 'iata',
    'as' => 'iata.',
], function () {
    Route::post('/company-wallet', [DashboardController::class, 'iataCompanyWallet'])->name('company-wallet');
});

Route::group([
    'prefix' => 'supplier-procedures',
    'as' => 'supplier-procedures.',
], function () {
    Route::post('/{supplierId}', [SupplierProcedureController::class, 'store'])->name('store');
    Route::patch('/{procedureId}/activate', [SupplierProcedureController::class, 'activate'])->name('activate');
    Route::get('/{procedureId}', [SupplierProcedureController::class, 'show'])->name('show');
    Route::delete('/{procedureId}', [SupplierProcedureController::class, 'destroy'])->name('destroy');
});

Route::group([
    'prefix' => 'terms',
    'as' => 'terms.',
], function () {
    // Terms Templates
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TermController::class, 'index'])->name('index');
        Route::post('/', [TermController::class, 'store'])->name('store');
        Route::put('/{id}', [TermController::class, 'update'])->name('update');
        Route::delete('/{id}', [TermController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/set-default', [TermController::class, 'setDefault'])->name('set-default');
        Route::post('/{id}/toggle-active', [TermController::class, 'toggleActive'])->name('toggle-active');
    });
});

Route::get('/hesabe/get-payment/{token}', [PaymentController::class, 'getHesabePayment'])->name('hesabe.get-payment');

// Manual Intervention Dashboard (Phase 2 Wave 3 + Phase 4 Group B)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Manual Intervention
    Route::get('/manual-intervention', [\App\Http\Controllers\Admin\ManualInterventionController::class, 'index'])
        ->name('manual-intervention.index');
    Route::get('/manual-intervention/{log}', [\App\Http\Controllers\Admin\ManualInterventionController::class, 'show'])
        ->name('manual-intervention.show');
    Route::get('/manual-intervention/{log}/timeline', [\App\Http\Controllers\Admin\ManualInterventionController::class, 'timeline'])
        ->name('manual-intervention.timeline');
    Route::post('/manual-intervention/{log}/retry', [\App\Http\Controllers\Admin\ManualInterventionController::class, 'retry'])
        ->name('manual-intervention.retry');
    Route::post('/manual-intervention/{log}/resolve', [\App\Http\Controllers\Admin\ManualInterventionController::class, 'resolve'])
        ->name('manual-intervention.resolve');
    Route::post('/manual-intervention/{log}/escalate', [\App\Http\Controllers\Admin\ManualInterventionController::class, 'escalate'])
        ->name('manual-intervention.escalate');
    Route::post('/manual-intervention/bulk-retry', [\App\Http\Controllers\Admin\ManualInterventionController::class, 'bulkRetry'])
        ->name('manual-intervention.bulk-retry');
    Route::get('/manual-intervention-export/csv', [\App\Http\Controllers\Admin\ManualInterventionController::class, 'exportCsv'])
        ->name('manual-intervention.export-csv');

    // Error Dashboard (ERR-05)
    Route::get('/error-dashboard', [\App\Http\Controllers\Admin\ErrorDashboardController::class, 'index'])
        ->name('error-dashboard.index');
    Route::get('/error-dashboard/metrics', [\App\Http\Controllers\Admin\ErrorDashboardController::class, 'metrics'])
        ->name('error-dashboard.metrics');
});

// DOTW Audit Log Viewer (Phase 2 Plan 3)
Route::middleware(['auth', 'dotw_audit_access'])
    ->prefix('admin/dotw')
    ->name('admin.dotw.')
    ->group(function () {
        Route::get('audit-logs', fn () => view('admin.dotw.audit-logs'))
            ->name('audit-logs');
    });

// DOTW API Token Manager — Super Admin only (403 enforced in DotwApiTokenIndex::mount())
Route::middleware(['auth'])
    ->prefix('admin/dotw')
    ->name('admin.dotw.')
    ->group(function () {
        Route::get('api-tokens', fn () => view('admin.dotw.api-tokens'))
            ->name('api-tokens');
    });

require __DIR__.'/auth.php';
