<?php

namespace App\AI;

use App\AI\Contracts\AIClientInterface;

class AIManager
{
    protected AIClientInterface $client;

    public function __construct(AIClientInterface $client)
    {
        $this->client = $client;
    }

    public function chat(array $messages, array $options = []): array
    {
        return $this->client->chat($messages, $options);
    }
}