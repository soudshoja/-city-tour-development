<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public static function run(): void
    {
        Setting::firstOrCreate(
            [
                'key' => 'invoice_expiry_days',
            ],
            [
                'value' => 5, // Default value for invoice expiry days
                'type' => 'integer',
                'description' => 'Number of days after which an invoice expires'
            ]
        );

    }
}
