<?php

namespace Tests\Unit\Concerns;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\GuardsTestDatabaseIsolation;
use Tests\TestCase;

/**
 * Exercises Tests\Concerns\GuardsTestDatabaseIsolation directly.
 *
 * The guard itself has no automated coverage prior to this test -it lived
 * only as an exit(1) inside setUp(), which is both untestable (PHPUnit
 * cannot observe a raw process exit as a normal failure) and, per the trait's
 * docblock, was the wrong tool: exit() skips shutdown handlers that a
 * thrown exception does not. This test targets the pure decision function
 * (evaluateDatabaseIsolation / isDisposableTestDatabaseName -no I/O, no
 * exit, no exception) with a table of accept/reject names, then separately
 * proves the abort path throws a RuntimeException that PHPUnit reports as a
 * normal test failure rather than exiting the process.
 */
class GuardsTestDatabaseIsolationTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function nameProvider(): array
    {
        return [
            // Accepted: exact prefix or prefix + "_" + suffix.
            'exact prefix' => ['city_tour_test', true],
            'prefix + agent suffix' => ['city_tour_test_agent7', true],
            'prefix + this workflow\'s db' => ['city_tour_test_b_env', true],
            'prefix + map suffix' => ['city_tour_test_map', true],
            'prefix + underscore only' => ['city_tour_test_', true],

            // Rejected: the real primary/local databases this guard exists to protect.
            'primary local app db' => ['laravel_testing', false],
            'map database' => ['map_data_citytour', false],
            'dev server db' => ['citycomm_city-tour-test', false],
            'production db' => ['citycomm_city-tour', false],

            // Rejected: shares the prefix as a literal string but is a
            // genuinely different database name -- this is exactly the
            // false-accept the old plain str_starts_with() check allowed.
            'prefix look-alike, no delimiter' => ['city_tour_testing_snapshot', false],
            'prefix look-alike, longer word' => ['city_tour_testers', false],

            // Rejected: garbage/empty/wrong-type input.
            'empty string' => ['', false],
            'whitespace only' => ['   ', false],
            'case mismatch' => ['CITY_TOUR_TEST', false],
        ];
    }

    #[DataProvider('nameProvider')]
    public function test_is_disposable_test_database_name_accepts_or_rejects_by_table(string $database, bool $expected): void
    {
        $this->assertSame(
            $expected,
            self::invokeIsDisposable($database),
            "isDisposableTestDatabaseName(\"{$database}\") expected ".($expected ? 'true' : 'false')
        );
    }

    #[DataProvider('nameProvider')]
    public function test_evaluate_database_isolation_flags_the_same_names_as_violations(string $database, bool $expected): void
    {
        $violations = self::invokeEvaluate(['the label' => $database]);

        if ($expected) {
            $this->assertSame([], $violations, 'expected no violations for an accepted name');
        } else {
            $this->assertSame(['the label' => $database], $violations, 'expected exactly one violation for a rejected name');
        }
    }

    public function test_evaluate_database_isolation_reports_only_the_offending_entries_from_a_mixed_set(): void
    {
        $violations = self::invokeEvaluate([
            'mysql_testing connection' => 'city_tour_test_b_env',
            'DB_TEST_DATABASE (env)' => 'laravel_testing',
            'mysql_map connection' => 'city_tour_test_b_env_map',
            'DB_DATABASE_MAP (env)' => 'map_data_citytour',
            'accounting_audit connection' => 'city_tour_test_b_env',
            'DB_AUDIT_DATABASE (env)' => 'laravel',
        ]);

        $this->assertSame([
            'DB_TEST_DATABASE (env)' => 'laravel_testing',
            'DB_DATABASE_MAP (env)' => 'map_data_citytour',
            'DB_AUDIT_DATABASE (env)' => 'laravel',
        ], $violations);
    }

    public function test_evaluate_database_isolation_rejects_non_string_input(): void
    {
        $violations = self::invokeEvaluate([
            'null value' => null,
            'array value' => ['city_tour_test'],
            'int value' => 0,
        ]);

        $this->assertSame(['null value', 'array value', 'int value'], array_keys($violations));
    }

    /**
     * Proves the abort path throws \RuntimeException (PHPUnit reports this
     * as a normal test failure, with shutdown handlers intact) rather than
     * calling exit(1) -- and that it does so as the FIRST thing in setUp(),
     * before parent::setUp() runs. If parent::setUp() (and therefore
     * RefreshDatabase's afterApplicationCreated -> migrate:fresh hook) had
     * been allowed to run first, a real config/database boot problem would
     * surface as some other exception type/message, not this guard's own
     * "FATAL TEST-SAFETY ABORT" RuntimeException.
     */
    public function test_guard_throws_runtime_exception_when_resolved_config_is_not_a_disposable_database(): void
    {
        $originalTesting = config('database.connections.mysql_testing.database');
        $originalMap = config('database.connections.mysql_map.database');

        Config::set('database.connections.mysql_testing.database', 'laravel_testing');
        Config::set('database.connections.mysql_map.database', 'map_data_citytour');

        try {
            $victim = new class('testNoop') extends TestCase
            {
                public function testNoop(): void
                {
                }
            };

            $setUp = new \ReflectionMethod($victim, 'setUp');
            $setUp->setAccessible(true);

            $thrown = null;

            try {
                $setUp->invoke($victim);
            } catch (\RuntimeException $e) {
                $thrown = $e;
            }

            $this->assertInstanceOf(
                \RuntimeException::class,
                $thrown,
                'guardTestDatabaseIsolation() should throw RuntimeException, not exit(), when the resolved config is unsafe'
            );
            $this->assertStringContainsString('FATAL TEST-SAFETY ABORT', $thrown->getMessage());
            $this->assertStringContainsString('laravel_testing', $thrown->getMessage());
            $this->assertStringContainsString('map_data_citytour', $thrown->getMessage());
        } finally {
            Config::set('database.connections.mysql_testing.database', $originalTesting);
            Config::set('database.connections.mysql_map.database', $originalMap);
        }
    }

    /**
     * accounting_audit (Accounting Gap/18): proves it is guarded
     * INDEPENDENTLY of mysql_testing/mysql_map -- both of those are left
     * untouched and disposable here, only accounting_audit is forced unsafe,
     * and the guard must still abort. Without accounting_audit in
     * resolveGuardedDatabases() this scenario would sail through -- exactly
     * the leak this round closes (see config/database.php's own comment on
     * the accounting_audit connection and IdempotencyKeyRejection's
     * docblock for why writes on this connection matter).
     */
    public function test_guard_throws_when_only_accounting_audit_resolves_unsafely(): void
    {
        $original = config('database.connections.accounting_audit.database');

        Config::set('database.connections.accounting_audit.database', 'laravel_testing');

        try {
            $this->assertTrue(self::invokeIsDisposable(config('database.connections.mysql_testing.database')));
            $this->assertTrue(self::invokeIsDisposable(config('database.connections.mysql_map.database')));

            $victim = new class('testNoop') extends TestCase
            {
                public function testNoop(): void
                {
                }
            };

            $setUp = new \ReflectionMethod($victim, 'setUp');
            $setUp->setAccessible(true);

            $thrown = null;

            try {
                $setUp->invoke($victim);
            } catch (\RuntimeException $e) {
                $thrown = $e;
            }

            $this->assertInstanceOf(
                \RuntimeException::class,
                $thrown,
                'guardTestDatabaseIsolation() should abort when only accounting_audit resolves unsafely, even though mysql_testing/mysql_map are fine'
            );
            $this->assertStringContainsString('FATAL TEST-SAFETY ABORT', $thrown->getMessage());
            $this->assertStringContainsString('accounting_audit', $thrown->getMessage());
            $this->assertStringContainsString('laravel_testing', $thrown->getMessage());
        } finally {
            Config::set('database.connections.accounting_audit.database', $original);
        }
    }

    public function test_guard_does_not_throw_when_resolved_config_is_already_a_disposable_database(): void
    {
        // This is the live state of the current process (see phpunit.xml /
        // the shell export this run used) -- all three connections must
        // already be disposable, or every other test in this run would have
        // aborted before reaching this one.
        $this->assertTrue(self::invokeIsDisposable(config('database.connections.mysql_testing.database')));
        $this->assertTrue(self::invokeIsDisposable(config('database.connections.mysql_map.database')));
        $this->assertTrue(self::invokeIsDisposable(config('database.connections.accounting_audit.database')));

        $victim = new class('testNoop') extends TestCase
        {
            public function testNoop(): void
            {
            }
        };

        $setUp = new \ReflectionMethod($victim, 'setUp');
        $setUp->setAccessible(true);

        // No exception -- and specifically, no RuntimeException from the guard.
        $setUp->invoke($victim);
        $this->addToAssertionCount(1);
    }

    /**
     * Proves deriveAuditDatabaseEnvironment() (Accounting Gap/18) actually
     * plants DB_AUDIT_DATABASE -- matching the resolved mysql_testing
     * database -- into all three places env() can read from, so that
     * config/database.php's `env('DB_AUDIT_DATABASE', env('DB_DATABASE',
     * 'laravel'))` for the accounting_audit connection lands on the SAME
     * per-agent database as mysql_testing the next time the application
     * boots, with no shell export required.
     */
    public function test_derive_audit_database_environment_plants_db_audit_database_from_resolved_test_database(): void
    {
        $expected = config('database.connections.mysql_testing.database');
        $this->assertIsString($expected, 'sanity check: the live test run must already resolve mysql_testing to a string');

        $originalServer = $_SERVER['DB_AUDIT_DATABASE'] ?? null;
        $originalEnvVar = $_ENV['DB_AUDIT_DATABASE'] ?? null;
        $originalGetenv = getenv('DB_AUDIT_DATABASE');

        try {
            unset($_SERVER['DB_AUDIT_DATABASE'], $_ENV['DB_AUDIT_DATABASE']);
            putenv('DB_AUDIT_DATABASE');

            $victim = new class
            {
                use GuardsTestDatabaseIsolation;

                public function call(): void
                {
                    $this->deriveAuditDatabaseEnvironment();
                }
            };

            $victim->call();

            $this->assertSame($expected, $_SERVER['DB_AUDIT_DATABASE'] ?? null);
            $this->assertSame($expected, $_ENV['DB_AUDIT_DATABASE'] ?? null);
            $this->assertSame($expected, getenv('DB_AUDIT_DATABASE'));
        } finally {
            if ($originalServer === null) {
                unset($_SERVER['DB_AUDIT_DATABASE']);
            } else {
                $_SERVER['DB_AUDIT_DATABASE'] = $originalServer;
            }

            if ($originalEnvVar === null) {
                unset($_ENV['DB_AUDIT_DATABASE']);
            } else {
                $_ENV['DB_AUDIT_DATABASE'] = $originalEnvVar;
            }

            putenv($originalGetenv === false ? 'DB_AUDIT_DATABASE' : "DB_AUDIT_DATABASE={$originalGetenv}");
        }
    }

    /**
     * Proves the fold: deriveAuditDatabaseEnvironment() is now called
     * INTERNALLY, as the first thing inside guardTestDatabaseIsolation()
     * itself, so a caller that follows the trait's docblock and calls ONLY
     * guardTestDatabaseIsolation() -- the documented contract for every
     * base class -- still gets a correctly derived DB_AUDIT_DATABASE, with
     * no separate call required.
     *
     * Revert the fold (move deriveAuditDatabaseEnvironment()'s call back out
     * of guardTestDatabaseIsolation() and this test goes red: nothing in
     * this test calls deriveAuditDatabaseEnvironment() directly, so
     * DB_AUDIT_DATABASE would be left unset by guardTestDatabaseIsolation()
     * alone -- exactly the silent leak a future base class following only
     * the old "call guardTestDatabaseIsolation() first thing in setUp()"
     * instruction would have reintroduced.
     */
    public function test_guard_test_database_isolation_alone_derives_db_audit_database(): void
    {
        $expected = config('database.connections.mysql_testing.database');
        $this->assertIsString($expected, 'sanity check: the live test run must already resolve mysql_testing to a string');

        $originalServer = $_SERVER['DB_AUDIT_DATABASE'] ?? null;
        $originalEnvVar = $_ENV['DB_AUDIT_DATABASE'] ?? null;
        $originalGetenv = getenv('DB_AUDIT_DATABASE');

        try {
            unset($_SERVER['DB_AUDIT_DATABASE'], $_ENV['DB_AUDIT_DATABASE']);
            putenv('DB_AUDIT_DATABASE');

            $victim = new class
            {
                use GuardsTestDatabaseIsolation;

                public function call(): void
                {
                    // The method under test here is guardTestDatabaseIsolation() ALONE --
                    // deriveAuditDatabaseEnvironment() is deliberately never called directly
                    // in this test, so the assertions below can only pass if
                    // guardTestDatabaseIsolation() calls it internally.
                    $this->guardTestDatabaseIsolation();
                }
            };

            // Must not throw: the live test run's connections are already disposable (per
            // test_guard_does_not_throw_when_resolved_config_is_already_a_disposable_database
            // above), so this call proves the derive step alone, not the abort path.
            $victim->call();

            $this->assertSame($expected, $_SERVER['DB_AUDIT_DATABASE'] ?? null);
            $this->assertSame($expected, $_ENV['DB_AUDIT_DATABASE'] ?? null);
            $this->assertSame($expected, getenv('DB_AUDIT_DATABASE'));
        } finally {
            if ($originalServer === null) {
                unset($_SERVER['DB_AUDIT_DATABASE']);
            } else {
                $_SERVER['DB_AUDIT_DATABASE'] = $originalServer;
            }

            if ($originalEnvVar === null) {
                unset($_ENV['DB_AUDIT_DATABASE']);
            } else {
                $_ENV['DB_AUDIT_DATABASE'] = $originalEnvVar;
            }

            putenv($originalGetenv === false ? 'DB_AUDIT_DATABASE' : "DB_AUDIT_DATABASE={$originalGetenv}");
        }
    }

    private static function invokeIsDisposable(mixed $database): bool
    {
        return (new class
        {
            use GuardsTestDatabaseIsolation;

            public function call(mixed $database): bool
            {
                return self::isDisposableTestDatabaseName($database);
            }
        })->call($database);
    }

    /**
     * @param  array<string, mixed>  $targets
     * @return array<string, mixed>
     */
    private static function invokeEvaluate(array $targets): array
    {
        return (new class
        {
            use GuardsTestDatabaseIsolation;

            /**
             * @param  array<string, mixed>  $targets
             * @return array<string, mixed>
             */
            public function call(array $targets): array
            {
                return self::evaluateDatabaseIsolation($targets);
            }
        })->call($targets);
    }
}
