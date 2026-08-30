# Audit 04 — Travel Industry Specifics (IATA/BSP)

> **Note:** Rendered from the analyze-phase agent's structured findings (original detailed write was lost in a workflow path bug); findings verbatim, prose reconstructed.

**Dimension:** Travel Industry Specifics (IATA/BSP)
**Blueprint:** `.claude/skills/travel-accounting-system/references/04-travel-industry.md`
**Codebase audited:** `citytourv2` (main branch checkout at `scratchpad/citytourv2-main-checkout`, HEAD `431f97e68`)
**Date:** 2026-07-07
**Completeness estimate:** ~35%

---

## Executive summary

Of the 16 findings in this dimension, 7 are **missing**, 5 are **partial**, 1 is **buggy**, and 3 are **present_ok**. By severity: 3 high, 7 medium, 6 low — no critical findings, but the three high-severity items sit at the core of the airline-ticketing accounting cycle: there is no BSP statement reconciliation at all (no concept of matching what an airline billed per ticket against what was sold), the void flow has multiple correctness bugs (wrong reversal-matching key, a date-typo that always reverses "now", a non-atomic paid-void transaction, and no guard against voiding a settled/reconciled ticket), and there is no duplicate-invoice guard on the two primary invoicing paths (only the bulk path checks), so webhook retries can silently double-post AR and revenue.

On the positive side, the GDS auto-import pipeline (watched folders → staging → validation → task financials → auto-invoice) is a faithful, working implementation of the blueprint's feeder pattern across AIR, TXT, PDF/passport-AI, email, and API sources; hotel dual-currency handling is implemented correctly end-to-end; and the visa/ancillary "one ledger, many feeders" pattern is realized across all 12 task types. The medium-severity gaps cluster around airline-specific accounting machinery the blueprint expects but the codebase never built: airline accounting-code resolution, fare/commission decomposition, ADM/ACM adjustment memos, incentive/booking-class tracking, and a PCC/office-ID allowlist (office routing is currently hardcoded). Ticket stock control, per-airline sales/void analytics, and ticket check-digit validation are absent but low severity. Air cargo/GSA job costing is treated as plausibly out of scope for a retail agency platform.

---

## Summary table

| # | Finding | Status | Severity |
|---|---|---|---|
| 1 | BSP statement reconciliation (MIR vs airline billing, ticket-by-ticket) absent | missing | high |
| 2 | Void flow buggy: reversal matching, date typo, non-atomic, no settled-ticket guard | buggy | high |
| 3 | No duplicate-invoice guard on manual/auto paths; no auto-void flow | partial | high |
| 4 | Ticket stock control and missing-ticket report absent | missing | medium |
| 5 | Airline identification: full ticket numbers stored but 3-digit accounting code never resolved; airline master has no accounting linkage | partial | medium |
| 6 | Auto-invoice GL lacks fare/commission decomposition (no airline commission concept, gross revenue method, no timing controls) | partial | medium |
| 7 | ADM/ACM adjustment memo module absent | missing | medium |
| 8 | Airline incentives and booking-class mapping absent | missing | medium |
| 9 | PCC/office-ID allowlist validation missing; office routing hardcoded | partial | medium |
| 10 | Refund flow substantially implemented but with unenforced amounts and a misclassified clawback account | partial | medium |
| 11 | GDS segment counting, airline targets, and sales/void analytics by airline absent | missing | low |
| 12 | Ticket check-digit (mod 7) validation absent | missing | low |
| 13 | GDS auto-import pipeline (watched folders → staging → validate → task financials → auto-invoice) implemented | present_ok | low |
| 14 | Hotel dual-currency handling implemented correctly | present_ok | low |
| 15 | Visa and ancillary service feeders implemented (12 task types, one ledger) | present_ok | low |
| 16 | Air cargo (GSA) and sea-cargo job costing absent | missing | low |

**Overall completeness: ~35%.**

---

