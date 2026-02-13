# COA/Accounting Integration Research

## Executive Summary

The invoice system uses **double-entry bookkeeping** with automatic journal entry creation at two key moments:
1. **Invoice Generation** - Creates receivable (debit) and revenue (credit) entries
2. **Invoice Payment** - Clears receivable (credit) and records cash/asset receipt (debit)

All COA entries are created **automatically** through the `addJournalEntry()` method called during invoice operations.

---

## JournalEntry Model
**File**: `/home/soudshoja/soud-laravel/app/Models/JournalEntry.php`

### Fields
```php
protected $fillable = [
    'transaction_id',        // Links to Transaction (parent record)
    'company_id',           // Multi-tenant isolation
    'account_id',           // Chart of Accounts reference
    'branch_id',            // Branch allocation
    'invoice_id',           // Invoice reference
    'invoice_detail_id',    // Specific task/line item
    'transaction_date',     // When transaction occurred
    'description',          // Human-readable description
    'debit',                // Debit amount
    'credit',               // Credit amount
    'balance',              // Account balance at time of entry
    'voucher_number',       // Payment voucher reference
    'name',                 // Account name (denormalized)
    'type',                 // Entry type: receivable, payable, bank, charges
    'type_reference_id',    // Reference to related account
    'currency',             // Transaction currency
    'exchange_rate',        // Exchange rate if multi-currency
    'amount',               // Original amount
    'cheque_no',            // Cheque details (if applicable)
    'cheque_date',
    'bank_info',
    'auth_no',              // Payment gateway authorization
    'reconciled',           // Bank reconciliation flag
    'reconciled_ref_id',
    'task_id',              // Related task
    'original_currency',    // Multi-currency support
    'original_amount',
    'receipt_reference_number',
];
```

### Relationships
```php
public function account()           // belongsTo Account
public function referenceAccount()  // belongsTo Account (type_reference_id)
public function invoice()           // belongsTo Invoice
public function invoiceDetail()     // belongsTo InvoiceDetail
public function task()              // belongsTo Task
public function transaction()       // belongsTo Transaction (parent)
public function branch()            // belongsTo Branch
public function company()           // belongsTo Company
public function agent()             // hasOneThrough Agent
```

---

## Account Model
**File**: `/home/soudshoja/soud-laravel/app/Models/Account.php`

### Chart of Accounts Structure
Hierarchical tree structure with parent-child relationships:
- **Root Accounts**: Assets, Liabilities, Income, Expenses
- **Parent Accounts**: Accounts Receivable, Direct Income, etc.
- **Child Accounts**: Clients, Flight Booking Revenue, etc.

### Key Fields
```php
'serial_number',
'account_type',      // asset, liability, income, expense
'report_type',       // profit loss, balance sheet
'name',
'level',             // Hierarchy depth
'actual_balance',    // Current balance
'budget_balance',
'variance',
'parent_id',         // Parent account
'root_id',           // Root account
'company_id',        // Multi-tenant isolation
'code',              // Account code (e.g., 4110, 4115)
'currency',
'is_group',          // Is this a parent account?
'disabled',          // Active/inactive
```

### Account Types Used in Invoice System
1. **Accounts Receivable** → **Clients** (Asset - Debit increases)
2. **Direct Income** → **Flight/Hotel/Visa Booking Revenue** (Income - Credit increases)
3. **Liabilities** → **Advances** → **Client** → **Payment Gateway** (Liability - Credit increases)
4. **Expenses** → **Commissions Expense (Agents)** (Expense - Debit increases)
5. **Liabilities** → **Commissions (Agents)** (Liability - Credit increases)
6. **Assets** → **Gateway Accounts** (e.g., Tap, MyFatoorah) (Asset - Debit increases)
7. **Expenses** → **Gateway Expenses** (Expense - Debit increases)

---

## COA Entry Creation

### 📌 TIMING 1: When Invoice is Generated

**Trigger**: Invoice creation with status = 'paid' or 'partial'
**Method**: `InvoiceController@addJournalEntry()`
**File**: `/home/soudshoja/soud-laravel/app/Http/Controllers/InvoiceController.php` (lines 1292-1553)

