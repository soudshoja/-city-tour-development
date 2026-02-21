---
phase: 04-hotel-search-graphql
verified: 2026-02-21T10:00:00Z
status: human_needed
score: 11/11 must-haves verified
re_verification: true
  previous_status: gaps_found
  previous_score: 10/11
  gaps_closed:
    - "SEARCH-03: REQUIREMENTS.md updated in commit b160f59a to accurately describe the actual behavior — when currency is not provided, the param is omitted from the DOTW API call; DOTW account default currency applies; no per-company override stored. Text now matches code at DotwSearchHotels.php line 81 exactly."
  gaps_remaining: []
  regressions: []
human_verification:
  - test: "End-to-end hotel search with real credentials"
    expected: "searchHotels returns SearchHotelsResponse with hotels[], each containing hotel_code and rooms[].room_types[] with markup applied. Second identical call within 2.5 minutes returns cached: true."
    why_human: "Requires live DOTW API credentials stored in company_dotw_credentials table and an authenticated company session."
  - test: "getCities query with valid country_code"
    expected: "getCities(country_code: 'AE') returns GetCitiesResponse with data.cities[] where each city has code and name. Response meta contains trace_id."
    why_human: "Requires live DOTW API credentials and authenticated company session."
  - test: "Unauthenticated searchHotels guard"
    expected: "POST to /graphql with searchHotels query and no Authorization header returns HTTP 200 with success: false and error_code: CREDENTIALS_NOT_CONFIGURED — no 401, no PHP exception."
    why_human: "Requires running Laravel server (php artisan serve) and a GraphQL client."
  - test: "SEARCH-03 currency omission behavior"
    expected: "searchHotels called without the currency field succeeds and DOTW responds with whatever currency the DOTW account is configured for. No PHP error from absent currency parameter."
    why_human: "Requires live DOTW API to observe which currency DOTW returns when no currency param is sent."
---

# Phase 4: Hotel Search GraphQL Verification Report

**Phase Goal:** Agents can search hotels by destination, dates, and room configuration through the GraphQL API, with full DOTW filter support, cached results, and an audit trail entry per search.
**Verified:** 2026-02-21T10:00:00Z
**Status:** human_needed
**Re-verification:** Yes — third pass after commit b160f59a closed the SEARCH-03 requirements mismatch gap.

---

## Re-verification Summary

| Item | Previous | Current | Change |
|------|----------|---------|--------|
| SEARCH-03 requirements/code mismatch | FAILED | RESOLVED | Commit b160f59a updated REQUIREMENTS.md line 40 to describe actual behavior — text now matches code exactly |
| All other 10 items | VERIFIED | VERIFIED | No regressions detected |

**Score: 11/11** — all must-haves verified. No gaps remain. Human verification required before declaring full pass.

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | GraphQL schema defines searchHotels query with SearchHotelsInput and all filter fields | VERIFIED | `graphql/dotw.graphql` line 112 — `searchHotels(input: SearchHotelsInput!)`. SearchHotelsFiltersInput contains all 8 fields (minRating, maxRating, minPrice, maxPrice, propertyType, mealPlanType, amenities, cancellationPolicy) |
| 2 | getCities query accepts country_code and returns cities with code and name | VERIFIED | `graphql/dotw.graphql` lines 104-109 — getCities(country_code: String!) returning GetCitiesResponse. DotwCity type has code and name fields. |
| 3 | SearchHotelRoomInput accepts adultsCode, children, passengerNationality, passengerCountryOfResidence | VERIFIED | `graphql/dotw.graphql` lines 161-170 — all 4 fields present with correct types |
| 4 | SearchHotelsFiltersInput has all 8 filter fields | VERIFIED | `graphql/dotw.graphql` lines 173-190 — all 8 fields confirmed present |
| 5 | HotelSearchResult returns hotel_code and rooms — hotel metadata deferred to Phase 5, documented in schema | VERIFIED | Schema description on HotelSearchResult explicitly states hotel name, city name, star rating, and image_url are not available from DOTW searchhotels and are deferred to Phase 5 getRoomRates |
| 6 | RoomTypeRate includes currency_id from DOTW rateType currencyid | VERIFIED | `graphql/dotw.graphql` line 248 — currency_id: String!. DotwSearchHotels.php line 193: `'currency_id' => $rt['rateType']` |
| 7 | searchHotels resolver returns hotels with full DOTW field set and markup applied per rate | VERIFIED | DotwSearchHotels.php lines 170-210 — formatHotels() maps all parseHotels() keys. applyMarkup() called at line 187 for every RoomTypeRate |
| 8 | Cache hit detection — isCached() called before remember(), cached field reflects true hit | VERIFIED | DotwSearchHotels.php line 87: `$wasCached = $this->cache->isCached($cacheKey)` before `$this->cache->remember(...)` at line 90 |
| 9 | Unauthenticated calls return CREDENTIALS_NOT_CONFIGURED, never fall back to env credentials | VERIFIED | Both resolvers check `auth()->user()?->company?->id === null` at lines 70/328 and return errorResponse early. No CompanyDotwCredential import or DB lookup for auth guard — correct B2B pattern. |
| 10 | Audit trail entry written per search internally by DotwService::searchHotels() | VERIFIED | DotwSearchHotels.php PHPDoc at line 25 explicitly states resolver does NOT call DotwAuditService. No DotwAuditService usage appears in the file — audit handled inside DotwService internally. |
| 11 | SEARCH-03: REQUIREMENTS.md accurately describes currency behavior | VERIFIED | REQUIREMENTS.md line 40 now reads: "Query accepts optional currency; when omitted, the param is excluded from the DOTW API call and DOTW account default currency applies — no per-company override stored." DotwSearchHotels.php line 81: `$currency = trim($input['currency'] ?? '') ?: null; // null = omit from DOTW call; DOTW account default applies`. Text matches code exactly. |

