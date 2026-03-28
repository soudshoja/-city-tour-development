---
phase: 24-dotw-certification-fixes-v2-olga-march-27-feedback
verified: 2026-03-28T12:00:00Z
status: passed
score: 9/9 must-haves verified
re_verification: false
---

# Phase 24: DOTW Certification Fixes v2 — Olga March 27 Feedback Verification Report

**Phase Goal:** Resolve all issues from Olga Chicu's March 27 review to pass DOTW certification. Fix salutation ID mapping, rateBasis=0 leak, remove APR flow, wire mandatory display features into WhatsApp messages, run 2-room cancellation test, add special request codes, collect nationality/residence from user, write B2B/B2C connection document, prepare certification evidence.
**Verified:** 2026-03-28T12:00:00Z
**Status:** PASSED
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Salutation IDs sent to DOTW use value codes (147=Mr, 149=Mrs, etc.) not runno codes | VERIFIED | DotwService.php line 1015: `'mr' => 147`; DotwCertify.php line 203: `'mr' => 147`; both in real code arrays, not only comments |
| 2 | Special request codes match DOTW API codes (1711=non-smoking, 1719=baby cot, all 23 codes) | VERIFIED | `config/dotwai.php` line 139: `special_request_codes` array with all 23 codes confirmed via grep count |
| 3 | rateBasis never sends 0 to DOTW — always -1 or a valid rate basis ID | VERIFIED | DotwService.php line 1375: `if ($rateBasis === 0)` guard; BookingService.php line 719: `!empty($booking->rate_basis_id) ? $booking->rate_basis_id : '-1'`; line 540-542: rateBasisId=0 set to -1 |
| 4 | BookingService resolves salutation labels to DOTW value codes before passing to DotwService | VERIFIED | BookingService.php lines 240-242 and 366-367: `$salutationMap = $dotwService->getSalutationIds()` called before building passengers; line 688: `$salutationMap[strtolower(...)] ?? 147` |
| 5 | APR flow (savebooking + bookitinerary) completely removed — all bookings use confirmBooking | VERIFIED | `grep savebooking\|bookitinerary\|invoiceAPRBooking BookingService.php` returns 0 matches; test 16 in DotwCertify.php line 2679 calls `$this->skipTest(16, 'DOTW removed APRs...')` |
| 6 | Nationality and residence collected from user input, not hardcoded to 66 | VERIFIED | HotelSearchService.php lines 77 and 214: `resolveResidenceCode($input['residence'] ?? null)`; new `resolveResidenceCode()` method at line 668; BookingService.php lines 587-588: uses `$booking->nationality_code` and `$booking->residence_code` |
| 7 | All mandatory DOTW features shown in WhatsApp messages before booking AND in confirmation/voucher | VERIFIED | MessageBuilderService.php: `formatMandatoryFeatures()` static method at line 233 covers all 8 features (cancel policy, tariff notes, min stay, MSP, specials, taxes/fees, restricted warnings); called from `formatPrebookConfirmation()` line 441, `formatBookingConfirmation()` line 512; voucher method has inline equivalent logic at lines 824-860 |
| 8 | DotwCertify has a 2-room booking + cancellation test with evidence capture | VERIFIED | `runTest21()` at line 3706; test name: "2-Room Cancellation — book 2 rooms then cancel (CERT-06 evidence for Olga)"; dispatched via `range(1, 21)` at lines 166 and 4367; step 21g checks `productsLeftOnItinerary` at lines 3867-3878 |
| 9 | B2B/B2C connection document and certification evidence guide ready for Olga | VERIFIED | `docs/DOTW-B2B-B2C-Connection-Guide.md` (313 lines); `docs/DOTW-Certification-Evidence.md` (264 lines); both contain required sections confirmed |

**Score:** 9/9 truths verified

---

## Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `app/Services/DotwService.php` | Fixed salutation fallback map with correct DOTW value codes | VERIFIED | Lines 1014-1027: fallback array with `'mr' => 147` and 11 other value codes; line 1612: `?? 147` default in buildPassengersXml |
| `app/Console/Commands/DotwCertify.php` | Fixed salutation fallback map + test 16 N/A + test 21 2-room cancel | VERIFIED | Lines 202-215: fallback map with `'mr' => 147`; line 2679: skipTest(16); lines 3706-3879: runTest21(); range(1,21) in dispatch |
| `app/Modules/DotwAI/Services/BookingService.php` | Salutation resolution via getSalutationIds(), APR removal, rateBasis guards | VERIFIED | Lines 240-242, 366-367: getSalutationIds() calls; no savebooking/invoiceAPRBooking; lines 540-542, 719: rateBasis guards |
| `app/Modules/DotwAI/Config/dotwai.php` | Special request code map with all 23 DOTW codes | VERIFIED | Line 139: `special_request_codes` array; grep confirms all 23 codes including 1711, 1719, 92255 etc. |
| `app/Modules/DotwAI/Services/MessageBuilderService.php` | All mandatory DOTW features formatted in WhatsApp messages | VERIFIED | `formatMandatoryFeatures()` method at line 233 with 8 sections; wired into formatPrebookConfirmation, formatBookingConfirmation, formatVoucherMessage (inline); special requests shown in confirmation and voucher |
| `app/Modules/DotwAI/Services/HotelSearchService.php` | resolveResidenceCode method + MSP propagation | VERIFIED | `resolveResidenceCode()` at line 668; MSP reads `totalMinimumSelling` at line 544; outputs `minimum_selling_price` at line 592 |
| `app/Services/DotwService.php` (parseRooms) | totalMinimumSelling propagated to detail entries | VERIFIED | Lines 1810 and 1881: `'totalMinimumSelling'` added to rateBasis entries in parseGetRoomsResponse |
| `app/Modules/DotwAI/Services/LifecycleService.php` | is_apr filter removed from reminder queries | VERIFIED | No `where('is_apr', false)` found; lines 22, 41 in comments confirm removal with explanation |
| `app/Modules/DotwAI/Services/MessageBuilderService.php` | "Non-Refundable (APR)" label removed | VERIFIED | grep returns 0 matches for "Non-Refundable (APR)" in MessageBuilderService |
| `app/Modules/DotwAI/Views/voucher-pdf.blade.php` | APR condition removed from cancellation policy block | VERIFIED | Summary confirms change; grep confirms no "Non-Refundable (APR)" in this file |
| `app/Modules/DotwAI/Config/dotwai-system-message.md` | APR Rates section replaced with note about DOTW removal | VERIFIED | Lines 199-201 contain updated note referencing DOTW APR removal |
| `docs/DOTW-B2B-B2C-Connection-Guide.md` | Professional B2B/B2C connection document | VERIFIED | 313 lines; confirmed contains Multi-Tenant Architecture, B2B Flow, B2C Flow, WhatsApp interface, How Other Agencies Connect sections |
| `docs/DOTW-Certification-Evidence.md` | Certification evidence capture guide | VERIFIED | 264 lines; confirmed contains Option A (direct WhatsApp testing), Option B (screenshots+logs), Evidence Checklist |

---

## Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `BookingService.php` | `DotwService.php` | `getSalutationIds()` call | WIRED | Lines 240-242 and 366-367 instantiate DotwService and call `getSalutationIds()` before passenger building |
| `BookingService.php` | `config/dotwai.php` | `config('dotwai.special_request_codes')` | WIRED | Lines 711-725: validates against config codes, passes `specialRequests` to each room in params |
| `HotelSearchService.php` | `DotwService.php` | `totalMinimumSelling` in parsed detail | WIRED | DotwService returns `totalMinimumSelling` in room details; HotelSearchService reads it at line 544 |
| `MessageBuilderService.php` | `config/dotwai.php` | `config('dotwai.special_request_codes')` | WIRED | Lines 525-529 and 855-858: resolves special request codes to labels using config |
| `DotwCertify.php` | `DotwService.php` | `runTest21()` 2-room search/block/confirm/cancel | WIRED | runTest21() at line 3706 uses the existing `tryBookHotels` helper which calls DotwService internally; steps 21e-21g build and send XML |

