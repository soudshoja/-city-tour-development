# Models & Relationships Deep Dive

> Generated: 2026-02-12
> Source: Direct analysis of all model files in `app/Models/`

---

## Complete Model Map

---

### Invoice Model

**File**: `app/Models/Invoice.php`
**Traits**: `HasFactory`, `SoftDeletes`

**Fillable Fields**:
| Field | Purpose |
|---|---|
| `invoice_number` | Unique invoice identifier |
| `client_id` | FK to clients table |
| `agent_id` | FK to agents table |
| `currency` | Invoice currency |
| `sub_amount` | Subtotal before charges (sum of task prices) |
| `invoice_charge` | Additional charges on the invoice |
| `amount` | Grand total amount |
| `status` | Enum: `paid`, `unpaid`, `partial`, `paid by refund`, `refunded`, `partial refund` |
| `invoice_date` | Date invoice was created |
| `paid_date` | Date invoice was fully paid |
| `due_date` | Payment due date |
| `label` | Custom label/description |
| `account_number` | Bank account number (for wire transfers) |
| `bank_name` | Bank name |
| `swift_no` | SWIFT code |
| `iban_no` | IBAN number |
| `country_id` | FK to countries |
| `tax` | Tax amount |
| `discount` | Discount amount |
| `shipping` | Shipping amount |
| `accept_payment` | Whether invoice accepts online payment |
| `payment_type` | Type of payment accepted |
| `is_client_credit` | Whether this invoice was paid using client credit |
| `external_url` | External URL (e.g., payment link) |

**Relationships**:
```php
// belongsTo
public function client()       -> belongsTo(Client::class, 'client_id')
public function agent()        -> belongsTo(Agent::class, 'agent_id')

// hasOne
public function payment()      -> hasOne(Payment::class)
public function refund()       -> hasOne(Refund::class, 'refund_invoice_id')
    // Refund that uses this invoice AS the refund invoice

// hasMany
public function invoiceDetails()       -> hasMany(InvoiceDetail::class)
public function invoicePartials()      -> hasMany(InvoicePartial::class)
public function JournalEntrys()        -> hasMany(JournalEntry::class)
public function transactions()         -> hasMany(Transaction::class)
public function originalRefunds()      -> hasMany(Refund::class, 'invoice_id')
    // Refunds that refer to this invoice as the ORIGINAL invoice
public function reminders()            -> hasMany(Reminder::class, 'invoice_id')
public function paymentApplications()  -> hasMany(PaymentApplication::class, 'invoice_id')
```

**Key Methods**:
```php
// Boot validation: enforces InvoiceStatus enum on save
static::saving(function ($invoice) {
    $validStatuses = array_column(InvoiceStatus::cases(), 'value');
    if (!in_array($invoice->status, $validStatuses, true)) {
        throw new InvalidArgumentException("Invalid invoice status: {$invoice->status}");
    }
});

// Recalculates total from line items
public function recalculateTotal()
{
    $this->amount = $this->invoiceDetails()->sum('task_price');
    $this->sub_amount = $this->invoiceDetails()->sum('task_price');
    $this->save();
}

// Computed: total paid via PaymentApplication records
public function getTotalPaidViaApplicationsAttribute()
    -> PaymentApplication::getTotalAppliedToInvoice($this->id)

// Computed: remaining balance
public function getRemainingBalanceAttribute()
    -> $this->amount - $this->total_paid_via_applications

// Check if fully paid
public function isFullyPaidViaApplications()
    -> $this->remaining_balance <= 0
```

**Invoice Status Enum** (`app/Enums/InvoiceStatus.php`):
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

---

### InvoiceDetail Model

**File**: `app/Models/InvoiceDetail.php`
**Traits**: `HasFactory`, `SoftDeletes`
**Purpose**: Line item on an Invoice. Bridges a Task to an Invoice. Each InvoiceDetail represents one task/service being billed.

**Fillable Fields**:
| Field | Purpose |
|---|---|
| `invoice_id` | FK to invoices table |
| `invoice_number` | Denormalized invoice number |
| `task_id` | FK to tasks table (the service being billed) |
| `task_description` | Human-readable description of the task |
| `task_remark` | Internal remark about the task |
| `client_notes` | Notes visible to the client |
| `task_price` | Price charged to client for this task |
| `supplier_price` | Cost from the supplier |
| `markup_price` | Markup over supplier price |
| `profit` | Profit on this line item |
| `commission` | Commission amount |
| `paid` | Whether this line item is paid |

**Relationships**:
```php
public function invoice()      -> belongsTo(Invoice::class, 'invoice_id')
public function task()         -> belongsTo(Task::class, 'task_id')
public function JournalEntrys() -> hasMany(JournalEntry::class)
public function payment()      -> hasOne(Payment::class)
```

**Critical Insight**: InvoiceDetail has a `hasOne(Payment::class)` relationship, meaning a Payment can be directly tied to an InvoiceDetail (not just an Invoice). This suggests payments can be at the line-item level.

---

### InvoicePartial Model

**File**: `app/Models/InvoicePartial.php`
**Traits**: `HasFactory` (no SoftDeletes)
**Purpose**: Represents a partial/split payment attempt or installment against an Invoice. Each InvoicePartial is a discrete payment portion with its own gateway, method, and charge configuration.

