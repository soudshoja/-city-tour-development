<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IncomingMedia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

            $agentPhone = $request->input('device.phone') ?? null;
            Log::info("Agent Phone: {$agentPhone}");

            $deviceId = $request->input('device.id');
            $chatWid = $request->input('data.chat.id') ?? $request->input('data.from') ?? null;

            // Fetch agent (owner) info
            $url = "https://api.resayil.io/v1/device/{$deviceId}/profile";
            $response = Http::withToken(config('services.whatsapp.token', ''))
                ->acceptJson()
                ->get($url);

            $agentEmail = $agentName = null;
            if ($response->successful()) {
                $ownerData = $response->json();
                $agentEmail = $ownerData['businessProfile']['email'] ?? null;
                $agentName  = $ownerData['name'] ?? null;
                Log::info("Fetched owner info: Agent Name: {$agentName}, Agent Email: {$agentEmail}");
            } else {
                Log::warning("Failed to fetch owner info. HTTP Status: {$response->status()}");
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
                    : "https://api.resayil.io/v1/files/{$mediaId}/download";

                // Download using Resayil's secure token header
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
