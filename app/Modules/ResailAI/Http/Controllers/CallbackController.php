<?php

namespace App\Modules\ResailAI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Modules\ResailAI\Services\ProcessingAdapter;
use App\Modules\ResailAI\Services\TaskWebhookBridge;
use App\Models\FileUpload;
use App\Models\ResailaiCredential;

class CallbackController extends Controller
{
    protected ProcessingAdapter $processingAdapter;
    protected TaskWebhookBridge $taskWebhookBridge;

    /**
     * Create a new controller instance.
     */
    public function __construct(ProcessingAdapter $processingAdapter, TaskWebhookBridge $taskWebhookBridge)
    {
        $this->processingAdapter = $processingAdapter;
        $this->taskWebhookBridge = $taskWebhookBridge;
    }

    /**
     * Handle ResailAI callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Validate input
            $validated = $request->validate([
                'document_id' => 'required|uuid',
                'status' => 'required|in:success,error,pending',
                'supplier_id' => 'nullable|integer',
                'company_id' => 'nullable|integer',
                'agent_id' => 'nullable|integer',
                'branch_id' => 'nullable|integer',
                'file_url' => 'nullable|string|url',
                'extraction_result' => 'nullable|array',
                'error' => 'nullable|array',
                'error.code' => 'required_if:status,error|string',
                'error.message' => 'required_if:status,error|string',
            ]);

            $documentId = $validated['document_id'];
            $status = $validated['status'];
            $supplierId = $validated['supplier_id'] ?? null;
            $companyId = $validated['company_id'] ?? null;

            Log::info('[ResailAI] Callback received', [
                'document_id' => $documentId,
                'status' => $status,
                'supplier_id' => $supplierId,
                'company_id' => $companyId,
            ]);

            // Check if feature flag is enabled for this supplier/company
            if ($supplierId && $companyId) {
                if (!$this->processingAdapter->isPdfProcessingEnabled($supplierId, $companyId)) {
                    Log::warning('[ResailAI] PDF processing not enabled for supplier/company', [
                        'document_id' => $documentId,
                        'supplier_id' => $supplierId,
                        'company_id' => $companyId,
                    ]);
                    return response()->json([
                        'message' => 'PDF processing not enabled',
                        'document_id' => $documentId,
                    ], 200);
                }
            }

            // Handle error status
            if ($status === 'error') {
                $errorCode = $validated['error']['code'] ?? 'UNKNOWN';
                $errorMessage = $validated['error']['message'] ?? 'Unknown error';

                Log::error('[ResailAI] Extraction failed', [
                    'document_id' => $documentId,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                ]);

                // Update FileUpload status to error
                $this->updateFileUploadStatus($documentId, 'error', $errorMessage);

                return response()->json([
                    'message' => 'Extraction failed',
                    'document_id' => $documentId,
                    'error' => [
                        'code' => $errorCode,
                        'message' => $errorMessage,
                    ],
                ], 200);
            }

            // Handle success status - process extraction results
            if ($status === 'success' && isset($validated['extraction_result'])) {
                $extractionResult = $validated['extraction_result'];

                Log::info('[ResailAI] Processing extraction result', [
                    'document_id' => $documentId,
                    'has_tasks' => isset($extractionResult['tasks']) ? count($extractionResult['tasks']) : 0,
                ]);

                // Transform extraction result via ProcessingAdapter
                $processedResult = $this->processingAdapter->processExtractionResult($extractionResult);

                // Call TaskWebhookBridge to process and create tasks
                $bridgeResult = $this->taskWebhookBridge->processExtraction($processedResult);

                if ($bridgeResult['success']) {
                    // Update FileUpload status to completed
                    $this->updateFileUploadStatus($documentId, 'completed');

                    Log::info('[ResailAI] Task created successfully', [
                        'document_id' => $documentId,
                        'response' => $bridgeResult['response'],
                    ]);

                    return response()->json([
                        'message' => 'Task created successfully',
                        'document_id' => $documentId,
                        'data' => $bridgeResult['response'],
                    ], 200);
                } else {
                    $errorMessage = $bridgeResult['error'] ?? 'Unknown error during task creation';

                    Log::error('[ResailAI] Task creation failed', [
                        'document_id' => $documentId,
                        'error' => $errorMessage,
                    ]);

                    // Update FileUpload status to error
                    $this->updateFileUploadStatus($documentId, 'error', $errorMessage);

                    return response()->json([
                        'message' => 'Task creation failed',
                        'document_id' => $documentId,
                        'error' => $errorMessage,
                    ], 200);
                }
            }

            // Handle pending status
            if ($status === 'pending') {
                Log::info('[ResailAI] Processing pending', [
                    'document_id' => $documentId,
                ]);

                // Update FileUpload status to pending
                $this->updateFileUploadStatus($documentId, 'pending');

                return response()->json([
                    'message' => 'Processing pending',
                    'document_id' => $documentId,
                ], 200);
            }

            // Fallback response
            Log::info('[ResailAI] Callback processed', [
                'document_id' => $documentId,
            ]);

            return response()->json([
                'message' => 'Callback processed',
                'document_id' => $documentId,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[ResailAI] Callback validation failed', $e->errors());

            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('[ResailAI] Callback processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update FileUpload status
     *
     * @param string $documentId FileUpload ID
     * @param string $status New status
     * @param string|null $errorMessage Error message if any
     * @return void
     */
    private function updateFileUploadStatus(string $documentId, string $status, ?string $errorMessage = null): void
    {
        $fileUpload = FileUpload::find($documentId);

        if ($fileUpload) {
            $fileUpload->update([
                'status' => $status,
            ]);

            Log::info('[ResailAI] FileUpload status updated', [
                'file_upload_id' => $fileUpload->id,
                'status' => $status,
            ]);
        } else {
            Log::warning('[ResailAI] FileUpload not found for status update', [
                'document_id' => $documentId,
                'status' => $status,
            ]);
        }
    }
}
