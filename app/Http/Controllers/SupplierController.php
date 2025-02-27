<?php

namespace App\Http\Controllers;

use App\Models\GeneralLedger;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Supplier;
use DateTime;
use Generator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class SupplierController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        Gate::authorize('view supplier');
        $user = auth()->user();

        if($user->role_id == Role::ADMIN) {
            $suppliers = Supplier::all();
        } elseif($user->role_id == Role::COMPANY) {
            $suppliers = $user->company->suppliers()->get();
        } else {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

        foreach ($suppliers as $supplier) {
            if (!is_null($supplier->route)) {
                $route = Route::getRoutes()->getByName('suppliers.'. $supplier->route . '.index');
                $supplier->named_route = $route ? $route->getName() : null;
            } else {
                $supplier->named_route = null;
            }
        }
        $suppliersCount = Supplier::count();
       
        return view('suppliers.index', compact('suppliers', 'suppliersCount'));
    }

    public function show($suppliersId)
    {
        if (Auth::user()->role_id !== Role::ADMIN && Auth::user()->role_id !== Role::COMPANY) {
            abort(403, 'Unauthorized action.');
        }

        $supplier = Supplier::with('tasks.invoiceDetail.invoice')->findOrFail($suppliersId);
        $invoicesId = $supplier->tasks->pluck('invoiceDetail.invoice_id')->toArray();
        $invoicesId = array_values(array_filter($invoicesId));

        $generalLedger = GeneralLedger::select('id', 'debit', 'credit', 'created_at')
            ->whereIn('invoice_id', $invoicesId)
            ->get();

        return view('suppliers.show', compact('supplier', 'generalLedger'));
    }

    public function create()
    {
        // Check if the user has an admin role
        if (Auth::user()->role_id !== Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        // Return view
        return view('suppliers.SuppliersCreate');
    }

    public function store(Request $request)
    {
        // Check if the user has an admin role
        if (Auth::user()->role_id !== Role::ADMIN) {
            abort(403, 'Unauthorized action.');
        }

        // Validate the request
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'address' => 'required',
        ]);

        // Create a new supplier
        Supplier::create($request->all());

        // Redirect to the suppliers list
        return redirect()->route('suppliers.index');
    }

    public function getTotalDebitCredit($supplierId, $endDate)
    {
        $endDate = new DateTime($endDate);
        $supplier = Supplier::with('tasks.invoiceDetail.invoice')->findOrFail($supplierId);
        $invoicesId = $supplier->tasks->pluck('invoiceDetail.invoice_id')->toArray();
        $invoicesId = array_values(array_filter($invoicesId));
        $totalDebit = GeneralLedger::whereIn('invoice_id', $invoicesId)->where('created_at', '<=', $endDate)->sum('debit');
        $totalCredit = GeneralLedger::whereIn('invoice_id', $invoicesId)->where('created_at', '<=', $endDate)->sum('credit');
        
        return response()->json([
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
        ]);
    }
}
