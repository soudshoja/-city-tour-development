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
use App\Models\RefundClient;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Gate;

class RefundController extends Controller
{

    public function index()
    {
        $user = Auth::user();
        if (Auth::user()->role->name === 'company') {
            $agents = $user->company->branches->pluck('agents')->flatten();
            $refundClients = $agents->pluck('refundClients')->flatten();
            $refunds = Refund::with('task.client', 'task.agent')
                ->where('company_id', Auth::user()->company->id)
                ->orderBy('id', 'desc')
                ->get();
        } elseif (Auth::user()->role->name === 'branch') {
            $refundClients = $user->branch->agents->refundClients;
            $refunds = Refund::with('task.client', 'task.agent')
                ->where('branch_id', Auth::user()->branch->id)
                ->orderBy('id', 'desc')
                ->get();
        } else {
            $refundClients = $user->agent->refundClients;
            $refunds = collect();
        }
    
        $totalRefunds = $refunds->count();
        $totalRefundClients = $refundClients->count();
    
        return view('refunds.index', compact('refunds', 'totalRefunds', 'refundClients', 'totalRefundClients'));
    }
    
    public function create(Task $task)
    {   
        // Get reference value based on task type
        $referenceValue = null;
        if ($task->type === 'flight') {
            $referenceValue = $task->ticket_number;
        } elseif ($task->type === 'hotel') {
            $referenceValue = $task->ticket_number;
        }

        //dd($referenceValue);
    
        // Fail early if reference is missing
        if (!$referenceValue) {
            return redirect()->back()->withErrors(['error' => 'No valid reference (ticket number or room reference) found for this task.']);
        }
    
        // Ensure the invoice and its detail exists
        $invoiceDetails = null;
        if ($task->type === 'flight' && $referenceValue) {
            $invoiceDetails = InvoiceDetail::with('invoice', 'task')
                ->whereHas('task', function ($query) use ($referenceValue) {
                    $query->where('ticket_number', $referenceValue);
                })
                ->first();
        } elseif ($task->type === 'hotel' && $referenceValue) {
            $invoiceDetails = InvoiceDetail::with('invoice', 'task')
                ->whereHas('task', function ($query) use ($referenceValue) {
                    $query->where('ticket_number', $referenceValue);
                })
                ->first();
        }
    
        // Check if invoiceDetails exists before continuing
        if (!$invoiceDetails || !$invoiceDetails->invoice) {
            return redirect()->back()->withErrors(['error' => 'Original invoice not found.']);
        }
    
        // Ensure invoice is paid
        if ($invoiceDetails->invoice->status === 'unpaid') {
            return redirect()->back()->withErrors(['error' => 'The invoice from the original task is still unpaid.']);
        }
    
        // Make sure there's at least one other task with the same reference and status "issued"
        // $hasTicketedReference = Task::where('id', '!=', $task->id)
        //     ->where('status', 'issued')
        //     ->when($task->type === 'flight', function ($query) use ($referenceValue) {
        //         $query->whereHas('flightDetails', function ($sub) use ($referenceValue) {
        //             $sub->where('ticket_number', $referenceValue);
        //         });
        //     })
        //     ->when($task->type === 'hotel', function ($query) use ($referenceValue) {
        //         $query->whereHas('hotelDetails', function ($sub) use ($referenceValue) {
        //             $sub->where('room_reference', $referenceValue);
        //         });
        //     })
        //     ->exists();
    
        // if (!$hasTicketedReference) {
        //     return redirect()->back()->withErrors(['error' => 'No matching issued task found for this reference.']);
        // }
    
        // Get the root IDs for Assets and Liabilities accounts
        $assetsRootId = Account::where('name', 'Assets')->value('id');
        $liabilitiesRootId = Account::where('name', 'Liabilities')->value('id');
        // Fetch COA accounts (leaf nodes) under Assets and Liabilities
        $coaAccounts = Account::doesntHave('children')
            ->whereHas('parent', function ($query) use ($assetsRootId, $liabilitiesRootId) {
                $query->whereIn('root_id', [$assetsRootId, $liabilitiesRootId]);
            })
            ->get();
    
        // Load task with agent and client
        $taskWithRelations = Task::with('agent', 'client')->find($task->id);
        if (!$taskWithRelations) {
            return redirect()->back()->withErrors(['error' => 'The selected task has no tied to agent/ client.']);
        }

        return view('refunds.create', [
            'tasks' => $taskWithRelations,
            'invoiceDetails' => $invoiceDetails,
            // 'hasTicketedTasksWithReference' => $hasTicketedReference,
            'coaAccounts' => $coaAccounts,
        ]);
    }    
    

