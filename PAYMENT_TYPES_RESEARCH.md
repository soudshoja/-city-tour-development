# Payment Types & Charge System Research

## Executive Summary

A **Payment** in this system represents a **client topup/advance payment** or a **payment link transaction**. It is NOT the same as paying an invoice. When an invoice is paid, an **InvoicePartial** record is created, which may or may not link to a Payment record.

## Payment Model

**File**: `/home/soudshoja/soud-laravel/app/Models/Payment.php`

### Definition

A "Payment" in this system is:
1. **Client Topup/Advance Payment** - Money deposited by client into their account (creates Credit balance)
2. **Payment Link Transaction** - Online payment initiated via gateway (Tap, MyFatoorah, Hesabe, UPayment)
3. **Invoice Payment Gateway Transaction** - When client pays invoice through payment gateway

### Key Fields

| Field | Purpose | Example |
|-------|---------|---------|
| `voucher_number` | Unique payment reference | `VOU-2025-00123` |
| `amount` | Base payment amount (before fees) | `100.000` KWD |
| `service_charge` | Gateway fee charged to client | `2.500` KWD |
| `gateway_fee` | Actual accounting fee (may differ from service_charge) | `2.000` KWD |
| `status` | Payment status | `pending`, `initiate`, `completed`, `failed` |
| `payment_gateway` | Gateway used | `Tap`, `MyFatoorah`, `Hesabe`, `UPayment`, `Multi` |
| `payment_method_id` | Specific payment method | FK to `payment_methods` table |
| `payment_url` | Gateway payment link | `https://tap.company/pay/...` |
| `expiry_date` | Payment link expiration | `2025-02-14 10:30:00` |
| `invoice_id` | Optional link to invoice | `NULL` for topup, ID for invoice payment |
| `client_id` | Client making payment | FK to `clients` table |
| `agent_id` | Agent handling payment | FK to `agents` table |
| `completed` | Payment completed flag | `true`/`false` |
| `send_payment_receipt` | Auto-send receipt PDF | `true`/`false` |
| `terms_conditions` | Payment link T&C text | NULL or text |
| `language` | Payment link language | `ENG` or `ARB` |

### Relationships

```php
// Core relationships
belongsTo Client
belongsTo Agent
belongsTo Invoice (optional - NULL for topup payments)
belongsTo PaymentMethod
belongsTo User (created_by)

// Gateway-specific payment records
hasOne TapPayment
hasOne MyFatoorahPayment
hasOne HesabePayment

// Invoice connections
hasMany InvoicePartial (invoice payments using this Payment)
hasMany PaymentApplication (where this payment was applied to invoices)

// Topup/Credit system
hasMany Credit (topup credits and usage records)

// Payment link features
hasMany PaymentItem (line items for advanced payment links)
belongsToMany PaymentMethod via 'availablePaymentMethods' (multi-method links)
belongsToMany PaymentMethodGroup via 'availablePaymentMethodGroups'

// Other
hasMany PaymentTransaction
hasMany PaymentFile
hasOne HotelBooking (TBO integration)
morphMany Transaction
```

### When Payment Records Are Created

1. **Invoice Payment via Gateway** (`PaymentController@initiatePayment`, line 1186)
   - Client clicks "Pay" on invoice
   - Payment link is generated
   - Status: `pending` → `initiate` → `completed`

2. **Import Payment** (`PaymentController@paymentStoreImport`, line 1891)
   - Manual payment import from gateway transactions
   - Status: `completed` immediately
   - Creates Credit record automatically via `addCredit()`

3. **Payment Link** (`PaymentController@paymentStoreLinkProcess`, line 2410)
   - Standalone payment link (not tied to invoice)
   - Can have multiple items (advanced mode)
   - Status: `pending`

4. **Multi-Method Payment Link** (`PaymentController@multiPaymentMethodProcess`, line 6185)
   - Payment link with multiple gateway options
   - Status: `pending`

### Available Balance (for Topup Payments)

```php
// Calculated attribute
$payment->available_balance
// Returns: Sum of Credit records where type = 'Topup' minus used amounts
// Formula: Credit::where('payment_id', $id)->sum('amount')
```

### Payment Statuses

| Status | Description | When Used |
|--------|-------------|-----------|
| `pending` | Payment link created, awaiting user action | Initial state |
| `initiate` | Payment gateway link generated | After gateway API call |
| `completed` | Payment successfully processed | After gateway callback |
| `failed` | Payment failed | After gateway error |

---

## Charge Model

