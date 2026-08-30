# 08 — Prioritized Bug List

Every finding with **status = `buggy`** that survived (or, for medium/low, was reported by) the audit, ranked **critical → high → medium → low**. Where the same defect was independently reported by more than one dimension auditor, the entries are **merged** and every source finding title is listed so nothing is dropped.

- **[CONFIRMED]** = independently adversarially re-verified (see `verification-log.md`).
- **[UNVERIFIED]** = medium/low finding reported as-found, **not** adversarially re-checked.

Full file:line citations and code excerpts live in the per-dimension reports (`01`–`07`); this list is the actionable index.

---

## CRITICAL

### BUG-C1 — No save-time `debit = credit` enforcement; production paths post one-sided documents  **[CONFIRMED]**
*Sources: Posting Engine "No save-time debit=credit enforcement…"; AR/AP "Balanced-document invariant not enforced; at least five unbalanced posting paths"; Modules "Credit-apply GL posting can silently fail or post one-sided entries".*

**What's wrong:** No posting path asserts header-level `sum(debit)=sum(credit)`. Worse, several paths structurally write half a document:
- `PaymentApplicationService::createCreditPaymentCOA` (702–766) posts each leg inside an independent `if ($account)` guard; a missing name-lookup logs a warning and posts only the other side. The whole method is wrapped in try/catch and **returns null on any exception** (776–784) while the caller ignores the return value, so credits/applications commit with no GL.
- `InvoiceController::addJournalEntry` skips the receivable debit if the `Clients` account is missing (1494) but always posts the income credit (1563).
- `ReceiptVoucherController` `approve()` `account`-mode posts a lone one-sided `JournalEntry` (866) with no counter-leg, and the `import` branch (1111–1224) runs with **no DB transaction** and returns after persisting a one-sided debit.
- `RefundController::handlePaidRefund` (775–792) posts a lone Dr with no Cr.
- `ReceiptVoucherController::declineReconcile` / `BankPaymentController::declineReconcile` delete only one leg of a two-leg settling document.

**Files:** `app/Services/PaymentApplicationService.php`, `app/Http/Controllers/InvoiceController.php`, `app/Http/Controllers/ReceiptVoucherController.php`, `app/Http/Controllers/RefundController.php`, `app/Http/Controllers/BankPaymentController.php`, `app/Http/Controllers/PaymentController.php:6257` (computes `balanced` YES/NO only for a log line).

**Fix:** Build one `PostingService::post(header, lines[])` that opens the DB transaction, resolves **all** accounts up front, **throws** (never warns) on any missing account, and asserts `abs(sum(debit)-sum(credit)) < 0.0005` before commit. Refactor all ~21 `JournalEntry::create` call sites onto it. Immediately: convert the warning-and-continue branches to exceptions, make `createCreditPaymentCOA` re-throw so its transaction rolls back, and wrap the RV `import` branch in a transaction.

---

### BUG-C2 — Leaf-only posting invariant is commented out; group postings vanish from the trial balance  **[CONFIRMED]**
*Sources: CoA "Leaf-only posting invariant unenforced — the guard exists but is commented out"; Posting Engine "Leaf-account-only rule exists only as commented-out code; group postings vanish from trial balance".*

**What's wrong:** The only leaf-account guard ever written (`JournalEntry::creating` hook rejecting postings to accounts with children) is entirely commented out (`JournalEntry.php:57-78`); `accounts.is_group` is written but **never read** by any posting code. `TaskController::processIssuedTask` (2118–2128) deliberately falls back to posting to the **parent** supplier-payable account for 9 of 12 task types. Because `TrialBalanceService` aggregates leaf accounts only (`whereRaw NOT EXISTS child`, lines 96–98 / 147–149), any entry that lands on a group account is **silently excluded** from the trial balance and opening balances — balanced raw data renders as an unbalanced/understated TB. `delegatePriceAmadeus` (`CoaController.php:944+`) is a manual retroactive re-homing tool that exists precisely because this happens in production.

**Files:** `app/Models/JournalEntry.php`, `app/Http/Controllers/TaskController.php`, `app/Services/TrialBalanceService.php`, `app/Http/Controllers/CoaController.php`.

**Fix:** Re-enable the leaf check as a **throwing** `JournalEntry::creating` observer; backfill `is_group` from `children()->exists()`; enforce no-mixed-parents at account creation; backfill existing group-account entries onto explicit leaf children; make the TB fail loudly (or include non-leaf rows) when postings to group accounts are found.

