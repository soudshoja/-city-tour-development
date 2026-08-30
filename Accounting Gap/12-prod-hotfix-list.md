# 12 — Production Hotfix List (P0)

**Target:** the **current live** soud-laravel production at `tour.citycommerce.group` (DB `citycomm_city-tour`). Same code lineage as the audited citytourv2 checkout — **the file paths and line numbers below are identical in the soud-laravel repo**. This is real money and real client PII today.

**Scope discipline:** small, targeted diffs only. No engine rebuild, no architecture change, no schema restructuring beyond adding indexes/constraints. Everything here is safe to ship independently of the P1–P9 plan in [11-technical-implementation-plan.md](11-technical-implementation-plan.md) and should ship **first**. File 11's "Phase P0" is exactly this list.

**Order:** by exploitability × impact. HF-1..HF-6 are the ship-today set (live, remotely reachable, money/PII). HF-7..HF-11 are same-week hardening.

**Before you start:** take a fresh `mysqldump --single-transaction` snapshot, and run `Accounting Gap/data-integrity-queries.sql` to record the "before" baseline (also the P3 baseline).

**After each fix:** add the named regression test; the accounting suite must stay green.

---

## HF-1 — Cross-tenant credit funds-transfer (the worst one) · CRITICAL

**Exploit:** any authenticated user of *any* company can read another company's client credit balances and **apply** them to pay an unrelated invoice — a real cross-tenant funds-transfer/fraud primitive, not just a read leak.

**Files / methods:**
- `app/Services/PaymentApplicationService.php` → `applyPaymentsToInvoice()` (loads `Invoice::findOrFail`, `Credit::findOrFail` with no company check).
- `app/Http/Controllers/InvoiceController.php` → `getAvailablePayments()` (:6284), `applyPaymentsToInvoice()`, `getInvoicePaymentHistory()` (:6407), `linkPaymentsToInvoicePartial()`.

**Minimal diff:** at the top of each method resolve `$companyId = getCompanyId(Auth::user())` and `abort_unless(…, 403)` that every supplied id belongs to it:
- `$invoice->agent->branch->company_id === $companyId`
- `$sourceCredit->company_id === $companyId`
- `$client->company_id === $companyId`
Laravel's `exists:clients,id` rules do **not** scope by company — these explicit checks are required. Do the credit check inside the existing transaction, after loading the credit, before any deduction.

**Test:** `TenantIsolationTest::apply_payments_rejects_foreign_credit` — user of company A posts company B's `credit_id`/`client_id`; assert 403 and that no `PaymentApplication`/`Credit`/`InvoicePartial` row was written and the invoice status is unchanged.

**Deploy risk:** Low. Adds guards only; legitimate same-company flows unaffected. Watch for any admin cross-company tooling that legitimately spans companies (there is none in these paths).

**Effort:** ~4h.

---

## HF-2 — Unauthenticated invoice PDF + sequential enumeration · CRITICAL

**Exploit:** `generatePdf()` builds an invoice PDF from `invoice_number` alone and **ignores the `$companyId` route param**, on a route with `withoutMiddleware(['auth'])`. Combined with sequential, guessable keys (`companyId = 1,2,3…`, `invoiceNumber = INV-2026-00001…`), the entire internet can enumerate every company's invoices, PDFs, proformas — client names, amounts, IBAN/SWIFT/bank fields.

**Files / methods:**
- `app/Http/Controllers/InvoiceController.php` → `generatePdf(int $companyId, string $invoiceNumber)` (:3270) — never uses `$companyId` to filter.
- `routes/web.php` → the public `withoutMiddleware(['auth'])` routes `:460, :498-500` (`/{companyId}/{invoiceNumber}`, `/pdf`, `/proforma`, `/arabic`).
- Number format: `generateInvoiceNumber()` (:2886).

**Minimal diff (two layers, ship both):**
1. **Immediate correctness bug:** add the same tenant filter `show()` already uses —
   `Invoice::where('invoice_number', $invoiceNumber)->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId))->firstOrFail()` — in `generatePdf` (and verify `showDetails`/proforma do the same).
2. **Kill enumeration:** replace the `{companyId}/{invoiceNumber}` public key with a high-entropy signed link. Add a nullable `invoices.public_token` (UUID) column, generate on issue, and route the public views by `URL::temporarySignedRoute('invoice.public', …, ['token' => $token])` / `signed` middleware. Keep the authenticated internal routes as-is.

