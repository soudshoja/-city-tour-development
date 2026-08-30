# 09 — Prioritized Missing Features

Every finding with **status = `missing` or `partial`**, ranked by the blueprint's own **5-layer dependency model** — Chart of Accounts → Posting Engine → Feeder modules (AR/AP + travel specifics) → Reporting, with cross-cutting infrastructure (numbering, config, sub-ledgers, year-end) sequenced by what it blocks — and by severity within each layer. Foundational gaps come first because everything above them inherits their defects.

- **[CONFIRMED]** = adversarially re-verified. **[UNVERIFIED]** = medium/low, reported as-found.
- Cross-dimension duplicates are merged with all source titles listed.
- "Depends on" points at the prerequisite feature(s) that must land first.

---

## LAYER 1 — Chart of Accounts (foundation; everything posts and reports against it)

### MF-1 — Central `AccountService` with the blueprint's account-creation rules  **[CONFIRMED · critical · missing]**
**Missing:** No `AccountService`; creation logic is duplicated across 10+ controllers/commands. Max-depth-6 is checked nowhere; `addCategory` takes `level`+`root_id`+`parent_id` raw from the request; `ChargeController` hardcodes `level=4` under level-2 parents; `ChatController:1140` hardcodes `level=4` under a `%Receivable%` LIKE-match; `importAccounts` inserts `parent_id=null` phantom roots when a parent name fails to resolve.
**Why it matters:** Without one creation chokepoint enforcing parent-mandatory / depth≤6 / level=parent+1 / no-mixed-parents / derived type / derived root_id, the ledger tree is structurally corruptible — phantom roots and inconsistent depths break every rollup and report above.
**Depends on:** nothing — this is the keystone of Layer 1. Blocks MF-2, MF-3, and every posting-time invariant.

### MF-2 — Party-master COA pointer FKs (clients pooled, suppliers ambiguous, airlines absent)  **[CONFIRMED · critical · partial]**
*Sources: CoA "Party-master pointer FKs incomplete…"; AR/AP "Customer receivables pooled in one 'Clients' account…"; Data Model "Party master: no unified tblPartner…".*
**Missing:** Only **agents** have real pointer FKs (`profit_account_id`/`loss_account_id` + auto-created tree). **Clients** have no per-client account — `clients.account_id` was explicitly dropped by migration `2025_03_28_105231`, so every invoice debits one shared `Clients` leaf; `Client::account()` is dead code. **Suppliers** get payable+cost leaves but `Supplier::payableAccount()` (`hasOne` over `accounts.supplier_id`) is ambiguous, so posting resolves suppliers **by name string**. **Airlines** have only an `accounting_code` string and a retroactive `delegatePriceAmadeus` split. Party identity survives on JE lines only as free-text `name`.
**Why it matters:** Per-party leaf accounts are the precondition for AR/AP ageing, customer/supplier statements, and per-airline BSP exposure. Name-based resolution also breaks silently on any COA rename and drives the cross-tenant lookup bug (BUG-H6).
**Depends on:** MF-1 (use `AccountService` + the agent auto-create pattern as the template). Blocks MF-14 (ageing), MF-11 (statements), MF-20 (BSP per-airline).

### MF-3 — Unique constraints on account name/code (seeder ships duplicates)  **[CONFIRMED · high · missing]**
**Missing:** No DB unique index on `accounts.name` or `accounts.code`; app-level checks exist only in `addCategory` and `delegatePriceAmadeus`. `CoaSeeder` ships duplicate codes (`2130`, `4130`) and duplicate names (`Clients`, `Payment Gateway`) that corrupt its own name-keyed `parentMap` and make unqualified `->first()` lookups ambiguous. `ImportChartOfAccounts` de-dupes by fuzzy `LIKE '%name%'`, clobbering near-matches.
**Why it matters:** Ambiguous `->first()` resolution silently posts to the wrong account; duplicate codes break code-keyed reports.
**Depends on:** clean the seeder duplicates and backfill collisions first, then add `UNIQUE(company_id, code)` and `UNIQUE(company_id, parent_id, name)`. Pairs with BUG-H1/H2.

