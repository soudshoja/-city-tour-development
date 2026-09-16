<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * legacy-ledger-pilot LP0.3 -- streams one manifest CSV into its stg_<table>
 * landing table on the quarantined `legacy_pilot` connection.
 *
 * Deliberately "dumb" per file: it does NOT transform a single value. Every
 * column lands as a nullable TEXT column -- deliberately NOT typed as
 * BIGINT/DECIMAL by name heuristic. An earlier revision inferred numeric
 * SQL types from column-name patterns (e.g. "*_FK" -> BIGINT, "*Comm*" ->
 * DECIMAL) and that broke on real legacy data the first time it was
 * exercised end to end: a column literally named `Commision_type` (sic --
 * the legacy typo) matched the amount-shaped "Comm" pattern and got a
 * DECIMAL column, then failed to load its actual (non-numeric) category
 * values. A landing/staging layer whose own stated principle is "no
 * transformation" has no business making a per-column numeric-vs-text
 * judgement call by name-sniffing; that judgement belongs to the code that
 * later CONSUMES a specific, known column (CoaImporter, MastersAuditor,
 * ...), which casts with `(int)`/`is_numeric()` at the point of use and can
 * fail loudly on that one column instead of aborting the whole load. The
 * table schema (one TEXT column per header field) is discovered from the
 * CSV's own header row rather than hand-authored per file (30 files,
 * several 250+ columns wide -- see the migration's docblock).
 *
 * Idempotency: a SHA-256 of the file's bytes is recorded in
 * `legacy_load_audit`. Re-running against byte-identical input is a no-op
 * (status `skipped_identical`); a changed file truncates and reloads.
 */
final class LegacyCsvLoader
{
    private const CHUNK_SIZE = 500;

    /**
     * Rows-per-INSERT chunk size, capped so rows * $columnCount never
     * exceeds MySQL/MariaDB's per-prepared-statement placeholder ceiling
     * (65,535). A fixed CHUNK_SIZE=500 regardless of column count breaks on
     * wide tables -- tblTrDetail has 353 columns, so 500 rows needs 176,500
     * placeholders and fails with error 1390. The cap itself comes from
     * config('legacy_pilot.loader.max_placeholders'), never a literal in
     * this method, so it can be tuned without a code change. Always at
     * least 1 row, however wide the table.
     */
    public function chunkSize(int $columnCount): int
    {
        $maxPlaceholders = (int) config('legacy_pilot.loader.max_placeholders', 60000);
        $columnCount = max(1, $columnCount);

        return max(1, min(self::CHUNK_SIZE, intdiv($maxPlaceholders, $columnCount)));
    }

    public function load(string $tableKey, array $definition, string $csvPath, bool $force = false): array
    {
        LegacyPathGuard::assertPathUnderRoot($csvPath);
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $stgTable = $definition['table'];
        $expectedRows = (int) $definition['rows'];

        $startedAt = now();
        $fileHash = hash_file('sha256', $csvPath);

        if ($fileHash === false) {
            throw new RuntimeException("Unable to hash file: {$csvPath}");
        }

        if (! $force && $this->alreadyLoadedIdentical($tableKey, $fileHash)) {
            $this->recordAudit($tableKey, $stgTable, $csvPath, $expectedRows, $this->currentRowCount($stgTable), 'skipped_identical', $fileHash, 'unchanged since last load', $startedAt);

            return ['status' => 'skipped_identical', 'rows' => $this->currentRowCount($stgTable)];
        }

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open file: {$csvPath}");
        }

        try {
            // A UTF-8 BOM sits BEFORE the first field's opening quote, so
            // fgetcsv() never recognises that field as quoted at all --
            // stripping the BOM after fgetcsv() has already mis-parsed the
            // field is too late. Skip the 3 BOM bytes on the raw stream
            // first, if present.
            $bom = fread($handle, 3);

            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $header = fgetcsv($handle);

            if ($header === false || $header === null) {
                throw new RuntimeException("Empty or unreadable header row: {$csvPath}");
            }

            $header = array_map(fn ($h) => $this->stripBom((string) $h), $header);

            $this->validateOrRecordHeader($tableKey, $header);

            $this->ensureTable($stgTable, $header);

            DB::connection('legacy_pilot')->table($stgTable)->truncate();

            $columns = array_map([$this, 'columnName'], $header);
            $chunkSize = $this->chunkSize(count($columns));
            $rowCount = 0;
            $buffer = [];

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === false) {
                    continue;
                }

