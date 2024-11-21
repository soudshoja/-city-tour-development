<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;

use App\Models\User;
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

class AccountingController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Retrieve the company associated with the user, along with necessary relationships
        $company = Company::where('user_id', $user->id)->with([
            'branches.agents.clients.invoices' => function ($query) {
                // Summing credits and debits
                $query->withSum('transactions as total_credits', 'credit')
                      ->withSum('transactions as total_debits', 'debit');
            }
        ])->first();

        // Initialize the company summary data structure
        $companySummary = [];

        foreach ($company->branches as $branch) {
            // Calculate branch-level totals
            $branchCredits = 0;
            $branchDebits = 0;
            $branchBalance = 0;
            $branchData = [
                'branch_name' => $branch->name,
                'agents' => [],
                'total_credits' => 0,
                'total_debits' => 0,
                'balance' => 0,
            ];

            foreach ($branch->agents as $agent) {
                // Calculate agent-level totals
                $agentCredits = 0;
                $agentDebits = 0;
                $agentBalance = 0;
                $agentData = [
                    'agent_name' => $agent->name,
                    'clients' => [],
                    'total_credits' => 0,
                    'total_debits' => 0,
                    'balance' => 0,
                ];

                foreach ($agent->clients as $client) {
                    // Calculate client-level totals based on transactions within invoices
                    $clientCredits = $client->invoices->sum(fn($invoice) => $invoice->transactions->where('transaction_type', 'credit')->sum('amount'));
                    $clientDebits = $client->invoices->sum(fn($invoice) => $invoice->transactions->where('transaction_type', 'debit')->sum('amount'));
                    $clientBalance = $clientCredits - $clientDebits;

                    // Prepare client data with transactions
                    $clientData = [
                        'client_name' => $client->name,
                        'total_credits' => $clientCredits,
                        'total_debits' => $clientDebits,
                        'balance' => $clientBalance,
                        'transactions' => $client->invoices->flatMap(fn($invoice) => $invoice->transactions),
                    ];

                    // Append client data to the agent
                    $agentData['clients'][] = $clientData;

                    // Update agent totals
                    $agentCredits += $clientCredits;
                    $agentDebits += $clientDebits;
                    $agentBalance += $clientBalance;
                }

                // Update agent totals in the data structure
                $agentData['total_credits'] = $agentCredits;
                $agentData['total_debits'] = $agentDebits;
                $agentData['balance'] = $agentBalance;

                // Append agent data to the branch
                $branchData['agents'][] = $agentData;

                // Update branch totals
                $branchCredits += $agentCredits;
                $branchDebits += $agentDebits;
                $branchBalance += $agentBalance;
            }

            // Update branch totals in the data structure
            $branchData['total_credits'] = $branchCredits;
            $branchData['total_debits'] = $branchDebits;
            $branchData['balance'] = $branchBalance;

            // Append branch data to the company summary
            $companySummary[] = $branchData;
        }

        // Pass data to the view
        return view('accounting.summary', [
            'company' => $company,
            'companySummary' => $companySummary,
        ]);
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
    
        return view('accounting.summary', compact('company', 'accounts', 'companySummary'));
    }
    
    



}