### CoA — Medium/low gaps *(UNVERIFIED)*
- **MF-4 · medium · partial — Type-band derivation via `root_id`, but `root_id` is user-suppliable and null on import.** `ImportChartOfAccounts:117` hardcodes `root_id=null`; `addCategory` takes it from the request. *Fix: derive from parent chain on create; backfill imports by walking `parent_id`.*
- **MF-5 · medium · partial — Freeze/disable column blocks nothing.** `accounts.disabled` is set but no posting flow or picker checks it; no cascade, no `(CLOSED)` rename. *Fix: check in the central posting guard; filter pickers.*
- **MF-6 · medium · partial — Behaviour types are three half-built unused mechanisms** (`CoaLabel` queried once, `account_type_id` never queried, `account_dimension` only in rollups). Cash/bank/gateway behaviour inferred from parent names. *Fix: consolidate on `label`/`CoaLabel`, stamp in seeder + auto-create paths.*
- **MF-7 · medium · missing — `AccGroup` 9-digit statement-rollup key has no equivalent**; legacy value read then discarded on import (non-idempotent re-imports). *Fix: materialized-path column, or persist legacy `acc_group`.*
- **MF-8 · medium · missing — Per-account posting date windows / period locking** (`TransLockdt`/`TransOpenFrom/Todt`) absent. *Fix: `companies.books_closed_until` in the posting guard (minimum).* — subsumed by MF-17.
- **MF-9 · medium · missing — No audit columns / account change log** on `accounts`. *Subsumed by MF-16.*
- **MF-10 · low · partial** — five fixed roots seeded instead of six, matched by name strings (add `root_type` enum); `balance_must_be` orientation column never enforced; no Arabic `name_ar`; no `AltAccCode` import/export mapping; multi-currency accounts accept any currency with no `AllowMultiCurr` gate.

---

## LAYER 2 — Posting Engine (the double-entry spine)

> The single highest-leverage missing piece is the **central `PostingService`** — it is the fix vehicle for BUG-C1, BUG-C2, and the three iron-rule gaps below. It is treated as a build item in the roadmap (Phase 2) rather than a separate finding here.

### MF-11 — Line-level iron rules (debit-XOR-credit, non-negative, frozen-account, FC-consistency)  **[CONFIRMED · high · missing]**
**Missing:** No DB `CHECK` constraints; the `JournalEntry` `boot()` guard is commented out; receipt-voucher rows accept independent debit **and** credit values validated only as `required|numeric` (admits negatives and two-sided rows); `accounts.disabled` never consulted at posting time; no check that a base-currency line's FC amount equals its base amount. Rounding is ad-hoc `round(x,3)` with no per-currency decimals lookup.
**Why it matters:** These are the six `spAccDetailInsertUpdateSingleItem` validations — the last line of defence for ledger integrity on the manual JE / receipt-voucher path a user can submit directly.
**Depends on:** the `PostingService` (Phase 2) to host the `creating`-observer checks; per-currency `decimal_places` (MF-30).

### MF-12 — Posted/draft lifecycle + period locks that block new postings into closed months  **[CONFIRMED · high · partial]**
*Sources: Posting Engine "No Posted/draft flag; period locks…"; CoA "Per-account posting date windows".*
**Missing:** No posted/draft state — every JE is live immediately. A real lock framework exists (`Lockable`, `LockManagementController`) but enforcement is checked **only in `InvoiceController`**; all other controllers and console commands ignore it, and locking flags existing rows only — nothing prevents **creating a new document dated inside a locked month**. No financial-year entity.
**Why it matters:** "Drafts never hit reports" and "you cannot post into a closed period" are baseline controls; today a locked month is silently writable via any non-invoice document.
**Depends on:** `accounting_periods` table (MF-17) + `PostingService` to enforce it globally via observers.

