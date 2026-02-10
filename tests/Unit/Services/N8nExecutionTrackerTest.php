<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\N8nExecutionTracker;
use App\Models\DocumentProcessingLog;
use App\Models\DocumentError;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class N8nExecutionTrackerTest extends TestCase
{
    use RefreshDatabase;

    private N8nExecutionTracker $tracker;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tracker = new N8nExecutionTracker();
        $this->company = Company::factory()->create();
    }

    /** @test */
    public function it_can_start_execution_tracking()
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => $documentId,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'queued',
        ]);

        $payload = [
            'supplier_id' => 'test_supplier',
            'file_path' => 's3://bucket/test.pdf',
        ];

        // Act
        $result = $this->tracker->startExecution($documentId, $payload, 'workflow-123');

        // Assert
        $this->assertEquals('processing', $result->status);
        $this->assertNotNull($result->started_at);
        $this->assertEquals($payload, $result->input_payload);
        $this->assertEquals('workflow-123', $result->n8n_workflow_id);
    }

    /** @test */
    public function it_can_complete_execution_successfully()
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => $documentId,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'processing',
            'started_at' => now()->subSeconds(30),
        ]);

        $result = [
            'extracted_tasks' => [
                ['type' => 'flight', 'reference' => 'EK123'],
            ],
        ];

        // Act
        $completed = $this->tracker->completeExecution($documentId, $result, 'exec-456');

        // Assert
        $this->assertEquals('completed', $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertNotNull($completed->duration_ms);
        $this->assertEquals($result, $completed->output_data);
        $this->assertEquals('exec-456', $completed->n8n_execution_id);
        $this->assertGreaterThan(0, $completed->duration_ms);
    }

    /** @test */
    public function it_can_fail_execution_with_error_details()
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => $documentId,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'processing',
            'started_at' => now()->subSeconds(10),
            'input_payload' => ['test' => 'data'],
        ]);

        $error = [
            'code' => 'ERR_TIMEOUT',
            'message' => 'Processing timeout after 30s',
            'context' => [
                'failed_at_node' => 'OpenAI Vision',
                'request_duration_ms' => 30100,
            ],
        ];

        // Act
        $failed = $this->tracker->failExecution($documentId, $error, 'exec-789');

        // Assert
        $this->assertEquals('failed', $failed->status);
        $this->assertEquals('ERR_TIMEOUT', $failed->error_code);
        $this->assertEquals('Processing timeout after 30s', $failed->error_message);
        $this->assertTrue($failed->needs_review);
        $this->assertNotNull($failed->completed_at);
        $this->assertNotNull($failed->duration_ms);

        // Check DocumentError was created
        $this->assertDatabaseHas('document_errors', [
            'document_processing_log_id' => $failed->id,
            'error_code' => 'ERR_TIMEOUT',
            'error_type' => 'transient',
        ]);
    }

    /** @test */
    public function it_correctly_classifies_transient_errors()
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => $documentId,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $error = [
            'code' => 'ERR_RATE_LIMIT',
            'message' => 'OpenAI rate limit exceeded',
        ];

        // Act
        $this->tracker->failExecution($documentId, $error);

        // Assert
        $documentError = DocumentError::where('document_processing_log_id', $log->id)->first();
        $this->assertEquals('transient', $documentError->error_type);
        $this->assertTrue($documentError->isTransient());
    }

    /** @test */
    public function it_correctly_classifies_non_transient_errors()
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => $documentId,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $error = [
            'code' => 'ERR_PARSE_FAILURE',
            'message' => 'Invalid JSON format',
        ];

        // Act
        $this->tracker->failExecution($documentId, $error);

        // Assert
        $documentError = DocumentError::where('document_processing_log_id', $log->id)->first();
        $this->assertEquals('non_transient', $documentError->error_type);
        $this->assertFalse($documentError->isTransient());
    }

    /** @test */
    public function it_correctly_classifies_system_errors()
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => $documentId,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $error = [
            'code' => 'ERR_N8N_UNAVAILABLE',
            'message' => 'N8n service is down',
        ];

        // Act
        $this->tracker->failExecution($documentId, $error);

        // Assert
        $documentError = DocumentError::where('document_processing_log_id', $log->id)->first();
        $this->assertEquals('system', $documentError->error_type);
    }

    /** @test */
    public function it_can_get_execution_metrics()
    {
        // Arrange - create some executions
        $now = now();

        // Successful executions
        for ($i = 0; $i < 8; $i++) {
            $docId = (string) Str::uuid();
            DocumentProcessingLog::create([
                'company_id' => $this->company->id,
                'document_id' => $docId,
                'document_type' => 'pdf',
                'file_path' => "s3://bucket/test-{$i}.pdf",
                'status' => 'completed',
                'started_at' => $now->copy()->subMinutes(30),
                'completed_at' => $now->copy()->subMinutes(29),
                'duration_ms' => 1000 * ($i + 1),
            ]);
        }

        // Failed executions with errors
        for ($i = 0; $i < 2; $i++) {
            $docId = (string) Str::uuid();
            $log = DocumentProcessingLog::create([
                'company_id' => $this->company->id,
                'document_id' => $docId,
                'document_type' => 'pdf',
                'file_path' => "s3://bucket/failed-{$i}.pdf",
                'status' => 'failed',
                'started_at' => $now->copy()->subMinutes(20),
                'completed_at' => $now->copy()->subMinutes(19),
                'duration_ms' => 5000,
                'error_code' => 'ERR_TIMEOUT',
            ]);

            DocumentError::create([
                'document_processing_log_id' => $log->id,
                'error_type' => 'transient',
                'error_code' => 'ERR_TIMEOUT',
                'error_message' => 'Timeout',
            ]);
        }

        // Act
        $metrics = $this->tracker->getExecutionMetrics('day', $this->company->id);

        // Assert
        $this->assertEquals(10, $metrics['total_executions']);
        $this->assertEquals(8, $metrics['completed']);
        $this->assertEquals(2, $metrics['failed']);
        $this->assertEquals(80.0, $metrics['success_rate']);
        $this->assertArrayHasKey('avg_duration_ms', $metrics);
        $this->assertArrayHasKey('errors_by_type', $metrics);
        $this->assertEquals(2, $metrics['errors_by_type']['transient']);
    }

    /** @test */
    public function it_throws_exception_for_non_existent_document()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Document not found');

        $this->tracker->startExecution('non-existent-id', []);
    }

    /** @test */
    public function it_calculates_duration_correctly()
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $startTime = now();
        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => $documentId,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'processing',
            'started_at' => $startTime,
        ]);

        // Wait a tiny bit and complete
        usleep(10000); // 10ms

        $result = ['extracted_tasks' => []];
        $completed = $this->tracker->completeExecution($documentId, $result);

        // Assert
        $this->assertNotNull($completed->duration_ms);
        $this->assertGreaterThan(0, $completed->duration_ms);
    }
}
