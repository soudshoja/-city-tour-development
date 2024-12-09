<?php

namespace App\Imports;

use App\Models\Agent;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
class AgentsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        // Create a new User
        $user = User::create([
            'name' => $row['name'],
            'email' => $row['email'],
            'password' => Hash::make('citytour123'),
            'role' => 'agent'
        ]);

        $company = Company::where('email', $row['company_email'])->first();

        // Create a new Agent
        return new Agent([
            'user_id' => $user->id,
            'company_id' => $company->id, 
            'type' => $row['type'], 
            'email' => $user->email,
            'name' => $user->name,
            'phone_number' => $row['phone_number'],
        ]);
    }
}