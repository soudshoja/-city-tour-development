# DOTW v1.0 B2B — Complete Integration Guide

## 1. Overview

The DOTW module is a standalone Laravel package that integrates the DOTWconnect (DCML) Version 4 XML hotel booking API into multi-tenant Laravel applications. It supports per-company DOTW credentials stored encrypted in the database, a full GraphQL API for B2B consumers, and a WhatsApp-first (Resayil) workflow. The module is architecturally independent — it has zero coupling to the invoice, task, or payment systems and can be deployed to a new Laravel installation with config changes and migrations only.

**Supported operations (5 total):**
1. `getCities` — list cities by country code
2. `searchHotels` — search hotels by destination, dates, rooms
3. `getRoomRates` — browse full room rate details with cancellation rules
4. `blockRates` — lock a rate for 3 minutes (creates a prebook record)
5. `createPreBooking` — confirm booking with passenger details

**API:** DOTW V4 XML via DOTWconnect (DCML) gateway.

---

## 2. Module Architecture

All files that constitute the DOTW module (copy these to a new installation):

### Config
- `config/dotw.php` — all runtime configuration via env()

### Service Layer
- `app/Services/DotwService.php` — DOTW V4 XML API client, business logic, markup, circuit breaker
- `app/Services/DotwAuditService.php` — audit log wrapper (fail-silent, never breaks operations)
- `app/Services/DotwCacheService.php` — hotel search result caching (2.5 min TTL)

### GraphQL Resolvers
- `app/GraphQL/Queries/DotwGetCities.php` — getCities query
- `app/GraphQL/Queries/DotwSearchHotels.php` — searchHotels query (with circuit breaker + cache)
- `app/GraphQL/Queries/DotwGetRoomRates.php` — getRoomRates query (never cached — tokens expire in 3 min)
- `app/GraphQL/Mutations/DotwBlockRates.php` — blockRates mutation (rate locking + prebook creation)
- `app/GraphQL/Mutations/DotwCreatePreBooking.php` — createPreBooking mutation (confirmation with passengers)

### GraphQL Middleware
- `app/GraphQL/Middleware/DotwTraceMiddleware.php` — injects X-Trace-ID and X-Request-Time-Ms response headers
- `app/GraphQL/Middleware/ResayilContextMiddleware.php` — reads X-Resayil-Message-ID, X-Resayil-Quote-ID, X-Company-ID from request

### HTTP Middleware
- `app/Http/Middleware/DotwAuditAccess.php` — restricts audit log admin UI to Role::ADMIN and Role::COMPANY

### Livewire Admin
- `app/Http/Livewire/Admin/DotwAuditLogIndex.php` — audit log viewer (super admin: all companies; company admin: own company)

### Models
- `app/Models/CompanyDotwCredential.php` — per-company DOTW credentials (encrypted at rest)
- `app/Models/DotwPrebook.php` — rate lock records with 3-minute expiry
- `app/Models/DotwRoom.php` — room occupancy details within a prebook
- `app/Models/DotwBooking.php` — confirmed booking records
- `app/Models/DotwAuditLog.php` — append-only audit trail (all operations)

### Migrations (6 files)
- `database/migrations/2026_02_21_033317_create_dotw_prebooks_table.php`
- `database/migrations/2026_02_21_033318_create_dotw_rooms_table.php`
- `database/migrations/2026_02_21_100001_create_company_dotw_credentials_table.php`
- `database/migrations/2026_02_21_100001_create_dotw_audit_logs_table.php`
- `database/migrations/2026_02_21_155718_add_company_id_resayil_to_dotw_prebooks_table.php`
- `database/migrations/2026_02_21_165035_create_dotw_bookings_table.php`

### GraphQL Schema
- `graphql/dotw.graphql` — standalone DOTW schema (677+ lines), registered via `#import dotw.graphql`

### Documentation
- `DOTW_INTEGRATION.md` — this file

