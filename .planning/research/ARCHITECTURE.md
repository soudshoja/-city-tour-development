# Architecture Patterns: DOTW AI Module Integration

**Domain:** Hotel booking module integration into existing Laravel 11 multi-tenant travel platform
**Researched:** 2026-03-24
**Overall confidence:** HIGH (based on direct codebase analysis, not external sources)

---

## 1. Existing Architecture Inventory

Before defining the new module, here is what already exists and must NOT be modified.

### 1.1 Existing DOTW Components (Certification Layer)

These components were built during DOTW V4 certification (Phases 1-16). They are **granular API wrappers** -- one GraphQL resolver per DOTW XML command. They are NOT booking-flow orchestrators.

| Component | Location | Purpose | Status |
|-----------|----------|---------|--------|
| `DotwService` | `app/Services/DotwService.php` (2,232 lines) | Raw DOTW V4 XML API wrapper with per-company credential resolution | KEEP, DO NOT MODIFY |
| `DotwAuditService` | `app/Services/DotwAuditService.php` | Sanitized audit logging for all DOTW operations | KEEP, DO NOT MODIFY |
| 13 GraphQL Queries | `app/GraphQL/Queries/Dotw*.php` | One query per API command (searchHotels, getRoomRates, getCities, getBookingDetails, etc.) | KEEP, DO NOT MODIFY |
| 6 GraphQL Mutations | `app/GraphQL/Mutations/Dotw*.php` | blockRates, saveBooking, bookItinerary, cancelBooking, deleteItinerary, createPreBooking | KEEP, DO NOT MODIFY |
| `dotw.graphql` | `graphql/dotw.graphql` (~950 lines) | Complete DOTW schema with DotwMeta, DotwError, DotwErrorCode enums, response envelopes | KEEP, DO NOT MODIFY |
| `DotwPrebook` | `app/Models/DotwPrebook.php` | Rate allocation records with 3-min expiry, per-company+WhatsApp user scoping | KEEP, DO NOT MODIFY |
| `DotwRoom` | `app/Models/DotwRoom.php` | Room occupancy details per prebook | KEEP, DO NOT MODIFY |
| `DotwBooking` | `app/Models/DotwBooking.php` | Immutable booking confirmation records (append-only) | KEEP, DO NOT MODIFY |
| `DotwAuditLog` | `app/Models/DotwAuditLog.php` | Append-only audit trail | KEEP, DO NOT MODIFY |
| `CompanyDotwCredential` | `app/Models/CompanyDotwCredential.php` | Per-company encrypted DOTW credentials with markup_percent | KEEP, DO NOT MODIFY |
| `config/dotw.php` | `config/dotw.php` | API config, endpoints, rate basis codes, cache TTL | KEEP, DO NOT MODIFY |

### 1.2 Existing Non-DOTW Components the Module Integrates With

| Component | Location | How Module Uses It |
|-----------|----------|-------------------|
| `HotelBooking` | `app/Models/HotelBooking.php` | Shared booking record across suppliers (TBO, Magic, DOTW) |
| `Task` | `app/Models/Task.php` | After booking confirmation, create a hotel task (type=hotel) |
| `Invoice` / `InvoiceDetail` | `app/Models/Invoice*.php` | B2C: auto-generate invoice after booking |
| `JournalEntry` | `app/Models/JournalEntry.php` | Accounting journal entries for money movement |
| `Credit` | `app/Models/Credit.php` | B2B credit line tracking (types: Invoice, Topup, Refund) |
| `PaymentApplicationService` | `app/Services/PaymentApplicationService.php` | Apply credits/payments to invoices (full/partial/split modes) |
| Payment Gateways | `app/Support/PaymentGateway/{MyFatoorah,Knet,Hesabe,Tap,UPayment}.php` | B2C payment link generation |
| `Company` / `Branch` / `Agent` | `app/Models/Company.php` etc. | Multi-tenant hierarchy |
| `MapHotel` / `MapCity` / `MapCountry` | `app/Models/Map*.php` (mysql_map connection) | Hotel static data in map_data_citytour database |
| WhatsApp Hotel Routes | `routes/api.php` lines 112-139 | Existing TBO/Magic WhatsApp booking endpoints |

### 1.3 Existing Module Pattern: ResailAI

The codebase has one existing module at `app/Modules/ResailAI/`. This establishes the pattern:

```
app/Modules/ResailAI/
  Providers/ResailAIServiceProvider.php   -- registers config + routes
  Config/resailai.php                     -- module config
  Routes/routes.php                       -- module REST routes
  Services/TaskWebhookBridge.php          -- bridges to existing Task system
  Services/ProcessingAdapter.php          -- adapts module logic to existing services
  Http/Controllers/CallbackController.php -- n8n callback endpoint
  Jobs/ProcessDocumentJob.php             -- async processing
  Middleware/VerifyResailAIToken.php       -- auth middleware
  composer.json                           -- PSR-4 autoload declaration
```

Key observations:
- Namespace: `App\Modules\ResailAI\` auto-resolved via PSR-4 (`App\` -> `app/`)
- Provider registered via `bootstrap/providers.php` or route inclusion
- Uses **bridge/adapter pattern** to connect to existing models without modifying them
- Has its own middleware for authentication

---

## 2. Recommended Architecture: DOTW AI Module

### 2.1 Core Principle: Orchestration Layer Over Certification Layer

The existing DOTW code is a **certification layer** -- one resolver per API command, designed for n8n to call directly. The new DotwAI module is a **booking orchestration layer** -- it chains multiple certification-layer calls into complete business flows (search-to-book, book-to-invoice, cancel-with-refund).

```
                    n8n AI Agent (WhatsApp)
                          |
                   GraphQL / REST
                          |
              +-----------+-----------+
              |                       |
    DotwAI Module (NEW)     Existing DOTW Resolvers
    (orchestration)          (certification layer)
              |                       |
              +-----+---------+-------+
                    |         |
              DotwService   DotwPrebook/DotwBooking
              (XML API)      (existing models)
                    |
              DOTW V4 XML API
