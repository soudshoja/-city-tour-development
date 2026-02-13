# Invoice Status Research

## Overview
This document provides a comprehensive analysis of all invoice status values, their meanings, valid transitions, and the code that manages them. The invoice status system uses a PHP enum with 6 defined statuses that track the payment lifecycle of invoices.

---

## All Status Values

### 1. **unpaid** (Default)
- **Description**: Invoice has been created but payment has not been received
- **Initial Status**: Yes, default for all newly created invoices
- **Meaning**: The full amount is still outstanding
- **Editable**: Yes, invoice can still be edited/modified
- **Code Location**: `/home/soudshoja/soud-laravel/app/Enums/InvoiceStatus.php`

### 2. **paid**
- **Description**: Invoice has been fully paid in cash or other payment gateway
- **Initial Status**: No
- **Meaning**: The full invoice amount (100%) has been received
- **Editable**: No, invoice cannot be edited
- **Additional Tracking**: `paid_date` field is populated with current timestamp

### 3. **partial**
- **Description**: Invoice has been partially paid, with some balance remaining
- **Initial Status**: No
- **Meaning**: Only a portion of the invoice has been paid; remaining balance is outstanding
- **Editable**: Limited (cannot modify amounts, but can receive additional payments)
- **Related Records**: `InvoicePartials` table tracks each payment segment separately

### 4. **partial refund**
- **Description**: Some tasks on the invoice have been refunded, but not all
- **Initial Status**: No
- **Meaning**: The invoice originally was paid, but only some of the tasks have been refunded
- **Constraint**: Cannot transition to this if invoice was originally unpaid
- **Related Records**: References refund details that show which tasks were refunded

### 5. **refunded**
- **Description**: All tasks on the invoice have been refunded
- **Initial Status**: No
- **Meaning**: The entire invoice has been refunded back to the client
- **Constraint**: Only reached when all original tasks are refunded
- **Related Records**: All invoice details should have corresponding refund records

### 6. **paid by refund**
- **Description**: Invoice was originally paid, and a refund invoice was created to settle it
- **Initial Status**: No
- **Meaning**: The original invoice was marked as paid, but a refund invoice was generated to handle the refund process
- **Editable**: No, invoice is locked and cannot be modified
- **Related Records**: `refund_invoice_id` references the refund invoice record

---

## Status Transitions

### Creation Phase
```
New Invoice → unpaid (default)
```
- **When**: Every new invoice is created with `status = 'unpaid'`
- **Code**: `/home/soudshoja/soud-laravel/app/Http/Controllers/InvoiceController.php` lines 1227-1230
- **Example**:
  ```php
  $invoice = Invoice::create([
      'invoice_number' => $invoiceNumber,
      'status' => 'unpaid',  // Always defaults to unpaid
      'invoice_date' => now(),
      // ... other fields
  ]);
  ```

---

### Payment Application Transitions

#### **Path 1: unpaid → paid** (Full Payment)
- **Trigger**: Full payment via gateway (Knet, MyFatoorah, etc.) OR full payment via client credit
- **Payment Mode**: Credit payment with 'full' mode or cash/card payment covering 100%
- **Code Location**: `/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php` lines 294-302
- **Example**:
  ```php
  if ($paymentMode === 'full') {
      $invoice->status = 'paid';
      $invoice->paid_date = now();
      $invoice->payment_type = 'credit';  // or gateway name
      $invoice->save();
  }
  ```
- **Additional Fields Updated**:
  - `paid_date` ← current timestamp
  - `payment_type` ← method used ('cash', 'credit', 'knet', 'myfatoorah', etc.)
  - `is_client_credit` ← boolean flag
- **Validation**: Invoice status must be `unpaid` or `partial` before receiving full payment

#### **Path 2: unpaid → partial** (Partial Payment)
- **Trigger**: Partial payment received (less than invoice total) with remaining amount to be paid later
- **Payment Modes**:
  - Credit payment with 'partial' mode
  - Split payment (credit + another gateway)
- **Code Location**: `/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php` lines 334-342 (partial mode)
- **Example**:
  ```php
  } elseif ($paymentMode === 'partial') {
      $invoice->status = 'partial';
      $invoice->payment_type = 'partial';
      $invoice->is_client_credit = true;
      $invoice->save();
  }
  ```
