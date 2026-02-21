# Phase 4: Hotel Search GraphQL — Research

**Researched:** 2026-02-21
**Domain:** Lighthouse GraphQL resolver, DOTW V4 searchhotels API, per-company B2B credential resolution, search result caching, audit logging
**Confidence:** HIGH — all findings based on existing codebase inspection (no external sources needed)

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| SEARCH-01 | GraphQL `searchHotels` query accepts destination (city code/name), check-in date, check-out date, resayil_message_id header | DotwService::searchHotels() already accepts these; new resolver wires them via GraphQL input type + ResayilContextMiddleware |
| SEARCH-02 | Query accepts room configuration (number of rooms, adults per room, children per room with ages) | DotwService::buildRoomsXml() handles multi-room arrays; input type must model `[SearchHotelRoomInput!]` |
| SEARCH-03 | Query accepts currency (defaults to company currency if not specified) | DotwService::searchHotels() accepts currency param; default resolution from CompanyDotwCredential or fallback |
| SEARCH-04 | Query supports full DOTW filter vocabulary: rating, price range, property type, meal plan type, amenities, cancellation policies | DotwService::buildFilterXml() uses generic `fieldName`/`fieldTest`/`fieldValues` condition array — all DOTW fields are passable |
| SEARCH-05 | Returns hotels with cheapest rate per meal plan per room type (DOTW searchhotels response) | DotwService::parseHotels() already parses this structure; new resolver formats it for GraphQL |
| SEARCH-06 | Response includes hotel code, name, city, rating, location, image_url, cheapest rates grouped by room type | parseHotels() returns hotelId + rooms[].roomTypes[]; hotel name, city, rating NOT returned by searchhotels — this is a known gap (see Open Questions) |
| SEARCH-07 | Logs search to `dotw_audit_logs` with resayil_message_id, destination, filters used | DotwService::searchHotels() already calls DotwAuditService::log() internally with OP_SEARCH |
| SEARCH-08 | Returns `cached: true` if result from 2.5 min cache, `cached: false` if fresh API call | DotwCacheService::isCached() called before remember(); resolver annotates response with cached flag |
| B2B-01 | GraphQL supports multiple room types in single search (agents can explore complex itineraries) | DotwService::buildRoomsXml() supports multiple rooms via `no=N` attribute; input type must accept array of rooms |
| B2B-02 | Filter support matches full DOTW V4 vocabulary (not hardcoded to common filters) | buildFilterXml() uses generic condition array — arbitrary fieldName/fieldTest combos are supported |
| B2B-03 | Room details include all DOTW fields (not summarized, allows detailed negotiation) | parseHotels() currently extracts partial fields; new resolver must pass all available fields through without summarizing |
</phase_requirements>

---

## Summary

Phase 4 builds the `searchHotels` GraphQL query that agents use via N8N/Resayil to find hotels by destination, dates, and room configuration. All infrastructure is in place from Waves 1 phases: DotwService (credential resolution), DotwCacheService (2.5-minute caching), DotwAuditService (audit logging), DotwTraceMiddleware (trace IDs), and DotwResponseEnvelope schema types. Phase 4 wires them together into a coherent GraphQL operation.

The existing `SearchDotwHotels.php` resolver in `app/GraphQL/Queries/` is a legacy resolver using the old pre-B2B instantiation pattern (`new DotwService()` without company_id). Phase 4 creates a **new** resolver specifically for the B2B `searchHotels` query, distinct from the legacy resolver which continues to serve `searchDotwHotels` (the old pre-DOTW-v1.0 operation). The new resolver must use per-company credential resolution, integrate DotwCacheService, and return the DotwResponseEnvelope shape.

A critical finding: DOTW's `searchhotels` command returns hotel_id, room types, rates — but does NOT return hotel name, city name, star rating, or image_url directly. These fields are available via a separate lookup or are embedded in getRooms responses. The resolver must address this gap explicitly (see Open Questions).