**Fillable Fields**:
| Field | Purpose |
|---|---|
| `invoice_id` | FK to the parent invoice |
| `invoice_number` | Denormalized invoice number |
| `client_id` | FK to clients |
| `service_charge` | Service/processing charge added |
| `gateway_fee` | Fee charged by the payment gateway |
| `amount` | Amount of this partial payment |
| `status` | Status of this partial (e.g., pending, paid) |
| `expiry_date` | Expiration date for this payment attempt |
| `type` | Type of partial payment |
| `charge_id` | FK to charges table (which gateway config) |
| `payment_gateway` | Name of payment gateway used |
| `payment_method` | FK to payment_methods table |
| `payment_id` | FK to payments table (links to the Payment record) |
| `receipt_voucher_id` | FK to receipt voucher |

**Relationships**:
```php
public function client()               -> belongsTo(Client::class, 'client_id')
public function invoice()              -> belongsTo(Invoice::class, 'invoice_id')
public function invoiceReceipt()       -> hasOne(InvoiceReceipt::class, 'invoice_partial_id')
public function payment()              -> belongsTo(Payment::class, 'payment_id')
public function paymentMethod()        -> belongsTo(PaymentMethod::class, 'payment_method')
public function charge()               -> belongsTo(Charge::class, 'charge_id')
public function paymentApplications()  -> hasMany(PaymentApplication::class, 'invoice_partial_id')
```

**Critical Insight**: InvoicePartial sits BETWEEN Invoice and Payment. It tracks which portion of an invoice was paid through which gateway, with what fees, and links to the specific Payment record. An Invoice can have many InvoicePartials (split payments / installments).

---

### Payment Model

**File**: `app/Models/Payment.php`
**Traits**: `HasFactory`, `SoftDeletes`

**What IS a Payment?**

A Payment is a **dual-purpose entity** in this system:

1. **Invoice Payment Link**: When `invoice_id` is set, it represents a payment link generated for a specific invoice. The client receives a URL (`payment_url`) to pay online through a gateway.

2. **Client Topup / Advance Payment**: When used with Credits, it represents money a client has deposited in advance (a "topup"). This creates positive Credit records that can later be applied to invoices via PaymentApplication.

The Payment model is the **central hub** that connects to external payment gateways (Tap, MyFatoorah, Hesabe, UPayment) and tracks the financial transaction.

**Fillable Fields**:
| Field | Purpose |
|---|---|
| `client_id` | FK to clients |
| `agent_id` | FK to agents |
| `voucher_number` | Unique payment voucher/receipt number |
| `payment_reference` | External reference from gateway |
| `invoice_id` | FK to invoices (optional - may be a standalone topup) |
| `invoice_reference` | Invoice reference string |
| `auth_code` | Authorization code from gateway |
| `from` | Payment sender |
| `pay_to` | Payment recipient |
| `created_by` | FK to users (who created this payment link) |
| `service_charge` | Service charge amount |
| `gateway_fee` | Gateway processing fee |
| `account_id` | FK to accounts (accounting integration) |
| `currency` | Payment currency |
| `payment_date` | When payment was made |
| `notes` | Internal notes |
| `amount` | Payment amount (decimal:3) |
| `payment_gateway` | Name of gateway used |
| `payment_method_id` | FK to payment_methods |
| `payment_url` | URL for online payment |
| `expiry_date` | Link expiration |
| `status` | Payment status |
| `terms_conditions` | T&C text for payment page |
| `send_payment_receipt` | Whether to email receipt |
| `account_number`, `bank_name`, `swift_no`, `iban_no`, `country` | Bank details |
| `tax`, `discount`, `shipping` | Additional amounts |
| `language` | Language for payment page |
| `completed` | Boolean - is payment finalized? |
| `is_disabled` | Boolean - is payment link disabled? |

**Casts**:
```php
'payment_date' => 'datetime',
'expiry_date' => 'datetime',
'amount' => 'decimal:3',
'service_charge' => 'decimal:3',
'tax' => 'decimal:3',
'completed' => 'boolean',
'is_disabled' => 'boolean',
'send_payment_receipt' => 'boolean',
```

**Relationships**:
```php
// belongsTo
public function client()           -> belongsTo(Client::class, 'client_id')
public function agent()            -> belongsTo(Agent::class, 'agent_id')
public function invoice()          -> belongsTo(Invoice::class, 'invoice_id')
public function paymentMethod()    -> belongsTo(PaymentMethod::class, 'payment_method_id')
public function createdBy()        -> belongsTo(User::class, 'created_by')

// hasOne (gateway-specific records)
public function tapPayment()           -> hasOne(TapPayment::class)
public function myFatoorahPayment()    -> hasOne(MyFatoorahPayment::class, 'payment_int_id', 'id')
public function hesabePayment()        -> hasOne(HesabePayment::class, 'payment_int_id', 'id')
public function hotelBooking()         -> hasOne(HotelBooking::class, 'payment_id')

// hasMany
public function partials()              -> hasMany(InvoicePartial::class)
public function credit()                -> hasMany(Credit::class, 'payment_id')
public function paymentTransactions()   -> hasMany(PaymentTransaction::class, 'payment_id')
public function paymentItems()          -> hasMany(PaymentItem::class, 'payment_id')
public function paymentFiles()          -> hasMany(PaymentFile::class, 'payment_id')
public function paymentApplications()   -> hasMany(PaymentApplication::class, 'payment_id')

// belongsToMany (pivot tables for payment link configuration)
public function availablePaymentMethods()      -> belongsToMany(PaymentMethod::class, 'payment_link_payment_method')
public function availablePaymentMethodGroups() -> belongsToMany(PaymentMethodGroup::class, 'payment_link_payment_method_group')

// morphMany
public function transactions() -> morphMany(Transaction::class, 'referenceable', 'reference_type', 'reference_id')
```

