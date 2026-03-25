---
phase: quick-5
plan: 5
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Http/Controllers/Api/Webhooks/N8nCallbackController.php
  - app/Services/WebhookSigningService.php
  - app/Console/Commands/WebhookClientProvision.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "POST /api/webhooks/n8n/extraction with no X-Client-ID header returns 401"
    - "POST with a valid X-Client-ID and correct HMAC-SHA256 signature returns 200"
    - "POST with a valid X-Client-ID and wrong signature returns 401"
    - "POST with an inactive or unknown client ID returns 401"
    - "php artisan webhook:provision --company=1 creates a WebhookClient+WebhookSecret row and prints the plaintext secret once"
    - "php artisan webhook:provision --company=1 --rotate updates the active secret (with grace period) and prints the new secret"
  artifacts:
    - path: "app/Http/Controllers/Api/Webhooks/N8nCallbackController.php"
      provides: "Per-client HMAC verification via X-Client-ID lookup"
    - path: "app/Services/WebhookSigningService.php"
      provides: "storeSecret() / retrieveSecret() using Laravel encrypt (not bcrypt)"
    - path: "app/Console/Commands/WebhookClientProvision.php"
      provides: "Artisan command to provision and rotate per-company webhook credentials"
  key_links:
    - from: "N8nCallbackController"
      to: "WebhookClient (DB)"
      via: "X-Client-ID header → WebhookClient::where('id')->active()->first()"
    - from: "WebhookClient"
      to: "WebhookSecret.secret_hash (encrypted plaintext)"
      via: "getValidSecrets() → decrypt() → hash_hmac verify"
---

<objective>
Replace the single global N8N_WEBHOOK_SECRET with per-company HMAC authentication using the
existing webhook_clients + webhook_secrets tables. Each company's n8n instance authenticates
by sending X-Client-ID and a per-client HMAC signature.

Purpose: Multi-tenant security — a compromised n8n instance for one company cannot forge
callbacks for another company.

Output:
- Updated N8nCallbackController — authenticates per-client
- Updated WebhookSigningService — stores secrets encrypted (not bcrypt), exposes retrieveSecret()
- New WebhookClientProvision Artisan command — provisions and rotates per-company credentials
</objective>

<execution_context>
@C:/Users/User/.claude/get-shit-done/workflows/execute-plan.md
</execution_context>

<context>
@CLAUDE.md
@app/Http/Controllers/Api/Webhooks/N8nCallbackController.php
@app/Services/WebhookSigningService.php
@app/Models/WebhookClient.php
@app/Models/WebhookSecret.php
@database/migrations/2026_02_10_231921_create_webhook_clients_table.php
@database/migrations/2026_02_10_231952_create_webhook_secrets_table.php
@config/webhook.php

<interfaces>
<!-- Existing contracts the executor must know before touching any file. -->

WebhookSigningService::verifySignature() signing message format:
  "{METHOD} {PATH}\n{timestamp}\n{payload}"
  Headers expected: X-Signature-SHA256, X-Signature-Timestamp

WebhookClient model (app/Models/WebhookClient.php):
  - company_id: nullable FK to companies
  - type: enum('n8n','external','internal')
  - is_active: bool
  - getActiveSecret(): ?WebhookSecret
  - getValidSecrets(): Collection<WebhookSecret>  // active + in grace period

WebhookSecret model (app/Models/WebhookSecret.php):
  - webhook_client_id FK
  - secret_hash: string  // currently named for bcrypt BUT we will repurpose as encrypted plaintext
  - secret_preview: last 8 chars for UI display
  - is_active: bool
  - grace_period_until: ?datetime

Current controller auth (BROKEN for multi-tenant):
  - Reads single global: config('services.n8n.webhook_secret')
  - Computes: hash_hmac('sha256', $payload, $secret)  // no method/path in message
  - Header names: X-Signature, X-Timestamp  // inconsistent with WebhookSigningService

