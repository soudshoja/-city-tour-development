# 16 — TravelERP Phase 1 Verification Findings + Corrected Prod Measurements

**Date:** 2026-08-24
**Source:** Phase 1 launch workflow (10 agents: 5 discover · 4 build · 1 adversarial verify by opus/xhigh) + lead-session direct re-measurement on live prod.
**Verdict from verifier:** **FIX-FIRST** — the entitlement + route-gating work is sound and kept, but 9 blockers stand between it and a client demo, four of them in files no builder was assigned (the seams between builders is where every leak sits).
**Context:** Phase 1 = make the 5-module package sellable with accounting HIDDEN (nav + routes blocked) while the ledger keeps auto-posting underneath. See `.planning/PLAN-TRAVELERP-LAUNCH.md`.

---

## A. Two numbers from the workflow were WRONG — corrected by direct measurement

The verifier relayed two alarming figures that did **not** survive re-measurement on live prod (`tour.citycommerce.group`, DB `citycomm_city-tour`, 2026-08-24). Recording the truth so neither figure gets re-quoted.

### A.1 "5,195 cross-tenant journal entries" → actually **3 lines**

Measured every plausible definition of cross-tenant contamination:

| Definition | Result |
|---|---|
| JE `company_id` ≠ owning account's `company_id` | **3 lines** — City Travelers entries posted into Ojeen's "Magic Holiday" accounts (#1199 code 5121 ×2, #1220 code 2151 ×1), Dr 141.000 / Cr 282.000, all dated 2025-09-19 |
| JE company ≠ its task's company | 0 |
| JE company ≠ its transaction's company | 0 |
| Accounts with NULL `company_id` | 0 (of 1,489) |
| JEs with NULL `company_id` | 0 |

The 5,195 figure was also arithmetically impossible: Ojeen holds 463 JEs total and Akeed holds 0, so companies 2+3 cannot contribute thousands of cross-tenant lines anywhere. **The unscoped-name-lookup RISK (audit Finding 14) is real and still worth fixing — but essentially nothing has leaked yet (3 lines).** Not an emergency; do not treat it as one.

### A.2 "~KWD 757K parked in suspense" → actually **~KWD 22,766 net**

Direct measurement of the `Suspense / Adjustments` (code 3900) accounts created by `AccountingRepair.php`:

| Company | Lines | Dr | Cr | Net |
|---|---|---|---|---|
| City Travelers | 3,676 | 284,295.105 | 261,529.088 | **22,766.017** |
| Ojeen Travel & Tourism | 3 | 0 | 138.600 | (138.600) |

Gross movement ~KWD 546K, but the actual unexplained **imbalance parked is ~KWD 22,766**, not 757K. Still needs the accountant's review — far smaller than reported. (The 757K appears to have conflated gross Dr+Cr across both companies.)

---

## B. The three prod companies (names, not ids)

| id | Name | Code | Live journal entries | Accounts | Note |
|---|---|---|---|---|---|
| 1 | **City Travelers** | CT00001 | 70,592 | 458 | the only real trading company; migration pilot target |
| 2 | **Ojeen Travel & Tourism Operations** | KWIKT2727 | 463 | 897 | over-provisioned COA (2× accounts on 0.6% of volume) |
| 3 | **Akeed** | AK | 0 | 134 | provisioned, never traded |

---

## C. The 9 blockers (verifier verdict: FIX-FIRST) — fix on dev, tick off here

| # | Blocker | File(s) | Status |
|---|---|---|---|
| 1 | **Entitlement is inert** — `ApplyCompanyModulePreset` is never called (0 call sites); prod has 0 `module.*` rows; `hasModule()` fails open → accounting is ON for everyone incl. new package clients. The phase cannot be demonstrated until company creation invokes the preset + an artisan retrofit command exists. | `app/Support/Entitlements/ApplyCompanyModulePreset.php` (uncalled) + `Company.php` | open |
| 2 | **Dashboard shows accounting panel to package clients** and renders "Error" in it — 4 accounting stat cards + Jazeera Credit card render for `hasRole('company')`/`accountant`, link to now-404 routes, JS catch writes "Error" into all five. | `resources/views/dashboard.blade.php:226,250-301,539` | open |
| 3 | **Unauthenticated `/api/*`** reads transactions + creates/deletes journal entries. `GET /api/transaction/{agentId}` (cross-tenant), `DELETE /api/invoice/delete/{id}` runs `JournalEntry::…->delete()`, `POST/PUT /api/invoice` writes JEs. Live on prod, no auth middleware at all. **DEFERRED by user** — revisit after the rest. | `routes/api.php` + `MobileController.php:166,668,780` | deferred |
| 4 | **Paying an invoice from client credit breaks for package clients** — `credits.useCreditNow` was gated `module:accounting`; it's a Payment-Gateway money flow. 4 invoice views POST to it → 404. Re-scope to `payment_gateway`. | `routes/web.php` credits group + `invoice/show*.blade.php`, `split*.blade.php` | open |
| 5 | **CRM client-credit panel breaks for package clients** — `credits.filter` similarly over-gated. | `routes/web.php` + 2 client views | open |
| 6 | **Mobile drawer**: package clients lose Task Report + Client Report; accounting links stay visible (drawer wasn't guarded like desktop `menu.blade.php`). | `resources/views/layouts/mobile-drawer.blade.php:117-123,192-207,245-264` | open |
| 7 | **`PaymentApplicationService` derives company from `Auth::user()`** → the bulk-invoice queue job (prod `QUEUE_CONNECTION=database`) 403s. This is P0 hotfix HF-1 regressing; fix = derive company from the invoice record. | `app/Services/PaymentApplicationService.php` | open |
| 8 | **DotwAI statement endpoint** serves journal entries behind phone-number-only auth (`X-DotwAI-Phone`, no token/signature/module gate). Not on prod yet (no `app/Modules/` there) → blocker for the branch before it ships, not a live incident. | `app/Modules/DotwAI/Http/Controllers/StatementController.php:44` | open |
| 9 | **~48 security Feature tests have never executed** and running them mutates a REAL database (`map_data_citytour` — the test suite's `mysql_map` connection has a pre-existing `Row size too large` migration failure). Until the phpunit map-DB override is fixed, the security tests are decorative. | `phpunit.xml` + `tests/Feature/Security/*` | open |

## D. Two GENUINE live prod security holes (separate from the gating work)

Verified directly against a control check (normal routes DO carry `Authenticate`; these do not):

1. **`receipt-voucher` group — 11 routes, no `auth`** incl. `POST store`, `PUT update/{id}`, `POST approve/{id}`, `POST import`. Anyone with the URL can create + approve receipt vouchers on the live site, unauthenticated.
2. **`bank-payments` group — 8 routes, no `auth`** incl. `POST store`, `PUT edit/{id}`.
3. (also) `export-tasks` unauthenticated.

These are **pre-existing on prod**, not introduced by Phase 1. Verifier + lead recommend hotfixing these on prod ahead of everything else — but per the staged plan, all fixes land on dev first, then dev→prod with user sign-off.

## E. What is CONFIRMED SAFE (verified, not assumed)

- **The silent ledger still posts** — every one of the 7 JE-writing controllers checked; none acquired a gate; every posting entry point sits on ungated routes. The "hide now, activate later with full history" strategy is intact at the gating level.
- **The 3 live companies are unaffected** — 0 `module.*` rows + fail-open default = no behavior change; verified against real prod users.
- **91 accounting routes genuinely gated** via resolved middleware (`route:list`, not the diff). GraphQL, Livewire, static exports checked clean. `SettingController` cannot self-grant the flag (keys regex-locked to `^notification\.`).
- Entitlement layer + route gating are good work — keep, extend at the seams.

## F. The real ledger risk (not that posting stops — WHERE it posts)

Verifier's sharpest point: the danger to "activate later with no migration" is **not** that posting halts, it's that a package client's entries land in the wrong company's chart of accounts via unscoped name-lookup resolution (the same Finding-14 pathology). Today that's 3 leaked lines (§A.1); at scale with many package clients it compounds daily. Fix ~1 hour (pin lookups to account code + the company's own receivable root); the decision on any accumulated residue is a business call. This is why PostingService + per-party accounts (files 11/13) should land before volume onboarding — restated in file 13 [AMENDMENT 7].

---

## G. Next actions (order)
1. (deferred) `/api/*` auth — per user.
2. Make entitlement real (blocker 1) — invoke preset at company creation + `modules:apply-preset` command.
3. Close the view seams (blockers 2, 6) + jazeeraCredit card.
4. Un-break the 3 package modules (blockers 4, 5, 6 reports wrapper).
5. Fix HF-1 company derivation (blocker 7).
6. Unblock + run the test suite (blocker 9).
7. Prod security hotfix (§D) — dev first, then dev→prod with sign-off.
8. COA-resolution fix (§F) — the 1-hour pin.
All on `development.citycommerce.group`. Prod untouched until dev→prod step with explicit user sign-off.

## D-bis. Third unauthenticated webhook found during spec revision (2026-08-24)

`WhatsappController::handleWebhook` (Meta Cloud API path, `routes/web.php:358`, registered with `->withoutMiddleware(['auth'])`) performs **zero signature verification** and calls an external download script keyed by an attacker-controlled `media_id`. Adds to §D's list (receipt-voucher 11 routes, bank-payments 8 routes, `export-tasks`) and to the 8 unauthenticated search/select routes at `routes/web.php:864-878` (which also target non-existent `InvoiceController` methods — dead AND unauthenticated).

Tracked in the product specs as corrections **RS-7** (HMAC on both inbound webhooks) and **PKG-6** (dead + unauth route cleanup). See `.planning/specs/PACKAGE-OVERVIEW.md` §6.

## H. Product-spec cross-reference (added 2026-08-24)

The five module specs + `PACKAGE-OVERVIEW.md` now live in `.planning/specs/`. Relevant to this file's blockers:
- Blockers 4/5/6 (features broken by wrong module gating) are resolved by ruling **R1** (client credits split by verb: CRM reads, Payment Gateway moves) and the finding that a working checkout path already exists (`invoice.client-credit` → `InvoiceController::createInvoiceLinkWithClientCredit`); only `credits.useCreditNow` is dead (its controller method does not exist).
- Blocker 1 (inert entitlement) is spec correction **PKG-2**; note the specs confirm `credits.filter` does not 404 anyone *today* because no explicit `module.*=false` rows exist yet — the breakage arrives the moment the preset starts being applied.
- New ruling **R8**: the Agent Profit "Total Loss" KPI tile is a named, documented exception — a ledger-sourced computed figure shown to clients, opaque, no drill-down. It is the ONLY place a journal-entry-derived number reaches a package-client surface.
- New correction **PKG-5**: `ChatController` (~1,270 lines, routes `chat.*` + `POST /api/chat/upload`, mounted live via `<livewire:chat />`) creates Clients/Agents/Branches, prices tasks and processes payments with no module gate — ruled **platform-operator-only**; needs an entitlement/role gate.

**Correction to §C context:** the Phase 1 discovery agents ran against the LOCAL checkout, which lacks prod's July/August features. Their "does not exist" claims were mostly WRONG — TaskActionRequest workflow, PNR reminders, price requests, email ingestion, WhatsApp ingestion, uploader heartbeats, company self-registration, `SupplierPdfDetector`, `TurkishNdcParser`, `MrzParser` all exist and are live on dev. Only the Resayil in-app drawer is genuinely absent, and `ProfitCalculationService` exists but is unwired. **Rule going forward: verify against the dev branch (`sync/prod-drift-reconciliation-2026-08-24`), never the local checkout alone.**