**Score:** 11/11 truths verified

---

## Required Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `graphql/dotw.graphql` | All Phase 4 types and both query declarations | VERIFIED | 18 references to Phase 4 types confirmed. Contains GetCitiesResponse, GetCitiesData, DotwCity, SearchHotelsInput, SearchHotelRoomInput, SearchHotelsFiltersInput, SearchHotelsResponse, SearchHotelsData, HotelSearchResult, HotelRoomResult, RoomTypeRate, RateMarkup + extend type Query with getCities and searchHotels |
| `app/GraphQL/Queries/DotwGetCities.php` | getCities resolver with auth guard, validation, DotwService call | VERIFIED | 136 lines. Full implementation: auth guard, country_code length validation, DotwService($companyId) instantiation, parseCityList key mapping with fallback, buildMeta() and errorResponse() helpers |
| `app/GraphQL/Queries/DotwSearchHotels.php` | searchHotels resolver with cache, markup, error handling | VERIFIED | 362 lines. Full implementation: DotwCacheService injected, isCached() before remember(), DotwService inside closure, formatHotels() with applyMarkup(), buildFilters() with all DOTW conditions |

---

## Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| `graphql/dotw.graphql` | `App\GraphQL\Queries\DotwGetCities` | `@field(resolver: "App\\GraphQL\\Queries\\DotwGetCities")` | WIRED | Line 109 — resolver directive matches class namespace |
| `graphql/dotw.graphql` | `App\GraphQL\Queries\DotwSearchHotels` | `@field(resolver: "App\\GraphQL\\Queries\\DotwSearchHotels")` | WIRED | Line 113 — resolver directive matches class namespace |
| `app/GraphQL/Queries/DotwSearchHotels.php` | `app/Services/DotwCacheService.php` | `isCached()` called before `remember()` | WIRED | Line 87: isCached, line 90: remember — correct ordering confirmed |
| `app/GraphQL/Queries/DotwSearchHotels.php` | `app/Services/DotwService.php` | `new DotwService($companyId)` inside remember() closure | WIRED | Line 95 inside cache closure — DotwService instantiated with int companyId, never null |
| `app/GraphQL/Queries/DotwSearchHotels.php` | `app/Services/DotwService.php` | `applyMarkup()` on each rate total | WIRED | Line 187: `$markup = $dotwService->applyMarkup((float) $rt['total'])` |
| `app/GraphQL/Queries/DotwGetCities.php` | `app/Services/DotwService.php` | `new DotwService($companyId)` + `getCityList()` | WIRED | Lines 53-54: instantiates DotwService with companyId and calls getCityList($countryCode) |

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| SEARCH-01 | 04-01 | searchHotels query accepts destination, check-in, check-out, resayil_message_id header | SATISFIED | SearchHotelsInput has destination, checkin, checkout. ResayilContextMiddleware extracts X-Resayil-Message-ID header into request attributes, passed to DotwService::searchHotels() via resayilMessageId param |
| SEARCH-02 | 04-01 | Room configuration with adults, children, ages | SATISFIED | SearchHotelRoomInput has adultsCode (Int!), children ([Int!]!), passengerNationality, passengerCountryOfResidence |
| SEARCH-03 | 04-01, 04-03 | Query accepts optional currency; when omitted, DOTW account default applies | SATISFIED | REQUIREMENTS.md line 40 updated by commit b160f59a to match actual code behavior. DotwSearchHotels.php line 81 sets `$currency = null` when absent and conditionally includes in searchParams only when non-null (lines 103-105). No currency column in migration or model — intentional after revert. |
| SEARCH-04 | 04-01 | Full DOTW filter vocabulary | SATISFIED | SearchHotelsFiltersInput has all 8 fields. buildFilters() maps to DOTW V4 fieldName/fieldTest/fieldValues structure |
| SEARCH-05 | 04-02 | Hotels with cheapest rate per meal plan per room type | SATISFIED | DotwService::parseHotels() + formatHotels() returns cheapest rate per rateBasis per roomType |
| SEARCH-06 | 04-02, 04-03 | Hotel code, name, city, rating, location, image_url, cheapest rates | PARTIAL — DEFERRED TO PHASE 5 (CORRECTLY TRACKED) | REQUIREMENTS.md line 43 correctly shows `[ ]` unchecked with deferral note. hotel_code and rooms returned in Phase 4. name/city/rating/location/image_url deferred to Phase 5 getRoomRates. Traceability table line 217 shows "Phase 5 — Partial." |
| SEARCH-07 | 04-02 | Audit log entry per search in dotw_audit_logs | SATISFIED | DotwSearchHotels.php has NO direct DotwAuditService calls. Audit handled internally by DotwService::searchHotels(). PHPDoc at line 25 explicitly documents this design decision. |
| SEARCH-08 | 04-02 | cached: true on cache hit within 2.5 minutes | SATISFIED | isCached() before remember() pattern wired. `wasCached` flag at line 87 set before cache read. SearchHotelsResponse.cached field returns wasCached at line 139. |
| B2B-01 | 04-01 | Multiple rooms in single search | SATISFIED | SearchHotelsInput.rooms: [SearchHotelRoomInput!]! — array input supports multi-room complex itineraries |
| B2B-02 | 04-01 | Full DOTW V4 filter vocabulary | SATISFIED | All 8 filter fields in SearchHotelsFiltersInput. buildFilters() maps to DOTW fieldName/fieldTest/fieldValues structure |
| B2B-03 | 04-02 | All DOTW room fields, not summarized | SATISFIED | RoomTypeRate includes: code, name, rate_basis_id, currency_id, non_refundable, total, markup, total_taxes, total_minimum_selling |

