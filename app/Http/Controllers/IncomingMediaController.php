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
use Carbon\Carbon;
class IncomingMediaController extends Controller
{
    public function handleResayilWebhook(Request $request)
    {
        Log::debug('Incoming Resayil Webhook:', $request->all());

        try {
            $phone = $request->input('data.fromNumber')
                ?? $request->input('phone')
                ?? $request->input('messages.0.from');

            $agentEmail = null;
            $agentPhone = $request->input('device.phone') ?? null;
            $agentDefaultId = "1";
            $agentDefaultPhone = $agentPhone;
            $agentDefaultEmail = "admin@citytravelers.co";
            Log::info("Agent Phone: {$agentPhone}");

            $deviceId = $request->input('device.id');
            $chatWid = $request->input('data.chat.id') ?? $request->input('data.from') ?? null;

            $fetchUrl = config('services.resayil.base_url') . "devices/{$deviceId}/departments";

            try {
                $responseFetch = Http::withHeaders([
                    'Token' => config('services.resayil.api_token', ''),
                ])->get($fetchUrl);

                if ($responseFetch->ok()) {
                    $departments = $responseFetch->json();
                    $allAgents = [];

                    foreach ($departments as $dept) {
                        foreach ($dept['agents'] ?? [] as $agent) {
                            if (isset($agent['role']) && $agent['role'] === 'agent') {
                                $allAgents[] = $agent;
                            }
                        }
                    }

                    if (!empty($allAgents)) {
                        $selectedAgent = $allAgents[array_rand($allAgents)];
                        $agentName = $selectedAgent['displayName'] ?? null;
                        $agentEmail = $selectedAgent['email'] ?? null;
                        Log::info("Randomly selected agent: {$agentName} ({$agentEmail})");

                        $agents = Agent::where('email', $agentEmail)->get();
                        if ($agents->isEmpty()) {
                            $agentId = $agentDefaultId;
                            $agentPhone = $agentDefaultPhone;
                            $agentEmail = $agentDefaultEmail;
                            Log::info("Agent not found in DB, using default phone: {$agentPhone}");
                        } else {
                            $agentId = $agents->first()->id ?? $agentDefaultId;
                            $agentPhone = $agents->first()->phone_number ?? $agentDefaultPhone;
                            $agentEmail = $agents->first()->email ?? $agentDefaultEmail;
                            Log::info("Agent found in DB | phone: {$agentPhone} | email: {$agentEmail}");
                        }
                    } else {
                        Log::warning("No agent with role 'agent' found.");
                    }
                } else {
                    Log::error("Failed to fetch departments. Status: {$responseFetch->status()} Body: {$responseFetch->body()}");
                }
            } catch (\Exception $e) {
                Log::error("Exception while fetching agent: " . $e->getMessage());
            }

            $mediaData = $request->input('media') ?? $request->input('data.media');
            $downloadLink = $mediaData['links']['download'] ?? null;

            if ($downloadLink) {
                Log::info("Media section with download link found.");

                $mediaId    = $mediaData['id'] ?? null;
                $mimeType   = $mediaData['mime'] ?? null;
                $caption    = $mediaData['caption'] ?? null;
                $receivedAt = $request->input('data.date') ?? now();
                $filename   = $mediaData['filename'] ?? 'file.jpg';
                $extension  = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';

                $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    Log::warning("Unsupported media type from {$phone}: {$mimeType}. Skipping.");
                    return response()->json(['message' => 'Unsupported media type.'], 200);
                }

                $mediaUrl = str_starts_with($downloadLink, 'http')
                    ? $downloadLink
                    : config('services.resayil.base_url') . "chat/{$deviceId}/files/{$mediaId}/download";

                try {
                    $newFilename = 'media_' . time() . '_' . uniqid() . '.' . $extension;
                    $response = Http::withHeaders([
                        'Token' => config('services.resayil.api_token', ''),
                    ])->get($mediaUrl);

                    if ($response->ok()) {
                        Storage::put("public/uploads/{$newFilename}", $response->body());
                        $localPath = "uploads/{$newFilename}";
                        Log::info("Media file saved to {$localPath}");
                    } else {
                        Log::error("Failed to download media. Status: {$response->status()} Body: {$response->body()}");
                        $localPath = null;
                    }
                } catch (\Exception $e) {
                    Log::error("Error downloading media: " . $e->getMessage());
                    $localPath = null;
                }

                try {
                    $newIncomingMedia = IncomingMedia::create([
                        'phone'       => $phone,
                        'media_id'    => $mediaId,
                        'mime_type'   => $mimeType,
                        'caption'     => $caption,
                        'received_at' => Carbon::parse($receivedAt),
                        'file_path'   => $localPath,
                        'agent_id'    => $agentId,
                        'agent_phone' => $agentPhone,
                        'agent_email' => $agentEmail,
                        'media_type'  => 'whatsapp',
                    ]);
                    Log::info("Saved incoming media from {$phone}, media_id: {$mediaId}");
                } catch (\Exception $e) {
                    Log::error("Error saving IncomingMedia: " . $e->getMessage());
                }

                if ($localPath && Storage::exists("public/{$localPath}")) {
                    try {
                        $fullPath = storage_path("app/public/{$localPath}");

                        $uploadResponse = Http::asMultipart()
                            ->attach('file', file_get_contents($fullPath), basename($fullPath))
                            ->post(config('app.url') . '/api/chat/upload');

                        if ($uploadResponse->successful()) {
                            Log::info("Posted file to handleFileUpload (API): " . $uploadResponse->body());

                            $data = $uploadResponse->json('data');

                            if ($data && isset($data['name']) && isset($data['civil_no'])) {
                                $checkClient = Client::where('civil_no', $data['civil_no'])->first();

                                if (!$checkClient) {
                                    DB::beginTransaction();
                                    $client = Client::create([
                                        'name'           => $data['name'],
                                        'email'          => $agents->first()->email ?? 'admin@citytravelers.co',
                                        'status'         => 'active',
                                        'phone'          => $phone ?? $agents->first()->phone_number,
                                        'date_of_birth'  => $data['date_of_birth'] ?? null,
                                        'address'        => $data['place_of_birth'] ?? null,
                                        'civil_no'       => $data['civil_no'] ?? null,
                                        'passport_no'    => $data['passport_no'] ?? null,
                                        'old_passport_no'=> $data['passport_no'] ?? null,
                                        'agent_id'       => $agents->first()->id ?? 1
                                    ]);
                                    DB::commit();

                                    $incomingMedia = IncomingMedia::where('file_path', $localPath)->first();
                                    if ($incomingMedia) {
                                        $incomingMedia->client_id = $client->id;
                                        $incomingMedia->save();
                                    }

                                    Log::info("Client created successfully: ID {$client->id}");
                                    $autoReplyText = "Thank you, your profile has been created.";
                                } else {
                                    if (!empty($data['passport_no']) && $checkClient->passport_no !== $data['passport_no']) {
                                        $checkClient->phone = $phone ?? $agents->first()->phone_number;
                                        $checkClient->date_of_birth = $data['date_of_birth'] ?? null;
                                        $checkClient->address = $data['place_of_birth'] ?? null;
                                        $checkClient->passport_no = $data['passport_no'];
                                        $checkClient->updated_at = Carbon::parse($receivedAt);
                                        $checkClient->save();

                                        Log::info("Updated passport number for client ID {$checkClient->id}");
                                        $autoReplyText = "Thank you for updating your passport details.";
                                    } else {
                                        Log::info("No changes to client {$checkClient->id}");
                                        $autoReplyText = "Thank you. We already have your passport information.";
                                    }

                                    $incomingMedia = IncomingMedia::where('file_path', $localPath)->first();
                                    if ($incomingMedia) {
                                        $incomingMedia->client_id = $checkClient->id;
                                        $incomingMedia->save();
                                    }
                                }
                            } else {
                                Log::error("No valid data returned from handleFileUpload response.");
                            }
                        } else {
                            Log::error("Failed to post to handleFileUpload (API): {$uploadResponse->status()} - {$uploadResponse->body()}");
                        }
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error("Exception during upload and client creation: " . $e->getMessage());
                    }
                }

                try {
                    $to = $request->input('data.from') ?? $request->input('from');
                    if ($to) {
                        $clientWAController = new WhatsappController();
                        $clientWAController->sendToResayil($to, $autoReplyText);
                        Log::info("Sent auto-reply to {$to}");
                    } else {
                        Log::warning("Auto-reply failed: 'from' not found in webhook.");
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to send auto-reply to {$phone}: " . $e->getMessage());
                }
            } else {
                Log::info("No media download link found in webhook.");
            }
        } catch (\Exception $e) {
            Log::error("Unexpected error in handleResayilWebhook: " . $e->getMessage());
        }

        return response()->json(['message' => 'Webhook received successfully']);
    }
}
