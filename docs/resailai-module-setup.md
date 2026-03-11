# ResailAI Module Setup Guide

**Last Updated:** 2026-03-11
**Version:** 1.0.0
**Status:** Ready for Deployment

---

## Overview

The ResailAI Module is a self-contained Laravel module that handles automated document processing for any supplier and any task type (flight, hotel, visa, insurance). Documents are uploaded from Laravel, sent to ResailAI extraction service via n8n webhook, and results feed back into the existing TaskWebhook pipeline.

**Branding:** ResailAI (external processing service is hidden from clients)

---

## Prerequisites

- Laravel 11.x with PHP 8.2+
- MySQL database with `supplier_companies` table
- n8n instance running on a separate VPS or server
- API token from ResailAI service

---

## Environment Variables

Add these to your `.env` file:

```env
# ResailAI Module Configuration
RESAILAI_API_TOKEN=your-secure-api-token-here
N8N_WEBHOOK_URL=https://n8n.example.com/webhook/resailai-process

# Optional (defaults shown)
RESAILAI_TIMEOUT=30
RESAILAI_MAX_RETRIES=3
RESAILAI_CALLBACK_EXPIRY_MINUTES=15
```

### Variable Descriptions

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `RESAILAI_API_TOKEN` | Yes | - | Bearer token for authenticating callbacks from ResailAI n8n webhook. Generate via `php artisan make:secret` or 32+ character random string. |
| `N8N_WEBHOOK_URL` | Yes | - | Full URL to your n8n webhook endpoint for PDF processing. Must be publicly accessible or tunnelled. |
| `RESAILAI_TIMEOUT` | No | 30 | HTTP timeout in seconds for ResailAI API calls. |
| `RESAILAI_MAX_RETRIES` | No | 3 | Number of callback retry attempts before marking as failed. |
| `RESAILAI_CALLBACK_EXPIRY_MINUTES` | No | 15 | How long callbacks are valid after issuance. |

---

## Installation Steps

### 1. Run Migrations

```bash
php artisan migrate
```

This will create:
- `resailai_credentials` table for API key storage
- Add `auto_process_pdf` column to `supplier_companies` pivot table

### 2. Clear Configuration Cache

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### 3. Enable ResailAI for a Supplier

Use the Admin UI or SQL to enable the feature flag:

```sql
-- Enable for supplier_id=2 (FlyDubai) and company_id=1
UPDATE supplier_companies
SET auto_process_pdf = 1, is_active = 1
WHERE supplier_id = 2 AND company_id = 1;
```

Or use the Admin UI (accessible to authenticated users with API permissions).

---

## n8n Webhook Configuration

### n8n Webhook Setup

1. **Create Webhook Node in n8n:**
   - Node type: Webhook
   - Path: `/resailai-process`
   - Method: POST
   - Authentication: None (handled via Bearer token in payload)

2. **Payload Expectation:**
```json
{
    "document_id": "uuid-string",
    "supplier_id": 2,
    "company_id": 1,
    "agent_id": 5,
    "branch_id": 1,
    "file_path": "storage/app/company/supplier/files_unprocessed/filename.pdf",
    "callback_url": "https://laravel.example.com/api/modules/resailai/callback",
    "created_at": "2026-03-11T12:00:00Z"
}
```

3. **Response Expected:**
```json
{
    "success": true,
    "message": "PDF received for processing",
    "processing_id": "uuid"
}
```

4. **Extraction Result Callback:**
After processing, n8n should POST back to Laravel's callback URL:

```json
{
    "document_id": "uuid-string",
    "status": "success",
    "supplier_id": 2,
    "company_id": 1,
    "agent_id": 5,
    "extraction_result": {
        "reference": "ABC123",
        "status": "confirmed",
        "type": "flight",
        "passenger_name": "John Doe",
        "flight_details": [...],
        "total": 150.50,
        "exchange_currency": "KWD",
        "exchange_rate": 3.25
    }
}
```

---

## ResailAI Callback URL Configuration

### Configure ResailAI Service

The ResailAI service needs to know where to send extraction results:

| Setting | Value |
|---------|-------|
| Callback URL | `https://laravel.example.com/api/modules/resailai/callback` |
| Auth Method | Bearer Token |
| Token | `RESAILAI_API_TOKEN` from your Laravel `.env` |

### Security

- Callbacks are validated using the Bearer token
- Invalid or missing tokens return HTTP 401
- All callbacks are logged in `storage/logs/laravel.log`

---

## API Endpoint Documentation

### Callback Endpoint

**Endpoint:** `POST /api/modules/resailai/callback`
**Middleware:** `verify.resailai.token`
**Authentication:** Bearer Token (from `RESAILAI_API_TOKEN`)

#### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `document_id` | string (UUID) | Yes | Unique document identifier |
| `status` | string | Yes | One of: `success`, `error`, `pending` |
| `supplier_id` | integer | No | Supplier ID |
| `company_id` | integer | No | Company ID |
| `agent_id` | integer | No | Agent ID (critical for task linking) |
| `file_url` | string | No | URL to file if stored externally |
| `extraction_result` | object | Conditional | Required when status=success |
| `error` | object | Conditional | Required when status=error |

