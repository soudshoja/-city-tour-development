<?php

namespace App\Services;

/**
 * Error Code Registry
 *
 * Central registry of all error codes used in N8n document processing pipeline.
 * Used by N8nExecutionTracker for automatic error classification.
 *
 * @see ERROR_HANDLING_ARCHITECTURE.md Section 2: Error Code Registry
 */
class ErrorCodeRegistry
{
    /**
     * Transient errors - Temporary failures that may succeed on retry
     * Safe for automatic recovery with exponential backoff
     */
    const TRANSIENT_ERRORS = [
        'ERR_TIMEOUT' => [
            'description' => 'N8n HTTP timeout exceeded',
            'recovery' => 'Exponential backoff retry',
            'max_retries' => 5,
        ],
        'ERR_SERVICE_UNAVAILABLE' => [
            'description' => 'N8n temporary downtime (503)',
            'recovery' => 'Exponential backoff retry',
            'max_retries' => 5,
        ],
        'ERR_RATE_LIMIT' => [
            'description' => 'API rate limit hit (OpenAI, Gmail)',
            'recovery' => 'Exponential backoff retry',
            'max_retries' => 5,
        ],
        'ERR_FILE_TEMP_UNAVAILABLE' => [
            'description' => 'S3 eventual consistency delay',
            'recovery' => 'Exponential backoff retry',
            'max_retries' => 3,
        ],
        'ERR_NETWORK_TRANSIENT' => [
            'description' => 'Temporary network issue',
            'recovery' => 'Exponential backoff retry',
            'max_retries' => 5,
        ],
    ];

    /**
     * Non-transient errors - Data quality or configuration issues
     * Will not resolve on retry without human intervention
     */
    const NON_TRANSIENT_ERRORS = [
        'ERR_PARSE_FAILURE' => [
            'description' => 'JSON parsing error in webhook body',
            'recovery' => 'Manual investigation',
            'max_retries' => 0,
        ],
        'ERR_VALIDATION_FAILURE' => [
            'description' => 'Missing/invalid required fields',
            'recovery' => 'Manual investigation',
            'max_retries' => 0,
        ],
        'ERR_UNSUPPORTED_FORMAT' => [
            'description' => 'File type not supported by N8n flow',
            'recovery' => 'Manual investigation',
            'max_retries' => 0,
        ],
        'ERR_FILE_NOT_FOUND' => [
            'description' => 'S3 file doesn\'t exist at path',
            'recovery' => 'Manual investigation',
            'max_retries' => 0,
        ],
        'ERR_INSUFFICIENT_DATA' => [
            'description' => 'Document has no extractable content',
            'recovery' => 'Manual investigation',
            'max_retries' => 0,
        ],
        'ERR_HMAC_INVALID' => [
            'description' => 'Webhook signature verification failed',
            'recovery' => 'Manual investigation',
            'max_retries' => 0,
        ],
        'ERR_SUPPLIER_NOT_CONFIG' => [
            'description' => 'Supplier ID not configured in N8n',
            'recovery' => 'Manual investigation',
            'max_retries' => 0,
        ],
    ];

    /**
     * System errors - Infrastructure-level failures
     * Affect multiple documents, require immediate escalation
     */
    const SYSTEM_ERRORS = [
        'ERR_N8N_UNAVAILABLE' => [
            'description' => 'N8n service offline',
            'recovery' => 'Escalate to ops',
            'severity' => 'critical',
        ],
        'ERR_CALLBACK_UNREACHABLE' => [
            'description' => 'Laravel callback URL unreachable',
            'recovery' => 'Wait for recovery',
            'severity' => 'critical',
        ],
        'ERR_DATABASE_ERROR' => [
            'description' => 'Database connection lost',
            'recovery' => 'Escalate to infrastructure',
            'severity' => 'critical',
        ],
        'ERR_AUTH_FAILURE' => [
            'description' => 'Invalid API credentials',
            'recovery' => 'Manual fix',
            'severity' => 'high',
        ],
        'ERR_RESOURCE_EXHAUSTION' => [
            'description' => 'Memory/CPU exhaustion',
            'recovery' => 'Scale horizontally',
            'severity' => 'critical',
        ],
    ];

    /**
     * Get all error codes
     */
    public static function getAllCodes(): array
    {
        return array_merge(
            array_keys(self::TRANSIENT_ERRORS),
            array_keys(self::NON_TRANSIENT_ERRORS),
            array_keys(self::SYSTEM_ERRORS)
        );
    }

    /**
     * Check if error code is transient (retriable)
     */
    public static function isTransient(string $errorCode): bool
    {
        return array_key_exists($errorCode, self::TRANSIENT_ERRORS);
    }

    /**
     * Check if error code is non-transient
     */
    public static function isNonTransient(string $errorCode): bool
    {
        return array_key_exists($errorCode, self::NON_TRANSIENT_ERRORS);
    }

    /**
     * Check if error code is system error
     */
    public static function isSystemError(string $errorCode): bool
    {
        return array_key_exists($errorCode, self::SYSTEM_ERRORS);
    }

    /**
     * Get error details
     */
    public static function getErrorDetails(string $errorCode): ?array
    {
        if (self::isTransient($errorCode)) {
            return self::TRANSIENT_ERRORS[$errorCode];
        }

        if (self::isNonTransient($errorCode)) {
            return self::NON_TRANSIENT_ERRORS[$errorCode];
        }

        if (self::isSystemError($errorCode)) {
            return self::SYSTEM_ERRORS[$errorCode];
        }

        return null;
    }

    /**
     * Get max retry count for error code
     */
    public static function getMaxRetries(string $errorCode): int
    {
        $details = self::getErrorDetails($errorCode);
        return $details['max_retries'] ?? 0;
    }

    /**
     * Get error category (transient/non_transient/system)
     */
    public static function getCategory(string $errorCode): string
    {
        if (self::isTransient($errorCode)) {
            return 'transient';
        }

        if (self::isNonTransient($errorCode)) {
            return 'non_transient';
        }

        if (self::isSystemError($errorCode)) {
            return 'system';
        }

        return 'system'; // Default to system for unknown errors
    }
}
