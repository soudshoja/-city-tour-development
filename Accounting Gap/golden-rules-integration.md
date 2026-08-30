# Golden-Rules Integration Audit — citytourv2 Accounting Implementation

**Scope:** Cross-cutting audit of the 8 "golden rules" from the `travel-accounting-system` skill (`.claude/skills/travel-accounting-system/SKILL.md`) against the full citytourv2 codebase (main-branch checkout).
**Method:** Full-codebase greps for every journal-entry creation/mutation path, direct reading of the core models, migrations, posting call sites, and lock/report services, plus three deep traces (edit/delete reversal paths; balance maintenance; open-item AR/AP).
**All paths below are relative to the checkout root** (`app/…`, `database/…`). Line numbers are from the audited checkout.

---

## Summary table

| # | Golden rule | Verdict | Worst finding severity |
|---|---|---|---|
| 1 | One tree, post only to leaves | **Not enforced** (guard exists but is commented out; live feeder posts to a parent account) | **Critical** |
| 2 | One header + N balanced lines (Σdebit = Σcredit) | **Violated** (no write-time enforcement anywhere; two structurally unbalanced feeders found) | **Critical** |
| 3 | Base AND original currency on every line | **Partially enforced** (columns exist, rarely populated; rates are single mutable rows, not effective-dated) | **High** |
| 4 | Eager balances + reverse-before-reapply | **Violated** (2 correct reversal paths, ~8 corrupting ones; cached balances drift one-way; soft-deleted entries double-count in raw reports) | **Critical** |
| 5 | Posted-only + opening journal | **Not enforced** (no draft/posted state at all; period locks exist but are barely enforced; no year-end close / retained earnings) | **High** |
| 6 | Open-item AR/AP via apply | **Violated** (5–6 parallel "paid" representations; the applications table is incomplete and unused by reports) | **Critical** |
| 7 | Feeders emit documents, never invent accounting | **Violated** (131 hand-rolled `JournalEntry::create` sites across 21 files; no shared posting engine; account resolution by name strings, one without a tenant filter) | **Critical** |
| 8 | Everything dimensioned and audited | **Partially enforced** (branch + company on every line; no cost-center, no user attribution, no audit-log mirror) | **Medium** |

**Critical findings: 5 rules. High: 2. Medium: 1.**

**Single worst integration violation:** there is **no posting engine**. Every feeder hand-rolls its own debit/credit legs (131 call sites, 21 files), which is *why* every other rule fails: nothing central can enforce leaf-only, balance, currency capture, reversal, or dimensions. The sharpest concrete corruption path this produces is `InvoiceController::update()` (see Rule 4, finding 4.1), which soft-deletes an invoice's complete journal set and recreates only 2 of its N legs, outside any DB transaction.

---

## Rule 1 — One tree, post only to leaves

**Verdict: NOT ENFORCED. Severity: Critical.**

### 1.1 The leaf-only guard exists — and is commented out

`app/Models/JournalEntry.php:57-78`. Someone built exactly the right enforcement (a `creating` hook that rejects postings to accounts with children) and then disabled the entire `boot()`:

```php
// public static function boot()
// {
//     parent::boot();
//     static::creating(function ($journalEntry) {
//         $account = Account::find($journalEntry->account_id);
//         if ($account && $account->children()->exists()) {
//             Log::error('Attempt to create journal entry for an account with child accounts.', [...]);
//             throw new \Exception('Cannot create journal entry for an account that has child accounts.');
//         }
//     });
// }
```

There is no substitute check anywhere: across the whole `app/` tree, `children()->exists()` appears exactly twice — the commented block above and one ad-hoc guard for flight tasks only (`app/Http/Controllers/TaskController.php:1967`, which throws "Flight task must have a valid issued by account **to avoid using parent account**" — proof the team knows parent-posting happens and guarded a single path).

### 1.2 A live feeder posts to parent (group) accounts

`app/Http/Controllers/ChatController.php:727-734` (WhatsApp invoice creation). The code *finds* the correct supplier child leaf, stores it in `$PayablechildAccountId` — and then never uses it, posting to the parent instead:

```php
$filteredPayableChildAccount = $payableAccount->children()
    ->where('reference_id', $task['supplier_id'])->first();
$PayablechildAccountId = $filteredPayableChildAccount ? $filteredPayableChildAccount->id : null;
...
JournalEntry::create([
    ...
    'account_id' =>  $payableAccount->id,   // ← the PARENT group account
```

`$PayablechildAccountId` appears only at lines 727 and 729 — computed, never consumed.

### 1.3 Why this is Critical, not just untidy

The trial balance **silently excludes** entries posted to non-leaf accounts. `app/Services/TrialBalanceService.php:96-98` and `:147-149` restrict to leaves:

