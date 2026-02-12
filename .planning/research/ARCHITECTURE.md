# Architecture Research: Bulk Invoice Upload Integration

**Domain:** Laravel multi-tenant travel agency invoice management
**Researched:** 2026-02-12
**Confidence:** HIGH

## Integration Context

This architecture integrates **bulk invoice upload from Excel** into an existing Laravel 11 multi-tenant travel agency platform. The existing architecture already handles:
- Manual invoice creation via web UI (InvoiceController::store)
- Task management with 12 service types
- Multi-tenant data isolation (company_id)
- Invoice numbering via InvoiceSequence table
- PDF generation via DomPDF
- Email delivery via Laravel Mail
- Background job processing via queues

**Goal:** Add Excel upload → validate → preview → approve → create invoices + PDFs + emails without disrupting existing patterns.

## Recommended Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           HTTP/Controller Layer                          │
│  ┌──────────────────────┐         ┌────────────────────────────────┐    │
│  │ InvoiceController    │         │ BulkInvoiceUploadController    │    │
│  │ (existing)           │         │ (new)                          │    │
│  │ ::create()           │         │ ::upload()  → Upload Excel     │    │
│  │ ::store()            │         │ ::preview() → Show validation  │    │
│  │ ::show()             │         │ ::approve() → Trigger creation │    │
│  │ ::edit()             │         │ ::status()  → Check progress   │    │
│  └──────────────────────┘         └────────────────────────────────┘    │
│                 │                               │                        │
├─────────────────┼───────────────────────────────┼────────────────────────┤
│                 │                               ↓                        │
│                 │                  ┌──────────────────────────────────┐  │
│                 │                  │ InvoiceUploadService             │  │
│                 │                  │ (new service layer)              │  │
│                 │                  │ - validateExcelStructure()       │  │
│                 │                  │ - groupTasksByClient()           │  │
│                 │                  │ - matchClientsByPhone()          │  │
│                 │                  │ - validateSuppliers()            │  │
│                 │                  │ - generatePreviewSummary()       │  │
│                 │                  │ - dispatchInvoiceCreationJobs()  │  │
│                 │                  └──────────────────────────────────┘  │
│                 │                               │                        │
│                 │                               ↓                        │
│                 │                  ┌──────────────────────────────────┐  │
│                 └─────────────────>│ CreateBulkInvoicesJob            │  │
│                                    │ (new queue job)                  │  │
│                                    │ - Creates invoices in batches    │  │
│                                    │ - Reuses InvoiceController logic │  │
│                                    │ - Generates PDFs                 │  │
│                                    │ - Sends emails                   │  │
│                                    │ - Updates upload status          │  │
│                                    └──────────────────────────────────┘  │
│                                                 │                        │
├─────────────────────────────────────────────────┼────────────────────────┤
│                         Import Layer            │                        │
│                  ┌──────────────────────────────┴──────────────────┐     │
│                  │ InvoiceTasksImport (new)                        │     │
│                  │ implements: ToCollection, WithHeadingRow,       │     │
│                  │            WithValidation, SkipsOnFailure       │     │
│                  │ - Validates each row                            │     │
│                  │ - Collects valid rows                           │     │
│                  │ - Collects validation failures                  │     │
│                  │ - Does NOT insert to database                   │     │
│                  └─────────────────────────────────────────────────┘     │
│                                                 │                        │
├─────────────────────────────────────────────────┼────────────────────────┤
│                          Data Layer             │                        │
│  ┌───────────────────┐  ┌────────────────────┐ │  ┌──────────────────┐  │
│  │ Invoice           │  │ InvoiceDetail      │ │  │ InvoiceUpload    │  │
│  │ (existing)        │  │ (existing)         │ │  │ (new)            │  │
│  │ - invoice_number  │  │ - invoice_id       │ │  │ - company_id     │  │
│  │ - client_id       │  │ - task_id          │ │  │ - agent_id       │  │
│  │ - agent_id        │  │ - task_price       │ │  │ - file_path      │  │
│  │ - amount          │  │ - supplier_price   │ │  │ - status         │  │
│  │ - status          │  │ - markup_price     │ │  │ - preview_data   │  │
│  └───────────────────┘  └────────────────────┘ │  │ - result_data    │  │
│           │                      │              │  │ - approved_at    │  │
│           │                      │              │  │ - completed_at   │  │
│  ┌────────┴──────────────────────┴──────┐      │  └──────────────────┘  │
│  │ InvoiceSequence (existing)           │      │                        │
│  │ - company_id                         │      │                        │
│  │ - current_sequence                   │      │                        │
│  └──────────────────────────────────────┘      │                        │
└─────────────────────────────────────────────────┴────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Integration Point |
|-----------|----------------|-------------------|
| **BulkInvoiceUploadController** | Handle HTTP requests for Excel upload, preview, approve | New controller, routes to `/invoices/bulk-upload/*` |
| **InvoiceUploadService** | Orchestrate validation, client matching, preview generation | New service, calls existing InvoiceController methods |
| **InvoiceTasksImport** | Parse Excel, validate rows using Laravel validation rules | New import class using Maatwebsite/Laravel-Excel |
| **CreateBulkInvoicesJob** | Background processing of invoice creation from validated data | New queue job, reuses InvoiceController::store logic |
| **InvoiceUpload** (model) | Track upload sessions, store preview data, audit trail | New model and `invoice_uploads` table |
| **InvoiceController** (existing) | Reused for actual invoice creation logic, NO changes needed | Extract reusable methods into service if needed |
| **InvoiceMail** (existing) | Email invoice PDFs to accountant and agent | Reused as-is with additional recipients |

