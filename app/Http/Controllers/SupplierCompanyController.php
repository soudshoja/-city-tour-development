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
use Illuminate\Support\Facades\Log;

class SupplierCompanyController extends Controller
{
    public function edit($id)
    {
        $companies = Company::all();
        $supplier = Supplier::findOrFail($id);

        // Check if the supplier is already activated for any company
        $activatedCompanies = SupplierCompany::where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->pluck('company_id')
            ->toArray();
        $companies = $companies->map(function ($company) use ($activatedCompanies) {
            $company->is_active = in_array($company->id, $activatedCompanies);

            return $company;
        });

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
            $result = app(\App\Services\SupplierActivationService::class)->activate($supplier, $company);
            DB::commit();
            return $result;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to activate supplier: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to activate supplier: ' . $e->getMessage()];
        }
    }

    public function activateSupplier(Request $request, ?Supplier $supplier = null, ?Company $company = null)
    {
        if ($request->has('supplier_id')) {
            $request->validate([
                'company_id' => 'required|exists:companies,id',
            ]);

            try {
                $supplier = Supplier::findOrFail($request->input('supplier_id'));
                $company = Company::findOrFail($request->input('company_id'));

                $response = $this->activateSupplierProcess($supplier, $company);

                if ($response['status'] === 'error') {

                    Log::error('[SUPPLIER COMPANY] Failed to activate supplier: '.$response['message']);

                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => 'Failed to activate supplier'], 422);
                    }

                    return redirect()->back()->with('error', $response['message']);
                }

            } catch (Exception $e) {

                Log::error('[SUPPLIER COMPANY] Failed to activate supplier: '.$e->getMessage());

                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Failed to activate supplier'], 422);
                }

                return redirect()->back()->with('error', 'Failed to activate supplier: '.$e->getMessage());
            }

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Supplier activated successfully.']);
            }

            return redirect()->back()->with('success', 'Supplier activated successfully.');
        } elseif ($supplier && $company) {
            try {
                $this->activateSupplierProcess($supplier, $company);
            } catch (Exception $e) {
                return redirect()->back()->with('error', 'Failed to activate supplier: '.$e->getMessage());
            }

            return redirect()->back()->with('success', 'Supplier activated successfully.');
        }
    }

    public function deactivateSupplier(Request $request, Supplier $supplier, Company $company)
    {
        if (isset($request->supplier_id)) {

            try {
                $supplierCompany = SupplierCompany::where('supplier_id', $request->supplier_id)
                    ->where('company_id', $request->company_id)
                    ->first();
                if (! $supplierCompany) {
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => 'Supplier is not activated for this company.'], 422);
                    }

                    return redirect()->back()->with('error', 'Supplier is not activated for this company.');
                }
                $supplierCompany->is_active = false;
                $supplierCompany->update();

            } catch (Exception $e) {

                Log::error('Failed to deactivate supplier: '.$e->getMessage());
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => 'Failed to deactivate supplier'], 422);
                }

                return redirect()->back()->with('error', 'Failed to deactivate supplier');

            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Supplier deactivated successfully.']);
        }

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
                'code' => 'SUP'.$accountPayable->id.str_pad($accountPayable->children->count() + 1, 3, '0', STR_PAD_LEFT),
            ]);

            SupplierCompany::firstOrCreate([
                'supplier_id' => $supplier->id,
                'company_id' => $companyId,
                // 'account_id' => $account->id
            ]);
        } catch (Exception $e) {
            logger('Created Supplier Company Error: '.$e->getMessage());

            return [
                'status' => 'error',
                'message' => 'Failed to create supplier company.',
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Supplier activated successfully.',
        ];
    }
}
