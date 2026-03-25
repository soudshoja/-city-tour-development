# N8n Webhook Integration for PDF Processing - Research

**Researched:** 2026-03-09
**Domain:** Workflow Automation, PDF Processing, Webhook Integration
**Confidence:** HIGH (official n8n docs + verified community patterns)

## Summary

N8n provides a complete webhook-based PDF processing pipeline: Laravel sends POST requests to n8n webhook triggers, n8n processes PDFs using built-in Extract From File nodes or community PDF OCR packages, and sends results back via HTTP Request nodes to Laravel callback endpoints. No authentication required for testing (webhooks are URL-based tokens). Synchronous responses use the "Respond to Webhook" node. Execution time is tracked via n8n's built-in Insights feature or custom expressions.

**Primary recommendation:** Use n8n Webhook (trigger) + Extract From File (PDF processing) + HTTP Request (callback) + Respond to Webhook (synchronous response) pattern. For large PDFs or advanced OCR, consider the PDF OCR community node or Mistral OCR integration.

---

## 1. Incoming Webhook (Laravel → N8n)

### Webhook Creation

**How to create:**
1. Open n8n workflow editor
2. Add a **Webhook** node as the trigger
3. Configure HTTP method: POST
4. Set Response mode to "Using Respond to Webhook node" (if you want synchronous response)
5. Save/Deploy workflow to activate webhook

### Webhook URL Format

Default URL structure:
```
http://localhost:5678/webhook/<random-generated-path>
```

For self-hosted with custom domain:
```
https://your-domain.com/webhook/<random-generated-path>
```

You can customize the path:
- Edit the "Path" field in Webhook node settings
- Supports dynamic route parameters: `/document/:document_id/process/:supplier_id`
- Only one webhook per path + HTTP method combination

### Payload Format (What N8n Expects)

N8n expects **JSON POST** with `Content-Type: application/json`:

```json
{
  "document_id": "DOC-12345",
  "file_path": "https://storage.example.com/files/invoice.pdf",
  "company_id": "COMP-001",
  "supplier_id": "SUP-456",
  "document_type": "invoice",
  "callback_url": "https://development.citycommerce.group/api/n8n-callback",
  "file_content": "base64-encoded-string-here" // OR file_path, not both
}
```

**Key points:**
- N8n auto-parses JSON (no manual parsing needed)
- Data available in workflow via `$json.document_id`, `$json.company_id`, etc.
- Path parameters accessible via `$('Webhook').params.document_id`

### Headers to Send from Laravel

**Minimum required:**
```
Content-Type: application/json
```

**Optional but recommended:**
```
User-Agent: SoudLaravel/1.0
X-Request-ID: unique-request-id-12345
```

N8n doesn't require special auth headers for testing (webhook URL is the token).

### File Path vs. Content

**Two options:**

**Option A: Send file URL** (n8n downloads it)
```json
{
  "file_path": "https://storage.example.com/documents/invoice.pdf"
}
```
N8n can fetch via HTTP Request node before Extract From File.

**Option B: Send base64 content** (direct binary)
```json
{
  "file_content": "JVBERi0xLjQKJeLjz9... [long base64 string]"
}
```
More reliable for air-gapped systems, but larger payloads.

**Recommendation for your setup:** Send file URL + store base64 as fallback. N8n can fetch PDFs from URLs efficiently using HTTP Request node.

---

## 2. PDF Processing in N8n

### Available PDF Nodes

| Node | Purpose | Best For | Free/Commercial |
|------|---------|----------|-----------------|
| **Extract From File** | Native PDF → text extraction | Fast text extraction | Built-in (free) |
| **PDF OCR** (community) | Full OCR via Tesseract.js | Scanned PDFs, images | Free, self-hosted |
| **Mistral OCR** (integration) | Advanced OCR + table extraction | Complex documents | Commercial API |
| **Read Binary File** | Load file from disk | Local file processing | Built-in (free) |

### How N8n Handles Files

**Important:** Extract From File requires **binary data**, not file paths.

