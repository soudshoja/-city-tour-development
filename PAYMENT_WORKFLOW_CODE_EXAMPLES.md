# Invoice Payment Workflow - Code Examples

## Quick Reference for Developers

### 1. Apply Credit Payment to Invoice

```php
// Use case: Client has topup/refund credit and wants to pay invoice

use App\Services\PaymentApplicationService;

$service = new PaymentApplicationService();

// Get invoice and available credits
$invoice = Invoice::findOrFail($invoiceId);
$availableCredits = $service->getAvailablePaymentsForClient($invoice->client_id);

// FULL PAYMENT: Cover entire invoice with credit
$result = $service->applyPaymentsToInvoice(
    invoiceId: $invoice->id,
    paymentAllocations: [
        [
            'credit_id' => $availableCredits[0]['credit_id'],
            'amount' => $invoice->amount
        ]
    ],
    paymentMode: 'full'
);

if ($result['success']) {
    // Invoice is now PAID
    echo "Invoice {$invoice->invoice_number} is now PAID";
    echo "Status: " . $invoice->fresh()->status; // 'paid'
}

// PARTIAL PAYMENT: Pay portion, leave balance
$result = $service->applyPaymentsToInvoice(
    invoiceId: $invoice->id,
    paymentAllocations: [
        [
            'credit_id' => $availableCredits[0]['credit_id'],
            'amount' => 50  // Pay 50 KWD, leave rest
        ]
    ],
    paymentMode: 'partial'
);

if ($result['success']) {
    echo "Invoice {$invoice->invoice_number} is PARTIAL";
    echo "Remaining balance: {$result['remaining_amount']}";
}

// SPLIT PAYMENT: Credit + external gateway
$result = $service->applyPaymentsToInvoice(
    invoiceId: $invoice->id,
    paymentAllocations: [
        [
            'credit_id' => $availableCredits[0]['credit_id'],
            'amount' => 50  // Pay 50 via credit
        ]
    ],
    paymentMode: 'split',
    options: [
        'other_gateway' => 'MyFatoorah',
        'other_method' => 'Card',
        'charge_id' => 3
    ]
);

// Result: Invoice PARTIAL with two InvoicePartials:
// - First: 50 KWD paid via credit (status='paid')
// - Second: Remaining via MyFatoorah (status='unpaid')
```

### 2. Validate Payment Selection Before Applying

```php
// Validate that selected credits can cover the invoice

$paymentAllocations = [
    ['credit_id' => 1, 'amount' => 50],
    ['credit_id' => 2, 'amount' => 60]
];

$validation = $service->validatePaymentSelection(
    $paymentAllocations,
    $invoice->amount  // Required amount
);

if (!$validation['valid']) {
    echo "Validation failed:";
    foreach ($validation['issues'] as $issue) {
        echo " - $issue";
    }
    echo "Shortfall: {$validation['shortfall']}";
} else {
    echo "Payment selection is valid";
    echo "Total selected: {$validation['total_selected']}";
}
```

### 3. Get Payment History for Invoice

```php
// See which payments were applied to an invoice

$paymentHistory = $service->getPaymentHistoryForInvoice($invoiceId);

foreach ($paymentHistory as $application) {
    echo "Voucher: {$application->source_reference}";
    echo "Amount: {$application->amount}";
    echo "Applied by: {$application->appliedBy->name}";
    echo "Applied at: {$application->applied_at}";
}
```

### 4. Get Available Payments for Client

```php
// Get all available credit balances for a client

$availablePayments = $service->getAvailablePaymentsForClient($clientId);

// Returns array of:
// [
//     [
//         'credit_id' => 1,
//         'voucher_number' => 'VCH-001',
//         'available_balance' => 100.00,
//         'type' => 'Topup',  // or 'Refund'
//     ],
//     ...
// ]

foreach ($availablePayments as $payment) {
    if ($payment['available_balance'] > 0) {
        echo "{$payment['voucher_number']}: {$payment['available_balance']} available";
    }
}
```

### 5. Check Invoice Payment Status

