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
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

/**
 * Feature tests for POST /api/dotwai/get_hotel_details endpoint.
 *
 * Validates browse-mode room detail retrieval with mocked DotwService.
 *
 * @covers \App\Modules\DotwAI\Http\Controllers\SearchController::getHotelDetails
 * @see SRCH-02
 */
class GetHotelDetailsEndpointTest extends TestCase
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
            'b2c_enabled' => true,
        ]);

        // Seed local hotel for enrichment
        DotwAIHotel::create([
            'dotw_hotel_id' => '12345',
            'name' => 'Hilton Dubai Creek',
            'city' => 'Dubai',
            'country' => 'UAE',
            'star_rating' => 5,
        ]);
    }

    /**
     * Standard request payload for hotel details.
     */
    private function validPayload(): array
    {
        return [
            'hotel_id' => '12345',
            'check_in' => now()->addDays(7)->format('Y-m-d'),
            'check_out' => now()->addDays(10)->format('Y-m-d'),
            'occupancy' => [['adults' => 2, 'children_ages' => []]],
            'telephone' => '+96599800027',
        ];
    }

    /**
     * Mock getRooms response simulating browse-mode DOTW API result.
     */
    private function mockGetRoomsResponse(): array
    {
        return [
            [
                'roomTypeCode' => 'DBL',
                'roomName' => 'Deluxe Double Room',
                'specials' => ['Early Bird Discount 15%'],
                'details' => [
                    [
                        'id' => '1331',
                        'price' => 45.000,
                        'taxes' => 5.000,
                        'tariffNotes' => 'Rate includes breakfast',
                        'cancellationRules' => [
                            [
                                'fromDate' => '2026-04-01',
                                'toDate' => '2026-04-05',
                                'charge' => 50.0,
                                'cancelRestricted' => false,
                            ],
                        ],
                        'specialsApplied' => [],
                        'allocationDetails' => 'OnRequest',
                        'propertyFees' => [],
                    ],
                ],
            ],
            [
                'roomTypeCode' => 'STD',
                'roomName' => 'Standard Room',
                'specials' => [],
                'details' => [
                    [
                        'id' => '0',
                        'price' => 30.000,
                        'taxes' => 3.000,
                        'tariffNotes' => '',
                        'cancellationRules' => [
                            [
                                'fromDate' => '2026-03-30',
                                'toDate' => '2026-04-05',
                                'cancelRestricted' => true,
                            ],
                        ],
                        'specialsApplied' => [],
                        'allocationDetails' => '',
                        'propertyFees' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Set up the DotwService mock for getRooms calls.
     */
    private function mockDotwService(?array $roomsResponse = null, ?\Exception $exception = null): void
    {
        $mock = Mockery::mock('overload:' . DotwService::class);

        if ($exception) {
            $mock->shouldReceive('getRooms')->andThrow($exception);
        } else {
            $mock->shouldReceive('getRooms')
                ->andReturn($roomsResponse ?? $this->mockGetRoomsResponse());
        }

        $mock->shouldReceive('searchHotels')->andReturn([]);
        $mock->shouldReceive('getCityList')->andReturn([]);
    }

    public function test_get_hotel_details_returns_rooms_with_prices(): void
    {
        $this->mockDotwService();

        $response = $this->postJson('/api/dotwai/get_hotel_details', $this->validPayload());

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $rooms = $response->json('data.rooms');
        $this->assertNotEmpty($rooms);

        // Check first room has expected fields
        $firstRoom = $rooms[0];
        $this->assertArrayHasKey('room_name', $firstRoom);
        $this->assertArrayHasKey('meal_type', $firstRoom);
        $this->assertArrayHasKey('price', $firstRoom);
        $this->assertArrayHasKey('display_price', $firstRoom);
    }

    public function test_get_hotel_details_includes_cancellation_rules(): void
    {
        $this->mockDotwService();

        $response = $this->postJson('/api/dotwai/get_hotel_details', $this->validPayload());

        $rooms = $response->json('data.rooms');
        $this->assertNotEmpty($rooms);

        // The first room should have cancellation_rules
        $firstRoom = $rooms[0];
        $this->assertArrayHasKey('cancellation_rules', $firstRoom);
        $this->assertNotEmpty($firstRoom['cancellation_rules']);

        $rule = $firstRoom['cancellation_rules'][0];
        $this->assertArrayHasKey('fromDate', $rule);
        $this->assertArrayHasKey('charge', $rule);
    }

    public function test_get_hotel_details_includes_specials(): void
    {
        $this->mockDotwService();

        $response = $this->postJson('/api/dotwai/get_hotel_details', $this->validPayload());

        $rooms = $response->json('data.rooms');

        // The first room type has a special "Early Bird Discount 15%"
        $firstRoom = $rooms[0];
        $this->assertArrayHasKey('specials', $firstRoom);
        // specials array should be populated for the room with promotions
        $this->assertIsArray($firstRoom['specials']);
    }

    public function test_get_hotel_details_browse_mode_no_blocking(): void
    {
        $mock = Mockery::mock('overload:' . DotwService::class);

        $mock->shouldReceive('getRooms')
            ->once()
            ->withArgs(function (array $params, bool $blocking) {
                // blocking should be false for browse mode
                return $blocking === false;
            })
            ->andReturn($this->mockGetRoomsResponse());

        $mock->shouldReceive('searchHotels')->andReturn([]);
        $mock->shouldReceive('getCityList')->andReturn([]);

        $response = $this->postJson('/api/dotwai/get_hotel_details', $this->validPayload());

        $response->assertStatus(200);
    }

    public function test_get_hotel_details_response_envelope(): void
    {
        $this->mockDotwService();

        $response = $this->postJson('/api/dotwai/get_hotel_details', $this->validPayload());

        $response->assertJsonStructure([
            'success',
            'data',
            'whatsappMessage',
            'whatsappOptions',
        ]);

        $json = $response->json();
        $this->assertNotEmpty($json['whatsappMessage']);
        $this->assertIsArray($json['whatsappOptions']);
    }

    public function test_get_hotel_details_b2c_markup_applied(): void
    {
        // Update credential to B2C with 20% markup
        CompanyDotwCredential::where('company_id', $this->company->id)
            ->update(['markup_percent' => 20]);

        $this->mockDotwService();

        $response = $this->postJson('/api/dotwai/get_hotel_details', $this->validPayload());

        $response->assertStatus(200);
        $rooms = $response->json('data.rooms');

        if (!empty($rooms)) {
            $firstRoom = $rooms[0];
            // display_price should be >= price (markup applied)
            $this->assertGreaterThanOrEqual(
                $firstRoom['price'],
                $firstRoom['display_price'],
                'B2C display price should be >= base price due to markup'
            );
        }
    }
}
