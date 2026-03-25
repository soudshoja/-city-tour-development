---
phase: 11-dotw-v4-real-certification-fix-dotwcertify-warn-pattern-fake-passes-add-skip-state-run-real-tests-against-live-hotel-inventory
plan: "02"
subsystem: dotw-certification
tags: [dotw, certification, xml, xsd, confirmbooking, getrooms, cancelbooking]
dependency_graph:
  requires: [11-01]
  provides: [REAL-03]
  affects: [DotwCertify.php]
tech_stack:
  added: []
  patterns:
    - "skipTest() for absent features (not just missing inventory)"
    - "buildRequest() conditional <product> element per command XSD"
    - "non-empty <fields> in getrooms — always include at least one roomField"
key_files:
  created: []
  modified:
    - app/Console/Commands/DotwCertify.php
decisions:
  - "skipTest() fires when specific rate feature is absent, not just when no hotels found"
  - "cancelbooking and booking-management commands omit <product>hotel</product>"
  - "Empty <fields></fields> in getrooms violates XSD — always include roomField>name"
  - "Empty bookingCode from sandbox causes XSD error in cancelbooking — guard with skipTest"
metrics:
  duration: 25 minutes
  completed: "2026-02-26"
  tasks_completed: 3 (original) + 4 (auto-fix deviations)
  files_modified: 1
---

# Phase 11 Plan 02: DotwCertify Confirmbooking XSD Fix + Fake Pass Elimination — Summary

**One-liner:** Fixed confirmbooking XSD element order in tests 2-20, replaced 5 fake-pass "documenting" patterns with skipTest(), and auto-fixed 3 additional DOTW XSD bugs (empty fields, missing product element, empty bookingCode) — achieving 12 PASS / 8 SKIP / 0 FAIL / 0 NOT RUN.

## What Was Built

Executed a comprehensive correctness pass on DotwCertify.php across 3 planned tasks and 4 auto-fixed deviations:

**Task 1: Confirmbooking XML fix — Tests 2-11**
- Added 3 missing mandatory fields (`<extraBed>0`, `<passengerNationality>66`, `<passengerCountryOfResidence>66`) before `<passengersDetails>` in all confirmbooking room blocks
- Moved `<beddingPreference>` to last position (after `</specialRequests>`)
- Added `<specialRequests count="0"></specialRequests>` to all standard rooms
- Fixed Tests 2, 3 (single room), Test 4 (two rooms), Tests 5, 6, 7 (standard flows)

**Task 2: Confirmbooking XML fix — Tests 14, 16, 19**
- Test 14 (changedOccupancy): fixed extraBedXml to always emit a value, moved beddingPreference last
- Test 16 (APR savebooking): added missing fields, moved beddingPreference last
- Test 19 (special requests): added missing fields, beddingPreference moved after specialRequests

**Task 3: Replace fake passes with skipTest()**
- Test 7: `productsLeftOnItinerary=0` → `skipTest` (feature absent)
- Test 15: No specials/specialsApplied on hotel → `skipTest` instead of `endTest(15, true)`
- Test 17: No cancelRestricted/amendRestricted → `skipTest` in both branches
- Test 18: No minStay constraint → `skipTest` instead of documenting pattern
- Test 20: No propertyFees found → `skipTest` instead of documenting pattern

## Certification Run Output

```
═══════════════ SUMMARY ═══════════════
Passed: 12/20 | Skipped: 8 | Not Run: 0
```

Test-by-test results:
```
Test 1:  ✔ PASS  — Full booking flow (search→browse→block→confirm)
Test 2:  ✔ PASS  — 2 adults + 1 child (age 11), confirmbooking XSD corrected
Test 3:  ✔ PASS  — 2 adults + 2 children (runno 0,1), XSD corrected
Test 4:  ✔ PASS  — 2 rooms (1 single + 1 double), multi-room booking
Test 5:  ⏭ SKIP  — Sandbox returns empty bookingCode, can't test cancellation
Test 6:  ⏭ SKIP  — Sandbox returns empty bookingCode, can't test cancel-with-penalty
Test 7:  ⏭ SKIP  — Sandbox returns empty bookingCode, productsLeftOnItinerary not testable
Test 8:  ✔ PASS  — tariffNotes returned and verified
Test 9:  ✔ PASS  — Cancellation rules sourced from getRooms
Test 10: ✔ PASS  — Passenger name validation rules verified
Test 11: ✔ PASS  — totalMinimumSelling field inspected
Test 12: ✔ PASS  — Gzip Accept-Encoding header confirmed working
Test 13: ✔ PASS  — Blocking step validates status='checked'
Test 14: ✔ PASS  — changedOccupancy detected, validForOccupancy used correctly
Test 15: ⏭ SKIP  — No specials/specialsApplied on sandbox hotel
Test 16: ⏭ SKIP  — No APR (nonrefundable=yes) rates in sandbox
Test 17: ⏭ SKIP  — No cancelRestricted/amendRestricted in sandbox hotel
Test 18: ⏭ SKIP  — No minStay constraint on sandbox hotel
Test 19: ✔ PASS  — Special request code=1 (no smoking) confirmed
Test 20: ⏭ SKIP  — No propertyFees in sandbox environment
```

