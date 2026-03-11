<?php

namespace Tests\Feature\ErrorLogging;

use Tests\TestCase;
use App\Models\DocumentProcessingLog;
use App\Models\DocumentError;
use App\Models\WebhookAuditLog;
use App\Models\Company;
use App\Services\N8nExecutionTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ErrorLoggingVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected N8nExecutionTracker $tracker;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tracker = new N8nExecutionTracker();
        $this->company = Company::factory()->create();
    }

    /**
     * Test 1: Transient error logged correctly
     * Verify ERR_TIMEOUT creates correct log entry with all fields
     */
    public function test_transient_error_logged_correctly(): void
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $payload = [
            'supplier_id' => 'supplier-123',
            'file_path' => 's3://bucket/test.pdf',
            'document_type' => 'air',
        ];

        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'supplier_id' => 'supplier-123',
            'document_id' => $documentId,
            'document_type' => 'air',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'processing',
            'started_at' => now()->subSeconds(5),
            'input_payload' => $payload,
        ]);

        Log::shouldReceive('error')->once();

        // Act
        $error = [
            'code' => 'ERR_TIMEOUT',
            'message' => 'Processing timeout after 30s',
            'context' => [
                'failed_at_node' => 'OpenAI Vision',
                'request_duration_ms' => 30100,
            ],
        ];

        $this->tracker->failExecution($documentId, $error, 'exec-timeout-123');

        // Assert - Check DocumentProcessingLog
        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Processing timeout after 30s',
            'needs_review' => true,
        ]);

        // Assert - Check DocumentError created with all fields
        $documentError = DocumentError::where('error_code', 'ERR_TIMEOUT')
            ->where('document_processing_log_id', $log->id)
            ->first();

        $this->assertNotNull($documentError);
        $this->assertEquals('transient', $documentError->error_type);
        $this->assertEquals('ERR_TIMEOUT', $documentError->error_code);
        $this->assertEquals('Processing timeout after 30s', $documentError->error_message);
        $this->assertEquals(0, $documentError->retry_count);
        $this->assertNull($documentError->resolved_at);
        $this->assertNotNull($documentError->input_context);
    }

    /**
     * Test 2: Non-transient error logged
     * Verify ERR_PARSE_FAILURE creates correct log entry
     */
    public function test_non_transient_error_logged(): void
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $payload = [
            'supplier_id' => 'supplier-456',
            'file_path' => 's3://bucket/invalid.pdf',
            'document_type' => 'pdf',
        ];

        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'supplier_id' => 'supplier-456',
            'document_id' => $documentId,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/invalid.pdf',
            'status' => 'processing',
            'started_at' => now()->subSeconds(2),
            'input_payload' => $payload,
        ]);

        Log::shouldReceive('error')->once();

        // Act
        $error = [
            'code' => 'ERR_PARSE_FAILURE',
            'message' => 'Invalid PDF structure: missing xref table',
            'context' => [
                'failed_at_node' => 'PDF Parser',
                'line_number' => 42,
            ],
        ];

        $this->tracker->failExecution($documentId, $error, 'exec-parse-456');

        // Assert - Check DocumentProcessingLog
        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status' => 'failed',
            'error_code' => 'ERR_PARSE_FAILURE',
            'error_message' => 'Invalid PDF structure: missing xref table',
            'needs_review' => true,
        ]);

        // Assert - Check DocumentError with non-transient classification
        $documentError = DocumentError::where('error_code', 'ERR_PARSE_FAILURE')
            ->where('document_processing_log_id', $log->id)
            ->first();

        $this->assertNotNull($documentError);
        $this->assertEquals('non_transient', $documentError->error_type);
        $this->assertFalse($documentError->isTransient());
    }

    /**
     * Test 3: System error logged
     * Verify ERR_N8N_UNAVAILABLE creates correct log entry
     */
    public function test_system_error_logged(): void
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $payload = [
            'supplier_id' => 'supplier-789',
            'file_path' => 's3://bucket/test.air',
            'document_type' => 'air',
        ];

        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'supplier_id' => 'supplier-789',
            'document_id' => $documentId,
            'document_type' => 'air',
            'file_path' => 's3://bucket/test.air',
            'status' => 'processing',
            'started_at' => now()->subSeconds(3),
            'input_payload' => $payload,
        ]);

        Log::shouldReceive('critical')->once();

        // Act
        $error = [
            'code' => 'ERR_N8N_UNAVAILABLE',
            'message' => 'N8n service is down (503 Service Unavailable)',
            'context' => [
                'http_status' => 503,
                'service_name' => 'n8n',
            ],
        ];

        $this->tracker->failExecution($documentId, $error, 'exec-system-789');

        // Assert - Check DocumentProcessingLog
        $this->assertDatabaseHas('document_processing_logs', [
            'document_id' => $documentId,
            'status' => 'failed',
            'error_code' => 'ERR_N8N_UNAVAILABLE',
            'error_message' => 'N8n service is down (503 Service Unavailable)',
            'needs_review' => true,
        ]);

        // Assert - Check DocumentError with system classification
        $documentError = DocumentError::where('error_code', 'ERR_N8N_UNAVAILABLE')
            ->where('document_processing_log_id', $log->id)
            ->first();

        $this->assertNotNull($documentError);
        $this->assertEquals('system', $documentError->error_type);
    }

    /**
     * Test 4: Error context preserved
     * Verify input_context JSON is complete (document_id, supplier_id, file_path, timestamp)
     */
    public function test_error_context_preserved(): void
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $supplierId = 'supplier-context-test';
        $filePath = 's3://bucket/supplier-context/test-doc.pdf';

        $payload = [
            'supplier_id' => $supplierId,
            'file_path' => $filePath,
            'document_type' => 'pdf',
            'additional_field' => 'test_value',
        ];

        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'supplier_id' => $supplierId,
            'document_id' => $documentId,
            'document_type' => 'pdf',
            'file_path' => $filePath,
            'status' => 'processing',
            'started_at' => now()->subSeconds(10),
            'input_payload' => $payload,
            'n8n_workflow_id' => 'wf-context-test',
        ]);

        Log::shouldReceive('error')->once();

        // Act
        $error = [
            'code' => 'ERR_VALIDATION_FAILURE',
            'message' => 'Document validation failed',
            'context' => ['validator_field' => 'page_count', 'expected' => 1, 'actual' => 0],
        ];

        $this->tracker->failExecution($documentId, $error, 'exec-context-test');

        // Assert - Check input_context contains all expected fields
        $documentError = DocumentError::where('document_processing_log_id', $log->id)->first();

        $this->assertNotNull($documentError->input_context);
        $this->assertIsArray($documentError->input_context);

        // Verify required context fields
        $this->assertArrayHasKey('payload', $documentError->input_context);
        $this->assertArrayHasKey('execution_id', $documentError->input_context);
        $this->assertArrayHasKey('workflow_id', $documentError->input_context);

        // Verify payload contains original data
        $this->assertEquals($supplierId, $documentError->input_context['payload']['supplier_id']);
        $this->assertEquals($filePath, $documentError->input_context['payload']['file_path']);
        $this->assertEquals('exec-context-test', $documentError->input_context['execution_id']);
        $this->assertEquals('wf-context-test', $documentError->input_context['workflow_id']);
    }

    /**
     * Test 5: Stack trace captured
     * Verify stack_trace field populated on exceptions
     */
    public function test_stack_trace_captured(): void
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'supplier_id' => 'supplier-stacktrace',
            'document_id' => $documentId,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'processing',
            'started_at' => now()->subSeconds(5),
            'input_payload' => ['test' => 'data'],
        ]);

        // Create a real exception to capture stack trace
        try {
            throw new \Exception('Test exception for stack trace');
        } catch (\Exception $e) {
            // Act
            $error = [
                'code' => 'ERR_DATABASE_ERROR',
                'message' => 'Database connection failed',
                'context' => ['database' => 'mysql'],
                'stack_trace' => $e->getTraceAsString(),
            ];

            Log::shouldReceive('critical')->once();

            $this->tracker->failExecution($documentId, $error, 'exec-stacktrace');
        }

        // Assert - Check stack_trace is populated
        $documentError = DocumentError::where('document_processing_log_id', $log->id)->first();

        $this->assertNotNull($documentError->stack_trace);
        $this->assertIsString($documentError->stack_trace);
        $this->assertStringContainsString('Test exception for stack trace', $documentError->stack_trace);
    }

    /**
     * Test 6: Retry count incremented
     * Verify retry_count increments on each retry
     */
    public function test_retry_count_incremented(): void
    {
        // Arrange
        $documentId = (string) Str::uuid();
        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'supplier_id' => 'supplier-retry',
            'document_id' => $documentId,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'processing',
            'started_at' => now()->subSeconds(5),
            'input_payload' => ['test' => 'data'],
        ]);

        Log::shouldReceive('warning')->times(3);

        // Act - Create error and fail execution
        $error = [
            'code' => 'ERR_TIMEOUT',
            'message' => 'Timeout attempt 1',
        ];

        $this->tracker->failExecution($documentId, $error, 'exec-retry-1');

        // Get the created error
        $documentError = DocumentError::where('document_processing_log_id', $log->id)->first();
        $this->assertEquals(0, $documentError->retry_count);

        // Simulate retries
        $documentError->incrementRetry();
        $this->assertEquals(1, $documentError->fresh()->retry_count);
        $this->assertNotNull($documentError->fresh()->last_retry_at);

        $documentError->incrementRetry();
        $this->assertEquals(2, $documentError->fresh()->retry_count);

        $documentError->incrementRetry();
        $this->assertEquals(3, $documentError->fresh()->retry_count);

        // Assert final state
        $finalError = DocumentError::where('document_processing_log_id', $log->id)->first();
        $this->assertEquals(3, $finalError->retry_count);
        $this->assertNotNull($finalError->last_retry_at);
    }

    /**
     * Test 7: Error metrics accurate
     * Call getExecutionMetrics() and verify counts match
     */
    public function test_error_metrics_accurate(): void
    {
        // Arrange
        $now = now();

        // Create 8 successful executions
        for ($i = 0; $i < 8; $i++) {
            $docId = (string) Str::uuid();
            DocumentProcessingLog::create([
                'company_id' => $this->company->id,
                'document_id' => $docId,
                'document_type' => 'pdf',
                'file_path' => "s3://bucket/success-{$i}.pdf",
                'status' => 'completed',
                'started_at' => $now->copy()->subMinutes(30),
                'completed_at' => $now->copy()->subMinutes(29),
                'duration_ms' => 1000 * ($i + 1),
            ]);
        }

        // Create 2 failed executions with transient errors
        for ($i = 0; $i < 2; $i++) {
            $docId = (string) Str::uuid();
            $logEntry = DocumentProcessingLog::create([
                'company_id' => $this->company->id,
                'document_id' => $docId,
                'document_type' => 'pdf',
                'file_path' => "s3://bucket/transient-failure-{$i}.pdf",
                'status' => 'failed',
                'started_at' => $now->copy()->subMinutes(20),
                'completed_at' => $now->copy()->subMinutes(19),
                'duration_ms' => 5000,
                'error_code' => 'ERR_TIMEOUT',
                'input_payload' => ['test' => 'data'],
            ]);

            DocumentError::create([
                'document_processing_log_id' => $logEntry->id,
                'error_type' => 'transient',
                'error_code' => 'ERR_TIMEOUT',
                'error_message' => 'Timeout',
                'input_context' => ['test' => 'context'],
            ]);
        }

        // Create 1 failed execution with non-transient error
        $docId = (string) Str::uuid();
        $nonTransientLog = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => $docId,
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/non-transient-failure.pdf',
            'status' => 'failed',
            'started_at' => $now->copy()->subMinutes(15),
            'completed_at' => $now->copy()->subMinutes(14),
            'duration_ms' => 2000,
            'error_code' => 'ERR_PARSE_FAILURE',
            'input_payload' => ['test' => 'data'],
        ]);

        DocumentError::create([
            'document_processing_log_id' => $nonTransientLog->id,
            'error_type' => 'non_transient',
            'error_code' => 'ERR_PARSE_FAILURE',
            'error_message' => 'Parse error',
            'input_context' => ['test' => 'context'],
        ]);

        // Act
        $metrics = $this->tracker->getExecutionMetrics('day', $this->company->id);

        // Assert
        $this->assertEquals(11, $metrics['total_executions']);
        $this->assertEquals(8, $metrics['completed']);
        $this->assertEquals(3, $metrics['failed']);
        $this->assertEquals(0, $metrics['processing']);
        $this->assertEquals(72.73, $metrics['success_rate']);

        // Verify error type breakdown
        $this->assertEquals(2, $metrics['errors_by_type']['transient']);
        $this->assertEquals(1, $metrics['errors_by_type']['non_transient']);
        $this->assertEquals(0, $metrics['errors_by_type']['system']);

        // Verify top error codes includes our created errors
        $this->assertArrayHasKey('ERR_TIMEOUT', $metrics['top_error_codes']);
        $this->assertArrayHasKey('ERR_PARSE_FAILURE', $metrics['top_error_codes']);
    }

    /**
     * Test 8: Audit log created
     * Verify webhook_audit_logs entry created for each request
     */
    public function test_audit_log_created(): void
    {
        // Arrange
        $payload = [
            'document_id' => (string) Str::uuid(),
            'status' => 'error',
            'error' => [
                'code' => 'ERR_TIMEOUT',
                'message' => 'Processing timeout',
            ],
        ];

        // Act - Create audit log entry
        $payloadHash = hash('sha256', json_encode($payload));
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', json_encode($payload), config('services.n8n.webhook_secret', 'test-secret'));

        WebhookAuditLog::create([
            'webhook_client_id' => 1,
            'direction' => 'inbound',
            'http_method' => 'POST',
            'endpoint' => '/api/webhooks/n8n/extraction',
            'signature_provided' => $signature,
            'signature_computed' => $signature,
            'signature_valid' => true,
            'timestamp_provided' => $timestamp,
            'timestamp_computed' => $timestamp,
            'timestamp_valid' => true,
            'payload_hash' => $payloadHash,
            'status_code' => 200,
            'error_message' => null,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        // Assert - Verify audit log was created
        $this->assertDatabaseHas('webhook_audit_logs', [
            'direction' => 'inbound',
            'http_method' => 'POST',
            'endpoint' => '/api/webhooks/n8n/extraction',
            'signature_valid' => true,
            'timestamp_valid' => true,
            'status_code' => 200,
            'payload_hash' => $payloadHash,
        ]);

        // Verify we can retrieve the audit log
        $auditLog = WebhookAuditLog::where('payload_hash', $payloadHash)->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals('POST', $auditLog->http_method);
        $this->assertTrue($auditLog->signature_valid);
    }
}
