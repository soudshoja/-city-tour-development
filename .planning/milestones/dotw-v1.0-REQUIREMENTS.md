# Requirements: DOTW v1.0 B2B

**Defined:** 2026-02-21
**Core Value:** Per-company DOTW credentials with Resayil WhatsApp message tracking enable B2B hotel booking API integrations through comprehensive, cacheable GraphQL operations.

**Architecture:** Development (soud-laravel) → Live (production subdomain) | Modular, copyable, no tight coupling

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### 1. Credential Management

- [ ] **CRED-01**: Database migration creates `company_dotw_credentials` table (company_id, dotw_username, dotw_password encrypted, dotw_company_code, created_at, updated_at)
- [ ] **CRED-02**: Admin UI/API allows storing/updating per-company DOTW credentials securely
- [ ] **CRED-03**: Credentials encrypted at rest using Laravel encryption (never logged in plaintext)
- [ ] **CRED-04**: DotwService resolves correct company credentials based on authenticated company context
- [ ] **CRED-05**: Missing credentials returns helpful error directing admin to configure

### 2. Message Tracking & Audit Trail

- [ ] **MSG-01**: Database migration creates `dotw_audit_logs` table (company_id, resayil_message_id, resayil_quote_id, operation_type, request_payload, response_payload, created_at)
- [ ] **MSG-02**: Every GraphQL operation logs Resayil message_id + quote_id (passed in GraphQL headers)
- [ ] **MSG-03**: Audit log captures entire request/response for debugging (DOTW bookings linked to WhatsApp conversations)
- [ ] **MSG-04**: All DOTW operations (search, rates, block, book) link to originating WhatsApp message for conversation context
- [ ] **MSG-05**: Audit logs never contain encrypted credentials or sensitive passenger details

### 3. Search Result Caching

- [ ] **CACHE-01**: Hotel search results cached for 2.5 minutes using Laravel cache (key: `dotw_search_{company_id}_{destination}_{dates}_{rooms_hash}`)
- [ ] **CACHE-02**: Subsequent searches for same destination/dates within 2.5 min return cached results (no API call)
- [ ] **CACHE-03**: Cache key includes room configuration hash to handle room type/occupancy changes
- [ ] **CACHE-04**: Cache is per-company (no cross-company data leakage)
- [ ] **CACHE-05**: GraphQL response includes `cached: true` flag to indicate whether result is fresh or from cache

### 4. Hotel Search GraphQL

- [ ] **SEARCH-01**: GraphQL `searchHotels` query accepts destination (city code/name), check-in date, check-out date, resayil_message_id header
- [ ] **SEARCH-02**: Query accepts room configuration (number of rooms, adults per room, children per room with ages)
- [ ] **SEARCH-03**: Query accepts currency (defaults to company currency if not specified)
- [ ] **SEARCH-04**: Query supports full DOTW filter vocabulary: rating, price range, property type, meal plan type, amenities, cancellation policies
- [ ] **SEARCH-05**: Returns hotels with cheapest rate per meal plan per room type (DOTW searchhotels response)
- [ ] **SEARCH-06**: Response includes hotel code, name, city, rating, location, image_url, cheapest rates grouped by room type
- [ ] **SEARCH-07**: Logs search to `dotw_audit_logs` with resayil_message_id, destination, filters used
- [ ] **SEARCH-08**: Returns `cached: true` if result from 2.5 min cache, `cached: false` if fresh API call

### 5. Rate Browsing GraphQL

- [ ] **RATE-01**: GraphQL `getRoomRates` query accepts hotel_code, check-in, check-out, room config, resayil_message_id header
- [ ] **RATE-02**: Returns all room types for hotel with all meal plans and rates (detailed breakdown)
- [ ] **RATE-03**: Each rate includes: total_fare, tax, total_price, cancellation_policy (refundable/non-refundable with fees)
- [ ] **RATE-04**: Response includes `allocationDetails` token (opaque string, required for blocking and confirmation)
- [ ] **RATE-05**: Shows original_currency, exchange_rate, and final_currency (with 20% B2C markup applied)
- [ ] **RATE-06**: Each rate tagged with rate_basis code (1331=BB, 1332=HB, 1333=FB, 1334=AI, 1335=ALL, 1336=RO)
- [ ] **RATE-07**: Includes refundability status and cancellation deadline
- [ ] **RATE-08**: Logs operation to `dotw_audit_logs` with hotel_code, rates returned count

### 6. Rate Blocking GraphQL

