<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\AkeedDotwAI;

use App\Modules\AkeedDotwAI\Console\Commands\SyncHotelsCommand;
use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAIHotel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature tests for akeed-dotwai:sync-hotels command.
 *
 * No live DOTW calls are made. DotwService is instantiated directly inside
 * SyncHotelsCommand (not via the container), so we intercept at the HTTP layer
 * using Http::fake() — the same technique used in HotelSearchServiceTest.
 *
 * company_id = 0 (null/legacy path) is used so the DotwService constructor
 * falls back to env credentials instead of querying company_dotw_credentials,
 * preventing a RuntimeException in tests where no credential rows exist.
 *
 * DatabaseTransactions wraps each test in a rolled-back transaction so the
 * schema stays intact between tests without running migrate:fresh (~60s on
 * this project's large migration set).
 *
 * Command registration: AkeedDotwAIServiceProvider gates the command behind
 * config('akeed_dotwai.enabled') at boot time. We register it manually after
 * boot via Kernel::registerCommand() so tests are not sensitive to boot order.
 *
 * @covers \App\Modules\AkeedDotwAI\Console\Commands\SyncHotelsCommand
 */
class SyncHotelsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected bool $skipPermissionSeeder = true;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'akeed_dotwai.enabled' => true,
            'akeed_dotwai.company_id' => 1,
            'akeed_dotwai.hotel_sync_priority_cities' => [364],
            'akeed_dotwai.hotel_sync_delay_ms' => 0,
            'dotw.api_url' => 'https://fake.dotw.test/api',
        ]);

        // Insert a fake CompanyDotwCredential row so DotwService constructor
        // succeeds for company_id=1 without real DOTW creds.
        // FK checks are disabled temporarily because there is no companies row
        // in the test DB — we only care about the credential lookup, not the
        // company row itself. Crypt::encrypt() is used because the model's
        // mutator expects encrypted values on insert.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('company_dotw_credentials')->insertOrIgnore([
            'company_id' => 1,
            'dotw_username' => encrypt('testuser'),
            'dotw_password' => encrypt('testpass'),
            'dotw_company_code' => 'TEST',
            'markup_percent' => 20.00,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Register the command after boot — the service provider's boot() gate
        // (enabled check + runningInConsole) runs before setUp(), so we register
        // manually here to guarantee the command exists for Artisan::call().
        $this->app[Kernel::class]->registerCommand(
            $this->app->make(SyncHotelsCommand::class)
        );

        // Seed the dotwai_cities row for Dubai (code 364) so the command can
        // resolve the city name fallback label from the DB.
        DotwAICity::create(['code' => '364', 'name' => 'Dubai', 'country_code' => '88']);
    }

    // -------------------------------------------------------------------------
    // XML response helpers
    // -------------------------------------------------------------------------

    /**
     * Build a minimal DOTW searchhotels XML response with 3 hotel elements.
     * Field names match what DotwService::searchHotelsWithMetadata parses:
     * hotelid attribute, hotelName, cityName, countryName, address child elements.
     */
    private function fakeSearchHotelsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<result>
  <successful>TRUE</successful>
  <hotels>
    <hotel hotelid="1001">
      <hotelName>Test Hotel 1</hotelName>
      <cityName>DUBAI</cityName>
      <countryName>UAE</countryName>
      <address>Addr 1</address>
      <geoPoint><lat>25.1</lat><lng>55.1</lng></geoPoint>
    </hotel>
    <hotel hotelid="1002">
      <hotelName>Test Hotel 2</hotelName>
      <cityName>DUBAI</cityName>
      <countryName>UAE</countryName>
      <address>Addr 2</address>
      <geoPoint><lat>25.2</lat><lng>55.2</lng></geoPoint>
    </hotel>
    <hotel hotelid="1003">
      <hotelName>Test Hotel 3</hotelName>
      <cityName>DUBAI</cityName>
      <countryName>UAE</countryName>
      <address>Addr 3</address>
      <geoPoint><lat>25.3</lat><lng>55.3</lng></geoPoint>
    </hotel>
  </hotels>
</result>
XML;
    }

    // -------------------------------------------------------------------------
    // Test 5 — inserts hotels for a given city
    // -------------------------------------------------------------------------

    /**
     * Running sync-hotels with --city=364 (Dubai) against a faked HTTP layer
     * that returns 3 hotel elements must insert exactly 3 rows into dotwai_hotels.
     *
     * Http::fake() intercepts DotwService's HTTP post so no live DOTW call occurs.
     */
    public function test_sync_hotels_inserts_hotels_for_given_city(): void
    {
        Http::fake(['*' => Http::response($this->fakeSearchHotelsXml(), 200)]);

        Artisan::call('akeed-dotwai:sync-hotels', ['--city' => ['364']]);

        $this->assertSame(3, DotwAIHotel::count(), 'Three hotels should be inserted');

        $names = DotwAIHotel::orderBy('dotw_hotel_id')->pluck('name')->toArray();
        $this->assertSame(['Test Hotel 1', 'Test Hotel 2', 'Test Hotel 3'], $names);
    }

    // -------------------------------------------------------------------------
    // Test 6 — idempotent: second run with --force does not duplicate rows
    // -------------------------------------------------------------------------

    /**
     * Running sync-hotels twice with --force must not create duplicate rows.
     * updateOrCreate ensures the count stays at 3 and rows are updated in-place.
     *
     * We verify the second run actually executed (not short-circuited) by
     * asserting Http::fake() received exactly 2 requests — one per Artisan call.
     * This proves searchHotelsWithMetadata was called both times and updateOrCreate
     * processed the data without inserting duplicates.
     *
     * Note: Carbon::setTestNow() does not affect MySQL's NOW() function, so
     * updated_at timestamp comparison is not a reliable idempotency proof here.
     * The HTTP call count is the correct invariant to assert.
     */
    public function test_sync_hotels_is_idempotent(): void
    {
        Http::fake(['*' => Http::response($this->fakeSearchHotelsXml(), 200)]);

        // First run — inserts 3 rows
        Artisan::call('akeed-dotwai:sync-hotels', ['--city' => ['364']]);
        $this->assertSame(3, DotwAIHotel::count(), 'First run must insert 3 hotels');

        // Second run with --force bypasses the exists() guard and calls updateOrCreate
        Artisan::call('akeed-dotwai:sync-hotels', ['--city' => ['364'], '--force' => true]);

        // Count must still be 3 — updateOrCreate must not insert duplicates
        $this->assertSame(3, DotwAIHotel::count(), 'Second run must not create duplicate rows');

        // Two HTTP requests fired (one per city per run) proves the second run
        // actually called searchHotelsWithMetadata and did not short-circuit.
        Http::assertSentCount(2);
    }
}
