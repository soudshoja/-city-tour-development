<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Agent;
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
            EntitySeeder::class,
            AgentPermissionSeeder::class,
            CurrencySeeder::class,
            // W6.S "Per-supplier status map" (w6-brief.md, owner addition 2026-08-28): global
            // (company_id=NULL) default rows only -- run AFTER SupplierSeeder so the Jazeera/Fly
            // Dubai/VFS/Magic Holiday supplier-scoped defaults it seeds can resolve those
            // suppliers by name. Idempotent (updateOrCreate) -- safe on every db:seed run.
            SupplierStatusMapSeeder::class,
        ]);
    }
}
