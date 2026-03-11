# Payment Gateway & invoice_partials Research

## Executive Summary
Soud Laravel integrates **5 major payment gateways** (Tap, MyFatoorah, Hesabe, uPayment, Knet) using a modular architecture. The `invoice_partials` table is a critical bridge between invoices and payment processing, enabling split payments, partial invoicing, and payment method tracking with gateway fees.

---

## invoice_partials Table

### Schema & Evolution
Created: `2025_03_17_111603_create_invoice_partials_table.php`

**Current Full Schema** (after all migrations):
```
- id (PK)
- invoice_id (FK to invoices)
- invoice_number (string)
- client_id (FK to clients)
- amount (decimal 15,2) - Base amount to pay
- service_charge (decimal 10,3) - Added 2025_06_05
- gateway_fee (decimal 10,3) - Added 2026_02_02
- status (string) - Payment status tracking
- type (string) - Partial type (invoice/topup/credit)
- expiry_date (date) - Payment link expiry
- charge_id (FK to charges) - Added 2025_09_17 (gateway config link)
- payment_gateway (string) - Gateway name (tap/myfatoorah/hesabe/knet/upayment)
- payment_method (FK to payment_methods) - Added 2025_05_30 (specific method: card/bank/wallet)
- payment_id (FK to payments) - Changed 2025_03_24 (now required via migration 2025_11_11)
- receipt_voucher_id (FK to invoice_receipts) - Added 2026_01_08
- created_at, updated_at (timestamps)
```

**Key Migrations Affecting invoice_partials:**
1. `2025_03_17_161538` - Foreign key constraint updates
2. `2025_03_24_111410` - Refactored payment_id relationship
3. `2025_04_14_142542` - Made expiry_date nullable
4. `2025_05_28_034401` - Added credit invoice_partial linking
5. `2025_05_30_123447` - Added payment_method field
6. `2025_06_05_112528` - Added service_charge column
7. `2025_07_02_115224` - Added charge_payer, base_amount
8. `2025_09_17_060331` - Added has_payment_link (later dropped)
9. `2025_09_17_165242` - Added charge_id foreign key (maps to Charge model for gateway config)
10. `2025_10_08_183612` - Added receipt_voucher_id link
11. `2026_02_02_131951` - Added gateway_fee tracking

### Purpose & Business Logic

**invoice_partials** is a **payment state machine** that tracks:

1. **Invoice Decomposition**: Break an invoice into multiple payment parts
   - Single invoice can have multiple partial payments
   - Each partial can use different payment gateway/method
   - Example: 50% upfront via Tap card, 50% via Hesabe bank transfer

2. **Payment Gateway Metadata**:
   - Links to specific gateway (payment_gateway column: 'tap', 'myfatoorah', 'hesabe', 'knet', 'upayment')
   - Links to payment method (e.g., visa card, bank transfer, wallet)
   - Tracks gateway configuration via charge_id (links to Charges table)
   - Stores gateway fees charged to client/company

3. **Payment Link Management**:
   - Each partial can have an expiring payment link
   - expiry_date drives payment link validity (typically 2-7 days)
   - Used for client self-service payment via email/SMS

4. **Audit & Accounting**:
   - service_charge: Commission/markup added (may go to company)
   - gateway_fee: Gateway transaction cost (may be passed to client)
   - Linked to receipt vouchers for accounting entries
   - Payment applications link back for settlement tracking

5. **Credit Management**:
   - Partial refunds/credits can be recorded as new invoices
   - credit_id in credits table references invoice_partial_id

### Database Relationships

```
InvoicePartial
├── belongsTo: Invoice
├── belongsTo: Client
├── belongsTo: Payment (via payment_id - FK constraint)
├── belongsTo: PaymentMethod (via payment_method)
├── belongsTo: Charge (via charge_id - gateway config)
├── hasOne: InvoiceReceipt (via receipt_voucher_id)
├── hasMany: PaymentApplications (payments applied to this partial)
└── referenced by: Credits (invoice_partial_id in credits table)
```

### Sample Data Structure

