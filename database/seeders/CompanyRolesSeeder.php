<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Company;

class CompanyRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        $roleNames = ['admin', 'company', 'branch', 'agent', 'accountant', 'client'];

        foreach ($companies as $company) {
            foreach ($roleNames as $roleName) {
                // Check if role already exists for this company
                $existingRole = Role::where('name', $roleName)
                    ->where('company_id', $company->id)
                    ->first();

                if (!$existingRole) {
                    Role::create([
                        'name' => $roleName,
                        'guard_name' => 'web',
                        'company_id' => $company->id,
                        'description' => ucfirst($roleName) . ' role for ' . $company->name
                    ]);
                }
            }
        }
    }
}