# Audit — Dimension 07: Sub-Ledgers, Year-End & Cross-Cutting Concerns ("Modules & Config")

**Blueprint:** `.claude/skills/travel-accounting-system/references/07-modules-and-config.md`
**Codebase audited:** `C:\Users\User\city-tours-main` (citytourv2 mirror, `main` branch, commit `431f97e6`)
**Date:** 2026-07-07
**Overall completeness for this dimension: ~30%**

The blueprint describes 12 capability areas that "round out" a mature travel-accounting system:
fixed assets, budgeting, recurring journals, FX revaluation, memos, bank/card reconciliation,
apply/release, year-end closing, inter-company, security, staff commission, and
notifications/attachments. citytourv2 has real substance in four of them (reconciliation-lite,
apply, security, commission, notifications) and effectively nothing in the other seven. Two
areas that do exist contain invariant-violating bugs (one-sided/skippable GL postings in the
credit-apply path; inconsistent opening-balance semantics between reports).

---

## Finding 1 — Fixed assets & depreciation: MISSING (dead model only)

**Severity: medium · Status: missing**

### What the blueprint requires
> "An asset register (`tblFADetails`: cost, current value, useful-life months, method, and
> pointers to the asset / accumulated-depreciation / expense accounts) plus monthly
> depreciation rows (`tblFADepreciation`) and disposals (`tblFASale`)." Each acquisition,
> monthly depreciation, and disposal posts a `JV` through the engine; the depreciation
> schedule is kept separate from posting to allow preview and un-post.

### What the code has
- `app/Models/Asset.php` exists but is a bare shell: `purchase_date, asset, name, description,
  serial_no, purchase_price, category_id, company_id` (lines 12–21). No useful life, no
  depreciation method, no current value, no account pointers.
- **There is no migration creating an `assets` table** — `grep "Schema::create('assets'"
  database/migrations/` returns nothing. The model cannot even persist.
- No `AssetController`, no routes (`grep Asset routes/web.php` → no matches), no usage of
  `Asset::` anywhere in `app/`.
- Whole-tree greps for `depreciat`, `fixed asset`, `useful life`, `accumulated` return zero
  hits in `app/`.

### Why it's a gap
The Asset model is dead code — someone started an asset register and abandoned it before the
table existed. There is no acquisition posting, no depreciation schedule, no monthly Dr
Depreciation Expense / Cr Accumulated Depreciation, no disposal gain/loss. Any fixed assets
the agency owns (offices, vehicles, IT) are invisible to the GL except as one-off expense
entries.

### Recommendation
Either delete `app/Models/Asset.php` (dead code) or build the module properly:
1. Migration `fixed_assets` (cost, acquisition_date, useful_life_months, method,
   salvage_value, asset_account_id, accum_depreciation_account_id, expense_account_id,
   current_value, company_id/branch_id) + `fixed_asset_depreciations` (asset_id, period,
   amount, posted_transaction_id nullable).
2. A monthly command (pattern already exists — see `ProcessAgentCommission` scheduled
   `monthlyOn(1,'00:10')` in `app/Console/Kernel.php:21`) that generates schedule rows,
   previews them, and posts each as a balanced Transaction + 2 JournalEntry rows.
3. Disposal flow posting Dr Cash + Dr Accum-Dep / Cr Asset ± Gain/Loss.

---

## Finding 2 — Budgeting: MISSING (vestigial zero-filled columns)

**Severity: medium · Status: missing**

### What the blueprint requires
> "`tblBudgetDetails`: a budget per account per year per branch/cost-center, with 12 monthly
> columns. No GL posting — budgets are informational. The budget-variance report joins budget
> to actual and reports `Budget − Actual` per account/period."

### What the code has
- `accounts.budget_balance` and `accounts.variance` columns exist
  (`app/Models/Account.php:22-23`) — but **every single write sets them to 0**:
  `CoaController.php:186-187, 525-526, 1058-1059, 1098-1099`,
  `AgentController.php:455-456` (and 5 more blocks), `ChargeController.php:239-240`,
  `BranchController.php:132-133`, `ChatController.php:1142-1143`,
  `ImportChartOfAccounts.php:114-115`. The CoaController store validation for them is
  commented out (`CoaController.php:168-170`).
- The Excel import (`app/Imports/AccountsImport.php:21-22`) can technically load non-zero
  values, but nothing ever reads them for reporting.
