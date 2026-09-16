<?php

use App\Services\Onboarding\LegacyStagingIndexes;
use Illuminate\Database\Migrations\Migration;

/**
 * legacy-ledger-pilot LP4b — the staging indexes, put in the repository.
 *
 * `LegacyCsvLoader` builds every `stg_*` table from the CSV header alone:
 * an auto-increment id and one nullable TEXT column per field, no keys. On
 * 179,421 detail lines the replay's per-document line fetch
 * (`stg_acc_detail WHERE docid_fk = ?`, once per each of 30,584 documents)
 * and the parity drill-down's per-account fetch (`WHERE accid_fk = ?`) are
 * each a full scan plus a filesort. On staging that did not just run slowly:
 * MariaDB 10.11 SEGFAULTED in the filesort, and run #6 only completed because
 * the staging agent added two indexes BY HAND. Nothing in the repository
 * recorded them, so the next bring-up would have hit the same crash with no
 * trace of the cause.
 *
 * The index set itself lives in {@see LegacyStagingIndexes} so that the
 * loader can apply it to a table it has only just created (a migration that
 * ran at bring-up cannot index a table that `legacy:load` creates later) and
 * this migration can apply it to a staging database that is ALREADY loaded —
 * which is exactly the state run #6's box is in. One source of truth, two
 * entry points.
 *
 * Both directions are safe on a database where the tables are not staged
 * yet: a missing table is skipped, never created. `up()` also skips any
 * column that already leads an index, so it will not add a second copy
 * beside the hand-made `idx_run6_*` pair; `down()` drops only the indexes
 * this class names and leaves the hand-made ones alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        LegacyStagingIndexes::ensure();
    }

    public function down(): void
    {
        LegacyStagingIndexes::drop();
    }
};