**ORPHANED REQUIREMENT CHECK:** No orphaned requirements. B2B-04 (multi-company credential isolation) is mapped to Phase 1 in REQUIREMENTS.md and is not in Phase 4 scope.

---

## Anti-Patterns Found

None. No TODO/FIXME/placeholder comments in any Phase 4 files. No empty implementations. No return null stubs in `__invoke` methods. No console.log-only handlers.

---

## Human Verification Required

### 1. End-to-End Hotel Search with Real Credentials

**Test:** Call `searchHotels` with a valid company JWT, destination "DXB", checkin "2026-04-01", checkout "2026-04-03", one room (adultsCode: 2, children: []). Then call again with identical input within 2.5 minutes.
**Expected:** First call returns cached: false with hotels[]. Second call returns cached: true with same data. Each hotel has hotel_code and rooms[].room_types[] with markup breakdown.
**Why human:** Requires live DOTW API credentials stored in company_dotw_credentials table and an authenticated company session.

### 2. getCities Country Code Resolution

**Test:** Call `getCities(country_code: "AE")` with an authenticated company user.
**Expected:** Returns data.cities[] with at least one city object containing code and name. Meta includes trace_id.
**Why human:** Requires live DOTW API and authenticated company session.

### 3. Unauthenticated searchHotels Guard

**Test:** POST to /graphql with searchHotels query and NO Authorization header.
**Expected:** HTTP 200 with `{"data": {"searchHotels": {"success": false, "error": {"error_code": "CREDENTIALS_NOT_CONFIGURED"}}}}` — no 401, no PHP exception.
**Why human:** Requires running Laravel server (php artisan serve) and a GraphQL client (curl or Insomnia).

### 4. SEARCH-03 Currency Omission Behavior

**Test:** Call searchHotels without the `currency` field. Inspect the DOTW API response to confirm which currency is returned.
**Expected per revert intent:** DOTW account default currency applies (the currency configured on the DOTW account, not USD, not a per-company override).
**Note:** REQUIREMENTS.md correctly reflects this behavior after commit b160f59a — no further code or requirements changes needed. This test confirms runtime behavior matches the documented design.
**Why human:** Requires live DOTW API to observe which currency DOTW returns when no currency param is sent.

---

## Commit History (Phase 4)

| Commit | Description | Verified |
|--------|-------------|---------|
| c3e06efb | feat(04-01): extend graphql/dotw.graphql with all Phase 4 types | EXISTS |
| 374d06e6 | feat(04-01): create DotwGetCities resolver | EXISTS |
| 94801520 | feat(04-02): create DotwSearchHotels resolver | EXISTS |
| 02268336 | fix(04-03): correct SEARCH-06 status and annotate SEARCH-03 in REQUIREMENTS.md | EXISTS |
| b93b6905 | feat(04-03): add currency column to migration and model | EXISTS (reverted by 96bf3dbb) |
| 6914ecf4 | feat(04-03): resolve company currency from DB in DotwSearchHotels | EXISTS (reverted by 96bf3dbb) |
| 96bf3dbb | revert(04-03): drop currency column, omit currency param when not provided | EXISTS — this is the implementation state |
| b160f59a | docs(04): update SEARCH-03 requirement text to match post-revert behavior | EXISTS — closes the SEARCH-03 gap |

The final commit b160f59a is the gap closure: it aligns REQUIREMENTS.md with the actual implementation produced by the revert.

---

_Verified: 2026-02-21T10:00:00Z_
_Verifier: Claude (gsd-verifier)_
_Re-verification: Yes — initial verified 2026-02-21T06:43:00Z, first re-verification 2026-02-21T08:00:00Z after 04-03 gap closure, second re-verification 2026-02-21T10:00:00Z after commit b160f59a closed SEARCH-03 requirements/code mismatch_
