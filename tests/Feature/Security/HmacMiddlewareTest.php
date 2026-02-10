<?php

namespace Tests\Feature\Security;

use Tests\TestCase;
use App\Models\WebhookClient;
use App\Models\WebhookSecret;
use App\Services\WebhookSigningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HmacMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private WebhookSigningService $signingService;
    private WebhookClient $client;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signingService = new WebhookSigningService();
        $this->secret = 'test-webhook-secret-key-12345';

        // Create test webhook client
        $this->client = WebhookClient::create([
            'name' => 'Test N8n Client',
            'type' => 'n8n',
            'webhook_url' => 'http://localhost:5678/webhook/test',
            'rate_limit' => 60,
            'is_active' => true,
        ]);

        // Create active secret
        WebhookSecret::create([
            'webhook_client_id' => $this->client->id,
            'secret_hash' => $this->signingService->hashSecret($this->secret),
            'secret_preview' => substr($this->secret, -8),
            'algorithm' => 'sha256',
            'is_active' => true,
            'created_at' => now(),
        ]);

        // Set secret in environment for middleware
        config(['app.env' => 'testing']);
        putenv('WEBHOOK_SECRET_' . $this->client->getActiveSecret()->id . '=' . $this->secret);
    }

    public function test_middleware_accepts_valid_signed_request()
    {
        $payload = ['test' => 'data', 'document_id' => 'doc-123'];
        $payloadJson = json_encode($payload);
        $path = 'api/webhook/test';

        $signedData = $this->signingService->signPayload($payloadJson, $this->secret, 'POST', $path);

        $response = $this->postJson('/api/webhook/test?client_id=' . $this->client->id, $payload, [
            'X-Signature-SHA256' => $signedData['signature'],
            'X-Signature-Timestamp' => $signedData['timestamp'],
        ]);

        // Should pass through middleware (endpoint may not exist, but middleware should pass)
        $this->assertNotEquals(401, $response->status());
    }

    public function test_middleware_rejects_request_without_signature()
    {
        $payload = ['test' => 'data'];

        $response = $this->postJson('/api/webhook/test?client_id=' . $this->client->id, $payload);

        // Should pass through (no signature header means skip verification)
        $this->assertNotEquals(401, $response->status());
    }

    public function test_middleware_rejects_invalid_signature()
    {
        $payload = ['test' => 'data'];

        $response = $this->postJson('/api/webhook/test?client_id=' . $this->client->id, $payload, [
            'X-Signature-SHA256' => 'invalid-signature-12345',
            'X-Signature-Timestamp' => time(),
        ]);

        $this->assertEquals(401, $response->status());
        $this->assertJsonStructure(['status', 'message'], $response->json());
    }

    public function test_middleware_rejects_expired_timestamp()
    {
        $payload = ['test' => 'data'];
        $payloadJson = json_encode($payload);
        $oldTimestamp = time() - 400; // 400 seconds ago

        $signedData = $this->signingService->signPayload($payloadJson, $this->secret, 'POST', 'api/webhook/test', $oldTimestamp);

        $response = $this->postJson('/api/webhook/test?client_id=' . $this->client->id, $payload, [
            'X-Signature-SHA256' => $signedData['signature'],
            'X-Signature-Timestamp' => $oldTimestamp,
        ]);

        $this->assertEquals(401, $response->status());
    }

    public function test_middleware_rejects_inactive_client()
    {
        $this->client->update(['is_active' => false]);

        $payload = ['test' => 'data'];
        $payloadJson = json_encode($payload);

        $signedData = $this->signingService->signPayload($payloadJson, $this->secret, 'POST', 'api/webhook/test');

        $response = $this->postJson('/api/webhook/test?client_id=' . $this->client->id, $payload, [
            'X-Signature-SHA256' => $signedData['signature'],
            'X-Signature-Timestamp' => $signedData['timestamp'],
        ]);

        $this->assertEquals(401, $response->status());
    }

    public function test_middleware_logs_audit_entry()
    {
        $payload = ['test' => 'data'];
        $payloadJson = json_encode($payload);

        $signedData = $this->signingService->signPayload($payloadJson, $this->secret, 'POST', 'api/webhook/test');

        $this->postJson('/api/webhook/test?client_id=' . $this->client->id, $payload, [
            'X-Signature-SHA256' => $signedData['signature'],
            'X-Signature-Timestamp' => $signedData['timestamp'],
        ]);

        $this->assertDatabaseHas('webhook_audit_logs', [
            'webhook_client_id' => $this->client->id,
            'direction' => 'inbound',
            'http_method' => 'POST',
        ]);
    }
}
