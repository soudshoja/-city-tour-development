<?php

namespace Database\Seeders;

use App\Http\Controllers\SupplierCompanyController;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
use App\Models\User;
use Exception;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EntitySeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();

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
                'phone' => '+96522210017',
                'user_id' => $user->id,
                'status' => 1,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        } 

        $role = Role::firstOrCreate(['name' => 'company']);

        $user->assignRole($role);

        $permissionAvailable = Permission::all();

        foreach($permissionAvailable as $permission) {
            $role->givePermissionTo($permission);
        }

        $userCompany = $user;
        try {
            CoaSeeder::run($company->id);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error seeding COA:', ['error' => $e->getMessage()]);
            throw $e;
        }

        $coa = new Account();
       
        $name = 'City Travelers HQ';
        $email = 'hq@citytravelers.co';

        // $user = User::firstOrCreate([
        //     'name' => $name,
        //     'email' => $email,
        // ], [
        //     'password' => Hash::make(config('auth.branch_password')),
        //     'role_id' => Role::BRANCH,
        //     'remember_token' => Str::random(10),
        //     'first_login' => 1,
        // ]);

        try {
            $branch = Branch::firstOrCreate([
                'user_id' => $user->id,
                'name' => $name,
                'email' => $email,
                'phone' => '+96522210017',
                'address' => 'Kuwait City',
                'company_id' => $company->id,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $assetAccount = $coa->where('name', 'like','Assets%')->first();

        if(!$assetAccount){
            DB::rollBack();
            throw new Exception('Assets account not found in COA');
        }

        $accountReceivable = $coa->where('name', 'like','Accounts Receivable%')->first();

        if(!$accountReceivable){
            DB::rollBack();
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
            'role_id' => Role::AGENT,
            'remember_token' => Str::random(10),
            'first_login' => 1,
        ]);

        try{
            $agentType = AgentType::where('name', 'salary')->first();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        try {
            $agent = Agent::firstOrCreate([
                'user_id' => $user->id,
                'name' => $name,
                'tbo_reference' => null,
                'email' => $email,
                'type_id' => $agentType->id,
                'phone_number' => '+96512345678',
                'branch_id' => $branch->id,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
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
            'agent_id' => $agent->id,
        ]);


        // $name = 'ahmed ali';
        // $email = 'ahmedali@gmail.com';

        // Client::firstOrCreate([
        //     'name' => $name,
        //     'email' => $email,
        // ], [
        //     'agent_id' => $agent->id,
        //     'phone' => '+60 0193058463',
        //     'address' => 'Kuwait City',
        //     'passport_no' => null,
        //     'status' => 'active',
        //     'civil_no' => null,
        //     'date_of_birth' => date('Y-m-d', strtotime('1990-01-01')),
        // ]);

        $suppliers = Supplier::all();

        $supplierCompanyController = new SupplierCompanyController();

        foreach ($suppliers as $supplier) {
            if (!($supplier->name == 'Amadeus' || $supplier->name == 'Magic Holiday' || $supplier->name == 'TBO Holiday')) {
                continue;
            }

            // Check if supplier is already activated
            $isActivated = SupplierCompany::where('supplier_id', $supplier->id)
                ->where('company_id', $company->id)
                ->exists();

            if ($isActivated) {
                continue;
            }

            $supplierCompanyController->activateSupplierProcess($supplier, $company);
            
        }
            // $accountPayable = Account::where('name', 'Accounts Payable')->first();


        $coaPaymentGatewayBankAcc = Account::where('name', 'Payment Gateway')
        ->where('company_id', $userCompany->company->id)
        ->first(); 

        $coaBankAccount = Account::whereHas('parent', function ($query) {
            $query->where('name', 'Bank Accounts');
        })
        ->where('company_id', $userCompany->company->id)
        ->first();  // Use first() to get a single model, not a collection

        try {
            $paymentGatewayNames = ['Tap', 'MyFatoorah', 'Hesabe'];

            foreach ($paymentGatewayNames as $paymentGatewayName) {
                $asset = Account::where('name', 'Assets')->first();
                $expenses = Account::where('name', 'Expenses')->first();
                $parentPaymentGateway = Account::where('name', 'Payment Gateway')
                    ->where('root_id', $asset->id)
                    ->first();

                $coaPaymentGateway = Account::where(function ($query) use ($paymentGatewayName, $userCompany, $asset) {
                        $query->where('name', 'like', '%' . $paymentGatewayName . '%')
                            ->where('company_id', $userCompany->company->id)
                            ->where('root_id', $asset->id);
                    })
                    ->orWhere(function ($query) {
                        $query->whereHas('parent', function ($query) {
                            $query->where('name', 'Payment Gateway Charges');
                        });
                    })
                    ->first();

                if (!$coaPaymentGateway) {
                    throw new Exception("COA account for {$paymentGatewayName} not found.");
                }

                // Create a new sub-account under the payment gateway parent (if needed)
                $newAccountBankFee = Account::create([
                    'name' => $paymentGatewayName,
                    'code' => '1310', 
                    'root_id' => $asset->id,
                    'parent_id' => $parentPaymentGateway->id,
                    'company_id' => $userCompany->company->id,
                    'branch_id' => $userCompany->branch_id,
                    'account_type' => 'asset',
                    'report_type' => 'balance sheet',
                    'level' => 4,
                    'is_group' => 0,
                    'disabled' => 0,
                    'actual_balance' => 0.00,
                    'budget_balance' => 0.00,
                    'variance' => 0.00,
                    'currency' => 'KWD',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $kuwaitBankAccount = Account::where('name', 'Kuwait International Bank')->first();
                if (!$kuwaitBankAccount) {
                    throw new Exception("Kuwait International Bank account not found.");
                }

                $coaChargesAcc = Account::where('name', $paymentGatewayName . ' Charges')
                    ->where('company_id', $userCompany->company->id)
                    ->where('root_id', $expenses->id)
                    ->first();

                Charge::create([
                    'name' => $paymentGatewayName,
                    'description' => 'Payment Gateway Fee',
                    'type' => 'Payment Gateway',
                    'amount' => 0.25,
                    'acc_fee_id' => $coaChargesAcc->id,
                    'acc_bank_id' => $kuwaitBankAccount->id,
                    'acc_fee_bank_id' => $newAccountBankFee->id,
                    'company_id' => $userCompany->company->id,
                    'branch_id' => $userCompany->branch->id,
                ]);
            }

    
            // Commit the transaction

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }



        DB::commit();
    }
}
