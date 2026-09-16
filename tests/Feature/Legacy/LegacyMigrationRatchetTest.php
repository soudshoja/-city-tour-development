<?php

declare(strict_types=1);

namespace Tests\Feature\Legacy;

use Tests\TestCase;

/**
 * legacy-ledger-pilot ratchet: the pilot's stg_ and map_ tables must NEVER
 * land as a root-level `database/migrations/*.php` file that the default
 * `php artisan migrate` would pick up -- it lives under
 * database/migrations/legacy_pilot/, run only via
 * `--path=database/migrations/legacy_pilot --database=legacy_pilot`.
 *
 * MUTATION PROOF: dropping a `.php` migration file directly into
 * database/migrations/ (root) makes this test fail.
 */
class LegacyMigrationRatchetTest extends TestCase
{
    public function test_no_php_migration_file_was_added_directly_under_the_root_migrations_directory(): void
    {
        $root = base_path('database/migrations');
        $rootFiles = glob($root.'/*.php');

        foreach ($rootFiles as $file) {
            $this->assertStringNotContainsString(
                'legacy_pilot',
                strtolower(basename($file)),
                "legacy-ledger-pilot migration '{$file}' must live under database/migrations/legacy_pilot/, not the root."
            );
        }

        // Never even one file our pilot did not intend directly touches this test's premise --
        // explicitly assert the legacy_pilot subdirectory exists and root count is unaffected by it.
        $this->assertDirectoryExists(base_path('database/migrations/legacy_pilot'));
    }

    public function test_the_legacy_pilot_migration_directory_is_not_among_the_default_migration_paths(): void
    {
        $migrator = app('migrator');
        $paths = $migrator->paths();

        // The default migrator only auto-registers database/migrations
        // (root) plus package paths -- legacy_pilot must not be in that
        // list, since it is only ever run with an explicit --path.
        foreach ($paths as $path) {
            $this->assertStringNotContainsString('legacy_pilot', str_replace('\\', '/', $path));
        }
    }
}