---

## Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
|----------|---------------|--------|--------------------|--------|
| `MessageBuilderService::formatMandatoryFeatures()` | `$room['minimum_selling_price']` | `DotwService::parseGetRoomsResponse()` → `HotelSearchService::parseRoomDetails()` | Yes — reads `$rateBasis->totalMinimumSelling` from DOTW API XML response | FLOWING |
| `MessageBuilderService::formatPrebookConfirmation()` | `$prebook['cancellation_rules']` | DotwService getRooms response → HotelSearchService → BookingService prebook | Yes — real DOTW cancellation rules array | FLOWING |
| `MessageBuilderService::formatBookingConfirmation()` | `$confirmation['special_requests']` | `$booking->special_requests` DB column → BookingService → confirmation array | Yes — codes stored per booking; config map resolves to labels | FLOWING |
| `DotwCertify::runTest21()` | 2-room XML | Hardcoded test XML with runtime hotel selection from `tryBookHotels` | Yes — real DOTW sandbox API call with live hotel inventory | FLOWING |

---

## Behavioral Spot-Checks

Step 7b: SKIPPED (cannot invoke DOTW sandbox API or start server in this environment; all checks require live DOTW XML API responses)

Covered by manual certification test runner (`php artisan dotw:certify --test=21`) which is the explicit evidence mechanism for Olga.

---

## Requirements Coverage

CERT requirements are defined in `24-CONTEXT.md` and plan frontmatter. They are NOT in `.planning/REQUIREMENTS.md` (which covers the DotwAI module v2.0 lifecycle requirements). CERT-01 through CERT-09 are certification-phase requirements specific to Olga's March 27 feedback.

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|-------------|-------------|--------|----------|
| CERT-01 | 24-01-PLAN.md | Salutation value codes (147=Mr not runno=1) | SATISFIED | DotwService fallback `'mr'=>147`; DotwCertify fallback `'mr'=>147`; BookingService calls getSalutationIds() |
| CERT-02 | 24-01-PLAN.md | Special request codes (all 23 from Olga screenshot) | SATISFIED | dotwai.php `special_request_codes` array with all 23 codes; BookingService wires through |
| CERT-03 | 24-01-PLAN.md | rateBasis=0 blocked in all code paths | SATISFIED | DotwService `if ($rateBasis === 0) { $rateBasis = -1; }`; BookingService guards in two locations |
| CERT-04 | 24-02-PLAN.md | Nationality/residence from user input, not hardcoded 66 | SATISFIED | `resolveResidenceCode()` method added; searchHotels and getHotelDetails use it; BookingService uses booking stored codes |
| CERT-05 | 24-02-PLAN.md | APR flow removed — all bookings use confirmBooking | SATISFIED | No savebooking/bookitinerary/invoiceAPRBooking in BookingService; test 16 marked N/A |
| CERT-06 | 24-03-PLAN.md | 2-room cancellation test with evidence | SATISFIED | `runTest21()` in DotwCertify; steps 21a-21g; productsLeftOnItinerary check at step 21g |
| CERT-07 | 24-03-PLAN.md | All 8 mandatory display features in WhatsApp messages | SATISFIED | `formatMandatoryFeatures()` covers all 8; wired into prebook, confirmation, and voucher methods |
| CERT-08 | 24-04-PLAN.md | B2B/B2C connection document for Olga | SATISFIED | `docs/DOTW-B2B-B2C-Connection-Guide.md` (313 lines) with all required sections |
| CERT-09 | 24-04-PLAN.md | Test access / screenshots evidence guide | SATISFIED | `docs/DOTW-Certification-Evidence.md` (264 lines) with Option A/B and full checklist |

