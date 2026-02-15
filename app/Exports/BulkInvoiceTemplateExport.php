<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * BulkInvoiceTemplateExport
 *
 * Generates Excel template for bulk invoice uploads.
 * Links existing tasks to clients and creates invoices with payments.
 */
class BulkInvoiceTemplateExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
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
     * Return empty data array for template.
     */
    public function array(): array
    {
        // Return empty rows as this is a template
        return [];
    }

    /**
     * Return column headings.
     */
    public function headings(): array
    {
        return [
            'invoice_date',
            'client_mobile',
            'task_reference',
            'task_status',
            'selling_price',
            'payment_reference',
            'notes',
        ];
    }

    /**
     * Apply styles to the sheet.
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
            ],
        ];
    }
}