```

The module delegates to the existing `DotwService` through a delegation pattern that wraps it without modification.

### 2.2 Module Directory Structure

```
app/Modules/DotwAI/
|
+-- Providers/
|   +-- DotwAIServiceProvider.php        -- Registers config, routes, migrations, scheduler
|
+-- Config/
|   +-- dotwai.php                       -- Module-specific config (B2B/B2C settings, markup, reminders)
|
+-- Routes/
|   +-- api.php                          -- REST endpoints for n8n
|
+-- Services/
|   +-- DotwAISearchService.php          -- Orchestrates search flow (city resolve -> search -> browse -> block)
|   +-- DotwAIBookingService.php         -- Orchestrates booking flow (prebook -> payment -> confirm -> task)
|   +-- DotwAICancellationService.php    -- Orchestrates cancel flow (check -> confirm -> refund -> journal)
|   +-- DotwAIAccountingBridge.php       -- Creates Task, Invoice, JournalEntry from booking data
|   +-- DotwAIPaymentBridge.php          -- Generates payment links, handles webhook results
|   +-- DotwAICreditService.php          -- B2B credit line operations (check balance, apply, deduct)
|   +-- HotelCacheService.php            -- Hotel static data sync (DOTW -> MapHotel)
|   +-- ReminderService.php              -- Cancellation deadline reminder logic
|   +-- MessageBuilderService.php        -- Pure functions for WhatsApp message formatting
|
+-- Http/
|   +-- Controllers/
|   |   +-- BookingController.php        -- Confirm/cancel booking endpoints
|   |   +-- PaymentWebhookController.php -- Handles payment gateway callbacks for B2C
|   |   +-- StatusController.php         -- Booking status check endpoint
|   +-- Middleware/
|       +-- ResolveCompanyFromPhone.php  -- Maps agent phone -> company_id for multi-tenant isolation
|
+-- GraphQL/
|   +-- Queries/
|   |   +-- SearchDotwHotelRooms.php     -- The unified search query from skill spec
|   +-- Mutations/
|       +-- ConfirmDotwBooking.php       -- B2C booking with payment flow (if GraphQL preferred)
|       +-- CancelDotwBooking.php        -- 2-step cancellation with charge check
|
+-- Models/
|   +-- DotwAIBooking.php               -- Extended booking state (B2B/B2C track, lifecycle, deadline)
|   +-- DotwAIReminder.php              -- Cancellation reminder tracking
|   +-- DotwStaticCity.php              -- Cached DOTW city codes for fuzzy resolution
|   +-- DotwStaticCountry.php           -- Cached DOTW country codes for nationality resolution
|   +-- DotwStaticCurrency.php          -- Cached DOTW currency codes
|   +-- DotwStaticSalutation.php        -- Cached DOTW salutation IDs
|
+-- Jobs/
|   +-- SyncDotwStaticDataJob.php       -- Weekly sync of cities/countries/currencies/salutations
|   +-- SyncDotwHotelCacheJob.php       -- Hotel static data sync to MapHotel
|   +-- SendCancellationReminderJob.php -- Dispatched by scheduler, sends WhatsApp reminders
|   +-- AutoInvoiceDeadlineJob.php      -- Auto-creates invoice when cancellation deadline passes
|   +-- CleanupExpiredPrebooks.php      -- Prebook cleanup (hourly)
|   +-- ConfirmBookingAfterPaymentJob.php -- Queued DOTW confirmation (avoids webhook timeout)
|
+-- Events/
|   +-- DotwBookingConfirmed.php        -- Fired after successful DOTW confirmation
|   +-- DotwBookingCancelled.php        -- Fired after successful DOTW cancellation
|   +-- DotwPaymentReceived.php         -- Fired when payment webhook confirms payment
|
+-- Listeners/
|   +-- CreateTaskFromBooking.php       -- Listens to DotwBookingConfirmed, creates Task
|   +-- CreateInvoiceFromBooking.php    -- Listens to DotwBookingConfirmed, creates Invoice
|   +-- CreateJournalEntries.php        -- Listens to DotwBookingConfirmed/Cancelled, creates JournalEntry
|   +-- SendBookingVoucher.php          -- Listens to DotwBookingConfirmed, sends WhatsApp voucher
|   +-- ProcessCancellationRefund.php   -- Listens to DotwBookingCancelled, creates Credit/Refund
|
+-- Notifications/
|   +-- CancellationReminderNotification.php  -- WhatsApp notification for upcoming deadline
|   +-- BookingConfirmationNotification.php   -- WhatsApp notification with voucher
|
+-- Database/
|   +-- Migrations/
|       +-- create_dotwai_bookings_table.php
|       +-- create_dotwai_reminders_table.php
|       +-- create_dotw_static_cities_table.php
|       +-- create_dotw_static_countries_table.php
|       +-- create_dotw_static_currencies_table.php
|       +-- create_dotw_static_salutations_table.php
|       +-- create_dotw_hotel_map_table.php
|
+-- composer.json                        -- PSR-4 autoload declaration
```

### 2.3 Why This Structure

| Decision | Rationale |
|----------|-----------|
| Own `Services/` instead of modifying existing | Module isolation constraint -- existing code must not be touched |
| Bridge pattern for accounting | `DotwAIAccountingBridge` knows how to create Task/Invoice/JournalEntry without modifying those models |
| Separate models for static data | Existing `DotwCity` type in dotw.graphql is a response type, not a model. Module needs real Eloquent models with fuzzy search |
| Events + Listeners over direct calls | Decouples booking confirmation from side effects (task, invoice, journal, WhatsApp). Each listener is independently testable |
| Jobs for async work | Reminders, hotel cache sync, static data sync are all long-running -- should not block request lifecycle |
| Own GraphQL schema file | Lighthouse imports via `#import` directive in schema.graphql -- module adds its own file without modifying dotw.graphql |
| Queued DOTW confirmation | Payment webhooks have 5-10s timeout; DOTW API takes up to 25s. Queueing prevents double-booking from webhook retries |

