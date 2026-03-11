<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Services\UPaymentMethodSyncService;
use Illuminate\Console\Command;

class SyncUPaymentMethods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-u-payment-methods {--company=* : Limit to one or more company IDs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync UPayment payment methods';

    public function handle()
    {
        $this->info('Start to sync payment method for UPayment payment gateway');
        $this->info('Finding companies with active UPayment charges...');

        $limit = collect($this->option('company'))->filter()->map(fn($v) => (int) $v);

        if ($limit->isEmpty()) {
            $companyIds = Charge::query()
                ->withoutGlobalScopes()
                ->where('is_active', true)
                ->where('name', 'like', '%upayment%')
                ->whereNotNull('company_id')
                ->pluck('company_id')
                ->unique();
        } else {
            $companyIds = $limit;
        }

        if ($companyIds->isEmpty()) {
            $this->warn('No companies found. Exiting...');
            return self::SUCCESS;
        }

        $totalCompanies = $companyIds->count();
        $this->info("Found {$totalCompanies} companies with active UPayment charges");

        $progressBar = $this->output->createProgressBar($totalCompanies);
        $progressBar->start();

        $count = 0;
        foreach ($companyIds as $index => $companyId) {
            $this->line("\nProcessing company ID: {$companyId} (" . ($index + 1) . "/{$totalCompanies})");

            $response = app(UPaymentMethodSyncService::class)->sync($companyId);

            if ($response === false) {
                $this->warn("⚠ Failed to sync company {$companyId}");
            } else {
                $this->line("✓ Completed payment methods for company ID: {$companyId}");
                $count += $response;
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->line('');
        $this->info('✓ UPayment synchronization completed successfully!');
        $this->info("Total companies processed: {$totalCompanies}. Total methods: {$count}");
        return self::SUCCESS;
    }
}
