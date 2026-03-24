<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Services;

use App\Modules\DotwAI\Jobs\WebhookDispatchJob;
use Illuminate\Support\Facades\Log;

/**
 * Webhook event coordination service.
 *
 * Static methods for dispatching async events to n8n webhook.
 * Each event is dispatched via queue job for fire-and-forget reliability.
 *
 * Supported event types:
 * - booking_confirmed   Fired after credit or gateway confirmation
 * - payment_completed   Fired when MyFatoorah callback confirms payment
 * - reminder_due        Fired by SendReminderJob when deadline reminder is sent
 * - deadline_passed     Fired by AutoInvoiceDeadlineJob when deadline passes
 *
 * @see EVNT-01 Laravel pushes async events to automation webhook
 */
class WebhookEventService
{
    /**
     * Dispatch an event to the webhook URL (via queue job).
     *
     * Silently skips if webhook_url is not configured or the event type
     * is not listed in dotwai.webhook_events config.
     *
     * @param string               $eventType One of: payment_completed, reminder_due, deadline_passed, booking_confirmed
     * @param array<string, mixed> $data      Event-specific payload fields
     *
     * @return void
     */
    public static function dispatchEvent(string $eventType, array $data): void
    {
        if (empty(config('dotwai.webhook_url'))) {
            Log::debug('[DotwAI] Webhook not configured, skipping event', ['event' => $eventType]);

            return;  // Webhook not configured
        }

        if (!self::isEventEnabled($eventType)) {
            Log::debug('[DotwAI] Event type disabled', ['event' => $eventType]);

            return;  // Event type not enabled
        }

        $payload = self::formatEventPayload($eventType, $data);
        WebhookDispatchJob::dispatch($payload);

        Log::info('[DotwAI] Event dispatched to queue', ['event' => $eventType]);
    }

    /**
     * Format event payload for webhook delivery.
     *
     * @param string               $eventType Event type
     * @param array<string, mixed> $data      Event data
     *
     * @return array<string, mixed> Formatted payload
     */
    public static function formatEventPayload(string $eventType, array $data): array
    {
        return [
            'event'     => $eventType,
            'timestamp' => now()->toIso8601String(),
            'source'    => 'dotwai',
            'data'      => $data,
        ];
    }

    /**
     * Check if an event type is enabled in config.
     *
     * @param string $eventType Event type
     *
     * @return bool True if enabled
     */
    private static function isEventEnabled(string $eventType): bool
    {
        $enabled = config('dotwai.webhook_events', []);

        return in_array($eventType, $enabled, true);
    }
}