**Workflow pattern:**
```
HTTP Request (fetch PDF from URL)
  ↓ [returns binary data]
Extract From File (process binary)
  ↓ [returns JSON with text]
Set Node (format output)
  ↓
HTTP Request (send callback)
```

**Key gotchas:**
- File paths don't work—must convert to binary first
- Manual Mapping nodes discard binary data by default (use "Append" mode)
- Binary data stored as Base64 under the hood, accessible via `$binary.data`

### Common PDF Operations

**1. Extract Plain Text**
```
Input: Binary PDF
Extract From File node → Type: Text
Output: {{ $json.text }}
```

**2. Extract Structured Data (JSON-LD)**
```
Extract From File → Type: JSON (if PDF has embedded JSON)
Output: {{ $json.data }}
```

**3. Extract Images**
```
Extract From File → Type: "Binary" (extracts images as separate items)
Output: {{ $binary.data }} (for each image)
```

**4. Advanced OCR (Scanned PDFs)**
Use community **PDF OCR node** (Tesseract.js):
```
Setup: Install community node
Input: Binary PDF
Process: Tesseract.js OCR engine
Output: Text with coordinates
Languages: 100+ (English, Spanish, French, etc.)
```

**5. Table Extraction**
Use **Mistral OCR** workflow template or split PDFs by structure:
```
Extract From File → Text
Set Node → Regex/Code to parse tables
Output: Structured JSON
```

### Limitations

| Limit | Value | Workaround |
|-------|-------|-----------|
| File Size | ~16 MB (configurable) | Increase N8N_PAYLOAD_SIZE_MAX on self-hosted |
| PDF Pages | No hard limit | Process large PDFs in batches with custom code |
| Processing Time | 60 sec (default timeout) | Use async pattern: webhook → immediate response → callback |
| OCR Accuracy | ~90% (text), ~70% (handwriting) | Combine with Claude/GPT for validation |

---

## 3. Outgoing Webhook (N8n → Laravel)

### How to Send Callback to Laravel

Use **HTTP Request** node:

```
HTTP Request Node Configuration:
├─ URL: {{ $json.callback_url }}
├─ Method: POST
├─ Headers:
│  └─ Content-Type: application/json
│  └─ Authorization: Bearer YOUR_LARAVEL_API_KEY (optional for testing)
├─ Body Type: JSON
└─ Body: (see below)
```

### Callback Payload Structure

Match your Laravel `N8nCallbackController` expectations:

```json
{
  "document_id": "{{ $json.document_id }}",
  "status": "success",
  "execution_id": "{{ $execution.id }}",
  "workflow_id": "{{ $workflow.id }}",
  "execution_time_ms": "{{ ($execution.stopTime - $execution.startTime) }}",
  "extraction_result": {
    "raw_text": "{{ $node['Extract From File'].json.text }}",
    "pages": 5,
    "confidence": 0.95,
    "metadata": {
      "title": "Invoice",
      "date": "2026-03-09"
    }
  }
}
```

**Or on error:**

```json
{
  "document_id": "{{ $json.document_id }}",
  "status": "error",
  "error_message": "{{ $execution.error.message }}",
  "error_node": "{{ $execution.error.nodeName }}",
  "execution_id": "{{ $execution.id }}"
}
```

### Headers for Callback

**Simple (for testing):**
```
Content-Type: application/json
```

**Production (with auth):**
```
Content-Type: application/json
Authorization: Bearer {{ $env.LARAVEL_API_TOKEN }}
X-N8n-Workflow-ID: {{ $workflow.id }}
X-Execution-ID: {{ $execution.id }}
```

### Error Handling & Retries

**Built-in HTTP Request retry:**

```
HTTP Request node → Settings tab:
├─ Retry on Fail: ✓ enabled
├─ Max Tries: 3
├─ Delay (ms): 2000
├─ Exponential Backoff: ✓ enabled
└─ Timeout (sec): 30
```

**Graceful fallback on callback failure:**

```
HTTP Request → Add error branch →
  Send notification / Log to database / Queue for retry
```

**What if callback fails?**
- Laravel doesn't receive result
- N8n logs execution as "completed with errors"
- Configure error workflow in Workflow Settings to alert you

### Authentication for Callbacks (No HMAC Required)

