<?php

namespace App\Imports;

use App\Models\Account;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AccountsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Account([
            'serial_number'         => $row['serial_number'] ?? null,
            'root_id'               => $row['root_id'] ?? null,
            'account_type'          => $row['account_type'] ?? null,
            'report_type'           => $row['report_type'] ?? 'balance sheet',
            'name'                  => $row['name'],
            'level'                 => $row['level'] ?? 0,
            'actual_balance'        => $row['actual_balance'] ?? 0,
            'budget_balance'        => $row['budget_balance'] ?? 0,
            'variance'              => $row['variance'] ?? 0,
            'parent_id'             => $row['parent_id'] ?? null,
            'company_id'            => $row['company_id'],
            'branch_id'             => $row['branch_id'] ?? null,
            'agent_id'              => $row['agent_id'] ?? null,
            'client_id'             => $row['client_id'] ?? null,
            'supplier_id'           => $row['supplier_id'] ?? null,
            'supplier_company_id'   => $row['supplier_company_id'] ?? null,
            'reference_id'          => $row['reference_id'] ?? null,
            'code'                  => $row['code'] ?? null,
            'currency'              => $row['currency'] ?? null,
            'is_group'              => isset($row['is_group']) ? (bool)$row['is_group'] : 1,
            'disabled'              => isset($row['disabled']) ? (bool)$row['disabled'] : 0,
            'balance_must_be'       => $row['balance_must_be'] ?? null,
            'created_at'            => $row['created_at'] ?? null,
            'updated_at'            => $row['updated_at'] ?? null,
            'account_type_id'       => $row['account_type_id'] ?? null,
        ]);
    }
}
