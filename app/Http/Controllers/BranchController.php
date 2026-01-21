<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class BranchController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Branch::class);

        $user = Auth::user();
        $isAdmin = $user->role_id == Role::ADMIN;
        $query = $request->get('q');

        $companyId = getCompanyId($user);

        if ($user->role_id == Role::BRANCH) {
            $branch = $user->branch;
            return view('branches.index', compact('branch'));
        }

        $branchesQuery = Branch::with('company');

        if ($isAdmin) {
            if ($companyId) {
                $branchesQuery->where('company_id', $companyId);
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $branchesQuery->where('company_id', $companyId);
        }

        if ($query) {
            $branchesQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            });
        }

        $branches = $branchesQuery->paginate(20)->withQueryString();

        return view('branches.list', compact('branches'));
    }

    // Display the specified branch
    public function show($id)
    {
        $branch = Branch::with(['company', 'agents.agentType'])->findOrFail($id);

        return view('branches.show', compact('branch'));
    }

    // Show the form to create a new branch
    public function create()
    {
        $this->authorize('create', Branch::class);

        return view('branches.store');
    }

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

        DB::beginTransaction();

        try {
            $branch = Branch::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'phone' => ($validatedData['dial_code'] ?? '') . ($validatedData['phone'] ?? ''),
                'address' => $validatedData['address'] ?? null,
                'company_id' => $validatedData['company_id'],
                'user_id' => $validatedData['user_id'],
            ]);

            if (!$branch) {
                throw new Exception('Branch creation failed');
            }

            $user = User::find($validatedData['user_id']);

            if (!$user) {
                throw new Exception('User not found on branch creation');
            }

            $company = Company::find($validatedData['company_id']);

            if (!$company) {
                throw new Exception('Company not found on branch creation');
            }

            $asset = Account::where('name', 'Assets')->first();
            $accountReceivable = Account::where('name', 'like', '%Receivable%')->first();

            if (!$asset->id) {
                throw new Exception('Asset account not found on branch creation');
            }

            if (!$accountReceivable->id) {
                throw new Exception('Account Receivable not found on branch creation');
            }

            $account = Account::create([
                'name' => $request->name,
                'level' => 3,
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'company_id' => $company->id,
                'root_id' => $asset->id,
                'parent_id' => $accountReceivable->id,
                'branch_id' => $branch->id,
                'reference_id' => $branch->id,
                'code' => 'BRN-' . rand(1000000, 9999999),
            ]);

            if (!$account) {
                throw new Exception('Account creation failed on branch creation');
            }

            logger('Branch created successfully with ID: ' . $branch->id);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Branch created successfully',
                'data' => $branch,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            logger('Branch creation failed with error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Branch creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function edit($id)
    {
        $branch = Branch::with('company')->findOrFail($id);
        $companies = Company::orderBy('name')->get();

        return view('branches.edit', compact('branch', 'companies'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company_id' => 'required|exists:companies,id',
            'gds_office_id' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        $branch = Branch::findOrFail($id);
        $branch->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'company_id' => $request->company_id,
            'gds_office_id' => $request->gds_office_id,
            'address' => $request->address,
        ]);

        return redirect()->route('branches.index')->with('success', 'Branch updated successfully');
    }
}
