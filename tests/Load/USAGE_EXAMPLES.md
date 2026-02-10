# Load Testing - Usage Examples

## Quick Start Commands

### 1. Run Sustained Load Test (Recommended for first test)
```bash
php artisan test:load --type=sustained
```
**What it does**: Processes 100 documents in batches of 10 to simulate normal daily operations.

### 2. Run All Tests
```bash
php artisan test:load --type=all
```
**What it does**: Executes all 6 load test scenarios in sequence.

### 3. Using the Bash Script
```bash
./tests/Load/run-load-test.sh sustained
./tests/Load/run-load-test.sh all
```

## Individual Test Scenarios

### Sustained Load (Daily Operations Simulation)
```bash
# Artisan command
php artisan test:load --type=sustained

# Bash script
./tests/Load/run-load-test.sh sustained

# Direct PHPUnit
php artisan test --filter=test_sustained_load_100_documents Tests/Load/DocumentProcessingLoadTest
```
**Expected output**: 90%+ success rate, P95 latency <5s

### Burst Load (Spike Testing)
```bash
php artisan test:load --type=burst
```
**What it tests**: 50 documents submitted simultaneously
**Expected output**: 85%+ success rate, handles parallel requests

### Stress Test (Find Breaking Point)
```bash
php artisan test:load --type=stress
```
**What it tests**: 500 documents rapidly
**Expected output**: System limits and degradation patterns

### Mixed Document Types
```bash
php artisan test:load --type=mixed
```
**What it tests**: PDF, image, email, AIR documents
**Expected output**: Consistent performance across all types

### Error Handling Under Load
```bash
php artisan test:load --type=error
```
**What it tests**: 100 documents with 10% simulated failures
**Expected output**: Proper error logging and recovery

### Daily Throughput Validation
```bash
php artisan test:load --type=daily
```
**What it tests**: Validates 100+ docs/day capability
**Expected output**: Confirms production readiness

## Viewing Results

### Console Output
All tests print comprehensive reports to console:
```
================================================================================
LOAD TEST REPORT
================================================================================

SUMMARY:
  Total Documents:  100
  Success Count:    98
  Failure Count:    2
  Success Rate:     98.0%

THROUGHPUT:
  Duration:         12.45s
  Docs/Second:      8.03
  Docs/Minute:      482.0

LATENCY:
  Min:              45.2ms
  Max:              892.1ms
  Average:          124.5ms
  P50:              118.3ms
  P95:              287.6ms
  P99:              645.8ms

DOCUMENT TYPES:
  pdf: 25/25 successful
  image: 24/24 successful
  email: 25/26 successful
  air: 24/25 successful

================================================================================
```

### JSON Reports
Find detailed JSON reports in:
```bash
storage/app/load-test-reports/
```

Example filenames:
- `sustained_load_test_2026-02-10_16-30-45.json`
- `burst_load_test_2026-02-10_16-35-22.json`

### View Latest Report
```bash
# List recent reports
ls -lt storage/app/load-test-reports/ | head -n 5

# View latest report
cat $(ls -t storage/app/load-test-reports/*.json | head -n 1) | jq .
```

## CI/CD Integration

### GitHub Actions
```yaml
name: Load Tests

on:
  push:
    branches: [master]
  pull_request:
    branches: [master]

jobs:
  load-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2

      - name: Install Dependencies
        run: composer install --no-interaction

      - name: Setup Test Database
        run: php artisan migrate:fresh --seed --env=testing

      - name: Run Sustained Load Test
        run: php artisan test:load --type=sustained

      - name: Upload Reports
        uses: actions/upload-artifact@v3
        if: always()
        with:
          name: load-test-reports
          path: storage/app/load-test-reports/
```

### GitLab CI
```yaml
load-tests:
  stage: test
  script:
    - composer install --no-interaction
    - php artisan migrate:fresh --seed --env=testing
    - php artisan test:load --type=all
  artifacts:
    paths:
      - storage/app/load-test-reports/
    when: always
```

