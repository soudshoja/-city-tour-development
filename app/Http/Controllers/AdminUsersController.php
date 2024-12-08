<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Country;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;

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

    public function ShowCompanies(Request $request)
    {
        // Handle dynamic per_page value from the request, default to 10
        $perPage = $request->get('per_page', 10);

        // Check if the user is authorized to view the companies
        if (Gate::denies('viewAny', Company::class)) {
            abort(403);
        }

        // Fetch paginated companies
        $companies = Company::paginate($perPage);

        $companiesCount = Company::count();
        // Return view with the paginated data
        return view('companies.companiesList', compact('companies', 'companiesCount'));
    }



    public function new()
    {
        $companies = Company::all();
        $countries = Country::all(); // Fetch all countries from the `countries` table
        return view('admin.addnewCompany', compact('companies', 'countries'));
    }

    public function store(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:15',
            'code' => 'required|string|max:100|unique:companies,code',
            'nationality_id' => 'required|integer|exists:countries,id', // Validate that the ID exists in the countries table
            'address' => 'nullable|string|max:255',
            'status' => 'required|in:0,1', // Validate that the status is either 0 or 1
        ]);

        // Create the user
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']), // Hash the password
            'role_id' => 2, // Assuming 2 is the role ID for "Company"
            'remember_token' => Str::random(10),
            'first_login' => 1,
        ]);

        // Create the company
        Company::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'code' => $validatedData['code'],
            'nationality_id' => $validatedData['nationality_id'],
            'address' => $validatedData['address'],
            'phone' => $validatedData['phone'] ?? null,
            'user_id' => $user->id,
            'status' => $validatedData['status'], // Use the validated status value from the request
        ]);

        // Redirect with success message
        return redirect()->route('admin.companiesList')->with('success', 'Company registered successfully');
    }
}
