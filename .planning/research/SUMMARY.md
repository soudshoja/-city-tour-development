# Research Summary: DOTW AI Module v2.0

**Project:** DOTW AI Hotel Booking Module (app/Modules/DotwAI/)
**Synthesized:** 2026-03-24
**Research files:** STACK.md, FEATURES.md, ARCHITECTURE.md, PITFALLS.md

---

## Executive Summary

The DOTW AI Module is a **booking orchestration layer** that sits on top of the existing DOTW V4 certification layer (13 GraphQL queries, 6 mutations, DotwService, DotwPrebook, DotwBooking) and chains those granular API wrappers into complete business flows: search-to-book, book-to-invoice, and cancel-with-refund. The module serves two independent tracks -- B2B (WhatsApp agents, credit-line booking, zero markup) and B2C (WhatsApp customers, payment-link-first, configurable markup) -- both driven by n8n AI agents. The critical architectural insight is that **zero new composer dependencies are needed**. Every capability (module structure, scheduling, queues, WhatsApp messaging, payment links, GraphQL extension, double-entry accounting) already exists in the codebase. The work is wiring, not dependency acquisition.

The recommended approach follows the established ResailAI module pattern: a self-contained module at `app/Modules/DotwAI/` with its own ServiceProvider, config, routes, services, jobs, events, and listeners. The module delegates to the existing `DotwService` through composition (not inheritance or decoration), writes to existing tables (tasks, invoices, journal_entries, credits) through bridge services, and registers its own scheduled jobs via the ServiceProvider's `boot()` method -- touching only 2 existing files (adding one `#import` line to `schema.graphql` and one provider registration to `bootstrap/providers.php`). The total new file count is approximately 39, with the remaining modification being 2 lines of existing code.

The dominant risk is the **DOTW 3-minute rate allocation expiry vs. B2C payment collection time** (5-15 minutes typical). This single architectural decision -- "re-block the rate after payment succeeds, not before" -- determines whether the B2C flow works or generates refund tickets. Secondary risks are concurrent credit deduction (race condition in B2B), premature journal entry creation (accounting entries before DOTW confirms), and payment webhook timeout causing double-bookings. All are solvable with established patterns (pessimistic locking, queued async confirmation, idempotency gates) documented in detail in PITFALLS.md.

---

## Key Findings

### From STACK.md: Technology Decisions

| Technology | Decision | Rationale |
|-----------|----------|-----------|
| Module framework | **None** (follow ResailAI manual pattern) | nwidart/laravel-modules is over-engineering for a single module addition |
| Queue driver | **Database** (existing) | Module adds <100 jobs/day; no Redis/Horizon needed |
| WhatsApp transport | **ResayilController::shareReminder()** (existing) | Already proven. No WhatsApp SDK or notification channel needed |
| GraphQL extension | **Lighthouse #import + extend type** (existing pattern) | New `dotwai.graphql` file, imported from `schema.graphql` |
| Payment gateways | **Reuse existing 5 gateways** (MyFatoorah, KNET, Hesabe, Tap, uPayment) | Module generates payment links via existing `route('payment.link.show')` pattern |
| Scheduled jobs | **ServiceProvider boot() + Schedule injection** | Avoids modifying Console/Kernel.php |
| Hotel static data | **4 new Eloquent models** with Levenshtein fuzzy matching | dotw_static_cities, dotw_static_countries, dotw_static_currencies, dotw_static_salutations |

**No new composer dependencies.** No version upgrades needed. PHP 8.2, Laravel 11.39.1, Lighthouse 6.63.1 all remain as-is.

### From FEATURES.md: Feature Prioritization

**Table Stakes (must ship):**
- Unified hotel search (city + dates + occupancy) with fuzzy city/country name resolution
- Multi-hotel disambiguation (return options when multiple matches)
- Rate blocking (3-min allocation via existing DotwService)
- B2B info-only response (search results + prebookKey, no DOTW confirmation)
- B2C payment link generation + DOTW confirmation after payment
- 2-step cancellation with charge display and APR blocking
- Cancellation deadline tracking from DOTW policies
- n8n REST endpoints for confirm/cancel/status
- Prebook expiry cleanup