## Data Flow

### Complete Request Flow

```
1. UPLOAD PHASE
   User uploads Excel file
       ↓
   [POST /invoices/bulk-upload]
       ↓
   BulkInvoiceUploadController::upload()
       ↓
   Store file to storage/app/uploads/invoices/{company_id}/{timestamp}.xlsx
       ↓
   Create InvoiceUpload record (status: 'pending')
       ↓
   InvoiceUploadService::processUpload()
       ↓
   Excel::toCollection(InvoiceTasksImport)
       ↓
   Validate each row:
       - Required fields (client_mobile, task_description, amount, supplier_name)
       - Supplier exists in company
       - Task type enum valid
       - Amount is numeric > 0
       - Date format valid
       ↓
   SkipsOnFailure collects errors
       ↓
   Generate preview data:
       - Group valid rows by client_mobile
       - Calculate invoice totals per client
       - Flag unknown clients (not in database)
       - Count invoices to be created
       ↓
   Store preview in InvoiceUpload.preview_data (JSON)
       ↓
   Update InvoiceUpload.status = 'validated' or 'validation_failed'
       ↓
   Return JSON: { upload_id, preview_url, validation_errors }

---

2. PREVIEW PHASE
   User views preview summary
       ↓
   [GET /invoices/bulk-upload/{upload_id}/preview]
       ↓
   BulkInvoiceUploadController::preview()
       ↓
   Load InvoiceUpload record
       ↓
   Render preview view with:
       - Summary: X invoices, Y tasks, Z clients
       - Table: Client Mobile | Client Name | Tasks | Total Amount
       - Unknown clients flagged in red
       - Validation errors listed
       - [Approve] [Reject] buttons
       ↓
   Return HTML view

---

3. APPROVAL PHASE
   User approves bulk creation
       ↓
   [POST /invoices/bulk-upload/{upload_id}/approve]
       ↓
   BulkInvoiceUploadController::approve()
       ↓
   Validate InvoiceUpload.status == 'validated'
       ↓
   Update InvoiceUpload.status = 'processing'
       ↓
   Update InvoiceUpload.approved_at = now()
       ↓
   Dispatch CreateBulkInvoicesJob::dispatch($uploadId)
       ↓
   Return JSON: { message: "Processing started", status_url }

---

4. BACKGROUND PROCESSING (Queue Job)
   CreateBulkInvoicesJob::handle()
       ↓
   Load InvoiceUpload with preview_data
       ↓
   DB::transaction() BEGIN
       ↓
   For each client group in preview_data:
       ↓
       Get or fail: client by mobile + company_id
           ↓
       Generate invoice_number using InvoiceSequence
           ↓
       Create Invoice record:
           - invoice_number
           - client_id
           - agent_id (from upload)
           - sub_amount (sum of task amounts)
           - amount (sub_amount)
           - status = 'unpaid'
           - invoice_date (from Excel or today)
           - due_date (calculated)
           - currency (company default)
           ↓
       For each task in client group:
           ↓
           Create or find Task record (if not exists):
               - description
               - type
               - supplier_id
               - client_id
               - agent_id
               - company_id
               - total (supplier price)
               - status = 'issued'
               ↓
           Create InvoiceDetail:
               - invoice_id
               - invoice_number
               - task_id
               - task_description
               - task_price (from Excel)
               - supplier_price (from Excel)
               - markup_price (task_price - supplier_price)
               - profit (same as markup)
               - paid = false
               ↓
       End task loop
       ↓
       Generate PDF:
           Pdf::loadView('invoice.pdf.invoice', $invoice)
           Store to storage/app/invoices/{company_id}/{invoice_number}.pdf
           ↓
       Queue email:
           Mail::to([$accountantEmail, $agentEmail])
               ->send(new InvoiceMail($invoice->id))
           ↓
   End client group loop
   ↓
   DB::transaction() COMMIT
       ↓
   Update InvoiceUpload:
       - status = 'completed'
       - completed_at = now()
       - result_data = JSON (created invoice IDs, PDF paths)
       ↓
   Log success
       ↓
   CATCH any exception:
       DB::transaction() ROLLBACK
       Update InvoiceUpload.status = 'failed'
       Update InvoiceUpload.result_data = error message
       Log error with stack trace
       Notify admin via email/Slack

---

5. STATUS CHECK (Optional Polling)
   User checks processing status
       ↓
   [GET /invoices/bulk-upload/{upload_id}/status]
       ↓
   BulkInvoiceUploadController::status()
       ↓
   Load InvoiceUpload record
       ↓
   Return JSON:
       {
         status: 'processing' | 'completed' | 'failed',
         progress: { created: 5, total: 10 },
         result_url: '/invoices' (if completed)
       }
```

