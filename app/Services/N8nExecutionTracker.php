<?php

namespace App\Services;

use App\Models\DocumentProcessingLog;
use App\Models\DocumentError;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class N8nExecutionTracker
{
    /**
     * Error type mapping from error codes
     */
    private const ERROR_TYPE_MAP = [
        // Transient errors (retriable)
        'ERR_TIMEOUT' => DocumentError::TYPE_TRANSIENT,
        'ERR_SERVICE_UNAVAILABLE' => DocumentError::TYPE_TRANSIENT,
        'ERR_RATE_LIMIT' => DocumentError::TYPE_TRANSIENT,
        'ERR_FILE_TEMP_UNAVAILABLE' => DocumentError::TYPE_TRANSIENT,
        'ERR_NETWORK_TRANSIENT' => DocumentError::TYPE_TRANSIENT,

        // Non-transient errors (manual intervention)
        'ERR_PARSE_FAILURE' => DocumentError::TYPE_NON_TRANSIENT,
        'ERR_VALIDATION_FAILURE' => DocumentError::TYPE_NON_TRANSIENT,
        'ERR_UNSUPPORTED_FORMAT' => DocumentError::TYPE_NON_TRANSIENT,
        'ERR_FILE_NOT_FOUND' => DocumentError::TYPE_NON_TRANSIENT,
        'ERR_INSUFFICIENT_DATA' => DocumentError::TYPE_NON_TRANSIENT,
        'ERR_HMAC_INVALID' => DocumentError::TYPE_NON_TRANSIENT,
        'ERR_SUPPLIER_NOT_CONFIG' => DocumentError::TYPE_NON_TRANSIENT,

        // System errors (critical)
        'ERR_N8N_UNAVAILABLE' => DocumentError::TYPE_SYSTEM,
        'ERR_CALLBACK_UNREACHABLE' => DocumentError::TYPE_SYSTEM,
        'ERR_DATABASE_ERROR' => DocumentError::TYPE_SYSTEM,
        'ERR_AUTH_FAILURE' => DocumentError::TYPE_SYSTEM,
        'ERR_RESOURCE_EXHAUSTION' => DocumentError::TYPE_SYSTEM,
    ];

    /**
     * Start execution tracking
     *
     * @param string $documentId
     * @param array $payload
     * @param string|null $n8nWorkflowId
     * @return DocumentProcessingLog
     */
    public function startExecution(string $documentId, array $payload, ?string $n8nWorkflowId = null): DocumentProcessingLog
    {
        try {
            $log = DocumentProcessingLog::where('document_id', $documentId)->first();

            if (!$log) {
                throw new \Exception("Document not found: {$documentId}");
            }

            $log->update([
                'status' => 'processing',
                'started_at' => now(),
                'input_payload' => $payload,
                'n8n_workflow_id' => $n8nWorkflowId ?? $log->n8n_workflow_id,
            ]);

            Log::info('N8n execution started', [
                'document_id' => $documentId,
                'workflow_id' => $n8nWorkflowId,
                'started_at' => $log->started_at,
            ]);

            return $log->fresh();
        } catch (\Exception $e) {
            Log::error('Failed to start execution tracking', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Complete execution with success
     *
     * @param string $documentId
     * @param array $result
     * @param string|null $executionId
     * @return DocumentProcessingLog
     */
    public function completeExecution(string $documentId, array $result, ?string $executionId = null): DocumentProcessingLog
    {
        try {
            $log = DocumentProcessingLog::where('document_id', $documentId)->first();

            if (!$log) {
                throw new \Exception("Document not found: {$documentId}");
            }

            $completedAt = now();
            $duration = $log->started_at
                ? $log->started_at->diffInMilliseconds($completedAt)
                : null;

            $log->update([
                'status' => 'completed',
                'completed_at' => $completedAt,
                'duration_ms' => $duration,
                'output_data' => $result,
                'extraction_result' => $result['extracted_tasks'] ?? null,
                'n8n_execution_id' => $executionId ?? $log->n8n_execution_id,
                'callback_received_at' => $completedAt,
            ]);

            Log::info('N8n execution completed', [
                'document_id' => $documentId,
                'execution_id' => $executionId,
                'duration_ms' => $duration,
                'status' => 'completed',
            ]);

            return $log->fresh();
        } catch (\Exception $e) {
            Log::error('Failed to complete execution tracking', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mark execution as failed with error details
     *
     * @param string $documentId
     * @param array $error
     * @param string|null $executionId
     * @return DocumentProcessingLog
     */
    public function failExecution(string $documentId, array $error, ?string $executionId = null): DocumentProcessingLog
    {
        try {
            DB::beginTransaction();

            $log = DocumentProcessingLog::where('document_id', $documentId)->first();

            if (!$log) {
                throw new \Exception("Document not found: {$documentId}");
            }

            $completedAt = now();
            $duration = $log->started_at
                ? $log->started_at->diffInMilliseconds($completedAt)
                : null;

            $errorCode = $error['code'] ?? 'ERR_UNKNOWN';
            $errorType = $this->getErrorType($errorCode);

            // Update log with failure details
            $log->update([
                'status' => 'failed',
                'completed_at' => $completedAt,
                'duration_ms' => $duration,
                'error_code' => $errorCode,
                'error_message' => $error['message'] ?? 'Unknown error',
                'error_context' => $error['context'] ?? null,
                'output_data' => $error,
                'n8n_execution_id' => $executionId ?? $log->n8n_execution_id,
                'callback_received_at' => $completedAt,
                'needs_review' => true, // Auto-flag for review (ERR-03)
            ]);

            // Create detailed error record (ERR-02)
            DocumentError::create([
                'document_processing_log_id' => $log->id,
                'error_type' => $errorType,
                'error_code' => $errorCode,
                'error_message' => $error['message'] ?? 'Unknown error',
                'stack_trace' => $error['stack_trace'] ?? null,
                'input_context' => [
                    'payload' => $log->input_payload,
                    'execution_id' => $executionId,
                    'workflow_id' => $log->n8n_workflow_id,
                    'failed_at_node' => $error['context']['failed_at_node'] ?? null,
                ],
                'retry_count' => 0,
            ]);

            DB::commit();

            Log::error('N8n execution failed', [
                'document_id' => $documentId,
                'execution_id' => $executionId,
                'error_code' => $errorCode,
                'error_type' => $errorType,
                'duration_ms' => $duration,
            ]);

            return $log->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to track execution failure', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get execution metrics for a timeframe
     *
     * @param string $timeframe ('hour', 'day', 'week', 'month')
     * @param int|null $companyId
     * @param string|null $supplierId
     * @return array
     */
    public function getExecutionMetrics(string $timeframe = 'day', ?int $companyId = null, ?string $supplierId = null): array
    {
        $startTime = match($timeframe) {
            'hour' => now()->subHour(),
            'day' => now()->subDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subDay(),
        };

        $query = DocumentProcessingLog::where('started_at', '>=', $startTime);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $total = $query->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $failed = (clone $query)->where('status', 'failed')->count();
        $processing = (clone $query)->where('status', 'processing')->count();

        $avgDuration = (clone $query)
            ->whereNotNull('duration_ms')
            ->avg('duration_ms');

        $p95Duration = (clone $query)
            ->whereNotNull('duration_ms')
            ->orderBy('duration_ms', 'desc')
            ->skip((int)($total * 0.05))
            ->first()
            ?->duration_ms;

        // Error breakdown
        $errorsByType = DocumentError::whereHas('documentProcessingLog', function ($q) use ($startTime, $companyId, $supplierId) {
            $q->where('started_at', '>=', $startTime);
            if ($companyId) $q->where('company_id', $companyId);
            if ($supplierId) $q->where('supplier_id', $supplierId);
        })
        ->select('error_type', DB::raw('count(*) as count'))
        ->groupBy('error_type')
        ->pluck('count', 'error_type')
        ->toArray();

        $errorsByCode = DocumentError::whereHas('documentProcessingLog', function ($q) use ($startTime, $companyId, $supplierId) {
            $q->where('started_at', '>=', $startTime);
            if ($companyId) $q->where('company_id', $companyId);
            if ($supplierId) $q->where('supplier_id', $supplierId);
        })
        ->select('error_code', DB::raw('count(*) as count'))
        ->groupBy('error_code')
        ->orderBy('count', 'desc')
        ->limit(10)
        ->pluck('count', 'error_code')
        ->toArray();

        return [
            'timeframe' => $timeframe,
            'period_start' => $startTime->toIso8601String(),
            'period_end' => now()->toIso8601String(),
            'total_executions' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'processing' => $processing,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'avg_duration_ms' => $avgDuration ? round($avgDuration, 2) : null,
            'p95_duration_ms' => $p95Duration,
            'errors_by_type' => [
                'transient' => $errorsByType[DocumentError::TYPE_TRANSIENT] ?? 0,
                'non_transient' => $errorsByType[DocumentError::TYPE_NON_TRANSIENT] ?? 0,
                'system' => $errorsByType[DocumentError::TYPE_SYSTEM] ?? 0,
            ],
            'top_error_codes' => $errorsByCode,
        ];
    }

    /**
     * Get error type from error code
     *
     * @param string $errorCode
     * @return string
     */
    private function getErrorType(string $errorCode): string
    {
        return self::ERROR_TYPE_MAP[$errorCode] ?? DocumentError::TYPE_SYSTEM;
    }
}
