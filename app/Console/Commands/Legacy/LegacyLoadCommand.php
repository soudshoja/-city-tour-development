<?php

declare(strict_types=1);

namespace App\Console\Commands\Legacy;

use App\Services\Onboarding\LegacyCsvLoader;
use App\Services\Onboarding\LegacyPathGuard;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * legacy-ledger-pilot LP0.3.
 *
 * Streams every file in config('legacy_pilot.tables') into its stg_<table>
 * landing table on the quarantined `legacy_pilot` connection, asserting
 * exact row-count parity against config('legacy_pilot.tables.*.rows')
 * (copied verbatim from README-MANIFEST.md) and header stability against
 * the first-load baseline. Any mismatch fails the WHOLE command (exit 1)
 * -- LP0.3 acceptance is "any mismatch fails the load", not a partial
 * best-effort import.
 */
class LegacyLoadCommand extends Command
{
    protected $signature = 'legacy:load
                            {--table= : Load only this manifest key (e.g. tblAccount) instead of all}
                            {--root= : Override the allowed data root. Accepts EITHER the export\'s PARENT directory (e.g. D:\akeedac, config(legacy_pilot.allowed_root)\'s own default shape) OR the export directory itself (e.g. D:\akeedac\ledger-export-2025-2026Q1) -- both are normalised to the parent before the export_dir subfolder is appended, so passing either form loads the same files.}
                            {--force : Reload even if the file is byte-identical to the last successful load}';

    protected $description = 'Stream the legacy-ledger-pilot manifest CSVs into legacy_pilot.stg_* tables (LP0.3).';

    public function handle(LegacyCsvLoader $loader): int
    {
        LegacyPathGuard::assertQuarantinedConnection('legacy_pilot');

        $root = $this->option('root') ?? config('legacy_pilot.allowed_root');
        $exportDir = config('legacy_pilot.export_dir');
        $root = $this->normaliseRoot((string) $root, (string) $exportDir);
        $tables = config('legacy_pilot.tables', []);

        $only = $this->option('table');

        if ($only !== null && ! array_key_exists($only, $tables)) {
            $this->error("Unknown manifest key: {$only}");

            return self::FAILURE;
        }

        $selected = $only !== null ? [$only => $tables[$only]] : $tables;

        $failures = [];
        $totalLoaded = 0;

        foreach ($selected as $key => $definition) {
            $path = rtrim($root, '\\/').DIRECTORY_SEPARATOR.$exportDir.DIRECTORY_SEPARATOR.$definition['file'];

            $this->line("Loading {$key} <= {$definition['file']} (expect {$definition['rows']} rows)...");

            try {
                $result = $loader->load($key, $definition, $path, (bool) $this->option('force'));

                $this->info("  {$key}: {$result['status']}, {$result['rows']} rows.");
                $totalLoaded++;
            } catch (RuntimeException $e) {
                $this->error("  {$key}: FAILED — {$e->getMessage()}");
                $failures[$key] = $e->getMessage();
            }
        }

        if (! empty($failures)) {
            $this->error(sprintf('legacy:load failed for %d of %d table(s): %s', count($failures), count($selected), implode(', ', array_keys($failures))));

            return self::FAILURE;
        }

        $this->info("legacy:load complete — {$totalLoaded} table(s) loaded/verified.");

        return self::SUCCESS;
    }

    /**
     * `--root` (or config('legacy_pilot.allowed_root')) is always joined
     * with $exportDir before a file path is built, so it must name the
     * export directory's PARENT. Passing the export directory itself
     * (e.g. "D:\akeedac\ledger-export-2025-2026Q1" instead of
     * "D:\akeedac") used to double the subfolder and fail every table with
     * "path does not exist" -- a real usage bug hit on the first staging
     * run. Accept either form: if the given root's own last path segment
     * already IS the export directory name, step up to its parent first.
     */
    private function normaliseRoot(string $root, string $exportDir): string
    {
        $trimmed = rtrim($root, '\\/');

        if ($trimmed === '' || $exportDir === '') {
            return $root;
        }

        if (strcasecmp(basename(str_replace('\\', '/', $trimmed)), $exportDir) === 0) {
            return dirname(str_replace('\\', '/', $trimmed));
        }

        return $root;
    }
}