---

### BUG-C3 — Account running balance is scattered, non-atomic, sign-inconsistent, and carries a raw-SQL amount vector; no monthly aggregate  **[CONFIRMED]**
*Sources: Data Model "Account balances maintained by scattered non-atomic read-modify-write; no monthly balance aggregate table" (critical); CoA "Live running balance (actual_balance) maintained in only a few flows" (high); Posting Engine "Eager balance maintenance broken" (high).*

**What's wrong:** `accounts.actual_balance` is mutated by in-PHP `+=`/`-=` at 20+ call sites with **no `lockForUpdate`** (lost-update race under concurrency). The main invoice/task/manual-JV posting paths update nothing; sign conventions differ per path (asset `+= debit` vs liability `+= credit` vs gateway `-= amount`); deletes never reverse it. `AccountingController::storeBankPayment` (930–948) updates the **same** bank account twice (nets zero) while never touching the counterparty, **and interpolates an unvalidated `$request->amount` directly into `DB::raw("actual_balance - {$request->amount}")`** (the `amount` field is not in the validate() rules — a SQL-injection vector). Per-line snapshots read a non-existent attribute (`$clientAccount->balance ?? 0`, `InvoiceController.php:1508`; `Account` has no `balance` column) so they **always store 0**. Six `Fix*` console commands exist solely to repair the resulting drift. No monthly/per-account/branch balance bucket table exists.

**Files:** `app/Http/Controllers/AccountingController.php`, `app/Http/Controllers/CreditController.php`, `app/Http/Controllers/ClientController.php`, `app/Http/Controllers/InvoiceController.php`, `app/Console/Commands/FixPaymentGatewayCOA.php` (+ 5 sibling `Fix*` commands).

**Fix:** **First, patch the SQL-injection vector** (validate `amount` numeric; use a bound/parameterized update). Then either (a) rebuild the aggregate properly — one observer doing atomic `UPDATE … SET actual_balance = actual_balance + (debit-credit)` inside the JE transaction, plus an `account_monthly_balances` table maintained in-transaction and a nightly drift check — **or** (b) drop/rename `actual_balance` and treat it as derived-only. Remove/repair the always-0 `balance` snapshot column.

---

### BUG-C4 — P&L filters by `created_at` while Trial Balance uses `transaction_date` — the two reports can never tie out  **[CONFIRMED]**
**What's wrong:** `ReportController::profitLoss()` (628–629, 680–687) filters and buckets journal entries by `created_at`; `TrialBalanceService` (line 90) correctly uses `transaction_date`. Any back-dated, migrated, or bulk-imported entry (`UpdateOldTaskToTransaction` sets `transaction_date` to a historical date while `created_at` is "now") lands in a **different period** on P&L vs TB, violating the blueprint's core invariant that all reports re-derive from one place and always agree.

**Files:** `app/Http/Controllers/ReportController.php`, `app/Services/TrialBalanceService.php`.

**Fix:** Switch both `profitLoss` queries to `transaction_date`. Add a regression test that posts a JE whose `transaction_date` is in a different month than `created_at` and asserts P&L income/expense totals equal the TB. (Best done as part of the canonical `LedgerReportQuery` — see 09/10.)

---

### BUG-C5 — Report authorization enforced only in navigation menus; agents can fetch company P&L and any account ledger by URL  **[CONFIRMED]**
**What's wrong:** `ReportPolicy` defines granular abilities but the only controller enforcement in the report layer is `Gate::authorize('viewPaymentGatewaysReport')` (3563/3829). All other abilities are checked only via `@can` in menu blades — links hidden, routes open. Confirmed holes reachable by a bare AGENT: `/reports/profit-loss` (no check, not even a role_id list), `/reports/settlements/entries/by-date` (full JE dump), `/journal-entries/{accountId}/account` + `/export/pdf` (any account ledger by id enumeration), `/filter-ledgers`, and `/suppliers/ledger-by-date/{id}` — the last has **no gate and no company scoping** (Task lacks `BelongsToCompany`), i.e. genuine **cross-tenant** exposure by supplier-id enumeration. `journalEntriesByDate` silently returns unscoped cross-company data when `companyId` is falsy.

**Files:** `app/Policies/ReportPolicy.php`, `app/Http/Controllers/ReportController.php`, `app/Http/Controllers/JournalEntryController.php`, `app/Http/Controllers/AccountingController.php`, `app/Http/Controllers/SupplierController.php`, `resources/views/layouts/menu.blade.php`.

