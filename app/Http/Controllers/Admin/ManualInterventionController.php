<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentProcessingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ManualInterventionController extends Controller
{
    /**
     * Display list of failed documents
     */
    public function index(Request $request)
    {
        // Base query for failed documents
        $query = DocumentProcessingLog::where('status', 'failed')
            ->with('company')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('error_code')) {
            $query->where('error_code', $request->error_code);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Pagination with error_type filter support
        $perPage = $request->input('per_page', 50);
        $failedDocuments = $query->paginate($perPage)->appends($request->except('page'));

        // Get error statistics
        $errorStats = DocumentProcessingLog::where('status', 'failed')
            ->selectRaw('error_code, COUNT(*) as count')
            ->groupBy('error_code')
            ->orderBy('count', 'desc')
            ->get();

        // Get unique companies and suppliers for filters
        $companies = DocumentProcessingLog::where('status', 'failed')
            ->with('company')
            ->select('company_id')
            ->distinct()
            ->get()
            ->pluck('company')
            ->filter();

        $suppliers = DocumentProcessingLog::where('status', 'failed')
            ->select('supplier_id')
            ->distinct()
            ->orderBy('supplier_id')
            ->pluck('supplier_id');

        // Get unique document types for filters
        $documentTypes = DocumentProcessingLog::where('status', 'failed')
            ->select('document_type')
            ->distinct()
            ->orderBy('document_type')
            ->pluck('document_type');

        return view('admin.manual-intervention.index', [
            'failedDocuments' => $failedDocuments,
            'errorStats' => $errorStats,
            'companies' => $companies,
            'suppliers' => $suppliers,
            'documentTypes' => $documentTypes,
        ]);
    }

    /**
     * Show detailed view of failed document
     */
    public function show(DocumentProcessingLog $log)
    {
        $log->load('company');

        return view('admin.manual-intervention.show', [
            'log' => $log,
        ]);
    }

    /**
     * Retry processing by re-queuing to N8n
     */
    public function retry(DocumentProcessingLog $log)
    {
        // Only retry failed documents
        if ($log->status !== 'failed') {
            return back()->with('error', 'Only failed documents can be retried');
        }

        try {
            // Reset status to queued
            $log->update([
                'status' => 'queued',
                'error_code' => null,
                'error_message' => null,
                'error_context' => null,
                'n8n_execution_id' => null,
                'n8n_workflow_id' => null,
                'callback_received_at' => null,
            ]);

            // Re-send to N8n
            $this->queueToN8n($log);

            Log::info('Document manually retried', [
                'document_id' => $log->document_id,
                'company_id' => $log->company_id,
                'supplier_id' => $log->supplier_id,
            ]);

            return back()->with('success', 'Document requeued for processing');

        } catch (\Exception $e) {
            Log::error('Manual retry failed', [
                'document_id' => $log->document_id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Retry failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark document as manually resolved
     */
    public function resolve(DocumentProcessingLog $log, Request $request)
    {
        $request->validate([
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $log->update([
                'status' => 'completed',
                'error_code' => null,
                'error_message' => 'Manually resolved: ' . ($request->resolution_notes ?? 'No notes provided'),
                'callback_received_at' => now(),
            ]);

            Log::info('Document manually resolved', [
                'document_id' => $log->document_id,
                'resolved_by' => auth()->user()->name ?? 'Unknown',
                'notes' => $request->resolution_notes,
            ]);

            return back()->with('success', 'Document marked as resolved');

        } catch (\Exception $e) {
            Log::error('Manual resolution failed', [
                'document_id' => $log->document_id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Resolution failed: ' . $e->getMessage());
        }
    }

    /**
     * Escalate document to admin/engineering
     */
    public function escalate(DocumentProcessingLog $log, Request $request)
    {
        $request->validate([
            'escalation_notes' => 'required|string|max:1000',
        ]);

        try {
            // Log escalation
            Log::warning('Document escalated to engineering', [
                'document_id' => $log->document_id,
                'error_code' => $log->error_code,
                'error_message' => $log->error_message,
                'escalated_by' => auth()->user()->name ?? 'Unknown',
                'notes' => $request->escalation_notes,
            ]);

            // TODO: Send notification to engineering team (Slack, email, etc.)

            return back()->with('success', 'Document escalated to engineering team');

        } catch (\Exception $e) {
            Log::error('Escalation failed', [
                'document_id' => $log->document_id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Escalation failed: ' . $e->getMessage());
        }
    }

    /**
     * Bulk retry multiple failed documents (ERR-04)
     */
    public function bulkRetry(Request $request)
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'required|exists:document_processing_logs,id',
        ]);

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($request->document_ids as $id) {
            try {
                $log = DocumentProcessingLog::findOrFail($id);

                if ($log->status !== 'failed') {
                    $failedCount++;
                    $errors[] = "Document {$log->document_id}: Not in failed status";
                    continue;
                }

                // Reset status to queued
                $log->update([
                    'status' => 'queued',
                    'error_code' => null,
                    'error_message' => null,
                    'error_context' => null,
                    'n8n_execution_id' => null,
                    'n8n_workflow_id' => null,
                    'callback_received_at' => null,
                ]);

                // Re-send to N8n
                $this->queueToN8n($log);
                $successCount++;

                Log::info('Document bulk retried', [
                    'document_id' => $log->document_id,
                    'company_id' => $log->company_id,
                ]);

            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = "Document ID {$id}: " . $e->getMessage();
                Log::error('Bulk retry failed for document', [
                    'document_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $message = "Bulk retry complete: {$successCount} succeeded, {$failedCount} failed";
        if (!empty($errors)) {
            $message .= '. Errors: ' . implode('; ', $errors);
        }

        return back()->with($successCount > 0 ? 'success' : 'error', $message);
    }

    /**
     * Export failed documents to CSV (ERR-04)
     */
    public function exportCsv(Request $request)
    {
        // Apply same filters as index
        $query = DocumentProcessingLog::where('status', 'failed')
            ->with('company');

        if ($request->filled('error_code')) {
            $query->where('error_code', $request->error_code);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $failedDocuments = $query->orderBy('created_at', 'desc')->get();

        $filename = 'failed_documents_' . now()->format('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($failedDocuments) {
            $handle = fopen('php://output', 'w');

            // CSV header
            fputcsv($handle, [
                'Document ID',
                'Company',
                'Company ID',
                'Supplier ID',
                'Document Type',
                'Error Code',
                'Error Message',
                'File Path',
                'File Size (bytes)',
                'File Hash',
                'N8n Execution ID',
                'N8n Workflow ID',
                'Created At',
                'Callback Received At',
                'Processing Duration (ms)',
            ]);

            // CSV rows
            foreach ($failedDocuments as $doc) {
                fputcsv($handle, [
                    $doc->document_id,
                    $doc->company->name ?? 'N/A',
                    $doc->company_id,
                    $doc->supplier_id,
                    $doc->document_type,
                    $doc->error_code,
                    $doc->error_message,
                    $doc->file_path,
                    $doc->file_size_bytes,
                    $doc->file_hash,
                    $doc->n8n_execution_id,
                    $doc->n8n_workflow_id,
                    $doc->created_at->toDateTimeString(),
                    $doc->callback_received_at?->toDateTimeString(),
                    $doc->processing_duration_ms,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show detailed error timeline for a document (ERR-04)
     */
    public function timeline(DocumentProcessingLog $log)
    {
        // Get error timeline events
        $timeline = [
            [
                'event' => 'Document Created',
                'timestamp' => $log->created_at,
                'status' => 'info',
                'details' => "Document queued for processing",
            ],
        ];

        if ($log->callback_received_at) {
            $timeline[] = [
                'event' => 'Callback Received',
                'timestamp' => $log->callback_received_at,
                'status' => 'warning',
                'details' => "Processing callback received from N8n",
            ];
        }

        if ($log->error_code) {
            $timeline[] = [
                'event' => 'Error Occurred',
                'timestamp' => $log->updated_at,
                'status' => 'danger',
                'details' => "Error {$log->error_code}: {$log->error_message}",
            ];
        }

        // Get all historical updates from this document (if retry history exists)
        $retryHistory = DocumentProcessingLog::where('document_id', $log->document_id)
            ->where('id', '!=', $log->id)
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($retryHistory as $retry) {
            $timeline[] = [
                'event' => 'Retry Attempt',
                'timestamp' => $retry->created_at,
                'status' => $retry->status === 'completed' ? 'success' : 'warning',
                'details' => "Status: {$retry->status}" . ($retry->error_code ? " | Error: {$retry->error_code}" : ''),
            ];
        }

        // Sort by timestamp
        usort($timeline, function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        return view('admin.manual-intervention.timeline', [
            'log' => $log,
            'timeline' => $timeline,
        ]);
    }

    /**
     * Re-queue document to N8n (extracted method for reuse)
     */
    protected function queueToN8n(DocumentProcessingLog $log): void
    {
        $timestamp = now()->timestamp;
        $payload = [
            'company_id' => $log->company_id,
            'supplier_id' => $log->supplier_id,
            'document_id' => $log->document_id,
            'document_type' => $log->document_type,
            'file_path' => $log->file_path,
            'file_size_bytes' => $log->file_size_bytes ?? 0,
            'file_hash' => $log->file_hash ?? '',
            'callback_url' => route('api.webhooks.n8n.callback'),
            'timestamp' => $timestamp,
        ];

        // Sign payload with HMAC
        $webhookSecret = config('services.n8n.webhook_secret', 'default-secret');
        $payloadJson = json_encode($payload);
        $hmacSignature = hash_hmac('sha256', $payloadJson, $webhookSecret);

        // Send HTTP POST to N8n webhook URL
        $n8nWebhookUrl = config('services.n8n.webhook_url');

        if (!$n8nWebhookUrl) {
            throw new \Exception('N8n webhook URL not configured');
        }

        $response = Http::timeout(5)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Signature' => $hmacSignature,
                'X-Timestamp' => $timestamp,
                'X-Request-ID' => $log->document_id,
            ])
            ->post($n8nWebhookUrl, $payload);

        if ($response->failed()) {
            throw new \Exception('N8n webhook request failed: ' . $response->status());
        }
    }
}