Correct header names (config/webhook.php + WebhookSigningService):
  - Signature: X-Signature-SHA256
  - Timestamp: X-Signature-Timestamp
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Add storeSecret / retrieveSecret to WebhookSigningService</name>
  <files>app/Services/WebhookSigningService.php</files>
  <behavior>
    - storeSecret(string $plaintext): string — returns Crypt::encrypt($plaintext); used when saving to DB
    - retrieveSecret(string $stored): string — returns Crypt::decrypt($stored); used at verify time
    - generateSecret() already exists — no change needed
    - hashSecret() already exists (bcrypt) — keep it but it is NOT used for HMAC secrets going forward
  </behavior>
  <action>
Add two public methods to WebhookSigningService:

```php
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypt plaintext secret for DB storage.
 * Use Crypt::encrypt (reversible) — NOT Hash::make (bcrypt is one-way).
 * HMAC verification requires the original plaintext at verify time.
 */
public function storeSecret(string $plaintext): string
{
    return Crypt::encrypt($plaintext);
}

/**
 * Decrypt a stored secret back to plaintext for HMAC computation.
 */
public function retrieveSecret(string $stored): string
{
    return Crypt::decrypt($stored);
}
```

No other changes to this file. The existing signPayload() and verifySignature() methods are
correct and must not be changed — they use the proper signing message format.
  </action>
  <verify>
    <automated>php artisan test --filter WebhookSigningServiceTest</automated>
  </verify>
  <done>
    storeSecret(retrieveSecret(storeSecret($s))) === $s for any string $s.
    Tests pass.
  </done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Refactor N8nCallbackController to per-client HMAC auth</name>
  <files>app/Http/Controllers/Api/Webhooks/N8nCallbackController.php</files>
  <behavior>
    - Missing X-Client-ID header → 401 "Missing client ID"
    - X-Client-ID present but no active WebhookClient row found → 401 "Unknown client"
    - WebhookClient found but is_active=false → 401 "Unknown client"
    - Valid client, valid HMAC (active or grace-period secret) → proceeds to existing validation logic
    - Valid client, bad HMAC → 401 "Webhook signature verification failed"
    - Header names must match WebhookSigningService: X-Signature-SHA256 + X-Signature-Timestamp
    - Log client_id and company_id on all auth failures
  </behavior>
  <action>
Replace the private verifyHmacSignature() method entirely. Inject WebhookSigningService via
constructor. The new auth flow:

1. Read X-Client-ID header. If absent → return 401.
2. Look up: WebhookClient::where('id', $clientId)->where('type', 'n8n')->where('is_active', true)->first(). If null → 401.
3. Get valid secrets: $client->getValidSecrets(). If empty → 401 (client exists but no secrets provisioned).
4. Read X-Signature-SHA256 and X-Signature-Timestamp headers. If either absent → 401.
5. For each valid secret, call $signingService->retrieveSecret($secret->secret_hash) then
   $signingService->verifySignature($payload, $signature, $timestamp, $plaintext, $method, $path).
   If ANY secret verifies → auth passes.
6. If no secret verified → 401.

Update the log context in verifyHmacSignature() to include client_id and company_id.

Keep all existing validation and processing logic (lines 36–130) completely unchanged.

Constructor injection:
```php
public function __construct(
    private readonly WebhookSigningService $signingService
) {}
```

Remove the old inline config('services.n8n.webhook_secret') lookup entirely.

Header alignment — replace all occurrences in this file:
  X-Signature  → X-Signature-SHA256
  X-Timestamp  → X-Signature-Timestamp
  (The hmac_signature field stored in DocumentProcessingLog should also be updated to the new header value.)
  </action>
  <verify>
    <automated>php artisan test --filter N8nCallbackControllerTest</automated>
  </verify>
  <done>
    Auth with valid client ID + valid HMAC → 200.
    Auth with valid client ID + wrong HMAC → 401.
    Auth with no X-Client-ID → 401.
    Auth with unknown client ID → 401.
    All N8nCallbackControllerTest cases pass.
  </done>
</task>

<task type="auto">
  <name>Task 3: Create webhook:provision Artisan command</name>
  <files>app/Console/Commands/WebhookClientProvision.php</files>
  <action>
Create `php artisan webhook:provision` with these options:

```
--company=   Company ID (required)
--name=      Client name (default: "N8n {company_id}")
--rotate     Rotate the secret for an existing client (skip if none exists)
--grace=     Grace period in minutes when rotating (default: 60)
```

