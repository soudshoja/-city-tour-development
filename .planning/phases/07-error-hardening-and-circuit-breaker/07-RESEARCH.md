# Phase 7: Error Hardening & Circuit Breaker - Research

**Researched:** 2026-02-21
**Domain:** DOTW error standardisation, timeout interception, circuit breaker via Laravel Cache, credential-missing guard, structured 'dotw' channel logging
**Confidence:** HIGH

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| ERROR-01 | Invalid/missing company credentials → "DOTW credentials not configured for this company" (not a stack trace or 500) | DotwService constructor already throws RuntimeException with correct message; resolvers already catch RuntimeException → CREDENTIALS_NOT_CONFIGURED. Gap: DotwGetCities and any Phase 6 resolver (DotwCreatePreBooking) must also catch RuntimeException the same way |
| ERROR-02 | DOTW API timeout > 25 sec → "Search taking too long, please try again" with action: retry | `post()` in DotwService uses `->timeout($this->timeout)` (currently 120s from config). Laravel Http client throws `Illuminate\Http\Client\ConnectionException` on timeout. DotwService `post()` catches generic Exception — does not distinguish timeouts. Gap: DotwService must be patched to (a) set DOTW_TIMEOUT=25 in config, and (b) catch `ConnectionException` before generic `Exception` and rethrow a typed `DotwTimeoutException` (or use sentinel message). Resolvers must catch this and return API_TIMEOUT + RETRY. |
| ERROR-07 | All errors logged to 'dotw' channel; log entries never include credentials or full response bodies | 'dotw' log channel already configured in `config/logging.php`. DotwService already uses `$this->logger` (Log::channel('dotw')). Gap: verify no resolver accidentally logs credentials or raw XML bodies > 500 chars; verify DotwAuditService::log() never writes raw $request containing credentials |
| ERROR-08 | Circuit breaker: 5 failures in 1 minute → return cached search results (if available) or "Try again in 30 seconds" with action: retry_in_30_seconds | No circuit breaker exists. Must be implemented as new DotwCircuitBreakerService using Laravel Cache (atomic increment counter per company_id with 60-second TTL). Open circuit bypasses DotwService, checks DotwCacheService first, falls back to CIRCUIT_BREAKER_OPEN error. Plugs into DotwSearchHotels resolver — only search uses circuit breaker (not rates/booking). |
</phase_requirements>

---

## Summary

Phase 7 is an error-hardening overlay on the existing DOTW integration. It does NOT add new DOTW operations — it makes the existing surface (search, rates, blocking, booking) fail gracefully and safely. The four requirements translate to four precise code changes:

1. **ERROR-01** — Credential guard audit: verify every DOTW GraphQL resolver catches `RuntimeException` and returns `CREDENTIALS_NOT_CONFIGURED`. Currently verified for DotwSearchHotels and DotwGetRoomRates. DotwBlockRates, DotwCreatePreBooking (Phase 6), and DotwGetCities need to be verified/patched.

2. **ERROR-02** — Timeout interception: reduce `DOTW_TIMEOUT` config to 25 seconds and add a `ConnectionException` catch block to `DotwService::post()` that rethrows a distinguishable exception. All resolvers catch the timeout and return `API_TIMEOUT + RETRY`.

3. **ERROR-07** — Log audit: mechanically verify DotwService log calls never include credentials ($username, $passwordMd5, $companyCode) or response bodies > 500 chars. DotwAuditService log() request parameter must not pass full XML. This is already largely correct (confirmed in existing code) but needs to be formally checked and locked.

4. **ERROR-08** — Circuit breaker: new `DotwCircuitBreakerService` class using `Cache::increment()` with a 60-second window per `company_id`. The circuit breaker wraps the DOTW call in `DotwSearchHotels::__invoke()`. Open circuit → try DotwCacheService → if cache hit return cached results with `cached: true`; if cache miss return `CIRCUIT_BREAKER_OPEN` error with `RETRY_IN_30_SECONDS`.

**Important scoping decision:** Circuit breaker applies ONLY to `DotwSearchHotels` — search is the highest-volume, most abuse-prone operation, and it has cache to fall back to. Rate browsing (`DotwGetRoomRates`) and rate blocking (`DotwBlockRates`) are intentionally never cached and are user-initiated point operations; wrapping them in a circuit breaker would block valid bookings incorrectly. Phase 7 success criteria (SC-4) confirms this: "subsequent search requests return cached results".

---

## Standard Stack

### Core (already installed and in use)

| Library | Version | Purpose | Notes |
|---------|---------|---------|-------|
| Laravel 11 | 11.x | Cache::increment(), Cache::has(), Cache::forget() | Circuit breaker counter storage |
| DotwService | existing | DOTW API calls — post() patch target | Constructor unchanged, post() gets ConnectionException catch |
| DotwCacheService | existing | Search result cache read (circuit breaker fallback) | isCached() + get() — no new methods needed |
| DotwAuditService | existing | Sanitized audit logging | Already strips credentials; needs formal verification |
| Illuminate\Http\Client\ConnectionException | Laravel HTTP | Thrown on request timeout | Caught in DotwService::post() before generic Exception |
| Log::channel('dotw') | Laravel | Structured error logging | Already wired in DotwService and all resolvers |

