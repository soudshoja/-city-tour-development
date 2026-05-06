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
    'prebook_ttl_minutes' => 30,
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
        'name'          => env('AKEED_DOTWAI_BRAND_NAME', 'Akeed'),
        'support_phone' => env('AKEED_DOTWAI_SUPPORT_PHONE', null),
        'support_email' => env('AKEED_DOTWAI_SUPPORT_EMAIL', null),
    ],

];
