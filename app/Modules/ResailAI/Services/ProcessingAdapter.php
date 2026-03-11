<?php

namespace App\Modules\ResailAI\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessingAdapter
{
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
     * Process the extraction result from ResailAI callback.
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
                'error' => $errorMessage,
                'attempts' => $attempt,
            ]);
            return false;
        }

        Log::warning('[ResailAI] Callback error, will retry', [
            'document_id' => $documentId,
            'error' => $errorMessage,
            'attempt' => $attempt,
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
                    'payload_keys' => array_keys($payload),
                ]);
                return false;
            }
        }

        if (!in_array($payload['status'], ['success', 'error', 'pending'])) {
            Log::warning('[ResailAI] Invalid status in callback', [
                'document_id' => $payload['document_id'] ?? 'unknown',
                'status' => $payload['status'] ?? 'missing',
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
