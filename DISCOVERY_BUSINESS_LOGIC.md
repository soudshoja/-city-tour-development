# Business Logic & Code Patterns Report

## Models Found

### Invoice Model (`app/Models/Invoice.php`)

**Database Fields:**
- `invoice_number`: Unique identifier (format: INV-YYYY-XXXXX)
- `client_id`: Links to Client
- `agent_id`: Links to Agent
- `currency`: Currency code (e.g., KWD)
- `sub_amount`: Base amount before charges
- `invoice_charge`: Additional charges
- `amount`: Final invoice total
- `status`: Invoice status (enum: InvoiceStatus)
- `invoice_date`: Date invoice was created
- `paid_date`: Date payment received
- `due_date`: Payment due date
- `label`: Custom label/reference
- `tax`, `discount`, `shipping`: Additional line items
- `payment_type`: Type of payment accepted
- `is_client_credit`: Boolean for client credit status
- `account_number`, `bank_name`, `swift_no`, `iban_no`: Bank details
- `accept_payment`, `external_url`: Payment gateway fields

**Relationships:**
- `client()` - BelongsTo Client
- `agent()` - BelongsTo Agent
- `payment()` - HasOne Payment
- `invoiceDetails()` - HasMany InvoiceDetail
- `invoicePartials()` - HasMany InvoicePartial
- `JournalEntrys()` - HasMany JournalEntry
- `transactions()` - HasMany Transaction
- `originalRefunds()` - HasMany Refund (this invoice as original)
- `refund()` - HasOne Refund (this invoice as refund invoice)
- `reminders()` - HasMany Reminder
- `paymentApplications()` - HasMany PaymentApplication

**Key Methods:**
- `recalculateTotal()` - Recalculates amount from invoice details
- `getTotalPaidViaApplicationsAttribute()` - Accessor for total paid
- `getRemainingBalanceAttribute()` - Accessor for remaining balance
- `isFullyPaidViaApplications()` - Check if invoice is fully paid

**Validation Rules:**
- Status must be from InvoiceStatus enum (enforced in boot method)

---

### Task Model (`app/Models/Task.php`)

**Key Fields:**
- `client_id`, `agent_id`, `company_id`, `supplier_id`
- `type`: Task type (flight, hotel, visa, insurance, tour, cruise, car, rail, esim, event, lounge, ferry)
- `status`, `supplier_status`: Current states
- `client_ref`, `reference`, `gds_reference`: Various identifiers
- `price`, `original_price`, `exchange_currency`, `exchange_rate`: Pricing
- `tax`, `surcharge`, `penalty_fee`: Charges
- `total`, `original_total`: Calculated totals
- `invoice_price`: What was invoiced to client
- `payment_type`, `payment_method_account_id`: Payment details

**Relationships:**
- `invoiceDetail()` - HasOne InvoiceDetail
- `refundDetail()` - HasOne RefundDetail
- `agent()` - BelongsTo Agent
- `client()` - BelongsTo Client
- `supplier()` - BelongsTo Supplier
- `journalEntries()` - HasMany JournalEntry
- `company()` - BelongsTo Company
- `paymentMethod()` - BelongsTo Account

**Required Fields (for completion):**
```php
[
    'company_id',
    'supplier_id',
    'type',
    'status',
    'reference',
    'total',
]
```

**Key Methods:**
- `getIsCompleteAttribute()` - Checks if all required fields are filled
- `scopeCompleted()` - Scope to get completed tasks

---

### Client Model (`app/Models/Client.php`)

**Database Fields:**
- `name`, `first_name`, `middle_name`, `last_name`
- `email`, `phone`, `address`
- `passport_no`, `civil_no`, `date_of_birth`
- `agent_id`, `company_id`: Links to organization
- `country_code`

**Relationships:**
- `agent()` - BelongsTo Agent
- `agents()` - BelongsToMany Agent
- `invoices()` - HasMany Invoice
- `tasks()` - HasMany Task
- `credits()` - HasMany Credit
- `subClients()` - HasMany ClientGroup (parent_client_id)
- `parentClients()` - HasMany ClientGroup (child_client_id)
- `account()` - BelongsTo Account
- `refunds()` - HasMany RefundClient

**Accessors:**
- `full_name` - Concatenated from first, middle, last names

---

### Agent Model (`app/Models/Agent.php`)

