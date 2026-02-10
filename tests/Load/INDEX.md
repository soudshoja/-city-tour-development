# Load Testing Harness - File Index

## 📂 Directory Structure

```
tests/Load/
├── Core Implementation
│   ├── LoadTestHelper.php              # Helper class with test utilities
│   ├── DocumentProcessingLoadTest.php  # PHPUnit test suite (6 scenarios)
│   └── run-load-test.sh                # Bash script wrapper
│
├── Documentation
│   ├── README.md                       # Complete guide & architecture
│   ├── QUICKSTART.md                   # 3-step quick start
│   ├── USAGE_EXAMPLES.md               # Command examples & workflows
│   ├── TEST_IMPLEMENTATION_SUMMARY.md  # Implementation details
│   └── INDEX.md                        # This file
│
├── Support Files
│   ├── sample-report.json              # Example output report
│   └── verify-setup.sh                 # Setup verification script
│
└── Generated Reports (created at runtime)
    └── storage/app/load-test-reports/

app/Console/Commands/
└── RunLoadTest.php                     # Artisan command
```

## 📄 File Descriptions

### Core Implementation Files

#### `LoadTestHelper.php` (12KB)
**Purpose**: Core utility class for load testing

**Key Methods**:
- `generateDocuments($count, $types)` - Create test payloads
- `submitBatch($documents, $baseUrl, $parallel)` - Submit documents
- `measureThroughput($start, $end, $count)` - Calculate metrics
- `generateReport($results, $throughput)` - Create reports
- `saveReport($report, $filename)` - Persist to storage
- `printReport($report)` - Console output

**Features**:
- Document generation for all types (PDF, image, email, AIR)
- Parallel and sequential submission
- Comprehensive metrics calculation
- JSON report persistence
- Formatted console output

---

#### `DocumentProcessingLoadTest.php` (13KB)
**Purpose**: PHPUnit test suite with 6 load test scenarios

**Test Methods**:
1. `test_sustained_load_100_documents()` - Normal daily operations
2. `test_burst_load_50_documents_parallel()` - Spike handling
3. `test_stress_test_500_documents()` - Breaking point
4. `test_mixed_document_types_load()` - Type diversity
5. `test_error_handling_under_load()` - Error resilience
6. `test_daily_throughput_capability()` - Production validation

**Features**:
- RefreshDatabase for clean state
- HTTP mocking with Http::fake()
- Database verification
- Comprehensive assertions
- Detailed output logging

---

#### `RunLoadTest.php` (7KB)
**Purpose**: Artisan command for CLI execution

**Signature**: `php artisan test:load --type=[TYPE]`

**Options**:
- `--type=sustained` - Sustained load test
- `--type=burst` - Burst load test
- `--type=stress` - Stress test
- `--type=mixed` - Mixed document types
- `--type=error` - Error handling test
- `--type=daily` - Daily throughput test
- `--type=all` - Run all tests

**Features**:
- Friendly CLI interface
- Progress indicators
- Summary output
- Exit codes for CI/CD

---

#### `run-load-test.sh` (5KB)
**Purpose**: Bash wrapper for automated execution

**Usage**: `./tests/Load/run-load-test.sh [TYPE]`

**Features**:
- Environment validation
- Database preparation
- Color-coded output
- Report listing
- Error handling

---

### Documentation Files

#### `README.md` (8KB)
**Comprehensive documentation covering**:
- Overview and test scenarios
- Usage instructions
- Output metrics
- Report structure
- Architecture details
- Development guide
- Troubleshooting
- CI/CD integration
- Best practices

**Best for**: Complete reference guide

---

#### `QUICKSTART.md` (2KB)
**Quick 3-step guide**:
1. Prepare environment
2. Run first test
3. Check results

**Best for**: Getting started quickly

---

#### `USAGE_EXAMPLES.md` (7KB)
**Detailed examples covering**:
- All command variations
- Viewing results
- CI/CD integration
- Advanced usage
- Debugging
- Performance monitoring
- Best practices
- Example workflows

**Best for**: Learning by example

---

#### `TEST_IMPLEMENTATION_SUMMARY.md` (9KB)
**Technical implementation details**:
- Files created
- Test scenarios
- Feature breakdown
- Metrics definition
- Technical architecture
- Success criteria
- Next steps

**Best for**: Understanding implementation

---

#### `INDEX.md` (this file)
**Navigation guide**:
- Directory structure
- File descriptions
- Usage flowchart
- Quick reference

**Best for**: Finding the right file

---

### Support Files

#### `sample-report.json` (1KB)
Example JSON report showing output format

#### `verify-setup.sh` (executable)
Script to verify all files are in place and environment is ready

---

## 🎯 Quick Reference

### "I want to..."

**...run my first test**
→ Start with `QUICKSTART.md`

