# Roadmap: DOTWconnect Skills

**Created:** 2026-03-09
**Mode:** YOLO (auto-advance)
**Granularity:** Coarse (3-4 phases)
**Parallelization:** Enabled

## Milestones

- ✅ **v1.0 DOTWconnect Skills** - Phases 1-4 (shipped 2026-03-09)
- ✅ **v1.0 ResailAI PDF Integration** - Phase 15 (shipped 2026-03-17)
- ✅ **v1.0 DOTW Certification Fixes** - Phase 16 (shipped 2026-03-17)
- 🚧 **v2.0 DOTW AI Module** - Phases 18-22 (in progress)

## Phases

<details>
<summary>v1.0 DOTWconnect Skills (Phases 1-4) - SHIPPED 2026-03-09</summary>

- [x] **Phase 1: API Foundation** - Extract and document complete DOTWconnect API specification
- [x] **Phase 2: Core Skills** - Create hotel search and booking skills
- [x] **Phase 3: Advanced Skills** - Complete reference data and advanced operations
- [x] **Phase 4: Testing & Packaging** - Comprehensive testing and final packaging

</details>

<details>
<summary>v1.0 ResailAI PDF Integration (Phase 15) - SHIPPED 2026-03-17</summary>

- [x] **Phase 15: ResailAI PDF Integration** - Wire up end-to-end PDF processing pipeline

</details>

<details>
<summary>v1.0 DOTW Certification Fixes (Phase 16) - SHIPPED 2026-03-17</summary>

- [x] **Phase 16: DOTW Certification Fixes** - Fix all 6 DOTW certification issues and generate submission package

</details>

### v2.0 DOTW AI Module

- [x] **Phase 18: Foundation + Search** - Self-contained module with hotel import, fuzzy matching, city/hotel search, and WhatsApp message formatting
- [ ] **Phase 19: B2B + B2C Booking** - Complete booking pipeline for both tracks: credit line, payment links, prebook, confirm, voucher delivery
- [ ] **Phase 20: Cancellation + Accounting** - Two-step cancellation with penalty handling and hybrid accounting integration
- [ ] **Phase 21: Lifecycle + History** - Automated reminders, auto-invoicing, booking history, voucher resend, and event webhooks
- [ ] **Phase 22: Dashboard** - Livewire monitoring dashboard for API calls, booking lifecycle, and error tracking

## Phase Details

<details>
<summary>v1.0 DOTWconnect Skills (Phases 1-4) - SHIPPED 2026-03-09</summary>

### Phase 1: API Foundation
**Goal:** Extract and document complete DOTWconnect API specification
**Requirements:** API-01, API-02, API-03, API-04
**Plans:** Complete

Plans:
- [x] 01-01: API extraction and documentation

### Phase 2: Core Skills
**Goal:** Create production-ready hotel search and booking skills
**Requirements:** SEARCH-01, SEARCH-02, SEARCH-03, SEARCH-04, BOOK-01, BOOK-02
**Plans:** Complete

Plans:
- [x] 02-01: Hotel search skill
- [x] 02-02: Booking skill

### Phase 3: Advanced Skills
**Goal:** Complete reference data and advanced operations
**Requirements:** BOOK-03, BOOK-04, REF-01, REF-02, REF-03, REF-04
**Plans:** Complete

Plans:
- [x] 03-01: Reference data and advanced operations

### Phase 4: Testing & Packaging
**Goal:** Comprehensive testing and final packaging
**Requirements:** TEST-01, TEST-02, TEST-03, TEST-04, PKG-01, PKG-02, PKG-03, PKG-04
**Plans:** Complete

Plans:
- [x] 04-01: Testing and packaging

</details>

<details>
<summary>v1.0 ResailAI PDF Integration (Phase 15) - SHIPPED 2026-03-17</summary>

### Phase 15: ResailAI PDF Integration
**Goal:** Wire up end-to-end PDF processing pipeline
**Requirements:** RESAIL-11 through RESAIL-20
**Plans:** 2/2 complete

Plans:
- [x] 15-01: TaskWebhookBridge full normalization
- [x] 15-02: CallbackController + ProcessingAdapter extraction flattening