**File**: `/home/soudshoja/soud-laravel/app/Models/Charge.php`

### Purpose

A **Charge** represents a **payment gateway configuration** for a specific company/branch. It stores:
- Gateway API credentials
- Fee structure (fixed amount or percentage)
- Who pays the fee (Company or Client)
- Accounting integration (COA accounts for bank and fees)

### Key Fields

| Field | Purpose | Example |
|-------|---------|---------|
| `name` | Gateway name | `Tap`, `MyFatoorah`, `Hesabe`, `UPayment`, `Knet` |
| `type` | Gateway type (same as name usually) | `Tap` |
| `api_key` | Gateway API key | `sk_test_...` |
| `tran_portal_id` | Gateway merchant ID | `123456` |
| `tran_portal_password` | Gateway password | `secret123` |
| `terminal_resource_key` | Additional gateway config | `trk_...` |
| `paid_by` | Who pays the gateway fee | `Company` or `Client` |
| `amount` | Fixed fee amount | `2.500` KWD |
| `extra_charge` | Percentage fee | `0.025` (2.5%) |
| `self_charge` | Self-checkout charge | `0.000` |
| `charge_type` | Fee calculation type | `percentage`, `fixed`, `both` |
| `is_active` | Gateway enabled | `true`/`false` |
| `can_generate_link` | Link generation enabled | `true`/`false` |
| `can_charge_invoice` | Invoice charging enabled | `true`/`false` |
| `is_auto_paid` | Auto-payment on invoice creation | `true`/`false` |
| `has_url` | Gateway supports URLs | `true`/`false` |
| `is_system_default` | Default gateway for company | `true`/`false` |
| `can_be_deleted` | User can delete this config | `true`/`false` |
| `company_id` | Company owning this config | FK to `companies` |
| `branch_id` | Optional branch filter | FK to `branches` |
| `acc_bank_id` | COA: Bank account (Asset) | FK to `accounts` |
| `acc_fee_id` | COA: Fee expense account | FK to `accounts` |
| `acc_fee_bank_id` | COA: Fee bank account | FK to `accounts` |

### Relationships

```php
belongsTo Company
belongsTo Branch (optional)
belongsTo Account as 'accBank' (bank asset account)
belongsTo Account as 'accFee' (fee expense account)
belongsTo Account as 'accBankFee' (fee bank account)
hasMany PaymentMethod (specific payment methods under this gateway)
```

### How Charge Relates to Payment Gateways

**Charge is the gateway CONFIGURATION**, not the transaction itself:

1. **Company creates Charge record** for "MyFatoorah"
   - Stores API key, merchant credentials
   - Defines fee: 2.5% + 0.200 KWD
   - Sets paid_by: "Client"
   - Links to COA accounts for accounting

2. **Payment uses Charge** when client pays
   - System reads Charge config to get API key
   - Calculates fee based on Charge.extra_charge and Charge.amount
   - Creates Payment record with payment_gateway = "MyFatoorah"
   - Creates MyFatoorahPayment record with gateway transaction details

3. **Accounting uses Charge** for journal entries
   - Debits/credits correct COA accounts (acc_bank_id, acc_fee_id)
   - Records gateway fee as expense or income based on paid_by

### Methods

```php
// Check if gateway has API implementation in code
$charge->hasApiImplementation()
// Returns true for: Tap, MyFatoorah, Hesabe, UPayment

// Check if payment link can be generated (technical + business check)
$charge->canGeneratePaymentLink()
// Returns true if hasApiImplementation() && can_generate_link
```

### Implemented Gateways

| Gateway | Status | Location |
|---------|--------|----------|
| Tap | Implemented | `app/Support/PaymentGateway/Tap.php` |
| MyFatoorah | Implemented | `app/Support/PaymentGateway/MyFatoorah.php` |
| Hesabe | Implemented | `app/Support/PaymentGateway/Hesabe.php` |
| UPayment | Implemented | `app/Support/PaymentGateway/UPayment.php` |
| Knet | Implemented | `app/Support/PaymentGateway/Knet.php` |

---

## PaymentMethod Model

**File**: `/home/soudshoja/soud-laravel/app/Models/PaymentMethod.php`

### Purpose

A **PaymentMethod** represents a **specific payment option** under a gateway. For example:
- MyFatoorah Charge has PaymentMethods: Visa, Mastercard, Knet, Benefit
- Tap Charge has PaymentMethods: Visa, Mastercard, Amex

### Key Fields

