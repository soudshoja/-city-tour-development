<?php

namespace App\Exports;

use App\Models\Account;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AccountsExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Account::select([
            'serial_number', 'root_id', 'account_type', 'report_type', 'name', 'level',
            'actual_balance', 'budget_balance', 'variance', 'parent_id', 'company_id', 'branch_id',
            'agent_id', 'client_id', 'supplier_id', 'supplier_company_id', 'reference_id',
            'code', 'currency', 'is_group', 'disabled', 'balance_must_be', 'created_at', 'updated_at', 'account_type_id'
        ])->get();
    }

    public function headings(): array
    {
        return [
            'serial_number', 'root_id', 'account_type', 'report_type', 'name', 'level',
            'actual_balance', 'budget_balance', 'variance', 'parent_id', 'company_id', 'branch_id',
            'agent_id', 'client_id', 'supplier_id', 'supplier_company_id', 'reference_id',
            'code', 'currency', 'is_group', 'disabled', 'balance_must_be', 'created_at', 'updated_at', 'account_type_id'
        ];
    }
}
