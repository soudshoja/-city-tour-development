# Verification Log — Adversarial Re-Verification Audit Trail

Every finding rated **critical** or **high** by an Analyze-phase agent was handed to an independent Verify-phase agent instructed to **refute** it — re-searching the codebase from scratch, not re-reading the original claim. Only findings that survived are carried into files 08–10.

- **51 high/critical findings** went through verification: **50 CONFIRMED**, **1 REFUTED** (excluded from all deliverables).
- Where verification **confirmed the finding but corrected a sub-claim**, the correction is recorded so the trail is faithful (the reviewer often found the reality *worse* than the original claim).
- Medium/low findings were **not** adversarially verified and are not listed here (they carry an `[UNVERIFIED]` tag wherever they appear in 08/09).

Codebase verified against `main` HEAD `431f97e6…` (the audit used two equivalent checkouts: `C:\Users\User\city-tours-main` and the workflow `citytourv2-main-checkout` worktree; both match).

---

## Chart of Accounts (8 — all CONFIRMED)

| Finding | Sev | Verdict | Condensed reasoning / corrections |
|---|:--:|:--:|---|
| Leaf-only posting invariant unenforced — guard commented out | critical | **CONFIRMED** | `JournalEntry.php:57-78` fully commented; no replacement observer anywhere; `is_group` read only in a UI blade; `ChargeController` adds gateway leaves while `PaymentApplicationService`/`ClientController`/`Invoice`/`Credit`/`Report` controllers post directly to the parent; `delegatePriceAmadeus` is a retroactive re-homing tool proving the violation happens in prod. |
| Account code auto-generation ad-hoc/colliding | high | **CONFIRMED** | Every cited line verified verbatim (`rand()`, hardcoded `1213`/`5111`, parent+1, lexicographic max, `updateCode` no-uniqueness). Seeder cross-check: `5111` already = "Visa Cost" → real collision. No central generator exists. |
| `account_type` free-text/null/never derived | high | **CONFIRMED** | 132/132 seeder rows `null`; two vocabularies (`liability` vs `Assets`); direct request passthrough; **zero** conditional reads across `app/`; debit/credit orientation keys off root **name** in a dozen controllers; two unused parallel typing mechanisms. |
| Account-creation rules unenforced; no central service | critical | **CONFIRMED** | No `AccountService`/observer/FormRequest; 10+ duplicated `Account::create` with hardcoded `level`; `addCategory` takes level/root_id/parent_id raw; `Charge`/`Chat` hardcode `level=4`; `importAccounts` inserts `parent_id=null` phantom roots. No max-depth check anywhere. |
| Live running balance `actual_balance` stale | high | **CONFIRMED** | Main posting flows never persist it; only Credit/Client/Accounting + `Fix*` touch it; `storeBankPayment` updates same bank account twice (nets zero), counterparty untouched; `balance` snapshot reads a non-existent attribute → always 0; COA recomputes from JEs at render; `decimal(10,2)` vs JE `15,2`. |
| Party-master pointer FKs incomplete | critical | **CONFIRMED (understated)** | `clients.account_id` dropped by migration, `Client::account()` dead; one shared `Clients` account; `Supplier` `hasOne` ambiguous → name resolution; airlines only `accounting_code`. Magic-name lookups number **237** (not "~60"); `%Income On Sales%` lookup never seeded; `agent-settlement` branch only extends agents. |
| No unique constraints on name/code; seeder ships duplicates | high | **CONFIRMED** | No unique index in any of 11 accounts migrations; codes `2130`/`4130` and names `Clients`/`Payment Gateway` duplicated in the seeder; `parentMap` name-keyed → silent overwrite; import fuzzy-`LIKE` + `updateOrCreate` clobbers near-matches. |
| Unsafe account deletion | high | **CONFIRMED (with correction)** | `dstry` no authz/guard, `Account` no SoftDeletes, `reference_id` misuse (user id / branch id) all confirmed. **Correction:** `journal_entries.account_id` FK is **`ON DELETE CASCADE`** (added by `_161405` migration), not "no FK/orphaned" — so a delete **silently hard-deletes all JE rows, bypassing JournalEntry's SoftDeletes**; deleting a non-leaf account throws an uncaught FK 500. Consequence is worse than the original framing, not milder. |

---

## Double-Entry Posting Engine (9 — all CONFIRMED)