                // Pad/truncate defensively -- a landing table must never
                // choke on a short/long row; downstream audits (LP0.4) are
                // the ones that flag structural anomalies, not this loader.
                $row = array_pad(array_slice($row, 0, count($columns)), count($columns), null);

                $buffer[] = array_combine($columns, $row);
                $rowCount++;

                if (count($buffer) >= $chunkSize) {
                    DB::connection('legacy_pilot')->table($stgTable)->insert($buffer);
                    $buffer = [];
                }
            }

            if (! empty($buffer)) {
                DB::connection('legacy_pilot')->table($stgTable)->insert($buffer);
            }
        } finally {
            fclose($handle);
        }

        if ($rowCount !== $expectedRows) {
            $this->recordAudit($tableKey, $stgTable, $csvPath, $expectedRows, $rowCount, 'failed', $fileHash, "row count mismatch: expected {$expectedRows}, loaded {$rowCount}", $startedAt);

            throw new RuntimeException(
                "legacy:load row-count mismatch for '{$tableKey}' ({$stgTable}): expected {$expectedRows}, loaded {$rowCount}."
            );
        }

        $this->recordAudit($tableKey, $stgTable, $csvPath, $expectedRows, $rowCount, 'loaded', $fileHash, null, $startedAt);

        return ['status' => 'loaded', 'rows' => $rowCount];
    }

    private function alreadyLoadedIdentical(string $tableKey, string $fileHash): bool
    {
        $last = DB::connection('legacy_pilot')->table('legacy_load_audit')
            ->where('table_key', $tableKey)
            ->where('status', 'loaded')
            ->orderByDesc('id')
            ->first();

        return $last !== null && $last->file_hash === $fileHash;
    }

    private function currentRowCount(string $stgTable): int
    {
        if (! Schema::connection('legacy_pilot')->hasTable($stgTable)) {
            return 0;
        }

        return (int) DB::connection('legacy_pilot')->table($stgTable)->count();
    }

    private function validateOrRecordHeader(string $tableKey, array $header): void
    {
        $existing = DB::connection('legacy_pilot')->table('legacy_load_manifest')
            ->where('table_key', $tableKey)
            ->first();

        if ($existing === null) {
            DB::connection('legacy_pilot')->table('legacy_load_manifest')->insert([
                'table_key' => $tableKey,
                'header_json' => json_encode($header),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $baseline = json_decode($existing->header_json, true) ?? [];

        if ($baseline !== $header) {
            throw new RuntimeException(
                "legacy:load header mismatch for '{$tableKey}': expected columns [".implode(',', $baseline).'], got ['.implode(',', $header).'].'
            );
        }
    }

    private function ensureTable(string $stgTable, array $header): void
    {
        if (Schema::connection('legacy_pilot')->hasTable($stgTable)) {
            // Still ensure the keys: a table staged before LP4b exists with
            // its columns but without them.
            LegacyStagingIndexes::ensure();

            return;
        }

        Schema::connection('legacy_pilot')->create($stgTable, function (Blueprint $table) use ($header) {
            $table->id('stg_row_id');

            foreach ($header as $column) {
                $table->text($this->columnName($column))->nullable();
            }

            $table->timestamp('stg_loaded_at')->useCurrent();
        });

        // LP4b: the loader knows nothing about which TEXT column is a key,
        // so the hot-query indexes are applied from one shared spec the
        // moment the table exists. Unindexed, `stg_acc_detail` segfaulted
        // MariaDB 10.11 in the replay's filesort — see LegacyStagingIndexes.
        LegacyStagingIndexes::ensure();
    }

    private function columnName(string $header): string
    {
        return LegacyColumn::name($header);
    }

    private function recordAudit(string $tableKey, string $stgTable, string $csvPath, int $expected, int $loaded, string $status, string $fileHash, ?string $message, $startedAt): void
    {
        DB::connection('legacy_pilot')->table('legacy_load_audit')->insert([
            'table_key' => $tableKey,
            'stg_table' => $stgTable,
            'source_file' => basename($csvPath),
            'expected_rows' => $expected,
            'loaded_rows' => $loaded,
            'status' => $status,
            'file_hash' => $fileHash,
            'message' => $message,
            'started_at' => $startedAt,
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }
}
