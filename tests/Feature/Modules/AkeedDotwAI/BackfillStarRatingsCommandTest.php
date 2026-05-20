<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\AkeedDotwAI;

use App\Modules\AkeedDotwAI\Console\Commands\BackfillStarRatingsCommand;
use App\Modules\AkeedDotwAI\Models\DotwCatalog;
use App\Modules\AkeedDotwAI\Services\StarRatingResolver;
use App\Modules\DotwAI\Models\DotwAIHotel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature tests for akeed-dotwai:backfill-star-ratings command.
 *
 * No live DOTW calls are made. Http::fake() intercepts DotwService's HTTP
 * layer (the same technique used in SyncHotelsCommandTest).
 *
 * The company_id=null (legacy env path) is used for most tests so DotwService
 * constructor does not throw — it falls back to env credentials instead of
 * querying company_dotw_credentials. For test #8 (credential failure), we
 * switch to company_id=1 without a credentials row so the constructor throws.
 *
 * DatabaseTransactions wraps each test in a rolled-back transaction.
 * StarRatingResolver::clearCache() is called in setUp() to reset the static map.
 *
 * @covers \App\Modules\AkeedDotwAI\Console\Commands\BackfillStarRatingsCommand
 */
class BackfillStarRatingsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected bool $skipPermissionSeeder = true;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'akeed_dotwai.enabled' => true,
            'akeed_dotwai.company_id' => null,  // Legacy env path — no credential row needed
            'akeed_dotwai.default_currency' => '769',
            'dotw.dev_mode' => true,
            'dotw.endpoints.development' => 'https://fake.dotw.test/gatewayV4.dotw',
        ]);

        // Reset StarRatingResolver cache so each test gets a fresh map from DB.
        StarRatingResolver::clearCache();

        // Register the command after boot (service provider gates on enabled flag).
        $this->app[Kernel::class]->registerCommand(
            $this->app->make(BackfillStarRatingsCommand::class)
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Seed a classification catalog row.
     */
    private function seedCatalog(string $code, string $name): void
    {
        DotwCatalog::create([
            'type' => DotwCatalog::TYPE_CLASSIFICATION,
            'code' => $code,
            'name' => $name,
            'synced_at' => now(),
        ]);
    }

    /**
     * Seed a dotwai_cities row (name → code mapping for the JOIN).
     */
    private function seedCity(string $code, string $name): void
    {
        DB::table('dotwai_cities')->insertOrIgnore([
            'code' => $code,
            'name' => $name,
            'country_code' => 'AE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Seed a dotwai_hotels row.
     */
    private function seedHotel(string $hotelId, string $city, ?int $starRating = null): void
    {
        DB::table('dotwai_hotels')->insertOrIgnore([
            'dotw_hotel_id' => $hotelId,
            'name' => 'Test Hotel '.$hotelId,
            'city' => $city,
            'country' => 'UAE',
            'star_rating' => $starRating,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Build a DOTW searchhotels XML response with hotels carrying <rating> codes.
     *
     * @param  array<int, array{hotelid: string, rating: string}>  $hotels
     */
    private function buildSearchHotelsXml(array $hotels): string
    {
        $hotelsXml = '';
        foreach ($hotels as $h) {
            $hotelsXml .= sprintf(
                '<hotel hotelid="%s"><hotelName>Hotel %s</hotelName><cityName>Dubai</cityName><rating>%s</rating></hotel>',
                $h['hotelid'],
                $h['hotelid'],
                $h['rating']
            );
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<result>'
            .'<successful>TRUE</successful>'
            .'<hotels>'.$hotelsXml.'</hotels>'
            .'</result>';
    }

    // -------------------------------------------------------------------------
    // Test 1: No NULL rows → exits 0 with "Nothing to do."
    // -------------------------------------------------------------------------

    public function test_command_exits_success_when_no_null_rows_exist(): void
    {
        $this->seedCatalog('561', '5 Star');
        $this->seedCity('1', 'Dubai');
        $this->seedHotel('H1', 'Dubai', 5);  // star_rating already set

        Http::fake();  // No HTTP calls expected

        $exitCode = Artisan::call('akeed-dotwai:backfill-star-ratings');
        $output = Artisan::output();

        $this->assertSame(BackfillStarRatingsCommand::SUCCESS, $exitCode);
        $this->assertStringContainsString('Nothing to do.', $output);
        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Test 2: Empty catalog triggers sync attempt; stays empty → exit 1
    // -------------------------------------------------------------------------

    /**
     * When dotw_catalogs(type='classification') is empty, the command must
     * attempt to call akeed-dotwai:sync-catalogs and then exit 1 if still empty.
     *
     * The sync-catalogs command is itself registered so Artisan::call() can invoke
     * it, but with no DOTW credentials and Http::fake(returning error), the sync
     * will write zero rows, leaving the catalog empty.
     *
     * Note: Artisan::output() after a command that internally calls Artisan::call()
     * may only capture the inner command's stdout. We verify exit code + catalog
     * state rather than relying on the outer command's error message appearing in
     * the captured output buffer.
     */
    public function test_command_attempts_sync_catalogs_and_exits_1_when_catalog_remains_empty(): void
    {
        // Seed a hotel with NULL star_rating so the command gets past the "nothing to do" check.
        // But since catalog is empty, it should attempt sync-catalogs first.
        $this->seedCity('1', 'Dubai');
        $this->seedHotel('H1', 'Dubai', null);

        // dotw_catalogs is empty — no classification rows seeded.
        // Http::fake returns a non-OK response so the DOTW sync call fails silently,
        // leaving dotw_catalogs empty after sync attempt.
        Http::fake(['*' => Http::response('<result><successful>FALSE</successful></result>', 200)]);

        $exitCode = Artisan::call('akeed-dotwai:backfill-star-ratings');

        // Command must exit 1 because the catalog is still empty after sync attempt.
        $this->assertSame(BackfillStarRatingsCommand::FAILURE, $exitCode);
        // Catalog must still be empty (sync did not add rows).
        $this->assertSame(0, DotwCatalog::ofType(DotwCatalog::TYPE_CLASSIFICATION)->count());
    }

    // -------------------------------------------------------------------------
    // Test 3: Happy path — resolves and writes star_rating
    // -------------------------------------------------------------------------

    public function test_command_resolves_and_writes_star_rating(): void
    {
        $this->seedCatalog('561', '5 Star');
        $this->seedCity('1', 'Dubai');
        $this->seedHotel('H1', 'Dubai', null);  // NULL star_rating to be filled

        Http::fake([
            '*' => Http::response(
                $this->buildSearchHotelsXml([['hotelid' => 'H1', 'rating' => '561']]),
                200
            ),
        ]);

        $exitCode = Artisan::call('akeed-dotwai:backfill-star-ratings');
        $output = Artisan::output();

        $this->assertSame(BackfillStarRatingsCommand::SUCCESS, $exitCode);
        $this->assertSame(5, DotwAIHotel::where('dotw_hotel_id', 'H1')->value('star_rating'));
        $this->assertStringContainsString('Backfill complete:', $output);
        $this->assertStringContainsString('1 rows updated', $output);
    }

    // -------------------------------------------------------------------------
    // Test 4: Existing non-null star_rating is NOT overwritten
    // -------------------------------------------------------------------------

    public function test_command_skips_hotels_with_existing_star_rating(): void
    {
        $this->seedCatalog('561', '5 Star');
        $this->seedCity('1', 'Dubai');
        $this->seedHotel('H1', 'Dubai', 3);  // star_rating=3 already set

        // DOTW would return rating=561 (5 stars), but the hotel won't appear in the
        // city list because it was excluded by WHERE star_rating IS NULL in the query.
        Http::fake();

        $exitCode = Artisan::call('akeed-dotwai:backfill-star-ratings');

        $this->assertSame(BackfillStarRatingsCommand::SUCCESS, $exitCode);
        // Row should still be 3, not overwritten with 5.
        $this->assertSame(3, DotwAIHotel::where('dotw_hotel_id', 'H1')->value('star_rating'));
        Http::assertNothingSent();  // No city with NULL star_rating = no DOTW call
    }

    // -------------------------------------------------------------------------
    // Test 5: Per-city error → continues, other cities still processed
    // -------------------------------------------------------------------------

    public function test_command_continues_on_per_city_error(): void
    {
        $this->seedCatalog('561', '5 Star');
        $this->seedCity('1', 'Dubai');
        $this->seedCity('99', 'Abu Dhabi');
        $this->seedHotel('H1', 'Dubai', null);
        $this->seedHotel('H2', 'Abu Dhabi', null);

        // First city POST (Dubai) returns HTTP 500, second (Abu Dhabi) returns OK.
        Http::fake([
            '*' => Http::sequence()
                ->push('<error>fail</error>', 500)
                ->push($this->buildSearchHotelsXml([['hotelid' => 'H2', 'rating' => '561']]), 200),
        ]);

        $exitCode = Artisan::call('akeed-dotwai:backfill-star-ratings');
        $output = Artisan::output();

        $this->assertSame(BackfillStarRatingsCommand::SUCCESS, $exitCode);
        // H2 (Abu Dhabi) should be resolved to 5 stars.
        $this->assertSame(5, DotwAIHotel::where('dotw_hotel_id', 'H2')->value('star_rating'));
        // H1 (Dubai) failed — still NULL.
        $this->assertNull(DotwAIHotel::where('dotw_hotel_id', 'H1')->value('star_rating'));
        $this->assertStringContainsString('1 errors', $output);
    }

    // -------------------------------------------------------------------------
    // Test 6: --dry-run flag skips writes
    // -------------------------------------------------------------------------

    public function test_dry_run_flag_skips_writes(): void
    {
        $this->seedCatalog('561', '5 Star');
        $this->seedCity('1', 'Dubai');
        $this->seedHotel('H1', 'Dubai', null);

        Http::fake([
            '*' => Http::response(
                $this->buildSearchHotelsXml([['hotelid' => 'H1', 'rating' => '561']]),
                200
            ),
        ]);

        $exitCode = Artisan::call('akeed-dotwai:backfill-star-ratings', ['--dry-run' => true]);

        $this->assertSame(BackfillStarRatingsCommand::SUCCESS, $exitCode);
        // Dry run must NOT write.
        $this->assertNull(DotwAIHotel::where('dotw_hotel_id', 'H1')->value('star_rating'));
    }

    // -------------------------------------------------------------------------
    // Test 7: --from-city flag skips cities below the offset
    // -------------------------------------------------------------------------

    public function test_from_city_flag_resumes_from_offset(): void
    {
        $this->seedCatalog('561', '5 Star');
        $this->seedCity('1', 'Dubai');    // code='1' — should be skipped (< '50')
        $this->seedCity('99', 'Abu Dhabi'); // code='99' — should be processed (>= '50')
        $this->seedHotel('H1', 'Dubai', null);
        $this->seedHotel('H2', 'Abu Dhabi', null);

        Http::fake([
            '*' => Http::response(
                $this->buildSearchHotelsXml([['hotelid' => 'H2', 'rating' => '561']]),
                200
            ),
        ]);

        $exitCode = Artisan::call('akeed-dotwai:backfill-star-ratings', ['--from-city' => '50']);

        $this->assertSame(BackfillStarRatingsCommand::SUCCESS, $exitCode);
        // Only one HTTP call was made (for city '99').
        Http::assertSentCount(1);
        // H2 (Abu Dhabi, code=99) should be resolved.
        $this->assertSame(5, DotwAIHotel::where('dotw_hotel_id', 'H2')->value('star_rating'));
        // H1 (Dubai, code=1) should still be NULL (skipped by --from-city=50).
        $this->assertNull(DotwAIHotel::where('dotw_hotel_id', 'H1')->value('star_rating'));
    }

    // -------------------------------------------------------------------------
    // Test 8: RuntimeException from DotwService constructor → exit 1
    // -------------------------------------------------------------------------

    /**
     * When company_id is non-null and no credential row exists,
     * DotwService constructor throws RuntimeException.
     * The command must catch it and exit 1 with a clear error message.
     */
    public function test_runtime_exception_from_dotw_service_constructor_exits_1(): void
    {
        // Switch to company_id=1 — no credential row inserted, so constructor throws.
        config(['akeed_dotwai.company_id' => 1]);

        // Ensure no credential row exists for company_id=1.
        DB::table('company_dotw_credentials')->where('company_id', 1)->delete();

        $exitCode = Artisan::call('akeed-dotwai:backfill-star-ratings');
        $output = Artisan::output();

        $this->assertSame(BackfillStarRatingsCommand::FAILURE, $exitCode);
        $this->assertStringContainsString('DOTW credentials', $output);
    }
}
