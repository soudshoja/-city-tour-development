# Roadmap: DOTW v1.0 B2B

## Overview

Enable travel agents to search, browse, and book hotels via real-time DOTW API rates through a GraphQL API consumed by N8N workflows and Resayil WhatsApp integration. Per-company credential management, 2.5-minute search caching, rate blocking with 3-minute allocation expiry, and pre-booking confirmation workflow — all with full audit trail linking operations to WhatsApp conversations.

**Phases:** 8 (Phase 1 through Phase 8 — standalone DOTW module, independent numbering)
**Requirements:** 54 v1 requirements, 100% mapped
**Milestone:** DOTW v1.0 B2B (separate from soud-laravel v1.0 Bulk Invoice Upload)

---

## Execution Waves

### Wave 1 — Foundation (All 3 run in parallel)

- **Phase 1**: Credential Management & Markup Foundation
- **Phase 2**: Message Tracking & Audit Infrastructure
- **Phase 3**: Cache Service & GraphQL Response Architecture

### Wave 2 — Core Features (After Wave 1 complete, both run in parallel)

- **Phase 4**: Hotel Search GraphQL
- **Phase 5**: Rate Browsing & Rate Blocking

### Wave 3 — Booking, Hardening & Packaging (After Wave 2 complete, all 3 run in parallel)

- **Phase 6**: Pre-Booking & Confirmation Workflow
- **Phase 7**: Error Hardening & Circuit Breaker
- **Phase 8**: Modular Architecture & B2B Packaging

---

## Phases

- [ ] **Phase 1: Credential Management & Markup Foundation** - Per-company DOTW credential storage with encryption, 20% markup foundation, multi-company isolation
- [ ] **Phase 2: Message Tracking & Audit Infrastructure** - Resayil WhatsApp message_id and quote_id logging, full request/response audit trail
- [ ] **Phase 3: Cache Service & GraphQL Response Architecture** - 2.5-minute search result caching, unified GraphQL response wrapper, trace IDs
- [ ] **Phase 4: Hotel Search GraphQL** - searchHotels query with full DOTW filter vocabulary, destination/dates/rooms, cache integration
- [ ] **Phase 5: Rate Browsing & Rate Blocking** - getRoomRates query with cancellation policies, blockRates mutation with 3-minute allocation prebook tracking
- [ ] **Phase 6: Pre-Booking & Confirmation Workflow** - createPreBooking mutation with passenger validation, DOTW booking confirmation, error messaging
- [ ] **Phase 7: Error Hardening & Circuit Breaker** - Circuit breaker pattern, resilience handling, error logging to dotw channel
- [ ] **Phase 8: Modular Architecture & B2B Packaging** - Service modularity, composable GraphQL schema, deployment README, B2B extensibility verification

---

## Phase Details

### Phase 1: Credential Management & Markup Foundation

**Wave:** 1
**Depends on:** Nothing — Wave 1 start
**Goal:** Per-company DOTW credentials are stored encrypted in the database, resolved contextually by authenticated company, and markup percentage is configurable per company.

**Requirements:** CRED-01, CRED-02, CRED-03, CRED-04, CRED-05, MARKUP-01, MARKUP-02, ERROR-05, B2B-04

**Success Criteria:**
1. A new `company_dotw_credentials` table exists with company_id, encrypted dotw_username, encrypted dotw_password, dotw_company_code, and markup_percent fields.
2. Admin can store or update DOTW credentials for a company via API; credentials are never visible in plaintext in logs or responses.
3. DotwService resolves the correct company credentials when given a company authentication context — wrong credentials raise a clear "DOTW credentials not configured" error.
4. A missing passenger field during any DOTW operation returns a specific error naming the missing field (not a generic error).
5. Credentials from Company A are never accessible when operating in Company B context — isolation verified by attempting cross-company access.
6. Default 20% B2C markup is stored and applies automatically; admin can override markup percentage per company.

**Plans:** 2 plans

Plans:
- [ ] 01-01-PLAN.md — DB migration + CompanyDotwCredential model + DotwService refactor (DB-based credential resolution)
- [ ] 01-02-PLAN.md — Admin API endpoints (POST/GET /api/admin/companies/{id}/dotw-credentials) with field-level validation

---

### Phase 2: Message Tracking & Audit Infrastructure

**Wave:** 1
**Depends on:** Nothing — Wave 1 start (parallel with Phase 1)
**Goal:** Every DOTW GraphQL operation is linked to the originating Resayil WhatsApp message and logged with full request/response for debugging — without ever persisting credentials or sensitive details.

**Requirements:** MSG-01, MSG-02, MSG-03, MSG-04, MSG-05