**Fix:** Add `Gate::authorize` / `->can()` to **every** report and ledger route using the existing `ReportPolicy`; company-scope `ledgerByDateRange` (and add `BelongsToCompany` to Task or filter explicitly); replace the hard-coded `role_id` allow-lists on the other reports with permission checks.

---

## HIGH

### BUG-H1 — Account code auto-generation is ad-hoc per call site, with random/hardcoded/colliding codes  **[CONFIRMED]**
**What's wrong:** No shared generator. Found: `'AGT-'.rand()` (`AgentController.php:459`), `'BRN-'.rand()` (`BranchController.php:139`), hardcoded `'1213'`/`'5111'` for **every** payment gateway (`ChargeController.php:232,253` — `5111` already belongs to "Visa Cost" in the seeder, a real collision), supplier code = parent code + 1 so all suppliers under one group share a code (`SupplierCompanyController.php:154-160`), lexicographic `MAX()`/`orderBy` on the varchar `code` (`AgentController.php:525`, `TaskController.php:1682-1689`, `InvoiceController.php:1536-1542` with an arbitrary +5 step), and `updateCode` (`CoaController.php:554-591`) sets any code with **no uniqueness check**.

**Files:** `AgentController.php`, `BranchController.php`, `ChargeController.php`, `SupplierCompanyController.php`, `InvoiceController.php`, `TaskController.php`, `CoaController.php`.

**Fix:** One `AccountCodeGenerator` service (numeric max among siblings, pad to sibling width, fallback to row id); migrate all ten sites; add `UNIQUE(company_id, code)` after cleaning existing duplicates.

---

### BUG-H2 — `account_type` is free text, user-suppliable, null for all seeded rows, never consumed  **[CONFIRMED]**
**What's wrong:** `accounts.account_type` is varchar with inconsistent vocabularies (`'liability'` lowercase at `TaskController.php:1699` vs `'Assets'` capitalized at `ImportChartOfAccounts.php:150-164` vs **null for all 132 seeder rows**), taken straight from the HTTP request (`AgentController.php:451`), and read by **zero** conditional logic. Debit/credit orientation instead keys off the root **name** string (`CoaController.php:112-115` hardcodes `'Assets'=>'normal'`, etc., repeated across a dozen controllers). Two parallel unused typing mechanisms exist (`account_type_id → account_types`, never queried; `CoaLabel` enum, queried once).

**Files:** `CoaSeeder.php`, `AgentController.php`, `TaskController.php`, `ImportChartOfAccounts.php`, `CoaController.php`.

**Fix:** One canonical enum **derived** in a model `creating` hook from the fixed root type; backfill from `root_id`; make non-fillable; retire the redundant `account_type_id`/label mechanisms.

---

### BUG-H3 — Unsafe account deletion: no authorization, no child/entry checks, cascade FK silently hard-deletes ledger history  **[CONFIRMED, with correction]**
**What's wrong:** `CoaController::dstry` (536–552, `DELETE /coa/api/{id}`) has no policy check, no children/`journalEntries` guard, and `Account` has no `SoftDeletes`. **Correction to the original finding:** `journal_entries.account_id` *is* FK-constrained with `ON DELETE CASCADE` (added by `2025_03_17_161405_update_foreign_in_general_ledgers_table.php`). So deleting a posted leaf does not "orphan" rows — it **physically hard-deletes every journal_entries row for that account at the DB engine level, bypassing JournalEntry's own SoftDeletes** (irrecoverable ledger destruction). Deleting a **non-leaf** account instead throws an uncaught FK-violation `QueryException` (raw 500) because `parent_id` defaults to RESTRICT. Also `accounts.reference_id` is FK-constrained to `accounts` yet populated with a **user id** (`ChatController.php:1146`) and a **branch id** (`BranchController.php:138`).

**Files:** `CoaController.php`, `create_general_ledgers_table.php` (+ the `_161405` FK migration), `ChatController.php`, `BranchController.php`.

**Fix:** Gate `dstry` behind a real `AccountPolicy::delete`; refuse deletion when children or entries exist (offer disable instead); change `journal_entries.account_id` FK to `RESTRICT`; add `SoftDeletes` to `Account`; repurpose or drop `reference_id`.

---

