<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAIServiceEmail
{
    public function getChatResponse(array $messages)
    {
        $apiKey = config('services.open-ai.key'); // API key from config
        $response = Http::withToken($apiKey)
        ->withoutVerifying()
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo', // Use the appropriate model for your use case
            'messages' => $messages,
            'temperature' => 0.7,
        ]);

        // Check if the API call failed
        if ($response->failed()) {
            \Log::error('OpenAI API Error: ' . json_encode($response->json()));
            throw new \Exception('OpenAI API request failed: ' . $response->body());
        }

        return $response->json();
    }
}