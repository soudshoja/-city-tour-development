# 19 — Report Data Contract (operational reports, built during the posting-engine cutover)

**Date:** 2026-08-26
**For:** the second engineering track building operational reports for agency staff (agent profit, supplier payable, pending payment links, client outstanding, …).
**Against:** branch `feat/accounting-posting-engine-p1`. Accounting track is mid-cutover: P1 `PostingService` is SHIPPED but flag-OFF and wired to nothing (`Accounting Gap/17-p1-postingservice-complete.md`); W1 feeder cutover starts this week; P3 data repair has not run.
**Purpose:** let you build reports **now** that will not need rework when the engine becomes the sole writer of `journal_entries`.

> The engine writes the **same two tables** (`transactions` + `journal_entries`) with the **same `transaction_id` grouping** as the legacy code. It adds `transactions.doc_type` / `sub_type` / `doc_year` / `posting_status` / `idempotency_key` (migration `2026_08_24_120004_add_document_columns_to_transactions_table`). Legacy rows carry `doc_type = NULL`. **Nothing you read changes shape at cutover.** The reason this document exists is that several *convenient-looking* columns are already wrong today and are being retired — read those and your report breaks at cutover, not because the engine changed, but because it stopped maintaining the lie.

---

## 1. TL;DR — the 6 rules

| # | Rule | One-line reason | Where it comes from |
|---|---|---|---|
| **R1** | **Never read `accounts.actual_balance`.** | `decimal(10,2)` (truncates KWD fils) and on City Travelers dev **190 of 458 accounts (41.5%) disagree with `SUM(debit − credit)`** — most sit at `0.00` against six-figure ledger balances; gateway accounts drifted up to **1.59M with the wrong sign**. Maintained by non-atomic `+=`/`-=` at 12+ unlocked call sites; the engine deliberately does not maintain it. | `database/migrations/2025_03_17_091543_create_accounts_table.php`; `Accounting Gap/06-data-model.md` Finding 1; `Accounting Gap/17-p1-postingservice-complete.md` §3.2 (measured 2026-08-24..26) |
| **R2** | **Never read `journal_entries.balance`.** | Per-row running-balance snapshot written by different writers under **contradictory sign conventions**; originally always `0` (attribute-name mismatch, `Account` has no `balance` attribute). Historical rows are unreliable and nothing recomputes them. One live report already reads it — `ReportController::rangeSalesSuppliers` surfaces it as `running_balance` on the Daily Sales supplier rows. | `TrialBalanceService::getCurrentAccountBalance` docblock ("co-writer conflict"); `Accounting Gap/06-data-model.md` Finding 1 |
| **R3** | **The only sanctioned ledger-balance accessor is `TrialBalanceService::getCurrentAccountBalance(int $companyId, int $accountId): float`.** | Derives the sign from the account's own root, includes `accounts.opening_balance`, filters `deleted_at`, returns `float` (no 2dp truncation), and throws `CrossTenantAccountException` if the account is not the company's. Everything else in the app that computes a balance (`CoaController::childAccount`, `JournalEntryController::getJournalEntries`, `ReportController::getAccountBalance`) disagrees with it and with each other. | `app/Services/TrialBalanceService.php` |
| **R4** | **Scope explicitly: `company_id` always; `deleted_at IS NULL` on every raw ledger query; `transaction_date` (never `created_at`) for periods.** | `BelongsToCompany::bootBelongsToCompany` only applies its global scope when `Auth::check()` — it **fails open** in exports, queue jobs, cron, artisan and webhooks. Raw `DB::table('journal_entries')` bypasses `SoftDeletes` (BUG-H9 class). P&L filtering on `created_at` is BUG-C4 and is why P&L and Trial Balance cannot tie out today. | `app/Traits/BelongsToCompany.php`; `Accounting Gap/05-reporting.md` Findings 2 & 13; `Accounting Gap/08-prioritized-bug-list.md` BUG-C4 |
| **R5** | **Add no new `JournalEntry` write anywhere.** | After W1, posting goes through the seam in `app/Services/Accounting/` (`PostingSeam::post`). `Tests\Feature\Accounting\ArchitectureTest::test_no_journal_entry_writes_outside_engine` already implements the grep-scan and fails CI on any `JournalEntry::create` / `new JournalEntry` / `JournalEntry::insert` / `->update([...'debit'...])` outside `app/Services/Accounting/`; it is `markTestSkipped()` today and flips on at the end of P2. Reports are read-only, so this only bites if your track adds a *write* (e.g. a "record supplier payment" button) — that must wait for W2/W3 or go through the seam. | `tests/Feature/Accounting/ArchitectureTest.php`; `app/Services/Accounting/PostingSeam.php`; `Accounting Gap/11-technical-implementation-plan.md` §C2, §P2 |
| **R6** | **Put your ledger reads behind one thin interface you own; swap it onto `LedgerReportQuery` when Phase 6 ships.** | Phase 6 / P5.4 delivers `App\Services\Accounting\LedgerReportQuery` owning canonical period/branch/company filters, opening balances, sign rules and roll-up. If your query layer is one class, the swap is mechanical and no report UI changes. | `Accounting Gap/11-technical-implementation-plan.md` §P5.4; `Accounting Gap/10-implementation-roadmap.md` phase 6 |

---

## 2. Dependency verdict — plain statement

### 2a. Build now, zero rework, correct numbers today

These read **only** operational tables (`tasks`, `invoices`, `invoice_details`, `invoice_partials`, `payments`, `payment_applications`, `credits`). They are untouched by the posting engine, untouched by the ledger corruption, and unaffected by cutover.

| Report | Why it's safe |
|---|---|
| **(a) Agent profit** per agent / period / task type | `invoice_details.profit` + `.commission` + `.markup_price` are operational columns written by `InvoiceController::addJournalEntry` alongside — not from — the ledger. |
| **(c) Pending amounts from payment links** | `payments` + `invoice_partials` only. No ledger involvement at all. |
| **(d) Client outstanding / receivable per client** | `invoices.amount − Σ payment_applications.amount`. The apply registry is the operational open-item source; the audit calls it "the good half". |
| Daily sales / ticket-wise, tasks report, void & refund counts, PNR, hotel voucher, airline/segment analytics | `tasks` (+ `task_flight_details`) only — this is exactly Track 1 of `Accounting Gap/15-travel-data-capture-and-reports-plan.md`, which states in its own §0 that Track 1 "do **not** depend on PostingService … and ship with the 5-module go-live package". |

> **W3d note (2026-08-28, `sale-shape-audit.md`/`w3d-brief.md`):** for `agent`-basis service types (the default for flight/rail/ferry/esim/insurance/visa/lounge/hotel — `config('accounting.posting_basis')`), the per-service `SERVICE_REVENUE` ledger leaf now holds the sale's **margin** (sell − cost), not the full sell price — gross turnover for BSP/segment/incentive reports was already, and must stay, reconstructed from `tasks`/`invoice_details`' own fare/tax/BSP-amount fields (2a above), never summed off this ledger line; only `principal`-basis types (tour/cruise/car/event by default) post the full sell price there.

### 2b. Ledger-derived — buildable now, but **the numbers on prod are wrong until P3**

| Report | Corruption that hits it |
|---|---|
| **(b) Supplier payable / what we owe each supplier** | Only computable from the ledger (see 3b). Sits on the same `transactions`/`journal_entries` rows as **2,273 unbalanced posting groups (~11% of 20,723 transactions)**, **46 negative debit/credit lines**, **3 cross-tenant lines**, **25 postings to genuine parent accounts**. |
| Any AP/AR **aging** built off `journal_entries.reconciled` | The flag is all-or-nothing per line with no amount; `BankPaymentController` sets it on user-selected lines without checking the payment covers them; `declineReconcile` deletes one leg of a two-leg document. |
| Anything summing income/expense accounts (gross margin by ledger, P&L tie-out) | Same unbalanced-groups population, plus **7 invoices with zero journal entries** (Aug 2025 – Feb 2026) that will never appear on any ledger-derived report. |
| **(g) Payables due — total owed as of a date, all categories** | The widest blast radius of any report in this document: it sums the **entire `Liabilities` subtree** in one pass, so it inherits (b)'s corruption plus the same population's errors under Agent Profit Payable, Refund Payable, and the client-advance leaf simultaneously. |

**Baseline (prod copy, `Accounting Gap/data-integrity-results-prod-copy.md`):** 2,273 unbalanced posting groups · 7 invoices with zero JEs · 46 sign-error lines · 51 negative money fields (46 JE + 5 refunds) · 3 cross-tenant journal lines (corrected count per `16-phase1-verification-findings-2026-08.md` §A.1 — **not** the 5,195 figure that circulated).

You can still ship 2b reports. Label them, or restrict them to a "verify against the accountant" surface, until P3 repair lands.

### 2c. Genuinely blocked on accounting-track work not yet shipped

| Report | Missing thing | Who ships it |
|---|---|---|
| **Per-client** ledger statement / per-client AR aging **from the GL** | Every client's receivable is pooled into one shared `Clients` leaf (code `1351`). `clients.account_id` was explicitly **removed** (`database/migrations/2025_03_28_105231_remove_account_id_from_clients_table.php`). Party detail survives only as free text in `journal_entries.name`. | P5.3 party de-pooling (`clients.receivable_account_id`) — `Accounting Gap/11-technical-implementation-plan.md` §P5.3 and `13-party-ledger-reattribution-plan.md` |
| **Supplier-side open-item** (invoice-level "which supplier bill did this payment settle") | `payment_applications` is client-side only; there is no supplier application table and no `journal_entries.settled_amount`. | P5.3 open-item completion |
| **(e) Agent settlement / loss-recovery balances** | `agent_settlements` / `_details` / `_payments` tables + `AgentSettlementService` exist but are **completely unwired** — zero controllers, zero routes, zero views (grep for `AgentSettlement` hits only its own 3 models + the service). See 3e for what *is* reportable. | product decision, not accounting-track |
| Balance Sheet, Cash Flow, canonical P&L | Do not exist. | Phase 6 / P5.4 |

**Nothing in 2a or 2b is blocked on the engine.** The engine changes *who writes* the ledger, not *what a report reads*.

---

## 3. Per-report data contract

Precision note used throughout: KWD is a **3-decimal (fils)** currency. Columns at `decimal(…,2)` truncate fils. Verified precisions — `tasks.price/total/tax/...` = `decimal(10,3)` since `2025_09_21_200554_update_decimal_point_in_tasks_table`; `invoices.amount/sub_amount/invoice_charge` = `decimal(15,3)` since `2025_10_15_231200_update_decimal_point_in_invoices_table`; `journal_entries.debit/credit` = `decimal(15,3)` since `2025_10_12_111941_update_decimal_points_in_journal_entries_table`; `payments.amount` = `decimal(15,3)` since `2025_10_11_165105_update_decimal_places_in_payments_table`; `payment_applications.amount` = `decimal(15,3)` from `2026_01_12_154855_create_payment_applications_table`. **Still 2dp:** `accounts.actual_balance` and `invoice_details.task_price` / `supplier_price` / `markup_price`.

