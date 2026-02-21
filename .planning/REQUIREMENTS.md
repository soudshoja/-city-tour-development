# Requirements: DOTW v1.0 B2B

**Defined:** 2026-02-21
**Core Value:** Per-company DOTW credentials enable B2B hotel booking API integrations with flexible rate search, blocking, and pre-booking workflows through comprehensive GraphQL operations.

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Credential Management

- [ ] **CRED-01**: Database migration creates `company_dotw_credentials` table (company_id, dotw_username, dotw_password encrypted, dotw_company_code, created_at, updated_at)
- [ ] **CRED-02**: Admin UI allows storing/updating per-company DOTW credentials
- [ ] **CRED-03**: Credentials encrypted at rest using Laravel encryption (never logged in plaintext)
- [ ] **CRED-04**: DotwService resolves correct company credentials based on authenticated company context
- [ ] **CRED-05**: Missing credentials shows helpful error message directing admin to configure

### Hotel Search

- [ ] **SEARCH-01**: GraphQL `searchHotels` query accepts destination (city code/name), check-in date, check-out date
- [ ] **SEARCH-02**: `searchHotels` accepts room configuration (number of rooms, adults per room, children per room with ages)
- [ ] **SEARCH-03**: `searchHotels` accepts currency (default company currency or override)
- [ ] **SEARCH-04**: `searchHotels` supports optional DOTW filters: rating, price range, property type, meal plan type
- [ ] **SEARCH-05**: Query returns hotels with cheapest rate per meal plan per room type
- [ ] **SEARCH-06**: Response includes hotel code, name, city, rating, cheapest rates per room type
- [ ] **SEARCH-07**: Response time < 10 seconds (uses company credentials + caching if applicable)

### Rate Browsing

- [ ] **RATE-01**: GraphQL `getRoomRates` query accepts hotel code, check-in, check-out, rooms configuration
- [ ] **RATE-02**: Returns all room types for hotel with all meal plans and rates
- [ ] **RATE-03**: Each rate includes total fare, tax breakdown, cancellation policy (refundable/non-refundable with fees)
- [ ] **RATE-04**: Response includes `allocationDetails` token (required for blocking and confirmation)
- [ ] **RATE-05**: Includes original currency and exchange rate applied
- [ ] **RATE-06**: Each rate tagged with rate basis code (meal plan: 1331=BB, 1332=HB, 1333=FB, 1334=AI, etc.)

### Rate Blocking

- [ ] **BLOCK-01**: GraphQL `blockRates` mutation accepts hotel code, dates, room config, selected room type, selected rate basis, allocationDetails token
- [ ] **BLOCK-02**: Calls DOTW getRooms with blocking=true (locks rate for 3 minutes)
- [ ] **BLOCK-03**: Creates `dotw_prebooks` record with prebook_key (UUID), allocation_details, hotel details, room details, expired_at (3 min from now)
- [ ] **BLOCK-04**: Returns prebook record with countdown timer (time until expiry)
- [ ] **BLOCK-05**: Mutation validates allocationDetails token matches hotel (prevents mixing rates)
- [ ] **BLOCK-06**: Mutation rejects if allocation already expired (< 1 minute remaining)

### Pre-Booking & Confirmation

- [ ] **BOOK-01**: GraphQL `createPreBooking` mutation accepts prebook_key, passenger details array (salutation, firstName, lastName, nationality, residenceCountry)
- [ ] **BOOK-02**: Validates all required passenger fields present and room count matches passenger count
- [ ] **BOOK-03**: Calls DOTW confirmBooking with passenger details and allocationDetails from prebook
- [ ] **BOOK-04**: Returns booking confirmation code from DOTW
- [ ] **BOOK-05**: Stores booking with all details in audit trail for reconciliation
- [ ] **BOOK-06**: Sends confirmation email to agent with booking code and itinerary details

### GraphQL API

- [ ] **GQL-01**: GraphQL endpoint authenticates company context (via auth token or request header)
- [ ] **GQL-02**: All GraphQL operations (searchHotels, getRoomRates, blockRates, createPreBooking) require valid company with DOTW credentials
- [ ] **GQL-03**: Queries/mutations return error with helpful message if company credentials missing or invalid
- [ ] **GQL-04**: GraphQL schema supports full DOTW filter vocabulary (flexible for future B2B integrations)
- [ ] **GQL-05**: API documentation in GraphQL schema (descriptions on all query/mutation fields)
- [ ] **GQL-06**: Rate limiting applied (100 requests/minute per company) to prevent API abuse

### B2C Markup

- [ ] **MARKUP-01**: Default 20% B2C markup applied to all fares in search/rate responses
- [ ] **MARKUP-02**: Admin can set custom markup percentage per company in credentials table
- [ ] **MARKUP-03**: Markup calculation shown in responses (original_fare + markup_percentage = final_fare)
- [ ] **MARKUP-04**: Markup applied consistently across search, rates, and blocking operations

### Error Handling & Logging

