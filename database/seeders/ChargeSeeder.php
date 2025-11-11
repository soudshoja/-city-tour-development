<?php

namespace Database\Seeders;

use App\Models\Charge;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChargeSeeder extends Seeder
{
    public function run(): void
    {
        Charge::whereIn('name', ['Tap', 'MyFatoorah', 'UPayment', 'Hesabe'])
            ->update([
                'is_system_default' => true,
                'can_be_deleted' => false,
                'enabled_by' => 'admin'
            ]);
    }
}
