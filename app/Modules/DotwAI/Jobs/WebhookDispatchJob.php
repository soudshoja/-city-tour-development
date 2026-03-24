<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fire-and-forget HTTP webhook dispatch job.
 *
 * Sends a JSON payload to the configured DOTWAI_WEBHOOK_URL with retry
 * logic and exponential backoff. Dead-letters permanently after all
 * retries are exhausted (failed() hook logs for monitoring).
 *
 * Retry schedule: 30s → 2m → 5m (4 attempts total)
 *
 * @see EVNT-01 Laravel pushes async events to automation webhook
 */
class WebhookDispatchJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum number of delivery attempts (including the first try).
     */
    public int $tries = 4;

    /**
     * Seconds to wait between retries: 30s, 2m, 5m.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Job timeout in seconds (hard kill from queue worker).
     */
    public int $timeout = 10;

    /**
     * @param array<string, mixed> $payload Pre-formatted webhook payload
     */
    public function __construct(private readonly array $payload) {}

    /**
     * Send the webhook payload to the configured URL.
     *
     * Uses a 5-second HTTP timeout to prevent queue worker stalls.
     * Throws on failure so the queue worker triggers the backoff retry.
     *
     * @return void
     * @throws Throwable on HTTP failure (triggers retry via backoff)
     */
    public function handle(): void
    {
        $url = config('dotwai.webhook_url');

        if (empty($url)) {
            return;  // Webhook not configured — discard silently
        }

        try {
            Http::timeout(5)->post($url, [
                'event'     => $this->payload['event'] ?? null,
                'timestamp' => $this->payload['timestamp'] ?? now()->toIso8601String(),
                'source'    => 'dotwai',
                'data'      => $this->payload['data'] ?? [],
            ]);

            Log::info('[DotwAI] Webhook dispatched', ['event' => $this->payload['event'] ?? null]);
        } catch (Throwable $e) {
            Log::warning('[DotwAI] Webhook dispatch failed, will retry', [
                'event' => $this->payload['event'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;  // Trigger retry via backoff
        }
    }

    /**
     * Handle job failure after all retries are exhausted.
     *
     * Logs the dead-lettered event for admin monitoring and reconciliation.
     *
     * @param Throwable $exception The final exception that caused failure
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        Log::error('[DotwAI] Webhook dispatch dead-lettered after retries', [
            'event'   => $this->payload['event'] ?? null,
            'payload' => $this->payload,
            'error'   => $exception->getMessage(),
        ]);
    }
}