## Advanced Usage

### Custom Document Count
Note: The current implementation uses fixed counts per test type. To customize, modify the test methods in `DocumentProcessingLoadTest.php`.

### Debugging Failures
```bash
# Run with verbose output
php artisan test --filter=test_sustained_load_100_documents Tests/Load/DocumentProcessingLoadTest --verbose

# Check Laravel logs
tail -f storage/logs/laravel.log

# Enable query logging in test
# Add to test setup:
\DB::enableQueryLog();
```

### Performance Monitoring
```bash
# Monitor during test execution
watch -n 1 'php artisan db:show'

# Check database size
php artisan db:show

# Check queue status (if using queues)
php artisan queue:monitor
```

### Comparing Test Results
```bash
# Save baseline
php artisan test:load --type=sustained > baseline_report.txt

# After changes, run again
php artisan test:load --type=sustained > current_report.txt

# Compare
diff baseline_report.txt current_report.txt
```

## Troubleshooting

### "No company found" Error
```bash
# Seed the database first
php artisan migrate:fresh --seed --env=testing
```

### Timeout Errors
Increase timeout in `.env.testing`:
```
HTTP_TIMEOUT=30
```

Or modify `LoadTestHelper.php`:
```php
Http::timeout(30) // Increase from 10
```

### Memory Exhausted
```bash
# Increase PHP memory limit
php -d memory_limit=512M artisan test:load --type=stress
```

### Database Lock Errors (SQLite)
Use PostgreSQL or MySQL for testing:
```bash
# In .env.testing
DB_CONNECTION=mysql
DB_DATABASE=testing
```

## Best Practices

1. **Start Small**: Begin with `sustained` test before running `all`
2. **Baseline First**: Run tests on clean system to establish baseline
3. **Regular Testing**: Run before each release to catch regressions
4. **Monitor Resources**: Watch CPU, memory, database during tests
5. **Clean Between Runs**: Use `migrate:fresh` for consistent results
6. **Save Reports**: Archive reports for trend analysis
7. **Document Changes**: Note any code changes that affect performance

## Example Workflow

```bash
# 1. Prepare environment
php artisan migrate:fresh --seed --env=testing

# 2. Run quick validation
php artisan test:load --type=sustained

# 3. If successful, run comprehensive suite
php artisan test:load --type=all

# 4. Review reports
ls -lt storage/app/load-test-reports/

# 5. Analyze specific report
cat storage/app/load-test-reports/sustained_load_test_*.json | jq .

# 6. Compare with baseline (if exists)
# ... comparison logic ...

# 7. Archive results
mkdir -p load-test-archives/$(date +%Y-%m-%d)
cp storage/app/load-test-reports/* load-test-archives/$(date +%Y-%m-%d)/
```

## Interpreting Results

### Success Rate
- **>95%**: Excellent - production ready
- **90-95%**: Good - acceptable for most use cases
- **85-90%**: Fair - investigate errors
- **<85%**: Poor - requires optimization

### Latency (P95)
- **<1s**: Excellent
- **1-3s**: Good
- **3-5s**: Acceptable
- **>5s**: Needs optimization

### Throughput
- **>100 docs/day**: Meets requirements
- **50-100 docs/day**: Marginal - may need scaling
- **<50 docs/day**: Insufficient - optimization required

### Error Patterns
- **Consistent errors**: Configuration or code issue
- **Sporadic errors**: Network or timing issue
- **Increasing errors**: Resource exhaustion (memory, connections)

## Next Steps

After successful load testing:

1. **Document Baseline**: Save results as reference
2. **Monitor Production**: Compare real-world vs test metrics
3. **Plan Scaling**: Use results to plan infrastructure
4. **Optimize**: Address any performance bottlenecks
5. **Automate**: Add to CI/CD pipeline
6. **Schedule Regular Tests**: Weekly or before releases