**Test:** `PublicInvoiceTest::pdf_requires_matching_company` (mismatched companyId → 404) and `::public_link_requires_valid_signature` (tampered/guessed URL → 403).

**Deploy risk:** Medium. The signed-URL change alters client-facing invoice links — regenerate/notify for any links already sent, or keep a backward-compatible fallback that still enforces layer-1's company filter (closes the leak) while you migrate outstanding links to tokens.

**Effort:** ~6h (layer 1: 1h; layer 2: 5h incl. token column + link regeneration).

---

## HF-3 — SQL injection in `storeBankPayment` · CRITICAL

**Exploit:** `AccountingController::storeBankPayment()` interpolates an **unvalidated** `$request->amount` directly into raw SQL:
```php
Account::where('id', $request->bank_account)
    ->update(['actual_balance' => \DB::raw("actual_balance - {$request->amount}")]);   // :932
// … and :948 "+ {$request->amount}"
```
`amount` is **not** in the `validate()` rules — a crafted `amount` is a SQL-injection vector. The same method also has two latent bugs: `$validated['company_id'] = 'required|integer';` (:906, assigns the rule string as data for admins) and both `actual_balance` updates target the **same** `$request->bank_account` (:932 and :948), so the counterparty account is never touched and the bank nets to zero.

**File / method:** `app/Http/Controllers/AccountingController.php` → `storeBankPayment()` (~:874-952).

**Minimal diff:**
- Add `'amount' => 'required|numeric|min:0'` to the validation rules.
- Replace both `DB::raw("… {$request->amount}")` with bound expressions: `->decrement('actual_balance', $validated['amount'])` and `->increment('actual_balance', $validated['amount'])` on the **correct** two accounts (bank account decremented, counterparty `$request->account_id` incremented).
- Fix `:906` to set the real company id for admins (from a validated `company_id`), not the rule string.
- Wrap the two `JournalEntry::create` + balance updates in one `DB::transaction`.

**Test:** `BankPaymentSecurityTest::amount_is_validated_numeric` (a non-numeric/injection `amount` → 422, no SQL executed) and `::posts_balanced_two_account_entry` (bank down, counterparty up, both legs present).

**Deploy risk:** Low–Medium. Fixes a real posting bug too; verify the counterparty-account change matches intended semantics with finance before shipping the balance-direction fix (the injection + validation fix alone is zero-risk and can ship first).

**Effort:** ~3h.

---

## HF-4 — Gateway double-post (double money) · CRITICAL

**Exploit:** every gateway callback reads `Payment` without a lock and uses `if ($payment->status === 'completed')` as its only idempotency guard. Under MySQL REPEATABLE READ that snapshot read doesn't block, so a gateway retry, a user refresh of the return URL, or the webhook-vs-return-URL race lets two deliveries both pass the guard and both post — invoice paid once, ledger credited twice (or client credited twice for top-ups). No unique constraint backstops it.

**Files / methods:** `app/Http/Controllers/PaymentController.php` — `handleWebhookFatoorah`, `handleMyFatoorahCallback`, `handleTapCallback` (:4040), `handleKnetResponse` (:4317), `handleUPaymentCallback` (:5084), `handleHesabeResponse` (:5412), `handleHesabeWebhook` (:5775), `checkTransactionStatus` (:7347); the amplifier `createInvoicePaymentCOA` (:6082); `ClientController::addCredit`.

