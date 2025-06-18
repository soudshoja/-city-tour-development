<?php

namespace App\Http\Controllers;

use App\AI\AIManager;
use Illuminate\Http\Request;
use App\Models\IncomingMedia;
use App\Models\Agent;
use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class IncomingMediaController extends Controller
{
    public function handleResayilWebhook(Request $request)
    {
        Log::debug('Incoming Resayil Webhook:', $request->all());

        $phone = $request->input('data.fromNumber')
            ?? $request->input('phone')
            ?? $request->input('messages.0.from');

        $deviceId = $request->input('device.id');
        $chatWid = $request->input('data.chat.id') ?? $request->input('data.from');

        $agentEmail = null;
        $agentPhone = $request->input('device.phone') ?? null;
        $agentId = 1; // default fallback
        $fallbackPhone = config('app.agent_default_phone', '+96522210017');
        $fallbackEmail = config('app.agent_default_email', 'admin@citytravelers.co');

        try {
            $agent = Agent::where('phone_number', $phone)->first();

            if (!$agent) {
                $agent = Agent::inRandomOrder()->first();
                Log::info("Selected random agent: " . ($agent ? "{$agent->name} ({$agent->email})" : 'None'));
            }

            if ($agent) {
                $agentId = $agent->id;
                $agentPhone = $agent->phone_number;
                $agentEmail = $agent->email;
            } else {
                $agentPhone = $fallbackPhone;
                $agentEmail = $fallbackEmail;
                Log::warning("No agent found, using fallback.");
            }
        } catch (\Exception $e) {
            Log::error("Error fetching agent: " . $e->getMessage());
            $agentPhone = $fallbackPhone;
            $agentEmail = $fallbackEmail;
        }

        $mediaData = $request->input('media') ?? $request->input('data.media');
        $downloadLink = $mediaData['links']['download'] ?? null;

        if (!$downloadLink) {
            Log::info("No media download link found.");
            return response()->json(['message' => 'No media found.'], 200);
        }

        $mediaId = $mediaData['id'] ?? null;
        $mimeType = $mediaData['mime'] ?? null;
        $caption = $mediaData['caption'] ?? null;
        $receivedAt = $request->input('data.date') ?? now();
        $filename = $mediaData['filename'] ?? 'file.jpg';
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';

        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            Log::warning("Unsupported media type: {$mimeType}");
            return response()->json(['message' => 'Unsupported media type.'], 200);
        }

        $mediaUrl = str_starts_with($downloadLink, 'http')
            ? $downloadLink
            : config('services.resayil.base_url') . "chat/{$deviceId}/files/{$mediaId}/download";

        $localPath = null;
        try {
            $newFilename = 'media_' . time() . '_' . uniqid() . '.' . $extension;

            $response = Http::withHeaders([
                'Token' => config('services.resayil.api_token', ''),
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

        try {
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
                $fullFilePath = storage_path("app/public/{$localPath}");

                // $uploadResponse = Http::asMultipart()
                //     ->attach('file', file_get_contents($fullPath), basename($fullPath))
                //     ->post(config('app.url') . '/api/chat/upload');
                
                Log::info('Processing passport file with AI:', [
                    'fileName' => $newFilename,
                    'filePath' => $fullFilePath
                ]);

                $aiManager = new AIManager();
                $response = $aiManager->extractPassportData($fullFilePath, $newFilename);

                Log::info('AI passport extraction response:', ['response' => $response]);

                if ($response['status'] === 'success') {
                    $passportData = $response['data'];

                    return response()->json([
                        'success' => true,
                        'message' => 'Passport data extracted successfully using AI!',
                        'data' => $passportData,
                    ], 200);

                    if ($data && isset($data['name'], $data['civil_no'])) {
                        $client = Client::where('civil_no', $data['civil_no'])->first();

                        DB::beginTransaction();

                        $clientData = [
                            'name' => $data['name'],
                            'email' => $agentEmail,
                            'status' => 'active',
                            'phone' => $phone ?? $agentPhone,
                            'date_of_birth' => $data['date_of_birth'] ?? null,
                            'address' => $data['place_of_birth'] ?? null,
                            'civil_no' => $data['civil_no'] ?? null,
                            'passport_no' => $data['passport_no'] ?? null,
                            'old_passport_no' => $data['passport_no'] ?? null,
                            'nationality' => $data['nationality'] ?? null,
                            'agent_id' => $agentId,
                        ];

                        if (!$client) {
                            $client = Client::create($clientData);
                            $autoReplyText = "Thank you, your profile has been created.";
                            Log::info("Client created: ID {$client->id}");
                        } else {
                            if (!empty($data['passport_no']) && $client->passport_no !== $data['passport_no']) {
                                $client->update(array_merge($clientData, [
                                    'updated_at' => Carbon::parse($receivedAt),
                                ]));
                                $autoReplyText = "Thank you for updating your passport details.";
                                Log::info("Client passport updated: ID {$client->id}");
                            } else {
                                $autoReplyText = "Thank you. We already have your passport information.";
                            }
                        }

                        if ($incomingMedia) {
                            $incomingMedia->client_id = $client->id;
                            $incomingMedia->save();
                        }

                        DB::commit();
                    }

                } else {
                    Log::error('AI passport extraction failed: ' . $response['message']);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to extract passport data using AI: ' . $response['message'],
                        'errors' => $response['message'],
                    ], 400);
                }

            } catch (\Exception $e) {
                Log::error('Failed to process passport with AI: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Error processing passport with AI',
                    'errors' => $e->getMessage(),
                ], 400);
            }

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

        }
        
        return response()->json(['message' => 'Webhook received successfully']);
    }
}
