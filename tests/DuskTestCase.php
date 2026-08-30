<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;
use Tests\Concerns\GuardsTestDatabaseIsolation;

abstract class DuskTestCase extends BaseTestCase
{
    use GuardsTestDatabaseIsolation;

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Same hard safety rail as tests/TestCase.php: a Dusk test that mixes in
     * DatabaseMigrations/RefreshDatabase runs migrate:fresh from inside
     * parent::setUp() exactly like the Feature/Unit base class does, and
     * Laravel\Dusk\TestCase does not extend Tests\TestCase, so without this
     * override a Dusk test would bypass the guard entirely and could
     * migrate:fresh straight into laravel_testing / map_data_citytour.
     */
    protected function setUp(): void
    {
        // See tests/TestCase.php's setUp() for why this ordering (guard, then parent::setUp())
        // matters -- same rationale applies here. guardTestDatabaseIsolation() derives
        // DB_AUDIT_DATABASE internally -- no separate call needed here.
        $this->guardTestDatabaseIsolation();

        parent::setUp();
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--no-sandbox',
            '--disable-dev-shm-usage',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