</details>

<details>
<summary>v1.0 DOTW Certification Fixes (Phase 16) - SHIPPED 2026-03-17</summary>

### Phase 16: DOTW Certification Fixes
**Goal:** Fix all 6 DOTW certification issues and generate submission package
**Requirements:** DOTW-FIX-01 through DOTW-FIX-08
**Plans:** 3/3 complete

Plans:
- [x] 16-01: Pagination, roomField, rateBasis, salutation fixes
- [x] 16-02: changedOccupancy fix, SKIP-to-PASS conversion
- [x] 16-03: Certification log package and connection type document

</details>

---

### Phase 18: Foundation + Search
**Goal**: n8n AI agents can resolve phone numbers to companies, search hotels by city/name/filters, browse room details, and receive WhatsApp-formatted responses
**Depends on**: Phase 16 (DOTW certification layer must be complete)
**Requirements**: FOUND-01, FOUND-02, FOUND-03, FOUND-04, FOUND-05, FOUND-06, SRCH-01, SRCH-02, SRCH-03, SRCH-04, SRCH-05, SRCH-06, EVNT-02, EVNT-03
**Success Criteria** (what must be TRUE):
  1. Module boots as self-contained package at app/Modules/DotwAI/ with its own ServiceProvider, config, routes, and models -- no existing files modified except bootstrap/providers.php registration
  2. Hotel static data imports from DOTW Excel/CSV via artisan command, and fuzzy matching resolves natural text ("Hilton Dubai") to DOTW hotel IDs
  3. A phone number sent to the search endpoint resolves to agent, company, DOTW credentials, and track (B2B or B2C) automatically
  4. search_hotels returns a flat list of hotels with prices, and results are cached per phone number so the user can reference "option 1" in follow-up messages
  5. Every REST response includes a pre-formatted whatsappMessage field and error responses include suggestedAction for the AI agent
**Plans**: 3 plans

Plans:
- [x] 18-01-PLAN.md -- Module scaffold, config, migrations, models, phone resolution, hotel import, fuzzy matching
- [x] 18-02-PLAN.md -- Search endpoints (search_hotels, get_hotel_details, get_cities), caching, WhatsApp formatting
- [x] 18-03-PLAN.md -- Integration and unit test suite for all module components

---

### Phase 19: B2B + B2C Booking
**Goal**: Agents can book hotels on credit or via payment link (B2B), and customers can pay upfront and have bookings auto-confirmed (B2C), with vouchers delivered via WhatsApp
**Depends on**: Phase 18
**Requirements**: B2B-01, B2B-02, B2B-03, B2B-04, B2B-05, B2B-06, B2B-07, B2C-01, B2C-02, B2C-03, B2C-04, B2C-05
**Success Criteria** (what must be TRUE):
  1. B2B agent with credit line can prebook, confirm, and receive a voucher via WhatsApp without any payment step -- credit is deducted atomically with pessimistic locking
  2. B2B agent without credit line receives a payment link via WhatsApp, and booking proceeds only after payment
  3. B2C customer receives a payment link with markup applied (configurable per company, MSP enforced), and after payment the system re-blocks the rate and auto-confirms with DOTW
  4. Confirmed bookings create a task, invoice, and voucher automatically for both tracks
  5. get_company_balance returns accurate credit limit, used, and available amounts for B2B agents
**Plans**: 3 plans

Plans:
- [ ] 19-01-PLAN.md -- Core booking infrastructure: DotwAIBooking model, BookingService, CreditService, BookingController (prebook, confirm, balance)
- [ ] 19-02-PLAN.md -- Payment integration: PaymentBridgeService, PaymentCallbackController, ConfirmBookingAfterPaymentJob, payment_link endpoint
- [ ] 19-03-PLAN.md -- Voucher delivery + test suite: VoucherService, WhatsApp voucher formatting, booking flow tests

---

