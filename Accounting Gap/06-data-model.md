# Audit 06 — Data Model, Document Numbering & Configuration

> **Note:** Rendered from the analyze-phase agent's structured findings (original detailed write was lost in a workflow path bug); findings verbatim, prose reconstructed.

**Dimension:** Data Model, Document Numbering & Configuration
**Blueprint:** `.claude/skills/travel-accounting-system/references/06-data-model.md`
**Codebase audited:** `citytourv2` (main branch checkout at `scratchpad/citytourv2-main-checkout`, HEAD `431f97e68`)
**Date:** 2026-07-07
**Completeness estimate:** ~40%

---

## Executive summary

Of the 15 findings in this dimension, 3 are **buggy**, 3 are **missing**, 8 are **partial**, and 1 is **present_ok**. By severity: 1 critical, 7 high, 5 medium, 2 low. The critical finding is foundational: account balances are maintained by scattered, non-atomic, unlocked read-modify-write statements across 12+ call sites rather than a monthly balance aggregate table, and the per-line `journal_entries.balance` snapshot is silently always zero due to an attribute-name mismatch — six separate `Fix*` console commands exist solely to repair balance drift in production, which is itself evidence the model is broken.

The seven high-severity findings describe a data model that has the right *tables* in outline but is missing the *discipline* the blueprint requires around them: the number-series engine has ad-hoc duplicated generators, inconsistent locking (some call sites increment another tenant's counter with no company filter), and a latent cross-company `invoice_number` collision; there is no audit-log mirror or `CreateID`/`ModID` convention on core accounting tables; the ledger is a flat table with a nullable header link, no `Posted` flag, and no `DocType`/`SubType`/`DocYear`; there is no financial-year table or period close (only manual per-document lock flags that cannot stop back-dated postings into a closed month); the settings table exists but carries no posting-critical keys, so base currency and control accounts are hardcoded or resolved by name lookup at dozens of sites; there is no unified party master, so client/supplier/agent/airline COA linkage is inconsistent and sometimes cross-tenant-unsafe; and the cost-center dimension is completely absent even though the branch dimension is implemented correctly.

The medium and low findings describe real but narrower gaps — branches lack GL account pointers, the open-item registry covers AR but not AP, travel-specific feeder tables (BSP, airline stock/incentive) are absent consistent with Dimension 04's findings, several sub-ledgers (fixed assets, budget, recurring JV, FX revaluation, memos) are dead code or vestigial, monetary precision is inconsistent across ledger tables (2 vs 3 vs 4 decimal places, with a trail of patch migrations showing repeated production rounding-drift discoveries), and the generic master-lookup table pattern was abandoned in favor of separate lookup tables (an acceptable alternative, but without COA linkage). Employee/user-rights/session masters are the one area judged present_ok, cleanly mapped onto Laravel-native `users`/`sessions`/Spatie permissions.

---

## Summary table

| # | Finding | Status | Severity |
|---|---|---|---|
| 1 | Account balances maintained by scattered non-atomic read-modify-write; no monthly balance aggregate table | buggy | critical |
| 2 | Number-series engine: ad-hoc counters, duplicated generators, inconsistent locking, cross-tenant leaks, latent cross-company invoice_number collision | buggy | high |
| 3 | No audit-log mirror tables; CreateID/ModID convention absent on core accounting tables | missing | high |
| 4 | Ledger is a flat journal_entries table: nullable header link, no Posted flag, no DocType/SubType/DocYear | partial | high |
| 5 | No financial-year table or period close; only manual per-document lock flags | missing | high |
| 6 | System parameters: settings table exists but carries no posting-critical keys; KWD and control accounts hardcoded | partial | high |
| 7 | Party master: no unified tblPartner; per-party COA auto-accounts only for agents; name-based and unscoped account resolution | partial | high |
| 8 | Cost-center dimension completely absent (branch dimension present) | missing | high |
| 9 | Branch table carries no GL account pointers (cash/bank/branch/discount accounts) | partial | medium |
| 10 | Open-item registry partial: payment_applications/credits cover AR only; no supplier-side application; overlapping mechanisms unreconciled | partial | medium |
| 11 | Travel feeder tables: AIR import staging present; BSP reconciliation, airline stock, airline class/incentive tables missing | partial | medium |
| 12 | Sub-ledgers: fixed assets dead code (model without table, no depreciation), no budget details, no recurring JV, no FX revaluation, no memo entries | partial | medium |
| 13 | Currency master lacks decimal-places metadata; monetary precision inconsistent (2 vs 3 vs 4 dp) across ledger tables | buggy | medium |
| 14 | Generic master lookup table (tblMaster) not implemented; "master" table holds only a VERSION row | partial | low |
| 15 | Employee / user-rights / session masters adequately covered by Laravel-native equivalents | present_ok | low |

**Overall completeness: ~40%.**

---

## Finding 1 — Account balances maintained by scattered non-atomic read-modify-write; no monthly balance aggregate table

**Status:** buggy · **Severity:** critical

**Files:**
- `app/Http/Controllers/CreditController.php`
- `app/Http/Controllers/ClientController.php`
- `app/Console/Commands/FixPaymentGatewayCOA.php`
- `app/Services/TrialBalanceService.php`
- `database/migrations/2025_03_17_091543_create_accounts_table.php`

Blueprint requires `tblAccMonthlyBalance` updated atomically inside the posting procedure. Instead `accounts.actual_balance` is mutated with in-PHP `+=`/`-=` at 12+ unlocked call sites (`CreditController.php:212,242`; `ClientController.php:1190`; `CreditClientCredit`/`CheckMyFatoorahPayments`/`FixPaymentGatewayCOA` commands), racing under concurrency. `journal_entries.balance` is a per-line snapshot that `InvoiceController` writes as `$clientAccount->balance ?? 0` — Account has no `balance` attribute (column is `actual_balance`) so it always stores 0. No monthly aggregate exists anywhere (grep `MonthlyBalance` = zero hits); `TrialBalanceService` full-scans `journal_entries` per request including all history for opening balances. Six `Fix*` console commands exist solely to repair balance drift — evidence the model is broken in production.

**Recommendation:** Add an `account_monthly_balances` (account_id, month, branch_id, debit, credit, unique key) updated via `INSERT..ON DUPLICATE KEY UPDATE` in the same DB transaction as JE inserts; derive `actual_balance` from opening + aggregate or drop it; remove or rename the per-line `balance` column.

---

## Finding 2 — Number-series engine: ad-hoc counters, duplicated generators, inconsistent locking, cross-tenant leaks, latent cross-company invoice_number collision

**Status:** buggy · **Severity:** high

**Files:**
- `app/Http/Controllers/InvoiceController.php`
- `app/Http/Controllers/ChatController.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/ReceiptVoucherController.php`
- `app/Jobs/CreateBulkInvoicesJob.php`
- `database/migrations/2024_10_29_063642_create_invoices_table.php`

Blueprint requires SerialSchemas + SerialNumbersShelf + one generator proc with mask (type/branch/year/seq), reset config, and lock-once-posted. Found: 3 bare counter tables (`invoice_sequence`, `sequences`, `refund_sequence`) with no mask/branch/year key; `sprintf('INV-%s-%05d')` duplicated in 4 files; no yearly reset (year in the string is cosmetic). Locking inconsistent: `CreateBulkInvoicesJob` locks correctly; `ChatController:618`, `MobileController:264`, `OpenAiController:1024` do `InvoiceSequence::lockForUpdate()->first()` with NO company filter (increment another tenant's counter); `InvoiceController:1361`, `RefundController:507`, `PaymentController:1129` use unlocked `firstOrCreate`+increment. `invoices.invoice_number` is globally unique while sequences are per-company and the mask has no company discriminator → two companies generating `INV-2026-00042` collide on the unique key. `payments.voucher_number` has no unique index. Receipt-voucher numbers are user-supplied (`ReceiptVoucherController.php:261`) or random `Str::random(10)` (line 1462). `PaymentController` uses the shared `sequences` table ignoring `sequence_for`.

**Recommendation:** One `SequenceService::next(docType, companyId, branchId, date)` backed by a `serial_schemas` table keyed (doc_type, company_id, branch_id, year), always `SELECT..FOR UPDATE` inside the caller's transaction; scope invoice uniqueness to (company_id, invoice_number) or embed branch code in the mask; add unique indexes on `payments.voucher_number` and `transactions.reference_number`; delete duplicated generators; generate RV numbers from the engine.

---

## Finding 3 — No audit-log mirror tables; CreateID/ModID convention absent on core accounting tables

**Status:** missing · **Severity:** high

**Files:**
- `database/migrations/2025_10_12_120102_create_system_log_table.php`
- `app/Services/LoggingHelper.php`
- `app/Http/Controllers/TaskController.php`

Blueprint: every base table carries `CreateID`/`CreateDt`/`ModID`/`ModDt` and has a `*Log` mirror populated by triggers. Found: no `*_log` mirror for any accounting table, no DB triggers, no auditing package in `composer.json`. The only generic log (`system_logs`) is written almost exclusively from `TaskController` (~15 calls, lines 2813-3055) — zero `SystemLog` writes from Invoice/Payment/JournalEntry/Coa/Refund controllers. `journal_entries`, `invoices`, `accounts`, `transactions` have no `created_by`/`updated_by` (only `locked_by`). Journal entries are updatable/deletable with no trace of prior values.

**Recommendation:** Add `created_by`/`updated_by` via observer to `accounts`, `journal_entries`, `transactions`, `invoices`, `payments`; add append-only `journal_entries_log`/`accounts_log` mirrors written by observers or triggers; forbid updates to posted JEs.

---

## Finding 4 — Ledger is a flat journal_entries table: nullable header link, no Posted flag, no DocType/SubType/DocYear

**Status:** partial · **Severity:** high

**Files:**
- `database/migrations/2025_03_17_103934_create_general_ledgers_table.php`
- `app/Models/JournalEntry.php`
- `app/Models/Transaction.php`
- `app/Services/TrialBalanceService.php`

Blueprint requires `tblAccHeader`/`tblAccDetail` with `DocType`, `SubType`, `DocDt`, `DocYear`, `Posted`, `RefNo`/`RefType`. Found: `journal_entries` (renamed from `general_ledgers`) is line-level only; `transactions` is a quasi-header but `journal_entries.transaction_id` is nullable and feeders (`InvoiceController::addJournalEntry` ~line 1494) insert lines one-by-one inside `if ($account)` guards, so documents are never atomically defined and lines can be silently skipped. No Posted lifecycle; `reference_type` started as a 2-value enum vs the blueprint's full doc-type catalog; no DocYear. `TrialBalanceService::findUnbalancedTransactions` (lines 191-221) exists specifically to hunt unbalanced documents the schema permits. Instrument fields (`cheque_no`/`date`, `bank_info`, `auth_no`) and reconciliation flags (`reconciled`, `reconciled_ref_id`) are present — that part matches the blueprint.

**Recommendation:** Make `transaction_id` NOT NULL; add `doc_type`/`sub_type`/`doc_year`/`posted` to `transactions`; route all JE creation through one service that writes header + balanced lines in a single DB transaction.

---

## Finding 5 — No financial-year table or period close; only manual per-document lock flags

**Status:** missing · **Severity:** high

**Files:**
- `database/migrations/2026_02_09_133957_add_lock_columns_to_financials_table.php`
- `app/Http/Traits/Lockable.php`
- `app/Http/Controllers/LockManagementController.php`

Blueprint requires `tblAccYear` plus `FinancialStartingDate`/`EndingDate`, `ProfitLossAccount` (retained earnings sweep) and `AccountsClosingRollbackDisable`. Grep for fiscal/financial_year/closing across `app/` finds no accounting-period entity. The only control is `is_locked`/`locked_by`/`locked_at` on invoices/transactions/journal_entries with a cascade `Lockable` trait — manual, per-document, and it cannot stop back-dating NEW entries into a closed month; unlock is gated only by a `manageLocks` permission. No year-end close exists; P&L accounts accumulate forever.

**Recommendation:** Add `accounting_periods` (company_id, year, start/end, status) enforced in the posting service; implement year-end close posting to a configured retained-earnings account; make close irreversible per blueprint's `AccountsClosingRollbackDisable`.

---

## Finding 6 — System parameters: settings table exists but carries no posting-critical keys; KWD and control accounts hardcoded

**Status:** partial · **Severity:** high

**Files:**
- `app/Models/Setting.php`
- `database/seeders/SettingSeeder.php`
- `app/Services/PaymentApplicationService.php`
- `app/Http/Webhooks/TaskWebhook.php`

Blueprint's `tblSystemParameters` drives SystemCurrency, control accounts, P&L account, serial format, VAT, auto-lock. Found: a good per-company typed settings table (`Setting::getByKey`) but the only keys in use are `invoice_expiry_days` and `notification.*` channels. Base currency 'KWD' is hardcoded at 39 sites in 15 files; AR/AP/gateway control accounts are resolved by account-NAME lookups (`PaymentApplicationService.php:682-741` 'Liabilities'/'Advances'/'Client'/'Payment Gateway'/'Accounts Receivable'/'Clients'); `TaskWebhook.php:596-652` hardcodes tenant-specific account names ('City Travelers (EasyPay)', 'Como Travel & Tourism') in shared code; several lookups lack a company filter (`AccountingController.php:367,385,860,868`; `CreateClientCredit.php:214`). No FX gain/loss, suspense, intercompany, VAT, or numbering parameters exist.

**Recommendation:** Define ~10 validated per-company posting keys (base_currency, receivable/payable control account ids, parent group ids, fx_gain_loss_account_id, retained_earnings_account_id, auto_lock_on_post, gateway suspense) and replace every `Account::where('name',...)` control lookup with them.

---

## Finding 7 — Party master: no unified tblPartner; per-party COA auto-accounts only for agents; name-based and unscoped account resolution

**Status:** partial · **Severity:** high

**Files:**
- `app/Http/Controllers/AgentController.php`
- `database/migrations/2026_02_11_162453_add_profit_loss_accounts_in_agents_table.php`
- `database/migrations/2025_03_28_105231_remove_account_id_from_clients_table.php`
- `app/Schema/TaskSchema.php`

Blueprint: one `tblPartner` (IsCust/IsSupp/IsAirline) with per-party COA pointers and auto-created leaf accounts. Found: four separate masters (clients/suppliers/agents/airlines); COA linkage inverted via `accounts.agent_id`/`client_id`/`supplier_id`; forward pointers exist only for agents (`profit_account_id`/`loss_account_id` plus auto-created AR leaf at `AgentController.php:449` — whose "Assets" root lookup at line 398 is NOT company-scoped, a cross-tenant parenting bug). `clients.account_id` was explicitly removed; all clients share one generic 'Clients' account; supplier accounts are matched by name string to the supplier name (`TaskSchema.php:24`). Credit limits (`clients.credit`, `credit_facility`) and GDS codes (`amadeus_id`, `gds_office_id`, `iata_code`) do exist.

**Recommendation:** Add `receivable_account_id` (clients), `payable_account_id` (suppliers), `account_id` (airlines) FKs; create leaf accounts in party created-observers reusing the pattern proven in the agents migration; company-scope every account lookup.

---

## Finding 8 — Cost-center dimension completely absent (branch dimension present)

**Status:** missing · **Severity:** high

**Files:**
- `database/migrations/2025_03_17_103934_create_general_ledgers_table.php`
- `database/migrations/2025_08_10_151225_add_account_dimension_in_accounts_table.php`

Blueprint: every ledger line carries `BranchID_FK` AND `CostID_FK`, tagged even when the feature is off. `journal_entries.branch_id` exists and is populated (branch dimension OK), but grep for `cost_center`/`CostCenter` across the entire repo returns zero hits — no table, no column, no UI. `accounts.account_dimension` is an `enum('service','payment','both')` used only to filter account pickers, not a reporting dimension.

**Recommendation:** Add a `cost_centers` table and nullable `cost_center_id` on `journal_entries` + `transactions` now, defaulted from branch/task type, so cost-center P&L can be enabled later with zero data migration.

---

## Finding 9 — Branch table carries no GL account pointers (cash/bank/branch/discount accounts)

**Status:** partial · **Severity:** medium

**Files:**
- `database/migrations/2025_03_17_094850_create_branches_table.php`
- `app/Models/Branch.php`
- `app/Http/Controllers/AccountingController.php`

Blueprint: `tblBranch` carries `CashAccID_FK`/`BankAccID_FK`/`BranchAccID_FK`/discount account so feeders post to the right branch accounts. `branches` has only identity fields (+`gds_office_id`); bank/fee routing is scattered onto `users.acc_bank_id` and `charges.acc_fee_bank_id`, and feeders that need "the branch bank account" walk the COA by name (`AccountingController.php:408,502-517`).

**Recommendation:** Add `cash_account_id`, `bank_account_id`, `branch_account_id`, `discount_account_id` FKs to `branches`, backfill from current name conventions, and read the pointers in feeders.

---

## Finding 10 — Open-item registry partial: payment_applications/credits cover AR only; no supplier-side application; overlapping mechanisms unreconciled

**Status:** partial · **Severity:** medium

**Files:**
- `database/migrations/2026_01_12_154855_create_payment_applications_table.php`
- `app/Services/PaymentApplicationService.php`
- `app/Models/InvoiceReceipt.php`

Blueprint's `tblAccIsApply` is a single apply registry. Found a genuine payment→invoice allocation table (`payment_applications` with amount 15,3, `applied_by`/`at`, FKs, soft deletes, credit_id link) plus credits and `invoice_receipt.is_used` — but coverage is client-side only; supplier payment→cost application doesn't exist; refund application is status columns; three overlapping mechanisms (`payment_applications`, `invoice_partials`, `invoice_receipt.is_used`) are maintained independently. The unmerged branch `fix/payment-voucher` attempts a PaymentVoucher + PaymentReconciliation system for this gap — stale (161 commits behind main), not production-ready.

**Recommendation:** Extend `payment_applications` into the single registry (supplier applications, credit/refund applications) and derive invoice status / `is_used` from it.

---

## Finding 11 — Travel feeder tables: AIR import staging present; BSP reconciliation, airline stock, airline class/incentive tables missing

**Status:** partial · **Severity:** medium

**Files:**
- `app/Services/AirFileParser.php`
- `database/migrations/2025_07_04_101603_create_file_uploads_table.php`
- `database/migrations/2025_10_29_164546_create_wallets_table.php`

Blueprint lists `tblMirHeader`/`Detail`/`Payment`, `tblBspReconciliation`, `tblAirlineStockHeader`/`Details`, `tblAirlineClass`/`Incentive`. GDS import staging exists (`file_uploads` + `AirFileParser` → tasks/task_flight_details; `incoming_media` for WhatsApp docs). BSP: zero case-insensitive matches in `app/` — nothing matches tickets to BSP settlement files; wallets + `RecordWalletBalance` only snapshot IATA EasyPay balances. Airline ticket-stock tables: none. Airline incentive/class commission config: none (commission logic is agent-side and hardcoded: `type_id in [2,3,4]`, default 0.15 in `InvoiceController::addJournalEntry`).

**Recommendation:** If BSP settlement is in scope, add `bsp_reconciliations` (period, airline, doc number, fare/tax/comm, matched task_id, status) fed by BSP files; add stock/incentive tables only if those operations are actually run.

---

## Finding 12 — Sub-ledgers: fixed assets dead code (model without table, no depreciation), no budget details, no recurring JV, no FX revaluation, no memo entries

**Status:** partial · **Severity:** medium

**Files:**
- `app/Models/Asset.php`
- `database/migrations/2024_08_27_064222_create_fa_type_table.php`
- `database/migrations/2025_10_14_170225_create_auto_billing_table.php`
- `database/migrations/2025_07_30_110917_create_exchange_rate_histories_table.php`

Blueprint lists `tblFADetails`/`FADepreciation`/`FASale`, `tblBudgetDetails`, `tblAccRecurringHeader`/`Detail`, `tblAccRateAdj*`, `tblMemoHeader`/`Detail`. Found: `Asset` model exists but no `create_assets_table` migration exists and `Asset::` is used nowhere in `app/` — dead code; no depreciation/disposal. Budget: only vestigial `accounts.budget_balance`/`variance` columns always written 0. Recurring: `auto_billings` implements recurring client invoicing but no generic recurring journals. FX: rates well tracked (`system_exchange_rates`, `exchange_rate_histories` with old/new rate + `changed_by`) but no revaluation posting ever occurs. Memo: nothing. Bank reconciliation flags on JEs (`reconciled` 0/1/2 + `reconciled_ref_id` via `BankPaymentController`) are the one present piece.

**Recommendation:** Delete or complete the fixed-asset module (table + depreciation postings); drop dead budget columns or add `budget_details`; add a period-end FX revaluation job posting to a configured gain/loss account.

---

## Finding 13 — Currency master lacks decimal-places metadata; monetary precision inconsistent (2 vs 3 vs 4 dp) across ledger tables

**Status:** buggy · **Severity:** medium

**Files:**
- `database/migrations/2025_03_17_101721_create_currencies_table.php`
- `database/migrations/2025_03_25_085713_add_columns_to_general_ledgers_table.php`
- `database/migrations/2025_10_12_111941_update_decimal_points_in_journal_entries_table.php`

Blueprint: `currencies` carry exchange rate and decimal places (KWD = 3 dp). `currencies` has no `decimal_places`; `journal_entries` debit/credit started `decimal(15,2)` and `exchange_rate` `decimal(10,2)` (unusable precision), `payment_applications` uses 15,3, `system_exchange_rates` 10,4 — a trail of five "update decimal point/precision" patch migrations (tasks, payments, invoices, journal_entries, system_exchange_rates) shows rounding drift was repeatedly discovered in production. Mixed precision on either side of a posting produces the sub-fils imbalances the 0.001 tolerance checks then absorb.

**Recommendation:** Standardize monetary columns to `decimal(18,3+)` in one wave; exchange rates to `decimal(18,6)`; add `decimal_places` to `currencies` and round only at document boundaries.

---

## Finding 14 — Generic master lookup table (tblMaster) not implemented; "master" table holds only a VERSION row

**Status:** partial · **Severity:** low

**Files:**
- `app/Models/Master.php`
- `database/seeders/MasterSeeder.php`
- `database/migrations/2025_03_18_034628_update_column_in_master_table.php`

Blueprint's polymorphic `tblMaster` (MasterType-discriminated lookups with `AccID_FK` GL links) is absent; the table named 'master' stores a single `VERSION=1.001` row consumed only by `VersionController`. Lookups are separate tables (`payment_methods`, `charges`, `agent_types`, `client_groups`, `countries`, `cities`, `currencies`, `account_types`, `task_rules`) — an acceptable alternative — but the GL-link discipline survives only on `charges` (`acc_bank_id`/`acc_fee_bank_id`/`acc_fee_id`); other lookups have no COA linkage, and travel-service types are a hardcoded `tasks.type` enum rather than a controls table.

**Recommendation:** Rename/remove the misleading `master` table; add nullable `account_id` FKs to lookups that feeders post by.

---

## Finding 15 — Employee / user-rights / session masters adequately covered by Laravel-native equivalents

**Status:** present_ok · **Severity:** low

**Files:**
- `database/migrations/2025_01_15_105449_create_permission_tables.php`
- `database/migrations/2025_09_22_120012_create_accountants_table.php`
- `database/migrations/2025_09_20_171019_add_company_id_to_roles_table.php`

`tblEmployee`/`tblUserRights`/`tblUserSession` map cleanly to `users`+`sessions` (stock migration), Spatie permission tables with permission groups and company-scoped roles, and the `accountants` table scoping accountant users to branches. Minor risk: dual role mechanisms (`users.role_id` enum + Spatie roles) can diverge.

**Recommendation:** No structural action; eventually collapse the `role_id` enum / Spatie duality.
