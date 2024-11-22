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
    
        // Retrieve the company associated with the user, including the necessary relationships
        $company = Company::where('user_id', $user->id)->with([
            'branches.agents.clients.invoices.invoiceDetails.generalLedgers' => function ($query) {
                // You can apply any specific queries here, if needed
            }
        ])->first();
    
        // Prepare data for dropdowns (branches -> agents -> clients -> invoices -> invoiceDetails -> generalLedgers)
        $branches = $company->branches->map(function ($branch) {
            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'agents' => $branch->agents->map(function ($agent) {
                    return [
                        'id' => $agent->id,
                        'name' => $agent->name,
                        'clients' => $agent->clients->map(function ($client) {
                            return [
                                'id' => $client->id,
                                'name' => $client->name,
                                'invoices' => $client->invoices->map(function ($invoice) {
                                    return [
                                        'id' => $invoice->id,
                                        'description' => $invoice->invoice_number,
                                        'invoiceDetails' => $invoice->invoiceDetails->map(function ($invoiceDetail) {
                                            return [
                                                'id' => $invoiceDetail->id,
                                                'generalLedgers' => $invoiceDetail->generalLedgers->map(function ($generalLedger) {
                                                    return [
                                                        'id' => $generalLedger->id,
                                                        'name' => $generalLedger->name, // Adjust field as necessary
                                                        'credit' => $generalLedger->credit, // Assuming these fields exist
                                                        'debit' => $generalLedger->debit, // Adjust as needed
                                                        'balance' => $generalLedger->balance, // Assuming balance exists
                                                    ];
                                                })
                                            ];
                                        })
                                    ];
                                })
                            ];
                        }),
                    ];
                }),
            ];
        });
    
        // Prepare data for generalLedgers (to replace transactions)
        $generalLedgers = [];
        foreach ($company->branches as $branch) {
            foreach ($branch->agents as $agent) {
                foreach ($agent->clients as $client) {
                    foreach ($client->invoices as $invoice) {
                        foreach ($invoice->invoiceDetails as $invoiceDetail) {
                            foreach ($invoiceDetail->generalLedgers as $generalLedger) {
                                $generalLedgers[] = [
                                    'generalLedger_id' => $generalLedger->id,
                                    'generalLedger_name' => $generalLedger->name,
                                    'credit' => $generalLedger->credit,
                                    'debit' => $generalLedger->debit,
                                    'balance' => $generalLedger->balance,
                                    'transaction_date' => $generalLedger->created_at,
                                    'description' => $generalLedger->description,
                                    'agent_name' => $agent->name,
                                    'type' => $generalLedger->type,
                                    'invoice_number' => $invoice->invoice_number,
                                    'status'=>$invoice->status
                                ];
                            }
                        }
                    }
                }
            }
        }
    
        // Pass the data to the view
        return view('accounting.index', [
            'company' => $company,
            'branches' => $branches, // Used for dropdown population
            'generalLedgers' => $generalLedgers, // To display in the table
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
    
    



}