### MF-13 — Audit trail: mirror tables / observers / `CreateID`/`ModID` on core accounting tables  **[CONFIRMED · high · missing]**
*Sources: Posting Engine "Audit trail missing…"; Data Model "No audit-log mirror tables; CreateID/ModID convention absent".*
**Missing:** No `*_log` mirror for any accounting table, no DB triggers, no auditing package, no observers. `journal_entries`/`invoices`/`accounts`/`transactions` have no `created_by`/`updated_by` (only `locked_by`). Posted lines are mutated in place (`UpdateTransactionDate` rewrites `transaction_date`); cascade FKs can hard-delete ledger history (BUG-H3). The generic `system_logs` table is wired only into Task/Supplier controllers.
**Why it matters:** No version history, no who/when-changed trail on financial records — fails audit and makes drift un-diagnosable.
**Depends on:** pairs with BUG-H3 (change cascade FKs to RESTRICT) and BUG-H4 (forbid posted-line mutation). *Fix: add `owen-it/laravel-auditing` (or mirror tables) on JournalEntry/Transaction/Account; add `created_by`/`updated_by` via observer.*

### Posting Engine — Medium/low gaps *(UNVERIFIED)*
- **MF-14 · medium · partial — Multi-currency without discipline:** nothing enforces base = FC × rate; only the liability side of a task carries FC data; JE `currency` label misrepresents a KWD `amount`; `SystemExchangeRate` is one mutable row per pair, not effective-dated. *Subsumed by MF-11 + MF-30 + MF-33.*
- **MF-15 · medium · partial — Line dimensions:** `branch_id` present (sometimes `?? 0`), cost center absent; `agent_id`/`invoice_partial_id` are passed to `JournalEntry::create` but **not fillable and not columns** — silently discarded, so developers believe an agent dimension is stored when it is not. *Fix: add the columns and backfill from `task_id`, or remove the dead keys.* — cost-center part is MF-24.
- **MF-16 · low · partial — Reconciliation/instrument fields** present but missing `reconciled_date`/`reconciled_amount` (no partial reconciliation) and cheque clearance date.

---

## LAYER 3a — Transactions & AR/AP feeder modules

### MF-17 — Financial-year entity + year-end close (retained-earnings sweep)  **[CONFIRMED · high · missing]**
*Sources: Data Model "No financial-year table or period close"; Modules "Year-end closing absent…".*
**Missing:** No `accounting_periods`/`tblAccYear`, no `FinancialStartingDate/EndingDate`, no year-end close routine, no OJV/Balance-B/F journal, no P&L→retained-earnings transfer (the `3400` account exists but is never posted to). P&L accounts accumulate forever. The only control is manual per-document `is_locked`.
**Why it matters:** Without a close, "opening balance" is undefined (BUG-H5), prior-year income never clears from equity, and period locking (MF-12) has no anchor.
**Depends on:** unify opening-balance semantics first (BUG-H5), then build close-year on top of the OJV. Blocks correct Balance Sheet (MF-21) and TB openings.

### MF-18 — Open-item apply engine completion (ledger-line settlement + release)  **[CONFIRMED · high · partial]**
**Missing:** `payment_applications` is a faithful `tblAccIsApply` analog with partial settlement and source-balance guards, **but** there is no `DebitAdj`/`CreditAdj` on `journal_entries` — GL settlement is an all-or-nothing `reconciled` flag set on user-selected lines **without validating amounts** (`BankPaymentController:381-398`). Outstanding is computed from operational tables while GL open-items come from the flag; nothing keeps them consistent. No standalone un-apply (only destructive bulk delete on invoice delete); no never-negative guard. Supplier-side application doesn't exist.
**Why it matters:** This is the AR/AP consistency mechanism; today GL lines can be silently over-marked settled, and AR/AP ageing (MF-14 reporting) has no reliable open-item source.
**Depends on:** MF-2 (per-party accounts). *Fix: add `settled_amount` to `journal_entries` maintained only by an apply/release service with a negative guard; derive `reconciled` from `settled_amount == amount`; extend to supplier applications.*

