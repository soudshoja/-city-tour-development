<?php

namespace App\Console\Commands;

use Database\Seeders\TaskRuleSeeder;
use Illuminate\Console\Command;

class updateTaskRuleSeeder extends Command
{
    protected $signature = 'task:update-rule
                            {--companyId=The ID of the company} 
                            {--supplierId=The ID of the supplier}
    ';

    protected $description = 'Update TaskRuleSeeder to create new rule types';

    public function handle()
    {
        $companyId = $this->option('companyId');
        $supplierId = $this->option('supplierId');

        $this->info("Updating TaskRuleSeeder for Company ID: {$companyId}, Supplier ID: {$supplierId}");

        (new TaskRuleSeeder())->run($companyId, $supplierId);

        $this->info('TaskRuleSeeder update completed successfully.');
    }
}
