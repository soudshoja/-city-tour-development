# Technology Stack: DOTW AI Module

**Project:** DOTW AI Module (app/Modules/DotwAI/)
**Researched:** 2026-03-24
**Overall Confidence:** HIGH

---

## Executive Summary

The DOTW AI Module requires **zero new composer dependencies**. Every capability needed -- module structure, scheduled jobs, queue processing, WhatsApp messaging, payment link generation, GraphQL schema extension, and hotel data caching -- is already available through the existing Laravel 11 framework and installed packages. The work is architectural, not dependency-driven.

The key insight from examining the codebase: there is already a `ResailAI` module at `app/Modules/ResailAI/` that establishes the module pattern (ServiceProvider, Config, Routes, Services, Jobs). The DOTW AI module should follow this exact pattern rather than introducing `nwidart/laravel-modules` or any other module framework.

**Stack verdict: Use what exists. Add nothing. Wire it differently.**

---

## What Already Exists (DO NOT ADD)

These are validated, deployed, and working. The DOTW AI Module consumes them as-is.

| Capability | What Exists | Location |
|-----------|-------------|----------|
| DOTW V4 XML API | `DotwService.php` with all methods | `app/Services/DotwService.php` |
| DOTW search caching | `DotwCacheService.php` (2.5min TTL) | `app/Services/DotwCacheService.php` |
| DOTW circuit breaker | `DotwCircuitBreakerService.php` | `app/Services/DotwCircuitBreakerService.php` |
| DOTW audit logging | `DotwAuditService.php` | `app/Services/DotwAuditService.php` |
| DOTW prebook model | `DotwPrebook.php` with expiry logic | `app/Models/DotwPrebook.php` |
| DOTW booking model | `DotwBooking.php` (immutable record) | `app/Models/DotwBooking.php` |
| DOTW room model | `DotwRoom.php` | `app/Models/DotwRoom.php` |
| DOTW config | `config/dotw.php` (credentials, endpoints, cache) | `config/dotw.php` |
| DOTW GraphQL schema | `dotw.graphql` (searchHotels, getRoomRates, blockRates, confirmBooking, cancelBooking) | `graphql/dotw.graphql` |
| Company DOTW credentials | `CompanyDotwCredential.php` | `app/Models/CompanyDotwCredential.php` |
| Payment gateways | MyFatoorah, KNET, Hesabe, uPayment, Tap | `PaymentController.php` + gateway configs |
| Payment link generation | `route('payment.link.show', ...)` pattern | `WhatsAppHotelController.php` |
| WhatsApp messaging | `ResayilController::shareReminder()` | `app/Http/Controllers/ResayilController.php` |
| Reminder system | `Reminder` model + `process:reminder` command | `app/Models/Reminder.php`, `SendReminders.php` |
| Double-entry accounting | JournalEntry, Account, Invoice, Refund, Credit | Multiple models in `app/Models/` |
| Task management | Task model with 12 types including hotel | `app/Models/Task.php` |
| Queue system | Database driver, `api_sync` queue + default | `config/queue.php` |
| Scheduler | Console Kernel with existing jobs | `app/Console/Kernel.php` |
| GraphQL engine | Lighthouse v6.63.1 with `#import` support | `graphql/schema.graphql` |
| Module pattern | ResailAI module (ServiceProvider + Config + Routes) | `app/Modules/ResailAI/` |
| Hotel data sync | SyncHotelsJob, SyncCitiesJob, SyncCountriesJob | `app/Jobs/` |
| Expired offer cleanup | `app:delete-expired-offers` (15min expiry) | `DeleteExpiredHotelOffers.php` |
| Expired task processing | `tasks:process-expired-confirmed` | `ProcessExpiredConfirmedTasks.php` |

---

## What the Module Creates (New Code, Not New Dependencies)

### 1. Module Structure

**Pattern:** Follow existing `app/Modules/ResailAI/` exactly.

