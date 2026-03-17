<?php

namespace App\Modules\ResailAI\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessingAdapter
{
    /**
     * Metadata from the last extraction result (if present).
     */
    protected ?array $lastMetadata = null;

    /**
     * Check if PDF processing is enabled for a supplier/company combination.
     */
    public static function isPdfProcessingEnabled(?int $supplierId, ?int $companyId): bool
    {
        if ($supplierId === null || $companyId === null) {
            return false;
        }

        $result = DB::table('supplier_companies')
            ->where('supplier_id', $supplierId)
            ->where('company_id', $companyId)
            ->value('auto_process_pdf');

        return (bool) $result;
    }

    /**
     * Flatten the full callback payload into an array of task payloads ready for TaskWebhookBridge.
     *
     * Handles two inbound formats from n8n/ResailAI:
     *
     * Format A — Nested (extraction_result.tasks[] contains task objects):
     * {
     *   "document_id": "...", "supplier_id": 1, ...,
     *   "extraction_result": {
     *     "tasks": [{"reference": "...", "type": "flight", "task_flight_details": [...]}],
     *     "metadata": {"processor": "tika", "confidence": 0.95}
     *   }
     * }
     *
     * Format B — Flat (task fields at top level of extraction_result or callback root):
     * {
     *   "document_id": "...", "supplier_id": 1, ...,
     *   "extraction_result": {"reference": "...", "type": "flight", "task_flight_details": [...]}
     * }
     *
     * Each returned element contains all fields needed by TaskWebhookBridge.processExtraction():
     * document_id, reference, type, company_id, supplier_id, agent_id, branch_id, status,
     * price, total, tax, exchange_currency, client_name, and type-specific detail arrays.
     *
     * @param  array  $callbackPayload  The full validated callback payload
     * @return array  Array of task payloads (one element per task)
     */
    public function flattenExtractionResult(array $callbackPayload): array
    {
        $contextFields = [
            'document_id' => $callbackPayload['document_id'] ?? null,
            'supplier_id' => $callbackPayload['supplier_id'] ?? null,
            'company_id'  => $callbackPayload['company_id'] ?? null,
            'agent_id'    => $callbackPayload['agent_id'] ?? null,
            'branch_id'   => $callbackPayload['branch_id'] ?? null,
            'status'      => $callbackPayload['status'] ?? null,
        ];

        // Use extraction_result if present; otherwise treat the callback root as the extraction
        $extraction = $callbackPayload['extraction_result'] ?? $callbackPayload;

        // Store metadata for later logging if present
        if (isset($extraction['metadata']) && is_array($extraction['metadata'])) {
            $this->lastMetadata = $extraction['metadata'];
        }

        // Format A — nested tasks array
        if (isset($extraction['tasks']) && is_array($extraction['tasks']) && count($extraction['tasks']) > 0) {
            $results = [];
            foreach ($extraction['tasks'] as $task) {
                $results[] = array_merge($contextFields, $task);
            }

            Log::info('[ResailAI] Flattened nested extraction result', [
                'document_id' => $contextFields['document_id'],
                'task_count'  => count($results),
            ]);

            return $results;
        }

        // Format B — flat (remove meta-only keys that are not task fields)
        $taskFields = array_diff_key($extraction, array_flip(['tasks', 'metadata']));
        $merged = [array_merge($contextFields, $taskFields)];

        Log::info('[ResailAI] Flattened flat extraction result', [
            'document_id' => $contextFields['document_id'],
            'task_count'  => 1,
        ]);

        return $merged;
    }

    /**
     * Get metadata stored from the last flattenExtractionResult() call.
     *
     * @return array|null
     */
    public function getLastMetadata(): ?array
    {
        return $this->lastMetadata;
    }

    /**
     * Process the extraction result from ResailAI callback.
     *
     * @deprecated Use flattenExtractionResult() instead for proper context merging.
     *
     * @param  array  $extractionResult  The extraction data from ResailAI
     * @return array  Processed data ready for TaskWebhookBridge
     */
    public function processExtractionResult(array $extractionResult): array
    {
        $processed = $extractionResult;

        // Apply any needed transformations here
        // Example: Normalize currency codes, format dates, etc.

        return $processed;
    }

    /**
     * Handle callback error scenarios.
     *
     * @param  string  $documentId  The document identifier
     * @param  string  $errorMessage  Error message from extraction
     * @param  int  $attempt  Current attempt number
     * @return bool  True if should retry, false otherwise
     */
    public function handleCallbackError(string $documentId, string $errorMessage, int $attempt = 1): bool
    {
        $maxRetries = config('resailai.max_retries', 3);

        if ($attempt >= $maxRetries) {
            Log::error('[ResailAI] Max retries reached for document', [
                'document_id' => $documentId,
                'error'       => $errorMessage,
                'attempts'    => $attempt,
            ]);
            return false;
        }

        Log::warning('[ResailAI] Callback error, will retry', [
            'document_id' => $documentId,
            'error'        => $errorMessage,
            'attempt'      => $attempt,
            'max_attempts' => $maxRetries,
        ]);

        return true;
    }

    /**
     * Validate callback payload before processing.
     *
     * @param  array  $payload  The callback payload
     * @return bool  True if valid, false otherwise
     */
    public function validateCallbackPayload(array $payload): bool
    {
        $requiredFields = ['document_id', 'status'];

        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $payload)) {
                Log::warning('[ResailAI] Missing required field in callback', [
                    'missing_field' => $field,
                    'payload_keys'  => array_keys($payload),
                ]);
                return false;
            }
        }

        if (!in_array($payload['status'], ['success', 'error', 'pending'])) {
            Log::warning('[ResailAI] Invalid status in callback', [
                'document_id' => $payload['document_id'] ?? 'unknown',
                'status'      => $payload['status'] ?? 'missing',
            ]);
            return false;
        }

        return true;
    }

    /**
     * Get the expiry timestamp for a callback.
     */
    public function getCallbackExpiry(): \DateTime
    {
        $minutes = config('resailai.callback_expiry_minutes', 15);
        return now()->addMinutes($minutes);
    }

    /**
     * Check if a callback is still valid.
     *
     * @param  \DateTime  $issuedAt  When the callback was issued
     * @return bool  True if still valid
     */
    public function isCallbackValid(\DateTime $issuedAt): bool
    {
        $expiry = $this->getCallbackExpiry();
        return $issuedAt <= $expiry;
    }
}
