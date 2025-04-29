<?php
return [
    'default' => env('AI_PROVIDER', 'openai'),
    'providers' => [
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1'),
        ],
        // Add more providers as needed
    ],
];