```
Example: Invoice #INV001 for 1,000 KWD
├── InvoicePartial #1: 500 KWD (50%) + 15 KWD service charge = 515 KWD
│   - payment_gateway: 'tap'
│   - payment_method: 'visa_card' (Charge class ID reference)
│   - gateway_fee: 5.00 KWD
│   - status: 'pending' → 'processing' → 'paid'
│   - expiry_date: 2026-03-01 23:59:59
│   - payment_id: Links to Payment record created for this link
│   - Awaiting client payment via generated payment URL
│
└── InvoicePartial #2: 500 KWD (50%) + 10 KWD service charge = 510 KWD
    - payment_gateway: 'hesabe'
    - payment_method: 'bank_transfer'
    - gateway_fee: 3.50 KWD
    - status: 'pending'
    - expiry_date: NULL (no auto-generated link)
    - payment_id: Manual payment when bank transfer received
```

---

## Payment Gateways Configured

### 1. **Tap** (Primary - Card Payments)
**Location**: `/home/soudshoja/soud-laravel/app/Support/PaymentGateway/Tap.php`

**Configuration**:
- **Env Variables**: `TAP_SANDBOX_SECRET`, `TAP_SANDBOX_PUBLIC`, `TAP_SECRET`, `TAP_PUBLIC`
- **URL**: `TAP_URL=https://api.tap.company`
- **Config Service**: `GatewayConfigService::getTapConfig()`
- **Database**: Can override via `Charge` model (where `name LIKE '%tap%'` and `is_active=true`)

**Capabilities**:
- Charge creation with embedded card/payment source selection
- Metadata tracking: invoice_number, voucher_number, payment_id, invoice_partial_id
- Currency: KWD (hardcoded)
- Merchant ID: 23428929 (hardcoded)
- Charge retrieval for status checking

**Key Methods**:
```php
createCharge(Request $request)  // POST /charges
  - Accepts: finalAmount, client_name, invoice_id, payment_id, invoice_partial_id
  - Returns: Charge ID, redirect URL if hosted

getCharge($chargeId)            // GET /charges/{chargeId}
  - Retrieves charge status
```

**Use Case**: Quick online card payments for travel agencies

---

### 2. **MyFatoorah** (Multi-Method Hub)
**Location**: `/home/soudshoja/soud-laravel/app/Support/PaymentGateway/MyFatoorah.php`

**Configuration**:
- **Env Variables**:
  - `MYFATOORAH_SANDBOX_URL=https://apitest.myfatoorah.com`
  - `MYFATOORAH_SANDBOX_KEY`
  - `MYFATOORAH_LIVE_URL=https://api.myfatoorah.com`
  - `MYFATOORAH_LIVE_URL_SA`, `_QA`, `_EG` (regional variants)
  - `MYFATOORAH_LIVE_KEY`
- **Config Service**: `GatewayConfigService::getMyFatoorahConfig()`
- **Database**: Can override via `Charge` model

**Capabilities**:
- Multi-payment method support (card, bank transfer, wallet, etc.)
- Payment method ID from PaymentMethod table (`myfatoorah_id`)
- Invoice creation with 2-day expiry (hardcoded in code)
- Customer data: name, email, phone, country code
- Invoice items tracking (line items in invoice)

**Key Methods**:
```php
createCharge(Request $request)           // POST /ExecutePayment
  - Accepts: final_amount, client_name, invoice_number, payment_method_id, invoice_partial_id
  - Returns: InvoiceId, PaymentURL, ExpiryDate

getPaymentStatus(string $type, string $key)  // POST /GetPaymentStatus
  - Types: 'invoice', 'payment', 'reference'
  - Returns: Full payment status object
```

**Integration Points**:
- Multi-payment flows (can process different methods in sequence)
- Webhook callback at `route('payments.callback')`
- Error URL: `route('payments.error', ['payment_id' => $payment->id])`

---

### 3. **Hesabe** (Encrypted Bank Transfer)
**Location**: `/home/soudshoja/soud-laravel/app/Support/PaymentGateway/Hesabe.php`

**Configuration**:
- **Env Variables**: None in `.env` (config-driven or DB)
- **Config Service**: `GatewayConfigService::getHesabeConfig()`
- **Encryption**: Uses AES encryption via `HesabeCrypt` service
  - Requires: `api_key`, `iv_key` (initialization vector)
  - Additional: `merchant_code`, `access_code`

**Capabilities**:
- Bank transfer payments (local/regional)
- Encrypted request/response handling (security-focused)
- Custom metadata fields (variable1, variable2, variable3)
- Batch invoice support

