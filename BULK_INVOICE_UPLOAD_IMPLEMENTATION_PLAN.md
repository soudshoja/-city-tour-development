# 📋 Bulk Invoice Upload - Implementation Plan

**Project**: Soud Laravel Travel Agency Platform
**Feature**: Bulk Invoice Upload from Excel
**Date**: 2024-02-12
**Status**: Ready for Implementation

---

## Table of Contents
1. [Overview](#1-overview)
2. [Requirements Summary](#2-requirements-summary)
3. [Excel Template Structure](#3-excel-template-structure)
4. [Complete Flow Design](#4-complete-flow-design)
5. [Validation Rules](#5-validation-rules)
6. [Grouping Logic](#6-grouping-logic)
7. [Error Handling](#7-error-handling)
8. [File Structure](#8-file-structure)
9. [Database Schema](#9-database-schema)
10. [Implementation Steps](#10-implementation-steps)
11. [Code Examples](#11-code-examples)
12. [UI Mockups](#12-ui-mockups)
13. [Testing Scenarios](#13-testing-scenarios)

---

## 1. Overview

### Current Problem
Agents must manually create invoices one by one by selecting tasks through the UI. For bulk invoicing (e.g., 50 invoices), this is time-consuming and error-prone.

### Solution
Allow agents to upload an Excel file with task IDs, system automatically:
1. Validates all rows
2. Groups tasks by client and date
3. Shows preview of invoices to be created
4. Creates all invoices in one transaction

### Key Benefits
- ✅ Bulk process 100+ tasks into invoices in minutes
- ✅ Automatic grouping by client and date
- ✅ Validation before commit
- ✅ Preview before creation
- ✅ Audit trail

---

## 2. Requirements Summary

### User Answers to Design Questions:

| Question | Answer | Implementation |
|----------|--------|----------------|
| **Excel Format** | Custom columns decided together | See Section 3 |
| **Invoice Grouping** | One invoice per client per date | Group by [client_id, invoice_date] |
| **Client Selection** | Dropdown from database (cannot type) | Excel data validation |
| **Validation Failures** | Block upload, keep form filled, show errors | No partial commits |
| **Invoice Date** | From Excel column | Required column |
| **Already-Invoiced Tasks** | Block upload with error | Same as other errors |
| **Preview Approval** | Approve all or reject all | Single transaction |

### Critical Business Rules

1. **Tasks must exist first** - Cannot create tasks from Excel
2. **One task = one invoice** - Task can only be invoiced once
3. **Block on any error** - All-or-nothing validation
4. **No partial commits** - Either all invoices created or none
5. **Grouped by client + date** - Automatic invoice grouping
6. **Invoice always unpaid** - Created with status='unpaid'
7. **No accounting entries** - Only `store()` logic, no `savePartial()`

---

## 3. Excel Template Structure

### 3.1 Required Columns

```excel
| task_id | invoice_payer_id | invoice_date | currency | notes |
|---------|------------------|--------------|----------|-------|
| 123     | 100              | 2024-02-12   | KWD      | Trip  |
| 124     | 100              | 2024-02-12   | KWD      |       |
| 125     | 101              | 2024-02-13   | USD      |       |
```

### 3.2 Column Specifications

| Column | Type | Required | Format | Validation | Default |
|--------|------|----------|--------|------------|---------|
| **task_id** | Integer | ✅ Yes | 123 | Must exist in tasks table | - |
| **invoice_payer_id** | Integer | ✅ Yes | 100 | Must exist in clients table | - |
| **invoice_date** | Date | ✅ Yes | YYYY-MM-DD | Valid date | - |
| **currency** | String | ❌ No | KWD | ISO code | KWD |
| **notes** | Text | ❌ No | Any text | Max 500 chars | null |

### 3.3 Template Sheets

**Sheet 1: Instructions**
```
Bulk Invoice Upload - Instructions

1. Fill only the yellow columns (task_id, invoice_payer_id, invoice_date)
2. Select invoice_payer_id from dropdown (Sheet: Clients)
3. Date format: YYYY-MM-DD (e.g., 2024-02-12)
4. Currency: Leave blank for KWD, or enter USD, EUR, etc.
5. Upload completed file through the system

Important:
- Tasks must exist in the system before upload
- Tasks already invoiced will cause an error
- All rows must be valid or upload will be rejected
```

**Sheet 2: Template** (Empty, ready to fill)
```
Headers:
task_id | invoice_payer_id | invoice_date | currency | notes

Row 2-1000: Empty
```

**Sheet 3: Clients** (Reference list)
```
| client_id | client_name        | phone      | company_id |
|-----------|--------------------|------------|------------|
| 100       | Ahmad Ali          | 96512345   | 1          |
| 101       | Sara Mohammed      | 96587654   | 1          |
| 102       | Ali Hassan         | 96523456   | 1          |
```

**Data Validation Rules**:
- Column B (invoice_payer_id): Dropdown = Sheet3!$A$2:$A$1000
- Column C (invoice_date): Date format = YYYY-MM-DD
- Column D (currency): List = KWD,USD,EUR,GBP,SAR,AED

### 3.4 Template Generation

```php
// Generate template on the fly
Route: GET /invoice/bulk-upload/template

Controller: BulkInvoiceController@downloadTemplate()

Logic:
1. Get all clients for current agent's company
2. Create Excel with 3 sheets (Instructions, Template, Clients)
3. Apply data validation rules
4. Return downloadable file: "Invoice_Upload_Template_{date}.xlsx"
```

---

## 4. Complete Flow Design

### 4.1 Upload Page (Step 1)

```
┌───────────────────────────────────────────────────────┐
│ Bulk Invoice Upload                                   │
├───────────────────────────────────────────────────────┤
│                                                        │
│ 📥 Step 1: Download Template                          │
│ ┌────────────────────────────────────────────────┐   │
│ │ [📄 Download Excel Template]                   │   │
│ │                                                 │   │
│ │ Template includes:                              │   │
│ │ • Instructions sheet                            │   │
│ │ • Empty template                                │   │
│ │ • Your company's client list                    │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
│ 📝 Step 2: Fill Template                              │
│ • Enter task IDs (must exist in system)               │
│ • Select payer from dropdown                          │
│ • Set invoice date                                    │
│ • Optional: Set currency and notes                    │
│                                                        │
│ 📤 Step 3: Upload Filled Template                     │
│ ┌────────────────────────────────────────────────┐   │
│ │ [Choose File]  No file chosen                  │   │
│ │                                                 │   │
│ │ [🔄 Upload & Validate]                         │   │
│ └────────────────────────────────────────────────┘   │
│                                                        │
└───────────────────────────────────────────────────────┘

Route: GET /invoice/bulk-upload
View: resources/views/invoice/bulk-upload.blade.php
```

---

### 4.2 Validation Page (Step 2)

```
POST /invoice/bulk-upload/validate

Flow:
1. Upload file → Store in temporary location
2. Parse Excel (Maatwebsite/Laravel-Excel)
3. Validate EVERY row (see Section 5)
4. IF all valid → Redirect to preview
5. IF any invalid → Redirect back with errors

Error Response:
┌───────────────────────────────────────────────────────┐
│ ❌ Validation Failed                                  │
├───────────────────────────────────────────────────────┤
│                                                        │
│ Your upload contains errors. Please fix and re-upload.│
│                                                        │
│ Errors found:                                          │
│                                                        │
│ ❌ Row 3: Task #999 does not exist                    │
│ ❌ Row 5: Task #124 is already invoiced               │
│ ❌ Row 8: Client #888 does not exist                  │
│                                                        │
│ [Go Back and Fix] ← Form stays filled                 │
│                                                        │
└───────────────────────────────────────────────────────┘
```

---

### 4.3 Preview Page (Step 3)

```
GET /invoice/bulk-upload/preview

Data passed:
- Validated rows (session)
- Grouped invoices (calculated)
- Summary statistics

┌───────────────────────────────────────────────────────┐
│ Preview - 3 Invoices will be created                  │
├───────────────────────────────────────────────────────┤
│                                                        │
│ ┌─────────────────────────────────────────────────┐  │
│ │ 📄 Invoice #1                                   │  │
│ │ Payer: Ahmad Ali (#100)                         │  │
│ │ Date: 2024-02-12                                │  │
│ │ Currency: KWD                                   │  │
│ │                                                  │  │
│ │ Tasks (2):                                      │  │
│ │ ✓ #123: Flight (Traveler: Sara Ali) - 200 KWD │  │
│ │ ✓ #124: Hotel (Traveler: Ali Hassan) - 150 KWD│  │
│ │                                                  │  │
│ │ Subtotal: 350.000 KWD                           │  │
│ └─────────────────────────────────────────────────┘  │
│                                                        │
│ ┌─────────────────────────────────────────────────┐  │
│ │ 📄 Invoice #2                                   │  │
│ │ Payer: Sara Mohammed (#101)                     │  │
│ │ Date: 2024-02-12                                │  │
│ │ Currency: KWD                                   │  │
│ │                                                  │  │
│ │ Tasks (1):                                      │  │
│ │ ✓ #125: Visa (Traveler: Sara Mohammed) - 100 KWD │
│ │                                                  │  │
│ │ Subtotal: 100.000 KWD                           │  │
│ └─────────────────────────────────────────────────┘  │
│                                                        │
│ ┌─────────────────────────────────────────────────┐  │
│ │ 📄 Invoice #3                                   │  │
│ │ Payer: Ahmad Ali (#100)                         │  │
│ │ Date: 2024-02-13                                │  │
│ │ Currency: KWD                                   │  │
│ │                                                  │  │
│ │ Tasks (1):                                      │  │
│ │ ✓ #126: Insurance (Traveler: Ahmad Ali) - 50 KWD │
│ │                                                  │  │
│ │ Subtotal: 50.000 KWD                            │  │
│ └─────────────────────────────────────────────────┘  │
│                                                        │
│ ═══════════════════════════════════════════════════  │
│ Summary:                                               │
│ • Total Invoices: 3                                   │
│ • Total Tasks: 4                                      │
│ • Total Amount: 500.000 KWD                           │
│                                                        │
│ [✓ Create All Invoices] [✗ Cancel]                   │
│                                                        │
└───────────────────────────────────────────────────────┘

View: resources/views/invoice/bulk-preview.blade.php
```

---

### 4.4 Create Invoices (Step 4)

```
POST /invoice/bulk-upload/create

Process:
1. Retrieve validated data from session
2. Start DB::transaction()
3. For each grouped invoice:
   a. Generate invoice number
   b. Create Invoice record
   c. Create InvoiceDetail records
   d. Update InvoiceSequence
4. Create BulkInvoiceUpload record (audit trail)
5. Commit transaction
6. Clear session data
7. Redirect to success page

Success Page:
┌───────────────────────────────────────────────────────┐
│ ✅ Success - 3 Invoices Created                       │
├───────────────────────────────────────────────────────┤
│                                                        │
│ Created Invoices:                                      │
│ ✓ INV-2024-00123 - Ahmad Ali - 350.000 KWD           │
│ ✓ INV-2024-00124 - Sara Mohammed - 100.000 KWD       │
│ ✓ INV-2024-00125 - Ahmad Ali - 50.000 KWD            │
│                                                        │
│ [📄 View All Invoices] [📊 Download Summary]          │
│ [📤 Upload Another File]                              │
│                                                        │
└───────────────────────────────────────────────────────┘

View: resources/views/invoice/bulk-success.blade.php
```

---

## 5. Validation Rules

### 5.1 Row-Level Validations

**Execute for EVERY row in Excel:**

```php
For each row (skip header):

1. Required Fields Check
   ✓ task_id is present and not empty
   ✓ invoice_payer_id is present and not empty
   ✓ invoice_date is present and not empty

2. Data Type Check
   ✓ task_id is integer
   ✓ invoice_payer_id is integer
   ✓ invoice_date is valid date (Y-m-d format)
   ✓ currency is string (if provided)

3. Task Validation
   ✓ Task exists in tasks table
   ✓ Task belongs to agent's company (task.company_id = agent.company_id)
   ✓ Task is NOT already invoiced (no InvoiceDetail with task_id)
   ✓ Task status is valid for invoicing (not 'void', 'cancelled')

4. Client Validation
   ✓ Client exists in clients table
   ✓ Client belongs to agent's company
   ✓ Client is active (not disabled)

5. Date Validation
   ✓ Date is not in future (optional rule)
   ✓ Date is within current fiscal year (optional)

6. Currency Validation
   ✓ Currency code is valid (KWD, USD, EUR, etc.)
   ✓ Currency is supported by company

7. Notes Validation
   ✓ Notes length <= 500 characters (if provided)
```

### 5.2 Validation Error Format

```php
[
    'row' => 3,
    'task_id' => 999,
    'field' => 'task_id',
    'error' => 'Task #999 does not exist or does not belong to your company'
]

[
    'row' => 5,
    'task_id' => 124,
    'field' => 'task_id',
    'error' => 'Task #124 is already invoiced (Invoice #INV-2024-00100)'
]
```

### 5.3 Validation Service

```php
class BulkInvoiceValidationService
{
    public function validate(array $rows, int $companyId, int $agentId): array
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Excel row (1-based + header)

            // 1. Required fields
            if (empty($row['task_id'])) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'task_id',
                    'error' => 'Task ID is required'
                ];
                continue; // Skip further validation for this row
            }

            // 2. Task validation
            $task = Task::where('id', $row['task_id'])
                ->where('company_id', $companyId)
                ->first();

            if (!$task) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'task_id',
                    'error' => "Task #{$row['task_id']} does not exist"
                ];
                continue;
            }

            // 3. Already invoiced check
            if ($task->invoiceDetail()->exists()) {
                $invoiceNumber = $task->invoiceDetail->invoice_number;
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'task_id',
                    'error' => "Task #{$row['task_id']} is already invoiced ({$invoiceNumber})"
                ];
                continue;
            }

            // 4. Client validation
            // ... similar pattern

            // 5. Date validation
            // ... similar pattern
        }

        return $errors;
    }
}
```

---

## 6. Grouping Logic

### 6.1 Group By: Client + Date

**Requirement**: One invoice per client per date

```php
Group tasks by: [invoice_payer_id, invoice_date]

Example Input (4 rows):
[
    ['task_id' => 123, 'invoice_payer_id' => 100, 'invoice_date' => '2024-02-12'],
    ['task_id' => 124, 'invoice_payer_id' => 100, 'invoice_date' => '2024-02-12'],
    ['task_id' => 125, 'invoice_payer_id' => 101, 'invoice_date' => '2024-02-12'],
    ['task_id' => 126, 'invoice_payer_id' => 100, 'invoice_date' => '2024-02-13'],
]

Grouped Output (3 invoices):
[
    [
        'client_id' => 100,
        'invoice_date' => '2024-02-12',
        'currency' => 'KWD',
        'tasks' => [123, 124],
        'total' => 350.000  // Sum of task prices
    ],
    [
        'client_id' => 101,
        'invoice_date' => '2024-02-12',
        'currency' => 'KWD',
        'tasks' => [125],
        'total' => 100.000
    ],
    [
        'client_id' => 100,
        'invoice_date' => '2024-02-13',
        'currency' => 'KWD',
        'tasks' => [126],
        'total' => 50.000
    ]
]
```

### 6.2 Grouping Service

```php
class BulkInvoiceGroupingService
{
    public function groupInvoices(array $validatedRows): array
    {
        $grouped = [];

        foreach ($validatedRows as $row) {
            $key = $row['invoice_payer_id'] . '_' . $row['invoice_date'];

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'client_id' => $row['invoice_payer_id'],
                    'invoice_date' => $row['invoice_date'],
                    'currency' => $row['currency'] ?? 'KWD',
                    'notes' => $row['notes'] ?? null,
                    'task_ids' => [],
                    'tasks' => [],
                ];
            }

            $grouped[$key]['task_ids'][] = $row['task_id'];
        }

        // Load all tasks with details
        foreach ($grouped as $key => $group) {
            $tasks = Task::whereIn('id', $group['task_ids'])
                ->with('client', 'supplier')
                ->get();

            $grouped[$key]['tasks'] = $tasks;
            $grouped[$key]['total'] = $tasks->sum('invoice_price');
        }

        return array_values($grouped); // Re-index
    }
}
```

---

## 7. Error Handling

### 7.1 Block on Any Error

**Rule**: If ANY row fails validation, STOP and show all errors. Do NOT create any invoices.

```php
POST /invoice/bulk-upload/validate

if (!empty($errors)) {
    return redirect()->back()
        ->withInput()  // Keep uploaded file in session
        ->withErrors(['upload' => $errors]);
}

// Only proceed if zero errors
return redirect()->route('invoice.bulk-upload.preview')
    ->with('validated_rows', $validatedRows);
```

### 7.2 Error Display

```blade
@if ($errors->has('upload'))
<div class="alert alert-danger">
    <h4>❌ Validation Failed</h4>
    <p>Your upload contains {{ count($errors->get('upload')[0]) }} error(s). Please fix and re-upload.</p>

    <table class="table">
        <thead>
            <tr>
                <th>Row</th>
                <th>Field</th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody>
            @foreach($errors->get('upload')[0] as $error)
            <tr>
                <td>{{ $error['row'] }}</td>
                <td>{{ $error['field'] }}</td>
                <td>{{ $error['error'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <a href="{{ route('invoice.bulk-upload') }}" class="btn btn-primary">Go Back and Fix</a>
</div>
@endif
```

### 7.3 Transaction Rollback

```php
POST /invoice/bulk-upload/create

DB::beginTransaction();

try {
    foreach ($groupedInvoices as $group) {
        // Create invoice
        $invoice = Invoice::create([...]);

        // Create invoice details
        foreach ($group['tasks'] as $task) {
            InvoiceDetail::create([...]);
        }

        // Update sequence
        InvoiceSequence::increment(...);
    }

    // Create upload record
    BulkInvoiceUpload::create([...]);

    DB::commit();

    return redirect()->route('invoice.bulk-upload.success')
        ->with('invoices', $createdInvoices);

} catch (\Exception $e) {
    DB::rollback();

    Log::error('Bulk invoice creation failed: ' . $e->getMessage());

    return redirect()->back()
        ->withErrors(['create' => 'Failed to create invoices. Please try again.']);
}
```

---

## 8. File Structure

### 8.1 New Files to Create

```
app/
├── Http/Controllers/
│   └── BulkInvoiceController.php          ← Main controller
│
├── Imports/
│   └── BulkInvoiceImport.php              ← Excel import class
│
├── Services/
│   ├── BulkInvoiceValidationService.php   ← Validation logic
│   ├── BulkInvoiceGroupingService.php     ← Grouping logic
│   └── BulkInvoiceCreationService.php     ← Invoice creation logic
│
└── Models/
    └── BulkInvoiceUpload.php              ← Audit trail model

resources/views/invoice/
├── bulk-upload.blade.php                   ← Upload page
├── bulk-preview.blade.php                  ← Preview page
└── bulk-success.blade.php                  ← Success page

database/migrations/
└── 2024_02_12_create_bulk_invoice_uploads_table.php

routes/
└── web.php                                 ← Add routes

storage/app/
└── bulk-uploads/                           ← Temporary file storage
```

---

## 9. Database Schema

### 9.1 Migration: bulk_invoice_uploads

```php
Schema::create('bulk_invoice_uploads', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->onDelete('cascade');
    $table->foreignId('agent_id')->constrained()->onDelete('cascade');

    $table->string('file_name');
    $table->string('file_path');

    $table->integer('total_rows')->default(0);
    $table->integer('valid_rows')->default(0);
    $table->integer('invalid_rows')->default(0);
    $table->integer('invoices_created')->default(0);

    $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');

    $table->json('error_report')->nullable();      // Validation errors
    $table->json('created_invoices')->nullable();  // Array of invoice IDs

    $table->timestamps();

    $table->index(['company_id', 'agent_id']);
    $table->index('status');
});
```

### 9.2 Model: BulkInvoiceUpload

```php
class BulkInvoiceUpload extends Model
{
    protected $fillable = [
        'company_id',
        'agent_id',
        'file_name',
        'file_path',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'invoices_created',
        'status',
        'error_report',
        'created_invoices',
    ];

    protected $casts = [
        'error_report' => 'array',
        'created_invoices' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function invoices()
    {
        return Invoice::whereIn('id', $this->created_invoices ?? [])->get();
    }
}
```

---

## 10. Implementation Steps

### Step 1: Setup & Migration

```bash
# Create migration
php artisan make:migration create_bulk_invoice_uploads_table

# Create model
php artisan make:model BulkInvoiceUpload

# Create controller
php artisan make:controller BulkInvoiceController

# Run migration
php artisan migrate
```

### Step 2: Create Services

1. **BulkInvoiceValidationService.php**
   - validate(array $rows, int $companyId): array

2. **BulkInvoiceGroupingService.php**
   - groupInvoices(array $validatedRows): array

3. **BulkInvoiceCreationService.php**
   - createInvoices(array $groupedInvoices, int $agentId): array

### Step 3: Create Import Class

```php
// app/Imports/BulkInvoiceImport.php
use Maatwebsite\Excel\Concerns\ToArray;

class BulkInvoiceImport implements ToArray
{
    public function array(array $rows)
    {
        // Skip header row
        array_shift($rows);

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'task_id' => $row[0] ?? null,
                'invoice_payer_id' => $row[1] ?? null,
                'invoice_date' => $row[2] ?? null,
                'currency' => $row[3] ?? 'KWD',
                'notes' => $row[4] ?? null,
            ];
        }

        return $data;
    }
}
```

### Step 4: Create Controller Methods

```php
class BulkInvoiceController extends Controller
{
    public function index()
    {
        // Show upload form
    }

    public function downloadTemplate()
    {
        // Generate Excel template with clients
    }

    public function validate(Request $request)
    {
        // Validate uploaded file
    }

    public function preview()
    {
        // Show preview of grouped invoices
    }

    public function create()
    {
        // Create all invoices
    }

    public function success()
    {
        // Show success page
    }
}
```

### Step 5: Create Views

1. **bulk-upload.blade.php** - Upload form
2. **bulk-preview.blade.php** - Preview page
3. **bulk-success.blade.php** - Success confirmation

### Step 6: Add Routes

```php
// routes/web.php

Route::middleware(['auth'])->prefix('invoice/bulk-upload')->group(function () {
    Route::get('/', [BulkInvoiceController::class, 'index'])->name('invoice.bulk-upload');
    Route::get('/template', [BulkInvoiceController::class, 'downloadTemplate'])->name('invoice.bulk-upload.template');
    Route::post('/validate', [BulkInvoiceController::class, 'validate'])->name('invoice.bulk-upload.validate');
    Route::get('/preview', [BulkInvoiceController::class, 'preview'])->name('invoice.bulk-upload.preview');
    Route::post('/create', [BulkInvoiceController::class, 'create'])->name('invoice.bulk-upload.create');
    Route::get('/success', [BulkInvoiceController::class, 'success'])->name('invoice.bulk-upload.success');
});
```

### Step 7: Testing

1. Unit tests for validation service
2. Unit tests for grouping service
3. Integration test for full flow
4. Manual testing with sample data

---

## 11. Code Examples

### 11.1 Controller: downloadTemplate()

```php
public function downloadTemplate()
{
    $companyId = Auth::user()->company_id;

    // Get all clients for this company
    $clients = Client::where('company_id', $companyId)
        ->select('id', 'full_name', 'phone')
        ->get();

    // Create Excel
    return Excel::download(
        new BulkInvoiceTemplateExport($clients),
        'Invoice_Upload_Template_' . date('Y-m-d') . '.xlsx'
    );
}
```

### 11.2 Controller: validate()

```php
public function validate(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:xlsx,xls|max:5120', // 5MB max
    ]);

    // Import Excel
    $rows = Excel::toArray(new BulkInvoiceImport, $request->file('file'))[0];

    // Validate rows
    $validationService = new BulkInvoiceValidationService();
    $errors = $validationService->validate($rows, Auth::user()->company_id, Auth::user()->id);

    if (!empty($errors)) {
        return redirect()->back()
            ->withInput()
            ->withErrors(['upload' => $errors]);
    }

    // Store validated data in session
    session(['validated_rows' => $rows]);

    return redirect()->route('invoice.bulk-upload.preview');
}
```

### 11.3 Controller: preview()

```php
public function preview()
{
    $rows = session('validated_rows');

    if (!$rows) {
        return redirect()->route('invoice.bulk-upload')
            ->withErrors(['session' => 'Session expired. Please upload again.']);
    }

    // Group invoices
    $groupingService = new BulkInvoiceGroupingService();
    $groupedInvoices = $groupingService->groupInvoices($rows);

    // Calculate summary
    $summary = [
        'total_invoices' => count($groupedInvoices),
        'total_tasks' => count($rows),
        'total_amount' => collect($groupedInvoices)->sum('total'),
    ];

    return view('invoice.bulk-preview', compact('groupedInvoices', 'summary'));
}
```

### 11.4 Controller: create()

```php
public function create()
{
    $rows = session('validated_rows');

    if (!$rows) {
        return redirect()->route('invoice.bulk-upload');
    }

    // Group invoices
    $groupingService = new BulkInvoiceGroupingService();
    $groupedInvoices = $groupingService->groupInvoices($rows);

    // Create invoices
    $creationService = new BulkInvoiceCreationService();
    $result = $creationService->createInvoices($groupedInvoices, Auth::user()->id);

    // Clear session
    session()->forget('validated_rows');

    return redirect()->route('invoice.bulk-upload.success')
        ->with('result', $result);
}
```

### 11.5 Service: BulkInvoiceCreationService

```php
class BulkInvoiceCreationService
{
    public function createInvoices(array $groupedInvoices, int $agentId): array
    {
        $createdInvoices = [];
        $agent = Agent::find($agentId);
        $companyId = $agent->branch->company_id;

        DB::beginTransaction();

        try {
            foreach ($groupedInvoices as $group) {
                // Get next invoice number
                $invoiceSequence = InvoiceSequence::firstOrCreate(
                    ['company_id' => $companyId],
                    ['current_sequence' => 1]
                );

                $currentSequence = $invoiceSequence->current_sequence;
                $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($currentSequence, 5, '0', STR_PAD_LEFT);

                // Create Invoice
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'agent_id' => $agentId,
                    'client_id' => $group['client_id'],
                    'sub_amount' => $group['total'],
                    'amount' => $group['total'],
                    'currency' => $group['currency'],
                    'status' => 'unpaid',
                    'invoice_date' => $group['invoice_date'],
                ]);

                // Create InvoiceDetails
                foreach ($group['tasks'] as $task) {
                    InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'task_id' => $task->id,
                        'task_description' => $task->reference,
                        'task_remark' => null,
                        'client_notes' => $group['notes'] ?? null,
                        'task_price' => $task->invoice_price,
                        'supplier_price' => $task->total,
                        'markup_price' => $task->invoice_price - $task->total,
                        'profit' => $task->invoice_price - $task->total,
                        'paid' => false,
                    ]);
                }

                // Increment sequence
                $invoiceSequence->increment('current_sequence');

                $createdInvoices[] = $invoice->id;
            }

            // Create audit record
            BulkInvoiceUpload::create([
                'company_id' => $companyId,
                'agent_id' => $agentId,
                'file_name' => 'bulk_upload_' . date('Y-m-d_H-i-s') . '.xlsx',
                'file_path' => null,
                'total_rows' => count($groupedInvoices),
                'valid_rows' => count($groupedInvoices),
                'invalid_rows' => 0,
                'invoices_created' => count($createdInvoices),
                'status' => 'completed',
                'created_invoices' => $createdInvoices,
            ]);

            DB::commit();

            return [
                'success' => true,
                'invoices' => Invoice::whereIn('id', $createdInvoices)->get(),
                'count' => count($createdInvoices),
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Bulk invoice creation failed: ' . $e->getMessage());

            throw $e;
        }
    }
}
```

---

## 12. UI Mockups

### 12.1 Upload Page

```html
<!-- resources/views/invoice/bulk-upload.blade.php -->
<div class="container">
    <h1>Bulk Invoice Upload</h1>

    <div class="card mb-4">
        <div class="card-header">Step 1: Download Template</div>
        <div class="card-body">
            <p>Download the Excel template with your company's client list.</p>
            <a href="{{ route('invoice.bulk-upload.template') }}" class="btn btn-primary">
                📄 Download Template
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Step 2: Fill Template</div>
        <div class="card-body">
            <ul>
                <li>Enter task IDs (must exist in system)</li>
                <li>Select payer from dropdown</li>
                <li>Set invoice date (YYYY-MM-DD)</li>
                <li>Optional: Set currency and notes</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Step 3: Upload Template</div>
        <div class="card-body">
            <form action="{{ route('invoice.bulk-upload.validate') }}" method="POST" enctype="multipart/form-data">
                @csrf

                @if($errors->has('upload'))
                <div class="alert alert-danger">
                    <h4>Validation Failed</h4>
                    <!-- Error table here -->
                </div>
                @endif

                <div class="form-group">
                    <input type="file" name="file" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-success">Upload & Validate</button>
            </form>
        </div>
    </div>
</div>
```

### 12.2 Preview Page

```html
<!-- resources/views/invoice/bulk-preview.blade.php -->
<div class="container">
    <h1>Preview - {{ $summary['total_invoices'] }} Invoices</h1>

    @foreach($groupedInvoices as $index => $group)
    <div class="card mb-3">
        <div class="card-header">
            Invoice #{{ $index + 1 }}
        </div>
        <div class="card-body">
            <p><strong>Payer:</strong> {{ $group['client']->full_name }} (#{{ $group['client_id'] }})</p>
            <p><strong>Date:</strong> {{ $group['invoice_date'] }}</p>
            <p><strong>Currency:</strong> {{ $group['currency'] }}</p>

            <h5>Tasks ({{ count($group['tasks']) }}):</h5>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Task ID</th>
                        <th>Reference</th>
                        <th>Type</th>
                        <th>Traveler</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['tasks'] as $task)
                    <tr>
                        <td>#{{ $task->id }}</td>
                        <td>{{ $task->reference }}</td>
                        <td>{{ $task->type }}</td>
                        <td>{{ $task->client->full_name }}</td>
                        <td>{{ number_format($task->invoice_price, 3) }} {{ $group['currency'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="text-right"><strong>Subtotal: {{ number_format($group['total'], 3) }} {{ $group['currency'] }}</strong></p>
        </div>
    </div>
    @endforeach

    <div class="card">
        <div class="card-body">
            <h5>Summary</h5>
            <ul>
                <li>Total Invoices: {{ $summary['total_invoices'] }}</li>
                <li>Total Tasks: {{ $summary['total_tasks'] }}</li>
                <li>Total Amount: {{ number_format($summary['total_amount'], 3) }} KWD</li>
            </ul>

            <form action="{{ route('invoice.bulk-upload.create') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success">✓ Create All Invoices</button>
                <a href="{{ route('invoice.bulk-upload') }}" class="btn btn-secondary">✗ Cancel</a>
            </form>
        </div>
    </div>
</div>
```

---

## 13. Testing Scenarios

### 13.1 Valid Upload

**Input:**
```excel
| task_id | invoice_payer_id | invoice_date | currency | notes |
|---------|------------------|--------------|----------|-------|
| 100     | 50               | 2024-02-12   | KWD      |       |
| 101     | 50               | 2024-02-12   | KWD      |       |
| 102     | 51               | 2024-02-13   | USD      |       |
```

**Expected:**
- ✅ Validation passes
- ✅ Preview shows 2 invoices (1 for client 50, 1 for client 51)
- ✅ Creates 2 invoices successfully
- ✅ All 3 tasks have InvoiceDetail records

---

### 13.2 Invalid Task ID

**Input:**
```excel
| task_id | invoice_payer_id | invoice_date | currency | notes |
|---------|------------------|--------------|----------|-------|
| 999     | 50               | 2024-02-12   | KWD      |       |
```

**Expected:**
- ❌ Validation fails
- ❌ Error: "Row 2: Task #999 does not exist"
- ❌ No invoices created

---

### 13.3 Already Invoiced Task

**Input:**
```excel
| task_id | invoice_payer_id | invoice_date | currency | notes |
|---------|------------------|--------------|----------|-------|
| 100     | 50               | 2024-02-12   | KWD      |       |
```

**Precondition**: Task #100 already has InvoiceDetail

**Expected:**
- ❌ Validation fails
- ❌ Error: "Row 2: Task #100 is already invoiced (INV-2024-00050)"
- ❌ No invoices created

---

### 13.4 Multiple Errors

**Input:**
```excel
| task_id | invoice_payer_id | invoice_date | currency | notes |
|---------|------------------|--------------|----------|-------|
|         | 50               | 2024-02-12   | KWD      |       |
| 999     | 50               | 2024-02-12   | KWD      |       |
| 100     | 888              | invalid      | KWD      |       |
```

**Expected:**
- ❌ Validation fails with 4 errors:
  - Row 2: task_id is required
  - Row 3: Task #999 does not exist
  - Row 4: Client #888 does not exist
  - Row 4: invoice_date is invalid
- ❌ No invoices created

---

## 14. Additional Features (Future)

### 14.1 Optional Enhancements

1. **Email Notification**
   - Send summary email to agent after successful upload
   - Include PDF report of created invoices

2. **PDF Generation**
   - Auto-generate PDF for each created invoice
   - Batch download option

3. **Upload History**
   - View past uploads
   - Re-download upload reports
   - Filter by date/agent

4. **Partial Success Mode**
   - Create invoices for valid rows
   - Queue invalid rows for manual review

5. **Excel Validation**
   - Client-side validation before upload
   - Excel macro for real-time validation

---

## 15. Summary Checklist

### Implementation Checklist

- [ ] Create migration: `bulk_invoice_uploads`
- [ ] Create model: `BulkInvoiceUpload`
- [ ] Create controller: `BulkInvoiceController`
- [ ] Create services:
  - [ ] BulkInvoiceValidationService
  - [ ] BulkInvoiceGroupingService
  - [ ] BulkInvoiceCreationService
- [ ] Create import: `BulkInvoiceImport`
- [ ] Create template export: `BulkInvoiceTemplateExport`
- [ ] Create views:
  - [ ] bulk-upload.blade.php
  - [ ] bulk-preview.blade.php
  - [ ] bulk-success.blade.php
- [ ] Add routes
- [ ] Write tests
- [ ] Deploy

### Testing Checklist

- [ ] Test valid upload (multiple invoices)
- [ ] Test validation errors (all types)
- [ ] Test grouping logic
- [ ] Test transaction rollback
- [ ] Test with large file (1000+ rows)
- [ ] Test concurrent uploads
- [ ] Test session expiry
- [ ] Manual UAT with real data

---

## 16. Notes for Implementation

### Dependencies Required

```json
{
    "require": {
        "maatwebsite/excel": "^3.1"
    }
}
```

### Configuration

```bash
# Publish config
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"

# Set memory limit in php.ini (for large files)
memory_limit = 512M
upload_max_filesize = 10M
post_max_size = 10M
```

### Environment Variables

```env
EXCEL_TEMP_PATH=storage/app/bulk-uploads
EXCEL_MAX_ROWS=10000
```

---

**END OF IMPLEMENTATION PLAN**

**Ready for development!** 🚀
