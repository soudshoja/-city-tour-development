<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
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
use App\Models\InvoiceReceipt;
use App\Models\InvoicePartial;
use App\Models\InvoiceDetail;
use App\Models\Task;
use App\Models\Agent;
use App\Models\Credit;
use Exception;
use Throwable;
use Carbon\Carbon;
class ReceiptVoucherController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->role_id == Role::ADMIN) {
            $invoicereceiptvouchers = Transaction::all();
            $totalRecords = Transaction::count();
        } elseif ($user->role_id == Role::COMPANY) {

            $companyId = Company::where('user_id', $user->id)->value('id'); // Get the company ID
            $branch = Branch::where('company_id', $companyId)->get();

            $branchesId = $branch->pluck('id')->toArray();
            $invoicereceiptvouchers = Transaction::with('invoiceReceipt')
                ->whereIn('branch_id', $branchesId)
                ->whereNotNull('name')
                ->where('reference_number', 'like', 'RV-%')
                ->get();

            $totalRecords = Transaction::whereIn('branch_id', $branchesId)
                ->whereNotNull('name')
                ->where('reference_number', 'like', 'RV-%') // <-- change PV-% to RV-%
                ->count();

        } elseif ($user->role_id == Role::AGENT) {
            $branchId = $user->branch_id;
            $invoicereceiptvouchers = Transaction::where('branch_id', $branchId)
                ->whereNotNull('name')
                ->where('reference_number', 'like', 'RV-%') // <-- change PV-% to RV-%
                ->latest()
                ->paginate(10);

            $totalRecords = Transaction::where('branch_id', $branchId)
                ->whereNotNull('name')
                ->where('reference_number', 'like', 'RV-%') // <-- change PV-% to RV-%
                ->count();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $companyId = $user->accountant->branch->company->id;
            
            $branch = Branch::where('company_id', $companyId)->get();

            $branchesId = $branch->pluck('id')->toArray();
            
            $invoicereceiptvouchers = Transaction::with('invoiceReceipt')
                ->whereIn('branch_id', $branchesId) 
                ->whereNotNull('name')
                ->where('reference_number', 'like', 'RV-%')
                ->latest() 
                ->paginate(10); 

            $totalRecords = Transaction::whereIn('branch_id', $branchesId) // <-- CORRECTED
                ->whereNotNull('name')
                ->where('reference_number', 'like', 'RV-%')
                ->count();
        } else {
            return redirect()->route('dashboard')->with('error', 'Page not found.');
        }

        return view('receipt-voucher.index', compact('invoicereceiptvouchers', 'totalRecords'));
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
        } elseif ($user->role_id == Role::ACCOUNTANT) {
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
                ->where('branch_id', $user->accountant->branch->id)
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
        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'required|exists:branches,id',
            'docdate' => 'required|date',
            'receiptvoucherref' => 'required|string',
            'receiptvouchertype' => 'required|string',
            'remarks_create' => 'required|string',
            'internal_remarks' => 'nullable|string',
            'remarks_fl' => 'nullable|string',
            'pay_to' => [
                'required',
                function ($attribute, $value, $fail) {
                    $accountExists = Account::where('name', $value)->exists();
                    $clientIdExists = Client::where('id', $value)->exists();

                    // Check concatenated name
                    $clientNameExists = Client::whereRaw(
                        "TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) = ?",
                        [$value]
                    )->exists();

                    if (!$accountExists && !$clientIdExists && !$clientNameExists) {
                        $fail('The selected pay to is invalid.');
                    }
                }
            ],
            'items.*.type_selector' => 'nullable|string',
            'items.*.invoice_id' => 'nullable|string',
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

        $items = $data['items'][0];

        foreach ($request->items as $i => $item) {
            $hasClient = !empty($item['client_id']);
            $hasAccount = !empty($item['account_id']);
            $hasInvoice = !empty($item['invoice_id']);
            $hasImport = isset($item['type_selector']) && $item['type_selector'] === 'import'; 
            if (!$hasClient && !$hasAccount && !$hasInvoice && !$hasImport) {
                return back()->with('error', "Row " . ($i + 1) . ": Please select either Client Credit, A/C, Invoice Number or Import.");
            }
            if ($hasClient && $hasAccount) {
                return back()->with('error', "Row " . ($i + 1) . ": You cannot select both Client Credit and A/C. Please choose only one.");
            }
        }

        $type = $items['type_selector'] ?? null;
        $amount = (float)($items['debit'] > 0 ? $items['debit'] : ($items['credit'] > 0 ? $items['credit'] : ($items['amount'] ?? 0)));        $invoiceId = $items['invoice_id'] ?? null;
        $companyId = $data['company_id'];
        $branchId = $data['branch_id'];

        try {
            DB::beginTransaction(); 

            if ($type == 'account') {
                Log::info('Starting to create Receipt Voucher for Account with Receipt Reference: ' . $request->receiptvocuherref);

                $account = Account::where('id', $items['account_id'])->first();
                if (!$account) {
                    Log::error('Account not found');
                }

                $type = (!empty($items['debit']) && (float)$items['debit'] > 0)
                    ? 'debit'
                    : ((!empty($items['credit']) && (float)$items['credit'] > 0)
                        ? 'credit'
                        : 'unknown');

                $transaction = Transaction::create([
                    'entity_id'         => $companyId,
                    'entity_type'       => 'company',
                    'company_id'        => $companyId,
                    'branch_id'         => $branchId,
                    'transaction_type'  => $type, 
                    'amount'            => $amount,
                    'date'              => \Carbon\Carbon::parse($request->docdate)->format('Y-m-d H:i:s'),
                    'description'       => 'Cash Payment for Account: '. $account->name . '. Additional Remarks: ' . $request->remarks_create,
                    'invoice_id'        => null,
                    'reference_number'  => $request->receiptvoucherref,
                    'reference_type'    => 'Account',
                    'name'              => $request->pay_to,
                    'remarks_internal'  => $request->internal_remarks,
                    'remarks_fl'        => $request->remarks_fl,
                    'transaction_date'  => now(),
                ]);
                if (!$transaction) {
                    Log::error("Failed to create {$type} transaction");
                    return back()->with('error', 'Failed to create transaction');
                }

                $invoiceReceipt = InvoiceReceipt::create([
                    'type' => 'account',
                    'account_id' => $account->id,
                    'transaction_id' => $transaction->id,
                    'amount' => $request->total_payment,
                    'status' => 'pending',
                    'is_used' => false,
                ]);

                if (!$invoiceReceipt) {
                     Log::error('Failed to create Invoice Receipt record', [
                        'account_id' => $account->id,
                        'transaction_id' => $transaction->id
                    ]);
                }

                Log::info('Successfully created Receipt Voucher for Account: ' . $account->name . ' with ID: ' . $invoiceReceipt->id);
            } elseif ($type == 'invoice') {

                $invoice = Invoice::where('id', $invoiceId)->first();
                if (!$invoice) {
                    Log::error('Invoice is not found');
                }        

                $invoiceDetail = InvoiceDetail::where('invoice_number', $invoice->invoice_number)->first();
                if (!$invoiceDetail) {
                    Log::error('Invoice detail not found', ['invoice_number' => $invoice->invoice_number]);
                    return ['status' => 'error', 'message' => 'Invoice detail not found'];
                }

                $invoicePartial = InvoicePartial::where('invoice_id', $invoiceId)->first();
                if (!$invoicePartial) {
                    Log::error('Invoice Partial is not found');

                    $new = InvoicePartial::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'client_id' => $invoice->client_id,
                        'service_charge' => 0,
                        'amount' => $invoice->amount,
                        'status' => $invoice->status,
                        'expiry_date' => $invoice->due_date,  
                        'type' => 'full',    
                        'charge_id' => null,
                        'payment_gateway' => 'Cash',
                        'payment_method' => null,
                        'payment_id' => null,
                        'receipt_voucher_id' => null,
                    ]);

                    if (!$new) {
                        Log::error('Failed to create Invoice Partial for Invoice ID: ' . $invoice->id);
                        return redirect()->back()->with('error', 'Failed to create the missing Invoice Partial');
                    }

                    $invoicePartial = $new;
                }

                $client = Client::find($invoice->client_id);
                if (!$client) {
                    Log::error('Client not found', ['client_id' => $invoice->client_id]);
                    return ['status' => 'error', 'message' => 'Client not found'];
                }
                
                $transaction = Transaction::where('invoice_id', $invoiceId)
                                ->first();
                if (!$transaction) {
                    $transaction = Transaction::create([
                        'entity_id' => $invoice->agent->branch->company->id,
                        'entity_type' => 'company',
                        'company_id' => $invoice->agent->branch->company->id,
                        'branch_id' => $invoice->agent->branch->id,
                        'transaction_type' => 'cash',
                        'amount' => $invoice->amount,
                        'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
                        'invoice_id' => $invoice->id,
                        'reference_type' => 'Invoice',
                        'name' => $client->name,
                        'transaction_date' => $invoice->invoice_date,
                    ]);

                    if (!$transaction) {
                        Log::error('error', 'Failed to create Transaction with ID: ', [
                            'transaction_id' => $transaction->id
                        ]);
                        
                        return redirect()->back()->with('error', 'Failed to create Transaction record for ' . $invoice->invoice_number);
                    }

                    $uninvoiced = $this->invoiceJournalEntry($transaction, $invoice);
                    
                    if (!is_array($uninvoiced) || !isset($uninvoiced['status']) || $uninvoiced['status'] === 'error') {
                        Log::error('Failed to create journal entry during full payment', [
                            'invoice_id' => $invoice->id ?? null,
                            'transaction_id' => $transaction->id ?? null,
                            'response' => $uninvoiced,
                        ]);

                        return redirect()->back()->with('error', $journal['message'] ?? 'Failed to create journal entry');
                    }
                }

                try {
                    if ($invoicePartial) {
                        $invoicePartial->update([
                            'amount' => $amount,
                            'expiry_date' => null,
                            'charge_id' => null,
                            'type' => 'full',
                            'payment_gateway' => 'Cash',
                            'payment_method' => null,
                            'updated_at' => now(),
                        ]);

                        Log::info('Invoice Partial updated to full payment', [
                            'invoice_partial_id' => $invoicePartial->id,
                            'invoice_id' => $invoicePartial->invoice_id,
                            'status' => $invoicePartial->status,
                        ]);
                    } 

                    $transaction = Transaction::create([
                        'entity_id' => $request->company_id ?? $invoice->agent->branch->company->id,
                        'entity_type' => 'company',
                        'company_id' => $request->company_id ?? $invoice->agent->branch->company->id,
                        'branch_id' => $request->branch_id ?? $invoice->agent->branch->id,
                        'transaction_type' => 'debit',
                        'amount' => $amount,
                        'description' => 'Payment for Invoice ' . $invoice->invoice_number . '. Additional Remarks: ' . $request->remarks_create,
                        'invoice_id' => $invoiceId,
                        'reference_number' => $request->receiptvoucherref,
                        'reference_type' => 'Invoice', 
                        'name' => $request->pay_to,
                        'transaction_date' => $request->docdate,
                    ]);

                    if (!$transaction) {
                        Log::error('error', 'Failed to create Transaction with ID: ', [
                            'transaction_id' => $transaction->id
                        ]);
                        
                    }

                    $invoiceReceipt = InvoiceReceipt::create([
                        'type' => 'invoice',
                        'invoice_id' => $invoiceId,
                        'transaction_id' => $transaction->id,
                        'amount' => $amount,
                        'status' => 'pending',
                        'is_used' => false,
                    ]);
                
                    if (!$invoiceReceipt) {
                        Log::error('Failed to create Invoice Receipt record', [
                            'invoice_id' => $invoiceId,
                            'transaction_id' => $transaction->id
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('Failed to process Receipt Voucher for Invoice: ' . $invoiceId, [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);          

                    return redirect()->back()->with('error', 'Failed to create Receipt Voucher');
                }
                                
                Log::info('Successfully created Receipt Voucher for Invoice ID: ' . $invoice->id);

            } elseif ($type == 'credit') {

                $addCreditResponse = $this->receiptVoucherCredit($data);
                if (isset($addCreditResponse['error'])) {
                    Log::error('Failed to add credit to client from Receipt Voucher', [
                        'message' => $addCreditResponse['error'],
                    ]);

                    return redirect()->back()->with('error', 'Failed to add credit');
                }

                Log::info('Succesfully add credit to client through Receipt Voucher: ', [
                    'response' => $addCreditResponse
                ]);
                
            } elseif ($type == 'import') {
                Log::info('Starting to create receipt voucher for import');

                try {
                    $transaction = Transaction::create([
                        'entity_id'         =>  $data['company_id'],
                        'entity_type'       => 'company',
                        'company_id'        =>  $data['company_id'],
                        'branch_id'         =>  $data['branch_id'],
                        'transaction_type'  => 'payment', // 'debit' or 'credit'
                        'amount'            => $amount,
                        'date'              => $request->docdate,
                        'description'       => 'Cash Payment via Receipt Voucher. Additional Remarks: ' . $request->remarks_create,
                        'invoice_id'        => null,
                        'reference_number'  => $request->receiptvoucherref,
                        'reference_type'    => 'Import',
                        'name'              => $request->pay_to,
                        'remarks_internal'  => $request->internal_remarks,
                        'remarks_fl'        => $request->remarks_fl,
                        'transaction_date'  => now(),
                    ]);

                    $invoiceReceipt = InvoiceReceipt::create([
                        'type' => 'import',
                        'transaction_id' => $transaction->id,
                        'amount' => $amount,
                        'status' => 'pending',
                        'is_used' => false,
                    ]);

                } catch (Exception $e) {
                    Log::error('Failed to create Receipt Voucher', [
                        'response' => $e->getMessage(),
                    ]);

                    return redirect()->back()->with('error', 'Failed to create Receipt Voucher');
                }

                Log::info('Successfully created Cash Receipt Voucher with ID: ' . $invoiceReceipt->id);
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
        $transaction = Transaction::findOrFail($id);
        $transactionId = $transaction->id;

        $invoiceReceipt = InvoiceReceipt::where('transaction_id', $transactionId)->first();
        if (!$invoiceReceipt) {
            Log::error('Invoice Receipt not exist');
            return redirect()->back()->with('error', 'Invoice Receipt not found');
        }

        $companyId = $transaction->company_id;
        $branchId = $transaction->company_id;
        $type = $invoiceReceipt->type;
        $amount = (float) $transaction->amount;

        if ($type == 'account') {

            $type = strtolower($transaction->transaction_type);
            if (!in_array($type, ['debit', 'credit'], true)) {
                // FIX: Change the hyphen (-) to an arrow (->)
                return back()->with('error', 'Invalid transaction type');
            }

            $debit = $type === 'debit' ? $amount : 0;
            $credit = $type === 'credit' ? $amount : 0;

            $account = Account::where('id', $invoiceReceipt->account_id)
                            ->first();
            try {
                $journalEntry = JournalEntry::create([
                    'transaction_date'         => $transaction->transaction_date,
                    'account_id'               => $invoiceReceipt->account_id,
                    'company_id'               => $companyId,
                    'branch_id'                => $branchId,
                    'transaction_id'           => $transaction->id,
                    'description'              => 'Client Pays Cash via Account: ' . $account->name,
                    'amount'                   => $amount,
                    'debit'                    => $debit,
                    'credit'                   => $credit,
                    'balance'                  => $account->balance ?? 0,
                    'receipt_reference_number' => $transaction->reference_number,
                    'name'                     => $account->name ?? '',
                    'type'                     => $type === 'debit' ? 'receivable' : 'payable', 
                    'currency'                 => 'KWD',
                    'exchange_rate'            => 1,
                ]);

                $invoiceReceipt->update([
                    'status' => 'approved',
                    'is_used' => true,
                ]);

            } catch (Exception $e) {
                    Log::error('Failed to approve the Receipt Voucher of ID: ' . $invoiceReceipt->id, [
                        'response' => $e->getMessage(),
                ]);
                return redirect()->back()->with('error', 'Failed to approve');
            }
                        
        } elseif ($type == 'invoice') {

            $invoiceId = $transaction->invoice_id;
        
            $invoice = Invoice::where('id', $invoiceId)->first();

            $invoiceDetail = InvoiceDetail::where('invoice_number', $invoice->invoice_number)->first();
            if (!$invoiceDetail) {
                Log::error('Invoice detail not found', ['invoice_number' => $invoice->invoice_number]);
                return ['status' => 'error', 'message' => 'Invoice detail not found'];
            }

            $invoicePartial = InvoicePartial::where('invoice_id', $invoiceId)->first();

            $client = Client::find($invoice->client_id);
            if (!$client) {
                Log::error('Client not found', ['client_id' => $invoice->client_id]);
                return ['status' => 'error', 'message' => 'Client not found'];
            }

            $clientName = $client->name ?? trim(implode(' ', array_filter([
                $client->first_name ?? null,
                $client->middle_name ?? null,
                $client->last_name ?? null,
            ])));

            if ($invoice) {
                try {
                    DB::beginTransaction();

                    $remainingBalance = $invoice->amount - ($invoicePartial->amount);
                    if ($remainingBalance > 0) { //Partial/Split Payment
                        Log::info('Remaining balance: KWD ' . $remainingBalance . '. Proceed to create new partial for another payment to complete the transaction');

                        $invoice->update([
                            'status' => 'unpaid',
                            'type' => 'partial',
                            'payment_type' => 'partial',
                            'paid_date' => now(),
                        ]); 
                        Log::info('Succesfully updated the Invoice, status remained Unpaid as there is remaining balance of KWD ' . $remainingBalance);
                        
                        $invoicePartial->update([
                            'status' => 'paid',
                            'type' => 'partial',
                            'receipt_voucher_id' => $invoiceReceipt->id,
                            'updated_at' => now(),
                        ]);
                        Log::info('Successfully updated the existing Invoice Partial, status remained Unpaid as there is remaining balance of KWD ' . $remainingBalance);

                        $invoiceReceipt->update([
                            'status' => 'approved',
                            'is_used' => true,

                        ]);

                        $totalSum = InvoicePartial::where('invoice_id', $invoiceId)
                                        ->sum('amount');

                        if ($totalSum < $invoice->amount) {
                            $newPartial = InvoicePartial::create([
                                'invoice_id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'client_id'=> $invoice->client_id,
                                'service_charge' => 0,
                                'amount' => $remainingBalance,
                                'status' => 'unpaid',
                                'expiry_date' => null,  
                                'type' => 'partial',    
                                'charge_id' => null,
                                'payment_gateway' => null,
                                'payment_method' => null,
                                'payment_id' => null,
                            ]);   
                            Log::info('Successfully created new Invoice Partial', [
                                'response' => $newPartial,
                            ]);
                        }

                    } elseif ($remainingBalance == 0) { //Full Payment
                        Log::info('Has 0 of remaining balance. Proceed to pay the entire invoice');

                        $invoice->update([
                            'status' => 'paid',
                            'type' => 'full',
                            'payment_type' => 'Cash',
                            'paid_date' => now(),
                        ]); 
                        Log::info('Succesfully updated the Invoice');
                        
                        $invoicePartial->update([
                            'status' => 'paid',
                            'type' => 'full',
                            'receipt_voucher_id' => $invoiceReceipt->id,
                            'updated_at' => now(),
                        ]);
                        Log::info('Successfully updated the existing Invoice Partial');

                        $invoiceReceipt->update([
                            'status' => 'approved',
                            'is_used' => true,
                        ]);
                    }

                    //Cash Account (Assets)
                    $assets = Account::where('name', 'like', '%Assets%')
                                ->where('company_id', $companyId)
                                ->value('id');
                    if (!$assets) {
                        Log::error('Assets root account not found');
                        return ['status' => 'error', 'message' => 'Assets root account not found'];
                    }

                    $receiptVoucherCash = Account::where('name', 'Receipt Voucher Cash')
                                            ->where('company_id', $companyId)
                                            ->where('root_id', $assets)
                                            ->first();
                    if (!$receiptVoucherCash) {
                        Log::error('Receipt Voucher Cash account not found', ['company_id' => $companyId]);
                        return ['status' => 'error', 'message' => 'Receipt Voucher Cash account not found'];
                    }

                    JournalEntry::create([
                        'task_id' => $invoiceDetail->task_id,
                        'transaction_id' => $transaction->id,
                        'company_id' => $companyId,
                        'branch_id' => $invoice->agent->branch->id,
                        'account_id' => $receiptVoucherCash->id,
                        'invoice_id' => $invoice->id,
                        'invoice_details_id' => $invoiceDetail->id, 
                        'transaction_date' => $transaction->transaction_date,
                        'description' => 'Client Pays Cash via (Assets): ' . $receiptVoucherCash->name,
                        'debit' => $invoicePartial->amount,
                        'credit' => 0, 
                        'balance' => $remainingBalance,
                        'name' => $clientName,
                        'type' => 'receivable',
                        'voucher_number' => null,
                        'receipt_reference_number' => $transaction->reference_number,
                        'type_reference_id' => $receiptVoucherCash->id,
                    ]);

                    //Advances Account (Liabilities)
                    $liabilities = Account::where('name', 'like', '%Liabilities%')
                                    ->where('company_id', $companyId)
                                    ->value('id');

                    if (!$liabilities) {
                        Log::error('Liabilities root account not found');
                        return ['status' => 'error', 'message' => 'Assets root account not found'];
                    }

                    $clientAccount = Account::where('name', 'like', 'Client')
                                ->where('company_id', $companyId)
                                ->where('root_id', $liabilities)
                                ->first();
                    if (!$clientAccount) {
                        Log::error('Client account not found');
                        return ['status' => 'error', 'message' => 'Client account not found'];    
                    }

                    $cash = Account::where('name', 'like', 'Cash')
                                ->where('company_id', $companyId)
                                ->where('parent_id', $clientAccount->id)
                                ->first();
                    if (!$cash) {
                        Log::error('Cash account not found');
                        return ['status' => 'error', 'message' => 'Cashs account not found'];
                    }

                    JournalEntry::create([
                        'task_id' => $invoiceDetail->task_id,
                        'transaction_id' => $transaction->id,
                        'company_id' => $companyId,
                        'branch_id' => $invoice->agent->branch->id,
                        'account_id' => $cash->id,
                        'invoice_id' => $invoice->id,
                        'invoice_details_id' => $invoiceDetail->id, 
                        'transaction_date' => $transaction->transaction_date,
                        'description' => 'Client Pays Invoice via (Advances): ' . $cash->name,
                        'debit' => 0,
                        'credit' => $invoicePartial->amount, 
                        'balance' => $remainingBalance,
                        'name' => $clientName,
                        'type' => 'payable',
                        'voucher_number' => null,
                        'receipt_reference_number' => $transaction->reference_number,
                        'type_reference_id' => $cash->id,
                    ]);

                    Log::info('Journal entry created successfully', [
                        'journal_entries' => [
                            'receipt_voucher_cash' => $receiptVoucherCash->name,
                            'cash' => $cash->name,
                        ],
                    ]);

                    DB::commit();

                    return redirect()->back()->with('success', 'Receipt Voucher has been successfully approved');

                } catch (Exception $e) {
                    Log::error('Failed to approve the Receipt Voucher of ID: ' . $invoiceReceipt->id, [
                        'response' => $e->getMessage(),
                    ]);
                    return redirect()->back()->with('error', 'Failed to approve');
                }
            }
        } elseif ($type == 'import') {
            try {
                //Assets
                $assets = Account::where('name', 'like', '%Assets%')
                ->where('company_id', $companyId)
                ->value('id');

                if (!$assets) {
                    Log::error('Assets root account not found');
                    return [
                        'status' => 'error',
                        'message' => 'Assets root account not found',
                    ];
                }

                $liabilities = Account::where('name', 'like', '%Liabilities%')
                    ->where('company_id', $companyId)
                    ->value('id');

                if (!$liabilities) {
                    Log::error('Liabilities root account not found');
                    return [
                        'status' => 'error',
                        'message' => 'Liabilities root account not found',
                    ];
                }

                $receiptVoucherCash = Account::where('name', 'Receipt Voucher Cash')
                    ->where('company_id', $companyId)
                    ->where('root_id', $assets)
                    ->first();

                if (!$receiptVoucherCash) {
                    Log::error('Cash in Hand (Receipt Voucher Cash) account not found');
                    return [
                        'status' => 'error',
                        'message' => 'Failed to add journal entry to Cash in Hand (Receipt Voucher Cash) account',
                    ];
                }

                $journalEntry1 = JournalEntry::create([
                    'transaction_date'         => $transaction->transaction_date,
                    'account_id'               => $receiptVoucherCash->id,
                    'company_id'               => $companyId,
                    'branch_id'                => $branchId,
                    'transaction_id'           => $transaction->id,
                    'description'              => 'Client Pays Cash via Account: ' . $receiptVoucherCash->name,
                    'amount'                   => $amount,
                    'debit'                    => $amount,
                    'credit'                   => 0,
                    'balance'                  => $receiptVoucherCash->balance ?? 0,
                    'receipt_reference_number' => $transaction->reference_number,
                    'name'                     => $receiptVoucherCash->name ?? '',
                    'type'                     => 'receivable', 
                    'currency'                 => 'KWD',
                    'exchange_rate'            => 1,
                ]); 

                $receiptVoucherCash->actual_balance = ($receiptVoucherCash->actual_balance ?? 0) + $amount;
                $receiptVoucherCash->save();

                $advancesParent = Account::where('name', 'Advances')
                    ->where('company_id', $companyId)
                    ->where('root_id', $liabilities)
                    ->first();

                $clientAdvance = Account::where('name', 'Client')
                    ->where('company_id', $companyId)
                    ->where('parent_id', $advancesParent->id)
                    ->first();

                $cash= Account::where('name', 'Cash')
                    ->where('company_id', $companyId)
                    ->where('parent_id', $clientAdvance->id)
                    ->first();
                    
                if (!$cash) {
                    Log::error('Advances (Client -> Cash) account not found');
                    return [
                        'status' => 'error',
                        'message' => 'Failed to add journal entry to Advances (Client -> Cash) account',
                    ];
                }

                $journalEntry2 = JournalEntry::create([
                    'transaction_id'   => $transaction->id,
                    'company_id'       => $companyId,
                    'branch_id'        => $branchId,
                    'account_id'       => $cash->id,
                    'transaction_date' => Carbon::now(),
                    'description'      => 'Client Pays Cash via (Advances): ' . $cash->name,
                    'debit'            => 0,
                    'credit'           => $amount,
                    'balance'          => ($cash->actual_balance ?? 0) + $amount,
                    'name'             => $cash->name,
                    'type'             => 'cash',
                    'type_reference_id'=> $cash->id,
                    'receipt_reference_number' => $transaction->reference_number,
                ]);

                $cash->actual_balance = ($cash->actual_balance ?? 0) + $amount;
                $cash->save();

                $invoiceReceipt->update([
                    'status' => 'approved',
                ]);
            } catch (Exception $e) {
                Log::error('Failed to approce the Receipt Voucher of ID: ' . $invoiceReceipt->id, [
                    'response' => $e->getMessage(),
                ]);

                return redirect()->back()->with('error', 'Failed to approve');
            }
        }
        
        Log::info('Receipt Voucher for ID: ' . $invoiceReceipt->id . ' has been successfully approved');

        return redirect()->route('receipt-voucher.index')->with('success', 'Receipt Voucher has been marked as paid');
    }

    public function invoiceJournalEntry($transaction, $invoice)
    {           
        if (JournalEntry::where('invoice_id', $invoice->id)->exists()) {
            Log::info('Journal entries already exist for this invoice. Skipping creation.', [
                'invoice_id' => $invoice->id,
            ]);
            return ['status' => 'skipped'];
        }

        try {
            Log::info('Starting creating journal entries for uninvoiced task', [
                'transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
            ]);

            DB::beginTransaction();

            $companyId = $invoice->agent->branch->company->id;
            if (!$companyId) {
                Log::error('Company ID not found');
                return ['status' => 'error', 'message' => 'Company ID not found'];
            }
            
            $invoiceDetail = InvoiceDetail::where('invoice_number', $invoice->invoice_number)->first();
            if (!$invoiceDetail) {
                Log::error('Invoice detail not found', ['invoice_number' => $invoice->invoice_number]);
                return ['status' => 'error', 'message' => 'Invoice detail not found'];
            }

            $invoicePartial = InvoicePartial::where('invoice_number', $invoice->invoice_number)->first();
            if (!$invoicePartial) {
                Log::error('Invoice partial not found', ['invoice_number' => $invoice->invoice_number]);
                return ['status' => 'error', 'message' => 'Invoice partial not found'];
            }

            $task = Task::where('id', $invoiceDetail->task_id)
                        ->first();

            $client = Client::find($invoice->client_id);
            if (!$client) {
                Log::error('Client not found', ['client_id' => $invoice->client_id]);
                return ['status' => 'error', 'message' => 'Client not found'];
            }

            $agent = Agent::find($invoice->agent_id);
            if (!$agent) {
                Log::error('Agent not found', ['agent_id' => $invoice->agent_id]);
                return ['status' => 'error', 'message' => 'Client not found'];
            }

            //Receivable Account
            $accountReceivable = Account::where('name', 'Accounts Receivable')
                                    ->where('company_id', $companyId)
                                    ->first();
            
            $clientAccount = Account::where('name', 'Clients')
                                ->where('company_id', $companyId)
                                ->where('parent_id', optional($accountReceivable)->id)
                                ->first();

            if ($clientAccount) {
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'company_id' => $companyId,
                    'branch_id' => $invoice->agent->branch->id,
                    'account_id' => $clientAccount->id,
                    'task_id' => $task->id,
                    'agent_id' => $invoice->agent_id, 
                    'invoice_id' => $invoice->id,
                    'type_reference_id' => $clientAccount->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Invoice created for (Assets): ' . $client->name,
                    'debit' => $invoice->amount,
                    'credit' => 0,
                    'balance' => $clientAccount->balance ?? 0,
                    'name' => $clientAccount->name,
                    'type' => 'receivable',
                    'currency' => $task->currency ?? 'KWD',
                    'exchange_rate' => $task->exchange_rate ?? 1.0,
                    'amount' => $invoice->amount,
                    'receipt_reference_number' => $transaction->reference_number,

                ]);
            }

            //Booking Account (Income)
            $bookingAccount = Account::where('name', 'like', $task['type'] == 'flight' ? '%Flight Booking%' : '%Hotel Booking%')
                                ->where('company_id', $companyId)
                                ->first();
            if ($bookingAccount) {
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'company_id' => $companyId,
                    'branch_id' => $invoice->agent->branch->company->id,
                    'account_id' => $bookingAccount->id,
                    'task_id' => $task->id,
                    'agent_id' => $invoice->agent->id,
                    'invoice_id' => $invoice->id,
                    'type_reference_id' => $bookingAccount->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Invoice created for (Income): ' . $task->reference,
                    'debit' => 0,
                    'credit' => $invoicePartial->amount,
                    'balance' => $bookingAccount->balance ?? 0,
                    'name' => $bookingAccount->name,
                    'type' => 'payable',
                    'currency' => $task->currency ?? 'KWD',
                    'exchange_rate' => $task->exchange_rate ?? 1.0,
                    'amount' => $invoice->amount,
                    'receipt_reference_number' => $transaction->reference_number,
                ]);
            }

            //Commision (Expense)
            if (in_array($agent->type_id, [2, 3])) {
                $selling = (float) ($task->invoiceDetail->task_price ?? 0);
                $supplier = (float) ($task->total ?? 0);
                $rate = (float) ($agent->commission ?? 0.15);
                $commission = $rate * ($selling - $supplier);

                $commissionExpenses = Account::where('name', 'like', 'Commissions Expense (Agents)%')
                    ->where('company_id', $task->company_id)
                    ->first();
            } else {
                $commissionExpenses = null;
            }

            if ($commissionExpenses) {
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'company_id' => $companyId,
                    'branch_id' => $invoice->agent->branch->id,
                    'account_id' => $commissionExpenses->id,
                    'task_id' => $task->id,
                    'agent_id' => $agent->id,
                    'invoice_id' => $invoice->id,
                    'type_reference_id' => $commissionExpenses->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Agents Commissions for (Expenses): ' . $task['agent']['name'],
                    'debit' => $commission,
                    'credit' => 0,
                    'balance' => $commissionExpenses->balance ?? 0,
                    'name' => $commissionExpenses->name,
                    'type' => 'receivable',
                    'currency' => $task->currency ?? 'KWD',
                    'exchange_rate' => $task->exchange_rate ?? 1.0,
                    'amount' => $commission,
                    'receipt_reference_number' => $transaction->reference_number,
                ]);
            }

            //Commision (Liability)
            $accruedCommissions = null;

            if (in_array($agent->type_id, [2, 3])) {
                $selling = (float) ($task->invoiceDetail->task_price ?? 0);
                $supplier = (float) ($task->total ?? 0);
                $rate = (float) ($agent->commission ?? 0.15);
                $commission = $rate * ($selling - $supplier);

                $accruedCommissions = Account::where('name', 'like', 'Commissions (Agents)%')
                    ->where('company_id', $task->company_id)
                    ->first();
            }

            if ($accruedCommissions) {
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'company_id' => $companyId,
                    'branch_id' => $invoice->agent->branch_id,
                    'account_id' => $accruedCommissions->id,
                    'task_id' => $task->id,    
                    'agent_id' => $agent->id,
                    'invoice_id' => $invoice->id,
                    'type_reference_id' => $accruedCommissions->id,
                    'invoice_detail_id' => $invoiceDetail->id,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Agents Commissions for (Liabilities): ' . $task['agent']['name'],
                    'debit' => 0,
                    'credit' => $commission,
                    'balance' => $accruedCommissions->balance ?? 0,
                    'name' => $accruedCommissions->name,
                    'type' => 'payable',
                    'currency' => $task->currency ?? 'KWD',
                    'exchange_rate' => $task->exchange_rate ?? 1.0,
                    'amount' => $commission,
                    'receipt_reference_number' => $transaction->reference_number,
                ]);
            }

            Log::info('Journal entry created successfully', [
                'journal_entries' => [
                    'client'     => $clientAccount?->name,
                    'booking'    => $bookingAccount?->name,
                    'commission' => $commissionExpenses?->name,
                    'accrued'    => $accruedCommissions?->name,
                ],
            ]);

            DB::commit();

            return ['status' => 'success'];

        } catch (\Exception $e) {
            Log::error('Error in invoiceJournalEntry', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            DB::rollback();

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function autoGenerate(Invoice $invoice,  Request $request): JsonResponse
    {
        Log::info('Starting to auto generate an unpaid Receipt Voucher', [
            'invoice_data' => $invoice,
            'request_data' => $request->all(),
        ]);

        $invoiceId = $invoice->id;

        $invoice = Invoice::find($invoiceId);
        if (!$invoice) {
            Log::error('Invoice not found', ['invoice_id' => $invoiceId]);
            return response()->json(['ok' => false, 'message' => 'Invoice not found'], 404);
        }

        $type = $request->input('type', '');
        $isPartial = strcasecmp($type, 'partial') === 0;

        if ($isPartial) {
            $invoicePartial = InvoicePartial::firstOrCreate(
                ['invoice_id' => $invoiceId],
                [
                    'invoice_number'     => $invoice->invoice_number,
                    'client_id'          => $invoice->client_id,
                    'service_charge'     => 0,
                    'amount'             => $request->amount,
                    'status'             => $invoice->status,
                    'expiry_date'        => $invoice->due_date,
                    'type'               => 'partial',
                    'charge_id'          => null,
                    'payment_gateway'    => 'Cash',
                    'payment_method'     => null,
                    'payment_id'         => null,
                    'receipt_voucher_id' => null,
                ]
            );
           
        } else {
             $invoicePartial = InvoicePartial::firstOrCreate(
                ['invoice_id' => $invoiceId],
                [
                    'invoice_number'     => $invoice->invoice_number,
                    'client_id'          => $invoice->client_id,
                    'service_charge'     => 0,
                    'amount'             => $request->amount,
                    'status'             => $invoice->status,
                    'expiry_date'        => $invoice->due_date,
                    'type'               => 'full',
                    'charge_id'          => null,
                    'payment_gateway'    => 'Cash',
                    'payment_method'     => null,
                    'payment_id'         => null,
                    'receipt_voucher_id' => null,
                ]
            );
        }
        Log::info('data of invoice partial', [
            'invoice_partial' => $invoicePartial->withoutRelations()->toArray(),
        ]);        
        
        $client = $invoice->client()->first();
        if (!$client) {
            Log::error('Missing client relation', ['invoice_id' => $invoiceId]);
            return response()->json(['ok' => false, 'message' => 'Client missing for invoice'], 422);
        }

        $amount = $request->amount;
        $ref    = 'RV-'.Str::upper(Str::random(10)); 

        try {
            DB::beginTransaction();

            $uninvoicedTransaction = Transaction::where('invoice_id', $invoiceId)->where('transaction_type', 'cash')->first();
            if (!$uninvoicedTransaction) {
                $uninvoicedTransaction = Transaction::create([
                    'entity_id'        => $invoice->agent->branch->company->id,
                    'entity_type'      => 'company',
                    'company_id'       => $invoice->agent->branch->company->id,
                    'branch_id'        => $invoice->agent->branch->id,
                    'transaction_type' => 'cash',
                    'amount'           => $request->amount,
                    'description'      => 'Invoice: '.$invoice->invoice_number.' Generated',
                    'invoice_id'       => $invoice->id,
                    'reference_type'   => 'Invoice',
                    'name'             => $client->name,
                    'transaction_date' => $invoice->invoice_date,
                ]);
            }

            $uninvoiced = $this->invoiceJournalEntry($uninvoicedTransaction, $invoice);
            if (!is_array($uninvoiced) || ($uninvoiced['status'] ?? 'error') === 'error') {
                Log::error('Journal entry failed', ['invoice_id' => $invoiceId, 'response' => $uninvoiced]);
                DB::rollBack();
                return response()->json(['ok' => false, 'message' => $uninvoiced['message'] ?? 'Journal entry failed'], 422);
            }

            $invoice->update([
                'payment_type' => 'cash',
            ]);

            $invoicePartial->update([
                'amount'          => $amount,
                'expiry_date'     => null,
                'charge_id'       => null,
                'type'            => $isPartial ? 'partial' : 'full',
                'payment_gateway' => 'Cash',
                'payment_method'  => null,
                'updated_at'      => now(),
            ]);

            $transaction = Transaction::create([
                'entity_id'        => $invoice->agent->branch->company->id,
                'entity_type'      => 'company',
                'company_id'       => $invoice->agent->branch->company->id,
                'branch_id'        => $invoice->agent->branch->id,
                'transaction_type' => 'debit',
                'amount'           => $amount,
                'description'      => 'Payment for Invoice '.$invoice->invoice_number,
                'invoice_id'       => $invoiceId,
                'reference_number' => $ref,
                'reference_type'   => 'Invoice',
                'name'             => $client->name,
                'transaction_date' => now(),
            ]);

            $invoiceReceipt = InvoiceReceipt::create([
                'invoice_id'     => $invoiceId,
                'transaction_id' => $transaction->id,
                'amount'         => $amount,
            ]);

            if (!$invoiceReceipt) {
                Log::error('Failed to create Invoice Receipt');
            }

            DB::commit();

            return response()->json([
                'ok'                => true,
                'invoice_id'        => $invoiceId,
                'invoice_partial_id'=> $invoicePartial->id,
                'payment_txn_id'    => $transaction->id,
                'reference'         => $ref,
            ], 201);

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Failed to process Receipt Voucher', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);
            return response()->json(['ok' => false, 'message' => 'Failed to generate a Receipt Voucher'], 500);
        }
    }

    public function import(Request $request) 
    {
        Log::info('Starting to process the import payment of Receipt Voucher', [
            'data' => $request->all(),
        ]);

        $user = Auth::user();

        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company?->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company->id;
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = $user->branch->company->id;
        }

        $request->validate([
            'receipt_reference' => 'required|string',
            'agent_name' => 'required|string',
            'client_name' => 'required|string',
            'invoice_number' => 'required|string',
        ]);
        
        $transaction = Transaction::with('invoiceReceipt')
                            ->where('reference_number', $request->receipt_reference)
                            ->first();

        $client = Client::where('name', $transaction->client_id)->first();

        $invoice = Invoice::where('invoice_number', $request->invoice_number)->first();
        if (!$invoice) {
            Log::error('Invoice is not found for invoice number: ' . $request->invoice_number);
        }

        $invoiceReceipt = InvoiceReceipt::where('transaction_id', $transaction->id)->first();

        $companyId = $invoice->agent->branch->company->id;
        $branchId = $invoice->agent->branch->id;

        $remainingBalance = (float)($invoice->amount) - (float)($transaction->invoiceReceipt->amount); 

        try {
            
            DB::beginTransaction();

            if ($remainingBalance == 0) {
                    Log::info('Has 0 of remaining balance. Proceed to pay the entire invoice');

                    $invoice->update([
                        'status' => 'paid',
                        'paid_date' => now(),
                    ]);

                    $invoicePartial = InvoicePartial::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'client_id' => $client->id,
                        'service_charge' => 0,
                        'amount' => $invoice->amount,
                        'status' => 'paid',
                        'expiry_date' => now(),
                        'type' => 'cash',
                        'charge_id' => null,
                        'payment_gateway' => 'Cash',
                        'payment_method' => null, 
                        'payment_id' => null,
                        'receipt_voucher_id' => $transaction->invoiceReceipt?->id,
                    ]);
                    if(!$invoicePartial) {
                        Log::error('Failed to create Invoice Partial for invoice ID: ', [
                            'invoice_id' => $invoice->id
                        ]);
                    }

                    $transaction = Transaction::create([
                        'entity_id' => $companyId,
                        'entity_type' => 'company',
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'transaction_type' => 'debit',
                        'amount' => $invoice->amount,
                        'description' => 'Payment for Invoice ' . $invoice->invoice_number . '. Additional Remarks: ' . $transaction->description,
                        'invoice_id' => $invoice->id,
                        'reference_number' => $transaction->reference_number,
                        'reference_type' => 'Invoice', //$receiptvoucherType
                        'name' => $client->name,
                        'transaction_date' => now(),
                    ]);

                    if (!$transaction) {
                        Log::error('error', 'Failed to create Transaction with ID: ', [
                            'transaction_id' => $transaction->id
                        ]);
                    }

                    $uninvoiced = $this->invoiceJournalEntry($transaction, $invoice);
                    if (!is_array($uninvoiced) || !isset($uninvoiced['status']) || $uninvoiced['status'] === 'error') {
                        Log::error('Failed to create journal entry during full payment', [
                            'invoice_id' => $invoice->id ?? null,
                            'transaction_id' => $transaction->id ?? null,
                            'response' => $uninvoiced,
                        ]);

                        return redirect()->back()->with('error', $journal['message'] ?? 'Failed to create journal entry');
                    }

                    Log::info('Successfully paid Invoice with ID: ' . $invoice->id . ' using Receipt Voucher via full payment');

            } elseif ($remainingBalance > 0)  {
                Log::info('Remaining balance: KWD ' . $remainingBalance . '. Proceed to create new partial for another payment to complete the transaction');

                $invoice->update([
                    'status' => 'unpaid',
                    'type' => 'partial',
                    'payment_type' => 'partial',
                    'paid_date' => now(),
                ]); 
                Log::info('Succesfully updated the Invoice, status remained Unpaid as there is remaining balance of KWD ' . $remainingBalance);

                $invoicePartial = InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $client->id,
                    'service_charge' => 0,
                    'amount' => $invoice->amount,
                    'status' => 'paid',
                    'expiry_date' => now(),
                    'type' => 'cash',
                    'charge_id' => null,
                    'payment_gateway' => 'Cash',
                    'payment_method' => null, 
                    'payment_id' => null,
                    'receipt_voucher_id' => $transaction->invoiceReceipt?->id,
                ]);
                if(!$invoicePartial) {
                    Log::error('Failed to create Invoice Partial for invoice ID: ', [
                        'invoice_id' => $invoice->id
                    ]);
                }

                $totalSum = InvoicePartial::where('invoice_id', $invoice->id)
                                ->sum('amount');

                if ($totalSum < $invoice->amount) {
                    $newPartial = InvoicePartial::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'client_id'=> $invoice->client_id,
                        'service_charge' => 0,
                        'amount' => $remainingBalance,
                        'status' => 'unpaid',
                        'expiry_date' => null,  
                        'type' => 'partial',    
                        'charge_id' => null,
                        'payment_gateway' => null,
                        'payment_method' => null,
                        'payment_id' => null,
                    ]);   
                    Log::info('Successfully created new Invoice Partial', [
                        'response' => $newPartial,
                    ]);
                }

                Log::info('Successfully paid Invoice with ID: ' . $invoice->id . ' using Receipt Voucher. Invoice remained unpaid as there is remaining balance of KWD ' . $remainingBalance);
            }

            $invoiceReceipt->update([
                'is_used' => true,
            ]);

            DB::commit();
        } catch (Exception $e) {
            Log::error('Failed to process import via Receipt Voucher', [
                'transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
                'receipt_reference_number' => $transaction->reference_number,
            ]);

            DB::rollback();

            return redirect()->back()->with('error', 'Failed to process import via Receipt Voucher');
        }
    }

    public function receiptVoucherCredit($data)
    {
        $user = Auth::user();

        $client = Client::findOrFail($data['items'][0]['client_id']);
        if (!$client) {
            return [
                'status' => 'error',
                'message' => 'Client not found',
            ];
        }

        $companyId = $data['company_id'];
        $branchId = $data['branch_id'];
        $amount = $data['items'][0]['amount'];

        try {
            DB::beginTransaction();

            $topupCreditClientData = Credit::create ([
                'company_id'  => $companyId,
                'branch_id'   => $branchId,
                'client_id'   => $client->id,
                'type'        => 'Topup',
                'description' => 'Topup Credit via ' . $data['receiptvoucherref'] . '. Additional Remarks: ' . $data['remarks_create'],
                'amount'      => $amount,
            ]);
            
            Log::info('Credit record created successfully for client ID: ' . $client->id);
       
            $transaction = Transaction::create([
                'branch_id'        => $branchId,
                'company_id'       => $companyId,
                'name'             => $client->full_name,
                'entity_id'        => $companyId,
                'entity_type'      => 'client',
                'transaction_type' => 'debit',
                'amount'           => $amount,
                'description'      => 'Credit for Client ' . $client->full_name . '. Additional Remarks: ' . $data['remarks_create'],
                'reference_type'   => 'Credit',
                'reference_number' => $data['receiptvoucherref'],
                'transaction_date' => now(),
            ]);

            if (!$transaction) {
                Log::error('Transaction failed to create');
                return [
                    'status' => 'error',
                    'message' => 'Failed to create transaction',
                ];
            }

            $assets = Account::where('name', 'like', '%Assets%')
                ->where('company_id', $companyId)
                ->value('id');

            if (!$assets) {
                Log::error('Assets root account not found');
                return [
                    'status' => 'error',
                    'message' => 'Assets root account not found',
                ];
            }

            $liabilities = Account::where('name', 'like', '%Liabilities%')
                ->where('company_id', $companyId)
                ->value('id');

            if (!$liabilities) {
                Log::error('Liabilities root account not found');
                return [
                    'status' => 'error',
                    'message' => 'Liabilities root account not found',
                ];
            }

            $receiptVoucherCash = Account::where('name', 'Receipt Voucher Cash')
                ->where('company_id', $companyId)
                ->where('root_id', $assets)
                ->first();

            if (!$receiptVoucherCash) {
                Log::error('Cash in Hand (Receipt Voucher Cash) account not found');
                return [
                    'status' => 'error',
                    'message' => 'Failed to add journal entry to Cash in Hand (Receipt Voucher Cash) account',
                ];
            }

            JournalEntry::create([
                'transaction_id'   => $transaction->id,
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'account_id'       => $receiptVoucherCash->id,
                'transaction_date' => Carbon::now(),
                'description'      => 'Client ' . $client->full_name . ' Pays Cash via (Assets): ' . $receiptVoucherCash->name,
                'debit'            => $amount,
                'credit'           => 0,
                'name'             => $receiptVoucherCash->name,
                'type'             => 'receivable',
                'voucher_number'   => $data['receiptvoucherref'],
                'type_reference_id'=> $receiptVoucherCash->id,
            ]);

            $receiptVoucherCash->actual_balance = ($receiptVoucherCash->actual_balance ?? 0) + $amount;
            $receiptVoucherCash->save();

            $advancesParent = Account::where('name', 'Advances')
                ->where('company_id', $companyId)
                ->where('root_id', $liabilities)
                ->first();

            $clientAdvance = Account::where('name', 'Client')
                ->where('company_id', $companyId)
                ->where('parent_id', $advancesParent->id)
                ->first();

            $cash= Account::where('name', 'Cash')
                ->where('company_id', $companyId)
                ->where('parent_id', $clientAdvance->id)
                ->first();
                
            if (!$cash) {
                Log::error('Advances (Client -> Cash) account not found');
                return [
                    'status' => 'error',
                    'message' => 'Failed to add journal entry to Advances (Client -> Cash) account',
                ];
            }

            JournalEntry::create([
                'transaction_id'   => $transaction->id,
                'company_id'       => $companyId,
                'branch_id'        => $branchId,
                'account_id'       => $cash->id,
                'voucher_number'   => $data['receiptvoucherref'],
                'transaction_date' => Carbon::now(),
                'description'      => 'Client Pays Credit via (Advances): ' . $cash->name,
                'debit'            => 0, // liability increase → credit
                'credit'           => $amount,
                'balance'          => ($cash->actual_balance ?? 0) + $amount,
                'name'             => $cash->name,
                'type'             => 'credit',
                'type_reference_id'=> $cash->id,
            ]);

            $cash->actual_balance = ($cash->actual_balance ?? 0) + $amount;
            $cash->save();

            $invoiceReceipt = InvoiceReceipt::create([
                'type' => 'credit',
                'credit_id' => $topupCreditClientData->id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'status' => 'approved',
                'is_used' => true,
            ]);

            if (!$invoiceReceipt) {
                    Log::error('Failed to create Invoice Receipt record', [
                    'transaction_id' => $transaction->id
                ]);
            }
            
            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            logger('Error adding JournalEntry: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to add JournalEntry',
            ];
        }

        
        return [
            'status' => 'success',
            'message' => 'Credit added successfully',
            'data' => [
                'client_id' => $client->id,
                'credit' => $amount,
            ],
        ];
    }
}

