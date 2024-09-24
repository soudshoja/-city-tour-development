<?php
// app/Http/Controllers/CompanyController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use App\Models\Task;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\companiesImport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CompanyController extends Controller
{

    use AuthorizesRequests;

    public function index()
    {

        if (Gate::denies('viewAny', Company::class)) {
            abort(403);
        }

        $companies = Company::all();

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


        // Create a new Company associated with the user
        $Company = new Company([
            'code' => $request->code,
            'name' => $request->name,
            'nationality' => $request->nationality
        ]);
        $Company->save();

        return redirect()->route('companies.index')->with('success', 'Company updated successfully');
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Create new Company
        $Company = Company::create([
            'name' => $request->name,
            'code' => $request->code,
            'nationality' => $request->nationality
        ]);

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

        return redirect()->back()->with('success', 'companies imported successfully.');
    }
}
