<?php

namespace App\Imports;

use App\Models\Agent;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
class CompaniesImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {

        // Create a new User
        $user = User::create([
            'name' => $row['company_name'],
            'email' => $row['email'],
            'password' => Hash::make('citytour123'),
            'role' => 'company'
        ]);

        // Create a new company
        $company = company::create([
            'user_id' => $user->id,
            'name' => $row['company_name'],
            'code' => $row['company_code'],
            'email' => $row['email'],
            'nationality' => $row['country'],
            'phone' => $row['contact'],
            'address' => $row['address'],
            'status' => true
        ]);    

    }
}