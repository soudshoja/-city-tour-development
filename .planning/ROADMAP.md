# DOTW v1.0 B2B Roadmap

**Milestone:** DOTW v1.0 B2B (Hotel Booking Integration)
**Phases:** 5-12 (8 phases, continuing from v1.0 Bulk Invoice Upload)
**Depth:** Standard
**Coverage:** 54/54 v1 requirements mapped
**Updated:** 2026-02-21

## Phases

- [ ] **Phase 5: Credential Management & Database Setup** - Per-company DOTW credential storage with encryption and 20% markup foundation
- [ ] **Phase 6: Message Tracking & Audit Infrastructure** - Resayil WhatsApp message tracking with comprehensive audit logs
- [ ] **Phase 7: Hotel Search API & Caching** - GraphQL search endpoint with 2.5-minute result caching per destination/dates/rooms
- [ ] **Phase 8: Rate Browsing & Rate Blocking** - Room rates display and 3-minute allocation blocking with prebook tracking
- [ ] **Phase 9: Pre-Booking & Confirmation Workflow** - Passenger validation and DOTW booking confirmation with confirmation tracking
- [ ] **Phase 10: GraphQL Response Architecture & Error Handling** - Unified response structure, error codes, circuit breaker, and resilience patterns
- [ ] **Phase 11: Modular Architecture & B2B Extensibility** - Service modularity, composable schema, deployment documentation
- [ ] **Phase 12: Integration Testing & Deployment** - End-to-end testing, N8N workflow validation, production deployment

## Phase Details

### Phase 5: Credential Management & Database Setup

**Goal:** Companies can store and manage DOTW credentials securely with encryption and per-company isolation.

**Depends on:** Nothing (foundation phase)

**Requirements:** CRED-01, CRED-02, CRED-03, CRED-04, CRED-05, MARKUP-01, MARKUP-02, B2B-04, ERROR-05

**Success Criteria:**
1. Database migration creates `company_dotw_credentials` table with company_id, encrypted username/password, company_code, markup_percent (default 20)
2. Admin can create/update DOTW credentials via API endpoint (POST/PUT `/api/admin/companies/{id}/dotw-credentials`)
3. Credentials stored encrypted at rest using Laravel encryption (readable only via key rotation or admin decrypt)
4. DotwService reads credentials from database based on authenticated company_id with helpful error if missing
5. Missing credentials return 401 with actionable message: "DOTW not configured for your company. Contact admin to add credentials."
6. Custom markup percentage per company configurable via admin credentials UI (default 20%)
7. Credentials never appear in logs, errors, or audit trails (sanitized output)

**Plans:** TBD

**Parallel:** Can execute immediately alongside Phase 6

**Blocking:** Phase 7, 8, 9 (all search/rate/booking operations need credentials)

---

### Phase 6: Message Tracking & Audit Infrastructure

**Goal:** Every DOTW operation links to originating WhatsApp message for full conversation context.

**Depends on:** Nothing (foundation phase)

**Requirements:** MSG-01, MSG-02, MSG-03, MSG-04, MSG-05, GQLR-05, GQLR-06

**Success Criteria:**
1. Database migration creates `dotw_audit_logs` table with company_id, resayil_message_id, resayil_quote_id, operation_type, request_payload, response_payload, created_at
2. GraphQL context middleware extracts X-Resayil-Message-ID and X-Resayil-Quote-ID headers from every request
3. Every GraphQL operation (searchHotels, getRoomRates, blockRates, createPreBooking) captures headers and logs to audit_logs table
4. Audit log entries include full request payload and response payload (stripped of sensitive details like passwords)
5. Response headers include X-Trace-ID (UUID generated per request) and X-Request-Time-Ms (operation duration)
6. Audit logs never contain encrypted credentials, full passenger details, or sensitive payment information (sanitized)
7. Agents can query audit logs by resayil_message_id to review booking conversation history

**Plans:** TBD

**Parallel:** Can execute immediately alongside Phase 5

**Blocking:** Phase 7, 8, 9 (audit logging required in all operations)

---

### Phase 7: Hotel Search API & Caching

**Goal:** Agents can search hotels by destination/dates with live DOTW rates cached for 2.5 minutes.

**Depends on:** Phase 5 (credentials), Phase 6 (audit logging)

**Requirements:** SEARCH-01, SEARCH-02, SEARCH-03, SEARCH-04, SEARCH-05, SEARCH-06, SEARCH-07, SEARCH-08, CACHE-01, CACHE-02, CACHE-03, CACHE-04, CACHE-05, GQL-01, GQL-02, GQL-03, ERROR-01, ERROR-02, ERROR-06, ERROR-07, B2B-01, B2B-02, B2B-03

