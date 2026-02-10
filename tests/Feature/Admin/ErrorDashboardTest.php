<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\DocumentProcessingLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErrorDashboardTest extends TestCase
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
    public function it_displays_error_dashboard_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('admin.error-dashboard.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.error-dashboard.index');
        $response->assertSee('Error Analytics Dashboard');
    }

    /** @test */
    public function it_returns_metrics_json_with_summary_stats()
    {
        // Create test data
        DocumentProcessingLog::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
            'processing_duration_ms' => 2000,
        ]);

        DocumentProcessingLog::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.error-dashboard.metrics', ['range' => '24h']));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary' => [
                'total_processed',
                'total_failed',
                'total_completed',
                'failure_rate',
                'success_rate',
                'avg_processing_time_seconds',
            ],
            'error_trend',
            'error_type_distribution',
            'supplier_errors',
            'document_type_errors',
            'recent_errors',
        ]);

        $data = $response->json();
        $this->assertEquals(8, $data['summary']['total_processed']);
        $this->assertEquals(3, $data['summary']['total_failed']);
        $this->assertEquals(5, $data['summary']['total_completed']);
    }

    /** @test */
    public function it_calculates_failure_rate_correctly()
    {
        // 2 completed, 8 failed = 80% failure rate
        DocumentProcessingLog::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
        ]);

        DocumentProcessingLog::factory()->count(8)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_PARSE',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.error-dashboard.metrics', ['range' => '24h']));

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(80.0, $data['summary']['failure_rate']);
        $this->assertEquals(20.0, $data['summary']['success_rate']);
    }

    /** @test */
    public function it_groups_errors_by_type()
    {
        DocumentProcessingLog::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TIMEOUT',
        ]);

        DocumentProcessingLog::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_PARSE',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.error-dashboard.metrics', ['range' => '24h']));

        $data = $response->json();
        $errorTypes = collect($data['error_type_distribution']);

        $this->assertCount(2, $errorTypes);
        $this->assertEquals('ERR_TIMEOUT', $errorTypes->first()['error_code']);
        $this->assertEquals(3, $errorTypes->first()['count']);
    }

    /** @test */
    public function it_shows_per_supplier_error_rates()
    {
        // Supplier A: 2 failed, 8 completed = 20% error rate
        DocumentProcessingLog::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'supplier_id' => 'supplier_a',
            'status' => 'failed',
        ]);

        DocumentProcessingLog::factory()->count(8)->create([
            'company_id' => $this->company->id,
            'supplier_id' => 'supplier_a',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.error-dashboard.metrics', ['range' => '24h']));

        $data = $response->json();
        $supplierErrors = collect($data['supplier_errors']);

        $this->assertGreaterThan(0, $supplierErrors->count());
        $supplierA = $supplierErrors->firstWhere('supplier_id', 'supplier_a');
        $this->assertEquals(10, $supplierA['total']);
        $this->assertEquals(2, $supplierA['failed']);
        $this->assertEquals(20.0, $supplierA['error_rate']);
    }

    /** @test */
    public function it_filters_by_time_range()
    {
        // Create old data (outside 24h window)
        DocumentProcessingLog::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'created_at' => now()->subDays(2),
        ]);

        // Create recent data (within 24h)
        DocumentProcessingLog::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'created_at' => now()->subHours(12),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.error-dashboard.metrics', ['range' => '24h']));

        $data = $response->json();
        $this->assertEquals(3, $data['summary']['total_failed']);
    }

    /** @test */
    public function it_returns_recent_errors_list()
    {
        DocumentProcessingLog::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'status' => 'failed',
            'error_code' => 'ERR_TEST',
            'error_message' => 'Test error message',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('admin.error-dashboard.metrics', ['range' => '24h']));

        $data = $response->json();
        $recentErrors = $data['recent_errors'];

        $this->assertGreaterThan(0, count($recentErrors));
        $this->assertArrayHasKey('document_id', $recentErrors[0]);
        $this->assertArrayHasKey('error_code', $recentErrors[0]);
        $this->assertArrayHasKey('error_message', $recentErrors[0]);
    }
}