---

## 3. Environment Variables

Add these to your `.env` file:

| Variable | Default | Required | Description |
|----------|---------|----------|-------------|
| `DOTW_USERNAME` | (none) | Yes | DOTW API username |
| `DOTW_PASSWORD` | (none) | Yes | DOTW API password — plain text, MD5-hashed by service before transmission |
| `DOTW_COMPANY_CODE` | (none) | Yes | DOTW company code |
| `DOTW_DEV_MODE` | `true` | No | `true` = sandbox (`xmldev.dotwconnect.com`), `false` = production (`us.dotwconnect.com`) |
| `DOTW_TIMEOUT` | `25` | No | HTTP request timeout in seconds (DOTW SLA: 25s) |
| `DOTW_CONNECT_TIMEOUT` | `30` | No | TCP connection timeout in seconds |
| `DOTW_ALLOCATION_EXPIRY_MINUTES` | `3` | No | Rate block validity window — 3 minutes per DOTW specification |
| `DOTW_B2C_MARKUP` | `20` | No | Default B2C markup percentage applied to fares |
| `DOTW_CACHE_TTL` | `150` | No | Search result cache TTL in seconds (default 2.5 minutes) |
| `DOTW_CACHE_PREFIX` | `dotw_search` | No | Cache key prefix for hotel search results |

**Note:** `DOTW_USERNAME` / `DOTW_PASSWORD` / `DOTW_COMPANY_CODE` are the **legacy single-company path** (env-based credentials). In B2B multi-company mode, credentials are stored per-company in the `company_dotw_credentials` database table. Both paths are supported simultaneously — the `DotwService` constructor accepts `?int $companyId`; when passed it loads DB credentials, when `null` it falls back to env vars.

---

## 4. New Installation Guide

Follow these steps to deploy the DOTW module to a new Laravel 11 installation:

1. **Copy all files** listed in Section 2 to the target Laravel application.

2. **Add environment variables** from Section 3 to your `.env` file.

3. **Run migrations** to create all DOTW tables:
   ```bash
   php artisan migrate
   ```
   This creates: `company_dotw_credentials`, `dotw_audit_logs`, `dotw_prebooks`, `dotw_rooms`, `dotw_bookings`.

   **Prerequisite:** The `companies` table must exist before running DOTW migrations — `company_dotw_credentials` has a foreign key to it. This is the only external dependency.

4. **Register the GraphQL schema** — add to `graphql/schema.graphql` (line 1):
   ```
   #import dotw.graphql
   ```
   To deregister the entire DOTW module from GraphQL, remove this single line.

5. **Register GraphQL route middleware** — add to `config/lighthouse.php` under `route.middleware`:
   ```php
   \App\GraphQL\Middleware\ResayilContextMiddleware::class,
   \App\GraphQL\Middleware\DotwTraceMiddleware::class,
   ```

6. **Register HTTP middleware alias** — add to `bootstrap/app.php` inside `withMiddleware()`:
   ```php
   $middleware->alias([
       'dotw_audit_access' => \App\Http\Middleware\DotwAuditAccess::class,
   ]);
   ```

7. **Register admin route** — add to `routes/web.php`:
   ```php
   Route::middleware(['auth', 'dotw_audit_access'])
       ->prefix('admin/dotw')
       ->name('admin.dotw.')
       ->group(function () {
           Route::get('audit-logs', \App\Http\Livewire\Admin\DotwAuditLogIndex::class)
               ->name('audit-logs');
       });
   ```

8. **Configure DOTW logging** — add to `config/logging.php` channels array:
   ```php
   'dotw' => [
       'driver' => 'daily',
       'path' => storage_path('logs/dotw/dotw.log'),
       'level' => 'debug',
       'days' => 14,
   ],
   ```

9. **Clear caches:**
   ```bash
   php artisan optimize:clear
   ```

---

## 5. Per-Company Credential Setup (B2B Path)

