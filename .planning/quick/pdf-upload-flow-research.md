# PDF Upload Flow Research - Soud Laravel

**Researched:** 2026-03-10
**Domain:** Laravel 11 File Upload & Document Processing
**Confidence:** HIGH

## Summary

The Soud Laravel system has multiple PDF upload pathways depending on the use case:

1. **Task File Upload** (`TaskController::upload`) - Main upload for AIR/PDF documents via web interface
2. **Agent Upload** (`TaskController::supplierTaskForAgent`) - Agent-specific uploads with supplier routing
3. **WhatsApp/Media Upload** (`IncomingMediaController::handleResayilWebhook`) - WhatsApp document uploads via Resayil webhook
4. **Chat/AI Upload** (`ChatController::handleFileUpload`) - Passport/document processing via AI
5. **N8n Webhook** (`N8nCallbackController`) - External automation callback for document extraction
6. **Bulk Invoice Upload** (`BulkInvoiceController::upload`) - Excel batch invoice uploads

The primary flow for task-related PDF uploads follows this pattern:
- Frontend form submission
- File stored in `storage/app/{company}/{supplier}/files_unprocessed/`
- Database record created in `file_uploads` table
- Manual or scheduled processing via `app:process-files` artisan command

## Standard Stack

### Core Components

| Component | Purpose | Why Standard |
|-----------|---------|--------------|
| Laravel Storage | File storage abstraction | Built-in, configurable disks |
| FileUpload Model | Database tracking | Audit trail, deduplication |
| ProcessAirFiles Command | Batch processing | Queue-safe, lock-protected |
| AirFileParser/AirFileService | AIR file parsing | Domain-specific extraction |
| AIManager | AI-based extraction | OpenAI/OpenWebUI integration |

### Supporting Components

| Component | Purpose | When to Use |
|-----------|---------|-------------|
| libmergepdf (Merger) | PDF merging for TBO suppliers | Merge supplier uploads |
| ResayilController | WhatsApp file handling | External media downloads |
| BulkUploadValidationService | Excel validation | Bulk invoice imports |

## Upload Endpoints & Routes

### Primary Task Upload Route

```
POST /tasks/upload
Route: tasks.upload
Controller: TaskController::upload
Middleware: auth
```

### Agent Upload Route

```
POST /tasks/agent/upload
Route: agent.upload
Controller: TaskController::supplierTaskForAgent
Middleware: auth
```

### API Chat Upload

```
POST /api/chat/upload
Route: api.chat.upload
Controller: ChatController::handleFileUpload
Middleware: none (public API)
```

### WhatsApp Webhook

```
POST /api/webhook/resayil/media
Route: webhook.resayil.media
Controller: IncomingMediaController::handleResayilWebhook
Middleware: none (webhook)
```

### N8n Callback

```
POST /api/webhooks/n8n/extraction
Route: api.webhooks.n8n.callback
Controller: N8nCallbackController::handle
Middleware: none (webhook)
```

## Storage Path Structure

```
storage/app/
├── {company_name}/                    # Lowercase, underscores for spaces
│   └── {supplier_name}/               # Lowercase, underscores for spaces
│       ├── files_unprocessed/         # Pending processing queue
│       ├── files_processed/           # Successfully processed
│       ├── files_error/               # Failed processing
│       └── resayil/{date}/            # WhatsApp uploads (dated folders)
├── bulk-uploads/{company_id}/         # Bulk invoice uploads
└── uploads/                           # Chat/AI passport uploads (public)
```

**Path Generation Code:**
```php
$companyName = strtolower(preg_replace('/\s+/', '_', $company->name));
$supplierName = strtolower(preg_replace('/\s+/', '_', $supplier->name));
$filePath = storage_path("app/{$companyName}/{$supplierName}/files_unprocessed");
```

## Database Tables

### file_uploads Table