- **InvoicePartials Created**:
  - One `paid` partial for the amount paid
  - Remaining amount can be collected later

#### **Path 3: unpaid → partial** (Split Payment)
- **Trigger**: Payment is split between credit and another gateway
- **Payment Modes**: Credit payment with 'split' mode + other_gateway specified
- **Code Location**: `/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php` lines 303-333
- **Example**:
  ```php
  } elseif ($paymentMode === 'split') {
      $splitPartial = InvoicePartial::create([
          'amount' => $remainingAmount,
          'status' => 'unpaid',
          'payment_gateway' => $options['other_gateway'],
      ]);

      $invoice->status = 'partial';
      $invoice->payment_type = 'split';
      $invoice->save();
  }
  ```
- **InvoicePartials Created**:
  - One `paid` partial for credit portion
  - One `unpaid` partial for remaining amount (other gateway)

#### **Path 4: partial → paid** (Complete Outstanding Balance)
- **Trigger**: Remaining balance on partially paid invoice is fully paid
- **Code Location**: Similar to Path 1, but applied to invoice with existing `partial` status
- **Process**:
  1. Receive additional payment covering remaining balance
  2. Create new `paid` InvoicePartial for this payment
  3. Check if all remaining balance is covered
  4. Update invoice status from `partial` to `paid`
  5. Set `paid_date` to current timestamp

---

### Refund Transitions

#### **Path 5: paid → partial refund** (Some Tasks Refunded)
- **Trigger**: Refund created for a paid invoice, but only some tasks are refunded (not all)
- **Code Location**: `/home/soudshoja/soud-laravel/app/Http/Controllers/RefundController.php` lines 574-580
- **Conditions**:
  - Original invoice status must be 'paid' or 'partial refund'
  - Some invoice tasks have matching refund records
  - NOT all invoice tasks are refunded
- **Example**:
  ```php
  if (count(array_intersect($allInvoiceTaskIds, $refundedOriginalTaskIds)) >= count($allInvoiceTaskIds)) {
      $invoice->update(['status' => InvoiceStatus::REFUNDED->value]);  // All refunded
  } else {
      $invoice->update(['status' => InvoiceStatus::PARTIAL_REFUND->value]);  // Some refunded
  }
  ```
- **Automatic Refund Credit**: System automatically credits the client's balance for refunded amount

#### **Path 6: paid → refunded** (All Tasks Refunded)
- **Trigger**: Refund created for a paid invoice where ALL tasks are refunded
- **Code Location**: `/home/soudshoja/soud-laravel/app/Http/Controllers/RefundController.php` lines 573-575
- **Conditions**:
  - Original invoice status must be 'paid'
  - ALL invoice tasks have matching refund records
  - Refund amount includes all original tasks
- **Example**:
  ```php
  if ($allTasksRefunded) {
      $invoice->update(['status' => InvoiceStatus::REFUNDED->value]);
      Log::info("Invoice marked as REFUNDED (all tasks refunded)");
  }
  ```
- **Automatic Refund Credit**: Full amount credited back to client

#### **Path 7: partial → refunded** (Partial Paid, Then Fully Refunded)
- **Trigger**: Partially paid invoice receives refunds covering all original tasks
- **Code Location**: Similar logic as Path 6
- **Conditions**: All tasks must be refunded regardless of whether invoice was partially or fully paid

#### **Path 8: paid → paid by refund** (Unpaid Refund with Original Payment)
- **Trigger**: Refund created for a paid invoice, but refund itself is marked as unpaid (payment will be collected separately)
- **Code Location**: `/home/soudshoja/soud-laravel/app/Http/Controllers/RefundController.php` lines 428-429
- **Example**:
  ```php
  if ($refund->originalInvoice) {
      $refund->originalInvoice->update(['status' => InvoiceStatus::PAID_BY_REFUND->value]);
  }
  ```
- **Meaning**: Original invoice is marked as "paid by refund" because a separate refund invoice was created
- **Related Records**: Refund invoice is created and referenced via `refund_invoice_id`

---

## Code Examples

### Status Update Methods in PaymentApplicationService