For **testing phase** (no auth):
```
Just send POST to callback_url—Laravel validates based on
document_id matching + known workflow_id
```

For **production** (optional hardening):
```
Option 1: API Token in header
Authorization: Bearer {{ env variable }}

Option 2: Query parameter
callback_url?token=secret-key

Option 3: Custom header
X-N8n-Secret: {{ $env.N8N_WEBHOOK_SECRET }}

⚠️ Do NOT use HMAC in n8n yet (no built-in support).
   If needed, add Code node to generate HMAC manually.
```

---

## 4. Workflow Flow in N8n

### Basic PDF Processing Workflow

```
┌─────────────────────────────────────────────────────────┐
│ Webhook (trigger)                                       │
│ Listen for POST from Laravel                           │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ HTTP Request (fetch PDF if URL provided)               │
│ GET {{ $json.file_path }}                              │
│ [Returns binary data]                                   │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ Extract From File                                       │
│ Type: Text                                              │
│ Input Binary Field: data                                │
│ [Returns JSON with .text, .pages, .info]              │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ Set Node / Code Node (optional: parse/validate)        │
│ Transform extraction_result                             │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ Respond to Webhook (optional: immediate response)      │
│ Return 200 with status="processing"                     │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────┐
│ HTTP Request (send callback to Laravel)                │
│ POST {{ $json.callback_url }}                           │
│ With extraction_result                                  │
└────────────────┬────────────────────────────────────────┘
                 │
                 ▼
         [Workflow Complete]
```

### Data Passing Between Nodes

**Reference previous node output:**

```
Field: {{ $node['HTTP Request'].json.content }}
       or {{ $('HTTP Request').json.content }}
       or {{ $json.field }} (current node input)

Binary data: {{ $binary.data.mimeType }}
            {{ $binary.data.data }} (Base64)
```

**Common operations:**

```javascript
// Concatenate
{{ $json.document_id + "_" + $json.company_id }}

// Array access
{{ $json.items[0].name }}

// Conditional
{{ $json.status === "success" ? "done" : "failed" }}

// String methods
{{ $json.text.trim().toLowerCase() }}

// JSON path / JMESPath
{{ $json.extraction_result[0].text }}
```

### Error Handling Pattern

**Option 1: Node-level "Continue on Error"**

```
Extract From File node → Settings:
  On Error: Continue

Next node receives:
  $json with error info: { "message": "...", "code": "..." }
```

**Option 2: Conditional branching**

```
Extract From File
  │
  ├─→ Success path → HTTP Request (callback with result)
  │
  └─→ Error path → HTTP Request (callback with error)
      [Use IF/Switch node to branch]
```

**Option 3: Separate error workflow** (production)

```
Workflow Settings → Error Workflow: "Handle PDF Processing Errors"

Error workflow:
├─ Error Trigger
├─ Log error to database
├─ Send Slack alert
└─ Queue for manual review
```

### Measuring Execution Time

**Automatic tracking:**

```
Access in expressions:
{{ $execution.startTime }}     // Unix timestamp (ms)
{{ $execution.stopTime }}      // Unix timestamp (ms)
{{ ($execution.stopTime - $execution.startTime) }}  // ms

Example: Send with callback
"execution_time_ms": {{ $execution.stopTime - $execution.startTime }}
```

**View in UI:**

```
Workflow → Executions tab:
├─ Shows execution duration
├─ Per-node timing breakdown
└─ Total workflow time

OR: Insights dashboard
├─ Historical execution times
├─ Success/failure rates
├─ Average runtime trends
```

---

## 5. Testing & Debugging

### Testing Webhook Without Sending Real POST

**Method 1: Test button in editor**

```
Webhook node → Click "Test" button
├─ Generates test URL
├─ Listen for one incoming request
├─ Shows payload in editor (live preview)
└─ Use test URL to POST from curl/Postman
```

**Method 2: Mock payload in Set node**

```
Create a Set node BEFORE Webhook (for testing):
├─ Set document_id: "TEST-001"
├─ Set file_path: "https://example.com/test.pdf"
├─ Set callback_url: "https://localhost:3000/callback"

Toggle on/off for test vs. production
```