```
app/Modules/DotwAI/
  Providers/
    DotwAIServiceProvider.php          # Registers routes, config, commands, scheduler
  Config/
    dotwai.php                         # Module-specific config (B2B/B2C toggles, markup, reminder intervals)
  Routes/
    api.php                            # REST endpoints for n8n tools (confirm, cancel, status)
  Services/
    BookingLifecycleService.php        # Orchestrates: confirm -> payment -> DOTW book -> task -> invoice
    CancellationService.php            # 2-step DOTW cancel, charge calculation, refund logic
    ReminderSchedulerService.php       # Creates Reminder records for cancellation deadlines
    PaymentLinkService.php             # Wraps existing payment link generation for WhatsApp delivery
    HotelCacheSyncService.php          # DOTW static data sync (countries, cities, currencies, salutations)
  Jobs/
    SendCancellationReminder.php       # Queue job: sends WhatsApp reminder via ResayilController
    ProcessAutoInvoice.php             # Queue job: auto-invoice after cancellation deadline passes
    SyncDotwStaticData.php             # Queue job: weekly DOTW static data refresh
  Commands/
    ProcessCancellationReminders.php   # Artisan: checks deadlines, dispatches reminder jobs
    AutoInvoicePassedDeadlines.php     # Artisan: converts non-cancelled bookings to invoices
    SyncDotwStaticData.php             # Artisan: wrapper for the sync job
  Http/
    Controllers/
      DotwBookingController.php        # REST: confirm, cancel, status (n8n tools)
    Middleware/
      ValidateDotwRequest.php          # Validates prebook exists and is not expired
  GraphQL/
    Queries/
      SearchDotwHotelRooms.php         # Unified search query (n8n's primary tool)
```

**Why this structure:** The ResailAI module already proved this pattern works in this codebase. The ServiceProvider loads routes and config via `loadRoutesFrom()` and `mergeConfigFrom()`. No additional autoload config needed because `App\` PSR-4 root already covers `app/Modules/DotwAI/`.

**Why NOT nwidart/laravel-modules:** The project has a working lightweight module pattern. nwidart adds complexity (artisan generators, module activation/deactivation, separate migration tracking) that this single-module addition does not need. Adding a framework dependency for one module is over-engineering.

**Confidence:** HIGH -- verified from existing `app/Modules/ResailAI/Providers/ResailAIServiceProvider.php`.

### 2. Scheduled Job Framework (Cancellation Reminders)

**Pattern:** Use Laravel's built-in scheduler (`Console\Kernel::schedule()`), same as existing 12+ scheduled commands.

| Job | Schedule | What It Does |
|-----|----------|-------------|
| `dotwai:process-reminders` | `everyFiveMinutes()->withoutOverlapping()` | Finds bookings with cancellation deadlines in 3/2/1 days, dispatches WhatsApp reminders |
| `dotwai:auto-invoice` | `hourly()->withoutOverlapping()` | Finds refundable bookings past cancellation deadline that were not cancelled, creates invoice + journal entry |
| `dotwai:sync-static` | `weekly()->sundays()->at('03:00')` | Syncs DOTW countries/cities/currencies/salutations tables |
| `dotwai:cleanup-prebooks` | `daily()->at('04:00')` | Purges expired DotwPrebook records older than 24 hours |

**Implementation detail:** The reminder system should create `Reminder` model records (already exists in the codebase) with `target_type: 'hotel_cancellation'`. The existing `process:reminder` command already sends WhatsApp messages via `ResayilController::shareReminder()`. The DOTW AI module's reminder command creates the Reminder records; the existing command sends them. This avoids duplicating WhatsApp delivery logic.

**Why the existing Reminder model:** It already has `agent_id`, `client_id`, `scheduled_at`, `status`, `message`, `send_to_client`, `send_to_agent` fields. Perfect fit for cancellation reminders. Add a nullable `dotw_prebook_id` column via migration to link reminders to bookings.

**Queue:** Use the existing `database` queue driver on the `default` queue. The `api_sync` queue is reserved for hotel sync jobs. Reminder dispatch is low-volume (< 100/day) and does not need a dedicated queue.

**Confidence:** HIGH -- verified scheduler patterns from `app/Console/Kernel.php`, Reminder model from `app/Models/Reminder.php`.

### 3. WhatsApp Message Templating

**Pattern:** Use `ResayilController::shareReminder()` for all outbound WhatsApp messages. Build message strings in the module's services.

No template engine needed. The existing `ResayilController` sends plain text messages via the Resayil API. The DOTW AI module builds message strings for:

| Message Type | Trigger | Content |
|-------------|---------|---------|
| Booking confirmation | After DOTW confirms | Hotel name, dates, confirmation number, total price |
| Payment link | B2C confirm step | "Please pay KWD X for [hotel]. Link: [url]" |
| Cancellation reminder (3 days) | Scheduler | "Your booking at [hotel] has a free cancellation deadline in 3 days. Reply to cancel." |
| Cancellation reminder (1 day) | Scheduler | "LAST DAY: Free cancellation for [hotel] expires tomorrow. After that, you will be charged." |
| Auto-invoice notice | After deadline passes | "Your booking at [hotel] is now confirmed and invoiced. Amount: KWD X." |
| Cancellation result | After cancel confirmed | "Booking [ref] cancelled. Charge: KWD X. Refund: KWD Y." |
| Voucher delivery | After DOTW confirms | Hotel voucher details (paymentGuaranteedBy, dates, guest names) |

**Message builder location:** `app/Modules/DotwAI/Services/MessageBuilderService.php` -- pure functions that take booking data and return formatted strings. No new dependencies.

**Why NOT Laravel Notifications:** The system uses `ResayilController` as its WhatsApp transport, not Laravel's notification channels. Adding a WhatsApp notification channel would require abstracting Resayil into a channel driver. Not worth the overhead for this milestone. If the project later adds email/SMS notifications alongside WhatsApp, revisit.

**Confidence:** HIGH -- verified from existing `SendReminders.php` which uses `ResayilController::shareReminder()`.

### 4. GraphQL Schema: Modular Extension

**Pattern:** Use Lighthouse's `#import` directive (already in use) and `extend type Query/Mutation` (already in use in `dotw.graphql`).

