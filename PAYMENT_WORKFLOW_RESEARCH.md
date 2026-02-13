# Invoice Payment Workflow Research

## Executive Summary
The Soud Laravel system implements a sophisticated payment processing workflow supporting multiple payment gateways (MyFatoorah, Tap, Hesabe, uPayment, Knet) and credit-based payments. Invoices transition through multiple payment states and can be paid via client credits, external gateways, or cash.

---

## Invoice Status Flow

### Invoice Status Enum Values
Located in: `app/Enums/InvoiceStatus.php`

```php
enum InvoiceStatus: string
{
    case PAID = 'paid';
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID_BY_REFUND = 'paid by refund';
    case REFUNDED = 'refunded';
    case PARTIAL_REFUND = 'partial refund';
}
```

### Status Lifecycle
```
UNPAID → [multiple paths depending on payment method]
↓
├─ PAID (full payment received)
├─ PARTIAL (partial payment received, balance remains)
├─ PAID_BY_REFUND (fully paid via refund credit)
├─ PARTIAL_REFUND (some amount refunded)
└─ REFUNDED (fully refunded)
```

---

## Key Models & Relationships

### 1. Invoice Model
**File**: `/home/soudshoja/soud-laravel/app/Models/Invoice.php`

Key fields:
- `status` - Invoice status (enum: paid, unpaid, partial, paid by refund, refunded, partial refund)
- `payment_type` - Type of payment (credit, cash, full, split, partial)
- `amount` - Total invoice amount
- `paid_date` - Date when invoice was fully paid
- `is_client_credit` - Boolean flag if paid via client credit

Key relationships:
```php
public function payment()           // HasOne - Related payment record
public function invoicePartials()   // HasMany - Payment split records
public function paymentApplications() // HasMany - Payment application audit trail
```

Key methods:
```php
public function getTotalPaidViaApplicationsAttribute()  // Sum of all payment applications
public function getRemainingBalanceAttribute()          // Amount still owed
public function isFullyPaidViaApplications()            // Check if paid
```

### 2. Payment Model
**File**: `/home/soudshoja/soud-laravel/app/Models/Payment.php`

Key fields:
- `amount` - Payment amount received
- `payment_gateway` - Gateway used (MyFatoorah, Tap, Hesabe, etc.)
- `payment_method_id` - Payment method (bank transfer, card, etc.)
- `voucher_number` - Unique identifier for payment
- `payment_date` - When payment was received
- `status` - Payment status (pending, completed, failed)
- `gateway_fee` - Fee charged by gateway
- `completed` - Boolean for completion status

Key relationships:
```php
public function invoice()              // BelongsTo - Related invoice
public function paymentApplications()  // HasMany - Where this payment was applied
public function credit()               // HasMany - Credit records for balance tracking
```

### 3. PaymentApplication Model (New - Audit Trail)
**File**: `/home/soudshoja/soud-laravel/app/Models/PaymentApplication.php`

Purpose: Track which payments were applied to which invoices (audit trail)

Key fields:
- `payment_id` - Which payment was applied
- `credit_id` - Source credit record
- `invoice_id` - Which invoice received the payment
- `invoice_partial_id` - Which partial was paid
- `amount` - Amount applied
- `applied_by` - User who applied payment
- `applied_at` - When applied

Key methods:
```php
public static function getTotalAppliedByPayment($paymentId)        // Total from one payment
public static function getTotalAppliedToInvoice($invoiceId)        // Total to one invoice
public static function getApplicationsForInvoice($invoiceId)       // History of payments
public static function getApplicationsFromPayment($paymentId)      // Which invoices this paid
public function isFromTopup(): bool                                // Is from topup credit
public function isFromRefund(): bool                               // Is from refund credit
```

### 4. InvoicePartial Model
**File**: (referenced in PaymentApplicationService)

Purpose: Track split payments (when invoice is paid via multiple methods)

