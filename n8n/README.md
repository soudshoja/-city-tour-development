# N8n Document Extraction Workflow

**Project:** Soud Laravel - N8n Integration Phase 2
**Status:** Wave 2 Completed (Document Extraction Nodes)
**Date:** 2026-02-10

---

## Overview

This directory contains the complete N8n workflow for document processing, including extraction nodes for PDF, image, email, and AIR documents. The workflow integrates with Laravel via secure webhooks using HMAC-SHA256 authentication.

---

## Directory Structure

```
n8n/
├── workflows/
│   ├── supplier-document-processing.json           # Main workflow (Wave 2 complete)
│   └── supplier-document-processing.backup.json    # Pre-Wave 2 backup
├── nodes/
│   ├── pdf-processor.json                          # Tika PDF extraction pipeline
│   ├── image-processor.json                        # Gutenberg OCR pipeline
│   ├── email-processor.json                        # Gmail API pipeline
│   ├── air-processor.json                          # AIR fallback handler
│   └── schema-normalizer.json                      # Task schema normalization
├── credentials/
│   └── laravel-webhook-secret.json                 # HMAC secret credential
└── README.md                                       # This file
```

---

## Workflow Components

### Main Workflow: supplier-document-processing.json

**Workflow ID:** supplier-document-processing
**Webhook Path:** `/webhook/supplier-document-processing`
**Response:** 202 Accepted (asynchronous processing)

**Node Count:** 18 nodes
1. Webhook Trigger
2. Validate HMAC
3. Route by Supplier (Switch)
4. AIR Processor
5. Read PDF File
6. Extract PDF with Tika
7. Parse Tika Response
8. Extract Image with Gutenberg
9. Parse Gutenberg Response
10. Gmail Fetch Email Content (future)
11. Parse Email Response (future)
12. Error Handler - Unmapped Supplier
13. Merge Extraction Results
14. Normalize to Task Schema
15. Compute Callback HMAC
16. Send to Laravel Callback

**Execution Flow:**
```
Webhook → HMAC → Router → [AIR|PDF|Image|Email] → Merge → Normalize → Callback
```

---

## Extraction Processors

### 1. PDF Processor (Tika)

**Service:** Apache Tika Server
**URL:** http://tika-server:9998/tika
**Method:** PUT (binary upload)
**Timeout:** 60 seconds

**Pipeline:**
1. Read PDF file from Laravel storage
2. Send binary data to Tika
3. Tika extracts plain text
4. Parse and validate response
5. Return structured JSON

**Supported Suppliers:**
- Supplier ID 3: ETA UK
- Supplier ID 4: The Skyrooms
- Supplier ID 8: VFS Global

### 2. Image Processor (Gutenberg OCR)

**Service:** Gutenberg OCR Server (Tesseract wrapper)
**URL:** http://gutenberg-server:8080/ocr
**Method:** POST (file path)
**Timeout:** 90 seconds

**Pipeline:**
1. Send file path to Gutenberg
2. Tesseract performs OCR
3. Returns text + confidence score
4. Parse and validate response
5. Return structured JSON

**Supported Suppliers:**
- Supplier ID 12: Image Upload

### 3. Email Processor (Gmail API)

**Service:** Google Gmail API
**Authentication:** OAuth2
**Scope:** gmail.readonly

**Pipeline:**
1. Fetch email by message ID
2. Extract plain text body
3. Decode base64 content
4. Extract headers (subject, from, date)
5. Return structured JSON

**Supported Suppliers:**
- Supplier ID 11: Gmail

### 4. AIR Processor (Laravel Fallback)

**Method:** Deferred processing
**Fallback:** Laravel AirFileParser skill

**Pipeline:**
1. Return extraction_status='deferred'
2. Laravel receives callback
3. Laravel triggers AirFileParser
4. AirFileParser processes file
5. Updates database directly

**Supported Suppliers:**
- Supplier ID 1: Jazeera Airways
- Supplier ID 2: FlyDubai
- Supplier ID 5: Air Arabia
- Supplier ID 6: Indigo
- Supplier ID 7: Cham Wings

---

## Deployment Instructions

### Prerequisites

1. **N8n Installed:**
   - N8n version 1.x or higher
   - Docker or self-hosted installation

2. **Laravel Configured:**
   - Plan 02-01 endpoints deployed
   - N8N_WEBHOOK_URL and N8N_WEBHOOK_SECRET configured

3. **Docker Services:**
   - Tika server (see docker-compose.extraction-services.yml)
   - Gutenberg server (see docker-compose.extraction-services.yml)

### Step 1: Deploy Extraction Services

