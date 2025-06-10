<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\IncomingMedia;
use Illuminate\Support\Facades\Log;

class IncomingMediaController extends Controller
{
    public function handleResayilWebhook(Request $request)
    {
        Log::debug('Incoming Resayil Webhook:', $request->all());

        // Extract customer phone number
        $phone = $request->input('data.fromNumber')
            ?? $request->input('phone')
            ?? $request->input('messages.0.from');

        // Extract agent phone and email (Resayil device format)
        $agentPhone = $request->input('device.phone') ?? null;
        $agentEmail = $request->input('device.alias') ?? null;

        Log::info("Agent Phone: {$agentPhone}, Agent Email (alias): {$agentEmail}");

        // Check if media exists
        if ($request->has('media')) {

            $mediaData = $request->input('media');

            $mediaId = $mediaData['id'] ?? null;
            $mimeType = $mediaData['mime'] ?? null;
            $caption = $mediaData['caption'] ?? null;
            $receivedAt = $request->input('data.date') ?? now();
            $filename = $mediaData['filename'] ?? null;

            // Build media URL
            $downloadLink = $mediaData['links']['download'] ?? null;

            // BASE URL 
            $baseUrl = 'https://api.resayil.com'; 

            $mediaUrl = null;
            if ($downloadLink) {
                $mediaUrl = rtrim($baseUrl, '/') . $downloadLink;
                Log::info("Resolved media download URL: {$mediaUrl}");
            } else {
                Log::warning("No download link found in media data.");
            }

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

            if ($mediaUrl) {
                try {
                    // Generate unique filename
                    $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';
                    $newFilename = 'media_' . time() . '_' . uniqid() . '.' . $extension;

                    $storagePath = storage_path('app/public/uploads/' . $newFilename);

                    $context = stream_context_create([
                        'http' => [
                            'header' => 'Authorization: Bearer ' . config('services.resayil.api_token', ''),
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
            Log::info("No media found in this webhook.");
        }

        return response()->json(['message' => 'Webhook received successfully']);
    }

}
