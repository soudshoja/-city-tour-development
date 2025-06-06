<?php

namespace App\AI\Contracts;

use Illuminate\Http\Response;

interface AiClientInterface
{
    public function chat(array $messages): array;
    public function processWithAiTool(string $filePath, string $fileName): array;
    public function extractAirFiles(string $content): array;
    public function extractPdfFiles(string $fileId): array;
}