### MF-19 — Credit/debit-note (CRN/DBN) memo module  **[CONFIRMED · high · partial]**
*Sources: AR/AP "Credit/debit notes and memo module…"; Modules "Credit/debit note (memo) documents missing"; Travel "ADM/ACM adjustment memo module absent".*
**Missing:** No CRN/DBN document type, no memo tables, no ADM/ACM handling. CRN economics exist only **inside** the refund flow (`RefundController::handleRefundCOA` posts a balanced net Dr income / Cr receivable, numbered via `RefundSequence`), so there is no way to **increase** a customer balance (debit note) or record a non-refund allowance/commission adjustment. `storePayableDetail`/`storeReceivableDetail` force a bank leg, so a pure payable↔P&L clawback can't be entered as designed.
**Why it matters:** Credit/debit notes are standard AR/AP instruments; airline ADM/ACM (BSP memos) have nowhere to post.
**Depends on:** `PostingService` + `SequenceService`. *Fix: small memo module (header/lines, type C/D, party, optional linked invoice) with its own number series; route refund CRNs and future ADM/ACM through it.*

### AR/AP — Medium/low gaps *(UNVERIFIED)*
- **MF-20 · medium · partial — Sales invoice posts as two undated-linked gross documents; ledger posting is lazy** (receivable created at first payment/apply, so issued-but-unpaid invoices may have no AR in the GL, understating receivables). *Fix: post the receivable/revenue document at invoice issue; link task-cost and invoice documents via a shared header.*
- **MF-21 · medium · partial — No DocType/SubType taxonomy; number series fragmented** (`reference_type` free text; `Sequence.sequence_for` never used). *Subsumed by MF-29.*
- **MF-22 · medium · partial — RV/PV vouchers implemented minus defects** (instrument details not persisted onto approval-time JEs; flag-based apply without amount validation). *Pairs with MF-18.*
- **MF-23 · low — FIFO auto-allocation exists only as UI ordering** (no routine auto-applies to oldest open invoices); **CTR control/accrual-timing documents absent** (blueprint-sanctioned deferral).

---

## LAYER 3b — Travel-industry feeder specifics (IATA/BSP)

### MF-24 — Duplicate-invoice guard + auto-void flow  **[CONFIRMED · high · partial]**
**Missing:** Only the bulk path checks already-invoiced (`BulkInvoiceValidationService:294-297`). `InvoiceController::store` and `autoGenerateInvoice` create invoices with no already-invoiced/voided check; webhook retries (`TaskWebhook:479,521`) can double-invoice a ticket, double-posting AR and revenue. No void-customer/auto-void concept.
**Why it matters:** Silent double-posting of AR and revenue is a direct financial-integrity failure on the primary (auto-invoice) path.
**Depends on:** a central `Task::isInvoiced()` guard. *Fix: enforce it in all three paths; add a DB unique constraint on `invoice_details.task_id` for active invoices; auto-void duplicates to a house "void customer".*

### MF-25 — BSP statement reconciliation (MIR vs airline billing, ticket-by-ticket)  **[CONFIRMED · high · missing]**
**Missing:** No BSP concept anywhere — no billing-statement table/importer, no ticket-level `SysPayable`-vs-`BspPayable` variance. Partial substitutes (IATA EasyPay wallet snapshots, internal supplier-payable "reconciled" report, bank-statement matching) all reconcile cash legs, never what BSP billed per ticket vs what was sold.
**Why it matters:** BSP reconciliation is the core financial control of an IATA agency; without it, airline over/under-billing and ADMs go undetected.
**Depends on:** MF-2 (per-airline accounts) + MF-27 (airline code resolution) + MF-19 (ADM/ACM memos). *Fix: `bsp_statement_lines` table (period, ticket_number, bsptype, fare, tax, commission, net) + CSV importer; join on `tasks.ticket_number` for per-ticket variance.*

