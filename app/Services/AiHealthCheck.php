<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Probes the AI models the app depends on (Resayil text + passport/vision,
 * OpenAI fallback key) and caches the result for the dashboard status card.
 * Used by the ai:health-check scheduled command and the dashboard refresh.
 *
 * Latency policy (2026-08-10): the models routinely show 40-121s tail latency
 * on individual requests while parallel requests in the same second return in
 * under a second. The old 45s probe timeout was therefore STRICTER than real
 * traffic (ResayilClient uses ai.providers.resayil.timeout = 120s) and far
 * stricter than the gateway itself (480s non-streaming), so ordinary tails were
 * being reported as "model DOWN". The probe now uses the same timeout as real
 * traffic and retries ONCE on a timeout / connection error before failing, and
 * a probe that fails after a healthy previous run is 'degraded', not 'down'.
 */
class AiHealthCheck
{
    public const STATUS_KEY = 'ai_health_status';

    /** Previous run's status — drives the degraded-vs-down decision. */
    public const PREV_KEY = 'ai_health_prev';

    /** Cached status lifetime. NOT forever: see the Cache::put() note in run(). */
    public const STATUS_TTL_MINUTES = 30;

    /** Status older than this renders as "status unknown / stale" on the card. */
    public const STALE_AFTER_MINUTES = 15;

    /** Once a whole run has burned this many seconds, stop spending retries. */
    private const RUN_RETRY_BUDGET_SECONDS = 180;

    private static ?float $runStartedAt = null;

    /** Run all probes, cache and return the status array. */
    public static function run(): array
    {
        AiConfigOverride::apply();

        self::$runStartedAt = microtime(true);
        $prev = Cache::get(self::PREV_KEY);

        try {
            $probes = [
                'passport' => self::probeVision(),
                'text' => self::probeText(),
                'openai' => self::probeOpenai(),
            ];
        } finally {
            self::$runStartedAt = null;
        }

        // A failure that follows a HEALTHY run is 'degraded' (almost always a
        // tail-latency burst that clears within one 10-minute tick). Only a
        // failure that follows an already-failed run counts as 'down' — that is
        // the state CheckAiHealth pages the admins on.
        foreach ($probes as $name => $probe) {
            if (isset($probe['state'])) {
                continue; // probe already declared its own state (e.g. 'disabled')
            }
            if ($probe['ok']) {
                $probes[$name]['state'] = 'ok';
                continue;
            }
            $prevOk = $prev['probes'][$name]['ok'] ?? true;
            $probes[$name]['state'] = $prevOk ? 'degraded' : 'down';
        }

        $status = [
            'checked_at' => now()->toIso8601String(),
            'probes' => $probes,
        ];

        // TTL, not Cache::forever(): with forever, one blip read as DOWN until
        // the next tick, and if the scheduler ever stops (the citycomm crontab
        // WAS found empty once — see the 2026-08-06 note in crontab -l) the card
        // froze on an ancient status with no staleness signal at all.
        Cache::put(self::STATUS_KEY, $status, now()->addMinutes(self::STATUS_TTL_MINUTES));

        return $status;
    }

    public static function current(): ?array
    {
        $v = Cache::get(self::STATUS_KEY);
        if (!is_array($v)) {
            return null;
        }

        $v['stale'] = self::isStale($v['checked_at'] ?? null);

        return $v;
    }

    /** True when the cached status is too old to be trusted by the dashboard. */
    public static function isStale(?string $checkedAt): bool
    {
        if (!is_string($checkedAt) || $checkedAt === '') {
            return true;
        }
        try {
            return Carbon::parse($checkedAt)->lt(now()->subMinutes(self::STALE_AFTER_MINUTES));
        } catch (\Throwable $e) {
            return true;
        }
    }

    private static function probeText(): array
    {
        $model = (string) config('ai.providers.resayil.model_text');
        $messages = [['role' => 'user', 'content' => 'Reply with exactly: OK']];

        return self::chatProbe($model, $messages, 'Text model');
    }

