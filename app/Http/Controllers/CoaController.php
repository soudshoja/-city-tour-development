<?php

namespace App\Http\Controllers;

use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Agent;
use App\Models\Client;
use App\Models\Account;
use App\Models\Branch;
use App\Models\CoaCategory;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Enums\CoaLabel;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class CoaController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', CoaCategory::class);

        $user = Auth::user();

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT])) {
            return abort(403, 'Unauthorized action.');
        }

        $companyId = getCompanyId($user);
        if (!$companyId) {
            return redirect()->route('dashboard')->with('error', 'Please select a company first.');
        }

        $company = Company::find($companyId);
        if (!$company) {
            return redirect()->route('dashboard')->with('error', 'Company not found.');
        }

        $agents = $company->agents()->select('agents.id', 'agents.name')->get();
        $agentIds = $agents->pluck('id')->toArray();

        $clients = Client::whereIn('agent_id', $agentIds)
            ->select('id', 'name')
            ->get();

        $branches = Branch::where('company_id', $companyId)
            ->select('id', 'name')
            ->get();

        // Load all company accounts in a single query
        $allAccounts = Account::where('company_id', $companyId)->get()->keyBy('id');

        foreach ($allAccounts as $acct) {
            if ($acct->root_id && $allAccounts->has($acct->root_id)) {
                $acct->setRelation('root', $allAccounts->get($acct->root_id));
            }
        }

        // Build parent->children map in PHP (eliminates N+1)
        $childrenMap = [];
        foreach ($allAccounts as $acct) {
            if ($acct->parent_id !== null) {
                $childrenMap[$acct->parent_id][] = $acct;
            }
        }

        // Single aggregate query: debit/credit sums + count per account
        $accountIds = $allAccounts->pluck('id');
        $journalAggregates = DB::table('journal_entries')
            ->whereNull('deleted_at')
            ->whereIn('account_id', $accountIds)
            ->groupBy('account_id')
            ->select(
                'account_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit'),
                DB::raw('COUNT(*) as entry_count')
            )
            ->get()
            ->keyBy('account_id');

        // Currency-specific aggregates for non-KWD accounts only
        $nonKwdAccountIds = $allAccounts->filter(fn($a) => $a->currency !== null && $a->currency !== 'KWD')->pluck('id');

        $currencyAggregates = collect();
        if ($nonKwdAccountIds->isNotEmpty()) {
            $currencyAggregates = DB::table('journal_entries')
                ->whereNull('deleted_at')
                ->whereIn('account_id', $nonKwdAccountIds)
                ->whereNotNull('original_currency')
                ->groupBy('account_id', 'original_currency')
                ->select(
                    'account_id',
                    'original_currency',
                    DB::raw('SUM(CASE WHEN debit > 0 THEN original_amount ELSE 0 END) as original_debit'),
                    DB::raw('SUM(CASE WHEN credit > 0 THEN original_amount ELSE 0 END) as original_credit')
                )
                ->get()
                ->groupBy('account_id');
        }

        $rootConfig = [
            'Assets'      => 'normal',
            'Liabilities' => 'reverse',
            'Income'      => 'reverse',
            'Expenses'    => 'normal',
            'Equity'      => 'reverse',
        ];

        $rootAccounts = $allAccounts->filter(fn($a) => $a->parent_id === null);

        $assets = $liabilities = $incomes = $expenses = $equities = null;

        foreach ($rootConfig as $rootName => $debitCreditType) {
            $rootAccount = $rootAccounts->firstWhere('name', $rootName);
            if ($rootAccount) {
                $this->buildAccountTree($rootAccount, $childrenMap, $journalAggregates, $currencyAggregates, $debitCreditType);
            }
            match ($rootName) {
                'Assets'      => $assets = $rootAccount,
                'Liabilities' => $liabilities = $rootAccount,
                'Income'      => $incomes = $rootAccount,
                'Expenses'    => $expenses = $rootAccount,
                'Equity'      => $equities = $rootAccount,
            };
        }

        return view('coa.index', [
            'assets'      => $assets,
            'liabilities' => $liabilities,
            'incomes'     => $incomes,
            'expenses'    => $expenses,
            'equities'    => $equities,
            'clients'     => $clients,
            'branches'    => $branches,
            'agents'      => $agents,
            'labelType'   => CoaLabel::cases(),
        ]);
    }

    public function addCategory(Request $request)
    {
        if (Auth::user()->company == null) {
            return response()->json(['success' => false, 'message' => 'User not authorized'], 404);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'label' => 'nullable|in:' . implode(',', CoaLabel::getValues()),
            'level' => 'required|integer',
            'root_id' => 'required|integer',
            'parent_id' => 'required|integer',
            'entity' => 'nullable| enum: client, agent, branch',
            'client' => 'required_if:entity,client|integer',
            'agent' => 'required_if:entity,agent|integer',
            'branch' => 'required_if:entity,branch|integer',
            // 'account_type' => 'required|string|max:255',
            // 'budget_balance' => 'required|numeric',
            // 'actual_balance' => 'required|numeric',
            // 'variance' => 'required|numeric',
        ]);

        $existingCode = Account::where('code', $request->code)->first();

        if ($existingCode) {
            return redirect()->back()->with('error', 'Code already exists');
        }

        try {
            $category = new Account();
            $category->name = $request->name;
            $category->code = $request->code;
            $category->label = $request->label;
            $category->level = $request->level;
            $category->parent_id = $request->parent_id;
            $category->variance = 0;
            $category->budget_balance = 0;
            $category->actual_balance = 0;
            $category->company_id = Auth::user()->company->id;
            $category->root_id  = $request->root_id;

            $category->save();
        } catch (Exception $e) {

            logger('Error creating category: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Error creating category');
        }

        return redirect()->back()->with('success', 'Category created successfully');
    }

    public function childAccount($account, $debitCreditType = 'normal')
    {
        $childAccounts = Account::where('parent_id', $account->id)->get();

        $totalDebit = 0;
        $totalCredit = 0;
        $originalCurrencyTotals = []; // Track totals by original currency

        // Track excluded amounts separately for reporting purposes
        $excludedPaymentDebit = 0;
        $excludedPaymentCredit = 0;

        if ($childAccounts->isNotEmpty()) {
            $account->childAccounts = $childAccounts;

            foreach ($childAccounts as $child) {
                $this->childAccount($child, $debitCreditType); // Recursively get child accounts

                // Account dimension inclusion logic:
                // - 'both': Always included in parent totals (traditional accounts)
                // - 'service': Include in parent totals (service consumption affects expenses)
                // - 'payment': Exclude from parent totals (prevents double-counting liabilities)
                if (($child->account_dimension ?? 'both') !== 'payment') {
                    $totalDebit += $child->debit ?? 0;
                    $totalCredit += $child->credit ?? 0;
                } else {
                    // Store separate tracking for payment accounts (excluded from parent)
                    $child->excluded_from_parent = true;

                    // Track excluded payment amounts for reporting
                    $excludedPaymentDebit += $child->debit ?? 0;
                    $excludedPaymentCredit += $child->credit ?? 0;
                }

                // Also accumulate excluded amounts from deeper levels (grandchildren, etc.)
                if (isset($child->excluded_payment_debit) && $child->excluded_payment_debit > 0) {
                    $excludedPaymentDebit += $child->excluded_payment_debit;
                }
                if (isset($child->excluded_payment_credit) && $child->excluded_payment_credit > 0) {
                    $excludedPaymentCredit += $child->excluded_payment_credit;
                }

                // Aggregate original currency totals if child has them
                if (isset($child->original_currency) && $child->original_currency !== 'KWD') {
                    $currency = $child->original_currency;

                    if (!isset($originalCurrencyTotals[$currency])) {
                        $originalCurrencyTotals[$currency] = [
                            'debit' => 0,
                            'credit' => 0,
                            'balance' => 0
                        ];
                    }

                    $originalCurrencyTotals[$currency]['debit'] += $child->original_debit ?? 0;
                    $originalCurrencyTotals[$currency]['credit'] += $child->original_credit ?? 0;
                    $originalCurrencyTotals[$currency]['balance'] += $child->original_balance ?? 0;
                }
            }

            // Assign the summed debit and credit to the parent account (in KWD)
            $account->debit = (string)$totalDebit;
            $account->credit = (string)$totalCredit;

            // Store excluded amounts for display purposes (only payment accounts excluded)
            $account->excluded_payment_debit = (string)$excludedPaymentDebit;
            $account->excluded_payment_credit = (string)$excludedPaymentCredit;

            // Store original currency totals if any exist
            if (!empty($originalCurrencyTotals)) {
                $account->original_currency_totals = $originalCurrencyTotals;
            }

            if ($debitCreditType == 'normal') {
                $account->balance = bcsub($totalDebit, $totalCredit, 2);
                $account->excluded_payment_balance = bcsub($excludedPaymentDebit, $excludedPaymentCredit, 2);
            } else if ($debitCreditType == 'reverse') {
                $account->balance = bcsub($totalCredit, $totalDebit, 2);
                $account->excluded_payment_balance = bcsub($excludedPaymentCredit, $excludedPaymentDebit, 2);
            } else {
                throw new Exception('Invalid debitCreditType');
            }
        } else {
            // If it's the last level, calculate debit and credit from journal entries
            $journalEntries = JournalEntry::with('transaction')->where('account_id', $account->id)->get();

            // Handle currency conversion if account currency is not KWD
            if ($account->currency !== null && $account->currency !== 'KWD') {
                // Calculate original currency totals from journal entries that have original_currency data
                $originalDebit = $journalEntries->whereNotNull('original_currency')
                    ->where('original_currency', $account->currency)
                    ->where('debit', '>', 0)
                    ->sum('original_amount');

                $originalCredit = $journalEntries->whereNotNull('original_currency')
                    ->where('original_currency', $account->currency)
                    ->where('credit', '>', 0)
                    ->sum('original_amount');

                // Calculate KWD totals (converted amounts)
                $kwdDebit = $journalEntries->sum('debit');
                $kwdCredit = $journalEntries->sum('credit');

                // Store both original and converted amounts
                $account->original_currency = $account->currency;
                $account->original_debit = (string)$originalDebit;
                $account->original_credit = (string)$originalCredit;
                $account->original_balance = $debitCreditType == 'normal'
                    ? bcsub($originalDebit, $originalCredit, 2)
                    : bcsub($originalCredit, $originalDebit, 2);

                // Use KWD converted amounts for main calculations
                $debit = $kwdDebit;
                $credit = $kwdCredit;
            } else {
                // For KWD accounts, use regular calculation
                $debit = $journalEntries->sum('debit');
                $credit = $journalEntries->sum('credit');
            }

            $account->debit = (string)$debit;
            $account->credit = (string)$credit;

            $openingBalance = (float) ($account->opening_balance ?? 0);

            if ($debitCreditType == 'normal') {
                $movementBalance = bcsub($debit, $credit, 2);
            } else {
                $movementBalance = bcsub($credit, $debit, 2);
            }

            $account->balance = bcadd($openingBalance, $movementBalance, 2);
            $account->journalEntries = $journalEntries;
            $account->ledger = true;
        }

        // Only calculate dimensional data if specifically requested
        // For basic COA display, skip these expensive calculations
        // if($reportDimension === 'service'){
        //     $serviceTotal = $this->calculateServiceDimension($account);
        //     $account->service_total = $serviceTotal;
        // }

        // if($reportDimension === 'payment'){
        //     $paymentTotal = $this->calculatePaymentDimension($account);
        //     $account->payment_total = $paymentTotal;
        // }

        // // Only get linked accounts if we're doing dimensional reporting
        // if($reportDimension !== 'both'){
        //     $account->linked_accounts = $this->getLinkedAccounts($account);
        // }

        return $account; // Return the account with its childAccounts populated
    }

    /**
     * Build account tree using pre-loaded data (no additional DB queries).
     * Sets the same dynamic properties as childAccount() for view compatibility.
     */
    private function buildAccountTree(
        $account,
        array &$childrenMap,
        $journalAggregates,
        $currencyAggregates,
        string $debitCreditType = 'normal'
    ) {
        $children = $childrenMap[$account->id] ?? [];

        $totalDebit = 0;
        $totalCredit = 0;
        $originalCurrencyTotals = [];
        $excludedPaymentDebit = 0;
        $excludedPaymentCredit = 0;

        if (!empty($children)) {
            $account->childAccounts = collect($children);

            foreach ($children as $child) {
                $this->buildAccountTree($child, $childrenMap, $journalAggregates, $currencyAggregates, $debitCreditType);

                if (($child->account_dimension ?? 'both') !== 'payment') {
                    $totalDebit += $child->debit ?? 0;
                    $totalCredit += $child->credit ?? 0;
                } else {
                    $child->excluded_from_parent = true;
                    $excludedPaymentDebit += $child->debit ?? 0;
                    $excludedPaymentCredit += $child->credit ?? 0;
                }

                if (isset($child->excluded_payment_debit) && $child->excluded_payment_debit > 0) {
                    $excludedPaymentDebit += $child->excluded_payment_debit;
                }
                if (isset($child->excluded_payment_credit) && $child->excluded_payment_credit > 0) {
                    $excludedPaymentCredit += $child->excluded_payment_credit;
                }

                if (isset($child->original_currency) && $child->original_currency !== 'KWD') {
                    $currency = $child->original_currency;
                    if (!isset($originalCurrencyTotals[$currency])) {
                        $originalCurrencyTotals[$currency] = ['debit' => 0, 'credit' => 0, 'balance' => 0];
                    }
                    $originalCurrencyTotals[$currency]['debit'] += $child->original_debit ?? 0;
                    $originalCurrencyTotals[$currency]['credit'] += $child->original_credit ?? 0;
                    $originalCurrencyTotals[$currency]['balance'] += $child->original_balance ?? 0;
                }
            }

            $account->debit = (string) $totalDebit;
            $account->credit = (string) $totalCredit;
            $account->excluded_payment_debit = (string) $excludedPaymentDebit;
            $account->excluded_payment_credit = (string) $excludedPaymentCredit;

            if (!empty($originalCurrencyTotals)) {
                $account->original_currency_totals = $originalCurrencyTotals;
            }

            if ($debitCreditType == 'normal') {
                $account->balance = bcsub($totalDebit, $totalCredit, 2);
                $account->excluded_payment_balance = bcsub($excludedPaymentDebit, $excludedPaymentCredit, 2);
            } elseif ($debitCreditType == 'reverse') {
                $account->balance = bcsub($totalCredit, $totalDebit, 2);
                $account->excluded_payment_balance = bcsub($excludedPaymentCredit, $excludedPaymentDebit, 2);
            } else {
                throw new Exception('Invalid debitCreditType');
            }
        } else {
            // Leaf account: use pre-computed aggregates
            $aggregate = $journalAggregates->get($account->id);

            $debit = $aggregate ? (float) $aggregate->total_debit : 0;
            $credit = $aggregate ? (float) $aggregate->total_credit : 0;
            $entryCount = $aggregate ? (int) $aggregate->entry_count : 0;

            // Handle non-KWD currency accounts
            if ($account->currency !== null && $account->currency !== 'KWD') {
                $accountCurrencyAggs = $currencyAggregates->get($account->id, collect());
                $matchingCurrency = $accountCurrencyAggs->firstWhere('original_currency', $account->currency);

                $originalDebit = $matchingCurrency ? (float) $matchingCurrency->original_debit : 0;
                $originalCredit = $matchingCurrency ? (float) $matchingCurrency->original_credit : 0;

                $account->original_currency = $account->currency;
                $account->original_debit = (string) $originalDebit;
                $account->original_credit = (string) $originalCredit;
                $account->original_balance = $debitCreditType == 'normal'
                    ? bcsub($originalDebit, $originalCredit, 2)
                    : bcsub($originalCredit, $originalDebit, 2);
            }

            $account->debit = (string) $debit;
            $account->credit = (string) $credit;

            $openingBalance = (float) ($account->opening_balance ?? 0);

            if ($debitCreditType == 'normal') {
                $movementBalance = bcsub($debit, $credit, 2);
            } else {
                $movementBalance = bcsub($credit, $debit, 2);
            }

            $account->balance = bcadd($openingBalance, $movementBalance, 2);
            $account->ledger = true;

            // View only checks ->count() > 0 for the Amadeus delegate button
            $account->journalEntries = $entryCount > 0 ? collect([true]) : collect();
        }

        return $account;
    }

    // create accounts
    public function createAccounts(Request $request)
    {
        // Allowed account types in lowercase for validation
        $allowedTypes = ['assets', 'liabilities', 'income', 'expenses'];

        // Validate the incoming request, allowing case-insensitive matching for 'type'
        $request->validate([
            'accountName' => 'required|string|max:255',
            'type' => ['required', 'string', function ($attribute, $value, $fail) use ($allowedTypes) {
                if (!in_array(strtolower($value), $allowedTypes)) {
                    $fail('The selected type is invalid.');
                }
            }],
        ]);

        // Get the authenticated user
        $user = Auth::user();

        // Retrieve the company associated with the user
        $company = Company::where('user_id', $user->id)->first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        // Convert 'type' to capitalize the first letter (e.g., 'assets' -> 'Assets')
        $type = ucfirst(strtolower($request->type));

        // Find the parent account ID based on the type and company
        $parentAccount = Account::where('name', $type)
            ->where('company_id', $company->id)
            ->first();

        if (!$parentAccount) {
            return response()->json([
                'success' => false,
                'message' => "{$type} account not found for this company.",
            ], 404);
        }

        // Create the new account under the correct parent_id
        $newAccount = Account::create([
            'name' => $request->accountName,
            'parent_id' => $parentAccount->id,
            'company_id' => $company->id,
            'level' => 2,
            'actual_balance' => 0,
            'budget_balance' => 0,
            'variance' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => "New {$type} account created successfully with parent ID {$parentAccount->id}.",
            'account' => $newAccount,
        ], 201);
    }

    public function dstry($id)
    {
        $account = Account::find($id);

        if ($account) {
            $account->delete();
            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully.',
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Account not found.',
            ], 404);
        }
    }

    public function updateCode(Request $request, $id)
    {
        // Validate the incoming request to ensure a code is provided
        $request->validate([
            'code' => 'required|string|max:255',
        ]);

        // Find the asset and liability by ID
        $asset = Account::find($id);
        $liability = Account::find($id); // Assuming liabilities are also stored in the Account model

        if (!$asset) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not found.',
            ], 404);
        }

        if (!$liability) {
            return response()->json([
                'success' => false,
                'message' => 'Liability not found.',
            ], 404);
        }

        // Update the asset's code
        $asset->code = $request->code;
        $asset->save();

        // Update the liability's code
        $liability->code = $request->code; // Assuming the same code update for liability
        $liability->save();

        return response()->json([
            'success' => true,
            'message' => 'Code updated successfully ',
        ]);
    }


    public function transaction(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            if ($user->role_id == Role::ADMIN) {
                return redirect()->back()->with('error', 'Please select a company first.');
            }
            return redirect()->route('dashboard')->with('error', 'Company not found.');
        }

        $company = Company::find($companyId);

        if (!$company) {
            return redirect()->route('dashboard')->with('error', 'Company not found.');
        }

        $companies = Company::all();

        $referenceTypes = (array) $request->input('reference_type', []);
        $entityTypes = (array) $request->input('entity_type', []);
        $agentIds = array_filter((array) $request->input('agent_ids', []));
        $accountIds = array_filter((array) $request->input('account_ids', []));
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $agents = Agent::query()
            ->whereHas('branch', fn($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name']);

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->whereNotIn('level', [1, 2])
            ->orderBy('name')
            ->get(['id', 'name']);

        $withJournal = function ($q) use ($accountIds, $agentIds) {
            $q->when($accountIds, fn($qq) => $qq->whereIn('account_id', $accountIds))
                ->when($agentIds, function ($qq) use ($agentIds) {
                    $qq->where(function ($w) use ($agentIds) {
                        $w->whereHas('task', fn($t) => $t->whereIn('agent_id', $agentIds))
                            ->orWhereHas('invoice', fn($i) => $i->whereIn('agent_id', $agentIds));
                    });
                })
                ->with(['account', 'task.agent', 'invoice.agent']);
        };

        $query = Transaction::query()
            ->with(['journalEntries' => $withJournal])
            ->where('company_id', $companyId)
            ->when($referenceTypes, fn($q) => $q->whereIn('reference_type', $referenceTypes))
            ->when($entityTypes, fn($q) => $q->whereIn('entity_type', $entityTypes))
            ->when($agentIds, function ($q) use ($agentIds) {
                $q->where(function ($w) use ($agentIds) {
                    $w->whereHas('journalEntries.task', fn($t) => $t->whereIn('agent_id', $agentIds))
                        ->orWhereHas('journalEntries.invoice', fn($i) => $i->whereIn('agent_id', $agentIds));
                });
            })
            ->when($accountIds, fn($q) => $q->whereHas('journalEntries', fn($j) => $j->whereIn('account_id', $accountIds)))
            ->when(
                $fromDate && $toDate,
                fn($q) => $q->whereBetween('transaction_date', [Carbon::parse($fromDate)->startOfDay(), Carbon::parse($toDate)->endOfDay()]),
                function ($q) use ($fromDate, $toDate) {
                    $q->when($fromDate, fn($qq) => $qq->whereDate('transaction_date', '>=', Carbon::parse($fromDate)->startOfDay()))
                        ->when($toDate, fn($qq) => $qq->whereDate('transaction_date', '<=', Carbon::parse($toDate)->endOfDay()));
                }
            );

        $transactions = $query->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('coa.transaction', compact('companies', 'company', 'agents', 'accounts', 'transactions'));
    }

    public function importAccounts(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            if (!$request->hasFile('file')) {
                return back()->with('error', 'No file was uploaded.');
            }

            $file = $request->file('file');
            $rows = Excel::toCollection(null, $file);
            $dataRows = $rows->first()->skip(1); // Skip header row

            // Prepare caches
            $rootAccounts = Account::where('level', 1)
                ->pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $accountNameToId = Account::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $companies = Company::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $branches = Branch::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $agents = Agent::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $clients = Client::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $supplierNameToId = DB::table('suppliers')
                ->pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id]);

            $supplierCompanyMap = DB::table('supplier_companies')
                ->select('id', 'supplier_id', 'company_id')
                ->get()
                ->mapWithKeys(fn($item) => [$item->supplier_id . '-' . $item->company_id => $item->id]);

            $duplicatesInFile = collect();
            $existingInDb = collect();
            $rowsToImport = collect();

            foreach ($dataRows as $row) {
                $rootName = strtolower(trim((string)($row[1] ?? '')));
                $accountName = trim((string)($row[4] ?? ''));
                $parentName = strtolower(trim((string)($row[9] ?? '')));

                $companyName = strtolower(trim((string)($row[10] ?? '')));
                $branchName  = strtolower(trim((string)($row[11] ?? '')));
                $agentName   = strtolower(trim((string)($row[12] ?? '')));
                $clientName  = strtolower(trim((string)($row[13] ?? '')));

                $rootId = $rootAccounts[$rootName] ?? null;
                $parentId = $accountNameToId[$parentName] ?? null;

                $companyId = $companies[$companyName] ?? $user->company_id;
                $branchId = $branches[$branchName] ?? $user->branch_id;
                $agentId = $agents[$agentName] ?? null;
                $clientId = $clients[$clientName] ?? null;

                // Supplier and supplier_company_id mapping
                $supplierNameRaw = (string)($row[14] ?? '');
                $supplierName = strtolower(trim($supplierNameRaw));
                $supplierId = $supplierNameToId[$supplierName] ?? null;

                $supplierCompanyId = null;
                if ($supplierId) {
                    $supplierCompanyKey = $supplierId . '-' . $companyId;
                    $supplierCompanyId = $supplierCompanyMap[$supplierCompanyKey] ?? null;

                    if (!$supplierCompanyId) {
                        Log::warning("No supplier_company_id found", [
                            'supplier_name_raw' => $supplierNameRaw,
                            'normalized_supplier' => $supplierName,
                            'supplier_id' => $supplierId,
                            'company_id' => $companyId,
                            'expected_key' => $supplierCompanyKey,
                            'available_keys_sample' => $supplierCompanyMap->keys()->take(5),
                        ]);
                    }
                } elseif (!empty($supplierNameRaw)) {
                    Log::warning("Supplier name not found in DB", [
                        'supplier_name_raw' => $supplierNameRaw,
                        'normalized' => $supplierName,
                    ]);
                }

                if (!$rootId || $accountName === '') {
                    Log::warning('Skipping row due to invalid root or account name', [
                        'root_name' => $rootName,
                        'account_name' => $accountName
                    ]);
                    continue;
                }

                $key = $rootId . '|' . $accountName;
                if ($duplicatesInFile->contains($key)) {
                    return back()->with('error', "Duplicate row in file found for account: $accountName.");
                }
                $duplicatesInFile->push($key);

                $exists = Account::where('root_id', $rootId)
                    ->where('name', $accountName)
                    ->exists();

                if ($exists) {
                    $existingInDb->push($key);
                    continue;
                }

                $disabledRaw = strtolower(trim((string)($row[21] ?? '0')));
                $isDisabled = in_array($disabledRaw, ['1', 'yes', 'true']) ? 1 : 0;

                $balanceMustBe = strtolower(trim((string)($row[22] ?? '')));
                $balanceMustBe = in_array($balanceMustBe, ['debit', 'credit']) ? $balanceMustBe : null;

                $referenceId = is_numeric($row[17]) ? (int)$row[17] : null;

                $rowsToImport->push([
                    'serial_number'       => $row[0],
                    'root_id'             => $rootId,
                    'account_type'        => $row[2] ?? null,
                    'report_type'         => $row[3] ?? null,
                    'name'                => $accountName,
                    'level'               => $row[5] ?? 2,
                    'actual_balance'      => $row[6] ?? 0,
                    'budget_balance'      => $row[7] ?? 0,
                    'variance'            => $row[8] ?? 0,
                    'parent_id'           => $parentId,
                    'company_id'          => $companyId,
                    'branch_id'           => $branchId,
                    'agent_id'            => $agentId,
                    'client_id'           => $clientId,
                    'supplier_id'         => $supplierId,
                    'supplier_company_id' => $supplierCompanyId ? (int) $supplierCompanyId : null,
                    'reference_id'        => $referenceId,
                    'code'                => $row[16] ?? null,
                    'currency'            => $row[17] ?? 'KWD',
                    'is_group'            => (int)($row[18] ?? 1),
                    'disabled'            => $isDisabled,
                    'balance_must_be'     => $balanceMustBe,
                    'created_at'          => $row[21] ?? now(),
                    'updated_at'          => $row[22] ?? now(),
                    'account_type_id'     => $row[23] ?? null,
                ]);
            }

            $totalImportRow = $rowsToImport->count();

            if ($totalImportRow === 0) {
                return back()->with('error', 'No new record to import due to data invalid or duplicates.');
            }

            foreach ($rowsToImport as $data) {
                Account::create($data);
            }

            return back()->with('success', "$totalImportRow record(s) imported successfully.");
        } catch (\Exception $e) {
            Log::error('Account import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Import failed. Please check the file format or server configuration.');
        }
    }

    public function exportAccounts(): BinaryFileResponse
    {
        // Root account names (level 1)
        $rootAccounts = Account::where('level', 1)->pluck('name', 'id'); // [id => name]

        // Account names for parent lookup
        $allAccountNames = Account::pluck('name', 'id');

        // Foreign key mappings
        $companyNames = Company::pluck('name', 'id');           // Company ID → Company Name
        $branchNames = Branch::pluck('name', 'id');             // Branch ID → Branch Name
        $agentNames = Agent::pluck('name', 'id');               // Agent ID → Agent Name
        $clientNames = Client::pluck('name', 'id');             // Client ID → Client Name

        // SupplierCompanyID → SupplierID
        $supplierCompanies = DB::table('supplier_companies')->pluck('supplier_id', 'id');
        // SupplierID → Supplier Name
        $supplierNames = DB::table('suppliers')->pluck('name', 'id');
        // SupplierCompanyID → Supplier Name
        $supplierCompanyIdToName = $supplierCompanies->mapWithKeys(function ($supplierId, $companyId) use ($supplierNames) {
            return [$companyId => $supplierNames[$supplierId] ?? null];
        });

        // Fetch all accounts
        $accounts = Account::all();

        // Header row
        $data = collect();
        $data->push([
            'Serial Number',
            'Root Name',
            'Account Type',
            'Report Type',
            'Name',
            'Level',
            'Actual Balance',
            'Budget Balance',
            'Variance',
            'Parent Name',
            'Company Name',
            'Branch Name',
            'Agent Name',
            'Client Name',
            'Supplier Name',
            'Reference ID',
            'Code',
            'Currency',
            'Is Group',
            'Disabled',
            'Balance Must Be',
            'Created At',
            'Updated At',
            'Account Type ID'
        ]);

        foreach ($accounts as $account) {
            $data->push([
                $account->serial_number,
                $rootAccounts[$account->root_id] ?? '',                    // Root Name
                $account->account_type,
                $account->report_type,
                $account->name,
                $account->level,
                $account->actual_balance,
                $account->budget_balance,
                $account->variance,
                $allAccountNames[$account->parent_id] ?? '',               // Parent Name
                $companyNames[$account->company_id] ?? '',                 // Company Name
                $branchNames[$account->branch_id] ?? '',                   // Branch Name
                $agentNames[$account->agent_id] ?? '',                     // Agent Name
                $clientNames[$account->client_id] ?? '',                   // Client Name
                $supplierCompanyIdToName[$account->supplier_company_id] ?? '', // Supplier Name only
                $account->reference_id,
                $account->code,
                $account->currency,
                $account->is_group,
                $account->disabled,
                $account->balance_must_be,
                $account->created_at,
                $account->updated_at,
                $account->account_type_id,
            ]);
        }

        return Excel::download(new class($data) implements \Maatwebsite\Excel\Concerns\FromCollection {
            protected $data;
            public function __construct(Collection $data)
            {
                $this->data = $data;
            }
            public function collection()
            {
                return $this->data;
            }
        }, 'accounts_export.xlsx');
    }

    public function delegatePriceAmadeus(Request $request)
    {
        $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
            'code' => 'required|integer',
        ]);

        $accountId = $request->input('account_id');
        $code = $request->input('code');

        $account = Account::with('journalEntries')
            ->where('id', $accountId)
            ->where('company_id', Auth::user()->company->id)
            ->first();

        $debit = $account->journalEntries->sum('debit');
        $credit = $account->journalEntries->sum('credit');
        $isDebit = $debit > 0;

        $totalAmount = 0;

        // if ($debit === 0 && $credit === 0) {
        //     return redirect()->back()->with('error', 'No debit or credit entries found for this account');
        // } else if ($debit > 0 && $credit > 0) {
        //     return redirect()->back()->with('error', 'Both debit and credit entries found for this account, contact your support');
        // }

        if ($isDebit) {
            $totalAmount = $debit;
        } else {
            $totalAmount = $credit;
        }
        if (!$account) {
            return redirect()->back()->with('error', 'Account not found');
        }

        if ($account->name !== 'Amadeus') {
            return redirect()->back()->with('error', 'This account is not Amadeus');
        }

        $journalEntries = $account->journalEntries;
        $tasks = collect();
        foreach ($journalEntries as $journalEntry) {
            $task = $journalEntry->task;
            if ($task && $task->type === 'flight') {
                $tasks->push($task);
            }
        }

        $issuedBy = $tasks->whereNotNull('issued_by')
            ->pluck('issued_by')
            ->unique()
            ->toArray();

        if (empty($issuedBy)) {
            return redirect()->back()->with('error', 'No issued tasks found for this account');
        }

        // Get sum of total grouped by issued_by for all tasks
        $taskSummary = $tasks->groupBy('issued_by')
            ->map(function ($groupedTasks) {
                return $groupedTasks->sum('total');
            });

        // Debug: show the grouped sums

        $notIssuedTask = $tasks->whereNull('issued_by')
            ->values();

        // $issuedBy = Task::where('company_id', $account->company_id)
        //     ->where('type', 'flight')
        //     ->whereNotNull('issued_by')
        //     ->pluck('issued_by')
        //     ->unique()
        //     ->toArray();

        // $notIssued = Task::where('company_id', $account->company_id)
        //     ->where('type', 'flight')
        //     ->whereNull('issued_by')
        //     ->get();

        // dump($notIssued);
        $cumulativeTaskTotal = 0;

        DB::beginTransaction();

        try {
            foreach ($issuedBy as $company) {
                if (Account::where('code', $code)->where('company_id', $account->company_id)->exists()) {
                    throw new Exception('Account with this code already exists');
                }

                if (Account::where('name', $company)->where('parent_id', $account->id)->where('company_id', $account->company_id)->exists()) {
                    throw new Exception('Account with this name already exists under the parent account');
                }

                // Get the sum for this specific company from our pre-calculated summary
                $sumTotal = $taskSummary[$company] ?? 0;

                if ($sumTotal <= 0) {
                    continue; // Skip tasks with zero or negative amounts
                }


                $data = [
                    'name' => $company,
                    'parent_id' => $account->id,
                    'company_id' => $account->company_id,
                    'level' => $account->level + 1,
                    'root_id' => $account->root_id,
                    'account_type' => $account->account_type,
                    'report_type' => $account->report_type,
                    'code' =>  $code,
                    'actual_balance' => 0,
                    'budget_balance' => 0,
                    'variance' => 0,
                ];

                if ($isDebit) {
                    $data['debit'] = $sumTotal;
                    $data['credit'] = 0;
                } else {
                    $data['debit'] = 0;
                    $data['credit'] = $sumTotal;
                }

                $childAccount = Account::create($data);

                // Get tasks for this specific company from our collection
                $companyTasks = $tasks->where('issued_by', $company);

                foreach ($companyTasks as $task) {
                    foreach ($task->journalEntries->where('account_id', $account->id) as $journalEntry) {
                        $journalEntry->account_id = $childAccount->id;
                        $journalEntry->update();
                    }
                }

                $cumulativeTaskTotal += $sumTotal;

                $code++;
            }

            if ($notIssuedTask->isNotEmpty()) {
                $notIssuedAccount = Account::create([
                    'name' => 'Not Issued',
                    'parent_id' => $account->id,
                    'company_id' => $account->company_id,
                    'level' => $account->level + 1,
                    'root_id' => $account->root_id,
                    'account_type' => $account->account_type,
                    'report_type' => $account->report_type,
                    'code' =>  $code,
                    'actual_balance' => 0,
                    'budget_balance' => 0,
                    'variance' => 0,
                ]);

                foreach ($notIssuedTask as $task) {
                    foreach ($task->journalEntries->where('account_id', $account->id) as $journalEntry) {
                        $journalEntry->account_id = $notIssuedAccount->id;
                        $journalEntry->update();
                    }
                }
                $cumulativeTaskTotal += $notIssuedTask->sum('total');
            }
        } catch (Exception $e) {
            Log::error('Error creating account', [
                'error' => $e->getMessage(),
                'code' => $code,
                'company_gds' => $company,
            ]);
            DB::rollBack();
            return redirect()->back()->with('error', 'Error creating account: contact you support ');
        }

        if (number_format($cumulativeTaskTotal, 2) !== number_format($totalAmount, 2)) {
            Log::error('Cumulative task price does not match account balance', [
                'cumulative_task_total' => $cumulativeTaskTotal,
                'total_amount' => $totalAmount,
            ]);
            DB::rollBack();
            return redirect()->back()->with('error', 'Cumulative task price does not match account balance');
        }

        DB::commit();

        return redirect()->back()->with('success', 'Account has been delegated successfully');
        // Get all companies
        // $companies = Company::all();

        // foreach ($companies as $company) {
        //     // Clone the account for each company
        //     $newAccount = $account->replicate();
        //     $newAccount->company_id = $company->id;
        //     $newAccount->save();
        // }

        // return response()->json(['success' => 'Account has been relegated to all companies successfully']);
    }

    public function deleteTransaction($id)
    {

        $transaction = Transaction::findOrFail($id);

        DB::beginTransaction();

        try {
            Log::info("Starting soft delete process for transaction of ID: {$transaction->id}");

            if ($transaction) {
                $transaction->delete();
                Log::info('Successfully deleted transaction with ID: ' . $transaction->id);

                DB::commit();
                return redirect()->back()->with('success', 'Transaction successfully deleted');
            } else {
                Log::info('Transaction is not found. Transaction deletion is aborted');
                return redirect()->back()->with('error', 'Transaction not found');
            }
        } catch (Exception $e) {
            DB::rollback();
            Log::error("Error during transaction soft delete: " . $e->getMessage(), [
                'transaction_id' => $id,
                'trace' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete transaction: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function openingBalances(Request $request)
    {
        Gate::authorize('viewAny', CoaCategory::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->route('dashboard')->with('error', 'Please select a company first.');
        }

        $accounts = Account::where('company_id', $companyId)
            ->whereDoesntHave('children')
            ->where('level', '>', 1)
            ->orderBy('root_id')
            ->orderBy('code')
            ->orderBy('name')
            ->get()
            ->groupBy(function ($account) {
                return $account->root?->name ?? 'Other';
            });

        $company = Company::find($companyId);
        $openingBalanceDate = $company->opening_balance_date ?? null;

        return view('coa.opening-balances', [
            'accounts' => $accounts,
            'openingBalanceDate' => $openingBalanceDate,
        ]);
    }

    public function saveOpeningBalances(Request $request)
    {
        Gate::authorize('viewAny', CoaCategory::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->back()->with('error', 'Please select a company first.');
        }

        $request->validate([
            'opening_balance_date' => 'required|date',
            'balances' => 'required|array',
            'balances.*' => 'nullable|numeric',
        ]);

        $openingBalanceDate = $request->input('opening_balance_date');
        $balances = $request->input('balances', []);

        DB::beginTransaction();

        try {
            $updatedCount = 0;

            foreach ($balances as $accountId => $balance) {
                if ($balance === null || $balance === '') {
                    continue;
                }

                $account = Account::where('id', $accountId)
                    ->where('company_id', $companyId)
                    ->first();

                if ($account) {
                    $account->update([
                        'opening_balance' => (float) $balance,
                        'opening_balance_date' => $openingBalanceDate,
                    ]);
                    $updatedCount++;
                }
            }

            DB::commit();

            return redirect()->back()->with('success', "{$updatedCount} account(s) opening balance updated successfully.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error saving opening balances', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Failed to save opening balances. Please try again.');
        }
    }
}