**Differentiators (high value, ship if possible):**
- Automated cancellation reminders at 3/2/1 days before deadline
- Auto-invoice after cancellation deadline passes (revenue protection)
- WhatsApp voucher delivery after booking confirmation
- Credit line booking for B2B agents
- Currency conversion with markup transparency

**Deferred to v2+:**
- Livewire/web UI for hotel booking (WhatsApp-only for now)
- Multi-supplier aggregation (DOTW-only this milestone)
- WhatsApp media messages (text-only vouchers)
- Automated re-booking on DOTW failure
- Guest name collection via multi-turn WhatsApp conversation

### From ARCHITECTURE.md: System Design

**Core principle:** Orchestration layer over certification layer. The module chains existing granular API wrappers into complete business flows without modifying them.

**Major components:**
- `DotwAISearchService` -- Orchestrates city resolve -> search -> browse -> block -> prebook
- `DotwAIBookingService` -- Orchestrates prebook -> payment/credit -> confirm -> task/invoice
- `DotwAICancellationService` -- Orchestrates check-charge -> confirm-cancel -> refund/journal
- `DotwAIAccountingBridge` -- Creates Task, Invoice, JournalEntry without modifying those models
- `DotwAIPaymentBridge` -- Generates payment links via existing gateway classes
- `DotwAICreditService` -- B2B credit balance check with pessimistic locking
- `ReminderService` -- Cancellation deadline tracking and WhatsApp reminder scheduling
- `MessageBuilderService` -- Pure functions for WhatsApp message formatting

**Key patterns:**
- **Delegation** to DotwService (not inheritance, not decoration)
- **Event-driven side effects** (DotwBookingConfirmed fires -> Task, Invoice, JournalEntry, Voucher listeners independently)
- **Queued DOTW confirmation** (payment webhooks acknowledge immediately, DOTW call is async)
- **Soft foreign keys** (nullable, no DB constraints) to maintain module isolation
- **Bridge pattern** for accounting integration (DotwAIAccountingBridge knows model field requirements)

**Database:** 7 new tables, 0 modifications to existing tables. The `dotwai_bookings` table is the lifecycle hub; existing `dotw_prebooks` and `dotw_bookings` remain untouched.

### From PITFALLS.md: Top 5 Risks

| # | Pitfall | Severity | Prevention |
|---|---------|----------|------------|
| 1 | **3-min rate allocation expires before B2C payment completes** | CRITICAL | Re-block rate after payment webhook fires, before calling confirmBooking. Auto-refund if re-block fails. |
| 2 | **Concurrent credit deduction drains B2B credit beyond limit** | CRITICAL | Pessimistic locking (`lockForUpdate()`) within a DB transaction for credit check + deduction |
| 3 | **Journal entries created before DOTW booking is confirmed** | CRITICAL | "No journal until money moves" rule. CRM events for pending state; journal only after confirmation. |
| 4 | **Payment webhook arrives but DOTW API is down** | CRITICAL | Queue-based async confirmation with exponential backoff (4 retries: 0s, 30s, 120s, 300s). Idempotency gate. |
| 5 | **Module isolation violation -- new code mutates existing models** | CRITICAL | Strict boundary: own models + bridge services + event-driven side effects. Code review rule: no modifications outside `app/Modules/DotwAI/` |

**Additional high-severity pitfalls to track:** timezone mismatch in cancellation reminders (store UTC, derive from hotel timezone), double-booking via n8n webhook retry (idempotency gate + advisory lock), currency conversion race between search and booking (lock exchange rate at prebook time), WhatsApp delivery failures for critical reminders (multi-channel escalation).

---

## Implications for Roadmap

### Recommended Phase Structure

The build order is dictated by hard dependencies: static data enables search, search enables booking, booking enables accounting, accounting enables cancellation refunds and auto-invoicing.

---

