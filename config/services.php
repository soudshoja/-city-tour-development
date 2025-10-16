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

    'myfatoorah' => [
        'api_key'             => env('APP_ENV') == 'production' ? env('MYFATOORAH_LIVE_KEY') : env('MYFATOORAH_SANDBOX_KEY'),
        'base_url'            => env('APP_ENV') == 'production' ? env('MYFATOORAH_LIVE_URL') . '/v2' : env('MYFATOORAH_SANDBOX_URL') . '/v2',
        'test_mode'           => env('MYFATOORAH_TEST_MODE', true),
        'country_iso'         => env('MYFATOORAH_COUNTRY_ISO', 'KWT'),
        'save_card'           => env('MYFATOORAH_SAVE_CARD', false),
        'webhook_secret_key'  => env('MYFATOORAH_WEBHOOK_SECRET', ''),
        'register_apple_pay'  => env('MYFATOORAH_APPLE_PAY', false),
    ],

    'convert-api' => [
        'secret' => env('CONVERT_API_SECRET'),
    ],

    'whatsapp' => [
        'url' => env('WHATSAPP_URL'), 
        'phone-number-id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'token' => env('WHATSAPP_TOKEN'),
        'graph_api_url' => env('WHATSAPP_GRAPH_API_URL', 'https://graph.facebook.com/v22.0'), 
    ],

    'resayil' => [
        'base_url'  => env('RESAYIL_BASE_URL'),
        'version' => env('RESAYIL_API_VERSION', 'v1'),
        'api_token' => env('RESAYIL_API_TOKEN'),
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

    'magic-holiday' => [
        'url' => env('MAGIC_HOLIDAY_URL'),
        'client-id' => env('MAGIC_HOLIDAY_CLIENT_ID'),
        'client-secret' => env('MAGIC_HOLIDAY_CLIENT_SECRET'),
        'authorization_url' => env('MAGIC_HOLIDAY_AUTHORIZATION_URL'),
        'token-url' => env('MAGIC_HOLIDAY_TOKEN_URL'),
    ],

    'uPayment' => [
        'api_key' => env('APP_ENV') == 'production' ? env('UPAYMENT_LIVE_KEY') : env('UPAYMENT_SANDBOX_KEY'),
        'base_url' => rtrim(env('APP_ENV') == 'production' ? env('UPAYMENT_LIVE_URL') : env('UPAYMENT_SANDBOX_URL'), '/') . '/v1',
    ],
    
    'hesabe' => [
        'api_key' => env('APP_ENV') == 'production' ? env('HESABE_SECRET_KEY') : env('HESABE_SANDBOX_SECRET_KEY'),
        'base_url' => env('APP_ENV') == 'production' ? env('HESABE_PRODUCTION') : env('HESABE_SANDBOX'),
        'merchant_code' => env('APP_ENV') == 'production' ? env('HESABE_MERCHANT_CODE') : env('HESABE_SANDBOX_MERCHANT_CODE'),
        'access_code' => env('APP_ENV') == 'production' ? env('HESABE_ACCESS_CODE') : env('HESABE_SANDBOX_ACCESS_CODE'),
        'iv_key' => env('APP_ENV') == 'production' ? env('HESABE_IV_KEY') : env('HESABE_SANDBOX_IV_KEY'),
    ],

    'n8n' => [
        'webhook_url' => env('N8N_WEBHOOK_URL'),
    ],

    'iata' => [
        'base_url' => env('IATA_API_BASE_URL'),
        'token_url' => env('IATA_TOKEN_URL'),
    ],
];
