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
use App\Http\Controllers\WhatsAppHotelController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SupplierController;
use App\Services\MagicHolidayService;

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
Route::post('/invoice', [MobileController::class, 'store']);
Route::get('/invoice/{agentId}', [MobileController::class, 'getInvoiceByAgentId']);
Route::get('/invoice/by/{Id}', [MobileController::class, 'getInvoiceById']);
Route::post('/invoice/partial', [MobileController::class, 'savePartial']);
Route::post('/invoice/remove/partial', [MobileController::class, 'removePartial']);
Route::put('/invoice/{id}', [MobileController::class, 'updateInvoice']);
Route::delete('/invoice/delete/{id}', [MobileController::class, 'deleteInvoice']);
Route::get('/transaction/{agentId}', [MobileController::class, 'getTransactionByAgentId']);

Route::post('payment/webhook-fatoorah', [PaymentController::class, 'handleWebhookFatoorah']);
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

Route::get('pin', function () {
    return view('auth.pin');
})->name('pin');

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

Route::post('/automation-supplier', [TaskController::class, 'automationSupplier']);

// Payment API routes for lazy-loaded content
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/payments/{id}/partials', [PaymentController::class, 'getPartials']);
    Route::get('/payments/{id}/transactions', [PaymentController::class, 'getTransactions']);
});

require __DIR__ . '/auth.php';
