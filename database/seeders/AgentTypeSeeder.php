<?php

namespace Database\Seeders;

use App\Models\AgentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgentTypeSeeder extends Seeder
{
    public function run()
    {
        AgentType::firstOrCreate(['name' => 'Salary']);
        AgentType::firstOrCreate(['name' => 'Commission']);
        AgentType::firstOrCreate(['name' => 'Both-A']);
        AgentType::firstOrCreate(['name' => 'Both-B']);
    }
}
