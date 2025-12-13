<?php

namespace App\Console\Commands;

use App\Http\Controllers\ReceiptVoucherController;
use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\InvoiceReceipt;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMissingReceiptVouchers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-missing-receipt-vouchers {--invoice_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate receipt vouchers for past cash invoices that don\'t have them yet';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting to check and generate missing receipt vouchers for cash invoices...');

        $invoiceId = $this->option('invoice_id');

        $query = Invoice::with(['invoicePartials', 'client', 'agent.branch.company'])
            ->where(function ($q) {
                $q->where('payment_type', 'cash')
                    ->orWhereHas('invoicePartials', function ($q) {
                        $q->where('payment_gateway', 'Cash')
                            ->orWhere('payment_gateway', 'cash');
                    });
            });

        if ($invoiceId) {
            $query->where('id', $invoiceId);
        }

        $cashInvoices = $query->get();

        if ($cashInvoices->isEmpty()) {
            $this->info('No cash invoices found.');
            return self::SUCCESS;
        }

        $this->info("Found {$cashInvoices->count()} cash invoice(s) to check.");

        $bar = $this->output->createProgressBar($cashInvoices->count());
        $bar->start();

        $processedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($cashInvoices as $invoice) {
            $hasReceiptVoucher = InvoiceReceipt::where('invoice_id', $invoice->id)
                ->where('type', 'invoice')
                ->exists();

            if ($hasReceiptVoucher) {
                $this->newLine();
                $this->comment("Invoice #{$invoice->invoice_number} already has a receipt voucher. Skipping...");
                $skippedCount++;
                $bar->advance();
                continue;
            }

            $invoicePartial = $invoice->invoicePartials()
                ->where(function ($q) {
                    $q->where('payment_gateway', 'Cash')
                        ->orWhere('payment_gateway', 'cash');
                })
                ->first();

            $amount = $invoicePartial ? $invoicePartial->amount : $invoice->amount;
            $type = $invoicePartial && $invoicePartial->type === 'partial' ? 'partial' : 'full';

            $request = new Request([
                'amount' => $amount,
                'type' => $type,
            ]);

            try {
                DB::beginTransaction();

                $receiptVoucherController = new ReceiptVoucherController();
                $response = $receiptVoucherController->autoGenerate($invoice, $request);

                $responseData = json_decode($response->getContent(), true);

                if (isset($responseData['ok']) && $responseData['ok'] === true) {
                    DB::commit();
                    $this->newLine();
                    $this->info("✓ Generated receipt voucher for Invoice #{$invoice->invoice_number} (Amount: {$invoice->currency} {$amount})");
                    $processedCount++;
                } else {
                    DB::rollBack();
                    $this->newLine();
                    $this->error("✗ Failed to generate receipt voucher for Invoice #{$invoice->invoice_number}: " . ($responseData['message'] ?? 'Unknown error'));
                    $errorCount++;
                }
            } catch (\Exception $e) {
                DB::rollBack();
                $this->newLine();
                $this->error("✗ Exception for Invoice #{$invoice->invoice_number}: " . $e->getMessage());
                Log::error('Failed to generate receipt voucher for invoice', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errorCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("=== Summary ===");
        $this->info("Total cash invoices checked: {$cashInvoices->count()}");
        $this->info("Receipt vouchers generated: {$processedCount}");
        $this->info("Already had receipt vouchers (skipped): {$skippedCount}");
        $this->info("Errors: {$errorCount}");

        return self::SUCCESS;
    }
}