Key fields:
- `invoice_id` - Parent invoice
- `amount` - Amount of this partial
- `status` - 'paid' or 'unpaid'
- `type` - 'full', 'partial', 'split'
- `payment_gateway` - Gateway used for this partial
- `payment_method` - Payment method used
- `service_charge` - Charge for this portion
- `gateway_fee` - Fee for this portion

### 5. Credit Model
**File**: `/home/soudshoja/soud-laravel/app/Models/Credit.php`

Purpose: Track balance changes (topups and usage)

Credit types:
```php
const INVOICE = 'Invoice';           // Using credit to pay invoice
const TOPUP = 'Topup';               // Client received credit via payment
const REFUND = 'Refund';             // Client received credit via refund
const INVOICE_REFUND = 'Invoice Refund';  // Refund deduction
```

How it works:
- When payment received → Creates TOPUP credit with positive amount
- When credit used to pay invoice → Creates INVOICE credit with negative amount
- When refund issued → Creates REFUND credit with positive amount

Key methods:
```php
static function getAvailableBalanceByPayment($paymentId)   // Remaining from payment
static function getAvailableBalanceByRefund($refundId)     // Remaining from refund
static function getAvailablePaymentsForClient($clientId)   // All available credits
```

---

## Payment Processing Workflow

### Flow Diagram

```
Invoice Created (UNPAID)
        ↓
   [Choose Payment Path]
        ↓
    ┌───┴───┬──────────────┬──────────┐
    ↓       ↓              ↓          ↓
  CREDIT  GATEWAY        CASH      SPLIT
    ↓       ↓              ↓          ↓
[App]    [Redirect]    [Manual]  [Partial+Gateway]
    ↓       ↓              ↓          ↓
   PAID  [Webhook]     [Manual]   PARTIAL
         [Callback]      ↓        +[Other]
            ↓           PAID       ↓
           PAID                   PAID
```

### Path 1: Client Credit Payment (Full)

**Service**: `PaymentApplicationService::applyPaymentsToInvoice()`
**File**: `/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php`

**Steps**:
1. Client has credit balance (from previous payment or refund)
2. User selects credit to apply via `applyPaymentsToInvoice()` endpoint
3. Request validated:
   ```php
   'invoice_id' => 'required|integer|exists:invoices,id',
   'payment_allocations' => 'required|array|min:1',
   'payment_allocations.*.credit_id' => 'required|integer|exists:credits,id',
   'payment_allocations.*.amount' => 'required|numeric|min:0.001',
   'payment_mode' => 'required|in:full,partial,split',
   'other_gateway' => 'nullable|string',           // For split mode
   'other_method' => 'nullable|string',            // For split mode
   'charge_id' => 'nullable|integer',              // For split mode
   ```

4. Validation checks amount based on mode:
   - **full**: Credit amount must >= invoice amount
   - **partial**: Credit amount must < invoice amount (leaves balance)
   - **split**: Credit amount < invoice amount + specify other gateway for rest

5. Transaction created with:
   - **InvoicePartial**: Record of how much paid via credit
   - **Credit**: Negative amount deducting from client's balance
   - **PaymentApplication**: Audit trail linking payment to invoice
   - **JournalEntry**: Accounting entries for COA

6. Invoice status updated:
   - **full** → `status = 'paid'`, `payment_type = 'credit'`, `is_client_credit = true`
   - **partial** → `status = 'partial'`, `payment_type = 'partial'`, `is_client_credit = true`
   - **split** → `status = 'partial'`, `payment_type = 'split'`, `is_client_credit = true`

**Code Example**:
```php
// From InvoiceController
$result = $service->applyPaymentsToInvoice(
    $request->input('invoice_id'),
    $request->input('payment_allocations'),  // [['credit_id' => 1, 'amount' => 100]]
    $request->input('payment_mode', 'full'),  // 'full', 'partial', or 'split'
    $options
);

// Result
[
    'success' => true,
    'message' => "Successfully paid invoice in full using 100 KWD credit.",
    'payment_mode' => 'full',
    'credit_applied' => 100,
    'remaining_amount' => 0,
    'applied_payments' => [...],
    'invoice_status' => 'paid',
]
```

