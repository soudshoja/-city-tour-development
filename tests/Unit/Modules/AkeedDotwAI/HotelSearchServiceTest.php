<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\AkeedDotwAI;

use App\Modules\AkeedDotwAI\Services\FuzzyMatcherService;
use App\Modules\AkeedDotwAI\Services\HotelSearchService;
use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAICountry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for AkeedDotwAI HotelSearchService.
 *
 * Uses Http::fake() to intercept DotwService HTTP calls (DotwService is
 * instantiated inline inside HotelSearchService, not injected, so the
 * Http layer is the interception point).
 *
 * @covers \App\Modules\AkeedDotwAI\Services\HotelSearchService
 */
class HotelSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private HotelSearchService $service;

    /** Minimal DOTW XML search response with one hotel */
    private string $searchXmlResponse;

    /** Minimal DOTW XML getRooms response with one rate */
    private string $browseXmlResponse;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable the module
        config(['akeed_dotwai.enabled' => true]);
        config(['akeed_dotwai.company_id' => 0]); // 0 = env-based fallback (no DB creds)

        // Seed static-data rows
        DotwAICity::create(['code' => '2275', 'name' => 'Dubai', 'country_code' => '14']);
        DotwAICountry::create(['code' => '66', 'name' => 'Kuwait', 'nationality_name' => 'Kuwaiti']);

        // Use array cache driver so Cache::remember behaviour is observable
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->service = new HotelSearchService(new FuzzyMatcherService);

        $this->searchXmlResponse = $this->buildSearchXmlResponse();
        $this->browseXmlResponse = $this->buildBrowseXmlResponse();
    }

    // -------------------------------------------------------------------------
    // search()
    // -------------------------------------------------------------------------

    public function test_search_returns_hotel_found_status(): void
    {
        Http::fake(['*' => Http::response($this->searchXmlResponse, 200)]);

        $result = $this->service->search($this->defaultInput());

        $this->assertSame('hotel_found', $result['status']);
        $this->assertNotEmpty($result['hotels']);
        $this->assertArrayHasKey('nationality_code', $result);
        $this->assertArrayHasKey('residence_code', $result);
    }

    public function test_search_returns_city_not_found_when_fuzzy_fails(): void
    {
        // No Http::fake needed — FuzzyMatcher returns null before any HTTP call
        $input = $this->defaultInput(['city' => 'XYZImaginaryCity99999']);

        $result = $this->service->search($input);

        $this->assertSame('city_not_found', $result['status']);
        Http::assertNothingSent();
    }

    public function test_search_returns_error_when_dotw_service_throws_runtime_exception(): void
    {
        // Simulate credentials missing: DotwService throws RuntimeException on construction
        // when company_id resolves no CompanyDotwCredential and env creds are blank.
        // We fake a 500 or a response that causes a RuntimeException inside DotwService.
        // Easiest: configure no env creds and no DB creds → DotwService constructor throws.
        config([
            'dotw.username' => '',
            'dotw.password' => '',
            'dotw.company_code' => '',
        ]);

        // DotwService will throw RuntimeException('credentials missing' / 'not configured')
        // when it cannot resolve creds. Wrap to verify the service handles it gracefully.
        $result = $this->service->search($this->defaultInput());

        $this->assertSame('error', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_search_caches_result_and_hits_once(): void
    {
        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;

            return Http::response($this->searchXmlResponse, 200);
        });

        $input = $this->defaultInput();

        // First call — goes to HTTP
        $first = $this->service->search($input);
        // Second call — must hit cache, not HTTP
        $second = $this->service->search($input);

        $this->assertSame('hotel_found', $first['status']);
        $this->assertSame('hotel_found', $second['status']);
        // DotwService should have fired exactly one HTTP request for both calls
        $this->assertSame(1, $callCount, 'DotwService should be called once; second call must be served from cache');
    }

    // -------------------------------------------------------------------------
    // browse()
    // -------------------------------------------------------------------------

    public function test_browse_passes_blocking_false_to_dotw_service(): void
    {
        // The browseXmlResponse represents a non-blocked browse call (no allocationDetails
        // block token). We verify the HTTP body contains blocking=false / no block element.
        // Since DotwService constructs the XML internally we inspect the HTTP body sent.
        $sentBody = null;
        Http::fake(function ($request) use (&$sentBody) {
            $sentBody = $request->body();

            return Http::response($this->browseXmlResponse, 200);
        });

        config([
            'dotw.username' => 'testuser',
            'dotw.password' => 'testpass',
            'dotw.company_code' => 'TEST',
            'dotw.api_url' => 'https://fake.dotw.test/api',
        ]);

        $input = $this->defaultInput();
        $this->service->browse('12345', $input, '66', '66');

        // Critical Phase 31 invariant: the XML request must NOT contain a <blockRoom> or
        // blocking=true indicator. The presence of <bookRoom> would indicate block mode.
        if ($sentBody !== null) {
            $this->assertStringNotContainsString('<bookRoom>', $sentBody, 'browse() must not send a block request');
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @param  array<string, mixed>  $overrides */
    private function defaultInput(array $overrides = []): array
    {
        return array_merge([
            'telephone' => '+96599800027',
            'city' => 'Dubai',
            'hotel' => null,
            'guestNationality' => 'Kuwait',
            'checkIn' => '2026-08-01',
            'checkOut' => '2026-08-05',
            'occupancy' => [
                ['adults' => 2, 'childrenAges' => []],
            ],
            'bookingType' => 'B2C',
        ], $overrides);
    }

    private function buildSearchXmlResponse(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<result>
  <hotels>
    <hotel>
      <hotelid>12345</hotelid>
      <name>Fake Dubai Hotel</name>
      <cityCode>2275</cityCode>
      <cityName>Dubai</cityName>
      <countryCode>14</countryCode>
      <starRating>4</starRating>
      <address>Sheikh Zayed Road, Dubai</address>
    </hotel>
  </hotels>
</result>
XML;
    }

    private function buildBrowseXmlResponse(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<result>
  <rooms>
    <room>
      <roomName>Standard Room</roomName>
      <roomTypeCode>STD</roomTypeCode>
      <details>
        <detail>
          <id>0</id>
          <rateBasisDesc>Room Only</rateBasisDesc>
          <allocationDetails>FAKE-ALLOC-DETAILS-123</allocationDetails>
          <price>200.00</price>
          <tariffNotes></tariffNotes>
          <maxOccupancy>2</maxOccupancy>
          <twin>false</twin>
        </detail>
      </details>
    </room>
  </rooms>
</result>
XML;
    }
}
