# Wave 2 Deployment Guide: Document Extraction Nodes

**Project:** Soud Laravel N8n Integration
**Phase:** 02-Integration, Wave 2
**Date:** 2026-02-10

---

## Quick Start

This guide provides step-by-step instructions to deploy Wave 2 document extraction nodes.

---

## Prerequisites Checklist

- [x] Wave 1 completed (Laravel webhooks + N8n foundation)
- [ ] Docker installed and running
- [ ] N8n accessible (web interface)
- [ ] Laravel environment configured
- [ ] File storage path: `/home/soudshoja/soud-laravel/storage/app/`

---

## Deployment Steps

### 1. Deploy Extraction Services (5 minutes)

```bash
cd /home/soudshoja/soud-laravel

# Start Tika and Gutenberg servers
docker-compose -f docker-compose.extraction-services.yml up -d

# Verify services started
docker ps | grep -E 'tika|gutenberg'

# Check health
curl http://localhost:9998/tika
# Expected: Returns Tika server info

curl http://localhost:8080/health
# Expected: {"status": "ok"}
```

**If services fail to start:**
```bash
# Check logs
docker logs soud-laravel-tika
docker logs soud-laravel-gutenberg

# Restart
docker-compose -f docker-compose.extraction-services.yml restart
```

### 2. Import N8n Workflow (3 minutes)

1. Open N8n: http://localhost:5678 (or your N8n URL)
2. Navigate: **Workflows** → **Import from File**
3. Select file: `/home/soudshoja/soud-laravel/n8n/workflows/supplier-document-processing.json`
4. Click **Import**
5. Workflow appears: "Supplier Document Processing"

### 3. Configure N8n Credentials (5 minutes)

#### Laravel Webhook Secret

1. Navigate: **Credentials** → **Add Credential**
2. Type: **Generic Credential**
3. Name: `Laravel Webhook Secret`
4. Fields:
   - **Key:** `secret`
   - **Type:** String (mark as sensitive)
   - **Value:** Copy from Laravel `.env` → `N8N_WEBHOOK_SECRET`
5. Save (note credential ID, should be 1)

#### Update Workflow Nodes

1. Open workflow: "Supplier Document Processing"
2. Edit node: **Validate HMAC**
   - Credentials: Select "Laravel Webhook Secret"
3. Edit node: **Compute Callback HMAC**
   - Credentials: Select "Laravel Webhook Secret"
4. Save workflow

### 4. Activate N8n Workflow (2 minutes)

1. In workflow editor, click **Active** toggle (top-right)
2. Status changes to green: Active
3. Webhook URL displayed: `https://your-n8n-domain.com/webhook/supplier-document-processing`
4. Copy webhook URL

### 5. Update Laravel Configuration (2 minutes)

Edit `/home/soudshoja/soud-laravel/.env`:

```bash
# Update N8n webhook URL (if changed)
N8N_WEBHOOK_URL=https://your-n8n-domain.com/webhook/supplier-document-processing

# Verify webhook secret matches N8n credential
N8N_WEBHOOK_SECRET=your-shared-secret-here
```

Clear Laravel cache:
```bash
cd /home/soudshoja/soud-laravel
php artisan config:clear
php artisan cache:clear
```

### 6. Test End-to-End (10 minutes)

#### Test PDF Extraction

```bash
cd /home/soudshoja/soud-laravel

# Create test PDF
mkdir -p storage/app/test
echo "Flight booking confirmation PNR ABC123 passenger JOHN DOE" > storage/app/test/sample.pdf

# Queue document
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

**Expected Response:**
```json
{
  "document_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "queued",
  "message": "Document queued for processing"
}
```

**Verify in N8n:**
1. Open N8n → **Executions**
2. Find execution for document_id
3. Check nodes:
   - Webhook Trigger: Received
   - Validate HMAC: Passed
   - Route by Supplier: Output 2 (ETA UK - PDF)
   - Read PDF File: Success
   - Extract PDF with Tika: Success
   - Parse Tika Response: Text extracted
   - Normalize to Task Schema: PNR=ABC123, passenger=JOHN DOE
   - Send to Laravel Callback: Success

**Verify in Laravel:**
```sql
SELECT document_id, status, extraction_result
FROM document_processing_logs
WHERE document_id = '550e8400-e29b-41d4-a716-446655440000';
```

Expected status: `completed`
Expected extraction_result: JSON with tasks array

#### Test Image OCR

```bash
# Create test image (requires ImageMagick)
convert -size 400x200 -pointsize 20 -gravity center \
  label:"Booking PNR: XYZ789\nPassenger: JANE SMITH" \
  storage/app/test/sample.png

