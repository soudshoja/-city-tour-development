# Research: Phase 3 — Cache Service & GraphQL Response Architecture

**Date:** 2026-02-21
**Phase:** 03-cache-service-and-graphql-response-architecture
**Scope:** CACHE-01 through CACHE-05, GQLR-01 through GQLR-08

---

## Existing Codebase State

### What Already Exists (From Reading Source)

**DotwService.php** (`app/Services/DotwService.php`, 1,407 lines):
- Reads credentials from `config/dotw.php` directly — no company_id awareness yet (Phase 1 adds multi-company support)
- Has `searchHotels()`, `getRooms()`, `confirmBooking()` etc. — all throw `Exception` on error
- No caching anywhere — every call hits DOTW API
- Logs to `dotw` channel already

**config/dotw.php** — exists, has: username, password, company_code, dev_mode, endpoints, request (timeout/connect_timeout/source/product), allocation_expiry_minutes, b2c_markup_percentage, rate_basis_codes, log_channel. Does NOT have a cache section.

**graphql/schema.graphql** — exists with scalars, HotelSearchInput, existing TBO/Magic Holiday types. Lighthouse schema_path points to this file. No DOTW-specific types defined.

**config/lighthouse.php** — standard Lighthouse config. Route middleware currently has: AcceptJson, AttemptAuthentication. No custom DOTW middleware.

**app/GraphQL/Queries/SearchDotwHotels.php** — exists (865+ lines) from an earlier spike. This is an existing query from the legacy implementation — Phase 4 will refactor it to use the new cache + envelope architecture. Phase 3 does NOT touch this file.

