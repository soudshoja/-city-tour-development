<?php

namespace App\AI\Support;

use Illuminate\Support\Facades\Log;

class AIResponse
{
    /**
     * Standardized AI response format
     */
    public static function success(mixed $data, string $message = 'Operation completed successfully', array $metadata = []): array
    {
        return [
            'success' => true,
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    public static function error(string $message, mixed $data = null, array $metadata = []): array
    {
        return [
            'success' => false,
            'status' => 'error',
            'message' => $message,
            'data' => $data,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    public static function partial(mixed $data, string $message = 'Operation partially completed', array $metadata = []): array
    {
        return [
            'success' => true,
            'status' => 'partial_success',
            'message' => $message,
            'data' => $data,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Extract content from various AI provider response formats
     */
    public static function extractContent(array $response): ?string
    {
        Log::info('Extracting content from AI response', ['response' => $response]);
        // OpenAI format
        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }

        // AnythingLLM format
        if (isset($response['textResponse'])) {
            return $response['textResponse'];
        }

        if (isset($response['response'])) {
            return $response['response'];
        }

        // Direct string response
        if (is_string($response)) {
            return $response;
        }

        return null;
    }

    /**
     * Extract usage/metadata from various AI provider response formats
     */
    public static function extractMetadata(array $response): array
    {
        $metadata = [];

        // OpenAI usage
        if (isset($response['usage'])) {
            $metadata['usage'] = $response['usage'];
        }

        // AnythingLLM metadata
        if (isset($response['original_response'])) {
            $metadata['provider_response'] = $response['original_response'];
        }

        // Model information
        if (isset($response['model'])) {
            $metadata['model'] = $response['model'];
        }

        return $metadata;
    }

    /**
     * Parse JSON content safely
     */
    public static function parseJsonContent(string $content): array
    {
        // Try to extract JSON from content that might have extra text
        $patterns = [
            '/\{.*\}/s',     // Find JSON object
            '/\[.*\]/s',     // Find JSON array
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $jsonString = $matches[0];
                $decoded = json_decode($jsonString, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        // If no valid JSON found, return the content as-is
        return ['raw_content' => $content];
    }
}
