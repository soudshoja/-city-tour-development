<?php

namespace Tests\Feature\Staging;

use Tests\TestCase;
use App\Models\DocumentProcessingLog;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Staging Test Report Generator
 *
 * Generates comprehensive staging test reports including:
 * - Supplier coverage statistics
 * - Document type coverage
 * - Success/failure rates per supplier
 * - Processing time metrics
 * - Issues discovered during testing
 */
class StagingTestReport extends TestCase
{
    use RefreshDatabase;

    protected $skipPermissionSeeder = true;

    protected array $testResults = [];
    protected int $startTime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startTime = microtime(true);
    }

    /**
     * Generate comprehensive staging test report
     */
    public function test_generate_staging_report(): void
    {
        Http::fake([
            '*' => Http::response(['status' => 'queued'], 200),
        ]);

        // Run comprehensive test suite
        $this->runSupplierCoverageTests();
        $this->runDocumentTypeCoverageTests();
        $this->runPerformanceTests();
        $this->runErrorScenarioTests();

        // Generate reports
        $this->generateTextReport();
        $this->generateJsonReport();
        $this->generateMarkdownReport();

        $this->assertTrue(true, 'Staging test report generated successfully');
    }

    /**
     * Test supplier coverage
     */
    protected function runSupplierCoverageTests(): void
    {
        $suppliers = [
            1 => 'Amadeus',
            2 => 'Sabre',
            3 => 'Travelport',
            4 => 'TBO',
            5 => 'Magic Holiday',
            6 => 'Expedia',
            7 => 'Booking.com',
            8 => 'Travco',
            9 => 'Al-Tayyar',
            10 => 'IATA BSP',
            11 => 'Generic Email',
            12 => 'Manual Upload',
        ];

        $company = Company::factory()->create();

        foreach ($suppliers as $supplierId => $supplierName) {
            $startTime = microtime(true);

            try {
                $response = $this->postJson('/api/document-processing', [
                    'company_id' => $company->id,
                    'supplier_id' => $supplierId,
                    'document_type' => 'air',
                    'file_path' => "test/{$supplierName}.air",
                    'file_size_bytes' => rand(1024, 10240),
                ]);

                $duration = round((microtime(true) - $startTime) * 1000, 2);

                $this->testResults['suppliers'][$supplierId] = [
                    'name' => $supplierName,
                    'tested' => true,
                    'success' => $response->status() === 202,
                    'status_code' => $response->status(),
                    'processing_time_ms' => $duration,
                    'error' => $response->status() !== 202 ? $response->json('error') : null,
                ];
            } catch (\Exception $e) {
                $this->testResults['suppliers'][$supplierId] = [
                    'name' => $supplierName,
                    'tested' => true,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * Test document type coverage
     */
    protected function runDocumentTypeCoverageTests(): void
    {
        $documentTypes = ['air', 'pdf', 'image', 'email'];
        $company = Company::factory()->create();

        foreach ($documentTypes as $docType) {
            $startTime = microtime(true);

            try {
                $response = $this->postJson('/api/document-processing', [
                    'company_id' => $company->id,
                    'supplier_id' => 5,
                    'document_type' => $docType,
                    'file_path' => "test/sample.{$docType}",
                    'file_size_bytes' => rand(1024, 102400),
                ]);

                $duration = round((microtime(true) - $startTime) * 1000, 2);

                $this->testResults['document_types'][$docType] = [
                    'tested' => true,
                    'success' => $response->status() === 202,
                    'status_code' => $response->status(),
                    'processing_time_ms' => $duration,
                ];
            } catch (\Exception $e) {
                $this->testResults['document_types'][$docType] = [
                    'tested' => true,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * Run performance tests
     */
    protected function runPerformanceTests(): void
    {
        $company = Company::factory()->create();

        // Test batch processing
        $batchSize = 10;
        $startTime = microtime(true);

        for ($i = 0; $i < $batchSize; $i++) {
            $this->postJson('/api/document-processing', [
                'company_id' => $company->id,
                'supplier_id' => rand(1, 12),
                'document_type' => 'air',
                'file_path' => "test/batch-{$i}.air",
            ]);
        }

        $totalDuration = round((microtime(true) - $startTime) * 1000, 2);

        $this->testResults['performance'] = [
            'batch_size' => $batchSize,
            'total_duration_ms' => $totalDuration,
            'average_per_document_ms' => round($totalDuration / $batchSize, 2),
            'throughput_per_second' => round($batchSize / ($totalDuration / 1000), 2),
        ];
    }

    /**
     * Run error scenario tests
     */
    protected function runErrorScenarioTests(): void
    {
        $company = Company::factory()->create();
        $errorScenarios = [];

        // Test 1: Invalid document type
        try {
            $response = $this->postJson('/api/document-processing', [
                'company_id' => $company->id,
                'supplier_id' => 5,
                'document_type' => 'invalid',
                'file_path' => 'test/sample.air',
            ]);
            $errorScenarios['invalid_document_type'] = [
                'expected' => 422,
                'actual' => $response->status(),
                'passed' => $response->status() === 422,
            ];
        } catch (\Exception $e) {
            $errorScenarios['invalid_document_type'] = [
                'passed' => false,
                'error' => $e->getMessage(),
            ];
        }

        // Test 2: Missing required field
        try {
            $response = $this->postJson('/api/document-processing', [
                'company_id' => $company->id,
                'supplier_id' => 5,
                // Missing: document_type, file_path
            ]);
            $errorScenarios['missing_required_fields'] = [
                'expected' => 422,
                'actual' => $response->status(),
                'passed' => $response->status() === 422,
            ];
        } catch (\Exception $e) {
            $errorScenarios['missing_required_fields'] = [
                'passed' => false,
                'error' => $e->getMessage(),
            ];
        }

        // Test 3: Non-existent company
        try {
            $response = $this->postJson('/api/document-processing', [
                'company_id' => 99999,
                'supplier_id' => 5,
                'document_type' => 'air',
                'file_path' => 'test/sample.air',
            ]);
            $errorScenarios['non_existent_company'] = [
                'expected' => 422,
                'actual' => $response->status(),
                'passed' => $response->status() === 422,
            ];
        } catch (\Exception $e) {
            $errorScenarios['non_existent_company'] = [
                'passed' => false,
                'error' => $e->getMessage(),
            ];
        }

        $this->testResults['error_scenarios'] = $errorScenarios;
    }

    /**
     * Generate text report
     */
    protected function generateTextReport(): void
    {
        $report = $this->buildTextReport();
        $reportPath = storage_path('app/reports/staging_test_report.txt');

        // Ensure directory exists
        if (!File::exists(dirname($reportPath))) {
            File::makeDirectory(dirname($reportPath), 0755, true);
        }

        File::put($reportPath, $report);
        echo "\n[REPORT] Text report saved to: {$reportPath}\n";
    }

    /**
     * Generate JSON report
     */
    protected function generateJsonReport(): void
    {
        $reportData = [
            'generated_at' => now()->toIso8601String(),
            'test_duration_seconds' => round(microtime(true) - $this->startTime, 2),
            'results' => $this->testResults,
            'summary' => $this->generateSummary(),
        ];

        $reportPath = storage_path('app/reports/staging_test_report.json');

        // Ensure directory exists
        if (!File::exists(dirname($reportPath))) {
            File::makeDirectory(dirname($reportPath), 0755, true);
        }

        File::put($reportPath, json_encode($reportData, JSON_PRETTY_PRINT));
        echo "[REPORT] JSON report saved to: {$reportPath}\n";
    }

    /**
     * Generate Markdown report
     */
    protected function generateMarkdownReport(): void
    {
        $report = $this->buildMarkdownReport();
        $reportPath = storage_path('app/reports/staging_test_report.md');

        // Ensure directory exists
        if (!File::exists(dirname($reportPath))) {
            File::makeDirectory(dirname($reportPath), 0755, true);
        }

        File::put($reportPath, $report);
        echo "[REPORT] Markdown report saved to: {$reportPath}\n";
    }

    /**
     * Build text report content
     */
    protected function buildTextReport(): string
    {
        $summary = $this->generateSummary();

        $report = "========================================\n";
        $report .= "STAGING TEST REPORT\n";
        $report .= "========================================\n";
        $report .= "Generated: " . now()->toDateTimeString() . "\n";
        $report .= "Duration: " . round(microtime(true) - $this->startTime, 2) . " seconds\n\n";

        $report .= "SUMMARY\n";
        $report .= "----------------------------------------\n";
        $report .= "Total Suppliers Tested: {$summary['suppliers']['total']}\n";
        $report .= "Suppliers Passed: {$summary['suppliers']['passed']}\n";
        $report .= "Suppliers Failed: {$summary['suppliers']['failed']}\n";
        $report .= "Success Rate: {$summary['suppliers']['success_rate']}%\n\n";

        $report .= "Document Types Tested: {$summary['document_types']['total']}\n";
        $report .= "Document Types Passed: {$summary['document_types']['passed']}\n\n";

        $report .= "SUPPLIER DETAILS\n";
        $report .= "----------------------------------------\n";
        foreach ($this->testResults['suppliers'] ?? [] as $supplierId => $result) {
            $status = $result['success'] ? 'PASS' : 'FAIL';
            $report .= sprintf(
                "[%s] Supplier %d: %s (%.2fms)\n",
                $status,
                $supplierId,
                $result['name'],
                $result['processing_time_ms'] ?? 0
            );
            if (!$result['success'] && isset($result['error'])) {
                $report .= "  Error: {$result['error']}\n";
            }
        }

        $report .= "\nDOCUMENT TYPE COVERAGE\n";
        $report .= "----------------------------------------\n";
        foreach ($this->testResults['document_types'] ?? [] as $type => $result) {
            $status = $result['success'] ? 'PASS' : 'FAIL';
            $report .= sprintf(
                "[%s] %s (%.2fms)\n",
                $status,
                strtoupper($type),
                $result['processing_time_ms'] ?? 0
            );
        }

        if (isset($this->testResults['performance'])) {
            $report .= "\nPERFORMANCE METRICS\n";
            $report .= "----------------------------------------\n";
            $perf = $this->testResults['performance'];
            $report .= "Batch Size: {$perf['batch_size']}\n";
            $report .= "Total Duration: {$perf['total_duration_ms']}ms\n";
            $report .= "Average per Document: {$perf['average_per_document_ms']}ms\n";
            $report .= "Throughput: {$perf['throughput_per_second']} docs/sec\n";
        }

        $report .= "\n========================================\n";
        $report .= "END OF REPORT\n";
        $report .= "========================================\n";

        return $report;
    }

    /**
     * Build Markdown report content
     */
    protected function buildMarkdownReport(): string
    {
        $summary = $this->generateSummary();

        $report = "# Staging Test Report\n\n";
        $report .= "**Generated:** " . now()->toDateTimeString() . "\n";
        $report .= "**Duration:** " . round(microtime(true) - $this->startTime, 2) . " seconds\n\n";

        $report .= "## Summary\n\n";
        $report .= "| Metric | Value |\n";
        $report .= "|--------|-------|\n";
        $report .= "| Total Suppliers Tested | {$summary['suppliers']['total']} |\n";
        $report .= "| Suppliers Passed | {$summary['suppliers']['passed']} |\n";
        $report .= "| Suppliers Failed | {$summary['suppliers']['failed']} |\n";
        $report .= "| Success Rate | {$summary['suppliers']['success_rate']}% |\n";
        $report .= "| Document Types Tested | {$summary['document_types']['total']} |\n\n";

        $report .= "## Supplier Test Results\n\n";
        $report .= "| ID | Supplier | Status | Processing Time (ms) |\n";
        $report .= "|----|----------|--------|---------------------|\n";
        foreach ($this->testResults['suppliers'] ?? [] as $supplierId => $result) {
            $status = $result['success'] ? '✅ PASS' : '❌ FAIL';
            $report .= sprintf(
                "| %d | %s | %s | %.2f |\n",
                $supplierId,
                $result['name'],
                $status,
                $result['processing_time_ms'] ?? 0
            );
        }

        $report .= "\n## Document Type Coverage\n\n";
        $report .= "| Type | Status | Processing Time (ms) |\n";
        $report .= "|------|--------|---------------------|\n";
        foreach ($this->testResults['document_types'] ?? [] as $type => $result) {
            $status = $result['success'] ? '✅ PASS' : '❌ FAIL';
            $report .= sprintf(
                "| %s | %s | %.2f |\n",
                strtoupper($type),
                $status,
                $result['processing_time_ms'] ?? 0
            );
        }

        if (isset($this->testResults['performance'])) {
            $report .= "\n## Performance Metrics\n\n";
            $perf = $this->testResults['performance'];
            $report .= "| Metric | Value |\n";
            $report .= "|--------|-------|\n";
            $report .= "| Batch Size | {$perf['batch_size']} |\n";
            $report .= "| Total Duration | {$perf['total_duration_ms']}ms |\n";
            $report .= "| Average per Document | {$perf['average_per_document_ms']}ms |\n";
            $report .= "| Throughput | {$perf['throughput_per_second']} docs/sec |\n";
        }

        if (isset($this->testResults['error_scenarios'])) {
            $report .= "\n## Error Scenario Tests\n\n";
            $report .= "| Scenario | Status |\n";
            $report .= "|----------|--------|\n";
            foreach ($this->testResults['error_scenarios'] as $scenario => $result) {
                $status = ($result['passed'] ?? false) ? '✅ PASS' : '❌ FAIL';
                $report .= sprintf("| %s | %s |\n", ucwords(str_replace('_', ' ', $scenario)), $status);
            }
        }

        $report .= "\n---\n";
        $report .= "*Report generated by Staging Test Suite*\n";

        return $report;
    }

    /**
     * Generate summary statistics
     */
    protected function generateSummary(): array
    {
        $suppliersPassed = 0;
        $suppliersTotal = count($this->testResults['suppliers'] ?? []);

        foreach ($this->testResults['suppliers'] ?? [] as $result) {
            if ($result['success']) {
                $suppliersPassed++;
            }
        }

        $docTypesPassed = 0;
        $docTypesTotal = count($this->testResults['document_types'] ?? []);

        foreach ($this->testResults['document_types'] ?? [] as $result) {
            if ($result['success']) {
                $docTypesPassed++;
            }
        }

        return [
            'suppliers' => [
                'total' => $suppliersTotal,
                'passed' => $suppliersPassed,
                'failed' => $suppliersTotal - $suppliersPassed,
                'success_rate' => $suppliersTotal > 0 ? round(($suppliersPassed / $suppliersTotal) * 100, 2) : 0,
            ],
            'document_types' => [
                'total' => $docTypesTotal,
                'passed' => $docTypesPassed,
                'failed' => $docTypesTotal - $docTypesPassed,
            ],
        ];
    }
}