> **Decisions 2026-08-27 from the accounting track**, feeding into 3(g) below: `MARKUP_INCOME` → new leaf `4132 "Markup Income"` (today it still resolves to the shared `4130 Commission & Service Fee Income` per `SystemAccountsSeeder::resolveControls()` — the re-point to its own leaf has not shipped yet); `SALARY_PAYABLE` → new leaf `2240 "Salaries & Wages Payable"` (purpose code does not exist in `config('accounting.purpose_codes')` or `SystemAccountsSeeder` today — confirmed absent by reading both); the seam class both feeders post through is `App\Services\Accounting\PostingSeam` (present in the tree at `app/Services/Accounting/PostingSeam.php`, flag-off per the document header); agent settlement (3e) stays deferred until after W3.

---

### (a) Agent profit — per agent, per period, per task type

**Definition.** What the agency (and the agent's commission scheme) earned on the services this agent sold: sell price minus supplier cost, net of the agent's share of the gateway fee. Per-task grain, roll up by agent / period / task type.

**Preferred source: operational (`invoice_details`), not the ledger.** `invoice_details.profit` is the authoritative figure and it is computed *before* posting, in `InvoiceController::addJournalEntry`, as:

```
margin  = invoice sell price − task supplier total
profit  = clientPaid ? (margin + accountingFeePerTask) − agentFeeDeduction
                     :  margin                        − agentFeeDeduction
commission = (agent->type_id ∈ {2,3,4} && profit > 0) ? profit × (agent->commission ?? 0.15) : 0
```

The ledger's profit legs (`Dr Agent Salaries` / `Cr Agent Profit Payable`, `createProfitEntries`) are *derived from* this same number, so reading the ledger buys you nothing and inherits the corruption.

**Reuse the commission scheme, don't re-derive it.** `ReportController::calculateAgentCommission(Agent $agent, $invoices)` already implements the full payout scheme on the stored `invoice_details.profit` / `.commission` columns: it branches on `agents.type_id` 1–4 using `agents.commission` (default `0.15`), `agents.salary` and `agents.target` — type 4 being `(profit − salary) × rate + salary`, gated on `profit > target`, allocated pro-rata per invoice. It is operational, ledger-free, and already correct. Lift the logic into your own service rather than calling the controller (T14).

**Tables + columns (all verified).**

| Table | Columns | Verified in |
|---|---|---|
| `invoice_details` | `invoice_id`, `task_id`, `task_price`, `supplier_price`, `markup_price` *(all `decimal(10,2)`)*, `profit`, `commission` *(`decimal(15,3)`)*, `deleted_at` | `2025_03_17_111051_create_invoice_details_table`; `2026_01_29_130316_add_profit_columns_to_invoice_details_table`; `2025_07_21_164914_add_soft_deletes_to_task_related_tables` |
| `invoices` | `id`, `agent_id`, `client_id`, `invoice_date`, `status`, `amount`, `deleted_at` — **no `company_id` column** | `2024_10_29_063642_create_invoices_table`; soft deletes as above |
| `tasks` | `id`, `type`, `status`, `company_id`, `supplier_id`, `total`, `deleted_at` | `2024_08_23_022024_create_tasks_table` + `2025_09_21_200554_…` |
| `agents` | `id`, `name`, `branch_id`, `type_id`, `commission`, `profit_account_id`, `loss_account_id` | `2024_08_22_093322_create_agents_table`; `2026_02_11_162453_add_profit_loss_accounts_in_agents_table` |
| `branches` | `id`, `company_id` | `2025_03_17_094850_create_branches_table` |

**Company scoping:** `invoices` has **no `company_id`**. The only paths are `invoices.agent_id → agents.branch_id → branches.company_id`, or `invoice_details.task_id → tasks.company_id`. Use one consistently. (P4 adds `invoices.company_id`; when it lands, collapse to that.)

**Sketch.**

```php
// Agent profit, per agent, per task type, for one period. Operational only.
$rows = DB::table('invoice_details as idt')
    ->join('invoices as i',  'i.id',  '=', 'idt.invoice_id')
    ->join('agents as ag',   'ag.id', '=', 'i.agent_id')
    ->join('branches as br', 'br.id', '=', 'ag.branch_id')
    ->join('tasks as t',     't.id',  '=', 'idt.task_id')
    ->where('br.company_id', $companyId)          // R4: explicit, never Auth-derived
    ->whereNull('idt.deleted_at')                 // R4
    ->whereNull('i.deleted_at')                   // R4
    ->whereNull('t.deleted_at')                   // R4
    ->whereBetween('i.invoice_date', [$from, $to])// operational period = invoice_date
    ->groupBy('ag.id', 'ag.name', 't.type')
    ->selectRaw('
        ag.id  as agent_id,
        ag.name as agent_name,
        t.type  as task_type,
        COUNT(*)                     as task_count,
        SUM(idt.task_price)          as sold,
        SUM(idt.supplier_price)      as cost,
        SUM(idt.profit)              as profit,
        SUM(idt.commission)          as commission
    ')
    ->get();
```

**What NOT to read, and the trap avoided.**

- **Do not use `agents.profit_account_id` balances** for "profit earned". That leaf is `Agent Profit Payable` (an *accrual liability* — what you still owe the agent), not profit earned, and its balance is ledger-derived → §2b.
- **Do not use `invoice_details.markup_price` as profit.** It is gross margin *before* the gateway-fee split, it is `decimal(10,2)` (fils truncated), and it is exactly what the existing `ReportController::getProfitAgent` / `getProfitAgentSum` use — which is why those two numbers already differ from what the accounting entries book (and from the "Total Profit" tile on the dashboard, which is fed by `getProfitAgentSum`).
- **Do not use `invoices.agent_loss` / `invoices.company_loss` as amounts.** They are `decimal(5,2)` **percentages** (loss-bearer override), per `2026_03_01_000000_add_loss_bearer_override_to_invoices_table`.
- **Do not filter on `invoice_details.created_at`.** Use `invoices.invoice_date` (or `tasks.issued_date`) — pick one and document it.

**Known-wrong-until-P3?** **No.** Operational rows are untouched by the ledger corruption. Two honest caveats that are *not* P3 issues: (i) `profit`/`commission` are written by `addJournalEntry`, so the 7 invoices with no posting also have `profit = 0` (default) — they surface as zero-profit rows, not as missing rows; (ii) `markup_price`'s 2dp truncation means `Σ markup_price ≠ Σ profit` by up to 0.005/task.

**What changes at engine cutover?** **Nothing.** W1–W6 change which code writes `journal_entries`; `invoice_details.profit` keeps being written by the invoice feeder before it posts. If the feeder is later moved to write `profit` via a service, the column and its meaning are unchanged.

---

### (b) Supplier payable — what we owe each supplier, + aging

**Definition.** Open balance on each supplier's payable control account: cost accrued on issued tasks minus payments/settlements made to that supplier, aged by document date.

**Preferred source: the ledger. There is no operational alternative — say this out loud to stakeholders.**

- Supplier cost/payable is posted at task issue (`TaskController::processIssuedTask`: `Dr <supplier> cost` / `Cr <supplier> payable`, dated `tasks.supplier_pay_date`).
- Supplier **payments** are posted by `BankPaymentController::store` / `ReceiptVoucherController` straight into `journal_entries`. **There is no supplier payment table, no supplier-side `payment_applications`, and no supplier open-item registry** (`Accounting Gap/03-transactions-ar-ap.md` Finding 9; `06-data-model.md` Finding 10).
- `tasks.supplier_status` is **not** a payment flag — its vocabulary is `XX/OK/RQ/AM` (`app/Enums/TaskSupplierStatus.php`), i.e. the supplier's *booking* state.

So: an **accrual-only** proxy (`Σ tasks.total` by supplier, unpaid-agnostic) is operational and correct-today but answers a different question. The real "how much do we owe" number is ledger-derived.

**How to find a supplier's accounts without name matching.** `SupplierCompanyController::store` stamps `accounts.supplier_company_id` on both leaves it creates for a supplier (the payable leaf under `Suppliers (<Type>)` and the cost leaf under `<Type> Cost`). That is a **real FK** — use it instead of `Account::where('name', $supplier->name)`.

**Verified on DEV (2026-08-26):** the FK is stamped on the per-supplier group node only, never on the leaves beneath it. The sketch below works because step 1 finds the group node and step 2 walks its descendants; do not shortcut it by filtering leaves on `supplier_company_id` directly — you will get zero rows.

| Table | Columns | Verified in |
|---|---|---|
| `accounts` | `id`, `company_id`, `parent_id`, `root_id`, `code`, `name`, `level`, `supplier_company_id`, `opening_balance`, `disabled`, `deleted_at` | `2025_03_17_091543_create_accounts_table`; `2025_04_16_130041_add_supplier_company_id_in_accounts_table`; `2026_02_08_100000_add_opening_balance_to_accounts_table`; `2025_04_03_112301_add_new_columns_in_accounts_table` (`is_group`, `disabled`); `2026_08_24_120002_add_engine_columns_to_accounts_table` (`deleted_at`) |
| `supplier_companies` | `id`, `supplier_id`, `company_id`, `account_id`, `group_id`, `is_active` | `2025_02_27_182602_create_supplier_companies_table`; `2025_04_04_085609_update_column_in_supplier_companies_table` |
| `journal_entries` | `id`, `transaction_id`, `company_id`, `account_id`, `branch_id`, `transaction_date`, `debit`, `credit`, `name`, `reconciled`, `reconciled_ref_id`, `task_id`, `invoice_id`, `original_currency`, `original_amount`, `deleted_at` | `2025_03_17_103934_create_general_ledgers_table` (renamed by `2025_03_28_145526_rename_table_general_ledgers_to_journal_entries`); `2025_05_13_015314_…` / `2025_05_13_071726_…` (reconciled); `2025_05_14_074153_add_task_id_to_journal_entries_table`; `2025_08_02_170959_add_original_currency_to_journal_entries_table` |

**Caution — the supplier's payable is a subtree, not one leaf.** `TaskController` auto-creates deeper children *under* the supplier payable leaf: per GDS `issued_by` office and per currency (`getOrCreateCurrencySpecificAccount`, `"<supplier> (<CUR>)"`), both seeded at `code = 2151` then `last + 1`. Those children **do not** carry `supplier_company_id`. Sum the leaf **and its descendants**.

**Better anchor if the registry has been seeded.** `database/seeders/SystemAccountsSeeder` maps `SERVICE_PAYABLE` × each of the 12 task types onto the `Suppliers (<Type>)` node, resolved structurally (never by bare name, and never trusting `is_group`). If `system_accounts` is populated for the company, take the type-level payable parent from `App\Services\Accounting\AccountResolver` and walk down to the per-supplier leaf by `supplier_company_id` — that removes the last name dependency from this report entirely. See open question 4.

**Sketch.**

```php
// 1. Supplier's payable subtree roots (FK, never name matching).
$payableRootIds = DB::table('accounts as a')
    ->join('supplier_companies as sc', 'sc.id', '=', 'a.supplier_company_id')
    ->join('accounts as root', 'root.id', '=', 'a.root_id')
    ->where('a.company_id', $companyId)
    ->where('sc.supplier_id', $supplierId)
    ->where('root.name', 'Liabilities')      // excludes the sibling COST leaf
    ->whereNull('a.deleted_at')
    ->pluck('a.id');

// 2. Walk down (the tree is shallow: leaf -> office -> currency).
$accountIds = $payableRootIds->all();
$frontier   = $accountIds;
while ($frontier) {
    $frontier = DB::table('accounts')
        ->where('company_id', $companyId)->whereNull('deleted_at')
        ->whereIn('parent_id', $frontier)->pluck('id')->all();
    $accountIds = array_merge($accountIds, $frontier);
}

// 3. Balance + aging buckets, credit-normal (liability).
$aging = DB::table('journal_entries as je')
    ->where('je.company_id', $companyId)          // R4
    ->whereNull('je.deleted_at')                  // R4
    ->whereIn('je.account_id', $accountIds)
    ->where('je.transaction_date', '<=', $asOf)   // R4: transaction_date, not created_at
    ->selectRaw("
        SUM(je.credit - je.debit) as balance,
        SUM(CASE WHEN DATEDIFF(?, je.transaction_date) <= 30                                    THEN je.credit - je.debit ELSE 0 END) as b_0_30,
        SUM(CASE WHEN DATEDIFF(?, je.transaction_date) BETWEEN 31 AND 60                        THEN je.credit - je.debit ELSE 0 END) as b_31_60,
        SUM(CASE WHEN DATEDIFF(?, je.transaction_date) BETWEEN 61 AND 90                        THEN je.credit - je.debit ELSE 0 END) as b_61_90,
        SUM(CASE WHEN DATEDIFF(?, je.transaction_date) > 90                                     THEN je.credit - je.debit ELSE 0 END) as b_90_plus
    ", [$asOf, $asOf, $asOf, $asOf])
    ->first();
```

For the single **as-of-now** total of one account (not a period or a bucket), prefer `app(TrialBalanceService::class)->getCurrentAccountBalance($companyId, $accountId)` — it also folds in `accounts.opening_balance`, which the raw query above deliberately does not (add it once per subtree root if your company keyed openings in).

**What NOT to read, and the trap avoided.**

- `accounts.actual_balance` (R1) — the gateway/payable accounts are the *worst* drifted set.
- `CoaController::childAccount()` (what `ReportController::payableSupplier` and `creditors` use): N+1 (one query per node), loads every JE row into PHP, no date filter (lifetime only), rounds with `bcsub(..., 2)` (fils truncated), adds `opening_balance` to lifetime movement (a third opening convention), **excludes children whose `account_dimension = 'payment'` from parent totals** so parent ≠ Σ children, and takes its sign as a `'normal'`/`'reverse'` string from the *call site*. Do not build on it.
- `Account::where('name', 'Creditors')` / `where('name', $supplier->name)` — 244 `Account::where('name', …)` sites exist app-wide; several are unscoped. Supplier renames silently break them and duplicate names collide.
- Account **codes** are not unique either: `database/seeders/CoaSeeder.php` ships `2130` twice (`Suppliers (Hotels)` and `Suppliers (Ferry)`) and `4130` twice (`Commission & Service Fee Income` and `Gateway Fee Recovery`). Resolve by `id` from the FK, never by `code` and never by `name`.
- `journal_entries.reconciled` as an "unpaid" filter (what `ReportController::unpaidaccountsPayableReceivableReport` does) — no amount, no partial settlement, and set without validating coverage.

**Known-wrong-until-P3?** **YES.** Ledger-derived. Expect residual imbalance from the 2,273 unbalanced groups and 46 negative-signed lines. Show an "as-of" stamp and a reconciliation caveat.

**What changes at engine cutover?** **Nothing structural.** Engine-posted supplier documents land in the same `journal_entries` rows with the same `account_id`/`transaction_date`/`debit`/`credit`, grouped by the same `transaction_id`. Your query must simply not require `transactions.doc_type` to be non-null (legacy rows are `NULL`). What *does* change, for the better: after P2 these documents balance, and after P3 the historical ones do too — the same query returns better numbers without an edit.

---

### (c) Pending amounts from payment links

**Definition.** Money requested from clients through gateway links that has not been collected: unpaid/expired payment links, plus invoice partials that carry a link and have not settled.

**Preferred source: operational only (`payments`, `invoice_partials`). Never the ledger** — a link that was never paid has, correctly, no journal entry.

| Table | Columns | Verified in |
|---|---|---|
| `payments` | `id`, `company_id` *(nullable)*, `client_id`, `agent_id`, `invoice_id`, `voucher_number`, `amount`, `currency`, `status`, `payment_url`, `expiry_date`, `payment_date`, `payment_gateway`, `payment_method_id`, `completed`, `is_disabled`, `service_charge`, `gateway_fee`, `settlement_id`, `deleted_at` | `2025_03_17_122129_create_payments_table`; `2025_06_23_162119_add_payment_url_and_expiry_date_into_payments_table`; `2026_03_03_104918_add_company_id_to_payments_table`; `2026_02_02_131951_add_gateway_fee_to_payments_invoice_partials_and_credits`; `2026_04_01_155631_add_column_in_payments_table` (`settlement_id`); soft deletes `2025_07_21_164914_…` |
| `invoice_partials` | `id`, `invoice_id`, `invoice_number`, `client_id`, `amount`, `status`, `expiry_date`, `type`, `charge_id`, `payment_gateway`, `payment_method`, `payment_id`, `receipt_voucher_id`, `invoice_charge`, `service_charge`, `gateway_fee` | `2025_03_17_111603_create_invoice_partials_table`; `2025_09_17_165242_update_invoice_partials_table` (`charge_id`, and it **drops** `has_payment_link`); `2025_10_08_183612_…`; `2026_02_05_190706_…` |
| `payment_applications` | `payment_id`, `credit_id`, `invoice_id`, `invoice_partial_id`, `amount`, `applied_at`, `deleted_at` | `2026_01_12_154855_create_payment_applications_table`; `2026_01_14_181120_add_credit_id_to_payment_applications_table`; `2026_02_11_200000_add_soft_deletes_to_credits_and_payment_applications_table` |

**Sketch.**

```php
// Pending payment links: requested but not collected.
$pending = DB::table('payments as p')
    ->leftJoin('clients as c', 'c.id', '=', 'p.client_id')
    ->leftJoin('agents  as ag','ag.id','=', 'p.agent_id')
    ->leftJoin('branches as br','br.id','=','ag.branch_id')
    ->where(function ($q) use ($companyId) {          // R4 — company_id is NULLABLE on payments
        $q->where('p.company_id', $companyId)
          ->orWhere(fn ($s) => $s->whereNull('p.company_id')->where('br.company_id', $companyId));
    })
    ->whereNull('p.deleted_at')                        // R4
    ->where('p.status', '!=', 'completed')
    ->where('p.is_disabled', false)
    ->whereBetween('p.created_at', [$from, $to])       // link creation is an operational event
    ->selectRaw("
        p.id, p.voucher_number, p.amount, p.currency, p.status, p.payment_gateway,
        p.expiry_date, p.payment_url, ag.name as agent_name,
        CASE WHEN p.expiry_date IS NOT NULL AND p.expiry_date < NOW() THEN 1 ELSE 0 END as is_expired
    ")
    ->get();
```

**What NOT to read, and the trap avoided.**

- **Do not assume `payments.company_id` is populated.** It is nullable and the backfill in `2026_03_03_104918_add_company_id_to_payments_table` only ran `WHERE agent_id IS NOT NULL`. A plain `where('company_id', $x)` silently drops agent-less payments — hence the `orWhere` fallback above.
- **Do not use `created_at` as the *period* basis for anything you will later compare to accounting.** For a link-pipeline report `created_at` is the right operational field (that *is* when the link was raised); just never join that number to a ledger period.
- **Do not compute "pending" as `amount − Σ payment_applications.amount`** for a link that was never paid — applications only exist post-collection. Use `status != 'completed'`.
- **Do not read `invoice_partials.has_payment_link`** — dropped by `2025_09_17_165242_update_invoice_partials_table`.
- **`payments.status` has no enum and no `PaymentStatus` class.** Observed values written in `PaymentController` include `completed`, `pending`, `failed`, `initiate`, `forwarded`, `cancelled`, `paid`, `issued`. Treat `!= 'completed'` as the pending predicate (that is what `PaymentController::outstanding` already does) and enumerate what you actually find on prod before hard-coding a list.

**Known-wrong-until-P3?** **No.** No ledger dependency.

**What changes at engine cutover?** **Nothing.** Payment links do not post until they complete, and completion posting moves to the engine in W2 without touching `payments`/`invoice_partials`.

---

### (d) Client outstanding / receivable per client

**Definition.** Per client: invoiced minus collected, as of a date, with an aging profile.

**Preferred source: operational (`invoices` + `payment_applications`), NOT the ledger.** Every client's receivable is pooled into one shared `Clients` leaf in the GL (code `1351`), so per-client AR is **structurally impossible** from `journal_entries` until P5.3 de-pools it. The apply registry is the correct source and is company-safe once you scope it.

**Tables + columns.** `invoices` (`id`, `client_id`, `agent_id`, `invoice_number`, `invoice_date`, `due_date`, `amount`, `status`, `deleted_at`); `payment_applications` (`invoice_id`, `payment_id`, `credit_id`, `amount`, `deleted_at`); `clients` (`id`, `first_name`, `middle_name`, `last_name`, `name`, `company_id`, `agent_id`, `phone`, `country_code`) — `clients.company_id` added by `2025_09_03_101222_add_company_foreign_in_clients_table`, `first_name`/`middle_name`/`last_name` by `2025_08_05_151930_add_first_middle_last_name_in_clients_table`.

Valid `invoices.status` values are fixed by `App\Enums\InvoiceStatus`: `paid`, `unpaid`, `partial`, `paid by refund`, `refunded`, `partial refund` — and `Invoice::boot()` throws `InvalidArgumentException` on anything else, so the vocabulary is trustworthy. **The values are not**: prod has 12 invoices marked `paid` with zero money on both signals, 18 marked `unpaid` that are fully covered, and 1 mis-marked `partial` (checks 3.1/3.2/3.3). **Compute outstanding from the applications, not from `status`.**

**Sketch.**

```php
$asOf = $request->date('as_of') ?? now();

$outstanding = DB::table('invoices as i')
    ->join('clients as cl', 'cl.id', '=', 'i.client_id')
    ->join('agents as ag',  'ag.id', '=', 'i.agent_id')
    ->join('branches as br','br.id', '=', 'ag.branch_id')
    ->leftJoin('payment_applications as pa', function ($j) use ($asOf) {
        $j->on('pa.invoice_id', '=', 'i.id')
          ->whereNull('pa.deleted_at')                 // R4
          ->where('pa.applied_at', '<=', $asOf);
    })
    ->where('br.company_id', $companyId)               // R4 (invoices has no company_id)
    ->whereNull('i.deleted_at')                        // R4
    ->where('i.invoice_date', '<=', $asOf)
    ->groupBy('cl.id', 'cl.first_name', 'cl.last_name', 'i.id', 'i.invoice_number', 'i.invoice_date', 'i.due_date', 'i.amount')
    ->havingRaw('i.amount - COALESCE(SUM(pa.amount), 0) > 0.0005')
    ->selectRaw('
        cl.id as client_id,
        TRIM(CONCAT(COALESCE(cl.first_name,"")," ",COALESCE(cl.last_name,""))) as client_name,
        i.id as invoice_id, i.invoice_number, i.invoice_date, i.due_date, i.amount,
        COALESCE(SUM(pa.amount), 0)              as applied,
        i.amount - COALESCE(SUM(pa.amount), 0)   as open_amount,
        DATEDIFF(?, i.invoice_date)              as age_days
    ', [$asOf])
    ->get();
```

**What NOT to read, and the trap avoided.**

- **Do not read the `Clients` account balance.** Besides being pooled, the name is ambiguous: `CoaSeeder` seeds **two** accounts literally named `Clients` per company — `1351` under `Accounts Receivable` (asset) and `2610` under `Refund Payable` (liability) — plus a third named `Client` (`2630`) under `Advances`. A `where('name','Clients')` lookup can land on the refund-payable one.
- **Do not trust `invoices.status`** as the open/closed predicate (see drift counts above).
- **Do not treat `clients.credit` as a credit limit.** It is a prepaid top-up balance (`2025_04_23_163107_add_credit_column_in_clients_table`); the `credit_facility` table is dead schema with zero references. No credit-limit feature exists.
- **Client advances / unapplied credit** live in `credits` (`type` ∈ `Topup`/`Refund`/`Invoice`/`Invoice Refund`, enforced by `Credit::boot()`); prod has 27 rows of credit-ledger-vs-applications drift and 3 non-positive source rows — present them as a separate column, never net them into AR silently.

**Known-wrong-until-P3?** **No** for the AR figure itself (operational). **Partly yes** for reconciliation columns: the status-drift and credit-drift rows listed in 3.1–4.3 are real operational data defects that P3's mechanical recompute fixes.

**What changes at engine cutover?** **Nothing.** Later, P5.3 gives each client its own receivable leaf; at that point a *ledger* per-client statement becomes possible as an additional view — your operational report stays valid and stays the tie-out reference.

---

### (e) Agent settlement / loss-recovery balances

**Definition.** Losses charged to an agent (negative-margin sales) and how much of that has been recovered — by offsetting the agent's accrued profit, by a payment link, or from a wallet.

**State of the feature — read this before scoping.** The schema and a service exist and are **not wired to anything**:

- Tables: `agent_settlements` (`settlement_number` unique, `agent_id`, `branch_id`, `company_id`, `total_amount`, `paid_amount`, `remaining_amount`, `status` ∈ `unpaid|partial|paid`, `settlement_date`, `created_by`, `deleted_at`), `agent_settlement_details` (`agent_settlement_id`, `invoice_id`, `invoice_detail_id`, `amount`), `agent_settlement_payments` (`agent_settlement_id`, `amount`, `method` ∈ `profit|payment_link|wallet`, `payment_id`) — migrations `2026_03_18_031249`, `2026_03_18_031721`, `2026_03_18_033850`.
- `app/Services/AgentSettlementService.php` (commit `7f73badab`, "feat: add agent settlement system for loss recovery", 12 files / +637 lines).
- **Grep for `AgentSettlement` across `app/`, `routes/`, `resources/` returns only its own 3 models + the service.** No controller, no route, no view. The `Payment` and `Company` relations the commit added are not in the working tree.

**Three defects in `AgentSettlementService` you must not inherit if the feature is revived:**

1. `settleByProfit()` writes `Transaction::create([... 'reference_type' => 'Settlement' ...])`, but `transactions.reference_type` is an **ENUM restricted to `Receipt|Invoice|Payment|Refund`** (`2025_09_20_071840_alter_reference_type_length_in_transactions_table`). Under MySQL strict mode that insert errors; otherwise it stores `''`.
2. Both `JournalEntry::create()` calls pass `'agent_id' => …`. **`journal_entries` has no `agent_id` column** (no migration adds one) and `JournalEntry::$fillable` does not list it — the attribution is silently dropped.
3. Those two `JournalEntry::create()` calls are exactly what R5 forbids. If this feature ships, its posting must go through `PostingSeam`.

**What IS reportable today, operationally, with zero new schema.** Per-invoice-line loss is `invoice_details.profit < 0`; the bearer split is configured in `agent_loss` (`agent_id`, `company_id`, `loss_bearer` ∈ `company|agent|split`, `agent_percentage`, `company_percentage`, unique on `(agent_id, company_id)` — `2026_02_11_143329_add_agent_loss_table`), overridable per invoice by `invoices.agent_loss` / `company_loss` (**percentages**, `decimal(5,2)`).

```php
// Agent loss exposure (operational). Recovery-to-date requires the settlement
// feature to be wired; until then report exposure only, and label it so.
$loss = DB::table('invoice_details as idt')
    ->join('invoices as i',   'i.id',  '=', 'idt.invoice_id')
    ->join('agents as ag',    'ag.id', '=', 'i.agent_id')
    ->join('branches as br',  'br.id', '=', 'ag.branch_id')
    ->leftJoin('agent_loss as al', function ($j) use ($companyId) {
        $j->on('al.agent_id', '=', 'ag.id')->where('al.company_id', $companyId);
    })
    ->where('br.company_id', $companyId)
    ->whereNull('idt.deleted_at')->whereNull('i.deleted_at')
    ->where('idt.profit', '<', 0)
    ->whereBetween('i.invoice_date', [$from, $to])
    ->groupBy('ag.id', 'ag.name')
    ->selectRaw('
        ag.id as agent_id, ag.name as agent_name,
        SUM(-idt.profit) as total_loss,
        SUM(-idt.profit * COALESCE(i.agent_loss,   al.agent_percentage,   0) / 100) as agent_share,
        SUM(-idt.profit * COALESCE(i.company_loss, al.company_percentage, 100) / 100) as company_share
    ')
    ->get();
```

**What NOT to read.** The agent's `loss_account_id` leaf balance (`Agent Loss Receivable`) — ledger-derived (§2b) and, because the settlement service never ran in production, almost certainly zero regardless of real exposure.

**Known-wrong-until-P3?** Operational exposure: **no**. Any ledger-sourced recovery figure: **yes**.

**What changes at engine cutover?** If the settlement feature is revived, it becomes a **W2/W3-or-later feeder** through `PostingSeam` — that is accounting-track work with a hard R5 dependency. The exposure report above is unaffected.

---

### (f) Reports already half-built in `ReportController` you will be asked for

| Report | Method | Verdict for your track |
|---|---|---|
| **Daily sales (ticket-wise)** | `ReportController::dailySalesReport` (+ `rangeSalesSummary`, `rangeSalesAgents`, `rangeSalesTasks`, `rangeSalesSuppliers`, `rangeSalesRefunds`, `calculateAgentCommission`) | The strongest existing family, and the only one that already reads `invoice_details.profit`. Everything except `rangeSalesSuppliers` is operational. `rangeSalesSuppliers` is ledger-derived, resolves AP by fuzzy `name LIKE '%accounts payable%'` / `'%supplier%'`, and **reads the stored `journal_entries.balance`** as `running_balance` (R2 violation). The single screen periodizes on **six different date columns** (`invoices.invoice_date`, `refunds.refund_date`, `payments.payment_date`, `tasks.supplier_pay_date`, `journal_entries.transaction_date`, plus partials via their invoice). **Its two PDF routes are dead** — `reports.daily-sales.pdf` / `.pdf.download` point at commented-out methods (500 on click); the three `reports/pdf/daily-sales*.blade.php` views are orphaned. Reuse the operational half; rewrite or drop the supplier half. |
| **Tasks report** | `ReportController::tasksReport` / `tasksReportPdf` | Mostly `tasks` (+ flight/hotel details), issued↔void/confirmed pairing by matching `tasks.reference` strings, merged with `Transaction::where('reference_number','LIKE','PV-%')` payment vouchers — so it sorts `tasks.supplier_pay_date` and `transactions.transaction_date` together in one `date` column. Not module-gated → already reaches package clients. **No auth gate; company filter is conditional on `$companyId`.** Safe to extend once you fix those two. |
| **Client report** | `ReportController::clientReport` / `clientReportPdf` | Task-wise client outstanding, operational (`clients` → `tasks`/`invoice_details`/`refund_details`, `invoices` → `invoice_partials`/`payment_applications`), company-scoped via `whereHas('agent.branch.company')`, periodized on `tasks.supplier_pay_date` + `invoices.invoice_date`. Balance is `invoices.amount − Σ invoice_partials.amount where status='paid'` — **not** the applications registry. Running balance restarts at 0 each window (no brought-forward). No auth gate. Good base for (d) if you switch to `payment_applications` and add an opening row. |
| **Agent profit** | `ReportController::getProfitAgent` / `profitAgent`, and `getProfitAgentSum` | Both sum **`markup_price`**, not `profit`. `getProfitAgentSum` is a raw `DB::table` join with **no `deleted_at` filter on `invoice_details` or `invoices`** → counts soft-deleted rows. Neither takes a date range (all-time). `getProfitAgent` is N+1 in PHP over every agent's every invoice. No auth gate. **Replace, don't extend.** |
| **Supplier payable / creditors** | `ReportController::payableSupplier` (`getPayableSupplier`), `creditors` / `creditorsPdf` | `payableSupplier` routes through `CoaController::childAccount(…, 'reverse')` — all-time, no date filter, all childAccount defects, no auth gate. `creditors` name-walks `Liabilities → Accounts Payable → Creditors`, computes its running balance in PHP, and does **`Task::find()` inside the entry loop** (N+1) plus one `JournalEntry` query per child account. `creditorsPdf` groups by comparing `task->supplier->name` strings and has **no null guard** on `$childOfCreditors->first()` (500 for a company whose Creditors node has no children); it is also gated *more* narrowly (`[ADMIN, COMPANY]`) than the screen it exports (`[ADMIN, COMPANY, ACCOUNTANT]`). **Avoid all three.** |
| **Unpaid / paid AP-AR** | `ReportController::unpaidaccountsPayableReceivableReport` / `paidaccountsPayableReceivableReport` | Twins differing only by `reconciled = 0` vs `!= 0`. Driven by `journal_entries.reconciled` with a supplier filter that matches the JE free-text `name` column against `suppliers.name`. Periodized on `transaction_date` (correct). No buckets, no as-of. `abort(403)` for `AGENT`. **Avoid as a source; reuse only the screen layout.** |
| **Payment gateways** | `ReportController::paymentGateways` / `paymentGatewaysReportPdf` | Operational only (`invoices` + `invoice_partials` `status='paid'`, `payments` `invoice_id IS NULL` + `status='completed'`, `charges`), periodized on `invoices.paid_date` / `payments.payment_date`, and **gated with `Gate::authorize('viewPaymentGatewaysReport', Report::class)`**. **Best authorization pattern to copy.** |
| **Pending payment links** | `PaymentController::outstanding` | Already implements (c) plus an unpaid-invoice tab; filters `payments.status != 'completed'` and scopes by an agent-id list built from `role_id`. Closest thing to a working (c). |
| **Profit & Loss** | `ReportController::profitLoss` | Gated (`Gate::authorize('viewProfitLoss', Report::class)`) and classifies by `accounts.report_type` + `level = 3` + code prefix `4`/`5` — better than the 2026-07 audit recorded. Still periodizes on **`created_at`** for both the monthly figures and the 12-month chart, and buckets by `$entry->created_at->format('n')` (BUG-C4). Do not reuse the query. |
| **Dead screens** | `agentReport`, `performance`, `summary`, `accsummary` | All four return `view('reports.maintenance')` as their **first statement**; the bodies below are unreachable (and contain a `journal_entries.balance` select, unscoped raw `transactions` joins, an `accounts.balance` column that does not exist, and an undefined-variable typo). Their blades are orphaned. |
| **Dead route** | `reports.clientmgmnt` → `ReportController::clientMgmnt` | **The method does not exist.** The route 500s; `reports/clientmgmt.blade.php` is orphaned. |
| **Trial balance** | `ReportController::trialBalance` / `Pdf` / `Export` / `Validation` on `TrialBalanceService` | The one report built on the sanctioned service and **the only place in the report layer that filters `je.deleted_at`**. `trialBalanceValidation` surfaces `findUnbalancedTransactions()` — **use it as your data-quality banner** on any §2b report. Gated `[ADMIN, COMPANY, ACCOUNTANT]`. Known limits: branch filter targets `accounts.branch_id` not `je.branch_id`; opening ignores `accounts.opening_balance`; CSV omits opening/closing. **It has no menu entry — it is URL-only today.** |

---

### (g) Payables Due — total owed as of a date, all categories

**Definition.** Everything the company owes as of an as-of date, regardless of category: supplier payables, agent commissions / profit payable, salaries & wages payable, refund payable, and client advances held — grouped by the `Liabilities` subtree, with 0-30/31-60/61-90/90+ aging by `journal_entries.transaction_date`. **An expense already paid is not a payable.** A `5xxx` (Expenses) line that cleared through cash/bank never touches a `Liabilities` leaf; only balances rolling up under the `Liabilities` root count. If a query for this report ever needs to touch an Expenses- or Income-rooted account, the root-selection step (below) is wrong — stop and re-check it.

**Preferred source: the ledger (`Liabilities` root subtree) — the only place all categories meet.** No operational table spans supplier payable + agent profit payable + salary payable + refund payable + client advances at once; each category has its own operational proxy elsewhere in this document (3a's `agents.profit_account_id` balance is the *accrual*, not this; 3b's accrual-only `Σ tasks.total`; 3e's exposure query), but "total owed, across every category, as of a date" only exists as a GL roll-up. This makes it **ledger-derived → §2b applies in full**, and it is the *widest* §2b report in this document — one query sums every corrupted Liabilities subtree at once rather than just the supplier one. Label it "unaudited" on-screen and surface `TrialBalanceService::findUnbalancedTransactions($companyId, $from, $asOf)` as the banner (R6.6).

**Chart it walks.** Verified against `database/seeders/CoaSeeder.php` — cite it, and re-verify on whichever company you run against, since dev may differ from the seeded shape per the duplicate-code trap (T2) and the leaf-vs-group FK placement trap (T29):

- **`2100 Accounts Payable`** → `2110 Creditors`, `21xx Suppliers (<Type>)` (group nodes, one per task type — `2130` is duplicated per T2) and their per-supplier/per-office/per-currency descendants (§3b's subtree walk, unchanged here).
- **`2200 Accrued Expenses`** → `2210 Commissions (Agents)`; `2230 Agent Profit Payable`, whose per-agent leaves are reached via `agents.profit_account_id` (same FK 3a's "what NOT to read" section already warns is an accrual, not profit-earned — here it is exactly the right thing to read, because this report *wants* the accrual); and the **NEW `2240 Salaries & Wages Payable`** — user decision 2026-08-27, purpose code `SALARY_PAYABLE`, to be seeded by the accounting track. It does not exist in `CoaSeeder` or `SystemAccountsSeeder` today (verified by reading both) and will be **absent on companies until the ensure-leaves step runs** — treat it as an optional branch of the walk (a company with no `2240` contributes `0`, not an error).
- **`2600 Refund Payable`** → `2610 Clients`. This is **not** the `1351 Clients` receivable leaf (T1) — same name, opposite side of the balance sheet.
- **The client-advance leaf**: `2632 Payment Gateway`, under `Liabilities > Advances > Client` — purpose code `CLIENT_ADVANCE`. Distinct from the `1300 Payment Gateway` asset leaf that `GATEWAY_CLEARING_*` resolves to (`SystemAccountsSeeder::resolveGatewayClearing()` disambiguates the two by root-chain for exactly this reason).
- **Excluded:** the **NEW `4132 Markup Income`** (2026-08-27 decision) is a Direct Income leaf under `Income`, not `Liabilities` — it is revenue, never a payable. Its appearance in this report's result set is a sign the root-selection step below is broken.

**Sketch.**

```php
// 1. Find the company's Liabilities root. Roots do NOT self-reference root_id —
// TrialBalanceService::resolveAccountNormalSide() and AccountService::resolveRoot()
// both establish that a root row has parent_id === null AND root_id === null; every
// other account's root_id points AT the root row's id. So "root, level 1" is
// (parent_id IS NULL AND root_id IS NULL AND level = 1) — never assume root_id = id.
// Picking *which* of the five roots is Liabilities still takes the name "Liabilities",
// but that one name check is safe, unlike the T1/T9 traps: AccountService::
// assertCanCreateRoot() enforces uniqueness on (company_id, parent_id IS NULL, name)
// and restricts root creation to FIXED_ROOT_NAMES, so there is exactly one row that
// can ever match.
$liabilitiesRootId = DB::table('accounts')
    ->where('company_id', $companyId)
    ->whereNull('parent_id')
    ->whereNull('root_id')
    ->where('level', 1)
    ->where('name', 'Liabilities')
    ->whereNull('deleted_at')
    ->value('id');

// 2. Collect every descendant id (iterative parent_id walk, exactly as §3(b) does
// for one supplier's subtree — here the frontier starts at one root instead of a
// per-supplier group node).
$accountIds = [$liabilitiesRootId];
$frontier   = $accountIds;
while ($frontier) {
    $frontier = DB::table('accounts')
        ->where('company_id', $companyId)->whereNull('deleted_at')
        ->whereIn('parent_id', $frontier)->pluck('id')->all();
    $accountIds = array_merge($accountIds, $frontier);
}

// 3. Keep LEAVES only. NEVER filter on is_group (T22 — 80%+ of posted-to accounts
// on prod are flagged is_group=1). Structural leaf test, as TrialBalanceService uses.
$leafIds = DB::table('accounts as a')
    ->where('a.company_id', $companyId)->whereNull('a.deleted_at')
    ->whereIn('a.id', $accountIds)
    ->whereRaw('NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id AND child.deleted_at IS NULL)')
    ->pluck('a.id');

// 4. One aggregate over journal_entries, credit-normal (liability), aged by
// transaction_date, grouped per leaf. Opening balance folded in once per leaf.
$byLeaf = DB::table('journal_entries as je')
    ->join('accounts as a', 'a.id', '=', 'je.account_id')
    ->where('je.company_id', $companyId)          // R4 — company_id explicit
    ->whereNull('je.deleted_at')                  // R4
    ->whereIn('je.account_id', $leafIds)
    ->where('je.transaction_date', '<=', $asOf)   // R4 — transaction_date, never created_at
    ->groupBy('a.id', 'a.code', 'a.name', 'a.parent_id', 'a.opening_balance')
    ->selectRaw("
        a.id as account_id, a.code, a.name, a.parent_id, a.opening_balance,
        a.opening_balance + SUM(je.credit - je.debit) as balance,
        SUM(CASE WHEN DATEDIFF(?, je.transaction_date) <= 30                THEN je.credit - je.debit ELSE 0 END) as b_0_30,
        SUM(CASE WHEN DATEDIFF(?, je.transaction_date) BETWEEN 31 AND 60    THEN je.credit - je.debit ELSE 0 END) as b_31_60,
        SUM(CASE WHEN DATEDIFF(?, je.transaction_date) BETWEEN 61 AND 90    THEN je.credit - je.debit ELSE 0 END) as b_61_90,
        SUM(CASE WHEN DATEDIFF(?, je.transaction_date) > 90                 THEN je.credit - je.debit ELSE 0 END) as b_90_plus
    ", [$asOf, $asOf, $asOf, $asOf])
    ->get();

// 5. Roll up to the level-2/3 subtree (Accounts Payable / Accrued Expenses / Refund
// Payable / Advances) for the summary view by walking each leaf's parent chain up
// to the account whose parent_id === $liabilitiesRootId (or the fixed level you
// want to summarize at) and summing $byLeaf into that bucket in PHP — the accounts
// table itself is small enough (hundreds of rows/company) that this is one more
// query, not N+1: fetch all ancestors of $accountIds once, keyed by id.
```

For the single **as-of-now** total of one already-known leaf (not a period, not a bucket, not a roll-up) — e.g. spot-checking `2240` for one company — prefer `app(TrialBalanceService::class)->getCurrentAccountBalance($companyId, $accountId)` (R3): it derives the sign from the root, folds in `accounts.opening_balance`, and filters `deleted_at`, which the raw aggregate above must do by hand.

**What NOT to read, and the trap avoided.**

- `accounts.actual_balance` (R1) — drifted worst on exactly the payable/gateway accounts this report totals.
- `journal_entries.balance` (R2) — never a running total, historically 0.
- Any `Account::where('name', …)` lookup below the root level (T1/T9) — `Clients` (T1) and the duplicate `2130`/`4130` codes (T2) both sit inside the subtree this report walks.
- `accounts.is_group` as a leaf filter (T22) — walk structurally (step 3 above), exactly as `TrialBalanceService` does.
- The `Equity` or `Income` roots. If your root-selection query (step 1) is wrong, you will silently sum unrelated subtrees — `4132 Markup Income` (Income) is the specific new trap this decision introduces (see the chart section above).
- `invoices.status` or any `payments` table as a source of "owed" — those are **receivable**-side (what clients owe the company, §3d), the mirror image of this report, and mixing them in inverts the balance sheet.
- The supplier FK: `accounts.supplier_company_id` sits on the per-supplier **group** node only, never on the transactional leaves beneath it (T29, verified on dev 2026-08-26) — irrelevant to this report's own walk (which doesn't need the FK, only `parent_id`), but relevant if you cross-reference a leaf back to its supplier name.

**Known-wrong-until-P3?** **YES — every subtree it touches.** The supplier subtree is worst (the same 2,273 unbalanced posting groups / 46 negative-signed lines documented in §2b and §3b), but Agent Profit Payable, Refund Payable and the client-advance leaf sit on the same corrupted `journal_entries` population. Do not present this as a trusted total; present it as a "here's roughly what we owe, verify with the accountant" surface with the Trial Balance's unbalanced-transactions banner attached.

**What changes at engine cutover?** **Nothing structural.** Engine-posted rows land in the same `accounts`/`journal_entries` tables, under the same subtree, grouped by the same `transaction_id` shape — this query does not need to know whether a row came from legacy code or the engine. What *does* change, for the better: after W1–W3 the salary, commission and advance feeders post through `PostingSeam` as balanced documents, so those three legs of this report improve without any query edit; after P3 the historical supplier legs improve too. One caveat specific to this report: some legacy chat-originated rows have `transactions.company_id = NULL` (the `Transaction` model's bespoke scope, T7, does not backfill it) — this report never reads `transactions.company_id` at all (step 4 filters `journal_entries.company_id`, which is always populated), so it is unaffected, but do not "fix" it by adding a `transactions` join keyed on company — that would silently drop those rows.

**Reuse/avoid.** Reuse `TrialBalanceService`'s leaf-detection pattern (`NOT EXISTS` on `parent_id`) and its opening-balance handling. Avoid `CoaController::childAccount` (2dp `bcsub` rounding, all-time only, sign from a call-site string, excludes `account_dimension='payment'` children from parent totals — see §3b) and `ReportController::payableSupplier` / `creditors` (name-walk, N+1 `Task::find()` per row, no null guard) — both are single-subtree versions of exactly this defect, multiplied across every subtree here.

---

## 4. Traps catalogue

| # | Trap | Where | Consequence if you step on it |
|---|---|---|---|
| T1 | **Two accounts named `Clients` per company** — `1351` under `Accounts Receivable` (asset) and `2610` under `Refund Payable` (liability); plus `Client` (`2630`) under `Advances`. | `database/seeders/CoaSeeder.php` | `where('name','Clients')` returns whichever the DB orders first — an asset or a liability. Sign flips, AR becomes refund payable. |
| T2 | **Duplicate account codes in the seeder** — `2130` = `Suppliers (Hotels)` **and** `Suppliers (Ferry)`; `4130` = `Commission & Service Fee Income` **and** `Gateway Fee Recovery`. | `database/seeders/CoaSeeder.php` | Resolving by `code` is no safer than by `name`. Resolve by `id` via an FK (`accounts.supplier_company_id`, `agents.profit_account_id`) or via `system_accounts.purpose_code`. |
| T3 | **`accounts.actual_balance`** — `decimal(10,2)`, 41.5% drifted on dev, retired. | `2025_03_17_091543_create_accounts_table`; 134 references across 30 files in `app/` | Wrong numbers today; silently frozen after cutover (the engine does not maintain it). |
| T4 | **`journal_entries.balance`** — mixed sign conventions, historically always 0. Read on a **live** path by `ReportController::rangeSalesSuppliers` (surfaced as `running_balance` on Daily Sales supplier rows) and in the dead `ReportController::agentReport` select list. | `2025_03_17_103934_create_general_ledgers_table` | Meaningless running balance. Recompute it in the query (`SUM(debit − credit)` ordered by `transaction_date, id`), never read the column. |
| T5 | **`BelongsToCompany` fails open** — the global scope is applied only `if (Auth::check())`. Applied to `Account`, `JournalEntry`, `Payment`; **not** to `Task`, `Invoice`, `InvoiceDetail`, `InvoicePartial`, `PaymentApplication`, `Credit`, `Client`, `Agent`. | `app/Traits/BelongsToCompany.php` | Exports, queue jobs, cron, artisan and webhooks read **all tenants**. Always pass `company_id` explicitly. |
| T6 | **`getCompanyId()` defaults ADMIN to company 1** — `return (int) session('company_id', 1);`. | `app/Helper/helper.php` | An admin without a session silently reads City Travelers. Never derive the report's company from `Auth` inside a query builder; take it as a parameter. |
| T7 | **`Transaction` has its own bespoke scope, not `BelongsToCompany`** — ADMIN gets `where('company_id','!=',null)` = *every* company; BRANCH filters by `branch_id` only. | `App\Models\Transaction::booted()` | Reading `Transaction` as an ADMIN crosses tenants by design. Add `->where('company_id', $companyId)` yourself. |
| T8 | **`ReportController::getAccountBalance(string $accountName, int $companyId, bool $creditPositive = false): float`** — the same footgun already removed from `TrialBalanceService::getCurrentAccountBalance`: the sign is a caller-supplied flag **defaulting to debit-positive**, not derived from the account. It also resolves by name string (silent `0` if not found) and applies **no date filter at all** (inception-to-date). All four call sites are in `ReportController::getDashboardStats`; only the `Accounts Payable` one passes `creditPositive: true`. Same pattern in `CoaController::childAccount($account, $debitCreditType)` where callers pass `'normal'` / `'reverse'` literals. | `app/Http/Controllers/ReportController.php`, `app/Http/Controllers/CoaController.php` | The code is right today only by coincidence of the four accounts chosen. Add a fifth tile for anything credit-normal (VAT payable, client wallet liability, agent payable), or copy one of the three flag-less lines as a template, and it silently inverts — no exception, no log, a plausible negative on the dashboard. Derive the sign from the account's root, never from the call site. |
| T9 | **Name-based account lookups: 244 `Account::where('name', …)` sites in `app/`**, several unscoped by company. Prod has 3 cross-tenant journal lines caused by exactly this. | app-wide; `Accounting Gap/16-…` §A.1, §F | Renaming an account breaks posting *and* reporting; unscoped lookups cross tenants. |
| T10 | **Soft-delete omission on raw queries — universal in `ReportController`.** `SoftDeletes` covers `tasks`, `journal_entries`, `transactions`, `invoices`, `invoice_details`, `payments`, `task_flight_details` (`2025_07_21_164914_…`), `credits` + `payment_applications` (`2026_02_11_200000_…`). Every `DB::table(...)` bypasses it, and **not one raw ledger query in `ReportController` filters `deleted_at`**. Live offender: `ReportController::accountsReconciliationReport` — its raw `DB::table('journal_entries')` totals query omits the filter while the Eloquent detail query rendered beside it on the same page applies it. Also `getProfitAgentSum`; and the dead `index` / `agentReport` / `performance` / `summary` bodies. | as listed | The summary totals and the detail rows of the same screen disagree the moment any JE is soft-deleted. Deleted invoices reappear in profit totals. |
| T11 | **`accounts.deleted_at` exists but `App\Models\Account` does NOT use `SoftDeletes`.** | `2026_08_24_120002_add_engine_columns_to_accounts_table`; `app/Models/Account.php` | Once `AccountService` starts soft-deleting accounts, every account query in the app (including `TrialBalanceService`'s raw joins) will include them until someone adds the filter. Add `whereNull('accounts.deleted_at')` in your own queries now. |
| T12 | **`created_at` vs `transaction_date`.** Uses `transaction_date` (correct): unpaid/paid AP-AR, acc-reconcile, creditors + PDF, `journalEntriesByDate`, the supplier block of daily sales, task-report payment vouchers, trial balance. **Uses `created_at`: `ReportController::profitLoss`** (both the monthly figures and the 12-month chart, bucketed by `created_at->format('n')`) **and `ReportController::settlementsReport`**. | `Accounting Gap/05-reporting.md` Finding 2 (BUG-C4) | A back-dated journal appears in the trial balance for its true period but in the P&L for the period it was typed. The two can never tie out. |
| T13 | **`decimal(…,2)` truncation on a 3-decimal currency.** Still 2dp: `accounts.actual_balance`, `invoice_details.task_price` / `supplier_price` / `markup_price`. `CoaController::childAccount` additionally rounds every roll-up with `bcsub(..., 2)`. | migrations listed in §3 preamble | Fils vanish; `Σ markup_price ≠ Σ profit`; roll-ups fail to reconcile to their own children. |
| T14 | **24 `catch (\Exception)` blocks in `PaymentController`**, plus `PaymentApplicationService::createCreditPaymentCOA` swallowing its own exceptions and returning `null` inside a committed transaction. | `app/Http/Controllers/PaymentController.php`; `app/Services/PaymentApplicationService.php` | If your report *calls into* these (do not — read the tables directly), a failure returns a success-shaped response with missing data. Never call a controller from a report. |
| T15 | **`payments.company_id` is nullable and only backfilled where `agent_id IS NOT NULL`.** | `2026_03_03_104918_add_company_id_to_payments_table` | `where('company_id', $x)` silently drops agent-less payments from a "pending links" total. |
| T16 | **`invoices` has no `company_id` column at all** (and neither do `invoice_details`, `invoice_partials`, `payment_applications`, `invoice_receipt`). | `2024_10_29_063642_create_invoices_table`; `Accounting Gap/11-…` §P4 | The only tenant path is `agent → branch → company` (or `invoice_details.task_id → tasks.company_id`). Forget the join and the report is cross-tenant. |
| T17 | **`invoices.agent_loss` / `company_loss` are percentages, not amounts** — `decimal(5,2)`, from `add_loss_bearer_override_to_invoices_table`. | `2026_03_01_000000_…` | A "total loss" tile summing them reports a meaningless number. |
| T18 | **`tasks` has no `void_date` column.** Only `refund_date` exists. Voids are identified by `tasks.status`. | all `Schema::table('tasks', …)` migrations | A query joining a non-existent `void_date` fails; a report assuming one exists silently reports zero voids. |
| T19 | **`invoice_partials` has no `deleted_at` in any repo migration, yet `App\Models\InvoicePartial` declares `use SoftDeletes`.** | `app/Models/InvoicePartial.php` vs. the 17 `invoice_partials` migrations | Either the model is broken or prod has drifted (prod drift is documented in `Accounting Gap/14-prod-drift-findings-2026-08.md`). **Verify against the dev branch before writing any `invoice_partials` query.** |
| T20 | **`transactions.reference_type` is a narrow ENUM** (`Receipt|Invoice|Payment|Refund`) while `transaction_type` is free text with incoherent semantics (`credit`, `debit`, `cash`, `payment`, `refund` — sometimes direction, sometimes payment kind). | `2025_09_20_071840_alter_reference_type_length_in_transactions_table`; `Accounting Gap/03-…` Finding 1 | You cannot reliably filter "all sales documents". Use `doc_type` **when present** and fall back on the joined source (`invoice_id`/`payment_id`), never on `transaction_type`. |
| T21 | **`journal_entries` has no `agent_id` column**, so per-agent ledger attribution does not exist. Party identity on a JE line is the free-text `name` column. | all `journal_entries` migrations | Per-agent or per-supplier GL grouping by `name` is string matching. Group by `account_id` and resolve the party from the account's FK. |
| T22 | **`accounts.is_group` is unreliable** — 42,401 of 52,670 JE rows post to `is_group = 1` accounts while only 25 post to accounts that structurally have children. The backfill is deferred. | `Accounting Gap/data-integrity-results-prod-copy.md` 6.1/6.2 | Never filter leaves by `is_group`. Use `NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id)`, as `TrialBalanceService` does. |
| T23 | **Report authorization is mostly enforced in the navigation menu, not in controllers.** `App\Policies\ReportPolicy` defines 9 abilities; exactly **two** are enforced — `viewProfitLoss` (in `ReportController::profitLoss`) and `viewPaymentGatewaysReport` (in `paymentGateways` + its PDF). Nine methods use ad-hoc `role_id` allow-lists instead. **Twelve have no gate at all**: `index`, `clientReport`, `clientReportPdf`, `getAccounts`, `payableSupplier`, `profitAgent`, `receivable`, `totalBank`, `gatewayReceivable`, `tasksReport`, `tasksReportPdf`, `getDashboardStats`. `viewPayableSupplier`, `viewReconcile`, `viewCreditors`, `viewDailySales`, `viewTaskReport`, `viewClientReport` are consumed only by `@can` in the menu. Inconsistency in the other direction: `creditorsPdf` is gated `[ADMIN, COMPANY]` while the screen it exports allows `ACCOUNTANT` too. | `resources/views/layouts/menu.blade.php`, `app/Policies/ReportPolicy.php` | Any authenticated agent can fetch company-wide reports by URL. **Gate every new report route in the controller.** Copy the `paymentGateways` pattern. |
| T24 | **Company scoping is *conditional* in the `transactions`-based reports.** `ReportController::index`, `settlementsReport`, `tasksReport`, `tasksReportPdf` all wrap the filter in `if ($companyId)`. `journalEntriesByDate` is the only one that fails closed (`abort_unless($companyId, 403)`). | `app/Http/Controllers/ReportController.php` | A user whose company cannot be resolved sees **every** company's data. Always `abort_unless($companyId, 403)`. |
| T25 | **`ReportController::index` joins `transactions → companies → agents` on `companies.id = agents.company_id`** — a cartesian product: every agent in the company matches every transaction. | `ReportController::index` | Do not copy this join shape. Also: no `deleted_at`, no period filter, no gate. |
| T26 | **`ReportController::show($account_name)`** is public, **unrouted**, ignores its argument entirely, and hard-codes `Account::where('name', 'clients')->first()` — **lowercase, with no `company_id` clause** (relying on the global scope, which fails open per T5). | `ReportController::show` | Dead today, but it is the template someone will copy. Do not. |
| T27 | **Module gating is per-route, not per-group**, inside `reports.*`. Ungated (reachable by package clients): `agent`, `client`, `client.pdf`, `clientmgmnt`, `performance`, `profit-agent`, `tasks`, `tasks.pdf`, `payment-gateways`. Everything else carries `middleware('module:accounting')`. | `routes/web.php` reports group | Put an operational report behind `module:accounting` and package clients 404. Put an accounting report outside it and you leak the hidden module. |
| T28 | **`tasks` is indexed on `reference` and `created_at` only** (`2026_01_26_133624_add_indexes_in_tasks_table`); the old `unique(supplier_id, reference)` was dropped. | that migration | Date-ranged, company-scoped scans over 10k+ tasks will table-scan. Measure before shipping; ask the accounting track before adding an index (shared table). |
| T29 | `accounts.supplier_company_id` lives on the per-supplier GROUP node only; 0 of 96 leaves carry it (dev, company 1). `supplier_companies.account_id` is 0/97 populated. | verified on dev 2026-08-26 | A leaf-level join returns nothing; walk up to the parent group or down from it. |

---

## 5. Existing report inventory

`app/Http/Controllers/ReportController.php` (~4,200 lines) exposes **34 routed report bindings**, all inside one `prefix 'reports'` group nested in the top-level `Route::middleware(['auth'])`. No policy/permission middleware is attached to any route; `module:accounting` is chained per-route on the accounting subset only. **There are zero Livewire report components** — `app/Livewire/` holds only `Chat`, `Notification`, `NotificationIndex`, and `app/Http/Livewire/` does not exist. Every report screen is a classic Blade page in `resources/views/reports/`.

Legend — **Source:** OP = operational tables only · GL = ledger (`journal_entries`/`transactions`) · MIX = both. **Period:** OP-date = an operational date column · TD = `transaction_date` · CA = `created_at` · — = none (all-time).

| Report screen | Entry point | Source | Period | Gate | Rule compliance |
|---|---|---|---|---|---|
| Reports index | `ReportController::index` | GL (raw) | — | none | Breaks R4 (no `deleted_at`, conditional company filter) + cartesian join (T25). |
| Agent report | `agentReport` | — | — | none | **Dead** (`return view('reports.maintenance')` first line). Unreachable body breaks R2 + R4. |
| Client report (+PDF) | `clientReport`, `clientReportPdf` | OP | OP-date (`tasks.supplier_pay_date`, `invoices.invoice_date`) | none | Follows R1/R2/R5. Uses `invoice_partials` not `payment_applications` for paid-to-date; no brought-forward opening. |
| Client management | `clientMgmnt` | — | — | — | **Route exists, method does not.** 500. View orphaned. |
| Performance / Summary / Acc summary | `performance`, `summary`, `accsummary` | — | — | none | **All three dead** (maintenance view first line). Dead bodies break R4; `accsummary` selects a non-existent `accounts.balance`. |
| Agent profit | `profitAgent` → `getProfitAgent`; `getProfitAgentSum` | OP | — | none | **Breaks R4** (`getProfitAgentSum` raw, no `deleted_at`). Wrong column (`markup_price`, 2dp), no period filter, N+1. Replace. |
| Unpaid / Paid AP-AR | `unpaidaccountsPayableReceivableReport`, `paidaccountsPayableReceivableReport` | GL | TD | `role_id` (403 for AGENT) | §2b. Party resolved by JE free-text `name` (T21). No buckets, no as-of. |
| Accounts reconciliation | `accountsReconciliationReport` | GL (raw + Eloquent) | TD | `role_id` (403 for AGENT) | **Breaks R4** — the raw totals query omits `whereNull('deleted_at')` while the Eloquent detail beside it applies it. The two disagree. |
| Account list (AJAX) | `getAccounts` | `accounts` | — | none | Company-scoped name lookups; no ledger. |
| Payable supplier | `payableSupplier` → `getPayableSupplier` | GL via `CoaController::childAccount(…, 'reverse')` | — | none | §2b. 2dp `bcsub`, all-time, sign from call site, `account_dimension='payment'` exclusion. Avoid. |
| Total receivable / Total bank / Gateway receivable | `receivable`, `totalBank`, `gatewayReceivable` | GL via `childAccount(…, 'normal')` | — | none | §2b; same defects. |
| Creditors (+PDF) | `creditors`, `creditorsPdf` | GL + `Task::find()` per row | TD | `role_id` `[ADMIN,COMPANY,ACCOUNTANT]`; PDF narrower `[ADMIN,COMPANY]` | §2b. Name-walk `Liabilities→Accounts Payable→Creditors`; N+1 twice; PDF groups by supplier **name string** and has no null guard (500 risk). |
| Bank settlements | `settlementsReport` | `transactions` matched by `description LIKE '%Settles to Bank (After 24h)%'` + `SUBSTRING_INDEX(description,' ',1)` | **CA** | `role_id` `[ADMIN,COMPANY,ACCOUNTANT]` | Breaks R4 (CA; conditional company filter on a model with no `BelongsToCompany`). Description-string parsing. |
| Journal entries by date (JSON) | `journalEntriesByDate` | GL | TD | `abort_unless($companyId, 403)`, no role check | §2b. **Fails closed on company** — the one place that does. |
| Profit & Loss | `profitLoss` | GL | **CA** (figures *and* 12-month chart) | `Gate::authorize('viewProfitLoss', Report::class)` | **Breaks R4** (BUG-C4). Classifies by `report_type` + `level = 3` + code prefix; `abs()` mis-signs net-credit expense months (BUG-M3). Gated. Do not reuse the query. |
| Daily sales (+ 2 dead PDF routes) | `dailySalesReport` (+5 range helpers, `calculateAgentCommission`); `dailySalesPdf` / `dailySalesPdfDownload` | MIX | **six different date columns** | `role_id` `[ADMIN,COMPANY,ACCOUNTANT]` | Operational half sound and reads `invoice_details.profit`. `rangeSalesSuppliers` **breaks R2** (`journal_entries.balance` as `running_balance`) and uses fuzzy `LIKE` account lookups. PDF methods commented out → 500; 3 PDF blades orphaned. |
| Tasks report (+PDF) | `tasksReport`, `tasksReportPdf` | OP + `transactions` | OP-date + TD merged into one column | none | Follows R1/R2/R5. Company filter conditional (T24); void/confirmed pairing by `tasks.reference` strings. **Best operational base to copy**, once gated. |
| Payment gateways (+PDF) | `paymentGateways`, `paymentGatewaysReportPdf` | OP | OP-date (`invoices.paid_date`, `payments.payment_date`) | `Gate::authorize('viewPaymentGatewaysReport', Report::class)` | Follows all six rules. **Copy its authorization pattern.** |
| Trial balance (+PDF/Export/Validation) | `trialBalance`, `trialBalancePdf`, `trialBalanceExport`, `trialBalanceValidation` on `TrialBalanceService` | GL via the sanctioned service | TD | `role_id` `[ADMIN,COMPANY,ACCOUNTANT]` | Follows R2/R3/R4 — **the only place in the report layer that filters `je.deleted_at`**. Branch filter targets `accounts.branch_id` not `je.branch_id`; opening ignores `accounts.opening_balance`; CSV omits opening/closing. **No menu entry — URL-only.** |
| Dashboard stats (AJAX) | `getDashboardStats` | MIX — `getAccountBalance` ×4 + `getProfitAgentSum` | — | none | Breaks R4 (no period, no gate). `getAccountBalance` sign footgun (T8). Does **not** read `actual_balance` — it recomputes from `journal_entries`. |
| Account ledger | `JournalEntryController::index` / `show` / `exportPdf` | GL; opening from `accounts.opening_balance` only | user-supplied range | none beyond `auth` | §2b. Any `accountId` can be enumerated. Opening-balance definition disagrees with the Trial Balance. |
| COA day-book | `CoaController::transaction` | `transactions` + lines | filterable | — | §2b. |
| AJAX ledger filter | `AccountingController::filterLedgers` | GL | — | none | Crashes on any JE without an invoice (`$ledger->invoice->agent->name` unguarded). Effectively dead. |
| Supplier ledger by date | `SupplierController::ledgerByDateRange` | OP (`tasks`) | OP-date (`supplier_pay_date`) | `Gate::authorize('view', Supplier::class)` + `abort_unless($companyId, 403)` | Already hardened. Scopes via `agent.branch.company`, not `tasks.company_id`. |
| Outstanding (payment links + unpaid invoices) | `PaymentController::outstanding` | OP | `created_at` (operationally correct — link creation date) | `role_id` switch, redirect otherwise | Follows R1/R2/R5. Scopes by an agent-id list per `role_id` rather than by `company_id`. |

**Unrouted but public** (do not resurrect without review): `ReportController::show($account_name)` (T26), `getPayableSupplier`, `getProfitAgent`, `getReceivable`, `getTotalBank`, `getGatewayReceivable`.

**Menu reality check.** The Reports menu lists: Paid / Unpaid Acc Pay-Receive, Profit & Loss, Bank Settlement, Transaction List (`coa.transaction`), Creditors, Daily Sales, Task Report, Client Report, Payment Gateways. Acc Reconcile is commented out; Summary / Accounts / Performance / Agent / Client Reports sit in a large commented-out block; **Trial Balance has no entry at all**. The five COA-subtree screens are reachable only from the dashboard KPI tiles fed by `reports.ajax.dashboard-stats`.

**Reuse:** `tasksReport` (structure), `paymentGateways` (especially its gate), `clientReport` (layout), `calculateAgentCommission` (logic), `PaymentController::outstanding`, `TrialBalanceService`.
**Avoid as a source:** anything routed through `CoaController::childAccount` (`payableSupplier`, `receivable`, `totalBank`, `gatewayReceivable`), plus `profitLoss`, `settlementsReport`, `getProfitAgentSum`, `getAccountBalance`, `rangeSalesSuppliers`, the four dead maintenance methods, `filterLedgers`, and `unpaid`/`paidaccountsPayableReceivableReport`.

---

## 6. Integration points later — how you plug into Phase 6

Phase 6 (roadmap file 10, = `11-technical-implementation-plan.md` §P5.4) ships `App\Services\Accounting\LedgerReportQuery`, which will own: canonical period filter (`transaction_date`), branch/cost-centre/company filters, opening-balance computation (via P5.2's opening journal), sign rules keyed off the account root, and the account-tree roll-up. It will also fix P&L to `transaction_date`, fix the `abs()` expense bug, fix the TB branch filter to `je.branch_id`, and add Balance Sheet / AR-AP ageing / Cash Flow.

**Code against this shape now.** Own one interface in your own namespace (suggested `App\Reports\Contracts\LedgerBalanceSource` — the name is yours to pick, not ours to impose). One implementation today; a second, thin one on `LedgerReportQuery` at Phase 6; a config flag or container binding to switch.

```php
namespace App\Reports\Contracts;

use Carbon\CarbonInterface;

interface LedgerBalanceSource
{
    /** Signed balance of one account as of a date, in the account's normal direction. */
    public function balanceAsOf(int $companyId, int $accountId, CarbonInterface $asOf): float;

    /** Signed movement over a period, keyed by account_id. Period = transaction_date. */
    public function movementByAccount(
        int $companyId,
        CarbonInterface $from,
        CarbonInterface $to,
        array $accountIds,
        ?int $branchId = null,
    ): array;

    /** Account ids of a subtree root plus all descendants (leaves included). */
    public function subtreeAccountIds(int $companyId, int $rootAccountId): array;
}
```

Rules for the implementation you write today, so the swap is mechanical:

1. **Every method takes `$companyId` as its first argument.** Never resolve it from `Auth` inside the implementation — that is the single thing that makes the class safe in exports, jobs and cron (R4), and `LedgerReportQuery` will have the same signature discipline (`TrialBalanceService::getCurrentAccountBalance` already does).
2. **`balanceAsOf` with `$asOf = now()` delegates straight to `TrialBalanceService::getCurrentAccountBalance()`** (R3). Only write your own SQL for the dated / bucketed variants the sanctioned accessor does not cover — and when you do, mirror its rules exactly: `whereNull('deleted_at')`, `transaction_date`, sign from the account root.
3. **Return `float`, never a `decimal(10,2)` column value** — the whole point of R1 is that 2dp cannot represent fils.
4. **No `doc_type` in any predicate.** Legacy rows are `NULL`; engine rows are not. If you ever want to *display* the document type, read it as nullable and fall back to `reference_type`.
5. **Keep operational reports off this interface entirely.** (a), (c), (d) and the whole travel Track-1 catalogue never touch the ledger — that separation is what makes them immune to both the corruption and the cutover, and it is the same enforcement line `Accounting Gap/15-travel-data-capture-and-reports-plan.md` §0 draws for Track 1 vs Track 2.
6. **Surface `TrialBalanceService::findUnbalancedTransactions($companyId, $from, $to)` as a banner** on every §2b report until P3 completes. It already exists and is exactly the "these numbers may not foot" signal.

**(g) Payables Due is the first consumer of `subtreeAccountIds()` + `movementByAccount()`.** Its root-find → descendant-walk → leaf-filter → aggregate shape (§3g steps 1–4) is exactly `subtreeAccountIds(companyId, rootAccountId)` feeding `movementByAccount(companyId, from, to, accountIds, branchId)` on this interface — build it as those two calls from day one rather than as inline SQL, and its swap onto `LedgerReportQueryBalanceSource` at Phase 6 is a rebind with no query rewrite.

When Phase 6 lands: write `LedgerReportQueryBalanceSource implements LedgerBalanceSource`, delegate each method, rebind. No report UI changes.

---

## 7. Open questions for the accounting track

1. **Prod schema vs repo schema.** `App\Models\InvoicePartial` uses `SoftDeletes` but no repo migration adds `invoice_partials.deleted_at` (T19). Prod drift is documented (`14-prod-drift-findings-2026-08.md`) and file 16 states the rule "verify against the dev branch (`sync/prod-drift-reconciliation-2026-08-24`), never the local checkout alone." **Which branch is the schema of record for report development, and does `invoice_partials.deleted_at` exist there?**

   **Answer (verified on DEV `citycomm_city-tour-test`, 2026-08-26, read-only):** `invoice_partials.deleted_at` EXISTS (timestamp, nullable) — the model is correct. `invoices.company_id` DOES NOT exist — scope via `agent_id → agents.branch_id → branches.company_id` or `invoice_details.task_id → tasks.company_id`. `transactions` has NO `doc_type` / `posting_status` / `idempotency_key` yet and `system_accounts` does not exist — the P1 accounting-engine migrations are not deployed to dev. Last deployed migration is id 442 `2026_08_26_130000_add_resayil_email_to_users_table` (batch 122). Schema of record for reports = dev server, verify columns there before writing a query.

2. **Supplier payable is ledger-only.** Confirmed there is no supplier-side open-item table (§3b). **Is a supplier payment/settlement record planned for P5.3, or should we ship the ledger-derived version with a P3 caveat and revisit?**
3. **Prod supplier-payable COA shape.** `CoaSeeder` puts supplier payables under `2100 Accounts Payable` (`2110 Creditors`, `2120–2130 Suppliers (<Type>)`), while `TaskController` mints per-office/per-currency children starting at `code = 2151`, and the prod data shows a `2151` leaf. **Is `accounts.supplier_company_id` populated on all prod supplier payable leaves, or only on those created via `SupplierCompanyController::store`?** If not, we need a second resolution path.

   **Answer (verified on DEV, company_id=1, read-only):** `accounts.supplier_company_id` is populated ONLY on the 34 per-supplier GROUP nodes at level 4 (e.g. "Amadeus", "Jazeera Airways"); on the 96 transactional LEAF accounts beneath them (levels 4–6, `is_group=0`) it is 100% NULL — including all 20 largest liability balances (e.g. `2151 Not Issued` under Jazeera Airways 171,992.143; `2151 Magic Holiday (KWD)` 80,665.950; `2232 Mohammed Alhashimi` under Agent Profit Payable 14,153.452). `supplier_companies.account_id` (forward link) is unused: 0 of 97 rows across all companies. Consequence: resolve a supplier's payable subtree by finding the GROUP node via `accounts.supplier_company_id`, then walk `parent_id` descendants to the leaves — never expect the FK on a leaf.

4. **`system_accounts` registry availability.** `database/seeders/SystemAccountsSeeder` (run via `php artisan db:seed --class=SystemAccountsSeeder`, not an artisan command of its own) — **has it been run for City Travelers on dev/prod?** If yes, `system_accounts.purpose_code` (`RECEIVABLE_CONTROL`, `PAYABLE_CONTROL`, `SERVICE_PAYABLE`/`SERVICE_COST` × service_type, `GATEWAY_CLEARING_*`) is a far better resolution path than any name or FK walk, and we would read it through `App\Services\Accounting\AccountResolver`. Its docblock says it skips-and-reports rather than guessing, so **we also need the skip report** — notably `SERVICE_REVENUE` exists only for `flight` and `hotel` in the current COA, and `VAT_OUTPUT` / `SUSPENSE` map nowhere. **Confirm and we will switch.**
5. **`ProfitCalculationService`.** File 16 records that it "exists but is unwired" on the dev branch; it is absent from this checkout. **Is it intended to become the single source of `invoice_details.profit`?** If so, we should read its output rather than the column.
6. **Agent settlement feature.** Tables + `AgentSettlementService` exist, nothing is wired (§3e), and the service has three defects including two `JournalEntry::create()` calls that R5 forbids. **Is the feature dead, deferred, or ours to wire?** If ours, we need a `PostingSeam` feeder from your side first.
7. **Which company/companies are the pilot?** Prod holds City Travelers (70,592 JEs, 458 accounts), Ojeen (463 JEs, 897 accounts, over-provisioned COA), Akeed (0 JEs). **Do reports need to work for a company with an empty or over-provisioned COA?** Note `coa.index` currently returns 500 for a company with no root COA rows (`17-p1-postingservice-complete.md` §5) — the same class of bug will hit any report that assumes roots exist.
8. **Period basis for operational reports.** We propose `invoices.invoice_date` for revenue/profit and `tasks.supplier_pay_date` for supplier cost. **Confirm** — so that when Track-2 tie-out reports arrive they compare like with like against `transaction_date`.
9. **Index budget on shared tables.** `tasks` is indexed only on `reference` and `created_at` (T28). Date-ranged company-scoped reports will need composite indexes on `tasks`, `invoices`, `invoice_details`. **Who owns adding them, and is there a migration freeze during the W1–W6 cutover?**
10. **Authorization.** `ReportPolicy` defines 9 abilities; 2 are enforced, 9 methods use ad-hoc `role_id` allow-lists, and 12 report methods have no gate at all (T23). **Do we add `Gate::authorize` to the existing report routes as part of our work, or is that P4/P5.4 scope?** We will gate all *new* routes regardless, using the `paymentGateways` pattern.
11. **Dead routes and methods.** `reports.clientmgmnt` points at a non-existent `ReportController::clientMgmnt` (500); `reports.daily-sales.pdf` / `.pdf.download` point at commented-out methods (500); `agentReport`, `performance`, `summary`, `accsummary` all return the maintenance view. **Are these ours to delete/restore, or does the launch track own them?** They are currently reachable by anyone authenticated.
