# Phase 02: Core Skills - DOTWconnect v4 Integration Patterns

## Overview

This phase documents production-ready patterns for integrating DOTWconnect v4 XML API into Laravel 11 applications. The research builds on Phase 1 (API Methods Reference) to provide concrete implementation guidance.

## Key Deliverable

**Document:** `02-INTEGRATION_PATTERNS.md` (1,555 lines)

### What's Covered

1. **XML Request/Response Handling** (§1)
   - Request building with authentication wrapper
   - Safe response parsing with XXE prevention
   - Gzip compression requirements

2. **MD5 Password Encryption** (§2)
   - Why MD5 is required (v4 API requirement, not choice)
   - Safe implementation patterns
   - Encrypted credential storage in database

3. **3-Minute Rate Lock Management** (§3)
   - Rate lock lifecycle (preview → block → confirm)
   - State management with cache + database
   - Expiration detection and warning

4. **Error Handling for Rate Expiration** (§4)
   - DOTWconnect error code mapping
   - User-friendly error messages
   - Rate change detection

5. **Booking State Persistence** (§5)
   - Database schemas for rate locks and bookings
   - State machine for booking workflow
   - Audit trail support

6. **Multi-Tenant Integration** (§6)
   - Company isolation patterns
   - Agent context tracking
   - Audit logging for compliance

7. **Testing Patterns** (§7)
   - Mocked XML response testing
   - XXE security tests
   - Feature tests with database

8. **Security Best Practices** (§8)
   - Secrets management
   - Logging without credential leaks
   - XXE attack prevention
   - Multi-tenant isolation enforcement

9. **Performance Considerations** (§9)
   - Cache strategy for rate locks (Redis vs File)
   - Streaming XML parsing for large results

10. **Monitoring & Observability** (§10)
    - Health check commands
    - Key metrics to track

## Critical Implementation Points

### ✅ Do This

```php
// Encrypt credentials in database
$credential->dotw_password;  // Auto-decrypted by Eloquent cast

// Hash only when building request
$md5Hash = md5($credential->dotw_password);

// Store rate locks with TTL
Cache::put("rate_lock:{$lockId}", $data, now()->addMinutes(3));

// Always filter by company_id
RateLockToken::where('company_id', $companyId)->where('lock_id', $lockId)->first();

// Disable XXE before parsing
libxml_disable_entity_loader(true);
$xml = simplexml_load_string($xmlString);
```

### ❌ Don't Do This

```php
// Never store MD5 hashes
Cache::put('dotw_pwd', md5($credential->dotw_password));

// Never log credentials
Log::error('DOTW failed', ['password' => $credential->dotw_password]);

// Never skip company_id in queries
RateLockToken::where('lock_id', $lockId)->first();  // SECURITY BUG!

// Never parse untrusted XML without XXE prevention
$xml = simplexml_load_string($untrustedXml);
```

## Integration Points with Soud Laravel

The document references existing code in the Soud Laravel codebase:

- **DotwService** (`/app/Services/DotwService.php`) - Production v4 client implementation
- **DotwCacheService** (`/app/Services/DotwCacheService.php`) - Search caching with company isolation
- **DotwAuditService** - Audit logging
- **CompanyDotwCredential** - Encrypted credential model

## Testing Recommendations

1. **Unit Tests:** Mock XML responses for all v4 methods
2. **Security Tests:** Verify XXE prevention, test rate lock isolation
3. **Feature Tests:** Test complete workflows (search → preview → block → confirm)
4. **Load Tests:** Verify cache performance under concurrent rate locks

## Production Checklist

- [ ] XXE protection enabled (libxml_disable_entity_loader)
- [ ] Credentials encrypted in database
- [ ] MD5 hashing only at API call time
- [ ] Rate locks with 3-minute TTL in cache + audit in DB
- [ ] All queries filtered by company_id
- [ ] Logging contains no credentials
- [ ] Error messages translated for users
- [ ] Health checks configured
- [ ] Metrics tracked (rate lock expiration, confirmation time)

## Next Phase

Phase 3 should implement concrete controllers and jobs using these patterns:
- SearchHotelsController → getRoomsController → ConfirmBookingController
- Queue jobs for long-running confirmations
- GraphQL resolvers with rate lock validation

## Sources Referenced

- Phase 1: API_METHODS.md (v4 critical requirements)
- Phase 1: PITFALLS.md (multi-tenant isolation patterns)
- Phase 1: STACK.md (technology stack validation)
- Laravel 11 Official Documentation
- OWASP XXE Prevention Guide
- Soud Laravel existing implementation patterns

---

**Research Date:** 2026-03-09  
**Framework:** Laravel 11, PHP 8.2+  
**Status:** Ready for Phase 3 implementation
