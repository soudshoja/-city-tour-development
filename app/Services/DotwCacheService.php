<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * DOTW Search Result Cache Service
 *
 * Encapsulates hotel search caching with deterministic per-company keys.
 * Cache key format: {prefix}_{company_id}_{destination}_{checkin}_{checkout}_{rooms_hash}
 *
 * Rooms hash is md5 of sorted, normalized room configuration JSON — ensures
 * identical rooms in different order produce the same key.
 *
 * TTL: 150 seconds (2.5 minutes) — configurable via DOTW_CACHE_TTL env var.
 * Isolation: company_id in key ensures Company A cache never serves Company B.
 */
class DotwCacheService
{
    private int $ttl;

    private string $prefix;

    /**
     * Initialise the service from dotw config.
     */
    public function __construct()
    {
        $this->ttl = (int) config('dotw.cache.ttl', 150);
        $this->prefix = config('dotw.cache.prefix', 'dotw_search');
    }

    /**
     * Build a deterministic cache key for a hotel search.
     *
     * The key embeds company_id so results from one company are never
     * returned to another.  Room configuration is normalised (sorted) so
     * that identical room configs supplied in a different order produce the
     * same key.
     *
     * Format: {prefix}_{companyId}_{destination}_{checkin}_{checkout}_{roomsHash}
     *
     * @param  int  $companyId  The tenant company identifier.
     * @param  string  $destination  City/destination code (case-insensitive).
     * @param  string  $checkin  Check-in date in YYYY-MM-DD format.
     * @param  string  $checkout  Check-out date in YYYY-MM-DD format.
     * @param  array  $rooms  Array of room configurations.
     * @return string The fully formed cache key.
     */
    public function buildKey(
        int $companyId,
        string $destination,
        string $checkin,
        string $checkout,
        array $rooms
    ): string {
        $normalised = $this->normalizeRooms($rooms);
        $roomsHash = md5((string) json_encode($normalised));

        return implode('_', [
            $this->prefix,
            $companyId,
            strtolower(trim($destination)),
            $checkin,
            $checkout,
            $roomsHash,
        ]);
    }

    /**
     * Retrieve a cached value or execute the callback and cache the result.
     *
     * Uses Cache::remember() with a DateInterval TTL derived from the
     * configured DOTW_CACHE_TTL value.  Callers should call isCached()
     * before invoking this method when they need to distinguish hits from
     * misses.
     *
     * @param  string  $key  The cache key (from buildKey()).
     * @param  callable  $callback  Callable that returns the search result array.
     * @return array The search result (from cache or fresh from callback).
     */
    public function remember(string $key, callable $callback): array
    {
        $ttl = new \DateInterval('PT'.$this->ttl.'S');

        /** @var array $result */
        $result = Cache::remember($key, $ttl, $callback);

        return $result;
    }

    /**
     * Determine whether a result is currently stored in the cache.
     *
     * Useful for callers that need to annotate a response with
     * `cached: true` when the value came from the cache store.
     *
     * @param  string  $key  The cache key to check.
     * @return bool True if the key exists in cache, false otherwise.
     */
    public function isCached(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * Remove a specific entry from the cache.
     *
     * Use this to invalidate stale search results, e.g. after a booking
     * is confirmed for the same hotel/dates.
     *
     * @param  string  $key  The cache key to remove.
     * @return bool True if the key was present and removed, false otherwise.
     */
    public function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    /**
     * Normalise a rooms array to produce a stable, order-independent JSON.
     *
     * Steps per room:
     *  1. ksort() — canonical key order regardless of how caller built the array.
     *  2. Sort children ages ascending — [8,5] and [5,8] become [5,8].
     *
     * The resulting rooms array is then sorted by adultsCode ascending so that
     * multi-room configs supplied in different orders produce the same hash.
     *
     * @param  array  $rooms  Raw room configuration array.
     * @return array Normalised rooms array suitable for JSON encoding.
     */
    private function normalizeRooms(array $rooms): array
    {
        $normalised = [];

        foreach ($rooms as $room) {
            // Ensure keys are in a consistent, canonical order.
            ksort($room);

            // Sort children ages ascending so [8,5] === [5,8].
            if (isset($room['children']) && is_array($room['children'])) {
                sort($room['children']);
            }

            $normalised[] = $room;
        }

        // Sort rooms by adultsCode ascending so multi-room order is irrelevant.
        usort($normalised, static function (array $a, array $b): int {
            $adultA = $a['adultsCode'] ?? 0;
            $adultB = $b['adultsCode'] ?? 0;

            return $adultA <=> $adultB;
        });

        return $normalised;
    }
}