The existing `graphql/schema.graphql` already imports `dotw.graphql` with `#import dotw.graphql`. The DOTW AI module adds a new file:

```
graphql/dotwai.graphql    # New file for module-specific queries
```

Import it from `schema.graphql`:
```graphql
#import dotw.graphql
#import dotwai.graphql    # Add this line
```

The new file uses `extend type Query` and `extend type Mutation` (same pattern as `dotw.graphql` lines 103-114 and 295-310). This adds the unified `searchDotwHotelRooms` query without modifying any existing schema.

**Schema federation:** Not needed. Lighthouse's `#import` + `extend type` is sufficient for modular schemas within a single Laravel app. Schema federation (Apollo Federation) is for microservices architectures with separate GraphQL servers. This project runs a single Lighthouse instance.

**Why NOT separate schema files in the module directory:** Lighthouse expects `.graphql` files relative to the configured schema path (`graphql/`). Placing them in `app/Modules/DotwAI/GraphQL/` would require custom schema loading. Not worth the complexity; the existing convention of `graphql/*.graphql` with `#import` works.

**Confidence:** HIGH -- verified from `graphql/schema.graphql` (line 1: `#import dotw.graphql`) and `graphql/dotw.graphql` (lines 103, 277, 295 use `extend type`).

### 5. Hotel Static Data Cache Sync

**Pattern:** Artisan command + queue job, same as existing `mapping:sync` commands.

The project already has `mapping:sync countries`, `mapping:sync cities`, and `mapping:sync hotels` scheduled weekly/daily in `Console/Kernel.php`. The DOTW AI module adds its own sync for DOTW-specific static data:

| Table | Source | Sync Frequency | Records |
|-------|--------|---------------|---------|
| `dotw_countries` | `getallcountries` API | Weekly | ~250 |
| `dotw_cities` | `getservingcountries` + `getservingcities` API | Weekly | ~5,000 |
| `dotw_currencies` | `getcurrenciesids` API | Monthly | ~30 |
| `dotw_salutations` | `getsalutationsids` API | Monthly | ~10 |

**Existing GraphQL queries for these:** The codebase already has `DotwGetAllCountries`, `DotwGetCities`, `DotwGetServingCountries` GraphQL queries. The sync command calls these internally or calls `DotwService` directly.

**Models needed:** `DotwCountry`, `DotwCity`, `DotwCurrency`, `DotwSalutation` -- simple Eloquent models with `code`, `name`, and lookup methods. Include Levenshtein distance fuzzy matching for city/country name resolution (typo correction for WhatsApp input).

**Migration:** Single migration creating all 4 tables. Lightweight, no foreign keys to existing tables.

**Confidence:** HIGH -- pattern verified from existing `SyncCitiesJob.php`, `SyncCountriesJob.php`, `SyncHotelsJob.php`.