**Primary recommendation:** Create `app/GraphQL/Queries/DotwSearchHotels.php` (new B2B resolver), extend `graphql/dotw.graphql` with `searchHotels` query + input/output types, wire DotwCacheService and DotwAuditService correctly.

---

## Standard Stack

### Core (all already installed/built — no new dependencies)

| Component | Version/Location | Purpose | Status |
|-----------|-----------------|---------|--------|
| `DotwService` | `app/Services/DotwService.php` | DOTW API calls, credential resolution, XML builder | Built in Phase 1 |
| `DotwCacheService` | `app/Services/DotwCacheService.php` | 150s per-company search result caching | Built in Phase 3 |
| `DotwAuditService` | `app/Services/DotwAuditService.php` | Fail-silent audit logging to dotw_audit_logs | Built in Phase 2 |
| `DotwTraceMiddleware` | `app/GraphQL/Middleware/DotwTraceMiddleware.php` | Injects trace_id, timing headers | Built in Phase 3 |
| `ResayilContextMiddleware` | `app/GraphQL/Middleware/ResayilContextMiddleware.php` | Sets resayil_message_id on request attributes | Built in Phase 2 |
| Lighthouse | `nuwave/lighthouse` | GraphQL server, schema-first, resolver binding | Existing |
| `graphql/dotw.graphql` | schema file | DotwMeta, DotwError, DotwErrorCode, DotwErrorAction types | Built in Phase 3 |

### No New Packages Required

All dependencies exist. Phase 4 is purely wiring: new resolver class + schema extension.

---

## Architecture Patterns

### Pattern 1: New B2B Resolver (Separate from Legacy)

The existing `SearchDotwHotels.php` resolver is legacy (uses `new DotwService()` without company_id, uses old response shape). Phase 4 creates a **parallel** resolver:

- **Legacy:** `app/GraphQL/Queries/SearchDotwHotels.php` → `searchDotwHotels` query → continues unchanged
- **New B2B:** `app/GraphQL/Queries/DotwSearchHotels.php` → `searchHotels` query in dotw.graphql → new B2B operation

Both coexist. The legacy resolver is NOT modified in Phase 4.

### Pattern 2: Resolver Class Structure

Based on Phase 3 patterns (DotwTraceMiddleware summary section "How Phase 4+ Resolvers Access trace_id"):

```php
// Source: .planning/phases/03-cache-service-and-graphql-response-architecture/03-02-SUMMARY.md
class DotwSearchHotels
{
    public function __construct(
        private DotwCacheService $cache,
        private DotwAuditService $audit,
    ) {}

    public function __invoke($_, array $args, $context = null): array
    {
        $input = $args['input'] ?? [];
        $request = $context?->request();
        $resayilMessageId = $request?->attributes->get('resayil_message_id');

        // Resolve company_id from authenticated user
        $companyId = auth()->user()?->company?->id;

        // Check cache BEFORE calling remember() to detect hit
        $cacheKey = $this->cache->buildKey(
            $companyId,
            $input['destination'],
            $input['checkin'],
            $input['checkout'],
            $input['rooms'] ?? []
        );
        $wasCached = $this->cache->isCached($cacheKey);

        // Execute or return from cache
        $hotels = $this->cache->remember($cacheKey, function () use ($input, $companyId, $resayilMessageId) {
            $dotwService = new DotwService($companyId);
            return $dotwService->searchHotels($this->buildSearchParams($input), $resayilMessageId);
        });

        return [
            'success' => true,
            'data' => ['hotels' => $hotels],
            'cached' => $wasCached,
            'meta' => [
                'trace_id'   => app('dotw.trace_id'),
                'request_id' => app('dotw.trace_id'),
                'timestamp'  => now()->toIso8601String(),
                'company_id' => $companyId ?? 0,
            ],
        ];
    }
}
```

### Pattern 3: DotwCacheService Usage (isCached before remember)