### Travel — Medium/low gaps *(UNVERIFIED)*
- **MF-26 · medium · missing — Ticket stock control & missing-ticket (serial-gap) report.** Sales completeness depends entirely on AIR files arriving; a dropped file is silently missing revenue. *Fix: persist serial per airline prefix, report gaps per office/period.*
- **MF-27 · medium · partial — Airline `accounting_code` never resolved; airline never an accounting dimension.** Full ticket numbers captured but no `substr(ticket,0,3) → airline` resolution; payable is per-supplier+office, not per-airline. *Prereq for MF-25.*
- **MF-28 · medium · partial — Auto-invoice GL lacks fare/commission decomposition** (FM commission element not parsed; payable assumed = K-line total, overstated for commissionable carriers; gross revenue; no travel-date deferral). *Prereq for MF-25.*
- **MF-29 · medium · missing — Airline incentives & booking-class mapping** (`tblAirlineClass`/`Incentive` equivalents); override income untracked. **PCC/office-ID allowlist validation missing; office routing hardcoded** (`KWIKT211N`/`KWIKT2843`, supplier ids, IATA number all hardcoded in shared code — a misfiled AIR file imports under the wrong tenant).
- **MF-30 · medium · partial — Refund flow: unenforced amounts + misclassified clawback account** (`RefundController:674-692` creates "Commissions Expense (Agents)" as an **asset on the balance sheet**). *Fix: validate operator figures vs parsed amounts; fix clawback classification.*
- **MF-31 · low — GDS segment counting / airline targets / sales-void-by-airline analytics absent; ticket check-digit (mod-7) validation absent; air-cargo (GSA) absent** (likely out of scope).

---

## LAYER 4 — Reporting (re-derives from everything below)

### MF-32 — Single canonical ledger-report query (`LedgerReportQuery`)  **[CONFIRMED · high · partial]**
**Missing:** Four independent balance calculators (`TrialBalanceService`, ~30 inline `ReportController` queries, `CoaController::childAccount` roll-up, `JournalEntryController` running-balance) that disagree on period-date column (BUG-C4), sign rules (BUG-M3), and opening definition (BUG-H5). No last-year comparison anywhere.
**Why it matters:** The blueprint's invariant is "all reports re-derive from one place and always agree." Four divergent implementations guarantee they don't.
**Depends on:** builds on `TrialBalanceService`'s conventions. **Blocks all report fixes below** — build once, specialize per report.

### MF-33 — Balance Sheet report  **[CONFIRMED · high · missing]**
**Missing:** Only `Account::REPORT_TYPES['BALANCE_SHEET']` + `report_type` column exist; no route/controller/view; period profit never folded into equity.
**Depends on:** MF-32 (shared query) + MF-17 (year-end/retained-earnings for the equity line). *Fix: A/L/Equity balances as-of a date + computed period profit as a virtual equity line.*

### MF-34 — AR/AP ageing report (30/60/90/120 buckets)  **[CONFIRMED · high · missing]**
*Sources: AR/AP "AR/AP ageing reports missing"; Reporting "Receivable/Payable Ageing missing".*
**Missing:** No bucketed ageing anywhere; the closest artifact lists `reconciled=0` JE lines with running balances — no buckets, no per-document outstanding, party filter only by supplier name string. `invoices.due_date` exists but drives only reminders, never overdue buckets.
**Depends on:** MF-2 (per-party accounts) + MF-18 (open-item engine). *Fix: open = original − applied, grouped by party account, bucketed by `DATEDIFF(as-of, doc_date)` in SQL.*

### MF-35 — Profit & Loss variants (branch filter, cumulative, comparative)  **[CONFIRMED · high · partial]**
**Missing:** `profitLoss()` renders one month for `4*`/`5*` level-3 accounts + a 12-month chart. No branch/cost-center filter (`branch_id` unused), no month-wise 12-column table, no cumulative/last-year comparison; accounts outside the code shape silently vanish; loads full-year JEs into PHP. Route has no authz (BUG-C5).
**Depends on:** MF-32; MF-24 (cost-center) for the branch/cost-center variant. Pairs with BUG-C4 + BUG-M3.

### MF-36 — Travel-industry analytics catalogue  **[CONFIRMED · high · missing]**
**Missing:** No GDS segment count, airline segment-vs-target variance, incentive accrual, BSP summary, missing-ticket/stock, tour-code, or discrepancy/lowest-fare report. Airline is never an aggregation dimension despite the raw data (segments/PNRs/ticket numbers) already being parsed.
**Depends on:** MF-27 (airline dimension) for Phase-2 items; Phase-1 items (airline-wise sales, segment count, PNR report, void-% report) need only existing data. *Cheap wins available immediately.*

