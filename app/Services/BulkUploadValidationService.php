<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Credit;
use App\Models\InvoiceDetail;
use App\Models\Payment;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        'client_name',
        'client_mobile',
        'task_reference',
        'task_status',
        'passenger_name',
        'selling_price',
        'payment_reference',
        'notes',
    ];

    /**
     * Required headers that must be present
     */
    private const REQUIRED_HEADERS = [
        'invoice_date',
        'client_name',
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
    public function validateRow(array $row, int $rowNumber, int $companyId, ?int $agentId = null): array
    {
        $errors = [];
        $matched = [
            'client_id' => null,
            'task_id' => null,
            'payment_id' => null,
        ];
        $flagReason = null;
        $client = null;
        $task = null;
        $payment = null;

        // Log the row being validated
        Log::info("[BULK UPLOAD] Validating row {$rowNumber}", [
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

        // 2. Validate client_name (required)
        if (empty($row['client_name'])) {
            $errors[] = "Row {$rowNumber}: client_name is required";
        }

        // 2b. Validate client_mobile (required) - match by company_id + phone + name
        if (empty($row['client_mobile'])) {
            $errors[] = "Row {$rowNumber}: client_mobile is required";
        } elseif (! empty($row['client_name'])) {
            // Strip spaces, dashes, plus signs from phone number
            $cleanPhone = preg_replace('/[\s\-\+\(\)]/', '', trim($row['client_mobile']));
            $clientName = trim($row['client_name']);

            $clientsQuery = Client::where('company_id', $companyId)
                ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') = ?", [$cleanPhone]);

            $matchingClients = $clientsQuery->get();

            if ($matchingClients->count() === 0) {
                Log::warning("[BULK UPLOAD] Client not found", [
                    'row' => $rowNumber,
                    'client_mobile' => $row['client_mobile'],
                    'company_id' => $companyId,
                ]);
                $errors[] = "Row {$rowNumber}: client with mobile \"{$row['client_mobile']}\" not found in your company";
            } elseif ($matchingClients->count() === 1) {
                // Single match — verify name matches (flexible: all Excel words must exist in DB name)
                $client = $matchingClients->first();
                if (! $this->nameMatches($clientName, $client->full_name)) {
                    $errors[] = "Row {$rowNumber}: client name \"{$clientName}\" does not match the client found with mobile \"{$row['client_mobile']}\" (expected: \"{$client->full_name}\")";
                } else {
                    $matched['client_id'] = $client->id;
                }
            } else {
                // Multiple clients with same phone — match by name (flexible)
                $client = $matchingClients->first(function ($c) use ($clientName) {
                    return $this->nameMatches($clientName, $c->full_name);
                });

                if (! $client) {
                    $names = $matchingClients->map(fn ($c) => $c->full_name)->implode(', ');
                    $errors[] = "Row {$rowNumber}: multiple clients found with mobile \"{$row['client_mobile']}\" but none match name \"{$clientName}\" (found: {$names})";
                } else {
                    $matched['client_id'] = $client->id;
                }
            }

            if ($matched['client_id']) {
                Log::info("[BULK UPLOAD] Client found", [
                    'row' => $rowNumber,
                    'client_id' => $client->id,
                    'client_name' => $client->full_name ?? 'N/A',
                ]);
            }
        }

        // 3. Validate task_reference (required) - find by reference field
        if (empty($row['task_reference'])) {
            $errors[] = "Row {$rowNumber}: task_reference is required";
        } else {
            $ref = $row['task_reference'];
            $statusFilter = ! empty($row['task_status']) ? strtolower(trim($row['task_status'])) : null;

            // Search by reference field + optional status filter
            $q = Task::where('company_id', $companyId)->where('reference', $ref);
            if ($statusFilter) {
                $q->whereRaw('LOWER(status) = ?', [$statusFilter]);
            }
            $matchingTasks = $q->get();

            // If multiple matches, try to narrow down by passenger_name
            $passengerName = ! empty($row['passenger_name']) ? trim($row['passenger_name']) : null;
            if ($matchingTasks->count() > 1 && $passengerName) {
                $filtered = $matchingTasks->filter(function ($t) use ($passengerName) {
                    return strtolower($t->passenger_name ?? '') === strtolower($passengerName);
                });
                if ($filtered->count() > 0) {
                    $matchingTasks = $filtered->values();
                }
            }

            $task = $matchingTasks->first();

            Log::info("[BULK UPLOAD] Task search", [
                'row' => $rowNumber,
                'search_value' => $ref,
                'company_id' => $companyId,
                'status_filter' => $statusFilter,
                'passenger_name' => $passengerName,
                'matches_count' => $matchingTasks->count(),
                'found' => $task ? $task->id : null,
            ]);

            if ($matchingTasks->count() === 0) {
                Log::warning("[BULK UPLOAD] Task not found", [
                    'row' => $rowNumber,
                    'task_reference' => $row['task_reference'],
                    'task_status' => $row['task_status'] ?? null,
                    'company_id' => $companyId,
                ]);
                $errors[] = "Row {$rowNumber}: task_reference \"{$row['task_reference']}\" not found" .
                    (! empty($row['task_status']) ? " with status \"{$row['task_status']}\"" : "");
            } elseif ($matchingTasks->count() > 1) {
                // Still ambiguous after passenger_name filter — reject
                $taskIds = $matchingTasks->pluck('id')->implode(', ');
                $errorMsg = "Row {$rowNumber}: multiple tasks found for reference \"{$row['task_reference']}\"" .
                    (! empty($row['task_status']) ? " with status \"{$row['task_status']}\"" : "");
                if ($passengerName) {
                    $errorMsg .= " and passenger \"{$passengerName}\"";
                } else {
                    $errorMsg .= ". Try adding passenger_name to narrow down";
                }
                $errorMsg .= " (IDs: {$taskIds})";
                $errors[] = $errorMsg;
            } else {
                Log::info("[BULK UPLOAD] Task found", [
                    'row' => $rowNumber,
                    'task_id' => $task->id,
                    'task_reference' => $task->reference,
                    'task_status' => $task->status,
                ]);
                $matched['task_id'] = $task->id;

                // 3a. Check if task is complete (has all required fields)
                if (! $task->is_complete) {
                    $missingFields = [];
                    foreach ($task->getRequiredColumns() as $col) {
                        if (empty($task->$col) && $task->$col != 0 && $task->$col != '0') {
                            $missingFields[] = $col;
                        }
                    }
                    $errors[] = "Row {$rowNumber}: task \"{$row['task_reference']}\" is incomplete (missing: " . implode(', ', $missingFields) . ")";
                }

                // 3b. Check if task has an agent assigned
                if (! $task->agent_id) {
                    $errors[] = "Row {$rowNumber}: task \"{$row['task_reference']}\" has no agent assigned";
                } elseif ($agentId && $task->agent_id !== $agentId) {
                    // 3b2. Check if task belongs to the selected agent
                    $taskAgent = $task->agent?->user?->name ?? "Agent #{$task->agent_id}";
                    $errors[] = "Row {$rowNumber}: task \"{$row['task_reference']}\" belongs to {$taskAgent}, not the selected agent";
                }

                // 3c. Check if task is already invoiced
                $existingInvoiceDetail = InvoiceDetail::where('task_id', $task->id)->first();
                if ($existingInvoiceDetail) {
                    $errors[] = "Row {$rowNumber}: task \"{$row['task_reference']}\" is already invoiced (Invoice #{$existingInvoiceDetail->invoice_number})";
                }

                // 3d. Check if task's client matches the Excel client (if task already has a client)
                if ($task->client_id && $client && $task->client_id !== $client->id) {
                    $errors[] = "Row {$rowNumber}: task \"{$row['task_reference']}\" belongs to a different client (Client #{$task->client_id})";
                }
            }
        }

        // 4. Validate task_status (required enum, case-insensitive)
        if (empty($row['task_status'])) {
            $errors[] = "Row {$rowNumber}: task_status is required";
        } elseif (! in_array(strtolower(trim($row['task_status'])), self::VALID_TASK_STATUSES, true)) {
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
                Log::warning("[BULK UPLOAD] Payment not found", [
                    'row' => $rowNumber,
                    'payment_reference' => $row['payment_reference'],
                    'company_id' => $companyId,
                ]);
                $errors[] = "Row {$rowNumber}: payment_reference \"{$row['payment_reference']}\" not found";
            } else {
                Log::info("[BULK UPLOAD] Payment found", [
                    'row' => $rowNumber,
                    'payment_id' => $payment->id,
                    'voucher_number' => $payment->voucher_number ?? 'N/A',
                ]);
                $matched['payment_id'] = $payment->id;

                // 6a. Check payment belongs to the same client
                if ($client && $payment->client_id !== $client->id) {
                    $errors[] = "Row {$rowNumber}: payment \"{$row['payment_reference']}\" belongs to a different client, not \"{$row['client_mobile']}\"";
                }

                // 6b. Check payment is actually paid/completed
                if ($payment->status !== 'completed') {
                    $errors[] = "Row {$rowNumber}: payment \"{$row['payment_reference']}\" is not paid yet (status: {$payment->status})";
                }

                // 6c. Check payment has enough available credit balance
                if ($client && is_numeric($row['selling_price'] ?? null)) {
                    $availableBalance = Credit::getAvailableBalanceByPayment($payment->id);
                    $sellingPrice = (float) $row['selling_price'];

                    if ($availableBalance <= 0) {
                        $errors[] = "Row {$rowNumber}: payment \"{$row['payment_reference']}\" has no remaining credit balance (fully used)";
                    } elseif ($availableBalance < $sellingPrice) {
                        $flagReason = "Payment \"{$row['payment_reference']}\" available balance (" . number_format($availableBalance, 3) . ") is less than selling price (" . number_format($sellingPrice, 3) . ")";
                    }
                }
            }
        }

        // Determine status
        $status = 'valid';
        if (! empty($errors)) {
            $status = 'error';
        } elseif ($flagReason !== null) {
            $status = 'flagged';
        }

        Log::info("[BULK UPLOAD] Row validation result", [
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
    public function validateAll(array $rows, int $companyId, ?int $agentId = null): array
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
            $result = $this->validateRow($row, $rowNumber, $companyId, $agentId);
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

        // Check for duplicate task references within the same upload
        $seenTaskIds = [];
        foreach ($results as $index => &$result) {
            $taskId = $result['matched']['task_id'] ?? null;
            if ($taskId && $result['status'] !== 'error') {
                if (isset($seenTaskIds[$taskId])) {
                    $rowNumber = $index + 1;
                    $firstRow = $seenTaskIds[$taskId];
                    $result['errors'][] = "Row {$rowNumber}: duplicate task - same task already in row {$firstRow}";
                    if ($result['status'] === 'valid') {
                        $counts['valid']--;
                    } elseif ($result['status'] === 'flagged') {
                        $counts['flagged']--;
                    }
                    $result['status'] = 'error';
                    $counts['errors']++;
                } else {
                    $seenTaskIds[$taskId] = $index + 1;
                }
            }
        }
        unset($result);

        // Check cumulative payment balance across rows sharing the same payment
        $paymentTotals = []; // payment_id => ['total' => float, 'rows' => [rowNumbers]]
        foreach ($results as $index => $result) {
            if ($result['status'] === 'error') {
                continue;
            }
            $paymentId = $result['matched']['payment_id'] ?? null;
            $sellingPrice = (float) ($rows[$index]['selling_price'] ?? 0);
            if ($paymentId) {
                if (! isset($paymentTotals[$paymentId])) {
                    $paymentTotals[$paymentId] = ['total' => 0, 'rows' => []];
                }
                $paymentTotals[$paymentId]['total'] += $sellingPrice;
                $paymentTotals[$paymentId]['rows'][] = $index;
            }
        }

        // Flag rows where combined total exceeds available balance
        foreach ($paymentTotals as $paymentId => $info) {
            if (count($info['rows']) < 2) {
                continue; // Single row already checked in validateRow
            }
            $availableBalance = Credit::getAvailableBalanceByPayment($paymentId);
            if ($info['total'] > $availableBalance) {
                // Flag all rows using this payment
                foreach ($info['rows'] as $rowIndex) {
                    $rowNumber = $rowIndex + 1;
                    $ref = $rows[$rowIndex]['payment_reference'] ?? 'unknown';
                    if ($results[$rowIndex]['status'] === 'valid') {
                        $results[$rowIndex]['flag_reason'] = "Payment \"{$ref}\" combined total (" . number_format($info['total'], 3) . ") across " . count($info['rows']) . " rows exceeds available balance (" . number_format($availableBalance, 3) . ")";
                        $results[$rowIndex]['status'] = 'flagged';
                        $counts['valid']--;
                        $counts['flagged']++;
                    }
                }
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

    /**
     * Flexible name matching — all words from the input name must exist in the DB name.
     * Handles partial names (first + last without middle name).
     *
     * Examples:
     *   "ANIL SINGH" matches "ANIL KUMAR SINGH" ✓
     *   "ANIL KUMAR SINGH" matches "ANIL KUMAR SINGH" ✓
     *   "ANIL KUMAR" matches "ANIL KUMAR SINGH" ✓
     *   "JOHN DOE" does NOT match "ANIL KUMAR SINGH" ✗
     */
    private function nameMatches(string $inputName, ?string $dbName): bool
    {
        if (empty($dbName)) {
            return false;
        }

        $inputWords = preg_split('/\s+/', strtolower(trim($inputName)));
        $dbWords = preg_split('/\s+/', strtolower(trim($dbName)));

        // Every word from the Excel input must exist in the DB name
        foreach ($inputWords as $word) {
            if (! in_array($word, $dbWords, true)) {
                return false;
            }
        }

        return true;
    }
}
