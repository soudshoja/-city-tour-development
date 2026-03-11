# Wallet/Credit System Research - Soud Laravel

## Executive Summary

The Soud Laravel system has a **dual credit system**:

1. **Wallet System**: Legacy table (`wallets` table) for IATA-based travel agencies tracking balance by wallet_id
2. **Credit System** (Active): Modern system for client credit/topup management integrated with invoice payment

The active credit system allows clients to maintain credit balances (from payments or refunds) that can be applied to pay invoices. Credits are tracked at the client level and support three payment modes: full, partial, and split.

---

## Database Structure

### Credits Table Schema

**Table:** `credits`

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | bigint(20) | No | Primary key |
| company_id | bigint(20) | No | Company owning the credit |
| branch_id | bigint(20) | Yes | Branch owning the credit |
| client_id | bigint(20) | No | Client who owns the credit |
| invoice_id | bigint(20) | Yes | Invoice being paid (if used for invoice) |
| invoice_partial_id | bigint(20) | Yes | Invoice partial being paid |
| payment_id | bigint(20) | Yes | Topup payment source (if TOPUP type) |
| refund_id | bigint(20) | Yes | Refund source (if REFUND type) |
| account_id | bigint(20) | Yes | Account reference |
| type | varchar(20) | Yes | Credit type: 'Invoice', 'Topup', 'Refund', 'Invoice Refund' |
| description | varchar(255) | Yes | Description of credit |
| amount | decimal(15,2) | Yes | Credit amount (positive or negative) |
| gateway_fee | decimal(10,3) | Default 0 | Gateway fee associated with credit |
| topup_by | enum | Yes | Who created topup: 'Client', 'Branch', 'Company' |
| created_at | timestamp | Yes | Creation timestamp |
| updated_at | timestamp | Yes | Update timestamp |

**Key Relationships:**
- Foreign keys exist to: companies, clients, invoices, payments, refunds
- Credits table includes soft delete support

### Wallets Table (Legacy)

**Table:** `wallets`

| Column | Type | Purpose |
|--------|------|---------|
| id | bigint | Primary key |
| wallet_id | varchar | IATA wallet identifier |
| iata_number | varchar | IATA agency number |
| currency | varchar | Currency code |
| wallet_balance | decimal(15,3) | Current balance |
| opening_balance | decimal(15,3) | Opening balance |
| task_amount | decimal(15,3) | Task-related amount |
| closing_balance | decimal(15,3) | Closing balance |
| timestamps | | created_at, updated_at |

**Note:** The Wallets table appears to be for legacy/IATA airline wallet tracking, separate from the client credit system.

### Related Tables

#### InvoicePartials Table
- Tracks partial/split payments on invoices
- Stores: invoice_id, amount, status (paid/unpaid), payment_gateway, payment_method, gateway_fee
- Linked to credits table via invoice_partial_id

#### PaymentApplications Table
- Audit trail for credit applications
- Links: payment_id, credit_id, invoice_id, invoice_partial_id
- Tracks: amount applied, applied_by (user), applied_at (timestamp)

---

## How Credit Balance Works

### Storage Model

Credits are **stored as individual ledger entries**, not as a single balance field. The balance is **calculated dynamically** by summing:

```
Total Available Balance = SUM(credits.amount) WHERE:
  - credits.client_id = client_id
  - credits.type IN ('Topup', 'Refund')
  - credits.payment_id IS NOT NULL OR credits.refund_id IS NOT NULL
  - NO negative (Invoice) credits linked to this payment/refund
```

### Balance Calculation Methods

#### 1. **Get Total Credits by Client**
```php
Credit::getTotalCreditsByClient($clientId)
// Returns: SUM of all positive credits for client (topups + refunds)
```

#### 2. **Get Available Balance by Payment (Topup)**
```php
Credit::getAvailableBalanceByPayment($paymentId)
// Returns: SUM(amount) WHERE payment_id = $paymentId and type IN ('Topup')
// Subtracts: negative amount credits (Invoices using this payment)
```