### Phase 20: Cancellation + Accounting
**Goal**: Bookings can be cancelled with full penalty visibility, and all money movement generates correct journal entries while non-financial events stay in CRM only
**Depends on**: Phase 19
**Requirements**: CANC-01, CANC-02, CANC-03, CANC-04, ACCT-01, ACCT-02, ACCT-03, ACCT-04, ACCT-05
**Success Criteria** (what must be TRUE):
  1. cancel_booking first shows the penalty amount and waits for explicit confirmation before executing (2-step flow)
  2. Cancellation with penalty creates journal entry and invoice; free cancellation updates CRM/booking status only with no journal entry
  3. Cancellation confirmation is sent via WhatsApp with a warning that DOTW cancellation confirmation may take additional time
  4. Company statements can be generated to reconcile against the DOTW portal
  5. No journal entry is created until money actually moves or liability is confirmed -- queue/scheduler jobs use explicit company_id (not auth scope)
**Plans**: TBD

---

### Phase 21: Lifecycle + History
**Goal**: The system automatically manages booking deadlines with WhatsApp reminders, auto-invoices after deadlines pass, and agents/customers can check booking status and resend vouchers at any time
**Depends on**: Phase 20
**Requirements**: LIFE-01, LIFE-02, LIFE-03, LIFE-04, LIFE-05, HIST-01, HIST-02, HIST-03, HIST-04, EVNT-01
**Success Criteria** (what must be TRUE):
  1. Cancellation deadline date is stored per booking and a daily scheduler dispatches WhatsApp reminders at 3, 2, and 1 days before the deadline
  2. After the cancellation deadline passes without cancellation, the system auto-creates an invoice, sends the voucher via WhatsApp, and records accounting entries
  3. Non-refundable (APR) bookings are auto-invoiced immediately on confirmation with no reminder cycle
  4. booking_status returns current details including cancellation policy, deadline, and penalty; get_booking_history lists bookings with status/date filters
  5. Laravel pushes async events (payment_completed, reminder_due, deadline_passed, booking_confirmed) to the automation webhook for n8n consumption
**Plans**: TBD

---

### Phase 22: Dashboard
**Goal**: Administrators can monitor the entire DOTW AI Module through a dedicated Livewire dashboard with API call logs, booking lifecycle tracking, and error investigation tools
**Depends on**: Phase 18 (can be built in parallel with Phases 19-21 once foundation exists)
**Requirements**: DASH-01, DASH-02, DASH-03, DASH-04, DASH-05
**Success Criteria** (what must be TRUE):
  1. Livewire dashboard displays incoming API call logs (requests, responses, errors) with no n8n branding visible
  2. Outgoing DOTW API calls are monitored with timeouts, empty responses, and failures surfaced
  3. Each booking shows its full lifecycle (search, prebook, book, cancel) with timestamps in a single view
  4. Errors can be filtered by date, company, agent, and error type
**Plans**: TBD
**UI hint**: yes

---

## Progress

**Execution Order:**
Phases execute in numeric order: 18 → 19 → 20 → 21 → 22

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. API Foundation | v1.0 Skills | 1/1 | Complete | 2026-03-09 |
| 2. Core Skills | v1.0 Skills | 2/2 | Complete | 2026-03-09 |
| 3. Advanced Skills | v1.0 Skills | 1/1 | Complete | 2026-03-09 |
| 4. Testing & Packaging | v1.0 Skills | 1/1 | Complete | 2026-03-09 |
| 15. ResailAI PDF | v1.0 ResailAI | 2/2 | Complete | 2026-03-17 |
| 16. DOTW Cert Fixes | v1.0 DOTW Cert | 3/3 | Complete | 2026-03-17 |
| 18. Foundation + Search | v2.0 DOTW AI | 3/3 | Complete | 2026-03-24 |
| 19. B2B + B2C Booking | 2/3 | In Progress|  | - |
| 20. Cancellation + Accounting | v2.0 DOTW AI | 0/? | Not started | - |
| 21. Lifecycle + History | v2.0 DOTW AI | 0/? | Not started | - |
| 22. Dashboard | v2.0 DOTW AI | 0/? | Not started | - |

---
*Roadmap created: 2026-03-09*
*v2.0 DOTW AI Module phases added: 2026-03-24*
*Phase 18 plans created: 2026-03-24*
*Phase 19 plans created: 2026-03-24*