In production, each company has its own DOTW credentials stored encrypted in the database.

**Storage:** The `company_dotw_credentials` table via `CompanyDotwCredential` model. Credentials are stored using Laravel's `Crypt::encrypt()` — never in plaintext.

**Upsert credentials for a company:**
```php
CompanyDotwCredential::updateOrCreate(
    ['company_id' => $companyId],
    [
        'dotw_username'     => 'their_username',
        'dotw_password'     => 'their_password',
        'dotw_company_code' => 'their_company_code',
        'markup_percent'    => 20,  // Optional, company-specific markup
    ]
);
```

**Service constructor:**
```php
// B2B path — loads DB credentials for company 5
$service = new DotwService(companyId: 5);

// Legacy path — uses env vars (backward compat)
$service = new DotwService();
```

Each company can have a custom `markup_percent` (default: 20%). The markup is applied to all rates returned for that company.

---

## 6. B2B API Consumer Guide

This section is for external B2B partners consuming the DOTW GraphQL API.

### 6.1 Request Headers

All DOTW GraphQL operations require these headers:

| Header | Type | Required | Description |
|--------|------|----------|-------------|
| `X-Company-ID` | integer | Yes | Identifies the company — used to resolve per-company DOTW credentials. Omitting or providing an invalid value results in `CREDENTIALS_NOT_CONFIGURED`. |
| `X-Resayil-Message-ID` | string | Recommended | WhatsApp/Resayil message ID for full traceability (logged to audit trail) |
| `X-Resayil-Quote-ID` | string | Optional | Quoted message context (for WhatsApp reply chains) |

### 6.2 Response Envelope

All 5 DOTW operations return this standard envelope:

```json
{
  "data": {
    "operationName": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "550e8400-e29b-41d4-a716-446655440000",
        "timestamp": "2026-02-21T10:00:00Z",
        "company_id": 1,
        "request_id": "550e8400-e29b-41d4-a716-446655440001"
      },
      "cached": false,
      "data": { }
    }
  }
}
```

`cached: true` indicates the response was served from the search cache (no DOTW API call made).

### 6.3 Error Response Structure

```json
{
  "success": false,
  "error": {
    "error_code": "CREDENTIALS_NOT_CONFIGURED",
    "error_message": "DOTW credentials not configured for this company",
    "error_details": "Technical detail for debugging",
    "action": "RECONFIGURE_CREDENTIALS"
  }
}
```

**Error codes:**

| Code | Meaning | Action |
|------|---------|--------|
| `CREDENTIALS_NOT_CONFIGURED` | No DOTW credentials found for company | `RECONFIGURE_CREDENTIALS` |
| `CREDENTIALS_INVALID` | Credentials rejected by DOTW API | `RECONFIGURE_CREDENTIALS` |
| `ALLOCATION_EXPIRED` | 3-minute rate block window has passed | `RETRY_SEARCH` |
| `RATE_UNAVAILABLE` | Rate no longer available from supplier | `RETRY_SEARCH` |
| `HOTEL_SOLD_OUT` | No availability for requested dates | `TRY_DIFFERENT_DATES` |
| `PASSENGER_VALIDATION_FAILED` | Passenger name validation error | `FIX_PASSENGER_DATA` |
| `API_TIMEOUT` | DOTW API did not respond in time | `RETRY_IN_30_SECONDS` |
| `API_ERROR` | DOTW API returned an error | `CONTACT_SUPPORT` |
| `CIRCUIT_BREAKER_OPEN` | Too many recent DOTW failures (searchHotels only) | `RETRY_IN_30_SECONDS` |
| `VALIDATION_ERROR` | Input validation failed | `FIX_INPUT` |
| `INTERNAL_ERROR` | Unexpected server error | `CONTACT_SUPPORT` |

### 6.4 Example Queries

