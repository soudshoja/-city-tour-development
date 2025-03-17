<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Supplier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kuwaitId = Country::where('name', 'Kuwait')->first()->id;

        Supplier::firstOrCreate(['name' => 'Amadeus', 'country_id' => $kuwaitId]);
        Supplier::firstOrCreate(['name' => 'Magic Holiday', 'country_id' => $kuwaitId]);
        Supplier::firstOrCreate(['name' => 'TBO Holiday', 'country_id' => $kuwaitId]);
        Supplier::firstOrCreate(['name' => 'DOTW', 'country_id' => $kuwaitId]);
        Supplier::firstOrCreate(['name' => 'Rate Hawk', 'country_id' => $kuwaitId]);
    }
}
