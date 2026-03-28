# Phase 24: DOTW Certification Fixes v2 — Olga March 27 Feedback - Context

**Gathered:** 2026-03-28
**Status:** Ready for planning
**Source:** Olga Chicu email (March 27, 2026) + screenshots analysis

<domain>
## Phase Boundary

Fix all 9 issues raised by Olga Chicu (DOTW Integration Consultant) in her March 27 review email. Pass DOTW XML API certification. This phase touches:
- DotwService.php (XML request building)
- DotwCertify.php (certification test runner)
- DotwAI BookingService (production booking flow)
- DotwAI HotelSearchService (search responses)
- DotwAI MessageBuilderService (WhatsApp messages)
- DotwAI CancellationService (multi-room cancel)
- config/dotw.php and config/dotwai.php

Does NOT touch: n8n workflows, dashboard (Phase 22), agent facade (Phase 23).

</domain>

<decisions>
## Implementation Decisions

### CERT-01: Salutation ID Mapping (CRITICAL)
- The DOTW `getsalutationsids` API returns 12 options. The `value` attribute is the REAL code, NOT `runno`.
- Actual mapping from Olga's screenshot:
  - runno=0, value=14632 → Child
  - runno=1, value=558 → Dr.
  - runno=2, value=1671 → Madame
  - runno=3, value=74195 → Mademoiselle
  - runno=4, value=9234 → Messrs.
  - runno=5, value=15134 → Miss
  - runno=6, value=74185 → Monsieur
  - runno=7, value=147 → Mr.
  - runno=8, value=149 → Mrs.
  - runno=9, value=148 → Ms.
  - runno=10, value=1328 → Sir
  - runno=11, value=3801 → Sir/Madam
- Our hardcoded fallback `['mr' => 1, 'mrs' => 2, 'miss' => 3, 'master' => 4, 'ms' => 5]` is WRONG — those are runno values, not codes.
- Fix fallback to: `['mr' => 147, 'mrs' => 149, 'miss' => 15134, 'ms' => 148, 'dr' => 558, 'child' => 14632, 'sir' => 1328]`
- DotwAI BookingService passes `'salutation' => 'Mr'` (string) which gets cast to `(int) 'Mr'` = 0, falls back to `?? 1` (which = Dr, not Mr).
- BookingService MUST call `DotwService::getSalutationIds()` and resolve label → DOTW code.
- Files to fix:
  - `app/Services/DotwService.php` — fix fallback map in `getSalutationIds()`, fix `buildPassengersXml()`
  - `app/Console/Commands/DotwCertify.php` — fix fallback map in `fetchSalutationMap()`
  - `app/Modules/DotwAI/Services/BookingService.php` — resolve salutation label to DOTW code

### CERT-02: Special Request Codes (CRITICAL)
- Olga's screenshot shows 23 special request options with their REAL codes (e.g., 1711 = non-smoking room, 92255 = allergy, etc.)
- We send `<req runno="0">1</req>` — code `1` does NOT exist in DOTW. Non-smoking = 1711.
- Must store special request codes and periodically sync from DOTW API.
- DotwAI BookingService doesn't pass `specialRequests` at all — wire it in.
- Create a method to fetch/cache special request codes (similar to salutations).
- Full code list from screenshot:
  - 92255: Allergy - Nut or Food or Bedding
  - 92245: Guest celebrating a birthday
  - 92235: Guest celebrating a wedding anniversary
  - 92295: Guest has a sensory impairment (hearing or vision loss)
  - 92265: Guest requires space for a CPAP machine
  - 92225: Hotel Membership Number
  - 1717: Mark the guest as a VIP
  - 1718: Mark the guests as a honeymoon couple
  - 1719: Request a baby cot
  - 1711: Request a non-smoking room
  - 92285: Request a room close to elevators or amenities
  - 1713: Request a room on a higher floor
  - 1714: Request a room on a lower floor
  - 1712: Request a smoking room
  - 92305: Request a wheelchair-accessible room with a separate shower
  - 92215: Request adjacent rooms
  - 1710: Request an interconnecting room
  - 92325: Request double bedding
  - 1715: Request early check-in
  - 93975: Request late check-in
  - 1716: Request late check-out
  - 92275: Request refrigeration for insulin (subject to availability)
  - 92315: Request twin bedding

### CERT-03: rateBasis = 0 Fix
- Olga's screenshot shows our 2-room search: room 0 has `rateBasis=1331` (Breakfast), room 1 has `rateBasis=-1`. Inconsistent.
- She also sees `rateBasis=0` in "almost all requests". This is wrong — must be -1 for "all rates" or a specific rate basis ID.
- Audit ALL code paths where rateBasis could default to 0.
- In DotwService `buildRoomsXml()`, default is `(int) ($room['rateBasis'] ?? -1)` — but callers may pass 0 explicitly.
- Check HotelSearchService and BookingService for any path that sends rateBasis=0.

