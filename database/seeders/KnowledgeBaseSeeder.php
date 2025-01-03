<?php

namespace Database\Seeders;

use App\Models\KnowledgeBase;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class KnowledgeBaseSeeder extends Seeder
{
    public function run()
    {
        KnowledgeBase::create([
            'topic' => 'Pending Tasks',
            'content' => 'Pending tasks are tasks assigned to agents but not yet completed or marked as done.'
        ]);

        KnowledgeBase::create([
            'topic' => 'COA',
            'content' => 'COA (Chart of Accounts) is used to organize financial data into categories such as assets, liabilities, income, and expenses.'
        ]);
    }
}
