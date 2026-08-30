# 10 — Implementation Roadmap

A dependency-ordered plan to bring citytourv2's accounting up to the blueprint standard. Phases respect the blueprint's 5-layer model: **Chart of Accounts → Posting Engine → Feeders (AR/AP + travel) → Reporting**, with cross-cutting infrastructure sequenced by what it unblocks. Findings are referenced **by title** (bug titles from `08`, feature IDs from `09`).

Complexity key: **S** ≤ 2 dev-days · **M** ~1 week · **L** ~2–4 weeks · **XL** > 1 month.

> **Sequencing principle:** Phase 0 stops active harm (security + data-corruption) with surgical fixes and no architecture change. Phases 1–2 build the two keystones (`AccountService`, `PostingService`) that every later phase depends on. Do **not** reorder past a phase's stated dependencies.

---

## Phase 0 — Stop the bleeding (security + integrity hotfixes)  ·  Complexity: **S–M**
**Goal:** Eliminate the actively-exploitable and actively-corrupting defects without touching architecture. Ship first, independently.

**Build/fix:**
1. Patch the **SQL-injection vector** in `AccountingController::storeBankPayment` — validate `amount` as numeric and use a bound/parameterized update instead of `DB::raw("… {$request->amount}")`.
2. Add `Gate::authorize`/`->can()` to **every** report and ledger route; company-scope `SupplierController::ledgerByDateRange` and fix `journalEntriesByDate`'s unscoped fallback.
3. Hotfix the cross-tenant lookup: `->where('company_id', $companyId)` at `PaymentController::createInvoicePaymentCOA:6143` (full registry fix comes in Phase 3).
4. Fix `AccountingController::filterLedgers` null-crash (guard `$ledger->invoice` before `->agent->name`).
5. Add `whereNull('journal_entries.deleted_at')` to the raw JE totals queries in `accountsReconciliationReport`, `fetchPaymentsByDate`, `ReceiptVoucherController` (use `data-integrity-queries.sql` to find all raw JE queries).
6. Restore or remove the dead `dailySalesPdf`/`dailySalesPdfDownload` routes (500 error).
7. Gate `CoaController::dstry` behind a real `AccountPolicy::delete` and refuse deletion when children/entries exist; change `journal_entries.account_id` FK from `CASCADE` to `RESTRICT` (prevents silent hard-delete of ledger history).
8. Run `data-integrity-queries.sql` to **quantify existing drift** (unbalanced docs, orphaned lines, duplicate codes, one-sided documents) and record a baseline.

**Resolves:** BUG-C3 (SQL-injection portion), BUG-C5 (Report authorization only in menus), BUG-H6 (immediate hotfix), BUG-H8 (`filterLedgers` crash), BUG-H9 (soft-deleted totals), BUG-H3 (deletion authz + cascade), BUG-M4 (dead PDF routes).

---

## Phase 1 — Chart of Accounts hardening  ·  Complexity: **L**
**Goal:** One correct way to create and resolve accounts. Everything above the COA inherits its integrity from here.
**Depends on:** Phase 0.

**Build/fix:**
1. **`AccountService::create()`** implementing the blueprint's nine rules (parent mandatory, depth ≤ 6, `level = parent+1`, `root_id`/`account_type` **derived** not user-supplied, no mixed parents). Refactor all 10+ creation call sites onto it; add a model `creating`-hook backstop so imports/tinker can't bypass. Fix `importAccounts` phantom-root insertion.
2. **`AccountCodeGenerator`** (numeric max among siblings, padded to sibling width, id fallback). Migrate all ten ad-hoc code sites. Clean duplicate codes/names in `CoaSeeder`, backfill collisions, then add `UNIQUE(company_id, code)` and `UNIQUE(company_id, parent_id, name)`.
3. Derive `account_type` in the `creating` hook from the fixed root; backfill from `root_id`; make non-fillable; retire `account_type_id` + the redundant label typing.
4. Re-enable the **leaf-only** guard as a throwing observer; backfill `is_group` from `children()->exists()`.
5. **Party-master pointer FKs:** add `clients.receivable_account_id`, `supplier_companies.payable_account_id`/`cost_account_id`, `airlines.account_id`; auto-create per-party leaves in party `created` observers (reuse the working `AgentController` tree pattern). Introduce a **system-account/purpose-code registry** and begin replacing `Account::where('name',…)` resolution with it.
6. Add `SoftDeletes` + `created_by`/`updated_by` to `accounts`; fix `reference_id` misuse.

