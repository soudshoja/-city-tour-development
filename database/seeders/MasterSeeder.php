<?php

namespace Database\Seeders;

use App\Models\Master;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $masters = [
            [
                'name' => 'VERSION',
                'value' => '1.001',
                'description' => 'Current Git Version',
            ],
        ];

        foreach ($masters as $master) {
            Master::updateOrCreate($master);
        }
    }
}
