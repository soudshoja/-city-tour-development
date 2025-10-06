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
use App\Models\Role;
use App\Models\Refund;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Http\Controllers\ClientController;
use App\Models\Client;
use App\Models\Invoice;

class ReceiptVoucherController extends Controller
{


    public function index()
    {
        $user = auth()->user();

        if ($user->role_id == Role::ADMIN) {
            $receiptvouchers = Transaction::all();
            $totalRecords = Transaction::count();
        } elseif ($user->role_id == Role::COMPANY) {

            $companyId = Company::where('user_id', $user->id)->value('id'); // Get the company ID
            $branch = Branch::where('company_id', $companyId)->get();

            $branchesId = $branch->pluck('id')->toArray();
            $receiptvouchers = Transaction::whereIn('branch_id', $branchesId)
                ->whereNotNull('name')
                ->where('reference_number', 'like', 'RV-%') // <-- change PV-% to RV-%
                ->latest()
                ->paginate(10);

            $totalRecords = Transaction::whereIn('branch_id', $branchesId)
                ->whereNotNull('name')
                ->where('reference_number', 'like', 'RV-%') // <-- change PV-% to RV-%
                ->count();
        } elseif ($user->role_id == Role::AGENT) {
            $branchId = $user->branch_id;
            $receiptvouchers = Transaction::where('branch_id', $branchId)
                ->whereNotNull('name')
                ->where('reference_number', 'like', 'RV-%') // <-- change PV-% to RV-%
                ->latest()
                ->paginate(10);

            $totalRecords = Transaction::where('branch_id', $branchId)
                ->whereNotNull('name')
                ->where('reference_number', 'like', 'RV-%') // <-- change PV-% to RV-%
                ->count();
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        return view('receipt-voucher.index', compact('receiptvouchers', 'totalRecords'));
    }

    public function create()
    {
        $user = auth()->user();
        if ($user->role_id == Role::ADMIN) {
            $accounts = Account::all();
            $companies = Company::all();
            $branches = Branch::all();

            $rootNames = ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity'];
            $rootIds = Account::whereIn('name', $rootNames)->pluck('id');

            $accpayreceives = Account::doesntHave('children')
                ->with('root')
                ->whereHas('parent', function ($query) use ($rootIds) {
                    $query->whereIn('root_id', $rootIds);
                })
                ->get();

            $lastLevelAccounts = Account::doesntHave('children')
                ->with('root')
                ->whereHas('parent', function ($query) use ($rootIds) {
                    $query->whereIn('root_id', $rootIds);
                })
                ->get();

            $rootIds = Account::where('name', 'Liabilities')->pluck('id');
            $suppliers = Account::doesntHave('children')
                ->with('root')
                ->whereIn('root_id', $rootIds)
                ->get();


            $refundNumbers = Refund::select('refund_number')->get();
        } elseif ($user->role_id == Role::COMPANY) {
            $company = Company::with('branches.agents')->find($user->company->id);
            $accounts = $company->branches->flatMap->accounts;
            $branches = $company->branches;
            $companies = $company;

            $rootNames = ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity'];
            $rootIds = Account::whereIn('name', $rootNames)->pluck('id');

            $accpayreceives = Account::doesntHave('children')
                ->with('root')
                ->whereHas('parent', function ($query) use ($rootIds) {
                    $query->whereIn('root_id', $rootIds);
                })
                ->get();

            $lastLevelAccounts = Account::doesntHave('children')
                ->with('root')
                ->whereHas('parent', function ($query) use ($rootIds) {
                    $query->whereIn('root_id', $rootIds);
                })
                ->get();

            $rootIds = Account::where('name', 'Liabilities')->pluck('id');
            $suppliers = Account::doesntHave('children')
                ->with('root')
                ->whereIn('root_id', $rootIds)
                ->get();
            $clients = \App\Models\Client::all();

            $refundNumbers = Refund::where('company_id', $user->company->id)
                ->where('branch_id', $user->branch->id)
                ->select('refund_number')
                ->get();
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }
        $unpaidInvoices = Invoice::where('status', 'unpaid')->get();
        $oldItems = old('items') ?? [];

        return view('receipt-voucher.create', compact(
            'accounts',
            'companies',
            'branches',
            'suppliers',
            'accpayreceives',
            'lastLevelAccounts',
            'refundNumbers',
            'clients',
            'unpaidInvoices',
            'oldItems'

        ));
    }

    public function store(Request $request)
    {

        if ($request->receiptvouchertype === 'PaymentByDate') {
            $receiptvoucherType = 'Receipt';
            $reconciledFlag = 2; //0 = no yet reconciled, 1 = the record that has been reconciled, 2 = reconciled record
            $reconciledProcess = 'yes';
        } elseif ($request->receiptvouchertype === 'Receipt') {
            $receiptvoucherType = 'Receipt';
            $reconciledFlag = 0;
            $reconciledProcess = 'no';
        } elseif ($request->receiptvouchertype === 'Refund') {
            $receiptvoucherType = 'Refund';
            $reconciledFlag = 0;
            $reconciledProcess = 'no';
            $totalNettRefund = Refund::where('refund_number', $request->refund_number)
                ->value('total_nett_refund');
        } else {
            $receiptvoucherType = 'Invoice';
            $reconciledFlag = 0;
            $reconciledProcess = 'no';
        }

        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'docdate' => 'required|date',
            'receiptvoucherref' => 'required|string',
            'receiptvouchertype' => 'required|string',
            'pay_to' => [
                'required',
                function ($attribute, $value, $fail) {
                    $accountExists = Account::where('name', $value)->exists();
                    $clientIdExists = Client::where('id', $value)->exists();

                    // Check concatenated name
                    $clientNameExists = \App\Models\Client::whereRaw(
                        "TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) = ?",
                        [$value]
                    )->exists();

                    if (!$accountExists && !$clientIdExists && !$clientNameExists) {
                        $fail('The selected pay to is invalid.');
                    }
                }
            ],
            'remarks_create' => 'required|string',
            'internal_remarks' => 'nullable|string',
            'remarks_fl' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.account_id' => ['nullable', 'exists:accounts,id'],
            'items.*.client_id' => ['nullable', 'exists:clients,id'],
            'items.*.remarks' => 'nullable|string',
            'items.*.currency' => 'nullable|string',
            'items.*.exchange_rate' => 'nullable|numeric',
            'items.*.amount' => 'nullable|numeric',
            'items.*.debit' => 'nullable|numeric',
            'items.*.credit' => 'nullable|numeric',
            'items.*.cheque_no' => 'nullable|string',
            'items.*.cheque_date' => 'nullable|date',
            'items.*.bank_name' => 'nullable|string',
            'items.*.branch' => 'nullable|string',
            'items.*.balance' => 'nullable|numeric',
        ], [
            'items.*.account_id.exists' => 'The selected account code does not exist.',
        ]);

