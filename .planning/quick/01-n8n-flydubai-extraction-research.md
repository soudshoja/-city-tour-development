# N8n Webhook Processing for Flydubai (supplier_id=2) Document Extraction

**Researched:** 2026-03-10
**Domain:** Document Processing, AIR File Parsing, Multi-Tenant Laravel with N8n Integration
**Confidence:** HIGH

## Summary

This research documents the current Flydubai document processing flow and identifies gaps in the N8n webhook integration for automated AIR file extraction.

**Current State:** Flydubai files (supplier_id=2) are processed using AIR format (Amadeus GDS standard) via the `AirFileParser` service in Laravel. The N8n webhook workflow exists but currently **defers AIR processing to Laravel** with a `pending_air_processing` status.

**Primary Recommendation:** The N8n webhook for Flydubai should fetch the AIR file from Laravel storage, pass it to Laravel's `AirFileParser` service (via CLI command or shared library), and return the parsed task schema. This maintains the existing AIR parsing logic while leveraging N8n's orchestration capabilities.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel | 11.x | PHP framework for API/webhook endpoints | Project standard, documented in CLAUDE.md |
| AirFileParser | Local | Parse AIR files (Amadeus GDS format) | Custom parser, 1690+ lines, handles all AIR file variations |
| N8n | Current | Workflow automation and webhook orchestration | Existing infrastructure in `n8n/` directory |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel HTTP Client | Built-in | Make HTTP requests to N8n webhook | DocumentProcessingController uses this |
| HMAC SHA256 | Built-in | Sign payloads for security | Used in both Laravel and N8n for callback verification |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Deferred processing | Full AIR parsing in N8n | Higher maintenance, must replicate regex patterns |

**Installation:**
No additional installation required. Uses existing Laravel and N8n infrastructure.

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Services/
│   ├── AirFileParser.php      # AIR file parsing (regex-based)
│   └── AirFileService.php     # Business logic wrapper for parsing
├── Models/
│   ├── DocumentProcessingLog.php  # Tracks N8n processing state
│   └── Supplier.php           # Supplier configuration
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── DocumentProcessingController.php  # Queue files for N8n
│   │   │   └── Webhooks/
│   │   │       └── N8nCallbackController.php     # Handle N8n callbacks
n8n/
├── workflows/
│   └── supplier-document-processing.json       # Main workflow
└── nodes/
    ├── air-processor.json                      # AIR processing node
    └── schema-normalizer.json                  # Normalize extraction results
```

### Pattern 1: Document Processing Flow
**What:** Queue document for N8n processing via Laravel API, receive callback with results
**When to use:** For automated document extraction with external processing service
**Example:**
```php
// Laravel: DocumentProcessingController@store
$payload = [
    'company_id' => $validated['company_id'],
    'supplier_id' => $validated['supplier_id'],
    'document_id' => $documentId,
    'document_type' => $validated['document_type'],
    'file_path' => $validated['file_path'],
    'callback_url' => route('api.webhooks.n8n.callback'),
];

// POST to N8n webhook
$response = Http::timeout(10)->post($n8nWebhookUrl, $payload);
```

**Source:** `app/Http/Controllers/Api/DocumentProcessingController.php`

### Pattern 2: HMAC Signature Verification
**What:** Verify webhook payloads using HMAC SHA256 to prevent tampering
**When to use:** All webhook communications between Laravel and N8n
**Example:**
```javascript
// N8n: Validate HMAC node
const crypto = require('crypto');

const body = JSON.stringify($input.all()[0].json.body);
const computedSignature = crypto
  .createHmac('sha256', webhookSecret)
  .update(body)
  .digest('hex');

if (crypto.timingSafeEqual(Buffer.from(providedSignature), Buffer.from(computedSignature))) {
  return $input.all()[0].json.body;
}
```

**Source:** `n8n/workflows/supplier-document-processing.json`

### Pattern 3: Callback with Extraction Results
**What:** N8n sends processed results back to Laravel with HMAC signature
**When to use:** When extraction is complete in N8n
**Example:**
```javascript
// N8n: Compute Callback HMAC node
const callbackPayload = {
  document_id: $json.document_id,
  status: $json.extraction_status || 'error',
  extraction_result: $json.extraction_result || null,
};

const body = JSON.stringify(callbackPayload);
const hmac = crypto.createHmac('sha256', webhookSecret).update(body).digest('hex');

