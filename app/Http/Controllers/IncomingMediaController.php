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

        $phone = $request->input('phone') ?? $request->input('messages.0.from');
        $messages = $request->input('messages', []);

        foreach ($messages as $message) {
            // Check if media exists
            if (isset($message['media'])) {
                $mediaId = $message['media']['id'] ?? null;
                $mimeType = $message['media']['mimeType'] ?? null;
                $caption = $message['media']['caption'] ?? null;
                $receivedAt = $message['timestamp'] ?? now();
                $mediaUrl = $message['media']['url'] ?? null;
                $filename = $message['media']['filename'] ?? null;

                $allowedMimeTypes = [
                    'image/jpeg',
                    'image/jpg',
                    'image/png',
                ];

                // Only allow specific image types
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    Log::warning("Unsupported media type received from {$phone}: {$mimeType}. Skipping.");
                    continue; // Skip this message
                }

                $localPath = null;

                // If media URL is provided, download it
                if ($mediaUrl) {
                    try {
                        // Generate a unique filename
                        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'jpg';
                        $newFilename = 'media_' . time() . '_' . uniqid() . '.' . $extension;

                        // Define full path
                        $storagePath = storage_path('app/public/uploads/' . $newFilename);

                        // Download the file
                        $fileContents = file_get_contents($mediaUrl);

                        if ($fileContents !== false) {
                            // Save file to storage
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

                // Save to DB
                IncomingMedia::create([
                    'phone'       => $phone,
                    'media_id'    => $mediaId,
                    'mime_type'   => $mimeType,
                    'caption'     => $caption,
                    'received_at' => \Carbon\Carbon::parse($receivedAt),
                    'file_path'   => $localPath,
                ]);

                Log::info("Saved incoming media from {$phone}, media_id: {$mediaId}");
            } else {
                Log::info("No media in message from {$phone}.");
            }
        }

        return response()->json(['message' => 'Webhook received successfully']);
    }


}
