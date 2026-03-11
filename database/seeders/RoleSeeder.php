<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'company']);
        Role::firstOrCreate(['name' => 'branch']);
        Role::firstOrCreate(['name' => 'agent']);
        Role::firstOrCreate(['name' => 'accountant']);
        Role::firstOrCreate(['name' => 'client']);
    }
}