```bash
cd /home/soudshoja/soud-laravel
docker-compose -f docker-compose.extraction-services.yml up -d

# Verify services
docker ps | grep -E 'tika|gutenberg'
curl http://localhost:9998/tika
curl http://localhost:8080/health
```

### Step 2: Import Workflow to N8n

1. Open N8n web interface: http://localhost:5678 (or your N8n URL)
2. Navigate to **Workflows** → **Import from File**
3. Upload: `/home/soudshoja/soud-laravel/n8n/workflows/supplier-document-processing.json`
4. Workflow imported with name: "Supplier Document Processing"

### Step 3: Configure Credentials

#### Laravel Webhook Secret

1. Navigate to **Credentials** → **Add Credential**
2. Select **Generic Credential** type
3. Name: "Laravel Webhook Secret"
4. Add field:
   - Name: `secret`
   - Type: String (sensitive)
   - Value: (must match Laravel `N8N_WEBHOOK_SECRET` in .env)
5. Save credential (ID: 1)

#### Gmail OAuth2 (Optional - for Email Processor)

1. Navigate to **Credentials** → **Add Credential**
2. Select **Gmail OAuth2 API**
3. Follow N8n OAuth2 flow to authorize
4. Scope required: `https://www.googleapis.com/auth/gmail.readonly`
5. Save credential

### Step 4: Update Node Credentials

1. Open workflow in editor
2. Edit **Validate HMAC** node
   - Select credential: "Laravel Webhook Secret" (ID: 1)
3. Edit **Compute Callback HMAC** node
   - Select credential: "Laravel Webhook Secret" (ID: 1)
4. (If using Gmail) Edit **Fetch Email Content** node
   - Select credential: Gmail OAuth2
5. Save workflow

### Step 5: Activate Workflow

1. Click **Active** toggle (top-right)
2. Webhook URL generated: `https://your-n8n-domain.com/webhook/supplier-document-processing`
3. Copy webhook URL

### Step 6: Configure Laravel

Update Laravel `.env`:

```bash
N8N_WEBHOOK_URL=https://your-n8n-domain.com/webhook/supplier-document-processing
N8N_WEBHOOK_SECRET=your-shared-secret-here  # Must match N8n credential
```

Restart Laravel:
```bash
php artisan config:clear
php artisan cache:clear
```

---

## Testing the Workflow

### Test 1: PDF Document

```bash
# Create test PDF
echo "Test PDF with booking PNR ABC123 passenger JOHN DOE" > /home/soudshoja/soud-laravel/storage/app/test/sample.pdf

# Queue via Laravel
curl -X POST http://127.0.0.1:8000/api/documents/process \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": 1,
    "supplier_id": 3,
    "document_type": "pdf",
    "file_path": "test/sample.pdf",
    "file_size_bytes": 1024,
    "file_hash": "abc123"
  }'
```

**Expected Result:**
- N8n receives webhook
- Tika extracts text
- Schema normalizer finds PNR=ABC123, passenger=JOHN DOE
- Callback to Laravel with extraction_result

### Test 2: Image Document

```bash
# Create test image (requires ImageMagick)
convert -size 400x200 -pointsize 20 -gravity center \
  label:"Booking PNR: XYZ789\nPassenger: JANE SMITH" \
  /home/soudshoja/soud-laravel/storage/app/test/sample.png

# Queue via Laravel
curl -X POST http://127.0.0.1:8000/api/documents/process \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": 1,
    "supplier_id": 12,
    "document_type": "image",
    "file_path": "test/sample.png",
    "file_size_bytes": 5000,
    "file_hash": "def456"
  }'
```

**Expected Result:**
- N8n receives webhook
- Gutenberg OCR extracts text
- Confidence score returned
- Callback with OCR result

### Test 3: AIR Document (Deferred)

```bash
# Queue AIR document
curl -X POST http://127.0.0.1:8000/api/documents/process \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": 1,
    "supplier_id": 5,
    "document_type": "air",
    "file_path": "test/sample.air",
    "file_size_bytes": 2048,
    "file_hash": "ghi789"
  }'
```

**Expected Result:**
- N8n receives webhook
- AIR processor returns extraction_status='deferred'
- Callback to Laravel immediately
- Laravel triggers AirFileParser skill

---

## Monitoring & Debugging

### N8n Execution Logs

1. Open N8n web interface
2. Navigate to **Executions**
3. Filter by workflow: "Supplier Document Processing"
4. Click execution ID to view details
5. Inspect node outputs and errors

### Docker Logs

**Tika Server:**
```bash
docker logs -f soud-laravel-tika
```

**Gutenberg Server:**
```bash
docker logs -f soud-laravel-gutenberg
```

### Laravel Logs