- [ ] **BLOCK-01**: GraphQL `blockRates` mutation accepts: hotel_code, dates, room_config, selected_room_type, selected_rate_basis, allocationDetails token, resayil_message_id header
- [ ] **BLOCK-02**: Validates allocationDetails token matches selected hotel (prevents token mixing)
- [ ] **BLOCK-03**: Calls DOTW getRooms with blocking=true (locks rate for 3 minutes)
- [ ] **BLOCK-04**: Creates `dotw_prebooks` record: prebook_key (UUID), allocation_details, hotel_code, hotel_name, room_type, total_fare, total_tax, currency, is_refundable, expired_at (now + 3 min), resayil_message_id
- [ ] **BLOCK-05**: Returns: prebook_key, hotel details, selected rates, countdown_timer_seconds (180, 179, ..., 0), expires_at timestamp
- [ ] **BLOCK-06**: Rejects if allocation < 1 minute remaining (prompts re-search)
- [ ] **BLOCK-07**: Logs to `dotw_audit_logs` with prebook_key created, allocation_expiry time
- [ ] **BLOCK-08**: Only one active prebook per (company, WhatsApp user) allowed (new prebook expires previous)

### 7. Pre-Booking & Confirmation GraphQL

- [ ] **BOOK-01**: GraphQL `createPreBooking` mutation accepts: prebook_key, passengers array (salutation, firstName, lastName, nationality, residenceCountry, email), resayil_message_id header
- [ ] **BOOK-02**: Validates: all required passenger fields present, passenger count matches room configuration, email format valid
- [ ] **BOOK-03**: Validates prebook_key exists and not expired (> 0 seconds remaining)
- [ ] **BOOK-04**: Calls DOTW confirmBooking with passenger details, allocationDetails, email
- [ ] **BOOK-05**: On success: Returns booking_confirmation_code, booking_status, itinerary_details
- [ ] **BOOK-06**: Creates `dotw_bookings` record: confirmation_code, prebook_key, passengers, booking_status, hotel_details, resayil_message_id, company_id
- [ ] **BOOK-07**: Logs to `dotw_audit_logs` with confirmation_code, booking_status
- [ ] **BOOK-08**: On failure: Returns specific error (rate no longer available, hotel sold out, DOTW system error) with helpful message for N8N

### 8. GraphQL Response Structure & Error Handling

- [ ] **GQLR-01**: All GraphQL responses return: success (boolean), data (if successful), error (if failed), timestamp, trace_id (for debugging)
- [ ] **GQLR-02**: Structured error responses with: error_code, error_message (user-friendly for N8N), error_details (technical), action (retry/cancel/reconfigure)
- [ ] **GQLR-03**: GraphQL schema self-documented (descriptions on all fields, input types, enums)
- [ ] **GQLR-04**: All operations support synchronous call (user waits for response in WhatsApp conversation)
- [ ] **GQLR-05**: Response includes company context in meta (company_id, request_id)
- [ ] **GQLR-06**: Response headers include X-Trace-ID, X-Request-Time-Ms for debugging
- [ ] **GQLR-07**: Error responses include action hints for N8N workflow (e.g., "retry_in_30_seconds", "reconfigure_credentials")
- [ ] **GQLR-08**: All responses consistent format regardless of operation (searchHotels, getRoomRates, blockRates, createPreBooking)

### 9. B2C Markup & Pricing

- [ ] **MARKUP-01**: Default 20% B2C markup applied to all fares in search/rate/block responses
- [ ] **MARKUP-02**: Admin can set custom markup percentage per company (stored in `company_dotw_credentials`)
- [ ] **MARKUP-03**: Markup calculation transparent in responses: {original_fare: 100, markup_percent: 20, markup_amount: 20, final_fare: 120}
- [ ] **MARKUP-04**: Markup applied consistently across all operations (same hotel+rate always shows same markup)
- [ ] **MARKUP-05**: Markup shown in WhatsApp messages (e.g., "100 KD → 120 KD after markup")

### 10. Error Handling & Resilience

- [ ] **ERROR-01**: Invalid company credentials → 401 "DOTW credentials not configured for this company"
- [ ] **ERROR-02**: DOTW API timeout (> 25 sec) → "Search taking too long, please try again" (N8N can retry)
- [ ] **ERROR-03**: Allocation expired → "Rate offer expired, please search again" (clear, actionable)
- [ ] **ERROR-04**: Rate no longer available → "This hotel/rate no longer available, similar options:" + suggest 3 alternatives
- [ ] **ERROR-05**: Missing passenger field → "Please provide passenger {field_name}" (specific error per missing field)
- [ ] **ERROR-06**: Hotel sold out → "Hotel full, showing 3 similar alternatives with availability"
- [ ] **ERROR-07**: All errors logged to 'dotw' channel (no credentials, no full responses)
- [ ] **ERROR-08**: Circuit breaker: If DOTW API fails 5x in 1 minute, return cached results (if available) or friendly "Try again in 30 seconds"

