<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
use Exception;
use Illuminate\Http\Request;

class SupplierCompanyController extends Controller
{
    public function edit($id)
    {
        $supplier = Supplier::find($id);
        $companies = Company::with('suppliers.credentials')->get();

        // $companies = $companies->map(function ($company) use ($supplier) {
        //     $company->is_active = $company->suppliers->contains('id', $supplier->id);
        //     return $company;
        // });

        return view('supplier-company.index', compact('supplier', 'companies'));
    }

    public function activateSupplier(Request $request, Supplier $supplier, Company $company)
    {
        if ($request->has('supplier_id')) {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
            ]);

            $company = Company::find($request->company_id);

            $supplier = Supplier::find($request->supplier_id);

            // Check if supplier is already activated
            $isActivated = SupplierCompany::where('supplier_id', $supplier->id)
                ->where('company_id', $company->id)
                ->exists();

            if ($isActivated) {
                return redirect()->back()->with('error', 'Supplier already activated.');
            } else {
                // Check if credentials exist
                $credentials = SupplierCredential::where('supplier_id', $supplier->id)
                    ->where('company_id', $company->id)
                    ->exists();

                $request->validate([
                    'type' => 'required',
                    'username' => 'required_if:type,basic',
                    'password' => 'required_if:type,basic',
                    'client_id' => 'required_if:type,oauth',
                    'client_secret' => 'required_if:type,oauth',
                ]);

                if (!$credentials) {
                    SupplierCredential::create([
                        'supplier_id' => $supplier->id,
                        'company_id' => $company->id,
                        'environment' => env('APP_ENV') == 'production' ? 'production' : 'sandbox',
                        'type' => $request->type,
                        'username' => $request->username,
                        'password' => $request->password,
                        'client_id' => $request->client_id,
                        'client_secret' => $request->client_secret,
                        'access_token' => $request->access_token,
                        'refresh_token' => $request->refresh_token,
                        'expires_at' => $request->expires_at,
                    ]);
                }

                try {
                    $accountPayable = Account::where('name', 'Accounts Payable')->first();

                    $account = Account::create([
                        'name' => $supplier->name,
                        'level' => 4,
                        'actual_balance' => 0,
                        'budget_balance' => 0,
                        'variance' => 0,
                        'company_id' => $company->id,
                        'parent_id' => $accountPayable->id,
                        'code' => 'SUP' . $accountPayable->id . str_pad($accountPayable->children->count() + 1, 3, '0', STR_PAD_LEFT),
                    ]);

                    SupplierCompany::firstOrCreate([
                        'supplier_id' => $supplier->id,
                        'company_id' => $company->id,
                        'account_id' => $account->id
                    ]);
                } catch (Exception $e) {
                    logger('Created Supplier Company Error: ' . $e->getMessage());
                    return redirect()->back()->with('error', 'Supplier credentials not found.');
                }
            }

            return redirect()->back()->with('success', 'Supplier activated successfully.');
        }


        $accountPayable = Account::where('name', 'Accounts Payable')->first();

        $account = Account::create([
            'name' => $supplier->name,
            'level' => 4,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
            'company_id' => $company->id,
            'parent_id' => $accountPayable->id,
            'code' => 'SUP' . $accountPayable->id . str_pad($accountPayable->children->count() + 1, 3, '0', STR_PAD_LEFT),
        ]);

        SupplierCompany::firstOrCreate([
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
            'account_id' => $account->id
        ]);

        return redirect()->back()->with('success', 'Supplier activated successfully.');
    }

    public function deactivateSupplier(Request $request, Supplier $supplier, Company $company)
    {
        // Deactivate supplier using SupplierCompany model
        SupplierCompany::where('supplier_id', $supplier->id)
            ->where('company_id', $company->id)
            ->delete();

        return redirect()->back()->with('success', 'Supplier deactivated successfully.');
    }

    private function createData(Supplier $supplier, int $companyId, Account $accountPayable)
    {
        try {
            $account = Account::create([
                'name' => $supplier->name,
                'level' => 4,
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'company_id' => $companyId,
                'parent_id' => $accountPayable->id,
                'code' => 'SUP' . $accountPayable->id . str_pad($accountPayable->children->count() + 1, 3, '0', STR_PAD_LEFT),
            ]);

            SupplierCompany::firstOrCreate([
                'supplier_id' => $supplier->id,
                'company_id' => $companyId,
                'account_id' => $account->id
            ]);
        } catch (Exception $e) {
            logger('Created Supplier Company Error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to create supplier company.'
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Supplier activated successfully.'
        ];
    }
}
