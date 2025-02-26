<?php

namespace Database\Seeders;

use App\Models\SystemExchangeRate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Event\Telemetry\System;

class SystemExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        SystemExchangeRate::truncate();
        $exchangeRates = [
            ['base_currency' => 'USD', 'exchange_currency' => 'EUR', 'exchange_rate' => 0.85],
            ['base_currency' => 'USD', 'exchange_currency' => 'GBP', 'exchange_rate' => 0.75],
            ['base_currency' => 'USD', 'exchange_currency' => 'JPY', 'exchange_rate' => 110.00],
            ['base_currency' => 'USD', 'exchange_currency' => 'CAD', 'exchange_rate' => 1.25],
            ['base_currency' => 'USD', 'exchange_currency' => 'AUD', 'exchange_rate' => 1.35],
            ['base_currency' => 'USD', 'exchange_currency' => 'CHF', 'exchange_rate' => 0.92],
            ['base_currency' => 'USD', 'exchange_currency' => 'CNY', 'exchange_rate' => 6.45],
            ['base_currency' => 'USD', 'exchange_currency' => 'INR', 'exchange_rate' => 74.00],
            ['base_currency' => 'USD', 'exchange_currency' => 'MXN', 'exchange_rate' => 20.00],
            ['base_currency' => 'USD', 'exchange_currency' => 'BRL', 'exchange_rate' => 5.20],
            ['base_currency' => 'USD', 'exchange_currency' => 'RUB', 'exchange_rate' => 73.00],
            ['base_currency' => 'USD', 'exchange_currency' => 'ZAR', 'exchange_rate' => 14.50],
            ['base_currency' => 'USD', 'exchange_currency' => 'KRW', 'exchange_rate' => 1150.00],
            ['base_currency' => 'USD', 'exchange_currency' => 'SGD', 'exchange_rate' => 1.35],
            ['base_currency' => 'USD', 'exchange_currency' => 'HKD', 'exchange_rate' => 7.80],
            ['base_currency' => 'USD', 'exchange_currency' => 'NZD', 'exchange_rate' => 1.40],
            ['base_currency' => 'USD', 'exchange_currency' => 'SEK', 'exchange_rate' => 8.60],
            ['base_currency' => 'USD', 'exchange_currency' => 'NOK', 'exchange_rate' => 8.90],
            ['base_currency' => 'USD', 'exchange_currency' => 'DKK', 'exchange_rate' => 6.30],
            ['base_currency' => 'USD', 'exchange_currency' => 'PLN', 'exchange_rate' => 3.85],
            ['base_currency' => 'KWD', 'exchange_currency' => 'USD', 'exchange_rate' => 2.10],
        ];

        SystemExchangeRate::insert($exchangeRates);
    }
}
