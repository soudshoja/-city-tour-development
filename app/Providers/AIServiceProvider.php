<?php

namespace App\Providers;

use App\AI\Contracts\AiClientInterface;
use App\AI\Services\OpenAIClient;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiClientInterface::class, function ($app) {
            switch (config('ai.client')) {
                case 'openai':
                    return new OpenAIClient();
                default:
                    throw new \InvalidArgumentException('Invalid AI client specified.');
            }
        });
    }

    public function boot(): void
    {
        //
    }
}
