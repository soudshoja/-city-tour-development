<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function activateSupplierProcess(Supplier $supplier, Company $company)
    {
        DB::beginTransaction();
        try {
            // Check if supplier is already activated
            $isActivated = SupplierCompany::where('supplier_id', $supplier->id)
                ->where('company_id', $company->id)
                ->exists();

            if ($isActivated) {
                throw new Exception('Supplier already activated.');
            }

            // Check if credentials exist
            $credentials = SupplierCredential::where('supplier_id', $supplier->id)
                ->where('company_id', $company->id)
                ->exists();


            if (!$credentials) {
                SupplierCredential::create([
                    'supplier_id' => $supplier->id,
                    'company_id' => $company->id,
                    'environment' => env('APP_ENV') == 'production' ? 'production' : 'sandbox',
                    'type' => 'basic',
                    'username' => 'test',
                    'password' => 'test',
                    'client_id' => null,
                    'client_secret' => null,
                    'access_token' => null,
                    'refresh_token' => null,
                    'expires_at' => null,

                ]);
            }

            $parentAccountName = $supplier->has_flight
                ? 'Suppliers (Flights)'
                : ($supplier->has_hotel ? 'Suppliers (Hotels)' : 'Accounts Payable');

            $accountPayable = Account::where('name', $parentAccountName)->first();

            if (!$accountPayable) {
                throw new Exception("Account Payable group '$parentAccountName' not found.");
            }

            $supplierCostAccount = collect();

            if ($supplier->has_flight) {
                $supplierCostAccount = Account::where('name', 'Flights Cost')->first();
            } else if ($supplier->has_hotel) {
                $supplierCostAccount = Account::where('name', 'Hotels Cost')->first();
            } else {
                throw new Exception('Supplier is not a flight or hotel supplier.');
            }

            if (!$supplierCostAccount) {
                throw new Exception("Supplier cost account not found.");
            }

            SupplierCompany::firstOrCreate([
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
            ]);


            $supplierCompany = SupplierCompany::where('supplier_id', $supplier->id)
                ->where('company_id', $company->id)
                ->first();

            $data = [
                'name' => $supplier->name,
                'level' => 4,
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'company_id' => $company->id,
                'supplier_company_id' => $supplierCompany->id,
            ];


            $accountPayableCode = (int)$accountPayable->code + 1;

            Account::create(
                $data + [
                    'parent_id' => $accountPayable->id,
                    'root_id' => $accountPayable->root_id,
                    'code' => (string)$accountPayableCode,
                ]
            );

            Account::create(
                $data + [
                    'parent_id' => $supplierCostAccount->id,
                    'root_id' => $supplierCostAccount->root_id,
                    'code' => (string)$supplierCostAccount->code,
                ]
            );

            // $account = Account::create([
            //     'name' => $supplier->name,
            //     'level' => 4,
            //     'actual_balance' => 0,
            //     'budget_balance' => 0,
            //     'variance' => 0,
            //     'company_id' => $company->id,
            //     'parent_id' => $accountPayable->id,
            //     'code' => 'SUP' . $accountPayable->id . str_pad($accountPayable->children()->count() + 1, 3, '0', STR_PAD_LEFT),
            // ]);
        } catch (Exception $e) {
            DB::rollBack();
            logger('Created Supplier Company Account Error: ' . $e->getMessage());
            throw new Exception('Failed to create supplier account.');
        }

        DB::commit();

    }

    public function activateSupplier(Request $request, ?Supplier $supplier = null, ?Company $company = null)
    {
        if ($request->has('supplier_id')) {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
            ]);

            try{
                $supplier = Supplier::findOrFail($request->input('supplier_id'));
                $company = Company::findOrFail($request->input('company_id'));

                $this->activateSupplierProcess($supplier, $company);

            } catch (Exception $e) {
                return redirect()->back()->with('error', 'Failed to activate supplier: ' . $e->getMessage());
            }
         
            return redirect()->back()->with('success', 'Supplier activated successfully.'); 
        } else if($supplier && $company) {
            try {
                $this->activateSupplierProcess($supplier, $company);
            } catch (Exception $e) {
                return redirect()->back()->with('error', 'Failed to activate supplier: ' . $e->getMessage());
            }

            return redirect()->back()->with('success', 'Supplier activated successfully.');
        }
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
                // 'account_id' => $account->id
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
