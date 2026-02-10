<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentProcessingLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DocumentProcessingController extends Controller
{
    /**
     * Queue document for N8n processing
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Validate input
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'supplier_id' => 'required|integer',
            'document_type' => 'required|in:air,pdf,image,email',
            'file_path' => 'required|string|max:500',
            'file_size_bytes' => 'nullable|integer|max:52428800',
            'file_hash' => 'nullable|string|regex:/^[a-f0-9]{64}$/',
        ]);

        try {
            // Generate document_id
            $documentId = Str::uuid()->toString();

            // Create DocumentProcessingLog record
            $log = DocumentProcessingLog::create([
                'company_id' => $validated['company_id'],
                'supplier_id' => $validated['supplier_id'],
                'document_id' => $documentId,
                'document_type' => $validated['document_type'],
                'file_path' => $validated['file_path'],
                'file_size_bytes' => $validated['file_size_bytes'] ?? null,
                'file_hash' => $validated['file_hash'] ?? null,
                'status' => 'queued',
            ]);

            // Build webhook payload
            $timestamp = now()->timestamp;
            $payload = [
                'company_id' => $validated['company_id'],
                'supplier_id' => $validated['supplier_id'],
                'document_id' => $documentId,
                'document_type' => $validated['document_type'],
                'file_path' => $validated['file_path'],
                'file_size_bytes' => $validated['file_size_bytes'] ?? 0,
                'file_hash' => $validated['file_hash'] ?? '',
                'callback_url' => route('api.webhooks.n8n.callback'),
                'timestamp' => $timestamp,
            ];

            // Sign payload with HMAC (placeholder for Phase 2)
            $webhookSecret = config('services.n8n.webhook_secret', 'default-secret');
            $payloadJson = json_encode($payload);
            $hmacSignature = hash_hmac('sha256', $payloadJson, $webhookSecret);

            // Send HTTP POST to N8n webhook URL
            $n8nWebhookUrl = config('services.n8n.webhook_url');

            if (!$n8nWebhookUrl) {
                $log->update([
                    'status' => 'failed',
                    'error_code' => 'ERR_N8N_CONFIG_MISSING',
                    'error_message' => 'N8n webhook URL not configured',
                ]);

                return response()->json([
                    'error' => 'N8n configuration missing',
                ], 500);
            }

            $response = Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Signature' => $hmacSignature,
                    'X-Timestamp' => $timestamp,
                    'X-Request-ID' => $documentId,
                ])
                ->post($n8nWebhookUrl, $payload);

            if ($response->failed()) {
                $log->update([
                    'status' => 'failed',
                    'error_code' => 'ERR_N8N_UNAVAILABLE',
                    'error_message' => 'N8n webhook request failed: ' . $response->status(),
                ]);

                Log::error('N8n webhook request failed', [
                    'document_id' => $documentId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'error' => 'Service unavailable',
                    'message' => 'Document processing service is temporarily unavailable',
                ], 503);
            }

            Log::info('Document queued for N8n processing', [
                'document_id' => $documentId,
                'supplier_id' => $validated['supplier_id'],
                'n8n_response_status' => $response->status(),
            ]);

            return response()->json([
                'document_id' => $documentId,
                'status' => 'queued',
                'message' => 'Document queued for processing',
            ], 202);

        } catch (\Exception $e) {
            Log::error('Document processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
                'message' => 'Failed to queue document for processing',
            ], 500);
        }
    }
}
