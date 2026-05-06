<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\AkeedDotwAI;

use App\Modules\AkeedDotwAI\Models\DotwPrebook;
use App\Modules\AkeedDotwAI\Services\FuzzyMatcherService;
use App\Modules\AkeedDotwAI\Services\HotelSearchService;
use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAICountry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Feature tests for the `searchDotwHotelRooms` GraphQL query.
 *
 * Hits POST /graphql. HotelSearchService is mocked via the service container
 * so all tests remain fast and hermetic (no real DOTW calls).
 *
 * Architectural invariant asserted on every happy-path:
 *   DotwPrebook::count() === 0 — Phase 31 resolver must NEVER create prebooks.
 *
 * @covers \App\Modules\AkeedDotwAI\GraphQL\Queries\SearchDotwHotelRooms
 */
class SearchDotwHotelRoomsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    /** GraphQL query fragment used in all tests */
    private string $gqlQuery;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable module for all tests unless a test overrides this
        config(['akeed_dotwai.enabled' => true]);

        // Seed minimal static-data so FuzzyMatcherService can resolve city/country
        // even when the real HotelSearchService is used (module-disabled test).
        DotwAICity::create(['code' => '2275', 'name' => 'Dubai', 'country_code' => '14']);
        DotwAICountry::create(['code' => '66', 'name' => 'Kuwait', 'nationality_name' => 'Kuwaiti']);

        $this->gqlQuery = <<<'GRAPHQL'
            query SearchRooms($input: DotwHotelSearchInput!) {
                searchDotwHotelRooms(input: $input) {
                    success
                    status
                    message
                    hotelOptions {
                        name
                        city_name
                        hotel_id
                    }
                    data {
                        hotel_name
                        hotel_id
                        hotel_address
                        star_rating
                        city_name
                        rooms {
                            room_name
                            room_type_code
                            rate_basis_id
                            rate_basis_desc
                            meal_type
                            max_occupancy
                            twin
                            browse_allocation_details
                            displayed_price
                            original_total_fare
                            currency
                            is_refundable
                            is_apr
                            cancel_policies {
                                fromDate
                                charge
                            }
                            tariff_notes
                            specials {
                                type
                                name
                            }
                            min_stay
                            min_stay_date
                        }
                    }
                }
            }
        GRAPHQL;
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
            'occupancy' => [['adults' => 2, 'childrenAges' => []]],
            'bookingType' => 'B2C',
        ], $overrides);
    }

    private function postGraphQL(array $input): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/graphql', [
            'query' => $this->gqlQuery,
            'variables' => ['input' => $input],
        ]);
    }

    /** Build a mock HotelSearchService that returns canned search + browse results. */
    private function mockSearchService(
        array $searchReturn,
        ?array $browseReturn = null
    ): HotelSearchService {
        $mock = Mockery::mock(HotelSearchService::class);

        $mock->shouldReceive('search')->andReturn($searchReturn);

        if ($browseReturn !== null) {
            $mock->shouldReceive('browse')->andReturn($browseReturn);
        }

        $this->app->instance(HotelSearchService::class, $mock);

        return $mock;
    }

    private function singleHotelSearchResult(): array
    {
        return [
            'status' => 'hotel_found',
            'hotels' => [
                [
                    'hotel_id' => '12345',
                    'hotel_name' => 'Fake Dubai Hotel',
                    'hotel_address' => 'Sheikh Zayed Road',
                    'star_rating' => 4,
                    'city_name' => 'Dubai',
                ],
            ],
            'city_code' => '2275',
            'nationality_code' => '66',
            'residence_code' => '66',
        ];
    }

    private function singleRoomBrowseResult(): array
    {
        return [
            'status' => 'rates_found',
            'rates' => [
                [
                    'room_name' => 'Standard Double',
                    'room_type_code' => 'STD',
                    'rate_basis_id' => '0',
                    'rate_basis_desc' => 'Room Only',
                    'browse_allocation_details' => 'ALLOC-XYZ-123',
                    'meal_type' => 'Room Only',
                    'max_occupancy' => 2,
                    'twin' => false,
                    'original_total_fare' => 200.0,
                    'currency' => '520',
                    'displayed_price' => 240.0,
                    'is_refundable' => true,
                    'is_apr' => false,
                    'cancellation_rules' => [
                        [
                            'fromDate' => '2026-07-25',
                            'toDate' => '2026-08-01',
                            'charge' => 100.0,
                            'chargeType' => 'Fixed',
                            'cancelRestricted' => false,
                        ],
                    ],
                    'specials' => [],
                    'tariff_notes' => '',
                    'min_stay' => null,
                    'min_stay_date' => null,
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Test cases
    // -------------------------------------------------------------------------

    public function test_happy_path_returns_hotel_found_with_rooms(): void
    {
        $this->mockSearchService(
            $this->singleHotelSearchResult(),
            $this->singleRoomBrowseResult()
        );

        $response = $this->postGraphQL($this->defaultInput());

        $response->assertStatus(200)
            ->assertJsonPath('data.searchDotwHotelRooms.success', true)
            ->assertJsonPath('data.searchDotwHotelRooms.status', 'hotel_found');

        $rooms = $response->json('data.searchDotwHotelRooms.data.rooms');
        $this->assertNotEmpty($rooms, 'data.rooms must be non-empty on happy path');

        // Each room must have browse_allocation_details populated
        foreach ($rooms as $room) {
            $this->assertNotEmpty(
                $room['browse_allocation_details'],
                'browse_allocation_details must be populated on every room'
            );
        }

        // Architectural invariant: Phase 31 resolver must NEVER create a prebook
        $this->assertSame(0, DotwPrebook::count(), 'DotwPrebook::count() must be 0 after browse-only path');
    }

    public function test_multiple_hotels_returns_multiple_hotels_found_with_hotel_options(): void
    {
        $multiHotelResult = [
            'status' => 'hotel_found',
            'hotels' => [
                ['hotel_id' => '11111', 'hotel_name' => 'Hilton Dubai', 'city_name' => 'Dubai'],
                ['hotel_id' => '22222', 'hotel_name' => 'Hilton Garden Dubai', 'city_name' => 'Dubai'],
            ],
            'city_code' => '2275',
            'nationality_code' => '66',
            'residence_code' => '66',
        ];
        $this->mockSearchService($multiHotelResult);

        $response = $this->postGraphQL($this->defaultInput(['hotel' => 'Hilton']));

        $response->assertStatus(200)
            ->assertJsonPath('data.searchDotwHotelRooms.status', 'multiple_hotels_found')
            ->assertJsonPath('data.searchDotwHotelRooms.success', true);

        $hotelOptions = $response->json('data.searchDotwHotelRooms.hotelOptions');
        $this->assertIsArray($hotelOptions);
        $this->assertCount(2, $hotelOptions);

        // Architectural invariant
        $this->assertSame(0, DotwPrebook::count(), 'DotwPrebook::count() must be 0 after multiple-hotels path');
    }

    public function test_city_not_found_returns_error_envelope(): void
    {
        $this->mockSearchService([
            'status' => 'city_not_found',
            'message' => 'Could not resolve city: XYZ',
        ]);

        $response = $this->postGraphQL($this->defaultInput(['city' => 'XYZ']));

        $response->assertStatus(200)
            ->assertJsonPath('data.searchDotwHotelRooms.success', false)
            ->assertJsonPath('data.searchDotwHotelRooms.status', 'city_not_found');
    }

    public function test_module_disabled_returns_graphql_error(): void
    {
        config(['akeed_dotwai.enabled' => false]);

        // Module disabled gate runs before HotelSearchService; no mock needed.
        $response = $this->postGraphQL($this->defaultInput());

        $response->assertStatus(200);

        // Lighthouse wraps RuntimeException as a GraphQL error
        $errors = $response->json('errors');
        $this->assertNotEmpty($errors, 'GraphQL errors array must be non-empty when module is disabled');

        $errorMessages = array_column($errors, 'message');
        $combined = implode(' ', $errorMessages);
        $this->assertStringContainsString(
            'AkeedDotwAI module is disabled',
            $combined,
            'Error message must mention the module being disabled'
        );

        // Architectural invariant
        $this->assertSame(0, DotwPrebook::count(), 'DotwPrebook::count() must be 0 when module is disabled');
    }

    public function test_missing_credentials_returns_error_status(): void
    {
        $this->mockSearchService([
            'status' => 'error',
            'message' => 'credentials missing',
        ]);

        $response = $this->postGraphQL($this->defaultInput());

        $response->assertStatus(200)
            ->assertJsonPath('data.searchDotwHotelRooms.success', false)
            ->assertJsonPath('data.searchDotwHotelRooms.status', 'error');

        $message = $response->json('data.searchDotwHotelRooms.message');
        $this->assertStringContainsString('credentials', strtolower($message ?? ''));

        // Architectural invariant
        $this->assertSame(0, DotwPrebook::count(), 'DotwPrebook::count() must be 0 on credential-error path');
    }
}