    private static function probeVision(): array
    {
        $model = (string) config('ai.providers.resayil.model_passport');
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAYklEQVRoge3PMQ0AIADAMEAI/kUhBhEcDcmqYJtn7/GzpQNeNaA1oDWgNaA1oDWgNaA1oDWgNaA1oDWgNaA1oDWgNaA1oDWgNaA1oDWgNaA1oDWgNaA1oDWgNaA1oDWgNaBdCJ0BmI1Z9gwAAAAASUVORK5CYII=';
        $messages = [['role' => 'user', 'content' => [
            ['type' => 'text', 'text' => 'What color is this image? One word.'],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . $png]],
        ]]];

        return self::chatProbe($model, $messages, 'Passport model');
    }

    /** Retries are only worth spending while the overall run is still young. */
    private static function mayRetry(): bool
    {
        if (self::$runStartedAt === null) {
            return true;
        }

        return (microtime(true) - self::$runStartedAt) < self::RUN_RETRY_BUDGET_SECONDS;
    }

    private static function chatProbe(string $model, array $messages, string $label): array
    {
        // Match real traffic (ResayilClient::$timeout) instead of the old hard 45s.
        $timeout = (int) config('ai.providers.resayil.timeout', 120);
        if ($timeout < 30) {
            $timeout = 30;
        }

        $url = rtrim((string) config('ai.providers.resayil.url'), '/') . '/chat/completions';
        $key = (string) config('ai.providers.resayil.key');
        $payload = ['model' => $model, 'messages' => $messages, 'max_tokens' => 300];

        $t0 = microtime(true);
        $ok = false;
        $message = null;
        $attempts = 0;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $attempts = $attempt;
            try {
                $r = Http::withToken($key)->withoutVerifying()->timeout($timeout)->post($url, $payload);
                $content = $r->json()['choices'][0]['message']['content'] ?? null;
                $ok = $r->successful() && $content !== null && trim((string) $content) !== '';
                $message = $ok ? null : ('HTTP ' . $r->status() . ': ' . substr((string) $r->body(), 0, 150));
                break; // a real HTTP answer, good or bad, is not a latency problem
            } catch (ConnectionException $e) {
                // Timeout / connect failure — the exact class of failure that a
                // single upstream tail produces. Retry once before believing it.
                $message = substr($e->getMessage(), 0, 150);
                if ($attempt === 1 && self::mayRetry()) {
                    continue;
                }
                break;
            } catch (\Throwable $e) {
                $message = substr($e->getMessage(), 0, 150);
                break;
            }
        }

        return [
            'label' => $label,
            'model' => $model,
            'ok' => $ok,
            'seconds' => round(microtime(true) - $t0, 1),
            'attempts' => $attempts,
            'message' => $message,
        ];
    }

    private static function probeOpenai(): array
    {
        $label = 'OpenAI fallback';
        $model = (string) config('ai.providers.openai.model');

        // Do not probe (and do not paint the card red) when OpenAI is not
        // actually part of the active chain — a permanently red tile for a
        // provider nothing routes to just trains everyone to ignore red.
        $inChain = false;
        foreach ((array) config('ai.chain', []) as $entry) {
            $provider = is_array($entry) ? ($entry['provider'] ?? null) : $entry;
            if ($provider === 'openai') {
                $inChain = true;
                break;
            }
        }
        if (!$inChain) {
            return ['label' => $label, 'model' => $model, 'ok' => false, 'state' => 'disabled',
                'seconds' => 0, 'attempts' => 0, 'message' => 'Not in the active AI chain — not probed'];
        }

        $key = (string) config('ai.providers.openai.key');
        if ($key === '') {
            return ['label' => $label, 'model' => $model, 'ok' => false, 'state' => 'disabled',
                'seconds' => 0, 'attempts' => 0, 'message' => 'No API key configured'];
        }

        $t0 = microtime(true);
        try {
            // A real (1-token) completion — /models returns 200 even for keys
            // with exhausted quota, which is exactly the failure that matters.
            $r = Http::withToken($key)->timeout(30)
                ->post(rtrim((string) config('ai.providers.openai.url'), '/') . '/chat/completions', [
                    'model' => $model,
                    'messages' => [['role' => 'user', 'content' => 'Say OK']],
                    'max_tokens' => 1,
                ]);
            $ok = $r->successful();
            $msg = null;
            if (!$ok) {
                $err = $r->json()['error']['message'] ?? substr((string) $r->body(), 0, 120);
                $msg = 'HTTP ' . $r->status() . ': ' . substr((string) $err, 0, 120);
            }

            return [
                'label' => $label,
                'model' => $model,
                'ok' => $ok,
                'seconds' => round(microtime(true) - $t0, 1),
                'attempts' => 1,
                'message' => $msg,
            ];
        } catch (\Throwable $e) {
            return ['label' => $label, 'model' => $model,
                'ok' => false, 'seconds' => round(microtime(true) - $t0, 1), 'attempts' => 1,
                'message' => substr($e->getMessage(), 0, 150)];
        }
    }
}
