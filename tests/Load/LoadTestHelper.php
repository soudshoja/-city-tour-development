<?php

namespace Tests\Load;

use App\Models\Company;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LoadTestHelper
{
    private array $results = [];
    private int $successCount = 0;
    private int $failureCount = 0;
    private array $errors = [];
    private array $latencies = [];

    /**
     * Generate test document payloads
     *
     * @param int $count Number of documents to generate
     * @param array $types Document types to include
     * @return array
     */
    public function generateDocuments(int $count, array $types = ['pdf', 'image', 'email', 'air']): array
    {
        $documents = [];
        $company = Company::first();

        if (!$company) {
            throw new \Exception('No company found. Please seed database first.');
        }

        for ($i = 0; $i < $count; $i++) {
            $type = $types[array_rand($types)];
            $documents[] = [
                'company_id' => $company->id,
                'supplier_id' => rand(1, 10),
                'document_type' => $type,
                'file_path' => sprintf('load_test/supplier_%d/%s_%s.%s',
                    rand(1, 10),
                    $type,
                    Str::random(8),
                    $type === 'air' ? 'air' : ($type === 'email' ? 'eml' : $type)
                ),
                'file_size_bytes' => rand(1000, 5000000),
                'file_hash' => hash('sha256', Str::random(32)),
            ];
        }

        return $documents;
    }

    /**
     * Submit a batch of documents via HTTP
     *
     * @param array $documents Array of document payloads
     * @param string $baseUrl Base URL for API
     * @param bool $parallel Whether to submit in parallel (true) or sequentially (false)
     * @return array Results array
     */
    public function submitBatch(array $documents, string $baseUrl = 'http://localhost', bool $parallel = false): array
    {
        $this->results = [];
        $this->successCount = 0;
        $this->failureCount = 0;
        $this->errors = [];
        $this->latencies = [];

        if ($parallel) {
            return $this->submitParallel($documents, $baseUrl);
        } else {
            return $this->submitSequential($documents, $baseUrl);
        }
    }

    /**
     * Submit documents in parallel using HTTP pool
     *
     * @param array $documents
     * @param string $baseUrl
     * @return array
     */
    private function submitParallel(array $documents, string $baseUrl): array
    {
        $requests = [];
        foreach ($documents as $index => $doc) {
            $requests["doc_$index"] = fn() => Http::timeout(10)
                ->post("$baseUrl/api/documents/process", $doc);
        }

        $responses = Http::pool($requests);

        foreach ($responses as $key => $response) {
            $index = (int) str_replace('doc_', '', $key);
            $this->recordResponse($response, $index, $documents[$index]);
        }

        return $this->results;
    }

    /**
     * Submit documents sequentially
     *
     * @param array $documents
     * @param string $baseUrl
     * @return array
     */
    private function submitSequential(array $documents, string $baseUrl): array
    {
        foreach ($documents as $index => $doc) {
            $startTime = microtime(true);

            $response = Http::timeout(10)
                ->post("$baseUrl/api/documents/process", $doc);

            $latency = (microtime(true) - $startTime) * 1000; // Convert to ms
            $this->latencies[] = $latency;

            $this->recordResponse($response, $index, $doc, $latency);
        }

        return $this->results;
    }

    /**
     * Record response from HTTP request
     *
     * @param \Illuminate\Http\Client\Response $response
     * @param int $index
     * @param array $document
     * @param float|null $latency
     * @return void
     */
    private function recordResponse($response, int $index, array $document, ?float $latency = null): void
    {
        $isSuccess = $response->successful() || $response->status() === 202;

        if ($isSuccess) {
            $this->successCount++;
        } else {
            $this->failureCount++;
            $this->errors[] = [
                'index' => $index,
                'status' => $response->status(),
                'error' => $response->json()['error'] ?? 'Unknown error',
                'document_type' => $document['document_type'],
            ];
        }

        $this->results[] = [
            'index' => $index,
            'document_type' => $document['document_type'],
            'status_code' => $response->status(),
            'success' => $isSuccess,
            'latency_ms' => $latency,
            'response' => $response->json(),
        ];
    }

    /**
     * Measure throughput (documents per second)
     *
     * @param float $startTime Start timestamp
     * @param float $endTime End timestamp
     * @param int $count Number of documents
     * @return array Throughput metrics
     */
    public function measureThroughput(float $startTime, float $endTime, int $count): array
    {
        $durationSeconds = $endTime - $startTime;
        $docsPerSecond = $count / $durationSeconds;
        $docsPerMinute = $docsPerSecond * 60;

        return [
            'duration_seconds' => round($durationSeconds, 2),
            'docs_per_second' => round($docsPerSecond, 2),
            'docs_per_minute' => round($docsPerMinute, 2),
        ];
    }

    /**
     * Calculate percentile latency
     *
     * @param array $latencies Array of latency values in ms
     * @param int $percentile Percentile to calculate (e.g., 95, 99)
     * @return float|null
     */
    private function calculatePercentile(array $latencies, int $percentile): ?float
    {
        if (empty($latencies)) {
            return null;
        }

        sort($latencies);
        $index = ceil(count($latencies) * ($percentile / 100)) - 1;
        return $latencies[$index] ?? null;
    }

    /**
     * Generate comprehensive report
     *
     * @param array $results Test results
     * @param array $throughput Throughput metrics
     * @return array
     */
    public function generateReport(array $results, array $throughput): array
    {
        $latencies = array_filter(array_column($results, 'latency_ms'));

        $report = [
            'summary' => [
                'total_documents' => count($results),
                'success_count' => $this->successCount,
                'failure_count' => $this->failureCount,
                'success_rate_percent' => count($results) > 0
                    ? round(($this->successCount / count($results)) * 100, 2)
                    : 0,
            ],
            'throughput' => $throughput,
            'latency' => [
                'min_ms' => !empty($latencies) ? round(min($latencies), 2) : null,
                'max_ms' => !empty($latencies) ? round(max($latencies), 2) : null,
                'avg_ms' => !empty($latencies) ? round(array_sum($latencies) / count($latencies), 2) : null,
                'p50_ms' => $this->calculatePercentile($latencies, 50),
                'p95_ms' => $this->calculatePercentile($latencies, 95),
                'p99_ms' => $this->calculatePercentile($latencies, 99),
            ],
            'errors' => [
                'count' => count($this->errors),
                'breakdown' => $this->getErrorBreakdown(),
            ],
            'document_types' => $this->getDocumentTypeBreakdown($results),
        ];

        return $report;
    }

    /**
     * Get error breakdown by type
     *
     * @return array
     */
    private function getErrorBreakdown(): array
    {
        $breakdown = [];

        foreach ($this->errors as $error) {
            $key = $error['status'] . ': ' . $error['error'];
            if (!isset($breakdown[$key])) {
                $breakdown[$key] = 0;
            }
            $breakdown[$key]++;
        }

        return $breakdown;
    }

    /**
     * Get document type breakdown
     *
     * @param array $results
     * @return array
     */
    private function getDocumentTypeBreakdown(array $results): array
    {
        $breakdown = [];

        foreach ($results as $result) {
            $type = $result['document_type'];
            if (!isset($breakdown[$type])) {
                $breakdown[$type] = [
                    'total' => 0,
                    'success' => 0,
                    'failure' => 0,
                ];
            }

            $breakdown[$type]['total']++;
            if ($result['success']) {
                $breakdown[$type]['success']++;
            } else {
                $breakdown[$type]['failure']++;
            }
        }

        return $breakdown;
    }

    /**
     * Save report to file
     *
     * @param array $report
     * @param string $filename
     * @return string Path to saved file
     */
    public function saveReport(array $report, string $filename): string
    {
        $dir = storage_path('app/load-test-reports');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filepath = "$dir/{$filename}_{$timestamp}.json";

        file_put_contents($filepath, json_encode($report, JSON_PRETTY_PRINT));

        Log::info('Load test report saved', ['path' => $filepath]);

        return $filepath;
    }

    /**
     * Print report to console
     *
     * @param array $report
     * @return void
     */
    public function printReport(array $report): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "LOAD TEST REPORT\n";
        echo str_repeat('=', 80) . "\n\n";

        echo "SUMMARY:\n";
        echo "  Total Documents:  {$report['summary']['total_documents']}\n";
        echo "  Success Count:    {$report['summary']['success_count']}\n";
        echo "  Failure Count:    {$report['summary']['failure_count']}\n";
        echo "  Success Rate:     {$report['summary']['success_rate_percent']}%\n\n";

        echo "THROUGHPUT:\n";
        echo "  Duration:         {$report['throughput']['duration_seconds']}s\n";
        echo "  Docs/Second:      {$report['throughput']['docs_per_second']}\n";
        echo "  Docs/Minute:      {$report['throughput']['docs_per_minute']}\n\n";

        echo "LATENCY:\n";
        echo "  Min:              {$report['latency']['min_ms']}ms\n";
        echo "  Max:              {$report['latency']['max_ms']}ms\n";
        echo "  Average:          {$report['latency']['avg_ms']}ms\n";
        echo "  P50:              {$report['latency']['p50_ms']}ms\n";
        echo "  P95:              {$report['latency']['p95_ms']}ms\n";
        echo "  P99:              {$report['latency']['p99_ms']}ms\n\n";

        if (!empty($report['errors']['breakdown'])) {
            echo "ERRORS:\n";
            foreach ($report['errors']['breakdown'] as $error => $count) {
                echo "  $error: $count\n";
            }
            echo "\n";
        }

        echo "DOCUMENT TYPES:\n";
        foreach ($report['document_types'] as $type => $stats) {
            echo "  $type: {$stats['success']}/{$stats['total']} successful\n";
        }

        echo "\n" . str_repeat('=', 80) . "\n";
    }
}