**Minimal diff (copy MyFatoorah's existing `Cache::lock` pattern to every gateway, add the lock + re-check + DB backstops):**
1. Load the payment **with a row lock inside** the money transaction and re-check status after acquiring it:
   ```php
   DB::transaction(function () use ($paymentId) {
       $payment = Payment::whereKey($paymentId)->lockForUpdate()->firstOrFail();
       if ($payment->status === 'completed') return;   // second caller blocks, then no-ops
       $payment->status = 'completed'; $payment->save();
       // … createInvoicePaymentCOA / addCredit …
   });
   ```
   (`handleMyFatoorahCallback` already wraps `Cache::lock('mf:callback:'.$paymentId, 40)` — replicate that lock name-spaced per gateway, but the `lockForUpdate` re-check is the real fix.)
2. Make `createInvoicePaymentCOA` and `addCredit` **idempotent**: at entry, `if (Transaction::where('payment_id',$payment->id)->where('reference_type','Invoice')->exists()) return;`.
3. **DB backstops (add these unique indexes now — cheapest structural fix):**
   ```php
   Schema::table('payments', fn (Blueprint $t) => $t->unique(['company_id','voucher_number']));          // HF-5 too
   Schema::table('transactions', fn (Blueprint $t) => $t->unique(['payment_id','reference_type']));       // gateway payment dedup
   // + unique on each gateway table's dedup key: tap_payments.tap_id, upayments_payments.track_id,
   //   myfatoorah_payments.payment_int_id, hesabe_payments.payment_int_id
   ```
   Clean any existing duplicates before adding the index (the P3 repair covers historical rows; for the index you only need to de-dup the small set of current collisions).
4. Move `handleHesabeResponse`'s `payment->save()` (:5427) **inside** the money transaction (F12 — else a rollback leaves `completed` with no postings, permanently suppressing retry).

**Test:** `GatewayDoublePostTest::concurrent_callbacks_post_once` — fire two callbacks for one payment (simulate the F1 interleaving); assert exactly one `Transaction` + one set of `JournalEntry` rows and one `actual_balance` increment. Repeat per gateway.

**Deploy risk:** Medium. Touches all payment callbacks — the money paths. Add the `lockForUpdate` + idempotency first (behavioural fix), then the unique indexes (after de-duping current collisions, or the migration fails). Test each gateway's happy path in sandbox before prod.

**Effort:** ~12h (2h/gateway × behaviour + 2h indexes/de-dup).

---

## HF-5 — `payments.voucher_number` race + no unique index · CRITICAL (part of HF-4's index set)

**Exploit:** voucher numbers are generated by unlocked read-increment (`Sequence::firstOrCreate → read → increment`, `PaymentController` :1129, :2505, :6641) and `payments.voucher_number` has **no unique index** (unlike `invoice_number`/`refund_number`). Two concurrent initiations get the same `VOU-YYYY-NNNNN` **silently**, and downstream gateway lookups keyed on `voucher_number` (Hesabe :5412/:5775, callback `orWhere('voucher_number', …)` :3867) then resolve to the **wrong** payment — misapplying one client's gateway result to another.

**File / method:** `app/Http/Controllers/PaymentController.php` (:1129-1133 and the sibling sites).

**Minimal diff:** copy the one correct pattern (`CreateBulkInvoicesJob::generateInvoiceNumber` :260-282) — `Sequence::where('company_id',$companyId)->lockForUpdate()->first()` inside a `DB::transaction` — at every voucher-number site; and add the `unique(['company_id','voucher_number'])` index from HF-4 step 3 as the hard backstop.

**Test:** `VoucherNumberTest::concurrent_initiations_never_collide` (parallel initiations produce distinct voucher numbers; the unique index would otherwise throw).

**Deploy risk:** Low (after de-duping current voucher collisions so the unique index applies cleanly).

**Effort:** ~3h.

---

## HF-6 — `PaymentController::createInvoicePaymentCOA:6143` posts cross-tenant · CRITICAL

**Exploit:** in the **unauthenticated** gateway-webhook posting path, `$receivableAccount = Account::where('name', 'Clients')->first();` runs with **no `company_id` filter**. `BelongsToCompany`'s global scope is a no-op when `Auth::check()` is false, so this resolves whichever company's `Clients` account has the lowest id — posting client receivables to the wrong tenant. (The developer scoped the adjacent `Charge` lookup by `$companyId` but missed this one.)

**File / line:** `app/Http/Controllers/PaymentController.php` :6143.

**Minimal diff:**
```php
$receivableAccount = Account::where('name', 'Clients')
    ->where('company_id', $companyId)   // <-- add; $companyId is already in scope at :6134
    ->first();
```
(The full fix — a per-company purpose-code registry replacing all name lookups — is P1.0 in file 11. This one-liner closes the live cross-tenant posting today.)

**Test:** `GatewayPostingTest::receivable_resolves_to_payment_company` (a payment for company 2 posts to company 2's `Clients`, never company 1's).

**Deploy risk:** Very low. One scoping clause.

**Effort:** ~1h.

---

## HF-7 — The 8 `AccountingController` AJAX endpoints trust client `company_id` · HIGH

**Exploit:** endpoints under the plain `auth` group take `$request->company_id` and never verify it belongs to the caller. `getBranchByCompany` (`Branch` has no tenant scope) and `getInvoicesByJournalEntry` (unscoped `Invoice::whereIn`) leak another company's branches, agents, suppliers, chart-of-accounts and — most sensitively — **bank account details** to any logged-in user, including low-privilege Agent accounts.

**File / methods:** `app/Http/Controllers/AccountingController.php` :286-448 — `getAccountsByCompanyReceivable`, `getBranchByCompany` (:342), `getBankAccountByCompany` (:400), `getInvoicesByJournalEntry` (:433), and the sibling `getAccountsBy*`/`get*ByCompany` methods (8 total).

**Minimal diff:** derive the company server-side and ignore the request value for non-admins:
```php
$companyId = Auth::user()->role_id === Role::ADMIN
    ? (int) $request->validate(['company_id' => 'required|integer'])['company_id']  // admin only, validated
    : getCompanyId(Auth::user());
```
Use `$companyId` in every query; drop `$request->company_id`. (`InvoiceController::edit()` :404 already does this correctly — copy it.)

**Test:** `AccountingAjaxTenantTest::non_admin_cannot_read_foreign_company` (Agent of A posts `company_id` = B to each endpoint → gets A's data or 403, never B's bank details).

**Deploy risk:** Low. Front-end already sends the caller's own company id in normal use, so non-admin behaviour is unchanged; only the forged-id path is closed.

**Effort:** ~4h (8 methods).

---

## HF-8 — Invoice lock / loss-bearer endpoints bound by raw ID only · HIGH

**Exploit:** `lockInvoice`/`unlockInvoice`/`getLossBearer`/`updateLossBearer` use route-model binding on `{invoice}` by raw id with only a **role** check (no company match). Any COMPANY/ACCOUNTANT/ADMIN-role user (or anyone with `manageLocks`) can lock, unlock, or rewrite the agent/company loss-split of **another company's** invoice.

**File / methods:** `app/Http/Controllers/InvoiceController.php` :6456-6560.

**Minimal diff:** add at the top of each of the four methods:
```php
abort_unless($invoice->agent->branch->company_id === getCompanyId(Auth::user()), 403);
```

**Test:** `LossBearerTenantTest::cannot_lock_or_edit_foreign_invoice` (company A user → 403 on B's invoice; loss-split unchanged).

**Deploy risk:** Very low.

**Effort:** ~2h.

---

## HF-9 — Report authorization URL bypass (incl. cross-tenant supplier ledger) · HIGH

**Exploit:** report authorization is enforced only in the navigation menu (`@can` on hidden links); the routes are open. A bare AGENT can fetch `/reports/profit-loss`, `/reports/settlements/entries/by-date` (full JE dump), `/journal-entries/{accountId}/account` (any account ledger by id), `/filter-ledgers`, and `/suppliers/ledger-by-date/{id}` — the last has **no gate and no company scoping** (`Task` lacks `BelongsToCompany`), a genuine cross-tenant leak by supplier-id enumeration. `journalEntriesByDate` returns unscoped cross-company data when `companyId` is falsy.

**Files / methods:** `app/Policies/ReportPolicy.php`, `ReportController.php` (profitLoss, settlements-by-date, journalEntriesByDate), `JournalEntryController.php` (account ledger + export/pdf), `AccountingController.php` (filterLedgers), `SupplierController::ledgerByDateRange`.

**Minimal diff:** add `Gate::authorize(...)`/`->can(...)` (using the existing `ReportPolicy` abilities) to **every** report and ledger route; company-scope `ledgerByDateRange` by filtering the `Task` query on the caller's company (via `agent.branch.company_id`); `abort_unless($companyId, 403)` in `journalEntriesByDate`'s falsy-company branch.

**Test:** `ReportAuthTest::agent_cannot_fetch_company_pl_or_foreign_supplier_ledger` (bare AGENT → 403 on each route; `ledger-by-date` for a foreign supplier → 403/empty).

**Deploy risk:** Low–Medium. May surface reports that some roles *were* silently using via direct URL — confirm the intended permission matrix with the product owner before locking each route.

**Effort:** ~5h.

---

## HF-10 — `filterLedgers` null-crash 500 · HIGH (availability)

**Exploit:** `AccountingController::filterLedgers` (:200) unconditionally reads `$ledger->invoice->agent->name` after guarding only `$ledger->invoice` at :197 — any journal entry without an invoice (payments, manual JVs) throws a null-property error → 500. The account-ledger screen is broken for any account that has non-invoice postings.

**File / method:** `app/Http/Controllers/AccountingController.php` `filterLedgers` (~:197-200).

**Minimal diff:** null-safe the chain: `$ledger->invoice?->agent?->name ?? $ledger->name`.

**Test:** `FilterLedgersTest::renders_for_payment_and_manual_je` (a JE with `invoice_id = null` renders without error).

**Deploy risk:** Very low.

**Effort:** ~1h.

---

## HF-11 — Correctness & cleanup batch · HIGH / MEDIUM

Ship together as one small PR:

**(a) Bank-rec raw totals include soft-deleted rows (report ≠ detail).** Add `whereNull('journal_entries.deleted_at')` to the raw `DB::table('journal_entries')` totals in `ReportController::accountsReconciliationReport` (:1127), `BankPaymentController::fetchPaymentsByDate` (:642), `ReceiptVoucherController` (:712). Use `data-integrity-queries.sql` to find any other raw JE queries. *Test:* `SoftDeleteTotalsTest::totals_exclude_soft_deleted` (soft-delete a leg; header total equals detail sum). *Risk:* very low. *Effort:* ~2h.

**(b) Unsafe account deletion.** `CoaController::dstry` (`DELETE /coa/api/{id}`) has no authorization and no child/entry guard, and `journal_entries.account_id` is FK `ON DELETE CASCADE` — deleting a posted leaf **hard-deletes its entire ledger history** at the DB level, bypassing SoftDeletes (irrecoverable). Gate `dstry` behind `AccountPolicy::delete`, refuse deletion when children or `journalEntries` exist (offer disable instead), and change the FK to `RESTRICT`:
```php
// migration: drop + re-add journal_entries.account_id FK with ->restrictOnDelete()
```
*Test:* `AccountDeleteTest::cannot_delete_account_with_entries_and_history_is_preserved`. *Risk:* low. *Effort:* ~3h.

**(c) Dead PDF routes → 500.** `routes/web.php:423-424` register `/reports/daily-sales/pdf` and `/pdf/download` to `dailySalesPdf`/`dailySalesPdfDownload`, both commented out (`ReportController.php:2058/:2088`) → clicking export throws method-not-found. Remove the two routes (or restore the methods). *Risk:* very low. *Effort:* ~0.5h.

---

## Summary

| HF | Title | Severity | Effort |
|----|-------|----------|:------:|
| HF-1 | Cross-tenant credit funds-transfer | CRITICAL | 4h |
| HF-2 | Unauth invoice PDF + enumeration | CRITICAL | 6h |
| HF-3 | SQL injection in `storeBankPayment` | CRITICAL | 3h |
| HF-4 | Gateway double-post (double money) | CRITICAL | 12h |
| HF-5 | Voucher-number race + no unique index | CRITICAL | 3h |
| HF-6 | `:6143` cross-tenant posting | CRITICAL | 1h |
| HF-7 | 8 AccountingController AJAX endpoints | HIGH | 4h |
| HF-8 | Invoice lock / loss-bearer raw-ID | HIGH | 2h |
| HF-9 | Report auth URL bypass (+ supplier ledger) | HIGH | 5h |
| HF-10 | `filterLedgers` 500 | HIGH | 1h |
| HF-11 | Correctness/cleanup batch (a/b/c) | HIGH/MED | 5.5h |

**Total: ~46.5h (~6 dev-days).** Ship HF-1..HF-6 in the first window (the live, remotely-reachable money/PII holes); HF-7..HF-11 the same week. None of these block or conflict with P1's engine work — they are pure guards, indexes, and scoping clauses on the existing code.

**One caveat:** these hotfixes *contain* the live exploits; they do **not** remove the class of bug. The structural fixes (per-company purpose-code registry for HF-6, `company_id` on the invoice pipeline + `BelongsToCompany` for HF-1/7/8, the PostingService idempotency-key for HF-4/5) land in P1/P2/P4 of [11-technical-implementation-plan.md](11-technical-implementation-plan.md). Do not treat P0 as a substitute for them.
