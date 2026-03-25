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
- [x] **B2B-01**: Agent with credit line can book directly — no upfront payment, tracked in accounting
- [x] **B2B-02**: Agent without credit line gets payment link via WhatsApp before confirmation
- [x] **B2B-03**: `prebook_hotel` locks rate using option number from cached search results
- [x] **B2B-04**: `confirm_booking` accepts passenger details and confirms with DOTW
- [x] **B2B-05**: `get_company_balance` returns credit limit, used, available for B2B agents
- [x] **B2B-06**: Company credit deduction uses pessimistic locking (prevent concurrent overdraw)
- [x] **B2B-07**: Booking creates voucher and sends via WhatsApp after confirmation

## B2C Booking (B2C)
- [x] **B2C-01**: `payment_link` generates MyFatoorah/KNET payment URL sent via WhatsApp
- [x] **B2C-02**: After payment webhook received, Laravel re-blocks rate and confirms with DOTW automatically
- [x] **B2C-03**: Configurable markup per company (default 20%) applied to all B2C prices
- [x] **B2C-04**: Booking creates invoice + task + voucher automatically after confirmation
- [x] **B2C-05**: MSP enforced — selling price never below DOTW minimum selling price

## Cancellation (CANC)
- [x] **CANC-01**: `cancel_booking` shows penalty amount before confirming (2-step)
- [x] **CANC-02**: Cancellation confirmation sent via WhatsApp with warning about DOTW confirmation
- [x] **CANC-03**: Cancellation with penalty > 0 creates journal entry + invoice
- [x] **CANC-04**: Free cancellation (penalty = 0) updates CRM/booking status only, no journal entry

## Lifecycle Automation (LIFE)
- [x] **LIFE-01**: Cancellation deadline date stored from DOTW getRooms response per booking
- [x] **LIFE-02**: Auto-reminders via WhatsApp: 3 days, 2 days, 1 day before cancellation deadline
- [x] **LIFE-03**: After deadline passes without cancellation: auto-create invoice + send voucher via WhatsApp + accounting entries
- [x] **LIFE-04**: Non-refundable (APR) bookings: auto-invoice immediately on confirmation
- [x] **LIFE-05**: Scheduler/cron job checks upcoming deadlines daily and dispatches reminders + auto-invoicing

## Accounting (ACCT)
- [x] **ACCT-01**: Hybrid approach — all cancellations tracked in CRM, journal entries only for money movement
- [x] **ACCT-02**: Company statement generation to match with DOTW portal
- [x] **ACCT-03**: No journal entry created until money moves or liability is confirmed
- [x] **ACCT-04**: JournalEntry creation from queue/scheduler uses explicit company_id (not auth global scope)
- [x] **ACCT-05**: Company credit limit management for B2B agents

## Booking History & Vouchers (HIST)
- [x] **HIST-01**: `booking_status` returns booking details, cancellation policy, deadline, current penalty
- [x] **HIST-02**: `get_booking_history` lists bookings filtered by status/date for agent or client
- [x] **HIST-03**: `resend_voucher` re-sends booking confirmation via WhatsApp
- [x] **HIST-04**: DOTW voucher/PDF retrieved if API supports, otherwise generated locally

## Monitoring Dashboard (DASH)
- [x] **DASH-01**: Livewire dashboard showing incoming API call logs (requests, responses, errors) — no n8n branding
- [x] **DASH-02**: Outgoing DOTW API call monitoring (timeouts, empty responses, failures)
- [x] **DASH-03**: Booking lifecycle view (search → prebook → book → cancel with timestamps)
- [ ] **DASH-04**: Error tracking with filters (date, company, agent, error type)
- [ ] **DASH-05**: DOTW calls with no output / empty responses flagged for investigation

## Events & Integration (EVNT)
- [x] **EVNT-01**: Laravel pushes async events to automation webhook: payment_completed, reminder_due, deadline_passed, booking_confirmed
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
| FOUND-01 | Phase 18 | Complete (18-01), Tested (18-03) |
| FOUND-02 | Phase 18 | Complete (18-01), Tested (18-03) |
| FOUND-03 | Phase 18 | Complete (18-01), Tested (18-03) |
| FOUND-04 | Phase 18 | Complete (18-01), Tested (18-03) |
| FOUND-05 | Phase 18 | Complete (18-01), Tested (18-03) |
| FOUND-06 | Phase 18 | Complete (18-01), Tested (18-03) |
| SRCH-01 | Phase 18 | Complete (18-02), Tested (18-03) |
| SRCH-02 | Phase 18 | Complete (18-02), Tested (18-03) |
| SRCH-03 | Phase 18 | Complete (18-02), Tested (18-03) |
| SRCH-04 | Phase 18 | Complete (18-02), Tested (18-03) |
| SRCH-05 | Phase 18 | Complete (18-02), Tested (18-03) |
| SRCH-06 | Phase 18 | Complete (18-02), Tested (18-03) |
| EVNT-02 | Phase 18 | Complete (18-01), Tested (18-03) |
| EVNT-03 | Phase 18 | Complete (18-01), Tested (18-03) |
| B2B-01 | Phase 19 | Complete |
| B2B-02 | Phase 19 | Complete |
| B2B-03 | Phase 19 | Complete |
| B2B-04 | Phase 19 | Complete |
| B2B-05 | Phase 19 | Complete |
| B2B-06 | Phase 19 | Complete |
| B2B-07 | Phase 19 | Complete |
| B2C-01 | Phase 19 | Complete |
| B2C-02 | Phase 19 | Complete |
| B2C-03 | Phase 19 | Complete |
| B2C-04 | Phase 19 | Complete |
| B2C-05 | Phase 19 | Complete |
| CANC-01 | Phase 20 | Complete |
| CANC-02 | Phase 20 | Complete |
| CANC-03 | Phase 20 | Complete |
| CANC-04 | Phase 20 | Complete |
| ACCT-01 | Phase 20 | Complete |
| ACCT-02 | Phase 20 | Complete (20-02) |
| ACCT-03 | Phase 20 | Complete |
| ACCT-04 | Phase 20 | Complete |
| ACCT-05 | Phase 20 | Complete (20-02) |
| LIFE-01 | Phase 21 | Complete |
| LIFE-02 | Phase 21 | Complete |
| LIFE-03 | Phase 21 | Complete |
| LIFE-04 | Phase 21 | Complete |
| LIFE-05 | Phase 21 | Complete |
| HIST-01 | Phase 21 | Complete |
| HIST-02 | Phase 21 | Complete |
| HIST-03 | Phase 21 | Complete |
| HIST-04 | Phase 21 | Complete |
| EVNT-01 | Phase 21 | Complete |
| DASH-01 | Phase 22 | Complete (22-01) |
| DASH-02 | Phase 22 | Complete (22-01) |
| DASH-03 | Phase 22 | Complete |
| DASH-04 | Phase 22 | Pending |
| DASH-05 | Phase 22 | Pending |
