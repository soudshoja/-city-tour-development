<?php

namespace App\Modules\ResailAI\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Webhooks\TaskWebhook;
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
     *
     * @param  array  $extractionResult  The extraction data from ResailAI
     * @return array  Response from TaskWebhook
     */
    public function processExtraction(array $extractionResult): array
    {
        $documentId = $extractionResult['document_id'] ?? 'unknown';

        Log::info('[ResailAI] Processing extraction result via TaskWebhook', [
            'document_id' => $documentId,
        ]);

        try {
            // Transform extraction result to Request format
            $request = $this->buildRequestFromExtraction($extractionResult);

            // Log the request data for debugging
            Log::info('[ResailAI] Built request for TaskWebhook', [
                'document_id' => $documentId,
                'reference' => $request->input('reference'),
                'type' => $request->input('type'),
                'company_id' => $request->input('company_id'),
            ]);

            // Process via TaskWebhook
            $response = $this->taskWebhook->webhook($request);

            // Update document processing log
            $this->updateDocumentLog($documentId, 'completed');

            return [
                'success' => true,
                'response' => $response->getData(true),
            ];
        } catch (\Exception $e) {
            Log::error('[ResailAI] TaskWebhook processing failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update document processing log with error
            $this->updateDocumentLog($documentId, 'error', $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build a Laravel Request from extraction result.
     *
     * @param  array  $extractionResult  The extraction data from ResailAI
     * @return Request
     */
    protected function buildRequestFromExtraction(array $extractionResult): Request
    {
        $request = Request::create(
            '/api/internal/task-webhook',
            'POST',
            $this->transformExtractionToPayload($extractionResult)
        );

        // Set headers that might be needed
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    /**
     * Transform extraction result into payload array.
     *
     * @param  array  $extractionResult  The extraction data from ResailAI
     * @return array
     */
    protected function transformExtractionToPayload(array $extractionResult): array
    {
        $payload = [];

        // Basic task fields
        if (isset($extractionResult['reference'])) {
            $payload['reference'] = $extractionResult['reference'];
        }

        if (isset($extractionResult['status'])) {
            $payload['status'] = $extractionResult['status'];
        }

        if (isset($extractionResult['company_id'])) {
            $payload['company_id'] = $extractionResult['company_id'];
        }

        if (isset($extractionResult['supplier_id'])) {
            $payload['supplier_id'] = $extractionResult['supplier_id'];
        }

        if (isset($extractionResult['agent_id'])) {
            $payload['agent_id'] = $extractionResult['agent_id'];
        }

        if (isset($extractionResult['branch_id'])) {
            $payload['branch_id'] = $extractionResult['branch_id'];
        }

        // Task type detection
        if (!isset($extractionResult['type']) && isset($extractionResult['task_type'])) {
            $payload['type'] = $extractionResult['task_type'];
        } elseif (isset($extractionResult['type'])) {
            $payload['type'] = $extractionResult['type'];
        }

        // Original reference for refunds/voids
        if (isset($extractionResult['original_reference'])) {
            $payload['original_reference'] = $extractionResult['original_reference'];
        }

        // Passenger details
        if (isset($extractionResult['passenger_name'])) {
            $payload['passenger_name'] = $extractionResult['passenger_name'];
        }

        if (isset($extractionResult['passengers']) && is_array($extractionResult['passengers'])) {
            $payload['passengers'] = $extractionResult['passengers'];
        }

        // Currency conversion
        if (isset($extractionResult['exchange_currency'])) {
            $payload['exchange_currency'] = $extractionResult['exchange_currency'];
        }

        if (isset($extractionResult['exchange_rate'])) {
            $payload['exchange_rate'] = $extractionResult['exchange_rate'];
        }

        // Issue by (for Como Travels)
        if (isset($extractionResult['issued_by'])) {
            $payload['issued_by'] = $extractionResult['issued_by'];
        }

        // Client info
        if (isset($extractionResult['client_id'])) {
            $payload['client_id'] = $extractionResult['client_id'];
        }

        if (isset($extractionResult['client_name'])) {
            $payload['client_name'] = $extractionResult['client_name'];
        }

        // Flight details
        if (isset($extractionResult['flight_details'])) {
            $payload['flight_details'] = $extractionResult['flight_details'];
        }

        // Hotel details
        if (isset($extractionResult['hotel_details'])) {
            $payload['hotel_details'] = $extractionResult['hotel_details'];
        }

        // Visa details
        if (isset($extractionResult['visa_details'])) {
            $payload['visa_details'] = $extractionResult['visa_details'];
        }

        // Insurance details
        if (isset($extractionResult['insurance_details'])) {
            $payload['insurance_details'] = $extractionResult['insurance_details'];
        }

        return $payload;
    }

    /**
     * Update document processing log.
     *
     * @param  string  $documentId  The document identifier
     * @param  string  $status  Processing status
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
