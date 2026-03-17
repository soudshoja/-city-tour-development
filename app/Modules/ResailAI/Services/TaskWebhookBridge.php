<?php

namespace App\Modules\ResailAI\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Http\Webhooks\TaskWebhook;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Country;
use App\Models\DocumentError;
use App\Models\DocumentProcessingLog;

class TaskWebhookBridge
{
    protected TaskWebhook $taskWebhook;

    /**
     * Create a new TaskWebhookBridge instance.
     */
    public function __construct(TaskWebhook $taskWebhook)
    {
        $this->taskWebhook = $taskWebhook;
    }

    /**
     * Transform extraction result into a Request and process via TaskWebhook.
     * Entry point for all n8n/ResailAI extraction results.
     *
     * @param  array  $extractionResult  The extraction data from ResailAI/n8n
     * @return array  Response from TaskWebhook
     */
    public function processExtraction(array $extractionResult): array
    {
        $documentId = $extractionResult['document_id'] ?? 'unknown';

        Log::info('[ResailAI] Processing extraction result via TaskWebhook', [
            'document_id' => $documentId,
            'type'        => $extractionResult['type'] ?? null,
        ]);

        // (a) Validate critical fields first — any failure rejects the task entirely
        try {
            $this->validateCriticalFields($extractionResult);
        } catch (\RuntimeException $e) {
            Log::error('[ResailAI] Critical field validation failed', [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
            ]);
            $this->updateDocumentLog($documentId, 'failed', $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }

        // (c) Non-critical normalization errors collected here
        $normalizationErrors = [];

        // (d) Normalize common fields
        $commonFields = $this->normalizeCommonFields($extractionResult, $normalizationErrors);

        // (e) Normalize financial fields
        $financialFields = $this->normalizeFinancialFields($extractionResult, $normalizationErrors);

        // (f) Normalize type-specific details
        $type = strtolower(trim($extractionResult['type']));
        $typeFields = match ($type) {
            'flight'    => $this->normalizeFlightDetails($extractionResult, $normalizationErrors),
            'hotel'     => $this->normalizeHotelDetails($extractionResult, $normalizationErrors),
            'visa'      => $this->normalizeVisaDetails($extractionResult, $normalizationErrors),
            'insurance' => $this->normalizeInsuranceDetails($extractionResult, $normalizationErrors),
            default     => [],
        };

        // (g) Merge all normalized parts
        $payload = array_merge($commonFields, $financialFields, $typeFields);

        // (h) If non-critical errors exist, mark task as incomplete
        if (!empty($normalizationErrors)) {
            $payload['is_complete'] = false;
            $payload['enabled']     = false;
            $this->logNormalizationErrors($documentId, $normalizationErrors);
        }

        // (i) Build Request object
        $request = $this->buildRequestFromExtraction($payload);

        try {
            // (j) Call TaskWebhook
            $response = $this->taskWebhook->webhook($request);

            // (l) Update document log to completed
            $this->updateDocumentLog($documentId, 'completed');

            // (m) Return success
            return [
                'success'  => true,
                'response' => $response->getData(true),
            ];
        } catch (ValidationException $e) {
            // (k) Catch validation exceptions — task was not created; log and return partial success
            Log::warning('[ResailAI] TaskWebhook validation failed, task not created', [
                'document_id' => $documentId,
                'errors'      => $e->errors(),
            ]);
            $this->updateDocumentLog($documentId, 'completed', 'Validation failed: task not created, needs review');

            // Update document log to needs_review=true
            $log = DocumentProcessingLog::where('document_id', $documentId)->first();
            if ($log) {
                $log->needs_review = true;
                $log->save();
            }

            return [
                'success'  => false,
                'error'    => 'Validation failed — task not created',
                'warnings' => $e->errors(),
            ];
        } catch (\Exception $e) {
            Log::error('[ResailAI] TaskWebhook processing failed', [
                'document_id' => $documentId,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            $this->updateDocumentLog($documentId, 'failed', $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate critical fields that are required for task creation.
     * Throws RuntimeException if any critical field is missing or invalid.
     *
     * Critical fields: reference, type, company_id, status
     */
    protected function validateCriticalFields(array $data): void
    {
        if (empty($data['reference']) || !is_string($data['reference']) || trim($data['reference']) === '') {
            throw new \RuntimeException("Missing critical field: reference");
        }

        $validTypes = ['flight', 'hotel', 'visa', 'insurance'];
        $type = strtolower(trim($data['type'] ?? ''));
        if (empty($type) || !in_array($type, $validTypes)) {
            throw new \RuntimeException(
                "Missing critical field: type (must be one of: " . implode(', ', $validTypes) . ")"
            );
        }

        if (!isset($data['company_id']) || !is_numeric($data['company_id']) || (int) $data['company_id'] <= 0) {
            throw new \RuntimeException("Missing critical field: company_id (must be a positive integer)");
        }

        if (empty($data['status']) || !is_string($data['status']) || trim($data['status']) === '') {
            throw new \RuntimeException("Missing critical field: status");
        }
    }

    /**
     * Normalize common/shared task fields from extraction format.
     * Handles pass-through fields, type coercions, and optional date normalization.
     */
    protected function normalizeCommonFields(array $data, array &$errors): array
    {
        $payload = [];

        // Critical fields (already validated)
        $payload['reference']  = $data['reference'];
        $payload['status']     = strtolower(trim($data['status']));
        $payload['company_id'] = (int) $data['company_id'];
        $payload['type']       = strtolower(trim($data['type']));

        // Optional integer IDs
        if (isset($data['supplier_id']) && $data['supplier_id'] !== null) {
            $payload['supplier_id'] = (int) $data['supplier_id'];
        }

        if (isset($data['agent_id']) && $data['agent_id'] !== null) {
            if (is_numeric($data['agent_id'])) {
                $payload['agent_id'] = (int) $data['agent_id'];
            } else {
                $errors[] = [
                    'field' => 'agent_id',
                    'value' => $data['agent_id'],
                    'error' => 'agent_id must be numeric',
                ];
                $payload['agent_id'] = null;
            }
        }

        if (isset($data['branch_id']) && $data['branch_id'] !== null) {
            $payload['branch_id'] = (int) $data['branch_id'];
        }

        if (isset($data['client_id']) && $data['client_id'] !== null) {
            $payload['client_id'] = (int) $data['client_id'];
        }

        // String pass-throughs
        foreach ([
            'issued_by',
            'original_reference',
            'gds_reference',
            'airline_reference',
            'created_by',
            'ticket_number',
            'original_ticket_number',
            'booking_reference',
            'client_ref',
            'iata_number',
            'additional_info',
            'taxes_record',
            'file_name',
        ] as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $payload[$field] = $data[$field];
            }
        }

        // String fields that need trim
        if (isset($data['client_name']) && $data['client_name'] !== null) {
            $payload['client_name'] = trim($data['client_name']);
        }

        if (isset($data['supplier_status']) && $data['supplier_status'] !== null) {
            $payload['supplier_status'] = trim($data['supplier_status']);
        }

        // passenger_name: set from client_name if not explicitly provided
        if (isset($data['passenger_name'])) {
            $payload['passenger_name'] = $data['passenger_name'];
        } elseif (isset($payload['client_name'])) {
            $payload['passenger_name'] = $payload['client_name'];
        }

        // Boolean fields
        $payload['enabled'] = $this->normalizeBool($data['enabled'] ?? null);

        // Date fields
        $payload['issued_date']            = $this->normalizeDate($data['issued_date'] ?? null, 'issued_date', $errors);
        $payload['expiry_date']            = $this->normalizeDate($data['expiry_date'] ?? null, 'expiry_date', $errors);
        $payload['cancellation_deadline']  = $this->normalizeDate($data['cancellation_deadline'] ?? null, 'cancellation_deadline', $errors);
        $payload['supplier_pay_date']      = $this->normalizeDate($data['supplier_pay_date'] ?? null, 'supplier_pay_date', $errors);
        $payload['refund_date']            = $this->normalizeDate($data['refund_date'] ?? null, 'refund_date', $errors);

        // Numeric fields
        $refundCharge = $this->normalizeNumeric($data['refund_charge'] ?? null, 'refund_charge', $errors);
        $payload['refund_charge'] = $refundCharge ?? 0;

        return array_filter($payload, fn($v) => $v !== null);
    }

    /**
     * Normalize financial fields including currency swap for non-KWD amounts.
     */
    protected function normalizeFinancialFields(array $data, array &$errors): array
    {
        $payload = [];

        // Core financial amounts
        $payload['price']       = $this->normalizeNumeric($data['price'] ?? null, 'price', $errors) ?? 0;
        $payload['total']       = $this->normalizeNumeric($data['total'] ?? null, 'total', $errors) ?? 0;
        $payload['tax']         = $this->normalizeNumeric($data['tax'] ?? null, 'tax', $errors) ?? 0;
        $payload['surcharge']   = $this->normalizeNumeric($data['surcharge'] ?? null, 'surcharge', $errors) ?? 0;
        $payload['penalty_fee'] = $this->normalizeNumeric($data['penalty_fee'] ?? null, 'penalty_fee', $errors) ?? 0;

        // Currency fields
        $exchangeCurrency = isset($data['exchange_currency'])
            ? strtoupper(trim($data['exchange_currency']))
            : 'KWD';

        $exchangeRate = $this->normalizeNumeric($data['exchange_rate'] ?? null, 'exchange_rate', $errors);

        // Check if original_* fields are explicitly set
        $hasOriginalFields = isset($data['original_price']) || isset($data['original_total'])
            || isset($data['original_tax']) || isset($data['original_currency']);

        if ($exchangeCurrency !== 'KWD' && !$hasOriginalFields) {
            // Move current amounts to original_* and calculate KWD amounts
            $payload['original_currency'] = $exchangeCurrency;
            $payload['original_price']    = $payload['price'];
            $payload['original_total']    = $payload['total'];
            $payload['original_tax']      = $payload['tax'];
            $payload['original_surcharge'] = $payload['surcharge'];
            $payload['exchange_currency'] = 'KWD';

            if ($exchangeRate && $exchangeRate > 0) {
                $payload['exchange_rate']  = $exchangeRate;
                $payload['price']          = round($payload['original_price'] * $exchangeRate, 4);
                $payload['total']          = round($payload['original_total'] * $exchangeRate, 4);
                $payload['tax']            = round($payload['original_tax'] * $exchangeRate, 4);
                $payload['surcharge']      = round($payload['original_surcharge'] * $exchangeRate, 4);
            } else {
                $errors[] = [
                    'field' => 'exchange_rate',
                    'value' => $data['exchange_rate'] ?? null,
                    'error' => 'exchange_rate is required when exchange_currency is not KWD',
                ];
                $payload['price']     = 0;
                $payload['total']     = 0;
                $payload['tax']       = 0;
                $payload['surcharge'] = 0;
            }
        } elseif ($exchangeCurrency !== 'KWD' && $hasOriginalFields) {
            // Both sets already provided — keep as-is, just normalize exchange_currency to KWD
            $payload['exchange_currency'] = 'KWD';
            $payload['original_currency'] = $exchangeCurrency;

            if (isset($data['original_price'])) {
                $payload['original_price'] = $this->normalizeNumeric($data['original_price'], 'original_price', $errors);
            }
            if (isset($data['original_total'])) {
                $payload['original_total'] = $this->normalizeNumeric($data['original_total'], 'original_total', $errors);
            }
            if (isset($data['original_tax'])) {
                $payload['original_tax'] = $this->normalizeNumeric($data['original_tax'], 'original_tax', $errors);
            }
            if (isset($data['original_surcharge'])) {
                $payload['original_surcharge'] = $this->normalizeNumeric($data['original_surcharge'], 'original_surcharge', $errors);
            }

            if ($exchangeRate) {
                $payload['exchange_rate'] = $exchangeRate;
            }
        } else {
            // Already KWD
            $payload['exchange_currency'] = 'KWD';

            // Pass through any original_* if explicitly provided
            if (isset($data['original_price'])) {
                $payload['original_price'] = $this->normalizeNumeric($data['original_price'], 'original_price', $errors);
            }
            if (isset($data['original_total'])) {
                $payload['original_total'] = $this->normalizeNumeric($data['original_total'], 'original_total', $errors);
            }
            if (isset($data['original_tax'])) {
                $payload['original_tax'] = $this->normalizeNumeric($data['original_tax'], 'original_tax', $errors);
            }
            if (isset($data['original_surcharge'])) {
                $payload['original_surcharge'] = $this->normalizeNumeric($data['original_surcharge'], 'original_surcharge', $errors);
            }
            if (isset($data['original_currency'])) {
                $payload['original_currency'] = strtoupper(trim($data['original_currency']));
            }

            if ($exchangeRate) {
                $payload['exchange_rate'] = $exchangeRate;
            }
        }

        // taxes_record — string pass-through
        if (isset($data['taxes_record'])) {
            $payload['taxes_record'] = $data['taxes_record'];
        }

        return $payload;
    }

    /**
     * Normalize flight task_flight_details array.
     * Accepts both 'task_flight_details' and 'flight_details' input keys.
     *
     * @return array ['task_flight_details' => [...]]
     */
    protected function normalizeFlightDetails(array $data, array &$errors): array
    {
        $rawDetails = $data['task_flight_details'] ?? $data['flight_details'] ?? [];

        if (empty($rawDetails) || !is_array($rawDetails)) {
            $errors[] = [
                'field' => 'task_flight_details',
                'value' => null,
                'error' => 'Flight details missing or empty',
            ];

            return ['task_flight_details' => []];
        }

        $normalized = [];

        foreach ($rawDetails as $index => $segment) {
            $prefix = "flight[{$index}]";

            $norm = [];
            $norm['is_ancillary']    = $this->normalizeBool($segment['is_ancillary'] ?? false);
            $norm['farebase']        = $this->normalizeNumeric($segment['farebase'] ?? null, "{$prefix}.farebase", $errors) ?? 0.0;
            $norm['departure_time']  = $this->normalizeDate($segment['departure_time'] ?? null, "{$prefix}.departure_time", $errors) ?? '';
            $norm['arrival_time']    = $this->normalizeDate($segment['arrival_time'] ?? null, "{$prefix}.arrival_time", $errors) ?? '';
            $norm['airline_id']      = $this->resolveAirlineId($segment['airline_id'] ?? null, "{$prefix}.airline_id", $errors);
            $norm['country_id_from'] = $this->resolveCountryIdFromAirport($segment['country_id_from'] ?? null, "{$prefix}.country_id_from", $errors);
            $norm['country_id_to']   = $this->resolveCountryIdFromAirport($segment['country_id_to'] ?? null, "{$prefix}.country_id_to", $errors);
            $norm['airport_from']    = strtoupper(trim($segment['airport_from'] ?? ''));
            $norm['airport_to']      = strtoupper(trim($segment['airport_to'] ?? ''));
            $norm['terminal_from']   = trim($segment['terminal_from'] ?? '');
            $norm['terminal_to']     = trim($segment['terminal_to'] ?? '');
            $norm['duration_time']   = trim($segment['duration_time'] ?? '');
            $norm['flight_number']   = trim($segment['flight_number'] ?? '');
            $norm['ticket_number']   = trim($segment['ticket_number'] ?? '');
            $norm['class_type']      = strtolower(trim($segment['class_type'] ?? ''));
            $norm['baggage_allowed'] = trim($segment['baggage_allowed'] ?? '');
            $norm['equipment']       = trim($segment['equipment'] ?? '');
            $norm['flight_meal']     = trim($segment['flight_meal'] ?? '');
            $norm['seat_no']         = trim($segment['seat_no'] ?? '');

            $normalized[] = $norm;
        }

        return ['task_flight_details' => $normalized];
    }

    /**
     * Normalize hotel task_hotel_details array.
     * Accepts both 'task_hotel_details' and 'hotel_details' input keys.
     *
     * @return array ['task_hotel_details' => [...]]
     */
    protected function normalizeHotelDetails(array $data, array &$errors): array
    {
        $rawDetails = $data['task_hotel_details'] ?? $data['hotel_details'] ?? [];

        if (empty($rawDetails) || !is_array($rawDetails)) {
            $errors[] = [
                'field' => 'task_hotel_details',
                'value' => null,
                'error' => 'Hotel details missing or empty',
            ];

            return ['task_hotel_details' => []];
        }

        $normalized = [];

        foreach ($rawDetails as $index => $entry) {
            $prefix = "hotel[{$index}]";

            $norm = [];
            $norm['hotel_name']      = trim($entry['hotel_name'] ?? '');
            $norm['booking_time']    = $this->normalizeDate($entry['booking_time'] ?? null, "{$prefix}.booking_time", $errors) ?? Carbon::now()->toDateTimeString();
            $norm['check_in']        = $this->normalizeDate($entry['check_in'] ?? null, "{$prefix}.check_in", $errors) ?? '';
            $norm['check_out']       = $this->normalizeDate($entry['check_out'] ?? null, "{$prefix}.check_out", $errors) ?? '';
            $norm['room_reference']  = trim($entry['room_reference'] ?? '');
            $norm['room_number']     = trim($entry['room_number'] ?? '');
            $norm['room_type']       = trim($entry['room_type'] ?? '');
            $rawRoomAmount           = $this->normalizeNumeric($entry['room_amount'] ?? null, "{$prefix}.room_amount", $errors);
            $norm['room_amount']     = max(1, (int) ($rawRoomAmount ?? 1));
            $norm['room_details']    = trim($entry['room_details'] ?? '');
            $norm['room_promotion']  = trim($entry['room_promotion'] ?? '');
            $norm['rate']            = $this->normalizeNumeric($entry['rate'] ?? null, "{$prefix}.rate", $errors) ?? 0.0;
            $norm['meal_type']       = $this->normalizeMealType($entry['meal_type'] ?? null);
            $norm['is_refundable']   = $this->normalizeBool($entry['is_refundable'] ?? false);
            $norm['supplements']     = trim($entry['supplements'] ?? '');

            $normalized[] = $norm;
        }

        return ['task_hotel_details' => $normalized];
    }

    /**
     * Normalize visa task_visa_details array.
     * Accepts both 'task_visa_details' and 'visa_details' input keys.
     *
     * @return array ['task_visa_details' => [...]]
     */
    protected function normalizeVisaDetails(array $data, array &$errors): array
    {
        $rawDetails = $data['task_visa_details'] ?? $data['visa_details'] ?? [];

        if (empty($rawDetails) || !is_array($rawDetails)) {
            $errors[] = [
                'field' => 'task_visa_details',
                'value' => null,
                'error' => 'Visa details missing or empty',
            ];

            return ['task_visa_details' => []];
        }

        $normalized = [];

        foreach ($rawDetails as $index => $entry) {
            $prefix = "visa[{$index}]";

            $norm = [];
            $norm['visa_type']          = trim($entry['visa_type'] ?? '');
            $norm['application_number'] = trim($entry['application_number'] ?? '');
            $norm['expiry_date']        = $this->normalizeDate($entry['expiry_date'] ?? null, "{$prefix}.expiry_date", $errors) ?? '';
            $norm['issuing_country']    = trim($entry['issuing_country'] ?? '');

            // number_of_entries: must be single, double, or multiple
            $entries = strtolower(trim($entry['number_of_entries'] ?? ''));
            if (in_array($entries, ['single', 'double', 'multiple'])) {
                $norm['number_of_entries'] = $entries;
            } else {
                $errors[] = [
                    'field' => "{$prefix}.number_of_entries",
                    'value' => $entry['number_of_entries'] ?? null,
                    'error' => "number_of_entries must be single, double, or multiple",
                ];
                $norm['number_of_entries'] = 'single';
            }

            // stay_duration: extract integer from strings like "30 days", "14", "Up to 30 days"
            $norm['stay_duration'] = $this->normalizeStayDuration(
                $entry['stay_duration'] ?? null,
                "{$prefix}.stay_duration",
                $errors
            );

            $normalized[] = $norm;
        }

        return ['task_visa_details' => $normalized];
    }

    /**
     * Normalize insurance task_insurance_details array.
     * Accepts both 'task_insurance_details' and 'insurance_details' input keys.
     *
     * @return array ['task_insurance_details' => [...]]
     */
    protected function normalizeInsuranceDetails(array $data, array &$errors): array
    {
        $rawDetails = $data['task_insurance_details'] ?? $data['insurance_details'] ?? [];

        if (empty($rawDetails) || !is_array($rawDetails)) {
            $errors[] = [
                'field' => 'task_insurance_details',
                'value' => null,
                'error' => 'Insurance details missing or empty',
            ];

            return ['task_insurance_details' => []];
        }

        $normalized = [];

        foreach ($rawDetails as $index => $entry) {
            $prefix = "insurance[{$index}]";

            $norm = [];

            // date: if 4-digit year pass through; if full date extract year; otherwise error
            $dateVal = $entry['date'] ?? null;
            if ($dateVal !== null) {
                if (preg_match('/^\d{4}$/', trim((string) $dateVal))) {
                    // Already a 4-digit year string
                    $norm['date'] = trim((string) $dateVal);
                } else {
                    try {
                        $norm['date'] = (string) Carbon::parse($dateVal)->year;
                    } catch (\Exception $e) {
                        $errors[] = [
                            'field' => "{$prefix}.date",
                            'value' => $dateVal,
                            'error' => 'Could not parse insurance date/year: ' . $e->getMessage(),
                        ];
                        $norm['date'] = null;
                    }
                }
            } else {
                $norm['date'] = null;
            }

            $norm['paid_leaves']          = (int) ($entry['paid_leaves'] ?? 0);
            $norm['document_reference']   = trim($entry['document_reference'] ?? '');
            $norm['insurance_type']       = trim($entry['insurance_type'] ?? '');
            $norm['destination']          = trim($entry['destination'] ?? '');
            $norm['plan_type']            = trim($entry['plan_type'] ?? '');
            $norm['duration']             = trim($entry['duration'] ?? '');
            $norm['package']              = trim($entry['package'] ?? '');

            $normalized[] = $norm;
        }

        return ['task_insurance_details' => $normalized];
    }

    // -------------------------------------------------------------------------
    // Helper / utility normalization methods
    // -------------------------------------------------------------------------

    /**
     * Normalize a date string to YYYY-MM-DD HH:MM:SS format.
     * Accepts multiple input formats (DD/MM/YYYY, DD-Mon-YYYY, YYYYMMDD, ISO 8601, etc.).
     *
     * @param  string|null  $value  The raw date string
     * @param  string       $field  Field name for error reporting
     * @param  array        &$errors  Accumulated normalization errors
     * @return string|null  Normalized date string or null on failure
     */
    protected function normalizeDate(?string $value, string $field, array &$errors): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // Try DD/MM/YYYY or DD/MM/YYYY HH:MM
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $value)) {
            try {
                $format = strlen($value) > 10 ? 'd/m/Y H:i' : 'd/m/Y';
                $parsed = Carbon::createFromFormat($format, substr($value, 0, strlen($format) === 10 ? 10 : 16));
                if ($parsed) {
                    return $parsed->toDateTimeString();
                }
            } catch (\Exception $e) {
                // Fall through to next format
            }
        }

        // Try DD-Mon-YYYY (e.g., 15-Mar-2026)
        if (preg_match('/^\d{1,2}-[A-Za-z]{3}-\d{4}/', $value)) {
            try {
                $parsed = Carbon::parse($value);

                return $parsed->toDateTimeString();
            } catch (\Exception $e) {
                // Fall through
            }
        }

        // Try YYYYMMDD (8 digits)
        if (preg_match('/^\d{8}$/', $value)) {
            try {
                $parsed = Carbon::createFromFormat('Ymd', $value);
                if ($parsed) {
                    return $parsed->toDateTimeString();
                }
            } catch (\Exception $e) {
                // Fall through
            }
        }

        // Try standard YYYY-MM-DD, YYYY-MM-DD HH:MM:SS, ISO 8601
        try {
            $parsed = Carbon::parse($value);

            return $parsed->toDateTimeString();
        } catch (\Exception $e) {
            // All parsing failed
        }

        $errors[] = [
            'field' => $field,
            'value' => $value,
            'error' => 'Unrecognized date format',
        ];

        return null;
    }

    /**
     * Normalize a value to float.
     * Handles numeric strings, comma-separated numbers, and invalid values.
     *
     * @param  mixed   $value  The raw value
     * @param  string  $field  Field name for error reporting
     * @param  array   &$errors  Accumulated normalization errors
     * @return float|null  Normalized float or null if value was null/empty
     */
    protected function normalizeNumeric($value, string $field, array &$errors): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            // Strip commas (e.g., "1,234.56")
            $cleaned = str_replace(',', '', trim($value));
            if (is_numeric($cleaned)) {
                return (float) $cleaned;
            }
        }

        $errors[] = [
            'field' => $field,
            'value' => $value,
            'error' => "Cannot convert '{$value}' to numeric",
        ];

        return null;
    }