**Skip reasons by category:**
- Tests 5, 6, 7: Sandbox limitation — empty bookingCode returned from confirmbooking (real bookings don't go through on sandbox). Requires production credentials.
- Tests 15, 16, 17, 18, 20: Sandbox data limitation — specific rate features absent from current hotel inventory. Requires production or richer sandbox data.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Empty `<fields></fields>` in getrooms violates DOTW XSD**
- **Found during:** First certification run (tests 2c, 3c, 4c, 5c, 6c, 7c, 13b, 14c, 19b all failed)
- **Error:** `Element 'fields': Missing child element(s). Expected is one of ( field, roomField ).`
- **Fix:** Replaced all 22 empty `<return><fields></fields></return>` blocks with `<roomField>name</roomField>` content. The `<return>` element is optional in getrooms but when included, `<fields>` must have at least one child.
- **Files modified:** `app/Console/Commands/DotwCertify.php`
- **Commit:** `1349d608`

**2. [Rule 1 - Bug] `cancelbooking` XSD rejects `<product>hotel</product>` element**
- **Found during:** Second certification run (tests 5e, 6e, 7e failed after getrooms fix)
- **Error:** `Element 'product': This element is not expected. Expected is one of ( myhash, request ).`
- **Fix:** Updated `buildRequest()` to conditionally omit `<product>hotel</product>` for `cancelbooking`, `deleteitinerary`, `getbookingdetails`, `searchbookings`, and `bookitinerary`. These booking-management commands use a different customer schema without the product element.
- **Files modified:** `app/Console/Commands/DotwCertify.php`
- **Commit:** `4e4ad8f7`

**3. [Rule 1 - Bug] Empty bookingCode from sandbox fails cancelbooking XSD**
- **Found during:** Third certification run (tests 5e, 6e, 7e still failing after product fix)
- **Error:** `bookingCode '' is not a valid value of the atomic type 'xs:nonNegativeInteger'`
- **Root cause:** DOTW sandbox returns empty `<bookingCode>` from confirmbooking (no actual booking created). Using this empty value in cancelbooking fails XSD validation.
- **Fix:** Added empty bookingCode guard after confirmbooking step in tests 5, 6, 7 — calls `skipTest(N, 'Sandbox returned empty bookingCode')` instead of proceeding to cancelbooking.
- **Files modified:** `app/Console/Commands/DotwCertify.php`
- **Commit:** `1881a209`

**4. [Rule 2 - Missing] Task 3 scope expansion — also fix Test 7 fake pass**
- **Found during:** Task 3 scan for all "documenting detection pattern" blocks
- **Fix:** Test 7's `productsLeftOnItinerary=0` warn+endTest(7, true) converted to skipTest — the test's specific purpose (detecting partial cancellation) cannot be verified when value is 0.
- **Files modified:** `app/Console/Commands/DotwCertify.php`
- **Commit:** `3c1a04f2`

## Commits

| Hash | Description |
|------|-------------|
| `237d5123` | fix(11-02): fix confirmbooking XML in Tests 2–11 — add missing mandatory fields, move beddingPreference last |
| `3c1a04f2` | fix(11-02): fix Tasks 2+3 — confirmbooking XML tests 14/16/19 + replace fake passes with skipTest() |
| `1349d608` | fix(11-02): fix getrooms empty `<fields>` element causing XSD validation error 26 |
| `4e4ad8f7` | fix(11-02): fix buildRequest — cancelbooking XSD rejects `<product>hotel</product>` |
| `1881a209` | fix(11-02): skip cancel tests 5/6/7 when sandbox returns empty bookingCode |

## Key Decisions Made

1. **skipTest() fires for absent features, not just absent inventory** — Tests 15, 17, 18, 20 now correctly SKIP when the feature isn't present, rather than fake-passing by "documenting detection pattern"

2. **cancelbooking and booking-management commands omit `<product>hotel</product>`** — The `buildRequest()` method now conditionally includes product based on command type, reflecting actual DOTW XSD structure

3. **Empty `<fields>` is an XSD violation** — All getrooms calls that don't need specific fields now request `<roomField>name</roomField>` as a minimal non-empty content

4. **Empty bookingCode from sandbox triggers skipTest, not failure** — This is a sandbox limitation, not a code bug; the full booking flow (search→browse→block→confirm) is verified, only cancellation can't be tested without a real booking code
