<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\DocumentProcessingLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManualInterventionEnhancedTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
    }

    /** @test */
    public function it_can_bulk_retry_multiple_documents()
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $failedDocs = DocumentProcessingLog::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.manual-intervention.bulk-retry'), [
                'document_ids' => $failedDocs->pluck('id')->toArray(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify all documents are now queued
        foreach ($failedDocs as $doc) {
            $doc->refresh();
            $this->assertEquals('queued', $doc->status);
            $this->assertNull($doc->error_code);
        }
    }

    /** @test */
    public function it_handles_bulk_retry_validation_errors()
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.manual-intervention.bulk-retry'), [
                'document_ids' => [],
            ]);

        $response->assertSessionHasErrors('document_ids');
    }

    /** @test */
    public function it_skips_non_failed_documents_in_bulk_retry()
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $failedDoc = DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
        ]);

        $completedDoc = DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.manual-intervention.bulk-retry'), [
                'document_ids' => [$failedDoc->id, $completedDoc->id],
            ]);

        $response->assertRedirect();

        // Failed doc should be queued
        $failedDoc->refresh();
        $this->assertEquals('queued', $failedDoc->status);

        // Completed doc should remain completed
        $completedDoc->refresh();
        $this->assertEquals('completed', $completedDoc->status);
    }

    /** @test */
    public function it_exports_failed_documents_to_csv()
    {
        DocumentProcessingLog::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_PARSE',
            'error_message' => 'Parse error',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.manual-intervention.export-csv'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');

        // Check CSV contains expected data
        $content = $response->streamedContent();
        $this->assertStringContainsString('Document ID', $content);
        $this->assertStringContainsString('ERR_PARSE', $content);
        $this->assertStringContainsString('Parse error', $content);
    }

    /** @test */
    public function it_exports_csv_with_filters_applied()
    {
        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'supplier_id' => 'supplier_a',
        ]);

        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_PARSE',
            'supplier_id' => 'supplier_b',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.manual-intervention.export-csv', [
                'supplier_id' => 'supplier_a',
            ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('supplier_a', $content);
        $this->assertStringNotContainsString('supplier_b', $content);
    }

    /** @test */
    public function it_displays_error_timeline_page()
    {
        $log = DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
            'created_at' => now()->subHours(2),
            'callback_received_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.manual-intervention.timeline', $log));

        $response->assertStatus(200);
        $response->assertViewIs('admin.manual-intervention.timeline');
        $response->assertSee('Error Timeline');
        $response->assertSee($log->document_id);
    }

    /** @test */
    public function it_filters_by_error_type()
    {
        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
        ]);

        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_PARSE',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.manual-intervention.index', [
                'error_code' => 'ERR_TIMEOUT',
            ]));

        $response->assertStatus(200);
        $response->assertSee('ERR_TIMEOUT');
    }

    /** @test */
    public function it_filters_by_date_range()
    {
        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'created_at' => now()->subDays(5),
        ]);

        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.manual-intervention.index', [
                'date_from' => now()->subDays(2)->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        // Should only show 1 document (from yesterday)
        $this->assertEquals(1, $response->viewData('failedDocuments')->total());
    }

    /** @test */
    public function it_paginates_results_correctly()
    {
        DocumentProcessingLog::factory()->count(55)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.manual-intervention.index'));

        $response->assertStatus(200);
        $failedDocuments = $response->viewData('failedDocuments');

        // Default pagination is 50 per page
        $this->assertEquals(50, $failedDocuments->perPage());
        $this->assertEquals(55, $failedDocuments->total());
        $this->assertEquals(2, $failedDocuments->lastPage());
    }
}
