<?php

declare(strict_types=1);

namespace App\Modules\AkeedDotwAI\Jobs;

use App\Modules\DotwAI\Models\DotwAIHotel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Lazily enrich the dotwai_hotels catalog from a live searchhotels response.
 *
 * Dispatched by HotelSearchService::search() after the response envelope is
 * built — it runs on the default queue after the HTTP response is sent so
 * customer-facing search latency is unaffected.
 *
 * Only hotels whose names are non-empty (i.e. DOTW returned <hotelName> in
 * the regular search XML) are upserted. Hotels with empty names are skipped
 * to avoid polluting the catalog with "Hotel #ID" placeholders — the weekly
 * akeed-dotwai:sync-hotels run (which uses the noPrice+fields path) is the
 * authoritative source for those.
 *
 * Idempotent: running twice with the same data produces the same result
 * because the upsert uses dotw_hotel_id as the unique key and updates
 * only columns that are present in the live response.
 *
 * @see \App\Modules\AkeedDotwAI\Services\HotelSearchService::search()
 */
class EnrichHotelCatalogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum queue attempts before the job is abandoned.
     *
     * Enrichment is best-effort — a failed upsert does not affect the search
     * result the customer already received, so a single retry is sufficient.
     */
    public int $tries = 2;

    /**
     * @param  array<int, array{
     *   hotelId: string,
     *   hotelName: string,
     *   cityName: string,
     *   countryName: string,
     *   address: string|null,
     *   latitude: float|null,
     *   longitude: float|null,
     * }>  $hotels  Metadata rows extracted from the searchhotels response
     */
    public function __construct(private readonly array $hotels) {}

    /**
     * Execute the enrichment upsert.
     *
     * Filters out hotels with empty names before the DB write so we never
     * overwrite a good name from the weekly sync with an empty string from
     * a live search that did not return the fields block.
     *
     * Uses a single bulk upsert keyed on dotw_hotel_id. The `country` column
     * is NOT NULL in the migration — empty string is used as the safe fallback
     * when DOTW does not return countryName in the regular search response.
     */
    public function handle(): void
    {
        // Filter: only enrich rows where DOTW returned a real hotel name.
        $rows = array_values(array_filter(
            $this->hotels,
            static fn (array $h): bool => isset($h['hotelId'])
                && $h['hotelId'] !== ''
                && isset($h['hotelName'])
                && $h['hotelName'] !== '',
        ));

        if (empty($rows)) {
            return;
        }

        $upsertRows = array_map(static fn (array $h): array => [
            'dotw_hotel_id' => $h['hotelId'],
            'name' => $h['hotelName'],
            'city' => $h['cityName'] ?? '',
            'country' => $h['countryName'] ?? '',
            'address' => $h['address'] ?? null,
            'latitude' => $h['latitude'] ?? null,
            'longitude' => $h['longitude'] ?? null,
        ], $rows);

        try {
            // Bulk upsert: insert new rows, update name/city/country/address/coords
            // on conflict. star_rating is intentionally excluded — the weekly sync
            // owns that field (it always comes back null from live search anyway).
            DotwAIHotel::upsert(
                $upsertRows,
                ['dotw_hotel_id'],
                ['name', 'city', 'country', 'address', 'latitude', 'longitude'],
            );

            Log::channel('dotw')->info('[AkeedDotwAI] lazy hotel catalog enrichment', [
                'upserted' => count($upsertRows),
            ]);
        } catch (\Throwable $e) {
            // Log and swallow — enrichment is best-effort.
            Log::channel('dotw')->warning('[AkeedDotwAI] EnrichHotelCatalogJob upsert failed', [
                'error' => $e->getMessage(),
                'count' => count($upsertRows),
            ]);
        }
    }
}
