# Test Suite Documentation - TEST-05 & TEST-06

## Overview
This documentation covers the comprehensive error scenario tests (TEST-05) and staging supplier validation tests (TEST-06) for the Soud Laravel N8n integration project.

## Files Created

### 1. ErrorScenarioTest.php
**Location:** `/home/soudshoja/soud-laravel/tests/Feature/ErrorScenarios/ErrorScenarioTest.php`

**Purpose:** Comprehensive error scenario testing covering 17 different error conditions.

**Test Coverage:**

| Test # | Test Name | Scenario | Expected Result |
|--------|-----------|----------|-----------------|
| 1 | `test_malformed_json_payload` | Invalid JSON payload with wrong data types | 422 Validation Error |
| 2 | `test_missing_hmac_signature` | No X-Signature or X-Timestamp headers | 401 Unauthorized |
| 3 | `test_invalid_hmac_signature` | Wrong HMAC signature provided | 401 Unauthorized |
| 4 | `test_expired_timestamp_replay_attack` | Timestamp older than 5 minutes | 401 Unauthorized |
| 5 | `test_n8n_returns_500_error` | N8n API returns 500 Internal Server Error | 503 + Document marked failed |
| 6 | `test_n8n_returns_502_bad_gateway` | N8n API returns 502 Bad Gateway | 503 + Error logged |
| 7 | `test_n8n_connection_timeout` | Connection timeout to N8n API | 500 + Error handling |
| 8 | `test_n8n_read_timeout` | Read timeout from N8n API | 500 + Error handling |
| 9 | `test_callback_with_unknown_document_id` | Callback for non-existent document | 422 Validation Error |
| 10 | `test_callback_with_invalid_status` | Invalid status value in callback | 422 Validation Error |
| 11 | `test_double_callback_idempotency` | Second callback for same document | 409 Conflict |
| 12 | `test_oversized_payload_rejection` | File size exceeds 52MB limit | 422 Validation Error |
| 13 | `test_sql_injection_in_supplier_id` | SQL injection attempt | 422 + Sanitized |
| 14 | `test_xss_in_error_message_escaped` | XSS attempt in error message | 200 + HTML escaped |
| 15 | `test_missing_required_fields_in_callback` | Missing required callback fields | 422 Validation Error |
| 16 | `test_invalid_file_hash_format` | Invalid SHA256 hash format | 422 Validation Error |
| 17 | `test_invalid_document_type` | Unknown document type | 422 Validation Error |

### 2. StagingSupplierTest.php
**Location:** `/home/soudshoja/soud-laravel/tests/Feature/Staging/StagingSupplierTest.php`

**Purpose:** Validate supplier-specific routing, authentication, and processing across all 12 suppliers.

**Test Coverage:**

| Test # | Test Name | Scenario | Coverage |
|--------|-----------|----------|----------|
| 1 | `test_supplier_routing_validation` | All 12 suppliers route correctly | All suppliers |
| 2 | `test_per_supplier_hmac_secrets` | Per-supplier webhook authentication | HMAC per supplier |
| 3 | `test_supplier_fallback_for_unknown_supplier` | Unknown supplier_id handling | Fallback workflow |
| 4 | `test_multi_company_same_supplier_isolation` | Two companies, same supplier | Data isolation |
| 5 | `test_supplier_config_validation` | All required configs exist | Config validation |
| 6 | `test_real_document_simulation_per_supplier` | Real document format testing | 6 major suppliers |
| 7 | `test_supplier_specific_error_handling` | Supplier-specific errors | Error categorization |
| 8 | `test_concurrent_supplier_processing` | Multiple suppliers simultaneously | Concurrency |
| 9 | `test_supplier_credential_validation` | Suppliers with credentials | Credential handling |

**Supplier Coverage:**
1. Amadeus (AIR files)
2. Sabre (PDF documents)
3. Travelport (AIR files)
4. TBO (Email + AIR)
5. Magic Holiday (Images + AIR)
6. Expedia (AIR files)
7. Booking.com (PDF documents)
8. Travco (AIR files)
9. Al-Tayyar (AIR files)
10. IATA BSP (AIR files)
11. Generic Email (Email documents)
12. Manual Upload (All types)

### 3. StagingTestReport.php
**Location:** `/home/soudshoja/soud-laravel/tests/Feature/Staging/StagingTestReport.php`

**Purpose:** Generate comprehensive staging test reports in multiple formats.

**Features:**
- Supplier coverage statistics
- Document type coverage analysis
- Success/failure rates per supplier
- Processing time metrics
- Performance benchmarking
- Issues discovered during testing

**Report Formats Generated:**

1. **Text Report** (`storage/app/reports/staging_test_report.txt`)
   - Plain text format for console viewing
   - Supplier test results
   - Document type coverage
   - Performance metrics

2. **JSON Report** (`storage/app/reports/staging_test_report.json`)
   - Machine-readable format
   - Complete test results data
   - Timestamps and durations
   - Programmatic analysis

3. **Markdown Report** (`storage/app/reports/staging_test_report.md`)
   - Human-readable with formatting
   - Tables for results
   - Success/failure indicators (✅/❌)
   - GitHub/GitLab compatible

## Running the Tests

### Run Error Scenario Tests
```bash
php artisan test --filter=ErrorScenarioTest
```

### Run Staging Supplier Tests
```bash
php artisan test --filter=StagingSupplierTest
```

