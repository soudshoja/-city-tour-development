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

        $type = $request->input('data.type');
        Log::info("Source type: {$type}");

        $phone = $request->input('data.fromNumber')
            ?? $request->input('phone')
            ?? $request->input('messages.0.from');

        $chatWid = $request->input('data.chat.id') ?? $request->input('data.from');

        // Handle phone number reply by agent
        if ($type === 'text') {
            $text = trim($request->input('data.body'));
            if (preg_match('/^\+?[0-9]{7,15}$/', $text)) {
                $agent = Agent::where('phone_number', $phone)->first();

                if ($agent) {
                    try {
                        $pending = IncomingMedia::where('agent_id', $agent->id)
                            ->whereNull('client_id')
                            ->latest()
                            ->first();

                        if (!$pending) {
                            $this->sendInteractiveOptions($chatWid, 'No pending passport found. Please resend the document.');
                            return response()->json(['message' => 'No pending media']);
                        }

                        $client = Client::firstOrNew(['phone' => $text]);
                        $isNew = !$client->exists;

                        $client->fill([
                            'email' => 'ops@citytravelers.co',
                            'agent_id' => $agent->id,
                        ]);

                        if ($isNew) {
                            $client->status = 'active';
                        }

                        $client->save();

                        $pending->client_id = $client->id;
                        $pending->save();

                        $message = $isNew
                            ? "✅ Thank you, your profile has been created for {$text}."
                            : "ℹ️ Thank you for updating your passport details for {$text}.";

                        $this->sendInteractiveOptions($chatWid, $message);
                        return response()->json(['message' => 'Client linked']);
                    } catch (\Exception $e) {
                        Log::error("Error linking client: " . $e->getMessage());
                        $this->sendInteractiveOptions($chatWid, '❌ Failed to process client number. Please try again.');
                        return response()->json(['message' => 'Error processing']);
                    }
                }
            }
        }

        // Skip non-media messages
        if (!in_array($type, ['image', 'video', 'document'])) {
            Log::info("Skipping unsupported message type: {$type}");
            return response()->json(['message' => 'No media to process.'], 200);
        }

        // Agent & fallback
        $deviceId = $request->input('device.id');
        $agentId = 1;
        $agentPhone = $request->input('device.phone');
        $agentEmail = null;
        $fallbackPhone = config('app.agent_default_phone', '+96522210017');
        $fallbackEmail = config('app.agent_default_email', 'ops@citytravelers.co');

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

        // Media setup
        $mediaData = $request->input('media') ?? $request->input('data.media');
        $downloadLink = $mediaData['links']['download'] ?? null;

        if (!$downloadLink) {
            Log::info("No media download link found.");
            return response()->json(['message' => 'No media found.'], 200);
        }

        if (!str_starts_with($downloadLink, 'http')) {
            $downloadLink = rtrim(config('services.resayil.base_url'), '/') . $downloadLink;
        }

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

        // Download media
        $localPath = null;
        try {
            $newFilename = 'media_' . time() . '_' . uniqid() . '.' . $extension;
            $response = Http::withHeaders([
                'Token' => config('services.resayil.api_token'),
            ])->get($downloadLink);

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

        // Save IncomingMedia
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

            if ($agent) {
                $this->sendInteractiveOptions($chatWid);
            }

            return response()->json(['message' => 'Awaiting client number']);
        } catch (\Exception $e) {
            Log::error("Error saving IncomingMedia: " . $e->getMessage());
        }

        return response()->json(['message' => 'Webhook received']);
    }

    public function sendInteractiveOptions($to, $bodyText = '📩 Please enter the client mobile number to proceed:')
    {
        try {
            $wa = new WhatsappController();
            $wa->sendToResayil($to, $bodyText);
        } catch (\Exception $e) {
            Log::error("Failed to send interactive prompt to {$to}: " . $e->getMessage());
        }
    }
}