**Database Fields:**
- `user_id`, `name`, `email`, `phone_number`
- `branch_id`: Links to Branch
- `type_id`: Links to AgentType
- `commission`, `salary`, `target`: Financial fields
- `tbo_reference`, `amadeus_id`: Supplier credentials

**Relationships:**
- `branch()` - BelongsTo Branch
- `agentType()` - BelongsTo AgentType
- `tasks()` - HasMany Task
- `invoices()` - HasMany Invoice
- `clients()` - BelongsToMany Client
- `user()` - BelongsTo User
- `account()` - HasOne Account
- `chargeSetting()` - HasOne AgentCharge
- `refundClients()` - HasMany RefundClient

**Key Methods:**
- `clientQuery()` - Get client query for this agent
- `getEffectiveChargeSetting($companyId)` - Get charge settings with company scope

---

### InvoiceDetail Model (`app/Models/InvoiceDetail.php`)

**Database Fields:**
- `invoice_id`: Links to Invoice
- `invoice_number`: Denormalized invoice number
- `task_id`: Links to Task
- `task_description`: Text description
- `task_remark`: Additional remarks
- `client_notes`: Notes for client
- `task_price`: What was invoiced to client
- `supplier_price`: What was paid to supplier
- `markup_price`: Difference (task_price - supplier_price)
- `profit`: Profit amount
- `commission`: Commission amount
- `paid`: Boolean flag

**Relationships:**
- `invoice()` - BelongsTo Invoice
- `task()` - BelongsTo Task
- `JournalEntrys()` - HasMany JournalEntry
- `payment()` - HasOne Payment

---

### InvoicePartial Model (`app/Models/InvoicePartial.php`)

**Database Fields:**
- `invoice_id`, `invoice_number`: Links to Invoice
- `client_id`: Links to Client
- `amount`: Partial payment amount
- `service_charge`: Service charge amount
- `gateway_fee`: Payment gateway fee
- `status`: Payment status
- `type`: Type of partial (e.g., gateway, deposit)
- `charge_id`: Links to Charge
- `payment_gateway`: Gateway used (e.g., MyFatoorah, Tap, Knet)
- `payment_method`: Payment method
- `payment_id`: Links to Payment
- `receipt_voucher_id`: Links to receipt
- `expiry_date`: Payment expiry

**Relationships:**
- `invoice()` - BelongsTo Invoice
- `client()` - BelongsTo Client
- `payment()` - BelongsTo Payment
- `paymentMethod()` - BelongsTo PaymentMethod
- `charge()` - BelongsTo Charge
- `paymentApplications()` - HasMany PaymentApplication
- `invoiceReceipt()` - HasOne InvoiceReceipt

---

### InvoiceSequence Model (`app/Models/InvoiceSequence.php`)

**Database Fields:**
- `company_id`: Unique per company
- `current_sequence`: Current invoice number counter

**Purpose:** Maintains sequential invoice number generation per company.

---

### Payment Model (`app/Models/Payment.php`)

**Database Fields:**
- `client_id`, `agent_id`: Links to parties
- `invoice_id`: Links to Invoice
- `amount`: Payment amount
- `currency`: Currency code
- `payment_date`: When payment was made
- `status`: Payment status
- `payment_gateway`: Gateway used (MyFatoorah, Tap, Knet, Hesabe, uPayment)
- `payment_method_id`: Links to PaymentMethod
- `payment_url`: URL for payment
- `expiry_date`: Link expiry
- `service_charge`, `gateway_fee`: Fees
- `tax`, `discount`, `shipping`: Adjustments
- `voucher_number`, `payment_reference`: References
- `auth_code`: Gateway authorization code
- `notes`: Additional notes
- `completed`: Boolean flag
- `is_disabled`: Boolean flag
- `send_payment_receipt`: Boolean flag

**Relationships:**
- `client()` - BelongsTo Client
- `agent()` - BelongsTo Agent
- `invoice()` - BelongsTo Invoice
- `transactions()` - MorphMany Transaction
- `partials()` - HasMany InvoicePartial
- `paymentMethod()` - BelongsTo PaymentMethod
- `tapPayment()` - HasOne TapPayment
- `myFatoorahPayment()` - HasOne MyFatoorahPayment
- `hesabePayment()` - HasOne HesabePayment

---

## Controllers Found

### InvoiceController (`app/Http/Controllers/InvoiceController.php`)

**Key Methods:**

#### 1. `store(Request $request): JsonResponse` (Line 1171)
Creates a new invoice with line items.