### 6. Payment Link Generation for WhatsApp

**Pattern:** Reuse existing `route('payment.link.show', [...])` approach from `WhatsAppHotelController.php`.

The B2C flow needs to generate a payment link after the customer confirms a room selection. The existing codebase already does this for TBO hotel bookings:

```php
// From WhatsAppHotelController.php (lines 2477-2486)
$paymentLink = route('payment.link.show', [
    'companyId' => $companyId,
    'voucherNumber' => $payment->voucher_number,
]);
```

The DOTW AI module's `PaymentLinkService` wraps this pattern:

1. Create a `Payment` record (voucher) for the booking amount
2. Generate the payment link URL via the existing route
3. Return the URL to the n8n tool response (n8n sends it via WhatsApp)

**No new gateway integration needed.** The payment link page already handles gateway selection (MyFatoorah/KNET/Tap/etc.). The customer clicks the link, picks their gateway, pays. The webhook fires, and `processDotwBookingAfterPayment()` completes the DOTW booking.

**Confidence:** HIGH -- verified from `WhatsAppHotelController.php` lines 2260-2486.

---

## Locked Versions (Existing Stack)

| Technology | Locked Version | Purpose |
|-----------|---------------|---------|
| PHP | 8.2.12 | Runtime |
| Laravel Framework | 11.39.1 | Application framework |
| Lighthouse (GraphQL) | 6.63.1 | GraphQL engine |
| Livewire | 3.6.4 | Frontend (not used by this module) |
| myfatoorah/laravel-package | 2.2.x | Payment gateway |
| guzzlehttp/guzzle | 7.9.x | HTTP client (DOTW API calls) |
| spatie/laravel-permission | 6.10.x | RBAC |

**None of these need upgrading for the DOTW AI Module.**

---

## New Dependencies: None Required

| Considered | Why NOT Needed |
|-----------|---------------|
| nwidart/laravel-modules | Existing ResailAI module pattern is sufficient. Over-engineering for one module. |
| laravel/horizon | Queue monitoring. The database queue with existing `queue:work` scheduler is adequate for <100 jobs/day. |
| spatie/laravel-webhook-client | Payment webhooks already handled by existing controllers. |
| predis/predis or phpredis | Redis for cache/queue. Project uses database driver. Works fine at current scale. |
| Any WhatsApp SDK | Resayil API is accessed via simple HTTP calls in ResayilController. |
| Any notification package | WhatsApp delivery is already solved. No need for notification abstraction. |

---

## New Configuration (dotwai.php)

```php
// app/Modules/DotwAI/Config/dotwai.php
return [
    'b2b' => [
        'enabled' => env('DOTWAI_B2B_ENABLED', true),
        'markup_percent' => 0,                      // Always 0 for B2B
        'credit_booking' => env('DOTWAI_B2B_CREDIT', true),  // Book now, pay later
    ],
    'b2c' => [
        'enabled' => env('DOTWAI_B2C_ENABLED', true),
        'markup_percent' => env('DOTWAI_B2C_MARKUP', 20),
        'default_gateway' => env('DOTWAI_DEFAULT_GATEWAY', 'tap'),
    ],
    'reminders' => [
        'days_before_deadline' => [3, 2, 1],        // When to send cancellation reminders
        'auto_invoice_after_deadline' => true,        // Auto-invoice when deadline passes
    ],
    'static_data_sync' => [
        'schedule' => 'weekly',                       // When to sync DOTW countries/cities
    ],
    'prebook_cleanup_hours' => 24,                    // Purge expired prebooks after N hours
];
```

---

## New Database Migrations

| Migration | Tables | Purpose |
|-----------|--------|---------|
| `create_dotw_static_data_tables` | `dotw_countries`, `dotw_cities`, `dotw_currencies`, `dotw_salutations` | DOTW code resolution (fuzzy name matching) |
| `add_dotw_fields_to_reminders` | Alter `reminders` | Add `dotw_prebook_id` (nullable FK) for linking reminders to bookings |
| `add_booking_lifecycle_fields` | Alter `dotw_prebooks` | Add `booking_type` (b2b/b2c), `cancellation_deadline`, `auto_invoiced_at`, `reminder_sent_at` |

**No new standalone tables for booking lifecycle.** The existing `dotw_prebooks` and `dotw_bookings` tables cover the data model. The module adds lifecycle fields to `dotw_prebooks` via alter migrations.