```php
->whereRaw('NOT EXISTS (SELECT 1 FROM accounts child WHERE child.parent_id = a.id)')
```

So every journal entry posted to a group account (Rule 1.2 above) **vanishes from the trial balance and opening balances** while its counter-leg (on a leaf) is counted → the TB will not tie, with no error anywhere.

### 1.4 Account type is hand-typed, not derived

`accounts.account_type` is a free string set inline at 25+ account-creation sites scattered through feeders: `app/Http/Controllers/InvoiceController.php:1551` (`'account_type' => 'income'`), `RefundController.php:681,706,764,961,983,1347,…` (`'asset'`/`'income'`), `ChargeController.php:233,254`, `AgentController.php:451` (straight from request input), `app/Console/Commands/UpdateOldTaskToTransaction.php:260,293,407`. Meanwhile the reporting layer *ignores* that column and derives debit/credit nature from the tree root's **name** (`TrialBalanceService.php:226-229`: `in_array($account->root_name, ['Assets','Expenses']) ? 'debit' : 'credit'`). Two independent type systems that nothing reconciles. Feeders also auto-create structural accounts inline with hardcoded tree positions — e.g. `RefundController.php:957-967` `firstOrCreate` of 'Direct Income' with `'root_id' => 4` **hardcoded** (a literal cross-company ID assumption).

### Recommendation

1. Re-enable the `creating` guard in `JournalEntry` (as a DB-agnostic minimum), plus reject `is_group = 1` accounts; backfill the `is_group` flag from `children()` reality first.
2. Fix `ChatController.php:734` to use `$PayablechildAccountId` (auto-create the supplier child if missing, as TaskController does).
3. Make `account_type`/normal-balance a derived attribute of `root_id` (single source), and stop accepting `account_type` from request input (`AgentController.php:451`).
4. Add a scheduled integrity check: journal entries whose account has children → alert (these are already invisible to the TB).

---

## Rule 2 — Every transaction is one header + N balanced lines

**Verdict: VIOLATED. Severity: Critical.**

### 2.1 A header model exists, but attachment is optional and unbalanced-by-schema

There *is* a header: `app/Models/Transaction.php` (`transactions` table), and most feeders create one before their journal legs. But:

- `journal_entries.transaction_id` is **nullable** with no FK (`database/migrations/2025_03_17_103934_create_general_ledgers_table.php:17`: `$table->foreignId('transaction_id')->nullable();`) — journal entries can and do float free.
- `Transaction` carries a single `amount` + `transaction_type` ('debit'/'credit'), not total-debit/total-credit; nothing compares header amount to line sums.
- **No write-time Σdebit=Σcredit check exists anywhere in the codebase.** No DB constraint, no observer (the only observer-like hook is the commented-out block in Rule 1.1), no service-level validation. Balance is purely by-convention at each of the 131 creation sites.

### 2.2 Structurally unbalanced feeders (proof, not theory)

**(a) WhatsApp invoice creation is unbalanced by construction.** `app/Http/Controllers/ChatController.php:734-799`: per task it writes exactly three legs —

| Leg | Account | Side | Amount |
|---|---|---|---|
| 1 (line 734) | Supplier payable (parent!) | **debit** | `$selectedtask->total` (cost) |
| 2 (line 756) | Receivable | **credit** | `$task['invprice']` (selling) |
| 3 (line 780) | Income | **credit** | `invprice − total` (markup) |

Σdebit − Σcredit = `total − invprice − (invprice − total)` = **−2 × markup**. Every WhatsApp-created invoice with any markup posts an unbalanced document (and with inverted sides relative to a normal sale: payable debited, receivable credited at *invoice* time). Bonus defects in the same arrays: duplicated keys (`'branch_id'`, `'account_id'`, `'invoiceDetail_id'` each appear twice) and the misspelled `'invoiceDetail_id'` (real column: `invoice_detail_id`) is silently discarded by the fillable guard — so these legs also lose their invoice-detail linkage.

**(b) Scheduled command posts a single-sided entry.** `app/Console/Commands/CheckMyFatoorahPayments.php:153-171` creates a `Transaction` and then exactly **one** journal entry (credit to 'Payment Gateway', `debit => 0`) — no debit leg exists anywhere in the file (verified: single `JournalEntry::create` occurrence). It then mutates `actual_balance` directly (line 170).

**(c) The invoice posting routine can persist half a document.** `app/Http/Controllers/InvoiceController.php:1374` `addJournalEntry()` creates each leg inside its own `try/catch` that `return response()->json(['success'=>false…])` instead of throwing (`:1516-1522`, `:1583-1589`). Whether the half-posting survives depends entirely on the caller:
- `InvoiceController::savePartial` (`:1146-1159`) decodes the response and throws → its wrapping `DB::transaction` (`:941`) rolls back. Safe.
- `app/Console/Commands/RunAutoBilling.php:275-284` calls it and **ignores the returned response** (its catch only handles thrown exceptions, which never come) → a failed leg leaves a committed one-sided posting, silently, on a scheduled job.
- `app/Services/PaymentApplicationService.php:149-156` likewise ignores the return value.