#### 3. **Get Available Balance by Refund**
```php
Credit::getAvailableBalanceByRefund($refundId)
// Returns: SUM(amount) WHERE refund_id = $refundId and type = 'Refund'
// Subtracts: negative amount credits (Invoices using this refund)
```

#### 4. **Get Available Payments/Credits for Client** (FIFO)
```php
Credit::getAvailablePaymentsForClient($clientId)
// Returns: Array of available topup payments and refunds with balances
// Sorted: By date (FIFO - oldest first) to ensure oldest credits used first
// Each entry includes: voucher_number, available_balance, credit_id, source_type
```

### Credit Types

| Type | Source | Storage | Use Case |
|------|--------|---------|----------|
| **Topup** | Payment record | payment_id | Client pays in advance for future invoices |
| **Refund** | Refund record | refund_id | Refund from task cancellation/void |
| **Invoice** | Negative credit | invoice_id + invoice_partial_id | When credit is used to pay invoice |
| **Invoice Refund** | Negative credit | invoice_id | When invoice is refunded |

---

## Credit Application to Invoices

### Step-by-Step Process

#### **STEP 1: Get Available Credits for Client**

```php
$availablePayments = Credit::getAvailablePaymentsForClient($clientId);
// Returns array like:
[
  [
    'payment' => Payment object,
    'available_balance' => 5000.00,
    'reference_number' => 'VCH-001',
    'date' => '2025-01-15',
    'source_type' => 'topup',
    'credit_id' => 12,
    'refund_id' => null,
  ],
  [
    'available_balance' => 2000.00,
    'reference_number' => 'RF-001',
    'date' => '2025-01-10',
    'source_type' => 'refund',
    'credit_id' => 8,
    'refund_id' => 5,
  ]
]
```

#### **STEP 2: Select Credits and Amount to Apply**

User selects which credits to use and how much from each:

```php
$paymentAllocations = [
  ['credit_id' => 12, 'amount' => 3000.00],  // From topup VCH-001
  ['credit_id' => 8, 'amount' => 2000.00],   // From refund RF-001
  // Total: 5000.00
]
```

#### **STEP 3: Validate Payment Selection**

```php
$service = new PaymentApplicationService();
$validation = $service->validatePaymentSelection($paymentAllocations, $invoiceAmount);
// Checks:
// - Each credit has enough available balance
// - Total selected covers required amount
// - Returns: valid flag, shortfall, excess
```

#### **STEP 4: Apply Payments Using PaymentApplicationService**

```php
$result = $service->applyPaymentsToInvoice(
  invoiceId: 123,
  paymentAllocations: [
    ['credit_id' => 12, 'amount' => 3000.00],
    ['credit_id' => 8, 'amount' => 2000.00],
  ],
  paymentMode: 'full',  // full, partial, or split
  options: []
);
```

**Payment Modes:**

| Mode | Invoice Amount Coverage | Invoice Status | Use Case |
|------|--------------------------|-----------------|----------|
| **full** | Credit must equal invoice amount | Paid | Credit covers entire invoice |
| **partial** | Credit < invoice amount | Partial | Credit covers part, rest unpaid |
| **split** | Credit < invoice amount | Partial | Credit + another gateway covers rest |

#### **STEP 5: Create Records**

For each credit being applied:

**A. Create InvoicePartial Record**
```php
$invoicePartial = InvoicePartial::create([
  'invoice_id' => $invoiceId,
  'invoice_number' => $invoice->invoice_number,
  'client_id' => $clientId,
  'agent_id' => $agentId,
  'amount' => 3000.00,
  'status' => 'paid',
  'type' => 'full',
  'payment_gateway' => 'Credit',
  'payment_method' => 'Credit Balance',
  'gateway_fee' => 0,
]);
```

**B. Create Negative Credit Entry** (Deduction)
```php
$credit = Credit::create([
  'company_id' => $invoice->agent->branch->company_id,
  'branch_id' => $invoice->agent->branch_id,
  'client_id' => $invoiceId,
  'payment_id' => $sourceCredit->payment_id,  // Topup payment
  'refund_id' => $sourceCredit->refund_id,    // Refund source
  'invoice_id' => $invoiceId,
  'invoice_partial_id' => $invoicePartial->id,
  'type' => Credit::INVOICE,  // Negative credit
  'amount' => -3000.00,  // NEGATIVE to reduce balance
  'gateway_fee' => 0,
  'description' => "Payment for INV-001 via VCH-001",
]);
```

