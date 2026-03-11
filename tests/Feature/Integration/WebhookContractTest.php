<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * TEST-01: Webhook Contract Validation Tests
 *
 * Validates that the DocumentProcessingController enforces proper webhook contracts
 * for incoming document processing requests.
 */
class WebhookContractTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test company
        $this->company = Company::factory()->create();

        // Mock N8n webhook to always return success
        Http::fake([
            config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 200),
        ]);
    }

    /** @test */
    public function it_accepts_valid_payload_with_all_required_fields()
    {
        // Arrange
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 123,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/company_1/supplier_123/invoice.pdf',
            'file_size_bytes' => 524288,
            'file_hash' => hash('sha256', 'test-content'),
        ];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(202)
            ->assertJsonStructure([
                'document_id',
                'status',
                'message',
            ])
            ->assertJson([
                'status' => 'queued',
            ]);

        $this->assertDatabaseHas('document_processing_logs', [
            'company_id' => $this->company->id,
            'supplier_id' => 123,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/company_1/supplier_123/invoice.pdf',
            'status' => 'queued',
        ]);
    }

    /** @test */
    public function it_rejects_payload_missing_supplier_id()
    {
        // Arrange
        $payload = [
            'company_id' => $this->company->id,
            // Missing: supplier_id
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/invoice.pdf',
        ];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id']);
    }

    /** @test */
    public function it_rejects_payload_missing_company_id()
    {
        // Arrange
        $payload = [
            // Missing: company_id
            'supplier_id' => 123,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/invoice.pdf',
        ];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    /** @test */
    public function it_rejects_payload_missing_document_type()
    {
        // Arrange
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 123,
            // Missing: document_type
            'file_path' => 's3://bucket/invoice.pdf',
        ];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_type']);
    }

    /** @test */
    public function it_rejects_invalid_document_type()
    {
        // Arrange
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 123,
            'document_type' => 'invalid_type', // Invalid type
            'file_path' => 's3://bucket/invoice.pdf',
        ];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_type']);
    }

    /** @test */
    public function it_rejects_payload_missing_file_path()
    {
        // Arrange
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 123,
            'document_type' => 'pdf',
            // Missing: file_path
        ];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file_path']);
    }

    /** @test */
    public function it_accepts_payload_with_extra_fields()
    {
        // Arrange
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 123,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/invoice.pdf',
            // Extra fields (should be ignored)
            'extra_field_1' => 'should be ignored',
            'extra_field_2' => 'also ignored',
            'metadata' => ['key' => 'value'],
        ];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(202)
            ->assertJsonStructure([
                'document_id',
                'status',
                'message',
            ]);
    }

    /** @test */
    public function it_rejects_empty_payload()
    {
        // Arrange
        $payload = [];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'company_id',
                'supplier_id',
                'document_type',
                'file_path',
            ]);
    }

    /** @test */
    public function it_accepts_all_valid_document_types()
    {
        // Valid document types: air, pdf, image, email
        $validTypes = ['air', 'pdf', 'image', 'email'];

        foreach ($validTypes as $type) {
            // Arrange
            $payload = [
                'company_id' => $this->company->id,
                'supplier_id' => 123,
                'document_type' => $type,
                'file_path' => "s3://bucket/document.{$type}",
            ];

            // Act
            $response = $this->postJson('/api/documents/process', $payload);

            // Assert
            $response->assertStatus(202);

            $this->assertDatabaseHas('document_processing_logs', [
                'company_id' => $this->company->id,
                'document_type' => $type,
                'status' => 'queued',
            ]);
        }
    }

    /** @test */
    public function it_rejects_invalid_company_id()
    {
        // Arrange
        $payload = [
            'company_id' => 99999, // Non-existent company
            'supplier_id' => 123,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/invoice.pdf',
        ];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);
    }

    /** @test */
    public function it_validates_file_hash_format()
    {
        // Arrange - invalid SHA256 hash (not 64 hex characters)
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 123,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/invoice.pdf',
            'file_hash' => 'invalid-hash', // Invalid format
        ];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file_hash']);
    }

    /** @test */
    public function it_validates_file_size_maximum()
    {
        // Arrange - file size exceeds maximum (50MB)
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 123,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/invoice.pdf',
            'file_size_bytes' => 52428801, // Over 50MB limit
        ];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file_size_bytes']);
    }

    /** @test */
    public function it_validates_file_path_maximum_length()
    {
        // Arrange - file path exceeds maximum (500 characters)
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 123,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/' . str_repeat('a', 500), // Over 500 chars
        ];

        // Act
        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file_path']);
    }
}
