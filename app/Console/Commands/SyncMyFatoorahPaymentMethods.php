<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MFMethodSyncService;

class SyncMyFatoorahPaymentMethods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-myfatoorah-methods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync active MyFatoorah payment methods';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('⏳ Syncing MyFatoorah payment methods...');

        $success = app(MFMethodSyncService::class)->sync();

        if ($success) {
            $this->info('MyFatoorah payment methods sync completed.');
            return Command::SUCCESS;
        } else {
            $this->error('Sync failed. Check logs for details.');
            return Command::FAILURE;
        }
    }
}
