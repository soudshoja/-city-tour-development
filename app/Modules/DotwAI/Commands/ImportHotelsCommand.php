<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Commands;

use App\Modules\DotwAI\Imports\HotelsImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import DOTW hotel data from Excel/CSV file.
 *
 * Reads hotel static data (ID, name, city, country, star rating, etc.)
 * from a DOTW-provided Excel or CSV file and upserts into the dotwai_hotels
 * table using updateOrCreate keyed on dotw_hotel_id (never truncates).
 *
 * Handles common DOTW Excel column name variations automatically.
 *
 * Usage:
 *   php artisan dotwai:import-hotels /path/to/hotels.xlsx
 *   php artisan dotwai:import-hotels /path/to/hotels.csv --preview
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
     *
     * @return int
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');

        // Resolve file path
        $absolutePath = $this->resolveFilePath($filePath);

        if (!file_exists($absolutePath)) {
            $this->error("File not found: {$absolutePath}");
            return self::FAILURE;
        }

        $this->info("Reading file: {$absolutePath}");

        // Preview mode: show first 5 rows
        if ($this->option('preview')) {
            return $this->previewFile($absolutePath);
        }

        // Import
        $import = new HotelsImport();
        Excel::import($import, $absolutePath);

        $imported = $import->getImportedCount();
        $skipped = $import->getSkippedCount();

        $this->info("Import complete: {$imported} hotels imported/updated.");

        if ($skipped > 0) {
            $this->warn("{$skipped} rows skipped (missing hotel ID).");
        }

        $skippedRows = $import->getSkippedRows();
        if (!empty($skippedRows)) {
            $this->newLine();
            $this->warn('Skipped rows (first 10):');
            foreach (array_slice($skippedRows, 0, 10) as $row) {
                $this->line("  Row {$row['row']}: {$row['reason']}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Resolve the file path to an absolute path.
     *
     * Supports absolute paths and paths relative to storage/app/.
     *
     * @param string $filePath
     * @return string
     */
    private function resolveFilePath(string $filePath): string
    {
        // Already absolute (Unix or Windows)
        if (str_starts_with($filePath, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $filePath)) {
            return $filePath;
        }

        // Relative to storage/app
        return storage_path('app/' . $filePath);
    }

    /**
     * Preview the first 5 rows of the file.
     *
     * @param string $absolutePath
     * @return int
     */
    private function previewFile(string $absolutePath): int
    {
        $rows = Excel::toArray(new HotelsImport(), $absolutePath);

        if (empty($rows) || empty($rows[0])) {
            $this->warn('No data found in file.');
            return self::SUCCESS;
        }

        $data = array_slice($rows[0], 0, 5);

        if (empty($data)) {
            $this->warn('No rows to preview.');
            return self::SUCCESS;
        }

        // Show headers
        $headers = array_keys($data[0]);
        $this->table($headers, $data);

        $this->info('Showing first 5 rows. Run without --preview to import.');

        return self::SUCCESS;
    }
}