#### getCities
```graphql
query GetCities {
  getCities(country_code: "AE") {
    success
    meta { trace_id }
    data {
      cities { code name }
      total_count
    }
  }
}
```

#### searchHotels
```graphql
query SearchHotels {
  searchHotels(input: {
    destination: "DXB"
    checkin: "2026-03-15"
    checkout: "2026-03-18"
    rooms: [{ adults: 2, children: 0, child_ages: [] }]
    currency: "USD"
  }) {
    success
    cached
    meta { trace_id timestamp }
    data {
      hotels {
        hotel_code
        cheapest_rates {
          room_type_code
          meal_plan_code
          final_fare
          currency
          is_refundable
        }
      }
    }
  }
}
```

#### getRoomRates
```graphql
query GetRoomRates {
  getRoomRates(input: {
    hotel_code: "15536"
    checkin: "2026-03-15"
    checkout: "2026-03-18"
    rooms: [{ adults: 2, children: 0, child_ages: [] }]
    currency: "USD"
  }) {
    success
    meta { trace_id }
    data {
      rooms {
        room_type_code
        rates {
          allocation_details
          rate_basis
          total_fare
          total_tax
          final_fare
          currency
          is_refundable
          cancellation_rules {
            from_date
            penalty_amount
          }
        }
      }
    }
  }
}
```

**Note:** `getRoomRates` is never cached — `allocationDetails` tokens expire in 3 minutes and rates change continuously. Always call this immediately before `blockRates`.

#### blockRates
```graphql
mutation BlockRates {
  blockRates(input: {
    hotel_code: "15536"
    checkin: "2026-03-15"
    checkout: "2026-03-18"
    rooms: [{ adults: 2, children: 0, child_ages: [] }]
    room_type_code: "DLX"
    rate_basis: "1332"
    allocation_details: "opaque-token-from-getRoomRates"
  }) {
    success
    meta { trace_id }
    data {
      prebook_key
      countdown_timer_seconds
      expires_at
      hotel_details {
        hotel_code
        hotel_name
        total_fare
        currency
      }
    }
  }
}
```

**Note:** Returns a `prebook_key` UUID valid for 3 minutes. Pass this to `createPreBooking`. Only one active prebook per `(company_id, resayil_message_id)` pair is allowed — creating a new one automatically expires the previous one.

#### createPreBooking
```graphql
mutation CreatePreBooking {
  createPreBooking(input: {
    prebook_key: "550e8400-e29b-41d4-a716-446655440000"
    checkin: "2026-03-15"
    checkout: "2026-03-18"
    passengers: [{
      salutation: "Mr"
      first_name: "John"
      last_name: "Doe"
      nationality: "US"
      residence_country: "AE"
      email: "john.doe@example.com"
    }]
  }) {
    success
    meta { trace_id }
    data {
      confirmation_code
      confirmation_number
      booking_status
      itinerary_details
    }
  }
}
```

### 6.5 Booking Flow Sequence

```
1. getCities(country_code)
       ↓ city codes
2. searchHotels(destination, dates, rooms)
       ↓ hotel_code + cheapest_rates (cached 2.5 min)
3. getRoomRates(hotel_code, dates, rooms)
       ↓ allocation_details + full rates (NOT cached — call immediately before blockRates)
4. blockRates(hotel_code, dates, rooms, room_type_code, rate_basis, allocation_details)
       ↓ prebook_key (3-minute window starts)
5. createPreBooking(prebook_key, checkin, checkout, passengers)
       ↓ confirmation_code
```

**Time constraint:** Steps 3→4→5 must complete within 3 minutes. If the window expires, restart from Step 3.

---

## 7. Audit Logs

Every DOTW operation is logged to the `dotw_audit_logs` table regardless of success or failure. Audit logging is fail-silent — a logging failure never breaks a DOTW operation.

