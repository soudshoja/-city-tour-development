# Soud Laravel - Travel Agency Management Platform

## What This Is

A multi-tenant Laravel 11 platform for travel agencies to manage bookings, invoices, payments, accounting, and real-time hotel sourcing. Features AI-powered document processing, payment gateway integration, double-entry bookkeeping, and a GraphQL B2B API for WhatsApp/N8N workflows to search and book DOTW hotel inventory with per-company credential management and transparent markup.

## Core Value

**Agents can invoice clients accurately from any source and book hotels in real-time via WhatsApp — all with automated payment tracking and accounting integration.**

## Current State (v1.0 — Shipped 2026-02-21)

Two milestones shipped:
- **v1.0 Bulk Invoice Upload** (2026-02-13) — Excel bulk invoice creation, validation, preview, PDF + email delivery
- **DOTW v1.0 B2B** (2026-02-21) — Hotel search/booking GraphQL API, 8 phases, 78/81 requirements

Codebase: ~120 Eloquent models, Laravel 11, Livewire 3.5, GraphQL (Lighthouse), Sanctum API tokens, circuit breaker pattern, 3-table DOTW booking flow (prebooks → bookings → audit_logs).

## Next Milestone

TBD — use `/gsd:new-milestone` to plan.

Known candidates:
- DOTW booking → task/invoice integration (link confirmed bookings to task system)
- Save Booking workflow (non-refundable itinerary confirmation)
- Cancellation and amendment workflows
- Mobile app API layer

## Requirements

### Validated

<!-- Shipped and confirmed valuable -->

**Existing Platform (pre-v1.0):**
- ✓ Multi-tenant architecture (company → branch → agent hierarchy)
- ✓ Task management for 12 service types (flight, hotel, visa, insurance, tour, cruise, car, rail, esim, event, lounge, ferry)
- ✓ Client, agent, supplier management with relationships
- ✓ Invoice creation from tasks (manual selection via UI)
- ✓ Invoice number auto-generation (INV-YYYY-XXXXX format, per-company sequence)
- ✓ Payment gateway integration (MyFatoorah, Tap, Hesabe, uPayment, Knet)
- ✓ Partial payment tracking (invoice_partials, payment_applications, credits)
- ✓ Double-entry bookkeeping (accounts, journal entries, general ledger)
- ✓ AI document processing (AIR files, PDFs, passport images via OpenAI/OpenWebUI)
- ✓ Email attachment processing and task creation
- ✓ Excel import for clients, agents, companies, tasks (Maatwebsite/Laravel-Excel)
- ✓ WhatsApp Business API integration for client communication
- ✓ Travel API integration (TBO Holidays, Magic Holiday)
- ✓ GraphQL API via Lighthouse

**v1.0 Bulk Invoice Upload (2026-02-13):**
- ✓ Bulk invoice upload from Excel — v1.0
- ✓ Excel row validation (tasks, clients, suppliers, data types) — v1.0
- ✓ Client matching by mobile — v1.0
- ✓ Group tasks into invoices by client — v1.0
- ✓ Preview before commit (approve/reject) — v1.0
- ✓ Invoice PDF generation — v1.0
- ✓ Email invoice to accountant + agent — v1.0
- ✓ Upload history tracking + audit trail — v1.0
- ✓ Error reporting with downloadable Excel reports — v1.0

**DOTW v1.0 B2B (2026-02-21):**
- ✓ Per-company DOTW credential management (encrypted, isolated) — v1.0
- ✓ B2C markup per company (default 20%, configurable) — v1.0
- ✓ WhatsApp message ID linking in all DOTW audit logs — v1.0
- ✓ Hotel search result caching (150s, per-company key) — v1.0
- ✓ GraphQL response envelope (trace_id, timing, structured errors, cached flag) — v1.0
- ✓ searchHotels GraphQL query with full DOTW filter vocabulary — v1.0
- ✓ getRoomRates query with cancellation policies, allocationDetails token, markup breakdown — v1.0
- ✓ blockRates mutation with 3-minute prebook, countdown timer, race-condition guard — v1.0
- ✓ createPreBooking mutation with passenger validation, booking confirmation, alternatives on failure — v1.0
- ✓ DotwTimeoutException (25s SLA), circuit breaker (5 failures/60s → 30s open) — v1.0
- ✓ Sanctum Bearer token auth for N8N per-company GraphQL access — v1.0
- ✓ Standalone modular architecture (no coupling to invoice/task system) — v1.0
- ✓ Unified DOTW admin at /settings → DOTW tab and /admin/dotw — v1.0

### Active

<!-- Next milestone requirements go here after /gsd:new-milestone -->

