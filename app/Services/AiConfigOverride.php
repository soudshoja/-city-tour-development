<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Applies admin-managed AI settings (Settings > AI Configuration, stored in the
 * settings table as aicfg.* rows on company 1) over config/ai.php at boot.
 * .env stays the baseline; only non-empty DB values override, and deleting a
 * row reverts that field to the .env/config default.
 */
class AiConfigOverride
{
    public const CACHE_KEY = 'aicfg_overrides';

    /** UI field => config path(s) it overrides (first path is canonical). */
    public const MAP = [
        'resayil_key'            => ['ai.providers.resayil.key'],
        'resayil_model'          => ['ai.providers.resayil.model', 'ai.chain.0.model'],
        'resayil_model_fallback' => ['ai.chain.1.model'],
        'resayil_model_text'     => ['ai.providers.resayil.model_text'],
        'resayil_model_passport' => ['ai.providers.resayil.model_passport'],
        'resayil_timeout'        => ['ai.providers.resayil.timeout'],
        'resayil_max_pdf_pages'  => ['ai.providers.resayil.max_pdf_pages'],
        'openai_key'             => ['ai.providers.openai.key'],
        'openai_model'           => ['ai.providers.openai.model'],
        'fallback_enabled'       => ['ai.fallback_enabled'],
        'retries'                => ['ai.retries'],
        'alert_agent_emails'     => ['ai.alert_agent_emails'],
        'alert_throttle_minutes' => ['ai.alert_throttle_minutes'],
    ];

    public const INT_FIELDS = ['resayil_timeout', 'resayil_max_pdf_pages', 'retries', 'alert_throttle_minutes'];
    public const BOOL_FIELDS = ['fallback_enabled'];
    public const CSV_FIELDS = ['alert_agent_emails'];
    public const SECRET_FIELDS = ['resayil_key', 'openai_key'];

    public static function apply(): void
    {
        try {
            $rows = Cache::remember(self::CACHE_KEY, 60, function () {
                if (!Schema::hasTable('settings')) {
                    return [];
                }
                return Setting::where('company_id', 1)
                    ->where('key', 'like', 'aicfg.%')
                    ->pluck('value', 'key')
                    ->toArray();
            });
        } catch (\Throwable $e) {
            return; // never break boot on a config nicety
        }

        foreach ($rows as $key => $value) {
            $field = substr($key, strlen('aicfg.'));
            if (!isset(self::MAP[$field]) || $value === null || $value === '') {
                continue;
            }
            $v = $value;
            if (in_array($field, self::INT_FIELDS, true)) {
                $v = (int) $v;
            } elseif (in_array($field, self::BOOL_FIELDS, true)) {
                $v = filter_var($v, FILTER_VALIDATE_BOOLEAN);
            } elseif (in_array($field, self::CSV_FIELDS, true)) {
                $v = array_values(array_filter(array_map('trim', explode(',', $v))));
            }
            foreach (self::MAP[$field] as $path) {
                config([$path => $v]);
            }
        }

        // Custom fallback chain (aicfg.chain, JSON [{provider, model}, ...]) —
        // applied LAST so it fully replaces ai.chain, including any per-slot
        // model overrides above. Unknown providers are dropped.
        if (!empty($rows['aicfg.chain'])) {
            $decoded = json_decode((string) $rows['aicfg.chain'], true);
            if (is_array($decoded)) {
                $chain = [];
                foreach ($decoded as $entry) {
                    $provider = is_array($entry) ? ($entry['provider'] ?? null) : null;
                    if (!in_array($provider, ['resayil', 'openai'], true)) {
                        continue;
                    }
                    $model = is_array($entry) ? trim((string) ($entry['model'] ?? '')) : '';
                    $chain[] = $model !== ''
                        ? ['provider' => $provider, 'model' => $model]
                        : ['provider' => $provider];
                }
                if (count($chain) > 0) {
                    config(['ai.chain' => $chain]);
                }
            }
        }
    }
}