**Key Methods**:
```php
createCharge(Request $request)     // POST /checkout (encrypted)
  - Accepts: final_amount, client_name, invoice_number, type (invoice/topup), invoice_partial_id
  - Payload: AES-128-CBC encrypted JSON
  - Returns: payment_url, token, order_reference

getPaymentStatus(string $token)    // GET /api/transaction/{token}
  - Returns: Transaction status with decrypted data
```

**Special Fields**:
- `variable1`: Type (invoice or topup)
- `variable2`: invoice_partial_id (for invoice type)
- Webhook: `route('payment.hesabe.webhook')`
- Response: `route('payment.hesabe.response')`
- Failure: `route('payment.hesabe.failure')`

---

### 4. **Knet** (Kuwait National ePayment)
**Location**: `/home/soudshoja/soud-laravel/app/Support/PaymentGateway/Knet.php`

**Configuration**:
- **Env Variables**: None (DB-driven only)
- **Config Source**: `Charge` model (required)
  - Fields: `tran_portal_id`, `tran_portal_password`, `terminal_resource_key`
- **Service URL**: `config('services.knet.url')`

**Capabilities**:
- Kuwait-specific payment gateway
- AES-128-CBC encryption (binary to hex encoding)
- UDF fields (User Defined Fields) for metadata tracking:
  - udf1: payment_id
  - udf2: voucher_number
  - udf3: company_id
  - udf4: invoice_number
  - udf5: invoice_partial_id

**Key Methods**:
```php
createCharge(Request $request)   // Builds encrypted param string
  - Returns: redirect_url, track_id

decryptResponse($encryptedData)  // Decrypts Knet response
  - Returns: Parsed response array
```

**Initialization**:
```php
new Knet($companyId)  // Requires company_id, throws if config missing
```

**Flow**:
1. Generate trackId: `TRK{timestamp}{random}`
2. Encrypt params (amount, URLs, UDFs)
3. Redirect to Knet payment page
4. Knet redirects back to response/error URLs with encrypted data

---

### 5. **uPayment** (Alternative Payment Hub)
**Location**: `/home/soudshoja/soud-laravel/app/Support/PaymentGateway/UPayment.php`

**Configuration**:
- **Env Variables**:
  - `UPAYMENT_SANDBOX_KEY`, `UPAYMENT_SANDBOX_URL=https://sandboxapi.upayments.com/api`
  - `UPAYMENT_LIVE_KEY`, `UPAYMENT_LIVE_URL=https://api.upayment.com`
- **Config Service**: `GatewayConfigService::getUPaymentConfig()`

**Capabilities**:
- Multi-gateway support (Knet default via `paymentGateway.src`)
- Order/invoice linking
- 48-hour payment link expiry (2880 minutes)
- Customer tokenization support

**Key Methods**:
```php
makeCharge(Request $request)      // POST /charge
  - Accepts: final_amount, client_id, invoice_id, payment_id, invoice_partial_id, currency
  - Returns: Payment link, transaction ID

getPaymentStatus($trackId)        // GET /get-payment-status/{trackId}
  - Returns: Payment status object
```

**Request Structure**:
```json
{
  "order": {
    "id": "123",
    "reference": "INV001",
    "currency": "KWD",
    "amount": 1000.00
  },
  "paymentGateway": { "src": "knet" },
  "customer": { "uniqueId", "name", "email", "mobile" },
  "returnUrl": "...",
  "cancelUrl": "...",
  "notificationUrl": "...",
  "paymentLinkExpiryInMinutes": 2880
}
```

---

## Payment Gateway Integration Code Flow

### Architecture Overview

```
PaymentController
├── initiatePayment($data)
│   └── Routes to gateway based on payment_gateway
│       ├── Tap::createCharge()
│       ├── MyFatoorah::createCharge()
│       ├── Hesabe::createCharge()
│       ├── Knet::createCharge()
│       └── UPayment::makeCharge()
│
├── Webhook handlers (callbacks)
│   ├── tapCallback() → updates Payment status
│   ├── myFatoorahCallback() → creates PaymentTransaction
│   ├── hesabeCallback() → processes encrypted response
│   ├── knetCallback() → decrypts Knet response
│   └── uPaymentCallback() → processes uPayment response
│
└── Status checking
    └── Periodic jobs to check payment status
```

### Typical Payment Flow