## Finding 1 — BSP statement reconciliation (MIR vs airline billing, ticket-by-ticket) absent

**Status:** missing · **Severity:** high

**Files:**
- `app/Services/IataEasyPayService.php`
- `app/Console/Commands/RecordWalletBalance.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/BankPaymentController.php`

No BSP concept anywhere: grep for BSP/ADM/ACM across `app/` returns zero matches; no table/importer for an airline billing statement, no ticket-level SysPayable-vs-BspPayable variance. Partial substitutes exist: IATA EasyPay wallet balance API + wallets snapshots (funding control only, hardcoded offices), an internal supplier-payable "reconciled" report (`ReportController::accountsReconciliationReport:1091`), and bank-statement matching (`BankPaymentController`) — all reconcile cash legs, never what BSP billed per ticket vs what was sold. Unmerged branch `fix/payment-voucher` attempts a payment/reconciliation system (prior art, not production-ready).

**Recommendation:** Add a `bsp_statement_lines` table (period, ticket_number, bsptype, fare, tax, commission, net) + CSV importer; join on `tasks.ticket_number` to compute per-ticket variance bucketed matched/system-only/statement-only; make it a monthly ritual.

---

## Finding 2 — Void flow buggy: reversal matching, date typo, non-atomic, no settled-ticket guard

**Status:** buggy · **Severity:** high

**Files:**
- `app/Http/Controllers/TaskController.php`
- `app/Http/Controllers/InvoiceController.php`

(a) `voidTask` (`TaskController:2604-2606`) selects entries to reverse via `invoice_details.task_description == task reference`, but the two main invoice paths store the task DESCRIPTION there (`InvoiceController:1344`, `:6009`) — so for auto-invoiced tickets the query matches zero rows: client gets the Credit but AR/revenue are never reversed. (b) `ReverseUnpaidVoidedTask:4469` checks `supplier_pay_date` but parses a nonexistent `$originalTask->supplier_date` → reversal always dated "now". (c) Paid-void flow is non-atomic: DB transaction wraps only the Credit creation (`:2559-2579`); JE reversal runs outside with a dangling commit (`:2631`). (d) No idempotency — re-running duplicates reversals. (e) Blueprint invariant "settled/reconciled ticket can't be silently voided" unenforced: no check of `journal_entries.reconciled`. No bulk-void variant.

**Recommendation:** Select reversal entries by `task_id`/`invoice_detail.task_id`, never description strings; fix the `supplier_date` typo; wrap the whole flow in one `DB::transaction`; tag reversals (`reversal_of_transaction_id`) for idempotency; block void when original entries are reconciled without supervisor override.

---

## Finding 3 — No duplicate-invoice guard on manual/auto paths; no auto-void flow

**Status:** partial · **Severity:** high

**Files:**
- `app/Http/Controllers/InvoiceController.php`
- `app/Services/BulkInvoiceValidationService.php`
- `app/Http/Webhooks/TaskWebhook.php`

Blueprint requires validating a ticket isn't already invoiced/voided before invoicing, plus an auto-void that zeroes duplicates to a "void customer". Only the bulk path checks (`BulkInvoiceValidationService:294-297`). `InvoiceController::store` (`:1253-1372`) and `autoGenerateInvoice` (`:5976`) create invoices with no already-invoiced or voided-status check; webhook retries (`TaskWebhook:479,521`) can double-invoice a ticket, double-posting AR and revenue invisibly. No void-customer/auto-void concept exists.

**Recommendation:** Enforce a central `Task::isInvoiced()` guard in all three paths; add a DB unique constraint on `invoice_details.task_id` for active invoices; implement an auto-void that zeroes duplicate invoices to a house "void customer" client.

---

## Finding 4 — Ticket stock control and missing-ticket report absent

**Status:** missing · **Severity:** medium

**Files:**
- `app/Models/Wallet.php`
- `database/migrations`

