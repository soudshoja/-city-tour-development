<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Refund;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Task;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;

class RefundController extends Controller
{

    public function index()
    {
        if (Auth::user()->role->name === 'company') {
            $refunds = Refund::with('task.client', 'task.agent')
                ->where('company_id', Auth::user()->company->id)
                ->orderBy('id', 'desc')
                ->get();
        } elseif (Auth::user()->role->name === 'branch') {
            $refunds = Refund::with('task.client', 'task.agent')
                ->where('branch_id', Auth::user()->branch->id)
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $refunds = collect();
        }


    
        $totalRefunds = $refunds->count();
    
        return view('refunds.index', compact('refunds', 'totalRefunds'));
    }
    
    public function create(Task $task)
    {
        // Get the task with its related agent, branch, and client
        $tasks = Task::with('agent', 'client')
            ->where('id', $task->id)
            ->first();
    
        // If no task is found, redirect back with an error
        if (!$tasks) {
            return redirect()->back()->withErrors('Task not found.');
        }
    
        // Get the root IDs for Assets and Liabilities accounts
        $assetsRootId = Account::where('name', 'Assets')->value('id');
        $liabilitiesRootId = Account::where('name', 'Liabilities')->value('id');
        
        // Fetch COA accounts (leaf nodes) under Assets and Liabilities
        $coaAccounts = Account::doesntHave('children')
            ->whereHas('parent', function ($query) use ($assetsRootId, $liabilitiesRootId) {
                $query->whereIn('root_id', [$assetsRootId, $liabilitiesRootId]);
            })
            ->get();
    
        return view('refunds.create', compact('coaAccounts', 'tasks'));
    }
    