- No `budgets` table, no per-year or per-month budget rows, no budget entry screen, no
  variance report. `grep -i budget app/Http/Controllers/ReportController.php` → nothing.

### Why it's a gap
The schema gestures at budgeting (a single static number per account) but even that is
unpopulated and unread. The blueprint's model — per account **per year per branch** with 12
monthly buckets — and the Budget-vs-Actual report are entirely absent.

### Recommendation
Drop the dead `budget_balance`/`variance` columns or supersede them with a
`account_budgets` table: `(account_id, year, branch_id, m01..m12)` and a report that joins
`TrialBalanceService::getAccountBalances()` actuals against the budget rows per period. No GL
posting needed — this is the cheapest high-value module in the file.

---

## Finding 3 — Recurring journals: MISSING (recurring *invoicing* exists, recurring *JVs* do not)

**Severity: medium · Status: missing**

### What the blueprint requires
> "A template (`tblAccRecurringHeader` + `tblAccRecurringDetail`) for standing entries (rent
> accrual, monthly provisions). A schedule date and frequency drive posting; when due (and not
> frozen), the engine writes a `JV` from the template lines and advances the next date. Track
> each run so you don't double-post a period."

### What the code has
- No recurring-journal template anywhere (`grep -i recurring app/` → zero matches).
- **Adjacent prior art:** `app/Models/AutoBilling.php` + `app/Console/Commands/RunAutoBilling.php`
  implement recurring **invoice generation** — per-client rules with a daily
  `invoice_time_system`, run every minute from `Kernel.php:22` (`autobill:run`), scanning the
  last 7 days of tasks and invoicing them. This proves scheduling/recurrence machinery exists,
  but it produces invoices from tasks, not standing GL entries.
- Reminders (`app/Models/Reminder.php`) also carry `frequency/value/unit` recurrence fields —
  again messaging, not posting.

### Why it's a gap
There is no way to define "post Dr Rent Expense / Cr Rent Accrual 500 KWD on the 1st of every
month". Accruals and provisions must be typed by hand each period via the manual JV screen,
with no double-post protection.

### Recommendation
Add `recurring_journal_templates` (header: name, frequency, next_run_date, is_frozen,
company_id) + `recurring_journal_template_lines` (account_id, debit, credit, description) +
`recurring_journal_runs` (template_id, period, transaction_id) with a unique index on
(template_id, period) to prevent double posting. Post through the same Transaction+JournalEntry
pattern used by `BankPaymentController::store`. Model the scheduler on `RunAutoBilling`.

---

## Finding 4 — Foreign-currency revaluation: MISSING

**Severity: high · Status: missing**

### What the blueprint requires
> "At period-end, revalue each open foreign balance at the current rate and post the
> **unrealized** difference … `tblAccRateAdj*` records each adjustment, linked to the
> originating open item via the apply registry, so it reverses cleanly when the item settles.
> Can be automatic (`AutoRateAdjustment`) or run on demand; posts as a `JV`."

### What the code has
- Multi-currency **capture** is genuinely present: `journal_entries` store `currency`,
  `exchange_rate`, `original_currency`, `original_amount` (`app/Models/JournalEntry.php:34-44`),
  and `CoaController.php:300-316` converts FC ledgers to KWD for display.
- Rate infrastructure exists: `SystemExchangeRate` synced daily from an API
  (`app/Console/Commands/UpdateExchangeRate.php`), plus `SupplierExchangeRate`,
  `CurrencyExchange`, `ExchangeRateHistory` models and `app/Http/Traits/CurrencyExchangeTrait.php`.
- **But there is no revaluation anywhere**: greps for `revalu`, `unrealized`, `unrealised`,
  `rate adjust`, `exchange gain`, `fx gain`, `gain loss`, `currency gain` across `app/` return
  zero hits. No FX gain/loss accounts exist in `database/seeders/CoaSeeder.php` or the COA
  bootstrap code. No `tblAccRateAdj` analog table.

### Why it's a gap
Open FC supplier payables (TBO, DOTW, Magic Holiday are settled in USD et al.) and FC
receivables sit in the GL at their historical booking rate forever. As rates move, the KWD
balance sheet is silently wrong, and when the item settles at a different rate the difference
lands untracked inside whatever account absorbed it. For a multi-currency travel business this
is one of the two most financially material gaps in this dimension (with year-end).