**Success Criteria:**
1. GraphQL `searchHotels` query accepts: destination (city code/name), check-in date, check-out date, room configuration (rooms, adults/children per room), currency, filters (rating, price range, property type, meal plan, amenities, cancellation policies), resayil_message_id header
2. Query returns hotels with cheapest rate per meal plan per room type from DOTW searchhotels response with hotel code, name, city, rating, location, image_url
3. Cache key includes company_id + destination + dates + rooms_hash to prevent cross-company data leakage; results cached for 2.5 minutes
4. Subsequent searches for same parameters return cached results with `cached: true` flag in response
5. All DOTW filter vocabulary supported (not hardcoded common filters) for B2B flexibility
6. Response includes company context (company_id, request_id) and X-Trace-ID header for debugging
7. Audit log captures search with resayil_message_id, destination, filters used, result count
8. DOTW API timeout (>25 sec) returns error: "Search taking too long, please try again" allowing N8N retry
9. Invalid credentials return 401: "DOTW credentials not configured for this company"
10. Hotel sold out returns error with 3 alternative suggestions

**Plans:** TBD

**Parallel:** Depends on Phase 5 & 6 completion; Phase 8 can start after Phase 7 begins

**Blocking:** Phase 8 (rates depend on hotel selection)

---

### Phase 8: Rate Browsing & Rate Blocking

**Goal:** Agents can view all room rates with cancellation policies and lock rates for 3 minutes before booking.

**Depends on:** Phase 7 (search completed for hotel selection)

**Requirements:** RATE-01, RATE-02, RATE-03, RATE-04, RATE-05, RATE-06, RATE-07, RATE-08, BLOCK-01, BLOCK-02, BLOCK-03, BLOCK-04, BLOCK-05, BLOCK-06, BLOCK-07, BLOCK-08, MARKUP-03, MARKUP-04, ERROR-03

**Success Criteria:**
1. GraphQL `getRoomRates` query accepts: hotel_code, check-in/check-out dates, room configuration, resayil_message_id header and returns all room types with all meal plans, rates, and cancellation policies
2. Each rate includes: total_fare, tax, total_price, cancellation_policy (refundable/non-refundable with fees), original_currency, exchange_rate, final_currency with 20% B2C markup applied
3. Markup calculation transparent in response: {original_fare: 100, markup_percent: 20, markup_amount: 20, final_fare: 120}
4. Each rate tagged with rate_basis code (1331=BB, 1332=HB, 1333=FB, 1334=AI, 1335=ALL, 1336=RO)
5. Response includes `allocationDetails` token (opaque string required for blocking and confirmation)
6. GraphQL `blockRates` mutation accepts: hotel_code, dates, room_config, selected_room_type, selected_rate_basis, allocationDetails token, resayil_message_id and locks rate for 3 minutes
7. Validates allocationDetails token matches selected hotel (prevents token mixing)
8. Creates `dotw_prebooks` record with: prebook_key (UUID), allocation_details, hotel details, room_type, rates, is_refundable, expired_at (now + 3 min), resayil_message_id
9. Returns: prebook_key, hotel details, selected rates, countdown_timer_seconds (180, 179, ..., 0), expires_at timestamp
10. Rejects if allocation <1 minute remaining with message: "Rate offer expired, please search again"
11. Only one active prebook per (company, WhatsApp user) allowed (new prebook expires previous)
12. Rate no longer available returns: "This hotel/rate no longer available" with 3 alternative suggestions
13. Audit logs capture rate browsing operations with hotel_code, rates returned count, and blocking operations with prebook_key, allocation_expiry

**Plans:** TBD

**Parallel:** Can execute after Phase 7 begins; Phase 9 can start after Phase 8 rate locking works

**Blocking:** Phase 9 (pre-booking needs locked rates/prebooks)

---

### Phase 9: Pre-Booking & Confirmation Workflow

**Goal:** Agents can submit passenger details and receive confirmed booking with confirmation code.

**Depends on:** Phase 8 (prebook/rate blocking completed)

**Requirements:** BOOK-01, BOOK-02, BOOK-03, BOOK-04, BOOK-05, BOOK-06, BOOK-07, BOOK-08, ERROR-03, ERROR-04, ERROR-05, ERROR-06

