# PaymentApplicationService Complete Analysis

## File Location
`/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php` (786 lines)

## Class Overview

**Purpose**: The central service for applying client credit balances to invoices. It handles three payment modes (full, partial, split), creates all necessary records (InvoicePartial, Credit deductions, PaymentApplication audit trail), and generates Chart of Accounts (COA) journal entries for double-entry bookkeeping.

**Dependencies (imported)**:
- `App\Models\Credit` - Credit balance ledger (topups and deductions)
- `App\Models\Invoice` - The invoice being paid
- `App\Models\InvoicePartial` - Per-payment-source line items on an invoice
- `App\Models\Payment` - Original topup payment records
- `App\Models\PaymentApplication` - Audit trail linking payments to invoices
- `App\Models\Account` - Chart of Accounts tree
- `App\Models\Transaction` - Accounting transactions
- `App\Models\JournalEntry` - Double-entry journal entries
- `Illuminate\Support\Facades\DB` - Database transactions
- `Illuminate\Support\Facades\Auth` - Current user tracking
- `Illuminate\Support\Facades\Log` - Extensive logging

**Injected Services**: None. The class is stateless and instantiated directly via `new PaymentApplicationService()` or `app(PaymentApplicationService::class)`.

---

## Main Methods

### 1. `applyPaymentsToInvoice()` -- Primary Method

**Signature**:
```php
public function applyPaymentsToInvoice(
    int $invoiceId,
    array $paymentAllocations,
    string $paymentMode = 'full',
    array $options = []
): array
```

**Parameters Explained**:
- `$invoiceId` (int): The ID of the invoice to pay. Fetched via `Invoice::findOrFail()`.
- `$paymentAllocations` (array): Array of allocations, each element is `['credit_id' => X, 'amount' => Y]`. The `credit_id` points to a Credit record of type `Topup` or `Refund` (the positive/source credit). The `amount` is how much to deduct from that source.
- `$paymentMode` (string): One of `'full'`, `'partial'`, or `'split'`. Defaults to `'full'`. Controls validation logic and resulting invoice status.
- `$options` (array): Only relevant for `split` mode. Contains:
  - `'other_gateway'` (string) - gateway name for the remaining amount (e.g., "Knet", "Cash")
  - `'other_method'` (string) - payment method name
  - `'charge_id'` (int) - Charge record ID for the other gateway

**Return Value**: Always returns an associative array:
```php
// Success:
[
    'success' => true,
    'message' => "Successfully paid invoice...",
    'payment_mode' => 'full',
    'credit_applied' => 500.000,
    'remaining_amount' => 0.000,
    'applied_payments' => [...],
    'invoice_status' => 'paid',
    'invoice_partials_created' => 2,
]

// Failure:
[
    'success' => false,
    'message' => "Insufficient credit selected...",
    'shortfall' => 50.000,  // optional
]
```

**Step-by-Step Logic**:

1. **Log the request** (invoice_id, allocations, mode, user_id)
2. **Load the invoice** via `Invoice::findOrFail($invoiceId)`
3. **Calculate total credit selected**: `array_sum(array_column($paymentAllocations, 'amount'))`
4. **Validate based on payment mode**:
   - **Full**: `totalCreditSelected` must be >= `invoiceAmount`. Otherwise returns error with `shortfall`.
   - **Split**: `totalCreditSelected` must be > 0, must be < `invoiceAmount` (otherwise use full mode), and `options['other_gateway']` must be provided.
   - **Partial**: `totalCreditSelected` must be > 0 and < `invoiceAmount`.
5. **Begin DB transaction**
6. **STEP 1A -- Ensure Invoice Generation COA exists**: Checks `Transaction` table for existing invoice-generation entry. If none exists, creates a `Transaction` record and calls `InvoiceController->addJournalEntry()` for each invoice detail/task to create the DEBIT Receivable / CREDIT Revenue entries.
7. **STEP 1B -- Process each payment allocation** (loop):
   - Load the source `Credit` record
   - Determine credit type (Topup or Refund)
   - Calculate available balance via `Credit::getAvailableBalanceByPayment()` or `Credit::getAvailableBalanceByRefund()`
   - Validate: available balance must be >= requested amount
   - Calculate actual amount to apply: `min(requestedAmount, remainingToApply)`
   - Calculate proportional gateway fee: `sourcePayment.gateway_fee * (applyFromThis / sourcePayment.amount)`
   - **Create InvoicePartial** (status = 'paid', gateway = 'Credit')
   - **Create Credit record** (negative amount = deduction from balance)
   - **Create PaymentApplication record** (audit trail)
   - Accumulate to `$appliedPayments` array
   - Reduce `$remainingToApply`