### Path 2: External Gateway Payment

**Supported Gateways**:
- MyFatoorah (model: `MyFatoorahPayment`)
- Tap (model: `TapPayment`)
- Hesabe (model: `HesabePayment`)
- uPayment (model: `UpaymentPayment`)
- Knet (implicit support via PaymentMethod)

**High-level flow**:
1. User selects external gateway payment method
2. System creates `Payment` record with status 'pending'
3. Payment link/URL generated via gateway API
4. User redirected to gateway
5. After payment, webhook received from gateway
6. Webhook handler:
   - Updates Payment status to 'completed'
   - Creates Credit with TOPUP type
   - Creates InvoicePartial marking portion as paid
   - Updates Invoice status (PAID or PARTIAL depending on amount)

**Models involved**:
- `Payment` - Main payment record
- `MyFatoorahPayment` / `TapPayment` / etc - Gateway-specific data
- `Credit` - Balance tracking
- `InvoicePartial` - Payment split record

### Path 3: Cash Payment (Manual)

**Flow**:
1. User marks invoice as paid via cash
2. System creates manual Payment record
3. Creates InvoicePartial with payment_gateway = 'cash'
4. Marks invoice status as PAID
5. No external gateway interaction

### Path 4: Split Payment (Credit + Gateway)

**Flow**:
1. User specifies:
   - Amount to pay via client credit
   - Gateway for remaining amount
2. System applies credit first (creates InvoicePartial with status 'paid')
3. Creates second InvoicePartial for remaining amount (status 'unpaid')
4. Invoice status = 'partial'
5. When gateway payment received, second partial is marked paid and invoice becomes PAID

---

## Payment Application Service (Core Logic)

### Location
`/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php`

### Main Function: `applyPaymentsToInvoice()`

**Parameters**:
```php
public function applyPaymentsToInvoice(
    int $invoiceId,                    // Invoice to pay
    array $paymentAllocations,         // [['credit_id' => 1, 'amount' => 100], ...]
    string $paymentMode = 'full',      // 'full', 'partial', or 'split'
    array $options = []                // ['other_gateway' => 'cash', ...]
): array
```

**Key Operations** (lines 37-369):

1. **Invoice Generation COA** (lines 116-163)
   - Checks if invoice has been recorded in accounting system
   - If not, creates initial transaction and journal entries for invoice creation

2. **Process Each Payment Allocation** (lines 165-281)
   For each credit being applied:
   - Get available balance from credit source
   - Create InvoicePartial record (payment portion)
   - Create negative Credit record (deduct from balance)
   - Create PaymentApplication record (audit trail)

3. **Create Credit Payment COA** (lines 283-288)
   - Creates accounting journal entries:
     - DEBIT: Liabilities → Advances → Client → Payment Gateway
     - CREDIT: Accounts Receivable → Clients

4. **Update Invoice Status** (lines 290-342)
   ```php
   // Full payment
   if ($paymentMode === 'full') {
       $invoice->status = 'paid';
       $invoice->paid_date = now();
       $invoice->payment_type = 'credit';
       $invoice->is_client_credit = true;
   }

   // Split payment
   elseif ($paymentMode === 'split') {
       // Create unpaid partial for other gateway
       $invoice->status = 'partial';
       $invoice->payment_type = 'split';
   }

   // Partial payment
   elseif ($paymentMode === 'partial') {
       // Leave remaining balance
       $invoice->status = 'partial';
       $invoice->payment_type = 'partial';
   }
   ```

### Helper Methods

**`getAvailablePaymentsForClient($clientId)`**
- Returns all available credit balances for a client
- Used for displaying options in UI

**`validatePaymentSelection($paymentAllocations, $requiredAmount)`**
- Validates selected payments can cover amount
- Returns any issues and shortfall