---

## 3. Component Boundaries and Data Flow

### 3.1 Component Communication Map

```
+------------------+     delegates to      +---------------------+
| DotwAI Search    |--------------------->| DotwService          |
| Service          |                       | (existing, unmodified)|
+------------------+                       +---------------------+
        |                                          |
        | stores prebook                           | XML API calls
        v                                          v
+------------------+                       +---------------------+
| DotwPrebook      |                       | DOTW V4 XML API     |
| (existing model) |                       +---------------------+
+------------------+
        |
        | on confirmation
        v
+------------------+     fires event      +---------------------+
| DotwAI Booking   |--------------------->| DotwBookingConfirmed |
| Service          |                       +---------------------+
+------------------+                               |
        |                                          | listeners
        v                                          v
+------------------+               +-----------------------------------+
| DotwAIBooking    |               | CreateTaskFromBooking             |
| (module model)   |               | CreateInvoiceFromBooking          |
+------------------+               | CreateJournalEntries              |
                                   | SendBookingVoucher                |
                                   +-----------------------------------+
                                           |         |         |
                                           v         v         v
                                       Task      Invoice   JournalEntry
                                    (existing)  (existing)  (existing)
```

### 3.2 Detailed Data Flow: B2C Booking (Full Path)

```
1. WhatsApp message -> n8n AI Agent
2. n8n -> GraphQL: searchDotwHotelRooms(input)
3. DotwAI SearchService:
   a. Resolve city name -> DOTW code (DotwStaticCity, fuzzy match)
   b. Resolve nationality -> DOTW code (DotwStaticCountry, fuzzy match)
   c. Call DotwService::searchHotels()
   d. Filter results by hotel name / star rating / price
   e. For top match: call DotwService::getRoomsBrowse()
   f. Pick best rates per room type
   g. Call DotwService::getRoomsBlock() (locks rate for 3 min)
   h. Create DotwPrebook + DotwRoom records
   i. Apply markup for B2C
   j. Return formatted response with prebookKey
4. n8n -> REST: POST /api/dotwai/booking/confirm { prebookKey, guest details }
5. DotwAI BookingService:
   a. Load DotwPrebook by prebookKey, validate not expired
   b. Create DotwAIBooking with status=pending_payment, track=b2c
   c. Generate payment link via PaymentBridge (MyFatoorah/KNET/Tap)
   d. Return payment link to n8n -> WhatsApp user
6. Customer pays via payment link
7. Payment gateway webhook -> PaymentWebhookController (module)
8. Module dispatches ConfirmBookingAfterPaymentJob (queued)
9. ConfirmBookingAfterPaymentJob:
   a. Verify payment success
   b. Build passenger details, sanitize names
   c. Handle changed occupancy if present
   d. Call DotwService::confirmBooking() with original_total_fare
   e. Verify DOTW response success
   f. Store confirmation_no, payment_guaranteed_by
   g. Update DotwAIBooking status=confirmed
   h. Fire DotwBookingConfirmed event
10. Listeners fire:
   a. CreateTaskFromBooking -> creates Task (type=hotel, status=issued)
   b. CreateInvoiceFromBooking -> creates Invoice + InvoiceDetail
   c. CreateJournalEntries -> creates JournalEntry (debit receivable, credit revenue)
   d. SendBookingVoucher -> sends WhatsApp message with voucher details
```

### 3.3 Detailed Data Flow: B2B Booking (Credit Line)

```
1-3. Same as B2C steps 1-3 (search, but with 0% markup)
4. n8n -> REST: POST /api/dotwai/booking/confirm { prebookKey, agent details }
5. DotwAI BookingService:
   a. Load DotwPrebook, verify agent's company has credit
   b. Check: company credit balance >= booking total
   c. If sufficient credit:
      - Call DotwService::confirmBooking() immediately (no payment step)
      - Deduct credit via CreditService
      - Create DotwAIBooking with status=confirmed, track=b2b
      - Fire DotwBookingConfirmed event
   d. If insufficient credit:
      - Fall back to gateway payment (same as B2C step 5c-d)
      - Create DotwAIBooking with status=pending_payment, track=b2b_gateway
```

### 3.4 Detailed Data Flow: Cancellation

```
1. n8n -> REST: POST /api/dotwai/booking/cancel { prebookKey, confirm: false }
2. DotwAI CancellationService:
   a. Load DotwAIBooking, verify is_apr=false (APR cannot cancel)
   b. Check cancelRestricted in cancellation_rules for current date
   c. Call DotwService::cancelBookingCheck() with confirmation_no
   d. Extract <charge> amount (NOT <formatted>)
   e. Return charge to n8n -> agent confirms via WhatsApp
3. n8n -> REST: POST /api/dotwai/booking/cancel { prebookKey, confirm: true }
4. DotwAI CancellationService:
   a. Call DotwService::cancelBookingConfirm() with charge as penaltyApplied
   b. Update DotwAIBooking status=cancelled
   c. Fire DotwBookingCancelled event
5. Listeners:
   a. ProcessCancellationRefund -> creates Credit (type=Refund) if penalty < total
   b. CreateJournalEntries -> reversal journal entry
   c. Update Task status -> cancelled/refunded
```

---

## 4. Wrapping DotwService Without Modification

The critical constraint is that `DotwService.php` must not be modified. The module wraps it through **delegation**.

### 4.1 Delegation Pattern (NOT Inheritance, NOT Decoration)

