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
use Database\Seeders\CoaSeeder;
use Illuminate\Http\Request;

class AdminUsersController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', User::class);

        if(auth()->user()->role_id == Role::ADMIN) {
            $users = User::with('roles')->get();
        } else if(auth()->user()->role_id == Role::COMPANY) {

            $branches = Branch::where('company_id', auth()->user()->company->id)->pluck('id');
            $branchUsers = User::with('roles')
                ->whereHas('branch', function($query) use ($branches) {
                    $query->whereIn('id', $branches);
                })
                ->get();

            $agents = Agent::whereIn('branch_id', $branches)->get();
            $agentUsers = User::with('roles')
                ->whereHas('agent', function($query) use ($agents) {
                    $query->whereIn('id', $agents->pluck('id'));
                })
                ->get();

            $users = $branchUsers->merge($agentUsers)->unique('id');

        } else {
            abort(403, 'Unauthorized action.');
        }

        $usersCount = $users->count();
        
        return view('users.index', compact('users', 'usersCount'));
    }

    public function editRole($userId)
    {
        if(auth()->user()->role_id != Role::COMPANY){
            abort(403, 'Unauthorized action.');
        }
        $user = User::find($userId);
        
        if($user->role_id == Role::ADMIN) {
            abort(403, 'Cannot change role of Admin users.');
        }

        $roles = Role::where('company_id', auth()->user()->company->id)->get();

        $userRole = null;
        $phone = null;

        if($user->role_id == Role::COMPANY && $user->company) {
            $userRole = 'company';
            $phone = $user->company->phone;
        } elseif($user->role_id == Role::BRANCH && $user->branch) {
            $userRole = 'branch';
            $phone = $user->branch->phone;
        } elseif($user->role_id == Role::AGENT && $user->agent) {
            $userRole = 'agent';
            $phone = $user->agent->phone_number;
        } elseif($user->role_id == Role::ACCOUNTANT && $user->company) {
            $userRole = 'accountant';
        } elseif($user->role_id == Role::CLIENT && $user->client) {
            $userRole = 'client';
        }

        return view('users.edit', compact(
            'user',
            'roles',
            'userRole',
            'phone'
        ));
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
            'user_id' => 'required|integer|exists:users,id',
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        $user = User::find($request->user_id);
        $role = Role::where('id', $request->role_id)
            ->where('company_id', $request->company_id)
            ->first();

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
            $branches = collect();
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

        $companyController = new CompanyController();

        $response = $companyController->store($request);

        $response = json_decode($response->getContent(), true);

        if ($response['status'] !== 'success') { 
            return redirect()->route('companies.index')->with('error', 'Error creating company.');
        }

        $company = Company::find($response['data']['id']);

        if (!$company) {
            logger('Company not found after creation.');
            return redirect()->route('companies.index')->with('error', 'Company not found.');
        }

        try {
            CoaSeeder::run($company->id);
        } catch (Exception $e) {
            Log::error('Error seeding COA:', ['error' => $e->getMessage()]);
            return redirect()->route('companies.index')->with('error', 'Error creating COA accounts.');
        }
        
        $branchName = $company->name . ' - Main Branch';
        $branchEmail = $company->email;

        $branchController = new BranchController();

        $request = new Request([
            'name' => $branchName,
            'email' => $branchEmail,
            'phone' => $company->phone,
            'address' => $company->address,
            'user_id' => $company->user_id,
            'company_id' => $company->id,
        ]);

        $branchResponse = $branchController->store($request);

        $branchResponse = json_decode($branchResponse->getContent(), true);


        return redirect()->route('companies.index')->with($branchResponse['status'], $branchResponse['message']);
        
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

    public function updateInfo(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'source_role' => 'required|in:company,branch,agent',
            'info-new-password' => 'nullable|string|min:6', // optional password field
        ]);

        $roleType = $request->input('source_role');

        // Prepare fields to update
        $fields = [
            'name' => $request->name,
            'email' => $request->email,
            $roleType === 'agent' ? 'phone_number' : 'phone' => $request->phone,
        ];

        // Update related model (company, branch, or agent)
        if ($roleType === 'company' && $user->company) {
            $user->company->update($fields);
        } elseif ($roleType === 'branch' && $user->branch) {
            $user->branch->update($fields);
        } elseif ($roleType === 'agent' && $user->agent) {
            $user->agent->update($fields);
        }

        // Update password if provided
        if (!empty($request->input('info-new-password'))) {
            $user->password = Hash::make($request->input('info-new-password'));
            $user->save();
        }

        return redirect()->back()->with('success', 'Information updated successfully.');
    }


}