```
1. Invoice Created
   └─ Amount: 1,000 KWD

2. Create InvoicePartial
   ├─ amount: 500 KWD (50%)
   ├─ service_charge: 15 KWD
   ├─ gateway_fee: 5 KWD
   ├─ total_amount: 520 KWD
   ├─ payment_gateway: 'tap'
   ├─ payment_method: 'visa_card'
   └─ status: 'pending'

3. Create Payment Record
   ├─ amount: 520 KWD
   ├─ payment_gateway: 'tap'
   ├─ payment_method_id: <from PaymentMethod table>
   ├─ invoice_id: <original invoice>
   └─ status: 'pending'

4. Generate Payment Link
   ├─ Call: Gateway::createCharge(Request)
   │   ├─ Pass: payment_id, invoice_partial_id, final_amount, currency
   │   └─ Receive: payment_url, expiry_date
   └─ Store in Payment.payment_url, Payment.expiry_date

5. Client Pays
   ├─ Clicks payment link
   ├─ Completes payment on gateway
   └─ Gateway redirects to callback URL

6. Webhook/Callback Received
   ├─ Parse gateway response
   ├─ Verify transaction
   └─ Update Payment.status = 'completed'

7. Accounting Entry Created
   ├─ Create JournalEntry (debit: bank, credit: accounts_receivable)
   ├─ Create Transaction record
   ├─ Record gateway_fee to expense account
   └─ Mark invoice as paid
```

### Charge Creation Request Structure

**Generic Template** (varies by gateway):
```php
$chargeRequest = [
    'finalAmount'          => decimal,           // Total with charges
    'client_name'          => string,            // Customer name
    'client_email'         => string|null,       // Customer email
    'client_phone'         => string|null,       // Customer phone
    'invoice_id'           => int|null,          // Original invoice
    'invoice_number'       => string,            // Invoice reference
    'payment_id'           => int,               // Payment record ID
    'payment_gateway'      => string,            // 'tap'|'myfatoorah'|'hesabe'|'knet'|'upayment'
    'payment_method_id'    => int,               // PaymentMethod record ID
    'invoice_partial_id'   => int|null,          // InvoicePartial record ID
    'description'          => string,            // Payment description
    'currency'             => 'KWD'|other,      // Currency code
    'type'                 => 'invoice'|'topup', // Hesabe only
    'voucher_number'       => string|null,       // Knet/Tap
    'company_id'           => int,               // Company context (Knet required)
];
```

### Payment Status Resolution

**Current Status Tracking**:
- `Payment.status`: pending → processing → completed → failed → refunded
- `Payment.completed`: boolean flag
- `InvoicePartial.status`: pending → paid → cancelled

**Check Methods**:
```php
// Tap
$tap->getCharge($chargeId)

// MyFatoorah
$myfatoorah->getPaymentStatus('invoice', $invoiceId)
$myfatoorah->getPaymentStatus('payment', $paymentId)

// Hesabe
$hesabe->getPaymentStatus($token)

// UPayment
$upayment->getPaymentStatus($trackId)

// Knet
// Uses callback only (no direct status query)
```

---

## GatewayConfigService

**Location**: `/home/soudshoja/soud-laravel/app/Services/GatewayConfigService.php`

**Purpose**: Centralized gateway configuration management

**Methods**:
```php
getTapConfig()           // Returns: ['status', 'data' => ['secret', 'url']]
getMyFatoorahConfig()    // Returns: ['status', 'data' => ['api_key', 'base_url']]
getHesabeConfig()        // Returns: ['status', 'data' => [...]]
getUPaymentConfig()      // Returns: ['status', 'data' => [...]]
// Knet handled differently (per-company in Charge table)
```

**Configuration Precedence**:
1. **Database**: `Charge` model (company-specific, can be hot-swapped)
2. **Config File**: `config/services.php` (fallback)
3. **Environment**: `.env` (legacy, for simple setups)

**Benefits**:
- Multi-company support (each company can have different gateway credentials)
- No redeploy needed to change credentials (DB-driven)
- Easy gateway switching per company

---

## Related Models & Tables

### Payment Model
```php
// Location: /home/soudshoja/soud-laravel/app/Models/Payment.php
class Payment extends Model {
    // Key fields for gateway integration
    - payment_gateway: string (tap/myfatoorah/hesabe/knet/upayment)
    - payment_method_id: int (FK to PaymentMethod)
    - payment_url: string (generated payment link)
    - expiry_date: datetime (link expiration)
    - amount: decimal
    - gateway_fee: decimal (Fee charged by gateway)
    - service_charge: decimal (Internal markup)
    - status: string (pending/completed/failed/refunded)
    - completed: boolean (Quick flag)
    - voucher_number: string (Internal reference)
    - payment_reference: string (Gateway transaction ID)

    // Relations
    hasMany: InvoicePartial
    hasOne: TapPayment, MyFatoorahPayment, HesabePayment, UpaymentPayment
    morphMany: Transaction (accounting entries)
    belongsTo: Invoice, Client, Agent
}
```