**`getPaymentHistoryForInvoice($invoiceId)`**
- Retrieves all PaymentApplication records for invoice
- Shows which payments paid this invoice

**`getInvoiceHistoryForPayment($paymentId)`**
- Retrieves all invoices paid by a specific payment

**`linkPaymentsToInvoicePartial($invoice, $invoicePartial, $paymentAllocations)`**
- Used when InvoicePartial already exists
- Just creates Credit and PaymentApplication records

**`createCreditPaymentCOA($invoice, $appliedPayments, $totalAmount)`**
- Creates accounting journal entries
- Handles liability clearing and receivable reduction

---

## Invoice Controller Payment Methods

### Location
`/home/soudshoja/soud-laravel/app/Http/Controllers/InvoiceController.php` (lines 5204-5249)

### Main Endpoint: `applyPaymentsToInvoice()`

**Route**: `POST /apply-payments`
**Controller**: `InvoiceController::applyPaymentsToInvoice()`

**Request format**:
```json
{
    "invoice_id": 1,
    "payment_allocations": [
        {
            "credit_id": 5,
            "amount": 100
        }
    ],
    "payment_mode": "full",
    "other_gateway": "cash",      // Optional, for split
    "other_method": "Manual",     // Optional, for split
    "charge_id": 3                // Optional, for split
}
```

**Response**:
```json
{
    "success": true,
    "message": "Successfully paid invoice in full using 100 KWD credit.",
    "payment_mode": "full",
    "credit_applied": 100,
    "remaining_amount": 0,
    "applied_payments": [...],
    "invoice_status": "paid",
    "invoice_partials_created": 1
}
```

### Other Payment Methods in InvoiceController

**`handlePaymentTypeChange($invoice, $newPaymentType)`** (lines 4388-4425)
- Allows changing payment type ONLY for paid invoices
- Supports: credit ↔ cash, full ↔ credit
- Prevents changes for external gateway payments
- Updates COA entries

**Methods**:
- `changeCreditToCash()` - Changes credit payment to cash
- `changeCashToCredit()` - Changes cash payment to credit
- `changeFullToCredit()` - Changes full payment to credit
- `changeCreditToFull()` - Changes credit payment to full

---

## Database Tables Structure

### invoices table
```sql
├─ status ENUM('paid', 'unpaid', 'partial', 'paid by refund', 'refunded', 'partial refund')
├─ payment_type VARCHAR (credit, cash, full, split, partial)
├─ amount DECIMAL(10,3)
├─ paid_date TIMESTAMP
├─ is_client_credit BOOLEAN
├─ accept_payment BOOLEAN
└─ [payment gateway fields: account_number, bank_name, swift_no, iban_no]
```

### invoice_partials table
```sql
├─ invoice_id (FK to invoices)
├─ amount DECIMAL(10,3)
├─ status ENUM('paid', 'unpaid')
├─ type VARCHAR(split, partial, full)
├─ payment_gateway VARCHAR
├─ payment_method VARCHAR
├─ gateway_fee DECIMAL(10,3)
├─ service_charge DECIMAL(10,3)
└─ charge_id (FK to charges)
```

### payments table
```sql
├─ invoice_id (FK to invoices, nullable)
├─ voucher_number VARCHAR
├─ payment_gateway VARCHAR (MyFatoorah, Tap, Hesabe, etc.)
├─ payment_method_id (FK to payment_methods)
├─ amount DECIMAL(10,3)
├─ gateway_fee DECIMAL(10,3)
├─ status VARCHAR (pending, completed, failed)
├─ completed BOOLEAN
├─ payment_date TIMESTAMP
└─ payment_url VARCHAR
```

### credits table
```sql
├─ payment_id (FK to payments, nullable)
├─ refund_id (FK to refunds, nullable)
├─ invoice_id (FK to invoices, nullable)
├─ invoice_partial_id (FK to invoice_partials, nullable)
├─ type ENUM('Invoice', 'Topup', 'Refund', 'Invoice Refund')
├─ amount DECIMAL(10,3) [positive for topup/refund, negative for usage]
├─ gateway_fee DECIMAL(10,3)
├─ description VARCHAR
└─ [company/branch/client FKs]
```

