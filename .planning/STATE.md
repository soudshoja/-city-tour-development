# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-21)

**Core value:** Agents can invoice clients accurately from any source (AIR files, PDFs, Excel uploads) with automated payment tracking and accounting integration.
**Current milestone:** DOTW v1.0 B2B — Hotel search & booking API

## Current Position

Phase: Wave 1 — Plans complete, ready to execute
Plan: —
Status: Ready — Wave 1 phases planned and verified, execute phases 1, 2, 3 in parallel
Last activity: 2026-02-21 — Wave 1 plans created (7 plans across 3 phases), plan checkers passed

Progress: ○ 0 of 8 phases complete

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
| 2 | Message Tracking & Audit Infrastructure | Wave 1 | Complete (Plans 01 and 02 of 02 complete) |
| 3 | Cache Service & GraphQL Response Architecture | Wave 1 | In Progress (Plans 01 and 02 of 03 complete) |
| 4 | Hotel Search GraphQL | Wave 2 | Not started |
| 5 | Rate Browsing & Rate Blocking | Wave 2 | Not started |
| 6 | Pre-Booking & Confirmation Workflow | Wave 3 | Not started |
| 7 | Error Hardening & Circuit Breaker | Wave 3 | Not started |
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

### Pending Todos

None yet.

### Blockers/Concerns

None yet.

## Session Continuity

Last session: 2026-02-21
Stopped at: Completed 02-02-PLAN.md — Lighthouse middleware and DotwService audit wiring (ResayilContextMiddleware, 4-operation audit chain, SearchDotwHotels resolver wired)
Next: Phase 2 complete — Phase 3 is the only remaining Wave 1 phase (Cache Service & GraphQL Response Architecture — Plan 03 of 03 remaining)

## Previous Milestone (v1.0 Bulk Invoice Upload)

Completed 2026-02-13 — 4 phases, 9 plans, 1.2 hours execution
See: .planning/milestones/v1.0-ROADMAP.md