**Key Computed Attributes**:
```php
// Balance from Credit records (for topup payments)
public function getAvailableBalanceAttribute()
    -> Credit::getAvailableBalanceByPayment($this->id)

// Total applied from this payment to invoices
public function getTotalAppliedAttribute()
    -> PaymentApplication::getTotalAppliedByPayment($this->id)

// Check if payment has remaining balance
public function hasAvailableBalance()
    -> $this->available_balance > 0
```

---

### PaymentApplication Model

**File**: `app/Models/PaymentApplication.php`
**Traits**: `HasFactory` (no SoftDeletes)
**Purpose**: The junction/ledger table that records how money from a Payment (or Credit from a Refund) is applied to an Invoice. This is the "wallet deduction" record.

**Fillable Fields**:
| Field | Purpose |
|---|---|
| `payment_id` | FK to payments (nullable for refund credits) |
| `credit_id` | FK to credits (the source credit record) |
| `invoice_id` | FK to invoices (the target invoice) |
| `invoice_partial_id` | FK to invoice_partials (optional - specific partial) |
| `amount` | Amount applied (decimal:3) |
| `applied_by` | FK to users (who performed the application) |
| `applied_at` | Timestamp of application |
| `notes` | Notes about this application |

**Relationships**:
```php
public function payment()        -> belongsTo(Payment::class)
public function credit()         -> belongsTo(Credit::class)
public function invoice()        -> belongsTo(Invoice::class)
public function invoicePartial() -> belongsTo(InvoicePartial::class)
public function appliedBy()      -> belongsTo(User::class, 'applied_by')
```

**Key Static Methods**:
```php
// Aggregation queries
static getTotalAppliedByPayment($paymentId)   -> sum of amount WHERE payment_id = $paymentId
static getTotalAppliedToInvoice($invoiceId)    -> sum of amount WHERE invoice_id = $invoiceId
static getTotalAppliedToPartial($partialId)    -> sum of amount WHERE invoice_partial_id = $partialId

// Eager-loaded queries
static getApplicationsForInvoice($invoiceId)   -> with(['payment', 'credit.refund', 'appliedBy'])
static getApplicationsFromPayment($paymentId)  -> with(['invoice', 'invoicePartial', 'appliedBy'])
```

**Source Detection Methods**:
```php
// Determines if this application came from a refund credit (no payment_id, has refund_id on credit)
public function isFromRefund(): bool
    -> $this->payment_id === null && $this->credit?->refund_id !== null

// Determines if this application came from a topup (has payment_id)
public function isFromTopup(): bool
    -> $this->payment_id !== null

// Returns the voucher number or refund number
public function getSourceReferenceAttribute(): ?string

// Returns 'Topup' or 'Refund'
public function getSourceTypeAttribute(): string
```

**Critical Insight**: PaymentApplication supports TWO sources of funds:
1. **Topup Credits** - Money from a Payment (client advance). `payment_id` is set.
2. **Refund Credits** - Money from a completed refund. `payment_id` is null, `credit_id` points to a Credit with `refund_id`.

---

### Charge Model

**File**: `app/Models/Charge.php`
**Traits**: `HasFactory` (no SoftDeletes)
**Purpose**: Represents a **payment gateway configuration**. Each Charge is a configured payment gateway (Tap, MyFatoorah, Hesabe, UPayment, or custom gateways like Cash, Bank Transfer, Cheque). It stores API credentials, fee configuration, and accounting linkage.

**Global Scope**: Automatically filters by `company_id` of the authenticated user (multi-tenant).

**Fillable Fields**:
| Field | Purpose |
|---|---|
| `name` | Gateway name (e.g., "Tap", "MyFatoorah", "Cash") |
| `type` | Gateway type classification |
| `description` | Human-readable description |
| `api_key` | API key for the gateway |
| `tran_portal_id` | Transaction portal ID (KNET) |
| `tran_portal_password` | Transaction portal password (KNET) |
| `terminal_resource_key` | Terminal resource key (KNET) |
| `paid_by` | Who bears the gateway charges (client/company) |
| `amount` | Base charge amount |
| `extra_charge` | Additional charge amount |
| `self_charge` | Self-charge configuration |
| `is_active` | Boolean - is this gateway enabled? |
| `can_generate_link` | Boolean - can generate payment links? |
| `charge_type` | Type of charge (percentage/fixed) |
| `company_id` | FK to companies (multi-tenant) |
| `branch_id` | FK to branches |
| `acc_bank_id` | FK to accounts - the bank account for this gateway |
| `acc_fee_id` | FK to accounts - the fee revenue account |
| `acc_fee_bank_id` | FK to accounts - the fee bank account |
| `is_auto_paid` | Boolean - does this gateway auto-confirm payments? |
| `has_url` | Boolean - does this gateway provide a payment URL? |
| `can_charge_invoice` | Boolean - can charge invoices directly? |
| `is_system_default` | Boolean - is this a system-default gateway? |
| `can_be_deleted` | Boolean - can admin delete this gateway? |
| `enabled_by` | Who enabled this gateway |

**Relationships**:
```php
public function company()     -> belongsTo(Company::class, 'company_id')
public function branch()      -> belongsTo(Branch::class, 'branch_id')
public function accFee()      -> belongsTo(Account::class, 'acc_fee_id')
public function accBank()     -> belongsTo(Account::class, 'acc_bank_id')
public function accBankFee()  -> belongsTo(Account::class, 'acc_fee_bank_id')
public function methods()     -> hasMany(PaymentMethod::class, 'charge_id')
```

