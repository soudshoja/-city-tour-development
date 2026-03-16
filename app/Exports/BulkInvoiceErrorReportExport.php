<?php

namespace App\Exports;

use App\Models\BulkInvoice;
use App\Models\Task;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * BulkInvoiceErrorReportExport
 *
 * Exports validation errors and flagged rows from a bulk invoice.
 * Three-section layout: Upload Data | Matched Results | Issues, separated by visual dividers.
 */
class BulkInvoiceErrorReportExport implements FromArray, WithEvents, WithHeadings, WithStyles
{
    protected BulkInvoice $bulkInvoice;

    protected int $dataRowCount = 0;

    public function __construct(BulkInvoice $bulkInvoice)
    {
        $this->bulkInvoice = $bulkInvoice;
    }

    /**
     * Column headings — split into three clear sections.
     */
    public function headings(): array
    {
        return [
            // Section 1: Upload Data (A-K)
            '#',
            'STATUS',
            'INVOICE DATE',
            'CLIENT NAME',
            'CLIENT MOBILE',
            'TASK REF',
            'TASK STATUS',
            'PASSENGER',
            'AMOUNT',
            'PAYMENT REF',
            'NOTES',
            // Divider column (L)
            '',
            // Section 2: Validation Results (M-O)
            'MATCHED TASK',
            'MATCHED CLIENT',
            'MATCHED PAYMENT',
            // Divider column (P)
            '',
            // Section 3: Issues (Q)
            'ISSUES',
        ];
    }

