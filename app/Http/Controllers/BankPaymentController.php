<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\JournalEntry;
use App\Models\Account;
use App\Models\Company;
use App\Models\Branch;
use App\Models\CoaCategory;
use App\Models\Role;
use App\Models\Refund;
use App\Models\Agent;
use App\Models\BonusAgent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BankPaymentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', CoaCategory::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $bankPaymentsQuery = Transaction::with(['journalEntries' => function ($query) {
            $query->where('type', 'payable');
        }])
            ->whereNotNull('name')
            ->where('reference_number', 'like', 'PV-%')
            ->latest();

        if ($request->filled('q')) {
            $search = $request->q;
            $bankPaymentsQuery->where(function ($query) use ($search) {
                $query->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('journalEntries', function ($q) use ($search) {
                        $q->where('type', 'payable')
                            ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $bankPaymentsQuery->where('company_id', $companyId);
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $branchIds = Branch::where('company_id', $companyId)->pluck('id')->toArray();
            $bankPaymentsQuery->whereIn('branch_id', $branchIds);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $bankPaymentsQuery->where('company_id', $companyId);
        } elseif ($user->role_id == Role::AGENT) {
            return abort(403, 'Unauthorized action.');
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        $totalRecords = (clone $bankPaymentsQuery)->count();
        $bankPayments = $bankPaymentsQuery->paginate(10)->withQueryString();

        return view('bank-payments.index', compact(
            'bankPayments',
            'totalRecords',
        ));
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return redirect()->route('bank-payments.index')->with('error', 'Please select a company first.');
        }

        $company = Company::with('branches')->find($companyId);

        if (!$company) {
            return redirect()->route('bank-payments.index')->with('error', 'Company not found.');
        }

        $companies = $company;
        $branches = $company->branches;

        $rootNames = ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity'];
        $rootIds = Account::whereIn('name', $rootNames)
            ->where('company_id', $companyId)
            ->pluck('id');

        $accpayreceives = Account::doesntHave('children')
            ->with('root')
            ->where('company_id', $companyId)
            ->whereHas('parent', function ($query) use ($rootIds) {
                $query->whereIn('root_id', $rootIds);
            })
            ->get();

        $lastLevelAccounts = Account::doesntHave('children')
            ->with('root')
            ->where('company_id', $companyId)
            ->whereHas('parent', function ($query) use ($rootIds) {
                $query->whereIn('root_id', $rootIds);
            })
            ->get();

        $supplierRootIds = Account::whereIn('name', ['Liabilities', 'Expenses'])
            ->where('company_id', $companyId)
            ->pluck('id');
        $suppliers = Account::doesntHave('children')
            ->with('root')
            ->where('company_id', $companyId)
            ->whereIn('root_id', $supplierRootIds)
            ->get();

        $accounts = Account::where('company_id', $companyId)->get();

        $refundNumbers = Refund::where('company_id', $companyId)
            ->select('refund_number')
            ->get();

        $bonusAccounts = Account::where('company_id', $companyId)
            ->with('root')
            ->where('label', 'like', '%bonus%')
            ->get();

        $agents = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->get();

        $assetsRoot = Account::where('name', 'Assets')
            ->where('company_id', $companyId)
            ->first();

        $bankAccounts = collect();
        if ($assetsRoot) {
            $bankParent = Account::where('parent_id', $assetsRoot->id)
                ->where('name', 'Bank Accounts')
                ->where('company_id', $companyId)
                ->first();

            if ($bankParent) {
                // Get all child accounts under "Bank Accounts"
                $bankAccounts = Account::where('parent_id', $bankParent->id)
                    ->where('company_id', $companyId)
                    ->get()
                    ->map(function ($account) {
                        // Calculate balance from journal entries (Assets: Debit - Credit)
                        $debitSum = JournalEntry::where('account_id', $account->id)->sum('debit');
                        $creditSum = JournalEntry::where('account_id', $account->id)->sum('credit');
                        $account->current_balance = $debitSum - $creditSum;
                        return $account;
                    });
            }
        }

        return view('bank-payments.create', compact(
            'accounts',
            'companies',
            'branches',
            'suppliers',
            'accpayreceives',
            'lastLevelAccounts',
            'refundNumbers',
            'bonusAccounts',
            'agents',
            'bankAccounts',
        ));
    }

    /**
     * Store bank payment transaction.
     */
    public function store(Request $request)
    {
        Log::info('Starting to create Payment Voucher', ['response' => $request->all()]);

        if ($request->bankpaymenttype === 'PaymentByDate') {
            $bankPaymentType = 'Payment';
            $reconciledFlag = 2; // 0 = not yet reconciled, 1 = the record that has been reconciled, 2 = reconciled record
            $reconciledProcess = 'yes';
        } elseif ($request->bankpaymenttype === 'Payment') {
            $bankPaymentType = 'Payment';
            $reconciledFlag = 0;
            $reconciledProcess = 'no';
        } elseif ($request->bankpaymenttype === 'Refund') {
            $bankPaymentType = 'Refund';
            $reconciledFlag = 0;
            $reconciledProcess = 'no';
        } else {
            $bankPaymentType = 'Invoice';
            $reconciledFlag = 0;
            $reconciledProcess = 'no';
        }

        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'docdate' => 'required|date',
            'bankpaymentref' => 'required|string',
            'bankpaymenttype' => 'required|string',
            'pay_from_account' => 'required|exists:accounts,id',
            'remarks_create' => 'required|string',
            'internal_remarks' => 'nullable|string',
            'remarks_fl' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.type_selector' => 'nullable|string',
            'items.*.account_id' => ['nullable', 'exists:accounts,id'],
            'items.*.remarks' => 'nullable|string',
            'items.*.currency' => 'nullable|string',
            'items.*.exchange_rate' => 'nullable|numeric',
            'items.*.debit' => 'nullable|numeric',
            'items.*.credit' => 'nullable|numeric',
            'items.*.cheque_no' => 'nullable|string',
            'items.*.cheque_date' => 'nullable|date',
            'items.*.bank_name' => 'nullable|string',
            'items.*.branch' => 'nullable|string',
            'items.*.balance' => 'nullable|numeric',
        ], [
            'items.*.account_id.exists' => 'The selected account code does not exist.',
            'pay_from_account.required' => 'Please select a bank account to pay from.',
        ]);

        $payFromAccount = Account::find($request->pay_from_account);
        if (!$payFromAccount) {
            return redirect()->back()->with('error', 'Invalid bank account selected.');
        }

        $totalPaymentAmount = 0;
        foreach ($request->items as $item) {
            $credit = (float) ($item['credit'] ?? 0);
            $totalPaymentAmount += $credit;
        }

        if ($totalPaymentAmount > 0) {
            $bankDebitSum = JournalEntry::where('account_id', $payFromAccount->id)->sum('debit');
            $bankCreditSum = JournalEntry::where('account_id', $payFromAccount->id)->sum('credit');
            $currentBankBalance = $bankDebitSum - $bankCreditSum;

            if ($currentBankBalance < $totalPaymentAmount) {
                return redirect()->back()->with(
                    'error',
                    "Insufficient bank balance. Current balance: KWD " . number_format($currentBankBalance, 3) .
                        ", Required: KWD " . number_format($totalPaymentAmount, 3)
                );
            }
        }

        try {
            DB::beginTransaction();

            foreach ($request->items as $item) {
                $creditAmount = (float) ($item['credit'] ?? 0);
                $amount = $creditAmount;

                if ($amount <= 0) {
                    Log::warning('Skipping item with zero amount', ['item' => $item]);
                    continue;
                }

                $type = $item['type_selector'] ?? 'account';
                $targetAccount = Account::find($item['account_id']);
                $agent = isset($item['agent_id']) ? Agent::find($item['agent_id']) : null;

                if (!$targetAccount && $type !== 'refund') {
                    Log::warning('Target account not found', ['item' => $item]);
                    continue;
                }

                $accountName = $targetAccount ? $targetAccount->name : 'Unknown';
                if ($type === 'bonus' && $agent) {
                    $description = "Bonus Payment to Agent: {$agent->name}. {$request->remarks_create}";
                } elseif ($type === 'bonus') {
                    $description = "Bonus Payment for Account: {$accountName}. {$request->remarks_create}";
                } elseif ($type === 'refund') {
                    $description = "Refund Payment. {$request->remarks_create}";
                } else {
                    $description = "Payment Voucher for Account: {$accountName}. {$request->remarks_create}";
                }

                $transaction = Transaction::create([
                    'entity_id' => $request->company_id,
                    'entity_type' => 'company',
                    'company_id' => $request->company_id,
                    'branch_id' => $request->branch_id,
                    'transaction_type' => 'debit',
                    'amount' => $amount,
                    'date' => Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                    'description' => $description,
                    'invoice_id' => null,
                    'reference_number' => $request->bankpaymentref,
                    'reference_type' => $bankPaymentType,
                    'name' => $payFromAccount->name,
                    'remarks_internal' => $request->internal_remarks,
                    'remarks_fl' => $request->remarks_fl,
                    'transaction_date' => Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                ]);

                if (!$transaction) {
                    Log::warning('Failed to create transaction', ['item' => $item]);
                    continue;
                }

                // Journal Entry 1: Target Account (Supplier/Liability) - DEBIT to reduce liability
                if ($targetAccount) {
                    $journalEntry1 = JournalEntry::create([
                        'transaction_date' => Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                        'account_id' => $targetAccount->id,
                        'company_id' => $request->company_id,
                        'branch_id' => $request->branch_id,
                        'transaction_id' => $transaction->id,
                        'description' => $description,
                        'debit' => $amount,
                        'credit' => 0,
                        'balance' => $item['balance'] ?? 0,
                        'voucher_number' => $request->bankpaymentref,
                        'name' => $targetAccount->name,
                        'type' => 'payable',
                        'currency' => $item['currency'] ?? 'KWD',
                        'exchange_rate' => $item['exchange_rate'] ?? 1,
                        'amount' => $amount,
                        'cheque_no' => $item['cheque_no'] ?? '',
                        'cheque_date' => !empty($item['cheque_date']) ? Carbon::parse($item['cheque_date'])->format('Y-m-d H:i:s') : null,
                        'bank_info' => $item['bank_name'] ?? '',
                        'auth_no' => $item['auth_no'] ?? '',
                        'reconciled' => $reconciledFlag,
                    ]);

                    Log::info('Created Journal Entry 1 (Target Account - DEBIT)', [
                        'account' => $targetAccount->name,
                        'debit' => $amount,
                        'credit' => 0,
                    ]);
                }

                // Journal Entry 2: Bank Account - CREDIT to reduce cash
                $journalEntry2 = JournalEntry::create([
                    'transaction_date' => Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                    'account_id' => $payFromAccount->id,
                    'company_id' => $request->company_id,
                    'branch_id' => $request->branch_id,
                    'transaction_id' => $transaction->id,
                    'description' => "Payment from {$payFromAccount->name}: {$description}",
                    'debit' => 0,
                    'credit' => $amount,
                    'balance' => 0,
                    'voucher_number' => $request->bankpaymentref,
                    'name' => $payFromAccount->name,
                    'type' => 'bank',
                    'currency' => $item['currency'] ?? 'KWD',
                    'exchange_rate' => $item['exchange_rate'] ?? 1,
                    'amount' => $amount,
                    'cheque_no' => $item['cheque_no'] ?? '',
                    'cheque_date' => !empty($item['cheque_date']) ? Carbon::parse($item['cheque_date'])->format('Y-m-d H:i:s') : null,
                    'bank_info' => $item['bank_name'] ?? '',
                    'auth_no' => $item['auth_no'] ?? '',
                    'reconciled' => $reconciledFlag,
                ]);

                Log::info('Created Journal Entry 2 (Bank Account)', [
                    'account' => $payFromAccount->name,
                    'debit' => 0,
                    'credit' => $amount,
                ]);

                if ($type === 'bonus' && $agent) {
                    BonusAgent::create([
                        'transaction_id' => $transaction->id,
                        'agent_id' => $agent->id,
                        'amount' => $amount,
                        'created_by' => Auth::user()->id,
                    ]);
                    Log::info('Created BonusAgent record', ['agent' => $agent->name, 'amount' => $amount]);
                }

                if (!empty($item['transaction_id']) && $reconciledProcess === 'yes') {
                    $ids = is_array($item['transaction_id'])
                        ? $item['transaction_id']
                        : array_filter(array_map('trim', explode(',', $item['transaction_id'])));
                    $selectedIds = array_unique(array_map('intval', $ids));

                    if (!empty($selectedIds) && isset($journalEntry1)) {
                        JournalEntry::where('company_id', $request->company_id)
                            ->where('branch_id', $request->branch_id)
                            ->whereIn('id', $selectedIds)
                            ->where('reconciled', '!=', 2)
                            ->update([
                                'reconciled' => 1,
                                'reconciled_ref_id' => $journalEntry1->id,
                            ]);
                        Log::info('Reconciled old journal entries', ['ids' => $selectedIds]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('bank-payments.index')->with('success', 'Payment Voucher Successfully Recorded with Double-Entry.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment Voucher Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $bankPayment = Transaction::findOrFail($id);
        $JournalEntrys = JournalEntry::where('transaction_id', $bankPayment->id)->get();

        $payeeEntry = $JournalEntrys->where('type', 'payable')->first();
        $payeeName = $payeeEntry?->name ?? $bankPayment->name;

        $bankEntry = $JournalEntrys->where('type', 'bank')->first();
        $payFromName = $bankEntry?->name ?? '';

        $user = Auth::user();
        $companyId = $bankPayment->company_id;
        $company = Company::with('branches.account', 'branches.agents')->find($companyId);

        if (!$company) {
            return redirect()->route('bank-payments.index')->with('error', 'Company not found.');
        }

        if (!in_array($user->role_id, [Role::ADMIN, Role::COMPANY, Role::ACCOUNTANT])) {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        $companies = $company;
        $branches = $company->branches;
        $accounts = $branches->pluck('account')->filter();

        $rootNames = ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity'];
        $rootIds = Account::whereIn('name', $rootNames)
            ->where('company_id', $companyId)
            ->pluck('id');

        $accpayreceives = Account::doesntHave('children')
            ->where('company_id', $companyId)
            ->whereHas('parent', fn($q) => $q->whereIn('root_id', $rootIds))
            ->get();

        $suppliers = Account::doesntHave('children')
            ->with('root')
            ->where('company_id', $companyId)
            ->whereHas('parent', fn($q) => $q->whereIn('root_id', $rootIds))
            ->get();

        $assetsRoot = Account::where('name', 'Assets')->where('company_id', $companyId)->first();
        $bankAccounts = collect();

        if ($assetsRoot) {
            $bankParent = Account::where('parent_id', $assetsRoot->id)
                ->where('name', 'Bank Accounts')
                ->where('company_id', $companyId)
                ->first();

            if ($bankParent) {
                $bankAccounts = Account::where('parent_id', $bankParent->id)
                    ->where('company_id', $companyId)
                    ->get()
                    ->map(function ($account) {
                        $debitSum = JournalEntry::where('account_id', $account->id)->sum('debit');
                        $creditSum = JournalEntry::where('account_id', $account->id)->sum('credit');
                        $account->current_balance = $debitSum - $creditSum;
                        return $account;
                    });
            }
        }

        return view('bank-payments.edit', compact(
            'companies',
            'bankPayment',
            'accounts',
            'branches',
            'suppliers',
            'accpayreceives',
            'JournalEntrys',
            'bankAccounts',
            'payeeName',
            'payFromName',
        ));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'bankpaymentref' => 'required|string',
            'docdate' => 'required|date',
            'pay_to' => 'required|exists:accounts,name',
            'remarks_create' => 'required|string',
            'internal_remarks' => 'nullable|string',
            'remarks_fl' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.account_id' => ['required', 'exists:accounts,id'],
            'items.*.description' => 'required|string',
            'items.*.currency' => 'required|string',
            'items.*.exchange_rate' => 'required|numeric',
            'items.*.amount' => 'required|numeric',
            'items.*.debit' => 'required|numeric',
            'items.*.credit' => 'required|numeric',
        ], [
            //'items.*.account_id.exists' => 'The selected account code does not exist.', 
        ]);

        try {
            DB::beginTransaction();

            $transaction = Transaction::findOrFail($id);
            $transaction->update([
                'branch_id' => $request->branch_id,
                'transaction_type' => 'debit',
                'amount' => collect($request->items)->sum('amount'),
                'date' => Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                'description' => $request->remarks_create,
                'reference_type' => $request->bankpaymenttype,
                'invoice_id' => null,
                'reference_number' => $request->bankpaymentref,
                'name' => $request->pay_to,
                'remarks_internal' => $request->internal_remarks,
                'remarks_fl' => $request->remarks_fl,
                'updated_at' => now(),

            ]);

            // Remove old general ledger entries and insert new ones
            JournalEntry::where('transaction_id', $id)->delete();
            $this->storeJournalEntryEntries($request->items, $request, $id);

            DB::commit();
            return redirect()->back()->with('success', 'Payment Voucher Updated Successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Store general ledger entries for a transaction.
     */
    private function storeJournalEntryEntries($items, $request, $transactionId, $payFromAccount)
    {
        foreach ($items as $item) {
            $account = Account::find($item['account_id']);
            $companyId = $account ? $account->company_id : null;

            $debitAmount = (float) ($item['debit'] ?? 0);
            $creditAmount = (float) ($item['credit'] ?? 0);

            JournalEntry::create([
                'transaction_date' => Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                'account_id' => $item['account_id'],
                'company_id' => $companyId,
                'branch_id' => $request->branch_id ?? 0,
                'transaction_id' => $transactionId,
                'description' => $item['description'],
                'debit' => $debitAmount,
                'credit' => $creditAmount,
                'balance' => $item['balance'] ?? 0,
                'voucher_number' => $request->bankpaymentref,
                'name' => $account->name ?? '',
                'type' => 'payable',
                'currency' => $item['currency'],
                'exchange_rate' => $item['exchange_rate'],
                'amount' => $item['amount'],
                'cheque_no' => $item['cheque_no'] ?? '',
                'cheque_date' => $item['cheque_date']
                    ? Carbon::parse($item['cheque_date'])->format('Y-m-d H:i:s')
                    : null,
                'bank_info' => $item['bank_name'] ?? '',
                'auth_no' => $item['auth_no'] ?? '',
                'updated_at' => now(),
            ]);

            JournalEntry::create([
                'transaction_date' => Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                'account_id' => $payFromAccount->id,
                'company_id' => $companyId,
                'branch_id' => $request->branch_id ?? 0,
                'transaction_id' => $transactionId,
                'description' => "Bank: " . $item['description'],
                'debit' => $creditAmount,
                'credit' => $debitAmount,
                'balance' => 0,
                'voucher_number' => $request->bankpaymentref,
                'name' => $payFromAccount->name,
                'type' => 'bank',
                'currency' => $item['currency'],
                'exchange_rate' => $item['exchange_rate'],
                'amount' => $item['amount'],
                'cheque_no' => $item['cheque_no'] ?? '',
                'cheque_date' => $item['cheque_date']
                    ? Carbon::parse($item['cheque_date'])->format('Y-m-d H:i:s')
                    : null,
                'bank_info' => $item['bank_name'] ?? '',
                'auth_no' => $item['auth_no'] ?? '',
                'updated_at' => now(),
            ]);
        }
    }

    public function fetchPaymentsByDate(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $supplierName = (string) $request->get('supplier');
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if (!$companyId) {
            return response()->json(['error' => 'Please select a company first.'], 400);
        }

        // Get branch IDs for the company
        $branchIds = Branch::where('company_id', $companyId)->pluck('id')->toArray();

        $accountIds = [];
        $supplierNameTrimmed = trim($supplierName);
        if ($supplierNameTrimmed !== '') {
            $acc = Account::where('name', $supplierNameTrimmed)
                ->where('company_id', $companyId)
                ->first()
                ?? Account::where('name', 'LIKE', "%{$supplierNameTrimmed}%")
                ->where('company_id', $companyId)
                ->first();

            if ($acc) {
                $accountIds = [$acc->id];
                Log::info('Resolved supplier account', ['name' => $supplierNameTrimmed, 'account_id' => $acc->id]);
            } else {
                Log::info('Supplier name not found in accounts', ['name' => $supplierNameTrimmed]);
            }
        }

        $totalsByAccountQuery = DB::table('journal_entries')
            ->join('accounts as a', 'journal_entries.account_id', '=', 'a.id')
            ->join('accounts as root_a', 'a.root_id', '=', 'root_a.id')
            ->select(
                'journal_entries.account_id',
                DB::raw('SUM(COALESCE(journal_entries.credit, 0)) - SUM(COALESCE(journal_entries.debit, 0)) AS total')
            )
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.branch_id', $branchIds)
            ->whereBetween('journal_entries.transaction_date', [$request->from, $request->to])
            ->whereIn('root_a.name', ['Liabilities'])
            ->when(!empty($accountIds), fn($q) => $q->whereIn('journal_entries.account_id', $accountIds));

        $totalsByAccount = $totalsByAccountQuery
            ->groupBy('journal_entries.account_id')
            ->get()
            ->filter(fn($e) => $e->total > 0)
            ->pluck('total', 'account_id');

        if ($totalsByAccount->isEmpty()) {
            return response()->json([]);
        }

        $entriesQuery = JournalEntry::whereIn('account_id', $totalsByAccount->keys())
            ->where('company_id', $companyId)
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('transaction_date', [$request->from, $request->to])
            ->where('credit', '!=', 0)
            ->where('reconciled', 0)
            ->whereNull('voucher_number')
            ->whereHas('account.root', fn($q) => $q->whereIn('name', ['Liabilities']))
            ->when(!empty($accountIds), fn($q) => $q->whereIn('account_id', $accountIds))
            ->with(['account', 'account.root', 'task'])
            ->orderBy('transaction_date');

        $entries = $entriesQuery->get();

        $payments = $entries->map(function ($entry) use ($totalsByAccount) {
            $description = '';
            if ($entry->task) $description = $entry->task->reference . ' - ';

            if (isset($entry->task->client_name)) {
                $description .= $entry->task->client_name;
            } elseif (isset($entry->task->passenger_name)) {
                $description .= $entry->task->passenger_name;
            } elseif (isset($entry->task->supplier_name)) {
                $description .= $entry->task->supplier_name;
            } else {
                $description .= 'No Client';
            }

            if ($entry->task) {
                if ($entry->task->type === 'flight') {
                    $ticketNumber = $entry->task->ticket_number;
                    $description .= $ticketNumber ? ' - ' . $ticketNumber : '';
                } elseif ($entry->task->hotel === 'hotel') {
                    $hotelName = $entry->task->hotelDetails->hotel->name ?? '';
                    $description .= $hotelName ? ' - ' . $hotelName : '';
                }
            }

            return [
                'id'               => $entry->id,
                'transaction_id'   => $entry->transaction_id,
                'transaction_date' => $entry->transaction_date,
                'account_id'       => $entry->account_id,
                'account_code'     => $entry->account->code ?? '',
                'account_name'     => $entry->account->name ?? '',
                'root_name'        => $entry->account->root->name ?? 'No Root',
                'name'             => $entry->name,
                'description'      => $description,
                'debit'            => (float) $entry->debit,
                'credit'           => (float) $entry->credit,
                'account_total'    => (float) ($totalsByAccount[$entry->account_id] ?? 0),
            ];
        });

        return response()->json($payments);
    }

    public function fetchJournalEntriesByIds(Request $request)
    {
        $id = $request->input('id');

        if (!$id) {
            return response()->json(['error' => 'Invalid or missing ID.'], 400);
        }

        // Fetch the journal entries where reconciled_ref_id equals the given transaction ID
        $entries = JournalEntry::with(['account', 'transaction'])
            ->where('reconciled', 1)
            ->where('reconciled_ref_id', $id)
            ->get();

        return response()->json($entries);
    }

    public function declineReconcile($transactionId)
    {
        $transaction = JournalEntry::findOrFail($transactionId);

        $recJournalEntry = JournalEntry::where('id', $transaction->id)
            ->firstOrFail();

        $recJournalEntry->reconciled = 0;
        $recJournalEntry->save();

        JournalEntry::where('id', $recJournalEntry->id)->update([
            'reconciled' => 0,
        ]);

        $recOriginalJournalEntry = JournalEntry::where('reconciled_ref_id', $recJournalEntry->id)->get();
        foreach ($recOriginalJournalEntry as $entry) {
            $entry->reconciled = 0;
            $entry->reconciled_ref_id = null;
            $entry->save();
        }

        JournalEntry::where('reconciled_ref_id', $recJournalEntry->id)->update([
            'reconciled' => 0,
            'reconciled_ref_id' => null,
        ]);

        JournalEntry::where('id', $recJournalEntry->id)->delete();

        return response()->json(['success' => true]);
    }
}
