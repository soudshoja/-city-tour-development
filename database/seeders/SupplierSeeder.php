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

        Supplier::updateOrCreate(['name' => 'Amadeus', 'country_id' => $kuwaitId]);
        Supplier::updateOrCreate(['name' => 'Magic Holiday', 'country_id' => $kuwaitId]);
        Supplier::updateOrCreate(['name' => 'TBO Holiday', 'country_id' => $kuwaitId]);
    }
}