**Phase 1: Module Foundation + Static Data**
- What to build: ServiceProvider, config, module directory structure, `dotwai_bookings` table + model, `dotwai_reminders` table + model, 4 static data tables + models, `SyncDotwStaticDataJob`, fuzzy city/country resolution service, `dotwai.graphql` schema file, routes skeleton
- What it delivers: A bootable module with static data sync and the city/country fuzzy matching needed by every subsequent phase
- Features addressed: Static data sync, city/country resolution, module registration
- Pitfalls to avoid: Module isolation violation (Pitfall 5) -- establish the boundary pattern here
- Rationale: Everything else depends on this. The static data tables are the first thing search needs. Getting the module structure right here prevents rework in every later phase.
- Research needed: LOW -- well-documented patterns (ResailAI module, existing sync jobs)

**Phase 2: Search Flow**
- What to build: `DotwAISearchService`, `SearchDotwHotelRooms` GraphQL query, `ResolveCompanyFromPhone` middleware, multi-hotel disambiguation logic, B2B/B2C markup calculation with MSP floor
- What it delivers: n8n's primary tool -- the unified search query that resolves fuzzy city names, searches DOTW, browses rooms, blocks rates, and returns formatted results with a prebookKey
- Features addressed: Unified hotel search, multi-hotel disambiguation, rate blocking, tariff notes display, special promotions display
- Pitfalls to avoid: DOTW API timeout cascading to duplicate searches (Pitfall 11), allocation token corruption (Pitfall 12), MSP violation in B2C (Pitfall 13), static data staleness (Pitfall 14)
- Rationale: This is the entry point for all booking flows. Must be rock-solid before building booking confirmation.
- Research needed: LOW -- DotwService public API is well-analyzed. The 3-step search->browse->block flow is established.

**Phase 3: B2B Booking Flow**
- What to build: `DotwAIBookingService` (B2B track), `BookingController` confirm endpoint, `StatusController`, `DotwBookingConfirmed` event + listeners (CreateTaskFromBooking, SendBookingVoucher), `MessageBuilderService`
- What it delivers: End-to-end B2B info-only booking -- agent searches, gets results, confirms, DOTW booking is placed (if credit available or info-only), task created, voucher sent via WhatsApp
- Features addressed: B2B info-only response, booking confirmation, WhatsApp voucher delivery, booking status endpoint
- Pitfalls to avoid: Double-booking via n8n retry (Pitfall 8), passenger name sanitization (Pitfall 17), missing DOTW credentials (Pitfall 20), n8n response format mismatch (Pitfall 18)
- Rationale: B2B is simpler than B2C (no payment step). Proves the full pipeline end-to-end before adding payment complexity.
- Research needed: MEDIUM -- exact fields required for Task and HotelBooking creation need verification during implementation

**Phase 4: B2C Payment Flow**
- What to build: `DotwAIPaymentBridge`, `PaymentWebhookController`, `ConfirmBookingAfterPaymentJob` (queued with retry backoff), payment link generation, re-block-after-payment logic, auto-refund on re-block failure
- What it delivers: The revenue-generating path -- customer pays via WhatsApp link, payment webhook fires, DOTW booking confirmed asynchronously, voucher sent
- Features addressed: B2C payment link generation, B2C DOTW confirmation after payment, payment status tracking
- Pitfalls to avoid: Rate allocation expiry (Pitfall 1 -- CRITICAL), DOTW API down after payment (Pitfall 4 -- CRITICAL), currency conversion race (Pitfall 9)
- Rationale: This is the hardest phase and the most financially sensitive. The re-block pattern and queued confirmation job are the single most important architectural decisions.
- Research needed: HIGH -- payment gateway internal API for creating payment sessions needs verification. Webhook callback URL registration needs testing.

**Phase 5: B2B Credit Line Booking**
- What to build: `DotwAICreditService` with pessimistic locking, credit balance check, credit deduction within DB transaction, fallback-to-gateway when credit insufficient
- What it delivers: Agents with company credit can book without upfront payment. Credit is deducted atomically.
- Features addressed: Credit line booking (B2B differentiator)
- Pitfalls to avoid: Concurrent credit deduction (Pitfall 2 -- CRITICAL)
- Rationale: Separated from Phase 3 because credit locking is a distinct concern. Phase 3 proves the booking pipeline; Phase 5 adds the credit payment method.
- Research needed: MEDIUM -- Credit model locking patterns need verification. `PaymentApplicationService` usage needs study.

