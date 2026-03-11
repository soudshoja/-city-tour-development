<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Services\HesabeMethodSyncService;
use Illuminate\Console\Command;

class SyncHesabePaymentMethods extends Command 
{
    protected $signature = 'app:sync-hesabe-methods {--company=* : Limit to one or more company ID}';
    protected $description = 'Sync Hesabe payment methods for every company that has active Hesabe gateway';

    public function handle()
    {
        $this->info('Start to sync payment method for Hesabe payment gateway');

        $limit = collect($this->option('company'))->filter()->map(fn($v) => (int) $v);

        if ($limit->isEmpty()) {
            $companyIds = Charge::query()
                ->withoutGlobalScopes()
                ->where('is_active', true)
                ->where('name', 'like', '%hesabe%')
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

        $total = 0; $error = 0;

        foreach ($companyIds as $companyId) {
            $this->line("Processing company ID: {$companyId}");
            $response = app(HesabeMethodSyncService::class)->sync($companyId);

            if ($response === false) {
                $this->error("Failed syncing company {$companyId}");
                $error++;
            } else {
                $this->info("✓ Completed syncing {$response} methods for company {$companyId}");
                $total += $response;
            }
        }

        $this->info("Syncing completed. Total methods: {$total}. Errors: {$error}");
        return self::SUCCESS;
    }    
}
