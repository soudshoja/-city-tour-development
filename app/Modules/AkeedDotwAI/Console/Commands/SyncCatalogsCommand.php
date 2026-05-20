<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Console\Commands;

use App\Modules\AkeedDotwAI\Models\DotwCatalog;
use App\Modules\AkeedDotwAI\Services\CatalogSyncService;
use Illuminate\Console\Command;

/**
 * Snapshot DOTW reference catalogs into the dotw_catalogs table.
 *
 * Syncs all 11 DOTW reference taxonomies (classifications, chains, locations,
 * amenities, leisure, business, preferences, meal plans, room types, specials,
 * and salutations) by default. Use --type= to restrict to a specific subset.
 *
 * Wired to a weekly Sunday 04:00 KW cron in routes/console.php (T34.10).
 *
 * Exit codes:
 *   0 (SUCCESS) — at least one type produced rows, or all types ran with partial failures
 *   1 (FAILURE) — unknown type supplied, missing DOTW credentials, or every type failed
 *
 * Usage:
 *   php artisan akeed-dotwai:sync-catalogs
 *   php artisan akeed-dotwai:sync-catalogs --type=chain
 *   php artisan akeed-dotwai:sync-catalogs --type=chain --type=classification
 */
class SyncCatalogsCommand extends Command
{
    protected $signature = 'akeed-dotwai:sync-catalogs
                            {--type=* : Catalog types to sync (default: all). Repeat for multiple.}';

    protected $description = 'Snapshot DOTW reference catalogs into the dotw_catalogs table.';

    public function handle(): int
    {
        /** @var array<string> $requested */
        $requested = $this->option('type') ?: [];

        // Validate requested types against the known enum values
        if (! empty($requested)) {
            $unknown = array_diff($requested, DotwCatalog::ALL_TYPES);
            if (! empty($unknown)) {
                $this->error('Unknown catalog types: ' . implode(', ', $unknown));
                $this->line('Valid types: ' . implode(', ', DotwCatalog::ALL_TYPES));

                return self::FAILURE;
            }
        }

        // Instantiate the sync service — may throw RuntimeException if DOTW
        // credentials are missing for the configured company_id.
        try {
            $service = new CatalogSyncService();
        } catch (\RuntimeException $e) {
            $this->error('DOTW credentials problem: ' . $e->getMessage());
            $this->line('Ensure company_dotw_credentials row exists for the configured company_id.');

            return self::FAILURE;
        }

        $only   = ! empty($requested) ? $requested : null;
        $result = $service->syncAll($only);

        // Per-type output lines
        foreach ($result['types'] as $type) {
            $line = sprintf('[%s] %d rows in %dms', $type['type'], $type['rows'], $type['duration_ms']);

            if ($type['error'] !== null) {
                $this->warn($line . ' — FAILED: ' . $type['error']);
            } else {
                $this->info($line);
            }
        }

        // Summary line
        $this->info(sprintf(
            'Sync complete: %d types, %d rows total, %d errors.',
            count($result['types']),
            $result['rows_total'],
            $result['errors'],
        ));

        // Return FAILURE only when every single type failed (catastrophic).
        // Partial failures (some types OK, some failed) = SUCCESS exit code.
        if ($result['rows_total'] === 0 && count($result['types']) > 0 && $result['errors'] === count($result['types'])) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
