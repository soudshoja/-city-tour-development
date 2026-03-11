<?php

namespace Tests\Feature\Api\Webhooks;

use Tests\TestCase;
use App\Models\Company;
use App\Models\WebhookClient;
use App\Models\WebhookSecret;
use App\Models\DocumentProcessingLog;
use App\Services\WebhookSigningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Per-client HMAC authentication tests for N8nCallbackController.
 *
 * Tests the multi-tenant webhook auth flow:
 *   X-Client-ID → WebhookClient lookup → per-client secret → HMAC verify
 */
class N8nCallbackControllerTest extends TestCase
{
    use RefreshDatabase;

    private WebhookSigningService $signingService;
    private Company $company;
    private WebhookClient $client;
    private string $plainSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signingService = app(WebhookSigningService::class);

        $this->company = Company::factory()->create();

        $this->plainSecret = $this->signingService->generateSecret();

        $this->client = WebhookClient::create([
            'name'       => 'Test N8n Client',
            'type'       => 'n8n',
            'company_id' => $this->company->id,
            'is_active'  => true,
        ]);

        WebhookSecret::create([
            'webhook_client_id' => $this->client->id,
            'secret_hash'       => $this->signingService->storeSecret($this->plainSecret),
            'secret_preview'    => substr($this->plainSecret, -8),
            'algorithm'         => 'sha256',
            'is_active'         => true,
            'created_at'        => now(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function makeLog(string $documentId): DocumentProcessingLog
    {
        return DocumentProcessingLog::create([
            'company_id'    => $this->company->id,
            'supplier_id'   => 5,
            'document_id'   => $documentId,
            'document_type' => 'air',
            'file_path'     => 'test/sample.air',
            'status'        => 'queued',
        ]);
    }

    private function signedHeaders(string $payloadJson, ?string $secret = null): array
    {
        $secret ??= $this->plainSecret;
        $result = $this->signingService->signPayload(
            $payloadJson,
            $secret,
            'POST',
            '/api/webhooks/n8n/extraction'
        );
        return [
            'X-Client-ID'           => (string) $this->client->id,
            'X-Signature-SHA256'    => $result['signature'],
            'X-Signature-Timestamp' => (string) $result['timestamp'],
        ];
    }

    // -----------------------------------------------------------------------
    // Auth: missing X-Client-ID
    // -----------------------------------------------------------------------

    public function test_missing_client_id_header_returns_401(): void
    {
        $documentId = Str::uuid()->toString();
        $this->makeLog($documentId);

        $payload = [
            'document_id'       => $documentId,
            'status'            => 'success',
            'execution_id'      => 'exec-123',
            'workflow_id'       => 'wf-456',
            'execution_time_ms' => 1500,
        ];
        $payloadJson = json_encode($payload);
        $signed = $this->signingService->signPayload($payloadJson, $this->plainSecret, 'POST', '/api/webhooks/n8n/extraction');

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            // No X-Client-ID
            'X-Signature-SHA256'    => $signed['signature'],
            'X-Signature-Timestamp' => (string) $signed['timestamp'],
        ]);

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // Auth: unknown / inactive client
    // -----------------------------------------------------------------------

    public function test_unknown_client_id_returns_401(): void
    {
        $documentId = Str::uuid()->toString();
        $this->makeLog($documentId);

        $payload = [
            'document_id'       => $documentId,
            'status'            => 'success',
            'execution_id'      => 'exec-123',
            'workflow_id'       => 'wf-456',
            'execution_time_ms' => 1500,
        ];
        $payloadJson = json_encode($payload);
        $signed = $this->signingService->signPayload($payloadJson, $this->plainSecret, 'POST', '/api/webhooks/n8n/extraction');

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Client-ID'           => '99999', // Non-existent
            'X-Signature-SHA256'    => $signed['signature'],
            'X-Signature-Timestamp' => (string) $signed['timestamp'],
        ]);

        $response->assertStatus(401);
    }

    public function test_inactive_client_returns_401(): void
    {
        $this->client->update(['is_active' => false]);

        $documentId = Str::uuid()->toString();
        $this->makeLog($documentId);

        $payload = [
            'document_id'       => $documentId,
            'status'            => 'success',
            'execution_id'      => 'exec-123',
            'workflow_id'       => 'wf-456',
            'execution_time_ms' => 1500,
        ];
        $payloadJson = json_encode($payload);
        $headers = $this->signedHeaders($payloadJson);

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, $headers);

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // Auth: wrong HMAC
    // -----------------------------------------------------------------------

