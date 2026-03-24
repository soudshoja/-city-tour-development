<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Commands;

use Illuminate\Console\Command;

/**
 * Sync DOTW static data (cities, countries) from DOTW API.
 *
 * Calls DotwService::getCountryList() and DotwService::getCityList()
 * to populate the dotwai_countries and dotwai_cities tables.
 * Uses updateOrCreate to upsert without truncating existing data.
 *
 * Stub: Full implementation in Task 2.
 *
 * @see FOUND-05, SRCH-03
 */
class SyncStaticDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dotwai:sync-static
                            {--countries-only : Sync only countries}
                            {--cities-only : Sync only cities}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync DOTW static data (cities, countries) from DOTW API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('SyncStaticDataCommand: Full implementation in Task 2.');

        return self::SUCCESS;
    }
}
