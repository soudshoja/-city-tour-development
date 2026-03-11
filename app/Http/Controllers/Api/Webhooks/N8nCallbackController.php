<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\DocumentProcessingLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class N8nCallbackController extends Controller
{
    /**
     * Handle N8n extraction callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Validate input
            $validated = $request->validate([
                'document_id' => 'required|uuid|exists:document_processing_logs,document_id',
                'status' => 'required|in:success,error',
                'execution_id' => 'required|string',
                'workflow_id' => 'required|string',
                'execution_time_ms' => 'required|integer',
                'extraction_result' => 'nullable|array',
                'error' => 'nullable|array',
                'error.code' => 'required_if:status,error|string',
                'error.message' => 'required_if:status,error|string',
                'error.context' => 'nullable|array',
            ]);

            // Find DocumentProcessingLog by document_id
            $log = DocumentProcessingLog::where('document_id', $validated['document_id'])->first();

            if (!$log) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Document not found',
                ], 404);
            }

            // Check for duplicate callback
            if (in_array($log->status, ['completed', 'failed'])) {
                Log::info('Duplicate N8n callback received', [
                    'document_id' => $validated['document_id'],
                    'current_status' => $log->status,
                ]);

                return response()->json([
                    'message' => 'Callback already processed',
                    'document_id' => $validated['document_id'],
                    'status' => $log->status,
                ], 409);
            }

            // Update log record
            $updateData = [
                'status' => $validated['status'] === 'success' ? 'completed' : 'failed',
                'n8n_execution_id' => $validated['execution_id'],
                'n8n_workflow_id' => $validated['workflow_id'],
                'processing_duration_ms' => $validated['execution_time_ms'],
                'callback_received_at' => now(),
                'hmac_signature' => $request->header('X-Signature-SHA256'),
            ];

            if ($validated['status'] === 'success') {
                $updateData['extraction_result'] = $validated['extraction_result'] ?? null;
            } else {
                $updateData['error_code'] = $validated['error']['code'] ?? 'ERR_UNKNOWN';
                $updateData['error_message'] = $validated['error']['message'] ?? 'Unknown error';
                $updateData['error_context'] = $validated['error']['context'] ?? null;
            }

            $log->update($updateData);

            // If failed, dispatch notification (placeholder for Phase 3)
            if ($validated['status'] === 'error') {
                Log::error('Document processing failed', [
                    'document_id' => $validated['document_id'],
                    'error_code' => $updateData['error_code'],
                    'error_message' => $updateData['error_message'],
                ]);

                // TODO: Dispatch ManualInterventionNotification in Phase 3
            }

            Log::info('N8n callback processed successfully', [
                'document_id' => $validated['document_id'],
                'status' => $validated['status'],
                'execution_id' => $validated['execution_id'],
            ]);

            return response()->json([
                'message' => 'Callback processed',
                'document_id' => $validated['document_id'],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('N8n callback processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }

}