**Method 3: Local tunneling**

```bash
# Use ngrok or Tunnelmole to expose local n8n
ngrok http localhost:5678

# n8n webhook becomes:
https://random123.ngrok.io/webhook/your-path

# Post from Laravel to ngrok URL
curl -X POST https://random123.ngrok.io/webhook/your-path \
  -H "Content-Type: application/json" \
  -d '{"document_id": "TEST", ...}'
```

### Viewing Execution Logs

```
Workflow Executions tab:
├─ Click any execution row
├─ View node-by-node flow
├─ See input/output for each node
├─ Expand errors for full stack trace
└─ Export execution data as JSON

Production logs:
├─ Insights dashboard (n8n UI)
├─ File logs (self-hosted): .n8n/logs/
├─ Custom: HTTP Request to logging service
```

### Debugging Failed Webhook Calls

**Scenario: Webhook not triggering**

```
1. Check Workflow Status
   └─ Must be Active (not Draft)

2. Verify URL is correct
   └─ Copy from Webhook node → test in curl

3. Check webhook execution logs
   └─ Executions tab → look for "Webhook Trigger"

4. Test payload structure
   └─ Must be JSON with Content-Type: application/json

5. Check firewall/reverse proxy
   └─ If behind nginx: verify proxy_pass to n8n
```

**Scenario: Callback HTTP Request fails**

```
HTTP Request node failed?

1. Check status code
   └─ 200? 404? 500?

2. View response body
   └─ Click executed HTTP Request node → see response

3. Verify callback_url is reachable
   └─ curl -X POST {{ $json.callback_url }} -H "Content-Type: application/json" -d '{}'

4. Check Laravel API key/auth
   └─ If using Bearer token, verify it's valid

5. Enable HTTP Request retries
   └─ Settings → Retry on Fail: 3 times
```

---

## 6. Gotchas & Best Practices

### Common Webhook Mistakes

| Mistake | Symptom | Fix |
|---------|---------|-----|
| Workflow not active | Webhook returns 404 | Publish/Deploy workflow (Status: Active) |
| Wrong Content-Type | N8n can't parse JSON | Send `Content-Type: application/json` |
| File path vs binary | Extract From File fails | Use HTTP Request to fetch first, returns binary |
| Manual Mapping discards binary | Extract From File has no data | Use "Append" mode, not "Set" mode |
| Callback timeout | Response never arrives | Use Respond to Webhook for immediate 200, then process async |
| URL behind reverse proxy | Webhook URL incorrect | Set N8N_DOMAIN in env vars or webhook settings |

### File Size & Performance Limits

| Limit | Default | Configure | Impact |
|-------|---------|-----------|--------|
| Payload Size | 16 MB | N8N_PAYLOAD_SIZE_MAX | Requests > limit rejected |
| Workflow Timeout | 1 hour (3600s) | Workflow Settings | Execution stops, marked failed |
| HTTP Request Timeout | 30s | HTTP Request node Settings | Individual node times out |
| Respond to Webhook Timeout | 64s | N/A | Response must complete in 64s |
| PDF Page Processing | No limit | Batch large PDFs | Long-running workflows at risk |

### Large PDF Handling

**Problem:** 100+ page PDF takes >60 seconds to process.

**Solution 1: Async pattern**

```
Webhook → Respond Immediately (200 OK) →
  Background: Extract PDF → Callback with results
```

**Solution 2: Split PDF before sending**

```
Laravel: Split PDF into 10-page chunks
  ↓ (each chunk to separate webhook call)
N8n: Process each chunk faster
  ↓
Aggregate results via unique batch_id
```

**Solution 3: Use external OCR service**

```
N8n: Send PDF to Mistral OCR API (handles large files)
  ↓
Mistral returns text + structured data
  ↓
Callback to Laravel with results
```

### Webhook Security (Testing vs. Production)

**Testing (development.citycommerce.group):**
```
No auth needed—webhook URL is the token
Just keep URL secret / restrict to dev network
```

