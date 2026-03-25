<?php

namespace App\Console\Commands;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\PaymentApplication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SoftDeleteOrphanedCreditsAndPaymentApplications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credits:soft-delete-orphaned
                           {--dry-run : Show what would be soft-deleted without actually doing it}
                           {--company= : Filter by specific company ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Soft delete payment applications and credits linked to soft-deleted invoices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $companyId = $this->option('company');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode - no changes will be made.');
        }

        $this->info('Scanning for payment applications and credits linked to deleted invoices...');

        $deletedInvoiceQuery = Invoice::onlyTrashed();

        if ($companyId) {
            $deletedInvoiceQuery->where('company_id', $companyId);
        }

        $deletedInvoiceIds = $deletedInvoiceQuery->pluck('id');

        if ($deletedInvoiceIds->isEmpty()) {
            $this->info('No soft-deleted invoices found. Nothing to do.');
            return 0;
        }

        $this->info("Found {$deletedInvoiceIds->count()} soft-deleted invoice(s).");

        $paymentApplications = PaymentApplication::whereIn('invoice_id', $deletedInvoiceIds)->get();

        $credits = Credit::whereIn('invoice_id', $deletedInvoiceIds)
            ->where('type', Credit::INVOICE)
            ->get();

        $this->info("Found {$paymentApplications->count()} payment application(s) to soft-delete.");
        $this->info("Found {$credits->count()} credit(s) to soft-delete.");

        if ($paymentApplications->isEmpty() && $credits->isEmpty()) {
            $this->info('No orphaned records found. Nothing to do.');
            return 0;
        }

        if ($paymentApplications->isNotEmpty()) {
            $this->newLine();
            $this->info('Payment Applications to soft-delete:');
            $this->table(
                ['ID', 'Invoice ID', 'Payment ID', 'Credit ID', 'Amount', 'Applied At'],
                $paymentApplications->map(fn ($pa) => [
                    $pa->id,
                    $pa->invoice_id,
                    $pa->payment_id,
                    $pa->credit_id,
                    $pa->amount,
                    $pa->applied_at,
                ])->toArray()
            );
        }

        if ($credits->isNotEmpty()) {
            $this->newLine();
            $this->info('Credits to soft-delete:');
            $this->table(
                ['ID', 'Invoice ID', 'Client ID', 'Client Name', 'Type', 'Amount', 'Description'],
                $credits->map(fn ($c) => [
                    $c->id,
                    $c->invoice_id,
                    $c->client_id,
                    $c->client ? $c->client->full_name : 'N/A',
                    $c->type,
                    $c->amount,
                    \Illuminate\Support\Str::limit($c->description, 40),
                ])->toArray()
            );
        }

        if ($dryRun) {
            $this->warn('DRY-RUN complete. No records were modified.');
            return 0;
        }

        if (!$this->confirm('Do you want to proceed with soft-deleting these records?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $softDeletedPA = 0;
        $softDeletedCredits = 0;

        DB::beginTransaction();

        try {
            foreach ($paymentApplications as $pa) {
                $pa->delete();
                $softDeletedPA++;
                Log::info("Soft-deleted PaymentApplication #{$pa->id} (invoice_id: {$pa->invoice_id})");
            }

            foreach ($credits as $credit) {
                $credit->delete();
                $softDeletedCredits++;
                Log::info("Soft-deleted Credit #{$credit->id} (invoice_id: {$credit->invoice_id})");
            }

            DB::commit();

            $this->newLine();
            $this->info("Successfully soft-deleted {$softDeletedPA} payment application(s).");
            $this->info("Successfully soft-deleted {$softDeletedCredits} credit(s).");
            Log::info("SoftDeleteOrphanedCreditsAndPaymentApplications: Soft-deleted {$softDeletedPA} payment applications and {$softDeletedCredits} credits.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("An error occurred: {$e->getMessage()}");
            Log::error("SoftDeleteOrphanedCreditsAndPaymentApplications failed: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
