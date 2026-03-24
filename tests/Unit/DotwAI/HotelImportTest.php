<?php

declare(strict_types=1);

namespace Tests\Unit\DotwAI;

use App\Modules\DotwAI\Models\DotwAIHotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Unit tests for the dotwai:import-hotels artisan command.
 *
 * Verifies CSV/Excel import creates records, handles upserts
 * correctly, and skips rows without a hotel ID.
 *
 * @covers \App\Modules\DotwAI\Commands\ImportHotelsCommand
 * @covers \App\Modules\DotwAI\Imports\HotelsImport
 * @see FOUND-04
 */
class HotelImportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $skipPermissionSeeder = true;

    /**
     * Path to the test fixture CSV file.
     */
    private function fixturePath(): string
    {
        return base_path('tests/Fixtures/DotwAI/hotels_sample.csv');
    }

    public function test_import_hotels_creates_records_from_csv(): void
    {
        $this->assertFileExists($this->fixturePath());

        Artisan::call('dotwai:import-hotels', ['file' => $this->fixturePath()]);

        $this->assertEquals(3, DotwAIHotel::count());

        // Verify specific records
        $hilton = DotwAIHotel::where('dotw_hotel_id', '1001')->first();
        $this->assertNotNull($hilton);
        $this->assertEquals('Hilton Dubai Creek', $hilton->name);
        $this->assertEquals('Dubai', $hilton->city);
        $this->assertEquals(5, $hilton->star_rating);

        $marriott = DotwAIHotel::where('dotw_hotel_id', '1002')->first();
        $this->assertNotNull($marriott);
        $this->assertEquals('JW Marriott Marquis Dubai', $marriott->name);

        $gardenInn = DotwAIHotel::where('dotw_hotel_id', '1003')->first();
        $this->assertNotNull($gardenInn);
        $this->assertEquals('Hilton Garden Inn Kuwait', $gardenInn->name);
        $this->assertEquals('Kuwait City', $gardenInn->city);
    }

    public function test_import_hotels_upserts_existing_records(): void
    {
        // First import
        Artisan::call('dotwai:import-hotels', ['file' => $this->fixturePath()]);
        $this->assertEquals(3, DotwAIHotel::count());

        // Second import of the same file -- should not create duplicates
        Artisan::call('dotwai:import-hotels', ['file' => $this->fixturePath()]);
        $this->assertEquals(3, DotwAIHotel::count());

        // Create an updated CSV with a modified hotel name
        $updatedCsvPath = base_path('tests/Fixtures/DotwAI/hotels_updated.csv');
        file_put_contents($updatedCsvPath, implode("\n", [
            'hotel_id,hotel_name,city,country,star_rating',
            '1001,Hilton Dubai Creek UPDATED,Dubai,UAE,5',
            '1002,JW Marriott Marquis Dubai,Dubai,UAE,5',
            '1003,Hilton Garden Inn Kuwait,Kuwait City,Kuwait,4',
        ]));

        try {
            Artisan::call('dotwai:import-hotels', ['file' => $updatedCsvPath]);
            $this->assertEquals(3, DotwAIHotel::count());

            $hilton = DotwAIHotel::where('dotw_hotel_id', '1001')->first();
            $this->assertEquals('Hilton Dubai Creek UPDATED', $hilton->name);
        } finally {
            // Cleanup temp file
            @unlink($updatedCsvPath);
        }
    }

    public function test_import_hotels_skips_rows_without_hotel_id(): void
    {
        // Create a CSV with one row missing hotel_id
        $csvPath = base_path('tests/Fixtures/DotwAI/hotels_with_missing_id.csv');
        file_put_contents($csvPath, implode("\n", [
            'hotel_id,hotel_name,city,country,star_rating',
            '1001,Hilton Dubai Creek,Dubai,UAE,5',
            ',Missing ID Hotel,Dubai,UAE,3',
            '1003,Hilton Garden Inn Kuwait,Kuwait City,Kuwait,4',
        ]));

        try {
            Artisan::call('dotwai:import-hotels', ['file' => $csvPath]);

            // Only 2 valid rows should be imported (the one with empty hotel_id is skipped)
            $this->assertEquals(2, DotwAIHotel::count());

            $this->assertNotNull(DotwAIHotel::where('dotw_hotel_id', '1001')->first());
            $this->assertNotNull(DotwAIHotel::where('dotw_hotel_id', '1003')->first());
            $this->assertNull(DotwAIHotel::where('name', 'Missing ID Hotel')->first());
        } finally {
            @unlink($csvPath);
        }
    }
}
