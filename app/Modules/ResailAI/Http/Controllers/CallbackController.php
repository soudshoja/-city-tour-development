<?php

namespace App\Modules\ResailAI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Modules\ResailAI\Services\ProcessingAdapter;
use App\Modules\ResailAI\Services\TaskWebhookBridge;
use App\Models\DocumentProcessingLog;
use App\Models\FileUpload;

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
     * Handle ResailAI callback.
     *
     * Accepts both nested (extraction_result.tasks[]) and flat (fields at top level) payload formats.
     * Supports multi-task callbacks — one document may produce multiple extracted tasks.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Validate input — accept both nested and flat callback formats
            $validated = $request->validate([
                'document_id'            => 'required|uuid',
                'status'                 => 'required|in:success,error,pending',
                'supplier_id'            => 'nullable|integer',
                'company_id'             => 'nullable|integer',
                'agent_id'               => 'nullable|integer',
                'branch_id'              => 'nullable|integer',
                'file_url'               => 'nullable|string|url',
                'extraction_result'      => 'nullable|array',
                'error'                  => 'nullable|array',
                'error.code'             => 'required_if:status,error|string',
                'error.message'          => 'required_if:status,error|string',
                // Flat format fields (top-level task data when extraction_result is not used)
                'reference'              => 'nullable|string',
                'type'                   => 'nullable|string|in:flight,hotel,visa,insurance',
                'price'                  => 'nullable|numeric',
                'total'                  => 'nullable|numeric',
                'tax'                    => 'nullable|numeric',
                'exchange_currency'      => 'nullable|string',
                'client_name'            => 'nullable|string',
                'task_flight_details'    => 'nullable|array',
                'task_hotel_details'     => 'nullable|array',
                'task_visa_details'      => 'nullable|array',
                'task_insurance_details' => 'nullable|array',
            ]);

            $documentId = $validated['document_id'];
            $status     = $validated['status'];
            $supplierId = $validated['supplier_id'] ?? null;
            $companyId  = $validated['company_id'] ?? null;

            Log::info('[ResailAI] Callback received', [
                'document_id' => $documentId,
                'status'      => $status,
                'supplier_id' => $supplierId,
                'company_id'  => $companyId,
            ]);

            // Record callback receipt in DocumentProcessingLog
            $log = DocumentProcessingLog::where('document_id', $documentId)->first();
            if ($log) {
                $log->update([
                    'callback_received_at' => now(),
                    'status'               => 'processing',
                ]);
            }

            // Check if feature flag is enabled for this supplier/company
            if ($supplierId && $companyId) {
                if (!$this->processingAdapter->isPdfProcessingEnabled($supplierId, $companyId)) {
                    Log::warning('[ResailAI] PDF processing not enabled for supplier/company', [
                        'document_id' => $documentId,
                        'supplier_id' => $supplierId,
                        'company_id'  => $companyId,
                    ]);
                    return response()->json([
                        'message'     => 'PDF processing not enabled',
                        'document_id' => $documentId,
                    ], 200);
                }
            }

            // Handle error status
            if ($status === 'error') {
                $errorCode    = $validated['error']['code'] ?? 'UNKNOWN';
                $errorMessage = $validated['error']['message'] ?? 'Unknown error';

                Log::error('[ResailAI] Extraction failed', [
                    'document_id'  => $documentId,
                    'error_code'   => $errorCode,
                    'error_message' => $errorMessage,
                ]);

                // Update FileUpload status to error
                $this->updateFileUploadStatus($documentId, 'error', $errorMessage);

                // Update DocumentProcessingLog with error details
                if ($log) {
                    $log->update([
                        'status'               => 'failed',
                        'error_code'           => $errorCode,
                        'error_message'        => $errorMessage,
                        'callback_received_at' => now(),
                    ]);
                }

                return response()->json([
                    'message'     => 'Extraction failed',
                    'document_id' => $documentId,
                    'error'       => [
                        'code'    => $errorCode,
                        'message' => $errorMessage,
                    ],
                ], 200);
            }

            // Handle success status — flatten payload and create tasks
            if ($status === 'success') {
                // Flatten the callback payload into individual task payloads
                $taskPayloads = $this->processingAdapter->flattenExtractionResult($validated);

                if (empty($taskPayloads)) {
                    Log::warning('[ResailAI] No tasks found in extraction result', [
                        'document_id' => $documentId,
                    ]);

                    $this->updateFileUploadStatus($documentId, 'error', 'No tasks in extraction result');

                    return response()->json([
                        'message'     => 'No tasks found in extraction result',
                        'document_id' => $documentId,
                    ], 200);
                }

                $results    = [];
                $allSuccess = true;

                foreach ($taskPayloads as $taskPayload) {
                    $bridgeResult = $this->taskWebhookBridge->processExtraction($taskPayload);

                    if ($bridgeResult['success']) {
                        $results[] = $bridgeResult['response'] ?? [];
                        Log::info('[ResailAI] Task created successfully', [
                            'document_id' => $documentId,
                            'task_result' => $bridgeResult['response'] ?? [],
                        ]);
                    } else {
                        $allSuccess = false;
                        $results[]  = ['error' => $bridgeResult['error'] ?? 'Unknown error'];
                        Log::error('[ResailAI] Task creation failed for one extraction', [
                            'document_id' => $documentId,
                            'error'       => $bridgeResult['error'] ?? 'Unknown',
                        ]);
                    }
                }

                $finalStatus = $allSuccess ? 'completed' : 'error';
                $this->updateFileUploadStatus($documentId, $finalStatus);

                // Update DocumentProcessingLog with completion info
                if ($log) {
                    $log->update([
                        'status'               => $allSuccess ? 'completed' : 'failed',
                        'extraction_result'    => $validated['extraction_result'] ?? null,
                        'completed_at'         => now(),
                        'processing_duration_ms' => $log->started_at
                            ? $log->started_at->diffInMilliseconds(now())
                            : null,
                    ]);
                }

                return response()->json([
                    'message'         => $allSuccess ? 'All tasks created successfully' : 'Some tasks failed',
                    'document_id'     => $documentId,
                    'tasks_processed' => count($taskPayloads),
                    'data'            => $results,
                ], 200);
            }

            // Handle pending status
            if ($status === 'pending') {
                Log::info('[ResailAI] Processing pending', [
                    'document_id' => $documentId,
                ]);

                $this->updateFileUploadStatus($documentId, 'pending');

                return response()->json([
                    'message'     => 'Processing pending',
                    'document_id' => $documentId,
                ], 200);
            }

            // Fallback response
            Log::info('[ResailAI] Callback processed', [
                'document_id' => $documentId,
            ]);

            return response()->json([
                'message'     => 'Callback processed',
                'document_id' => $documentId,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[ResailAI] Callback validation failed', $e->errors());

            return response()->json([
                'error'    => 'Validation failed',
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
     * Update FileUpload status.
     *
     * @param  string       $documentId    FileUpload ID (UUID)
     * @param  string       $status        New status value
     * @param  string|null  $errorMessage  Error message if applicable
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
                'status'         => $status,
            ]);
        } else {
            Log::warning('[ResailAI] FileUpload not found for status update', [
                'document_id' => $documentId,
                'status'      => $status,
            ]);
        }
    }
}
