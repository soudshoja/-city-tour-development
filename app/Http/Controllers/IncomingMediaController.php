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

        $deviceId = $request->input('device.id');
        $chatWid = $request->input('data.chat.id') ?? $request->input('data.from');

        $senderAgent = "";
        $agentId = 1;
        $agentPhone = $request->input('device.phone');
        $agentEmail = null;
        $fallbackPhone = config('app.agent_default_phone', '+96522210017');
        $fallbackEmail = config('app.agent_default_email', 'ops@citytravelers.co');

        try {
            $agent = Agent::where('phone_number', $phone)->first();

            if ($agent) {
                Log::info("Sender is an agent: {$agent->name} ({$phone})");

                $waitingForClientPhone = Cache::get('agent_waiting_for_client_phone_' . $phone);

                $clientPhoneReply = trim(
                    $request->input('data.text')
                        ?? $request->input('data.body')
                        ?? $request->input('messages.0.body')
                        ?? $request->input('messages.0.text')
                        ?? ''
                );

                if (strtolower($clientPhoneReply) === 'restart') {
                    Cache::forget('pending_media_' . $phone);
                    Cache::forget('agent_client_phone_' . $phone);
                    Cache::forget('agent_waiting_for_client_phone_' . $phone);
                    
                    $to = $request->input('data.from') ?? $request->input('from');
                    $this->sendWhatsAppMessage($to, "Okay, let's start fresh. Please send your client's media document again.", 'agent_restart');
                    return response()->json(['message' => 'Restart triggered'], 200);
                }

                if (!$waitingForClientPhone) {
                    $mediaData = $request->input('media') ?? $request->input('data.media');
                    if ($mediaData) {
                        Cache::put('pending_media_' . $phone, $mediaData, now()->addMinutes(30));
                        Log::info("Media cached for agent {$phone} until client phone is received.");
                    }

                    $to = $request->input('data.from') ?? $request->input('from');
                    $this->sendWhatsAppMessage($to, "Hello {$agent->name}, please reply with your client's phone number to proceed.\n(eg: +96522210017)", 'agent_phone_request');

                    Cache::put('agent_waiting_for_client_phone_' . $phone, true, now()->addMinutes(30));
                    return response()->json(['message' => 'Agent prompt sent, waiting for client phone input.'], 200);
                }

                if (preg_match('/^\+?\d{6,15}$/', $clientPhoneReply)) {
                    Cache::put('agent_client_phone_' . $phone, $clientPhoneReply, now()->addHour());
                    Cache::forget('agent_waiting_for_client_phone_' . $phone);

                    $to = $request->input('data.from') ?? $request->input('from');
                    $this->sendWhatsAppMessage($to, "Your client's phone number {$clientPhoneReply} received.\nPlease hold while we process the data...", 'agent_phone_confirmed');
                    
                    // Don't return here - continue processing with cached media
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

                $agentId = $agent->id;
                $agentPhone = $agent->phone_number;
                $agentEmail = $agent->email;
                $senderAgent = 'yes';
            } else {
                // Handle client (non-agent) messages
                Log::info("Sender is a client: {$phone}");
                
                // Check if client sent text message
                $clientTextMessage = trim(
                    $request->input('data.text')
                        ?? $request->input('data.body')
                        ?? $request->input('messages.0.body')
                        ?? $request->input('messages.0.text')
                        ?? ''
                );

                // If client sent text, provide helpful guidance with escalating reminders
                if (!empty($clientTextMessage) && !$request->input('media') && !$request->input('data.media')) {
                    $to = $request->input('data.from') ?? $request->input('from');
                    
                    // Track how many times this client has sent text messages
                    $textMessageCount = Cache::get('client_text_count_' . $phone, 0) + 1;
                    Cache::put('client_text_count_' . $phone, $textMessageCount, now()->addHours(2));
                    
                    // Stop responding after 5 attempts to prevent spam
                    if ($textMessageCount > 5) {
                        Log::info("Client exceeded text message limit, ignoring", [
                            'phone' => $phone,
                            'message_count' => $textMessageCount
                        ]);
                        return response()->json(['message' => 'Client exceeded text limit, ignored'], 200);
                    }
                    
                    $helpMessage = $this->getClientHelpMessage($textMessageCount);
                    
                    Log::info("Client sent text message", [
                        'phone' => $phone,
                        'message_count' => $textMessageCount,
                        'text_preview' => substr($clientTextMessage, 0, 50)
                    ]);
                    
                    $this->sendWhatsAppMessage($to, $helpMessage, "client_help_attempt_{$textMessageCount}");
                    return response()->json(['message' => 'Help message sent to client'], 200);
                }

                $agent = Agent::inRandomOrder()->first();
                Log::info("Selected random agent: " . ($agent ? "{$agent->name} ({$agent->email})" : 'None'));

                $senderAgent = 'no';
                if ($agent) {
                    $agentId = $agent->id;
                    $agentPhone = $agent->phone_number;
                    $agentEmail = $agent->email;
                } else {
                    $agentPhone = $fallbackPhone;
                    $agentEmail = $fallbackEmail;
                }
            }
        } catch (Exception $e) {
            Log::error("Error fetching agent: " . $e->getMessage());
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
            $this->sendWhatsAppMessage($to, "It looks like the document you sent has expired or wasn't received. Please re-upload it.", 'media_missing');
            return response()->json(['message' => 'Media missing or expired.'], 200);
        }

        // Clear text message counter since client sent media
        Cache::forget('client_text_count_' . $phone);
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

        // Retrieve client phone from cache if exists (only for actual agents, not random agents)
        $clientPhoneFromAgent = null;
        if ($senderAgent === 'yes' && Cache::has('agent_client_phone_' . $phone)) {
            $clientPhoneFromAgent = Cache::get('agent_client_phone_' . $phone);
            // Clear cache after use to avoid reuse
            Cache::forget('agent_client_phone_' . $phone);
            Log::info("Retrieved client phone from agent cache", [
                'agent_phone' => $phone,
                'client_phone' => $clientPhoneFromAgent
            ]);
        }

        if ($senderAgent === 'yes') {
            $clientPhoneNumber = $clientPhoneReply;
            $autoReplyAdd = "your client's";
        } else {
            $clientPhoneNumber = $phone;
            $autoReplyAdd = "your";
        }

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

                    if ($data && isset($data['name'], $data['civil_no'])) {
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
                                    'name' => $data['name'],
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

                            // Clean up cache
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
     * Get escalating help messages for clients who keep sending text
     */
    private function getClientHelpMessage($attemptCount)
    {
        switch ($attemptCount) {
            case 1:
                return "Thank you for contacting us. To process your request, please upload a clear image or PDF of your passport or civil ID document.\n\nAccepted formats:\n• JPEG/JPG images\n• PNG images\n• PDF documents\n\nFor best results, ensure the document is well-lit and all details are clearly visible.";
                
            case 2:
                return "We appreciate your message, however our system requires document verification to proceed. Please attach your passport or civil ID document as an image or PDF file.\n\nText messages cannot be processed automatically. Once you upload your document, we will review it promptly.";
                
            case 3:
                return "IMPORTANT: Document verification is required to continue with your application.\n\nPlease note:\n• Text messages cannot be processed\n• Only document uploads (passport/civil ID) can be reviewed\n• Supported formats: JPG, PNG, PDF\n\nPlease upload your document to proceed.";
                
            default: // 4th attempt and beyond
                return "NOTICE: This service processes identity document verification only.\n\nRequired action: Upload your passport or civil ID document in JPG, PNG, or PDF format.\n\nNo additional text responses will be provided. Please submit your document to continue with the verification process.";
        }
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