```php
// Migration: 2025_07_04_101603_create_file_uploads_table.php
Schema::create('file_uploads', function (Blueprint $table) {
    $table->id();
    $table->string('file_name');
    $table->string('merged_file_name')->nullable();      // For merged PDFs
    $table->string('destination_path');
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
    $table->foreignId('company_id')->nullable()->constrained('companies'); // Added later
    $table->foreignId('task_id')->nullable()->constrained('tasks')->onDelete('cascade');
    $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
    $table->json('source_files')->nullable();            // For merged files tracking
    $table->timestamps();
});
```

### FileUpload Model

```php
// app/Models/FileUpload.php
protected $fillable = [
    'file_name',
    'merged_file_name',
    'destination_path',
    'user_id',
    'company_id',
    'supplier_id',
    'status',
    'source_files',
];

protected $casts = [
    'source_files' => 'array',
];

// Relationships
public function user()      // BelongsTo User
public function supplier()   // BelongsTo Supplier
public function task()       // BelongsTo Task
public function company()    // BelongsTo Company
```

## Upload Flow Details

### 1. Web Upload (TaskController::upload)

**Request Validation:**
```php
$request->validate([
    'agent_id' => 'nullable|exists:agents,id',
    'supplier_id' => 'required|exists:suppliers,id',
    'task_file' => [Rule::requiredIf(!$isMergeSupplier), 'array'],
    'task_file.*' => ['mimes:pdf,txt'],
    'batches' => [Rule::requiredIf($isMergeSupplier), 'array', 'min:1'],
    'batches.*' => ['array'],
    'batches.*.*' => ['file', 'mimes:pdf'],
    'batch_names' => ['nullable', 'array'],
    'batch_names.*' => ['nullable', 'string', 'max:120'],
]);
```

**Flow:**
1. Get authenticated user's company context (Company/Branch/Agent role)
2. Load supplier and check if merge supplier (`isMergeSupplier()`)
3. For merge suppliers (TBO Air, TBO Car):
   - Process batches of files
   - Merge PDFs using `libmergepdf`
   - Store merged file with source_files tracking
4. For regular suppliers:
   - Check for duplicate file names (per supplier/company)
   - Move file to `files_unprocessed/` directory
   - Create FileUpload record with pending status
5. Return response with success/error per file

**Response Structure:**
```php
[
    'status' => 'success|error',
    'message' => 'Files uploaded successfully: ...',
    'data' => ['file1.pdf', 'file2.pdf']  // or error details
]
```

### 2. Duplicate Detection

Files are checked against existing uploads before processing:

```php
$existingFileUpload = FileUpload::where([
    'file_name' => $fileName,
    'supplier_id' => $supplier->id,
    'company_id' => $company->id,
])->exists();

if ($existingFileUpload) {
    // Return appropriate error message based on uploader
}
```

### 3. Merge Suppliers (TBO Air, TBO Car)

Special handling for suppliers that require PDF merging:

```php
// Supplier::isMergeSupplier()
public function isMergeSupplier(): bool
{
    return in_array($this->name, ['TBO Air', 'TBO Car']);
}

// Merge flow
$merger = new Merger(new Fpdi2Driver());
foreach ($batchFiles as $file) {
    $merger->addFile($file->getRealPath());
}
$mergedBytes = $merger->merge();
Storage::put($mergedPath, $mergedBytes);

// Track source files
FileUpload::create([
    'file_name' => $mergedName,
    'destination_path' => Storage::path($mergedPath),
    'user_id' => $user->id,
    'supplier_id' => $supplier->id,
    'company_id' => $company->id,
    'status' => 'pending',
    'source_files' => $successFiles,  // Array of original filenames
]);
```

## Processing Flow

### ProcessAirFiles Command

```bash
php artisan app:process-files --batch --batch-size=10 --export-debug
```

**Options:**
- `--batch` : Use batch processing (upload all files first, then process together)
- `--single` : Use single file processing (process files one by one)
- `--batch-size=10` : Maximum files per batch
- `--export-debug` : Export parsed data to CSV/Excel for debugging

**Processing Steps:**
1. Acquire lock (cache or DB) to prevent concurrent runs
2. Iterate through companies and their active suppliers
3. For each supplier, scan `files_unprocessed/` directory
4. Determine processing method:
   - **AirFileParser**: For Amadeus AIR format files (regex-based, fast)
   - **AI-based**: For PDFs and other formats (OpenAI/OpenWebUI)
