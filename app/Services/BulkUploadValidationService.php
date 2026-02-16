<?php

namespace App\Services;

use App\Models\Client;
use App\Models\InvoiceDetail;
use App\Models\Payment;
use App\Models\Task;
use Carbon\Carbon;

/**
 * Bulk Upload Validation Service
 *
 * Validates Excel upload data for bulk invoice creation.
 * Checks headers, row data, task/client/supplier existence, and business rules.
 */
class BulkUploadValidationService
{
    /**
     * Expected headers in Excel template
     */
    private const EXPECTED_HEADERS = [
        'invoice_date',
        'client_mobile',
        'task_reference',
        'task_status',
        'selling_price',
        'payment_reference',
        'notes',
    ];

    /**
     * Required headers that must be present
     */
    private const REQUIRED_HEADERS = [
        'invoice_date',
        'client_mobile',
        'task_reference',
        'task_status',
        'selling_price',
        'payment_reference',
    ];

    /**
     * Valid task types (12 enum values)
     */
    private const VALID_TASK_TYPES = [
        'flight',
        'hotel',
        'visa',
        'insurance',
        'tour',
        'cruise',
        'car',
        'rail',
        'esim',
        'event',
        'lounge',
        'ferry',
    ];

    /**
     * Valid task statuses
     */
    private const VALID_TASK_STATUSES = [
        'pending',
        'issued',
        'confirmed',
        'reissued',
        'refund',
        'void',
        'emd',
    ];

    /**
     * Valid currency codes
     */
    private const VALID_CURRENCIES = [
        'KWD',
        'USD',
        'EUR',
        'GBP',
        'SAR',
        'AED',
        'BHD',
        'OMR',
        'QAR',
    ];

    /**
     * Validate Excel headers
     *
     * @param  array  $headers  Array of header strings from first row
     * @return array ['valid' => bool, 'missing' => [...], 'extra' => [...]]
     */
    public function validateHeaders(array $headers): array
    {
        $missing = array_diff(self::REQUIRED_HEADERS, $headers);
        $extra = array_diff($headers, self::EXPECTED_HEADERS);

        return [
            'valid' => empty($missing),
            'missing' => array_values($missing),
            'extra' => array_values($extra),
        ];
    }

    /**
     * Validate a single row
     *
     * @param  array  $row  Associative array of row data
     * @param  int  $rowNumber  1-indexed row number for error messages
     * @param  int  $companyId  Company context for lookups
     * @return array ['status' => 'valid'|'error'|'flagged', 'errors' => [...], 'flag_reason' => ?string, 'matched' => [...]]
     */
    public function validateRow(array $row, int $rowNumber, int $companyId): array
    {
        $errors = [];
        $matched = [
            'client_id' => null,
            'task_id' => null,
            'payment_id' => null,
        ];
        $flagReason = null;

        // Log the row being validated
        \Log::info("[BULK UPLOAD] Validating row {$rowNumber}", [
            'company_id' => $companyId,
            'row_data' => $row,
        ]);

        // 1. Validate invoice_date (required)
        if (empty($row['invoice_date'])) {
            $errors[] = "Row {$rowNumber}: invoice_date is required";
        } else {
            try {
                Carbon::parse($row['invoice_date']);
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNumber}: invoice_date \"{$row['invoice_date']}\" is not a valid date format";
            }
        }

        // 2. Validate client_mobile (required) - match by company_id + phone
        if (empty($row['client_mobile'])) {
            $errors[] = "Row {$rowNumber}: client_mobile is required";
        } else {
            $client = Client::where('company_id', $companyId)
                ->where('phone', $row['client_mobile'])
                ->first();

            if (! $client) {
                \Log::warning("[BULK UPLOAD] Client not found", [
                    'row' => $rowNumber,
                    'client_mobile' => $row['client_mobile'],
                    'company_id' => $companyId,
                ]);
                $errors[] = "Row {$rowNumber}: client_mobile \"{$row['client_mobile']}\" not found in your company";
            } else {
                \Log::info("[BULK UPLOAD] Client found", [
                    'row' => $rowNumber,
                    'client_id' => $client->id,
                    'client_name' => $client->name ?? 'N/A',
                ]);
                $matched['client_id'] = $client->id;
            }
        }