**Validation:**
```php
'tasks' => 'required|array',
'tasks.*.id' => 'required|integer',
'tasks.*.description' => 'required|string',
'tasks.*.invprice' => 'required|numeric',
'tasks.*.supplier_id' => 'required|integer',
'tasks.*.client_id' => 'required|integer',
'tasks.*.agent_id' => 'required|integer',
'tasks.*.total' => 'required|numeric',
'label' => 'nullable|string',
'invdate' => 'required|date',
'duedate' => 'nullable|date',
'subTotal' => 'required|numeric',
'clientId' => 'required|integer',
'agentId' => 'required|integer',
'invoiceNumber' => 'required|string',
'currency' => 'required|string',
'payment_id' => 'nullable|integer',
```

**Process:**
1. Validate request data
2. Get agent and extract company_id, branch_id
3. Create Invoice record with status='unpaid'
4. For each task:
   - Create InvoiceDetail record with task price, supplier price, and calculated markup/profit
5. Update InvoiceSequence counter
6. Return JSON response with invoice ID

**Code Excerpt:**
```php
$invoice = Invoice::create([
    'invoice_number' => $invoiceNumber,
    'agent_id' => $agentId,
    'client_id' => $clientId,
    'sub_amount' => $amount,
    'amount' => $amount,
    'currency' => $currency,
    'status' => 'unpaid',
    'invoice_date' => $invdate,
    'due_date' => $duedate,
]);

foreach ($tasks as $task) {
    $invoiceDetail = InvoiceDetail::create([
        'invoice_id' => $invoice->id,
        'invoice_number' => $invoiceNumber,
        'task_id' => $task['id'],
        'task_description' => $task['description'],
        'task_price' => $task['invprice'],
        'supplier_price' => $selectedtask->total,
        'markup_price' => $task['invprice'] - $selectedtask->total,
        'profit' => $task['invprice'] - $selectedtask->total,
        'paid' => false,
    ]);
}
```

#### 2. `generateInvoiceNumber($sequence)` (Line 1977)
Generates invoice number in format INV-YYYY-XXXXX.

```php
public function generateInvoiceNumber($sequence)
{
    $year = now()->year;
    return sprintf('INV-%s-%05d', $year, $sequence);
}
```

#### 3. `getInvoiceNumberGenerated($companyId): string` (Line 4840)
Private method that gets next sequential invoice number.

```php
private function getInvoiceNumberGenerated($companyId): string
{
    $invoiceSequence = InvoiceSequence::firstOrCreate(
        ['company_id' => $companyId],
        ['current_sequence' => 1]
    );
    $currentSequence = $invoiceSequence->current_sequence;
    $invoiceNumber = $this->generateInvoiceNumber($currentSequence);
    $invoiceSequence->current_sequence++;
    $invoiceSequence->save();
    return $invoiceNumber;
}
```

#### 4. `autoGenerateInvoice(Task $task, Payment $payment): array` (Line 4851)
Auto-generates invoice when payment is made to a task.

**Features:**
- Uses database transaction
- Creates single InvoiceDetail from payment amount
- Creates journal entries for accounting

```php
$invoice = DB::transaction(function () use ($task, $payment) {
    $invoice = Invoice::create([
        'invoice_number' => $this->getInvoiceNumberGenerated($task->company_id),
        'agent_id' => $task->agent_id,
        'client_id' => $task->client_id,
        'company_id' => $task->company_id,
        'sub_amount' => $payment->amount,
        'amount' => $payment->amount,
        // ... more fields
    ]);
    // Create InvoiceDetail and JournalEntries
    return $invoice;
});
```

#### 5. `addJournalEntry()` (Line 1292)
Creates accounting journal entries for invoice creation.

**Creates two entries per invoice:**
1. **DEBIT Asset (Receivable):** Clients account (Asset increase)
2. **CREDIT Income (Revenue):** Booking Revenue account (Income increase)

```php
// Entry 1: Create client receivable account entry
JournalEntry::create([
    'account_id' => $clientAccount->id,
    'invoice_id' => $invoiceId,
    'debit' => $task->invoiceDetail->task_price,
    'credit' => 0,
    'type' => 'receivable',
    'description' => 'Invoice created for (Assets): ' . $clientName,
]);

// Entry 2: Create booking revenue entry
JournalEntry::create([
    'account_id' => $detailsAccount->id,
    'invoice_id' => $invoiceId,
    'debit' => 0,
    'credit' => $task->invoiceDetail->task_price,
    'type' => 'booking_revenue',
    'description' => 'Invoice created for (Income): ' . $task->reference,
]);
```