- [ ] **ERROR-01**: Invalid DOTW credentials trigger 401 error with "DOTW credentials expired or invalid"
- [ ] **ERROR-02**: DOTW API timeout (> 120 seconds) handled with retry once, then error response
- [ ] **ERROR-03**: Allocation expiry (< 1 min remaining) prevents booking, prompts to re-search
- [ ] **ERROR-04**: Missing required passenger data caught with field-level error messages
- [ ] **ERROR-05**: All DOTW API requests/responses logged to 'dotw' channel (no credentials in logs)
- [ ] **ERROR-06**: DOTW API errors mapped to user-friendly messages (e.g., "No availability for selected dates")
- [ ] **ERROR-07**: Circuit breaker protects against DOTW API outages (graceful degradation)

### B2B Flexibility

- [ ] **B2B-01**: GraphQL API supports multiple room types in single search (flexible for complex packages)
- [ ] **B2B-02**: Filter support matches DOTW documentation (conditions, fieldName, fieldTest, fieldValues)
- [ ] **B2B-03**: API designed for B2B integrations (partners can use GraphQL directly)
- [ ] **B2B-04**: Multi-company isolation ensures credentials/data never cross-contaminate

## v2 Requirements

Deferred to future release. Not in current roadmap.

### Task & Invoice Integration

- **TASK-01**: Booking confirmation auto-creates hotel task with DOTW booking details
- **TASK-02**: Hotel task feeds into bulk invoice upload workflow
- **TASK-03**: Invoice generated with DOTW booking code and rate breakdown

### Advanced Features

- **ADV-01**: Rate history tracking (price trends for dates/hotels)
- **ADV-02**: Favorite hotels/searches for repeat customers
- **ADV-03**: Booking amendments (date changes, passenger changes with DOTW)
- **ADV-04**: Cancellation management (calculate refund from DOTW policy)

### Integrations

- **INT-01**: Sync bookings with calendar/CRM systems via webhook
- **INT-02**: Email integration (confirmation templates customizable per company)
- **INT-03**: WhatsApp notifications on booking confirmation

## Out of Scope

| Feature | Reason |
|---------|--------|
| Save Booking (non-refundable) | Requires additional DOTW operation and state management; v2 feature |
| Booking amendments | Complex state management; defer to v2 |
| Rate history/analytics | Nice-to-have; v2 feature |
| Cancellation refund calculation | Requires additional DOTW operation; v2 feature |
| Payment capture | Already handled by existing payment gateway system, not DOTW responsibility |
| SMS notifications | WhatsApp preferred; SMS deferred to v2 |
| Real-time rate caching | Out of scope; API called on-demand for real-time rates |

## Traceability

| Requirement | Phase | Status |
|---|---|---|
| CRED-01 | Phase 1 | Pending |
| CRED-02 | Phase 1 | Pending |
| CRED-03 | Phase 1 | Pending |
| CRED-04 | Phase 1 | Pending |
| CRED-05 | Phase 2 | Pending |
| SEARCH-01 | Phase 2 | Pending |
| SEARCH-02 | Phase 2 | Pending |
| SEARCH-03 | Phase 2 | Pending |
| SEARCH-04 | Phase 2 | Pending |
| SEARCH-05 | Phase 2 | Pending |
| SEARCH-06 | Phase 2 | Pending |
| SEARCH-07 | Phase 2 | Pending |
| RATE-01 | Phase 3 | Pending |
| RATE-02 | Phase 3 | Pending |
| RATE-03 | Phase 3 | Pending |
| RATE-04 | Phase 3 | Pending |
| RATE-05 | Phase 3 | Pending |
| RATE-06 | Phase 3 | Pending |
| BLOCK-01 | Phase 3 | Pending |
| BLOCK-02 | Phase 3 | Pending |
| BLOCK-03 | Phase 3 | Pending |
| BLOCK-04 | Phase 3 | Pending |
| BLOCK-05 | Phase 3 | Pending |
| BLOCK-06 | Phase 3 | Pending |
| BOOK-01 | Phase 4 | Pending |
| BOOK-02 | Phase 4 | Pending |
| BOOK-03 | Phase 4 | Pending |
| BOOK-04 | Phase 4 | Pending |
| BOOK-05 | Phase 4 | Pending |
| BOOK-06 | Phase 4 | Pending |
| GQL-01 | Phase 2 | Pending |
| GQL-02 | Phase 2 | Pending |
| GQL-03 | Phase 2 | Pending |
| GQL-04 | Phase 2 | Pending |
| GQL-05 | Phase 2 | Pending |
| GQL-06 | Phase 2 | Pending |
| MARKUP-01 | Phase 1 | Pending |
| MARKUP-02 | Phase 1 | Pending |
| MARKUP-03 | Phase 3 | Pending |
| MARKUP-04 | Phase 3 | Pending |
| ERROR-01 | Phase 2 | Pending |
| ERROR-02 | Phase 2 | Pending |
| ERROR-03 | Phase 4 | Pending |
| ERROR-04 | Phase 4 | Pending |
| ERROR-05 | Phase 1 | Pending |
| ERROR-06 | Phase 2 | Pending |
| ERROR-07 | Phase 2 | Pending |
| B2B-01 | Phase 2 | Pending |
| B2B-02 | Phase 2 | Pending |
| B2B-03 | Phase 2 | Pending |
| B2B-04 | Phase 1 | Pending |

**Coverage:**
- v1 requirements: 48 total
- Mapped to phases: 48 (pending phase creation)
- Unmapped: 0 ✓

---
*Requirements defined: 2026-02-21*
*Last updated: 2026-02-21 after initial definition*
