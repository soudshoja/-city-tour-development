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
            // Top-Level (Level 1)
            ['code' => '1000', 'name' => 'Assets',     'level' => 1, 'parent' => null, 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2000', 'name' => 'Liabilities','level' => 1, 'parent' => null, 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '3000', 'name' => 'Equity',     'level' => 1, 'parent' => null, 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '4000', 'name' => 'Income',     'level' => 1, 'parent' => null, 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5000', 'name' => 'Expenses',   'level' => 1, 'parent' => null, 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
        
            // Assets (Level 2 and deeper)
            ['code' => '1100', 'name' => 'Cash In Hand',                 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1110', 'name' => 'Petty Cash',                   'level' => 3, 'parent' => 'Cash In Hand', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1120', 'name' => 'Receipt Voucher Cash',         'level' => 3, 'parent' => 'Cash In Hand', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            
            ['code' => '1200', 'name' => 'Bank Accounts',                'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1201', 'name' => 'Kuwait International Bank',                      'level' => 3, 'parent' => 'Bank Accounts', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1204', 'name' => 'Ahli United Bank Kuwait',                          'level' => 3, 'parent' => 'Bank Accounts', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1300', 'name'  => 'Payment Gateway',             'level' => 2, 'parent' => 'Assets','account_type'         => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
           
            ['code' => '1350', 'name' => 'Accounts Receivable',          'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1351', 'name' => 'Clients','level' => 3, 'parent' => 'Accounts Receivable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1352', 'name' => 'Agents/Branches','level' => 3, 'parent' => 'Accounts Receivable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            ['code' => '1400', 'name' => 'Supplier Advances/Prepayments','level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1410', 'name' => 'Prepaid Flights',             'level' => 3, 'parent' => 'Supplier Advances/Prepayments', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1420', 'name' => 'Prepaid Hotels',              'level' => 3, 'parent' => 'Supplier Advances/Prepayments', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            ['code' => '1500', 'name' => 'Stock Assets',                'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1510', 'name' => 'Stock In Hand',               'level' => 3, 'parent' => 'Stock Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            ['code' => '1600', 'name' => 'Tax Assets',                  'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            ['code' => '1700', 'name' => 'Loans and Advances (Assets)', 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1710', 'name' => 'Employee Advances',           'level' => 3, 'parent' => 'Loans and Advances (Assets)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1720', 'name' => 'Securities and Deposits',     'level' => 3, 'parent' => 'Loans and Advances (Assets)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1721', 'name' => 'Earnest Money',               'level' => 4, 'parent' => 'Securities and Deposits', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            ['code' => '1800', 'name' => 'Fixed Assets',                'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1810', 'name' => 'Capital Equipments',          'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1820', 'name' => 'Electronic Equipments',       'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1830', 'name' => 'Furniture and Fixtures',      'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1840', 'name' => 'Office Equipments',           'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1850', 'name' => 'Plants and Machineries',      'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1860', 'name' => 'Buildings',                   'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1870', 'name' => 'Softwares',                   'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1880', 'name' => 'Accumulated Depreciation',    'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1890', 'name' => 'CWIP Account (Construction Work in Progress)', 'level' => 3, 'parent' => 'Fixed Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            ['code' => '1900', 'name' => 'Investments',                 'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            ['code' => '1950', 'name' => 'Temporary Accounts',          'level' => 2, 'parent' => 'Assets', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '1951', 'name' => 'Temporary Opening',           'level' => 3, 'parent' => 'Temporary Accounts', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            // Liabilities (Level 2 and deeper)
            ['code' => '2100', 'name' => 'Accounts Payable',            'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2110', 'name' => 'Creditors',                   'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2120', 'name' => 'Suppliers (Flights)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2130', 'name' => 'Suppliers (Hotels)',  'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2121', 'name' => 'Suppliers (Visas)',     'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2122', 'name' => 'Suppliers (Insurance)', 'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2123', 'name' => 'Suppliers (Tour)',     'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2124', 'name' => 'Suppliers (Cruise)',   'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2125', 'name' => 'Suppliers (Car)',      'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2126', 'name' => 'Suppliers (Rail)',     'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2127', 'name' => 'Suppliers (Esim)',     'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2128', 'name' => 'Suppliers (Event)',    'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2129', 'name' => 'Suppliers (Lounge)',   'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2130', 'name' => 'Suppliers (Ferry)',    'level' => 3, 'parent' => 'Accounts Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2200', 'name' => 'Accrued Expenses',            'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2210', 'name' => 'Commissions (Agents)','level' => 3, 'parent' => 'Accrued Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2220', 'name' => 'Expenses (General)',  'level' => 3, 'parent' => 'Accrued Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            ['code' => '2300', 'name' => 'Stock Liabilities',           'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2310', 'name' => 'Stock Received But Not Billed','level' => 3, 'parent' => 'Stock Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2320', 'name' => 'Asset Received But Not Billed','level' => 3, 'parent' => 'Stock Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            ['code' => '2400', 'name' => 'Duties and Taxes',            'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2410', 'name' => 'TDS Payable',                 'level' => 3, 'parent' => 'Duties and Taxes', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2420', 'name' => 'GST Payable',                 'level' => 3, 'parent' => 'Duties and Taxes', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            ['code' => '2500', 'name' => 'Loans (Liabilities)',         'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2510', 'name' => 'Secured Loans',               'level' => 3, 'parent' => 'Loans (Liabilities)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2520', 'name' => 'Unsecured Loans',             'level' => 3, 'parent' => 'Loans (Liabilities)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2530', 'name' => 'Bank Overdraft Account',      'level' => 3, 'parent' => 'Loans (Liabilities)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            ['code' => '2600', 'name' => 'Refund Payable',              'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2610', 'name' => 'Clients',                     'level' => 3, 'parent' => 'Refund Payable', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],

            ['code' => '2620', 'name' => 'Advances', 'level' => 2, 'parent' => 'Liabilities', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2630', 'name' => 'Client', 'level' => 3, 'parent' => 'Advances', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2631', 'name' => 'Cash', 'level' => 4, 'parent' => 'Client', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '2632', 'name' => 'Payment Gateway', 'level' => 4, 'parent' => 'Client', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            // Equity (Level 2)
            ['code' => '3100', 'name' => 'Capital Stock',               'level' => 2, 'parent' => 'Equity', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '3200', 'name' => 'Dividends Paid',              'level' => 2, 'parent' => 'Equity', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '3300', 'name' => 'Opening Balance Equity',      'level' => 2, 'parent' => 'Equity', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
            ['code' => '3400', 'name' => 'Retained Earnings',           'level' => 2, 'parent' => 'Equity', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['BALANCE_SHEET']],
        
            // Income (Level 2 and deeper)
            ['code' => '4100', 'name' => 'Direct Income', 'level' => 2, 'parent' => 'Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4110', 'name' => 'Flight Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4120', 'name' => 'Hotel Booking Revenue', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4130', 'name' => 'Commission & Service Fee Income', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4130', 'name' => 'Gateway Fee Recovery', 'level' => 4, 'parent' => 'Commission & Service Fee Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4140', 'name' => 'Sales', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '4150', 'name' => 'Services (other)', 'level' => 3, 'parent' => 'Direct Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
        
            ['code' => '4200', 'name' => 'Indirect Income', 'level' => 2, 'parent' => 'Income', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
        
            // Expenses (Level 2 and deeper)
            ['code' => '5100', 'name' => 'Direct Expenses (Cost of Sales)', 'level' => 2, 'parent' => 'Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5110', 'name' => 'Flights Cost',                    'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5120', 'name' => 'Hotels Cost',                     'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5111', 'name' => 'Visa Cost',             'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5112', 'name' => 'Insurance Cost',        'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5113', 'name' => 'Tour Cost',     'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5114', 'name' => 'Cruise Cost',   'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5115', 'name' => 'Car Cost',      'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5116', 'name' => 'Rail Cost',     'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5117', 'name' => 'Esim Cost',     'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5118', 'name' => 'Event Cost',    'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5119', 'name' => 'Lounge Cost',   'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5121', 'name' => 'Ferry Cost',    'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5130', 'name' => 'Commissions Expense (Agents)',    'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5140', 'name' => 'Payment Gateway Charges',         'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5141', 'name' => 'TAP Charges',             'level' => 4, 'parent' => 'Payment Gateway Charges', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5142', 'name' => 'MyFatoorah Charges',      'level' => 4, 'parent' => 'Payment Gateway Charges', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5143', 'name' => 'Hesabe Charges',          'level' => 4, 'parent' => 'Payment Gateway Charges', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5160', 'name' => 'Agent Salaries',    'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],

            ['code' => '5150', 'name' => 'Stock Expenses',                  'level' => 3, 'parent' => 'Direct Expenses (Cost of Sales)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5151', 'name' => 'Cost of Goods Sold',              'level' => 4, 'parent' => 'Stock Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5152', 'name' => 'Expenses Included in Asset Valuation', 'level' => 4, 'parent' => 'Stock Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5159', 'name' => 'Stock Adjustment',                'level' => 4, 'parent' => 'Stock Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
        
            ['code' => '5200', 'name' => 'Indirect Expenses (Operating Expenses)', 'level' => 2, 'parent' => 'Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5201', 'name' => 'Administrative Expenses',              'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5202', 'name' => 'Commission on Sales',                  'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5203', 'name' => 'Depreciation',                         'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5204', 'name' => 'Entertainment Expenses',               'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5205', 'name' => 'Freight and Forwarding Charges',       'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5206', 'name' => 'Legal Expenses',                       'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5207', 'name' => 'Marketing Expenses',                   'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5208', 'name' => 'Office Maintenance Expenses',          'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5209', 'name' => 'Office Rent',                          'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5210', 'name' => 'Postal Expenses',                      'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5211', 'name' => 'Print and Stationery',                 'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5212', 'name' => 'Round Off',                            'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5213', 'name' => 'Salary',                               'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5214', 'name' => 'Sales Expenses',                       'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5215', 'name' => 'Telephone Expenses',                   'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5216', 'name' => 'Travel Expenses',                      'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5217', 'name' => 'Utility Expenses',                     'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5218', 'name' => 'Write Off',                            'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5219', 'name' => 'Exchange Gain/Loss',                   'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
            ['code' => '5220', 'name' => 'Gain/Loss on Asset Disposal',          'level' => 3, 'parent' => 'Indirect Expenses (Operating Expenses)', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],

            ['code' => '5300', 'name' => 'Refund Clearing / Payable Allocation', 'level' => 2, 'parent' => 'Expenses', 'account_type' => null, 'report_type' => Account::REPORT_TYPES['PROFIT_LOSS']],
        ];

        $parentMap = [];
        foreach ($accounts as $account) {
            $parentId = $account['parent'] && isset($parentMap[$account['parent']])
            ? $parentMap[$account['parent']]->id
            : null;
    
            // Determine root_id
            $rootId = $parentId
                ? $parentMap[$account['parent']]->root_id ?? $parentMap[$account['parent']]->id
                : null;

            $newAccount = Account::updateOrCreate([
                'name' => $account['name'],
                'parent_id' => $parentId,
                'company_id' => $companyId,
                'parent_id' => $parentId,
                'root_id' => $rootId,
            ],[
                'serial_number' => null,
                'account_type' => $account['account_type'],
                'report_type' => $account['report_type'],
                'level' => $account['level'],
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'branch_id' => null,
                'agent_id' => null,
                'client_id' => null,
                'supplier_id' => null,
                'reference_id' => null,
                'code' => $account['code'],
            ]);

            $parentMap[$account['name']] = $newAccount;
        }
    }
    
}
