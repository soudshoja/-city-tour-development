<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\AkeedDotwAI;

use App\Modules\AkeedDotwAI\Models\DotwCatalog;
use App\Modules\AkeedDotwAI\Services\StarRatingResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unit tests for StarRatingResolver.
 *
 * Uses DatabaseTransactions so each test wraps DB writes in a rolled-back
 * transaction. dotw_catalogs must already exist in the test DB (run
 * 'AKEED_DOTWAI_ENABLED=true php artisan migrate' once before running).
 *
 * Each test calls StarRatingResolver::clearCache() in setUp() so the static
 * in-process map is rebuilt fresh from the seeded rows.
 *
 * @covers \App\Modules\AkeedDotwAI\Services\StarRatingResolver
 */
class StarRatingResolverTest extends TestCase
{
    use DatabaseTransactions;

    protected bool $skipPermissionSeeder = true;

    private StarRatingResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        config(['akeed_dotwai.enabled' => true]);

        // Reset the static map so each test gets a fresh DB-backed state.
        StarRatingResolver::clearCache();

        $this->resolver = new StarRatingResolver;
    }

    /** @test */
    public function resolve_returns_int_for_known_5_star_code(): void
    {
        DotwCatalog::create([
            'type' => DotwCatalog::TYPE_CLASSIFICATION,
            'code' => '561',
            'name' => '5 Star',
            'synced_at' => now(),
        ]);

        $this->assertSame(5, $this->resolver->resolve('561'));
    }

    /** @test */
    public function resolve_handles_4_star_dash_format(): void
    {
        DotwCatalog::create([
            'type' => DotwCatalog::TYPE_CLASSIFICATION,
            'code' => '559',
            'name' => '4-Star Deluxe',
            'synced_at' => now(),
        ]);

        $this->assertSame(4, $this->resolver->resolve('559'));
    }

    /** @test */
    public function resolve_handles_bare_digit_name(): void
    {
        DotwCatalog::create([
            'type' => DotwCatalog::TYPE_CLASSIFICATION,
            'code' => '555',
            'name' => '3',
            'synced_at' => now(),
        ]);

        $this->assertSame(3, $this->resolver->resolve('555'));
    }

    /** @test */
    public function resolve_returns_null_for_unknown_code(): void
    {
        DotwCatalog::create([
            'type' => DotwCatalog::TYPE_CLASSIFICATION,
            'code' => '561',
            'name' => '5 Star',
            'synced_at' => now(),
        ]);

        $this->assertNull($this->resolver->resolve('999'));
    }

    /** @test */
    public function resolve_returns_null_for_unparseable_name(): void
    {
        DotwCatalog::create([
            'type' => DotwCatalog::TYPE_CLASSIFICATION,
            'code' => '600',
            'name' => 'Apartment',
            'synced_at' => now(),
        ]);

        $this->assertNull($this->resolver->resolve('600'));
    }

    /** @test */
    public function resolve_returns_null_for_null_input(): void
    {
        $this->assertNull($this->resolver->resolve(null));
    }

    /** @test */
    public function resolve_returns_null_for_empty_string(): void
    {
        $this->assertNull($this->resolver->resolve(''));
    }

    /** @test */
    public function resolve_returns_null_for_zero_int(): void
    {
        $this->assertNull($this->resolver->resolve(0));
    }

    /** @test */
    public function resolve_accepts_int_input(): void
    {
        DotwCatalog::create([
            'type' => DotwCatalog::TYPE_CLASSIFICATION,
            'code' => '561',
            'name' => '5 Star',
            'synced_at' => now(),
        ]);

        $this->assertSame(5, $this->resolver->resolve(561));
    }

    /** @test */
    public function resolve_returns_null_when_catalog_empty(): void
    {
        // No DotwCatalog rows seeded — empty catalog.
        $this->assertNull($this->resolver->resolve('561'));
    }

    /** @test */
    public function resolve_caches_after_first_call(): void
    {
        DotwCatalog::create([
            'type' => DotwCatalog::TYPE_CLASSIFICATION,
            'code' => '561',
            'name' => '5 Star',
            'synced_at' => now(),
        ]);

        // First call loads the map.
        $this->assertSame(5, $this->resolver->resolve('561'));

        // Delete the row from the DB — the in-process cache should still serve the result.
        DotwCatalog::where('type', DotwCatalog::TYPE_CLASSIFICATION)->where('code', '561')->delete();
        $this->assertSame(5, $this->resolver->resolve('561'));

        // After clearCache(), the map is rebuilt from DB — which now has no row.
        StarRatingResolver::clearCache();
        $this->assertNull($this->resolver->resolve('561'));
    }

    /** @test */
    public function resolve_rejects_out_of_range_digit(): void
    {
        DotwCatalog::create([
            'type' => DotwCatalog::TYPE_CLASSIFICATION,
            'code' => '888',
            'name' => '9 Star',
            'synced_at' => now(),
        ]);

        // Regex matches digit 9 but 9 > 5, so resolver returns null.
        $this->assertNull($this->resolver->resolve('888'));
    }
}
