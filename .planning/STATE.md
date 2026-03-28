---
gsd_state_version: 1.0
milestone: v2.0
milestone_name: DOTW AI Module
status: Ready to execute
stopped_at: Completed 24-04-PLAN.md (B2B/B2C Connection Guide + Evidence Checklist)
last_updated: "2026-03-28T04:07:35.645Z"
progress:
  total_phases: 14
  completed_phases: 13
  total_plans: 35
  completed_plans: 32
---

# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-03-25)

**Core value:** Enable travel agents and customers to search, book, and manage DOTW hotel reservations entirely through WhatsApp with AI-driven conversation, automated lifecycle, and full accounting
**Current focus:** Phase 24 — dotw-certification-fixes-v2-olga-march-27-feedback

## Current Position

Phase: 24 (dotw-certification-fixes-v2-olga-march-27-feedback) — EXECUTING
Plan: 2 of 4

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
| Phase 19-b2b-b2c-booking P01 | 9 | 2 tasks | 11 files |
| Phase 19-b2b-b2c-booking P02 | 5min | 2 tasks | 8 files |
| Phase 19-b2b-b2c-booking P03 | 9min | 2 tasks | 5 files |
| Phase 20-cancellation-accounting P01 | 260 | 2 tasks | 8 files |
| Phase 21-lifecycle-history P01 | 15 | 3 tasks | 11 files |
| Phase 21-lifecycle-history P02 | 12 | 2 tasks | 9 files |
| Phase 22 P02 | 78 | 2 tasks | 2 files |
| Phase 22 P01 | 348s (5.8m) | 2 tasks | 2 files |
| Phase 24-dotw-certification-fixes-v2-olga-march-27-feedback P04 | 201 | 2 tasks | 2 files |

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
- [Phase 19-b2b-b2c-booking]: sanitizePassengerName is private in DotwService -- BookingService has own helper with identical logic for module self-containment
- [Phase 19-b2b-b2c-booking]: Search cache has only hotel summaries -- prebook always re-calls getRooms(blocking=true) regardless of option_number or hotel_id input
- [Phase 19-b2b-b2c-booking]: CreditService::getClientIdForCompany resolves via Agent->branch->company_id chain (Company model has no clients() relationship)
- [Phase 19-b2b-b2c-booking]: Direct MyFatoorah ExecutePayment API call (not createCharge) gives full control over CallBackUrl without modifying existing code
- [Phase 19-b2b-b2c-booking]: PaymentMethod queried with withoutGlobalScopes() to bypass Auth-based company scope in queue/API contexts
- [Phase 19-b2b-b2c-booking]: ConfirmBookingAfterPaymentJob::failed() only marks booking failed, no auto-refund -- admin handles manually
- [Phase 19-b2b-b2c-booking]: Text-based WhatsApp vouchers (not PDF attachments) chosen for maximum reliability
- [Phase 19-b2b-b2c-booking]: formatVoucherMessage always includes paymentGuaranteedBy when present (per locked CONTEXT.md)
- [Phase 20-cancellation-accounting]: DOTW API called before DB::transaction; HTTP cannot roll back so DOTW first, then Eloquent writes inside transaction
- [Phase 20-cancellation-accounting]: AccountingService skips JournalEntry if accounts not found but still creates Invoice for admin reconciliation
- [Phase 21-lifecycle-history]: ProcessDeadlinesCommand is a pure dispatcher — all lifecycle side effects in queue jobs (SendReminderJob, AutoInvoiceDeadlineJob)
- [Phase 21-lifecycle-history]: reminder_sent_at and auto_invoiced_at are idempotency markers — NULL on job failure allows scheduler retry next cycle
- [Phase 21-lifecycle-history]: AccountingService::createAutoInvoiceForDeadline uses company_id from booking directly (no DotwAIContext) since there is no HTTP context in queue jobs
- [Phase 21-lifecycle-history]: APR auto-invoice failure does not fail the booking — stays confirmed, error logged for reconciliation
- [Phase 21-lifecycle-history]: WebhookDispatchJob retries 4 times with backoff 30s/2m/5m, 10s HTTP timeout per attempt
- [Phase 21-lifecycle-history]: Webhook events are config-gated: empty webhook_url disables all webhooks; per-event gating via webhook_events array in dotwai config
- [Phase 21]: DOTW V4 API has NO voucher/PDF/invoice endpoint — local PDF generation via DomPDF is the only option
- [Phase 21]: PDF voucher includes B2B agent + agency company details when track is b2b/b2b_gateway
- [Phase 24-dotw-certification-fixes-v2-olga-march-27-feedback]: B2B/B2C connection guide answers Olga's onboarding question with multi-tenant WhatsApp-first architecture diagram
- [Phase 24-dotw-certification-fixes-v2-olga-march-27-feedback]: Evidence guide offers Option A (direct WhatsApp test) or Option B (screenshots+XML logs) — Olga's choice

### Pending Todos

None yet.

### Roadmap Evolution

- Phase 24 added: DOTW Certification Fixes v2 — Olga March 27 Feedback (9 issues to resolve for certification)

### Blockers/Concerns

- DOTW tests 17+18 still need specific hotel IDs (from Phase 16) -- not blocking v2.0 work
- Olga confirmed APRs removed from DOTW API (2026-03-27) — APR flow is dead code

## Session Continuity

Last session: 2026-03-28T04:07:35.642Z
Stopped at: Completed 24-04-PLAN.md (B2B/B2C Connection Guide + Evidence Checklist)
Resume file: None
Resume file: None
