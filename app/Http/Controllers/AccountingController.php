<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Agent;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Account;
use App\Models\Supplier;
use App\Models\GeneralLedger;
use App\Models\Payment;
use App\Models\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LedgerExport;

class AccountingController extends Controller
{
    public function index()
    {
        $user = Auth::user();
    
        // Retrieve the company associated with the user, including the necessary relationships
        $company = Company::where('user_id', $user->id)->with([
            'branches.agents.clients.invoices.invoiceDetails.generalLedgers' => function ($query) {
                // You can apply any specific queries here, if needed
            }
        ])->first();
    
        $suppliers = Supplier::all();
        $accounts = Account::where('company_id', $company->id)
                   ->select(['id', 'name'])
                   ->get();

                //    $accountsArray = $accounts->map(function ($account) {
                //     return [
                //         'id' => $account->id,
                //         'name' => $account->name,
                //     ];
                // })->toArray(); // Convert the collection to an array
                $accountsArray = []; 
                $company->load([
                    'branches.agents.clients.invoices.invoiceDetails.task.supplier',
                ]);
                
        
                foreach ($accounts as $account) {
                    if ($account->name === 'Accounts Receivable') {
                        foreach ($company->branches as $branch) { // Loop through branches
                            foreach ($branch->agents as $agent) { // Loop through agents in each branch
                                foreach ($agent->clients as $client) {            
                                $accountsArray[] = [
                                    'id' => 'account-' . $account->id . ':client-' . $client->id,
                                    'name' => 'Client: ' . $client->name,
                                ];
                              }
                            }
                        }
                    } elseif ($account->name === 'Accounts Payable') { 
                                    foreach ( $suppliers as $supplier) { // Loop through invoice details
                                            $accountsArray[] = [
                                                'id' => 'account-' . $account->id . ':supplier-' . $supplier->id, // Ensure unique key
                                                'name' => 'Supplier: ' . $supplier->name, // Use supplier's name
                                            ];
                                    }
                    } elseif ($account->name === 'Income') {
                        foreach ($company->branches as $branch) { // Loop through branches
                            foreach ($branch->agents as $agent) { // Loop through agents in each branch
                                $accountsArray[] = [
                                    'id' => 'account-' . $account->id . ':agent-' . $agent->id, // Ensure unique key
                                    'name' => 'Agent: ' . $agent->name, // Access the agent's name or other properties
                                ];
                            }
                        }
                    } else {
                        // For other account names, you can keep them simple
                        $accountsArray[] = [
                            'id' => 'account-' . $account->id, // Ensure unique key
                            'name' => $account->name,
                        ];
                    }
                }


        // Prepare data for generalLedgers (to replace transactions)
        $generalLedgers = [];
        $groupedGeneralLedgers = [];

        foreach ($company->branches as $branch) {
            foreach ($branch->agents as $agent) {
                foreach ($agent->clients as $client) {
                    foreach ($client->invoices as $invoice) {
                        foreach ($invoice->invoiceDetails as $invoiceDetail) {
                            // Retrieve the task associated with this invoiceDetail
                            $task = $invoiceDetail->task; // assuming each invoiceDetail has a related task
                            $taskName = $task ? $task->reference .'-'. $task->additional_info .'-'. $task->venue .'-'. $task->type : null;
                            foreach ($invoiceDetail->generalLedgers as $generalLedger) {
                                $groupedGeneralLedgers[$taskName][]  = [
                                    'generalLedger_id' => $generalLedger->id,
                                    'generalLedger_name' => $generalLedger->name,
                                    'client_name' => $client->name,
                                    'supplier_name' => $task->supplier->name,
                                    'credit' => $generalLedger->credit,
                                    'debit' => $generalLedger->debit,
                                    'balance' => $generalLedger->balance,
                                    'transaction_date' => $generalLedger->created_at,
                                    'description' => $generalLedger->description,
                                    'branch_name' => $branch->name,
                                    'agent_name' => $agent->name,
                                    'type' => $generalLedger->type,
                                    'invoice_number' => $invoice->invoice_number,
                                    'status' => $invoice->status,
                                    'task_name' => $taskName,
                                  
                                ];
                            }
                        }
                    }
                }
            }
        }
        
    
        // Pass the data to the view
        return view('accounting.index', [
            'groupedGeneralLedgers' => $groupedGeneralLedgers,
            'company' => $company,
            'accounts' => $accountsArray,
            'branches' => $company->branches, 
            'generalLedgers' => $generalLedgers, // To display in the table
        ]);
    }
    

