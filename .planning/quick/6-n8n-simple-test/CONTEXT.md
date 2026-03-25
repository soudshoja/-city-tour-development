# N8n Simple Test - Project Context & Specifications

**Date Created:** 2026-03-09
**Status:** Documented for future implementation
**Scope:** MVP - file upload → n8n processing → webhook callback → database storage

---

## What We're Building

A minimal end-to-end integration test between Laravel and n8n:

1. **Laravel** uploads a document to **n8n** workflow via webhook POST
2. **N8n** receives file, extracts structured data from PDF/document
3. **N8n** sends extraction results back to **Laravel** via webhook callback
4. **Laravel** stores processing status and extracted data in database

**Critical constraint:** Only these two things. No authentication, no retries, no error recovery, no fancy features. Pure happy-path test.

---

## Exact Payloads

### Phase 1: Upload Request (Laravel → N8n)

**Endpoint:** `POST` to n8n webhook (TBD - n8n provides this)

**Payload:**
```json
{
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "file_path": "http://development.citycommerce.group/storage/documents/file.pdf",
  "company_id": 1,
  "supplier_id": 5,
  "agent_id": 2,
  "document_type": "air"
}
```

**Description:**
- `document_id`: Unique UUID for tracking this document through the pipeline
- `file_path`: Full HTTP URL to the PDF file in Laravel storage (must be publicly accessible)
- `company_id`, `supplier_id`, `agent_id`: Tenant context for data isolation
- `document_type`: Type hint ("air", "pdf", "image", "email") - helps n8n choose extraction model

**Example curl (for testing):**
```bash
curl -X POST https://n8n.example.com/webhook/your-workflow-id \
  -H "Content-Type: application/json" \
  -d '{
    "document_id": "550e8400-e29b-41d4-a716-446655440000",
    "file_path": "http://development.citycommerce.group/storage/documents/file.pdf",
    "company_id": 1,
    "supplier_id": 5,
    "agent_id": 2,
    "document_type": "air"
  }'
```

---

### Phase 3: Webhook Callback (N8n → Laravel)

**Endpoint:** `POST /api/webhooks/n8n/extraction` (Laravel endpoint)

**Payload:**
```json
{
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "success",
  "extraction_result": {
    "reference": "ZC4D44",
    "type": "flight",
    "status": "reissued",
    "passenger": {
      "name": "Mr. Sanjai"
    },
    "financial_details": {
      "currency": "KWD",
      "price": 75.7,
      "tax": 2,
      "surcharge": 3.4,
      "total": 24.15
    },
    "supplier_information": {
      "supplier": "Fly Dubai",
      "pay_date": "2025-08-19"
    },
    "flight_details": [
      {
        "flight_number": "FZ444",
        "class": "Economy",
        "departure": {
          "airport": "Unknown",
          "time": "06:45"
        },
        "arrival": {
          "airport": "Unknown",
          "time": "09:25"
        },
        "baggage": {
          "hand": "7 kg included",
          "checked": "30 kg"
        },
        "farebase": "75.7"
      }
    ]
  }
}
```

**Description:**
- `document_id`: Same UUID from the upload request - used to match callback to document
- `status`: "success" or "failed" - processing result
- `extraction_result`: Nested object containing all extracted structured data
  - Flight-specific fields: reference, passenger, flight_details, financial_details
  - Structured for direct insertion into Task table

**Example curl (for testing Laravel endpoint):**
```bash
curl -X POST http://localhost:8000/api/webhooks/n8n/extraction \
  -H "Content-Type: application/json" \
  -d '{
    "document_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "success",
    "extraction_result": {
      "reference": "ZC4D44",
      "type": "flight",
      "status": "reissued",
      "passenger": {"name": "Mr. Sanjai"},
      "financial_details": {"currency": "KWD", "price": 75.7, "tax": 2, "surcharge": 3.4, "total": 24.15},
      "supplier_information": {"supplier": "Fly Dubai", "pay_date": "2025-08-19"},
      "flight_details": [{"flight_number": "FZ444", "class": "Economy", "departure": {"airport": "Unknown", "time": "06:45"}, "arrival": {"airport": "Unknown", "time": "09:25"}, "baggage": {"hand": "7 kg included", "checked": "30 kg"}, "farebase": "75.7"}]
    }
  }'
```

---

## Database Schema - Existing Tables

### DocumentProcessingLog Table

**Purpose:** Tracks lifecycle of document upload → processing → completion

