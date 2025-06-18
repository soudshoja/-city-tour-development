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
use App\Http\Controllers\WhatsAppHotelController;

        Route::post('/login2', [MobileController::class, 'login2']);
        Route::post('/verifytwofa', [MobileController::class, 'verifytwofa']);
        // Agents
        Route::get('/agents', [MobileController::class, 'agent']);
        Route::get('/agents/{userId}', [MobileController::class, 'getAgentByUserId']);


        Route::get('/companies', [MobileController::class, 'company']);
        Route::get('/companies/{id}', [CompanyController::class, 'show'])->name('companiesshow.show');

        Route::get('/tasks/{agentId}', [MobileController::class, 'getTasksByAgentId']);
        Route::get('/tasks', [MobileController::class, 'task']);
        Route::get('/tasks/pending', [MobileController::class, 'taskPending']);

        Route::get('/invoice/create', [MobileController::class, 'create'])->name('invoice.create');
        Route::post('/invoice', [MobileController::class, 'store']);
        Route::get('/invoice/{agentId}', [MobileController::class, 'getInvoiceByAgentId']);
        Route::get('/invoice/by/{Id}', [MobileController::class, 'getInvoiceById']);
        Route::post('/invoice/partial', [MobileController::class, 'savePartial']);
        Route::post('/invoice/remove/partial', [MobileController::class, 'removePartial']);
        Route::put('/invoice/{id}', [MobileController::class, 'updateInvoice']);
        Route::delete('/invoice/delete/{id}', [MobileController::class, 'deleteInvoice']);
        Route::get('/transaction/{agentId}', [MobileController::class, 'getTransactionByAgentId']);


        Route::get('/clients', [MobileController::class, 'client']);  
        Route::get('/clients/{agentId}', [MobileController::class, 'getClientByAgentId']);

        Route::get('/test-get-client', [MobileController::class, 'clientTest']);
        // Route::get('/thread/{threadId}',[MobileController::class, 'retrieveThread']);
        Route::get('/create-assistant',[MobileController::class, 'createAssistant']);
        Route::put('/assistant/{assistantId}',[MobileController::class, 'modifyAssistant']);
        Route::get('/send-client-data',[MobileController::class, 'sendDataToThread']);
        Route::get('/create-thread',[MobileController::class, 'createThread']);
        Route::delete('/thread/{threadId}',[MobileController::class, 'deleteThread']);
        Route::get('/thread/{threadId}/run/{runId}',[MobileController::class, 'checkRun']);
        Route::get('/thread/{threadId}/run/{runId}/cancel',[MobileController::class, 'cancelRun']);
        Route::get('/thread/{threadId}/messages', [MobileController::class, 'getMessages']);
        Route::get('/thread/{threadId}/run', [MobileController::class, 'listRun']);
        Route::post('/send-message', [MobileController::class, 'sendMessage']);
        Route::get('/list-step/{threadId}/{runId}',[MobileController::class, 'listStep']);
        Route::get('/step/{threadId}/{runId}/{stepId}',[MobileController::class, 'retrieveStep']);
        Route::post('/test-user-task/{userId}', [MobileController::class, 'getUserTask']);
        Route::get('/get-invoices/{userId}',[MobileController::class, 'getInvoices']);
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

        Route::get('pin', function(){
            return view('auth.pin');
        })->name('pin');

        Route::post('/webhook/resayil/media', [IncomingMediaController::class, 'handleResayilWebhook'])
        ->name('webhook.resayil.media');
        Route::post('/chat/upload', [ChatController::class, 'handleFileUpload']);

        Route::prefix('/whatsapp/hotel')->group(function () {
            Route::post('/city-id', [WhatsAppHotelController::class, 'getCityIdFromHotelName']);
            Route::post('/offers', [WhatsAppHotelController::class, 'storeTemporaryOffer']);
            Route::post('/offers/find', [WhatsAppHotelController::class, 'findOffer']);
        });
        
require __DIR__.'/auth.php';
