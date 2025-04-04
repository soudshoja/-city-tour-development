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

class BankPaymentController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $companyId = Company::where('user_id', $user->id)->value('id'); // Get the company ID

        if($user->role_id == Role::ADMIN){
            $bankPayments = Transaction::all();
            $totalRecords = Transaction::count();

        }elseif ($user->role_id == Role::COMPANY) {
            $bankPayments = Transaction::where('entity_id', $companyId)
            ->where('reference_type', 'Payment')
            ->latest()
            ->paginate(10);

            $totalRecords = Transaction::where('entity_id', $companyId)
            ->where('reference_type', 'Payment')
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
            $parentIds = Account::where('name', 'LIKE', '%Payable%')
            ->orWhere('name', 'LIKE', '%Receivable%')
            ->pluck('id');
            $accpayreceives = Account::whereIn('parent_id', $parentIds)->get();

            $lastLevelAccounts = Account::doesntHave('children')->get();

            $parentIdsSuppliers = Account::where('name', 'LIKE', '%Payable%')
            ->pluck('id');
            $suppliers = Account::whereIn('parent_id', $parentIdsSuppliers)->get();

            
        }elseif ($user->role_id == Role::COMPANY) {
            $company = Company::with('branches.agents')->find($user->company->id);
            $accounts = $company->branches->flatMap->accounts;
            $branches = $company->branches;
            $companies = $company;

            $parentIds = Account::where('name', 'LIKE', '%Payable%')
            ->orWhere('name', 'LIKE', '%Receivable%')
            ->pluck('id');
            $accpayreceives = Account::whereIn('parent_id', $parentIds)->get();

            $lastLevelAccounts = Account::doesntHave('children')->get();

            $parentIdsSuppliers = Account::where('name', 'LIKE', '%Payable%')
            ->pluck('id');
            $suppliers = Account::whereIn('parent_id', $parentIdsSuppliers)->get();


        }else{
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        return view('bank-payments.create', compact('accounts', 'companies', 'branches', 'suppliers', 'accpayreceives', 'lastLevelAccounts'));

    }

    /**
     * Store bank payment transaction.
     */
    public function store(Request $request)
    {   
        //dd($request->all());

       // Fetch all account names and their IDs
        $accountMap = Account::pluck('id', 'name')->toArray(); 

        // Modify request data: Replace account name with ID
        $modifiedItems = collect($request->items)->map(function ($item) use ($accountMap) {
            if (isset($accountMap[$item['ac_code']])) {
                $item['ac_code'] = $accountMap[$item['ac_code']]; // Replace name with ID
            }
            return $item;
        })->toArray();

        // Replace request data
        $request->merge(['items' => $modifiedItems]);

        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'bankpaymentref' => 'required|string',
            'docdate' => 'required|date',
            'pay_to' => ['required', 'exists:accounts,name'],
            'remarks_create' => 'required|string',
            'internal_remarks' => 'nullable|string',
            'remarks_fl' => 'nullable|string',
            'account_id' => 'nullable|exists:accounts,id',
            'items' => 'required|array|min:1',
            'items.*.ac_code' => ['required', 'exists:accounts,id'],
            'items.*.remarks' => 'required|string',
            'items.*.currency' => 'required|string',
            'items.*.exchange_rate' => 'required|numeric',
            'items.*.amount' => 'required|numeric',
            'items.*.debit' => 'required|numeric',
            'items.*.credit' => 'required|numeric',
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
                'entity_id' => $request->company_id,
                'entity_type' => 'company',
                'branch_id' => $request->branch_id,
                'transaction_type' => 'debit',
                'amount' => array_sum(array_column($request->items, 'amount')),
                'date' => $request->docdate,
                'description' => $request->remarks_create,
                'reference_type' => 'Payment',
                'invoice_id' => null,
                'reference_number' => $request->bankpaymentref,
                'name' => $request->pay_to,
                'remarks_internal' => $request->internal_remarks,
                'remarks_fl' => $request->remarks_fl,
                
            ]);

            // Store General Ledger Entries
            foreach ($request->items as $item) {
                JournalEntry::create([
                    'transaction_date' => $request->docdate,
                    'account_id' => $item['ac_code'],
                    'company_id' => $request->company_id,
                    'branch_id' => $item['branch'] ?? 0,
                    'transaction_id' => $transaction->id,
                    'description' => $item['remarks'] ?? '',
                    'debit' => $item['debit'] ?? 0,
                    'credit' => $item['credit'] ?? 0,
                    'balance' => $item['balance'] ?? 0,
                    'voucher_number' => $request->bankpaymentref,
                    'name' => $request->pay_to ?? '',
                    'type' => 'payable',
                    'currency' => $item['currency'] ?? '',
                    'exchange_rate' => $item['exchange_rate'] ?? 0,
                    'amount' => $item['amount'] ?? 0,
                    'cheque_no' => $item['cheque_no'] ?? '',
                    'cheque_date' => $item['cheque_date'] ?? '',
                    'bank_info' => $item['bank_name'] ?? '',
                    'auth_no' => $item['auth_no'] ?? '',
                ]);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Bank Payment Successfully Recorded.');

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
            $company = Company::with('branches.accounts', 'branches.agents')->find($bankPayment->entity_id);
            $accounts = $company->branches->flatMap->accounts;            
            $branches = $company->branches;
            $companies = $company;

            $parentIds = Account::where('name', 'LIKE', '%Payable%')
            ->orWhere('name', 'LIKE', '%Receivable%')
            ->pluck('id');
            $accpayreceives = Account::whereIn('parent_id', $parentIds)->get();

            $parentIdsSuppliers = Account::where('name', 'LIKE', '%Payable%')
            ->pluck('id');
            $suppliers = Account::whereIn('parent_id', $parentIdsSuppliers)->get();

            
        }elseif ($user->role_id == Role::COMPANY) {
            $company = Company::with('branches.accounts', 'branches.agents')->find($bankPayment->entity_id);
            $accounts = $company->branches->flatMap->accounts;
            $branches = $company->branches;
            $companies = $company;
            
            $parentIds = Account::where('name', 'LIKE', '%Payable%')
            ->orWhere('name', 'LIKE', '%Receivable%')
            ->pluck('id');
            $accpayreceives = Account::whereIn('parent_id', $parentIds)->get();

            $parentIdsSuppliers = Account::where('name', 'LIKE', '%Payable%')
            ->pluck('id');
            $suppliers = Account::whereIn('parent_id', $parentIdsSuppliers)->get();


        }else{
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }


        // return view('bank-payments.edit', compact('bankPayment', 'accounts', 'branches', 'suppliers', 'accpayreceives', 'JournalEntrys'));

        // $bankPayment = Transaction::findOrFail($id);
        // $accounts = Account::all();
        // $branches = Branch::all();
        // $accpayreceives = $this->getPayableReceivableAccounts();
        // $suppliers = $this->getSupplierAccounts();
        // $JournalEntrys = JournalEntry::where('transaction_id', $bankPayment->id)->get();

        return view('bank-payments.edit', compact('bankPayment', 'accounts', 'branches', 'suppliers', 'accpayreceives', 'JournalEntrys'));
    

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
                'date' => $request->docdate,
                'description' => $request->remarks_create,
                'reference_type' => 'Payment',
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
            return redirect()->back()->with('success', 'Bank Payment Updated Successfully.');

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
                'transaction_date' => $request->docdate,
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
                'cheque_date' => $item['cheque_date'] ?? '',
                'bank_info' => $item['bank_name'] ?? '',
                'auth_no' => $item['auth_no'] ?? '',
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Get payable and receivable accounts.
     */
    private function getPayableReceivableAccounts()
    {
        return Account::whereIn('parent_id', Account::where('name', 'LIKE', '%Payable%')
            ->orWhere('name', 'LIKE', '%Receivable%')
            ->pluck('id'))->get();
    }

    /**
     * Get supplier accounts.
     */
    private function getSupplierAccounts()
    {
        return Account::whereIn('parent_id', Account::where('name', 'LIKE', '%Payable%')->pluck('id'))->get();
    }


}