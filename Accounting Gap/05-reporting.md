# Gap Audit — Dimension 05: Reporting Layer

**Audited codebase:** `citytourv2` @ `main` (commit `431f97e68`, "Merge branch 'feat/developer-docs'"), read-only worktree checkout.
**Blueprint:** `travel-accounting-system` skill, `references/05-reporting.md` ("Reference 05 — The Reporting Layer").
**Audit date:** 2026-07-07.
**Completeness estimate for this dimension: ~32%.**

All file paths below are relative to the citytourv2 repo root unless stated otherwise.

---

## Executive summary

The blueprint describes a reporting layer where ~100 financial + travel-industry reports are thin
specializations of **four shared building blocks**: (1) one canonical posted-lines query, (2) a
bottom-up account-tree roll-up, (3) sign rules by account type, and (4) a security filter — all
reading back from the same ledger the posting engine wrote to, so every report ties out with every
other.

citytourv2 has a genuinely working **Trial Balance** (with an unusual and valuable
unbalanced-transaction detector), a functional **Daily Sales** report family, a basic **account
ledger**, and an operational **tasks/void/refund** report. But there is **no balance sheet, no cash
flow, no AR/AP ageing, no statements, and none of the airline-industry reports** (GDS segments, BSP
reconciliation, incentives, missing-ticket/stock, tour code). More importantly, the four shared
building blocks were never built once: each report hand-rolls its own query, its own sign rules,
and its own opening-balance definition — and they **already disagree with each other** (P&L uses
`created_at` while the Trial Balance uses `transaction_date`; the ledger uses a static
`opening_balance` column while the TB recomputes opening from prior movement and ignores that
column). The blueprint's central promise — "the trial balance, balance sheet, and P&L always tie
out — they all re-derive from the same place" — is structurally violated today.

Report authorization is enforced almost entirely in the navigation menus (`@can` in Blade), not in
the controllers, so an AGENT-role user can fetch the full company P&L and any account ledger by
URL.

---

## Finding 1 — Universal report pattern / shared query building blocks

**Status: partial · Severity: high**

**Blueprint requirement** (§1): every financial report should be the same skeleton — validate
session → select posted lines from header ⋈ detail ⋈ account for the period (excluding opening
journals) with branch/cost-center filters → sum Dr/Cr/Diff per leaf → roll up the tree → sign rules
→ optional last-year comparison. §5 closes with: *"Build the canonical query, the roll-up, the sign
rules, and the security filter once as shared building blocks; every report is then a thin
specialization of them."*

**What exists:**

- `app/Services/TrialBalanceService.php` is the only report-grade shared service. Its
  `getOpeningBalances()` (line 131) is even marked *"Public so other services/reports can reuse
  this"* — but nothing else calls it.
- Every other report builds its own query from scratch:
  - `app/Http/Controllers/ReportController.php` (4,184 lines, ~30 report actions, each with its
    own inline query and its own inline sign convention).
  - `app/Http/Controllers/CoaController.php::childAccount()` (line 203) — a second, independent
    balance calculator used by the dashboard-style reports (`payableSupplier`, `receivable`,
    `totalBank`, `gatewayReceivable`).
  - `app/Http/Controllers/JournalEntryController.php::getJournalEntries()` (line 46) — a third
    independent running-balance calculator for the ledger view.
- There is no `Posted` flag at all (see Finding 15), no shared period/branch filter helper, and no
  last-year comparison anywhere (`grep -riE "last[_ ]?year|prior[_ ]?year"` over
  `ReportController.php` + `TrialBalanceService.php` returns nothing).

**Why it's a gap:** the blueprint's consistency guarantee comes precisely from the shared skeleton.
With four independent implementations, drift is not hypothetical — Findings 2, 4 and 5 document
places where the reports *already* return different numbers for the same account and period.

**Recommendation:** extract a single `LedgerReportQuery` (or extend `TrialBalanceService`) that
owns: (a) the JE base query (`transaction_date` period filter, `deleted_at` exclusion, company +
branch scoping), (b) opening-balance computation, (c) sign rules keyed off `accounts.root_id`
/ a new `account_nature` column, and (d) the roll-up. Rewrite `profitLoss`, the ledger, and the
COA dashboard totals as thin callers of it.

---

## Finding 2 — P&L reads `created_at`; Trial Balance reads `transaction_date` (reports cannot tie out)

**Status: buggy · Severity: critical**

**Blueprint requirement** (§1 step 2): the period filter is on the **document date**
(`H.DocDt BETWEEN @from AND @to`), and (§1 closing): *"That consistency is why the trial balance,
balance sheet, and P&L always tie out."*

