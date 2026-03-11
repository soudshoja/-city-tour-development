<?php

namespace App\Modules\ResailAI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Modules\ResailAI\Services\ProcessingAdapter;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $documentContext;

    /**
     * Create a new job instance.
     */
    public function __construct(array $documentContext)
    {
        $this->documentContext = $documentContext;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $documentId = $this->documentContext['document_id'] ?? 'unknown';
        $supplierId = $this->documentContext['supplier_id'] ?? null;
        $companyId = $this->documentContext['company_id'] ?? null;
        $agentId = $this->documentContext['agent_id'] ?? null;
        $branchId = $this->documentContext['branch_id'] ?? null;
        $filePath = $this->documentContext['file_path'] ?? null;

        Log::info('[ResailAI] Processing document job started', [
            'document_id' => $documentId,
            'supplier_id' => $supplierId,
            'company_id' => $companyId,
            'agent_id' => $agentId,
            'branch_id' => $branchId,
            'file_path' => $filePath,
        ]);

        try {
            // Check if PDF processing is enabled for this supplier/company
            if (!ProcessingAdapter::isPdfProcessingEnabled($supplierId, $companyId)) {
                Log::info('[ResailAI] PDF processing not enabled, skipping', [
                    'document_id' => $documentId,
                ]);
                return;
            }

            // Build callback URL
            $callbackUrl = config('app.url') . '/api/modules/resailai/callback';

            // Build payload for ResailAI n8n webhook
            $payload = [
                'document_id' => $documentId,
                'supplier_id' => $supplierId,
                'company_id' => $companyId,
                'agent_id' => $agentId,
                'branch_id' => $branchId,
                'file_path' => $filePath,
                'callback_url' => $callbackUrl,
                'created_at' => now()->toIso8601String(),
            ];

            Log::info('[ResailAI] Sending document to ResailAI webhook', [
                'document_id' => $documentId,
                'n8n_url' => config('resailai.n8n_webhook_url'),
            ]);

            // Send to ResailAI n8n webhook
            $response = Http::withToken(config('resailai.api_token'))
                ->timeout(config('resailai.timeout', 30))
                ->post(config('resailai.n8n_webhook_url'), $payload);

            if ($response->successful()) {
                Log::info('[ResailAI] Document sent successfully to ResailAI', [
                    'document_id' => $documentId,
                    'status' => $response->status(),
                ]);
            } else {
                Log::error('[ResailAI] Failed to send document to ResailAI', [
                    'document_id' => $documentId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[ResailAI] Job processing failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * The number of seconds the job can run before timing out.
     */
    public function timeout(): int
    {
        return 300; // 5 minutes max
    }
}