8. **STEP 2 -- Create Credit Payment COA**: Calls `createCreditPaymentCOA()` with all applied payments
9. **STEP 3 -- Update invoice status** based on mode:
   - **Full**: `status = 'paid'`, `paid_date = now()`, `payment_type = 'credit'`
   - **Split**: Creates an additional `InvoicePartial` (status = 'unpaid') for the remaining amount with the other gateway. Sets `status = 'partial'`, `payment_type = 'split'`
   - **Partial**: Sets `status = 'partial'`, `payment_type = 'partial'`
10. **Commit transaction**
11. **Return success response** with detailed breakdown
12. **On exception**: Rollback and return error

**Actual Code Excerpt** (core loop, lines 166-281):
```php
foreach ($paymentAllocations as $allocation) {
    if ($remainingToApply <= 0) break;

    $creditId = $allocation['credit_id'];
    $requestedAmount = $allocation['amount'];

    $sourceCredit = Credit::findOrFail($creditId);

    if ($sourceCredit->type === Credit::TOPUP) {
        $availableBalance = Credit::getAvailableBalanceByPayment($sourceCredit->payment_id);
        $voucherNumber = $sourceCredit->payment?->voucher_number ?? 'TOPUP';
    } elseif ($sourceCredit->type === Credit::REFUND) {
        $availableBalance = Credit::getAvailableBalanceByRefund($sourceCredit->refund_id);
        $voucherNumber = $sourceCredit->refund?->refund_number ?? ('RF-' . $sourceCredit->refund_id);
    } else {
        throw new Exception("This credit type cannot be used: {$sourceCredit->type}");
    }

    // ... balance validation ...

    $applyFromThis = min($requestedAmount, $remainingToApply);

    // Proportional gateway fee
    $proportionalFee = 0;
    if ($sourceCredit->payment_id) {
        $sourcePayment = $sourceCredit->payment;
        if ($sourcePayment && $sourcePayment->amount > 0 && $sourcePayment->gateway_fee > 0) {
            $proportionalFee = round(
                $sourcePayment->gateway_fee * ($applyFromThis / $sourcePayment->amount), 3
            );
        }
    }

    // Create InvoicePartial
    $invoicePartial = InvoicePartial::create([
        'invoice_id' => $invoice->id,
        'invoice_number' => $invoice->invoice_number,
        'client_id' => $invoice->client_id,
        'agent_id' => $invoice->agent_id,
        'gateway_fee' => $proportionalFee,
        'amount' => $applyFromThis,
        'status' => 'paid',
        'type' => $paymentMode,
        'payment_gateway' => 'Credit',
        'payment_method' => 'Credit Balance',
        'service_charge' => 0,
    ]);

    // Create Credit deduction (negative)
    $credit = Credit::create([
        'company_id' => $invoice->agent?->branch?->company_id,
        'branch_id' => $invoice->agent?->branch_id,
        'client_id' => $invoice->client_id,
        'payment_id' => $sourceCredit->payment_id,
        'refund_id'  => $sourceCredit->refund_id,
        'invoice_id' => $invoiceId,
        'invoice_partial_id' => $invoicePartial->id,
        'type' => Credit::INVOICE,
        'amount' => -$applyFromThis,
        'gateway_fee' => $proportionalFee,
        'description' => "Payment for {$invoice->invoice_number} via {$voucherNumber}",
    ]);

    // Create PaymentApplication audit record
    PaymentApplication::create([
        'payment_id' => $sourceCredit->payment_id,
        'credit_id' => $sourceCredit->id,
        'invoice_id' => $invoiceId,
        'invoice_partial_id' => $invoicePartial->id,
        'amount' => $applyFromThis,
        'applied_by' => Auth::id(),
        'applied_at' => now(),
        'notes' => "Applied from {$voucherNumber} ({$paymentMode} payment)",
    ]);

    $remainingToApply -= $applyFromThis;
}
```

