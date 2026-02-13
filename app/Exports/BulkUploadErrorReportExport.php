<?php

namespace App\Exports;

use App\Models\BulkUpload;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * BulkUploadErrorReportExport
 *
 * Exports validation errors and flagged rows from a bulk upload with color-coded styling.
 * Red for errors, yellow for flagged rows.
 */
class BulkUploadErrorReportExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
{
    /**
     * The bulk upload instance.
     */
    protected BulkUpload $bulkUpload;

    /**
     * Create a new export instance.
     */
    public function __construct(BulkUpload $bulkUpload)
    {
        $this->bulkUpload = $bulkUpload;
    }

    /**
     * Return column headings.
     */
    public function headings(): array
    {
        return [
            'Row #',
            'Status',
            'Task ID',
            'Client Mobile',
            'Supplier Name',
            'Task Type',
            'Task Status',
            'Invoice Date',
            'Currency',
            'Notes',
            'Errors',
            'Flag Reason',
        ];
    }

    /**
     * Return array of error and flagged rows.
     */
    public function array(): array
    {
        $rows = $this->bulkUpload->rows()
            ->whereIn('status', ['error', 'flagged'])
            ->orderBy('row_number')
            ->get();

        $data = $rows->map(function ($row) {
            $rawData = $row->raw_data ?? [];

            return [
                $row->row_number,
                strtoupper($row->status),
                $rawData['task_id'] ?? '',
                $rawData['client_mobile'] ?? '',
                $rawData['supplier_name'] ?? '',
                $rawData['task_type'] ?? '',
                $rawData['task_status'] ?? '',
                $rawData['invoice_date'] ?? '',
                $rawData['currency'] ?? '',
                $rawData['notes'] ?? '',
                is_array($row->errors) ? implode('; ', $row->errors) : ($row->errors ?? ''),
                $row->flag_reason ?? '',
            ];
        })->toArray();

        // Add summary row at the end
        $data[] = []; // Empty row for spacing
        $data[] = [
            'Summary:',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            "Total errors: {$this->bulkUpload->error_rows}, Total flagged: {$this->bulkUpload->flagged_rows}",
            '',
        ];

        return $data;
    }

    /**
     * Apply styles to the sheet.
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Header row styling
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * Register events for conditional formatting.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Get the highest row number (excluding summary rows)
                $highestRow = $sheet->getHighestRow();

                // Apply conditional formatting to data rows (starting from row 2)
                for ($row = 2; $row <= $highestRow; $row++) {
                    $statusCell = $sheet->getCell("B{$row}");
                    $status = $statusCell->getValue();

                    if ($status === 'ERROR') {
                        // Red background for error rows
                        $sheet->getStyle("A{$row}:L{$row}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'FFC7CE'],
                            ],
                        ]);
                    } elseif ($status === 'FLAGGED') {
                        // Yellow background for flagged rows
                        $sheet->getStyle("A{$row}:L{$row}")->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'FFEB9C'],
                            ],
                        ]);
                    }
                }

                // Make summary row bold
                $summaryRow = $highestRow;
                $sheet->getStyle("A{$summaryRow}:L{$summaryRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                ]);
            },
        ];
    }
}
