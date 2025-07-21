<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Agent;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction;
use App\Models\Role;
use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

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
            ->map(function ($agent) {
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
            ->map(function ($client) {
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
            ->map(function ($agent) {
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
            ->map(function ($client) {
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
            ->select('accounts.name', 'balance', 'accounts.company_id')
            ->get();

        // Fetch clients and suppliers
        $clients = DB::table('clients')
            ->join('agents', 'agents.id', '=', 'clients.agent_id')
            ->join('companies', 'companies.id', '=', 'agents.company_id')
            ->select('clients.id', 'clients.name', 'clients.agent_id')
            ->get();

        $suppliers = DB::table('suppliers') // Assuming there's a suppliers table
            ->select('id', 'name')
            ->get();

        return view('reports.accsummary', compact('accounts', 'clients', 'suppliers'));
    }

    public function accountsPayableReceivableReport(Request $request)
    {
        $user = Auth::user();
        if ($user->role_id == Role::AGENT) {
            return abort(403, 'Unauthorized action.');
        }

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $branchId = $request->input('branch_id');
        $supplierId = $request->input('supplier_id');
        $accountId = $request->input('account_id');

        $companyId = auth()->user()->company->id; // Adjust this to get the current company ID

        $accountPayable = Account::where('name', 'Accounts Payable')
            ->where('company_id', $companyId)
            ->first();

        if (!$accountPayable) {
            return redirect()->back()->with('error', 'Accounts Payable account not found.');
        }

        $receivableAccount = Account::where('name', 'Accounts Receivable')
            ->where('company_id', $companyId)
            ->first();

        if (!$receivableAccount) {
            return redirect()->back()->with('error', 'Accounts Receivable account not found.');
        }

        $payableQuery = JournalEntry::whereIn('account_id', function ($query) use ($accountPayable) {
            $query->select('id')
                ->from('accounts')
                ->where('parent_id', $accountPayable->id)
                ->orWhereIn('parent_id', function ($subquery) use ($accountPayable) {
                    $subquery->select('id')
                        ->from('accounts')
                        ->where('parent_id', $accountPayable->id);
                });
        });

        $receivableQuery = JournalEntry::whereIn('account_id', function ($query) use ($receivableAccount) {
            $query->select('id')
                ->from('accounts')
                ->where('id', $receivableAccount->id)
                ->orWhereIn('id', function ($subquery) use ($receivableAccount) {
                    $subquery->select('id')
                        ->from('accounts')
                        ->where('parent_id', $receivableAccount->id);
                });
        });

        if ($accountId && $accountId != 'all') {
            $childAccountIds = Account::where('id', $accountId)
                ->orWhereIn('id', function ($subquery) use ($accountId) {
                    $subquery->select('id')
                        ->from('accounts')
                        ->where('parent_id', $accountId);
                })
                ->pluck('id')
                ->toArray();

            $payableQuery->whereIn('account_id', $childAccountIds);
            $receivableQuery->whereIn('account_id', $childAccountIds);
        }

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

        if ($startDate == null && $endDate !== null) {
            $startDate = Carbon::parse($endDate)->startOfMonth(); // 2023-10-01 00:00:00

            $payableQuery->where('transaction_date', '>=', $startDate);
            $receivableQuery->where('transaction_date', '>=', $startDate);
        }

        if ($endDate == null && $startDate !== null) {
            $endDate = Carbon::parse($startDate)->endOfMonth(); // 2023-10-31 23:59:59

            $payableQuery->where('transaction_date', '<=', $endDate);
            $receivableQuery->where('transaction_date', '<=', $endDate);
        }


        if ($startDate && $endDate) {
            $startDate = Carbon::parse($startDate)->startOfDay(); // 2023-10-01 00:00:00
            $endDate = Carbon::parse($endDate)->endOfDay();

            $payableQuery->whereBetween('transaction_date', [$startDate, $endDate]);
            $receivableQuery->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        $payableTransactions = $payableQuery->get();
        $receivableTransactions = $receivableQuery->get();

        $receivableBalance = $receivableTransactions->sum('debit') - $receivableTransactions->sum('credit');
        $payableBalance = $payableTransactions->sum('credit') - $payableTransactions->sum('debit');

        $payableAccounts = collect();
        $receivableAccounts = collect();

        if (!isset($request->type_id) || $request->type_id == 'all' || $request->type_id == 'payable') {
            $payableAccounts = Account::where('parent_id', $accountPayable->id)
                ->orWhereIn('parent_id', function ($subquery) use ($accountPayable) {
                    $subquery->select('id')
                        ->from('accounts')
                        ->where('parent_id', $accountPayable->id);
                })
                ->get();
        }

        if (!isset($request->type_id) || $request->type_id == 'all' || $request->type_id == 'receivable') {
            $receivableAccounts = Account::where('id', $receivableAccount->id)
                ->orWhereIn('id', function ($subquery) use ($receivableAccount) {
                    $subquery->select('id')
                        ->from('accounts')
                        ->where('parent_id', $receivableAccount->id);
                })
                ->get();
        }
        // List out all accounts
        $allAccounts = $payableAccounts->merge($receivableAccounts);

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
        $user = auth()->user();

        if (Auth::user()->role->id == Role::ADMIN) {
            $suppliers = Supplier::with('companies')->get();
        } elseif (Auth::user()->role->id == Role::COMPANY) {
            $suppliers = SupplierCompany::where('company_id', $user->company->id)
                ->with('supplier')
                ->get();
        } else {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

        return view('reports.new-report', [
            'payableTransactions' => $payableTransactions,
            'receivableTransactions' => $receivableTransactions,
            'payableBalance' => $payableBalance,
            'receivableBalance' => $receivableBalance,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'branchId' => $branchId,
            'supplierId' => $supplierId,
            'branches' => $branches,
            'suppliers' => $suppliers,
            'accountPayable' => $accountPayable,
            'receivableAccount' => $receivableAccount,
            'accountId' => $accountId,
            'allAccounts' => $allAccounts,
        ]);
    }

    public function accountsReconciliationReport(Request $request)
    {
        // Default dates
        $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('to', Carbon::now()->endOfMonth()->toDateString());

        // Add validation for the new reconciled filter option
        $request->merge(['from' => $from, 'to' => $to])->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'reconciled' => 'nullable|in:both,yes,no',
        ]);

        $supplierName = $request->input('supplier');
        $reconciledFilter = $request->input('reconciled', 'both'); // default 'both'
        $user = auth()->user();
        if ($user->role_id == Role::AGENT) {
            return abort(403, 'Unauthorized action.');
        }

        $accountPayable = Account::where('name', 'Accounts Payable')->first();
        if (!$accountPayable) {
            return back()->with('error', 'Accounts Payable account not found.');
        }

        // Query totals grouped by account_id
        $totalsByAccountQuery = DB::table('journal_entries')
            ->join('accounts as a', 'journal_entries.account_id', '=', 'a.id')
            ->join('accounts as root_a', 'a.root_id', '=', 'root_a.id')
            ->select(
                'journal_entries.account_id',
                DB::raw('SUM(COALESCE(journal_entries.credit, 0)) - SUM(COALESCE(journal_entries.debit, 0)) AS total')
            )
            ->where('journal_entries.company_id', $user->company->id)
            ->where('journal_entries.branch_id', $user->branch->id)
            ->whereBetween('journal_entries.transaction_date', [$from, $to])
            ->whereIn('root_a.name', ['Liabilities']);

        if ($supplierName) {
            $totalsByAccountQuery->where('journal_entries.name', 'LIKE', "%{$supplierName}%");
        }

        // Apply reconcile filter to totals query
        if ($reconciledFilter === 'yes') {
            $totalsByAccountQuery->where('journal_entries.reconciled', 1);
        } elseif ($reconciledFilter === 'no') {
            $totalsByAccountQuery->where('journal_entries.reconciled', 0);
        }
        // 'both' means no filter on reconciled

        $totalsByAccount = $totalsByAccountQuery
            ->groupBy('journal_entries.account_id')
            ->havingRaw('total > 0')
            ->get()
            ->pluck('total', 'account_id');

        // Fetch journal entries filtered by reconcile status
        $entriesQuery = JournalEntry::whereIn('account_id', $totalsByAccount->keys())
            ->where('company_id', $user->company->id)
            ->where('branch_id', $user->branch->id)
            ->whereBetween('transaction_date', [$from, $to])
            ->where('credit', '!=', 0)
            ->whereNull('voucher_number')
            ->whereHas('account.root', function ($q) {
                $q->where('name', 'Liabilities');
            });

        if ($supplierName) {
            $entriesQuery->where('name', 'LIKE', "%{$supplierName}%");
        }

        // Apply reconcile filter to journal entries
        if ($reconciledFilter === 'yes') {
            $entriesQuery->where('reconciled', 1);
        } elseif ($reconciledFilter === 'no') {
            $entriesQuery->where('reconciled', 0);
        }

        $transactions = $entriesQuery
            ->with(['account', 'account.root'])
            ->orderBy('transaction_date')
            ->get();

        // Supplier options for datalist
        $suppliers = Supplier::query()
            ->when($supplierName, fn($q) => $q->where('name', 'LIKE', "%{$supplierName}%"))
            ->orderBy('name')
            ->get();

        return view('reports.acc-reconcile', [
            'accountPayable' => $accountPayable,
            'totalsByAccount' => $totalsByAccount,
            'transactions' => $transactions,
            'from' => $from,
            'to' => $to,
            'supplier' => $supplierName,
            'suppliers' => $suppliers,
            'reconciled' => $reconciledFilter, // pass to view for form select default
        ]);
    }


    public function getAccounts(Request $request)
    {
        $user = auth()->user();

        if ($user->company == null) {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

        $companyId = $user->company->id; // Adjust this to get the current company ID

        $accountPayable = Account::where('name', 'Accounts Payable')
            ->where('company_id', $companyId)
            ->first();

        if (!$accountPayable) {
            return redirect()->back()->with('error', 'Accounts Payable account not found.');
        }

        $receivableAccount = Account::where('name', 'Accounts Receivable')
            ->where('company_id', $companyId)
            ->first();

        if (!$receivableAccount) {
            return redirect()->back()->with('error', 'Accounts Receivable account not found.');
        }

        $payableAccounts = collect();
        $receivableAccounts = collect();

        $typeId = request()->query('type_id'); // Get type_id from the URL query parameter

        if ($typeId == 'payable' || $typeId == 'all') {
            $payableAccounts = Account::where('parent_id', $accountPayable->id)
                ->orWhereIn('parent_id', function ($subquery) use ($accountPayable) {
                    $subquery->select('id')
                        ->from('accounts')
                        ->where('parent_id', $accountPayable->id);
                })
                ->get();
        }

        if ($typeId == 'receivable' || $typeId == 'all') {
            $receivableAccounts = Account::where('id', $receivableAccount->id)
                ->orWhereIn('id', function ($subquery) use ($receivableAccount) {
                    $subquery->select('id')
                        ->from('accounts')
                        ->where('parent_id', $receivableAccount->id);
                })
                ->get();
        }

        // Merge all accounts
        $allAccounts = $payableAccounts->merge($receivableAccounts);

        return $allAccounts;
    }

    private function sumJournalEntries($account, $debitCreditType = 'normal')
    {
        if ($account->childAccounts) {
            foreach ($account->childAccounts as $childAccount) {
                $this->sumJournalEntries($childAccount, $debitCreditType);

                if ($account->journalEntries) {
                    $runningBalance = 0;
                    foreach ($account->journalEntries as $journalEntry) {
                        if ($debitCreditType == 'normal') {
                            $runningBalance += $journalEntry->debit - $journalEntry->credit;
                        } else {
                            $runningBalance += $journalEntry->credit - $journalEntry->debit;
                        }
                        $journalEntry->balance = $runningBalance;
                    }
                }
            }
        } else {
            if ($account->journalEntries) {
                $runningBalance = 0;
                foreach ($account->journalEntries as $journalEntry) {
                    if ($debitCreditType == 'normal') {
                        $runningBalance += $journalEntry->debit - $journalEntry->credit;
                    } else {
                        $runningBalance += $journalEntry->credit - $journalEntry->debit;
                    }
                    $journalEntry->balance = $runningBalance;
                }
            }
        }

        return $account;
    }

    public function getPayableSupplier()
    {

        $companyId = auth()->user()->company->id; // Adjust this to get the current company ID
        $accountPayable = Account::where('name', 'Accounts Payable')->first();

        if (!$accountPayable) {
            return redirect()->back()->with('error', 'Accounts Payable account not found.');
        }


        $coaController = new CoaController();
        $debitCreditType = 'reverse';

        $childAccountsPayable = $coaController->childAccount($accountPayable, $debitCreditType);

        $childAccountsPayable = $this->sumJournalEntries($childAccountsPayable, $debitCreditType);

        return $childAccountsPayable;
    }

    public function payableSupplier()
    {
        $childAccountsPayable = $this->getPayableSupplier();
        // return response()->json($childAccountsPayable);


        return view('reports.payable-supplier', [
            'childAccountsPayable' => $childAccountsPayable,
        ]);
    }

    public function getProfitAgent()
    {
        $branchesId = auth()->user()->company->branches->pluck('id')->toArray();

        $agents = Agent::with('account', 'invoices.invoiceDetails.task', 'invoices.transactions')->whereIn('branch_id', $branchesId)->get();

        $sumProfitAgent = 0;
        foreach ($agents as $agent) {
            // $agent->balance = 0;
            $agent->profit = 0;

            // if ($agent->account) {
            //     $journalEntries = JournalEntry::where('account_id', $agent->account->id)
            //         ->where('company_id', $companyId)
            //         ->orderBy('created_at', 'desc')
            //         ->get();

            //     $agent->balance = $journalEntries->sum('debit') - $journalEntries->sum('credit');
            // }

            foreach ($agent->invoices as $invoice) {
                foreach ($invoice->invoiceDetails as $invoiceDetail) {
                    $agent->profit += $invoiceDetail->markup_price;
                }
            }


            $sumProfitAgent += $agent->profit;
        }
        return [
            'agents' => $agents,
            'sumProfitAgent' => $sumProfitAgent,
        ];
    }

    public function profitAgent()
    {
        $profitAgent = $this->getProfitAgent();

        return view('reports.profit-agent', [
            'agents' => $profitAgent['agents'],
            'sumProfitAgent' => $profitAgent['sumProfitAgent'],
        ]);
    }

    public function getReceivable()
    {
        $companyId = auth()->user()->company->id;
        $receivableAccount = Account::where('name', 'Accounts Receivable')
            ->where('company_id', $companyId)
            ->first();

        if (!$receivableAccount) {
            return redirect()->back()->with('error', 'Accounts Receivable account not found.');
        }

        $coaController = new CoaController();
        $debitCreditType = 'normal';

        $childAccountsReceivable = $coaController->childAccount($receivableAccount, $debitCreditType);

        $childAccountsReceivable = $this->sumJournalEntries($childAccountsReceivable, $debitCreditType);

        return $childAccountsReceivable;
    }

    public function receivable()
    {
        $childAccountsReceivable = $this->getReceivable();

        // return response()->json($childAccountsReceivable);

        return view('reports.total-receivable', [
            'childAccountsReceivable' => $childAccountsReceivable,
        ]);
    }

    public function getTotalBank()
    {
        $companyId = auth()->user()->company->id;
        $bankAccount = Account::where('name', 'Bank Accounts')
            ->where('company_id', $companyId)
            ->first();

        if (!$bankAccount) {
            return redirect()->back()->with('error', 'Accounts Receivable account not found.');
        }

        $coaController = new CoaController();
        $debitCreditType = 'normal';

        $childAccountsBank = $coaController->childAccount($bankAccount, $debitCreditType);

        $childAccountsBank = $this->sumJournalEntries($childAccountsBank, $debitCreditType);

        return $childAccountsBank;
    }

    public function totalBank()
    {
        $childAccountsBank = $this->getTotalBank();

        // return response()->json($childAccountsBank);

        return view('reports.total-bank', [
            'childAccountsBank' => $childAccountsBank,
        ]);
    }

    public function getGatewayReceivable()
    {
        $companyId = auth()->user()->company->id;
        $gatewayAccount = Account::where('name', 'Payment Gateway')
            ->where('company_id', $companyId)
            ->first();

        if (!$gatewayAccount) {
            return redirect()->back()->with('error', 'Accounts Receivable account not found.');
        }

        $coaController = new CoaController();
        $debitCreditType = 'normal';

        $childAccountsBank = $coaController->childAccount($gatewayAccount, $debitCreditType);

        $childAccountsBank = $this->sumJournalEntries($childAccountsBank, $debitCreditType);

        return $childAccountsBank;
    }

    public function gatewayReceivable()
    {
        $childAccountsBank = $this->getGatewayReceivable();

        // return response()->json($childAccountsBank);

        return view('reports.gateway-receivable', [
            'childAccountsBank' => $childAccountsBank,
        ]);
    }
    public function show($account_name)
    {
        // Fetch journal entries where the account name matches the account_name
        // For example, only show entries related to 'clients'
        $account = Account::where('name', 'clients')->first();  // Check for 'clients' account type

        if ($account) {
            // Retrieve journal entries linked to this account
            $journalEntries = JournalEntry::where('account_id', $account->id)->get();

            // Pass data to the view
            return view('journal-entries.show', compact('journalEntries'));
        }

        // If account not found, you can handle it by showing an error or redirecting
        return abort(404);  // Or redirect with a message
    }

    public function settlementsReport(Request $request)
    {
        $user = Auth::user();

        // Only allow ADMIN and COMPANY roles
        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            abort(403, 'Unauthorized');
        }

        // Date range defaults
        $from = $request->input('from') ?? Carbon::now()->startOfMonth()->toDateString();
        $to = $request->input('to') ?? Carbon::now()->endOfMonth()->toDateString();

        // Base query: Only settlement-type transactions
        $query = Transaction::query()
            ->where('description', 'like', '%Settles to Bank (After 24h)%')
            ->whereBetween('transactions.created_at', ["$from 00:00:00", "$to 23:59:59"]);

        // Filter by payment gateway (first word of description)
        if ($request->filled('payment_gateway')) {
            $gateway = $request->input('payment_gateway');
            $query->whereRaw("SUBSTRING_INDEX(description, ' ', 1) = ?", [$gateway]);
        }

        // Filter by reference type
        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        // Company restriction for COMPANY users
        if ($user->role_id === Role::COMPANY) {
            $query->where('company_id', $user->company->id);
        }

        $query->orderByDesc('transactions.created_at')
            ->orderByDesc('transactions.id');
        $query->with(['company', 'account', 'payment']);

        $transactions = $query->paginate(25)->appends($request->query());

        // Get distinct payment gateways (first word in description)
        $gateways = Transaction::selectRaw("DISTINCT SUBSTRING_INDEX(description, ' ', 1) AS gateway")
            ->where('description', 'like', '%Settles to Bank (After 24h)%')
            ->orderBy('gateway')
            ->pluck('gateway');

        return view('reports.settlements', compact('transactions', 'gateways'));
    }

    public function journalEntriesByDate(Request $request)
    {
        $date = $request->input('date');

        if (!$date) {
            return response()->json(['entries' => []]);
        }

        $entries = JournalEntry::whereDate('transaction_date', $date)
            ->with('account')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'account_name' => $entry->account->code . ' - ' . $entry->account->name,
                    'debit' => $entry->debit,
                    'credit' => $entry->credit,
                    'description' => $entry->description,
                ];
            });

        return response()->json(['entries' => $entries]);
    }
}
