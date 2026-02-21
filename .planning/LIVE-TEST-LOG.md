# DOTW Live Test Log

End-of-milestone human verification checklist. Run these against a live server with real DOTW credentials before production deployment.

**Status:** Pending (approved for deferral 2026-02-21)

---

## Phase 4 — Hotel Search GraphQL

> Prerequisite: `company_dotw_credentials` row seeded for test company, `php artisan serve` running, GraphQL client (Insomnia/Postman/Altair) pointed at `/graphql`.

- [ ] **P4-1: End-to-end hotel search**
  - Call `searchHotels` with valid destination, checkin, checkout, rooms
  - Expected: `data.hotels[]` returns with `hotel_code` and `rooms[].room_types[]` each containing `markup { markup_percent, markup_amount, final_fare }`
  - Call again with identical input within 2.5 minutes
  - Expected: `meta.cached: true` on second call

- [ ] **P4-2: getCities lookup**
  - Call `getCities(country_code: "AE")`
  - Expected: `data.cities[]` with `code` and `name` fields, `meta.trace_id` present

- [ ] **P4-3: Unauthenticated guard**
  - POST `searchHotels` with no `Authorization` header
  - Expected: HTTP 200, `success: false`, `error.code: CREDENTIALS_NOT_CONFIGURED` — no 401, no PHP exception

- [ ] **P4-4: Currency omission (SEARCH-03)**
  - Call `searchHotels` without the `currency` field in input
  - Expected: call succeeds, DOTW responds with account-default currency — no PHP error

---

## Phase 5 — Rate Browsing & Rate Blocking

> *(To be filled when Phase 5 is verified)*

---

## Phase 6 — Pre-Booking & Confirmation

> *(To be filled when Phase 6 is verified)*

---

## Phase 7 — Error Hardening & Circuit Breaker

> *(To be filled when Phase 7 is verified)*

---

## Phase 8 — Modular Architecture & B2B Packaging

> *(To be filled when Phase 8 is verified)*
