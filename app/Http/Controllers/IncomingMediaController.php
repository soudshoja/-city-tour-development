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
        $receivedAt = $request->input('data.date') ?? now();

        $agentId = 1; // default
        $agentPhone = $request->input('device.phone');
        $agentEmail = null;

        try {
            $agent = Agent::where('phone_number', $phone)->first();

            if (!$agent) {
                $agent = Agent::inRandomOrder()->first();
                Log::info("Fallback to random agent: " . ($agent ? $agent->name : 'None'));
            }

            if ($agent) {
                $agentId = $agent->id;
                $agentPhone = $agent->phone_number;
                $agentEmail = $agent->email;
            } else {
                $agentPhone = config('app.agent_default_phone', '+96522210017');
                $agentEmail = config('app.agent_default_email', 'admin@citytravelers.co');
                Log::warning("No agent found, using fallback credentials.");
            }
        } catch (\Exception $e) {
            Log::error("Agent lookup error: " . $e->getMessage());
            $agentPhone = config('app.agent_default_phone');
            $agentEmail = config('app.agent_default_email');
        }

        $mediaData = $request->input('media') ?? $request->input('data.media');
        $downloadLink = $mediaData['links']['download'] ?? null;

        if (!$downloadLink) {
            Log::info("No media to download.");
            return response()->json(['message' => 'No media found.'], 200);
        }

        $mimeType = $mediaData['mime'] ?? null;
        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            Log::warning("Unsupported MIME type: {$mimeType}");
            return response()->json(['message' => 'Unsupported media type.'], 200);
        }

        $filename = $mediaData['filename'] ?? 'file.jpg';
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';
        $mediaId = $mediaData['id'] ?? null;
        $caption = $mediaData['caption'] ?? null;

        $mediaUrl = str_starts_with($downloadLink, 'http')
            ? $downloadLink
            : config('services.resayil.base_url') . "chat/{$deviceId}/files/{$mediaId}/download";

        $localPath = null;

        try {
            $newFilename = 'media_' . time() . '_' . uniqid() . '.' . $extension;
            $response = Http::withHeaders(['Token' => config('services.resayil.api_token', '')])->get($mediaUrl);

            if ($response->ok()) {
                Storage::put("public/uploads/{$newFilename}", $response->body());
                $localPath = "uploads/{$newFilename}";
                Log::info("Media saved to: {$localPath}");
            } else {
                Log::error("Download failed, HTTP status: " . $response->status());
            }
        } catch (\Exception $e) {
            Log::error("Download exception: " . $e->getMessage());
        }

        $incomingMedia = null;

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
            Log::error("Failed to save IncomingMedia: " . $e->getMessage());
        }

        $autoReplyText = null;

        if ($localPath && Storage::exists("public/{$localPath}")) {
            try {
                $fullPath = storage_path("app/public/{$localPath}");
                $fileContent = file_get_contents($fullPath);
                $fileName = basename($fullPath);

                $aiService = new AIManager();
                $response = $aiService->extractPassportData($fileContent, $fileName);

                Log::info("AI response: " . json_encode($response));

                if ($response['status'] === 'success' && !empty($response['data']['civil_no']) && !empty($response['data']['name'])) {
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
                            'civil_no' => $data['civil_no'],
                            'passport_no' => $data['passport_no'] ?? null,
                            'old_passport_no' => $data['passport_no'] ?? null,
                            'agent_id' => $agentId,
                        ]);
                        $autoReplyText = "Thank you, your profile has been created.";
                        Log::info("New client created: {$client->id}");
                    } else {
                        if (!empty($data['passport_no']) && $client->passport_no !== $data['passport_no']) {
                            $client->update([
                                'phone' => $phone ?? $agentPhone,
                                'date_of_birth' => $data['date_of_birth'] ?? null,
                                'address' => $data['place_of_birth'] ?? null,
                                'passport_no' => $data['passport_no'],
                                'updated_at' => Carbon::parse($receivedAt),
                            ]);
                            $autoReplyText = "Thank you for updating your passport details.";
                            Log::info("Client passport updated: {$client->id}");
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
                    Log::error("Invalid or missing fields in AI response.");
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("AI or DB error: " . $e->getMessage());
            }
        }

        try {
            $to = $chatWid ?? $request->input('from');
            if ($to && $autoReplyText) {
                (new WhatsappController())->sendToResayil($to, $autoReplyText);
                Log::info("Auto-reply sent to {$to}");
            }
        } catch (\Exception $e) {
            Log::error("Auto-reply exception: " . $e->getMessage());
        }

        return response()->json(['message' => 'Webhook received successfully']);
    }

}
