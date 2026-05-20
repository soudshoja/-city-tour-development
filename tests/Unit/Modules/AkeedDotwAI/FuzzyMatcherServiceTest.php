<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\AkeedDotwAI;

use App\Modules\AkeedDotwAI\Services\FuzzyMatcherService;
use App\Modules\DotwAI\Models\DotwAICity;
use App\Modules\DotwAI\Models\DotwAICountry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for AkeedDotwAI FuzzyMatcherService.
 *
 * Uses dotwai_cities / dotwai_countries (static-data tables owned by DotwAI
 * module). Rows are seeded in setUp; RefreshDatabase resets between tests.
 *
 * @covers \App\Modules\AkeedDotwAI\Services\FuzzyMatcherService
 */
class FuzzyMatcherServiceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    private FuzzyMatcherService $matcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matcher = new FuzzyMatcherService;

        // Seed minimal city rows
        DotwAICity::create(['code' => '2275', 'name' => 'Dubai', 'country_code' => '14']);
        DotwAICity::create(['code' => '3456', 'name' => 'Kuwait City', 'country_code' => '66']);

        // Seed minimal country rows
        DotwAICountry::create(['code' => '66', 'name' => 'Kuwait', 'nationality_name' => 'Kuwaiti']);
        DotwAICountry::create(['code' => '14', 'name' => 'United Arab Emirates', 'nationality_name' => 'Emirati']);
    }

    // -------------------------------------------------------------------------
    // resolveCityCode
    // -------------------------------------------------------------------------

    public function test_resolve_city_code_exact_match(): void
    {
        $code = $this->matcher->resolveCityCode('Dubai');

        $this->assertSame('2275', $code);
    }

    public function test_resolve_city_code_typo_within_threshold(): void
    {
        // 'Dubay' → Levenshtein distance 1 from 'Dubai' (well within threshold 3)
        $code = $this->matcher->resolveCityCode('Dubay');

        $this->assertSame('2275', $code);
    }

    public function test_resolve_city_code_unknown_city_returns_null(): void
    {
        $code = $this->matcher->resolveCityCode('XYZNotACity');

        $this->assertNull($code);
    }

    public function test_resolve_city_code_empty_string_returns_null(): void
    {
        $code = $this->matcher->resolveCityCode('');

        $this->assertNull($code);
    }

    // -------------------------------------------------------------------------
    // resolveCountryCode
    // -------------------------------------------------------------------------

    public function test_resolve_country_code_by_name(): void
    {
        $code = $this->matcher->resolveCountryCode('Kuwait');

        $this->assertSame('66', $code);
    }

    public function test_resolve_country_code_by_nationality_name(): void
    {
        // 'Kuwaiti' matches on nationality_name column
        $code = $this->matcher->resolveCountryCode('Kuwaiti');

        $this->assertSame('66', $code);
    }

    public function test_resolve_country_code_unknown_returns_null(): void
    {
        $code = $this->matcher->resolveCountryCode('Atlantis');

        $this->assertNull($code);
    }
}