**What exists:**

- `app/Services/TrialBalanceService.php:90` filters `je.transaction_date` — correct.
- `app/Http/Controllers/ReportController.php::profitLoss()`:
  - line 628–629: `JournalEntry::where('company_id',…)->whereBetween('created_at', [$from, $to])`
  - line 680–682: the 12-month chart query also uses `whereBetween('created_at', …)` and buckets
    by `$entry->created_at->format('n')` (line 687).

Any back-dated or migrated journal entry (the repo ships `UpdateOldTaskToTransaction` and bulk
import commands, so back-dated rows demonstrably exist) lands in a different month on the P&L than
on the Trial Balance. The income/expense totals in the TB for a period will not equal the P&L for
the same period.

**Recommendation:** change both `profitLoss` queries to `transaction_date`. Add a regression test
that posts one JE with `transaction_date` ≠ `created_at` month and asserts P&L and TB agree.

---

## Finding 3 — Account-tree roll-up exists in two divergent forms; the Trial Balance has none

**Status: partial · Severity: medium**

**Blueprint requirement** (§1 step 4 + the SQL sketch): insert group accounts, then roll up level
8→1 via `ParentAccID_FK`, spreading amounts into per-level columns for indented display — *"it
totals every group without storing group balances."*

**What exists:**

- `CoaController::childAccount()` (line 203) — recursive PHP roll-up, one DB query per node
  (N+1), used only by the four dashboard-style reports. It has a deliberate deviation: children
  with `account_dimension = 'payment'` are **excluded** from parent totals (lines 225–235,
  commented "prevents double-counting liabilities"). So the roll-up is not a pure GL roll-up — a
  parent's displayed total is not the sum of its ledger children.
- `ReportController::profitLoss()` builds its own descendant cache (`getAllDescendants`, line
  744) hard-anchored to `level == 3` parents (line 620) and code prefixes `'4'`/`'5'`.
- `TrialBalanceService` skips roll-up entirely: leaf accounts only
  (`whereRaw('NOT EXISTS (SELECT 1 FROM accounts child …)')`, line 96) grouped under **hard-coded
  English root names** `['Assets','Liabilities','Equity','Income','Expenses']`
  (`groupByRootCategory`, line 180). A renamed or localized root drops its accounts from the
  ordered output.
- No per-level column spread / indentation model anywhere.

**Recommendation:** one roll-up utility (recursive CTE on `accounts.parent_id`, or a
materialized-path column) shared by TB, P&L and COA; key roots by `root_id`/`account_type`, never
by display name; document (or remove) the `account_dimension='payment'` exclusion since it makes
group totals disagree with the raw ledger.

---

## Finding 4 — Sign rules duplicated 4×, with an `abs()` bug on P&L expenses

**Status: buggy · Severity: medium**

**Blueprint requirement** (§2): sign meaning comes from a single `AccType` table (A/E = debit
natural, L/I = credit natural); reports flip presentation sign from that one rule.

**What exists — four independent implementations:**

1. `TrialBalanceService::getNormalBalance()` (line 226): `in_array($account->root_name, ['Assets','Expenses']) ? 'debit' : 'credit'` — name-string based.
2. `JournalEntryController::getJournalEntries()` (lines 46–77): looks up the five root accounts **by name with no company filter in the code** (`Account::where('name','Assets')->first()` — only safe because of the `BelongsToCompany` global scope; for an ADMIN with no `session('company_id')` it silently resolves company 1 via `helper.php:10`), then compares `root_id`.
3. `CoaController::childAccount()`: caller passes `'normal'`/`'reverse'` manually per report (`ReportController.php:1340` passes `'reverse'` for payables, `:1435` `'normal'` for receivables) — the sign convention lives in the call site, not the account.
4. `ReportController::profitLoss()` (lines 723–724): income/expense classified by **code prefix** `'4'`/`'5'`, and expenses computed as `$expense += abs($total)`.

**The `abs()` bug:** `$total` for an expense group is `credit − debit` (line 715), normally
negative. `abs()` makes it positive — but if a rebate/reversal month leaves the group net-credit
(total positive), `abs()` still **adds** it to expenses instead of subtracting, overstating expense
and understating profit. Correct is `$expense += -$total` (i.e. `debit − credit`).

**Recommendation:** add an `account_nature` (`debit`/`credit`) column or derive strictly from
`root_id`→type mapping stored once; replace the `abs()` with a signed calculation; delete the
per-call-site `'normal'/'reverse'` argument.

---