```php
namespace App\Modules\DotwAI\Services;

use App\Services\DotwService;

class DotwAISearchService
{
    /**
     * Use the existing DotwService as a delegate.
     * Construct with company_id to get per-company credentials.
     */
    public function search(array $input, int $companyId): array
    {
        // Module-level orchestration logic
        $cityCode = $this->resolveCityCode($input['city']);
        $nationalityCode = $this->resolveCountryCode($input['guestNationality']);

        // Delegate to existing service (pass company_id for credential resolution)
        $dotwService = new DotwService($companyId);
        $searchResult = $dotwService->searchHotels($cityCode, ...);

        // Module continues with browse -> block -> store prebook
        // ...
    }
}
```

### 4.2 Why Delegation Over Other Patterns

| Pattern | Problem |
|---------|---------|
| Inheritance (`extends DotwService`) | DotwService has 2,232 lines with internal state. Extending creates tight coupling and risks breaking certification behavior |
| Decorator (`implements DotwServiceInterface`) | DotwService has no interface. Creating one would require modifying DotwService |
| Service replacement (rebind in container) | Would break existing GraphQL resolvers that depend on current DotwService behavior |
| **Delegation (recommended)** | Module creates DotwService instances as needed, calls public methods, adds orchestration around them |

### 4.3 DotwService Public API Available to Module

From analyzing the existing code, these public methods are available:

```php
// Search
searchHotels(string $cityCode, string $fromDate, string $toDate, string $currency, array $rooms): ?SimpleXMLElement
searchHotelsByIds(array $hotelIds, string $fromDate, string $toDate, string $currency, array $rooms): ?SimpleXMLElement

// Room rates
getRoomsBrowse(string $hotelId, string $fromDate, string $toDate, string $currency, array $rooms): ?SimpleXMLElement
getRoomsBlock(string $hotelId, string $fromDate, string $toDate, string $currency, array $roomSelections): ?SimpleXMLElement

// Booking
confirmBooking(array $params): ?SimpleXMLElement

// Cancellation (2-step)
cancelBookingCheck(string $bookingCode): ?SimpleXMLElement
cancelBookingConfirm(string $bookingCode, string $serviceCode, string $charge): ?SimpleXMLElement

// Static utility
static sanitizePassengerName(string $name): string
```

The module calls these methods exactly as they are. Constructor accepts `?int $companyId` for per-company credential resolution -- this is already built.

---

## 5. GraphQL Schema Composition

### 5.1 Approach: Separate Schema File with #import

Lighthouse supports `#import` directives in the root `schema.graphql`. The existing pattern:

```graphql
# schema.graphql (line 1)
#import dotw.graphql
```

The module adds its own schema file:

```graphql
# schema.graphql (updated -- the ONLY modification to existing code)
#import dotw.graphql
#import dotwai.graphql
```

This is the ONLY modification to an existing file -- adding one `#import` line. All types and resolvers live in the module's own schema file.

### 5.2 Why Separate File Instead of Extending dotw.graphql

| Option | Risk |
|--------|------|
| Add types to `dotw.graphql` | Violates module isolation. Certification schema should remain frozen |
| New file `dotwai.graphql` in `graphql/` | Clean separation. Module owns its types. Only 1 line added to existing code |
| Module-owned file imported by ServiceProvider | Lighthouse does not support dynamic schema loading from providers. Must be file-based import |

### 5.3 Schema Design: Module Types

The module's GraphQL schema defines the **orchestrated** booking flow types, distinct from the granular certification types:

```graphql
# graphql/dotwai.graphql

# --- Module Input Types ---

input DotwAIHotelSearchInput {
  telephone: String!          # Agent phone -> company resolution
  city: String!               # Natural language city name (fuzzy resolved)
  hotel: String               # Optional hotel name filter
  guestNationality: String!   # Natural language (fuzzy resolved)
  checkIn: String!
  checkOut: String!
  occupancy: [DotwAIOccupancyInput!]!
  bookingType: String!        # "b2b" or "b2c"
  # Filters
  refundable: Boolean
  mealType: String
  priceMin: Float
  priceMax: Float
  starRating: Int
}

input DotwAIOccupancyInput {
  adults: Int!
  childrenAges: [Int!]!
}

# --- Module Response Types ---

type DotwAISearchResult {
  success: Boolean!
  status: String!             # hotel_found | multiple_hotels_found | no_results | error
  message: String
  hotelOptions: [DotwAIHotelOption]
  data: DotwAIHotelData
}

# ... (full types per SKILL.md spec)

# --- Module Queries ---

extend type Query {
  searchDotwHotelRooms(input: DotwAIHotelSearchInput!): DotwAISearchResult!
    @field(resolver: "App\\Modules\\DotwAI\\GraphQL\\Queries\\SearchDotwHotelRooms")
}
```

Important: Module types are prefixed with `DotwAI` to avoid collision with existing `Dotw` types in `dotw.graphql`.

### 5.4 n8n Interface: GraphQL vs REST

Per the skill spec, n8n needs 2-3 tools:

| Tool | Protocol | Why |
|------|----------|-----|
| Search Hotels | **GraphQL** | Complex input/output structure, variable query shape, benefits from GraphQL's type system |
| Confirm Booking | **REST** | Simple input (prebookKey + guest details), webhook-friendly, matches existing WhatsApp hotel patterns |
| Cancel Booking | **REST** | Simple input, 2-step flow needs clear request/response semantics |
| Booking Status | **REST** | Simple key->status lookup |

REST endpoints go under a new prefix to avoid polluting existing routes:

```php
// Module routes (app/Modules/DotwAI/Routes/api.php)
Route::prefix('api/dotwai')->group(function () {
    Route::post('/booking/confirm', [BookingController::class, 'confirm']);
    Route::post('/booking/cancel', [BookingController::class, 'cancel']);
    Route::get('/booking/status/{prebookKey}', [StatusController::class, 'show']);
    Route::post('/payment/webhook/{gateway}', [PaymentWebhookController::class, 'handle'])
        ->name('dotwai.payment.webhook');
});
```

