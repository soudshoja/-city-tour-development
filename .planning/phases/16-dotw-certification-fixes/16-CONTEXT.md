# Phase 16: DOTW Certification Fixes - Context

**Gathered:** 2026-03-17
**Status:** Ready for planning
**Source:** DOTW certification team feedback (docs/log.docx)

<domain>
## Phase Boundary

Fix all 6 issues identified by DOTW certification team from reviewing our certification logs. These are blockers — certification cannot proceed until all are resolved. All previously-SKIP tests must become PASS.

</domain>

<decisions>
## Implementation Decisions

### Issue 1: Salutation ID Mapping
- Currently hardcoding `<salutation>1</salutation>` in confirmbooking/savebooking
- Must use `getsalutationsids` API method to dynamically map salutation codes
- Affects: DotwCertify.php and any resolver that builds passenger XML

### Issue 2: Remove roomField from getRooms blocking
- Currently sending `<fields><roomField>cancellation</roomField></fields>` in getRooms blocking step
- DOTW says remove this node from blocking requests
- roomField is fine for browse/info requests, just not the blocking step

### Issue 3: rateBasis must be -1 (best available)
- Some requests hardcode specific rateBasis values instead of -1
- Must default to -1 unless intentionally requesting a specific basis
- Check all searchHotels and getRooms calls in DotwCertify.php

### Issue 4: Remove pagination elements
- `<resultsPerPage>5</resultsPerPage>` and `<page>1</page>` are not active DOTW elements
- Must remove from all requests
- Affects: DotwService.php and DotwCertify.php

### Issue 5: changedOccupancy fix (CRITICAL)
- Current bug: when validForOccupancy converts child to adult, we're not correctly separating adultsCode vs actualAdults
- CORRECT behavior per DOTW:
  - `<adultsCode>4</adultsCode>` — from validForOccupancy (what DOTW needs for pricing)
  - `<actualAdults>3</actualAdults>` — original search (real occupancy)
  - `<children no="0"></children>` — empty (validForOccupancy says 0 children)
  - `<actualChildren no="1"><actualChild runno="0">12</actualChild></actualChildren>` — original child
- Affects: DotwCertify.php test 14 and DotwService.php confirmBooking/saveBooking XML building

### Issue 6: Fix SKIP tests using DOTW hotel hints (ALL MANDATORY)
- ALL 19 test cases are MANDATORY — no SKIPs accepted, DOTW will reject submission with any SKIP
- DOTW provided specific hotels to guarantee data availability:
  - **Special Promotions (test 15):** Hotel ID 2344175 (The S Hotel Al Barsha), Dubai, Check-in 14.05.2026, Check-out 15.05.2026, 2 adults + 2 children (ages 8 and 12)
  - **MSP (test 11):** Hotel ID 809755 (Conrad London St James)
- Previously SKIP tests: 6, 15, 16, 17, 18, 20
- Update DotwCertify.php to use these EXACT hotel IDs and dates for targeted tests
- For other SKIP tests (6, 16, 17, 18, 20): need to find working hotels or use DOTW-suggested params — these tests MUST pass

### Issue 7: Certification Log Package (DOTW-FIX-07)
- After all code fixes, run the full 19-test cert suite
- Capture complete RQ/RS XML for every test case
- Package into docs/dotw-certification-submission/ directory
- Each test should have its own log file with request and response XML

### Issue 8: Connection Type Response Document (DOTW-FIX-08)
- DOTW wants to know: how clients connect, what is displayed to them
- Create docs/dotw-connection-type-response.md covering:
  - Platform type: B2B web portal for travel agencies
  - Tech stack: Laravel 11 + Livewire 3 + GraphQL (Lighthouse)
  - Client access: authenticated agents via web browser
  - DOTW integration: GraphQL resolvers call DotwService which makes XML API calls
  - Mandatory display features implemented: tariffNotes, cancellation policies (refundable/non-refundable/cancelRestricted), special promotions, taxes & fees, minimum selling price
  - Validation approach: Option A — providing full test logs + screenshots of mandatory display features
  - Booking flow: searchHotels → getRooms (browse) → getRooms (blocking) → confirmBooking (or saveBooking → bookItinerary for APR)

### Claude's Discretion
- How to cache/store salutation ID mappings (one-time fetch vs per-request)
- Whether to add getsalutationsids as a new DotwService method
- How to restructure test methods to use specific hotel IDs
- Format and structure of the connection type response document

</decisions>

<canonical_refs>
## Canonical References

### DOTW Core Files
- `app/Console/Commands/DotwCertify.php` — 4,157-line certification test suite (main file to modify)
- `app/Services/DotwService.php` — Core XML API client (salutation mapping, roomField, pagination)
- `docs/log.docx` — DOTW certification team feedback (source of all 6 issues)
- `docs/Best Practice Notes__.docx` — DOTW best practices requirements

### Previous Phase Work
- `.planning/phases/13-dotw-certification-compliance/` — Last certification phase (15 PASS / 5 SKIP)
- `.planning/phases/11-dotw-v4-real-certification-fix-dotwcertify-warn-pattern-fake-passes-add-skip-state-run-real-tests-against-live-hotel-inventory/` — Real sandbox run

</canonical_refs>

<specifics>
## Specific Ideas

- DOTW-provided hotel IDs for specific tests are time-sensitive (May 2026 dates for promotions)
- getsalutationsids should be cached since salutation codes don't change frequently
- The changedOccupancy fix is the most complex — need to trace through the full XML building pipeline

</specifics>

<deferred>
## Deferred Ideas

- searchHotels by hotelID (batching 50 per request) — recommended but not mandatory per DOTW
- Static data download implementation
- paymentGuaranteedBy voucher display

</deferred>

---

*Phase: 16-dotw-certification-fixes*
*Context gathered: 2026-03-17 via DOTW certification feedback*