**C. Create PaymentApplication Record** (Audit Trail)
```php
$app = PaymentApplication::create([
  'payment_id' => $sourceCredit->payment_id,
  'credit_id' => $sourceCredit->id,
  'invoice_id' => $invoiceId,
  'invoice_partial_id' => $invoicePartial->id,
  'amount' => 3000.00,
  'applied_by' => Auth::id(),
  'applied_at' => now(),
  'notes' => 'Applied from VCH-001 (full payment)',
]);
```

#### **STEP 6: Create Chart of Accounts (COA) Entries**

When credits are applied, Journal Entries are created:

```php
// Transaction record
$transaction = Transaction::create([
  'company_id' => $companyId,
  'branch_id' => $branchId,
  'entity_id' => $clientId,
  'entity_type' => 'Client',
  'transaction_type' => 'debit',
  'amount' => 5000.00,
  'description' => 'Credit Payment for INV-001',
  'invoice_id' => $invoiceId,
  'reference_type' => 'Payment',
  'reference_number' => 'INV-001',
  'transaction_date' => now(),
]);
```

**Journal Entries:**

1. **DEBIT: Liabilities → Advances → Client → Payment Gateway**
   - Clears the advance/credit held by client
   - One debit entry per voucher used

2. **CREDIT: Accounts Receivable → Clients**
   - Reduces the client's outstanding receivable
   - Single credit entry for total amount applied

```php
// DEBIT entry (clears liability)
JournalEntry::create([
  'transaction_id' => $transaction->id,
  'account_id' => $liabilityAccount->id,
  'debit' => 3000.00,
  'credit' => 0,
  'description' => 'Apply Client Credit from VCH-001',
]);

// CREDIT entry (clears receivable)
JournalEntry::create([
  'transaction_id' => $transaction->id,
  'account_id' => $receivableAccount->id,
  'debit' => 0,
  'credit' => 5000.00,
  'description' => 'Invoice INV-001 paid via Client Credit',
]);
```

#### **STEP 7: Update Invoice Status**

```php
$invoice->status = 'paid';        // If full payment with credit
$invoice->paid_date = now();
$invoice->payment_type = 'credit';
$invoice->is_client_credit = true;
$invoice->save();
```

---

## Code Examples

### Deducting Credit

**Example: Applying $3,000 topup credit to $5,000 invoice (full payment mode)**

```php
// 1. Get available credits for client
$availablePayments = Credit::getAvailablePaymentsForClient(123);
// Returns payment with balance 5000

// 2. Validate selection
$paymentAllocations = [
  ['credit_id' => 12, 'amount' => 5000.00]
];
$validation = app(PaymentApplicationService::class)
  ->validatePaymentSelection($paymentAllocations, 5000.00);
// Returns: {valid: true, total_selected: 5000, shortfall: 0}

// 3. Apply to invoice
$result = app(PaymentApplicationService::class)->applyPaymentsToInvoice(
  invoiceId: 456,
  paymentAllocations: [
    ['credit_id' => 12, 'amount' => 5000.00]
  ],
  paymentMode: 'full'
);

// Result:
// {
//   'success' => true,
//   'message' => 'Successfully paid invoice in full using 5000.00 KWD credit.',
//   'credit_applied' => 5000.00,
//   'remaining_amount' => 0,
//   'applied_payments' => [
//     [
//       'credit_id' => 12,
//       'payment_id' => 8,
//       'voucher_number' => 'VCH-001',
//       'amount_applied' => 5000.00,
//       'remaining_balance' => 0,
//     ]
//   ],
// }
```

**Database Changes After Application:**