### 11. Modular Architecture

- [ ] **MOD-01**: DOTW module lives in separate Laravel service (`app/Services/DotwService.php`)
- [ ] **MOD-02**: DOTW config file (`config/dotw.php`) is environment-agnostic (no hardcoded paths)
- [ ] **MOD-03**: DOTW migrations (`database/migrations/dotw_*.php`) are standalone and idempotent
- [ ] **MOD-04**: DOTW GraphQL schema (`graphql/dotw.graphql`) is modular and composable
- [ ] **MOD-05**: DOTW models (`app/Models/DotwPrebook.php`, `DotwBooking.php`, `DotwAuditLog.php`) use standard Eloquent patterns
- [ ] **MOD-06**: No dependencies on invoice/task system (decoupled for v2 integration later)
- [ ] **MOD-07**: Can copy entire DOTW module to production with: config changes + migrations + service + GraphQL schema
- [ ] **MOD-08**: README documents deployment: env vars needed, migration steps, GraphQL schema registration

### 12. B2B Flexibility & Extensibility

- [ ] **B2B-01**: GraphQL supports multiple room types in single search (agents can explore complex itineraries)
- [ ] **B2B-02**: Filter support matches full DOTW V4 vocabulary (not hardcoded to common filters)
- [ ] **B2B-03**: Room details include all DOTW fields (not summarized, allows detailed negotiation)
- [ ] **B2B-04**: Multi-company credential isolation (credentials never leak between companies)
- [ ] **B2B-05**: API designed for external B2B partners (N8N/Resayil is primary consumer, but extensible)

## v2 Requirements

Deferred to future release. Not in current roadmap.

### DOTW V2 B2C — N8N GraphQL Integration

Complete GraphQL API with full N8N + Resayil integration for production WhatsApp booking workflows.

- **GQL-01**: GraphQL endpoint accepts company authentication (Bearer token or X-Company-ID header)
- **GQL-02**: All operations require: X-Company-ID header (matches authenticated company), X-Resayil-Message-ID header (WhatsApp message_id), optional X-Resayil-Quote-ID header (for quoted messages)
- **GQL-03**: N8N workflows can call searchHotels, getRoomRates, blockRates, createPreBooking mutations
- **GQL-04**: Rate limiting: 100 requests/minute per company (returns 429 with retry_after header)
- **GQL-05**: Timeout enforcement: 30 second maximum response time (DOTW API 25 sec, buffer 5 sec)
- **GQL-06**: Webhook callbacks for async operations (e.g., booking confirmation callback to Resayil)
- **GQL-07**: GraphQL subscription support for real-time allocation countdown (WebSocket)
- **GQL-08**: N8N node templates pre-built (searchHotels, getRoomRates, blockRates, createPreBooking)

### Task & Invoice Integration

- **TASK-01**: Booking confirmation auto-creates hotel task with DOTW confirmation code + itinerary details
- **TASK-02**: Hotel task feeds into bulk invoice upload workflow (creates invoice from booking)
- **TASK-03**: Invoice auto-populated with DOTW rates, hotel details, and booking reference

### Advanced Features

- **ADV-01**: Save Booking (non-refundable itinerary code) for rate flexibility
- **ADV-02**: Booking amendments (date changes, passenger changes, room upgrades via DOTW)
- **ADV-03**: Cancellation management (calculate refund from DOTW cancellation policy)
- **ADV-04**: Rate history tracking (price trends, historical rates for dates/hotels)
- **ADV-05**: Favorite hotels/searches (agent history, quick re-search)

### Integrations & Automation