```php
// Source: .planning/phases/03-cache-service-and-graphql-response-architecture/03-01-SUMMARY.md
// isCached() must be called BEFORE remember() to detect cache hits
// remember() does NOT inject 'cached' flag — caller responsibility
$wasCached = $this->cache->isCached($cacheKey);
$result = $this->cache->remember($cacheKey, fn() => $dotwService->searchHotels($params));
// Now annotate response with $wasCached
```

### Pattern 4: Filter Condition Array (Generic DOTW Vocabulary)

DOTW's filter system uses generic `fieldName` / `fieldTest` / `fieldValues` conditions. The `buildFilterXml()` in DotwService already passes these through verbatim. The GraphQL input type must expose this same generic structure to satisfy B2B-02 (full DOTW vocabulary):

```php
// Source: app/Services/DotwService.php buildFilterXml()
$searchParams['filters'] = [
    'city' => 'DXB',
    'conditions' => [
        ['fieldName' => 'rating', 'fieldTest' => 'equals', 'fieldValues' => [5]],
        ['fieldName' => 'price', 'fieldTest' => 'between', 'fieldValues' => [500.0, 3000.0]],
        ['fieldName' => 'propertytype', 'fieldTest' => 'equals', 'fieldValues' => ['hotel']],
        ['fieldName' => 'mealplantype', 'fieldTest' => 'equals', 'fieldValues' => ['BB']],
    ],
];
```

**Known DOTW fieldName values** (from DOTW V4 spec and existing SearchDotwHotels.php):
- `rating` — star rating (1-5)
- `price` — price range (between with min/max)
- `propertytype` — hotel classification type
- `mealplantype` — meal plan (BB, HB, FB, etc.)
- `amenities` — hotel amenity codes
- `cancellation` — cancellation policy type

### Pattern 5: Multi-Room Search (B2B-01)

DotwService's `buildRoomsXml()` already handles multiple rooms via `no=N` count and multiple `<room runno="N">` elements. The GraphQL input must accept:

```graphql
input SearchHotelRoomInput {
    "Number of adults in this room."
    adultsCode: Int!
    "Ages of children in this room (empty array if no children)."
    children: [Int!]!
    "Passenger nationality ISO code (e.g. 'AE', 'KW')."
    passengerNationality: String
    "Country of residence ISO code."
    passengerCountryOfResidence: String
}
```

The resolver maps `[SearchHotelRoomInput]` → the rooms array format DotwService expects.

### Pattern 6: Error Response Shape

Must match established DotwMeta/DotwError pattern from Phase 3:

```php
// Source: .planning/phases/03-cache-service-and-graphql-response-architecture/03-02-SUMMARY.md
return [
    'success' => false,
    'error' => [
        'error_code'    => 'CREDENTIALS_NOT_CONFIGURED',
        'error_message' => 'DOTW credentials not set up for this company.',
        'error_details' => null,
        'action'        => 'RECONFIGURE_CREDENTIALS',
    ],
    'meta' => [
        'trace_id'   => app('dotw.trace_id'),
        'request_id' => app('dotw.trace_id'),
        'timestamp'  => now()->toIso8601String(),
        'company_id' => 0,  // unknown if credentials fail
    ],
    'cached' => false,
    'data' => null,
];
```

### Pattern 7: Lighthouse Resolver Registration (schema-first)

Project uses Lighthouse schema-first with `@field(resolver: "...")` directive. The new query should be added directly to `graphql/dotw.graphql`:

```graphql
# In graphql/dotw.graphql
extend type Query {
    "Search for hotels matching destination, dates, and room configuration via DOTW API."
    searchHotels(input: SearchHotelsInput!): SearchHotelsResponse!
        @field(resolver: "App\\GraphQL\\Queries\\DotwSearchHotels")
}
```

Using `extend type Query` in dotw.graphql keeps the DOTW schema modular (MOD-04).

### Recommended File Structure for Phase 4

