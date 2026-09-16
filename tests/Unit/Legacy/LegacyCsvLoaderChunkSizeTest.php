<?php

declare(strict_types=1);

namespace Tests\Unit\Legacy;

use App\Services\Onboarding\LegacyCsvLoader;
use Tests\TestCase;

/**
 * legacy-ledger-pilot LP1b defect 1 -- LegacyCsvLoader::chunkSize().
 *
 * A fixed CHUNK_SIZE=500 regardless of column count overran MySQL/
 * MariaDB's 65,535-placeholder-per-prepared-statement ceiling on wide
 * tables: tblTrDetail has 353 columns, so 500 rows x 353 columns =
 * 176,500 placeholders, and the real staging run failed with error 1390.
 * chunkSize() must derive its row count from
 * config('legacy_pilot.loader.max_placeholders') / column_count, floored,
 * never less than 1, and never more than the historical 500-row default.
 *
 * MUTATION PROOF: reverting chunkSize() to `return 500;` unconditionally
 * makes test_a_353_column_table_gets_a_chunk_size_that_fits_under_the_ceiling
 * fail (500 * 353 = 176,500 > 60,000 max_placeholders).
 */
class LegacyCsvLoaderChunkSizeTest extends TestCase
{
    public function test_a_353_column_table_gets_a_chunk_size_that_fits_under_the_ceiling(): void
    {
        config(['legacy_pilot.loader.max_placeholders' => 60000]);

        $chunkSize = app(LegacyCsvLoader::class)->chunkSize(353);

        $this->assertLessThanOrEqual(60000, $chunkSize * 353);
        $this->assertSame(intdiv(60000, 353), $chunkSize);
    }

    public function test_a_narrow_table_keeps_the_historical_500_row_chunk(): void
    {
        config(['legacy_pilot.loader.max_placeholders' => 60000]);

        // 10 columns * 500 rows = 5,000 placeholders, comfortably under the
        // 60,000 cap -- chunkSize() must not shrink narrow tables below the
        // historical default just because a cap now exists.
        $chunkSize = app(LegacyCsvLoader::class)->chunkSize(10);

        $this->assertSame(500, $chunkSize);
    }

    public function test_chunk_size_is_never_less_than_one_however_wide_the_table(): void
    {
        config(['legacy_pilot.loader.max_placeholders' => 60000]);

        // A pathologically wide table (wider than the placeholder cap
        // itself) must still get a usable chunk size, never 0.
        $chunkSize = app(LegacyCsvLoader::class)->chunkSize(100000);

        $this->assertSame(1, $chunkSize);
    }

    public function test_chunk_size_respects_a_reconfigured_placeholder_cap(): void
    {
        config(['legacy_pilot.loader.max_placeholders' => 3530]);

        $chunkSize = app(LegacyCsvLoader::class)->chunkSize(353);

        $this->assertSame(10, $chunkSize);
    }
}