### Generate Staging Test Report
```bash
php artisan test --filter=StagingTestReport
```

### Run All New Tests
```bash
php artisan test tests/Feature/ErrorScenarios tests/Feature/Staging
```

## Report Output Locations

After running `StagingTestReport`, reports will be available at:

- **Text Report:** `/home/soudshoja/soud-laravel/storage/app/reports/staging_test_report.txt`
- **JSON Report:** `/home/soudshoja/soud-laravel/storage/app/reports/staging_test_report.json`
- **Markdown Report:** `/home/soudshoja/soud-laravel/storage/app/reports/staging_test_report.md`

## Test Metrics

### Error Scenario Tests
- **Total Tests:** 17
- **Coverage Areas:**
  - Validation errors: 6 tests
  - Authentication failures: 3 tests
  - N8n service failures: 4 tests
  - Security attacks: 2 tests
  - Idempotency: 1 test
  - Missing data: 1 test

### Staging Supplier Tests
- **Total Tests:** 9
- **Suppliers Covered:** 12
- **Document Types:** 4 (AIR, PDF, Image, Email)
- **Coverage Areas:**
  - Routing validation
  - Authentication
  - Data isolation
  - Error handling
  - Performance
  - Credentials

## Integration Points

These tests validate:

1. **DocumentProcessingController** (`/api/document-processing`)
   - Input validation
   - N8n webhook invocation
   - Error handling
   - Document logging

2. **N8nCallbackController** (`/api/webhooks/n8n/extraction`)
   - HMAC signature verification
   - Timestamp replay protection
   - Document status updates
   - Idempotency handling

3. **WebhookSigningService**
   - HMAC-SHA256 signing
   - Signature verification
   - Timing-safe comparison

4. **DocumentProcessingLog Model**
   - Status tracking
   - Error logging
   - Execution metadata

## Expected Test Results

### Error Scenario Tests
- ✅ All 17 tests should PASS
- ✅ All error conditions properly handled
- ✅ No unhandled exceptions
- ✅ Proper HTTP status codes returned

### Staging Supplier Tests
- ✅ All 9 tests should PASS
- ✅ All 12 suppliers properly routed
- ✅ All 4 document types supported
- ✅ Data isolation verified
- ✅ Performance metrics within acceptable range

### Staging Test Report
- ✅ Test execution completes successfully
- ✅ All 3 report files generated
- ✅ 100% supplier coverage
- ✅ 100% document type coverage
- ✅ Performance benchmarks recorded

## Troubleshooting

### Test Failures

**Problem:** HMAC signature verification fails
**Solution:** Check `config/services.php` for `n8n.webhook_secret` configuration

**Problem:** N8n connection timeout
**Solution:** Verify HTTP::fake() is properly configured in test

**Problem:** Database errors
**Solution:** Ensure `RefreshDatabase` trait is used and migrations are up to date

**Problem:** Company factory not found
**Solution:** Verify `database/factories/CompanyFactory.php` exists

### Configuration Requirements

Ensure these configs exist:
- `config/services.php` - N8n settings
- `config/webhook.php` - Webhook security settings
- `.env.testing` - Test environment variables

Required environment variables:
```env
N8N_WEBHOOK_URL=http://localhost:5678/webhook/document-processing
N8N_WEBHOOK_SECRET=test-secret-key
```

## Security Considerations

These tests validate critical security features:

1. **HMAC Signature Verification**
   - Prevents unauthorized webhook callbacks
   - Timing-safe comparison prevents timing attacks

2. **Replay Attack Protection**
   - Timestamp validation (5-minute tolerance)
   - Prevents old requests from being replayed

3. **SQL Injection Protection**
   - Laravel validation sanitizes inputs
   - Eloquent ORM prevents SQL injection

4. **XSS Protection**
   - HTML escaping on output
   - Safe storage of user input

5. **Idempotency**
   - Prevents duplicate processing
   - 409 Conflict on second callback

## Performance Benchmarks

Expected performance metrics:

- **Single Document Processing:** < 100ms
- **Batch Processing (10 docs):** < 1000ms
- **Average Throughput:** > 10 docs/second
- **HMAC Verification:** < 5ms
- **Database Insert:** < 10ms

## Next Steps

1. **Run tests** to verify all scenarios pass
2. **Review reports** generated by StagingTestReport
3. **Analyze failures** if any tests fail
4. **Adjust configs** based on test results
5. **Document issues** found during testing
6. **Create tickets** for any bugs discovered

## Continuous Integration

Add to CI/CD pipeline:

```yaml
# .github/workflows/tests.yml
- name: Run Error Scenario Tests
  run: php artisan test --filter=ErrorScenarioTest

- name: Run Staging Supplier Tests
  run: php artisan test --filter=StagingSupplierTest

- name: Generate Test Reports
  run: php artisan test --filter=StagingTestReport

- name: Upload Test Reports
  uses: actions/upload-artifact@v2
  with:
    name: test-reports
    path: storage/app/reports/
```

## Contact & Support

For questions or issues with these tests:
- Review test code comments for detailed explanations
- Check Laravel logs in `storage/logs/laravel.log`
- Verify N8n webhook configuration
- Ensure database migrations are current

---

**Generated:** 2026-02-10
**Version:** 1.0.0
**Author:** Claude Code
**Project:** Soud Laravel N8n Integration