**Success Criteria:**
1. A `dotw_audit_logs` table exists with company_id, resayil_message_id, resayil_quote_id, operation_type, request_payload, response_payload, and created_at.
2. Calling any DOTW GraphQL operation with X-Resayil-Message-ID and X-Resayil-Quote-ID headers results in an audit log row linking the operation to that WhatsApp conversation.
3. Audit log rows include full request and response payloads — sufficient to replay or debug a booking dispute.
4. Inspecting any audit log row reveals no DOTW credentials (dotw_username, dotw_password) and no sensitive passenger details in plaintext.
5. All four operation types (search, rates, block, book) produce audit log entries with correct operation_type label.

**Plans:** TBD

---

### Phase 3: Cache Service & GraphQL Response Architecture

**Wave:** 1
**Depends on:** Nothing — Wave 1 start (parallel with Phases 1 & 2)
**Goal:** Search results are cached per-company for 2.5 minutes using a deterministic cache key, and all GraphQL operations return a consistent response envelope with trace IDs, timing headers, and structured error shapes.

**Requirements:** CACHE-01, CACHE-02, CACHE-03, CACHE-04, CACHE-05, GQLR-01, GQLR-02, GQLR-03, GQLR-04, GQLR-05, GQLR-06, GQLR-07, GQLR-08

**Success Criteria:**
1. Calling searchHotels twice with identical destination/dates/rooms within 2.5 minutes hits the cache on the second call — the response includes `cached: true` and no DOTW API call is made.
2. Calling searchHotels with a different room configuration (adults/children/ages) generates a new cache key and hits the DOTW API fresh.
3. Cache from Company A is never returned to Company B (verified by using the same destination/dates but different company context).
4. Every GraphQL response — success or failure — contains: `success`, `data` or `error`, `timestamp`, `trace_id`, and `company_id` in meta.
5. Error responses include `error_code`, `error_message` (user-friendly), `error_details` (technical), and `action` hint (e.g., `retry_in_30_seconds`, `reconfigure_credentials`).
6. HTTP response headers include `X-Trace-ID` and `X-Request-Time-Ms` on every DOTW GraphQL operation.
7. The GraphQL schema has descriptions on all fields, input types, and enums — introspection returns documentation.

**Plans:** TBD

---

### Phase 4: Hotel Search GraphQL

**Wave:** 2
**Depends on:** Phase 1 (credentials), Phase 2 (audit logs), Phase 3 (cache + response envelope)
**Goal:** Agents can search hotels by destination, dates, and room configuration through the GraphQL API, with full DOTW filter support, cached results, and an audit trail entry per search.

**Requirements:** SEARCH-01, SEARCH-02, SEARCH-03, SEARCH-04, SEARCH-05, SEARCH-06, SEARCH-07, SEARCH-08, B2B-01, B2B-02, B2B-03

**Success Criteria:**
1. A `searchHotels` GraphQL query accepts destination (city code or name), check-in date, check-out date, room configuration (number of rooms, adults per room, children with ages), and optional currency.
2. Filters covering DOTW's full vocabulary are supported: rating, price range, property type, meal plan type, amenities, and cancellation policy type — not just a subset.
3. Response lists hotels with cheapest rate per meal plan per room type — each hotel includes hotel_code, name, city, star rating, location, image_url, and rates grouped by room type.
4. Multiple room types can be requested in a single search (e.g., 1 double + 1 twin) and results accommodate the configuration.
5. Room detail fields include all DOTW API fields — nothing is summarized away that DOTW returns.
6. Each search logs a row to `dotw_audit_logs` with the resayil_message_id, destination, and filters used; the GraphQL response reflects `cached: true/false` correctly.

**Plans:** TBD

---

### Phase 5: Rate Browsing & Rate Blocking

**Wave:** 2
**Depends on:** Phase 1 (credentials), Phase 2 (audit logs), Phase 3 (response envelope), Phase 4 (hotel selection flow)
**Goal:** Agents can retrieve detailed room rates for a specific hotel and lock in a rate for 3 minutes via blocking, with transparent markup applied and a prebook record created for booking reference.

**Requirements:** RATE-01, RATE-02, RATE-03, RATE-04, RATE-05, RATE-06, RATE-07, RATE-08, BLOCK-01, BLOCK-02, BLOCK-03, BLOCK-04, BLOCK-05, BLOCK-06, BLOCK-07, BLOCK-08, MARKUP-03, MARKUP-04, MARKUP-05

