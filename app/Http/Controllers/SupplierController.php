<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Suppliers;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        // Check if the user has an admin role
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        // Get all the suppliers
        $suppliers = Suppliers::all();
     
        // Count the suppliers
        $SuppliersCount = Suppliers::count();
        
        // Return view with suppliers and the count
        return view('suppliers.SuppliersList', compact('suppliers', 'SuppliersCount'));
    }
}