**Key Methods**:
```php
// Checks if code implementation exists for this gateway
public function hasApiImplementation(): bool
    -> in_array($this->name, ['Tap', 'MyFatoorah', 'Hesabe', 'UPayment'])

// Combined check: has API AND is enabled for link generation
public function canGeneratePaymentLink(): bool
    -> $this->hasApiImplementation() && $this->can_generate_link
```

---

### PaymentMethod Model

**File**: `app/Models/PaymentMethod.php`
**Traits**: `HasFactory` (no SoftDeletes)
**Purpose**: Represents a specific payment method within a gateway (e.g., "KNET" under Tap, "Visa" under MyFatoorah). Each PaymentMethod belongs to a Charge (gateway) and optionally to a PaymentMethodGroup.

**Global Scope**: Filtered by `company_id` of authenticated user.

**Fillable Fields**:
| Field | Purpose |
|---|---|
| `charge_id` | FK to charges (parent gateway) |
| `myfatoorah_id` | MyFatoorah-specific method ID |
| `company_id` | FK to companies (multi-tenant) |
| `arabic_name` | Arabic display name |
| `english_name` | English display name |
| `payment_method_group_id` | FK to payment_method_groups |
| `code` | Method code identifier |
| `type` | Method type |
| `is_active` | Boolean - is this method enabled? |
| `currency` | Supported currency |
| `service_charge` | Service charge for this method |
| `self_charge` | Self-charge rate |
| `paid_by` | Who pays the charge (client/company) |
| `charge_type` | Fixed or percentage |
| `description` | Description text |
| `image` | Method logo/icon |

**Relationships**:
```php
public function charge()             -> belongsTo(Charge::class, 'charge_id')
public function gateways()           -> belongsTo(Charge::class, 'type', 'name')
    // Secondary lookup: matches PaymentMethod.type to Charge.name
public function company()            -> belongsTo(Company::class)
public function paymentMethodGroup() -> belongsTo(PaymentMethodGroup::class, 'payment_method_group_id')
public function paymentLinks()       -> belongsToMany(Payment::class, 'payment_link_payment_method')
```

---

### PaymentMethodGroup Model

**File**: `app/Models/PaymentMethodGroup.php`
**Purpose**: Groups related payment methods together (e.g., "Credit Cards", "Debit Cards", "Digital Wallets"). Used to organize the payment method selection UI on payment links.

**Fillable Fields**: `name`

**Relationships**:
```php
public function paymentMethods()       -> hasMany(PaymentMethod::class, 'payment_method_group_id')
public function activePaymentMethods() -> hasMany(PaymentMethod::class) WHERE is_active = 1
public function chosenMethod()         -> hasOne(PaymentMethodChose::class, 'payment_method_group_id')
public function paymentLinks()         -> belongsToMany(Payment::class, 'payment_link_payment_method_group')
```

**Key Method**:
```php
// Gets the currently selected method for a company, with fallback
public function getCurrentActiveMethod($companyId)
    -> Checks PaymentMethodChose first, falls back to first active method
```

---

### PaymentMethodChose Model

**File**: `app/Models/PaymentMethodChose.php`
**Purpose**: Records which PaymentMethod a company has chosen as their preferred method within a PaymentMethodGroup. Acts as a per-company preference setting.

**Fillable Fields**: `company_id`, `payment_method_group_id`, `payment_method_id`

**Relationships**:
```php
public function company()            -> belongsTo(Company::class)
public function paymentMethodGroup() -> belongsTo(PaymentMethodGroup::class)
public function paymentMethod()      -> belongsTo(PaymentMethod::class)
```

---

### Task Model

**File**: `app/Models/Task.php`
**Traits**: `HasFactory`, `SoftDeletes`
**Purpose**: Represents a travel service (flight, hotel, visa, insurance, etc.) that has been issued for a client. This is the core business entity that gets invoiced.

**Fillable Fields** (payment-relevant):
| Field | Purpose |
|---|---|
| `client_id` | FK to clients |
| `agent_id` | FK to agents |
| `company_id` | FK to companies |
| `supplier_id` | FK to suppliers |
| `type` | Task type: flight, hotel, visa, insurance, tour, cruise, car, rail, esim, event, lounge, ferry |
| `status` | Task status: issued, reissued, refund, void, etc. |
| `price` | Selling price in local currency |
| `original_price` | Price in original currency |
| `original_currency` | Original transaction currency |
| `exchange_rate` | Exchange rate applied |
| `exchange_currency` | Target currency |
| `tax` | Tax in local currency |
| `original_tax` | Tax in original currency |
| `surcharge` | Surcharge amount |
| `original_surcharge` | Surcharge in original currency |
| `total` | Total in local currency |
| `original_total` | Total in original currency |
| `invoice_price` | Price as shown on invoice |
| `penalty_fee` | Penalty/change fee |
| `supplier_surcharge` | Surcharge from supplier |
| `refund_charge` | Charge for refund processing |
| `payment_type` | How this was paid at supplier level |
| `payment_method_account_id` | FK to accounts (supplier payment method) |

**Invoice/Payment Relationships**:
```php
public function invoiceDetail()  -> hasOne(InvoiceDetail::class, 'task_id')
    // ONE task appears on ONE invoice line item
public function refundDetail()   -> hasOne(RefundDetail::class, 'task_id')
    // ONE task can have ONE refund detail
public function paymentMethod()  -> belongsTo(Account::class, 'payment_method_account_id')
    // Supplier payment method (accounting account)
```

