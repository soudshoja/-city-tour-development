<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\AkeedDotwAI;

use App\Modules\AkeedDotwAI\Models\DotwPrebook;
use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAICountry;
use App\Services\DotwService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['akeed_dotwai.enabled' => true, 'akeed_dotwai.company_id' => 1]);

        DotwAICity::create(['code' => '364', 'name' => 'Dubai']);
        DotwAICountry::create(['code' => '66', 'name' => 'Kuwait', 'nationality_name' => 'Kuwaiti']);
    }

    private function validInput(): array
    {
        return [
            'telephone' => '+96599800027',
            'city' => 'Dubai',
            'guestNationality' => 'Kuwait',
            'checkIn' => '2026-06-15',
            'checkOut' => '2026-06-17',
            'occupancy' => [['adults' => 2, 'childrenAges' => []]],
            'bookingType' => 'b2c',
        ];
    }

    public function test_disabled_returns_503(): void
    {
        config(['akeed_dotwai.enabled' => false]);

        $response = $this->postJson('/api/akeed-dotwai/search-hotels', $this->validInput());

        $response->assertStatus(503);
        $response->assertJson(['success' => false, 'status' => 'error']);
        $this->assertSame(0, DotwPrebook::count());
    }

    public function test_validation_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/akeed-dotwai/search-hotels', [
            'telephone' => '+96599800027',
            'bookingType' => 'b2c',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, DotwPrebook::count());
    }

    public function test_city_not_found_returns_envelope(): void
    {
        $mock = Mockery::mock(DotwService::class);
        $mock->shouldReceive('searchHotels')->andReturn([]);
        $this->app->bind(DotwService::class, fn () => $mock);

        $input = $this->validInput();
        $input['city'] = 'XYZNotARealCity';

        $response = $this->postJson('/api/akeed-dotwai/search-hotels', $input);

        $response->assertOk();
        $response->assertJson(['success' => false, 'status' => 'city_not_found']);
        $this->assertSame(0, DotwPrebook::count());
    }

    public function test_credentials_missing_returns_error_envelope(): void
    {
        $mock = Mockery::mock(DotwService::class);
        $mock->shouldReceive('searchHotels')
            ->andThrow(new \RuntimeException('DOTW credentials not configured for company 1'));
        $this->app->bind(DotwService::class, fn () => $mock);

        $response = $this->postJson('/api/akeed-dotwai/search-hotels', $this->validInput());

        $response->assertOk();
        $response->assertJson(['success' => false, 'status' => 'error']);
        $this->assertStringContainsString('credentials', strtolower($response->json('message')));
        $this->assertSame(0, DotwPrebook::count());
    }
}
