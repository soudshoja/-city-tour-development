# 21 — Blueprint Coverage Matrix

**What this is:** a complete, element-by-element reconciliation of the `travel-accounting-system` skill blueprint against our plan of record and shipped work, so the owner can make a decision on **every** uncovered item rather than discovering them one at a time during a sale.

**Date:** 2026-08-27 · **Branch:** `feat/accounting-posting-engine-p1` · **Supersedes nothing** (companion to files 09/10/11/13/15/19).

---

## 1. Method

I read the blueprint in full — `SKILL.md` (the 8 golden rules and 5-layer model) and all seven references: `01-chart-of-accounts`, `02-posting-engine`, `03-transactions-and-ar-ap`, `04-travel-industry`, `05-reporting`, `06-data-model`, `07-modules-and-config` — and walked every numbered section and sub-feature, decomposing each into the smallest element a buyer or auditor could ask about (one row per field, rule, document type, report, module, or config key that the blueprint actually names). I then read our plan of record in full — `00-executive-summary`, `09-prioritized-missing-features` (MF-1..MF-50), `10-implementation-roadmap` (Phases 0–8), `11-technical-implementation-plan` (P0–P9, the master spine), `15-travel-data-capture-and-reports-plan` (Stages A–G), `19-report-data-contract` §1–2, `17-p1-postingservice-complete`, and skimmed `13-party-ledger-reattribution-plan` — plus the orchestrator's status facts for what is DONE-but-uncommitted on this branch. For every blueprint element I searched the whole `Accounting Gap/` folder for the concept (not just the word) to distinguish *"scheduled in a phase"* from *"noticed in the audit but never scheduled"* from *"genuinely never mentioned"* — those three are materially different decisions and the matrix keeps them apart. **Nothing in this document is a new feature idea**: every row traces to a blueprint section, and where I drew a conclusion the blueprint does not state outright, the row is tagged `[inference]`.

**Status vocabulary**

| Status | Means |
|---|---|
| **DONE** | Shipped on this branch (P1 engine + W1/W2 cutover), per the status facts. Not necessarily deployed. |
| **PLANNED** | Named in a plan doc **with a phase**. Cited as `doc §phase`. |
| **PARTIAL** | Something real exists or is planned, but a named blueprint sub-part is missing. The gap is stated. |
| **NOT DISCUSSED** | No phase owns it. Sub-tagged `(audited only)` when a per-dimension audit file 01–07 noticed it but no MF-id/phase ever picked it up — those are the most dangerous, because they *look* covered. |

**Severity** is judged for a **sellable multi-tenant product**, not for our own bookkeeping: Critical = a buyer's accountant or auditor rejects the system without it; Important = we lose deals or eat support cost; Nice = polish.

---

## 2. Coverage matrix

### Reference 01 — Chart of Accounts

| # | Blueprint element (ref §) | What the blueprint requires | Our status | Sev | Recommendation |
|---|---|---|---|---|---|
| 01-1 | Self-referencing tree, ≤ 6 levels (01 §1, §6.2) | Hierarchy with a hard depth cap | **PARTIAL** — `AccountService` shipped with depth/level rules (11 §P1.0, doc 17); the 10+ legacy creation sites are **not yet refactored** onto it and `account_observer.enabled` defaults **false** (doc 17 §1) | Critical | **DO-NOW (W4)** — the observer backstop is worthless while off; flip it on and migrate creation sites in the long-tail wave |
| 01-2 | Group vs leaf nodes; a parent is header **xor** leaf (01 §1, §6.3) | Guarantees postings only land on leaves | **DONE** — engine throws `NonLeafAccountException`; `is_group` backfill specified 11 §P1.0 | Critical | — |
| 01-3 | `Acc_ID` first digit encodes root band (01 §2, §4) | Type derivable from the key itself | **NOT DISCUSSED** — we use `root_id` FK instead; MF-4 flags `root_id` as user-suppliable | Nice | **SKIP** — `root_id` + derived `account_type` is an equivalent mechanism; close MF-4 instead |
| 01-4 | `AccCode` auto-generated under parent, padded to sibling width (01 §2, §6.6) | Human-facing number, collision-free | **DONE** — `AccountCodeGenerator` (doc 17 §1); BUG-H1 closed | Important | — |
| 01-5 | `AccName_FL` foreign-language (Arabic) name (01 §2) | Bilingual COA | **PLANNED** — 11 §P7 (`name_ar` + RTL) | Important | **SCHEDULE (P7)** — GCC buyers expect Arabic statements |
| 01-6 | `AccType` derived A/L/I/E, never typed (01 §2, §4, §6.4) | Report classification can't be corrupted by data entry | **PARTIAL** — derivation specified in `AccountService` (11 §P1.0, BUG-H2); backfill from `root_id` and making the column non-fillable not confirmed done | Critical | **DO-NOW (W4)** — one migration + one `$guarded` change |
| 01-7 | `AccGroup` 9-digit statement-rollup key, +2 digits/level (01 §2, §4) | Drives statement grouping and report ordering | **NOT DISCUSSED** (audited only — MF-7, no phase) | Important | **SCHEDULE (P7)** — needs *some* stable rollup key (materialized path) before COA templates ship, or statement grouping stays name-ordered |
| 01-8 | `ParentAccID_FK` + `AccLevel = parent+1` (01 §2, §6.5) | The tree spine | **DONE** — in `AccountService` | Critical | — |
| 01-9 | `AccTransType` behaviour flag: 5 normal / 1 cash / 2 bank-gateway / 8 commission-control / 6 pure group (01 §2, §5) | Lets the rest of the system know how an account behaves | **PARTIAL** — three half-built mechanisms (MF-6); cash/bank/gateway inferred from **parent names** today; 11 never schedules MF-6 | Important | **SCHEDULE (P7)** — consolidate onto one `label` column; COA templates are the natural place to stamp it |
| 01-10 | `AllowMultiCurr` + default `CurrID_FK` per account (01 §2) | Blocks stray FC postings to base-only accounts | **NOT DISCUSSED** (audited only — MF-10 low) | Nice | **DEFER** — engine already enforces FC-consistency per line; the gate is belt-and-braces |
| 01-11 | `IsFreeze` / `AccStatus`, cascade to children, ` (CLOSED)` rename (01 §2, §6.9) | Retire an account without deleting history | **PARTIAL** — engine throws `FrozenAccountException` (DONE); cascade + rename + picker filtering planned 11 §P7 (MF-5) | Important | **SCHEDULE (P7)** — posting is already safe; this is the UX half |
| 01-12 | `OutstandingAmt` live running balance (01 §2; 02 §4a) | Fast balances without re-summing | **PARTIAL / open decision** — engine deliberately does **not** maintain `actual_balance` (11 §P1 step 9 left open; Appendix C note 2); ledger-derived accessor DONE; **115 legacy readers across 20+ files** (doc 17 §3 gate 2); 41.5% of dev accounts already disagree with the ledger (19 §R1) | **Critical** | **DO-NOW (before W3)** — this is a named cutover gate; W3's `InvoiceController` is one of the 115 readers. Decide: widen+maintain, or migrate readers to `TrialBalanceService`. Do not let it drift into W4 |
| 01-13 | Per-account posting date windows `TransLockdt` / `TransOpenFrom/Todt` (01 §2) | Per-account period control | **PARTIAL** — company-wide `accounting_periods` planned 11 §P5.1; per-**account** windows subsumed away (MF-8) | Nice | **DEFER** — company periods cover 95% of the need; revisit only if a buyer asks |
| 01-14 | `AltAccCodeExp` / `AltAccCodeImp` external-system mapping (01 §2) | Export/import to another accounting package | **NOT DISCUSSED** (audited only — MF-10 low) | Important | **SCHEDULE (P7)** — a sellable product gets asked "can you export to our auditor's system"; two nullable columns now is cheap |
| 01-15 | Audit columns + `tblAccountLog` mirror (01 §2, §8) | Who/when on every account change | **PARTIAL** — `created_by`/`updated_by` + `SoftDeletes` in 11 §P1.0; the **mirror table / version history** (MF-13) is silently downgraded to attribution-only in 11 §P2 | Important | **SCHEDULE (P5, new sub-phase)** — see §5. "We have an audit trail" is a sales claim we can't currently make |
| 01-16 | Six fixed roots incl. **APPROPRIATIONS** and **EQUITY** (01 §3) | The statement spine | **PARTIAL** — five roots seeded, matched by **name strings**; no `root_type` enum (MF-10, no phase) | Important | **SCHEDULE (P7)** — COA templates must seed six typed roots or year-end/appropriation entries have nowhere to land |
| 01-17 | Nine enforced creation rules (01 §6.1–§6.9) | The tree cannot be made invalid | **PARTIAL** — encoded in `AccountService`; not enforced at every call site (see 01-1) | Critical | **DO-NOW (W4)** |
| 01-18 | `UNIQUE` account name and code (01 §6.8) | Kills ambiguous `->first()` resolution | **PLANNED** — 11 §P1.0 (after seeder de-dup); MF-3 | Critical | **DO-NOW (W4)** — the de-dup is the work; the index is one line |
| 01-19 | Every customer / supplier / airline is its own **leaf account** (01 §7) | Precondition for ageing + statements | **PLANNED** — 11 §P5.3 + doc 13 Stages B–F (historical reattribution). Agents already have real pointer FKs; **per-agent Receivable group decided this week** | Critical | **SCHEDULE (P5.3)** — biggest single unlock; doc 13 is already written |
| 01-20 | Party master pointers `CustAccID_FK` / `SuppAccID_FK` / `AirlineAccID_FK` (01 §7; 03 §4) | Feeders read pointers, never names | **PLANNED** — 11 §P5.3, doc 13 §B.2 (MF-2) | Critical | **SCHEDULE (P5.3)** |
| 01-21 | Auto-create the COA group when a party **type** is onboarded (01 §7; 03 §4) | New tenant/party self-provisions correctly | **PLANNED** — 11 §P5.3 observers + 11 §P7 COA templates | Important | **SCHEDULE (P7)** — pair with template cloning at signup |

### Reference 02 — Posting engine