### Charge Model
```php
// Location: /home/soudshoja/soud-laravel/app/Models/Charge.php
class Charge extends Model {
    // Gateway configuration
    - company_id: int (Multi-company support)
    - name: string (tap/myfatoorah/hesabe/knet/upayment)
    - api_key: string (Gateway secret/key)
    - is_active: boolean (Enable/disable per company)

    // Knet-specific
    - tran_portal_id: string
    - tran_portal_password: string
    - terminal_resource_key: string

    // Other
    - base_url: string
    - merchant_code: string
    - iv_key: string (Hesabe encryption)
    - access_code: string (Hesabe encryption)

    // Relations
    hasMany: PaymentMethod
    hasMany: InvoicePartial
}
```

### PaymentMethod Model
```php
// Location: /home/soudshoja/soud-laravel/app/Models/PaymentMethod.php
class PaymentMethod extends Model {
    // Gateway-specific methods
    - charge_id: int (FK to Charge - which gateway)
    - myfatoorah_id: int (Method ID in MyFatoorah)
    - code: string (Internal code: visa_card, bank_transfer, wallet, etc.)
    - type: string (Gateway type: tap/myfatoorah/hesabe/knet/upayment)

    // Metadata
    - english_name: string (Card/Bank/Wallet name)
    - arabic_name: string (Arabic translation)
    - is_active: boolean
    - currency: string

    // Fee structure
    - service_charge: decimal (% or fixed)
    - self_charge: boolean (Who pays: client or merchant)
    - paid_by: string (client/merchant)
    - charge_type: string (percentage/fixed)

    // Relations
    belongsTo: Charge, Company, PaymentMethodGroup
    belongsToMany: Payment (via payment_link_payment_method)
}
```

### TapPayment, MyFatoorahPayment, HesabePayment, UpaymentPayment Models
```php
// Gateway-specific response storage
- payment_id: int (FK to Payment)
- charge_id: string (Gateway charge/transaction ID)
- invoice_id: int|null (MyFatoorah invoice ID)
- payment_reference: string (Gateway reference number)
- auth_code: string (Authorization code from gateway)
- invoice_ref: string (MyFatoorah invoice reference)
- gateway_response: json|text (Full gateway response stored for audit)
- created_at, updated_at
```

---

## Invoice Processing & Bulk Upload Considerations

### Should Bulk Invoices Create invoice_partials?

**YES, with conditions:**

1. **When to Auto-Create**:
   - Invoice has `accept_payment: true` (indicates client-facing payment)
   - Invoice has default payment gateway configured (in Invoice or Agent settings)
   - Client has preferred payment method on file

2. **When to Skip**:
   - Invoice marked as `draft` status
   - Invoice is B2B with manual payment terms (no auto-link)
   - Invoice has `payment_type: 'manual'` (no online payment)

### Implementation Approach

**Recommended Flow for Bulk Upload**:

```php
// Step 1: Create Invoice from bulk file
$invoice = Invoice::create([
    'invoice_number' => $invoiceNumber,
    'amount' => $amount,
    'accept_payment' => true,  // Enable payment acceptance
    'payment_type' => 'online', // or 'manual'
    // ... other fields
]);

// Step 2: Create Payment record (optional, depends on workflow)
$payment = Payment::create([
    'invoice_id' => $invoice->id,
    'client_id' => $invoice->client_id,
    'amount' => $invoice->amount,
    'payment_gateway' => $client->preferred_payment_gateway ?? 'myfatoorah',
    'payment_method_id' => $client->preferred_payment_method_id,
    'status' => 'pending',
    'voucher_number' => generateVoucherNumber(),
]);

// Step 3: Create InvoicePartial (for payment links)
$partial = InvoicePartial::create([
    'invoice_id' => $invoice->id,
    'invoice_number' => $invoice->invoice_number,
    'client_id' => $invoice->client_id,
    'amount' => $invoice->amount,
    'payment_gateway' => 'myfatoorah',  // or use client preference
    'payment_method' => $paymentMethod->id,
    'status' => 'pending',
    'expiry_date' => now()->addDays(7), // 7-day payment window
    'payment_id' => $payment->id,
    'type' => 'invoice',
    'service_charge' => calculateServiceCharge($invoice->amount),
    'gateway_fee' => 0, // Calculated after payment attempt
]);

// Step 4: Generate payment link (optional for bulk)
// Either: Auto-generate all links (more friction, more admin)
// Or: Generate on-demand when client receives invoice
if ($shouldAutoGeneratePaymentLink) {
    $paymentUrl = generatePaymentLink($partial);
}
```