    public function store(Request $request, Task $task)
    {   
        $request->validate([
            'total_nett_refund' => ['required', 'numeric', 'min:-999999.99'],
            'reason' => ['required', 'string'],
            'method' => ['required', 'in:Bank,Cash,Online'],
            'date' => ['required', 'date'],
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
            'date' => $request->date,
            'reference' => $request->reference,
            'status' => 'processed',
            'created_by' => auth()->user()->id,
        ]);

       
        //foreach ($invoiceDetails as $invdetail) {
            if ($task->id) {
           
                // Create Transaction Record
                $transaction = Transaction::create([
                    'entity_id' => $task->company_id,
                    'entity_type' => 'company',
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'transaction_type' => 'debit',
                    'amount' => $request->input('total_nett_refund'),
                    'date' => $request->date,
                    'description' => 'Refund - Record Agent Commission',
                    'reference_type' => 'Refund',
                    'reference_number' => $request->bankpaymentref,
                    'name' => $task->client_name,
                    'remarks_internal' => $request->input('remarks_internal'),
                    
                ]);

                $assetsDirectIncome = Account::where('name', 'LIKE', '%Direct Expenses%')
                    ->where('company_id', $task->company_id)
                    ->where('root_id', 5)                
                    ->first(); 

                $accountincomeName = 'Commissions Expense (Agents)';
        
                // Get or create Supplier Refund Account
                $incomeRefundAccount = Account::where('name', $accountincomeName)
                    ->where('company_id', $task->company_id)
                    ->where('root_id', $assetsDirectIncome->root_id)
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
                $incomeIndirectIncomeRec = Account::where('name', 'LIKE', '%Accrued Expenses%')
                    ->where('company_id', $task->company_id)
                    ->where('root_id', 2)
                    ->first();

                $accountincomeRefundIncomeRec = 'Commission (Agents)';
        
                $incomeRefundIncomeRec = Account::where('name', 'LIKE', $accountincomeRefundIncomeRec)
                    ->where('company_id', $task->company_id)
                    ->where('root_id', $incomeIndirectIncomeRec->root_id)
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
                    'description' => 'Refund Commission - Agent gets 15% of refund fee (Expenses): '.$incomeRefundAccountEntry->name.'',
                    'debit' => $request->input('new_task_profit') * 0.15,
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
                    'description' => 'Refund Commission - Agent gets 15% of refund fee (Liabilities): '.$incomeRefundIncomeAccEntry->name.'',
                    'debit' => 0,
                    'credit' => $request->input('new_task_profit') * 0.15,
                    'voucher_number' => $refund->id,
                    'name' => $incomeRefundIncomeAccEntry->name,
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

    public function complete_process(Task $task, Refund $refund)
    {
        if ($refund->task_id !== $task->id) {
            return response()->json(['error' => 'Invalid Task or Refund.'], 400);
        }
        $taskRec = Task::find($task->id); 
        $refundRec = Refund::find($refund->id);

        try {

            // // Create Transaction Record
            // $transaction = Transaction::create([
            //     'entity_id' => $taskRec->company_id,
            //     'entity_type' => 'company',
            //     'company_id' => $taskRec->company_id,
            //     'branch_id' => $taskRec->agent->branch_id,
            //     'transaction_type' => 'debit',
            //     'amount' => $refundRec->new_task_profit,
            //     'date' => $refundRec->date,
            //     'description' => 'Adjusted Profit - Refund ('.$refundRec->refund_number.')',
            //     'reference_type' => 'Refund',
            //     'reference_number' => $refundRec->refund_number,
            //     'name' => $taskRec->client_name,
            //     'remarks_internal' => $refundRec->remarks_internal,
                
            // ]);

            // $incomeIndirectIncome = Account::where('name', 'LIKE', '%Indirect Income%')->first();
            // $accountSupplierRefundIncome = 'Adjusted Profit';
    
            // $supplierRefundIncome = Account::where('name', 'LIKE', $accountSupplierRefundIncome)
            //     ->where('company_id', $taskRec->company_id)
            //     ->first();
    
            // if (!$supplierRefundIncome) {
            //     $supplierRefundIncomeId = Account::create([
            //         'name' => $accountSupplierRefundIncome,
            //         'parent_id' => $incomeIndirectIncome->id,
            //         'company_id' => Auth::user()->company->id,
            //         'branch_id' => Auth::user()->branch_id,
            //         'root_id' => $incomeIndirectIncome->root_id,
            //         'code' => $incomeIndirectIncome->code + 1,
            //         'account_type' => 'asset',
            //         'report_type' => 'balance sheet',
            //         'level' => $incomeIndirectIncome->level + 1,
            //         'is_group' => 0,
            //         'disabled' => 0,
            //         'actual_balance' => 0.00,
            //         'budget_balance' => 0.00,
            //         'variance' => 0.00,
            //         'currency' => 'KWD',
            //         'created_at' => now(),
            //         'updated_at' => now(),
            //     ]);
            //     $supplierRefundDirectIncomeEntry =  $supplierRefundIncomeId;
            // } else {
            //     $supplierRefundDirectIncomeEntry =  $supplierRefundIncome;
            // }
    
            // // Step 1: Debit Entry for Refund
            // JournalEntry::create([
            //     'transaction_date' => $refundRec->date,
            //     'transaction_id' => $transaction->id,
            //     'company_id' => $taskRec->company_id,
            //     'branch_id' => $taskRec->agent->branch_id,
            //     'account_id' => $supplierRefundDirectIncomeEntry->id,
            //     'description' => $refundRec->refund_number . ' - '.$supplierRefundDirectIncomeEntry->name.'',
            //     'debit' => $refundRec->new_task_profit,
            //     'credit' => 0,
            //     'voucher_number' => $refundRec->id,
            //     'name' => $supplierRefundDirectIncomeEntry->name,
            //     'type' => 'refund',
            // ]);

            $refund->update(['status' => 'completed']);
            return redirect()->route('refunds.index')->with('success', 'Refund processed successfully.');
        } catch (\Exception $e) {
            return redirect()->route('refunds.index')->with('error', 'Refund processing failed.');
        }
    }
    
    public function completeRefundClient($refundClientId)
    {
        $refundClient = RefundClient::find($refundClientId);

        if (!$refundClient) {
            return redirect()->back()->withErrors(['error' => 'Refund Client not found.']);
        }

        $refundClient->update(['status' => 'completed']);

        return redirect()->route('refunds.index')->with('success', 'Refund Client processed successfully.');
    }

    public function deleteRefundClient($refundClientId)
    {
        $refundClient = RefundClient::find($refundClientId);

        Gate::authorize('delete', $refundClient);

        if (!$refundClient) {
            return redirect()->back()->withErrors(['error' => 'Refund Client not found.']);
        }

        try {
            $refundClient->client->credit += $refundClient->amount;
            $refundClient->client->save();
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reverse client credit.']);
        }


        $refundClient->delete();

        return redirect()->route('refunds.index')->with('success', 'Refund Client deleted successfully.');
    }
    
}
