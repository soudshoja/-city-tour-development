<?php

return [
    /**
     * Bearer token for ResailAI n8n webhook authentication
     * Retrieved from RESAILAI_API_TOKEN environment variable
     */
    'api_token' => env('RESAILAI_API_TOKEN', ''),

    /**
     * URL of n8n webhook for PDF processing
     * Retrieved from N8N_WEBHOOK_URL environment variable
     */
    'n8n_webhook_url' => env('N8N_WEBHOOK_URL', ''),

    /**
     * HTTP timeout in seconds for API calls
     */
    'timeout' => env('RESAILAI_TIMEOUT', 30),

    /**
     * Number of callback retry attempts
     */
    'max_retries' => env('RESAILAI_MAX_RETRIES', 3),

    /**
     * How long callbacks are valid in minutes
     */
    'callback_expiry_minutes' => env('RESAILAI_CALLBACK_EXPIRY_MINUTES', 15),
];