        foreach ($request->items as $i => $item) {
            $hasClient = !empty($item['client_id']);
            $hasAccount = !empty($item['account_id']);
            $hasInvoice = !empty($item['invoice_id']);
            // Only error if none of the three is present
            if (!$hasClient && !$hasAccount && !$hasInvoice) {
                return back()->with('error', "Row " . ($i + 1) . ": Please select either Client Credit, A/C, or Invoice Number.");
            }
            // Prevent selecting both client and account (but allow invoice with either)
            if ($hasClient && $hasAccount) {
                return back()->with('error', "Row " . ($i + 1) . ": You cannot select both Client Credit and A/C. Please choose only one.");
            }
        }
        try {
            DB::beginTransaction();

            // Create Transaction Record
            $transaction = Transaction::create([
                'entity_id' => $request->company_id ?? auth()->user()->company->id,
                'entity_type' => 'company',
                'company_id' => $request->company_id ?? auth()->user()->company->id,
                'branch_id' => $request->branch_id ?? auth()->user()->branch->id,
                'transaction_type' => 'debit',
                'amount' => $request->receiptvouchertype === 'Refund'
                    ? $totalNettRefund
                    : abs($request->credit - $request->debit),
                'date' => \Carbon\Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                'description' => $request->remarks_create . ($request->refund_number ? ' | ' . $request->refund_number : ''),
                'description' => $request->receiptvouchertype === 'Refund'
                    ? 'Refund to Client - ' . $request->remarks_create . ($request->refund_number ? ' | ' . $request->refund_number : '')
                    : $request->remarks_create . ($request->refund_number ? ' | ' . $request->refund_number : ''),
                'invoice_id' => null,
                'reference_number' => $request->receiptvoucherref,
                'reference_type' => $receiptvoucherType,
                'name' => $request->pay_to,
                'remarks_internal' => $request->internal_remarks,
                'remarks_fl' => $request->remarks_fl,
                'transaction_date' => now(),

            ]);

            // Store JournalEntries
            foreach ($request->items as $item) {

                if (!empty($item['account_id'])) {

                    $accname = Account::where('id', $item['account_id'])->first();

                    $journalEntryRec = JournalEntry::create([
                        'transaction_date' => \Carbon\Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                        'account_id' => $item['account_id'],
                        'company_id' => $request->company_id ?? auth()->user()->company->id,
                        'branch_id' => $request->branch_id ?? auth()->user()->branch->id,
                        'transaction_id' => $transaction->id,
                        'description' => $request->receiptvouchertype === 'Refund'
                            ? 'Refund - ' . $item['remarks']
                            : $item['remarks'],
                        'debit' => $item['debit'] ?? 0,
                        'credit' => $item['credit'] ?? 0,
                        'balance' => $item['balance'] ?? 0,
                        'voucher_number' => $request->receiptvoucherref,
                        'name' => $accname->name ?? '',
                        'type' => 'payable',
                        'currency' => $item['currency'] ?? '',
                        'exchange_rate' => $item['exchange_rate'] ?? 0,
                        'amount' => $item['amount'] ?? 0,
                        'cheque_no' => $item['cheque_no'] ?? '',
                        'cheque_date' => $item['cheque_date'] ? \Carbon\Carbon::parse($item['cheque_date'])->format('Y-m-d H:i:s') : null,
                        'bank_info' => $item['bank_name'] ?? '',
                        'auth_no' => $item['auth_no'] ?? '',
                        'reconciled' => $reconciledFlag,
                    ]);

                    // Update selected journal entries 

                    if (!empty($item['transaction_id'])) {
                        $ids = array_filter(array_map('trim', explode(',', $item['transaction_id'])));
                        $selectedJournalEntryIds = array_unique(array_map('intval', $ids));

                        if (!empty($selectedJournalEntryIds)) {
                            JournalEntry::where('company_id', auth()->user()->company->id)
                                ->where('branch_id', auth()->user()->branch->id)
                                ->whereIn('id', $selectedJournalEntryIds)
                                ->where('reconciled', '!=', 2)
                                ->update([
                                    'reconciled' => 1,
                                    'reconciled_ref_id' => $journalEntryRec->id,
                                ]);
                        }
                    }
                }
            }
            foreach ($request->items as $item) {
                if (!empty($item['invoice_id'])) {
                    DB::table('invoice_receipt')->insert([
                        'invoice_id' => $item['invoice_id'],
                        'transaction_id' => $transaction->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            foreach ($request->items as $item) {
                if (!empty($item['invoice_id'])) {
                    $invoice = Invoice::find($item['invoice_id']);
                    if ($invoice && $invoice->status === 'unpaid') {
                        // Compare paid amount with invoice amount
                        $paidAmount = floatval($item['debit'] ?? $item['amount'] ?? 0);
                        if ($paidAmount >= floatval($invoice->amount)) {
                            $invoice->status = 'paid';
                            $invoice->paid_date = now();
                            $invoice->save();
                        } else {
                            // Optionally, set status to 'partial' or leave as 'unpaid'
                            $invoice->status = 'partial';
                            $invoice->save();
                        }
                    }
                }
            }
            foreach ($request->items as $item) {
                if (!empty($item['client_id']) && floatval($item['debit']) > 0) {
                    Log::info('Crediting client', ['client_id' => $item['client_id'], 'amount' => $item['amount']]);
                }
            }
            foreach ($request->items as $item) {
                if (!empty($item['client_id']) && floatval($item['amount']) > 0) {
                    $client = \App\Models\Client::find($item['client_id']);
                    $agentId = $client ? $client->agent_id : null;

                    $payment = new Payment([
                        'client_id' => $item['client_id'],
                        'agent_id' => $agentId,
                        'amount' => $item['amount'],
                        'voucher_number' => $request->receiptvoucherref,
                        'currency' => $item['currency'] ?? 'KWD',
                    ]);
                    $addCreditResponse = app(ClientController::class)->addCredit($payment);

                    Log::info('Add Credit Response: ' . json_encode($addCreditResponse));
                }
            }
            DB::commit();
            return redirect()->route('receipt-voucher.index')->with('success', 'Receipt Voucher Successfully Recorded.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        // $user = auth()->user();
        $receiptvoucher = Transaction::with('invoiceReceipts')->findOrFail($id);

        $JournalEntrys = JournalEntry::where('transaction_id', $receiptvoucher->id)->get();

        $user = auth()->user();
        if ($user->role_id == Role::ADMIN) {
            $companies = Company::with('branches.account', 'branches.agents')->get();
            $branches = $companies->flatMap->branches;
            $accounts = $branches->pluck('account')->filter();

            $rootNames = ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity'];
            $rootIds = Account::whereIn('name', $rootNames)->pluck('id');

            $accpayreceives = Account::doesntHave('children')
                ->whereHas('parent', function ($query) use ($rootIds) {
                    $query->whereIn('root_id', $rootIds);
                })
                ->get();

            $suppliers = Account::doesntHave('children')->get();
        } elseif ($user->role_id == Role::COMPANY) {
            $company = Company::with('branches.account', 'branches.agents')->find($receiptvoucher->entity_id);
            $accounts = $company->branches->pluck('account')->filter(); // get accounts from each branch
            $branches = $company->branches;
            $companies = $company;

            $rootNames = ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity'];
            $rootIds = Account::whereIn('name', $rootNames)->pluck('id');

            $accpayreceives = Account::doesntHave('children')
                ->whereHas('parent', function ($query) use ($rootIds) {
                    $query->whereIn('root_id', $rootIds);
                })
                ->get();

            $suppliers = Account::doesntHave('children')
                ->with('root')
                ->whereHas('parent', function ($query) use ($rootIds) {
                    $query->whereIn('root_id', $rootIds);
                })
                ->get();
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        return view('receipt-voucher.edit', compact('companies', 'receiptvoucher', 'accounts', 'branches', 'suppliers', 'accpayreceives', 'JournalEntrys'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'receiptvoucherref' => 'required|string',
            'docdate' => 'required|date',
            'pay_to' => [
                'required',
                function ($attribute, $value, $fail) {
                    $accountExists = \App\Models\Account::where('name', $value)->exists();
                    $clientIdExists = \App\Models\Client::where('id', $value)->exists();

                    // Check concatenated name
                    $clientNameExists = \App\Models\Client::whereRaw(
                        "TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) = ?",
                        [$value]
                    )->exists();

                    if (!$accountExists && !$clientIdExists && !$clientNameExists) {
                        $fail('The selected pay to is invalid.');
                    }
                }
            ],
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


        foreach ($request->items as $i => $item) {
            $hasClient = !empty($item['client_id']);
            $hasAccount = !empty($item['account_id']);
            $hasInvoice = !empty($item['invoice_id']);
            // Only error if none of the three is present
            if (!$hasClient && !$hasAccount && !$hasInvoice) {
                return back()->with('error', "Row " . ($i + 1) . ": Please select either Client Credit, A/C, or Invoice Number.");
            }
            // Prevent selecting both client and account (but allow invoice with either)
            if ($hasClient && $hasAccount) {
                return back()->with('error', "Row " . ($i + 1) . ": You cannot select both Client Credit and A/C. Please choose only one.");
            }
        }
        try {
            DB::beginTransaction();

            $transaction = Transaction::findOrFail($id);
            $transaction->update([
                'branch_id' => $request->branch_id,
                'transaction_type' => 'debit',
                'amount' => collect($request->items)->sum('amount'),
                'date' => \Carbon\Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                'description' => $request->remarks_create,
                'reference_type' => $request->receiptvouchertype,
                'invoice_id' => null,
                'reference_number' => $request->receiptvoucherref,
                'name' => $request->pay_to,
                'remarks_internal' => $request->internal_remarks,
                'remarks_fl' => $request->remarks_fl,
                'updated_at' => now(),

            ]);

            // Remove old general ledger entries and insert new ones
            JournalEntry::where('transaction_id', $id)->delete();
            $this->storeJournalEntryEntries($request->items, $request, $id);

            DB::commit();
            return redirect()->back()->with('success', 'Receipt Voucher Updated Successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    private function storeJournalEntryEntries($items, $request, $transactionId)
    {
        foreach ($items as $item) {

            // Retrieve company_id from the related account
            $account = Account::find($item['account_id']);
            $companyId = $account ? $account->company_id : null; // Ensure company_id exists

            JournalEntry::create([
                'transaction_date' => \Carbon\Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                'account_id' => $item['account_id'],
                'company_id' => $companyId,
                'branch_id' => $request->branch_id ?? 0,
                'transaction_id' => $transactionId,
                'description' => $item['description'],
                'debit' => $item['debit'],
                'credit' => $item['credit'],
                'balance' => $item['balance'] ?? 0,
                'voucher_number' => $request->receiptvoucherref,
                'name' => $request->pay_to,
                'type' => 'payable',
                'currency' => $item['currency'],
                'exchange_rate' => $item['exchange_rate'],
                'amount' => $item['amount'],
                'cheque_no' => $item['cheque_no'] ?? '',
                'cheque_date' => $item['cheque_date'] ? \Carbon\Carbon::parse($item['cheque_date'])->format('Y-m-d H:i:s') : null,
                'bank_info' => $item['bank_name'] ?? '',
                'auth_no' => $item['auth_no'] ?? '',
                'updated_at' => now(),
                'type_reference_id' => $item['type_reference_id'],
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
        $user = auth()->user();

        $accountIds = [];
        $supplierNameTrimmed = trim($supplierName);
        if ($supplierNameTrimmed !== '') {
            $acc = Account::where('name', $supplierNameTrimmed)->first()
                ?? Account::where('name', 'LIKE', "%{$supplierNameTrimmed}%")->first();

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
            ->where('journal_entries.company_id', $user->company->id)
            ->where('journal_entries.branch_id', $user->branch->id)
            ->whereBetween('journal_entries.transaction_date', [$request->from, $request->to])
            ->whereIn('root_a.name', ['Liabilities'])
            ->when(!empty($accountIds), fn($q) => $q->whereIn('journal_entries.account_id', $accountIds));

        $totalsByAccount = $totalsByAccountQuery
            ->groupBy('journal_entries.account_id')
            ->get()
            ->filter(fn($e) => $e->total > 0)
            ->pluck('total', 'account_id');

        $entriesQuery = \App\Models\JournalEntry::whereIn('account_id', $totalsByAccount->keys())
            ->where('company_id', $user->company->id)
            ->where('branch_id', $user->branch->id)
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

    public function approve($id)
    {
        $receiptvoucher = Transaction::findOrFail($id);

        if ($receiptvoucher->invoice_id) {
            $invoice = \App\Models\Invoice::find($receiptvoucher->invoice_id);
            if ($invoice) {
                $invoice->status = 'paid';
                $invoice->save();
            }
        }

        return redirect()->route('receipt-voucher.index')->with('success', 'Invoice marked as paid.');
    }
}
