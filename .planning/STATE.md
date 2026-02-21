# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-21)

**Core value:** Agents can invoice clients accurately from any source (AIR files, PDFs, Excel uploads) with automated payment tracking and accounting integration.
**Current milestone:** DOTW v1.0 B2B — Hotel search & booking API

## Current Position

Phase: MILESTONE COMPLETE — All 8 phases executed and verified
Plan: —
Status: Complete — DOTW v1.0 B2B milestone all 8 phases done, milestone audit passed
Last activity: 2026-02-21 — Phase 8 Modular Architecture & B2B Packaging executed (2/2 plans) + milestone audit passed

Progress: █████████████ 8 of 8 phases complete

## Wave Structure (DOTW v1.0 B2B)

**Wave 1 — run in parallel (no dependencies):**
- Phase 1: Credential Management & Markup Foundation
- Phase 2: Message Tracking & Audit Infrastructure
- Phase 3: Cache Service & GraphQL Response Architecture

**Wave 2 — after Wave 1 complete (run both in parallel):**
- Phase 4: Hotel Search GraphQL
- Phase 5: Rate Browsing & Rate Blocking

**Wave 3 — after Wave 2 complete (run all in parallel):**
- Phase 6: Pre-Booking & Confirmation Workflow
- Phase 7: Error Hardening & Circuit Breaker
- Phase 8: Modular Architecture & B2B Packaging

## Phase Status

| Phase | Name | Wave | Status |
|-------|------|------|--------|
| 1 | Credential Management & Markup Foundation | Wave 1 | Complete (Plans 01 and 02 of 02 complete) |
| 2 | Message Tracking & Audit Infrastructure | Wave 1 | Complete (Plans 01, 02, and 03 of 03 complete) |
| 3 | Cache Service & GraphQL Response Architecture | Wave 1 | In Progress (Plans 01 and 02 of 03 complete) |
| 4 | Hotel Search GraphQL | Wave 2 | Complete (Plans 01, 02, and 03 of 03 complete) |
| 5 | Rate Browsing & Rate Blocking | Wave 2 | Complete (Plans 01, 02, and 03 of 03 complete) |
| 6 | Pre-Booking & Confirmation Workflow | Wave 3 | Complete (Plans 01 and 02 of 02 complete) |
| 7 | Error Hardening & Circuit Breaker | Wave 3 | Complete (Plans 01, 02, and 03 of 03 complete) |
| 8 | Modular Architecture & B2B Packaging | Wave 3 | Not started |

## Accumulated Context

### Key Decisions

