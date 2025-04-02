<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CoaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public static function run(int $companyId = 1): void
    {
        $accounts = [
            ['name' => 'Assets', 'level' => 1, 'parent' => null, 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Liabilities', 'level' => 1, 'parent' => null, 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Income', 'level' => 1, 'parent' => null, 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],
            ['name' => 'Expenses', 'level' => 1, 'parent' => null, 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],

            ['name' => 'Current Assets', 'level' => 2, 'parent' => 'Assets', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Fixed Assets', 'level' => 2, 'parent' => 'Assets', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Investments', 'level' => 2, 'parent' => 'Assets', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Deposits', 'level' => 2, 'parent' => 'Assets', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],

            ['name' => 'Current Liabilities', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Long-Term Liabilities', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Provisions', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],

            ['name' => 'Operating Income', 'level' => 2, 'parent' => 'Income', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],
            ['name' => 'Non-Operating Income', 'level' => 2, 'parent' => 'Income', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],

            ['name' => 'Fixed Expenses', 'level' => 2, 'parent' => 'Expenses', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],
            ['name' => 'Variable Expenses', 'level' => 2, 'parent' => 'Expenses', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],

            ['name' => 'Cash', 'level' => 3, 'parent' => 'Current Assets', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Accounts Receivable', 'level' => 3, 'parent' => 'Current Assets', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Inventory', 'level' => 3, 'parent' => 'Current Assets', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],

            ['name' => 'Property, Plant, and Equipment', 'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Investments in Subsidiaries', 'level' => 3, 'parent' => 'Investments', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Long-Term Deposits', 'level' => 3, 'parent' => 'Investments', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],

            ['name' => 'Accounts Payable', 'level' => 3, 'parent' => 'Current Liabilities', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],
            ['name' => 'Short-Term Debt', 'level' => 3, 'parent' => 'Current Liabilities', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],

            ['name' => 'Long-Term Debt', 'level' => 3, 'parent' => 'Long-Term Liabilities', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['BALANCE']],

            ['name' => 'Income On Sales', 'level' => 3, 'parent' => 'Operating Income', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],

            ['name' => 'Salary Expense', 'level' => 3, 'parent' => 'Fixed Expenses', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],
            ['name' => 'Rent Expense', 'level' => 3, 'parent' => 'Fixed Expenses', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],
            ['name' => 'Depreciation Expense', 'level' => 3, 'parent' => 'Fixed Expenses', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],

            ['name' => 'Business Trip Expense', 'level' => 3, 'parent' => 'Variable Expenses', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],
            ['name' => 'Agent Sales Commission', 'level' => 3, 'parent' => 'Variable Expenses', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],
            ['name' => 'Sponsorship Fee', 'level' => 3, 'parent' => 'Variable Expenses', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],
            ['name' => 'Legal & Professional Fees', 'level' => 3, 'parent' => 'Variable Expenses', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],
            ['name' => 'Utilities Expense', 'level' => 3, 'parent' => 'Variable Expenses', 'account_type' => null , 'report_type' => Account::REPORT_TYPES['PROFIT']],
        ];

        $parentMap = [];
        foreach ($accounts as $account) {
            $parentId = $account['parent'] ? $parentMap[$account['parent']] : null;

            $newAccount = Account::updateOrCreate([
                'name' => $account['name'],
                'company_id' => $companyId,
            ],[
                'serial_number' => null,
                'account_type' => $account['account_type'],
                'report_type' => $account['report_type'],
                'level' => $account['level'],
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'parent_id' => $parentId,
                'root_id' => $parentId ? $parentMap[$account['parent']] : null,
                'branch_id' => null,
                'agent_id' => null,
                'client_id' => null,
                'supplier_id' => null,
                'reference_id' => null,
                'code' => null,
            ]);

            $parentMap[$account['name']] = $newAccount->id;
        }
    }
    
}
