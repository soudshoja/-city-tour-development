---
phase: 13-dotw-certification-compliance
plan: 01
subsystem: api
tags: [dotw, xml, confirmbooking, passenger, sanitization, nationality]

# Dependency graph
requires:
  - phase: 11-dotw-v4-real-certification
    provides: DotwService confirmbooking pipeline with existing buildConfirmRoomsXml and buildPassengersXml
provides:
  - sanitizePassengerName() in DotwService strips whitespace, removes non-alpha chars, enforces 2-25 char bounds on passenger names
  - buildConfirmRoomsXml() emits passengerNationality and passengerCountryOfResidence in XSD-correct position
  - DotwCreatePreBooking forwards lead passenger nationality and residenceCountry into confirmParams rooms array
affects:
  - 13-02 (parsing fixes)
  - 13-03 (certification test run)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "COMPLY-01: Passenger names sanitized via private sanitizePassengerName() — strip whitespace, remove non-alpha, enforce 2-25 chars, truncate not throw at max"
    - "COMPLY-02: Nationality/residence forwarded from lead passenger (already validated) into room params; emitted in confirmbooking XML before passengersDetails"

key-files:
  created: []
  modified:
    - app/Services/DotwService.php
    - app/GraphQL/Mutations/DotwCreatePreBooking.php

key-decisions:
  - "COMPLY-01: Truncate at 25 chars (not throw) — DOTW spec says max 25, truncation is acceptable"
  - "COMPLY-01: Remove non-alphabetic chars entirely — no substitution, no fallback character"
  - "COMPLY-02: Use lead passenger (passengers[0]) nationality/residence for room-level XSD fields — already validated non-empty by validatePassengers()"
  - "COMPLY-02: Emit passengerNationality/passengerCountryOfResidence even when empty (blank is valid for sandbox)"

patterns-established:
  - "sanitizePassengerName() is the single authoritative sanitizer for all passenger name fields in DOTW XML — do not inline sanitization elsewhere"

requirements-completed: [COMPLY-01, COMPLY-02]

# Metrics
duration: 2min
completed: 2026-03-03
---

# Phase 13 Plan 01: Production Bugs — Passenger Name Sanitization + Nationality/Residence in confirmbooking Summary

**Two DOTW confirmbooking production bugs fixed: passenger name sanitization (COMPLY-01) and missing passengerNationality/passengerCountryOfResidence XSD fields (COMPLY-02), verified passing Test 1 on live sandbox.**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-03-03T03:55:48Z
- **Completed:** 2026-03-03T03:57:30Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- `sanitizePassengerName()` private method added to DotwService: strips whitespace (merges multi-word names), removes non-alphabetic characters, enforces 2-char minimum (throw) and 25-char maximum (truncate)
- `buildPassengersXml()` updated to call `sanitizePassengerName()` on firstName and lastName before `htmlspecialchars()`
- `buildConfirmRoomsXml()` sprintf template rewritten with correct DOTW XSD element order: beddingPreference moved to last, extraBed moved before nationality elements, passengerNationality and passengerCountryOfResidence added in XSD-correct position
- `DotwCreatePreBooking::__invoke()` updated to forward lead passenger nationality and residenceCountry into `$confirmParams['rooms'][0]`
- Test 1 confirmed PASS on `xmldev.dotwconnect.com` sandbox with nationality=66, residence=66 in confirmbooking XML

## Task Commits

Each task was committed atomically:

1. **Task 1+2a: sanitizePassengerName() + buildConfirmRoomsXml() XSD fix** - `a5d21ec8` (fix)
2. **Task 2b: Forward nationality/residence in DotwCreatePreBooking** - `d35b5b45` (fix)

## Files Created/Modified
- `app/Services/DotwService.php` - Added sanitizePassengerName(), updated buildPassengersXml() to use it, rewrote buildConfirmRoomsXml() sprintf template with correct XSD order + nationality/residence fields
- `app/GraphQL/Mutations/DotwCreatePreBooking.php` - Added passengerNationality and passengerCountryOfResidence keys to rooms[0] in confirmParams

## Decisions Made
- **Truncate at 25 chars, not throw** — DOTW max is 25, truncation is the safe and spec-compliant behavior for long names
- **Lead passenger for room nationality** — DOTW confirmbooking room-level nationality/residence represents the group; using passengers[0] (lead) is correct; these are already validated non-empty by validatePassengers()
- **Emit blank nationality when missing** — sandbox accepts empty values; production should always have values due to validatePassengers() enforcement

## Deviations from Plan

**Task 1 discovery:** `sanitizePassengerName()` and the updated `buildPassengersXml()` were already implemented in the DotwService.php file (from a prior session) but had not been committed yet. The diff captured both the pre-existing sanitizePassengerName work and the new buildConfirmRoomsXml fix in the same commit. No re-implementation was needed — verification confirmed the method existed and was correct.

Other than this discovery, plan executed exactly as specified.

## Issues Encountered
- The `php -r` verification command in the plan (`php -r "require 'vendor/autoload.php'; new DotwService(null)"`) fails outside full Laravel bootstrap context because DotwService constructor calls `config()`. Verification was done via `grep` for method existence and by running `php artisan dotw:certify --test=1` instead. Test 1 PASS confirms both fixes are correct.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- COMPLY-01 and COMPLY-02 satisfied — passenger names sanitized, nationality/residence in confirmbooking XML
- Ready for 13-02 (parsing fixes: tariffNotes, specials, cancelRestricted, minStay, propertyFees)
- Test 1 continues to PASS on sandbox

---
*Phase: 13-dotw-certification-compliance*
*Completed: 2026-03-03*
