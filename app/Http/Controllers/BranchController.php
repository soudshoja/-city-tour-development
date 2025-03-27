<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
            if ($user->role_id == Role::ADMIN) {
                $branches = Branch::all();

            } elseif ($user->role_id == Role::COMPANY) {
                // Get agents belonging to the company
                $branches = Branch::where('company_id', $user->company->id)->get();
            }

            $branchesCount = $branches->count();
            // dd($branches);
            return view('branches.list', compact('branches', 'branchesCount'));
        }
    }


    // Display the specified branch
    public function show($id)
    {
        $branch = Branch::findOrFail($id);
        if (!$branch) {
            return response()->json(['error' => 'branch not found'], 404);
        }

        return response()->json([
            'id' => $branch->id,
            'name' => $branch->name,
            'email' => $branch->email,
            'phone' => $branch->phone,
        ]);
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
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:branches,email',
            'dial_code' => 'nullable|string|max:30',
            'phone' => 'nullable|string|max:15',
            'address' => 'nullable|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        try {
            $branch = Branch::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'phone' => ($validatedData['dial_code'] ?? '') . ($validatedData['phone'] ?? ''),
                'address' => $validatedData['address'] ?? null,
                'company_id' => $validatedData['company_id'],
                'user_id' => $validatedData['user_id'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Branch created successfully',
                'data' => $branch,
            ], 201);
        } catch (Exception $e) {

            logger('Branch creation failed with error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Branch creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
