# DOTW v1.0 B2B: Complete Reference Guide

**Version:** 1.0 (B2B Milestone - Complete)
**Last Updated:** February 2026
**Status:** Production Ready
**Maintainer:** Development Team

---

## Table of Contents

- [Executive Summary](#executive-summary)
- [Quick Start](#quick-start-5-minute-guide)
- [Architecture Overview](#architecture-overview)
- [Authentication & Authorization](#authentication--authorization)
- [API Reference](#api-reference)
  - [Queries](#queries)
  - [Mutations](#mutations)
  - [Data Types](#data-types)
- [Service Layer](#service-layer)
  - [DotwService](#dotwservice)
  - [DotwCacheService](#dotw-cache-service)
  - [DotwCircuitBreakerService](#dotw-circuit-breaker-service)
  - [DotwAuditService](#dotw-audit-service)
- [Admin UI & Configuration](#admin-ui--configuration)
- [n8n Integration](#n8n-integration)
- [Database Schema](#database-schema)
- [Booking Workflow](#booking-workflow)
- [Error Handling](#error-handling)
- [Security & Isolation](#security--isolation)
- [Troubleshooting Index](#troubleshooting-index)
- [Document Map](#document-map)

---

## Executive Summary

DOTW v1.0 B2B is a production-ready hotel booking integration module for the Soud Laravel platform. It enables travel agencies to search, rate, block, and confirm hotel reservations through the DOTWconnect supplier API via a GraphQL interface.

**Key Capabilities:**
- **City Discovery:** Browse available destinations per country
- **Hotel Search:** Query hotels with rates for specified dates and room configurations (cached 2.5 minutes)
- **Rate Details:** Retrieve full rate information including meal plans, cancellation policies, and opaque allocation tokens
- **Rate Blocking:** Lock a selected rate for 3 minutes to hold pricing during booking conversations
- **Booking Confirmation:** Confirm a locked rate into an actual DOTW booking with passenger details

**Architecture Highlights:**
- Multi-tenant B2B system with per-company credential isolation
- GraphQL API powered by Lighthouse (Sanctum Bearer token authentication)
- Service layer with caching, circuit breaker protection, and audit logging
- Deterministic search result cache (order-independent room configurations)
- 25-second API timeout with graceful degradation
- Append-only audit trail linked to WhatsApp message IDs

**Core Operations:**
1. **getCities** → Discover cities per country
2. **searchHotels** → Find hotels with lowest rates per meal plan
3. **getRoomRates** → Get all room types and meal plans with details
4. **blockRates** → Lock a rate for 3 minutes (creates `dotw_prebooks` record)
5. **createPreBooking** → Confirm booking with passenger details (creates `dotw_bookings` record)

---

## Quick Start: 5-Minute Guide

### Step 1: Configure Company Credentials

Navigate to `/settings` → **DOTW / Hotel API** → **Credentials** tab:

```
DOTW Username:     [your_dotw_username]
DOTW Password:     [your_dotw_password]
DOTW Company Code: [COMP_CODE_FROM_DOTW]
Markup %:          20  (or your B2C markup)
```

Click "Save Credentials". Credentials are encrypted at rest using Laravel's `APP_KEY`.

**For Super Admins:** Use the REST API instead:
```bash
POST /api/admin/companies/{companyId}/dotw-credentials
Content-Type: application/json

{
  "dotw_username": "your_username",
  "dotw_password": "your_password",
  "dotw_company_code": "COMP_CODE",
  "markup_percent": 20
}
```

### Step 2: Generate API Token for n8n

Navigate to `/admin/dotw` → **API Tokens** tab (Super Admin only):

1. Click **"Generate"** for your company
2. Copy the plaintext token (shown once)
3. Store in n8n as environment variable: `DOTW_TOKEN`

Token format: `{UUID}|{HASH}` (65 characters, Sanctum-based)

### Step 3: Make Your First API Call

**Using cURL:**
```bash
curl -X POST https://development.citycommerce.group/graphql \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "query GetCities($countryCode: String!) { getCities(country_code: $countryCode) { success error { error_code error_message action } meta { trace_id timestamp company_id } data { cities { code name } total_count } } }",
    "variables": {
      "countryCode": "AE"
    }
  }'
```

**Expected Response (200 OK):**
```json
{
  "data": {
    "getCities": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "a1b2c3d4-e5f6-4789-abcd-ef0123456789",
        "timestamp": "2026-02-21T14:30:00Z",
        "company_id": 5
      },
      "data": {
        "cities": [
          { "code": "DXB", "name": "Dubai" },
          { "code": "AUH", "name": "Abu Dhabi" }
        ],
        "total_count": 2
      }
    }
  }
}
```

### Step 4: Complete Booking Workflow

1. **Search:** `searchHotels(destination, dates, rooms)` → Get hotel results (cached)
2. **Browse Rates:** `getRoomRates(hotel_code, dates, rooms)` → See all options
3. **Block Rate:** `blockRates(hotel_code, room_type, allocation_details)` → Lock for 3 min
4. **Confirm:** `createPreBooking(prebook_key, passengers)` → Finalize booking

Store `trace_id` from each response for debugging and support tickets.

---

## Architecture Overview

### System Components

```
External Systems
  ├── WhatsApp / n8n (Workflow Client)
  ├── DOTW SOAP API (Hotel Supplier, 25s timeout)
  └── Resayil API (Message Context)

Soud Laravel Application
  ├── GraphQL API Layer (Lighthouse)
  │   ├── getCities Query
  │   ├── searchHotels Query
  │   ├── getRoomRates Query
  │   ├── blockRates Mutation
  │   └── createPreBooking Mutation
  │
  ├── DotwService (Business Logic)
  │   ├── Credential Resolution
  │   ├── SOAP Communication (25s timeout)
  │   ├── Markup Calculation
  │   └── Rate Locking & Prebook Tracking
  │
  ├── Supporting Services
  │   ├── DotwCacheService (2.5 min search cache)
  │   ├── DotwCircuitBreakerService (resilience)
  │   ├── DotwAuditService (logging)
  │   └── DotwTimeoutException (exception handling)
  │
  └── Database Models
      ├── CompanyDotwCredential (encrypted creds per company)
      ├── DotwPrebook (rate locks)
      ├── DotwRoom (occupancy details)
      ├── DotwBooking (confirmations)
      └── DotwAuditLog (operation trail)

Database
  └── MySQL laravel_testing
```

### Component Relationships

| Component | Purpose | Dependencies |
|-----------|---------|--------------|
| **GraphQL API** | Query/mutation entry point | Lighthouse, DotwService, Authentication |
| **DotwService** | Core business logic | CompanyDotwCredential, cache, audit, circuit breaker |
| **DotwCacheService** | Deterministic search result caching | Laravel cache backend |
| **DotwAuditService** | Sanitized request/response logging | DotwAuditLog model |
| **DotwCircuitBreakerService** | Graceful degradation under failures | Redis or cache backend |
| **CompanyDotwCredential** | Encrypted API credentials | Company model (FK) |
| **DotwPrebook** | Rate allocation for 3 minutes | DotwRoom (HasMany) |
| **DotwRoom** | Occupancy details within prebook | DotwPrebook (BelongsTo) |
| **DotwBooking** | Immutable confirmation record | None (standalone) |
| **DotwAuditLog** | Append-only operation trail | None (standalone) |

---

## Authentication & Authorization

### Sanctum Bearer Token Authentication

All DOTW GraphQL endpoints require a **Sanctum Bearer token** in the `Authorization` header:

```http
POST /graphql
Authorization: Bearer {personal_access_token}
Content-Type: application/json
X-Trace-ID: {optional UUID for request correlation}
```

**Token Generation:**
1. Navigate to `/admin/dotw` → **API Tokens** tab (Super Admin only)
2. Click **"Generate"** for a company
3. Copy the plaintext token (shown once)
4. Token is bound to the company context

**Token Storage:**
- Stored in `personal_access_tokens` table (hashed)
- Format: `{UUID}|{HASH}` (65 chars, Sanctum format)
- Lifetime: ~1 year (depends on Sanctum config)
- Revokable per token

### Company Context Resolution

The authenticated user's company is extracted from:
```php
$companyId = auth()->user()?->company?->id;
```

All operations are scoped to that company. Credentials are fetched from `company_dotw_credentials` table.

**Error if credentials missing:**
```graphql
{
  "success": false,
  "error": {
    "error_code": "CREDENTIALS_NOT_CONFIGURED",
    "error_message": "DOTW credentials not configured for this company",
    "action": "RECONFIGURE_CREDENTIALS"
  }
}
```

### Role-Based Access

| Role | Credentials Tab | Audit Logs | API Tokens |
|------|-----------------|-----------|-----------|
| Super Admin (ADMIN) | REST API only | All companies | Full access |
| Company Admin (COMPANY) | Web form | Own company only | Not accessible |
| Branch / Agent | None | None | None |

---

## API Reference

### Queries

#### getCities

List all cities served by DOTW in a given country.

**Request:**
```graphql
query GetCities($countryCode: String!) {
  getCities(country_code: $countryCode) {
    success
    error { error_code error_message action }
    meta { trace_id timestamp company_id }
    data {
      cities { code name }
      total_count
    }
  }
}
```

**Variables:**
```json
{ "countryCode": "AE" }
```

**Response (Success):**
```json
{
  "data": {
    "getCities": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "uuid-...",
        "timestamp": "2026-02-21T14:30:00Z",
        "company_id": 5
      },
      "data": {
        "cities": [
          { "code": "DXB", "name": "Dubai" },
          { "code": "AUH", "name": "Abu Dhabi" }
        ],
        "total_count": 2
      }
    }
  }
}
```

**Error Cases:**
| Scenario | Error Code | Action |
|----------|-----------|--------|
| Invalid country code | VALIDATION_ERROR | RETRY |
| Company not authenticated | CREDENTIALS_NOT_CONFIGURED | RECONFIGURE_CREDENTIALS |
| DOTW API timeout | API_TIMEOUT | RETRY |
| DOTW API error | API_ERROR | RETRY |

**Notes:**
- Not cached; returns fresh data each call
- Country code must be ISO 3166-1 alpha-2 (2 chars, uppercase)

---

#### searchHotels

Find available hotels by destination, dates, and room configuration.

**Request:**
```graphql
query SearchHotels($input: SearchHotelsInput!) {
  searchHotels(input: $input) {
    success
    error { error_code error_message action }
    meta { trace_id timestamp company_id }
    cached
    data {
      hotels {
        hotel_code
        rooms {
          adults
          children
          children_ages
          room_types {
            code
            name
            rate_basis_id
            currency_id
            non_refundable
            total
            markup { original_fare markup_percent markup_amount final_fare }
            total_taxes
            total_minimum_selling
          }
        }
      }
      total_count
    }
  }
}
```

**Input Parameters:**

| Field | Type | Required | Example |
|-------|------|----------|---------|
| `destination` | String | Yes | "DXB" |
| `checkin` | String | Yes | "2026-02-28" (YYYY-MM-DD) |
| `checkout` | String | Yes | "2026-03-02" (YYYY-MM-DD) |
| `rooms` | [SearchHotelRoomInput!]! | Yes | See below |
| `currency` | String | No | "KWD" |
| `filters` | SearchHotelsFiltersInput | No | See below |

**SearchHotelRoomInput:**
```json
{
  "adultsCode": 2,
  "children": [5, 8],
  "passengerNationality": "KW",
  "passengerCountryOfResidence": "KW"
}
```

**SearchHotelsFiltersInput (all optional):**
- `minRating` (Int): 1-5 star minimum
- `maxRating` (Int): 1-5 star maximum
- `minPrice` (Float): Minimum total price
- `maxPrice` (Float): Maximum total price
- `propertyType` (String): "hotel", "apartment", "resort"
- `mealPlanType` (String): "BB", "HB", "FB", "AI", "RO", "SC"
- `amenities` (String[]): ["pool", "wifi", "spa"]
- `cancellationPolicy` (String): "refundable", "non-refundable"

**Response (Cached):**
```json
{
  "data": {
    "searchHotels": {
      "success": true,
      "cached": true,
      "meta": { "trace_id": "uuid-...", "timestamp": "2026-02-21T14:31:00Z", "company_id": 5 },
      "data": {
        "hotels": [
          {
            "hotel_code": "HOTEL-001",
            "rooms": [
              {
                "adults": "2",
                "children": "1",
                "children_ages": "5",
                "room_types": [
                  {
                    "code": "DBL",
                    "name": "Double Room",
                    "rate_basis_id": "1332",
                    "currency_id": "KWD",
                    "non_refundable": false,
                    "total": 250.0,
                    "markup": {
                      "original_fare": 250.0,
                      "markup_percent": 15.0,
                      "markup_amount": 37.5,
                      "final_fare": 287.5
                    },
                    "total_taxes": 10.0,
                    "total_minimum_selling": 275.0
                  }
                ]
              }
            ]
          }
        ],
        "total_count": 1
      }
    }
  }
}
```

**Caching:**
- Results cached **2.5 minutes** per company
- Cache key: `dotw_search_{company_id}_{destination}_{checkin}_{checkout}_{rooms_hash}`
- Room hashes are order-independent (same result regardless of room order)
- `cached: true` flag indicates cache hit

**Circuit Breaker:**
- Applied to `searchHotels` only
- Threshold: 5 failures within 60 seconds
- Opens for 30 seconds (auto-recovery)
- Returns cached results if available when open; otherwise `CIRCUIT_BREAKER_OPEN` error

**Error Cases:**
| Scenario | Error Code | Action | Circuit Breaker |
|----------|-----------|--------|-----------------|
| Invalid dates | VALIDATION_ERROR | RETRY | No |
| Missing destination | VALIDATION_ERROR | RETRY | No |
| Credentials missing | CREDENTIALS_NOT_CONFIGURED | RECONFIGURE_CREDENTIALS | No |
| Invalid credentials | CREDENTIALS_INVALID | RECONFIGURE_CREDENTIALS | No |
| DOTW timeout | API_TIMEOUT | RETRY | Yes, counts |
| DOTW API error | API_ERROR | RETRY | Yes, counts |
| Circuit breaker open | CIRCUIT_BREAKER_OPEN | RETRY_IN_30_SECONDS | N/A |

---

#### getRoomRates

Retrieve all room types and meal plans for a specific hotel with full details.

**Request:**
```graphql
query GetRoomRates($input: GetRoomRatesInput!) {
  getRoomRates(input: $input) {
    success
    error { error_code error_message action }
    meta { trace_id timestamp company_id }
    cached
    data {
      hotel_code
      rooms {
        room_type_code
        room_name
        rate_details {
          rate_basis_id
          rate_basis_name
          is_refundable
          total_fare
          total_taxes
          total_price
          markup { original_fare markup_percent markup_amount final_fare }
          allocation_details
          cancellation_rules { from_date to_date charge cancel_charge }
          original_currency
          exchange_rate
          final_currency
        }
      }
      total_count
    }
  }
}
```

**Input Parameters:**
- `hotel_code` (String, required): From `searchHotels` response
- `checkin` (String, required): Must match `searchHotels`
- `checkout` (String, required): Must match `searchHotels`
- `rooms` (SearchHotelRoomInput[], required): Must match `searchHotels`
- `currency` (String, optional): Currency code

**Rate Basis IDs:**
- `1331`: Room Only
- `1332`: Bed & Breakfast
- `1333`: Half Board
- `1334`: Full Board
- `1335`: All Inclusive
- `1336`: Self Catering

**Critical Notes:**
- **Never cached** — rates change minute-to-minute; always fresh from DOTW
- `allocation_details` is an **opaque token** — pass verbatim to `blockRates`
- Room configuration must **exactly match** `searchHotels` for rate consistency
- Single room type can have multiple `rate_details` (one per meal plan)

---

### Mutations

#### blockRates

Lock a selected hotel rate for 3 minutes. Returns a `prebook_key` UUID and countdown timer.

**Request:**
```graphql
mutation BlockRates($input: BlockRatesInput!) {
  blockRates(input: $input) {
    success
    error { error_code error_message action }
    meta { trace_id timestamp company_id }
    cached
    data {
      prebook_key
      expires_at
      countdown_timer_seconds
      hotel_code
      hotel_name
      room_type
      rate_basis
      total_fare
      total_tax
      markup { original_fare markup_percent markup_amount final_fare }
      is_refundable
      cancellation_rules { from_date to_date charge cancel_charge }
    }
  }
}
```

**Input Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `hotel_code` | String | Yes | From `searchHotels` |
| `hotel_name` | String | No | Hotel name (DOTW doesn't provide) |
| `checkin` | String | Yes | Must match `getRoomRates` |
| `checkout` | String | Yes | Must match `getRoomRates` |
| `rooms` | SearchHotelRoomInput[] | Yes | Must match `getRoomRates` |
| `selected_room_type` | String | Yes | Room code from `getRoomRates` |
| `selected_rate_basis` | String | Yes | Rate basis ID from `getRoomRates` |
| `allocation_details` | String | Yes | **Opaque token from `getRoomRates`** — never modify |
| `currency` | String | No | Currency code |

**Response (Success):**
```json
{
  "data": {
    "blockRates": {
      "success": true,
      "meta": { "trace_id": "uuid-...", "timestamp": "2026-02-21T14:33:00Z", "company_id": 5 },
      "data": {
        "prebook_key": "uuid-format-string",
        "expires_at": "2026-02-21T14:36:00Z",
        "countdown_timer_seconds": 180,
        "hotel_code": "HOTEL-001",
        "hotel_name": "Burj Al Arab",
        "room_type": "DBL",
        "rate_basis": "1332",
        "total_fare": 287.5,
        "total_tax": 10.0,
        "markup": { ... },
        "is_refundable": true,
        "cancellation_rules": [ ... ]
      }
    }
  }
}
```

**Database Side Effects:**
- Creates 1 row in `dotw_prebooks` with `prebook_key`, `allocation_details`, rates, and `expired_at = now() + 3 min`
- Creates N rows in `dotw_rooms` (one per room)
- **BLOCK-08 Constraint:** Automatically expires any previous active prebook for the same company/WhatsApp user

**Error Cases:**
| Scenario | Error Code | Action |
|----------|-----------|--------|
| Missing allocation_details | VALIDATION_ERROR | RETRY |
| Token expired (< 60 sec remaining) | ALLOCATION_EXPIRED | RESEARCH |
| Rate no longer available | RATE_UNAVAILABLE | RESEARCH |
| Hotel sold out | HOTEL_SOLD_OUT | RESEARCH |
| Credentials missing | CREDENTIALS_NOT_CONFIGURED | RECONFIGURE_CREDENTIALS |
| DOTW timeout | API_TIMEOUT | RETRY |
| DOTW error | API_ERROR | RETRY |

**Important Notes:**
- Display countdown timer in UI; prompt user to complete booking within 3 minutes
- `allocation_details` is critical — pass raw token without modification or encoding
- Audit logging in two phases: Phase A (API call) logged internally; Phase B (prebook creation) logged via `DotwAuditService`

---

#### createPreBooking

Confirm a locked rate into an actual DOTW hotel booking. Validates passenger details and completes workflow.

**Request:**
```graphql
mutation CreatePreBooking($input: CreatePreBookingInput!) {
  createPreBooking(input: $input) {
    success
    error { error_code error_message action }
    meta { trace_id timestamp company_id }
    cached
    data {
      booking_confirmation_code
      booking_status
      itinerary_details {
        hotel_code
        hotel_name
        checkin
        checkout
        room_type
        rate_basis
        total_fare
        currency
        is_refundable
        lead_guest_name
        customer_reference
        confirmation_number
      }
      alternatives {
        hotel_code
        rooms { ... }
      }
    }
  }
}
```

**Input Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `prebook_key` | String | Yes | UUID from `blockRates` response |
| `checkin` | String | Yes | Must match `blockRates` |
| `checkout` | String | Yes | Must match `blockRates` |
| `passengers` | PassengerInput[] | Yes | Array of passenger details |
| `rooms` | SearchHotelRoomInput[] | Yes | Must match `blockRates` |
| `destination` | String | No | City code (enables alternative suggestions on failure) |

**PassengerInput:**
```json
{
  "salutation": 1,
  "firstName": "John",
  "lastName": "Doe",
  "nationality": "KW",
  "residenceCountry": "KW",
  "email": "john@example.com"
}
```

**Salutation Codes:**
- `1`: Mr
- `2`: Mrs
- `3`: Ms
- `4`: Dr
- `5`: Prof

**Response (Success):**
```json
{
  "data": {
    "createPreBooking": {
      "success": true,
      "meta": { "trace_id": "uuid-...", "timestamp": "2026-02-21T14:34:00Z", "company_id": 5 },
      "data": {
        "booking_confirmation_code": "DOT12345678",
        "booking_status": "confirmed",
        "itinerary_details": {
          "hotel_code": "HOTEL-001",
          "hotel_name": "Burj Al Arab",
          "checkin": "2026-02-28",
          "checkout": "2026-03-02",
          "room_type": "DBL",
          "rate_basis": "1332",
          "total_fare": 287.5,
          "currency": "KWD",
          "is_refundable": true,
          "lead_guest_name": "Mr John Doe",
          "customer_reference": "uuid-...",
          "confirmation_number": "CONF987654"
        },
        "alternatives": []
      }
    }
  }
}
```

**Response (Failure with Alternatives):**
```json
{
  "data": {
    "createPreBooking": {
      "success": false,
      "error": {
        "error_code": "RATE_UNAVAILABLE",
        "error_message": "Rate no longer available. Showing alternatives...",
        "action": "RESEARCH"
      },
      "data": {
        "alternatives": [
          { "hotel_code": "HOTEL-002", "rooms": [ ... ] }
        ]
      }
    }
  }
}
```

**Validation:**
- Prebook exists in `dotw_prebooks`
- Prebook not expired (now < `expired_at`)
- Passenger count matches total adults in room config
- All required fields present (salutation, firstName, lastName, nationality, residenceCountry, email)
- Email format valid

**Database Side Effects:**
- Creates 1 row in `dotw_bookings` with confirmation details
- Marks prebook as expired (`expired_at = now`)
- Immutable record (no future updates)

**Error Cases:**
| Scenario | Error Code | Action |
|----------|-----------|--------|
| Prebook not found | VALIDATION_ERROR | RESEARCH |
| Prebook expired | ALLOCATION_EXPIRED | RESEARCH |
| Passenger count mismatch | VALIDATION_ERROR | RETRY |
| Missing field | PASSENGER_VALIDATION_FAILED | RETRY |
| Invalid email | PASSENGER_VALIDATION_FAILED | RETRY |
| Rate unavailable | RATE_UNAVAILABLE | RESEARCH |
| Hotel sold out | HOTEL_SOLD_OUT | RESEARCH |
| Credentials missing | CREDENTIALS_NOT_CONFIGURED | RECONFIGURE_CREDENTIALS |
| DOTW timeout | API_TIMEOUT | RETRY |
| DOTW error | API_ERROR | RETRY |

---

### Data Types

#### DotwMeta

Present in every response; used for request correlation.

```graphql
type DotwMeta {
  trace_id: String!        # UUID v4, unique per request
  request_id: String!      # Same as trace_id (backwards compat)
  timestamp: String!       # ISO 8601 UTC (2026-02-21T14:30:00Z)
  company_id: Int!         # Authenticated company ID
}
```

#### DotwError

Present only when `success: false`.

```graphql
type DotwError {
  error_code: DotwErrorCode!      # Machine-readable enum
  error_message: String!          # User-friendly (WhatsApp-safe)
  error_details: String           # Technical details for debugging
  action: DotwErrorAction!        # Suggested next action
}

enum DotwErrorCode {
  CREDENTIALS_NOT_CONFIGURED
  CREDENTIALS_INVALID
  ALLOCATION_EXPIRED
  RATE_UNAVAILABLE
  HOTEL_SOLD_OUT
  PASSENGER_VALIDATION_FAILED
  API_TIMEOUT
  API_ERROR
  CIRCUIT_BREAKER_OPEN
  VALIDATION_ERROR
  INTERNAL_ERROR
}

enum DotwErrorAction {
  RETRY
  RETRY_IN_30_SECONDS
  RECONFIGURE_CREDENTIALS
  RESEARCH
  CANCEL
  NONE
}
```

#### RateMarkup

Transparent pricing breakdown for every rate.

```graphql
type RateMarkup {
  original_fare: Float!      # DOTW fare before markup
  markup_percent: Float!     # Percentage (0–100)
  markup_amount: Float!      # Absolute amount in currency
  final_fare: Float!         # Customer-facing price
}
```

#### CancellationRule

Cancellation penalty rules for a rate.

```graphql
type CancellationRule {
  from_date: String!         # ISO 8601 (2026-02-28T00:00:00Z)
  to_date: String!           # ISO 8601
  charge: Float!             # Penalty amount
  cancel_charge: Float!      # May differ from charge
}
```

---

## Service Layer

### DotwService

Core business logic for all DOTW operations.

#### Initialization

```php
// B2B multi-tenant
$service = new DotwService(companyId: $company->id);

// Legacy single-tenant
$service = new DotwService();  // Uses env vars
```

**Constructor Parameters:**
- `$companyId` (int|null): Company ID for credential resolution
- `$auditService` (DotwAuditService|null): Optional audit service instance

**Credential Resolution:**
1. **B2B Path** (companyId provided): Loads from `company_dotw_credentials` table
2. **Legacy Path** (companyId null): Falls back to `config/dotw.php` environment variables

**Throws `RuntimeException`** if companyId provided but no active credentials exist.

#### Key Methods

**searchHotels(array $params)**
- Searches available hotels by destination, dates, and rooms
- Returns array of hotels with cheapest rates per meal plan
- Cached 2.5 minutes per company
- Circuit breaker protection enabled
- 25-second timeout

```php
$results = $service->searchHotels([
    'fromDate' => '2026-03-15',
    'toDate' => '2026-03-18',
    'currency' => 'USD',
    'city' => 'JED',
    'rooms' => [
        ['adultsCode' => 2, 'children' => [], 'rateBasis' => 1]
    ]
]);
```

**getRooms(array $params, bool $blocking)**
- Dual-call pattern (browse then block)
- First call ($blocking=false): Get all rates and allocation details
- Second call ($blocking=true): Lock the rate for 3 minutes
- Returns array of room types with rate details
- No caching; always fresh from DOTW
- 25-second timeout

```php
// Browse
$rooms = $service->getRooms([...], blocking: false);

// Block
$blocked = $service->getRooms([
    ...same params...,
    'roomTypeSelected' => [
        'code' => 'STD',
        'selectedRateBasis' => 1332,
        'allocationDetails' => $rooms[0]['details'][0]['allocationDetails']
    ]
], blocking: true);
```

**confirmBooking(array $params)**
- Confirms booking immediately (direct flow)
- Creates `dotw_bookings` record
- Returns confirmation code and status
- 25-second timeout

**saveBooking(array $params) / bookItinerary(string $bookingCode)**
- Save booking for later (Non-Refundable Advance Purchase rates)
- Two-step process: save → confirm later

**cancelBooking(array $params)**
- Two-step cancellation flow
- First call (confirm='no'): Query charges
- Second call (confirm='yes'): Apply penalty, get refund

**applyMarkup(float $originalFare)**
- Applies per-company markup to raw fare
- Returns array with original, markup %, amount, and final fare
- Uses company credentials `markup_percent` field

```php
$markup = $service->applyMarkup(100.00);
// Returns:
// [
//   'original_fare' => 100.00,
//   'markup_percent' => 15.0,
//   'markup_amount' => 15.00,
//   'final_fare' => 115.00
// ]
```

**Rate Basis Constants:**
```php
DotwService::RATE_BASIS_ALL        // 1
DotwService::RATE_BASIS_ROOM_ONLY  // 1331
DotwService::RATE_BASIS_BB         // 1332
DotwService::RATE_BASIS_HB         // 1333
DotwService::RATE_BASIS_FB         // 1334
DotwService::RATE_BASIS_AI         // 1335
DotwService::RATE_BASIS_SC         // 1336
```

---

### DotwCacheService

Deterministic, per-company, order-independent caching of search results.

#### Purpose
- Reduce redundant API calls during WhatsApp conversations
- 2.5-minute TTL (configurable via `DOTW_CACHE_TTL`)
- Storage: Laravel Cache (File, Redis, Memcached)

#### Public Methods

**buildKey(int $companyId, string $destination, string $checkin, string $checkout, array $rooms)**
- Builds deterministic cache key
- Room array normalization (order-independent)
- Returns: `dotw_search_{company_id}_{destination}_{checkin}_{checkout}_{rooms_hash}`

```php
$key = $cacheService->buildKey(
    42, 'JED', '2026-03-15', '2026-03-18',
    [['adultsCode' => 2, 'children' => [8, 5]]]
);
// Result: dotw_search_42_jed_2026-03-15_2026-03-18_a1b2c3d4e5f6g7h8...
```

**remember(string $key, callable $callback)**
- Retrieves cached value or executes callback
- Caches result for TTL duration

```php
$results = $cacheService->remember($key, function () use ($service, $params) {
    return $service->searchHotels($params);
});
```

**isCached(string $key)**
- Checks if key exists in cache
- Useful for annotating response with `cached: true` flag

**get(string $key)**
- Retrieves cached value without affecting TTL
- Use case: Circuit breaker fallback

**forget(string $key)**
- Removes specific cache entry
- Use case: Invalidate stale results after booking confirmed

#### Room Normalization

Order-independent caching through room normalization:
1. Per-room: Keys sorted (ksort)
2. Children ages: Sorted ascending
3. Multi-room: Sorted by `adultsCode`

**Example:**
```php
$rooms1 = [['adultsCode' => 2, 'children' => [8, 5]]];
$rooms2 = [['adultsCode' => 2, 'children' => [5, 8]]];

$key1 = $cache->buildKey(..., $rooms1);
$key2 = $cache->buildKey(..., $rooms2);
assert($key1 === $key2);  // ✓ Identical despite different order
```

---

### DotwCircuitBreakerService

State-machine circuit breaker preventing API hammering during outages.

#### Overview
- **Threshold:** 5 failures in 60 seconds
- **Open Duration:** 30 seconds (auto-recovery)
- **Scope:** Applied to `searchHotels` only
- **State Store:** Redis or file cache (two keys per company)

#### State Machine

```
CLOSED (normal)
  ↓ (5 failures/60s)
OPEN (reject requests for 30s)
  ↓ (30s expires OR recordSuccess() called)
CLOSED (reset)
```

#### Public Methods

**isOpen(int $companyId)**
- Returns true if circuit currently open
- Check before API call to decide on cached fallback

```php
if ($breaker->isOpen($companyId)) {
    $cached = $cacheService->get($key);
    if ($cached) return $cached;
    throw new Exception('API temporarily unavailable');
}
```

**recordFailure(int $companyId)**
- Increments failure counter atomically
- Logs warning when circuit opens
- Uses Cache::add() for thread-safe 60-second window

```php
try {
    $results = $service->searchHotels($params);
    $breaker->recordSuccess($companyId);
} catch (Exception $e) {
    $breaker->recordFailure($companyId);
    throw $e;
}
```

**recordSuccess(int $companyId)**
- Resets failure counter and closes circuit immediately
- Clears both `dotw_circuit_failures_{companyId}` and `dotw_circuit_open_{companyId}`

#### Cache Keys

| Key | Purpose | TTL |
|-----|---------|-----|
| `dotw_circuit_failures_{companyId}` | Rolling failure counter | 60s |
| `dotw_circuit_open_{companyId}` | Open flag | 30s |

#### Production Considerations

- **Redis/Memcached required:** File cache's `increment()` is not atomic
- **Integration pattern:** Check `isOpen()` before API call; call `recordSuccess/Failure()` after

---

### DotwAuditService

Sanitized audit logging for all DOTW operations.

#### Purpose
- Compliance audit trail with automatic PII/credential redaction
- Single point for writing to `dotw_audit_logs` table
- Audit failures never break operations

#### Operation Types

| Constant | Value | Logged By |
|----------|-------|-----------|
| `OP_SEARCH` | 'search' | `searchHotels()` |
| `OP_RATES` | 'rates' | `getRooms(blocking=false)` |
| `OP_BLOCK` | 'block' | `getRooms(blocking=true)` |
| `OP_BOOK` | 'book' | `confirmBooking()`, etc. |

#### Public Methods

**log(string $operationType, array $request, array $response, ?string $resayilMessageId, ?string $resayilQuoteId, ?int $companyId)**

```php
$auditLog = $auditService->log(
    DotwAuditService::OP_SEARCH,
    ['fromDate' => '2026-03-15', ...],
    ['hotels' => [...], 'totalCount' => 42],
    'MSG-123456',
    null,
    42
);
```

**Sanitization:**
- Both request and response recursively sanitized
- Sensitive keys (case-insensitive): password, dotw_password, dotw_username, token, credit_card, cvv, passport_number, etc.
- Any matching key has value replaced with `[REDACTED]`

**Error Handling:**
- If DB write fails, exception caught and logged to 'dotw' channel
- No exception rethrown — audit failure must not break operations
- Returns unsaved DotwAuditLog instance for type safety

**operationTypes()**
- Returns array of valid operation type strings
- Use for input validation

---

## Admin UI & Configuration

### Accessing DOTW Admin

**Two Access Points:**

1. **Standalone Page:** `/admin/dotw`
   - Full-screen DOTW management
   - Sidebar icon: Webbeds logo

2. **Settings Tab:** `/settings` → **DOTW / Hotel API** tab
   - Embedded within Project Settings
   - Quick access for configuration

### Credentials Tab

**For Company Admins:**
1. Navigate to `/settings` → **DOTW / Hotel API** → **Credentials**
2. Fill form:
   - DOTW Username (max 100 chars)
   - DOTW Password (max 200 chars, never pre-filled after save)
   - DOTW Company Code (max 50 chars)
   - Markup % (0-100, optional, default 20%)
3. Click "Save Credentials"
4. Encrypted at rest using `APP_KEY`

**For Super Admins via REST API:**
```bash
POST /api/admin/companies/{companyId}/dotw-credentials
Content-Type: application/json

{
  "dotw_username": "user",
  "dotw_password": "pass",
  "dotw_company_code": "CODE",
  "markup_percent": 20
}
```

### API Tokens Tab (Super Admin Only)

1. Navigate to `/admin/dotw` → **API Tokens**
2. Click **"Generate"** for company
3. Copy plaintext token (shown once)
4. Use in n8n as `Authorization: Bearer {token}`

**Token Management:**
- **Generate/Regenerate:** Create new Sanctum token
- **Revoke:** Delete token; n8n requests fail with 401
- **Token Format:** `{UUID}|{HASH}` (65 chars)
- **Lifetime:** ~1 year (Sanctum config)

### Audit Logs Tab

View all DOTW GraphQL operations with filtering:

**Filters:**
- Operation: search, rates, block, book
- Message ID: WhatsApp message ID (partial match)
- From/To: Date range
- Company ID: Super Admin only

**Log Table Columns:**
- ID, Company, Message ID, Quote ID, Operation (color-coded), Payloads, Created

**Viewing Payloads:**
- Click "View" button to expand request/response JSON
- Fully sanitized (credentials removed)

---

## n8n Integration

### Setup Steps

**Step 1: Generate API Token**
1. Login with Super Admin role
2. Navigate to `/admin/dotw` → **API Tokens**
3. Click **"Generate"** for your company
4. Copy token
5. Store in n8n env: `DOTW_TOKEN`

**Step 2: Create HTTP Node**

| Setting | Value |
|---------|-------|
| Method | POST |
| URL | https://development.citycommerce.group/graphql |
| Authentication | None (use headers) |

**Step 3: Configure Authorization**

Add to Headers:
```
Authorization: Bearer {{ $env.DOTW_TOKEN }}
Content-Type: application/json
```

**Step 4: Send GraphQL Query**

Set Body to GraphQL mutation/query.

### Workflow Best Practices

1. **Always check `success` field:**
   ```javascript
   if (response.success === true) {
     // Process data
   } else {
     // Handle error based on response.error.action
   }
   ```

2. **Handle error actions:**
   ```javascript
   switch(response.error.action) {
     case "RETRY": // Immediate retry
     case "RETRY_IN_30_SECONDS": // Wait 30s
     case "RECONFIGURE_CREDENTIALS": // Alert admin
     case "RESEARCH": // Run new searchHotels
     case "CANCEL": // Abort workflow
   }
   ```

3. **Log trace_id for debugging:**
   ```javascript
   console.log(`Trace: ${response.meta.trace_id}`);
   ```

### Error Response Structure

```json
{
  "success": false,
  "error": {
    "error_code": "API_TIMEOUT",
    "error_message": "The DOTW API did not respond within 25 seconds",
    "error_details": "Connection timeout to api.dotwconnect.com",
    "action": "RETRY"
  },
  "meta": {
    "trace_id": "uuid-...",
    "timestamp": "2026-02-21T14:30:00Z",
    "company_id": 5
  }
}
```

---

## Database Schema

### Tables

#### company_dotw_credentials
- `id` (PK)
- `company_id` (FK to companies, UNIQUE)
- `dotw_username` (text, encrypted)
- `dotw_password` (text, encrypted)
- `dotw_company_code` (string, plaintext)
- `markup_percent` (decimal, default 20.00)
- `is_active` (boolean, default true)
- `created_at`, `updated_at`

#### dotw_prebooks
- `id` (PK)
- `company_id` (nullable, indexed)
- `resayil_message_id` (string, nullable)
- `prebook_key` (string, UNIQUE)
- `allocation_details` (longtext)
- `hotel_code`, `hotel_name`, `room_type`, `room_quantity`
- `total_fare`, `total_tax`, `original_currency`, `exchange_rate`
- `room_rate_basis`, `is_refundable`
- `customer_reference` (indexed)
- `booking_details` (json, nullable)
- `expired_at` (timestamp, nullable)
- `created_at`, `updated_at`
- **Composite Index:** `(company_id, resayil_message_id, expired_at)` for BLOCK-08

#### dotw_rooms
- `id` (PK)
- `dotw_preboot_id` (FK to dotw_prebooks, CASCADE)
- `room_number` (int)
- `adults_count`, `children_count`
- `children_ages` (json, nullable)
- `passenger_nationality`, `passenger_residence` (string, nullable)
- `created_at`, `updated_at`

#### dotw_bookings
- `id` (PK)
- `prebook_key` (string, UNIQUE, no FK)
- `confirmation_code` (string, nullable)
- `confirmation_number` (string, nullable)
- `customer_reference` (string, UUID)
- `booking_status` (string, 'confirmed' | 'failed')
- `passengers` (json)
- `hotel_details` (json)
- `resayil_message_id`, `resayil_quote_id`
- `company_id` (nullable)
- `created_at` (no UPDATED_AT — immutable)

#### dotw_audit_logs
- `id` (PK)
- `company_id` (nullable)
- `resayil_message_id`, `resayil_quote_id`
- `operation_type` (enum: search, rates, block, book)
- `request_payload` (longtext, json)
- `response_payload` (longtext, json)
- `created_at` (no UPDATED_AT — append-only)
- **Indexes:** `company_id`, `(company_id, operation_type)`, `resayil_message_id`

### Relationships

```
companies (1) ──FK──> company_dotw_credentials (1)
                      ↓ (1 to Many — future)

dotw_prebooks (1) ──FK──> dotw_rooms (Many)

dotw_prebooks (reference) ──> dotw_bookings (no FK constraint)
```

---

## Booking Workflow

### End-to-End Flow

```
Step 1: searchHotels
  Input: destination, checkin/checkout, rooms, filters
  → Check cache (hit? return cached)
  → Check circuit breaker (open? return cached or error)
  → Call DOTW API (25s timeout)
  → Apply markup
  → Cache result (2.5 min)
  → Log to dotw_audit_logs
  → Return hotels with rates

Step 2: getRoomRates (Optional)
  Input: hotel_code, dates, rooms
  → Call DOTW getRooms (25s timeout, blocking=false)
  → Apply markup
  → Extract allocation_details
  → Log to dotw_audit_logs
  → Return room types with all meal plans

Step 3: blockRates
  Input: hotel_code, room_type, rate_basis, allocation_details, rooms, dates
  → Validate allocation_details
  → Call DOTW getRooms (25s timeout, blocking=true)
  → Create dotw_prebooks row (prebook_key, allocation_details, expired_at=now+3min)
  → Create dotw_rooms rows (one per room)
  → BLOCK-08: Expire any previous active prebook for this company+user
  → Log to dotw_audit_logs
  → Return prebook_key with 180-second countdown

Step 4: createPreBooking
  Input: prebook_key, passengers, rooms, dates
  → Validate prebook exists and not expired
  → Validate passenger count matches room config
  → Validate all required fields (salutation, first/last name, email)
  → Call DOTW confirmBooking (25s timeout)
  → Create dotw_bookings row (confirmation_code, passengers, status='confirmed')
  → Mark prebook as expired
  → Log to dotw_audit_logs
  → Return confirmation_code + itinerary details
  → If fails: suggest alternatives (if destination provided)
```

### Prebook State Transitions

```
CREATED (blockRates)
  ↓ (expired_at = now + 3 min)
ACTIVE (usable for createPreBooking)
  ├→ createPreBooking called → EXPIRED_CONFIRMED
  ├→ 3 minutes elapsed → EXPIRED_TIMEOUT
  ├→ New blockRates from same user → EXPIRED_AUTO (BLOCK-08)
  ↓
EXPIRED (deleted after 1 hour)
```

---

## Error Handling

### Common Error Codes & Actions

| Code | Message | Action | Cause |
|------|---------|--------|-------|
| CREDENTIALS_NOT_CONFIGURED | Credentials missing | RECONFIGURE_CREDENTIALS | No company row in dotw_credentials |
| CREDENTIALS_INVALID | Invalid username/password/code | RECONFIGURE_CREDENTIALS | Wrong credentials |
| ALLOCATION_EXPIRED | Rate allocation expired | RESEARCH | 3-min window closed or auto-expired by BLOCK-08 |
| RATE_UNAVAILABLE | Rate no longer available | RESEARCH | Released by another user or changed at DOTW |
| HOTEL_SOLD_OUT | Hotel fully booked | RESEARCH | Inventory exhausted |
| PASSENGER_VALIDATION_FAILED | Missing/invalid field | RETRY | Incomplete passenger data |
| API_TIMEOUT | DOTW didn't respond in 25s | RETRY | Network latency or DOTW slow |
| API_ERROR | DOTW server error | RETRY | DOTW API error |
| CIRCUIT_BREAKER_OPEN | 5 failures/60s | RETRY_IN_30_SECONDS | Repeated API failures; try cached or wait 30s |
| VALIDATION_ERROR | Bad input arguments | RETRY | Missing/invalid field in request |
| INTERNAL_ERROR | Unexpected server error | RETRY | Bug in platform code |

### Handling Circuit Breaker

When `searchHotels` fails with `CIRCUIT_BREAKER_OPEN`:
1. Check if cached results available
2. If yes, return cached results (stale but functional)
3. If no, return friendly message: "Service temporarily unavailable, try again in 30 seconds"
4. Wait 30 seconds, then retry
5. Circuit auto-closes when success occurs

---

## Security & Isolation

### Credential Encryption

- **Storage:** Encrypted blobs in `company_dotw_credentials`
- **Encryption:** Laravel's `Crypt` class using `APP_KEY`
- **Decryption:** Automatic via Eloquent Attribute accessors
- **Serialization:** Hidden in `$hidden` array — never exposed in JSON
- **Per-Company:** UNIQUE constraint on `company_id`

```php
$credential = CompanyDotwCredential::find(1);
$username = $credential->dotw_username;  // Auto-decrypts
```

### Multi-Tenant Isolation

**Company Context Flow:**
```
Request (GraphQL)
  ↓
Auth Middleware (extract company from user)
  ↓
GraphQL Resolver
  ↓
DotwService(companyId: $user->company_id)
  ↓
Load credentials WHERE company_id = $user->company_id
  ↓
DOTW API call with correct credentials
```

**Data Isolation:**
- `company_dotw_credentials`: FK constraint + UNIQUE on company_id
- `dotw_prebooks`: Indexed by company_id + resayil_message_id
- `dotw_audit_logs`: Indexed by company_id + operation_type
- Cache keys: Include company_id to prevent cross-company hits

### BLOCK-08: Single Active Prebook Per User

When `blockRates` called:
1. Find all active prebooks for (company_id, resayil_message_id)
2. Expire all existing ones (set `expired_at = now`)
3. Create new prebook
4. Only one active prebook per user per time

Prevents double-booking and ensures user always has latest rate lock.

### Audit Trail & Compliance

**Logging:**
- `DotwAuditService::log()` called after every operation
- Stores request/response in `dotw_audit_logs`
- Payloads automatically sanitized (passwords, cards, passport numbers removed)

**Audit Uses:**
- Dispute resolution (replay request/response)
- Security investigation (access patterns)
- Compliance (PCI-DSS, GDPR)
- WhatsApp conversation linking (message_id traceability)

---

## Troubleshooting Index

### Credential Issues

**Symptom:** Repeated `CREDENTIALS_NOT_CONFIGURED` or `CREDENTIALS_INVALID` errors

**Solutions:**
1. Verify company has credentials saved: `php artisan tinker → CompanyDotwCredential::where('company_id', 5)->first();`
2. Verify credentials valid with DOTW support
3. Check `.env` `APP_KEY` for encryption key issues

### Circuit Breaker

**Symptom:** `searchHotels` returns `CIRCUIT_BREAKER_OPEN`

**Solutions:**
1. Wait 30 seconds before retrying
2. Check logs: `grep "Circuit breaker" storage/logs/dotw.log`
3. Verify DOTW API availability
4. Check rate limits (supplier-side throttling)

### API Timeout

**Symptom:** Operations timeout after 25 seconds

**Solutions:**
1. Check network connectivity to DOTW API
2. Retry operation (transient error)
3. Check DOTW status page
4. Increase timeout if needed (adjust `DOTW_TIMEOUT` env var)

### Allocation Token Issues

**Symptom:** `blockRates` fails immediately after `getRoomRates`

**Causes:**
1. Token was Base64-encoded or modified
2. Token truncated or decoded
3. Wrong token passed to wrong hotel_code

**Solution:**
1. Pass token verbatim (raw value, no encoding)
2. Compare token length in logs (getRoomRates response vs blockRates input)
3. Re-call `getRoomRates` and try `blockRates` again

### Prebook Expiry

**Symptom:** `createPreBooking` fails with `ALLOCATION_EXPIRED`

**Causes:**
1. User took > 3 minutes between `blockRates` and `createPreBooking`
2. Another `blockRates` call from same user expired the prebook (BLOCK-08)

**Solution:**
1. Inform user to provide passenger details within 3 minutes
2. Call `blockRates` again to generate new prebook_key if needed

### Passenger Validation

**Symptom:** `createPreBooking` returns `PASSENGER_VALIDATION_FAILED`

**Check:**
1. Count matches total adults: `passengers.length === room1.adults + room2.adults + ...`
2. All required fields present: salutation, firstName, lastName, nationality, residenceCountry, email
3. Email format valid

### Cache Issues

**Symptom:** `searchHotels` returns stale results; `cached: true` when fresh expected

**Solution:**
1. Cache TTL is 2.5 minutes per company per destination/dates/rooms
2. Wait 2.5 minutes for auto-expiry
3. Clear manually: `cache()->forget('dotw_search_5_DXB_...')`

### Markup Not Applied

**Symptom:** Room prices don't include configured markup percentage

**Solution:**
1. Verify markup_percent saved: `php artisan tinker → CompanyDotwCredential::where('company_id', 5)->first()->markup_percent;`
2. Default is 20% if not set
3. Markup applied by `DotwService` in all rate methods

### Token Revoked / 401 Unauthorized

**Symptom:** n8n requests fail with 401 Unauthorized

**Solution:**
1. Check if token was revoked: `php artisan tinker → User::find(5)->tokens;`
2. If missing, regenerate via `/admin/dotw` → API Tokens
3. Update n8n Bearer token to new value
4. Test connection

### Audit Logs Not Appearing

**Symptom:** No entries in `/admin/dotw` → Audit Logs after operations

**Solution:**
1. Verify operations completed successfully (check `success: true`)
2. Check database: `php artisan tinker → DotwAuditLog::latest()->first();`
3. Verify `DotwAuditService` is being called in resolvers
4. Check `dotw` log channel configuration

### Getting Help

Include in support tickets:
1. `trace_id` from error response
2. Operation type (getCities, searchHotels, etc.)
3. Company ID
4. Timestamp of failure
5. Server logs (grep by trace_id): `grep "TRACE_ID_HERE" storage/logs/dotw.log`

---

## Document Map

This master document consolidates information from four detailed reference documents:

| Topic | Coverage in DOTW.md | Detailed Docs |
|-------|-------------------|---------------|
| **API Reference** | Complete GraphQL query/mutation specs with examples | DOTW_API_REFERENCE.md (1,637 lines) |
| **Services** | Core service layer (DotwService, Cache, CircuitBreaker, Audit) | DOTW_SERVICES.md (1,561 lines) |
| **Admin & Integration** | Credential management, API tokens, audit logs, n8n setup | DOTW_INTEGRATION_GUIDE.md (1,579 lines) |
| **Architecture & Data** | Database schema, models, booking flow, error handling | DOTW_ARCHITECTURE.md (1,183 lines) |

**For In-Depth Reference:**
- GraphQL specifics & advanced error handling → `DOTW_API_REFERENCE.md`
- Service implementation & code examples → `DOTW_SERVICES.md`
- UI workflows & n8n templates → `DOTW_INTEGRATION_GUIDE.md`
- Database design & state machines → `DOTW_ARCHITECTURE.md`

---

## Environment Configuration

### Required Environment Variables

```bash
# Application
APP_KEY=base64:...              # Encryption key for credentials
APP_ENV=production              # Environment

# DOTW Fallback (legacy single-tenant)
DOTW_USERNAME=username          # Legacy credentials
DOTW_PASSWORD=password
DOTW_COMPANY_CODE=CODE
DOTW_TIMEOUT=25                 # API timeout in seconds

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=laravel_testing
DB_USERNAME=user
DB_PASSWORD=password

# Sanctum (API tokens)
SANCTUM_STATEFUL_DOMAINS=development.citycommerce.group
SANCTUM_GUARD=web

# Caching
CACHE_STORE=file                # Use redis for production
```

### Configuration File

File: `config/dotw.php`

```php
return [
    'api' => [
        'timeout_seconds' => env('DOTW_TIMEOUT', 25),
    ],
    'allocation' => [
        'expiry_minutes' => env('DOTW_ALLOCATION_EXPIRY_MINUTES', 3),
    ],
    'cache' => [
        'ttl' => env('DOTW_CACHE_TTL', 150),           // 2.5 minutes
        'prefix' => env('DOTW_CACHE_PREFIX', 'dotw_search'),
    ],
    'circuit_breaker' => [
        'failure_threshold' => env('DOTW_CIRCUIT_BREAKER_THRESHOLD', 5),
        'window_seconds' => env('DOTW_CIRCUIT_BREAKER_WINDOW_SECONDS', 60),
        'open_duration_seconds' => env('DOTW_CIRCUIT_BREAKER_OPEN_SECONDS', 30),
    ],
    'logging' => [
        'channel' => 'dotw',
        'sanitize_credentials' => true,
    ],
];
```

---

## Version History

| Version | Date | Status |
|---------|------|--------|
| 1.0 | 2026-02-21 | Production (Complete B2B Milestone) |

---

## Support & Resources

- **Live Site:** https://development.citycommerce.group
- **Admin Interface:** `/admin/dotw` or `/settings` → DOTW tab
- **GraphQL Endpoint:** POST `/graphql`
- **Server SSH:** `ssh citycomm`
- **Documentation:** This file + 4 detailed reference docs

---

**Master Document Generated:** 2026-02-21
**DOTW v1.0 B2B Milestone - Complete**
**All 8 phases, 54 requirements implemented**
