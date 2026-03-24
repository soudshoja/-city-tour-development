# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-24)

**Core value:** Enable travel agents and customers to search, book, and manage DOTW hotel reservations entirely through WhatsApp with AI-driven conversation, automated lifecycle, and full accounting
**Current focus:** Phase 18 — Foundation + Search (module scaffolding, hotel import, search endpoints, WhatsApp message formatting)

## Current Position

Phase: 18 (1 of 5 in v2.0 DOTW AI Module) -- COMPLETE
Plan: 3 of 3 in current phase (phase complete)
Status: Phase 18 Complete
Last activity: 2026-03-24 — Completed 18-03 (DotwAI Module Test Suite)

Progress: [####░░░░░░] 20% (v2.0 milestone — 3/15 plans)

## Performance Metrics

**Velocity:**
- Total plans completed: 10 (v1.0 + v2.0 milestones)
- Average duration: N/A (not tracked for previous milestones)
- Total execution time: N/A

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 15 | 2 | - | - |
| 16 | 3 | - | - |
| 18 | 3 | 21m | 7m |

**Recent Trend:**
- Last 5 plans: 16-02, 16-03, 18-01, 18-02, 18-03
- Trend: Stable

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- [v2.0]: REST API (not GraphQL) -- 11 endpoints + 1 webhook for n8n consumption
- [v2.0]: AI model Qwen3-Next:80B on Ollama cloud for intent detection
- [v2.0]: B2B entirely through WhatsApp, B2C independent track
- [v2.0]: Hybrid accounting: CRM for all events, journal only for money movement
- [v2.0]: Hotel data from Excel/CSV import (not API sync)
- [v2.0]: Every response includes whatsappMessage (pre-formatted)
- [v2.0]: No modification of existing code -- wrap/extend only
- [18-01]: DotwAIResponse uses static methods with default bilingual messages per error code
- [18-01]: Track determination: markup_percent > 0 = B2C, 0 = B2B
- [18-01]: LIKE + Levenshtein two-tier fuzzy matching (threshold 3)
- [18-02]: DotwService instantiated with companyId for per-company credential resolution (not DI)
- [18-02]: MessageBuilderService all-static methods (pure functions, no state)
- [18-02]: Dual-level filtering: API-level (hotel IDs, stars) + post-search (meal, price, refundable, name)
- [18-02]: Browse-only for hotel details (blocking=false) -- rate blocking deferred to Phase 19
- [18-03]: Mockery overload pattern for DotwService mocking (new DotwService() interception)
- [18-03]: skipPermissionSeeder=true on all DotwAI tests for isolation from permission system

### Pending Todos

None yet.

### Blockers/Concerns

- DOTW tests 17+18 still need specific hotel IDs (from Phase 16) -- not blocking v2.0 work
- Payment gateway session creation API needs verification during Phase 19 planning
- Invoice/JournalEntry field requirements need verification during Phase 20 planning

## Session Continuity

Last session: 2026-03-24
Stopped at: Completed 18-03-PLAN.md (DotwAI Module Test Suite -- Phase 18 complete)
Resume file: None