# Queue document
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

**Verify OCR extraction in N8n and Laravel database.**

#### Test AIR Deferred

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

**Verify:**
- N8n execution shows extraction_status='deferred'
- Laravel receives callback with deferred status
- Laravel triggers AirFileParser (Plan 02-04 implementation)

---

## Troubleshooting

### Error: Tika server unavailable

**Symptom:** N8n execution shows ERR_SERVICE_UNAVAILABLE

**Fix:**
```bash
# Check if Tika is running
docker ps | grep tika

# Restart Tika
docker restart soud-laravel-tika

# Test Tika endpoint
curl http://localhost:9998/tika
```

### Error: HMAC signature invalid

**Symptom:** N8n execution shows ERR_HMAC_INVALID

**Fix:**
1. Verify N8N_WEBHOOK_SECRET in Laravel `.env`
2. Verify "Laravel Webhook Secret" credential in N8n
3. Ensure both match exactly (case-sensitive)
4. Clear Laravel cache: `php artisan config:clear`

### Error: File not found

**Symptom:** N8n execution shows ERR_FILE_NOT_FOUND

**Fix:**
1. Check file exists: `ls /home/soudshoja/soud-laravel/storage/app/test/sample.pdf`
2. Verify file_path in webhook payload matches actual path
3. Check Docker volume mount in docker-compose.extraction-services.yml

### Error: No text extracted

**Symptom:** N8n execution shows ERR_INSUFFICIENT_DATA

**Fix:**
1. Verify PDF/image is valid (not empty, not corrupted)
2. For PDF: Try opening in PDF reader
3. For image: Try viewing in image viewer
4. Check Tika/Gutenberg logs for extraction errors

---

## Rollback Procedure

If Wave 2 deployment fails, rollback to Wave 1:

```bash
cd /home/soudshoja/soud-laravel

# Stop extraction services
docker-compose -f docker-compose.extraction-services.yml down

# Restore N8n workflow
# In N8n UI:
# 1. Delete "Supplier Document Processing" workflow
# 2. Import: n8n/workflows/supplier-document-processing.backup.json
# 3. Activate workflow

# Laravel remains unchanged (backward compatible)
```

---

## Monitoring

### Dashboard Metrics (Phase 3)

Track these metrics:
- Documents processed: Total, by supplier, by type
- Success rate: % successful extractions
- Processing time: Average, P95, P99
- Error rate: By error code
- Manual review rate: % pending_review

### Real-Time Monitoring

**N8n Executions:**
- http://localhost:5678/executions
- Filter by workflow
- Sort by newest first

**Laravel Logs:**
```bash
tail -f storage/logs/laravel.log | grep -E 'DocumentProcessing|N8nCallback'
```

**Docker Service Logs:**
```bash
# Tika
docker logs -f soud-laravel-tika

# Gutenberg
docker logs -f soud-laravel-gutenberg
```

---

## Next Steps

After Wave 2 deployment:

1. **Phase 2 Complete:**
   - All 4 document types supported
   - Extraction working end-to-end
   - Callbacks to Laravel

2. **Plan 02-04: Integration Testing & Dashboard**
   - Create comprehensive test suite
   - Build admin dashboard for monitoring
   - Add manual review UI
   - Performance benchmarking

3. **Phase 3 Planning:**
   - GPT-4o integration for complex parsing
   - Native AIR parsing in N8n
   - Confidence scoring and auto-approval
   - Multi-language support

---

## Support

**Documentation:**
- Wave 2 Summary: `.planning/phases/02-integration/02-03-SUMMARY.md`
- N8n README: `n8n/README.md`
- Webhook Contract: `.planning/artifacts/WEBHOOK_CONTRACT.md`

**Deployment Issues:**
- Check logs: N8n executions, Docker logs, Laravel logs
- Verify configuration: credentials, environment variables
- Test services: curl health checks

---

**Deployment Time Estimate:** 30 minutes (including testing)
**Complexity:** Medium (Docker + N8n configuration)
**Risk:** Low (rollback available, backward compatible)

---

**Status:** Ready for deployment
**Version:** 2.0 (Wave 2 Complete)
**Date:** 2026-02-10