    public function filterLedgers(Request $request)
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $accountIdInput = $request->input('account');
        $branchId = $request->input('branch');
    
        // Parse the account ID format
        $parsedAccount = [];
        if (preg_match('/^account-(\d+)(?::(client|supplier|agent)-(\d+))?$/', $accountIdInput, $matches)) {
            $parsedAccount['account_id'] = $matches[1];
            $parsedAccount['related_type'] = $matches[2] ?? null;
            $parsedAccount['related_id'] = $matches[3] ?? null;
        }
    
        if (empty($parsedAccount)) {
            return response()->json(['error' => 'Invalid account ID format'], 400);
        }
    
        // Build the query with conditional filters
        $ledgersQuery = GeneralLedger::query()
            ->when($fromDate, fn($query) => $query->where('transaction_date', '>=', $fromDate))
            ->when($toDate, fn($query) => $query->where('transaction_date', '<=', $toDate))
            ->when($parsedAccount['account_id'], fn($query) => $query->where('account_id', $parsedAccount['account_id']))
            ->when($branchId, callback: fn($query) => $query->where('branch_id', $branchId));
    
        // Add conditions for related entities
        if ($parsedAccount['related_type'] && $parsedAccount['related_id']) {
            switch ($parsedAccount['related_type']) {
                case 'client':
                    $ledgersQuery->where('type_reference_id', $parsedAccount['related_id']);
                    break;
                case 'supplier':
                    $ledgersQuery->where('type_reference_id', $parsedAccount['related_id']);
                    break;
                case 'agent':
                    $ledgersQuery->where('type_reference_id', $parsedAccount['related_id']);
                    break;
            }
        }
    
        $ledgers = $ledgersQuery->get();
    
        // Map results with additional context if necessary
        $result = $ledgers->map(fn($ledger) => [
            'invoice_number' => $ledger->invoice ? $ledger->invoice->invoice_number : null,
            'transaction_date' => $ledger->transaction_date,
            'description' => $ledger->description,
            'agent_name' => $ledger->invoice->agent->name,
            'branch_name' => $ledger->branch->name,
            'generalLedger_name' => $ledger->name ?? null,
            'debit' => $ledger->debit,
            'credit' => $ledger->credit,
        ]);
    