**Production (future hardening):**
```
Option 1: API key in header
├─ HTTP Request Authorization: Bearer {{ $env.LARAVEL_API_TOKEN }}
├─ Laravel validates token in middleware

Option 2: HMAC signature (manual in Code node)
├─ N8n Code node: generate HMAC-SHA256
├─ Laravel verifies signature

Option 3: IP whitelist
├─ N8n self-hosted: restrict webhook to Laravel IP
└─ N8n Cloud: configure IP allow list
```

---

## 7. Code Examples

### Example 1: Basic Webhook Node Configuration

**N8n Webhook Node:**
```
Method: POST
Path: /document/process
Response: Using Respond to Webhook node
Request Body: Raw (let n8n auto-parse JSON)
Authentication: None (for testing)
```

**Test with curl:**
```bash
curl -X POST http://localhost:5678/webhook/document/process \
  -H "Content-Type: application/json" \
  -d '{
    "document_id": "DOC-123",
    "file_path": "https://example.com/invoice.pdf",
    "callback_url": "http://localhost:8000/api/n8n-callback"
  }'
```

### Example 2: PDF Extraction Workflow (Set Node Configuration)

**After Extract From File, use Set node to format:**

```javascript
{
  "document_id": "{{ $json.document_id }}",
  "file_name": "{{ $json.file_path.split('/').pop() }}",
  "extraction": {
    "raw_text": "{{ $node['Extract From File'].json.text }}",
    "character_count": "{{ $node['Extract From File'].json.text.length }}",
    "pages": "{{ Object.keys($node['Extract From File'].json).filter(k => k.startsWith('page_')).length || 'unknown' }}",
    "extraction_completed_at": "{{ new Date().toISOString() }}"
  }
}
```

### Example 3: Error Handling with Conditional Response

**Use IF node after Extract From File:**

```
IF node condition:
{{ $node['Extract From File'].json.error === undefined }}

TRUE branch:
├─ Set node (format success result)
└─ HTTP Request (callback with extraction)

FALSE branch:
├─ Set node (format error result)
└─ HTTP Request (callback with error)
```

### Example 4: Callback HTTP Request Configuration

**HTTP Request node to Laravel:**

```
URL: {{ $json.callback_url }}
Method: POST
Headers:
  Content-Type: application/json
  Authorization: Bearer {{ $env.LARAVEL_API_KEY }} [optional]

Body (JSON):
{
  "document_id": "{{ $json.document_id }}",
  "status": "{{ $node['Extract From File'].json.error ? 'error' : 'success' }}",
  "execution_id": "{{ $execution.id }}",
  "execution_time_ms": "{{ $execution.stopTime - $execution.startTime }}",
  "result": {
    "text": "{{ $node['Extract From File'].json.text }}",
    "pages": "{{ $node['Extract From File'].json.pages || 1 }}"
  }
}

Retry on Fail: ✓ enabled
Max Tries: 3
Delay: 2000ms
Exponential Backoff: ✓ enabled
```

### Example 5: Advanced OCR with Community PDF Node

**Install community node:**

```
n8n UI → Settings → Community Nodes
Search: "pdf-ocr" → Install n8n-nodes-pdf-ocr
```

**Use in workflow:**

```
HTTP Request (fetch PDF) →
PDF OCR node:
├─ Input Binary Field: data
├─ Language: eng (or fra, ger, etc.)
├─ OCR Type: Text with Confidence
└─ Output: {{ $json.text }}, {{ $json.confidence }}

→ Set Node (format result)
→ HTTP Request (callback to Laravel)
```

---

## 8. State of the Art (2026)

| Aspect | Current Approach | What Changed | Impact |
|--------|------------------|-------------|--------|
| PDF Extraction | Built-in Extract From File node | Replaced older "Read PDF" (v1.21.0+) | Simpler, faster, native |
| OCR | Community nodes (Tesseract) or external APIs | HMAC signature verification still manual | Dev needs Code node for verification |
| Webhook Testing | Test button + live preview | No native UI for mocking | Use external tools (Postman, curl) |
| Async Callbacks | Respond to Webhook node | Still 64s timeout on response | Need async pattern for long jobs |
| Performance Tracking | Insights dashboard | New in 2026: custom dashboards | Real-time execution analytics |