**Resolves:** MF-1 (AccountService), MF-2 (party FKs), MF-3 (unique constraints), BUG-C2 (leaf-only, enforcement half — TB half in Phase 2), BUG-H1 (code gen), BUG-H2 (account_type), BUG-H3 (SoftDeletes remainder), BUG-M1 (seeder defects); enables MF-4/5/6.

---

## Phase 2 — Central `PostingService` (the keystone)  ·  Complexity: **XL**
**Goal:** Exactly one writer of `journal_entries`, enforcing every posting invariant atomically. This is the single highest-leverage phase.
**Depends on:** Phase 1 (needs `AccountService`, purpose-code registry, leaf/`is_group` truth).

**Build/fix:**
1. **`PostingService::post(header, lines[])`** that: opens the DB transaction; resolves all accounts up front via the purpose-code registry (explicit `company_id`, safe in webhook/queue context); and **throws** on any violation of — `sum(debit)=sum(credit)` (tolerance 0.0005), leaf-only, debit-XOR-credit, non-negative amounts, frozen (`disabled`) account, FC-consistency (base = FC × rate). Never `Log::warning`-and-continue.
2. Refactor **all ~21** `JournalEntry::create` call sites onto it. Convert the one-sided branches (`createCreditPaymentCOA`, `addJournalEntry`, RV `account`/`import` modes, `handlePaidRefund`) to throw; make `createCreditPaymentCOA` re-throw so its transaction rolls back; wrap the RV `import` branch in a transaction.
3. **Reversal discipline:** all edits/deletes of posted documents post dated reversal documents (generalize the good `InvoiceController` reversal path, keyed on `doc_type` not description strings). Ban query-level `JournalEntry::where(…)->delete()` outside the service; refuse changes when any line has `reconciled != 0`.
4. Make `TrialBalanceService` fail loudly (or include non-leaf rows) when postings to group accounts are found — closes the other half of BUG-C2.
5. **Audit trail:** add `owen-it/laravel-auditing` (or mirror tables + observers) on `JournalEntry`/`Transaction`/`Account`; forbid updates to posted lines at the model layer.

**Resolves:** BUG-C1 (debit=credit / one-sided), BUG-C2 (TB half), BUG-H4 (edit/delete reversal discipline), BUG-H6 (registry-based resolution, full fix), BUG-H5 (opening bug is unblocked here, closed in Phase 4), BUG-M2 (top-up both-legs), MF-11 (iron rules), MF-13 (audit trail), "Credit-apply GL posting can silently fail".

---

## Phase 3 — Ledger header, balances, numbering, periods  ·  Complexity: **L–XL**
**Goal:** Turn the flat line table into a proper header+lines ledger with trustworthy balances, atomic numbering, and enforceable periods.
**Depends on:** Phase 2 (`PostingService` is the writer of all of this).

**Build/fix:**
1. Make `journal_entries.transaction_id` **NOT NULL**; add `doc_type`/`sub_type`/`doc_year`/`posted` to `transactions`; the `PostingService` writes header + balanced lines atomically.
2. **Balances:** either maintain `actual_balance` via an atomic `UPDATE … SET actual_balance = actual_balance + (debit-credit)` inside the JE transaction **and** an `account_monthly_balances` bucket table maintained in-transaction, plus a nightly drift check — or drop/derive `actual_balance`. Remove the always-0 `balance` snapshot column. Retire the six `Fix*` drift-repair commands.
3. **`SequenceService::next(docType, companyId, branchId, date)`** backed by `serial_schemas` keyed `(doc_type, company_id, branch_id, year)`, always `SELECT … FOR UPDATE` inside the caller's transaction. Scope invoice uniqueness to `(company_id, invoice_number)` (or embed a company/branch code in the mask); add unique indexes on `payments.voucher_number` and `transactions.reference_number`; generate RV numbers from the engine.
4. **`accounting_periods`** table (company_id, year, start/end, status); `PostingService` checks it for both new postings and reversals; extend `Lockable` coverage beyond invoices.
5. **System-parameters posting keys** (base_currency, control-account ids, parent-group ids, fx/retained-earnings ids, auto_lock); finish replacing every name-based control lookup.
6. **Cost-center dimension:** add `cost_centers` + nullable `cost_center_id` on `journal_entries`/`transactions` now (defaulted from branch/task type), so cost-center P&L can be switched on later with zero migration.