#### Full Payment Example
```php
// From: app/Services/PaymentApplicationService.php:294-302
if ($paymentMode === 'full') {
    // Full payment with credit - mark invoice as paid
    $invoice->status = 'paid';
    $invoice->paid_date = now();
    $invoice->payment_type = 'credit';
    $invoice->is_client_credit = true;
    $invoice->save();

    Log::info('[PAYMENT APPLICATION] Full payment - Invoice marked as paid');
}
```

#### Partial Payment Example
```php
// From: app/Services/PaymentApplicationService.php:334-342
} elseif ($paymentMode === 'partial') {
    // Partial payment - leave remaining unpaid (no second partial needed)
    $invoice->status = 'partial';
    $invoice->payment_type = 'partial';
    $invoice->is_client_credit = true;
    $invoice->save();

    Log::info('[PAYMENT APPLICATION] Partial payment - Invoice marked as partial, remaining: ' . $remainingAmount);
}
```

#### Split Payment Example
```php
// From: app/Services/PaymentApplicationService.php:303-333
} elseif ($paymentMode === 'split') {
    // Split payment - create unpaid partial for remaining amount
    $splitPartial = InvoicePartial::create([
        'invoice_id' => $invoice->id,
        'invoice_number' => $invoice->invoice_number,
        'amount' => $remainingAmount,
        'status' => 'unpaid',  // Remaining amount is unpaid
        'type' => 'split',
        'payment_gateway' => $options['other_gateway'] ?? null,
        'payment_method' => $options['other_method'] ?? null,
    ]);

    // Mark invoice as partial
    $invoice->status = 'partial';
    $invoice->payment_type = 'split';
    $invoice->is_client_credit = true;
    $invoice->save();

    Log::info('[PAYMENT APPLICATION] Split payment - Invoice marked as partial');
}
```

### Refund Status Updates in RefundController

#### Paid Refund (Auto-Credit) Example
```php
// From: app/Http/Controllers/RefundController.php:573-580
if ($allTasksRefunded) {
    $invoice->update(['status' => InvoiceStatus::REFUNDED->value]);
    Log::info("Invoice {$invoice->invoice_number} marked as REFUNDED (all tasks refunded)");
} else {
    $invoice->update(['status' => InvoiceStatus::PARTIAL_REFUND->value]);
    $refundedCount = count($refundedOriginalTaskIds);
    $totalTasks = count($allInvoiceTaskIds);
    Log::info("Invoice {$invoice->invoice_number} marked as PARTIAL_REFUND ({$refundedCount}/{$totalTasks} tasks refunded)");
}

$refund->update(['status' => 'completed']);
```

#### Unpaid Invoice Refund (Creates Refund Invoice) Example
```php
// From: app/Http/Controllers/RefundController.php:426-429
if ($refund->originalInvoice) {
    $refund->originalInvoice->update(['status' => InvoiceStatus::PAID_BY_REFUND->value]);
}

InvoicePartial::create([
    'invoice_id' => $refund->originalInvoice->id,
    'invoice_number' => $refund->originalInvoice->invoice_number,
    'amount' => $refund->total_nett_refund,
    'status' => 'unpaid',
    'type' => 'unpaid_refund',
    'payment_gateway' => $request->payment_gateway,
]);
```

### Refund Eligibility Check
```php
// From: app/Http/Controllers/RefundController.php:204-208
// Only invoices with these statuses can be refunded:
if (!in_array($invoicePaymentStatus, ['paid', 'unpaid', 'partial', 'partial refund'])) {
    Log::error('Invoice status of task ' . $task->reference . ' is ' . $invoicePaymentStatus);
    return redirect()->back()->withErrors([
        'error' => 'Invoice with payment status of ' . $invoicePaymentStatus . ' cannot be processed for refund yet.'
    ]);
}
```

---

## Partial Payment Logic

### InvoicePartials Table
The system uses a separate `InvoicePartials` table to track each payment segment:

```php
// Structure for each partial payment:
InvoicePartial::create([
    'invoice_id' => $invoice->id,
    'invoice_number' => $invoice->invoice_number,
    'client_id' => $invoice->client_id,
    'agent_id' => $invoice->agent_id,
    'amount' => $partialAmount,           // Amount of this specific payment
    'status' => 'paid' or 'unpaid',       // Status of this segment
    'type' => 'partial|split|unpaid_refund',
    'payment_gateway' => 'Knet|Cash|Credit',
    'payment_method' => 'Card|Direct Transfer',
    'gateway_fee' => $proportionalFee,
    'service_charge' => 0,
]);
```

