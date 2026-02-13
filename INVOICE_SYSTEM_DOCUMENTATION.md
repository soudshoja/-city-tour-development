# 📘 Soud Laravel Invoice System - Complete Documentation

## Table of Contents
1. [System Overview](#1-system-overview)
2. [Core Data Models](#2-core-data-models)
3. [Entity Relationships](#3-entity-relationships)
4. [Invoice Creation Flow](#4-invoice-creation-flow)
5. [Payment & Credit System](#5-payment--credit-system)
6. [Accounting & Journal Entries](#6-accounting--journal-entries)
7. [Key Business Rules](#7-key-business-rules)
8. [API Endpoints](#8-api-endpoints)
9. [Database Schema Summary](#9-database-schema-summary)

---

## 1. System Overview

### What This System Does
A multi-tenant Laravel 11 platform for travel agencies to manage bookings, invoices, and payments with integrated accounting.

### Core Hierarchy
```
Company
  └── Branch
       └── Agent
            ├── Tasks (bookings)
            ├── Clients (travelers)
            └── Invoices (billing)
```

### Key Concept
**Tasks exist BEFORE invoicing**. Invoices are created from existing tasks, not created with tasks.

---

## 2. Core Data Models

### 2.1 Task (Booking/Service)

**Purpose**: Represents a single booking or service (flight, hotel, visa, etc.)

**Key Fields**:
```php
- id
- type                    // flight, hotel, visa, insurance, tour, cruise, car, rail, esim, event, lounge, ferry
- status                  // issued, reissued, refund, void
- client_id               // WHO is traveling (can differ from invoice payer)
- agent_id                // WHO handles this task
- supplier_id             // WHERE we booked from
- company_id              // Multi-tenant isolation
- reference               // Task reference number
- total                   // Supplier price (what we pay)
- invoice_price           // Client price (what client pays)
- issued_date
- expiry_date
```

**Required Fields**:
- company_id, supplier_id, type, status, reference, total

**Business Rules**:
- ✅ Can only be invoiced ONCE (hasOne InvoiceDetail)
- ✅ Must exist before invoice creation
- ✅ Can have type-specific details (TaskFlightDetail, TaskHotelDetail, etc.)

---

### 2.2 Invoice (Payment Collection Document)

**Purpose**: Bill to collect payment from a client

**Key Fields**:
```php
- id
- invoice_number          // Auto-generated: INV-YYYY-XXXXX
- client_id               // WHO is paying (the payer)
- agent_id                // WHO handles this invoice
- currency                // KWD, USD, etc.
- sub_amount              // Sum of task prices
- invoice_charge          // Gateway fees
- amount                  // sub_amount + invoice_charge
- status                  // unpaid, partial, paid
- invoice_date
- due_date
- paid_date
```

**Status Flow**:
```
unpaid → partial → paid
```

**Business Rules**:
- ✅ ONE client_id (the payer)
- ✅ Can contain tasks from DIFFERENT clients (e.g., parent paying for family)
- ✅ Can contain tasks from DIFFERENT suppliers
- ✅ Must have ONE agent_id (invoice handler)
- ✅ Invoice number generated from InvoiceSequence table (per company)

---

### 2.3 InvoiceDetail (Bridge/Line Item)

**Purpose**: Links tasks to invoices (many-to-one relationship)

**Key Fields**:
```php
- id
- invoice_id              // → Invoice
- task_id                 // → Task
- invoice_number          // Denormalized for quick lookup
- task_description        // Copied from task
- task_price              // What client pays
- supplier_price          // What we pay supplier (from task.total)
- markup_price            // task_price - supplier_price
- profit                  // Same as markup_price
- paid                    // boolean
```

**Business Rules**:
- ✅ One task can only have ONE InvoiceDetail (one invoice per task)
- ✅ One invoice can have MANY InvoiceDetails (multiple tasks)
- ✅ Existence of InvoiceDetail = task is "invoiced"

---

### 2.4 Payment (Client Wallet Topup)

**Purpose**: Client wallet credit/topup (like prepaid balance)

**Key Fields**:
```php
- id
- voucher_number          // PAY-YYYY-XXXXX
- client_id               // WHO is topping up
- agent_id
- amount                  // Topup amount
- currency
- payment_gateway         // Knet, MyFatoorah, Tap, etc.
- payment_method_id
- status                  // pending, initiate, completed
- payment_date
- expiry_date
```

**Status Flow**:
```
pending → initiate → completed
```

**Business Rules**:
- ✅ Payment = TOPUP (adds to client wallet)
- ✅ NOT directly linked to invoice (link happens via PaymentApplication)
- ✅ One Payment can be used for MULTIPLE invoices until depleted
- ✅ Has available_balance attribute (from Credit records)

---

### 2.5 InvoicePartial (Payment Installment)

**Purpose**: Records a payment installment for an invoice

**Key Fields**:
```php
- id
- invoice_id              // → Invoice
- invoice_number
- client_id
- amount                  // Installment amount
- service_charge          // Gateway fee
- gateway_fee             // Accounting fee
- status                  // unpaid, paid
- type                    // full, partial, split, credit
- payment_gateway         // Credit, Cash, Knet, etc.
- payment_method
- expiry_date
```

**Business Rules**:
- ✅ One invoice can have MANY InvoicePartials (split payments)
- ✅ Invoice status calculated from partials (all paid → invoice paid)
- ✅ Gateway = "Credit" → uses client wallet balance
- ✅ Gateway = "Cash" → creates Receipt Voucher

---

### 2.6 Credit (Ledger/Balance Tracker)

**Purpose**: Tracks client wallet balance (double-entry ledger)

**Key Fields**:
```php
- id
- client_id
- type                    // TOPUP, INVOICE, REFUND, INVOICE_REFUND
- amount                  // Positive = add, Negative = deduct
- payment_id              // If from topup
- invoice_partial_id      // If used for invoice payment
- refund_id               // If from refund
- description
- gateway_fee
```

**Credit Types**:
```php
Credit::TOPUP          // +amount (add to wallet)
Credit::INVOICE        // -amount (deduct from wallet)
Credit::REFUND         // +amount (refund adds to wallet)
Credit::INVOICE_REFUND // Amount varies
```

**Business Rules**:
- ✅ Client balance = SUM(amount) for all client's credits
- ✅ TOPUP creates positive credit (adds balance)
- ✅ INVOICE creates negative credit (deducts balance)
- ✅ Links Payment (topup) to Invoice (usage) via payment_id + invoice_partial_id

---

### 2.7 PaymentApplication (Payment-to-Invoice Link)

**Purpose**: Links which Payment (topup) was used for which Invoice

**Key Fields**:
```php
- id
- payment_id              // → Payment (the topup)
- invoice_id              // → Invoice (the bill)
- invoice_partial_id      // → InvoicePartial (the installment)
- amount_applied          // How much from this payment
```

**Business Rules**:
- ✅ One Payment can have MANY PaymentApplications (used across multiple invoices)
- ✅ One Invoice can have MANY PaymentApplications (paid from multiple topups)
- ✅ Tracks which topup paid which invoice

---

## 3. Entity Relationships

### 3.1 Task → Invoice Relationship

```
Task (1) ←──→ (1) InvoiceDetail (M) ←──→ (1) Invoice
```

**Example**:
```
Invoice #INV-2024-00001 (Payer: Client A)
├── InvoiceDetail #1 → Task #123 (Traveler: Client A - Flight)
├── InvoiceDetail #2 → Task #124 (Traveler: Client B - Hotel)
└── InvoiceDetail #3 → Task #125 (Traveler: Client C - Visa)
```

**Key Insight**: Invoice's `client_id` = PAYER, but tasks' `client_id` = TRAVELER

---

### 3.2 Payment → Invoice Relationship (via Credit)

```
Payment (1) ──→ (M) Credit ←── (M) InvoicePartial ←── (1) Invoice
                    ↓
              PaymentApplication (links Payment to Invoice)
```

**Example**:
```
Payment #PAY-2024-0001 (500 KWD topup)
├── Credit #1: +500 KWD (TOPUP)
├── Credit #2: -200 KWD (INVOICE) → Invoice #INV-2024-0001
├── Credit #3: -150 KWD (INVOICE) → Invoice #INV-2024-0002
└── Available Balance: 150 KWD
```

---

### 3.3 Complete Data Flow

```
┌─────────┐
│  Task   │ (Booking exists)
└────┬────┘
     │
     ├──► Invoice created (store method)
     │    ├── Invoice record (status: unpaid)
     │    ├── InvoiceDetail records (link tasks)
     │    └── InvoiceSequence updated
     │
     ├──► Payment added (savePartial method)
     │    ├── InvoicePartial created
     │    ├── Choose payment method:
     │    │   ├─► Credit → PaymentApplicationService
     │    │   │           ├── Link to Payment (topup)
     │    │   │           └── Create negative Credit
     │    │   ├─► Cash → Create Receipt Voucher
     │    │   └─► Gateway → Create payment link
     │    │
     │    ├── Transaction created
     │    ├── JournalEntry created (accounting)
     │    └── Invoice status updated
     │
     └──► Accounting finalized
          ├── DEBIT: Accounts Receivable
          ├── CREDIT: Booking Revenue
          └── If credit: DEBIT Client Credit, CREDIT Receivable
```

---

## 4. Invoice Creation Flow

### 4.1 Two-Step Process

#### **Step 1: Create Invoice (`InvoiceController@store`)**

**Location**: `invoice/create.blade.php` → POST to `/invoice/store`

**Input**:
```javascript
{
  clientId: 123,           // The PAYER
  agentId: 456,
  tasks: [                 // Array of EXISTING tasks
    {
      id: 789,
      description: "Flight to Dubai",
      invprice: 200,       // Client price
      supplier_id: 10,
      client_id: 124,      // Traveler (can differ from payer)
      agent_id: 456,
      total: 150           // Supplier price
    },
    // ... more tasks
  ],
  subTotal: 500,
  invoiceNumber: "INV-2024-00001",  // Passed but regenerated
  currency: "KWD",
  invdate: "2024-02-12",
  duedate: "2024-03-12"
}
```

**Process**:
```php
1. Validate request
2. Get agent → branch → company (hierarchy)
3. Create Invoice:
   - invoice_number (from request, but will be regenerated)
   - agent_id, client_id
   - sub_amount, amount
   - currency
   - status = 'unpaid'  ⚠️ ALWAYS starts as unpaid
   - invoice_date, due_date

4. For each task:
   - Validate task exists
   - Create InvoiceDetail:
     * invoice_id, task_id
     * task_price (from task.invprice)
     * supplier_price (from task.total)
     * profit (task_price - supplier_price)

5. Update InvoiceSequence (increment counter)

6. Return success → Redirect to invoice/edit
```

**What's NOT done**:
- ❌ NO accounting entries created
- ❌ NO payment processing
- ❌ NO transaction records
- ❌ NO journal entries
- ❌ Invoice stays 'unpaid'

**Code Location**: `app/Http/Controllers/InvoiceController.php:1171-1290`

---

#### **Step 2: Add Payment (`InvoiceController@savePartial`)**

**Location**: `invoice/edit.blade.php` → POST to `/invoice/savePartial`

**Input**:
```javascript
{
  invoiceId: 1,
  invoiceNumber: "INV-2024-00001",
  clientId: 123,
  amount: 500,
  type: "full",          // full, partial, split, credit
  gateway: "Credit",     // Credit, Cash, Knet, MyFatoorah, etc.
  method: "knet",
  credit: true,          // If using client wallet
  payment_allocations: [ // If credit, which topups to use
    { payment_id: 10, amount: 300 },
    { payment_id: 11, amount: 200 }
  ],
  companyId: 5
}
```

**Process** (wrapped in `DB::transaction`):
```php
1. Load Invoice with relationships

2. Validate credit balance (if gateway = "Credit")

3. Create InvoicePartial:
   - amount, status, payment_gateway
   - service_charge = 0 (if credit)
   - gateway_fee = 0 (if credit)

4. IF gateway = "Credit":
   A. IF payment_allocations provided:
      → PaymentApplicationService.linkPaymentsToInvoicePartial()
        - Create PaymentApplication records
        - Create Credit records (type: INVOICE, amount: negative)
        - Track applied payments for COA

   B. ELSE (legacy):
      → Create generic Credit record

5. Update Invoice:
   - payment_type
   - is_client_credit
   - Calculate status from all partials:
     * All partials paid → 'paid'
     * Some paid, some unpaid → 'partial'
     * All unpaid → 'unpaid'
   - Set paid_date if paid

6. Create or Reuse Transaction record

7. IF first payment for this invoice:
   → For each task:
     * Call addJournalEntry():
       - DEBIT: Accounts Receivable (Client owes us)
       - CREDIT: Booking Revenue (We earned it)

8. IF credit payment:
   → createCreditPaymentCOA():
     - DEBIT: Client Credit (Reduce balance)
     - CREDIT: Accounts Receivable (Payment received)

9. IF cash payment:
   → Create Receipt Voucher

10. Return success
```

**Code Location**: `app/Http/Controllers/InvoiceController.php:821-1125`

---

### 4.2 Complete Example

**Scenario**: Create invoice for 3 tasks, pay with credit

```
┌─── STEP 1: Create Invoice ───────────────────────────┐
│                                                       │
│  POST /invoice/store                                 │
│  {                                                    │
│    clientId: 100,  (Parent)                         │
│    agentId: 50,                                      │
│    tasks: [                                          │
│      {id: 1, client_id: 101, invprice: 200},  (Child 1) │
│      {id: 2, client_id: 102, invprice: 150},  (Child 2) │
│      {id: 3, client_id: 103, invprice: 100}   (Child 3) │
│    ],                                                │
│    subTotal: 450                                     │
│  }                                                    │
│                                                       │
│  CREATES:                                            │
│  ✓ Invoice #1 (client_id=100, status=unpaid, amount=450) │
│  ✓ InvoiceDetail #1 (invoice_id=1, task_id=1, task_price=200) │
│  ✓ InvoiceDetail #2 (invoice_id=1, task_id=2, task_price=150) │
│  ✓ InvoiceDetail #3 (invoice_id=1, task_id=3, task_price=100) │
│                                                       │
│  → Redirect to invoice/edit                          │
└───────────────────────────────────────────────────────┘

┌─── STEP 2: Add Credit Payment ───────────────────────┐
│                                                       │
│  POST /invoice/savePartial                           │
│  {                                                    │
│    invoiceId: 1,                                     │
│    amount: 450,                                      │
│    gateway: "Credit",                                │
│    payment_allocations: [                            │
│      {payment_id: 10, amount: 450}  (Use PAY-001)   │
│    ]                                                  │
│  }                                                    │
│                                                       │
│  CREATES:                                            │
│  ✓ InvoicePartial #1 (invoice_id=1, amount=450, status=paid) │
│  ✓ PaymentApplication (payment_id=10, invoice_id=1, amount=450) │
│  ✓ Credit (type=INVOICE, amount=-450, payment_id=10, invoice_partial_id=1) │
│  ✓ Transaction #1 (invoice_id=1, amount=450)        │
│  ✓ JournalEntry #1 (DEBIT: A/R 200, CREDIT: Revenue 200) Task 1 │
│  ✓ JournalEntry #2 (DEBIT: A/R 150, CREDIT: Revenue 150) Task 2 │
│  ✓ JournalEntry #3 (DEBIT: A/R 100, CREDIT: Revenue 100) Task 3 │
│  ✓ JournalEntry #4 (DEBIT: Client Credit 450, CREDIT: A/R 450) │
│  ✓ Invoice status updated → 'paid'                  │
│  ✓ Invoice paid_date → now()                        │
│                                                       │
│  RESULT:                                             │
│  Payment #10 balance: 500 - 450 = 50 KWD remaining  │
│  Client credit balance updated                       │
│  Invoice fully paid                                  │
└───────────────────────────────────────────────────────┘
```

---

## 5. Payment & Credit System

### 5.1 Payment Topup Flow

```
┌─── Agent Creates Payment Link ─────────────────────┐
│                                                     │
│  Payment::create([                                 │
│    'voucher_number' => 'PAY-2024-0001',           │
│    'amount' => 500,                                │
│    'status' => 'pending',                          │
│    'payment_gateway' => 'Knet',                    │
│    'client_id' => 123                              │
│  ])                                                 │
│                                                     │
│  → Send link to client via WhatsApp/Email         │
└─────────────────────────────────────────────────────┘

┌─── Client Pays via Gateway ────────────────────────┐
│                                                     │
│  Client opens payment link                         │
│  → Redirects to Knet/MyFatoorah                    │
│  → Client completes payment                        │
│  → Webhook received                                │
│                                                     │
│  Payment->update(['status' => 'completed'])        │
│                                                     │
│  Credit::create([                                  │
│    'type' => Credit::TOPUP,                        │
│    'amount' => +500,   ← POSITIVE (add to wallet) │
│    'payment_id' => $payment->id,                   │
│    'client_id' => 123                              │
│  ])                                                 │
│                                                     │
│  RESULT: Client has 500 KWD in wallet             │
└─────────────────────────────────────────────────────┘
```

---

### 5.2 Credit Balance Calculation

```php
// Get client's total credit balance
Credit::getTotalCreditsByClient($clientId);  // SUM(amount)

// Example ledger:
┌────┬─────────┬────────┬─────────────┬─────────┐
│ ID │ Type    │ Amount │ Payment ID  │ Balance │
├────┼─────────┼────────┼─────────────┼─────────┤
│ 1  │ TOPUP   │ +500   │ PAY-001     │ 500     │
│ 2  │ INVOICE │ -200   │ PAY-001     │ 300     │
│ 3  │ TOPUP   │ +300   │ PAY-002     │ 600     │
│ 4  │ INVOICE │ -150   │ PAY-002     │ 450     │
│ 5  │ REFUND  │ +100   │ null        │ 550     │
└────┴─────────┴────────┴─────────────┴─────────┘

Current Balance = 550 KWD
```

---

### 5.3 Payment Available Balance

```php
// Get how much is still available from a specific Payment
Payment::find(10)->available_balance;

// Calculated as:
Credit::getAvailableBalanceByPayment($paymentId);  // SUM(amount) WHERE payment_id = X

// Example:
Payment #PAY-001 (500 KWD topup)
├── Credit: +500 (TOPUP)
├── Credit: -200 (used for Invoice #1)
├── Credit: -150 (used for Invoice #2)
└── Available: 150 KWD

Payment->available_balance = 150 KWD
```

---

## 6. Accounting & Journal Entries

### 6.1 When Accounting Happens

**❌ NOT during `store()` (invoice creation)**
**✅ ONLY during `savePartial()` (payment addition)**

---

### 6.2 Journal Entry Structure

**Created via**: `InvoiceController@addJournalEntry()`

**For Each Task** in the invoice:

```php
// ENTRY 1: DEBIT Asset (Receivable)
JournalEntry::create([
  'account_id' => $clientAccount->id,  // "Clients" under "Accounts Receivable"
  'transaction_id' => $transaction->id,
  'task_id' => $task->id,
  'invoice_id' => $invoice->id,
  'invoice_detail_id' => $invoiceDetail->id,
  'debit' => $task->invoiceDetail->task_price,  // What client owes
  'credit' => 0,
  'type' => 'receivable',
  'description' => 'Invoice created for (Assets): Client Name'
]);

// ENTRY 2: CREDIT Income (Revenue)
JournalEntry::create([
  'account_id' => $bookingAccount->id,  // "Flight Booking Revenue", "Hotel Booking Revenue", etc.
  'transaction_id' => $transaction->id,
  'task_id' => $task->id,
  'invoice_id' => $invoice->id,
  'invoice_detail_id' => $invoiceDetail->id,
  'debit' => 0,
  'credit' => $task->invoiceDetail->task_price,  // Revenue earned
  'type' => 'income',
  'description' => 'Revenue from Flight Booking'
]);
```

**Account Names**:
- Assets: `Accounts Receivable > Clients`
- Income: `Direct Income > Flight Booking Revenue`, `Hotel Booking Revenue`, etc.

**Auto-creates** booking revenue account if it doesn't exist!

---

### 6.3 Credit Payment COA

**Created via**: `InvoiceController@createCreditPaymentCOA()`

When invoice is paid with credit:

```php
// DEBIT: Client Credit (reduce wallet balance)
JournalEntry::create([
  'account_id' => $clientCreditAccount->id,
  'debit' => $totalCreditApplied,
  'credit' => 0,
  'description' => 'Credit payment for Invoice #INV-XXX'
]);

// CREDIT: Accounts Receivable (payment received)
JournalEntry::create([
  'account_id' => $receivableAccount->id,
  'debit' => 0,
  'credit' => $totalCreditApplied,
  'description' => 'Payment received via credit for Invoice #INV-XXX'
]);
```

---

### 6.4 Complete Accounting Example

**Scenario**: Invoice for 2 tasks (200 KWD + 150 KWD), paid with credit

```
┌─── Journal Entries Created ─────────────────────────────┐
│                                                          │
│ Transaction #1: Invoice #INV-2024-0001 Generated       │
│                                                          │
│ Entry #1 (Task 1 - Flight):                            │
│   DEBIT   Accounts Receivable > Clients      200 KWD   │
│   CREDIT  Direct Income > Flight Booking     200 KWD   │
│                                                          │
│ Entry #2 (Task 2 - Hotel):                             │
│   DEBIT   Accounts Receivable > Clients      150 KWD   │
│   CREDIT  Direct Income > Hotel Booking      150 KWD   │
│                                                          │
│ Entry #3 (Credit Payment):                             │
│   DEBIT   Client Credit                      350 KWD   │
│   CREDIT  Accounts Receivable > Clients      350 KWD   │
│                                                          │
│ Net Effect:                                             │
│   Client Credit:           -350 KWD (reduced)          │
│   Accounts Receivable:       0 KWD (200+150-350)       │
│   Revenue:                 +350 KWD (200+150)          │
└──────────────────────────────────────────────────────────┘
```

---

## 7. Key Business Rules

### 7.1 Invoice Rules

| Rule | Explanation |
|------|-------------|
| ✅ Tasks must exist first | Cannot create invoice without existing tasks |
| ✅ One task = one invoice | Task can only be invoiced once (hasOne InvoiceDetail) |
| ✅ One payer per invoice | Invoice has ONE client_id (the payer) |
| ✅ Tasks can have different clients | Traveler can differ from payer (parent/child scenario) |
| ✅ One agent per invoice | Invoice handled by ONE agent |
| ✅ Multiple suppliers allowed | Invoice can contain tasks from different suppliers |
| ✅ Invoice always starts unpaid | store() creates invoice with status='unpaid' |
| ✅ Accounting happens on payment | No journal entries until savePartial() is called |

---

### 7.2 Payment Rules

| Rule | Explanation |
|------|-------------|
| ✅ Payment = Wallet topup | Payment is NOT invoice payment, it's client credit |
| ✅ One Payment → Many Invoices | A topup can be used across multiple invoices |
| ✅ Must select which topup to use | When paying with credit, must specify payment_id |
| ✅ Credit tracks balance | SUM of all Credit records = client balance |
| ✅ PaymentApplication links them | Links Payment (topup) to Invoice (usage) |

---

### 7.3 Accounting Rules

| Rule | Explanation |
|------|-------------|
| ✅ Double-entry bookkeeping | Every entry has equal debit and credit |
| ✅ Accounting on payment only | store() creates NO entries, savePartial() creates ALL |
| ✅ One Transaction per invoice | Groups all journal entries for one invoice |
| ✅ One JournalEntry per task | Each task gets its own debit/credit pair |
| ✅ Auto-create revenue accounts | If "Flight Booking Revenue" doesn't exist, created automatically |

---

## 8. API Endpoints

### 8.1 Invoice Endpoints

```php
POST /invoice/store
Description: Create invoice from existing tasks (Step 1)
Input: {clientId, agentId, tasks[], subTotal, invoiceNumber, currency, invdate, duedate}
Output: {success, message, invoiceId}
Redirects to: /invoice/edit/{companyId}/{invoiceNumber}

POST /invoice/savePartial
Description: Add payment to invoice (Step 2)
Input: {invoiceId, invoiceNumber, amount, type, gateway, credit, payment_allocations[], companyId}
Output: {success, message, invoiceId}
Creates: InvoicePartial, Transaction, JournalEntry, Credit (if applicable)
```

---

### 8.2 Payment Endpoints

```php
POST /payment/store-link
Description: Create payment link (topup)
Input: {client_id, amount, payment_gateway, payment_method, notes, items[]}
Output: {status, message, data: Payment}
Creates: Payment (status: pending)

Webhook: /payment/callback/{gateway}
Description: Handle payment gateway webhook
Updates: Payment status → completed
Creates: Credit (type: TOPUP, amount: positive)
```

---

## 9. Database Schema Summary

### 9.1 Core Tables

```sql
-- Tasks (Bookings)
tasks
├── id
├── type (enum: flight, hotel, visa, ...)
├── status (enum: issued, reissued, refund, void)
├── client_id (traveler)
├── agent_id
├── supplier_id
├── company_id
├── reference
├── total (supplier price)
├── invoice_price (client price)
└── timestamps

-- Invoices
invoices
├── id
├── invoice_number (unique per company)
├── client_id (payer)
├── agent_id
├── currency
├── sub_amount
├── invoice_charge
├── amount (sub_amount + invoice_charge)
├── status (enum: unpaid, partial, paid)
├── invoice_date
├── due_date
├── paid_date
└── timestamps

-- Invoice Details (Bridge)
invoice_details
├── id
├── invoice_id → invoices
├── task_id → tasks
├── invoice_number
├── task_description
├── task_price
├── supplier_price
├── markup_price
├── profit
├── paid (boolean)
└── timestamps

-- Invoice Partials (Payment Installments)
invoice_partials
├── id
├── invoice_id → invoices
├── invoice_number
├── client_id
├── amount
├── service_charge
├── gateway_fee
├── status (enum: unpaid, paid)
├── type (enum: full, partial, split, credit)
├── payment_gateway
├── payment_method
└── timestamps

-- Payments (Topups)
payments
├── id
├── voucher_number
├── client_id
├── agent_id
├── amount
├── currency
├── payment_gateway
├── payment_method_id
├── status (enum: pending, initiate, completed)
├── payment_date
└── timestamps

-- Credits (Ledger)
credits
├── id
├── client_id
├── type (enum: Topup, Invoice, Refund, Invoice Refund)
├── amount (positive or negative)
├── payment_id → payments (if from topup)
├── invoice_partial_id → invoice_partials (if used for invoice)
├── refund_id
└── timestamps

-- Payment Applications (Links)
payment_applications
├── id
├── payment_id → payments
├── invoice_id → invoices
├── invoice_partial_id → invoice_partials
├── amount_applied
└── timestamps

-- Transactions (Groups journal entries)
transactions
├── id
├── company_id
├── branch_id
├── invoice_id
├── transaction_type
├── amount
├── description
└── timestamps

-- Journal Entries (Accounting)
journal_entries
├── id
├── transaction_id → transactions
├── account_id → accounts
├── task_id → tasks
├── invoice_id → invoices
├── invoice_detail_id → invoice_details
├── debit
├── credit
├── balance
├── type (enum: receivable, income, ...)
└── timestamps

-- Invoice Sequence (Auto-increment per company)
invoice_sequences
├── id
├── company_id
├── current_sequence (integer)
└── timestamps
```

---

### 9.2 Key Constraints

```sql
-- Task can only be invoiced once
UNIQUE constraint via relationship: Task hasOne InvoiceDetail

-- Invoice number unique per company
UNIQUE (company_id, invoice_number) on invoices

-- Multi-tenant isolation
All tables have company_id for data isolation
```

---

## 10. Summary Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    COMPLETE SYSTEM FLOW                      │
└─────────────────────────────────────────────────────────────┘

1. TASK CREATION (Booking exists)
   └─► Task::create() → Status: issued

2. PAYMENT TOPUP (Client adds wallet credit)
   ├─► Payment::create() → Status: pending
   ├─► Client pays → Webhook
   ├─► Payment->update() → Status: completed
   └─► Credit::create() → Type: TOPUP, Amount: +500

3. INVOICE CREATION (Bill client)
   ├─► InvoiceController@store()
   │   ├── Invoice::create() → Status: unpaid
   │   ├── InvoiceDetail::create() (for each task)
   │   └── InvoiceSequence->increment()
   │
   └─► Redirect to invoice/edit

4. ADD PAYMENT TO INVOICE (Collect payment)
   ├─► InvoiceController@savePartial()
   │   ├── InvoicePartial::create()
   │   ├── PaymentApplicationService (if credit)
   │   │   ├── PaymentApplication::create()
   │   │   └── Credit::create() → Type: INVOICE, Amount: -200
   │   ├── Transaction::create()
   │   ├── JournalEntry::create() (for each task)
   │   │   ├── DEBIT: Accounts Receivable
   │   │   └── CREDIT: Booking Revenue
   │   ├── JournalEntry::create() (if credit payment)
   │   │   ├── DEBIT: Client Credit
   │   │   └── CREDIT: Accounts Receivable
   │   └── Invoice->update() → Status: paid
   │
   └─► Invoice fully paid, accounting complete

RESULT:
✓ Task invoiced
✓ Client wallet balance updated
✓ Invoice paid
✓ Accounting entries created
✓ Revenue recognized
```

---

## 📌 Next Steps

Now that we have complete understanding of the system, we can proceed with:

1. **Design the bulk Excel invoice upload feature**
2. **Define Excel template structure**
3. **Create validation logic**
4. **Implement preview mechanism**
5. **Build the upload and processing flow**

**Ready to proceed with the bulk invoice upload implementation plan?**