| Field | Purpose | Example |
|-------|---------|---------|
| `charge_id` | Parent gateway | FK to `charges` |
| `myfatoorah_id` | Gateway-specific method ID | `1` (Knet), `2` (Visa), etc. |
| `arabic_name` | Arabic display name | `كي نت` |
| `english_name` | English display name | `Knet` |
| `code` | Method code | `KNET`, `VISA`, `MASTERCARD` |
| `type` | Gateway type | `MyFatoorah`, `Tap` |
| `is_active` | Method enabled | `true`/`false` |
| `currency` | Supported currency | `KWD` |
| `service_charge` | Method-specific fee | `2.500` |
| `self_charge` | Self-checkout fee | `0.000` |
| `paid_by` | Who pays fee | `Company` or `Client` |
| `charge_type` | Fee type | `percentage`, `fixed` |
| `description` | Method description | `Knet card payment` |
| `image` | Method logo URL | `/images/knet.png` |
| `payment_method_group_id` | Group classification | FK to `payment_method_groups` |
| `company_id` | Company-specific method | FK to `companies` |

### Relationships

```php
belongsTo Charge (parent gateway)
belongsTo Charge as 'gateways' (by type)
belongsTo Company
belongsTo PaymentMethodGroup
belongsToMany Payment via 'paymentLinks' (payment links using this method)
```

### How PaymentMethod Works

1. **Gateway Configuration** (Charge) has many **Payment Methods**
   - MyFatoorah Charge → Knet, Visa, Mastercard PaymentMethods
   - Each method has its own fee structure

2. **Client selects PaymentMethod** when paying
   - Payment.payment_method_id = PaymentMethod.id
   - System uses PaymentMethod.myfatoorah_id to call gateway API
   - Applies method-specific fees

3. **Multi-Method Payment Links**
   - Payment can have multiple PaymentMethods attached
   - Client chooses method on payment page
   - `payment_link_payment_method` pivot table tracks available methods

---

## Payment vs InvoicePartial

### Payment

**What it is:**
- A topup/advance payment from client
- A payment gateway transaction
- Creates a Credit balance

**When created:**
- Client makes advance payment
- Client pays invoice via gateway
- Manual payment import

**Does NOT directly pay invoices:**
- Payment creates Credit balance
- Credit is then applied to invoices via InvoicePartial

### InvoicePartial

**What it is:**
- A partial or full payment on a specific invoice
- The actual "invoice payment" record
- Can be paid via Credit, Gateway, or Manual

**When created:**
- Invoice is split for partial payments
- Invoice is paid (full or partial)
- Created automatically when applying Credit to invoice

**Fields:**

| Field | Purpose |
|-------|---------|
| `invoice_id` | Invoice being paid |
| `invoice_number` | Invoice number |
| `client_id` | Client paying |
| `amount` | Partial amount |
| `service_charge` | Gateway fee |
| `gateway_fee` | Actual fee |
| `status` | `pending`, `paid`, `failed` |
| `type` | `full`, `partial`, `deposit` |
| `charge_id` | Gateway config used |
| `payment_gateway` | Gateway name |
| `payment_method` | Method ID |
| `payment_id` | Link to Payment (if paid via gateway) |
| `receipt_voucher_id` | Link to receipt voucher |

**Relationship:**

```php
belongsTo Payment (optional - NULL if paid via Credit)
belongsTo Invoice
belongsTo Client
belongsTo Charge
belongsTo PaymentMethod
hasMany PaymentApplication (tracking credit application)
```

---

## Credit System

**File**: `/home/soudshoja/soud-laravel/app/Models/Credit.php`

### Purpose

Credits track client account balances and usage. Think of it as a **client ledger**.

### Credit Types

```php
const INVOICE = 'Invoice';         // Used credit (negative balance)
const TOPUP = 'Topup';             // Added credit (positive balance)
const REFUND = 'Refund';           // Refund credit (positive balance)
const INVOICE_REFUND = 'Invoice Refund'; // Invoice refund (positive balance)
```

### How Credits Work

**Scenario 1: Client makes advance payment (Topup)**
1. Payment record created with status = 'completed'
2. `ClientController@addCredit()` called
3. Credit record created:
   ```php
   Credit::create([
       'type' => 'Topup',
       'payment_id' => $payment->id,
       'amount' => 100.000,  // Positive amount
       'gateway_fee' => 2.500
   ]);
   ```
4. Client available balance: +100.000 KWD

