<?php

namespace Database\Seeders;

use App\Enums\TaskRuleEnum;
use App\Models\TaskRules;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaskRuleSeeder extends Seeder
{
    public function run($companyId, $supplierId = null): void
    {
        if ($supplierId) {
            TaskRules::create([
                'company_id' => $companyId,
                'supplier_id' => $supplierId,
                'name' =>  TaskRuleEnum::DEFAULT->value,
                'description' => 'Default task rule for specific supplier.',
                'column' => null,
            ]);
        }
    }
}
