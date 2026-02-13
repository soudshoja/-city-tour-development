<?php

namespace App\Services;

use App\Models\Client;
use App\Models\InvoiceDetail;
use App\Models\Supplier;
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
        'task_id',
        'client_mobile',
        'supplier_name',
        'task_type',
        'task_status',
        'invoice_date',
        'currency',
        'notes',
    ];

    /**
     * Required headers that must be present
     */
    private const REQUIRED_HEADERS = [
        'task_id',
        'client_mobile',
        'supplier_name',
        'task_type',
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
            'supplier_id' => null,
        ];
        $flagReason = null;

        // 1. Validate task_id (required)
        if (empty($row['task_id'])) {
            $errors[] = "Row {$rowNumber}: task_id is required";
        } else {
            // Check task exists and belongs to company
            $task = Task::where('id', $row['task_id'])
                ->where('company_id', $companyId)
                ->first();

            if (! $task) {
                $errors[] = "Row {$rowNumber}: task_id {$row['task_id']} does not exist or does not belong to your company";
            } else {
                $matched['task_id'] = $task->id;

                // Check task not already invoiced
                $alreadyInvoiced = InvoiceDetail::where('task_id', $task->id)->exists();
                if ($alreadyInvoiced) {
                    $errors[] = "Row {$rowNumber}: task_id {$task->id} is already invoiced";
                }
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
                // Unknown client -> flag, not error
                $flagReason = 'unknown_client';
            } else {
                $matched['client_id'] = $client->id;
            }
        }

        // 3. Validate supplier_name (required) - case-insensitive lookup
        if (empty($row['supplier_name'])) {
            $errors[] = "Row {$rowNumber}: supplier_name is required";
        } else {
            $supplier = Supplier::whereRaw('LOWER(name) = ?', [strtolower($row['supplier_name'])])->first();

            if (! $supplier) {
                $errors[] = "Row {$rowNumber}: supplier '{$row['supplier_name']}' not found";
            } else {
                $matched['supplier_id'] = $supplier->id;
            }
        }

        // 4. Validate task_type (required enum)
        if (empty($row['task_type'])) {
            $errors[] = "Row {$rowNumber}: task_type is required";
        } elseif (! in_array($row['task_type'], self::VALID_TASK_TYPES, true)) {
            $validTypes = implode(', ', self::VALID_TASK_TYPES);
            $errors[] = "Row {$rowNumber}: task_type \"{$row['task_type']}\" is invalid. Must be one of: {$validTypes}";
        }

        // 5. Validate task_status (optional enum)
        if (! empty($row['task_status']) && ! in_array($row['task_status'], self::VALID_TASK_STATUSES, true)) {
            $validStatuses = implode(', ', self::VALID_TASK_STATUSES);
            $errors[] = "Row {$rowNumber}: task_status \"{$row['task_status']}\" is invalid. Must be one of: {$validStatuses}";
        }

        // 6. Validate invoice_date (optional - must be valid date if provided)
        if (! empty($row['invoice_date'])) {
            try {
                Carbon::parse($row['invoice_date']);
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNumber}: invoice_date \"{$row['invoice_date']}\" is not a valid date format";
            }
        }

        // 7. Validate currency (optional - must be 3-letter code if provided)
        if (! empty($row['currency']) && ! in_array($row['currency'], self::VALID_CURRENCIES, true)) {
            $validCurrencies = implode(', ', self::VALID_CURRENCIES);
            $errors[] = "Row {$rowNumber}: currency \"{$row['currency']}\" is invalid. Must be one of: {$validCurrencies}";
        }

        // Determine status
        $status = 'valid';
        if (! empty($errors)) {
            $status = 'error';
        } elseif ($flagReason !== null) {
            $status = 'flagged';
        }

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