### payment_applications table (Audit Trail)
```sql
├─ payment_id (FK to payments, nullable)
├─ credit_id (FK to credits, nullable)
├─ invoice_id (FK to invoices)
├─ invoice_partial_id (FK to invoice_partials, nullable)
├─ amount DECIMAL(10,3)
├─ applied_by (FK to users)
├─ applied_at TIMESTAMP
└─ notes VARCHAR
```

---

## Payment Methods Supported

### Payment Gateway Integrations
Located in: `/home/soudshoja/soud-laravel/app/Models/` (Gateway-specific models)

1. **MyFatoorah**
   - Model: `MyFatoorahPayment`
   - Supports: Cards, Bank Transfers, Apple Pay
   - Webhook handler for callback

2. **Tap**
   - Model: `TapPayment`
   - Supports: Cards, Wallet
   - Webhook integration

3. **Hesabe**
   - Model: `HesabePayment`
   - Mobile payment platform

4. **uPayment**
   - Model: `UpaymentPayment`
   - Digital payment platform

5. **Knet**
   - Via payment methods configuration
   - Payment Gateway integration

### Non-Gateway Methods
- **Client Credit** - Using available balance
- **Cash** - Manual entry
- **Bank Transfer** - Via payment method

---

## Integration Points for Bulk Upload

### For Invoice Payment Processing After Bulk Creation

When invoices are bulk uploaded/created, payment can be applied in these ways:

#### Option 1: Immediate Credit Payment (After Invoice Creation)

**Location to hook**: After invoices are created in bulk upload process

```php
// 1. Create invoices (existing process)
$invoice = Invoice::create([...]);

// 2. Apply credit payment
$paymentApplicationService = new PaymentApplicationService();
$result = $paymentApplicationService->applyPaymentsToInvoice(
    invoiceId: $invoice->id,
    paymentAllocations: [
        [
            'credit_id' => $clientCredit->id,  // Available credit
            'amount' => $invoice->amount
        ]
    ],
    paymentMode: 'full'  // or 'partial' / 'split'
);

if ($result['success']) {
    // Invoice now marked as PAID via credit
    Log::info("Invoice {$invoice->invoice_number} paid via credit");
}
```

#### Option 2: Deferred Gateway Payment

```php
// Create invoice
$invoice = Invoice::create([...]);

// Create payment link for external gateway
$payment = Payment::create([
    'invoice_id' => $invoice->id,
    'amount' => $invoice->amount,
    'payment_gateway' => 'MyFatoorah',
    'status' => 'pending'
]);

// Payment link will be sent to client
// Webhook will update status when payment received
```

#### Option 3: Mark as Paid Manually (Cash)

```php
$invoice = Invoice::create([...]);

// Mark as paid via cash
$invoice->update([
    'status' => 'paid',
    'payment_type' => 'cash',
    'paid_date' => now()
]);

// Create invoice partial record
InvoicePartial::create([
    'invoice_id' => $invoice->id,
    'amount' => $invoice->amount,
    'status' => 'paid',
    'payment_gateway' => 'cash',
    'type' => 'full'
]);
```

### Required Service Instance

**Service Class**: `App\Services\PaymentApplicationService`

**Methods to call**:
1. `applyPaymentsToInvoice()` - For credit payments
2. `validatePaymentSelection()` - To validate before applying
3. `getAvailablePaymentsForClient()` - To check available credits

### Key Parameters for Bulk Payment