**Phase 6: Accounting Integration**
- What to build: `DotwAIAccountingBridge`, `CreateInvoiceFromBooking` listener, `CreateJournalEntries` listener, hybrid accounting flow (CRM events for pending, journal entries only after confirmation)
- What it delivers: Confirmed bookings generate invoices, journal entries (DR Receivable/CR Revenue), and link to tasks. Failed bookings generate reversal entries.
- Features addressed: Auto-invoice creation, journal entry creation, invoice-task linking
- Pitfalls to avoid: Journal entries before confirmation (Pitfall 3 -- CRITICAL), JournalEntry global scope breaking module queries (Pitfall 16)
- Rationale: Accounting must follow booking confirmation, not precede it. This phase establishes the "no journal until money moves" rule.
- Research needed: HIGH -- exact required fields for Invoice, InvoiceDetail, JournalEntry creation. Account IDs for chart of accounts. Existing AutoBilling patterns to understand and NOT copy blindly.

**Phase 7: Cancellation Flow**
- What to build: `DotwAICancellationService`, cancel endpoint (2-step: check then confirm), `DotwBookingCancelled` event, `ProcessCancellationRefund` listener, cancellation charge extraction, APR blocking
- What it delivers: Full 2-step cancellation: check charge -> display to agent -> confirm cancel -> journal reversal + credit refund
- Features addressed: 2-step cancellation, APR booking block, cancellation charge handling
- Pitfalls to avoid: APR treated as refundable (Pitfall 15), charge vs formatted value confusion (Pitfall 19)
- Rationale: Cancellation requires accounting to be in place (for reversal entries and credit refunds). Must come after Phase 6.
- Research needed: LOW -- 2-step cancel flow is well-documented in DOTW certification. Already implemented as individual GraphQL mutations.

**Phase 8: Lifecycle Automation (Reminders + Auto-Invoice)**
- What to build: `ReminderService`, `SendCancellationReminderJob`, `AutoInvoiceDeadlineJob`, scheduler registration in ServiceProvider, DOTW booking status verification before auto-invoicing
- What it delivers: Automated WhatsApp reminders at 3/2/1 days before cancellation deadline. Auto-invoice generation when deadline passes and booking is not cancelled.
- Features addressed: Automated cancellation reminders (differentiator), auto-invoice after deadline (differentiator)
- Pitfalls to avoid: Timezone mismatch (Pitfall 6 -- HIGH), WhatsApp delivery failures (Pitfall 7 -- HIGH), auto-invoice for cancelled booking (Pitfall 10 -- HIGH)
- Rationale: This is the highest-value differentiator but requires bookings, accounting, and cancellation to all be working first.
- Research needed: MEDIUM -- timezone handling strategy needs finalization. Multi-channel fallback (email/SMS) scope decision needed.

**Phase 9: Dashboard + Hotel Cache**
- What to build: DOTW AI Dashboard (monitoring, no n8n branding), `HotelCacheService`, `SyncDotwHotelCacheJob`, `dotw_hotel_map` table, hotel cache graceful degradation
- What it delivers: Admin visibility into booking lifecycle, search performance, reminder delivery status. Enriched hotel data for faster searches.
- Features addressed: DOTW AI Dashboard, hotel static data cache
- Pitfalls to avoid: Hotel cache staleness (Pitfall 14)
- Rationale: Nice-to-have, not blocking core booking flow. Dashboard provides operational visibility after the core system is live.
- Research needed: MEDIUM -- DOTW hotel listing API availability unclear. Dashboard UI framework decision (Livewire vs. static Blade).

### Research Flags