**Success Criteria:**
1. GraphQL `createPreBooking` mutation accepts: prebook_key, passengers array (salutation, firstName, lastName, nationality, residenceCountry, email), resayil_message_id header
2. Validates all required passenger fields present, passenger count matches room configuration, email format valid
3. Validates prebook_key exists and not expired (>0 seconds remaining) with error: "Rate offer expired, please search again"
4. Calls DOTW confirmBooking with passenger details, allocationDetails, and email
5. On success: Returns booking_confirmation_code, booking_status, itinerary_details with clarity on refundability and cancellation deadlines
6. Creates `dotw_bookings` record: confirmation_code, prebook_key, passengers (hashed, not stored plaintext), booking_status, hotel_details, resayil_message_id, company_id
7. Audit log captures confirmation_code, booking_status, and booking details (sanitized)
8. On failure: Returns specific error (rate no longer available, hotel sold out, DOTW system error) with helpful message for N8N workflow decision tree
9. All booking confirmations include reference code for audit trail linking back to WhatsApp conversation
10. Passengers stored securely (hashed or encrypted) in dotw_bookings, never in logs

**Plans:** TBD

**Parallel:** Executes after Phase 8; can run alongside Phase 10

**Blocking:** None (Phase 12 integration testing)

---

### Phase 10: GraphQL Response Architecture & Error Handling

**Goal:** Unified response format, comprehensive error codes, and circuit breaker resilience across all operations.

**Depends on:** Phase 7+ (operations exist to implement response wrapper)

**Requirements:** GQLR-01, GQLR-02, GQLR-03, GQLR-04, GQLR-05, GQLR-06, GQLR-07, GQLR-08, ERROR-01, ERROR-02, ERROR-04, ERROR-07, ERROR-08

**Success Criteria:**
1. All GraphQL responses return standardized structure: success (boolean), data (if successful), error (if failed), timestamp, trace_id
2. Error responses include: error_code (machine-readable), error_message (user-friendly for N8N), error_details (technical), action (retry/cancel/reconfigure)
3. GraphQL schema self-documented with descriptions on all fields, input types, enums for B2B clarity
4. All operations support synchronous calls (user waits for response in WhatsApp conversation, no async callbacks)
5. Response includes company context in meta (company_id, request_id) and headers (X-Trace-ID, X-Request-Time-Ms)
6. Error responses include action hints for N8N workflows: "retry_in_30_seconds", "reconfigure_credentials", "search_again"
7. Error codes standardized: CRED_MISSING (401), TIMEOUT (504), ALLOCATION_EXPIRED (410), RATE_UNAVAILABLE (404), VALIDATION_ERROR (400), DOTW_ERROR (502)
8. Circuit breaker pattern: If DOTW API fails 5x in 1 minute, return cached results (if available) or friendly "Try again in 30 seconds"
9. All errors logged to 'dotw' channel with sanitized details (no credentials, full responses, or sensitive passenger data)
10. Response consistent format across searchHotels, getRoomRates, blockRates, createPreBooking regardless of success/failure

**Plans:** TBD

**Parallel:** Can execute alongside Phase 7-9 (response wrapper applies to all)

**Blocking:** None (Phase 12 integration)

---

### Phase 11: Modular Architecture & B2B Extensibility

**Goal:** DOTW module decoupled and deployable independently with comprehensive documentation.

**Depends on:** Phases 5-10 (all features implemented)

**Requirements:** MOD-01, MOD-02, MOD-03, MOD-04, MOD-05, MOD-06, MOD-07, MOD-08, B2B-01, B2B-02, B2B-03, B2B-04, B2B-05

**Success Criteria:**
1. DOTW module lives in separate Laravel service (`app/Services/DotwService.php`) with no dependencies on invoice/task system
2. DOTW config file (`config/dotw.php`) is environment-agnostic with no hardcoded paths; all paths configurable via .env
3. DOTW migrations (`database/migrations/dotw_*.php`) are standalone and idempotent (can re-run without errors)
4. DOTW GraphQL schema (`graphql/dotw.graphql`) is modular and composable (can be included in main schema file)
5. DOTW models (`app/Models/DotwPrebook.php`, `DotwBooking.php`, `DotwAuditLog.php`) follow standard Eloquent patterns with relationships
6. DOTW module decoupled from invoice/task system (can be developed independently for v2 integration later)
7. Entire DOTW module copyable to production with: config changes + environment variables + migrations + service + GraphQL schema
8. README documents deployment: environment variables needed (DOTW_API_URL, DOTW_TIMEOUT), migration steps, GraphQL schema registration
9. GraphQL schema supports all DOTW V4 filter vocabulary (not hardcoded to common filters)
10. Room details include all DOTW fields (not summarized) allowing detailed negotiation for B2B partners
11. Multi-company credential isolation enforced at database + service layer (credentials never leak between companies)
12. API designed for external B2B partners (N8N/Resayil primary consumer, extensible for future partners)

**Plans:** TBD

**Parallel:** Can start in Phase 7; finalized in Phase 11

**Blocking:** Phase 12 (deployment)

