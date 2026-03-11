<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\DocumentProcessingLog;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class DocumentProcessingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful document queue
     */
    public function test_successful_document_queue(): void
    {
        // Mock N8n HTTP request
        Http::fake([
            config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 202),
        ]);

        // Create test company
        $company = Company::factory()->create();

        $payload = [
            'company_id' => $company->id,
            'supplier_id' => 5,
            'document_type' => 'air',
            'file_path' => 'test/sample.air',
            'file_size_bytes' => 1024,
            'file_hash' => str_repeat('a', 64),
        ];

        $response = $this->postJson('/api/documents/process', $payload);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'document_id',
                'status',
                'message',
            ]);

        // Assert database record created
        $this->assertDatabaseHas('document_processing_logs', [
            'company_id' => $company->id,
            'supplier_id' => 5,
            'status' => 'queued',
        ]);

        // Assert HTTP request sent
        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Signature') &&
                   $request->hasHeader('X-Timestamp') &&
                   $request->hasHeader('X-Request-ID');
        });
    }

    /**
     * Test validation errors
     */
    public function test_validation_error_missing_company_id(): void
    {
        $payload = [
            'supplier_id' => 5,
            'document_type' => 'air',
            'file_path' => 'test/sample.air',
        ];

        $response = $this->postJson('/api/documents/process', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    /**
     * Test invalid document type
     */
    public function test_validation_error_invalid_document_type(): void
    {
        $company = Company::factory()->create();

        $payload = [
            'company_id' => $company->id,
            'supplier_id' => 5,
            'document_type' => 'invalid',
            'file_path' => 'test/sample.air',
        ];

        $response = $this->postJson('/api/documents/process', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_type']);
    }

    /**
     * Test N8n unreachable
     */
    public function test_n8n_unreachable(): void
    {
        // Mock N8n HTTP failure
        Http::fake([
            config('services.n8n.webhook_url') => Http::response([], 500),
        ]);

        $company = Company::factory()->create();

        $payload = [
            'company_id' => $company->id,
            'supplier_id' => 5,
            'document_type' => 'air',
            'file_path' => 'test/sample.air',
        ];

        $response = $this->postJson('/api/documents/process', $payload);

        $response->assertStatus(503);

        // Assert database record marked as failed
        $this->assertDatabaseHas('document_processing_logs', [
            'company_id' => $company->id,
            'status' => 'failed',
            'error_code' => 'ERR_N8N_UNAVAILABLE',
        ]);
    }
}
