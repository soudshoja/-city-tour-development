<?php

namespace App\AI;

use App\AI\Contracts\AIClientInterface;
use App\AI\Services\OpenAIClient;
use App\AI\Services\AnythingLLMClient;
use App\AI\Services\OpenWebUIClient;
use App\AI\Support\AIResponse;
use App\Models\Supplier;
use Exception;

class AIManager
{
    protected AIClientInterface $client;

    public function __construct()
    {
        $this->client = $this->createClient();
    }

    protected function createClient(): AIClientInterface
    {
        $provider = config('ai.default', 'openai');

        return match ($provider) {
            'openai' => new OpenAIClient(),
            'anythingllm' => new AnythingLLMClient(),
            'openwebui' => new OpenWebUIClient(),
            default => throw new Exception("Unsupported AI provider: {$provider}")
        };
    }

    public function getClient(): AIClientInterface
    {
        return $this->client;
    }

    public function switchProvider(string $provider): void
    {
        $oldProvider = config('ai.default');
        config(['ai.default' => $provider]);
        
        try {
            $this->client = $this->createClient();
        } catch (Exception $e) {
            // Rollback on failure
            config(['ai.default' => $oldProvider]);
            throw $e;
        }
    }

    /**
     * Standardized chat method - returns simple, consistent format
     */
    public function chat(array $messages): array
    {
        try {
            return $this->client->chat($messages);
        } catch (Exception $e) {
            return AIResponse::error('Chat failed: ' . $e->getMessage());
        }
    }

    /**
     * Standardized file processing - returns simple, consistent format
     */
    public function processWithAiTool(string $filePath, string $fileName): array
    {
        try {
            return $this->client->processWithAiTool($filePath, $fileName);
        } catch (Exception $e) {
            return AIResponse::error('File processing failed: ' . $e->getMessage());
        }
    }

    /**
     * Standardized passport extraction - returns simple, consistent format
     */
    public function extractPassportData(string $filePath, string $fileName): array
    {
        try {
            return $this->client->extractPassportData($filePath, $fileName);
        } catch (Exception $e) {
            return AIResponse::error('Passport extraction failed: ' . $e->getMessage());
        }
    }

    /**
     * Standardized air files extraction - returns simple, consistent format
     */
    public function extractAirFiles($parameter): array
    {
        try {
            return $this->client->extractAirFiles($parameter);
        } catch (Exception $e) {
            return AIResponse::error('Air files extraction failed: ' . $e->getMessage());
        }
    }

    /**
     * Standardized PDF extraction - returns simple, consistent format
     */
    public function extractPdfFiles($parameter): array
    {
        try {
            return $this->client->extractPdfFiles($parameter);
        } catch (Exception $e) {
            return AIResponse::error('PDF extraction failed: ' . $e->getMessage());
        }
    }

    /**
     * Standardized multiple PDF extraction - returns simple, consistent format
     */
    public function extractMultiplePdfFiles(array $fileIds): array
    {
        try {
            return $this->client->extractMultiplePdfFiles($fileIds);
        } catch (Exception $e) {
            return AIResponse::error('Multiple PDF extraction failed: ' . $e->getMessage());
        }
    }

    /**
     * Standardized file upload - returns simple, consistent format
     */
    public function uploadFileToAI($file): array
    {
        try {
            return $this->client->uploadFileToOpenAI($file);
        } catch (Exception $e) {
            return AIResponse::error('File upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Standardized batch processing - returns simple, consistent format
     */
    public function processBatchFiles(array $files): array
    {
        try {
            return $this->client->processBatchFiles($files);
        } catch (Exception $e) {
            return AIResponse::error('Batch processing failed: ' . $e->getMessage());
        }
    }
}