<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Models\Company;
use App\Models\DocumentProcessingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class N8nDocumentProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected string $testFilePath;
    protected string $webhookSecret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->testFilePath = 'test_company/test_supplier/sample.pdf';
        $this->webhookSecret = config('services.n8n.webhook_secret', 'test-secret');

        // Create test file in storage (mock)
        Storage::fake('local');
        Storage::put($this->testFilePath, 'test content');
    }

    /**
     * Test successful PDF document processing
     */
    public function test_successful_pdf_processing(): void
    {
        // Mock N8n webhook response
        Http::fake([
            config('services.n8n.webhook_url') => Http::response([
                'status' => 'accepted',
                'execution_id' => 'exec-test-123',
                'document_id' => '*',
                'message' => 'Document queued for processing',
            ], 202),
        ]);

        // Queue document to N8n
        $response = $this->postJson('/api/documents/process', [
            'company_id' => $this->company->id,
            'supplier_id' => 3, // ETA UK (PDF)
            'document_type' => 'pdf',
            'file_path' => $this->testFilePath,
            'file_size_bytes' => 2048,
            'file_hash' => hash('sha256', 'test content'),
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure(['document_id', 'status', 'message']);

        $documentId = $response->json('document_id');

        // Assert log created
        $log = DocumentProcessingLog::where('document_id', $documentId)->first();
        $this->assertNotNull($log);
        $this->assertEquals('queued', $log->status);
        $this->assertEquals($this->company->id, $log->company_id);
        $this->assertEquals(3, $log->supplier_id);

        // Simulate N8n callback with extraction result
        $callbackPayload = [
            'document_id' => $documentId,
            'status' => 'success',
            'execution_id' => 'exec-test-123',
            'workflow_id' => 'wf-test-456',
            'execution_time_ms' => 1500,
            'extraction_result' => [
                'tasks' => [
                    [
                        'type' => 'flight',
                        'supplier_reference' => 'EK123',
                        'passenger' => 'John Doe',
                    ],
                ],
            ],
        ];

        $signature = $this->computeHmac($callbackPayload);

        $callbackResponse = $this->postJson('/api/webhooks/n8n/extraction', $callbackPayload, [
            'X-Signature' => $signature,
            'X-Timestamp' => (string)now()->timestamp,
        ]);

        $callbackResponse->assertStatus(200);

        // Assert callback received and processed
        $log->refresh();
        $this->assertEquals('completed', $log->status);
        $this->assertNotNull($log->extraction_result);
        $this->assertNotNull($log->n8n_execution_id);
        $this->assertEquals('exec-test-123', $log->n8n_execution_id);
        $this->assertArrayHasKey('tasks', $log->extraction_result);
    }

    /**
     * Test invalid HMAC signature is rejected
     */
    public function test_invalid_hmac_rejected(): void
    {
        $log = DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'queued',
        ]);

        $payload = [
            'document_id' => $log->document_id,
            'status' => 'success',
            'execution_id' => 'test-exec-123',
            'workflow_id' => 'test-wf-456',
            'execution_time_ms' => 1500,
            'extraction_result' => ['tasks' => []],
        ];

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Signature' => 'invalid-signature',
            'X-Timestamp' => (string)now()->timestamp,
        ]);

        $response->assertStatus(401);

        $log->refresh();
        $this->assertEquals('queued', $log->status); // Not updated
    }

    /**
     * Test replay attack prevention (stale timestamp)
     */
    public function test_replay_attack_prevented(): void
    {
        $log = DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'queued',
        ]);

        $staleTimestamp = now()->subMinutes(10)->timestamp; // 10 minutes ago (>5 min threshold)
        $payload = [
            'document_id' => $log->document_id,
            'status' => 'success',
            'execution_id' => 'test-exec-123',
            'workflow_id' => 'test-wf-456',
            'execution_time_ms' => 1500,
        ];

        $signature = $this->computeHmac($payload);

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => (string)$staleTimestamp,
        ]);

        $response->assertStatus(401);

        $log->refresh();
        $this->assertEquals('queued', $log->status);
    }

    /**
     * Test error callback handling
     */
    public function test_error_callback(): void
    {
        $log = DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'queued',
        ]);

        $payload = [
            'document_id' => $log->document_id,
            'status' => 'error',
            'execution_id' => 'exec-err-123',
            'workflow_id' => 'wf-456',
            'execution_time_ms' => 2500,
            'error' => [
                'code' => 'ERR_FILE_NOT_FOUND',
                'message' => 'File does not exist at path',
                'context' => ['file_path' => 'invalid/path.pdf'],
            ],
        ];

        $signature = $this->computeHmac($payload);

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => (string)now()->timestamp,
        ]);

        $response->assertStatus(200);

        $log->refresh();
        $this->assertEquals('failed', $log->status);
        $this->assertEquals('ERR_FILE_NOT_FOUND', $log->error_code);
        $this->assertEquals('File does not exist at path', $log->error_message);
        $this->assertNotNull($log->error_context);
    }

    /**
     * Test duplicate callback is rejected (idempotency)
     */
    public function test_duplicate_callback_rejected(): void
    {
        $log = DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
            'n8n_execution_id' => 'exec-original-123',
            'extraction_result' => ['tasks' => []],
        ]);

        $payload = [
            'document_id' => $log->document_id,
            'status' => 'success',
            'execution_id' => 'exec-duplicate-456',
            'workflow_id' => 'wf-789',
            'execution_time_ms' => 1500,
            'extraction_result' => ['tasks' => []],
        ];

        $signature = $this->computeHmac($payload);

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => (string)now()->timestamp,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Callback already processed',
                'document_id' => $log->document_id,
                'status' => 'completed',
            ]);

        // Assert execution_id not changed
        $log->refresh();
        $this->assertEquals('exec-original-123', $log->n8n_execution_id);
    }

    /**
     * Test N8n service unavailable handling
     */
    public function test_n8n_unavailable(): void
    {
        // Mock N8n returning 503
        Http::fake([
            config('services.n8n.webhook_url') => Http::response([], 503),
        ]);

        $response = $this->postJson('/api/documents/process', [
            'company_id' => $this->company->id,
            'supplier_id' => 3,
            'document_type' => 'pdf',
            'file_path' => $this->testFilePath,
        ]);

        $response->assertStatus(503)
            ->assertJson([
                'error' => 'Service unavailable',
            ]);
    }

    /**
     * Test image document processing
     */
    public function test_image_document_processing(): void
    {
        Http::fake([
            config('services.n8n.webhook_url') => Http::response([
                'status' => 'accepted',
                'execution_id' => 'exec-img-123',
            ], 202),
        ]);

        $response = $this->postJson('/api/documents/process', [
            'company_id' => $this->company->id,
            'supplier_id' => 5,
            'document_type' => 'image',
            'file_path' => 'test_company/supplier/ticket.png',
        ]);

        $response->assertStatus(202);

        $documentId = $response->json('document_id');
        $log = DocumentProcessingLog::where('document_id', $documentId)->first();

        $this->assertNotNull($log);
        $this->assertEquals('image', $log->document_type);
    }

    /**
     * Test email document processing
     */
    public function test_email_document_processing(): void
    {
        Http::fake([
            config('services.n8n.webhook_url') => Http::response([
                'status' => 'accepted',
                'execution_id' => 'exec-email-123',
            ], 202),
        ]);

        $response = $this->postJson('/api/documents/process', [
            'company_id' => $this->company->id,
            'supplier_id' => 7,
            'document_type' => 'email',
            'file_path' => 'test_company/supplier/booking.eml',
        ]);

        $response->assertStatus(202);

        $documentId = $response->json('document_id');
        $log = DocumentProcessingLog::where('document_id', $documentId)->first();

        $this->assertNotNull($log);
        $this->assertEquals('email', $log->document_type);
    }

    /**
     * Test AIR file processing
     */
    public function test_air_file_processing(): void
    {
        Http::fake([
            config('services.n8n.webhook_url') => Http::response([
                'status' => 'accepted',
                'execution_id' => 'exec-air-123',
            ], 202),
        ]);

        $response = $this->postJson('/api/documents/process', [
            'company_id' => $this->company->id,
            'supplier_id' => 1,
            'document_type' => 'air',
            'file_path' => 'test_company/supplier/ticket.air',
        ]);

        $response->assertStatus(202);

        $documentId = $response->json('document_id');
        $log = DocumentProcessingLog::where('document_id', $documentId)->first();

        $this->assertNotNull($log);
        $this->assertEquals('air', $log->document_type);
    }

    /**
     * Helper: Compute HMAC signature
     */
    protected function computeHmac(array $payload): string
    {
        $payloadJson = json_encode($payload);
        return hash_hmac('sha256', $payloadJson, $this->webhookSecret);
    }
}
