# DOTW GraphQL API Reference

**Last Updated:** February 2026
**Version:** 1.0 (B2B)
**Status:** Production

## Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Tracing & Correlation](#tracing--correlation)
- [Error Handling](#error-handling)
- [Rate Limiting & Circuit Breaker](#rate-limiting--circuit-breaker)
- [Query Reference](#query-reference)
  - [getCities](#getcities)
  - [searchHotels](#searchhotels)
  - [getRoomRates](#getroomrates)
- [Mutation Reference](#mutation-reference)
  - [blockRates](#blockrates)
  - [createPreBooking](#createprebooking)
- [Data Types](#data-types)
- [Common Patterns](#common-patterns)
- [Troubleshooting](#troubleshooting)

---

## Overview

The DOTW GraphQL API provides a B2B hotel search, rate browsing, and booking workflow for travel agencies via the DOTW (Dreams on the Way) supplier. The API is designed for integration with N8N automation workflows and Resayil WhatsApp business agents.

### Key Capabilities

- **City Discovery**: Fetch available destinations in DOTW per country
- **Hotel Search**: Query available hotels with rates for specified dates and room configurations
- **Rate Details**: Retrieve full rate information including meal plans, cancellation policies, and opaque allocation tokens
- **Rate Blocking**: Lock a selected rate for 3 minutes to hold pricing during booking conversation
- **Booking Confirmation**: Confirm a locked rate into an actual DOTW booking with passenger details

### Architecture

```
getCities (lookup cities per country)
    ↓
searchHotels (find available hotels with rates)
    ↓
getRoomRates (fetch detailed rates, meal plans, cancellation policies)
    ↓
blockRates (lock rate for 3 minutes)
    ↓
createPreBooking (confirm booking with passenger data)
```

### Workflow Typical Sequence

1. **Agent searches for destination** → `getCities` (cached) → display city list
2. **User selects city, dates, rooms** → `searchHotels` (2.5-minute cache per company) → display hotel results
3. **User selects a hotel** → `getRoomRates` (not cached, fresh) → display room types and meal plans
4. **User picks a rate** → `blockRates` → lock rate for 3 minutes, display countdown timer
5. **User provides passenger details** → `createPreBooking` → confirm booking with DOTW

---

## Authentication

All DOTW GraphQL endpoints require **Sanctum Bearer token** authentication with per-company credentials configured.

### Authentication Flow

1. **Bearer Token**: Include in every GraphQL request header:
   ```
   Authorization: Bearer {personal_access_token}
   ```

2. **Token Source**: Sanctum personal access tokens generated per company via the DOTW settings:
   - Navigate to `/settings` → "DOTW / Hotel API" tab → "API Tokens" section
   - Generate a new token (admin-only)
   - Token is bound to the authenticated company context

3. **Company Context**: The token resolves the authenticated user's company via `auth()->user()?->company?->id`.
   - All operations are scoped to that company
   - Credentials are fetched from `dotw_company_credentials` table per company
   - Fails with `CREDENTIALS_NOT_CONFIGURED` if no credentials exist

### Required Credentials per Company

Agents or admins must configure DOTW credentials in `/settings` → "DOTW / Hotel API" → "Credentials":

- `dotw_username` — DOTW account username
- `dotw_password` — DOTW account password (encrypted at rest)
- `dotw_company_code` — DOTW supplier company code
- `markup_percent` — Markup percentage applied to all rates (0–100)

### Request Headers

```http
POST /graphql
Host: development.citycommerce.group
Content-Type: application/json
Authorization: Bearer {personal_access_token}
X-Trace-ID: {optional, for caller's trace correlation}
```

---

## Tracing & Correlation

Every DOTW GraphQL response includes tracing metadata (`DotwMeta`) and HTTP headers for request correlation.

### Response Metadata (DotwMeta)

Every query/mutation response includes:

```graphql
meta: {
  trace_id: String!        # UUID v4 unique to this request
  request_id: String!      # Same as trace_id (backwards compat)
  timestamp: String!       # ISO 8601 UTC timestamp
  company_id: Int!         # Authenticated company ID
}
```

### Response Headers

```http
X-Trace-ID: {trace_id}            # Echo of DotwMeta.trace_id
X-Request-Time-Ms: {milliseconds} # Total request processing time
```

### Log Correlation

Use the `trace_id` to correlate logs across systems:

1. **GraphQL request**: trace_id in response metadata
2. **Audit logs** (`dotw_audit_logs` table): `trace_id` column for this operation
3. **Server logs** (`storage/logs/dotw.log`): search for `[trace_id]` prefix
4. **N8N workflow logs**: store trace_id in webhook execution context

Example:
```bash
# Tail server logs for this trace
tail -f storage/logs/dotw.log | grep "a1b2c3d4-e5f6-4789-abcd-ef0123456789"
```

---

## Error Handling

### Error Response Structure

When `success: false`, every response includes a structured `DotwError` object:

```graphql
error: {
  error_code: DotwErrorCode!      # Machine-readable code for N8N branching
  error_message: String!          # User-friendly message (WhatsApp-safe)
  error_details: String           # Technical details (never shown to end users)
  action: DotwErrorAction!        # Suggested next action
}
```

### Error Codes & Actions

| Error Code | Meaning | Suggested Action | Retry Safe |
|---|---|---|---|
| `CREDENTIALS_NOT_CONFIGURED` | Company DOTW credentials missing or unconfigured | `RECONFIGURE_CREDENTIALS` | No |
| `CREDENTIALS_INVALID` | Wrong username, password, or company code | `RECONFIGURE_CREDENTIALS` | No |
| `VALIDATION_ERROR` | Invalid input arguments (e.g., bad date format, missing field) | `RETRY` | Yes |
| `API_TIMEOUT` | DOTW API did not respond within 25 seconds | `RETRY` | Yes |
| `API_ERROR` | DOTW API returned an unexpected error | `RETRY` | Yes |
| `CIRCUIT_BREAKER_OPEN` | Too many recent DOTW failures; circuit breaker activated | `RETRY_IN_30_SECONDS` | Yes |
| `ALLOCATION_EXPIRED` | Rate lock expired (> 3 minutes) or prebook timed out | `RESEARCH` | No |
| `RATE_UNAVAILABLE` | Selected rate no longer available from supplier | `RESEARCH` | No |
| `HOTEL_SOLD_OUT` | Hotel fully booked for requested dates | `RESEARCH` | No |
| `PASSENGER_VALIDATION_FAILED` | Missing or invalid passenger field(s) | `RETRY` | Yes |
| `INTERNAL_ERROR` | Unexpected server error | `RETRY` | Yes |

### Error Actions

| Action | Meaning | Client Behavior |
|---|---|---|
| `RETRY` | Transient error; retry immediately | Retry the same request |
| `RETRY_IN_30_SECONDS` | Rate limit or temporary overload | Wait 30 seconds, then retry |
| `RESEARCH` | Rate/allocation no longer valid or expired | Run new `searchHotels` from start |
| `RECONFIGURE_CREDENTIALS` | Admin action needed | Contact admin to update `/settings` credentials |
| `CANCEL` | Booking failed; manual intervention needed | Log error, notify user to contact support |
| `NONE` | Informational; no action required | Acknowledge and continue |

### Error Response Example

```json
{
  "data": {
    "searchHotels": {
      "success": false,
      "error": {
        "error_code": "CIRCUIT_BREAKER_OPEN",
        "error_message": "Try again in 30 seconds",
        "error_details": "5 failures in the last 60 seconds",
        "action": "RETRY_IN_30_SECONDS"
      },
      "meta": {
        "trace_id": "a1b2c3d4-e5f6-4789-abcd-ef0123456789",
        "timestamp": "2026-02-21T14:30:00Z",
        "company_id": 5
      },
      "cached": false
    }
  }
}
```

---

## Rate Limiting & Circuit Breaker

### Circuit Breaker (searchHotels only)

The `searchHotels` operation is protected by a **circuit breaker** to prevent cascading DOTW API failures.

**Thresholds:**
- Failure count: 5 failures within 60 seconds
- Open window: 30 seconds (no DOTW API calls allowed)
- Success reset: Any successful API call resets the counter to 0

**Behavior:**
- When **open** and cache hit available → serve cached result
- When **open** and no cache → return `CIRCUIT_BREAKER_OPEN` error with action `RETRY_IN_30_SECONDS`
- When **closed** → allow normal DOTW API call

**Failure Types:**
- `API_TIMEOUT` — counts toward circuit breaker
- `API_ERROR` — counts toward circuit breaker
- `CREDENTIALS_NOT_CONFIGURED` — does NOT count (configuration error, not transient)

### Caching Strategy

| Operation | Cache Duration | Per-Company Isolation | Key Components |
|---|---|---|---|
| `getCities` | Not cached | No | Implicit (fresh each time) |
| `searchHotels` | 2.5 minutes | **Yes** | `company_id`, `destination`, `checkin`, `checkout`, `rooms` hash |
| `getRoomRates` | Not cached | N/A | Rates are volatile; tokens expire minute-to-minute |
| `blockRates` | N/A | N/A | Mutation; no caching |
| `createPreBooking` | N/A | N/A | Mutation; no caching |

**Cache Key Example (searchHotels):**
```
dotw_search_5_DXB_2026-02-28_2026-03-02_{rooms_hash}
```

Where:
- `5` = company_id
- `DXB` = destination
- `2026-02-28` = checkin date
- `2026-03-02` = checkout date
- `{rooms_hash}` = MD5 hash of room configuration (adults, children, nationality)

### Rate Limiting (API-Level)

DOTW API imposes rate limits on the supplier side. If exceeded:
- Error code: `API_ERROR`
- Action: `RETRY`
- Delay: Use exponential backoff in N8N workflow

---

## Query Reference

### getCities

Retrieve the list of cities served by DOTW in a given country.

**Use Case:** Populate a city dropdown or autocomplete for the user.

#### Request

```graphql
query GetCities {
  getCities(country_code: "AE") {
    success
    error {
      error_code
      error_message
      action
    }
    meta {
      trace_id
      timestamp
      company_id
    }
    data {
      cities {
        code
        name
      }
      total_count
    }
  }
}
```

#### Input Arguments

| Argument | Type | Required | Description | Example |
|---|---|---|---|---|
| `country_code` | String | Yes | ISO 3166-1 alpha-2 country code (uppercase, 2 characters) | `"AE"`, `"KW"`, `"GB"` |

#### Response

```graphql
type GetCitiesResponse {
  success: Boolean!
  error: DotwError
  meta: DotwMeta!
  data: GetCitiesData
}

type GetCitiesData {
  cities: [DotwCity!]!
  total_count: Int!
}

type DotwCity {
  code: String!        # DOTW city code (pass to searchHotels)
  name: String!        # Human-readable city name
}
```

#### Example Response

```json
{
  "data": {
    "getCities": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "a1b2c3d4-e5f6-4789-abcd-ef0123456789",
        "request_id": "a1b2c3d4-e5f6-4789-abcd-ef0123456789",
        "timestamp": "2026-02-21T14:30:00Z",
        "company_id": 5
      },
      "data": {
        "cities": [
          {
            "code": "DXB",
            "name": "Dubai"
          },
          {
            "code": "AUH",
            "name": "Abu Dhabi"
          },
          {
            "code": "SHJ",
            "name": "Sharjah"
          }
        ],
        "total_count": 3
      }
    }
  }
}
```

#### Error Cases

| Scenario | Error Code | Action |
|---|---|---|
| Invalid country code (not 2 chars) | `VALIDATION_ERROR` | `RETRY` |
| Company not authenticated | `CREDENTIALS_NOT_CONFIGURED` | `RECONFIGURE_CREDENTIALS` |
| DOTW API timeout | `API_TIMEOUT` | `RETRY` |
| DOTW API error | `API_ERROR` | `RETRY` |

#### Notes

- **Caching:** Not cached; returns fresh data each call.
- **Company isolation:** Returns cities available to authenticated company.
- **Country validation:** Must be uppercase, exactly 2 characters.

---

### searchHotels

Search for available hotels in a destination with rates matching room configuration and optional filters.

**Use Case:** Find hotels for a given destination, dates, and number of rooms/occupancy.

#### Request

```graphql
query SearchHotels {
  searchHotels(input: {
    destination: "DXB"
    checkin: "2026-02-28"
    checkout: "2026-03-02"
    currency: "KWD"
    rooms: [
      {
        adultsCode: 2
        children: [5]
        passengerNationality: "KW"
        passengerCountryOfResidence: "KW"
      }
    ]
    filters: {
      minRating: 4
      minPrice: 100
      maxPrice: 500
      propertyType: "hotel"
      mealPlanType: "BB"
      amenities: ["pool", "wifi"]
      cancellationPolicy: "refundable"
    }
  }) {
    success
    error {
      error_code
      error_message
      action
    }
    meta {
      trace_id
      timestamp
      company_id
    }
    cached
    data {
      hotels {
        hotel_code
        rooms {
          adults
          children
          children_ages
          room_types {
            code
            name
            rate_basis_id
            currency_id
            non_refundable
            total
            markup {
              original_fare
              markup_percent
              markup_amount
              final_fare
            }
            total_taxes
            total_minimum_selling
          }
        }
      }
      total_count
    }
  }
}
```

#### Input Arguments

| Argument | Type | Required | Description |
|---|---|---|---|
| `input` | SearchHotelsInput | Yes | See below |

**SearchHotelsInput:**

| Field | Type | Required | Description | Example |
|---|---|---|---|---|
| `destination` | String | Yes | City code from `getCities` response | `"DXB"` |
| `checkin` | String | Yes | Check-in date (YYYY-MM-DD) | `"2026-02-28"` |
| `checkout` | String | Yes | Check-out date (YYYY-MM-DD) | `"2026-03-02"` |
| `rooms` | [SearchHotelRoomInput!]! | Yes | Array of room configurations (one per room) | See below |
| `currency` | String | No | Response currency code (defaults to DOTW account default) | `"KWD"`, `"USD"` |
| `filters` | SearchHotelsFiltersInput | No | Optional filters (all fields optional) | See below |

**SearchHotelRoomInput:**

| Field | Type | Required | Description | Example |
|---|---|---|---|---|
| `adultsCode` | Int | Yes | Number of adults in this room | `2` |
| `children` | [Int!]! | Yes | Ages of children (empty array if none) | `[5, 8]` or `[]` |
| `passengerNationality` | String | No | ISO alpha-2 nationality code | `"KW"` |
| `passengerCountryOfResidence` | String | No | ISO alpha-2 residence country code | `"KW"` |

**SearchHotelsFiltersInput (all optional):**

| Field | Type | Description | Example |
|---|---|---|---|
| `minRating` | Int | Minimum star rating (1–5) | `4` |
| `maxRating` | Int | Maximum star rating (1–5) | `5` |
| `minPrice` | Float | Minimum total price | `100.0` |
| `maxPrice` | Float | Maximum total price | `500.0` |
| `propertyType` | String | Property type filter | `"hotel"`, `"apartment"`, `"resort"` |
| `mealPlanType` | String | Meal plan type | `"BB"` (Bed & Breakfast), `"HB"` (Half Board), `"FB"` (Full Board), `"AI"` (All Inclusive), `"RO"` (Room Only), `"SC"` (Self Catering) |
| `amenities` | [String!] | Amenity codes to require | `["pool", "wifi", "spa"]` |
| `cancellationPolicy` | String | Cancellation policy | `"refundable"`, `"non-refundable"` |

#### Response

```graphql
type SearchHotelsResponse {
  success: Boolean!
  error: DotwError
  meta: DotwMeta!
  cached: Boolean!
  data: SearchHotelsData
}

type SearchHotelsData {
  hotels: [HotelSearchResult!]!
  total_count: Int!
}

type HotelSearchResult {
  hotel_code: String!      # Pass to getRoomRates and blockRates
  rooms: [HotelRoomResult!]!
}

type HotelRoomResult {
  adults: String!
  children: String!
  children_ages: String!   # Comma-separated child ages
  room_types: [RoomTypeRate!]!
}

type RoomTypeRate {
  code: String!            # Room type code
  name: String!            # Room type name
  rate_basis_id: String!   # Meal plan ID (1332=BB, 1333=HB, etc.)
  currency_id: String!     # Currency code
  non_refundable: Boolean!
  total: Float!            # Pre-markup total
  markup: RateMarkup!      # Markup breakdown
  total_taxes: Float!
  total_minimum_selling: Float!
}

type RateMarkup {
  original_fare: Float!    # Fare before markup
  markup_percent: Float!   # Markup percentage
  markup_amount: Float!    # Markup amount in absolute currency
  final_fare: Float!       # Customer-facing price (original + markup)
}
```

#### Example Response (Cached)

```json
{
  "data": {
    "searchHotels": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "b2c3d4e5-f6a7-4789-abcd-ef0123456789",
        "request_id": "b2c3d4e5-f6a7-4789-abcd-ef0123456789",
        "timestamp": "2026-02-21T14:31:00Z",
        "company_id": 5
      },
      "cached": true,
      "data": {
        "hotels": [
          {
            "hotel_code": "HOTEL-001",
            "rooms": [
              {
                "adults": "2",
                "children": "1",
                "children_ages": "5",
                "room_types": [
                  {
                    "code": "DBL",
                    "name": "Double Room",
                    "rate_basis_id": "1332",
                    "currency_id": "KWD",
                    "non_refundable": false,
                    "total": 250.0,
                    "markup": {
                      "original_fare": 250.0,
                      "markup_percent": 15.0,
                      "markup_amount": 37.5,
                      "final_fare": 287.5
                    },
                    "total_taxes": 10.0,
                    "total_minimum_selling": 275.0
                  }
                ]
              }
            ]
          }
        ],
        "total_count": 1
      }
    }
  }
}
```

#### Error Cases

| Scenario | Error Code | Action | Circuit Breaker |
|---|---|---|---|
| Invalid dates (past, bad format) | `VALIDATION_ERROR` | `RETRY` | No |
| Missing destination | `VALIDATION_ERROR` | `RETRY` | No |
| Company not authenticated | `CREDENTIALS_NOT_CONFIGURED` | `RECONFIGURE_CREDENTIALS` | No |
| Invalid credentials | `CREDENTIALS_INVALID` | `RECONFIGURE_CREDENTIALS` | No |
| DOTW API timeout | `API_TIMEOUT` | `RETRY` | Yes, counts |
| DOTW API error | `API_ERROR` | `RETRY` | Yes, counts |
| Circuit breaker open (no cache) | `CIRCUIT_BREAKER_OPEN` | `RETRY_IN_30_SECONDS` | N/A |

#### Notes

- **Caching:** Results cached for 2.5 minutes per company, using cache key derived from destination, dates, and room configuration hash.
- **Cached flag:** `cached: true` if result served from cache; `false` if fresh DOTW API call.
- **Circuit breaker:** Applies only to this operation; failures count toward the 5-in-60-seconds threshold.
- **Hotel metadata:** DOTW `searchhotels` does not return hotel name, city, or image URL. Use `getRoomRates` for full details (deferred in Phase 5).
- **Markup:** Included in every rate for transparent pricing display to end users.
- **Multi-room support:** Pass multiple room objects for multi-room bookings.

---

### getRoomRates

Retrieve all room types and meal plans for a specific hotel with full details including cancellation policies and allocation tokens.

**Use Case:** Display detailed rate options (multiple meal plans, cancellation policies) for a selected hotel before blocking a rate.

#### Request

```graphql
query GetRoomRates {
  getRoomRates(input: {
    hotel_code: "HOTEL-001"
    checkin: "2026-02-28"
    checkout: "2026-03-02"
    currency: "KWD"
    rooms: [
      {
        adultsCode: 2
        children: [5]
        passengerNationality: "KW"
        passengerCountryOfResidence: "KW"
      }
    ]
  }) {
    success
    error {
      error_code
      error_message
      action
    }
    meta {
      trace_id
      timestamp
      company_id
    }
    cached
    data {
      hotel_code
      rooms {
        room_type_code
        room_name
        rate_details {
          rate_basis_id
          rate_basis_name
          is_refundable
          total_fare
          total_taxes
          total_price
          markup {
            original_fare
            markup_percent
            markup_amount
            final_fare
          }
          allocation_details
          cancellation_rules {
            from_date
            to_date
            charge
            cancel_charge
          }
          original_currency
          exchange_rate
          final_currency
        }
      }
      total_count
    }
  }
}
```

#### Input Arguments

**GetRoomRatesInput:**

| Field | Type | Required | Description | Example |
|---|---|---|---|---|
| `hotel_code` | String | Yes | Hotel code from `searchHotels` response | `"HOTEL-001"` |
| `checkin` | String | Yes | Check-in date (YYYY-MM-DD, must match searchHotels) | `"2026-02-28"` |
| `checkout` | String | Yes | Check-out date (YYYY-MM-DD, must match searchHotels) | `"2026-03-02"` |
| `rooms` | [SearchHotelRoomInput!]! | Yes | Room configuration (must match searchHotels) | See searchHotels |
| `currency` | String | No | Response currency code | `"KWD"`, `"USD"` |

#### Response

```graphql
type GetRoomRatesResponse {
  success: Boolean!
  error: DotwError
  meta: DotwMeta!
  cached: Boolean!         # Always false (never cached)
  data: GetRoomRatesData
}

type GetRoomRatesData {
  hotel_code: String!
  rooms: [RoomRateResult!]!
  total_count: Int!
}

type RoomRateResult {
  room_type_code: String!
  room_name: String!
  rate_details: [RateDetail!]!
}

type RateDetail {
  rate_basis_id: String!           # 1331=RO, 1332=BB, 1333=HB, 1334=FB, 1335=AI, 1336=SC
  rate_basis_name: String!         # "Bed & Breakfast", etc.
  is_refundable: Boolean!
  total_fare: Float!               # Pre-markup total
  total_taxes: Float!
  total_price: Float!              # total_fare + total_taxes
  markup: RateMarkup!
  allocation_details: String!      # Opaque token for blockRates (CRITICAL: never modify)
  cancellation_rules: [CancellationRule!]!
  original_currency: String!       # Currency from DOTW
  exchange_rate: Float             # null if no conversion; otherwise exchange rate applied
  final_currency: String!          # Same as original_currency (DOTW handles conversion server-side)
}

type CancellationRule {
  from_date: String!               # ISO 8601 start of penalty window
  to_date: String!                 # ISO 8601 end of penalty window
  charge: Float!
  cancel_charge: Float!
}
```

#### Example Response

```json
{
  "data": {
    "getRoomRates": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "c3d4e5f6-a7b8-4789-abcd-ef0123456789",
        "request_id": "c3d4e5f6-a7b8-4789-abcd-ef0123456789",
        "timestamp": "2026-02-21T14:32:00Z",
        "company_id": 5
      },
      "cached": false,
      "data": {
        "hotel_code": "HOTEL-001",
        "rooms": [
          {
            "room_type_code": "DBL",
            "room_name": "Double Room",
            "rate_details": [
              {
                "rate_basis_id": "1332",
                "rate_basis_name": "Bed & Breakfast",
                "is_refundable": true,
                "total_fare": 250.0,
                "total_taxes": 10.0,
                "total_price": 260.0,
                "markup": {
                  "original_fare": 250.0,
                  "markup_percent": 15.0,
                  "markup_amount": 37.5,
                  "final_fare": 287.5
                },
                "allocation_details": "SGVsbG8gV29ybGQhIFRoaXMgaXMgYW4gb3BhcXVlIHRva2VuLg==",
                "cancellation_rules": [
                  {
                    "from_date": "2026-02-25T00:00:00Z",
                    "to_date": "2026-02-27T23:59:59Z",
                    "charge": 0.0,
                    "cancel_charge": 0.0
                  },
                  {
                    "from_date": "2026-02-28T00:00:00Z",
                    "to_date": "2026-03-02T23:59:59Z",
                    "charge": 287.5,
                    "cancel_charge": 287.5
                  }
                ],
                "original_currency": "KWD",
                "exchange_rate": null,
                "final_currency": "KWD"
              }
            ]
          }
        ],
        "total_count": 1
      }
    }
  }
}
```

#### Error Cases

| Scenario | Error Code | Action |
|---|---|---|
| Invalid hotel_code | `API_ERROR` | `RETRY` |
| Room config doesn't match | `VALIDATION_ERROR` | `RETRY` |
| Company not authenticated | `CREDENTIALS_NOT_CONFIGURED` | `RECONFIGURE_CREDENTIALS` |
| DOTW API timeout | `API_TIMEOUT` | `RETRY` |
| DOTW API error | `API_ERROR` | `RETRY` |

#### Critical Notes

- **Never cache:** Always fresh from DOTW (rates change minute-to-minute, tokens expire).
- **allocation_details is opaque:** Pass verbatim to `blockRates`; any modification or encoding will cause DOTW to reject the block call.
- **Room config must match:** Use the exact same room configuration from `searchHotels` to ensure rate consistency.
- **Multiple meal plans:** A single room type can have multiple rate_details (one per meal plan).
- **Currency handling (RATE-05):** DOTW may include per-rate currency and exchange rate. Fallback to request currency or empty string.

---

## Mutation Reference

### blockRates

Lock a selected hotel rate for 3 minutes. Returns a `prebook_key` UUID and countdown timer for `createPreBooking`.

**Use Case:** Hold a rate while the user provides passenger details (typically 3 minutes in a WhatsApp conversation).

#### Request

```graphql
mutation BlockRates {
  blockRates(input: {
    hotel_code: "HOTEL-001"
    hotel_name: "Burj Al Arab"
    checkin: "2026-02-28"
    checkout: "2026-03-02"
    rooms: [
      {
        adultsCode: 2
        children: [5]
        passengerNationality: "KW"
        passengerCountryOfResidence: "KW"
      }
    ]
    selected_room_type: "DBL"
    selected_rate_basis: "1332"
    allocation_details: "SGVsbG8gV29ybGQhIFRoaXMgaXMgYW4gb3BhcXVlIHRva2VuLg=="
    currency: "KWD"
  }) {
    success
    error {
      error_code
      error_message
      action
    }
    meta {
      trace_id
      timestamp
      company_id
    }
    cached
    data {
      prebook_key
      expires_at
      countdown_timer_seconds
      hotel_code
      hotel_name
      room_type
      rate_basis
      total_fare
      total_tax
      markup {
        original_fare
        markup_percent
        markup_amount
        final_fare
      }
      is_refundable
      cancellation_rules {
        from_date
        to_date
        charge
        cancel_charge
      }
    }
  }
}
```

#### Input Arguments

**BlockRatesInput:**

| Field | Type | Required | Description | Example |
|---|---|---|---|---|
| `hotel_code` | String | Yes | Hotel code from `searchHotels` | `"HOTEL-001"` |
| `hotel_name` | String | No | Hotel name (optional; DOTW doesn't provide it) | `"Burj Al Arab"` |
| `checkin` | String | Yes | Check-in date (YYYY-MM-DD, must match `getRoomRates`) | `"2026-02-28"` |
| `checkout` | String | Yes | Check-out date (YYYY-MM-DD, must match `getRoomRates`) | `"2026-03-02"` |
| `rooms` | [SearchHotelRoomInput!]! | Yes | Room config (must match `getRoomRates`) | See searchHotels |
| `selected_room_type` | String | Yes | Room type code from `getRoomRates` | `"DBL"` |
| `selected_rate_basis` | String | Yes | Rate basis ID from `getRoomRates` | `"1332"` |
| `allocation_details` | String | Yes | Opaque token from `getRoomRates` (CRITICAL: never modify) | Base64-like string |
| `currency` | String | No | Currency code (defaults to DOTW account default) | `"KWD"` |

#### Response

```graphql
type BlockRatesResponse {
  success: Boolean!
  error: DotwError
  meta: DotwMeta!
  cached: Boolean!         # Always false
  data: BlockRatesData
}

type BlockRatesData {
  prebook_key: String!              # UUID; pass to createPreBooking
  expires_at: String!               # ISO 8601 expiry timestamp (3 minutes from now)
  countdown_timer_seconds: Int!      # Seconds remaining (display in UI)
  hotel_code: String!               # Echo of input
  hotel_name: String!               # Echo of input or empty string
  room_type: String!                # Echo of input
  rate_basis: String!               # Echo of input
  total_fare: Float!                # Customer-facing price (after markup)
  total_tax: Float!
  markup: RateMarkup!               # Transparent pricing
  is_refundable: Boolean!
  cancellation_rules: [CancellationRule!]!
}
```

#### Example Response

```json
{
  "data": {
    "blockRates": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "d4e5f6a7-b8c9-4789-abcd-ef0123456789",
        "request_id": "d4e5f6a7-b8c9-4789-abcd-ef0123456789",
        "timestamp": "2026-02-21T14:33:00Z",
        "company_id": 5
      },
      "cached": false,
      "data": {
        "prebook_key": "9d3b8e2c-5f4a-4f1a-9e3b-2c8d5f4a1e9b",
        "expires_at": "2026-02-21T14:36:00Z",
        "countdown_timer_seconds": 180,
        "hotel_code": "HOTEL-001",
        "hotel_name": "Burj Al Arab",
        "room_type": "DBL",
        "rate_basis": "1332",
        "total_fare": 287.5,
        "total_tax": 10.0,
        "markup": {
          "original_fare": 250.0,
          "markup_percent": 15.0,
          "markup_amount": 37.5,
          "final_fare": 287.5
        },
        "is_refundable": true,
        "cancellation_rules": [
          {
            "from_date": "2026-02-25T00:00:00Z",
            "to_date": "2026-02-27T23:59:59Z",
            "charge": 0.0,
            "cancel_charge": 0.0
          }
        ]
      }
    }
  }
}
```

#### Error Cases

| Scenario | Error Code | Action | Details |
|---|---|---|---|
| Missing allocation_details | `VALIDATION_ERROR` | `RETRY` | Required for blocking |
| allocation_details < 60 sec from expiry | `ALLOCATION_EXPIRED` | `RESEARCH` | Re-search required |
| Rate no longer available | `RATE_UNAVAILABLE` | `RESEARCH` | Re-search required |
| Hotel sold out | `HOTEL_SOLD_OUT` | `RESEARCH` | Re-search required |
| Company not authenticated | `CREDENTIALS_NOT_CONFIGURED` | `RECONFIGURE_CREDENTIALS` | |
| DOTW API timeout | `API_TIMEOUT` | `RETRY` | |
| DOTW API error | `API_ERROR` | `RETRY` | |

#### Important Notes

- **3-minute countdown:** Display countdown timer in UI; prompt user to complete booking within 3 minutes.
- **BLOCK-08 auto-expiry:** Calling `blockRates` again from the same company/WhatsApp user automatically expires the previous `prebook_key`. Only one active prebook per conversation.
- **allocation_details is critical:** Pass the raw token from `getRoomRates` without modification, encoding, or truncation. DOTW uses this to validate the rate lock.
- **Audit logging:** Two-phase: Phase A (API call) logged by `DotwService::getRooms()` internally; Phase B (prebook creation) logged via `DotwAuditService` with `prebook_key`.
- **Database:** Creates a `dotw_prebooks` record with `prebook_key`, expiry, rate details, and `resayil_message_id`.

---

### createPreBooking

Confirm a locked rate into an actual DOTW hotel booking. Validates passenger details and completes the booking workflow.

**Use Case:** Convert a locked prebook (3-minute hold) into a confirmed booking when passenger details are provided.

#### Request

```graphql
mutation CreatePreBooking {
  createPreBooking(
    prebook_key: "9d3b8e2c-5f4a-4f1a-9e3b-2c8d5f4a1e9b"
    checkin: "2026-02-28"
    checkout: "2026-03-02"
    destination: "DXB"
    passengers: [
      {
        salutation: 1
        firstName: "John"
        lastName: "Doe"
        nationality: "KW"
        residenceCountry: "KW"
        email: "john.doe@example.com"
      }
    ]
    rooms: [
      {
        adultsCode: 2
        children: [5]
        passengerNationality: "KW"
        passengerCountryOfResidence: "KW"
      }
    ]
  ) {
    success
    error {
      error_code
      error_message
      action
    }
    meta {
      trace_id
      timestamp
      company_id
    }
    cached
    data {
      booking_confirmation_code
      booking_status
      itinerary_details {
        hotel_code
        hotel_name
        checkin
        checkout
        room_type
        rate_basis
        total_fare
        currency
        is_refundable
        lead_guest_name
        customer_reference
        confirmation_number
      }
      alternatives {
        hotel_code
        rooms {
          adults
          children
          children_ages
          room_types {
            code
            name
            rate_basis_id
            currency_id
            non_refundable
            total
            markup {
              original_fare
              markup_percent
              markup_amount
              final_fare
            }
            total_taxes
            total_minimum_selling
          }
        }
      }
    }
  }
}
```

#### Input Arguments

**CreatePreBookingInput (via @spread):**

| Field | Type | Required | Description | Example |
|---|---|---|---|---|
| `prebook_key` | String | Yes | UUID from `blockRates` response | `"9d3b8e2c-..."` |
| `checkin` | String | Yes | Check-in date (YYYY-MM-DD, must match blockRates) | `"2026-02-28"` |
| `checkout` | String | Yes | Check-out date (YYYY-MM-DD, must match blockRates) | `"2026-03-02"` |
| `passengers` | [PassengerInput!]! | Yes | Array of passenger details (count = total adults) | See below |
| `rooms` | [SearchHotelRoomInput!]! | Yes | Room config (must match blockRates) | See searchHotels |
| `destination` | String | No | City code for alternative suggestions on failure | `"DXB"` |

**PassengerInput:**

| Field | Type | Required | Description | Example |
|---|---|---|---|---|
| `salutation` | Int | Yes | Salutation code: 1=Mr, 2=Mrs, 3=Ms, 4=Dr, 5=Prof | `1` |
| `firstName` | String | Yes | Passenger first name | `"John"` |
| `lastName` | String | Yes | Passenger last name | `"Doe"` |
| `nationality` | String | Yes | ISO 3166-1 alpha-2 nationality | `"KW"` |
| `residenceCountry` | String | Yes | ISO 3166-1 alpha-2 residence country | `"KW"` |
| `email` | String | Yes | Valid email address (lead passenger receives confirmation) | `"john@example.com"` |

#### Response

```graphql
type CreatePreBookingResponse {
  success: Boolean!
  error: DotwError
  meta: DotwMeta!
  cached: Boolean!         # Always false
  data: CreatePreBookingData
}

type CreatePreBookingData {
  booking_confirmation_code: String!     # DOTW confirmation code
  booking_status: String!                # "confirmed", etc.
  itinerary_details: BookingItinerary!
  alternatives: [HotelSearchResult!]!    # Up to 3 alternatives if booking failed
}

type BookingItinerary {
  hotel_code: String!
  hotel_name: String!
  checkin: String!
  checkout: String!
  room_type: String!
  rate_basis: String!
  total_fare: Float!                     # Customer-facing price
  currency: String!
  is_refundable: Boolean!
  lead_guest_name: String!               # Salutation + firstName + lastName
  customer_reference: String!            # UUID for amendment/dispute reference
  confirmation_number: String!           # Secondary DOTW confirmation number
}
```

#### Example Response (Success)

```json
{
  "data": {
    "createPreBooking": {
      "success": true,
      "error": null,
      "meta": {
        "trace_id": "e5f6a7b8-c9d0-4789-abcd-ef0123456789",
        "request_id": "e5f6a7b8-c9d0-4789-abcd-ef0123456789",
        "timestamp": "2026-02-21T14:34:00Z",
        "company_id": 5
      },
      "cached": false,
      "data": {
        "booking_confirmation_code": "DOT12345678",
        "booking_status": "confirmed",
        "itinerary_details": {
          "hotel_code": "HOTEL-001",
          "hotel_name": "Burj Al Arab",
          "checkin": "2026-02-28",
          "checkout": "2026-03-02",
          "room_type": "DBL",
          "rate_basis": "1332",
          "total_fare": 287.5,
          "currency": "KWD",
          "is_refundable": true,
          "lead_guest_name": "Mr John Doe",
          "customer_reference": "9e3b8e2c-5f4a-4f1a-9e3b-2c8d5f4a1e9b",
          "confirmation_number": "CONF987654"
        },
        "alternatives": []
      }
    }
  }
}
```

#### Example Response (Failure with Alternatives)

```json
{
  "data": {
    "createPreBooking": {
      "success": false,
      "error": {
        "error_code": "RATE_UNAVAILABLE",
        "error_message": "This hotel/rate no longer available. Please search again or choose an alternative.",
        "error_details": "DOTW API returned: rate no longer available",
        "action": "RESEARCH"
      },
      "meta": {
        "trace_id": "f6a7b8c9-d0e1-4789-abcd-ef0123456789",
        "request_id": "f6a7b8c9-d0e1-4789-abcd-ef0123456789",
        "timestamp": "2026-02-21T14:35:00Z",
        "company_id": 5
      },
      "cached": false,
      "data": {
        "booking_confirmation_code": "",
        "booking_status": "failed",
        "itinerary_details": {
          "hotel_code": "HOTEL-001",
          "hotel_name": "Burj Al Arab",
          "checkin": "2026-02-28",
          "checkout": "2026-03-02",
          "room_type": "DBL",
          "rate_basis": "1332",
          "total_fare": 287.5,
          "currency": "KWD",
          "is_refundable": true,
          "lead_guest_name": "",
          "customer_reference": "",
          "confirmation_number": ""
        },
        "alternatives": [
          {
            "hotel_code": "HOTEL-002",
            "rooms": [
              {
                "adults": "2",
                "children": "1",
                "children_ages": "5",
                "room_types": [
                  {
                    "code": "DBL",
                    "name": "Double Room",
                    "rate_basis_id": "1332",
                    "currency_id": "KWD",
                    "non_refundable": false,
                    "total": 240.0,
                    "markup": {
                      "original_fare": 240.0,
                      "markup_percent": 15.0,
                      "markup_amount": 36.0,
                      "final_fare": 276.0
                    },
                    "total_taxes": 10.0,
                    "total_minimum_selling": 265.0
                  }
                ]
              }
            ]
          }
        ]
      }
    }
  }
}
```

#### Error Cases

| Scenario | Error Code | Action | Details |
|---|---|---|---|
| Prebook not found | `VALIDATION_ERROR` | `RESEARCH` | Invalid prebook_key |
| Prebook expired (> 3 min) | `ALLOCATION_EXPIRED` | `RESEARCH` | User took too long; re-search required |
| Passenger count mismatch | `VALIDATION_ERROR` | `RETRY` | Count doesn't match total adults |
| Missing passenger field | `PASSENGER_VALIDATION_FAILED` | `RETRY` | e.g., missing email |
| Invalid email format | `PASSENGER_VALIDATION_FAILED` | `RETRY` | Email validation failed |
| Rate no longer available | `RATE_UNAVAILABLE` | `RESEARCH` | Suggest alternatives (if destination provided) |
| Hotel sold out | `HOTEL_SOLD_OUT` | `RESEARCH` | Suggest alternatives (if destination provided) |
| Company not authenticated | `CREDENTIALS_NOT_CONFIGURED` | `RECONFIGURE_CREDENTIALS` | |
| DOTW API timeout | `API_TIMEOUT` | `RETRY` | |
| DOTW API error | `API_ERROR` | `RETRY` | |

#### Important Notes

- **Passenger count:** Must equal total adults across all rooms in the room config.
- **Lead passenger:** First passenger receives DOTW confirmation email.
- **customer_reference:** UUID generated for each booking; use for amendments or disputes.
- **Prebook expiry check (ERROR-03):** Checked BEFORE any DOTW API call. If expired, return `ALLOCATION_EXPIRED` immediately.
- **Atomic transaction:** Prebook expiry + booking creation happen atomically via `DB::transaction()`.
- **Alternative suggestions (ERROR-04):** If rate unavailable and `destination` provided, automatically search for up to 3 alternative hotels.
- **Audit logging:** Two-phase pattern (same as blockRates).
- **Database:** Creates `dotw_bookings` record with confirmation details and passenger data.

---

## Data Types

### DotwMeta

Present in every response; used for request correlation.

```graphql
type DotwMeta {
  trace_id: String!        # UUID v4 unique to this request
  request_id: String!      # Same as trace_id (backwards compat)
  timestamp: String!       # ISO 8601 UTC timestamp (e.g., 2026-02-21T14:30:00Z)
  company_id: Int!         # Authenticated company ID
}
```

### DotwError

Present only when `success: false`.

```graphql
type DotwError {
  error_code: DotwErrorCode!      # Machine-readable enum
  error_message: String!          # User-friendly message (WhatsApp-safe)
  error_details: String           # Technical details for debugging (never shown to users)
  action: DotwErrorAction!        # Suggested next action
}

enum DotwErrorCode {
  CREDENTIALS_NOT_CONFIGURED
  CREDENTIALS_INVALID
  ALLOCATION_EXPIRED
  RATE_UNAVAILABLE
  HOTEL_SOLD_OUT
  PASSENGER_VALIDATION_FAILED
  API_TIMEOUT
  API_ERROR
  CIRCUIT_BREAKER_OPEN
  VALIDATION_ERROR
  INTERNAL_ERROR
}

enum DotwErrorAction {
  RETRY
  RETRY_IN_30_SECONDS
  RECONFIGURE_CREDENTIALS
  RESEARCH
  CANCEL
  NONE
}
```

### RateMarkup

Transparent pricing breakdown for every rate.

```graphql
type RateMarkup {
  original_fare: Float!      # Fare from DOTW before markup
  markup_percent: Float!     # Markup percentage (0–100)
  markup_amount: Float!      # Absolute markup amount in currency
  final_fare: Float!         # Customer-facing price (original + markup)
}
```

### SearchHotelRoomInput

Room configuration (reused across queries).

```graphql
input SearchHotelRoomInput {
  adultsCode: Int!
  children: [Int!]!
  passengerNationality: String
  passengerCountryOfResidence: String
}
```

### CancellationRule

Cancellation penalty rules for a rate.

```graphql
type CancellationRule {
  from_date: String!         # ISO 8601 (e.g., 2026-02-28T00:00:00Z)
  to_date: String!           # ISO 8601
  charge: Float!             # Penalty amount
  cancel_charge: Float!      # May differ from charge in DOTW responses
}
```

---

## Common Patterns

### Per-Company Credential Resolution

All DOTW operations resolve credentials from the authenticated user's company context:

```php
$companyId = auth()->user()?->company?->id;

if ($companyId === null) {
    return $this->errorResponse(
        'CREDENTIALS_NOT_CONFIGURED',
        'No authenticated company context.',
        'RECONFIGURE_CREDENTIALS'
    );
}

$dotwService = new DotwService($companyId);
```

Credentials are fetched from the `dotw_company_credentials` table (encrypted at rest using Laravel's `Crypt`).

### Allocation Details Token Handling

The `allocation_details` field is an **opaque token** returned by DOTW. It must be passed verbatim to subsequent operations:

```php
// From getRoomRates response
$rateDetail = [
    'allocation_details' => 'SGVsbG8gV29ybGQhIFRoaXMgaXMgYW4gb3BhcXVlIHRva2VuLg==',
    // ... other fields
];

// Pass to blockRates WITHOUT encoding, truncation, or modification
$blockInput = [
    'allocation_details' => $rateDetail['allocation_details'],  // Raw value
    // ... other fields
];
```

**Critical:** Any modification of this token will cause DOTW to reject the blocking or confirmation call.

### Room Configuration Consistency

Room configurations must be identical across the workflow:

```
searchHotels(rooms: [...])
    → getRoomRates(rooms: [...])  # MUST match searchHotels
    → blockRates(rooms: [...])    # MUST match getRoomRates
    → createPreBooking(rooms: [...]) # MUST match blockRates
```

If room configs diverge, rate prices or allocation tokens may be invalid.

### Markup Application

Every resolver applies per-company markup to rates:

```php
$markup = $dotwService->applyMarkup($originalFare);
// Returns:
// [
//   'original_fare' => 250.0,
//   'markup_percent' => 15.0,
//   'markup_amount' => 37.5,
//   'final_fare' => 287.5
// ]
```

The `final_fare` is the customer-facing price shown in WhatsApp conversations.

### Error Handling in Workflows

N8N workflows should branch on `error_code` and `action`:

```graphql
# Hypothetical N8N logic
if (response.success) {
  // Continue to next step
} else {
  const action = response.error.action;
  if (action === 'RETRY') {
    // Retry immediately
  } else if (action === 'RETRY_IN_30_SECONDS') {
    // Wait 30 seconds, retry
  } else if (action === 'RESEARCH') {
    // Run searchHotels again
  } else if (action === 'RECONFIGURE_CREDENTIALS') {
    // Notify admin; stop workflow
  }
  // Else: handle other actions (CANCEL, NONE, etc.)
}
```

### Audit Logging

Every operation is audit-logged to the `dotw_audit_logs` table with:

- `trace_id` — for log correlation
- `company_id` — company context
- `operation` — query/mutation name
- `input_data` — request arguments
- `output_data` — response data
- `resayil_message_id` — WhatsApp conversation ID (if provided)
- `resayil_quote_id` — quote context (if provided)

Access audit logs via:
- Laravel: `DotwAuditLog::where('trace_id', $traceId)->get()`
- UI: `/settings` → "DOTW / Hotel API" tab → "Audit Logs" (per-company)

---

## Troubleshooting

### Credential Errors

**Symptom:** Repeated `CREDENTIALS_NOT_CONFIGURED` or `CREDENTIALS_INVALID` errors.

**Solutions:**
1. Verify company credentials in `/settings` → "DOTW / Hotel API" → "Credentials"
2. Test DOTW account independently (username, password, company code)
3. Check database: `SELECT * FROM dotw_company_credentials WHERE company_id = ?`
4. Verify encryption: credentials are encrypted using Laravel `Crypt`; check `.env` `APP_KEY`

### Circuit Breaker Open

**Symptom:** `CIRCUIT_BREAKER_OPEN` error; cannot search hotels.

**Solutions:**
1. Wait 30 seconds as suggested by the error action
2. Check server logs for recent DOTW failures: `tail -f storage/logs/dotw.log`
3. Verify DOTW API availability independently
4. Check DOTW account rate limits (supplier-side throttling)
5. Reset circuit breaker (if debugging):
   ```php
   use App\Services\DotwCircuitBreakerService;
   $cb = new DotwCircuitBreakerService();
   $cb->recordSuccess($companyId); // Reset counter
   ```

### Allocation Token Corruption

**Symptom:** `blockRates` fails with `RATE_UNAVAILABLE` immediately after `getRoomRates`.

**Causes:**
1. `allocation_details` was encoded, Base64-decoded, or truncated
2. Token passed to `blockRates` does not match `getRoomRates` response

**Solution:**
1. Verify token is passed raw (no encoding)
2. Check token length in logs (compare getRoomRates response vs blockRates input)
3. Re-call `getRoomRates` and try `blockRates` again

### Prebook Expiry

**Symptom:** `createPreBooking` fails with `ALLOCATION_EXPIRED`.

**Causes:**
1. User took longer than 3 minutes between `blockRates` and `createPreBooking`
2. Another `blockRates` call from same user expired the prebook (BLOCK-08)

**Solution:**
1. Inform user to provide passenger details within 3 minutes
2. If re-attempting booking, call `blockRates` again to generate a new `prebook_key`

### Rate Unavailable on Confirmation

**Symptom:** `createPreBooking` fails with `RATE_UNAVAILABLE` and suggests alternatives.

**Causes:**
1. Rate was released by another user
2. Hotel inventory changed between `blockRates` and `createPreBooking`
3. DOTW supplier released the allocation

**Solution:**
1. User should select from suggested alternatives (if provided)
2. Re-run `searchHotels` to see current availability
3. Select a different hotel or time

### Passenger Validation Failures

**Symptom:** `createPreBooking` fails with `PASSENGER_VALIDATION_FAILED`.

**Check:**
1. Count matches total adults: `passengers.length === rooms[0].adultsCode + rooms[1].adultsCode + ...`
2. All required fields present: `salutation, firstName, lastName, nationality, residenceCountry, email`
3. Email format is valid: `filter_var($email, FILTER_VALIDATE_EMAIL)`

### Cache Issues

**Symptom:** `searchHotels` returns stale results; `cached: true` when fresh search expected.

**Solution:**
1. Cache TTL is 2.5 minutes per company per destination/dates/rooms
2. Wait 2.5 minutes for cache to expire
3. Or clear cache manually:
   ```php
   use App\Services\DotwCacheService;
   $cache = new DotwCacheService();
   $key = $cache->buildKey($companyId, $destination, $checkin, $checkout, $rooms);
   cache()->forget($key);
   ```

### Trace ID Correlation

**To debug a failed request:**

1. Note the `trace_id` from GraphQL response meta (or `X-Trace-ID` header)
2. Search server logs: `grep "a1b2c3d4-..." storage/logs/dotw.log`
3. Check audit logs: `SELECT * FROM dotw_audit_logs WHERE trace_id = '...'`
4. Correlate with N8N workflow execution logs (if applicable)

---

## Best Practices

1. **Always extract and store trace_id:** Use for debugging and customer support.
2. **Implement retry logic:** Handle `API_TIMEOUT` and `API_ERROR` with exponential backoff.
3. **Display countdown timer:** Show `countdown_timer_seconds` from `blockRates` to guide user urgency.
4. **Validate passenger data early:** Before calling `blockRates`, ensure passenger count and email format.
5. **Pass optional destination to createPreBooking:** Enables alternative suggestions on failure.
6. **Monitor circuit breaker:** Alert admins when frequently triggered (indicates DOTW API issues).
7. **Audit all bookings:** Store confirmation codes and trace IDs for reconciliation.
8. **Test with @spread:** The `createPreBooking` mutation uses GraphQL `@spread` to flatten input fields; ensure N8N payload matches schema.

---

## Related Documentation

- **Architecture:** See `PROJECT_OVERVIEW.md` for full system context
- **DOTW Integration:** See `OPENWEBUI_INTEGRATION.md` for AI document processing integration
- **Settings UI:** `/settings` → "DOTW / Hotel API" tab for credentials and tokens
- **Audit UI:** `/admin/dotw` or `/settings` → "DOTW / Hotel API" → "Audit Logs"

---

**Document Version:** 1.0 (B2B)
**Last Updated:** February 2026
**Maintained By:** @soudshoja
