<?php

namespace Tests\Concerns;

/**
 * Hard safety rail: refuse to let any test run migrations against a
 * database that isn't unambiguously a disposable test database.
 *
 * Shared by tests/TestCase.php (Feature/Unit tests, via RefreshDatabase) and
 * tests/DuskTestCase.php (browser tests, which can also carry
 * DatabaseMigrations/RefreshDatabase) -- both call ONLY
 * guardTestDatabaseIsolation() as the very first thing in setUp(), before
 * parent::setUp(), because RefreshDatabase/DatabaseMigrations run
 * `migrate:fresh` from inside parent::setUp()
 * (Illuminate\Foundation\Testing\TestCase::setUpTheTestEnvironment()
 * -> setUpTraits() -> the trait's afterApplicationCreated callback). A guard
 * that ran any later would be too late. guardTestDatabaseIsolation() itself
 * calls deriveAuditDatabaseEnvironment() first, internally -- a base class
 * needs no separate call to get that derivation; see
 * deriveAuditDatabaseEnvironment()'s own docblock for why that call is
 * folded in rather than left as a second thing every base class must
 * remember to call in the right order.
 *
 * WHY THIS CHECKS RESOLVED CONFIG, NOT env() DIRECTLY: config('database.connections.*')
 * is what Laravel's DB layer actually uses to open a connection. env() reads
 * the raw process environment every time it's called *unless* the framework
 * has cached config (php artisan config:cache), in which case env() calls
 * inside config files never ran again and env() itself can report a value
 * that no longer matches what config() resolved to at cache time. Checking
 * the resolved config values is therefore the authoritative check; env() is
 * additionally checked belt-and-braces, but resolved config is what decides.
 *
 * WHY THE PREFIX CHECK IS DELIMITED: a plain str_starts_with($db, 'city_tour_test')
 * would also accept "city_tour_testing_snapshot" -- a real, differently-named
 * database that merely happens to share the same leading characters. Only
 * the exact name "city_tour_test" or a "city_tour_test_<suffix>" name (prefix
 * followed by an underscore) counts as disposable.
 */
trait GuardsTestDatabaseIsolation
{
    private const REQUIRED_TEST_DB_PREFIX = 'city_tour_test';

    /**
     * Abort path. Throws (rather than exit()s) so PHPUnit reports a normal
     * test failure/error -- including running shutdown handlers and any
     * registered PHPUnit output -- instead of the process disappearing via a
     * raw exit(1), which historically made this guard itself untestable and
     * skipped shutdown handling. Called before parent::setUp(), so a thrown
     * exception here prevents parent::setUp() (and therefore
     * RefreshDatabase/DatabaseMigrations' migrate:fresh) from ever running.
     *
     * Calls deriveAuditDatabaseEnvironment() first, as the very first thing
     * inside this method -- NOT left for the caller to invoke separately.
     * This is the ONE call every base class needs to make; folding the
     * derive step in here means a future base class that follows this
     * docblock and calls only guardTestDatabaseIsolation() gets the
     * accounting_audit derivation for free, instead of silently
     * reintroducing the leak deriveAuditDatabaseEnvironment() exists to
     * close by forgetting to call it (or calling it in the wrong order).
     *
     * @throws \RuntimeException when any guarded database does not resolve
     *                            to a disposable test database name.
     */
    protected function guardTestDatabaseIsolation(): void
    {
        $this->deriveAuditDatabaseEnvironment();

        $violations = self::evaluateDatabaseIsolation(self::resolveGuardedDatabases());

        if ($violations !== []) {
            throw new \RuntimeException(self::formatIsolationViolationMessage($violations));
        }
    }