**Deprecated/outdated:**
- "Read PDF" node: Replaced by Extract From File (v1.21.0+)
- Manual HMAC: Still required (no built-in support yet)
- Ngrok-only testing: Now supports test button in UI

---

## 9. Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | N/A (n8n is low-code visual, not unit-tested like traditional code) |
| Config file | none — n8n workflows are JSON exported/tested via UI |
| Quick validation | Manual POST to webhook + verify callback received |
| Full suite | End-to-end: PDF upload → extraction → callback confirmation |

### Phase Requirements → Validation Map

| Requirement | Behavior | Validation Method | Manual/Automated |
|-------------|----------|------------------|-----------------|
| Webhook receives POST | N8n Webhook node triggers | POST to webhook URL, check Executions tab | Manual (curl/Postman) |
| PDF extraction works | Extract From File produces text output | Upload test PDF, verify output in node inspector | Manual |
| Callback sent to Laravel | HTTP Request reaches Laravel endpoint | Check Laravel logs for received data | Manual |
| Error handling | Failed extractions return error status | Force error (invalid PDF), verify callback | Manual |
| Execution time tracked | execution_time_ms populated | Parse callback payload, verify timestamp fields | Manual |

### Wave 0 Gaps
- [ ] **Integration test script** — Bash/PHP script to POST sample PDF → verify callback → confirm round-trip works
- [ ] **Mock Laravel callback endpoint** — Simple endpoint that logs received payloads for testing
- [ ] **N8n workflow export** — Save finalized workflow as JSON for version control + deployment
- [ ] **Error scenarios test** — Malformed JSON, oversized files, unreachable callback URL

---

## Sources

### Primary (HIGH confidence)

