<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\Refund;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Task;
use App\Models\JournalEntry;
use App\Models\Credit;
use App\Models\Role;
use App\Models\Charge;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\AgentType;
use App\Models\InvoiceSequence;
use App\Models\RefundClient;
use App\Services\ChargeService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class RefundController extends Controller
{

    public function index()
    {
        Gate::authorize('viewAny', Refund::class);

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
        } elseif (Auth::user()->role->id == Role::AGENT) {
            $refundClients = $user->agent->refundClients;
            $refunds = Refund::with('task.client', 'task.agent')
                ->where('agent_id', $user->agent->id)
                ->orderBy('id', 'desc')
                ->get();
        } elseif (Auth::user()->role->id == Role::ACCOUNTANT) {
            $refundClients = $user->accountant->branch->agents->pluck('refundClients')->flatten();
            $refunds = Refund::with('task.client', 'task.agent')
                ->whereIn('agent_id', $refundClients->pluck('id'))
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

        $originalInvoiceDetail = InvoiceDetail::where('task_id', $task->originalTask->id)->first();
        
        if (!$originalInvoiceDetail) {
            return redirect()->back()->withErrors(['error' => 'Original task has not been invoiced yet.']);
        }
    
        if ($originalInvoiceDetail->invoice->status === 'paid by refund') {
            return redirect()->back()->withErrors(['error' => 'The invoice from the original task has already been settled by a previous refund.']);
        }

        $invoicePaymentStatus = $task->originalTask->invoiceDetail->invoice->status;

        if(!($invoicePaymentStatus === 'paid' || $invoicePaymentStatus === 'unpaid')){
           Log::error('Invoice status of task ID ' . $task->id . ' is ' . $invoicePaymentStatus . ' which is not valid for refund processing.');
           return redirect()->back()->withErrors(['error' => 'Invoice with payment status of ' . $invoicePaymentStatus . ' cannot be processed for refund yet. Sorry for the inconvenience.']);
        }

        $invoicePaid = $task->originalTask->invoiceDetail->invoice->status === 'paid';

        // Get payment gateways and methods for the agent's company
        $paymentGateways = Charge::where('company_id', $task->agent->branch->company_id)
            ->where('is_active', true)
            ->get();
        
        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        // Calculate gateway fees if needed
        $invoiceAmount = $originalInvoiceDetail->invoice->amount ?? 0;
        foreach ($paymentGateways as $gateway) {
            if (strtolower($gateway->name) === 'myfatoorah') {
                foreach($paymentMethods as $method){
                    if($method->company_id == $task->agent->branch->company_id && $method->type == 'myfatoorah'){
                        try {
                            $method->gateway_fee = ChargeService::FatoorahCharge($invoiceAmount, $method->id, $task->agent->branch->company_id)['fee'] ?? 0;
                        } catch (\Exception $e) {
                            Log::error('FatoorahCharge exception in refund', [
                                'message' => $e->getMessage(),
                                'paymentMethod' => $method->id,
                                'company_id' => $task->agent->branch->company_id,
                            ]);
                            $method->gateway_fee = 0;
                        }
                    }
                }
            }  else if (strtolower($gateway->name) === 'upayment') {
                foreach($paymentMethods as $method){
                    if($method->company_id == $task->agent->branch->company_id && $method->type == 'upayment'){
                        try {
                            $method->gateway_fee = ChargeService::UPaymentCharge($invoiceAmount, $method->id, $task->agent->branch->company_id)['fee'] ?? 0;
                        } catch (\Exception $e) {
                            Log::error('UPaymentCharge exception in refund', [
                                'message' => $e->getMessage(),
                                'paymentMethod' => $method->id,
                                'company_id' => $task->agent->branch->company_id,
                            ]);
                            $method->gateway_fee = 0;
                        }
                    }
                }
            } else if (strtolower($gateway->name) === 'hesabe') {
                foreach ($paymentMethods as $method) {
                    if ($method->company_id == $task->agent->branch->company_id && $method->type == 'hesabe') {
                        try {
                            $method->gateway_fee = ChargeService::HesabeCharge($invoiceAmount, $method->id, $task->agent->branch->company_id)['fee'] ?? 0;
                        } catch (Exception $e) {
                            Log::error('HesabeCharge exception in refund', [
                                'message' => $e->getMessage(),
                                'paymentMethod' => $method->id,
                                'company_id' => $task->agent->branch->company_id,
                            ]);
                        }   
                    }
                }
            } else {
                $gateway->gateway_fee = ChargeService::TapCharge([
                    'amount' => $invoiceAmount,
                    'client_id' => $task->originalTask->client_id,
                    'agent_id' => $task->agent_id,
                    'currency' => $originalInvoiceDetail->invoice->currency ?? 'USD'
                ], $gateway->name)['fee'] ?? 0;
            }
        }

        $task->calculated_refund_charge = $task->originalTask->total - $task->total;

        return view('refunds.create', [
            'task' => $task,
            'invoicePaid' => $invoicePaid,
            'invoiceDetail' => $originalInvoiceDetail,
            // 'hasTicketedTasksWithReference' => $hasTicketedReference,
            'paymentGateways' => $paymentGateways,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function store(Request $request)
    {   
        $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'total_nett_refund' => ['required', 'numeric', 'min:-999999.99'],
            'reason' => ['nullable', 'string'],
            'method' => ['required', 'in:Bank,Cash,Online,Credit'],
            'date' => ['required', 'date'],
        ]);

        $task = Task::findOrFail($request->input('task_id'));

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
                'method' => $request->method,
                'date' => $request->date,
                'reference' => $request->reference,
                'status' => 'processed',
                'created_by' => Auth::user()->id,
            ]);

            if ($task->id) {
                // Create Transaction Record
                $transaction = Transaction::create([
                    'entity_id' => $task->company_id,
                    'entity_type' => 'company',
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'transaction_type' => 'debit',
                    'transaction_date' => $request->date,
                    'amount' => $request->input('total_nett_refund'),
                    'description' => 'Refund Recorded: ' . $refund->refund_number,
                    'reference_type' => 'Refund',
                    'reference_number' => $request->bankpaymentref,
                    'name' => $task->client_name,
                    'remarks_internal' => $request->input('remarks_internal'),
                ]);

                $agent = $task->agent;

                if (in_array(strtolower($agent->agentType?->name), ['commission', 'type-a'], true)) {
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
                            'company_id' => $task->company_id,
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
                            'company_id' => $task->company_id,
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
                        'company_id' => $task->company_id,
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
                        'type' => 'Refund',
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

    public function storeForUnpaidInvoice(Request $request) : RedirectResponse
    {
        $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'invoice_price' => ['required', 'numeric', 'min:0'],
            'original_invoice_price' => ['required', 'numeric', 'min:0'],
            'original_task_profit' => ['required', 'numeric'],
            'supplier_charge' => ['required', 'numeric'],
            'new_agent_markup' => ['required', 'numeric'],
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
            'payment_gateway_option' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'numeric'],
            'service_charge' => ['required', 'numeric', 'min:0']
        ]);

        $task = Task::findOrFail($request->input('task_id'));
        $origTask = $task->originalTask()->with(['agent', 'company', 'invoiceDetail.invoice'])->first();
        $invoice = $origTask->invoiceDetail->invoice;
        $invoicePaid = ($invoice->status === 'paid');

        if($invoicePaid){
            Log::error('Attempted to process refund for unpaid invoice with task ID ' . $task->id . ' but the original invoice is already paid.');
            return redirect()->back()->withErrors(['error' => 'The invoice from the original task is already paid. Please use the standard refund process.']);
        }

        //total net refund = original invoice price - new invoice price
        $calculatedTotalNetRefund = $request->input('original_invoice_price') - $request->input('invoice_price');

        $refundInvoicePrice = $request->input('invoice_price');

        DB::beginTransaction();
        try {
            $refund = Refund::create([
                'refund_number' => 'RF-' . now()->timestamp, 
                'task_id' => $task->id,
                'company_id' => $task->company_id,
                'branch_id' => $task->agent->branch_id,
                'agent_id' => $task->agent_id,
                'remarks' => $request->input('remarks'),
                'remarks_internal' => $request->input('remarks_internal'),
                'airline_nett_fare' => $request->input('original_invoice_price'),
                'tax_refund' => $request->input('tax_refund'),
                'refund_airline_charge' => $request->input('refund_airline_charge'),
                'original_task_profit' => $request->input('original_task_profit'),
                'new_task_profit' => $request->input('new_agent_markup'),
                'total_nett_refund' => $calculatedTotalNetRefund,
                'service_charge' => $request->input('service_charge'),
                'method' => $request->input('method', 'Bank'),
                'payment_gateway' => $request->input('payment_gateway_option'),
                'payment_method' => $request->input('payment_method'),
                'date' => $request->date,
                'reference' => $request->reference,
                'reason' => $request->reason,
                'status' => 'processed',
                'created_by' => Auth::user()->id,
            ]);

            if (!$invoicePaid) {
                $invoice->status = 'paid by refund'; //need to add one more status for refund like 'paid refund'
                $invoice->save();

                $transaction = Transaction::where('invoice_id', $invoice->id)->first();
                if (!$transaction) {
                    $transaction = Transaction::create([
                        'company_id' => $origTask->company_id,
                        'branch_id' => $origTask->agent->branch_id,
                        'entity_id' => $origTask->company_id,
                        'entity_type' => 'company',
                        'transaction_type' => 'credit',
                        'amount' =>  $invoice->amount,
                        'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
                        'invoice_id' => $invoice->id,
                        'reference_type' => 'Invoice',
                        'transaction_date' => $invoice->invoice_date,
                    ]);

                    app(InvoiceController::class)->addJournalEntry(
                        $origTask,
                        $invoice->id,
                        $origTask->invoiceDetail->id,
                        $transaction->id,
                        $invoice->client->full_name
                    );
                }
            }

            $agent = $task->agent;

            if (in_array(strtolower($agent->agentType?->name), ['commission', 'type-a'], true)) {
                $agentCommission = $agent->commission;

                $txnRefund = Transaction::create([
                    'entity_id' => $task->company_id,
                    'entity_type' => 'company',
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'transaction_type' => 'debit',
                    'transaction_date' => $request->date,
                    'amount' => $refundInvoicePrice,
                    'description' => 'Refund - Record Agent Commission',
                    'reference_type' => 'Refund',
                    'reference_number' => $request->reference,
                    'name' => $task->client_name,
                    'remarks_internal' => $request->input('remarks_internal'),
                ]);

                $assetsDirectIncome = Account::where('name', 'LIKE', '%Direct Expenses%')
                    ->where('company_id', $task->company_id)
                    ->where('root_id', 5)
                    ->first();

                $incomeRefundAccountEntry = Account::where('name', 'LIKE', '%Commissions Expense (Agents)%')
                    ->where('company_id', $task->company_id)
                    ->where('root_id', $assetsDirectIncome->root_id)
                    ->first();

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
                        'company_id' => $task->company_id,
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

                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $txnRefund->id,
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'account_id' => $incomeRefundAccountEntry->id,
                    'description' => 'Refund Commission - Agent gets ' . $agentCommission * 100 . '% of refund fee (Assets): ' . $incomeRefundAccountEntry->name . '',
                    'debit' => $request->input('new_agent_markup') * $agentCommission,
                    'credit' => 0,
                    'voucher_number' => $refund->id,
                    'name' => $incomeRefundAccountEntry->name,
                    'type' => 'refund',
                ]);

                JournalEntry::create([
                    'transaction_date' => $request->date,
                    'transaction_id' => $txnRefund->id,
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'account_id' => $incomeRefundIncomeAccEntry->id,
                    'description' => 'Refund Commission - Agent gets ' . $agentCommission * 100 . '% of refund fee (Liabilities): ' . $incomeRefundIncomeAccEntry->name . '',
                    'debit' => 0,
                    'credit' => $request->input('new_agent_markup') * $agentCommission,
                    'voucher_number' => $refund->id,
                    'name' => $incomeRefundIncomeAccEntry->name,
                    'type' => 'refund',
                ]);
            }

   
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->withErrors(['error' => 'An unexpected error occurred while processing the refund.']);
        }

        $supplierCharge = $request->input('original_task_profit') + $request->input('supplier_charge');

        $createInvoiceResponse = $this->createInvoiceFromRefund(
            $task,
            $refund,
            $refundInvoicePrice,
            $supplierCharge,
            $request->input('payment_gateway_option'),
            $request->input('payment_method')
        );

        if($createInvoiceResponse instanceof JsonResponse && $createInvoiceResponse->getStatusCode() !== 200) {
            // Rollback the refund and related transactions if invoice creation failed
            DB::beginTransaction();
            try {
                // Delete the refund
                $refund->delete();
                // Revert the invoice status if it was changed
                if (isset($invoice) && $invoice->status === 'paid by refund') {
                    $invoice->status = 'unpaid';
                    $invoice->save();
                }
                // Delete the transaction related to the refund
                if (isset($txnRefund)) {
                    Transaction::where('id', $txnRefund->id)->delete();
                }
                DB::commit();
            } catch (Throwable $rollbackException) {
                DB::rollBack();
                Log::error('Failed to rollback refund process for refund ID ' . $refund->id . ': ' . $rollbackException->getMessage());
            }

            return redirect()->back()->withErrors(['error' => $createInvoiceResponse->getData(true)['error'] ?? 'Something went wrong'])->withInput();
        }


        $invoiceId = $createInvoiceResponse->getData(true)['invoiceId'];

        if (!$invoiceId) {
            Log::error('Invoice Id not found in the response for refund with ID ' . $refund->id . ' and task ID ' . $task->id ,[ 'response' => $createInvoiceResponse ]);
            
            // Rollback the refund and related transactions if invoice creation failed
            DB::beginTransaction();
            try {
                // Delete the refund
                $refund->delete();
                // Revert the invoice status if it was changed
                if (isset($invoice) && $invoice->status === 'paid by refund') {
                    $invoice->status = 'unpaid';
                    $invoice->save();
                }
                // Delete the transaction related to the refund
                if (isset($txnRefund)) {
                    Transaction::where('id', $txnRefund->id)->delete();
                }
                DB::commit();
            } catch (Throwable $rollbackException) {
                DB::rollBack();
                Log::error('Failed to rollback refund process for refund ID ' . $refund->id . ': ' . $rollbackException->getMessage());
            }

            Log::error('Failed to create invoice from refund for refund ID ' . $refund->id . ' and task ID ' . $task->id);
            return redirect()->back()->withErrors(['error' => 'An unexpected error occurred while processing the refund. Please try again.']);
        }

        $refund->update(['invoice_id' => $invoiceId]);

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

        $task = Task::with('originalTask.invoiceDetail.invoice')->find($task->id);

        $invoice = Invoice::with('invoicePartials')->whereHas('invoiceDetails', function ($query) use ($task) {
            $query->where('task_id', $task->originalTask->id);
        })->first();

        $originalInvoiceDetail = $invoice->invoiceDetails->first();

        $refundInvoice = $refund->invoice;
        $refundInvoiceDetail = $refundInvoice ? $refundInvoice->invoiceDetails->first() : null;

        $invoicePaid = data_get($task, 'originalTask.invoiceDetail.invoice.status') === 'paid';

        $invoiceDetail = $originalInvoiceDetail;

        // Get payment gateways and methods for the agent's company
        $paymentGateways = Charge::where('company_id', $task->agent->branch->company_id)
            ->where('is_active', true)
            ->get();

        // foreach($paymentGateways as $gateway) {
        //     dump($gateway->name);
        // }
        
        $paymentMethods = PaymentMethod::where('is_active', true)->get();

        // Calculate gateway fees if needed
        $invoiceAmount = $invoice->amount ?? 0;
        foreach ($paymentGateways as $gateway) {
            if (strtolower($gateway->name) === 'myfatoorah') {
                foreach($paymentMethods as $method){
                    if($method->company_id == $task->agent->branch->company_id && $method->type == 'myfatoorah'){
                        try {
                            $method->gateway_fee = ChargeService::FatoorahCharge($refund->airline_nett_fare - $refund->total_nett_refund, $method->id, $task->agent->branch->company_id)['fee'] ?? 0;
                        } catch (Exception $e) {
                            Log::error('FatoorahCharge exception in refund edit', [
                                'message' => $e->getMessage(),
                                'paymentMethod' => $method->id,
                                'company_id' => $task->agent->branch->company_id,
                            ]);
                            $method->gateway_fee = 0;
                        }
                    }
                }
            } else if (strtolower($gateway->name) === 'upayment') {

                foreach ($paymentMethods as $method) {
                    if ($method->company_id == $task->agent->branch->company_id && $method->type == 'upayment') {
                        try {
                            $method->gateway_fee = ChargeService::UPaymentCharge($refund->airline_nett_fare - $refund->total_nett_refund, $method->id, $task->agent->branch->company_id)['fee'] ?? 0;
                        } catch (Exception $e) {
                            Log::error('UPaymentCharge exception in refund edit', [
                                'message' => $e->getMessage(),
                                'paymentMethod' => $method->id,
                                'company_id' => $task->agent->branch->company_id,
                            ]);
                            $method->gateway_fee = 0;
                        }
                    }
                }
            } else if (strtolower($gateway->name) === 'hesabe') {
                foreach ($paymentMethods as $method) {
                    if ($method->company_id == $task->agent->branch->company_id && $method->type == 'hesabe') {
                        try {
                            $method->gateway_fee = ChargeService::HesabeCharge($refund->airline_nett_fare - $refund->total_nett_refund, $method->id, $task->agent->branch->company_id)['fee'] ?? 0;
                        } catch (Exception $e) {
                            Log::error('HesabeCharge exception in refund edit', [
                                'message' => $e->getMessage(),
                                'paymentMethod' => $method->id,
                                'company_id' => $task->agent->branch->company_id,
                            ]);
                            $method->gateway_fee = 0;
                        }
                    }
                }
            } else {
                $gateway->gateway_fee = ChargeService::TapCharge([
                    'amount' => $refund->airline_nett_fare - $refund->total_nett_refund,
                    'client_id' => $task->client_id,
                    'agent_id' => $task->agent_id,
                    'currency' => $invoice->currency ?? 'USD'
                ], $gateway->name)['fee'] ?? 0;
            }
        }

        return view('refunds.edit', compact(
            'refund',
            'coaAccounts',
            'tasks',
            'task',
            'invoicePaid',
            'invoiceDetail',
            'originalInvoiceDetail',
            'refundInvoiceDetail',
            'paymentGateways',
            'paymentMethods',
        ));
    }

    public function update(Request $request, Task $task, Refund $refund)
    {
        // Check if this is an unpaid invoice update (has invoice_price field)
        $isUnpaidInvoiceUpdate = $request->has('invoice_price');
        
        if ($isUnpaidInvoiceUpdate) {
            // Validate for unpaid invoice update
            $request->validate([
                'invoice_price' => ['required', 'numeric', 'min:0'],
                'original_invoice_price' => ['required', 'numeric', 'min:0'],
                'original_task_profit' => ['required', 'numeric'],
                'supplier_charge' => ['required', 'numeric'],
                'new_agent_markup' => ['required', 'numeric'],
                'date' => ['required', 'date'],
                'reference' => ['nullable', 'string'],
                'remarks' => ['nullable', 'string'],
                'remarks_internal' => ['nullable', 'string'],
                'reason' => ['nullable', 'string'],
                'payment_gateway_option' => ['nullable', 'string'],
                'payment_method' => ['nullable', 'numeric']
            ]);

            // Calculate total net refund for unpaid invoice
            $calculatedTotalNetRefund = $request->input('original_invoice_price') - $request->input('invoice_price');

            // Update refund with unpaid invoice data
            $refund->update([
                'airline_nett_fare' => $request->input('original_invoice_price'),
                'original_task_profit' => $request->input('original_task_profit'),
                'new_task_profit' => $request->input('new_agent_markup'),
                'total_nett_refund' => $calculatedTotalNetRefund,
                'service_charge' => $request->input('supplier_charge'),
                'payment_gateway' => $request->input('payment_gateway_option'),
                'payment_method' => $request->input('payment_method'),
                'date' => $request->date,
                'reference' => $request->reference,
                'remarks' => $request->input('remarks'),
                'remarks_internal' => $request->input('remarks_internal'),
                'reason' => $request->input('reason'),
            ]);

            $invoice = $refund->invoice;

            if (!$invoice) {
                Log::error('No invoice associated with refund ID ' . $refund->id . ' during update process.');
                return redirect()->back()->withErrors(['error' => 'No invoice associated with this refund. Please contact support.']);
            }

            // Update the associated invoice's amount
            $invoice->amount = $request->input('invoice_price');
            $invoice->save();

            $invoicePartial = $invoice->invoicePartials->first();

            // If there are existing partial payments, adjust them if necessary
            if ($invoicePartial) {
                $invoicePartial->update(['amount' => $request->input('invoice_price')]);
            }


            $invoiceDetail = $invoice->invoiceDetails->first();
            if ($invoiceDetail) {
                $invoiceDetail->update([
                    'task_price' => $request->input('invoice_price'),
                    'agent_markup' => $request->input('new_agent_markup'),
                    'supplier_charge' => $request->input('supplier_charge'),
                ]);
            }

            $updateGatewayResponse = app(InvoiceController::class)->updatePaymentGateway(new Request([
                'invoiceId' => $refund->invoice_id,
                'gateway' => $request->input('payment_gateway_option'),
                'method' => $request->input('payment_method'),
                'amount' => $request->input('invoice_price'),
                'invoiceNumber' => $refund->invoice ? $refund->invoice->invoice_number : null,
            ]));

            if ($updateGatewayResponse->status() !== 200) {
                Log::error('Failed to update payment gateway for refund ID ' . $refund->id . ' with response: ', (array) $updateGatewayResponse->getData());
                return redirect()->back()->withErrors(['error' => 'Failed to update payment gateway information. Please try again.']);
            }
        } else {
            // Validate for regular paid invoice update
            $request->validate([
                'date' => 'required|date',
                'method' => 'required|string',
                'total_nett_refund' => 'required|numeric|min:0',
                'refund_airline_charge' => 'nullable|numeric|min:0',
                'original_task_profit' => 'nullable|numeric|min:0',
                'new_task_profit' => 'nullable|numeric|min:0',
                'reason' => 'nullable|string|max:1000',
                'remarks' => 'nullable|string',
                'remarks_internal' => 'nullable|string',
            ]);

            // Update the refund with validated data
            $refund->update($request->all());
        }

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
                'transaction_date' => $refundRec->date,
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
                    'company_id' => $taskRec->company_id,
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
                    'company_id' => $taskRec->company_id,
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

    public function createInvoiceFromRefund(
        Task $task,
        Refund $refund,
        float $invoicePrice,
        float $supplierCharge,
        string $paymentGateway,
        ?string $paymentMethod = null
    ): JsonResponse
    {
        $user = Auth::user();

        if ($refund->task_id !== $task->id) {
            Log::error('Invalid Task or Refund. Task ID ' . $task->id . ' does not match Refund Task ID ' . $refund->task_id);
            return response()->json(['error' => 'Invalid Task or Refund.'], 400);
        }

        $companyId = $task->company_id;

        try {
            // Generate invoice number
            $invoiceSequence = InvoiceSequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
            $currentSequence = $invoiceSequence->current_sequence;
            $invoiceNumber = app(InvoiceController::class)->generateInvoiceNumber($currentSequence);


            // Create Invoice
            try {
                $invoice = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'client_id' => $refund->task->client_id,
                    'agent_id' => $refund->task->agent_id,
                    'currency' => $task->exchange_currency ?? 'KWD',
                    'sub_amount' => $invoicePrice,
                    'invoice_charge' => 0,
                    'amount' => $invoicePrice,
                    'status' => 'unpaid',
                    'invoice_date' => $refund->date,
                    'paid_date' => null,
                    'due_date' => Carbon::parse($refund->date)->addDays(5)->toDateString(),
                    'label' => 'refund',
                    'payment_type' => 'full'
                ]);

                // Create Invoice Detail
                $invoiceDetail = InvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'task_id' => $task->id,
                    'task_description' => 'Refund for Task ID: ' . $task->id . ' - ' . ($task->reference ?? ''),
                    'task_price' => $invoicePrice,
                    'supplier_price' => $supplierCharge,
                    'markup_price' => $refund->new_task_profit,
                    'created_by' => Auth::user()->id,
                ]);

                // Create Invoice Partial
                $invoicePartial = InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'client_id' => $refund->task->client_id,
                    'service_charge' => $refund->service_charge,
                    'amount' => $invoicePrice,
                    'status' => 'unpaid',
                    'expiry_date' => Carbon::parse($refund->date)->addDays(5)->toDateString(),
                    'type' => 'full',
                    'payment_gateway' => $paymentGateway,
                    'payment_method' => $paymentMethod,
                    'charge_id' => Charge::where('name', $paymentGateway)->value('id'),
                ]);
    
                $transaction = Transaction::where('invoice_id', $invoice->id)->first();
                if (!$transaction) {
                    $transaction = Transaction::create([
                        'company_id' => $task->company_id,
                        'branch_id' => $task->agent->branch_id,
                        'entity_id' => $task->company_id,
                        'entity_type' => 'company',
                        'transaction_type' => 'credit',
                        'amount' =>  $invoice->amount,
                        'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
                        'invoice_id' => $invoice->id,
                        'reference_type' => 'Invoice',
                        'transaction_date' => $invoice->invoice_date,
                    ]);

                    app(InvoiceController::class)->addJournalEntry(
                        $task,
                        $invoice->id,
                        $invoiceDetail->id,
                        $transaction->id,
                        $invoice->client->full_name
                    );
                }
            } catch (Exception $e) {
                Log::error('Failed to create invoice or related records: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to create invoice or related records.'], 500);
            }
            // Update invoice sequence
            $invoiceSequence->increment('current_sequence');

            Log::info('Invoice created successfully from refund', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'amount' => $invoicePrice,
                'refund_id' => $refund->id
            ]);

            return response()->json([
                'success' => true,
                'invoiceId' => $invoice->id,
                'invoiceNumber' => $invoiceNumber,
                'message' => 'Invoice created successfully from refund'
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to create invoice from refund: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create invoice from refund.'], 500);
        }
    }
}
