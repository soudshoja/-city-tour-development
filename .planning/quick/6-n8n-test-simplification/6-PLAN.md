---
phase: quick-6
plan: 6
type: execute
wave: 1
depends_on: [quick-5]
files_modified:
  - app/Http/Controllers/Api/Webhooks/N8nCallbackController.php
  - app/Http/Controllers/Api/DocumentProcessingController.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "POST /api/webhooks/n8n/extraction with a valid payload returns 200 (no auth headers required)"
    - "POST /api/webhooks/n8n/extraction with an invalid payload returns 422 (validation, not 401)"
    - "DocumentProcessingController sends a plain POST to N8N_WEBHOOK_URL with JSON body only — no HMAC headers"
    - "DocumentProcessingController uses a 10s HTTP timeout (up from 5s)"
    - "N8nCallbackController has no __construct, no WebhookSigningService injection, no verifyHmacSignature() method"
    - "N8nCallbackController has no import of WebhookClient or WebhookSigningService"
  artifacts:
    - path: "app/Http/Controllers/Api/Webhooks/N8nCallbackController.php"
      provides: "Auth-free n8n callback endpoint — validates payload fields only"
    - path: "app/Http/Controllers/Api/DocumentProcessingController.php"
      provides: "Plain POST outbound to n8n — no signing overhead"
  key_links:
    - from: "DocumentProcessingController"
      to: "N8N_WEBHOOK_URL (env)"
      via: "Http::timeout(10)->post() with JSON body"
    - from: "N8nCallbackController"
      to: "Payload validation"
      via: "Direct field validation — no auth layer"
---

<objective>
Temporarily remove all HMAC authentication from the n8n ↔ Laravel integration so an end-to-end
test can be run without requiring DB-provisioned WebhookClient rows or shared secrets.

Context: Quick Task 5 designed per-company HMAC auth (WebhookClient + WebhookSigningService) but
the `webhook:provision` Artisan command was never built. No WebhookClient rows exist in the DB,
so every n8n callback returns 401. Rather than block testing on completing the provisioning
toolchain, auth is stripped now to allow a clean integration test.

This is intentionally temporary. Auth will be reinstated once the end-to-end flow is confirmed
working.

Output:
- N8nCallbackController — HMAC verification removed, accepts any POST with valid payload
- DocumentProcessingController — HMAC signing removed, plain JSON POST to n8n
</objective>

<context>
@CLAUDE.md
@app/Http/Controllers/Api/Webhooks/N8nCallbackController.php
@app/Http/Controllers/Api/DocumentProcessingController.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Strip HMAC auth from N8nCallbackController</name>
  <files>app/Http/Controllers/Api/Webhooks/N8nCallbackController.php</files>
  <action>
Remove all authentication infrastructure from the controller:

1. Remove: `use App\Models\WebhookClient;`
2. Remove: `use App\Services\WebhookSigningService;`
3. Remove: constructor `__construct(private readonly WebhookSigningService $signingService)`
4. Remove: entire `verifyHmacSignature()` private method (~100 lines)
5. Remove: the `$authResult = $this->verifyHmacSignature($request);` call and its if-block

Keep all existing payload validation and processing logic completely unchanged.
The endpoint now accepts any POST to `/api/webhooks/n8n/extraction` that passes field validation.
  </action>
  <verify>
    POST /api/webhooks/n8n/extraction with a valid payload returns 200.
    POST with missing required fields returns 422.
    POST with no auth headers returns 200 (not 401).
  </verify>
  <done>
    Controller has no auth constructor, no verifyHmacSignature(), no WebhookClient/WebhookSigningService imports.
    Valid payload → 200. Invalid payload → 422. No auth check.
  </done>
</task>

<task type="auto">
  <name>Task 2: Strip HMAC signing from DocumentProcessingController</name>
  <files>app/Http/Controllers/Api/DocumentProcessingController.php</files>
  <action>
Remove HMAC signing from the outbound n8n call:

1. Remove the 3-line signing block:
   - `$webhookSecret = ...`
   - `$payloadJson = ...`
   - `$hmacSignature = ...`
2. Remove old mismatched headers (`X-Signature`, `X-Timestamp`) from the Http call
3. Change Http timeout from 5s to 10s
4. Keep the call as a plain: Http::timeout(10)->post($url, $payload)

Result: clean JSON POST to n8n with no signing overhead.
  </action>
  <verify>
    Controller sends POST to N8N_WEBHOOK_URL with JSON body only.
    No X-Signature or X-Timestamp headers in the outbound request.
    Timeout is 10 seconds.
  </verify>
  <done>
    No signing variables, no signature headers, timeout=10. Http call is a plain JSON POST.
  </done>
</task>

</tasks>

<verification>
With both changes in place:

1. Set N8N_WEBHOOK_URL in .env to your n8n trigger URL.

2. Trigger document processing — the outbound POST to n8n should succeed without signature headers.

3. From n8n, send a callback to POST /api/webhooks/n8n/extraction with a valid payload — expect
   200 (not 401).

4. Send the callback with missing required fields — expect 422 (validation, not 401).
</verification>

<success_criteria>
- N8nCallbackController accepts POST with no auth headers — returns 200 for valid payload, 422 for invalid
- DocumentProcessingController sends plain JSON POST to N8N_WEBHOOK_URL with 10s timeout
- No HMAC signing code remains in either controller
- All existing payload validation and processing logic is unchanged
</success_criteria>

<output>
No SUMMARY.md needed for quick plans. The plan itself is the output.
</output>
