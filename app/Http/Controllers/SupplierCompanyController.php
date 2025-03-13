<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
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

        return view('supplier-company.index', compact('supplier','companies'));
   } 

   public function activateSupplier(Request $request, Supplier $supplier, Company $company)
   {
     // Check if credentials exist
        $credentialsExist = SupplierCredential::where('supplier_id', $supplier->id)
            ->where('company_id', $company->id)
            ->exists();

        if (!$credentialsExist) {
            return redirect()->back()->with('error', 'Please add credentials for this supplier before activating.');
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
   
}
