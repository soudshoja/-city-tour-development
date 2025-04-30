<?php

namespace App\AI\Contracts;

interface AiClientInterface
{
    public function chat(array $messages, );
    public function getResponse(string $prompt): string;
    public function getResponseWithContext(string $prompt, array $context): string;
    public function getResponseWithHistory(string $prompt, array $history): string;
    public function getResponseWithContextAndHistory(string $prompt, array $context, array $history): string;
}