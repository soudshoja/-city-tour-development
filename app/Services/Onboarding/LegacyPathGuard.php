<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use RuntimeException;

/**
 * legacy-ledger-pilot LP0.2 fence.
 *
 * Two independent safety rails every legacy:* command must pass through
 * BEFORE opening a single byte of a CSV or writing a single row:
 *
 *   1. assertPathUnderRoot() -- refuses any file path that does not resolve
 *      (via realpath()-equivalent normalisation, NOT a naive string prefix
 *      check) to somewhere under the configured `legacy_pilot.allowed_root`
 *      (default D:\akeedac). A naive `str_starts_with($path, $root)` is
 *      exactly the bug this class exists to avoid: "D:\akeedac\..\evil.csv"
 *      starts with "D:\akeedac" as a STRING while resolving to a sibling
 *      directory. Handles: `..` traversal, a symlink/junction whose target
 *      escapes the root, a UNC path (`\\host\share\...`), and a relative
 *      path (resolved against cwd, which may not even be under the root).
 *
 *   2. assertQuarantinedConnection() -- refuses to write through the
 *      `legacy_pilot` database connection unless its RESOLVED database name
 *      (after Laravel has already applied every env() fallback in
 *      config/database.php) starts with "legacy_pilot" or "city_tour_test"
 *      -- the only two families of database name this quarantined
 *      connection is ever allowed to point at (real staging vs. the test
 *      fence). A misconfigured .env pointing this connection at a real app
 *      database (akeed2_app, or worse a production-shaped name) is refused
 *      loudly instead of silently landing staged legacy rows there.
 */
final class LegacyPathGuard
{
    private const ALLOWED_DB_PREFIXES = ['legacy_pilot', 'city_tour_test'];

    /**
     * @throws RuntimeException when $path does not resolve under the
     *                          configured allowed root.
     */
    public static function assertPathUnderRoot(string $path, ?string $root = null): string
    {
        $root = $root ?? (string) config('legacy_pilot.allowed_root', 'D:\\akeedac');

        $normalisedRoot = self::normalise($root);

        if ($normalisedRoot === '') {
            throw new RuntimeException('legacy_pilot.allowed_root is not configured — refusing to load anything.');
        }

        // Reject UNC paths outright -- they never resolve to a local drive root
        // and are exactly the kind of path this guard exists to keep out.
        if (str_starts_with($path, '\\\\') || str_starts_with($path, '//')) {
            throw new RuntimeException("Refused: UNC path is not permitted: {$path}");
        }

        // realpath() requires the target to exist and, critically, resolves
        // `..` segments AND symlink/junction targets -- exactly the
        // normalisation a naive string-prefix check would miss. A relative
        // path is resolved against getcwd() by realpath() itself, which is
        // the correct behaviour here: a relative path is refused unless it
        // happens to genuinely resolve under the root.
        $resolved = realpath($path);

        if ($resolved === false) {
            // File doesn't exist (yet) -- still validate the DIRECTORY
            // component so "not found" and "refused" are distinguishable,
            // and so a not-yet-existing evil path is still refused rather
            // than allowed to pass this gate by virtue of not existing.
            $dir = realpath(dirname($path));

            if ($dir === false) {
                throw new RuntimeException("Refused: path does not exist and cannot be resolved safely: {$path}");
            }

            $resolved = rtrim($dir, '\\/').DIRECTORY_SEPARATOR.basename($path);
        }

        $normalisedResolved = self::normalise($resolved);

        if (! str_starts_with($normalisedResolved, $normalisedRoot.'/')
            && $normalisedResolved !== $normalisedRoot
        ) {
            throw new RuntimeException(
                "Refused: '{$path}' resolves to '{$resolved}', which is outside the allowed root '{$root}'."
            );
        }

        return $resolved;
    }

    /**
     * @throws RuntimeException when the legacy_pilot connection's resolved
     *                          database name is not fence-safe.
     */
    public static function assertQuarantinedConnection(string $connection = 'legacy_pilot'): void
    {
        $database = (string) config("database.connections.{$connection}.database", '');

        foreach (self::ALLOWED_DB_PREFIXES as $prefix) {
            if (str_starts_with($database, $prefix)) {
                return;
            }
        }

        throw new RuntimeException(
            "Refused: connection '{$connection}' resolves to database '{$database}', which is neither ".
            "a 'legacy_pilot*' nor a 'city_tour_test*' name. Refusing to write staged legacy data ".
            'into what looks like a real application database.'
        );
    }

    /**
     * Normalise a Windows/Unix path for comparison: backslashes to forward
     * slashes, lowercase drive letter, no trailing slash.
     */
    private static function normalise(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = rtrim($path, '/');

        // Lowercase a leading drive letter ("D:" -> "d:") so "D:\akeedac" and
        // "d:\akeedac" compare equal -- Windows paths are case-insensitive.
        if (preg_match('/^([A-Za-z]):/', $path, $m)) {
            $path = strtolower($m[1]).':'.substr($path, 2);
        }

        return $path;
    }
}