#### Successful Response (200)

```json
{
    "message": "Callback processed",
    "document_id": "uuid-string"
}
```

#### Validation Error (422)

```json
{
    "error": "Validation failed",
    "messages": {
        "document_id": ["The document id field is required."],
        "status": ["The status field is required."]
    }
}
```

#### Unauthorized (401)

```json
{
    "status": "error",
    "message": "Missing or invalid Authorization header"
}
```

---

## Error Handling

### Common Errors

| Error Code | Message | Solution |
|------------|---------|----------|
| `ERR_N8N_CONFIG_MISSING` | N8n webhook URL not configured | Set `N8N_WEBHOOK_URL` in `.env` |
| `ERR_N8N_UNAVAILABLE` | N8n webhook request failed | Check n8n is running and accessible |
| `ERR_INVALID_TOKEN` | Invalid API key | Generate new key via Admin UI |
| `ERR_CALLBACK_EXPIRED` | Callback expired | Resend the extraction result |
| `ERR_MAX_RETRIES` | Max retries reached | Check supplier_companies configuration |

### Debugging

```bash
# Check ResailAI callbacks in logs
tail -f storage/logs/laravel.log | grep ResailAI

# Verify configuration
php artisan tinker --execute="echo json_encode(config('resailai'));"

# Check callback routes
php artisan route:list --path=modules/resailai
```

---

## Admin UI Usage

### Access the Admin UI

Navigate to the appropriate admin route (configure in your web routes).

### API Key Management

1. Click "Generate new API key"
2. Enter a descriptive name
3. Set expiry (optional)
4. Copy the displayed API key immediately
5. Store in ResailAI service configuration

### Supplier Toggle

1. View list of all suppliers
2. Toggle switch to enable/disable ResailAI processing
3. Changes take effect immediately

---

## Database Schema

### resailai_credentials Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigInt (PK) | Auto-increment ID |
| `user_id` | bigInt | User who created credential |
| `name` | varchar(255) | Credential name for identification |
| `api_key` | text | Encrypted API key |
| `api_secret` | text (nullable) | Encrypted API secret |
| `last_used_at` | timestamp | Last use timestamp |
| `expires_at` | timestamp (nullable) | Expiry date |
| `is_active` | boolean | Active/inactive status |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Update timestamp |
| `deleted_at` | timestamp (nullable) | Soft delete timestamp |

### supplier_companies Pivot Table

Added column:
- `auto_process_pdf` (boolean, default: 0) - Auto-process PDF files via ResailAI

---

## Security Best Practices

1. **API Token Storage:**
   - Tokens are encrypted at rest using Laravel's `Crypt::encryptString()`
   - Never log API keys
   - Rotate tokens periodically

2. **Callback Validation:**
   - All callbacks require valid Bearer token
   - Invalid tokens return 401 without details
   - Audit log entries created for all callback attempts

3. **Rate Limiting:**
   - Consider adding rate limiting middleware to callback route
   - Currently: No rate limiting (configurable)

4. **File Access:**
   - Files stored in `storage/app/` which should not be web-accessible
   - Use signed URLs for file access if needed

---

## Troubleshooting

### PDF Not Being Processed

1. Check feature flag enabled:
```sql
SELECT supplier_id, company_id, auto_process_pdf
FROM supplier_companies
WHERE auto_process_pdf = 1;
```

2. Check Laravel logs for job dispatch errors:
```bash
tail -f storage/logs/laravel.log
```

3. Verify queue worker is running:
```bash
php artisan queue:work --verbose
```

### n8n Callback Returns 401

1. Verify `RESAILAI_API_TOKEN` in `.env` matches ResailAI configuration
2. Check token hasn't expired
3. Verify callback URL matches what ResailAI expects

### Task Not Created After Extraction

1. Check extraction result contains required fields:
   - `agent_id` - Required for task enabled status
   - `client_id` - Required for client linking
   - `exchange_rate` - Required if non-KWD currency

2. Check TaskWebhook logs in `storage/logs/laravel.log`

---

## Next Steps

1. **Configure ResailAI Service:**
   - Set n8n webhook URL in ResailAI dashboard
   - Configure Bearer token
   - Test with sample PDF

2. **Enable Suppliers:**
   - Start with one supplier for testing
   - Verify end-to-end flow
   - Enable additional suppliers

3. **Monitoring:**
   - Set up alerting for failed callbacks
   - Monitor queue depth
   - Track processing times

4. **Documentation:**
   - Create supplier-specific extraction rules
   - Document expected payload formats
   - Maintain API key inventory

---

## Support

For issues or questions:
1. Check `storage/logs/laravel.log` for ResailAI entries
2. Verify all environment variables are set
3. Test n8n webhook independently first
4. Check ResailAI service status and logs

---

*Document created: 2026-03-11*
*For Soud Laravel ResailAI Module v1.0.0*
