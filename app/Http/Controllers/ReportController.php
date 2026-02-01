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
use App\Models\Client;
use App\Http\Controllers\CoaController;
use Exception;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        $agentsQuery = DB::table('transactions')
            ->join('companies', 'transactions.company_id', '=', 'companies.id')
            ->join('agents', 'companies.id', '=', 'agents.company_id')
            ->select('agents.name as name', 'transactions.description', 'transactions.amount', 'transactions.transaction_date');

        if ($companyId) {
            $agentsQuery->where('transactions.company_id', $companyId);
        }

        $agents = $agentsQuery->get()->groupBy('name');

        $clientsQuery = DB::table('transactions')
            ->join('companies', 'transactions.company_id', '=', 'companies.id')
            ->join('clients', 'clients.id', '=', 'transactions.client_id')
            ->select('clients.name as name', 'transactions.description', 'transactions.amount', 'transactions.transaction_date');

        if ($companyId) {
            $clientsQuery->where('transactions.company_id', $companyId);
        }

        $clients = $clientsQuery->get()->groupBy('name');

        return view('reports.index', compact('agents', 'clients'));
    }

    public function agentReport()
    {
        return view('reports.maintenance'); // Show the maintenance page

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $agentsQuery = DB::table('transactions')
            ->join('companies', 'transactions.company_id', '=', 'companies.id')
            ->join('agents', 'companies.id', '=', 'agents.company_id')
            ->select(
                'agents.name as agent_name',
                DB::raw('COUNT(transactions.id) as total_transactions'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) as total_credit')
            );

        if ($companyId) {
            $agentsQuery->where('transactions.company_id', $companyId);
        }

        $agents = $agentsQuery->groupBy('agents.name')->get();

        $agentLedgersQuery = DB::table('journal_entries')
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
            );

        if ($companyId) {
            $agentLedgersQuery->where('journal_entries.company_id', $companyId);
        }

        $agentLedgers = $agentLedgersQuery
            ->orderBy('agent_name')
            ->orderBy('journal_entries.transaction_date')
            ->get();

        return view('reports.agent', compact('agents', 'agentLedgers'));
    }

    /**
     * Enhanced Client Report Method - Task-wise View
     * 
     * Replace the existing clientReport method in ReportController.php with this version
     * This provides task-wise breakdown showing invoice and refund status per task
     */
    public function clientReport(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'nullable|integer',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $clientIds = $request->input('client_ids', []);
        $user = Auth::user();
        $companyId = getCompanyId($user);

        $clientsQuery = Client::with([
            'tasks' => function ($query) use ($dateFrom, $dateTo) {
                $query->when($dateFrom, fn($q) => $q->whereDate('supplier_pay_date', '>=', $dateFrom))
                    ->when($dateTo, fn($q) => $q->whereDate('supplier_pay_date', '<=', $dateTo))
                    ->with([
                        'supplier',
                        'invoiceDetail.invoice.agent.branch',
                        'refundDetail.refund',
                    ])
                    ->orderBy('supplier_pay_date', 'desc');
            },
            'invoices' => function ($query) use ($dateFrom, $dateTo) {
                $query->when($dateFrom, fn($q) => $q->whereDate('invoice_date', '>=', $dateFrom))
                    ->when($dateTo, fn($q) => $q->whereDate('invoice_date', '<=', $dateTo))
                    ->with(['invoicePartials', 'paymentApplications']);
            }
        ]);

        if ($companyId) {
            $clientsQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
        }

        if (!empty($clientIds)) {
            $clientsQuery->whereIn('id', $clientIds);
        }

        $clients = $clientsQuery->get();

        $clientData = [];
        $totalOwed = 0;
        $totalPaid = 0;
        $totalBalance = 0;

        foreach ($clients as $client) {
            $invoices = $client->invoices;
            $clientTotalOwed = 0;
            $clientTotalPaid = 0;
            $paidInvoicesCount = 0;

            foreach ($invoices as $invoice) {
                $clientTotalOwed += $invoice->amount;
                $paidAmount = $invoice->invoicePartials->where('status', 'paid')->sum('amount');
                $clientTotalPaid += $paidAmount;

                if (in_array($invoice->status, ['paid', 'refunded']) || $paidAmount >= $invoice->amount) {
                    $paidInvoicesCount++;
                }
            }

            $tasks = $client->tasks;
            $totalTasks = $tasks->count();

            $invoicedTasksCount = $tasks->filter(fn($t) => $t->invoiceDetail !== null)->count();
            $refundedTasksCount = $tasks->filter(fn($t) => $t->refundDetail !== null)->count();
            $uninvoicedTasksCount = $tasks->filter(function ($task) {
                return $task->invoiceDetail === null;
            })->count();

            $refundCredit = 0;
            $refundOwed = 0;

            foreach ($tasks as $task) {
                if ($task->refundDetail && $task->refundDetail->refund) {
                    $refund = $task->refundDetail->refund;
                    $refundAmount = $task->refundDetail->total_refund_to_client ?? 0;

                    if ($refund->refund_invoice_id === null) {
                        $refundCredit += $refundAmount;
                    } else {
                        $refundOwed += $refundAmount;
                    }
                }
            }

            $clientCredit = $client->total_credit ?? 0;

            $runningBalance = 0;
            $taskRows = [];

            $sortedTasks = $tasks->sortBy('supplier_pay_date');

            foreach ($sortedTasks as $task) {
                $debit = 0;
                $credit = 0;

                if (strtolower($task->status) === 'refund' || $task->refundDetail) {
                    // Refund = Credit (we owe client money back)
                    if ($task->refundDetail) {
                        $credit = $task->refundDetail->total_refund_to_client ?? $task->total ?? 0;
                    } else {
                        $credit = $task->total ?? 0;
                    }
                } else {
                    // Check if invoice is paid
                    $invoicePaid = false;
                    if ($task->invoiceDetail && $task->invoiceDetail->invoice) {
                        $invoiceStatus = strtolower($task->invoiceDetail->invoice->status ?? '');
                        // If invoice is paid, paid by refund, or refunded - it's settled
                        if (in_array($invoiceStatus, ['paid', 'paid by refund', 'refunded'])) {
                            $invoicePaid = true;
                        }
                    }

                    // Only show as DEBIT if invoice is NOT paid
                    if (!$invoicePaid) {
                        $debit = $task->invoiceDetail->task_price ?? $task->total ?? 0;
                    }
                    // If paid, both debit and credit stay 0 (settled)
                }

                $runningBalance = $runningBalance + $debit - $credit;

                $taskRows[] = [
                    'task' => $task,
                    'debit' => $debit,
                    'credit' => $credit,
                    'running_balance' => $runningBalance,
                ];
            }

            $balance = $clientTotalOwed - $clientTotalPaid;

            $clientData[] = [
                'client' => $client,
                'total_owed' => $clientTotalOwed,
                'total_paid' => $clientTotalPaid,
                'balance' => $balance,
                'total_tasks' => $totalTasks,
                'tasks' => $tasks, //->take(20)
                'task_rows' => $taskRows,
                'invoices_count' => $invoices->count(),
                'paid_invoices_count' => $paidInvoicesCount,
                'invoiced_tasks_count' => $invoicedTasksCount,
                'uninvoiced_tasks_count' => $uninvoicedTasksCount,
                'refunded_tasks_count' => $refundedTasksCount,
                'refund_credit' => $refundCredit,
                'refund_owed' => $refundOwed,
                'client_credit' => $clientCredit,
            ];

            $totalOwed += $clientTotalOwed;
            $totalPaid += $clientTotalPaid;
            $totalBalance += $balance;
        }

        // Sort by balance descending (clients who owe the most first)
        usort($clientData, fn($a, $b) => $b['balance'] <=> $a['balance']);

        // Pagination for view
        $perPage = 20;
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedData = array_slice($clientData, $offset, $perPage);
        $clientsPaginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedData,
            count($clientData),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $clientsListQuery = Client::orderBy('name');
        if ($companyId) {
            $clientsListQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
        }

        $clientsList = $clientsListQuery
            ->get(['id', 'name', 'first_name', 'middle_name', 'last_name'])
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->full_name ?: $c->name
            ]);

        return view('reports.client', [
            'clients' => $clientsPaginated,
            'allClients' => $clientData,
            'clientsList' => $clientsList,
            'totals' => [
                'totalOwed' => $totalOwed,
                'totalPaid' => $totalPaid,
                'totalBalance' => $totalBalance,
            ],
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function clientReportPdf(Request $request)
    {
        // Increase limits for large reports
        ini_set('max_execution_time', 300);

        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'client_ids' => 'nullable|array',
            'client_ids.*' => 'nullable|integer',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $clientIds = $request->input('client_ids', []);
        $user = Auth::user();
        $companyId = getCompanyId($user);

        $clientsQuery = Client::with([
            'tasks' => function ($query) use ($dateFrom, $dateTo) {
                $query->when($dateFrom, fn($q) => $q->whereDate('supplier_pay_date', '>=', $dateFrom))
                    ->when($dateTo, fn($q) => $q->whereDate('supplier_pay_date', '<=', $dateTo))
                    ->with([
                        'supplier',
                        'invoiceDetail.invoice.agent.branch',
                        'refundDetail.refund',
                    ])
                    ->orderBy('supplier_pay_date', 'desc');
            },
            'invoices' => function ($query) use ($dateFrom, $dateTo) {
                $query->when($dateFrom, fn($q) => $q->whereDate('invoice_date', '>=', $dateFrom))
                    ->when($dateTo, fn($q) => $q->whereDate('invoice_date', '<=', $dateTo))
                    ->with(['invoicePartials', 'paymentApplications']);
            }
        ]);

        if ($companyId) {
            $clientsQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
        }

        if (!empty($clientIds)) {
            $clientsQuery->whereIn('id', $clientIds);
        }

        $clientsQuery->where(function ($q) {
            $q->has('tasks')->orHas('invoices');
        });

        $clients = $clientsQuery->get();

        $clientData = [];
        $totalOwed = 0;
        $totalPaid = 0;
        $totalBalance = 0;

        foreach ($clients as $client) {
            $invoices = $client->invoices;
            $clientTotalOwed = 0;
            $clientTotalPaid = 0;
            $paidInvoicesCount = 0;

            foreach ($invoices as $invoice) {
                $clientTotalOwed += $invoice->amount;
                $paidAmount = $invoice->invoicePartials->where('status', 'paid')->sum('amount');
                $clientTotalPaid += $paidAmount;

                if ($invoice->status === 'paid' || $paidAmount >= $invoice->amount) {
                    $paidInvoicesCount++;
                }
            }

            $tasks = $client->tasks;
            $totalTasks = $tasks->count();

            $invoicedTasksCount = $tasks->filter(fn($t) => $t->invoiceDetail !== null)->count();
            $refundedTasksCount = $tasks->filter(fn($t) => $t->refundDetail !== null)->count();
            $uninvoicedTasksCount = $tasks->filter(fn($t) => $t->invoiceDetail === null)->count();

            $refundCredit = 0;
            $refundOwed = 0;

            foreach ($tasks as $task) {
                if ($task->refundDetail && $task->refundDetail->refund) {
                    $refund = $task->refundDetail->refund;
                    $refundAmount = $task->refundDetail->total_refund_to_client ?? 0;

                    if ($refund->refund_invoice_id === null) {
                        $refundCredit += $refundAmount;
                    } else {
                        $refundOwed += $refundAmount;
                    }
                }
            }

            $clientCredit = $client->total_credit ?? 0;
            $balance = $clientTotalOwed - $clientTotalPaid;

            // Skip clients with no tasks if date filter is applied
            if (($dateFrom || $dateTo) && $totalTasks === 0) continue;
            // Skip clients with no activity
            if ($totalTasks === 0 && $invoices->count() === 0) continue;

            $clientData[] = [
                'client' => $client,
                'total_owed' => $clientTotalOwed,
                'total_paid' => $clientTotalPaid,
                'balance' => $balance,
                'total_tasks' => $totalTasks,
                'tasks' => $tasks,
                'invoices_count' => $invoices->count(),
                'paid_invoices_count' => $paidInvoicesCount,
                'invoiced_tasks_count' => $invoicedTasksCount,
                'uninvoiced_tasks_count' => $uninvoicedTasksCount,
                'refunded_tasks_count' => $refundedTasksCount,
                'refund_credit' => $refundCredit,
                'refund_owed' => $refundOwed,
                'client_credit' => $clientCredit,
            ];

            $totalOwed += $clientTotalOwed;
            $totalPaid += $clientTotalPaid;
            $totalBalance += $balance;
        }

        usort($clientData, fn($a, $b) => $b['balance'] <=> $a['balance']);

        $pdf = Pdf::loadView('reports.pdf.client', [
            'allClients' => $clientData,
            'totals' => [
                'totalOwed' => $totalOwed,
                'totalPaid' => $totalPaid,
                'totalBalance' => $totalBalance,
            ],
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'generatedAt' => now()->format('d M Y, H:i'),
        ])->setPaper('a4', 'landscape');

        $filename = 'client-report-' . now()->format('Y-m-d') . '.pdf';
        return $pdf->download($filename);
    }

    public function performance()
    {
        return view('reports.maintenance'); // Show the maintenance page

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $agentsQuery = DB::table('agents')
            ->join('clients', 'agents.id', '=', 'clients.agent_id')
            ->join('transactions', 'clients.id', '=', 'transactions.client_id')
            ->select(
                'agents.id',
                'agents.name as agent_name',
                DB::raw('COUNT(transactions.id) as total_transactions'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) as total_credit'),
                DB::raw('(SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) - SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END)) as balance')
            );

        if ($companyId) {
            $agentsQuery->where('transactions.company_id', $companyId);
        }

        $agents = $agentsQuery
            ->groupBy('agents.id', 'agents.name')
            ->get()
            ->map(function ($agent) {
                // Calculate a performance score based on custom logic
                $agent->performance_score = $agent->total_transactions > 10 && $agent->balance > 1000 ? 5 : 3; // Example score calculation
                return $agent;
            });

        $clienclientsQueryts = DB::table('clients')
            ->join('transactions', 'clients.id', '=', 'transactions.client_id')
            ->select(
                'clients.id',
                'clients.name as client_name',
                DB::raw('COUNT(transactions.id) as total_transactions'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END) as total_debit'),
                DB::raw('SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) as total_credit'),
                DB::raw('(SUM(CASE WHEN transactions.transaction_type = "credit" THEN transactions.amount ELSE 0 END) - SUM(CASE WHEN transactions.transaction_type = "debit" THEN transactions.amount ELSE 0 END)) as balance')
            );

        if ($companyId) {
            $clientsQuery->where('transactions.company_id', $companyId);
        }

        $clients = $clientsQuery
            ->groupBy('clients.id', 'clients.name')
            ->get()
            ->map(function ($client) {
                $client->is_good_payer = $client->total_debit < $client->total_credit && $client->balance >= 0;
                $client->client_rating = $client->is_good_payer ? 5 : 3;
                return $client;
            });

        return view('reports.performance', [
            'agents' => $agents,
            'clients' => $clients
        ]);
    }

    public function summary()
    {
        return view('reports.maintenance'); // Show the maintenance page

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $agentsQuery = DB::table('agents')
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
            );

        if ($companyId) {
            $agentsQuery->where('transactions.company_id', $companyId);
        }

        $agents = $agentsQuery
            ->groupBy('agents.id', 'agents.name')
            ->get()
            ->map(function ($agent) {
                $agent->profit_margin = ($agent->total_credit - $agent->total_debit) / max($agent->total_credit, 1);
                return $agent;
            });

        // Fetch and process client metrics
        $clientsQuery = DB::table('clients')
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
            );

        if ($companyId) {
            $clientsQuery->where('transactions.company_id', $companyId);
        }

        $clients = $clientsQuery
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
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $month = $request->input('month', now()->format('Y-m'));
        $year = $request->input('year', now()->format('Y'));
        $from = \Carbon\Carbon::parse($month)->startOfMonth();
        $to = \Carbon\Carbon::parse($month)->endOfMonth();

        $allAccounts = Account::where('company_id', $companyId)->get();
        $accountsById = $allAccounts->keyBy('id');

        $childrenMap = [];
        foreach ($allAccounts as $account) {
            if ($account->parent_id) {
                if (!isset($childrenMap[$account->parent_id])) {
                    $childrenMap[$account->parent_id] = collect();
                }
                $childrenMap[$account->parent_id]->push($account);
            }
        }

        $level3Accounts = $allAccounts
            ->where('report_type', Account::REPORT_TYPES['PROFIT_LOSS'])
            ->where('level', 3)
            ->sortBy('code');

        $descendantsCache = [];
        foreach ($level3Accounts as $parent) {
            $descendantsCache[$parent->id] = $this->getAllDescendants($parent->id, $childrenMap);
        }

        $journalEntries = JournalEntry::where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $entriesByAccount = $journalEntries->groupBy('account_id');

        $grouped = [];

        foreach ($level3Accounts as $parent) {
            $descendants = $descendantsCache[$parent->id];
            $totalAmount = 0;
            $childRows = [];

            foreach ($descendants as $child) {
                $childEntries = $entriesByAccount->get($child->id, collect());
                $childAmount = $childEntries->sum(fn($j) => $j->credit - $j->debit);
                $totalAmount += $childAmount;

                if ($childAmount != 0) {
                    $childRows[] = [
                        'account' => $child,
                        'amount' => $childAmount,
                    ];
                }
            }

            $parentEntries = $entriesByAccount->get($parent->id, collect());
            $parentAmount = $parentEntries->sum(fn($j) => $j->credit - $j->debit);
            $totalAmount += $parentAmount;

            $grouped[$parent->id] = [
                'account' => $parent,
                'amount' => $totalAmount,
                'children' => $childRows,
            ];
        }

        $incomeAccounts = collect($grouped)->filter(fn($item) => str_starts_with($item['account']->code, '4'));
        $expenseAccounts = collect($grouped)->filter(fn($item) => str_starts_with($item['account']->code, '5'));

        $relevantAccountIds = collect();
        foreach ($level3Accounts as $parent) {
            $relevantAccountIds->push($parent->id);
            foreach ($descendantsCache[$parent->id] as $desc) {
                $relevantAccountIds->push($desc->id);
            }
        }
        $relevantAccountIds = $relevantAccountIds->unique()->values();

        $yearStart = \Carbon\Carbon::createFromDate($year, 1, 1)->startOfYear();
        $yearEnd = \Carbon\Carbon::createFromDate($year, 12, 31)->endOfYear();

        $yearlyEntries = JournalEntry::where('company_id', $companyId)
            ->whereIn('account_id', $relevantAccountIds)
            ->whereBetween('created_at', [$yearStart, $yearEnd])
            ->get();

        $entriesByMonthAndAccount = [];
        foreach ($yearlyEntries as $entry) {
            $monthKey = $entry->created_at->format('n');
            $accountId = $entry->account_id;

            if (!isset($entriesByMonthAndAccount[$monthKey])) {
                $entriesByMonthAndAccount[$monthKey] = [];
            }
            if (!isset($entriesByMonthAndAccount[$monthKey][$accountId])) {
                $entriesByMonthAndAccount[$monthKey][$accountId] = collect();
            }
            $entriesByMonthAndAccount[$monthKey][$accountId]->push($entry);
        }

        $monthlyLabels = [];
        $monthlyProfits = [];
        $monthlyProfitsColors = [];

        foreach (range(1, 12) as $m) {
            $monthEntries = $entriesByMonthAndAccount[$m] ?? [];

            $income = 0;
            $expense = 0;

            foreach ($level3Accounts as $parent) {
                $descendants = $descendantsCache[$parent->id];
                $total = 0;

                foreach ($descendants as $child) {
                    $childEntries = $monthEntries[$child->id] ?? collect();
                    $amount = $childEntries->sum(fn($j) => $j->credit - $j->debit);
                    $total += $amount;
                }

                $parentEntries = $monthEntries[$parent->id] ?? collect();
                $amount = $parentEntries->sum(fn($j) => $j->credit - $j->debit);
                $total += $amount;

                if (str_starts_with($parent->code, '4')) $income += $total;
                if (str_starts_with($parent->code, '5')) $expense += abs($total);
            }

            $monthlyLabels[] = \Carbon\Carbon::createFromDate($year, $m, 1)->format('M');
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

    private function getAllDescendants($parentId, $childrenMap)
    {
        $descendants = collect();
        $children = $childrenMap[$parentId] ?? collect();

        foreach ($children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->getAllDescendants($child->id, $childrenMap));
        }

        return $descendants;
    }

    public function accsummary()
    {
        return view('reports.maintenance'); // Show the maintenance page

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $accountsQuery = DB::table('accounts')
            ->join('companies', 'companies.id', '=', 'accounts.company_id')
            ->join('users', 'users.id', '=', 'companies.user_id')
            ->select('accounts.name', 'balance', 'accounts.company_id');

        if ($companyId) {
            $accountsQuery->where('accounts.company_id', $companyId);
        }

        $accounts = $accountsQuery->get();

        $clientsQuery = DB::table('clients')
            ->join('agents', 'agents.id', '=', 'clients.agent_id')
            ->join('companies', 'companies.id', '=', 'agents.company_id')
            ->select('clients.id', 'clients.name', 'clients.agent_id');

        if ($companyId) {
            $clientsQuery->where('companies.id', $companyId);
        }

        $clients = $clientsQuery->get();

        $suppliers = DB::table('suppliers')
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

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

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
            ->where('company_id', $companyId)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc');

        $receivableQuery = JournalEntry::whereIn('account_id', $receivableAccounts->pluck('id'))
            ->where('company_id', $companyId)
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

        if ($user->role_id == Role::ADMIN) {
            $suppliers = Supplier::with('companies')->get();
        } elseif ($user->role_id == Role::COMPANY) {
            $suppliers = SupplierCompany::where('company_id', $companyId)
                ->with('supplier')
                ->get();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $suppliers = SupplierCompany::where('company_id', $companyId)
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

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

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
            ->where('company_id', $companyId)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc');

        $receivableQuery = JournalEntry::whereIn('account_id', $receivableAccounts->pluck('id'))
            ->where('company_id', $companyId)
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

        if ($user->role_id == Role::ADMIN) {
            $suppliers = Supplier::with('companies')->get();
        } elseif ($user->role_id == Role::COMPANY) {
            $suppliers = SupplierCompany::where('company_id', $companyId)
                ->with('supplier')
                ->get();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $suppliers = SupplierCompany::where('company_id', $companyId)
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
        $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->input('to', Carbon::now()->endOfMonth()->toDateString());

        $request->merge(['from' => $from, 'to' => $to])->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'reconciled' => 'nullable|in:both,yes,no',
        ]);

        $supplierName = $request->input('supplier');
        $reconciledFilter = $request->input('reconciled', 'both');
        $user = Auth::user();

        if ($user->role_id == Role::AGENT) {
            return abort(403, 'Unauthorized action.');
        }

        $user = Auth::user();
        $companyId = getCompanyId($user);
        $branchId = null;
        if ($user->role_id == Role::COMPANY) {
            $branchId = $user->branch->id ?? null;
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $branchId = $user->accountant->branch->id ?? null;
        }

        $accountPayable = Account::where('name', 'Accounts Payable')
            ->where('company_id', $companyId)
            ->first();

        if (!$accountPayable) {
            return back()->with('error', 'Accounts Payable account not found.');
        }

        $totalsByAccountQuery = DB::table('journal_entries')
            ->join('accounts as a', 'journal_entries.account_id', '=', 'a.id')
            ->join('accounts as root_a', 'a.root_id', '=', 'root_a.id')
            ->select(
                'journal_entries.account_id',
                DB::raw('SUM(COALESCE(journal_entries.credit, 0)) - SUM(COALESCE(journal_entries.debit, 0)) AS total')
            )
            ->where('journal_entries.company_id', $companyId)
            ->whereBetween('journal_entries.transaction_date', [$from, $to])
            ->whereIn('root_a.name', ['Liabilities']);

        if ($branchId) {
            $totalsByAccountQuery->where('journal_entries.branch_id', $branchId);
        }

        if ($supplierName) {
            $totalsByAccountQuery->where('journal_entries.name', 'LIKE', "%{$supplierName}%");
        }

        if ($reconciledFilter === 'yes') {
            $totalsByAccountQuery->where('journal_entries.reconciled', 1);
        } elseif ($reconciledFilter === 'no') {
            $totalsByAccountQuery->where('journal_entries.reconciled', 0);
        }

        $totalsByAccount = $totalsByAccountQuery
            ->groupBy('journal_entries.account_id')
            ->havingRaw('total > 0')
            ->get()
            ->pluck('total', 'account_id');

        $entriesQuery = JournalEntry::whereIn('account_id', $totalsByAccount->keys())
            ->where('company_id', $companyId)
            ->whereBetween('transaction_date', [$from, $to])
            ->where('credit', '!=', 0)
            ->whereNull('voucher_number')
            ->whereHas('account.root', function ($q) {
                $q->where('name', 'Liabilities');
            });

        if ($branchId) {
            $entriesQuery->where('branch_id', $branchId);
        }

        if ($supplierName) {
            $entriesQuery->where('name', 'LIKE', "%{$supplierName}%");
        }

        if ($reconciledFilter === 'yes') {
            $entriesQuery->where('reconciled', 1);
        } elseif ($reconciledFilter === 'no') {
            $entriesQuery->where('reconciled', 0);
        }

        $transactions = $entriesQuery
            ->with(['account', 'account.root'])
            ->orderBy('transaction_date')
            ->get();

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
            'reconciled' => $reconciledFilter,
        ]);
    }

    public function getAccounts(Request $request)
    {
        $user = Auth::user();
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

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
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $accountPayable = Account::where('name', 'Accounts Payable')
            ->where('company_id', $companyId)
            ->first();

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
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return [
                'agents' => collect(),
                'sumProfitAgent' => 0,
            ];
        }

        $branchesId = Branch::where('company_id', $companyId)->pluck('id')->toArray();

        $agents = Agent::with('account', 'invoices.invoiceDetails.task', 'invoices.transactions')
            ->whereIn('branch_id', $branchesId)
            ->get();

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
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

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

        return view('reports.total-receivable', [
            'childAccountsReceivable' => $childAccountsReceivable,
        ]);
    }

    public function getTotalBank()
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $bankAccount = Account::where('name', 'Bank Accounts')
            ->where('company_id', $companyId)
            ->first();

        if (!$bankAccount) {
            return redirect()->back()->with('error', 'Bank Accounts account not found.');
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
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $gatewayAccount = Account::where('name', 'Payment Gateway')
            ->where('company_id', $companyId)
            ->first();

        if (!$gatewayAccount) {
            return redirect()->back()->with('error', 'Payment Gateway account not found.');
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

        $user = Auth::user();
        $companyId = getCompanyId($user);

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

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $query->orderByDesc('transactions.created_at')
            ->orderByDesc('transactions.id');
        $query->with(['company', 'account', 'payment']);

        $transactions = $query->paginate(25)->appends($request->query());

        $gatewaysQuery = Transaction::selectRaw("DISTINCT SUBSTRING_INDEX(description, ' ', 1) AS gateway")
            ->where('description', 'like', '%Settles to Bank (After 24h)%')
            ->orderBy('gateway');

        if ($companyId) {
            $gatewaysQuery->where('company_id', $companyId);
        }

        $gateways = $gatewaysQuery->pluck('gateway');

        return view('reports.settlements', compact('transactions', 'gateways'));
    }

    public function journalEntriesByDate(Request $request)
    {
        $date = $request->input('date');
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$date) {
            return response()->json(['entries' => []]);
        }

        $query = JournalEntry::whereDate('transaction_date', $date)
            ->with('account')
            ->orderBy('id', 'desc');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $entries = $query->get()
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

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT])) {
            return abort(403, 'Unauthorized action.');
        }

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $liabilitiesAccount = Account::where('name', 'Liabilities')
            ->where('company_id', $companyId)
            ->first();

        if (!$liabilitiesAccount) {
            Log::info('Liabilities account not found for company ID: ' . $companyId);
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $payableAccounts = Account::where('name', 'Accounts Payable')
            ->where('company_id', $companyId)
            ->where('parent_id', $liabilitiesAccount->id)
            ->first();

        if (!$payableAccounts) {
            Log::info('Accounts Payable account not found under Liabilities for company ID: ' . $companyId);
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $creditorsAccount = Account::where('name', 'Creditors')
            ->where('company_id', $companyId)
            ->where('parent_id', $payableAccounts->id)
            ->where('root_id', $liabilitiesAccount->id)
            ->first();

        if (!$creditorsAccount) {
            Log::info('Creditors account not found under Accounts Payable for company ID: ' . $companyId);
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $childOfCreditors = Account::where('parent_id', $creditorsAccount->id)
            ->where('company_id', $companyId)
            ->get();

        $accountForReport = null;

        if ($accountId) {
            $accountForReport = Account::find($accountId);
        } elseif ($childOfCreditors->isNotEmpty()) {
            $accountForReport = $childOfCreditors->first();
        } else {
            $accountForReport = $creditorsAccount;
            $childOfCreditors = collect([$creditorsAccount]);
        }

        if (!$accountForReport) {
            Log::info('No valid account found for the report. AccountId: ' . $accountId . ', ChildOfCreditors empty: ' . $childOfCreditors->isEmpty());
            return redirect()->back()->with('error', 'No valid account found for the report.');
        }

        $journalQuery = JournalEntry::where('account_id', $accountForReport->id)
            ->where('company_id', $companyId);

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
                ->where('company_id', $companyId);

            if ($startDate) {
                $creditorQuery->whereDate('transaction_date', '>=', $startDate);
            }

            if ($endDate) {
                $creditorQuery->whereDate('transaction_date', '<=', $endDate);
            }

            $creditorEntries = $creditorQuery->get();
            $creditorBalance = $creditorEntries->sum('credit') - $creditorEntries->sum('debit');

            // Include accounts with balance > 0 OR if it's the currently selected account
            if ($creditorBalance > 0 || $creditor->id === $accountForReport->id) {
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

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY])) {
            return abort(403, 'Unauthorized action.');
        }

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $company = Company::find($companyId);

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
            ->where('company_id', $companyId)
            ->first();

        if (!$liabilitiesAccount) {
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $payableAccounts = Account::where('name', 'Accounts Payable')
            ->where('company_id', $companyId)
            ->where('parent_id', $liabilitiesAccount->id)
            ->first();

        if (!$payableAccounts) {
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $creditorsAccount = Account::where('name', 'Creditors')
            ->where('company_id', $companyId)
            ->where('parent_id', $payableAccounts->id)
            ->where('root_id', $liabilitiesAccount->id)
            ->first();

        if (!$creditorsAccount) {
            return redirect()->back()->with('error', 'This page cannot be accessed at the moment. Please contact support.');
        }

        $childOfCreditors = Account::where('parent_id', $creditorsAccount->id)
            ->where('company_id', $companyId)
            ->get();

        $accountForReport = $childOfCreditors->first();

        if ($accountId) {
            $accountForReport = Account::find($accountId);
        }

        // Get journal entries
        $journalQuery = JournalEntry::where('account_id', $accountForReport->id)
            ->where('company_id', $companyId);

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
            'company' => $company,
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

        $pdf = Pdf::loadView($pdfView, $data)
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif']);

        return $pdf->download($filename);
    }

    // public function dailySalesPdf(Request $request)
    // {
    //     $user = Auth::user();
    //     $companyId = getCompanyId($user);

    //     if (!$companyId) {
    //         return redirect()->back()->with('error', 'Please select a company first.');
    //     }

    //     $company = Company::find($companyId);

    //     $date = $request->filled('date') ? Carbon::parse($request->input('date'))->toDateString() : now()->toDateString();
    //     $summary = $this->dailySalesSummary($companyId, $date);
    //     $agents = $this->dailySalesAgents($companyId, $date);
    //     $suppliers = $this->dailySalesSuppliers($date);
    //     $refunds = $this->dailySalesRefunds($companyId, $date);

    //     $pdf = Pdf::loadView('reports.pdf.daily-sales', [
    //         'date' => $date,
    //         'summary' => $summary,
    //         'agents' => $agents,
    //         'suppliers' => $suppliers,
    //         'refunds' => $refunds,
    //         'company' => $company,
    //     ])->setPaper('a4', 'portrait')->setOptions(['defaultFont' => 'sans-serif']);

    //     $filename = 'daily-sales-' . Carbon::parse($date)->format('Y-m-d') . '.pdf';
    //     return $pdf->stream($filename);
    // }

    // public function dailySalesPdfDownload(Request $request)
    // {
    //     $user = Auth::user();
    //     $companyId = getCompanyId($user);

    //     if (!$companyId) {
    //         return redirect()->back()->with('error', 'Please select a company first.');
    //     }

    //     $company = Company::find($companyId);

    //     $date = $request->filled('date') ? Carbon::parse($request->input('date'))->toDateString() : now()->toDateString();
    //     $summary = $this->dailySalesSummary($companyId, $date);
    //     $agents = $this->dailySalesAgents($companyId, $date);
    //     $suppliers = $this->dailySalesSuppliers($date);
    //     $refunds = $this->dailySalesRefunds($companyId, $date);

    //     $pdf = Pdf::loadView('reports.pdf.daily-sales', [
    //         'date' => $date,
    //         'summary' => $summary,
    //         'agents' => $agents,
    //         'suppliers' => $suppliers,
    //         'refunds' => $refunds,
    //         'company' => $company,
    //     ])
    //         ->setPaper('a4', 'portrait')
    //         ->setOptions(['defaultFont' => 'sans-serif']);

    //     $filename = 'daily-sales-' . Carbon::parse($date)->format('Y-m-d') . '.pdf';
    //     return $pdf->download($filename);
    // }

    public function dailySalesReport(Request $request)
    {
        $user = Auth::user();
        $roleId = $user->role_id;

        if (!in_array($roleId, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT])) {
            abort(403, 'Unauthorized action.');
        }

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $from = $request->filled('from_date') ? Carbon::parse($request->input('from_date'))->startOfDay() : now()->startOfDay();
        $to = $request->filled('to_date') ? Carbon::parse($request->input('to_date'))->endOfDay() : (clone $from)->endOfDay();

        $reportView = $request->input('report_view', 'summary');
        if ($reportView !== 'details') {
            $request->merge(['task_types' => []]);
        }
        $taskTypes = collect((array) $request->input('task_types', []))->filter()->unique()->values()->all();

        $agentIds = collect((array) $request->input('agent_ids', []))->filter()->map(fn($v) => (int) $v)->unique()->values()->all();
        $allAgents = Agent::whereHas('branch.company', fn($q) => $q->where('id', $companyId))->orderBy('name')->get(['id', 'name']);

        $summary = $this->rangeSalesSummary($companyId, $from, $to, $agentIds);
        $agents = $this->rangeSalesAgents($companyId, $from, $to, $agentIds);
        $groups = $this->rangeSalesSuppliers($from, $to, $companyId, $agentIds);
        $refunds = $this->rangeSalesRefunds($companyId, $from, $to, $agentIds);

        $possibleTypes = [
            'hotel' => 'Hotel',
            'flight' => 'Flight',
            'visa' => 'Visa',
            'insurance' => 'Insurance',
            'tour' => 'Tour',
            'cruise' => 'Cruise',
            'car' => 'Car',
            'rail' => 'Rail',
            'esim' => 'Esim',
            'event' => 'Event',
            'lounge' => 'Lounge',
            'ferry' => 'Ferry',
        ];

        $tasks = null;
        if ($reportView === 'details') {
            $tasks = $this->rangeSalesTasks($companyId, $from, $to, $agentIds, $taskTypes);
        }

        return view('reports.daily-sales', compact('summary', 'agents', 'groups', 'refunds', 'from', 'to', 'allAgents', 'reportView', 'tasks', 'taskTypes', 'possibleTypes'));
    }

    private function rangeSalesTasks(int $companyId, Carbon $from, Carbon $to, array $agentIds = [], array $taskTypes = [])
    {
        return Task::query()
            ->with([
                'client',
                'agent',
                'agent.branch.company',
                'supplier',
                'invoiceDetail',
                'invoiceDetail.invoice',
                'invoiceDetail.invoice.invoicePartials'
            ])
            ->whereBetween('supplier_pay_date', [$from, $to])
            ->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId))
            ->when(!empty($agentIds), fn($q) => $q->whereIn('agent_id', $agentIds))
            ->when(!empty($taskTypes), fn($q) => $q->whereIn('type', $taskTypes))
            ->orderBy('supplier_pay_date')
            ->paginate(30)
            ->withQueryString();
    }

    private function rangeSalesSummary(int $companyId, Carbon $from, Carbon $to, $agentIds): array
    {
        $invBase = Invoice::with('invoiceDetails')
            ->whereBetween('invoice_date', [$from, $to])
            ->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));

        if (!empty($agentIds)) {
            $invBase->whereIn('agent_id', $agentIds);
        }

        $invoices = $invBase->get();
        $totalInvoices = $invoices->count();
        $totalInvoiced = $invoices->sum('amount');

        $partials = InvoicePartial::query()
            ->whereHas('invoice', function ($q) use ($companyId, $from, $to, $agentIds) {
                $q->whereBetween('invoice_date', [$from, $to])
                    ->whereHas('agent.branch.company', fn($q2) => $q2->where('id', $companyId));
                if (!empty($agentIds)) $q->whereIn('agent_id', $agentIds);
            });

        $totalPaid = (clone $partials)->sum('invoice_partials.amount');
        $cashSum   = (clone $partials)->where('invoice_partials.payment_gateway', 'cash')->sum('invoice_partials.amount');
        $creditSum = (clone $partials)->where('invoice_partials.payment_gateway', 'credit')->sum('invoice_partials.amount');
        $gatewaySum = (clone $partials)->whereNotIn('invoice_partials.payment_gateway', ['cash', 'credit'])->sum('invoice_partials.amount');

        $refunds = Refund::whereBetween('refund_date', [$from, $to])
            ->where(function ($q) use ($companyId, $agentIds) {
                $q->whereHas('invoice.agent.branch.company', fn($q) => $q->where('id', $companyId))
                    ->orWhereHas('refundDetails.task.agent.branch.company', fn($q) => $q->where('id', $companyId));
                if ($agentIds) {
                    $q->where(function ($qq) use ($agentIds) {
                        $qq->whereHas('invoice', fn($q) => $q->whereIn('agent_id', $agentIds))
                            ->orWhereHas('refundDetails.task', fn($q) => $q->whereIn('agent_id', $agentIds));
                    });
                }
            })
            ->sum('total_nett_refund');

        $profit = 0;
        foreach ($invoices as $inv) {
            $profit += $inv->invoiceDetails->sum('profit');
        }

        $topAgentRow = (clone $partials)
            ->join('invoices as inv', 'invoice_partials.invoice_id', '=', 'inv.id')
            ->selectRaw('inv.agent_id, SUM(invoice_partials.amount) as total_paid')
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
            ->whereBetween('inv.invoice_date', [$from, $to])
            ->whereExists(function ($qq) use ($companyId) {
                $qq->select(DB::raw(1))
                    ->from('agents as a')
                    ->join('branches as b', 'a.branch_id', '=', 'b.id')
                    ->join('companies as c', 'b.company_id', '=', 'c.id')
                    ->whereColumn('inv.agent_id', 'a.id')
                    ->where('c.id', $companyId);
            });

        if ($agentIds) $topSupplierRow->whereIn('inv.agent_id', $agentIds);

        $topSupplierRow = $topSupplierRow
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

        return compact('totalInvoices', 'totalInvoiced', 'totalPaid', 'gatewaySum', 'cashSum', 'creditSum', 'refunds', 'profit', 'topAgent', 'topAgentAmount', 'topSupplier', 'topSupplierAmount');
    }

    private function rangeSalesAgents(int $companyId, Carbon $from, Carbon $to, $agentIds)
    {
        $query = Agent::whereHas('branch.company', fn($q) => $q->where('id', $companyId))
            ->with([
                'invoices' => fn($q) => $q->whereBetween('invoice_date', [$from, $to])
                    ->with(['client', 'invoiceDetails.task', 'invoicePartials']),
            ]);

        if (!empty($agentIds)) $query->whereIn('id', $agentIds);

        $agents = $query->get();

        $data = [];
        foreach ($agents as $agent) {
            $invoices = $agent->invoices->each(function ($invoice) {
                $partials = $invoice->invoicePartials ?? collect();
                $paid = $invoice->status === 'paid' ? $invoice->amount : $partials->where('status', 'paid')->sum('amount');
                $invoice->setAttribute('paid_amount', $paid);
                $invoice->setAttribute('unpaid_amount', max(0, $invoice->amount - $paid));
            });
            $totalInvoices = $invoices->count();
            $totalInvoiced = $invoices->sum('amount');
            $paid = $invoices->where('status', 'paid')->sum('amount');
            $unpaid = $invoices->where('status', '<>', 'paid')->sum('amount');

            $summary = $this->calculateAgentCommission($agent, $invoices);

            $topupCollected = Payment::whereNull('invoice_id')
                ->where('agent_id', $agent->id)
                ->whereBetween('payment_date', [$from, $to])
                ->sum('amount');

            $totalTasks = Task::where('agent_id', $agent->id)
                ->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId))
                ->whereBetween('supplier_pay_date', [$from, $to])
                ->count();

            $voidTasks = Task::where('agent_id', $agent->id)
                ->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId))
                ->whereBetween('supplier_pay_date', [$from, $to])
                ->where('status', 'void')
                ->count();

            $data[] = [
                'agent' => $agent,
                'totalInvoices' => $totalInvoices,
                'totalInvoiced' => $totalInvoiced,
                'paid' => $paid,
                'unpaid' => $unpaid,
                'profit' => $summary['profit'],
                'commission' => $summary['commission'],
                'topupCollected' => $topupCollected,
                'invoices' => $invoices,
                'totalTasks' => $totalTasks,
                'voidTasks' => $voidTasks,
            ];
        }
        return $data;
    }

    private function rangeSalesSuppliers(Carbon $from, Carbon $to, int $companyId, $agentIds = null): array
    {
        Log::info('RangeSuppliers: start', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'company' => $companyId, 'agent' => $agentIds]);

        $liabilities = Account::where('name', 'Liabilities')
            ->where('company_id', $companyId)
            ->first();

        if (!$liabilities) {
            Log::warning('RangeSuppliers: Liabilities root not found');
            return [];
        }

        $accountPayable = Account::where('root_id', $liabilities->id)
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('name', 'Accounts Payable')
                    ->orWhere('name', 'like', '%accounts payable%');
            })
            ->first();

        if (!$accountPayable) {
            Log::info('RangeSuppliers: Accounts Payable node not found under Liabilities', [
                'root_id' => $liabilities->id,
            ]);
            return [];
        }

        // Supplier TYPES (children of A/P that include supplier/suppliers in the name)
        $supplierTypes = Account::where('root_id', $liabilities->id)
            ->where('parent_id', $accountPayable->id)
            ->where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('name', 'like', '%supplier%')
                    ->orWhere('name', 'like', '%suppliers%');
            })
            ->orderBy('id')
            ->get(['id', 'name', 'parent_id']);

        Log::info('DailySuppliers: supplier types under A/P', [
            'count' => $supplierTypes->count(),
            'ids' => $supplierTypes->pluck('id')->take(30),
            'names' => $supplierTypes->pluck('name')->take(30),
        ]);

        if ($supplierTypes->isEmpty()) {
            Log::info('RangeSuppliers: no supplier types found');
            return [];
        }

        $groups = [];

        foreach ($supplierTypes as $typeNode) {

            // Suppliers under this type (L4). If none, treat type as supplier.
            $suppliers = Account::where('parent_id', $typeNode->id)
                ->where('company_id', $companyId)
                ->orderBy('id')
                ->get(['id', 'name', 'parent_id']);

            if ($suppliers->isEmpty()) {
                $suppliers = collect([$typeNode]);
            }

            $leafIds = collect();
            $leafToSupplier = [];
            $supplierById = $suppliers->keyBy('id');

            foreach ($suppliers as $sup) {
                $children = Account::where('parent_id', $sup->id)
                    ->where('company_id', $companyId)
                    ->get(['id', 'name', 'parent_id']);
                $postings = $children->isNotEmpty() ? $children : collect([$sup]);

                Log::info('RangeSuppliers: postings for supplier', [
                    'type_id' => $typeNode->id,
                    'type_name' => $typeNode->name,
                    'supplier_id' => $sup->id,
                    'supplier_name' => $sup->name,
                    'posting_ids' => $postings->pluck('id')->take(50),
                    'posting_names' => $postings->pluck('name')->take(50),
                    'posting_count' => $postings->count(),
                    'used_supplier_as_posting' => $children->isEmpty(),
                ]);

                foreach ($postings as $p) {
                    $leafIds->push($p->id);
                    $leafToSupplier[$p->id] = $sup->id;
                }
            }

            $leafIds = $leafIds->unique()->values();
            if ($leafIds->isEmpty()) {
                continue;
            }

            // Journal entries for the range, scoped to this company (and optional agent)
            $jeQuery = JournalEntry::with([
                'account:id,name,parent_id',
                'transaction:id,transaction_date',
                'task:id,reference,client_id,supplier_pay_date,issued_date,agent_id',
                'task.client:id,name',
                'task.agent.branch.company:id' // for scoping
            ])
                ->whereIn('account_id', $leafIds)
                ->where('company_id', $companyId)
                ->whereBetween('transaction_date', [$from, $to])
                ->whereHas('task.agent.branch.company', fn($q) => $q->where('id', $companyId));

            if (!empty($agentIds)) {
                $jeQuery->whereHas('task', fn($q) => $q->whereIn('agent_id', $agentIds));
            }

            $jeRange = $jeQuery->orderBy('transaction_date')->get();

            if ($jeRange->isEmpty()) {
                continue;
            }

            // Aggregate by supplier
            $rows = [];
            $groupTotals = [
                'totalTasks' => 0,
                'totalTaskPrice' => 0.0,
                'paid' => 0.0,
                'unpaid' => 0.0,
            ];

            // supplier_id => entries
            $entriesBySupplier = [];
            foreach ($jeRange as $e) {
                $postingId  = $e->account_id;
                $supplierId = $leafToSupplier[$postingId] ?? null;
                if ($supplierId) {
                    $entriesBySupplier[$supplierId][] = $e;
                }
            }

            foreach ($entriesBySupplier as $supplierId => $entries) {
                $supplierNode = $supplierById->get($supplierId);
                if (!$supplierNode) continue;

                // Split entries by posting account
                $byAccount = collect($entries)->groupBy('account_id');

                $accountRows = [];
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
                            'running_balance' => $row->balance,
                        ];
                    }

                    $accountRows[] = [
                        'account' => $accObj ? $accObj->only(['id', 'name', 'parent_id']) : ['id' => $accId, 'name' => '—', 'parent_id' => null],
                        'debit'   => $debit,
                        'credit'  => $credit,
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
                $groups[$typeNode->name] = [
                    'totals' => [
                        'totalTasks' => $groupTotals['totalTasks'],
                        'totalTaskPrice' => $groupTotals['totalTaskPrice'],
                        'paid' => $groupTotals['paid'],
                        'unpaid' => $groupTotals['unpaid'],
                    ],
                    'rows' => $rows,
                ];
            }
        }

        Log::info('RangeSuppliers: finished', [
            'group_count' => count($groups),
            'group_names' => array_keys($groups),
        ]);

        return $groups;
    }

    private function rangeSalesRefunds(int $companyId, Carbon $from, Carbon $to, $agentIds)
    {
        return Refund::with([
            'invoice.agent.branch.company',
            'invoice.client',
            'refundDetails.task.agent.branch.company',
            'refundDetails.task.originalTask.invoiceDetail.invoice',
        ])
            ->whereBetween('refund_date', [$from, $to])
            ->where(function ($q) use ($companyId, $agentIds) {
                $q->whereHas('invoice.agent.branch.company', fn($q) => $q->where('id', $companyId))
                    ->orWhereHas('refundDetails.task.agent.branch.company', fn($q) => $q->where('id', $companyId));

                if (!empty($agentIds)) {
                    $q->where(function ($qq) use ($agentIds) {
                        $qq->whereHas('invoice', fn($q) => $q->whereIn('agent_id', $agentIds))
                            ->orWhereHas('refundDetails.task', fn($q) => $q->whereIn('agent_id', $agentIds));
                    });
                }
            })
            ->get()
            ->map(function ($refund) {
                $firstDetail = $refund->refundDetails->first();
                $original = $firstDetail?->task?->originalTask?->invoiceDetail?->invoice;
                $refundInv = $refund->invoice;

                $refund->refund_type = $original?->status === 'paid' ? 'Credit to Client' : 'Client Owes';
                $refund->original_invoice_number = $original?->invoice_number;
                $refund->original_invoice_status = $original?->status;
                $refund->refund_invoice_number = $refundInv?->invoice_number;
                $refund->refund_invoice_status = $refundInv?->status;

                $refund->links = [
                    'view_refund' => route('refunds.edit', ['refund' => $refund->id]),
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
        $profitTotal = 0.0;
        $invoiceProfit = [];

        // Step 1: Sum stored profit and commission from invoice_details
        foreach ($invoices as $invoice) {
            $invProfit = $invoice->invoiceDetails->sum('profit');
            $invoiceProfit[$invoice->id] = $invProfit;
            $profitTotal += $invProfit;
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
                foreach ($invoices as $invoice) {
                    $invComm = $invoice->invoiceDetails->sum('commission');
                    $invoice->computed_commission = $invComm;
                    $commissionTotal += $invComm;
                }
                break;

            case 3: // Both-A: stored commission + salary
                foreach ($invoices as $invoice) {
                    $invComm = $invoice->invoiceDetails->sum('commission');
                    $invoice->computed_commission = $invComm; // Rate part only per invoice
                    $commissionTotal += $invComm;
                }
                $commissionTotal += $salary; // Salary added to total only
                break;

            case 4: // Both-B: (profit - salary) × rate + salary, only if profit > target
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
        ];
    }

    public function tasksReport(Request $request)
    {
        $request->validate([
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'integer',
            'statuses' => 'nullable|array',
            'statuses.*' => 'string',
            'issued_by' => 'nullable|array',
            'issued_by.*' => 'string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'date_preset' => 'nullable|string',
        ]);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $supplierIds = $request->input('supplier_ids', []);
        $statuses = $request->input('statuses', []);
        $issuedBy = $request->input('issued_by', []);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $datePreset = $request->input('date_preset');

        if (empty($statuses)) {
            $statuses = ['issued', 'reissued', 'refund', 'payment_voucher'];
        }

        if ($datePreset && !$dateFrom && !$dateTo) {
            $now = Carbon::now();
            switch ($datePreset) {
                case 'this_week':
                    $dateFrom = $now->startOfWeek()->toDateString();
                    $dateTo = $now->endOfWeek()->toDateString();
                    break;
                case 'this_month':
                    $dateFrom = $now->startOfMonth()->toDateString();
                    $dateTo = $now->endOfMonth()->toDateString();
                    break;
                case 'this_year':
                    $dateFrom = $now->startOfYear()->toDateString();
                    $dateTo = $now->endOfYear()->toDateString();
                    break;
                case 'january':
                    $dateFrom = Carbon::create($now->year, 1, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 1, 1)->endOfMonth()->toDateString();
                    break;
                case 'february':
                    $dateFrom = Carbon::create($now->year, 2, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 2, 1)->endOfMonth()->toDateString();
                    break;
                case 'march':
                    $dateFrom = Carbon::create($now->year, 3, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 3, 1)->endOfMonth()->toDateString();
                    break;
                case 'april':
                    $dateFrom = Carbon::create($now->year, 4, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 4, 1)->endOfMonth()->toDateString();
                    break;
                case 'may':
                    $dateFrom = Carbon::create($now->year, 5, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 5, 1)->endOfMonth()->toDateString();
                    break;
                case 'june':
                    $dateFrom = Carbon::create($now->year, 6, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 6, 1)->endOfMonth()->toDateString();
                    break;
                case 'july':
                    $dateFrom = Carbon::create($now->year, 7, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 7, 1)->endOfMonth()->toDateString();
                    break;
                case 'august':
                    $dateFrom = Carbon::create($now->year, 8, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 8, 1)->endOfMonth()->toDateString();
                    break;
                case 'september':
                    $dateFrom = Carbon::create($now->year, 9, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 9, 1)->endOfMonth()->toDateString();
                    break;
                case 'october':
                    $dateFrom = Carbon::create($now->year, 10, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 10, 1)->endOfMonth()->toDateString();
                    break;
                case 'november':
                    $dateFrom = Carbon::create($now->year, 11, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 11, 1)->endOfMonth()->toDateString();
                    break;
                case 'december':
                    $dateFrom = Carbon::create($now->year, 12, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 12, 1)->endOfMonth()->toDateString();
                    break;
            }
        }

        // Check if payment_voucher is included
        $includePaymentVouchers = in_array('payment_voucher', $statuses);

        // Remove payment_voucher from task statuses filter
        $taskStatuses = array_diff($statuses, ['payment_voucher']);

        // Check if void/confirmed were selected
        $voidSelected = in_array('void', $taskStatuses);
        $confirmedSelected = in_array('confirmed', $taskStatuses);

        $mainStatuses = array_diff($taskStatuses, ['void', 'confirmed']);

        try {
            $allTasks = collect();

            if (!empty($mainStatuses)) {
                $taskQuery = Task::with(['supplier', 'agent', 'client'])
                    ->whereIn('status', $mainStatuses);

                if ($companyId) {
                    $taskQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
                }

                if (!empty($supplierIds) && is_array($supplierIds)) {
                    $taskQuery->whereIn('supplier_id', $supplierIds);
                }

                if (!empty($issuedBy) && is_array($issuedBy)) {
                    $taskQuery->whereIn('issued_by', $issuedBy);
                }

                if ($dateFrom) {
                    $taskQuery->whereDate('supplier_pay_date', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $taskQuery->whereDate('supplier_pay_date', '<=', $dateTo);
                }

                // Apply void exclusion (hide issued/reissued that have matching void)
                // Skip if void is selected
                if (!$voidSelected) {
                    $voidQuery = Task::where('status', 'void');

                    if ($companyId) {
                        $voidQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
                    }

                    if (!empty($supplierIds) && is_array($supplierIds)) {
                        $voidQuery->whereIn('supplier_id', $supplierIds);
                    }

                    if (!empty($issuedBy) && is_array($issuedBy)) {
                        $voidQuery->whereIn('issued_by', $issuedBy);
                    }

                    $voidedTaskReferences = $voidQuery->pluck('reference')->toArray();

                    if (!empty($voidedTaskReferences)) {
                        $taskQuery->whereNotIn('reference', $voidedTaskReferences);
                    }
                }

                // Apply confirmed exclusion (hide issued/reissued that have matching confirmed)
                // Skip if confirmed is selected
                if (!$confirmedSelected) {
                    $confirmedQuery = Task::where('status', 'confirmed');

                    if ($companyId) {
                        $confirmedQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
                    }

                    if (!empty($supplierIds) && is_array($supplierIds)) {
                        $confirmedQuery->whereIn('supplier_id', $supplierIds);
                    }

                    if (!empty($issuedBy) && is_array($issuedBy)) {
                        $confirmedQuery->whereIn('issued_by', $issuedBy);
                    }

                    $confirmedTaskReferences = $confirmedQuery->pluck('reference')->toArray();

                    if (!empty($confirmedTaskReferences)) {
                        $taskQuery->whereNotIn('reference', $confirmedTaskReferences);
                    }
                }

                $allTasks = $taskQuery->get();
            }

            $issuedReissuedQuery = Task::whereIn('status', ['issued', 'reissued']);

            if ($companyId) {
                $issuedReissuedQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
            }

            $issuedReissuedReferences = $issuedReissuedQuery
                ->when(!empty($supplierIds), fn($q) => $q->whereIn('supplier_id', $supplierIds))
                ->when(!empty($issuedBy), fn($q) => $q->whereIn('issued_by', $issuedBy))
                ->pluck('reference')
                ->toArray();

            if ($voidSelected) {
                $voidOnlyQuery = Task::with(['supplier', 'agent', 'client'])
                    ->where('status', 'void')
                    ->whereNotIn('reference', $issuedReissuedReferences);

                if ($companyId) {
                    $voidOnlyQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
                }

                if (!empty($supplierIds) && is_array($supplierIds)) {
                    $voidOnlyQuery->whereIn('supplier_id', $supplierIds);
                }

                if (!empty($issuedBy) && is_array($issuedBy)) {
                    $voidOnlyQuery->whereIn('issued_by', $issuedBy);
                }

                if ($dateFrom) {
                    $voidOnlyQuery->whereDate('supplier_pay_date', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $voidOnlyQuery->whereDate('supplier_pay_date', '<=', $dateTo);
                }

                $voidOnlyTasks = $voidOnlyQuery->get();
                $allTasks = $allTasks->merge($voidOnlyTasks);
            }

            if ($confirmedSelected) {
                $confirmedOnlyQuery = Task::with(['supplier', 'agent', 'client'])
                    ->where('status', 'confirmed')
                    ->whereNotIn('reference', $issuedReissuedReferences);

                if ($companyId) {
                    $confirmedOnlyQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
                }

                if (!empty($supplierIds) && is_array($supplierIds)) {
                    $confirmedOnlyQuery->whereIn('supplier_id', $supplierIds);
                }

                if (!empty($issuedBy) && is_array($issuedBy)) {
                    $confirmedOnlyQuery->whereIn('issued_by', $issuedBy);
                }

                if ($dateFrom) {
                    $confirmedOnlyQuery->whereDate('supplier_pay_date', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $confirmedOnlyQuery->whereDate('supplier_pay_date', '<=', $dateTo);
                }

                $confirmedOnlyTasks = $confirmedOnlyQuery->get();
                $allTasks = $allTasks->merge($confirmedOnlyTasks);
            }
        } catch (Exception $e) {
            Log::info('Error building task query', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error building task query',
            ], 400);
        }

        $mergedData = collect();

        foreach ($allTasks as $task) {
            $debit = 0;
            $credit = 0;

            if ($task->status === 'refund') {
                $credit = $task->total ?? 0;
            } else {
                $debit = ($task->price ?? 0) + ($task->tax ?? 0) + ($task->supplier_surcharge ?? 0);
            }

            $mergedData->push((object)[
                'type' => 'task',
                'date' => $task->supplier_pay_date,
                'reference' => $task->reference,
                'original_reference' => $task->original_reference,
                'passenger_name' => $task->passenger_name,
                'supplier_name' => $task->supplier->name ?? 'N/A',
                'status' => $task->status,
                'debit' => $debit,
                'credit' => $credit,
            ]);
        }

        if ($includePaymentVouchers) {
            try {
                $transactionQuery = Transaction::where('reference_number', 'LIKE', 'PV-%');

                if ($companyId) {
                    $transactionQuery->where('company_id', $companyId);
                }

                if ($dateFrom) {
                    $transactionQuery->whereDate('transaction_date', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $transactionQuery->whereDate('transaction_date', '<=', $dateTo);
                }

                $allTransactions = $transactionQuery->get();

                foreach ($allTransactions as $transaction) {
                    $mergedData->push((object)[
                        'type' => 'transaction',
                        'date' => $transaction->transaction_date,
                        'reference' => $transaction->reference_number,
                        'original_reference' => null,
                        'passenger_name' => $transaction->name . ($transaction->description ? ' - ' . $transaction->description : ''),
                        'supplier_name' => 'Payment Voucher',
                        'status' => 'payment_voucher',
                        'debit' => 0,
                        'credit' => $transaction->amount ?? 0,
                    ]);
                }
            } catch (Exception $e) {
                Log::info('Error fetching PV transactions', ['error' => $e->getMessage()]);
            }
        }

        // Sort by date, then by reference
        $mergedData = $mergedData->sortBy([
            ['date', 'asc'],
            ['reference', 'asc'],
        ])->values();

        // Calculate totals
        $totalTasks = $allTasks->count();
        $totalDebit = $mergedData->sum('debit');
        $totalCredit = $mergedData->sum('credit');
        $netBalance = $totalDebit - $totalCredit;

        // Manual pagination
        $perPage = 20;
        $currentPage = request()->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        $paginatedData = $mergedData->slice($offset, $perPage)->values();

        $tasks = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedData,
            $mergedData->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);

        $availableStatusesQuery = Task::select('status')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status');

        if ($companyId) {
            $availableStatusesQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
        }

        $availableStatuses = $availableStatusesQuery->pluck('status')->toArray();
        $availableStatuses[] = 'payment_voucher';

        $availableIssuedByQuery = Task::select('issued_by')
            ->whereNotNull('issued_by')
            ->distinct()
            ->orderBy('issued_by');

        if ($companyId) {
            $availableIssuedByQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
        }

        $availableIssuedBy = $availableIssuedByQuery->pluck('issued_by')->toArray();

        return view('reports.tasks', compact(
            'tasks',
            'totalTasks',
            'suppliers',
            'availableStatuses',
            'availableIssuedBy',
            'supplierIds',
            'statuses',
            'issuedBy',
            'netBalance',
            'totalDebit',
            'totalCredit',
            'dateFrom',
            'dateTo',
            'datePreset'
        ));
    }

    public function tasksReportPdf(Request $request)
    {
        $request->validate([
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'integer',
            'statuses' => 'nullable|array',
            'statuses.*' => 'string',
            'issued_by' => 'nullable|array',
            'issued_by.*' => 'string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'date_preset' => 'nullable|string',
        ]);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $supplierIds = $request->input('supplier_ids', []);
        $statuses = $request->input('statuses', []);
        $issuedBy = $request->input('issued_by', []);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $datePreset = $request->input('date_preset');

        if (empty($statuses)) {
            $statuses = ['issued', 'reissued', 'refund', 'payment_voucher'];
        }

        if ($datePreset && !$dateFrom && !$dateTo) {
            $now = Carbon::now();
            switch ($datePreset) {
                case 'this_week':
                    $dateFrom = $now->startOfWeek()->toDateString();
                    $dateTo = $now->endOfWeek()->toDateString();
                    break;
                case 'this_month':
                    $dateFrom = $now->startOfMonth()->toDateString();
                    $dateTo = $now->endOfMonth()->toDateString();
                    break;
                case 'this_year':
                    $dateFrom = $now->startOfYear()->toDateString();
                    $dateTo = $now->endOfYear()->toDateString();
                    break;
                case 'january':
                    $dateFrom = Carbon::create($now->year, 1, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 1, 1)->endOfMonth()->toDateString();
                    break;
                case 'february':
                    $dateFrom = Carbon::create($now->year, 2, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 2, 1)->endOfMonth()->toDateString();
                    break;
                case 'march':
                    $dateFrom = Carbon::create($now->year, 3, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 3, 1)->endOfMonth()->toDateString();
                    break;
                case 'april':
                    $dateFrom = Carbon::create($now->year, 4, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 4, 1)->endOfMonth()->toDateString();
                    break;
                case 'may':
                    $dateFrom = Carbon::create($now->year, 5, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 5, 1)->endOfMonth()->toDateString();
                    break;
                case 'june':
                    $dateFrom = Carbon::create($now->year, 6, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 6, 1)->endOfMonth()->toDateString();
                    break;
                case 'july':
                    $dateFrom = Carbon::create($now->year, 7, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 7, 1)->endOfMonth()->toDateString();
                    break;
                case 'august':
                    $dateFrom = Carbon::create($now->year, 8, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 8, 1)->endOfMonth()->toDateString();
                    break;
                case 'september':
                    $dateFrom = Carbon::create($now->year, 9, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 9, 1)->endOfMonth()->toDateString();
                    break;
                case 'october':
                    $dateFrom = Carbon::create($now->year, 10, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 10, 1)->endOfMonth()->toDateString();
                    break;
                case 'november':
                    $dateFrom = Carbon::create($now->year, 11, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 11, 1)->endOfMonth()->toDateString();
                    break;
                case 'december':
                    $dateFrom = Carbon::create($now->year, 12, 1)->toDateString();
                    $dateTo = Carbon::create($now->year, 12, 1)->endOfMonth()->toDateString();
                    break;
            }
        }

        // Check if payment_voucher is included
        $includePaymentVouchers = in_array('payment_voucher', $statuses);

        // Remove payment_voucher from task statuses filter
        $taskStatuses = array_diff($statuses, ['payment_voucher']);

        $voidSelected = in_array('void', $taskStatuses);
        $confirmedSelected = in_array('confirmed', $taskStatuses);

        $mainStatuses = array_diff($taskStatuses, ['void', 'confirmed']);

        $mergedData = collect();

        try {
            $allTasks = collect();

            if (!empty($mainStatuses)) {
                $taskQuery = Task::with(['supplier', 'agent', 'client'])
                    ->whereIn('status', $mainStatuses);

                if ($companyId) {
                    $taskQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
                }

                if (!empty($supplierIds) && is_array($supplierIds)) {
                    $taskQuery->whereIn('supplier_id', $supplierIds);
                }

                if (!empty($issuedBy) && is_array($issuedBy)) {
                    $taskQuery->whereIn('issued_by', $issuedBy);
                }

                if ($dateFrom) {
                    $taskQuery->whereDate('supplier_pay_date', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $taskQuery->whereDate('supplier_pay_date', '<=', $dateTo);
                }

                // Apply void exclusion (hide issued/reissued that have matching void)
                // Skip if void is selected
                if (!$voidSelected) {
                    $voidQuery = Task::where('status', 'void');

                    if ($companyId) {
                        $voidQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
                    }

                    if (!empty($supplierIds) && is_array($supplierIds)) {
                        $voidQuery->whereIn('supplier_id', $supplierIds);
                    }

                    if (!empty($issuedBy) && is_array($issuedBy)) {
                        $voidQuery->whereIn('issued_by', $issuedBy);
                    }

                    $voidedTaskReferences = $voidQuery->pluck('reference')->toArray();

                    if (!empty($voidedTaskReferences)) {
                        $taskQuery->whereNotIn('reference', $voidedTaskReferences);
                    }
                }

                // Apply confirmed exclusion (hide issued/reissued that have matching confirmed)
                // Skip if confirmed is selected
                if (!$confirmedSelected) {
                    $confirmedQuery = Task::where('status', 'confirmed');

                    if ($companyId) {
                        $confirmedQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
                    }

                    if (!empty($supplierIds) && is_array($supplierIds)) {
                        $confirmedQuery->whereIn('supplier_id', $supplierIds);
                    }

                    if (!empty($issuedBy) && is_array($issuedBy)) {
                        $confirmedQuery->whereIn('issued_by', $issuedBy);
                    }

                    $confirmedTaskReferences = $confirmedQuery->pluck('reference')->toArray();

                    if (!empty($confirmedTaskReferences)) {
                        $taskQuery->whereNotIn('reference', $confirmedTaskReferences);
                    }
                }

                $allTasks = $taskQuery->get();
            }

            $issuedReissuedQuery = Task::whereIn('status', ['issued', 'reissued']);

            if ($companyId) {
                $issuedReissuedQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
            }

            $issuedReissuedReferences = $issuedReissuedQuery
                ->when(!empty($supplierIds), fn($q) => $q->whereIn('supplier_id', $supplierIds))
                ->when(!empty($issuedBy), fn($q) => $q->whereIn('issued_by', $issuedBy))
                ->pluck('reference')
                ->toArray();

            if ($voidSelected) {
                $voidOnlyQuery = Task::with(['supplier', 'agent', 'client'])
                    ->where('status', 'void')
                    ->whereNotIn('reference', $issuedReissuedReferences);

                if ($companyId) {
                    $voidOnlyQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
                }

                if (!empty($supplierIds) && is_array($supplierIds)) {
                    $voidOnlyQuery->whereIn('supplier_id', $supplierIds);
                }

                if (!empty($issuedBy) && is_array($issuedBy)) {
                    $voidOnlyQuery->whereIn('issued_by', $issuedBy);
                }

                if ($dateFrom) {
                    $voidOnlyQuery->whereDate('supplier_pay_date', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $voidOnlyQuery->whereDate('supplier_pay_date', '<=', $dateTo);
                }

                $voidOnlyTasks = $voidOnlyQuery->get();
                $allTasks = $allTasks->merge($voidOnlyTasks);
            }

            if ($confirmedSelected) {
                $confirmedOnlyQuery = Task::with(['supplier', 'agent', 'client'])
                    ->where('status', 'confirmed')
                    ->whereNotIn('reference', $issuedReissuedReferences);

                if ($companyId) {
                    $confirmedOnlyQuery->whereHas('agent.branch.company', fn($q) => $q->where('id', $companyId));
                }

                if (!empty($supplierIds) && is_array($supplierIds)) {
                    $confirmedOnlyQuery->whereIn('supplier_id', $supplierIds);
                }

                if (!empty($issuedBy) && is_array($issuedBy)) {
                    $confirmedOnlyQuery->whereIn('issued_by', $issuedBy);
                }

                if ($dateFrom) {
                    $confirmedOnlyQuery->whereDate('supplier_pay_date', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $confirmedOnlyQuery->whereDate('supplier_pay_date', '<=', $dateTo);
                }

                $confirmedOnlyTasks = $confirmedOnlyQuery->get();
                $allTasks = $allTasks->merge($confirmedOnlyTasks);
            }

            foreach ($allTasks as $task) {
                $debit = 0;
                $credit = 0;

                if ($task->status === 'refund') {
                    $credit = $task->total ?? 0;
                } else {
                    $debit = ($task->price ?? 0) + ($task->tax ?? 0) + ($task->supplier_surcharge ?? 0);
                }

                $mergedData->push((object)[
                    'type' => 'task',
                    'date' => $task->supplier_pay_date,
                    'reference' => $task->reference,
                    'original_reference' => $task->original_reference,
                    'passenger_name' => $task->passenger_name,
                    'supplier_name' => $task->supplier->name ?? 'N/A',
                    'agent_name' => $task->agent->name ?? 'N/A',
                    'issued_by' => $task->issued_by,
                    'status' => $task->status,
                    'debit' => $debit,
                    'credit' => $credit,
                ]);
            }
        } catch (Exception $e) {
            Log::info('Error building task query for PDF', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Error generating PDF');
        }

        if ($includePaymentVouchers) {
            try {
                $transactionQuery = Transaction::where('reference_number', 'LIKE', 'PV-%');

                if ($companyId) {
                    $transactionQuery->where('company_id', $companyId);
                }

                if ($dateFrom) {
                    $transactionQuery->whereDate('transaction_date', '>=', $dateFrom);
                }

                if ($dateTo) {
                    $transactionQuery->whereDate('transaction_date', '<=', $dateTo);
                }

                $allTransactions = $transactionQuery->get();

                foreach ($allTransactions as $transaction) {
                    $mergedData->push((object)[
                        'type' => 'transaction',
                        'date' => $transaction->transaction_date,
                        'reference' => $transaction->reference_number,
                        'original_reference' => null,
                        'passenger_name' => $transaction->name . ($transaction->description ? ' - ' . $transaction->description : ''),
                        'supplier_name' => 'Payment Voucher',
                        'agent_name' => 'N/A',
                        'issued_by' => 'N/A',
                        'status' => 'payment_voucher',
                        'debit' => 0,
                        'credit' => $transaction->amount ?? 0,
                    ]);
                }
            } catch (Exception $e) {
                Log::info('Error fetching PV transactions for PDF', ['error' => $e->getMessage()]);
            }
        }

        // Sort by date, then by reference
        $mergedData = $mergedData->sortBy([
            ['date', 'asc'],
            ['reference', 'asc'],
        ])->values();

        // Calculate totals
        $totalTasks = $allTasks->count();
        $totalDebit = $mergedData->sum('debit');
        $totalCredit = $mergedData->sum('credit');
        $netBalance = $totalDebit - $totalCredit;

        $pdf = Pdf::loadView('reports.pdf.tasks', [
            'tasks' => $mergedData,
            'totalTasks' => $totalTasks,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'netBalance' => $netBalance,
            'generatedAt' => now()->format('M d, Y H:i:s'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions(['defaultFont' => 'sans-serif']);

        $filename = 'tasks-report-' . now()->format('Y-m-d-His') . '.pdf';
        return $pdf->download($filename);
    }
}
