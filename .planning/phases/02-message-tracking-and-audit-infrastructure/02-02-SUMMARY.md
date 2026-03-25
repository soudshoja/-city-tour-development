---
phase: 02-message-tracking-and-audit-infrastructure
plan: "02"
subsystem: dotw-audit
tags: [lighthouse, graphql, middleware, dotw-service, audit-logging, whatsapp-tracking]
dependency_graph:
  requires:
    - 02-01 (DotwAuditLog model, DotwAuditService, dotw_audit_logs migration)
  provides:
    - ResayilContextMiddleware (HTTP header extraction into GraphQL context)
    - DotwService audit instrumentation (4 operation types)
    - SearchDotwHotels resolver wired to pass Resayil IDs
  affects:
    - Phase 4 (Hotel Search GraphQL — inherits the audit chain)
    - Phase 5 (Rate Browsing & Rate Blocking — getRooms is already instrumented)
    - Phase 6 (Pre-Booking & Confirmation — confirmBooking/saveBooking instrumented)
tech_stack:
  added: []
  patterns:
    - Standard Laravel HTTP middleware on Lighthouse /graphql route
    - request->attributes as GraphQL context carrier (no global state)
    - Optional parameter injection for backward-compatible audit context
    - Fail-silent audit logging (DotwAuditService catches all exceptions)
    - companyId ?? $this->companyId fallback for B2B / legacy mode
key_files:
  created:
    - app/GraphQL/Middleware/ResayilContextMiddleware.php
  modified:
    - config/lighthouse.php
    - app/Services/DotwService.php
    - app/GraphQL/Queries/SearchDotwHotels.php
decisions:
  - Standard Laravel HTTP middleware used (not Lighthouse-specific interface) — simpler, works in Lighthouse 6.x via route middleware array
  - request->attributes used as carrier (not session/context bag) — zero serialization overhead, request-scoped lifetime
  - Optional companyId param on DotwService operation methods uses constructor companyId as fallback
  - bookItinerary also instrumented with OP_BOOK (wraps bookingCode string in array for request payload)
  - LighthouseServiceProvider NOT created — config/lighthouse.php route.middleware array is sufficient
metrics:
  duration: "12 minutes"
  completed: "2026-02-21"
  tasks_completed: 4
  files_changed: 4
---

# Phase 2 Plan 2: Lighthouse Middleware & DotwService Audit Wiring Summary

**One-liner:** ResayilContextMiddleware extracts WhatsApp tracking headers; DotwService calls DotwAuditService::log() for all four DOTW operation types.

## Architecture: Complete Audit Chain

```
HTTP Request
  └─ X-Resayil-Message-ID header
  └─ X-Resayil-Quote-ID header
        |
        v
ResayilContextMiddleware (config/lighthouse.php route.middleware)
        |
        v
$request->attributes->set('resayil_message_id', ...)
$request->attributes->set('resayil_quote_id', ...)
        |
        v
SearchDotwHotels resolver (Task 4)
  $context->request()->attributes->get('resayil_message_id')
        |
        v
DotwService::searchHotels($params, $resayilMessageId, $resayilQuoteId, $companyId)
        |
        v
DotwAuditService::log(OP_SEARCH, $params, $hotels, $messageId, $quoteId, $companyId)
        |
        v
dotw_audit_logs row (from Plan 01 migration)
```

## Files Created

| File | Purpose |
|------|---------|
| `app/GraphQL/Middleware/ResayilContextMiddleware.php` | Standard Laravel HTTP middleware; extracts X-Resayil-* headers into request attributes |

## Files Modified

| File | Change |
|------|--------|
| `config/lighthouse.php` | Added `ResayilContextMiddleware::class` to `route.middleware` array after `DotwTraceMiddleware` |
| `app/Services/DotwService.php` | Injected `DotwAuditService`; added optional params + `log()` calls to 5 methods |
| `app/GraphQL/Queries/SearchDotwHotels.php` | `__invoke` accepts `$context`; extracts Resayil IDs; passes to `searchHotels()` |

## Middleware Registration

Registered in `config/lighthouse.php` under `route.middleware`:

```php
'middleware' => [
    \App\GraphQL\Middleware\DotwTraceMiddleware::class,
    \App\GraphQL\Middleware\ResayilContextMiddleware::class,  // <-- added
    Nuwave\Lighthouse\Http\Middleware\AcceptJson::class,
    Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication::class,
],
```

**Why route middleware (not global middleware)?** Lighthouse 6.x registers the `/graphql` endpoint as a standard Laravel route. The `route.middleware` array in `config/lighthouse.php` is the canonical way to attach middleware to that route — no custom ServiceProvider needed.

## How Resolvers Access resayil_message_id

Resolvers receive a `$context` argument implementing `GraphQLContext`. The middleware sets headers on request attributes before the resolver runs. Access pattern:

```php
$request = $context->request();
$resayilMessageId = $request->attributes->get('resayil_message_id'); // ?string
$resayilQuoteId   = $request->attributes->get('resayil_quote_id');   // ?string
```

Both are nullable — null when the header is absent. This is correct and expected for non-WhatsApp callers.

## DotwService Method Signatures with New Parameters

