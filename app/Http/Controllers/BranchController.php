<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class BranchController extends Controller
{
    // Display a listing of the branches
    public function index()
    {
        $user = Auth::user();
        $branches = Branch::all();
        $branchesCount = Branch::count();
       
        return view('companies.branches.bList', compact('branches', 'branchesCount'));
    }

    // Show the form to create a new branch
    public function create()
    {
        return view('branches.store');
    }

    // Store a new branch
    public function store(Request $request)
    {   
        // Validate the incoming request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:branches,email', // Corrected table name
            'phone' => 'nullable|string|max:15', // Optional phone field
            'address' => 'required|string|max:255', // Added address validation
        ]);
        
        // Create a new branch record
        try {
            Branch::create([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'phone' => $request->get('phone'),
                'address' => $request->get('address'),
            ]);

            // Redirect to the branches list with a success message
           return redirect()->route('brancheslist.index')->with('success', 'Branch added successfully!');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }
}