## Architectural Patterns

### Pattern 1: Preview-Approve Workflow

**What:** Two-phase commit pattern where data is validated and previewed before permanent storage.

**When to use:** Bulk operations where errors are likely and user review is critical.

**How it works:**
1. Parse Excel into temporary data structure (in-memory collection)
2. Validate all rows without database writes
3. Store validation results in preview_data JSON column
4. User reviews and approves
5. Background job performs actual database inserts

**Trade-offs:**
- PRO: User catches errors before invoice creation
- PRO: No orphaned invoices if approval is rejected
- PRO: Audit trail of what was approved
- CON: Extra step in workflow
- CON: Preview data must be serializable (JSON)

**Example:**
```php
// In InvoiceUploadService
public function processUpload(UploadedFile $file, int $companyId, int $agentId): array
{
    $import = new InvoiceTasksImport();
    Excel::import($import, $file);

    $validRows = $import->getValidRows();
    $failures = $import->getFailures();

    // Group tasks by client mobile
    $groupedByClient = $validRows->groupBy('client_mobile');

    $preview = $groupedByClient->map(function ($tasks, $mobile) use ($companyId) {
        $client = Client::where('phone', $mobile)
            ->where('company_id', $companyId)
            ->first();

        return [
            'client_mobile' => $mobile,
            'client_name' => $client?->first_name ?? 'UNKNOWN',
            'client_id' => $client?->id,
            'is_unknown' => is_null($client),
            'tasks_count' => $tasks->count(),
            'total_amount' => $tasks->sum('amount'),
            'tasks' => $tasks->toArray(),
        ];
    });

    return [
        'preview' => $preview,
        'validation_errors' => $failures,
        'summary' => [
            'total_invoices' => $groupedByClient->count(),
            'total_tasks' => $validRows->count(),
            'unknown_clients' => $preview->where('is_unknown', true)->count(),
        ],
    ];
}
```

### Pattern 2: Reuse Existing Logic via Service Extraction

**What:** Extract invoice creation logic from InvoiceController into a service, then reuse in both manual and bulk flows.

**When to use:** When adding a new entry point to existing functionality.

**How it works:**
1. Create InvoiceCreationService with createInvoice($data) method
2. Extract logic from InvoiceController::store() into service
3. InvoiceController calls service
4. CreateBulkInvoicesJob calls same service

**Trade-offs:**
- PRO: Single source of truth for invoice creation
- PRO: Easier testing (service is isolated)
- PRO: Maintains consistency between manual and bulk
- CON: Refactoring existing controller (risk of regression)
- CON: More abstraction layers

**Alternative (lower risk):** Keep InvoiceController as-is, duplicate logic in job with clear comment "// Logic mirrored from InvoiceController::store()". Update both places when changes needed.

