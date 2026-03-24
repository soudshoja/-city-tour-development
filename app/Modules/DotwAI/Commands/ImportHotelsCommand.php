<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Commands;

use Illuminate\Console\Command;

/**
 * Import DOTW hotel data from Excel/CSV file.
 *
 * Reads hotel static data (ID, name, city, country, star rating, etc.)
 * from a DOTW-provided Excel or CSV file and upserts into the dotwai_hotels
 * table. Uses updateOrCreate keyed on dotw_hotel_id (never truncates).
 *
 * Stub: Full implementation in Task 2.
 *
 * @see FOUND-04
 */
class ImportHotelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dotwai:import-hotels
                            {file : Path to the Excel/CSV file (absolute or relative to storage/app)}
                            {--preview : Preview the first 5 rows without importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import DOTW hotel data from Excel/CSV file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('ImportHotelsCommand: Full implementation in Task 2.');

        return self::SUCCESS;
    }
}
