# DOTWconnect v4 Quick Reference Card

## v4 API Mandatory Requirements

| Requirement | Detail | Consequence of Missing |
|---|---|---|
| **getRooms MANDATORY** | Must call getRooms (not optional) | Search incomplete, bookings fail |
| **Dual getRooms Pattern** | Preview call (no block) + Block call (lock) | Cannot confirm without lock |
| **3-Minute Rate Lock** | Hard limit from block to confirmation | Token expires, must re-search |
| **allocationDetails Token** | Required in confirmBooking/savebooking | Confirmation rejected |
| **MD5 Password Hash** | `<password>` field must be MD5-hashed | Authentication fails |
| **Gzip Compression** | All requests/responses gzipped | API rejects connection |

---

## XML Request Template

```xml
<customer>
  <username>DOTW_USERNAME</username>
  <password>MD5_HASH_HERE</password>
  <id>COMPANY_CODE</id>
  <source>1</source>
  <product>hotel</product>
  <request command="METHOD_NAME">
    <!-- Method-specific payload -->
  </request>
</customer>
```

## Rate Lock State Diagram

```
search
  ↓
getRooms (preview, no block) → [ALLOCATIONDETAILS_1]
  ↓
getRooms (with block) → [ALLOCATIONDETAILS_2] ← CACHE: 3 minutes TTL
  ↓
[WINDOW CLOSED - 3 minutes]
  ↓
confirmBooking/savebooking with allocationDetails
  ↓
✓ SUCCESS or ✗ RATE_LOCK_EXPIRED
```

## Cache Keys Pattern

```
rate_lock:{LOCK_ID} → {allocation_details, hotel_id, room_id, rate}
   TTL: 180 seconds (3 minutes)
   Driver: Redis (production) or File (dev)
```

## Database Query Filters

```php
// ALWAYS include company_id
RateLockToken::where('company_id', $companyId)
    ->where('lock_id', $lockId)
    ->first();

// NEVER query without company_id
RateLockToken::where('lock_id', $lockId)->first();  // SECURITY BUG
```

## Error Codes

| Code | Meaning | Fix |
|---|---|---|
| 1100 | Allocation expired | Re-search, call getRooms again |
| 1101 | Invalid allocation token | Use token from latest block call |
| 1200 | Hotel not found | Verify hotelId from searchHotels |
| 1300 | Rate changed | Rates unstable, re-search |
| 5000 | DOTW server error | Retry with exponential backoff |

## Security Checklist

```
[ ] Credentials encrypted in database (Eloquent cast)
[ ] MD5 hash ONLY when building request
[ ] MD5 hash NEVER stored or logged
[ ] XXE disabled: libxml_disable_entity_loader(true)
[ ] All queries filtered by company_id
[ ] No credentials in logs (use company_id instead)
[ ] Rate lock validated before confirmation
[ ] Gzip compression enabled on requests
```

## Performance Benchmarks

| Operation | Expected Time | Cache? |
|---|---|---|
| searchHotels | 5-30 seconds | Yes (150 sec) |
| getRooms preview | 2-10 seconds | No |
| getRooms blocking | 2-10 seconds | No (must be fresh) |
| confirmBooking | 5-15 seconds | No |
| validate rate lock | <1ms | Yes (cache) |

## Common Gotchas

### Gotcha 1: Using Old allocationDetails
```php
// ❌ WRONG: Using token from preview call
$preview = getRooms($hotelId, false);
$block = getRooms($hotelId, true);
confirmBooking($preview->allocationDetails);  // ✗ Uses preview token!

// ✅ CORRECT: Using token from block call
confirmBooking($block->allocationDetails);   // ✓ Uses lock token
```

### Gotcha 2: Rate Lock Expiration
```php
// ❌ WRONG: Not checking expiration
$lock = Cache::get("rate_lock:{$lockId}");
if ($lock) confirmBooking($lock);  // May be stale!

// ✅ CORRECT: Validate before use
$lock = Cache::get("rate_lock:{$lockId}");
if (!$lock) throw new RateLockExpiredException();
if (secondsRemaining($lockId) < 0) throw new RateLockExpiredException();
confirmBooking($lock);
```

