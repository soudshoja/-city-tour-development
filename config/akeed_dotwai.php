<?php

declare(strict_types=1);

/**
 * AkeedDotwAI Module Configuration
 *
 * Controls the B2C hotel booking module (AkeedDotwAI). This module is disabled
 * by default and must be explicitly enabled via AKEED_DOTWAI_ENABLED=true.
 * The existing app/Modules/DotwAI/ (B2B variant) is independent and unaffected.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Module Enable Flag
    |--------------------------------------------------------------------------
    |
    | When false, the ServiceProvider registers nothing: no routes, no
    | migrations loaded, no middleware alias. Zero side effects.
    |
    */
    'enabled' => env('AKEED_DOTWAI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Tenant Identity
    |--------------------------------------------------------------------------
    |
    | Single-tenant: this module is pinned to a specific company. No
    | BelongsToCompany global scope on AkeedDotwAI models.
    |
    */
    'company_id' => env('AKEED_DOTWAI_COMPANY_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | System Agent
    |--------------------------------------------------------------------------
    |
    | Optional agent_id used as the "system" actor for programmatic invoice
    | creation. Null is valid — storeProgrammatic accepts nullable agent.
    |
    */
    'system_agent_id' => env('AKEED_DOTWAI_SYSTEM_AGENT_ID', null),

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | b2c_markup: Fractional markup applied on top of DOTW net rate.
    |   0.20 = 20% markup. Stored as decimal for arithmetic clarity.
    |
    */
    'b2c_markup' => env('AKEED_DOTWAI_B2C_MARKUP', 0.20),

    /*
    |--------------------------------------------------------------------------
    | Session / Prebook TTLs
    |--------------------------------------------------------------------------
    |
    | session_ttl_minutes: How long the customer WhatsApp conversation session
    |   remains active in Redis (idle timeout).
    | prebook_ttl_minutes: How long a prebook rate-lock is valid before the
    |   customer must re-search. Hard-coded — not overridable per-deploy.
    | search_cache_ttl_seconds: How long search results are cached per phone
    |   number. 600 seconds = 10 minutes.
    |
    */
    'session_ttl_minutes' => env('AKEED_DOTWAI_SESSION_TTL', 60),
    'prebook_ttl_minutes' => 2, // 2 min — DOTW non-FIT inventory has no hold guarantee, FIT max 5 min, V4 default 3 min
    'search_cache_ttl_seconds' => 600,

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | Config-driven branding so the module can be white-labeled for resale
    | without code changes.
    |
    */
    'brand' => [
        'name' => env('AKEED_DOTWAI_BRAND_NAME', 'Akeed'),
        'support_phone' => env('AKEED_DOTWAI_SUPPORT_PHONE', null),
        'support_email' => env('AKEED_DOTWAI_SUPPORT_EMAIL', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone Normalization
    |--------------------------------------------------------------------------
    |
    | default_country_code: ITU-T country code prepended to 8-digit local
    |   numbers during phone normalization in AttachDotwContext. Kuwait = 965.
    |   Override via AKEED_DOTWAI_DEFAULT_COUNTRY_CODE for other markets.
    |
    */
    'default_country_code' => env('AKEED_DOTWAI_DEFAULT_COUNTRY_CODE', '965'),

    /*
    |--------------------------------------------------------------------------
    | DOTW Currency
    |--------------------------------------------------------------------------
    |
    | DOTW internal currency code used for searchhotels / getrooms requests.
    | This must match what your DOTW account is provisioned for. The B2B
    | DotwAI module reads DOTWAI_DEFAULT_CURRENCY (same code, separate env).
    | Common DOTW codes: 769 = USD, 520 = KWD. Default 520 fails for accounts
    | not provisioned for KWD — set AKEED_DOTWAI_DEFAULT_CURRENCY=769
    | (or your account's code) on every deploy.
    |
    */
    'default_currency' => env('AKEED_DOTWAI_DEFAULT_CURRENCY', '520'),

    /*
    |--------------------------------------------------------------------------
    | Hotel Sync — Priority Cities
    |--------------------------------------------------------------------------
    |
    | City codes (DOTW internal numeric codes) used by the
    | akeed-dotwai:sync-hotels --all command for the initial hotel pre-load
    | and the weekly scheduled top-up.
    |
    | These codes cover the top GCC + high-demand outbound destinations that
    | account for ~80% of Akeed customer search traffic. Additional city codes
    | can be discovered after running dotwai:sync-static (which populates
    | dotwai_cities with all 25,868 DOTW cities).
    |
    | Known codes (verified against live DOTW sandbox 2026-05-06):
    |   364    = Dubai
    |   410    = Abu Dhabi
    |   353    = Kuwait City
    |   24     = Doha (Qatar)
    |   194    = Riyadh
    |   844    = Beirut
    |   134    = Jeddah
    |   93846  = Manama (Bahrain)
    |   264    = Muscat
    |   454    = Cairo
    |   14214  = Istanbul
    |   22594  = Bangkok
    |   22394  = Singapore
    |   271565 = Maldives (Male)
    |
    */
    'hotel_sync_priority_cities' => [
        364, 410, 353, 24, 194, 844, 134, 93846, 264, 454,
        14214, 22594, 22394, 271565,
    ],

    /*
    |--------------------------------------------------------------------------
    | Hotel Sync — Rate Limiting Delay
    |--------------------------------------------------------------------------
    |
    | Milliseconds to sleep between city requests during hotel sync.
    | Default 200 ms keeps a 14-city priority run under 3 seconds while
    | respecting DOTW's undocumented rate limit (best-practices guidance
    | recommends pacing batch requests).
    |
    */
    'hotel_sync_delay_ms' => env('AKEED_DOTWAI_HOTEL_SYNC_DELAY_MS', 200),

    /*
    |--------------------------------------------------------------------------
    | Catalog Sync — Tuning
    |--------------------------------------------------------------------------
    |
    | Controls the weekly akeed-dotwai:sync-catalogs run that snapshots all 11
    | DOTW reference taxonomies into the dotw_catalogs table.
    |
    | delay_ms: Pacing between DOTW calls (millis). 11 commands × 200ms = ~2.2s
    |   plus roundtrips. Increase if DOTW rate-limits catalog commands.
    |
    | skip_types: Comma-separated list of catalog types to skip from the default
    |   'all' run (e.g. AKEED_DOTWAI_CATALOG_SYNC_SKIP=room_type,special).
    |   Useful for debugging or working around known-broken commands.
    |
    | company_id: Override the company_id used for DOTW credentials during sync.
    |   Defaults to akeed_dotwai.company_id (global default). Note: dotw_catalogs
    |   is a SINGLE GLOBAL TABLE — UNIQUE(type, code) disregards company_id.
    |   This env override changes the DOTW CREDENTIALS used for the sync, NOT
    |   the storage scope. Multi-tenant catalog sync is NOT supported in Phase 34.
    |
    */
    'catalog_sync' => [
        // Pacing between DOTW calls (millis). 11 commands × 200ms = ~2.2s + roundtrips.
        'delay_ms' => env('AKEED_DOTWAI_CATALOG_SYNC_DELAY_MS', 200),
        // Skip these types from the default 'all' run (debugging / known-broken commands).
        'skip_types' => array_filter(explode(',', env('AKEED_DOTWAI_CATALOG_SYNC_SKIP', ''))),
        // Optional override of the company_id used for sync (defaults to akeed_dotwai.company_id).
        // Single global table — UNIQUE(type, code) disregards company_id. This env override
        // changes the DOTW credentials used for the sync, NOT the storage scope.
        // Multi-tenant sync NOT supported in Phase 34.
        'company_id' => env('AKEED_DOTWAI_CATALOG_SYNC_COMPANY_ID', null),
    ],

];
