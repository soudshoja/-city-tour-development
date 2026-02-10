<?php

namespace Tests\Fixtures;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * N8nResponseFactory - Factory for creating mock N8n workflow responses
 *
 * Provides methods to generate realistic N8n callback payloads for testing
 * document processing workflows including success, failure, and edge cases.
 */
class N8nResponseFactory
{
    /**
     * Response status constants
     */
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILURE = 'failure';
    const STATUS_TIMEOUT = 'timeout';
    const STATUS_DEFERRED = 'deferred';

    /**
     * Error code constants
     */
    const ERROR_INVALID_FORMAT = 'ERR_INVALID_FORMAT';
    const ERROR_UNSUPPORTED_TYPE = 'ERR_UNSUPPORTED_TYPE';
    const ERROR_EXTRACTION_FAILED = 'ERR_EXTRACTION_FAILED';
    const ERROR_TIMEOUT = 'ERR_TIMEOUT';
    const ERROR_RATE_LIMIT = 'ERR_RATE_LIMIT';
    const ERROR_SERVICE_UNAVAILABLE = 'ERR_SERVICE_UNAVAILABLE';
    const ERROR_CORRUPTED_FILE = 'ERR_CORRUPTED_FILE';
    const ERROR_MISSING_FIELDS = 'ERR_MISSING_FIELDS';

    /**
     * Create a successful extraction response
     *
     * @param string $documentId - UUID of the processed document
     * @param array $extractedData - Extracted/parsed data from the document
     * @param array $options - Optional configuration
     *                         - documentType: Document type (air, pdf, image, email)
     *                         - processingTimeMs: Processing time in milliseconds
     *                         - confidenceScore: Confidence score (0-1)
     *                         - metadata: Additional metadata
     * @return array - Success response payload
     */
    public static function success(
        string $documentId,
        array $extractedData,
        array $options = []
    ): array {
        $documentType = $options['documentType'] ?? 'pdf';
        $processingTime = $options['processingTimeMs'] ?? rand(500, 5000);
        $confidence = $options['confidenceScore'] ?? rand(85, 99) / 100;

        return [
            'success' => true,
            'status' => self::STATUS_SUCCESS,
            'documentId' => $documentId,
            'documentType' => $documentType,
            'executionId' => self::generateExecutionId(),
            'workflowId' => $options['workflowId'] ?? 'workflow_document_processor',
            'timestamp' => now()->toIso8601String(),
            'extractedData' => $extractedData,
            'processingTimeMs' => $processingTime,
            'confidenceScore' => $confidence,
            'metadata' => $options['metadata'] ?? [
                'source' => 'n8n',
                'version' => '1.0',
                'processor' => 'document_extraction_v' . rand(1, 3),
            ],
        ];
    }

    /**
     * Create a failure response
     *
     * @param string $documentId - UUID of the document that failed
     * @param string $errorCode - Error code constant (ERR_*)
     * @param string $errorMessage - Human-readable error message
     * @param array $options - Optional configuration
     *                         - errorContext: Additional error context
     *                         - stackTrace: Stack trace from the service
     *                         - metadata: Additional metadata
     * @return array - Failure response payload
     */
    public static function failure(
        string $documentId,
        string $errorCode = self::ERROR_EXTRACTION_FAILED,
        string $errorMessage = null,
        array $options = []
    ): array {
        $errorMessage ??= self::getErrorMessageForCode($errorCode);

        return [
            'success' => false,
            'status' => 'failed',
            'documentId' => $documentId,
            'documentType' => $options['documentType'] ?? 'pdf',
            'executionId' => self::generateExecutionId(),
            'workflowId' => $options['workflowId'] ?? 'workflow_document_processor',
            'timestamp' => now()->toIso8601String(),
            'errorCode' => $errorCode,
            'errorMessage' => $errorMessage,
            'errorContext' => $options['errorContext'] ?? [
                'phase' => 'extraction',
                'retryable' => self::isRetryableError($errorCode),
            ],
            'stackTrace' => $options['stackTrace'] ?? null,
            'metadata' => $options['metadata'] ?? [
                'source' => 'n8n',
                'version' => '1.0',
            ],
        ];
    }