**Success Criteria:**
1. `getRoomRates` query accepts hotel_code, check-in/out dates, room config, and X-Resayil-Message-ID header; returns all room types with all meal plans and rates for that hotel.
2. Each rate includes: total_fare, tax, total_price, cancellation policy (refundable/non-refundable with fees and deadline), rate_basis code (BB/HB/FB/AI/ALL/RO), and the allocationDetails token.
3. Rate responses show: `{original_fare, markup_percent, markup_amount, final_fare}` — markup calculation is visible in every rate, consistently applied across operations for the same hotel+rate.
4. The WhatsApp-facing rate display shows the marked-up price (e.g., "100 KD → 120 KD after markup").
5. `blockRates` mutation locks a selected rate via DOTW (blocking=true) and creates a `dotw_prebooks` record with prebook_key (UUID), expires_at (now + 3 min), hotel details, and resayil_message_id.
6. `blockRates` returns countdown_timer_seconds (starting at ~180), expires_at timestamp, and full hotel/rate details — an expired or near-expired allocation (< 1 minute) is rejected with a prompt to re-search.
7. A new `blockRates` call from the same (company, WhatsApp user) automatically expires the previous prebook — only one active prebook exists per user.
8. All getRoomRates and blockRates operations log to `dotw_audit_logs`; blockRates log includes the prebook_key and allocation expiry.

**Plans:** TBD

---

### Phase 6: Pre-Booking & Confirmation Workflow

**Wave:** 3
**Depends on:** Phase 5 (blocked rates with prebook_key)
**Goal:** Agents can complete a hotel booking by submitting passenger details against a valid prebook_key, receiving a DOTW confirmation code and itinerary — with clear errors when the prebook has expired or required fields are missing.

**Requirements:** BOOK-01, BOOK-02, BOOK-03, BOOK-04, BOOK-05, BOOK-06, BOOK-07, BOOK-08, ERROR-03, ERROR-04

**Success Criteria:**
1. `createPreBooking` mutation accepts: prebook_key, a passengers array (salutation, firstName, lastName, nationality, residenceCountry, email), and X-Resayil-Message-ID header.
2. Passenger count is validated against the room configuration in the prebook; passenger emails are validated for format; each missing or invalid field returns a specific field-level error message.
3. An expired prebook_key (0 seconds remaining) is rejected with "Rate offer expired, please search again" before any DOTW API call is made.
4. On successful DOTW confirmation, the response includes booking_confirmation_code, booking_status, and itinerary_details; a `dotw_bookings` record is created with company_id isolation.
5. When a rate is no longer available at confirmation time, the response includes a specific error and suggests 3 alternative hotels with availability — not a generic failure.
6. When DOTW returns "hotel sold out," the response includes the error and 3 similar alternatives with availability.
7. Every booking attempt (success or failure) logs to `dotw_audit_logs` with confirmation_code (if obtained) and booking_status.

**Plans:** TBD

---

### Phase 7: Error Hardening & Circuit Breaker

**Wave:** 3
**Depends on:** Phase 3 (response envelope + error shapes), Phase 4 (search for fallback), Phase 6 (full operation surface complete)
**Goal:** The DOTW integration degrades gracefully under failure conditions — invalid credentials surface a clear configuration error, API timeouts give actionable messages, repeated failures trigger a circuit breaker that returns cached results or a friendly retry prompt.

**Requirements:** ERROR-01, ERROR-02, ERROR-07, ERROR-08

**Success Criteria:**
1. Calling any DOTW operation with no company credentials configured returns: "DOTW credentials not configured for this company" — not a stack trace or generic 500.
2. A DOTW API call exceeding 25 seconds returns "Search taking too long, please try again" — response includes `action: retry` for N8N workflow handling.
3. All errors are logged to the `dotw` Laravel logging channel; log entries never include DOTW credentials or full response bodies.
4. After 5 DOTW API failures within 1 minute, the circuit breaker activates: subsequent search requests return cached results if available, or return "Try again in 30 seconds" with `action: retry_in_30_seconds` — no further DOTW API calls during the open circuit window.

**Plans:** TBD

---

### Phase 8: Modular Architecture & B2B Packaging

**Wave:** 3
**Depends on:** Phase 1 (service exists), Phase 3 (GraphQL schema established), Phase 6 (all operations implemented)
**Goal:** The entire DOTW module can be copied to a production subdomain with config changes and migrations only — no tight coupling to the invoice/task system, with a README that documents every deployment step and environment variable.

**Requirements:** MOD-01, MOD-02, MOD-03, MOD-04, MOD-05, MOD-06, MOD-07, MOD-08, B2B-05