### BUG-H4 — Edit/delete has no reverse-then-apply discipline; posted documents are mutable & deletable; 16+ bulk-delete sites, reconciled lines unprotected  **[CONFIRMED]**
*Sources: Posting Engine "Edit/delete has no reverse-then-apply discipline…"; AR/AP "Posted ledger documents are mutable and deletable (no draft/posted state)".*

**What's wrong:** Two contradictory regimes. The one good path (`InvoiceController` amount-edit, 4865–5163) posts a reversal with swapped debit/credit but reverses only the **latest** transaction and re-identifies line roles by `str_contains` on **description strings** (4999–5001, 5127–5135). The dominant pattern is bulk soft-delete-and-reinsert: `InvoiceController.php:3843-3845` (invoice update deletes all Transactions/JEs, including `reconciled=1` rows), `ReceiptVoucherController.php:628`, `BankPaymentController.php:531`, `RefundController.php:1276`, `TaskController.php:2428/2466` (selected by `description LIKE`), `MobileController` (770–791, a second unguarded API path). No path reverses `actual_balance` on delete; nothing checks `reconciled` before deleting; `recalculateInvoiceCOA` mutates posted entries in place. No `posted`/`draft` state exists.

**Files:** `InvoiceController.php`, `TaskController.php`, `RefundController.php`, `BankPaymentController.php`, `ReceiptVoucherController.php`, `MobileController.php`, `Models/Transaction.php`, `Models/JournalEntry.php`.

**Fix:** Add `doc_status (draft|posted|void)` on `transactions`; forbid update/delete once posted; implement void/edit as **dated reversal documents**; ban query-level `JournalEntry` deletes outside the posting service; refuse changes when any line has `reconciled != 0`; key reversal targeting on a real `doc_type`, never description text.

---

### BUG-H5 — Opening balances are a mutable account column with two (three) contradictory report definitions  **[CONFIRMED]**
*Sources: Posting Engine "Opening balances are a mutable account column…"; Reporting "Two contradictory opening-balance definitions; ledger invariant opening+movement=closing broken".*

**What's wrong:** Openings live in mutable `accounts.opening_balance` / `opening_balance_date`, edited any time via `CoaController::saveOpeningBalances` (1210–1265) with no zero-sum check and no audit. The definitions disagree: `TrialBalanceService::getOpeningBalances` (131–157) computes opening from **prior journal entries** and ignores the column entirely; `JournalEntryController::show` (25–43) uses **only the static column** and ignores pre-period movement; `CoaController::childAccount` (326–334) adds the column to **all-time** movement (a third convention, double-counting). So the same account shows different balances on TB vs ledger drill-down. No year-end close exists (P&L never resets; "Retained Earnings" account `3400` exists in the seeder but is never posted to).

**Files:** `CoaController.php`, `TrialBalanceService.php`, `JournalEntryController.php`.

**Fix:** Replace the columns with a real posted **Opening Journal Voucher** (OJV) balanced against retained earnings; make TB, ledger, and COA call one shared opening function; build year-close as a command that generates next year's OJV. (See 09/10 — this is coupled to the year-end-close missing feature.)

---

### BUG-H6 — Cross-tenant account resolution: name-based lookups run unscoped in webhook/unauthenticated context  **[CONFIRMED]**
*Sources: Posting Engine "Cross-tenant account resolution…"; AR/AP "Unscoped 'Clients' account lookup in gateway posting". See also `tenant-isolation-audit.md`.*

**What's wrong:** `BelongsToCompany`'s global scope only applies when `Auth::check()` is true. Gateway callbacks (Tap/uPayment/Knet/MyFatoorah/Hesabe) run **unauthenticated** (`->withoutMiddleware(['auth'])`), so `PaymentController::createInvoicePaymentCOA:6143` — `Account::where('name','Clients')->first()` with **no `company_id`** — resolves the first matching company's account (the developer scoped the adjacent `Charge` lookup by `$companyId` but forgot this one). `CoaSeeder` even seeds two different `Clients` accounts per company, so it's ambiguous within a tenant too. `JournalEntryController.php:48-52` fetches root accounts unscoped for ledger math.

**Files:** `PaymentController.php`, `JournalEntryController.php`, `Traits/BelongsToCompany.php`.

**Fix:** Add a per-company **system-account registry** (purpose code → account_id, seeded per company); resolve by purpose code with an explicit `company_id` in all queue/webhook contexts; never rely on the auth-based global scope there. Immediate hotfix: add `->where('company_id', $companyId)` at `PaymentController.php:6143`.

