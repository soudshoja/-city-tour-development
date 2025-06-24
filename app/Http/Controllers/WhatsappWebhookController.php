<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    // You can customize this as needed
    private $VERIFY_TOKEN = 'd41d8cd98f00b204e9800998ecf8427e';

    /**
     * Handle the incoming WhatsApp webhook request.
     */
    public function handleWebhook(Request $request)
    {
        // 1. Handle the GET request for verifying the webhook subscription
        if ($request->isMethod('get') && $request->query('hub_mode') === 'subscribe') {
            logger('get request from facebook');
            $challenge    = $request->query('hub_challenge');
            $verify_token = $request->query('hub_verify_token');

            if ($verify_token === $this->VERIFY_TOKEN) {
                return response($challenge, 200);
            }
            return response('Error: Verification token mismatch', 403);
        }

        // 2. Handle the POST request with incoming messages
        if ($request->isMethod('post')) {
            logger('post request from facebook');

            $requestBody = $request->getContent();
            Log::info("WhatsApp Webhook POST Body: {$requestBody}");

            // Optionally, also store in a text file
            file_put_contents(
                storage_path('logs/whatsapp_log.txt'),
                $requestBody . PHP_EOL,
                FILE_APPEND
            );

            $json = json_decode($requestBody, true);

            // Process the 'entry' objects
            if (isset($json['entry'])) {
                foreach ($json['entry'] as $entry) {
                    if (isset($entry['changes'])) {
                        foreach ($entry['changes'] as $change) {
                            if (isset($change['value']['messages'])) {
                                foreach ($change['value']['messages'] as $message) {
                                    $phoneNumber = $message['from'];
                                    $messageType = $message['type'];

                                    if ($messageType === 'text') {
                                        $text = $message['text']['body'] ?? '';

                                        // Append to received messages log
                                        file_put_contents(
                                            storage_path('logs/received_messages.txt'),
                                            "From: $phoneNumber - Message: $text" . PHP_EOL,
                                            FILE_APPEND
                                        );

                                    } elseif ($messageType === 'document') {
                                        $docName = $message['document']['filename'];
                                        $mimeType = $message['document']['mime_type'];
                                        $mediaId = $message['document']['id'];

                                        // Log document details
                                        file_put_contents(
                                            storage_path('logs/received_messages.txt'),
                                            "From: $phoneNumber - Document: $docName ($mimeType) - Media ID: $mediaId" . PHP_EOL,
                                            FILE_APPEND
                                        );

                                        // Make a cURL request to download_media.php automatically
                                        // Adjust to your actual domain/path
                                        $downloadScriptUrl = route('download.media', ['media_id' => $mediaId]);

                                        $ch = curl_init($downloadScriptUrl);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

                                        $downloadResult = curl_exec($ch);

                                        // Log cURL errors if any
                                        if (curl_errno($ch)) {
                                            Log::error('CURL Error: ' . curl_error($ch));
                                            file_put_contents(
                                                storage_path('logs/log.txt'),
                                                "CURL Error: " . curl_error($ch) . PHP_EOL,
                                                FILE_APPEND
                                            );
                                        }
                                        curl_close($ch);

                                        // Optionally, log the download script response
                                        file_put_contents(
                                            storage_path('logs/log.txt'),
                                            "Download script response: " . $downloadResult . PHP_EOL,
                                            FILE_APPEND
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Respond with 200 OK
            return response('OK', 200);
        }

        // 3. If neither GET nor POST matched, respond with 404
        return response('Not Found', 404);
    }
}
