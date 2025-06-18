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
                $fullPath = storage_path("app/public/{$localPath}");
                $fileContent = file_get_contents($fullPath);
                $fileName = basename($fullPath);

                $aiService = new AIManager(); 
                $response = $aiService->extractPassportData($fileContent, $fileName);

                Log::info("AI passport extraction response: " . json_encode($response));

                if ($response['status'] === 'success' && is_array($response['data']) && !empty($response['data']['name']) && !empty($response['data']['civil_no'])) {
                    $data = $response['data'];

                    DB::beginTransaction();

                    $client = Client::where('civil_no', $data['civil_no'])->first();

                    if (!$client) {
                        $client = Client::create([
                            'name' => $data['name'],
                            'email' => $agentEmail,
                            'status' => 'active',
                            'phone' => $phone ?? $agentPhone,
                            'date_of_birth' => $data['date_of_birth'] ?? null,
                            'address' => $data['place_of_birth'] ?? null,
                            'civil_no' => $data['civil_no'] ?? null,
                            'passport_no' => $data['passport_no'] ?? null,
                            'old_passport_no' => $data['passport_no'] ?? null,
                            'agent_id' => $agentId,
                            'nationality' => $data['nationality'] ?? null,
                            'date_of_issue' => $data['date_of_issue'] ?? null,
                            'date_of_expiry' => $data['date_of_expiry'] ?? null,
                            'place_of_issue' => $data['place_of_issue'] ?? null,
                        ]);
                        $autoReplyText = "Thank you, your profile has been created.";
                        Log::info("Client created: ID {$client->id}");
                    } else {
                        if (!empty($data['passport_no']) && $client->passport_no !== $data['passport_no']) {
                            $client->update([
                                'phone' => $phone ?? $agentPhone,
                                'date_of_birth' => $data['date_of_birth'] ?? null,
                                'address' => $data['place_of_birth'] ?? null,
                                'passport_no' => $data['passport_no'],
                                'updated_at' => Carbon::parse($receivedAt),
                                'nationality' => $data['nationality'] ?? null,
                                'date_of_issue' => $data['date_of_issue'] ?? null,
                                'date_of_expiry' => $data['date_of_expiry'] ?? null,
                                'place_of_issue' => $data['place_of_issue'] ?? null,
                            ]);
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
                } else {
                    Log::error("Required data missing from extraction result.");
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Client creation/upload error: " . $e->getMessage());
            }
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

        return response()->json(['message' => 'Webhook received successfully']);
    }

}
