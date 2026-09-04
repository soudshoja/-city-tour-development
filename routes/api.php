<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\MobileController;
use App\Http\Controllers\VersionApiController;
use App\Http\Controllers\Auth\TwoFAController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\IncomingMediaController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CurrencyExchangeController;
use App\Http\Controllers\WhatsAppHotelController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\APIController;
use App\Services\MagicHolidayService;
use App\Http\Webhooks\TaskWebhook;

Route::post('/login2', [MobileController::class, 'login2']);
Route::post('/verifytwofa', [MobileController::class, 'verifytwofa']);
// Agents
Route::get('/agents', [MobileController::class, 'agent']);
Route::get('/agents/{userId}', [MobileController::class, 'getAgentByUserId']);


Route::get('/companies', [MobileController::class, 'company']);
Route::get('/companies/{id}', [CompanyController::class, 'show'])->name('companiesshow.show');

Route::group([
    'prefix' => 'task',
    'as' => 'task.',
], function () {
    Route::get('/{agentId}', [MobileController::class, 'getTasksByAgentId']);
    Route::get('/', [MobileController::class, 'task']);
    Route::get('/pending', [MobileController::class, 'taskPending']);
    Route::post('/task-from-email', [TaskController::class, 'handleTaskFromEmail']);
});

Route::get('/invoice/create', [MobileController::class, 'create'])->name('invoice.create');
Route::get('/invoice/{agentId}', [MobileController::class, 'getInvoiceByAgentId']);
Route::get('/invoice/by/{Id}', [MobileController::class, 'getInvoiceById']);
Route::post('/invoice/partial', [MobileController::class, 'savePartial']);
Route::post('/invoice/remove/partial', [MobileController::class, 'removePartial']);
Route::put('/invoice/{id}', [MobileController::class, 'updateInvoice']);
Route::delete('/invoice/delete/{id}', [MobileController::class, 'deleteInvoice']);
Route::get('/transaction/{agentId}', [MobileController::class, 'getTransactionByAgentId']);

Route::post('payment/webhook-fatoorah', [PaymentController::class, 'handleWebhookFatoorah']);
Route::post('payment/hesabe-webhook', [PaymentController::class, 'handleHesabeWebhook'])->name('payment.hesabe.webhook');
Route::post('payment/importfatoorah', [PaymentController::class, 'importPaidFatoorah'])->name('importfatoorah');
Route::post('payment/register-tbo-booking', [PaymentController::class, 'registerTBOBookingAsTask'])->name('payment.register.tbo.booking');

Route::get('/clients', [MobileController::class, 'client']);
Route::get('/clients/{agentId}', [MobileController::class, 'getClientByAgentId']);

Route::group([
    'prefix' => 'clients',
    'as' => 'clients.',
], function () {
    Route::post('/', [ ClientController::class, 'storeApi' ]);
});

Route::get('/test-get-client', [MobileController::class, 'clientTest']);
// Route::get('/thread/{threadId}',[MobileController::class, 'retrieveThread']);
Route::get('/create-assistant', [MobileController::class, 'createAssistant']);
Route::put('/assistant/{assistantId}', [MobileController::class, 'modifyAssistant']);
Route::get('/send-client-data', [MobileController::class, 'sendDataToThread']);
Route::get('/create-thread', [MobileController::class, 'createThread']);
Route::delete('/thread/{threadId}', [MobileController::class, 'deleteThread']);
Route::get('/thread/{threadId}/run/{runId}', [MobileController::class, 'checkRun']);
Route::get('/thread/{threadId}/run/{runId}/cancel', [MobileController::class, 'cancelRun']);
Route::get('/thread/{threadId}/messages', [MobileController::class, 'getMessages']);
Route::get('/thread/{threadId}/run', [MobileController::class, 'listRun']);
Route::post('/send-message', [MobileController::class, 'sendMessage']);
Route::get('/list-step/{threadId}/{runId}', [MobileController::class, 'listStep']);
Route::get('/step/{threadId}/{runId}/{stepId}', [MobileController::class, 'retrieveStep']);
Route::post('/test-user-task/{userId}', [MobileController::class, 'getUserTask']);
Route::get('/get-invoices/{userId}', [MobileController::class, 'getInvoices']);
Route::post('knowledge', [KnowledgeBaseController::class, 'fetchRelevantKnowledge']);