```
app/GraphQL/Queries/
├── SearchDotwHotels.php       # Legacy resolver — DO NOT MODIFY
└── DotwSearchHotels.php       # NEW: B2B resolver for searchHotels query

graphql/
├── schema.graphql             # Already has #import dotw.graphql
└── dotw.graphql               # Extend with searchHotels query + input/output types
```

### Anti-Patterns to Avoid

- **Modifying SearchDotwHotels.php:** Legacy resolver is explicitly noted as "updated in Phase 4" in Phase 1 research, but given it is pre-DOTW-v1.0 and serves a different legacy operation (`searchDotwHotels`), creating a parallel resolver is safer. Verify which approach the team prefers.
- **Adding searchHotels to schema.graphql root:** Breaks modular design (MOD-04). Always extend via dotw.graphql.
- **Calling isCached() after remember():** remember() doesn't expose cache hit status. Always call isCached() first.
- **Blocking rate in searchHotels:** The legacy SearchDotwHotels.php performs rate blocking during search — this is the old B2C pattern. The new B2B searchHotels should return results WITHOUT blocking (that happens in Phase 5's blockRates mutation). The search response gives cheapest rates per hotel; blocking happens when agent selects a specific rate.
- **Instantiating DotwService without company_id:** Must always pass $companyId from auth context for B2B path.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Cache key generation | Custom string concat | `DotwCacheService::buildKey()` | Built, tested, order-normalized |
| Cache read/write with TTL | Direct Cache::remember | `DotwCacheService::remember()` | DateInterval TTL, correct pattern |
| Cache hit detection | Custom Cache::has | `DotwCacheService::isCached()` | Must call before remember() |
| Audit logging | Direct DotwAuditLog::create() | `DotwAuditService::log()` | Handles sanitization, fail-silent |
| Credential resolution | Direct DB query | `new DotwService($companyId)` | Phase 1 constructor handles all |
| DOTW XML building | Custom XML | `DotwService::searchHotels()` | All XML complexity encapsulated |
| Filter XML | Custom XML | `DotwService::buildFilterXml()` internally | Called internally by searchHotels |
| Trace ID access | Global variable or request parse | `app('dotw.trace_id')` | Bound by DotwTraceMiddleware |
| Resayil ID access | Re-parse HTTP headers | `$request->attributes->get('resayil_message_id')` | Set by ResayilContextMiddleware |
| Company resolution | Raw User query | `auth()->user()->company->id` | Existing company relationship |

---

## Common Pitfalls

### Pitfall 1: Blocking Rates During Search (Wrong B2B Pattern)
**What goes wrong:** Legacy SearchDotwHotels.php performs the dual getRooms browse+block pattern for EVERY hotel in search results. This locks rates for every returned hotel — expensive and wasteful in B2B where agent just wants to browse.
**Why it happens:** Legacy pattern was designed for B2C auto-booking flow.
**How to avoid:** New B2B `searchHotels` resolver calls ONLY `DotwService::searchHotels()` (the searchhotels API command). No getRooms calls in search. Rate blocking is Phase 5's responsibility.
**Warning signs:** Look for getRooms() calls in the new resolver — they do not belong here.

### Pitfall 2: Hotel Name Missing from searchhotels Response
**What goes wrong:** DOTW's `searchhotels` command returns `hotelId` and room/rate data — but NOT hotel name, city name, star rating, image_url.
**Why it happens:** DOTW's searchhotels is lightweight — it returns only booking-relevant data. Hotel metadata comes from a separate catalog or is embedded in getRooms responses.
**How to avoid:** The `parseHotels()` method in DotwService confirms this — it extracts only `hotelId` and room type data from searchhotels. For Phase 4, options are:
  1. Return what searchhotels actually gives (hotelId + rates) and note that hotel metadata is retrieved in Phase 5's getRoomRates call.
  2. Make a secondary DOTW classification/catalog call per hotel (expensive).
  3. Cache hotel metadata from previous getRooms calls if available.
  The simplest B2B-appropriate approach: return hotelId + rate data from search; full hotel metadata comes in getRoomRates (Phase 5). Document this in the schema description.
**Warning signs:** SEARCH-06 requires `hotel_code, name, city, star rating, location, image_url`. Plan must explicitly address what is available vs. deferred to Phase 5.

### Pitfall 3: company_id Resolution for Unauthenticated Requests
**What goes wrong:** `auth()->user()` returns null if no auth middleware on the GraphQL route, causing company_id to be null, triggering DotwService legacy path instead of B2B path.
**Why it happens:** The GraphQL endpoint may not enforce auth.
**How to avoid:** Check `auth()->user()?->company?->id` with null-safe operator. If null, return `CREDENTIALS_NOT_CONFIGURED` error. Do not silently fall back to legacy env credentials.
**Warning signs:** DotwService constructor with null companyId falls back to env — always verify auth context first.

### Pitfall 4: Cache Key with Null company_id
**What goes wrong:** If company_id is null, cache key becomes `dotw_search__{destination}_...` — incorrectly namespaced, could serve wrong results cross-company.
**Why it happens:** Passing null company_id to buildKey().
**How to avoid:** Validate company_id is not null before building cache key. Fail fast with error response if auth context is missing.

### Pitfall 5: isCached() Race Condition with remember()
**What goes wrong:** isCached() returns false, then another process writes to cache before remember() executes, making the response say `cached: false` when it was actually served from cache.
**Why it happens:** Non-atomic check-then-use.
**How to avoid:** This is an acceptable minor inaccuracy given 150s TTL and low traffic. Document it. Do not over-engineer with distributed locks.

### Pitfall 6: DotwResponseEnvelope Type Conflict
**What goes wrong:** Phase 3 built `DotwResponseEnvelope` as a generic type — but each operation needs operation-specific data fields (hotels array vs. rates array vs. booking data). Using `DotwResponseEnvelope` directly as the return type would require a union/interface.
**Why it happens:** The envelope was designed as shared infrastructure, not as a concrete return type for operations.
**How to avoid:** Define operation-specific response types that embed the envelope fields. Example: `SearchHotelsResponse` has `success`, `error`, `meta`, `cached` (from envelope) PLUS `data: SearchHotelsData`. Do NOT return `DotwResponseEnvelope` directly — create `SearchHotelsResponse` that mirrors its structure with the data field typed correctly.

### Pitfall 7: Audit Log company_id from Wrong Source
**What goes wrong:** DotwService::searchHotels() already calls DotwAuditService::log() internally. If the resolver also calls DotwAuditService::log() separately, the search operation gets double-logged.
**Why it happens:** Resolver doesn't know DotwService already handles audit internally.
**How to avoid:** Let DotwService handle audit internally (it already does via the $resayilMessageId/$resayilQuoteId/$companyId parameters passed to searchHotels). Resolver should NOT call audit service directly for the search operation.

---

## Code Examples

### searchHotels Resolver — Integration Pattern

```php
// Source: app/Services/DotwService.php + app/Services/DotwCacheService.php patterns
public function __invoke($_, array $args, $context = null): array
{
    $input = $args['input'] ?? [];
    $request = $context?->request();
    $resayilMessageId = $request?->attributes->get('resayil_message_id');
    $resayilQuoteId   = $request?->attributes->get('resayil_quote_id');
    $companyId        = auth()->user()?->company?->id;

    // Guard: auth required for B2B path
    if ($companyId === null) {
        return $this->errorResponse('CREDENTIALS_NOT_CONFIGURED', 'No authenticated company context.', 'RECONFIGURE_CREDENTIALS');
    }

    // Build rooms array from GraphQL input
    $rooms = $this->buildRoomsFromInput($input['rooms'] ?? []);
    $destination = $input['destination'];  // city code or name
    $checkin  = $input['checkin'];
    $checkout = $input['checkout'];
    $currency = $input['currency'] ?? null;  // null = resolve from company

    // Cache key
    $cacheKey = $this->cache->buildKey($companyId, $destination, $checkin, $checkout, $rooms);
    $wasCached = $this->cache->isCached($cacheKey);

    try {
        $hotels = $this->cache->remember($cacheKey, function () use ($input, $rooms, $companyId, $resayilMessageId, $resayilQuoteId, $currency, $destination, $checkin, $checkout) {
            $dotwService = new DotwService($companyId);
            $searchParams = [
                'fromDate'  => $checkin,
                'toDate'    => $checkout,
                'currency'  => $currency ?? 'USD',  // fallback if not provided
                'rooms'     => $rooms,
                'filters'   => $this->buildFilters($destination, $input['filters'] ?? []),
            ];
            return $dotwService->searchHotels($searchParams, $resayilMessageId, $resayilQuoteId, $companyId);
        });
    } catch (\RuntimeException $e) {
        // Credential error from DotwService constructor
        return $this->errorResponse('CREDENTIALS_NOT_CONFIGURED', $e->getMessage(), 'RECONFIGURE_CREDENTIALS');
    } catch (\Exception $e) {
        return $this->errorResponse('API_ERROR', 'Hotel search failed.', 'RETRY', $e->getMessage());
    }

    return [
        'success' => true,
        'error'   => null,
        'cached'  => $wasCached,
        'data'    => ['hotels' => $this->formatHotels($hotels)],
        'meta'    => [
            'trace_id'   => app('dotw.trace_id'),
            'request_id' => app('dotw.trace_id'),
            'timestamp'  => now()->toIso8601String(),
            'company_id' => $companyId,
        ],
    ];
}
```

### GraphQL Schema Extension Pattern

```graphql
# Source: graphql/dotw.graphql — extend with operation types
extend type Query {
    "Search for hotels by destination, dates, and room configuration via DOTW V4 API."
    searchHotels(input: SearchHotelsInput!): SearchHotelsResponse!
        @field(resolver: "App\\GraphQL\\Queries\\DotwSearchHotels")
}

"Input for the searchHotels query."
input SearchHotelsInput {
    "City code (e.g. DXB for Dubai) or city name."
    destination: String!
    "Check-in date (YYYY-MM-DD)."
    checkin: String!
    "Check-out date (YYYY-MM-DD)."
    checkout: String!
    "Room configuration — one entry per room requested."
    rooms: [SearchHotelRoomInput!]!
    "Response currency code (e.g. KWD, USD). Defaults to USD if not provided."
    currency: String
    "Optional filters for rating, price, property type, meal plan, etc."
    filters: SearchHotelsFiltersInput
}

"Occupancy for a single room."
input SearchHotelRoomInput {
    "Number of adults in this room."
    adultsCode: Int!
    "Ages of children in this room. Empty array if no children."
    children: [Int!]!
    "ISO 3166-1 alpha-2 nationality code (e.g. AE, KW)."
    passengerNationality: String
    "ISO 3166-1 alpha-2 country of residence code."
    passengerCountryOfResidence: String
}

"Filter input for searchHotels. All fields are optional."
input SearchHotelsFiltersInput {
    "Minimum star rating (1-5)."
    minRating: Int
    "Maximum star rating (1-5)."
    maxRating: Int
    "Minimum total price."
    minPrice: Float
    "Maximum total price."
    maxPrice: Float
    "Property type filter (e.g. hotel, apartment)."
    propertyType: String
    "Meal plan type filter (e.g. BB, HB, FB, AI, RO)."
    mealPlanType: String
    "Amenity codes to filter by."
    amenities: [String!]
    "Cancellation policy type (e.g. refundable, non-refundable)."
    cancellationPolicy: String
}

"Response from the searchHotels query."
type SearchHotelsResponse {
    "Whether the search succeeded."
    success: Boolean!
    "Structured error — present only when success is false."
    error: DotwError
    "Per-request tracing metadata — always present."
    meta: DotwMeta!
    "True if results were served from the 2.5-minute search cache."
    cached: Boolean!
    "Search result data — present only when success is true."
    data: SearchHotelsData
}

"Container for hotel search results."
type SearchHotelsData {
    "Hotels matching the search criteria."
    hotels: [HotelSearchResult!]!
    "Total number of hotels returned."
    total_count: Int!
}

"A single hotel result from searchHotels."
type HotelSearchResult {
    "DOTW hotel ID (use this as hotel_code in getRoomRates)."
    hotel_code: String!
    "Room types available for this hotel with cheapest rate per meal plan."
    rooms: [HotelRoomResult!]!
}

"Room types for a hotel — cheapest rate per meal plan from searchhotels response."
type HotelRoomResult {
    "Number of adults this room configuration covers."
    adults: String!
    "Number of children."
    children: String!
    "Child ages if any."
    children_ages: String!
    "Available room types with cheapest rates."
    room_types: [RoomTypeRate!]!
}

"A room type with its cheapest rate for a specific meal plan."
type RoomTypeRate {
    "DOTW room type code."
    code: String!
    "Room type name."
    name: String!
    "Rate basis ID (1331=RO, 1332=BB, 1333=HB, 1334=FB, 1335=AI, 1336=SC)."
    rate_basis_id: String!
    "Whether this rate is non-refundable."
    non_refundable: Boolean!
    "Total fare before markup."
    total: Float!
    "Total taxes."
    total_taxes: Float!
    "Minimum selling price (MSP) — never undercut this."
    total_minimum_selling: Float!
    "Currency of the fare."
    currency_id: String!
}
```

### Filter Mapping — GraphQL Input to DOTW Condition Array

```php
// Source: app/Services/DotwService.php buildFilterXml() pattern
private function buildFilters(string $destination, array $filterInput): array
{
    $filters = ['city' => $destination];
    $conditions = [];

    if (isset($filterInput['minRating']) || isset($filterInput['maxRating'])) {
        // Use 'equals' for single value, 'between' for range
        $rating = $filterInput['minRating'] ?? $filterInput['maxRating'];
        $conditions[] = [
            'fieldName'   => 'rating',
            'fieldTest'   => 'equals',
            'fieldValues' => [(int) $rating],
        ];
    }

    if (isset($filterInput['minPrice']) && isset($filterInput['maxPrice'])) {
        $conditions[] = [
            'fieldName'   => 'price',
            'fieldTest'   => 'between',
            'fieldValues' => [(float) $filterInput['minPrice'], (float) $filterInput['maxPrice']],
        ];
    }

    if (!empty($filterInput['propertyType'])) {
        $conditions[] = [
            'fieldName'   => 'propertytype',
            'fieldTest'   => 'equals',
            'fieldValues' => [$filterInput['propertyType']],
        ];
    }

    // ... same pattern for mealPlanType, amenities, cancellationPolicy

    if (!empty($conditions)) {
        $filters['conditions'] = $conditions;
    }

    return $filters;
}
```

---

## State of the Art

| Old Approach | Current Approach | Why Changed |
|--------------|-----------------|-------------|
| SearchDotwHotels.php: blocks rates during search | New resolver: search only, no blocking in search | B2B agents browse first, block only on selection |
| SearchDotwHotels.php: uses env credentials | DotwSearchHotels.php: uses per-company DB credentials | Multi-tenant B2B requires isolation |
| Single room search only | Multi-room via `[SearchHotelRoomInput!]` | B2B-01 — agents need complex room configs |
| No caching | DotwCacheService 2.5-min TTL | Reduces API load during WhatsApp conversation flow |
| No audit trail | DotwAuditService called via DotwService internally | MSG-04 — all ops linked to WhatsApp message |
| Legacy response shape | DotwMeta + DotwError envelope | GQLR-01..08 — consistent shape for N8N |

---

## Open Questions

### 1. Hotel Name, City, Star Rating, Image URL from searchhotels
**What we know:** DOTW's `searchhotels` command (via `parseHotels()`) only returns `hotelId` + room/rate data. No hotel name, no city name, no star rating, no image_url fields in the response.
**What's unclear:** SEARCH-06 requires all of these. The DOTW V4 spec may return these in hotel attributes not yet parsed, or they require a separate `gethotelinfo` call. The existing `parseHotels()` uses `//hotel` xpath and extracts only `hotelid` attribute and child `//room` elements.
**Recommendation:** Planner must decide between three options:
  - **Option A (recommended):** Return hotelId and rates from search; hotel metadata (name, city, rating, image) is retrieved by Phase 5's `getRoomRates` operation — document this in schema descriptions.
  - **Option B:** Inspect if DOTW's searchhotels response XML actually contains hotel metadata in other attributes not currently parsed (would require checking raw DOTW response).
  - **Option C:** Make secondary catalog calls per hotel during search (expensive, adds latency per hotel).
**Impact:** This affects the `SearchHotelsResponse` schema design. The planner should resolve this before writing plans to avoid re-work.

### 2. Company Currency Default
**What we know:** SEARCH-03 says currency defaults to "company currency if not specified." `company_dotw_credentials` table does not have a currency column.
**What's unclear:** Where the company's default currency is stored. It may be on the `companies` table or there may be no per-company default.
**Recommendation:** Planner should check `companies` table schema. Simplest fallback: default to USD if not specified, matching existing SearchDotwHotels.php behavior.

### 3. Destination — City Code vs. City Name Resolution
**What we know:** DOTW's `searchhotels` filter uses `<city>CITYCODE</city>` (e.g., "DXB"). The legacy resolver passes the city value directly. If the user provides "Dubai" instead of "DXB," the search may return nothing.
**What's unclear:** Whether the DOTW API accepts full city names or requires three-letter city codes.
**Recommendation:** Build a city name → city code lookup using `DotwService::getCityList()` or a cached lookup table. Alternatively, require city codes in the input and document this constraint in the schema description. The simpler path for Phase 4 is to accept city codes only and leave name resolution for a future enhancement.

---

## Sources

### Primary (HIGH confidence — all from codebase)
- `app/Services/DotwService.php` — searchHotels(), parseHotels(), buildSearchHotelsBody(), buildFilterXml(), buildRoomsXml() patterns
- `app/Services/DotwCacheService.php` — buildKey(), remember(), isCached() patterns and constraints
- `app/Services/DotwAuditService.php` — OP_SEARCH constant, log() signature (already called internally by DotwService)
- `graphql/dotw.graphql` — DotwMeta, DotwError, DotwErrorCode, DotwErrorAction types established in Phase 3
- `.planning/phases/03-cache-service-and-graphql-response-architecture/03-02-SUMMARY.md` — resolver DotwMeta pattern, trace_id access
- `.planning/phases/03-cache-service-and-graphql-response-architecture/03-01-SUMMARY.md` — isCached() before remember() pattern
- `app/GraphQL/Queries/SearchDotwHotels.php` — legacy resolver pattern (NOT to be modified)
- `.planning/phases/01-credential-management-and-markup-foundation/RESEARCH.md` — DotwService constructor B2B/legacy path documentation

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components exist and verified in codebase
- Architecture patterns: HIGH — established from Phase 3 summaries, existing resolvers
- Pitfalls: HIGH — identified from DotwService/parseHotels analysis and existing legacy code anti-patterns
- Open questions: MEDIUM — hotel metadata gap identified, confirmed by parseHotels() code review; resolution requires design decision

**Research date:** 2026-02-21
**Valid until:** Until DotwService.php or dotw.graphql are modified (stable)
