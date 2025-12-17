<?php

namespace Database\Seeders;

use App\Models\PaymentMethodGroup;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMethodGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'KNET',
            'VISA/MASTER',
            'APPLE PAY',
            'SAMSUNG PAY',
            'GOOGLE PAY',
            'MADA',
            'BENEFIT',
            'DEEMA',
            'QPAY',
            'AMEX',
            'BNPL',
        ];

        foreach ($groups as $groupName) {
            PaymentMethodGroup::firstOrCreate(['name' => $groupName]);
        }
    }
}