#### Process Flow
1. **Create Transaction Record** (parent entry)
```php
Transaction::create([
    'company_id' => $task->company_id,
    'branch_id' => $agent->branch_id,
    'entity_id' => $task->company_id,
    'entity_type' => 'company',
    'transaction_type' => 'credit',
    'amount' => $invoice->amount,
    'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
    'invoice_id' => $invoice->id,
    'reference_type' => 'Invoice',
    'transaction_date' => $invoice->invoice_date,
]);
```

2. **For Each Task in Invoice** → Call `addJournalEntry()`
```php
foreach ($tasks as $task) {
    $response = $this->addJournalEntry(
        $task,
        $invoice->id,
        $invoiceDetail->id,
        $transaction->id,
        $invoice->client->full_name
    );
}
```

#### Journal Entries Created

##### ENTRY 1: DEBIT Accounts Receivable (Asset)
**Account Path**: Accounts Receivable → Clients
**Amount**: `$task->invoiceDetail->task_price`

```php
JournalEntry::create([
    'transaction_id' => $transactionId,
    'branch_id' => $task->agent->branch_id,
    'company_id' => $task->company_id,
    'account_id' => $clientAccount->id,      // "Clients" account
    'task_id' => $task->id,
    'agent_id' => $task->agent_id,
    'invoice_id' => $invoiceId,
    'invoice_detail_id' => $invoiceDetailId,
    'transaction_date' => $invoice->invoice_date,
    'description' => 'Invoice created for (Assets): ' . $clientName,
    'debit' => $task->invoiceDetail->task_price,  // DR
    'credit' => 0,
    'balance' => $clientAccount->balance,
    'name' => $clientAccount->name,
    'type' => 'receivable',
    'currency' => $task->currency,
    'exchange_rate' => $task->exchange_rate,
    'amount' => $task->invoiceDetail->task_price,
]);
```

##### ENTRY 2: CREDIT Booking Revenue (Income)
**Account Path**: Direct Income → Flight/Hotel/Visa Booking Revenue
**Amount**: `$task->invoiceDetail->task_price`
**Note**: Auto-creates revenue account if not exists

```php
$bookingAccountName = ucfirst($task->type) . ' Booking Revenue';
// e.g., "Flight Booking Revenue", "Hotel Booking Revenue"

JournalEntry::create([
    'transaction_id' => $transactionId,
    'branch_id' => $task->agent->branch_id,
    'company_id' => $task->company_id,
    'account_id' => $detailsAccount->id,     // e.g., "Flight Booking Revenue"
    'task_id' => $task->id,
    'agent_id' => $task->agent_id,
    'invoice_id' => $invoiceId,
    'invoice_detail_id' => $invoiceDetailId,
    'transaction_date' => $invoice->invoice_date,
    'description' => 'Invoice created for (Income): ' . $task->reference,
    'debit' => 0,
    'credit' => $task->invoiceDetail->task_price,  // CR
    'balance' => $detailsAccount->balance,
    'name' => $detailsAccount->name,
    'type' => 'payable',
    'currency' => $task->currency,
    'exchange_rate' => $task->exchange_rate,
    'amount' => $task->invoiceDetail->task_price,
]);
```

##### ENTRY 3 & 4: Commission Entries (Agent Types 2, 3, 4 Only)
**Only if**: Agent type = 2, 3, or 4 **AND** commission ≠ 0
**Amount**: `$profit * $agent->commission_rate`

**ENTRY 3: DEBIT Commission Expense (Expense)**
```php
JournalEntry::create([
    'account_id' => $commissionExpenseAccount->id,  // "Commissions Expense (Agents)"
    'description' => 'Agents Commissions for (Expenses): ' . $agent->name,
    'debit'  => $commission > 0 ? abs($commission) : 0,
    'credit' => $commission < 0 ? abs($commission) : 0,
    'type' => 'receivable',
    // ... other fields
]);
```

**ENTRY 4: CREDIT Commission Liability (Liability)**
```php
JournalEntry::create([
    'account_id' => $commissionLiabilityAccount->id,  // "Commissions (Agents)"
    'description' => 'Agents Commissions for (Liabilities): ' . $agent->name,
    'debit'  => $commission < 0 ? abs($commission) : 0,
    'credit' => $commission > 0 ? abs($commission) : 0,
    'type' => 'payable',
    // ... other fields
]);
```

---

### 📌 TIMING 2: When Invoice is Paid (via Credit Balance)

