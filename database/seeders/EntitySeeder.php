<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EntitySeeder extends Seeder
{
    public function run(): void
    {
        $name = 'City Travelers';
        $email = 'admin@citytravelers.co';

        $countryId = Country::firstOrCreate(['name' => 'Kuwait'])->id ?? 1;

        $user = User::firstOrCreate([
            'name' => $name,
            'email' => $email,
        ], [
            'password' => Hash::make(config('auth.company_password')),
            'role_id' => Role::COMPANY,
            'remember_token' => Str::random(10),
            'first_login' => 1,
        ]);

        try {
            $company = Company::firstOrCreate([
                'name' => $name,
                'email' => $email,
            ], [
                'code' => 'CT00001',
                'country_id' => $countryId,
                'address' => 'Kuwait City',
                'phone' => '+965 12345678',
                'user_id' => $user->id,
                'status' => 1,
            ]);
        } catch (Exception $e) {
            $user->delete();
            throw $e;
        } 

        $role = Role::firstOrCreate(['name' => 'company']);

        $user->assignRole($role);

        try {
            CoaSeeder::run($company->id);
        } catch (Exception $e) {
            Log::error('Error seeding COA:', ['error' => $e->getMessage()]);
            throw $e;
        }

        $coa = new Account();
       
        $name = 'Branch 1';
        $email = 'branch1@citytravelers.co';

        $user = User::firstOrCreate([
            'name' => $name,
            'email' => $email,
        ], [
            'password' => Hash::make(config('auth.branch_password')),
            'role_id' => Role::BRANCH,
            'remember_token' => Str::random(10),
            'first_login' => 1,
        ]);

        try {
            $branch = Branch::firstOrCreate([
                'user_id' => $user->id,
                'name' => $name,
                'email' => $email,
                'phone' => '+965 4543266',
                'address' => 'Kuwait City',
                'company_id' => $company->id,
            ]);
        } catch (Exception $e) {
            $user->delete();
            throw $e;
        }

        $assetAccount = $coa->where('name', 'like','Assets%')->first();

        if(!$assetAccount){
            throw new Exception('Assets account not found in COA');
        }

        $accountReceivable = $coa->where('name', 'like','Accounts Receivable%')->first();

        if(!$accountReceivable){
            throw new Exception('Account Receivable not found in COA');
        }

        $branchAccount = Account::firstOrCreate([
            'account_type' => null,
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'name' => $branch->name,
            'level' => 3,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'parent_id' => $accountReceivable->id,
            'root_id' => $assetAccount->id,
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'account_type_id' => null,
            'code' => '1351',
            'reference_id' => null,
            'currency' => 'KWD',
            'is_group' => 1,
            'disabled' => 0,
        ]);

        $role = Role::firstOrCreate(['name' => 'branch']);

        $user->assignRole($role);


        $name = 'Soud Shoja';
        $email = 'soud@shoja.co';

        $user = User::firstOrCreate([
            'name' => $name,
            'email' => $email,
        ], [
            'password' => Hash::make(config('auth.agent_password')),
            'role_id' => Role::COMPANY,
            'remember_token' => Str::random(10),
            'first_login' => 1,
        ]);

        try{
            $agentType = AgentType::where('name', 'salary')->first();
        } catch (Exception $e) {
            throw $e;
        }

        try {
            $agent = Agent::firstOrCreate([
                'user_id' => $user->id,
                'name' => $name,
                'tbo_reference' => null,
                'email' => $email,
                'type_id' => $agentType->id,
                'phone_number' => '+965 12345678',
                'branch_id' => $branch->id,
            ]);
        } catch (Exception $e) {
            $user->delete();
            throw $e;
        }

        $role = Role::firstOrCreate(['name' => 'agent']);

        $user->assignRole($role);

        Account::firstOrCreate([
            'account_type' => null,
            'report_type' => Account::REPORT_TYPES['BALANCE_SHEET'],
            'name' => $agent->name,
            'level' => 3,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'parent_id' => $branchAccount->id,
            'root_id' => $assetAccount->id,
            'company_id' => $company->id,
            'account_type_id' => null,
            'code' => '1361',
            'reference_id' => null,
            'currency' => 'KWD',
            'is_group' => 0,
            'disabled' => 0,
        ]);


        $name = 'ahmed ali';
        $email = 'ahmedali@gmail.com';

        Client::firstOrCreate([
            'name' => $name,
            'email' => $email,
        ], [
            'agent_id' => $agent->id,
            'phone' => '+60 0193058463',
            'address' => 'Kuwait City',
            'passport_no' => null,
            'status' => 'active',
            'civil_no' => null,
            'date_of_birth' => date('Y-m-d', strtotime('1990-01-01')),
        ]);

    }
}