**Example:**
```php
// Option A: Service extraction (if refactoring is acceptable)
class InvoiceCreationService
{
    public function createInvoice(array $data): Invoice
    {
        DB::transaction(function () use ($data) {
            $invoice = Invoice::create([
                'invoice_number' => $data['invoice_number'],
                'client_id' => $data['client_id'],
                'agent_id' => $data['agent_id'],
                'amount' => $data['amount'],
                // ... other fields
            ]);

            foreach ($data['tasks'] as $taskData) {
                InvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'task_id' => $taskData['task_id'],
                    // ... other fields
                ]);
            }

            return $invoice;
        });
    }
}

// Option B: Direct reuse (if no refactoring)
// In CreateBulkInvoicesJob
public function handle()
{
    // Mirror logic from InvoiceController::store() line 1171-1290
    // Update both places if invoice creation logic changes
}
```

### Pattern 3: Transaction Boundaries with Partial Success

**What:** Wrap each invoice creation in its own transaction, allowing partial success if some invoices fail.

**When to use:** Bulk operations where one failure shouldn't block all others.

**How it works:**
1. Loop through client groups
2. For each client, start a new transaction
3. Create invoice + details + task creation
4. Commit transaction
5. If exception, rollback ONLY that invoice, continue to next
6. Track successes and failures in result_data

**Trade-offs:**
- PRO: Partial success is better than all-or-nothing
- PRO: User gets some invoices even if a few fail
- CON: Harder to undo if user wants to revert
- CON: Need clear reporting of which invoices succeeded

**Alternative:** Single transaction for all invoices (all-or-nothing). Safer but less user-friendly.

**Recommendation:** Use partial success with detailed result reporting.

**Example:**
```php
public function handle()
{
    $upload = InvoiceUpload::findOrFail($this->uploadId);
    $previewData = $upload->preview_data;

    $results = [
        'success' => [],
        'failed' => [],
    ];

    foreach ($previewData as $clientGroup) {
        try {
            DB::transaction(function () use ($clientGroup, &$results) {
                $invoice = $this->createInvoiceForClient($clientGroup);
                $results['success'][] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_mobile' => $clientGroup['client_mobile'],
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to create invoice for client', [
                'client_mobile' => $clientGroup['client_mobile'],
                'error' => $e->getMessage(),
            ]);

            $results['failed'][] = [
                'client_mobile' => $clientGroup['client_mobile'],
                'error' => $e->getMessage(),
            ];
        }
    }

    $upload->update([
        'status' => count($results['failed']) > 0 ? 'completed_with_errors' : 'completed',
        'result_data' => $results,
        'completed_at' => now(),
    ]);
}
```

### Pattern 4: File Storage with Cleanup

**What:** Store uploaded Excel files temporarily, clean up after processing.

**When to use:** File uploads that don't need permanent retention.

**How it works:**
1. Upload stores to `storage/app/uploads/invoices/{company_id}/{timestamp}.xlsx`
2. Store file path in InvoiceUpload.file_path
3. After successful processing + 7 days, scheduled task deletes file
4. Or: Delete immediately after approval if preview_data contains all needed info

**Trade-offs:**
- PRO: Disk space management
- PRO: Security (no stale sensitive files)
- CON: Can't re-process from original file
- CON: Need scheduled cleanup task

**Recommendation:** Keep files for 30 days, then auto-delete. Allows re-processing if needed.

**Example:**
```php
// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $thirtyDaysAgo = now()->subDays(30);

        InvoiceUpload::where('completed_at', '<', $thirtyDaysAgo)
            ->whereNotNull('file_path')
            ->each(function ($upload) {
                if (Storage::exists($upload->file_path)) {
                    Storage::delete($upload->file_path);
                    $upload->update(['file_path' => null]);
                }
            });
    })->daily();
}
```

## Integration Points with Existing Codebase

### Direct Integration Points

| Existing Component | How Bulk Upload Integrates | Changes Required |
|-------------------|---------------------------|------------------|
| **InvoiceController::store()** | Reuse invoice creation logic OR extract to service | OPTION A: Extract to InvoiceCreationService<br>OPTION B: Mirror logic in CreateBulkInvoicesJob |
| **InvoiceSequence** | Same logic for generating invoice numbers per company | None - reuse as-is |
| **InvoiceMail** | Send PDFs to accountant + agent emails | None - pass additional recipients array |
| **Client model** | Match clients by `(company_id, phone)` | None - existing query pattern |
| **Task model** | Create tasks from Excel if they don't exist | None - existing creation pattern |
| **Supplier model** | Validate supplier names exist for company | None - existing query pattern |
| **Invoice, InvoiceDetail models** | Create records same as manual flow | None - same structure |
| **Queue system** | Dispatch CreateBulkInvoicesJob to existing queue | None - existing queue infrastructure |
| **DomPDF** | Generate invoice PDFs after creation | None - existing PDF generation |
| **Multi-tenant isolation** | Filter by company_id in all queries | None - existing pattern applied to uploads |

