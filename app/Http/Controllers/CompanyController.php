<?php
// app/Http/Controllers/CompanyController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use App\Models\Task;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\companiesImport;
use Illuminate\Support\Facades\Hash;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        // Handle dynamic per_page value from the request, default to 10
        $perPage = $request->get('per_page', 10);

        // Fetch paginated data with the dynamic per page value
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
        $Company = Company::find($id);
        return view('companies.companiesShow', compact('Company'));
    }

    public function edit($id)
    {
        $Company = Company::find($id);
        $companies = Company::all();
        
        return view('companies.companiesEdit', compact('Company', 'companies'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Update Company
        $Company = Company::find($id);
        $Company->update([
            'name' => $request->name,
            'code' => $request->code,
            'nationality' => $request->nationality
        ]);

        return redirect()->route('companies.index')->with('success', 'Company updated successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'code' => 'required',
            'nationality' => 'required',
        ]);

        // Create a new user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make('citytour123'),
        ]);

        // Create new company
        $Company = Company::create([
            'user_id' => $user->id, 
            'name' => $request->name,
            'code' => $request->code,
            'nationality' => $request->nationality
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
}