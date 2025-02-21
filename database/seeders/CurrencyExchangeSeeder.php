<?php

namespace Database\Seeders;

use App\Models\CurrencyExchange;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencyExchangeSeeder extends Seeder
{
    public function run(): void
    {
        $currencyExchanges = [
            ['company_id' => 10, 'base_currency' => 'USD', 'exchange_currency' => 'EUR', 'exchange_rate' => 0.85, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 11, 'base_currency' => 'USD', 'exchange_currency' => 'GBP', 'exchange_rate' => 0.75, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 12, 'base_currency' => 'USD', 'exchange_currency' => 'JPY', 'exchange_rate' => 110.00, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 13, 'base_currency' => 'USD', 'exchange_currency' => 'CAD', 'exchange_rate' => 1.25, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 14, 'base_currency' => 'USD', 'exchange_currency' => 'AUD', 'exchange_rate' => 1.35, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 15, 'base_currency' => 'USD', 'exchange_currency' => 'CHF', 'exchange_rate' => 0.92, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 10, 'base_currency' => 'EUR', 'exchange_currency' => 'USD', 'exchange_rate' => 1.18, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 11, 'base_currency' => 'GBP', 'exchange_currency' => 'USD', 'exchange_rate' => 1.33, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 12, 'base_currency' => 'JPY', 'exchange_currency' => 'USD', 'exchange_rate' => 0.0091, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 13, 'base_currency' => 'CAD', 'exchange_currency' => 'USD', 'exchange_rate' => 0.80, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 14, 'base_currency' => 'AUD', 'exchange_currency' => 'USD', 'exchange_rate' => 0.74, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 15, 'base_currency' => 'CHF', 'exchange_currency' => 'USD', 'exchange_rate' => 1.09, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 10, 'base_currency' => 'USD', 'exchange_currency' => 'INR', 'exchange_rate' => 74.00, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 11, 'base_currency' => 'USD', 'exchange_currency' => 'CNY', 'exchange_rate' => 6.45, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 12, 'base_currency' => 'USD', 'exchange_currency' => 'BRL', 'exchange_rate' => 5.20, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 13, 'base_currency' => 'USD', 'exchange_currency' => 'MXN', 'exchange_rate' => 20.00, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 14, 'base_currency' => 'USD', 'exchange_currency' => 'RUB', 'exchange_rate' => 73.00, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 15, 'base_currency' => 'USD', 'exchange_currency' => 'ZAR', 'exchange_rate' => 14.50, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 10, 'base_currency' => 'USD', 'exchange_currency' => 'KRW', 'exchange_rate' => 1150.00, 'is_manual' => false, 'last_updated' => now()],
            ['company_id' => 11, 'base_currency' => 'USD', 'exchange_currency' => 'SGD', 'exchange_rate' => 1.35, 'is_manual' => false, 'last_updated' => now()],
        ];

        foreach ($currencyExchanges as $currencyExchange) {
            CurrencyExchange::create($currencyExchange);
        }
    }
}