No stock header/series tables, no consumption/return of ticket ranges, no next-number generation, no missing-ticket (serial gap) report — grep 'stock' across `app/` returns nothing. The Wallet model tracks EasyPay money balances per IATA number, not ticket serial ranges. Sales completeness currently depends entirely on AIR files arriving in the watched folder; a dropped file is silently missing revenue and payable.

**Recommendation:** Implement the completeness control at minimum: persist serial (last 10 digits) per 3-digit airline prefix and report gaps in consumed serial sequences per office/period.

---

## Finding 5 — Airline identification: full ticket numbers stored but 3-digit accounting code never resolved; airline master has no accounting linkage

**Status:** partial · **Severity:** medium

**Files:**
- `app/Services/AirFileParser.php`
- `app/Models/Airline.php`
- `database/migrations/2026_01_27_194005_add_details_to_airlines_table.php`
- `app/Http/Controllers/TaskController.php`

Full AAA-SSSSSSSSSS ticket numbers are captured (`AirFileParser::extractTicketNumber:330-355`) and per-segment carrier/class/route persisted (`task_flight_details`), satisfying the multi-carrier-per-PNR capture. But `airlines.accounting_code` (added 2026-01) is written/read nowhere; no code resolves ticket prefix 229 → airline. Airline resolution is fuzzy name LIKE matching (`TaskController:3510`) from the A-line name, discarding the 2-letter code (`AirFileParser:1324`). The payable side is per Supplier + GDS office sub-account (`processTaskFinancial:1841-1895`), not per airline — no AirlineAccID_FK equivalent, so per-airline exposure is not derivable from the ledger.

**Recommendation:** Seed `accounting_code` with real IATA 3-digit codes and resolve `substr(ticket_number,0,3)` on import, falling back to the A-line 2-letter code; add airline linkage to tasks/journal entries so per-airline payable and sales are reportable.

---

## Finding 6 — Auto-invoice GL lacks fare/commission decomposition (no airline commission concept, gross revenue method, no timing controls)

**Status:** partial · **Severity:** medium

**Files:**
- `app/Http/Controllers/InvoiceController.php`
- `app/Http/Controllers/TaskController.php`
- `app/Services/AirFileParser.php`

Blueprint: Dr Customer=Sell / Cr Airline payable=(fare−commission)+tax / Cr commission income=NetRevenue, plus CTR timing controls. Implemented instead as two balanced gross documents: at import Dr Supplier Cost / Cr Supplier Payable = task.total (`TaskController::processIssuedTask:2027-2147`); at invoicing Dr AR / Cr "<Type> Booking Revenue" = full selling (`InvoiceController::addJournalEntry:1483-1589`) with margin/profit posted via agent profit/loss entries. Economically equivalent at margin, but: the AIR FM commission element is not parsed (no AirlineComm%, no IataFare/MarketFare distinction, payable assumed = K-line total — overstated for commissionable carriers); revenue is gross not net-commission; no issue-date/travel-date deferral controls; auto-void on duplicates missing.

**Recommendation:** Parse the FM element, store airline commission and computed payable per ticket (prerequisite for BSP recon) even if postings stay gross; consider config-flagged net presentation and travel-date revenue deferral later.

---

## Finding 7 — ADM/ACM adjustment memo module absent

**Status:** missing · **Severity:** medium

**Files:**
- `app/Http/Controllers/AccountingController.php`

No debit/credit memo document type exists (grep 'memo' → nothing). The manual payable/receivable entry screens (`AccountingController::storePayableDetail:543-640`, `storeReceivableDetail:743-844`) force a bank account as the contra side, so a pure commission-clawback (payable↔P&L, no cash) cannot be entered as designed; users must abuse raw manual JEs. No ticket linkage or BSPTYPE classification.

**Recommendation:** Add a `supplier_memos` document (ADM/ACM, supplier, optional ticket ref, reason) posting Dr expense-recovery/Cr payable (ADM) or inverse (ACM), linked to BSP statement lines once those exist.