```
CREDITS table:
+ New row: credit_id=99, client_id=123, invoice_id=456, type='Invoice', amount=-5000.00
  (This negative entry reduces available balance)

INVOICE_PARTIALS table:
+ New row: invoice_id=456, amount=5000.00, status='paid', payment_gateway='Credit'

PAYMENT_APPLICATIONS table:
+ New row: payment_id=8, credit_id=12, invoice_id=456, amount=5000.00

INVOICES table:
^ Update: status='paid', paid_date=NOW(), is_client_credit=1, payment_type='credit'

TRANSACTIONS table:
+ New row: reference_type='Payment', invoice_id=456, amount=5000.00

JOURNAL_ENTRIES table:
+ Debit: Liabilities → Client → Payment Gateway, amount=5000.00
+ Credit: Accounts Receivable → Clients, amount=5000.00
```

### Accounting Entries

When a credit is applied, the accounting impact is:

**Entry 1: Clear Client Liability**
```
DEBIT:  Liabilities → Advances → Client → Payment Gateway    5000.00
CREDIT: Accounts Receivable → Clients                                    5000.00
```

This entries shows:
- Client's advance balance (liability) is reduced
- Client's outstanding receivable (asset) is reduced
- Net effect: Company recognizes the payment from client's credit

**GL Accounts Affected:**
- **Advances (Liability)**: Reduced by credit amount (client owes less)
- **Accounts Receivable (Asset)**: Reduced by credit amount (company receives less)

---

## Integration Points for Bulk Upload

### Scenario: Bulk Invoice Creation with Client Credit

When creating multiple invoices via bulk upload, the credit system has **limited direct integration**. Here's how it works:

### Current Implementation

#### **Method 1: Manual Credit Application After Invoice Creation**

The `InvoiceController::store()` method creates invoices but does NOT automatically apply client credits. Credits must be applied separately.

```php
// 1. Create invoice (no credit applied)
$invoice = Invoice::create([
  'invoice_number' => 'INV-001',
  'client_id' => 123,
  'amount' => 5000.00,
  'status' => 'unpaid',
]);

// 2. Later: Apply credit if needed
$service = app(PaymentApplicationService::class);
$result = $service->applyPaymentsToInvoice(
  invoiceId: $invoice->id,
  paymentAllocations: [
    ['credit_id' => 12, 'amount' => 5000.00]
  ],
  paymentMode: 'full'
);
```

#### **Method 2: Bulk Creation with savePartial() for Each Invoice**

The `InvoiceController::savePartial()` method CAN accept credit payment:

```php
// After creating invoice, apply credit via savePartial
$request->merge([
  'invoiceId' => $invoice->id,
  'clientId' => 123,
  'amount' => 5000.00,
  'gateway' => 'Credit',
  'type' => 'full',
  'credit' => true,  // Mark as credit payment
  'payment_allocations' => [
    ['credit_id' => 12, 'amount' => 5000.00]
  ]
]);

$partialResult = $invoiceController->savePartial($request);
```

### Required Data for Bulk Credit Usage

**To apply client credit during bulk invoice creation, you need:**

1. **For each invoice to be paid with credit:**
   - `invoice_id` (created from bulk upload)
   - `client_id`
   - `amount_to_pay` (part of invoice or full invoice)
   - `credit_id` (from available credits) OR `payment_id` (from topup payment)

2. **Credit sources must already exist:**
   - Either a TOPUP payment record (client paid in advance)
   - Or a REFUND record (from previous refund/void)

3. **Payment mode:**
   - `full`: If credit covers entire invoice
   - `partial`: If credit covers part of invoice
   - `split`: If credit + another gateway covers invoice

### Recommended Approach for Bulk Upload

**Suggested Implementation:**