## Finding 5 — Two contradictory opening-balance definitions; ledger invariant broken

**Status: buggy · Severity: high**

**Blueprint requirement** (§2): *"Opening balance in a ledger or trial balance = all posted
movement before the period plus the opening journal, but excluding prior-year income/expense…
A ledger line then reads: opening + period movement = closing."*

**What exists:**

- Opening balances are captured as a **static column** `accounts.opening_balance` (+
  `opening_balance_date`), edited via `CoaController::openingBalances()/saveOpeningBalances()`
  (lines 1179–1265). No opening journal voucher is created (upstream branch `opening-balance`
  appears merged; the column approach is what shipped).
- **Trial Balance:** `TrialBalanceService::getOpeningBalances()` (line 131) = sum of JE movement
  strictly before `date_from` — it **ignores `accounts.opening_balance` entirely**. A company that
  keyed in opening balances gets a TB whose opening column omits them, so opening + movement ≠
  reality.
- **Ledger:** `JournalEntryController::show()` (lines 27–42) does the opposite: starting balance =
  `accounts.opening_balance` only — it **ignores all movement between `opening_balance_date` and
  the requested `date_from`** (which defaults to the current month, line 27). For any month after
  the first, "opening + period movement = closing" is false.
- **COA dashboard:** `CoaController::childAccount()` line 326–334 adds `opening_balance` to
  *lifetime* movement — a third convention.
- **Prior-year I/E reset:** nowhere. There is no fiscal-year close anywhere in the app (no
  year-end journal, no retained-earnings roll), so income/expense accounts accumulate forever and
  any "opening balance" on an I/E account includes prior years' P&L, contrary to the blueprint.

**Recommendation:** pick one definition — the blueprint's: opening = Σ movement before period
start **+ opening balance row** (ideally re-model the keyed-in openings as an opening journal
transaction so they live in `journal_entries` like everything else, dated `opening_balance_date`
and typed e.g. `OJV`), and exclude prior-year I/E once a year-close exists. Then make TB, ledger
and COA all call the same function.

---

## Finding 6 — Trial Balance report

**Status: partial · Severity: medium**

**Blueprint requirement** (§3): every account: opening + period Dr/Cr + closing; variants —
monthly, comparative, branch-consolidated; injects a live P&L figure if the year isn't closed.

**What exists:** `ReportController::trialBalance()/trialBalancePdf()/trialBalanceExport()/trialBalanceValidation()`
(lines 3993–4182) on top of `TrialBalanceService`. Genuinely good parts: balanced/out-of-balance
check with 0.001 tolerance (`TrialBalanceService.php:57`), and `findUnbalancedTransactions()`
(line 191) which surfaces transactions whose lines don't net to zero — a valuable integrity tool
the blueprint doesn't even ask for. PDF + CSV export, zero-balance toggle, role gate
(ADMIN/COMPANY/ACCOUNTANT).

**Gaps/bugs:**

1. **Branch filter is wrong-by-construction** (`TrialBalanceService.php:100–105`): it filters
   *accounts* by `a.branch_id = X OR a.branch_id IS NULL`, not journal entries by `je.branch_id`
   (the JE table has a `branch_id` column — `app/Models/JournalEntry.php` fillable). Shared
   (NULL-branch) accounts contribute **all branches' activity** to a single-branch TB. A branch TB
   therefore neither isolates the branch nor balances meaningfully.
2. **Opening balances ignore the branch/agent options** — `getOpeningBalances()` takes no
   `$options`, so a branch-filtered TB mixes an unfiltered opening column with filtered movement.
3. **Opening ignores `accounts.opening_balance`** (Finding 5).
4. `show_zero=false` filtering (`TrialBalanceService.php:118–122`) drops accounts with **zero
   period movement but non-zero opening balance**, silently removing their closing balances from
   the report.
5. No monthly-columns variant, no comparative (last year), no multi-branch consolidated variant,
   no live P&L injection (harmless today since I/E leaves are included directly).
6. CSV export (`ReportController.php:4141–4148`) emits only period Dr/Cr — not the
   opening/closing columns the service computes; also the TOTALS row has a column-misalignment
   (`"TOTALS,,"` puts debit under Root Type).

**Recommendation:** filter movement by `je.branch_id`; thread options into opening; include
opening in the zero-filter predicate and the CSV; add a comparative period parameter.

---

## Finding 7 — Balance Sheet

**Status: missing · Severity: high**

**Blueprint requirement** (§3): Assets vs Liabilities+Equity at a date, group summary,
last-year/last-year-month comparatives, folding in the period profit.

