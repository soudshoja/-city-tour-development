<?php

namespace Database\Seeders;


use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgentTypesTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('agent_type')->insert([
            ['name' => 'Salary', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Commission', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
