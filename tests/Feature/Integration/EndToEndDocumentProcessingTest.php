<?php

namespace Tests\Feature\Integration;

use Tests\TestCase;
use App\Models\Company;
use App\Models\DocumentProcessingLog;
use App\Models\DocumentError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * TEST-02: Full End-to-End Integration Tests
 *
 * Validates the complete Laravel → N8n → Laravel callback flow
 * for document processing across various scenarios.
 */
class EndToEndDocumentProcessingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test company
        $this->company = Company::factory()->create();

        // Prevent actual logging during tests
        Log::spy();
    }

    /** @test */
    public function it_processes_pdf_document_end_to_end()
    {
        // Arrange - Mock N8n to accept the webhook
        Http::fake([
            config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 200),
        ]);

        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 456,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/company_1/supplier_456/invoice.pdf',
            'file_size_bytes' => 1024000,
            'file_hash' => hash('sha256', 'test-pdf-content'),
        ];

        // Act 1: Submit document for processing
        $submitResponse = $this->postJson('/api/documents/process', $payload);

        // Assert 1: Document queued successfully
        $submitResponse->assertStatus(202);
        $documentId = $submitResponse->json('document_id');

        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status' => 'queued',
            'company_id' => $this->company->id,
            'supplier_id' => 456,
            'document_type' => 'pdf',
        ]);

        // Act 2: Simulate N8n callback with extraction results
        $callbackPayload = [
            'document_id' => $documentId,
            'status' => 'success',
            'execution_id' => 'exec-' . Str::random(12),
            'workflow_id' => 'wf-pdf-processor',
            'execution_time_ms' => 2500,
            'extraction_result' => [
                'tasks' => [
                    [
                        'type' => 'flight',
                        'supplier_reference' => 'EK123',
                        'passenger' => 'John Doe',
                        'total_amount' => 1250.00,
                        'currency' => 'USD',
                    ],
                ],
                'metadata' => [
                    'pages_processed' => 3,
                    'confidence_score' => 0.95,
                ],
            ],
        ];

        $timestamp = now()->timestamp;
        $hmacSignature = hash_hmac(
            'sha256',
            json_encode($callbackPayload),
            config('services.n8n.webhook_secret', 'default-secret')
        );

        $callbackResponse = $this->withHeaders([
            'X-Signature' => $hmacSignature,
            'X-Timestamp' => $timestamp,
        ])->postJson('/api/webhooks/n8n/extraction', $callbackPayload);

        // Assert 2: Callback processed successfully
        $callbackResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Callback processed',
                'document_id' => $documentId,
            ]);

        // Assert 3: Database updated with extraction results
        $log = DocumentProcessingLog::where('document_id', $documentId)->first();
        $this->assertEquals('completed', $log->status);
        $this->assertEquals('exec-' . substr($callbackPayload['execution_id'], -12), substr($log->n8n_execution_id, -12));
        $this->assertEquals('wf-pdf-processor', $log->n8n_workflow_id);
        $this->assertEquals(2500, $log->processing_duration_ms);
        $this->assertNotNull($log->extraction_result);
        $this->assertEquals('EK123', $log->extraction_result['tasks'][0]['supplier_reference']);
    }

    /** @test */
    public function it_processes_image_document_with_ocr_data()
    {
        // Arrange
        Http::fake([
            config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 200),
        ]);

        // Act 1: Submit image
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 789,
            'document_type' => 'image',
            'file_path' => 's3://bucket/scans/receipt.jpg',
        ];

        $submitResponse = $this->postJson('/api/documents/process', $payload);
        $documentId = $submitResponse->json('document_id');

        // Act 2: Simulate N8n callback with OCR results
        $callbackPayload = [
            'document_id' => $documentId,
            'status' => 'success',
            'execution_id' => 'exec-ocr-' . Str::random(10),
            'workflow_id' => 'wf-image-ocr',
            'execution_time_ms' => 4200,
            'extraction_result' => [
                'ocr_text' => 'Total Amount: $150.00\nDate: 2026-02-10',
                'detected_entities' => [
                    'amount' => 150.00,
                    'date' => '2026-02-10',
                    'merchant' => 'Test Store',
                ],
            ],
        ];

        $timestamp = now()->timestamp;
        $hmacSignature = hash_hmac(
            'sha256',
            json_encode($callbackPayload),
            config('services.n8n.webhook_secret', 'default-secret')
        );

        $callbackResponse = $this->withHeaders([
            'X-Signature' => $hmacSignature,
            'X-Timestamp' => $timestamp,
        ])->postJson('/api/webhooks/n8n/extraction', $callbackPayload);

        // Assert
        $callbackResponse->assertStatus(200);

        $log = DocumentProcessingLog::where('document_id', $documentId)->first();
        $this->assertEquals('completed', $log->status);
        $this->assertArrayHasKey('ocr_text', $log->extraction_result);
        $this->assertArrayHasKey('detected_entities', $log->extraction_result);
        $this->assertEquals(150.00, $log->extraction_result['detected_entities']['amount']);
    }

    /** @test */
    public function it_processes_email_document()
    {
        // Arrange
        Http::fake([
            config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 200),
        ]);

        // Act 1: Submit email
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 101,
            'document_type' => 'email',
            'file_path' => 's3://bucket/emails/confirmation-123.eml',
        ];

        $submitResponse = $this->postJson('/api/documents/process', $payload);
        $documentId = $submitResponse->json('document_id');

        // Act 2: Simulate N8n callback with email parsing results
        $callbackPayload = [
            'document_id' => $documentId,
            'status' => 'success',
            'execution_id' => 'exec-email-' . Str::random(10),
            'workflow_id' => 'wf-email-parser',
            'execution_time_ms' => 1800,
            'extraction_result' => [
                'from' => 'airline@example.com',
                'subject' => 'Booking Confirmation #ABC123',
                'body_text' => 'Your booking has been confirmed...',
                'attachments' => [
                    ['filename' => 'ticket.pdf', 'size' => 45678],
                ],
                'parsed_data' => [
                    'booking_reference' => 'ABC123',
                    'passenger_name' => 'Jane Smith',
                ],
            ],
        ];

        $timestamp = now()->timestamp;
        $hmacSignature = hash_hmac(
            'sha256',
            json_encode($callbackPayload),
            config('services.n8n.webhook_secret', 'default-secret')
        );

        $callbackResponse = $this->withHeaders([
            'X-Signature' => $hmacSignature,
            'X-Timestamp' => $timestamp,
        ])->postJson('/api/webhooks/n8n/extraction', $callbackPayload);

        // Assert
        $callbackResponse->assertStatus(200);

        $log = DocumentProcessingLog::where('document_id', $documentId)->first();
        $this->assertEquals('completed', $log->status);
        $this->assertEquals('ABC123', $log->extraction_result['parsed_data']['booking_reference']);
        $this->assertCount(1, $log->extraction_result['attachments']);
    }

    /** @test */
    public function it_processes_air_file_with_deferred_status()
    {
        // Arrange
        Http::fake([
            config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 200),
        ]);

        // Act 1: Submit AIR file
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 202,
            'document_type' => 'air',
            'file_path' => 's3://bucket/air_files/daily_report.air',
        ];

        $submitResponse = $this->postJson('/api/documents/process', $payload);
        $documentId = $submitResponse->json('document_id');

        // Act 2: Simulate N8n callback with deferred/batch processing status
        $callbackPayload = [
            'document_id' => $documentId,
            'status' => 'success',
            'execution_id' => 'exec-air-' . Str::random(10),
            'workflow_id' => 'wf-air-batch-processor',
            'execution_time_ms' => 8500,
            'extraction_result' => [
                'processing_status' => 'deferred',
                'batch_id' => 'batch-2026-02-10-001',
                'records_count' => 145,
                'message' => 'AIR file queued for batch processing',
            ],
        ];

        $timestamp = now()->timestamp;
        $hmacSignature = hash_hmac(
            'sha256',
            json_encode($callbackPayload),
            config('services.n8n.webhook_secret', 'default-secret')
        );

        $callbackResponse = $this->withHeaders([
            'X-Signature' => $hmacSignature,
            'X-Timestamp' => $timestamp,
        ])->postJson('/api/webhooks/n8n/extraction', $callbackPayload);

        // Assert
        $callbackResponse->assertStatus(200);

        $log = DocumentProcessingLog::where('document_id', $documentId)->first();
        $this->assertEquals('completed', $log->status);
        $this->assertEquals('deferred', $log->extraction_result['processing_status']);
        $this->assertEquals(145, $log->extraction_result['records_count']);
    }

    /** @test */
    public function it_handles_n8n_unavailable_error()
    {
        // Arrange - Mock N8n to return 503 Service Unavailable
        Http::fake([
            config('services.n8n.webhook_url') => Http::response([
                'error' => 'Service temporarily unavailable',
            ], 503),
        ]);

        // Act
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 303,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/invoice.pdf',
        ];

        $response = $this->postJson('/api/documents/process', $payload);

        // Assert
        $response->assertStatus(503)
            ->assertJson([
                'error' => 'Service unavailable',
                'message' => 'Document processing service is temporarily unavailable',
            ]);

        $this->assertDatabaseHas('document_processing_logs', [
            'company_id' => $this->company->id,
            'supplier_id' => 303,
            'status' => 'failed',
            'error_code' => 'ERR_N8N_UNAVAILABLE',
        ]);
    }

    /** @test */
    public function it_handles_n8n_timeout()
    {
        // Arrange - Mock N8n to timeout
        Http::fake([
            config('services.n8n.webhook_url') => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            },
        ]);

        // Act
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 404,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/invoice.pdf',
        ];

        $response = $this->postJson('/api/documents/process', $payload);

        // Assert - Should catch exception and return 500
        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Internal server error',
            ]);
    }

    /** @test */
    public function it_rejects_invalid_callback_data()
    {
        // Arrange - Create a document first
        $log = DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'queued',
        ]);

        // Act - Send callback with missing required fields
        $invalidPayload = [
            'document_id' => $log->document_id,
            'status' => 'success',
            // Missing: execution_id, workflow_id, execution_time_ms
        ];

        $timestamp = now()->timestamp;
        $hmacSignature = hash_hmac(
            'sha256',
            json_encode($invalidPayload),
            config('services.n8n.webhook_secret', 'default-secret')
        );

        $response = $this->withHeaders([
            'X-Signature' => $hmacSignature,
            'X-Timestamp' => $timestamp,
        ])->postJson('/api/webhooks/n8n/extraction', $invalidPayload);

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Validation failed',
            ])
            ->assertJsonValidationErrors(['execution_id', 'workflow_id', 'execution_time_ms']);
    }

    /** @test */
    public function it_handles_extraction_failure_from_n8n()
    {
        // Arrange
        Http::fake([
            config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 200),
        ]);

        // Act 1: Submit document
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 505,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/corrupted.pdf',
        ];

        $submitResponse = $this->postJson('/api/documents/process', $payload);
        $documentId = $submitResponse->json('document_id');

        // Act 2: Simulate N8n callback with error
        $callbackPayload = [
            'document_id' => $documentId,
            'status' => 'error',
            'execution_id' => 'exec-failed-' . Str::random(10),
            'workflow_id' => 'wf-pdf-processor',
            'execution_time_ms' => 1200,
            'error' => [
                'code' => 'ERR_EXTRACTION_FAILED',
                'message' => 'Failed to extract text from corrupted PDF',
                'context' => [
                    'pdf_version' => '1.7',
                    'page_with_error' => 2,
                ],
            ],
        ];

        $timestamp = now()->timestamp;
        $hmacSignature = hash_hmac(
            'sha256',
            json_encode($callbackPayload),
            config('services.n8n.webhook_secret', 'default-secret')
        );

        $callbackResponse = $this->withHeaders([
            'X-Signature' => $hmacSignature,
            'X-Timestamp' => $timestamp,
        ])->postJson('/api/webhooks/n8n/extraction', $callbackPayload);

        // Assert
        $callbackResponse->assertStatus(200);

        $log = DocumentProcessingLog::where('document_id', $documentId)->first();
        $this->assertEquals('failed', $log->status);
        $this->assertEquals('ERR_EXTRACTION_FAILED', $log->error_code);
        $this->assertEquals('Failed to extract text from corrupted PDF', $log->error_message);
        $this->assertNotNull($log->error_context);
        $this->assertEquals(2, $log->error_context['page_with_error']);
    }

    /** @test */
    public function it_prevents_duplicate_document_submission()
    {
        // Arrange - Create a document with a specific document_id
        $existingLog = DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
        ]);

        // Act - Try to send callback for the same document again
        $callbackPayload = [
            'document_id' => $existingLog->document_id,
            'status' => 'success',
            'execution_id' => 'exec-duplicate-' . Str::random(10),
            'workflow_id' => 'wf-test',
            'execution_time_ms' => 1000,
            'extraction_result' => ['test' => 'data'],
        ];

        $timestamp = now()->timestamp;
        $hmacSignature = hash_hmac(
            'sha256',
            json_encode($callbackPayload),
            config('services.n8n.webhook_secret', 'default-secret')
        );

        $response = $this->withHeaders([
            'X-Signature' => $hmacSignature,
            'X-Timestamp' => $timestamp,
        ])->postJson('/api/webhooks/n8n/extraction', $callbackPayload);

        // Assert
        $response->assertStatus(409)
            ->assertJson([
                'message' => 'Callback already processed',
                'document_id' => $existingLog->document_id,
                'status' => 'completed',
            ]);
    }

    /** @test */
    public function it_routes_documents_to_correct_supplier_workflows()
    {
        // Arrange - Track N8n requests
        $requests = [];
        Http::fake([
            config('services.n8n.webhook_url') => function ($request) use (&$requests) {
                $requests[] = json_decode($request->body(), true);
                return Http::response(['status' => 'accepted'], 200);
            },
        ]);

        // Act - Submit documents for different suppliers
        $suppliers = [111, 222, 333];

        foreach ($suppliers as $supplierId) {
            $payload = [
                'company_id' => $this->company->id,
                'supplier_id' => $supplierId,
                'document_type' => 'pdf',
                'file_path' => "s3://bucket/supplier_{$supplierId}/invoice.pdf",
            ];

            $this->postJson('/api/documents/process', $payload);
        }

        // Assert - Verify all requests were sent with correct supplier_id
        $this->assertCount(3, $requests);

        foreach ($requests as $index => $request) {
            $this->assertEquals($suppliers[$index], $request['supplier_id']);
            $this->assertEquals($this->company->id, $request['company_id']);
        }
    }

    /** @test */
    public function it_handles_concurrent_document_processing()
    {
        // Arrange
        Http::fake([
            config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 200),
        ]);

        $documentIds = [];

        // Act - Submit 10 documents rapidly
        for ($i = 1; $i <= 10; $i++) {
            $payload = [
                'company_id' => $this->company->id,
                'supplier_id' => 600 + $i,
                'document_type' => 'pdf',
                'file_path' => "s3://bucket/concurrent/doc_{$i}.pdf",
            ];

            $response = $this->postJson('/api/documents/process', $payload);
            $documentIds[] = $response->json('document_id');
        }

        // Assert - All documents queued successfully
        $this->assertCount(10, $documentIds);
        $this->assertCount(10, array_unique($documentIds)); // All unique

        foreach ($documentIds as $docId) {
            $this->assertDatabaseHas('document_processing_logs', [
                'document_id' => $docId,
                'status' => 'queued',
            ]);
        }

        // Act 2 - Simulate callbacks for all documents
        foreach ($documentIds as $index => $docId) {
            $callbackPayload = [
                'document_id' => $docId,
                'status' => 'success',
                'execution_id' => 'exec-concurrent-' . $index,
                'workflow_id' => 'wf-concurrent-test',
                'execution_time_ms' => 1000 + ($index * 100),
                'extraction_result' => ['index' => $index],
            ];

            $timestamp = now()->timestamp;
            $hmacSignature = hash_hmac(
                'sha256',
                json_encode($callbackPayload),
                config('services.n8n.webhook_secret', 'default-secret')
            );

            $this->withHeaders([
                'X-Signature' => $hmacSignature,
                'X-Timestamp' => $timestamp,
            ])->postJson('/api/webhooks/n8n/extraction', $callbackPayload);
        }

        // Assert - All documents completed without conflicts
        foreach ($documentIds as $docId) {
            $this->assertDatabaseHas('document_processing_logs', [
                'document_id' => $docId,
                'status' => 'completed',
            ]);
        }

        $completedCount = DocumentProcessingLog::where('status', 'completed')
            ->whereIn('document_id', $documentIds)
            ->count();

        $this->assertEquals(10, $completedCount);
    }

    /** @test */
    public function it_tracks_full_document_lifecycle()
    {
        // Arrange
        Http::fake([
            config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 200),
        ]);

        // Act 1: Submit document
        $payload = [
            'company_id' => $this->company->id,
            'supplier_id' => 777,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/lifecycle_test.pdf',
            'file_size_bytes' => 256000,
            'file_hash' => hash('sha256', 'lifecycle-test'),
        ];

        $submitResponse = $this->postJson('/api/documents/process', $payload);
        $documentId = $submitResponse->json('document_id');

        // Assert 1: Initial state
        $log = DocumentProcessingLog::where('document_id', $documentId)->first();
        $this->assertEquals('queued', $log->status);
        $this->assertNull($log->n8n_execution_id);
        $this->assertNull($log->callback_received_at);

        // Act 2: Simulate successful callback
        $callbackPayload = [
            'document_id' => $documentId,
            'status' => 'success',
            'execution_id' => 'exec-lifecycle-123',
            'workflow_id' => 'wf-lifecycle-test',
            'execution_time_ms' => 3500,
            'extraction_result' => [
                'tasks' => [['type' => 'flight', 'ref' => 'LH456']],
            ],
        ];

        $timestamp = now()->timestamp;
        $hmacSignature = hash_hmac(
            'sha256',
            json_encode($callbackPayload),
            config('services.n8n.webhook_secret', 'default-secret')
        );

        $this->withHeaders([
            'X-Signature' => $hmacSignature,
            'X-Timestamp' => $timestamp,
        ])->postJson('/api/webhooks/n8n/extraction', $callbackPayload);

        // Assert 2: Full lifecycle tracked
        $log->refresh();
        $this->assertEquals('completed', $log->status);
        $this->assertEquals('exec-lifecycle-123', $log->n8n_execution_id);
        $this->assertEquals('wf-lifecycle-test', $log->n8n_workflow_id);
        $this->assertEquals(3500, $log->processing_duration_ms);
        $this->assertNotNull($log->callback_received_at);
        $this->assertNotNull($log->extraction_result);
        $this->assertEquals('LH456', $log->extraction_result['tasks'][0]['ref']);

        // Assert 3: Verify no errors were logged
        $errorCount = DocumentError::where('document_processing_log_id', $log->id)->count();
        $this->assertEquals(0, $errorCount);
    }

    /** @test */
    public function it_validates_hmac_signature_on_callback()
    {
        // Arrange
        $log = DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'queued',
        ]);

        $callbackPayload = [
            'document_id' => $log->document_id,
            'status' => 'success',
            'execution_id' => 'exec-hmac-test',
            'workflow_id' => 'wf-hmac-test',
            'execution_time_ms' => 1000,
        ];

        $timestamp = now()->timestamp;

        // Act - Send with invalid HMAC signature
        $response = $this->withHeaders([
            'X-Signature' => 'invalid-signature-12345',
            'X-Timestamp' => $timestamp,
        ])->postJson('/api/webhooks/n8n/extraction', $callbackPayload);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
                'message' => 'Webhook signature verification failed',
            ]);

        // Verify log status unchanged
        $log->refresh();
        $this->assertEquals('queued', $log->status);
    }

    /** @test */
    public function it_rejects_callbacks_with_expired_timestamp()
    {
        // Arrange
        $log = DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'queued',
        ]);

        $callbackPayload = [
            'document_id' => $log->document_id,
            'status' => 'success',
            'execution_id' => 'exec-timestamp-test',
            'workflow_id' => 'wf-timestamp-test',
            'execution_time_ms' => 1000,
        ];

        // Use timestamp from 10 minutes ago (exceeds 5 minute window)
        $expiredTimestamp = now()->subMinutes(10)->timestamp;
        $hmacSignature = hash_hmac(
            'sha256',
            json_encode($callbackPayload),
            config('services.n8n.webhook_secret', 'default-secret')
        );

        // Act
        $response = $this->withHeaders([
            'X-Signature' => $hmacSignature,
            'X-Timestamp' => $expiredTimestamp,
        ])->postJson('/api/webhooks/n8n/extraction', $callbackPayload);

        // Assert
        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
            ]);

        // Verify log status unchanged
        $log->refresh();
        $this->assertEquals('queued', $log->status);
    }
}
