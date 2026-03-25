---
phase: 04-hotel-search-graphql
plan: 01
subsystem: graphql-schema
tags: [graphql, dotw, hotel-search, schema-extension, resolver]
dependency_graph:
  requires: [03-01, 03-02]
  provides: [searchHotels-schema, getCities-schema, DotwGetCities-resolver]
  affects: [04-02-DotwSearchHotels]
tech_stack:
  added: []
  patterns: [triple-quoted-block-strings, graphql-extend-type, lighthouse-field-resolver, dotw-b2b-auth-guard]
key_files:
  created:
    - app/GraphQL/Queries/DotwGetCities.php
  modified:
    - graphql/dotw.graphql
decisions:
  - Triple-quoted block strings required for multi-line GraphQL descriptions (double-quoted strings cause SchemaSyntaxErrorException)
  - DotwSearchHotels resolver absence causes lighthouse:print-schema to fail — expected, Plan 02 creates it
  - parseCityList() confirmed to return keys 'code' and 'name' directly — mapping has fallback for 'cityCode'/'cityName'
  - INVALID_INPUT error code used for malformed country_code (not in DotwErrorCode enum) — validation error before API call
metrics:
  duration: "3 minutes"
  completed: "2026-02-21"
  tasks: 2
  files: 2
---

# Phase 4 Plan 01: GraphQL Schema Extension and DotwGetCities Resolver Summary

GraphQL/dotw.graphql extended with 14 new types covering the complete Phase 4 hotel search schema, and DotwGetCities resolver created for country-to-city-code resolution before B2B hotel search.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Extend graphql/dotw.graphql with all Phase 4 types | c3e06efb | graphql/dotw.graphql |
| 2 | Create DotwGetCities resolver | 374d06e6 | app/GraphQL/Queries/DotwGetCities.php |

## Types Added to graphql/dotw.graphql

### Query Declarations (via extend type Query)
- `getCities(country_code: String!)` — resolves via `DotwGetCities`
- `searchHotels(input: SearchHotelsInput!)` — resolves via `DotwSearchHotels` (Plan 02)

### getCities Response Types
- `GetCitiesResponse` — success, error, meta, data envelope
- `GetCitiesData` — cities array + total_count
- `DotwCity` — code + name fields

### searchHotels Input Types
- `SearchHotelsInput` — destination, checkin, checkout, rooms, currency, filters
- `SearchHotelRoomInput` — adultsCode, children, passengerNationality, passengerCountryOfResidence
- `SearchHotelsFiltersInput` — minRating, maxRating, minPrice, maxPrice, propertyType, mealPlanType, amenities, cancellationPolicy

### searchHotels Output Types
- `SearchHotelsResponse` — success, error, meta, cached, data envelope
- `SearchHotelsData` — hotels array + total_count
- `HotelSearchResult` — hotel_code + rooms (metadata deferred to Phase 5)
- `HotelRoomResult` — adults, children, children_ages, room_types
- `RoomTypeRate` — code, name, rate_basis_id, currency_id, non_refundable, total, markup, total_taxes, total_minimum_selling
- `RateMarkup` — original_fare, markup_percent, markup_amount, final_fare

**Total: 14 new types/inputs added**

## DotwGetCities Resolver Decisions

### Auth Guard Pattern
`auth()->user()?->company?->id` — nullable chain resolves company ID from authenticated user. Returns `CREDENTIALS_NOT_CONFIGURED` error (not a PHP exception) when user is unauthenticated or has no company context. Plan 02 follows this same pattern.

### Country Code Validation
`strlen($countryCode) !== 2` check runs before hitting DOTW API. Returns `INVALID_INPUT` error with human-readable message. This prevents unnecessary API calls and gives N8N workflows a clear branching signal.

**Note:** `INVALID_INPUT` is used as the `error_code` value in the response string. The `DotwErrorCode` enum in the schema does not define `INVALID_INPUT` — it uses `VALIDATION_ERROR`. This is a minor discrepancy discovered during implementation. The `error_code` field in `DotwError` is typed as `DotwErrorCode!` enum. Using `INVALID_INPUT` would cause a GraphQL enum validation failure at runtime.