---

### Phase 12: Integration Testing & Deployment

**Goal:** End-to-end testing validates full booking workflow; N8N integration ready; deployment to production.

**Depends on:** Phases 5-11 (all features complete)

**Requirements:** All 54 requirements verified through integration tests

**Success Criteria:**
1. End-to-end tests cover complete workflows: search → rates → block → confirm with real DOTW test credentials
2. N8N workflow templates provided for: searchHotels, getRoomRates, blockRates, createPreBooking (with error handling)
3. Load testing validates 100 concurrent searches without timeout or cache invalidation
4. Response time validated: search <10sec, rates <5sec, block <5sec, confirm <10sec (including DOTW API delay)
5. Security audit: credentials encrypted, audit logs sanitized, no plaintext passwords in logs/responses
6. Database migrations run cleanly on production schema (no conflicts with existing tables)
7. GraphQL schema registered in live Lighthouse (queries + mutations functional)
8. Deployment checklist completed: environment variables set, migrations run, cache cleared
9. Smoke tests pass: search returns results, rates show markup correctly, blocks expire after 3 min, confirmations create bookings
10. Documentation complete: API guide for N8N, troubleshooting guide, credential management workflow

**Plans:** TBD

**Parallel:** None (final integration phase)

**Blocking:** Release gate

---

## Parallel Execution Strategy

### Wave 1 (Start Immediately)
- **Phase 5:** Credential Management & Database Setup
- **Phase 6:** Message Tracking & Audit Infrastructure

**Rationale:** Both are foundational infrastructure with no dependencies. Can be developed in parallel by independent developers/Claude instances.

### Wave 2 (After Wave 1 Ready)
- **Phase 7:** Hotel Search API & Caching (depends on Phase 5 & 6)
- **Phase 10:** GraphQL Response Architecture & Error Handling (can parallelize with Phase 7 as response wrapper applies to all)
- **Phase 11:** Modular Architecture & B2B Extensibility (can start early to inform code structure)

**Rationale:** Phase 7 is the first user-facing feature requiring credentials + audit logs. Phase 10 can parallelize by implementing response wrapper while Phase 7 builds search logic. Phase 11 begins early to ensure modular architecture from start.

### Wave 3 (After Phase 7 Ready)
- **Phase 8:** Rate Browsing & Rate Blocking (depends on Phase 7)

**Rationale:** Rates depend on hotel selection from Phase 7.

### Wave 4 (After Phase 8 Ready)
- **Phase 9:** Pre-Booking & Confirmation Workflow (depends on Phase 8)

**Rationale:** Confirmations depend on blocked rates from Phase 8.

### Wave 5 (Final)
- **Phase 12:** Integration Testing & Deployment (depends on all phases)

**Rationale:** Full system test after all features complete.

---

## Progress Table

| Phase | Goal | Status | Plans Complete | Estimated Tasks |
|-------|------|--------|-----------------|-----------------|
| 5 | Credential Management & Setup | Not started | 0/3 | 8 |
| 6 | Message Tracking & Audit | Not started | 0/2 | 5 |
| 7 | Search API & Caching | Not started | 0/4 | 10 |
| 8 | Rate Browsing & Blocking | Not started | 0/3 | 9 |
| 9 | Pre-Booking & Confirmation | Not started | 0/2 | 8 |
| 10 | GraphQL Response & Error Handling | Not started | 0/2 | 6 |
| 11 | Modular Architecture & B2B | Not started | 0/2 | 5 |
| 12 | Integration Testing & Deployment | Not started | 0/3 | 8 |

**Total:** 8 phases, 19 planned plans, 59 estimated tasks

---

## Requirement Coverage

**Total v1 requirements:** 54
**Mapped to phases:** 54
**Coverage:** 100%

### Coverage by Category

| Category | Count | Phases |
|----------|-------|--------|
| Credentials (CRED) | 5 | Phase 5 |
| Message Tracking (MSG) | 5 | Phase 6 |
| Caching (CACHE) | 5 | Phase 7 |
| Search (SEARCH) | 8 | Phase 7 |
| Rates (RATE) | 8 | Phase 8 |
| Blocking (BLOCK) | 8 | Phase 8 |
| Pre-Booking (BOOK) | 8 | Phase 9 |
| GraphQL Response (GQLR) | 8 | Phase 10 |
| Markup (MARKUP) | 5 | Phase 5 + 8 |
| Error Handling (ERROR) | 8 | Phase 10 |
| Modular (MOD) | 8 | Phase 11 |
| B2B (B2B) | 5 | Phase 5 + 7 + 11 |

---

**Last updated:** 2026-02-21
**Status:** Ready for planning
