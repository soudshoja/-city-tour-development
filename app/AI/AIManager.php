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

    public function extractAirFiles($parameter)
    {
        return $this->client->extractAirFiles($parameter);
    }
}