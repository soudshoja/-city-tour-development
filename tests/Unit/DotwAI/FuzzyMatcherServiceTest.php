<?php

declare(strict_types=1);

namespace Tests\Unit\DotwAI;

use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAIHotel;
use App\Modules\DotwAI\Services\FuzzyMatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for FuzzyMatcherService.
 *
 * Verifies the two-tier matching approach (LIKE + Levenshtein fallback)
 * for hotels and cities.
 *
 * @covers \App\Modules\DotwAI\Services\FuzzyMatcherService
 * @see FOUND-05
 */
class FuzzyMatcherServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private FuzzyMatcherService $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new FuzzyMatcherService();

        // Seed test hotels
        DotwAIHotel::create([
            'dotw_hotel_id' => '1001',
            'name' => 'Hilton Dubai Creek',
            'city' => 'Dubai',
            'country' => 'UAE',
            'star_rating' => 5,
        ]);
        DotwAIHotel::create([
            'dotw_hotel_id' => '1002',
            'name' => 'JW Marriott Marquis Dubai',
            'city' => 'Dubai',
            'country' => 'UAE',
            'star_rating' => 5,
        ]);
        DotwAIHotel::create([
            'dotw_hotel_id' => '1003',
            'name' => 'Hilton Garden Inn Kuwait',
            'city' => 'Kuwait City',
            'country' => 'Kuwait',
            'star_rating' => 4,
        ]);

        // Seed test cities
        DotwAICity::create(['code' => '2275', 'name' => 'Dubai', 'country_code' => '14']);
        DotwAICity::create(['code' => '3456', 'name' => 'Kuwait City', 'country_code' => '66']);
    }

    public function test_finds_hotel_by_exact_name(): void
    {
        $results = $this->matcher->findHotels('Hilton Dubai Creek');

        $this->assertCount(1, $results);
        $this->assertEquals('1001', $results->first()->dotw_hotel_id);
    }

    public function test_finds_hotel_by_partial_name(): void
    {
        $results = $this->matcher->findHotels('Hilton');

        // Should find both "Hilton Dubai Creek" and "Hilton Garden Inn Kuwait"
        $this->assertGreaterThanOrEqual(2, $results->count());
        $hotelIds = $results->pluck('dotw_hotel_id')->toArray();
        $this->assertContains('1001', $hotelIds);
        $this->assertContains('1003', $hotelIds);
    }

    public function test_finds_hotel_filtered_by_city(): void
    {
        $results = $this->matcher->findHotels('Hilton', 'Dubai');

        $this->assertCount(1, $results);
        $this->assertEquals('1001', $results->first()->dotw_hotel_id);
        $this->assertEquals('Hilton Dubai Creek', $results->first()->name);
    }

    public function test_levenshtein_fallback_for_typo(): void
    {
        // "hiltn dubai" is close to "hilton dubai creek" -- Levenshtein fallback
        $results = $this->matcher->findHotels('Hiltn Dubai Creek');

        $this->assertGreaterThanOrEqual(1, $results->count());
        $hotelIds = $results->pluck('dotw_hotel_id')->toArray();
        $this->assertContains('1001', $hotelIds);
    }

    public function test_returns_empty_for_no_match(): void
    {
        $results = $this->matcher->findHotels('NonexistentHotelXYZ12345');

        $this->assertTrue($results->isEmpty());
    }

    public function test_resolves_city_by_name(): void
    {
        $city = $this->matcher->resolveCity('Dubai');

        $this->assertNotNull($city);
        $this->assertEquals('2275', $city->code);
        $this->assertEquals('Dubai', $city->name);
    }

    public function test_resolves_city_with_typo(): void
    {
        // "Dubay" is distance 1 from "Dubai" -- within threshold 3
        $city = $this->matcher->resolveCity('Dubay');

        $this->assertNotNull($city);
        $this->assertEquals('2275', $city->code);
    }
}
