<?php

namespace App\Imports;

use App\Models\Agent;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\company;
use Illuminate\Support\Facades\Hash;
class CompaniesImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        // Create a new company
        $company = company::create([
            'name' => $row['name'],
            'code' => $row['code'],
            'nationality' => $row['nationality'],
        ]);


    }
}