<?php

namespace Database\Seeders;

use App\Models\AccountType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountTypeSeeder extends Seeder
{
    public function run(): void
    {
        $accountTypes = [
            'Accumulated Depreciation',
            'Asset Received But Not Billed',
            'Bank',
            'Cash',
            'Chargeable',
            'Capital Work in Progress',
            'Cost of Goods Sold',
            'Current Asset',
            'Current Liability',
            'Depreciation',
            'Direct Expense',
            'Direct Income',
            'Equity',
            'Expense Account',
            'Expenses Included In Asset Valuation',
            'Expenses Included In Valuation',
            'Fixed Asset',
            'Income Account',
            'Indirect Expense',
            'Liability',
            'Payable',
            'Receivable',
            'Round Off',
            'Round Off for Opening',
            'Stock',
            'Stock Adjustment',
            'Stock Received But Not Billed',
            'Service Received But Not Billed',
            'Tax',
            'Temporary',
        ];

        foreach ($accountTypes as $type) {
            AccountType::firstOrCreate([
                'name' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