        // 3. Validate task_reference (required) - find by ID, PNR, or booking_reference
        if (empty($row['task_reference'])) {
            $errors[] = "Row {$rowNumber}: task_reference is required";
        } else {
            $taskQuery = Task::where('company_id', $companyId);

            // Try finding by ID if numeric
            if (is_numeric($row['task_reference'])) {
                $taskQuery->where('id', $row['task_reference']);
                \Log::info("[BULK UPLOAD] Searching task by ID", [
                    'row' => $rowNumber,
                    'task_id' => $row['task_reference'],
                    'company_id' => $companyId,
                    'status_filter' => $row['task_status'] ?? 'none',
                ]);
            } else {
                // Try finding by reference
                $taskQuery->where('reference', $row['task_reference']);
                \Log::info("[BULK UPLOAD] Searching task by reference", [
                    'row' => $rowNumber,
                    'reference' => $row['task_reference'],
                    'company_id' => $companyId,
                    'status_filter' => $row['task_status'] ?? 'none',
                ]);
            }

            // Filter by status if provided
            if (! empty($row['task_status'])) {
                $taskQuery->where('status', $row['task_status']);
            }

            // Log the SQL query
            $sql = $taskQuery->toSql();
            $bindings = $taskQuery->getBindings();
            \Log::info("[BULK UPLOAD] Task query", [
                'row' => $rowNumber,
                'sql' => $sql,
                'bindings' => $bindings,
            ]);

            $task = $taskQuery->first();

            if (! $task) {
                \Log::warning("[BULK UPLOAD] Task not found", [
                    'row' => $rowNumber,
                    'task_reference' => $row['task_reference'],
                    'task_status' => $row['task_status'] ?? null,
                    'company_id' => $companyId,
                ]);
                $errors[] = "Row {$rowNumber}: task_reference \"{$row['task_reference']}\" not found" .
                    (! empty($row['task_status']) ? " with status \"{$row['task_status']}\"" : "");
            } else {
                \Log::info("[BULK UPLOAD] Task found", [
                    'row' => $rowNumber,
                    'task_id' => $task->id,
                    'task_reference' => $task->reference,
                    'task_status' => $task->status,
                ]);
                $matched['task_id'] = $task->id;
            }
        }

        // 4. Validate task_status (required enum)
        if (empty($row['task_status'])) {
            $errors[] = "Row {$rowNumber}: task_status is required";
        } elseif (! in_array($row['task_status'], self::VALID_TASK_STATUSES, true)) {
            $validStatuses = implode(', ', self::VALID_TASK_STATUSES);
            $errors[] = "Row {$rowNumber}: task_status \"{$row['task_status']}\" is invalid. Must be one of: {$validStatuses}";
        }

        // 5. Validate selling_price (required numeric)
        if (empty($row['selling_price']) && $row['selling_price'] !== '0') {
            $errors[] = "Row {$rowNumber}: selling_price is required";
        } elseif (! is_numeric($row['selling_price'])) {
            $errors[] = "Row {$rowNumber}: selling_price \"{$row['selling_price']}\" must be a number";
        } elseif ((float)$row['selling_price'] < 0) {
            $errors[] = "Row {$rowNumber}: selling_price must be >= 0";
        }

        // 6. Validate payment_reference (required) - find by voucher_number or payment_reference
        if (empty($row['payment_reference'])) {
            $errors[] = "Row {$rowNumber}: payment_reference is required";
        } else {
            // Payment doesn't have company_id, use agent relationship
            $paymentQuery = Payment::whereHas('agent.branch', function($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });

            // Try finding by ID if numeric
            if (is_numeric($row['payment_reference'])) {
                $paymentQuery->where('id', $row['payment_reference']);
            } else {
                // Try finding by voucher_number or payment_reference
                $paymentQuery->where(function($q) use ($row) {
                    $q->where('voucher_number', $row['payment_reference'])
                      ->orWhere('payment_reference', $row['payment_reference']);
                });
            }

            $payment = $paymentQuery->first();

            if (! $payment) {
                \Log::warning("[BULK UPLOAD] Payment not found", [
                    'row' => $rowNumber,
                    'payment_reference' => $row['payment_reference'],
                    'company_id' => $companyId,
                ]);
                $errors[] = "Row {$rowNumber}: payment_reference \"{$row['payment_reference']}\" not found";
            } else {
                \Log::info("[BULK UPLOAD] Payment found", [
                    'row' => $rowNumber,
                    'payment_id' => $payment->id,
                    'voucher_number' => $payment->voucher_number ?? 'N/A',
                ]);
                $matched['payment_id'] = $payment->id;
            }
        }

        // Determine status
        $status = 'valid';
        if (! empty($errors)) {
            $status = 'error';
        } elseif ($flagReason !== null) {
            $status = 'flagged';
        }

        \Log::info("[BULK UPLOAD] Row validation result", [
            'row' => $rowNumber,
            'status' => $status,
            'errors_count' => count($errors),
            'errors' => $errors,
            'matched' => $matched,
        ]);

        return [
            'status' => $status,
            'errors' => $errors,
            'flag_reason' => $flagReason,
            'matched' => $matched,
        ];
    }

    /**
     * Validate all rows and aggregate results
     *
     * @param  array  $rows  Array of row arrays
     * @param  int  $companyId  Company context
     * @return array ['total' => int, 'valid' => int, 'errors' => int, 'flagged' => int, 'rows' => [...]]
     */
    public function validateAll(array $rows, int $companyId): array
    {
        $results = [];
        $counts = [
            'total' => count($rows),
            'valid' => 0,
            'errors' => 0,
            'flagged' => 0,
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1; // 1-indexed for user display
            $result = $this->validateRow($row, $rowNumber, $companyId);
            $results[] = $result;

            // Aggregate counts
            if ($result['status'] === 'valid') {
                $counts['valid']++;
            } elseif ($result['status'] === 'error') {
                $counts['errors']++;
            } elseif ($result['status'] === 'flagged') {
                $counts['flagged']++;
            }
        }

        return [
            'total' => $counts['total'],
            'valid' => $counts['valid'],
            'errors' => $counts['errors'],
            'flagged' => $counts['flagged'],
            'rows' => $results,
        ];
    }
}
