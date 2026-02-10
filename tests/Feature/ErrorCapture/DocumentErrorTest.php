<?php

namespace Tests\Feature\ErrorCapture;

use Tests\TestCase;
use App\Models\DocumentProcessingLog;
use App\Models\DocumentError;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class DocumentErrorTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private DocumentProcessingLog $log;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();

        $this->log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => (string) Str::uuid(),
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'failed',
        ]);
    }

    /** @test */
    public function it_can_create_document_error_with_full_context()
    {
        // Arrange
        $errorData = [
            'document_processing_log_id' => $this->log->id,
            'error_type' => DocumentError::TYPE_TRANSIENT,
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Processing timeout after 30s',
            'stack_trace' => 'Stack trace here...',
            'input_context' => [
                'supplier_id' => 'test_supplier',
                'file_size' => 2048576,
            ],
        ];

        // Act
        $error = DocumentError::create($errorData);

        // Assert
        $this->assertDatabaseHas('document_errors', [
            'id' => $error->id,
            'error_type' => 'transient',
            'error_code' => 'ERR_TIMEOUT',
            'retry_count' => 0,
        ]);

        $this->assertEquals('test_supplier', $error->input_context['supplier_id']);
        $this->assertNull($error->resolved_at);
    }

    /** @test */
    public function it_can_scope_unresolved_errors()
    {
        // Arrange
        DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => 'transient',
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Error 1',
            'resolved_at' => now(),
        ]);

        DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => 'transient',
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Error 2',
        ]);

        // Act
        $unresolvedCount = DocumentError::unresolved()->count();

        // Assert
        $this->assertEquals(1, $unresolvedCount);
    }

    /** @test */
    public function it_can_scope_by_error_type()
    {
        // Arrange
        DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => DocumentError::TYPE_TRANSIENT,
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Transient error',
        ]);

        DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => DocumentError::TYPE_SYSTEM,
            'error_code' => 'ERR_N8N_UNAVAILABLE',
            'error_message' => 'System error',
        ]);

        // Act
        $transientCount = DocumentError::byType(DocumentError::TYPE_TRANSIENT)->count();
        $systemCount = DocumentError::system()->count();

        // Assert
        $this->assertEquals(1, $transientCount);
        $this->assertEquals(1, $systemCount);
    }

    /** @test */
    public function it_can_scope_recent_errors()
    {
        // Arrange
        $oldError = DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => 'transient',
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Old error',
        ]);
        $oldError->created_at = now()->subDays(10);
        $oldError->save();

        $recentError = DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => 'transient',
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Recent error',
        ]);

        // Act
        $recentCount = DocumentError::recent(7)->count();

        // Assert
        $this->assertEquals(1, $recentCount);
    }

    /** @test */
    public function it_can_mark_error_as_resolved()
    {
        // Arrange
        $error = DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => 'transient',
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Test error',
        ]);

        $this->assertFalse($error->isResolved());

        // Act
        $error->markAsResolved($this->user->id, 'Fixed by reprocessing');

        // Assert
        $error->refresh();
        $this->assertTrue($error->isResolved());
        $this->assertNotNull($error->resolved_at);
        $this->assertEquals($this->user->id, $error->resolved_by);
        $this->assertEquals('Fixed by reprocessing', $error->resolution_notes);
    }

    /** @test */
    public function it_can_increment_retry_count()
    {
        // Arrange
        $error = DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => 'transient',
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Test error',
            'retry_count' => 0,
        ]);

        // Act
        $error->incrementRetry();
        $error->refresh();

        // Assert
        $this->assertEquals(1, $error->retry_count);
        $this->assertNotNull($error->last_retry_at);

        // Increment again
        $error->incrementRetry();
        $error->refresh();
        $this->assertEquals(2, $error->retry_count);
    }

    /** @test */
    public function it_has_relationship_with_document_processing_log()
    {
        // Arrange
        $error = DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => 'transient',
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Test error',
        ]);

        // Act
        $relatedLog = $error->documentProcessingLog;

        // Assert
        $this->assertInstanceOf(DocumentProcessingLog::class, $relatedLog);
        $this->assertEquals($this->log->id, $relatedLog->id);
    }

    /** @test */
    public function document_processing_log_has_relationship_with_errors()
    {
        // Arrange
        DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => 'transient',
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Error 1',
        ]);

        DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => 'system',
            'error_code' => 'ERR_N8N_UNAVAILABLE',
            'error_message' => 'Error 2',
        ]);

        // Act
        $errors = $this->log->errors;

        // Assert
        $this->assertCount(2, $errors);
        $this->assertInstanceOf(DocumentError::class, $errors->first());
    }

    /** @test */
    public function it_can_mark_document_for_review()
    {
        // Arrange
        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => (string) Str::uuid(),
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'processing',
        ]);

        // Act
        $log->markForReview('ERR_PARSE_FAILURE', 'Invalid JSON format');

        // Assert
        $log->refresh();
        $this->assertTrue($log->needs_review);
        $this->assertEquals('failed', $log->status);
        $this->assertEquals('ERR_PARSE_FAILURE', $log->error_code);
        $this->assertEquals('Invalid JSON format', $log->error_message);
        $this->assertNull($log->reviewed_at);
    }

    /** @test */
    public function it_can_mark_review_as_completed()
    {
        // Arrange
        $log = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => (string) Str::uuid(),
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test.pdf',
            'status' => 'failed',
            'needs_review' => true,
        ]);

        // Act
        $log->markReviewCompleted($this->user->id, 'Reviewed and reprocessed');

        // Assert
        $log->refresh();
        $this->assertNotNull($log->reviewed_at);
        $this->assertEquals($this->user->id, $log->reviewed_by);
        $this->assertEquals('Reviewed and reprocessed', $log->review_notes);
    }

    /** @test */
    public function it_can_scope_documents_needing_review()
    {
        // Arrange
        DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => (string) Str::uuid(),
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test1.pdf',
            'status' => 'failed',
            'needs_review' => true,
        ]);

        DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => (string) Str::uuid(),
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test2.pdf',
            'status' => 'failed',
            'needs_review' => true,
            'reviewed_at' => now(),
        ]);

        DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => (string) Str::uuid(),
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test3.pdf',
            'status' => 'completed',
        ]);

        // Act
        $needsReviewCount = DocumentProcessingLog::needsReview()->count();

        // Assert
        $this->assertEquals(1, $needsReviewCount);
    }

    /** @test */
    public function it_can_filter_errors_by_multiple_scopes()
    {
        // Arrange
        $log2 = DocumentProcessingLog::create([
            'company_id' => $this->company->id,
            'document_id' => (string) Str::uuid(),
            'document_type' => 'pdf',
            'file_path' => 's3://bucket/test2.pdf',
            'status' => 'failed',
        ]);

        // Recent unresolved transient error
        DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => DocumentError::TYPE_TRANSIENT,
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Should be found',
        ]);

        // Resolved transient error
        DocumentError::create([
            'document_processing_log_id' => $this->log->id,
            'error_type' => DocumentError::TYPE_TRANSIENT,
            'error_code' => 'ERR_TIMEOUT',
            'error_message' => 'Already resolved',
            'resolved_at' => now(),
        ]);

        // Recent unresolved system error
        DocumentError::create([
            'document_processing_log_id' => $log2->id,
            'error_type' => DocumentError::TYPE_SYSTEM,
            'error_code' => 'ERR_N8N_UNAVAILABLE',
            'error_message' => 'Wrong type',
        ]);

        // Act
        $count = DocumentError::unresolved()
            ->transient()
            ->recent(7)
            ->count();

        // Assert
        $this->assertEquals(1, $count);
    }
}
