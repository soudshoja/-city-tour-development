<?php
return [
    'default' => env('AI_PROVIDER', 'openai'),
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
        // Add more providers as needed
    ],
];