**(d) Manual entry screens.** `app/Http/Controllers/AccountingController.php:874-952` `storeBankPayment()` creates two entries with **no DB transaction at all**, and contains two outright bugs: for admins it assigns the literal validation-rule string as data — `:906` `$validated['company_id'] = 'required|integer';` — and its `actual_balance` "double update" subtracts then re-adds on the **same** bank account (`:931-932` and `:947-948` both target `$request->bank_account`), so the counterparty account balance is never touched and the bank nets to zero.

### 2.3 The only balance check is read-side, and it has a blind spot

`TrialBalanceService::findUnbalancedTransactions()` (`app/Services/TrialBalanceService.php:191-221`) detects `ABS(SUM(debit)−SUM(credit)) > 0.001` — good — but it **inner-joins `transactions`**, so any journal entries with `transaction_id = NULL` (legal per schema) are invisible to the detector.

### Recommendation

1. Introduce one posting API (see Rule 7) that accepts a document (header + lines), validates Σd=Σc, debit-XOR-credit per line, and non-null header linkage, and writes atomically. Refuse everything else.
2. Make `journal_entries.transaction_id` NOT NULL + FK once backfilled; until then, extend `findUnbalancedTransactions` with a second query for orphan entries.
3. Immediate hotfixes: ChatController leg amounts/sides; CheckMyFatoorahPayments missing debit leg; make `addJournalEntry` **throw** on failure instead of returning JSON; wrap `storeBankPayment` in a transaction and fix the two bugs at `:906` and `:947`.

---

## Rule 3 — Base AND original currency on every line

**Verdict: PARTIALLY ENFORCED. Severity: High.**

### 3.1 The columns exist…

`journal_entries` has `currency`, `exchange_rate`, `amount` (added `database/migrations/2025_03_25_085713_…:10-12`) and `original_currency` (3-char, nullable) + `original_amount` (nullable) (added `2025_08_02_170959_…:15-16`). Precision was corrected late: until `2025_10_12_111941_…`, `exchange_rate` was **decimal(10,2)** — i.e. every historical entry's stored rate is rounded to 2 decimal places (now 15,6); `amount` was (10,2), now (15,3).

### 3.2 …but the feeders barely populate them

