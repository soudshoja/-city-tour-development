<?php

namespace App\Http\Controllers;

use App\Http\Traits\HttpRequestTrait;
use Illuminate\Http\Request;

class OpenApiController extends Controller
{
    use HttpRequestTrait;
    public function index()
    {
        return view('ai.openapi.index');
    }

    public function store(Request $request)
    {
        $prompt = $request->input('prompt');
        $url = config('services.open-api.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];
        $data = [
            'model' => 'gpt-4',  // Use a valid model name like 'gpt-4' or 'gpt-3.5-turbo'
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an assistant in a travel agency. You will suggest the best flight options to a customer based on their preferences.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'stream' => true
        ];

        $ch = curl_init();

        // Set cURL options for OpenAI API request
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Stream response
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Set up streaming response headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Transfer-Encoding: chunked');

        // Execute cURL request and handle streamed data
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) {
            echo "data: " . trim($data) . "\n\n"; // Format data as an SSE message
            ob_flush();
            flush();
            return strlen($data);
        });

        // Execute the cURL request
        if (curl_exec($ch) === false) {
            echo 'cURL error: ' . curl_error($ch); // Print error if cURL fails
        }

        // Close the cURL session
        curl_close($ch);
    }

    public function test()
    {
        // $prompt = $request->input('prompt');
        $url = config('services.open-ai.url') . '/chat/completions';
        $header = [
            'Authorization: Bearer ' . config('services.open-ai.key'),
            'Content-Type: application/json',
        ];
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an assistant in a travel agency. You will suggest the best flight options to a customer based on their preferences.',
                ],
                [
                    'role' => 'user',
                    'content' => 'suggest me a hiking trip to the mountains',
                ],
            ],
            'stream' => false // Non-streaming request for debugging
        ];
        // dd($url, $header, $data);
        $ch = curl_init();

        // Set cURL options for OpenAI API request
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Regular response
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        // Execute cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            echo 'cURL error: ' . curl_error($ch);
        } else {
            // Decode and log or return the response
            $responseData = json_decode($response, true);
            // Log the response for debugging
            dd($responseData); // This will dump the response for inspection
        }

        // Close the cURL session
        curl_close($ch);
    }
}
