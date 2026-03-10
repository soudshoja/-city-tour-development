<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * BulkInvoiceImport
 *
 * Parses Excel files into associative arrays for bulk invoice uploads.
 * Uses WithHeadingRow to automatically map column headers to array keys.
 * Does not create models - just extracts data for validation and processing.
 */
class BulkInvoiceImport implements ToArray, WithHeadingRow
{
    /**
     * @var array
     */
    protected $rows = [];

    /**
     * Parse the Excel file into an array.
     *
     * @return void
     */
    public function array(array $array)
    {
        $this->rows = $array;
    }

    /**
     * Get the parsed rows.
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Get the header keys from the first row.
     */
    public function getHeaders(): array
    {
        if (empty($this->rows)) {
            return [];
        }

        return array_keys($this->rows[0]);
    }
}
