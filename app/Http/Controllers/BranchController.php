<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Hash;

class BranchController extends Controller
{
    use AuthorizesRequests;
    // Display a listing of the branches
    public function index()
    {
        $this->authorize('viewAny', Branch::class);
        $user = Auth::user();

        if ($user->role_id == Role::BRANCH) {

            return view('branches.index', compact('branch'));
        } else {

            $branches = Branch::all();
            foreach ($branches as $key => $branch) {
                // dump($branch->user);
            }


            $branchesCount = Branch::count();
            // dd($branches);
            return view('branches.list', compact('branches', 'branchesCount'));
        }
    }

    // Show the form to create a new branch
    public function create()
    {
        $this->authorize('create', Branch::class);
        return view('branches.store');
    }

    // Store a new branch
    public function store(Request $request)
    {
        $this->authorize('create', Branch::class);
        // Validate the incoming request
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:branches,email', // Corrected table name
            'phone' => 'nullable|string|max:15', // Optional phone field
            'address' => 'required|string|max:255', // Added address validation
        ]);


        // Create a new branch record
        try {

            //Create user before create branch
            $user = User::create([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'password' => Hash::make('password'), // Default password
                'role_id' => Role::BRANCH, // Default role
            ]);

            Branch::create([
                'user_id' => $user->id,
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'phone' => $request->get('phone'),
                'address' => $request->get('address'),
                'company_id' => auth()->user()->company()->first()->id,
            ]);

            // Redirect to the branches list with a success message
            return redirect()->route('branches.index')->with('success', 'Branch added successfully!');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', 'An error occurred: ' . $e->getMessage());
        }
    }
}