(None — planning next milestone)

### Out of Scope

- Auto-create clients from Excel — Requires manual review to ensure data quality
- Modify existing invoices via Excel — Too risky, only new invoice creation supported
- Multi-currency in Excel upload — Use company default currency
- Real-time Excel editing in browser — Upload file, preview, approve/reject pattern
- WhatsApp notifications on upload — Manual follow-up preferred
- Save Booking (non-refundable) — Additional DOTW operation; v2
- Booking amendments — Complex state management; v2
- Cancellation refunds — Requires cancellation API; v2
- Rate history/analytics — v2 optimization
- SMS notifications — WhatsApp preferred; v2
- Real-time rate monitoring — On-demand calls preferred; v2
- Mobile app — Web API first; post-v1
- Multi-language UI — English + Arabic in GraphQL data; UI translations v2
- DOTW credential admin API auth middleware — Deferred to next milestone (currently authorize():true)

## Context

**Codebase Architecture:**
- Multi-tenant with company_id isolation throughout
- Service layer for complex operations (AirFileParser, DotwService, PaymentApplicationService)
- AI integration layer (OpenAI, OpenWebUI) for document extraction
- ~120 Eloquent models with relationships
- GraphQL API via Lighthouse (Sanctum Bearer auth for B2B)
- Livewire 3.5 + Alpine.js frontend
- DOTW module: 5 resolvers, 3 tables (dotw_prebooks, dotw_bookings, dotw_audit_logs), circuit breaker, cache service

**Known Tech Debt:**
- Route cache broken — duplicate route named `pin` prevents `php artisan route:cache`
- DOTW credential admin routes have no auth middleware (authorize():true)
- Phase 7 DOTW has no VERIFICATION.md (features confirmed by integration checker)
- REQUIREMENTS.md checkbox tracking was not maintained during DOTW phases (docs gap only)
- Dead file: `app/GraphQL/Queries/SearchDotwHotels.php` (legacy, not registered in schema)

## Constraints

- **Tech Stack**: Laravel 11, PHP 8.2+, MySQL — Existing platform, must integrate seamlessly
- **Excel Format**: Must use Maatwebsite/Laravel-Excel library — Existing pattern in codebase
- **Client Matching**: Cannot auto-create clients — Business rule to maintain data quality
- **Multi-Tenant**: company_id must isolate data — Security requirement
- **Invoice Numbering**: Must use existing invoice_sequence table — Prevents duplicates
- **Accounting**: Must create journal entries on invoice creation — Existing accounting requirement
- **Email**: Use existing email service (Resend, AWS SES, Postmark) — Already configured
- **DOTW**: Standalone module — no coupling to invoice/task system until integration phase

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| One invoice per client + date grouping | Matches existing manual pattern, reduces clutter | ✓ Good (v1.0) |
| Flag unknown clients instead of auto-create | Prevents duplicate client creation | ✓ Good (v1.0) |
| Full validation before preview | Fail fast with clear errors, better UX | ✓ Good (v1.0) |
| Email to accountant + agent (not WhatsApp) | Professional delivery, avoid client spam | ✓ Good (v1.0) |
| Separate queue job for email delivery | Prevents DB locks during invoice creation | ✓ Good (v1.0) |
| afterCommit() on job dispatch | Ensures DB committed before background jobs | ✓ Good (v1.0) |
| In-memory PDF generation | No temp file cleanup needed | ✓ Good (v1.0) |
| DOTW B2B credential resolution via company_id | Per-company isolation, no shared credentials | ✓ Good (v1.0) |
| DOTW standalone module (no task coupling) | Deployable independently, clean separation | ✓ Good (v1.0) |
| GraphQL response envelope (trace_id + error shapes) | Consistent N8N handling, debuggable | ✓ Good (v1.0) |
| Circuit breaker on searchHotels only | Most frequent operation; others are user-initiated | ✓ Good (v1.0) |
| Sanctum Bearer for N8N auth | Existing Sanctum install, per-company token revocation | ✓ Good (v1.0) |
| Lighthouse guards: ['sanctum'] | Required for Bearer token auth on /graphql | ✓ Good (v1.0) — was null (bug fixed) |
| DB::transaction for prebook creation | Prevents duplicate active prebooks (BLOCK-08) | ✓ Good (v1.0) |
| Wrapper view pattern for Livewire full-page | Prevents AppLayout variable scope errors | ✓ Good (v1.0) |
| @livewire(ClassName::class) not string alias | String aliases fail to resolve in this project | ✓ Good (v1.0) |

---
*Last updated: 2026-02-21 after DOTW v1.0 B2B milestone*
