# Gap Audit 02 — Double-Entry Posting Engine

**Dimension:** Double-Entry Posting Engine (blueprint: `travel-accounting-system/references/02-posting-engine.md`)
**Codebase audited:** `C:\Users\User\city-tours-main` (citytourv2 mirror, branch `main`, HEAD `431f97e6`, 2026-04-06)
**Auditor:** Claude (read-only analysis), 2026-07-07
**Completeness estimate for this dimension: ~22%**

---

## Executive summary

citytourv2 has the *shape* of a header+detail ledger — a `transactions` table (header-ish) and a
`journal_entries` table (debit/credit lines) — but **it has no posting engine**. There is no single
service that writes a balanced document. Instead, `JournalEntry::create()` is called inline from
**21 different files** (controllers, console commands, one service), each duplicating account
lookup, amount math, and side selection, with **no shared validation of any blueprint invariant**:

- No Σdebit = Σcredit assertion anywhere at save time — and several paths **by design** write
  one-sided documents when a name-based account lookup fails (they log a warning and keep going).
- The leaf-account-only rule exists in the codebase **only as commented-out code**.
- No debit-XOR-credit rule, no non-negativity rule, no frozen-account rule.
- The eager running balance (`accounts.actual_balance`) is updated in a *minority* of posting
  paths, with non-atomic read-modify-write, inconsistent sign conventions, and no reversal on
  edit/delete — it is effectively abandoned; reports recompute everything from raw lines.
- There is no monthly-bucket aggregate table at all.
- No audit-log mirror tables; no Posted flag; no opening journal; period "locks" exist but do not
  block new postings into a locked period.

The strongest assets on the positive side: `TrialBalanceService` (correct recompute-from-lines
trial balance with leaf-only aggregation) and its `findUnbalancedTransactions()` diagnostic, one
invoice-edit path that does proper journal-style reversal, soft deletes on the ledger, and a
record-level lock system. The fleet of one-off repair commands (`FixInvoiceCoa`, `FixOldProfit`,
`FixPaymentGatewayCOA`, `FixPaymentLinkCOA`, `FixCreditInvoiceCOA`, `FixGatewayCharges`) is itself
evidence that the missing engine invariants have repeatedly corrupted the books in production.

---

## Finding 1 — Document model exists but the header is optional and weakly typed

**Status: partial | Severity: high**

**Blueprint (§1, §2).** Two tables, one-to-many: `tblAccHeader` (one row per voucher: `DocNo`,
`DocType`, `SubType`, `DocDt`, `Narration`, `Posted`, `BranchID_FK`, `CostCenter_FK`, `DocYear`,
`RefNo/RefCode/RefType`) and `tblAccDetail` (the lines). The header carries **no amount**; a
document is valid only when its lines net to zero.

**What the code has.**
- Header ≈ `transactions` table — `database/migrations/2024_08_23_022618_create_transactions_table.php`;
  model `app/Models/Transaction.php`. Columns: `entity_id/entity_type`, `transaction_type`
  (`'debit'`/`'credit'` — meaningless for a multi-line document), **`amount` (a `float` column —
  floating-point money)**, `reference_type` (enum, later widened), `reference_number`,
  `invoice_id`, `payment_id`, `transaction_date`, `branch_id`, `company_id`.
- Detail ≈ `journal_entries` (born `general_ledgers`,
  `2025_03_17_103934_create_general_ledgers_table.php`, renamed by
  `2025_03_28_145526_rename_table_general_ledgers_to_journal_entries.php`); model
  `app/Models/JournalEntry.php`. Line columns include `account_id`, `debit`, `credit`, `balance`,
  `voucher_number`, `type`, `currency`, `exchange_rate`, `amount`, `cheque_no/cheque_date/
  bank_info/auth_no`, `reconciled/reconciled_ref_id`, `original_currency/original_amount`,
  `task_id`, `invoice_id`, `invoice_detail_id`, `branch_id`, `is_locked`.

**Gaps.**
1. `journal_entries.transaction_id` is **nullable** — a line can exist without a document header.
   (FKs do exist: `2025_03_17_161405_update_foreign_in_general_ledgers_table.php`, but all with
   `onDelete('cascade')` — see Finding 8.)
2. No `DocType`/`SubType` taxonomy. Document identity is smeared across `reference_type` (an enum
   originally just `['Invoice','Payment']`), free-text `description` matching, and per-module
   prefixes. Reversal logic literally string-matches descriptions
   (`str_contains($entry->description, 'Invoice created for (Assets)')`,
   `app/Http/Controllers/InvoiceController.php:5127-5135`) — renaming a description breaks
   accounting behavior.
3. Header carries `amount` (denormalized, and in several flows set to `0.00` or a partial figure —
   e.g. the reversal header at `InvoiceController.php:4888` gets `amount => 0.00`), so it cannot be
   trusted and contradicts "the amount lives entirely in the lines".
