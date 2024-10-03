<?php

namespace App\Imports;

use App\Models\Agent;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Hash;
class ClientsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {

        $agent = Agent::where('email', $row['agent_email'])->first();

        // Create a new Agent
        return new Client([
            'agent_id' => $agent->id, 
            'name' => $row['name'], 
            'email' => $row['email'], 
            'phone' => $row['phone'], 
            'address' => $row['address'],
            'passport_no' => $row['passport_number'],
        ]);
    }
}