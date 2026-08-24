<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resayil WhatsApp CRM — Embed, Drawer & Account Provisioning (Module 5)
    |--------------------------------------------------------------------------
    |
    | This config powers the in-app Resayil drawer + full-page view, and the
    | reseller-side account provisioning that backs "no second password".
    | Pattern ported from the aircon project's config/resayil.php.
    |
    | IMPORTANT — this is a SEPARATE config surface from:
    |   - config('services.resayil.*')   (base_url/version/api_token)
    |   - config('services.whatsapp.token')
    | which App\Http\Controllers\ResayilController already uses to send
    | outbound WhatsApp MESSAGES via the Resayil messaging API. That code and
    | its config are untouched by this file. This file is for:
    |   1. The EMBED iframe (drawer + full-page view), and
    |   2. The RESELLER (admin) API — api.resayil.io/v1/resellers — used to
    |      provision a company's Resayil "customer" account and, once wired,
    |      per-user team-member accounts. A different Resayil API surface
    |      with its own token, documented in the resayil-admin skill.
    |
    | SECURITY (ported verbatim from aircon's rules — do not weaken):
    |  - All values come from server env/config ONLY.
    |  - The reseller API token is injected server-side by ResayilClient and
    |    is NEVER sent to the front-end.
    |  - embed_url is a fixed configured URL, NEVER a user-supplied parameter
    |    (SSRF guard) — App\Http\Middleware\ResayilFrameHeaders derives the
    |    allowed CSP frame-src origin from this value, not from any request
    |    input.
    |
    */

    // Reseller (admin) API — used server-side only, to provision a company's
    // Resayil "customer" account (POST /v1/resellers/customers). See the
    // resayil-admin skill for the full contract.
    'reseller_base_url' => env('RESAYIL_RESELLER_BASE_URL', 'https://api.resayil.io/v1/resellers'),
    'reseller_token' => env('RESAYIL_RESELLER_TOKEN'),

    // Account-level (non-reseller) API — needed for POST /devices/{id}/team
    // (per-user team-member creation). Each company has its OWN token here
    // (stored per company on resayil_accounts.resayil_account_token), so
    // there is no single global token config for this surface — only the
    // base URL is global.
    'account_base_url' => env('RESAYIL_ACCOUNT_BASE_URL', 'https://api.resayil.io/v1'),

    'timeout' => (int) env('RESAYIL_TIMEOUT', 15),
    'retries' => (int) env('RESAYIL_RETRIES', 3),

    // Kuwait defaults for this deployment (aircon's equivalent defaults to
    // Malaysia/+60 — do NOT reuse those values here).
    'default_country_code' => (string) env('RESAYIL_DEFAULT_COUNTRY_CODE', '965'),
    'default_country' => (string) env('RESAYIL_DEFAULT_COUNTRY', 'KW'),

    // When true, provisioning short-circuits and logs instead of hitting the
    // reseller API — handy for local/dev so no real Resayil customer is
    // created while testing.
    'test_mode' => (bool) env('RESAYIL_TEST_MODE', false),

    // The iframe src for both the drawer and the full-page view. Server
    // configured ONLY. On dev this is currently unset — the UI must render
    // the graceful "not configured" state, never a broken iframe.
    'embed_url' => env('RESAYIL_EMBED_URL'),

    // Inbound-webhook signing, ported from aircon's shape for parity. Not
    // wired to a route by this module — kept so the shape is ready if/when
    // a Resayil -> TravelERP webhook is added later.
    'webhook_secret' => env('RESAYIL_WEBHOOK_SECRET', ''),
    'webhook_signature_header' => env('RESAYIL_WEBHOOK_SIGNATURE_HEADER', 'X-Resayil-Signature'),
    'webhook_timestamp_header' => env('RESAYIL_WEBHOOK_TIMESTAMP_HEADER', 'X-Resayil-Timestamp'),
    'webhook_tolerance_seconds' => (int) env('RESAYIL_WEBHOOK_TOLERANCE_SECONDS', 0),

    /*
    |--------------------------------------------------------------------------
    | Account provisioning model (owner spec, 2026-08-24)
    |--------------------------------------------------------------------------
    |
    | The first TravelERP user who needs Resayil access for a company becomes
    | that company's Resayil "admin" account (a reseller customer, created via
    | POST /v1/resellers/customers with a server-generated secret — never the
    | user's TravelERP password). Every subsequent user of that company is
    | auto-provisioned up to max_auto_users. Beyond the cap, Resayil bills
    | extra per seat, so auto-creation stops and the UI shows a "contact
    | support" state instead of silently over-billing the company.
    |
    | A config value (not a hardcoded 9) so it can change without a deploy.
    |
    */
    'max_auto_users' => (int) env('RESAYIL_MAX_AUTO_USERS', 9),

];