Route::get('/version/{versionId}', [VersionApiController::class, 'edit']);
Route::post('/version', [VersionApiController::class, 'store']);
Route::put('/version/update/{id}', [VersionApiController::class, 'update']);
Route::post('/version/update/current', [VersionApiController::class, 'updateCurrent']);
Route::get('/current', [VersionApiController::class, 'getCurrent']);
Route::get('/version', function () {
    return response()->json([
        'commit' => trim(exec('git rev-parse --short HEAD')),   // Short commit hash
        'branch' => trim(exec('git rev-parse --abbrev-ref HEAD')), // Current branch name
        'date'   => trim(exec('git log -1 --format=%ci')), // Commit date
        'message' => trim(exec('git log -1 --pretty=%s'))  // Commit message
    ]);
});

// Duplicate of routes/web.php's own 'pin' route (identical body, same name) — pre-existing on
// feat/travelerp-launch before this merge (confirmed via git show), unrelated to the 46 conflicts
// resolved here. Broke `php artisan route:cache` (Laravel refuses two routes with the same name),
// one of this merge's required gates, so removed here; a Blade view() route belongs in web.php
// (an "api" route returning HTML was never right anyway), which keeps the working copy.
Route::post('/webhook/resayil/media', [IncomingMediaController::class, 'handleResayilWebhook'])
    ->name('webhook.resayil.media');
Route::post('/chat/upload', [ChatController::class, 'handleFileUpload']);

Route::prefix('/whatsapp/hotel')->group(function () {
    // Route::post('/list', [WhatsAppHotelController::class, 'getListOfHotels']);
    // routes/api.php
    Route::post('/booking/save', [WhatsAppHotelController::class, 'saveBookingDetails']);
    Route::post('/hotels/list', [WhatsAppHotelController::class, 'listHotels']);

    Route::post('/details', [WhatsAppHotelController::class, 'getHotelDetails']);
    Route::post('/offers', [WhatsAppHotelController::class, 'storeTemporaryOffer']);
    Route::post('/offers/find', [WhatsAppHotelController::class, 'findOffer']);
    Route::post('/offers/all', [WhatsAppHotelController::class, 'findAllOffers']);
    Route::post('/store-prebook', [WhatsAppHotelController::class, 'storePrebook']);
    Route::post('/prebook-details', [WhatsAppHotelController::class, 'getPrebookDetails']);
    Route::post('/store-book', [WhatsAppHotelController::class, 'storeBooking']);
    Route::post('/delete-booking-request', [WhatsAppHotelController::class, 'deleteBookingRequest']);
    Route::post('/time-left', [WhatsappHotelController::class, 'temporaryOffersTimeLeft']);
    Route::get('/booking-details', [WhatsAppHotelController::class, 'hotelBookingDetails']);
    Route::post('/booking-confirm', [WhatsAppHotelController::class, 'confirmBooking']);
    Route::post('/tbo-booking-confirm', [WhatsAppHotelController::class, 'confirmTBOBooking']);
    Route::post('/tbo/b2c/booking-confirm', [WhatsAppHotelController::class, 'confirmTBOB2CBooking']);

    Route::group([
        'prefix' => 'step',
        'as' => 'step.',
    ], function () {
        Route::post('/store', [WhatsAppHotelController::class, 'storeStep']);
        Route::post('/retrieve', [WhatsAppHotelController::class, 'retrieveStep']);
        Route::post('/update', [WhatsAppHotelController::class, 'updateStep']);
        Route::post('/delete', [WhatsAppHotelController::class, 'deleteStep']);
    });
});

Route::post('/hesabe/transaction-enquiry', [PaymentController::class, 'hesabeTransactionEnquiry'])->name('hesabe.transaction.enquiry');

Route::post('/magic/webhook/callback', [SupplierController::class, 'magicReserveWebhookCallback'])->name('magic-webhook-callback')->withoutMiddleware(['auth']);

Route::group([
    'prefix' => 'magic-holiday',
], function(){
    Route::get('/get-reservation/{reservationId}', [MagicHolidayService::class, 'getSingleReservation'])->name('magic-holiday.get-reservation');
    Route::post('/access-token', [WhatsAppHotelController::class, 'getAccessToken'])->name('magic-holiday.access-token');
    Route::delete('/reservation/{reservationId}', [MagicHolidayService::class, 'cancelReservation'])->name('magic-holiday.cancel-reservation');
});