**Admin UI:** `/admin/dotw/audit-logs`
- Requires `Role::ADMIN` (super admin) or `Role::COMPANY` (company admin)
- Super admin: sees all 8 columns, all companies
- Company admin: sees columns 3–8 only, scoped to own company

**Log fields:** `id`, `company_id`, `resayil_message_id`, `operation_type`, `request_payload`, `response_payload`, `created_at`

**Security:** Credentials (`username`, `password`, `company_code`) are never logged — sanitized before storage in `DotwService` before being passed to audit.

---

## 8. Circuit Breaker (searchHotels only)

`searchHotels` has a circuit breaker that protects against cascading DOTW API failures:

| Parameter | Value |
|-----------|-------|
| Failure threshold | 5 failures |
| Window | 60 seconds |
| Open TTL | 30 seconds |

**Circuit open + cache hit:** Returns cached hotels with `cached: true` (seamless to consumer).
**Circuit open + no cache:** Returns `CIRCUIT_BREAKER_OPEN` error with `action: RETRY_IN_30_SECONDS`.

The circuit breaker applies **only** to `searchHotels` — `getRoomRates`, `blockRates`, `getCities`, and `createPreBooking` are unaffected.

---

## 9. Known Issues / Limitations

| Issue | Description | Resolution |
|-------|-------------|------------|
| `dotw_rooms.dotw_preboot_id` column name typo | Should be `dotw_prebook_id` — pre-existing migration artefact from before Phase 1. Renaming requires a breaking migration. | Tracked for DOTW v2 — do not change in v1 |
| Hotel metadata missing from searchHotels | `hotel_name`, `city`, `rating`, `location`, `image_url` not returned by DOTW `searchhotels` command — only available via `getRoomRates` | Deferred to DOTW v2 (SEARCH-06) |
| N8N node templates | N8N GraphQL node templates for automated booking workflows are planned for DOTW v2 B2C | Deferred to DOTW v2 B2C milestone |

---

## 10. Modular Architecture

The DOTW module satisfies these architecture requirements:

| Requirement | Status | Verification |
|-------------|--------|-------------|
| MOD-01: No invoice/task/payment imports | Verified | `grep -rn "use.*Invoice\|use.*Task\b\|use.*Payment" app/Services/Dotw*.php app/GraphQL/*/Dotw*.php app/Models/Dotw*.php app/Models/CompanyDotwCredential.php` — zero results |
| MOD-02: config/dotw.php uses env() for all runtime values | Verified | Endpoint URLs and log channel name are static constants; all user-configurable values use `env()` |
| MOD-03: All migrations use `Schema::dropIfExists()` | Verified | All 5 create migrations use `dropIfExists`; addColumn migration's `down()` uses `hasColumn` guard |
| MOD-04: `graphql/dotw.graphql` independently importable | Verified | Registered via `#import dotw.graphql` in `graphql/schema.graphql` line 1; uses `extend type Query / Mutation` (not bare `type`) |
| MOD-05: Models use only DOTW-internal or `companies` FKs | Verified | Only FKs: `dotw_rooms → dotw_prebooks` (DOTW-internal), `company_dotw_credentials → companies` (core multi-tenant, not invoice/task) |
| MOD-06: No dependencies on invoice/task system | Verified | Same as MOD-01 — zero cross-system imports |

**Deregistering the module:** Remove `#import dotw.graphql` from `graphql/schema.graphql`. All DOTW GraphQL operations disappear. Tables remain (run `php artisan migrate:rollback` to remove if needed).

---

## 11. Support & References

- **DOTW V4 API Spec:** `DOTWV4/SKILL.md`
- **Config:** `config/dotw.php`
- **Main Service:** `app/Services/DotwService.php` (1,500+ lines)
- **GraphQL Schema:** `graphql/dotw.graphql` (677+ lines)
- **Logs:** `storage/logs/dotw/dotw.log`
- **Planning Docs:** `.planning/phases/` (Phases 1–8)
- **Live Site:** https://development.citycommerce.group
