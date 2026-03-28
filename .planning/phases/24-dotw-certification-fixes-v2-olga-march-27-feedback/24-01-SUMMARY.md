---
phase: 24-dotw-certification-fixes-v2-olga-march-27-feedback
plan: "01"
subsystem: dotw-integration
tags: [dotw, certification, salutation, special-requests, rate-basis, bug-fix]
dependency_graph:
  requires: []
  provides: [correct-salutation-ids, special-request-codes-config, rate-basis-guard]
  affects: [DotwService, DotwCertify, BookingService, dotwai-config]
tech_stack:
  added: []
  patterns: [value-code-not-runno, guard-invalid-zero, config-driven-codes]
key_files:
  created: []
  modified:
    - app/Services/DotwService.php
    - app/Console/Commands/DotwCertify.php
    - app/Modules/DotwAI/Services/BookingService.php
    - app/Modules/DotwAI/Config/dotwai.php
decisions:
  - "Salutation fallback map uses DOTW value codes (147=Mr, 149=Mrs, etc.) not runno (1,2,3) per Olga screenshot"
  - "BookingService resolves string salutation labels via getSalutationIds() before passing to DotwService"
  - "rateBasisId in buildConfirmParams defaults to '-1' string (not empty string that casts to 0)"
  - "Special request codes stored in dotwai config (23 codes from Olga screenshot)"
  - "rateBasis=0 guard added in DotwService buildRoomsXml and BookingService buildRoomSelections"
metrics:
  duration: ~10m
  completed: "2026-03-28"
  tasks_completed: 2
  tasks_total: 2
  files_modified: 4
requirements: [CERT-01, CERT-02, CERT-03]
---

# Phase 24 Plan 01: Fix Salutation IDs, Special Request Codes, and rateBasis=0 Summary

**One-liner:** Fixed three blocking DOTW certification bugs: salutation value codes (runno → value), all 23 special request codes added to config with BookingService wiring, and rateBasis=0 guard in all code paths.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Fix salutation ID mapping and wire into BookingService | `99c4cc9f` | DotwService.php, DotwCertify.php, BookingService.php |
| 2 | Add special request codes and fix rateBasis=0 leak | `d2a6eb31` | dotwai.php, DotwService.php, BookingService.php |

## What Was Built

### CERT-01: Salutation Value Codes

**Problem:** DOTW `getsalutationsids` API returns `value` attribute as the real code, but our fallback maps used `runno` values. `runno=1` = Dr (value=558), `runno=7` = Mr (value=147). Sending `<salutation>1</salutation>` was booking with "Dr." not "Mr.".

**Fix in DotwService.php:**
- `getSalutationIds()` fallback map updated: `mr=>147, mrs=>149, miss=>15134, ms=>148, dr=>558, child=>14632, sir=>1328, madame=>1671, mademoiselle=>74195, messrs=>9234, monsieur=>74185, sir/madam=>3801`
- `buildPassengersXml()` default changed from `?? 1` to `?? 147` (Mr, not Dr)

**Fix in DotwCertify.php:**
- `fetchSalutationMap()` fallback map updated to same correct value codes

**Fix in BookingService.php:**
- `buildConfirmParams()` now accepts `$salutationMap` parameter
- String salutations ("Mr", "Mrs") resolved via `$salutationMap[strtolower()]` with `?? 147` default
- `confirmWithCredit()` and `confirmAfterPayment()` both call `$dotwService->getSalutationIds()` before building params
- Placeholder passenger uses `'salutation' => 147` (not string "Mr")

### CERT-02: Special Request Codes

**Problem:** We were sending `<req runno="0">1</req>` — code `1` does not exist in DOTW. Non-smoking room is code `1711`, baby cot is `1719`.

**Fix:**
- Added all 23 DOTW special request codes to `app/Modules/DotwAI/Config/dotwai.php` under key `special_request_codes`
- `BookingService::buildConfirmParams()` validates `$booking->special_requests` against valid codes and passes through to `specialRequests` in each room array
- `DotwService::buildConfirmBookingXml()` already handled `$room['specialRequests']` — just needed data wired through

### CERT-03: rateBasis=0 Guard

**Problem:** Empty `rateBasisId` fields (null, '') cast to 0 via `(int)`. DOTW rejects rateBasis=0 — must be -1 for "all rates" or a specific ID.

**Fix in DotwService.php:**
- `buildRoomsXml()` now explicitly checks: `if ($rateBasis === 0) { $rateBasis = -1; }`

**Fix in BookingService.php:**
- `buildRoomSelections()`: added `if ($rateBasisId !== null && (int) $rateBasisId === 0) { $rateBasisId = -1; }`
- `buildConfirmParams()`: rateBasisId uses `!empty($booking->rate_basis_id) ? $booking->rate_basis_id : '-1'` (string '-1' to prevent empty string → 0 cast)

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — all three code paths are fully wired with real data.

## Self-Check: PASSED

All files exist. Both commits (99c4cc9f, d2a6eb31) verified in git log.