    /**
     * Plants DB_AUDIT_DATABASE into $_SERVER/$_ENV/putenv(), derived from the
     * already-resolved DB_TEST_DATABASE, BEFORE parent::setUp() creates the
     * application (see class docblock on WHY this must run that early).
     *
     * Called automatically, as the first thing inside guardTestDatabaseIsolation()
     * below -- NOT a second call a base class's setUp() needs to make on its
     * own. It used to be exactly that: tests/TestCase.php and
     * tests/DuskTestCase.php each called this method and then
     * guardTestDatabaseIsolation() separately, in that order, and only this
     * docblock (not the type system) enforced the ordering. A future base
     * class copying the OLD "call guardTestDatabaseIsolation() first thing in
     * setUp()" instruction with no mention of this method would have called
     * only the guard, silently reintroducing the accounting_audit leak this
     * method exists to close, with a suite that still went green (every
     * other guarded connection was still fine). Folding the call in here
     * instead makes "call guardTestDatabaseIsolation() alone" the whole
     * contract -- there is no second step left to forget.
     *
     * WHY THIS CLOSES THE LEAK (Accounting Gap/18 audit-isolation finding):
     * config/database.php's `accounting_audit` connection reads
     * `env('DB_AUDIT_DATABASE', env('DB_DATABASE', 'laravel'))`. Nothing in
     * phpunit.xml or the per-agent shell export (DB_TEST_DATABASE /
     * DB_DATABASE_MAP) ever sets DB_AUDIT_DATABASE, so without this method
     * that connection always fell through to DB_DATABASE -- which
     * phpunit.xml force="true"s to the single SHARED "city_tour_test" for
     * the unrelated `mysql`/default connection, regardless of which
     * per-agent DB_TEST_DATABASE is in effect. Every agent's
     * IdempotencyKeyRejection writes (see that model's docblock -- it uses
     * this connection specifically to survive a transaction rollback) were
     * landing in that one shared database instead of the agent's own.
     *
     * Laravel's env() helper (Illuminate\Config\Repository, via
     * vlucas/phpdotenv's Env) reads $_SERVER first, ahead of $_ENV/getenv(),
     * so a value planted here wins the exact same way a shell-exported
     * DB_TEST_DATABASE already wins over phpunit.xml's <env> declarations
     * (see phpunit.xml's own top-of-file comment) -- no fourth, ad-hoc
     * resolution path, just the same mechanism reused one env key over.
     *
     * Deliberately reuses resolveConnectionDatabase()'s exact tiering
     * (live config if bound, else the config cache file, else env()) to
     * find "the test database" instead of inventing a new way to ask the
     * same question -- at the point this runs, no application/container
     * exists yet, so in practice this always lands on tier 2 or 3, same as
     * guardTestDatabaseIsolation() itself does at this same point in the
     * lifecycle. Runs as the first statement inside guardTestDatabaseIsolation()
     * (below), strictly before that method computes resolveGuardedDatabases(),
     * so the guard's own accounting_audit check observes the value this
     * method just planted rather than whatever DB_AUDIT_DATABASE held on
     * entry.
     *
     * A non-string/empty resolution is left untouched here -- there is
     * nothing safe to derive from it, and guardTestDatabaseIsolation(),
     * called immediately after, will independently report the underlying
     * mysql_testing/DB_TEST_DATABASE violation and abort the run.
     */
    protected function deriveAuditDatabaseEnvironment(): void
    {
        $testDatabase = self::resolveConnectionDatabase(
            'database.connections.mysql_testing.database', 'DB_TEST_DATABASE', 'city_tour_test'
        );

        if (!is_string($testDatabase) || $testDatabase === '') {
            return;
        }

        $_SERVER['DB_AUDIT_DATABASE'] = $testDatabase;
        $_ENV['DB_AUDIT_DATABASE'] = $testDatabase;
        putenv("DB_AUDIT_DATABASE={$testDatabase}");
    }

    /**
     * Gathers the values to be checked: the RESOLVED connection config for
     * all three guarded connections (authoritative, see class docblock) plus
     * the raw env() values for the same three variables (belt and braces --
     * catches the case where config() has somehow not picked up the same
     * value, e.g. a test that mutates config() directly without also
     * touching env()).
     *
     * accounting_audit is guarded here too (Accounting Gap/18): a
     * mis-resolved audit database -- e.g. deriveAuditDatabaseEnvironment()
     * never having run, or something overriding DB_AUDIT_DATABASE back to a
     * non-disposable name -- must abort the run exactly like a mis-resolved
     * mysql_testing/mysql_map does, not silently leak writes into whatever
     * DB_DATABASE happens to be.
     *
     * @return array<string, mixed> label => database name (or whatever
     *                               non-string garbage config/env produced)
     */
    private static function resolveGuardedDatabases(): array
    {
        return [
            'mysql_testing connection (resolved)' => self::resolveConnectionDatabase(
                'database.connections.mysql_testing.database', 'DB_TEST_DATABASE', 'city_tour_test'
            ),
            'DB_TEST_DATABASE (env, belt-and-braces)' => env('DB_TEST_DATABASE', 'city_tour_test'),
            'mysql_map connection (resolved)' => self::resolveConnectionDatabase(
                'database.connections.mysql_map.database', 'DB_DATABASE_MAP', 'city_tour_test_map'
            ),
            'DB_DATABASE_MAP (env, belt-and-braces)' => env('DB_DATABASE_MAP', 'city_tour_test_map'),
            'accounting_audit connection (resolved)' => self::resolveConnectionDatabase(
                'database.connections.accounting_audit.database', 'DB_AUDIT_DATABASE', (string) env('DB_DATABASE', 'laravel')
            ),
            'DB_AUDIT_DATABASE (env, belt-and-braces)' => env('DB_AUDIT_DATABASE', (string) env('DB_DATABASE', 'laravel')),
        ];
    }