**Resolves:** BUG-C3 (balance rebuild), BUG-H10 (number series), BUG-M5 (precision — do the money-column widening here), MF-41 (ledger header), MF-12 (period locks), MF-42 (system params), MF-43 (cost center), MF-15 (line dimensions).

---

## Phase 4 — Opening balances & year-end close  ·  Complexity: **L**
**Goal:** One definition of "opening," and a real fiscal close that sweeps P&L to equity.
**Depends on:** Phase 3 (needs `accounting_periods`, `doc_type` for the OJV, retained-earnings param key).

**Build/fix:**
1. Convert `accounts.opening_balance`/`_date` into a posted, immutable **Opening Journal Voucher** (OJV `doc_type`), balanced against retained earnings.
2. Unify one opening-balance function used by TB, ledger drill-down, and the COA tree (kills the three-way divergence).
3. **`close-year` command:** validate year order; post a locked OJV with brought-forward lines for balance-sheet leaves; sweep net P&L into the per-year Retained Earnings account (`3400`, currently seeded-but-never-posted); advance the `accounting_periods` pointer; make the close irreversible (`AccountsClosingRollbackDisable`).

**Resolves:** BUG-H5 (opening definitions — full close), MF-17 (financial year + year-end close), "two conflicting opening-balance semantics."

---

## Phase 5 — AR/AP sub-ledger completion  ·  Complexity: **L**
**Goal:** Trustworthy per-party open items, real un-apply, and credit/debit-note instruments.
**Depends on:** Phase 1 (per-party accounts), Phase 2 (`PostingService`), Phase 3 (`SequenceService`).

**Build/fix:**
1. Unify the open-item engine: add `settled_amount` to `journal_entries` maintained **only** by an apply/release service with a never-negative guard; derive the `reconciled` flag from `settled_amount == amount`; add a guarded **standalone un-apply** that writes reversing rows (never deletes GL); extend to supplier-side application.
2. **Memo module** (CRN/DBN header+lines, type C/D, party, optional linked invoice, currency/rate) numbered via `SequenceService`, posting balanced receivable-vs-income; route refund CRNs (and future airline ADM/ACM) through it.
3. Post the receivable/revenue document **at invoice issue**, not at first payment (fixes lazy/understated AR); link task-cost and invoice documents via a shared header.
4. Fix the manual client top-up to Dr cash/bank (if not already done in Phase 2).

**Resolves:** MF-18 (open-item completion), MF-19 (memo module), MF-20 (post-at-issue), MF-22 (RV/PV defects), MF-47 (supplier-side application); enables MF-34 (ageing).

---

## Phase 6 — Reporting layer rebuild  ·  Complexity: **L–XL**
**Goal:** One canonical query; the standard financial statements; reports that tie out by construction.
**Depends on:** Phases 3–5 (header/doc-type, periods, per-party open items, year-end for equity).