- `original_currency`/`original_amount` are set at journal-entry creation in essentially **one** place, and it's hardcoded: `app/Http/Controllers/RefundController.php:790` (`'original_currency' => 'KWD'` — the *base* currency, defeating the purpose). Every other `'original_currency' =>` hit in controllers targets the `tasks`/`payments` tables, not journal entries. The 100+ other JE creation sites leave both NULL.
- `exchange_rate` on invoice-side entries is copied from the task (`'exchange_rate' => $task->exchange_rate ?? 1.00`, e.g. `InvoiceController.php:1512,1580,…` — ~30 sites), which at least snapshots the task-time rate. But large families of entries (ChatController, AccountingController, RefundController's refund legs, CheckMyFatoorahPayments, ClientController top-ups) set **no currency and no rate at all** — the column default is `0.00`, an impossible rate value that downstream code must special-case.
- Reporting partially compensates: `CoaController.php:99-103, 292-298, 440` aggregates by `original_currency` where present — so where the data is NULL, the "foreign ledger view" is simply empty.

### 3.3 Rates are single mutable numbers, not effective-dated

`app/Models/CurrencyExchange.php` and `app/Models/SystemExchangeRate.php` are one-row-per-pair current rates (`base_currency`, `exchange_currency`, `exchange_rate`) that get overwritten (`CurrencyExchangeController.php:153,161,433-442`). `app/Models/ExchangeRateHistory.php` is a *change audit* (`old_rate`/`new_rate`/`changed_by`), not an effective-dated lookup: no posting or backdated-report code resolves "the rate as of date X." Any backdated document or re-posting uses **today's** rate.

### Recommendation

1. In the shared posting engine, make `currency`, `exchange_rate` (> 0), base `amount`, `original_currency`, `original_amount` mandatory on every line; base-currency lines record `original_* = base` and rate 1.
2. Add `effective_from` to `currency_exchanges` (or query `ExchangeRateHistory` by date) and resolve rates by document date.
3. Data repair: flag entries with `exchange_rate = 0.00` and rebuild from linked task/payment records where possible; document the pre-Oct-2025 2-dp rate rounding for auditors.

---

## Rule 4 — Maintain balances eagerly; always reverse before re-applying

**Verdict: VIOLATED. Severity: Critical.**
(Design note: the system actually chose the *opposite* of the skill's "eager" approach — every report re-sums `journal_entries` at read time. Re-summing per se is a defensible design at this scale; the violations are (a) the half-abandoned eager fields that still exist and drift, and (b) edit/delete paths that corrupt the ledger itself.)

### 4.1 Invoice edit paths — five inconsistent strategies, two of them corrupting

| Path | File:lines | Strategy | In DB txn? | Verdict |
|---|---|---|---|---|
| `update()` | `InvoiceController.php:3802-3946` | soft-delete ALL JEs (`:3845`) + recreate **only** payable+receivable (`:3894,3911`) | **No** | **Corrupting** |
| `updateTaskPrice()` | `:3426-3510` | in-place mutation of JEs matched by **description substring** (`:3450-3473`) | **No** | **Corrupting (partial)** |
| `updateAmount()` | `:3533-3800` | contra-reversal + full repost (`:3577-3582`) | Yes | **Correct** |
| `updateDetailsAmount()` (accountant edit) | `:4811-5196` | contra-reversal + full repost (`:4891-4901`) | Yes | **Correct** |
| `recalculateInvoiceCOA()` | `:2317-2690` | in-place update-or-create of profit/loss/gateway legs only; bails silently if no transaction (`:2333`) | caller | Partial |
| `delete()` | `:4012-4069` | soft-delete cascade | Yes | OK as soft-delete (but see 4.3) |
| `destroy()` | `:4074-4077` | **empty method body** — the resource destroy route is a no-op | – | Dead |

The legacy `update()` path is the sharpest corruption in the system: it soft-deletes the invoice's *complete* journal set — including profit, commission, loss, and gateway-fee legs — then recreates only the supplier-payable and client-receivable legs, with no `DB::transaction`, so a mid-loop exception leaves deletes committed and re-inserts half done. Every invoice edited through it silently loses its P&L postings.

`updateTaskPrice()` mutates only the entries whose `description` contains `'Invoice created for (Assets)'` / `'(Income)'` / `'Agents Commissions for (Expenses)'` — the agent-profit-share, loss, and gateway-profit entries derived from the old price go permanently stale.

The two **correct** paths (`updateAmount`, `updateDetailsAmount`) show the team knows the right pattern:

```php
// InvoiceController.php:3577-3582 — contra entries, originals preserved
foreach ($transactionToReverse->journalEntries as $entry) {
    JournalEntry::create(['transaction_id' => $reversalTransaction->id,
        'debit' => $entry->credit, 'credit' => $entry->debit, ...]);
```

### 4.2 Other documents

- **Receipt voucher `update()`** (`ReceiptVoucherController.php:608-631`): soft-delete all + recreate (`JournalEntry::where('transaction_id',$id)->delete();` then `storeJournalEntryEntries(...)`), transactional. No destroy method exists at all.
- **Refund `update()`** (`RefundController.php:1156-1281`): transactional soft-delete + recreate via `cleanupExistingRefundRecords()` (`:1273-1278`). No standalone refund destroy.
- **Payment**: edits are blocked once completed (`PaymentController.php:4692`); but `paymentDeleteLink()` (`:4789-4807`) soft-deletes a Payment with **no status guard and no JE handling** — invoked on a completed payment it orphans the posted transaction/JEs (they reference `transaction_id`, not `payment_id`; nothing cascades).
- **Manual journal entries can never be corrected**: `JournalEntryController` is read-only (`index/show/getJournalEntries/exportPdf` only, 95 lines) and `AccountingController` has create methods only — a wrong manual payable/receivable/bank posting has **no edit, no reversal, no delete path** in the UI.
- **Task void/status** (`TaskController.php:2416-2478, 4653-4759`): transactional soft-delete + repost, but the revert helpers select entries via `description LIKE '%{$task->reference}%'` — fragile string matching that silently strands non-matching entries.

### 4.3 The soft-delete / raw-report mismatch (system-wide double counting)

`JournalEntry` uses `SoftDeletes` (`app/Models/JournalEntry.php:14`), and every controller delete above is a soft delete. Eloquent hides them — but several reports query `journal_entries` through the **raw query builder**, which does not apply the soft-delete scope:

- Filtered correctly: `TrialBalanceService.php:88,143,209`; `CoaController.php:79,97`.
- **NOT filtered** (verified zero `deleted_at` occurrences in these files): `ReportController.php:90` (agent ledger), `:1127` (supplier receivable/payable totals); `BankPaymentController.php:642`; `ReceiptVoucherController.php:712`.

Consequence: **every** delete-and-recreate edit path (invoice `update()`, receipt-voucher `update()`, refund `update()`, task reverts) double-counts in the agent ledger, supplier statements, and the receipt-voucher/bank-payment account pickers, while the trial balance shows the correct number — reports that should tie, don't.

### 4.4 The eager fields that do exist are write-only garbage

- `journal_entries.balance` is populated with whatever the site felt like: `$clientAccount->balance ?? 0` where `Account` has **no** `balance` attribute → literally 0 (`InvoiceController.php:1508,1576` + ~15 more), the line amount (`AccountingController.php:619,823`), hardcoded 0, or `task->total`. It is a running balance in name only; the sole true computation is in a repair command (`app/Console/Commands/FixPaymentLinkCOA.php:141-180`, opt-in `--recalculate-balances`).
- `accounts.actual_balance` is incremented on some creates (`ReceiptVoucherController.php:1041,1169,1204,1211`; `CreditController.php:212,242`; `PaymentController.php:6224,6247`; `CheckMyFatoorahPayments.php:170`) and **never decremented by any reversal/delete/recalc path** — one-way drift, repaired only by `FixPaymentLinkCOA`/`FixGatewayCharges` commands. The very existence of the `Fix*` command family (`FixPaymentLinkCOA`, `FixGatewayCharges`, `FixOldProfit`, `FixInvoiceCoa`, `FixCreditInvoiceCOA`) is the system confessing that balances drift.

### Recommendation

1. Route **all** edits through the contra-reversal pattern already proven in `updateAmount`/`updateDetailsAmount`; delete the legacy `update()` recreate logic and `updateTaskPrice` description-matching.
2. Add `whereNull('je.deleted_at')` to the four raw report queries **today** — one-line fixes that stop active double counting.
3. Either maintain `actual_balance`/`balance` transactionally in the posting engine (increment on post, decrement on reversal) or drop the columns; the current half-state misleads.
4. Guard `paymentDeleteLink` with a completed-status check.

---

## Rule 5 — Posted-only, and opening journals are special

**Verdict: NOT ENFORCED. Severity: High.**

### 5.1 No draft/posted distinction exists

Grep for `is_posted` / `'draft'` / `->posted` across `app/`: zero hits in the accounting layer. `journal_entries` has no status column (`app/Models/JournalEntry.php:16-48`); every created entry is immediately visible to every report. `TrialBalanceService` and all ledgers filter only by soft-deletes and dates. An accountant cannot stage anything.

### 5.2 Period locking: built, barely enforced

The `Lockable` trait (`app/Http/Traits/Lockable.php`) + `LockManagementController` (`lockByPeriod` `:172`, `lockByMonth` `:230`, `unlockByMonth` `:275`) set `is_locked` flags on `invoices`/`transactions`/`journal_entries` by date range (`database/migrations/2026_02_09_133957_…`). But enforcement at write time is almost nonexistent: `isLocked()`/`canModify()` are checked in exactly **three** places, all in `InvoiceController.php:6441-6481` (the lock/unlock endpoints themselves). None of the edit/delete paths in Rule 4 — receipt-voucher update, refund update, task revert, journal deletes, `recalculateInvoiceCOA` — consult the lock. And nothing prevents **creating new backdated entries** with a `transaction_date` inside a locked period: locks apply to rows, not periods.

### 5.3 No year-end close; opening balances are inconsistent

- **No closing/opening journal mechanism exists.** No code posts to Retained Earnings; greps for retained/closing/year-end across `app/` return nothing accounting-related. P&L never collapses into equity; the "current year" is simply whatever date range a report is run for.
- An opening-balance *column* exists (`accounts.opening_balance` + `opening_balance_date`, migration `2026_02_08_100000`; writer `CoaController::saveOpeningBalances()` `:1210-1265`) — but **`TrialBalanceService::getOpeningBalances()` ignores it** (`:131-157` sums prior journal entries only), while `CoaController` (`:326,:456`) and `JournalEntryController::show()` (`:31`) **do** add it. The trial balance and the COA/ledger screens therefore disagree on opening balances for any account seeded via the UI.

### 5.4 Equity & shareholder profit (asked mid-audit)

Equity exists **only as seeded COA placeholders**: `database/seeders/CoaSeeder.php:108-112` creates `3100 Capital Stock`, `3200 Dividends Paid`, `3300 Opening Balance Equity`, `3400 Retained Earnings`, and `TrialBalanceService` will display an Equity section if postings exist (`:180`). But **no application code ever posts to any of them** — there is no P&L-close into Retained Earnings, no dividend/shareholder distribution logic, no capital-account movement. The extensive "profit" machinery in the codebase (invoice profit, agent commission/loss split, gateway profit — `InvoiceController::createProfitEntries` etc.) is *operational gross-margin allocation between agents and the company*, not equity accounting. Shareholder profit calculation is **not part of this system today**; implementing it requires the year-end close of Rule 5.3 first.

### Recommendation

1. Add `status` (`draft|posted|reversed`) to `transactions` + `journal_entries`; reports filter `posted`.
2. Enforce locks centrally in the posting engine: reject any post/reversal whose `transaction_date` falls in a locked period (period table, not row flags), instead of relying on per-controller checks.
3. Build the year-end routine: immutable opening journal carrying BS accounts forward, P&L collapsed into `3400 Retained Earnings`; make `TrialBalanceService` and `CoaController` read openings from the same source.

---

## Rule 6 — Open-item AR/AP via "apply"

**Verdict: VIOLATED. Severity: Critical.**

### 6.1 Five-to-six parallel representations of "how much of this invoice is paid"

1. **`Invoice.status`** — cached enum (`paid/partial/unpaid/paid by refund/refunded`), written directly at ~15 sites (`PaymentController.php:3903,4068,4330,4476-4478,5097,5251-5253,6109-6120`; `InvoiceController.php:1074,1089-1091,4188,4223,4435,5731`; `PaymentApplicationService.php:294-342`).
2. **`InvoicePartial` rows** (`status='paid'`, `amount`) — the de-facto paid breakdown most reports sum.
3. **`PaymentApplication` rows** — the intended open-item table (added Jan 2026, `database/migrations/2026_01_12_154855`). `Invoice::getRemainingBalanceAttribute()` (`app/Models/Invoice.php:163-166`) correctly derives outstanding from it — but the table is **only populated for credit/top-up applications** (`PaymentApplicationService.php:258,594` and a backfill command are the only writers). Gateway/cash/KNET payments never create one (e.g. `PaymentController.php:4457-4484` sets partials paid and posts COA with **no** PaymentApplication), so the derived accessor is wrong for every gateway-paid invoice.
4. **`Credit` ledger rows** (negative `amount` on application, `PaymentApplicationService.php:236-248`).
5. **`journal_entries`/`transactions`** — the double-entry AR itself.
6. **`InvoiceReceipt`** rows (`amount`, `status`, `is_used`).

### 6.2 Reports disagree by construction

- `ClientController::show` (`:519-537`) and `ReportController::rangeSalesAgents` (`:2302-2311`): outstanding = `Invoice.status` + `InvoicePartial` sums.
- `ReportController::clientReport` (`:147-183`): eager-loads `paymentApplications` (`:150`) then **ignores them**, summing partials instead (`:177`).
- `ReportController` client-credit report (`:568`): outstanding = `SUM(credit) − SUM(debit)` over **`transactions`** — a third basis.

No report derives outstanding as *invoice − applied*. The applications table that would make this open-item is incomplete and read by nothing.

### 6.3 Unapply/delete doesn't reconcile the copies

- `payment_applications.payment_id` has `onDelete('cascade')` (migration `2026_01_12_154855:25`) — but `Payment` and `PaymentApplication` both got **SoftDeletes** later (`2026_02_11_200000`), and DB cascades don't fire on soft deletes: soft-deleting a payment leaves its applications and the invoice's `status='paid'` untouched.
- There is no "unapply" routine that reverses application + credit + partial + journal together; the closest thing is the invoice-delete cascade (`InvoiceController.php:4028-4032`).
- Credit top-ups decrement `Account.actual_balance` (`CreditController.php:212`), and the later soft-deletes of Credit rows never restore it (see Rule 4.4).

### Recommendation

1. Make `PaymentApplication` the **only** paid-state writer: every settlement (gateway, cash, credit, refund-offset) creates one; `Invoice.status` becomes a derived/refreshed projection of `amount − SUM(applications)`; retire `InvoicePartial.status` as a source of truth.
2. One `outstanding` computation (repository/scope) used by every report and statement.
3. Build an explicit `unapply()` that contra-posts journals, restores the Credit row, and deletes the application in one transaction; block payment soft-delete while live applications exist.
4. Backfill applications from historical partials/receipts so the derived accessor becomes true.

---

## Rule 7 — Feeders emit documents; they don't invent accounting

**Verdict: VIOLATED. Severity: Critical. This is the root-cause finding.**

### 7.1 Census of every journal-entry creation path

`JournalEntry::create` appears at **131 call sites in 21 files** (there are no `new JournalEntry`, `JournalEntry::insert`, relation-`create`, or raw-SQL insert paths, and no Jobs/GraphQL writers — verified by grep):

| File | Sites | Classification |
|---|---|---|
| `app/Http/Controllers/InvoiceController.php` | 33 | Hand-rolled (module-local helpers: `addJournalEntry` :1374, `createCreditPaymentCOA` :2692, `addInvoiceChargeJournalEntries` :5238, reversal blocks) |
| `app/Http/Controllers/RefundController.php` | 23 | Hand-rolled |
| `app/Http/Controllers/ReceiptVoucherController.php` | 12 | Hand-rolled (`storeJournalEntryEntries` :654, `invoiceJournalEntry` :1231) |
| `app/Http/Controllers/TaskController.php` | 9 | Hand-rolled (`processIssuedTask` family) |
| `app/Console/Commands/FixCreditInvoiceCOA.php` | 9 | Repair script, hand-rolled |
| `app/Http/Controllers/ClientController.php` | 6 | Hand-rolled (client credit top-up) |
| `app/Http/Controllers/AccountingController.php` | 6 | Hand-rolled (manual payable/receivable/bank screens) |
| `app/Http/Controllers/MobileController.php` | 5 | Hand-rolled (mobile invoice flows; also raw `JournalEntry::where(...)->delete()` at :668, :780) |
| `app/Http/Controllers/BankPaymentController.php` | 4 | Hand-rolled (`storeJournalEntryEntries` :545 — a *second, different* function with the same name as ReceiptVoucherController's) |
| `app/Console/Commands/UpdateOldTaskToTransaction.php` | 4 | Migration script, hand-rolled |
| `app/Http/Controllers/PaymentController.php` | 3 | Hand-rolled (`createInvoicePaymentCOA` :6074) |
| `app/Http/Controllers/ChatController.php` | 3 | Hand-rolled (unbalanced — Rule 2.2a) |
| `app/Services/PaymentApplicationService.php` | 2 | Hand-rolled (`createCreditPaymentCOA` :645 — a **duplicate** of InvoiceController :2692) |
| `app/Http/Controllers/CreditController.php` | 2 | Hand-rolled |
| `app/Console/Commands/PaymentReleaseToCompanyBankAccProcess.php` | 2 | Hand-rolled scheduled posting |
| `app/Console/Commands/FixPaymentLinkCOA.php` / `CreateClientCredit.php` | 2+2 | Repair scripts |
| `app/Http/Controllers/AgentController.php` | 1 | Hand-rolled — **one-sided** salary-expense debit with no credit leg (`:332-344`) |
| `app/Console/Commands/FixOldProfit.php` / `FixInvoiceCoa.php` | 1+1 | Repair scripts |
| `app/Console/Commands/CheckMyFatoorahPayments.php` | 1 | Hand-rolled — **one-sided** (Rule 2.2b) |

**Shared-engine sites: zero.** There is no posting service. The nearest thing, `InvoiceController::addJournalEntry()`, is a public **controller method** invoked cross-layer by a console command (`RunAutoBilling.php:275`) and by a service that resolves a controller out of the container (`PaymentApplicationService.php:140` `app(\App\Http\Controllers\InvoiceController::class)`) — inverted architecture, and its JSON-response error contract silently swallows failures for both of those callers (Rule 2.2c).

### 7.2 Duplicated and diverging posting logic

- `createCreditPaymentCOA` exists **twice** (`InvoiceController.php:2692` and `PaymentApplicationService.php:645`) — parallel implementations of the same posting that will drift.
- `storeJournalEntryEntries` exists twice with different bodies (`BankPaymentController.php:545`, `ReceiptVoucherController.php:654`).
- The same "invoice sale" document is posted by at least four independent implementations (InvoiceController, ChatController, MobileController, UpdateOldTaskToTransaction), each with different legs — ChatController's is unbalanced, MobileController's uses its own delete+recreate.

### 7.3 No service→account mapping; accounts resolved by name strings

The skill requires a service→GL-account link table. Here, every feeder resolves accounts by **name matching at post time**, and auto-creates them if missing:

- `Account::where('name', 'Accounts Receivable')` / `('name', 'Clients')` (`InvoiceController.php:1485-1492`, `RefundController.php:1029-1036`, …)
- `Account::where('name','like','%'.ucfirst($task->type).' Booking Revenue%')` with inline `Account::create` fallback (`InvoiceController.php:1526-1560`, duplicated at `RefundController.php:951-988` with hardcoded `root_id => 4`)
- **Tenant-unsafe:** `app/Http/Controllers/PaymentController.php:6143` — `$receivableAccount = Account::where('name', 'Clients')->first();` with **no `company_id` filter** inside the gateway-webhook posting path: in this multi-tenant system it will post client receivables to whichever company's 'Clients' account has the lowest ID.
- Renaming any of these anchor accounts ('Accounts Receivable', 'Clients', 'Direct Income', 'Payment Gateway', 'Agent Salaries', …) silently breaks or reroutes postings; `CheckMyFatoorahPayments.php:122-136` at least throws when lookups fail, most sites just skip the leg.
- Supporting evidence that feeders historically failed to emit documents at all: `docs/generate-missing-receipt-vouchers-command.md` documents a backfill command for cash invoices that never got receipt vouchers, and the whole `Fix*COA` command family re-derives postings feeders should have made.

### Recommendation (the one structural fix)

1. Create `app/Services/Accounting/PostingService.php` with a single API — `post(DocumentDraft $doc): Transaction` — that enforces: leaf-only accounts (Rule 1), Σd=Σc + debit-XOR-credit (Rule 2), mandatory currency fields (Rule 3), dimension completeness (Rule 8), period-lock check (Rule 5), atomic write, and typed exceptions (never JSON returns).
2. Add a `service_account_mappings` table (`company_id`, `service_type`, `role` [receivable|revenue|payable|fee|loss…], `account_id`); feeders look up accounts by role, never by name; seeding replaces inline `Account::create` fallbacks.
3. Migrate call sites in risk order: ChatController & CheckMyFatoorahPayments (broken today) → PaymentController webhook (tenant bug at :6143) → invoice/refund/receipt flows → manual screens → console commands. Delete the duplicated helper pairs as each migrates.
4. Add a CI guard (e.g. architecture test) forbidding `JournalEntry::create` outside the posting service.

---

## Rule 8 — Everything is dimensioned and audited

**Verdict: PARTIALLY ENFORCED. Severity: Medium.**

### Dimensions

- **Branch and company are on every line**: `journal_entries.branch_id` and `company_id` are in the original schema (`2025_03_17_103934_…:19-20`, non-nullable) and populated at all creation sites; `Transaction` carries both too; `TrialBalanceService` supports branch/agent filtering (`:100-112`). Task branch moves are propagated (`TaskController::updateJournalEntriesBranch` `:4546`). This part matches the skill.
- **No cost-center exists anywhere**: grep for `cost_center|costCenter` across `app/` and `database/` returns nothing. Cost-center P&L is impossible without schema work.
- Multi-tenant scoping is applied inconsistently at *read* level (`Transaction` has a global company scope, `app/Models/Transaction.php:44-63`; `JournalEntry` relies on `BelongsToCompany`) and is bypassed by the raw-builder reports and the tenant-unsafe lookup in Rule 7.3.

### Audit trail

- **No `*Log` mirror for financial tables.** The only audit table is `system_logs` (`app/Models/SystemLog.php` — `user_id, model, current_value, new_value, remarks`), written via `LoggingHelper::log()` from exactly one domain: supplier renames/surcharges (`SupplierController.php:331,418,469,484`). No invoice, payment, journal, or account mutation is mirrored anywhere. No `spatie/laravel-activitylog` or similar package in `composer.json`. There is no `app/Observers/` directory.
- **No user attribution on postings**: `journal_entries` and `transactions` have no `created_by`/`user_id` column (verified against both models' fillables and all migrations) — only `locked_by` for the lock feature. You cannot answer "who posted this."
- Partial compensations: SoftDeletes on `JournalEntry`/`Transaction`/`Payment`/`Credit`/`PaymentApplication` preserve deleted rows (though Rule 4.3 shows this leaks into reports); `ExchangeRateHistory` audits rate changes with `changed_by`; `Log::info/error` file logging is pervasive but is not a queryable audit trail.

### Recommendation

1. Add `created_by` (and `posted_by` once draft/posted exists) to `transactions` + `journal_entries`; set it in the posting engine.
2. Introduce model-observer-based audit logging (or spatie/activitylog) scoped to the financial models: Invoice, Transaction, JournalEntry, Payment, PaymentApplication, Credit, Account (incl. opening-balance edits).
3. Add nullable `cost_center_id` to `journal_entries` + a `cost_centers` master now (cheap while the posting engine is being built), even if the UI comes later.

---

## Cross-rule remediation priority

| # | Action | Fixes | Effort |
|---|---|---|---|
| 1 | Add `whereNull('deleted_at')` to `ReportController.php:90,1127`, `BankPaymentController.php:642`, `ReceiptVoucherController.php:712` | Active double counting (R4) | Hours |
| 2 | Hotfix ChatController legs (:734-799), CheckMyFatoorahPayments missing debit (:153), AgentController one-sided salary (:332), PaymentController tenant filter (:6143), AccountingController `:906/:947` bugs | Live unbalanced/one-sided/cross-tenant postings (R1, R2, R7) | Days |
| 3 | Build `PostingService` + `service_account_mappings`; re-enable leaf guard; make `addJournalEntry` throw | Root cause (R7 → enforces R1, R2, R3, R8) | Weeks |
| 4 | Replace invoice `update()`/`updateTaskPrice()` with the contra-reversal pattern from `updateDetailsAmount`; add unapply routine | Ledger corruption on edit (R4, R6) | Weeks |
| 5 | Unify paid-state on `PaymentApplication`; single outstanding query | AR drift (R6) | Weeks |
| 6 | Draft/posted status + period-lock enforcement in engine + year-end close into `3400 Retained Earnings` | R5 (and unlocks shareholder-profit/equity accounting) | Weeks |
| 7 | `created_by` + observer audit log + `cost_center_id` | R8 | Days–weeks |
