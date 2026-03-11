<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use Illuminate\Http\Request;

class ResailAISupplierController extends Controller
{
    /**
     * Display the ResailAI supplier settings page.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $companyId = $request->query('company_id');

        // Get all suppliers with their auto_process_pdf status
        $suppliers = \DB::table('supplier_companies')
            ->join('suppliers', 'supplier_companies.supplier_id', '=', 'suppliers.id')
            ->select(
                'supplier_companies.supplier_id',
                'suppliers.name as supplier_name',
                'supplier_companies.auto_process_pdf',
                'supplier_companies.is_active',
                'supplier_companies.company_id',
                'supplier_companies.updated_at'
            )
            ->when($companyId, function ($query) use ($companyId) {
                return $query->where('supplier_companies.company_id', $companyId);
            })
            ->groupBy('supplier_companies.supplier_id', 'suppliers.name', 'supplier_companies.auto_process_pdf', 'supplier_companies.is_active', 'supplier_companies.company_id', 'supplier_companies.updated_at')
            ->orderBy('suppliers.name')
            ->get()
            ->map(function ($item) {
                return [
                    'supplier_id' => $item->supplier_id,
                    'supplier_name' => $item->supplier_name,
                    'supplier_slug' => strtolower(str_replace(' ', '-', $item->supplier_name)),
                    'auto_process_pdf' => (bool) $item->auto_process_pdf,
                    'is_active' => (bool) $item->is_active,
                    'company_id' => $item->company_id,
                    'updated_at' => $item->updated_at,
                ];
            });

        return view('resailai.suppliers', [
            'suppliers' => $suppliers,
            'companyId' => $companyId,
        ]);
    }

    /**
     * Toggle auto_process_pdf flag for a supplier (web version).
     *
     * @param int $supplierId
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggle(int $supplierId, Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        $supplierCompany = SupplierCompany::where('supplier_id', $supplierId)
            ->where('company_id', $validated['company_id'])
            ->first();

        if (!$supplierCompany) {
            return redirect()->back()->with('error', 'Supplier not configured for this company');
        }

        $newValue = !$supplierCompany->auto_process_pdf;

        $supplierCompany->update([
            'auto_process_pdf' => $newValue,
        ]);

        return redirect()->back()->with('success', 'Auto-process PDF flag updated successfully');
    }
}
