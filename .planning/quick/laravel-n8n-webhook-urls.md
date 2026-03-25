# Laravel to N8N Webhook URL Configuration

**Researched:** 2026-03-10
**Domain:** Webhook Integration, N8N Automation, Document Processing
**Confidence:** HIGH

## Summary

This document captures the actual webhook URLs used for Laravel ↔ N8N communication in the Soud Laravel system. The system uses a bidirectional webhook architecture: Laravel sends documents to N8N for processing, and N8N calls back to Laravel with results.

**Current State:** URLs are configurable via environment variables. Production URL is currently **not set** in `.env` (empty string), requiring configuration before deployment.

---

## Environment Variables

### Required Configuration

| Variable | Purpose | Current Value | Production Value |
|----------|---------|---------------|------------------|
| `N8N_WEBHOOK_URL` | URL Laravel uses to send documents to N8N | Empty (not set) | **REQUIRES CONFIGURATION** |
| `N8N_WEBHOOK_SECRET` | HMAC secret for webhook signing | Empty (not set) | **REQUIRES CONFIGURATION** |
| `N8N_BASE_URL` | Base URL for N8N instance | `http://localhost:5678` (default) | **Set to production N8N host** |
| `N8N_WEBHOOK_PATH` | Webhook path suffix | `/webhook/document-processing` (default) | Typically unchanged |
| `N8N_API_KEY` | N8N API key (if auth enabled) | Empty (not set) | Optional |

### Location in `.env`
```env
#N8N
N8N_WEBHOOK_URL=
N8N_WEBHOOK_SECRET=
```

### Location in `.env.example`
```env
#N8N
N8N_WEBHOOK_URL=
```

---

## Configuration Files

### 1. `config/services.php`
N8N webhook URL is retrieved via:
```php
'n8n' => [
    'webhook_url' => env('N8N_WEBHOOK_URL'),
    'webhook_secret' => env('N8N_WEBHOOK_SECRET', 'default-secret'),
],
```

**Usage in code:**
```php
$n8nWebhookUrl = config('services.n8n.webhook_url');
```

### 2. `config/webhook.php`
Extended N8N configuration:
```php
'n8n' => [
    'base_url' => env('N8N_BASE_URL', 'http://localhost:5678'),
    'webhook_path' => env('N8N_WEBHOOK_PATH', '/webhook/document-processing'),
    'api_key' => env('N8N_API_KEY', ''),
],
```

**Note:** This provides base URL + path composition option, but `services.n8n.webhook_url` is the primary configuration point.

---

## Webhook URLs

### Laravel → N8N (Outbound)

**Purpose:** Laravel queues documents for N8N processing

| Environment | URL | Source |
|------------|-----|--------|
| **Development** | `http://localhost:5678/webhook/supplier-document-processing` | N8N workflow default |
| **Production** | **REQUIRES CONFIGURATION** | Set `N8N_WEBHOOK_URL` in `.env` |

**Expected Production URL:**
```
N8N_WEBHOOK_URL=http://localhost:5678/webhook/supplier-document-processing
```

**Actual URL Construction:**
The N8N workflow defines the webhook path in `n8n/workflows/supplier-document-processing.json`:
```json
{
  "parameters": {
    "httpMethod": "POST",
    "path": "supplier-document-processing",
    "responseMode": "onReceived",
    "responseCode": 202
  },
  "name": "Webhook Trigger",
  "type": "n8n-nodes-base.webhook"
}
```

This creates the endpoint: `{N8N_BASE_URL}/webhook/supplier-document-processing`

**Default N8N Base URL:** `http://localhost:5678`
**Full Default URL:** `http://localhost:5678/webhook/supplier-document-processing`

---

### N8N → Laravel (Callback)

**Purpose:** N8N returns processing results to Laravel

| Environment | URL | Source |
|------------|-----|--------|
| **Development** | `http://127.0.0.1:8000/api/webhooks/n8n/extraction` | `route('api.webhooks.n8n.callback')` |
| **Production** | `https://development.citycommerce.group/api/webhooks/n8n/extraction` | Production APP_URL |

**Route Definition** (`routes/api.php` lines 162-164):
```php
// N8n Webhook Callback
Route::post('/webhooks/n8n/extraction', [N8nCallbackController::class, 'handle'])
    ->name('api.webhooks.n8n.callback');
```

**Callback URL Generation** (`app/Http/Controllers/Api/DocumentProcessingController.php` line 59):
```php
$payload = [
    // ...
    'callback_url' => route('api.webhooks.n8n.callback'),
];
```

