# DOTW v1.0 B2B: Architecture & Data Model Reference

**Document Version:** 1.0
**Last Updated:** 2026-02-21
**Status:** Complete (v1.0 Milestone)
**Maintainer:** Development Team

---

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Data Models](#data-models)
3. [Database Schema](#database-schema)
4. [Booking Flow](#booking-flow)
5. [Error Handling](#error-handling)
6. [Security & Isolation](#security--isolation)
7. [Configuration & Environment](#configuration--environment)

---

## System Architecture

### High-Level System Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          EXTERNAL SYSTEMS                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐          │
│  │  WhatsApp / n8n  │  │  Resayil API     │  │  DOTW SOAP API   │          │
│  │ (Workflow Client)│  │ (Message Context)│  │ (Hotel Supplier) │          │
│  └────────┬─────────┘  └────────┬─────────┘  └────────┬─────────┘          │
│           │                     │                     │                     │
└───────────┼─────────────────────┼─────────────────────┼───────────────────┘
            │                     │                     │
            │ HTTP POST           │ Headers             │ HTTP/SOAP
            │ GraphQL             │ (Message ID)        │ (25s timeout)
            │                     │                     │
    ┌───────▼──────────────────────▼──────────────────────▼────────┐
    │              SOUD LARAVEL APPLICATION                         │
    ├──────────────────────────────────────────────────────────────┤
    │                                                               │
    │  ┌────────────────────────────────────────────────────────┐  │
    │  │         GraphQL API Layer (Lighthouse)                 │  │
    │  │  ┌──────────────────────────────────────────────────┐  │  │
    │  │  │  searchHotels(destination, dates, rooms, ...)    │  │  │
    │  │  │  getRoomRates(hotel_code, dates, ...)            │  │  │
    │  │  │  blockRates(hotel_code, allocation, ...)         │  │  │
    │  │  │  createPreBooking(prebook_key, passengers, ...)  │  │  │
    │  │  └──────────────────────────────────────────────────┘  │  │
    │  └──────────────┬──────────────────────────────────────────┘  │
    │                 │                                             │
    │  ┌──────────────▼──────────────────────────────────────────┐  │
    │  │         DotwService (Business Logic)                    │  │
    │  │  ┌──────────────────────────────────────────────────┐  │  │
    │  │  │ - Credential resolution (CompanyDotwCredential)  │  │  │
    │  │  │ - Markup calculation (configurable per company)  │  │  │
    │  │  │ - SOAP API orchestration with 25s timeout        │  │  │
    │  │  │ - Circuit breaker (5 failures/60s → 30s open)    │  │  │
    │  │  │ - Rate locking & prebook tracking                │  │  │
    │  │  │ - Passenger validation                           │  │  │
    │  │  └──────────────────────────────────────────────────┘  │  │
    │  └──────┬──────────────────────────────────────────────────┘  │
    │         │                                                     │
    │  ┌──────▼──────────────────────────────────────────────────┐  │
    │  │         Supporting Services                             │  │
    │  │  ┌──────────────────────────────────────────────────┐  │  │
    │  │  │ DotwCacheService          (2.5 min search cache) │  │  │
    │  │  │ DotwAuditService          (req/resp logging)     │  │  │
    │  │  │ DotwCircuitBreakerService (resilience)          │  │  │
    │  │  │ DotwTimeoutException      (timeout tracking)    │  │  │
    │  │  └──────────────────────────────────────────────────┘  │  │
    │  └──────────────────────────────────────────────────────────┘  │
    │         │                     │                                │
    │  ┌──────▼──────────────┐  ┌───▼──────────────────────┐        │
    │  │ DOTW Models         │  │ Database (Laravel ORM)   │        │
    │  │ (Eloquent)          │  │                          │        │
    │  │ - DotwPrebook       │  │ dotw_prebooks           │        │
    │  │ - DotwRoom          │  │ dotw_rooms              │        │
    │  │ - DotwBooking       │  │ dotw_bookings           │        │
    │  │ - DotwAuditLog      │  │ dotw_audit_logs         │        │
    │  │ - CompanyDotwCred.. │  │ company_dotw_credentials│        │
    │  └─────────────────────┘  └────────────────────────┘        │
    │                                                               │
    └───────────────────────────────────────────────────────────────┘
            │
            │ MySQL PDO
            │
    ┌───────▼──────────────────┐
    │  MySQL Database          │
    │  laravel_testing         │
    └──────────────────────────┘
```

### Component Relationships

| Component | Purpose | Dependencies |
|-----------|---------|--------------|
| **GraphQL API** | Query/mutation entry point for hotel booking operations | Lighthouse, DotwService, Authentication |
| **DotwService** | Core business logic for all DOTW operations | CompanyDotwCredential, cache, audit, circuit breaker |
| **DotwCacheService** | Deterministic 2.5-minute search result caching | Laravel cache backend |
| **DotwAuditService** | Sanitized request/response logging per operation | DotwAuditLog model |
| **DotwCircuitBreakerService** | Graceful degradation under repeated DOTW API failures | Redis or cache backend |
| **CompanyDotwCredential** | Per-company encrypted DOTW API credentials | Company model (FK) |
| **DotwPrebook** | Rate lock allocation for 3 minutes | DotwRoom (HasMany) |
| **DotwRoom** | Room occupancy details within a prebook | DotwPrebook (BelongsTo) |
| **DotwBooking** | Immutable booking confirmation record | none (standalone) |
| **DotwAuditLog** | Append-only operation audit trail | none (standalone) |
| **DotwTimeoutException** | Exception class for API timeout events | DotwService (thrown by) |

---

## Data Models

### 1. CompanyDotwCredential

**Purpose:** Stores encrypted DOTW API credentials per company (B2B multi-tenant isolation)

**Table Name:** `company_dotw_credentials`

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | bigint (PK) | No | Primary key |
| `company_id` | bigint (FK) | No | Reference to `companies` table; **UNIQUE** constraint |
| `dotw_username` | text | No | Encrypted DOTW username (via `Crypt::encrypt()`) |
| `dotw_password` | text | No | Encrypted DOTW password (via `Crypt::encrypt()`) |
| `dotw_company_code` | string(255) | No | DOTW-assigned company code (not sensitive) |
| `markup_percent` | decimal(5,2) | No | B2C markup percentage (default: 20.00) |
| `is_active` | boolean | No | Enable/disable DOTW access for this company (default: true) |
| `created_at` | timestamp | No | Row creation timestamp |
| `updated_at` | timestamp | No | Last update timestamp |

**Indexes:**
- `company_id` (UNIQUE FK)
- `is_active`

**Eloquent Relationships:**
```php
public function company(): BelongsTo
```

**Notable Features:**

- **Encryption via Accessors:** `dotw_username` and `dotw_password` are decrypted automatically in PHP via Eloquent Attribute accessors using Laravel's `Crypt::decrypt()`. The database stores the encrypted blobs.
- **Hidden from Serialization:** Both encrypted fields are in the `$hidden` array, preventing plaintext exposure in JSON responses, logs, or API dumps.
- **Markup Helper:** `getMarkupMultiplier()` method returns `(1 + markup_percent / 100)` for fare calculations.
- **Scope Method:** `scopeForCompany($query, $companyId)` filters to a single active company's credentials.

**Security Design:**
- Credentials are encrypted at rest with Laravel's application key.
- Decrypted values are only available in PHP memory; never logged in plaintext.
- Each company has exactly one row (UNIQUE constraint on `company_id`).

---

### 2. DotwPrebook

**Purpose:** Represents a rate lock (3-minute allocation) after `blockRates` is called

**Table Name:** `dotw_prebooks`

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | bigint (PK) | No | Primary key |
| `company_id` | bigint | Yes | Company that created this prebook (for BLOCK-08 single-active constraint) |
| `resayil_message_id` | string(255) | Yes | WhatsApp message ID (one active prebook per company+message_id pair) |
| `prebook_key` | string(255) | No | Unique allocation identifier (UUID-like); **UNIQUE** index |
| `allocation_details` | longtext | No | Opaque DOTW allocation token (required for `createPreBooking`) |
| `hotel_code` | string(255) | No | DOTW hotel code |
| `hotel_name` | string(255) | No | Hotel display name |
| `room_type` | string(255) | No | Room type code from DOTW (e.g., "DBL", "TWN") |
| `room_quantity` | integer | No | Number of rooms (default: 1) |
| `total_fare` | decimal(12,2) | No | Base fare (before markup and tax) |
| `total_tax` | decimal(12,2) | No | Tax amount (default: 0) |
| `original_currency` | string(3) | No | Original currency code (default: "USD") |
| `exchange_rate` | decimal(10,4) | No | Exchange rate applied if currency differs (default: 1.0) |
| `room_rate_basis` | string(50) | No | Rate basis code: 1331=RoomOnly, 1332=BB, 1333=HB, 1334=FB, 1335=AI, 1336=ALL |
| `is_refundable` | boolean | No | Refundability flag (default: true) |
| `customer_reference` | string(255) | Yes | Client's reference (indexed for quick lookup) |
| `booking_details` | json | Yes | Additional JSON-encoded booking context |
| `expired_at` | timestamp | Yes | Allocation expiry time (now + 3 minutes at creation) |
| `created_at` | timestamp | No | Prebook creation timestamp |
| `updated_at` | timestamp | No | Last update timestamp |

**Indexes:**
- `prebook_key` (UNIQUE)
- `hotel_code`
- `customer_reference`
- `expired_at`
- `created_at`
- Composite: `(company_id, resayil_message_id, expired_at)` for BLOCK-08 constraint checking

**Eloquent Relationships:**
```php
public function rooms(): HasMany
```

**Notable Methods:**

- `isValid(): bool` — Checks if allocation has not expired (compares `expired_at` to current time)
- `setExpiry(): void` — Sets `expired_at = now() + allocation_expiry_minutes` (config)
- `markExpired(): void` — Marks as expired by setting `expired_at = now()`
- `valid()` — Static query scope returns all non-expired prebooks
- `cleanupExpired(): int` — Static method deletes prebooks expired > 1 hour ago
- `activeForUser($companyId, $resayilMessageId)` — Scope for BLOCK-08: one active per user

**Business Logic:**

- Prebooks are created by the `blockRates` mutation.
- DOTW allocations expire after **3 minutes** (configurable via `config('dotw.allocation_expiry_minutes')`).
- Multiple prebooks can exist but only **one per (company_id, resayil_message_id)** can be active (non-expired).
- Creating a new prebook for the same user automatically expires the previous one (BLOCK-08).
- `createPreBooking` mutation validates `prebook_key` exists and not expired before confirming the booking.

---

### 3. DotwRoom

**Purpose:** Represents individual room occupancy details within a prebook (supports multi-room bookings)

**Table Name:** `dotw_rooms`

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | bigint (PK) | No | Primary key |
| `dotw_preboot_id` | bigint (FK) | No | Reference to `dotw_prebooks`; cascading delete |
| `room_number` | integer | No | Room sequence (0-indexed) |
| `adults_count` | integer | No | Number of adults (default: 1) |
| `children_count` | integer | No | Number of children (default: 0) |
| `children_ages` | json | Yes | Array of child ages: `[5, 8, 12]` |
| `passenger_nationality` | string(2) | Yes | ISO country code (e.g., "KW") |
| `passenger_residence` | string(2) | Yes | ISO country code for residence |
| `created_at` | timestamp | No | Row creation timestamp |
| `updated_at` | timestamp | No | Last update timestamp |

**Indexes:**
- `dotw_preboot_id` (FK)
- `room_number`

**Eloquent Relationships:**
```php
public function prebook(): BelongsTo  // DotwPrebook::class, 'dotw_preboot_id'
```

**Notable Methods:**

- `getTotalOccupancy(): int` — Returns `adults_count + children_count`
- `getOccupancyDescription(): string` — Human-readable occupancy: "2 adults, 1 child (age 8)"

**Design Notes:**

- One `DotwRoom` per room in the prebook (if `room_quantity=2`, there are 2 `DotwRoom` rows).
- Replaces the need for a separate "passengers" table during the prebook phase.
- Contains only occupancy and demographic info; actual passenger names/emails are stored in `DotwBooking::passengers` after confirmation.

---

### 4. DotwBooking

**Purpose:** Immutable confirmation record after booking is confirmed via DOTW API

**Table Name:** `dotw_bookings`

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | bigint (PK) | No | Primary key |
| `prebook_key` | string(36) | No | UUID reference to `dotw_prebooks.prebook_key`; **UNIQUE** index (no FK constraint) |
| `confirmation_code` | string(255) | Yes | DOTW `bookingCode` from confirmation response |
| `confirmation_number` | string(255) | Yes | DOTW `confirmationNumber` (secondary reference) |
| `customer_reference` | string(36) | No | UUID generated as `Str::uuid()` and sent to DOTW |
| `booking_status` | string(50) | No | 'confirmed' or 'failed' (default: 'pending') |
| `passengers` | json | No | Array of passenger detail objects: `[{salutation, firstName, lastName, nationality, email}, ...]` |
| `hotel_details` | json | No | Hotel context snapshot: `{hotel_code, hotel_name, checkin, checkout, room_type, total_fare, currency}` |
| `resayil_message_id` | string(255) | Yes | WhatsApp message ID (conversation traceability) |
| `resayil_quote_id` | string(255) | Yes | Quoted WhatsApp message ID |
| `company_id` | bigint | Yes | Company context (no FK constraint per MOD-06) |
| `created_at` | timestamp | No | Booking creation timestamp (auto-set by DB) |

**Indexes:**
- `prebook_key` (UNIQUE)
- `confirmation_code`
- Composite: `(company_id, created_at)`

**Eloquent Features:**
- `const UPDATED_AT = null` — Booking records are append-only; no updates after creation
- No relationships to other DOTW models (standalone design)

**Business Logic:**

- Created only after successful DOTW `confirmBooking` call.
- Immutable after creation (UPDATED_AT is null).
- `booking_status` values: 'confirmed' (success) or 'failed' (DOTW error).
- Contains full passenger and hotel details for audit and reconciliation.
- No FK to `dotw_prebooks` (can exist independently for v2 integration with invoice/task systems).

---

### 5. DotwAuditLog

**Purpose:** Append-only audit trail of all DOTW GraphQL operations for debugging and compliance

**Table Name:** `dotw_audit_logs`

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| `id` | bigint (PK) | No | Primary key |
| `company_id` | bigint | Yes | Company context (nullable; no FK constraint per MOD-06) |
| `resayil_message_id` | string(255) | Yes | WhatsApp message ID from `X-Resayil-Message-ID` header |
| `resayil_quote_id` | string(255) | Yes | Quoted message ID from `X-Resayil-Quote-ID` header |
| `operation_type` | enum | No | One of: 'search', 'rates', 'block', 'book' |
| `request_payload` | longtext | Yes | Sanitized request (credentials & sensitive data stripped) |
| `response_payload` | longtext | Yes | Sanitized response (credentials & sensitive data stripped) |
| `created_at` | timestamp | No | Log creation timestamp (auto-set by DB) |

**Indexes:**
- `company_id` (for company context queries)
- Composite: `(company_id, operation_type)` for operation filtering per company
- `resayil_message_id` for linking to WhatsApp conversations

**Eloquent Features:**
- `const UPDATED_AT = null` — Audit logs are immutable
- `public $timestamps = false` with explicit `const CREATED_AT = 'created_at'`
- Static `log(array $data): static` — Semantic entry point for creating audit records

**Security Design:**

- Request and response payloads are **sanitized** before logging:
  - DOTW username/password stripped
  - Passenger passport numbers/sensitive IDs removed
  - Payment details scrubbed
- Payloads stored as JSON strings (cast to array by Eloquent for retrieval)
- No FK to companies (module stays standalone per MOD-06)

**Use Cases:**

- Debug booking disputes by replaying request/response
- Trace WhatsApp conversations to booking confirmations
- Audit trail for compliance (PCI-DSS, GDPR)
- Monitor operation success rates per company/operation

---

### 6. DotwTimeoutException

**Purpose:** Exception thrown when DOTW API does not respond within the configured timeout window

**File:** `/home/soudshoja/soud-laravel/app/Exceptions/DotwTimeoutException.php`

**Class Definition:**
```php
class DotwTimeoutException extends \Exception {}
```

**When Thrown:**

- By `DotwService::post()` when DOTW SOAP call exceeds **25 seconds** (configurable)
- Only thrown for timeout events; not for HTTP errors or invalid responses

**Exception Hierarchy:**

```
Exception (PHP base)
└── DotwTimeoutException (custom)
    └── Used to distinguish from RuntimeException (credential errors)
```

**Catch Order in Resolvers:**

```php
try {
    // GraphQL resolver code
} catch (DotwTimeoutException $e) {
    // Handle timeout (return friendly "Search taking too long" message)
} catch (RuntimeException $e) {
    // Handle credential errors
} catch (\Exception $e) {
    // Handle other errors
}
```

**N8N Integration:**

When caught, the resolver should return:
```graphql
{
  success: false
  error: {
    error_code: "DOTW_TIMEOUT"
    error_message: "Search taking too long, please try again"
    action: "retry"
  }
}
```

---

## Database Schema

### Entity-Relationship Diagram (ERD)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          SOUD LARAVEL DOTW MODULE                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│                                                                              │
│  ┌──────────────────────────┐           ┌──────────────────────────┐       │
│  │    companies             │           │ company_dotw_credentials │       │
│  ├──────────────────────────┤           ├──────────────────────────┤       │
│  │ id (PK)                  │──FK───────│ company_id (FK, UNIQUE)  │       │
│  │ name                     │    1   *  │ dotw_username (encrypted)│       │
│  │ ...                      │           │ dotw_password (encrypted)│       │
│  └──────────────────────────┘           │ dotw_company_code        │       │
│                                         │ markup_percent           │       │
│                                         │ is_active                │       │
│                                         │ created_at               │       │
│                                         │ updated_at               │       │
│                                         └──────────────────────────┘       │
│                                                                              │
│  ┌──────────────────────────┐           ┌──────────────────────────┐       │
│  │   dotw_prebooks          │  1    *   │    dotw_rooms            │       │
│  ├──────────────────────────┤───────────├──────────────────────────┤       │
│  │ id (PK)                  │           │ id (PK)                  │       │
│  │ company_id               │           │ dotw_preboot_id (FK)     │       │
│  │ resayil_message_id       │ (BLOCK-08)│ room_number              │       │
│  │ prebook_key (UNIQUE)     │           │ adults_count             │       │
│  │ allocation_details       │           │ children_count           │       │
│  │ hotel_code               │           │ children_ages (JSON)     │       │
│  │ hotel_name               │           │ passenger_nationality    │       │
│  │ room_type                │           │ passenger_residence      │       │
│  │ room_quantity            │           │ created_at               │       │
│  │ total_fare               │           │ updated_at               │       │
│  │ total_tax                │           │                          │       │
│  │ original_currency        │           └──────────────────────────┘       │
│  │ exchange_rate            │                                              │
│  │ room_rate_basis          │                                              │
│  │ is_refundable            │                                              │
│  │ customer_reference       │                                              │
│  │ booking_details (JSON)   │                                              │
│  │ expired_at               │  (3-min allocation window)                   │
│  │ created_at               │                                              │
│  │ updated_at               │                                              │
│  └──────────────────────────┘                                              │
│       │                                                                     │
│       └─ Relationship (HasMany) ──────────► dotw_rooms                     │
│                                                                              │
│  ┌──────────────────────────┐                                              │
│  │   dotw_bookings          │  (Immutable — append-only)                   │
│  ├──────────────────────────┤                                              │
│  │ id (PK)                  │                                              │
│  │ prebook_key (UNIQUE)     │ ──ref──► dotw_prebooks.prebook_key (no FK)   │
│  │ confirmation_code        │                                              │
│  │ confirmation_number      │                                              │
│  │ customer_reference       │                                              │
│  │ booking_status           │  ('confirmed' | 'failed')                    │
│  │ passengers (JSON)        │  (sanitized passenger details)               │
│  │ hotel_details (JSON)     │  (hotel context snapshot)                    │
│  │ resayil_message_id       │                                              │
│  │ resayil_quote_id         │                                              │
│  │ company_id               │  (no FK per MOD-06)                          │
│  │ created_at               │                                              │
│  └──────────────────────────┘                                              │
│                                                                              │
│  ┌──────────────────────────┐                                              │
│  │   dotw_audit_logs        │  (Append-only audit trail)                   │
│  ├──────────────────────────┤                                              │
│  │ id (PK)                  │                                              │
│  │ company_id               │  (nullable, no FK per MOD-06)                │
│  │ resayil_message_id       │  (WhatsApp traceability)                     │
│  │ resayil_quote_id         │                                              │
│  │ operation_type (enum)    │  ('search', 'rates', 'block', 'book')        │
│  │ request_payload (JSON)   │  (sanitized request)                         │
│  │ response_payload (JSON)  │  (sanitized response)                        │
│  │ created_at               │                                              │
│  └──────────────────────────┘                                              │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘

LEGEND:
  ────FK──────  Foreign Key with ON DELETE CASCADE
  ────ref──────  Reference (no FK constraint)
  (BLOCK-08)    Business constraint: one active prebook per (company, user)
  (3-min)       Allocation expires after 3 minutes
```

### Migration Execution Order

| Order | File | Purpose | Dependencies |
|-------|------|---------|--------------|
| 1 | `2026_02_21_100001_create_company_dotw_credentials_table.php` | Per-company credentials storage | FK to `companies` |
| 2 | `2026_02_21_033317_create_dotw_prebooks_table.php` | Rate lock allocations | None |
| 3 | `2026_02_21_033318_create_dotw_rooms_table.php` | Room occupancy details | FK to `dotw_prebooks` |
| 4 | `2026_02_21_100001_create_dotw_audit_logs_table.php` | Operation audit trail | None (standalone) |
| 5 | `2026_02_21_165035_create_dotw_bookings_table.php` | Booking confirmations | None (no FK) |
| 6 | `2026_02_21_155718_add_company_id_resayil_to_dotw_prebooks_table.php` | BLOCK-08 constraint columns | Alters `dotw_prebooks` |

**Migration Summary:**

- **4 tables created:** `company_dotw_credentials`, `dotw_prebooks`, `dotw_audit_logs`, `dotw_bookings`
- **1 table enhanced:** `dotw_prebooks` (add `company_id`, `resayil_message_id`)
- **1 child table created:** `dotw_rooms` (FK to `dotw_prebooks` with cascading delete)
- **Total FKs:** 2 (company → credentials, prebooks → rooms); no FKs from audit/booking (standalone per MOD-06)
- **All migrations idempotent:** Can run multiple times without errors

---

## Booking Flow

### End-to-End Booking Workflow

```
┌────────────────────────────────────────────────────────────────────────────┐
│ BOOKING WORKFLOW: Search → Rate → Block → Confirm                         │
├────────────────────────────────────────────────────────────────────────────┤
│                                                                            │
│ Step 1: searchHotels Query                                                │
│ ────────────────────────────────────────────────────────────────────────  │
│ Input: {                                                                   │
│   destination: "Dubai"          (city code or name)                        │
│   checkinDate: "2026-03-01"                                               │
│   checkoutDate: "2026-03-05"                                              │
│   rooms: [                       (multi-room support)                      │
│     { adults: 2, children: 0 },  (Room 1: 2 adults)                       │
│     { adults: 1, children: 1, childrenAges: [8] }  (Room 2: 1 adult, 1 kid)
│   ]                                                                        │
│   filters: { ... }               (optional: rating, price, amenities, etc)│
│   currency: "KWD"                (optional, company default if omitted)    │
│   X-Resayil-Message-ID: "msg_123"  (WhatsApp context)                     │
│ }                                                                          │
│                                                                            │
│ Processing:                                                               │
│  1. Resolve company from auth context                                     │
│  2. Load CompanyDotwCredential (encrypted creds auto-decrypted)           │
│  3. Check cache: dotw_search_{company}_{dest}_{dates}_{rooms_hash}       │
│     ✓ If hit → return cached result + cached: true flag                   │
│  4. If miss → call DOTW searchHotels (25s timeout)                        │
│  5. Log to dotw_audit_logs: operation='search', req payload, resp payload │
│  6. Cache result for 2.5 minutes                                          │
│  7. Return: [ { hotel_code, name, rating, image_url, rates: [...] }, ...] │
│                                                                            │
│ Data Persisted: dotw_audit_logs (request/response)                       │
│ State: Hotels browsed; no commitment yet                                  │
│                                                                            │
│ ─────────────────────────────────────────────────────────────────────────  │
│                                                                            │
│ Step 2: getRoomRates Query (Optional — for detailed rate browsing)        │
│ ────────────────────────────────────────────────────────────────────────  │
│ Input: {                                                                   │
│   hotelCode: "DXB-12345"        (from search results)                     │
│   checkinDate: "2026-03-01"                                               │
│   checkoutDate: "2026-03-05"                                              │
│   rooms: [ { adults: 2 }, { adults: 1, children: 1, ages: [8] } ]        │
│   X-Resayil-Message-ID: "msg_123"                                         │
│ }                                                                          │
│                                                                            │
│ Processing:                                                               │
│  1. Resolve company credentials                                           │
│  2. Call DOTW getRooms (25s timeout; no blocking yet)                     │
│  3. Apply company markup (e.g., 100 KD * 1.20 = 120 KD)                   │
│  4. Build response: all room types, all meal plans, rates, cancellation   │
│  5. Log to dotw_audit_logs: operation='rates'                             │
│  6. Return: [ { roomType, mealPlan, fare, tax, allocationDetails, ... }...] │
│                                                                            │
│ Data Persisted: dotw_audit_logs only                                      │
│ State: Rates browsed; still no lock                                       │
│                                                                            │
│ ─────────────────────────────────────────────────────────────────────────  │
│                                                                            │
│ Step 3: blockRates Mutation (BLOCK ALLOCATION FOR 3 MINUTES)              │
│ ────────────────────────────────────────────────────────────────────────  │
│ Input: {                                                                   │
│   hotelCode: "DXB-12345"                                                  │
│   checkinDate: "2026-03-01"                                               │
│   checkoutDate: "2026-03-05"                                              │
│   rooms: [ { adults: 2 }, { adults: 1, children: 1, ages: [8] } ]        │
│   selectedRoomType: "DBL"        (e.g., "DBL", "TWN")                     │
│   selectedRateBasis: "1332"      (BB, HB, FB, etc.)                       │
│   allocationDetails: "<opaque_token_from_getRates>"                       │
│   X-Resayil-Message-ID: "msg_123"                                         │
│ }                                                                          │
│                                                                            │
│ Processing:                                                               │
│  1. Resolve company context                                               │
│  2. Validate allocationDetails matches hotel_code (prevent token mixing)  │
│  3. Check if allocation < 1 minute remaining → reject, prompt re-search   │
│  4. Call DOTW getRooms with blocking=true (25s timeout)                   │
│  5. DOTW locks rate for 3 minutes                                         │
│  6. CREATE dotw_prebooks row:                                             │
│     - prebook_key = Str::uuid()                                           │
│     - company_id = {resolved}                                             │
│     - resayil_message_id = "msg_123"                                      │
│     - allocation_details = {returned from DOTW}                           │
│     - expired_at = now() + 3 minutes                                      │
│     - (BLOCK-08: expire any other active prebook for this company+user)   │
│  7. CREATE dotw_rooms rows (one per room in booking):                     │
│     - dotw_preboot_id = prebook.id                                        │
│     - adults_count, children_count, children_ages                         │
│  8. Log to dotw_audit_logs: operation='block'                             │
│  9. Return: {                                                             │
│       prebook_key: "uuid-...",                                            │
│       countdownSeconds: 180,                                              │
│       expiresAt: "2026-02-21T10:00:00Z",                                  │
│       hotelDetails: { ... },                                              │
│       success: true                                                       │
│     }                                                                      │
│                                                                            │
│ Data Persisted:                                                           │
│  - 1 row in dotw_prebooks                                                 │
│  - N rows in dotw_rooms (one per room)                                    │
│  - 1 row in dotw_audit_logs                                               │
│ State: Rate locked for 3 minutes; ready for confirmation                 │
│                                                                            │
│ ─────────────────────────────────────────────────────────────────────────  │
│                                                                            │
│ Step 4: createPreBooking Mutation (CONFIRM BOOKING)                       │
│ ────────────────────────────────────────────────────────────────────────  │
│ Input: {                                                                   │
│   prebookKey: "uuid-..." (from blockRates response)                       │
│   passengers: [                                                           │
│     {                                                                     │
│       salutation: "Mr",                                                   │
│       firstName: "Ahmed",                                                 │
│       lastName: "Al-Sabah",                                               │
│       nationality: "KW",  (ISO country code)                              │
│       residenceCountry: "KW",                                             │
│       email: "ahmed@example.com"  (required, validated)                   │
│     },                                                                    │
│     { ... }  (more passengers for rooms 2, 3, etc.)                       │
│   ]                                                                       │
│   X-Resayil-Message-ID: "msg_123"                                         │
│ }                                                                          │
│                                                                            │
│ Validation:                                                               │
│  1. prebook_key exists in dotw_prebooks                                  │
│  2. prebook not expired (now < expired_at)                                │
│  3. passenger count matches room occupancy                                │
│  4. all required fields present (first name, email, etc.)                 │
│  5. email format valid                                                    │
│  6. missing field → return specific error (e.g., "Please provide email")  │
│                                                                            │
│ Processing (if valid):                                                    │
│  1. Load prebook + rooms from DB                                          │
│  2. Resolve company credentials                                           │
│  3. Build passenger array for DOTW confirmBooking call                    │
│  4. Generate customer_reference = Str::uuid()                             │
│  5. Call DOTW confirmBooking (25s timeout)                                │
│  6. DOTW returns: { bookingCode, confirmationNumber, ... }                │
│  7. CREATE dotw_bookings row:                                             │
│     - prebook_key = input prebook_key                                     │
│     - confirmation_code = DOTW bookingCode                                │
│     - booking_status = 'confirmed'                                        │
│     - passengers = submitted passenger details (sanitized)                │
│     - hotel_details = snapshot from prebook                               │
│     - company_id = resolved                                               │
│  8. Mark prebook as expired: prebook.markExpired()                        │
│  9. Log to dotw_audit_logs: operation='book', request, response           │
│ 10. Return: {                                                             │
│       success: true,                                                      │
│       bookingConfirmationCode: "DOTW-...",                                │
│       bookingStatus: "confirmed",                                         │
│       itineraryDetails: { hotelName, checkin, checkout, rate, ... }       │
│     }                                                                      │
│                                                                            │
│ On Failure (rate no longer available, sold out):                          │
│  - Return: { success: false, error: {...}, alternatives: [ ... ] }        │
│  - Suggest 3 alternative hotels                                           │
│  - Log to dotw_audit_logs: operation='book', booking_status='failed'      │
│                                                                            │
│ Data Persisted:                                                           │
│  - 1 row in dotw_bookings (confirmation record)                           │
│  - 1 row in dotw_audit_logs                                               │
│  - prebook marked expired (expired_at = now)                              │
│ State: Booking confirmed; itinerary ready                                 │
│                                                                            │
└────────────────────────────────────────────────────────────────────────────┘
```

### DotwPrebook State Transitions

```
┌──────────────┐
│   CREATED    │
│ (blockRates) │
└──────┬───────┘
       │ expired_at = now() + 3 min
       │
       ▼
┌──────────────────────────┐
│   ACTIVE/VALID           │
│ (non-expired, usable)    │
└──────┬───────────────────┘
       │
       │ (Option A)                    (Option B)                 (Option C)
       ├─ createPreBooking called ──►  MARKED_EXPIRED         EXPIRED_TIMEOUT
       │  (confirm booking)            (bookings table created)  (3 min elapsed)
       │                                                          │
       └───────────────┬────────────────────────┬────────────────┘
                       │                        │
                       ▼                        ▼
            ┌──────────────────────┐  ┌──────────────────────┐
            │  EXPIRED_CONFIRMED   │  │  EXPIRED_TIMEOUT     │
            │  (after booking)     │  │  (allocation lapsed) │
            │  (can be cleaned up) │  │  (can be cleaned up) │
            └──────────────────────┘  └──────────────────────┘
                      │                         │
                      └──────────┬──────────────┘
                                 │
                                 ▼
                    ┌──────────────────────┐
                    │  CLEANUP (> 1 hour)  │
                    │  (deleted by cron)   │
                    └──────────────────────┘

TIMING:
  - CREATED → ACTIVE: 0s to 180s (3 minutes)
  - ACTIVE → EXPIRED: 180s (or earlier if createPreBooking called)
  - EXPIRED → CLEANED: > 3600s (1 hour)

KEY: Only one ACTIVE prebook per (company_id, resayil_message_id) — new blockRates
     call automatically expires the previous one (BLOCK-08).
```

### Data Persistence Per Step

| Step | Tables | Records Created | Notes |
|------|--------|-----------------|-------|
| searchHotels | dotw_audit_logs | 1 log row | Request/response payload, cache flag |
| getRoomRates | dotw_audit_logs | 1 log row | Detailed rates, no database changes |
| blockRates | dotw_prebooks, dotw_rooms, dotw_audit_logs | 1 prebook + N rooms + 1 log | 3-min allocation; BLOCK-08 expires previous |
| createPreBooking | dotw_bookings, dotw_audit_logs | 1 booking + 1 log | Immutable confirmation record |

---

## Error Handling

### DotwTimeoutException

**When Thrown:**

```php
// In DotwService::post()
if (time exceeds 25 seconds) {
    throw new DotwTimeoutException("DOTW API timeout");
}
```

**Catch and Handle:**

```php
try {
    $response = $dotwService->searchHotels(...);
} catch (DotwTimeoutException $e) {
    return [
        'success' => false,
        'error' => [
            'error_code' => 'DOTW_TIMEOUT',
            'error_message' => 'Search taking too long, please try again',
            'action' => 'retry',
        ]
    ];
}
```

**N8N Workflow Action:** Retry immediately or after brief delay (N8N interprets `action: retry`)

---

### Circuit Breaker Pattern

**Service:** `DotwCircuitBreakerService`

**States:**

```
┌──────────────────────────────────────────────────────────────────────────┐
│ CIRCUIT BREAKER STATE MACHINE                                             │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                           │
│ Initial: CLOSED (normal operation)                                       │
│    │                                                                      │
│    │ ◄─── 5 failures within 60 seconds ──────────────┐                  │
│    │                                                  │                  │
│    ▼                                                  │                  │
│ OPEN (circuit trips)                                │                  │
│    │  - Return cached results if available          │                  │
│    │  - Return "Try again in 30s" if no cache       │                  │
│    │  - Block all new DOTW API calls                │                  │
│    │  - Failure counter: 0 (reset)                  │                  │
│    │                                                  │                  │
│    │ 30 seconds elapse (or HALF_OPEN triggered)     │                  │
│    ▼                                                  │                  │
│ HALF_OPEN (recovery mode)                           │                  │
│    │  - Allow one test request to DOTW              │                  │
│    │  - If succeeds → CLOSED (normal)               │                  │
│    │  - If fails → OPEN (try again in 30s)          │                  │
│    │                                                  │                  │
│    └──────► CLOSED ─────► Success ─────► CLOSED ────┘                  │
│                                                                           │
└──────────────────────────────────────────────────────────────────────────┘

Configuration:
  - Failure threshold: 5 failures within 60 seconds
  - Open timeout: 30 seconds
  - Operation monitored: searchHotels only (per requirements)
```

**Implementation Details:**

- Failure counter incremented on DOTW API errors (timeout, invalid response, etc.)
- Counter reset every 60 seconds
- When counter reaches 5: circuit opens immediately
- In OPEN state:
  - Cache hit → return cached results
  - Cache miss → return friendly message "Try again in 30 seconds"
  - No DOTW API call made
- After 30 seconds: enter HALF_OPEN state (allow test request)

**GraphQL Response (Circuit Open):**

```graphql
{
  success: false
  error: {
    error_code: "DOTW_CIRCUIT_OPEN"
    error_message: "Service temporarily unavailable, please try again in 30 seconds"
    action: "retry_in_30_seconds"
  }
  timestamp: "2026-02-21T10:00:00Z"
  trace_id: "trace_abc123"
}
```

---

### Error Response Structure

**All GraphQL errors follow this envelope:**

```graphql
{
  success: Boolean!
  data: Any                    # Null if success=false
  error: {
    error_code: String!        # Machine-readable: "DOTW_TIMEOUT", "MISSING_FIELD", etc.
    error_message: String!     # User-friendly for WhatsApp display
    error_details: String      # Technical details (logged but not returned to N8N)
    action: String             # "retry", "retry_in_30_seconds", "reconfigure_credentials", "cancel"
  }
  timestamp: DateTime!
  trace_id: String!            # For correlation with logs
  meta: {
    company_id: ID
    request_id: String
  }
}
```

**Common Error Codes:**

| Code | Message | Action | Cause |
|------|---------|--------|-------|
| `DOTW_TIMEOUT` | "Search taking too long, please try again" | retry | DOTW API > 25s |
| `DOTW_CIRCUIT_OPEN` | "Try again in 30 seconds" | retry_in_30_seconds | 5 failures/60s |
| `DOTW_CREDENTIALS_MISSING` | "DOTW credentials not configured" | reconfigure_credentials | No CompanyDotwCredential |
| `ALLOCATION_EXPIRED` | "Rate offer expired, please search again" | cancel | Prebook expired |
| `INVALID_PREBOOK` | "Pre-booking not found or invalid" | cancel | Invalid prebook_key |
| `RATE_UNAVAILABLE` | "This hotel/rate no longer available" | cancel | DOTW confirmBooking failed |
| `HOTEL_SOLD_OUT` | "Hotel full, showing alternatives" | cancel | Overbooking at DOTW |
| `MISSING_FIELD` | "Please provide {field_name}" | cancel | Validation error |
| `PASSENGER_COUNT_MISMATCH` | "Passenger count mismatch" | cancel | Passenger count != room config |
| `INVALID_EMAIL` | "Invalid email format" | cancel | Email validation failed |

---

## Security & Isolation

### Credential Encryption

**Storage:**

- DOTW username and password stored as encrypted blobs in `company_dotw_credentials.dotw_username` and `dotw_password`
- Encryption performed by Laravel's `Crypt` class using the application key (`APP_KEY`)

**Access Pattern:**

```php
// Automatic decryption via Eloquent Attribute accessor:
$credential = CompanyDotwCredential::find(1);
$username = $credential->dotw_username;  // Automatically decrypts
$password = $credential->dotw_password;  // Automatically decrypts

// Encryption on assignment:
$credential->dotw_username = "new_username";  // Automatically encrypts before save
$credential->save();
```

**Serialization Protection:**

- Both credentials hidden in `$hidden` array
- `json_encode($credential)` will NOT include plaintext credentials
- API responses never expose encrypted blobs

**Scope Protection:**

```php
// Only retrieve active credentials for a specific company:
$creds = CompanyDotwCredential::forCompany($companyId)->first();
// If no row or is_active=false, returns null
```

---

### Multi-Tenant Isolation

**Company Context Resolution:**

```
Request (e.g., GraphQL mutation)
  ↓
Authentication Middleware
  ↓
Extract authenticated user's company_id
  ↓
GraphQL Resolver
  ↓
DotwService::searchHotels(companyId: $user->company_id, ...)
  ↓
Load CompanyDotwCredential WHERE company_id = $user->company_id
  ↓
DOTW API call with correct credentials
```

**Data Isolation:**

| Table | Isolation Column | Notes |
|-------|------------------|-------|
| company_dotw_credentials | company_id (FK, UNIQUE) | One row per company; FK cascade delete |
| dotw_prebooks | company_id (indexed) | Queries scoped by company_id + resayil_message_id for BLOCK-08 |
| dotw_audit_logs | company_id (indexed, nullable) | Queries scoped by company_id + operation_type |
| dotw_bookings | company_id (indexed, nullable) | Queries scoped by company_id + created_at for audit |

**Cache Key Scoping:**

```php
// Cache key includes company_id to prevent cross-company cache hits:
$cacheKey = "dotw_search_{$companyId}_{$destination}_{$checkin}_{$checkout}_{$roomHash}";
```

**BLOCK-08 Constraint (One Active Prebook Per User):**

```php
// Before creating new prebook:
$existingPrebooks = DotwPrebook::activeForUser($companyId, $resayilMessageId)
    ->get();

// Expire all existing:
foreach ($existingPrebooks as $prebook) {
    $prebook->markExpired();
}

// Create new:
$newPrebook = DotwPrebook::create([...]);
```

---

### Audit Trail & Compliance

**Logging:**

```php
// DotwAuditService::log() called after every operation
DotwAuditLog::log([
    'company_id' => $companyId,
    'resayil_message_id' => $request->header('X-Resayil-Message-ID'),
    'resayil_quote_id' => $request->header('X-Resayil-Quote-ID'),
    'operation_type' => 'search',
    'request_payload' => sanitizeCredentials($requestData),
    'response_payload' => sanitizeCredentials($responseData),
]);
```

**Payload Sanitization:**

```php
// Remove sensitive fields before logging:
- dotw_username, dotw_password
- passport_number, passport_scan
- credit_card_number, cvv
- payment_reference (payment system data)
```

**Audit Trail Uses:**

- Dispute resolution (replay request/response)
- Security investigation (trace access patterns)
- Compliance (PCI-DSS, GDPR data processing records)
- WhatsApp conversation linking (message_id traceability)

---

### N8N Sanctum Authentication

**Token Generation:**

```php
// Via /settings DOTW tab → API Tokens section
// Generate a Sanctum Bearer token tied to the company
$token = $company->createToken('dotw-api')->plainTextToken;

// Format: "uuid|hash"
```

**Usage in N8N:**

```
POST /graphql
Authorization: Bearer <token>
X-Resayil-Message-ID: msg_abc123
X-Resayil-Quote-ID: msg_parent456

Query: { searchHotels(...) }
```

**Token Scope:**

- Per-company isolation (token generated under company context)
- No user-level permissions needed (company-level auth)
- Revokable per token (via Sanctum dashboard)

---

## Configuration & Environment

### Environment Variables

```bash
# DOTW Service Configuration
DOTW_API_TIMEOUT_SECONDS=25           # Timeout for DOTW SOAP calls
DOTW_ALLOCATION_EXPIRY_MINUTES=3      # 3-minute rate lock window
DOTW_SEARCH_CACHE_MINUTES=2.5         # Search result cache duration
DOTW_CIRCUIT_BREAKER_THRESHOLD=5      # Failures before circuit opens
DOTW_CIRCUIT_BREAKER_WINDOW_SECONDS=60  # Failure window
DOTW_CIRCUIT_BREAKER_OPEN_SECONDS=30  # Open state duration

# DOTW SOAP API (Supplier)
DOTW_SOAP_ENDPOINT=https://api.dotw.example.com/soap
DOTW_SOAP_WSDL=https://api.dotw.example.com/wsdl

# Logging
LOG_CHANNEL=dotw   # Dedicated DOTW logging channel
```

### Laravel Configuration File

**File:** `config/dotw.php`

```php
return [
    'api' => [
        'timeout_seconds' => env('DOTW_API_TIMEOUT_SECONDS', 25),
        'endpoint' => env('DOTW_SOAP_ENDPOINT'),
        'wsdl' => env('DOTW_SOAP_WSDL'),
    ],

    'allocation' => [
        'expiry_minutes' => env('DOTW_ALLOCATION_EXPIRY_MINUTES', 3),
    ],

    'cache' => [
        'search_duration_minutes' => env('DOTW_SEARCH_CACHE_MINUTES', 2.5),
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

### GraphQL Schema Registration

**File:** `config/lighthouse.php`

```php
'schema' => [
    'default' => [
        'paths' => [
            base_path('graphql/schema.graphql'),
            base_path('graphql/dotw.graphql'),  // DOTW standalone schema
        ],
    ],
],
```

---

## Appendix: Migration Manifest

### All DOTW Migrations

```
database/migrations/
├── 2026_02_21_100001_create_company_dotw_credentials_table.php
│   └── Creates: company_dotw_credentials
│       FK: company_id → companies.id (CASCADE)
│       Columns: dotw_username, dotw_password (encrypted), dotw_company_code,
│               markup_percent, is_active
│       Indexes: company_id (UNIQUE), is_active
│
├── 2026_02_21_033317_create_dotw_prebooks_table.php
│   └── Creates: dotw_prebooks
│       Columns: prebook_key, allocation_details, hotel_code, hotel_name,
│               room_type, room_quantity, total_fare, total_tax,
│               original_currency, exchange_rate, room_rate_basis,
│               is_refundable, customer_reference, booking_details, expired_at
│       Indexes: prebook_key (UNIQUE), hotel_code, customer_reference,
│               expired_at, created_at
│
├── 2026_02_21_033318_create_dotw_rooms_table.php
│   └── Creates: dotw_rooms
│       FK: dotw_preboot_id → dotw_prebooks.id (CASCADE)
│       Columns: room_number, adults_count, children_count, children_ages,
│               passenger_nationality, passenger_residence
│       Indexes: dotw_preboot_id, room_number
│
├── 2026_02_21_100001_create_dotw_audit_logs_table.php
│   └── Creates: dotw_audit_logs
│       Columns: company_id (nullable), resayil_message_id, resayil_quote_id,
│               operation_type (enum), request_payload, response_payload
│       Indexes: company_id, (company_id, operation_type), resayil_message_id
│       Note: No UPDATED_AT; append-only audit trail
│
├── 2026_02_21_165035_create_dotw_bookings_table.php
│   └── Creates: dotw_bookings
│       Columns: prebook_key (unique ref, no FK), confirmation_code,
│               confirmation_number, customer_reference, booking_status,
│               passengers (JSON), hotel_details (JSON),
│               resayil_message_id, resayil_quote_id, company_id
│       Indexes: prebook_key (UNIQUE), confirmation_code,
│               (company_id, created_at)
│       Note: No UPDATED_AT; immutable booking records
│
└── 2026_02_21_155718_add_company_id_resayil_to_dotw_prebooks_table.php
    └── Alters: dotw_prebooks
        Adds: company_id (nullable), resayil_message_id (nullable)
        Adds composite index: (company_id, resayil_message_id, expired_at)
        Purpose: BLOCK-08 constraint (one active prebook per company+user)
```

---

## Summary

The DOTW v1.0 B2B module is a **modular, multi-tenant hotel booking integration** with the following key characteristics:

1. **Credential Security:** Per-company encrypted credentials with automatic decryption via Eloquent accessors
2. **Rate Locking:** 3-minute allocation windows with BLOCK-08 single-active-prebook-per-user enforcement
3. **Audit Trail:** Append-only operation logs linked to WhatsApp conversations via message IDs
4. **Caching:** 2.5-minute search result cache with per-company isolation
5. **Circuit Breaker:** Graceful degradation under repeated DOTW API failures
6. **Error Handling:** Structured, action-oriented error responses for N8N workflow automation
7. **Modular Design:** No tight coupling to invoice/task systems; can be deployed independently (MOD-06)

**Database:** 5 tables + 1 enhanced (6 total), 2 FKs, all migrations idempotent
**GraphQL Operations:** 4 (searchHotels, getRoomRates, blockRates, createPreBooking)
**State Management:** Prebook lifecycle (created → active → expired → cleanup)
**Compliance:** Full audit trail, sanitized payloads, PCI-DSS ready

---

*Document Generated: 2026-02-21*
*DOTW v1.0 B2B Milestone - COMPLETE*
*All 8 phases, 54 requirements mapped and implemented*
