# 15 — Travel Data Capture & Industry Reports (Ingestion-Level Persistence · Backfill from AIR Archives · 14-Report Catalogue)

> **Amendments applied from doc 22 rev 3 (2026-08-27).** E12 (Stage F retitled: BSP reconciliation is REQUIRED, delivered in plan 11 §P7.5 — no longer a deferral), E13 (§B.5 default recorded: `ticket_stock.enabled = false`, contingent on §7 Q1's answer; the name marker stays open), E14 (§E report #8: the four dimensions decided — by customer, by airline, by agent/consultant, by service — in the detailed form only), E15 (§E report catalogue: BSP Reconciliation + BSP Summary/Sales Analysis added as Track 2 rows; counts corrected to 16 in scope — Track 1: 12, Track 2: 4), E16 (Stage G graph: a P7.5 node added downstream of Stage C and plan 11 §P5.3; the incentive-accrual annotation removed from file 13's branch), E17 (§E report #11: the accrual-posting half retargeted to P7.5, cleared against the BSP ACM). Source text: [22 — Plan Amendments](22-plan-amendments.md) §5.

**Target:** soud-laravel / citytourv2 lineage (`tour.citycommerce.group`, DB `citycomm_city-tour`; dev `development.citycommerce.group`).
**Date:** 2026-08-22.
**Position in the plan set:** execution plan for the travel-industry data layer from the blueprint (`.claude/skills/travel-accounting-system/references/04-travel-industry.md` + `05-reporting.md` §4). The core insight this plan is built on: **every travel report in the catalogue depends on what the AIR-file parser and Task Uploader capture and persist at ingestion.** Today the parser *reads* the K-line (fare + per-tax-code amounts) and the itinerary segment lines, but the Task flattens everything into cost/price totals — the structure the reports need is parsed and then thrown away. The fix is capture-first: new persistence tables at ingestion; the reports then become queries. Historical coverage comes from re-parsing the archived files under `storage/app/{company}/{supplier}/files_processed/`.
**Complexity key** (matches files 10/11/13): **S** ≤ 2 dev-days · **M** ~1 week · **L** ~2–4 weeks · **XL** > 1 month.

> **Decision status:** Capture-first, additive-only, backfill-from-archives, and the two-track report split are decided — this document does not re-litigate them. **BSP reconciliation is REQUIRED, delivered in plan 11 §P7.5** (22 §5.1 E12: the owner has since decided ADM/ACM ingestion is required, which drags the rest of BSP with it — no longer a deferral) — see Stage F below and [11](11-technical-implementation-plan.md#p75). Open decisions are marked **[USER-DECIDE]** (table names, module/command names, one cabin-mapping scope question), per the standing rule to never invent names.

---

## 0. What this document adds — and the two rules everything obeys

| Rule | Meaning in practice |
|---|---|
| **Additive only** | New tables *beside* `tasks` / `task_flight_details` / `task_hotel_details`; the parser gains new extract methods and one new persistence call, but every existing field keeps flowing exactly as today. Zero behavior change to the existing task-creation flow — a capture failure must never fail a task (`try/catch` around the capture write, error logged, task proceeds). |
| **Two-track shipping** | **Track 1 (operational reports)** read the new capture tables + existing task tables — *never* the GL. They do **not** depend on PostingService (file 13 Stage A / file 11 §P1) and ship with the 5-module go-live package (Task Uploader is one of the 5 modules going to market). **Track 2 (GL tie-out reports)** reconcile fare/tax/commission to ledger balances and wait for the accounting chain (file 13 Stages A–E). The split is explicit per report in Stage E. |

**Prerequisite gate (stated once, applies to Stages C and D):** production's `AirFileParser.php` has diverged from every repo via SSH hotfixes (`14-prod-drift-findings-2026-08.md` §4: multiple Jul/Aug 2026 `.bak` files for `AirFileParser.php`, `SupplierPdfDetector.php`, `TurkishNdcParser.php` sit next to the live sources on prod). **All parser changes in this plan are built on the consolidated codebase** — the planned unified GitHub repo (separate initiative, not planned here). Until the prod hotfixes are merged into that repo, any parser edit made from the local checkout risks silently reverting a live ingestion fix. Stage C cannot start before this gate; Stages A, B, and E's report-shell work can.

---

## Stage A — Capture-gap audit results  ·  Complexity: **S** (done — this section *is* the deliverable; re-verify citations on the consolidated repo before Stage C)

Investigated 2026-08-22 on the local checkout: `app/Services/AirFileParser.php` (1,818 lines), `app/Schema/TaskSchema.php`, `app/Schema/TaskFlightSchema.php`, the `tasks` / `task_flight_details` migrations, and the persistence path `ProcessAirFiles::saveTask()` (`app/Console/Commands/ProcessAirFiles.php:1085`) → `TaskController::store` (`Task::create` at `TaskController.php:1085`, `saveFlightDetails()` at `:3452`, `createSingleFlightDetail()` at `:3477-3545`).

### A.1 What the parser already extracts (and where it goes)

| Data | Parser location | Persisted today? |
|---|---|---|
| Ticket number (T-K / T-E / R- / TMCD lines, raw string incl. 3-digit airline prefix) | `extractTicketNumber()` :330-355; per-passenger in `extractAllPassengers()` :1606-1805 | ✅ `tasks.ticket_number` (raw), `tasks.reference` = last 10 digits (:877-881), `task_flight_details.ticket_number` |
| PNR / record locator + airline reference | `extractGdsReference()` :361-368, `extractAirlineReference()` :373-381 | ✅ `tasks.gds_reference`, `tasks.airline_reference` |
| GDS office / creation + issuing office, IATA number | `extractCreatedBy()` :922-935, `extractIssuedBy()` :940-961, `extractIataNumber()` :966-991 — **note: `issued_by`/`iata` logic is hardcoded to offices containing `KWIKT`** (:952, :980) | ✅ `tasks.created_by`, `tasks.issued_by`, `tasks.iata_number` |
| Status: issued / reissued / void / refund / EMD (+ refund/void dates) | `extractStatus()` :389-410, `extractRefundDate()` :415-432, `extractVoidDate()` :437-457 | ✅ `tasks.status`, `refund_date`, `void_date` path |
| K-line fare: base, equivalent, total, currency-exchange 3-pair detection | `extractPrice()` :467-515, `extractTotal()` :630-684, `extractOriginalPrice()` :569-594, `extractBaseFare()` :1546-1561, `hasCurrencyExchange()` :1590-1600 | ✅ but **flattened**: `tasks.price/total/original_price/original_currency/exchange_currency` only |
| **Per-tax-code amounts** (KFTF / KNTI / KSTI / TAX- / KRF lines: each `(currency, amount, 2-char code)` tuple) | `extractTax()` :723-791 — **parses every individual tax tuple via `preg_match_all`, then sums them into one float and discards the structure** | ⚠️ only `tasks.tax` (one float) + `tasks.taxes_record` (the raw semicolon string blob, :797-843) — **no queryable per-code rows anywhere** |
| Itinerary segments (H-/U- lines): origin, destination, carrier 2-letter code, flight number, **booking-class letter** (`$flightMatch[4]` → `class_type`, :243), dep/arr datetimes, terminals, meal, baggage, stops, equipment | `extractFlightSegments()` :192-253 | ✅ one `task_flight_details` row **per segment per passenger-task** (`saveFlightDetails()` handles segment arrays, `TaskController.php:3452-3468`) — a de-facto segment table already exists; the gap is *columns*, not the table |
| Passengers (I- lines), per-passenger ticket + seat; one task per passenger | `extractAllPassengers()` :1606-1805, `extractSeatNumber()` :1492-1506 | ✅ `tasks` (one row per pax), `tasks.passenger_name` |
| Reissue/refund linkage (FO line, original T-/R- lines) | `extractOriginalTicketNumber()` :890-908 | ✅ `tasks.original_ticket_number`, `original_reference`, `original_task_id` |
| EMD documents with per-TMCD D-reference pricing | :1650-1759 | ✅ separate tasks with `status='emd'` |

### A.2 What the parser does NOT extract at all (confirmed by full-file read + grep: zero matches for FM/FT/commission/tour-code handling)

| Missing at parse time | AIR source line | Why it matters |
|---|---|---|
| **Airline commission %/amount** | **FM line** (e.g. `FM*C*5`) — never referenced | Daily-sales-with-commission, incentive report, and the blueprint's auto-invoice math (`Commission = ApplicableFare × AirlineComm%`, ref 04 §4) are impossible without it |
| **Tour code** | **FT line** (e.g. `FT*KU123ABC`) — never referenced | Tour Code Report (negotiated fares) has no data source |
| **Fare basis codes** | fare-basis fields of the K/L lines — never referenced. Worse: `extractFarebase()` :1156-1159 **returns `extractPrice()`** — so `task_flight_details.farebase` (a `float` column) stores a *price*, not a fare-basis code | Class/incentive analysis and deviation reports key on fare basis |
| **Passenger type (ADT/CHD/INF)** | I-line PTC suffix (e.g. `(INF…)`, `(CHD)`) — `extractAllPassengers()` captures name only (:1634) | **Infant handling in segment counting** (ref 05 §4: "GDS Segment Count … with infant handling"): infants get their own task + duplicated segment rows today, silently inflating every per-PNR segment count; GDS incentive schemes exclude or discount INF segments |
| **Form of payment** | FP line — never referenced (prod's `AssignTaskPaymentMethod.php` hotfix backfills `payment_method_account_id` by GDS issued-by code precisely because this is missing — file 14 §2) | Daily sales cash/credit split; BSP-cash vs BSP-credit later |
| **Coupon numbers / per-coupon status** | ticket coupon data — segments have no coupon identity, no flown/refunded/exchanged state | Refund/void reports at coupon grain; partial refunds |
| **Airline identity from ticket prefix** | first 3 digits of the serial (e.g. `229` = Kuwait Airways) — embedded in the raw `ticket_number` string but never split into its own field | The blueprint's airline resolution rule (ref 04 §1); `airlines.accounting_code` already exists in the master (`app/Models/Airline.php:17`) and is never matched against |
| **Validating carrier as a field** | A- line is parsed for the *name* only (`extractAirlineName()` :1322-1330 → stored as a string into segment `airline_id`) | Segment comparison per airline needs the code, resolved by FK not `LIKE` (`createSingleFlightDetail()` resolves airline by `name LIKE '%…%'`, `TaskController.php:3482,3510` — the FK columns `airline_id_new`/`airport_from_id`/`airport_to_id` added by migration `2026_01_28_150439` exist but the AIR path populates none of them) |

### A.3 Known parser data-quality hazards the later stages must design around

1. **Hardcoded year:** `convertDateFormat()` :278 stamps every segment date with year `'2025'`. Live ingestion in 2026 is already writing wrong-year departure dates; the Stage D backfill would replay the bug across all history. Fix belongs to Stage C (infer year from issued date / file timestamp); Stage D must not run before it.
2. **Hardcoded office/agency strings:** `KWIKT` (:952, :980), `'COMO TRAVEL AND TOURISM'` (`extractAgentName()` :999), `supplier_name = 'Amadeus'` (:1060), the ~40-airport `$IATA_TO_COUNTRY` map (:1332-1374). Single-tenant assumptions baked into a multi-tenant product — flagged here because the capture tables must not inherit them (Stage B resolves airports/airlines by FK against the real masters).
3. **No PCC validation:** the blueprint validates the file's PCC against an allowed list (ref 04 §3) so you only import your own sales. Prod already has a `company_gds_pccs` table (migration `2026_08_21`, prod-side — file 14 §3 finding 10; **not present in the local checkout**, another consolidation item). Stage C should validate against it once consolidated.

### A.4 Field-by-field capture-gap table (blueprint requirement → today → new home)

| # | Blueprint requirement (ref 04/05) | Parser extracts? | Persisted today? | New home (Stage B) |
|---|---|---|---|---|
| 1 | 13-digit ticket split: 3-digit airline prefix + serial + check digit; airline resolved via `airlines.accounting_code` | partial (raw string) | raw string only | fare-detail table (B.1): `airline_prefix`, `ticket_serial`, `validating_airline_id` FK |
| 2 | Per-segment carrier / class / route / dates | ✅ :192-253 | ✅ `task_flight_details` (strings; FKs unpopulated) | coupon table (B.2) linked 1:1 to `task_flight_details`, carrying carrier FK + coupon fields |
| 3 | Booking-class letter → cabin mapping per airline/region | class letter only | class letter string | class-map master (B.4); mapping applied at query time, never overwriting the raw letter |
| 4 | Commission % / amount (FM) | ❌ | ❌ | fare-detail table (B.1) |
| 5 | Tour code (FT) | ❌ | ❌ | fare-detail table (B.1), indexed |
| 6 | **Per-tax-code amounts** | parsed then summed away (:723-791) | float + string blob | **tax-lines table (B.3)** — the single highest-value capture in this plan |
| 7 | Fare basis per segment | ❌ (farebase column misused for price) | ❌ | coupon table (B.2) |
| 8 | PNR + office/PCC | ✅ | ✅ `tasks` | no change; PCC validation vs `company_gds_pccs` in Stage C |
| 9 | Passenger type ADT/CHD/INF | ❌ | ❌ | fare-detail table (B.1) `pax_type`; segment counts exclude/flag INF via join |
| 10 | Coupon status (issued/void/refunded/exchanged) | task-level status only | task-level | coupon table (B.2) `coupon_status`, updated by the same flows that flip `tasks.status` (additive listener, Stage C) |
| 11 | Form of payment (FP) | ❌ | `payment_method_account_id` set by other flows/hotfix | fare-detail table (B.1) `form_of_payment` raw + typed |
| 12 | Ticket stock ranges + missing-ticket detection | n/a (master data) | ❌ no table exists (grep: no stock/StockHeader anywhere) | stock table (B.5) |
| 13 | Airline segment targets per month | n/a (master data) | ❌ | targets table (B.6) |
| 14 | Per-tax-code **tax dimension for GCC** (Kuwait has no VAT today; expansion markets will) | n/a | ❌ | B.3's `tax_code` + nullable `tax_type` classification column — captured now, engine later |
| 15 | Hotel dual-currency supplier vs customer pricing | (hotel flow, not AIR) | ✅ mostly: `task_hotel_details` + `tasks.original_*`/`exchange_rate` | no new table; Hotel Voucher report reads existing columns |
| 16 | Reissue/exchange chain | ✅ FO line | ✅ `original_task_id` etc. | no new table; coupon table records `exchanged_from_ticket` for coupon grain |

---

## Stage B — Schema design: the capture tables  ·  Complexity: **M**

Design rules: every table carries `company_id` (FK, indexed — tenant isolation from birth, per `tenant-isolation-audit.md` lessons), `task_id` FK with `restrictOnDelete` semantics matching the soft-delete discipline of the task tables, timestamps + `softDeletes` (matching `2025_07_21` on the task family), and an idempotency-bearing unique key so Stage D can re-run forever. Names below are working labels — **[USER-DECIDE] every table name before the migration is written** (candidates offered; do not assume).

### B.1 Ticket fare detail — one row per task (per passenger-ticket)

**[USER-DECIDE] name** — candidates: `task_ticket_fares`, `ticket_fare_details`, `task_fare_captures`.

```php
Schema::create('«task_ticket_fares»', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies');
    $table->foreignId('task_id')->constrained('tasks');           // 1:1 with the passenger-task

    // Ticket identity, decomposed (blueprint ref 04 §1)
    $table->string('ticket_number_raw', 32)->nullable();          // as parsed (T-K229-…)
    $table->char('airline_prefix', 3)->nullable()->index();       // '229'
    $table->string('ticket_serial', 10)->nullable();              // last 10 digits
    $table->foreignId('validating_airline_id')->nullable()->constrained('airlines'); // resolved via airlines.accounting_code

    // Fare block (K-line, all three currency pairs — never just the flattened total)
    $table->char('fare_currency', 3)->nullable();                 // first pair (original)
    $table->decimal('base_fare', 12, 3)->nullable();
    $table->char('equiv_currency', 3)->nullable();                // middle pair when exchange present
    $table->decimal('equiv_fare', 12, 3)->nullable();
    $table->char('total_currency', 3)->nullable();                // final pair
    $table->decimal('total_amount', 12, 3)->nullable();
    $table->decimal('total_tax', 12, 3)->nullable();              // Σ of B.3 rows — denormalized check value

    // The three fields the parser never captured (A.2)
    $table->decimal('commission_pct', 6, 3)->nullable();          // FM line
    $table->decimal('commission_amount', 12, 3)->nullable();      // FM absolute, or derived
    $table->string('tour_code', 32)->nullable()->index();         // FT line
    $table->string('form_of_payment', 64)->nullable();            // FP line raw
    $table->string('fop_type', 16)->nullable();                   // cash | card | invoice | mixed (parsed classification)
    $table->string('pax_type', 8)->nullable();                    // ADT | CHD | INF | INS (I-line PTC)

    // Provenance (backfill + audit)
    $table->string('source_file', 255)->nullable();               // matches tasks.file_name
    $table->string('capture_source', 16)->default('parser');      // parser | backfill | manual
    $table->timestamps();
    $table->softDeletes();

    $table->unique('task_id');                                    // idempotency: one fare row per task, ever
    $table->index(['company_id', 'tour_code']);
    $table->index(['company_id', 'validating_airline_id']);
});
```

### B.2 Ticket coupons — one row per segment-coupon, 1:1 onto the existing segment rows

`task_flight_details` already holds one row per segment per passenger-task (A.1) — it *is* the segment table. Rather than widening it (the AI/PDF flows also write to it and its schema is shared with `TaskFlightSchema`), attach a sibling row keyed to it. **[USER-DECIDE] name** — candidates: `ticket_coupons`, `task_segment_coupons`, `flight_coupon_details`.

```php
Schema::create('«ticket_coupons»', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies');
    $table->foreignId('task_id')->constrained('tasks');
    $table->foreignId('task_flight_detail_id')->nullable()->constrained('task_flight_details'); // null for backfilled rows whose segment row can't be matched 1:1
    $table->unsignedTinyInteger('coupon_number')->nullable();     // 1..4 within the ticket
    $table->foreignId('marketing_airline_id')->nullable()->constrained('airlines'); // resolved by 2-letter code, FK not LIKE
    $table->char('carrier_code', 2)->nullable()->index();         // raw 2-letter, kept even when FK unresolved
    $table->char('booking_class', 2)->nullable()->index();        // the raw letter — never overwritten by cabin mapping
    $table->string('fare_basis', 16)->nullable();
    $table->string('coupon_status', 16)->default('issued');       // issued|void|refunded|exchanged|flown(unknown)
    $table->date('flight_date')->nullable()->index();             // segment date with CORRECT year (A.3 hazard 1)
    $table->char('origin', 3)->nullable();
    $table->char('destination', 3)->nullable();
    $table->string('exchanged_from_ticket', 32)->nullable();
    $table->string('capture_source', 16)->default('parser');
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['task_id', 'coupon_number']);                 // idempotency
    $table->index(['company_id', 'flight_date']);
    $table->index(['company_id', 'carrier_code', 'flight_date']); // segment-count query spine
});
```

Segment counting then reads: `count(ticket_coupons)` filtered by company/date/GDS, joined to B.1 for `pax_type` (exclude or bucket `INF` — the blueprint's "infant handling"), grouped by `tasks.gds_reference` for the per-PNR breakdown. The GDS dimension itself is derivable today (all AIR = Amadeus via `tasks.created_by` office format); a `gds` column on B.1 keeps it honest when Galileo/Sabre feeds arrive.

### B.3 Tax lines — one row per tax code per task (the tax dimension, captured now, engine later)

**[USER-DECIDE] name** — candidates: `task_tax_lines`, `ticket_tax_details`, `task_tax_breakdown`.

```php
Schema::create('«task_tax_lines»', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies');
    $table->foreignId('task_id')->constrained('tasks');
    $table->unsignedSmallInteger('sequence');                     // order within the K-tax line
    $table->char('tax_code', 2)->index();                         // YQ, YR, KW, G4, XT…
    $table->char('currency', 3);
    $table->decimal('amount', 12, 3);
    $table->string('source_line', 8)->nullable();                 // KFTF | KNTI | KSTI | TAX | KRF — which line it came from (issue vs refund provenance)
    $table->boolean('is_refund_withheld')->nullable();            // YQ/YR/YX retained on refund (today's refund_charge guesswork, done right)
    $table->string('tax_type', 24)->nullable();                   // future GCC classification (VAT/excise/govt-fee); NULL today — no tax engine invented here
    $table->string('capture_source', 16)->default('parser');
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['task_id', 'sequence']);                      // idempotency
    $table->index(['company_id', 'tax_code']);
});
```

Kuwait has no VAT today; GCC expansion makes the *dimension* mandatory now even though no tax engine exists. This table is that dimension: per-code amounts queryable from day one, `tax_type` classifiable later without re-parsing anything. `tasks.tax` and `tasks.taxes_record` continue to be written exactly as today (additive rule) — V-checks in Stage D reconcile `Σ task_tax_lines.amount ≈ tasks.tax` per task.

### B.4 Class map + incentive rules (masters, ref 04 §6) — **[USER-DECIDE] names**

Candidates: `airline_class_maps` (airline_id, region nullable, `booking_class` char(2), `cabin` enum first/business/premium/economy) and `airline_incentive_rules` (airline_id, region/destination nullable, booking_class nullable, `incentive_pct`, `valid_from`/`valid_to`). Small CRUD masters; the incentive report is `Σ(applicable fare × rule%)` over B.1/B.2 joins. Rates and class letters are airline commercial terms — **seed data comes from the user/accountant, never invented**.

### B.5 Ticket stock allocations (ref 04 §2) — **[USER-DECIDE] name + scope**

Candidates: `ticket_stock_allocations` (company_id, airline_id, `series_start`/`series_end` as the numeric serial range, `is_bsp` flag, allocated_at, notes). Missing-Ticket report = generate the range, left-join B.1 on `ticket_serial`, list holes; Stock/Sales/Voids = issued vs void/refund counts per airline from B.1 + `tasks.status`. **Scope question [USER-DECIDE]:** does the agency actually receive allocated paper/e-stock ranges today, or is all issuance BSP-electronic (in which case the missing-ticket control degrades to "gaps in the observed serial sequence per airline" — still useful, different semantics)? Decide before building the CRUD; the report works either way off B.1.

**Default recorded (22 §5.1 E13):** `ticket_stock.enabled = false` (doc 22 §4.4), deliverable = serial-gap detection — **contingent on §7 Q1's answer to this section's own scope question above.** This closes only the **scope default**; the **name marker** (`ticket_stock_allocations`?) stays open and is the owner's to pick, per the standing rule never to invent table names.

### B.6 Airline segment targets (ref 05 §4 comparison report) — **[USER-DECIDE] name**

Candidates: `airline_segment_targets` (company_id, airline_id, `period` year-month, `target_segments` int). One tiny CRUD; the comparison report is B.2 actuals vs this, variance %.

**Stage B exit gate:** all five migrations reviewed against the additive rule (no ALTER on existing task tables except — optionally and separately decided — populating the already-existing FK columns from `2026_01_28_150439`); names confirmed **[USER-DECIDE]**; migrations run on dev; Eloquent models + relations (`Task::ticketFare()`, `Task::taxLines()`, `Task::coupons()`, `TaskFlightDetail::coupon()`) merged with zero references from existing controllers.

---

## Stage C — Parser persistence wiring (additive)  ·  Complexity: **M–L**  ·  **Gated on repo consolidation (§0)**

### C.1 New extraction methods on `AirFileParser`

All pure additions — no existing method's return value changes:

| New method | Source lines in the AIR file | Feeds |
|---|---|---|
| `extractTaxLines(): array` | the same KFTF/KNTI/KSTI/TAX-/KRF regexes already inside `extractTax()` :723-791, returning the tuples instead of summing them | B.3 |
| `extractCommission(): ?array` | FM line (`pct` or absolute) | B.1 |
| `extractTourCode(): ?string` | FT line | B.1 |
| `extractFormOfPayment(): ?array` | FP line (raw + classified type) | B.1 |
| `extractPaxTypes(): array` | I-line PTC suffixes, per passenger index (INF/CHD detection) | B.1 |
| `extractFareBlock(): array` | the three K-line currency pairs (logic already exists across :467-684; returned structured instead of flattened) | B.1 |
| `extractCoupons(?int $paxIdx): array` | H-/U- lines (reuse `extractFlightSegments()` :192-253) + coupon numbering + fare-basis fields | B.2 |
| `decomposeTicketNumber(string): array` | prefix/serial split + `airlines.accounting_code` lookup *outside* the parser (parser stays DB-free; resolution happens in the capture service) | B.1 |

Plus the **year fix** (A.3 hazard 1): `convertDateFormat()` :258-284 infers year from the ticket's issued date (roll forward when the segment month precedes the issue month), replacing the hardcoded `'2025'`. This is the single sanctioned change to an existing method's output, because its current output is *wrong*; regression-test with fixture files from 2024/2025/2026 (`ManageAirFileTests` command already manages fixtures).

`parseTaskSchema()` gains one new top-level key per task — working name `capture` — carrying the structured block. `TaskSchema::normalize()` passes unknown keys through untouched for the AIR path (verify: normalization is schema-driven, so add `capture` to the schema with `default => null`; AI/PDF flows simply produce `null` and no capture rows are written — AIR-first scope).

### C.2 The capture writer — one service, called after task creation

**[USER-DECIDE] name** — candidates: `TravelCaptureService`, `TicketCaptureService`, `IngestionCaptureService`.

- Single entry point `captureForTask(Task $task, array $capture, string $source): void`, called from `ProcessAirFiles::saveTask()` (`:1085`) immediately after `TaskController::store` returns the created task — **not** inside `TaskController::store` itself, so the manual UI task-creation flow is untouched.
- Wrapped in `try/catch`: a capture failure logs to `air_processing.log` (existing `FileProcessingLogger`) and never fails the task (additive rule §0).
- All lookups by FK/code (`airlines.accounting_code`, `airlines.icao_designator`, `airports` for B.2), never `LIKE` name matching — do not replicate `createSingleFlightDetail()`'s `name LIKE '%…%'` pattern (`TaskController.php:3482`).
- Idempotent by construction: `unique(task_id)` on B.1, `unique(task_id, coupon_number)` on B.2, `unique(task_id, sequence)` on B.3 — `firstOrCreate`/upsert semantics so a re-processed file cannot duplicate rows.
- **Status propagation:** when existing flows flip `tasks.status` to void/refund (they already do — no change to them), a `TaskObserver::updated` listener (new, additive) mirrors the change onto `ticket_coupons.coupon_status`. Full-ticket only; per-coupon partial refunds remain a manual edit until a real coupon feed exists.
- PCC validation (A.3 hazard 3): once the consolidated repo carries prod's `company_gds_pccs` table, the service warns (not blocks) on files whose office code isn't registered for the company.

### C.3 Exit gate

- Repo-consolidation gate confirmed passed (prod parser hotfixes merged; this plan does not do the merging).
- Fixture regression suite green: every archived fixture type (issued, reissued, void, refund, EMD, multi-pax, currency-exchange, infant-carrying) produces (a) byte-identical `tasks`/`task_flight_details` writes vs the pre-change parser, and (b) the expected capture rows. (a) is the additive-rule proof.
- `Σ task_tax_lines.amount` vs `tasks.tax` and `base_fare + Σtax` vs `total_amount` asserted per fixture (tolerance 0.001, matching file 13's balance tolerance discipline).
- Deployed to dev, live-ingesting for ≥1 week before Stage D runs anywhere.

---

## Stage D — Historical backfill from `files_processed/` archives  ·  Complexity: **M**

Same discipline as file 13 Stage D: idempotency keys, dry-run default, resumable, verification counts. Different risk profile: this backfill only *adds* capture rows — it never touches `tasks`, `task_flight_details`, or any ledger row, so no maintenance window and no accountant sign-off gate; the gate is a verification-counts review.

### D.1 The command

`php artisan «travel:backfill-capture» {--company=} {--supplier=} {--dry-run : default true} {--batch-size=200} {--resume}` — **[USER-DECIDE] command name** (offer: `travel:backfill-capture`, `tasks:backfill-travel-capture`, `air:backfill-capture`).

Pipeline per archived file under `storage/app/{company}/{supplier}/files_processed/`:

1. **Parse** with the *consolidated* `AirFileParser` (Stage C version, year fix included) — read-only, file never moved.
2. **Match to live tasks** — strict resolution order, no guessing (the file 13 D.4 doctrine): (a) `tasks.file_name` equality (column exists, migration `2025_06_30_023706`) + company; (b) else `tasks.ticket_number` + company; (c) else `tasks.reference` (last-10) + `tasks.gds_reference` + company. Ambiguous (>1 match) or zero-match → worklist CSV, skip. Never match on client name or free text.
3. **Write** capture rows via the same `TravelCaptureService::captureForTask()` with `capture_source='backfill'` — one code path for live and backfill, exactly the file 13 B.5 "one code path ever creates X" principle.
4. **Journal** each file into a run table (working name `«capture_backfill_runs»` **[USER-DECIDE]**: file path hash, company_id, matched_task_ids JSON, status planned/done/skipped/error, batch uuid) — the resume + idempotency spine at the *file* level, on top of the row-level unique keys.

### D.2 Backfill-specific hazards (from the drift findings)

- **Prod parsed some files with hotfixed logic the repo never saw** (file 14 §4). The backfill re-parses with the consolidated parser, so a file whose live task was created by a hotfix variant may parse *differently* today. Rule: capture rows are written from the fresh parse, but every mismatch between fresh-parse totals and the live task's stored totals (`price`, `tax`, `total`, ticket number) is logged to a variance CSV — reviewed, not auto-reconciled. The live task is never edited by this command.
- **Wrong-year segment dates in live `task_flight_details`** (A.3 hazard 1) will disagree with the backfill's corrected `ticket_coupons.flight_date`. Expected and intended — the coupon table is the corrected record; log the count, don't "fix" the old rows here (a separate, optional repair of `task_flight_details.departure_time` years can be decided later **[USER-DECIDE: whether to repair old segment dates at all]**).
- **Archive coverage is not 100% of tasks** (manual/UI tasks, AI-parsed PDFs, emails have no AIR file). The coverage number itself is a Stage D deliverable, not a failure: capture tables will cover the AIR-ingested population; reports must state their basis (Stage E notes which reports tolerate partial coverage).

### D.3 Verification counts (the exit gate)

| # | Check |
|---|---|
| V1 | files scanned = planned + done + skipped + error, per company/supplier; error list reviewed |
| V2 | per company: `count(tasks where type='flight' and file_name is not null)` vs `count(«task_ticket_fares» where capture_source='backfill')` + unmatched worklist = 100% accounted |
| V3 | per task sampled (top-N + random, file 13 E.6 style): `Σ task_tax_lines.amount` within 0.001 of `tasks.tax`; `total_amount` within 0.001 of `tasks.total` — exceptions in the variance CSV with the D.2 hotfix-drift explanation |
| V4 | coupon count per task == `task_flight_details` row count for that task (or logged mismatch reason) |
| V5 | re-run of the full command is a no-op (0 new rows) — idempotency proof on staging before prod |
| V6 | dry-run on the dev/staging clone first; prod run only after V1–V5 pass there (file 13 D.6 protocol, minus the mysqldump/maintenance-window since no existing row is ever written) |

---

## Stage E — The report catalogue (16 in scope — Track 1: 12, Track 2: 4)  ·  Complexity: per report below; shell/framework **M**
*(Counts corrected — 22 §5.1 E15: BSP Reconciliation and BSP Summary/Sales Analysis are no longer deferred — see Stage F.)*

Shared first (the ref 05 §5 doctrine — build once): a report base that enforces company scope + branch/agent permission filters + date range + export (the Task Uploader module's existing listing filters are the starting point), and the **cabin-mapping join** (B.4) as one reusable query scope. Every report below is then a thin specialization.

**Track key:** **T1** = operational, reads capture + task tables only, ships with the 5-module go-live package. **T2** = GL tie-out, additionally reads `journal_entries`/`TrialBalanceService`, gated on file 13's chain (PostingService sole writer + per-party accounts). Priority = build order within its track.

| # | Report (ref 05 §4) | Reads | Track | Priority | Cx |
|---|---|---|---|:---:|:---:|
| 1 | **Daily Sales (ticket-wise)** — per-day tickets with fare/tax/commission breakdown | B.1 + B.3 + `tasks` | T1 | 1 | **S** |
| 2 | **GDS Segment Count** — by GDS/date, infant handling, per-PNR detail | B.2 + B.1 (`pax_type`) + `tasks.gds_reference` | T1 | 2 | **S** |
| 3 | **Refund Report** — refund transactions, penalty, withheld YQ/YR, payment status | `tasks` (status/refund_date/penalty_fee) + B.3 (`is_refund_withheld`) + B.1 | T1 (payment-status column upgrades in T2) | 3 | **S–M** |
| 4 | **Stock, Sales & Voids** — issued vs void/refund per airline, void % | B.1 + `tasks.status` (+ B.5 when decided) | T1 | 4 | **S** |
| 5 | **Tour Code Report** — sales by negotiated-fare tour code | B.1 (`tour_code`) + B.3 | T1 | 5 | **S** |
| 6 | **Airline / Hotel PNR Reports** — bookings by PNR | `tasks` + B.2 / `task_hotel_details` | T1 | 6 | **S** |
| 7 | **Hotel Voucher Report** — bookings, nights, supplier vs customer pricing, dual currency | `task_hotel_details` + `tasks` (original_* + exchange_rate) — **no new capture needed** | T1 | 7 | **S** |
| 8 | **Management Sales variants** — **dimensions decided (22 §5.1 E14, D9): by customer, by airline, by agent (blueprint: consultant — same dimension, one word kept in the title), by service — in the `detailed` form only.** Comparison, variance and top-N are terminal presentation forms and build on request. | `tasks` + B.1 + masters | T1 | 8 | **M** |
| 9 | **Airline Segment Comparison** — actual vs target/budget, variance % | B.2 + B.6 | T1 | 9 (needs B.6 CRUD + user-entered targets) | **S** |
| 10 | **Missing Ticket Report** — serial-range holes | B.1 + B.5 | T1 | 10 (blocked on B.5 scope decision) | **S–M** |
| 11 | **Airline Incentive Report** — accrued override incentive by airline/class/region | B.1 + B.2 + B.4 rules | T1 (accrual *display*); **the accrual *posting* is P7.5, cleared against the BSP ACM (D5) — retargeted from "T2 via PostingService as a feeder", 22 §5.1 E17** | 11 (needs B.4 seeded by user) | **M** |
| 12 | **Deviation / Discrepancy** — exceptions (e.g. fare vs sell deviations, capture-vs-task variances from D.2's CSV productized) | B.1 + `tasks` + variance rules | T1 | 12 | **M** |
| 13 | **Daily Sales — GL tie-out variant** — #1 reconciled line-by-line to posted revenue/tax/payable | #1 sources + `journal_entries` via `TrialBalanceService` discipline | **T2** | after file 13 Stage A/B gates | **M** |
| 14 | **Sales-vs-Ledger tie-out / management pack** — fare/tax/commission totals vs GL account balances per period | capture tables + GL | **T2** | last | **M** |
| 15 | **BSP Reconciliation** (05-35) — added, no longer deferred (22 §5.1 E15) | B.1 (ticket decomposition) + `bsp_statement_lines` (plan 11 §P7.5) | **T2** | gated on P7.5 | **M** |
| 16 | **BSP Summary / Sales Analysis** (05-36) — added, no longer deferred (22 §5.1 E15) | B.1 + `bsp_statement_lines` (plan 11 §P7.5) | **T2** | gated on P7.5 | **M** |

Notes: (a) every T1 report states its data basis ("AIR-ingested tasks; coverage NN% per Stage D V2") until coverage is complete; (b) nothing in T1 reads `journal_entries` — that is the enforcement line keeping this plan parallel to file 13; (c) cargo reports from the blueprint catalogue are omitted — no cargo business line exists in this product today (add only when one does — scope, not name, so no marker needed).

**Stage E exit gate (T1):** reports 1–8 live behind the Task Uploader module for pilot companies; per-report spot-check against a hand-computed day signed off by the user; T2 reports explicitly absent from menus until file 13's Stage E sign-off exists.

---

## Stage F — BSP reconciliation: REQUIRED, delivered in plan 11 §P7.5

*(Retitled — 22 §5.1 E12: the owner has since decided ADM/ACM ingestion is required, which drags the rest of BSP with it — an ADM you cannot match to a statement line is a letter, not a control. This is no longer a deferral.)* BSP reconciliation (ref 04 §5) is built in **plan 11 §P7.5** — see [11](11-technical-implementation-plan.md#p75) and [22](22-plan-amendments.md) §3. For the record, it additionally needs — and *only* additionally needs:

1. **BSP/HOT statement file ingestion** — a parser + staging table for the billing statement (per-ticket: fare cash/credit, tax, commission, incentive, `BSPTYPE` ET/ADM/ACM/REFUND/EMD), analogous to the blueprint's `tblBspReconciliation`.
2. A **matching report** joining that staging table to B.1 on `ticket_serial` — which is precisely why B.1 stores the decomposed 13-digit serial now.
3. **ADM/ACM memo posting** through PostingService (a file 13 / file 11 §P5 memo-module concern, not a capture concern).

Nothing in Stages A–E blocks any of this: B.1's ticket decomposition, B.3's tax lines, and B.1's commission/FOP fields are exactly the "your side" of the ticket-by-ticket match. The only rule: when BSP starts, it gets its own staging tables — it must not write into the capture tables, which record what *we* parsed from *our* documents.

---

## Stage G — Dependency graph, tied into file 13's Stage G

```
Repo consolidation (separate initiative; file 14 §4 parser drift)   ← GATE for C, D
  └─> Stage C  parser extraction + TravelCaptureService (dev-soak ≥1 wk)
        ├─> Stage D  backfill from files_processed/ archives (V1–V6)
        │     └─> Stage E TRACK 1 reports (#1–#12)
        │           └─> ships WITH the 5-module go-live package
        │               (Task Uploader module carries them; NO PostingService dependency)
        └─> plan 11 §P5.3 ──┐
                             └─> plan 11 §P7.5  BSP reconciliation + ADM/ACM
                                     └─> Stage E TRACK 2 here (#13–#16, incl. BSP Reconciliation
                                         #15 and BSP Summary #16 — 22 §5.1 E15/E16)

Stage A (audit — done)  ─┐
Stage B (capture tables) ┴─ can run NOW, in parallel with file 13's P1/P2 —
                            no shared tables, no JE writes, no COA touch

file 13:  P1 PostingService ─> P2 sole-writer ─> P3 repair ─> B/C/D/E reattribution
                                                                  └─> Stage E TRACK 2 here (#13–#14)
```
*(P7.5 node added downstream of Stage C and plan 11 §P5.3, and the "+ incentive ACCRUAL POSTING (report #11's T2 half)" annotation removed from file 13's branch — 22 §5.1 E16. The incentive accrual's posting half now lives on the P7.5 branch, next to the ACM it clears against, per E17.)*

Hard ordering rules, restated once:

1. **Consolidation before parser edits** (C, D) — never patch the local parser while prod carries unmerged hotfix variants of the same file.
2. **C before D** — the backfill must use the year-fixed, capture-emitting parser; running it on the current parser replays the 2025-year bug into `ticket_coupons.flight_date`.
3. **B before C** — the service needs the tables; but B needs nothing from file 13, so B starts immediately after **[USER-DECIDE]** names land.
4. **Track 1 never waits for file 13.** The only coupling to the accounting chain is Track 2 (#13, #14, incentive posting) — gated on file 13 Stage E sign-off, same as the `module.accounting` activation flag.
5. **Track 2 before nothing** — no other work waits on it; it is the terminal node on both graphs.

### Stage summary

| Stage | Theme | Complexity | Exit gate |
|---|---|:---:|---|
| A | Capture-gap audit (field-by-field, cited) | S (done) | citations re-verified on consolidated repo before C |
| B | Capture tables: fare detail, coupons, tax lines, class/incentive masters, stock, targets | M | migrations on dev; names confirmed **[USER-DECIDE]**; zero existing-flow references |
| C | Parser extraction methods + `TravelCaptureService`, year fix, PCC warn | M–L | **consolidation gate**; fixture regression = byte-identical legacy writes + expected capture rows; 1-wk dev soak |
| D | Backfill from `files_processed/` (idempotent, dry-run default, resumable) | M | V1–V6; variance CSV reviewed; re-run = no-op |
| E | 16-report catalogue, Track 1 (12) vs Track 2 (4 + incentive posting) | S–M each | T1 reports 1–8 live for pilot; T2 hidden until file 13 Stage E sign-off |
| F | BSP reconciliation — REQUIRED, delivered in plan 11 §P7.5 | — | plan 11 §P7.5's acceptance tests |
| G | Sequencing vs file 13 | — | rules 1–5 respected in execution order |