**Scenario 2: Client uses credit to pay invoice**
1. InvoicePartial created with status = 'paid'
2. Credit record created:
   ```php
   Credit::create([
       'type' => 'Invoice',
       'invoice_partial_id' => $partial->id,
       'amount' => -50.000,  // Negative amount (usage)
   ]);
   ```
3. Client available balance: 100.000 - 50.000 = 50.000 KWD

**Scenario 3: Invoice refund**
1. Refund record created
2. Credit record created:
   ```php
   Credit::create([
       'type' => 'Refund',
       'refund_id' => $refund->id,
       'amount' => 30.000,  // Positive amount
   ]);
   ```
3. Client available balance: 50.000 + 30.000 = 80.000 KWD

### Available Balance Calculation

```php
// For specific payment
Credit::where('payment_id', $paymentId)->sum('amount')
// Returns: Topup amount + Usage amounts (negative)
// Example: 100.000 + (-50.000) = 50.000

// For client (all sources)
Credit::getAvailablePaymentsForClient($clientId)
// Returns array of available payments and refunds with balances
```

---

## PaymentApplication Model

**File**: `/home/soudshoja/soud-laravel/app/Models/PaymentApplication.php`

### Purpose

Tracks **which payments/credits were applied to which invoices**. This is the "payment allocation" record.

### Key Fields

| Field | Purpose |
|-------|---------|
| `payment_id` | Source payment (topup) |
| `credit_id` | Source credit record |
| `invoice_id` | Invoice receiving payment |
| `invoice_partial_id` | Specific partial payment |
| `amount` | Amount applied |
| `applied_by` | User who applied |
| `applied_at` | Application timestamp |
| `notes` | Application notes |

### How It Works

When applying credit to invoice:
1. Find available Payment with balance (via Credit records)
2. Create InvoicePartial for the invoice payment
3. Create negative Credit record (usage)
4. Create PaymentApplication linking Payment → Invoice

```php
PaymentApplication::create([
    'payment_id' => $payment->id,
    'credit_id' => $creditRecord->id,
    'invoice_id' => $invoice->id,
    'invoice_partial_id' => $partial->id,
    'amount' => 50.000,
    'applied_by' => Auth::id(),
    'applied_at' => now(),
]);
```

---

## Real-World Scenarios

### Scenario 1: Client Topup (Advance Payment)

**User Action:** Client wants to add 100 KWD to their account

**System Flow:**
1. Create Payment Link
   ```php
   Payment::create([
       'voucher_number' => 'VOU-2025-00123',
       'amount' => 100.000,
       'service_charge' => 2.500,
       'payment_gateway' => 'MyFatoorah',
       'payment_method_id' => 5, // Knet
       'status' => 'pending',
       'client_id' => 456,
       'agent_id' => 789,
   ]);
   ```

2. Client pays via gateway → Callback received

3. Update Payment status = 'completed'

4. Create Credit record (via `addCredit()`)
   ```php
   Credit::create([
       'type' => 'Topup',
       'payment_id' => $payment->id,
       'amount' => 100.000,
       'gateway_fee' => 2.500,
   ]);
   ```

5. Create Journal Entries:
   - **DEBIT** Asset (MyFatoorah Bank): 97.500 KWD
   - **DEBIT** Expense (Gateway Fee): 2.500 KWD
   - **CREDIT** Income (Fee Recovery): 2.500 KWD *(if client pays)*
   - **CREDIT** Liability (Client Advance): 100.000 KWD

**Result:**
- Payment record: ID=123, status='completed', amount=100.000
- Credit record: type='Topup', amount=100.000
- Client available balance: 100.000 KWD
- InvoicePartial: NONE
- Invoice: NONE

---

### Scenario 2: Invoice Payment via Gateway

**User Action:** Client pays invoice via MyFatoorah

**System Flow:**
1. Client clicks "Pay" on invoice
2. Create Payment record
   ```php
   Payment::create([
       'voucher_number' => 'VOU-2025-00124',
       'amount' => 50.000,
       'service_charge' => 1.250,
       'payment_gateway' => 'MyFatoorah',
       'status' => 'pending',
       'invoice_id' => 999,
       'client_id' => 456,
   ]);
   ```

3. Create InvoicePartial
   ```php
   InvoicePartial::create([
       'invoice_id' => 999,
       'amount' => 50.000,
       'service_charge' => 1.250,
       'payment_id' => $payment->id,
       'status' => 'pending',
   ]);
   ```

4. Generate payment link → Client pays

5. Update Payment status = 'completed'

6. Update InvoicePartial status = 'paid'