- DOTW module is standalone — independent phase numbering (Phase 1-8), no coupling to v1.0 Bulk Invoice Upload phases
- WhatsApp-first design — Resayil message_id + quote_id tracked on every operation
- Sync GraphQL operations — user waits for response (simpler than async for conversational flow)
- Search caching 2.5 min — reduces DOTW API calls during multi-question WhatsApp conversations
- Per-company credentials — each company has own DOTW username/password/company_code in DB
- Modular design — can be copied to production subdomain with only config changes + migrations
- N8N GraphQL integration (GQL-01..08) moved to DOTW V2 B2C milestone
- GQLR-01..08 (response structure) placed in Phase 3 — must exist before Search, Rates, Booking are built
- DotwTraceMiddleware registered as Lighthouse route middleware for universal X-Trace-ID and X-Request-Time-Ms header injection
- trace_id bound in service container as 'dotw.trace_id' for resolver access without global state
- dotw.graphql standalone schema imported via #import directive — DotwMeta, DotwError, DotwErrorCode, DotwErrorAction types established
- Nullable ?int $companyId constructor parameter maintains backward compat with existing DotwService callers
- Crypt::encrypt/Crypt::decrypt used explicitly in model accessors (not $casts) for encryption visibility
- $hidden array on CompanyDotwCredential prevents credential blob leakage in API responses/logs
- updateOrCreate(['company_id']) used in DotwCredentialController.store() — upsert semantics, no duplicate rows on re-submit
- Response payloads explicitly constructed in DotwCredentialController — credentials excluded at both model layer ($hidden) and response layer (not in array)
- findOrFail() used for company existence check — auto 404 before credential logic, no manual check needed
- DateInterval used for Cache TTL (not integer seconds) in DotwCacheService — type-safe and self-documenting
- remember() does not inject 'cached' flag into results — callers use isCached() before remember() to detect hits
- company_id embedded directly in cache key string — simpler than namespacing, works across all Laravel cache drivers
- No FK on company_id in dotw_audit_logs — DOTW module is standalone per MOD-06, audit logs survive company changes
- Fail-silent logging pattern in DotwAuditService — audit failure never breaks DOTW search/booking operations
- UPDATED_AT = null on DotwAuditLog — audit logs are append-only, immutable after creation
- Standard Laravel HTTP middleware used for ResayilContextMiddleware (not Lighthouse-specific interface) — route.middleware in lighthouse.php is sufficient for Lighthouse 6.x
- request->attributes used as Resayil ID carrier in GraphQL context — request-scoped, zero overhead, no global state
- bookItinerary wrapped bookingCode in array for DotwAuditService::log() request param — consistent with other methods
- companyId ?? $this->companyId fallback on DotwService operations — resolver can override constructor company context per-request
- App\Http\Livewire\Admin namespace for DotwAuditLogIndex — separate from existing App\Livewire to group DOTW admin components
- isSuperAdmin() method in Livewire component — DRY role check used for both query scoping and blade conditionals
- Sidebar WhatsApp AI button uses @if(in_array(...)) pattern — consistent with existing Admin company switcher check in sidebar
- dotw_audit_access middleware alias registered in bootstrap/app.php — allows Role::ADMIN and Role::COMPANY, 403 for all others
- Triple-quoted block strings (`"""..."""`) required for multi-line descriptions in GraphQL SDL — double-quoted strings must be single-line
- DotwErrorCode enum values must be used exactly in resolvers — VALIDATION_ERROR for input validation failures (not INVALID_INPUT)
- lighthouse:print-schema fails when any @field resolver class is missing — expected until Plan 02 creates DotwSearchHotels
- DotwService instantiated inside cache closure (not injected) — ensures per-company credentials resolved with companyId on every cache miss
- isCached() before remember() pattern — wasCached captured before cache read, cached: true accurately reflects API bypass
- DotwService reused in formatHotels() — single instance across all hotels avoids repeated DB credential lookups
- RuntimeException vs Exception catch separation — distinct error codes CREDENTIALS_NOT_CONFIGURED vs API_ERROR for N8N workflow branching
- currency column on company_dotw_credentials is plain string (not encrypted) — not sensitive, no Crypt needed
- DotwSearchHotels currency priority chain: input.currency (if non-empty) > company DB currency > 'USD' last resort fallback
- SEARCH-06 traceability moved to Phase 5 partial — hotel_code+rates delivered Phase 4; name/city/rating/location/image_url deferred
- No FK on company_id in dotw_prebooks — consistent with dotw_audit_logs standalone module approach (MOD-06)
- getRoomRates always returns cached: false — rates change minute-to-minute, allocationDetails tokens expire
- blockRates always returns cached: false — blocking is a side-effecting mutation, caching incorrect
- RateDetail.original_currency is String! (empty string sentinel when DOTW omits) per RATE-05
- RateDetail.exchange_rate is Float (nullable) — null when DOTW performs no conversion per RATE-05
- activeForUser() scope uses where('expired_at', '>', now()) — matches compound index column order for query plan optimization
- DotwGetRoomRates instantiates DotwService once in __invoke and passes it to formatRooms() — avoids second DB credential lookup, mirrors formatHotels() pattern in DotwSearchHotels
- is_refundable defaults to true when parseRooms() does not include nonRefundable key — safe conservative default for rate browse
- DotwAuditService::log() positional args: (string operationType, array request, array response, ?string resayilMessageId, ?string resayilQuoteId, ?int companyId) — plan template used single-array pattern which was corrected to match the real signature
- Two-phase audit in blockRates: Phase A (DotwService::getRooms internal log, no prebook_key), Phase B (supplementary post-transaction DotwAuditService::log with prebook_key and allocation_expiry per BLOCK-07)
- Two-phase audit in createPreBooking: Phase A (DotwService::confirmBooking internal log, raw params + confirmation), Phase B (supplementary resolver log with prebook_key + confirmation_code per BOOK-07)
- formatAlternatives() helper pattern: DotwService::searchHotels() returns raw array — must be mapped to HotelSearchResult schema shape with markup applied via DotwService::applyMarkup()
- UPDATED_AT = null on DotwBooking — booking records are append-only after creation (same as DotwAuditLog)
- No FK on company_id in dotw_bookings — consistent MOD-06 standalone module design across all DOTW tables
- createPreBooking requires checkin/checkout in input — DotwPrebook does not store these dates (Pitfall 1 from research — resolved by accepting from caller)
- DotwTimeoutException extends \Exception (NOT \RuntimeException) — catch order in all resolvers: DotwTimeoutException → RuntimeException → \Exception; class identity is the discriminator (ERROR-02)
- config/dotw.php default timeout: 120s → 25s per DOTW SLA (ERROR-02); DOTW_TIMEOUT env var still overrides
- ConnectionException caught in DotwService::post() before generic Exception — rethrown as DotwTimeoutException; no credentials in log context
- Circuit breaker applies ONLY to DotwSearchHotels (ERROR-08) — getRoomRates/blockRates/getCities excluded; FAILURE_THRESHOLD=5, WINDOW_SECONDS=60, OPEN_TTL_SECONDS=30
- Circuit open + cache hit → return cached hotels with cached:true; circuit open + no cache → CIRCUIT_BREAKER_OPEN + RETRY_IN_30_SECONDS
- recordFailure() on DotwTimeoutException and generic Exception; NOT on RuntimeException (credential misconfig is not transient)
- DotwCacheService::get() added for circuit-open fallback read (no TTL side-effect, distinct from remember())
- ERROR-07 log safety audit: CLEAN — no credential values ($username/$passwordMd5/$companyCode) in any DotwService log context; all body fragments truncated to ≤500 chars
- DotwGetCities upgraded from string-matching credential detection to proper RuntimeException catch (ERROR-01 now uniform across all 4 resolvers)

### Pending Todos

None yet.

### Blockers/Concerns

None yet.

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 1 | Sanctum API token support for DOTW per-company n8n integration with admin UI | 2026-02-21 | feebb1dd | [1-sanctum-api-token-support-for-dotw-per-c](.planning/quick/1-sanctum-api-token-support-for-dotw-per-c/) |

## Session Continuity

Last session: 2026-02-21
Stopped at: Completed Phase 7 — Error Hardening & Circuit Breaker (ERROR-01, ERROR-02, ERROR-07, ERROR-08). Phase 7 complete (3/3 plans).
Next: Execute Phase 8 (Modular Architecture & B2B Packaging) — final Wave 3 phase, DOTW v1.0 B2B completion

## Previous Milestone (v1.0 Bulk Invoice Upload)

Completed 2026-02-13 — 4 phases, 9 plans, 1.2 hours execution
See: .planning/milestones/v1.0-ROADMAP.md
