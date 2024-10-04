<?php

// app/Http/Controllers/CompanyController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
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
        // Retrieve the company for the authenticated user with agents
        $company = Company::where('user_id', Auth::id())->with('agents')->first();
        // Get all agents under the company
        $agents = $company->agents;
        $agentsCount = $company->agents->count();
        // Count total tasks, pending tasks, and completed tasks for all agents
        $totalTaskCount = $company->agents->sum(function ($agent) {
            return $agent->tasks()->count(); // Count all tasks for each agent
        });
    
        $pendingTaskCount = $company->agents->sum(function ($agent) {
            return $agent->tasks()->where('status', 'pending')->count(); // Count pending tasks for each agent
        });
    
        $completedTaskCount = $company->agents->sum(function ($agent) {
            return $agent->tasks()->where('status', 'completed')->count(); // Count completed tasks for each agent
        });
    
        // Count total invoices, paid invoices, and unpaid invoices for all agents
        $totalInvoices = $company->agents->sum(function ($agent) {
            return $agent->invoices()->count(); // Count all invoices for each agent
        });

        $totalInvoiceAmount = $company->agents->sum(function ($agent) {
            return $agent->invoices()->sum('amount'); // Sum the 'amount' field for all invoices of each agent
        });
    
        $paidInvoices = $company->agents->sum(function ($agent) {
            return $agent->invoices()->where('status', 'paid')->count(); // Count paid invoices for each agent
        });
    
        $unpaidInvoices = $company->agents->sum(function ($agent) {
            return $agent->invoices()->where('status', 'unpaid')->count(); // Count unpaid invoices for each agent
        });
    
        // Get clients under those agents
        $clients = Client::whereIn('agent_id', $agents->pluck('id'))->get();
        // Count clients associated with all agents
        $clientsCount = Client::whereIn('agent_id', $company->agents->pluck('id'))->count();

        // Prepare clients with task count and invoice count
        $clientsWithDetails = $clients->map(function ($client) {
        // Count the number of tasks related to this client
        $taskCount = Task::where('client_id', $client->id)->count();

        // Count the total number of invoices related to this client
        $totalInvoices = Invoice::where('client_id', $client->id)->count();
          // Count the unpaid invoices for this client
        $unpaidInvoices = Invoice::where('client_id', $client->id)
        ->where('status', 'unpaid')
        ->count();

        return [
            'name' => $client->name,
            'taskCount' => $taskCount,
            'totalInvoices' => $totalInvoices,
            'unpaidInvoices' => $unpaidInvoices,
        ];
    });

        // Prepare agents with task count and invoice count
        $agentsWithDetails = $agents->map(function ($agent) {
            // Count the number of tasks related to this client
            $taskCount = Task::where('agent_id', $agent->id)->count();
            $pendingTasks = Task::where('agent_id', $agent->id)
            ->where('status', 'pending')
            ->count();
            // Count the total number of invoices related to this client
            $totalInvoices = Invoice::where('agent_id', $agent->id)->count();
    
            return [
                'name' => $agent->name,
                'taskCount' => $taskCount,
                'totalInvoices' => $totalInvoices,
                'pendingTasks' => $pendingTasks,
            ];
        });


        // Prepare the data array
        $dashboardData = [
            'totalTasks' => $totalTaskCount,
            'pendingTasks' => $pendingTaskCount,
            'completedTasks' => $completedTaskCount,
            'totalInvoices' => $totalInvoices,
            'totalInvoiceAmount' => $totalInvoiceAmount,
            'paidInvoices' => $paidInvoices,
            'unpaidInvoices' => $unpaidInvoices,
            'clientsCount'=> $clientsCount,
            'agentsCount' => $agentsCount,
            'agents' => $agentsWithDetails,
            'clients' => $clientsWithDetails,
        ];
        return view('companies.index', compact(            'company', 'dashboardData'));
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