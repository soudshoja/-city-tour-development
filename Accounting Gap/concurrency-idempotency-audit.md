# Concurrency & Idempotency Audit — Financial Code Paths (citytourv2)

**Audit date:** 2026-07-07
**Codebase:** citytourv2-main (read-only checkout)
**Scope:** Payment gateway webhooks/callbacks, DB-transaction wrapping of multi-write financial ops, balance/aggregate read-modify-write races, queued-job idempotency, number-series generation.
**Verdict:** This is accounting software with **multiple confirmed double-post / double-spend / lost-update defects**. The dominant root cause is a **non-atomic check-then-act on `payments.status`** in every gateway callback, combined with a **non-idempotent shared posting routine** (`createInvoicePaymentCOA` / `addCredit`) and **no DB-level idempotency keys** on the money tables. Concurrent gateway retries, browser refreshes of return URLs, and the webhook-vs-return-URL race can each cause the ledger to be posted twice.

---

## Summary table

| # | Finding | Severity | File:line |
|---|---------|----------|-----------|
| F1 | Gateway callback/webhook double-post: unlocked `status`-check-then-act (all 6 gateways) | **Critical** | `PaymentController.php` 4900/4903, 4040/4064, 4317/4326, 5084/5094, 5412/5418, 5775/5784, 7347/7354 |
| F2 | `createInvoicePaymentCOA` is non-idempotent (unconditional Transaction + 3 JournalEntries) — the amplifier that turns F1 into double money | **Critical** | `PaymentController.php` 6082–6269 |
| F3 | Credit double-spend: check-then-act on unlocked `SUM(amount)` balance in payment application | **Critical** | `PaymentApplicationService.php` 175/195/236; `Credit.php` 88–90 |
| F4 | `RefundController::completeProcess` — no `DB::transaction`, no idempotency guard → double credit + partial post | **Critical** | `RefundController.php` 1283–1447 |
| F5 | Account balance lost update: `actual_balance += ...; save()` (non-atomic RMW) under concurrency | **High** | `PaymentController.php` 6224–6225, 6247–6248; `CreditController.php` 212–213, 242–243 |
| F6 | `payments.voucher_number` number-series race — unlocked read-increment **and no UNIQUE index** (silent duplicate) | **High** | `PaymentController.php` 1129–1133, 2505–2508, 6641–6745 |
| F7 | `addCredit` splits Credit insert and its JournalEntries into two separate transactions → partial write | **High** | `ClientController.php` 1063–1079 vs 1082+ |
| F8 | Payment application has no re-apply idempotency (no unique key on `payment_applications`) | **High** | `PaymentApplicationService.php` 166–288 |
| F9 | `CreateBulkInvoicesJob` re-posts full invoice set on retry-after-commit (no status/row guard) | **High** | `CreateBulkInvoicesJob.php` 71–176 |
| F10 | `RefundController::store` — unlocked `refund_number` sequence + no task-level dedup → double refund | **High** | `RefundController.php` 507–509, 490–537 |
| F11 | `CreditController::creditTopup` — account balance lost update + no submit idempotency | **High** | `CreditController.php` 178–243 |
| F12 | `handleHesabeResponse` flips `payment.status='completed'` and saves **outside** the money transaction → partial post on rollback | **Medium** | `PaymentController.php` 5427–5432 vs 5514 |
| F13 | Refund invoice-number generation unlocked → concurrent refunds spuriously roll back | **Medium** | `RefundController.php` 1501–1503, 1965–1967 |
| F14 | `deleteRefundClient` — unlocked `client.credit += ...` RMW with no transaction spanning credit-reversal + delete | **Medium** | `RefundController.php` 1472–1480 |

Severity counts: **Critical = 4, High = 7, Medium = 3.**

Reference implementation to copy everywhere: `CreateBulkInvoicesJob::generateInvoiceNumber` (lines 260–282) is the ONE correct pattern — `InvoiceSequence::...->lockForUpdate()->first()` inside `DB::transaction`. Findings F6, F13, and the InvoiceController/OpenAiController/MobileController voucher paths are the same code without that lock.

---

## Cross-cutting root cause (read this first)

Every gateway callback follows this shape:

```php
$payment = Payment::where(...)->first();          // (a) NON-LOCKING read
if ($payment->status === 'completed') { return; } // (b) idempotency guard = a plain read
// ... later ...
DB::transaction(function () {                      // (c) atomic, but NOT exclusive
    $payment->status = 'completed'; $payment->save();
    $this->createInvoicePaymentCOA(...);           // unconditional Transaction + 3 JournalEntries
    // or: $clientController->addCredit($payment); // unconditional Credit
});
```

Under MySQL's default **REPEATABLE READ**, the `SELECT` at (a) is a **snapshot read** — it does **not** block and does **not** see another transaction's uncommitted `status='completed'`. So two concurrent deliveries **both** pass guard (b), **both** enter (c), and **both** post to the ledger. `DB::transaction` gives atomicity (all-or-nothing per request) but **not** mutual exclusion between requests. There is no `SELECT ... FOR UPDATE`, no `Cache::lock` (except one gateway — see F1), and **no unique constraint on any gateway transaction id, `payment_id`, or `voucher_number`** to backstop it at the database.

The realistic triggers are not exotic:
1. **Gateway retry** — every gateway retries the webhook on timeout/non-2xx; `handleWebhookFatoorah` even `return 500` on a processing error (line 4920), guaranteeing a retry.
2. **User refresh** of the return URL (`hesabe-callback`, `knet-response`, `tap-callback`, `myfatoorah callback` are all `GET` redirect targets).
3. **Webhook vs return-URL race** — the gateway calls the server webhook AND redirects the user's browser at the same time. These run in two separate PHP processes that share no lock and each independently posts (e.g. Hesabe: `handleHesabeResponse` F1/#3 vs `handleHesabeWebhook` F1/#4).

---

## SURFACE 1 — Payment gateway webhooks / callbacks (idempotency)

### F1 (Critical) — Unlocked status-check-then-act in all six gateway handlers

Every handler reads the `Payment` row without `lockForUpdate` and uses `if ($payment->status === '...')` as its only idempotency guard.

| Gateway | Handler | Non-locking read | Guard | Money write |
|---------|---------|------------------|-------|-------------|
| MyFatoorah (webhook) | `handleWebhookFatoorah` | 4900 | 4903 `=== 'initiate'` | `processMyFatoorahPaymentCompletion` → COA 5026 / addCredit 5000 |
| MyFatoorah (return) | `handleMyFatoorahCallback` | 3867 | 3898 `=== 'completed'` | same completion 3915 |
| Tap | `handleTapCallback` | 4040 | 4064 `=== 'completed'` | TapPayment 4129 / addCredit 4157 / COA 4181 |
| KNET | `handleKnetResponse` | 4317 | 4326 `=== 'completed'` | addCredit 4423 / partials+COA 4457 |
| UPayment | `handleUPaymentCallback` | 5084 | 5094 `=== 'completed'` | addCredit 5208 / COA 5257 |
| Hesabe (return) | `handleHesabeResponse` | 5412 | 5418 `=== 'completed'` | addCredit 5540 / COA 5576 |
| Hesabe (webhook) | `handleHesabeWebhook` | 5775 | 5784 `=== 'completed'` | addCredit 5895 / COA 5933 |
| Manual "check status" | `checkTransactionStatus` | 7347 | 7354 / 7358 / 7443 | `processCompleted*` helpers (own txns) → addCredit / COA |

Representative excerpt (Tap — the worst, because it has **no** lock of any kind):

```php
// PaymentController.php
4040  $payment = Payment::with([...])->find($paymentId);   // no lockForUpdate
4064  if ($payment->status === 'completed') { ... return; } // guard = plain read
4120  DB::transaction(function () use ($payment, ...) {
4148     $payment->status = 'completed'; $payment->save();
4157     $addCreditResponse = $clientController->addCredit($payment);       // topup → double credit
4181     $coaResult = $this->createInvoicePaymentCOA(...);                 // invoice → dup Transaction+JEs
4195  });
```

**Failure interleaving (Tap invoice payment, concurrent user-refresh + gateway retry):**
- T0: Request A `find($paymentId)` → snapshot: `status='pending'`. Request B `find($paymentId)` → snapshot: `status='pending'`.
- T1: A passes guard 4064, enters txn 4120, creates `TapPayment`, sets `status='completed'`, calls `createInvoicePaymentCOA` → 1 Transaction + 3 JournalEntries + `gateway_asset.actual_balance += net`, commits.
- T2: B passes guard 4064 (its snapshot still shows `pending`), enters txn 4120, creates a **second** `TapPayment`, calls `createInvoicePaymentCOA` **again** → a **second** Transaction + **3 more** JournalEntries + `actual_balance` incremented a **second** time.
- Result: invoice paid once, ledger credited twice. `tap_payments` has no unique key on `payment_id`/`tap_id`, so the DB does not stop it.

