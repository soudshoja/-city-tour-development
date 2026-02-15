# Bulk Invoice Upload v2.0 - Implementation Status

**Date:** 2026-02-15
**Status:** IN PROGRESS - Excel template updated, logic needs rebuild

---

## ✅ Completed

### 1. Excel Template Structure
**File:** `app/Exports/BulkInvoiceTemplateExport.php`

**Columns (FINAL):**
1. `invoice_date` - Date to group tasks into invoices
2. `client_mobile` - To find client and link task
3. `task_reference` - To find existing task (task_id, PNR, etc.)
4. `task_status` - Task status (issued, reissued, refund, void, etc.)
5. `selling_price` - Amount to charge client (sets task.selling_amount)
6. `payment_reference` - Existing payment (voucher_number or payment_reference)
7. `notes` - Optional notes

**Files Updated:**
- ✅ `app/Exports/BulkInvoiceTemplateExport.php` - Simplified to single sheet
- ✅ `app/Exports/BulkInvoiceTemplateSheet.php` - Updated column headers

---

## 🔄 Needs Implementation

### 2. Upload Validation Logic
**File:** `app/Imports/BulkInvoiceImport.php` or validation service

**Required Changes:**
- Validate `invoice_date` format
- Validate `client_mobile` exists in system
- Validate `task_reference` + `task_status` combination exists
- Validate `selling_price` is numeric > 0
- Validate `payment_reference` exists (Payment model)
- Check task is not already invoiced (unless reissue/refund workflow)

**Validation Rules:**
```php
[
    'invoice_date' => 'required|date',
    'client_mobile' => 'required|exists:clients,phone',
    'task_reference' => 'required',
    'task_status' => 'required|in:issued,reissued,refund,void,cancelled',
    'selling_price' => 'required|numeric|min:0.001',
    'payment_reference' => 'required', // validate against Payment model
    'notes' => 'nullable|string|max:500',
]
```

**Complex Validations:**
1. **Task Lookup:** Find task by reference (could be task_id, PNR, booking_ref, etc.)
2. **Payment Lookup:** Find payment by voucher_number OR payment_reference
3. **Client Matching:** Find client by phone (with company_id scope)
4. **Duplicate Check:** Ensure task not already in another invoice (unless void/refund)

---

### 3. Row Processing Logic
**File:** `app/Jobs/CreateBulkInvoicesJob.php`

**Current Flow (WRONG):**
- Groups existing tasks into invoices

**Required Flow (CORRECT):**
```php
foreach ($rows as $row) {
    // 1. Find task by reference + status
    $task = Task::where('company_id', $companyId)
        ->where(function($q) use ($row) {
            $q->where('id', $row->task_reference)
              ->orWhere('pnr', $row->task_reference)
              ->orWhere('booking_reference', $row->task_reference);
        })
        ->where('task_status', $row->task_status)
        ->firstOrFail();

    // 2. Set selling price (was NULL before)
    $task->selling_amount = $row->selling_price;
    $task->save();

    // 3. Find client by mobile
    $client = Client::where('company_id', $companyId)
        ->where('phone', $row->client_mobile)
        ->firstOrFail();

    // 4. Link task to client (if not already linked)
    if (!$task->client_id) {
        $task->client_id = $client->id;
        $task->save();
    }
    // If task already has client, use existing (don't replace)

    // 5. Find payment
    $payment = Payment::where('company_id', $companyId)
        ->where(function($q) use ($row) {
            $q->where('voucher_number', $row->payment_reference)
              ->orWhere('payment_reference', $row->payment_reference);
        })
        ->firstOrFail();

    // Store for grouping
    $taskData[] = [
        'task' => $task,
        'client' => $task->client_id ? $task->client : $client,
        'payment' => $payment,
        'invoice_date' => $row->invoice_date,
        'selling_price' => $row->selling_price,
        'notes' => $row->notes,
    ];
}

// 6. Group by (client_id + invoice_date)
$invoiceGroups = collect($taskData)->groupBy(function($item) {
    return $item['client']->id . '_' . $item['invoice_date'];
});

// 7. Create invoices
foreach ($invoiceGroups as $group) {
    $invoice = Invoice::create([
        'company_id' => $companyId,
        'agent_id' => $agentId,
        'client_id' => $group->first()['client']->id,
        'invoice_date' => $group->first()['invoice_date'],
        'amount' => $group->sum('selling_price'),
        'currency' => 'KWD', // or from task
        // ... other fields
    ]);

    // 8. Create invoice details
    foreach ($group as $item) {
        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'task_id' => $item['task']->id,
            'amount' => $item['selling_price'],
            'notes' => $item['notes'],
        ]);
    }

    // 9. Link payment to invoice via PaymentApplication
    $payment = $group->first()['payment'];

    // Use PaymentApplicationService to link payment
    app(PaymentApplicationService::class)->applyPaymentsToInvoice(
        $invoice->id,
        [
            ['payment_id' => $payment->id, 'amount' => $invoice->amount]
        ],
        'full' // or 'partial' based on payment status
    );

    // 10. Check payment status and update invoice
    if ($payment->status === 'paid' || $payment->completed) {
        $invoice->status = 'paid';
        $invoice->paid_date = $payment->payment_date ?? now();
        $invoice->save();
    }
}
```

---

### 4. Task Reference Lookup Strategy
**Problem:** Task reference could be:
- `task.id` (integer)
- `task.pnr` (string)
- `task.booking_reference` (string)
- Other task-specific fields

