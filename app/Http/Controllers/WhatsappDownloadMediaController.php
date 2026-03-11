<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsappDownloadMediaController extends Controller
{
    public function download(Request $request)
    {
        $mediaId = $request->query('media_id') ?? '';

        if (empty($mediaId)) {
            return response('No media ID provided.', 400);
        }

        $whatsappToken = config('services.whatsapp.token');
        $graphApiBaseUrl = config('services.whatsapp.graph_api_url');

        // Retrieve the media URL from Meta Graph API
        $mediaApiUrl = "{$graphApiBaseUrl}/{$mediaId}";

        $response = Http::withHeaders([
            "Authorization" => "Bearer {$whatsappToken}",
            "Content-Type" => "application/json",
        ])->get($mediaApiUrl);

        if ($response->failed()) {
            Log::error("Error fetching media URL", ['response' => $response->body()]);
            return response('Error fetching media URL.', 500);
        }

        $data = $response->json();
        Log::info("WhatsApp Media Step 1 Response", $data);

        if (empty($data['url'])) {
            return response('No URL in response data.', 500);
        }

        // Download the actual file using the media URL
        $downloadUrl = $data['url'];

        $fileResponse = Http::withHeaders([
            "Authorization" => "Bearer {$whatsappToken}",
            "User-Agent" => "MyCustomUserAgent/1.0",
        ])->get($downloadUrl);

        if ($fileResponse->failed()) {
            Log::error("Unable to download media from URL", ['url' => $downloadUrl, 'response' => $fileResponse->body()]);
            return response("Unable to download media from {$downloadUrl}.", 500);
        }

        // Save the file
        $timestamp = date('Ymd_His');
        $filename = "document_{$mediaId}_{$timestamp}.pdf"; // adjust extension if needed based on file type
        $destinationPath = storage_path("app/tasks/{$filename}");

        file_put_contents($destinationPath, $fileResponse->body());

        Log::info("File saved to {$destinationPath}");

        return response("File saved to {$destinationPath}", 200);
    }


    // public function download(Request $request)
    // {
    //     $mediaId = $request->query('media_id') ?? '';

    //     if (empty($mediaId)) {
    //         return response('No media ID provided.', 400);
    //     }

    //     $whatsappToken = "EAANoyWCikw0BO4fkFHz5xWEupMXgdUMD03B5oQPVaDaJbussK0QrnctmNMF3gQiuXRKlwltRWiZBEtuQ7WRcWRz9MHOPdkgdLke9LZB4l89TbppWcVACkc3ZA8ZABw3HzKZBVpC4y8oZBaj0ZAUivtVZBNP2p3tfqwEOfev7Cezh3AZA84CskkUTvE8BZAtEJHlZBjJkgZDZD"; 
        
    //     // Step 1: Retrieve the media URL
    //     $apiUrl = "https://graph.facebook.com/v22.0/{$mediaId}";
    //     $headers = [
    //         "Authorization: Bearer {$whatsappToken}",
    //         "Content-Type: application/json",
    //     ];

    //     $response = $this->makeHttpRequest('GET', $apiUrl, $headers);

    //     if (!$response) {
    //         return response('Error fetching media URL.', 500);
    //     }

    //     // Parse JSON
    //     $data = json_decode($response, true);
    //     Log::info("WhatsApp Media Step 1 Response: ", $data);

    //     if (empty($data['url'])) {
    //         return response('No URL in response data.', 500);
    //     }

    //     // Step 2: Download the actual file
    //     $downloadUrl = $data['url'];
    //     $fileOutput = $this->makeHttpRequest('GET', $downloadUrl, [
    //         "Authorization: Bearer {$whatsappToken}",
    //         "User-Agent: MyCustomUserAgent/1.0"
    //     ]);

    //     if (!$fileOutput) {
    //         return response("Unable to download media from {$downloadUrl}.", 500);
    //     }

    //     // Generate a unique filename
    //     $timestamp = date('Ymd_His');
    //     $filename = "document_{$mediaId}_{$timestamp}.pdf";

    //     // Save the file
    //     $destinationPath = storage_path("app/tasks/{$filename}");
    //     file_put_contents($destinationPath, $fileOutput);

    //     Log::info("File saved to {$destinationPath}");

    //     return response("File saved to {$destinationPath}", 200);
    // }

    private function makeHttpRequest($method, $url, $headers = [], $body = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($method === 'POST' && $body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            Log::error('cURL Error: ' . curl_error($ch));
            $result = false;
        }
        curl_close($ch);

        return $result;
    }
}