**What exists:** only a data hook — `Account::REPORT_TYPES = ['PROFIT_LOSS' => 'profit loss',
'BALANCE_SHEET' => 'balance sheet']` (`app/Models/Account.php:47`) and a `report_type` column that
the COA import sets. No route (checked `routes/web.php` reports group, lines 396–443), no
controller method, no view (`resources/views/reports/` has no balance-sheet blade), no equivalent
anywhere (`grep -riE "balance[ _-]?sheet"` over `app/`, `routes/`, `resources/views` finds only
the constant, imports, and unrelated uses). The closest thing is the dashboard stats endpoint
(`getDashboardStats`, line 3485) exposing four headline account balances.

**Why it matters:** without a balance sheet there is no statement of financial position at all,
and no place where period profit is folded into equity — combined with the missing year-close
(Finding 5) the equity section of this GL is effectively unreadable.

**Recommendation:** build it as a thin specialization of the Finding-1 shared query: `AccType in
(A,L,Equity)` as of a date (movement ≤ date + opening), plus computed current-period profit as a
virtual equity line. The `report_type` column is already in place to drive it.

---

## Finding 8 — Profit & Loss report

**Status: partial (and buggy per Findings 2/4) · Severity: high**

**Blueprint requirement** (§3): Income vs Expense for a period; variants: by branch/cost-center,
month-wise (12 columns), progressive (cumulative); net profit = ΣCredit − ΣDebit over I+E.

**What exists:** `ReportController::profitLoss()` (lines 591–742): single selected month, level-3
income (`code 4*`) and expense (`code 5*`) groups with children, plus a 12-month profit bar chart
for the selected year. Route has **no role/permission gate** (see Finding 16).

**Gaps beyond Findings 2/4:** no branch or cost-center filter (JEs carry `branch_id`; unused
here); no month-wise 12-column table (only the chart); no progressive/cumulative variant; no
last-year comparison; classification by code prefix means any P&L account coded outside `4*/5*`
(or at level ≠ 3 without a level-3 ancestor in `report_type` P&L) silently vanishes; loads entire
year of JEs into PHP memory (`->get()` twice, lines 630/683) rather than aggregating in SQL.

**Recommendation:** rebuild on the shared query with `report_type = 'profit loss'` (already
stamped on accounts) instead of code prefixes; add `branch_id` filter and a month-wise/cumulative
mode; gate with `ReportPolicy::viewProfitLoss` (already written, never enforced).

---

## Finding 9 — Account Ledger

**Status: partial (one buggy variant) · Severity: high**

**Blueprint requirement** (§3): *"Every transaction for one account, with running balance; local or
foreign currency; opening-balance option; multi-line invoices consolidated into one line… the
biggest report."*

**What exists — three partial ledgers:**

1. `JournalEntryController::show()` (`journal-entries/{accountId}/account` route) — the real
   ledger: date-range, running balance with sign rules, opening from the static column (broken per
   Finding 5). No FC/local toggle, no consolidated invoice lines, no pagination (`->get()` on the
   whole range), and **no authorization beyond `auth`** — any company user, including agents, can
   read any account's ledger (client balances, income accounts, bank accounts) by iterating
   `accountId`. `exportPdf` (line 79) doesn't even scope by company in code (global scope saves
   it, except for the ADMIN-default-company-1 quirk).
2. `AccountingController::filterLedgers()` (line 152) — AJAX ledger with **no running balance** and
   a crash bug: line 200 maps `'agent_name' => $ledger->invoice->agent->name` unconditionally,
   while line 197 correctly guards `$ledger->invoice ? … : null`. Any journal entry without an
   invoice (payments, manual JVs, bank entries — the majority) throws
   "Attempt to read property on null" → 500. This endpoint appears effectively dead/unusable for
   non-invoice accounts.
3. `SupplierController::ledgerByDateRange()` (line 194) — "supplier ledger" that actually returns
   raw `Task` rows (not GL), with **no company scoping and no gate** beyond `auth` (Task model has
   no `BelongsToCompany` trait — `app/Models/Task.php:15` is `HasFactory, SoftDeletes` only), so
   any logged-in user can pull any supplier's tasks across companies.

**Recommendation:** consolidate on ledger #1; fix its opening balance (Finding 5); add FC columns
(`original_currency`/`original_amount` already exist on JEs); paginate; authorize (account-level
permission or at least role gate); fix or delete `filterLedgers`; scope `ledgerByDateRange` by
company and gate it.

---

## Finding 10 — Receivable / Payable Ageing

**Status: missing · Severity: high**