**Columns:**
| Column | Type | Purpose |
|--------|------|---------|
| `id` | UUID primary key | Unique identifier |
| `document_id` | UUID, unique | Matches document across upload & callback |
| `company_id` | Integer, foreign key | Tenant isolation |
| `supplier_id` | Integer, foreign key | Supplier context |
| `agent_id` | Integer, foreign key | Agent context |
| `document_type` | Enum/String | "air", "pdf", "image", "email" |
| `file_path` | String (URL) | Source file path/URL |
| `status` | Enum | "queued" → "processing" → "completed" or "failed" |
| `n8n_execution_id` | String (nullable) | N8n workflow execution ID from webhook POST response |
| `n8n_workflow_id` | String (nullable) | N8n workflow identifier |
| `extraction_result` | JSON | Stores complete callback payload (nested extraction data) |
| `processing_duration_ms` | Integer (nullable) | How long n8n took to process |
| `error_code` | String (nullable) | Error code if status = "failed" |
| `error_message` | String (nullable) | Human-readable error if failed |
| `created_at` | Timestamp | Document upload time |
| `updated_at` | Timestamp | Last status update |

**Usage in this flow:**
1. Upload creates row with `status="queued"`, empty `extraction_result`
2. Callback updates row: `status="completed"`, populates `extraction_result` JSON column
3. Laravel reads `extraction_result` to populate Tasks table

---

### Tasks Table

**Purpose:** Stores processed document data as structured task records

**Key columns:**
| Column | Type | Purpose |
|--------|------|---------|
| `id` | Integer primary key | Task ID |
| `client_id` | Integer | Passenger/client |
| `agent_id` | Integer | Agent who processed |
| `company_id` | Integer | Tenant |
| `supplier_id` | Integer | Booking supplier |
| `type` | Enum | "flight", "hotel", "visa", "insurance", "tour", etc. |
| `status` | Enum | "issued", "reissued", "void", "refund" |
| `reference` | String | Booking reference (e.g., "ZC4D44") |
| `price` | Decimal | Base price |
| `tax` | Decimal | Tax amount |
| `surcharge` | Decimal | Surcharges |
| `total` | Decimal | Total amount |
| `client_name` | String | Passenger name |
| `currency` | String | Currency code (KWD, AED, etc.) |
| `additional_info` | JSON | Generic metadata |
| `created_at`, `updated_at` | Timestamps | Record lifecycle |

**Related tables (for flight details):**
- `task_flight_details` - Flight segments (flight_number, departure, arrival, class, baggage)
- `task_hotel_details` - Hotel info
- `task_visa_details` - Visa info
- etc.

**Usage in this flow:**
- After callback stored in DocumentProcessingLog, Laravel parses `extraction_result`
- Creates Task row from `extraction_result.passenger`, `financial_details`, etc.
- Creates TaskFlightDetails rows from `extraction_result.flight_details[]` array

---

## Data Flow

```
1. Laravel Upload
   POST /api/documents/process
   ↓ Creates DocumentProcessingLog (status="queued")
   ↓
2. POST to N8n Webhook
   {document_id, file_path, company_id, ...}
   ↓ N8n receives, downloads PDF, extracts data
   ↓
3. N8n Callback
   POST /api/webhooks/n8n/extraction
   {document_id, status="success", extraction_result={...}}
   ↓ Receives in Laravel
   ↓
4. Update DocumentProcessingLog
   SET status="completed"
   SET extraction_result = JSON from callback
   ↓
5. Parse & Create Task
   INSERT INTO tasks (reference, type, status, passenger, ...)
   INSERT INTO task_flight_details (flight_number, ...)
   ↓
6. Complete
   Task visible in dashboard
```

---

## Key Decisions (MVP Constraints)

| Decision | Rationale | Status |
|----------|-----------|--------|
| **No authentication** | Webhooks run in controlled environment (localhost/VPN) | ✅ Approved |
| **No request validation** | Trust n8n to send correct structure | ✅ Approved |
| **No retry logic** | Test only - no production resilience | ✅ Approved |
| **No async queuing** | Sync HTTP POST/callback is sufficient for test | ✅ Approved |
| **No rate limiting** | Single test files, not bulk | ✅ Approved |
| **Store raw extraction_result** | Don't transform - store exactly as received | ✅ Approved |
| **No error recovery** | If callback fails, document stuck in "processing" | ✅ Acceptable for MVP |

---

## Implementation Phases

### Phase 1: Upload Endpoint
**File:** `app/Http/Controllers/DocumentUploadController.php`

Create endpoint `POST /api/documents/process`:
- Accept upload payload (document_id, file_path, company_id, etc.)
- Create DocumentProcessingLog record with status="queued"
- POST payload to n8n webhook URL
- Return `{status: "queued", document_id: "..."}`

**Test command:**
```bash
php artisan tinker
>>> $doc = DocumentProcessingLog::create([...]);
>>> // Verify in database
>>> \DB::table('document_processing_logs')->latest()->first();
```

---

### Phase 2: N8n Webhook Configuration
**Outside Laravel** - Configure in n8n UI

