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
use App\Models\Task;
use App\Models\Report;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\Refund;
use App\Http\Controllers\CoaController;
use Illuminate\Support\Str;

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

    public function profitLoss(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $year = $request->input('year', now()->format('Y'));
        $from = \Carbon\Carbon::parse($month)->startOfMonth();
        $to = \Carbon\Carbon::parse($month)->endOfMonth();

        $level3Accounts = Account::where('report_type', Account::REPORT_TYPES['PROFIT_LOSS'])
            ->where('level', 3)
            ->get()
            ->sortBy('code');

        $allAccounts = Account::all();

        $journalEntries = JournalEntry::whereBetween('created_at', [$from, $to])->get();

        $grouped = [];

        foreach ($level3Accounts as $parent) {
            $descendants = $this->getAllDescendants($parent->id, $allAccounts);
            $totalAmount = 0;
            $childRows = [];

            foreach ($descendants as $child) {
                $childAmount = $journalEntries
                    ->where('account_id', $child->id)
                    ->sum(fn($j) => $j->credit - $j->debit);
                $totalAmount += $childAmount;

                $childRows[] = [
                    'account' => $child,
                    'amount' => $childAmount,
                ];
            }

            $parentAmount = $journalEntries
                ->where('account_id', $parent->id)
                ->sum(fn($j) => $j->credit - $j->debit);
            $totalAmount += $parentAmount;

            $grouped[$parent->id] = [
                'account' => $parent,
                'amount' => $totalAmount,
                'children' => $childRows,
            ];
        }

        $incomeAccounts = collect($grouped)->filter(fn($item) => str_starts_with($item['account']->code, '4'));
        $expenseAccounts = collect($grouped)->filter(fn($item) => str_starts_with($item['account']->code, '5'));

        $totalIncome = $incomeAccounts->sum('amount');
        $totalExpense = $expenseAccounts->sum(fn($a) => abs($a['amount']));
        $net = $totalIncome - $totalExpense;

        $monthlyLabels = [];
        $monthlyProfits = [];
        $monthlyProfitsColors = [];

        foreach (range(1, 12) as $m) {
            $start = \Carbon\Carbon::createFromDate($year, $m, 1)->startOfMonth();
            $end = \Carbon\Carbon::createFromDate($year, $m, 1)->endOfMonth();
            $entries = JournalEntry::whereBetween('created_at', [$start, $end])->get();

            $income = 0;
            $expense = 0;

            foreach ($level3Accounts as $parent) {
                $descendants = $this->getAllDescendants($parent->id, $allAccounts);
                $total = 0;

                foreach ($descendants as $child) {
                    $amount = $entries->where('account_id', $child->id)->sum(fn($j) => $j->credit - $j->debit);
                    $total += $amount;
                }

                $amount = $entries->where('account_id', $parent->id)->sum(fn($j) => $j->credit - $j->debit);
                $total += $amount;

                if (str_starts_with($parent->code, '4')) $income += $total;
                if (str_starts_with($parent->code, '5')) $expense += abs($total);
            }

            $monthlyLabels[] = $start->format('M');
            $profit = $income - $expense;
            $monthlyProfits[] = round($profit, 2);
            $monthlyProfitsColors[] = $profit >= 0 ? '#16a34a' : '#dc2626';
        }

        return view('reports.profit-loss', compact(
            'month',
            'year',
            'monthlyLabels',
            'monthlyProfits',
            'monthlyProfitsColors',
            'incomeAccounts',
            'expenseAccounts',
        ));
    }

    private function getAllDescendants($parentId, $allAccounts)
    {
        $children = $allAccounts->where('parent_id', $parentId);
        $descendants = collect();

        foreach ($children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->getAllDescendants($child->id, $allAccounts));
        }

        return $descendants;
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

    public function unpaidaccountsPayableReceivableReport(Request $request)
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

        $companyId = auth()->user()->company->id;

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

        // Preload all leaf accounts
        $payableAccounts = $this->getLeafAccountsUnderParent($accountPayable->id);
        $receivableAccounts = $this->getLeafAccountsUnderParent($receivableAccount->id);
        $allAccounts = $payableAccounts->merge($receivableAccounts);

        // Default to first account if none selected
        if (empty($accountId) || $accountId === 'all') {
            $firstAccount = $allAccounts->first();
            $accountId = $firstAccount ? $firstAccount->id : null;
        }

        $payableQuery = JournalEntry::whereIn('account_id', $payableAccounts->pluck('id'))
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc');

        $receivableQuery = JournalEntry::whereIn('account_id', $receivableAccounts->pluck('id'))
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc');

        // Apply account filter
        if ($accountId) {
            $payableQuery->where('account_id', $accountId);
            $receivableQuery->where('account_id', $accountId);
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
            $payableQuery->where('transaction_date', '<=', $endDate);
            $receivableQuery->where('transaction_date', '<=', $endDate);
        }

        if ($endDate == null && $startDate !== null) {
            $endDate = Carbon::parse($startDate)->endOfMonth();
            $payableQuery->where('transaction_date', '<=', $endDate);
            $receivableQuery->where('transaction_date', '<=', $endDate);
        }

        if ($startDate && $endDate) {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
            $payableQuery->whereBetween('transaction_date', [$startDate, $endDate]);
            $receivableQuery->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        $payableTransactions = $payableQuery->where('reconciled', 0)->get();
        $receivableTransactions = $receivableQuery->where('reconciled', 0)->get();

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

        if (Auth::user()->role->id == Role::ADMIN) {
            $suppliers = Supplier::with('companies')->get();
        } elseif (Auth::user()->role->id == Role::COMPANY) {
            $suppliers = SupplierCompany::where('company_id', $user->company->id)
                ->with('supplier')
                ->get();
        } elseif (Auth::user()->role->id == Role::ACCOUNTANT) {
            $suppliers = SupplierCompany::where('company_id', $user->accountant->branch->company->id)
                ->with('supplier')
                ->get();
        } else {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

        return view('reports.unpaid-report', [
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

    public function paidaccountsPayableReceivableReport(Request $request)
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

        $companyId = auth()->user()->company->id;

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

        // Preload all leaf accounts
        $payableAccounts = $this->getLeafAccountsUnderParent($accountPayable->id);
        $receivableAccounts = $this->getLeafAccountsUnderParent($receivableAccount->id);
        $allAccounts = $payableAccounts->merge($receivableAccounts);

        // Default to first account if none selected
        if (empty($accountId) || $accountId === 'all') {
            $firstAccount = $allAccounts->first();
            $accountId = $firstAccount ? $firstAccount->id : null;
        }

        $payableQuery = JournalEntry::whereIn('account_id', $payableAccounts->pluck('id'))
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc');

        $receivableQuery = JournalEntry::whereIn('account_id', $receivableAccounts->pluck('id'))
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc');

        // Apply account filter
        if ($accountId) {
            $payableQuery->where('account_id', $accountId);
            $receivableQuery->where('account_id', $accountId);
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
            $payableQuery->where('transaction_date', '<=', $endDate);
            $receivableQuery->where('transaction_date', '<=', $endDate);
        }

        if ($endDate == null && $startDate !== null) {
            $endDate = Carbon::parse($startDate)->endOfMonth();
            $payableQuery->where('transaction_date', '<=', $endDate);
            $receivableQuery->where('transaction_date', '<=', $endDate);
        }

        if ($startDate && $endDate) {
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
            $payableQuery->whereBetween('transaction_date', [$startDate, $endDate]);
            $receivableQuery->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        $payableTransactions = $payableQuery->where('reconciled', '!=', 0)->get();
        $receivableTransactions = $receivableQuery->where('reconciled', '!=', 0)->get();

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

        if (Auth::user()->role->id == Role::ADMIN) {
            $suppliers = Supplier::with('companies')->get();
        } elseif (Auth::user()->role->id == Role::COMPANY) {
            $suppliers = SupplierCompany::where('company_id', $user->company->id)
                ->with('supplier')
                ->get();
        } elseif (Auth::user()->role->id == Role::ACCOUNTANT) {
            $suppliers = SupplierCompany::where('company_id', $user->accountant->branch->company->id)
                ->with('supplier')
                ->get();
        } else {
            return redirect()->back()->with('error', 'Unauthorized action.');
        }

        return view('reports.paid-report', [
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
            ->where('journal_entries.company_id', $user->company->id ?? $user->accountant->branch->company->id)
            ->where('journal_entries.branch_id', $user->branch->id ?? $user->accountant->branch->id)
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
            ->where('company_id', $user->company->id ?? $user->accountant->branch->company->id)
            ->where('branch_id', $user->branch->id ?? $user->accountant->branch->id)
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
            // Get all leaf accounts (accounts with no children) under Accounts Payable hierarchy
            $payableAccounts = $this->getLeafAccountsUnderParent($accountPayable->id);
        }

        if ($typeId == 'receivable' || $typeId == 'all') {
            // Get all leaf accounts (accounts with no children) under Accounts Receivable hierarchy
            $receivableAccounts = $this->getLeafAccountsUnderParent($receivableAccount->id);
        }

        // Merge all accounts
        $allAccounts = $payableAccounts->merge($receivableAccounts);

        return $allAccounts;
    }

    /**
     * Get all leaf accounts (accounts with no children) under a parent account recursively
     */
    private function getLeafAccountsUnderParent($parentId)
    {
        // Get all accounts that don't have any children (leaf accounts)
        $leafAccounts = Account::whereDoesntHave('children')->get();

        // Filter to only include those that are descendants of the parent
        $descendants = collect();

        foreach ($leafAccounts as $account) {
            if ($this->isDescendantOf($account->id, $parentId)) {
                $descendants->push($account);
            }
        }

        return $descendants;
    }

    /**
     * Check if an account is a descendant of a parent account
     */
    private function isDescendantOf($accountId, $parentId)
    {
        $account = Account::find($accountId);

        while ($account && $account->parent_id) {
            if ($account->parent_id == $parentId) {
                return true;
            }
            $account = Account::find($account->parent_id);
        }

        return false;
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

        $companyId = auth()->user()->company->id ?? auth()->user()->accountant->branch->company->id; // Adjust this to get the current company ID
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
        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT])) {
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
                    'root_name' => $entry->account->root->name ?? '-',
                    'debit' => $entry->debit,
                    'credit' => $entry->credit,
                    'description' => $entry->description,
                ];
            });

        return response()->json(['entries' => $entries]);
    }

    public function creditors(Request $request)
    {
        $user = Auth::user();

        $accountId = $request->input('account_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $groupBySupplier = $request->input('group_by_supplier', false);

        if ($user->role_id != Role::COMPANY && $user->role_id != Role::ACCOUNTANT) {
            return abort(403, 'Unauthorized action.');
        }

        $liabilitiesAccount = Account::where('name', 'Liabilities')
            ->where('company_id', $user->company->id ?? $user->accountant->branch->company->id)
            ->first();

        if (!$liabilitiesAccount) {
            Log::info('Liabilities account not found for company ID: ' . $user->company->name ?? $user->accountant->branch->company->name);
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $payableAccounts = Account::where('name', 'Accounts Payable')
            ->where('company_id', $user->company->id ?? $user->accountant->branch->company->id)
            ->where('parent_id', $liabilitiesAccount->id)
            ->first();

        if (!$payableAccounts) {
            Log::info('Accounts Payable account not found under Liabilities for company ID: ' . $user->company->id);
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $creditorsAccount = Account::where('name', 'Creditors')
            ->where('company_id', $user->company->id  ?? $user->accountant->branch->company->id)
            ->where('parent_id', $payableAccounts->id)
            ->where('root_id', $liabilitiesAccount->id)
            ->first();

        if (!$creditorsAccount) {
            Log::info('Creditors account not found under Accounts Payable for company ID: ' . $user->company->id);
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $childOfCreditors = Account::where('parent_id', $creditorsAccount->id)
            ->where('company_id', $user->company->id ?? $user->accountant->branch->company->id)
            ->get();

        $accountForReport = null;

        if ($childOfCreditors->isNotEmpty()) {
            $accountForReport = $childOfCreditors->first();
        } else {
            Log::info('null do');
        }

        if ($accountId) {
            $accountForReport = Account::find($accountId);
        }

        if (!$accountForReport) {
            Log::info('No valid account found for the report. AccountId: ' . $accountId . ', ChildOfCreditors empty: ' . $childOfCreditors->isEmpty());
            return redirect()->back()->with('error', 'No valid account found for the report.');
        }

        // Build query with date filtering
        $journalQuery = JournalEntry::where('account_id', $accountForReport->id)
            ->where('company_id', $user->company->id ?? $user->accountant->branch->company->id);

        if ($startDate) {
            $journalQuery->whereDate('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $journalQuery->whereDate('transaction_date', '<=', $endDate);
        }

        $journalEntries = $journalQuery->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $balance = 0.0;

        foreach ($journalEntries as $entry) {
            $balance += $entry->credit - $entry->debit;
            $entry->balance = $balance;

            $task = Task::find($entry->task_id);
            $entry->task = $task;
        }

        $accountForReport->journalEntries = $journalEntries;
        $accountForReport->final_balance = $balance;

        // Group by supplier if requested
        $supplierGroups = [];
        if ($groupBySupplier) {
            foreach ($journalEntries as $entry) {
                if ($entry->task && $entry->task->supplier_id) {
                    $supplierName = $entry->task->supplier->name;
                    $supplierId = $entry->task->supplier_id;

                    if (!isset($supplierGroups[$supplierId])) {
                        $supplierGroups[$supplierId] = [
                            'supplier_id' => $supplierId,
                            'supplier_name' => $supplierName,
                            'entries' => [],
                            'total_credit' => 0,
                            'total_debit' => 0,
                            'balance' => 0,
                            'entries_count' => 0
                        ];
                    }

                    $supplierGroups[$supplierId]['entries'][] = $entry;
                    $supplierGroups[$supplierId]['total_credit'] += $entry->credit;
                    $supplierGroups[$supplierId]['total_debit'] += $entry->debit;
                    $supplierGroups[$supplierId]['balance'] += ($entry->credit - $entry->debit);
                    $supplierGroups[$supplierId]['entries_count']++;
                } else {
                    // Entries without supplier
                    if (!isset($supplierGroups[0])) {
                        $supplierGroups[0] = [
                            'supplier_id' => 0,
                            'supplier_name' => 'No Supplier',
                            'entries' => [],
                            'total_credit' => 0,
                            'total_debit' => 0,
                            'balance' => 0,
                            'entries_count' => 0
                        ];
                    }

                    $supplierGroups[0]['entries'][] = $entry;
                    $supplierGroups[0]['total_credit'] += $entry->credit;
                    $supplierGroups[0]['total_debit'] += $entry->debit;
                    $supplierGroups[0]['balance'] += ($entry->credit - $entry->debit);
                    $supplierGroups[0]['entries_count']++;
                }
            }

            // Sort supplier groups by balance descending
            uasort($supplierGroups, function ($a, $b) {
                return $b['balance'] <=> $a['balance'];
            });
        }

        // Calculate summary for all creditors
        $creditorsSummary = [];
        foreach ($childOfCreditors as $creditor) {
            $creditorQuery = JournalEntry::where('account_id', $creditor->id)
                ->where('company_id', $user->company->id);

            if ($startDate) {
                $creditorQuery->whereDate('transaction_date', '>=', $startDate);
            }

            if ($endDate) {
                $creditorQuery->whereDate('transaction_date', '<=', $endDate);
            }

            $creditorEntries = $creditorQuery->get();
            $creditorBalance = $creditorEntries->sum('credit') - $creditorEntries->sum('debit');

            if ($creditorBalance > 0) {
                $creditorsSummary[] = [
                    'id' => $creditor->id,
                    'name' => $creditor->name,
                    'balance' => $creditorBalance,
                    'entries_count' => $creditorEntries->count()
                ];
            }
        }

        // Sort by balance descending
        usort($creditorsSummary, function ($a, $b) {
            return $b['balance'] <=> $a['balance'];
        });

        return view('reports.creditors', [
            'journalEntries' => $accountForReport->journalEntries,
            'childOfCreditors' => $childOfCreditors,
            'accountForReport' => $accountForReport,
            'creditorsSummary' => $creditorsSummary,
            'supplierGroups' => $supplierGroups,
            'groupBySupplier' => $groupBySupplier,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }

    public function creditorsPdf(Request $request)
    {
        $user = Auth::user();

        if ($user->role_id != Role::COMPANY) {
            return abort(403, 'Unauthorized action.');
        }

        // Get the same data as the regular creditors method
        $accountId = $request->input('account_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $groupBySupplier = $request->input('group_by_supplier', false);
        $supplierName = $request->input('supplier_name'); // for single supplier reports
        if ($supplierName) {
            $supplierName = urldecode($supplierName);
        }

        // Auto-determine report type based on request parameters
        if ($supplierName) {
            $reportType = 'single_supplier';
        } elseif ($groupBySupplier) {
            $reportType = 'grouped';
        } else {
            $reportType = 'all';
        }

        // Get account structure
        $liabilitiesAccount = Account::where('name', 'Liabilities')
            ->where('company_id', $user->company->id)
            ->first();

        if (!$liabilitiesAccount) {
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $payableAccounts = Account::where('name', 'Accounts Payable')
            ->where('company_id', $user->company->id)
            ->where('parent_id', $liabilitiesAccount->id)
            ->first();

        if (!$payableAccounts) {
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $creditorsAccount = Account::where('name', 'Creditors')
            ->where('company_id', $user->company->id)
            ->where('parent_id', $payableAccounts->id)
            ->where('root_id', $liabilitiesAccount->id)
            ->first();

        if (!$creditorsAccount) {
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $childOfCreditors = Account::where('parent_id', $creditorsAccount->id)
            ->where('company_id', $user->company->id)
            ->get();

        $accountForReport = $childOfCreditors->first();

        if ($accountId) {
            $accountForReport = Account::find($accountId);
        }

        // Get journal entries
        $journalQuery = JournalEntry::where('account_id', $accountForReport->id)
            ->where('company_id', $user->company->id);

        if ($startDate) {
            $journalQuery->whereDate('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $journalQuery->whereDate('transaction_date', '<=', $endDate);
        }

        $journalEntries = $journalQuery->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $balance = 0.0;

        foreach ($journalEntries as $entry) {
            $balance += $entry->credit - $entry->debit;
            $entry->balance = $balance;

            $task = Task::find($entry->task_id);
            $entry->task = $task;
        }

        $accountForReport->journalEntries = $journalEntries;
        $accountForReport->final_balance = $balance;

        // If this is a single supplier report, filter journal entries to only include the selected supplier
        if ($reportType === 'single_supplier' && $supplierName) {
            $filteredEntries = collect();
            $filteredBalance = 0.0;

            foreach ($journalEntries as $entry) {
                if ($entry->task && $entry->task->supplier_id && $entry->task->supplier->name === $supplierName) {
                    $filteredBalance += $entry->credit - $entry->debit;
                    $entry->balance = $filteredBalance;
                    $filteredEntries->push($entry);
                }
            }

            $journalEntries = $filteredEntries;
            $balance = $filteredBalance;
            $accountForReport->journalEntries = $journalEntries;
            $accountForReport->final_balance = $balance;
        }

        // Group by supplier if requested or if single supplier report is needed
        $supplierGroups = [];
        if ($groupBySupplier || $reportType === 'grouped' || $reportType === 'single_supplier') {
            foreach ($journalEntries as $entry) {
                if ($entry->task && $entry->task->supplier_id) {
                    $entrySupplierName = $entry->task->supplier->name ?? 'Supplier #' . $entry->task->supplier_id;
                    $supplierIdFromTask = $entry->task->supplier_id;

                    if (!isset($supplierGroups[$supplierIdFromTask])) {
                        $supplierGroups[$supplierIdFromTask] = [
                            'supplier_id' => $supplierIdFromTask,
                            'supplier_name' => $entrySupplierName,
                            'entries' => [],
                            'total_credit' => 0,
                            'total_debit' => 0,
                            'balance' => 0,
                            'entries_count' => 0
                        ];
                    }

                    $supplierGroups[$supplierIdFromTask]['entries'][] = $entry;
                    $supplierGroups[$supplierIdFromTask]['total_credit'] += $entry->credit;
                    $supplierGroups[$supplierIdFromTask]['total_debit'] += $entry->debit;
                    $supplierGroups[$supplierIdFromTask]['balance'] += ($entry->credit - $entry->debit);
                    $supplierGroups[$supplierIdFromTask]['entries_count']++;
                } else {
                    if (!isset($supplierGroups[0])) {
                        $supplierGroups[0] = [
                            'supplier_id' => 0,
                            'supplier_name' => 'No Supplier',
                            'entries' => [],
                            'total_credit' => 0,
                            'total_debit' => 0,
                            'balance' => 0,
                            'entries_count' => 0
                        ];
                    }

                    $supplierGroups[0]['entries'][] = $entry;
                    $supplierGroups[0]['total_credit'] += $entry->credit;
                    $supplierGroups[0]['total_debit'] += $entry->debit;
                    $supplierGroups[0]['balance'] += ($entry->credit - $entry->debit);
                    $supplierGroups[0]['entries_count']++;
                }
            }

            uasort($supplierGroups, function ($a, $b) {
                return $b['balance'] <=> $a['balance'];
            });
        }

        $data = [
            'journalEntries' => $accountForReport->journalEntries,
            'accountForReport' => $accountForReport,
            'supplierGroups' => $supplierGroups,
            'groupBySupplier' => $groupBySupplier,
            'reportType' => $reportType,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'company' => $user->company,
            'generatedAt' => now()->format('M d, Y H:i:s')
        ];

        // Determine which PDF view to use based on report type
        switch ($reportType) {
            case 'grouped':
                $pdfView = 'reports.pdf.creditors-grouped';
                $filename = 'creditors-grouped-report-' . now()->format('Y-m-d') . '.pdf';
                break;
            case 'single_supplier':
                if ($supplierName) {
                    // Find supplier by name in the supplier groups
                    $selectedSupplier = null;
                    foreach ($supplierGroups as $group) {
                        if ($group['supplier_name'] === $supplierName) {
                            $selectedSupplier = $group;
                            break;
                        }
                    }

                    if ($selectedSupplier) {
                        $data['selectedSupplier'] = $selectedSupplier;
                        $pdfView = 'reports.pdf.creditors-single-supplier';
                        $filename = 'creditor-supplier-' . str_replace(' ', '-', $selectedSupplier['supplier_name']) . '-' . now()->format('Y-m-d') . '.pdf';
                    } else {
                        return redirect()->back()->with('error', 'Supplier not found.');
                    }
                } else {
                    return redirect()->back()->with('error', 'Supplier name is required for single supplier report.');
                }
                break;
            default:
                $pdfView = 'reports.pdf.creditors-all';
                $filename = 'creditors-all-report-' . now()->format('Y-m-d') . '.pdf';
        }

        // foreach($supplierGroups as $group){
        //     dump($group['supplier_name'] . ' - ' . $group['balance']);
        // }

        // dd($data);
        $pdf = Pdf::loadView($pdfView, $data)
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif']);

        return $pdf->download($filename);
    }

    public function dailySalesPdf(Request $request)
    {
        $user = Auth::user();
        if ($user->role->id == Role::COMPANY) {
            $companyId = $user->company->id;
        } elseif ($user->role->id == Role::ACCOUNTANT) {
            $companyId = $user->accountant->branch->company_id;
        }

        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->toDateString() : now()->toDateString();
        $summary = $this->dailySalesSummary($companyId, $date);
        $agents = $this->dailySalesAgents($companyId, $date);
        $suppliers = $this->dailySalesSuppliers($date);
        $refunds = $this->dailySalesRefunds($companyId, $date);

        $pdf = Pdf::loadView('reports.pdf.daily-sales', [
            'date' => $date,
            'summary' => $summary,
            'agents' => $agents,
            'suppliers' => $suppliers,
            'refunds' => $refunds,
            'company' => $user->company,
        ])->setPaper('a4', 'portrait')->setOptions(['defaultFont' => 'sans-serif']);

        $filename = 'daily-sales-' . Carbon::parse($date)->format('Y-m-d') . '.pdf';
        return $pdf->stream($filename);
    }

    public function dailySalesPdfDownload(Request $request)
    {
        if (Auth::user()->role->id == Role::COMPANY) {
            $companyId = Auth::user()->company->id;
        } elseif (Auth::user()->role->id == Role::ACCOUNTANT) {
            $companyId = Auth::user()->accountant->branch->company_id;
        }

        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->toDateString() : now()->toDateString();
        $summary = $this->dailySalesSummary($companyId, $date);
        $agents = $this->dailySalesAgents($companyId, $date);
        $suppliers = $this->dailySalesSuppliers($date);
        $refunds = $this->dailySalesRefunds($companyId, $date);

        $pdf = Pdf::loadView('reports.pdf.daily-sales', compact('summary', 'agents', 'suppliers', 'refunds', 'date'))
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif']);

        $filename = 'daily-sales-' . Carbon::parse($date)->format('Y-m-d') . '.pdf';
        return $pdf->download($filename);
    }

    public function dailySalesReport(Request $request)
    {
        if (Auth::user()->role->id == Role::COMPANY) {
            $companyId = Auth::user()->company->id;
        } elseif (Auth::user()->role->id == Role::ACCOUNTANT) {
            $companyId = Auth::user()->accountant->branch->company_id;
        } else {
            return abort(403, 'Unauthorized action.');
        }

        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->toDateString() : now()->toDateString();
        $dateField = 'issued_date'; // or 'supplier_pay_date'

        $summary = $this->dailySalesSummary($companyId, $date);
        $agents = $this->dailySalesAgents($companyId, $date);
        $groups = $this->dailySalesSuppliers($date);
        $refunds = $this->dailySalesRefunds($companyId, $date);

        return view('reports.daily-sales', compact('summary', 'agents', 'groups', 'refunds', 'date'));
    }

    private function dailySalesSummary($companyId, $date)
    {
        $invoices = Invoice::with('invoiceDetails')
            ->whereDate('invoice_date', $date)
            ->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId))
            ->get();

        $totalInvoices = $invoices->count();
        $totalInvoiced = $invoices->sum('amount');

        $partialsToday = InvoicePartial::query()
            ->whereDate('invoice_partials.created_at', $date)
            ->whereHas('invoice.agent.branch.company', fn($q) => $q->where('id', $companyId));

        $totalPaid = (clone $partialsToday)->sum('invoice_partials.amount');
        $cashSum = (clone $partialsToday)->where('invoice_partials.payment_gateway', 'cash')->sum('invoice_partials.amount');
        $creditSum = (clone $partialsToday)->where('invoice_partials.payment_gateway', 'credit')->sum('invoice_partials.amount');
        $gatewaySum = (clone $partialsToday)->whereNotIn('invoice_partials.payment_gateway', ['cash', 'credit'])->sum('invoice_partials.amount');

        $refunds = Refund::whereDate('created_at', $date)
            ->whereHas('invoice.agent.branch.company', fn($q) => $q->where('id', $companyId))
            ->sum('total_nett_refund');

        $profit = 0;
        foreach ($invoices as $invoice) {
            $profit += $invoice->invoiceDetails->sum('markup_price');
        }

        $topAgentRow = (clone $partialsToday)
            ->join('invoices as inv', 'invoice_partials.invoice_id', '=', 'inv.id')
            ->selectRaw('inv.agent_id as agent_id, SUM(invoice_partials.amount) as total_paid')
            ->groupBy('inv.agent_id')
            ->orderByDesc('total_paid')
            ->first();

        $topAgent = '-';
        $topAgentAmount = 0.0;
        if ($topAgentRow) {
            $agent = Agent::find($topAgentRow->agent_id);
            $topAgent = $agent->name ?? '-';
            $topAgentAmount = (float) $topAgentRow->total_paid;
        }

        $topSupplierRow = DB::table('invoice_details as idt')
            ->join('invoices as inv', 'idt.invoice_id', '=', 'inv.id')
            ->join('tasks as t', 'idt.task_id', '=', 't.id')
            ->whereDate('inv.invoice_date', $date)
            ->whereExists(function ($qq) use ($companyId) {
                $qq->select(DB::raw(1))
                    ->from('agents as a')
                    ->join('branches as b', 'a.branch_id', '=', 'b.id')
                    ->join('companies as c', 'b.company_id', '=', 'c.id')
                    ->whereColumn('inv.agent_id', 'a.id')
                    ->where('c.id', $companyId);
            })
            ->selectRaw('t.supplier_id, SUM(idt.task_price) as total_revenue')
            ->groupBy('t.supplier_id')
            ->orderByDesc('total_revenue')
            ->first();

        $topSupplier = '-';
        $topSupplierAmount = 0.0;
        if ($topSupplierRow && $topSupplierRow->supplier_id) {
            $supplier = Supplier::find($topSupplierRow->supplier_id);
            $topSupplier = $supplier->name ?? '-';
            $topSupplierAmount = (float) $topSupplierRow->total_revenue;
        }

        return [
            'totalInvoices' => $totalInvoices,
            'totalInvoiced' => $totalInvoiced,
            'totalPaid' => $totalPaid,
            'gatewaySum' => $gatewaySum,
            'cashSum' => $cashSum,
            'creditSum' => $creditSum,
            'refunds' => $refunds,
            'profit' => $profit,
            'topAgent' => $topAgent,
            'topAgentAmount' => $topAgentAmount,
            'topSupplier' => $topSupplier,
            'topSupplierAmount' => $topSupplierAmount,
        ];
    }

    private function dailySalesAgents(int $companyId, string $date)
    {
        $agents = Agent::whereHas('branch.company', fn($q) => $q->where('id', $companyId))
            ->with([
                'invoices' => fn($q) => $q->whereDate('invoice_date', $date)
                    ->with(['client', 'invoiceDetails.task']),
            ])->get();

        $data = [];

        foreach ($agents as $agent) {
            $invoices = $agent->invoices;
            $totalInvoices = $invoices->count();
            $totalInvoiced = $invoices->sum('amount');
            $paid = $invoices->where('status', 'paid')->sum('amount');
            $unpaid = $invoices->where('status', '<>', 'paid')->sum('amount');

            $summary = $this->calculateAgentCommission($agent, $invoices);
            $profit = $summary['profit'];
            $commission = $summary['commission'];

            $topupCollected = Payment::whereNull('invoice_id')
                ->where('agent_id', $agent->id)
                ->whereDate('created_at', $date)
                ->sum('amount');

            $data[] = [
                'agent' => $agent,
                'totalInvoices' => $totalInvoices,
                'totalInvoiced' => $totalInvoiced,
                'paid' => $paid,
                'unpaid'  => $unpaid,
                'profit' => $profit,
                'commission' => $commission,
                'topupCollected' => $topupCollected,
                'invoices' => $invoices,
            ];
        }

        return $data;
    }

    private function dailySalesSuppliers(string $date): array
    {
        Log::info('DailySuppliers: start', ['date' => $date]);

        $liabilities = Account::where('name', 'Liabilities')->first();
        if (!$liabilities) {
            Log::warning('DailySuppliers: Liabilities root not found');
            return [];
        }
        Log::info('DailySuppliers: Liabilities found', ['id' => $liabilities->id]);

        $accountPayable = Account::where('root_id', $liabilities->id)
            ->where(function ($q) {
                $q->where('name', 'Accounts Payable')
                ->orWhere('name', 'like', '%account%payable%')
                ->orWhere('name', 'like', '%accounts%payable%');
            })
            ->first();

        if (!$accountPayable) {
            Log::info('DailySuppliers: Accounts Payable node not found under Liabilities', [
                'root_id' => $liabilities->id,
            ]);
            return [];
        }
        Log::info('DailySuppliers: Accounts Payable found', ['ap_id' => $accountPayable->id, 'ap_name' => $accountPayable->name]);

        // 3) Supplier TYPES (children of A/P that include supplier/suppliers in the name)
        $supplierTypes = Account::where('root_id', $liabilities->id)
            ->where('parent_id', $accountPayable->id)
            ->where(function ($q) {
                $q->where('name', 'like', '%supplier%')
                ->orWhere('name', 'like', '%suppliers%');
            })
            ->orderBy('id')
            ->get(['id','name','parent_id']);

        Log::info('DailySuppliers: supplier types under A/P', [
            'count' => $supplierTypes->count(),
            'ids' => $supplierTypes->pluck('id')->take(30),
            'names' => $supplierTypes->pluck('name')->take(30),
        ]);

        if ($supplierTypes->isEmpty()) {
            Log::info('DailySuppliers: no supplier types found under A/P', [
                'ap_id' => $accountPayable->id,
                'root_id' => $liabilities->id,
            ]);
            return [];
        }

        $groups = [];

        foreach ($supplierTypes as $typeNode) {

            // 4a) Fetch SUPPLIERS under this type (L4)
            $suppliers = Account::where('parent_id', $typeNode->id)
                ->orderBy('id')
                ->get(['id','name','parent_id']);

            if ($suppliers->isEmpty()) {
                // Some charts post straight to the type node (rare). Treat type as the only supplier.
                $suppliers = collect([$typeNode]);
                Log::info('DailySuppliers: type has no supplier children; will treat TYPE as supplier', [
                    'type_id'   => $typeNode->id,
                    'type_name' => $typeNode->name,
                ]);
            } else {
                Log::info('DailySuppliers: suppliers under type', [
                    'type_id' => $typeNode->id,
                    'type_name' => $typeNode->name,
                    'supplier_count' => $suppliers->count(),
                    'supplier_ids' => $suppliers->pluck('id')->take(30),
                    'supplier_names' => $suppliers->pluck('name')->take(30),
                ]);
            }

            // 4b) For each supplier, get POSTING accounts (L5). If none, supplier itself is posting.
            $leafIds = collect();               // all posting ids for this TYPE
            $leafToSupplier = [];                      // map posting_id -> supplier_id
            $supplierById = $suppliers->keyBy('id'); // for easy lookup later

            foreach ($suppliers as $sup) {
                $children = Account::where('parent_id', $sup->id)->get(['id','name','parent_id']);
                $postings = $children->isNotEmpty() ? $children : collect([$sup]);

                Log::info('DailySuppliers: postings for supplier', [
                    'type_id' => $typeNode->id,
                    'type_name' => $typeNode->name,
                    'supplier_id' => $sup->id,
                    'supplier_name'=> $sup->name,
                    'posting_ids' => $postings->pluck('id')->take(50),
                    'posting_names'=> $postings->pluck('name')->take(50),
                    'posting_count'=> $postings->count(),
                    'used_supplier_as_posting' => $children->isEmpty(),
                ]);

                foreach ($postings as $p) {
                    $leafIds->push($p->id);
                    $leafToSupplier[$p->id] = $sup->id;
                }
            }

            $leafIds = $leafIds->unique()->values();
            if ($leafIds->isEmpty()) {
                Log::info('DailySuppliers: no posting ids resolved for type', [
                    'type_id' => $typeNode->id, 'type_name' => $typeNode->name,
                ]);
                continue;
            }

            // 4c) Pull JE for the selected TRANSACTION DATE on those posting accounts
            $jeToday = JournalEntry::with([
                'account:id,name,parent_id',
                'transaction:id,transaction_date',
                'task:id,reference,client_id,supplier_pay_date,issued_date',
                'task.client:id,name',
            ])
            ->whereIn('account_id', $leafIds)
            ->whereDate('transaction_date', $date)
            ->orderBy('transaction_date')
            ->get();

            Log::info('DailySuppliers: JEs for TYPE on date', [
                'type_id' => $typeNode->id,
                'type_name' => $typeNode->name,
                'date' => $date,
                'leaf_count'=> $leafIds->count(),
                'je_count' => $jeToday->count(),
                'je_by_account' => $jeToday->groupBy('account_id')->map->count()->toArray(),
            ]);

            if ($jeToday->isEmpty()) {
                continue;
            }

            // 4d) Aggregate by SUPPLIER using reverse map posting_id -> supplier_id
            $rows = [];
            $groupTotals = [
                'totalTasks' => 0,
                'totalTaskPrice' => 0.0,
                'paid' => 0.0,
                'unpaid' => 0.0,
            ];

            // supplier_id => entries
            $entriesBySupplier = [];
            foreach ($jeToday as $e) {
                $postingId  = $e->account_id;
                $supplierId = $leafToSupplier[$postingId] ?? null;
                if ($supplierId) {
                    $entriesBySupplier[$supplierId][] = $e;
                }
            }

            foreach ($entriesBySupplier as $supplierId => $entries) {
                $supplierNode = $supplierById->get($supplierId); // L4 node (e.g., Amadeus, Wethaq Insurance)
                if (!$supplierNode) continue;

                // Split entries by posting account (so details table shows each account)
                $byAccount = collect($entries)->groupBy('account_id');

                $accountRows    = [];
                $supplierTaskIds = [];
                $supplierCredit = 0.0;
                $supplierPaid = 0.0;

                foreach ($byAccount as $accId => $accEntries) {
                    $accObj = optional($accEntries->first())->account;
                    $debit  = $accEntries->sum('debit');
                    $credit = $accEntries->sum('credit');

                    $supplierCredit += $credit;
                    $supplierPaid += $debit;

                    foreach ($accEntries as $je) {
                        $tid = $je->task_id ?? optional($je->task)->id;
                        if ($tid) $supplierTaskIds[$tid] = true;
                    }

                    $entryRows = [];
                    foreach ($accEntries as $row) {
                        $entryRows[] = [
                            'transaction_date' => $row->transaction?->transaction_date,
                            'supplier_pay_date' => $row->task?->supplier_pay_date ?? $row->task?->issued_date,
                            'reference' => $row->task?->reference,
                            'client_name' => $row->task?->client?->name,
                            'account_name' => $accObj?->name,
                            'debit' => $row->debit,
                            'credit' => $row->credit,
                            'running_balance'=> $row->balance,
                        ];
                    }

                    $accountRows[] = [
                        'account' => $accObj ? $accObj->only(['id','name','parent_id']) : ['id'=>$accId,'name'=>'—','parent_id'=>null],
                        'debit' => $debit,
                        'credit' => $credit,
                        'entries' => $entryRows,
                    ];
                }

                if ($supplierCredit > 0) {
                    $totalTasks = count($supplierTaskIds);
                    $supplierUnpaid = max(0, $supplierCredit - $supplierPaid);
                    $rows[] = [
                        'supplier_account_name' => $supplierNode->name, // row name = supplier (L4)
                        'accounts' => $accountRows,        // details per posting account (L5)
                        'creditedToday' => $supplierCredit,
                        'totalTasks' => $totalTasks,
                        'totalTaskPrice' => $supplierCredit,
                        'paid' => $supplierPaid,
                        'unpaid' => $supplierUnpaid,
                    ];
                    $groupTotals['totalTasks'] += $totalTasks;
                    $groupTotals['totalTaskPrice'] += $supplierCredit;
                    $groupTotals['paid'] += $supplierPaid;
                    $groupTotals['unpaid'] += $supplierUnpaid;
                }
            }

            if (!empty($rows)) {
                $rows = collect($rows)->sortByDesc('creditedToday')->values()->all();
                // group header = TYPE name (e.g., Suppliers (Flights))
                $groups[$typeNode->name] = [
                    'totals' => [
                        'totalTasks' => $groupTotals['totalTasks'],
                        'totalTaskPrice' => $groupTotals['totalTaskPrice'],
                        'paid' => $groupTotals['paid'],
                        'unpaid' => $groupTotals['unpaid'],
                    ],
                    'rows' => $rows,
                ];

                Log::info('DailySuppliers: group built', [
                    'type_id' => $typeNode->id,
                    'type_name' => $typeNode->name,
                    'row_count' => count($rows),
                    'total_tasks' => $groupTotals['totalTasks'],
                    'total_task_price' => $groupTotals['totalTaskPrice'],
                    'total_paid' => $groupTotals['paid'],
                ]);
            } else {
                Log::info('DailySuppliers: no rows after supplier aggregation', [
                    'type_id'   => $typeNode->id,
                    'type_name' => $typeNode->name,
                ]);
            }
        }

        Log::info('DailySuppliers: finished', [
            'group_count' => count($groups),
            'group_names' => array_keys($groups),
        ]);

        return $groups;
    }

    private function dailySalesRefunds($companyId, $date)
    {
        return Refund::with([
            'invoice.agent',
            'invoice.client',
            'task.agent.branch.company',
            'task.originalTask.invoiceDetail.invoice',
        ])
            ->whereDate('created_at', $date)
            ->where(function ($q) use ($companyId) {
                $q->whereHas('invoice.agent.branch.company', fn($q) => $q->where('id', $companyId))
                    ->orWhereHas('task.agent.branch.company', fn($q) => $q->where('id', $companyId));
            })
            ->get()
            ->map(function ($refund) {
                $original  = $refund->task?->originalTask?->invoiceDetail?->invoice;
                $refundInv = $refund->invoice;

                $refund->refund_type = $original?->status === 'paid' ? 'Credit to Client' : 'Client Owes';
                $refund->original_invoice_number = $original?->invoice_number;
                $refund->original_invoice_status = $original?->status;
                $refund->refund_invoice_number = $refundInv?->invoice_number;
                $refund->refund_invoice_status = $refundInv?->status;

                $refund->links = [
                    'view_refund' => route('refunds.edit', ['task' => $refund->task->id, 'refund' => $refund->id]),
                    'view_original' => $original ? route('invoice.details', ['companyId' => $original->agent->branch->company_id, 'invoiceNumber' => $original->invoice_number]) : null,
                    'view_refund_inv' => $refundInv ? route('invoice.show', ['companyId' => $refundInv->agent->branch->company_id, 'invoiceNumber' => $refundInv->invoice_number]) : null,
                ];

                return $refund;
            });
    }

    private function calculateAgentCommission(Agent $agent, $invoices): array
    {
        $rate = $agent->commission ?? 0.15;
        $salary = $agent->salary ?? 0.0;
        $target = $agent->target ?? 0.0;
        $profitTotal   = 0.0;
        $perInvoiceRate = [];
        $invoiceProfit = [];

        foreach ($invoices as $invoice) {
            $invProfit = $invoice->invoiceDetails->sum('markup_price');
            $invoiceProfit[$invoice->id] = $invProfit;
            $profitTotal += $invProfit;

            $perInvoiceRate[$invoice->id] = $invProfit * $rate;
            $invoice->computed_profit = $invProfit;
        }

        $commissionTotal = 0.0;

        switch ((int) $agent->type_id) {
            case 1: // Salary only -> no commission on sales
                $commissionTotal = 0.0;
                foreach ($invoices as $invoice) {
                    $invoice->computed_commission = 0.0;
                }
                break;

            case 2: // Commission only
                $commissionTotal = array_sum($perInvoiceRate);
                foreach ($invoices as $invoice) {
                    $invoice->computed_commission = $perInvoiceRate[$invoice->id];
                }
                break;

            case 3: // Both-A: sum of (profit*rate) per invoice + salary once
                $commissionTotal = array_sum($perInvoiceRate) + $salary;
                foreach ($invoices as $invoice) {
                    // show the rate portion only at invoice level
                    $invoice->computed_commission = $perInvoiceRate[$invoice->id];
                }
                break;

            case 4: // Both-B: only if total profit > target, then (profit - salary)*rate + salary
                if ($profitTotal > $target) {
                    $base = max($profitTotal - $salary, 0.0);
                    $ratePool = $base * $rate;
                    $commissionTotal = $salary + $ratePool;

                    // Per-invoice display (proportional allocation of the rate pool)
                    foreach ($invoices as $invoice) {
                        $p = $invoiceProfit[$invoice->id];
                        $share = ($profitTotal > 0) ? ($p / $profitTotal) : 0;
                        $invoice->computed_commission = $ratePool * $share;
                    }
                } else {
                    $commissionTotal = 0.0;
                    foreach ($invoices as $invoice) {
                        $invoice->computed_commission = 0.0;
                    }
                }
                break;

            default:
                foreach ($invoices as $invoice) {
                    $invoice->computed_commission = 0.0;
                }
        }

        return [
            'profit' => $profitTotal,
            'commission' => $commissionTotal,
            'per_invoice' => $perInvoiceRate,
        ];
    }
}
