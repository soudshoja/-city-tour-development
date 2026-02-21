---
plan: 07-02
phase: 07-error-hardening-and-circuit-breaker
status: complete
completed: 2026-02-21
requirements: [ERROR-08]
---

# Plan 07-02 Summary: Circuit Breaker Service

## What Was Built

Implemented `DotwCircuitBreakerService` and integrated it into `DotwSearchHotels` so that repeated DOTW API failures trigger a 30-second circuit open window with graceful degradation to cached results or a friendly retry error.

## Key Files

### Created
- `app/Services/DotwCircuitBreakerService.php` — Circuit breaker with `isOpen()`, `recordFailure()`, `recordSuccess()` using two Laravel Cache keys per company: `dotw_circuit_failures_{id}` (rolling 60s counter) and `dotw_circuit_open_{id}` (30s open flag).

### Modified
- `app/Services/DotwCacheService.php` — Added `get(string $key): ?array` method for circuit-open cache fallback reads (reads without affecting TTL).
- `app/GraphQL/Queries/DotwSearchHotels.php` — Injected `DotwCircuitBreakerService` via constructor. Added circuit breaker guard before `cache->remember()`, `recordFailure()` in timeout and generic exception catches, `recordSuccess()` after successful search.

## Circuit Breaker Behavior

| Condition | Behavior |
|---|---|
| Circuit closed | Normal flow — DOTW API called, cache->remember() used |
| Circuit open + cache hit | Return cached hotels with `cached: true` (no API call) |
| Circuit open + no cache | Return `CIRCUIT_BREAKER_OPEN` + `RETRY_IN_30_SECONDS` |
| API success | `recordSuccess()` resets failure counter + closes circuit |
| Timeout (`DotwTimeoutException`) | `recordFailure()` increments counter toward threshold |
| Generic exception | `recordFailure()` increments counter toward threshold |
| `RuntimeException` (credential error) | NOT counted — misconfig is not a transient API failure |

## Thresholds
- FAILURE_THRESHOLD: 5 failures
- WINDOW_SECONDS: 60 seconds (rolling counter TTL)
- OPEN_TTL_SECONDS: 30 seconds (circuit stays open for 30s, then auto-resets)

## Verification Results
- `php -l` passes on all 3 files
- `FAILURE_THRESHOLD = 5` confirmed in DotwCircuitBreakerService
- `WINDOW_SECONDS = 60` confirmed
- `OPEN_TTL_SECONDS = 30` confirmed
- `circuitBreaker->isOpen()` at line 94 — before try block
- `circuitBreaker->recordFailure()` at lines 149 (timeout) and 168 (generic exception)
- `circuitBreaker->recordSuccess()` at line 179 — after successful remember()
- `CIRCUIT_BREAKER_OPEN` and `RETRY_IN_30_SECONDS` error codes present
- `cache->get($cacheKey)` fallback read present
- Lighthouse schema resolves without unexpected errors

## Notes
- Circuit breaker applies ONLY to `DotwSearchHotels` — `DotwGetRoomRates`, `DotwBlockRates`, `DotwGetCities` are unaffected per ERROR-08 spec
- File cache driver warning documented in class PHPDoc — production must use Redis or Memcached for atomic `Cache::increment()`
- `DotwCacheService::get()` added rather than using `Cache::get()` directly — maintains service layer encapsulation and is testable
