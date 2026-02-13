<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * BulkInvoiceTemplateSheet
 *
 * First sheet containing the upload template with styled headers.
 */
class BulkInvoiceTemplateSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
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
            'task_id',
            'client_mobile',
            'supplier_name',
            'task_type',
            'task_status',
            'invoice_date',
            'currency',
            'notes',
        ];
    }

    /**
     * Apply styles to the sheet.
     *
     * @return array
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

    /**
     * Return the sheet title.
     */
    public function title(): string
    {
        return 'Upload Template';
    }
}
