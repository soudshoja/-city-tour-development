<?php

namespace App\Modules\ResailAI\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
{
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
                'file_url' => 'nullable|string|url',
                'extraction_result' => 'nullable|array',
                'error' => 'nullable|array',
                'error.code' => 'required_if:status,error|string',
                'error.message' => 'required_if:status,error|string',
            ]);

            Log::info('ResailAI callback received', $validated);

            // TODO: Implement callback processing logic
            // This will handle PDF processing results from ResailAI
            // and trigger the appropriate downstream actions

            return response()->json([
                'message' => 'Callback processed',
                'document_id' => $validated['document_id'] ?? 'unknown',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('ResailAI callback validation failed', $e->errors());

            return response()->json([
                'error' => 'Validation failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ResailAI callback processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
