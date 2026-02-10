# Load Testing Harness - Document Processing System

## Overview

This load testing harness validates the document processing system's capability to handle 100+ documents per day with comprehensive performance metrics, error handling, and throughput analysis.

## Test Scenarios

### 1. Sustained Load Test
- **Purpose**: Simulate normal daily operations
- **Configuration**: 100 documents in batches of 10
- **Validates**: Consistent throughput over time
- **Command**: `php artisan test:load --type=sustained`

### 2. Burst Load Test
- **Purpose**: Test system resilience under sudden load spikes
- **Configuration**: 50 documents submitted simultaneously
- **Validates**: Parallel request handling
- **Command**: `php artisan test:load --type=burst`

### 3. Stress Test
- **Purpose**: Find system breaking point
- **Configuration**: 500 documents submitted rapidly
- **Validates**: Maximum capacity and failure modes
- **Command**: `php artisan test:load --type=stress`

### 4. Mixed Document Types
- **Purpose**: Validate handling of diverse document types
- **Configuration**: 100 documents (PDF, image, email, AIR)
- **Validates**: Type-specific processing consistency
- **Command**: `php artisan test:load --type=mixed`

### 5. Error Handling Under Load
- **Purpose**: Test error resilience during high load
- **Configuration**: 100 documents with 10% simulated failures
- **Validates**: Error logging and graceful degradation
- **Command**: `php artisan test:load --type=error`

### 6. Daily Throughput Validation
- **Purpose**: Verify 100+ docs/day capability
- **Configuration**: Throughput measurement and projection
- **Validates**: Production readiness
- **Command**: `php artisan test:load --type=daily`

## Usage

### Quick Start

```bash
# Run a specific test
php artisan test:load --type=sustained

# Run all tests
php artisan test:load --type=all

# Or use the bash script
./tests/Load/run-load-test.sh sustained
./tests/Load/run-load-test.sh all
```

### Using PHPUnit Directly

```bash
# Run specific test
php artisan test --filter=test_sustained_load_100_documents Tests/Load/DocumentProcessingLoadTest

# Run all load tests
php artisan test Tests/Load/DocumentProcessingLoadTest
```

## Output Metrics

Each test generates comprehensive metrics:

### Summary Metrics
- **Total Documents**: Number of documents processed
- **Success Count**: Successfully queued documents
- **Failure Count**: Failed submissions
- **Success Rate**: Percentage of successful submissions

### Throughput Metrics
- **Duration**: Total test execution time
- **Docs/Second**: Documents processed per second
- **Docs/Minute**: Documents processed per minute
- **Projected Daily**: Extrapolated daily capacity

### Latency Metrics
- **Min/Max/Avg**: Response time statistics
- **P50**: Median latency
- **P95**: 95th percentile latency
- **P99**: 99th percentile latency

### Error Analysis
- **Error Count**: Total errors encountered
- **Error Breakdown**: Errors grouped by type and status code
- **Document Type Breakdown**: Success/failure by document type

## Report Files

Reports are automatically saved to `storage/app/load-test-reports/` with timestamps.

Example report structure:
```json
{
  "summary": {
    "total_documents": 100,
    "success_count": 98,
    "failure_count": 2,
    "success_rate_percent": 98.0
  },
  "throughput": {
    "duration_seconds": 12.45,
    "docs_per_second": 8.03,
    "docs_per_minute": 482.0
  },
  "latency": {
    "min_ms": 45.2,
    "max_ms": 892.1,
    "avg_ms": 124.5,
    "p50_ms": 118.3,
    "p95_ms": 287.6,
    "p99_ms": 645.8
  },
  "errors": {
    "count": 2,
    "breakdown": {
      "503: Service unavailable": 2
    }
  },
  "document_types": {
    "pdf": {"total": 25, "success": 25, "failure": 0},
    "image": {"total": 24, "success": 24, "failure": 0},
    "email": {"total": 26, "success": 25, "failure": 1},
    "air": {"total": 25, "success": 24, "failure": 1}
  }
}
```