- **INT-01**: Sync confirmed bookings with CRM/ERP via webhook
- **INT-02**: Email confirmation templates (customizable per company)
- **INT-03**: SMS notifications on booking confirmation (fallback if WhatsApp fails)
- **INT-04**: Calendar integration (auto-add booking dates to agent calendar)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Save Booking (non-refundable itinerary) | Additional DOTW operation; requires different confirmation logic; v2 |
| Booking amendments | Requires amendment-specific DOTW calls; complex state management; v2 |
| Cancellation refunds | Requires cancellation API call + refund calculation; v2 |
| Rate history/analytics | Nice-to-have analytics; v2 feature |
| Payment capture | Payment gateway (MyFatoorah) handles this; not DOTW responsibility |
| SMS notifications | Resayil WhatsApp preferred; SMS deferred to v2 |
| Real-time rate monitoring | Rates called on-demand; background monitoring is v2 optimization |
| Mobile app | Web API first; mobile apps are post-v1 |
| Multi-language UI | English + Arabic in GraphQL data; UI translations are v2 |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| CRED-01 | Phase 1 | Pending |
| CRED-02 | Phase 1 | Pending |
| CRED-03 | Phase 1 | Pending |
| CRED-04 | Phase 1 | Pending |
| CRED-05 | Phase 1 | Pending |
| MARKUP-01 | Phase 1 | Pending |
| MARKUP-02 | Phase 1 | Pending |
| ERROR-05 | Phase 1 | Pending |
| B2B-04 | Phase 1 | Pending |
| MSG-01 | Phase 2 | Pending |
| MSG-02 | Phase 2 | Pending |
| MSG-03 | Phase 2 | Pending |
| MSG-04 | Phase 2 | Pending |
| MSG-05 | Phase 2 | Pending |
| CACHE-01 | Phase 3 | Pending |
| CACHE-02 | Phase 3 | Pending |
| CACHE-03 | Phase 3 | Pending |
| CACHE-04 | Phase 3 | Pending |
| CACHE-05 | Phase 3 | Pending |
| GQLR-01 | Phase 3 | Pending |
| GQLR-02 | Phase 3 | Pending |
| GQLR-03 | Phase 3 | Pending |
| GQLR-04 | Phase 3 | Pending |
| GQLR-05 | Phase 3 | Pending |
| GQLR-06 | Phase 3 | Pending |
| GQLR-07 | Phase 3 | Pending |
| GQLR-08 | Phase 3 | Pending |
| SEARCH-01 | Phase 4 | Pending |
| SEARCH-02 | Phase 4 | Pending |
| SEARCH-03 | Phase 4 | Pending |
| SEARCH-04 | Phase 4 | Pending |
| SEARCH-05 | Phase 4 | Pending |
| SEARCH-06 | Phase 4 | Pending |
| SEARCH-07 | Phase 4 | Pending |
| SEARCH-08 | Phase 4 | Pending |
| B2B-01 | Phase 4 | Pending |
| B2B-02 | Phase 4 | Pending |
| B2B-03 | Phase 4 | Pending |
| RATE-01 | Phase 1 | Pending |
| RATE-02 | Phase 1 | Pending |
| RATE-03 | Phase 1 | Pending |
| RATE-04 | Phase 1 | Pending |
| RATE-05 | Phase 1 | Pending |
| RATE-06 | Phase 1 | Pending |
| RATE-07 | Phase 1 | Pending |
| RATE-08 | Phase 1 | Pending |
| BLOCK-01 | Phase 1 | Pending |
| BLOCK-02 | Phase 1 | Pending |
| BLOCK-03 | Phase 1 | Pending |
| BLOCK-04 | Phase 1 | Pending |
| BLOCK-05 | Phase 1 | Pending |
| BLOCK-06 | Phase 1 | Pending |
| BLOCK-07 | Phase 1 | Pending |
| BLOCK-08 | Phase 1 | Pending |
| MARKUP-03 | Phase 1 | Pending |
| MARKUP-04 | Phase 1 | Pending |
| MARKUP-05 | Phase 1 | Pending |
| BOOK-01 | Phase 2 | Pending |
| BOOK-02 | Phase 2 | Pending |
| BOOK-03 | Phase 2 | Pending |
| BOOK-04 | Phase 2 | Pending |
| BOOK-05 | Phase 2 | Pending |
| BOOK-06 | Phase 2 | Pending |
| BOOK-07 | Phase 2 | Pending |
| BOOK-08 | Phase 2 | Pending |
| ERROR-03 | Phase 2 | Pending |
| ERROR-04 | Phase 2 | Pending |
| ERROR-06 | Phase 2 | Pending |
| ERROR-01 | Phase 3 | Pending |
| ERROR-02 | Phase 3 | Pending |
| ERROR-07 | Phase 3 | Pending |
| ERROR-08 | Phase 3 | Pending |
| MOD-01 | Phase 4 | Pending |
| MOD-02 | Phase 4 | Pending |
| MOD-03 | Phase 4 | Pending |
| MOD-04 | Phase 4 | Pending |
| MOD-05 | Phase 4 | Pending |
| MOD-06 | Phase 4 | Pending |
| MOD-07 | Phase 4 | Pending |
| MOD-08 | Phase 4 | Pending |
| B2B-05 | Phase 4 | Pending |

**Coverage:**
- v1 requirements: 54 total
- Mapped to phases: 54
- Unmapped: 0 ✓

---
*Requirements defined: 2026-02-21*
*Last updated: 2026-02-21 — traceability updated for DOTW v1.0 B2B roadmap (phases 5-12)*