**Blueprint requirement** (§3): open items per party bucketed by age (120/90/60/45/30/15 days),
summary + detail, by branch/cost-center, local or FC, fed by the **apply engine** (open = original
− applied).

**What exists:** nothing bucketed by age anywhere (`grep -riE "aging|ageing|age_bucket|overdue"`
over `app/` finds only unrelated hits in a TBO hotel query and WhatsApp controller). The nearest
relatives:

- `ReportController::unpaidaccountsPayableReceivableReport()` (line 793) / `paid…` (line 942):
  AR/AP open-vs-settled listings driven by the JE `reconciled` flag (`reconciled = 0` ⇒ "unpaid",
  lines 888–889 / 1037–1038) — not by the apply engine (`payment_applications` exists and is
  ignored here), and with no age buckets, no as-of logic, and a supplier filter that matches on
  the JE free-text `name` column (`->where('name', $supplier->name)`, line 865) rather than a
  foreign key.
- Invoice-level partial/unpaid amounts are computed ad hoc in `clientReport` and `rangeSalesAgents`.

**Cross-reference:** unmerged branch **`fix/payment-voucher`** attempts a
PaymentVoucher/PaymentReconciliation layer that would give open-item data a real backbone — it is
not production-ready (161 commits behind main, includes a data-rewrite migration) but shows prior
art for the open-item ledger an ageing report needs.

**Recommendation:** build ageing on `invoices`/`payment_applications` (open = invoice amount −
applied) for AR, and on unreconciled AP journal lines (or a proper supplier open-item table) for
AP; bucket in SQL by `DATEDIFF(as_of, invoice_date)`; add branch filter and party grouping.

---

## Finding 11 — Outstanding / Statement of account

**Status: partial · Severity: medium**

**Blueprint requirement** (§3): what each customer owes / you owe each supplier, as of a date;
statement per party.

**What exists:** `ReportController::clientReport()` (line 121) is a competent task-wise client
outstanding report (owed / paid / refund credit vs refund owed / running balance per client, PDF
variant at line 323); `ClientController::show()` computes per-client outstanding (line ~533);
`creditors()` (line 1634) gives per-supplier-account balances with running balance and
group-by-supplier, plus three PDF layouts. But: no as-of-date statement with brought-forward
opening balance (client report recomputes running balance only over the filtered window starting
at zero), no formal numbered statement document, supplier side reads GL creditor accounts only
(fine) but relies on account naming conventions (`'Liabilities'` → `'Accounts Payable'` →
`'Creditors'` chain, lines 1654–1682) that break silently if an account is renamed.

**Recommendation:** add an opening-balance row to the client/creditor statements (reuse the
Finding-5 opening function), and resolve the AP tree by `code`/`account_type` rather than English
names.

---

## Finding 12 — Cash Flow report

**Status: missing · Severity: medium**

**Blueprint requirement** (§3): cash movement over a period, from cash/bank account movement.

**What exists:** nothing (`grep -riE "cash[ _-]?flow"` over `app/` + `routes/` → zero hits). The
`totalBank` dashboard total (line 1453) shows a point-in-time bank balance, and `settlementsReport`
(line 1547) lists gateway-settlement transactions — description-string-matched via
`->where('description','like','%Settles to Bank (After 24h)%')` (line 1564), which is fragile —
but there is no period cash-movement report.

**Recommendation:** simplest compliant version: movement report over accounts under `Bank
Accounts` + cash accounts (opening, in, out, closing per account per period) using the shared
query. Replace description-matching in `settlementsReport` with a typed `reference_type`.

---

## Finding 13 — GL / Bank Reconciliation

**Status: partial (with a soft-delete bug) · Severity: high**

**Blueprint requirement** (§3): ledger lines vs **bank statement**, reconciled vs not, using
`Reconciled` flags.

**What exists:** a `reconciled` tinyint + `reconciled_ref_id` on `journal_entries` (migrations
`2025_05_13_*`), set by `BankPaymentController` (lines 331–393) and `ReceiptVoucherController`
(lines 736–827) when supplier liabilities are settled via bank payment / receipt vouchers, with a
`declineReconcile` reversal. `ReportController::accountsReconciliationReport()` (line 1091) lists
liability-account entries filtered reconciled yes/no/both. So the *flags* half of the blueprint
exists — but it is **internal settlement matching**, not reconciliation against an imported bank
statement. There is no bank-statement entity, no import, no statement-line matching, no
reconciliation-difference report.

**Bugs in the existing report:**