### Reporting — Medium/low gaps *(UNVERIFIED)*
- **MF-37 · medium · partial — Trial Balance branch filter is wrong** (filters `accounts.branch_id` instead of `journal_entries.branch_id`, so shared accounts contribute all branches); opening column ignores options; `show_zero` drops opening-only accounts; CSV omits opening/closing. *Fix: filter movement by `je.branch_id`.*
- **MF-38 · medium · missing — Cash Flow report** (only a point-in-time bank balance; settlements matched by fragile description `LIKE`).
- **MF-39 · medium · partial — Apply/Unapplied payments report** absent despite the apply engine existing (data ready, needs a two-tab report).
- **MF-40 · medium · partial — Outstanding/Statement** lacks brought-forward opening rows and numbered statements; AP tree resolved by hard-coded English names.

---

## CROSS-CUTTING INFRASTRUCTURE (sequenced by what it blocks)

### MF-41 — Ledger header + `DocType`/`SubType`/`DocYear`/`Posted` (make `transaction_id` NOT NULL)  **[CONFIRMED · high · partial]**
**Missing:** `journal_entries` is line-level only; `transactions` is a quasi-header but `journal_entries.transaction_id` is **nullable** and feeders insert lines one-by-one inside `if ($account)` guards, so documents are never atomically defined. No `Posted` lifecycle, no `DocType`/`SubType`/`DocYear`; `reference_type` is a narrow free-text enum. `findUnbalancedTransactions` exists specifically because the schema permits imbalance.
**Why it matters:** A header with a doc-type taxonomy is the precondition for reversal-by-doc-type (BUG-H4), period locks (MF-12), and DocType-driven reports.
**Depends on:** `PostingService` (Phase 2) writes header + balanced lines atomically. *Fix: `transaction_id` NOT NULL; add `doc_type`/`sub_type`/`doc_year`/`posted` to `transactions`.*

### MF-42 — System-parameters posting keys (base currency + control accounts, today hardcoded)  **[CONFIRMED · high · partial]**
**Missing:** A good per-company `Setting` table exists but the only keys used are `invoice_expiry_days` and notification channels. Base currency `'KWD'` is hardcoded at ~39+ sites; AR/AP/gateway control accounts resolved by name lookups; `TaskWebhook` hardcodes tenant-specific account names in shared code; several lookups (`AccountingController:860/868`, `CreateClientCredit:214`, all of `TaskWebhook::getIataAccounts`) lack a company filter — **confirmed cross-tenant leaks**. No FX gain/loss, suspense, VAT, or numbering parameters.
**Why it matters:** Control-account-by-name is the root of BUG-H6 and the tenant-isolation findings; a rename silently reroutes money.
**Depends on:** MF-2 (accounts to point at). *Fix: ~10 validated per-company keys (base_currency, receivable/payable/gateway control ids, fx_gain_loss_account_id, retained_earnings_account_id, auto_lock_on_post); replace every `Account::where('name',…)` control lookup.*

### MF-43 — Cost-center dimension  **[CONFIRMED · high · missing]**
**Missing:** `journal_entries.branch_id` exists and is populated (branch dimension OK), but `cost_center`/`CostCenter` returns zero hits repo-wide — no table, column, or UI. `account_dimension` is a picker filter, not a reporting dimension.
**Why it matters:** Blueprint tags every line with `BranchID` **and** `CostID` even when the feature is off, so cost-center P&L can be switched on later with zero data migration.
**Depends on:** `PostingService` to default it from branch/task type. *Fix: add `cost_centers` table + nullable `cost_center_id` on `journal_entries` + `transactions` now.*

