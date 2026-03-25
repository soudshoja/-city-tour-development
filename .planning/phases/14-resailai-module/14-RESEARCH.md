# ResailAI Module Research

**Research Date:** 2026-03-11
**Status:** RESEARCH COMPLETE
**Next Phase:** 15 - ResailAI PDF Integration (Current Session)

---

## 1. Current State

### Completed (Phase 14)
| Component | Status | Location |
|-----------|--------|----------|
| Module Structure | ✅ Complete | `app/Modules/ResailAI/` |
| Service Provider | ✅ Complete | `app/Modules/ResailAI/Providers/ResailAIServiceProvider.php` |
| Configuration | ✅ Complete | `app/Modules/ResailAI/Config/resailai.php` |
| Routes | ✅ Complete | `app/Modules/ResailAI/Routes/routes.php` |
| Middleware | ✅ Complete | `app/Modules/ResailAI/Middleware/VerifyResailAIToken.php` |
| ProcessingAdapter | ✅ Complete | `app/Modules/ResailAI/Services/ProcessingAdapter.php` |
| TaskWebhookBridge | ✅ Complete | `app/Modules/ResailAI/Services/TaskWebhookBridge.php` |
| ProcessDocumentJob | ✅ Complete | `app/Modules/ResailAI/Jobs/ProcessDocumentJob.php` |
| CallbackController | ✅ Complete | `app/Modules/ResailAI/Http/Controllers/CallbackController.php` |
| Admin API Keys | ✅ Complete | `app/Http/Controllers/Api/ResailAIAdminController.php` |
| Admin Suppliers | ✅ Complete | `app/Http/Controllers/Api/ResailAISuppliersController.php` |
| Settings UI | ✅ Complete | `app/Http/Livewire/Admin/ResailaiSettingsIndex.php` |
| Supplier Toggle UI | ✅ Complete | `resources/views/resailai/suppliers.blade.php` |
| Developer Docs | ✅ Complete | `docs/resailai-module-setup.md` |
| Migration | ✅ Complete | `database/migrations/2026_03_11_000001_create_resailai_credentials_table.php` |

### Missing (Current Session)
| Component | Status | Impact |
|-----------|--------|--------|
| auto_process_pdf column | ❌ Missing | Feature flag not in DB |
| Upload flow integration | ❌ Missing | Files not processed via ResailAI |
| Callback processing | ⚠️ TODO | Callback returns 200 but doesn't create tasks |
| n8n webhook workflow | ⚠️ Test only | JSON created but not verified |

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Laravel Application                          │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  1. File Upload (TaskController::upload)                      │  │
│  │     - Stores file to: storage/app/{company}/{supplier}/...   │  │
│  │     - Creates FileUpload record in database                  │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  2. Process Document (AIR file or manual command)             │  │
│  │     - Checks: auto_process_pdf flag on supplier_companies    │  │
│  │     - If TRUE: Dispatch ProcessDocumentJob                   │  │
│  │     - If FALSE: Use AirFileParser (existing flow)            │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  3. ProcessDocumentJob (queued)                               │  │
│  │     - Sends to ResailAI service via n8n webhook              │  │
│  │     - Payload includes: company_id, supplier_id, callback_url│  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  4. n8n Webhook (ResailAI service)                            │  │
│  │     - Receives file, extracts data via Tika/Gutenberg        │  │
│  │     - Sends callback to Laravel with extraction results      │  │
│  └───────────────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────────────┐  │
│  │  5. Laravel Callback (CallbackController::handle)             │  │
│  │     - Validates Bearer token                                  │  │
│  │     - Calls TaskWebhookBridge to create task                 │  │
│  │     - Returns success/error response                          │  │
│  └───────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 3. File Path Conventions

| Environment | Upload Path | Storage Path |
|-------------|-------------|--------------|
| Local Development | `storage/app/{company}/{supplier}/files_unprocessed/` | `storage/app/` |
| Production | Same | Same (not web-accessible) |
| n8n Docker | `/var/www/storage/app/` | Mounted volume |

**Example:**
```
File uploaded: "FlyDubai_Flight_2026.pdf"
Stored as: storage/app/company_name/flydubai/files_unprocessed/FlyDubai_Flight_2026.pdf
Database record: destination_path = "/var/www/storage/app/company_name/flydubai/files_unprocessed/FlyDubai_Flight_2026.pdf"
```

---

## 4. Processing Flow Logic

