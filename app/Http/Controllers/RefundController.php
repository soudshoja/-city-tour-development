<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\InvoicePartial;
use App\Models\Refund;
use App\Models\RefundDetail;
use App\Models\RefundSequence;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Task;
use App\Models\JournalEntry;
use App\Models\Credit;
use App\Models\Role;
use App\Models\Client;
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
            $refunds = Refund::with([
                'refundDetails.task.client',
                'refundDetails.task.agent',
                'refundDetails.invoice'
            ])
                ->where('company_id', Auth::user()->company->id)
                ->orderBy('id', 'desc')
                ->get();
        } elseif (Auth::user()->role->id == Role::BRANCH) {
            $refundClients = $user->branch->agents->refundClients;
            $refunds = Refund::with([
                'refundDetails.task.client',
                'refundDetails.task.agent',
                'refundDetails.invoice'
            ])
                ->where('branch_id', Auth::user()->branch->id)
                ->orderBy('id', 'desc')
                ->get();
        } elseif (Auth::user()->role->id == Role::AGENT) {
            $refundClients = $user->agent->refundClients;
            $refunds = Refund::with([
                'refundDetails.task.client',
                'refundDetails.task.agent',
                'refundDetails.invoice'
            ])
                ->where('agent_id', $user->agent->id)
                ->orderBy('id', 'desc')
                ->get();
        } elseif (Auth::user()->role->id == Role::ACCOUNTANT) {
            $refundClients = $user->accountant->branch->agents->pluck('refundClients')->flatten();
            $refunds = Refund::with([
                'refundDetails.task.client',
                'refundDetails.task.agent',
                'refundDetails.invoice'
            ])
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

    public function generateRefundNumber($sequence)
    {
        $year = now()->year;
        return sprintf('RF-%s-%05d', $year, $sequence);
    }

    public function create(Request $request)
    {
        $taskIds = $request->query('task_ids', '');
        $taskIdsArray = is_string($taskIds) ? explode(',', $taskIds) : $taskIds;

        if (empty($taskIdsArray)) {
            return redirect()->back()->withErrors(['error' => 'No tasks selected for refund.']);
        }

        $tasks = Task::with([
            'agent.branch.company',
            'client',
            'originalTask.invoiceDetail.invoice',
            'originalTask.invoiceDetail'
        ])->whereIn('id', $taskIdsArray)->get();

        if ($tasks->isEmpty()) {
            return back()->withErrors(['error' => 'No valid tasks found for refund.']);
        }

        $allClients = collect();
        $invoiceIds = collect();
        foreach ($tasks as $task) {
            if (
                !$task->originalTask || !$task->originalTask->invoiceDetail || !$task->originalTask->invoiceDetail->invoice
            ) {
                return redirect()->back()->withErrors(['error' => "Original task for {$task->reference} has not been invoiced yet or invoice details are missing."]);
            }

            if (($task->agent->agent_type_id ?? 1) != 1 && ($task->agent->commission <= 0)) {
                return redirect()->back()->withErrors([
                    'error' => "The agent for task {$task->reference} does not have a valid commission to process a refund. Please set a valid commission for the agent."
                ]);
            }

            $invoicePaymentStatus = strtolower($task->originalTask->invoiceDetail->invoice->status);
            if (!in_array($invoicePaymentStatus, ['paid', 'unpaid', 'partial', 'credit'])) {
                Log::error('Invoice status of task ' . $task->reference . ' is ' . $invoicePaymentStatus . ' which is not valid for refund processing.');
                return redirect()->back()->withErrors([
                    'error' => 'Invoice with payment status of ' . $invoicePaymentStatus . ' cannot be processed for refund yet. Sorry for the inconvenience.'
                ]);
            }
            $allClients->push($task->client);
            $invoiceIds->push($task->originalTask->invoiceDetail->invoice->id);
        }

        if ($invoiceIds->unique()->count() > 1) {
            return redirect()->back()->withErrors([
                'error' => 'Refund cannot include tasks from different original invoices. Please process each invoice refund separately.'
            ]);
        }

        $uniqueClients = $allClients->unique('id');

        $paymentGateways = Charge::where('can_generate_link', true)
            ->where('is_active', true)
            ->get();

        $paymentMethods = PaymentMethod::where('is_active', true)
            ->where('company_id', $tasks->first()->agent->branch->company_id)
            ->get();

        return view('refunds.create-multi', [
            'tasks' => $tasks,
            'uniqueClients' => $uniqueClients,
            'paymentGateways' => $paymentGateways,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    // public function store(Request $request)
    // {
    //     $validatedData = $request->validate([
    //         'date' => ['required', 'date'],
    //         'reference' => ['nullable', 'string'],
    //         'method' => ['nullable', 'in:Bank,Cash,Online,Credit'],
    //         'remarks' => ['nullable', 'string'],
    //         'client_id' => ['nullable', 'exists:clients,id'],
    //         'tasks' => ['required', 'array', 'min:1'],
    //         'tasks.*.task_id' => ['required', 'exists:tasks,id'],
    //         'tasks.*.refund_fee_to_client' => ['required', 'numeric'],
    //         'tasks.*.supplier_charge' => ['required', 'numeric'],
    //         'tasks.*.original_task_profit' => ['required', 'numeric'],
    //         'tasks.*.new_task_profit' => ['required', 'numeric'],
    //         'tasks.*.total_nett_refund_charge' => ['required', 'numeric'],
    //         'tasks.*.remarks' => ['nullable', 'string'],
    //     ]);

    //     DB::beginTransaction();
    //     try {
    //         $totalNetRefund = 0;
    //         $totalRefundChargesToCollect = 0;
    //         $refundDetailsData = [];
    //         $tasksToUpdate = [];

    //         foreach ($validatedData['tasks'] as $taskData) {
    //             $task = Task::with('originalTask.invoiceDetail.invoice', 'client', 'agent.branch')
    //                 ->findOrFail($taskData['task_id']);
    //             $originalInvoice = $task->originalTask->invoiceDetail->invoice;
    //             $originalInvoiceDetail = $task->originalTask->invoiceDetail;

    //             $invoicePaymentStatus = strtolower($originalInvoice->status);
    //             $amountPaidForTask = $originalInvoiceDetail->amount_paid ?? 0;
    //             $netAmountForTask = $taskData['total_nett_refund_charge'];

    //             $refundDetailsData[] = [
    //                 'task_id' => $task->id,
    //                 'invoice_id' => $originalInvoice->id,
    //                 'client_id' => $task->client->id,
    //                 'task_description' => $task->reference,
    //                 'original_invoice_price' => $originalInvoiceDetail->task_price,
    //                 'original_task_cost' => $originalInvoiceDetail->supplier_price,
    //                 'original_task_profit' => $taskData['original_task_profit'],
    //                 'refund_fee_to_client' => $taskData['refund_fee_to_client'],
    //                 'supplier_charge' => $taskData['supplier_charge'],
    //                 'new_task_profit' => $taskData['new_task_profit'],
    //                 'total_refund_to_client' => $netAmountForTask,
    //                 'remarks' => $taskData['remarks'],
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ];

    //             if ($invoicePaymentStatus == 'paid' || $invoicePaymentStatus == 'credit') {
    //                 $totalNetRefund += $netAmountForTask;
    //                 $tasksToUpdate[] = $task->id;
    //             } elseif ($invoicePaymentStatus == 'unpaid') {
    //                 if ($netAmountForTask < 0) {
    //                     $totalRefundChargesToCollect += abs($netAmountForTask);
    //                     $tasksToUpdate[] = $task->id;
    //                 }
    //             } elseif ($invoicePaymentStatus == 'partial') {
    //                 if ($amountPaidForTask >= abs($netAmountForTask)) {
    //                     $totalNetRefund += $netAmountForTask;
    //                     $tasksToUpdate[] = $task->id;
    //                 } else {
    //                     $chargesToCollect = abs($netAmountForTask) - $amountPaidForTask;
    //                     $totalRefundChargesToCollect += $chargesToCollect;
    //                     $tasksToUpdate[] = $task->id;
    //                 }
    //             }
    //         }

    //         $firstTask = Task::find($validatedData['tasks'][0]['task_id']);

    //         $refundSequence = RefundSequence::firstOrCreate(['company_id' => $firstTask->company_id], ['current_sequence' => 1]);
    //         $refundNumber = $this->generateRefundNumber($refundSequence->current_sequence);
    //         $refundSequence->increment('current_sequence');

    //         $refund = Refund::create([
    //             'refund_number' => $refundNumber,
    //             'company_id' => $firstTask->company_id,
    //             'branch_id' => $firstTask->agent->branch_id,
    //             'agent_id' => $firstTask->agent_id,
    //             'method' => $validatedData['method'] ?? null,
    //             'reference' => $validatedData['reference'] ?? null,
    //             'remarks' => $validatedData['remarks'] ?? null,
    //             'reason' => $validatedData['remarks'] ?? null,
    //             'total_refund_amount' => array_sum(array_column($refundDetailsData, 'original_invoice_price')),
    //             'total_refund_charge' => array_sum(array_column($refundDetailsData, 'supplier_charge')),
    //             'total_nett_refund' => $totalNetRefund,
    //             'status' => 'processed',
    //             'refund_date' => $validatedData['date'],
    //             'created_by' => Auth::user()->id,
    //         ]);

    //         foreach ($refundDetailsData as $detail) {
    //             $detail['refund_id'] = $refund->id;
    //             RefundDetail::create($detail);
    //         }

    //         if ($totalNetRefund > 0) {
    //             $agent = $firstTask->agent;
    //             $client = Client::findOrFail($validatedData['client_id'] ?? $refundDetailsData[0]['client_id']);

    //             $transaction = Transaction::create([
    //                 'entity_id' => $firstTask->company_id,
    //                 'entity_type' => 'company',
    //                 'company_id' => $firstTask->company_id,
    //                 'branch_id' => $firstTask->agent->branch_id,
    //                 'transaction_type' => 'debit',
    //                 'transaction_date' => $validatedData['date'],
    //                 'amount' => $totalNetRefund,
    //                 'description' => 'Refund Recorded: ' . $refund->refund_number,
    //                 'reference_type' => 'Refund',
    //                 'reference_number' => $request->bankpaymentref,
    //                 'name' => $client->full_name,
    //                 'remarks_internal' => $validatedData['remarks'],
    //             ]);

    //             if (in_array(strtolower($agent->agentType?->name), ['commission', 'type-a'], true)) {
    //                 $agentCommission = $agent->commission;

    //                 $assetsDirectIncome = Account::where('name', 'LIKE', '%Direct Expenses%')
    //                     ->where('company_id', $firstTask->company_id)
    //                     ->where('root_id', 5)
    //                     ->first();

    //                 $accountIncomeName = 'Commissions Expense (Agents)';

    //                 $incomeRefundAccountEntry = Account::firstOrCreate([
    //                     'name' => $accountIncomeName,
    //                     'company_id' => $firstTask->company_id,
    //                     'root_id' => $assetsDirectIncome->root_id,
    //                 ], [
    //                     'parent_id' => $assetsDirectIncome->id,
    //                     'branch_id' => $firstTask->agent->branch_id,
    //                     'account_type' => 'asset',
    //                     'report_type' => 'balance sheet',
    //                     'level' => $assetsDirectIncome->level + 1,
    //                     'is_group' => 0,
    //                     'disabled' => 0,
    //                     'currency' => 'KWD',
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);

    //                 $incomeIndirectIncomeRec = Account::where('name', 'LIKE', '%Accrued Expenses%')
    //                     ->where('company_id', $firstTask->company_id)
    //                     ->where('root_id', 2)
    //                     ->first();

    //                 $accountincomeRefundIncomeRec = 'Commission (Agents)';

    //                 $incomeRefundIncomeAccEntry = Account::firstOrCreate([
    //                     'name' => $accountincomeRefundIncomeRec,
    //                     'company_id' => $firstTask->company_id,
    //                     'root_id' => $incomeIndirectIncomeRec->root_id,
    //                 ], [
    //                     'parent_id' => $incomeIndirectIncomeRec->id,
    //                     'branch_id' => $firstTask->agent->branch_id,
    //                     'account_type' => 'asset',
    //                     'report_type' => 'balance sheet',
    //                     'level' => $incomeIndirectIncomeRec->level + 1,
    //                     'is_group' => 0,
    //                     'disabled' => 0,
    //                     'currency' => 'KWD',
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);

    //                 // Step 3: Debit Entry (Expense)
    //                 JournalEntry::create([
    //                     'transaction_date' => $validatedData['date'],
    //                     'transaction_id' => $transaction->id,
    //                     'company_id' => $firstTask->company_id,
    //                     'branch_id' => $firstTask->agent->branch_id,
    //                     'account_id' => $incomeRefundAccountEntry->id,
    //                     'description' => 'Refund Commission - Agent gets ' . ($agentCommission * 100) . '% (Assets): ' . $incomeRefundAccountEntry->name,
    //                     'debit' => $firstTask->new_task_profit * $agentCommission,
    //                     'credit' => 0,
    //                     'voucher_number' => $refund->id,
    //                     'name' => $incomeRefundAccountEntry->name,
    //                     'type' => 'refund',
    //                 ]);

    //                 // Step 4: Credit Entry (Liability)
    //                 JournalEntry::create([
    //                     'transaction_date' => $validatedData['date'],
    //                     'transaction_id' => $transaction->id,
    //                     'company_id' => $firstTask->company_id,
    //                     'branch_id' => $firstTask->agent->branch_id,
    //                     'account_id' => $incomeRefundIncomeAccEntry->id,
    //                     'description' => 'Refund Commission - Agent gets ' . ($agentCommission * 100) . '% (Liabilities): ' . $incomeRefundIncomeAccEntry->name,
    //                     'debit' => 0,
    //                     'credit' => $firstTask->new_task_profit * $agentCommission,
    //                     'voucher_number' => $refund->id,
    //                     'name' => $incomeRefundIncomeAccEntry->name,
    //                     'type' => 'refund',
    //                 ]);
    //             }

    //             $liabilities = Account::where('name', 'Liabilities')
    //                 ->where('company_id', $firstTask->company_id)
    //                 ->first();

    //             $refundPayable = Account::where('name', 'LIKE', '%Refund Payable%')
    //                 ->where('company_id', $firstTask->company_id)
    //                 ->where('parent_id', $liabilities->id)
    //                 ->where('root_id', $liabilities->id)
    //                 ->first();

    //             $clientRefundAccountName  = 'Clients';

    //             $accountClientRefundLiability = Account::where('name', $clientRefundAccountName)
    //                 ->where('company_id', $firstTask->company_id)
    //                 ->where('parent_id', $refundPayable->id)
    //                 ->where('root_id', $refundPayable->root_id)
    //                 ->first();

    //             if (!$accountClientRefundLiability) {
    //                 $accountClientRefundLiability = Account::create([
    //                     'name' => $clientRefundAccountName,
    //                     'parent_id' => $refundPayable->id,
    //                     'company_id' => $firstTask->company_id,
    //                     'branch_id' => Auth::user()->branch_id,
    //                     'root_id' => $liabilities->id,
    //                     'code' => $refundPayable->code + 10,
    //                     'account_type' => 'asset',
    //                     'report_type' => 'balance sheet',
    //                     'level' => $refundPayable->level + 1,
    //                     'is_group' => 0,
    //                     'disabled' => 0,
    //                     'actual_balance' => 0.00,
    //                     'budget_balance' => 0.00,
    //                     'variance' => 0.00,
    //                     'currency' => 'KWD',
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ]);
    //             }

    //             JournalEntry::create([
    //                 'transaction_date' => $request->date,
    //                 'currency' => 'KWD',
    //                 'exchange_rate' => 1.0,
    //                 'amount' => $totalNetRefund,
    //                 'name' => $firstTask->client_name,
    //                 'description' => 'Refund to Client - ' . $firstTask->client_name,
    //                 'type' => 'refund',
    //                 'debit' => $totalNetRefund,
    //                 'credit' => 0,
    //                 'balance' => $totalNetRefund,
    //                 'transaction_id' => $transaction->id,
    //                 'company_id' => $firstTask->company_id,
    //                 'account_id' => $accountClientRefundLiability->id,
    //                 'branch_id' => $firstTask->agent->branch_id,
    //                 'original_currency' => 'KWD',
    //                 'original_amount' => $totalNetRefund,
    //             ]);

    //             $user = Auth::user();
    //             $refundBy = $user->company ? 'Company' : ($user->branch ? 'Branch' : 'Company');

    //             Credit::create([
    //                 'company_id' => $firstTask->company_id,
    //                 'branch_id' => $firstTask->agent->branch_id,
    //                 'client_id' => $client->id,
    //                 'type' => 'Refund',
    //                 'description' => 'Refund for ' . $refund->refund_number,
    //                 'amount' => $totalNetRefund,
    //                 'topup_by' => $refundBy,
    //                 'created_at' => now(),
    //                 'updated_at' => now(),
    //             ]);
    //         }

    //         // === 🧾 Handle Refund Charges (client owes company) ===
    //         elseif ($totalRefundChargesToCollect > 0) {
    //             $client = Client::findOrFail($validatedData['client_id'] ?? $refundDetailsData[0]['client_id']);
    //             $newInvoice = Invoice::create([
    //                 'invoice_number' => 'INV-REFUND-' . now()->timestamp,
    //                 'company_id' => Auth::user()->company_id,
    //                 'branch_id' => Auth::user()->branch_id,
    //                 'client_id' => $client->id,
    //                 'agent_id' => Auth::user()->agent_id,
    //                 'date' => $validatedData['date'],
    //                 'due_date' => now()->addDays(7),
    //                 'total_amount' => $totalRefundChargesToCollect,
    //                 'status' => 'unpaid',
    //                 'type' => 'refund_charges',
    //                 'remarks' => 'Charges for refund ' . $refund->refund_number,
    //                 'created_by' => Auth::user()->id,
    //             ]);
    //             RefundDetail::where('refund_id', $refund->id)
    //                 ->update(['refund_invoice_id' => $newInvoice->id]);
    //         }

    //         DB::commit();
    //         return redirect()->route('refunds.index')->with('success', 'Refund processed successfully!');
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Refund processing failed: ' . $e->getMessage());
    //         return redirect()->back()->withInput()->withErrors([
    //             'error' => 'Refund processing failed: ' . $e->getMessage(),
    //         ]);
    //     }
    // }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string'],
            'method' => ['nullable', 'in:Bank,Cash,Online,Credit'],
            'remarks' => ['nullable', 'string'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.task_id' => ['required', 'exists:tasks,id'],
            'tasks.*.original_invoice_price' => ['required', 'numeric'],
            'tasks.*.original_task_cost' => ['required', 'numeric'],
            'tasks.*.original_task_profit' => ['required', 'numeric'],
            'tasks.*.refund_fee_to_client' => ['required', 'numeric'],
            'tasks.*.supplier_charge' => ['required', 'numeric'],
            'tasks.*.new_task_profit' => ['required', 'numeric'],
            'tasks.*.total_refund_to_client' => ['required', 'numeric'],
            'tasks.*.remarks' => ['nullable', 'string'],
            'tasks.*.payment_gateway_option' => ['nullable', 'string'],
            'tasks.*.payment_method' => ['nullable', 'numeric'],
        ]);

        DB::beginTransaction();
        try {
            $firstTask = Task::with('agent.branch')->findOrFail($validatedData['tasks'][0]['task_id']);

            $refundSequence = RefundSequence::firstOrCreate(['company_id' => $firstTask->company_id], ['current_sequence' => 1]);
            $refundNumber = $this->generateRefundNumber($refundSequence->current_sequence);
            $refundSequence->increment('current_sequence');

            $totalRefundAmount = 0;
            $totalRefundCharge = 0; 
            $totalNettRefund  = 0;

            foreach ($validatedData['tasks'] as $taskData) {
                $totalRefundAmount += $taskData['refund_fee_to_client'];
                $totalRefundCharge += $taskData['supplier_charge'];
                $totalNettRefund  += $taskData['total_refund_to_client'];
            }

            $refund = Refund::create([
                'refund_number' => $refundNumber,
                'company_id' => $firstTask->company_id,
                'branch_id' => $firstTask->agent->branch_id,
                'agent_id' => $firstTask->agent_id,
                'method' => $validatedData['method'] ?? null,
                'reference' => $validatedData['reference'] ?? null,
                'remarks' => $validatedData['remarks'] ?? null,
                'reason' => $validatedData['remarks'] ?? null,
                'total_refund_amount' => $totalRefundAmount,
                'total_refund_charge' => $totalRefundCharge,
                'total_nett_refund' => $totalNettRefund,
                'status' => 'processed',
                'refund_date' => $validatedData['date'],
                'created_by' => Auth::user()->id,
            ]);

            foreach ($validatedData['tasks'] as $taskData) {
                $task = Task::with('originalTask.invoiceDetail.invoice', 'client', 'agent.branch')->findOrFail($taskData['task_id']);
                $originalInvoice = $task->originalTask->invoiceDetail->invoice;
                $paymentStatus = $originalInvoice->status;

                $refundDetail = RefundDetail::create([
                    'refund_id' => $refund->id,
                    'task_id' => $task->id,
                    'invoice_id' => $originalInvoice->id,
                    'client_id' => $task->client_id,
                    'task_description' => $task->reference,
                    'original_invoice_price' => $taskData['refund_fee_to_client'],
                    'original_task_cost' => $taskData['supplier_charge'],
                    'original_task_profit' => $taskData['original_task_profit'],
                    'new_task_profit' => $taskData['new_task_profit'],
                    'total_refund_to_client' => $taskData['total_refund_to_client'],
                    'remarks' => $taskData['remarks'] ?? null,
                ]);

                if (in_array($paymentStatus, ['paid'])) {
                    $this->handlePaidRefund($refund, $refundDetail, $originalInvoice);
                } elseif ($paymentStatus === 'unpaid') {
                    $this->handleUnpaidInvoice($refund, $refundDetail);
                }
            }

            DB::commit();
            return redirect()->route('refunds.index')->with('success', 'Refund processed successfully!');
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Refund processing failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Refund failed: ' . $e->getMessage()]);
        }
    }

    public function storeForUnpaidInvoice(Request $request): RedirectResponse
    {
        $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'invoice_price' => ['required', 'numeric', 'min:0'],
            'original_invoice_price' => ['required', 'numeric', 'min:0'],
            'original_task_profit' => ['required', 'numeric'],
            'supplier_charge' => ['required', 'numeric'],
            'new_agent_markup' => ['required', 'numeric'],
            'refund_airline_charge' => ['nullable', 'numeric'],
            'tax_refund' => ['nullable', 'numeric'],
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
            'payment_gateway_option' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'numeric'],
            'service_charge' => ['required', 'numeric'],
        ]);

        $task = Task::findOrFail($request->input('task_id'));
        $origTask = $task->originalTask()->with(['agent', 'company', 'invoiceDetail.invoice'])->first();
        $invoice = $origTask->invoiceDetail->invoice;
        $invoicePaid = ($invoice->status === 'paid');

        if ($invoicePaid) {
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
                'total_nett_refund' => $refundInvoicePrice,
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

        if ($createInvoiceResponse instanceof JsonResponse && $createInvoiceResponse->getStatusCode() !== 200) {
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
            Log::error('Invoice Id not found in the response for refund with ID ' . $refund->id . ' and task ID ' . $task->id, ['response' => $createInvoiceResponse]);

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

    public function handlePaidRefund(Refund $refund, RefundDetail $detail, Invoice $invoice)
    {
        $task = Task::with('agent', 'client')->findOrFail($detail->task_id);
        $client = $task->client;
        $agent = $task->agent;
        $companyId = $task->company_id;
        $branchId = $agent->branch_id;
        $refundAmount = $detail->total_refund_to_client;

        // === Create Transaction ===
        $transaction = Transaction::create([
            'entity_id' => $companyId,
            'entity_type' => 'company',
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'transaction_type' => 'debit',
            'transaction_date' => $refund->refund_date,
            'amount' => $refundAmount,
            'description' => 'Refund to client for invoice ' . $invoice->invoice_number,
            'reference_type' => 'Refund',
            'reference_number' => $refund->refund_number,
            'name' => $client->full_name,
            'remarks_internal' => $refund->remarks,
        ]);

        // === Create Client Credit ===
        Credit::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'client_id' => $client->id,
            'type' => 'Refund',
            'description' => 'Refund for ' . $refund->refund_number,
            'amount' => $refundAmount,
            'topup_by' => Auth::user()->company ? 'Company' : (Auth::user()->branch ? 'Branch' : 'Company'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // === Handle Agent Commission ===
        if (in_array(strtolower($agent->agentType?->name), ['commission', 'type-a'], true)) {
            $agentCommission = $agent->commission;

            $directExpense = Account::where('name', 'LIKE', '%Direct Expenses%')
                ->where('company_id', $companyId)
                ->where('root_id', 5)
                ->first();

            $commissionExpense = Account::firstOrCreate([
                'name' => 'Commissions Expense (Agents)',
                'company_id' => $companyId,
                'root_id' => $directExpense->root_id,
            ], [
                'parent_id' => $directExpense->id,
                'branch_id' => $branchId,
                'account_type' => 'asset',
                'report_type' => 'balance sheet',
                'level' => $directExpense->level + 1,
                'is_group' => 0,
                'disabled' => 0,
                'currency' => 'KWD',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $accrued = Account::where('name', 'LIKE', '%Accrued Expenses%')
                ->where('company_id', $companyId)
                ->where('root_id', 2)
                ->first();

            $commissionLiability = Account::firstOrCreate([
                'name' => 'Commission (Agents)',
                'company_id' => $companyId,
                'root_id' => $accrued->root_id,
            ], [
                'parent_id' => $accrued->id,
                'branch_id' => $branchId,
                'account_type' => 'asset',
                'report_type' => 'balance sheet',
                'level' => $accrued->level + 1,
                'is_group' => 0,
                'disabled' => 0,
                'currency' => 'KWD',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $commissionValue = $detail->new_task_profit * $agentCommission;

            // Debit - Expense
            JournalEntry::create([
                'transaction_date' => $refund->refund_date,
                'transaction_id' => $transaction->id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'account_id' => $commissionExpense->id,
                'description' => 'Refund Commission - Agent gets ' . ($agentCommission * 100) . '% (Assets): ' . $commissionExpense->name,
                'debit' => $commissionValue,
                'credit' => 0,
                'voucher_number' => $refund->id,
                'name' => $commissionExpense->name,
                'type' => 'refund',
            ]);

            // Credit - Liability
            JournalEntry::create([
                'transaction_date' => $refund->refund_date,
                'transaction_id' => $transaction->id,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'account_id' => $commissionLiability->id,
                'description' => 'Refund Commission - Agent gets ' . $agentCommission * 100 . '% of refund fee (Liabilities): ' . $commissionLiability->name . '',
                'debit' => 0,
                'credit' => $commissionValue,
                'voucher_number' => $refund->id,
                'name' => $commissionLiability->name,
                'type' => 'refund',
            ]);
        }

        // === Client Journal Entry (Refund Payable) ===
        $liabilities = Account::where('name', 'Liabilities')
            ->where('company_id', $companyId)
            ->first();

        $refundPayable = Account::where('name', 'LIKE', '%Refund Payable%')
            ->where('company_id', $companyId)
            ->where('parent_id', $liabilities->id)
            ->first();

        $clientRefund = Account::firstOrCreate([
            'name' => 'Clients',
            'company_id' => $companyId,
            'parent_id' => $refundPayable->id,
            'root_id' => $liabilities->id,
        ], [
            'branch_id' => $branchId,
            'account_type' => 'asset',
            'report_type' => 'balance sheet',
            'level' => $refundPayable->level + 1,
            'is_group' => 0,
            'disabled' => 0,
            'currency' => 'KWD',
        ]);

        JournalEntry::create([
            'transaction_date' => $refund->refund_date,
            'currency' => 'KWD',
            'exchange_rate' => 1.0,
            'amount' => $refundAmount,
            'name' => $client->full_name,
            'description' => 'Refund to Client - ' . $client->full_name,
            'type' => 'refund',
            'debit' => $refundAmount,
            'credit' => 0,
            'balance' => $refundAmount,
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'account_id' => $clientRefund->id,
            'branch_id' => $branchId,
            'original_currency' => 'KWD',
            'original_amount' => $refundAmount,
        ]);
    }

    public function handleUnpaidInvoice(Refund $refund, RefundDetail $detail)
    {
        $task = Task::with(['client', 'agent'])->findOrFail($detail->task_id);
        $client = $task->client;
        $companyId = $task->company_id;
        $branchId = $task->agent->branch_id;
        $chargeAmount = abs($detail->total_refund_to_client);
        $originalInvoiceId = $detail->invoice_id;

        // ✅ STEP 1: Find existing unpaid refund-charge invoice for this same original invoice
        $existingInvoice = Invoice::where('label', 'refund')
            ->where('client_id', $client->id)
            ->where('status', 'unpaid')
            ->whereHas('refundDetail', function ($q) use ($originalInvoiceId) {
                $q->where('invoice_id', $originalInvoiceId);
            })
            ->first();

        if ($existingInvoice) {
            $existingInvoice->increment('amount', $chargeAmount);
            $existingInvoice->increment('sub_amount', $chargeAmount);

            InvoiceDetail::create([
                'invoice_id' => $existingInvoice->id,
                'invoice_number' => $existingInvoice->invoice_number,
                'task_id' => $task->id,
                'task_description' => 'Refund charge for Task ' . ($task->reference ?? ''),
                'task_price' => $chargeAmount,
                'supplier_price' => 0,
                'markup_price' => 0,
                'created_by' => Auth::id(),
            ]);

            $detail->update(['refund_invoice_id' => $existingInvoice->id]);
            return;
        }

        // ✅ STEP 2: Create NEW invoice (fully matches createInvoiceFromRefund)
        $invoiceSequence = InvoiceSequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
        $invoiceNumber = app(InvoiceController::class)->generateInvoiceNumber($invoiceSequence->current_sequence);
        $invoiceSequence->increment('current_sequence');

        $gateway = request('payment_gateway_option');
        $methodId = request('payment_method');
        $serviceCharge = request('service_charge') ?? 0;

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'client_id' => $client->id,
            'agent_id' => $task->agent_id,
            'currency' => $task->exchange_currency ?? 'KWD',
            'sub_amount' => $chargeAmount,
            'invoice_charge' => 0,
            'amount' => $chargeAmount,
            'status' => 'unpaid',
            'invoice_date' => $refund->refund_date,
            'due_date' => Carbon::parse($refund->refund_date)->addDays(5)->toDateString(),
            'label' => 'refund',
            'payment_type' => 'full',
        ]);

        // ✅ STEP 3: Create Invoice Detail
        $invoiceDetail = InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoiceNumber,
            'task_id' => $task->id,
            'task_description' => 'Refund charge for Task ' . ($task->reference ?? ''),
            'task_price' => $chargeAmount,
            'supplier_price' => 0,
            'markup_price' => 0,
            'created_by' => Auth::id(),
        ]);

        // ✅ STEP 4: Create Invoice Partial (for gateway + method)
        $chargeId = \App\Models\Charge::where('name', $gateway)->value('id');
        $invoicePartial = InvoicePartial::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoiceNumber,
            'client_id' => $client->id,
            'service_charge' => $serviceCharge,
            'amount' => $chargeAmount,
            'status' => 'unpaid',
            'expiry_date' => Carbon::parse($refund->refund_date)->addDays(5)->toDateString(),
            'type' => 'full',
            'payment_gateway' => $gateway,
            'payment_method' => $methodId,
            'charge_id' => $chargeId,
        ]);

        // ✅ STEP 5: Create Transaction
        $transaction = Transaction::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'entity_id' => $companyId,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $chargeAmount,
            'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated (Refund Charge)',
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

        $detail->update(['refund_invoice_id' => $invoice->id]);
    }

    public function show($companyId, $refundNumber)
    {
        $refund = Refund::with([
            'company',
            'refundDetails.task.originalTask.invoiceDetail.invoice',
            'refundDetails.client',
            'refundDetails.invoice'
        ])
            ->where('company_id', $companyId)
            ->where('refund_number', $refundNumber)
            ->firstOrFail();

        $refundDetails = $refund->refundDetails;
        $company = $refund->company;

        $groupedByClient = $refundDetails->groupBy('client_id');
        $groupedByInvoice = $refundDetails->groupBy('invoice_id');

        return view('refunds.show', compact('refund', 'refundDetails', 'company', 'groupedByClient', 'groupedByInvoice'));
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
                foreach ($paymentMethods as $method) {
                    if ($method->company_id == $task->agent->branch->company_id && $method->type == 'myfatoorah') {
                        try {
                            $method->gateway_fee = ChargeService::FatoorahCharge($refund->total_nett_refund, $method->id, $task->agent->branch->company_id)['fee'] ?? 0;
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
                            $method->gateway_fee = ChargeService::UPaymentCharge($refund->total_nett_refund, $method->id, $task->agent->branch->company_id)['fee'] ?? 0;
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
                            $method->gateway_fee = ChargeService::HesabeCharge($refund->total_nett_refund, $method->id, $task->agent->branch->company_id)['fee'] ?? 0;
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
                    'amount' => $refund->total_nett_refund,
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

    public function complete_process(Refund $refund)
    {
        $refundDetails = $refund->refundDetails()->with('task.agent')->get();

        if ($refundDetails->isEmpty()) {
            return back()->with('error', 'No tasks linked to this refund.');
        }

        try {
            foreach ($refundDetails as $detail) {
                $taskRec = $detail->task;
                if (!$taskRec) continue;

                $transaction = Transaction::create([
                    'entity_id' => $taskRec->company_id,
                    'entity_type' => 'company',
                    'company_id' => $taskRec->company_id,
                    'branch_id' => $taskRec->agent->branch_id,
                    'transaction_type' => 'debit',
                    'amount' => $refund->new_task_profit,
                    'description' => 'Adjusted Profit - Refund (' . $refund->refund_number . ')',
                    'reference_type' => 'Refund',
                    'reference_number' => $refund->refund_number,
                    'name' => $taskRec->client_name,
                    'remarks_internal' => $refund->remarks_internal,
                    'transaction_date' => $refund->date,
                ]);

                $incomeIndirectIncome = Account::where('name', 'LIKE', '%Expenses%')->first();
                $accountSupplierRefundIncome = 'Refund Clearing / Payable Allocation';

                $supplierRefundIncome = Account::firstOrCreate(
                    [
                        'name' => $accountSupplierRefundIncome,
                        'company_id' => $taskRec->company_id,
                        'root_id' => 5,
                    ],
                    [
                        'parent_id' => $incomeIndirectIncome->id,
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
                    ]
                );

                $incomeIndirectLiability = Account::where('name', 'LIKE', '%Refund Payable%')
                    ->where('company_id', $taskRec->company_id)
                    ->where('root_id', 2)
                    ->first();

                $accountSupplierRefundLiability = 'Clients';

                $supplierRefundLiability = Account::firstOrCreate(
                    [
                        'name' => 'Clients',
                        'company_id' => $taskRec->company_id,
                        'root_id' => $incomeIndirectLiability->root_id,
                    ],
                    [
                        'parent_id' => $incomeIndirectLiability->id,
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
                    ]
                );

                JournalEntry::create([
                    'transaction_date' => $refund->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $taskRec->company_id,
                    'branch_id' => $taskRec->agent->branch_id,
                    'account_id' => $supplierRefundIncome->id,
                    'description' => $refund->refund_number . ' - ' . $supplierRefundIncome->name . '',
                    'debit' => $refund->new_task_profit,
                    'credit' => 0,
                    'voucher_number' => $refund->id,
                    'name' => $supplierRefundIncome->name,
                    'type' => 'refund',
                ]);

                JournalEntry::create([
                    'transaction_date' => $refund->date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $taskRec->company_id,
                    'branch_id' => $taskRec->agent->branch_id,
                    'account_id' => $supplierRefundLiability->id,
                    'description' => $refund->refund_number . ' - ' . $supplierRefundLiability->name . '',
                    'debit' => 0,
                    'credit' => $refund->new_task_profit,
                    'voucher_number' => $refund->id,
                    'name' => $supplierRefundLiability->name,
                    'type' => 'refund',
                ]);

                Credit::create([
                    'company_id'  => $taskRec->company_id,
                    'client_id'   => $taskRec->client_id,
                    'task_id'   => $taskRec->id,
                    'type'        => 'Refund',
                    'description' => $refund->refund_number . ': Refund for ' . $supplierRefundLiability->name,
                    'amount'      => $refund->new_task_profit,
                ]);
            }

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
    ): JsonResponse {
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
