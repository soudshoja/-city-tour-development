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

class BankPaymentController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if($user->role_id == Role::ADMIN){
            $bankPayments = Transaction::all();
            $totalRecords = Transaction::count();

        }elseif ($user->role_id == Role::COMPANY) {

            $companyId = Company::where('user_id', $user->id)->value('id'); // Get the company ID
            $branch = Branch::where('company_id', $companyId)->get();

            $branchesId = $branch->pluck('id')->toArray(); 

            $bankPayments = Transaction::whereIn('branch_id', $branchesId)
            ->whereNotNull('name')
            ->where('reference_number', 'like', 'PV-%')
            ->latest()
            ->paginate(10);
        
            $totalRecords = Transaction::whereIn('branch_id', $branchesId)
            ->whereNotNull('name')
            ->where('reference_number', 'like', 'PV-%')
            ->count();

        }else{
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        return view('bank-payments.index', compact('bankPayments','totalRecords'));
    }


    public function create()
    {   
        $user = auth()->user();
        if($user->role_id == Role::ADMIN){
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

            $suppliers = Account::doesntHave('children')
                ->with('root') 
                ->whereHas('parent', function ($query) use ($rootIds) {
                    $query->whereIn('root_id', $rootIds);
                })
                ->get();

            $refundNumbers = Refund::select('refund_number')->get();


            
        }elseif ($user->role_id == Role::COMPANY) {
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

            $suppliers = Account::doesntHave('children')
                ->with('root') 
                ->whereHas('parent', function ($query) use ($rootIds) {
                    $query->whereIn('root_id', $rootIds);
                })
                ->get();

            $refundNumbers = Refund::where('company_id', $user->company->id)
                ->where('branch_id', $user->branch->id)
                ->select('refund_number')
                ->get();

                
            $validAccountIds = DB::table('journal_entries')
                ->select('account_id')
                ->where('company_id', $user->company->id)
                ->where('branch_id', $user->branch->id)
                ->groupBy('account_id')
                ->havingRaw('SUM(debit - credit) > 0')
                ->pluck('account_id');

            $chkOutstandingDebitForPayment = JournalEntry::whereIn('account_id', $validAccountIds)
                ->where('company_id', $user->company->id)
                ->where('branch_id', $user->branch->id)
                ->whereHas('account.root', function ($q) {
                    $q->whereIn('name', ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity']);
                })
                ->with(['account', 'account.root'])
                ->orderBy('transaction_date')
                ->get();



        }else{
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        return view('bank-payments.create', compact('accounts', 'companies', 'branches', 'suppliers', 'accpayreceives', 'lastLevelAccounts', 'refundNumbers', 'chkOutstandingDebitForPayment'));

    }

    /**
     * Store bank payment transaction.
     */
    public function store(Request $request)
    {   
        //dd($request);
        $rootNames = ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity'];
        $rootIds = Account::whereIn('name', $rootNames)->pluck('id');

        $accountMap = Account::doesntHave('children')
            ->whereHas('parent', function ($query) use ($rootIds) {
                $query->whereIn('root_id', $rootIds);
            })
            ->pluck('id', 'name')
            ->toArray();

        $modifiedItems = collect($request->items)->map(function ($item) use ($accountMap) {
            if (isset($accountMap[$item['ac_code']])) {
                $item['ac_code'] = $accountMap[$item['ac_code']];
            }
            return $item;
        })->toArray();

        // Replace request data
        $request->merge(['items' => $modifiedItems]);
        //dd($request->bankpaymenttype);

        if ($request->bankpaymenttype === 'PaymentByDate') {
            $bankPaymentType = 'Payment';
        } elseif ($request->bankpaymenttype === 'Payment') {
            $bankPaymentType = 'Payment';
        } elseif ($request->bankpaymenttype === 'Refund') {
            $bankPaymentType = 'Refund';
        } else {
            $bankPaymentType = 'Invoice'; 
        }
        
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'docdate' => 'required|date',
            'bankpaymentref' => 'required|string',
            'bankpaymenttype' => 'required|string',
            'pay_to' => ['required', 'exists:accounts,name'],
            'remarks_create' => 'required|string',
            'internal_remarks' => 'nullable|string',
            'remarks_fl' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.ac_code' => ['nullable', 'exists:accounts,id'],
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
            'items.*.ac_code.exists' => 'The selected account code does not exist.', 
            'items.*.account_id.exists' => 'The selected account code does not exist.', 
        ]);

        try {
            DB::beginTransaction();

            // Create Transaction Record
            $transaction = Transaction::create([
                'entity_id' => $request->company_id ?? auth()->user()->company->id,
                'entity_type' => 'company',
                'company_id' => $request->company_id ?? auth()->user()->company->id,
                'branch_id' => $request->branch_id ?? auth()->user()->branch->id,
                'transaction_type' => 'debit',
                'amount' => ($item['credit'] ?? 0) - ($item['debit'] ?? 0),
                'date' => \Carbon\Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                'description' => $request->remarks_create . ($request->refund_number ? ' | ' . $request->refund_number : ''),
                'description' => $request->bankpaymenttype === 'Refund'
                ? 'Client Refund Payable - ' . $request->remarks_create . ($request->refund_number ? ' | ' . $request->refund_number : '')
                : $request->remarks_create . ($request->refund_number ? ' | ' . $request->refund_number : ''),
                'invoice_id' => null,
                'reference_number' => $request->bankpaymentref,
                'reference_type' => $bankPaymentType,
                'name' => $request->pay_to,
                'remarks_internal' => $request->internal_remarks,
                'remarks_fl' => $request->remarks_fl,
                
            ]);

            // Store General Ledger Entries
            foreach ($request->items as $item) {
                $accname = Account::where('id', $item['ac_code'])->first();

                JournalEntry::create([
                    'transaction_date' => \Carbon\Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                    'account_id' => $item['ac_code'],
                    'company_id' => $request->company_id ?? auth()->user()->company->id,
                    'branch_id' => $item['branch'] ?? auth()->user()->branch->id,
                    'transaction_id' => $transaction->id,
                    //'transaction_id' => $item['transaction_id'],
                    'description' => $item['remarks'] ?? '',
                    'debit' => $item['debit'] ?? 0,
                    'credit' => $item['credit'] ?? 0,
                    'balance' => $item['balance'] ?? 0,
                    'voucher_number' => $request->bankpaymentref,
                    'name' => $accname->name ?? '',
                    'type' => 'payable',
                    'currency' => $item['currency'] ?? '',
                    'exchange_rate' => $item['exchange_rate'] ?? 0,
                    'amount' => $item['amount'] ?? 0,
                    'cheque_no' => $item['cheque_no'] ?? '',
                    'cheque_date' => $item['cheque_date'] ? \Carbon\Carbon::parse($item['cheque_date'])->format('Y-m-d H:i:s'): null,
                    'bank_info' => $item['bank_name'] ?? '',
                    'auth_no' => $item['auth_no'] ?? '',
                ]);
            }

            DB::commit();
            return redirect()->route('bank-payments.index')->with('success', 'Payment Voucher Successfully Recorded.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }


    public function edit($id)
    {
        // $user = auth()->user();
        $bankPayment = Transaction::findOrFail($id);
        $JournalEntrys = JournalEntry::where('transaction_id', $bankPayment->id)->get();
        
        $user = auth()->user();
        if($user->role_id == Role::ADMIN){
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

            
        }elseif ($user->role_id == Role::COMPANY) {
            $company = Company::with('branches.account', 'branches.agents')->find($bankPayment->entity_id);
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

        }else{
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        return view('bank-payments.edit', compact('companies','bankPayment', 'accounts', 'branches', 'suppliers', 'accpayreceives', 'JournalEntrys'));

    }


    public function update(Request $request, $id)
    {

        //dd($request->all());
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
                'date' => \Carbon\Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
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
                'voucher_number' => $request->bankpaymentref,
                'name' => $request->pay_to,
                'type' => 'payable',
                'currency' => $item['currency'],
                'exchange_rate' => $item['exchange_rate'],
                'amount' => $item['amount'],
                'cheque_no' => $item['cheque_no'] ?? '',
                'cheque_date' => $item['cheque_date'] ? \Carbon\Carbon::parse($item['cheque_date'])->format('Y-m-d H:i:s'): null,
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
            'to' => 'required|date|after_or_equal:from',
        ]);
   
        $user = auth()->user();

        $validAccountIds = DB::table('journal_entries')
        ->select('account_id')
        ->where('company_id', $user->company->id)
        ->where('branch_id', $user->branch->id)
        ->whereBetween('transaction_date', [$request->from, $request->to])
        ->groupBy('account_id')
        ->havingRaw('SUM(debit - credit) > 0')
        ->pluck('account_id');

        $entries = JournalEntry::whereIn('account_id', $validAccountIds)
            ->where('company_id', $user->company->id)
            ->where('branch_id', $user->branch->id)
            ->whereBetween('transaction_date', [$request->from, $request->to])
            ->whereHas('account.root', function ($q) {
                $q->whereIn('name', ['Assets', 'Liabilities', 'Income', 'Expenses', 'Equity']);
            })
            ->with(['account', 'account.root'])
            ->orderBy('transaction_date')
            ->get();

        $payments  = $entries->map(function ($entry) {
            return [
                'id'               => $entry->id,
                'transaction_id'   => $entry->transaction_id,
                'transaction_date' => $entry->transaction_date,
                'account_id'       => $entry->account_id,
                'account_code'     => $entry->account->code ?? '',
                'account_name'     => $entry->account->name ?? '',
                'root_name'        => $entry->account->root->name ?? 'No Root',
                'name'             => $entry->name,
                'description'      => $entry->description,
                'debit'            => $entry->debit,
                'credit'           => $entry->credit,
            ];
        });

    return response()->json($payments);
    }
    


}