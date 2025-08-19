<?php

namespace App\Console\Commands;

use App\Models\Account;
use Database\Seeders\CoaSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedCompanyCoaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'company-coa:seed
                            {--remove : Remove existing accounts before seeding}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $companyId = $this->ask('Enter the company ID to seed COA for');

        if ($this->option('remove')) {
            // Remove accounts only for the specified company
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            Account::where('company_id', $companyId)->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->info("Existing accounts for company {$companyId} removed.");
        }

        (new DatabaseSeeder())->callWith(CoaSeeder::class, [
            'companyId' => $companyId
        ]);

        $this->info("COA seeded for company {$companyId}.");
    }
}