---

### BUG-H7 — Void flow: reversal matched by description, `supplier_date` typo, non-atomic, no idempotency, no settled-ticket guard  **[CONFIRMED]**
**What's wrong:** (a) `voidTask` (2604–2606) selects entries to reverse via `invoice_details.task_description == task.reference`, but the two main invoice paths store the task **description** there (`InvoiceController:1344`, `:6009`), and `Task` has no `description` column at all, so `autoGenerateInvoice` stores **NULL** — the void query matches **zero rows**: the client gets the Credit but AR/revenue are never reversed. (b) `ReverseUnpaidVoidedTask:4469` parses non-existent `$originalTask->supplier_date` → reversal always dated "now". (c) Paid-void is non-atomic — the `DB::transaction` wraps only the Credit; the JE reversal runs outside with a **dangling `commit()`** at :2631. (d) No idempotency — re-running duplicates reversals (and the batch backfill's own dedup guard checks a description string that never matches). (e) No `journal_entries.reconciled` guard before voiding.

**Files:** `app/Http/Controllers/TaskController.php`, `app/Http/Controllers/InvoiceController.php`.

**Fix:** Select reversal entries by `task_id` / `invoice_detail.task_id`, never description strings; fix the `supplier_date` typo; wrap the whole flow in one `DB::transaction`; tag reversals with `reversal_of_transaction_id` for idempotency; block void when original entries are reconciled (supervisor override only).

**See also:** the full W6 void-wave design (two-leg model, AUTO_VOID/VOID WITH FEE/REISSUE/BULK VOID, `TaskStatusService` consolidation, webhook auth) — [22-plan-amendments.md](22-plan-amendments.md) §12.

---

### BUG-H8 — Account Ledger: static opening, no FC view, `filterLedgers` null-crash (500), cross-tenant `ledgerByDateRange`  **[CONFIRMED]**
**What's wrong:** `JournalEntryController::show` opening balance is the **static column only** (wrong for any period after the first), with no local/FC toggle (despite `original_currency`/`original_amount` on JEs), no pagination, no authorization. `AccountingController::filterLedgers` (200) unconditionally reads `$ledger->invoice->agent->name` after guarding `invoice` only at 197 — **any JE without an invoice (payments, manual JVs) throws a null-property error → 500**. `SupplierController::ledgerByDateRange` returns Task rows with no company scoping (Task lacks `BelongsToCompany`) and no gate — cross-tenant exposure (also in BUG-C5).

**Files:** `JournalEntryController.php`, `AccountingController.php`, `SupplierController.php`.

**Fix:** Use the shared opening function; add FC columns + pagination + authorization; fix or delete `filterLedgers`; scope and gate `ledgerByDateRange`.

---

### BUG-H9 — Bank-reconciliation raw totals query includes soft-deleted entries (totals ≠ detail rows)  **[CONFIRMED]**
**What's wrong:** `accountsReconciliationReport` header totals use `DB::table('journal_entries')` (1127) with **no `deleted_at` filter** while the detail rows use Eloquent (SoftDeletes applied) — so totals and rows disagree whenever entries were soft-deleted (which happens routinely). Same hole in `BankPaymentController::fetchPaymentsByDate` (642 raw vs 665 Eloquent) and `ReceiptVoucherController` (712 vs 731). Supplier filtering matches JE free-text `name` via `LIKE`. This is also "internal settlement matching," not GL-vs-statement reconciliation (see 09 for the missing statement import).

**Files:** `ReportController.php`, `BankPaymentController.php`, `ReceiptVoucherController.php`.

**Fix:** Add `whereNull('journal_entries.deleted_at')` to **every** raw JE query (see `data-integrity-queries.sql` to find them); resolve suppliers by FK, not name `LIKE`.

---

### BUG-H10 — Number-series engine: ad-hoc counters, inconsistent locking, cross-tenant `invoice_number` collision, user-supplied RV numbers  **[CONFIRMED]**
**What's wrong:** Three bare counter tables with no mask/branch/year key; `sprintf('INV-%s-%05d')` duplicated in 4 files; no yearly reset (the year is cosmetic). Locking is inconsistent: `CreateBulkInvoicesJob` locks correctly, but `ChatController:618`/`MobileController:264`/`OpenAiController:1024` do `lockForUpdate()->first()` **with no company filter** (increment another tenant's counter), and `InvoiceController:1361`/`RefundController:507`/`PaymentController:1129` use unlocked `firstOrCreate+increment`. `invoices.invoice_number` is **globally unique** while sequences are per-company and the mask has no company discriminator → two companies generating `INV-2026-00042` collide on the unique key. `payments.voucher_number` has no unique index. Receipt-voucher numbers are **user-supplied** (`ReceiptVoucherController.php:261`) or `Str::random(10)`.