```php
// Query invoice payment status

$invoice = Invoice::find($invoiceId);

echo "Status: {$invoice->status}"; // 'paid', 'unpaid', 'partial', etc.
echo "Payment Type: {$invoice->payment_type}"; // 'credit', 'cash', 'split', etc.
echo "Paid Date: {$invoice->paid_date}"; // Timestamp or null
echo "Is Client Credit: {$invoice->is_client_credit}"; // true/false

// Get remaining balance
echo "Remaining balance: {$invoice->remaining_balance}";

// Check if fully paid
if ($invoice->isFullyPaidViaApplications()) {
    echo "Invoice is fully paid";
}
```

### 6. Get Payment Details for Invoice

```php
// Get all payment applications (which payments paid this invoice)

$applications = PaymentApplication::getApplicationsForInvoice($invoiceId);

foreach ($applications as $app) {
    echo "Source: {$app->source_reference}"; // Voucher or refund number
    echo "Source Type: {$app->source_type}"; // 'Topup' or 'Refund'
    echo "Amount: {$app->amount}";
    echo "Applied by: {$app->appliedBy->name}";
    echo "Date: {$app->applied_at}";
}
```

### 7. Create Payment Link via Gateway

```php
// Create payment link for external gateway (MyFatoorah example)

use App\Models\Payment;

$payment = Payment::create([
    'client_id' => $invoice->client_id,
    'agent_id' => $invoice->agent_id,
    'invoice_id' => $invoice->id,
    'amount' => $invoice->amount,
    'currency' => $invoice->currency,
    'payment_gateway' => 'MyFatoorah',
    'payment_method_id' => $paymentMethodId,
    'status' => 'pending',
    'completed' => false,
    'voucher_number' => 'VOC-' . date('YmdHis'),
]);

// Gateway generates payment_url (via webhook/API)
// Client redirected to $payment->payment_url

// Invoice remains UNPAID until gateway confirms payment
// Webhook handler updates Payment status to 'completed'
// Then marks Invoice as PAID
```

### 8. Mark Invoice as Paid (Cash)

```php
// Mark invoice as paid via cash (manual entry)

DB::transaction(function () use ($invoice) {
    // Update invoice
    $invoice->update([
        'status' => 'paid',
        'payment_type' => 'cash',
        'paid_date' => now(),
    ]);

    // Create invoice partial record
    $partial = InvoicePartial::create([
        'invoice_id' => $invoice->id,
        'invoice_number' => $invoice->invoice_number,
        'client_id' => $invoice->client_id,
        'agent_id' => $invoice->agent_id,
        'amount' => $invoice->amount,
        'status' => 'paid',
        'type' => 'full',
        'payment_gateway' => 'cash',
        'payment_method' => 'Cash',
        'service_charge' => 0,
        'gateway_fee' => 0,
    ]);
});
```

### 9. Change Payment Type (After Invoice Paid)

```php
// Change how a paid invoice was paid (credit to cash, etc.)

$invoice = Invoice::find($invoiceId);

// Only works for paid invoices
if ($invoice->status !== 'paid') {
    throw new Exception('Can only change payment type for paid invoices');
}

// Available changes:
// - credit ↔ cash
// - full ↔ credit

// Change from credit to cash
$result = $this->changeCreditToCash($invoice);

if ($result['error'] ?? false) {
    echo "Error: {$result['error']}";
} else {
    echo "Payment type changed to cash";
    echo "Invoice payment_type is now: {$invoice->fresh()->payment_type}";
}
```

### 10. Get Invoice Partials (Payment Splits)

```php
// Get all payment portions for an invoice

$invoice = Invoice::find($invoiceId);
$partials = $invoice->invoicePartials;

foreach ($partials as $partial) {
    echo "Amount: {$partial->amount}";
    echo "Status: {$partial->status}"; // 'paid' or 'unpaid'
    echo "Type: {$partial->type}"; // 'full', 'partial', 'split'
    echo "Gateway: {$partial->payment_gateway}"; // 'Credit', 'MyFatoorah', 'cash', etc.
    echo "Method: {$partial->payment_method}";
    echo "Gateway Fee: {$partial->gateway_fee}";
}
```

---

## Database Queries (Direct SQL)

### Get Invoice Payment Status