    /**
     * Resolves one connection's database name the same way Laravel itself
     * would, without requiring (and without triggering) a full application
     * boot -- this guard runs in setUp() BEFORE parent::setUp() creates the
     * application for this test, so app()/config() can be entirely unbound
     * at the moment this runs (calling config() directly then throws
     * BindingResolutionException: "Target class [config] does not exist").
     *
     * Preference order, each one strictly more authoritative than the next
     * for what the app would actually use at this instant:
     *   1. The live config repository, if a container with one already
     *      exists (e.g. a prior test in this process left one bound, or a
     *      test deliberately did Config::set() to exercise this guard) --
     *      this reflects any runtime override, not just what's on disk.
     *   2. The compiled config cache file (bootstrap/cache/config.php), read
     *      directly, if `php artisan config:cache` has been run -- this is
     *      exactly what LoadConfiguration would hand the container once it
     *      does boot, and is the scenario env() alone gets wrong (env()
     *      keeps reading the live process environment while the cached
     *      config array is frozen from cache time, so the two can diverge).
     *   3. env(), which is what config/database.php's own env() call would
     *      compute live if there were no cache -- i.e. exactly what the
     *      container would resolve to once booted, in the common
     *      uncached-local-dev case.
     */
    private static function resolveConnectionDatabase(string $configKey, string $envKey, string $envDefault): mixed
    {
        if (\Illuminate\Container\Container::getInstance()->bound('config')) {
            return config($configKey);
        }

        $cached = self::readCachedConfig();

        if ($cached !== null) {
            return data_get($cached, $configKey);
        }

        return env($envKey, $envDefault);
    }

    /**
     * Reads bootstrap/cache/config.php directly (a plain PHP array literal
     * Laravel's own config:cache writes and LoadConfiguration requires) with
     * no framework boot involved -- just a file path computed relative to
     * this file, since app()/base_path() are themselves unavailable at the
     * point this guard needs to run. Memoized per-process since the file
     * cannot change mid-run.
     *
     * @return array<string, mixed>|null null if no config cache exists
     */
    private static function readCachedConfig(): ?array
    {
        static $cache = false;

        if ($cache !== false) {
            return $cache;
        }

        $cacheFile = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'bootstrap'.
            DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php';

        $cache = is_file($cacheFile) ? require $cacheFile : null;

        return $cache;
    }

    /**
     * Pure decision function -- no I/O, no exit, no exception. Given a
     * label => database-name map, returns the subset that fail the
     * "clearly a disposable test database" check. An empty return value
     * means every entry passed.
     *
     * Exposed as public static specifically so a unit test can exercise the
     * guard's actual decision logic against a table of names without needing
     * to boot the framework, mutate env(), or trigger the abort path.
     *
     * @param  array<string, mixed>  $targets  label => database name
     * @return array<string, mixed> label => offending database name, for
     *                               every entry that failed the check
     */
    public static function evaluateDatabaseIsolation(array $targets): array
    {
        $violations = [];

        foreach ($targets as $label => $database) {
            if (!self::isDisposableTestDatabaseName($database)) {
                $violations[$label] = $database;
            }
        }

        return $violations;
    }

    /**
     * True only for the exact name "city_tour_test" or a name of the form
     * "city_tour_test_<suffix>" (prefix immediately followed by an
     * underscore). Deliberately rejects any other string that merely starts
     * with the same characters (e.g. "city_tour_testing_snapshot"), and
     * rejects non-string/empty input outright.
     */
    public static function isDisposableTestDatabaseName(mixed $database): bool
    {
        if (!is_string($database) || $database === '') {
            return false;
        }

        return $database === self::REQUIRED_TEST_DB_PREFIX
            || str_starts_with($database, self::REQUIRED_TEST_DB_PREFIX.'_');
    }

    /**
     * @param  array<string, mixed>  $violations  label => offending database name
     */
    private static function formatIsolationViolationMessage(array $violations): string
    {
        $lines = [];

        foreach ($violations as $label => $database) {
            $printable = is_string($database) ? $database : var_export($database, true);
            $lines[] = "{$label} resolves to database \"{$printable}\".";
        }

        return str_repeat('=', 78)."\n".
            "FATAL TEST-SAFETY ABORT\n".
            implode("\n", $lines)."\n".
            'None of these unambiguously start with "'.self::REQUIRED_TEST_DB_PREFIX.'" or "'.
            self::REQUIRED_TEST_DB_PREFIX."_<suffix>\", so at least one is not clearly a\n".
            "disposable test database. Refusing to let RefreshDatabase/DatabaseMigrations\n".
            "run migrate:fresh -- this guards laravel_testing and map_data_citytour from\n".
            "ever being wiped by a misconfigured test run (this has happened before; see\n".
            "Accounting Gap/17-p1-postingservice-complete.md section 6).\n".
            "See phpunit.xml and scripts/dev/create-test-db.php.\n".
            str_repeat('=', 78)."\n";
    }
}
