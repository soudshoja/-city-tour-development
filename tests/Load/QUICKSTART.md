# Load Testing Quick Start Guide

## 🚀 Get Started in 3 Steps

### Step 1: Prepare Environment
```bash
cd /home/soudshoja/soud-laravel
php artisan migrate:fresh --seed --env=testing
```

### Step 2: Run Your First Test
```bash
php artisan test:load --type=sustained
```

### Step 3: Check Results
```bash
# View latest report
cat $(ls -t storage/app/load-test-reports/*.json | head -n 1) | jq .
```

## 📊 What You'll See

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

================================================================================
```

## ⚡ Quick Commands

```bash
# Sustained Load (100 docs in batches)
php artisan test:load --type=sustained

# Burst Load (50 docs parallel)
php artisan test:load --type=burst

# Stress Test (500 docs rapidly)
php artisan test:load --type=stress

# Mixed Document Types
php artisan test:load --type=mixed

# Error Handling Test
php artisan test:load --type=error

# Daily Throughput Validation
php artisan test:load --type=daily

# Run ALL Tests
php artisan test:load --type=all
```

## 🎯 Success Criteria

| Metric | Target | Status |
|--------|--------|--------|
| Daily Throughput | 100+ docs/day | ✅ |
| Success Rate | ≥90% | ✅ |
| P95 Latency | <5 seconds | ✅ |
| P99 Latency | <10 seconds | ✅ |

## 📁 Where to Find Reports

```bash
storage/app/load-test-reports/
```

Each test creates a timestamped JSON report with complete metrics.

## 🔍 Need More Info?

- **Full Documentation**: `tests/Load/README.md`
- **Usage Examples**: `tests/Load/USAGE_EXAMPLES.md`
- **Implementation Details**: `tests/Load/TEST_IMPLEMENTATION_SUMMARY.md`

## ⚠️ Troubleshooting

### "No company found"
```bash
php artisan migrate:fresh --seed --env=testing
```

### Timeout errors
```bash
# Increase timeout in LoadTestHelper.php
Http::timeout(30) // Change from 10 to 30
```

### Memory issues
```bash
php -d memory_limit=512M artisan test:load --type=stress
```

## 🎉 That's It!

You now have a complete load testing harness that can:
- ✅ Validate 100+ documents/day throughput
- ✅ Measure latency and success rates
- ✅ Test error handling
- ✅ Find system breaking points
- ✅ Generate comprehensive reports

Happy testing! 🚀
