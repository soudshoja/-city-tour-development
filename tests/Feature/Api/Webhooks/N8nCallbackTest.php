<?php

namespace Tests\Feature\Api\Webhooks;

use Tests\TestCase;
use App\Models\DocumentProcessingLog;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class N8nCallbackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful callback
     */
    public function test_successful_callback(): void
    {
        $company = Company::factory()->create();
        $documentId = Str::uuid()->toString();

        // Create queued document
        $log = DocumentProcessingLog::create([
            'company_id' => $company->id,
            'supplier_id' => 5,
            'document_id' => $documentId,
            'document_type' => 'air',
            'file_path' => 'test/sample.air',
            'status' => 'queued',
        ]);

        $payload = [
            'document_id' => $documentId,
            'status' => 'success',
            'execution_id' => 'exec-123',
            'workflow_id' => 'wf-456',
            'execution_time_ms' => 1500,
            'extraction_result' => [
                'tasks' => [],
            ],
        ];

        // Sign payload
        $payloadJson = json_encode($payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $payloadJson, config('services.n8n.webhook_secret'));

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Callback processed',
                'document_id' => $documentId,
            ]);

        // Assert database updated
        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status' => 'completed',
            'n8n_execution_id' => 'exec-123',
            'n8n_workflow_id' => 'wf-456',
        ]);
    }

    /**
     * Test error callback
     */
    public function test_error_callback(): void
    {
        $company = Company::factory()->create();
        $documentId = Str::uuid()->toString();

        // Create queued document
        $log = DocumentProcessingLog::create([
            'company_id' => $company->id,
            'supplier_id' => 5,
            'document_id' => $documentId,
            'document_type' => 'air',
            'file_path' => 'test/sample.air',
            'status' => 'queued',
        ]);

        $payload = [
            'document_id' => $documentId,
            'status' => 'error',
            'execution_id' => 'exec-123',
            'workflow_id' => 'wf-456',
            'execution_time_ms' => 500,
            'error' => [
                'code' => 'ERR_EXTRACTION_FAILED',
                'message' => 'Failed to parse AIR file',
                'context' => ['line' => 42],
            ],
        ];

        // Sign payload
        $payloadJson = json_encode($payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $payloadJson, config('services.n8n.webhook_secret'));

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(200);

        // Assert database updated with error
        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status' => 'failed',
            'error_code' => 'ERR_EXTRACTION_FAILED',
            'error_message' => 'Failed to parse AIR file',
        ]);
    }

    /**
     * Test invalid signature
     */
    public function test_invalid_signature(): void
    {
        $company = Company::factory()->create();
        $documentId = Str::uuid()->toString();

        // Create queued document
        $log = DocumentProcessingLog::create([
            'company_id' => $company->id,
            'supplier_id' => 5,
            'document_id' => $documentId,
            'document_type' => 'air',
            'file_path' => 'test/sample.air',
            'status' => 'queued',
        ]);

        $payload = [
            'document_id' => $documentId,
            'status' => 'success',
            'execution_id' => 'exec-123',
            'workflow_id' => 'wf-456',
            'execution_time_ms' => 1500,
        ];

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Signature' => 'invalid-signature',
            'X-Timestamp' => now()->timestamp,
        ]);

        $response->assertStatus(401);

        // Assert database NOT updated
        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status' => 'queued', // Still queued
        ]);
    }

    /**
     * Test replay attack (old timestamp)
     */
    public function test_replay_attack(): void
    {
        $company = Company::factory()->create();
        $documentId = Str::uuid()->toString();

        // Create queued document
        $log = DocumentProcessingLog::create([
            'company_id' => $company->id,
            'supplier_id' => 5,
            'document_id' => $documentId,
            'document_type' => 'air',
            'file_path' => 'test/sample.air',
            'status' => 'queued',
        ]);

        $payload = [
            'document_id' => $documentId,
            'status' => 'success',
            'execution_id' => 'exec-123',
            'workflow_id' => 'wf-456',
            'execution_time_ms' => 1500,
        ];

        // Old timestamp (6 minutes ago)
        $oldTimestamp = now()->subMinutes(6)->timestamp;
        $payloadJson = json_encode($payload);
        $signature = hash_hmac('sha256', $payloadJson, config('services.n8n.webhook_secret'));

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $oldTimestamp,
        ]);

        $response->assertStatus(401);

        // Assert database NOT updated
        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status' => 'queued',
        ]);
    }

    /**
     * Test duplicate callback
     */
    public function test_duplicate_callback(): void
    {
        $company = Company::factory()->create();
        $documentId = Str::uuid()->toString();

        // Create completed document
        $log = DocumentProcessingLog::create([
            'company_id' => $company->id,
            'supplier_id' => 5,
            'document_id' => $documentId,
            'document_type' => 'air',
            'file_path' => 'test/sample.air',
            'status' => 'completed',
            'n8n_execution_id' => 'exec-original',
        ]);

        $payload = [
            'document_id' => $documentId,
            'status' => 'success',
            'execution_id' => 'exec-duplicate',
            'workflow_id' => 'wf-456',
            'execution_time_ms' => 1500,
        ];

        // Sign payload
        $payloadJson = json_encode($payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $payloadJson, config('services.n8n.webhook_secret'));

        $response = $this->postJson('/api/webhooks/n8n/extraction', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Callback already processed',
                'document_id' => $documentId,
                'status' => 'completed',
            ]);

        // Assert database NOT changed (still has original execution_id)
        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status' => 'completed',
            'n8n_execution_id' => 'exec-original',
        ]);
    }
}