---

## 6. Database Design: B2B/B2C Booking State

### 6.1 New Table: dotwai_bookings

This is the module's **lifecycle tracking** table. It differs from `dotw_bookings` (which is an immutable confirmation record from the certification layer).

```
dotwai_bookings
+-- id                          BIGINT PK
+-- prebook_key                 VARCHAR UNIQUE INDEX   -- FK-like to dotw_prebooks.prebook_key
+-- dotw_booking_id             BIGINT NULLABLE        -- FK to dotw_bookings.id (after confirmation)
+-- hotel_booking_id            BIGINT NULLABLE        -- FK to hotel_bookings.id (shared model)
+-- track                       ENUM('b2b','b2c','b2b_gateway')
+-- status                      VARCHAR                -- pending | pending_payment | confirmed | cancelled | failed | expired
+-- company_id                  BIGINT INDEX           -- Company that created booking
+-- agent_phone                 VARCHAR                -- Agent WhatsApp number
+-- client_phone                VARCHAR NULLABLE        -- End customer phone (B2C)
+-- client_email                VARCHAR NULLABLE        -- End customer email
+-- hotel_id                    VARCHAR                -- DOTW hotel productId
+-- hotel_name                  VARCHAR
+-- city_code                   VARCHAR
+-- check_in                    DATE
+-- check_out                   DATE
+-- original_total_fare         DECIMAL(12,3)          -- DOTW price (for booking API)
+-- original_currency           VARCHAR(10)
+-- display_total_fare          DECIMAL(12,3)          -- Customer-facing price
+-- display_currency            VARCHAR(10)
+-- markup_percentage           DECIMAL(5,2)           -- 0 for B2B, 20 for B2C
+-- cancellation_deadline       DATETIME NULLABLE      -- Earliest non-free cancellation date
+-- is_refundable               BOOLEAN
+-- is_apr                      BOOLEAN
+-- confirmation_no             VARCHAR NULLABLE        -- DOTW bookingCode
+-- payment_id                  BIGINT NULLABLE         -- FK to payments table
+-- payment_link                TEXT NULLABLE           -- Generated payment URL
+-- payment_status              VARCHAR NULLABLE        -- null | pending | paid | refunded
+-- task_id                     BIGINT NULLABLE         -- FK to tasks.id (after task creation)
+-- invoice_id                  BIGINT NULLABLE         -- FK to invoices.id (after invoicing)
+-- voucher_sent_at             DATETIME NULLABLE       -- When WhatsApp voucher was sent
+-- cancelled_at                DATETIME NULLABLE
+-- cancellation_charge         DECIMAL(12,3) NULLABLE
+-- guest_details               JSON                   -- Passenger names, salutations
+-- created_at                  TIMESTAMP
+-- updated_at                  TIMESTAMP
```

### 6.2 New Table: dotwai_reminders

```
dotwai_reminders
+-- id                          BIGINT PK
+-- dotwai_booking_id           BIGINT FK -> dotwai_bookings.id
+-- type                        ENUM('3_day','2_day','1_day','deadline_passed')
+-- scheduled_at                DATETIME INDEX         -- When to send
+-- sent_at                     DATETIME NULLABLE       -- When actually sent (null = pending)
+-- channel                     VARCHAR DEFAULT 'whatsapp'
+-- created_at                  TIMESTAMP
```

### 6.3 Static Data Tables (Module-Owned)

These provide proper cached tables for fuzzy city/country resolution:

```
dotw_static_cities
+-- id              BIGINT PK
+-- code            VARCHAR UNIQUE     -- DOTW city code
+-- name            VARCHAR INDEX      -- City name
+-- country_code    VARCHAR            -- DOTW country code
+-- updated_at      TIMESTAMP

dotw_static_countries
+-- id              BIGINT PK
+-- code            VARCHAR UNIQUE     -- DOTW country code
+-- name            VARCHAR INDEX      -- Country name
+-- nationality_name VARCHAR INDEX     -- "Kuwaiti", "Emirati", etc.
+-- updated_at      TIMESTAMP

dotw_static_currencies
+-- id              BIGINT PK
+-- code            VARCHAR UNIQUE     -- DOTW currency code
+-- symbol          VARCHAR            -- "KWD", "USD", etc.
+-- updated_at      TIMESTAMP

dotw_static_salutations
+-- id              BIGINT PK
+-- dotw_id         INT UNIQUE         -- DOTW salutation ID (1=Mr, 2=Mrs, etc.)
+-- label           VARCHAR            -- Display label
+-- updated_at      TIMESTAMP
```

### 6.4 Hotel Cache Mapping Table

```
dotw_hotel_map
+-- id              BIGINT PK
+-- dotw_hotel_id   VARCHAR UNIQUE     -- DOTW productId
+-- map_hotel_id    BIGINT NULLABLE    -- FK to map_data_citytour.hotels.id
+-- hotel_name      VARCHAR            -- Cached DOTW hotel name
+-- city_code       VARCHAR
+-- star_rating     INT NULLABLE
+-- created_at      TIMESTAMP
+-- updated_at      TIMESTAMP
```

Lives in the primary database, maps between DOTW and MapHotel without modifying `MapHotel`.

### 6.5 Relationship to Existing Tables

```
dotwai_bookings.prebook_key ----> dotw_prebooks.prebook_key    (soft FK, same DB)
dotwai_bookings.dotw_booking_id -> dotw_bookings.id             (soft FK, same DB)
dotwai_bookings.hotel_booking_id -> hotel_bookings.id           (soft FK, same DB)
dotwai_bookings.task_id ---------> tasks.id                     (soft FK, same DB)
dotwai_bookings.invoice_id ------> invoices.id                  (soft FK, same DB)
dotwai_bookings.company_id ------> companies.id                 (soft FK, same DB)
```