**Important:** The callback URL is dynamically generated using Laravel's `route()` helper, which respects `APP_URL` from `.env`. For production, set:
```env
APP_URL=https://development.citycommerce.group
```

---

## How URLs Flow Through the System

### 1. Document Queuing (Laravel → N8N)

```
┌─────────────────┐     POST /webhook/supplier-document-processing     ┌─────────────────┐
│     Laravel     │ ───────────────────────────────────────────────▶ │      N8N        │
│                 │     URL: config('services.n8n.webhook_url')        │                 │
│                 │     Payload includes: callback_url                 │                 │
└─────────────────┘                                                        └─────────────────┘
```

**Controller:** `DocumentProcessingController@store`
**Code:**
```php
// app/Http/Controllers/Api/DocumentProcessingController.php
$n8nWebhookUrl = config('services.n8n.webhook_url');

$payload = [
    'company_id' => $validated['company_id'],
    'supplier_id' => $validated['supplier_id'],
    'document_id' => $documentId,
    'document_type' => $validated['document_type'],
    'file_path' => $validated['file_path'],
    'callback_url' => route('api.webhooks.n8n.callback'),  // Dynamic callback URL
    'timestamp' => now()->timestamp,
];

$response = Http::timeout(10)->post($n8nWebhookUrl, $payload);
```

### 2. Result Callback (N8N → Laravel)

```
┌─────────────────┐     POST /api/webhooks/n8n/extraction     ┌─────────────────┐
│      N8N        │ ◀──────────────────────────────────────── │     Laravel     │
│                 │     URL: callback_url from payload         │                 │
│                 │     Payload: extraction_result              │                 │
└─────────────────┘                                               └─────────────────┘
```

**Controller:** `N8nCallbackController@handle`
**Route:** `api.webhooks.n8n.callback`

---

## Production Configuration Checklist

### Required `.env` Settings for Production

```env
# Application URL (affects callback URL generation)
APP_URL=https://development.citycommerce.group

# N8N Webhook Configuration
N8N_WEBHOOK_URL=http://localhost:5678/webhook/supplier-document-processing
N8N_WEBHOOK_SECRET=your-secure-secret-here

# Optional: If N8N base URL differs from default
# N8N_BASE_URL=http://your-n8n-server:5678
# N8N_WEBHOOK_PATH=/webhook/supplier-document-processing
```

### Production Checklist

- [ ] `APP_URL` set to `https://development.citycommerce.group`
- [ ] `N8N_WEBHOOK_URL` set to N8N instance webhook URL
- [ ] `N8N_WEBHOOK_SECRET` set to a secure random string (matching N8N credentials)
- [ ] N8N server accessible from Laravel application
- [ ] Laravel callback URL accessible from N8N server
- [ ] Firewall rules allow Laravel → N8N and N8N → Laravel traffic

---

## N8N Workflow Configuration

### Workflow File
`n8n/workflows/supplier-document-processing.json`

### Webhook Trigger Node
```json
{
  "parameters": {
    "httpMethod": "POST",
    "path": "supplier-document-processing",
    "responseMode": "onReceived",
    "responseCode": 202
  },
  "name": "Webhook Trigger",
  "type": "n8n-nodes-base.webhook"
}
```

### Callback Node (Send to Laravel)
```json
{
  "parameters": {
    "method": "POST",
    "url": "={{ $json.callback_url }}",
    "sendHeaders": true,
    "headerParameters": {
      "parameters": [
        { "name": "Content-Type", "value": "application/json" },
        { "name": "X-Signature", "value": "={{ $json.hmac }}" },
        { "name": "X-Timestamp", "value": "={{ Math.floor(Date.now() / 1000) }}" }
      ]
    }
  },
  "name": "Send to Laravel Callback",
  "type": "n8n-nodes-base.httpRequest"
}
```

**Note:** The callback URL is taken from the incoming payload (`$json.callback_url`), which Laravel generates dynamically.

---

## N8N Credentials

### File Location
`n8n/credentials/laravel-webhook-secret.json`

### Content
```json
{
  "name": "Laravel Webhook Secret",
  "type": "customCredential",
  "data": {
    "secret": "YOUR_WEBHOOK_SECRET_HERE"
  }
}
```

**Action Required:** Set this value to match `N8N_WEBHOOK_SECRET` in `.env`

---

## Security Notes

