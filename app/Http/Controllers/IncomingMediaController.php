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

class IncomingMediaController extends Controller
{
    public function handleResayilWebhook(Request $request)
    {
        Log::debug('Incoming Resayil Webhook:', $request->all());

        $type = $request->input('data.type');
        Log::info("Source type: {$type}");

        $phone = $request->input('data.fromNumber')
            ?? $request->input('phone')
            ?? $request->input('messages.0.from');

        // Clear agent cache after successful media handling
        // Cache::forget('agent_client_phone_' . $phone);
        // Cache::forget('agent_waiting_for_client_phone_' . $phone);

        $deviceId = $request->input('device.id');
        $chatWid = $request->input('data.chat.id') ?? $request->input('data.from');

        // Agent fallback setup
        $senderAgent = "";
        $agentId = 1;
        $agentPhone = $request->input('device.phone');
        $agentEmail = null;
        $fallbackPhone = config('app.agent_default_phone', '+96522210017');
        $fallbackEmail = config('app.agent_default_email', 'ops@citytravelers.co');

        try {
            // Check if sender is an agent
            $agent = Agent::where('phone_number', $phone)->first();

            if ($agent) {
                Log::info("Sender is an agent: {$agent->name} ({$phone})");

                // Check if we are currently waiting for client phone number from this agent
                $waitingForClientPhone = Cache::get('agent_waiting_for_client_phone_' . $phone);

                // 1) If not waiting, send prompt and set waiting flag
                if (!$waitingForClientPhone) {
                    $promptMessage = "Hello {$agent->name}, please reply with your client's phone number to proceed.";

                    // Store media temporarily in cache if available
                    $mediaData = $request->input('media') ?? $request->input('data.media');
                    if ($mediaData) {
                        Cache::put('pending_media_' . $phone, $mediaData, now()->addMinutes(30));
                        Log::info("Media cached for agent {$phone} until client phone is received.");
                    }

                    $wa = new WhatsappController();
                    $to = $request->input('data.from') ?? $request->input('from');

                    if ($to) {
                        $wa->sendToResayil($to, $promptMessage);
                        Log::info("Prompt sent to agent {$to}");
                    } else {
                        Log::warning("Could not find recipient phone to send prompt to agent.");
                    }

                    // Mark as waiting for client phone number for 30 mins
                    Cache::put('agent_waiting_for_client_phone_' . $phone, true, now()->addMinutes(30));

                    // Stop processing until we get client phone number reply
                    return response()->json(['message' => 'Agent prompt sent, waiting for client phone input.'], 200);
                }

                // 2) If waiting, treat this message as client's phone number reply
                $clientPhoneReply = trim(
                    $request->input('data.text')
                        ?? $request->input('data.body')
                        ?? $request->input('messages.0.body')
                        ?? $request->input('messages.0.text')
                        ?? ''
                );

                // Simple validation: basic phone number pattern (digits and + allowed)
                if (preg_match('/^\+?\d{6,15}$/', $clientPhoneReply)) {
                    // Save client phone in cache keyed by agent phone, valid for 1 hour
                    Cache::put('agent_client_phone_' . $phone, $clientPhoneReply, now()->addHour());

                    // Clear waiting flag
                    Cache::forget('agent_waiting_for_client_phone_' . $phone);

                    // Send confirmation to agent
                    $wa = new WhatsappController();
                    $to = $request->input('data.from') ?? $request->input('from');

                    if ($to) {
                        $wa->sendToResayil($to, "Your client phone number {$clientPhoneReply} received.");
                        Log::info("Confirmed client phone received from agent {$to}");
                    }

                    //return response()->json(['message' => 'Client phone number received and stored.'], 200);
                } else {
                    // Invalid phone number format, ask again
                    $wa = new WhatsappController();
                    $to = $request->input('data.from') ?? $request->input('from');

                    if ($to) {
                        $wa->sendToResayil($to, "The phone number you sent seems invalid. Please send a valid phone number including country code.");
                        Log::warning("Invalid client phone number from agent {$to}: {$clientPhoneReply}");
                    }

                    Cache::forget('pending_media_' . $phone);  // Don't reuse old media if phone is invalid

                    return response()->json(['message' => 'Invalid client phone number received.'], 200);
                }

                $agentId = $agent->id;
                $agentPhone = $agent->phone_number;
                $agentEmail = $agent->email;
                $senderAgent = 'yes';
            } else {
                // If not an agent, fallback to pick a random agent as before
                $agent = Agent::inRandomOrder()->first();
                Log::info("Selected random agent: " . ($agent ? "{$agent->name} ({$agent->email})" : 'None'));

                $senderAgent = 'no';
                if ($agent) {
                    $agentId = $agent->id;
                    $agentPhone = $agent->phone_number;
                    $agentEmail = $agent->email;
                } else {
                    $agentId = '1';
                    $agentPhone = $fallbackPhone;
                    $agentEmail = $fallbackEmail;
                    Log::warning("No agent found, using fallback.");
                }
            }
        } catch (\Exception $e) {
            Log::error("Error fetching agent: " . $e->getMessage());
            $agentPhone = $fallbackPhone;
            $agentEmail = $fallbackEmail;
        }

        // Try to get media from current request, or fallback to cached media (if available)
        $mediaData = $request->input('media')
            ?? $request->input('data.media')
            ?? Cache::get('pending_media_' . $phone);

        if ($mediaData) {
            // Clean up after using it
            Cache::forget('pending_media_' . $phone);
        }

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
                Log::info("Media downloaded: {$localPath}");
            } else {
                Log::error("Failed to download media: " . $response->status());
            }
        } catch (\Exception $e) {
            Log::error("Media download exception: " . $e->getMessage());
        }

        $incomingMedia = null;

        // Retrieve client phone from cache if exists (only for agents)
        $clientPhoneFromAgent = null;
        if ($agent && Cache::has('agent_client_phone_' . $agentPhone)) {
            $clientPhoneFromAgent = Cache::get('agent_client_phone_' . $agentPhone);
            // Optionally clear cache after use to avoid reuse
            Cache::forget('agent_client_phone_' . $agentPhone);
        }

        if ($senderAgent == 'yes') {
            $clientPhoneNumber = $clientPhoneReply;
            $agentPhoneNumber = $agentPhone;
            $autoReplyAdd = 'your client';
        } else {
            $clientPhoneNumber = $phone;
            $agentPhoneNumber = $agentPhone;
            $autoReplyAdd = 'your';
        }

        try {
            // Normalize phone number from the original sender number
            $phoneNormalized = trim($phone);
            $normalizedPhone = preg_replace('/\s+/', '', $phoneNormalized);

            // Get all dialing codes from DB
            $dialingCodes = DB::table('countries')->pluck('dialing_code');
            $dialingCodes = $dialingCodes->sortByDesc(fn($code) => strlen($code));

            $matchedCode = null;
            foreach ($dialingCodes as $code) {
                if (strpos($normalizedPhone, $code) === 0) {
                    $matchedCode = $code;
                    break;
                }
            }

            $localNumber = $matchedCode ? substr($normalizedPhone, strlen($matchedCode)) : $normalizedPhone;
            $localNumber = preg_replace('/\D+/', '', $localNumber);

            // Use client phone from agent if available, else use localNumber or fallback agent phone
            $finalPhone = $clientPhoneFromAgent ?? $localNumber ?? $agentPhone;

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
        } catch (\Exception $e) {
            Log::error("Error saving IncomingMedia: " . $e->getMessage());
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
                    Log::info("Upload response received");

                    if ($data && isset($data['name'], $data['civil_no'])) {
                        DB::beginTransaction();

                        // Use finalPhone here for client creation and update
                        $client = Client::where('civil_no', $data['civil_no'])->first();

                        if (!$client) {
                            $client = Client::create([
                                'name' => $data['name'],
                                'email' => $agentEmail,
                                'status' => 'active',
                                'phone' => $clientPhoneNumber,
                                'country_code' => $matchedCode ?? '+965',
                                'date_of_birth' => $data['date_of_birth'] ?? null,
                                'address' => $data['place_of_birth'] ?? null,
                                'civil_no' => $data['civil_no'] ?? null,
                                'passport_no' => $data['passport_no'] ?? null,
                                'old_passport_no' => $data['passport_no'] ?? null,
                                'agent_id' => $agentId
                            ]);
                            $autoReplyText = "Thank you, {$autoReplyAdd} profile has been created.";
                            Log::info("Client created: ID {$client->id}");
                        } else {
                            if (!empty($data['passport_no']) && $client->passport_no !== $data['passport_no']) {
                                $client->update([
                                    'phone' => $finalPhone,
                                    'country_code' => $matchedCode ?? '+965',
                                    'date_of_birth' => $data['date_of_birth'] ?? null,
                                    'address' => $data['place_of_birth'] ?? null,
                                    'passport_no' => $data['passport_no'],
                                    'updated_at' => Carbon::parse($receivedAt),
                                ]);
                                $autoReplyText = "Thank you for updating {$autoReplyAdd} passport details.";
                                Log::info("Client passport updated: ID {$client->id}");
                            } else {
                                $autoReplyText = "Thank you. We already have {$autoReplyAdd} passport information.";
                            }
                        }

                        if ($incomingMedia) {
                            $incomingMedia->client_id = $client->id;
                            $incomingMedia->save();
                        }

                        DB::commit();

                        Cache::forget('agent_client_phone_' . $phone);
                        Cache::forget('agent_waiting_for_client_phone_' . $phone);
                    } else {
                        Log::error("No valid data from upload response.");
                    }
                } else {
                    Log::error("Upload failed: " . $uploadResponse->status() . ' ' . $uploadResponse->body());
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Client creation/upload error: " . $e->getMessage());
            }
        }

        // Auto-reply
        try {
            $to = $request->input('data.from') ?? $request->input('from');
            if ($to && $autoReplyText) {
                $wa = new WhatsappController();
                $wa->sendToResayil($to, $autoReplyText);
                Log::info("Auto-reply sent to {$to}");
            } else {
                Log::warning("Missing recipient or message for auto-reply.");
            }
        } catch (\Exception $e) {
            Log::error("Auto-reply failed: " . $e->getMessage());
        }

        return response()->json(['message' => 'Webhook received successfully']);
    }
}