---

### 2. `linkPaymentsToInvoicePartial()` -- Secondary Method

**Signature**:
```php
public function linkPaymentsToInvoicePartial(
    Invoice $invoice,
    InvoicePartial $invoicePartial,
    array $paymentAllocations
): array
```

**Purpose**: Used when the InvoicePartial has **already been created** by the existing invoice creation flow (in `InvoiceController@store`). This method only creates the Credit deduction and PaymentApplication audit trail records -- it does NOT create InvoicePartials or update invoice status.

**When Called**: From `InvoiceController` (line 940) during the existing partial-payment flow where the controller creates the InvoicePartial first, then calls this to link specific credit sources.

**Key Differences from `applyPaymentsToInvoice()`**:
- Does NOT create InvoicePartials (they already exist)
- Does NOT update invoice status
- Does NOT create COA journal entries (the controller handles that separately)
- Does NOT wrap in its own DB transaction (caller manages transactions)
- Supports both `credit_id` and `payment_id` in allocations (dual format)

**Return Value**:
```php
[
    'success' => true,
    'applied_payments' => [...],
    'total_applied' => 500.000,
]
```

---

### 3. `getAvailablePaymentsForClient()`

**Signature**: `public function getAvailablePaymentsForClient(int $clientId): array`

**Purpose**: Thin wrapper around `Credit::getAvailablePaymentsForClient()`. Returns all credit sources (topups + refunds) for a client that still have available balance, sorted FIFO (oldest first).

---

### 4. `validatePaymentSelection()`

**Signature**: `public function validatePaymentSelection(array $paymentAllocations, float $requiredAmount): array`

**Purpose**: Pre-flight validation before actually applying payments. Checks each allocation's available balance and calculates total. Returns validation result with issues list.

**Return Value**:
```php
[
    'valid' => true/false,
    'total_selected' => 500.000,
    'required_amount' => 500.000,
    'shortfall' => 0.000,
    'excess' => 0.000,
    'issues' => [],
]
```

---

### 5. `getPaymentHistoryForInvoice()` / `getInvoiceHistoryForPayment()`

Simple wrappers around `PaymentApplication::getApplicationsForInvoice()` and `PaymentApplication::getApplicationsFromPayment()`. Used for displaying audit trails.

---

### 6. `createCreditPaymentCOA()` -- Protected Accounting Method

**Signature**:
```php
protected function createCreditPaymentCOA($invoice, array $appliedPayments, float $totalAmount)
```

**Purpose**: Creates double-entry journal entries when paying an invoice with client credit.

**Creates**:
1. **One Transaction record** (type: 'debit', reference_type: 'Payment')
2. **Multiple DEBIT JournalEntries** (one per credit source/voucher used) -- to **Liabilities > Advances > Client > Payment Gateway** account. This clears the advance/credit liability held for the client.
3. **One CREDIT JournalEntry** (total amount) -- to **Accounts Receivable > Clients** account. This clears the invoice debt.

**Account Tree Navigation**:
```
Debit Side:
  Liabilities (root, parent_id=NULL)
    -> Advances (parent_id = Liabilities.id)
      -> Client (parent_id = Advances.id)
        -> Payment Gateway (parent_id = Client.id) <-- DEBIT HERE

Credit Side:
  Accounts Receivable (root)
    -> Clients (parent_id = AccountsReceivable.id) <-- CREDIT HERE
```

---

## Payment Selection Logic

### How Payments are Found

The system does NOT automatically select payments. The user manually selects which credit sources to use via the UI. The `getAvailablePaymentsForClient()` method provides the available options.

**Available balance calculation** (from `Credit` model):
```php
// For topup-based credits:
Credit::getAvailableBalanceByPayment($paymentId)
// = SUM(amount) WHERE payment_id = $paymentId
// Topups are positive, Invoice deductions are negative
// So net sum = remaining balance

// For refund-based credits:
Credit::getAvailableBalanceByRefund($refundId)
// = SUM(amount) WHERE refund_id = $refundId
```

### FIFO Implementation

FIFO is implemented in `Credit::getAvailablePaymentsForClient()` (line 166-170 of Credit model):
```php
// Sort by payment date (FIFO - oldest first) to deduct from oldest payments first
usort($availablePayments, function ($a, $b) {
    $dateA = $a['date'];
    $dateB = $b['date'];
    return $dateA <=> $dateB;
});
```