### Bulk Upload Gateway Selection

**Options**:

| Gateway | Use Case | Bulk | Notes |
|---------|----------|------|-------|
| **MyFatoorah** | Default choice | ✅ Best | Multi-method hub, most flexible |
| **Tap** | Prefer card only | ✅ Good | Fastest processing, card-only |
| **Hesabe** | Bank transfer focus | ✅ Good | Encrypted, secure for B2B |
| **Knet** | Kuwait clients | ✅ Good | Local payment method |
| **uPayment** | Alternative hub | ✅ Good | Similar to MyFatoorah, less common |
| **Manual/Bank** | No gateway | ✅ Best | No processing fees, no link |

**Bulk Upload Strategy**:

```php
class BulkInvoiceUploadService
{
    public function processInvoices(Collection $invoices, $bulkUploadId)
    {
        $defaultGateway = $this->getCompanyDefaultGateway();
        $defaultMethod = $this->getCompanyDefaultPaymentMethod();

        foreach ($invoices as $invoiceData) {
            // Create invoice
            $invoice = $this->createInvoiceFromData($invoiceData);

            // Create payment partial if invoice is for online payment
            if ($this->shouldAcceptOnlinePayment($invoice)) {

                // Check if client has preferred gateway
                $gateway = $invoice->client->preferred_payment_gateway
                    ?? $defaultGateway ?? 'myfatoorah';

                $payment = Payment::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'amount' => $invoice->amount,
                    'payment_gateway' => $gateway,
                    'status' => 'pending',
                    'voucher_number' => Sequence::generateVoucher(),
                ]);

                InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $invoice->client_id,
                    'amount' => $invoice->amount,
                    'payment_gateway' => $gateway,
                    'payment_method' => $defaultMethod->id,
                    'payment_id' => $payment->id,
                    'status' => 'pending',
                    'expiry_date' => now()->addDays(7),
                    'type' => 'invoice',
                    'service_charge' => $this->calculateServiceCharge($invoice->amount),
                ]);

                // Optionally: Send payment link to client via email/SMS
                // $this->sendPaymentLink($invoice->client, $payment);
            }
        }

        // Log bulk upload completion
        Log::info("Bulk upload completed", [
            'bulk_upload_id' => $bulkUploadId,
            'invoice_count' => $invoices->count(),
            'payment_gateway' => $defaultGateway,
        ]);
    }
}
```

### Payment Link Distribution

**Options for Bulk**:

1. **Immediate Email** (Pro: Fast, Con: Spam risk)
   ```php
   Mail::send('emails.payment-link', [
       'invoice' => $invoice,
       'payment_url' => $payment->payment_url,
   ], function($m) { $m->to($client->email); });
   ```

2. **Batch Email (Next Day)** (Pro: Better UX, Con: Delay)
   ```php
   dispatch(new SendBulkPaymentLinks($bulkUploadId))
       ->delay(now()->addDay());
   ```

3. **Manual Send (Control)** (Pro: Review first, Con: Manual work)
   - Admin reviews bulk upload → Approve → Sends links

4. **No Email (Portal Only)** (Pro: No spam, Con: Client must check)
   - Link available in client portal, invoice detail page

5. **SMS + Email** (Pro: Multiple touchpoints, Con: Cost/Spam)
   - Send via SMS to phone + email for full invoice

### Multi-Currency Considerations

**Current Implementation**:
- Tap: Hardcoded to KWD
- MyFatoorah: Uses `Payment.currency` field
- Hesabe: Hardcoded to KWD
- Knet: Hardcoded to 414 (KWD currency code)
- uPayment: Dynamic via `currency` parameter