**Build/fix:**
1. **`LedgerReportQuery`** service owning period/branch/company filters, opening-balance computation, sign rules, and roll-up (built on `TrialBalanceService`'s conventions). Rewrite P&L, ledger, and COA-dashboard totals as thin callers.
2. Fix P&L to use `transaction_date`; add branch/cost-center filter, month-wise and cumulative/comparative modes; replace `abs($total)` with `debit − credit`.
3. Build the **Balance Sheet** (A/L/Equity as-of a date + computed period profit as a virtual equity line).
4. Build **AR/AP ageing** (open = original − applied, grouped by party account, bucketed by `DATEDIFF` in SQL); **Cash Flow**; **Apply/Unapplied payments** report.
5. Fix the **Trial Balance** branch filter (`je.branch_id`), opening column, and CSV; add comparative-period support.

**Resolves:** BUG-C4 (P&L date column), BUG-M3 (sign rules / abs), MF-32 (canonical query), MF-33 (Balance Sheet), MF-34 (ageing), MF-35 (P&L variants), MF-37 (TB branch filter), MF-38 (Cash Flow), MF-39 (Apply report), MF-40 (statements).

---

## Phase 7 — Travel-industry specifics  ·  Complexity: **XL**
**Goal:** IATA/BSP-grade ticket, void, and reconciliation controls.
**Depends on:** Phase 1–2 (accounts/posting), Phase 5 (memo module for ADM/ACM), Phase 3 (airline dimension column).

**Build/fix:**
1. **Fix the void flow:** select reversal entries by `task_id`/`invoice_detail.task_id` (never description); fix the `supplier_date` typo; wrap in one transaction; tag reversals `reversal_of_transaction_id` for idempotency; block void when original entries are reconciled (supervisor override).
2. **Duplicate-invoice guard:** central `Task::isInvoiced()` enforced in `store`, `autoGenerateInvoice`, and the webhook paths; DB unique constraint on `invoice_details.task_id` for active invoices; auto-void duplicates to a house "void customer."
3. **BSP reconciliation:** `bsp_statement_lines` table + CSV importer; join on `tasks.ticket_number` for per-ticket matched / system-only / statement-only variance; make it a monthly ritual.
4. **Airline as accounting dimension:** resolve `substr(ticket_number,0,3) → airline` (seed real IATA codes); add airline linkage to tasks/JEs; per-airline payable.
5. Parse the AIR **FM commission** element (fare/tax/commission decomposition — prerequisite for accurate BSP payable); ticket **stock ranges + missing-ticket report**; **PCC/office allowlist** (`company_gds_offices` table, quarantine mismatches, remove hardcoded offices); ADM/ACM memos (via Phase 5); airline incentive/class tables; mod-7 check-digit validation; fix the refund clawback account classification.
6. **Travel analytics** (airline-wise sales, segment count, PNR/void-% reports — cheap wins on existing data).

**Resolves:** BUG-H7 (void flow), MF-24 (duplicate guard/auto-void), MF-25 (BSP), MF-27 (airline dim), MF-26/28/29/30/31 (stock, fare decomp, incentives, PCC, refund fixes), MF-36 (analytics).

---

## Phase 8 — FX revaluation & remaining sub-ledgers  ·  Complexity: **L–XL**
**Goal:** Close the multi-currency and long-tail sub-ledger gaps.
**Depends on:** Phase 3 (fx/param keys, precision), Phase 4 (period close for revaluation timing).

**Build/fix:**
1. **FX revaluation:** activate FX gain/loss accounts; period-end command posting `Σ(original × current rate) − Σ(booked base)` per FC account as a JV into `fx_rate_adjustments` (reversed at settlement); book realized diffs in the PV flow; effective-date the rate lookup (`valid_from` on `system_exchange_rates`).
2. **Bank/card statement import:** `bank_statement_lines` staging + CSV/OFX import; match on `auth_no`/`cheque_no`; stamp `reconciled_date`/`amount`; statement-vs-GL exception report.
3. **Branch GL pointers** (`cash_account_id`/`bank_account_id`/`branch_account_id`/`discount_account_id` on `branches`).
4. Long-tail: complete or delete **fixed assets** (table + depreciation postings); **budgeting** (`account_budgets` + variance report) or drop dead columns; **recurring journal templates**; security hardening (password policy, single-session, the two missing integrity checks); currency `decimal_places` (if deferred from Phase 3).

**Resolves:** MF-44 (FX revaluation), MF-45 (bank import), MF-46 (branch pointers), MF-48 (fixed assets/budget/recurring), MF-49 (security hardening), MF-14 (FX discipline), MF-16 (reconciliation fields), MF-50 (feeder tables).

---

## Roadmap at a glance

| Phase | Theme | Complexity | Key unlock |
|------|-------|:---------:|-----------|
| 0 | Security + integrity hotfixes | S–M | Stops active harm; no architecture change |
| 1 | COA hardening | L | `AccountService`, party FKs, unique keys |
| 2 | **Central `PostingService`** | XL | The keystone — balanced, leaf-only, atomic posting |
| 3 | Ledger header, balances, numbering, periods | L–XL | Header+doc-type, atomic balances/sequences, periods |
| 4 | Opening balances & year-end close | L | One opening definition; P&L→equity sweep |
| 5 | AR/AP sub-ledger completion | L | Open-item engine, memos, post-at-issue |
| 6 | Reporting rebuild | L–XL | `LedgerReportQuery`; Balance Sheet, ageing, cash flow |
| 7 | Travel-industry specifics | XL | Void fix, dup guard, BSP recon, airline dimension |
| 8 | FX revaluation & sub-ledgers | L–XL | FX reval, bank import, fixed assets/budget/recurring |

**Critical path:** 0 → 1 → 2 → 3 → 4 → 6 (statements) with 5, 7, 8 fanning out after their dependencies. Phases 0, and the cost-center column in Phase 3, and the "cheap-win" travel analytics in Phase 7, can be pulled forward opportunistically. A genuinely buildable, schema/code-level technical plan (concrete migrations + model/service skeletons) is a separate follow-up pass that consumes this roadmap.
