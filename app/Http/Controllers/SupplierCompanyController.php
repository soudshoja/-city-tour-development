<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use App\Models\SupplierCredential;
use Illuminate\Http\Request;

class SupplierCompanyController extends Controller
{
   public function index()
   {
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

        // Activate supplier using SupplierCompany model
        $supplierCompany = SupplierCompany::firstOrCreate([
            'supplier_id' => $supplier->id,
            'company_id' => $company->id,
        ]);

        $supplierCompany->is_active = true;
        $supplierCompany->save();

        return redirect()->back()->with('success', 'Supplier activated successfully.');
   }

    public function deactivateSupplier(Request $request, Supplier $supplier, Company $company)
    {
          // Deactivate supplier using SupplierCompany model
          $supplierCompany = SupplierCompany::where('supplier_id', $supplier->id)
                ->where('company_id', $company->id)
                ->first();
    
          $supplierCompany->is_active = false;
          $supplierCompany->save();
    
          return redirect()->back()->with('success', 'Supplier deactivated successfully.');
    }   
   
}
