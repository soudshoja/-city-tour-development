<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'tap' => [
        'secret' => env('APP_ENV') == 'production' ? env('TAP_SECRET') : env('TAP_SANDBOX_SECRET'),
        'public' => env('APP_ENV') == 'production' ? env('TAP_PUBLIC') : env('TAP_SANDBOX_PUBLIC'),
        'url' => env('TAP_URL') . '/v2',
    ],

    'convert-api' => [
        'secret' => env('CONVERT_API_SECRET'),
    ],

    'whatsapp' => [
        'url' => env('WHATSAPP_URL') . '/' . env('WHATSAPP_API_VERSION'),
        'phone-number-id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'token' => env('WHATSAPP_TOKEN'),
    ],

    'open-ai' => [
        'model' => env('OPENAI_MODEL'),
        'url' => env('OPENAI_URL').'/'.env("OPENAI_VERSION"),
        'key' => env('OPENAI_KEY'),
    ],
    'tbo' => [
        'url' => env('APP_ENV') == 'production' ? env('TBO_URL') : env('TBO_SANDBOX_URL'),
        'username' => env('APP_ENV') == 'production' ? env('TBO_USERNAME') : env('TBO_SANDBOX_USERNAME'),
        'password' => env('APP_ENV') == 'production' ? env('TBO_PASSWORD') : env('TBO_SANDBOX_PASSWORD'),
    ],
    'currency-api' => [
        'url' => env('CURRENCY_API_URL'),
        'key' => env('CURRENCY_API_KEY'),
    ],
];