---

## Finding 8 — Airline incentives and booking-class mapping absent

**Status:** missing · **Severity:** medium

**Files:**
- `app/Models/TaskFlightDetail.php`
- `app/Models/AgentMonthlyCommissions.php`

Grep 'incentive|override commission' across `app/` → zero matches. `class_type` is free text per segment with no airline/region→cabin map (tblAirlineClass equivalent) and no incentive rules (tblAirlineClassIncentive equivalent); no accrual report. Existing commission machinery (`AgentMonthlyCommissions`, `BonusAgent`, `agent->commission`) is for paying own agents, not earning from airlines — override income is untracked and unverifiable against ACMs.

**Recommendation:** Add `airline_classes` and `airline_incentive_rules` tables plus a monthly accrual report over issued tasks (data already exists in `task_flight_details.airline_id` + `class_type` + `tasks.price`).

---

## Finding 9 — PCC/office-ID allowlist validation missing; office routing hardcoded

**Status:** partial · **Severity:** medium

**Files:**
- `app/Services/AirFileParser.php`
- `app/Http/Controllers/TaskController.php`

Blueprint validates the file's PCC against a configured allowed list. Here the office ID is extracted and drives payable sub-account routing, but there is no configurable allowlist: `extractIataNumber` only recognizes literal 'KWIKT' parts (`AirFileParser:980`); `TaskController:1328-1423` hardcodes `KWIKT211N`/`KWIKT2843` offices, IATA `42230215`, and supplier IDs '2','29','38','39'. Company attribution comes from the storage folder path, so a misfiled AIR file imports under the wrong tenant. Also `TaskController:1295` does a case-sensitive `str_contains` against lowercase literals ('trendy travel') on a raw DB name — the exemption likely never fires.

**Recommendation:** Add a `company_gds_offices` table (company, GDS, office/PCC, IATA number, payable account) and validate every imported file against it; quarantine mismatches; remove hardcoded office/supplier branches.

---

## Finding 10 — Refund flow substantially implemented but with unenforced amounts and a misclassified clawback account

**Status:** partial · **Severity:** medium

**Files:**
- `app/Http/Controllers/RefundController.php`
- `app/Http/Controllers/TaskController.php`
- `app/Models/Refund.php`

Strong module: parser recognizes refund AIR files incl. per-code refunded taxes and penalty; supplier-side reversal posted (`processRefundTask:2175-2414` incl. currency-specific accounts); numbered Refund documents with per-task economics (original price/cost/profit, refund fee, supplier penalty charge, new profit), three settlement paths (paid→client credit+commission clawback, unpaid→netted replacement invoice, partial), transactional. Gaps: refund economics are operator-typed and not validated against parser-extracted amounts; no BSP REFUND confirmation (depends on missing BSP recon); `RefundController:674-692` creates "Commissions Expense (Agents)" with `account_type` 'asset'/`report_type` 'balance sheet' — an expense on the balance sheet. Unmerged agent-settlement branch is adjacent prior art for loss recovery.

**Recommendation:** Validate operator refund figures against parsed amounts with mismatch warnings; fix clawback account classification; add BSP refund-line matching once statement recon exists.

---

## Finding 11 — GDS segment counting, airline targets, and sales/void analytics by airline absent

**Status:** missing · **Severity:** low

**Files:**
- `app/Http/Controllers/ReportController.php`
- `app/Models/TaskFlightDetail.php`

Segment data is fully captured (one `task_flight_details` row per coupon with carrier/class/route; `tasks.gds_reference` per PNR) but no report counts segments by GDS/airline/period, no target/budget comparison, no stock-sales-voids-per-airline view. Reporting suite groups only by supplier/agent/gateway (`rangeSalesSuppliers:2348`, `rangeSalesRefunds:2574`, `tasksReport:2690`).

**Recommendation:** Cheap win: `GROUP BY airline_id` over `task_flight_details` joined to issued tasks in a date range; add an `airline_targets` table for variance when needed.