### Current State (As of 2026-03-09)
HMAC authentication was **temporarily removed** for testing purposes (see `.planning/quick/6-n8n-test-simplification/6-SUMMARY.md`):
- `N8nCallbackController` no longer verifies HMAC signatures
- `DocumentProcessingController` no longer signs outbound requests
- This is a **temporary state** for end-to-end testing

### Restoration Required
After testing, restore HMAC authentication:
1. Build `webhook:provision` Artisan command
2. Create `WebhookClient` database rows
3. Restore HMAC verification in `N8nCallbackController`
4. Restore HMAC signing in `DocumentProcessingController`
5. Use correct header names: `X-Signature-SHA256`, `X-Signature-Timestamp`

---

## Testing URLs

### Local Development
```bash
# Test Laravel callback endpoint (requires document_id in payload)
curl -X POST http://127.0.0.1:8000/api/webhooks/n8n/extraction \
  -H "Content-Type: application/json" \
  -d '{
    "document_id": "test-uuid",
    "status": "success",
    "execution_id": "test-exec",
    "workflow_id": "test-workflow",
    "execution_time_ms": 100,
    "extraction_result": {}
  }'
```

### Test Document Queuing
```bash
# Test Laravel → N8N queuing
curl -X POST http://127.0.0.1:8000/api/documents/process \
  -H "Content-Type: application/json" \
  -d '{
    "company_id": 1,
    "supplier_id": 2,
    "document_type": "air",
    "file_path": "company/supplier/files_unprocessed/test.air"
  }'
```

---

## Troubleshooting

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| `ERR_N8N_CONFIG_MISSING` | `N8N_WEBHOOK_URL` not set in `.env` | Add `N8N_WEBHOOK_URL` to `.env` |
| `ERR_N8N_UNAVAILABLE` | N8N server not running or unreachable | Start N8N server, check firewall |
| Callback never received | Laravel callback URL unreachable from N8N | Use ngrok for local dev, check production APP_URL |
| `ERR_HMAC_INVALID` | Webhook secret mismatch | Ensure `.env` and N8N credentials match |

### Logs to Check
- Laravel: `storage/logs/laravel.log`
- N8N: N8N execution logs (via N8N UI)
- DocumentProcessingLog: `document_processing_logs` table

---

## Summary Table

| URL | Type | Configuration | Production Value |
|-----|------|---------------|------------------|
| Laravel → N8N | Outbound | `N8N_WEBHOOK_URL` env var | **Set in `.env`** |
| N8N → Laravel | Callback | `route('api.webhooks.n8n.callback')` | `https://development.citycommerce.group/api/webhooks/n8n/extraction` |
| N8N Webhook Path | Workflow config | `path: "supplier-document-processing"` | `/webhook/supplier-document-processing` |
| HMAC Secret | Shared | `N8N_WEBHOOK_SECRET` env var | **Set in `.env` and N8N credentials** |

---

## Files Referenced

| File | Purpose |
|------|---------|
| `.env` | Environment configuration (N8N_WEBHOOK_URL, N8N_WEBHOOK_SECRET) |
| `config/services.php` | N8N webhook_url and webhook_secret config |
| `config/webhook.php` | Extended N8N configuration (base_url, webhook_path) |
| `routes/api.php` | Defines `/webhooks/n8n/extraction` callback route |
| `app/Http/Controllers/Api/DocumentProcessingController.php` | Sends documents to N8N |
| `app/Http/Controllers/Api/Webhooks/N8nCallbackController.php` | Receives N8N callbacks |
| `app/Console/Commands/ScanUploadedFile.php` | Alternative file scanning that calls N8N |
| `n8n/workflows/supplier-document-processing.json` | N8N workflow definition |
| `n8n/credentials/laravel-webhook-secret.json` | N8N credentials for HMAC |

---

## Confidence Assessment

| Area | Level | Reason |
|------|-------|--------|
| Environment Variables | HIGH | Direct code inspection of `.env`, `.env.example`, config files |
| Route Definitions | HIGH | Direct inspection of `routes/api.php` |
| Controller Logic | HIGH | Direct inspection of `DocumentProcessingController.php`, `N8nCallbackController.php` |
| N8N Workflow | HIGH | Direct inspection of `supplier-document-processing.json` |
| Production URLs | MEDIUM | Production URL derived from `APP_URL` in CLAUDE.md, requires verification |
| Current Deployment | LOW | `N8N_WEBHOOK_URL` is empty, deployment status unknown |

**Research date:** 2026-03-10
**Valid until:** 2026-04-10 (30 days)