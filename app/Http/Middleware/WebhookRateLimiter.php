<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use App\Models\WebhookClient;
use Symfony\Component\HttpFoundation\Response;

class WebhookRateLimiter
{
    public function handle(Request $request, Closure $next): Response
    {
        // Get webhook client ID from route parameter or query
        $clientId = $request->route('webhook_client_id') ?? $request->query('client_id');

        if (!$clientId) {
            // If no client ID, apply global rate limit
            $key = 'webhook-global:' . $request->ip();
            $maxAttempts = config('webhook.global_rate_limit', 100);
        } else {
            // Load client-specific rate limit
            $client = WebhookClient::find($clientId);

            if (!$client) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid webhook client',
                ], 400);
            }

            $key = 'webhook-client:' . $clientId;
            $maxAttempts = $client->rate_limit ?? 60;
        }

        // Check rate limit (per minute)
        $executed = RateLimiter::attempt(
            $key,
            $maxAttempts,
            function() {},
            60 // decay in seconds (1 minute)
        );

        if (!$executed) {
            $retryAfter = RateLimiter::availableIn($key);

            Log::warning('[Webhook] Rate limit exceeded', [
                'client_id' => $clientId ?? 'global',
                'ip' => $request->ip(),
                'path' => $request->path(),
                'retry_after' => $retryAfter,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Rate limit exceeded',
                'retry_after' => $retryAfter,
            ], 429)->header('Retry-After', $retryAfter);
        }

        return $next($request);
    }
}
