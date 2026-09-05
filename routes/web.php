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
use App\Http\Controllers\LockManagementController;
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
use App\Models\Task;
use Illuminate\Support\Facades\Route;
use App\Models\Charge;
use App\Http\Controllers\ResayilAdminController;
use App\Http\Controllers\ResayilEmbedController;
use App\Http\Controllers\VoucherTemplateController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\PublicVoucherController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/ai-health-status', [DashboardController::class, 'aiHealthStatus'])->name('dashboard.ai-health');

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

    // Financial period locking is accounting-only: fold it into the
    // module.accounting gate even though its own policy (UserPolicy::manageLocks)
    // isn't company/module-scoped, so its UI stays consistently hidden.
    Route::group([
        'prefix' => 'lock-management',
        'as' => 'lock-management.',
        'middleware' => ['module:accounting'],
    ], function () {
        Route::get('/', [LockManagementController::class, 'index'])->name('index');
        Route::post('/lock-by-period', [LockManagementController::class, 'lockByPeriod'])->name('lock-by-period');
        Route::post('/lock-by-month', [LockManagementController::class, 'lockByMonth'])->name('lock-by-month');
        Route::post('/unlock-by-month', [LockManagementController::class, 'unlockByMonth'])->name('unlock-by-month');
    });

    // P2.5.E (p2_5-brief.md §P2.5.E) — single-record, dependency-aware unlock for ONE invoice
    // lives on the pre-existing `invoice.lock`/`invoice.unlock` routes (InvoiceController), not
    // here — see that route group below and InvoiceController::unlockInvoice()'s own docblock for
    // why the P2.5.E upgrade was applied in place rather than as a second, competing endpoint.

    // Admin users
    //
    // Fix 4 (pre-pilot defect list, spec correction PKG-1) investigation
    // result, 2026-08-25: the group-level 'role:admin' middleware below is
    // DELIBERATELY left commented out — restoring it as written would be a
    // *regression*, not a fix. It resolves via Spatie's RoleMiddleware
    // (bootstrap/app.php: 'role' => RoleMiddleware::class), which checks
    // $user->hasRole('admin') — a DIFFERENT authorization system from the
    // one this app actually uses everywhere else in this file (the
    // role_id integer column). Verified directly against the dev DB: a
    // real admin (role_id === Role::ADMIN) exists with no Spatie 'admin'
    // role assigned at all, and would be locked out by this line as
    // written. It would also wrongly block company-owner (Role::COMPANY)
    // access to routes below that intentionally allow it (users.edit,
    // users.role, users.updateInfo — see their inline checks).
    //
    // What actually guards this group today (verified per-route, not
    // assumed): users.index -> Gate::authorize('viewAny', User::class)
    // (Spatie permission 'view user'); companies.index, companiesnew.new,
    // companies.store, users.set-company -> inline
    // role_id === Role::ADMIN; users.edit, users.role, users.updateInfo ->
    // inline role_id in [ADMIN, COMPANY] (users.role and users.updateInfo
    // had NO check at all before this fix — closed here); users.create ->
    // intentionally open to any authenticated user (linked from
    // agents/branches/clients/companies views for company-scoped
    // self-service creation, not an admin-only action); company-invites.*
    // -> CompanyInviteController::authorizeAdmin() (role_id === ADMIN) on
    // every action, already correctly gated.
    Route::group([
        'prefix' => 'users',
        // 'as' => 'users.',
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

        // Company invites — admin only. Gate enforced in-controller via
        // CompanyInviteController::authorizeAdmin() (role_id === Role::ADMIN).
        Route::get('/company-invites', [\App\Http\Controllers\CompanyInviteController::class, 'index'])->name('company-invites.index');
        Route::post('/company-invites', [\App\Http\Controllers\CompanyInviteController::class, 'store'])->name('company-invites.store');
        Route::post('/company-invites/{invite}/cancel', [\App\Http\Controllers\CompanyInviteController::class, 'cancel'])->name('company-invites.cancel');
        Route::post('/company-invites/{invite}/resend', [\App\Http\Controllers\CompanyInviteController::class, 'resend'])->name('company-invites.resend');
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
    // AP-2: module-gated as defence in depth alongside AgentPolicy's own
    // Modules::AGENT_PROFIT check (see AgentPolicy / RequiresCompanyModule).
    Route::group([
        'prefix' => 'agents',
        'as' => 'agents.',
        'middleware' => ['module:agent_profit'],
    ], function () {
        Route::get('/', [AgentController::class, 'index'])->name('index');
        // Route::get('/new', [AgentController::class, 'new'])->name('new');
        Route::post('/', [AgentController::class, 'store'])->name('store');
        Route::get('/upload', [AgentController::class, 'upload'])->name('upload');
        Route::post('/upload', [AgentController::class, 'import'])->name('import');
        Route::get('/{id}', [AgentController::class, 'show'])->name('show');
        // AP-7: 'edit' route removed -- AgentController::edit is fully commented out
        // (replaced by the detail-page modal), so hitting this route fatally errored.
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
        // Step 4 (plan section 14.13): removed. GET /tasks/voucher pointed at
        // TaskController::voucher, a method that never existed -- 500'd on
        // every hit, zero real usage (grepped: no route('tasks.voucher') call
        // anywhere in app/resources). The real per-task voucher UI now lives
        // at GET vouchers/task/{task} (VoucherController::indexForTask), a
        // fresh name rather than reclaiming this one, since the dead symbol
        // carried no id and did not fit an issue-a-voucher action shape.
        Route::put('/update/{id}', [TaskController::class, 'update'])->name('update');
        Route::post('/upload', [TaskController::class, 'upload'])->name('upload');
        Route::get('/agents/{agentId}', [TaskController::class, 'getAgentTask'])->name('agent');
        Route::get('/all/queue', [TaskController::class, 'queue'])->name('queue');
        Route::get('/supplier-task/{id}', [TaskController::class, 'supplierTask'])->name('supplier');
        Route::post('/agent/upload', [TaskController::class, 'supplierTaskForAgent'])->name('agent.upload');
        Route::get('/get-tbo/{companyId}', [TaskController::class, 'getTboTask'])->name('get-tbo');
        // Step 4 item 5 (plan section 11.3, section 15 V3): CLOSED. No usage of
        // tasks.pdf.flight found anywhere outside the authenticated admin UI
        // (resources/views/invoice/index.blade.php's staff-facing links), so
        // auth is safely required again; flightPdf() below now also scopes
        // its query by company_id.
        Route::get('/pdf/flight/{taskId}', [TaskController::class, 'flightPdf'])->name('pdf.flight')->middleware('throttle:60,1');
        // Step 4 item 5: NOT closed, deliberately, and flagged for the owner.
        // grep -rn 'tasks/pdf' found TWO live production paths that build this
        // exact unauthenticated URL and hand it to a real end customer with no
        // Laravel session -- both inside files this session is forbidden to
        // edit (accounting boundary, plan section 2):
        //   - PaymentController::registerTBOBookingAsTask() (lines ~997, ~1096)
        //     returns 'hotel_voucher_url' => route('tasks.pdf.hotel', ...) in the
        //     JSON response of the fully public POST /api/payment/register-tbo-booking
        //     webhook (routes/api.php:55, no auth middleware at all).
        //   - InvoiceController::autoGenerateInvoice() (line ~6346) posts the same
        //     URL as 'hotel_voucher' to an n8n webhook alongside the client's own
        //     phone number and a WhatsApp message -- i.e. this URL is actively
        //     forwarded to real clients today.
        // Requiring auth here would break both flows for every real customer
        // mid-booking. Per this feature's own binding boundary ("If you think
        // you must change one, STOP and report instead"), this route stays
        // public+unscoped and only gets a throttle as a partial mitigation. The
        // real fix is for whoever owns PaymentController/InvoiceController to
        // repoint those two call sites at the new tokenised travel-voucher.show
        // route BEFORE this one can safely require auth -- reported to the owner,
        // not silently worked around.
        Route::get('/pdf/hotel/{taskId}', [TaskController::class, 'hotelPdf'])->name('pdf.hotel')->withoutMiddleware(['auth'])->middleware('throttle:60,1');
        Route::get('/pdf/receipt/{taskId}', [TaskController::class, 'receiptPdf'])->name('pdf.receipt');
        Route::get('/pdf/receipt/{taskId}/download', [TaskController::class, 'receiptPdfDownload'])->name('pdf.receipt.download');
        Route::post('/upload/passport', [TaskController::class, 'clientPassport'])->name('upload.passport');
        Route::delete('/{id}', [TaskController::class, 'destroy'])->name('destroy');
        Route::post('/columns/save', [TaskController::class, 'saveColumnPrefs'])->name('columns.save');
        Route::post('/bulk-update', [TaskController::class, 'bulkUpdate'])->name('bulkUpdate');
        Route::post('/tasks/bulk-update', [TaskController::class, 'bulkUpdate'])->name('bulk-update');
        // W6.B (w6-brief.md "## Kinds" 5, `POST /tasks/bulk-void`; TaskPolicy::bulkVoid()).
        Route::post('/bulk-void', [TaskController::class, 'bulkVoid'])->name('bulk-void');

        // W6.U -- Task actions (void / void-with-fee / reissue), per w6-brief.md "W6.U -- UI".
        // Placed ahead of the '/{task}/details' style routes below so a literal 'follow-up'
        // segment never gets swallowed by a '{task}' wildcard route registered earlier.
        Route::get('/follow-up', [TaskController::class, 'followUp'])->name('follow-up');
        Route::get('/follow-up/count', [TaskController::class, 'followUpCount'])->name('follow-up.count');
        Route::post('/{task}/follow-up/issue', [TaskController::class, 'followUpIssue'])->name('follow-up.issue');
        Route::post('/{task}/follow-up/extend', [TaskController::class, 'followUpExtend'])->name('follow-up.extend');
        Route::post('/{task}/follow-up/cancel', [TaskController::class, 'followUpCancel'])->name('follow-up.cancel');
        Route::post('/{task}/follow-up/note', [TaskController::class, 'followUpNote'])->name('follow-up.note');

        Route::get('/{task}/void-fee-preview', [TaskController::class, 'voidFeePreview'])->name('void-fee-preview');
        Route::post('/{task}/void', [TaskController::class, 'voidSingle'])->name('void');
        Route::get('/{task}/reissue-preview', [TaskController::class, 'reissuePreview'])->name('reissue-preview');
        Route::post('/{task}/reissue', [TaskController::class, 'reissueSingle'])->name('reissue');
        Route::post('/pending-actions/{pendingAction}/approve', [TaskController::class, 'approveFeeOverride'])->name('pending-actions.approve');
        Route::post('/pending-actions/{pendingAction}/reject', [TaskController::class, 'rejectFeeOverride'])->name('pending-actions.reject');
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
        Route::get('/resolve-whatsapp-group', [SupplierController::class, 'resolveWhatsappGroup'])->name('resolve-wa-group');
        Route::get('/{suppliersId}/export-excel', [SupplierController::class, 'exportExcel'])->name('suppliers.export.excel');
        Route::get('/{suppliersId}/export-pdf', [SupplierController::class, 'exportPdf'])->name('suppliers.export.pdf');
        Route::post('/store', [SupplierController::class, 'store'])->name('store');
        Route::put('/update/{id}', [SupplierController::class, 'update'])->name('update');
        Route::post('/{supplierCompany}/update-surcharges', [SupplierController::class, 'updateSurcharges'])->name('update.surcharges');

        // W6.U -- Supplier status map + charge rule editor (owner additions, 2026-08-28).
        // Literal segments registered ahead of the '/{suppliersId}' wildcard below.
        Route::get('/status-map/defaults', [SupplierController::class, 'statusMapDefaults'])->name('status-map.defaults');
        Route::post('/status-map/test', [SupplierController::class, 'statusMapTest'])->name('status-map.test');
        Route::post('/status-map/create-from-unmapped', [SupplierController::class, 'statusMapCreateFromUnmapped'])->name('status-map.create-from-unmapped');
        Route::put('/status-map/{statusMap}', [SupplierController::class, 'statusMapUpdate'])->name('status-map.update');
        Route::post('/status-map/{statusMap}/deactivate', [SupplierController::class, 'statusMapDeactivate'])->name('status-map.deactivate');
        Route::post('/{supplier}/status-map', [SupplierController::class, 'statusMapStore'])->name('status-map.store');

        Route::get('/charge-rules/defaults', [SupplierController::class, 'chargeRuleDefaults'])->name('charge-rules.defaults');
        Route::post('/charge-rules/test', [SupplierController::class, 'chargeRuleTestPreview'])->name('charge-rules.test');
        Route::post('/charge-rules/create-default', [SupplierController::class, 'chargeRuleStoreDefault'])->name('charge-rules.create-default');
        Route::put('/charge-rules/{chargeRule}', [SupplierController::class, 'chargeRuleUpdate'])->name('charge-rules.update');
        Route::post('/charge-rules/{chargeRule}/deactivate', [SupplierController::class, 'chargeRuleDeactivate'])->name('charge-rules.deactivate');
        Route::post('/{supplier}/charge-rules', [SupplierController::class, 'chargeRuleStore'])->name('charge-rules.store');

        // T14 -- Supplier bank details per currency (accounting-builds PLAN.md §5 T14; L18).
        // Literal segments registered ahead of the '/{suppliersId}' wildcard below, same
        // convention as status-map/charge-rules above.
        Route::put('/bank-details/{bankDetail}', [SupplierController::class, 'bankDetailUpdate'])->name('bank-details.update');
        Route::post('/bank-details/{bankDetail}/deactivate', [SupplierController::class, 'bankDetailDeactivate'])->name('bank-details.deactivate');
        Route::post('/{supplier}/bank-details', [SupplierController::class, 'bankDetailStore'])->name('bank-details.store');

        Route::get('/{suppliersId}', [SupplierController::class, 'show'])->name('show');
        // Ledger/journal endpoints inside the otherwise task_uploader-mapped
        // 'suppliers' prefix — gate only these two, not the whole group.
        Route::get('/total-ledger/{supplierId}/date/{endDate}', [SupplierController::class, 'getTotalDebitCredit'])->name('total-ledger')->middleware('module:accounting');
        Route::get('/magic/get', [SupplierController::class, 'getMagicHoliday'])->name('magic.get');
        Route::get('/magic/credential', [SupplierController::class, 'getClientCredential'])->name('magic-credential');
        Route::get('/magic/request', [SupplierController::class, 'makeApiRequest'])->name('magic-request');
        Route::get('/magic/callback', [SupplierController::class, 'handleAuthorizationCallback'])->name('magic-callback');
        Route::get('/magic/provider', [SupplierController::class, 'redirectToAuthorization'])->name('magic-provider');
        Route::get('/magic/webhook-initiate/{id}', [SupplierController::class, 'magicReserveWebhook'])->name('magic-webhook');
        Route::get('/ledger-by-date/{supplierId}', [SupplierController::class, 'ledgerByDateRange'])->name('suppliers.ledger-by-date')->middleware('module:accounting');
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
        'middleware' => ['module:accounting'],
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
        Route::get('/opening-balances', [CoaController::class, 'openingBalances'])->name('opening-balances');
        Route::post('/opening-balances', [CoaController::class, 'saveOpeningBalances'])->name('opening-balances.save');
        // COA UI lane (2026-08-31, scope item 3): disable/enable toggle on accounts.disabled.
        // Additive, same group/middleware as every other coa.* mutation above.
        Route::post('/{id}/toggle-disabled', [CoaController::class, 'toggleDisabled'])->name('toggle-disabled');
    });

    // AccountingController — company ledger summary/transaction browser + the
    // payable/receivable entry forms and their AJAX account-picker endpoints.
    // All of it is accounting-only, so gate the whole block as one group.
    Route::middleware(['module:accounting'])->group(function () {
        //    / Route::get('/accounting-summary', [AccountingController::class, 'index'])->name('accounting.index');
        Route::get('/accounting-summary', [AccountingController::class, 'showCompanySummary'])->name('accounting.index');
        Route::get('/transaction', [AccountingController::class, 'index'])->name('accounting.transaction');
        Route::post('/filter-ledgers', [AccountingController::class, 'filterLedgers']);
        Route::post('/export-excel', [AccountingController::class, 'exportExcel']);

        Route::get('/payable-details/payable-create', [AccountingController::class, 'createPayableDetail'])->name('payable-details.payable-create');
        Route::post('/payable-details/payable-store', [AccountingController::class, 'storePayableDetail'])->name('payable-details.payable-store');
        Route::get('/receivable-details/receivable-create', [AccountingController::class, 'createReceivableDetail'])->name('receivable-details.receivable-create');
        Route::post('/receivable-details/receivable-store', [AccountingController::class, 'storeReceivableDetail'])->name('receivable-details.receivable-store');
        // W7.A: reversal for the manual-JV screens above -- PostingService::reverse() is the sole
        // undo mechanism, matching every other engine feeder (see AccountingController's own docblock).
        Route::post('/manual-journal/{transaction}/reverse', [AccountingController::class, 'reverseManualJournal'])->name('manual-journal.reverse');
        Route::get('/get-accounts-by-company-payable', [AccountingController::class, 'getAccountsByCompanyPayable'])->name('get.accounts.by.company.payable');
        Route::get('/get-accounts-by-company-receivable', [AccountingController::class, 'getAccountsByCompanyReceivable'])->name('get.accounts.by.company.receivable');
        Route::get('/get-branches-by-company', [AccountingController::class, 'getBranchByCompany'])->name('get.branches.by.company');
        Route::get('/get-agents-by-branch-company', [AccountingController::class, 'getAgentByBranchCompany'])->name('get.agents.by.branch.company');
        Route::get('/get-suppliers-by-company', [AccountingController::class, 'getSupplierByCompany'])->name('get.suppliers.by.company');
        Route::get('/get-agents-clients-by-company', [AccountingController::class, 'getAgentClientByCompany'])->name('get.agents.clients.by.company');
        Route::get('/get-bank-accounts-by-company', [AccountingController::class, 'getBankAccountByCompany'])->name('get.bank.accounts.by.company');
        Route::get('/get-invoices-by-JournalEntry', [AccountingController::class, 'getInvoicesByJournalEntry'])->name('get.invoices.by.JournalEntry');
    });

    // accounting-builds T10 (Lane G) — the fixed-asset register's HTTP surface. Per-action
    // authorization is Gate::authorize('view'|'manage', FixedAsset::class) inside the controller
    // itself (App\Policies\FixedAssetPolicy) — the route middleware only gates module visibility,
    // the same split every other accounting screen's routes in this file already use. The
    // '/depreciate' and '/create' routes are declared BEFORE the '/{fixedAsset}' wildcard so
    // neither literal segment is ever swallowed by route-model-binding.
    Route::prefix('accounting/fixed-assets')->name('accounting.fixed-assets.')->middleware(['module:accounting'])->group(function () {
        Route::get('/depreciate', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'depreciateForm'])->name('depreciate');
        Route::post('/depreciate', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'depreciateRun'])->name('depreciate.run');
        Route::get('/', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'store'])->name('store');
        Route::get('/{fixedAsset}/edit', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'edit'])->name('edit');
        Route::put('/{fixedAsset}', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'update'])->name('update');
        Route::post('/{fixedAsset}/capitalise', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'capitalise'])->name('capitalise');
        Route::post('/{fixedAsset}/dispose', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'dispose'])->name('dispose');
        Route::get('/{fixedAsset}', [\App\Http\Controllers\Accounting\FixedAssetController::class, 'show'])->name('show');
    });

    // P2.5.C (p2_5-brief.md §P2.5.C) — period-control screen. Gated the same
    // 'module:accounting' way as every other accounting screen in this file; per-action
    // authorization is Gate::authorize('view'|'close'|'reopen', AccountingPeriod::class) inside
    // PeriodController itself (App\Policies\AccountingPeriodPolicy).
    Route::prefix('accounting/periods')->name('accounting.periods.')->middleware(['module:accounting'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Accounting\PeriodController::class, 'index'])->name('index');
        Route::post('/checklist', [\App\Http\Controllers\Accounting\PeriodController::class, 'checklist'])->name('checklist');
        Route::post('/close', [\App\Http\Controllers\Accounting\PeriodController::class, 'close'])->name('close');
        Route::post('/reopen', [\App\Http\Controllers\Accounting\PeriodController::class, 'reopen'])->name('reopen');
        Route::post('/close-year', [\App\Http\Controllers\Accounting\PeriodController::class, 'closeYear'])->name('close-year');
    });

    // P2.5.F (p2_5-brief.md §P2.5.F) — Accounting Log Center. Route name is a literal contract:
    // App\Services\Accounting\AuditLogLinker::forSubject() (shipped in P2.5.E, ahead of this
    // screen) resolves deep links against the exact name 'accounting.audit-log.index'.
    // Authorization is Gate::authorize('view', AccountingAuditLog::class) inside the Livewire
    // component's own mount() (App\Policies\AccountingAuditLogPolicy) — the route middleware only
    // gates module visibility, same split PeriodController's group above uses.
    Route::get('accounting/audit-log', fn () => view('accounting.audit-log.index'))
        ->middleware(['module:accounting'])
        ->name('accounting.audit-log.index');

    // COA UI lane (2026-08-31): purpose-mapping repair screen — closes the audit finding that
    // system_accounts purpose mappings have no UI, so a gap only ever surfaced at runtime as an
    // uncaught UnmappedPurposeException. Same split as every other accounting screen in this file:
    // the route middleware only gates module visibility; per-action authorization is
    // Gate::authorize('viewAny'|'update', CoaCategory::class) inside the Livewire component itself
    // (App\Policies\COAPolicy — the same policy CoaController's own coa.* screens already use).
    Route::get('accounting/purpose-mapping', fn () => view('accounting.purpose-mapping.index'))
        ->middleware(['module:accounting'])
        ->name('accounting.purpose-mapping.index');

    // P2.5.I (p2_5-brief.md §P2.5.I) -- reminder log screen. Same split as every other screen in
    // this file: the route middleware only gates module visibility, per-action authorization is
    // Gate::authorize('view'|'manage', Reminder::class) inside the Livewire component itself
    // (App\Policies\ReminderPolicy). The per-kind on/off + channel + offsets + quiet-hours form
    // lives in the existing Accounting settings tab (resources/views/settings/partial/
    // accounting.blade.php), not a second standalone screen -- this route is deep-linked from
    // there.
    Route::get('accounting/reminders-log', fn () => view('accounting.reminders-log.index'))
        ->middleware(['module:accounting'])
        ->name('accounting.reminders.log');

    // P2.5.G (p2_5-brief.md §P2.5.G) — Reconciliation Center v0. Per-action authorization is
    // Gate::authorize('view'|'manage', ReconciliationProposal::class) inside the controller itself
    // (App\Policies\ReconciliationProposalPolicy) — the route middleware only gates module
    // visibility, same split PeriodController/AuditLogIndex's own routes already use.
    Route::prefix('accounting/reconciliation')->name('accounting.reconciliation.')->middleware(['module:accounting'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'index'])->name('index');
        Route::get('/grid', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'grid'])->name('grid');
        Route::get('/rows/{rowKey}', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'rowDetail'])->name('row-detail')->where('rowKey', '.*');
        Route::post('/proposals/{proposal}/approve', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'approveProposal'])->name('proposals.approve');
        Route::post('/proposals/{proposal}/reject', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'rejectProposal'])->name('proposals.reject');
        Route::post('/match', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'manualMatch'])->name('match');
        Route::post('/unmatch', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'manualUnmatch'])->name('unmatch');
        Route::post('/fix-drafts', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'createFixDraft'])->name('fix-drafts.create');
        Route::post('/fix-drafts/{fixDraft}/post', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'postFixDraft'])->name('fix-drafts.post');
        Route::post('/fix-drafts/{fixDraft}/discard', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'discardFixDraft'])->name('fix-drafts.discard');
        Route::post('/run', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'runNow'])->name('run');
        Route::get('/run-status', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'runStatus'])->name('run-status');

        // accounting-builds T8 (Lane E) — DOTW supplier-statement reconciliation.
        Route::get('/supplier-statements', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'supplierStatements'])->name('supplier-statements.index');
        Route::post('/supplier-statements', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'importSupplierStatement'])->name('supplier-statements.import');
        Route::post('/supplier-statements/{supplierStatementImport}/match', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'matchSupplierStatement'])->name('supplier-statements.match');
        Route::get('/supplier-statements/{supplierStatementImport}/exceptions', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'supplierStatementExceptions'])->name('supplier-statements.exceptions');

        // accounting-builds T7 (Lane D) — "Record settlement" panel.
        Route::get('/bank-accounts', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'bankAccounts'])->name('bank-accounts');
        Route::get('/settlements', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'settlements'])->name('settlements');
        Route::post('/settlements', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'recordSettlement'])->name('settlements.record');

        // accounting-builds T9 (Wave 2) — bank statement import + auto-match.
        Route::get('/bank-statements', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'bankStatements'])->name('bank-statements.index');
        Route::post('/bank-statements', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'importBankStatement'])->name('bank-statements.import');
        Route::post('/bank-statements/{bankStatementImport}/match', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'matchBankStatement'])->name('bank-statements.match');
        Route::get('/bank-statements/{bankStatementImport}/exceptions', [\App\Http\Controllers\Accounting\ReconciliationController::class, 'bankStatementExceptions'])->name('bank-statements.exceptions');
    });

    // P2.5.H (p2_5-brief.md §P2.5.H) — client/supplier/agent statement (open_items|full_activity)
    // + client-facing statement PDF. Per-action authorization is Gate::authorize('view', $party)
    // inside the controller itself (ClientPolicy/SupplierPolicy/AgentPolicy) — the route
    // middleware only gates module visibility, same split every other accounting screen's routes
    // in this file already use.
    Route::prefix('accounting/statements')->name('accounting.statements.')->middleware(['module:accounting'])->group(function () {
        Route::get('/{partyType}/{partyId}', [\App\Http\Controllers\Accounting\StatementController::class, 'show'])
            ->where('partyType', 'client|supplier|agent')
            ->where('partyId', '[0-9]+')
            ->name('show');
        Route::get('/{partyType}/{partyId}/pdf', [\App\Http\Controllers\Accounting\StatementController::class, 'pdf'])
            ->where('partyType', 'client|supplier|agent')
            ->where('partyId', '[0-9]+')
            ->name('pdf');
    });

    // accounting-builds T6 (L10) — Statement of Changes in Equity, a read-layer report reusing
    // TrialBalanceService. Per-action authorization mirrors ReportController::trialBalance()'s own
    // role_id gate (ADMIN/COMPANY/ACCOUNTANT) inside the controller itself — the route middleware
    // only gates module visibility, same split every other accounting screen's routes in this file
    // already use.
    Route::prefix('accounting/reports')->name('accounting.reports.')->middleware(['module:accounting'])->group(function () {
        Route::get('/equity-changes', [\App\Http\Controllers\Accounting\EquityStatementController::class, 'show'])->name('equity-changes');
        Route::get('/equity-changes/export', [\App\Http\Controllers\Accounting\EquityStatementController::class, 'export'])->name('equity-changes.export');
    });

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
    // Route name deconflicted from the vendor package's own 'myfatoorah.callback'
    // (vendor/myfatoorah/laravel-package registers GET myfatoorah/callback under that
    // exact name via its ServiceProvider) — pre-existing on feat/travelerp-launch before
    // this merge (composer.json is identical both sides; this collision predates it),
    // but broke `php artisan route:cache`, one of this merge's required gates.
    Route::get('callback', [MyFatoorahController::class, 'callback'])->name('app.myfatoorah.callback');
    Route::get('/myfatoorah/pay-now', [MyFatoorahController::class, 'index'])->name('myfatoorah.paynow');
    Route::get('checkout', [MyFatoorahController::class, 'checkout'])->name('myfatoorah.checkout');

    Route::get('suppliers/{supplier}/exchange-rates', [SupplierController::class, 'exchangeRates'])->name('suppliers.exchange-rates');
    Route::post('suppliers/{supplier}/exchange-rates', [SupplierController::class, 'updateExchangeRates'])->name('suppliers.exchange-rates.update');
    //TRANSACTION
    Route::group([
        'prefix' => 'transactions',
        'as' => 'transactions.',
        'middleware' => ['module:accounting'],
    ], function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
    });

    //JOURNAL ENTRY
    // NOTE: prod additionally has a 'journal-entries/all' route (JournalEntryController@all)
    // not present in this local file (see prod-vs-local drift notes). Because this whole
    // group carries the module gate, that prod-only route inherits it automatically once
    // it's added here — no separate patch needed for it.
    Route::group([
        'prefix' => 'journal-entries',
        'as' => 'journal-entries.',
        'middleware' => ['module:accounting'],
    ], function () {
        Route::get('/all', [JournalEntryController::class, 'all'])->name('all');
        Route::get('/{transactionId}', [JournalEntryController::class, 'index'])->name('index');
        Route::get('/{accountId}/account', [JournalEntryController::class, 'show'])->name('show');
        Route::get('/{accountId}/export/pdf', [JournalEntryController::class, 'exportPdf'])->name('export.pdf');
    });

    Route::group([
        'prefix' => 'reports',
        'as' => 'reports.',
    ], function () {
        // NOTE: this prefix mixes pure accounting reports with the
        // Agent Profit Calculation module's report (profit-agent) and
        // the Task Uploader/Customer CRM/Payment Gateway modules' reports
        // (tasks/client/payment-gateways) — those must stay reachable for
        // package clients, so module:accounting is chained per-route below
        // instead of applied to the whole group.
        Route::get('/', [ReportController::class, 'index'])->name('index')->middleware('module:accounting');
        // AP-9: 'agent' route removed -- ReportController::agentReport() was a dead
        // stub (unconditionally returned the maintenance view; the unreachable code
        // beneath it queried journal_entries directly, which is not package-safe).
        Route::match(['get', 'post'], '/client', [ReportController::class, 'clientReport'])->name('client');
        Route::match(['get', 'post'], '/client/pdf', [ReportController::class, 'clientReportPdf'])->name('client.pdf');
        Route::get('/clientmgmnt', [ReportController::class, 'clientMgmnt'])->name('clientmgmnt');
        Route::get('/performance', [ReportController::class, 'performance'])->name('performance');
        Route::get('/summary', [ReportController::class, 'summary'])->name('summary')->middleware('module:accounting');
        Route::get('/accsummary', [ReportController::class, 'accsummary'])->name('accsummary')->middleware('module:accounting');
        Route::get('/unpaid-report', [ReportController::class, 'unpaidaccountsPayableReceivableReport'])->name('unpaid-report')->middleware('module:accounting');
        Route::get('/paid-report', [ReportController::class, 'paidaccountsPayableReceivableReport'])->name('paid-report')->middleware('module:accounting');
        Route::get('/payable_supplier', [ReportController::class, 'payableSupplier'])->name('payable-supplier')->middleware('module:accounting');
        Route::get('/profit-agent', [ReportController::class, 'profitAgent'])->name('profit-agent')->middleware('module:agent_profit');
        Route::get('/total-receivable', [ReportController::class, 'receivable'])->name('total-receivable')->middleware('module:accounting');
        Route::get('/total-bank', [ReportController::class, 'totalBank'])->name('total-bank')->middleware('module:accounting');
        Route::get('/gateway-receivable', [ReportController::class, 'gatewayReceivable'])->name('gateway-receivable')->middleware('module:accounting');
        Route::get('/account-list', [ReportController::class, 'getAccounts'])->name('account-list')->middleware('module:accounting');
        Route::get('/acc-reconcile', [ReportController::class, 'accountsReconciliationReport'])->name('acc-reconcile')->middleware('module:accounting');
        // settlementsReport() is the *bank* settlements report (payment-gateway
        // funds settling to the bank — see its 'Settles to Bank' query), not
        // the agent-loss settlement feature; it's accounting content despite
        // the ability name 'viewSettlement'.
        Route::get('/settlements', [ReportController::class, 'settlementsReport'])->name('settlements')->middleware('module:accounting');
        Route::get('/settlements/entries/by-date', [ReportController::class, 'journalEntriesByDate'])
            ->name('settlements.entries.by_date')->middleware('module:accounting');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss')->middleware('module:accounting');
        // P2.5.D (p2_5-brief.md §P2.5.D): deferred revenue schedule, grouped by release month.
        Route::get('/deferred-revenue-schedule', [ReportController::class, 'deferredRevenueSchedule'])->name('deferred-revenue-schedule')->middleware('module:accounting');
        Route::get('/creditors', [ReportController::class, 'creditors'])->name('creditors')->middleware('module:accounting');
        Route::get('/creditors/pdf', [ReportController::class, 'creditorsPdf'])->name('creditors.pdf')->middleware('module:accounting');
        Route::match(['get', 'post'], '/daily-sales', [ReportController::class, 'dailySalesReport'])->name('daily-sales')->middleware('module:accounting');
        // dailySalesPdf()/dailySalesPdfDownload() are currently dead code
        // (target methods commented out in ReportController — pre-existing,
        // not touched here); gating them anyway means a package client hits
        // our 404 instead of ever reaching that broken controller method.
        Route::get('/daily-sales/pdf', [ReportController::class, 'dailySalesPdf'])->name('daily-sales.pdf')->middleware('module:accounting');
        Route::get('/daily-sales/pdf/download', [ReportController::class, 'dailySalesPdfDownload'])->name('daily-sales.pdf.download')->middleware('module:accounting');
        Route::match(['get', 'post'], '/tasks', [ReportController::class, 'tasksReport'])->name('tasks');
        Route::match(['get', 'post'], '/tasks/pdf', [ReportController::class, 'tasksReportPdf'])->name('tasks.pdf');
        // payment-gateways report is gated by its own ReportPolicy::viewPaymentGatewaysReport
        // check already (payment_gateway package module) — left out of module:accounting.
        Route::match(['get', 'post'], '/payment-gateways', [ReportController::class, 'paymentGateways'])->name('payment-gateways');
        Route::get('/payment-gateways/pdf', [ReportController::class, 'paymentGatewaysReportPdf'])->name('payment-gateways.pdf');

        // Trial Balance
        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance')->middleware('module:accounting');
        Route::get('/trial-balance/pdf', [ReportController::class, 'trialBalancePdf'])->name('trial-balance.pdf')->middleware('module:accounting');
        Route::get('/trial-balance/export', [ReportController::class, 'trialBalanceExport'])->name('trial-balance.export')->middleware('module:accounting');
        Route::get('/trial-balance/validation', [ReportController::class, 'trialBalanceValidation'])->name('trial-balance.validation')->middleware('module:accounting');

        Route::group([
            'prefix' => 'ajax',
            'as' => 'ajax.',
        ], function () {
            // Split like every other mixed-module route in this 'reports.'
            // group (see the big comment above): dashboard-stats mixes
            // ledger-derived values (accounting) with profitAgentWise
            // (agent_profit, computed from invoice_details — not the
            // ledger). A package client with agent_profit but not
            // accounting must be able to fetch the latter without ever
            // hitting the former's 404, so each value now has its own
            // route gated to its own module instead of one endpoint gated
            // module:accounting that silently withheld an unrelated value.
            // (Merge fixup 2026-09-04: the merge's non-conflicting auto-merge
            // reintroduced a group-level 'module:accounting' middleware here,
            // silently defeating the split this comment describes and 404ing
            // dashboard-stats-profit-agent for every package client with
            // agent_profit but not accounting — removed.)
            Route::get('/dashboard-stats', [ReportController::class, 'getDashboardStats'])->name('dashboard-stats')->middleware('module:accounting');
            Route::get('/dashboard-stats/profit-agent', [ReportController::class, 'getDashboardProfitAgentStat'])->name('dashboard-stats-profit-agent')->middleware('module:agent_profit');
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
        // IDOR/leak fix: invoice.show / .pdf / .split / .proforma /
        // .proforma-pdf (and these Arabic twins) used to be
        // withoutMiddleware(['auth']) on a guessable {companyId}/{invoiceNumber}
        // (or {invoiceNumber}/{clientId}/{partialId}) pair, with no policy
        // check in the controller -- anyone who could guess/enumerate the
        // pair could read any tenant's invoice, including internal
        // supplier_price/markup_price/profit/commission figures baked into
        // the view data. They now require a logged-in, same-company (or
        // admin) user -- see InvoiceController's authorizeStaffInvoiceAccess()
        // helper, called from each of these methods. The client-facing
        // "share this invoice via WhatsApp/email/payment redirect" use case
        // the old unauthenticated routes existed for is preserved separately
        // below as signed-URL-only '.public' variants -- see
        // Invoice::publicUrl() for how those links are generated and
        // config('app.invoice_link_ttl_minutes') for their expiry. The
        // public variants also strip the internal pricing fields above from
        // what they hand to the view -- see
        // InvoiceController::scrubInvoiceDetailsForPublicView().
        Route::get('/{companyId}/{invoiceNumber}/arabic', [InvoiceController::class, 'showArabic'])->name('show-arabic');
        Route::get('/{companyId}/{invoiceNumber}/arabic/public', [InvoiceController::class, 'showArabic'])
            ->name('show-arabic.public')
            ->withoutMiddleware(['auth'])
            ->middleware('signed');
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
        Route::get('/partial/{invoiceNumber}/{clientId}/{partialId}', [InvoiceController::class, 'split'])->name('split');
        Route::get('/partial/{invoiceNumber}/{clientId}/{partialId}/public', [InvoiceController::class, 'split'])
            ->name('split.public')
            ->withoutMiddleware(['auth'])
            ->middleware('signed');
        Route::get('/partial/{invoiceNumber}/{clientId}/{partialId}/arabic', [InvoiceController::class, 'splitarabic'])->name('split-arabic');
        Route::get('/partial/{invoiceNumber}/{clientId}/{partialId}/arabic/public', [InvoiceController::class, 'splitarabic'])
            ->name('split-arabic.public')
            ->withoutMiddleware(['auth'])
            ->middleware('signed');
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

        // P2.5.E (p2_5-brief.md §P2.5.E): registered HERE, BEFORE the bare 2-segment `show` route
        // immediately below, on purpose. `show` (`/{companyId}/{invoiceNumber}`, no digit
        // constraint) matches ANY 2-segment GET path under `/invoice/` and is otherwise matched
        // FIRST by Laravel's router regardless of definition order elsewhere in this group -- the
        // exact pre-existing bug `tests/Feature/Security/InvoiceLockTenantIsolationTest.php`
        // already documents against `loss-bearer` (that route's own fix was deferred as "outside
        // this hotfix's assigned files"; its test works around the shadow by calling the
        // controller method directly instead of through HTTP). This NEW route is squarely this
        // sub-wave's own deliverable, so the fix here is to register it before the shadow instead
        // of also working around it -- verified via `php artisan route:list` + an HTTP test
        // actually hitting `invoice.unlock-blockers` successfully (InvoiceUnlockHttpTest).
        Route::get('/{invoice}/unlock-blockers', [InvoiceController::class, 'unlockBlockers'])->name('unlock-blockers');

        Route::get('/{companyId}/{invoiceNumber}', [InvoiceController::class, 'show'])->name('show');
        Route::get('/{companyId}/{invoiceNumber}/public', [InvoiceController::class, 'show'])
            ->name('show.public')
            ->withoutMiddleware(['auth'])
            ->middleware('signed');
        Route::get('/{companyId}/{invoiceNumber}/pdf', [InvoiceController::class, 'generatePdf'])->name('pdf');
        Route::get('/{companyId}/{invoiceNumber}/pdf/public', [InvoiceController::class, 'generatePdf'])
            ->name('pdf.public')
            ->withoutMiddleware(['auth'])
            ->middleware('signed');
        Route::get('/{companyId}/{invoiceNumber}/proforma', [InvoiceController::class, 'proforma'])->name('proforma');
        Route::get('/{companyId}/{invoiceNumber}/proforma/public', [InvoiceController::class, 'proforma'])
            ->name('proforma.public')
            ->withoutMiddleware(['auth'])
            ->middleware('signed');
        Route::get('/{companyId}/{invoiceNumber}/proforma-pdf', [InvoiceController::class, 'proformaGeneratePdf'])->name('proforma.pdf');
        Route::get('/{companyId}/{invoiceNumber}/proforma-pdf/public', [InvoiceController::class, 'proformaGeneratePdf'])
            ->name('proforma.pdf.public')
            ->withoutMiddleware(['auth'])
            ->middleware('signed');
        Route::post('/{companyId}/{invoiceNumber}/proforma-sent', [InvoiceController::class, 'markProformaSent'])->name('proforma.markSent');
        Route::put('/{companyId}/{invoiceNumber}/date', [InvoiceController::class, 'updateDate'])->name('updateDate');
        Route::put('/{companyId}/{invoiceNumber}/amount', [InvoiceController::class, 'updateAmount'])->name('updateAmount');
        Route::post('/update-task-price', [InvoiceController::class, 'updateTaskPrice'])->name('updateTaskPrice');
        Route::get('/details/{companyId}/{invoiceNumber}', [InvoiceController::class, 'showDetails'])->name('details');
        Route::post('/{invoice}/lock', [InvoiceController::class, 'lockInvoice'])->name('lock');
        Route::post('/{invoice}/unlock', [InvoiceController::class, 'unlockInvoice'])->name('unlock');
        // invoice.unlock-blockers (GET) is registered ABOVE, before the `show` shadow route — see
        // that registration's own comment for why.
        Route::get('/{invoice}/loss-bearer', [InvoiceController::class, 'getLossBearer'])->name('loss-bearer.get');
        Route::put('/{invoice}/loss-bearer', [InvoiceController::class, 'updateLossBearer'])->name('loss-bearer.update');
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
        // W4.R bundled fix (w4-brief.md §5 "remove dead store-unpaid route or implement
        // storeForUnpaidInvoice properly (pick one, justify)"; ct-refund-map.md §7 confirms
        // `RefundController::storeForUnpaidInvoice` does not exist). REMOVED, not implemented: the
        // unpaid-invoice refund flow is already fully reachable through the ordinary POST / above
        // — store()'s own `$paymentStatus === 'unpaid'` branch already calls
        // handleUnpaidInvoice() internally (see that method) with no separate request contract
        // ever having existed for this endpoint. Fabricating a second entry point for behaviour
        // the primary one already covers would duplicate, not restore, functionality.
        Route::get('/{refund}/edit', [RefundController::class, 'edit'])->name('edit');
        Route::put('/{refund}', [RefundController::class, 'update'])->name('update');
        Route::post('/{refund}/approve', [RefundController::class, 'approve'])->name('approve');
        Route::post('/{refund}/reject', [RefundController::class, 'reject'])->name('reject');
        Route::post('/{refund}/complete-process', [RefundController::class, 'completeProcess'])->name('complete_process');
        Route::get('/{refundClientId}/complete', [RefundController::class, 'completeRefundClient'])->name('refund-client.complete');
        Route::delete('/{refundClientId}', [RefundController::class, 'deleteRefundClient'])->name('refund-client.delete');
        // W4.R bundled fix (w4-brief.md §5 "refunds.show route -> auth OR signed URL (mirror
        // Invoice::publicUrl() pattern + TTL env)"; ct-refund-map.md §7 — this single route
        // stripped `auth` with NO signature check at all, so any caller who could guess/enumerate
        // {companyId}/{refundNumber} could view any company's refund). Split into two routes,
        // mirroring InvoiceController's own show/show-arabic vs. show.public/show-arabic.public
        // split exactly: 'show' now requires the ordinary authenticated session (this app's
        // internal refund-detail view); 'show.public' is the SIGNED-only variant for a shared
        // link (Refund::publicUrl()), never requiring auth. Together they satisfy "auth OR
        // signed" without a route that accepts either check on its own.
        Route::get('/{companyId}/{refundNumber}', [RefundController::class, 'show'])->name('show');
        Route::get('/{companyId}/{refundNumber}/public', [RefundController::class, 'show'])
            ->name('show.public')
            ->withoutMiddleware(['auth'])
            ->middleware('signed');
        Route::post('/{refund}/void', [RefundController::class, 'void'])->name('void');
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
        Route::get('/transaction/{transactionId}/check-status', [PaymentController::class, 'checkTransactionStatus'])->name('transaction.check-status');
        Route::post('/create/{companyId}/{invoiceNumber}', [PaymentController::class, 'create'])->name('create')->withoutMiddleware(['auth']);
        //Route::match(['get', 'post'], '/create/{invoiceNumber}', [PaymentController::class, 'create'])->name('create')->withoutMiddleware(['auth']);
        Route::post('/webhook', [PaymentController::class, 'webhook'])->name('webhook');
        Route::get('/success', [PaymentController::class, 'success'])->name('success')->withoutMiddleware(['auth']);
        Route::get('/failed', [PaymentController::class, 'failed'])->name('failed')->withoutMiddleware(['auth']);
        Route::get('/outstanding', [PaymentController::class, 'outstanding'])->name('outstanding');

        Route::group([
            'prefix' => 'link',
            'as' => 'link.',
        ], function () {

            Route::get('/', [PaymentController::class, 'paymentLink'])->name('index');
            Route::get('/export', [PaymentController::class, 'exportPaymentLinks'])->name('export');
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
            Route::post('/import/file', [PaymentController::class, 'importPaymentFile'])->name('import.file');
            Route::post('/import/assign-client/{paymentId}', [PaymentController::class, 'assignClientToImport'])->name('import.assign-client');
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
        Route::get('/{id}/credit-balance', [ClientController::class, 'getCreditBalance'])->name('credit-balance');
        // IDOR fix: this used to be reachable by anyone who guessed a client
        // id (withoutMiddleware(['auth']) + no policy check in the
        // controller). It now requires a logged-in, same-company (or admin)
        // user via ClientPolicy::view() -- see showCredit() in
        // ClientController. The client-facing "share this ledger via
        // WhatsApp" use case that the old unauthenticated route existed for
        // is preserved separately below as a signed-URL-only variant.
        Route::get('/{id}/credits', [ClientController::class, 'showCredit'])->name('credits');

        // Public, client-facing variant of the credit ledger (e.g. a link
        // sent to the client over WhatsApp/Resayil). Reachable ONLY with a
        // valid Laravel temporary signed URL -- see
        // Client::creditStatementUrl() for how the link is generated and
        // config('app.client_credit_link_ttl_minutes') for its expiry.
        // 'signed' (Illuminate\Routing\Middleware\ValidateSignature) rejects
        // any request whose signature is missing, tampered, or expired with
        // an InvalidSignatureException, which Laravel's default exception
        // handler renders as a 403 -- no bespoke handling needed here.
        Route::get('/{id}/credits/shared', [ClientController::class, 'showCreditShared'])
            ->name('credits.shared')
            ->withoutMiddleware(['auth'])
            ->middleware('signed');

        // Assignment request routes
        Route::post('/request-assignment', [ClientController::class, 'requestAssignment'])->name('request-assignment');
        Route::get('/assignment/approve/{token}', [ClientController::class, 'approveAssignment'])->name('assignment.approve');
        Route::get('/assignment/deny/{token}', [ClientController::class, 'denyAssignment'])->name('assignment.deny');

        Route::group([
            'prefix' => 'ajax',
            'as' => 'ajax.',
        ], function () {
            Route::get('/search', [ClientController::class, 'searchClient'])->name('search');
            Route::get('/{id}/tasks', [ClientController::class, 'ajaxTasks'])->name('tasks');
            Route::get('/{id}/invoices', [ClientController::class, 'ajaxInvoices'])->name('invoices');
            Route::get('/{id}/payments', [ClientController::class, 'ajaxPayments'])->name('payments');
        });
    });

    Route::group([
        'prefix' => 'exchange',
        'as' => 'exchange.',
        'middleware' => ['module:accounting'],
    ], function () {
        Route::get('index', [CurrencyExchangeController::class, 'index'])->name('index');
        Route::post('store', [CurrencyExchangeController::class, 'store'])->name('store');
        Route::put('update-manual', [CurrencyExchangeController::class, 'updateManual'])->name('update.manual');
        Route::put('update-auto', [CurrencyExchangeController::class, 'updateAuto'])->name('update.auto');
        Route::put('update-method/{id}', [CurrencyExchangeController::class, 'updateMethod'])->name('update.method');
        Route::get('histories', [CurrencyExchangeController::class, 'allHistories'])->name('histories.all');
    });

    // exchange/convert is deliberately OUTSIDE the exchange.* group above: it's
    // a currency-conversion utility (used by tasks/index.blade.php to price a
    // task in a non-KWD currency) rather than an accounting screen — a Task
    // Uploader dependency, not an Accounting one. The rest of the exchange.*
    // group (rate management screen, history, manual/auto rate edits) stays
    // module:accounting since those really are ledger-FX-rate administration.
    // The sidebar/mobile-drawer currency-converter WIDGET also posts here, but
    // stays invisible to a non-accounting company via its own
    // @if($hasAccountingModule) blade guard — this route-level change only
    // affects direct/task-price callers.
    Route::post('exchange/convert', [CurrencyExchangeController::class, 'convertFromSidebar'])
        ->name('exchange.convert')
        ->middleware('module:task_uploader');

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
        Route::post('/calculate-charge', [ChargeController::class, 'calculateCharge'])->name('calculate-charge');

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
    // Ruling R1: client credits are split BY VERB, not gated as one
    // accounting-module block. index()/filter() only read the client's
    // credit-balance ledger (Credit::with('client') / Gate::authorize('view',
    // $client)) — that's Customer CRM information, not accounting, so it
    // gates on module:crm. creditTopup() posts real Transaction + JournalEntry
    // rows against COA accounts, i.e. it moves money, so it gates on
    // module:payment_gateway. Neither is module:accounting: accounting is
    // never sold and must stay invisible regardless of which of these two
    // sellable modules a company bought.
    //
    // useCreditNow() was dropped entirely rather than re-scoped:
    // CreditController has no such method (confirmed 2026-08-31; see
    // Accounting Gap/16-phase1-verification-findings-2026-08.md section H),
    // so the route only ever 500'd on submit. The dead "pay with credit"
    // modal markup that posted to it in invoice/show(.blade.php),
    // show-arabic, split, and split-arabic was removed in the same change —
    // those views call route('credits.useCreditNow', ...) unconditionally
    // while rendering (not just on click), so deleting the route without
    // touching the views would have turned every split-payment invoice page
    // into a 500 (RouteNotFoundException).
    // useCreditNow() exists on dev as a Credit-row-only write that bypasses
    // PaymentApplicationService; not merged (see MERGE-PLAN-DEV-INTO-LAUNCH-2026-09-04.md S-2).
    Route::group([
        'prefix' => 'credits',
        'as' => 'credits.',
    ], function () {
        Route::get('/', [CreditController::class, 'index'])->name('index')->middleware('module:crm');
        Route::get('/filter', [CreditController::class, 'filter'])->name('filter')->middleware('module:crm');
        Route::post('/topup', [CreditController::class, 'creditTopup'])->name('topup')->middleware('module:payment_gateway');
    });

    Route::group([
        'prefix' => 'settings',
        'as' => 'settings.',
    ], function () {
        // `resayil.frame` (redesign, 2026-08-26): the WhatsApp tab embedded
        // on this page (SettingController::index() -> resayil.admin._panel)
        // can render the same Resayil iframe as the standalone
        // resayil-admin.index route, so this response needs the matching
        // CSP frame-src header. Harmless for every user without WhatsApp
        // access: ResayilFrameHeaders always emits SOME header (a bare
        // `frame-src 'self'` when there is nothing to allow), it does not
        // block anything this page did not already avoid framing.
        Route::get('/', [SettingController::class, 'index'])->name('index')->middleware('resayil.frame');

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
        Route::get('/agent-loss', [SettingController::class, 'getAgentLoss'])->name('agent-loss');
        Route::post('/agent-loss', [SettingController::class, 'storeAgentLoss'])->name('agent-loss.store');
        Route::post('/agent-loss/bulk-update', [SettingController::class, 'bulkUpdateAgentLoss'])->name('agent-loss.bulk-update');
        Route::delete('/agent-loss/{id}', [SettingController::class, 'deleteAgentLoss'])->name('agent-loss.delete');
        Route::get('/ai', [SettingController::class, 'getAiConfig'])->name('ai');
        Route::post('/ai', [SettingController::class, 'updateAiConfig'])->name('ai.update');
        Route::get('/ai/models', [SettingController::class, 'aiModels'])->name('ai.models');
        Route::post('/ai/test', [SettingController::class, 'aiTest'])->name('ai.test');
        Route::get('/notifications', [SettingController::class, 'getNotificationSettings'])->name('notifications');
        Route::post('/notifications', [SettingController::class, 'updateNotificationSetting'])->name('notifications.update');
        Route::get('/agent-notifications', [SettingController::class, 'getAgentNotifications'])->name('agent-notifications');
        Route::post('/agent-notifications', [SettingController::class, 'storeAgentNotification'])->name('agent-notifications.store');
        Route::post('/agent-notifications/bulk-update', [SettingController::class, 'bulkUpdateAgentNotifications'])->name('agent-notifications.bulk-update');
        Route::delete('/agent-notifications/{id}', [SettingController::class, 'deleteAgentNotification'])->name('agent-notifications.delete');

        // W4.U §a — Accounting settings tab (invoice_overpay_cancel_policy, refund fee schedule,
        // unclaimed_writeback_months, commissionable_fee_types, posting_basis, bearer matrix,
        // refund notification toggles). Authorization is SettingPolicy::viewAccountingSettings()/
        // manageAccountingSettings() inside the controller actions; the module middleware here
        // is about VISIBILITY, not permission. Without it the policy denies with a 403, which
        // confirms the screen exists to a client whose package does not include accounting —
        // every other accounting surface 404s instead (see EnsureModuleEnabled's docblock), and
        // these two routes must not be the pair that gives the module away.
        Route::middleware(['module:accounting'])->group(function () {
            Route::get('/accounting-settings', [SettingController::class, 'getAccountingSettings'])->name('accounting-settings');
            Route::post('/accounting-settings', [SettingController::class, 'storeAccountingSettings'])->name('accounting-settings.store');
        });

        /*
        | Voucher Templates gallery (.planning/specs/VOUCHER-TEMPLATES.md
        | §16 step 3, §8). Both routes read-only, both scoped to
        | getCompanyId(Auth::user()) inside the controller — deliberately
        | INSIDE this authenticated `settings` group, unlike the `terms`
        | route group elsewhere in this file, which the plan documents as
        | a known, unfixed hole (unauthenticated + no company scoping on
        | its mutations, §11.4). Nothing under this prefix mutates
        | anything: no create/edit/delete route exists here at all
        | (plan §14.8 — clients pick among shipped designs, they do not
        | edit or upload their own).
        */
        Route::group([
            'prefix' => 'voucher-templates',
            'as' => 'voucher-templates.',
        ], function () {
            Route::get('/', [VoucherTemplateController::class, 'gallery'])->name('index');
            Route::get('/preview/{taskType}/{language}', [VoucherTemplateController::class, 'preview'])->name('preview');
        });
    });

    /*
    | Voucher issue/send actions on a task or a package (Step 4, plan
    | .planning/specs/VOUCHER-TEMPLATES.md section 10, section 16). A SIBLING
    | of the settings group above (not nested — same reason as the
    | Resayil Admin Center group directly below: nesting would prefix
    | every name with settings.). Inherits 'auth' from the outer wrap;
    | issuing/sending needs no dedicated permission (plan section 11.5 —
    | normal authenticated task access is enough), and every method
    | inside VoucherController still scopes explicitly by
    | getCompanyId(Auth::user()) rather than trusting route-model-binding
    | alone (plan section 2.4 discipline).
    */
    Route::group([
        'prefix' => 'vouchers',
        'as' => 'vouchers.',
    ], function () {
        Route::get('/task/{task}', [VoucherController::class, 'indexForTask'])->name('task.index');
        Route::post('/task/{task}/issue', [VoucherController::class, 'issueForTask'])->name('task.issue');
        Route::post('/task/{task}/attach-client', [VoucherController::class, 'attachClient'])->name('task.attach-client');
        Route::post('/package/{package}/issue', [VoucherController::class, 'issueForPackage'])->name('package.issue');
        Route::get('/{voucher}/download', [VoucherController::class, 'download'])->name('download');
        Route::post('/{voucher}/send', [VoucherController::class, 'send'])->name('send');
        Route::post('/{voucher}/cancel', [VoucherController::class, 'cancel'])->name('cancel');
    });

    /*
    | Module 5 — Resayil Admin Center (Settings -> WhatsApp).
    | Plan: .planning/specs/RESAYIL-ADMIN-CENTER.md §4.1 / §9.2.
    |
    | A SIBLING of the `settings` group above, deliberately NOT nested
    | inside it: nesting would prefix every route name with `settings.`
    | and silently turn `resayil-admin.index` into
    | `settings.resayil-admin.index`, breaking every route() call.
    |
    | It sits inside the outer auth group, so 'auth' is inherited and is
    | not repeated here. Middleware ORDER MATTERS: `module:resayil` runs
    | first and 404s a company without the module (EnsureModuleEnabled
    | aborts 404, never 403, so an un-entitled company cannot even learn
    | this section exists); `can:manage-resayil` then 403s roles outside
    | {ADMIN, COMPANY} for companies that DO have the module.
    |
    | `resayil.frame` (redesign, 2026-08-26): NOW applied. The Inbox tab
    | added by the redesign embeds the same <x-resayil-frame> iframe the
    | drawer and /resayil full page already use, so this route needs the
    | same CSP frame-src allowlist they carry. ResayilFrameHeaders degrades
    | gracefully when RESAYIL_EMBED_URL is unset (emits a bare
    | `frame-src 'self'`, which simply matches "no iframe on this page" —
    | it does not error and does not block anything that isn't already
    | absent).
    */
    Route::group([
        'prefix' => 'settings/whatsapp',
        'as' => 'resayil-admin.',
        'middleware' => ['module:resayil', 'can:manage-resayil', 'resayil.frame'],
    ], function () {
        Route::get('/', [ResayilAdminController::class, 'index'])->name('index');

        // JSON feed for the panel's Alpine poller / manual refresh.
        // `throttle:resayil-overview-refresh` (registered in
        // AppServiceProvider) only counts `?refresh=1` requests — the
        // routine unthrottled 60 s poll is a cache read and does nothing
        // to the reseller API. A forced refresh does one upstream call and
        // one DB write per hit, and was unthrottled for every company user
        // (abuse surface fix, wave 3).
        Route::get('/overview-data', [ResayilAdminController::class, 'overviewData'])
            ->middleware('throttle:resayil-overview-refresh')
            ->name('overview');

        // Panel 4 — Billing. Payment history is a reseller read and needs
        // no company key; invoice PDFs do, and render the "available once
        // linked" state until slice 2 captures one.
        Route::get('/billing/payments', [ResayilAdminController::class, 'payments'])->name('billing.payments');

        // Operator collections lever (§5.5, owner decision D-2). ADMIN
        // role only — re-checked inside the controller, because this gate
        // admits COMPANY too. Pausing takes a live WhatsApp number dark.
        Route::post('/device/pause', [ResayilAdminController::class, 'pauseDevice'])->name('device.pause');
        Route::post('/device/resume', [ResayilAdminController::class, 'resumeDevice'])->name('device.resume');
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

// NOTE: this group sits outside the top-level Route::middleware(['auth'])
// wrap above, and ReceiptVoucherController has no constructor middleware
// either — before this change every one of these routes (not just `.show`)
// had ZERO auth protection, not merely a missing module gate. 'auth' is
// added here alongside 'module:accounting' to close that.
//
// IDOR fix: `.show` used to keep a blanket ->withoutMiddleware(['auth',
// 'module:accounting']) exemption on a bare {companyId}/{voucherNumber} pair
// (a single-digit company id and a sequential reference number) with NO
// signature check at all -- an anonymous visitor could enumerate every
// receipt voucher in the system (real KWD amounts, invoice amount, client
// name, status). Split into two routes, mirroring InvoiceController's own
// invoice.show/.public and RefundController's refunds.show/.public splits
// exactly: 'show' now requires the ordinary authenticated session, tenant-
// checked in ReceiptVoucherController::show() itself (same-company or
// unscoped admin -- see that method's own docblock); 'show.public' is the
// SIGNED-only variant for the client-facing shareable voucher link
// (InvoiceReceipt::publicUrl()), never requiring auth. It also keeps
// skipping 'module:accounting' -- an anonymous client opening their own link
// has no session/company to check the module against, and a link that
// legitimately went out must not start 404ing the moment a company's
// accounting module is toggled off.
Route::group([
    'prefix' => 'receipt-voucher',
    'as' => 'receipt-voucher.',
    'middleware' => ['auth', 'module:accounting'],
], function () {
    Route::get('/', [ReceiptVoucherController::class, 'index'])->name('index');
    Route::get('/create', [ReceiptVoucherController::class, 'create'])->name('create');
    Route::post('/store', [ReceiptVoucherController::class, 'store'])->name('store');
    Route::get('/edit/{id}', [ReceiptVoucherController::class, 'edit'])->name('edit');
    Route::put('/update/{id}', [ReceiptVoucherController::class, 'update'])->name('update');
    Route::post('/approve/{id}', [ReceiptVoucherController::class, 'approve'])->name('approve');
    // W5.R: delete()/clear()/bounce() are NEW -- HEAD had no delete action for RV at all, and no
    // cheque-instrument workflow. See ReceiptVoucherController's own class docblock ("$id is
    // invoice_receipts.id everywhere") for why these are keyed the same way as edit/update/approve.
    Route::delete('/{id}', [ReceiptVoucherController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/clear', [ReceiptVoucherController::class, 'clear'])->name('clear');
    Route::post('/{id}/bounce', [ReceiptVoucherController::class, 'bounce'])->name('bounce');
    Route::get('/fetch-journals-by-date', [ReceiptVoucherController::class, 'fetchPaymentsByDate'])->name('fetchPaymentsByDate');
    Route::get('/fetch-journals-view', [ReceiptVoucherController::class, 'fetchJournalEntriesByIds'])->name('fetch-journals');
    Route::post('/{id}/decline-reconcile', [ReceiptVoucherController::class, 'declineReconcile'])->name('decline-reconcile');
    Route::post('/import', [ReceiptVoucherController::class, 'import'])->name('import');
    // Cheque-image download (security fix): authenticated + tenant-checked, replacing the old
    // public-disk Storage::url() link. Registered BEFORE the `show` catch-all two-segment route
    // below so `/receipt-voucher/{id}/cheque-image` cannot be swallowed by `/{companyId}/{voucherNumber}`.
    Route::get('/{id}/cheque-image', [ReceiptVoucherController::class, 'chequeImage'])->name('cheque-image');
    Route::get('/{companyId}/{voucherNumber}', [ReceiptVoucherController::class, 'show'])->name('show');
    Route::get('/{companyId}/{voucherNumber}/public', [ReceiptVoucherController::class, 'show'])
        ->name('show.public')
        ->withoutMiddleware(['auth', 'module:accounting'])
        ->middleware('signed');
});

// Same pre-existing gap as receipt-voucher above: BankPaymentController has
// no 'show' route at all, so no exemption is needed — every action here
// gets both 'auth' and 'module:accounting'.
Route::group([
    'prefix' => 'bank-payments',
    'as' => 'bank-payments.',
    'middleware' => ['auth', 'module:accounting'],
], function () {
    Route::get('/create', [BankPaymentController::class, 'create'])->name('create');
    Route::post('/store', [BankPaymentController::class, 'store'])->name('store');
    Route::get('/edit/{id}', [BankPaymentController::class, 'edit'])->name('edit');
    Route::put('/edit/{id}', [BankPaymentController::class, 'update'])->name('update');
    Route::get('/', [BankPaymentController::class, 'index'])->name('index');
    // W5.P: approve()/destroy()/clear() are NEW -- HEAD had no approval gate or delete action for
    // PV at all, and no cheque-instrument clearance workflow. See BankPaymentController's own class
    // docblock ("$id is bank_payments.id everywhere") for why these are keyed the same way as
    // edit/update, mirroring ReceiptVoucherController's identical W5.R precedent.
    Route::post('/approve/{id}', [BankPaymentController::class, 'approve'])->name('approve');
    Route::delete('/{id}', [BankPaymentController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/clear', [BankPaymentController::class, 'clear'])->name('clear');
    // Cheque-image download (security fix): authenticated + tenant-checked, replacing the old
    // public-disk Storage::url() link -- mirrors receipt-voucher.cheque-image above.
    Route::get('/{id}/cheque-image', [BankPaymentController::class, 'chequeImage'])->name('cheque-image');
    Route::get('/fetch-journals-by-date', [BankPaymentController::class, 'fetchPaymentsByDate'])->name('fetchPaymentsByDate');
    Route::get('/fetch-journals-view', [BankPaymentController::class, 'fetchJournalEntriesByIds'])->name('fetch-journals');
    Route::post('/{id}/decline-reconcile', [BankPaymentController::class, 'declineReconcile'])->name('decline-reconcile');
    // T14 -- live supplier-bank-detail lookup for the create/edit screens' Alpine.js (see
    // BankPaymentController::resolveSupplierBankAjax()'s own docblock).
    Route::get('/resolve-supplier-bank', [BankPaymentController::class, 'resolveSupplierBankAjax'])->name('resolve-supplier-bank');
});

/*
| Public tokenised voucher route (Step 4 item 2, plan .planning/specs/VOUCHER-TEMPLATES.md
| section 3.6 / section 11.1). Deliberately in this file region: OUTSIDE the top-level
| Route::middleware(['auth'])->group(...) wrap, same as receipt-voucher/.show and
| bank-payments above -- no exemption dance needed here because this whole
| group carries no 'auth' middleware at all.  is UNTRUSTED URL input
| (same caution as InvoiceController::generatePdf's own comment) -- every
| lookup is TravelVoucher::scopeForPublicToken(, ), which
| double-scopes by company_id AND token AND excludes every
| TravelVoucher::PUBLICLY_DEAD_STATUSES status. throttle:30,1 because the
| URL shape (companyId + token) is enumerable-shaped even though the
| 64-char token itself is not guessable (plan section 11.1).
*/
Route::group([
    'prefix' => 'travel-voucher',
    'as' => 'travel-voucher.',
    'middleware' => ['throttle:30,1'],
], function () {
    Route::get('/{companyId}/{token}', [PublicVoucherController::class, 'show'])->name('show');
    Route::get('/{companyId}/{token}/pdf', [PublicVoucherController::class, 'pdf'])->name('pdf');
});

// EXPORT
Route::get('/download-company', [ExportController::class, 'downloadCompany'])->name('download.company');
Route::get('/download-agent', [ExportController::class, 'downloadAgent'])->name('download.agent');
Route::get('/download-task', [ExportController::class, 'downloadTask'])->name('download.tasks');
Route::get('/download-client', [ExportController::class, 'downloadClient'])->name('download.client');
Route::get('export-companies', [CompanyController::class, 'exportCsv'])->name('companies.exportCsv');
// AP-2 (extended): this route sits outside the top-level Route::middleware(['auth'])
// wrap above (like the receipt-voucher/bank-payment groups nearby) and had no auth
// or module gate at all -- unauthenticated, cross-tenant CSV export of every agent
// (name/email/phone/company). AgentController::exportCsv() now also calls
// Gate::authorize('viewAny', Agent::class) itself (AP-1), which alone denies a guest
// (AgentPolicy::viewAny(User $user) has a non-nullable param), but 'auth' is added
// here too so a guest gets the normal login redirect instead of a raw 403.
Route::get('export-agents', [AgentController::class, 'exportCsv'])
    ->middleware(['auth', 'module:agent_profit'])
    ->name('agents.exportCsv');
Route::get('export-tasks', [TaskController::class, 'exportCsv'])->name('tasks.exportCsv')->middleware('auth');

Route::get('export-clients', [TaskController::class, 'exportCsv'])->name('clients.exportCsv')->middleware('auth');

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
Route::group(['prefix' => 'docs', 'as' => 'docs.'], function () {
    Route::get('/user', fn () => view('docs.user-documentation'))->name('user-documentation')->middleware('auth');
    Route::get('/api', fn () => view('docs.api-documentation'))->name('api-documentation');
    Route::get('/developer', fn () => view('docs.developer-documentation'))->name('developer-documentation');
    Route::get('/postman/download', function () {
        $filePath = resource_path('postman/Task_Webhook_API.postman_collection.json');
        if (! file_exists($filePath)) {
            abort(404, 'Postman collection file not found');
        }

        return response()->download($filePath, 'Task_Webhook_API.postman_collection.json');
    })->name('postman.download');
});

Route::post('/whatsapp/sendToResayilSimple', [WhatsappController::class, 'sendToResayilSimple'])->name('whatsapp.sendToResayilSimple');
// Security fix (sec/resayil-webhook): see routes/api.php's
// /webhook/resayil/media/{secret} comment — same fix, same middleware.
Route::post('/webhook/resayil/{secret}', [WhatsappController::class, 'handleResayilWebhook'])
    ->middleware('verify.resayil.webhook')
    ->name('whatsapp.resayil-webhook');

// Legacy secret-less path: fail closed (404).
Route::post('/webhook/resayil', function () {
    return response()->json(['message' => 'Not Found'], 404);
})->name('whatsapp.resayil-webhook.legacy');

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

Route::get('locale/{lang}', function ($lang) {
    if (in_array($lang, config('app.available_locales', ['en', 'ar']))) {
        session()->put('locale', $lang);

        if (auth()->check()) {
            auth()->user()->update(['locale' => $lang]);
        }
    }

    return redirect()->back();
})->name('locale.switch');

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

// DOTW Admin (unified tabbed page) — Phase 2 Plan 3 + Quick-2
Route::middleware(['auth', 'dotw_audit_access'])
    ->prefix('admin/dotw')
    ->name('admin.dotw.')
    ->group(function () {
        Route::get('/', fn () => view('admin.dotw.index'))->name('index');
        Route::redirect('audit-logs', '/admin/dotw', 301)->name('audit-logs');
        Route::redirect('api-tokens', '/admin/dotw', 301)->name('api-tokens');
    });

// Task action requests — owner Approve/Deny via tokenized link from
// in-app notification, email, or WhatsApp. No auth required (token is
// sufficient entropy: 32 random chars, single-use via status check).
Route::prefix('task-action-requests')->name('task-action-request.')->group(function () {
    Route::get('/{token}', [\App\Http\Controllers\TaskActionRequestController::class, 'show'])->name('show');
    Route::get('/{token}/approve', [\App\Http\Controllers\TaskActionRequestController::class, 'approve'])->name('approve');
    Route::get('/{token}/deny', [\App\Http\Controllers\TaskActionRequestController::class, 'deny'])->name('deny');
});

// Public company self-registration (invite-token gated)
Route::get('/register/company/{token}', [\App\Http\Controllers\CompanyRegistrationController::class, 'show'])
    ->middleware('throttle:20,1')->name('company-register.show');
Route::get('/register/company/{token}/agents-template', [\App\Http\Controllers\CompanyRegistrationController::class, 'agentsTemplate'])
    ->middleware('throttle:20,1')->name('company-register.agents-template');
Route::post('/register/company/{token}', [\App\Http\Controllers\CompanyRegistrationController::class, 'store'])
    ->middleware('throttle:5,1')->name('company-register.store');

require __DIR__ . '/auth.php';

// ── AIR uploader dashboard actions (citycomm) — 2026-06-02 ──
Route::middleware('auth')->prefix('air/uploader')->name('air.uploader.')->group(function () {

    // Remove a stale/dead heartbeat row
    Route::post('/remove-host', function (\Illuminate\Http\Request $r) {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $host = (string) $r->input('host_id', '');
        if ($host !== '') {
            \Illuminate\Support\Facades\DB::table('uploader_heartbeats')->where('host_id', $host)->delete();
        }
        return back();
    })->name('remove-host');

    // Review held + errored files with their reasons + the office (PCC) they belong to
    Route::get('/logs', function () {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        $base = '/home/citycomm/AIR';
        $rows = [];
        foreach (['NOT LOADED' => 'error', 'NOT LOADED/unregistered_agent' => 'held'] as $dir => $kind) {
            foreach (glob("$base/$dir/*.AIR") ?: [] as $f) {
                $reason = $kind;
                $sc = $f . '.error.json';
                if (is_file($sc)) {
                    $j = json_decode(@file_get_contents($sc), true);
                    $reason = $j['reason'] ?? $kind;
                }
                // Extract the office / PCC code(s) from the file content (cheap — first 800 bytes)
                $office = '?';
                $head = @file_get_contents($f, false, null, 0, 800);
                if ($head && preg_match_all('/\b(KWIKT[0-9A-Z]{3,5})\b/', $head, $mm)) {
                    $office = implode(', ', array_values(array_unique($mm[1])));
                }
                $rows[] = [
                    'file'   => basename($f),
                    'kind'   => $kind,
                    'reason' => $reason,
                    'office' => $office,
                    'mtime'  => @filemtime($f) ?: 0,
                ];
            }
        }
        usort($rows, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        $shown = array_slice($rows, 0, 300);
        // office summary (counts across ALL held/errored, not just shown)
        $byOffice = [];
        foreach ($rows as $r) { $byOffice[$r['office']] = ($byOffice[$r['office']] ?? 0) + 1; }
        arsort($byOffice);
        $h = '<!doctype html><html><head><meta charset="utf-8"><title>AIR Uploader Logs</title>'
           . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"></head>'
           . '<body class="p-4" style="background:#f5f7fa"><div class="container-fluid" style="max-width:1050px">'
           . '<h5 class="mb-3">AIR Uploader — Held &amp; Errored Files '
           . '<small class="text-muted">(' . count($rows) . ' total, showing ' . count($shown) . ')</small> '
           . '<a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary float-end">&larr; Back</a></h5>';
        // office summary chips
        $h .= '<div class="mb-3">';
        foreach ($byOffice as $off => $n) {
            $h .= '<span class="badge bg-secondary-subtle text-secondary me-1">' . e($off) . ' &middot; ' . $n . '</span>';
        }
        $h .= '</div>';
        $h .= '<table class="table table-sm table-hover bg-white"><thead class="table-light"><tr>'
           . '<th>File</th><th>Office (PCC)</th><th>Type</th><th>Reason</th><th>When</th></tr></thead><tbody>';
        foreach ($shown as $r) {
            $badge = $r['kind'] === 'held'
                ? '<span class="badge bg-warning text-dark">held</span>'
                : '<span class="badge bg-danger">error</span>';
            $h .= '<tr><td><code>' . e($r['file']) . '</code></td>'
                . '<td><span class="badge bg-light text-dark border">' . e($r['office']) . '</span></td>'
                . '<td>' . $badge . '</td>'
                . '<td>' . e($r['reason']) . '</td>'
                . '<td class="text-muted small">' . ($r['mtime'] ? date('Y-m-d H:i', $r['mtime']) : '-') . '</td></tr>';
        }
        if (empty($shown)) {
            $h .= '<tr><td colspan="5" class="text-center text-muted py-4">No held or errored files.</td></tr>';
        }
        $h .= '</tbody></table>'
            . '<p class="text-muted small"><span class="badge bg-warning text-dark">held</span> = withheld by the agent gate (unregistered agent / orphan modification) — recoverable. '
            . '<span class="badge bg-danger">error</span> = genuine processing failure. '
            . '<strong>Office (PCC)</strong> = the GDS office code(s) found in the file — lets you see which agency the ticket belongs to.</p>'
            . '</div></body></html>';
        return response($h);
    })->name('logs');
});

// Module 5 — Resayil WhatsApp CRM full-page view. A TOP-LEVEL statement
// (sibling to the air/uploader group immediately above, same indentation,
// same explicit 'auth' pattern) — NOT nested inside it or any other group.
// Gated by module:resayil (App\Http\Middleware\EnsureModuleEnabled — 404 for
// companies without the module, never a 403, matching every other
// module-gated route) and resayil.frame (App\Http\Middleware\ResayilFrameHeaders
// — CSP so the Resayil iframe is actually allowed to load).
//
// The GET below is READ-ONLY on purpose (security fix, 2026-08-26 —
// blockers 1 & 3): it used to also provision a Resayil workspace/team
// member as a side effect of the page render, with no role check. Any
// action that creates or links an external Resayil identity now lives on
// the POST route in its OWN middleware group directly below, which layers
// `can:manage-resayil` (ADMIN + COMPANY only) UNDER `module:resayil` —
// same gate as Settings -> WhatsApp, and CSRF-protected like every other
// POST route in this app. `resayil.frame` is intentionally NOT applied to
// the POST: it sets a frame-ancestors CSP for the iframe response, which a
// redirect-back action has no use for.
Route::middleware(['auth', 'module:resayil', 'resayil.frame'])->group(function () {
    Route::get('/resayil', [ResayilEmbedController::class, 'index'])->name('resayil.index');
});

Route::middleware(['auth', 'module:resayil', 'can:manage-resayil'])->group(function () {
    Route::post('/resayil/provision', [ResayilEmbedController::class, 'provision'])->name('resayil.provision');
});

require __DIR__.'/resailai-admin.php';