```sql
SELECT
    i.id,
    i.invoice_number,
    i.amount,
    i.status,
    i.payment_type,
    i.paid_date,
    SUM(pa.amount) as total_paid,
    (i.amount - SUM(pa.amount)) as remaining
FROM invoices i
LEFT JOIN payment_applications pa ON i.id = pa.invoice_id
WHERE i.id = ?
GROUP BY i.id;
```

### Get Available Credits for Client

```sql
SELECT
    c.id as credit_id,
    p.voucher_number,
    SUM(c.amount) as available_balance,
    CASE
        WHEN p.id IS NOT NULL THEN 'Topup'
        WHEN r.id IS NOT NULL THEN 'Refund'
        ELSE c.type
    END as type
FROM credits c
LEFT JOIN payments p ON c.payment_id = p.id
LEFT JOIN refunds r ON c.refund_id = r.id
WHERE c.client_id = ?
GROUP BY c.payment_id, c.refund_id
HAVING available_balance > 0
ORDER BY c.created_at DESC;
```

### Get Payment History for Invoice

```sql
SELECT
    pa.id,
    pa.amount,
    COALESCE(p.voucher_number, r.refund_number, 'Manual') as source_reference,
    CASE
        WHEN p.id IS NOT NULL THEN 'Topup'
        WHEN r.id IS NOT NULL THEN 'Refund'
        ELSE 'Other'
    END as source_type,
    u.name as applied_by,
    pa.applied_at,
    pa.notes
FROM payment_applications pa
LEFT JOIN payments p ON pa.payment_id = p.id
LEFT JOIN credits c ON pa.credit_id = c.id
LEFT JOIN refunds r ON c.refund_id = r.id
LEFT JOIN users u ON pa.applied_by = u.id
WHERE pa.invoice_id = ?
ORDER BY pa.applied_at DESC;
```

### Get Invoice Partials with Payment Details

```sql
SELECT
    ip.id,
    ip.amount,
    ip.status,
    ip.type,
    ip.payment_gateway,
    ip.payment_method,
    ip.gateway_fee,
    ip.service_charge,
    COUNT(DISTINCT c.id) as num_credits_used
FROM invoice_partials ip
LEFT JOIN credits c ON ip.id = c.invoice_partial_id
WHERE ip.invoice_id = ?
GROUP BY ip.id
ORDER BY ip.created_at ASC;
```

---

## Flow Diagrams (Text)

### Credit Payment Flow

```
User selects invoice to pay
    ↓
Check available credits for client
    ↓
User selects credit amount & mode (full/partial/split)
    ↓
Validate:
├─ Credit exists & has sufficient balance
├─ Amount matches mode requirements
└─ Invoice exists
    ↓
Begin Database Transaction
    ↓
Create InvoicePartial (paid portion)
    ↓
Create negative Credit record (deduct from balance)
    ↓
Create PaymentApplication record (audit trail)
    ↓
Create COA Transaction + JournalEntry
    ├─ DEBIT: Liabilities → Advances → Client → Payment Gateway
    └─ CREDIT: Accounts Receivable → Clients
    ↓
Update Invoice status:
├─ full mode → status='paid'
├─ partial mode → status='partial'
└─ split mode → status='partial' + create unpaid partial
    ↓
Commit Transaction
    ↓
Return success response
```

### External Gateway Payment Flow

```
Invoice created (UNPAID)
    ↓
User selects external gateway (MyFatoorah, Tap, etc.)
    ↓
Create Payment record (status='pending')
    ↓
Generate payment link via gateway API
    ↓
Redirect user to payment URL
    ↓
User completes payment on gateway
    ↓
Gateway sends webhook callback
    ↓
Webhook handler receives & validates callback
    ↓
Update Payment status to 'completed'
    ↓
Create Credit (TOPUP type) with payment amount
    ↓
Create InvoicePartial (payment record)
    ↓
Check if full paid:
├─ If amount >= invoice amount → status='paid'
└─ If amount < invoice amount → status='partial'
    ↓
Create accounting entries
    ↓
Send payment confirmation email
```

### Split Payment Flow (Credit + Gateway)

