# N8N Integration Research - Soud Laravel

**Researched:** 2026-03-10
**Domain:** N8N Workflow Automation, Webhook Integration, Document Processing
**Confidence:** HIGH

---

## Summary

The system uses N8N for document extraction orchestration with secure HMAC-SHA256 authenticated webhooks. Laravel handles document queuing and callback processing while N8N orchestrates extraction pipelines through external services.

---

## N8N Workflow Files

**Directory:** `n8n/`
- `workflows/supplier-document-processing.json` - Main workflow (18 nodes)
- `workflows/supplier-document-processing.backup.json` - Backup
- `nodes/pdf-processor.json` - Tika PDF extraction
- `nodes/image-processor.json` - Gutenberg OCR pipeline
- `nodes/air-processor.json` - AIR fallback handler
- `credentials/laravel-webhook-secret.json` - HMAC shared secret

**Main Workflow:** `supplier-document-processing`
- **Webhook Path:** `/webhook/supplier-document-processing`
- **Response:** 202 Accepted

---

## Webhook Routes

| Route | Controller | Purpose |
|-------|------------|---------|
| `POST /api/documents/process` | `DocumentProcessingController@store` | Queue document for N8N |
| `POST /api/webhooks/n8n/extraction` | `N8nCallbackController@handle` | Receive N8N callback |

---

## Authentication Method

**HMAC-SHA256 Signature Verification:**
- Headers: `X-Signature-SHA256`, `X-Signature-Timestamp`
- Replay protection: 5-minute timestamp tolerance
- Timing-safe comparison: `hash_equals()` / `crypto.timingSafeEqual()`
- Audit logging in `webhook_audit_logs` table

**Credential Storage:**
- Laravel: `.env` → `N8N_WEBHOOK_SECRET`
- N8N: Credential Manager → `laravelWebhookSecret`

---

## Data Exchange Format

**Outbound (Laravel → N8N):**
```json
{
  "company_id": 1,
  "supplier_id": 2,
  "document_id": "uuid",
  "document_type": "air|pdf|image|email",
  "file_path": "company_1/supplier_2/files_unprocessed/file.air",
  "callback_url": "https://app.example.com/api/webhooks/n8n/extraction",
  "timestamp": 1709500000
}
```

**Inbound (N8N → Laravel):**
```json
{
  "document_id": "uuid",
  "status": "success|error|deferred",
  "execution_id": "n8n-exec-123",
  "workflow_id": "supplier-document-processing",
  "execution_time_ms": 1500,
  "extraction_result": {...}
}
```

---

## Configuration

**Environment Variables:**
```bash
N8N_WEBHOOK_URL=https://n8n.example.com/webhook/supplier-document-processing
N8N_WEBHOOK_SECRET=your-shared-secret-here
WEBHOOK_TIMESTAMP_TOLERANCE=300
WEBHOOK_RATE_LIMITING_ENABLED=true
WEBHOOK_GLOBAL_RATE_LIMIT=100
```

**Config Files:**
- `config/services.php` - N8N webhook URL/secret
- `config/webhook.php` - HMAC settings, rate limits, timeouts

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Services/WebhookSigningService.php` | HMAC signing/verification |
| `app/Http/Middleware/VerifyWebhookSignature.php` | Request signature validation |
| `app/Http/Middleware/WebhookRateLimiter.php` | Rate limiting |
| `app/Http/Controllers/Api/DocumentProcessingController.php` | Queue documents |
| `app/Http/Controllers/Api/Webhooks/N8nCallbackController.php` | Handle callbacks |
| `app/Services/N8nErrorLogger.php` | Error categorization |
| `app/Services/N8nExecutionTracker.php` | Execution metrics |
| `app/Models/WebhookClient.php` | Webhook client model |
| `app/Models/WebhookSecret.php` | Secret management |
| `app/Models/WebhookAuditLog.php` | Audit logging |
| `app/Models/DocumentProcessingLog.php` | Processing state tracking |

---

## Docker Services

`docker-compose.extraction-services.yml`:
- **tika-server** (port 9998) - PDF text extraction
- **gutenberg-server** (port 8080) - Image OCR (Tesseract wrapper)
- Volume mount: `./storage/app:/var/www/storage/app:ro` (read-only)

---

## Supplier ID Mapping

| ID | Supplier | Type | Processing |
|----|----------|------|------------|
| 1-2, 5-7 | Airlines | AIR | Deferred to Laravel |
| 3-4, 8 | ETA/Skyrooms/VFS | PDF | Tika extraction |
| 11 | Gmail | Email | Gmail API (future) |
| 12 | Image Upload | Image | Gutenberg OCR |

---

## Error Codes

| Code | Category | Action |
|------|----------|--------|
| `ERR_HMAC_INVALID` | non-transient | Check secret config |
| `ERR_FILE_NOT_FOUND` | non-transient | Verify file path |
| `ERR_TIMEOUT` | transient | Retry with backoff |
| `ERR_N8N_UNAVAILABLE` | system | Check N8N service |

---

## Integration Pattern for Flydubai

Based on this research, the Flydubai webhook integration should follow:

### Request Format (Laravel → N8N)
```json
{
  "company_id": 1,
  "supplier_id": 9,
  "agent_id": 123,
  "document_id": "uuid",
  "document_type": "pdf",
  "file_path": "company_1/supplier_9/files_unprocessed/flydubai_invoice.pdf",
  "callback_url": "https://development.citycommerce.group/api/webhooks/n8n/flydubai",
  "timestamp": 1709500000
}
```

### Callback Format (N8N → Laravel)
```json
{
  "document_id": "uuid",
  "status": "success",
  "supplier_id": 9,
  "agent_id": 123,
  "company_id": 1,
  "extraction_result": {
    "flight_number": "FZ123",
    "departure_date": "2026-03-15",
    "passenger_name": "John Doe",
    "total_amount": 150.00,
    "currency": "KWD"
  }
}
```

---

**Research completed:** 2026-03-10