For **topup** the same interleaving calls `addCredit` twice → client credited twice (mitigated only by F7's soft `exists()` check, which is itself racy).

**Partial mitigation that exists:** `handleMyFatoorahCallback` alone wraps its body in `Cache::lock('mf:callback:'.$paymentId, 40)` (line 3842). This dedups callback-vs-callback for MyFatoorah **only** — it does NOT coordinate with `handleWebhookFatoorah` (which takes no lock), so the MyFatoorah webhook-vs-return-URL race is still open. No other gateway has any lock.

**Fix (applies to all handlers):** Load the payment with a row lock *inside* the same transaction that flips status and posts money, then re-check status after acquiring the lock:

```php
DB::transaction(function () use ($paymentId, ...) {
    $payment = Payment::whereKey($paymentId)->lockForUpdate()->firstOrFail();
    if ($payment->status === 'completed') return; // second caller blocks here, then no-ops
    $payment->status = 'completed'; $payment->save();
    // ... createInvoicePaymentCOA / addCredit ...
});
```

Defense-in-depth: add a UNIQUE index on the gateway-transaction dedup key (e.g. `myfatoorah_payments.payment_int_id`, `tap_payments.tap_id`, `upayments_payments.track_id`, `hesabe_payments.payment_int_id`) and on `transactions(payment_id, reference_type)` for gateway-payment transactions, and catch the violation.

### F2 (Critical) — `createInvoicePaymentCOA` is non-idempotent (the amplifier)

`PaymentController.php` 6082–6269. The shared invoice-payment posting routine has **no existence check** for a prior posting of the same payment. It unconditionally:

```php
6166  $transaction = Transaction::create([... 'payment_id' => $payment->id ...]);
6188  JournalEntry::create([... 'credit' => $finalPaidAmount ...]);   // receivable
6206  JournalEntry::create([... 'debit'  => $netAmount ...]);         // bank asset
6224  $gatewayAssetAccount->actual_balance += $netAmount; $gatewayAssetAccount->save();
6229  JournalEntry::create([... 'debit'  => $accountingFee ...]);     // gateway fee
6247  $gatewayExpenseAccount->actual_balance += $accountingFee; $gatewayExpenseAccount->save();
```

Because it is called from every F1 handler and has no guard, any double-fire that reaches it posts the full set again. This is what converts the F1 TOCTOU window into actual double-counted money. (Note it IS wrapped in its own `DB::transaction` at 6082, so a single call is atomic — the defect is idempotency, not atomicity.)

**Fix:** Make it idempotent — at entry, `if (Transaction::where('payment_id',$payment->id)->where('reference_type','Invoice')->exists()) return existing;`. Better, gate on the locked payment status from F1 so it can never be entered twice.

---

## SURFACE 2 — DB-transaction wrapping of multi-write operations

Operations that are **NOT** fully wrapped (partial-write risk on crash / mid-way exception):

### F7 (High) — `addCredit` splits one logical operation across two transactions
`ClientController.php`. The Credit ledger row is committed in its own transaction, then the balancing JournalEntries are opened in a *separate* transaction:

```php
1063  DB::beginTransaction();
1065     Credit::create([... 'amount' => $clientCreditAmount ...]);   // client balance +
1079  DB::commit();                                                   // <-- committed alone
1082  DB::beginTransaction();                                          // separate txn
        // ... Transaction + JournalEntry rows (the double-entry) ...
```

If the process dies or the second block throws between line 1079 and the second commit, the client's credit balance is increased with **no corresponding journal entries** — an unbalanced ledger (money on the client side, nothing on the asset/liability side).

**Fix:** One `DB::transaction` spanning both the Credit insert and all JournalEntry inserts.

### F12 (Medium) — `handleHesabeResponse` posts payment status outside the money transaction
`PaymentController.php` 5427–5432 set `payment->status='completed'`, `payment_reference`, `service_charge` and `save()` **before** `DB::beginTransaction()` at 5514 (where addCredit/COA run). If the money transaction rolls back at 5616, the payment is left marked `completed` with **no** credit/journal entries — and F1's guard (`status==='completed'`) will then permanently suppress any retry. Silent lost payment.

**Fix:** Move the `payment->save()` inside the same transaction as addCredit/COA.

### F4 (Critical) — `RefundController::completeProcess` has no transaction at all
`RefundController.php` 1283–1447. See SURFACE 3 below — it creates `Transaction` + two `JournalEntry` + `Credit` **per detail in a loop with no `DB::transaction`**. If detail #2 throws, detail #1's postings are already committed. Partial financial posting with no rollback path (the `catch` at 1439 has nothing to roll back).

### F14 (Medium) — `deleteRefundClient` credit-reversal + delete not atomic
`RefundController.php` 1472–1480: `client.credit += amount; save()` then `refundClient->delete()` with no wrapping transaction. A crash between the two leaves the credit reversed without the record deleted (or the delete without the reversal).

**Other financial writes are correctly wrapped** — e.g. `InvoiceController::store`-family uses `DB::transaction` + `lockForUpdate` on the invoice (line 941/960), `createInvoicePaymentCOA` (6082), `PaymentApplicationService::applyPaymentsToInvoice` (`beginTransaction` at 110), the KNET/Tap/UPayment/Hesabe-webhook money blocks are each in a transaction (the defect there is the *missing lock*, not the missing transaction).

---

## SURFACE 3 — Balance / aggregate read-modify-write races

### F3 (Critical) — Credit double-spend: two invoices settle from one credit pool
`PaymentApplicationService.php` 166–288, balance from `Credit.php:88-90`.

```php
175  $availableBalance = Credit::getAvailableBalanceByPayment($sourceCredit->payment_id); // SUM(amount), NO lock
195  if ($availableBalance < $requestedAmount) { throw ... }                              // the check
236  $credit = Credit::create([... 'amount' => -$applyFromThis ...]);                     // the deduct
```
`getAvailableBalanceByPayment` = `self::where('payment_id',$id)->sum('amount')` — no `lockForUpdate`.

**Interleaving:** Client has one TOPUP credit = 100 KWD. Two `applyPaymentsToInvoice` calls apply that same credit to two different invoices concurrently. Both are inside `DB::beginTransaction` (line 110), but under REPEATABLE READ the two `SUM` reads do not block each other and the two INSERTs are different rows, so nothing conflicts. Both read `100`, both pass `100 >= 100`, both insert a `-100` Credit + COA. Pool balance becomes **-100**: 200 KWD of invoices settled from 100 KWD of credit. Same class for REFUND credits (`getAvailableBalanceByRefund`, line 178 / model 93–95).

**Fix:** Lock the credit pool before the balance read so the second txn serializes:
```php
Credit::where('payment_id',$sourceCredit->payment_id)->lockForUpdate()->get(); // acquire lock
$availableBalance = Credit::getAvailableBalanceByPayment($sourceCredit->payment_id);
```
or lock the parent `Payment`/`Refund` row as the serialization point. Add a post-write assertion (or DB trigger) that the pool sum can never go negative.

### F5 (High) — Account balance lost update: `actual_balance += ...; save()`
`PaymentController.php` 6224–6225 and 6247–6248 (inside `createInvoicePaymentCOA`); same pattern in `CreditController.php` 212–213, 242–243.

```php
6224  $gatewayAssetAccount->actual_balance += $netAmount;
6225  $gatewayAssetAccount->save();
```

Two payments completing concurrently to the **same** gateway asset account each read `actual_balance = B` (stale in-memory), each compute `B + net`, each `save()`. One update is lost → the account's `actual_balance` (and the `balance` snapshot copied into each JournalEntry, e.g. line 6217) drifts permanently below the true total. Ledger integrity corruption independent of F1.

**Fix:** Replace the RMW with an atomic DB expression — `$account->increment('actual_balance', $netAmount)` / `->decrement(...)` — or `lockForUpdate` the account row at the top of the transaction before reading `actual_balance`.

### F11 (High) — `CreditController::creditTopup` account balance lost update
`CreditController.php` 178–243. `paymentGateway->actual_balance -= amount; save()` (212–213) and `clientReceivable->actual_balance += amount; save()` (242–243), wrapped in a transaction (141) but with no lock. Same lost-update as F5; additionally no idempotency guard, so a double-submit posts two topups + two credits.

**Fix:** atomic `increment`/`decrement` (or `lockForUpdate`), plus an idempotency key/guard on the topup action.

### F14 (Medium) — `deleteRefundClient` `client.credit` RMW
`RefundController.php` 1473: `client.credit += amount; save()` — unlocked RMW (also the atomicity issue noted in Surface 2). **Fix:** `$client->increment('credit', $amount)` inside a transaction.

---

## SURFACE 4 — Job / queue idempotency

### F9 (High, Critical potential) — `CreateBulkInvoicesJob` double-posts on retry-after-commit
`CreateBulkInvoicesJob.php` 71–176. `$tries = 3` (45), `$timeout = 300` (59).

```php
74   $bulkInvoice = BulkInvoice::with('rows')->findOrFail(...);        // NO status guard at entry
     DB::transaction(function () use ($bulkInvoice) {
92      foreach ($bulkInvoice->rows()->where('status','valid')->get() as $row) { // rows never advanced
           // ... create Invoice + InvoiceDetail + applyPaymentsToInvoice ...
        }
153     $bulkInvoice->update(['status' => 'completed']);              // only the HEADER flips
     });
166  SendInvoiceEmailsJob::dispatchSync($this->bulkInvoiceId);        // runs AFTER commit, synchronously
```

The number generator inside is correct (`generateInvoiceNumber` uses `InvoiceSequence::...->lockForUpdate()` at 263–265 in the transaction). The defect is **retry idempotency**: after the transaction commits, the synchronous `dispatchSync` (166) can hit the `$timeout=300` alarm, or the worker can be SIGKILLed/redeployed before acking, or `dispatchSync` can throw a `\Throwable` that the `\Exception`-only catch (170) misses. The queue then retries `handle()`, which has **no `status==='completed'` guard**, and the per-row `status` is still `'valid'` (only the header flipped). So it re-creates the **entire** invoice set again — double invoices + double credit application per row (compounding F3/F8). `failed()` (291) only marks the header failed and does not undo committed duplicates.

**Fix:** Idempotency guard at entry (`if (in_array($bulkInvoice->status,['completed','failed'])) return;`); advance each row inside the transaction (`$row->update(['status'=>'invoiced'])`) so a retry re-selects only unprocessed rows; move the email dispatch to a post-commit `afterCommit` hook or async `->dispatch()` so a post-commit failure cannot retry the whole job.

**Other jobs are clean:** grepping `app/Jobs`, only `CreateBulkInvoicesJob` writes ledger records. `SendInvoiceEmailsJob`, the `Sync*` jobs, and `UpdateMagicHolidayIssuedDateJob` do not touch money. `Console/Commands/BackfillMyFatoorahPayments` is **safe to re-run** — it guards `if (!$mfPayment) MyFatoorahPayment::create(...)` and only back-fills fields, posting no ledger entries.

---

## SURFACE 5 — Number-series races

### F6 (High) — `payments.voucher_number` race with NO unique index (silent duplicate)
`PaymentController.php` — the pattern repeats at 1129–1133 (`initiatePayment`), 2505–2508, 2913–2930, 6641–6745:

```php
1129  $voucherSequence = Sequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
1130  $currentSequence = $voucherSequence->current_sequence;   // read
1131  $voucherNumber   = $this->generateVoucherNumber($currentSequence);
1132  $voucherSequence->current_sequence++;                    // modify
1133  $voucherSequence->save();                                // write — NO lock, NO surrounding txn
```

No `lockForUpdate` and no transaction. Two concurrent payment initiations read the same `current_sequence` → identical `VOU-YYYY-NNNNN`. **Unlike `invoice_number` and `refund_number`, `payments.voucher_number` has NO UNIQUE index** (verified: migrations create unique on `invoices.invoice_number` and `refunds.refund_number` only). So the collision is **silent** — two Payment rows share one voucher number, and every downstream lookup keyed on `voucher_number` (e.g. `handleHesabeResponse` 5412, `handleHesabeWebhook` 5775, the callback `orWhere('voucher_number', ...)` at 3867) can now resolve to the **wrong** payment, misapplying a gateway result to a different client's payment.

**Fix:** `Sequence::where('company_id',$companyId)->lockForUpdate()->first()` inside `DB::transaction` (copy `CreateBulkInvoicesJob::generateInvoiceNumber`). Add a UNIQUE index on `payments(company_id, voucher_number)` as a hard backstop and to make the misrouting impossible.

### F10 (High) — `RefundController::store` unlocked `refund_number` + no task dedup
`RefundController.php` 490–537:

```php
507  $refundSequence = RefundSequence::firstOrCreate(['company_id'=>...],['current_sequence'=>1]); // NO lock
508  $refundNumber   = $this->generateRefundNumber($refundSequence->current_sequence);
509  $refundSequence->increment('current_sequence');
521  $refund = Refund::create(['refund_number' => $refundNumber, ...]);
```

Two parts:
- **Number race (Medium):** unlocked read-increment; the UNIQUE index on `refund_number` converts a collision into a rolled-back transaction (one refund fails with "Refund failed", sequence gap). Availability, not corruption.
- **No task-level dedup (High):** `store()` never re-checks whether the submitted tasks are already refunded (that check lives only in the `create()` GET form, line 156). A slightly-staggered double-submit gets **different** refund_numbers, so the UNIQUE index does **not** catch it → two `Refund` records for the same tasks, and for a paid invoice each `handlePaidRefund` does `Credit::create` (≈ line 794) → **client credited twice**.

**Fix:** `RefundSequence::where('company_id',$id)->lockForUpdate()->first()`; and inside the transaction re-assert (with a lock) that none of `tasks.*.task_id` already has a `RefundDetail`, or add a unique index on `refund_details(task_id)` and catch the violation.

### F13 (Medium) — Refund invoice-number generation unlocked
`RefundController.php` 1501–1503 (`createRefundInvoiceUnpaid`) and 1965–1967 (`createRefundInvoicePartial`): the same unlocked `InvoiceSequence::firstOrCreate → read → increment` for `invoice_number`. Backstopped by the `invoice_number` UNIQUE index, but because these run inside `store()`'s transaction, a collision rolls back the **entire refund**, not just the number. This unlocked pattern is systemic (also `InvoiceController.php` ~371 and ~5967; `MobileController.php` 264 and `OpenAiController.php` 1024 use the lock correctly). **Fix:** `InvoiceSequence::where('company_id',$companyId)->lockForUpdate()->first()`.

---

## Prioritized remediation

1. **F1 + F2 (Critical, same fix)** — Refactor every gateway handler to `Payment::whereKey($id)->lockForUpdate()->firstOrFail()` inside the money transaction with a post-lock `status==='completed'` re-check; make `createInvoicePaymentCOA` and `addCredit` idempotent (existence check on prior Transaction/Credit). Add UNIQUE indexes on gateway-transaction dedup keys. This closes the entire double-post family including `checkTransactionStatus` and the webhook-vs-return-URL race.
2. **F3 (Critical)** — `lockForUpdate` the credit pool before the availability check in `PaymentApplicationService`.
3. **F4 (Critical)** — Wrap `completeProcess` in `DB::transaction` + top-of-method `lockForUpdate` + `status==='completed'` guard.
4. **F5 / F11 / F14 (High/Med)** — Replace all `actual_balance`/`client.credit` `+= ... ; save()` with atomic `increment`/`decrement` (or `lockForUpdate`).
5. **F6 (High)** — Lock the `Sequence` read AND add a UNIQUE index on `payments(company_id, voucher_number)` (silent-duplicate + payment-misrouting risk).
6. **F7 / F12 (High/Med)** — Merge split transactions so ledger rows and their balance/status writes commit together.
7. **F8 / F9 (High)** — Add unique key on `payment_applications(invoice_id, credit_id)`; add entry guard + per-row status advance + afterCommit email dispatch in `CreateBulkInvoicesJob`.
8. **F10 / F13 (High/Med)** — Lock refund/invoice sequences and add task-level refund dedup.

**Systemic recommendation:** these bugs share two root causes — (1) `check-then-act` without `lockForUpdate`, and (2) no DB-level idempotency keys on money tables. Adding UNIQUE constraints (gateway txn ids, `voucher_number`, `payment_applications`, `refund_details.task_id`) is the cheapest structural backstop; converting every balance RMW to atomic `increment`/`decrement` and every callback to a locked-read is the correctness fix.