```php
$paymentApplicationService = new PaymentApplicationService();

// Validate all payments can cover invoices
$validation = $paymentApplicationService->validatePaymentSelection(
    $paymentAllocations,     // [['credit_id' => X, 'amount' => Y], ...]
    $requiredAmount          // Total invoice amount
);

if (!$validation['valid']) {
    // Handle validation errors
    Log::error("Payment validation failed: " . json_encode($validation['issues']));
}

// Apply payments
$result = $paymentApplicationService->applyPaymentsToInvoice(
    $invoiceId,
    $paymentAllocations,
    $paymentMode,
    $options
);
```

---

## Important Notes

### Invoice Status Transitions

- Invoice cannot transition to PAID unless full amount is covered
- UNPAID → PARTIAL is valid (partial credit payment)
- PARTIAL → PAID is valid (additional payment covers remainder)
- Status changes are transactional (all-or-nothing)
- COA entries are created automatically with payment application

### Credit System

- Credits are tracked as positive (available) or negative (used) amounts
- One topup payment can pay multiple invoices
- Topup credits can be partially used
- Refund credits work same way as topup credits
- Balance calculations are done via SUM queries on Credit table

### Accounting Integration

- Every payment creates transactions and journal entries
- Accounts involved:
  - **Liabilities → Advances → Client → Payment Gateway** (DEBIT)
  - **Accounts Receivable → Clients** (CREDIT)
- COA is created when:
  1. Invoice is generated (invoice amount as receivable)
  2. Payment is applied (clears receivable, reduces advance)

### Validation Rules

When applying payments:
- Payment amount cannot exceed available balance
- Invoice amount cannot be over-paid (unless split mode)
- Credit must exist and belong to correct client
- Invoice must exist

### Logging

All payment operations are logged with:
- `[PAYMENT APPLICATION]` prefix
- Request details, validation, and results
- Error traces for debugging
- User ID who applied payment

---

## Code References

### Main Files
1. **PaymentApplicationService**: `/home/soudshoja/soud-laravel/app/Services/PaymentApplicationService.php` (786 lines)
2. **Invoice Model**: `/home/soudshoja/soud-laravel/app/Models/Invoice.php` (149 lines)
3. **Payment Model**: `/home/soudshoja/soud-laravel/app/Models/Payment.php` (182 lines)
4. **PaymentApplication Model**: `/home/soudshoja/soud-laravel/app/Models/PaymentApplication.php` (162 lines)
5. **Credit Model**: `/home/soudshoja/soud-laravel/app/Models/Credit.php` (240+ lines)
6. **InvoiceController**: `/home/soudshoja/soud-laravel/app/Http/Controllers/InvoiceController.php` (lines 5204-5249)

### Database Migrations
- Invoice table: `2024_10_29_063642_create_invoices_table.php`
- Invoice Partials: `2025_03_17_111603_create_invoice_partials_table.php`
- Payments: `2025_03_17_122129_create_payments_table.php`
- Credits: `2025_05_23_065747_rename_task_id_to_invoice_id_in_credits_table.php`
- Payment Applications: `2026_01_12_154855_create_payment_applications_table.php`
- Status Enum: `2025_09_06_102445_add_enum_value_for_status_in_invoices_table.php`

### Routes
- Apply payments: `POST /apply-payments` → `InvoiceController@applyPaymentsToInvoice`

---

## Questions/Gaps

1. **Payment Gateway Webhooks**: Where are webhook handlers for each gateway? (MyFatoorah, Tap, etc.)
   - Need to find callback URLs and webhook processing logic

2. **Invoice Refund Process**: How are refunds initiated and how do they create REFUND credits?
   - Related to `paid by refund` status

3. **Charge Configuration**: What is the relationship between Charge model and payment processing?
   - Used in split payment mode but unclear how charges determine payment methods

4. **Email Notifications**: When are payment emails sent?
   - Models exist: `PaymentMail`, `PaymentLinkEmail` but integration point unclear

5. **Payment Link Generation**: How are external payment links created and what triggers their generation?
   - Payment links stored in `payment_url` field but generation logic unclear

6. **Receipt Tracking**: The `invoice_receipt` table exists - how are receipts generated and linked to payments?
   - New functionality not fully documented