```php
// Pseudo-code for bulk invoice with credit

public function bulkCreateInvoicesWithCredit(array $invoiceData)
{
    foreach ($invoiceData as $item) {
        DB::transaction(function () use ($item) {
            // Step 1: Create invoice
            $invoice = Invoice::create([
                'invoice_number' => $item['invoice_number'],
                'client_id' => $item['client_id'],
                'amount' => $item['amount'],
                'status' => 'unpaid',
            ]);

            // Step 2: Add invoice details
            foreach ($item['tasks'] as $task) {
                InvoiceDetail::create([...]);
            }

            // Step 3: If payment method is credit, apply it
            if ($item['payment_method'] === 'credit') {
                $service = app(PaymentApplicationService::class);

                $result = $service->applyPaymentsToInvoice(
                    invoiceId: $invoice->id,
                    paymentAllocations: $item['credit_allocations'],
                    paymentMode: $item['payment_mode'] ?? 'full'
                );

                if (!$result['success']) {
                    throw new Exception('Credit application failed: ' . $result['message']);
                }
            }
        });
    }
}
```

### Data Structure for Bulk Upload with Credit

```php
$bulkInvoiceData = [
    [
        'invoice_number' => 'INV-001',
        'client_id' => 123,
        'agent_id' => 45,
        'amount' => 5000.00,
        'payment_method' => 'credit',  // NEW: specify credit
        'payment_mode' => 'full',      // NEW: full/partial/split
        'credit_allocations' => [      // NEW: which credits to use
            ['credit_id' => 12, 'amount' => 5000.00]
        ],
        'tasks' => [
            [
                'task_id' => 789,
                'invoice_price' => 5000.00
            ]
        ]
    ]
];
```

### Integration Checklist

- [ ] **Can bulk-created invoices use client credit automatically?**
  - Currently: NO - credit must be applied separately via API
  - Feasible: YES - can be implemented post-invoice creation

- [ ] **Which method to call?**
  - `PaymentApplicationService::applyPaymentsToInvoice()` (Recommended)
  - OR `InvoiceController::savePartial()` with `$credit=true`

- [ ] **Required data?**
  - Available credit sources (TOPUP or REFUND records)
  - Credit allocation array with credit_id and amount
  - Payment mode (full/partial/split)
  - Invoice ID (from bulk creation step)

- [ ] **Error handling needed?**
  - Insufficient credit balance check
  - Transaction rollback if credit application fails
  - Logging for audit trail

---

## Key Classes & Methods

### Credit Model
- **File:** `/app/Models/Credit.php`
- **Key Methods:**
  - `getTotalCreditsByClient($clientId)` - Get all credits for client
  - `getAvailableBalanceByPayment($paymentId)` - Balance from topup
  - `getAvailableBalanceByRefund($refundId)` - Balance from refund
  - `getAvailablePaymentsForClient($clientId)` - All available credit sources (FIFO)
  - `hasEnoughBalance($paymentId, $amount)` - Validate sufficient balance

### PaymentApplicationService
- **File:** `/app/Services/PaymentApplicationService.php`
- **Key Methods:**
  - `applyPaymentsToInvoice($invoiceId, $allocations, $mode, $options)` - Main method
  - `getAvailablePaymentsForClient($clientId)` - Get credit sources
  - `validatePaymentSelection($allocations, $requiredAmount)` - Validate before apply
  - `linkPaymentsToInvoicePartial($invoice, $partial, $allocations)` - Link to partial
  - `createCreditPaymentCOA($invoice, $appliedPayments, $totalAmount)` - Create accounting entries

### Invoice Model
- **File:** `/app/Models/Invoice.php`
- **Key Attributes:**
  - `is_client_credit` - Whether invoice was paid with client credit
  - `payment_type` - Type of payment (credit, cash, card, etc.)
- **Key Methods:**
  - `getTotalPaidViaApplicationsAttribute()` - Amount paid via credits
  - `getRemainingBalanceAttribute()` - Unpaid amount
  - `isFullyPaidViaApplications()` - Check if fully paid

### PaymentApplication Model
- **File:** `/app/Models/PaymentApplication.php`
- **Purpose:** Audit trail of credit applications
- **Key Methods:**
  - `getTotalAppliedByPayment($paymentId)` - Total applied from payment
  - `getTotalAppliedToInvoice($invoiceId)` - Total applied to invoice
  - `getApplicationsForInvoice($invoiceId)` - History of payments for invoice

---

## Database Queries for Analysis

