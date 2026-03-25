---
phase: quick-6
plan: "06"
subsystem: api
tags: [n8n, webhook, testing, auth, temporary]

dependency_graph:
  requires: [quick-5]
  provides:
    - Auth-free POST /api/webhooks/n8n/extraction (accepts any valid payload without credentials)
    - Plain JSON outbound POST from DocumentProcessingController to n8n (no signing)
  affects:
    - app/Http/Controllers/Api/Webhooks/N8nCallbackController.php
    - app/Http/Controllers/Api/DocumentProcessingController.php

tech_stack:
  added: []
  removed:
    - WebhookClient DB lookup in N8nCallbackController
    - WebhookSigningService injection in N8nCallbackController
    - verifyHmacSignature() private method (~100 lines)
    - HMAC signing block in DocumentProcessingController (webhookSecret, payloadJson, hmacSignature)
    - Outbound headers X-Signature and X-Timestamp from DocumentProcessingController
  patterns:
    - "TEMPORARY: No-auth webhook endpoint — accept POST, validate payload fields only"
    - "Plain Http::timeout(10)->post() to n8n — no signing overhead"

key_files:
  created: []
  modified:
    - app/Http/Controllers/Api/Webhooks/N8nCallbackController.php
    - app/Http/Controllers/Api/DocumentProcessingController.php

key_decisions:
  - "Auth stripped for test unblock: Quick Task 5 designed per-company HMAC auth but the webhook:provision
    Artisan command was never built. No WebhookClient rows exist in DB, so every n8n callback returned
    401 with no path to fix without completing the provisioning toolchain. Auth removed temporarily to
    allow a clean end-to-end test. MUST be reinstated after test confirms the flow works."
  - "Http timeout raised 5s → 10s: n8n workflows can take longer than 5s to respond, especially
    under load. 10s gives enough headroom without blocking indefinitely."
  - "Outbound headers removed entirely: X-Signature and X-Timestamp in DocumentProcessingController
    were mismatched with the correct header names defined in WebhookSigningService (X-Signature-SHA256,
    X-Signature-Timestamp). Removing them now avoids sending headers that n8n never expected anyway."

metrics:
  duration: "~15 minutes"
  completed: "2026-03-09"
  tasks: 2
  files_changed: 2
---

# Quick Task 6: N8n Test Simplification Summary

**HMAC auth stripped from both ends of the n8n ↔ Laravel integration to unblock end-to-end testing while the webhook:provision provisioning toolchain remains incomplete.**

> WARNING: This is a temporary state. The auth-free endpoint must NOT be deployed to production.
> Re-add HMAC verification (Quick Task 5 pattern) once integration testing confirms the flow works.

## What Was Done

The per-company HMAC authentication introduced in Quick Task 5 requires WebhookClient rows in the
database, which are created by a `webhook:provision` Artisan command that was never built. With no
DB rows, every n8n callback returned 401 unconditionally. Rather than complete the provisioning
toolchain before testing the rest of the integration, auth was stripped from both the inbound
callback controller and the outbound dispatch call.

## Tasks Completed

| Task | Name | Files |
|------|------|-------|
| 1 | Strip HMAC auth from N8nCallbackController | app/Http/Controllers/Api/Webhooks/N8nCallbackController.php |
| 2 | Strip HMAC signing from DocumentProcessingController | app/Http/Controllers/Api/DocumentProcessingController.php |

## Files Modified

- `app/Http/Controllers/Api/Webhooks/N8nCallbackController.php`
  - Removed: `use App\Models\WebhookClient`
  - Removed: `use App\Services\WebhookSigningService`
  - Removed: `__construct(private readonly WebhookSigningService $signingService)`
  - Removed: entire `verifyHmacSignature()` private method (~100 lines)
  - Removed: `$authResult = $this->verifyHmacSignature($request)` call and its if-block
  - Unchanged: all payload validation and processing logic

- `app/Http/Controllers/Api/DocumentProcessingController.php`
  - Removed: `$webhookSecret`, `$payloadJson`, `$hmacSignature` signing block
  - Removed: `X-Signature` and `X-Timestamp` headers from outbound Http call
  - Changed: Http timeout 5s → 10s
  - Result: `Http::timeout(10)->post($url, $payload)` — plain JSON, no signing

## User Setup Required

Set the n8n trigger URL in `.env`:
```
N8N_WEBHOOK_URL=https://your-n8n-url/webhook/your-trigger
```

## Deviations from Plan

None — plan executed exactly as written.

## What to Do After Testing

Once end-to-end testing confirms the n8n ↔ Laravel flow works:

1. Build the `webhook:provision` Artisan command (see Quick Task 5 plan, Task 3).
2. Run `php artisan webhook:provision --company={id}` to create WebhookClient + WebhookSecret rows.
3. Restore the per-client HMAC auth to `N8nCallbackController` (Quick Task 5 plan, Task 2).
4. Restore HMAC signing to `DocumentProcessingController` using correct header names
   (`X-Signature-SHA256`, `X-Signature-Timestamp`) as defined in `WebhookSigningService`.
