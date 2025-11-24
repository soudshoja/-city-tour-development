<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IncomingMedia;
use App\Models\Agent;
use App\Models\Client;
use App\Models\ClientAssignmentRequest;
use App\Models\Notification;
use App\Http\Traits\NotificationTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class IncomingMediaController extends Controller
{
    use NotificationTrait;
    public function handleResayilWebhook(Request $request)
    {
        $webhookId = uniqid();
        Log::info("=== WEBHOOK START [{$webhookId}] ===", [
            'request_data' => $request->all()
        ]);

        $type = $request->input('data.type');
        $phone = $request->input('data.fromNumber')
            ?? $request->input('phone')
            ?? $request->input('messages.0.from');

        Log::info("Webhook Processing [{$webhookId}]", [
            'source_type' => $type,
            'phone' => $phone
        ]);

        // Check if sender is an agent - if not, ignore the webhook completely
        $agent = Agent::where('phone_number', $phone)->first();
        if (!$agent) {
            Log::info("Non-agent contact ignored", [
                'phone' => $phone,
                'webhook_id' => $webhookId
            ]);
            return response()->json(['message' => 'Webhook ignored - agents only'], 200);
        }

        $deviceId = $request->input('device.id');
        $chatWid = $request->input('data.chat.id') ?? $request->input('data.from');

        $senderAgent = "yes";
        $agentId = $agent->id;
        $agentPhone = $agent->phone_number;
        $agentEmail = $agent->email;
        $fallbackPhone = config('app.agent_default_phone', '+96522210017');
        $fallbackEmail = config('app.agent_default_email', 'ops@citytravelers.co');

        try {
            Log::info("Sender is an agent: {$agent->name} ({$phone})");

            $waitingForClientPhone = Cache::get('agent_waiting_for_client_phone_' . $phone);

            $clientPhoneReply = trim(
                $request->input('data.text')
                    ?? $request->input('data.body')
                    ?? $request->input('messages.0.body')
                    ?? $request->input('messages.0.text')
                    ?? ''
            );

            // Handle restart command
            if (strtolower($clientPhoneReply) === 'restart') {
                Cache::forget('pending_media_' . $phone);
                Cache::forget('agent_client_phone_' . $phone);
                Cache::forget('agent_waiting_for_client_phone_' . $phone);
                
                $to = $request->input('data.from') ?? $request->input('from');
                $this->sendWhatsAppMessage($to, "Okay, let's start fresh. Please send your client's media document again.", 'agent_restart');
                return response()->json(['message' => 'Restart triggered'], 200);
            }

            // Handle assignment request commands (approve/deny)
            if ($this->isAssignmentCommand($clientPhoneReply)) {
                return $this->handleAssignmentCommand($clientPhoneReply, $agent, $request);
            }

            // Check if media is received first
            $mediaData = $request->input('media') ?? $request->input('data.media');
            
            // If agent sends media but we're not waiting for client phone yet
            if ($mediaData && !$waitingForClientPhone) {
                Cache::put('pending_media_' . $phone, $mediaData, now()->addMinutes(30));
                Log::info("Media cached for agent {$phone} until client phone is received.");
                
                $to = $request->input('data.from') ?? $request->input('from');
                $this->sendWhatsAppMessage($to, "Hello {$agent->name}, please reply with your client's phone number to proceed.\n(eg: +96522210017)", 'agent_phone_request');

                Cache::put('agent_waiting_for_client_phone_' . $phone, true, now()->addMinutes(30));
                return response()->json(['message' => 'Agent prompt sent, waiting for client phone input.'], 200);
            }

            // If we're waiting for client phone and agent sends text (not media)
            if ($waitingForClientPhone && !$mediaData && $clientPhoneReply) {
                if (preg_match('/^\+?\d{6,15}$/', $clientPhoneReply)) {
                    Cache::put('agent_client_phone_' . $phone, $clientPhoneReply, now()->addHour());
                    Cache::forget('agent_waiting_for_client_phone_' . $phone);

                    $to = $request->input('data.from') ?? $request->input('from');
                    $this->sendWhatsAppMessage($to, "Your client's phone number {$clientPhoneReply} received.\nPlease hold while we process the data...", 'agent_phone_confirmed');
                    
                    // Continue to process cached media
                    Log::info("Agent phone confirmed, continuing with media processing", [
                        'agent_phone' => $phone,
                        'client_phone' => $clientPhoneReply
                    ]);
                } else {
                    $to = $request->input('data.from') ?? $request->input('from');
                    $this->sendWhatsAppMessage($to, "The phone number you sent seems invalid. Please send a valid phone number including country code.", 'agent_phone_invalid');
                    Cache::forget('pending_media_' . $phone);
                    return response()->json(['message' => 'Invalid client phone number received.'], 200);
                }
            }

            // If we're still waiting for client phone and no valid phone received, return
            if ($waitingForClientPhone && !Cache::has('agent_client_phone_' . $phone)) {
                return response()->json(['message' => 'Still waiting for client phone number.'], 200);
            }

            // NEW: If agent sends text but no media exists and we're not in any process
            if (!$mediaData && !$waitingForClientPhone && !Cache::has('pending_media_' . $phone) && $clientPhoneReply) {
                $to = $request->input('data.from') ?? $request->input('from');
                $this->sendWhatsAppMessage($to, "Hello {$agent->name}! To create a client profile, please send your client's identification document (Civil ID or Passport) first.", 'agent_no_media_instruction');
                return response()->json(['message' => 'Agent instructed to send media first.'], 200);
            }

            $agentId = $agent->id;
            $agentPhone = $agent->phone_number;
            $agentEmail = $agent->email;
            $senderAgent = 'yes';
        } catch (Exception $e) {
            Log::error("Error processing agent webhook: " . $e->getMessage());
            $agentPhone = $fallbackPhone;
            $agentEmail = $fallbackEmail;
        }

        // Retrieve media data - priority: current request > cached (for agents)
        $mediaData = $request->input('media')
            ?? $request->input('data.media')
            ?? Cache::get('pending_media_' . $phone);

        Log::info("Media data retrieval", [
            'has_current_media' => !empty($request->input('media') ?? $request->input('data.media')),
            'has_cached_media' => !empty(Cache::get('pending_media_' . $phone)),
            'sender_agent' => $senderAgent,
            'phone' => $phone
        ]);

        if (!$mediaData) {
            $to = $request->input('data.from') ?? $request->input('from');
            
            // Check if there was a previous session that expired
            $hadPreviousSession = Cache::has('agent_waiting_for_client_phone_' . $phone) || 
                                Cache::has('agent_client_phone_' . $phone);
            
            if ($hadPreviousSession) {
                $this->sendWhatsAppMessage($to, "Your previous session has expired. Please send your client's identification document again to start fresh.", 'session_expired');
            } else {
                $this->sendWhatsAppMessage($to, "Hello {$agent->name}! To create a client profile, please send your client's identification document (Civil ID or Passport).", 'no_media_found');
            }
            
            return response()->json(['message' => 'No media available for processing.'], 200);
        }

        // Clear cached media after retrieval since we're about to process it
        Cache::forget('pending_media_' . $phone);

        $downloadLink = $mediaData['links']['download'] ?? null;
        if (!$downloadLink) {
            Log::info("No media download link found.");
            return response()->json(['message' => 'No media found.'], 200);
        }

        if (!str_starts_with($downloadLink, 'http')) {
            $downloadLink = rtrim(config('services.resayil.base_url'), '/') . $downloadLink;
        }

        $mediaUrl = $downloadLink;
        $mediaId = $mediaData['id'] ?? null;
        $mimeType = $mediaData['mime'] ?? null;
        $caption = $mediaData['caption'] ?? null;
        $receivedAt = $request->input('data.date') ?? now();
        $filename = $mediaData['filename'] ?? 'file.jpg';
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';

        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            Log::warning("Unsupported media MIME type: {$mimeType}");
            return response()->json(['message' => 'Unsupported media type.'], 200);
        }

        if (IncomingMedia::where('media_id', $mediaId)->exists()) {
            Log::info("Duplicate media ignored: {$mediaId}");
            return response()->json(['message' => 'Duplicate media.'], 200);
        }

        $localPath = null;
        try {
            $newFilename = 'media_' . time() . '_' . uniqid() . '.' . $extension;
            $response = Http::withHeaders([
                'Token' => config('services.resayil.api_token'),
            ])
            ->timeout(120)
            ->get($mediaUrl);

            if ($response->ok()) {
                Storage::put("public/uploads/{$newFilename}", $response->body());
                $localPath = "uploads/{$newFilename}";
                Log::info("Media downloaded successfully", [
                    'local_path' => $localPath,
                    'media_id' => $mediaId,
                    'file_size' => strlen($response->body())
                ]);
            } else {
                $to = $request->input('data.from') ?? $request->input('from');
                Log::error("Media download failed", [
                    'media_url' => $mediaUrl,
                    'status_code' => $response->status(),
                    'response_body' => $response->body()
                ]);
                $this->sendWhatsAppMessage($to, "We were unable to download your file. Please try uploading again.", 'media_download_failed');
                return response()->json(['message' => 'Media download failed.'], 200);
            }
        } catch (Exception $e) {
            $to = $request->input('data.from') ?? $request->input('from');
            Log::error("Media download exception", [
                'media_url' => $mediaUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendWhatsAppMessage($to, "There was an error processing your file. Please try again.", 'media_download_exception');
            return response()->json(['message' => 'Media download exception.'], 200);
        }

        $incomingMedia = null;

        // Retrieve client phone from cache - this should exist if we reached this point
        $clientPhoneFromAgent = Cache::get('agent_client_phone_' . $phone);
        
        if (!$clientPhoneFromAgent) {
            Log::error("No client phone found in cache during media processing", [
                'agent_phone' => $phone,
                'media_id' => $mediaId
            ]);
            $to = $request->input('data.from') ?? $request->input('from');
            $this->sendWhatsAppMessage($to, "Session expired. Please send the document and client phone number again.", 'session_expired');
            return response()->json(['message' => 'Session expired - client phone missing.'], 200);
        }

        // Since sender is always an agent, use the client phone from cache
        $clientPhoneNumber = $clientPhoneFromAgent;
        $autoReplyAdd = "your client's";

        Log::info("Using client phone from cache", [
            'agent_phone' => $phone,
            'client_phone' => $clientPhoneNumber
        ]);

        // Normalize client phone number
        $normalizedClientPhone = $this->normalizePhoneNumber($clientPhoneNumber);

        $normalizedClientFullPhone = $normalizedClientPhone['full'];
        $matchedCodeClientPhone = $normalizedClientPhone['dialing_code'];
        $localNumberClient = $normalizedClientPhone['local'];

        Log::info("Phone number processing", [
            'sender_agent' => $senderAgent,
            'raw_client_phone' => $clientPhoneNumber,
            'normalized_full' => $normalizedClientFullPhone,
            'country_code' => $matchedCodeClientPhone,
            'local_number' => $localNumberClient
        ]);


        // Ensure we have a valid local path before proceeding
        if (!$localPath) {
            Log::error("No valid local path after media download", [
                'media_id' => $mediaId,
                'phone' => $phone
            ]);
            return response()->json(['message' => 'Media processing failed - no valid file path.'], 200);
        }

        $autoReplyText = null;

        if ($localPath && Storage::exists("public/{$localPath}")) {
            try {
                $fullPath = storage_path("app/public/{$localPath}");

                $uploadResponse = Http::asMultipart()
                    ->attach('file', file_get_contents($fullPath), basename($fullPath))
                    ->post(config('app.url') . '/api/chat/upload');

                if ($uploadResponse->successful()) {
                    $data = $uploadResponse->json('data');
                    Log::info("Upload response received", [
                        'ai_data' => $data
                    ]);

                    if ($data && isset($data['first_name'])) {
                        // Start transaction for all database operations
                        DB::beginTransaction();

                        if (
                            isset($data['nationality'])
                            && strtoupper(trim($data['nationality'])) === 'KUWAIT'
                            && empty($data['civil_no'])
                        ) {
                            Log::warning("Civil No. is mandatory for Kuwait nationals but missing", [
                                'phone' => $phone,
                                'data' => $data
                            ]);

                            $to = $request->input('data.from') ?? $request->input('from');
                            $this->sendWhatsAppMessage(
                                $to,
                                "Sorry, Civil ID is required for Kuwait nationals. Please resend with Civil ID.",
                                'civil_id_required'
                            );
                            return response()->json(['message' => 'Civil ID required for Kuwait nationals'], 422);
                        }

                        try {
                            // Create IncomingMedia record first (within transaction)
                            $incomingMedia = IncomingMedia::create([
                                'phone' => $phone,
                                'media_id' => $mediaId,
                                'mime_type' => $mimeType,
                                'caption' => $caption,
                                'received_at' => Carbon::parse($receivedAt, 'UTC'),
                                'file_path' => $localPath,
                                'agent_id' => $agentId,
                                'agent_phone' => $agentPhone,
                                'agent_email' => $agentEmail,
                                'media_type' => 'whatsapp',
                            ]);
                            Log::info("IncomingMedia record created within transaction", [
                                'id' => $incomingMedia->id,
                                'media_id' => $mediaId
                            ]);

                            // Check for duplicate clients using the same logic as ClientController
                            $currentAgent = Agent::find($agentId);
                            $companyId = $currentAgent->branch->company_id ?? null;
                            $existingClient = null;
                            $duplicateType = null;

                            // First check: Civil Number duplicate (if provided)
                            if (!empty($data['civil_no'])) {
                                $existingClient = Client::where('civil_no', $data['civil_no'])
                                    ->whereHas('agent.branch', function ($q) use ($companyId) {
                                        $q->where('company_id', $companyId);
                                    })
                                    ->first();
                                
                                if ($existingClient) {
                                    $duplicateType = 'civil_no';
                                }
                            }

                            // Second check: Name + Phone duplicate (if civil check didn't find anything)
                            if (!$existingClient && !empty($data['first_name']) && !empty($localNumberClient)) {
                                $existingClient = Client::where('first_name', $data['first_name'])
                                    ->where('phone', $localNumberClient)
                                    ->whereHas('agent.branch', function ($q) use ($companyId) {
                                        $q->where('company_id', $companyId);
                                    })
                                    ->first();
                                
                                if ($existingClient) {
                                    $duplicateType = 'name_phone';
                                }
                            }

                            // Handle duplicate detection
                            if ($existingClient) {
                                $ownerAgent = $existingClient->agent;
                                $assignedAgents = $existingClient->agents;

                                // Check if same agent or already assigned
                                if ($currentAgent->id === $ownerAgent->id || $assignedAgents->contains($currentAgent->id)) {
                                    $message = $duplicateType === 'civil_no' 
                                        ? "You already have a client with Civil ID: {$existingClient->civil_no}" 
                                        : "You already have a client with this name and phone number: {$existingClient->first_name} {$existingClient->last_name}";
                                    
                                    $to = $request->input('data.from') ?? $request->input('from');
                                    $this->sendWhatsAppMessage($to, $message, 'duplicate_client_same_agent');
                                    
                                    // Still process the media record but don't create/update client
                                    $incomingMedia->client_id = $existingClient->id;
                                    $incomingMedia->save();
                                    
                                    DB::commit();
                                    Cache::forget('agent_client_phone_' . $phone);
                                    Cache::forget('agent_waiting_for_client_phone_' . $phone);
                                    return response()->json(['message' => 'Duplicate client - same agent'], 200);
                                }

                                // Check if already assigned
                                if ($existingClient->agents()->where('agent_id', $currentAgent->id)->exists()) {
                                    $message = "You are already assigned to this client: {$existingClient->first_name} {$existingClient->last_name}\nYou can find them in your client list.";
                                    
                                    $to = $request->input('data.from') ?? $request->input('from');
                                    $this->sendWhatsAppMessage($to, $message, 'duplicate_client_already_assigned');
                                    
                                    // Still process the media record
                                    $incomingMedia->client_id = $existingClient->id;
                                    $incomingMedia->save();
                                    
                                    DB::commit();
                                    Cache::forget('agent_client_phone_' . $phone);
                                    Cache::forget('agent_waiting_for_client_phone_' . $phone);
                                    return response()->json(['message' => 'Client already assigned'], 200);
                                }

                                // Send assignment request via WhatsApp
                                $duplicateMessage = $duplicateType === 'civil_no' 
                                    ? "A client with Civil ID '{$existingClient->civil_no}' already exists" 
                                    : "A client with name '{$existingClient->first_name}' and phone '{$existingClient->phone}' already exists";

                                $message = "{$duplicateMessage}\n\n" .
                                    "Client: {$existingClient->first_name} {$existingClient->last_name}\n" .
                                    "Current Owner: {$ownerAgent->name}\n\n" .
                                    "An assignment request has been sent to {$ownerAgent->name} for approval.\n" .
                                    "They will be notified and can approve or deny your request to work with this client.\n\n" .
                                    "You will be notified once they respond.";

                                $to = $request->input('data.from') ?? $request->input('from');
                                $this->sendWhatsAppMessage($to, $message, 'assignment_request_sent');

                                // Create assignment request
                                $this->handleWhatsAppAssignmentRequest($existingClient, $currentAgent, $ownerAgent, $duplicateType, $data);

                                // Still process the media record
                                $incomingMedia->client_id = $existingClient->id;
                                $incomingMedia->save();
                                
                                DB::commit();
                                Cache::forget('agent_client_phone_' . $phone);
                                Cache::forget('agent_waiting_for_client_phone_' . $phone);
                                return response()->json(['message' => 'Assignment request sent'], 200);
                            }

                            // No duplicates found - create new client
                            $client = Client::create([
                                'first_name' => $data['first_name'],
                                'middle_name' => $data['middle_name'] ?? null,
                                'last_name' => $data['last_name'] ?? null,
                                'email' => $agentEmail,
                                'status' => 'active',
                                'phone' => $localNumberClient ?? '',
                                'country_code' => $matchedCodeClientPhone ?? '+965',
                                'date_of_birth' => $data['date_of_birth'] ?? null,
                                'address' => $data['place_of_birth'] ?? null,
                                'civil_no' => $data['civil_no'],
                                'passport_no' => $data['passport_no'] ?? null,
                                'old_passport_no' => $data['passport_no'] ?? null,
                                'agent_id' => $agentId,
                                'company_id' => $companyId
                            ]);

                            $autoReplyText = "Thank you, {$autoReplyAdd} profile has been created successfully.\n\nClient: {$data['first_name']} {$data['last_name']}\nCivil ID: {$data['civil_no']}";
                            
                            Log::info("New client created via WhatsApp", [
                                'client_id' => $client->id,
                                'civil_no' => $data['civil_no'],
                                'agent_id' => $agentId
                            ]);

                            // Link IncomingMedia to client
                            if ($incomingMedia) {
                                $incomingMedia->client_id = $client->id;
                                $incomingMedia->save();
                                Log::info("IncomingMedia linked to client", [
                                    'media_id' => $incomingMedia->id,
                                    'client_id' => $client->id
                                ]);
                            }

                            // Clean up cache after successful processing
                            Cache::forget('agent_client_phone_' . $phone);
                            Cache::forget('agent_waiting_for_client_phone_' . $phone);

                            // Commit all changes
                            DB::commit();
                            Log::info("Transaction committed successfully", [
                                'client_id' => $client->id,
                                'media_id' => $incomingMedia->id
                            ]);
                        } catch (Exception $e) {
                            DB::rollBack();
                            Log::error("Transaction failed, rolling back", [
                                'error' => $e->getMessage(),
                                'media_id' => $mediaId,
                                'phone' => $phone
                            ]);

                            $to = $request->input('data.from') ?? $request->input('from');
                            $this->sendWhatsAppMessage($to, "Sorry, there was an error creating the client profile. Please try again or contact support.", 'client_creation_failed');
                            return response()->json(['message' => 'Client creation failed'], 500);
                        }
                    } else {
                        Log::error("No valid data from upload response", [
                            'response_data' => $data
                        ]);
                        
                        $to = $request->input('data.from') ?? $request->input('from');
                        $this->sendWhatsAppMessage($to, "Sorry, I couldn't read the information from the document. Please ensure the document is clear and try again.", 'ai_extraction_failed');
                        return response()->json(['message' => 'AI extraction failed'], 400);
                    }
                } else {
                    Log::error("Upload failed", [
                        'status' => $uploadResponse->status(),
                        'body' => $uploadResponse->body()
                    ]);
                    
                    $to = $request->input('data.from') ?? $request->input('from');
                    $this->sendWhatsAppMessage($to, "Sorry, there was an issue processing your document. Please try again.", 'upload_processing_failed');
                    return response()->json(['message' => 'Upload processing failed'], 500);
                }
            } catch (Exception $e) {
                Log::error("Client creation/upload error", [
                    'error' => $e->getMessage(),
                    'media_id' => $mediaId,
                    'phone' => $phone
                ]);
                
                $to = $request->input('data.from') ?? $request->input('from');
                $this->sendWhatsAppMessage($to, "Sorry, there was an unexpected error processing your request. Please try again.", 'unexpected_error');
            }
        } else {
            Log::warning("File not found for processing", [
                'local_path' => $localPath,
                'media_id' => $mediaId
            ]);
            
            $to = $request->input('data.from') ?? $request->input('from');
            $this->sendWhatsAppMessage($to, "The uploaded file could not be found. Please try uploading again.", 'file_not_found');
        }

        // Auto-reply
        try {
            $to = $request->input('data.from') ?? $request->input('from');
            if ($to && $autoReplyText) {
                sleep(4);
                $this->sendWhatsAppMessage($to, $autoReplyText, 'auto_reply_success');
            } else {
                Log::warning("Missing recipient or message for auto-reply.", [
                    'to' => $to,
                    'has_auto_reply' => !empty($autoReplyText)
                ]);
            }
        } catch (Exception $e) {
            Log::error("Auto-reply failed: " . $e->getMessage());
        }

        return response()->json(['message' => 'Webhook received successfully']);
    }

    /**
     * Send WhatsApp message via Resayil
     */
    private function sendWhatsAppMessage($to, $message, $context = '')
    {
        try {
            if (!$to) {
                Log::warning("Cannot send WhatsApp message: missing recipient", ['context' => $context]);
                return false;
            }

            $wa = new WhatsappController();
            $wa->sendToResayil($to, $message);
            Log::info("WhatsApp message sent", [
                'to' => $to,
                'context' => $context,
                'message_preview' => substr($message, 0, 50) . '...'
            ]);
            return true;
        } catch (Exception $e) {
            Log::error("Failed to send WhatsApp message", [
                'to' => $to,
                'context' => $context,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function normalizePhoneNumber($rawPhone): array
    {
        if (!str_starts_with($rawPhone, '+')) {
            $rawPhone = '+' . $rawPhone;
        }

        $normalizedPhone = preg_replace('/\s+/', '', trim($rawPhone));

        $dialingCodes = DB::table('countries')->pluck('dialing_code');
        $dialingCodes = $dialingCodes->sortByDesc(fn($code) => strlen($code));

        $matchedCode = null;
        foreach ($dialingCodes as $code) {
            if (strpos($normalizedPhone, $code) === 0) {
                $matchedCode = $code;
                break;
            }
        }

        $localNumber = $matchedCode
            ? substr($normalizedPhone, strlen($matchedCode))
            : $normalizedPhone;

        $localNumber = preg_replace('/\D+/', '', $localNumber);

        return [
            'full' => $normalizedPhone,
            'dialing_code' => $matchedCode ?? '',
            'local' => $localNumber,
        ];
    }

    /**
     * Handle assignment request for WhatsApp duplicate client
     */
    private function handleWhatsAppAssignmentRequest($existingClient, $currentAgent, $ownerAgent, $duplicateType, $data)
    {
        try {
            // Create assignment request with WhatsApp context
            $assignmentRequest = ClientAssignmentRequest::create([
                'request_token' => ClientAssignmentRequest::generateToken(),
                'owner_agent_id' => $ownerAgent->id,
                'requesting_agent_id' => $currentAgent->id,
                'client_id' => $existingClient->id,
                'reason' => "WhatsApp client creation request - Agent {$currentAgent->name} attempted to create a client profile via WhatsApp for: {$data['first_name']} {$data['last_name']}",
                'status' => ClientAssignmentRequest::STATUS_PENDING,
                'expires_at' => now()->addDays(7),
            ]);

            // Send notification to owner agent
            $duplicateMessage = $duplicateType === 'civil_no' 
                ? "Civil ID: {$existingClient->civil_no}" 
                : "Name: {$existingClient->first_name} {$existingClient->last_name}, Phone: {$existingClient->phone}";

            $notificationData = [
                'request_token' => $assignmentRequest->request_token,
                'client_id' => $existingClient->id,
                'client_name' => "{$existingClient->first_name} {$existingClient->last_name}",
                'requesting_agent_name' => $currentAgent->name,
                'duplicate_type' => $duplicateType,
                'duplicate_info' => $duplicateMessage,
                'source' => 'whatsapp'
            ];

            $this->storeNotification([
                'user_id' => $ownerAgent->user_id,
                'title' => "Assignment Request via WhatsApp",
                'message' => "Agent {$currentAgent->name} is requesting access to your client ({$duplicateMessage}) through WhatsApp. Please review and take action.",
                'type' => 'client_assignment_request',
                'data' => $notificationData
            ]);

            // Send WhatsApp message to owner agent with commands
            $whatsappMessage = "ASSIGNMENT REQUEST\n\n" .
                "Agent {$currentAgent->name} is requesting access to your client:\n" .
                "Client: {$existingClient->first_name} {$existingClient->last_name}\n" .
                "{$duplicateMessage}\n\n" .
                "To respond, reply with one of these commands:\n" .
                "• APPROVE {$assignmentRequest->request_token}\n" .
                "• DENY {$assignmentRequest->request_token}\n\n" .
                "Or you can approve/deny from the web dashboard.";

            $this->sendWhatsAppMessage($ownerAgent->phone_number, $whatsappMessage, 'assignment_request_notification');

            Log::info("WhatsApp assignment request created", [
                'request_token' => $assignmentRequest->request_token,
                'owner_agent' => $ownerAgent->name,
                'requesting_agent' => $currentAgent->name,
                'client_id' => $existingClient->id,
                'duplicate_type' => $duplicateType
            ]);

        } catch (Exception $e) {
            Log::error("Failed to create WhatsApp assignment request", [
                'error' => $e->getMessage(),
                'owner_agent_id' => $ownerAgent->id,
                'requesting_agent_id' => $currentAgent->id,
                'client_id' => $existingClient->id
            ]);
        }
    }

    /**
     * Check if the message is an assignment command
     */
    private function isAssignmentCommand($message)
    {
        $message = strtoupper(trim($message));
        return preg_match('/^(APPROVE|DENY)\s+[A-Z0-9]{32}$/i', $message);
    }

    /**
     * Handle assignment command from WhatsApp
     */
    private function handleAssignmentCommand($message, $agent, $request)
    {
        try {
            $message = strtoupper(trim($message));
            $parts = explode(' ', $message);
            
            if (count($parts) !== 2) {
                $to = $request->input('data.from') ?? $request->input('from');
                $this->sendWhatsAppMessage($to, "Invalid command format. Use: APPROVE [TOKEN] or DENY [TOKEN]", 'invalid_command_format');
                return response()->json(['message' => 'Invalid command format'], 200);
            }

            $command = $parts[0];
            $token = $parts[1];

            // Find the assignment request
            $assignmentRequest = ClientAssignmentRequest::where('request_token', $token)
                ->where('owner_agent_id', $agent->id)
                ->where('status', ClientAssignmentRequest::STATUS_PENDING)
                ->first();

            if (!$assignmentRequest) {
                $to = $request->input('data.from') ?? $request->input('from');
                $this->sendWhatsAppMessage($to, "Assignment request not found or already processed. Please check the token or use the web dashboard.", 'request_not_found');
                return response()->json(['message' => 'Request not found'], 200);
            }

            // Check if request is expired
            if ($assignmentRequest->isExpired()) {
                $to = $request->input('data.from') ?? $request->input('from');
                $this->sendWhatsAppMessage($to, "This assignment request has expired. Please use the web dashboard for further actions.", 'request_expired');
                return response()->json(['message' => 'Request expired'], 200);
            }

            $client = $assignmentRequest->client;
            $requestingAgent = $assignmentRequest->requestingAgent;
            $to = $request->input('data.from') ?? $request->input('from');

            if ($command === 'APPROVE') {
                // Process approval
                DB::beginTransaction();
                
                try {
                    // Add agent to client's assigned agents
                    $client->agents()->syncWithoutDetaching([$requestingAgent->id]);
                    
                    // Mark request as approved
                    $assignmentRequest->approve($agent->user_id, 'Approved via WhatsApp');
                    
                    // Mark related notification as read
                    $notification = Notification::findByUserAndToken($agent->user_id, 'client_assignment_request', $token);
                    if ($notification) {
                        $notification->update(['status' => 'read', 'close' => 1]);
                    }

                    // Send confirmation to owner agent
                    $this->sendWhatsAppMessage($to, 
                        "REQUEST APPROVED\n\n" .
                        "You have successfully approved the assignment request.\n" .
                        "Agent {$requestingAgent->name} now has access to client: {$client->first_name} {$client->last_name}", 
                        'approval_confirmation'
                    );

                    // Send notification to requesting agent
                    $this->sendWhatsAppMessage($requestingAgent->phone_number,
                        "ASSIGNMENT APPROVED\n\n" .
                        "Your request to access client {$client->first_name} {$client->last_name} has been approved by {$agent->name}.\n" .
                        "You can now find this client in your client list.",
                        'assignment_approved'
                    );

                    DB::commit();
                    
                    Log::info("Assignment request approved via WhatsApp", [
                        'token' => $token,
                        'owner_agent' => $agent->name,
                        'requesting_agent' => $requestingAgent->name,
                        'client_id' => $client->id
                    ]);

                } catch (Exception $e) {
                    DB::rollBack();
                    Log::error("Failed to approve assignment via WhatsApp", ['error' => $e->getMessage(), 'token' => $token]);
                    $this->sendWhatsAppMessage($to, "Sorry, there was an error processing your approval. Please try again or use the web dashboard.", 'approval_error');
                }

            } elseif ($command === 'DENY') {
                // Process denial
                DB::beginTransaction();
                
                try {
                    // Mark request as denied
                    $assignmentRequest->deny($agent->user_id, 'Denied via WhatsApp');
                    
                    // Mark related notification as read
                    $notification = Notification::findByUserAndToken($agent->user_id, 'client_assignment_request', $token);
                    if ($notification) {
                        $notification->update(['status' => 'read', 'close' => 1]);
                    }

                    // Send confirmation to owner agent
                    $this->sendWhatsAppMessage($to, 
                        "REQUEST DENIED\n\n" .
                        "You have denied the assignment request.\n" .
                        "Agent {$requestingAgent->name} will be notified of your decision.", 
                        'denial_confirmation'
                    );

                    // Send notification to requesting agent
                    $this->sendWhatsAppMessage($requestingAgent->phone_number,
                        "ASSIGNMENT DENIED\n\n" .
                        "Your request to access client {$client->first_name} {$client->last_name} has been denied by {$agent->name}.\n" .
                        "Please contact {$agent->name} directly if you need to discuss this decision.",
                        'assignment_denied'
                    );

                    DB::commit();
                    
                    Log::info("Assignment request denied via WhatsApp", [
                        'token' => $token,
                        'owner_agent' => $agent->name,
                        'requesting_agent' => $requestingAgent->name,
                        'client_id' => $client->id
                    ]);

                } catch (Exception $e) {
                    DB::rollBack();
                    Log::error("Failed to deny assignment via WhatsApp", ['error' => $e->getMessage(), 'token' => $token]);
                    $this->sendWhatsAppMessage($to, "Sorry, there was an error processing your denial. Please try again or use the web dashboard.", 'denial_error');
                }
            }

            return response()->json(['message' => 'Assignment command processed'], 200);

        } catch (Exception $e) {
            Log::error("Error handling assignment command", [
                'error' => $e->getMessage(),
                'message' => $message,
                'agent_id' => $agent->id
            ]);
            
            $to = $request->input('data.from') ?? $request->input('from');
            $this->sendWhatsAppMessage($to, "Sorry, there was an error processing your command. Please try again or use the web dashboard.", 'command_processing_error');
            
            return response()->json(['message' => 'Command processing error'], 500);
        }
    }
}