### Get Client's Total Available Credit
```sql
SELECT
  c.id,
  c.name,
  SUM(cr.amount) as total_credit_balance
FROM clients c
LEFT JOIN credits cr ON c.id = cr.client_id
WHERE cr.type IN ('Topup', 'Refund')
  AND c.id = 123
GROUP BY c.id;
```

### Get Available Credits by Source
```sql
SELECT
  cr.id,
  cr.type,
  cr.payment_id,
  p.voucher_number,
  cr.amount,
  SUM(cr2.amount) as amount_used,
  cr.amount + SUM(cr2.amount) as available_balance
FROM credits cr
LEFT JOIN payments p ON cr.payment_id = p.id
LEFT JOIN credits cr2 ON cr.id = cr2.payment_id
  AND cr2.type = 'Invoice'
WHERE cr.client_id = 123
  AND cr.type IN ('Topup', 'Refund')
GROUP BY cr.id;
```

### Track Credit Usage for Invoice
```sql
SELECT
  pa.id,
  pa.credit_id,
  cr.type,
  p.voucher_number,
  pa.amount as applied_amount,
  pa.applied_at,
  u.email as applied_by
FROM payment_applications pa
JOIN credits cr ON pa.credit_id = cr.id
LEFT JOIN payments p ON cr.payment_id = p.id
LEFT JOIN users u ON pa.applied_by = u.id
WHERE pa.invoice_id = 456
ORDER BY pa.applied_at DESC;
```

### Verify Credit Balance Calculation
```sql
-- Total topup/refund credits (positive)
SELECT SUM(amount) as total_positive
FROM credits
WHERE client_id = 123
  AND type IN ('Topup', 'Refund');

-- Total invoice deductions (negative)
SELECT SUM(amount) as total_negative
FROM credits
WHERE client_id = 123
  AND type = 'Invoice';

-- Net available balance
SELECT
  (SELECT SUM(amount) FROM credits WHERE client_id = 123 AND type IN ('Topup', 'Refund'))
  + (SELECT SUM(amount) FROM credits WHERE client_id = 123 AND type = 'Invoice')
  as net_available_balance;
```

---

## Notes for Implementation

1. **FIFO Processing**: Credits are applied oldest first via `usort()` in `getAvailablePaymentsForClient()`

2. **Negative Amounts**: When credit is used, a negative credit entry is created to reduce available balance

3. **Gateway Fees**: Proportional gateway fees are calculated and stored when applying credits

4. **Transaction Safety**: All credit applications use `DB::transaction()` for atomicity

5. **Audit Trail**: Every credit application creates PaymentApplication record for full traceability

6. **Soft Deletes**: Payments support soft deletes, but credits do not (yet)

7. **COA Automation**: Credit payment automatically creates appropriate journal entries

8. **No Direct Balance Field**: Balance is always calculated from credit entries (ledger-based)

---

## Related Tables Reference

- **clients** - Client records
- **invoices** - Invoice records
- **invoice_details** - Line items on invoices
- **invoice_partials** - Payment partials (multiple payments per invoice)
- **payments** - Payment records (client topups)
- **refunds** - Refund records
- **payment_applications** - Audit trail linking payments to invoices
- **transactions** - Ledger transactions
- **journal_entries** - Chart of accounts entries
- **accounts** - Chart of accounts
- **charges** - Payment gateway configurations

---

## Summary

The Soud Laravel credit system is a **flexible, audit-able ledger-based solution** for managing client prepayments and refunds. Key characteristics:

✅ **Strengths:**
- Supports multiple credit sources (topups + refunds)
- FIFO processing for fairness
- Comprehensive audit trail via PaymentApplications
- Automatic COA entry creation
- Three payment modes (full/partial/split)
- Proportional gateway fee calculation

⚠️ **Considerations for Bulk Upload:**
- Credit application is NOT automatic during invoice creation
- Requires post-creation API call to apply credits
- Must have credit sources pre-existing
- Transaction safety built-in

🔄 **Recommended for Bulk:**
1. Create invoices first
2. Fetch available credits for each client
3. Call `PaymentApplicationService::applyPaymentsToInvoice()` for each invoice
4. Handle errors and validate before applying
