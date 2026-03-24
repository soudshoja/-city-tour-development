# Requirements: DOTW AI Module v2.0

**Defined:** 2026-03-24
**Core Value:** Enable travel agents and customers to search, book, and manage DOTW hotel reservations entirely through WhatsApp with AI-driven natural conversation, automated lifecycle management, and full accounting integration.

## Module Foundation (FOUND)
- [x] **FOUND-01**: Module registers as self-contained Laravel package at app/Modules/DotwAI/ with own ServiceProvider, config, routes, models
- [x] **FOUND-02**: Module config allows per-company enable/disable of B2B and B2C tracks independently
- [x] **FOUND-03**: Phone number resolves to agent → company → DOTW credentials → track (B2B/B2C) automatically
- [x] **FOUND-04**: Hotel static data imported from DOTW Excel/CSV file into local database with artisan import command
- [x] **FOUND-05**: Hotel name fuzzy matching resolves client text ("Hilton Dubai") to DOTW hotel IDs locally
- [x] **FOUND-06**: Module provides default AI system message template (Arabic/English bilingual) with tool descriptions auto-generated from endpoint metadata

## Search (SRCH)
- [x] **SRCH-01**: `search_hotels` endpoint accepts city, dates, occupancy, filters and returns flat hotel list with pre-formatted WhatsApp message
- [x] **SRCH-02**: `get_hotel_details` endpoint returns rooms, rates, cancellation policies, meal plans, specials for a specific hotel
- [x] **SRCH-03**: `get_cities` endpoint returns available destinations
- [x] **SRCH-04**: Search results cached per phone number so client can reference by option number ("book option 1")
- [x] **SRCH-05**: Multi-room/family scenarios supported (distribute occupancy across rooms)
- [x] **SRCH-06**: Filters: star rating, meal type, price range, refundable only, hotel name

## B2B Booking (B2B)
- [ ] **B2B-01**: Agent with credit line can book directly — no upfront payment, tracked in accounting
- [ ] **B2B-02**: Agent without credit line gets payment link via WhatsApp before confirmation
- [ ] **B2B-03**: `prebook_hotel` locks rate using option number from cached search results
- [ ] **B2B-04**: `confirm_booking` accepts passenger details and confirms with DOTW
- [ ] **B2B-05**: `get_company_balance` returns credit limit, used, available for B2B agents
- [ ] **B2B-06**: Company credit deduction uses pessimistic locking (prevent concurrent overdraw)
- [ ] **B2B-07**: Booking creates voucher and sends via WhatsApp after confirmation

## B2C Booking (B2C)
- [ ] **B2C-01**: `payment_link` generates MyFatoorah/KNET payment URL sent via WhatsApp
- [ ] **B2C-02**: After payment webhook received, Laravel re-blocks rate and confirms with DOTW automatically
- [ ] **B2C-03**: Configurable markup per company (default 20%) applied to all B2C prices
- [ ] **B2C-04**: Booking creates invoice + task + voucher automatically after confirmation
- [ ] **B2C-05**: MSP enforced — selling price never below DOTW minimum selling price

## Cancellation (CANC)
- [ ] **CANC-01**: `cancel_booking` shows penalty amount before confirming (2-step)
- [ ] **CANC-02**: Cancellation confirmation sent via WhatsApp with warning about DOTW confirmation
- [ ] **CANC-03**: Cancellation with penalty > 0 creates journal entry + invoice
- [ ] **CANC-04**: Free cancellation (penalty = 0) updates CRM/booking status only, no journal entry

## Lifecycle Automation (LIFE)
- [ ] **LIFE-01**: Cancellation deadline date stored from DOTW getRooms response per booking
- [ ] **LIFE-02**: Auto-reminders via WhatsApp: 3 days, 2 days, 1 day before cancellation deadline
- [ ] **LIFE-03**: After deadline passes without cancellation: auto-create invoice + send voucher via WhatsApp + accounting entries
- [ ] **LIFE-04**: Non-refundable (APR) bookings: auto-invoice immediately on confirmation
- [ ] **LIFE-05**: Scheduler/cron job checks upcoming deadlines daily and dispatches reminders + auto-invoicing

## Accounting (ACCT)
- [ ] **ACCT-01**: Hybrid approach — all cancellations tracked in CRM, journal entries only for money movement
- [ ] **ACCT-02**: Company statement generation to match with DOTW portal
- [ ] **ACCT-03**: No journal entry created until money moves or liability is confirmed
- [ ] **ACCT-04**: JournalEntry creation from queue/scheduler uses explicit company_id (not auth global scope)
- [ ] **ACCT-05**: Company credit limit management for B2B agents

