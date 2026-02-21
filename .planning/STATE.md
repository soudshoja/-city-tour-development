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
| 1 | Credential Management & Markup Foundation | Wave 1 | In Progress (Plan 01 of 02 complete) |
| 2 | Message Tracking & Audit Infrastructure | Wave 1 | Not started |
| 3 | Cache Service & GraphQL Response Architecture | Wave 1 | In Progress (Plan 02 of 03 complete) |
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

### Pending Todos

None yet.

### Blockers/Concerns

None yet.

## Session Continuity

Last session: 2026-02-21
Stopped at: Completed 01-01-PLAN.md — per-company DOTW credential storage (migration, model, DotwService refactor)
Next: Execute Phase 1 Plan 02 (remaining plan in Phase 1)

## Previous Milestone (v1.0 Bulk Invoice Upload)

Completed 2026-02-13 — 4 phases, 9 plans, 1.2 hours execution
See: .planning/milestones/v1.0-ROADMAP.md