**Solution:**
```php
protected function findTaskByReference(string $reference, string $status, int $companyId)
{
    return Task::where('company_id', $companyId)
        ->where('task_status', $status)
        ->where(function($q) use ($reference) {
            // Try ID first
            if (is_numeric($reference)) {
                $q->where('id', $reference);
            }
            // Try PNR
            $q->orWhere('pnr', $reference)
              ->orWhere('booking_reference', $reference)
              ->orWhere('confirmation_code', $reference);
        })
        ->first();
}
```

---

### 5. Payment Reference Lookup Strategy
**Problem:** Payment reference could be:
- `payment.voucher_number` (VCH-001)
- `payment.payment_reference` (gateway reference)
- `payment.id`

**Solution:**
```php
protected function findPaymentByReference(string $reference, int $companyId)
{
    return Payment::where('company_id', $companyId)
        ->where(function($q) use ($reference) {
            if (is_numeric($reference)) {
                $q->where('id', $reference);
            }
            $q->orWhere('voucher_number', $reference)
              ->orWhere('payment_reference', $reference);
        })
        ->first();
}
```

---

### 6. Client Linking Logic
**Rules:**
- If `task.client_id` is NULL → Link to client from mobile
- If `task.client_id` exists → Keep existing, use for invoice

**Code:**
```php
// Find client from mobile
$clientFromMobile = Client::where('company_id', $companyId)
    ->where('phone', $row->client_mobile)
    ->firstOrFail();

// Link task to client if not already linked
if (!$task->client_id) {
    $task->client_id = $clientFromMobile->id;
    $task->save();
    $clientForInvoice = $clientFromMobile;
} else {
    // Use existing client for invoice
    $clientForInvoice = $task->client;
}
```

---

### 7. Payment Application Integration
**Use existing PaymentApplicationService:**

```php
use App\Services\PaymentApplicationService;

$paymentService = app(PaymentApplicationService::class);

// After creating invoice, link payment
$result = $paymentService->applyPaymentsToInvoice(
    invoiceId: $invoice->id,
    paymentAllocations: [
        [
            'payment_id' => $payment->id,
            'amount' => $invoice->amount,
        ]
    ],
    paymentMode: $payment->completed ? 'full' : 'partial'
);

if ($result['success']) {
    // Payment linked successfully
    // Invoice status updated automatically by service
}
```

---

### 8. Invoice Status Logic
**Based on Payment Status:**

```php
if ($payment->completed && $payment->status === 'paid') {
    // Full payment - mark invoice as paid
    $paymentMode = 'full';
    // PaymentApplicationService will:
    // - Create invoice_partial with status 'paid'
    // - Update invoice.status to 'paid'
    // - Set invoice.paid_date
} else {
    // Payment not completed - mark as unpaid/partial
    $paymentMode = 'partial';
    // PaymentApplicationService will:
    // - Create invoice_partial with status 'unpaid'
    // - Update invoice.status to 'partial' or 'unpaid'
}
```

---

## 📋 Files That Need Changes

### Must Change:
1. ✅ `app/Exports/BulkInvoiceTemplateExport.php` - DONE
2. ✅ `app/Exports/BulkInvoiceTemplateSheet.php` - DONE
3. ❌ `app/Imports/BulkInvoiceImport.php` - Needs complete rewrite
4. ❌ `app/Jobs/CreateBulkInvoicesJob.php` - Needs complete rewrite
5. ❌ Validation service/logic - Needs update

### Might Need Changes:
- `app/Models/BulkUploadRow.php` - Field mapping
- `resources/views/bulk-invoice/preview.blade.php` - Display logic
- `resources/views/bulk-invoice/success.blade.php` - Success message

---

## 🧪 Testing Checklist

### Test Cases:
1. **Task Lookup:**
   - ✓ Find by task ID
   - ✓ Find by PNR
   - ✓ Find by booking reference
   - ✓ Handle task not found
   - ✓ Handle multiple tasks with same reference (filtered by status)

2. **Client Linking:**
   - ✓ Task with no client → Link to client from mobile
   - ✓ Task with existing client → Keep existing, use for invoice
   - ✓ Client mobile not found → Error
   - ✓ Client mobile matches task's existing client → Success

3. **Payment Lookup:**
   - ✓ Find by voucher_number
   - ✓ Find by payment_reference
   - ✓ Find by payment ID
   - ✓ Handle payment not found

4. **Invoice Grouping:**
   - ✓ Same client + same date → One invoice
   - ✓ Same client + different dates → Multiple invoices
   - ✓ Different clients + same date → Multiple invoices

5. **Payment Application:**
   - ✓ Paid payment → Invoice marked as paid
   - ✓ Unpaid payment → Invoice marked as unpaid/partial
   - ✓ Payment amount matches invoice → Full payment
   - ✓ Payment amount less than invoice → Partial payment

6. **Selling Price:**
   - ✓ Task selling_amount NULL → Set from Excel
   - ✓ Selling price sets task.selling_amount
   - ✓ Invoice_detail created with selling price

---

## 🚀 Next Steps

1. **Update BulkInvoiceImport:** Change validation logic for new columns
2. **Update CreateBulkInvoicesJob:** Implement new flow (link tasks, find payments, create invoices)
3. **Test with sample data:** Create test Excel with real task/payment references
4. **Update preview page:** Show task references instead of task IDs
5. **Deploy and test:** Full end-to-end testing with real data

---

## 📝 Notes

- **Task selling price is ONLY set during invoice creation** - was NULL before
- **Tasks may not have clients initially** - we link them during invoice creation
- **Don't replace existing task.client_id** - only set if NULL
- **Payment already exists** - agent created payment link before upload
- **Use PaymentApplicationService** - don't manually create payment links

---

_Created: 2026-02-15_
_Last Updated: 2026-02-15_
_Status: Excel template done, awaiting logic implementation_