**...understand all available tests**
→ Read `README.md` → Test Scenarios section

**...see command examples**
→ Read `USAGE_EXAMPLES.md`

**...understand the implementation**
→ Read `TEST_IMPLEMENTATION_SUMMARY.md`

**...customize a test**
→ Read `README.md` → Development section

**...troubleshoot an issue**
→ Read `README.md` → Troubleshooting section

**...integrate with CI/CD**
→ Read `USAGE_EXAMPLES.md` → CI/CD Integration

**...verify setup**
→ Run `./tests/Load/verify-setup.sh`

---

## 🔄 Usage Flowchart

```
┌─────────────────────────┐
│  First Time User?       │
└───────────┬─────────────┘
            │
            ├─ YES → Read QUICKSTART.md
            │        │
            │        ├─ Run: php artisan test:load --type=sustained
            │        │
            │        └─ View report → Done! ✓
            │
            └─ NO  → Choose your path:
                     │
                     ├─ Need examples? → USAGE_EXAMPLES.md
                     │
                     ├─ Need full docs? → README.md
                     │
                     ├─ Troubleshooting? → README.md → Troubleshooting
                     │
                     └─ Technical details? → TEST_IMPLEMENTATION_SUMMARY.md
```

---

## 📊 Test Selection Guide

```
┌─────────────────────────────────────────────────────────────┐
│  What do you want to test?                                  │
└─────────────────────────────────────────────────────────────┘

Normal Operations (100 docs/day)
→ php artisan test:load --type=sustained

Spike Handling (50 parallel requests)
→ php artisan test:load --type=burst

System Limits (500 docs rapidly)
→ php artisan test:load --type=stress

Document Type Handling (PDF/image/email/AIR)
→ php artisan test:load --type=mixed

Error Resilience (10% failures)
→ php artisan test:load --type=error

Production Readiness (throughput validation)
→ php artisan test:load --type=daily

Complete Validation (all of the above)
→ php artisan test:load --type=all
```

---

## 🚀 Common Commands

```bash
# Verify setup
./tests/Load/verify-setup.sh

# Prepare database
php artisan migrate:fresh --seed --env=testing

# Run sustained test
php artisan test:load --type=sustained

# Run all tests
php artisan test:load --type=all

# Using bash script
./tests/Load/run-load-test.sh sustained

# Direct PHPUnit
php artisan test Tests/Load/DocumentProcessingLoadTest

# View latest report
cat $(ls -t storage/app/load-test-reports/*.json | head -n 1) | jq .

# List all reports
ls -lt storage/app/load-test-reports/
```

---

## 📈 Metrics Reference

### Summary Metrics
- Total documents processed
- Success/failure counts
- Success rate percentage

### Throughput Metrics
- Duration (seconds)
- Docs per second
- Docs per minute
- Projected daily throughput

### Latency Metrics
- Min/Max/Average (ms)
- P50 - Median latency
- P95 - 95th percentile
- P99 - 99th percentile

### Error Analysis
- Total error count
- Breakdown by status code
- Per-document-type statistics

---

## 🎓 Learning Path

### Beginner
1. Read `QUICKSTART.md`
2. Run `php artisan test:load --type=sustained`
3. View generated report
4. Read `USAGE_EXAMPLES.md` for more commands

### Intermediate
1. Read `README.md` completely
2. Run all test types individually
3. Compare reports
4. Read `TEST_IMPLEMENTATION_SUMMARY.md`

### Advanced
1. Review source code in `LoadTestHelper.php`
2. Understand test implementation in `DocumentProcessingLoadTest.php`
3. Customize tests for specific scenarios
4. Integrate with CI/CD pipeline

---

## 📞 Support Resources

| Issue Type | Resource |
|------------|----------|
| Quick start | `QUICKSTART.md` |
| Commands | `USAGE_EXAMPLES.md` |
| Understanding output | `README.md` → Output Metrics |
| Errors | `README.md` → Troubleshooting |
| Customization | `README.md` → Development |
| Implementation | `TEST_IMPLEMENTATION_SUMMARY.md` |
| Setup verification | `./tests/Load/verify-setup.sh` |

---

## ✅ File Checklist

- [x] LoadTestHelper.php
- [x] DocumentProcessingLoadTest.php
- [x] RunLoadTest.php (Artisan command)
- [x] run-load-test.sh
- [x] README.md
- [x] QUICKSTART.md
- [x] USAGE_EXAMPLES.md
- [x] TEST_IMPLEMENTATION_SUMMARY.md
- [x] INDEX.md
- [x] sample-report.json
- [x] verify-setup.sh

**All files present and ready for use!** ✓

---

**Last Updated**: 2026-02-10
**Version**: 1.0.0
**Status**: Production Ready
