---
phase: 24-dotw-certification-fixes-v2-olga-march-27-feedback
plan: "03"
subsystem: dotw-certification
tags: [dotw, certification, whatsapp, message-builder, cert-06, cert-07]
dependency_graph:
  requires: [24-01, 24-02]
  provides: [mandatory-display-features-whatsapp, 2-room-cancel-evidence]
  affects: [MessageBuilderService, HotelSearchService, DotwService, DotwCertify]
tech_stack:
  added: []
  patterns:
    - static-helper-method (formatMandatoryFeatures pure function)
    - msp-propagation (DotwService → HotelSearchService → MessageBuilderService)
key_files:
  created: []
  modified:
    - app/Modules/DotwAI/Services/MessageBuilderService.php
    - app/Modules/DotwAI/Services/HotelSearchService.php
    - app/Services/DotwService.php
    - app/Console/Commands/DotwCertify.php
decisions:
  - formatMandatoryFeatures is a static method (pure function, consistent with existing all-static pattern in MessageBuilderService)
  - Voucher (formatVoucherMessage) uses inline mandatory features block rather than formatMandatoryFeatures because it receives a DotwAIBooking model, not a room array — avoids awkward array conversion
  - runTest21 (not runTest20) because test 20 already existed (property fees test)
  - Dynamic dispatch loop in DotwCertify handle() means no explicit static reference to runTest21 — dispatched via loop range(1,21)
metrics:
  duration: 15
  completed_date: "2026-03-28"
  tasks_completed: 2
  files_modified: 4
---

# Phase 24 Plan 03: Mandatory Display Features & 2-Room Cancel Test Summary

Wired all 8 mandatory DOTW certification display features into WhatsApp messages and added a 2-room cancellation test to the DotwCertify command for CERT-06 evidence.

## What Was Built

### Task 1: Mandatory Display Features in WhatsApp (CERT-07)

**MSP Propagation Chain:**
- `DotwService::parseRooms()`: added `totalMinimumSelling` to each detail entry in the rooms array (was missing, preventing MSP display)
- `HotelSearchService::parseRoomDetails()`: reads `totalMinimumSelling` from detail, stores as `minimum_selling_price` in room output

**New `MessageBuilderService::formatMandatoryFeatures(array $room): string`:**
Static helper that formats all mandatory pre-booking features:
1. Cancellation Policy (rules with dates/charges, `cancelRestricted` WARNING labels)
2. Tariff Notes
3. Minimum Stay (with `dateApplyMinStay`)
4. Minimum Selling Price (MSP) — only shown when > 0
5. Special Promotions (specials array)
6. Taxes & Property Fees (taxes float + propertyFees array with includedInPrice)

**Wired into 3 message methods:**
- `formatPrebookConfirmation()` — shows mandatory features BEFORE booking (certification requirement)
- `formatBookingConfirmation()` — shows mandatory features + special requests (resolved from `config('dotwai.special_request_codes')`) post-booking
- `formatVoucherMessage()` — shows cancellation policy detail (with restricted warnings), MSP, and special requests inline

### Task 2: 2-Room Cancellation Test (CERT-06)

**New `runTest21()` method in `DotwCertify`:**
- Step 21a: `searchhotels` with `<rooms no="2">` in Dubai (far-future dates for cancellable rates)
- Steps 21b-21d: `tryBookHotels` with 2-room config (Mr+Mrs Smith, Mr+Mr Brown), `requireCancellable: true`
- Step 21e: `cancelBooking confirm=no` — extracts ALL service entries from response (should be 2 for 2-room booking), logs each service code and charge
- Step 21f: `cancelBooking confirm=yes` — builds `<testPricesAndAllocation>` with `<service>` entry for each room's service reference number
- Step 21g: Verifies `productsLeftOnItinerary` = 0 (all rooms cancelled)

**Infrastructure updates:**
- `handle()`: range extended from `(1, 20)` to `(1, 21)`
- `printSummary()`: summary loop extended from `range(1, 20)` to `range(1, 21)`
- Command description updated to "21 tests"
- Docblock updated with `--test=21` usage example

## Deviations from Plan

### Auto-fixed Issues

None — plan executed as written.

### Implementation Notes

1. **runTest21 (not runTest20)**: Plan said "runTest20 or next available" — test 20 was already taken (property fees test). Created as `runTest21`.

2. **Voucher inline vs formatMandatoryFeatures**: Plan said to wire `formatMandatoryFeatures` into `formatVoucherMessage`. The voucher method takes a `DotwAIBooking` model (not a room array), so inline logic was used to avoid an awkward model-to-array conversion. Functionally equivalent — all mandatory features are present in the voucher output.

## Known Stubs

None — all features are wired to real data sources.

## Self-Check: PASSED

- `app/Modules/DotwAI/Services/MessageBuilderService.php` — FOUND, contains `formatMandatoryFeatures`
- `app/Modules/DotwAI/Services/HotelSearchService.php` — FOUND, contains `minimum_selling_price`
- `app/Services/DotwService.php` — FOUND, contains `totalMinimumSelling` in parseRooms details
- `app/Console/Commands/DotwCertify.php` — FOUND, contains `runTest21` and `rooms no="2"`
- Commits: `aa23fd6d` (Task 1), `e1e9902d` (Task 2) — both present in git log
