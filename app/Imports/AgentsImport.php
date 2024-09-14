<?php

namespace App\Imports;

use App\Models\Agent;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\User;
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
        ]);

        // Create a new Agent
        return new Agent([
            'user_id' => $user->id,
            'company_id' => '1', 
            'type' =>'staff', 
            'email' => $user->email,
            'name' => $user->name,
            'phone_number' => $row['phone_number'],
        ]);
    }
}