**For Bulk**:
```php
// Store currency on invoice
$invoice->currency = 'KWD'; // or detect from client country

// Use in payment creation
$payment->currency = $invoice->currency;

// Pass to gateway
$chargeRequest['currency'] = $payment->currency;
// Most gateways return error if currency mismatch
```

---

## Key Implementation Files

| File | Purpose |
|------|---------|
| `app/Models/InvoicePartial.php` | Partial payment record model |
| `app/Models/Payment.php` | Payment transaction model |
| `app/Models/PaymentMethod.php` | Payment method configuration |
| `app/Models/Charge.php` | Gateway configuration storage |
| `app/Http/Controllers/PaymentController.php` | Main payment orchestration |
| `app/Http/Controllers/InvoiceController.php` | Invoice & partial creation |
| `app/Support/PaymentGateway/Tap.php` | Tap gateway implementation |
| `app/Support/PaymentGateway/MyFatoorah.php` | MyFatoorah gateway implementation |
| `app/Support/PaymentGateway/Hesabe.php` | Hesabe gateway implementation |
| `app/Support/PaymentGateway/Knet.php` | Knet gateway implementation |
| `app/Support/PaymentGateway/UPayment.php` | uPayment gateway implementation |
| `app/Services/GatewayConfigService.php` | Gateway configuration fetcher |
| `app/Services/PaymentApplicationService.php` | Payment application (reconciliation) |
| `database/migrations/2025_03_17_111603_*` | invoice_partials table creation |
| `database/migrations/2025_09_17_165242_*` | Charge link migration |
| `database/migrations/2026_02_02_131951_*` | Gateway fee tracking |
| `routes/api.php`, `routes/web.php` | Payment endpoints and webhooks |

---

## Summary Table: Gateway Features Comparison

| Feature | Tap | MyFatoorah | Hesabe | Knet | uPayment |
|---------|-----|-----------|--------|------|----------|
| **Card Payments** | ✅ | ✅ | ⚠️ | ✅ | ✅ |
| **Bank Transfer** | ❌ | ✅ | ✅ | ✅ | ✅ |
| **Digital Wallets** | ❌ | ✅ | ❌ | ❌ | ✅ |
| **Encryption** | No | No | ✅ AES | ✅ AES | No |
| **Multi-Method** | No | ✅ | No | No | ✅ |
| **Status Query** | ✅ | ✅ | ✅ | ❌ (callback only) | ✅ |
| **Tokenization** | No | No | No | No | ✅ |
| **Regional Focus** | ME | ME/GCC | ME | Kuwait | ME/GCC |
| **Payment Link** | ✅ | ✅ | ✅ | ⚠️ Redirect | ✅ |
| **Webhook Support** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **invoice_partial** | ✅ Full | ✅ Full | ✅ Full | ✅ Full | ✅ Full |

---

## Architectural Insights

### Why invoice_partials Exists

The system was designed to handle:

1. **Business Requirements**:
   - Travel agencies need flexibility in payment collection
   - Clients may pay deposits upfront, balance on travel date
   - Different payment methods may be preferred for different amounts
   - Gateway fees can vary by method (card expensive, bank cheap)

2. **Multi-Gateway World**:
   - No single gateway is perfect
   - Each gateway has strengths (Tap: fast, MyFatoorah: flexible, Hesabe: secure)
   - System allows blending: 50% via Tap card, 50% via Hesabe bank

3. **Accounting Compliance**:
   - Partial payments must be tracked separately
   - Each partial generates its own accounting entries
   - Gateway fees are isolated per payment method for cost analysis
   - Enables detailed reporting by payment method

### Future Enhancement Opportunities

1. **Scheduled Payments**: Create invoice_partial with future expiry date
2. **Recurring Payments**: Auto-renew invoices with same partial structure
3. **Dynamic Routing**: AI-select best gateway based on amount/currency/client
4. **Fee Optimization**: Calculate optimal gateway for lowest cost
5. **Payment Retries**: Auto-retry failed payments with alternative gateway
6. **Mobile Native**: Direct gateway integration in mobile app

---

## Conclusion

The `invoice_partials` table is the **backbone of the payment system**, enabling:
- Flexible partial payment workflows
- Multi-gateway support without duplication
- Detailed accounting and fee tracking
- Client self-service payment links
- Audit trail for regulatory compliance

**Bulk uploads should create invoice_partials** for any invoice intended to accept online payments, with fallback to sensible defaults (MyFatoorah recommended for flexibility).