**Success Criteria:**
1. `app/Services/DotwService.php` contains all DOTW business logic with no imports from the invoice, task, or payment systems.
2. `config/dotw.php` contains no hardcoded server paths or environment-specific values — all configuration flows from `.env`.
3. DOTW migrations (named `dotw_*.php`) run cleanly on a fresh database without errors and are idempotent (running twice does not fail).
4. `graphql/dotw.graphql` is a standalone schema file that can be registered or deregistered in the Lighthouse config independently of other schemas.
5. DOTW models (`DotwPrebook`, `DotwBooking`, `DotwAuditLog`) use standard Eloquent patterns and have no foreign keys referencing invoices, tasks, or other non-DOTW tables.
6. A developer can copy the DOTW module to a new Laravel installation by: adding 4 env vars, running migrations, and registering the GraphQL schema — documented step-by-step in the README.
7. The API is usable by an external B2B partner consuming GraphQL — not just N8N — with clear schema documentation from introspection.

**Plans:** TBD

---

## Progress Table

| Phase | Name | Wave | Requirements | Status |
|-------|------|------|-------------|--------|
| 1 | Credential Management & Markup Foundation | Wave 1 | CRED-01..05, MARKUP-01/02, ERROR-05, B2B-04 | Planning complete |
| 2 | Message Tracking & Audit Infrastructure | Wave 1 | MSG-01..05 | Not started |
| 3 | Cache Service & GraphQL Response Architecture | Wave 1 | CACHE-01..05, GQLR-01..08 | Not started |
| 4 | Hotel Search GraphQL | Wave 2 | SEARCH-01..08, B2B-01..03 | Not started |
| 5 | Rate Browsing & Rate Blocking | Wave 2 | RATE-01..08, BLOCK-01..08, MARKUP-03..05 | Not started |
| 6 | Pre-Booking & Confirmation Workflow | Wave 3 | BOOK-01..08, ERROR-03/04 | Not started |
| 7 | Error Hardening & Circuit Breaker | Wave 3 | ERROR-01/02/07/08 | Not started |
| 8 | Modular Architecture & B2B Packaging | Wave 3 | MOD-01..08, B2B-05 | Not started |

---

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
| RATE-01 | Phase 5 | Pending |
| RATE-02 | Phase 5 | Pending |
| RATE-03 | Phase 5 | Pending |
| RATE-04 | Phase 5 | Pending |
| RATE-05 | Phase 5 | Pending |
| RATE-06 | Phase 5 | Pending |
| RATE-07 | Phase 5 | Pending |
| RATE-08 | Phase 5 | Pending |
| BLOCK-01 | Phase 5 | Pending |
| BLOCK-02 | Phase 5 | Pending |
| BLOCK-03 | Phase 5 | Pending |
| BLOCK-04 | Phase 5 | Pending |
| BLOCK-05 | Phase 5 | Pending |
| BLOCK-06 | Phase 5 | Pending |
| BLOCK-07 | Phase 5 | Pending |
| BLOCK-08 | Phase 5 | Pending |
| MARKUP-03 | Phase 5 | Pending |
| MARKUP-04 | Phase 5 | Pending |
| MARKUP-05 | Phase 5 | Pending |
| BOOK-01 | Phase 6 | Pending |
| BOOK-02 | Phase 6 | Pending |
| BOOK-03 | Phase 6 | Pending |
| BOOK-04 | Phase 6 | Pending |
| BOOK-05 | Phase 6 | Pending |
| BOOK-06 | Phase 6 | Pending |
| BOOK-07 | Phase 6 | Pending |
| BOOK-08 | Phase 6 | Pending |
| ERROR-03 | Phase 6 | Pending |
| ERROR-04 | Phase 6 | Pending |
| ERROR-06 | Phase 6 | Pending |
| ERROR-01 | Phase 7 | Pending |
| ERROR-02 | Phase 7 | Pending |
| ERROR-07 | Phase 7 | Pending |
| ERROR-08 | Phase 7 | Pending |
| MOD-01 | Phase 8 | Pending |
| MOD-02 | Phase 8 | Pending |
| MOD-03 | Phase 8 | Pending |
| MOD-04 | Phase 8 | Pending |
| MOD-05 | Phase 8 | Pending |
| MOD-06 | Phase 8 | Pending |
| MOD-07 | Phase 8 | Pending |
| MOD-08 | Phase 8 | Pending |
| B2B-05 | Phase 8 | Pending |

**Coverage:**
- Total requirements: 54
- Mapped: 54
- Unmapped: 0 ✓

---

*Roadmap created: 2026-02-21*
*Milestone: DOTW v1.0 B2B*
*Phases: 1-8 (standalone, independent of soud-laravel v1.0 Bulk Invoice Upload)*