### How Partial Payments Work
1. **First partial payment** (e.g., 50 KWD out of 100 KWD)
   - Invoice status changes from `unpaid` → `partial`
   - One `paid` InvoicePartial created for 50 KWD
   - Remaining 50 KWD balance can be collected later

2. **Second partial payment** (e.g., additional 50 KWD)
   - Invoice status remains `partial` until fully paid
   - Another `paid` InvoicePartial created for additional 50 KWD
   - When all partials sum to invoice total, can transition to `paid`

3. **Split payment** (credit + other gateway)
   - First InvoicePartial: `paid` status (credit portion)
   - Second InvoicePartial: `unpaid` status (other gateway portion)
   - Invoice status: `partial`

---

## Default Status for New Invoices

**Default Status: `unpaid`**

- **Set At**: Invoice creation time in `Invoice::create()`
- **Database Default**: `enum('paid', 'unpaid', 'partial', 'paid by refund', 'refunded', 'partial refund') DEFAULT 'unpaid'`
- **Code Locations**:
  1. `/home/soudshoja/soud-laravel/app/Http/Controllers/InvoiceController.php` line 1230
  2. `/home/soudshoja/soud-laravel/app/Http/Controllers/RefundController.php` line 2858
  3. `/home/soudshoja/soud-laravel/app/Http/Controllers/MobileController.php`
  4. Multiple other controllers

### Bulk Invoice Creation Pattern
```php
// Standard pattern used everywhere:
$invoice = Invoice::create([
    'invoice_number' => $invoiceNumber,
    'agent_id' => $agentId,
    'client_id' => $clientId,
    'amount' => $amount,
    'status' => 'unpaid',  // ← Always explicitly set to unpaid
    'invoice_date' => now(),
    'due_date' => $dueDate,
    // ... other fields
]);
```

---

## Integration Points for Bulk Upload

### Current Behavior
1. **Initial Status**: All uploaded invoices are created with `status = 'unpaid'`
2. **Payment at Creation**: No - invoices are always created as unpaid, payment is handled separately
3. **Payment Application**: Done after invoices exist via `PaymentApplicationService::applyPaymentsToInvoice()`

### Recommended for Bulk Upload Feature
```php
// Option 1: Standard Pattern (Current)
// - Create invoice with status = 'unpaid'
// - Apply payment in separate step

// Option 2: With Payment Support
$invoice = Invoice::create([
    'invoice_number' => $invoiceNumber,
    'status' => 'unpaid',  // Start as unpaid
    // ... other fields
]);

// Then apply payment if provided in upload data:
if (!empty($uploadData['payment_amount'])) {
    $paymentService->applyPaymentsToInvoice(
        invoiceId: $invoice->id,
        paymentAllocations: $uploadData['payments'],
        paymentMode: $uploadData['payment_mode'] ?? 'full'
    );
}

// Result: Invoice status will be updated to 'paid', 'partial', or remain 'unpaid'
// based on payment_mode and amount provided
```

### Validation Rules for Bulk Upload
```php
// Validate payment data structure:
$uploadData = $request->validate([
    'invoice_data.*.invoice_number' => 'required|string|unique:invoices',
    'invoice_data.*.amount' => 'required|numeric|min:0.01',
    'invoice_data.*.client_id' => 'required|exists:clients,id',
    'invoice_data.*.agent_id' => 'required|exists:agents,id',

    // Optional payment data:
    'invoice_data.*.payment_amount' => 'nullable|numeric|min:0',
    'invoice_data.*.payment_method' => 'nullable|in:cash,credit,knet,myfatoorah',
    'invoice_data.*.payment_mode' => 'nullable|in:full,partial,split',
]);

// If payment_amount >= amount → can set payment_mode = 'full'
// If payment_amount < amount → payment_mode = 'partial' or 'split'
// If no payment_amount → status remains 'unpaid'
```

---

## State Machine Summary