```
Invoice created (UNPAID)
    ↓
User selects: "Use credit + other gateway"
    ↓
User selects credit amount (less than invoice total)
    ↓
User selects other gateway for remaining amount
    ↓
Apply credit via PaymentApplicationService:
├─ Create paid InvoicePartial (credit portion)
├─ Create negative Credit record
├─ Create PaymentApplication record
└─ Create COA entries
    ↓
Create unpaid InvoicePartial:
├─ Amount = invoice.amount - credit amount
├─ Status = 'unpaid'
├─ Gateway = selected gateway
└─ Type = 'split'
    ↓
Invoice status = 'PARTIAL'
    ↓
Wait for gateway payment on second partial
    ↓
When gateway payment received:
├─ Update second InvoicePartial status to 'paid'
└─ Update Invoice status to 'PAID'
```

---

## Route Examples

### Apply Payment to Invoice

```
POST /apply-payments

Request Body:
{
    "invoice_id": 1,
    "payment_allocations": [
        {
            "credit_id": 5,
            "amount": 100
        }
    ],
    "payment_mode": "full",
    "other_gateway": null,
    "other_method": null,
    "charge_id": null
}

Response (Success):
{
    "success": true,
    "message": "Successfully paid invoice in full using 100 KWD credit.",
    "payment_mode": "full",
    "credit_applied": 100,
    "remaining_amount": 0,
    "applied_payments": [
        {
            "credit_id": 5,
            "payment_id": null,
            "refund_id": null,
            "voucher_number": "VCH-001",
            "amount_applied": 100,
            "remaining_balance": 0,
            "invoice_partial_id": 1
        }
    ],
    "invoice_status": "paid",
    "invoice_partials_created": 1
}

Response (Error):
{
    "success": false,
    "message": "Insufficient credit selected. You selected 50 but need 100. Use partial or split payment mode.",
    "shortfall": 50
}
```

---

## Testing

### Test Credit Payment

```php
// Unit Test Example

use Tests\TestCase;
use App\Models\Invoice;
use App\Models\Credit;
use App\Models\Payment;
use App\Services\PaymentApplicationService;

class PaymentApplicationTest extends TestCase
{
    public function test_can_apply_full_credit_payment()
    {
        // Create invoice
        $invoice = Invoice::factory()->create(['amount' => 100]);

        // Create payment and credit
        $payment = Payment::factory()->create(['amount' => 100]);
        $credit = Credit::factory()->create([
            'payment_id' => $payment->id,
            'type' => 'Topup',
            'amount' => 100  // Available balance
        ]);

        // Apply payment
        $service = new PaymentApplicationService();
        $result = $service->applyPaymentsToInvoice(
            $invoice->id,
            [['credit_id' => $credit->id, 'amount' => 100]],
            'full'
        );

        // Assertions
        $this->assertTrue($result['success']);
        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertEquals(100, $result['credit_applied']);

        // Verify records created
        $this->assertCount(1, $invoice->fresh()->paymentApplications);
        $this->assertCount(1, $invoice->fresh()->invoicePartials);
    }

    public function test_cannot_apply_insufficient_credit()
    {
        $invoice = Invoice::factory()->create(['amount' => 100]);
        $payment = Payment::factory()->create();
        $credit = Credit::factory()->create([
            'payment_id' => $payment->id,
            'amount' => 50  // Only 50 available
        ]);

        $service = new PaymentApplicationService();
        $result = $service->applyPaymentsToInvoice(
            $invoice->id,
            [['credit_id' => $credit->id, 'amount' => 50]],
            'full'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Insufficient credit', $result['message']);
    }
}
```

---

## Key Points to Remember

1. **Credit amounts are stored as balance deltas**
   - Positive: money available
   - Negative: money used

2. **Every payment creates accounting entries**
   - Ensures double-entry bookkeeping
   - Required for financial reporting

3. **Invoice status cannot be overpaid**
   - Validation prevents paying more than invoice amount
   - Unless in "split" mode with specific intention

4. **Credit can be used multiple times**
   - One topup credit can pay multiple invoices
   - Balance tracked via SUM calculations

5. **Transactions are atomic**
   - All-or-nothing: either all records created or none
   - Rollback on any error

6. **Comprehensive logging**
   - All operations logged with [PAYMENT APPLICATION] prefix
   - User ID tracked for audit trail
   - Useful for debugging