### New Components Required

| Component | Purpose | Why New |
|-----------|---------|---------|
| **BulkInvoiceUploadController** | Handle upload/preview/approve HTTP endpoints | New feature entry point |
| **InvoiceUploadService** | Orchestrate validation and preview generation | Business logic isolation |
| **InvoiceTasksImport** | Parse Excel with Maatwebsite/Laravel-Excel | New import class |
| **CreateBulkInvoicesJob** | Background invoice creation from approved data | Async processing |
| **InvoiceUpload model** | Track upload sessions and store preview data | Audit trail and state management |
| **invoice_uploads migration** | Create table for upload tracking | Persistent state storage |

### Routes Integration

```php
// Add to routes/web.php
Route::middleware(['auth', 'verified'])->prefix('invoices')->group(function () {
    // Existing invoice routes...
    Route::get('/', [InvoiceController::class, 'index'])->name('invoice.index');
    Route::post('/store', [InvoiceController::class, 'store'])->name('invoice.store');

    // NEW: Bulk upload routes
    Route::prefix('bulk-upload')->name('invoice.bulk-upload.')->group(function () {
        Route::get('/', [BulkInvoiceUploadController::class, 'index'])->name('index');
        Route::post('/upload', [BulkInvoiceUploadController::class, 'upload'])->name('upload');
        Route::get('/{upload}/preview', [BulkInvoiceUploadController::class, 'preview'])->name('preview');
        Route::post('/{upload}/approve', [BulkInvoiceUploadController::class, 'approve'])->name('approve');
        Route::post('/{upload}/reject', [BulkInvoiceUploadController::class, 'reject'])->name('reject');
        Route::get('/{upload}/status', [BulkInvoiceUploadController::class, 'status'])->name('status');
    });
});
```

## Suggested Build Order (Dependencies)

### Phase 1: Data Layer (Foundation)
**Build first because:** All other components depend on this.

1. Create `invoice_uploads` migration with fields:
   - `id`, `company_id`, `agent_id`, `file_path`, `status`, `preview_data`, `result_data`, `approved_at`, `completed_at`, `timestamps`
2. Create `InvoiceUpload` model with relationships and casts
3. Run migration and test model CRUD

**Why first:** Foundation for all upload tracking. No dependencies on other new components.

### Phase 2: Import and Validation (Core Logic)
**Build second because:** Services depend on this.

1. Create `InvoiceTasksImport` class implementing:
   - `ToCollection` (not `ToModel` - preview only)
   - `WithHeadingRow` (Excel headers as keys)
   - `WithValidation` (Laravel validation rules)
   - `SkipsOnFailure` (collect errors)
2. Define validation rules in `rules()` method
3. Test with sample Excel files
4. Handle validation failures collection

**Why second:** Core parsing logic. Independent component.

### Phase 3: Service Layer (Orchestration)
**Build third because:** Controllers depend on this.

1. Create `InvoiceUploadService` with methods:
   - `processUpload()` - Parse and validate Excel
   - `groupTasksByClient()` - Group by mobile
   - `matchClientsByPhone()` - Lookup clients
   - `validateSuppliers()` - Check supplier exists
   - `generatePreviewSummary()` - Create preview data
2. Test service with real Excel data
3. Verify preview_data JSON structure

**Why third:** Business logic orchestration. Depends on Import (Phase 2) and Model (Phase 1).

### Phase 4: Controller and Views (User Interface)
**Build fourth because:** UI layer depends on service.

1. Create `BulkInvoiceUploadController` with actions:
   - `index()` - Upload form
   - `upload()` - Process file upload
   - `preview()` - Show validation results
   - `approve()` - Trigger job dispatch
   - `reject()` - Cancel upload
   - `status()` - Check progress
2. Create Blade views for upload form and preview
3. Add routes to `web.php`

**Why fourth:** User-facing layer. Depends on Service (Phase 3).

