<?php

namespace App\Jobs;

use App\Models\BulkUpload;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoiceSequence;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CreateBulkInvoicesJob
 *
 * Background queue job for atomic bulk invoice creation from approved bulk uploads.
 * Creates all invoices within a single database transaction (all succeed or all fail).
 * Uses pessimistic locking on invoice_sequence to prevent duplicate invoice numbers.
 * Checks for duplicate task invoicing before creating each invoice detail.
 */
class CreateBulkInvoicesJob implements ShouldQueue
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
     * @param  int  $bulkUploadId  The bulk upload ID to process
     */
    public function __construct(public int $bulkUploadId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Load BulkUpload with eager loading
        $bulkUpload = BulkUpload::with('rows.client', 'rows.supplier', 'rows.task')
            ->findOrFail($this->bulkUploadId);

        // Set log context
        Log::withContext([
            'bulk_upload_id' => $this->bulkUploadId,
            'company_id' => $bulkUpload->company_id,
        ]);

        Log::info('Starting bulk invoice creation', [
            'filename' => $bulkUpload->original_filename,
            'valid_rows' => $bulkUpload->valid_rows,
        ]);

        // Wrap everything in DB::transaction for atomicity
        DB::transaction(function () use ($bulkUpload) {
            // Get valid rows
            $validRows = $bulkUpload->rows()->where('status', 'valid')->get();

            // Group by composite key (same logic as BulkInvoiceController::preview)
            $invoiceGroups = $validRows->groupBy(function ($row) {
                $clientId = $row->client_id;
                $invoiceDate = $row->raw_data['invoice_date'] ?? date('Y-m-d');

                return "{$clientId}_{$invoiceDate}";
            });

            $invoiceIds = [];

            // Create invoices for each group
            foreach ($invoiceGroups as $groupKey => $rows) {
                // Parse composite key
                [$clientId, $invoiceDate] = explode('_', $groupKey);

                // Generate invoice number with pessimistic lock
                $invoiceNumber = $this->generateInvoiceNumber($bulkUpload->company_id);

                // Get first row for shared metadata
                $firstRow = $rows->first();

                // Calculate subTotal
                $subTotal = $rows->sum(fn ($r) => (float) ($r->raw_data['task_price'] ?? $r->task->total));

                // Create Invoice (same fields as InvoiceController::store)
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'client_id' => (int) $clientId,
                    'agent_id' => $bulkUpload->agent_id,
                    'currency' => $firstRow->raw_data['currency'] ?? 'KWD',
                    'sub_amount' => $subTotal,
                    'amount' => $subTotal,
                    'invoice_date' => $invoiceDate,
                    'status' => 'unpaid', // Matches InvoiceStatus::UNPAID
                ]);

                // Create InvoiceDetails for each row in the group
                foreach ($rows as $row) {
                    // Check duplicate task: has this task been invoiced already?
                    $isDuplicate = InvoiceDetail::where('task_id', $row->task_id)
                        ->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['refunded', 'paid by refund']))
                        ->exists();

                    if ($isDuplicate) {
                        // Find the existing invoice number for error message
                        $existingInvoice = InvoiceDetail::where('task_id', $row->task_id)
                            ->whereHas('invoice', fn ($q) => $q->whereNotIn('status', ['refunded', 'paid by refund']))
                            ->with('invoice')
                            ->first();

                        throw new Exception(
                            "Task ID {$row->task_id} already invoiced in invoice {$existingInvoice->invoice->invoice_number}. Transaction rolled back."
                        );
                    }

                    // Calculate prices
                    $taskPrice = (float) ($row->raw_data['task_price'] ?? $row->task->total);
                    $supplierPrice = (float) ($row->task->price ?? $row->task->total ?? 0);

                    // Create InvoiceDetail (same fields as InvoiceController::store)
                    InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'task_id' => $row->task_id,
                        'task_description' => $row->raw_data['task_description'] ?? $row->task->reference ?? '',
                        'task_price' => $taskPrice,
                        'supplier_price' => $supplierPrice,
                        'markup_price' => $taskPrice - $supplierPrice,
                        'profit' => $taskPrice - $supplierPrice,
                        'paid' => false,
                    ]);
                }

                // Collect invoice ID
                $invoiceIds[] = $invoice->id;

                Log::info('Created invoice', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'client_id' => $clientId,
                    'task_count' => $rows->count(),
                ]);
            }

            // Update BulkUpload status and invoice_ids
            $bulkUpload->update([
                'status' => 'completed',
                'invoice_ids' => $invoiceIds,
            ]);

            Log::info('Bulk invoice creation completed', [
                'invoices_created' => count($invoiceIds),
                'invoice_ids' => $invoiceIds,
            ]);
        });
    }

    /**
     * Generate the next invoice number with pessimistic locking.
     *
     * Uses lockForUpdate() on InvoiceSequence to prevent race conditions
     * when multiple jobs try to generate invoice numbers concurrently.
     *
     * @param  int  $companyId  The company ID
     * @return string The generated invoice number (e.g., "INV-2026-00001")
     */
    protected function generateInvoiceNumber(int $companyId): string
    {
        // Get sequence with pessimistic lock
        $sequence = InvoiceSequence::where('company_id', $companyId)
            ->lockForUpdate()
            ->first();

        // If no sequence exists, create one
        if (! $sequence) {
            $sequence = InvoiceSequence::create([
                'company_id' => $companyId,
                'current_sequence' => 1,
            ]);
        }

        // Format invoice number (matches InvoiceController::generateInvoiceNumber)
        $invoiceNumber = sprintf('INV-%s-%05d', now()->year, $sequence->current_sequence);

        // Increment sequence for next invoice
        $sequence->increment('current_sequence');

        return $invoiceNumber;
    }

    /**
     * Handle a job failure.
     *
     * Updates the BulkUpload status to 'failed' with error details.
     *
     * @param  Throwable  $exception  The exception that caused the failure
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Bulk invoice creation job failed permanently', [
            'bulk_upload_id' => $this->bulkUploadId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Update BulkUpload status to 'failed' with error details
        BulkUpload::where('id', $this->bulkUploadId)->update([
            'status' => 'failed',
            'error_summary' => json_encode([
                'job_failure' => $exception->getMessage(),
                'failed_at' => now()->toDateTimeString(),
            ]),
        ]);
    }
}
