<?php

namespace App\AI\Contracts;

interface AiClientInterface
{
    public function extractAirFiles(string $content): array;
}