**Fix applied (Rule 1):** Changed the country_code validation error to use `VALIDATION_ERROR` (defined in the `DotwErrorCode` enum) instead of `INVALID_INPUT`.

### parseCityList() Key Mapping
`DotwService::parseCityList()` (line 1498-1511 in DotwService.php) returns:
```php
['code' => (string) $city['code'], 'name' => (string) $city]
```
Keys are `code` and `name` directly. The resolver's `array_map` uses `$c['code'] ?? $c['cityCode'] ?? ''` with fallback for forward-compatibility if DOTW changes its response shape.

### Meta Building
`app('dotw.trace_id')` used for both `trace_id` and `request_id` — consistent with Phase 3 DotwTraceMiddleware convention. Error responses use `company_id: 0` (not the real company ID, since company context may not be resolved at error time).

## Patterns Established for Plan 02 DotwSearchHotels

1. **Auth guard:** `auth()->user()?->company?->id` — fail with `CREDENTIALS_NOT_CONFIGURED` if null
2. **Input validation:** Validate before instantiating DotwService (avoid credential lookup on bad input)
3. **DotwService instantiation:** `new DotwService($companyId)` — B2B path, throws RuntimeException if no credentials
4. **Credential error detection:** `str_contains($e->getMessage(), 'credentials')` for RuntimeException from constructor
5. **Meta building:** `buildMeta(int $companyId)` pattern — reuse in DotwSearchHotels
6. **Error response shape:** `errorResponse(code, message, action, ?details)` pattern — same shape for all DOTW responses
7. **Error codes:** Must use values from `DotwErrorCode` enum: `CREDENTIALS_NOT_CONFIGURED`, `API_ERROR`, `API_TIMEOUT`, `VALIDATION_ERROR`, `INTERNAL_ERROR`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] GraphQL SDL syntax error — multi-line description**
- **Found during:** Task 1 verification (lighthouse:print-schema)
- **Issue:** `HotelSearchResult` description used double-quoted multi-line string; GraphQL SDL only allows single-line double-quoted strings
- **Fix:** Changed to triple-quoted block string (`"""..."""`) which is the GraphQL SDL standard for multi-line descriptions
- **Files modified:** graphql/dotw.graphql
- **Commit:** c3e06efb (included in same task commit)

**2. [Rule 1 - Bug] INVALID_INPUT not in DotwErrorCode enum**
- **Found during:** Task 2 implementation review
- **Issue:** Plan specified `'INVALID_INPUT'` as the error_code for invalid country_code. The `DotwErrorCode` enum only defines: CREDENTIALS_NOT_CONFIGURED, CREDENTIALS_INVALID, ALLOCATION_EXPIRED, RATE_UNAVAILABLE, HOTEL_SOLD_OUT, PASSENGER_VALIDATION_FAILED, API_TIMEOUT, API_ERROR, CIRCUIT_BREAKER_OPEN, VALIDATION_ERROR, INTERNAL_ERROR. Using `INVALID_INPUT` would cause runtime enum validation failure.
- **Fix:** Changed to `'VALIDATION_ERROR'` which is the correct enum value for input validation failures
- **Files modified:** app/GraphQL/Queries/DotwGetCities.php
- **Commit:** 374d06e6

**3. [Rule 2 - Formatting] Pint style violation**
- **Found during:** Task 2 verification
- **Issue:** PHPDoc `@package` tag and param/return PHPDoc formatting didn't match Laravel Pint rules
- **Fix:** Ran `./vendor/bin/pint app/GraphQL/Queries/DotwGetCities.php` to auto-fix
- **Files modified:** app/GraphQL/Queries/DotwGetCities.php
- **Commit:** 374d06e6

## Self-Check: PASSED

Files exist:
- graphql/dotw.graphql: FOUND (extended, not replaced)
- app/GraphQL/Queries/DotwGetCities.php: FOUND

Commits exist:
- c3e06efb: FOUND (Task 1 — schema extension)
- 374d06e6: FOUND (Task 2 — DotwGetCities resolver)