### Recommendation
1. Seed `FX Gain (Unrealized)` / `FX Loss (Unrealized)` income/expense accounts per company.
2. Add a period-end command: for each account with open FC balances, compute
   `Σ(original_amount × current_rate) − Σ(KWD booked)` and post the difference as a JV,
   recording each adjustment in an `fx_rate_adjustments` table linked to the account (and,
   once open-item tracking matures, to the open document) so it reverses at settlement.
3. Also book **realized** differences at payment time (currently the PV flow at
   `BankPaymentController::store` accepts `exchange_rate` per line but posts naïvely at that
   single rate with no comparison to the invoice's original rate).

---

## Finding 5 — Memos / credit & debit notes: MISSING

**Severity: high · Status: missing**

### What the blueprint requires
> "`tblMemoHeader` (`MemoType` `D`/`C`) + `tblMemoDetail` is the entry front for credit notes
> (`CRN`), debit notes (`DBN`), BSP ADM/ACM memos, and commission adjustments. It posts a
> balanced document like any other. Multi-currency aware; supports single- or multi-line memos."

### What the code has
- Greps for `CRN`, `DBN`, `credit_note`, `debit_note`, `CreditNote`, `DebitNote`, `memo`
  (excluding text-remarks fields) return no memo document implementation.
- Nearest concepts, none of which is a memo document:
  - `app/Models/Credit.php` — a client **wallet** ledger (TOPUP / REFUND / INVOICE rows) used
    to hold and spend prepaid balances; not a numbered, GL-posted credit-note document that
    reduces a specific invoice with reason codes.
  - `app/Models/Refund.php` + `RefundController` — refund workflow with its own numbering
    (`RefundSequence`); covers the customer-refund slice only.
  - Manual JV entry via `BankPaymentController` "Invoice/Payment/Refund" types — a raw journal,
    with no memo semantics, numbering, or AR/AP open-item effect.
- No BSP ADM/ACM ingestion at all (consistent with the ref-04 travel-feeders dimension).

### Why it's a gap
There is no controlled document for "charge the client 20 KWD more" (DBN) or "reduce this
invoice by 15 KWD" (CRN) that posts a balanced GL document and hits the open-item balance.
Today such corrections are done by deleting/regenerating invoices (see Finding 7) — which
destroys audit history — or by raw journal entries that don't touch invoice status.

### Recommendation
Add a `memos` header (+optional detail) table: memo_type (C/D), party (client/agent/supplier),
linked invoice (optional), currency/rate, numbered via the existing `Sequence`/`InvoiceSequence`
pattern, posting a balanced Transaction+JEs (Dr/Cr AR or AP vs income/expense adjustment
account), and feeding the same apply registry as payments so open balances stay true.

---

## Finding 6 — Bank & card reconciliation: PARTIAL (internal matching, no statement import)

**Severity: high · Status: partial**

### What the blueprint requires
> Ledger lines carry `Reconciled / ReconciledDate / ReconciledAmt` plus instrument fields
> (cheque no/date/clearance, bank, auth no). Reconciliation = (1) import the bank/card
> statement into a staging table, (2) match statement lines to GL lines — cards on
> authorization code, cheques on cheque number — (3) mark GL lines reconciled with the
> statement date, (4) report reconciled vs unreconciled; gateway settlements reconcile to the
> **net** deposited.

### What the code has (the good half)
- **Instrument fields on the ledger line:** `journal_entries.cheque_no, cheque_date,
  bank_info, auth_no, reconciled, reconciled_ref_id` (`app/Models/JournalEntry.php:34-45`;
  migrations `2025_05_13_015314_add_reconciled_to_journal_entries_table.php` and
  `2025_05_13_071726_add_reconciled_ref_id_to_journal_entries_table.php`).
- **Matching flow:** `BankPaymentController::store` (lines 179–408): a "PaymentByDate" PV sets
  `reconciled = 2` on its own lines and flips the selected historical supplier JE lines to
  `reconciled = 1` with `reconciled_ref_id` pointing at the settling entry (lines 381–398).
  Flag semantics documented at line 185: `0 = not yet, 1 = record reconciled, 2 = reconciling
  record`.
- **Un-reconcile:** `ReceiptVoucherController::declineReconcile` (lines ~805–825) resets the
  flags on the settling entry and every entry that references it.
- **Reporting:** `ReportController::accountsReconciliationReport` (line 1091+) filters
  reconciled = yes/no/both per supplier; the payable/receivable report splits on
  `reconciled = 0` vs `!= 0` (lines 888–889, 1037–1038).
- **Gateway settlement netting:** `app/Console/Commands/PaymentReleaseToCompanyBankAccProcess.php`
  (scheduled daily, `Kernel.php:17`) groups completed gateway payments by date+gateway,
  computes net = amount − fee JE (lines 80–95), and posts the net to the configured bank
  account — matching the blueprint's "reconcile to the net deposited, fee on its own line".

### What's missing / wrong vs the blueprint
1. **No statement import.** There is no staging table and no upload of an actual bank or card
   statement (grep `statement|bank import` → only unrelated hits). What exists is
   internal-to-internal matching: the user marks *our own* JE lines as settled when creating a
   PV. Nothing ever confirms the bank agrees.
2. **No auth-code matching.** `auth_no` is captured (`BankPaymentController.php:330,361`) but
   never used as a match key anywhere.
3. **No `ReconciledDate` / `ReconciledAmt`** — only the tri-state flag and a ref id, so partial
   reconciliations and statement-date attribution are unrepresentable.
4. Bank balance check before paying (`BankPaymentController.php:240-252`) sums **all** JEs for
   the account with no company filter on the account query itself (account id is
   user-supplied, `pay_from_account => exists:accounts,id` at line 207 — no company scoping in
   the validation rule; the account is fetched by bare `Account::find` at line 229).

### Cross-reference to unmerged branches
The `fix/payment-voucher` branch attempts a `PaymentVoucher` + `PaymentReconciliation` system
(unmerged, 161 commits behind main, likely superseded/stale). Treat this gap as "attempted in
unmerged branch fix/payment-voucher, not production-ready", not green-field.

### Recommendation
Keep the JE-line flags but add: a `bank_statement_lines` staging table (post date, auth code,
cheque no, net amount, card type, import batch), an import (CSV/OFX), a matching screen that
proposes matches on auth_no (cards) and cheque_no (cheques) and stamps
`reconciled_date`/`reconciled_amount`, and extend `accountsReconciliationReport` to show
statement-vs-GL exceptions with ageing.

---

## Finding 7 — Apply / release & auto-allocation: PARTIAL with GL-integrity BUGS

**Severity: high · Status: buggy**

### What the blueprint requires
> "**Apply** — match a payment to invoice(s); write `tblAccIsApply` rows and bump
> `DebitAdj`/`CreditAdj`. **Release** — un-apply; reverse the `*Adj` and delete the rows
> (guarded so balances never go negative). **Auto-allocate** — match a payment to the oldest
> open items first (FIFO), in the document's currency."

### What the code has (the good half)
- **Apply is real:** `app/Services/PaymentApplicationService.php` —
  `applyPaymentsToInvoice()` (lines 37–369) allocates client credit sources (topups/refunds)
  to an invoice in full/partial/split modes, guards against over-draw
  (`if ($availableBalance < $requestedAmount) throw` — lines 195–199), writes
  `PaymentApplication` audit rows (lines 258–267) and negative `Credit` rows (the `*Adj`
  analog, lines 236–248), updates invoice status, all inside `DB::beginTransaction()`.
- **FIFO presentation:** `app/Models/Credit.php:166-171` sorts available credit sources
  oldest-first, and `FixCreditPaymentIds` backfills legacy links FIFO.

### What's missing
1. **No standalone Release.** Un-apply exists only as destructive cascade deletes:
   `InvoiceController::updatePaymentType` (lines 683–716) deletes receipts **and their
   Transactions and JournalEntries**, credits, and partials; `InvoiceController::delete`
   (lines 4023–4057) does the same for the whole invoice. The blueprint's release reverses
   adjustments with negative-balance guards — here GL history is erased instead of reversed
   (audit-trail loss), gated only by the invoice lock check (`checkLocked`, line 4019).
2. **No auto-allocation.** Nothing allocates a payment to the *oldest open invoices* FIFO —
   FIFO exists only on the source-credit side; the user picks invoices manually.

### The bugs (invariant violations)
- **Swallowed GL failure:** `createCreditPaymentCOA()` wraps its entire posting in
  `try/catch` and on exception logs and `return null`
  (`PaymentApplicationService.php:776-784`). Because the caller does not check the return
  value (line 287), the surrounding transaction **commits credits and payment applications
  without any journal entries** — the sub-ledger and GL silently diverge.
- **One-sided posting path:** in the same method, the debit side is only written
  `if ($liabilityAccount)` (line 702) and the credit side only `if ($receivableAccount)`
  (line 744). The accounts are resolved by a fragile 4-level **name walk**
  ("Liabilities" → "Advances" → "Client" → "Payment Gateway", lines 682–700). If either
  lookup fails, the other side still posts → an **unbalanced document**, directly violating
  the ref-02 balanced-posting invariant. These do show up later in
  `TrialBalanceService::findUnbalancedTransactions`, but the engine should refuse to write
  them, not merely report them.

### Recommendation
1. Make `createCreditPaymentCOA` throw (so the wrapping `DB::transaction` rolls the whole
   apply back) and assert Σdebits = Σcredits before commit.
2. Resolve control accounts by stored ID (company settings / `Charge`-style `acc_*_id`
   columns, as `PaymentReleaseToCompanyBankAccProcess` already does) instead of name walking.
3. Add a first-class `release` operation that writes reversing Credit/PaymentApplication rows
   (never deletes GL), guarded so the source balance can't go negative.
4. Add an optional "auto-allocate" that walks the client's open invoices oldest-first.

---

## Finding 8 — Year-end closing: MISSING (with inconsistent opening-balance substitutes)

**Severity: high · Status: missing**

### What the blueprint requires
> In order: (1) refuse if next year already closed / previous year isn't; (2) one `JV`/`OJV`
> header dated day 1 of the new year, posted; (3) carry **balance-sheet** accounts forward as
> "Balance B/F" lines, excluding income and expense; (4) post the year's net P&L to a
> "Profit & Loss <year>" account under the retained-earnings parent; (5) advance the
> financial-period parameters. Reports treat the `OJV` as opening balance, never movement.

### What the code has
- **No close routine at all.** Greps for `OJV`, `B/F`, `carry forward`, `brought forward`,
  `year close`, `closeYear`, `retained` → zero relevant hits. No retained-earnings posting
  exists anywhere; no financial-period parameter table exists.
- **Substitute A — computed opening:** `TrialBalanceService::getOpeningBalances()`
  (lines 131–157) sums *all* JEs before the report's from-date. Functional, but (a) it never
  resets nominal accounts, so income/expense "opening" figures accumulate forever, and
  (b) it scans full history on every report, degrading as data grows.
- **Substitute B — imported opening:** `CoaController::saveOpeningBalances` (lines 1210–1265)
  stores a static `accounts.opening_balance` + `opening_balance_date`
  (migration `2026_02_08_100000_add_opening_balance_to_accounts_table.php`).
- **Substitute C — period locking:** `LockManagementController` + `app/Http/Traits/Lockable.php`
  lock invoices (cascading to their transactions/JEs) by month or arbitrary period, with a
  `manageLocks` gate and unlock-reason logging. Lock columns exist on `invoices`,
  `transactions`, `journal_entries`
  (migration `2026_02_09_133957_add_lock_columns_to_financials_table.php`), but only the
  Invoice record type is wired up — Payments are commented out
  (`LockManagementController.php:41-52`), and standalone PVs/JVs are lockable only via the
  columns, not via any UI path.

### The bug — two irreconcilable opening-balance semantics
- `TrialBalanceService` **ignores** `accounts.opening_balance` entirely (opening = Σ prior
  JEs).
- `JournalEntryController::show` (lines 25–43) and `CoaController::childAccount`
  (lines 326–334) **start from** `accounts.opening_balance` regardless of the requested
  date range — `show()` applies the full static opening balance and then adds only entries
  inside `[date_from, date_to]`, omitting all movement between `opening_balance_date` and
  `date_from`. The same account can therefore show different balances on the trial balance
  vs the ledger drill-down for identical dates. If any user has entered non-zero opening
  balances, one of the two reports is wrong today.

### Recommendation
1. Unify opening-balance semantics now (short term: make ledger views compute opening as
   static `opening_balance` **plus** JEs from `opening_balance_date` to `date_from`, or drop
   the static field into a posted opening JV so every report derives from JEs alone).
2. Build the close: a `financial_periods` table per company; a `close-year` command that
   validates order, posts a locked OJV with B/F lines for balance-sheet leaves, computes
   ΣCredit − ΣDebit over Income+Expenses, posts it to a per-year "Profit & Loss YYYY" account
   under an Equity/Retained-Earnings parent (seed it in `CoaSeeder`), locks the year via the
   existing `Lockable` machinery, and advances the period pointer.
3. Extend `LockManagementController::getRecordTypes()` to cover payments, refunds, and manual
   PVs/JVs so the lock actually seals a period.

---

## Finding 9 — Inter-company posting: MISSING (likely acceptable)

**Severity: low · Status: missing**

### What the blueprint requires
> A config table maps each inter-company relationship to its target database and bridge
> account; a posting in one company auto-mirrors into the other's books via a
> bridge/suspense account, with linkage recorded for reversals. "Use this only if you
> genuinely run multiple legal entities."

### What the code has
- Nothing. `grep -i "inter.?company" app/` → zero hits.
- What exists instead is **multi-tenant isolation**: every accounting row carries
  `company_id`, `app/Http/Traits/BelongsToCompany.php` scopes models, and controllers filter
  by `getCompanyId($user)`. Companies are watertight silos; there is no mirrored posting, no
  bridge/suspense account, no cross-company linkage.
- `SupplierCompany` is a supplier↔company enablement pivot, not inter-company accounting.

### Why this is (mostly) fine
The blueprint itself marks this module conditional. citytourv2's tenants are separate customer
companies, not related legal entities sharing transactions. Flagged for completeness: if the
operator (Alphia/CCG group) ever books cross-entity transactions between its own companies in
this system, there is no mechanism and each side would need manual JVs in both books.

### Recommendation
No action unless a genuine multi-entity requirement appears; then add an
`intercompany_links` config (source company, target company, bridge account each side) and a
mirror-posting hook in the transaction-creation path.

---

## Finding 10 — Security & permissions: PARTIAL

**Severity: medium · Status: partial**

### What the blueprint requires
> Users with rank + encrypted password (history + complexity + renewal policy); menu
> permissions per user/profile with none/view/full **plus an optional WHERE-clause row
> filter**; branch/cost-center scoping; one active session per user with IP/device log and
> inactivity timeout; sensitive-data hiding (payroll) and protected-user locks; a regular
> **integrity routine** flagging unbalanced documents, zero/invalid currency or rate, and
> base-vs-FC mismatches.

### What the code has (the good half)
- **RBAC:** Spatie permissions (`app/Models/Permission.php` extends Spatie's, grouped by
  module; `app/Models/User.php:11,15` uses `HasRoles`), ~24 policies in `app/Policies/`
  combining `role_id` rank checks (`Role::ADMIN/COMPANY/ACCOUNTANT/AGENT`) with named
  permissions (`view invoice`, `create invoice`, … seeded per module in
  `database/seeders/PermissionSeeder.php`) — a reasonable analog of per-menu none/view/full.
- **Branch/company scoping:** enforced query-side per role in every accounting controller
  (e.g. `BankPaymentController::index` lines 51–64), plus `BelongsToCompany` trait.
- **2FA:** `app/Http/Middleware/Verify2FA.php`, `CheckFactorAuthentication.php`,
  `Auth/TwoFAController.php`, `config/google2fa.php`.
- **Sessions:** database driver (`config/session.php:21`).
- **Integrity check (unbalanced docs):** `TrialBalanceService::findUnbalancedTransactions`
  (lines 191–221) flags any Transaction whose JEs differ by > 0.001, surfaced on the trial
  balance screen, its PDF, and an AJAX endpoint (`ReportController.php:4026, 4080, 4162-4182`).
  This is a genuine match for the blueprint's security/integrity routine — for one of its
  three checks.
- **Record locking:** `Lockable` trait + `manageLocks` gate (Finding 8) covers the
  "protect posted data" concern for invoices.

### What's missing
1. **Row-level WHERE-clause filters** per permission (restrict a user to certain
   branches/customers *within* a screen) — nothing equivalent; scoping is hardcoded per role,
   not configurable per user.
2. **Session policy:** no single-active-session enforcement, no IP/device log, no
   inactivity-timeout logic beyond Laravel's session lifetime; no `tblUserSession` analog
   (`grep logoutOtherDevices|user_session` → nothing).
3. **Password policy:** only `min:8` / `Rules\Password::defaults()`
   (`AdminUsersController.php:202`, `Auth/PasswordController.php:31`) — no history, no
   complexity beyond default, no renewal/expiry (`PasswordUpdateToken` is a reset-token
   model, not a policy).
4. **Integrity routine is incomplete:** no check for zero/invalid currency or exchange rate,
   and no base-vs-FC consistency check (`debit/credit` vs `original_amount × exchange_rate`)
   — meaningful here because JE lines default `exchange_rate => 1` on manual entry
   (`BankPaymentController.php:325,356`).
5. No protected-user permission locks; payroll is out of scope (no HR module) so the
   HRJV-hiding rule is N/A.

### Recommendation
Add (a) two more integrity queries alongside `findUnbalancedTransactions` — invalid
currency/rate and FC-vs-base mismatch — and run all three from the scheduler with an alert;
(b) `Session::logoutOtherDevices` on login + a last-activity middleware if single-session is
wanted; (c) a custom password rule (complexity + reuse history table) if policy compliance
matters; (d) if per-branch user restriction is needed, model it as team/branch scoping on the
Spatie permission rows rather than a raw WHERE-clause string.

---

## Finding 11 — Staff (agent) commission: PARTIAL — strongest module in this dimension

**Severity: low · Status: partial**

### What the blueprint requires
> Per-salesperson **per-service** income accounts on the staff record (`TKTIncomeAccID_FK`,
> `TRVIncomeAccID_FK`, `XOIncomeAccID_FK`, cargo/GSA variants), flagged commission-control in
> the COA; sale posts credit the salesperson's account so their GL balance *is* their earned
> commission per service type. Optional: bracketed agent-commission rate table and monthly
> sales targets vs actual feeding management reports.

### What the code has
- **Per-agent GL accounts, auto-created at agent creation** (`AgentController::store`
  lines 448–667): an agent AR account under the branch, an **"Agent Profit Payable"** leaf
  per agent under Liabilities → Accrued Expenses (stored as `agents.profit_account_id`,
  line 550), and an **"Agent Loss Receivable"** leaf per agent under AR (stored as
  `agents.loss_account_id`, line 666).
- **Sale posting credits the agent's account:** invoice COA generation posts profit to
  `$agent->profit_account_id` (`InvoiceController.php:2198-2213, 2441-2449`;
  backfill in `FixInvoiceCoa.php:296-301`; losses measured off `loss_account_id` in
  `AgentController.php:129-134`). So each agent's GL balance *is* their accrued profit share —
  the blueprint's core mechanism, minus the per-service split.
- **Monthly snapshot:** `AgentMonthlyCommissions` (agent, month, year, salary, **target**,
  commission_rate, total_commission, total_profit) computed by
  `app/Console/Commands/ProcessAgentCommission.php` scheduled `monthlyOn(1, '00:10')`
  (`Kernel.php:21`) via `AgentController::calculateMonthlySummary`, displayed in the agent
  profile (`AgentController.php:115-127`, `ProfileController.php:85+`).
- **Bonuses:** `BonusAgent` rows written when a bonus-type PV is posted
  (`BankPaymentController.php:371-379`), reported per month (`AgentController.php:203-208`).

### What's missing vs the blueprint
1. **Per-service-type accounts** — one profit account per agent, not TKT/TRV/XO splits; you
   cannot read "Peter's ticket commission vs his hotel commission" straight off the GL.
   (Per-service revenue can still be derived from `invoice_details`→`tasks.type`, but not as
   a GL control balance.)
2. **Bracketed commission rate table** — `agents.commission` is a single flat rate; no
   brackets → %/amount ladder.
3. Targets exist (`agents.target`, snapshot `target` column) but there is no
   target-vs-actual **variance report** or segment-comparison feed.
4. No `AccTransType = 8`-style commission-control flag on the COA — agent accounts are
   distinguishable only by `agent_id` + naming convention.

### Cross-reference to unmerged branches
The **`agent-settlement`** branch adds `AgentSettlement` / `AgentSettlementDetail` /
`AgentSettlementPayment` + a service for recovering agent losses via profit offset or payment
gateway — i.e. the settlement/clearing leg of this module is *attempted in unmerged branch
agent-settlement, not production-ready*. (A commit with the same feature name exists on the
local project's branch — `7f73badab feat: add agent settlement system for loss recovery` —
but is not in citytourv2 main.)

### Recommendation
If per-service commission is required, add `tkt/trv/xo_income_account_id` columns to `agents`,
split the invoice-posting branch by task type, and add a commission-control flag column to
`accounts`. Add a small bracket table (`agent_commission_brackets`) consumed by
`calculateMonthlySummary`, and a monthly target-vs-actual report off `AgentMonthlyCommissions`.

---

## Finding 12 — Notifications & attachments: PARTIAL (largely present)

**Severity: low · Status: partial**

### What the blueprint requires
> Document attachments (file path + size limit, attached to a document by type/number); email
> and SMS (with per-day quotas and sender); a reminder/follow-up task engine (one-time or
> recurring, multi-channel, e.g. "remind this customer 5 days before the invoice due date").
> None touch the ledger; build them last.

### What the code has (the good half)
- **Reminder engine — the blueprint's exact example is implemented:** `app/Models/Reminder.php`
  targets an invoice or payment, addresses client and/or agent, supports one-time and
  recurring schedules (`frequency`, `value`, `unit`, `scheduled_at`, `group_id`), processed by
  `app/Console/Commands/SendReminders.php` (with dry-run/proceed modes) sending WhatsApp via
  `ResayilController`; plus operational reminders `SendUnassignedTaskReminders` and
  `SendAgentUninvoicedTaskReminders`.
- **Email:** queued `SendInvoiceEmailsJob`, `BulkInvoicesMail` (with attachments),
  `EmailNotificationTrait`, inbound processing `ReadAndProcessEmails` (every 10 min,
  `Kernel.php:16`).
- **WhatsApp:** full Resayil integration (`ResayilController`, `WhatsappController`,
  webhook + media download controllers).
- **Attachments:** `app/Models/PaymentFile.php` links files to payments with an expiry global
  scope; `FileUpload` records processed documents (file_name, destination_path, status);
  `TaskEmail` ties emails to tasks; invoice PDFs are generated and attached to outbound mail.
- **In-app notifications:** `app/Models/Notification.php` + Laravel `Notifiable`.

### What's missing
1. **Generic document-attachment mechanism** — attachments are per-payment (`PaymentFile`)
   and per-task, not a universal "attach any file to any document type/number" registry with
   a size-limit config; e.g. you cannot attach a contract to a JV or a statement to a PV.
2. **Per-day send quotas / sender config per channel** — no quota logic anywhere
   (`grep -i quota|daily limit` → nothing).
3. **SMS** — no SMS channel (WhatsApp fills the role in Kuwait; acceptable substitution).
4. Reminder triggers are absolute (`scheduled_at`); there's no declarative "N days before
   due date" rule — the UI computes the date up front, which loses the rule if the due date
   changes.

### Recommendation
Low priority per the blueprint's own "build them last". If closing the gap: add a polymorphic
`attachments` table (`attachable_type/id`, path, size, uploaded_by) with a max-size config,
and a per-company daily send-quota counter checked in `ResayilController`/mail jobs.

---

## Cross-cutting note — the blueprint's build order

The blueprint closes with: sub-ledgers (this file) are step 7 of 8, and "each step is usable
before the next exists — get steps 1–2 (COA + posting engine) exactly right". citytourv2's
pattern is consistent with a system that skipped ahead: feeders and messaging (steps 6, 8's
notification slice) are comparatively rich, while the period-close/valuation core of this
dimension (year-end, FX revaluation, memos, budgets) is absent, and the posting invariants the
sub-ledgers rely on are enforced by *reporting* (`findUnbalancedTransactions`) rather than by
*refusal at write time*. The two bugs called out in Findings 7 and 8 are the concrete cost of
that ordering.

## Scorecard

| # | Blueprint capability | Status | Severity | Approx. coverage |
|---|---|---|---|---|
| 1 | Fixed assets & depreciation | missing | medium | 5% |
| 2 | Budgeting | missing | medium | 5% |
| 3 | Recurring journals | missing | medium | 10% |
| 4 | FX revaluation | missing | high | 10% |
| 5 | Memos / credit & debit notes | missing | high | 5% |
| 6 | Bank & card reconciliation | partial | high | 45% |
| 7 | Apply / release / auto-allocate | buggy | high | 50% |
| 8 | Year-end closing | missing (+bug) | high | 20% |
| 9 | Inter-company posting | missing (likely N/A) | low | — |
| 10 | Security & permissions | partial | medium | 60% |
| 11 | Staff commission | partial | low | 65% |
| 12 | Notifications & attachments | partial | low | 70% |

**Dimension completeness: ~30%** (inter-company excluded as conditionally applicable).