---

## Finding 12 — Ticket check-digit (mod 7) validation absent

**Status:** missing · **Severity:** low

**Files:**
- `app/Services/AirFileParser.php`
- `app/AI/Services/OpenAIClient.php`

No mod-7 check-digit validation anywhere (grep '% 7|check_digit|luhn' → nothing). Especially valuable here because tickets also arrive via AI/OCR extraction (GPT-4o vision, PDF), which is error-prone; regexes only enforce digit-shape in the strictest pattern.

**Recommendation:** Add a `TicketNumber::isValid()` helper (serial mod 7 == final digit) applied in the parser, AI post-processing, and form validation; flag failures for review.

---

## Finding 13 — GDS auto-import pipeline (watched folders → staging → validate → task financials → auto-invoice) implemented

**Status:** present_ok · **Severity:** low

**Files:**
- `app/Console/Commands/ProcessAirFiles.php`
- `app/Services/AirFileParser.php`
- `app/Http/Controllers/TaskController.php`
- `app/Http/Webhooks/TaskWebhook.php`

Faithful implementation of the blueprint's feeder pattern: watched folders per company/supplier with processed/error/debug outcomes; tasks (+per-segment details) as staging with enabled gate; duplicate detection returning 409 (`ProcessAirFiles:958-978`; `TaskController:821-919`); agent/branch/original-task resolution; multiple feeds (Amadeus AIR regex, TXT, PDF/passport via AI, email, TBO/MagicHoliday APIs, n8n webhook) all writing the same Task shape, which then posts supplier financials and auto-invoices on payment (`autoGenerateInvoice` in `DB::transaction`). Missing only native Galileo/Sabre parsers and voided-status check pre-invoice (covered in other findings).

**Recommendation:** Keep the architecture; address the PCC-allowlist and duplicate-invoice-guard findings which are the remaining holes in the validate step.

---

## Finding 14 — Hotel dual-currency handling implemented correctly

**Status:** present_ok · **Severity:** low

**Files:**
- `app/Models/Task.php`
- `app/Http/Controllers/TaskController.php`
- `app/Services/AirFileParser.php`

Tasks carry `original_price`/`original_currency` alongside converted totals and `exchange_rate`; parser extracts both currency pairs from 3-pair K-lines; hotel supplier payables post to auto-created currency-specific sub-accounts (`getOrCreateCurrencySpecificAccount`, used at `TaskController:1913-1948`) with journal entries storing `original_currency`/`original_amount` while posting converted amounts — margin computed against converted cost. Rate infrastructure (`SupplierExchangeRate`, `SystemExchangeRate`, `ExchangeRateHistory`) present.

**Recommendation:** None required; consider extending currency-specific sub-accounts to non-KWD flight suppliers if any emerge.

---

## Finding 15 — Visa and ancillary service feeders implemented (12 task types, one ledger)

**Status:** present_ok · **Severity:** low

**Files:**
- `app/Models/TaskVisaDetail.php`
- `app/Models/TaskInsuranceDetail.php`
- `app/Http/Controllers/TaskController.php`

`TaskVisaDetail` captures `visa_type`, `entries`, `stay_duration`, `issuing_country` per blueprint; visa/insurance/tour/etc. flow through the identical task feeder and posting pattern — a correct realization of "one ledger, many feeders" exceeding the blueprint's ancillary list.

**Recommendation:** None.

---

## Finding 16 — Air cargo (GSA) and sea-cargo job costing absent

**Status:** missing · **Severity:** low

**Files:**
- `app/Http/Controllers/TaskController.php`

No cargo task type, AWB/shipper/consignee capture, or job-costing construct (grep 'cargo|airway|consignee|shipper' → only "Jazeera Airways" false positives). Likely out of scope for this retail agency platform.

**Recommendation:** Treat as out-of-scope-by-design unless a GSA business line is planned; the Task feeder plus a job header would host it if so.
