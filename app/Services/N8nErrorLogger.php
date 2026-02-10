<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

class N8nErrorLogger
{
    // Error categories from ERROR_HANDLING_ARCHITECTURE.md
    const CATEGORY_TRANSIENT = 'transient';
    const CATEGORY_NON_TRANSIENT = 'non-transient';
    const CATEGORY_SYSTEM = 'system';

    // Error codes registry
    const ERROR_CODES = [
        // Transient errors
        'ERR_TIMEOUT' => self::CATEGORY_TRANSIENT,
        'ERR_SERVICE_UNAVAILABLE' => self::CATEGORY_TRANSIENT,
        'ERR_RATE_LIMIT' => self::CATEGORY_TRANSIENT,
        'ERR_FILE_TEMP_UNAVAILABLE' => self::CATEGORY_TRANSIENT,
        'ERR_NETWORK_TRANSIENT' => self::CATEGORY_TRANSIENT,

        // Non-transient errors
        'ERR_PARSE_FAILURE' => self::CATEGORY_NON_TRANSIENT,
        'ERR_VALIDATION_FAILURE' => self::CATEGORY_NON_TRANSIENT,
        'ERR_UNSUPPORTED_FORMAT' => self::CATEGORY_NON_TRANSIENT,
        'ERR_FILE_NOT_FOUND' => self::CATEGORY_NON_TRANSIENT,
        'ERR_INSUFFICIENT_DATA' => self::CATEGORY_NON_TRANSIENT,
        'ERR_HMAC_INVALID' => self::CATEGORY_NON_TRANSIENT,
        'ERR_SUPPLIER_NOT_CONFIG' => self::CATEGORY_NON_TRANSIENT,

        // System errors
        'ERR_N8N_UNAVAILABLE' => self::CATEGORY_SYSTEM,
        'ERR_CALLBACK_UNREACHABLE' => self::CATEGORY_SYSTEM,
        'ERR_DATABASE_ERROR' => self::CATEGORY_SYSTEM,
        'ERR_AUTH_FAILURE' => self::CATEGORY_SYSTEM,
        'ERR_RESOURCE_EXHAUSTION' => self::CATEGORY_SYSTEM,
    ];

    /**
     * Log N8n processing error with structured format
     */
    public function logError(
        string $errorCode,
        string $errorMessage,
        ?string $documentId = null,
        ?string $supplierId = null,
        ?int $companyId = null,
        ?string $documentType = null,
        array $context = [],
        ?Throwable $exception = null
    ): void {
        $category = self::ERROR_CODES[$errorCode] ?? self::CATEGORY_NON_TRANSIENT;
        $level = $this->getLogLevel($category);

        $logData = [
            'timestamp' => now()->toIso8601String(),
            'level' => strtoupper($level),
            'source' => 'n8n',
            'request_id' => request()->header('X-Request-ID') ?? uniqid('req-', true),
            'supplier_id' => $supplierId,
            'company_id' => $companyId,
            'document_id' => $documentId,
            'document_type' => $documentType,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'error_category' => $category,
            'context' => array_merge($context, [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]),
        ];

        // Add exception details if provided
        if ($exception) {
            $logData['context']['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];

            if (config('webhook.error_logging.include_stack_trace', true)) {
                $logData['context']['stack_trace'] = $exception->getTraceAsString();
            }
        }

        // Log with appropriate level
        Log::channel(config('webhook.error_logging.log_channel', 'stack'))
            ->{$level}('[N8n Error] ' . $errorMessage, $logData);
    }

    /**
     * Log successful N8n processing
     */
    public function logSuccess(
        string $documentId,
        string $supplierId,
        int $companyId,
        string $documentType,
        array $extractedData = [],
        array $context = []
    ): void {
        $logData = [
            'timestamp' => now()->toIso8601String(),
            'level' => 'INFO',
            'source' => 'n8n',
            'request_id' => request()->header('X-Request-ID') ?? uniqid('req-', true),
            'supplier_id' => $supplierId,
            'company_id' => $companyId,
            'document_id' => $documentId,
            'document_type' => $documentType,
            'status' => 'success',
            'extracted_data_keys' => array_keys($extractedData),
            'context' => $context,
        ];

        Log::channel(config('webhook.error_logging.log_channel', 'stack'))
            ->info('[N8n Success] Document processed', $logData);
    }

    /**
     * Determine log level based on error category
     */
    private function getLogLevel(string $category): string
    {
        return match ($category) {
            self::CATEGORY_TRANSIENT => 'warning',
            self::CATEGORY_NON_TRANSIENT => 'error',
            self::CATEGORY_SYSTEM => 'critical',
            default => 'error',
        };
    }

    /**
     * Check if error code is retriable
     */
    public function isRetriable(string $errorCode): bool
    {
        $category = self::ERROR_CODES[$errorCode] ?? self::CATEGORY_NON_TRANSIENT;
        return $category === self::CATEGORY_TRANSIENT;
    }

    /**
     * Get error category for error code
     */
    public function getErrorCategory(string $errorCode): string
    {
        return self::ERROR_CODES[$errorCode] ?? self::CATEGORY_NON_TRANSIENT;
    }

    /**
     * Format error response for N8n callback
     */
    public function formatErrorResponse(
        string $errorCode,
        string $errorMessage,
        string $documentId,
        string $supplierId,
        int $companyId,
        string $documentType,
        array $context = []
    ): array {
        $category = $this->getErrorCategory($errorCode);

        return [
            'request_id' => request()->header('X-Request-ID') ?? uniqid('req-', true),
            'status' => 'error',
            'document_id' => $documentId,
            'supplier_id' => $supplierId,
            'company_id' => $companyId,
            'document_type' => $documentType,
            'error' => [
                'code' => $errorCode,
                'message' => $errorMessage,
                'category' => $category,
                'retriable' => $this->isRetriable($errorCode),
                'context' => $context,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