**Important nuance**: The FIFO sort only determines the **display order** in the UI. The actual selection is manual -- the user picks which credits to use and how much from each. The system does not enforce FIFO consumption; it merely presents credits oldest-first to encourage FIFO usage.

### Available vs Used Payments

A credit source (topup or refund) is "available" when:
- `Credit::getAvailableBalanceByPayment($paymentId) > 0` (for topups)
- `Credit::getAvailableBalanceByRefund($refundId) > 0` (for refunds)

The balance is the **net sum** of all Credit records for that payment_id/refund_id:
- Topup credits have `amount > 0` (positive, money in)
- Invoice credits have `amount < 0` (negative, money out)
- When the net sum reaches 0, the topup is fully consumed

### Two Credit Source Types

1. **Topup** (`Credit::TOPUP`): Client made a payment (via gateway) that was stored as a credit balance. Has `payment_id` linking to the Payment record.
2. **Refund** (`Credit::REFUND`): Client received a refund that was stored as credit balance. Has `refund_id` linking to the Refund record.

Only these two types can be used as payment sources. `Credit::INVOICE` type records are the deductions (negative amounts) created when credit is used.

---

## Record Creation

### InvoicePartial

**When Created**: One InvoicePartial is created per credit source used in `applyPaymentsToInvoice()`. In split mode, an additional InvoicePartial is created for the remaining amount (with status 'unpaid').

**Fields Set**:
```php
InvoicePartial::create([
    'invoice_id'      => $invoice->id,
    'invoice_number'  => $invoice->invoice_number,
    'client_id'       => $invoice->client_id,
    'agent_id'        => $invoice->agent_id,
    'gateway_fee'     => $proportionalFee,    // proportional share of source payment's gateway fee
    'amount'          => $applyFromThis,       // amount applied from this source
    'status'          => 'paid',               // always 'paid' for credit portions
    'type'            => $paymentMode,         // 'full', 'partial', or 'split'
    'payment_gateway' => 'Credit',             // always 'Credit'
    'payment_method'  => 'Credit Balance',     // always 'Credit Balance'
    'service_charge'  => 0,                    // no service charge for credit payments
]);
```

**Split mode additional partial** (for remaining non-credit amount):
```php
InvoicePartial::create([
    'invoice_id'      => $invoice->id,
    'invoice_number'  => $invoice->invoice_number,
    'client_id'       => $invoice->client_id,
    'agent_id'        => $invoice->agent_id,
    'amount'          => $remainingAmount,     // invoiceAmount - creditApplied
    'status'          => 'unpaid',             // waiting for other gateway payment
    'type'            => 'split',
    'payment_gateway' => $options['other_gateway'],  // e.g., 'Knet', 'Cash'
    'payment_method'  => $options['other_method'],
    'service_charge'  => 0,
    'charge_id'       => $options['charge_id'],
]);
```

### Credit (Deduction Record)

**When Created**: One per credit source used. This is the negative-amount record that reduces the source's available balance.

```php
Credit::create([
    'company_id'          => $invoice->agent?->branch?->company_id,
    'branch_id'           => $invoice->agent?->branch_id,
    'client_id'           => $invoice->client_id,
    'payment_id'          => $sourceCredit->payment_id,   // links to source topup payment
    'refund_id'           => $sourceCredit->refund_id,    // links to source refund (if refund credit)
    'invoice_id'          => $invoiceId,                  // invoice being paid
    'invoice_partial_id'  => $invoicePartial->id,         // the InvoicePartial just created
    'type'                => Credit::INVOICE,             // always 'Invoice' for deductions
    'amount'              => -$applyFromThis,             // NEGATIVE = deduction
    'gateway_fee'         => $proportionalFee,
    'description'         => "Payment for INV-2025-00001 via VCH-001",
]);
```

### PaymentApplication

**When Created**: One per credit source used. This is the audit trail record.

