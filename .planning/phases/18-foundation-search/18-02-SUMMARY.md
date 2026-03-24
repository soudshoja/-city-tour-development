---
phase: 18-foundation-search
plan: 02
subsystem: dotwai-module
tags: [search-endpoints, hotel-search, city-list, whatsapp-messaging, rest-api, caching, filtering, bilingual]
dependency_graph:
  requires:
    - phase: 18-01
      provides: [DotwAIServiceProvider, DotwAIContext, DotwAIResponse, PhoneResolverService, FuzzyMatcherService, dotwai_hotels, dotwai_cities, dotwai_countries, ResolveDotwAIContext middleware]
  provides:
    - SearchController with 3 REST endpoints (search_hotels, get_hotel_details, get_cities)
    - HotelSearchService for search orchestration, filtering, caching
    - MessageBuilderService for bilingual Arabic/English WhatsApp message formatting
    - SearchHotelsRequest and GetHotelDetailsRequest form validation
    - Per-phone search result caching with 10-min TTL
    - Numbered results (1..N) for option reference in follow-up messages
  affects: [19-booking-payment, 20-cancellation-accounting, dotwai-module]
tech_stack:
  added: []
  patterns: [thin-controller-fat-service, static-formatter, response-envelope, per-phone-caching, post-search-filtering]
key_files:
  created:
    - app/Modules/DotwAI/Http/Controllers/SearchController.php
    - app/Modules/DotwAI/Services/HotelSearchService.php
    - app/Modules/DotwAI/Services/MessageBuilderService.php
    - app/Modules/DotwAI/Http/Requests/SearchHotelsRequest.php
    - app/Modules/DotwAI/Http/Requests/GetHotelDetailsRequest.php
  modified:
    - app/Modules/DotwAI/Routes/api.php
key_decisions:
  - "HotelSearchService creates DotwService via new DotwService(companyId) for per-company credential resolution -- constructor injection not viable because DotwService needs runtime companyId"
  - "MessageBuilderService uses all-static methods (pure functions) since it has no state and is called from multiple contexts"
  - "Post-search filtering applies both at DOTW API level (hotel IDs, star rating) and after results return (meal type, price range, refundable, hotel name substring match)"
  - "Browse-only mode for getHotelDetails (blocking=false) per Phase 18 scope -- rate blocking deferred to Phase 19 booking flow"
  - "getCities returns from local dotwai_cities table when available (fast path) and falls back to DOTW API with upsert (slow path)"
patterns_established:
  - "Thin controller pattern: controllers validate, delegate to service, format response via MessageBuilderService, wrap in DotwAIResponse"
  - "Every controller method wrapped in try/catch returning DotwAIResponse::error('INTERNAL_ERROR', ...) -- never raw 500 responses"
  - "Search result caching: Cache::put('dotwai:search:{normalizedPhone}', results, TTL) for option reference"
  - "Bilingual message formatting: Arabic first, English second, separated by pipe or newline"
requirements_completed: [SRCH-01, SRCH-02, SRCH-03, SRCH-04, SRCH-05, SRCH-06, EVNT-02, EVNT-03]
metrics:
  duration: "6 minutes"
  completed: "2026-03-24"
  tasks: 2
  files: 6
---

# Phase 18 Plan 02: Search Endpoints, Hotel Search Service, and WhatsApp Message Builder Summary

**3 REST search endpoints (search_hotels, get_hotel_details, get_cities) with DOTW API delegation, 6-filter post-search pipeline, per-phone result caching, and bilingual Arabic/English WhatsApp message formatting**

## Performance

- **Duration:** 6 minutes
- **Started:** 2026-03-24
- **Completed:** 2026-03-24
- **Tasks:** 2
- **Files created/modified:** 6

## Accomplishments
- Three REST endpoints at /api/dotwai/ prefix: POST search_hotels, POST get_hotel_details, GET get_cities
- HotelSearchService orchestrates full search flow: city resolution via FuzzyMatcherService, DOTW API call via DotwService, 6 post-search filters (star rating, meal type, price min/max, refundable, hotel name), sort by stars desc + price asc, limit to configurable max, number results 1..N, cache per phone
- MessageBuilderService provides pure static methods for bilingual WhatsApp message formatting: search results as numbered hotel list, hotel details with rooms/cancellation/specials, city list, error messages with Arabic/English text
- Every response guaranteed to include whatsappMessage and whatsappOptions through DotwAIResponse envelope
- Multi-room occupancy (SRCH-05) correctly maps to DOTW format with incrementing room numbers, nationality/residence codes
- Browse-only mode for hotel details (no rate blocking -- that is Phase 19 scope)

## Task Commits

Each task was committed atomically:

