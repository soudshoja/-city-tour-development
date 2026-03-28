<?php

declare(strict_types=1);

/**
 * DotwAI Module Configuration
 *
 * Controls B2B/B2C track toggles, markup, search limits, cache TTL,
 * fuzzy match settings, and DOTW API defaults for the DotwAI module.
 *
 * Per-company overrides are stored in the company_dotw_credentials table
 * (b2b_enabled, b2c_enabled, markup_percent columns). These config values
 * serve as global defaults when per-company values are not set.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | B2B / B2C Track Toggles (Global Defaults)
    |--------------------------------------------------------------------------
    |
    | These are the fallback values when a company's credential row does not
    | have explicit b2b_enabled / b2c_enabled columns set.
    |
    */
    'b2b_enabled' => env('DOTWAI_B2B_ENABLED', true),
    'b2c_enabled' => env('DOTWAI_B2C_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Markup
    |--------------------------------------------------------------------------
    |
    | Default B2C markup percentage applied when the company credential row
    | does not specify a custom markup_percent value.
    |
    */
    'default_markup_percent' => env('DOTWAI_DEFAULT_MARKUP', 20),

    /*
    |--------------------------------------------------------------------------
    | Search Settings
    |--------------------------------------------------------------------------
    |
    | search_results_limit: Maximum hotels returned per search request.
    | search_cache_ttl: Seconds to cache search results per phone number.
    |   10 minutes gives the user time to browse options in WhatsApp.
    |
    */
    'search_results_limit' => env('DOTWAI_SEARCH_LIMIT', 10),
    'search_cache_ttl' => env('DOTWAI_SEARCH_CACHE_TTL', 600),

    /*
    |--------------------------------------------------------------------------
    | Fuzzy Matching
    |--------------------------------------------------------------------------
    |
    | Maximum Levenshtein distance for fuzzy name matching. Lower values
    | are stricter; 3 allows minor typos like "Hilton" vs "Hilten".
    |
    */
    'fuzzy_match_threshold' => 3,

    /*
    |--------------------------------------------------------------------------
    | AI System Message
    |--------------------------------------------------------------------------
    |
    | Path to the bilingual Arabic/English system message template that
    | instructs the n8n AI agent on available tools and conversation style.
    |
    */
    'system_message_path' => __DIR__ . '/dotwai-system-message.md',

    /*
    |--------------------------------------------------------------------------
    | DOTW API Defaults
    |--------------------------------------------------------------------------
    |
    | Default currency, nationality, and residence codes used when the
    | request does not specify explicit values.
    |
    | 520 = KWD, 66 = Kuwait (DOTW internal codes)
    |
    */
    'default_currency' => env('DOTWAI_DEFAULT_CURRENCY', '520'),
    'default_nationality' => env('DOTWAI_DEFAULT_NATIONALITY', '66'),
    'default_residence' => env('DOTWAI_DEFAULT_RESIDENCE', '66'),
    'display_currency' => env('DOTWAI_DISPLAY_CURRENCY', 'KWD'),

    /*
    |--------------------------------------------------------------------------
    | Booking Settings (Phase 19)
    |--------------------------------------------------------------------------
    |
    | prebook_expiry_minutes: How long a prebooked rate is held before
    |   the user must search again (default 30 minutes).
    | payment_link_expiry_hours: How long a payment link remains valid
    |   for gateway/B2C flows (default 48 hours).
    | default_payment_gateway: Which payment gateway to use when generating
    |   payment links (default myfatoorah).
    |
    */
    'prebook_expiry_minutes'   => env('DOTWAI_PREBOOK_EXPIRY', 30),
    'payment_link_expiry_hours' => env('DOTWAI_PAYMENT_LINK_EXPIRY', 48),
    'default_payment_gateway'  => env('DOTWAI_DEFAULT_GATEWAY', 'myfatoorah'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration (Phase 21 Plan 02)
    |--------------------------------------------------------------------------
    |
    | webhook_url: URL to POST lifecycle events to (e.g., n8n workflow endpoint).
    |   Empty string = webhooks disabled.
    |
    | webhook_events: List of lifecycle events that trigger webhook dispatch.
    |   Add/remove event types to control which events fire webhooks.
    |
    */
    'webhook_url' => env('DOTWAI_WEBHOOK_URL', ''),

    'webhook_events' => [
        'payment_completed',
        'reminder_due',
        'deadline_passed',
        'booking_confirmed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Special Request Codes (CERT-02)
    |--------------------------------------------------------------------------
    |
    | DOTW API special request codes from getspecialsrequests API.
    | Keys are the numeric codes sent in <req runno="N">CODE</req>.
    | Source: Olga Chicu screenshot 2026-03-27.
    |
    */
    'special_request_codes' => [
        92255 => 'Allergy - Nut or Food or Bedding',
        92245 => 'Guest celebrating a birthday',
        92235 => 'Guest celebrating a wedding anniversary',
        92295 => 'Guest has a sensory impairment (hearing or vision loss)',
        92265 => 'Guest requires space for a CPAP machine',
        92225 => 'Hotel Membership Number',
        1717  => 'Mark the guest as a VIP',
        1718  => 'Mark the guests as a honeymoon couple',
        1719  => 'Request a baby cot',
        1711  => 'Request a non-smoking room',
        92285 => 'Request a room close to elevators or amenities',
        1713  => 'Request a room on a higher floor',
        1714  => 'Request a room on a lower floor',
        1712  => 'Request a smoking room',
        92305 => 'Request a wheelchair-accessible room with a separate shower',
        92215 => 'Request adjacent rooms',
        1710  => 'Request an interconnecting room',
        92325 => 'Request double bedding',
        1715  => 'Request early check-in',
        93975 => 'Request late check-in',
        1716  => 'Request late check-out',
        92275 => 'Request refrigeration for insulin (subject to availability)',
        92315 => 'Request twin bedding',
    ],
];