5. Parse files and normalize data using TaskSchema
6. Create Task records with flight/hotel/visa details
7. Move files to appropriate directory:
   - Success: `files_processed/`
   - Error: `files_error/`
8. Update FileUpload status to `completed` or `failed`

**File Movement After Processing:**
```php
$successPath = storage_path("app/{$companyName}/{$supplierName}/files_processed");
$errorPath = storage_path("app/{$companyName}/{$supplierName}/files_error");

// Successful files
File::move($fileRealPath, $successPath . '/' . $fileName);

// Failed files
File::move($fileRealPath, $errorPath . '/' . $fileName);
```

## Metadata Captured

### FileUpload Record Fields

| Field | Source | Description |
|-------|--------|-------------|
| `file_name` | Original/uploaded name | Sanitized filename |
| `merged_file_name` | System generated | For merged PDFs |
| `destination_path` | Storage path | Full filesystem path |
| `user_id` | Authenticated user | Uploader tracking |
| `company_id` | User's company | Multi-tenant isolation |
| `supplier_id` | Form submission | Document source |
| `task_id` | Post-processing | Linked task (after processing) |
| `status` | Processing status | pending/completed/failed |
| `source_files` | JSON array | Original files for merged PDFs |

### Processing Log Fields

```php
// DocumentProcessingLog model
'document_id' => uuid(),
'file_path' => $filePath,
'file_name' => $fileName,
'company_id' => $companyId,
'supplier_id' => $supplierId,
'user_id' => $userId,
'status' => 'pending|processing|completed|failed',
'n8n_execution_id' => null,  // For AI processing
'n8n_workflow_id' => null,
'processing_duration_ms' => null,
'extraction_result' => null,
'error_code' => null,
'error_message' => null,
```

## Frontend Response Structure

### Successful Upload Response

```php
// Single file or batch success
return response()->json([
    'status' => 'success',
    'message' => 'Files uploaded successfully: filename1.pdf, filename2.pdf',
    'data' => ['filename1.pdf', 'filename2.pdf']
]);
```

### Error Response

```php
// Duplicate file or validation error
return response()->json([
    'status' => 'error',
    'message' => 'Some files failed to upload.',
    'data' => [
        ['file_name' => 'duplicate.pdf', 'message' => 'File has already been uploaded by you'],
        ['file_name' => 'other.pdf', 'message' => 'File has been uploaded by another user: John Doe']
    ]
]);
```

### Bulk Upload Preview Response

```php
// After bulk invoice upload
return redirect()->route('bulk-invoices.preview', $bulkUpload->id)
    ->with('message', "Upload validated: {$results['valid']} valid rows, {$results['errors']} errors");
```

## Alternative Upload Paths

### 1. WhatsApp Upload Flow

```
User sends document to WhatsApp
     ↓
Resayil webhook receives media
     ↓
Download media to storage/app/public/uploads/
     ↓
Send to AI chat endpoint for passport extraction
     ↓
Create Client record with extracted data
```

**Storage Location:**
```php
Storage::put("public/uploads/{$newFilename}", $response->body());
$localPath = "uploads/{$newFilename}";
```

### 2. N8n Automation Flow

```
External document received
     ↓
N8n workflow triggered
     ↓
Document processed (extraction)
     ↓
Callback to /api/webhooks/n8n/extraction
     ↓
Update DocumentProcessingLog
     ↓
Task creation (if configured)
```

### 3. Bulk Invoice Upload

```
POST /bulk-invoices/upload
     ↓
Validate Excel headers
     ↓
Parse rows with Laravel Excel
     ↓
Validate each row (BulkUploadValidationService)
     ↓
Create BulkUpload + BulkUploadRow records
     ↓
Redirect to preview page
     ↓
User approves → CreateBulkInvoicesJob dispatched
```

## Common Pitfalls

### 1. Duplicate File Handling