---

### Credit Model

**File**: `app/Models/Credit.php`
**Traits**: `HasFactory` (no SoftDeletes)
**Purpose**: The **ledger for client wallet/credit balance**. Each Credit record is a line entry that either adds or subtracts from a client's credit balance. The sum of all Credit records for a client = their total credit balance.

**Credit Types** (Constants):
```php
const INVOICE = 'Invoice';         // Deduction: credit used to pay an invoice (negative amount)
const TOPUP = 'Topup';            // Addition: client topped up their account (positive amount)
const REFUND = 'Refund';          // Addition: refund credited to client (positive amount)
const INVOICE_REFUND = 'Invoice Refund'; // Credit from invoice refund
```

**Fillable Fields**:
| Field | Purpose |
|---|---|
| `company_id` | FK to companies |
| `branch_id` | FK to branches |
| `client_id` | FK to clients |
| `invoice_id` | FK to invoices (when credit used for invoice) |
| `invoice_partial_id` | FK to invoice_partials |
| `payment_id` | FK to payments (the topup payment) |
| `refund_id` | FK to refunds (the source refund) |
| `type` | One of: Invoice, Topup, Refund, Invoice Refund |
| `description` | Human-readable description |
| `amount` | Amount (positive = add credit, negative = use credit) |
| `gateway_fee` | Gateway fee associated with this credit entry |
| `topup_by` | Who performed the topup |

**Relationships**:
```php
public function company()        -> belongsTo(Company::class)
public function client()         -> belongsTo(Client::class)
public function invoice()        -> belongsTo(Invoice::class)
public function invoicePartial() -> belongsTo(InvoicePartial::class)
public function payment()        -> belongsTo(Payment::class)
public function refund()         -> belongsTo(Refund::class)
```

**Key Static Methods**:
```php
// Get total credit balance for a client
static getTotalCreditsByClient($clientId) -> sum of all amounts

// Get balance from a specific payment (topup)
static getAvailableBalanceByPayment($paymentId) -> sum WHERE payment_id = $paymentId

// Get balance from a specific refund
static getAvailableBalanceByRefund($refundId) -> sum WHERE refund_id = $refundId

// CRITICAL: Get all available payment sources for a client (FIFO sorted)
static getAvailablePaymentsForClient($clientId)
    -> Collects topup payments with positive balance
    -> Collects refunds with positive balance
    -> Sorts by date (oldest first - FIFO deduction)
    -> Returns array of available sources with amounts

// Check if enough balance exists
static hasEnoughBalance($paymentId, $amount)
```

**How Credit Balance Works**:
- When a client tops up (e.g., pays KWD 500): A Credit record is created with `type = 'Topup'`, `amount = 500`, `payment_id = X`
- When credit is applied to an invoice: A Credit record is created with `type = 'Invoice'`, `amount = -200` (negative), `invoice_id = Y`
- Net balance for a payment = sum of all Credits where `payment_id = X` (500 + (-200) = 300 remaining)
- `getAvailablePaymentsForClient()` uses FIFO ordering to deduct from oldest deposits first

---

### Gateway-Specific Payment Models

#### TapPayment (`app/Models/TapPayment.php`)
**Table**: `tap_payments`
**Fields**: `payment_id`, `tap_id`, `authorization_id`, `timezone`, `expiry_period`, `expiry_type`, `amount`, `currency`, `date_created`, `date_completed`, `date_transaction`, `receipt_id`, `receipt_email`, `receipt_sms`

#### MyFatoorahPayment (`app/Models/MyFatoorahPayment.php`)
**Table**: `myfatoorah_payments`
**Fields**: `payment_int_id` (FK to payments.id), `payment_id` (MyFatoorah's ID), `invoice_id` (MyFatoorah's invoice ID), `invoice_ref`, `invoice_status`, `customer_reference`, `payload` (JSON)