    /**
     * Create a timeout response
     *
     * @param string $documentId - UUID of the document
     * @param array $options - Optional configuration
     *                         - timeoutMs: Timeout duration (default: 30000)
     *                         - partialResult: Any partial extraction before timeout
     * @return array - Timeout response payload
     */
    public static function timeout(
        string $documentId,
        array $options = []
    ): array {
        $timeoutMs = $options['timeoutMs'] ?? 30000;

        return [
            'success' => false,
            'status' => self::STATUS_TIMEOUT,
            'documentId' => $documentId,
            'documentType' => $options['documentType'] ?? 'pdf',
            'executionId' => self::generateExecutionId(),
            'workflowId' => $options['workflowId'] ?? 'workflow_document_processor',
            'timestamp' => now()->toIso8601String(),
            'errorCode' => self::ERROR_TIMEOUT,
            'errorMessage' => "Processing timeout exceeded ({$timeoutMs}ms)",
            'timeoutMs' => $timeoutMs,
            'partialResult' => $options['partialResult'] ?? null,
            'retryable' => true,
            'metadata' => $options['metadata'] ?? [
                'source' => 'n8n',
                'version' => '1.0',
            ],
        ];
    }

    /**
     * Create a deferred response (for AIR files requiring manual processing)
     *
     * @param string $documentId - UUID of the document
     * @param string $reason - Reason for deferral
     * @param array $options - Optional configuration
     *                         - retryAfterMs: Milliseconds to wait before retry
     *                         - priority: Priority level (1-10)
     * @return array - Deferred response payload
     */
    public static function deferred(
        string $documentId,
        string $reason = 'Complex AIR file requires manual review',
        array $options = []
    ): array {
        $retryAfter = $options['retryAfterMs'] ?? rand(30000, 300000);

        return [
            'success' => false,
            'status' => self::STATUS_DEFERRED,
            'documentId' => $documentId,
            'documentType' => $options['documentType'] ?? 'air',
            'executionId' => self::generateExecutionId(),
            'workflowId' => $options['workflowId'] ?? 'workflow_document_processor',
            'timestamp' => now()->toIso8601String(),
            'reason' => $reason,
            'retryAfterMs' => $retryAfter,
            'priority' => $options['priority'] ?? 5,
            'deferredAt' => now()->toIso8601String(),
            'metadata' => $options['metadata'] ?? [
                'source' => 'n8n',
                'version' => '1.0',
                'deferral_code' => 'AIR_REVIEW_REQUIRED',
            ],
        ];
    }

    /**
     * Create a response for invalid file format
     *
     * @param string $documentId - UUID of the document
     * @param string $detectedFormat - What format was detected (if any)
     * @return array - Failure response with format error
     */
    public static function invalidFormat(
        string $documentId,
        string $detectedFormat = null
    ): array {
        $message = "Invalid document format";
        if ($detectedFormat) {
            $message .= " (detected: $detectedFormat)";
        }

        return self::failure(
            $documentId,
            self::ERROR_INVALID_FORMAT,
            $message,
            [
                'errorContext' => [
                    'phase' => 'validation',
                    'detectedFormat' => $detectedFormat,
                ]
            ]
        );
    }

    /**
     * Create a response for corrupted file
     *
     * @param string $documentId - UUID of the document
     * @param string $reason - Specific reason for corruption (e.g., "Missing EOF marker")
     * @return array - Failure response with corruption error
     */
    public static function corruptedFile(
        string $documentId,
        string $reason = 'File appears to be corrupted or truncated'
    ): array {
        return self::failure(
            $documentId,
            self::ERROR_CORRUPTED_FILE,
            $reason,
            [
                'errorContext' => [
                    'phase' => 'file_validation',
                    'reason' => $reason,
                ]
            ]
        );
    }

    /**
     * Create a response for missing required fields
     *
     * @param string $documentId - UUID of the document
     * @param array $missingFields - List of missing field names
     * @return array - Failure response with missing fields error
     */
    public static function missingFields(
        string $documentId,
        array $missingFields
    ): array {
        $fieldList = implode(', ', $missingFields);

        return self::failure(
            $documentId,
            self::ERROR_MISSING_FIELDS,
            "Missing required fields: $fieldList",
            [
                'errorContext' => [
                    'phase' => 'extraction',
                    'missingFields' => $missingFields,
                ]
            ]
        );
    }

    /**
     * Create a rate limit response
     *
     * @param string $documentId - UUID of the document
     * @param int $retryAfterSeconds - Seconds to wait before retry
     * @return array - Failure response with rate limit error
     */
    public static function rateLimited(
        string $documentId,
        int $retryAfterSeconds = 60
    ): array {
        return self::failure(
            $documentId,
            self::ERROR_RATE_LIMIT,
            "Rate limit exceeded. Please retry after $retryAfterSeconds seconds.",
            [
                'errorContext' => [
                    'retryAfterSeconds' => $retryAfterSeconds,
                    'retryable' => true,
                ]
            ]
        );
    }

