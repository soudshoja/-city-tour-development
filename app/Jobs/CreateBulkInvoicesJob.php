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
 * Links existing tasks to clients, sets selling prices, creates invoices, and applies payments.
 *
 * Process:
 * 1. Load validated rows with matched task_id, client_id, payment_id
 * 2. Set task.selling_amount from Excel (was NULL before)
 * 3. Link task to client if task.client_id is NULL
 * 4. Group tasks by client + invoice_date
 * 5. Create invoices with InvoiceDetails
 * 6. Apply payments using PaymentApplicationService
 *
 * All operations within a single transaction (all succeed or all fail).
 * Uses pessimistic locking on invoice_sequence to prevent duplicate invoice numbers.
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
        // Load BulkUpload with rows
        $bulkUpload = BulkUpload::with('rows')->findOrFail($this->bulkUploadId);

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
            $invoiceGroups = [];

            // STEP 1: Process each valid row
            foreach ($bulkUpload->rows()->where('status', 'valid')->get() as $row) {
                // 1a. Load matched entities
                $task = \App\Models\Task::findOrFail($row->matched['task_id']);
                $client = \App\Models\Client::findOrFail($row->matched['client_id']);
                $payment = \App\Models\Payment::findOrFail($row->matched['payment_id']);

                // 1b. Set selling price (was NULL before)
                $task->selling_amount = $row->raw_data['selling_price'];
                $task->save();

                // 1c. Link task to client (if not already linked)
                if (! $task->client_id) {
                    $task->client_id = $client->id;
                    $task->save();
                }

                // 1d. Group by client_id + invoice_date
                $groupKey = ($task->client_id ?? $client->id).'_'.$row->raw_data['invoice_date'];
                $invoiceGroups[$groupKey][] = [
                    'task' => $task,
                    'client' => $task->client ?? $client,
                    'payment' => $payment,
                    'invoice_date' => $row->raw_data['invoice_date'],
                    'selling_price' => $row->raw_data['selling_price'],
                    'notes' => $row->raw_data['notes'] ?? null,
                ];
            }

            // STEP 2: Create invoices for each group
            $createdInvoices = [];
            foreach ($invoiceGroups as $group) {
                // 2a. Create invoice
                $invoice = $this->createInvoice($group, $bulkUpload);

                // 2b. Create invoice details (link tasks)
                foreach ($group as $item) {
                    InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'task_id' => $item['task']->id,
                        'task_description' => $item['task']->reference ?? '',
                        'task_price' => $item['selling_price'],
                        'supplier_price' => $item['task']->price ?? 0,
                        'markup_price' => $item['selling_price'] - ($item['task']->price ?? 0),
                        'profit' => $item['selling_price'] - ($item['task']->price ?? 0),
                        'paid' => false,
                    ]);
                }

                // 2c. Link payment via PaymentApplicationService
                $payment = $group[0]['payment'];
                $client = $group[0]['client'];
                $this->linkPaymentToInvoice($invoice, $payment, $client);

                $createdInvoices[] = $invoice->id;

                Log::info('Created invoice', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $invoice->client_id,
                    'task_count' => count($group),
                ]);
            }

            // STEP 3: Update bulk upload status
            $bulkUpload->update([
                'status' => 'completed',
                'invoice_ids' => $createdInvoices,
            ]);

            Log::info('Bulk invoice creation completed', [
                'invoices_created' => count($createdInvoices),
                'invoice_ids' => $createdInvoices,
            ]);
        });

        // STEP 4: Dispatch email job (after commit)
        SendInvoiceEmailsJob::dispatch($this->bulkUploadId)
            ->onQueue('emails')
            ->afterCommit();

        Log::info('Dispatched email notification job', [
            'bulk_upload_id' => $this->bulkUploadId,
        ]);
    }

    /**
     * Create an invoice for a group of tasks.
     *
     * @param  array  $group  Group of task data with client, payment, dates, prices
     * @param  BulkUpload  $bulkUpload  The bulk upload instance
     * @return Invoice The created invoice
     */
    protected function createInvoice(array $group, BulkUpload $bulkUpload): Invoice
    {
        $firstItem = $group[0];
        $client = $firstItem['client'];
        $invoiceDate = $firstItem['invoice_date'];
        $totalAmount = array_sum(array_column($group, 'selling_price'));

        // Generate invoice number with pessimistic lock
        $invoiceNumber = $this->generateInvoiceNumber($bulkUpload->company_id);

        // Create invoice
        return Invoice::create([
            'company_id' => $bulkUpload->company_id,
            'invoice_number' => $invoiceNumber,
            'client_id' => $client->id,
            'agent_id' => $bulkUpload->agent_id,
            'invoice_date' => $invoiceDate,
            'currency' => 'KWD', // or from first task if needed
            'sub_amount' => $totalAmount,
            'amount' => $totalAmount,
            'status' => 'unpaid', // Will be updated by PaymentApplicationService
        ]);
    }

    /**
     * Link payment to invoice using PaymentApplicationService.
     *
     * @param  Invoice  $invoice  The invoice to link payment to
     * @param  \App\Models\Payment  $payment  The payment to link
     * @param  \App\Models\Client  $client  The client
     * @return void
     * @throws Exception If payment linking fails
     */
    protected function linkPaymentToInvoice(Invoice $invoice, \App\Models\Payment $payment, \App\Models\Client $client): void
    {
        // Find the topup credit for this payment
        $credit = \App\Models\Credit::where('client_id', $client->id)
            ->where('payment_id', $payment->id)
            ->where('type', \App\Models\Credit::TOPUP)
            ->orderBy('id', 'desc')
            ->first();

        if (! $credit) {
            throw new Exception("No topup credit found for payment {$payment->voucher_number}");
        }

        // Determine payment mode based on payment status
        $paymentMode = ($payment->completed && $payment->status === 'paid') ? 'full' : 'partial';

        // Apply payment to invoice using PaymentApplicationService
        $paymentService = app(\App\Services\PaymentApplicationService::class);
        $result = $paymentService->applyPaymentsToInvoice(
            invoiceId: $invoice->id,
            paymentAllocations: [
                [
                    'credit_id' => $credit->id,
                    'amount' => $invoice->amount,
                ],
            ],
            paymentMode: $paymentMode
        );

        if (! $result['success']) {
            throw new Exception("Failed to link payment: ".$result['message']);
        }
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