    public function store(Request $request, Task $task)
    {   
        $request->validate([
            'total_nett_refund' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string'],
            'method' => ['required', 'in:Bank,Cash,Online'],
            'account_id' => ['required', 'exists:accounts,id'],
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string'],
        ]);

        $refund = Refund::create([
            'refund_number' => 'RF-' . now()->timestamp, 
            'task_id' => $task->id,
            'company_id' => $task->company_id,
            'branch_id' => $task->agent->branch_id,
            'agent_id' => $task->agent_id,
            'remarks' => $request->input('remarks'),
            'remarks_internal' => $request->input('remarks_internal'),
            'airline_nett_fare' => $request->input('airline_nett_fare'),
            'tax_refund' => $request->input('tax_refund'),
            'refund_airline_charge' => $request->input('refund_airline_charge'),
            'original_task_profit' => $request->input('original_task_profit'),
            'new_task_profit' => $request->input('new_task_profit'),
            'total_nett_refund' => $request->input('total_nett_refund'),
            'service_charge' => $request->input('service_charge'),
            'reason' => $request->reason,
            'method' => $request->method,
            'account_id' => $request->account_id,
            'date' => $request->date,
            'reference' => $request->reference,
            'status' => 'processed',
            'created_by' => auth()->user()->id,
        ]);

        // Create Transaction Record
        $transaction = Transaction::create([
            'entity_id' => $task->company_id,
            'entity_type' => 'company',
            'company_id' => $task->company_id,
            'branch_id' => $task->agent->branch_id,
            'transaction_type' => 'debit',
            'amount' => $request->input('total_nett_refund'),
            'date' => $request->date,
            'description' => 'Refund',
            'reference_type' => 'Refund',
            'reference_number' => $request->bankpaymentref,
            'name' => $task->client_name,
            'remarks_internal' => $request->input('remarks_internal'),
            
        ]);
       
        $assetsReceivableAccount = Account::where('name', 'Accounts Receivable')->first(); 
        
        //foreach ($invoiceDetails as $invdetail) {
            if ($task->id) {
                $accountSupplierName = 'Supplier Refunds';

                // Get or create Supplier Refund Account
                $supplierRefundAccount = Account::where('name', 'LIKE', '%' . $accountSupplierName . '%')
                    ->where('company_id', $task->company_id)
                    ->first();
                
                if (!$supplierRefundAccount) {
                    $supplierRefundAccountId = Account::create([
                        'name' => $accountSupplierName,
                        'parent_id' => $assetsReceivableAccount->id,
                        'company_id' => Auth::user()->company->id,
                        'branch_id' => Auth::user()->branch_id,
                        'root_id' => $assetsReceivableAccount->root_id,
                        'code' => $assetsReceivableAccount->code + 1,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $assetsReceivableAccount->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $supplierRefundAccountEntry =  $supplierRefundAccountId;
                } else {
                    $supplierRefundAccountEntry =  $supplierRefundAccount;
                }

                //dd($supplierRefundAccountEntry);
        
                // Get or create Supplier Refund Income Account
                $incomeIndirectIncome = Account::where('name', 'LIKE', '%Indirect Income%')->first();
                $accountSupplierRefundIncome = 'Supplier Refund Income (Refund Adjustment)';
        
                $supplierRefundIncome = Account::where('name', 'LIKE', $accountSupplierRefundIncome)
                    ->where('company_id', $task->company_id)
                    ->first();
        
                if (!$supplierRefundIncome) {
                    $supplierRefundIncomeId = Account::create([
                        'name' => $accountSupplierRefundIncome,
                        'parent_id' => $incomeIndirectIncome->id,
                        'company_id' => Auth::user()->company->id,
                        'branch_id' => Auth::user()->branch_id,
                        'root_id' => $incomeIndirectIncome->root_id,
                        'code' => $incomeIndirectIncome->code + 1,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $incomeIndirectIncome->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $supplierRefundIncomeEntry =  $supplierRefundIncomeId;
                } else {
                    $supplierRefundIncomeEntry =  $supplierRefundIncome;
                }
        
                // Step 1: Debit Entry for Supplier Refund
                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'account_id' => $supplierRefundAccountEntry->id,
                    'description' => $refund->refund_number . ' - Record Refund Due From Supplier (Assets) ('.$supplierRefundAccountEntry->name.')',
                    'debit' => $request->input('airline_nett_fare'),
                    'credit' => 0,
                    'voucher_number' => $refund->id,
                    'name' => $supplierRefundAccountEntry->name,
                    'type' => 'refund',
                ]);
        
                // Step 2: Credit Entry for Supplier Refund
                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'account_id' => $supplierRefundIncomeEntry->id,
                    'description' => $refund->refund_number . ' - Record Refund Due From Supplier (Income) ('.$supplierRefundIncomeEntry->name.')',
                    'debit' => 0,
                    'credit' => $request->input('airline_nett_fare'),
                    'voucher_number' => $refund->id,
                    'name' => $supplierRefundIncomeEntry->name,
                    'type' => 'refund',
                ]);


                $assetsDirectIncome = Account::where('name', 'Direct Income')->first(); 
                $accountincomeName = 'Flight Booking Revenue';
        
                // Get or create Supplier Refund Account
                $incomeRefundAccount = Account::where('name', 'LIKE', $accountincomeName)
                    ->where('company_id', $task->company_id)
                    ->first();
        
                if (!$incomeRefundAccount) {
                    $incomeRefundAccountId = Account::create([
                        'name' => $accountincomeName,
                        'parent_id' => $assetsDirectIncome->id,
                        'company_id' => Auth::user()->company->id,
                        'branch_id' => Auth::user()->branch_id,
                        'root_id' => $assetsDirectIncome->root_id,
                        'code' => $assetsDirectIncome->code + 1,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $assetsDirectIncome->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $incomeRefundAccountEntry =  $incomeRefundAccountId;
                } else {
                    $incomeRefundAccountEntry =  $incomeRefundAccount;
                }
        
                // Get or create Refund Adjustment Account
                $incomeIndirectIncomeRec = Account::where('name', 'LIKE', '%Indirect Income%')->first();
                $accountincomeRefundIncomeRec = 'Refund Adjustment (Revenue Reversal)';
        
                $incomeRefundIncomeRec = Account::where('name', 'LIKE', $accountincomeRefundIncomeRec)
                    ->where('company_id', $task->company_id)
                    ->first();
        
                if (!$incomeRefundIncomeRec) {
                    $incomeRefundIncomeRecId = Account::create([
                        'name' => $accountincomeRefundIncomeRec,
                        'parent_id' => $incomeIndirectIncomeRec->id,
                        'company_id' => Auth::user()->company->id,
                        'branch_id' => Auth::user()->branch_id,
                        'root_id' => $incomeIndirectIncomeRec->root_id,
                        'code' => $incomeIndirectIncomeRec->code + 1 + 1,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $incomeIndirectIncomeRec->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $incomeRefundIncomeAccEntry =  $incomeRefundIncomeRecId;
                } else {
                    $incomeRefundIncomeAccEntry =  $incomeRefundIncomeRec;
                }
        
                // Step 3: Debit Entry for Supplier Refund
                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'account_id' => $incomeRefundAccountEntry->id,
                    'description' => $refund->refund_number . ' - Reverse Original Profit (Income) ('.$incomeRefundAccountEntry->name.')',
                    'debit' => $request->input('original_task_profit'),
                    'credit' => 0,
                    'voucher_number' => $refund->id,
                    'name' => $incomeRefundAccountEntry->name,
                    'type' => 'refund',
                ]);
        
                // Step 4: Credit Entry for Supplier Refund
                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'account_id' => $incomeRefundIncomeAccEntry->id,
                    'description' => $refund->refund_number . ' - Reverse Original Profit (Income) ('.$incomeRefundIncomeAccEntry->name.')',
                    'debit' => 0,
                    'credit' => $request->input('original_task_profit'),
                    'voucher_number' => $refund->id,
                    'name' => $incomeRefundIncomeAccEntry->name,
                    'type' => 'refund',
                ]);



                // Get or create Refund Adjustment Account
                $incomeIndirectRefundCharges = Account::where('name', 'LIKE', '%Indirect Income%')->first();
                $incomeIndirectRefundChargesRec = 'Refund Charges';
        
                $incomeIndirectRefundChargesRecQuery = Account::where('name', 'LIKE', $incomeIndirectRefundChargesRec)
                    ->where('company_id', $task->company_id)
                    ->first();
        
                if (!$incomeIndirectRefundChargesRecQuery) {
                    $incomeIndirectRefundChargesRecQueryId = Account::create([
                        'name' => $incomeIndirectRefundChargesRec,
                        'parent_id' => $incomeIndirectRefundCharges->id,
                        'company_id' => Auth::user()->company->id,
                        'branch_id' => Auth::user()->branch_id,
                        'root_id' => $incomeIndirectRefundCharges->root_id,
                        'code' => $incomeIndirectRefundCharges->code + 1 + 1 + 1,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $incomeIndirectRefundCharges->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $incomeIndirectRefundChargesRecEntry =  $incomeIndirectRefundChargesRecQueryId;
                } else {
                    $incomeIndirectRefundChargesRecEntry =  $incomeIndirectRefundChargesRecQuery;
                }
        
                // Step 5: Debit Entry for Supplier Refund
                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'account_id' => $incomeIndirectRefundChargesRecEntry->id,
                    'description' => $refund->refund_number . ' - Refund Service Charges to Client ('.$task->client_name.')',
                    'debit' => $request->input('total_nett_refund'),
                    'credit' => 0,
                    'voucher_number' => $refund->id,
                    'name' => $incomeIndirectRefundChargesRecEntry->name,
                    'type' => 'refund',
                ]);


            }
        //}
        

        return redirect()->route('refunds.index')->with('success', 'Refund processed successfully.');

        
    }

