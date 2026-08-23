<?php

namespace App\AI\Services;

use App\AI\Contracts\AIClientInterface;
use App\AI\Support\AIResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ordered primary→fallback decorator. For each interface method it retries the
 * first client, then tries each subsequent client once, returning the first
 * usable result. A result is a FAILURE if it throws, signals error
 * (status==='error' or success===false), or carries empty data.
 *
 * Built 2026-06-04 to run Resayil-first with automatic OpenAI fallback without
 * touching the existing provider clients or any callers.
 */
class FallbackAIClient implements AIClientInterface
{
    /** @var AIClientInterface[] ordered primary..fallback */
    private array $clients;

    public function __construct(array $clients, private int $retries = 2)
    {
        $this->clients = array_values(array_filter($clients));
    }

    protected function isFailure(mixed $result): bool
    {
        if (!is_array($result)) {
            return true;
        }
        if (($result['status'] ?? null) === 'error') {
            return true;
        }
        if (array_key_exists('success', $result) && $result['success'] === false) {
            return true;
        }
        if (array_key_exists('data', $result) && ($result['data'] === null || $result['data'] === [] || $result['data'] === '')) {
            return true;
        }
        return false;
    }

    protected function attempt(string $method, array $args): mixed
    {
        $last = AIResponse::error("All AI providers failed for {$method}");
        foreach ($this->clients as $index => $client) {
            $tries = $index === 0 ? 1 + $this->retries : 1;
            for ($i = 0; $i < $tries; $i++) {
                try {
                    $result = $client->{$method}(...$args);
                    if (!$this->isFailure($result)) {
                        if ($index > 0) {
                            Log::channel('ai')->warning("AI fallback used for {$method}", ['client_index' => $index]);
                        }
                        return $result;
                    }
                    $last = $result;
                } catch (Throwable $e) {
                    $last = AIResponse::error("{$method} threw on client #{$index}: " . $e->getMessage());
                }
                if ($i + 1 < $tries) {
                    usleep(400000); // 0.4s backoff between primary retries
                }
            }
        }
        return $last;
    }

    public function chat(array $messages): array
    {
        return $this->attempt('chat', [$messages]);
    }

    public function processWithAiTool(string $filePath, string $fileName): array
    {
        return $this->attempt('processWithAiTool', [$filePath, $fileName]);
    }

    public function extractAirFiles(string $content): array
    {
        return $this->attempt('extractAirFiles', [$content]);
    }

    public function extractPdfFiles(string $fileId): array
    {
        return $this->attempt('extractPdfFiles', [$fileId]);
    }

    public function extractMultiplePdfFiles(array $fileIds): array
    {
        return $this->attempt('extractMultiplePdfFiles', [$fileIds]);
    }

    public function processBatchFiles(array $files): array
    {
        return $this->attempt('processBatchFiles', [$files]);
    }

    public function extractPassportData(string $filePath, string $fileName): array
    {
        return $this->attempt('extractPassportData', [$filePath, $fileName]);
    }

    // Internal plumbing — delegate to primary only (not part of cross-call fallback).
    public function uploadFileToOpenAI($file, string $purpose = 'user_data')
    {
        return $this->clients[0]->uploadFileToOpenAI($file, $purpose);
    }
}