return {
  json: { ...callbackPayload, hmac: hmac }
};
```

**Source:** `n8n/workflows/supplier-document-processing.json`

### Anti-Patterns to Avoid
- **Building custom AIR parsing in N8n:** The Laravel `AirFileParser` uses complex regex patterns (1690+ lines) that handle edge cases. Replicating this in N8n would be high-maintenance.
- **Not using DocumentProcessingLog:** Tracking document state is critical for reconciliation. Do not bypass the logging table.
- **Missing error handling:** Always handle N8n webhook failures and callback timeouts.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| AIR file parsing | Custom N8n parser | `AirFileParser.php` | Complex regex patterns handle 15+ AIR file variants, currency conversions, multi-passenger |
| Webhook security | Basic auth/token | HMAC SHA256 | Already implemented, timing-safe comparison prevents replay attacks |
| Document tracking | Custom logging | `DocumentProcessingLog` model | Provides audit trail, status tracking, error context |
| File storage paths | Hardcoded paths | Laravel `storage_path()` | Works with containerized deployments and cloud storage |

**Key insight:** AIR parsing requires regex expertise for Amadeus GDS format. Keep the parser in Laravel, use N8n for orchestration and file retrieval.

## Common Pitfalls

### Pitfall 1: File Path Mismatch Between Laravel and N8n
**What goes wrong:** N8n tries to read files using a path that doesn't exist in its container
**Why it happens:** Laravel stores files at `storage/app/`, but N8n may use a different base path
**How to avoid:** Use absolute paths in webhook payload and ensure volume mounts align. Example:
```json
{
  "file_path": "/var/www/storage/app/company_1/supplier_2/files_unprocessed/file.air"
}
```
**Warning signs:** `ERR_FILE_NOT_FOUND` in N8n logs, "File not found" in callback error

### Pitfall 2: AIR File Format Variations
**What goes wrong:** N8n receives an AIR file that doesn't match expected format, causing parse failures
**Why it happens:** Different suppliers have slightly different AIR file formats (TBO, Magic Holiday, Amadeus)
**How to avoid:** The Laravel `AirFileParser` handles variations internally. N8n should delegate parsing to Laravel via `php artisan app:process-files`.
**Warning signs:** Partial data in extraction_result, missing flight segments

### Pitfall 3: Callback URL Not Accessible
**What goes wrong:** N8n cannot reach Laravel's callback endpoint
**Why it happens:** Laravel running on localhost, N8n on different network, or firewall blocking
**How to avoid:** Ensure callback_url uses accessible hostname/IP. For local development, use ngrok or similar.
**Warning signs:** `ERR_CONNECTION_REFUSED`, callbacks never received

### Pitfall 4: HMAC Signature Mismatch
**What goes wrong:** Laravel rejects N8n callback with `401 Unauthorized`
**Why it happens:** Webhook secret differs between Laravel config and N8n credentials
**How to avoid:** Store webhook secret in `.env` and N8n credentials manager with identical values.
**Warning signs:** `ERR_HMAC_INVALID` in logs, callback validation failures

## Code Examples

### Laravel Webhook Payload (to N8n)
```php
// DocumentProcessingController@store
$payload = [
    'company_id' => $validated['company_id'],
    'supplier_id' => $validated['supplier_id'],
    'document_id' => $documentId,
    'document_type' => $validated['document_type'],  // 'air' for Flydubai
    'file_path' => $validated['file_path'],          // Absolute path
    'file_size_bytes' => $validated['file_size_bytes'],
    'file_hash' => $validated['file_hash'],
    'callback_url' => route('api.webhooks.n8n.callback'),
    'timestamp' => now()->timestamp,
];
```

### N8n AIR Fallback Response (from N8n to Laravel)
```json
{
  "document_id": "uuid-here",
  "extraction_status": "deferred",
  "extraction_result": {
    "tasks": [],
    "metadata": {
      "extraction_method": "laravel-airfileparser-fallback",
      "document_type": "air",
      "supplier_id": 2,
      "file_path": "/var/www/storage/app/...",
      "message": "AIR file processing deferred to Laravel AirFileParser"
    },
    "deferred": true,
    "deferred_reason": "AIR format requires Laravel AirFileParser skill"
  },
  "callback_url": "http://..."
}
```

### Laravel Callback Handler
```php
// N8nCallbackController@handle
// Validates HMAC, updates DocumentProcessingLog, handles success/error

if ($validated['status'] === 'success') {
    $log->update([
        'status' => 'completed',
        'extraction_result' => $validated['extraction_result'],
        'n8n_execution_id' => $validated['execution_id'],
        'processing_duration_ms' => $validated['execution_time_ms'],
    ]);

    // Extract tasks and create Task records
    foreach ($validated['extraction_result']['tasks'] as $taskData) {
        Task::create($taskData);
    }
}
```

### AirFileParser Task Schema Output
```php
// AirFileParser::parseTaskSchema() returns array of tasks
[
    [
        'ticket_number' => 'T-K229-2833133219',
        'gds_reference' => '8DROXL',
        'status' => 'issued',
        'price' => 90.000,
        'currency' => 'KWD',
        'total' => 143.400,
        'task_flight_details' => [
            [
                'departure_time' => '2025-07-30 04:35',
                'arrival_time' => '2025-07-30 06:05',
                'airport_from' => 'KWI',
                'airport_to' => 'DOH',
                'flight_number' => 'QR1077',
            ]
        ],
        // ... all fields from TaskSchema
    ]
]
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual file processing | N8n webhook orchestration | Phase 2 (2025) | Automated extraction with external processing |
| No document tracking | DocumentProcessingLog table | Phase 2 Wave 1 | Full audit trail, status tracking |
| Laravel-only processing | Hybrid Laravel+N8n | Phase 2 (ongoing) | Scalable, separates orchestration from parsing |