    public function edit(Task $task, Refund $refund)
    {
        // Fetch accounts
        $coaAccounts = Account::where('report_type', 'Assets')->get();

        // Get task IDs from invoice details
        $taskIds = [$task->id]; 

        // Fetch tasks using those IDs
        $tasks = Task::with('agent.branch', 'client')
            ->whereIn('id', $taskIds)
            ->orderBy('id', 'desc')
            ->get();

        return view('refunds.edit', compact('refund', 'coaAccounts', 'tasks', 'task'));
    }

    public function update(Request $request, Task $task, Refund $refund)
    {
        // Validate the incoming data
        $request->validate([
            'date' => 'required|date',
            'method' => 'required|string',
            'account_id' => 'required|exists:accounts,id',
            'total_nett_refund' => 'required|numeric|min:0',
            'refund_airline_charge' => 'nullable|numeric|min:0',
            'original_task_profit' => 'nullable|numeric|min:0',
            'new_task_profit' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
        ]);

        // Update the refund with validated data
        $refund->update($request->all());

        // Redirect back with success message
        return redirect()->route('refunds.edit', [
            'task' => $task->id,
            'refund' => $refund->id,
        ])
        ->with('success', 'Refund updated successfully.');
    }

    public function complete_process(Request $request, Task $task, Refund $refund)
    {
        if ($refund->task_id !== $task->id) {
            return response()->json(['error' => 'Invalid Task or Refund.'], 400);
        }
    
        try {
            $refund->update(['status' => 'completed']);
            return redirect()->route('refunds.index')->with('success', 'Refund processed successfully.');
        } catch (\Exception $e) {
            return redirect()->route('refunds.index')->with('error', 'Refund processing failed.');
        }
    }
    
    
    
}
