<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Country;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentType;
use App\Models\Role;
use App\Models\User;
use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminUsersController extends Controller
{
    public function index()
    {
        $usersCount = User::all()->count();
        $users = User::with('roles')->get();
        
        return view('users.index', compact('users', 'usersCount'));
    }

    public function editRole($roleId)
    {
        $user = User::find($roleId);
        $roles = Role::all();

        return view('users.edit', compact('user', 'roles'));
    }

    public function storeRole(Request $request)
    {
        $validatedData = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::find($validatedData['user_id']);
        $role = Role::find($validatedData['role_id']);
        try {
            $user->syncRoles($role);
        } catch (Exception $e) {
            logger($e->getMessage());
            return redirect()->route('users.index')->with('error', 'Role assignment failed.');
        }

        return redirect()->route('users.index')->with('success', 'Role assigned successfully.');
    }

    public function newCompany()
    {
        $countries = Country::all(); // Fetch all countries from the `countries` table
        return view('admin.addnewCompany', compact('countries'));
    }

    public function create()
    {
        $user = auth()->user();
        if ($user->role_id == Role::ADMIN) {
            $branches = Branch::all();
        } elseif ($user->role_id == Role::COMPANY) {
            $branches = Branch::where('company_id', auth()->user()->company->id)->get();
        } else {
            return redirect()->route('home')->with('error', 'You are not authorized to access this page.');
        }

        $branches_id = $branches->pluck('id');

        $agents = Agent::whereIn('branch_id', $branches_id)->get();
        

        $agentTypes = AgentType::all(); 
        $countries = Country::all(); 

        return view('users.create', compact('agents', 'branches', 'agentTypes', 'countries'));
    }

    public function store(Request $request)
    {
        Log::info('Store function called with request data:', $request->all());

        // Validate the request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:15',
            'code' => 'required|string|max:100|unique:companies,code',
            'country_id' => 'required|integer|exists:countries,id',
            'address' => 'nullable|string|max:255',
            'status' => 'required|in:0,1',
        ]);

        Log::info('Validation passed:', $validatedData);

        // Create the user (Company Owner)
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'role_id' => Role::COMPANY,
            'remember_token' => Str::random(10),
            'first_login' => 1,
        ]);

        Log::info('Company owner user created:', ['user_id' => $user->id]);

        // Create the company
        $company = Company::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'code' => $validatedData['code'],
            'country_id' => $validatedData['country_id'],
            'address' => $validatedData['address'],
            'phone' => $validatedData['phone'] ?? null,
            'user_id' => $user->id,
            'status' => $validatedData['status'],
        ]);

        Log::info('Company created:', ['company_id' => $company->id]);


         // Insert to accounts for coa
         $accounts = [
            ['name' => 'Assets', 'level' => 1, 'parent' => null],
            ['name' => 'Liabilities', 'level' => 1, 'parent' => null],
            ['name' => 'Income', 'level' => 1, 'parent' => null],
            ['name' => 'Expenses', 'level' => 1, 'parent' => null],

            ['name' => 'Current Assets', 'level' => 2, 'parent' => 'Assets'],
            ['name' => 'Fixed Assets', 'level' => 2, 'parent' => 'Assets'],
            ['name' => 'Investments', 'level' => 2, 'parent' => 'Assets'],
            ['name' => 'Deposits', 'level' => 2, 'parent' => 'Assets'],

            ['name' => 'Current Liabilities', 'level' => 2, 'parent' => 'Liabilities'],
            ['name' => 'Long-Term Liabilities', 'level' => 2, 'parent' => 'Liabilities'],
            ['name' => 'Provisions', 'level' => 2, 'parent' => 'Liabilities'],

            ['name' => 'Operating Income', 'level' => 2, 'parent' => 'Income'],
            ['name' => 'Non-Operating Income', 'level' => 2, 'parent' => 'Income'],

            ['name' => 'Fixed Expenses', 'level' => 2, 'parent' => 'Expenses'],
            ['name' => 'Variable Expenses', 'level' => 2, 'parent' => 'Expenses'],

            ['name' => 'Cash', 'level' => 3, 'parent' => 'Current Assets'],
            ['name' => 'Accounts Receivable', 'level' => 3, 'parent' => 'Current Assets'],
            ['name' => 'Inventory', 'level' => 3, 'parent' => 'Current Assets'],

            ['name' => 'Property, Plant, and Equipment', 'level' => 3, 'parent' => 'Fixed Assets'],
            ['name' => 'Investments in Subsidiaries', 'level' => 3, 'parent' => 'Investments'],
            ['name' => 'Long-Term Deposits', 'level' => 3, 'parent' => 'Investments'],

            ['name' => 'Accounts Payable', 'level' => 3, 'parent' => 'Current Liabilities'],
            ['name' => 'Short-Term Debt', 'level' => 3, 'parent' => 'Current Liabilities'],

            ['name' => 'Long-Term Debt', 'level' => 3, 'parent' => 'Long-Term Liabilities'],

            ['name' => 'Income On Sales', 'level' => 3, 'parent' => 'Operating Income'],

            ['name' => 'Salary Expense', 'level' => 3, 'parent' => 'Fixed Expenses'],
            ['name' => 'Rent Expense', 'level' => 3, 'parent' => 'Fixed Expenses'],
            ['name' => 'Depreciation Expense', 'level' => 3, 'parent' => 'Fixed Expenses'],

            ['name' => 'Business Trip Expense', 'level' => 3, 'parent' => 'Variable Expenses'],
            ['name' => 'Agent Sales Commission', 'level' => 3, 'parent' => 'Variable Expenses'],
            ['name' => 'Sponsorship Fee', 'level' => 3, 'parent' => 'Variable Expenses'],
            ['name' => 'Legal & Professional Fees', 'level' => 3, 'parent' => 'Variable Expenses'],
            ['name' => 'Utilities Expense', 'level' => 3, 'parent' => 'Variable Expenses'],
        ];

        // Store newly inserted IDs
        $idMapping = [];

        // Insert accounts dynamically
        foreach ($accounts as $account) {
            // Determine the parent_id dynamically
            $parentId = isset($account['parent']) && isset($idMapping[$account['parent']]) 
            ? $idMapping[$account['parent']]->id 
            : null;

            // Insert account
            $newId = Account::create([
                'name' => $account['name'],
                'level' => $account['level'],
                'actual_balance' => 0,
                'budget_balance' => 0,
                'variance' => 0,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'company_id' => $company->id,
                'parent_id' => $parentId,
            ]);

            // Store new ID for future parent_id references
            $idMapping[$account['name']] = $newId;
        }


        // Create a default branch for the company
        $defaultBranch = Branch::create([
            'name' => $company->name . ' - Main Branch',
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        Log::info('Default branch created:', ['branch_id' => $defaultBranch->id]);

        // Create a default agent for the company
        $defaultAgentUser = User::create([
            'name' => 'Default Agent',
            'email' => 'agent_' . $company->code . '@example.com',
            'password' => Hash::make('password123'),
            'role_id' => 3,
            'remember_token' => Str::random(10),
            'first_login' => 1,
        ]);

        Log::info('Default agent user created:', ['user_id' => $defaultAgentUser->id]);

        // Agent::create([
        //     'name' => 'Default Agent',
        //     'email' => $defaultAgentUser->email,
        //     'phone_number' => null,
        //     'type_id' => 1,
        //     'branch_id' => $defaultBranch->id,
        //     'user_id' => $defaultAgentUser->id,
        // ]);

        Log::info('Default agent created.');

        return redirect()->route('companies.index')->with('success', 'Company registered successfully.');
    }

    public function ShowCompanies(Request $request)
    {
        // Retrieve all companies with their related nationality
        $companies = Company::with('nationality')->get(); // Eager load the nationality relationship

        // Retrieve all companies and their count
        $companiesCount = Company::count();

        // Return view with the companies data
        return view('admin.companiesList', compact('companies', 'companiesCount'));
    }
}
