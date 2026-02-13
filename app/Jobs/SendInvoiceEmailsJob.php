<?php

namespace App\Jobs;

use App\Mail\BulkInvoicesMail;
use App\Models\BulkUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SendInvoiceEmailsJob
 *
 * Background queue job for sending bulk invoice emails with PDF attachments.
 * Sends BulkInvoicesMail to company accountant (company email) and uploading agent.
 * Email delivery failure is non-critical (invoices already created successfully).
 */
class SendInvoiceEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public $backoff = [10, 30, 60];

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param  int  $bulkUploadId  The bulk upload ID to send emails for
     */
    public function __construct(public int $bulkUploadId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Load BulkUpload with eager loading
        $bulkUpload = BulkUpload::with('agent.branch.company')->findOrFail($this->bulkUploadId);

        // Guard clause: verify status is 'completed'
        if ($bulkUpload->status !== 'completed') {
            Log::warning('SendInvoiceEmailsJob skipped - BulkUpload not completed', [
                'bulk_upload_id' => $this->bulkUploadId,
                'status' => $bulkUpload->status,
            ]);

            return;
        }

        // Guard clause: verify invoice_ids is not empty
        if (empty($bulkUpload->invoice_ids) || ! is_array($bulkUpload->invoice_ids)) {
            Log::warning('SendInvoiceEmailsJob skipped - No invoice IDs found', [
                'bulk_upload_id' => $this->bulkUploadId,
                'invoice_ids' => $bulkUpload->invoice_ids,
            ]);

            return;
        }

        // Get company and agent
        $company = $bulkUpload->agent?->branch?->company;
        $agent = $bulkUpload->agent;

        // Set log context
        Log::withContext([
            'bulk_upload_id' => $this->bulkUploadId,
            'company_id' => $bulkUpload->company_id,
        ]);

        Log::info('Starting bulk invoice email delivery', [
            'filename' => $bulkUpload->original_filename,
            'invoice_count' => count($bulkUpload->invoice_ids),
        ]);

        // Send to accountant (company email)
        if ($company && $company->email) {
            Mail::to($company->email)
                ->queue(new BulkInvoicesMail($this->bulkUploadId));

            Log::info('Queued bulk invoice email to accountant', [
                'email' => $company->email,
            ]);
        } else {
            Log::warning('No company email found, skipping accountant notification', [
                'company_id' => $bulkUpload->company_id,
            ]);
        }

        // Send to uploading agent
        if ($agent && $agent->email) {
            Mail::to($agent->email)
                ->queue(new BulkInvoicesMail($this->bulkUploadId));

            Log::info('Queued bulk invoice email to agent', [
                'email' => $agent->email,
            ]);
        } else {
            Log::warning('No agent email found, skipping agent notification', [
                'agent_id' => $bulkUpload->agent_id,
            ]);
        }

        Log::info('Bulk invoice email delivery completed');
    }

    /**
     * Handle a job failure.
     *
     * Email delivery failure is non-critical. The bulk upload succeeded,
     * only the notification failed. We log the error but do NOT update
     * BulkUpload status.
     *
     * @param  Throwable  $exception  The exception that caused the failure
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SendInvoiceEmailsJob failed', [
            'bulk_upload_id' => $this->bulkUploadId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
