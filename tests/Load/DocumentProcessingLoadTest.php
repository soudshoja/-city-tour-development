<?php

namespace Tests\Load;

use Tests\TestCase;
use App\Models\Company;
use App\Models\DocumentProcessingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;

class DocumentProcessingLoadTest extends TestCase
{
    use RefreshDatabase;

    private LoadTestHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helper = new LoadTestHelper();

        // Create test company
        Company::factory()->create();

        // Mock N8n responses
        Http::fake([
            config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 202),
        ]);
    }

    /**
     * Test 1: Sustained Load - 100 documents over simulated day
     * Simulates normal daily load with batches processed over time
     */
    public function test_sustained_load_100_documents(): void
    {
        $totalDocuments = 100;
        $batchSize = 10;
        $batchCount = $totalDocuments / $batchSize;

        echo "\n[SUSTAINED LOAD TEST] Processing $totalDocuments documents in $batchCount batches of $batchSize\n";

        $startTime = microtime(true);
        $allResults = [];

        for ($i = 0; $i < $batchCount; $i++) {
            echo "  Batch " . ($i + 1) . "/$batchCount... ";

            $documents = $this->helper->generateDocuments($batchSize);
            $results = $this->helper->submitBatch($documents, config('app.url'), false);

            $allResults = array_merge($allResults, $results);

            echo "Done (" . count(array_filter($results, fn($r) => $r['success'])) . " succeeded)\n";

            // Small delay between batches (simulate time between document arrivals)
            if ($i < $batchCount - 1) {
                usleep(100000); // 100ms delay
            }
        }

        $endTime = microtime(true);

        // Generate report
        $throughput = $this->helper->measureThroughput($startTime, $endTime, $totalDocuments);
        $report = $this->helper->generateReport($allResults, $throughput);

        $this->helper->printReport($report);
        $filepath = $this->helper->saveReport($report, 'sustained_load_test');

        echo "Report saved to: $filepath\n";

        // Assertions
        $this->assertGreaterThan(0, $report['summary']['total_documents']);
        $this->assertGreaterThanOrEqual(90, $report['summary']['success_rate_percent'],
            'Success rate should be at least 90%');
        $this->assertLessThan(5000, $report['latency']['p95_ms'],
            'P95 latency should be under 5 seconds');

        // Verify database records
        $this->assertEquals($totalDocuments, DocumentProcessingLog::count());
    }

    /**
     * Test 2: Burst Load - 50 documents submitted simultaneously
     * Tests system resilience under sudden load spike
     */
    public function test_burst_load_50_documents_parallel(): void
    {
        $totalDocuments = 50;

        echo "\n[BURST LOAD TEST] Submitting $totalDocuments documents in parallel\n";

        $startTime = microtime(true);

        $documents = $this->helper->generateDocuments($totalDocuments);
        $results = $this->helper->submitBatch($documents, config('app.url'), true);

        $endTime = microtime(true);

        // Generate report
        $throughput = $this->helper->measureThroughput($startTime, $endTime, $totalDocuments);
        $report = $this->helper->generateReport($results, $throughput);

        $this->helper->printReport($report);
        $filepath = $this->helper->saveReport($report, 'burst_load_test');

        echo "Report saved to: $filepath\n";

        // Assertions
        $this->assertEquals($totalDocuments, $report['summary']['total_documents']);
        $this->assertGreaterThanOrEqual(85, $report['summary']['success_rate_percent'],
            'Success rate should be at least 85% under burst load');
        $this->assertLessThan(10000, $report['latency']['p99_ms'],
            'P99 latency should be under 10 seconds even with parallel requests');

        // Verify database records
        $this->assertEquals($totalDocuments, DocumentProcessingLog::count());
    }

    /**
     * Test 3: Stress Test - 500 documents rapidly to find breaking point
     * Pushes system to limits to identify failure thresholds
     */
    public function test_stress_test_500_documents(): void
    {
        $totalDocuments = 500;
        $batchSize = 50;
        $batchCount = $totalDocuments / $batchSize;

        echo "\n[STRESS TEST] Processing $totalDocuments documents rapidly in batches of $batchSize\n";

        $startTime = microtime(true);
        $allResults = [];

        for ($i = 0; $i < $batchCount; $i++) {
            echo "  Batch " . ($i + 1) . "/$batchCount... ";

            $documents = $this->helper->generateDocuments($batchSize);
            $results = $this->helper->submitBatch($documents, config('app.url'), true);

            $allResults = array_merge($allResults, $results);

            echo "Done (" . count(array_filter($results, fn($r) => $r['success'])) . " succeeded)\n";

            // Minimal delay to stress the system
            usleep(50000); // 50ms delay
        }

        $endTime = microtime(true);

        // Generate report
        $throughput = $this->helper->measureThroughput($startTime, $endTime, $totalDocuments);
        $report = $this->helper->generateReport($allResults, $throughput);

        $this->helper->printReport($report);
        $filepath = $this->helper->saveReport($report, 'stress_test');

        echo "Report saved to: $filepath\n";

        // Assertions (more lenient for stress test)
        $this->assertEquals($totalDocuments, $report['summary']['total_documents']);
        $this->assertGreaterThanOrEqual(75, $report['summary']['success_rate_percent'],
            'Success rate should be at least 75% under stress');

        // Log breaking point info
        if ($report['summary']['success_rate_percent'] < 90) {
            echo "\n⚠️  WARNING: Success rate dropped to {$report['summary']['success_rate_percent']}% under stress\n";
            echo "    This indicates the system breaking point is around {$report['throughput']['docs_per_minute']} docs/minute\n\n";
        }

        // Verify database records
        $this->assertGreaterThan(0, DocumentProcessingLog::count());
    }

    /**
     * Test 4: Mixed Document Types - Load test with all document types
     * Validates handling of diverse document types under load
     */
    public function test_mixed_document_types_load(): void
    {
        $totalDocuments = 100;
        $types = ['pdf', 'image', 'email', 'air'];

        echo "\n[MIXED TYPES TEST] Processing $totalDocuments documents with mixed types\n";

        $startTime = microtime(true);

        $documents = $this->helper->generateDocuments($totalDocuments, $types);
        $results = $this->helper->submitBatch($documents, config('app.url'), false);

        $endTime = microtime(true);

        // Generate report
        $throughput = $this->helper->measureThroughput($startTime, $endTime, $totalDocuments);
        $report = $this->helper->generateReport($results, $throughput);

        $this->helper->printReport($report);
        $filepath = $this->helper->saveReport($report, 'mixed_types_test');

        echo "Report saved to: $filepath\n";

        // Assertions
        $this->assertEquals($totalDocuments, $report['summary']['total_documents']);
        $this->assertGreaterThanOrEqual(90, $report['summary']['success_rate_percent']);

        // Verify all document types were tested
        foreach ($types as $type) {
            $this->assertArrayHasKey($type, $report['document_types'],
                "Document type '$type' should be present in results");
            $this->assertGreaterThan(0, $report['document_types'][$type]['total'],
                "Should have processed at least one '$type' document");
        }

        // Verify consistency across document types
        foreach ($report['document_types'] as $type => $stats) {
            $successRate = ($stats['success'] / $stats['total']) * 100;
            $this->assertGreaterThanOrEqual(85, $successRate,
                "Success rate for '$type' should be at least 85%");
        }
    }

    /**
     * Test 5: Error Rate Under Load - Inject failures and verify error handling
     * Tests system resilience when errors occur during high load
     */
    public function test_error_handling_under_load(): void
    {
        $totalDocuments = 100;
        $errorRate = 0.10; // 10% failure rate

        echo "\n[ERROR HANDLING TEST] Processing $totalDocuments documents with ~10% simulated failures\n";

        // Mock N8n to fail for ~10% of requests
        Http::fake(function ($request) use ($errorRate) {
            $shouldFail = (mt_rand() / mt_getrandmax()) < $errorRate;

            if ($shouldFail) {
                return Http::response(['error' => 'Service temporarily unavailable'], 500);
            }

            return Http::response(['status' => 'accepted'], 202);
        });

        $startTime = microtime(true);

        $documents = $this->helper->generateDocuments($totalDocuments);
        $results = $this->helper->submitBatch($documents, config('app.url'), false);

        $endTime = microtime(true);

        // Generate report
        $throughput = $this->helper->measureThroughput($startTime, $endTime, $totalDocuments);
        $report = $this->helper->generateReport($results, $throughput);

        $this->helper->printReport($report);
        $filepath = $this->helper->saveReport($report, 'error_handling_test');

        echo "Report saved to: $filepath\n";

        // Assertions
        $this->assertEquals($totalDocuments, $report['summary']['total_documents']);

        // Should have some failures
        $this->assertGreaterThan(0, $report['summary']['failure_count'],
            'Should have recorded some failures from simulated errors');

        // Successful requests should still work fine
        $this->assertGreaterThan(0, $report['summary']['success_count'],
            'Should have some successful requests despite errors');

        // Verify failed documents are logged correctly
        $failedLogs = DocumentProcessingLog::where('status', 'failed')->count();
        $this->assertEquals($report['summary']['failure_count'], $failedLogs,
            'All failed requests should be logged in database');

        // Verify error codes are set
        $logsWithErrorCodes = DocumentProcessingLog::where('status', 'failed')
            ->whereNotNull('error_code')
            ->count();
        $this->assertEquals($failedLogs, $logsWithErrorCodes,
            'All failed logs should have error codes');
    }

    /**
     * Test 6: Throughput validation - Verify 100+ docs/day capability
     * Validates the system can handle required daily throughput
     */
    public function test_daily_throughput_capability(): void
    {
        $documentsPerDay = 100;
        $testDuration = 60; // 60 seconds to simulate
        $requiredDocsPerMinute = $documentsPerDay / (24 * 60); // Distributed over 24 hours
        $documentsToTest = (int) ceil($requiredDocsPerMinute); // At least this many per minute

        echo "\n[DAILY THROUGHPUT TEST] Validating {$documentsPerDay} docs/day capability\n";
        echo "  Required throughput: " . round($requiredDocsPerMinute, 2) . " docs/minute\n";
        echo "  Testing with: $documentsToTest documents in 1 minute\n";

        $startTime = microtime(true);

        $documents = $this->helper->generateDocuments($documentsToTest);
        $results = $this->helper->submitBatch($documents, config('app.url'), false);

        $endTime = microtime(true);

        // Generate report
        $throughput = $this->helper->measureThroughput($startTime, $endTime, $documentsToTest);
        $report = $this->helper->generateReport($results, $throughput);

        $this->helper->printReport($report);

        // Calculate projected daily throughput
        $projectedDailyThroughput = $throughput['docs_per_minute'] * 60 * 24;

        echo "\n📊 CAPACITY ANALYSIS:\n";
        echo "  Tested throughput:     {$throughput['docs_per_minute']} docs/minute\n";
        echo "  Projected daily:       " . round($projectedDailyThroughput) . " docs/day\n";
        echo "  Required daily:        $documentsPerDay docs/day\n";
        echo "  Capacity margin:       " . round((($projectedDailyThroughput / $documentsPerDay) - 1) * 100) . "%\n\n";

        $filepath = $this->helper->saveReport($report, 'daily_throughput_test');
        echo "Report saved to: $filepath\n";

        // Assertions
        $this->assertGreaterThanOrEqual($documentsPerDay, $projectedDailyThroughput,
            "System should handle at least $documentsPerDay documents per day");
        $this->assertGreaterThanOrEqual(90, $report['summary']['success_rate_percent'],
            'Success rate should be at least 90% for daily operations');
    }
}
