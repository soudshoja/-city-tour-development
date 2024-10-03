<?php

// app/Http/Controllers/CompanyController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Invoice;
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

    public function getTransaction()
    {
        $transactions = Invoice::with('agent')->get();
        // $transactions = DB::table('invoice_transaction_view')
        // ->where('agent_id', operator: $agentId)
        // ->get();
    
        if ($transactions->isEmpty()) {
            return response()->json(['message' => 'No transactions found for this agent.'], 404);
        }


        return response()->json([
            'transactions' => $transactions
        ]);
    }

    public function dashboard() 
    {

        $response2 = $this->getTransaction();
    
        $data2 = $response2->getData(true);
        $status = $response2->status();

        if (isset($data2['transactions']) && !empty($data2['transactions'])) {
            // If items is an array, we can count its elements
            $transactions = $data2['transactions']; // Assuming this is an array of items
            // You may want to define counts based on the structure of items
            $unpaidInvoiceCount = count(array_filter($transactions, fn($transaction) => $transaction['status'] === 'unpaid'));
            $paidInvoiceCount = count(array_filter($transactions, fn($transaction) => $transaction['status'] === 'paid'));
            $totalInvoiceCount = count($transactions);
          
            $message = null;
            $status = 'success';
        } else {
            $transactions = [];
            $message = $data2['message'] ?? 'An error occurred';
    
            if ($status === 200) {
                $status = 'info';
            } elseif ($status === 404) {
                $status = 'warning';
            } else {
                $status = 'error';
            }
    
            // Initialize counts to zero if no items are found
            $unpaidInvoiceCount = 0;
            $paidInvoiceCount = 0;
            $totalInvoiceCount = 0;

        }


        return view('companies.index', compact(
            'transactions', 
            'message', 
            'status', 
            'unpaidInvoiceCount',
            'paidInvoiceCount',
            'totalInvoiceCount'
        ));
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
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        return redirect()->route('companies.index')->with('success', 'Company updated successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'nationality' => 'required|string|max:255',
        ]);

        // Create a new user associated with the company
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make('citytour123'),
            'role' => 'company',
        ]);

        // Create new company
        Company::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'code' => $request->code,
            'email' => $request->email,
            'nationality' => $request->nationality,
            'phone' => $request->phone,
            'address' => $request->address,
            'status' => true,
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

        public function exportCsv()
        {
            // Fetch all company data
            $companies = Company::all();
    
            // Create a CSV file in memory
            $csvFileName = 'companies.csv';
            $handle = fopen('php://output', 'w');
    
            // Set headers for the response
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $csvFileName . '"');
    
            // Add CSV header
            fputcsv($handle, ['Company Name', 'Company Code', 'Email', 'Country', 'Contact', 'Address']);
    
            // Add company data to CSV
            foreach ($companies as $company) {
                fputcsv($handle, [
                    $company->name,
                    $company->code,
                    $company->email,
                    $company->nationality,
                    $company->phone,
                    $company->address,
                ]);
            }
    
            fclose($handle);
            exit();
        }

        
}