- [n8n Webhook Node Documentation](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.webhook/) - Webhook trigger configuration
- [n8n Respond to Webhook Node Documentation](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.respondtowebhook/) - Synchronous response handling
- [n8n HTTP Request Node Documentation](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.httprequest/) - Callback implementation
- [n8n Extract From File Node Documentation](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.extractfromfile/) - PDF text extraction
- [n8n Binary Data Guide](https://docs.n8n.io/data/binary-data/) - File handling patterns
- [n8n Expressions Documentation](https://docs.n8n.io/code/expressions/) - Data passing syntax
- [n8n Error Handling Documentation](https://docs.n8n.io/flow-logic/error-handling/) - Error workflows

### Secondary (MEDIUM confidence)

- [n8n Webhook Tutorial (2026)](https://lumberjack.so/n8n-webhook-tutorial-trigger-workflows-from-anywhere/) - Practical webhook setup
- [Mastering n8n Webhook Node: Part A](https://automategeniushub.com/mastering-the-n8n-webhook-node-part-a/) - JSON handling and responses
- [N8n PDF Document RAG System Workflow](https://n8n.io/workflows/4400-build-a-pdf-document-rag-system-with-mistral-ocr-qdrant-and-gemini-ai/) - Advanced PDF + OCR pattern
- [N8n PDF OCR Community Node](https://ncnodes.com/package/n8n-nodes-pdf-ocr) - Tesseract.js integration
- [N8n Extract from File Node Tutorial](https://logicworkflow.com/nodes/extract-from-file-node/) - Best practices

### Tertiary (for reference)

- [n8n Webhook Common Issues](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.webhook/common-issues/) - Troubleshooting
- [n8n Performance & Benchmarking](https://docs.n8n.io/hosting/scaling/performance-benchmarking/) - Limits and scaling
- [n8n Insights Dashboard](https://docs.n8n.io/insights/) - Execution tracking

---

## N8n Setup Checklist

### Phase 1: Initial Setup
- [ ] **Install n8n** or use cloud instance (docs.n8n.io)
- [ ] **Create new workflow** in n8n editor
- [ ] **Add Webhook node** (trigger)
  - [ ] Set HTTP method to POST
  - [ ] Note the webhook URL (e.g., `/webhook/document/process`)
  - [ ] Save workflow

### Phase 2: PDF Processing
- [ ] **Add HTTP Request node** (to fetch PDF if needed)
  - [ ] URL: `{{ $json.file_path }}`
  - [ ] Test with sample PDF URL
- [ ] **Add Extract From File node**
  - [ ] Type: Text
  - [ ] Input Binary Field: `data` (from HTTP Request output)
- [ ] **Add Set node** (format extraction result)
  - [ ] Map extracted text and metadata

### Phase 3: Callback Setup
- [ ] **Add Respond to Webhook node** (optional, for immediate response)
  - [ ] Response Body: `{ "status": "processing" }`
- [ ] **Add HTTP Request node** (send callback)
  - [ ] URL: `{{ $json.callback_url }}`
  - [ ] Method: POST
  - [ ] Body: structured result with `document_id`, `status`, `extraction_result`, `execution_time_ms`
  - [ ] Retry on Fail: enabled (3 tries, 2000ms delay, exponential backoff)

### Phase 4: Error Handling
- [ ] **Add IF node** after Extract From File
  - [ ] Condition: `{{ $node['Extract From File'].json.error === undefined }}`
  - [ ] TRUE branch: success callback
  - [ ] FALSE branch: error callback
- [ ] **Test error scenario** (send invalid PDF)

### Phase 5: Testing
- [ ] **Publish workflow** (Status: Active)
- [ ] **Get webhook URL** from Webhook node
- [ ] **Test via curl/Postman**
  ```bash
  curl -X POST http://your-n8n-url/webhook/document/process \
    -H "Content-Type: application/json" \
    -d '{"document_id":"TEST-001","file_path":"https://example.com/test.pdf","callback_url":"http://localhost:3000/api/n8n-callback"}'
  ```
- [ ] **Verify execution** in Executions tab
- [ ] **Check Laravel** for received callback

### Phase 6: Production Hardening
- [ ] **Configure authentication** (API key in header or query param)
- [ ] **Set N8N_DOMAIN** env var (correct webhook URLs)
- [ ] **Configure error workflow** in Workflow Settings
- [ ] **Add logging/monitoring** (Insights dashboard)
- [ ] **Test large PDF handling** (100+ pages)
- [ ] **Set timeout appropriately** (Workflow Settings)

### Phase 7: Laravel Integration
- [ ] **Create N8nCallbackController** to receive callbacks
- [ ] **Parse callback payload** (document_id, execution_id, extraction_result)
- [ ] **Validate payload integrity** (check document_id matches)
- [ ] **Store extraction results** in database
- [ ] **Send error alerts** if callback status = "error"

---

## Open Questions for Implementation

1. **File Storage:** Will PDFs come from Laravel storage, external S3, or embedded as base64?
   - *Recommendation:* Start with URLs (simplest), add base64 fallback later

2. **OCR Accuracy:** If processing scanned documents, do you need Mistral OCR or is Tesseract sufficient?
   - *Recommendation:* Test with native Extract From File first; upgrade to Mistral if accuracy < 90%

3. **Batch Processing:** Should multiple documents be processed in parallel or sequentially?
   - *Recommendation:* Sequential first (easier debugging), optimize later if needed

4. **Callback Retry Strategy:** If Laravel is temporarily down, how many retries before giving up?
   - *Recommendation:* 3 retries with 2-5s exponential backoff; log to error workflow if all fail

5. **Authentication:** Will you implement API key auth immediately or keep it simple for testing?
   - *Recommendation:* No auth for MVP; add Bearer token auth before production deployment

---

## Metadata

**Confidence breakdown:**
- Webhook mechanics: **HIGH** (official docs verified)
- PDF extraction: **HIGH** (native node + templates available)
- Callback implementation: **HIGH** (HTTP Request node well-documented)
- Error handling: **MEDIUM** (docs describe patterns but no explicit try-catch node)
- Performance limits: **MEDIUM** (community reports vary; test with your data)

**Research date:** 2026-03-09
**Valid until:** 2026-03-30 (n8n moves fast; validate limits before production)

**Key takeaway:** N8n webhooks are production-ready for this use case. No fancy auth needed for testing. Focus on error handling (callback retries) and measuring execution time for monitoring.