### MF-44 — Foreign-currency revaluation  **[CONFIRMED · high · missing]**
**Missing:** JE lines capture `currency`/`exchange_rate`/`original_currency`/`original_amount` and daily rates sync, but there is no period-end revaluation of open FC balances, no realized/unrealized FX gain/loss accounts in use (the seeded `5219 Exchange Gain/Loss` is dead code), and no `tblAccRateAdj` analog. Open FC payables/receivables sit at historical rates forever; settlement differences vanish untracked.
**Why it matters:** Real multi-currency bookkeeping without revaluation misstates FC balances and hides realized FX on settlement.
**Depends on:** MF-42 (fx account keys) + effective-dated rates (MF-14). *Fix: period-end command posting `Σ(original × current rate) − Σ(booked base)` per FC account as a JV into an `fx_rate_adjustments` table (reversed at settlement); book realized diffs in the PV flow.*

### MF-45 — Bank & card statement import (true reconciliation, not internal matching)  **[CONFIRMED · high · partial]**
**Missing:** JE lines carry instrument fields and a working internal reconcile/un-reconcile flow, **but** no bank/card statement import or staging table; `auth_no` captured but never used as a match key; no `ReconciledDate`/`ReconciledAmt`. Reconciliation confirms our own PV against our own JEs, never against the bank.
**Depends on:** BUG-H9 (fix the soft-delete totals bug first). *Fix: `bank_statement_lines` staging + CSV/OFX import, match on `auth_no`/`cheque_no`, stamp `reconciled_date`/`amount`, report statement-vs-GL exceptions.*

### Cross-cutting — Medium/low gaps *(UNVERIFIED)*
- **MF-46 · medium · partial — Branch table has no GL account pointers** (`CashAccID`/`BankAccID`/`BranchAccID`/discount); routing scattered onto `users.acc_bank_id`/`charges` and name-walking. *Fix: add the four FKs to `branches`.*
- **MF-47 · medium · partial — Open-item registry AR-only** (no supplier-side application; three overlapping mechanisms — `payment_applications`, `invoice_partials`, `invoice_receipt.is_used` — maintained independently). *Subsumed by MF-18.*
- **MF-48 · medium · missing — Fixed assets & depreciation** (dead `Asset` model, no table, no depreciation engine); **Budgeting** (zero-filled vestigial `budget_balance`/`variance` columns, no budget rows/variance report); **Recurring journal templates** (recurrence machinery exists in `RunAutoBilling` but generates invoices, not GL journals).
- **MF-49 · medium · partial — Security hardening:** solid RBAC/2FA/integrity-report core, missing per-user row filters, single-session/IP-log policy, password history/complexity, and the two other integrity checks (invalid FX rate, base-vs-FC mismatch).
- **MF-50 · low — Travel feeder tables** (`tblMirHeader/Detail`, `tblBspReconciliation`, `tblAirlineStock`, `tblAirlineClass/Incentive`) missing — subsumed by MF-25/26/29; **generic `tblMaster` lookup** not implemented (acceptable alternative in place); **inter-company posting** missing (conditionally applicable — not needed for the current single-legal-entity tenant model); **staff commission** per-service split / bracket table absent; **notifications/attachments** lack a universal registry and send quotas.

---

### Dependency summary (build order implied)
```
MF-1 AccountService ─┬─> MF-2 party FKs ─┬─> MF-18 open-item ─┬─> MF-34 ageing
                     │                   │                   └─> MF-40 statements
                     ├─> MF-3 unique     └─> MF-25 BSP <── MF-27 airline dim <── MF-28 fare decomp
                     └─> MF-42 param keys ──> (fixes BUG-H6 tenant isolation)

PostingService (Phase 2) ─┬─> MF-11 iron rules ─┬─> MF-41 header/doc-type ─> MF-12 period locks
                          ├─> MF-13 audit trail │
                          └─> MF-19 memos       └─> MF-17 year-end ─> BUG-H5 opening ─> MF-33 Balance Sheet

MF-32 LedgerReportQuery ─> {MF-33, MF-34, MF-35, MF-36, MF-38, MF-39, MF-37}
MF-42 param keys ─> MF-44 FX reval ; MF-43 cost center (independent, do early) ; MF-45 bank import (after BUG-H9)
```