#### HesabePayment (`app/Models/HesabePayment.php`)
**Table**: `hesabe_payments`
**Fields**: `payment_int_id` (FK to payments.id), `status`, `payment_token`, `payment_id` (Hesabe's ID), `order_reference_number`, `auth_code`, `track_id`, `transaction_id`, `invoice_id`, `paid_on`, `payload` (JSON)

#### UpaymentPayment (`app/Models/UpaymentPayment.php`)
**Table**: `upayments_payments`
**Fields**: `payment_int_id` (FK to payments.id), `payment_id` (UPayment's ID), `order_id`, `invoice_id`, `track_id`, `status`, `payment_type`, `payment_method`, `total_price`, `payment_date`, `payload` (JSON)

All gateway models follow the same pattern: `belongsTo(Payment::class, 'payment_int_id', 'id')` linking back to the main Payment record.

---

### Supporting Models

#### PaymentTransaction (`app/Models/PaymentTransaction.php`)
**Purpose**: Records each gateway transaction attempt for a Payment. A Payment may have multiple transaction attempts (e.g., failed then succeeded).
**Fields**: `payment_id`, `transaction_id`, `status`, `url`, `payment_gateway_id` (FK to charges), `payment_method_id`, `track_id`, `reference_number`, `expiry_date`, `notes`

#### PaymentItem (`app/Models/PaymentItem.php`)
**Purpose**: Line items on a Payment link (what the client sees as itemized charges).
**Fields**: `payment_id`, `product_name`, `quantity`, `unit_price`, `extended_amount`, `currency`

#### PaymentFile (`app/Models/PaymentFile.php`)
**Purpose**: Temporary file attachments for payment links (e.g., invoice PDFs). Has a global scope filtering out expired files.
**Fields**: `payment_id`, `file_id`, `expiry_date`

#### InvoiceReceipt (`app/Models/InvoiceReceipt.php`)
**Purpose**: Records receipt vouchers generated when payments are received against invoices.
**Fields**: `type`, `invoice_id`, `invoice_partial_id`, `account_id`, `credit_id`, `transaction_id`, `amount`, `status`, `is_used`

#### InvoiceSequence (`app/Models/InvoiceSequence.php`)
**Purpose**: Auto-incrementing invoice number generator, per company.
**Fields**: `company_id`, `current_sequence`

#### AgentCharge (`app/Models/AgentCharge.php`)
**Purpose**: Configures how payment gateway fees are split between company and agent.
**Bearer Options**: `company` (company pays all), `agent` (agent pays all), `split` (percentage-based split)
**Fields**: `agent_id`, `company_id`, `charge_bearer`, `agent_percentage`, `company_percentage`

---

### Refund Model

**File**: `app/Models/Refund.php`
**Traits**: `SoftDeletes`
**Purpose**: Represents a refund of one or more tasks from an original invoice. Creates a negative/refund invoice and may generate Credit for the client.

**Fields**: `refund_number`, `company_id`, `branch_id`, `agent_id`, `invoice_id` (original), `refund_invoice_id` (the new refund invoice), `method`, `remarks`, `remarks_internal`, `reason`, `total_refund_amount`, `total_refund_charge`, `total_nett_refund`, `status`, `refund_date`, `created_by`, `updated_by`

**Relationships**:
```php
public function refundDetails()    -> hasMany(RefundDetail::class, 'refund_id')
public function originalInvoice()  -> belongsTo(Invoice::class, 'invoice_id')
public function invoice()          -> belongsTo(Invoice::class, 'refund_invoice_id')
    // The refund invoice (not the original)
public function company()          -> belongsTo(Company::class)
public function branch()           -> belongsTo(Branch::class)
public function agent()            -> belongsTo(Agent::class)
```

#### RefundDetail (`app/Models/RefundDetail.php`)
**Purpose**: Line items in a refund, one per refunded task.
**Fields**: `refund_id`, `task_id`, `client_id`, `task_description`, `original_invoice_price`, `original_task_cost`, `original_task_profit`, `refund_fee_to_client`, `supplier_charge`, `new_task_profit`, `total_refund_to_client`, `remarks`

---

## Corrected Relationship Diagram

```
                         Company (multi-tenant root)
                            |
              +-------------+-------------+
              |             |             |
           Branch         Agent        Client
              |             |             |
              +------+------+      +------+------+
                     |             |             |
                   Task          Credit        Invoice
                     |             |             |
                     |             |        +----+----+----+
                     |             |        |         |    |
                InvoiceDetail      |   InvoicePartial |  Refund
                     |             |        |         |    |
                     +-------------+--------+    PaymentApplication
                                   |                  |
                                Payment              |
                                   |                  |
                    +--------------+--------------+   |
                    |              |              |    |
              TapPayment   MyFatoorahPayment  HesabePayment
                    |              |              |
              UpaymentPayment     PaymentTransaction
                                        |
                                      Charge ---- PaymentMethod
                                                      |
                                               PaymentMethodGroup
                                                      |
                                               PaymentMethodChose
```

### Detailed Entity Relationships

```
Task (1) ----hasOne----> (1) InvoiceDetail
                              |
                         belongsTo
                              |
                              v
Invoice (1) ----hasMany----> (*) InvoiceDetail
Invoice (1) ----hasMany----> (*) InvoicePartial
Invoice (1) ----hasOne-----> (1) Payment         [direct payment link]
Invoice (1) ----hasMany----> (*) PaymentApplication [credit applications]
Invoice (1) ----hasMany----> (*) JournalEntry
Invoice (1) ----hasMany----> (*) Transaction
Invoice (1) ----hasMany----> (*) Refund (via invoice_id = original invoice)
Invoice (1) ----hasOne-----> (1) Refund (via refund_invoice_id = refund invoice)

InvoicePartial (*) ----belongsTo----> (1) Invoice
InvoicePartial (*) ----belongsTo----> (1) Payment
InvoicePartial (*) ----belongsTo----> (1) Charge
InvoicePartial (*) ----belongsTo----> (1) PaymentMethod
InvoicePartial (1) ----hasOne-------> (1) InvoiceReceipt
InvoicePartial (1) ----hasMany------> (*) PaymentApplication

Payment (1) ----hasMany----> (*) InvoicePartial  [via partials()]
Payment (1) ----hasMany----> (*) Credit          [credit entries]
Payment (1) ----hasMany----> (*) PaymentApplication
Payment (1) ----hasOne-----> (1) TapPayment
Payment (1) ----hasOne-----> (1) MyFatoorahPayment
Payment (1) ----hasOne-----> (1) HesabePayment
Payment (1) ----hasMany----> (*) PaymentTransaction
Payment (1) ----hasMany----> (*) PaymentItem
Payment (1) ----hasMany----> (*) PaymentFile
Payment (*) ----belongsToMany----> (*) PaymentMethod (pivot: payment_link_payment_method)
Payment (*) ----belongsToMany----> (*) PaymentMethodGroup (pivot: payment_link_payment_method_group)

Charge (1) ----hasMany----> (*) PaymentMethod
Charge (1) ----belongsTo----> (1) Account [accBank]
Charge (1) ----belongsTo----> (1) Account [accFee]
Charge (1) ----belongsTo----> (1) Account [accBankFee]

PaymentMethod (*) ----belongsTo----> (1) Charge
PaymentMethod (*) ----belongsTo----> (1) PaymentMethodGroup

PaymentApplication (*) ----belongsTo----> (1) Payment   [nullable - null for refund credits]
PaymentApplication (*) ----belongsTo----> (1) Credit    [the source credit record]
PaymentApplication (*) ----belongsTo----> (1) Invoice   [the target invoice]
PaymentApplication (*) ----belongsTo----> (1) InvoicePartial [optional]

Credit (*) ----belongsTo----> (1) Payment [for topups]
Credit (*) ----belongsTo----> (1) Refund  [for refund credits]
Credit (*) ----belongsTo----> (1) Invoice [for used credits]
Credit (*) ----belongsTo----> (1) Client

Refund (1) ----hasMany------> (*) RefundDetail
Refund (*) ----belongsTo----> (1) Invoice [original invoice]
Refund (*) ----belongsTo----> (1) Invoice [refund invoice]
RefundDetail (*) ----belongsTo----> (1) Task
```

---

## Data Flow Discovery

### 1. How a Task Becomes Part of an Invoice

```
1. Task is created (type: flight/hotel/visa/etc., status: issued)
   - Has: price, tax, surcharge, total, supplier cost

2. InvoiceDetail is created linking Task to Invoice:
   - invoice_id = the target Invoice
   - task_id = the Task being billed
   - task_price = amount charged to client (may differ from task.total)
   - supplier_price = cost from supplier
   - markup_price = markup added
   - profit = calculated profit

3. Invoice.recalculateTotal() is called:
   - amount = SUM(invoice_details.task_price)
   - sub_amount = SUM(invoice_details.task_price)
   - Status set to 'unpaid'

Result: Task -> InvoiceDetail -> Invoice (one-to-one-to-many)
Each Task appears on exactly ONE InvoiceDetail (hasOne relationship).
Each Invoice can have MANY InvoiceDetails (multiple tasks on one invoice).
```

### 2. How InvoicePartial is Created (Split/Online Payment)

```
1. Client receives invoice link or agent initiates payment
2. A payment split/partial is configured:
   - Selects amount to pay (full or partial)
   - Selects gateway (Charge) and method (PaymentMethod)
   - Service charge and gateway fee calculated

3. InvoicePartial is created:
   - invoice_id = the invoice being paid
   - amount = portion being paid
   - charge_id = which Charge (gateway) is used
   - payment_gateway = gateway name
   - payment_method = which PaymentMethod
   - service_charge = fee for this payment
   - gateway_fee = gateway processing fee
   - status = 'pending'
   - expiry_date = when this payment attempt expires

4. A Payment record is created (or linked):
   - InvoicePartial.payment_id = Payment.id
   - Payment gets payment_url for the gateway

5. Client pays via gateway -> callback -> status updated to 'paid'
6. InvoiceReceipt created for the partial
7. Invoice status updated (to 'partial' or 'paid')
```

### 3. How Payment Applies to Invoice (Credit/Wallet System)

```
TWO paths for applying payment to an invoice:

PATH A: Direct Gateway Payment (via InvoicePartial)
1. InvoicePartial created with gateway details
2. Payment link generated -> client pays online
3. Gateway callback marks partial as paid
4. Journal entries created for accounting

PATH B: Credit/Wallet Application (via PaymentApplication)
1. Client has existing credit balance from:
   a. TOPUP: Client paid money in advance -> Payment created -> Credit record (type='Topup', positive amount)
   b. REFUND: Previous refund created -> Credit record (type='Refund', positive amount)

2. Agent applies credit to invoice:
   a. Credit::getAvailablePaymentsForClient($clientId) called
   b. Returns available topup and refund balances (FIFO sorted)
   c. PaymentApplication created:
      - payment_id = source Payment (for topups)
      - credit_id = source Credit record
      - invoice_id = target Invoice
      - amount = amount being applied

3. Credit deduction record created:
   - type = 'Invoice' (negative amount)
   - This reduces the available balance

4. Invoice status rechecked:
   - If remaining_balance <= 0 -> status = 'paid'
   - If partially covered -> status = 'partial'
```

### 4. Role of Charge in Payment Processing

```
Charge is the GATEWAY CONFIGURATION entity:

1. System Setup:
   - Company configures Charges (one per gateway)
   - Each Charge stores API credentials (api_key, tran_portal_id, etc.)
   - Fees configured: amount, extra_charge, charge_type (fixed/percentage)
   - Accounting links: acc_bank_id (where money goes), acc_fee_id (where fees recorded)

2. Payment Method Association:
   - Each Charge has many PaymentMethods
   - PaymentMethods are specific options (KNET, Visa, Apple Pay, etc.)
   - PaymentMethodGroups organize methods for the UI

3. During Payment:
   - InvoicePartial.charge_id links to the chosen gateway
   - Charge.hasApiImplementation() validates if code exists
   - Charge.canGeneratePaymentLink() checks both API + business permission
   - AgentCharge determines who bears the gateway fees (company/agent/split)

4. Implemented Gateways:
   - Tap -> TapPayment model
   - MyFatoorah -> MyFatoorahPayment model
   - Hesabe -> HesabePayment model
   - UPayment -> UpaymentPayment model
   - Cash, Bank Transfer, Cheque -> manual gateways (no API)
```

---

## Critical Insights

### What is a "Payment" vs "InvoicePartial"?

| Aspect | Payment | InvoicePartial |
|--------|---------|---------------|
| **Role** | The financial transaction entity | A payment installment/split on an invoice |
| **Scope** | Can exist independently (topup) OR tied to invoice | Always tied to an Invoice |
| **Gateway** | Stores the payment URL & gateway details | Stores which gateway/method was chosen |
| **Fees** | Has service_charge, gateway_fee | Has its own service_charge, gateway_fee |
| **Relationship** | Invoice hasOne Payment; Payment hasMany InvoicePartials | Invoice hasMany InvoicePartials |

**Key difference**: A Payment is the "bank transaction" (the money moving). An InvoicePartial is the "invoice-side record" of how much of an invoice was paid, through which gateway, with what fees. Multiple InvoicePartials on one Invoice can all reference the SAME or DIFFERENT Payment records.

### Can One Payment Pay Multiple Invoices?

**YES.** Evidence from the code:

1. `Payment -> hasMany(PaymentApplication)` and `PaymentApplication -> belongsTo(Invoice)` means a single Payment can have multiple PaymentApplication records, each pointing to different invoices.

2. `Credit::getAvailablePaymentsForClient()` returns available balance from a payment, which can be applied incrementally to different invoices.

3. The `Payment.available_balance` attribute tracks remaining balance: `Credit::getAvailableBalanceByPayment($this->id)`. If balance > 0, more invoices can be paid from it.

**Example flow**: Client tops up KWD 500 -> Payment created -> Credit(Topup, +500). Then KWD 200 applied to Invoice A (Credit(Invoice, -200), PaymentApplication created) -> KWD 300 remains -> KWD 300 applied to Invoice B.

### Can One Invoice Have Multiple Payments?

**YES.** Evidence from the code:

1. `Invoice -> hasMany(InvoicePartial)` - Multiple partials can be created for one invoice, each with its own Payment.

2. `Invoice -> hasMany(PaymentApplication)` - Multiple credit applications can be made against one invoice.

3. The `Invoice.remaining_balance` attribute is calculated as `amount - total_paid_via_applications`, indicating incremental payments are expected.

4. Invoice status `partial` exists specifically for this case.

**Example flow**: Invoice for KWD 1000 -> InvoicePartial #1 via KNET for KWD 400 (partial) -> InvoicePartial #2 via credit for KWD 300 -> InvoicePartial #3 via bank transfer for KWD 300 -> Status changes to 'paid'.

### What Triggers InvoicePartial Creation?

InvoicePartial is created when:
1. **Split payment initiated**: Agent or client chooses to pay a portion of an invoice through a specific gateway
2. **Full payment via gateway**: Even a full payment creates an InvoicePartial to track the gateway, fees, and method used
3. **Receipt voucher generation**: When a manual payment (cash/bank) is recorded against an invoice

The InvoicePartial is NOT created for credit/wallet applications - those go through `PaymentApplication` instead.

### The Complete Payment Ecosystem Summary

```
INCOMING MONEY:
  1. Direct gateway payment    -> Payment + InvoicePartial + [Gateway]Payment
  2. Client topup/advance      -> Payment + Credit(Topup)
  3. Refund credit             -> Refund + Credit(Refund)

APPLYING MONEY TO INVOICES:
  1. Direct gateway payment    -> InvoicePartial links to Payment
  2. Credit wallet application -> PaymentApplication + Credit(Invoice, negative)

TRACKING:
  - JournalEntry: Double-entry accounting records
  - Transaction: Financial transaction records
  - InvoiceReceipt: Receipt vouchers
  - PaymentTransaction: Gateway transaction attempts
```

---

## Pivot Tables Identified

| Pivot Table | Connects | Purpose |
|---|---|---|
| `payment_link_payment_method` | Payment <-> PaymentMethod | Which methods are available on a payment link |
| `payment_link_payment_method_group` | Payment <-> PaymentMethodGroup | Which method groups shown on payment link |
| `client_agents` | Client <-> Agent | Many-to-many client-agent relationship |

---

## Models NOT Using SoftDeletes

The following payment-related models do NOT use SoftDeletes (records are permanently deleted or never deleted):
- InvoicePartial
- PaymentApplication
- Credit
- PaymentMethod
- PaymentMethodGroup
- PaymentMethodChose
- Charge
- PaymentItem
- PaymentFile
- PaymentTransaction
- InvoiceReceipt
- InvoiceSequence
- All gateway-specific models (TapPayment, MyFatoorahPayment, HesabePayment, UpaymentPayment)

Models that DO use SoftDeletes:
- Invoice
- InvoiceDetail
- Payment
- Task
- Transaction
- JournalEntry
- Refund
- RefundDetail

---

## Multi-Tenant Scoping

The following models have automatic company-based global scopes:
- **Charge**: `WHERE company_id = [user's company]`
- **PaymentMethod**: `WHERE company_id = [user's company]` (only when authenticated)
- **Transaction**: Role-based scoping (admin sees all, company/branch/agent see their own)
- **JournalEntry**: `WHERE company_id = [user's company]`

Models that depend on relationships for tenant isolation (no global scope):
- Invoice (filtered via client_id -> agent_id -> company_id)
- Payment (filtered via client_id or invoice_id)
- InvoicePartial (filtered via invoice_id)
- Credit (has company_id but no global scope)
