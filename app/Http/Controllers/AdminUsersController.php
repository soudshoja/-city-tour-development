<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Country;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use App\Models\Branch;
use App\Models\Agent;
use App\Models\Role;

use App\Models\User;
use Illuminate\Http\Request;

class AdminUsersController extends Controller
{
    public function index()
    {
        $NumberOfAdmins = User::where('role', 'admin')->count();
        $adminUsers = User::where('role', 'admin')->get();

        return view('adminsList', compact('adminUsers', 'NumberOfAdmins'));
    }




    public function newCompany()
    {
        $countries = Country::all(); // Fetch all countries from the `countries` table
        return view('admin.addnewCompany', compact('countries'));
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
            'nationality_id' => 'required|integer|exists:countries,id',
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
            'nationality_id' => $validatedData['nationality_id'],
            'address' => $validatedData['address'],
            'phone' => $validatedData['phone'] ?? null,
            'user_id' => $user->id,
            'status' => $validatedData['status'],
        ]);

        Log::info('Company created:', ['company_id' => $company->id]);

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

        Agent::create([
            'name' => 'Default Agent',
            'email' => $defaultAgentUser->email,
            'phone_number' => null,
            'type_id' => 1,
            'branch_id' => $defaultBranch->id,
            'company_id' => $company->id,
            'user_id' => $defaultAgentUser->id,
        ]);

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