### New for this phase

| Class | Location | Purpose |
|-------|----------|---------|
| `DotwCircuitBreakerService` | `app/Services/DotwCircuitBreakerService.php` | Circuit breaker logic: record failure, check state, reset |
| `DotwTimeoutException` | `app/Exceptions/DotwTimeoutException.php` | Typed exception for timeout disambiguation in resolvers |

**Installation:** No new Composer packages. All dependencies are Laravel built-ins + existing project classes.

---

## Architecture Patterns

### Pattern 1: Circuit Breaker via Cache::increment()

Laravel's `Cache::increment()` is atomic (when using Redis/Memcached/database drivers) and returns the new counter value. Using a per-company cache key with a TTL-reset approach:

```
Key: dotw_circuit_failures_{companyId}
Value: integer (number of failures in current window)
TTL: 60 seconds (reset on first failure — sliding window approximation)
Threshold: 5 failures → open circuit
Open circuit key: dotw_circuit_open_{companyId} → TTL 30 seconds
```

Two-key approach is cleaner than embedding the open/closed state in the counter:
- `dotw_circuit_failures_{companyId}` — rolling failure counter (TTL 60s, reset on first write)
- `dotw_circuit_open_{companyId}` — circuit open flag (TTL 30s from activation)

```php
// DotwCircuitBreakerService
public function isOpen(int $companyId): bool
{
    return Cache::has("dotw_circuit_open_{$companyId}");
}

public function recordFailure(int $companyId): void
{
    $key = "dotw_circuit_failures_{$companyId}";
    // Add with 60s TTL only if not already exists (first failure starts the window)
    Cache::add($key, 0, 60);
    $count = Cache::increment($key);

    if ($count >= 5) {
        Cache::put("dotw_circuit_open_{$companyId}", true, 30);
        Log::channel('dotw')->warning('DOTW circuit breaker opened', [
            'company_id' => $companyId,
            'failure_count' => $count,
        ]);
    }
}

public function recordSuccess(int $companyId): void
{
    Cache::forget("dotw_circuit_failures_{$companyId}");
    Cache::forget("dotw_circuit_open_{$companyId}");
}
```

### Pattern 2: Resolver Integration (DotwSearchHotels only)

```php
// In DotwSearchHotels::__invoke() — BEFORE the try/catch block:
if ($this->circuitBreaker->isOpen($companyId)) {
    // Try cache fallback first
    if ($this->cache->isCached($cacheKey)) {
        $hotels = Cache::get($cacheKey);  // direct Cache::get to read without re-caching
        $formattedHotels = $this->formatHotels($hotels, $companyId);
        return [
            'success' => true,
            'error' => null,
            'cached' => true,  // serve cached even though circuit open
            'data' => ['hotels' => $formattedHotels, 'total_count' => count($formattedHotels)],
            'meta' => $this->buildMeta($companyId),
        ];
    }
    // No cache — return CIRCUIT_BREAKER_OPEN error
    return $this->errorResponse(
        'CIRCUIT_BREAKER_OPEN',
        'Try again in 30 seconds',
        'RETRY_IN_30_SECONDS'
    );
}

// After try/catch success:
$this->circuitBreaker->recordSuccess($companyId);

// In catch (\Exception $e) block — record failure and then return error:
$this->circuitBreaker->recordFailure($companyId);
return $this->errorResponse('API_ERROR', 'Hotel search failed. Please try again.', 'RETRY', $e->getMessage());
```

### Pattern 3: Timeout Exception

`Illuminate\Http\Client\ConnectionException` is thrown by Laravel HTTP client when the server does not respond within `->timeout($seconds)`. The existing `DotwService::post()` catches all `Exception` uniformly. The fix is to add a dedicated catch block first:

```php
// In DotwService::post():
use Illuminate\Http\Client\ConnectionException;

} catch (ConnectionException $e) {
    $this->logger->error('DOTW API timeout', [
        'timeout_seconds' => $this->timeout,
        'company_id' => $this->companyId,
    ]);
    throw new \App\Exceptions\DotwTimeoutException(
        "DOTW API timeout after {$this->timeout}s",
        0,
        $e
    );
} catch (Exception $e) {
    // existing catch
}
```

Then in each resolver:

```php
} catch (\App\Exceptions\DotwTimeoutException $e) {
    return $this->errorResponse('API_TIMEOUT', 'Search taking too long, please try again', 'RETRY', $e->getMessage());
} catch (RuntimeException $e) {
    return $this->errorResponse('CREDENTIALS_NOT_CONFIGURED', '...', 'RECONFIGURE_CREDENTIALS');
} catch (\Exception $e) {
    return $this->errorResponse('API_ERROR', '...', 'RETRY');
}
```

**Catch order matters:** `DotwTimeoutException` must come before `RuntimeException` which must come before `\Exception`.

### Pattern 4: DotwCacheService::get() Gap

`DotwCacheService` currently has `isCached()` and `remember()` but no raw `get()` method. The circuit breaker fallback needs to read cached data without re-setting the TTL. Two options:

