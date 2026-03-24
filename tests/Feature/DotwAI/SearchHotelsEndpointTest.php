<?php

declare(strict_types=1);

namespace Tests\Feature\DotwAI;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyDotwCredential;
use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAIHotel;
use App\Services\DotwService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

/**
 * Feature tests for POST /api/dotwai/search_hotels endpoint.
 *
 * Validates the full request lifecycle: middleware -> validation -> service
 * -> DotwService mock -> formatting -> response envelope.
 *
 * @covers \App\Modules\DotwAI\Http\Controllers\SearchController::searchHotels
 * @see SRCH-01 Search hotels
 * @see SRCH-04 Caching
 * @see SRCH-05 Multi-room occupancy
 * @see SRCH-06 Filters
 * @see EVNT-02 whatsappMessage in every response
 * @see EVNT-03 suggestedAction in error responses
 */
class SearchHotelsEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private Company $company;
    private Branch $branch;
    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['name' => 'Test Agency']);
        $this->branch = Branch::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Branch',
        ]);
        $this->agent = Agent::factory()->create([
            'branch_id' => $this->branch->id,
            'phone_number' => '99800027',
            'country_code' => '+965',
        ]);

        CompanyDotwCredential::create([
            'company_id' => $this->company->id,
            'dotw_username' => Crypt::encrypt('testuser'),
            'dotw_password' => Crypt::encrypt('testpass'),
            'dotw_company_code' => 'TEST',
            'markup_percent' => 0,
            'is_active' => true,
            'b2b_enabled' => true,
            'b2c_enabled' => false,
        ]);

        // Seed city for resolution
        DotwAICity::create(['code' => '2275', 'name' => 'Dubai', 'country_code' => '14']);

        // Seed local hotels for enrichment
        DotwAIHotel::create([
            'dotw_hotel_id' => '12345',
            'name' => 'Hilton Dubai Creek',
            'city' => 'Dubai',
            'country' => 'UAE',
            'star_rating' => 5,
        ]);
        DotwAIHotel::create([
            'dotw_hotel_id' => '67890',
            'name' => 'Budget Hotel Dubai',
            'city' => 'Dubai',
            'country' => 'UAE',
            'star_rating' => 3,
        ]);
    }

    /**
     * Standard search payload used across multiple tests.
     */
    private function validPayload(): array
    {
        return [
            'city' => 'Dubai',
            'check_in' => now()->addDays(7)->format('Y-m-d'),
            'check_out' => now()->addDays(10)->format('Y-m-d'),
            'occupancy' => [['adults' => 2, 'children_ages' => []]],
            'telephone' => '+96599800027',
        ];
    }

    /**
     * Standard mock response from DotwService::searchHotels.
     */
    private function mockSearchResponse(): array
    {
        return [
            [
                'hotelId' => '12345',
                'rooms' => [
                    [
                        'adults' => '2',
                        'children' => '0',
                        'roomTypes' => [
                            [
                                'code' => 'DBL',
                                'name' => 'Deluxe Room',
                                'rateBasisId' => '1331',
                                'nonRefundable' => 'no',
                                'total' => 45.000,
                                'totalTaxes' => 5.000,
                                'totalMinimumSelling' => 50.000,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'hotelId' => '67890',
                'rooms' => [
                    [
                        'adults' => '2',
                        'children' => '0',
                        'roomTypes' => [
                            [
                                'code' => 'STD',
                                'name' => 'Standard Room',
                                'rateBasisId' => '0',
                                'nonRefundable' => 'yes',
                                'total' => 30.000,
                                'totalTaxes' => 3.000,
                                'totalMinimumSelling' => 33.000,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Mock the DotwService to return canned search results.
     */
    private function mockDotwService(?array $searchResponse = null, ?\Exception $exception = null): void
    {
        // HotelSearchService creates DotwService via `new DotwService($companyId)`
        // We need to mock at the class level using alias mock
        $mock = Mockery::mock('overload:' . DotwService::class);

        if ($exception) {
            $mock->shouldReceive('searchHotels')->andThrow($exception);
        } else {
            $mock->shouldReceive('searchHotels')
                ->andReturn($searchResponse ?? $this->mockSearchResponse());
        }

        // Default getRooms mock for safety
        $mock->shouldReceive('getRooms')->andReturn([]);
        $mock->shouldReceive('getCityList')->andReturn([]);
    }

    public function test_search_hotels_returns_success_with_hotels(): void
    {
        $this->mockDotwService();

        $response = $this->postJson('/api/dotwai/search_hotels', $this->validPayload());

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => ['hotels', 'total_found', 'showing'],
                'whatsappMessage',
                'whatsappOptions',
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data['hotels']);
        $this->assertNotEmpty($response->json('whatsappMessage'));
    }

    public function test_search_hotels_response_envelope_format(): void
    {
        $this->mockDotwService();

        $response = $this->postJson('/api/dotwai/search_hotels', $this->validPayload());

        $response->assertJsonStructure([
            'success',
            'data',
            'whatsappMessage',
            'whatsappOptions',
        ]);

        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertIsString($json['whatsappMessage']);
        $this->assertIsArray($json['whatsappOptions']);
    }

    public function test_search_hotels_results_are_numbered(): void
    {
        $this->mockDotwService();

        $response = $this->postJson('/api/dotwai/search_hotels', $this->validPayload());

        $hotels = $response->json('data.hotels');

        if (!empty($hotels)) {
            $this->assertEquals(1, $hotels[0]['option_number']);
            if (count($hotels) >= 2) {
                $this->assertEquals(2, $hotels[1]['option_number']);
            }
        }
    }

    public function test_search_hotels_caches_results(): void
    {
        $this->mockDotwService();

        Cache::flush();

        $this->postJson('/api/dotwai/search_hotels', $this->validPayload())
            ->assertStatus(200);

        // Cache key uses normalized phone: 96599800027
        $this->assertTrue(
            Cache::has('dotwai:search:96599800027'),
            'Search results should be cached per phone number'
        );
    }

    public function test_search_hotels_filters_by_refundable(): void
    {
        $this->mockDotwService();

        $payload = array_merge($this->validPayload(), ['refundable' => true]);

        $response = $this->postJson('/api/dotwai/search_hotels', $payload);

        $response->assertStatus(200);
        $hotels = $response->json('data.hotels');

        // With refundable=true, the non-refundable hotel (67890) should be filtered out
        if (!empty($hotels)) {
            foreach ($hotels as $hotel) {
                $this->assertTrue(
                    $hotel['is_refundable'],
                    "Hotel {$hotel['name']} should be refundable when filter is applied"
                );
            }
        }
    }

    public function test_search_hotels_validates_required_fields(): void
    {
        // No mock needed -- validation fires before service call
        // But the middleware still needs a valid phone to pass
        $response = $this->postJson('/api/dotwai/search_hotels', [
            'telephone' => '+96599800027',
            // Missing: city, check_in, check_out, occupancy
        ]);

        $response->assertStatus(422);
    }

    public function test_search_hotels_phone_not_found(): void
    {
        $response = $this->postJson('/api/dotwai/search_hotels', [
            'city' => 'Dubai',
            'check_in' => now()->addDays(7)->format('Y-m-d'),
            'check_out' => now()->addDays(10)->format('Y-m-d'),
            'occupancy' => [['adults' => 2]],
            'telephone' => '+9650000000',
        ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $json = $response->json();
        $this->assertEquals('PHONE_NOT_FOUND', $json['error']['code']);
        $this->assertNotEmpty($json['error']['suggestedAction']);
        $this->assertNotEmpty($json['whatsappMessage']);
    }

    public function test_search_hotels_dotw_api_error(): void
    {
        $this->mockDotwService(null, new \RuntimeException('DOTW API timeout'));

        $response = $this->postJson('/api/dotwai/search_hotels', $this->validPayload());

        // Should return error response, not a 500
        $json = $response->json();
        $this->assertFalse($json['success']);
        $this->assertNotEmpty($json['whatsappMessage']);
        $this->assertArrayHasKey('suggestedAction', $json['error']);
    }

    public function test_search_hotels_multi_room_occupancy(): void
    {
        $mock = Mockery::mock('overload:' . DotwService::class);

        $mock->shouldReceive('searchHotels')
            ->once()
            ->withArgs(function (array $params) {
                // Verify that 2 rooms are passed in the params
                return isset($params['rooms']) && count($params['rooms']) === 2;
            })
            ->andReturn($this->mockSearchResponse());

        $mock->shouldReceive('getRooms')->andReturn([]);
        $mock->shouldReceive('getCityList')->andReturn([]);

        $payload = array_merge($this->validPayload(), [
            'occupancy' => [
                ['adults' => 2, 'children_ages' => []],
                ['adults' => 1, 'children_ages' => [5]],
            ],
        ]);

        $response = $this->postJson('/api/dotwai/search_hotels', $payload);

        $response->assertStatus(200);
    }
}