    /**
     * Build data rows from error + flagged BulkInvoiceRows.
     */
    public function array(): array
    {
        $rows = $this->bulkInvoice->rows()
            ->whereIn('status', ['error', 'flagged'])
            ->orderBy('row_number')
            ->get();

        $data = [];

        foreach ($rows as $row) {
            $rawData = $row->raw_data ?? [];

            // Enrich: load matched task info
            $taskInfo = '';
            if ($row->task_id) {
                $task = Task::with('supplier')->find($row->task_id);
                if ($task) {
                    $type = ucfirst($task->type ?? '');
                    $supplier = $task->supplier?->name ?? '';
                    $taskInfo = "#{$task->id}";
                    if ($type || $supplier) {
                        $taskInfo .= " ({$type}" . ($supplier ? " · {$supplier}" : '') . ")";
                    }
                }
            }

            // Enrich: load matched client info
            $clientInfo = '';
            if ($row->client_id) {
                $client = \App\Models\Client::find($row->client_id);
                if ($client) {
                    $clientInfo = ($client->full_name ?: '') . " (#{$client->id})";
                }
            }

            // Enrich: load matched payment info
            $paymentInfo = '';
            if ($row->payment_id) {
                $payment = \App\Models\Payment::find($row->payment_id);
                if ($payment) {
                    $paymentInfo = $payment->voucher_number ?? "#{$payment->id}";
                    if ($payment->status) {
                        $paymentInfo .= " [{$payment->status}]";
                    }
                }
            }

            // Merge errors + flag_reason into one combined issues column
            $issues = [];

            if (is_array($row->errors) && count($row->errors) > 0) {
                foreach ($row->errors as $err) {
                    $issues[] = preg_replace('/^Row \d+: /', '', $err);
                }
            }

            if (! empty($row->flag_reason)) {
                $issues[] = $row->flag_reason;
            }

            // Format with bullets
            $issuesText = '';
            if (count($issues) === 1) {
                $issuesText = $issues[0];
            } elseif (count($issues) > 1) {
                $issuesText = collect($issues)->map(fn ($i) => "• {$i}")->implode("\n");
            }

            $data[] = [
                $row->row_number,
                strtoupper($row->status),
                $rawData['invoice_date'] ?? '',
                $rawData['client_name'] ?? '',
                $rawData['client_mobile'] ?? '',
                $rawData['task_reference'] ?? '',
                $rawData['task_status'] ?? '',
                $rawData['passenger_name'] ?? '',
                $rawData['selling_price'] ?? '',
                $rawData['payment_reference'] ?? '',
                $rawData['notes'] ?? '',
                '', // divider column L
                $taskInfo,
                $clientInfo,
                $paymentInfo,
                '', // divider column P
                $issuesText,
            ];
        }

        $this->dataRowCount = count($data);

        // Spacer
        $data[] = array_fill(0, 17, '');

        // Summary section
        $errorCount = $this->bulkInvoice->error_rows;
        $flaggedCount = $this->bulkInvoice->flagged_rows;
        $validCount = $this->bulkInvoice->valid_rows;
        $totalCount = $this->bulkInvoice->total_rows;

        $data[] = ['REPORT SUMMARY', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        $data[] = ['', 'Total Rows', $totalCount, '', 'Valid', $validCount, '', 'Errors', $errorCount, '', 'Flagged', $flaggedCount, '', '', '', '', ''];
        $data[] = ['', 'File', $this->bulkInvoice->original_filename, '', 'Uploaded', $this->bulkInvoice->created_at?->format('d M Y, H:i'), '', '', '', '', '', '', '', '', '', '', ''];

        return $data;
    }

    /**
     * Header row styling.
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 10,
                    'name' => 'Calibri',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F3864'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * Register events for detailed styling.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $dataEndRow = $this->dataRowCount + 1; // +1 for header

                // ── Default font ──
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

                // ── Header row height ──
                $sheet->getRowDimension(1)->setRowHeight(32);

                // ── Column widths (manual, no auto-size) ──
                $widths = [
                    'A' => 6,    // #
                    'B' => 10,   // Status
                    'C' => 13,   // Invoice Date
                    'D' => 20,   // Client Name
                    'E' => 16,   // Client Mobile
                    'F' => 16,   // Task Ref
                    'G' => 12,   // Task Status
                    'H' => 20,   // Passenger
                    'I' => 12,   // Amount
                    'J' => 20,   // Payment Ref
                    'K' => 16,   // Notes
                    'L' => 2,    // ── Divider ──
                    'M' => 24,   // Matched Task
                    'N' => 22,   // Matched Client
                    'O' => 22,   // Matched Payment
                    'P' => 2,    // ── Divider ──
                    'Q' => 65,   // Issues
                ];
                foreach ($widths as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width)->setAutoSize(false);
                }

                // ── Divider columns (L and P): dark grey fill ──
                $dividerStyle = [
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2F3542'],
                    ],
                ];
                $sheet->getStyle("L1:L{$highestRow}")->applyFromArray($dividerStyle);
                $sheet->getStyle("P1:P{$highestRow}")->applyFromArray($dividerStyle);

                // ── Section header colors (override base header) ──
                // Upload Data section (A-K): dark blue
                $sheet->getStyle("A1:K1")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
                ]);
                // Matched section (M-O): dark teal
                $sheet->getStyle("M1:O1")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5E4B']],
                ]);
                // Issues section (Q): dark red
                $sheet->getStyle("Q1")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8B1A1A']],
                ]);

                // ── Data row styling ──
                for ($row = 2; $row <= $dataEndRow; $row++) {
                    $status = $sheet->getCell("B{$row}")->getValue();
                    $rowHeight = 28;

                    // Count issue lines for row height
                    $issueText = $sheet->getCell("Q{$row}")->getValue();
                    if ($issueText) {
                        $lines = substr_count($issueText, "\n") + 1;
                        $rowHeight = max($rowHeight, $lines * 16 + 8);
                    }
                    $sheet->getRowDimension($row)->setRowHeight($rowHeight);

                    if ($status === 'ERROR') {
                        // Light red bg for upload data section
                        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF0F0']],
                        ]);
                        // Light red bg for matched section
                        $sheet->getStyle("M{$row}:O{$row}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF0F0']],
                        ]);
                        // Stronger red bg for issues section
                        $sheet->getStyle("Q{$row}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE0E0']],
                            'font' => ['color' => ['rgb' => '9C0006'], 'size' => 9],
                        ]);
                        // Status badge style
                        $sheet->getStyle("B{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC2626']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                    } elseif ($status === 'FLAGGED') {
                        // Light amber bg for upload data section
                        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFBF0']],
                        ]);
                        // Light amber bg for matched section
                        $sheet->getStyle("M{$row}:O{$row}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFBF0']],
                        ]);
                        // Amber bg for issues section
                        $sheet->getStyle("Q{$row}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF3CD']],
                            'font' => ['color' => ['rgb' => '7C5C00'], 'size' => 9],
                        ]);
                        // Status badge style
                        $sheet->getStyle("B{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D97706']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                    }

                    // Alternating subtle grey for even rows (only if no status color)
                    if ($row % 2 === 0 && ! in_array($status, ['ERROR', 'FLAGGED'])) {
                        $sheet->getStyle("A{$row}:K{$row}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8F9FA']],
                        ]);
                    }
                }

                // ── Borders: data rows only ──
                if ($dataEndRow >= 2) {
                    // Upload data section borders
                    $sheet->getStyle("A1:K{$dataEndRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E2E8F0'],
                            ],
                        ],
                    ]);
                    // Matched section borders
                    $sheet->getStyle("M1:O{$dataEndRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E2E8F0'],
                            ],
                        ],
                    ]);
                    // Issues section borders
                    $sheet->getStyle("Q1:Q{$dataEndRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E2E8F0'],
                            ],
                        ],
                    ]);

                    // Thick bottom border under header
                    $sheet->getStyle("A1:Q1")->applyFromArray([
                        'borders' => [
                            'bottom' => [
                                'borderStyle' => Border::BORDER_MEDIUM,
                                'color' => ['rgb' => '0F172A'],
                            ],
                        ],
                    ]);
                }

                // ── Text wrapping for issues + notes ──
                $sheet->getStyle("Q2:Q{$highestRow}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("K2:K{$highestRow}")->getAlignment()->setWrapText(true);

                // ── Alignment ──
                $sheet->getStyle("A2:A{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("I2:I{$dataEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A2:Q{$dataEndRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

                // ── Freeze pane: scroll data while keeping header + row # visible ──
                $sheet->freezePane('C2');

                // ── Summary section styling ──
                $summaryHeaderRow = $dataEndRow + 2; // after spacer
                if ($summaryHeaderRow <= $highestRow) {
                    // Summary header row
                    $sheet->getStyle("A{$summaryHeaderRow}:Q{$summaryHeaderRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
                    ]);
                    $sheet->getRowDimension($summaryHeaderRow)->setRowHeight(26);

                    // Summary detail rows
                    for ($r = $summaryHeaderRow + 1; $r <= $highestRow; $r++) {
                        $sheet->getStyle("A{$r}:Q{$r}")->applyFromArray([
                            'font' => ['size' => 10, 'color' => ['rgb' => '334155']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                            'borders' => [
                                'bottom' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => 'CBD5E1'],
                                ],
                            ],
                        ]);
                        // Bold labels
                        $sheet->getStyle("B{$r}")->getFont()->setBold(true);
                        $sheet->getStyle("E{$r}")->getFont()->setBold(true);
                        $sheet->getStyle("H{$r}")->getFont()->setBold(true);
                        $sheet->getStyle("K{$r}")->getFont()->setBold(true);
                    }
                }
            },
        ];
    }
}