Setup n8n workflow to:
1. Receive webhook POST with upload payload
2. Download file from file_path URL
3. Extract structured data (use n8n's AI integration)
4. POST callback to Laravel with extraction_result

**Webhook URL format:**
```
https://development.citycommerce.group/api/webhooks/n8n/extraction
```

---

### Phase 3: Webhook Receiver
**File:** `app/Http/Controllers/WebhookController.php`

Create endpoint `POST /api/webhooks/n8n/extraction`:
- Accept callback payload
- Find DocumentProcessingLog by document_id
- Update: status="completed", extraction_result=JSON from callback
- Return `{success: true}`

**Test command:**
```bash
curl -X POST http://localhost:8000/api/webhooks/n8n/extraction \
  -H "Content-Type: application/json" \
  -d '{"document_id": "...", "status": "success", "extraction_result": {...}}'
```

---

### Phase 4: Parse & Store in Tasks
**File:** `app/Services/DocumentExtractionService.php`

Create service to:
- Accept DocumentProcessingLog
- Read extraction_result JSON
- Map to Task table columns
- Create Task + TaskFlightDetails records

**Example logic:**
```php
$log = DocumentProcessingLog::find($document_id);
$result = json_decode($log->extraction_result, true);

Task::create([
    'client_id' => /* lookup by passenger name */,
    'reference' => $result['reference'],
    'type' => $result['type'],
    'status' => $result['status'],
    'price' => $result['financial_details']['price'],
    // ... map all fields
]);

foreach ($result['flight_details'] as $flight) {
    TaskFlightDetail::create([
        'task_id' => $task->id,
        'flight_number' => $flight['flight_number'],
        // ... map flight fields
    ]);
}
```

---

## Testing Checklist

When ready to test end-to-end:

- [ ] DocumentProcessingLog table exists & migrations run
- [ ] Upload endpoint accepts POST payload, creates log record
- [ ] Manual curl to n8n webhook confirms POST works
- [ ] N8n workflow configured, can download files
- [ ] Webhook receiver accepts callback POST
- [ ] Webhook updates DocumentProcessingLog with extraction_result
- [ ] Task creation logic parses extraction_result correctly
- [ ] Tasks visible in dashboard/database after full flow

---

## Files to Create/Modify

| File | Purpose |
|------|---------|
| `app/Http/Controllers/DocumentUploadController.php` | Phase 1 - Upload endpoint |
| `app/Models/DocumentProcessingLog.php` | Model for logging table |
| `app/Http/Controllers/WebhookController.php` | Phase 3 - Callback receiver |
| `app/Services/DocumentExtractionService.php` | Phase 4 - Parse & store |
| `routes/api.php` | Register endpoints |
| `database/migrations/` | Create DocumentProcessingLog table (if not exists) |
| `tests/Feature/DocumentUploadTest.php` | Feature tests for flow |

---

## Quick Reference: Copy-Paste Payloads

### Test Upload:
```bash
curl -X POST http://localhost:8000/api/documents/process \
  -H "Content-Type: application/json" \
  -d '{
    "document_id": "550e8400-e29b-41d4-a716-446655440000",
    "file_path": "http://development.citycommerce.group/storage/documents/sample.pdf",
    "company_id": 1,
    "supplier_id": 5,
    "agent_id": 2,
    "document_type": "air"
  }'
```

### Test Callback:
```bash
curl -X POST http://localhost:8000/api/webhooks/n8n/extraction \
  -H "Content-Type: application/json" \
  -d '{
    "document_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "success",
    "extraction_result": {
      "reference": "ZC4D44",
      "type": "flight",
      "status": "reissued",
      "passenger": {"name": "Mr. Sanjai"},
      "financial_details": {"currency": "KWD", "price": 75.7, "tax": 2, "surcharge": 3.4, "total": 24.15},
      "supplier_information": {"supplier": "Fly Dubai", "pay_date": "2025-08-19"},
      "flight_details": [{"flight_number": "FZ444", "class": "Economy", "departure": {"airport": "Unknown", "time": "06:45"}, "arrival": {"airport": "Unknown", "time": "09:25"}, "baggage": {"hand": "7 kg included", "checked": "30 kg"}, "farebase": "75.7"}]
    }
  }'
```

---

## When to Resume

**If user says:**
- "bring up our n8n topic"
- "continue with the n8n integration"
- "let's build the n8n endpoints"
- "implement the simple test we discussed"

**Then:**
1. Read this file
2. Refer to the decision log and exact payloads
3. Start from Phase 1 (or where you left off)
4. Use copy-paste payloads for testing

---

**Document Status:** ✅ Ready for future reference
**Last Updated:** 2026-03-09
**Next Step:** Awaiting user signal to implement phases