### CERT-04: Nationality/Residence Collection
- Currently hardcoded to 66 (Kuwait) in config.
- Olga says: "include a dropdown that allows users to accurately select their nationality and residence".
- For WhatsApp flow: ask user for nationality during booking conversation. Store in booking context.
- HotelSearchService has `resolveNationalityCode()` via fuzzy match — but residence is always default.
- Wire residence through the same fuzzy-match pipeline.
- DotwCertify: keep 66 as default for cert tests (we're in Kuwait).

### CERT-05: Remove APR Flow (DOTW Removed APRs)
- Olga: "Please disregard APRs, these has been recently removed from our API."
- Remove APR booking flow from BookingService (`savebooking` + `bookitinerary`).
- All bookings now use `confirmbooking` only.
- Remove `is_apr` logic, APR detection in HotelSearchService.
- Remove DotwCertify Test 16 (APR test) or mark as N/A.
- Clean up related code: `invoiceAPRBooking()`, APR auto-invoice in lifecycle.

### CERT-06: 2-Room Cancellation Test
- Olga: "Please cancel the booking for 2 rooms and share evidence."
- DotwCertify needs a test case that books 2 rooms then cancels.
- CancellationService uses simple `cancelBooking` format.
- May need `testPricesAndAllocation` wrapper for partial cancellation.
- Check `productsLeftOnItinerary` in cancel response.

### CERT-07: Mandatory Display Features in WhatsApp
- ALL features must be shown to end clients BEFORE booking AND in confirmation/voucher:
  1. Cancellation Policy — show rules with dates/charges
  2. Tariff Notes — at checkout step and on confirmation/voucher
  3. Minimum Stay — before confirming booking
  4. Minimum Selling Price (MSP) — on all steps. Currently MSP=0 in getRooms (not propagated from searchhotels).
  5. Special Promotions — show applied specials
  6. Special Requests — allow selection and show in confirmation
  7. Restricted Cancellation Rules — before confirming (general CLX, not APR-specific)
  8. Taxes & Property Fees — at checkout and on confirmation/voucher
- MessageBuilderService must format all these in WhatsApp-friendly text.
- Voucher (text-based WhatsApp message) must include all post-booking features.

### CERT-08: B2B/B2C Connection Document
- Olga: "I still have not received information as of how will other agencies connect through your development."
- Write a document explaining:
  - Multi-tenant architecture (Company → Branch → Agent)
  - WhatsApp as the client interface
  - Each agency gets their own DOTW credentials stored in company settings
  - B2B: agents search/book via WhatsApp, credit line or payment gateway
  - B2C: customers book via WhatsApp, payment upfront with markup
  - API endpoints exposed for n8n workflow consumption
  - White-label capability

### CERT-09: Test Access / Screenshots
- Olga offers to test directly as test user via WhatsApp.
- Alternative: provide screenshots + DOTW logs showing full booking flow.
- Need to capture WhatsApp conversation screenshots showing all mandatory features displayed.
- This is a documentation/evidence task, not code.

### Claude's Discretion
- Implementation order and plan grouping
- Whether to cache special request codes in DB or config file
- How to format mandatory features in WhatsApp messages (layout, emoji usage)
- Whether to add a `getSpecialRequests` API method or reuse existing patterns

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Core DOTW Integration
- `app/Services/DotwService.php` — Main DOTW API service (XML building, API calls, response parsing)
- `app/Console/Commands/DotwCertify.php` — Certification test runner (19 tests)
- `config/dotw.php` — DOTW configuration (rate basis codes, API settings)

### DotwAI Module
- `app/Modules/DotwAI/Services/BookingService.php` — Production booking flow (B2B/B2C)
- `app/Modules/DotwAI/Services/HotelSearchService.php` — Hotel search and room details
- `app/Modules/DotwAI/Services/MessageBuilderService.php` — WhatsApp message formatting
- `app/Modules/DotwAI/Services/CancellationService.php` — Cancellation flow
- `app/Modules/DotwAI/Config/dotwai.php` — DotwAI module config

### DOTW API Skill Reference
- `.claude/skills/dotw-api/SKILL.md` — DOTW V4 XML API specification and best practices

### Olga's Email (source of truth for this phase)
- `docs/Dotwcert/v1.htm` — Full email thread (UTF-16 encoded Word HTML)
- `docs/Dotwcert/v1_files/image003.png` — Salutation ID mapping screenshot (12 codes)
- `docs/Dotwcert/v1_files/image006.gif` — Special request codes screenshot (23 codes)
- `docs/Dotwcert/v1_files/image010.png` — rateBasis inconsistency screenshot (2-room search)
- `docs/Dotwcert/v1_files/image001.png` — Broken passenger XML showing salutation=1

</canonical_refs>

<specifics>
## Specific Ideas

- Salutation fallback map must match EXACT values from Olga's screenshot (image003.png)
- Special request codes must match EXACT values from Olga's screenshot (image006.gif)
- For WhatsApp mandatory features, format similar to existing voucher text messages (Phase 19 decision: text-based, not PDF)
- rateBasis audit: search for any `rateBasis` assignment that could produce 0
- APR removal: search for `is_apr`, `savebooking`, `bookitinerary`, `invoiceAPRBooking`, `nonrefundable` to find all APR-related code

</specifics>

<deferred>
## Deferred Ideas

- Full UI for nationality/residence selection (WhatsApp text input for now, proper dropdowns in future mobile app)
- Automated periodic sync of salutation/special request codes (manual sync command for now)
- PDF voucher with mandatory features (text-based for now per Phase 19 decision)

</deferred>

---

*Phase: 24-dotw-certification-fixes-v2-olga-march-27-feedback*
*Context gathered: 2026-03-28 from Olga's email analysis*