### Gotcha 3: Company Isolation
```php
// ❌ WRONG: No company filter
$lock = RateLockToken::where('lock_id', $lockId)->first();
// Agent A could get Agent B's lock!

// ✅ CORRECT: Always filter company
$lock = RateLockToken::where('company_id', auth()->user()->company_id)
    ->where('lock_id', $lockId)
    ->first();
```

### Gotcha 4: XXE Injection
```php
// ❌ WRONG: Parsing untrusted XML
$xml = simplexml_load_string($dotwResponse);

// ✅ CORRECT: Disable external entities first
libxml_disable_entity_loader(true);
try {
    $xml = simplexml_load_string($dotwResponse);
} finally {
    libxml_disable_entity_loader(false);
}
```

## Logging Safe Patterns

```php
// ✅ CORRECT: Log company context
Log::channel('dotw')->info('Rate locked', [
    'company_id' => $companyId,
    'lock_id' => $lockId,
    'hotel_id' => $hotelId,
]);

// ❌ WRONG: Logging secrets
Log::error('Auth failed', [
    'username' => $credential->username,  // ✗ Exposed!
    'password' => $credential->password,  // ✗ Exposed!
]);
```

## Recovery Procedures

### If Rate Lock Expires
```php
// User gets: "Rate lock expired (3 min limit). Please search again."
// Agent action: New searchHotels call
// Backend: Create new rate lock from fresh getRooms call
```

### If confirmBooking Fails
```php
// Check error code:
// - 1100/1101: Rate lock expired → new search required
// - 1200: Hotel not available → new search required
// - 1300: Rate changed → new search required
// - 5000: Server error → retry with backoff
```

### If Rate Lock in Cache but DB Missing
```php
// Indicates: Cache and DB out of sync
// Action: Log with high priority
// Fix: Invalidate cache, require new lock
Log::critical('Rate lock sync error', [
    'lock_id' => $lockId,
    'in_cache' => true,
    'in_db' => false,
]);
Cache::forget("rate_lock:{$lockId}");
```

## Monitoring Queries

```sql
-- Rate locks about to expire
SELECT * FROM rate_lock_tokens
WHERE status = 'active'
AND expires_at < DATE_ADD(NOW(), INTERVAL 30 SECOND);

-- Rate locks that expired
SELECT * FROM rate_lock_tokens
WHERE status = 'expired'
AND cleared_at IS NULL
AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Bookings stuck in "saved" state (deferred flow)
SELECT * FROM dotw_bookings
WHERE state = 'saved'
AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Rate lock expiration rate
SELECT
    DATE(expires_at) as date,
    COUNT(*) as total_locks,
    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
    ROUND(100.0 * SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) / COUNT(*), 2) as expiration_rate
FROM rate_lock_tokens
GROUP BY DATE(expires_at)
ORDER BY date DESC;
```

## Configuration Values

```php
// config/dotw.php
'rate_lock' => [
    'ttl_seconds' => 180,              // 3 minutes (v4 requirement)
    'warning_threshold' => 30,         // Warn at 30 seconds remaining
    'cache_driver' => 'redis',         // Use Redis in production
    'db_audit' => true,                // Always audit to DB
];

// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),  // Must be Redis for distributed
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'prefix' => 'dotw_',
    ],
];

// config/logging.php
'channels' => [
    'dotw' => [
        'driver' => 'single',
        'path' => storage_path('logs/dotw.log'),
        'level' => 'info',
    ],
];
```

## Testing Checklist

- [ ] Test preview getRooms (no lock)
- [ ] Test block getRooms (with lock)
- [ ] Test immediate confirmBooking (3-min flow)
- [ ] Test savebooking → bookItinerary (deferred flow)
- [ ] Test rate lock expiration (wait 3+ min)
- [ ] Test rate lock validation (use old token)
- [ ] Test XXE injection prevention
- [ ] Test company isolation (cross-tenant query)
- [ ] Test error codes (1100, 1200, 5000)
- [ ] Test concurrent rate locks

---

**Last Updated:** 2026-03-09
**Format:** Quick reference for developers
**For Details:** See `02-INTEGRATION_PATTERNS.md`