    public function test_valid_client_with_wrong_hmac_returns_401(): void
    {
        $documentId = Str::uuid()->toString();
        $this->makeLog($documentId);

        $payload = [
            'document_id'       => $documentId,
            'status'            => 'success',
            'execution_id'      => 'exec-123',
            'workflow_id'       => 'wf-456',
            'execution_time_ms' => 1500,
        ];

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Client-ID'           => (string) $this->client->id,
            'X-Signature-SHA256'    => 'wrong-signature-value',
            'X-Signature-Timestamp' => (string) time(),
        ]);

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // Auth: valid client, correct HMAC → passes through to processing
    // -----------------------------------------------------------------------

    public function test_valid_client_and_correct_hmac_processes_callback(): void
    {
        $documentId = Str::uuid()->toString();
        $this->makeLog($documentId);

        $payload = [
            'document_id'       => $documentId,
            'status'            => 'success',
            'execution_id'      => 'exec-123',
            'workflow_id'       => 'wf-456',
            'execution_time_ms' => 1500,
            'extraction_result' => ['tasks' => []],
        ];
        $payloadJson = json_encode($payload);
        $headers = $this->signedHeaders($payloadJson);

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, $headers);

        $response->assertStatus(200)
            ->assertJson([
                'message'     => 'Callback processed',
                'document_id' => $documentId,
            ]);

        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status'      => 'completed',
        ]);
    }

    // -----------------------------------------------------------------------
    // Auth: grace-period secret still accepted
    // -----------------------------------------------------------------------

    public function test_grace_period_secret_is_still_accepted(): void
    {
        // Deactivate the current secret but leave it in grace period
        WebhookSecret::where('webhook_client_id', $this->client->id)
            ->update([
                'is_active'          => false,
                'grace_period_until' => now()->addMinutes(30),
                'deactivated_at'     => now(),
            ]);

        // Create a new active secret
        $newSecret = $this->signingService->generateSecret();
        WebhookSecret::create([
            'webhook_client_id' => $this->client->id,
            'secret_hash'       => $this->signingService->storeSecret($newSecret),
            'secret_preview'    => substr($newSecret, -8),
            'algorithm'         => 'sha256',
            'is_active'         => true,
            'created_at'        => now(),
        ]);

        $documentId = Str::uuid()->toString();
        $this->makeLog($documentId);

        $payload = [
            'document_id'       => $documentId,
            'status'            => 'success',
            'execution_id'      => 'exec-grace',
            'workflow_id'       => 'wf-456',
            'execution_time_ms' => 800,
            'extraction_result' => [],
        ];
        $payloadJson = json_encode($payload);

        // Sign with the OLD (grace-period) secret
        $headers = $this->signedHeaders($payloadJson, $this->plainSecret);

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, $headers);

        $response->assertStatus(200);
    }

    // -----------------------------------------------------------------------
    // Existing callback processing behaviour preserved
    // -----------------------------------------------------------------------

    public function test_error_callback_is_processed_correctly(): void
    {
        $documentId = Str::uuid()->toString();
        $this->makeLog($documentId);

        $payload = [
            'document_id'       => $documentId,
            'status'            => 'error',
            'execution_id'      => 'exec-err',
            'workflow_id'       => 'wf-456',
            'execution_time_ms' => 500,
            'error'             => [
                'code'    => 'ERR_EXTRACTION_FAILED',
                'message' => 'Failed to parse AIR file',
                'context' => ['line' => 42],
            ],
        ];
        $payloadJson = json_encode($payload);
        $headers = $this->signedHeaders($payloadJson);

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, $headers);

        $response->assertStatus(200);

        $this->assertDatabaseHas('document_processing_logs', [
            'document_id'   => $documentId,
            'status'        => 'failed',
            'error_code'    => 'ERR_EXTRACTION_FAILED',
            'error_message' => 'Failed to parse AIR file',
        ]);
    }

    public function test_duplicate_callback_returns_409(): void
    {
        $documentId = Str::uuid()->toString();
        DocumentProcessingLog::create([
            'company_id'       => $this->company->id,
            'supplier_id'      => 5,
            'document_id'      => $documentId,
            'document_type'    => 'air',
            'file_path'        => 'test/sample.air',
            'status'           => 'completed',
            'n8n_execution_id' => 'exec-original',
        ]);

        $payload = [
            'document_id'       => $documentId,
            'status'            => 'success',
            'execution_id'      => 'exec-duplicate',
            'workflow_id'       => 'wf-456',
            'execution_time_ms' => 1500,
        ];
        $payloadJson = json_encode($payload);
        $headers = $this->signedHeaders($payloadJson);

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, $headers);

        $response->assertStatus(409);
    }
}
