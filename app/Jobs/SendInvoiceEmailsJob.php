<?php

namespace App\Jobs;

use App\Mail\BulkInvoicesMail;
use App\Models\BulkInvoice;
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
     * @param  int  $bulkInvoiceId  The bulk invoice ID to send emails for
     */
    public function __construct(public int $bulkInvoiceId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Load BulkInvoice with eager loading
        $bulkInvoice = BulkInvoice::with('agent.branch.company')->findOrFail($this->bulkInvoiceId);

        // Guard clause: verify status is 'completed'
        if ($bulkInvoice->status !== 'completed') {
            Log::warning('SendInvoiceEmailsJob skipped - BulkInvoice not completed', [
                'bulk_invoice_id' => $this->bulkInvoiceId,
                'status' => $bulkInvoice->status,
            ]);

            return;
        }

        // Guard clause: verify invoice_ids is not empty
        if (empty($bulkInvoice->invoice_ids) || ! is_array($bulkInvoice->invoice_ids)) {
            Log::warning('SendInvoiceEmailsJob skipped - No invoice IDs found', [
                'bulk_invoice_id' => $this->bulkInvoiceId,
                'invoice_ids' => $bulkInvoice->invoice_ids,
            ]);

            return;
        }

        // Get company and agent
        $company = $bulkInvoice->agent?->branch?->company;
        $agent = $bulkInvoice->agent;

        // Set log context
        Log::withContext([
            'bulk_invoice_id' => $this->bulkInvoiceId,
            'company_id' => $bulkInvoice->company_id,
        ]);

        Log::info('Starting bulk invoice email delivery', [
            'filename' => $bulkInvoice->original_filename,
            'invoice_count' => count($bulkInvoice->invoice_ids),
        ]);

        // Send to accountant (company email)
        if ($company && $company->email) {
            Mail::to($company->email)
                ->queue(new BulkInvoicesMail($this->bulkInvoiceId));

            Log::info('Queued bulk invoice email to accountant', [
                'email' => $company->email,
            ]);
        } else {
            Log::warning('No company email found, skipping accountant notification', [
                'company_id' => $bulkInvoice->company_id,
            ]);
        }

        // Send to uploading agent
        if ($agent && $agent->email) {
            Mail::to($agent->email)
                ->queue(new BulkInvoicesMail($this->bulkInvoiceId));

            Log::info('Queued bulk invoice email to agent', [
                'email' => $agent->email,
            ]);
        } else {
            Log::warning('No agent email found, skipping agent notification', [
                'agent_id' => $bulkInvoice->agent_id,
            ]);
        }

        Log::info('Bulk invoice email delivery completed');
    }

    /**
     * Handle a job failure.
     *
     * Email delivery failure is non-critical. The bulk invoice succeeded,
     * only the notification failed. We log the error but do NOT update
     * BulkInvoice status.
     *
     * @param  Throwable  $exception  The exception that caused the failure
     */
    public function failed(Throwable $exception): void
    {
        Log::error('SendInvoiceEmailsJob failed', [
            'bulk_invoice_id' => $this->bulkInvoiceId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