7. Create Credit record (Topup)
   ```php
   Credit::create([
       'type' => 'Topup',
       'payment_id' => $payment->id,
       'amount' => 50.000,
   ]);
   ```

8. Create Credit record (Invoice usage)
   ```php
   Credit::create([
       'type' => 'Invoice',
       'invoice_partial_id' => $partial->id,
       'amount' => -50.000,
   ]);
   ```

9. Create PaymentApplication
   ```php
   PaymentApplication::create([
       'payment_id' => $payment->id,
       'invoice_id' => 999,
       'invoice_partial_id' => $partial->id,
       'amount' => 50.000,
   ]);
   ```

**Result:**
- Payment: ID=124, status='completed', invoice_id=999
- InvoicePartial: ID=555, status='paid', payment_id=124
- Credit (Topup): +50.000
- Credit (Invoice): -50.000
- Net client balance change: 0
- Invoice: Marked as paid

---

### Scenario 3: Invoice Payment via Existing Credit

**User Action:** Client uses existing 100 KWD balance to pay 50 KWD invoice

**System Flow:**
1. Find available Payment with balance
   ```php
   $availablePayments = Credit::getAvailablePaymentsForClient($clientId);
   // Returns: [payment_id=123, available_balance=100.000]
   ```

2. Create InvoicePartial (NO payment_id)
   ```php
   InvoicePartial::create([
       'invoice_id' => 888,
       'amount' => 50.000,
       'service_charge' => 0,
       'payment_id' => null, // IMPORTANT: No Payment link
       'status' => 'paid',
   ]);
   ```

3. Create Credit record (usage)
   ```php
   Credit::create([
       'type' => 'Invoice',
       'payment_id' => 123, // Original topup payment
       'invoice_partial_id' => $partial->id,
       'amount' => -50.000,
   ]);
   ```

4. Create PaymentApplication
   ```php
   PaymentApplication::create([
       'payment_id' => 123, // Original topup
       'credit_id' => $creditRecord->id,
       'invoice_id' => 888,
       'invoice_partial_id' => $partial->id,
       'amount' => 50.000,
   ]);
   ```

**Result:**
- Payment: NONE created (used existing ID=123)
- InvoicePartial: ID=666, status='paid', payment_id=NULL
- Credit (Invoice): -50.000
- Client available balance: 100.000 - 50.000 = 50.000 KWD
- Invoice: Marked as paid

---

## For Bulk Invoice Upload

### Question: Do we create Payment records for bulk invoices?

**Answer: NO (by default)**

When bulk uploading invoices:
1. Invoice records are created
2. InvoicePartial records are created with status = 'unpaid'
3. NO Payment records are created
4. Client can later:
   - Pay via gateway (creates Payment)
   - Pay via credit (uses existing Payment)
   - Pay manually (creates receipt voucher, no Payment)

### Question: Do we create InvoicePartials?

**Answer: YES**

InvoicePartial is created:
1. When invoice is created (initial partial with full amount)
2. When invoice is split into multiple parts
3. When invoice is paid (marks partial as 'paid')

**Bulk upload creates:**
```php
foreach ($invoices as $invoiceData) {
    $invoice = Invoice::create([...]);

    InvoicePartial::create([
        'invoice_id' => $invoice->id,
        'amount' => $invoice->total_amount,
        'status' => 'unpaid',
        'type' => 'full',
    ]);
}
```

### Question: When should we create Payment for bulk invoices?

**Answer: Only if:**
1. Client paid in advance (create Payment with status='completed' + Credit)
2. Client will pay immediately via gateway (create Payment + payment link)
3. Manual payment import (create Payment with status='completed')

**DO NOT create Payment if:**
- Invoices are unpaid
- Invoices will be paid via existing credit
- Invoices are for record-keeping only

---

## Summary Table

| Action | Payment | InvoicePartial | Credit | PaymentApplication |
|--------|---------|----------------|--------|-------------------|
| Client topup | ✅ Created | ❌ No | ✅ +Amount | ❌ No |
| Invoice paid via gateway | ✅ Created | ✅ Created (paid) | ✅ +Amount, -Amount | ✅ Created |
| Invoice paid via credit | ❌ No (uses existing) | ✅ Created (paid) | ✅ -Amount | ✅ Created |
| Bulk invoice upload | ❌ No | ✅ Created (unpaid) | ❌ No | ❌ No |
| Manual payment import | ✅ Created | ❌ No | ✅ +Amount | ❌ No |
| Invoice refund | ❌ No | ❌ No | ✅ +Amount | ❌ No |