| # | Blueprint element (ref §) | What the blueprint requires | Our status | Sev | Recommendation |
|---|---|---|---|---|---|
| 02-1 | Header + N detail lines; header carries no amount (02 §1) | One document = one atomic balanced unit | **DONE** — `transactions` gains `doc_type`/`sub_type`/`doc_year`/`posting_status`/`total_debit`/`total_credit` | Critical | — |
| 02-2 | `journal_entries.transaction_id` always set (02 §1) | No orphan lines | **PARTIAL** — engine always sets it; **NOT NULL deferred to P3** because prod has orphans (11 §P1.1, §P3) | Critical | **SCHEDULE (P3)** — already the plan; don't let W4 slip it |
| 02-3 | Header-level `Σdebit = Σcredit` assertion at save (02 §1 — the blueprint's explicit "add this yourself" instruction) | The one guard the reference system leaves to the app | **DONE** — `UnbalancedDocumentException`, tolerance 0.0005 | Critical | — |
| 02-4 | `RefNo`/`RefCode`/`RefType` back-link to the source module (02 §1) | Every GL doc traceable to the sale | **DONE** — engine line attribution (`invoice_id`, `task_id`, `type`, `type_reference_id`, `name`, `voucher_number`) + header `payment_id` | Important | — |
| 02-5 | `DC` denormalised side column (02 §2) | Fast filtering | **NOT DISCUSSED** | Nice | **SKIP** — derivable from debit/credit; not worth a column |
| 02-6 | `DebitAdj` / `CreditAdj` settled-amount on each line (02 §2; 03 §7) | The open-item spine | **PLANNED** — `journal_entries.settled_amount` in 11 §P5.3 (MF-18) | Critical | **DO-NOW (W3, column only)** — add the column while the JE table is already being migrated; the service lands in P5.3 |
| 02-7 | `TransactionType` audit label on the line (02 §2) | Why the line exists; module-specific reversal | **DONE** — `LineDraft::$transactionType`; **line reason tags** `loan\|service\|adm\|fee\|loss\|settlement` decided this week | Important | **DO-NOW (W3)** — persist reason tags as a typed column, not free text |
| 02-8 | `TransactionDtlNo` link to the source line (02 §2) | Ticket-level traceability | **DONE** — `task_id` / `invoice_detail_id` on the line | Important | — |
| 02-9 | `Reconciled` / `ReconciledDate` / `ReconciledAmt` (02 §2) | Partial bank reconciliation | **PARTIAL** — flag exists; **date and amount missing**, so no partial reconciliation (MF-16); 11 §P5.10 adds the matching screen | Important | **DO-NOW (W3, columns only)** + **SCHEDULE (P5.10)** for the logic |
| 02-10 | Cheque no / date / clearance date / bank on the line (02 §2) | Instrument detail for later reconciliation | **PARTIAL** — fields exist but are **not persisted onto approval-time JEs** (MF-22); no clearance date | Important | **DO-NOW (W5)** — the receipt/bank voucher wave already touches these methods |
| 02-11 | `Tax` and `Discount` line extras (02 §2) | Per-line tax/discount visibility | **PARTIAL** — Tax planned 11 §P9 (VAT engine); **Discount not discussed** (`DiscountAcc` appears only in the 06 audit) | Nice | **DEFER** — Kuwait has no VAT; revisit discount with P9 |
| 02-12 | Line is one-sided (debit XOR credit) (02 §2, §3) | | **DONE** — `OneSidedLineException` | Critical | — |
| 02-13 | Line points at a leaf only (02 §2, §3.5) | | **DONE** | Critical | — |
| 02-14 | Amounts non-negative (02 §2, §3.2) | Post the opposite side, never a negative | **DONE** — `NonNegativeAmountException` | Critical | — |
| 02-15 | Round to the **currency's own** decimals, base and FC separately (02 §3.1) | | **PARTIAL** — `Money` rounds; per-currency `decimal_places` lookup missing (MF-11/MF-30) | Important | **DO-NOW (W4)** — `currencies.decimal_places` + wire into `Money` while it's fresh |
| 02-16 | Base-currency line: `FCDebit+FCCredit == Debit+Credit` (02 §3.3) | No rate distortion on base lines | **DONE** — `FcConsistencyException` | Critical | — |
| 02-17 | Reject posting to a frozen account (02 §3.4) | | **DONE** | Important | — |
| 02-18 | Header must exist before any line (02 §3.6) | | **DONE** — engine writes header first in one transaction | Critical | — |
| 02-19 | Eager balance (a): live `OutstandingAmt` per account (02 §4a) | Reports never re-sum millions of lines | **PARTIAL / open decision** — see 01-12 | Critical | **DO-NOW (before W3)** |
| 02-20 | Eager balance (b): per-account/month/branch/cost **bucket table** (02 §4b) | The trial-balance/ledger/ageing performance trick | **NOT DISCUSSED** — `account_monthly_balances` appears in **10 Phase 3 only** and was **dropped from 11** with no note | Important | **SCHEDULE (new P5.16 or fold into P5.4)** — see §5. At 73k+ lines we're fine; at a 20-tenant SaaS we are not |
| 02-21 | Reverse-then-apply on every edit/delete (02 §5) | Balances never drift | **DONE** — `PostingService::reverse()` / `repost()` | Critical | — |
| 02-22 | Applied / reconciled lines are protected from edit/delete (02 §5) | Can't silently unwind a settled entry | **DONE** — `reverse()` refuses reconciled lines without `$force` | Critical | — |
| 02-23 | Periodic **integrity check** recomputing balances from raw lines, flagging drift (02 §5) | The safety net | **PARTIAL** — nightly drift check named in 11 §P3; the six `Fix*COA` commands are the current (wrong) substitute and are slated for retirement | Important | **SCHEDULE (P3)** — ship the checker in the same PR that retires the `Fix*` family |
| 02-24 | `*Log` mirror tables on every base table, trigger-populated (02 §6) | Full version history | **PARTIAL** — MF-13; 11 closes it as `created_by` attribution only | Important | **SCHEDULE (new sub-phase, §5)** |
| 02-25 | Account log doubles as running-balance history (02 §6) | Timestamped balance snapshots for audit | **NOT DISCUSSED** | Nice | **SKIP** — blueprint itself says "decide deliberately"; high volume, low value for us |
| 02-26 | `Posted` flag: drafts never hit reports (02 §7) | | **PARTIAL** — `posting_status` column DONE and defaults `posted`; the **draft lifecycle** (create → review → post) does not exist | Important | **SCHEDULE (P5.1)** — a manual-JV screen without drafts is a hard no for accountants |
| 02-27 | Period locks checked before posting (02 §7) | | **PLANNED** — `PeriodGuard` stub shipped, real in 11 §P5.1 (MF-12) | Critical | **SCHEDULE (P5.1)** |
| 02-28 | Opening Journal (OJV): immutable, carries BS accounts forward, collapses P&L to retained earnings, excluded from movement (02 §7) | | **PLANNED** — 11 §P5.2 (MF-17, BUG-H5) | Critical | **SCHEDULE (P5.2)** |
| 02-29 | Both FC and base amounts on **every** line + `FcExchRate` (02 §8) | | **DONE** — engine populates on every line | Critical | — |
| 02-30 | Exchange rates stored **historically / effective-dated** (02 §8) | Never one mutable number | **PLANNED** — `system_exchange_rates.valid_from` in 11 §P5.6 (MF-14) | Important | **SCHEDULE (P5.6)** |
| 02-31 | Period-end FX revaluation posts as a JV (02 §8; 07 §4) | | **PLANNED** — 11 §P5.6 (MF-44) | Important | **SCHEDULE (P5.6)** |

### Reference 03 — Transactions & AR/AP

| # | Blueprint element (ref §) | What the blueprint requires | Our status | Sev | Recommendation |
|---|---|---|---|---|---|
| 03-1 | Two parallel layers: operational (`TrMaster`/`TrDetail`/`TrPayment`/`TrPaymentAllocation`) and ledger, linked (03 §1) | Ops reports and financial truth kept separate but tied | **DONE** — `tasks`/`invoices`/`invoice_details`/`payments`/`payment_applications` are the analog; 19 §2a confirms the split is clean | Critical | — |
| 03-2 | `TrType` 1 = sale / 2 = refund; `Locked`/`Posted` on the operational doc (03 §1) | | **PARTIAL** — refund is a separate flow, not a typed operational header; `Lockable` exists but is enforced only in `InvoiceController` (MF-12) | Important | **SCHEDULE (P5.1)** — fold into the period/lock work |
| 03-3 | `DocType` taxonomy INV / RV / PV / JV / CTR / CRN / DBN / BDS (03 §2) | Each doc type behaves and numbers differently | **PARTIAL** — INV/RV/PV/JV/CRN/DBN/OJV/REV present in `serial_schemas` (11 §P1.0); **CTR and BDS absent** | Important | See 03-4, 03-5 |
| 03-4 | `CTR` control postings for accrual timing (03 §2, §3) | Places payable on issue date and income on travel date via control accounts | **NOT DISCUSSED** (audited only — 03-audit Finding 4, MF-23; blueprint explicitly sanctions deferring) | Important | **DEFER** — but note it blocks period-accurate accrual reporting; revisit when a buyer asks for accrual-basis statements |
| 03-5 | `BDS` bulk daily sheet (03 §2) | Batch of sales as one document | **NOT DISCUSSED** | Nice | **SKIP** — our bulk-invoice path covers the need |
| 03-6 | `SubType` refinement (MAN_INV, BPV/BRV, SUPP_INV/SUPP_REF, OJV) (03 §2) | | **PARTIAL** — column exists, taxonomy unpopulated | Nice | **DO-NOW (W3/W4)** — stamp sub-types as each feeder is cut over; free at cutover, expensive after |
| 03-7 | Own number series per DocType (03 §2; 06 §2) | | **DONE** — `serial_schemas` + `SequenceService`. **Gate: `accounting:seed-serial-schemas` has never been run** (doc 17 §3 gate 3) | Critical | **DO-NOW (before any company goes live)** |
| 03-8 | Sales invoice 3-line economics: Dr customer = Cr supplier + Cr income (03 §3) | | **PARTIAL** — exists in `InvoiceController`; **W3 cutover (33 sites) not started** | Critical | **DO-NOW (W3)** — the current wave |
| 03-9 | Post the receivable at **invoice issue**, not first payment (03 §3; MF-20) | Otherwise AR is understated | **PLANNED** — 11 §P5.3 | Critical | **SCHEDULE (P5.3)** — a buyer's AR ageing is wrong until this lands |
| 03-10 | Account derivation from party master, airline before supplier (03 §4) | Feeder never hard-codes an account | **DONE for purpose-codes** (`system_accounts`, ~237 name lookups being replaced); **PLANNED for per-party** (11 §P5.3) | Critical | **SCHEDULE (P5.3)** |
| 03-11 | Receivables/payables tracked at the **individual party**, never pooled (03 §4 design principle) | | **PLANNED** — 11 §P5.3 + doc 13 (all clients currently share leaf `1351`) | Critical | **SCHEDULE (P5.3)** |
| 03-12 | Receipt Voucher (RV): Dr bank/cash, Cr customer, then apply (03 §5) | | **PARTIAL** — exists; W5 cutover pending; instrument details not persisted (MF-22) | Critical | **DO-NOW (W5)** |
| 03-13 | Payment Voucher (PV): Dr supplier, Cr bank/cash, then apply (03 §5) | | **PARTIAL** — supplier-side apply does not exist at all (MF-47) | Critical | **SCHEDULE (P5.3)** |
| 03-14 | Front-office counter receipt document linked to the RV (03 §5) | Counter-level document trail | **NOT DISCUSSED** | Nice | **SKIP** — no counter/walk-in business line today |
| 03-15 | Credit note (CRN) reduces a receivable (03 §6) | | **PARTIAL** — CRN economics exist **only inside** the refund flow (MF-19); no standalone document | Critical | **SCHEDULE (P5.3)** |
| 03-16 | Debit note (DBN) increases a receivable (03 §6) | No way to bill an extra charge today | **PLANNED** — 11 §P5.3 memo module (MF-19) | Critical | **SCHEDULE (P5.3)** |
| 03-17 | Memo module as the data-entry front for CRN/DBN/**ADM/ACM**/commission adjustments (03 §6; 07 §5) | | **PLANNED** — 11 §P5.3. **ADM/ACM ingestion decided REQUIRED this week**, which promotes this from "nice memo module" to BSP prerequisite | Critical | **SCHEDULE (P5.3, then P7.5)** — see §5 |
| 03-18 | `tblAccIsApply` open-item registry (03 §7) | One row per payment→invoice match | **DONE (operational half)** — `payment_applications` is a faithful analog with partial settlement and source guards (00 calls it "the good half") | Critical | — |
| 03-19 | `DebitAdj`/`CreditAdj` maintained on the GL line by apply (03 §7) | GL and operational open-items agree | **PLANNED** — 11 §P5.3; today GL settlement is an **all-or-nothing flag set without validating amounts** (MF-18) | Critical | **SCHEDULE (P5.3)** |
| 03-20 | Outstanding = original − applied; partial settlement; many-to-many (03 §7) | | **PARTIAL** — true operationally, not in the GL | Critical | **SCHEDULE (P5.3)** |
| 03-21 | Release / un-apply with never-negative guard (03 §7; 07 §7) | | **PLANNED** — 11 §P5.3 (today: destructive bulk delete on invoice delete) | Critical | **SCHEDULE (P5.3)** |
| 03-22 | **Auto-allocation FIFO** — oldest open invoices first (03 §7; 07 §7) | | **NOT DISCUSSED** (audited only — MF-23 low; exists as UI ordering only) | Important | **SCHEDULE (P5.3)** — it is ~20 lines on top of the apply service; leaving it out means every receipt is hand-allocated |
| 03-23 | Ageing reads straight off the apply engine, bucketed by `DATEDIFF` (03 §7) | | **PLANNED** — 11 §P5.4 (MF-34) | Critical | **SCHEDULE (P5.4)** |
| 03-24 | **Credit control**: `CrLimit` + variance, credit-enabled date, recomputed `CurrentBalance`/`AvailableCredit`, `IsBacklisted` hard stop, enforced at invoice save (03 §8) | Stops selling to a customer who already owes too much | **NOT DISCUSSED** (audited only — 03-audit Finding, with a written recommendation, but **no MF-id and no phase**). 19 §3d warns `clients.credit` is a prepaid balance, not a limit; `credit_facility` is dead schema | **Critical** | **SCHEDULE (new P5.12, §5)** — this is the most commercially visible pure-gap in the matrix: every agency asks "can it stop an agent selling over their limit?" |
| 03-25 | Gateway clearing account + fee account per gateway, `AccTransType=2` (03 §9) | Fee stays visible; bank line reconciles to net | **DONE** — `GATEWAY_CLEARING_*` / `GATEWAY_FEE_EXPENSE_*` purpose codes, leaves 5144 KNET / 5145 uPayment | Critical | — |
| 03-26 | Gateway 3-line RV posted immediately (Dr clearing + Dr fee = Cr customer) (03 §9) | | **DONE** — W2 cut over `createInvoicePaymentCOA` + 6 gateway entry points; fee-bearer policy (client\|company, company sub-choice company\|agent\|split+%) decided; `4133 Gateway Fee Recovery (Agents)` decided | Critical | — |
| 03-27 | Gateway receipt **applied** to the matching invoice (03 §9) | | **PARTIAL** — W2b (credit-apply) is the next wave | Critical | **DO-NOW (W2b)** |
| 03-28 | Later reconcile gateway/bank statement by **authorization code** (03 §9; 07 §6) | | **PLANNED** — 11 §P5.10 (MF-45); `auth_no` captured but never used as a match key | Important | **SCHEDULE (P5.10)** |
| 03-29 | Ancillary services all use one feeder pattern (hotel/visa/XO/cargo) (03 §10) | Build accounting once, add services as thin feeders | **DONE** — all 12 task types funnel through one path (00 "one-ledger-many-feeders") | Critical | — |
| 03-30 | Per-**salesperson**, per-service income accounts (`TKTIncomeAccID`, `TRVIncomeAccID`, `XOIncomeAccID`, cargo) (03 §10; 07 §11) | Each person's GL balance *is* their commission | **PARTIAL** — one agent profit/loss pair, no per-service split (03-audit; MF-50); income accounts hang off the **task type**, not the person | Important | **SCHEDULE (P5.13, §5)** — the agent sub-ledger work already queued after W3 is the right home |
| 03-31 | XO / miscellaneous exchange-order document (03 §10) | Ad-hoc charge document grouped by `DocGroupID` | **NOT DISCUSSED** (audited as "business-scope") | Nice | **DEFER** — `label`-based free-form invoices cover it; revisit if a buyer needs true XO numbering |
| 03-32 | Hotel dual-currency supplier vs customer pricing (03 §10) | | **DONE** — hotel sub-accounts + `original_*`/`exchange_rate` (00; 15 A.4 #15) | Important | — |
| 03-33 | Visa service feeder (03 §10) | | **DONE** | Important | — |
| 03-34 | Air cargo (GSA) and sea-cargo job costing (03 §10) | | **DEFERRED by decision** — 11 §C3 | Nice | **SKIP** — no cargo business line exists |

### Reference 04 — Travel industry

| # | Blueprint element (ref §) | What the blueprint requires | Our status | Sev | Recommendation |
|---|---|---|---|---|---|
| 04-1 | 13-digit ticket = 3-digit airline code + serial + check digit (04 §1) | | **PLANNED** — decomposition into `airline_prefix`/`ticket_serial` in 15 §B.1 | Critical | **SCHEDULE (15 Stage B/C)** |
| 04-2 | Resolve airline from the 3-digit prefix via the party master (04 §1) | Airline becomes an accounting dimension | **PLANNED** — 15 §B.1 `validating_airline_id`; 11 §P5.8 (MF-27). `airlines.accounting_code` exists and is never matched today | Critical | **SCHEDULE (15 Stage C + P5.8)** |
| 04-3 | Check digit = serial mod 7 validation (04 §1) | | **DEFERRED by decision** — 11 §C3 (MF-31) | Nice | **SKIP** — GDS already validates |
| 04-4 | Multi-carrier PNR: capture per-segment carrier/class/route (04 §1) | | **PLANNED** — 15 §B.2 coupon table | Important | **SCHEDULE (15 Stage B)** |
| 04-5 | Ticket **stock control**: allocated series, Qty/AvailableQty/Booked, `isBSP` (04 §2) | | **PLANNED** — 15 §B.5, with an open `[USER-DECIDE]` on whether paper/e-stock ranges are actually allocated to us | Important | **SCHEDULE (15 Stage B)** — needs the owner's answer first (§6 Q7) |
| 04-6 | Voids return stock to the range (04 §2) | | **NOT DISCUSSED** | Nice | **DEFER** — falls out of 04-5 once scope is decided |
| 04-7 | Next-ticket-number generation per series, BSP vs non-BSP (04 §2) | | **NOT DISCUSSED** | Nice | **SKIP** — we do not issue stock; the GDS does |
| 04-8 | Missing-ticket report — serials in range never sold (04 §2, §7) | The control that catches lost/unreported stock | **PLANNED** — 15 §E report #10 | Important | **SCHEDULE (15 Stage E)** — degrades gracefully to "gaps in observed serials" |
| 04-9 | GDS→staging→validate→auto-invoice pipeline (04 §3) | | **DONE** — praised in 00 as "genuinely strong"; multi-feed (AIR/TXT/PDF/API/webhook) | Critical | — |
| 04-10 | MIR staging tables (`MirHeader`/`MirDetail`/`MirPayment`) (04 §3) | Structured per-ticket staging | **PARTIAL** — our equivalent is `tasks` + the 15 §B capture tables; per-tax-code and commission structure is **parsed then discarded today** (15 §A.2) | Critical | **SCHEDULE (15 Stages B–D)** — the single highest-value capture in that plan |
| 04-11 | Per-tax-code amounts captured individually (04 §3 `Tax1..3` + 20 `IT` codes) | | **PLANNED** — 15 §B.3 tax-lines table | Critical | **SCHEDULE (15 Stage B)** |
| 04-12 | **PCC allowlist validation** so you only import your own sales (04 §3) | Misfiled file imports under the wrong tenant | **PLANNED** — 15 §C.2 (warn, not block) against prod's `company_gds_pccs`; office codes are **hardcoded** in shared code today (MF-29) | **Critical** | **SCHEDULE (15 Stage C)** — a multi-tenant product cannot ship with `KWIKT` hardcoded |
| 04-13 | Duplicate / already-voided validation before invoicing (04 §3) | | **PLANNED** — 11 §P5.8 (MF-24); webhook retries can double-invoice today | Critical | **DO-NOW (W3)** — `InvoiceController::store`/`autoGenerateInvoice` are exactly the W3 files; add `Task::isInvoiced()` + the unique index while you're in there |
| 04-14 | Auto-invoice math: `Commission = ApplicableFare × AirlineComm%`; `Payable = (Fare − Comm) + Tax` (04 §4) | | **PLANNED** — FM-line commission capture 15 §B.1 (MF-28). Today payable is assumed = K-line total, **overstated for commissionable carriers** | Critical | **SCHEDULE (15 Stage C)** — this is a real money error, not a reporting gap |
| 04-15 | Link ticket back to invoice; mark batch invoiced (04 §4) | | **DONE** | Important | — |
| 04-16 | **Auto-void** duplicates to a house "void customer" and re-invoice (04 §4) | | **PLANNED** — 11 §P5.8 (MF-24) | Important | **SCHEDULE (P5.8)** |
| 04-17 | BSP statement ingestion (`IATAFareCash/Credit`, Tax, Commission, Incentive, `STCommAmt`, Void, `BSPTYPE`) (04 §5) | | **CONFLICTED** — 11 §P5.8 plans `bsp_statement_lines` (MF-25); 15 §F **explicitly defers BSP**. Owner **decided this week that ADM/ACM ingestion is REQUIRED** | **Critical** | **SCHEDULE (new P7.5, §5)** — resolve the conflict in favour of "required"; 15 §F already lists exactly what it needs |
| 04-18 | Ticket-by-ticket variance `SysPayable − BspPayable` (04 §5) | The core financial control of an IATA agency | **PLANNED (conflicted, as 04-17)** | Critical | **SCHEDULE (P7.5)** |
| 04-19 | **ADM** (agency debit memo): Dr cost/recovery, Cr airline payable (04 §5) | | **DECIDED REQUIRED**, posting home = memo module (11 §P5.3) | Critical | **SCHEDULE (P5.3 → P7.5)** |
| 04-20 | **ACM** (agency credit memo): Dr airline payable, Cr income/recovery (04 §5) | | **DECIDED REQUIRED** (as 04-19) | Critical | **SCHEDULE (P5.3 → P7.5)** |
| 04-21 | `BSPTYPE` classification ET/ADM/ACM/REFUND/EMD (04 §5) | | **PLANNED** — 15 §F item 1 | Important | **SCHEDULE (P7.5)** |
| 04-22 | Monthly BSP reconciliation ritual (~2 wks after period end) (04 §5) | Operational cadence, not just a report | **NOT DISCUSSED** | Important | **SCHEDULE (P7.5)** — schedule + worklist + sign-off, else the report is built and never run |
| 04-23 | Booking-class → cabin map per airline/region (04 §6) | | **PLANNED** — 15 §B.4 (`[USER-DECIDE]` names; seed data must come from the accountant) | Important | **SCHEDULE (15 Stage B)** |
| 04-24 | Incentive rules by airline/region/destination/class (04 §6) | | **PLANNED** — 15 §B.4 | Important | **SCHEDULE (15 Stage B)** |
| 04-25 | Incentive **accrual** as additional income, cleared against the BSP ACM (04 §6) | | **PLANNED** — 15 §E #11 display now, posting in Track 2 | Important | **SCHEDULE (P7.5)** — the accrual only makes sense next to ADM/ACM |
| 04-26 | GDS segment count with **infant handling** and per-PNR breakdown (04 §7; 05 §4) | Infants silently inflate every segment count today | **PLANNED** — 15 §B.1 `pax_type` + §E #2 | Important | **SCHEDULE (15 Stages B–E)** |
| 04-27 | Airline segment actual vs **target** with variance % (04 §7) | | **PLANNED** — 15 §B.6 + §E #9 (needs user-entered targets) | Nice | **SCHEDULE (15 Stage E)** |
| 04-28 | Stock, sales & voids per airline; void % (04 §7) | | **PLANNED** — 15 §E #4 | Important | **SCHEDULE (15 Stage E)** |
| 04-29 | **Void flow**: zero the sale, reassign to void customer, refuse when payment applied (04 §8) | | **PLANNED** — 11 §P5.8 (BUG-H7). Today reversal entries are selected **by description string** and silently miss auto-invoiced tickets — client credited, AR/revenue never reversed | **Critical** | **DO-NOW (W4)** — the refund/void wave touches these methods; `PostingService::reverse` already exists to do it properly |
| 04-30 | Bulk void, transactional (04 §8) | | **NOT DISCUSSED** | Nice | **DEFER** |
| 04-31 | Refund as `TrType=2` linked to the original, posting the reversal (04 §8) | | **PARTIAL** — `RefundController` exists (23 sites, W4); clawback account **misclassified as an asset** (MF-30); operator figures unvalidated | Critical | **DO-NOW (W4)** |
| 04-32 | Refunds flow through BSP as `REFUND` type (04 §8) | | **PLANNED (conflicted, as 04-17)** | Important | **SCHEDULE (P7.5)** |
| 04-33 | Hotel dual-currency margin correctness (04 §9) | | **DONE** | Important | — |
| 04-34 | Cargo (air GSA / sea job costing) specifics (04 §9) | | **DEFERRED by decision** — 11 §C3 | Nice | **SKIP** |

### Reference 05 — Reporting

| # | Blueprint element (ref §) | What the blueprint requires | Our status | Sev | Recommendation |
|---|---|---|---|---|---|
| 05-1 | **One canonical query shape** every report specialises (05 §1) | The reason ~100 reports agree with each other | **PLANNED** — `LedgerReportQuery` in 11 §P5.4 (MF-32). Four divergent calculators today | Critical | **SCHEDULE (P5.4)** |
| 05-2 | Session gate on every report (05 §1.1, §5.1) | | **PARTIAL** — 11 §P0/§P4; report authz was menu-only (BUG-C5) | Critical | **SCHEDULE (P4)** — the entitlements track owns route gating |
| 05-3 | `Posted = 1` filter — drafts never count (05 §1.2) | | **PARTIAL** — no draft state exists yet (see 02-26) | Important | **SCHEDULE (P5.1)** |
| 05-4 | Period filter on `DocDt`, never on row-creation time (05 §1.2) | | **PLANNED** — 11 §P5.4 (BUG-C4: P&L uses `created_at`, TB uses `transaction_date`, so they can never tie out); 19 §R4 already binds the reports track to `transaction_date` | Critical | **SCHEDULE (P5.4)** |
| 05-5 | `SubType <> 'OJV'` — opening journal is opening, not movement (05 §1.2, §5.5) | | **PLANNED** — 11 §P5.2/§P5.4 | Critical | **SCHEDULE (P5.2)** |
| 05-6 | Optional branch / cost-centre filters on every report (05 §1.2) | | **PARTIAL** — branch exists (TB filter is on the wrong table, MF-37); cost-centre column doesn't exist (MF-43) | Important | **DO-NOW (W3, column)** + **SCHEDULE (P5.4)** |
| 05-7 | Bottom-up roll-up level 8→1 via `ParentAccID_FK` (05 §1.4) | Totals every group without storing group balances | **PARTIAL** — `CoaController::childAccount` does a version of it; canonical roll-up in 11 §P5.4 | Important | **SCHEDULE (P5.4)** |
| 05-8 | Spread amounts into per-level columns for indented display (05 §1.5) | Statement presentation | **NOT DISCUSSED** | Nice | **SCHEDULE (P7)** — presentation detail of the auditor exports |
| 05-9 | Last-year comparison column (05 §1.6) | | **PLANNED** — 10 Phase 6 ("comparative-period support"); **not restated in 11 §P5.4** | Important | **SCHEDULE (P5.4)** — make sure it survives the 10→11 handoff |
| 05-10 | Order by `AccGroup` (05 §1.7) | Statement ordering | **NOT DISCUSSED** — depends on 01-7 | Nice | **SCHEDULE (P7)** with 01-7 |
| 05-11 | Sign rules by `AccType`; P&L flips L/I for presentation (05 §2) | | **PLANNED** — 11 §P5.4 (BUG-M3: `abs($total)` today) | Critical | **SCHEDULE (P5.4)** |
| 05-12 | Opening = prior movement + OJV, **excluding prior-year I/E** (05 §2) | | **PLANNED** — one `OpeningBalanceService` in 11 §P5.2 (BUG-H5: three contradictory definitions today) | Critical | **SCHEDULE (P5.2)** |
| 05-13 | **Trial Balance** — opening + period Dr/Cr + closing (05 §3) | | **DONE (core)** — `TrialBalanceService` is the one thing 00 says to preserve | Critical | — |
| 05-14 | TB variants: monthly, comparative, branch-consolidated (05 §3) | | **PARTIAL** — branch filter is on the wrong column (MF-37); no comparative | Important | **SCHEDULE (P5.4)** |
| 05-15 | TB injects a **live P&L figure** when the year isn't closed (05 §3) | TB balances mid-year | **NOT DISCUSSED** | Important | **SCHEDULE (P5.2/P5.4)** — without it the TB does not balance between closes |
| 05-16 | **Balance Sheet** as-of a date, with period profit folded into equity (05 §3) | | **PLANNED** — 11 §P5.4 (MF-33). Does not exist at all today | Critical | **SCHEDULE (P5.4)** |
| 05-17 | Balance Sheet group-summary + last-year/last-year-month comparatives (05 §3) | | **NOT DISCUSSED** | Important | **SCHEDULE (P5.4)** — bundle with 05-9 |
| 05-18 | **Profit & Loss** for a period (05 §3) | | **PARTIAL** — exists but filters `created_at` and hardcodes `4*`/`5*` level-3 codes so other accounts silently vanish (MF-35) | Critical | **SCHEDULE (P5.4)** |
| 05-19 | P&L variants: branch/cost-centre, month-wise 12-column, progressive cumulative (05 §3) | | **PLANNED** — 11 §P5.4 (MF-35) | Important | **SCHEDULE (P5.4)** |
| 05-20 | **Account Ledger** with running balance (05 §3) | The biggest report | **PARTIAL** — exists in several divergent forms; canonical rebuild in 11 §P5.4 | Critical | **SCHEDULE (P5.4)** |
| 05-21 | Ledger in **local or foreign currency** (05 §3) | | **NOT DISCUSSED** | Important | **SCHEDULE (P5.4)** — data is on the line already; it is a display toggle |
| 05-22 | Ledger consolidates multi-line invoices into one line (05 §3) | Readability for an accountant | **NOT DISCUSSED** | Nice | **SCHEDULE (P5.4)** |
| 05-23 | Payroll documents hidden unless authorised (05 §3, §5.3) | | **NOT DISCUSSED** (audited only — 05/07 audits mention `HRJV`). Note we now post `SALARY_EXPENSE`/`SALARY_PAYABLE` through the engine, so salary lines are **visible to anyone with ledger access today** | Important | **SCHEDULE (P4 or the entitlements track)** — see §6 Q9 |
| 05-24 | **Receivable / Payable Ageing** — summary + detail, buckets (05 §3) | | **PLANNED** — 11 §P5.4 (MF-34) | Critical | **SCHEDULE (P5.4)** |
| 05-25 | Ageing by branch / cost-centre, local or FC (05 §3) | | **NOT DISCUSSED** | Nice | **DEFER** |
| 05-26 | **Outstanding / Statement** per customer & supplier as of a date (05 §3) | | **PARTIAL** — 11 §P7 numbered statements (MF-40); brought-forward opening rows missing; AP tree resolved by hardcoded English names | Critical | **SCHEDULE (P5.4 + P7)** |
| 05-27 | **Cash Flow** report (05 §3) | | **PLANNED** — 11 §P5.4 (MF-38) | Important | **SCHEDULE (P5.4)** |
| 05-28 | **GL / Bank Reconciliation** report (05 §3) | | **PLANNED** — 11 §P5.10 (MF-45) | Important | **SCHEDULE (P5.10)** |
| 05-29 | **Apply / Unapplied / Payment** report (05 §3) | | **PLANNED** — 11 §P5.4 (MF-39); data is ready today | Important | **SCHEDULE (P5.4)** — cheap win |
| 05-30 | **Day Book** — all documents for a day (05 §3) | The accountant's daily proof sheet | **NOT DISCUSSED** (audited only — 05 audit) | Important | **SCHEDULE (P5.4)** — trivial once `LedgerReportQuery` exists; conspicuous by absence in a demo |
| 05-31 | **Daily Sales (DSR)** (05 §3, §4) | | **PLANNED** — 15 §E report #1 (ticket-wise, with fare/tax/commission) | Important | **SCHEDULE (15 Stage E)** |
| 05-32 | GDS Segment Count report (05 §4) | | **PLANNED** — 15 §E #2 | Important | **SCHEDULE (15 Stage E)** |
| 05-33 | Airline Segment Comparison vs target (05 §4) | | **PLANNED** — 15 §E #9 | Nice | **SCHEDULE (15 Stage E)** |
| 05-34 | Airline Incentive report (05 §4) | | **PLANNED** — 15 §E #11 | Important | **SCHEDULE (15 Stage E + P7.5)** |
| 05-35 | BSP Reconciliation report (05 §4) | | **CONFLICTED** — see 04-17 | Critical | **SCHEDULE (P7.5)** |
| 05-36 | BSP Summary / Sales Analysis by airline/branch/IATA/`BSPTYPE` (05 §4) | | **NOT DISCUSSED** | Important | **SCHEDULE (P7.5)** |
| 05-37 | Missing Ticket report (also hotel/XO/cargo docs) (05 §4) | | **PLANNED** — 15 §E #10 (ticket only) | Important | **SCHEDULE (15 Stage E)** |
| 05-38 | Stock, Sales & Voids (05 §4) | | **PLANNED** — 15 §E #4 | Important | **SCHEDULE (15 Stage E)** |
| 05-39 | Air / Sea Cargo reports (05 §4) | | **DEFERRED by decision** — 11 §C3, 15 §E note (c) | Nice | **SKIP** |
| 05-40 | Hotel Voucher report (05 §4) | | **PLANNED** — 15 §E #7 (no new capture needed) | Important | **SCHEDULE (15 Stage E)** — cheapest win in the catalogue |
| 05-41 | Refund report with payment status (05 §4) | | **PLANNED** — 15 §E #3 | Important | **SCHEDULE (15 Stage E)** |
| 05-42 | Airline / Hotel PNR reports (05 §4) | | **PLANNED** — 15 §E #6 | Nice | **SCHEDULE (15 Stage E)** |
| 05-43 | Management sales reports (by customer/airline/consultant/service; comparison, variance, top-N) (05 §4) | | **PLANNED** — 15 §E #8 (ship 3–4 variants first, `[USER-DECIDE]` which) | Important | **SCHEDULE (15 Stage E)** — see §6 Q8 |
| 05-44 | Tour Code report (negotiated fares) (05 §4) | | **PLANNED** — 15 §E #5 (needs FT-line capture) | Nice | **SCHEDULE (15 Stages C/E)** |
| 05-45 | Discrepancy / cost-saving / deviation exception reports (05 §4) | | **PLANNED** — 15 §E #12 | Nice | **SCHEDULE (15 Stage E)** |
| 05-46 | Branch / account **permission row filters** on reports (05 §5.2) | A user only sees their branches and permitted accounts | **PARTIAL** — 11 §P4 policies + entitlements track; the blueprint's per-menu `WHERE`-clause filter has no analog (MF-49) | Critical | **SCHEDULE (P4 / entitlements track)** |
| 05-47 | Reports built as thin specialisations of shared building blocks (05 §5 closing) | Why ~100 reports agree | **PLANNED** — 11 §P5.4 + 15 §E shared report base | Critical | **SCHEDULE (P5.4)** |

### Reference 06 — Data model, numbering & config

| # | Blueprint element (ref §) | What the blueprint requires | Our status | Sev | Recommendation |
|---|---|---|---|---|---|
| 06-1 | The core table set (accounts, header/detail + logs, monthly balance, apply registry, operational, travel feeders, masters, sub-ledgers) (06 §1) | | **PARTIAL** — most exist or are planned; monthly-balance table (02-20) and the `*Log` mirrors (02-24) are the holes | Important | See 02-20, 02-24 |
| 06-2 | Universal `CreateID/CreateDt/ModID/ModDt` + `*Log` on **every** base table (06 §1) | | **PARTIAL** — MF-13, downgraded to attribution-only | Important | **SCHEDULE (§5 audit sub-phase)** |
| 06-3 | Number format `TYPE / BRANCH / YY / SEQ` (06 §2) | | **DONE** — `DEFAULT_MASK = {TYPE}{BRANCH:4}-{YYYY}-{SEQ:5}` (doc 17 §2) | Important | — |
| 06-4 | `SerialSchemas` (mask, initial, increment, padding, max/cycle, `Last_Serial`) (06 §2) | | **DONE** — `serial_schemas` + `SequenceService` | Critical | — |
| 06-5 | `SerialNumbersShelf` reserve/hold queue so concurrent users never collide (06 §2) | | **NOT DISCUSSED** — we use `SELECT … FOR UPDATE` instead. Known consequence: the race **loser burns a number** (doc 17 §4) | Nice | **SKIP** — gaps are acceptable, duplicates are not; document the gap behaviour for auditors |
| 06-6 | Reset behaviour `SerialSchemaFormat` 0 = branch/year, 1 = branch/month, 2 = month (06 §2) | | **PARTIAL** — per-branch-per-year only | Nice | **DEFER** |
| 06-7 | Copy schemas forward at year-end (06 §2) | Numbering keeps working in January | **NOT DISCUSSED** | Important | **SCHEDULE (P5.2)** — bundle into `close-year`, or the first document of the new year fails |
| 06-8 | Posted number is permanent; PK on (type, value, branch, year) prevents reuse (06 §2) | | **PARTIAL** — `unique(company_id, doc_type, reference_number)` exists but **cannot see legacy rows** (`doc_type IS NULL`), and `post()` has **no handler for a unique violation** (doc 17 §3 gate 3, §4 MEDIUM) | Critical | **DO-NOW (before go-live)** — run the seeder, add the typed exception |
| 06-9 | Generic `tblMaster` polymorphic lookup table (06 §3) | | **SKIPPED by decision** — 11 §C3 (per-table lookups accepted) | Nice | **SKIP** |
| 06-10 | `tblTRVControls` travel-service option lists (06 §3) | Room types, visa types, transfers | **NOT DISCUSSED** | Nice | **DEFER** — task schemas cover it today |
| 06-11 | **Branch** dimension on every line (06 §4) | | **DONE** — `branch_id` stamped (sometimes `?? 0` — MF-15) | Critical | — |
| 06-12 | **Cost-centre** dimension on every line, even when the feature is off (06 §4) | Switch on cost-centre P&L later with zero migration | **PLANNED** — 11 §P5.7 (MF-43) | Important | **DO-NOW (W3)** — the whole point is to add the column *before* more rows accumulate; it is an `S` task |
| 06-13 | Branch GL pointers `CashAccID` / `BankAccID` / `BranchAccID` / discount (06 §4) | Feeders post to the right branch accounts | **PARTIAL** — 10 Phase 8 (MF-46); **not carried into 11 at all** | Important | **SCHEDULE (P5.3)** — routing is scattered onto `users.acc_bank_id` and name-walking today |
| 06-14 | Unified party master with `IsCust`/`IsSupp`/`IsAirline` + pointers + currency + credit limit + GDS codes (06 §5) | | **PLANNED** — 11 §P5.3 + doc 13 §B.2 (four separate masters today; linkage is inverted) | Critical | **SCHEDULE (P5.3)** |
| 06-15 | `SystemCurrency` base-currency parameter (06 §6) | | **PLANNED** — MF-42; `'KWD'` hardcoded at 39+ sites | Critical | **SCHEDULE (P5.3)** — do it with the party/param sweep |
| 06-16 | `ProfitLossAccount` retained-earnings parameter (06 §6) | | **DONE (registry slot)** — `RETAINED_EARNINGS` purpose code; **posted to** in 11 §P5.2 | Critical | **SCHEDULE (P5.2)** |
| 06-17 | `FirstFinancialYear` / `FinancialStartingDate` / `EndingDate` (06 §6) | | **PLANNED** — `accounting_periods`, 11 §P5.1 | Critical | **SCHEDULE (P5.1)** |
| 06-18 | `PayableControlAcc` / `ReceivableControlAcc` (06 §6) | | **DONE (registry slots)** — purpose codes exist | Critical | — |
| 06-19 | `PayableAccHeaderGroup` / `ReceivableAccHeaderGroup` — where new party accounts are created (06 §6) | | **PLANNED** — 11 §P5.3 / doc 13 §B.3 (tree placement avoiding the mixed-parent trap) | Critical | **SCHEDULE (P5.3)** |
| 06-20 | `SuspenseControlAccount` (06 §6) | Where an unresolvable plug goes | **DONE (registry slot)** — used as the repair target in 11 §P3 | Critical | — |
| 06-21 | `InterCompanyControlAccount` (06 §6) | | **SKIPPED by decision** — 11 §C3 (single legal entity per tenant) | Nice | **SKIP** |
| 06-22 | `ExchangeRateEarningsAcc` FX gain/loss (06 §6) | | **PLANNED** — `FX_GAIN_LOSS` → `5219`, activated in 11 §P5.6 (dead code today) | Important | **SCHEDULE (P5.6)** |
| 06-23 | `DiscountAcc` (06 §6) | | **NOT DISCUSSED** (audited only, 06) | Nice | **DEFER** |
| 06-24 | `systemcustomeracgroupparentgroup` / `systemsupplieracgroupparentgroup` (06 §6) | | **PLANNED** — same as 06-19 | Critical | **SCHEDULE (P5.3)** |
| 06-25 | `AutoLockInvoices` / `AutoLockRefunds` (06 §6) | | **PARTIAL** — `Lockable` exists, enforced only in `InvoiceController` | Important | **SCHEDULE (P5.1)** |
| 06-26 | `AutoRateAdjustment` (06 §6) | | **PLANNED** — 11 §P5.6 | Nice | **SCHEDULE (P5.6)** |
| 06-27 | `VATEnable` / `VAT*` (06 §6) | | **PLANNED** — 11 §P9 (GCC VAT + ZATCA) | Important | **SCHEDULE (P9)** — Kuwait ships VAT-off |
| 06-28 | `AccountsClosingRollbackDisable` (06 §6) | Year-end is irreversible | **PLANNED** — 11 §P5.2 | Important | **SCHEDULE (P5.2)** |
| 06-29 | Posting-affecting config keys are validated and read once per transaction (06 §6 design guidance) | | **PARTIAL** — `system_accounts` is validated and resolved up-front (DONE); the non-account keys (06-15, 06-17, 06-25) are not | Important | **SCHEDULE (P5.3)** |

### Reference 07 — Sub-ledgers, year-end & cross-cutting

| # | Blueprint element (ref §) | What the blueprint requires | Our status | Sev | Recommendation |
|---|---|---|---|---|---|
| 07-1 | Fixed-asset register + monthly depreciation + disposal, posted as JVs (07 §1) | | **PLANNED** — 11 §P5.5 (MF-48); dead `Asset` model today | Important | **SCHEDULE (P5.5)** — an agency owns offices and cars; auditors ask |
| 07-2 | Depreciation schedule kept separate from posting (preview before commit) + un-post (07 §1) | | **PLANNED** — 11 §P5.5 states the separation | Important | **SCHEDULE (P5.5)** |
| 07-3 | Budget per account/year/branch/cost-centre with 12 monthly columns; **no GL posting** (07 §2) | | **PLANNED** — 11 §P5.9 (`budgets`/`budget_lines`). Note: 11 widened `accounts.budget_balance`/`variance` in P1.0 with no module using them | Nice | **SCHEDULE (P5.9)** |
| 07-4 | Budget-vs-actual variance report (07 §2) | | **PLANNED** — 11 §P5.9 as a P5.4 specialisation | Nice | **SCHEDULE (P5.9)** |
| 07-5 | Recurring journal templates + schedule + frequency + run tracking (07 §3) | Rent accruals, monthly provisions | **PLANNED** — 11 §P5.11 (idempotent per period by construction) | Important | **SCHEDULE (P5.11)** |
| 07-6 | FX revaluation of open FC balances, unrealized gain/loss posted as JV (07 §4) | | **PLANNED** — 11 §P5.6 | Important | **SCHEDULE (P5.6)** |
| 07-7 | `tblAccRateAdj*` links each adjustment to the open item so it reverses cleanly at settlement (07 §4) | | **PLANNED** — `fx_rate_adjustments` in 11 §P5.6 | Important | **SCHEDULE (P5.6)** |
| 07-8 | Realized FX booked in the PV flow at settlement (07 §4) | | **PLANNED** — 11 §P5.6 | Important | **SCHEDULE (P5.6)** |
| 07-9 | Memo module as the entry front for CRN/DBN/ADM-ACM/commission adjustments; multi-currency; single- or multi-line (07 §5) | | **PLANNED** — 11 §P5.3 | Critical | **SCHEDULE (P5.3)** |
| 07-10 | Bank/card statement import into a staging table (07 §6.1) | | **PLANNED** — `bank_statement_lines` + CSV, 11 §P5.10 (OFX deferred, §C3) | Important | **SCHEDULE (P5.10)** |
| 07-11 | Match cards on **authorization code**, cheques on cheque no / clearance date (07 §6.2) | | **PLANNED** — 11 §P5.10; `auth_no` is captured and never used | Important | **SCHEDULE (P5.10)** |
| 07-12 | Mark matched lines `Reconciled` with the statement date (07 §6.3) | | **PARTIAL** — flag exists, date/amount columns do not (see 02-9) | Important | **DO-NOW (W3, columns)** |
| 07-13 | Reconciliation report: reconciled vs unreconciled, stale exceptions (07 §6.4) | | **PLANNED** — 11 §P5.10 | Important | **SCHEDULE (P5.10)** |
| 07-14 | Card/gateway settlements reconcile to the **net**, fee on its own line explains the difference (07 §6) | | **DONE** — 3-line gateway receipt with a separate fee leg | Important | — |
| 07-15 | Apply / Release / Auto-allocate as first-class operations (07 §7) | | **PARTIAL** — apply DONE operationally; release PLANNED (P5.3); auto-allocate NOT DISCUSSED (03-22) | Critical | **SCHEDULE (P5.3)** |
| 07-16 | Year-end close, 5 ordered steps, refuse out-of-order years (07 §8) | | **PLANNED** — 11 §P5.2 `close-year` | Critical | **SCHEDULE (P5.2)** |
| 07-17 | Close excludes income/expense from carry-forward; nominal accounts reset (07 §8.3) | | **PLANNED** — 11 §P5.2 | Critical | **SCHEDULE (P5.2)** |
| 07-18 | Net profit posted to a "Profit & Loss `<year>`" account under retained earnings (07 §8.4) | | **PLANNED** — 11 §P5.2 (`3400` seeded, never posted to) | Critical | **SCHEDULE (P5.2)** |
| 07-19 | Advance the financial-period parameters on close (07 §8.5) | | **PLANNED** — 11 §P5.2 | Critical | **SCHEDULE (P5.2)** |
| 07-20 | Inter-company mirror posting via a bridge/suspense account (07 §9) | | **SKIPPED by decision** — 11 §C3 | Nice | **SKIP** |
| 07-21 | Users on the staff master with a rank (super-user vs restricted) (07 §10) | | **PARTIAL** — Spatie RBAC exists and 00 calls the core solid | Important | — |
| 07-22 | Password policy: encryption, history, complexity, renewal (07 §10) | | **PLANNED** — 10 Phase 8 (MF-49); **not carried into 11** | Important | **SCHEDULE (P7)** — a security questionnaire item in every enterprise sale |
| 07-23 | Menu permissions with an optional row-restricting `WHERE` filter (07 §10) | Restrict a screen to certain branches/customers | **NOT DISCUSSED** (audited only — 07) | Important | **SCHEDULE (entitlements track)** — see §6 Q9 |
| 07-24 | Branch / cost-centre scoping per user (07 §10) | | **PARTIAL** — branch scoping exists; cost-centre doesn't exist yet | Important | **SCHEDULE (P4 + P5.7)** |
| 07-25 | Sessions: one active per user/app, IP/device logged, inactivity timeout (07 §10) | | **PLANNED** — 10 Phase 8 (MF-49); **not in 11** | Important | **SCHEDULE (P7)** |
| 07-26 | Protected users whose permissions cannot be edited (07 §10) | | **NOT DISCUSSED** | Nice | **DEFER** |
| 07-27 | Integrity routine flagging unbalanced docs, zero/invalid currency or rate, base-vs-FC mismatch (07 §10) | | **PARTIAL** — `findUnbalancedTransactions` exists; the **other two checks are missing** (MF-49) | Important | **SCHEDULE (P3)** — ship all three with the drift checker (02-23) |
| 07-28 | Per-service commission accounts on the staff record, `AccTransType=8` (07 §11) | Each person's GL balance is their commission | **PARTIAL** — see 03-30 | Important | **SCHEDULE (P5.13, §5)** |
| 07-29 | Agent commission rate/bracket table (07 §11 optional) | | **DEFERRED by decision** — 11 §C3 | Nice | **SKIP** |
| 07-30 | Staff **sales targets** (monthly target vs actual, variance) feeding management reports (07 §11 optional) | | **NOT DISCUSSED** — airline segment targets are planned (15 §B.6); **staff** targets are not | Nice | **DEFER** |
| 07-31 | Document attachments (file + size limit, attached by doc type/number) (07 §12) | | **PARTIAL** — no universal registry (MF-50); not in 11 or its defer list | Important | **SCHEDULE (P7)** — "attach the supplier invoice to the voucher" is table stakes for an accounts department |
| 07-32 | Email / SMS with per-day quotas and sender (07 §12) | | **PARTIAL** — channels exist; quotas/sender registry do not | Nice | **DEFER** |
| 07-33 | Reminder / follow-up task engine, one-time or recurring, multi-channel (07 §12) | | **PARTIAL** — invoice reminders exist via `invoices.due_date` | Nice | **DEFER** |

### SKILL.md — the eight golden rules (cross-cutting scorecard)

| # | Golden rule | Status |
|---|---|---|
| GR-1 | One tree, post only to leaves; type derived from position | **DONE** (engine) / **PARTIAL** (creation sites — 01-1, 01-6) |
| GR-2 | One header + N balanced lines, always | **DONE** (engine) / **PARTIAL** until W3–W7 finish |
| GR-3 | Base **and** original currency on every line; effective-dated rates | **DONE** (line) / **PLANNED** (effective-dated — 02-30) |
| GR-4 | Maintain balances eagerly; always reverse before re-applying | **PARTIAL** — reverse-then-apply DONE; eager balances are an **open decision** (01-12, 02-19, 02-20) |
| GR-5 | Posted-only reporting; opening journals are special | **PLANNED** — P5.1 / P5.2 |
| GR-6 | Open-item AR/AP via apply | **PARTIAL** — operational DONE, GL half PLANNED (P5.3) |
| GR-7 | Feeders emit documents; they don't invent accounting | **DONE in principle** (`system_accounts` + `PostingSeam`); **W3/W4 still hand-roll** |
| GR-8 | Everything is dimensioned and audited | **PARTIAL** — branch DONE, cost-centre PLANNED, audit mirrors downgraded (02-24) |

---

## 3. "NOT DISCUSSED" shortlist — every uncovered element, explained for a non-accountant

Grouped by layer. Each entry: **what it is → why the blueprint has it → what breaks without it.**

### Layer 1 — Chart of accounts

**N1. `AccGroup` statement rollup key (01-7, ref 01 §2/§4)**
A short numeric code on every account that says where it sits in the *statement*, growing two digits per level (`110040100`), so reports can group and order without walking the tree.
The blueprint uses it as the sort key and grouping key for every financial statement.
Without it, our statements group by whatever the query happens to order by. It works for one company with a tidy COA; it produces visibly wrong-looking statements the first time a customer builds a deep or irregular tree. Also makes COA import/export non-idempotent (MF-7).

**N2. Sixth root "APPROPRIATIONS" and typed roots (01-16, ref 01 §3)**
The blueprint seeds six fixed top-level accounts; we seed five and identify them by matching English names.
The sixth (APPROPRIATIONS) is where transfers to reserves and tax provisions go at year-end.
Without it, year-end appropriation entries have nowhere natural to land, and name-matched roots break the moment a tenant renames "Assets" or runs an Arabic-first COA.

**N3. Alternate account codes for import/export (01-14, ref 01 §2)**
Two spare columns holding the account's code in *someone else's* system.
The blueprint has them because agencies file to an external auditor or a group parent that uses different codes.
Without them, every export needs a hand-maintained mapping spreadsheet — a recurring support cost and a common RFP question.

**N4. Multi-currency gate per account (01-10) and default account currency**
A flag saying "this account may hold foreign-currency entries".
Stops someone booking a USD entry into a KWD-only bank account.
Low risk for us because the engine already enforces rate/amount consistency per line — this is the second lock on a door that has one.

### Layer 2 — Posting engine

**N5. Monthly balance buckets (02-20, ref 02 §4b)** — *the most consequential item in this section.*
A small summary table holding, for each account × month × branch × cost-centre, the total debits and credits — updated as each line posts.
The blueprint's stated reason is performance: the trial balance, every ledger, and every ageing report read the buckets instead of re-summing millions of raw lines.
Right now we re-sum. At 73k lines that is fine. At a 20-tenant SaaS with several years of history, trial balance and ageing get slow in a way that is very expensive to retrofit (you must backfill years of buckets and prove they match). This was in roadmap 10 Phase 3 and quietly vanished from plan 11 — it is the clearest example of something that *looks* covered and isn't.

**N6. Audit mirror tables / version history (02-24, 01-15, 06-2, ref 02 §6)**
Every financial table gets a shadow `*_log` table that records every insert, update and delete.
The blueprint has it so an auditor can ask "who changed this and when" and get an answer for any row, ever.
We plan `created_by`/`updated_by` only — that answers "who made it", not "what did it look like before". An external auditor performing a controls review will flag this. It is also what makes drift diagnosable when something goes wrong.

**N7. Running-balance history from the account log (02-25)** — the blueprint itself says to decide deliberately; high volume, low value for us. Listed only so the decision is recorded.

**N8. Denormalised `DC` side column (02-5)** — pure performance micro-optimisation; derivable. Recorded and dismissed.

### Layer 3a — Transactions & AR/AP

**N9. Credit control: credit limits and blacklisting (03-24, ref 03 §8)** — *the biggest pure gap in the matrix.*
Each customer/agent gets a credit limit and a "from this date" marker; the system recomputes what they currently owe and how much credit is left, and **blocks or warns at invoice-save time** if a new sale would breach it. A blacklist flag is a hard stop.
The blueprint has it because a travel agency's core credit risk is selling tickets to an agent who never pays — the money is owed to the airline regardless.
Without it, nothing stops a sale to a customer already 90 days overdue. We have no limit field at all (`clients.credit` is a *prepaid top-up balance*, and `credit_facility` is dead schema — 19 §3d). Our audit noticed this and wrote a recommendation, but it never got an MF-id or a phase, so no wave owns it. Every agency demo will ask for it.

**N10. FIFO auto-allocation (03-22, ref 03 §7, 07 §7)**
When a payment arrives without an invoice reference, the system automatically applies it to the customer's oldest open invoices first.
The blueprint has it because most receipts arrive as round-sum payments against many invoices.
Without it every receipt must be hand-matched invoice by invoice. It is small work on top of the apply service we're already building — but only if it is scoped in, otherwise it never gets built.

**N11. CTR control / accrual-timing postings (03-4, ref 03 §2/§3)**
Extra zero-net documents that put the supplier payable on the ticket's issue date and the income on the travel date, routed through control accounts.
The blueprint has it so payable and income sub-ledgers tie to the GL when billing date ≠ service date.
The blueprint explicitly says to build the simple version first, so deferring is sanctioned. The cost is that we cannot produce accrual-basis (as opposed to cash-timing) statements — which matters only if a buyer's auditor asks.

**N12. Front-office counter receipt (03-14)**, **N13. Bulk daily sheet BDS (03-5)**, **N14. XO/exchange-order document (03-31)** — three document types with no business line behind them today. Recorded so the decision is explicit rather than accidental.

### Layer 3b — Travel industry

**N15. BSP monthly reconciliation as a *ritual* (04-22, ref 04 §5)**
Not the report — the operating rhythm: statement arrives ~2 weeks after period end, someone runs the match, differences become a worklist someone owns.
The blueprint calls it out as a monthly ritual because that is the control.
Without it we build a BSP report and nobody runs it. Needs a schedule, a worklist, and a sign-off step — not just a screen.

**N16. BSP summary / sales analysis by airline, branch, IATA number and `BSPTYPE` (05-36)**
The roll-up view above the ticket-by-ticket match.
Without it, reconciliation is only usable ticket by ticket — fine for finding one error, useless for "are we materially in line with BSP this month".

**N17. Void returns stock to the range (04-6)** and **N18. next-number-in-series generation (04-7)** — both only matter if we hold allocated ticket stock, which is exactly the open question in 15 §B.5. Answer that (§6 Q7) and these resolve themselves.

**N19. Bulk void, transactional (04-30)** — nice-to-have operational convenience.

### Layer 4 — Reporting

**N20. Day Book (05-30, ref 05 §3)**
A single screen listing every document posted on a given day.
The blueprint has it because it is the accountant's daily proof sheet — the thing they open every morning.
It is trivial to build once the canonical query exists, and its absence is instantly visible in a demo to a real bookkeeper.

**N21. Live P&L injection into the Trial Balance (05-15, ref 05 §3)**
Before a year is closed, the profit for the year-to-date isn't in any equity account yet, so a naive trial balance doesn't balance. The blueprint injects the computed P&L figure as a synthetic line.
Without it, our trial balance will visibly fail to balance for eleven months of every year — the single most damaging thing an accountant can see.

**N22. Ledger in foreign currency (05-21)**, **N23. multi-line invoice consolidation in the ledger (05-22)**, **N24. per-level indent columns (05-8)**, **N25. `AccGroup` ordering (05-10)**, **N26. Balance-Sheet group summary + comparatives (05-17)** — presentation features that separate "a report" from "a statement an accountant will sign". Cheap individually, and all live in the same P5.4/P7 work.

**N27. Payroll confidentiality (05-23, ref 05 §3/§5.3)**
Payroll journals are hidden from anyone without the payroll right.
This one has become **live and material**: we now post `SALARY_EXPENSE` / `SALARY_PAYABLE` through the engine (the `AgentController` salary cutover), so salary amounts are visible to anyone who can open the ledger today.

### Cross-cutting infrastructure & security

**N28. Serial-number shelf / hold queue (06-5)** — we use row locks instead; consequence is that a race loser burns a number, producing gaps. Acceptable; record it for auditors who ask why numbering has gaps.

**N29. Copy serial schemas forward at year-end (06-7)**
The number series are keyed by year; someone must create next year's rows.
Without it the first document posted in January fails. It is two lines inside `close-year` — but only if someone remembers.

**N30. Serial reset modes per month (06-6)**, **N31. `DiscountAcc` (06-23)**, **N32. `tblTRVControls` service option lists (06-10)** — low-impact config parity items.

**N33. Menu permissions with row-level `WHERE` filters (07-23, ref 07 §10)**
Beyond "can this user open this screen", the blueprint lets you restrict *which rows* they see inside it (e.g. only their own branch's customers).
Without it, permission is all-or-nothing per screen — so a branch manager who needs the ledger sees every branch's ledger.

**N34. Password policy: history, complexity, renewal (07-22)** and **N35. session policy: single active session, IP logging, inactivity timeout (07-25)**
Both were scheduled in roadmap 10 Phase 8 (MF-49) and then **did not survive into plan 11**. They are standard security-questionnaire line items in any B2B sale.

**N36. Protected users (07-26)** — accounts whose permissions can't be edited even by an admin. Nice-to-have.

**N37. Staff sales targets (07-30)** — we plan *airline* segment targets (15 §B.6) but not per-consultant targets. Purely a management-reporting nicety.

**N38. Document attachments registry (07-31, ref 07 §12)**
Attach the supplier's invoice PDF, the bank slip, the ADM letter to the voucher it belongs to.
The blueprint keeps it as a universal registry (attach by document type + number) rather than per-module uploads.
Without it, an accounts department keeps its evidence in a shared drive and the audit trail stops at the ledger. This is table stakes for a finance product and it appears nowhere in plan 11 — not even in the deferral list.

---

## 4. "PARTIAL" shortlist — what exists, and precisely what is still missing

**P1. `AccountService` exists but is not the only door (01-1, 01-6, 01-17).** The service enforces all nine creation rules, but the 10+ legacy creation sites still create accounts directly and the observer backstop ships **disabled by default**. *Missing:* refactor the call sites, flip `account_observer.enabled` on, make `account_type` non-fillable and backfill it.

**P2. `actual_balance` is in limbo (01-12, 02-19) — the most urgent item here.** The engine deliberately stopped maintaining the live balance column, but 115 references across 20+ files still read and write it, and 41.5% of dev accounts already disagree with the ledger. Plan 11 Appendix C explicitly flags this as an unclosed decision and doc 17 makes it a cutover gate. *Missing:* one decision (widen and maintain vs. migrate every reader onto `TrialBalanceService`) executed before W3 touches `InvoiceController`, which is one of the readers.

**P3. Open-item settlement lives in two places (03-19, 03-20, 07-15).** `payment_applications` correctly tracks partial settlement operationally, but the GL side is an all-or-nothing `reconciled` flag set on user-selected lines **without checking the amounts**, and nothing keeps the two in step. *Missing:* `journal_entries.settled_amount`, an apply/release service that is the only writer of it, a never-negative guard, supplier-side application, and a guarded un-apply that reverse-posts instead of deleting.

**P4. Credit notes exist only inside refunds (03-15, 03-16).** The economics are right where they appear, but there is no standalone document, no way to *increase* a customer balance (debit note), and no place at all to record a non-refund allowance or commission adjustment. *Missing:* the memo module — which is now also the landing place for ADM/ACM.

**P5. Draft/posted lifecycle is a column, not a workflow (02-26, 05-3).** `posting_status` exists and defaults to `posted`; there is no create-review-post flow. *Missing:* the draft state in the engine and a report filter that excludes drafts. Accountants will not accept a manual-journal screen that posts straight to the books.

**P6. Reconciliation is half-instrumented (02-9, 02-10, 07-12).** Lines carry a `reconciled` flag and instrument fields, but no `reconciled_date`, no `reconciled_amount` (so no partial reconciliation), no cheque clearance date, and instrument details are not persisted onto the JEs written at approval time. *Missing:* three columns (cheap, do now) plus the P5.10 matching screen.

**P7. Audit trail is attribution, not history (01-15, 02-24, 06-2).** Plan 11 closes MF-13 with `created_by`/`updated_by`. *Missing:* the `*Log` mirrors that give version history — the thing an auditor actually asks for.

**P8. Period control is planned but unanchored (01-13, 02-27, 03-2, 06-25).** `PeriodGuard` is a stub that always allows; `Lockable` is enforced in exactly one controller. *Missing:* `accounting_periods` + the real guard (P5.1), and extending lock enforcement past `InvoiceController`.

**P9. Sales-invoice posting is correct in one place and hand-rolled in 33 others (03-8).** *Missing:* the W3 cutover — the current wave.

**P10. Refund and void are the most damaged remaining flows (04-29, 04-31).** Void selects its reversal entries **by description string** and silently misses auto-invoiced tickets, leaving the client credited while AR and revenue are never reversed; the refund clawback account is classified as an **asset**. *Missing:* select by `task_id`, use `PostingService::reverse`, fix the account classification, validate operator-entered amounts. All of this sits inside the W4 files.

**P11. Airline is not yet an accounting dimension (04-2, 04-14).** We capture full ticket numbers and have `airlines.accounting_code`, and never match one against the other; payable is per-supplier+office, not per-airline; commission is not decomposed so payable is **overstated for commissionable carriers**. *Missing:* prefix→airline resolution and FM-line commission capture — both specified in 15 Stages B–C.

**P12. Branch GL pointers fell between two plans (06-13).** Roadmap 10 Phase 8 has them; plan 11 does not mention them at all. *Missing:* four FKs on `branches`, with the routing currently scattered across `users.acc_bank_id`, `charges`, and name-walking.

**P13. Per-service staff commission accounts (03-30, 07-28).** We have one profit/loss pair per agent and hang income off the *task type* rather than the person. *Missing:* the TKT/TRV/XO-style per-service split — which the newly-decided per-agent Receivable group work is the natural home for.

**P14. Security core is solid, policy layer is thin (05-46, 07-21, 07-24, 07-27).** RBAC/2FA are good; missing are per-user row filters, cost-centre scoping (no column yet), and two of the three integrity checks (invalid FX rate, base-vs-FC mismatch).

**P15. MIR-equivalent staging discards structure (04-10, 04-11).** Our GDS pipeline is genuinely strong, but the parser reads per-tax-code amounts and then **sums them away**, and never reads the FM (commission), FT (tour code), FP (form of payment) or PTC (passenger type) lines. *Missing:* 15 Stages B–D — the capture tables, the parser additions, and the archive backfill.

---

## 5. Suggested additions to the wave plan

### 5a. Fold into the waves already in flight (no new phase, no schedule slip)

These are chosen on one criterion: **the wave is already editing that exact file**, so doing it now costs a fraction of doing it later.

| Add to | Item | Why now |
|---|---|---|
| **Before W3 (blocking)** | Close the **`actual_balance` decision** (01-12/P2) | Named cutover gate (doc 17 §3.2); W3's `InvoiceController` is one of the 115 readers. Deciding after W3 means re-touching the same file |
| **Before W3 (blocking)** | Run `accounting:seed-serial-schemas`; add a typed handler for the `reference_number` unique violation; confirm the `{BRANCH}` mask policy (06-8, 03-7) | Doc 17 §3.3 + §4: engine-minted numbers can currently collide with legacy ones and fail with an untyped driver exception |
| **W3** | **Duplicate-invoice guard** `Task::isInvoiced()` in `store`/`autoGenerateInvoice`/webhook + unique index on `invoice_details.task_id` (04-13, MF-24) | These are literally the W3 methods; webhook retries double-post AR and revenue today |
| **W3** | Schema-only: `journal_entries.settled_amount`, `reconciled_date`, `reconciled_amount`, `cost_center_id` (02-6, 02-9, 06-12) | Four nullable columns on a table the wave is already migrating. The services land later; the migration cost is now-or-never |
| **W3** | Stamp `doc_type` **and `sub_type`** on every cut-over document (03-6) | Free at cutover, expensive to backfill |
| **W3** | Persist the decided **line reason tags** (`loan\|service\|adm\|fee\|loss\|settlement`) as a typed column (02-7) | Decided this week; make it a column before rows accumulate as free text |
| **W4** | **Void flow fix** — select by `task_id`, post via `PostingService::reverse`, block when reconciled (04-29, BUG-H7) | Highest-severity remaining correctness bug and it lives in the W4 files |
| **W4** | **Refund clawback account reclassification** + validate operator amounts (04-31, MF-30) | Same files; an expense currently sits on the balance sheet as an asset |
| **W4** | Refactor the 10+ account-creation sites onto `AccountService`; enable `account_observer`; derive + backfill `account_type`; add the two unique indexes (01-1, 01-6, 01-17, 01-18) | The long-tail wave is the only pass that touches all of them |
| **W4** | `currencies.decimal_places` wired into `Money` (02-15) | `Money` is fresh in everyone's head now |
| **W5** | Persist instrument details (cheque no/date/bank/auth) onto approval-time JEs (02-10, MF-22) | The receipt/bank-voucher wave owns those methods; P5.10 reconciliation depends on the data existing |

### 5b. New phases to add (with dependency order)

```
  [in flight]  W2b → W3 → W4 → W5–W7 → P2 exit (ArchitectureTest) → DEV deploy → P3 repair
                                                                          │
   ┌──────────────────────────────────────────────────────────────────────┘
   ▼
  P5.1 periods/locks ──► P5.2 opening journal + year-end close ──┬──► P5.4 reporting rebuild
                          (+ NEW: N29 copy serial schemas fwd,   │      (+ NEW: N20 Day Book,
                               N21 live P&L injection)          │       N22–N26 statement polish)
                                                                 │
  P5.3 party de-pooling + open-item + memos ─────────────────────┤
        (+ NEW: N10 FIFO auto-allocation,                        │
             P12 branch GL pointers, 06-15 base-currency param)  │
        └──► doc 13 Stages C–F historical reattribution          │
                                                                 │
  ▼ NEW PHASES (ordered)                                         ▼
  P5.12  CREDIT CONTROL          ← after P5.3 (needs per-party balances). Blueprint 03 §8. Owner-visible gap.
  P5.13  STAFF/AGENT SUB-LEDGER  ← after W3 (already the decision). Per-agent Receivable group, reason tags,
                                    settlement documents, per-service commission accounts (03-30 / 07-28).
  P5.16  BALANCE STRATEGY        ← after P5.4. Either build account_monthly_balances (02-20) or formally record
                                    "ledger-derived only" with a measured performance ceiling. Do not leave implicit.
  P5.17  AUDIT TRAIL             ← after P3. *_log mirrors on accounts/transactions/journal_entries (02-24)
                                    + the two missing integrity checks (07-27) + the drift checker (02-23),
                                    shipped in the same PR that retires the six Fix*COA commands.
  P7.5   BSP + ADM/ACM           ← after P5.3 (memo module) + 15 Stage C (ticket decomposition, FM commission)
                                    + MF-27 airline dimension. Promoted from "deferred" because ADM/ACM ingestion
                                    was decided REQUIRED this week. Contains: BSP statement ingestion (04-17),
                                    ticket-by-ticket variance (04-18), ADM/ACM posting (04-19/20), BSPTYPE (04-21),
                                    the monthly ritual + worklist (04-22), BSP summary (05-36),
                                    incentive accrual posting (04-25).
                                    ⚠ This CONTRADICTS 15 §F which defers BSP — reconcile the two docs.
  P7 (extend) PRODUCT LAYER      ← already planned; add: sixth typed root (01-16), AccGroup/materialised path
                                    (01-7) + AccGroup ordering (05-10), AltAccCode (01-14), AccTransType
                                    consolidation (01-9), freeze cascade + (CLOSED) (01-11), attachments
                                    registry (N38), password + session policy (N34/N35, orphaned from 10 Phase 8).
  entitlements track             ← N27 payroll confidentiality (live risk today), N33 row-level WHERE filters,
                                    05-46 branch/account report filters. That track already owns route gating.
```

**Two documented conflicts to resolve, not to inherit:**
1. **BSP**: plan 11 §P5.8 schedules it; plan 15 §F defers it; the owner decided this week it is required. → Adopt P7.5 above and amend 15 §F.
2. **Orphaned items between roadmap 10 and plan 11**: `account_monthly_balances` (10 Phase 3), branch GL pointers (10 Phase 8), and security hardening MF-49 (10 Phase 8) all exist in the roadmap and are **absent from plan 11**. Plan 11's own MF citations for P5.9/P5.11 are also mis-numbered against file 09 `[inference]`. → Fold them in explicitly (above) rather than assuming 11 supersedes 10 cleanly.

---

## 6. Questions that need the owner's decision

1. **`actual_balance`** — widen the column and have the engine maintain it atomically, or drop it and migrate all 115 readers onto `TrialBalanceService`? *(Blocks W3. Plan 11 Appendix C left this open on purpose; doc 17 made it a gate.)*
2. **Monthly balance buckets (02-20)** — build them, or accept ledger-derived-only and set a documented tenant/volume ceiling? *(Retrofitting later means backfilling years of buckets and proving they tie.)*
3. **Credit control (03-24)** — do we ship credit limits + blacklist? If yes, does the limit sit on the client, the agent, or both, and is a breach a **block** or a **warning**?
4. **Audit mirror tables (02-24)** — is `created_by`/`updated_by` enough for the buyers you're targeting, or do we need full row-version history before the first external audit?
5. **BSP conflict (04-17)** — confirm that ADM/ACM ingestion being "required" also means the full BSP statement reconciliation ships (P7.5), and amend 15 §F accordingly?
6. **Draft/posted workflow (02-26)** — do accountants get a create → review → post flow for manual journals, or does everything post immediately as it does today?
7. **Ticket stock (04-5, from 15 §B.5)** — does the agency actually receive allocated ticket-number ranges, or is all issuance BSP-electronic? *(Decides whether "missing ticket" means a real stock control or just serial-gap detection.)*
8. **Management sales reports (05-43)** — which 3–4 of the many variants ship first? *(15 §E #8 leaves this open.)*
9. **Payroll confidentiality (N27)** — salary postings now flow through the engine and are visible to anyone with ledger access. Restrict them now, or accept it until the entitlements track lands?
10. **Attachments (N38)** — do we build the universal document-attachment registry for v1, or ship without evidence attachment?
11. **Password + session policy (N34/N35)** — these were dropped between roadmap 10 and plan 11. Reinstate into P7, or accept the current state for the first sales cycle?
12. **Sixth root + `AccGroup` (01-16, 01-7)** — the COA-template work in P7 is the last cheap moment to change the tree's shape. Do we adopt the blueprint's six typed roots and a rollup key, or lock in the current five-root, name-matched structure?
