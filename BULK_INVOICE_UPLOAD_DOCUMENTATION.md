# Bulk Invoice Upload - Full Development Documentation

**Version:** v1.0
**Date:** February 13, 2026
**Project:** Soud Laravel - Travel Agency Management Platform

---

## Table of Contents

1. [Feature Overview](#feature-overview)
2. [Architecture & Design](#architecture--design)
3. [Database Schema](#database-schema)
4. [File Structure](#file-structure)
5. [User Flow](#user-flow)
6. [API Endpoints](#api-endpoints)
7. [Code Documentation](#code-documentation)
8. [Configuration](#configuration)
9. [Testing Guide](#testing-guide)
10. [Deployment Guide](#deployment-guide)
11. [Troubleshooting](#troubleshooting)

---

## Feature Overview

### Purpose
Enable travel agency agents to create multiple invoices efficiently from Excel uploads with validation, preview, and automated workflows.

### Key Features
- **Excel Template Download**: Pre-filled with company's client list
- **Comprehensive Validation**: Task existence, client matching, supplier validation, data type checking
- **Preview Workflow**: Grouped invoice cards by client and date with flagged rows
- **Approval/Rejection**: Alpine.js modals with CSRF protection
- **Background Processing**: Atomic invoice creation in queue jobs
- **PDF Generation**: In-memory PDF creation using existing invoice template
- **Email Delivery**: Automated emails to company accountant and uploading agent
- **Error Reporting**: Downloadable Excel reports with color-coded errors
- **Audit Trail**: Complete upload history with stored files

### Business Value
- **Time Savings**: Create 50+ invoices in 2 minutes vs 30+ minutes manually
- **Error Prevention**: Validation catches mistakes before invoice creation
- **Data Quality**: Client matching prevents duplicates, flags unknowns
- **Audit Compliance**: Full history of uploads, validations, and created invoices
- **Professional Delivery**: Automated PDF emails to stakeholders

---

## Architecture & Design

### Design Patterns

**1. Multi-Tenant Architecture**
- All data scoped by `company_id`
- Agent can only access their company's data
- Prevents data leakage between companies

**2. Command Pattern**
- Queue jobs for background processing
- Prevents UI blocking during long operations
- Enables retry logic and failure handling

**3. Repository Pattern**
- Service layer (`BulkUploadValidationService`) for business logic
- Controllers remain thin, focused on HTTP concerns
- Reusable validation across contexts

**4. Event-Driven Architecture**
- Job dispatch with `afterCommit()` prevents race conditions
- Separate queues ('invoices', 'emails') for independent scaling
- Non-blocking email delivery

### Workflow Phases

```
Phase 1: Data Foundation & Validation
├── Excel template generation
├── File upload and parsing
├── Header validation
├── Row-level validation
└── Error reporting

Phase 2: UI & Preview Workflow
├── Invoice grouping by client + date
├── Preview display with flagged rows
├── Approve/reject modals
└── Success page scaffold

Phase 3: Background Invoice Creation
├── Queue job dispatch
├── Atomic transaction (all-or-nothing)
├── Invoice number generation (race-free)
├── Duplicate task prevention
└── Success page with real invoices

Phase 4: PDF Generation & Email Delivery
├── In-memory PDF generation
├── Email composition
├── Queue job for delivery
└── PDF download links
```

### Key Design Decisions

| Decision | Rationale | Impact |
|----------|-----------|--------|
| One invoice per client + date | Matches existing manual workflow | Reduces invoice clutter |
| Flag unknown clients (not auto-create) | Prevents duplicates from typos | Maintains data quality |
| Full validation before preview | Fail fast with clear errors | Better UX than partial imports |
| Separate email queue job | Prevents PDF generation from holding DB locks | Performance optimization |
| afterCommit() on job dispatch | Ensures status committed before job runs | Prevents race conditions |
| In-memory PDF generation | No temp file cleanup needed | Simpler, cleaner code |

---

## Database Schema

### Tables Created

#### 1. `bulk_uploads`
Primary table tracking each upload session.

```sql
CREATE TABLE bulk_uploads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    agent_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_path VARCHAR(500) NOT NULL,
    status ENUM('validated', 'processing', 'completed', 'failed', 'rejected') NOT NULL DEFAULT 'validated',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
    error_rows INT UNSIGNED NOT NULL DEFAULT 0,
    flagged_rows INT UNSIGNED NOT NULL DEFAULT 0,
    error_summary JSON NULL,
    invoice_ids JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,

    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_company_status (company_id, status),
    INDEX idx_created_at (created_at)
);
```

**Status Flow:**
```
validated → processing → completed
                      ↘ failed
         ↘ rejected
```

#### 2. `bulk_upload_rows`
Individual row data and validation results.

```sql
CREATE TABLE bulk_upload_rows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bulk_upload_id BIGINT UNSIGNED NOT NULL,
    row_number INT UNSIGNED NOT NULL,
    status ENUM('valid', 'error', 'flagged') NOT NULL,
    task_id BIGINT UNSIGNED NULL,
    client_id BIGINT UNSIGNED NULL,
    supplier_id BIGINT UNSIGNED NULL,
    raw_data JSON NOT NULL,
    errors JSON NULL,
    flag_reason VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (bulk_upload_id) REFERENCES bulk_uploads(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,

    INDEX idx_bulk_upload_status (bulk_upload_id, status),
    INDEX idx_task_id (task_id)
);
```

#### 3. Modified: `bulk_uploads.invoice_ids`
Added JSON column to track created invoices.

```sql
ALTER TABLE bulk_uploads
ADD COLUMN invoice_ids JSON NULL AFTER error_summary;
```

**Example:**
```json
[123, 124, 125, 126, 127]
```

### Relationships

```
companies (1) ─────→ (many) bulk_uploads
agents (1) ────────→ (many) bulk_uploads
users (1) ─────────→ (many) bulk_uploads
bulk_uploads (1) ──→ (many) bulk_upload_rows
tasks (1) ─────────→ (many) bulk_upload_rows
clients (1) ───────→ (many) bulk_upload_rows
suppliers (1) ─────→ (many) bulk_upload_rows
bulk_uploads (1) ──→ (many) invoices (via invoice_ids)
```

---

## File Structure

### Controllers
```
app/Http/Controllers/
└── BulkInvoiceController.php          (392 lines)
    ├── downloadTemplate()              Route: bulk-invoices.template
    ├── upload()                        Route: bulk-invoices.upload
    ├── preview()                       Route: bulk-invoices.preview
    ├── approve()                       Route: bulk-invoices.approve
    ├── reject()                        Route: bulk-invoices.reject
    ├── success()                       Route: bulk-invoices.success
    └── downloadErrorReport()           Route: bulk-invoices.error-report
```

### Models
```
app/Models/
├── BulkUpload.php                      (Model with relationships)
│   ├── fillable: company_id, agent_id, user_id, filename, path, status, counts, error_summary, invoice_ids
│   ├── casts: error_summary => array, invoice_ids => array
│   ├── relations: company(), agent(), user(), rows()
│   └── softDeletes
└── BulkUploadRow.php                   (Model with relationships)
    ├── fillable: bulk_upload_id, row_number, status, task_id, client_id, supplier_id, raw_data, errors, flag_reason
    ├── casts: raw_data => array, errors => array
    └── relations: bulkUpload(), task(), client(), supplier()
```

### Services
```
app/Services/
└── BulkUploadValidationService.php    (Complete validation logic)
    ├── validateHeaders()               Check Excel column headers
    ├── validateAll()                   Validate all rows
    ├── validateRow()                   Individual row validation
    ├── matchClient()                   Client matching by phone
    ├── matchTask()                     Task existence check
    └── matchSupplier()                 Supplier lookup
```

### Jobs
```
app/Jobs/
├── CreateBulkInvoicesJob.php          (238 lines - Invoice creation)
│   ├── handle()                        Main execution
│   ├── generateInvoiceNumber()         Race-free numbering
│   ├── checkTaskNotAlreadyInvoiced()   Duplicate prevention
│   └── failed()                        Error handling
└── SendInvoiceEmailsJob.php           (145 lines - Email delivery)
    ├── handle()                        Send to accountant + agent
    └── failed()                        Log non-critical failures
```

### Mail
```
app/Mail/
└── BulkInvoicesMail.php               (130 lines - Mailable)
    ├── build()                         Email composition
    └── attachments()                   In-memory PDF generation
```

### Exports
```
app/Exports/
├── BulkInvoiceTemplateExport.php      (Multi-sheet template)
│   ├── UploadTemplateSheet            Empty template with headers
│   └── ClientListSheet                Pre-filled client data
└── BulkUploadErrorReportExport.php    (Error report with styling)
    └── ErrorRowsSheet                  Red/yellow color coding
```

### Imports
```
app/Imports/
└── BulkInvoiceImport.php              (Empty - used for parsing only)
```

### Views
```
resources/views/
├── bulk-invoice/
│   ├── preview.blade.php              (217 lines - Preview page)
│   └── success.blade.php              (109 lines - Success page)
└── email/
    └── bulk-invoices.blade.php        (109 lines - Email template)
```

### Migrations
```
database/migrations/
├── 2026_02_13_095156_create_bulk_uploads_table.php
├── 2026_02_13_095157_create_bulk_upload_rows_table.php
└── 2026_02_13_134526_add_invoice_ids_to_bulk_uploads_table.php
```

### Tests
```
tests/Feature/
└── BulkUploadValidationTest.php       (TDD validation tests)
```

---

## User Flow

### 1. Download Template

**Route:** `GET /bulk-invoices/template`

**Action:**
1. Agent clicks "Download Template" button
2. System generates Excel with 2 sheets:
   - **Upload Template**: Empty with column headers
   - **Client List**: All clients from agent's company

**Excel Columns:**
```
task_id | client_phone | invoice_date | currency | notes | supplier_name
```

**Controller:**
```php
public function downloadTemplate(Request $request): BinaryFileResponse
{
    $user = Auth::user();
    $companyId = getCompanyId($user);

    return Excel::download(
        new BulkInvoiceTemplateExport($companyId),
        'bulk-invoice-template.xlsx'
    );
}
```

### 2. Fill Template

**Agent fills:**
- `task_id`: Existing task IDs from system
- `client_phone`: Client mobile number (for matching)
- `invoice_date`: When invoice should be dated
- `currency`: KWD, USD, EUR, etc.
- `notes`: Optional invoice notes
- `supplier_name`: Optional supplier name

### 3. Upload File

**Route:** `POST /bulk-invoices/upload`

**Process:**
1. File uploaded via form
2. File stored to `storage/app/bulk-uploads/{company_id}/`
3. Excel parsed to array
4. Headers validated
5. All rows validated
6. BulkUpload + BulkUploadRow records created
7. Redirect to preview

**Validation Rules:**
```php
task_id:        required, integer, exists in tasks table, belongs to company
client_phone:   required, string, finds client in company
invoice_date:   required, date, format Y-m-d
currency:       required, enum (KWD, USD, EUR, GBP, etc.)
notes:          optional, string, max 500 chars
supplier_name:  optional, string, exists in suppliers table
```

**Validation Results:**
- **valid**: All checks passed
- **error**: Missing required field, invalid value, task not found
- **flagged**: Client phone not found (needs manual review)

### 4. Preview

**Route:** `GET /bulk-invoices/{id}/preview`

**Display:**
```
┌─────────────────────────────────────────┐
│ Upload Summary                          │
│ ✓ 45 valid rows, 0 errors, 3 flagged  │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ Invoices to Create (12)                 │
│                                         │
│ ┌───────────────────────────────────┐ │
│ │ Client: John Doe (+965 9999 9999) │ │
│ │ Date: 2026-02-15                  │ │
│ │ Tasks: 4 items                    │ │
│ │ Total: 1,250.500 KWD             │ │
│ └───────────────────────────────────┘ │
│                                         │
│ [... more invoice cards ...]            │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ ⚠ Flagged Rows (3)                     │
│ Row 12: Unknown client +965 6666 6666  │
│ Row 18: Unknown client +965 7777 7777  │
│ Row 34: Unknown client +965 8888 8888  │
└─────────────────────────────────────────┘

[Approve All] [Reject Upload]
```

**Grouping Logic:**
```php
$invoiceGroups = $validRows->groupBy(function ($row) {
    $clientId = $row->client_id;
    $invoiceDate = $row->raw_data['invoice_date'] ?? date('Y-m-d');
    return "{$clientId}_{$invoiceDate}";
});
```

### 5. Approve/Reject

**Approve Route:** `POST /bulk-invoices/{id}/approve`

**Process:**
1. Conditional status update: `validated` → `processing`
2. Dispatch `CreateBulkInvoicesJob` with `afterCommit()`
3. Redirect to success page
4. Job runs in background

**Reject Route:** `POST /bulk-invoices/{id}/reject`

**Process:**
1. Status update: `validated` → `rejected`
2. Redirect to dashboard
3. No invoices created

### 6. Background Invoice Creation

**Job:** `CreateBulkInvoicesJob`

**Process:**
```php
DB::transaction(function () {
    // 1. Get valid rows
    $validRows = BulkUploadRow::where('bulk_upload_id', $this->bulkUploadId)
        ->where('status', 'valid')
        ->with('client', 'task')
        ->get();

    // 2. Group by client + date
    $invoiceGroups = $validRows->groupBy(function ($row) {
        return "{$row->client_id}_{$row->raw_data['invoice_date']}";
    });

    // 3. Create invoice for each group
    foreach ($invoiceGroups as $group) {
        // Generate invoice number (race-free)
        $invoiceNumber = $this->generateInvoiceNumber($companyId);

        // Check tasks not already invoiced
        $this->checkTaskNotAlreadyInvoiced($group);

        // Create invoice
        $invoice = Invoice::create([
            'company_id' => $companyId,
            'agent_id' => $agentId,
            'client_id' => $clientId,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $invoiceDate,
            'currency' => $currency,
            'subtotal' => $subtotal,
            'total' => $total,
            'status' => 'pending',
        ]);

        // Create invoice details (line items)
        foreach ($group as $row) {
            InvoiceDetail::create([
                'invoice_id' => $invoice->id,
                'task_id' => $row->task_id,
                'description' => $row->task->description,
                'quantity' => 1,
                'unit_price' => $row->task->cost,
                'total' => $row->task->cost,
            ]);
        }

        $createdInvoiceIds[] = $invoice->id;
    }

    // 4. Update BulkUpload record
    $bulkUpload->update([
        'status' => 'completed',
        'invoice_ids' => $createdInvoiceIds,
    ]);
});
```

**Race-Free Invoice Numbering:**
```php
protected function generateInvoiceNumber(int $companyId): string
{
    $sequence = InvoiceSequence::where('company_id', $companyId)
        ->lockForUpdate()
        ->first();

    if (!$sequence) {
        $sequence = InvoiceSequence::create([
            'company_id' => $companyId,
            'current_sequence' => 1
        ]);
    }

    $year = now()->year;
    $invoiceNumber = sprintf('INV-%s-%05d', $year, $sequence->current_sequence);

    $sequence->increment('current_sequence');

    return $invoiceNumber; // e.g., INV-2026-00123
}
```

### 7. Success Page

**Route:** `GET /bulk-invoices/{id}/success`

**Three States:**

**State 1: Processing** (job still running)
```
[Spinner] Invoices are being created in the background.
          Refresh this page to check progress.
```

**State 2: Completed** (job succeeded)
```
✓ All invoices have been created successfully.
  Invoice PDFs are being emailed to the company accountant and uploading agent.

Created Invoices (12)
┌──────────────────────────────────────┐
│ INV-2026-00123                       │
│ John Doe                             │
│ 2026-02-15 · KWD 1,250.500          │
│ [View] [Download PDF]                │
└──────────────────────────────────────┘
[... more invoices ...]
```

**State 3: Failed** (job failed)
```
✗ Invoice creation failed: Duplicate task detected (Task #456 already invoiced)
  Please contact support or try uploading again.
```

### 8. PDF Generation & Email

**Job:** `SendInvoiceEmailsJob`

**Process:**
```php
public function handle()
{
    // 1. Load data
    $bulkUpload = BulkUpload::with('agent.branch.company')->findOrFail($this->bulkUploadId);

    // 2. Guard clauses
    if ($bulkUpload->status !== 'completed') {
        Log::warning('BulkUpload not completed, skipping email');
        return;
    }

    // 3. Send to accountant
    if ($company && $company->email) {
        Mail::to($company->email)
            ->queue(new BulkInvoicesMail($this->bulkUploadId));
    }

    // 4. Send to agent
    if ($agent && $agent->email) {
        Mail::to($agent->email)
            ->queue(new BulkInvoicesMail($this->bulkUploadId));
    }
}
```

**Email Content:**
```
Subject: Bulk Invoice Upload - 12 Invoices Created

[Company Logo]

Invoice Delivery
─────────────────

12 invoice(s) have been created from bulk upload: invoices-2026-02-13.xlsx

┌────────────────────────────────────────────────┐
│ Invoice No.  │ Client     │ Date       │ Amount │
├────────────────────────────────────────────────┤
│ INV-2026-123 │ John Doe   │ 2026-02-15 │ 1,250  │
│ INV-2026-124 │ Jane Smith │ 2026-02-15 │ 890    │
│ ... (10 more rows) ...                         │
└────────────────────────────────────────────────┘

Total: 15,234.500 KWD

PDFs are attached to this email.

────────────────────────────────────────────────
This is an automated message from Soud Laravel.
```

**Attachments:**
- `Invoice-INV-2026-00123.pdf`
- `Invoice-INV-2026-00124.pdf`
- ... (one per invoice)

---

## API Endpoints

### 1. Download Template

```http
GET /bulk-invoices/template
```

**Auth:** Required
**Returns:** Excel file download

**Response Headers:**
```
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="bulk-invoice-template.xlsx"
```

---

### 2. Upload File

```http
POST /bulk-invoices/upload
Content-Type: multipart/form-data
```

**Auth:** Required

**Request Body:**
```
file: (binary Excel file)
```

**Success Response (302 Redirect):**
```
Location: /bulk-invoices/123/preview
Flash Message: "Upload validated: 45 valid rows, 0 errors, 3 flagged."
```

**Error Response (422 Validation Error):**
```json
{
    "error": "Invalid Excel headers.",
    "missing_headers": ["task_id", "client_phone"],
    "extra_headers": ["unknown_column"]
}
```

---

### 3. Preview

```http
GET /bulk-invoices/{id}/preview
```

**Auth:** Required
**Multi-Tenant:** Scoped by `company_id`

**Response:** HTML (Blade view)

**Data Passed to View:**
```php
[
    'bulkUpload' => BulkUpload,        // Upload record
    'invoiceGroups' => Collection,      // Grouped by client+date
    'flaggedRows' => Collection,        // Flagged for review
    'clientCount' => int                // Unique clients
]
```

---

### 4. Approve Upload

```http
POST /bulk-invoices/{id}/approve
```

**Auth:** Required
**Multi-Tenant:** Scoped by `company_id`

**Success Response (302 Redirect):**
```
Location: /bulk-invoices/123/success
Flash Message: "Invoices are being created in the background."
```

**Error Response (302 Redirect Back):**
```
Errors: "Upload already processed or no longer in validated status."
```

---

### 5. Reject Upload

```http
POST /bulk-invoices/{id}/reject
```

**Auth:** Required
**Multi-Tenant:** Scoped by `company_id`

**Success Response (302 Redirect):**
```
Location: /dashboard
Flash Message: "Upload rejected and discarded."
```

---

### 6. Success Page

```http
GET /bulk-invoices/{id}/success
```

**Auth:** Required
**Multi-Tenant:** Scoped by `company_id`

**Response:** HTML (Blade view)

**Data Passed to View:**
```php
[
    'bulkUpload' => BulkUpload,        // Upload record with status
    'invoiceCount' => int,              // Total invoices
    'clientCount' => int,               // Unique clients
    'invoices' => Collection            // Created Invoice records (if completed)
]
```

---

### 7. Download Error Report

```http
GET /bulk-invoices/{id}/error-report
```

**Auth:** Required
**Multi-Tenant:** Scoped by `company_id`

**Returns:** Excel file with error rows

**Response Headers:**
```
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="error-report-invoices-2026-02-13-123.xlsx"
```

**Excel Content:**
- All error rows (red background)
- All flagged rows (yellow background)
- Error messages in "Errors" column
- Flag reasons in "Flag Reason" column

---

## Code Documentation

### BulkUploadValidationService

**Purpose:** Centralized validation logic for bulk uploads.

**Key Methods:**

#### validateHeaders()
```php
/**
 * Validate Excel column headers.
 *
 * @param array $headers Column headers from Excel
 * @return array ['valid' => bool, 'missing' => array, 'extra' => array]
 */
public function validateHeaders(array $headers): array
{
    $required = ['task_id', 'client_phone', 'invoice_date', 'currency'];
    $missing = array_diff($required, $headers);
    $extra = array_diff($headers, $this->allowedHeaders);

    return [
        'valid' => empty($missing),
        'missing' => array_values($missing),
        'extra' => array_values($extra)
    ];
}
```

#### validateAll()
```php
/**
 * Validate all rows in the upload.
 *
 * @param array $rows Excel rows (array of arrays)
 * @param int $companyId Company ID for scoping
 * @return array ['total' => int, 'valid' => int, 'errors' => int, 'flagged' => int, 'rows' => array]
 */
public function validateAll(array $rows, int $companyId): array
{
    $results = [];
    $counts = ['total' => 0, 'valid' => 0, 'errors' => 0, 'flagged' => 0];

    foreach ($rows as $index => $row) {
        $result = $this->validateRow($row, $index, $companyId);
        $results[] = $result;
        $counts['total']++;
        $counts[$result['status'] === 'error' ? 'errors' : $result['status']]++;
    }

    return array_merge($counts, ['rows' => $results]);
}
```

#### validateRow()
```php
/**
 * Validate a single row.
 *
 * @param array $row Row data
 * @param int $index Row number (0-indexed)
 * @param int $companyId Company ID
 * @return array ['status' => string, 'errors' => array, 'matched' => array, 'flag_reason' => string]
 */
public function validateRow(array $row, int $index, int $companyId): array
{
    $rowNumber = $index + 1;
    $errors = [];
    $matched = [];

    // Required field validation
    if (empty($row['task_id'])) {
        $errors[] = "Row {$rowNumber}: task_id is required";
    }

    // Task validation
    if (!empty($row['task_id'])) {
        $task = $this->matchTask($row['task_id'], $companyId);
        if (!$task) {
            $errors[] = "Row {$rowNumber}: Task not found or doesn't belong to your company";
        } else {
            $matched['task_id'] = $task->id;
        }
    }

    // Client matching
    $client = $this->matchClient($row['client_phone'], $companyId);
    if ($client) {
        $matched['client_id'] = $client->id;
    }

    // Determine status
    $status = !empty($errors) ? 'error' : (!$client ? 'flagged' : 'valid');
    $flagReason = !$client ? "Unknown client: {$row['client_phone']}" : null;

    return [
        'status' => $status,
        'errors' => $errors,
        'matched' => $matched,
        'flag_reason' => $flagReason
    ];
}
```

### CreateBulkInvoicesJob

**Purpose:** Background job for atomic invoice creation.

**Key Features:**
- Atomic transaction (all-or-nothing)
- Race-free invoice numbering
- Duplicate task prevention
- Comprehensive error handling

**Critical Code:**

```php
public function handle()
{
    try {
        DB::transaction(function () {
            // Lock invoice sequence for this company
            $sequence = InvoiceSequence::where('company_id', $this->companyId)
                ->lockForUpdate()
                ->first();

            // Group valid rows
            $validRows = BulkUploadRow::where('bulk_upload_id', $this->bulkUploadId)
                ->where('status', 'valid')
                ->with('client', 'task.supplier')
                ->get();

            $invoiceGroups = $validRows->groupBy(function ($row) {
                $clientId = $row->client_id;
                $invoiceDate = $row->raw_data['invoice_date'] ?? date('Y-m-d');
                return "{$clientId}_{$invoiceDate}";
            });

            // Create invoices
            $createdInvoiceIds = [];
            foreach ($invoiceGroups as $compositeKey => $rows) {
                // Check for duplicate tasks
                $this->checkTaskNotAlreadyInvoiced($rows);

                // Generate invoice number
                $invoiceNumber = $this->generateInvoiceNumber($this->companyId);

                // Create invoice with details
                $invoice = $this->createInvoiceWithDetails($rows, $invoiceNumber);

                $createdInvoiceIds[] = $invoice->id;
            }

            // Update bulk upload
            BulkUpload::where('id', $this->bulkUploadId)->update([
                'status' => 'completed',
                'invoice_ids' => $createdInvoiceIds
            ]);
        });

        // Dispatch email job AFTER transaction commits
        SendInvoiceEmailsJob::dispatch($this->bulkUploadId)
            ->onQueue('emails')
            ->afterCommit();

    } catch (\Exception $e) {
        Log::error('CreateBulkInvoicesJob failed', [
            'bulk_upload_id' => $this->bulkUploadId,
            'error' => $e->getMessage()
        ]);
        throw $e; // Re-throw to trigger failed()
    }
}

public function failed(\Throwable $exception)
{
    BulkUpload::where('id', $this->bulkUploadId)->update([
        'status' => 'failed',
        'error_summary' => [
            'job_failure' => $exception->getMessage()
        ]
    ]);
}
```

### BulkInvoicesMail

**Purpose:** Mailable for sending invoice PDFs via email.

**Key Features:**
- In-memory PDF generation
- Laravel 11 Attachment::fromData() pattern
- No ShouldQueue (job handles queueing)
- Serializes only ID (not Eloquent model)

**Critical Code:**

```php
public function build()
{
    $bulkUpload = BulkUpload::with('agent.branch.company')
        ->findOrFail($this->bulkUploadId);

    $invoices = Invoice::whereIn('id', $bulkUpload->invoice_ids)
        ->with('client', 'agent.branch.company', 'invoiceDetails.task.supplier')
        ->get();

    return $this->subject("Bulk Invoice Upload - {$invoices->count()} Invoices Created")
        ->view('email.bulk-invoices')
        ->with([
            'invoices' => $invoices,
            'bulkUpload' => $bulkUpload,
            'company' => $bulkUpload->agent->branch->company
        ]);
}

public function attachments(): array
{
    $bulkUpload = BulkUpload::findOrFail($this->bulkUploadId);
    $invoices = Invoice::whereIn('id', $bulkUpload->invoice_ids)
        ->with('client', 'agent.branch.company', 'invoiceDetails.task.supplier')
        ->get();

    $attachments = [];

    foreach ($invoices as $invoice) {
        $pdf = Pdf::loadView('invoice.pdf.invoice', [
            'invoice' => $invoice,
            'company' => $invoice->agent->branch->company,
            'invoiceDetails' => $invoice->invoiceDetails,
            'isPdf' => true
        ])->setPaper('a4', 'portrait');

        $attachments[] = Attachment::fromData(
            fn () => $pdf->output(),
            "Invoice-{$invoice->invoice_number}.pdf"
        )->withMime('application/pdf');
    }

    return $attachments;
}
```

---

## Configuration

### Environment Variables

**Required:**
```env
# Queue Configuration
QUEUE_CONNECTION=database          # Use database queue driver

# Mail Configuration
MAIL_MAILER=smtp                   # Or: ses, postmark, resend
MAIL_FROM_ADDRESS=noreply@citycommerce.group
MAIL_FROM_NAME="${APP_NAME}"

# Storage
FILESYSTEM_DISK=local              # Where to store uploaded files
```

**Optional:**
```env
# Queue Worker Settings
QUEUE_INVOICES_RETRY_AFTER=300     # Retry after 5 minutes
QUEUE_EMAILS_RETRY_AFTER=180       # Retry after 3 minutes
```

### Queue Configuration

**config/queue.php:**
```php
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
    ],
],
```

### Storage Configuration

**config/filesystems.php:**
```php
'disks' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
        'throw' => false,
    ],
],
```

**Uploaded files stored at:**
```
storage/app/bulk-uploads/{company_id}/{timestamp}_{filename}.xlsx
```

### Mail Configuration

**Using Resend (Recommended):**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=587
MAIL_USERNAME=resend
MAIL_PASSWORD=re_xxx  # Your Resend API key
MAIL_ENCRYPTION=tls
```

**Using AWS SES:**
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=xxx
AWS_SECRET_ACCESS_KEY=xxx
AWS_DEFAULT_REGION=us-east-1
```

---

## Testing Guide

### Manual Testing

#### Test 1: Template Download
```
1. Login as agent
2. Navigate to bulk invoice upload page
3. Click "Download Template"
4. Verify Excel has 2 sheets:
   - Upload Template (empty with headers)
   - Client List (company clients)
5. Check columns: task_id, client_phone, invoice_date, currency, notes, supplier_name
```

#### Test 2: Valid Upload
```
1. Fill template with 5 valid rows
2. Upload file
3. Verify redirect to preview
4. Check invoice grouping by client
5. Verify task counts and totals
6. Click "Approve All"
7. Wait on success page
8. Refresh until status = completed
9. Verify invoices displayed
10. Click "Download PDF" - verify PDF opens
11. Check email inbox for PDFs
```

#### Test 3: Validation Errors
```
1. Fill template with errors:
   - Missing task_id (row 2)
   - Invalid task_id (row 3)
   - Already invoiced task (row 4)
2. Upload file
3. Verify redirect to preview with errors
4. Check error count displayed
5. Click "Download Error Report"
6. Open Excel - verify red rows with errors
7. Reject upload
8. Verify redirect to dashboard
```

#### Test 4: Flagged Clients
```
1. Fill template with unknown client phones
2. Upload file
3. Verify flagged rows section
4. Check yellow highlighting
5. Approve anyway
6. Verify invoices created only for valid rows
7. Flagged rows ignored
```

#### Test 5: Concurrent Uploads
```
1. Open 2 browser windows
2. Both agents from same company
3. Both upload files simultaneously
4. Both approve at same time
5. Verify invoice numbers don't overlap
6. Check all invoices created correctly
```

### Automated Testing

**Run tests:**
```bash
php artisan test --filter BulkUploadValidationTest
```

**Test Coverage:**
- ✅ Header validation
- ✅ Required field validation
- ✅ Task existence validation
- ✅ Client matching
- ✅ Supplier lookup
- ✅ Unknown client flagging

**Example Test:**
```php
public function test_validates_required_task_id()
{
    $row = ['client_phone' => '96512345678', 'invoice_date' => '2026-02-15'];
    $result = $this->validationService->validateRow($row, 0, $this->companyId);

    $this->assertEquals('error', $result['status']);
    $this->assertStringContainsString('task_id is required', $result['errors'][0]);
}
```

### Performance Testing

**Test Scenarios:**

| Rows | Expected Time | Notes |
|------|---------------|-------|
| 10   | < 5 seconds   | Small upload |
| 50   | < 15 seconds  | Medium upload |
| 100  | < 30 seconds  | Large upload |
| 500  | < 2 minutes   | Very large (consider splitting) |

**Test Commands:**
```bash
# Time the upload process
time php artisan tinker --execute="
\$file = new Illuminate\Http\UploadedFile('/path/to/test-50-rows.xlsx', 'test.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
app(App\Http\Controllers\BulkInvoiceController::class)->upload(request()->merge(['file' => \$file]));
"

# Monitor queue processing
php artisan queue:work --queue=invoices --once --verbose

# Check database performance
php artisan tinker --execute="
DB::enableQueryLog();
\$result = app(App\Services\BulkUploadValidationService::class)->validateAll(\$rows, 1);
dump(DB::getQueryLog());
"
```

---

## Deployment Guide

### Prerequisites

**Server Requirements:**
- PHP 8.2+
- MySQL 8.0+ or PostgreSQL 13+
- Composer
- Node.js 18+ & NPM
- Queue worker configured

**Laravel Requirements:**
- Laravel 11.x
- Maatwebsite/Laravel-Excel 3.1+
- barryvdh/laravel-dompdf 3.0+

### Deployment Steps

#### 1. Pull Code
```bash
cd /home/citycomm/development.citycommerce.group
git fetch origin main
git pull origin main
```

#### 2. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
npm install --production
npm run build
```

#### 3. Run Migrations
```bash
php artisan migrate --force
```

**Migrations to run:**
- `2026_02_13_095156_create_bulk_uploads_table.php`
- `2026_02_13_095157_create_bulk_upload_rows_table.php`
- `2026_02_13_134526_add_invoice_ids_to_bulk_uploads_table.php`

#### 4. Clear and Rebuild Caches
```bash
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

#### 5. Verify Queue Worker
```bash
# Check if queue worker is running
ps aux | grep "queue:work"

# If not running, start it:
php artisan queue:work --queue=invoices,emails --tries=3 --timeout=300 &

# Or configure with supervisor:
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

**Supervisor Config:**
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/citycomm/development.citycommerce.group/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --queue=invoices,emails
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=citycomm
numprocs=2
redirect_stderr=true
stdout_logfile=/home/citycomm/development.citycommerce.group/storage/logs/worker.log
stopwaitsecs=3600
```

#### 6. Set Permissions
```bash
chmod -R 775 storage bootstrap/cache
chown -R citycomm:citycomm storage bootstrap/cache
```

#### 7. Test Deployment
```bash
# Check routes exist
php artisan route:list | grep bulk-invoice

# Check migrations ran
php artisan migrate:status | grep bulk

# Test template download
curl -I https://development.citycommerce.group/bulk-invoices/template
```

### Rollback Plan

**If deployment fails:**

```bash
# 1. Rollback code
git reset --hard HEAD~3

# 2. Rollback migrations
php artisan migrate:rollback --step=3

# 3. Clear caches
php artisan optimize:clear

# 4. Restart services
php artisan queue:restart
```

---

## Troubleshooting

### Issue 1: Upload Fails with "Invalid Headers"

**Symptom:** Error message: "Invalid Excel headers. Missing: task_id, client_phone"

**Cause:** Excel template doesn't match expected columns

**Solution:**
```bash
# 1. Check template export
php artisan tinker --execute="
\$export = new App\Exports\BulkInvoiceTemplateExport(1);
dump(\$export->sheets()[0]->headings());
"

# 2. Verify expected headers in service
grep 'required.*headers' app/Services/BulkUploadValidationService.php
```

### Issue 2: Foreign Key Constraint Error on Migration

**Symptom:** `SQLSTATE[HY000]: General error: 1005 Can't create table (errno: 150)`

**Cause:** Migrations ran out of order, or tables already exist

**Solution:**
```bash
# 1. Check if tables exist
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
echo 'bulk_uploads: ' . (Schema::hasTable('bulk_uploads') ? 'EXISTS' : 'MISSING') . PHP_EOL;
echo 'bulk_upload_rows: ' . (Schema::hasTable('bulk_upload_rows') ? 'EXISTS' : 'MISSING') . PHP_EOL;
"

# 2. Drop tables if partially created
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
Schema::dropIfExists('bulk_upload_rows');
Schema::dropIfExists('bulk_uploads');
"

# 3. Re-run migrations
php artisan migrate --force
```

### Issue 3: Job Stuck in "Processing" Status

**Symptom:** Success page shows spinner forever, status never changes to "completed"

**Cause:** Queue worker not running, or job failed silently

**Solution:**
```bash
# 1. Check queue worker
ps aux | grep "queue:work"

# 2. Check failed jobs
php artisan queue:failed

# 3. Check logs
tail -50 storage/logs/laravel.log

# 4. Retry failed job
php artisan queue:retry <job-id>

# 5. If worker not running, start it
php artisan queue:work --queue=invoices --tries=3
```

### Issue 4: Duplicate Invoice Numbers

**Symptom:** Two invoices have the same invoice number

**Cause:** lockForUpdate not working, or concurrent transactions

**Solution:**
```bash
# 1. Check if lockForUpdate is in code
grep -n "lockForUpdate" app/Jobs/CreateBulkInvoicesJob.php

# 2. Check InvoiceSequence table
php artisan tinker --execute="
\$sequence = App\Models\InvoiceSequence::where('company_id', 1)->first();
dump(\$sequence);
"

# 3. Verify database isolation level
php artisan tinker --execute="
DB::select('SELECT @@transaction_isolation');
"

# Expected: REPEATABLE-READ or SERIALIZABLE
```

### Issue 5: PDFs Not Attaching to Emails

**Symptom:** Email received but no PDFs attached

**Cause:** PDF generation failing, or attachment method incorrect

**Solution:**
```bash
# 1. Test PDF generation manually
php artisan tinker --execute="
\$invoice = App\Models\Invoice::first();
\$pdf = Barryvdh\DomPDF\Facade\Pdf::loadView('invoice.pdf.invoice', [
    'invoice' => \$invoice,
    'company' => \$invoice->agent->branch->company,
    'invoiceDetails' => \$invoice->invoiceDetails,
    'isPdf' => true
]);
file_put_contents('/tmp/test-invoice.pdf', \$pdf->output());
echo 'PDF generated at /tmp/test-invoice.pdf' . PHP_EOL;
"

# 2. Check if DomPDF is installed
composer show barryvdh/laravel-dompdf

# 3. Check mail logs
tail -50 storage/logs/laravel.log | grep -i "mail\|pdf"

# 4. Test mailable
php artisan tinker --execute="
\$mail = new App\Mail\BulkInvoicesMail(1);
dump(\$mail->attachments());
"
```

### Issue 6: Client Not Matching by Phone

**Symptom:** Valid client phone flagged as unknown

**Cause:** Phone format mismatch, or company_id not scoped

**Solution:**
```bash
# 1. Check client records
php artisan tinker --execute="
\$client = App\Models\Client::where('mobile', '96512345678')
    ->where('company_id', 1)
    ->first();
dump(\$client);
"

# 2. Check if phone has formatting
php artisan tinker --execute="
\$clients = App\Models\Client::where('company_id', 1)
    ->pluck('mobile')
    ->take(10);
dump(\$clients);
"

# 3. Test matchClient method
php artisan tinker --execute="
\$service = app(App\Services\BulkUploadValidationService::class);
\$client = \$service->matchClient('96512345678', 1);
dump(\$client);
"
```

### Issue 7: Route Cache Fails

**Symptom:** `Unable to prepare route [pin] for serialization. Another route has already been assigned name [pin]`

**Cause:** Duplicate route name in routes/web.php

**Solution:**
```bash
# 1. Find duplicate route
grep -n "name('pin')" routes/web.php

# 2. Clear route cache
php artisan route:clear

# 3. Skip route caching for now
# (Only use config and view cache)
```

---

## Appendix

### Excel Template Format

**Sheet 1: Upload Template**
| task_id | client_phone | invoice_date | currency | notes | supplier_name |
|---------|--------------|--------------|----------|-------|---------------|
| 123     | 96512345678  | 2026-02-15   | KWD      | Test  | Emirates      |
| 124     | 96587654321  | 2026-02-15   | KWD      |       | Qatar Airways |

**Sheet 2: Client List**
| client_id | name         | mobile      | company   |
|-----------|--------------|-------------|-----------|
| 1         | John Doe     | 96512345678 | City Travelers |
| 2         | Jane Smith   | 96587654321 | City Travelers |

### Database Indexes

**For Performance:**
```sql
-- bulk_uploads
CREATE INDEX idx_company_status ON bulk_uploads(company_id, status);
CREATE INDEX idx_created_at ON bulk_uploads(created_at);

-- bulk_upload_rows
CREATE INDEX idx_bulk_upload_status ON bulk_upload_rows(bulk_upload_id, status);
CREATE INDEX idx_task_id ON bulk_upload_rows(task_id);
```

### Git Tags

**v1.0 Release:**
```bash
git tag -a v1.0 -m "v1.0 Bulk Invoice Upload

Delivered: Complete bulk invoice creation system from Excel uploads

Key accomplishments:
- Excel template download with pre-filled client list
- Comprehensive row-level validation
- Preview workflow with grouped invoice cards
- Background invoice creation with atomic transactions
- PDF generation and email delivery
- Error reporting with downloadable Excel reports
- Full upload history and audit trail

See .planning/MILESTONES.md for full details."
```

---

## Support

**Documentation:** This file
**Codebase:** https://github.com/soudshoja/-city-tour-development
**Issues:** GitHub Issues
**Deployed:** https://development.citycommerce.group

---

**Last Updated:** February 13, 2026
**Version:** v1.0
**Author:** Claude (Anthropic) + Soud Shoja