1. `accountsReconciliationReport` line 1127: `DB::table('journal_entries')` with **no
   `whereNull('deleted_at')`** — soft-deleted journal entries are included in the per-account
   totals (the Eloquent half of the same report at line 1158 excludes them via the model's
   `SoftDeletes`), so the header totals and the detail rows can disagree. The same raw-query
   pattern in `BankPaymentController::fetchPaymentsByDate` (line ~642) has the same hole.
2. Supplier filter again matches JE free-text `name` with `LIKE` (line 1143).
3. It only looks at `root = 'Liabilities'` credits with `voucher_number IS NULL` — receivable-side
   reconciliation doesn't exist.

**Cross-reference:** unmerged branch **`fix/payment-voucher`** attempted a
`PaymentReconciliation` model — prior art, not production-ready.

**Recommendation:** add `->whereNull('journal_entries.deleted_at')` to every raw JE query
(grep for `DB::table('journal_entries')`); longer-term, introduce a bank-statement import +
match screen if bank rec is a business requirement.

---

## Finding 14 — Apply / Unapplied / Payment report

**Status: partial · Severity: medium**

**Blueprint requirement** (§3): which payments settled which invoices; what's unallocated (from
the apply table).

**What exists:** the apply engine itself exists (`app/Services/PaymentApplicationService.php`,
`payment_applications` table, `remaining_amount` tracking at line 351) and applications are shown
on invoice detail views (`resources/views/invoice/show.blade.php`, `details.blade.php`) and inside
the payment-gateways report (`ReportController::paymentGateways()`, line 3561, eager-loads
`paymentApplications.payment.paymentMethod`). But there is **no report of unapplied/unallocated
payments or credits** — no screen answers "which receipts are sitting unallocated," which is the
control the blueprint derives from `tblAccIsApply`.

**Recommendation:** a simple two-tab report over `payments` LEFT JOIN `payment_applications`:
(a) payments with `amount − Σ applied > 0`; (b) per-invoice application trail. All data already
exists.

---

## Finding 15 — Day Book / Daily Sales (DSR) — plus broken PDF routes

**Status: partial · Severity: medium**

**Blueprint requirement** (§3): all documents/sales for a day, posted documents by date; (§4)
"Daily Sales (ticket-wise): tickets issued per day with fare/tax/commission breakdown."

**What exists — the strongest report family in the app:**

- `ReportController::dailySalesReport()` (line 2120): summary + details views; totals for
  invoiced/paid/cash/credit/gateway; refunds; profit; top agent; top supplier; per-agent
  commission (`calculateAgentCommission`); per-supplier GL-derived purchase totals
  (`rangeSalesSuppliers`, line 2348); task-type filter across the 12 task types.
- `CoaController::transaction()` (line 594): a paginated document day-book (all `transactions`
  with their journal lines, filterable by reference/entity type, agent, account, date).
- `ReportController::journalEntriesByDate()` (line 1601): per-day JE drill-down.
- `tasksReport` (line 2690) covers ticket-wise listing with issued/reissued/refund/void/confirmed
  status logic and travel-date filters, with its own PDF (line 3110).

**Bug:** `routes/web.php:423–424` still registers
`GET /reports/daily-sales/pdf → dailySalesPdf` and `…/pdf/download → dailySalesPdfDownload`, but
both methods are **commented out** (`ReportController.php:2058`, `:2088`). Hitting those routes
(the PDF export buttons) throws a `Method … does not exist` 500. The blade PDFs
(`resources/views/reports/pdf/daily-sales*.blade.php`) still exist, so this looks like an
accidental regression.

