<?php

namespace App\Http\Controllers;

use App\Models\SupplierProcedure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierProcedureController extends Controller
{
    public function store(Request $request){
        $request->validate([
            'supplier_company_id' => 'required|exists:supplier_companies,id',
            'name' => 'required|string|max:255',
            'procedure' => 'required|string',
        ]);

        try{
            SupplierProcedure::create([
                'supplier_company_id' => $request->supplier_company_id,
                'name' => $request->name,
                'procedure' => $request->procedure,
            ]);
        } catch (Exception $e){
            return redirect()->back()->with('error', 'Failed to add supplier procedure: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Supplier procedure added successfully.');
    }

    public function activate($procedureId)
    {
        try {
            $procedure = SupplierProcedure::findOrFail($procedureId);
            
            // Begin transaction to ensure atomicity
            DB::transaction(function () use ($procedure) {
                // Deactivate all other procedures for this supplier-company
                SupplierProcedure::where('supplier_company_id', $procedure->supplier_company_id)
                    ->where('id', '!=', $procedure->id)
                    ->update(['is_active' => false]);
                
                // Activate the selected procedure
                $procedure->update(['is_active' => true]);
            });

            return redirect()->back()->with('success', 'Procedure activated successfully.');
            
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to activate procedure: ' . $e->getMessage());
        }
    }

    public function show($procedureId)
    {
        try {
            $procedure = SupplierProcedure::with('supplierCompany.supplier', 'supplierCompany.company')
                ->findOrFail($procedureId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $procedure->id,
                    'name' => $procedure->name,
                    'procedure' => $procedure->procedure,
                    'is_active' => $procedure->is_active,
                    'created_at' => $procedure->created_at->format('M d, Y H:i'),
                    'updated_at' => $procedure->updated_at->format('M d, Y H:i'),
                    'supplier_name' => $procedure->supplierCompany->supplier->name ?? 'N/A',
                    'company_name' => $procedure->supplierCompany->company->name ?? 'N/A',
                ]
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch procedure: ' . $e->getMessage()
            ], 404);
        }
    }
}
