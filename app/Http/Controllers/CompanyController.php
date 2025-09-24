<?php

// app/Http/Controllers/CompanyController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Task;
use App\Models\Agent;
use App\Models\User;
use App\Models\Branch;
use App\Models\Role;
use App\Models\Country;
use App\Models\AgentType;
use App\Models\Accountant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use App\Imports\companiesImport;
use App\Models\Account;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;



use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CompanyController extends Controller
{
    use AuthorizesRequests;

    public function index(Company $company)
    {
        Gate::authorize('view', $company);

        $companies = Company::all();
        return view('companies.list', compact('companies'));
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
        $company = Company::where('user_id', Auth::id())->with('branches.agents.clients.invoices.invoiceDetails.tasks')->first();
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

        $totalPaidAmount = $company->agents->sum(function ($agent) {
            return $agent->invoices()->where('status', 'paid')->sum('amount'); // Sum the 'amount' field for all invoices of each agent
        });
        $totalPaidAmountChart = Invoice::where('status', 'paid')
            ->selectRaw('SUM(amount) as total, DATE_FORMAT(created_at, "%Y-%m") as month')
            ->groupBy('month')
            ->get();
        $totalUnpaidAmountChart = Invoice::where('status', 'unpaid')
            ->selectRaw('SUM(amount) as total, DATE_FORMAT(created_at, "%Y-%m") as month')
            ->groupBy('month')
            ->get();

        $totalUnpaidAmount = $company->agents->sum(function ($agent) {
            return $agent->invoices()->where('status', 'unpaid')->sum('amount'); // Sum the 'amount' field for all invoices of each agent
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
                'name' => $client->full_name,
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
            'hello' => 'hello',
            'totalTasks' => $totalTaskCount,
            'totalBranches' => $company->branches->count(),
            'pendingTasks' => $company->branches->count(),
            'completedTasks' => $completedTaskCount,
            'totalInvoices' => $totalInvoices,
            'totalInvoiceAmount' => $totalInvoiceAmount,
            'totalPaidAmount' => $totalPaidAmount,
            'totalUnpaidAmount' => $totalUnpaidAmount,
            'totalPaidAmountChart' => $totalPaidAmountChart,
            'totalUnpaidAmountChart' => $totalUnpaidAmountChart,
            'paidInvoices' => $paidInvoices,
            'unpaidInvoices' => $unpaidInvoices,
            'clientsCount' => $clientsCount,
            'agentsCount' => $agentsCount,
            'agents' => $agentsWithDetails,
            'clients' => $clientsWithDetails,
        ];

        return view('companies.index', compact('company', 'dashboardData'));
    }

    public function show($id)
    {
        // Fetch the specific company with its agents, tasks, clients, invoices, and items    
        $companies = Company::all();
        $company = Company::with([
            'agents.tasks.client',
            'agents.invoices',
            'agents.tasks'
        ])->findOrFail($id);


        // Return the view, passing the specific company to it
        return view('companies.show', compact('company', 'companies'));
    }

    public function edit($id)
    {
        $company = Company::findOrFail($id);
        $companies = Company::all();


        return view('companies.companiesEdit', compact('company', 'companies'));
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => Role::COMPANY,
                'remember_token' => Str::random(10),
                'first_login' => 1,
            ]);

            if (!$user) {
                Log::error('Error creating company user:', ['user_id' => $user->id]);
                throw new Exception('Error creating company user.');
            }

            Log::info('Company owner user created:', ['user_id' => $user->id]);

            // Create the company
            $company = Company::create([
                'name' => $request->name,
                'email' => $request->email,
                'code' => $request->code,
                'country_id' => $request->country_id,
                'address' => $request->address,
                'phone' => $request->phone,
                'user_id' => $user->id,
                'status' => $request->status,
            ]);

            $user->assignRole('company');

            Log::info('Company created:', ['company_id' => $company->id]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Company created successfully.',
                'data' => $company,
            ], 201);

        } catch (Exception $e) {
            Log::error('Error creating company:', ['error' => $e->getMessage()]);

            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating company: ' . $e->getMessage(),
            ], 500);
        }
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


    public function upload()
    {
        $companies = Company::all();
        return view('companies.companiesUpload', compact('companies'));
    }

    public function import(Request $request)
    {
        return redirect()->back()->with('error', 'Module not available yet.');
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

    public function createBranch(Request $request)
    {
        $user = Auth::user();

        if ($user->company == null) {
            return back()->withErrors(['error' => 'No company found for the logged-in user.']);
        }

        $company = $user->company;

        $userController = new UserController();

        $userCreationResponse = $userController->store($request);

        if ($userCreationResponse->getStatusCode() !== 201) {
            return back()->withErrors(['error' => 'Failed to create user.']);
        }

        $userCreationResponseData = json_decode($userCreationResponse->getContent());
        
        $userBranchId = $userCreationResponseData->data->id;
        $user = User::find($userBranchId);

        $request->merge([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $branchController = new BranchController();

        $branchCreationResponse = $branchController->store($request);

        if($branchCreationResponse->getStatusCode() !== 201) {
            $user->delete();
            return back()->withErrors(['error' => 'Failed to create branch.']);
        }

        return redirect()->back()->with('success', 'Branch created successfully.');
    }

    public function createAccountant(Request $request)
    {
        $auth = Auth()->user();
        $companyId = $auth->branch->company_id;
        $branchId = $auth->branch->id;
        $company = Company::findOrFail($companyId);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'dial_code' => 'required|string',
            'phone' => 'required|string|max:15',
            'password' => 'required|string|max:100',
        ]);

        $role = Role::where('name', 'accountant')
                ->where('company_id', $companyId)
                ->first();

            if (!$role) {

                $role = Role::create([
                    'name' => 'accountant',
                    'description' => 'Accountant role for company ' . $company->name,
                    'company_id' => $companyId,
                ]);
            }

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => $validatedData['password'], 
            'role_id' => Role::ACCOUNTANT,
            'remember_token' => Str::random(10),
            'first_login' => 1,
        ])->assignRole($role);
        
        $accountant = $user->accountant()->create([
            'name'         => $validatedData['name'],
            'email'        => $validatedData['email'],
            'country_code' => $validatedData['dial_code'],
            'phone_number' => $validatedData['phone'],
            'branch_id'    => $branchId,
        ]);

        Log::info('Accountant role has succesfully created for company ' . $company->name, [
            'user' => $user,
            'accountant' => $accountant,
        ]);
        return redirect()->back()->with('success', 'Accountant created successfully.');
    }

    public function deleteAgentType(Request $request)
    {
        // Validate the incoming request
        $validated = $request->validate([
            'agent_type_id' => 'required|exists:agent_type,id', // Ensure the ID exists in the agent_types table
        ]);

        // Retrieve the agent type by ID
        $agentType = AgentType::find($validated['agent_type_id']);

        if (!$agentType) {
            // If no agent type is found, return an error message
            return redirect()->back()->with('error', 'Agent type not found.');
        }

        // Check if any agents are associated with this type
        if ($agentType->agents()->exists()) {
            // If agents are associated with this agent type, prevent deletion and return error message
            return redirect()->back()->with('error', 'Agent type is associated with agents and cannot be deleted.');
        }

        // Proceed with deletion
        $agentType->delete();

        // Redirect back with a success message
        return redirect()->back()->with('success', 'Agent type deleted successfully.');
    }

    public function destroy($id)
    {
        Gate::authorize('delete', Company::class);

        $company = Company::findOrFail($id);

        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Company deleted successfully.');
    }
}