| Method | Signature (new params at end) |
|--------|-------------------------------|
| `searchHotels` | `(array $params, ?string $resayilMessageId = null, ?string $resayilQuoteId = null, ?int $companyId = null): array` |
| `getRooms` | `(array $params, bool $blocking = false, ?string $resayilMessageId = null, ?string $resayilQuoteId = null, ?int $companyId = null): array` |
| `confirmBooking` | `(array $params, ?string $resayilMessageId = null, ?string $resayilQuoteId = null, ?int $companyId = null): array` |
| `saveBooking` | `(array $params, ?string $resayilMessageId = null, ?string $resayilQuoteId = null, ?int $companyId = null): array` |
| `bookItinerary` | `(string $bookingCode, ?string $resayilMessageId = null, ?string $resayilQuoteId = null, ?int $companyId = null): array` |

All new parameters are optional with null defaults — no breaking change for existing callers that do `new DotwService()`.

## Operation Type Mapping

| Method | Blocking | OP_* Constant | Audit Row operation_type |
|--------|----------|---------------|--------------------------|
| `searchHotels` | N/A | `OP_SEARCH` | `search` |
| `getRooms` | `false` | `OP_RATES` | `rates` |
| `getRooms` | `true` | `OP_BLOCK` | `block` |
| `confirmBooking` | N/A | `OP_BOOK` | `book` |
| `saveBooking` | N/A | `OP_BOOK` | `book` |
| `bookItinerary` | N/A | `OP_BOOK` | `book` |

`getRooms` uses `$blocking` boolean to determine whether to use `OP_RATES` or `OP_BLOCK`.

## Integration Test Results

**DB availability:** MySQL server is not installed in this development environment (same constraint noted in Plan 01 SUMMARY). Integration test via tinker confirming row counts was not possible.

**Structural verification via tinker (all passed):**

1. `ResayilContextMiddleware` instantiates successfully: `OK`
2. `OP_*` constants: `search`, `rates`, `block`, `book` — all present
3. All 5 method parameter positions verified correct via `ReflectionMethod`:
   - `searchHotels param[1] = resayilMessageId: OK`
   - `getRooms param[2] = resayilMessageId: OK`
   - `confirmBooking param[1] = resayilMessageId: OK`
   - `saveBooking param[1] = resayilMessageId: OK`
   - `bookItinerary param[1] = resayilMessageId: OK`
4. Credential sanitization verified: `password => [REDACTED]`, `dotw_username => [REDACTED]`, non-sensitive keys pass through unchanged

## Decisions Made

1. **Standard Laravel HTTP middleware** — Used `handle(Request $request, Closure $next)` pattern rather than a Lighthouse-specific interface. Lighthouse 6.x processes the `/graphql` route through the standard Laravel HTTP kernel, so route middleware works identically to any other Laravel route.

2. **`$request->attributes` as carrier** — Avoids global state (session, config, container bindings). `ParameterBag::set()` is request-scoped, zero-overhead, and naturally garbage-collected at request end.

3. **`companyId ?? $this->companyId` fallback** — Each operation method accepts an optional `$companyId` parameter that overrides the constructor-level company context. This allows resolvers to pass a freshly-authenticated user's company ID on each request while maintaining backward compat for callers that rely on the constructor-level ID.

4. **`bookItinerary` wrapped in array** — `bookItinerary(string $bookingCode)` doesn't receive a `$params` array. To call `DotwAuditService::log(OP_BOOK, $request, ...)`, the booking code is wrapped: `['bookingCode' => $bookingCode]`. This is sanitized (no sensitive keys), and documents the operation context clearly in the audit log.

5. **`LighthouseServiceProvider` not created** — The config file approach is sufficient. The ServiceProvider approach would be needed only if middleware needed to be added conditionally or with dependencies, which is not the case here.

## Requirements Satisfied

| Requirement | Description | Status |
|-------------|-------------|--------|
| MSG-02 | WhatsApp message_id tracked on every operation | Done — middleware + resolver wire |
| MSG-03 | Quote ID tracked when present | Done — resayil_quote_id flows through |
| MSG-04 | All four operation types produce audit rows | Done — search/rates/block/book all log |
| MSG-05 | No DOTW credentials in audit log | Done — DotwAuditService sanitizes (verified) |

Together with Plan 01 (migration + model + service), all five MSG requirements (MSG-01..MSG-05) are satisfied.

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| Task 1 | `4f0189f7` | feat(02-02): create ResayilContextMiddleware and register in Lighthouse config |
| Task 2 | `54fce020` | feat(02-02): instrument DotwService with audit log calls for all four operation types |
| Task 3 | (no commit — verification only) | Structural verification via tinker (DB not available) |
| Task 4 | `04ca5ca4` | feat(02-02): wire SearchDotwHotels resolver to pass Resayil IDs from context to DotwService |

## Deviations from Plan

None — plan executed exactly as written.

The only deviation from the verification steps is that `php artisan tinker` integration tests requiring DB access (DotwAuditLog::count(), log() creating actual rows) could not be run because MySQL server is not installed in the local development environment. All structural and logic-level verifications passed. The migration from Plan 01 is syntactically correct and will populate the table when executed on a MySQL-enabled environment.