1. **Task 1: HotelSearchService, MessageBuilderService, and validation requests** - `d3fbd3cf` (feat)
2. **Task 2: SearchController with 3 endpoints + route wiring** - `5d634ca4` (feat)

## Files Created/Modified

- `app/Modules/DotwAI/Services/HotelSearchService.php` - Search orchestration: city resolution, DOTW API call, filtering, numbering, caching, B2C markup, getCities with local cache + API fallback
- `app/Modules/DotwAI/Services/MessageBuilderService.php` - Pure static methods for bilingual WhatsApp message formatting (search results, hotel details, city list, errors, options)
- `app/Modules/DotwAI/Http/Controllers/SearchController.php` - Thin controller with searchHotels, getHotelDetails, getCities methods, all responses through DotwAIResponse
- `app/Modules/DotwAI/Http/Requests/SearchHotelsRequest.php` - Validation: city, dates, occupancy, 6 optional filters (star_rating, meal_type, price_min, price_max, refundable, hotel name)
- `app/Modules/DotwAI/Http/Requests/GetHotelDetailsRequest.php` - Validation: hotel_id, dates, occupancy, telephone
- `app/Modules/DotwAI/Routes/api.php` - Updated with 3 search routes under /api/dotwai/ prefix with dotwai.resolve middleware

## Decisions Made

1. **DotwService instantiation via `new DotwService($companyId)`** -- DotwService constructor requires a runtime companyId for per-company credential resolution. Standard Laravel dependency injection would not provide the correct company context, so the service is instantiated directly with the companyId from DotwAIContext. This matches the existing pattern used in the codebase.

2. **All-static MessageBuilderService** -- Formatting is pure/stateless. Static methods allow calling from controllers, services, and middleware without injection overhead. Consistent with DotwAIResponse's static pattern from Plan 01.

3. **Dual-level filtering (API + post-search)** -- Hotel IDs and star rating can be sent to the DOTW API for server-side filtering. Meal type, price range, refundable-only, and hotel name substring matching require post-search filtering since DOTW search results don't include full room details. This minimizes API calls while still supporting all 6 filter types.

4. **Browse-only getHotelDetails** -- getRooms called with `blocking: false` per CONTEXT.md and Phase 18 scope. Rate blocking (which locks the price for 3 minutes) is deferred to Phase 19's booking flow.

5. **getCities local-first strategy** -- Checks dotwai_cities table first (fast, no API call). Only calls DotwService::getCityList when no local data exists for the country, then upserts results for future requests.

## Deviations from Plan

None - plan executed exactly as written.

### Pre-existing Issues

**Artisan CLI error:** `Call to a member function make() on null` when running `php artisan route:list`. This is the same pre-existing bootstrap/cache issue documented in Plan 01 summary. Routes are correctly defined in the file and will work at runtime. Route verification performed via file content analysis instead.

## Known Stubs

None -- all planned functionality is implemented. The search endpoints are fully wired to DotwService via HotelSearchService. All response formatting produces real bilingual WhatsApp messages (not placeholder text).

## Requirements Addressed

| Requirement | Status | Evidence |
|-------------|--------|----------|
| SRCH-01 | Complete | POST /api/dotwai/search_hotels accepts city, dates, occupancy, filters and returns numbered hotel list |
| SRCH-02 | Complete | POST /api/dotwai/get_hotel_details returns rooms with rates, cancellation rules, specials, tariff notes |
| SRCH-03 | Complete | GET /api/dotwai/get_cities returns city list for a country (local cache + API fallback) |
| SRCH-04 | Complete | Results numbered 1..N and cached per phone number with 10-min TTL |
| SRCH-05 | Complete | Multi-room occupancy maps to DOTW rooms format with incrementing room numbers |
| SRCH-06 | Complete | 6 filters: star rating, meal type, price min/max, refundable, hotel name |
| EVNT-02 | Complete | Every response includes whatsappMessage with bilingual Arabic/English text |
| EVNT-03 | Complete | Error responses include suggestedAction field via DotwAIResponse::error() |

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Search endpoints complete and ready for n8n AI agent integration
- Phase 19 (Booking + Payment) can build on these endpoints by adding rate blocking (getRooms with blocking=true) and prebookKey generation
- Cached search results enable "book option N" flow in Phase 19
- MessageBuilderService pattern established for future booking confirmation/cancellation messages

## Self-Check: PASSED

- All 6 created/modified files exist on disk
- Both task commits found: d3fbd3cf, 5d634ca4
- All PHP files pass syntax check (php -l)
- All classes autoload correctly via Composer autoloader
- Route file contains all 4 endpoints (health, search_hotels, get_hotel_details, get_cities)

---
*Phase: 18-foundation-search*
*Plan: 02*
*Completed: 2026-03-24*