**Deprecated/outdated:**
- `n8n/workflows/supplier-document-processing.backup.json` - Uses placeholder nodes, not for production

## Open Questions

1. **How should N8n fetch files from Laravel storage?**
   - What we know: N8n needs file content for AIR parsing, but current workflow uses `Read Binary File` node with hardcoded path
   - What's unclear: Should Laravel provide a signed URL endpoint, or should N8n access storage directly?
   - Recommendation: Add Laravel endpoint `GET /api/documents/{documentId}/file` that returns signed storage URL or file content with auth token.

2. **Should AIR parsing happen in N8n or defer to Laravel?**
   - What we know: Current workflow defers to Laravel, returning `extraction_status: 'deferred'`
   - What's unclear: Is deferred processing working in production? What triggers the Laravel AirFileParser after deferred status?
   - Recommendation: For Phase 1, keep deferral model. For Phase 2, implement Laravel endpoint that N8n calls with file content for parsing.

## Validation Architecture

> Note: Validation architecture section is included based on `.planning/config.json` settings.

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit (Laravel default) |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --filter N8n` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| N8N-01 | Webhook accepts document queue request | unit | `php artisan test tests/Feature/DocumentProcessingTest.php::testQueueDocumentForProcessing` | ❌ Not yet |
| N8N-02 | HMAC signature validation for incoming callbacks | unit | `php artisan test tests/Feature/N8nCallbackTest.php::testValidHmacSignature` | ❌ Not yet |
| N8N-03 | DocumentProcessingLog created on queue request | feature | `php artisan test tests/Feature/DocumentProcessingTest.php::testCreatesLogRecord` | ❌ Not yet |
| N8N-04 | AIR file deferred status handled correctly | integration | `php artisan test tests/Feature/N8nCallbackTest.php::testDeferredAirProcessing` | ❌ Not yet |
| N8N-05 | N8n webhook URL configuration | unit | `php artisan test tests/Unit/ConfigTest.php::testN8nWebhookConfig` | ✅ 2025 |

### Sampling Rate
- **Per task commit:** `php artisan test --filter DocumentProcessing\|N8nCallback`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/DocumentProcessingTest.php` — Covers queueing documents for N8n processing
- [ ] `tests/Feature/N8nCallbackTest.php` — Covers callback handling and HMAC validation
- [ ] `tests/Feature/AirFileProcessingTest.php` — Covers deferred AIR processing workflow
- [ ] `tests/CreatesApplication.php` — Shared test fixtures
- [ ] Framework install: Already present — `phpunit.xml` detected

*(Existing test infrastructure covers basic Laravel functionality, but lacks N8n-specific tests)*

## Sources

### Primary (HIGH confidence)
- `app/Services/AirFileParser.php` - AIR file parsing logic with regex patterns
- `app/Http/Controllers/Api/DocumentProcessingController.php` - Queue document for N8n processing
- `app/Http/Controllers/Api/Webhooks/N8nCallbackController.php` - Handle N8n callback
- `n8n/workflows/supplier-document-processing.json` - N8n workflow definition
- `app/Models/DocumentProcessingLog.php` - Document processing tracking model
- `database/migrations/2026_02_10_120000_create_document_processing_logs_table.php` - Database schema

### Secondary (MEDIUM confidence)
- `n8n/nodes/air-processor.json` - AIR processing node configuration
- `n8n/nodes/schema-normalizer.json` - Schema normalization logic
- `app/Http/Controllers/Api/Webhooks/N8nCallbackController.php` - Callback validation

### Tertiary (LOW confidence)
- `n8n/workflows/supplier-document-processing.backup.json` - Backup file, uses placeholders

## Metadata

**Confidence breakdown:**
- Standard Stack: HIGH - Based on code inspection of AirFileParser.php (1690+ lines), DocumentProcessingController.php, N8n workflow JSON
- Architecture: HIGH - Clear separation of concerns: Laravel for parsing, N8n for orchestration, DocumentProcessingLog for tracking
- Pitfalls: HIGH - Based on common integration issues (file paths, HMAC, callback URLs) observed in code

**Research date:** 2026-03-10
**Valid until:** 2026-04-10 (30 days for stable Laravel/N8n)
