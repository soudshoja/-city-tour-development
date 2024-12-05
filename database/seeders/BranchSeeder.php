<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::create(
            [
                'user_id' => 1,
                'name' => 'Branch 1',
                'address' => '123 Main St, City, Country',
                'phone' => '123-456-7890',
            ]
        )
        // $branches = [
        //     [
        //         'name' => 'Branch 1',
        //         'address' => '123 Main St, City, Country',
        //         'phone' => '123-456-7890',
        //     ],
        //     [
        //         'name' => 'Branch 2',
        //         'address' => '456 Elm St, City, Country',
        //         'phone' => '098-765-4321',
        //     ],
        //     [
        //         'name' => 'Branch 3',
        //         'address' => '789 Oak St, City, Country',
        //         'phone' => '456-789-0123',
        //     ],
        // ];

        // foreach ($branches as $branch) {
        //    Branch::create($branch);
        // }
    }
}