**Orphaned requirements check:** No CERT-01 through CERT-09 entries exist in `.planning/REQUIREMENTS.md` tracking table — these requirements were created specifically for Phase 24 and are documented only in 24-CONTEXT.md. This is expected given the certification-fix nature of the phase.

---

## Anti-Patterns Found

No blocking anti-patterns found. Spot checks on key files:

| File | Pattern Checked | Result |
|------|----------------|--------|
| `BookingService.php` | `savebooking\|bookitinerary\|invoiceAPRBooking` | 0 matches — clean |
| `MessageBuilderService.php` | `Non-Refundable (APR)` | 0 matches — clean |
| `DotwService.php` | `'mr' => 1,` in fallback map | 0 matches — old wrong code gone |
| `DotwCertify.php` | `'mr' => 1,` in fallback map | 0 matches — old wrong code gone |
| `LifecycleService.php` | `where('is_apr', false)` | 0 matches — filter removed |
| `dotwai-system-message.md` | `APR Rates (Non-Refundable)` section | 0 matches — replaced with note |

One notable design choice: `is_apr` column retained in database schema and `DotwAIBooking` fillable for backward compatibility. The column will always be `false` going forward. This is not a stub — it is an intentional migration-safety decision documented in the summary.

---

## Human Verification Required

### 1. Live DOTW Sandbox Certification Run

**Test:** Run `php artisan dotw:certify` against live DOTW sandbox environment
**Expected:** All 21 tests pass (test 16 shows SKIP with APR explanation, test 21 shows full 2-room cancel with productsLeftOnItinerary=0 in logs)
**Why human:** Requires DOTW sandbox credentials configured in .env and live hotel inventory in the target environment

### 2. WhatsApp Mandatory Features Display

**Test:** Trigger a hotel search and pre-booking via WhatsApp conversation; review the pre-booking confirmation message
**Expected:** Message shows all 8 sections: cancellation policy, tariff notes, minimum stay, MSP, special promotions, taxes & fees, restricted cancellation warning (if applicable), and special requests option
**Why human:** Requires live WhatsApp Business API connection and n8n workflow running

### 3. Nationality/Residence Fuzzy Resolution in Practice

**Test:** Send a hotel search request with nationality="Kuwaiti" and residence="Kuwait" through the WhatsApp AI agent
**Expected:** System resolves both to country code 414 (Kuwait) via fuzzy matching, not the hardcoded 66 default
**Why human:** Requires live n8n + WhatsApp + fuzzy country matcher with live country data

### 4. Special Requests Selection Flow

**Test:** During a hotel booking via WhatsApp, provide a special request (e.g., "non-smoking room"); verify booking XML contains `<req runno="0">1711</req>` not the old invalid code `1`
**Expected:** DOTW confirmation XML in logs shows correct code 1711
**Why human:** Requires live booking flow with valid DOTW sandbox credentials

---

## Gaps Summary

No gaps found. All 9 CERT requirements have complete implementations verified in the actual codebase:

- CERT-01 (salutation): All three code locations (DotwService, DotwCertify, BookingService) use correct DOTW value codes
- CERT-02 (special requests): 23 codes in config; BookingService validates and passes them through to DotwService XML
- CERT-03 (rateBasis): Guards exist in DotwService and two locations in BookingService
- CERT-04 (nationality/residence): resolveResidenceCode() method added to HotelSearchService; BookingService reads from stored booking data
- CERT-05 (APR removal): Clean removal confirmed; no remnant calls to deprecated endpoints; test 16 properly skipped
- CERT-06 (2-room cancel): Complete runTest21() with 7 steps including productsLeftOnItinerary check
- CERT-07 (mandatory display): formatMandatoryFeatures() covers all 8 required features; wired into all 3 message types
- CERT-08 (B2B/B2C doc): Professional 313-line document ready for Olga
- CERT-09 (evidence guide): 264-line guide with two testing options and full CERT-01–09 checklist

The 4 human verification items above are operational concerns requiring live environment access, not code deficiencies.

---

_Verified: 2026-03-28T12:00:00Z_
_Verifier: Claude (gsd-verifier)_