## Booking History & Vouchers (HIST)
- [ ] **HIST-01**: `booking_status` returns booking details, cancellation policy, deadline, current penalty
- [ ] **HIST-02**: `get_booking_history` lists bookings filtered by status/date for agent or client
- [ ] **HIST-03**: `resend_voucher` re-sends booking confirmation via WhatsApp
- [ ] **HIST-04**: DOTW voucher/PDF retrieved if API supports, otherwise generated locally

## Monitoring Dashboard (DASH)
- [ ] **DASH-01**: Livewire dashboard showing incoming API call logs (requests, responses, errors) — no n8n branding
- [ ] **DASH-02**: Outgoing DOTW API call monitoring (timeouts, empty responses, failures)
- [ ] **DASH-03**: Booking lifecycle view (search → prebook → book → cancel with timestamps)
- [ ] **DASH-04**: Error tracking with filters (date, company, agent, error type)
- [ ] **DASH-05**: DOTW calls with no output / empty responses flagged for investigation

## Events & Integration (EVNT)
- [ ] **EVNT-01**: Laravel pushes async events to automation webhook: payment_completed, reminder_due, deadline_passed, booking_confirmed
- [x] **EVNT-02**: Every REST response includes `whatsappMessage` (pre-formatted) and `whatsappOptions`
- [x] **EVNT-03**: Error responses include WhatsApp-ready text with `suggestedAction` for AI

## Future Requirements
- Multi-supplier aggregation (TBO + DOTW combined search)
- Booking modification (cancel + rebook — DOTW has no amendment API)
- Multi-language beyond Arabic/English
- Mobile app integration

## Out of Scope
- Livewire UI for hotel booking (B2B is WhatsApp-only)
- DOTW amendment API (doesn't exist — modification = cancel + rebook)
- Flight/visa/insurance through this module
- n8n workflow building (module provides tools, n8n workflow is separate)

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| FOUND-01 | Phase 18 | Complete (18-01) |
| FOUND-02 | Phase 18 | Complete (18-01) |
| FOUND-03 | Phase 18 | Complete (18-01) |
| FOUND-04 | Phase 18 | Complete (18-01) |
| FOUND-05 | Phase 18 | Complete (18-01) |
| FOUND-06 | Phase 18 | Complete (18-01) |
| SRCH-01 | Phase 18 | Complete (18-02) |
| SRCH-02 | Phase 18 | Complete (18-02) |
| SRCH-03 | Phase 18 | Complete (18-02) |
| SRCH-04 | Phase 18 | Complete (18-02) |
| SRCH-05 | Phase 18 | Complete (18-02) |
| SRCH-06 | Phase 18 | Complete (18-02) |
| EVNT-02 | Phase 18 | Complete (18-01) |
| EVNT-03 | Phase 18 | Complete (18-01) |
| B2B-01 | Phase 19 | Pending |
| B2B-02 | Phase 19 | Pending |
| B2B-03 | Phase 19 | Pending |
| B2B-04 | Phase 19 | Pending |
| B2B-05 | Phase 19 | Pending |
| B2B-06 | Phase 19 | Pending |
| B2B-07 | Phase 19 | Pending |
| B2C-01 | Phase 19 | Pending |
| B2C-02 | Phase 19 | Pending |
| B2C-03 | Phase 19 | Pending |
| B2C-04 | Phase 19 | Pending |
| B2C-05 | Phase 19 | Pending |
| CANC-01 | Phase 20 | Pending |
| CANC-02 | Phase 20 | Pending |
| CANC-03 | Phase 20 | Pending |
| CANC-04 | Phase 20 | Pending |
| ACCT-01 | Phase 20 | Pending |
| ACCT-02 | Phase 20 | Pending |
| ACCT-03 | Phase 20 | Pending |
| ACCT-04 | Phase 20 | Pending |
| ACCT-05 | Phase 20 | Pending |
| LIFE-01 | Phase 21 | Pending |
| LIFE-02 | Phase 21 | Pending |
| LIFE-03 | Phase 21 | Pending |
| LIFE-04 | Phase 21 | Pending |
| LIFE-05 | Phase 21 | Pending |
| HIST-01 | Phase 21 | Pending |
| HIST-02 | Phase 21 | Pending |
| HIST-03 | Phase 21 | Pending |
| HIST-04 | Phase 21 | Pending |
| EVNT-01 | Phase 21 | Pending |
| DASH-01 | Phase 22 | Pending |
| DASH-02 | Phase 22 | Pending |
| DASH-03 | Phase 22 | Pending |
| DASH-04 | Phase 22 | Pending |
| DASH-05 | Phase 22 | Pending |
