<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\AiHealthCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled AI model health check (every 10 minutes). Probes the Resayil text +
 * passport models and the OpenAI fallback key; caches the result for the
 * dashboard AI-status card, and WhatsApps the AI-down admins
 * (config ai.alert_agent_emails) on DOWN and on RECOVERY transitions.
 * While a model stays down, the alert repeats at most hourly.
 *
 * Alerting policy (2026-08-10): a probe must fail TWICE IN A ROW (~10 minutes
 * apart) before anyone is paged. Every "outage" observed to date was a
 * sub-2-minute tail-latency burst at the upstream gateway — a single 72s
 * request while a parallel probe in the same second answered in 774ms — and the
 * old "alert on the first failed probe" rule turned each of those into a real
 * WhatsApp to two real admins. The first failure is recorded as 'degraded' and
 * only colours the dashboard card. Recovery is only announced if a DOWN alert
 * was actually sent, so a blip no longer produces an "AI recovered" message for
 * an outage nobody was told about.
 *
 * The OpenAI probe is shown on the dashboard but does NOT page the admins on its
 * own (a missing/unfunded fallback key is a known state, not an outage).
 */
class CheckAiHealth extends Command
{
    protected $signature = 'ai:health-check';

    protected $description = 'Probe AI models, cache status for the dashboard, alert admins on down/recovery.';

    private const ALERTING_PROBES = ['passport', 'text'];

    public function handle(): int
    {
        $status = AiHealthCheck::run();

        foreach ($status['probes'] as $name => $probe) {
            $state = $probe['state'] ?? ($probe['ok'] ? 'ok' : 'down');

            $this->line(sprintf('%-10s %-9s %6.1fs  attempts=%d  %s %s',
                $name, strtoupper($state), $probe['seconds'], $probe['attempts'] ?? 1,
                $probe['model'], $probe['message'] ?? ''));

            if (!in_array($name, self::ALERTING_PROBES, true)) {
                continue;
            }

            $throttleKey = "ai_health_downalert_{$name}";
            $alertedKey = "ai_health_alerted_{$name}";

            if ($state === 'degraded') {
                // First failed run after a healthy one. Do NOT page — wait for
                // the next tick to confirm it is more than a latency burst.
                Log::warning("[AiHealthCheck] {$name} degraded (first failed probe, not paging)", [
                    'model' => $probe['model'],
                    'seconds' => $probe['seconds'],
                    'message' => $probe['message'],
                ]);
                continue;
            }

            if ($state === 'down') {
                // Second (or later) consecutive failure — this is a real outage.
                if (!Cache::has($throttleKey)) {
                    Cache::put($throttleKey, 1, now()->addMinutes(60));
                    Cache::put($alertedKey, 1, now()->addDays(7));
                    $this->alertAdmins(
                        "⚠️ *AI model DOWN*\n\n{$probe['label']} (`{$probe['model']}`) failed two consecutive "
                        . "health checks (~10 minutes apart).\n"
                        . 'Error: ' . ($probe['message'] ?? 'unknown') . "\n\n"
                        . 'Passport reading / AI features may fail until it is fixed. '
                        . 'Check Settings > AI Configuration to switch the model.'
                    );
                }
                continue;
            }

            if ($state === 'ok' && Cache::has($alertedKey)) {
                // Only announce recovery for an outage the admins were told about.
                Cache::forget($throttleKey);
                Cache::forget($alertedKey);
                $this->alertAdmins(
                    "✅ *AI model recovered*\n\n{$probe['label']} (`{$probe['model']}`) is responding again "
                    . "({$probe['seconds']}s)."
                );
            } elseif ($state === 'ok') {
                Cache::forget($throttleKey);
            }
        }

        // TTL rather than forever: a prev-run snapshot that outlives the
        // scheduler by days must not silently drive the degraded/down decision.
        Cache::put(AiHealthCheck::PREV_KEY, $status, now()->addMinutes(60));

        return self::SUCCESS;
    }

    private function alertAdmins(string $message): void
    {
        try {
            $emails = array_map('strtolower', (array) config('ai.alert_agent_emails', []));
            if (empty($emails)) {
                return;
            }
            $admins = Agent::whereIn(DB::raw('LOWER(email)'), $emails)->get();
            foreach ($admins as $admin) {
                (new \App\Http\Controllers\ResayilController())->message(
                    $admin->phone_number, $admin->country_code ?? '', $message, isDummyNumber: false
                );
            }
            Log::warning('[AiHealthCheck] admin alert sent', ['admins' => $admins->pluck('id'), 'message' => substr($message, 0, 120)]);
        } catch (\Throwable $e) {
            Log::error('[AiHealthCheck] admin alert failed: ' . $e->getMessage());
        }
    }
}
