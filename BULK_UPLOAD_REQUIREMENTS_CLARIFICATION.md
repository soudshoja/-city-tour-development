# Bulk Invoice Upload - Requirements Clarification

**Date:** 2026-02-14
**Status:** NEEDS REBUILD - Wrong understanding of requirements

---

## What Was Built (v1.0 - INCORRECT)

### Assumption (WRONG):
- System has existing tasks already created
- Agent selects tasks and groups them into invoices
- Excel upload just groups existing tasks by client

### What Was Implemented:
1. Excel template with task selection
2. Validation of existing tasks
3. Grouping tasks by (client + invoice_date)
4. Preview of invoice groups
5. Approve → Create invoices from existing tasks
6. PDF generation and email delivery

**Files Created:**
- 4 phases, 10 plans executed
- Migrations, controllers, jobs, views, routes
- All documented in `.planning/milestones/v1.0-ROADMAP.md`

---

## Actual Requirements (CORRECT)

### What Agent Actually Needs:

**Single Operation:** Upload Excel → Create Tasks + Invoices + Handle Payments

### Excel Template Columns:
1. **Date** - Service/task date
2. **Client Mobile** - To find/match existing client
3. **Client Name** - For reference/verification
4. **Service Type** - Task type (flight, hotel, visa, insurance, tour, cruise, car, rail, esim, event, lounge, ferry)
5. **Reference** - Source/supplier where service was issued from
6. **Net Price** - Cost price (what we pay)
7. **Selling Price** - Sale amount (what client pays)
8. **Payment Method** - How payment collected (Payment link, cash, bank transfer, etc.)

### System Behavior:

For each Excel row:
1. **Match Client** by mobile number (required - must exist)
2. **Create Task** with service details
3. **Create Invoice** for that task immediately
4. **If Payment Collected:**
   - Mark invoice as PAID
   - Mark invoice as CLOSED
   - Update invoice status based on payment method
5. **If Payment NOT Collected:**
   - Invoice stays UNPAID
   - Invoice stays OPEN

### Key Differences:

| Current (Wrong) | Required (Correct) |
|----------------|-------------------|
| Upload lists existing tasks | Upload creates NEW tasks |
| Groups tasks into invoices | Creates task + invoice together |
| No payment handling | Handles payment status automatically |
| Manual invoice creation | Automatic invoice creation |
| Agent selects tasks | Agent provides task details |

---

## What Needs to Change

### 1. Excel Template
**Remove:**
- Task selection/listing
- Task ID column
- Existing task lookup

**Add:**
- Date column
- Client mobile (required)
- Client name (reference)
- Service type dropdown
- Reference/source field
- Net price (cost)
- Selling price (revenue)
- Payment method (Payment link, cash, etc.)
- Optional: Notes/remarks

**Sheet Structure:**
- **Sheet 1 ("Tasks"):** Columns for new task data
- **Sheet 2 ("Client Reference"):** Existing clients for lookup (Name, Mobile, Email)
- **Sheet 3 ("Service Types"):** Valid service types list
- **Sheet 4 ("Payment Methods"):** Valid payment methods

### 2. Upload Processing Logic

**Change from:**
```php
// Current: Validate existing task IDs
$task = Task::find($row['task_id']);
if (!$task) {
    $errors[] = "Task not found";
}
```

**Change to:**
```php
// New: Create task from row data
$task = Task::create([
    'company_id' => $companyId,
    'client_id' => $client->id,
    'task_type' => $row['service_type'],
    'supplier_id' => $supplier->id,
    'task_status' => 'issued',
    'net_amount' => $row['net_price'],
    'selling_amount' => $row['selling_price'],
    'service_date' => $row['date'],
    // ... other task fields
]);

// Create invoice immediately
$invoice = Invoice::create([
    'company_id' => $companyId,
    'client_id' => $client->id,
    'invoice_date' => $row['date'],
    // ... invoice fields
]);

// Create invoice detail linking task to invoice
InvoiceDetail::create([
    'invoice_id' => $invoice->id,
    'task_id' => $task->id,
    'amount' => $row['selling_price'],
]);

// Handle payment status
if ($row['payment_method'] === 'Payment link' || $row['payment_collected']) {
    $invoice->update([
        'status' => 'paid',
        'paid_amount' => $row['selling_price'],
    ]);

    // Create payment record
    InvoicePartial::create([
        'invoice_id' => $invoice->id,
        'amount' => $row['selling_price'],
        'payment_gateway' => $row['payment_method'],
        'payment_date' => $row['date'],
    ]);
}
```