- **Option A (recommended):** Add `public function get(string $key): ?array` to DotwCacheService — calls `Cache::get($key)` and returns null if not set.
- **Option B:** Use `Cache::get()` directly in DotwSearchHotels with the raw key — couples resolver to cache driver internals.

Option A is preferred — keeps DotwCacheService as the single cache abstraction layer.

### Pattern 5: DOTW_TIMEOUT Config Reduction

Current default: `DOTW_TIMEOUT=120` (2 minutes). Phase 7 requires > 25 seconds triggers timeout. The fix is to:

1. Set `DOTW_TIMEOUT=25` in `.env` (or document that the env var must be set to 25).
2. Update `config/dotw.php` default from 120 to 25.

`DotwService` already reads `config('dotw.request.timeout', 120)` — simply changing the default value is sufficient. The config comment should be updated to document the 25-second requirement.

---

## Key Decisions for Planner

1. **Circuit breaker scope:** Search ONLY (DotwSearchHotels). Rates and blocking are excluded — they are intentional user actions and have no cache fallback.

2. **DotwTimeoutException location:** `app/Exceptions/DotwTimeoutException.php` — extends `\RuntimeException` for consistent catch hierarchy. This makes it distinct from the credential `\RuntimeException` thrown in the constructor.

3. **Cache::add() vs Cache::increment() TTL:** `Cache::add($key, 0, 60)` only sets the key if it doesn't exist (atomic, safe for concurrent requests). `Cache::increment($key)` increments and returns the new value. This avoids a separate `Cache::has()` check before `Cache::increment()`.

4. **DotwCacheService::get() is a new method** — 4 lines, add to existing class. Cache key format is unchanged (buildKey() output passed in by caller).

5. **No new migration needed** — circuit breaker state lives in Laravel cache (Redis/file), not the database.

6. **ERROR-07 audit is a read-only verification task** — scan existing DotwService log calls to confirm no credentials appear. If any are found, remove them. The 'dotw' logging channel is already configured correctly.

7. **Config change to 25s timeout** — update `config/dotw.php` default and document the env var. Do NOT change the env var name — just the default value.

8. **DotwGetCities** resolver (Phase 4, Plan 02) also instantiates DotwService — verify it has a `RuntimeException` catch with `CREDENTIALS_NOT_CONFIGURED`.

9. **DotwCreatePreBooking** (Phase 6, Plan 02) also instantiates DotwService — verify it has a `RuntimeException` catch. Phase 6 is being planned in parallel (Wave 3) — Phase 7 plan should verify the pattern is in place without modifying Phase 6 code.

---

## Implementation Sequencing

| Plan | Deliverable | Depends on |
|------|-------------|------------|
| 07-01 | DotwTimeoutException + DotwService::post() patch + config timeout → 25s | Standalone |
| 07-02 | DotwCircuitBreakerService + DotwCacheService::get() + DotwSearchHotels integration | 07-01 (DotwTimeoutException must exist) |
| 07-03 | Resolver audit: verify ERROR-01 (RuntimeException catch) across all resolvers + ERROR-07 log audit | Independent (no code deps) |

Plans 07-01 and 07-03 can run in parallel (Wave 1). Plan 07-02 depends on 07-01 for the timeout exception class (Wave 2).

---

## Pitfalls & Edge Cases

1. **Cache driver atomicity:** `Cache::increment()` is atomic on Redis and Memcached but NOT on the file cache driver. If the dev environment uses `CACHE_DRIVER=file`, the failure counter could have a race condition. Document this — production must use Redis/Memcached. The logic is still correct; the race window is small enough for the 5-failure threshold.

2. **DotwCacheService::remember() closure timing:** The circuit breaker check (`isOpen()`) must happen BEFORE `$this->cache->remember()` is called in the resolver — otherwise the circuit could open inside the cache closure but the search call has already been made.

3. **Success path record:** `recordSuccess()` must be called after the `cache->remember()` call completes without throwing — NOT inside the cache closure. The closure could be skipped entirely on cache hit, so a success call inside the closure would not fire on cached requests.

4. **DotwTimeoutException catch order:** Must be caught before `\RuntimeException` in resolvers. If `DotwTimeoutException extends \RuntimeException`, it will be caught by the `RuntimeException` block first if not ordered correctly. Safe approach: extend `\Exception` directly (not `\RuntimeException`) to avoid ordering dependency.

5. **Open circuit TTL:** 30 seconds (per SUCCESS CRITERIA "Try again in 30 seconds" — SC-4 of Phase 7 roadmap). Do NOT use 60 seconds. The open circuit key is `dotw_circuit_open_{companyId}` with `Cache::put($key, true, 30)`.

6. **Log redaction check:** `DotwService::post()` logs `'body' => substr($response->body(), 0, 500)` on HTTP error. This is a raw XML response — confirm it does not echo back user credentials. DOTW XML responses never contain the request credentials — confirmed safe.

7. **`DotwService` logger does NOT log $username or $passwordMd5** — confirmed by reading source lines 155, 208, 275, etc. The only sensitive field in the logger calls is `company_id` (logged) and `endpoint` (logged) — both are acceptable.