**Gap:** no fare/tax/commission column breakdown per ticket (tasks store composite prices; tax
isn't separated), and the day book shows only `Posted`-equivalent rows trivially since drafts
don't exist.

**Recommendation:** restore or remove the two dead routes; add fare/tax breakdown columns if the
task schema captures them (it captures totals only today).

---

## Finding 16 — Travel-industry report catalogue (GDS/BSP/airline analytics)

**Status: missing (almost entirely) · Severity: high**

**Blueprint requirement** (§4): the reports a generic package doesn't have: GDS segment count,
airline segment vs target with variance, airline incentive accrual, BSP reconciliation
(ticket-by-ticket `SysPayable − BspPayable`) + BSP summary by airline/IATA/BSPTYPE, missing-ticket
stock report, stock/sales/voids with void %, cargo, hotel voucher, refund report, PNR reports,
management sales variants, tour-code, discrepancy/lowest-fare-deviation.

**What exists in main:**

| Blueprint report | Status in citytourv2 | Evidence |
|---|---|---|
| GDS segment count | **missing** — AIR parsing captures flight segments (`app/Services/AirFileParser.php`, `task_flight_details`) but no segment-count report; no GDS dimension on tasks (`grep -riE "\bgds\b" app/Http/Controllers` → only OpenAI/Task parsing contexts) | — |
| Airline segment vs target | **missing** — no target/budget tables at all (`accounts.budget_balance` exists but unused in reports) | — |
| Airline incentive | **missing** (`grep -ri incentive app/` → 0) | — |
| BSP reconciliation + summary | **missing** (`grep -riE "\bBSP\b" app/` → 0). No IATA/BSP concept anywhere | — |
| Missing ticket / stock | **missing** — no ticket-stock allocation model | — |
| Stock, sales & voids | **partial** — `tasksReport` pairs issued↔void/confirmed by `reference` (lines 2845–2890) and `rangeSalesAgents` counts `voidTasks` (line 2325); no void-% analytics by airline | `ReportController.php:2845` |
| Air/Sea cargo | **missing** (not part of this business — low relevance) | — |
| Hotel voucher report | **partial** — hotel tasks filterable by check-in in `tasksReport` (line 2808); supplier vs customer pricing visible in daily-sales details; no nights/voucher-centric report | `ReportController.php:2808` |
| Refund report | **partial** — `RefundController::index` (gated list), refunds totals inside daily sales (line 2223), refund-status logic in `clientReport` (lines 197–208); no payment-status (paid/partial/unpaid) refund report | `RefundController.php:45` |
| Airline/Hotel PNR reports | **partial** — `tasks.reference` holds PNR and is searchable/groupable in tasksReport; no dedicated PNR-grouped report | — |
| Management sales (by customer/airline/consultant/service, top-N, variance) | **partial** — daily sales gives top agent/supplier and by-agent detail; `issued_by` filter exists (consultant-ish); **no airline dimension anywhere in reporting** (airline lives in `task_flight_details`, never aggregated); no variance/top-N reports | `ReportController.php:2241` |
| Daily sales ticket-wise | **present** — see Finding 15 | |
| Tour code | **missing** (`grep -ri "tour[ _-]?code" app/` → 0; AIR `FM`/tour-code fields not modeled) | — |
| Discrepancy / cost-saving | **missing** | — |

**Why it matters:** this catalogue is the reason a travel agency buys a travel back-office instead
of QuickBooks. The operational data to power ~half of these (segments, airlines, PNRs, ticket
numbers, void chains) is already being parsed out of AIR files into `tasks`/`task_flight_details`;
the reports were simply never written. The BSP/stock family additionally needs new data (BSP
billing import, ticket stock ranges) that has no schema today.

**Recommendation:** phase 1 (data already exists): airline-wise sales report, segment count by
carrier/date, PNR report, void-% report, refund-status report — all queries over
`tasks ⋈ task_flight_details`. Phase 2 (needs new schema): BSP file import + ticket-level
reconciliation (`SysPayable − BspPayable`), ticket stock allocation + missing-ticket report,
airline targets + variance.

---

## Finding 17 — Report security: permissions enforced in menus, not controllers

**Status: buggy · Severity: critical**

**Blueprint requirement** (§5): *"Every report applies the same controls (don't bolt these on
later — design them in): session gate; branch/account permission; payroll confidentiality;
posted-only; OJV as opening."*

**What exists:**

- **Session gate — present.** The whole reports group sits inside `Route::middleware(['auth'])`
  (`routes/web.php:60`), and multi-tenancy is enforced by the `BelongsToCompany` global scope on
  `Account`/`JournalEntry` (`app/Traits/BelongsToCompany.php`) plus explicit `company_id` filters.
- **Permission gates — broken by design.** `app/Policies/ReportPolicy.php` defines granular
  abilities (`viewProfitLoss`, `viewReconcile`, `viewSettlement`, `viewCreditors`,
  `viewDailySales`, `viewTaskReport`, `viewClientReport`, `viewPaymentGatewaysReport`,
  `viewPayableSupplier`) — but the **only** controller enforcement in the entire report layer is
  `Gate::authorize('viewPaymentGatewaysReport', …)` (`ReportController.php:3563, 3829`). Every
  other policy method is referenced solely in the navigation blades
  (`resources/views/layouts/menu.blade.php:218–260`, `mobile-drawer.blade.php`) — i.e. the link is
  hidden but the route is open.
