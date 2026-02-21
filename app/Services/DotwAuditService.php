<?php

namespace App\Services;

use App\Models\DotwAuditLog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DotwAuditService — sanitized audit logging for all DOTW operations.
 *
 * This service is the single point through which all DOTW audit logs are written.
 * Its two responsibilities are:
 *   1. Sanitize payloads before persisting (strip credentials and PII).
 *   2. Write to dotw_audit_logs via DotwAuditLog::log().
 *
 * Audit failure must never break the operation — if logging fails, the exception
 * is caught and logged to the 'dotw' channel, but NOT rethrown.
 *
 * Security guarantee (MSG-05): any key named password, dotw_password, dotw_username,
 * or any other sensitive key (see SENSITIVE_KEYS) is replaced with '[REDACTED]'
 * before the payload is persisted. The check is case-insensitive and recursive.
 */
class DotwAuditService
{
    /**
     * Operation type: search hotels.
     */
    public const OP_SEARCH = 'search';

    /**
     * Operation type: browse room rates.
     */
    public const OP_RATES = 'rates';

    /**
     * Operation type: block/lock a rate allocation.
     */
    public const OP_BLOCK = 'block';

    /**
     * Operation type: confirm a booking.
     */
    public const OP_BOOK = 'book';

    /**
     * Sensitive key patterns that must never appear in persisted payloads.
     *
     * Matching is case-insensitive and applied recursively at any nesting depth.
     *
     * @var array<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'dotw_password',
        'dotw_username',
        'username',
        'md5',
        'secret',
        'token',
        'authorization',
        'credit_card',
        'card_number',
        'cvv',
        'passport_number',
    ];

    /**
     * Write a sanitized audit log entry for a DOTW operation.
     *
     * Sanitizes both request and response payloads before persisting.
     * If the DB write fails, the error is logged to the 'dotw' channel
     * but no exception is thrown — audit failure must not break operations.
     *
     * @param  string  $operationType  One of OP_SEARCH, OP_RATES, OP_BLOCK, OP_BOOK
     * @param  array<mixed>  $request  Raw request payload (will be sanitized)
     * @param  array<mixed>  $response  Raw response payload (will be sanitized)
     * @param  string|null  $resayilMessageId  WhatsApp message_id from X-Resayil-Message-ID header
     * @param  string|null  $resayilQuoteId  Quoted message_id from X-Resayil-Quote-ID header
     * @param  int|null  $companyId  Company context (nullable — module is standalone)
     * @return DotwAuditLog The created model instance
     */
    public function log(
        string $operationType,
        array $request,
        array $response,
        ?string $resayilMessageId = null,
        ?string $resayilQuoteId = null,
        ?int $companyId = null
    ): DotwAuditLog {
        try {
            $sanitizedRequest = $this->sanitizePayload($request);
            $sanitizedResponse = $this->sanitizePayload($response);

            return DotwAuditLog::log([
                'company_id' => $companyId,
                'resayil_message_id' => $resayilMessageId,
                'resayil_quote_id' => $resayilQuoteId,
                'operation_type' => $operationType,
                'request_payload' => $sanitizedRequest,
                'response_payload' => $sanitizedResponse,
            ]);
        } catch (Throwable $e) {
            // Audit failure must never break the calling operation
            Log::channel('dotw')->error('DotwAuditService: failed to write audit log', [
                'operation_type' => $operationType,
                'resayil_message_id' => $resayilMessageId,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            // Return an unsaved model so callers can still type-check the return value
            return new DotwAuditLog([
                'company_id' => $companyId,
                'resayil_message_id' => $resayilMessageId,
                'resayil_quote_id' => $resayilQuoteId,
                'operation_type' => $operationType,
                'request_payload' => null,
                'response_payload' => null,
            ]);
        }
    }

    /**
     * Return the four valid operation type labels.
     *
     * Callers may use this to validate user-supplied operation types
     * before calling log(). Prefer the OP_* class constants for
     * compile-time safety.
     *
     * @return array<string>
     */
    public function operationTypes(): array
    {
        return [
            self::OP_SEARCH,
            self::OP_RATES,
            self::OP_BLOCK,
            self::OP_BOOK,
        ];
    }

    /**
     * Recursively sanitize a payload array, replacing sensitive key values with '[REDACTED]'.
     *
     * Traverses the array at any nesting depth. Any key whose lowercase name
     * matches one of SENSITIVE_KEYS has its value replaced with '[REDACTED]'.
     * The structure of the array (keys, nesting) is preserved.
     *
     * Example (MSG-05 compliance):
     *   Input:  ['fromDate' => '2026-03-01', 'password' => 'abc123md5']
     *   Output: ['fromDate' => '2026-03-01', 'password' => '[REDACTED]']
     *
     * @param  array<mixed>  $payload  The payload to sanitize
     * @return array<mixed> Sanitized payload safe for persistence
     */
    private function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = array_map('strtolower', self::SENSITIVE_KEYS);

        $sanitize = function (array $data) use (&$sanitize, $sensitiveKeys): array {
            $result = [];

            foreach ($data as $key => $value) {
                if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                    $result[$key] = '[REDACTED]';
                } elseif (is_array($value)) {
                    $result[$key] = $sanitize($value);
                } else {
                    $result[$key] = $value;
                }
            }

            return $result;
        };

        return $sanitize($payload);
    }
}
