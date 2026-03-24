<?php

/**
 * DOTW V4 API Configuration
 * DOTWconnect (DCML) - XML-based hotel booking API
 *
 * @see https://dotw.dotwconnect.com/ (Production)
 * @see https://xmldev.dotwconnect.com/ (Development/Sandbox)
 */

return [
    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | DOTW username, plain password (will be MD5'd by service), and company code
    | Always use environment variables, never hardcode credentials
    |
    */
    'username' => env('DOTW_USERNAME', ''),
    'password' => env('DOTW_PASSWORD', ''),
    'company_code' => env('DOTW_COMPANY_CODE', ''),

    /*
    |--------------------------------------------------------------------------
    | Development Mode
    |--------------------------------------------------------------------------
    |
    | When true: uses xmldev.dotwconnect.com (sandbox environment)
    | When false: uses us.dotwconnect.com (production)
    |
    | In development mode, no real bookings are sent to suppliers
    |
    */
    'dev_mode' => env('DOTW_DEV_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | Full URLs for development and production gateways
    |
    */
    'endpoints' => [
        'development' => 'https://xmldev.dotwconnect.com/gatewayV4.dotw',
        'production' => 'https://us.dotwconnect.com/gatewayV4.dotw',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Configuration
    |--------------------------------------------------------------------------
    |
    | timeout: Maximum seconds to wait for a response
    | source: DOTW source code (always 1 for hotel bookings)
    | product: Product type (always "hotel")
    |
    */
    'request' => [
        // Maximum seconds to wait for a DOTW API response. DOTW SLA: 25 seconds (ERROR-02).
        'timeout' => env('DOTW_TIMEOUT', 25),
        'connect_timeout' => env('DOTW_CONNECT_TIMEOUT', 30),
        'source' => 1,
        'product' => 'hotel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allocation Expiry
    |--------------------------------------------------------------------------
    |
    | How long an allocation (rate block) is valid after getRooms
    | Default: 3 minutes (as per DOTW specification)
    |
    */
    'allocation_expiry_minutes' => env('DOTW_ALLOCATION_EXPIRY_MINUTES', 3),

    /*
    |--------------------------------------------------------------------------
    | B2C Markup
    |--------------------------------------------------------------------------
    |
    | Percentage markup to apply to B2C bookings
    | Default: 20% like other travel APIs
    |
    */
    'b2c_markup_percentage' => env('DOTW_B2C_MARKUP', 20),

    /*
    |--------------------------------------------------------------------------
    | Rate Basis Codes Reference
    |--------------------------------------------------------------------------
    |
    | Standard meal plan codes used in DOTW V4 API
    | These are provided for reference and used internally
    |
    */
    'rate_basis_codes' => [
        'ALL' => -1,             // All Rates (best available)
        'ROOM_ONLY' => 1331,     // Room Only
        'BB' => 1332,            // Bed & Breakfast
        'HB' => 1333,            // Half Board
        'FB' => 1334,            // Full Board
        'AI' => 1335,            // All Inclusive
        'SC' => 1336,            // Self Catering
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Log channel for DOTW requests and responses
    | Configure the 'dotw' channel in config/logging.php
    |
    */
    'log_channel' => 'dotw',

    /*
    |--------------------------------------------------------------------------
    | Search Result Cache
    |--------------------------------------------------------------------------
    |
    | TTL for hotel search results in seconds.
    | Default: 150 seconds (2.5 minutes) — reduces DOTW API calls during
    | multi-question WhatsApp conversations.
    |
    | Prefix applied to all DOTW cache keys.
    |
    */
    'cache' => [
        'ttl' => env('DOTW_CACHE_TTL', 150),
        'prefix' => env('DOTW_CACHE_PREFIX', 'dotw_search'),
    ],
];