**Trigger**: `PaymentApplicationService@applyPaymentsToInvoice()`
**File**: `/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php` (lines 645-785)
**Method**: `createCreditPaymentCOA()`

#### Process Flow
1. **Check if Invoice Generation COA exists** (lines 116-163)
   - If not, creates it retroactively using `addJournalEntry()`
2. **Create Credit Payment COA** (AFTER credit applications)

#### Journal Entries Created

##### ENTRY 1: DEBIT Liability (Per Credit Source Used)
**Account Path**: Liabilities → Advances → Client → Payment Gateway
**Amount**: Amount from each voucher/credit source
**Note**: ONE entry PER voucher used

```php
// Creates multiple DEBIT entries (one per credit source)
foreach ($appliedPayments as $payment) {
    JournalEntry::create([
        'transaction_id' => $transaction->id,
        'branch_id' => $branchId,
        'company_id' => $companyId,
        'account_id' => $liabilityAccount->id,    // "Payment Gateway" (Liability)
        'invoice_id' => $invoice->id,
        'invoice_partial_id' => $invoicePartialId,
        'agent_id' => $invoice->agent_id,
        'transaction_date' => now(),
        'description' => "Apply Client Credit from {$voucherNumber}",
        'debit' => $amountApplied,                 // DR (reduces liability)
        'credit' => 0,
        'balance' => $liabilityAccount->actual_balance,
        'name' => $liabilityAccount->name,
        'type' => 'payable',
        'currency' => $invoice->currency,
    ]);
}
```

##### ENTRY 2: CREDIT Accounts Receivable (Single Total)
**Account Path**: Accounts Receivable → Clients
**Amount**: Total credit applied

```php
JournalEntry::create([
    'transaction_id' => $transaction->id,
    'branch_id' => $branchId,
    'company_id' => $companyId,
    'account_id' => $receivableAccount->id,        // "Clients" (Receivable)
    'invoice_id' => $invoice->id,
    'invoice_partial_id' => null,
    'agent_id' => $invoice->agent_id,
    'transaction_date' => now(),
    'description' => "Invoice {$invoice->invoice_number} paid via Client Credit",
    'debit' => 0,
    'credit' => $totalAmount,                      // CR (clears receivable)
    'balance' => $receivableAccount->actual_balance,
    'name' => $receivableAccount->name,
    'type' => 'receivable',
    'currency' => $invoice->currency,
]);
```

**Transaction Record**:
```php
Transaction::create([
    'company_id' => $companyId,
    'branch_id' => $branchId,
    'entity_id' => $invoice->client_id,
    'entity_type' => 'Client',
    'transaction_type' => 'debit',
    'amount' => $totalAmount,
    'description' => "Credit Payment for {$invoice->invoice_number}",
    'invoice_id' => $invoice->id,
    'reference_type' => 'Payment',
    'reference_number' => $invoice->invoice_number,
    'transaction_date' => now(),
]);
```

---

### 📌 TIMING 3: When Invoice is Paid (via Gateway Payment)

**Trigger**: Payment gateway callback → `PaymentController@handlePaymentCallback()`
**File**: `/home/soudshoja/soud-laravel/app/Http/Controllers/PaymentController.php` (lines 5579-5660)

#### Journal Entries Created

##### ENTRY 1: CREDIT Accounts Receivable
**Account Path**: Accounts Receivable → Clients
**Amount**: Total payment amount

```php
JournalEntry::create([
    'transaction_id' => $transaction->id,
    'branch_id' => $invoice->agent->branch->id,
    'company_id' => $companyId,
    'invoice_id' => $invoice->id,
    'account_id' => $receivableAccount->id,          // "Clients"
    'invoice_detail_id' => $invoiceDetail->id,
    'transaction_date' => now(),
    'description' => "Client payment received via {$gatewayName}",
    'debit' => 0,
    'credit' => $finalPaidAmount,                    // CR (clears receivable)
    'balance' => $invoiceDetail->task_price - $finalPaidAmount,
    'name' => $client->full_name,
    'type' => 'receivable',
    'voucher_number' => $payment->voucher_number,
]);
```

##### ENTRY 2: DEBIT Gateway Asset Account (Net Amount)
**Account Path**: Assets → Payment Gateway (e.g., Tap, MyFatoorah)
**Amount**: `$finalPaidAmount - $accountingFee`

