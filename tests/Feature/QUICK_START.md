# Quick Start Guide - TEST-05 & TEST-06

## Running Tests

### Run All New Tests
```bash
cd /home/soudshoja/soud-laravel
php artisan test tests/Feature/ErrorScenarios tests/Feature/Staging
```

### Run Individual Test Suites

**Error Scenarios (TEST-05):**
```bash
php artisan test --filter=ErrorScenarioTest
```

**Staging Suppliers (TEST-06):**
```bash
php artisan test --filter=StagingSupplierTest
```

**Generate Reports:**
```bash
php artisan test --filter=StagingTestReport
```

## View Test Reports

After running `StagingTestReport`, view the generated reports:

```bash
# Text report
cat storage/app/reports/staging_test_report.txt

# Markdown report (formatted)
cat storage/app/reports/staging_test_report.md

# JSON report (for automation)
cat storage/app/reports/staging_test_report.json
```

## Expected Results

### Success Indicators
- ✅ All ErrorScenarioTest tests PASS (17/17)
- ✅ All StagingSupplierTest tests PASS (9/9)
- ✅ StagingTestReport generates 3 report files
- ✅ 100% supplier coverage (12/12)
- ✅ 100% document type coverage (4/4)

### Example Output
```
PASS  Tests\Feature\ErrorScenarios\ErrorScenarioTest
  ✓ test malformed json payload
  ✓ test missing hmac signature
  ✓ test invalid hmac signature
  ✓ test expired timestamp replay attack
  ... (13 more tests)

PASS  Tests\Feature\Staging\StagingSupplierTest
  ✓ test supplier routing validation
  ✓ test per supplier hmac secrets
  ✓ test supplier fallback for unknown supplier
  ... (6 more tests)

PASS  Tests\Feature\Staging\StagingTestReport
  ✓ test generate staging report

Tests:    27 passed (27 assertions)
Duration: 5.23s
```

## Test Coverage Summary

### ErrorScenarioTest (17 tests)
1. Malformed JSON payload → 422
2. Missing HMAC signature → 401
3. Invalid HMAC signature → 401
4. Expired timestamp (replay attack) → 401
5. N8n returns 500 → Document marked failed
6. N8n returns 502 → Error logged
7. N8n connection timeout → Timeout handling
8. N8n read timeout → Timeout handling
9. Unknown document_id callback → 422
10. Invalid status callback → 422
11. Double callback (idempotency) → 409
12. Oversized payload → 422
13. SQL injection attempt → Sanitized
14. XSS in error_message → Escaped
15. Missing required fields → 422
16. Invalid file hash → 422
17. Invalid document type → 422

### StagingSupplierTest (9 tests)
1. Supplier routing validation (12 suppliers)
2. Per-supplier HMAC secrets
3. Supplier fallback handling
4. Multi-company isolation
5. Supplier config validation
6. Real document simulation (6 suppliers)
7. Supplier-specific error handling
8. Concurrent processing (5 suppliers)
9. Supplier credential validation

## Troubleshooting

### Common Issues

**Issue:** `Class 'Database\Factories\CompanyFactory' not found`
**Fix:**
```bash
php artisan make:factory CompanyFactory
```

**Issue:** `Table 'document_processing_logs' doesn't exist`
**Fix:**
```bash
php artisan migrate:fresh --env=testing
```

**Issue:** `N8N_WEBHOOK_SECRET not configured`
**Fix:** Add to `.env.testing`:
```env
N8N_WEBHOOK_URL=http://localhost:5678/webhook/document-processing
N8N_WEBHOOK_SECRET=test-secret-key
```

**Issue:** Tests timeout
**Fix:** Increase timeout in `phpunit.xml`:
```xml
<env name="TEST_TIMEOUT" value="30"/>
```

## What Gets Tested

### Security ✅
- HMAC signature verification
- Replay attack protection
- SQL injection prevention
- XSS attack prevention
- Idempotency enforcement

### Routing ✅
- All 12 suppliers routed correctly
- Document type handling (AIR, PDF, Image, Email)
- Unknown supplier fallback

### Error Handling ✅
- N8n service failures
- Connection timeouts
- Invalid payloads
- Missing data
- Validation errors

### Performance ✅
- Single document processing time
- Batch processing throughput
- HMAC verification speed
- Database operation speed

### Data Integrity ✅
- Multi-company isolation
- Document status tracking
- Error logging
- Execution metadata

## Next Actions

1. ✅ Run tests: `php artisan test tests/Feature/ErrorScenarios tests/Feature/Staging`
2. ✅ Review reports: `cat storage/app/reports/staging_test_report.md`
3. ✅ Check coverage: Verify 100% supplier and document type coverage
4. ✅ Performance: Review processing times in report
5. ✅ Fix issues: Address any failing tests

## Report Example

After running `StagingTestReport`, you'll see:

```
[REPORT] Text report saved to: /home/soudshoja/soud-laravel/storage/app/reports/staging_test_report.txt
[REPORT] JSON report saved to: /home/soudshoja/soud-laravel/storage/app/reports/staging_test_report.json
[REPORT] Markdown report saved to: /home/soudshoja/soud-laravel/storage/app/reports/staging_test_report.md
```

View the Markdown report for best formatting:

```bash
cat storage/app/reports/staging_test_report.md
```

Sample report content:
```markdown
# Staging Test Report

**Generated:** 2026-02-10 16:00:00
**Duration:** 5.23 seconds

## Summary

| Metric | Value |
|--------|-------|
| Total Suppliers Tested | 12 |
| Suppliers Passed | 12 |
| Suppliers Failed | 0 |
| Success Rate | 100.00% |
| Document Types Tested | 4 |

## Supplier Test Results

| ID | Supplier | Status | Processing Time (ms) |
|----|----------|--------|---------------------|
| 1 | Amadeus | ✅ PASS | 45.23 |
| 2 | Sabre | ✅ PASS | 42.18 |
...
```

---

**Quick Reference**
- 📁 Error Tests: `tests/Feature/ErrorScenarios/ErrorScenarioTest.php`
- 📁 Supplier Tests: `tests/Feature/Staging/StagingSupplierTest.php`
- 📁 Report Generator: `tests/Feature/Staging/StagingTestReport.php`
- 📊 Reports Location: `storage/app/reports/`
- 📚 Full Docs: `tests/Feature/TEST_SUITE_DOCUMENTATION.md`
