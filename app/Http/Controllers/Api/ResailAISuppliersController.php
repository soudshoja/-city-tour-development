<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ResailAISuppliersController extends Controller
{
    /**
     * List all suppliers with their auto_process_pdf status.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');

        if ($companyId) {
            // Get suppliers for specific company
            $suppliers = SupplierCompany::where('company_id', $companyId)
                ->with(['supplier'])
                ->get()
                ->map(function ($sc) {
                    return [
                        'supplier_id' => $sc->supplier_id,
                        'supplier_name' => $sc->supplier->name ?? 'Unknown',
                        'supplier_slug' => $sc->supplier->name ? strtolower(str_replace(' ', '-', $sc->supplier->name)) : 'unknown',
                        'auto_process_pdf' => (bool) $sc->auto_process_pdf,
                        'is_active' => (bool) $sc->is_active,
                        'created_at' => $sc->created_at?->toIso8601String(),
                        'updated_at' => $sc->updated_at?->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $suppliers->values(),
                'company_id' => $companyId,
            ]);
        }

        // Get all suppliers with their status (for all companies)
        $suppliers = DB::table('supplier_companies')
            ->join('suppliers', 'supplier_companies.supplier_id', '=', 'suppliers.id')
            ->select(
                'supplier_companies.supplier_id',
                'suppliers.name as supplier_name',
                'supplier_companies.auto_process_pdf',
                'supplier_companies.is_active',
                'supplier_companies.company_id',
                DB::raw('COUNT(DISTINCT supplier_companies.company_id) as companies_count')
            )
            ->groupBy('supplier_companies.supplier_id', 'suppliers.name', 'supplier_companies.auto_process_pdf', 'supplier_companies.is_active')
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
                    'companies_count' => (int) $item->companies_count,
                    'updated_at' => now()->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $suppliers->values(),
        ]);
    }

    /**
     * Toggle auto_process_pdf flag for a supplier.
     *
     * @param int $supplierId
     * @param Request $request
     * @return JsonResponse
     */
    public function toggle(int $supplierId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        $supplierCompany = SupplierCompany::where('supplier_id', $supplierId)
            ->where('company_id', $validated['company_id'])
            ->first();

        if (!$supplierCompany) {
            return response()->json([
                'success' => false,
                'error' => 'Supplier not configured for this company',
            ], 404);
        }

        $newValue = !$supplierCompany->auto_process_pdf;

        $supplierCompany->update([
            'auto_process_pdf' => $newValue,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'auto_process_pdf flag toggled successfully',
            'data' => [
                'supplier_id' => $supplierId,
                'company_id' => $validated['company_id'],
                'auto_process_pdf' => $newValue,
            ],
        ]);
    }
}