4. No `DocYear`, no `CostCenter`, no `Posted` (see Findings 9, 10).
5. `journal_entries.balance` is semantically dead: it is variously set to the posted amount
   (`AccountingController.php:619`), a stale `actual_balance` snapshot
   (`PaymentApplicationService.php:722,757`), `$invoiceDetail->task_price - $finalPaidAmount`
   (`PaymentController.php:6199`), `$clientAccount->balance ?? 0` — a nonexistent attribute, i.e.
   always 0 (`InvoiceController.php:1508`) — or plain `0`. Nothing reads it for accounting.

**Recommendation.** Make `transaction_id` NOT NULL; introduce a `doc_type` + `sub_type` enum on
`transactions` and populate it from every posting path; add a generated, per-company, per-type
document number (see Finding 12); drop or clearly deprecate `journal_entries.balance` and
`transactions.amount`, or make them derived-only; migrate `transactions.amount` off `float`.

---

## Finding 2 — No Σdebit = Σcredit enforcement; engine can and does write one-sided documents

**Status: buggy | Severity: critical**

**Blueprint (§1).** "A document is valid only when Σ(Debit) = Σ(Credit) across its detail lines…
**add a header-level Σdebit = Σcredit assertion at save/post time** — skipping it is how books
drift."

**What the code does.** There is **no balance assertion in any posting path**. Grep across
`app/` finds zero save-time comparisons of document debit vs credit. Worse, several paths are
structurally able to post half a document:

- `app/Services/PaymentApplicationService.php::createCreditPaymentCOA()` (lines 645-786): if the
  name-chain lookup `Liabilities → Advances → Client → Payment Gateway` fails, it logs
  `'[CREDIT PAYMENT COA] Liability account NOT FOUND'` **and still posts the credit side**
  (lines 702-732 vs 744-766). Either side can be skipped independently. The method is also wrapped
  in its own try/catch that swallows exceptions and returns `null`, so the caller's DB transaction
  does not roll back.
- `app/Http/Controllers/InvoiceController.php::addJournalEntry()` (line 1374): ENTRY 1 (debit
  receivable) is inside `if ($clientAccount) { … }` (line 1494) — if the account is missing the
  debit is silently skipped, then ENTRY 2 (credit income) posts unconditionally (line 1563).
- `app/Http/Controllers/ReceiptVoucherController.php` "import" branch (lines 1111-1219): posts
  `$journalEntry1` (debit cash, line 1151), then if the `Advances → Client → Cash` account is not
  found **returns an error array with the one-sided debit already persisted** — this branch has no
  `DB::beginTransaction()`/rollback at all (the cash branch above it does, line ~962/1101).
- `app/Http/Controllers/ReceiptVoucherController.php::storeJournalEntryEntries()` (line 654):
  inserts user-submitted rows with independent `debit` and `credit` values, never summing them.
- `app/Http/Controllers/PaymentController.php:6257` computes
  `'balanced' => ($finalPaidAmount == ($netAmount + $accountingFee)) ? 'YES' : 'NO'` — **for a log
  line only**; a "NO" does not stop the commit.

**Partial mitigation (detection, not prevention).**
`app/Services/TrialBalanceService.php::findUnbalancedTransactions()` (lines 191-221) finds
transactions whose lines are off by > 0.001 and surfaces them on the Trial Balance screen and PDF
(`app/Http/Controllers/ReportController.php:4026, 4080, 4176`). This is a good diagnostic but it
runs after the fact, only over documents that *have* a `transaction_id`, and repairs nothing.

**Recommendation.** Create one `PostingService::post(header, lines[])` that (a) opens the DB
transaction, (b) asserts `abs(Σdebit − Σcredit) < 0.0005` before commit, (c) throws — never
warns — on any missing account. Refactor all 21 call sites onto it. Until then, add an
`Observer`/`saved` hook on `Transaction` commit or a scheduled job that pages someone when
`findUnbalancedTransactions()` is non-empty, and treat the existing warnings in
`createCreditPaymentCOA` as exceptions.

---

## Finding 3 — Iron rule "debit XOR credit, non-negative" not enforced

**Status: missing | Severity: high**

**Blueprint (§2).** A line is one-sided: debit XOR credit, never both; amounts are non-negative —
you post the opposite side instead of a negative amount.

**What the code does.** Nothing enforces either rule:
- No DB CHECK constraint on `journal_entries` (`debit >= 0`, `credit >= 0`,
  `(debit = 0) <> (credit = 0)`).
- No model-level validation in `app/Models/JournalEntry.php` (the model's `boot()` is entirely
  commented out, lines 57-78).
- The manual receipt-voucher form posts both columns straight from the request:
  `'items.*.debit' => 'required|numeric', 'items.*.credit' => 'required|numeric'`
  (`app/Http/Controllers/ReceiptVoucherController.php:587-588`) — `numeric` admits negatives and
  admits both sides non-zero on one row.