All foreign keys are "soft" (nullable, no DB constraint) to maintain module isolation. The module never adds constraints to existing tables.

---

## 7. Payment Flow Integration

### 7.1 B2C Payment Flow

```
DotwAI BookingService
    |
    | generate payment link
    v
DotwAIPaymentBridge
    |
    | delegates to existing gateway
    v
app/Support/PaymentGateway/MyFatoorah.php  (or Tap/KNET)
    |
    | returns payment URL
    v
n8n sends URL to customer via WhatsApp
    |
    | customer pays
    v
Payment gateway webhook -> PaymentWebhookController (module)
    |
    | dispatches ConfirmBookingAfterPaymentJob (queued)
    | returns 200 immediately to gateway
    v
ConfirmBookingAfterPaymentJob (queued)
    |
    | calls DotwService::confirmBooking()
    v
Fire DotwBookingConfirmed event
```

### 7.2 Payment Bridge Design

The bridge does NOT modify existing `PaymentController`. It creates payment sessions using the existing gateway classes directly:

```php
namespace App\Modules\DotwAI\Services;

use App\Support\PaymentGateway\MyFatoorah;

class DotwAIPaymentBridge
{
    public function createPaymentLink(DotwAIBooking $booking): string
    {
        $gateway = new MyFatoorah(); // or resolve from config
        return $gateway->createPaymentLink([
            'amount' => $booking->display_total_fare,
            'currency' => $booking->display_currency,
            'reference' => 'DOTWAI-' . $booking->id,
            'callback_url' => route('dotwai.payment.webhook', ['gateway' => 'myfatoorah']),
        ]);
    }
}
```

### 7.3 Webhook Handling

The module registers its own webhook endpoint. When payment gateways call back, the module's controller handles the DOTW-specific flow. Critical: DOTW confirmation is **queued** (not synchronous) because DOTW API takes up to 25 seconds while payment webhooks expect responses in 5-10 seconds.

```
POST /api/dotwai/payment/webhook/{gateway}
```

This avoids adding DOTW-specific logic to the existing `PaymentController`.

---

## 8. Scheduler Integration

### 8.1 Jobs to Schedule

| Job | Frequency | Purpose |
|-----|-----------|---------|
| `SendCancellationReminderJob` | Every 15 minutes | Check dotwai_reminders for due reminders, send WhatsApp messages |
| `AutoInvoiceDeadlineJob` | Every 15 minutes | Refundable bookings past cancellation deadline -> auto-create invoice |
| `SyncDotwStaticDataJob` | Weekly (Sunday 02:00) | Download cities/countries/currencies/salutations from DOTW API |
| `SyncDotwHotelCacheJob` | Weekly (Monday 03:00) | Sync DOTW hotel catalog to dotw_hotel_map table |
| `CleanupExpiredPrebooks` | Hourly | Clean up module-specific expired state |

### 8.2 Registration

The `DotwAIServiceProvider` registers scheduled commands in its `boot()` method using `$this->app->booted()`:

```php
public function boot(): void
{
    $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

    $this->app->booted(function () {
        $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
        $schedule->job(new SendCancellationReminderJob)->everyFifteenMinutes()->withoutOverlapping();
        $schedule->job(new AutoInvoiceDeadlineJob)->everyFifteenMinutes()->withoutOverlapping();
        $schedule->job(new SyncDotwStaticDataJob)->weekly()->sundays()->at('02:00');
        $schedule->job(new SyncDotwHotelCacheJob)->weekly()->mondays()->at('03:00');
        $schedule->job(new CleanupExpiredPrebooks)->hourly();
    });
}
```

This approach adds scheduled tasks without modifying `app/Console/Kernel.php`.

---

## 9. Module Registration

### 9.1 ServiceProvider Registration

Follow ResailAI pattern. Register in `bootstrap/providers.php`:

```php
return [
    App\Providers\AIServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
    App\Modules\DotwAI\Providers\DotwAIServiceProvider::class,  // ADD THIS
];
```

### 9.2 GraphQL Schema Registration

Add one `#import` line to `graphql/schema.graphql`:

```graphql
#import dotw.graphql
#import dotwai.graphql
```

### 9.3 Module composer.json

```json
{
    "name": "citytravelers/dotwai-module",
    "description": "DOTW AI Hotel Booking Module for WhatsApp/n8n",
    "type": "library",
    "autoload": {
        "psr-4": {
            "App\\Modules\\DotwAI\\": ""
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "App\\Modules\\DotwAI\\Providers\\DotwAIServiceProvider"
            ]
        }
    }
}
```

---

## 10. Patterns to Follow

### Pattern 1: Service Layer Between Controller and External API

Controllers call module services, services call DotwService. Never call DotwService directly from a controller.

```php
// Good: Controller -> Module Service -> DotwService
class BookingController {
    public function confirm(Request $request, DotwAIBookingService $service) {
        return $service->confirmBooking($request->validated());
    }
}
```

### Pattern 2: Idempotent Webhook Handling

Payment webhook handler checks if booking is already confirmed before calling DOTW. DOTW confirmBooking is not idempotent -- calling it twice could create duplicate bookings.

```php
if ($booking->confirmation_no) {
    return ['already_booked' => true, 'confirmation_no' => $booking->confirmation_no];
}
```

### Pattern 3: Event-Driven Side Effects

Booking confirmation fires an event. Side effects (task, invoice, journal, voucher) are handled by independent listeners. Each listener has its own try/catch so one failure does not block others.

### Pattern 4: Message Builder as Pure Functions

WhatsApp message formatting in a dedicated service with no dependencies, no state, no database access. Testable in isolation.

---

## 11. Anti-Patterns to Avoid

### Anti-Pattern 1: Modifying Existing DOTW Resolvers
**What:** Editing `app/GraphQL/Queries/DotwSearchHotels.php` to add B2B/B2C logic
**Why bad:** Breaks existing n8n workflows and certification test behavior
**Instead:** New resolvers with distinct names (`searchDotwHotelRooms`)

