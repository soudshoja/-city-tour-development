<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * legacy-ledger-pilot LP4b -- the indexes the staged `stg_*` landing tables
 * need before the replay or the parity drill-down can run on them.
 *
 * WHY THIS EXISTS AS CODE AND NOT AS A RUNBOOK STEP. `LegacyCsvLoader`
 * creates every staging table from the CSV header alone: an auto-increment
 * `stg_row_id` and one nullable TEXT column per header field, with no index
 * on anything (deliberately -- the loader types nothing and knows nothing
 * about which column is a key). That is fine for 1,351 accounts and fatal
 * for 179,421 detail lines:
 *
 *   - the replay fetches lines ONE DOCUMENT AT A TIME
 *     (`stg_acc_detail WHERE docid_fk = ?`, 30,584 times) and the header the
 *     same way (`stg_acc_header WHERE docid = ?`);
 *   - `legacy:parity-diff --account=` reads every line on one legacy account
 *     (`stg_acc_detail WHERE accid_fk = ?`).
 *
 * Unindexed, each of those is a full scan plus a filesort. On staging's
 * MariaDB 10.11 the replay's repeated filesort over the detail table did not
 * merely run slowly -- it SEGFAULTED the server, and the staging agent got
 * run #6 out only by adding the two indexes by hand. Hand-added indexes are
 * not schema: the next bring-up would hit the same crash with nothing in the
 * repository to explain it.
 *
 * PREFIX INDEXES, BECAUSE EVERY STAGING COLUMN IS TEXT. A TEXT column cannot
 * be indexed whole in MariaDB, and these columns hold numeric ids written as
 * text, so 32 characters is far past any real value and keeps the key small
 * (the hand-added run #6 indexes used the 768-char maximum, which is ~24x
 * the key length for no extra selectivity).
 *
 * Idempotent, and never a second copy: if ANY index already LEADS with the
 * column -- including the hand-made `idx_run6_*` pair still sitting on
 * staging -- that column is left alone.
 */
final class LegacyStagingIndexes
{
    /**
     * The characters of each TEXT column that go into the key. These columns
     * carry numeric ids as text; 32 is comfortably past the longest.
     */
    public const PREFIX_LENGTH = 32;

    /**
     * Staging table => the columns a hot query filters or joins on.
     *
     * @var array<string, list<string>>
     */
    public const INDEXES = [
        // The replay's per-document line fetch (LegacyDocumentMapper), and
        // the drill-down's per-account line list (AccountDrillDown).
        'stg_acc_detail' => ['docid_fk', 'accid_fk'],
        // The replay's per-document header fetch (LegacyReplayRunner) and
        // the drill-down's Posted join.
        'stg_acc_header' => ['docid'],
    ];

    /**
     * Creates every missing index on the `legacy_pilot` connection.
     *
     * A staging table that has not been loaded yet is skipped, not created:
     * `legacy:load` owns the table's shape, this owns only its keys. So this
     * is safe to call before, during or after a load, and running it twice
     * does nothing the second time.
     *
     * @return list<string> the indexes actually created, as "table.column"
     */
    public static function ensure(): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $connection = DB::connection('legacy_pilot');
        $schema = Schema::connection('legacy_pilot');
        $database = (string) $connection->getDatabaseName();
        $created = [];

        foreach (self::INDEXES as $table => $columns) {
            if (! $schema->hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! $schema->hasColumn($table, $column)) {
                    continue;
                }

                if (self::hasLeadingIndex($database, $table, $column)) {
                    continue;
                }

                $connection->statement(sprintf(
                    'CREATE INDEX `%s` ON `%s` (`%s`(%d))',
                    self::indexName($table, $column),
                    $table,
                    $column,
                    self::PREFIX_LENGTH,
                ));

                $created[] = $table.'.'.$column;
            }
        }

        return $created;
    }

    /**
     * Drops only the indexes THIS class created -- never the hand-made
     * `idx_run6_*` pair, and never anything it did not name.
     *
     * @return list<string>
     */
    public static function drop(): array
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $connection = DB::connection('legacy_pilot');
        $schema = Schema::connection('legacy_pilot');
        $database = (string) $connection->getDatabaseName();
        $dropped = [];

        foreach (self::INDEXES as $table => $columns) {
            if (! $schema->hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                $name = self::indexName($table, $column);

                if (! self::indexExists($database, $table, $name)) {
                    continue;
                }

                $connection->statement(sprintf('DROP INDEX `%s` ON `%s`', $name, $table));
                $dropped[] = $table.'.'.$column;
            }
        }

        return $dropped;
    }

    public static function indexName(string $table, string $column): string
    {
        return $table.'_'.$column.'_idx';
    }

    /**
     * True when some index -- ours, or one added by hand -- already has this
     * column in FIRST position, which is what makes an equality lookup on it
     * a `ref` rather than a full scan.
     */
    private static function hasLeadingIndex(string $database, string $table, string $column): bool
    {
        return DB::connection('legacy_pilot')->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->where('seq_in_index', 1)
            ->exists();
    }

    private static function indexExists(string $database, string $table, string $name): bool
    {
        return DB::connection('legacy_pilot')->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }
}
