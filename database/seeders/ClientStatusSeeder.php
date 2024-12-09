<?php

namespace Database\Seeders;

use App\Models\ClientStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClientStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            'Active',
            'Inactive',
            'Suspended',
            'Terminated',
        ];

        foreach ($statuses as $status) {
            ClientStatus::create([
                'name' => $status,
            ]);
        }
    }
}
