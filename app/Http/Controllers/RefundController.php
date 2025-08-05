<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Refund;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Task;
use App\Models\JournalEntry;
use App\Models\Credit;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\AgentType;
use App\Models\RefundClient;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Gate;

class RefundController extends Controller
{

    public function index()
    {
        $user = Auth::user();
        if (Auth::user()->role->id == Role::COMPANY) {
            $agents = $user->company->branches->pluck('agents')->flatten();
            $refundClients = $agents->pluck('refundClients')->flatten();
            $refunds = Refund::with('task.client', 'task.agent')
                ->where('company_id', Auth::user()->company->id)
                ->orderBy('id', 'desc')
                ->get();
        } elseif (Auth::user()->role->id == Role::BRANCH) {
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
    
    public function create(int $taskId)
    {   
        $task = Task::find($taskId);

        if(!$task->originalTask) return redirect()->back()->withErrors(['error' => 'The selected task has no original task.']);

        if($task->agent_id !== $task->originalTask->agent_id) {
            return redirect()->back()->withErrors(['error' => 'The selected task does not belong to the same agent as the original task.']);
        }

        $agent = $task->agent;

        if(strtolower($agent->agentType->name) == 'commission' ? $agent->commission <= 0 : false) {
            return redirect()->back()->withErrors(['error' => 'The agent does not have a valid commission to process a refund. Please set a valid commission for the agent.']);
        }

        $invoiceDetail = InvoiceDetail::with('invoice')->where('task_id', $task->originalTask->id)->first();
        
        if (!$invoiceDetail) {
            return redirect()->back()->withErrors(['error' => 'Original task has not been invoiced yet.']);
        }
    
        if ($invoiceDetail->invoice->status === 'unpaid') {
            return redirect()->back()->withErrors(['error' => 'The invoice from the original task is still unpaid.']);
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
    
        // Load task with agent and client
        $taskWithRelations = Task::with('agent', 'client')->find($task->id);
        if (!$taskWithRelations) {
            return redirect()->back()->withErrors(['error' => 'The selected task has no tied to agent/ client.']);
        }

        return view('refunds.create', [
            'tasks' => $taskWithRelations,
            'invoiceDetails' => $invoiceDetail,
            // 'hasTicketedTasksWithReference' => $hasTicketedReference,
            'coaAccounts' => $coaAccounts,
        ]);
    }
    

    public function store(Request $request, Task $task)
    {   
        $request->validate([
            'total_nett_refund' => ['required', 'numeric', 'min:-999999.99'],
            'reason' => ['required', 'string'],
            // 'method' => ['required', 'in:Bank,Cash,Online'],
            'date' => ['required', 'date'],
        ]);

        try {
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
                // 'method' => $request->method,
                'date' => $request->date,
                'reference' => $request->reference,
                'status' => 'processed',
                'created_by' => auth()->user()->id,
            ]);

            if ($task->id) {
                // Create Transaction Record
                $transaction = Transaction::create([
                    'entity_id' => $task->company_id,
                    'entity_type' => 'company',
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'transaction_type' => 'debit',
                    'amount' => $request->input('total_nett_refund'),
                    'description' => 'Refund - Record Agent Commission',
                    'reference_type' => 'Refund',
                    'reference_number' => $request->bankpaymentref,
                    'name' => $task->client_name,
                    'remarks_internal' => $request->input('remarks_internal'),
                ]);

                $agent = $task->agent;

                if (strtolower($agent->agentType) == 'commission') {

                    $agentCommission = $agent->commission;

                    $assetsDirectIncome = Account::where('name', 'LIKE', '%Direct Expenses%')
                        ->where('company_id', $task->company_id)
                        ->where('root_id', 5)
                        ->first();

                    $accountIncomeName = 'Commissions Expense (Agents)';

                    // Get or create Supplier Refund Account
                    $incomeRefundAccount = Account::where('name', $accountIncomeName)
                        ->where('company_id', $task->company_id)
                        ->where('root_id', $assetsDirectIncome->root_id)
                        ->first();

                    if (!$incomeRefundAccount) {
                        $incomeRefundAccountEntry = Account::create([
                            'name' => $accountIncomeName,
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
                        $incomeRefundIncomeAccEntry = Account::create([
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
                        'description' => 'Refund Commission - Agent gets ' . $agentCommission * 100 . '% of refund fee (Assets): ' . $incomeRefundAccountEntry->name . '',
                        'debit' => $request->input('new_task_profit') * $agentCommission,
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
                        'description' => 'Refund Commission - Agent gets ' . $agentCommission * 100 . '% of refund fee (Liabilities): ' . $incomeRefundIncomeAccEntry->name . '',
                        'debit' => 0,
                        'credit' => $request->input('new_task_profit') * $agentCommission,
                        'voucher_number' => $refund->id,
                        'name' => $incomeRefundIncomeAccEntry->name,
                        'type' => 'refund',
                    ]);
                }

                $company = $task->company;

                //refund to client Account under liabilities > refund payable > clients

                $liabilities = Account::where('name', 'Liabilities')
                    ->where('company_id', $company->id)
                    ->first();
                
                if(!$liabilities) {
                    return redirect()->back()->withErrors(['error' => 'Liabilities account not found.']);
                }

                $refundPayable = Account::where('name', 'LIKE', '%Refund Payable%')
                    ->where('company_id', $task->company_id)
                    ->where('parent_id', $liabilities->id)
                    ->where('root_id', $liabilities->id)
                    ->first();

                $clientRefundAccountName  = 'Clients';

                $accountClientRefundLiability = Account::where('name', $clientRefundAccountName )
                    ->where('company_id', $task->company_id)
                    ->where('parent_id', $refundPayable->id)
                    ->where('root_id', $refundPayable->root_id)
                    ->first();

                if (!$accountClientRefundLiability) {
                    $accountClientRefundLiability = Account::create([
                        'name' => $clientRefundAccountName,
                        'parent_id' => $refundPayable->id,
                        'company_id' => Auth::user()->company->id,
                        'branch_id' => Auth::user()->branch_id,
                        'root_id' => $liabilities->id,
                        'code' => $refundPayable->code + 10,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $refundPayable->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'currency' => 'KWD',
                    'exchange_rate' => 1.0,
                    'amount' => $request->input('total_nett_refund'),
                    'name' => $task->client_name,
                    'description' => 'Refund to Client - ' . $task->client_name,
                    'type' => 'refund',
                    'debit' => $request->input('total_nett_refund'),
                    'credit' => 0,
                    'balance' => $request->input('total_nett_refund'),
                    'transaction_id' => $transaction->id,
                    'company_id' => $task->company_id,
                    'account_id' => $accountClientRefundLiability->id,
                    'branch_id' => $task->agent->branch_id,
                    'original_currency' => 'KWD',
                    'original_amount' => $request->input('total_nett_refund'),
                ]);

                $user = Auth::user();
                $refundBy = '';

                if ($user->company) {
                    $refundBy = 'Company';
                } elseif ($user->branch) {
                    $refundBy = 'Branch';
                }

                $credit = Credit::create([
                        'company_id' => $task->company_id,
                        'branch_id' => $task->agent->branch_id,
                        'client_id' => $task->client_id,
                        'type' => 'refund',
                        'description' => 'Refund for Task ID: ' . $task->id,
                        'amount' => $request->input('total_nett_refund'),
                        'topup_by' => $refundBy !== '' ? $refundBy : 'Company',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                if(!$credit){
                    throw new Exception('Failed to create credit record for refund.');
                }
            
            }

        } catch (Exception $e) {
            Log::error('Failed during create refund process: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'An unexpected error occurred while processing the refund. Please try again.']);
        }
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
        $user = Auth::user();
        if ($refund->task_id !== $task->id) {
            return response()->json(['error' => 'Invalid Task or Refund.'], 400);
        }
        $taskRec = Task::find($task->id); 
        $refundRec = Refund::find($refund->id);

        try {

            // Create Transaction Record
            $transaction = Transaction::create([
                'entity_id' => $taskRec->company_id,
                'entity_type' => 'company',
                'company_id' => $taskRec->company_id,
                'branch_id' => $taskRec->agent->branch_id,
                'transaction_type' => 'debit',
                'amount' => $refundRec->new_task_profit,
                'description' => 'Adjusted Profit - Refund ('.$refundRec->refund_number.')',
                'reference_type' => 'Refund',
                'reference_number' => $refundRec->refund_number,
                'name' => $taskRec->client_name,
                'remarks_internal' => $refundRec->remarks_internal,
                
            ]);

            $incomeIndirectIncome = Account::where('name', 'LIKE', '%Expenses%')->first();
            $accountSupplierRefundIncome = 'Refund Clearing / Payable Allocation';
    
            $supplierRefundIncome = Account::where('name', $accountSupplierRefundIncome)
                ->where('company_id', $taskRec->company_id)
                ->where('root_id', 5)
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
                $supplierRefundDirectIncomeEntry =  $supplierRefundIncomeId;
            } else {
                $supplierRefundDirectIncomeEntry =  $supplierRefundIncome;
            }


            $incomeIndirectLiability = Account::where('name', 'LIKE', '%Refund Payable%')
                ->where('company_id', $taskRec->company_id)
                ->where('root_id', 2)            
                ->first();

            $accountSupplierRefundLiability = 'Clients';
    
            $supplierRefundLiability = Account::where('name', $accountSupplierRefundLiability)
                ->where('company_id', $taskRec->company_id)
                ->where('root_id', $incomeIndirectLiability->root_id)
                ->first();

            if (!$supplierRefundLiability) {
                $supplierRefundLiabilityId = Account::create([
                    'name' => $accountSupplierRefundLiability,
                    'parent_id' => $incomeIndirectLiability->id,
                    'company_id' => Auth::user()->company->id,
                    'branch_id' => Auth::user()->branch_id,
                    'root_id' => $incomeIndirectLiability->root_id,
                    'code' => $incomeIndirectLiability->code + 1,
                    'account_type' => 'asset',
                    'report_type' => 'balance sheet',
                    'level' => $incomeIndirectLiability->level + 1,
                    'is_group' => 0,
                    'disabled' => 0,
                    'actual_balance' => 0.00,
                    'budget_balance' => 0.00,
                    'variance' => 0.00,
                    'currency' => 'KWD',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $supplierRefundDirectLiabilityEntry =  $supplierRefundLiabilityId;
            } else {
                $supplierRefundDirectLiabilityEntry =  $supplierRefundLiability;
            }            
    
            // Step 1: Debit Entry for Refund
            JournalEntry::create([
                'transaction_date' => $refundRec->date,
                'transaction_id' => $transaction->id,
                'company_id' => $taskRec->company_id,
                'branch_id' => $taskRec->agent->branch_id,
                'account_id' => $supplierRefundDirectIncomeEntry->id,
                'description' => $refundRec->refund_number . ' - '.$supplierRefundDirectIncomeEntry->name.'',
                'debit' => $refundRec->new_task_profit,
                'credit' => 0,
                'voucher_number' => $refundRec->id,
                'name' => $supplierRefundDirectIncomeEntry->name,
                'type' => 'refund',
            ]);

            // Step 2: Debit Entry for Refund
            JournalEntry::create([
                'transaction_date' => $refundRec->date,
                'transaction_id' => $transaction->id,
                'company_id' => $taskRec->company_id,
                'branch_id' => $taskRec->agent->branch_id,
                'account_id' => $supplierRefundDirectLiabilityEntry->id,
                'description' => $refundRec->refund_number . ' - '.$supplierRefundDirectLiabilityEntry->name.'',
                'debit' => 0,
                'credit' => $refundRec->new_task_profit,
                'voucher_number' => $refundRec->id,
                'name' => $supplierRefundDirectLiabilityEntry->name,
                'type' => 'refund',
            ]);

            // Create Credit Record
            $creditSubmit = Credit::create([
                'company_id'  => $taskRec->company_id,
                'client_id'   => $taskRec->client_id,
                'task_id'   => $taskRec->id,
                'type'        => 'Refund',
                'description' => $refundRec->refund_number . ': Refund for ' . $supplierRefundDirectLiabilityEntry->name,
                'amount'      => $refundRec->new_task_profit,
            ]);


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