```bash
tail -f /home/soudshoja/soud-laravel/storage/logs/laravel.log
```

### Database Inspection

```sql
-- View recent document processing logs
SELECT document_id, supplier_id, status, extraction_result
FROM document_processing_logs
ORDER BY created_at DESC
LIMIT 10;

-- View failed extractions
SELECT document_id, error_code, error_message
FROM document_processing_logs
WHERE status = 'failed'
ORDER BY created_at DESC;
```

---

## Error Handling

### Common Errors

**ERR_HMAC_INVALID:**
- **Cause:** Signature mismatch or expired timestamp
- **Fix:** Verify N8N_WEBHOOK_SECRET matches in Laravel and N8n credential

**ERR_FILE_NOT_FOUND:**
- **Cause:** Document file missing from storage
- **Fix:** Check file_path in Laravel storage/app/

**ERR_INSUFFICIENT_DATA:**
- **Cause:** No text extracted from document
- **Fix:** Verify document is valid (not empty, not corrupted)

**ERR_SERVICE_UNAVAILABLE:**
- **Cause:** Tika or Gutenberg server down
- **Fix:** Restart extraction services:
  ```bash
  docker-compose -f docker-compose.extraction-services.yml restart
  ```

### Error Recovery

All extraction nodes have `continueOnFail: true` to ensure:
- Errors are caught and formatted
- Laravel receives error callbacks
- Workflow doesn't halt on single document failure

---

## Security

### HMAC Authentication

**Algorithm:** HMAC-SHA256
**Secret:** Shared between Laravel and N8n (stored in N8n credentials)

**Request Signing (Laravel → N8n):**
```javascript
signature = HMAC-SHA256(secret, request_body)
X-Signature header = signature_hex
```

**Callback Signing (N8n → Laravel):**
```javascript
signature = HMAC-SHA256(secret, callback_body)
X-Signature header = signature_hex
```

**Replay Attack Protection:**
- X-Timestamp header included
- Requests older than 5 minutes rejected

### Volume Mounting Security

**Docker volumes:**
- Laravel storage mounted read-only: `:ro`
- Prevents N8n/Tika/Gutenberg from modifying source files
- Extraction services can only read files, not write

---

## Performance Tuning

### Timeouts

- **PDF Extraction (Tika):** 60 seconds
- **Image OCR (Gutenberg):** 90 seconds
- **Gmail API:** 30 seconds (N8n default)
- **Laravel Callback:** 10 seconds with 3 retries

### Resource Limits

**Tika Server:**
- CPU: 0.5-2.0 cores
- Memory: 512M-2G

**Gutenberg Server:**
- CPU: 0.5-2.0 cores
- Memory: 512M-2G

Adjust in `docker-compose.extraction-services.yml` under `deploy.resources`.

### Parallel Processing

Phase 3 will add parallel processing for:
- Multi-page PDFs (split → process → merge)
- Batch document queues
- Multiple supplier streams

---

## Phase 2 Limitations

1. **Simple Heuristic Extraction:**
   - Keyword matching only
   - Limited to flight bookings
   - No semantic understanding

2. **AIR Files Deferred:**
   - AIR processing requires Laravel AirFileParser
   - Round-trip callback overhead

3. **No Confidence Thresholds:**
   - All extractions flagged for manual review
   - No auto-approval logic

4. **Single Language:**
   - English only
   - No multi-language OCR

---

## Phase 3 Roadmap

**Planned enhancements:**

1. **GPT-4o Integration:**
   - Replace keyword matching with AI parsing
   - Extract complex data: prices, dates, itineraries
   - Multi-language support

2. **Native AIR Parsing:**
   - Port AirFileParser to N8n
   - Eliminate deferred processing

3. **Advanced OCR:**
   - Multi-language Tesseract models
   - Document layout analysis
   - Table extraction

4. **Quality Checks:**
   - Confidence thresholds
   - Data validation rules
   - Auto-approval for high-confidence extractions

5. **Performance:**
   - Parallel processing
   - Caching
   - Batch operations

---

## Support & Documentation

**Related Documentation:**
- `/home/soudshoja/.claude/projects/soud-laravel/.planning/phases/02-integration/02-03-SUMMARY.md`
- `/home/soudshoja/.claude/projects/soud-laravel/.planning/artifacts/WEBHOOK_CONTRACT.md`
- `/home/soudshoja/.claude/projects/soud-laravel/.planning/artifacts/N8N_PATTERN_ANALYSIS.md`

**Contact:**
- GitHub Issues: (project repository)
- Internal Wiki: (project documentation)

---

**Last Updated:** 2026-02-10
**Version:** 2.0 (Wave 2 - Document Extraction Nodes)