### Anti-Pattern 2: Storing Business State in n8n
**What:** Having n8n remember booking state, deadlines, or payment status
**Why bad:** n8n workflows are stateless between executions
**Instead:** All durable state lives in `dotwai_bookings`

### Anti-Pattern 3: Synchronous DOTW Calls in Webhook Handler
**What:** Calling DotwService::confirmBooking() synchronously inside payment webhook
**Why bad:** DOTW API takes up to 25s. Webhooks expect 5-10s. Timeout = retry = double booking
**Instead:** Queue the DOTW confirmation as a job, return 200 immediately

### Anti-Pattern 4: Adding Columns to Existing Tables
**What:** ALTER TABLE dotw_prebooks ADD booking_type, cancellation_deadline, etc.
**Why bad:** Breaks module isolation, risks regressions in certification layer
**Instead:** Separate `dotwai_bookings` table with soft FKs

### Anti-Pattern 5: Tight Coupling to Payment Gateway Internals
**What:** Calling MyFatoorah internal methods or relying on its database schema
**Why bad:** Payment gateways may change
**Instead:** Use DotwAIPaymentBridge as abstraction layer

---

## 12. Scalability Considerations

| Concern | At 100 bookings/day | At 1,000/day | At 10,000/day |
|---------|---------------------|--------------|---------------|
| DOTW API calls | Direct per-request | Cache search results (2.5 min TTL already configured) | Queue search requests, serve from cache |
| Static data resolution | In-memory Levenshtein | Database indexed LIKE queries | Redis-cached city/country lookup |
| Prebook cleanup | Hourly job | Every 15 min | Table partitioning by date |
| Cancellation reminders | Scheduled job scan | Index on scheduled_at + sent_at | Queue-based with Redis sorted sets |
| Hotel cache sync | Weekly batch | Daily incremental | Event-driven with DOTW change notifications (if available) |
| Payment webhooks | Queued processing | Same | Same -- queue scales horizontally |

---

## 13. Build Order and Dependency Graph

### Phase Dependency Graph

```
                     [1. Foundation]
                      /     |     \
                     /      |      \
        [2. Static Data]  [3. Search]  (parallel after foundation)
                     \      |
                      \     |
                    [4. B2B Booking]
                          |
                    [5. B2C Payment]
                          |
                    [6. Accounting]
                     /          \
        [7. Cancellation]  [8. Reminders]
                     \          /
                    [9. Hotel Cache]
```

### Suggested Build Order

| Order | Phase | What to Build | Dependencies | Rationale |
|-------|-------|---------------|--------------|-----------|
| 1 | Foundation | ServiceProvider, config, module structure, DotwAIBooking model/migration, routes skeleton, GraphQL schema file with `#import` | None | Everything else depends on this |
| 2 | Static Data | DotwStaticCity/Country/Currency/Salutation models, migrations, SyncDotwStaticDataJob, fuzzy resolution service | Phase 1 | Search needs city/country code resolution |
| 3 | Search | DotwAISearchService, SearchDotwHotelRooms GraphQL query, ResolveCompanyFromPhone middleware | Phase 1, Phase 2 | Core flow, n8n's primary tool |
| 4 | B2B Booking | DotwAIBookingService (B2B track), BookingController confirm endpoint, DotwAICreditService, DotwBookingConfirmed event | Phase 3 | Simpler than B2C (no payment step) |
| 5 | B2C Payment | DotwAIPaymentBridge, PaymentWebhookController, payment link generation, ConfirmBookingAfterPaymentJob | Phase 4 | Adds payment layer on top of B2B flow |
| 6 | Accounting | DotwAIAccountingBridge, CreateTaskFromBooking listener, CreateInvoiceFromBooking listener, CreateJournalEntries listener | Phase 4 | Tasks and invoices created from confirmed bookings |
| 7 | Cancellation | DotwAICancellationService, cancel endpoint, ProcessCancellationRefund listener, 2-step cancel flow | Phase 6 | Needs accounting in place for refund journal entries |
| 8 | Reminders | ReminderService, DotwAIReminder model, SendCancellationReminderJob, AutoInvoiceDeadlineJob, scheduler registration | Phase 6 | Needs bookings and invoices to exist |
| 9 | Hotel Cache | HotelCacheService, SyncDotwHotelCacheJob, dotw_hotel_map table, weekly scheduler | Phase 2 | Nice-to-have, not blocking core flow |

### What Each Phase Creates (New) vs Modifies (Existing)

| Phase | New Files | Modified Files |
|-------|-----------|----------------|
| 1. Foundation | ~8 files (provider, config, model, migration, routes, graphql schema, composer.json) | 2 files: `graphql/schema.graphql` (add `#import dotwai.graphql`); `bootstrap/providers.php` (add DotwAIServiceProvider) |
| 2. Static Data | ~5 files (4 models + sync job + migrations) | 0 files |
| 3. Search | ~3 files (service, GraphQL query, middleware) | 0 files |
| 4. B2B Booking | ~5 files (service, controller, credit service, event, status controller) | 0 files |
| 5. B2C Payment | ~3 files (bridge, webhook controller, queued job) | 0 files |
| 6. Accounting | ~4 files (bridge, 3 listeners) | 0 files |
| 7. Cancellation | ~3 files (service, listener, event) | 0 files |
| 8. Reminders | ~5 files (service, model, migration, 2 jobs, notification) | 0 files |
| 9. Hotel Cache | ~3 files (service, job, migration) | 0 files |
| **TOTAL** | **~39 new files** | **2 modified files** |

---

## 14. Integration Points Summary

### Files the Module READS FROM (does not modify)

