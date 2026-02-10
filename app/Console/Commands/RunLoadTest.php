<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunLoadTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:load
                            {--type=sustained : Type of load test (sustained|burst|stress|mixed|error|daily|all)}
                            {--count=100 : Number of documents to process}
                            {--batch=10 : Batch size for sustained tests}
                            {--parallel : Submit documents in parallel (burst mode)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run load tests for document processing system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $count = (int) $this->option('count');
        $batch = (int) $this->option('batch');

        $this->info('🚀 Starting Load Test');
        $this->info("Type: $type");
        $this->info("Count: $count documents");
        $this->newLine();

        try {
            $exitCode = match ($type) {
                'sustained' => $this->runSustainedTest(),
                'burst' => $this->runBurstTest(),
                'stress' => $this->runStressTest(),
                'mixed' => $this->runMixedTest(),
                'error' => $this->runErrorTest(),
                'daily' => $this->runDailyTest(),
                'all' => $this->runAllTests(),
                default => $this->invalidType($type),
            };

            if ($exitCode === 0) {
                $this->newLine();
                $this->info('✅ Load test completed successfully');
                $this->info('📁 Reports saved to: storage/app/load-test-reports/');
            }

            return $exitCode;

        } catch (\Exception $e) {
            $this->error('❌ Load test failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Run sustained load test
     */
    private function runSustainedTest(): int
    {
        $this->info('📊 Running Sustained Load Test (100 documents in batches)...');
        return Artisan::call('test', [
            '--filter' => 'test_sustained_load_100_documents',
            'Tests\\Load\\DocumentProcessingLoadTest',
        ]);
    }

    /**
     * Run burst load test
     */
    private function runBurstTest(): int
    {
        $this->info('💥 Running Burst Load Test (50 documents in parallel)...');
        return Artisan::call('test', [
            '--filter' => 'test_burst_load_50_documents_parallel',
            'Tests\\Load\\DocumentProcessingLoadTest',
        ]);
    }

    /**
     * Run stress test
     */
    private function runStressTest(): int
    {
        $this->info('⚡ Running Stress Test (500 documents rapidly)...');
        return Artisan::call('test', [
            '--filter' => 'test_stress_test_500_documents',
            'Tests\\Load\\DocumentProcessingLoadTest',
        ]);
    }

    /**
     * Run mixed document types test
     */
    private function runMixedTest(): int
    {
        $this->info('🔀 Running Mixed Document Types Test...');
        return Artisan::call('test', [
            '--filter' => 'test_mixed_document_types_load',
            'Tests\\Load\\DocumentProcessingLoadTest',
        ]);
    }

    /**
     * Run error handling test
     */
    private function runErrorTest(): int
    {
        $this->info('⚠️  Running Error Handling Test (10% failure rate)...');
        return Artisan::call('test', [
            '--filter' => 'test_error_handling_under_load',
            'Tests\\Load\\DocumentProcessingLoadTest',
        ]);
    }

    /**
     * Run daily throughput test
     */
    private function runDailyTest(): int
    {
        $this->info('📅 Running Daily Throughput Test (100+ docs/day validation)...');
        return Artisan::call('test', [
            '--filter' => 'test_daily_throughput_capability',
            'Tests\\Load\\DocumentProcessingLoadTest',
        ]);
    }

    /**
     * Run all load tests
     */
    private function runAllTests(): int
    {
        $this->info('🎯 Running ALL Load Tests...');
        $this->newLine();

        $tests = [
            'Sustained Load' => 'test_sustained_load_100_documents',
            'Burst Load' => 'test_burst_load_50_documents_parallel',
            'Stress Test' => 'test_stress_test_500_documents',
            'Mixed Types' => 'test_mixed_document_types_load',
            'Error Handling' => 'test_error_handling_under_load',
            'Daily Throughput' => 'test_daily_throughput_capability',
        ];

        $results = [];

        foreach ($tests as $name => $filter) {
            $this->info("Running: $name...");

            $exitCode = Artisan::call('test', [
                '--filter' => $filter,
                'Tests\\Load\\DocumentProcessingLoadTest',
            ]);

            $results[$name] = $exitCode === 0 ? '✅ PASS' : '❌ FAIL';

            $this->line($results[$name]);
            $this->newLine();
        }

        // Summary
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('LOAD TEST SUMMARY');
        $this->info('═══════════════════════════════════════════════════════');

        foreach ($results as $test => $result) {
            $this->line(sprintf('  %-30s %s', $test, $result));
        }

        $this->newLine();

        $failedCount = count(array_filter($results, fn($r) => str_contains($r, 'FAIL')));

        return $failedCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Handle invalid test type
     */
    private function invalidType(string $type): int
    {
        $this->error("Invalid test type: $type");
        $this->newLine();
        $this->info('Valid types:');
        $this->line('  sustained  - 100 documents in batches (simulated daily load)');
        $this->line('  burst      - 50 documents in parallel (spike handling)');
        $this->line('  stress     - 500 documents rapidly (breaking point)');
        $this->line('  mixed      - Mixed document types (pdf, image, email, air)');
        $this->line('  error      - Error handling with 10% failure rate');
        $this->line('  daily      - Daily throughput capability validation');
        $this->line('  all        - Run all tests in sequence');
        $this->newLine();
        $this->info('Examples:');
        $this->line('  php artisan test:load --type=sustained');
        $this->line('  php artisan test:load --type=burst');
        $this->line('  php artisan test:load --type=all');

        return Command::FAILURE;
    }
}