- `AccountingController::storePayableDetail()` at least validates `'amount' =>
  'required|numeric|min:0.001'` (line 557) and splits sides itself — the only path with a floor.

By convention the hard-coded paths do write one-sided rows (`'debit' => $x, 'credit' => 0`), so
today's data is *mostly* clean, but the invariant lives in copy-paste discipline, not in the
system.

**Recommendation.** Add MySQL CHECK constraints (MySQL 8 enforces them) in one migration:
`CHECK (debit >= 0 AND credit >= 0 AND (debit = 0 OR credit = 0))`, plus a `creating` model
observer that throws. Optionally add the denormalized `dc` char('D'/'C') column from the blueprint
for fast filtering (today code filters with `where('credit','!=',0)` — e.g.
`ReceiptVoucherController.php:735`).

---

## Finding 4 — Leaf-account-only rule exists only as commented-out code; group postings vanish from the trial balance

**Status: buggy | Severity: critical**

**Blueprint (§2, §3 rule 5).** A line points at a **leaf** account only; reject posting to a
group/header account.

**What the code does.**
- The one implementation that ever existed is **commented out**:
  `app/Models/JournalEntry.php:57-78` (`static::creating(... if ($account->children()->exists())
  throw ...)` — all inside `//`).
- `accounts.is_group` exists (model `app/Models/Account.php:36`) but is **never checked before any
  of the 21 posting call sites** (verified by grepping `is_group` / `children()->exists` across
  `app/` — the only runtime check is a log field in `TaskController.php:1967`).
- Posting to parents actually happens by design:
  `TaskController::processIssuedTask()` falls back to `$supplierPayable->id` — the supplier's
  *parent* payable account — whenever there is no issued-by/currency child
  (`app/Http/Controllers/TaskController.php:2118-2128`). Hotel and flight paths create child
  accounts under it, so the parent both has children *and* receives direct postings.

**Why this is worse here than in the blueprint's world:** `TrialBalanceService` aggregates **leaf
accounts only** (`whereRaw('NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id =
a.id)')`, `app/Services/TrialBalanceService.php:96-98` and again at 147-149). Any journal entry
posted to a group account is **excluded from the trial balance and from opening balances**, so the
TB shows Σdebit ≠ Σcredit (or silently loses both sides) even though the raw ledger balances. This
is a live report-corruption mechanism, not a theoretical one.