## Performance Targets

### Production Requirements
- **Daily Throughput**: 100+ documents/day
- **Success Rate**: ≥90% under normal load
- **P95 Latency**: <5 seconds
- **P99 Latency**: <10 seconds

### Test Assertions

Each test validates specific performance criteria:

1. **Sustained Load**: 90% success rate, P95 <5s
2. **Burst Load**: 85% success rate, P99 <10s
3. **Stress Test**: 75% success rate (under extreme load)
4. **Mixed Types**: 85% success rate per type
5. **Error Handling**: Proper error logging and recovery
6. **Daily Throughput**: Sustained 100+ docs/day capability

## Architecture

### LoadTestHelper.php
Core helper class providing:
- `generateDocuments()` - Create test document payloads
- `submitBatch()` - Submit documents sequentially or in parallel
- `measureThroughput()` - Calculate performance metrics
- `generateReport()` - Create comprehensive test reports
- `saveReport()` - Persist reports to storage
- `printReport()` - Console output formatting

### DocumentProcessingLoadTest.php
PHPUnit test suite with 6 comprehensive test scenarios.

### RunLoadTest.php
Artisan command for easy CLI execution:
```php
php artisan test:load --type=sustained
```

### run-load-test.sh
Bash script wrapper for automated test execution:
```bash
./tests/Load/run-load-test.sh sustained
```

## Development

### Adding New Tests

1. Add test method to `DocumentProcessingLoadTest.php`:
```php
public function test_custom_scenario(): void
{
    $documents = $this->helper->generateDocuments(50);
    $results = $this->helper->submitBatch($documents, config('app.url'));

    $throughput = $this->helper->measureThroughput($startTime, $endTime, 50);
    $report = $this->helper->generateReport($results, $throughput);

    // Assertions
    $this->assertGreaterThanOrEqual(90, $report['summary']['success_rate_percent']);
}
```

2. Add command option in `RunLoadTest.php`:
```php
'custom' => $this->runCustomTest(),
```

3. Update bash script with new option.

### Customizing Metrics

Modify `LoadTestHelper.php` to add custom metrics:

```php
private function calculateCustomMetric($results): array
{
    // Your custom calculation
    return ['custom_metric' => $value];
}
```

Add to `generateReport()` method.

## Troubleshooting

### Database Connection Issues
```bash
# Ensure test database is configured
php artisan migrate:fresh --seed --env=testing
```

### N8n Mock Not Working
Check that `Http::fake()` is properly configured in test setup:
```php
Http::fake([
    config('services.n8n.webhook_url') => Http::response(['status' => 'accepted'], 202),
]);
```

### Memory Issues
For large stress tests (500+ documents), increase PHP memory limit:
```bash
php -d memory_limit=512M artisan test:load --type=stress
```

### Timeout Issues
Adjust timeout in LoadTestHelper:
```php
Http::timeout(30) // Increase from 10 to 30 seconds
```

## CI/CD Integration

### GitHub Actions Example
```yaml
- name: Run Load Tests
  run: |
    php artisan migrate:fresh --seed --env=testing
    php artisan test:load --type=all
```

### Performance Regression Detection
```bash
# Run baseline test
php artisan test:load --type=sustained > baseline.txt

# Compare with current performance
php artisan test:load --type=sustained > current.txt
diff baseline.txt current.txt
```

## Best Practices

1. **Run tests in isolated environment**: Don't run against production
2. **Monitor system resources**: Track CPU, memory, DB connections
3. **Baseline first**: Establish performance baseline before changes
4. **Regular testing**: Run load tests before each release
5. **Analyze trends**: Track metrics over time to detect degradation
6. **Scale appropriately**: Adjust test counts based on hardware

## Support

For issues or questions:
1. Check test output for detailed error messages
2. Review report files in `storage/app/load-test-reports/`
3. Enable Laravel logging for debugging
4. Check database logs for query performance

## License

Part of Soud Laravel document processing system.