### Current Flow (Manual Processing)
```php
// TaskController::upload() stores file
FileUpload::create([
    'file_name' => $fileName,
    'destination_path' => $filePath,
    'user_id' => $userId,
    'supplier_id' => $supplierId,
    'company_id' => $companyId,
    'status' => 'pending',  // <-- File is pending processing
]);

// Admin runs command to process
php artisan app:process-files --batch
```

### ResailAI Flow (Auto-Process)
```php
// TaskController::upload() checks flag
if (ProcessingAdapter::isPdfProcessingEnabled($supplierId, $companyId)) {
    // Dispatch job to queue (instant response)
    ProcessDocumentJob::dispatch($fileUploadId);
} else {
    // Traditional manual processing
    // File stays in files_unprocessed, waits for manual command
}

// Queue worker processes:
// 1. Send to ResailAI service via n8n webhook
// 2. Wait for callback
// 3. Process callback and create task
```

---

## 5. Required Changes (Current Session)

### A. Database Migration
```php
// database/migrations/2026_03_11_000002_add_auto_process_pdf_to_supplier_companies.php
Schema::table('supplier_companies', function (Blueprint $table) {
    $table->boolean('auto_process_pdf')->default(false)->after('is_active')
        ->comment('Auto-process PDF files via ResailAI webhook');
});
```

### B. TaskController::upload() Modification
```php
// After file upload, check if auto-process is enabled
$autoProcess = ProcessingAdapter::isPdfProcessingEnabled($supplierId, $companyId);

if ($autoProcess && $fileExtension === 'pdf') {
    // Dispatch to ResailAI queue
    $fileUpload = FileUpload::create([...]);
    ProcessDocumentJob::dispatch($fileUpload->id);
} else {
    // Traditional processing
}
```

### C. CallbackController Implementation
```php
public function handle(Request $request): JsonResponse
{
    // Validate token
    // Validate payload
    // Check feature flag
    // Transform extraction result
    // Call TaskWebhookBridge::process()
    // Update file status
    // Return response
}
```

---

## 6. Environment Variables

```env
# ResailAI Configuration
RESAILAI_API_TOKEN=your-bearer-token-here
N8N_WEBHOOK_URL=https://n8n.example.com/webhook/resailai-process

# Optional (defaults in config)
RESAILAI_TIMEOUT=30
RESAILAI_MAX_RETRIES=3
RESAILAI_CALLBACK_EXPIRY_MINUTES=15
```

---

## 7. n8n Webhook Configuration

### Webhook Node Settings
- **Path:** `resailai-process`
- **Method:** POST
- **Authentication:** None (handled via Bearer token in payload)

### Expected Payload
```json
{
  "document_id": "uuid-string",
  "supplier_id": 2,
  "company_id": 1,
  "agent_id": 5,
  "branch_id": 1,
  "file_path": "storage/app/company/supplier/files_unprocessed/file.pdf",
  "callback_url": "https://laravel.example.com/api/modules/resailai/callback"
}
```

---

## 8. Callback Response Format

```json
{
  "document_id": "uuid-string",
  "status": "success|error|pending",
  "supplier_id": 2,
  "company_id": 1,
  "agent_id": 5,
  "file_url": null,
  "extraction_result": {
    "tasks": [...],
    "metadata": {...}
  },
  "error": {
    "code": "ERR_EXTRACTION_FAILED",
    "message": "Could not extract text from PDF"
  } null
}
```

---

## 9. Key Files Reference

| File | Purpose |
|------|---------|
| `app/Modules/ResailAI/Providers/ResailAIServiceProvider.php` | Module bootstrap |
| `app/Modules/ResailAI/Services/ProcessingAdapter.php` | Feature flag check |
| `app/Modules/ResailAI/Services/TaskWebhookBridge.php` | Transform → TaskWebhook |
| `app/Modules/ResailAI/Jobs/ProcessDocumentJob.php` | Queue processing |
| `app/Modules/ResailAI/Http/Controllers/CallbackController.php` | Handle callbacks |
| `app/Http/Controllers/TaskController.php` | File upload (needs modification) |
| `app/Http/Webhooks/TaskWebhook.php` | Task creation pipeline |

---

## 10. Next Steps

1. **Run migration** for `auto_process_pdf` column
2. **Modify TaskController** to check flag and dispatch jobs
3. **Implement CallbackController** to process extraction results
4. **Test end-to-end** with sample PDF file
5. **Configure n8n webhook** with correct URL and secret
6. **Enable feature flag** for test supplier

---

*Research completed: 2026-03-11*
*For Soud Laravel ResailAI Module*
*Phase: 15 - PDF Processing Integration*