---

## Key Relationships Diagram

```
Client
  ├── Payment (topup/gateway transactions)
  │     ├── Credit (Topup: +Amount)
  │     ├── InvoicePartial (if paid via gateway)
  │     ├── PaymentApplication (where applied)
  │     └── Gateway Payment (TapPayment, MyFatoorahPayment, etc.)
  │
  ├── Invoice
  │     └── InvoicePartial (payment records)
  │           ├── Payment (optional - if via gateway)
  │           ├── Credit (Invoice: -Amount)
  │           └── PaymentApplication (credit allocation)
  │
  ├── Credit (balance ledger)
  │     ├── Topup: +Amount (from Payment)
  │     ├── Invoice: -Amount (to InvoicePartial)
  │     ├── Refund: +Amount (from Refund)
  │     └── Invoice Refund: +Amount
  │
  └── PaymentApplication (payment allocation tracking)
        ├── Payment (source)
        ├── Credit (credit record)
        ├── Invoice (destination)
        └── InvoicePartial (specific partial)
```

---

## Accounting Flow (addCredit)

**File**: `/home/soudshoja/soud-laravel/app/Http/Controllers/ClientController.php` (line 993-1192)

When Payment status = 'completed', `addCredit()` is called:

### Who Pays Fee: Company

**Journal Entries:**
1. **DEBIT** Asset (Gateway Bank): Amount - Fee
2. **DEBIT** Expense (Gateway Fee): Fee
3. **CREDIT** Liability (Client Advance): Amount

**Example:** 100 KWD payment, 2.50 KWD fee, Company pays
- DEBIT MyFatoorah Bank (Asset): 97.50
- DEBIT Gateway Fee Expense: 2.50
- CREDIT Client Advance (Liability): 100.00

**Client Credit:** 100.00 KWD

---

### Who Pays Fee: Client

**Journal Entries:**
1. **DEBIT** Asset (Gateway Bank): Amount
2. **DEBIT** Expense (Gateway Fee): Fee
3. **CREDIT** Income (Fee Recovery): Fee
4. **CREDIT** Liability (Client Advance): Amount

**Example:** 100 KWD payment, 2.50 KWD fee, Client pays
- DEBIT MyFatoorah Bank (Asset): 100.00
- DEBIT Gateway Fee Expense: 2.50
- CREDIT Gateway Fee Recovery (Income): 2.50
- CREDIT Client Advance (Liability): 100.00

**Client Credit:** 100.00 KWD

---

## Files Reference

### Models
- `/home/soudshoja/soud-laravel/app/Models/Payment.php`
- `/home/soudshoja/soud-laravel/app/Models/Charge.php`
- `/home/soudshoja/soud-laravel/app/Models/PaymentMethod.php`
- `/home/soudshoja/soud-laravel/app/Models/InvoicePartial.php`
- `/home/soudshoja/soud-laravel/app/Models/Credit.php`
- `/home/soudshoja/soud-laravel/app/Models/PaymentApplication.php`

### Controllers
- `/home/soudshoja/soud-laravel/app/Http/Controllers/PaymentController.php`
- `/home/soudshoja/soud-laravel/app/Http/Controllers/ClientController.php`

### Services
- `/home/soudshoja/soud-laravel/app/Services/ChargeService.php`

### Gateway Implementations
- `/home/soudshoja/soud-laravel/app/Support/PaymentGateway/Tap.php`
- `/home/soudshoja/soud-laravel/app/Support/PaymentGateway/MyFatoorah.php`
- `/home/soudshoja/soud-laravel/app/Support/PaymentGateway/Hesabe.php`
- `/home/soudshoja/soud-laravel/app/Support/PaymentGateway/UPayment.php`
- `/home/soudshoja/soud-laravel/app/Support/PaymentGateway/Knet.php`

### Migrations
- `/home/soudshoja/soud-laravel/database/migrations/2025_03_17_122129_create_payments_table.php`

---

## Conclusion

**Payment** = Client topup or gateway transaction (creates credit balance)
**InvoicePartial** = Actual invoice payment record
**Credit** = Client account ledger (balance tracking)
**PaymentApplication** = Payment allocation tracking
**Charge** = Gateway configuration (API keys, fees, COA)
**PaymentMethod** = Specific payment option (Visa, Knet, etc.)

For bulk invoice upload: Create invoices + InvoicePartials (unpaid), but NOT Payment records unless client paid in advance.
