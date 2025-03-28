<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $agents = DB::table('transactions')
        ->join('companies', 'transactions.company_id', '=', 'companies.id')
        ->join('agents', 'companies.id', '=', 'agents.company_id')
        ->select('agents.name as name', 'transactions.description', 'transactions.amount', 'transactions.transaction_date')
        ->get()
        ->groupBy('name');

            $clients = DB::table('transactions')
            ->join('companies', 'transactions.company_id', '=', 'companies.id')
            ->join('clients', 'clients.id', '=', 'transactions.client_id')
            ->select('clients.name as name', 'transactions.description', 'transactions.amount', 'transactions.transaction_date')
            ->get()
            ->groupBy('name');
        return view('reports.index', compact('agents', 'clients'));
    }
    public function agentReport()
    {   
        return view('reports.maintenance'); // Show the maintenance page
        
        $agents = DB::table('transactions')
            ->join('companies', 'transactions.company_id', '=', 'companies.id')
            ->join('agents', 'companies.id', '=', 'agents.company_id')
            ->select(
                'agents.name as agent_name',
                DB::raw('COUNT(transactions.id) as total_transactions'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) as total_credit')
            )
            ->groupBy('agents.name')
            ->get();

        $agentLedgers = DB::table('journal_entries')
            ->join('transactions', 'journal_entries.transaction_id', '=', 'transactions.id')
            ->join('clients', 'clients.id', '=', 'transactions.client_id')
            ->join('agents', 'agents.id', '=', 'clients.agent_id')
            ->select(
                'agents.name as agent_name',
                'journal_entries.transaction_date',
                'journal_entries.description',
                'journal_entries.debit',
                'journal_entries.credit',
                'journal_entries.balance'
            )
            ->orderBy('agent_name')
            ->orderBy('journal_entries.transaction_date')
            ->get();

        return view('reports.agent', compact('agents', 'agentLedgers'));
    }

    // Fetch client report data
    public function clientReport()
    {   
        return view('reports.maintenance'); // Show the maintenance page

        $clients = DB::table('transactions')
            ->join('clients', 'clients.id', '=', 'transactions.client_id')
            ->select(
                'clients.name as client_name',
                DB::raw('COUNT(transactions.id) as total_transactions'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) as total_credit'),
                DB::raw('SUM(CASE WHEN transactions.status = "completed" THEN transactions.amount ELSE 0 END) as total_completed'),
                DB::raw('SUM(CASE WHEN transactions.status = "pending" THEN transactions.amount ELSE 0 END) as total_pending')
            )
            ->groupBy('clients.name')
            ->get();

        $clientLedgers = DB::table('journal_entries')
            ->join('transactions', 'journal_entries.transaction_id', '=', 'transactions.id')
            ->join('clients', 'clients.id', '=', 'transactions.client_id')
            ->select(
                'clients.name as client_name',
                'journal_entries.transaction_date',
                'journal_entries.description',
                'journal_entries.debit',
                'journal_entries.credit',
                'journal_entries.balance'
            )
            ->orderBy('client_name')
            ->orderBy('journal_entries.transaction_date')
            ->get();

        return view('reports.client', compact('clients', 'clientLedgers'));
    }


    public function performance()
    {
        return view('reports.maintenance'); // Show the maintenance page

        // Agent Performance Data
        $agents = DB::table('agents')
            ->join('clients', 'agents.id', '=', 'clients.agent_id')
            ->join('transactions', 'clients.id', '=', 'transactions.client_id')
            ->select(
                'agents.id',
                'agents.name as agent_name',
                DB::raw('COUNT(transactions.id) as total_transactions'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) as total_credit'),
                DB::raw('(SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) - SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END)) as balance')
            )
            ->groupBy('agents.id', 'agents.name')
            ->get()
            ->map(function($agent) {
                // Calculate a performance score based on custom logic
                $agent->performance_score = $agent->total_transactions > 10 && $agent->balance > 1000 ? 5 : 3; // Example score calculation
                return $agent;
            });

        // Client Performance Data
        $clients = DB::table('clients')
            ->join('transactions', 'clients.id', '=', 'transactions.client_id')
            ->select(
                'clients.id',
                'clients.name as client_name',
                DB::raw('COUNT(transactions.id) as total_transactions'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) as total_credit'),
                DB::raw('(SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) - SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END)) as balance')
            )
            ->groupBy('clients.id', 'clients.name')
            ->get()
            ->map(function($client) {
                // Determine if the client is a good payer based on balance and transaction history
                $client->is_good_payer = $client->total_debit < $client->total_credit && $client->balance >= 0;
                $client->client_rating = $client->is_good_payer ? 5 : 3; // Example rating
                return $client;
            });

        // Return to view
        return view('reports.performance', [
            'agents' => $agents,
            'clients' => $clients
        ]);
    }


    public function summary()
    {

        return view('reports.maintenance'); // Show the maintenance page

        // Fetch and process agent metrics
        $agents = DB::table('agents')
            ->join('clients', 'clients.agent_id', '=', 'agents.id')
            ->join('transactions', 'clients.id', '=', 'transactions.client_id')
            ->select(
                'agents.id',
                'agents.name as agent_name',
                DB::raw('COUNT(transactions.id) as total_transactions'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) as total_credit'),
                DB::raw('(SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) - SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END)) as net_balance'),
                DB::raw('SUM(transactions.amount) / COUNT(transactions.id) as avg_transaction_value')
            )
            ->groupBy('agents.id', 'agents.name')
            ->get()
            ->map(function($agent) {
                $agent->profit_margin = ($agent->total_credit - $agent->total_debit) / max($agent->total_credit, 1);
                return $agent;
            });

        // Fetch and process client metrics
        $clients = DB::table('clients')
            ->join('transactions', 'clients.id', '=', 'transactions.client_id')
            ->select(
                'clients.id',
                'clients.name as client_name',
                DB::raw('COUNT(transactions.id) as total_transactions'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) as total_credit'),
                DB::raw('(SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) - SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END)) as outstanding_balance'),
                DB::raw('SUM(transactions.amount) / COUNT(transactions.id) as avg_transaction_value'),
                DB::raw('MAX(transactions.transaction_date) as last_transaction_date')
            )
            ->groupBy('clients.id', 'clients.name')
            ->get()
            ->map(function($client) {
                $client->credit_score = $client->outstanding_balance < $client->total_credit ? 5 : 3;
                return $client;
            });

        return view('reports.summary', [
            'agents' => $agents,
            'clients' => $clients,
        ]);
    }

    public function accsummary()
    {

        return view('reports.maintenance'); // Show the maintenance page

        // Fetch summary of accounts based on company_id
        $accounts = DB::table('accounts')
            ->join('companies', 'companies.id', '=', 'accounts.company_id')
            ->join('users', 'users.id', '=', 'companies.user_id')
            ->select( 'accounts.name', 'balance', 'accounts.company_id')
            ->get();

        // Fetch clients and suppliers
        $clients = DB::table('clients')
            ->join('agents', 'agents.id', '=', 'clients.agent_id')
            ->join('companies', 'companies.id', '=', 'agents.company_id')
            ->select('clients.id', 'clients.name', 'clients.agent_id')
            ->get();

        $suppliers = DB::table('suppliers') // Assuming there's a suppliers table
            ->select('id','name')
            ->get();

        return view('reports.accsummary', compact('accounts', 'clients', 'suppliers'));
    }

    public function accountsPayableReceivableReport(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $branchId = $request->input('branch_id');
        $supplierId = $request->input('supplier_id');

        $companyId = auth()->user()->company->id; // Adjust this to get the current company ID

        // $payableQuery = JournalEntry::where('account_id', 50)
        //     ->where('company_id', $companyId);
        $payableQuery = JournalEntry::where('company_id', $companyId)
        ->where(function ($query) {
            $query->where('account_id', 50)
                  ->orWhereIn('account_id', function ($subquery) {
                      $subquery->select('id')
                               ->from('accounts')
                               ->where('parent_id', 50);
                  });
        })
        ->orderBy('created_at', 'desc'); 
    
    
        // $receivableQuery = JournalEntry::where('account_id', 45)
        //     ->where('company_id', $companyId);
        $receivableQuery = JournalEntry::where('company_id', $companyId)
        ->where(function ($query) {
            $query->where('account_id', 45)
                  ->orWhereIn('account_id', function ($subquery) {
                      $subquery->select('id')
                               ->from('accounts')
                               ->where('parent_id', 45);
                  });
        })
        ->orderBy('created_at', 'desc');  

        
        if ($branchId) {
            $payableQuery->where('branch_id', $branchId);
            $receivableQuery->where('branch_id', $branchId);
        }

        if ($supplierId) {
            $supplier = Supplier::find($supplierId);
                if ($supplier) {
                    $payableQuery->where('name', $supplier->name);
                    $receivableQuery->where('name', $supplier->name);
                }
        }

        // Get individual transactions (for the detailed view)
        $payableTransactions = $payableQuery
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                return $query->whereBetween('transaction_date', [$startDate, $endDate]);
            })
            ->orderBy('transaction_date')
            ->get();

        $receivableTransactions = $receivableQuery
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                return $query->whereBetween('transaction_date', [$startDate, $endDate]);
            })
            ->orderBy('transaction_date')
            ->get();

        $receivableBalance = $receivableTransactions->sum('debit') - $receivableTransactions->sum('credit');
        $payableBalance = $payableTransactions->sum('credit') - $payableTransactions->sum('debit');

        $receivableSum = 0.0;
        foreach ($payableTransactions as $transaction) {
            $balance = $transaction->credit - $transaction->debit;
            $receivableSum += $balance;
            $transaction->balance = $receivableSum;
        }

        $payableSum = 0.0;
        foreach ($receivableTransactions as $transaction) {
            $balance = $transaction->debit - $transaction->credit;
            $payableSum += $balance;
            $transaction->balance = $payableSum;
        }

        $branches = Branch::where('company_id', $companyId)->get();
        $suppliers = Supplier::all();

        return view('reports.new-report', [
            'payableTransactions' => $payableTransactions,
            'receivableTransactions' => $receivableTransactions,
            'payableBalance' => $payableBalance, // Pass the payable balance to the view
            'receivableBalance' => $receivableBalance, // Pass the receivable balance to the view
            'startDate' => $startDate,
            'endDate' => $endDate,
            'branchId' => $branchId,
            'supplierId' => $supplierId,
            'branches' => $branches,
            'suppliers' => $suppliers,
        ]);
    }
}