**Recommendation.** Re-enable the leaf check as a model observer (throw, don't log), backfill: find
`journal_entries` whose account has children and re-point them to an explicit leaf (e.g. a
"General" child), and change TB to either include non-leaf postings or fail loudly when it finds
one.

---

## Finding 5 — Line validations (§3) almost entirely absent

**Status: missing | Severity: high**

**Blueprint (§3).** Six validations on every line: (1) round base amounts to base-currency
decimals and FC amounts to foreign-currency decimals; (2) reject negatives; (3) if line currency =
system currency then FC total must equal base total; (4) reject frozen accounts; (5) reject group
accounts; (6) header must exist before lines.

Point-by-point against the code:

1. **Rounding** — partial. Columns were widened to KWD's 3 decimals only in Oct 2025
   (`2025_10_12_111941_update_decimal_points_in_journal_entries_table.php`: `debit/credit/amount`
   → `decimal(15,3)`, `exchange_rate` → `decimal(15,6)`); everything before that was silently
   truncated to 2dp. Rounding in code is ad-hoc `round($x, 3)` scattered through
   `InvoiceController` / `PaymentApplicationService`; there is no per-currency decimals table
   lookup — foreign amounts are also stored at 3dp regardless of the currency's real precision.
2. **Negative rejection** — missing (Finding 3).
3. **Base-currency FC consistency** — missing. Nothing compares `amount`/`original_amount` against
   `debit+credit` when `currency == 'KWD'`. Lines routinely carry `currency => $task->currency`
   with a KWD figure in `debit` (`InvoiceController.php:1506-1513`), making the stored currency
   label actively misleading.
4. **Frozen account** — missing. `accounts.disabled` exists (set via COA import,
   `CoaController.php:790-819`) but is never consulted at posting time anywhere in `app/`.
5. **Group account** — missing (Finding 4).
6. **Header-exists** — partially structural: every observed path creates the `Transaction` first,
   but `transaction_id` is nullable so nothing *requires* it (Finding 1).

**Recommendation.** All six checks belong in the single `PostingService` (Finding 2) plus DB
constraints where possible (`NOT NULL transaction_id`, CHECKs from Finding 3). Add a
`currencies.decimal_places` lookup for rounding both sides.

---

## Finding 6 — Eager balance maintenance is fragmentary, non-atomic, sign-inconsistent, and never reversed

**Status: buggy | Severity: high**

**Blueprint (§4).** Every line insert updates, inside the same DB transaction: (a) the account's
live `OutstandingAmt += (Debit − Credit)`, and (b) a per-account/month/branch/cost bucket
(`tblAccMonthlyBalance`). These make reports fast; the price is they must stay in lock-step (§5).

**What the code does.**
- **(a) Running balance:** `accounts.actual_balance` exists but is updated in only a handful of
  the posting paths, e.g.:
  - `PaymentController.php:6224,6247` — `$account->actual_balance += $netAmount; $account->save();`
    (non-atomic read-modify-write on an Eloquent model, no `lockForUpdate`, race-prone under
    concurrent gateway callbacks);
  - `ReceiptVoucherController.php:1169-1170, 1211-1212` (cash/import receipt paths);
  - `ClientController.php:1139-1211`, `CreditController.php:205-242`,
    `CheckMyFatoorahPayments.php:163-170`;
  - `AccountingController.php:930-948` uses `DB::raw("actual_balance ± {$request->amount}")` —
    atomic, but **string-interpolates a request value into SQL** (injection surface even with
    validation upstream).
  Meanwhile the **highest-volume paths update nothing**: invoice generation
  (`InvoiceController::addJournalEntry`), task issue/refund/void (`TaskController::
  processIssuedTask/processRefundTask`), payable/receivable manual entries
  (`AccountingController::storePayableDetail` — creates JEs, touches no balance), credit
  application (`PaymentApplicationService`).
- **Sign convention is inconsistent** where it *is* updated: asset accounts get `+= debit`
  (`ReceiptVoucherController.php:1169`), liability accounts get `+= credit`
  (`ReceiptVoucherController.php:1211`) — i.e. "natural balance" semantics — while
  `CheckMyFatoorahPayments.php:170` *subtracts* from a gateway asset, and the blueprint's uniform
  `+(debit − credit)` is used nowhere. `actual_balance` therefore has no coherent meaning across
  accounts.
- **(b) Monthly buckets: missing entirely.** No table like `account_monthly_balances` exists in
  `database/migrations/`; nothing equivalent by any name (searched `monthly`, `bucket`,
  `balance` across migrations and models).
- Consequence: every report recomputes from raw `journal_entries`
  (`TrialBalanceService::getAccountBalances/getOpeningBalances` — full-table SUMs with date
  filters; `CoaController.php:84+` similar; `JournalEntryController::getJournalEntries` computes
  running balances in PHP per request). Works today; will degrade linearly with ledger growth,
  which is exactly what the blueprint's aggregates exist to prevent.

**Recommendation.** Either (a) finish the aggregate: maintain `actual_balance` with uniform
`+(debit − credit)` semantics via a single observer using atomic
`UPDATE accounts SET actual_balance = actual_balance + ?`, add an
`account_monthly_balances(account_id, month, branch_id, debit, credit)` table maintained in the
same transaction, and a nightly drift check recomputing both from raw lines; or (b) formally
abandon `actual_balance` (drop/rename it) so no code path half-trusts it. The current halfway
state is the worst option.

---

## Finding 7 — Edit/delete: no reverse-then-apply discipline; two contradictory patterns coexist

**Status: buggy | Severity: high**

**Blueprint (§5).** On any change: reverse old amounts out of the aggregates, then apply new ones.
Deleting a document loops its lines and reverses each. Applied/reconciled lines are protected from
normal edit/delete.

**What the code does — two coexisting regimes:**

1. **Journal-style reversal (good, one path):**
   `InvoiceController` invoice-amount edit (lines 4865-5163) creates a reversal `Transaction`
   ("Invoice reversal for: …") whose lines swap debit/credit of the originals (lines 4891-4913),
   then posts corrected entries. History is preserved. But even here: the reversal targets only
   `$invoice->transactions()->orderBy('id','desc')->first()` — the **latest** transaction — so a
   multi-transaction invoice (generation + payment + charges) is only partially reversed, and the
   re-creation logic identifies line roles by `str_contains($entry->description, …)` (lines
   4999-5001, 5127-5135) — fragile string coupling.

2. **Bulk delete-and-reinsert (dominant, destructive):** 16 call sites hard-`->delete()` ledger
   lines by query, e.g. `InvoiceController.php:3843-3845` (invoice `update()` deletes
   `InvoiceDetail`, `Transaction`, `JournalEntry` wholesale, then re-inserts),
   `ReceiptVoucherController.php:628` (voucher edit), `BankPaymentController.php:531`,
   `RefundController.php:1276`, `TaskController.php:2428,2466`
   (`revertFinancialsForTask/ForVoid`), `MobileController.php:668,780`,
   `RemoveConfirmedTaskTransactions.php:170`. These are soft deletes (model uses `SoftDeletes`,
   `deleted_at` added by `2025_07_21_164914_add_soft_deletes_to_task_related_tables.php`) so rows
   survive, but:
   - **No aggregate reversal**: paths that had incremented `actual_balance` are not decremented on
     delete → permanent drift (see Finding 6).
   - **No protection of reconciled/applied lines**: `InvoiceController.php:3845` deletes every JE
     for the invoice including rows with `reconciled = 1`; nothing anywhere checks `reconciled`
     before delete except the dedicated un-reconcile flows
     (`BankPaymentController.php:730-766`, `ReceiptVoucherController.php:796-831`).
   - `TaskController::revertFinancialsForTask` (2416-2437) selects lines to delete by
     `transaction.description LIKE '%{reference}%'` — description-based deletion of accounting
     records.

**Integrity check:** partial — `findUnbalancedTransactions()` (Finding 2) detects unbalanced
documents, but there is no job recomputing aggregates vs raw lines (nothing to compare, per
Finding 6), and the six `Fix*` console commands (`app/Console/Commands/FixInvoiceCoa.php`,
`FixOldProfit.php`, `FixPaymentGatewayCOA.php`, `FixPaymentLinkCOA.php`, `FixCreditInvoiceCOA.php`,
`FixGatewayCharges.php`) are manual, after-the-fact repairs — evidence of recurring drift.

**Cross-reference:** the unmerged branch **`fix/payment-voucher`** attempts a
PaymentVoucher + PaymentReconciliation system including a data migration rewriting existing
payment/JE data — i.e. upstream recognized this gap — but it is 161 commits behind main and not
production-ready.

**Recommendation.** Standardize on the reversal pattern for *all* edits/deletes of posted
documents (never delete posted lines); add a guard that refuses reversal/delete when any line has
`reconciled != 0`; make the reversal target *all* transactions of the document, keyed by a real
`doc_type`, not description text.

---

## Finding 8 — Audit trail: no log mirrors, no triggers; cascade FKs can even hard-delete history

**Status: missing | Severity: high**

**Blueprint (§6).** Every base table has a `*Log` mirror populated by an insert/update/delete
trigger; the header log's `LogID` gives version order; the account log doubles as running-balance
history.

**What the code has.**
- No `*_logs` mirror of `transactions`, `journal_entries`, or `accounts`; no DB triggers anywhere
  in `database/migrations/`; no auditing package in `composer.json` (searched `audit`,
  `activitylog`, `revision` — nothing); no model observers registered
  (`app/Observers/` doesn't exist; no `observe(` in `app/Providers/`).
- What exists instead: `SoftDeletes` on `JournalEntry`/`Transaction` (deleted rows survive with
  `deleted_at` — no record of *who* deleted), a `system_logs` table used for task status changes
  only (`TaskController.php:2813+`, `LoggingHelper.php`), `is_locked/locked_by/locked_at`
  (Finding 9), `ExchangeRateHistory` for FX-rate changes (a genuine, if narrow, audit log), and
  copious `Log::info()` to files — not queryable history.
- **In-place mutation of posted lines happens with no trace**: e.g.
  `app/Console/Commands/UpdateTransactionDate.php:124` rewrites `transaction_date` on existing
  journal entries; the invoice `update()` path re-writes documents wholesale (Finding 7).
- **Aggravator:** the FK set on `journal_entries` uses `onDelete('cascade')` for
  `transaction_id`, `invoice_id`, `invoice_detail_id`, `account_id`, `company_id`, `branch_id`
  (`2025_03_17_161405_update_foreign_in_general_ledgers_table.php`). A *hard* delete of an
  invoice, transaction, or account **cascades a hard delete of ledger lines at the DB layer,
  bypassing soft deletes entirely**. `InvoiceController.php:3844` hard-deleting transactions
  therefore also hard-deletes any JEs still pointing at them via the FK (the explicit JE
  soft-delete on the next line only catches rows matched by `invoice_id`).

**Recommendation.** Minimum viable: install `owen-it/laravel-auditing` (or a `journal_entry_logs`
mirror + observer) on `JournalEntry`, `Transaction`, `Account`; change the cascade FKs to
`restrict`; forbid updates to posted lines at the model layer (only reversal documents may change
balances).

---

## Finding 9 — Posted flag and period control: drafts don't exist; locks don't stop new postings

**Status: partial | Severity: high**

**Blueprint (§7).** Drafts (`Posted = 0`) never hit reports; before posting, check the financial
year isn't closed and the account's date window allows the posting date.

**What the code has.**
- **Posted flag: missing.** No `posted`/`is_posted`/draft state on `transactions` or
  `journal_entries` (grep confirms); every created line is immediately live in every report. The
  various "pending approval" flows (receipt vouchers with `status`) gate *their own* record, but
  the JEs they create are live the moment they exist.
- **Period locks: partial and porous.** A real system exists —
  `app/Http/Traits/Lockable.php` + `app/Http/Controllers/LockManagementController.php`
  (`lockByPeriod`, `lockByMonth`, `unlockByMonth` with mandatory reason, cascade from
  Invoice→Transaction→JournalEntry, `2026_02_09_133957_add_lock_columns_to_financials_table.php`).
  But:
  - Enforcement is opt-in per call site and today only `InvoiceController` checks it
    (`InvoiceController.php:572, 6441, 6461, 6481, 6529` — grep for `canModify|isLocked` finds no
    checks in `PaymentController`, `TaskController`, `ReceiptVoucherController`,
    `BankPaymentController`, `AccountingController`, or any console command). A locked month's
    JEs can still be bulk-deleted by `RefundController.php:1276` or edited by
    `UpdateTransactionDate.php`.
  - Locking flags **existing rows**; nothing prevents posting a *new* document dated into a locked
    month — the blueprint's actual period-close semantics. There is no financial-year entity, no
    year-close status, no per-account date windows (`TransLockdt`-style).
- **Cross-reference:** none of the unmerged branches address period close.

**Recommendation.** Add a `posted_at`/`status` on `transactions` and filter every report on it;
add an `accounting_periods (company_id, month, status)` table checked inside the future
`PostingService` for **both** new postings and reversals; wire `canModify()` into every mutating
path (a global observer on `JournalEntry::updating/deleting` would catch all of them at once).

---

## Finding 10 — Opening balances: mutable column instead of an opening journal; two reports disagree on what "opening" means

**Status: buggy | Severity: high**

**Blueprint (§7).** Year-end produces one immutable OJV document carrying balance-sheet accounts
forward, collapsing the year's P&L into retained earnings; reports treat OJV as opening, never as
movement; income/expense reset each year.

**What the code has.**
- No year-end close of any kind: grep for `retained`, `year.?end`, `closing entry`, `OJV` across
  `app/` finds nothing but a date-range helper. Income/expense accounts accumulate forever.
- Opening balances are a **mutable pair of columns** on `accounts`
  (`opening_balance`, `opening_balance_date`;
  `2026_02_08_100000_add_opening_balance_to_accounts_table.php`), editable at any time via
  `CoaController::saveOpeningBalances()` (lines 1210-1265) with **no check that the entered
  openings balance to zero**, no document, no audit trail (Finding 8).
- **The two ledger views define "opening" differently and will disagree:**
  - `TrialBalanceService::getOpeningBalances()` (lines 131-157) computes opening as
    Σ of journal entries **before the period start** — it **ignores `accounts.opening_balance`
    completely**, so manually-entered openings never reach the trial balance.
  - `JournalEntryController::show()` (lines 25-43) starts the account ledger's running balance at
    `accounts.opening_balance` and then adds **only in-period entries** — ignoring all pre-period
    journal entries.
  An account with both a manual opening and pre-period activity shows a different balance on the
  TB than on its own ledger card. This is a report-integrity bug, not just a missing feature.
- **Cross-reference:** the unmerged branch **`opening-balance`** exists upstream (by name),
  suggesting an attempted rework; not merged, not analyzed here.

**Recommendation.** Represent openings as a real posted document (JV with `sub_type='OJV'`, dated
day-0), balanced by an equity suspense/retained-earnings line, and delete the mutable columns.
Both reports then agree by construction (opening = Σ lines < period start). Build year-close as a
command that generates next year's OJV.

---

## Finding 11 — Multi-currency: columns exist, discipline doesn't

**Status: partial | Severity: medium**

**Blueprint (§8).** Every line stores the FC original (`FCDebit/FCCredit`, currency, rate) and the
converted base amount; base = FC × rate rounded to base decimals; GL balances in base; rates are
effective-dated history, never one mutable number.

**What the code has.**
- Line columns: `currency`, `exchange_rate` (decimal 15,6), `amount`, and
  `original_currency`/`original_amount`
  (`2025_08_02_170959_add_original_currency_to_journal_entries_table.php`). Base currency is
  KWD by convention (hard-coded defaults throughout). Debits/credits are indeed in base.
- Good bits: hotel supplier payables get **currency-specific child accounts** (e.g.
  "Supplier (USD)") with the converted KWD amount in `credit` and the FC original in
  `original_currency/original_amount`
  (`TaskController::processIssuedTask`, lines 2077-2146; `getOrCreateCurrencySpecificAccount`,
  1692-1708). `CoaController` (lines 99-105, 291-300) shows per-currency original totals.
  `ExchangeRateHistory` records every rate change (old/new/method/changed_by).
- Gaps:
  1. **No enforcement that base = FC × rate.** `exchange_rate` defaults to `0.00` and most paths
     never set it; nothing validates blueprint rule §3-3 (FC total = base total when currency is
     base).
  2. Only *one side* of the task document carries FC data — the expense line
     (`TaskController.php:2049-2063`) has no `original_currency`, so the FC picture of the
     document is incomplete.
  3. `currency` is set to the *task's* currency while `debit/credit` hold KWD
     (`InvoiceController.php:1511`) — the label lies about the amount next to it.
  4. Rates: `SystemExchangeRate` is one mutable row per pair (model setter rounds to 6dp);
     history is a *change log*, not an effective-dated lookup — you cannot ask "what was the rate
     on the 3rd" and repost faithfully. `CurrencyExchange`/`SupplierExchangeRate` similar.
  5. No period-end FX revaluation of open FC balances (no JV, no code).

**Recommendation.** In `PostingService`: require (`original_amount`, `original_currency`,
`exchange_rate`) as a triple or none; compute base = round(FC × rate, 3) server-side; store
`fc_debit`/`fc_credit` split rather than one signed `original_amount`. Make rate lookups
effective-dated (add `valid_from` to `system_exchange_rates` and query ≤ posting date). FX
revaluation can wait until FC balances become material.

---

## Finding 12 — Document numbering: read-max-plus-one and user-supplied voucher numbers

**Status: partial | Severity: medium**

**Blueprint (§1).** Header has a generated `DocNo` (per type/branch/year in the real system).

**What the code has.**
- `Sequence` / `InvoiceSequence` / `RefundSequence` models exist and invoices use them.
- But the accounting-entry paths generate references by scanning the last row:
  `AccountingController::storePayableDetail()` (lines 576-587) does
  `Transaction::where('reference_number','like','PY-%')->orderByDesc('id')->first()` then `+1` —
  race-prone under concurrency, no unique index protecting it.
- Receipt vouchers accept the number **from the client request**:
  `'receiptvoucherref' => 'required|string'` (`ReceiptVoucherController.php:171, 559`) and write
  it into `transactions.reference_number` and `journal_entries.voucher_number` — duplicates and
  arbitrary strings are possible.
- `GenerateMissingReceiptVouchers.php` exists in Console/Commands — evidence numbers have gone
  missing before.

**Recommendation.** One `document_sequences (company_id, doc_type, year, next_no)` table read with
`lockForUpdate()` inside the posting transaction; unique index on
`(company_id, doc_type, doc_no)`; never accept a document number from request input.

---

## Finding 13 — Reporting dimensions on the line: branch OK, cost center absent, agent dimension silently dropped

**Status: partial | Severity: medium**

**Blueprint (§2).** Lines carry `BranchID_FK` and `CostID_FK` as reporting dimensions.

**What the code has.**
- `branch_id` exists on every line (sometimes defaulted as `$request->branch_id ?? 0` —
  `ReceiptVoucherController.php:666` — writing branch `0`).
- No cost-center concept anywhere (`accounts.account_dimension` was added
  `2025_08_10_151225_add_account_dimension_in_accounts_table.php` but is not a line dimension).
- **Silently-dropped writes:** many posting calls pass `'agent_id' => …` and
  `'invoice_partial_id' => …` to `JournalEntry::create()`
  (`InvoiceController.php:1501`, `PaymentApplicationService.php:716-717`,
  `TaskController.php:2055,2390` etc.), but **`journal_entries` has neither column and neither is
  fillable** (checked all migrations touching `general_ledgers`/`journal_entries`; checked
  `$fillable` in `app/Models/JournalEntry.php`). Laravel mass-assignment silently discards them —
  the developers believe they are storing an agent dimension per line; they are not. Agent
  attribution is instead reconstructed via `task_id` (`JournalEntry::agent()` hasOneThrough Task).

**Recommendation.** Either add the columns (and backfill from `task_id`) or delete the dead keys
from every create call so the code stops lying to its readers. Decide whether agent is the
cost-center analog for this business (it probably is) and index it.

---

## Finding 14 — Account resolution by display name, unscoped in webhook context (cross-tenant risk)

**Status: buggy | Severity: high**

**Blueprint (implicit in §2/§3).** Lines point at a specific account ID; the engine never guesses.

**What the code does.** Nearly every posting path resolves accounts by **display-name string**
(`'Accounts Receivable'`, `'Clients'`, `'Liabilities'`, `'Advances'`, `'Payment Gateway'`,
`'Gateway Fee Recovery'`, supplier names, `like '%Direct Income%'` …). Renaming an account in the
COA UI silently reroutes or breaks postings (see Findings 2's skip-a-side behavior). Two spots are
worse:

- `app/Http/Controllers/PaymentController.php:6143`:
  `$receivableAccount = Account::where('name', 'Clients')->first();` — **no `company_id` filter**.
  `Account` uses the `BelongsToCompany` global scope (`app/Traits/BelongsToCompany.php`), but that
  scope **only applies when `Auth::check()` is true** — and this code runs in payment-gateway
  callback context where there is typically no authenticated user. First company's "Clients"
  account in the table wins: cross-tenant posting.
- `app/Http/Controllers/JournalEntryController.php:48-52`: root accounts fetched by bare
  `Account::where('name','Assets')->first()` etc. for the ledger running-balance math — same
  unscoped-when-unauthenticated hazard, and wrong company when an admin views another company.

**Recommendation.** Introduce a `system_account` registry (company_id + purpose-code → account_id)
seeded per company (`SeedCompanyCoaCommand` already exists as a natural home). Resolve by purpose
code, always scoped by explicit `company_id`, never by name and never relying on the auth-based
global scope in queue/webhook contexts.

---

## Finding 15 — What's genuinely good (present_ok)

**Status: present_ok | Severity: low**

Worth preserving through any refactor:

1. **`TrialBalanceService`** (`app/Services/TrialBalanceService.php`) — clean recompute-from-lines
   TB with soft-delete awareness, opening/movement/closing split, normal-balance handling
   (`getNormalBalance`, line 226), root grouping, and an `is_balanced` flag with 0.001 tolerance.
   Documented in `docs/trial-balance-technical.md`.
2. **`findUnbalancedTransactions()`** — the seed of the blueprint's integrity check, already
   surfaced in UI + PDF (`ReportController.php:4026, 4080, 4176`).
3. **Reversal-style invoice correction** (`InvoiceController.php:4865-4913`) — the correct §5
   pattern exists in the codebase and can be generalized.
4. **The lock framework** (`Lockable` trait + `LockManagementController`) — cascade design is
   sound; it needs enforcement coverage, not redesign.
5. **Soft deletes** on `journal_entries`/`transactions` + FK skeleton.
6. **Instrument fields** on the line (`cheque_no/cheque_date/bank_info/auth_no`) and a working
   bank-reconciliation flag flow (`reconciled` 0/1/2 + `reconciled_ref_id`, reconcile/unreconcile
   in `BankPaymentController`), though without `reconciled_date`/`reconciled_amount` (no partial
   reconciliation) and without delete-protection (Finding 7).
7. **3-decimal KWD storage** since `2025_10_12` and 6-decimal rates.

---

## Scorecard vs blueprint sections

| Blueprint § | Capability | Verdict |
|---|---|---|
| §1 | Header+detail document model | partial (~40%) |
| §1 | Save-time Σdebit=Σcredit | buggy (~10%, detection only, one-sided posts possible) |
| §2 | Debit XOR credit, non-negative | missing (~5%) |
| §2 | Leaf-only posting | buggy (check commented out; TB drops group postings) |
| §3 | Six line validations | missing/partial (~10%) |
| §4 | Eager running balance | buggy (fragmentary, non-atomic, inconsistent signs) |
| §4 | Monthly buckets | missing (0%) |
| §5 | Reverse-then-apply on edit/delete | buggy (~25%, one good path, 16 delete sites) |
| §5 | Protect applied/reconciled lines | missing |
| §5 | Periodic integrity check | partial (unbalanced-doc detector only) |
| §6 | Log-mirror audit trail | missing (~15% credit for soft deletes; cascade FKs undermine it) |
| §7 | Posted flag / drafts | missing |
| §7 | Period locks | partial (record locks; new postings not blocked) |
| §7 | Opening journal / year-end | missing + buggy (two conflicting "opening" definitions) |
| §8 | FC on the line | partial (~40%) |
| §8 | Effective-dated rates | partial (change log, not effective-dated) |
| — | Document numbering | partial (race-prone / user-supplied) |
| — | Line dimensions | partial (branch only; agent silently dropped) |

**Overall completeness: ~22%.**

## Priority fix order (developer-ready)

1. **Stop the bleeding (days):** throw instead of warn in `createCreditPaymentCOA` /
   `addJournalEntry` account lookups; wrap the ReceiptVoucher `import` branch in a DB transaction;
   scope `PaymentController.php:6143` and `JournalEntryController.php:48-52` by explicit
   `company_id`; add DB CHECK constraints (Finding 3) + `NOT NULL transaction_id`.
2. **Central PostingService (1-2 weeks):** single entry point enforcing §1 balance, §2 iron rules,
   §3 validations, doc numbering via locked sequences, purpose-code account registry. Migrate call
   sites incrementally, riskiest first (PaymentController webhook path, TaskController).
3. **Reversal discipline:** ban `JournalEntry::…->delete()` outside the service; generalize the
   existing reversal pattern; refuse changes to `reconciled != 0` lines.
4. **Periods & audit:** posting-date check against an `accounting_periods` table; JE/Transaction/
   Account audit log; change cascade FKs to restrict.
5. **Openings & aggregates:** replace `accounts.opening_balance` with an OJV document (reconciles
   the TB-vs-ledger discrepancy, Finding 10); then decide to rebuild or drop `actual_balance` and
   add monthly buckets if report volume demands it.