```php
JournalEntry::create([
    'transaction_id' => $transaction->id,
    'branch_id' => $invoice->agent->branch->id,
    'company_id' => $companyId,
    'invoice_id' => $invoice->id,
    'account_id' => $gatewayAssetAccount->id,        // e.g., "Tap" (Asset)
    'invoice_detail_id' => $invoiceDetail->id,
    'transaction_date' => now(),
    'description' => 'Net payment received',
    'debit' => $netAmount,                           // DR (increase asset)
    'credit' => 0,
    'balance' => $gatewayAssetAccount->actual_balance + $netAmount,
    'name' => $gatewayAssetAccount->name,
    'type' => 'bank',
    'voucher_number' => $payment->voucher_number,
]);
```

##### ENTRY 3: DEBIT Gateway Fee Expense
**Account Path**: Expenses → Gateway Expenses
**Amount**: Gateway processing fee

```php
JournalEntry::create([
    'transaction_id' => $transaction->id,
    'branch_id' => $invoice->agent->branch->id,
    'company_id' => $companyId,
    'invoice_id' => $invoice->id,
    'account_id' => $gatewayExpenseAccount->id,      // "Gateway Expenses"
    'invoice_detail_id' => $invoiceDetail->id,
    'transaction_date' => now(),
    'description' => 'Company Pays Gateway Fee: ' . $gatewayExpenseAccount->name,
    'debit' => $accountingFee,                       // DR (expense)
    'credit' => 0,
    'balance' => $gatewayExpenseAccount->actual_balance + $accountingFee,
    'name' => $gatewayExpenseAccount->name,
    'type' => 'charges',
    'voucher_number' => $payment->voucher_number,
]);
```

---

## Visual Summary: Double-Entry Flow

### Invoice Generation
```
DEBIT:   Accounts Receivable → Clients        $100
CREDIT:  Direct Income → Flight Booking Rev   $100

If commission applies (agent types 2,3,4):
DEBIT:   Commissions Expense (Agents)         $15
CREDIT:  Commissions (Agents) - Liability     $15
```

### Payment via Credit Balance
```
DEBIT:   Liabilities → Payment Gateway        $100
CREDIT:  Accounts Receivable → Clients        $100
```

### Payment via Gateway (e.g., Tap)
```
CREDIT:  Accounts Receivable → Clients        $100
DEBIT:   Assets → Tap                         $97   (net)
DEBIT:   Expenses → Gateway Expenses          $3    (fee)
```

---

## Integration for Bulk Upload

### Automatic COA Creation
✅ **YES** - COA entries are created **automatically** when:
1. Invoice is generated with tasks
2. Invoice is paid via credit
3. Invoice is paid via payment gateway

### Timing of COA Creation
- **Invoice Generation COA**: Created immediately when invoice status = 'paid' or 'partial'
- **Credit Payment COA**: Created when `PaymentApplicationService@applyPaymentsToInvoice()` is called
- **Gateway Payment COA**: Created in payment gateway callback

### No Special Handling Needed
✅ **YES** - Bulk upload integration does NOT need special COA handling if:
1. You use the existing `InvoiceController@addJournalEntry()` method
2. You create invoices with proper task relationships
3. You use `PaymentApplicationService` for credit payments
4. You use payment gateway callbacks for gateway payments

### Critical Requirements for Bulk Upload

#### 1. Invoice Must Have Tasks
```php
// Each invoice needs related tasks with invoiceDetails
$invoice = Invoice::create([...]);

foreach ($tasks as $taskData) {
    $task = Task::create([...]);

    $invoiceDetail = InvoiceDetail::create([
        'invoice_id' => $invoice->id,
        'task_id' => $task->id,
        'task_price' => $taskData['price'],
        'profit' => 0,  // Calculated by addJournalEntry()
        'commission' => 0,  // Calculated by addJournalEntry()
    ]);
}
```

#### 2. Create Transaction + Call addJournalEntry()
```php
$transaction = Transaction::create([
    'company_id' => $task->company_id,
    'branch_id' => $agent->branch_id,
    'entity_id' => $task->company_id,
    'entity_type' => 'company',
    'transaction_type' => 'credit',
    'amount' => $invoice->amount,
    'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
    'invoice_id' => $invoice->id,
    'reference_type' => 'Invoice',
    'transaction_date' => $invoice->invoice_date,
]);

$invoiceController = app(\App\Http\Controllers\InvoiceController::class);

foreach ($tasks as $task) {
    $invoiceDetail = $invoice->invoiceDetails->firstWhere('task_id', $task->id);

    $response = $invoiceController->addJournalEntry(
        $task,
        $invoice->id,
        $invoiceDetail->id,
        $transaction->id,
        $client->full_name
    );
}
```