        return response()->json($result);
    }


    public function exportExcel(Request $request)
    {   
            // Extract ledgers and totals from the request
            $ledgers = $request->input('ledgers');
            $totalDebit = $request->input('total_debit');
            $totalCredit = $request->input('total_credit');

            // Export the data along with totals to Excel
            return Excel::download(new LedgerExport($ledgers, $totalDebit, $totalCredit), 'GeneralLedgerReport.xlsx');

    }


    public function showCompanySummary()
    {
        $user = Auth::user();
    
        // Retrieve the company associated with the user and load its branches with agents, clients, invoices, and general ledgers
        $company = Company::where('user_id', $user->id)
            ->with([
                'branches.agents.clients.invoices.transactions' // Eager load everything in one go
            ])
            ->first();

            $accounts = Account::all(['id', 'name']);

            $generalLedgers = GeneralLedger::where('company_id', $company->id)->get();
        // Process summary for branches, agents, clients, and invoices
        $companySummary = $company->branches->map(function ($branch) {
            $branch->total_credits = 0;
            $branch->total_debits = 0;
            $branch->balance = 0;
    
            // Iterate over agents and clients to calculate totals
            $branch->agents->each(function ($agent) use ($branch) {
                $agent->total_credits = 0;
                $agent->total_debits = 0;
                $agent->balance = 0;
    
                // Iterate over clients to calculate totals
                $agent->clients->each(function ($client) use ($agent) {
                    $client->total_credits = 0;
                    $client->total_debits = 0;
                    $client->balance = 0;
    
                    // Iterate over invoices to calculate totals
                    $client->invoices->each(function ($invoice) use ($client) {
                        $invoice->total_credits = $invoice->transactions->where('transaction_type', 'credit')->sum('amount');
                        $invoice->total_debits =  $invoice->transactions->where('transaction_type', 'debit')->sum('amount');
                        $invoice->balance = $invoice->total_credits - $invoice->total_debits;
                       
                        $client->total_credits += $invoice->total_credits;
                        $client->total_debits += $invoice->total_debits;
                        $client->balance += $invoice->balance;
                    });
    
                    $agent->total_credits += $client->total_credits;
                    $agent->total_debits += $client->total_debits;
                    $agent->balance += $client->balance;
                });
    
                $branch->total_credits += $agent->total_credits;
                $branch->total_debits += $agent->total_debits;
                $branch->balance += $agent->balance;
            });
    
            return $branch;
        });
    
        return view('accounting.summary', compact('company', 'accounts', 'generalLedgers', 'companySummary'));
    }
    
    
    public function getAccountsByCompanyReceivable(Request $request)
    {
        $accounts = Account::where('company_id', $request->company_id)
        ->whereIn('level', [4])
        ->where(function ($query) {
            
            $query->whereHas('parent', function ($query) {
                $query->where('level', 1)
                      ->whereIn('name', ['Assets', 'Income']);
            })
            
            ->orWhereHas('parent.parent', function ($query) {
                $query->where('level', 1)
                      ->whereIn('name', ['Assets', 'Income']);
            })
            
            ->orWhereHas('parent.parent.parent', function ($query) {
                $query->where('level', 1)
                      ->whereIn('name', ['Assets', 'Income']);
            });
        })
        ->orderBy('level') // Order by level in ascending order
        ->get();    
    
        return response()->json(['accounts' => $accounts]);
    }


    public function getAccountsByCompanyPayable(Request $request)
    {
        $accounts = Account::where('company_id', $request->company_id)
        ->whereIn('level', [4])
        ->where(function ($query) {
            
            $query->whereHas('parent', function ($query) {
                $query->where('level', 1)
                      ->whereIn('name', ['Liabilities', 'Expenses']);
            })
            
            ->orWhereHas('parent.parent', function ($query) {
                $query->where('level', 1)
                      ->whereIn('name', ['Liabilities', 'Expenses']);
            })
            
            ->orWhereHas('parent.parent.parent', function ($query) {
                $query->where('level', 1)
                      ->whereIn('name', ['Liabilities', 'Expenses']);
            });
        })
        ->orderBy('level') // Order by level in ascending order
        ->get();    
    
        return response()->json(['accounts' => $accounts]);
    }


    public function getBranchByCompany(Request $request)
    {
        $branches = Branch::where('company_id', $request->company_id)->get(); 

        if ($branches->isEmpty()) {
            return response()->json(['message' => 'No branches found for this company'], 404);
        }

        return response()->json(['branches' => $branches]);
    }

    public function getAgentByBranchCompany(Request $request)
    {
        $agents = Agent::where('company_id', $request->company_id)
                       ->where('branch_id', $request->branch_id)
                       ->get(); 
    
        if ($agents->isEmpty()) {
            return response()->json(['message' => 'No agents found for this branch and company'], 404);
        }
    
        return response()->json(['agents' => $agents]);
    }

    public function getSupplierByCompany(Request $request)
    {
        // Get all parent account IDs where the name contains "Payable"
        $parentIds = Account::where('name', 'LIKE', '%Payable%')->pluck('id');

        // Retrieve suppliers linked to the selected company and parent accounts
        $suppliers = Account::where('company_id', $request->company_id)
                            ->whereIn('parent_id', $parentIds)
                            ->whereNotNull('name') // Ensure name exists for suppliers
                            ->get(); 

        if ($suppliers->isEmpty()) {
            return response()->json(['message' => 'No suppliers found for this company'], 404);
        }

        return response()->json(['suppliers' => $suppliers]);
    }

    public function getAgentClientByCompany(Request $request)
    {
        // Get all parent account IDs where the name contains "Receivable"
        $parentIds = Account::where('name', 'LIKE', '%Receivable%')->pluck('id');
    
        // Retrieve agents linked to the selected company and parent accounts
        $agents = Account::where('company_id', $request->company_id)
                         ->whereIn('parent_id', $parentIds)
                         ->whereNotNull('name') // Ensure name exists for agents
                         ->get();
    
        if ($agents->isEmpty()) {
            return response()->json(['message' => 'No client or agents found for this company'], 404);
        }
    
        return response()->json(['agents' => $agents]);
    }

    public function getBankAccountByCompany(Request $request)
    {
        $companyId = $request->company_id;

        // Log company ID (optional)
        \Log::info("Fetching Bank Accounts for Company ID: " . $companyId);

        // Get parent account IDs where the name contains "Bank Accounts" and belongs to the selected company
        $parentIds = Account::where('name', 'LIKE', '%Bank Accounts%')
                            ->where('company_id', $companyId)
                            ->pluck('id');

        \Log::info("Parent Account IDs: " . json_encode($parentIds));

        // Fetch bank accounts under these parent IDs and the selected company
        $bankaccounts = Account::whereIn('parent_id', $parentIds)
                            ->where('company_id', $companyId)
                            ->get();

        \Log::info("Retrieved Bank Accounts: " . json_encode($bankaccounts));

        if ($bankaccounts->isEmpty()) {
            return response()->json(['message' => 'No bank account has been set for this company'], 404);
        }

        return response()->json(['bankaccounts' => $bankaccounts]);
    }


    public function getInvoicesByGeneralLedger(Request $request)
    {
   
        // Retrieve general ledger entries for the given company
        $ledgerEntries = GeneralLedger::where('company_id', $request->company_id)
                                      ->pluck('invoice_id'); // Get associated invoice IDs
    
        if ($ledgerEntries->isEmpty()) {
            return response()->json(['message' => 'No invoice record found for this company'], 404);
        }
    
        // Retrieve invoices linked to the general ledger entries
        $invoices = Invoice::whereIn('id', $ledgerEntries)->get();
    
        if ($invoices->isEmpty()) {
            return response()->json(['message' => 'No invoices found for this company'], 404);
        }
    
        return response()->json(['invoices' => $invoices]);
    }
    


    public function createPayableDetail()
    {
        $user = auth()->user();

        if ($user->role_id != Role::ADMIN) {
            if ($user->role_id != Role::COMPANY) {
                return abort(403, 'Unauthorized action.');
            }
            else {
                $companies = Company::where('user_id', $user->id)->get();
            }
        }
        else {
            $companies = Company::all();
        }

        $parentIds = Account::where('name', 'LIKE', '%Payable%')->pluck('id');
        $suppliers = Account::whereIn('parent_id', $parentIds)->get();

        $generalLedgers2 = GeneralLedger::whereIn('type', ['payable', 'expenses'])
        ->orderByDesc('created_at')  // Sort by date in descending order
        ->get()
        ->groupBy('type');

        $parentIdClients = Account::where('name', 'LIKE', '%Receivable%')->pluck('id');
        $clients = Account::whereIn('parent_id', $parentIdClients)->get();

        return view('accounting.payable-create', compact('companies', 'suppliers', 'clients', 'generalLedgers2'));
        
    }

    public function storePayableDetail(Request $request)
    {
        $user = auth()->user();

        if ($user->role_id != Role::COMPANY) {
            return abort(403, 'Unauthorized action.');
        }
        
        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'account_id' => 'required|integer',
            'branch_id' => 'required|integer',
            'transaction_id' => 'nullable|integer',
            'description' => 'required|string|max:255',
            'debit' => 'nullable|numeric',
            'credit' => 'nullable|numeric',
            'balance' => 'nullable|numeric',
            'invoice_id' => 'nullable|integer',
            'voucher_number' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'type' => 'required|string|max:255',
            'invoice_detail_id' => 'nullable|integer',
            'type_reference_id' => 'nullable|integer',
        ]);

        // Check if user is admin (role_id = 1)
        if (auth()->user()->role_id === 1) {
            $validated['company_id'] = 'required|integer';
        } else {
            $validated['company_id'] = $user->company->id;
        }
        $accountName = Account::find($request->account_id);
 
        //Account_From (company_bank)
        
        if (auth()->user()->role_id === 1) {
            $companyName = Company::find($request->company_id)?->name;
        } else {
            $companyName = Company::find(auth()->user()->company->id)?->name;
        }

        
        if ($request->has('amount')) {
            $validated['debit'] = $request->amount;
            $validated['credit'] = "0.00";
            $validated['balance'] = $request->amount;
        }
        $validated['description'] = $request->description . ' (From ' . strtoupper($request->bank_account) . ' to ' . strtoupper($accountName->name) . ')';
        $validated['name'] = $companyName;
        GeneralLedger::create($validated);

        //Account_From (supplier_name)
        if ($request->has('amount')) {
            $validated['debit'] = "0.00";
            $validated['credit'] = $request->amount;
            $validated['balance'] = "0.00";
        }

        $validated['description'] = $request->description . ' (From ' . strtoupper($request->bank_account) . ' to ' . strtoupper($accountName->name) . ')';
        $validated['name'] = $accountName->name;
        GeneralLedger::create($validated);

        return redirect()->route('payable-details.payable-create')
        ->with('success', 'Entry added successfully!')
        ->with('active_tab', request('active_tab'));
    }

    public function createReceivableDetail()
    {
        $user = auth()->user();

        if ($user->role_id != Role::ADMIN) {
            if ($user->role_id != Role::COMPANY) {
                return abort(403, 'Unauthorized action.');
            }
            else {
                $companies = Company::where('user_id', $user->id)->get();
            }
        }
        else {
            $companies = Company::all();
        }

        $parentIds = Account::where('name', 'LIKE', '%Payable%')->pluck('id');
        $suppliers = Account::whereIn('parent_id', $parentIds)->get();

        $generalLedgers = GeneralLedger::whereIn('type', ['receivable', 'income'])
        ->orderByDesc('created_at')  
        ->get()
        ->groupBy('type');  

        $parentIdClients = Account::where('name', 'LIKE', '%Receivable%')->pluck('id');
        $clients = Account::whereIn('parent_id', $parentIdClients)->get();

        return view('accounting.receivable-create', compact('companies', 'suppliers', 'clients', 'generalLedgers'));
        
    }

    public function storeReceivableDetail(Request $request)
    {
        $user = auth()->user();
        

        if ($user->role_id != Role::COMPANY) {
            return abort(403, 'Unauthorized action.');
        }
        
        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'account_id' => 'required|integer',
            'branch_id' => 'required|integer',
            'transaction_id' => 'nullable|integer',
            'description' => 'required|string|max:255',
            'debit' => 'nullable|numeric',
            'credit' => 'nullable|numeric',
            'balance' => 'nullable|numeric',
            'invoice_id' => 'nullable|integer',
            'voucher_number' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'type' => 'required|string|max:255',
            'invoice_detail_id' => 'nullable|integer',
            'type_reference_id' => 'nullable|integer',
        ]);

         // Check if user is admin (role_id = 1)
         if (auth()->user()->role_id === 1) {
             $validated['company_id'] = 'required|integer';
         } else {
             $validated['company_id'] = $user->company->id;
         }

        //Account_From (client_name)
        if ($request->has('amount')) {
            $validated['debit'] = $request->amount;
            $validated['credit'] = "0.00";
            $validated['balance'] = $request->amount;
        }
        $validated['description'] = $request->description . ' (From ' . strtoupper($request->name) . ' to ' . strtoupper($request->bank_account) . ')';
        $validated['name'] = $request->name;
        GeneralLedger::create($validated);

        
        //Account_To (company_bank)
        if ($request->has('amount')) {
            $validated['debit'] = "0.00";
            $validated['credit'] = $request->amount;
            $validated['balance'] = "0.00";
        }

        if (auth()->user()->role_id === 1) {
            $companyName = Company::find($request->company_id)?->name;
        } else {
            $companyName = Company::find(auth()->user()->company->id)?->name;
        }

        $validated['description'] = $request->description . ' (From ' . strtoupper($request->name) . ' to ' . strtoupper($request->bank_account) . ')';
        $validated['name'] = $companyName;
        GeneralLedger::create($validated);

        return redirect()->route('receivable-details.receivable-create')
        ->with('success', 'Entry added successfully!')
        ->with('active_tab', request('active_tab'));
    }

}
