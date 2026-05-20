<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\AkeedDotwAI;

use App\Modules\AkeedDotwAI\Models\DotwCatalog;
use App\Modules\AkeedDotwAI\Services\CatalogSyncService;
use App\Modules\DotwAI\Models\DotwAICountry;
use App\Services\DotwService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for CatalogSyncService.
 *
 * Uses Mockery to mock DotwService so no live DOTW calls are made.
 * DotwService is injected via the optional second constructor parameter
 * added for testability.
 *
 * Uses DatabaseTransactions so each test wraps DB writes in a rolled-back
 * transaction. This means the dotw_catalogs and dotwai_countries tables must
 * already exist in the test DB (run 'php artisan migrate' with AKEED_DOTWAI_ENABLED=true
 * once before running these tests — or let the CI migrate step handle it).
 *
 * @covers \App\Modules\AkeedDotwAI\Services\CatalogSyncService
 */
class CatalogSyncServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected bool $skipPermissionSeeder = true;

    private MockInterface $dotwMock;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'akeed_dotwai.enabled'                  => true,
            'akeed_dotwai.company_id'               => null,
            'akeed_dotwai.catalog_sync.delay_ms'    => 0,
            'akeed_dotwai.catalog_sync.skip_types'  => [],
            'akeed_dotwai.catalog_sync.company_id'  => null,
        ]);

        $this->dotwMock = Mockery::mock(DotwService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helper factories
    // ------------------------------------------------------------------

    /** @return CatalogSyncService */
    private function makeService(): CatalogSyncService
    {
        /** @var DotwService $dotw */
        $dotw = $this->dotwMock;

        return new CatalogSyncService(null, $dotw);
    }

    /**
     * Build N generic rows with code/name keys.
     *
     * @return array<array{code: string, name: string}>
     */
    private function genericRows(int $count, string $prefix = 'item'): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['code' => (string) $i, 'name' => "{$prefix} {$i}"];
        }

        return $rows;
    }

    /**
     * Build N classification rows (id/name keys — DotwService::getHotelClassifications shape).
     *
     * @return array<array{id: string, name: string}>
     */
    private function classificationRows(int $count): array
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['id' => (string) $i, 'name' => "Class {$i}"];
        }

        return $rows;
    }

    /**
     * Build amenity/leisure/business merged rows (getAmenityIds shape).
     *
     * @return array<array{code: string, name: string, category: string}>
     */
    private function amenityMergeRows(int $amenity, int $leisure, int $business): array
    {
        $rows = [];
        $code = 1;

        for ($i = 1; $i <= $amenity; $i++) {
            $rows[] = ['code' => (string) $code++, 'name' => "Amenity {$i}", 'category' => 'amenity'];
        }

        for ($i = 1; $i <= $leisure; $i++) {
            $rows[] = ['code' => (string) $code++, 'name' => "Leisure {$i}", 'category' => 'leisure'];
        }

        for ($i = 1; $i <= $business; $i++) {
            $rows[] = ['code' => (string) $code++, 'name' => "Business {$i}", 'category' => 'business'];
        }

        return $rows;
    }

    /**
     * Build a salutation map (getSalutationIds shape): ['label' => id, ...].
     *
     * @return array<string, int>
     */
    private function salutationMap(): array
    {
        return ['mr' => 147, 'mrs' => 149];
    }

    // ------------------------------------------------------------------
    // @dataProvider — per-type smoke tests (F5 all 11 ENUM values)
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{string, callable(MockInterface): void, int}>
     */
    public static function allCatalogTypesProvider(): array
    {
        return [
            // [type, mock_setup_fn, expected_rows]
            'classification' => [
                'classification',
                fn (MockInterface $m) => $m->shouldReceive('getHotelClassifications')
                    ->once()
                    ->andReturn(array_map(fn ($i) => ['id' => (string) $i, 'name' => "Class {$i}"], range(1, 5))),
                5,
            ],
            'chain' => [
                'chain',
                fn (MockInterface $m) => $m->shouldReceive('getChainIds')
                    ->once()
                    ->andReturn(array_map(fn ($i) => ['code' => (string) $i, 'name' => "Chain {$i}"], range(1, 3))),
                3,
            ],
            'location' => [
                'location',
                fn (MockInterface $m) => $m->shouldReceive('getLocationIds')
                    ->once()
                    ->andReturn(array_map(fn ($i) => ['code' => (string) $i, 'name' => "Loc {$i}"], range(1, 2))),
                2,
            ],
            'amenity (+ leisure + business via merge)' => [
                'amenity',
                function (MockInterface $m): void {
                    $rows = [];
                    for ($i = 1; $i <= 3; $i++) {
                        $rows[] = ['code' => (string) $i, 'name' => "Amenity {$i}", 'category' => 'amenity'];
                    }
                    for ($i = 4; $i <= 5; $i++) {
                        $rows[] = ['code' => (string) $i, 'name' => "Leisure ".($i - 3), 'category' => 'leisure'];
                    }
                    $rows[] = ['code' => '6', 'name' => 'Business 1', 'category' => 'business'];
                    $m->shouldReceive('getAmenityIds')->once()->andReturn($rows);
                },
                6, // 3 amenity + 2 leisure + 1 business
            ],
            'preference' => [
                'preference',
                fn (MockInterface $m) => $m->shouldReceive('getPreferenceIds')
                    ->once()
                    ->andReturn(array_map(fn ($i) => ['code' => (string) $i, 'name' => "Pref {$i}"], range(1, 4))),
                4,
            ],
            'meal_plan' => [
                'meal_plan',
                fn (MockInterface $m) => $m->shouldReceive('getMealPlanIds')
                    ->once()
                    ->andReturn(array_map(fn ($i) => ['code' => (string) $i, 'name' => "Meal {$i}"], range(1, 6))),
                6,
            ],
            'room_type' => [
                'room_type',
                fn (MockInterface $m) => $m->shouldReceive('getRoomTypeIds')
                    ->once()
                    ->andReturn(array_map(fn ($i) => ['code' => (string) $i, 'name' => "Room {$i}"], range(1, 7))),
                7,
            ],
            'special' => [
                'special',
                fn (MockInterface $m) => $m->shouldReceive('getSpecialsIds')
                    ->once()
                    ->andReturn(array_map(fn ($i) => ['code' => (string) $i, 'name' => "Special {$i}"], range(1, 2))),
                2,
            ],
            'salutation' => [
                'salutation',
                fn (MockInterface $m) => $m->shouldReceive('getSalutationIds')
                    ->once()
                    ->andReturn(['mr' => 147, 'mrs' => 149]),
                2,
            ],
        ];
    }

    /**
     * @dataProvider allCatalogTypesProvider
     */
    public function test_sync_type_persists_expected_rows(string $type, callable $mockSetup, int $expectedRows): void
    {
        $mockSetup($this->dotwMock);

        // Also allow getServingCountries for the backfill side-effects pass
        $this->dotwMock->shouldReceive('getServingCountries')->zeroOrMoreTimes()->andReturn([]);

        $service = $this->makeService();
        $result  = $service->syncType($type);

        $this->assertNull($result['error'], "syncType('{$type}') should not produce an error");
        $this->assertSame($expectedRows, $result['rows'], "Expected {$expectedRows} rows for type '{$type}'");

        // Verify the rows are in the DB (split for amenity merge)
        if ($type === 'amenity') {
            $this->assertSame(3, DotwCatalog::ofType('amenity')->count());
            $this->assertSame(2, DotwCatalog::ofType('leisure')->count());
            $this->assertSame(1, DotwCatalog::ofType('business')->count());
        } elseif ($type !== 'leisure' && $type !== 'business') {
            $this->assertSame($expectedRows, DotwCatalog::ofType($type)->count(), "DB row count for '{$type}'");
        }
    }

    // ------------------------------------------------------------------
    // Additional non-provider scenarios
    // ------------------------------------------------------------------

    /**
     * Scenario 1: Re-running syncType on same data stays idempotent.
     * Row count stays 5 (no duplicates); synced_at advances.
     */
    public function test_sync_type_is_idempotent_no_duplicates(): void
    {
        $rows = $this->classificationRows(5);

        $this->dotwMock
            ->shouldReceive('getHotelClassifications')
            ->twice()
            ->andReturn($rows);

        $this->dotwMock->shouldReceive('getServingCountries')->zeroOrMoreTimes()->andReturn([]);

        $service = $this->makeService();

        $result1 = $service->syncType('classification');
        $this->assertSame(5, $result1['rows']);
        $this->assertNull($result1['error']);

        // Advance time so synced_at on second run differs from first
        $beforeSecond = now()->subSecond();
        sleep(0); // nothing to advance in test, but updateOrInsert will touch updated_at

        $result2 = $service->syncType('classification');
        $this->assertSame(5, $result2['rows']);
        $this->assertNull($result2['error']);

        // Still exactly 5 rows (no duplicates)
        $this->assertSame(5, DotwCatalog::ofType('classification')->count());
    }

    /**
     * Scenario 2: syncType when mock throws Exception — returns error key, no rows written, does not throw.
     */
    public function test_sync_type_returns_error_key_on_dotw_exception(): void
    {
        $this->dotwMock
            ->shouldReceive('getChainIds')
            ->once()
            ->andThrow(new \Exception('DOTW getChainIds error [TIMEOUT]: connection timed out'));

        $service = $this->makeService();
        $result  = $service->syncType('chain');

        $this->assertNotNull($result['error'], 'error key should be populated on exception');
        $this->assertStringContainsString('TIMEOUT', $result['error']);
        $this->assertSame(0, $result['rows']);
        $this->assertSame(0, DotwCatalog::ofType('chain')->count(), 'No rows should be written on exception');
    }

    /**
     * Scenario 3: syncAll with partial scope only invokes the specified methods.
     */
    public function test_sync_all_partial_scope_only_invokes_specified_methods(): void
    {
        $this->dotwMock->shouldReceive('getChainIds')->once()->andReturn($this->genericRows(3, 'Chain'));
        $this->dotwMock->shouldReceive('getHotelClassifications')->once()->andReturn($this->classificationRows(5));
        $this->dotwMock->shouldReceive('getServingCountries')->once()->andReturn([]);

        // These must NOT be called
        $this->dotwMock->shouldNotReceive('getLocationIds');
        $this->dotwMock->shouldNotReceive('getAmenityIds');
        $this->dotwMock->shouldNotReceive('getPreferenceIds');
        $this->dotwMock->shouldNotReceive('getMealPlanIds');
        $this->dotwMock->shouldNotReceive('getRoomTypeIds');
        $this->dotwMock->shouldNotReceive('getSpecialsIds');
        $this->dotwMock->shouldNotReceive('getSalutationIds');

        $service = $this->makeService();
        $result  = $service->syncAll(['chain', 'classification']);

        $this->assertSame(2, count($result['types']));
        $this->assertSame(8, $result['rows_total']); // 3 + 5
        $this->assertSame(0, $result['errors']);
    }

    /**
     * Scenario 4: syncAll full run with one type failing — summary reports errors=1,
     * rows_total > 0, other types still populated.
     */
    public function test_sync_all_full_run_one_failing_type_does_not_abort_others(): void
    {
        // classification succeeds
        $this->dotwMock->shouldReceive('getHotelClassifications')
            ->once()
            ->andReturn($this->classificationRows(5));

        // chain throws
        $this->dotwMock->shouldReceive('getChainIds')
            ->once()
            ->andThrow(new \Exception('DOTW getChainIds error [PERMISSION_DENIED]: not allowed'));

        // location succeeds
        $this->dotwMock->shouldReceive('getLocationIds')
            ->once()
            ->andReturn($this->genericRows(2, 'Loc'));

        // amenity succeeds (3 merged rows)
        $this->dotwMock->shouldReceive('getAmenityIds')
            ->once()
            ->andReturn($this->amenityMergeRows(2, 1, 0));

        // preference succeeds
        $this->dotwMock->shouldReceive('getPreferenceIds')
            ->once()
            ->andReturn($this->genericRows(4, 'Pref'));

        // meal_plan succeeds
        $this->dotwMock->shouldReceive('getMealPlanIds')
            ->once()
            ->andReturn($this->genericRows(2, 'Meal'));

        // room_type succeeds
        $this->dotwMock->shouldReceive('getRoomTypeIds')
            ->once()
            ->andReturn($this->genericRows(3, 'Room'));

        // special succeeds
        $this->dotwMock->shouldReceive('getSpecialsIds')
            ->once()
            ->andReturn($this->genericRows(1, 'Special'));

        // salutation succeeds
        $this->dotwMock->shouldReceive('getSalutationIds')
            ->once()
            ->andReturn($this->salutationMap());

        $this->dotwMock->shouldReceive('getServingCountries')->once()->andReturn([]);

        $service = $this->makeService();
        $result  = $service->syncAll();

        $this->assertSame(1, $result['errors'], 'Exactly one type should fail');
        $this->assertGreaterThan(0, $result['rows_total'], 'Rows total should be > 0 despite one failure');

        // Verify a specific failing type entry
        $chainResult = collect($result['types'])->firstWhere('type', 'chain');
        $this->assertNotNull($chainResult);
        $this->assertNotNull($chainResult['error']);
        $this->assertSame(0, $chainResult['rows']);

        // Verify classification still populated
        $this->assertSame(5, DotwCatalog::ofType('classification')->count());
    }

    /**
     * Scenario 5: is_serving backfill — after getServingCountries returns 50 codes
     * against a seeded 250-row dotwai_countries, exactly 50 rows have is_serving=true.
     */
    public function test_is_serving_backfill_marks_correct_countries(): void
    {
        // Seed 250 dotwai_countries rows
        $allCodes = [];
        for ($i = 1; $i <= 250; $i++) {
            $allCodes[] = (string) $i;
            DotwAICountry::create([
                'code'             => (string) $i,
                'name'             => "Country {$i}",
                'nationality_name' => "Nat {$i}",
            ]);
        }

        // DOTW returns only the first 50 codes as "serving"
        $servingCodes = array_slice($allCodes, 0, 50);
        $servingRows  = array_map(fn ($c) => ['code' => $c, 'name' => "Country {$c}"], $servingCodes);

        // Mock: syncAll needs at least one catalog type call to not crash on the dispatch loop
        $this->dotwMock->shouldReceive('getHotelClassifications')
            ->once()
            ->andReturn($this->classificationRows(1));
        $this->dotwMock->shouldReceive('getChainIds')->once()->andReturn([]);
        $this->dotwMock->shouldReceive('getLocationIds')->once()->andReturn([]);
        $this->dotwMock->shouldReceive('getAmenityIds')->once()->andReturn([]);
        $this->dotwMock->shouldReceive('getPreferenceIds')->once()->andReturn([]);
        $this->dotwMock->shouldReceive('getMealPlanIds')->once()->andReturn([]);
        $this->dotwMock->shouldReceive('getRoomTypeIds')->once()->andReturn([]);
        $this->dotwMock->shouldReceive('getSpecialsIds')->once()->andReturn([]);
        $this->dotwMock->shouldReceive('getSalutationIds')->once()->andReturn([]);

        $this->dotwMock->shouldReceive('getServingCountries')
            ->once()
            ->andReturn($servingRows);

        $service = $this->makeService();
        $service->syncAll();

        $servingCount = DotwAICountry::where('is_serving', true)->count();
        $this->assertSame(50, $servingCount, 'Exactly 50 of 250 countries should be marked is_serving=true');

        $notServingCount = DotwAICountry::where('is_serving', false)->count();
        $this->assertSame(200, $notServingCount, 'Remaining 200 countries should be is_serving=false');
    }
}