#### 3. Required Accounts Must Exist
Ensure these accounts exist in the COA:
- Accounts Receivable → Clients
- Direct Income → Flight/Hotel/Visa Booking Revenue (auto-created if missing)
- Commissions Expense (Agents) - if agent types 2,3,4
- Commissions (Agents) - if agent types 2,3,4
- Liabilities → Advances → Client → Payment Gateway (for credit payments)
- Assets → [Gateway Name] (for gateway payments)
- Expenses → Gateway Expenses (for gateway payments)

---

## Code Examples from Codebase

### Example 1: Invoice Generation with COA
**File**: `/home/soudshoja/soud-laravel/app/Http/Controllers/InvoiceController.php` (lines 1044-1082)

```php
// Create Transaction
$transaction = Transaction::create([
    'company_id' => $tasks[0]->company_id,
    'branch_id' => $tasks[0]->agent->branch_id,
    'entity_id' => $tasks[0]->company_id,
    'entity_type' => 'company',
    'transaction_type' => 'credit',
    'amount' =>  $invoice->amount,
    'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
    'invoice_id' => $invoice->id,
    'reference_type' => 'Invoice',
    'transaction_date' => $invoice->invoice_date,
]);

// Create Journal Entries for Each Task
foreach ($tasks as $task) {
    $invoiceDetail = $task->invoiceDetail ?: $invoice->invoiceDetails->firstWhere('task_id', $task->id);

    $response = $this->addJournalEntry(
        $task,
        $invoice->id,
        $invoiceDetail->id,
        $transaction->id,
        $invoice->client->full_name,
    );

    $response = json_decode($response->getContent(), true);

    if (!$response['success']) {
        throw new Exception('Failed to create journal entry: ' . ($response['message'] ?? 'Unknown error'));
    }
}
```

### Example 2: Credit Payment with COA
**File**: `/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php` (lines 116-163)

```php
// STEP 1A: Check if Invoice Generation COA exists
$invoiceGenerationCOAExists = Transaction::where('invoice_id', $invoice->id)
    ->where('reference_type', 'Invoice')
    ->exists();

if (!$invoiceGenerationCOAExists) {
    // Create Invoice Generation COA retroactively
    $transaction = Transaction::create([
        'company_id' => $invoice->agent?->branch->company_id,
        'branch_id' => $invoice->agent->branch_id,
        'entity_id' => $invoice->agent?->branch->company_id,
        'entity_type' => 'company',
        'transaction_type' => 'credit',
        'amount' =>  $invoice->amount,
        'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
        'invoice_id' => $invoice->id,
        'reference_type' => 'Invoice',
        'transaction_date' => $invoice->invoice_date,
    ]);

    $invoiceController = app(\App\Http\Controllers\InvoiceController::class);
    $clientName = $invoice->client?->full_name;

    $invoiceDetails = $invoice->invoiceDetails()->with('task.agent')->get();
    foreach ($invoiceDetails as $invoiceDetail) {
        if (!$invoiceDetail->task) continue;

        $task = $invoiceDetail->task;
        $invoiceController->addJournalEntry(
            $task,
            $invoice->id,
            $invoiceDetail->id,
            $transaction->id,
            $clientName
        );
    }
}

// STEP 2: Create Credit Payment COA
if ($creditApplied > 0 && !empty($appliedPayments)) {
    $this->createCreditPaymentCOA($invoice, $appliedPayments, $creditApplied);
}
```

### Example 3: Gateway Payment with COA
**File**: `/home/soudshoja/soud-laravel/app/Http/Controllers/PaymentController.php` (lines 5579-5660)

