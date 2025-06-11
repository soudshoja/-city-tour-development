<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IncomingMedia;
use App\Models\Agent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;
class IncomingMediaController extends Controller
{
    public function handleResayilWebhook(Request $request)
    {
        Log::debug('Incoming Resayil Webhook:', $request->all());

        try {
            // Extract customer phone number
            $phone = $request->input('data.fromNumber')
                ?? $request->input('phone')
                ?? $request->input('messages.0.from');

            $agentEmail = null;
            $agentPhone = $request->input('device.phone') ?? null;
            $agentDefaultPhone = $agentPhone;
            $agentDefaultEmail = "admin@citytravelers.co";
            Log::info("Agent Phone: {$agentPhone}");

            $deviceId = $request->input('device.id');
            $chatWid = $request->input('data.chat.id') ?? $request->input('data.from') ?? null;

            $fetchUrl = "https://api.resayil.io/v1/devices/{$deviceId}/departments";

            try {
                $responseFetch = Http::withHeaders([
                    'Token' => config('services.whatsapp.token', ''),
                ])->get($fetchUrl);

                if ($responseFetch->ok()) {
                    $departments = $responseFetch->json();
                    $allAgents = [];

                    // Collect all agents with role 'agent'
                    foreach ($departments as $dept) {
                        foreach ($dept['agents'] ?? [] as $agent) {
                            if (isset($agent['role']) && $agent['role'] === 'agent') {
                                $allAgents[] = $agent;
                            }
                        }
                    }

                    if (!empty($allAgents)) {
                        // Pick a random agent
                        $selectedAgent = $allAgents[array_rand($allAgents)];
                        $agentName = $selectedAgent['displayName'] ?? null;
                        $agentEmail = $selectedAgent['email'] ?? null;
                        Log::info("Randomly selected agent: {$agentName} ({$agentEmail})");

                        // Check if agent exists in DB
                        $agents = Agent::where('email', $agentEmail)->get();
                        if ($agents->isEmpty()) {
                            $agentPhone = $agentDefaultPhone;
                            $agentEmail = $agentDefaultEmail;
                            Log::info("Agent not found in DB, using default phone: {$agentPhone}");
                        } else {
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


            // Extract media data (support both formats)
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

                // Define allowed mime types
                $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    Log::warning("Unsupported media type from {$phone}: {$mimeType}. Skipping.");
                    return response()->json(['message' => 'Unsupported media type.'], 200);
                }

                // Prepare media download URL
                $mediaUrl = str_starts_with($downloadLink, 'http')
                    ? $downloadLink
                    : "https://api.resayil.io/v1/chat/{$deviceId}/files/{$mediaId}/download";

                try {
                    $newFilename = 'media_' . time() . '_' . uniqid() . '.' . $extension;
                    $response = Http::withHeaders([
                        'Token' => config('services.whatsapp.token', ''),
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

                // Save media record
                try {
                    IncomingMedia::create([
                        'phone'       => $phone,
                        'media_id'    => $mediaId,
                        'mime_type'   => $mimeType,
                        'caption'     => $caption,
                        'received_at' => \Carbon\Carbon::parse($receivedAt),
                        'file_path'   => $localPath,
                        'agent_phone' => $agentPhone,
                        'agent_email' => $agentEmail,
                    ]);
                    Log::info("Saved incoming media from {$phone}, media_id: {$mediaId}");
                } catch (\Exception $e) {
                    Log::error("Error saving IncomingMedia: " . $e->getMessage());
                }

                if ($localPath && Storage::exists("public/{$localPath}")) {
                    try {
                        $fullPath = storage_path("app/public/{$localPath}");

                        $response = Http::asMultipart()
                            ->attach('file', file_get_contents($fullPath), basename($fullPath))
                            ->post(config('app.url') . '/api/chat/upload'); 

                        if ($response->successful()) {
                            Log::info("Posted file to handleFileUpload (API): " . $response->body());
                        } else {
                            Log::error("Failed to post to handleFileUpload (API): {$response->status()} - {$response->body()}");
                        }
                    } catch (\Exception $e) {
                        Log::error("Error posting file to handleFileUpload (API): " . $e->getMessage());
                    }
                }

                // Auto-reply
                try {
                    $to = $request->input('data.from') ?? $request->input('from');
                    if ($to) {
                        $clientWAController = new WhatsappController();
                        $clientWAController->sendToResayil($to, "We have received your image, thank you.");
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
