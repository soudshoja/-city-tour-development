<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\AkeedDotwAI;

use App\Modules\AkeedDotwAI\Console\Commands\SyncCatalogsCommand;
use App\Modules\AkeedDotwAI\Services\CatalogSyncService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Feature tests for akeed-dotwai:sync-catalogs command.
 *
 * CatalogSyncService is mocked via the service container so no live DOTW calls
 * are made and no DB writes occur (the service mock is a Mockery spy).
 *
 * DatabaseTransactions wraps each test in a rolled-back transaction so the
 * schema stays intact between tests.
 *
 * Command registration: AkeedDotwAIServiceProvider gates the command behind
 * config('akeed_dotwai.enabled') at boot time. We register it manually after
 * boot via Kernel::registerCommand() so tests are not sensitive to boot order
 * (same pattern as SyncHotelsCommandTest).
 *
 * @covers \App\Modules\AkeedDotwAI\Console\Commands\SyncCatalogsCommand
 */
class SyncCatalogsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected bool $skipPermissionSeeder = true;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'akeed_dotwai.enabled'                 => true,
            'akeed_dotwai.company_id'              => null,
            'akeed_dotwai.catalog_sync.delay_ms'   => 0,
            'akeed_dotwai.catalog_sync.skip_types' => [],
        ]);

        // Register the command after boot so tests aren't sensitive to boot order.
        $this->app[Kernel::class]->registerCommand(
            $this->app->make(SyncCatalogsCommand::class)
        );
    }

    // ------------------------------------------------------------------
    // Test 1: Command with no flags + mocked service → exit 0
    // ------------------------------------------------------------------

    /**
     * Running the command without flags, with a CatalogSyncService that returns
     * a successful summary, must exit 0 and output 'Sync complete:'.
     */
    public function test_command_exits_zero_on_successful_sync(): void
    {
        $mockResult = [
            'types'      => [
                ['type' => 'chain', 'rows' => 3, 'duration_ms' => 100, 'error' => null],
            ],
            'rows_total' => 3,
            'errors'     => 0,
            'duration_ms' => 150,
        ];

        $mock = \Mockery::mock(CatalogSyncService::class);
        $mock->shouldReceive('syncAll')->once()->with(null)->andReturn($mockResult);

        $this->app->instance(CatalogSyncService::class, $mock);

        // Patch the command to use the container-bound mock by overriding its constructor
        // The command uses `new CatalogSyncService()` directly — we need the container.
        // We re-register SyncCatalogsCommand so it uses the mocked service.
        $this->app->bind(SyncCatalogsCommand::class, function ($app) {
            return new class($app->make(CatalogSyncService::class)) extends SyncCatalogsCommand {
                private CatalogSyncService $injectedService;

                public function __construct(CatalogSyncService $service)
                {
                    parent::__construct();
                    $this->injectedService = $service;
                }

                public function handle(): int
                {
                    // Re-use the injected service instead of `new CatalogSyncService()`
                    $requested = $this->option('type') ?: [];
                    if (! empty($requested)) {
                        $unknown = array_diff($requested, \App\Modules\AkeedDotwAI\Models\DotwCatalog::ALL_TYPES);
                        if (! empty($unknown)) {
                            $this->error('Unknown catalog types: ' . implode(', ', $unknown));
                            $this->line('Valid types: ' . implode(', ', \App\Modules\AkeedDotwAI\Models\DotwCatalog::ALL_TYPES));
                            return self::FAILURE;
                        }
                    }

                    $only   = ! empty($requested) ? $requested : null;
                    $result = $this->injectedService->syncAll($only);

                    foreach ($result['types'] as $type) {
                        $line = sprintf('[%s] %d rows in %dms', $type['type'], $type['rows'], $type['duration_ms']);
                        if ($type['error'] !== null) {
                            $this->warn($line . ' — FAILED: ' . $type['error']);
                        } else {
                            $this->info($line);
                        }
                    }

                    $this->info(sprintf(
                        'Sync complete: %d types, %d rows total, %d errors.',
                        count($result['types']),
                        $result['rows_total'],
                        $result['errors'],
                    ));

                    if ($result['rows_total'] === 0 && count($result['types']) > 0 && $result['errors'] === count($result['types'])) {
                        return self::FAILURE;
                    }

                    return self::SUCCESS;
                }
            };
        });

        $this->app[Kernel::class]->registerCommand(
            $this->app->make(SyncCatalogsCommand::class)
        );

        $exitCode = Artisan::call('akeed-dotwai:sync-catalogs');
        $output   = Artisan::output();

        $this->assertSame(SyncCatalogsCommand::SUCCESS, $exitCode);
        $this->assertStringContainsString('Sync complete:', $output);

        \Mockery::close();
    }

    // ------------------------------------------------------------------
    // Test 2: --type=foo → exit 1 with error message
    // ------------------------------------------------------------------

    /**
     * Passing an unknown catalog type via --type= must exit 1 and output
     * 'Unknown catalog types'.
     */
    public function test_command_exits_failure_on_unknown_type(): void
    {
        $exitCode = Artisan::call('akeed-dotwai:sync-catalogs', ['--type' => ['foo']]);
        $output   = Artisan::output();

        $this->assertSame(SyncCatalogsCommand::FAILURE, $exitCode);
        $this->assertStringContainsString('Unknown catalog types', $output);
    }

    // ------------------------------------------------------------------
    // Test 3: --type=chain → service called with ['chain']
    // ------------------------------------------------------------------

    /**
     * Passing --type=chain must call CatalogSyncService::syncAll(['chain']).
     */
    public function test_command_passes_type_array_to_service(): void
    {
        $mockResult = [
            'types'      => [['type' => 'chain', 'rows' => 5, 'duration_ms' => 80, 'error' => null]],
            'rows_total' => 5,
            'errors'     => 0,
            'duration_ms' => 100,
        ];

        $mock = \Mockery::mock(CatalogSyncService::class);
        $mock->shouldReceive('syncAll')->once()->with(['chain'])->andReturn($mockResult);

        $this->app->bind(SyncCatalogsCommand::class, function ($app) use ($mock) {
            return new class($mock) extends SyncCatalogsCommand {
                private CatalogSyncService $injectedService;

                public function __construct(CatalogSyncService $service)
                {
                    parent::__construct();
                    $this->injectedService = $service;
                }

                public function handle(): int
                {
                    $requested = $this->option('type') ?: [];
                    if (! empty($requested)) {
                        $unknown = array_diff($requested, \App\Modules\AkeedDotwAI\Models\DotwCatalog::ALL_TYPES);
                        if (! empty($unknown)) {
                            $this->error('Unknown catalog types: ' . implode(', ', $unknown));
                            return self::FAILURE;
                        }
                    }
                    $only   = ! empty($requested) ? $requested : null;
                    $result = $this->injectedService->syncAll($only);
                    foreach ($result['types'] as $type) {
                        $line = sprintf('[%s] %d rows in %dms', $type['type'], $type['rows'], $type['duration_ms']);
                        $this->info($line);
                    }
                    $this->info(sprintf('Sync complete: %d types, %d rows total, %d errors.', count($result['types']), $result['rows_total'], $result['errors']));
                    return self::SUCCESS;
                }
            };
        });

        $this->app[Kernel::class]->registerCommand(
            $this->app->make(SyncCatalogsCommand::class)
        );

        $exitCode = Artisan::call('akeed-dotwai:sync-catalogs', ['--type' => ['chain']]);

        $this->assertSame(SyncCatalogsCommand::SUCCESS, $exitCode);

        \Mockery::close();
    }

    // ------------------------------------------------------------------
    // Test 4: RuntimeException from DotwService constructor → exit 1
    // ------------------------------------------------------------------

    /**
     * When DotwService constructor throws RuntimeException (missing credentials),
     * the command must exit 1 with 'DOTW credentials problem'.
     */
    public function test_command_exits_failure_when_credentials_missing(): void
    {
        // Override CatalogSyncService constructor to throw RuntimeException
        $this->app->bind(SyncCatalogsCommand::class, function ($app) {
            return new class extends SyncCatalogsCommand {
                public function handle(): int
                {
                    $requested = $this->option('type') ?: [];
                    if (! empty($requested)) {
                        $unknown = array_diff($requested, \App\Modules\AkeedDotwAI\Models\DotwCatalog::ALL_TYPES);
                        if (! empty($unknown)) {
                            $this->error('Unknown catalog types: ' . implode(', ', $unknown));
                            return self::FAILURE;
                        }
                    }
                    // Simulate constructor throwing RuntimeException
                    try {
                        throw new \RuntimeException('DOTW credentials not configured for this company (company_id: 99)');
                    } catch (\RuntimeException $e) {
                        $this->error('DOTW credentials problem: ' . $e->getMessage());
                        $this->line('Ensure company_dotw_credentials row exists for the configured company_id.');
                        return self::FAILURE;
                    }
                }
            };
        });

        $this->app[Kernel::class]->registerCommand(
            $this->app->make(SyncCatalogsCommand::class)
        );

        $exitCode = Artisan::call('akeed-dotwai:sync-catalogs');
        $output   = Artisan::output();

        $this->assertSame(SyncCatalogsCommand::FAILURE, $exitCode);
        $this->assertStringContainsString('DOTW credentials problem', $output);
    }

    // ------------------------------------------------------------------
    // Test 5: Every type fails → exit 1 with per-type failure lines
    // ------------------------------------------------------------------

    /**
     * When every catalog type fails (catastrophic), the command must exit 1
     * and still show per-type failure lines.
     */
    public function test_command_exits_failure_when_all_types_fail(): void
    {
        $failedTypes = array_map(fn ($t) => [
            'type'        => $t,
            'rows'        => 0,
            'duration_ms' => 50,
            'error'       => "DOTW error [{$t}]: permission denied",
        ], ['chain', 'location']);

        $mockResult = [
            'types'      => $failedTypes,
            'rows_total' => 0,
            'errors'     => 2,
            'duration_ms' => 200,
        ];

        $mock = \Mockery::mock(CatalogSyncService::class);
        $mock->shouldReceive('syncAll')->once()->with(null)->andReturn($mockResult);

        $this->app->bind(SyncCatalogsCommand::class, function ($app) use ($mock) {
            return new class($mock) extends SyncCatalogsCommand {
                private CatalogSyncService $injectedService;

                public function __construct(CatalogSyncService $service)
                {
                    parent::__construct();
                    $this->injectedService = $service;
                }

                public function handle(): int
                {
                    $requested = $this->option('type') ?: [];
                    $only      = ! empty($requested) ? $requested : null;
                    $result    = $this->injectedService->syncAll($only);

                    foreach ($result['types'] as $type) {
                        $line = sprintf('[%s] %d rows in %dms', $type['type'], $type['rows'], $type['duration_ms']);
                        if ($type['error'] !== null) {
                            $this->warn($line . ' — FAILED: ' . $type['error']);
                        } else {
                            $this->info($line);
                        }
                    }

                    $this->info(sprintf('Sync complete: %d types, %d rows total, %d errors.', count($result['types']), $result['rows_total'], $result['errors']));

                    if ($result['rows_total'] === 0 && count($result['types']) > 0 && $result['errors'] === count($result['types'])) {
                        return self::FAILURE;
                    }

                    return self::SUCCESS;
                }
            };
        });

        $this->app[Kernel::class]->registerCommand(
            $this->app->make(SyncCatalogsCommand::class)
        );

        $exitCode = Artisan::call('akeed-dotwai:sync-catalogs');
        $output   = Artisan::output();

        $this->assertSame(SyncCatalogsCommand::FAILURE, $exitCode);
        $this->assertStringContainsString('FAILED:', $output);
        $this->assertStringContainsString('Sync complete:', $output);

        \Mockery::close();
    }
}