```php
// Create Transaction
$transaction = Transaction::create([
    'branch_id' => $invoice->agent->branch->id,
    'company_id' => $companyId,
    'entity_id' => $companyId,
    'entity_type' => 'company',
    'transaction_type' => 'debit',
    'amount' => $finalPaidAmount,
    'description' => "{$gatewayName} payment success: {$invoice->invoice_number}",
    'invoice_id' => $invoice->id,
    'payment_id' => $payment->id,
    'payment_reference' => $paymentReference,
    'reference_type' => 'Invoice',
    'transaction_date' => now(),
]);

// CREDIT Receivable (clear debt)
JournalEntry::create([
    'transaction_id' => $transaction->id,
    'account_id' => $receivableAccount->id,
    'description' => "Client payment received via {$gatewayName}",
    'debit' => 0,
    'credit' => $finalPaidAmount,
    'type' => 'receivable',
]);

// DEBIT Gateway Asset (net amount)
JournalEntry::create([
    'transaction_id' => $transaction->id,
    'account_id' => $gatewayAssetAccount->id,
    'description' => 'Net payment received',
    'debit' => $netAmount,
    'credit' => 0,
    'type' => 'bank',
]);

// DEBIT Gateway Fee Expense
JournalEntry::create([
    'transaction_id' => $transaction->id,
    'account_id' => $gatewayExpenseAccount->id,
    'description' => 'Company Pays Gateway Fee',
    'debit' => $accountingFee,
    'credit' => 0,
    'type' => 'charges',
]);
```

---

## Key Findings for Bulk Upload Implementation

### ✅ GOOD NEWS
1. **Automatic COA Creation**: System automatically creates journal entries when proper methods are called
2. **Reusable Methods**: `addJournalEntry()` can be called from any controller/service
3. **Retroactive COA**: PaymentApplicationService automatically creates missing Invoice Generation COA
4. **Commission Calculation**: Profit and commission are automatically calculated and stored
5. **Multi-Currency Support**: System handles currency conversion and exchange rates
6. **Multi-Task Invoices**: System supports invoices with multiple tasks (one journal entry per task)

### ⚠️ WATCH OUT FOR
1. **Task Relationships**: Each invoice MUST have related tasks with invoiceDetails
2. **Agent Type**: Commission entries only created for agent types 2, 3, 4
3. **Account Existence**: Required accounts must exist (except booking revenue - auto-created)
4. **Transaction First**: Always create Transaction record before JournalEntry records
5. **Invoice Status**: COA creation triggered when status = 'paid' or 'partial'

### 📋 BULK UPLOAD CHECKLIST
- [ ] Create Invoice with proper client_id, agent_id, currency
- [ ] Create related Tasks with proper type (flight, hotel, visa, etc.)
- [ ] Create InvoiceDetails linking invoice + task
- [ ] Create Transaction record (parent)
- [ ] Call `addJournalEntry()` for each task
- [ ] If using credit payment: Call `PaymentApplicationService@applyPaymentsToInvoice()`
- [ ] Verify accounts exist in COA before bulk upload

---

## Related Files

### Controllers
- `/home/soudshoja/soud-laravel/app/Http/Controllers/InvoiceController.php`
  - `addJournalEntry()` - Main COA creation method (lines 1292-1553)
  - `createCreditPaymentCOA()` - Credit payment COA (lines 1706-1860)
- `/home/soudshoja/soud-laravel/app/Http/Controllers/PaymentController.php`
  - Gateway payment COA (lines 5579-5660)

### Services
- `/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php`
  - `applyPaymentsToInvoice()` - Credit payment orchestration (lines 37-369)
  - `createCreditPaymentCOA()` - Credit payment COA (lines 645-785)

### Models
- `/home/soudshoja/soud-laravel/app/Models/JournalEntry.php`
- `/home/soudshoja/soud-laravel/app/Models/Account.php`
- `/home/soudshoja/soud-laravel/app/Models/Invoice.php`
- `/home/soudshoja/soud-laravel/app/Models/Transaction.php`
- `/home/soudshoja/soud-laravel/app/Models/InvoiceDetail.php`

---

## Conclusion

**The invoice system uses comprehensive double-entry bookkeeping that is FULLY AUTOMATED** through the `addJournalEntry()` method. For bulk upload integration:

1. **Use existing infrastructure** - Call `addJournalEntry()` for each task in the invoice
2. **Follow the pattern** - Transaction → Loop through tasks → addJournalEntry()
3. **No special handling** - COA entries are created automatically
4. **Verify prerequisites** - Ensure tasks, invoiceDetails, and accounts exist

**Bottom Line**: If you create invoices using the same pattern as the existing code (Transaction + addJournalEntry per task), COA integration will happen automatically with no additional work needed.