Behaviour — CREATE (no --rotate, no existing active client for company+type=n8n):
  1. Create WebhookClient: name, type='n8n', company_id, is_active=true.
  2. Generate plaintext: $signingService->generateSecret().
  3. Store: $signingService->storeSecret($plaintext) → save to webhook_secrets.secret_hash.
  4. Save secret_preview = substr($plaintext, -8).
  5. Output to console (this is the ONLY time the plaintext is shown):
     ```
     Webhook client created.
     Client ID  : {id}
     Company ID : {company_id}
     Secret     : {plaintext}   <-- copy this; it will NOT be shown again
     ```

Behaviour — ROTATE (--rotate flag):
  1. Find existing active WebhookClient for company_id + type='n8n'. Abort with error if none.
  2. Find its active WebhookSecret. Set grace_period_until = now()->addMinutes($grace).
     Leave is_active=true (still valid during grace).
  3. Deactivate old: $oldSecret->update(['is_active' => false, 'deactivated_at' => now()]).
     Wait — set grace first, then create new:
       a. Old secret: is_active=false, grace_period_until=now()->addMinutes($grace), deactivated_at=now()
       b. New secret: is_active=true, created_at=now()
  4. Generate + store new plaintext as above.
  5. Output:
     ```
     Secret rotated.
     Client ID  : {id}
     New Secret : {plaintext}   <-- copy this; it will NOT be shown again
     Grace ends : {datetime}    (old secret still accepted until then)
     ```

Error cases:
  - Company not found → error("Company {id} not found.") and exit 1.
  - --rotate but no existing client → error("No active n8n client for company {id}. Run without --rotate to create one.") and exit 1.
  - Non-rotate create but client already exists → ask user to use --rotate flag; exit 1.

Use $this->info(), $this->error(), $this->line() for output. Use DB transaction for rotate
(both the old secret deactivation and new secret creation must be atomic).
  </action>
  <verify>
    <automated>php artisan webhook:provision --company=1 2>&1 | grep -E "Client ID|Secret"</automated>
  </verify>
  <done>
    Running with --company=1 on a fresh DB creates a WebhookClient row (type=n8n, company_id=1)
    and a WebhookSecret row (is_active=1), then prints the plaintext secret.
    Running again without --rotate exits with an error pointing to --rotate.
    Running with --rotate deactivates the old secret with grace_period_until set and creates a
    new active secret.
  </done>
</task>

</tasks>

<verification>
After all three tasks:

1. Provision a test client:
   php artisan webhook:provision --company=1

2. Use the printed secret to construct a valid HMAC request:
   php artisan tinker
   >>> $svc = app(\App\Services\WebhookSigningService::class);
   >>> $r = $svc->signPayload('{"document_id":"test"}', '{printed_secret}', 'POST', '/api/webhooks/n8n/extraction');
   >>> echo $r['signature'] . ' ts=' . $r['timestamp'];

3. Send a curl request with the Client-ID and signature headers and verify 422 (validation,
   not 401 — means auth passed, input rejected for missing fields):
   curl -X POST http://localhost:8000/api/webhooks/n8n/extraction \
     -H "X-Client-ID: 1" \
     -H "X-Signature-SHA256: {signature}" \
     -H "X-Signature-Timestamp: {timestamp}" \
     -H "Content-Type: application/json" \
     -d '{"document_id":"test"}'

4. Send without X-Client-ID → expect 401.

5. Run rotation: php artisan webhook:provision --company=1 --rotate --grace=5
   Verify old secret still works for 5 minutes, new secret works immediately.
</verification>

<success_criteria>
- N8nCallbackController no longer reads config('services.n8n.webhook_secret')
- Each company's n8n authenticates using its own DB-backed secret
- WebhookSigningService stores secrets with Crypt::encrypt (not bcrypt) so HMAC can be recomputed
- webhook:provision command can be run by an admin to issue or rotate credentials
- All existing callback processing logic (document_id validation, status updates) unchanged
</success_criteria>

<output>
No SUMMARY.md needed for quick plans. The plan itself is the output.
</output>