**Issue:** Files with same name for same supplier/company cause upload rejection.

**Prevention:** Check `FileUpload::where([...])->exists()` before processing.

### 2. Merge Supplier Confusion

**Issue:** Uploading single files to TBO Air/Car suppliers when batches expected.

**Prevention:** Frontend shows batch upload UI for merge suppliers, validation enforces `batches` array.

### 3. Processing Lock Conflicts

**Issue:** Multiple `app:process-files` runs causing concurrent processing.

**Prevention:** Command uses cache lock with fallback to DB GET_LOCK.

### 4. Storage Path Permissions

**Issue:** `files_unprocessed` directory doesn't exist on first upload.

**Prevention:** System auto-creates directory:
```php
if (!File::isDirectory($filePath)) {
    File::makeDirectory($filePath, 0755, true, true);
}
```

### 5. Multi-tenant Data Isolation

**Issue:** File uploads cross company boundaries.

**Prevention:** Always filter by `company_id` in queries, derive from authenticated user's context.

## Code Examples

### Standard File Upload

```php
// TaskController::upload() - simplified
public function upload(Request $request)
{
    $user = Auth::user();
    $company = $user->company;  // or branch->company, or agent->branch->company

    $supplier = Supplier::find($request->supplier_id);
    $companyName = strtolower(preg_replace('/\s+/', '_', $company->name));
    $supplierName = strtolower(preg_replace('/\s+/', '_', $supplier->name));

    $filePath = storage_path("app/{$companyName}/{$supplierName}/files_unprocessed");

    if (!File::isDirectory($filePath)) {
        File::makeDirectory($filePath, 0755, true, true);
    }

    foreach ($request->file('task_file') as $file) {
        $fileName = $file->getClientOriginalName();

        // Duplicate check
        if (FileUpload::where([
            'file_name' => $fileName,
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
        ])->exists()) {
            continue;  // Skip or error
        }

        $file->move($filePath, $fileName);

        FileUpload::create([
            'file_name' => $fileName,
            'destination_path' => $filePath . '/' . $fileName,
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'status' => 'pending',
        ]);
    }

    return response()->json(['status' => 'success']);
}
```

### Check Processing Status

```php
// Check if file has been processed
$fileUpload = FileUpload::where([
    'file_name' => $request->file_name,
    'company_id' => $request->company_id,
])->first();

if ($fileUpload && $fileUpload->user_id) {
    $agent = Agent::where('user_id', $fileUpload->user_id)->first();
    // Use agent_id for task assignment
}
```

### Trigger Processing

```php
// Manual processing trigger
Artisan::call('app:process-files', ['--batch' => true]);

// Or schedule in Kernel.php
$schedule->command('app:process-files --batch')
    ->everyFiveMinutes()
    ->withoutOverlapping();
```

## Sources

### Primary (HIGH confidence)
- `app/Http/Controllers/TaskController.php` - Lines 3000-3334 (upload method)
- `app/Console/Commands/ProcessAirFiles.php` - Full file
- `app/Models/FileUpload.php` - Model definition
- `database/migrations/2025_07_04_101603_create_file_uploads_table.php` - Schema

### Secondary (MEDIUM confidence)
- `app/Http/Controllers/BulkInvoiceController.php` - Bulk upload flow
- `app/Http/Controllers/IncomingMediaController.php` - WhatsApp webhook handling
- `app/Http/Controllers/Api/Webhooks/N8nCallbackController.php` - N8n callback
- `app/Services/AirFileService.php` - Processing service

### Tertiary (Context)
- `routes/web.php` - Route definitions
- `resources/views/tasks/tasksUpload.blade.php` - Upload form view

## Metadata

**Confidence breakdown:**
- Upload endpoints & routes: HIGH - Direct code analysis
- Storage structure: HIGH - Multiple code paths confirm structure
- Processing flow: HIGH - Command and controller analysis
- Database schema: HIGH - Migration files reviewed
- Alternative paths: MEDIUM - Inferred from code, not fully tested

**Research date:** 2026-03-10
**Valid until:** 30 days (stable architecture)