    /**
     * Create a service unavailable response
     *
     * @param string $documentId - UUID of the document
     * @param string $service - Service name (e.g., "PDF Parser", "OCR Engine")
     * @return array - Failure response with service unavailable error
     */
    public static function serviceUnavailable(
        string $documentId,
        string $service = 'Document Processing Service'
    ): array {
        return self::failure(
            $documentId,
            self::ERROR_SERVICE_UNAVAILABLE,
            "$service is currently unavailable. Please retry later.",
            [
                'errorContext' => [
                    'service' => $service,
                    'retryable' => true,
                ]
            ]
        );
    }

    /**
     * Create a partially successful response
     * Used when some data could be extracted but with warnings
     *
     * @param string $documentId - UUID of the document
     * @param array $extractedData - Partial extracted data
     * @param array $warnings - List of warning messages
     * @param array $options - Optional configuration
     * @return array - Partial success response
     */
    public static function partialSuccess(
        string $documentId,
        array $extractedData,
        array $warnings = [],
        array $options = []
    ): array {
        return [
            'success' => true,
            'status' => 'partial_success',
            'documentId' => $documentId,
            'documentType' => $options['documentType'] ?? 'pdf',
            'executionId' => self::generateExecutionId(),
            'workflowId' => $options['workflowId'] ?? 'workflow_document_processor',
            'timestamp' => now()->toIso8601String(),
            'extractedData' => $extractedData,
            'warnings' => $warnings,
            'processingTimeMs' => $options['processingTimeMs'] ?? rand(500, 5000),
            'confidenceScore' => $options['confidenceScore'] ?? 0.70,
            'metadata' => $options['metadata'] ?? [
                'source' => 'n8n',
                'version' => '1.0',
            ],
        ];
    }

    /**
     * Create a batch processing response
     * For testing multiple documents processed in one N8n execution
     *
     * @param array $documents - Array of document responses
     *                           Each should be from success(), failure(), etc.
     * @param array $options - Optional configuration
     * @return array - Batch response payload
     */
    public static function batch(array $documents, array $options = []): array
    {
        $successCount = count(array_filter($documents, fn($d) => $d['success'] ?? false));
        $failureCount = count($documents) - $successCount;

        return [
            'batchId' => self::generateBatchId(),
            'executionId' => self::generateExecutionId(),
            'workflowId' => $options['workflowId'] ?? 'workflow_batch_processor',
            'timestamp' => now()->toIso8601String(),
            'documents' => $documents,
            'summary' => [
                'total' => count($documents),
                'successful' => $successCount,
                'failed' => $failureCount,
                'successRate' => $successCount / count($documents),
            ],
            'metadata' => $options['metadata'] ?? [
                'source' => 'n8n',
                'version' => '1.0',
            ],
        ];
    }

    /**
     * Generate a realistic execution ID
     *
     * @return string
     */
    protected static function generateExecutionId(): string
    {
        return 'exec_' . strtoupper(Str::random(16)) . '_' . time();
    }

    /**
     * Generate a batch ID
     *
     * @return string
     */
    protected static function generateBatchId(): string
    {
        return 'batch_' . strtoupper(Str::random(12)) . '_' . time();
    }

    /**
     * Get error message for error code
     *
     * @param string $errorCode
     * @return string
     */
    protected static function getErrorMessageForCode(string $errorCode): string
    {
        return match ($errorCode) {
            self::ERROR_INVALID_FORMAT => 'Invalid or unsupported document format',
            self::ERROR_UNSUPPORTED_TYPE => 'Document type is not supported',
            self::ERROR_EXTRACTION_FAILED => 'Failed to extract data from document',
            self::ERROR_TIMEOUT => 'Processing timeout exceeded',
            self::ERROR_RATE_LIMIT => 'Rate limit exceeded, please retry later',
            self::ERROR_SERVICE_UNAVAILABLE => 'Service unavailable, please retry later',
            self::ERROR_CORRUPTED_FILE => 'File appears to be corrupted or invalid',
            self::ERROR_MISSING_FIELDS => 'Document is missing required fields',
            default => 'An unknown error occurred',
        };
    }

    /**
     * Determine if an error code is retryable
     *
     * @param string $errorCode
     * @return bool
     */
    protected static function isRetryableError(string $errorCode): bool
    {
        $retryableErrors = [
            self::ERROR_TIMEOUT,
            self::ERROR_RATE_LIMIT,
            self::ERROR_SERVICE_UNAVAILABLE,
        ];

        return in_array($errorCode, $retryableErrors);
    }
}