**Files:** `InvoiceController.php`, `ChatController.php`, `PaymentController.php`, `ReceiptVoucherController.php`, `CreateBulkInvoicesJob.php`, `create_invoices_table` migration.

**Fix:** One `SequenceService::next(docType, companyId, branchId, date)` backed by a `serial_schemas` table keyed `(doc_type, company_id, branch_id, year)`, always `SELECT … FOR UPDATE` inside the caller's transaction; scope invoice uniqueness to `(company_id, invoice_number)` or embed a branch/company code in the mask; add unique indexes on `payments.voucher_number` and `transactions.reference_number`; generate RV numbers from the engine (never accept from request input).

---

## MEDIUM  *(all UNVERIFIED — reported as-found, not adversarially re-checked)*

### BUG-M1 — CoaSeeder re-run safety & data-quality defects
`updateOrCreate` match array duplicates the `parent_id` key and includes `root_id` (drift creates duplicate rows on re-run); all rows seeded with `account_type=null` and no `label`/`is_group`/`balance_must_be`; `parentMap` keyed by bare name so duplicate names (`Payment Gateway`, `Clients`) overwrite entries and future rows attach to the wrong parent; duplicate codes `2130` and `4130`. **Fix:** key `parentMap` by full path, set `is_group`/`label` per row, fix duplicate codes, tighten the `updateOrCreate` key. *File: `database/seeders/CoaSeeder.php`.*

### BUG-M2 — Manual client top-up posts Dr receivable instead of Dr cash/bank
`CreditController::creditTopup` (126–254) records a client advance by Cr advance-liability and **Dr the pooled `Clients` receivable** (226–244) — money received should debit cash/bank, not inflate AR; the second leg also only posts if the lookup succeeds (conditional-leg pattern, BUG-C1 family). **Fix:** debit the actual cash/bank account chosen by the user; make both legs mandatory via `PostingService`. *File: `CreditController.php`.*

### BUG-M3 — Sign rules duplicated in 4 places; P&L `abs()` overstates expenses on net-credit months
Four independent sign-rule implementations; `profitLoss` line 724 does `$expense += abs($total)`, so an expense group that nets to a credit (rebates/reversals) is **added** to expenses instead of subtracted — overstating expense, understating profit. **Fix:** derive nature once from root/account_type; replace `abs($total)` with `-$total` (debit − credit). *Files: `ReportController.php`, `TrialBalanceService.php`, `JournalEntryController.php`, `CoaController.php`.*

### BUG-M4 — Daily-sales PDF routes point to commented-out methods → 500
`routes/web.php:423-424` register `/reports/daily-sales/pdf` and `/pdf/download` to `dailySalesPdf`/`dailySalesPdfDownload`, both commented out at `ReportController.php:2058`/`:2088` — clicking export throws method-not-found 500. **Fix:** restore or remove the two dead routes. *Files: `ReportController.php`, `routes/web.php`.*

### BUG-M5 — Currency master lacks decimal-places metadata; monetary precision inconsistent (2/3/4 dp)
`currencies` has no `decimal_places`; `journal_entries` started `decimal(15,2)` / rate `(10,2)`, `payment_applications` uses `15,3`, `system_exchange_rates` `10,4` — a trail of five "update decimal point" patch migrations shows rounding drift was repeatedly discovered in production; mixed precision produces sub-fils imbalances the 0.001 tolerance then absorbs. **Fix:** standardize money columns to `decimal(18,3+)` and rates to `decimal(18,6)` in one wave; add `decimal_places` to `currencies`; round only at document boundaries. *Files: `create_currencies_table.php` and the JE/rate precision migrations.*

---

### Coverage note
Every confirmed `buggy` finding from all seven dimensions is represented above. Cross-dimension duplicates were merged (BUG-C1, C2, C3, H4, H5, H6) with all source titles listed so the verification log reconciles 1:1. Findings with status `missing`/`partial` are in **[09-prioritized-missing-features.md](09-prioritized-missing-features.md)**.
