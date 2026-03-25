<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * BulkInvoiceTemplateExport
 *
 * Generates Excel template for bulk invoice uploads.
 * Links existing tasks to clients and creates invoices with payments.
 * Header row is protected to prevent accidental edits.
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
        return [];
    }

    /**
     * Return column headings.
     */
    public function headings(): array
    {
        return [
            'invoice_date',
            'client_name',
            'client_mobile',
            'task_reference',
            'task_status',
            'passenger_name',
            'selling_price',
            'payment_reference',
            'notes',
        ];
    }

    /**
     * Apply styles and sheet protection.
     * Header row (row 1) is locked; data rows (2-1000) are unlocked for editing.
     */
    public function styles(Worksheet $sheet)
    {
        // Enable sheet protection (password: simple deterrent, not real security)
        $sheet->getProtection()->setSheet(true);
        $sheet->getProtection()->setPassword('bulk');
        $sheet->getProtection()->setInsertRows(true);

        // Unlock data rows (A2:G1000) so users can type freely
        $sheet->getStyle('A2:I1000')->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);

        // Header row stays locked by default (Protection::PROTECTION_INHERIT = locked when sheet is protected)

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
