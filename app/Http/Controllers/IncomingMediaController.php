<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IncomingMedia;
use App\Models\Client;
use Illuminate\Support\Facades\Log;

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

            // Extract agent phone and email (Resayil device format)
            $agentPhone = $request->input('device.phone') ?? null;

            Log::info("Agent Phone: {$agentPhone}");

            $deviceId = $request->input('device.id');
            $chatWid = $request->input('data.chat.id') ?? $request->input('data.from') ?? null;

            $client = new Client();

            $url = "https://api.resayil.io/v1/chat/{$deviceId}/chats/{$chatWid}/owner";

            $response = $client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.whatsapp.token', ''),
                    'Accept' => 'application/json',
                ],
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() == 200) {
                $ownerData = json_decode($response->getBody(), true);

                $agentName = $ownerData['agent'] ?? null;
                $agentEmail = $ownerData['email'] ?? null;
                $agentDepartment = $ownerData['department'] ?? null;

                Log::info("Fetched owner info: Agent={$agentName}, Email={$agentEmail}, Department={$agentDepartment}");
            }


            // Extract media data — support both formats (root or data.media)
            $mediaData = $request->input('media') ?? $request->input('data.media');

            $downloadLink = $mediaData['links']['download'] ?? null;
            $baseUrl = 'https://wa.resayil.io'; 
            $mediaUrl = null;

            if ($downloadLink) {

                Log::info("Media section with download link found in webhook.");

                $mediaId = $mediaData['id'] ?? null;
                $mimeType = $mediaData['mime'] ?? null;
                $caption = $mediaData['caption'] ?? null;
                $receivedAt = $request->input('data.date') ?? now();
                $filename = $mediaData['filename'] ?? null;

                $mediaUrl = rtrim($baseUrl, '/') . $downloadLink;

                if (str_starts_with($downloadLink, 'http')) {
                    $mediaUrl = $downloadLink; // Use as-is if it's a full URL
                } else {
                    $baseUrl = 'https://wa.resayil.io'; // fallback
                    $mediaUrl = rtrim($baseUrl, '/') . $downloadLink;
                }

                Log::info("Resolved media download URL: {$mediaUrl}");

                // Allowed mime types
                $allowedMimeTypes = [
                    'image/jpeg',
                    'image/jpg',
                    'image/png',
                ];

                if (!in_array($mimeType, $allowedMimeTypes)) {
                    Log::warning("Unsupported media type received from {$phone}: {$mimeType}. Skipping.");
                    return response()->json(['message' => 'Unsupported media type.'], 200);
                }

                // Download and store the file
                $localPath = null;

                try {
                    // Generate unique filename
                    $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';
                    $newFilename = 'media_' . time() . '_' . uniqid() . '.' . $extension;

                    $storagePath = storage_path('app/public/uploads/' . $newFilename);

                    $context = stream_context_create([
                        'http' => [
                            'header' => 'Authorization: Bearer ' . config('services.whatsapp.token', ''),
                        ],
                    ]);

                    $fileContents = file_get_contents($mediaUrl, false, $context);

                    if ($fileContents !== false) {
                        file_put_contents($storagePath, $fileContents);
                        $localPath = 'uploads/' . $newFilename;
                        Log::info("Media file saved to {$localPath}");
                    } else {
                        Log::error("Failed to download media from {$mediaUrl}");
                    }

                } catch (\Exception $e) {
                    Log::error("Error downloading media: " . $e->getMessage());
                }

                // Save to database
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
                    $to = $request->input('data.from') ?? $request->input('from') ?? null;

                    if ($to) {
                        $clientWAController = new WhatsappController();
                        $clientWAController->sendToResayil($to, "We have received your image, thank you.");
                        Log::info("Sent auto-reply to {$to}");
                    } else {
                        Log::warning("Cannot send auto-reply: 'from' not found in webhook.");
                    }

                } catch (\Exception $e) {
                    Log::error("Failed to send auto-reply to {$phone}: " . $e->getMessage());
                }

            } else {
                // No media with download link
                if ($mediaData) {
                    Log::info("Media data found, but no download link present.");
                } else {
                    Log::info("No media found in this webhook.");
                }
            }

        } catch (\Exception $e) {
            Log::error("Unexpected error in handleResayilWebhook: " . $e->getMessage());
        }

        return response()->json(['message' => 'Webhook received successfully']);
    }


}