| Finding | Sev | Verdict | Condensed reasoning / corrections |
|---|:--:|:--:|---|
| No save-time debit=credit enforcement; one-sided documents by design | critical | **CONFIRMED** | All five one-sided paths verified live; no observer/boot/central service; JE `boot()` commented (and only about child-accounts, never balance); `findUnbalancedTransactions` is after-the-fact, called only from `ReportController`. |
| Leaf-account-only rule commented out; group postings vanish from TB | critical | **CONFIRMED** | `JE:57-78` commented; `is_group` write-only; `processIssuedTask:2118-2128` falls back to the parent supplier account for 9 of 12 task types; `TrialBalanceService` `NOT EXISTS child` filter silently drops group-account entries from TB **and** opening balances. |
| Eager balance maintenance broken (non-atomic, sign-inconsistent) | high | **CONFIRMED (conservative)** | 20 call sites (> "12+"), none with `lockForUpdate`; inconsistent signs; deletes never reverse; `AccountingController:930-948` interpolates unvalidated `$request->amount` into `DB::raw` (the `amount` field isn't validated) — SQL-injection vector; no monthly bucket table. |
| Edit/delete no reverse-then-apply discipline | high | **CONFIRMED** | One good reversal reverses only the latest txn via `str_contains` on descriptions; 22 raw bulk-delete sites; `reconciled` never checked before delete; mass `->delete()` bypasses model events; 6 `Fix*` commands; `fix/payment-voucher` exactly 161 commits behind. |
| Audit trail missing (no mirrors/triggers/package/observers) | high | **CONFIRMED (with correction)** | No auditing package, triggers, observers, or `*_log` mirrors; in-place mutation (`UpdateTransactionDate`); cascade FKs. **Correction:** `InvoiceController:3844` is a **soft** delete (`Transaction` has SoftDeletes), not hard — but an equivalent unprotected **hard**-delete path exists via `CoaController::dstry` (Account lacks SoftDeletes) and `ClearTaskRelatedData::forceDelete`, so the risk stands. |
| No Posted/draft flag; period locks single-controller | high | **CONFIRMED** | `Lockable`/`LockManagement` real but enforced only in `InvoiceController`; lock columns only on invoices/transactions/journal_entries; other controllers + all console commands ignore it; `store()` doesn't check a locked period on create; no posted/draft state, no financial-year entity. |
| Opening balances a mutable account column; two reports differ | high | **CONFIRMED** | `saveOpeningBalances` no zero-sum/audit; TB ignores the column (prior-JE sum), `JournalEntryController::show` uses only the column; `childAccount` a third convention; no year-end/retained-earnings logic; `opening-balance` branch unmerged/stale. |
| Line iron rules unenforced | high | **CONFIRMED** | No `CHECK` constraints; `boot()` commented; RV debit/credit `required|numeric` (store() even `nullable`) → negatives & two-sided rows pass; `disabled` never consulted at posting; no FC-consistency; ad-hoc `round()` with no per-currency lookup. System postings satisfy XOR only incidentally (hardcoded 0 side). |
| Cross-tenant account resolution unscoped in webhook context | high | **CONFIRMED** | `BelongsToCompany` scope only fires when `Auth::check()`; gateway callbacks run `withoutMiddleware(['auth'])`; `PaymentController:6143` omits `company_id` while the adjacent `Charge` lookup includes it (smoking-gun oversight); `company_id` non-nullable so a different tenant's row can win; pattern recurs in webhooks/commands. |

---

## Transactions & AR/AP (7 — all CONFIRMED)

| Finding | Sev | Verdict | Condensed reasoning / corrections |
|---|:--:|:--:|---|
| Balanced-document invariant not enforced; ≥5 unbalanced paths | critical | **CONFIRMED** | Verified in the worktree HEAD `431f97e68`: RV `account` one-sided (+ `branch_id=company_id` bug at :848), `handlePaidRefund` lone Dr, conditional-leg pattern in 4 sites, `createCreditPaymentCOA` swallows exceptions/returns null with caller ignoring it, `declineReconcile` orphans a leg. No PostingService/observer/trigger; the read-only detector corroborates. |
| Customer receivables pooled in one `Clients` account | high | **CONFIRMED** | `clients.account_id` was **built then removed** (stronger than claimed); pooled lookup repeated across Invoice/RV/PaymentApplication/Refund; suppliers get per-supplier leaves but are name-resolved; agents have real FKs; `accounts.client_id` exists but never used to resolve postings or auto-created per client. |
| Unscoped `Clients` lookup in gateway posting | high | **CONFIRMED (understated)** | `PaymentController:6143` unscoped; callbacks unauthenticated so the global scope is inert; `CoaSeeder` seeds **two** different `Clients` accounts per company (ambiguous even when scoped); every other site in the codebase scopes this lookup — 6143 is the lone outlier, i.e. an omission not a design choice. |
| Open-item apply engine half-built | high | **CONFIRMED** | `payment_applications` solid; no `settled_amount`/adjustment column on `journal_entries` (confirmed via migrations); `reconciled` set on user-selected lines **without amount validation** (`BankPayment:381-398`); no standalone un-apply; no negative guard; `fix/payment-voucher` is prior art. |
| AR/AP ageing reports missing | high | **CONFIRMED** | Zero `aging`/`ageing`/`bucket` hits in code or git history; closest is the unpaid JE report (no buckets, per-document outstanding, or FK party filter — string-matched supplier name). Minor overstatement noted: `due_date` *is* used for reminders (not for buckets), which doesn't affect the core claim. |
| Credit/debit notes & memo module absent | high | **CONFIRMED** | No CRN/DBN/memo/ADM/ACM anywhere; `handleRefundCOA` posts a balanced CRN but only inside the refund flow; `storePayable/ReceivableDetail` force a bank leg; `Credit` enum has no Debit/Charge type. |
| Posted ledger documents mutable & deletable | high | **CONFIRMED** | `InvoiceController::delete:4056-4057` soft-deletes JEs/Transactions with no status check; reports never `withTrashed()` → history vanishes; `MobileController::deleteInvoice` is an unguarded duplicate path; `recalculateInvoiceCOA` mutates posted rows in place; no `posted`/`doc_status` concept. |

---

## Travel Industry (IATA/BSP) (3 — all CONFIRMED)

| Finding | Sev | Verdict | Condensed reasoning / corrections |
|---|:--:|:--:|---|
| BSP statement reconciliation absent | high | **CONFIRMED** | Word-boundary grep: zero real BSP/ADM/ACM/MIR; `IataEasyPayService` wallet-balance only; `accountsReconciliationReport` is an internal `reconciled`-flag view; `airlines` is master-data, not a billing table; `fix/payment-voucher` unmerged. No ticket-level SysPayable-vs-BspPayable variance anywhere. |
| Void flow buggy | high | **CONFIRMED (worse than stated)** | `voidTask` matches `task_description == reference` but `autoGenerateInvoice` stores `$task->description` and **Task has no `description` column** → stored value is NULL → **zero-row match** (client credited, AR/revenue never reversed) on the primary auto-invoice path; `supplier_date` typo; non-atomic dangling `commit()`; no idempotency (batch dedup guard checks a string that never matches); no `reconciled` guard. |
| No duplicate-invoice guard; no auto-void | high | **CONFIRMED (inconsistent, not universal)** | Only the bulk path checks; `store`/`autoGenerateInvoice`/`TaskWebhook:479,521` unguarded; no unique constraint on `invoice_details.task_id`; no void-customer concept. Nuance: `PaymentController`'s own TBO path *does* check, and the webhook has task-level dedup — reduces but doesn't eliminate the double-post risk. |

---

## Reporting Layer (10 — all CONFIRMED)

| Finding | Sev | Verdict | Condensed reasoning / corrections |
|---|:--:|:--:|---|
| No shared canonical report query | high | **CONFIRMED** | Four divergent balance calculators; `getOpeningBalances` reused by nothing; `profitLoss` fully independent (own roll-up/sign/date column); `childAccount` and `JournalEntryController` separate again; no last-year comparison; no hidden shared service exists. |
| P&L `created_at` vs TB `transaction_date` — can't tie out | critical | **CONFIRMED** | `profitLoss` 628-629/680-687 use `created_at`; TB line 90 uses `transaction_date`; `UpdateOldTaskToTransaction` backfills `transaction_date ≠ created_at`, a live divergence path; no mutator syncs the two. Isolated bug (every other report uses `transaction_date`). |
| Two contradictory opening-balance definitions | high | **CONFIRMED** | `saveOpeningBalances` writes a static column (no JV); TB ignores the column; `JournalEntryController::show` uses only the column and drops movement between `opening_balance_date` and `date_from`; `childAccount` double-counts (third convention); no fiscal close. |
| Balance Sheet report missing | high | **CONFIRMED** | Only `REPORT_TYPES['BALANCE_SHEET']` + `report_type` column exist; no route/controller/view (full-tree grep); period profit never folded into equity; no year-end. |
| P&L simplified | high | **CONFIRMED** | Single month, `4*`/`5*` level-3 only, 12-month chart; no branch filter (`branch_id` unused); no cumulative/comparative; accounts outside the code shape silently vanish; full-year JEs loaded into PHP; route has no authz gate. |
| Account Ledger partial; `filterLedgers` crash; `ledgerByDateRange` cross-tenant | high | **CONFIRMED** | Static opening (wrong after period 1); no FC toggle despite `original_currency`; no pagination/authz; `filterLedgers:200` null-crashes (500) on non-invoice JEs; `ledgerByDateRange` has no company scope (Task lacks `BelongsToCompany`) and no gate — the sibling `show()` scopes correctly, proving the omission. |
| Receivable/Payable ageing missing | high | **CONFIRMED** | No bucketing; unpaid/paid reports use the `reconciled` flag, ignore `payment_applications`, and filter by free-text supplier name; `fix/payment-voucher` (161 behind, includes a data-rewrite migration) is unmerged prior art. |
| Travel-industry analytics catalogue missing | high | **CONFIRMED** | No segment count / airline variance / incentive / BSP / stock / tour-code / discrepancy reports; airline is never a `groupBy` dimension despite parsed data; only partial voids/hotel/refund/PNR coverage. |
| Report authorization only in menus | critical | **CONFIRMED (understated)** | Only `viewPaymentGatewaysReport` enforced in controllers; profit-loss/settlements/journal-entries/filter-ledgers/ledger-by-date open by URL for a bare AGENT; `ledgerByDateRange` cross-tenant; other reports use `role_id` allow-lists bypassing the permission system; `profitLoss`/`clientReport` lack even a `role_id` check. |

---

## Data Model, Numbering & Configuration (8 — all CONFIRMED)

| Finding | Sev | Verdict | Condensed reasoning / corrections |
|---|:--:|:--:|---|
| Account balances non-atomic RMW; no monthly aggregate | critical | **CONFIRMED (understates scope)** | No monthly-balance table anywhere; 20 unlocked `actual_balance +=`/`-=` sites; `balance` snapshot written from a non-existent attribute → always 0 (`1508`/`5378`); TB full-scans history for openings; 6 `Fix*` commands; the newest commit (`AgentSettlementService`) repeats the exact anti-pattern. |
| Number-series engine ad-hoc | high | **CONFIRMED (broader)** | 3 counter tables, no mask/year key; `sprintf` duplicated in 4 files; no yearly reset; unscoped `lockForUpdate` at 3 sites; unlocked `firstOrCreate` at more; global-unique `invoice_number` vs per-company sequence → cross-tenant collision; `voucher_number` non-unique; RV numbers user-supplied / `Str::random`. |
| No audit-log mirror tables; CreateID/ModID absent | high | **CONFIRMED** | No audit package (only spatie/permission + backup); accounts/invoices/transactions/journal_entries lack `created_by`/`updated_by` (only `locked_by`); `SystemLog` wired only into Task/Supplier controllers; JEs mutated in place (`CoaController:1076-1106`). |
| Ledger flat `journal_entries`; no header/Posted/DocType | high | **CONFIRMED** | `transaction_id` nullable from creation, never tightened; no `posted`/`doc_type`/`doc_year`; `reference_type` enum grew 2→4; `addJournalEntry` silently skips a leg inside `if($account)` yet returns success; `findUnbalancedTransactions` (INNER JOIN even excludes NULL `transaction_id`) is a symptom of the permitted imbalance; instrument/reconcile fields present (matches blueprint). |
| No financial-year table / period close | high | **CONFIRMED** | No fiscal-year/period entity; lock columns on 3 tables only; `lockByPeriod` flips existing rows; `store()` does no period validation on create → backdating into a "locked" month is possible; `Retained Earnings 3400` exists but is never posted to; TB computes closing on the fly with no reset. |
| System parameters carry no posting keys; KWD/control accounts hardcoded | high | **CONFIRMED** | `Setting` table good but only `invoice_expiry_days` + notifications used; `KWD` in 59 files; control accounts name-resolved; `TaskWebhook::getIataAccounts` hardcodes tenant names + `issued_by` with **no** company filter; `AccountingController:860/868` + `CreateClientCredit:214` are confirmed cross-tenant leaks; seeded `Exchange Gain/Loss 5219` is dead. |
| Party master: no unified tblPartner; per-party COA only for agents | high | **CONFIRMED** | 4 separate masters; inverted `accounts.{agent,client,supplier}_id` linkage; forward FKs only for agents; `clients.account_id` dropped; shared `Clients`; `TaskSchema:24` name-matches suppliers; `Supplier::account()` dead code; `AgentController:398` `Assets` lookup is **not** company-scoped (cross-tenant parenting bug — siblings scope it correctly); credit limits + GDS codes present. |
| Cost-center dimension absent | high | **CONFIRMED** | Zero `cost_center`/`CostCenter` hits repo-wide; `branch_id` present and populated; `account_dimension` enum is only a picker/rollup filter, not a reporting dimension; no later migration adds a cost dimension. |

---

## Modules & Config — Sub-Ledgers, Year-End, Cross-Cutting (5 — all CONFIRMED)

| Finding | Sev | Verdict | Condensed reasoning / corrections |
|---|:--:|:--:|---|
| Credit-apply GL posting can silently fail / post one-sided | high | **CONFIRMED** | `createCreditPaymentCOA` try/catch returns null; caller (line 287) ignores it; outer `DB::commit` proceeds → applied credits with no GL. Debit (4-level name-walk) and credit (AR>Clients) legs independently gated → one-sided possible. A second defensive copy in `InvoiceController` checks before creating the Transaction but its return is likewise ignored. Un-apply is destructive delete. FIFO is UI ordering only. |
| Year-end closing absent; conflicting opening semantics | high | **CONFIRMED (with correction)** | No close routine/OJV/nominal reset; TB (all prior JEs) vs static column vs movement-dropping ledger/COA → real cross-report discrepancy; payments commented out of the lock config. **Correction:** "no retained-earnings account anywhere" is inaccurate — `CoaSeeder:112` seeds `Retained Earnings 3400`; but it's never posted to, so the substantive point (no P&L→RE transfer) stands. |
| Bank & card reconciliation internal-matching only | high | **CONFIRMED** | Instrument + `reconciled` fields present; PaymentByDate flips matched JEs; `declineReconcile` un-reconciles; report exists — but no bank/card statement import or staging; `auth_no` is write-only (never a match key); no `ReconciledDate`/`Amt`; `fix/payment-voucher`'s `PaymentReconciliation` is an internal linking table, not a statement importer. |
| Foreign-currency revaluation entirely missing | high | **CONFIRMED (with nuance)** | JE captures currency/rate/original; `UpdateExchangeRate`/`CurrencyExchangeTrait` are point-in-time only; no revaluation command/observer/scheduled job; open FC balances sit at historical rate forever. **Nuance:** seeded `Exchange Gain/Loss 5219` exists but is dead (never posted) — reinforces rather than refutes the gap. |
| Credit/debit note (memo) documents missing | high | **CONFIRMED** | No CRN/DBN/memo/ADM/ACM under any name; `Credit` = prepaid wallet (closed enum), `Refund` = refund workflow, `AgentSettlement` = loss recovery — none is a numbered, GL-posted, C/D-typed memo adjusting a specific invoice/party; corrections done today by delete-and-regenerate or raw JVs. |

---

## REFUTED (1 — excluded from all deliverables)

| Finding | Sev | Verdict | Reasoning |
|---|:--:|:--:|---|
| *(not carried into the synthesis payload)* | high/critical | **REFUTED** | One high/critical finding flagged during the Analyze phase was **refuted** by its independent verification agent and excluded from files 00 and 08–10. Its specific title and refutation reasoning were **not** included in the synthesis input, so they cannot be reproduced verbatim here. This row is recorded for completeness of the audit trail; the detail lives in the workflow's verify-phase agent logs (`wf_ce18523e-8c7`). |

---

### How to read a "CONFIRMED (with correction)" row
The headline finding is real and stays in the report; a specific supporting sub-claim was found inaccurate on inspection and was rewritten in files 08/09 to match the code. In every such case the corrected reality was **equal or worse** than the original claim (e.g., a cascade hard-delete instead of orphaned rows; a NULL-valued join key instead of a mismatched-string join key; a seeded-but-dead retained-earnings account instead of no account). No "CONFIRMED (with correction)" row was a softening.