```php
PaymentApplication::create([
    'payment_id'          => $sourceCredit->payment_id,   // source payment (null for refund credits)
    'credit_id'           => $sourceCredit->id,           // the source Credit record (Topup or Refund type)
    'invoice_id'          => $invoiceId,
    'invoice_partial_id'  => $invoicePartial->id,
    'amount'              => $applyFromThis,              // POSITIVE amount applied
    'applied_by'          => Auth::id(),                  // user who did this
    'applied_at'          => now(),
    'notes'               => "Applied from VCH-001 (full payment)",
]);
```

**PaymentApplication Table Schema** (from migration):
```
id                  - bigint PK
payment_id          - bigint nullable FK -> payments.id (nullable for refund credits)
credit_id           - bigint nullable FK -> credits.id
invoice_id          - bigint FK -> invoices.id
invoice_partial_id  - bigint nullable FK -> invoice_partials.id
amount              - decimal(15,3)
applied_by          - bigint nullable FK -> users.id
applied_at          - timestamp
notes               - text nullable
created_at          - timestamp
updated_at          - timestamp

Indexes: (payment_id, invoice_id), (invoice_partial_id)
```

---

## Status Update Logic

### `unpaid -> paid` (Full Mode)

```php
if ($paymentMode === 'full') {
    $invoice->status = 'paid';
    $invoice->paid_date = now();
    $invoice->payment_type = 'credit';
    $invoice->is_client_credit = true;
    $invoice->save();
}
```
**Condition**: Credit covers entire invoice amount. All InvoicePartials created with status='paid'.

### `unpaid -> partial` (Partial Mode)

```php
if ($paymentMode === 'partial') {
    $invoice->status = 'partial';
    $invoice->payment_type = 'partial';
    $invoice->is_client_credit = true;
    $invoice->save();
}
```
**Condition**: Credit covers only a portion. No second InvoicePartial created -- the remaining balance is implicit (invoiceAmount - creditApplied).

### `unpaid -> partial` (Split Mode)

```php
if ($paymentMode === 'split') {
    // Create unpaid partial for remaining amount
    $splitPartial = InvoicePartial::create([...status: 'unpaid'...]);

    $invoice->status = 'partial';
    $invoice->payment_type = 'split';
    $invoice->is_client_credit = true;
    $invoice->save();
}
```
**Condition**: Credit covers a portion, rest to be paid via another gateway. Creates an 'unpaid' InvoicePartial for the remaining amount.

### `partial -> paid`

This transition is NOT handled by `PaymentApplicationService`. It would happen when:
- The split mode's unpaid InvoicePartial gets paid via the other gateway (handled by `InvoiceController@store`)
- The `InvoiceController` checks: `$hasUnpaid = $invoice->invoicePartials()->where('status', 'unpaid')->exists()` and sets status accordingly (lines 1006-1024 of InvoiceController)

---

## Modes Explained

### Full Mode
- **What it does**: Pays the entire invoice using credit balance only.
- **Validation**: `totalCreditSelected >= invoiceAmount` (rejects if insufficient)
- **Records created per source**: 1 InvoicePartial (paid) + 1 Credit (negative) + 1 PaymentApplication
- **Invoice result**: status='paid', payment_type='credit'
- **When to use**: Client has enough credit balance to cover the full invoice

### Partial Mode
- **What it does**: Pays a portion of the invoice with credit, leaves the rest as an outstanding balance.
- **Validation**: `totalCreditSelected > 0` AND `totalCreditSelected < invoiceAmount`
- **Records created**: Same per-source records, but NO additional InvoicePartial for the remainder
- **Invoice result**: status='partial', payment_type='partial'
- **When to use**: Client wants to apply available credit now, pay the rest later (or it remains as receivable)
- **Key difference from split**: No explicit record for the remaining amount. The balance is just the difference.

### Split Mode
- **What it does**: Pays a portion with credit AND explicitly designates another gateway for the remainder.
- **Validation**: `totalCreditSelected > 0` AND `totalCreditSelected < invoiceAmount` AND `options['other_gateway']` required
- **Records created**: Same per-source records PLUS one additional InvoicePartial (status='unpaid') for the remaining amount with the other gateway
- **Invoice result**: status='partial', payment_type='split'
- **When to use**: Client pays part with credit, part with Knet/Cash/etc. The unpaid partial acts as a pending charge record.
- **Key difference from partial**: Creates an explicit InvoicePartial for the remaining amount with the target gateway.

---

## Accounting Integration (COA)

### Yes, this service creates COA entries directly.

