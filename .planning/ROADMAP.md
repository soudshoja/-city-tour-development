# Roadmap: Soud Laravel

## Milestones

- ✅ **v1.0 Bulk Invoice Upload** — Phases 1-4 (shipped 2026-02-13)
- ✅ **DOTW v1.0 B2B** — Phases 1-8 (shipped 2026-02-21)
- 📋 **Next Milestone** — TBD (`/gsd:new-milestone` to plan)

## Phases

<details>
<summary>✅ v1.0 Bulk Invoice Upload (Phases 1-4) — SHIPPED 2026-02-13</summary>

See `.planning/milestones/v1.0-ROADMAP.md` for full details.

- [x] Phase 1: Foundation — DB schema + model layer (2/2 plans)
- [x] Phase 2: Processing — Validation + preview workflow (3/3 plans)
- [x] Phase 3: Output — Invoice creation + PDF + email (2/2 plans)
- [x] Phase 4: Polish — UI hardening + history (3/3 plans)

</details>

<details>
<summary>✅ DOTW v1.0 B2B (Phases 1-8) — SHIPPED 2026-02-21</summary>

See `.planning/milestones/v1.0-ROADMAP.md` for full details.

- [x] Phase 1: Credential Management & Markup Foundation (2/2 plans) — completed 2026-02-21
- [x] Phase 2: Message Tracking & Audit Infrastructure (3/3 plans) — completed 2026-02-21
- [x] Phase 3: Cache Service & GraphQL Response Architecture (2/2 plans) — completed 2026-02-21
- [x] Phase 4: Hotel Search GraphQL (3/3 plans) — completed 2026-02-21
- [x] Phase 5: Rate Browsing & Rate Blocking (3/3 plans) — completed 2026-02-21
- [x] Phase 6: Pre-Booking & Confirmation Workflow (2/2 plans) — completed 2026-02-21
- [x] Phase 7: Error Hardening & Circuit Breaker (3/3 plans) — completed 2026-02-21
- [x] Phase 8: Modular Architecture & B2B Packaging (2/2 plans) — completed 2026-02-21

**Delivered:** 5-operation GraphQL API (getCities, searchHotels, getRoomRates, blockRates, createPreBooking), per-company encrypted credentials, 150s search cache, circuit breaker, Sanctum B2B auth, full audit trail linked to WhatsApp message IDs.

</details>

### 📋 Next Milestone (TBD)

Run `/gsd:new-milestone` to plan the next milestone.

Known candidates:
- DOTW booking → task/invoice integration
- Save Booking workflow (non-refundable)
- Cancellation and amendment workflows
- Admin API auth hardening (CRED-02 tech debt)

## Progress

| Milestone | Phases | Plans | Status | Shipped |
|-----------|--------|-------|--------|---------|
| v1.0 Bulk Invoice Upload | 4 | 10 | ✅ Complete | 2026-02-13 |
| DOTW v1.0 B2B | 8 | 20 | ✅ Complete | 2026-02-21 |
| Next | TBD | TBD | 📋 Planned | — |
