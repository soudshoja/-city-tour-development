<?php
return [
    'default'  => env('AI_PROVIDER', 'openai'), // kept for back-compat
    'primary'  => env('AI_PRIMARY', 'resayil'),
    'fallback' => env('AI_FALLBACK', 'openai'),
    'retries'  => (int) env('AI_RETRIES', 2),
    'fallback_enabled' => filter_var(env('AI_FALLBACK_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    // Ordered AI fallback chain. Tried top-to-bottom; first usable result wins.
    // Same provider may appear with different models.
    // NOTE: model IDs must exist in the current Resayil catalog (GET
    // llmapi.resayil.io/v1/models) — the catalog changes over time and stale IDs
    // 404, silently falling through to the (dead) OpenAI key.
    //
    // VISION CAPABILITY — re-verified against the live gateway 2026-08-10 by
    // POSTing a 64x64 PNG to /chat/completions. The 2026-07-16 note that used to
    // sit here was WRONG and must not be trusted again:
    //   mistral-large-3:675b  image OK
    //   qwen3.5:397b          image OK
    //   kimi-k2.6             image OK
    //   glm-5.1               HTTP 400 "Model 'GLM-5.1' does not support image input"
    //   gemma4:31b            HTTP 400 "Model 'Gemma 4 31B' does not support image input"
    //   minimax-m3            HTTP 200 but answers "I don't see an image attached"
    //                         (silently blind — never use it for image work)
    // Only the three "image OK" models may be used for passport/image work.
    // Re-run that check before adding any model to passport_fallback_models.
    //
    // 2026-08-10: the OpenAI last hop was DISABLED. That key returns HTTP 429
    // ("You have no credits remaining") permanently, so the chain's final
    // fallback was a guaranteed failure and the health card carried a
    // permanently-red tile that trained everyone to ignore red. It is replaced
    // by a THIRD distinct vision-capable Resayil model. The openai provider CODE
    // is untouched — set AI_OPENAI_HOP_ENABLED=true in .env to put the hop back
    // the moment the key is funded again.
    //
    // NOTE: config/ai.php is only the BASELINE. Settings > AI Configuration
    // stores an aicfg.chain row that, when present, REPLACES this array wholesale
    // (see App\Services\AiConfigOverride). Editing this file alone will not change
    // the live chain while that row exists.
    'chain' => array_values(array_filter([
        ['provider' => 'resayil', 'model' => env('RESAYIL_MODEL', 'qwen3.5:397b')],
        ['provider' => 'resayil', 'model' => env('RESAYIL_MODEL_FALLBACK', 'glm-5.1')],
        ['provider' => 'resayil', 'model' => env('RESAYIL_MODEL_FALLBACK2', 'kimi-k2.6')],
        filter_var(env('AI_OPENAI_HOP_ENABLED', false), FILTER_VALIDATE_BOOLEAN)
            ? ['provider' => 'openai']
            : null,
    ])),
    'providers' => [
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1'),
        ],
        'anythingLLM' => [
            'base'      => env('ANYLLM_BASE', ''),
            'api_key'   => env('ANYLLM_API_KEY', ''),
            'workspace' => env('ANYLLM_WORKSPACE', ''),
            'timeout'   => (int) env('ANYLLM_TIMEOUT', 45),
            'slug'     => env('ANYLLM_SLUG', 'default-workspace'),
        ],
        'openwebui' => [
            'key' => env('OPENWEBUI_API_KEY'),
            'url' => env('OPENWEBUI_API_URL', 'http://localhost:3000/api'),
            'model' => env('OPENWEBUI_MODEL', 'city-tour-staging'),
        ],
        'resayil' => [
            'url'           => env('RESAYIL_BASE', 'https://llmapi.resayil.io/v1'),
            'key'           => env('RESAYIL_API_KEY'),
            'model'         => env('RESAYIL_MODEL', 'qwen3.5:397b'),
            'model_text'    => env('RESAYIL_MODEL_TEXT', 'gpt-oss:120b'),
            'model_passport'=> env('RESAYIL_MODEL_PASSPORT', 'mistral-large-3:675b'), // vision model for passport OCR (gemma3:27b removed from gateway; gemma4:31b too slow — benchmarked 2026-07-28)
            // Passport fallback ladder: hop 0 uses model_passport above, each later
            // chain hop takes the next model here. These must be VISION-CAPABLE
            // (see the verification table at the top of this file) — the chain's own
            // models are text-tuned and glm-5.1 hard-rejects images, which is why the
            // passport path keeps a separate ladder instead of reusing the chain.
            'passport_fallback_models' => array_values(array_filter(array_map('trim',
                explode(',', env('RESAYIL_PASSPORT_FALLBACK_MODELS', 'qwen3.5:397b,kimi-k2.6'))))),

            'max_pdf_pages' => (int) env('RESAYIL_MAX_PDF_PAGES', 6),
            'timeout'       => (int) env('RESAYIL_TIMEOUT', 120),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI-down admin alerting
    |--------------------------------------------------------------------------
    | When AI document extraction hard-fails (WhatsApp passport flow), these
    | agents get a WhatsApp alert (throttled) so they can switch the model.
    */
    'alert_agent_emails' => array_filter(array_map('trim', explode(',', env('AI_ALERT_AGENT_EMAILS', 'Saeid@citytravelers.co,Soud@citytravelers.co')))),
    'alert_throttle_minutes' => (int) env('AI_ALERT_THROTTLE_MINUTES', 30),
];