The `createCreditPaymentCOA()` method (called inside `applyPaymentsToInvoice()`) creates:

1. **Transaction record**: type='debit', reference_type='Payment', for the total credit amount applied
2. **DEBIT Journal Entries** (one per voucher/source used):
   - Account: Liabilities > Advances > Client > Payment Gateway
   - Amount: the portion applied from that voucher
   - Meaning: "We no longer owe this advance to the client -- it's been used"
3. **CREDIT Journal Entry** (one, total amount):
   - Account: Accounts Receivable > Clients
   - Amount: total credit applied
   - Meaning: "The client's debt (receivable) is reduced by this payment"

**Additionally**, in Step 1A, if no Invoice Generation COA exists, the service also creates the initial invoice-generation entries by calling `InvoiceController->addJournalEntry()` for each task on the invoice. This ensures the Receivable/Revenue entries exist before the payment entries are posted.

### Accounting Flow Summary:
```
Invoice Creation (Step 1A if missing):
  DEBIT  Accounts Receivable > Clients     (client owes money)
  CREDIT Revenue accounts                  (company earned revenue)

Credit Payment Application (Step 2):
  DEBIT  Liabilities > Advances > Client > Payment Gateway   (clear advance)
  CREDIT Accounts Receivable > Clients                        (clear receivable)
```

---

## Real-World Example

### Scenario: Invoice for 500 KWD, Client has 2 Credits:
- Credit #10 (Topup via VCH-001): 300 KWD available, source Payment had 1.500 KWD gateway fee on 600 KWD
- Credit #15 (Refund RF-2025-00001): 250 KWD available

### User Request:
```json
{
    "invoice_id": 42,
    "payment_allocations": [
        {"credit_id": 10, "amount": 300},
        {"credit_id": 15, "amount": 200}
    ],
    "payment_mode": "full"
}
```

### What Happens (Step by Step):

1. **Validation**: totalCreditSelected = 300 + 200 = 500. invoiceAmount = 500. Mode is 'full'. 500 >= 500 -- PASS.

2. **DB Transaction begins**

3. **Step 1A**: Check if Invoice Generation COA exists. If not, create Transaction + JournalEntries for the invoice.

