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
use Illuminate\Support\Str;
use Carbon\Carbon;
use Spatie\PdfToImage\Pdf;
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
            $deviceId = $request->input('device.id');
            $chatWid = $request->input('data.chat.id') ?? $request->input('data.from') ?? null;
            Log::info("Agent Phone: {$agentPhone}");

            try {
                $agent = Agent::where('phone_number', $phone)->first();

                if ($agent) {
                    // Agent with this phone exists
                    $agentId = $agent->id;
                    $agentPhone = $agent->phone_number;
                    $agentEmail = $agent->email;
                    Log::info("Agent matched by phone: {$agent->name} ({$agentEmail})");
                } else {
                    // Agent not found by phone, select random agent
                    $agent = Agent::inRandomOrder()->first();

                    if ($agent) {
                        $agentId = $agent->id;
                        $agentPhone = $agent->phone_number;
                        $agentEmail = $agent->email;
                        Log::info("Randomly selected agent from DB: {$agent->id} - {$agent->name} ({$agent->email})");
                    } else {
                        // No agents at all in DB, fallback values
                        $agentId = 1;
                        $agentPhone = '+96522210017';
                        $agentEmail = 'admin@citytravelers.co';
                        Log::warning("No agents found in DB. Using fallback values.");
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error selecting agent from DB: " . $e->getMessage());
                $agentId = 1;
                $agentPhone = '+96522210017';
                $agentEmail = 'admin@citytravelers.co';
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

                $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    Log::warning("Unsupported media type from {$phone}: {$mimeType}. Skipping.");
                    return response()->json(['message' => 'Unsupported media type.'], 200);
                }

                $mediaUrl = str_starts_with($downloadLink, 'http')
                    ? $downloadLink
                    : config('services.resayil.base_url') . "chat/{$deviceId}/files/{$mediaId}/download";

                $localPath = null;
                $convertedFromPdf = false;

                try {
                    $newFilename = 'media_' . time() . '_' . uniqid() . '.' . $extension;
                    $response = Http::withHeaders([
                        'Token' => config('services.resayil.api_token', ''),
                    ])->get($mediaUrl);

                    if ($response->ok()) {
                        Storage::put("public/uploads/{$newFilename}", $response->body());
                        $localPath = "uploads/{$newFilename}";
                        Log::info("Media file saved to {$localPath}");

                        // Convert PDF to image
                        if ($extension === 'pdf') {
                            try {
                                $pdfPath = storage_path("app/public/{$localPath}");
                                $pdf = new \Spatie\PdfToImage\Pdf($pdfPath);
                                $imageFilename = str_replace('.pdf', '.jpg', $newFilename);
                                $imagePath = "uploads/{$imageFilename}";

                                $pdf->setPage(1)->saveImage(storage_path("app/public/{$imagePath}"));
                                $convertedFromPdf = true;

                                // Delete original PDF
                                //Storage::delete("public/{$localPath}");
                                Log::info("PDF converted to image: {$imagePath}");

                                $localPath = $imagePath;
                            } catch (\Exception $e) {
                                Log::error("Failed to convert PDF to image: " . $e->getMessage());
                            }
                        }
                    } else {
                        Log::error("Failed to download media. Status: {$response->status()} Body: {$response->body()}");
                    }
                } catch (\Exception $e) {
                    Log::error("Error downloading media: " . $e->getMessage());
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

                $autoReplyText = "Thank you for your submission.";

                if ($localPath && Storage::exists("public/{$localPath}")) {
                    try {
                        $fullPath = storage_path("app/public/{$localPath}");

                        $uploadResponse = Http::asMultipart()
                            ->attach('file', file_get_contents($fullPath), basename($fullPath))
                            ->post(config('app.url') . '/api/chat/upload');

                        if ($uploadResponse->successful()) {
                            Log::info("Posted file to handleFileUpload (API): " . $uploadResponse->body());

                            $data = $uploadResponse->json('data');

                            if ($data && isset($data['name'], $data['civil_no'])) {
                                $checkClient = Client::where('civil_no', $data['civil_no'])->first();

                                if (!$checkClient) {
                                    try {
                                        DB::beginTransaction();

                                        $client = Client::create([
                                            'name'            => $data['name'],
                                            'email'           => $agentEmail,
                                            'status'          => 'active',
                                            'phone'           => $phone ?? $agentPhone,
                                            'date_of_birth'   => $data['date_of_birth'] ?? null,
                                            'address'         => $data['place_of_birth'] ?? null,
                                            'civil_no'        => $data['civil_no'] ?? null,
                                            'passport_no'     => $data['passport_no'] ?? null,
                                            'old_passport_no' => $data['passport_no'] ?? null,
                                            'agent_id'        => $agentId ?? 1,
                                        ]);

                                        DB::commit();

                                        IncomingMedia::where('file_path', $localPath)->update(['client_id' => $client->id]);

                                        Log::info("Client created successfully: ID {$client->id}");
                                        $autoReplyText = "Thank you, your profile has been created.";
                                    } catch (\Exception $e) {
                                        if (DB::transactionLevel() > 0) DB::rollBack();
                                        Log::error("Error during client creation: " . $e->getMessage());
                                    }
                                } else {
                                    if (!empty($data['passport_no']) && $checkClient->passport_no !== $data['passport_no']) {
                                        $checkClient->update([
                                            'phone'         => $phone ?? $agentPhone,
                                            'date_of_birth' => $data['date_of_birth'] ?? null,
                                            'address'       => $data['place_of_birth'] ?? null,
                                            'passport_no'   => $data['passport_no'],
                                            'updated_at'    => Carbon::parse($receivedAt),
                                        ]);

                                        Log::info("Updated passport number for client ID {$checkClient->id}");
                                        $autoReplyText = "Thank you for updating your passport details.";
                                    } else {
                                        Log::info("No changes to client {$checkClient->id}");
                                        $autoReplyText = "Thank you. We already have your passport information.";
                                    }

                                    IncomingMedia::where('file_path', $localPath)->update(['client_id' => $checkClient->id]);
                                }
                            } else {
                                Log::error("No valid data returned from handleFileUpload response.");
                            }
                        } else {
                            Log::error("Failed to post to handleFileUpload (API): {$uploadResponse->status()} - {$uploadResponse->body()}");
                        }
                    } catch (\Exception $e) {
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
