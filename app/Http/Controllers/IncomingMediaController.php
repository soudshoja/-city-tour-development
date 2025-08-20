<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IncomingMedia;
use App\Models\Agent;
use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

class IncomingMediaController extends Controller
{
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
            ])->get($mediaUrl);

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

                    if ($data && isset($data['first_name'], $data['civil_no'])) {
                        // Start transaction for all database operations
                        DB::beginTransaction();

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

                            // Find or create client
                            $client = Client::where('civil_no', $data['civil_no'])->first();

                            if (!$client) {
                                $client = Client::create([
                                    'first_name' => $data['first_name'],
                                    'middle_name' => $data['middle_name'] ?? null,
                                    'last_name' => $data['last_name'] ?? null,
                                    'email' => $agentEmail,
                                    'status' => 'active',
                                    'phone' => $localNumberClient,
                                    'country_code' => $matchedCodeClientPhone ?? '+965',
                                    'date_of_birth' => $data['date_of_birth'] ?? null,
                                    'address' => $data['place_of_birth'] ?? null,
                                    'civil_no' => $data['civil_no'] ?? null,
                                    'passport_no' => $data['passport_no'] ?? null,
                                    'old_passport_no' => $data['passport_no'] ?? null,
                                    'agent_id' => $agentId
                                ]);
                                $autoReplyText = "Thank you, {$autoReplyAdd} profile has been created.";
                                Log::info("Client created within transaction", [
                                    'client_id' => $client->id,
                                    'civil_no' => $data['civil_no']
                                ]);
                            } else {
                                if (!empty($data['passport_no']) && $client->passport_no !== $data['passport_no']) {
                                    $client->update([
                                        'phone' => $localNumberClient,
                                        'country_code' => $matchedCodeClientPhone ?? '+965',
                                        'date_of_birth' => $data['date_of_birth'] ?? null,
                                        'address' => $data['place_of_birth'] ?? null,
                                        'passport_no' => $data['passport_no'],
                                        'updated_at' => Carbon::parse($receivedAt),
                                    ]);
                                    $autoReplyText = "Thank you for updating {$autoReplyAdd} passport details.";
                                    Log::info("Client passport updated within transaction", [
                                        'client_id' => $client->id,
                                        'new_passport' => $data['passport_no']
                                    ]);
                                } else {
                                    $autoReplyText = "Thank you. We already have {$autoReplyAdd} passport information.";
                                }
                            }

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
                            throw $e; // Re-throw to be caught by outer catch
                        }
                    } else {
                        Log::error("No valid data from upload response", [
                            'response_data' => $data
                        ]);
                    }
                } else {
                    Log::error("Upload failed", [
                        'status' => $uploadResponse->status(),
                        'body' => $uploadResponse->body()
                    ]);
                }
            } catch (Exception $e) {
                Log::error("Client creation/upload error", [
                    'error' => $e->getMessage(),
                    'media_id' => $mediaId,
                    'phone' => $phone
                ]);
            }
        } else {
            Log::warning("File not found for processing", [
                'local_path' => $localPath,
                'media_id' => $mediaId
            ]);
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
}