**Special Feature:** Auto-creates booking revenue accounts if they don't exist.

#### 6. `createPaymentJournalEntries()` (Line 1872)
Creates journal entries when payment is applied to invoice.

---

## Invoice Creation Pattern

### Standard Flow (Manual Invoice Creation)

**Step 1: Client initiates invoice creation**
- Selects multiple tasks to group into one invoice
- Specifies invoice date and due date
- Confirms client and agent

**Step 2: InvoiceController::store() method**
```
1. Validate all request data
2. Determine company and branch from agent
3. Create Invoice record with status='unpaid'
4. For each task:
   a. Fetch Task, Supplier, Client, Agent records
   b. Create InvoiceDetail record
   c. Calculate: task_price (to client), supplier_price (from supplier), profit (difference)
5. Increment invoice sequence counter
6. Return success response with invoice ID
```

**Step 3: Optional - Create accounting entries**
- addJournalEntry() creates double-entry bookkeeping records
- Creates receivable asset (Debit) and booking revenue (Credit)

### Auto-Invoice Flow (Payment-Triggered)

**Step 1: Payment received on Task**
- autoGenerateInvoice() is called with Task and Payment

**Step 2: Transaction-wrapped creation**
```
1. Create Invoice with payment amount
2. Create single InvoiceDetail
3. Create accounting journal entries
4. Return invoice data
```

---

## Invoice Number Generation

### Sequence Management

**Database:**
- Table: `invoice_sequence`
- Per-company counter: Each company has its own sequence

**Format:**
```
INV-YYYY-XXXXX
  ↓    ↓     ↓
 Type Year Sequential(5-digit zero-padded)
```

**Generation Logic:**
```php
1. Look up or create InvoiceSequence for company
2. Get current_sequence value
3. Format: sprintf('INV-%s-%05d', year, sequence)
4. Increment sequence by 1
5. Save updated sequence
6. Return formatted invoice number
```

**Example:**
- INV-2025-00001 (first invoice in 2025)
- INV-2025-00002 (second invoice in 2025)
- INV-2026-00001 (first invoice in 2026)

---

## Payment Processing Pattern

### Payment Method Structure

**Payment Methods Table:**
- Stores available payment methods (Cash, Check, Bank Transfer, etc.)
- Links to PaymentMethodGroup (payment gateway groups)

**Payment Flow:**

1. **Payment Creation** (Payment model):
   - Stores payment details (amount, gateway, date, status)
   - Links to Invoice, Client, Agent
   - Stores payment gateway details (URL, expiry, auth code)

2. **Multiple Gateway Support:**
   - MyFatoorah (hasOne MyFatoorahPayment)
   - Tap (hasOne TapPayment)
   - Hesabe (hasOne HesabePayment)
   - uPayment
   - Knet

3. **Payment Application:**
   - PaymentApplication model ties payment to specific invoices
   - Supports partial payments
   - Multiple payments can be applied to one invoice

4. **Journal Entries:**
   - Payment creates debit to Bank account
   - Credit to Invoice Receivable account
   - Records in JournalEntry table

### Payment Status Flow
```
pending → completed
          ↓
       paid (invoice status)
```

---

## Existing Bulk/Import Patterns

### TasksImport Class (`app/Imports/TasksImport.php`)

**Framework:** Maatwebsite/Laravel-Excel

**Process:**
```php
class TasksImport implements ToModel, WithHeadingRow
```

**Features:**
- Reads Excel files with headers
- Creates Tasks, Clients, Items from spreadsheet
- Checks for duplicates before creating
- Prevents duplicate insertion

**Example Columns:**
- `description` - Task description
- `contract_id` - Unique identifier
- `contract_code` - Code
- `task_type` - Task type
- `status` - Task status
- `client_email` - Client email
- `agent_email` - Agent email
- `total_price` - Price
- `payment_date` - Payment date
- `client_name` - Client name

**Other Import Classes:**
- `AgentsImport.php` - Bulk import agents
- `ClientsImport.php` - Bulk import clients
- `CompaniesImport.php` - Bulk import companies
- `AccountsImport.php` - Bulk import accounts

---

## Key Business Rules Discovered

