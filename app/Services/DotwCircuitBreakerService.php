<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * DOTW Circuit Breaker Service
 *
 * Prevents DOTW API hammering during outages using Laravel Cache as the state store.
 * Uses a two-key approach:
 *   - dotw_circuit_failures_{companyId}: rolling 60s failure counter per company
 *   - dotw_circuit_open_{companyId}: open circuit flag, expires after 30 seconds
 *
 * Threshold: 5 failures in 60 seconds opens the circuit.
 * Recovery: circuit auto-closes after 30 seconds (key expiry). recordSuccess() closes immediately.
 *
 * NOTE: Cache::increment() is NOT atomic on the file cache driver. Production must use
 * Redis or Memcached (CACHE_STORE=redis). The logic is still correct; only the race window
 * for the 5th failure check differs on file cache.
 *
 * Applies to: DotwSearchHotels only — getRoomRates and blockRates are excluded.
 */
class DotwCircuitBreakerService
{
    private const FAILURE_THRESHOLD = 5;

    private const WINDOW_SECONDS = 60;

    private const OPEN_TTL_SECONDS = 30;

    /**
     * Check whether the circuit is currently open (failures exceeded threshold).
     *
     * @param  int  $companyId  The company whose circuit state to check.
     */
    public function isOpen(int $companyId): bool
    {
        return Cache::has("dotw_circuit_open_{$companyId}");
    }

    /**
     * Record an API failure for the given company.
     *
     * Uses Cache::add() to start the 60-second window on the first failure,
     * then increments atomically. Opens the circuit if threshold is reached.
     *
     * @param  int  $companyId  The company whose failure counter to increment.
     */
    public function recordFailure(int $companyId): void
    {
        $failureKey = "dotw_circuit_failures_{$companyId}";
        // add() only sets if key does not exist — starts the 60s window on first failure
        Cache::add($failureKey, 0, self::WINDOW_SECONDS);
        $count = (int) Cache::increment($failureKey);

        if ($count >= self::FAILURE_THRESHOLD) {
            Cache::put("dotw_circuit_open_{$companyId}", true, self::OPEN_TTL_SECONDS);
            Log::channel('dotw')->warning('DOTW circuit breaker opened', [
                'company_id' => $companyId,
                'failure_count' => $count,
                'open_for_seconds' => self::OPEN_TTL_SECONDS,
            ]);
        }
    }

    /**
     * Record a successful API call — resets failure counter and closes the circuit.
     *
     * @param  int  $companyId  The company whose circuit state to reset.
     */
    public function recordSuccess(int $companyId): void
    {
        Cache::forget("dotw_circuit_failures_{$companyId}");
        Cache::forget("dotw_circuit_open_{$companyId}");
    }
}
