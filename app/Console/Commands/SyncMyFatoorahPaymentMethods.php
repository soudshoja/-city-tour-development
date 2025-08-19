<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Charge;
use App\Services\MFMethodSyncService;

class SyncMyFatoorahPaymentMethods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-myfatoorah-methods {--company=* : Limit to one or more company IDs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync MyFatoorah payment methods for every company that has active MyFatoorah charges';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = collect($this->option('company'))->filter()->map(fn($v) => (int) $v);

        $companyIds = $limit->isNotEmpty()
            ? $limit
            : Charge::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('name', 'like', '%myfatoorah%');
                })
                ->whereNotNull('company_id')
                ->pluck('company_id');

        if ($companyIds->isEmpty()) {
            $this->info('No companies found with active MyFatoorah charges.');
            return self::SUCCESS;
        }

        $this->info('⏳ Syncing MyFatoorah payment methods for companies: '.$companyIds->implode(', '));

        $total = 0; $errors = 0;

        foreach ($companyIds as $companyId) {
            $count = app(MFMethodSyncService::class)->sync((int)$companyId);

            if ($count === false) {
                $this->error("Company {$companyId}: sync failed");
                $errors++;
            } else {
                $this->line("Company {$companyId}: synced {$count} methods");
                $total += (int)$count;
            }
        }

        $this->info("Done. Total methods: {$total}. Errors: {$errors}.");
        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