### 3. Validation Changes

**Current validation:**
- Task ID exists
- Task not already invoiced
- Client matches task

**New validation:**
- Client mobile exists in system (required)
- Service type is valid enum
- Net price > 0
- Selling price > 0
- Date is valid
- Payment method is valid enum
- Reference/source provided

### 4. Preview Page Changes

**Current preview:**
- Shows grouped tasks
- Shows total per invoice

**New preview:**
- Shows tasks that WILL BE CREATED
- Shows invoices that WILL BE CREATED
- Shows payment status for each
- Highlights which invoices will be marked as PAID
- Shows profit margin (selling - net)

### 5. Background Job Changes

**Current job:**
```php
CreateBulkInvoicesJob
- Groups tasks into invoices
- Creates Invoice records
- Creates InvoiceDetail records
```

**New job:**
```php
ProcessBulkTasksAndInvoicesJob
- Create Task for each row
- Create Invoice for each task
- Create InvoiceDetail linking them
- Create InvoicePartial if payment collected
- Update invoice status based on payment
- Create journal entries for accounting
```

---

## Migration Path

### Option 1: Fresh Start (Recommended)
1. Keep current v1.0 code as reference
2. Create new milestone v1.1 with correct requirements
3. Rewrite from scratch with proper understanding

### Option 2: Modify Current
1. Update Excel template structure
2. Modify validation logic to create tasks instead of selecting
3. Update preview to show "will be created" instead of "will be grouped"
4. Modify background job to create tasks + invoices together
5. Add payment handling logic

---

## Files That Need Changes

### High Priority:
- `app/Imports/BulkInvoiceImport.php` - Change from task validation to task creation
- `app/Http/Controllers/BulkInvoiceController.php` - Update downloadTemplate() for new columns
- `app/Jobs/CreateBulkInvoicesJob.php` - Rename and rewrite to create tasks + invoices
- `resources/views/bulk-invoice/preview.blade.php` - Update to show "will create" instead of existing
- Excel template generation logic

### Medium Priority:
- Validation rules in BulkUploadRow model
- Error reporting logic
- Success page messaging

### Low Priority:
- Documentation updates
- Test cases

---

## Current State (Deployed)

### What's Working:
- ✅ Login fixed (reCAPTCHA removed)
- ✅ Navigation added (bulk upload icon in sidebar)
- ✅ Upload page exists
- ✅ Route structure in place
- ✅ Database tables created

### What Needs Rework:
- ❌ Excel template structure
- ❌ Upload processing logic
- ❌ Validation rules
- ❌ Preview display
- ❌ Background job logic
- ❌ Payment status handling

---

## Next Steps

1. **Clarify Payment Logic:**
   - What payment methods exist?
   - How to determine if payment is collected?
   - What invoice statuses are valid?
   - How does payment method affect invoice status?

2. **Clarify Task Creation:**
   - What task fields are required?
   - How to determine supplier from "reference"?
   - Default values for optional task fields?
   - How to handle task numbering/ID generation?

3. **Clarify Accounting:**
   - Should journal entries be created automatically?
   - How does this affect COA?
   - Profit/loss tracking needed?

4. **Build Correct Solution:**
   - Design new Excel template
   - Write new import logic
   - Update preview workflow
   - Handle payment status
   - Test end-to-end

---

## Questions to Answer Before Rebuild

1. **Client Matching:** What if mobile number not found? Create new client or reject row?
2. **Payment Methods:** Full list of valid payment methods?
3. **Service Types:** Full list of valid service types (matches task_type enum)?
4. **Supplier Reference:** How to map "reference" field to supplier? By name? By code?
5. **Invoice Numbering:** One invoice per task or group by client+date?
6. **Profit Calculation:** Should profit (selling - net) be stored or calculated?
7. **Error Handling:** If task creation succeeds but invoice fails, rollback or keep task?
8. **Duplicate Detection:** Check for duplicate tasks by what criteria?

---

## Contact for Clarification

- Review with: @soudshoja
- Review date: TBD
- Decision: Fresh start (v1.1) or modify current (v1.0.1)?

---

_This document created: 2026-02-14_
_Last updated: 2026-02-14_
_Status: Awaiting requirements clarification_