- Concrete holes (verified against `helper.php:15` — an AGENT resolves a valid company id):
  - `GET /reports/profit-loss` (`profitLoss`, line 591): **no role or permission check** — an
    agent can read the full company P&L.
  - `GET /reports/settlements/entries/by-date` (`journalEntriesByDate`, line 1601): no
    role/permission check — full journal-entry dump for any date.
  - `GET /journal-entries/{accountId}/account` + `/export/pdf` (`JournalEntryController`): no
    check — any account ledger in the company, by id enumeration.
  - `POST /filter-ledgers` (`AccountingController::filterLedgers`): no check.
  - `GET /suppliers/ledger-by-date/{supplierId}` (`SupplierController::ledgerByDateRange`,
    line 194): no gate **and no company scoping** (Task has no company scope trait) —
    cross-tenant data exposure.
  - The rest use coarse hard-coded `role_id` allow-lists (`in_array($user->role_id, [ADMIN,
    COMPANY, ACCOUNTANT])`) instead of the permission system, so the Spatie permissions the
    policy checks are meaningless for reports.
- **Branch/account-level permission filtering** (blueprint's user-branch / permitted-account
  views): does not exist. Where a branch is derived (`accountsReconciliationReport` lines
  1112–1117) it comes from the user's own branch, not from a permission table; most reports accept
  arbitrary `branch_id` input.
- **Payroll confidentiality:** no payroll/HRJV module exists (`grep -riE "payroll|hrjv" app/` →
  0), so nothing to hide — noted as not-applicable rather than a defect.

**Recommendation:** add `Gate::authorize` (or route `->can(...)`) to every report route using the
already-written `ReportPolicy`; add policies for the ledger endpoints; scope
`ledgerByDateRange` by company; replace `role_id` allow-lists with the permission system so the
admin-configurable roles actually govern report access.

---

## Finding 18 — Posted-only / draft exclusion invariant

**Status: partial · Severity: low**

**Blueprint requirement** (§1 step 2, §5): `WHERE H.Posted = 1 — drafts never count`.

**What exists:** there is **no `posted`/status column** on `transactions` or `journal_entries`
(no migration adds one; `grep -riE "'posted'|is_posted" app/` → 0). Every journal entry is live
the instant it is inserted — so "drafts never appear" is trivially true because drafts cannot
exist. The flip side: there is no approval workflow, and a half-written transaction (e.g. a crash
between two `JournalEntry::create` calls — postings are not uniformly wrapped in DB transactions)
appears in reports immediately. The system's own `findUnbalancedTransactions()`
(`TrialBalanceService.php:191`) exists precisely because such imbalances occur in practice, and it
correctly filters JE `deleted_at` — but does not check `transactions.deleted_at`, so a
soft-deleted transaction whose JE lines were orphaned still shows up. Soft-delete exclusion is
also missed in the raw queries listed in Finding 13.

**Recommendation:** short-term: sweep all `DB::table('journal_entries')`/`DB::table('transactions')`
raw queries for `deleted_at` filters. Long-term: if a review/approve workflow is wanted, add a
`posted_at` column and make the shared query (Finding 1) filter on it in one place.

---

## Scorecard

| Blueprint capability | Status |
|---|---|
| Universal shared report pattern | partial |
| One consistent date basis across reports | **buggy** |
| Tree roll-up (level 8→1, per-level columns) | partial |
| Sign rules by account type | **buggy** (4 copies, `abs()` defect) |
| Opening balance semantics (incl. prior-year I/E reset) | **buggy** |
| Trial Balance (+ variants) | partial |
| Balance Sheet | **missing** |
| Profit & Loss (+ variants) | partial |
| Account Ledger (FC, opening, consolidation) | partial |
| AR/AP Ageing | **missing** (prior art: `fix/payment-voucher`, unmerged) |
| Outstanding / Statement | partial |
| Cash Flow | **missing** |
| GL/Bank Reconciliation | partial (internal matching only; soft-delete bug) |
| Apply / Unapplied report | partial |
| Day Book / Daily Sales | present, PDF routes broken |
| GDS segment / BSP / incentive / stock / tour-code family | **missing** |
| Ticket-wise daily sales, voids, refunds, hotel, PNR | partial |
| Security: session gate | present |
| Security: permission gates on report routes | **buggy** (menu-only) |
| Security: branch/account-level data permissions | missing |
| Security: payroll confidentiality | n/a (no payroll module) |
| Security: posted-only | partial (no drafts exist; soft-delete leaks in raw SQL) |

**Overall completeness: ~32%.** The financial core (TB + ledger + daily sales) is real but
inconsistent with itself; the statement layer (BS/CF/ageing/statements) and the entire
airline-analytics layer are absent; report authorization needs to move from the menu into the
controllers before any of this can be exposed to non-accountant roles.
