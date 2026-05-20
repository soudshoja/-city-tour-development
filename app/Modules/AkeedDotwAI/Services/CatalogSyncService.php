<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Services;

use App\Modules\AkeedDotwAI\Models\DotwCatalog;
use App\Services\DotwService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Snapshots every DOTW V4 reference catalog into the dotw_catalogs table.
 *
 * Each catalog type is fetched in its own try/catch so a single DOTW error
 * (e.g., permission not enabled for getroomtypeids) does not abort the entire
 * run. The other 8 already-wrapped catalog types still populate.
 *
 * After the 11-type dispatch loop, a post-dispatch side-effects pass calls
 * getServingCountries() and updates dotwai_countries.is_serving in a single
 * DB transaction (see syncAll docblock).
 *
 * Used by: SyncCatalogsCommand (T34.9), weekly cron via routes/console.php (T34.10).
 * Consumed by: StarRatingResolver (Phase 35), AgentFilterResolver (Phase 37).
 */
class CatalogSyncService
{
    /**
     * Maps catalog type → DotwService method name.
     *
     * NOTE: 'amenity' is a special case — getAmenityIds() internally merges
     * three DOTW commands (getamenitiesids, getleisureids, getbusinessids) and
     * returns rows with a 'category' discriminator. The syncType() dispatcher
     * handles the split into three catalog types (amenity/leisure/business)
     * from this single method call. Those two types are therefore absent from
     * this map.
     *
     * @var array<string, string>
     */
    private const TYPE_TO_METHOD = [
        'classification' => 'getHotelClassifications', // DotwService:895 — returns id/name (normalized below)
        'chain'          => 'getChainIds',             // DotwService:1115
        'location'       => 'getLocationIds',          // DotwService:971
        'amenity'        => 'getAmenityIds',           // DotwService:1012 (merges 3 commands; splits leisure+business)
        'preference'     => 'getPreferenceIds',        // DotwService:1077
        'meal_plan'      => 'getMealPlanIds',          // DotwService:T34.7
        'room_type'      => 'getRoomTypeIds',          // DotwService:T34.5
        'special'        => 'getSpecialsIds',          // DotwService:T34.6
        'salutation'     => 'getSalutationIds',        // DotwService:1157 (returns label=>id map; normalized below)
    ];

    private DotwService $dotw;

    private int $delayMs;

    /**
     * @param  int|null  $companyId  Optional override. Defaults to catalog_sync.company_id
     *                               then akeed_dotwai.company_id (the global default).
     *                               NOTE: dotw_catalogs is a SINGLE GLOBAL TABLE — this only
     *                               changes the DOTW credentials used for the sync request,
     *                               not the storage scope (CONTEXT decision 9).
     */
    public function __construct(?int $companyId = null)
    {
        $resolvedCompanyId = $companyId
            ?? config('akeed_dotwai.catalog_sync.company_id')
            ?? config('akeed_dotwai.company_id');

        // Passes int or null to DotwService constructor.
        // If null, DotwService uses legacy env credentials (no DB lookup).
        // If int, DotwService loads per-company credentials and throws RuntimeException
        // if the row is missing — the caller (SyncCatalogsCommand) handles that.
        $this->dotw = new DotwService(is_int($resolvedCompanyId) ? $resolvedCompanyId : null);

        $this->delayMs = (int) config('akeed_dotwai.catalog_sync.delay_ms', 200);
    }

