<?php

namespace App\AI\Contracts;

use Illuminate\Http\Response;

interface AIClientInterface
{
    public function chat(array $messages): array;
    public function processWithAiTool(string $filePath, string $fileName): array;
    public function extractAirFiles(string $content): array;
    public function extractPdfFiles(string $fileId): array;
    public function extractMultiplePdfFiles(array $fileIds): array;
    public function processBatchFiles(array $files): array;
    public function uploadFileToOpenAI($file, string $purpose = 'user_data');
    public function extractPassportData(string $filePath, string $fileName): array;
}