    /**
     * Normalize a value to boolean.
     * Handles string variations ("yes", "true", "1") and native types.
     *
     * @param  mixed  $value  The raw value
     * @return bool
     */
    protected function normalizeBool($value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['true', 'yes', '1']);
        }

        return false;
    }

    /**
     * Resolve airline name/ICAO code to database airline ID.
     * If value is numeric, returns as integer directly.
     * Otherwise performs LIKE/exact lookup against Airline model.
     *
     * @param  mixed   $value  Airline name, ICAO code, or numeric ID
     * @param  string  $field  Field name for error reporting
     * @param  array   &$errors  Accumulated normalization errors
     * @return int  Resolved airline ID or 0 if not found
     */
    protected function resolveAirlineId($value, string $field, array &$errors): int
    {
        if ($value === null || $value === '') {
            $errors[] = [
                'field' => $field,
                'value' => $value,
                'error' => 'airline_id is empty or null',
            ];

            return 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        // String lookup: try name LIKE or ICAO designator exact match
        $airline = Airline::where('name', 'LIKE', '%' . $value . '%')
            ->orWhere('icao_designator', strtoupper($value))
            ->first();

        if ($airline) {
            return $airline->id;
        }

        $errors[] = [
            'field' => $field,
            'value' => $value,
            'error' => "Airline not found for value '{$value}'",
        ];

        return 0;
    }

    /**
     * Resolve an airport IATA code, country name, or numeric ID to a country_id.
     * If value is numeric, returns as integer directly.
     * If 3-letter string, performs IATA code lookup on Airport table for country_id.
     * If longer string, performs LIKE lookup on Country table.
     *
     * @param  mixed   $value  IATA code, country name, or numeric ID
     * @param  string  $field  Field name for error reporting
     * @param  array   &$errors  Accumulated normalization errors
     * @return int  Resolved country_id or 0 if not found
     */
    protected function resolveCountryIdFromAirport($value, string $field, array &$errors): int
    {
        if ($value === null || $value === '') {
            $errors[] = [
                'field' => $field,
                'value' => $value,
                'error' => 'country_id / airport value is empty or null',
            ];

            return 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $value = trim((string) $value);

        if (strlen($value) === 3) {
            // Treat as IATA code
            $airport = Airport::where('iata_code', strtoupper($value))->first();
            if ($airport && $airport->country_id) {
                return (int) $airport->country_id;
            }
        }

        // Try country name lookup
        $country = Country::where('name', 'LIKE', '%' . $value . '%')->first();
        if ($country) {
            return (int) $country->id;
        }

        $errors[] = [
            'field' => $field,
            'value' => $value,
            'error' => "Could not resolve country_id from value '{$value}'",
        ];

        return 0;
    }

    /**
     * Normalize hotel board/meal code to full name.
     * Maps standard codes: BB, HB, FB, AI, RO, SC.
     * Unknown values are passed through as-is.
     *
     * @param  string|null  $value  Board code or meal type string
     * @return string
     */
    protected function normalizeMealType(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $map = [
            'BB' => 'Bed and Breakfast',
            'HB' => 'Half Board',
            'FB' => 'Full Board',
            'AI' => 'All Inclusive',
            'RO' => 'Room Only',
            'SC' => 'Self Catering',
        ];

        $upper = strtoupper(trim($value));
        if (isset($map[$upper])) {
            return $map[$upper];
        }

        return trim($value);
    }

    /**
     * Normalize stay duration to integer.
     * Extracts the first integer from strings like "30 days", "14", "Up to 30 days".
     *
     * @param  mixed   $value  Raw stay duration value
     * @param  string  $field  Field name for error reporting
     * @param  array   &$errors  Accumulated normalization errors
     * @return int  Extracted integer or 0 on failure
     */
    protected function normalizeStayDuration($value, string $field, array &$errors): int
    {
        if ($value === null || $value === '') {
            $errors[] = [
                'field' => $field,
                'value' => $value,
                'error' => 'stay_duration is empty or null',
            ];

            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        // Extract first integer from string (e.g., "30 days", "Up to 30 days")
        if (preg_match('/(\d+)/', (string) $value, $matches)) {
            return (int) $matches[1];
        }

        $errors[] = [
            'field' => $field,
            'value' => $value,
            'error' => "Cannot extract integer stay duration from '{$value}'",
        ];

        return 0;
    }

    /**
     * Log normalization errors as DocumentError records and update the
     * DocumentProcessingLog to needs_review=true.
     *
     * @param  string  $documentId  The document identifier for correlation
     * @param  array   $errors      Array of normalization error entries
     */
    protected function logNormalizationErrors(string $documentId, array $errors): void
    {
        $log = DocumentProcessingLog::where('document_id', $documentId)->first();

        if ($log) {
            $log->needs_review = true;
            $log->save();
        }

        foreach ($errors as $error) {
            $field = $error['field'] ?? 'unknown';
            $errorValue = $error['value'] ?? null;
            $errorMessage = $error['error'] ?? 'Normalization failed';

            if ($log) {
                DocumentError::create([
                    'document_processing_log_id' => $log->id,
                    'error_type'                 => DocumentError::TYPE_NON_TRANSIENT,
                    'error_code'                 => 'NORMALIZATION_' . strtoupper(str_replace(['.', '[', ']'], '_', $field)),
                    'error_message'              => "Field '{$field}' normalization failed: {$errorMessage}",
                    'input_context'              => ['value' => $errorValue, 'document_id' => $documentId],
                ]);
            }
        }

        // Also log immediately for visibility
        Log::warning('[ResailAI] Normalization errors for document', [
            'document_id'  => $documentId,
            'error_count'  => count($errors),
            'errors'       => $errors,
        ]);
    }

    /**
     * Build a Laravel Request from normalized payload array.
     *
     * @param  array  $payload  The normalized payload data
     * @return Request
     */
    protected function buildRequestFromExtraction(array $payload): Request
    {
        $request = Request::create(
            '/api/internal/task-webhook',
            'POST',
            $payload
        );

        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    /**
     * Update document processing log status and optional error message.
     *
     * @param  string       $documentId    The document identifier
     * @param  string       $status        Processing status (completed, failed, etc.)
     * @param  string|null  $errorMessage  Error message if any
     */
    protected function updateDocumentLog(string $documentId, string $status, ?string $errorMessage = null): void
    {
        $log = DocumentProcessingLog::where('document_id', $documentId)->first();

        if ($log) {
            $log->status = $status;
            if ($errorMessage) {
                $log->error_message = $errorMessage;
            }
            $log->save();
        }
    }
}
