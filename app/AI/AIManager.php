<?php

namespace App\AI;

use App\AI\Contracts\AIClientInterface;
use App\AI\Services\OpenAIClient;
use App\Models\Supplier;

class AIManager
{
    protected AIClientInterface $client;

    public function __construct()
    {
        $this->client = new OpenAIClient();
    }

    public function chat(array $parameter)
    {
        return $this->client->chat($parameter);
    }

    public function processWithAiTool(string $filePath, string $fileName)
    {
        return $this->client->processWithAiTool($filePath, $fileName);
    }

    public function extractAirFiles($parameter)
    {
        return $this->client->extractAirFiles($parameter);
    }

    public function extractPdfFiles($parameter)
    {
        return $this->client->extractPdfFiles($parameter);
    }
}