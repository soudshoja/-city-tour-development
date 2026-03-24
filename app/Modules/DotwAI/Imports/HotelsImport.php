<?php

declare(strict_types=1);

namespace App\Modules\DotwAI\Imports;

use App\Modules\DotwAI\Models\DotwAIHotel;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Maatwebsite Excel import class for DOTW hotel data.
 *
 * Handles common DOTW Excel column name variations automatically via
 * a normalization map. Uses updateOrCreate keyed on dotw_hotel_id
 * (upsert pattern -- never truncates existing data).
 *
 * Skips rows where dotw_hotel_id is empty/null.
 *
 * @see FOUND-04
 */
class HotelsImport implements ToModel, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    /**
     * Column name normalization map.
     *
     * Maps common DOTW Excel header variations to canonical field names.
     *
     * @var array<string, array<int, string>>
     */
    private const COLUMN_MAP = [
        'dotw_hotel_id' => ['hotel_id', 'hotelid', 'id', 'productid', 'product_id', 'hotel_code', 'dotw_hotel_id'],
        'name' => ['hotel_name', 'hotelname', 'name'],
        'city' => ['city', 'city_name', 'cityname'],
        'country' => ['country', 'country_name', 'countryname'],
        'star_rating' => ['stars', 'star_rating', 'starrating', 'classification', 'star'],
        'address' => ['address', 'hotel_address'],
        'latitude' => ['latitude', 'lat'],
        'longitude' => ['longitude', 'lng', 'lon'],
    ];

    /**
     * Count of imported/updated records.
     */
    private int $importedCount = 0;

    /**
     * Count of skipped records.
     */
    private int $skippedCount = 0;

    /**
     * Details of skipped rows for reporting.
     *
     * @var array<int, array{row: int, reason: string}>
     */
    private array $skippedRows = [];

    /**
     * Current row number (for error reporting).
     */
    private int $currentRow = 1;

    /**
     * Create a model instance from a row of data.
     *
     * Uses updateOrCreate keyed on dotw_hotel_id to upsert.
     * Returns null for skipped rows (Maatwebsite handles this gracefully).
     *
     * @param array<string, mixed> $row
     * @return DotwAIHotel|null
     */
    public function model(array $row): ?DotwAIHotel
    {
        $this->currentRow++;

        // Normalize column names
        $normalized = $this->normalizeRow($row);

        // Skip rows without hotel ID
        $hotelId = $normalized['dotw_hotel_id'] ?? null;

        if (empty($hotelId)) {
            $this->skippedCount++;
            $this->skippedRows[] = [
                'row' => $this->currentRow,
                'reason' => 'Missing hotel ID',
            ];
            return null;
        }

        // Upsert: updateOrCreate keyed on dotw_hotel_id
        $hotel = DotwAIHotel::updateOrCreate(
            ['dotw_hotel_id' => (string) $hotelId],
            [
                'name' => (string) ($normalized['name'] ?? ''),
                'city' => (string) ($normalized['city'] ?? ''),
                'country' => (string) ($normalized['country'] ?? ''),
                'star_rating' => !empty($normalized['star_rating']) ? (int) $normalized['star_rating'] : null,
                'address' => !empty($normalized['address']) ? (string) $normalized['address'] : null,
                'latitude' => !empty($normalized['latitude']) ? (float) $normalized['latitude'] : null,
                'longitude' => !empty($normalized['longitude']) ? (float) $normalized['longitude'] : null,
            ]
        );

        $this->importedCount++;

        return $hotel;
    }

    /**
     * Normalize a row's column names to canonical names.
     *
     * Maps common DOTW Excel header variations (e.g., "HotelName" -> "name")
     * to the canonical field names used by DotwAIHotel.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach (self::COLUMN_MAP as $canonical => $variations) {
            foreach ($variations as $variant) {
                // Try exact match and lowercase match
                if (isset($row[$variant])) {
                    $normalized[$canonical] = $row[$variant];
                    break;
                }

                $lower = strtolower($variant);
                if (isset($row[$lower])) {
                    $normalized[$canonical] = $row[$lower];
                    break;
                }
            }
        }

        return $normalized;
    }

    /**
     * Batch insert size for performance.
     *
     * @return int
     */
    public function batchSize(): int
    {
        return 500;
    }

    /**
     * Chunk reading size for memory efficiency.
     *
     * @return int
     */
    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * Get the count of imported/updated records.
     *
     * @return int
     */
    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    /**
     * Get the count of skipped records.
     *
     * @return int
     */
    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    /**
     * Get details of skipped rows.
     *
     * @return array<int, array{row: int, reason: string}>
     */
    public function getSkippedRows(): array
    {
        return $this->skippedRows;
    }
}