    /**
     * Sync all (or a filtered subset of) catalog types.
     *
     * After the 11-type dispatch loop completes, a post-dispatch side-effects pass
     * calls getServingCountries() once and updates dotwai_countries.is_serving in
     * a single DB transaction. This is NOT a catalog type — it does not appear in
     * TYPE_TO_METHOD. Logged separately under '[CatalogSyncService] is_serving backfill'.
     *
     * @param  array<string>|null  $only  If given, only sync these types. Must be subset of DotwCatalog::ALL_TYPES.
     * @return array{types: array<array{type: string, rows: int, duration_ms: int, error: string|null}>, rows_total: int, errors: int, duration_ms: int}
     */
    public function syncAll(?array $only = null): array
    {
        $start = hrtime(true);

        $typesToSync = $only ?? array_keys(self::TYPE_TO_METHOD);
        $skipTypes   = (array) config('akeed_dotwai.catalog_sync.skip_types', []);
        $typesToSync = array_values(array_diff($typesToSync, $skipTypes));

        $results   = [];
        $rowsTotal = 0;
        $errors    = 0;

        foreach ($typesToSync as $type) {
            if (! isset(self::TYPE_TO_METHOD[$type])) {
                // leisure / business are derived from the amenity call — skip standalone
                continue;
            }

            $result = $this->syncType($type);
            $results[] = $result;
            $rowsTotal += $result['rows'];

            if ($result['error'] !== null) {
                $errors++;
            }

            if ($this->delayMs > 0) {
                usleep($this->delayMs * 1000);
            }
        }

        // ------------------------------------------------------------------
        // Post-dispatch side-effects pass: update dotwai_countries.is_serving
        // ------------------------------------------------------------------
        // This is NOT a catalog type. It runs after all 11-type dispatch completes.
        $this->backfillIsServing();

        $durationMs = (int) round((hrtime(true) - $start) / 1_000_000);

        Log::channel('dotw')->info('[CatalogSyncService] sync complete', [
            'rows_total'  => $rowsTotal,
            'errors'      => $errors,
            'duration_ms' => $durationMs,
        ]);

        return [
            'types'       => $results,
            'rows_total'  => $rowsTotal,
            'errors'      => $errors,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Sync a single catalog type and return a result summary.
     *
     * The method is wrapped in try/catch for partial-failure tolerance:
     * one catalog type failing (e.g., DOTW permission error on getroomtypeids)
     * does NOT abort the entire sync run.
     *
     * @return array{type: string, rows: int, duration_ms: int, error: string|null}
     */
    public function syncType(string $type): array
    {
        $method = self::TYPE_TO_METHOD[$type] ?? null;

        if ($method === null) {
            return ['type' => $type, 'rows' => 0, 'duration_ms' => 0, 'error' => "No method mapped for type '{$type}'"];
        }

        $typeStart = hrtime(true);

        try {
            /** @var array<array<string, string>> $rawRows */
            $rawRows = $this->dotw->{$method}();

            // Special split: getAmenityIds returns rows with 'category' = amenity/leisure/business
            if ($type === 'amenity') {
                $rowsWritten = $this->persistAmenityMerge($rawRows);
            } elseif ($type === 'classification') {
                $rowsWritten = $this->persistClassifications($rawRows);
            } elseif ($type === 'salutation') {
                $rowsWritten = $this->persistSalutations($rawRows);
            } else {
                $rowsWritten = $this->persistGeneric($type, $rawRows);
            }

            $durationMs = (int) round((hrtime(true) - $typeStart) / 1_000_000);

            Log::channel('dotw')->info('[CatalogSyncService] type complete', [
                'type'        => $type,
                'rows'        => $rowsWritten,
                'duration_ms' => $durationMs,
            ]);

            return ['type' => $type, 'rows' => $rowsWritten, 'duration_ms' => $durationMs, 'error' => null];
        } catch (\Exception $e) {
            $durationMs = (int) round((hrtime(true) - $typeStart) / 1_000_000);

            // Extract DOTW command from the exception message (format: "DOTW <cmd> error [<code>]: <details>")
            $dotwCommand = self::TYPE_TO_METHOD[$type] ?? $type;
            $errorCode   = $this->extractDotwErrorCode($e->getMessage());

            Log::channel('dotw')->warning('[CatalogSyncService] type failed', [
                'type'         => $type,
                'dotw_command' => $dotwCommand,
                'error_code'   => $errorCode,
                'error'        => $e->getMessage(),
                'duration_ms'  => $durationMs,
            ]);

            return ['type' => $type, 'rows' => 0, 'duration_ms' => $durationMs, 'error' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Persistence helpers
    // ------------------------------------------------------------------

    /**
     * Persist generic catalog rows (code + name format from standard wrappers).
     *
     * @param  array<array<string, string>>  $rows  Each row must have 'code' and 'name' keys.
     * @return int Number of rows upserted
     */
    private function persistGeneric(string $type, array $rows): int
    {
        $count = 0;
        $now   = now();

        foreach ($rows as $row) {
            $code = (string) ($row['code'] ?? '');
            $name = (string) ($row['name'] ?? '');

            if ($code === '') {
                continue;
            }

            DotwCatalog::updateOrInsert(
                ['type' => $type, 'code' => $code],
                ['name' => $name, 'synced_at' => $now, 'updated_at' => $now]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Persist hotel classification rows.
     *
     * getHotelClassifications() returns rows with 'id' (not 'code') as the key.
     * Normalizes to 'code' for consistent storage.
     *
     * @param  array<array<string, string>>  $rows
     * @return int
     */
    private function persistClassifications(array $rows): int
    {
        $count = 0;
        $now   = now();

        foreach ($rows as $row) {
            // getHotelClassifications returns ['id' => ..., 'name' => ...]
            $code = (string) ($row['id'] ?? $row['code'] ?? '');
            $name = (string) ($row['name'] ?? '');

            if ($code === '') {
                continue;
            }

            DotwCatalog::updateOrInsert(
                ['type' => DotwCatalog::TYPE_CLASSIFICATION, 'code' => $code],
                ['name' => $name, 'synced_at' => $now, 'updated_at' => $now]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Persist amenity/leisure/business rows from the merged getAmenityIds() response.
     *
     * getAmenityIds() returns rows with 'code', 'name', and 'category' keys where
     * category is one of 'amenity', 'leisure', 'business'. We split them into
     * three separate catalog types.
     *
     * @param  array<array<string, string>>  $rows
     * @return int Total rows written (sum across all three categories)
     */
    private function persistAmenityMerge(array $rows): int
    {
        $count = 0;
        $now   = now();

        foreach ($rows as $row) {
            $code     = (string) ($row['code'] ?? '');
            $name     = (string) ($row['name'] ?? '');
            $category = (string) ($row['category'] ?? 'amenity');

            if ($code === '') {
                continue;
            }

            // Map category to catalog type (amenity/leisure/business all valid enum values)
            $type = in_array($category, ['amenity', 'leisure', 'business'], true) ? $category : 'amenity';

            DotwCatalog::updateOrInsert(
                ['type' => $type, 'code' => $code],
                ['name' => $name, 'category' => $category, 'synced_at' => $now, 'updated_at' => $now]
            );

            $count++;
        }

        return $count;
    }

    /**
     * Persist salutation rows from getSalutationIds().
     *
     * getSalutationIds() returns an associative map: ['mr' => 147, 'mrs' => 149, ...]
     * The numeric ID becomes the 'code'; the label becomes the 'name'.
     * Payload stores the original label for downstream lookup convenience.
     *
     * @param  array<string, int>|array<array<string, string>>  $rows
     * @return int
     */
    private function persistSalutations(array $rows): int
    {
        $count = 0;
        $now   = now();

        foreach ($rows as $label => $id) {
            // getSalutationIds returns label => int, not array rows
            if (is_array($id)) {
                // Fallback: handle if format ever normalised to code/name pairs
                $code = (string) ($id['code'] ?? '');
                $name = (string) ($id['name'] ?? '');
            } else {
                $code = (string) $id;
                $name = (string) $label;
            }

            if ($code === '' || $code === '0') {
                continue;
            }

            DotwCatalog::updateOrInsert(
                ['type' => DotwCatalog::TYPE_SALUTATION, 'code' => $code],
                [
                    'name'      => $name,
                    'payload'   => ['label' => $name],
                    'synced_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $count++;
        }

        return $count;
    }

    // ------------------------------------------------------------------
    // Post-dispatch side-effects
    // ------------------------------------------------------------------

    /**
     * Backfill dotwai_countries.is_serving from getservingcountries.
     *
     * Runs AFTER the 11-type dispatch loop. NOT a catalog type.
     *
     * Uses a DB transaction to reset all rows to false, then mark serving countries
     * as true in a single whereIn query. This avoids stale true values for countries
     * that DOTW has stopped serving.
     *
     * Verification gate (T34.11 R4): is_serving=1 row count should be between 1% and
     * 99% of total dotwai_countries rows. Zero or 100% = likely code/format mismatch.
     */
    private function backfillIsServing(): void
    {
        try {
            $servingCountries = $this->dotw->getServingCountries();
            $servingCodes     = collect($servingCountries)->pluck('code')->filter()->values()->all();

            DB::transaction(function () use ($servingCodes): void {
                DB::table('dotwai_countries')->update(['is_serving' => false]);

                if (! empty($servingCodes)) {
                    DB::table('dotwai_countries')
                        ->whereIn('code', $servingCodes)
                        ->update(['is_serving' => true]);
                }
            });

            // R4 sanity gate: is_serving=1 count should be between 1% and 99% of total rows.
            // Zero = serving-codes format mismatch (DOTW uses different code format than DB).
            // 100% = all countries served, which is implausible — likely a parse error.
            $totalCountries = DB::table('dotwai_countries')->count();
            $servingCount   = DB::table('dotwai_countries')->where('is_serving', true)->count();

            Log::channel('dotw')->info('[CatalogSyncService] is_serving backfill', [
                'serving'         => count($servingCodes),
                'written_serving' => $servingCount,
                'total_countries' => $totalCountries,
            ]);

            if ($totalCountries > 0) {
                $pct = ($servingCount / $totalCountries) * 100;

                if ($pct === 0.0 || $pct >= 99.0) {
                    Log::channel('dotw')->warning('[CatalogSyncService] is_serving R4 gate: suspicious ratio', [
                        'serving_pct'     => round($pct, 1),
                        'serving_count'   => $servingCount,
                        'total_countries' => $totalCountries,
                        'action'          => 'Verify DOTW country code format matches dotwai_countries.code column. Escalate before Phase 35.',
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::channel('dotw')->warning('[CatalogSyncService] is_serving backfill failed', [
                'error' => $e->getMessage(),
            ]);
            // Non-fatal: catalog type rows already written; continue
        }
    }

    /**
     * Parse city deal flags from a serving-cities response.
     *
     * DOTW conventional attributes probed: luxury, topDeals, specialDeals.
     * If the response does not carry them (unknown until first live sync run),
     * all flags default to false. Verifier confirms exact attribute names on
     * first sync run (CONTEXT decision 6 / F4 acceptance gate).
     *
     * NOTE: This helper is called with cities data parsed from getAllCities().
     * Since getAllCities() returns only code+name arrays (no XML attributes),
     * city flags will be false until DotwService exposes the raw XML response
     * for getservingcities. This is tracked as a Phase 34 known deviation:
     * city flags default false, pending raw-XML exposure in a follow-up.
     *
     * @param  array<array<string, mixed>>  $cities  Parsed city rows (code + name)
     * @return array<string, array{is_luxury: bool, is_top_deal: bool, is_special_deal: bool}>
     */
    private function parseServingCitiesFlags(array $cities): array
    {
        $flags = [];

        foreach ($cities as $city) {
            $code = (string) ($city['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $flags[$code] = [
                'is_luxury'      => (bool) ($city['is_luxury'] ?? false),
                'is_top_deal'    => (bool) ($city['is_top_deal'] ?? false),
                'is_special_deal' => (bool) ($city['is_special_deal'] ?? false),
            ];
        }

        return $flags;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Extract DOTW error code from an exception message.
     *
     * Exception messages from DotwService follow the pattern:
     * "DOTW <CommandName> error [<errorCode>]: <details>"
     * Returns the bracketed code or 'UNKNOWN' if not parseable.
     */
    private function extractDotwErrorCode(string $message): string
    {
        if (preg_match('/\[([^\]]+)\]/', $message, $m)) {
            return $m[1];
        }

        return 'UNKNOWN';
    }
}