Route::post('/find-agent', [TaskController::class, 'findAgent']);
Route::post('/automation-supplier', [TaskController::class, 'automationSupplier']);
// W6.S item (4) (w6-brief.md "Consolidation + fixes" -- ct-void-map.md §7 bug 1,
// "Unauthenticated POST /api/task/webhook posts JEs"). Callers must sign the request (see
// App\Services\WebhookSigningService) and identify their webhook client via the
// `{webhook_client_id}` ROUTE segment (VerifyWebhookSignature checks
// `$request->route('webhook_client_id') ?? $request->query('client_id')` -- the route param is
// used here, deliberately NOT the `?client_id=` query-string alternative, because this
// controller's OWN payload already has a `client_id` FIELD with a completely different meaning
// (`App\Models\Client`'s id) -- reusing the query-string convention would silently collide the
// two and fail this endpoint's own `client_id` validation rule for every request that also
// carries a real webhook client id). TaskWebhook::webhook() itself then REQUIRES that the
// middleware actually verified a signature (401 otherwise), turning the middleware's own "skip if
// no signature header" default into a hard requirement for this one endpoint.
Route::post('/task/webhook/{webhook_client_id}', [TaskWebhook::class, 'webhook'])
    ->middleware('verify.webhook.signature')
    ->name('task.webhook');

// Payment API routes for lazy-loaded content
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/payments/{id}/partials', [PaymentController::class, 'getPartials']);
    Route::get('/payments/{id}/transactions', [PaymentController::class, 'getTransactions']);
    // SECURITY: was public -- created invoices + journal entries (see
    // MobileController::store()) for anyone who could reach the API, no
    // auth at all. Read-side invoice/payment-link routes are deliberately
    // left untouched (owner wants invoices viewable by direct URL).
    Route::post('/invoice', [MobileController::class, 'store']);
});

// SECURITY (dev-branch hardening, see APIController::getClient/getAgent/getCompany/getSupplier
// docblocks): these four returned whole, unfiltered DB rows -- including Client.passport_no,
// Client.civil_no, Client.date_of_birth and Company.iata_client_secret -- to ANY caller, with no
// auth at all. `git log` on APIController.php shows they were added by "feat: n8n supplier api"
// as pre-flight ID-validation helpers for `POST /api/task/webhook` (see
// resources/views/docs/developer-documentation.blade.php's "Utility Endpoints" section, which
// describes them exactly that way), so they share that same endpoint's caller: an n8n/webhook
// integration identified via a WebhookClient. Gated with the exact same `verify.webhook.signature`
// middleware + explicit "no webhook_client resolved -> 401" controller-side check used by
// App\Http\Webhooks\TaskWebhook::webhook() (see that method's own docblock for why the middleware
// alone -- which only verifies a signature IF one is presented -- is not sufficient on its own).
// No `{webhook_client_id}` route segment here (unlike task/webhook): none of these four accept a
// `client_id` body field that would collide with the middleware's `?client_id=` query-string
// convention (see VerifyWebhookSignature::handle()), so the query-string form is used directly and
// the URL path is unchanged for any future caller.
Route::middleware('verify.webhook.signature')->group(function () {
    Route::post('/get-client', [APIController::class, 'getClient']);
    Route::post('/get-agent', [APIController::class, 'getAgent']);
    Route::post('/get-company', [APIController::class, 'getCompany']);
    Route::post('/get-supplier', [APIController::class, 'getSupplier']);
});

// Left unauthenticated deliberately: pure reference/schema data, no PII, not tenant-scoped.
// - getTaskStructure returns a static list of field NAMES (no DB query at all).
// - getCountry (`countries` table: name/iso codes/dialing code/currency) and getHotel (`hotels`
//   table: name/address/city/phone/website/rating/description) are global lookup/directory tables
//   with no client, financial or credential columns -- see their migrations. Neither carries a
//   company_id, so there is no tenant to scope them to.
Route::post('/get-task-structure', [APIController::class, 'getTaskStructure']);
Route::post('/get-country', [APIController::class, 'getCountry']);
Route::post('/get-hotel', [APIController::class, 'getHotel']);

Route::group([
    'prefix' => 'currency-exchange',
    'as' => 'currency-exchange.',
], function(){
    Route::get('/latest', [CurrencyExchangeController::class, 'getLatestRates'])->name('latest');
    Route::post('/convert', [CurrencyExchangeController::class, 'convertCurrency'])->name('convert');
});

// (routes/auth.php — Breeze's web-session login/register/password routes — is NOT required
// here. It was previously required from BOTH web.php and api.php, registering every one of
// its named routes twice ('register', 'login', 'logout', 'password.*', 'verification.*') and
// breaking `php artisan route:cache` ("Another route has already been assigned name
// [register]"). Pre-existing on feat/travelerp-launch before this merge (confirmed via git
// show), unrelated to the 46 conflicts resolved here, but broke a required merge gate. web.php
// already requires it (session-auth routes belong on the web middleware group, not api).