| Phase | Research Needed | Reason |
|-------|----------------|--------|
| Phase 4 (B2C Payment) | `/gsd:research-phase` recommended | Payment gateway internal API for session creation, webhook callback registration, re-block timing |
| Phase 5 (B2B Credit) | `/gsd:research-phase` recommended | Credit model locking, PaymentApplicationService integration, credit type constants |
| Phase 6 (Accounting) | `/gsd:research-phase` recommended | Invoice/JournalEntry required fields, chart of accounts IDs, AutoBilling pattern analysis |
| Phase 8 (Lifecycle) | Brief research | Timezone handling finalization, multi-channel scope decision |
| Phase 1, 2, 3, 7 | Standard patterns -- skip research | Well-documented by codebase analysis and existing patterns |
| Phase 9 | Brief research | Dashboard framework decision, DOTW hotel listing API availability |

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | **HIGH** | Zero new dependencies. All capabilities verified from existing codebase. Module pattern proven by ResailAI. |
| Features | **HIGH** | Feature list derived from SKILL.md and PROJECT.md. Table stakes vs differentiators clearly separated. Dependency graph is tight. |
| Architecture | **HIGH** | Module structure, delegation pattern, event-driven side effects, and database design all validated against existing codebase patterns. 39 new files, 2 modified files. |
| Pitfalls | **HIGH** | 20 pitfalls identified with concrete prevention strategies and code examples. All critical pitfalls have phase assignments. Based on direct codebase analysis + DOTW certification experience. |

**Overall Confidence: HIGH**

### Gaps to Address During Planning

1. **Payment gateway session creation API**: The exact method signature for creating payment links programmatically (outside the existing WhatsAppHotelController flow) needs verification. The `route('payment.link.show')` pattern is confirmed, but the internal payment session creation may differ for the module's webhook URL.

2. **Invoice/JournalEntry field requirements**: The exact fillable fields, required relationships, and sequence number generation for creating invoices and journal entries from a queue job context (no authenticated user, no company global scope) need phase-specific investigation.

3. **DOTW hotel listing API**: Whether DOTW exposes a bulk hotel listing endpoint for cache sync, or whether it requires batch `searchHotels` calls per city. This affects the hotel cache sync strategy in Phase 9.

4. **Dashboard scope**: Whether the DOTW AI Dashboard is a Livewire component (consistent with existing admin UI) or a standalone Blade page. This is a UI decision, not an architectural one.

5. **Multi-channel reminder fallback**: Whether Phase 8 reminders should include email/SMS fallback from day one, or start WhatsApp-only and add channels later. The pitfalls research recommends multi-channel, but the scope decision is product-level.

---

## Sources

### Codebase Analysis (HIGH confidence)
- `app/Modules/ResailAI/` -- module pattern reference
- `app/Services/DotwService.php` (2,232 lines) -- DOTW V4 API wrapper
- `app/Services/DotwCacheService.php`, `DotwCircuitBreakerService.php`, `DotwAuditService.php`
- `app/GraphQL/Queries/Dotw*.php` (13 resolvers), `app/GraphQL/Mutations/Dotw*.php` (6 resolvers)
- `graphql/dotw.graphql` (~950 lines), `graphql/schema.graphql`
- `app/Models/DotwPrebook.php`, `DotwBooking.php`, `DotwRoom.php`, `DotwAuditLog.php`, `CompanyDotwCredential.php`
- `app/Models/Task.php`, `Invoice.php`, `JournalEntry.php`, `Credit.php`, `HotelBooking.php`, `Reminder.php`
- `app/Services/PaymentApplicationService.php`
- `app/Support/PaymentGateway/{MyFatoorah,Knet,Hesabe,Tap,UPayment}.php`
- `app/Http/Controllers/PaymentController.php`, `WhatsAppHotelController.php`, `ResayilController.php`
- `app/Console/Kernel.php`, `app/Console/Commands/SendReminders.php`, `RunAutoBilling.php`
- `config/dotw.php`, `config/queue.php`, `bootstrap/providers.php`, `composer.json`

### Project Documentation (HIGH confidence)
- `.claude/skills/dotwai/SKILL.md` -- AI module skill specification
- `.claude/skills/dotw-api/references/best-practices.md` -- DOTW certification best practices
- `.planning/PROJECT.md` -- project specification

### External References (MEDIUM confidence)
- Laravel 11 documentation: Task Scheduling, Package Development, Pessimistic Locking
- Lighthouse documentation: Schema Organisation, #import directive
- WhatsApp Cloud API: webhook delivery semantics, delivery receipt limitations
- Industry: hotel cancellation timezone handling, webhook idempotency patterns
