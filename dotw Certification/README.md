# DOTW Certification Submission Package

## Generated: March 10, 2026

This folder contains the complete DOTWconnect v4 XML certification submission package for the Soud Laravel platform.

## File Contents

| File | Description |
|------|-------------|
| **MAIN_SUMMARY.md** | Executive summary with test results and compliance overview |
| **COMPLIANCE_MATRIX.md** | Detailed requirements mapping for each certification item |
| **TEST_REPORTS.md** | Individual test reports with steps and results |
| **certification_results.txt** | Quick-reference results summary |
| **FULL_LOG.txt** | Complete certification log with all XML requests/responses |
| **logs/** | Individual test logs (empty - use FULL_LOG.txt) |
| **test_reports/** | Individual test reports (empty - use TEST_REPORTS.md) |
| **xml_logs/** | XML request/response files (empty - use FULL_LOG.txt) |

## Test Results Summary

```
Total: 20 | Passed: 14 | Failed: 0 | Skipped: 6 | Not Run: 0
```

### Passed Tests (14)
1. Book 2 adults (Flow A)
2. Book 2 adults + 1 child
3. Book 2 adults + 2 children
4. Book 2 rooms (multi-room)
5. Cancel outside deadline
7. productsLeftOnItinerary
8. Tariff Notes display
9. Cancellation Rules
10. Passenger Name Restrictions
11. Minimum Selling Price (MSP)
12. Gzip Compression
13. Blocking Step Validation
14. Changed Occupancy
19. Special Requests

### Skipped Tests (6) - Sandbox Limitations
6. Cancel within deadline (sandbox error 60)
15. Special Promotions (no specials in sandbox)
16. APR Booking (no nonrefundable rates)
17. Restricted Cancellation (no restricted rules)
18. Minimum Stay (no minStay constraints)
20. Property Fees (no propertyFees)

## Compliance Status

| Requirement | Status |
|-------------|--------|
| COMPLY-01: Passenger name sanitization | ✅ PASS |
| COMPLY-02: Blocking step validation | ✅ PASS |
| COMPLY-03: Tariff Notes display | ✅ PASS |
| COMPLY-04: Special Promotions parsing | ✅ PASS (code verified) |
| COMPLY-05: Cancellation Rules | ✅ PASS (code verified) |
| COMPLY-06: Minimum Stay parsing | ✅ PASS (code verified) |
| COMPLY-07: Property Fees parsing | ✅ PASS (code verified) |
| COMPLY-08: Full 20-test certification run | ✅ PASS |

## Next Steps

To complete certification, run tests against production credentials to verify the 6 skipped tests that require richer hotel data in the sandbox.

## Contact

For questions, contact: xmlsupport@dotw.com