4. **Step 1B - First allocation (Credit #10, 300 KWD)**:
   - Load Credit #10 (type=Topup, payment_id=7)
   - Available balance for payment_id=7: 300 KWD. Requested: 300. OK.
   - `applyFromThis = min(300, 500) = 300`
   - Proportional fee: `1.500 * (300 / 600) = 0.750 KWD`
   - Create **InvoicePartial**: amount=300, status='paid', gateway='Credit'
   - Create **Credit**: amount=-300, payment_id=7, type='Invoice'
   - Create **PaymentApplication**: payment_id=7, credit_id=10, amount=300
   - `remainingToApply = 500 - 300 = 200`

5. **Step 1B - Second allocation (Credit #15, 200 KWD)**:
   - Load Credit #15 (type=Refund, refund_id=3)
   - Available balance for refund_id=3: 250 KWD. Requested: 200. OK.
   - `applyFromThis = min(200, 200) = 200`
   - Proportional fee: 0 (refund credits have no payment_id, so no gateway fee)
   - Create **InvoicePartial**: amount=200, status='paid', gateway='Credit'
   - Create **Credit**: amount=-200, refund_id=3, type='Invoice'
   - Create **PaymentApplication**: payment_id=null, credit_id=15, amount=200
   - `remainingToApply = 200 - 200 = 0`

6. **Step 2 - COA**: `creditApplied = 300 + 200 = 500`
   - Create Transaction (amount=500, type='debit', reference='Payment')
   - Create JournalEntry DEBIT: Liabilities account, 300 KWD (from VCH-001)
   - Create JournalEntry DEBIT: Liabilities account, 200 KWD (from RF-2025-00001)
   - Create JournalEntry CREDIT: Receivable account, 500 KWD

7. **Step 3 - Invoice Status**: mode='full'
   - Invoice status = 'paid'
   - Invoice paid_date = now()
   - Invoice payment_type = 'credit'
   - Invoice is_client_credit = true

8. **DB Transaction commits**

### Final State:
- **Invoice #42**: status='paid', payment_type='credit'
- **2 InvoicePartials**: both status='paid', gateway='Credit'
- **2 Credit deductions**: -300 and -200
- **2 PaymentApplication records**: audit trail
- **1 Transaction + 3 JournalEntries**: accounting records
- **Credit #10 balance**: now 0 (300 - 300)
- **Credit #15 balance**: now 50 (250 - 200)

---

## Multi-Payment Support

### Can one Payment pay multiple Invoices? YES

**Code Evidence**: Each call to `applyPaymentsToInvoice()` creates a negative Credit record linked to the source `payment_id`. The available balance is calculated as `SUM(amount) WHERE payment_id = X`. Multiple invoices can draw from the same source until the balance reaches zero.

Example: Payment VCH-001 with 1000 KWD can pay:
- Invoice A: 300 KWD (creates Credit amount=-300, payment_id=7)
- Invoice B: 400 KWD (creates Credit amount=-400, payment_id=7)
- Remaining balance: 1000 - 300 - 400 = 300 KWD available

### Can one Invoice be paid by multiple Payments? YES

**Code Evidence**: The `$paymentAllocations` array accepts multiple entries. The loop processes each one, creating separate InvoicePartial + Credit + PaymentApplication per source.

Example (as shown in the real-world scenario above): Invoice 500 KWD paid by Credit #10 (300 KWD) + Credit #15 (200 KWD).

### How is the amount split?

The user explicitly specifies the amount per source in the `payment_allocations` array. The service applies `min(requestedAmount, remainingToApply)` to prevent over-application. If the total selected exceeds the invoice amount, only the invoice amount is consumed (the excess is never deducted).

---

## Two Entry Points: When to Use Which

### Entry Point 1: `applyPaymentsToInvoice()` (Full orchestration)
- **Called from**: `InvoiceController@applyPaymentsToInvoice()` (AJAX endpoint, line 5204)
- **When**: Paying an existing invoice entirely with credit (standalone operation)
- **What it does**: Everything -- creates InvoicePartials, Credits, PaymentApplications, COA, updates invoice status

### Entry Point 2: `linkPaymentsToInvoicePartial()` (Linking only)
- **Called from**: `InvoiceController@store()` (during invoice partial creation, line 940)
- **When**: During the existing partial-payment flow where InvoicePartial is created first by the controller
- **What it does**: Only creates Credit deductions and PaymentApplication records. Does NOT create InvoicePartials or update invoice status.

---

## Integration Points for Bulk Upload

### When to Call
After invoice creation. The invoice must exist with a valid `amount`, `agent_id`, `client_id`, and `invoice_number` before calling `applyPaymentsToInvoice()`.

### Parameters to Pass
```php
$service = new PaymentApplicationService();

// First, get available credits for the client
$availableCredits = $service->getAvailablePaymentsForClient($clientId);

// Build allocations (auto-select FIFO, or manual)
$allocations = [];
$remaining = $invoiceAmount;
foreach ($availableCredits as $credit) {
    if ($remaining <= 0) break;
    $useAmount = min($credit['available_balance'], $remaining);
    $allocations[] = [
        'credit_id' => $credit['credit_id'],
        'amount' => $useAmount,
    ];
    $remaining -= $useAmount;
}

// Apply
$result = $service->applyPaymentsToInvoice(
    $invoiceId,
    $allocations,
    'full',   // or 'partial' if not enough credit
    []        // no options needed for full/partial
);
```

### Expected Behavior
- If `full` mode and sufficient credit: Invoice marked as 'paid', all records created
- If `full` mode and insufficient credit: Returns error with `shortfall` amount -- caller should switch to 'partial' mode
- If `partial` mode: Invoice marked as 'partial', credit applied, remaining balance is implicit
- All operations are atomic (DB transaction) -- either everything succeeds or nothing changes

### Caveats for Bulk Upload
1. The service calls `Auth::id()` for `applied_by` -- in CLI/queue context this will be null. The system handles this (field is nullable).
2. Step 1A calls `app(InvoiceController::class)` to create invoice-generation COA -- this works but is a code smell (service depending on controller).
3. The service does NOT send notifications. Bulk upload should handle notification separately if needed.
4. Each call is wrapped in its own DB transaction. For bulk operations, consider wrapping the entire batch in a larger transaction for atomicity.
