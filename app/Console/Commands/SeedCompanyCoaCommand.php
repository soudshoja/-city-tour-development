<?php

namespace App\Console\Commands;

use Database\Seeders\CoaSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

class SeedCompanyCoaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'company-coa:seed';

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
        (new DatabaseSeeder())->callWith(CoaSeeder::class, [
            'companyId' => $this->ask('Enter the company ID to seed COA for')
        ]);
    }
}
