<?php

declare(strict_types=1);

namespace Tests\Feature\DotwAI;

use App\Models\Agent;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyDotwCredential;
use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAICountry;
use App\Services\DotwService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

/**
 * Feature tests for GET /api/dotwai/get_cities endpoint.
 *
 * Validates city list retrieval with local-first strategy
 * and DOTW API fallback.
 *
 * @covers \App\Modules\DotwAI\Http\Controllers\SearchController::getCities
 * @see SRCH-03
 */
class GetCitiesEndpointTest extends TestCase
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

        // Seed country for resolution
        DotwAICountry::create([
            'code' => '14',
            'name' => 'UAE',
            'nationality_name' => 'Emirati',
        ]);

        // Seed local cities
        DotwAICity::create(['code' => '2275', 'name' => 'Dubai', 'country_code' => '14']);
        DotwAICity::create(['code' => '2276', 'name' => 'Abu Dhabi', 'country_code' => '14']);
        DotwAICity::create(['code' => '2277', 'name' => 'Sharjah', 'country_code' => '14']);
    }

    public function test_get_cities_returns_city_list(): void
    {
        $response = $this->getJson('/api/dotwai/get_cities?country=UAE&telephone=+96599800027');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $cities = $response->json('data.cities');
        $this->assertNotEmpty($cities);
        $this->assertGreaterThanOrEqual(3, count($cities));

        // Verify city structure
        $firstCity = $cities[0];
        $this->assertArrayHasKey('code', $firstCity);
        $this->assertArrayHasKey('name', $firstCity);
    }

    public function test_get_cities_from_local_cache(): void
    {
        // DotwService should NOT be called because local data exists
        $mock = Mockery::mock('overload:' . DotwService::class);
        $mock->shouldReceive('getCityList')->never();
        $mock->shouldReceive('searchHotels')->andReturn([]);
        $mock->shouldReceive('getRooms')->andReturn([]);

        $response = $this->getJson('/api/dotwai/get_cities?country=UAE&telephone=+96599800027');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_get_cities_response_envelope(): void
    {
        $response = $this->getJson('/api/dotwai/get_cities?country=UAE&telephone=+96599800027');

        $response->assertJsonStructure([
            'success',
            'data',
            'whatsappMessage',
            'whatsappOptions',
        ]);

        $json = $response->json();
        $this->assertNotEmpty($json['whatsappMessage']);
    }

    public function test_get_cities_country_not_found(): void
    {
        $response = $this->getJson('/api/dotwai/get_cities?country=Atlantis&telephone=+96599800027');

        $json = $response->json();
        $this->assertFalse($json['success']);
        $this->assertNotEmpty($json['whatsappMessage']);
        $this->assertArrayHasKey('suggestedAction', $json['error']);
    }
}