---

## Integration Points Map

```
n8n AI Agent
    |
    v
[GraphQL: searchDotwHotelRooms] -------> DotwAI/SearchDotwHotelRooms.php
    |                                          |
    |                                          v
    |                                    DotwService.php (existing)
    |                                    DotwCacheService.php (existing)
    |                                    DotwCircuitBreakerService.php (existing)
    |
[REST: /api/whatsapp/hotel/dotw-booking-confirm] --> DotwAI/DotwBookingController.php
    |                                                     |
    |                                                     v
    |                                              BookingLifecycleService.php (new)
    |                                                     |
    |                      +------------------------------+---------------------+
    |                      |                              |                     |
    |                 B2B path                        B2C path           Create Reminders
    |                 (info only)                     (payment)          (via Reminder model)
    |                      |                              |
    |                      v                              v
    |                 Return to n8n               PaymentLinkService.php (new)
    |                                                     |
    |                                                     v
    |                                              Payment model (existing)
    |                                              route('payment.link.show') (existing)
    |                                                     |
    |                                              [Customer pays via gateway]
    |                                                     |
    |                                                     v
    |                                              PaymentController webhook (existing)
    |                                                     |
    |                                                     v
    |                                              processDotwBookingAfterPayment() (new method)
    |                                                     |
    |                                                     v
    |                                              DotwService::confirmBooking() (existing)
    |                                                     |
    |                                              Create Task + Invoice (existing)
    |
[Scheduler: dotwai:process-reminders] --> ReminderSchedulerService.php (new)
    |                                          |
    |                                          v
    |                                    Reminder model (existing)
    |                                    ResayilController::shareReminder() (existing)
```

---

## What NOT to Add

| Do Not Add | Why |
|-----------|-----|
| New payment gateway integration | 5 gateways already work. Payment link page handles selection. |
| WhatsApp channel/notification driver | ResayilController is the WhatsApp transport. Works. |
| Redis or Memcached | Database queue handles the load. Module adds <100 jobs/day. |
| Separate database for DOTW module | All data goes in `laravel_testing`. Module isolation is at the code level, not database level. |
| API versioning middleware | n8n is the sole consumer. No public API versioning needed. |
| Rate limiting middleware | DOTW circuit breaker already handles API protection. |
| Module framework (nwidart) | Existing manual module pattern works. One module does not justify a framework. |
| Event sourcing | Hybrid accounting (CRM + journal) is simpler and already validated. |
| Separate GraphQL endpoint | Single Lighthouse endpoint with `#import` is the established pattern. |

---

## Environment Variables to Add

```env
# DOTW AI Module
DOTWAI_B2B_ENABLED=true
DOTWAI_B2C_ENABLED=true
DOTWAI_B2C_MARKUP=20
DOTWAI_DEFAULT_GATEWAY=tap
DOTWAI_B2B_CREDIT=true
```

All other env vars (DOTW credentials, payment gateway keys, Resayil API tokens) already exist.

---

## Sources

- Existing `app/Modules/ResailAI/Providers/ResailAIServiceProvider.php` -- module pattern reference
- Existing `app/Console/Kernel.php` -- scheduler patterns (12+ scheduled commands)
- Existing `app/Models/Reminder.php` -- reminder data model
- Existing `app/Console/Commands/SendReminders.php` -- WhatsApp reminder delivery via Resayil
- Existing `app/Http/Controllers/ResayilController.php` -- `shareReminder()` WhatsApp transport
- Existing `app/Http/Controllers/WhatsAppHotelController.php` -- payment link generation pattern
- Existing `graphql/schema.graphql` + `graphql/dotw.graphql` -- `#import` and `extend type` patterns
- Existing `app/Services/DotwService.php` -- complete DOTW V4 API wrapper
- Existing `config/dotw.php` -- DOTW configuration
- Existing `composer.json` -- no new dependencies needed
- [Lighthouse Schema Organisation](https://lighthouse-php.com/2/guides/schema-organisation.html) -- `#import` directive docs
- [Laravel 11 Task Scheduling](https://laravel.com/docs/11.x/scheduling) -- `withoutOverlapping()` reference
- [Laravel 11 Package Development](https://laravel.com/docs/11.x/packages) -- ServiceProvider patterns
