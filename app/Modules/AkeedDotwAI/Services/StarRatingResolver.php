<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Services;

use App\Modules\AkeedDotwAI\Models\DotwCatalog;

/**
 * Resolves a DOTW internal classification code to a 1-5 star integer.
 *
 * DOTW's <rating> element in searchhotels responses carries an internal
 * numeric code (e.g. 561) that maps to a human label in the
 * dotw_catalogs(type='classification') snapshot populated by Phase 34.
 *
 * The resolver loads the full classification catalog into a static in-process
 * map on first call and serves all subsequent lookups from memory (O(1)).
 * The map is process-scoped — perfectly fine for Artisan commands and
 * queue workers, which start fresh per invocation.
 *
 * @see \App\Modules\AkeedDotwAI\Services\CatalogSyncService  (catalog source)
 * @see \App\Modules\AkeedDotwAI\Jobs\EnrichHotelCatalogJob   (lazy backfill consumer)
 * @see \App\Modules\AkeedDotwAI\Console\Commands\BackfillStarRatingsCommand (eager backfill consumer)
 */
class StarRatingResolver
{
    /**
     * In-process cache of classification code → star integer.
     *
     * Null means the map has not been loaded yet.
     * Empty array means the catalog was queried but contained no parseable rows.
     *
     * @var array<string, int>|null
     */
    private static ?array $map = null;

    /**
     * Resolve a DOTW classification code to a 1-5 star integer.
     *
     * Returns null for:
     * - null or empty input
     * - zero (DOTW uses 0 as "no rating")
     * - unknown codes not present in the classification catalog
     * - catalog rows whose names do not parse to a 1-5 integer
     *
     * Never throws. Always safe to call in a queue job or Artisan command
     * without wrapping in a try/catch.
     */
    public function resolve(string|int|null $code): ?int
    {
        if ($code === null || $code === '' || $code === 0) {
            return null;
        }

        $key = (string) $code;
        $map = self::loadMap();

        return $map[$key] ?? null;
    }

    /**
     * Reset the static map — intended for use in tests only.
     *
     * Calling this in production forces a DB re-fetch on the next resolve()
     * call. Do not call from production code.
     *
     * @internal Do not call from production code.
     */
    public static function clearCache(): void
    {
        self::$map = null;
    }

    /**
     * Load (or return cached) the classification code → star map.
     *
     * @return array<string, int>
     */
    private static function loadMap(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $rows = DotwCatalog::ofType(DotwCatalog::TYPE_CLASSIFICATION)
            ->get(['code', 'name']);

        $map = [];
        foreach ($rows as $row) {
            $stars = self::parseStars((string) $row->name);
            if ($stars !== null) {
                $map[(string) $row->code] = $stars;
            }
        }

        return self::$map = $map;
    }

    /**
     * Parse a catalog row name to a 1-5 star integer.
     *
     * Three strategies (tried in order):
     * 1. Regex `/(\d)\s*[-–]?\s*star/i` — matches "5 Star", "4-Star Deluxe", "3 – Star" etc.
     * 2. Asterisk count — matches DOTW names like "Economy*", "Budget **", "Standard ***".
     *    Counts trailing `*` characters (after optional spaces); maps 1-5 to that int.
     * 3. Bare single digit — matches "3", " 4 " etc. (DOTW sometimes uses bare ints).
     *
     * Accepts only values in the range [1, 5]. Out-of-range → null.
     */
    private static function parseStars(string $name): ?int
    {
        if (preg_match('/(\d)\s*[-–]?\s*star/i', $name, $m) === 1) {
            $n = (int) $m[1];
        } elseif (preg_match('/\*+\s*$/', $name, $m) === 1) {
            // Count trailing asterisks — "Economy*"=1, "Budget **"=2, "Standard ***"=3 …
            $n = substr_count(rtrim($name), '*');
        } elseif (preg_match('/^\s*([1-5])\s*$/', $name, $m) === 1) {
            $n = (int) $m[1];
        } else {
            return null;
        }

        return ($n >= 1 && $n <= 5) ? $n : null;
    }
}