| Existing Component | What Module Reads |
|-------------------|-------------------|
| `DotwService` | Calls public API methods (search, getRooms, confirm, cancel) |
| `DotwPrebook` | Reads prebook records by prebook_key to get allocation details |
| `DotwBooking` | Reads booking records by confirmation_code for status checks |
| `CompanyDotwCredential` | Read indirectly via DotwService constructor (company_id resolution) |
| `config/dotw.php` | Reads rate_basis_codes, b2c_markup, allocation_expiry_minutes |
| `HotelBooking` | Reads for shared booking state |
| `Company` / `Agent` | Reads for multi-tenant resolution from phone number |
| `Credit` | Reads balance for B2B credit line checks |
| `MapHotel` | Reads for hotel metadata enrichment |

### Files the Module WRITES TO (existing tables, new rows only)

| Existing Table | What Module Creates |
|---------------|-------------------|
| `hotel_bookings` | New row per confirmed booking |
| `tasks` | New row per confirmed booking (type=hotel) |
| `invoices` + `invoice_details` | New rows for B2C invoices and B2B post-deadline invoices |
| `journal_entries` | New rows for booking/cancellation accounting |
| `credits` | New rows for B2B credit deductions and cancellation refunds |

### New Tables the Module Creates

| New Table | Purpose |
|-----------|---------|
| `dotwai_bookings` | Booking lifecycle tracking with B2B/B2C state |
| `dotwai_reminders` | Cancellation reminder scheduling |
| `dotw_static_cities` | Cached DOTW city codes for fuzzy resolution |
| `dotw_static_countries` | Cached DOTW country codes for nationality resolution |
| `dotw_static_currencies` | Cached DOTW currency codes |
| `dotw_static_salutations` | Cached DOTW salutation IDs |
| `dotw_hotel_map` | DOTW hotel ID to MapHotel mapping |

---

## 15. Configuration: config/dotwai.php

```php
return [
    // B2B settings
    'b2b' => [
        'enabled' => env('DOTWAI_B2B_ENABLED', true),
        'markup_percentage' => 0,
        'credit_required' => env('DOTWAI_B2B_CREDIT_REQUIRED', false),
        'fallback_to_gateway' => env('DOTWAI_B2B_FALLBACK_GATEWAY', true),
    ],

    // B2C settings
    'b2c' => [
        'enabled' => env('DOTWAI_B2C_ENABLED', true),
        'default_markup_percentage' => env('DOTWAI_B2C_MARKUP', 20),
        'default_gateway' => env('DOTWAI_B2C_GATEWAY', 'myfatoorah'),
    ],

    // Cancellation reminders
    'reminders' => [
        'days_before' => [3, 2, 1],
        'channel' => 'whatsapp',
    ],

    // Auto-invoice after deadline
    'auto_invoice' => [
        'enabled' => env('DOTWAI_AUTO_INVOICE', true),
        'hours_after_deadline' => 1,
    ],

    // Static data sync
    'static_sync' => [
        'schedule' => 'weekly',
        'day' => 'sunday',
        'time' => '02:00',
    ],

    // Hotel cache sync
    'hotel_cache' => [
        'enabled' => env('DOTWAI_HOTEL_CACHE_ENABLED', true),
        'batch_size' => 50,
        'schedule' => 'weekly',
    ],

    // Search behavior
    'search' => [
        'max_hotels_in_multiple_found' => 10,
        'fuzzy_match_threshold' => 3,  // Levenshtein distance
        'default_currency_code' => env('DOTWAI_CURRENCY', '520'),
        'default_nationality_code' => env('DOTWAI_NATIONALITY', '66'),
    ],

    // WhatsApp integration
    'whatsapp' => [
        'voucher_template' => 'dotw_booking_voucher',
        'reminder_template' => 'dotw_cancellation_reminder',
    ],
];
```

---

## 16. Confidence Assessment

| Area | Confidence | Reasoning |
|------|------------|-----------|
| Module structure | HIGH | Follows established ResailAI pattern in same codebase |
| DotwService wrapping | HIGH | Public API analyzed directly from source code, delegation pattern is straightforward |
| GraphQL composition | HIGH | Lighthouse `#import` directive already used for `dotw.graphql` |
| Database design | HIGH | Follows existing patterns (DotwPrebook, DotwBooking, HotelBooking) |
| Payment integration | MEDIUM | PaymentGateway classes reviewed but internal API for creating payment links needs verification during implementation |
| Accounting integration | MEDIUM | Invoice/JournalEntry/Credit models reviewed but exact required fields for creation need phase-specific research |
| Scheduler approach | HIGH | `$this->app->booted()` + Schedule injection is standard Laravel pattern |
| Hotel cache sync | MEDIUM | DOTW does not expose a hotel listing API directly; may require batch searchhotels calls -- verify during implementation |

---

## Sources

All findings based on direct codebase analysis of:
- `app/Services/DotwService.php` (2,232 lines)
- `app/Services/DotwAuditService.php`
- `app/GraphQL/Queries/Dotw*.php` (13 resolvers)
- `app/GraphQL/Mutations/Dotw*.php` (6 resolvers)
- `graphql/dotw.graphql` (~950 lines)
- `app/Models/DotwPrebook.php`, `DotwBooking.php`, `DotwRoom.php`, `DotwAuditLog.php`
- `app/Models/CompanyDotwCredential.php`
- `app/Models/HotelBooking.php`, `Task.php`, `Invoice.php`, `Credit.php`
- `app/Services/PaymentApplicationService.php`
- `app/Support/PaymentGateway/{MyFatoorah,Knet,Hesabe,Tap,UPayment}.php`
- `app/Modules/ResailAI/` (existing module pattern)
- `config/dotw.php`, `config/lighthouse.php`
- `graphql/schema.graphql`
- `routes/api.php`
- `bootstrap/providers.php`
- `composer.json`
- `.claude/skills/dotwai/SKILL.md` and all reference files
- `.planning/PROJECT.md`