### Phase 5: Background Job (Async Processing)
**Build fifth because:** Requires all previous layers.

1. Create `CreateBulkInvoicesJob` queue job
2. Implement `handle()` method:
   - Load InvoiceUpload
   - Loop through preview_data
   - Create invoices (reuse InvoiceController logic OR call extracted service)
   - Generate PDFs
   - Send emails
   - Update InvoiceUpload result
3. Test with queue worker running

**Why fifth:** Integration layer. Depends on Controller (Phase 4), Service (Phase 3), and existing InvoiceController logic.

### Phase 6: PDF and Email Integration (Delivery)
**Build sixth because:** Final output delivery.

1. Modify `InvoiceMail` if needed (or create `BulkInvoiceMail` variant)
2. Test PDF generation for bulk-created invoices
3. Test email delivery to accountant + agent
4. Verify email queue handling

**Why sixth:** Delivery mechanism. Depends on Job (Phase 5) completing invoice creation.

### Phase 7: Cleanup and Audit (Polish)
**Build last because:** Non-critical enhancements.

1. Add scheduled task for old file cleanup
2. Add upload history page (list past uploads)
3. Add re-process capability (for failed uploads)
4. Add audit logging
5. Add monitoring/alerts for failed jobs

**Why last:** Nice-to-have features. System is functional without these.

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| **0-100 invoices/upload** | Process synchronously in controller (no queue job needed). Return result immediately. |
| **100-1000 invoices/upload** | **Current recommendation.** Queue job with single transaction per invoice. Estimated time: 5-10 minutes for 1000 invoices. |
| **1000-10000 invoices/upload** | Chunk processing: Split into batches of 500 invoices per job. Chain jobs together. Add progress tracking. |
| **10000+ invoices/upload** | Consider: (1) Batch insert with raw SQL for performance, (2) Background processing with progress bar, (3) Separate queue for bulk operations, (4) Database optimization (indexes on client.phone, supplier.name). |

### Scaling Priorities

1. **First bottleneck:** Invoice number generation with InvoiceSequence. Each invoice locks the sequence table row. **Fix:** Batch reserve invoice numbers before loop (reserve N numbers at once).

2. **Second bottleneck:** PDF generation. DomPDF is slow for large volumes. **Fix:** Generate PDFs asynchronously in separate job after invoice creation. Queue 1000 GenerateInvoicePdfJob instances.

3. **Third bottleneck:** Email sending. Bulk emails may hit rate limits. **Fix:** Use email service batch API (e.g., Postmark batch endpoint) or throttle email jobs.

## Anti-Patterns to Avoid

### Anti-Pattern 1: Single Transaction for All Invoices

**What people do:** Wrap entire bulk creation in one DB::transaction().

**Why it's wrong:**
- If invoice #500 fails, all 499 previous invoices rollback
- Lock contention on invoice_sequence table
- User gets zero invoices instead of partial success
- Hard to report which specific invoice failed

**Do this instead:** Individual transactions per invoice with failure tracking in result_data.

### Anti-Pattern 2: Synchronous Processing in Controller

**What people do:** Process all invoices in controller action, return when done.

**Why it's wrong:**
- HTTP timeout for large uploads (>100 invoices)
- User waits staring at loading spinner
- No progress indication
- Retrying on failure re-processes everything

**Do this instead:** Dispatch queue job immediately, return status URL, let user poll or use WebSocket for progress.

### Anti-Pattern 3: Auto-Create Missing Clients

**What people do:** If client mobile not found, auto-create Client record from Excel.

**Why it's wrong:**
- Duplicate clients with slight mobile variations (+965 vs 965)
- Missing required client data (email, civil_no, passport)
- No validation of client data quality
- Violates business rule "manual review for new clients"

**Do this instead:** Flag unknown clients in preview, require user to create clients manually or provide more data.

### Anti-Pattern 4: Skip Validation, Rely on Database Constraints

**What people do:** Insert directly, catch database exceptions.

**Why it's wrong:**
- User sees cryptic MySQL errors instead of friendly messages
- No bulk validation - fails one at a time
- No preview of what will be created
- Wastes processing time on invalid data

**Do this instead:** Validate everything upfront with Laravel validation rules, show all errors in preview.

### Anti-Pattern 5: Store Excel Data in Database

**What people do:** Parse Excel, insert each row to invoice_upload_rows table, then process from database.

