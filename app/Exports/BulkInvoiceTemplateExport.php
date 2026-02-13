<?php

namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * BulkInvoiceTemplateExport
 *
 * Generates a multi-sheet Excel template for bulk invoice uploads.
 * Sheet 1: Upload template with column headers
 * Sheet 2: Client list for reference
 */
class BulkInvoiceTemplateExport implements WithMultipleSheets
{
    /**
     * @var int
     */
    protected $companyId;

    /**
     * Create a new export instance.
     *
     * @return void
     */
    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    /**
     * Return an array of sheets.
     */
    public function sheets(): array
    {
        return [
            new BulkInvoiceTemplateSheet,
            new ClientListSheet($this->companyId),
        ];
    }
}
