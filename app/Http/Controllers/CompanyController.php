<?php

// app/Http/Controllers/CompanyController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\companiesImport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CompanyController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        // Handle dynamic per_page value from the request, default to 10
        $perPage = $request->get('per_page', 10);

        // Check if the user is authorized to view the companies
        if (Gate::denies('viewAny', Company::class)) {
            abort(403);
        }

        // Fetch paginated companies
        $companies = Company::paginate($perPage);

        // Return view with the paginated data
        return view('companies.companiesList', compact('companies'));
    }

    public function new()
    {
        $companies = Company::all();
        return view('companies.companiesNew', compact('companies'));
    }

    public function show($id)
    {
        $company = Company::findOrFail($id);
        return view('companies.companiesShow', compact('company'));
    }

    public function edit($id)
    {
        $company = Company::findOrFail($id);
        $companies = Company::all();
        
        return view('companies.companiesEdit', compact('company', 'companies'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'nationality' => 'required|string|max:255',
        ]);

        // Find the company and update its data
        $company = Company::findOrFail($id);
        $company->update([
            'name' => $request->name,
            'code' => $request->code,
            'nationality' => $request->nationality,
        ]);

        return redirect()->route('companies.index')->with('success', 'Company updated successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'code' => 'required|string|max:255',
            'nationality' => 'required|string|max:255',
        ]);

        // Create a new user associated with the company
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make('citytour123'),
        ]);

        // Create new company
        Company::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'code' => $request->code,
            'nationality' => $request->nationality,
        ]);

        // Redirect with success message
        return redirect()->route('companies.index')->with('success', 'Company registered successfully');
    }

    public function upload()
    {
        $companies = Company::all();
        return view('companies.companiesUpload', compact('companies'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx',
        ]);

        Excel::import(new companiesImport, $request->file('excel_file'));

        return redirect()->back()->with('success', 'Companies imported successfully.');
    }
    public function toggleStatus(Request $request, $companyId)
{
    $company = Company::findOrFail($companyId);

    // Update the status based on the request input
    $company->status = $request->status;
    $company->save();

    return response()->json(['success' => true]);
}

}