### 1. **Invoice Number Uniqueness**
- Each invoice has unique invoice_number
- Format: INV-YYYY-XXXXX
- Incremented per company, reset per year
- InvoiceSequence table maintains state

### 2. **Multi-Currency Support**
- Tasks support original_currency and exchange_currency
- Exchange rates are tracked (exchange_rate field)
- Invoices store currency field
- Journal entries include currency and exchange_rate

### 3. **Profit Calculation**
- `Profit = Task Price (to client) - Supplier Price (to supplier)`
- Stored in InvoiceDetail.profit
- Also calculates markup_price (identical to profit in current code)

### 4. **Task-to-Invoice Relationship**
- One task can appear in one or more invoices
- Task has one InvoiceDetail
- InvoiceDetail links Task → Invoice
- Invoice has many InvoiceDetails (can include multiple tasks)

### 5. **Invoice Lifecycle**
```
Created (status='unpaid')
  ↓
Partially Paid (InvoicePartial records)
  ↓
Fully Paid (status='paid', remaining_balance <= 0)
```

### 6. **Double-Entry Bookkeeping**
- Every invoice creates accounting entries:
  - **DEBIT:** Accounts Receivable (Asset)
  - **CREDIT:** Booking Revenue (Income)
- Currency conversions tracked in journal entries
- Supports refunds via Refund model

### 7. **Payment Gateways**
- Multiple gateways supported per payment
- InvoicePartial tracks gateway and payment method
- Supports partial payments via multiple InvoicePartials

### 8. **Agent-Specific Settings**
- AgentCharge determines charge distribution:
  - Gateway charges (agent vs company split)
  - Supplier charges (agent vs company split)
- Charge settings can be company-specific

### 9. **Company Isolation**
- All data scoped to company_id
- Invoice sequences per company
- Agent charges configured per company
- Multi-tenant architecture enforced

### 10. **Refund Support**
- Two-way Refund relationship:
  - Original invoice → many refunds (originalRefunds)
  - Refund invoice ← one invoice (refund)
- Full and partial refunds supported

### 11. **Client Credit System**
- Client model has is_client_credit flag on Invoice
- Supports credit-based transactions
- Credit tracking via Credit model

### 12. **Payment Application Strategy**
- PaymentApplication ties payments to invoices
- Supports applying single payment to multiple invoices
- getTotalPaidViaApplicationsAttribute() calculates total
- remaining_balance calculated as: amount - total_paid_via_applications

---

## Data Flow Summary

```
Task (supplier's service)
  ↓
Create Invoice with selected tasks
  ↓
InvoiceDetail (line item)
  ↓
Invoice (aggregate)
  ↓
Payment (received from client)
  ↓
InvoicePartial (payment chunk)
  ↓
PaymentApplication (apply to invoice)
  ↓
JournalEntry (accounting record)
  ↓
InvoiceReceipt (confirmation)
```

---

## Files Involved

### Core Models
- `/app/Models/Invoice.php`
- `/app/Models/InvoiceDetail.php`
- `/app/Models/InvoicePartial.php`
- `/app/Models/InvoiceSequence.php`
- `/app/Models/Task.php`
- `/app/Models/Client.php`
- `/app/Models/Agent.php`
- `/app/Models/Payment.php`
- `/app/Models/PaymentApplication.php`

### Controllers
- `/app/Http/Controllers/InvoiceController.php` (4000+ lines)

### Services
- `/app/Services/PaymentApplicationService.php`

### Imports
- `/app/Imports/TasksImport.php`
- `/app/Imports/ClientsImport.php`
- `/app/Imports/AgentsImport.php`

### Enums
- `app/Enums/InvoiceStatus.php`

### Database
- Tables: invoices, invoice_details, invoice_partials, invoice_sequence, payments, tasks, clients, agents

---

## Integration Points

### Auto-Billing Integration
- `RunAutoBilling` command triggers automatic invoice generation
- Uses `autoGenerateInvoice()` method
- Creates invoices from pending payments

### Journal Entry Integration
- `addJournalEntry()` creates accounting records
- Supports auto-account creation for booking revenue
- Tracks currency conversions

### Payment Gateway Integration
- MyFatoorah, Tap, Hesabe, uPayment, Knet
- Each gateway has its own model (TapPayment, MyFatoorahPayment, HesabePayment)
- Gateway fee tracking in InvoicePartial

### Refund System
- Refund model links to original invoice
- Can create refund invoices
- Supports partial refunds
