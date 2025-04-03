<?php

namespace Database\Seeders;

use App\Models\SystemExchangeRate;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MasterSeeder::class,
            AdminSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            CountrySeeder::class,
            SupplierSeeder::class,
            AgentTypeSeeder::class,
            SystemExchangeRateSeeder::class,
            AccountTypeSeeder::class,
        ]);
    }
}