**Why it's wrong:**
- Extra database table and queries
- Need to clean up rows after processing
- JSON preview_data column is simpler and sufficient
- Adds complexity without benefit

**Do this instead:** Store validated rows in preview_data JSON column on InvoiceUpload record. Sufficient for preview and processing.

## Error Handling Strategy

### Validation Phase Errors

**Where:** InvoiceTasksImport during Excel parsing

**Strategy:** Collect all validation failures using `SkipsOnFailure`, return to user in preview.

**Example:**
```php
// In preview view
@foreach($validationErrors as $failure)
    <div class="alert alert-danger">
        Row {{ $failure->row() }}: {{ $failure->errors()[0] }}
    </div>
@endforeach
```

### Processing Phase Errors

**Where:** CreateBulkInvoicesJob during invoice creation

**Strategy:** Individual try-catch per invoice, log failure, continue to next invoice.

**Example:**
```php
foreach ($clientGroups as $group) {
    try {
        DB::transaction(function () use ($group) {
            // Create invoice
        });
        $results['success'][] = ...;
    } catch (\Exception $e) {
        Log::error('Bulk invoice creation failed', [
            'client_mobile' => $group['client_mobile'],
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        $results['failed'][] = ...;
    }
}
```

### System Errors (Queue Job Failure)

**Where:** Job crashes completely (uncaught exception)

**Strategy:** Laravel queue retry mechanism + failed_jobs table

**Example:**
```php
// In CreateBulkInvoicesJob
public $tries = 3; // Retry up to 3 times
public $backoff = [30, 60, 120]; // Wait 30s, then 60s, then 120s

public function failed(\Throwable $exception)
{
    // Update InvoiceUpload status to 'failed'
    InvoiceUpload::where('id', $this->uploadId)
        ->update([
            'status' => 'failed',
            'result_data' => ['error' => $exception->getMessage()],
        ]);

    // Notify admin
    Log::critical('Bulk invoice job failed completely', [
        'upload_id' => $this->uploadId,
        'exception' => $exception->getMessage(),
    ]);
}
```

## Sources

### Laravel Excel Documentation and Patterns
- [Row Validation | Laravel Excel](https://docs.laravel-excel.com/3.1/imports/validation.html) — Official validation guide
- [How to validate fields in maatwebsite excel import](https://laracasts.com/discuss/channels/laravel/how-to-validate-fields-in-maatwebsite-excel-import) — Community patterns
- [Laravel 11 Import Export Excel and CSV File Tutorial](https://www.itsolutionstuff.com/post/laravel-11-import-export-excel-and-csv-file-tutorialexample.html) — Current version guide

### Transaction and Rollback Best Practices
- [How to Handle Transactions in Laravel](https://oneuptime.com/blog/post/2026-02-02-laravel-database-transactions/view) — 2026 best practices
- [Mastering Laravel Database Transactions](https://masteryoflaravel.medium.com/mastering-laravel-database-transactions-from-automatic-rollbacks-to-deadlock-retries-e6c8fe5cf55e) — Advanced patterns
- [How to use try-catch with DB::transaction in Laravel](https://blog.hassam.dev/try-catch-with-db-transaction-in-laravel/) — Error handling guide

### File Storage and Cleanup
- [Managing temporary files in Laravel](https://accreditly.io/articles/managing-temporary-files-in-laravel) — Storage patterns
- [Temp files not deleted if validation fails · Issue #2792](https://github.com/SpartnerNL/Laravel-Excel/issues/2792) — Known issues
- [masterro/laravel-file-cleaner](https://packagist.org/packages/masterro/laravel-file-cleaner) — Cleanup package

### Existing Codebase Analysis
- `/app/Http/Controllers/InvoiceController.php` — Existing invoice creation logic (lines 1171-1290)
- `/app/Imports/TasksImport.php` — Existing import pattern with Maatwebsite/Excel
- `/app/Mail/InvoiceMail.php` — Existing email delivery pattern
- `/app/Models/InvoiceSequence.php` — Invoice numbering mechanism
- `/database/migrations/2024_10_29_063642_create_invoices_table.php` — Invoice schema
- `/database/migrations/2025_03_17_111051_create_invoice_details_table.php` — Invoice details schema

---

*Architecture research for: Bulk Invoice Upload Integration*
*Researched: 2026-02-12*
*Confidence: HIGH (based on existing codebase analysis + verified Laravel patterns)*