```
┌─────────────────────────────────────────────────────────────┐
│                   INVOICE STATUS FLOW                        │
└─────────────────────────────────────────────────────────────┘

                     ┌──────────┐
                     │  unpaid  │ ← DEFAULT for all new invoices
                     └─────┬────┘
                           │
                  ┌────────┴────────┐
                  │                 │
            Full Payment      Partial Payment
            (100%)            or Split Payment
                  │                 │
                  ▼                 ▼
            ┌────────┐        ┌──────────┐
            │  paid  │        │ partial  │
            └────┬───┘        └──────┬───┘
                 │                   │
          Has Refund Records   Additional Payment
                 │                   │
      ┌──────────┴──────────┐       │
      │                     │       │
   Some Tasks           All Tasks   │
   Refunded             Refunded    │
      │                     │       │
      ▼                     ▼       ▼
 ┌────────────┐        ┌─────────┐ │
 │partial     │        │refunded │ │
 │refund      │        └─────────┘ │
 └────────────┘                     │
                                    │
                          When all remaining
                          balance paid
                                    │
                                    ▼
                              ┌────────┐
                              │  paid  │
                              └────────┘

SPECIAL CASE: paid by refund
    └─ Only used when unpaid invoice refund is created
    └─ Original invoice locked (cannot edit)
    └─ Separate refund invoice tracks the refund process
```

---

## Database Schema

### Current Migration
File: `/home/soudshoja/soud-laravel/database/migrations/2026_01_14_163556_add_partial_refund_status_to_invoices_table.php`

```sql
ALTER TABLE invoices
MODIFY COLUMN status ENUM(
    'paid',
    'unpaid',
    'partial',
    'paid by refund',
    'refunded',
    'partial refund'
) DEFAULT 'unpaid'
```

### Related Fields
```sql
invoices table columns:
- status          → Current payment status (the 6 values above)
- paid_date       → Timestamp when invoice was fully paid
- payment_type    → Method used ('cash', 'credit', 'knet', 'split', 'partial', etc.)
- is_client_credit → Boolean: was this paid with client credit balance?

invoicePartials table:
- invoice_id      → Which invoice this partial belongs to
- amount          → Amount of this payment
- status          → 'paid' or 'unpaid' (status of this specific segment)
- type            → 'partial', 'split', 'unpaid_refund'
- payment_gateway → Which gateway was used for this segment
```

---

## Restrictions & Rules

### Cannot Edit Invoice When
- `status = 'paid'` → Invoice is fully paid (validated in controller)
- `status = 'paid by refund'` → Original invoice locked while refund is processed
- `status = 'refunded'` → Invoice is fully refunded, no longer applicable

### Can Edit Invoice When
- `status = 'unpaid'` → No payment received yet
- `status = 'partial'` → Some payment received, can still adjust or receive more payment

### Refund Eligibility
- Can only refund invoices with status: `paid`, `unpaid`, `partial`, `partial refund`
- Cannot refund invoices with status: `refunded`, `paid by refund`
- Refund triggers automatic status change to `partial refund` or `refunded`

---

## Key Observations for Bulk Upload

1. **Always Start as Unpaid**: System architecture expects invoices to be created as `unpaid`
2. **Payment Applied Separately**: Payment application is a separate transactional step after invoice creation
3. **Support Both Scenarios**:
   - Create invoice without payment → stays `unpaid`
   - Create invoice with payment data → applies payment immediately → status changes accordingly
4. **Use PaymentApplicationService**: Leverage existing service for consistency
5. **Validate Payment Data**: Ensure payment_mode matches the amounts provided
6. **Atomic Transactions**: Use `DB::beginTransaction()` to ensure invoice creation and payment application succeed together

---

## References

- **Enum Definition**: `/home/soudshoja/soud-laravel/app/Enums/InvoiceStatus.php`
- **Payment Logic**: `/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php`
- **Invoice Controller**: `/home/soudshoja/soud-laravel/app/Http/Controllers/InvoiceController.php`
- **Refund Logic**: `/home/soudshoja/soud-laravel/app/Http/Controllers/RefundController.php`
- **Model**: `/home/soudshoja/soud-laravel/app/Models/Invoice.php`
- **Migrations**: `/home/soudshoja/soud-laravel/database/migrations/202*_*_invoice*.php`