**app/GraphQL/Middleware/** — directory does NOT exist yet. Will be created by Plan 02.

### Lighthouse Version and Capabilities

Using Lighthouse (nuwave/lighthouse) based on config/lighthouse.php structure — confirms Lighthouse 6.x pattern. Key capabilities used:
- `#import` directive in .graphql files — supported for splitting schemas
- Route middleware array in lighthouse.php — standard Laravel middleware, runs before GraphQL execution
- `@field(resolver: "...")` — used throughout existing schema

### Cache Driver

`config/cache.php` exists. Default cache store from env `CACHE_DRIVER` — likely file or redis in dev. Laravel's `Cache::remember()` works with any driver. Using `DateInterval` object for TTL is preferred over integer (avoids ambiguity with seconds vs minutes in older Laravel versions).

---

## Key Decisions

### Cache Key Design

**Decision:** `{prefix}_{company_id}_{destination}_{checkin}_{checkout}_{rooms_hash}`

Rooms hash = `md5(json_encode(normalizedRooms))` where normalization:
1. Each room's array keys are sorted (ksort)
2. Children ages array is sorted ascending
3. Rooms array is sorted by adultsCode ascending

This makes `[{adultsCode:2, children:[8,5]}]` and `[{adultsCode:2, children:[5,8]}]` produce the same hash. It also makes two rooms in different order produce the same hash.

**Why md5 over sha256:** Shorter key string (32 chars vs 64). Cache keys don't need cryptographic security — just uniqueness for the same logical search. md5 is faster and the collision risk for this use case is negligible.

**Why company_id in key:** Phase 1 introduces per-company credentials. Without company_id isolation, Company A's cached results could be returned to Company B for the same destination/dates. This is a data isolation requirement (CACHE-04).

### Cache TTL Implementation

**Decision:** Use `new \DateInterval('PT'.$ttl.'S')` (DateInterval object) rather than integer seconds.

Reasoning: Laravel's Cache::remember() accepts both integers (seconds) and DateInterval. DateInterval is unambiguous and self-documenting. Integer TTL in older Laravel versions was sometimes interpreted differently across cache drivers.

### DotwCacheService as Standalone Class

**Decision:** DotwCacheService is NOT a subclass of DotwService and does NOT depend on DotwService.

Reasoning: Phase 4 (Hotel Search) will inject both DotwCacheService and DotwService into the resolver. The cache service wraps any callable — it doesn't know what the callable does. This makes it testable independently and reusable for other DOTW operations if needed in the future.

### GraphQL Response Envelope Design

**Decision:** Define shared types (DotwMeta, DotwError, DotwErrorCode, DotwErrorAction) in graphql/dotw.graphql but do NOT define a generic DotwResponseEnvelope union or interface.

Reasoning: GraphQL unions require every member type to have the same fields (for interface) or the client to query `__typename` (for union). The actual envelope shape varies per operation: searchHotels returns an array of hotels, blockRates returns a prebook object. Instead, each Phase 4/5/6 operation type will embed DotwMeta and DotwError as named fields within its own response type:

```graphql
type SearchHotelsResponse {
    success: Boolean!
    meta: DotwMeta!
    data: SearchHotelsData        # null if error
    error: DotwError              # null if success
    cached: Boolean!
}
```

This pattern (each operation owns its response type, shares meta/error types) is simpler than a generic envelope union and produces cleaner N8N GraphQL queries.

### Trace ID Strategy

**Decision:** Store trace_id in Laravel service container via `app()->instance('dotw.trace_id', $traceId)` — NOT via a static class property or session.

Reasoning:
- Static property would persist between requests in FPM workers (stale trace IDs)
- Session is wrong scope (GraphQL APIs are stateless)
- Service container instance binding is per-request in standard FPM — safe and accessible from any resolver via `app('dotw.trace_id')`
- Request attributes (`$request->attributes->set()`) also work but require injecting the Request object into every resolver

### Middleware Registration

**Decision:** Register DotwTraceMiddleware in Lighthouse's route middleware (config/lighthouse.php), NOT as a global Laravel middleware.

Reasoning: The DOTW trace headers should only appear on GraphQL responses, not on web routes, Livewire endpoints, or API resource routes. Lighthouse's route middleware runs only for requests to `/graphql`.

### Schema Import

**Decision:** Use `#import dotw.graphql` at the top of graphql/schema.graphql (Lighthouse's built-in import).

Reasoning: This keeps the DOTW schema isolated in its own file (supports Phase 8 modularity requirement MOD-04) without requiring any config/lighthouse.php schema_path changes. The `#import` directive is the standard Lighthouse pattern for multi-file schemas.

---

## Patterns Established for Downstream Phases

Phase 4 (Hotel Search) and later phases must:

1. **Use DotwCacheService** — inject it in resolver constructor, call `remember($key, fn() => $dotw->searchHotels(...))`.
2. **Check isCached($key) BEFORE calling remember()** to set `cached: true/false` in the response.
3. **Access trace_id** via `app('dotw.trace_id')` in any resolver to populate `DotwMeta.trace_id`.
4. **Return DotwMeta** in every operation response:
   ```php
   'meta' => [
       'trace_id' => app('dotw.trace_id'),
       'timestamp' => now()->toIso8601String(),
       'company_id' => $companyId,
       'request_id' => app('dotw.trace_id'),
   ]
   ```
5. **Return DotwError on failure:**
   ```php
   'error' => [
       'error_code' => 'CREDENTIALS_NOT_CONFIGURED',
       'error_message' => 'DOTW credentials not configured for this company',
       'error_details' => $e->getMessage(),
       'action' => 'RECONFIGURE_CREDENTIALS',
   ]
   ```
6. **Import dotw.graphql types** — types DotwMeta, DotwError, DotwErrorCode, DotwErrorAction are available in all schema files after the #import in schema.graphql.

---

## What Phase 3 Does NOT Do

- Does NOT refactor existing SearchDotwHotels.php (legacy spike — Phase 4 owns this)
- Does NOT create the `dotw_audit_logs` table (Phase 2)
- Does NOT implement per-company credential resolution (Phase 1)
- Does NOT add searchHotels, getRoomRates, blockRates, createPreBooking to the schema (Phase 4, 5, 6)
- Does NOT implement circuit breaker (Phase 7)
- Does NOT add rate limiting (deferred to v2)
