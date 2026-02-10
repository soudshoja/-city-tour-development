<?php

namespace App\Services;

use App\Models\DocumentProcessingLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ErrorAlertService
{
    /**
     * Check error thresholds and send alerts if needed (ERR-05)
     */
    public function checkThresholds(): array
    {
        if (!config('webhook.alerting.enabled', true)) {
            return [
                'checked' => false,
                'reason' => 'Alerting disabled in config',
            ];
        }

        $alerts = [];

        // Check error rate threshold
        $errorRateAlert = $this->checkErrorRateThreshold();
        if ($errorRateAlert) {
            $alerts[] = $errorRateAlert;
            $this->sendAlert('error_rate_exceeded', $errorRateAlert);
        }

        // Check consecutive failures
        $consecutiveFailuresAlert = $this->checkConsecutiveFailures();
        if ($consecutiveFailuresAlert) {
            $alerts[] = $consecutiveFailuresAlert;
            $this->sendAlert('consecutive_failures', $consecutiveFailuresAlert);
        }

        return [
            'checked' => true,
            'alerts' => $alerts,
            'alert_count' => count($alerts),
        ];
    }

    /**
     * Check if error rate exceeds threshold
     */
    protected function checkErrorRateThreshold(): ?array
    {
        $threshold = config('webhook.alerting.error_rate_threshold', 10); // 10%
        $cooldownMinutes = config('webhook.alerting.alert_cooldown_minutes', 30);
        $cacheKey = 'error_alert:error_rate_threshold';

        // Check cooldown
        if (Cache::has($cacheKey)) {
            return null; // Still in cooldown period
        }

        // Calculate error rate for the last hour
        $oneHourAgo = now()->subHour();
        $totalProcessed = DocumentProcessingLog::where('created_at', '>=', $oneHourAgo)->count();
        $totalFailed = DocumentProcessingLog::where('created_at', '>=', $oneHourAgo)
            ->where('status', 'failed')
            ->count();

        if ($totalProcessed === 0) {
            return null; // No data to analyze
        }

        $errorRate = round(($totalFailed / $totalProcessed) * 100, 2);

        if ($errorRate >= $threshold) {
            // Set cooldown
            Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));

            return [
                'type' => 'error_rate_exceeded',
                'severity' => 'warning',
                'threshold' => $threshold,
                'current_rate' => $errorRate,
                'total_processed' => $totalProcessed,
                'total_failed' => $totalFailed,
                'time_window' => '1 hour',
                'message' => "Error rate ({$errorRate}%) exceeded threshold ({$threshold}%) in the last hour",
            ];
        }

        return null;
    }

    /**
     * Check for consecutive failures
     */
    protected function checkConsecutiveFailures(): ?array
    {
        $threshold = config('webhook.alerting.consecutive_failures', 5);
        $cooldownMinutes = config('webhook.alerting.alert_cooldown_minutes', 30);
        $cacheKey = 'error_alert:consecutive_failures';

        // Check cooldown
        if (Cache::has($cacheKey)) {
            return null;
        }

        // Get last N documents
        $recentDocs = DocumentProcessingLog::orderBy('created_at', 'desc')
            ->limit($threshold)
            ->get();

        if ($recentDocs->count() < $threshold) {
            return null; // Not enough data
        }

        // Check if all are failed
        $allFailed = $recentDocs->every(function ($doc) {
            return $doc->status === 'failed';
        });

        if ($allFailed) {
            // Set cooldown
            Cache::put($cacheKey, true, now()->addMinutes($cooldownMinutes));

            $errorCodes = $recentDocs->pluck('error_code')->unique()->values()->toArray();

            return [
                'type' => 'consecutive_failures',
                'severity' => 'critical',
                'threshold' => $threshold,
                'consecutive_count' => $recentDocs->count(),
                'error_codes' => $errorCodes,
                'message' => "{$threshold} consecutive document processing failures detected",
            ];
        }

        return null;
    }

    /**
     * Send alert notification
     * Phase 1: Log only
     * Phase 2: Add Slack/email integration
     */
    public function sendAlert(string $type, array $context): void
    {
        $logLevel = $context['severity'] === 'critical' ? 'critical' : 'warning';

        Log::log($logLevel, "Error Alert: {$type}", [
            'alert_type' => $type,
            'context' => $context,
            'timestamp' => now()->toIso8601String(),
        ]);

        // TODO Phase 2: Send to Slack/Email
        // TODO: Implement Slack webhook notification
        // TODO: Implement email notification to admin team
    }

    /**
     * Clear all alert cooldowns (for testing)
     */
    public function clearCooldowns(): void
    {
        Cache::forget('error_alert:error_rate_threshold');
        Cache::forget('error_alert:consecutive_failures');
    }

    /**
     * Get current alert status (for monitoring)
     */
    public function getAlertStatus(): array
    {
        $errorRateCooldown = Cache::has('error_alert:error_rate_threshold');
        $consecutiveFailuresCooldown = Cache::has('error_alert:consecutive_failures');

        return [
            'error_rate_alert_active' => $errorRateCooldown,
            'consecutive_failures_alert_active' => $consecutiveFailuresCooldown,
            'cooldown_minutes' => config('webhook.alerting.alert_cooldown_minutes', 30),
        ];
    }
}
