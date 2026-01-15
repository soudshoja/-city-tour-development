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
use App\Models\InvoiceSequence;
use App\Models\RefundClient;
use App\Services\ChargeService;
use App\Enums\InvoiceStatus;
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
            $refunds = Refund::with(['refundDetails.task.client', 'refundDetails.task.agent', 'originalInvoice', 'invoice'])
                ->where('company_id', Auth::user()->company->id)
                ->orderBy('id', 'desc')
                ->get();
        } elseif (Auth::user()->role->id == Role::BRANCH) {
            $refundClients = $user->branch->agents->refundClients;
            $refunds = Refund::with(['refundDetails.task.client', 'refundDetails.task.agent', 'originalInvoice', 'invoice'])
                ->where('branch_id', Auth::user()->branch->id)
                ->orderBy('id', 'desc')
                ->get();
        } elseif (Auth::user()->role->id == Role::AGENT) {
            $refundClients = $user->agent->refundClients;
            $refunds = Refund::with(['refundDetails.task.client', 'refundDetails.task.agent', 'originalInvoice', 'invoice'])
                ->where('agent_id', $user->agent->id)
                ->orderBy('id', 'desc')
                ->get();
        } elseif (Auth::user()->role->id == Role::ACCOUNTANT) {
            $refundClients = $user->accountant->branch->agents->pluck('refundClients')->flatten();
            $refunds = Refund::with(['refundDetails.task.client', 'refundDetails.task.agent', 'originalInvoice', 'invoice'])
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

        $refundedTaskIds = RefundDetail::whereIn('task_id', $tasks->pluck('id'))->pluck('task_id')->unique();
        if ($refundedTaskIds->isNotEmpty()) {
            $refundedTaskRefs = $tasks->whereIn('id', $refundedTaskIds)->pluck('reference')->join(', ');
            return redirect()->back()->withErrors([
                'error' => "Refund already exists for task(s): {$refundedTaskRefs}. You cannot create a new refund for these tasks."
            ]);
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
            if (!in_array($invoicePaymentStatus, ['paid', 'unpaid', 'partial', 'partial refund'])) {
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

        $refundSequence = RefundSequence::firstOrCreate(['company_id' => $tasks->first()->agent->branch->company_id], ['current_sequence' => 1]);
        $refundNumber = $this->generateRefundNumber($refundSequence->current_sequence);

        return view('refunds.create-multi', [
            'refundNumber' => $refundNumber,
            'tasks' => $tasks,
            'uniqueClients' => $uniqueClients,
            'paymentGateways' => $paymentGateways,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'date' => ['required', 'date'],
            'method' => ['nullable', 'in:Bank,Cash,Online,Credit'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'remarks' => ['nullable', 'string'],
            'remarks_internal' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
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
                'invoice_id' => $firstTask->originalTask->invoiceDetail->invoice->id,
                'method' => $validatedData['method'] ?? null,
                'remarks' => $validatedData['remarks'],
                'remarks_internal' => $validatedData['remarks_internal'],
                'reason' => $validatedData['reason'],
                'total_refund_amount' => $totalRefundAmount,
                'total_refund_charge' => $totalRefundCharge,
                'total_nett_refund' => $totalNettRefund,
                'status' => 'processed',
                'refund_date' => $validatedData['date'],
                'created_by' => Auth::user()->id,
            ]);

            foreach ($validatedData['tasks'] as $taskData) {
                $task = Task::with('originalTask.invoiceDetail.invoice', 'client', 'agent.branch')->findOrFail($taskData['task_id']);
                $paymentStatus = $task->originalTask->invoiceDetail->invoice->status;

                RefundDetail::create([
                    'refund_id' => $refund->id,
                    'task_id' => $task->id,
                    'client_id' => $task->client_id,
                    'task_description' => $task->reference,
                    'original_invoice_price' => $taskData['original_invoice_price'],
                    'original_task_cost' => $taskData['original_task_cost'],
                    'original_task_profit' => $taskData['original_task_profit'],
                    'refund_fee_to_client' => $taskData['refund_fee_to_client'],
                    'supplier_charge' => $taskData['supplier_charge'],
                    'new_task_profit' => $taskData['new_task_profit'],
                    'total_refund_to_client' => $taskData['total_refund_to_client'],
                    'remarks' => $taskData['remarks'] ?? null,
                ]);
            }

            if (in_array($paymentStatus, ['paid', 'partial refund'])) {
                $this->handlePaidRefund($refund);
            } elseif ($paymentStatus === 'unpaid') {
                $invoice = $refund->originalInvoice;

                $refundedTaskIds = $refund->refundDetails
                    ->map(fn($d) => $d->task?->originalTask?->id ?? $d->task_id)
                    ->filter()
                    ->toArray();

                $remainingTaskTotal = $invoice->invoiceDetails()
                    ->when(!empty($refundedTaskIds), fn($q) => $q->whereNotIn('task_id', $refundedTaskIds))
                    ->sum('task_price');

                $totalToCollect = $refund->total_nett_refund + $remainingTaskTotal;

                Log::info("Unpaid refund invoice calculation", [
                    'refund_id' => $refund->id,
                    'refund_charge' => $refund->total_nett_refund,
                    'remaining_unrefunded_tasks' => $remainingTaskTotal,
                    'total_to_collect' => $totalToCollect,
                ]);
                $this->handleUnpaidInvoice($refund, $request, $totalToCollect);
            } elseif ($paymentStatus === 'partial') {
                $refund->unsetRelation('refundDetails');
                $refund->load(['refundDetails.task.agent', 'refundDetails.task.client', 'originalInvoice.invoicePartials', 'originalInvoice.invoiceDetails']);
                Log::info("Refund {$refund->refund_number} reloaded refundDetails with tasks: " . $refund->refundDetails->pluck('task_id')->join(', '));
                $this->handlePartialRefund($refund);
            }

            DB::commit();
            return redirect()->route('refunds.index')->with('success', 'Refund processed successfully!');
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Refund processing failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Refund failed: ' . $e->getMessage()]);
        }
    }

    public function handlePaidRefund(Refund $refund, ?float $overrideAmount = null)
    {
        $firstDetail = $refund->refundDetails()->with('task.agent', 'task.client')->firstOrFail();
        $task = $firstDetail->task;
        $agent = $task->agent;
        $refundAmount = $overrideAmount ?? $refund->total_nett_refund;

        $transaction = Transaction::create([
            'entity_id' => $task->company_id,
            'entity_type' => 'company',
            'company_id' => $task->company_id,
            'branch_id' => $task->agent->branch_id,
            'transaction_type' => 'debit',
            'transaction_date' => $refund->refund_date,
            'amount' => $refundAmount,
            'description' => 'Refund Recorded: ' . $refund->refund_number,
            'reference_type' => 'Refund',
            'reference_number' => $refund->refund_number,
            'name' => $task->client->full_name,
            'remarks_internal' => $refund->remarks,
        ]);

        if (in_array(strtolower($agent->agentType?->name), ['commission', 'type-a'], true)) {
            $agentCommission = $agent->commission;
            $totalNewProfit = $refund->refundDetails->sum('new_task_profit');
            $commissionValue = $totalNewProfit * $agentCommission;

            $directExpense = Account::where('name', 'LIKE', '%Direct Expenses%')
                ->where('company_id', $task->company_id)
                ->where('root_id', 5)
                ->first();

            $commissionExpense = Account::firstOrCreate([
                'name' => 'Commissions Expense (Agents)',
                'company_id' => $task->company_id,
                'root_id' => $directExpense->root_id,
            ], [
                'parent_id' => $directExpense->id,
                'branch_id' => $task->agent->branch_id,
                'account_type' => 'asset',
                'report_type' => 'balance sheet',
                'level' => $directExpense->level + 1,
                'is_group' => 0,
                'disabled' => 0,
                'actual_balance' => 0.00,
                'budget_balance' => 0.00,
                'variance' => 0.00,
                'currency' => 'KWD',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $accrued = Account::where('name', 'LIKE', '%Accrued Expenses%')
                ->where('company_id', $task->company_id)
                ->where('root_id', 2)
                ->first();

            $commissionLiability = Account::firstOrCreate([
                'name' => 'Commission (Agents)',
                'company_id' => $task->company_id,
                'root_id' => $accrued->root_id,
            ], [
                'parent_id' => $accrued->id,
                'branch_id' => $task->agent->branch_id,
                'account_type' => 'asset',
                'report_type' => 'balance sheet',
                'level' => $accrued->level + 1,
                'is_group' => 0,
                'disabled' => 0,
                'actual_balance' => 0.00,
                'budget_balance' => 0.00,
                'variance' => 0.00,
                'currency' => 'KWD',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            JournalEntry::create([
                'transaction_date' => $refund->refund_date,
                'transaction_id' => $transaction->id,
                'company_id' => $task->company_id,
                'branch_id' => $task->agent->branch_id,
                'account_id' => $commissionExpense->id,
                'description' => 'Refund Commission - Agent gets ' . ($agentCommission * 100) . '% of refund fee (Assets): ' . $commissionExpense->name,
                'debit' => $commissionValue,
                'credit' => 0,
                'voucher_number' => $refund->id,
                'name' => $commissionExpense->name,
                'type' => 'refund',
            ]);

            JournalEntry::create([
                'transaction_date' => $refund->refund_date,
                'transaction_id' => $transaction->id,
                'company_id' => $task->company_id,
                'branch_id' => $task->agent->branch_id,
                'account_id' => $commissionLiability->id,
                'description' => 'Refund Commission - Agent gets ' . $agentCommission * 100 . '% of refund fee (Liabilities): ' . $commissionLiability->name,
                'debit' => 0,
                'credit' => $commissionValue,
                'voucher_number' => $refund->id,
                'name' => $commissionLiability->name,
                'type' => 'refund',
            ]);
        }

        $liabilities = Account::where('name', 'Liabilities')
            ->where('company_id', $task->company_id)
            ->first();

        $refundPayable = Account::where('name', 'LIKE', '%Refund Payable%')
            ->where('company_id', $task->company_id)
            ->where('parent_id', $liabilities->id)
            ->first();

        $clientRefund = Account::firstOrCreate([
            'name' => 'Clients',
            'company_id' => $task->company_id,
            'parent_id' => $refundPayable->id,
            'root_id' => $liabilities->id,
        ], [
            'branch_id' => $task->agent->branch_id,
            'account_type' => 'asset',
            'report_type' => 'balance sheet',
            'level' => $refundPayable->level + 1,
            'is_group' => 0,
            'disabled' => 0,
            'actual_balance' => 0.00,
            'budget_balance' => 0.00,
            'variance' => 0.00,
            'currency' => 'KWD',
        ]);

        JournalEntry::create([
            'transaction_date' => $refund->refund_date,
            'currency' => 'KWD',
            'exchange_rate' => 1.0,
            'amount' => $refundAmount,
            'name' => $task->client->full_name,
            'description' => 'Refund to Client - ' . $task->client->full_name,
            'type' => 'refund',
            'debit' => $refundAmount,
            'credit' => 0,
            'balance' => $refundAmount,
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'account_id' => $clientRefund->id,
            'branch_id' => $task->agent->branch_id,
            'original_currency' => 'KWD',
            'original_amount' => $refundAmount,
        ]);

        Credit::create([
            'company_id' => $task->company_id,
            'branch_id' => $task->agent->branch_id,
            'client_id' => $task->client->id,
            'refund_id' => $refund->id,
            'type' => 'Refund',
            'description' => 'Refund for ' . $refund->refund_number,
            'amount' => $refundAmount,
            'topup_by' => Auth::user()->company ? 'Company' : (Auth::user()->branch ? 'Branch' : 'Agent'),
        ]);

        $invoice = $refund->originalInvoice;
        if ($invoice) {
            $allInvoiceTaskIds = $invoice->invoiceDetails()->pluck('task_id')->toArray();

            $refundedOriginalTaskIds = RefundDetail::whereHas('refund', function ($q) use ($invoice) {
                $q->where('invoice_id', $invoice->id);
            })
                ->get()
                ->map(fn($detail) => $detail->task?->originalTask?->id ?? $detail->task_id)
                ->filter()
                ->unique()
                ->toArray();

            $allTasksRefunded = count(array_intersect($allInvoiceTaskIds, $refundedOriginalTaskIds)) >= count($allInvoiceTaskIds);

            if ($allTasksRefunded) {
                $invoice->update(['status' => InvoiceStatus::REFUNDED->value]);
                Log::info("Invoice {$invoice->invoice_number} marked as REFUNDED (all tasks refunded)");
            } else {
                $invoice->update(['status' => InvoiceStatus::PARTIAL_REFUND->value]);
                $refundedCount = count($refundedOriginalTaskIds);
                $totalTasks = count($allInvoiceTaskIds);
                Log::info("Invoice {$invoice->invoice_number} marked as PARTIAL_REFUND ({$refundedCount}/{$totalTasks} tasks refunded)");
            }
        }

        $refund->update(['status' => 'completed']);
        Log::info("Refund {$refund->refund_number} marked as completed (paid invoice auto-credited)");
    }

    public function handleUnpaidInvoice(Refund $refund, Request $request, ?float $overrideAmount = null)
    {
        $refund->load(['refundDetails.task.agent', 'refundDetails.task.client', 'originalInvoice', 'invoice']);
        $refundCharge = $overrideAmount ?? $refund->total_nett_refund;

        $createInvoiceResponse = $this->createRefundInvoiceUnpaid(
            $refund,
            $refundCharge,
            $request->input('payment_gateway_option'),
            $request->input('payment_method'),
        );

        $data = $createInvoiceResponse->getData(true);
        $refund->update(['refund_invoice_id' => $data['invoiceId'] ?? null]);
    }

    private function handlePartialRefund(Refund $refund)
    {
        Log::info("Refund {$refund->refund_number} refundDetails tasks loaded: " . $refund->refundDetails->pluck('task_id')->join(', '));

        $invoice = $refund->originalInvoice;
        $totalPaid = $invoice->invoicePartials()->where('status', 'paid')->sum('amount');
        $refundCharge = $refund->total_nett_refund;

        $refundedTaskIds = $refund->refundDetails->map(fn($detail) => $detail->task?->originalTask?->id ?? $detail->task_id)->filter()->toArray();
        Log::info("Refund {$refund->refund_number} refundDetails original task IDs: " . implode(', ', $refundedTaskIds));
        if (empty($refundedTaskIds)) {
            Log::warning("Refund {$refund->refund_number} has no valid refundDetails task_id; assuming no tasks refunded yet.");
        }

        $remainingTaskTotal = $invoice->invoiceDetails()
            ->when(!empty($refundedTaskIds), fn($q) => $q->whereNotIn('task_id', $refundedTaskIds))
            ->sum('task_price');

        Log::info("Partial refund for {$invoice->invoice_number}: Paid={$totalPaid}, Charge={$refundCharge}, RemainingTasks={$remainingTaskTotal}");

        // Case 1 — Paid < Refund Charge
        if ($totalPaid < $refundCharge) {
            $this->handleUnpaidInvoice($refund, request());
            Log::info("Refund {$refund->refund_number} handled as unpaid (collect balance).");
            return;
        }

        // Case 2 — Paid > Refund Charge
        if ($totalPaid > $refundCharge) {
            $availableAfterRefund = $totalPaid - $refundCharge;

            // Sub-case A: still need to collect because unpaid tasks > available balance
            if ($availableAfterRefund < $remainingTaskTotal) {
                $amountOwed = $remainingTaskTotal - $availableAfterRefund;

                $this->handleRefundCOA($refund, $amountOwed, $refundCharge);
                $this->createRefundInvoicePartial(
                    $refund,
                    $amountOwed,
                    request()->input('payment_gateway_option'),
                    request()->input('payment_method'),
                );

                Log::info("Refund {$refund->refund_number} requires collection of {$amountOwed}. Paid-RefundCharge={$availableAfterRefund} < RemainingTasks={$remainingTaskTotal}");
                return;
            }

            // Sub-case B: have excess — credit to client
            $creditAmount = $availableAfterRefund - $remainingTaskTotal;

            $invoice->update(['status' => InvoiceStatus::REFUNDED->value]);
            $this->handlePaidRefund($refund, $creditAmount);

            Log::info("Refund {$refund->refund_number} credited {$creditAmount} to client after refunding invoice.");
            return;
        }

        // Case 4 — Equal
        $refund->update(['status' => 'completed']);
        Log::info("Refund {$refund->refund_number} completed, balanced perfectly.");
    }

    private function handleRefundCOA(Refund $refund, float $amountOwed, float $refundCharge)
    {
        $refund->load(['refundDetails.task.agent', 'refundDetails.task.client', 'originalInvoice', 'invoice']);
        $firstTask = $refund->refundDetails->first()->task;
        $companyId = $firstTask->company_id;
        $branchId = $firstTask->agent->branch_id;

        $transaction = Transaction::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'entity_id' => $companyId,
            'entity_type' => 'company',
            'transaction_type' => 'refund',
            'amount' => $refundCharge,
            'description' => 'Refund COA adjustment for refund ' . $refund->refund_number,
            'reference_type' => 'Refund',
            'reference_number' => $refund->refund_number,
            'transaction_date' => $refund->refund_date,
            'name' => $firstTask->client->full_name,
            'remarks_internal' => $refund->remarks,
        ]);

        $originalTotal = $refund->refundDetails
            ->filter(fn($d) => $d->task?->originalTask?->invoiceDetail)
            ->sum(fn($d) => $d->task->originalTask->invoiceDetail->task_price ?? 0);

        Log::info("Refund {$refund->refund_number} COA OriginalTotal={$originalTotal}, RefundCharge={$refundCharge}, AmountOwed={$amountOwed}");

        $bookingAccountName = ucfirst($firstTask->type) . ' Booking Revenue';
        $bookingRevenueAccount = Account::where('name', 'like', '%' . $bookingAccountName . '%')
            ->where('company_id', $companyId)
            ->first();

        if (!$bookingRevenueAccount) {
            $directIncomeParent = Account::firstOrCreate(
                ['name' => 'Direct Income', 'company_id' => $companyId],
                [
                    'root_id' => 4,
                    'account_type' => 'income',
                    'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
                    'level' => 2,
                    'is_group' => 1,
                    'currency' => 'KWD',
                ]
            );

            $lastRevenue = Account::where('parent_id', $directIncomeParent->id)
                ->where('company_id', $companyId)
                ->orderByDesc('code')
                ->first();

            $nextCode = (int)($lastRevenue?->code ?? 4110) + 5;

            $bookingRevenueAccount = Account::create([
                'code' => str_pad($nextCode, 4, '0', STR_PAD_LEFT),
                'name' => $bookingAccountName,
                'company_id' => $companyId,
                'root_id' => $directIncomeParent->root_id,
                'parent_id' => $directIncomeParent->id,
                'branch_id' => $branchId,
                'account_type' => 'income',
                'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
                'level' => $directIncomeParent->level + 1,
                'currency' => 'KWD',
            ]);
        }

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'account_id' => $bookingRevenueAccount->id,
            'transaction_date' => $refund->refund_date,
            'description' => "Invoice refund for (Income): " . $refund->refundDetails->first()->invoice,
            'debit' => $originalTotal,
            'credit' => 0,
            'name' => $bookingRevenueAccount->name,
            'type' => 'refund',
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'account_id' => $bookingRevenueAccount->id,
            'transaction_date' => $refund->refund_date,
            'description' => "Invoice refund for (Income): " . $refund->refundDetails->first()->invoice,
            'debit' => 0,
            'credit' => $refundCharge,
            'name' => $bookingRevenueAccount->name,
            'type' => 'refund',
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'account_id' => $bookingRevenueAccount->id,
            'transaction_date' => $refund->refund_date,
            'description' => "Invoice refund for (Income): " . $refund->refundDetails->first()->invoice,
            'debit' => $amountOwed,
            'credit' => 0,
            'name' => $bookingRevenueAccount->name,
            'type' => 'refund',
        ]);

        $accountReceivable = Account::where('name', 'Accounts Receivable')
            ->where('company_id', $companyId)
            ->first();

        $clientAccount = Account::where('name', 'Clients')
            ->where('company_id', $companyId)
            ->where('parent_id', $accountReceivable->id)
            ->first();

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'account_id' => $clientAccount->id,
            'transaction_date' => $refund->refund_date,
            'description' => "Invoice refund for (Assets): " . $refund->refundDetails->first()->invoice,
            'debit' => 0,
            'credit' => $originalTotal,
            'name' => $clientAccount->name,
            'type' => 'refund',
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'account_id' => $clientAccount->id,
            'transaction_date' => $refund->refund_date,
            'description' => "Invoice refund for (Assets): " . $refund->refundDetails->first()->invoice,
            'debit' => $refundCharge,
            'credit' => 0,
            'name' => $clientAccount->name,
            'type' => 'refund',
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'account_id' => $clientAccount->id,
            'transaction_date' => $refund->refund_date,
            'description' => "Invoice refund for (Assets): " . $refund->refundDetails->first()->invoice,
            'debit' => 0,
            'credit' => $amountOwed,
            'name' => $clientAccount->name,
            'type' => 'refund',
        ]);

        Log::info("Refund {$refund->refund_number} COA journal entries created successfully for company {$companyId}.");
    }

    public function show($companyId, $refundNumber)
    {
        $refund = Refund::where('refund_number', $refundNumber)
            ->where('company_id', $companyId)
            ->with([
                'refundDetails.task',
                'originalInvoice.client',
                'company',
                'agent',
                'branch',
            ])
            ->firstOrFail();

        return view('refunds.show', compact('refund'));
    }

    public function edit(Refund $refund)
    {
        $refund->load([
            'refundDetails.task.originalTask.invoiceDetail.invoice',
            'refundDetails.task.agent.branch.company',
            'refundDetails.task.client',
        ]);

        if ($refund->refundDetails->isEmpty()) {
            return back()->withErrors(['error' => 'No refund details found for this refund.']);
        }

        $firstTask = $refund->refundDetails->first()->task;
        $invoicePaid = in_array($firstTask->originalTask->invoiceDetail->invoice->status ?? '', ['paid', 'partial refund']);
        $invoiceDetail = $firstTask->originalTask->invoiceDetail;

        $paymentGateways = Charge::where('company_id', $firstTask->agent->branch->company_id)
            ->where('is_active', true)->get();

        $paymentMethods = PaymentMethod::where('company_id', $firstTask->agent->branch->company_id)
            ->where('is_active', true)->get();

        return view('refunds.edit', [
            'refund' => $refund,
            'invoicePaid' => $invoicePaid,
            'invoiceDetail' => $invoiceDetail,
            'paymentGateways' => $paymentGateways,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function update(Request $request, Task $task, Refund $refund)
    {
        if ($refund->status === 'completed') {
            return back()->withErrors(['error' => 'This refund has already been completed and cannot be modified.']);
        }

        $validatedData = $request->validate([
            'date' => ['required', 'date'],
            'method' => ['nullable', 'in:Bank,Cash,Online,Credit'],
            'remarks' => ['nullable', 'string'],
            'remarks_internal' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
            'payment_gateway_option' => ['nullable', 'string'],
            'payment_method' => ['nullable', 'numeric'],
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
        ]);

        DB::beginTransaction();
        try {
            $this->cleanupExistingRefundRecords($refund);

            $totalRefundAmount = collect($validatedData['tasks'])->sum('refund_fee_to_client');
            $totalRefundCharge = collect($validatedData['tasks'])->sum('supplier_charge');
            $totalNettRefund = collect($validatedData['tasks'])->sum('total_refund_to_client');

            $refund->update([
                'refund_date' => $validatedData['date'],
                'method' => $validatedData['method'] ?? null,
                'remarks' => $validatedData['remarks'] ?? null,
                'remarks_internal' => $validatedData['remarks_internal'] ?? null,
                'reason' => $validatedData['reason'] ?? null,
                'payment_gateway' => $validatedData['payment_gateway_option'] ?? null,
                'payment_method' => $validatedData['payment_method'] ?? null,
                'total_refund_amount' => $totalRefundAmount,
                'total_refund_charge' => $totalRefundCharge,
                'total_nett_refund' => $totalNettRefund,
            ]);

            foreach ($validatedData['tasks'] as $taskData) {
                $task = Task::findOrFail($taskData['task_id']);

                $detail = RefundDetail::where('refund_id', $refund->id)
                    ->where('task_id', $task->id)
                    ->first();

                if ($detail) {
                    $detail->update([
                        'client_id' => $task->client_id,
                        'task_description' => $task->reference,
                        'original_invoice_price' => $taskData['original_invoice_price'],
                        'original_task_cost' => $taskData['original_task_cost'],
                        'original_task_profit' => $taskData['original_task_profit'],
                        'refund_fee_to_client' => $taskData['refund_fee_to_client'],
                        'supplier_charge' => $taskData['supplier_charge'],
                        'new_task_profit' => $taskData['new_task_profit'],
                        'total_refund_to_client' => $taskData['total_refund_to_client'],
                        'remarks' => $taskData['remarks'] ?? null,
                    ]);
                } else {
                    RefundDetail::create([
                        'refund_id' => $refund->id,
                        'task_id' => $task->id,
                        'client_id' => $task->client_id,
                        'task_description' => $task->reference,
                        'original_invoice_price' => $taskData['original_invoice_price'],
                        'original_task_cost' => $taskData['original_task_cost'],
                        'original_task_profit' => $taskData['original_task_profit'],
                        'refund_fee_to_client' => $taskData['refund_fee_to_client'],
                        'supplier_charge' => $taskData['supplier_charge'],
                        'new_task_profit' => $taskData['new_task_profit'],
                        'total_refund_to_client' => $taskData['total_refund_to_client'],
                        'remarks' => $taskData['remarks'] ?? null,
                    ]);
                }
            }

            $invoice = $refund->originalInvoice;
            $paymentStatus = strtolower($invoice?->status ?? 'unpaid');

            if ($paymentStatus === 'paid') {
                $this->handlePaidRefund($refund);
            } elseif ($paymentStatus === 'unpaid') {
                $refundedTaskIds = $refund->refundDetails
                    ->map(fn($d) => $d->task?->originalTask?->id ?? $d->task_id)
                    ->filter()
                    ->toArray();

                $remainingTaskTotal = $invoice?->invoiceDetails()
                    ->when(!empty($refundedTaskIds), fn($q) => $q->whereNotIn('task_id', $refundedTaskIds))
                    ->sum('task_price');

                $totalToCollect = $refund->total_nett_refund + $remainingTaskTotal;
                $this->handleUnpaidInvoice($refund, $request, $totalToCollect);
            } elseif ($paymentStatus === 'partial') {
                $this->handlePartialRefund($refund);
            }

            DB::commit();
            return redirect()->route('refunds.edit', [$refund->id])->with('success', 'Refund updated successfully.');
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error("Refund update failed: " . $e->getMessage());
            return back()->withErrors(['error' => 'Refund update failed: ' . $e->getMessage()]);
        }
    }

    private function cleanupExistingRefundRecords(Refund $refund)
    {
        Transaction::where('reference_type', 'Refund')
            ->where('reference_number', $refund->refund_number)
            ->each(function ($transaction) {
                JournalEntry::where('transaction_id', $transaction->id)->delete();
                $transaction->delete();
            });

        Log::info("Cleaned up old transactions and journal entries for refund {$refund->refund_number}");
    }

    public function completeProcess(Refund $refund)
    {
        Log::info('Hit completeProcess()', ['refund_id' => $refund->id]);

        $refundDetails = $refund->refundDetails()->with('task.agent', 'refund')->get();
        Log::info('Fetched refund details', [
            'count' => $refundDetails->count(),
            'refund_details' => $refundDetails
        ]);

        if ($refundDetails->isEmpty()) {
            Log::warning('Refund has no linked tasks', ['refund_id' => $refund->id]);
            return back()->with('error', 'No tasks linked to this refund.');
        }

        try {
            foreach ($refundDetails as $detail) {
                Log::info('Processing refund detail', ['detail_id' => $detail->id]);

                $taskRec = $detail->task;
                if (!$taskRec) {
                    Log::warning('Task not found for refund detail', ['detail_id' => $detail->id]);
                    continue;
                }

                Log::info('Task found', [
                    'task_id' => $taskRec->id,
                    'company_id' => $taskRec->company_id,
                    'agent_id' => $taskRec->agent->id ?? null,
                    'all_data' => $detail
                ]);

                $transaction = Transaction::create([
                    'entity_id' => $taskRec->company_id,
                    'entity_type' => 'company',
                    'company_id' => $taskRec->company_id,
                    'branch_id' => $taskRec->agent->branch_id,
                    'transaction_type' => 'debit',
                    'amount' => $detail->new_task_profit,
                    'description' => 'Adjusted Profit - Refund (' . $refund->refund_number . ')',
                    'reference_type' => 'Refund',
                    'reference_number' => $refund->refund_number,
                    'name' => $taskRec->client_name,
                    'remarks_internal' => $refund->remarks_internal,
                    'transaction_date' => $refund->refund_date,
                ]);
                Log::info('Transaction created', ['transaction_id' => $transaction->id]);

                $incomeIndirectIncome = Account::where('name', 'LIKE', '%Expenses%')->first();
                Log::info('Fetched incomeIndirectIncome', ['id' => $incomeIndirectIncome->id ?? null]);

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
                Log::info('Supplier Refund Income Account', ['account_id' => $supplierRefundIncome->id]);

                $incomeIndirectLiability = Account::where('name', 'LIKE', '%Refund Payable%')
                    ->where('company_id', $taskRec->company_id)
                    ->where('root_id', 2)
                    ->first();
                Log::info('Fetched incomeIndirectLiability', ['id' => $incomeIndirectLiability->id ?? null]);

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
                Log::info('Supplier Refund Liability Account', ['account_id' => $supplierRefundLiability->id]);

                $journal1 = JournalEntry::create([
                    'transaction_date' => $refund->refund_date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $taskRec->company_id,
                    'branch_id' => $taskRec->agent->branch_id,
                    'account_id' => $supplierRefundIncome->id,
                    'description' => $refund->refund_number . ' - ' . $supplierRefundIncome->name . '',
                    'debit' => $detail->new_task_profit,
                    'credit' => 0,
                    'voucher_number' => $refund->id,
                    'name' => $supplierRefundIncome->name,
                    'type' => 'refund',
                ]);
                Log::info('Journal entry (debit) created', ['journal_id' => $journal1->id]);

                $journal2 = JournalEntry::create([
                    'transaction_date' => $refund->refund_date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $taskRec->company_id,
                    'branch_id' => $taskRec->agent->branch_id,
                    'account_id' => $supplierRefundLiability->id,
                    'description' => $refund->refund_number . ' - ' . $supplierRefundLiability->name . '',
                    'debit' => 0,
                    'credit' => $detail->new_task_profit,
                    'voucher_number' => $refund->id,
                    'name' => $supplierRefundLiability->name,
                    'type' => 'refund',
                ]);
                Log::info('Journal entry (credit) created', ['journal_id' => $journal2->id]);

                $credit = Credit::create([
                    'company_id'  => $taskRec->company_id,
                    'branch_id'   => $taskRec->agent->branch_id,
                    'client_id'   => $taskRec->client_id,
                    'refund_id'   => $refund->id,
                    'type'        => 'Refund',
                    'description' => $refund->refund_number . ': Refund for ' . $supplierRefundLiability->name,
                    'amount'      => $detail->new_task_profit,
                    'topup_by'    => Auth::user()->company ? 'Company' : (Auth::user()->branch ? 'Branch' : 'Agent'),
                ]);
                Log::info('Credit created', ['credit_id' => $credit->id]);
            }

            $refund->update(['status' => 'completed']);
            Log::info('Refund status updated to completed', ['refund_id' => $refund->id]);

            return redirect()->route('refunds.index')->with('success', 'Refund processed successfully.');
        } catch (\Exception $e) {
            Log::error('Error in completeProcess()', [
                'refund_id' => $refund->id,
                'error_message' => $e->getMessage(),
                'stack' => $e->getTraceAsString(),
            ]);
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

    public function createRefundInvoiceUnpaid(
        Refund $refund,
        float $invoicePrice,
        string $paymentGateway,
        ?string $paymentMethod = null,
    ): JsonResponse {
        $user = Auth::user();

        $refund->load(['refundDetails.task.agent', 'refundDetails.task.client', 'originalInvoice', 'invoice']);
        $firstTask = $refund->refundDetails->first()->task;
        $companyId = $firstTask->company_id;

        try {
            $invoiceSequence = InvoiceSequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
            $currentSequence = $invoiceSequence->current_sequence;
            $invoiceSequence->increment('current_sequence');
            $invoiceNumber = app(InvoiceController::class)->generateInvoiceNumber($currentSequence);
            $isTrueUnpaid = empty($refund->originalInvoice?->payment_type) && !InvoicePartial::where('invoice_id', $refund->originalInvoice?->id)->exists();

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'client_id' => $firstTask->client_id,
                'agent_id' => $firstTask->agent_id,
                'currency' => $firstTask->exchange_currency ?? 'KWD',
                'sub_amount' => $invoicePrice,
                'invoice_charge' => 0,
                'amount' => $invoicePrice,
                'status' => 'unpaid',
                'invoice_date' => $refund->refund_date,
                'paid_date' => null,
                'due_date' => Carbon::parse($refund->refund_date)->addDays(3)->toDateString(),
                'label' => 'refund',
                'payment_type' => 'full'
            ]);

            foreach ($refund->refundDetails as $detail) {
                $task = $detail->task;

                if ($isTrueUnpaid) {
                    $taskPrice = $detail->total_refund_to_client;
                    $supplierPrice = $detail->total_refund_to_client;
                    $markupPrice = 0;
                } else {
                    $taskPrice = $detail->total_refund_to_client;
                    $supplierPrice = $detail->refund_fee_to_client;
                    $markupPrice = $detail->new_task_profit;
                }

                InvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'task_id' => $task->id,
                    'task_description' => $task->reference,
                    'task_remark' => $isTrueUnpaid ? 'Refund adjustment for invoice ' . $refund->originalInvoice?->invoice_number
                        : 'Refund for task ' . $task->reference,
                    'task_price' => $taskPrice,
                    'supplier_price' => $supplierPrice,
                    'markup_price' => $markupPrice,
                    'created_by' => $user->id,
                ]);
            }

            $refund->update(['refund_invoice_id' => $invoice->id]);
            if ($refund->originalInvoice) {
                $refund->originalInvoice->update(['status' => InvoiceStatus::PAID_BY_REFUND->value]);
            }

            InvoicePartial::create([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'client_id' => $firstTask->client_id,
                'service_charge' => 0.00,
                'amount' => $invoicePrice,
                'status' => 'unpaid',
                'expiry_date' => Carbon::parse($refund->refund_date)->addDays(3)->toDateString(),
                'type' => 'full',
                'payment_gateway' => $paymentGateway,
                'payment_method' => $paymentMethod,
                'charge_id' => Charge::where('name', $paymentGateway)->value('id'),
            ]);

            $transaction = Transaction::create([
                'company_id' => $companyId,
                'branch_id' => $firstTask->agent->branch_id,
                'entity_id' => $firstTask->company_id,
                'entity_type' => 'company',
                'transaction_type' => 'credit',
                'amount' =>  $invoice->amount,
                'description' => 'Refund Invoice: ' . $invoice->invoice_number . ' Generated',
                'invoice_id' => $invoice->id,
                'reference_type' => 'Invoice',
                'transaction_date' => $invoice->invoice_date,
            ]);

            foreach ($refund->refundDetails as $detail) {
                $task = $detail->task;
                if (!$task) {
                    Log::warning("Skipping refund detail {$detail->id} - no linked task found.");
                    continue;
                }

                $taskPrice = $task->invoiceDetail?->task_price ?? $detail->total_refund_to_client;

                $accountReceivable = Account::where('name', 'Accounts Receivable')
                    ->where('company_id', $companyId)
                    ->first();

                $clientAccount = Account::where('name', 'Clients')
                    ->where('company_id', $companyId)
                    ->where('parent_id', $accountReceivable->id)
                    ->first();

                if ($clientAccount) {
                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $task->agent->branch_id,
                        'company_id' => $companyId,
                        'account_id' => $clientAccount->id,
                        'task_id' => $task->id,
                        'agent_id' => $task->agent_id ?? $invoice->agent_id,
                        'invoice_id' => $invoice->id,
                        'invoice_detail_id' => $task->invoiceDetail?->id,
                        'transaction_date' => $invoice->invoice_date,
                        'description' => 'Invoice created for (Assets): ' . $invoice->client->full_name,
                        'debit' => $taskPrice,
                        'credit' => 0,
                        'balance' => $clientAccount->balance ?? 0,
                        'name' => $clientAccount->name,
                        'type' => 'receivable',
                        'currency' => $task->currency ?? 'KWD',
                        'exchange_rate' => $task->exchange_rate ?? 1.00,
                        'amount' => $taskPrice,
                    ]);
                } else {
                    Log::warning("Accounts Receivable client account not found for company {$companyId}.");
                }

                $bookingAccountName = ucfirst($task->type) . ' Booking Revenue';
                $detailsAccount = Account::where('name', 'like', '%' . $bookingAccountName . '%')
                    ->where('company_id', $companyId)
                    ->first();

                if (!$detailsAccount) {
                    Log::info("Booking revenue account '{$bookingAccountName}' not found. Creating it now...");
                    $directIncomeParent = Account::where('name', 'like', '%Direct Income%')
                        ->where('company_id', $companyId)
                        ->first();

                    $lastRevenue = Account::where('parent_id', $directIncomeParent->id)
                        ->where('company_id', $companyId)
                        ->orderByDesc('code')
                        ->first();

                    $lastCode = (int)($lastRevenue?->code ?? 4110);
                    $nextCode = $lastCode + 5;

                    $detailsAccount = Account::create([
                        'code' => str_pad($nextCode, 4, '0', STR_PAD_LEFT),
                        'name' => $bookingAccountName,
                        'company_id' => $companyId,
                        'root_id' => $directIncomeParent->root_id,
                        'parent_id' => $directIncomeParent->id,
                        'branch_id' => $task->agent->branch_id,
                        'account_type' => 'income',
                        'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
                        'level' => $directIncomeParent->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                    ]);

                    Log::info("Auto-created new booking revenue account '{$bookingAccountName}' ({$detailsAccount->code}) for company {$companyId}");
                }

                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $task->agent->branch_id,
                    'company_id' => $companyId,
                    'account_id' => $detailsAccount->id,
                    'task_id' => $task->id,
                    'agent_id'  => $task->agent_id ?? $invoice->agent_id,
                    'invoice_id' => $invoice->id,
                    'invoice_detail_id' => $task->invoiceDetail?->id,
                    'transaction_date'  => $invoice->invoice_date,
                    'description' => 'Invoice reversal for (Income): ' . $task->reference,
                    'debit' => 0,
                    'credit' => $taskPrice,
                    'balance' => $detailsAccount->balance ?? 0,
                    'name' => $detailsAccount->name,
                    'type' => 'payable',
                    'currency' => $task->currency ?? 'KWD',
                    'exchange_rate' => $task->exchange_rate ?? 1.00,
                    'amount' => $taskPrice,
                ]);

                $agent = $task->agent ?? $firstTask->agent;
                if ($agent && in_array(strtolower($agent->agentType?->name), ['commission', 'type-a'], true)) {
                    $commissionRate = (float)($agent->commission ?? 0);
                    $commissionAmount = $detail->new_task_profit * $commissionRate;

                    $directExpense = Account::where('name', 'LIKE', '%Direct Expenses%')
                        ->where('company_id', $companyId)
                        ->where('root_id', 5)
                        ->first();

                    $commissionExpense = Account::firstOrCreate(
                        [
                            'name' => 'Commissions Expense (Agents)',
                            'company_id' => $companyId,
                            'root_id' => optional($directExpense)->root_id,
                        ],
                        [
                            'parent_id' => optional($directExpense)->id,
                            'branch_id' => $task->agent->branch_id,
                            'root_id' => $directExpense->root_id,
                            'code' => $directExpense->code + 1,
                            'account_type' => 'asset',
                            'report_type' => 'balance sheet',
                            'level' => $directExpense->level + 1,
                            'is_group' => 0,
                            'disabled' => 0,
                            'actual_balance' => 0.00,
                            'budget_balance' => 0.00,
                            'variance' => 0.00,
                            'currency' => 'KWD',
                        ]
                    );

                    $indirectExpense = Account::where('name', 'LIKE', '%Accrued Expenses%')
                        ->where('company_id', $companyId)
                        ->where('root_id', 2)
                        ->first();

                    $commissionLiability = Account::firstOrCreate(
                        [
                            'name' => 'Commission (Agents)',
                            'company_id' => $companyId,
                            'root_id' => optional($indirectExpense)->root_id,
                        ],
                        [
                            'parent_id' => optional($indirectExpense)->id,
                            'branch_id' => $task->agent->branch_id,
                            'root_id' => $indirectExpense->root_id,
                            'code' => $indirectExpense->code + 1,
                            'account_type' => 'liability',
                            'report_type' => 'balance sheet',
                            'level' => $indirectExpense->level + 1,
                            'is_group' => 0,
                            'disabled' => 0,
                            'actual_balance' => 0.00,
                            'budget_balance' => 0.00,
                            'variance' => 0.00,
                            'currency' => 'KWD',
                        ]
                    );

                    JournalEntry::create([
                        'transaction_date' => $refund->refund_date,
                        'transaction_id' => $transaction->id,
                        'company_id' => $companyId,
                        'branch_id' => $task->agent->branch_id,
                        'account_id' => $commissionExpense->id,
                        'description' => 'Refund Commission - Agent gets ' . ($commissionRate * 100) . '% of refund fee (Assets): ' . $commissionExpense->name,
                        'debit' => $commissionAmount,
                        'credit' => 0,
                        'voucher_number' => $refund->id,
                        'name' => $commissionExpense->name,
                        'type' => 'refund',
                    ]);

                    JournalEntry::create([
                        'transaction_date' => $refund->refund_date,
                        'transaction_id' => $transaction->id,
                        'company_id' => $companyId,
                        'branch_id' => $task->agent->branch_id,
                        'account_id' => $commissionLiability->id,
                        'description' => 'Refund Commission - Agent gets ' . ($commissionRate * 100) . '% of refund fee (Liabilities): ' . $commissionLiability->name,
                        'debit' => 0,
                        'credit' => $commissionAmount,
                        'voucher_number' => $refund->id,
                        'name' => $commissionLiability->name,
                        'type' => 'refund',
                    ]);
                }
            }

            if ($isTrueUnpaid) {
                $unrefundedTasks = $refund->originalInvoice ? $refund->originalInvoice->invoiceDetails()
                    ->whereNotIn(
                        'task_id',
                        $refund->refundDetails
                            ->map(fn($d) => $d->task?->originalTask?->id ?? $d->task_id)
                            ->filter()
                            ->toArray()
                    )->get() : collect();

                foreach ($unrefundedTasks as $detail) {
                    $task = $detail->task;
                    $taskPrice = $detail->task_price ?? 0;

                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $task->agent->branch_id,
                        'company_id' => $companyId,
                        'account_id' => $clientAccount->id,
                        'task_id' => $task->id,
                        'agent_id' => $task->agent_id ?? $invoice->agent_id,
                        'invoice_id' => $invoice->id,
                        'invoice_detail_id' => $task->invoiceDetail?->id,
                        'transaction_date' => $invoice->invoice_date,
                        'description' => 'Invoice created for (Assets): ' . $invoice->client->full_name,
                        'debit' => $taskPrice,
                        'credit' => 0,
                        'balance' => $clientAccount->balance ?? 0,
                        'name' => $clientAccount->name,
                        'type' => 'receivable',
                        'currency' => $task->currency ?? 'KWD',
                        'exchange_rate' => $task->exchange_rate ?? 1.00,
                        'amount' => $taskPrice,
                    ]);

                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $task->agent->branch_id,
                        'company_id' => $companyId,
                        'account_id' => $detailsAccount->id,
                        'task_id' => $task->id,
                        'agent_id'  => $task->agent_id ?? $invoice->agent_id,
                        'invoice_id' => $invoice->id,
                        'invoice_detail_id' => $task->invoiceDetail?->id,
                        'transaction_date'  => $invoice->invoice_date,
                        'description' => 'Invoice created for (Income): ' . $task->reference,
                        'debit' => 0,
                        'credit' => $taskPrice,
                        'balance' => $detailsAccount->balance ?? 0,
                        'name' => $detailsAccount->name,
                        'type' => 'payable',
                        'currency' => $task->currency ?? 'KWD',
                        'exchange_rate' => $task->exchange_rate ?? 1.00,
                        'amount' => $taskPrice,
                    ]);

                    if ($agent && in_array(strtolower($agent->agentType?->name), ['commission', 'type-a'], true)) {
                        $commissionRate = (float)($agent->commission ?? 0);
                        $commissionAmount = $detail->markup_price * $commissionRate;

                        $directExpense = Account::where('name', 'LIKE', '%Direct Expenses%')
                            ->where('company_id', $companyId)
                            ->where('root_id', 5)
                            ->first();

                        $commissionExpense = Account::firstOrCreate(
                            [
                                'name' => 'Commissions Expense (Agents)',
                                'company_id' => $companyId,
                                'root_id' => optional($directExpense)->root_id,
                            ],
                            [
                                'parent_id' => optional($directExpense)->id,
                                'branch_id' => $task->agent->branch_id,
                                'root_id' => $directExpense->root_id,
                                'code' => $directExpense->code + 1,
                                'account_type' => 'asset',
                                'report_type' => 'balance sheet',
                                'level' => $directExpense->level + 1,
                                'is_group' => 0,
                                'disabled' => 0,
                                'actual_balance' => 0.00,
                                'budget_balance' => 0.00,
                                'variance' => 0.00,
                                'currency' => 'KWD',
                            ]
                        );

                        $indirectExpense = Account::where('name', 'LIKE', '%Accrued Expenses%')
                            ->where('company_id', $companyId)
                            ->where('root_id', 2)
                            ->first();

                        $commissionLiability = Account::firstOrCreate(
                            [
                                'name' => 'Commission (Agents)',
                                'company_id' => $companyId,
                                'root_id' => optional($indirectExpense)->root_id,
                            ],
                            [
                                'parent_id' => optional($indirectExpense)->id,
                                'branch_id' => $task->agent->branch_id,
                                'root_id' => $indirectExpense->root_id,
                                'code' => $indirectExpense->code + 1,
                                'account_type' => 'liability',
                                'report_type' => 'balance sheet',
                                'level' => $indirectExpense->level + 1,
                                'is_group' => 0,
                                'disabled' => 0,
                                'actual_balance' => 0.00,
                                'budget_balance' => 0.00,
                                'variance' => 0.00,
                                'currency' => 'KWD',
                            ]
                        );

                        JournalEntry::create([
                            'transaction_date' => $refund->refund_date,
                            'transaction_id' => $transaction->id,
                            'company_id' => $companyId,
                            'branch_id' => $task->agent->branch_id,
                            'account_id' => $commissionExpense->id,
                            'description' => 'Refund Commission - Agent gets ' . ($commissionRate * 100) . '% of refund fee (Assets): ' . $commissionExpense->name,
                            'debit' => $commissionAmount,
                            'credit' => 0,
                            'voucher_number' => $refund->id,
                            'name' => $commissionExpense->name,
                            'type' => 'refund',
                        ]);

                        JournalEntry::create([
                            'transaction_date' => $refund->refund_date,
                            'transaction_id' => $transaction->id,
                            'company_id' => $companyId,
                            'branch_id' => $task->agent->branch_id,
                            'account_id' => $commissionLiability->id,
                            'description' => 'Refund Commission - Agent gets ' . ($commissionRate * 100) . '% of refund fee (Liabilities): ' . $commissionLiability->name,
                            'debit' => 0,
                            'credit' => $commissionAmount,
                            'voucher_number' => $refund->id,
                            'name' => $commissionLiability->name,
                            'type' => 'refund',
                        ]);
                    }
                }
            }

            Log::info('Invoice created successfully from multi-task refund', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'refund_id' => $refund->id,
                'amount' => $invoicePrice,
            ]);

            return response()->json([
                'success' => true,
                'invoiceId' => $invoice->id,
                'invoiceNumber' => $invoiceNumber,
                'message' => 'Invoice created successfully from refund',
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to create invoice from refund: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create invoice from refund.'], 500);
        }
    }

    public function createRefundInvoicePartial(
        Refund $refund,
        float $invoicePrice,
        string $paymentGateway,
        ?string $paymentMethod = null,
    ): JsonResponse {
        $user = Auth::user();

        $refund->load(['refundDetails.task.agent', 'refundDetails.task.client', 'originalInvoice', 'invoice']);
        $firstTask = $refund->refundDetails->first()->task;
        $companyId = $firstTask->company_id;

        try {
            $invoiceSequence = InvoiceSequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
            $currentSequence = $invoiceSequence->current_sequence;
            $invoiceSequence->increment('current_sequence');
            $invoiceNumber = app(InvoiceController::class)->generateInvoiceNumber($currentSequence);

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'client_id' => $firstTask->client_id,
                'agent_id' => $firstTask->agent_id,
                'currency' => $firstTask->exchange_currency ?? 'KWD',
                'sub_amount' => $invoicePrice,
                'invoice_charge' => 0,
                'amount' => $invoicePrice,
                'status' => 'unpaid',
                'invoice_date' => $refund->refund_date,
                'paid_date' => null,
                'due_date' => Carbon::parse($refund->refund_date)->addDays(3)->toDateString(),
                'label' => 'refund',
                'payment_type' => 'full'
            ]);

            foreach ($refund->refundDetails as $detail) {
                $task = $detail->task;

                InvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'task_id' => $task->id,
                    'task_description' => $task->reference,
                    'task_remark' => 'Partial refund adjustment for invoice ' . $refund->originalInvoice?->invoice_number,
                    'task_price' => 0,
                    'supplier_price' => 0,
                    'markup_price' => 0,
                    'created_by' => $user->id,
                ]);
            }

            $refund->update(['refund_invoice_id' => $invoice->id]);
            if ($refund->originalInvoice) {
                $refund->originalInvoice->update(['status' => InvoiceStatus::PAID_BY_REFUND->value]);
            }

            InvoicePartial::create([
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'client_id' => $firstTask->client_id,
                'service_charge' => 0.00,
                'amount' => $invoicePrice,
                'status' => 'unpaid',
                'expiry_date' => Carbon::parse($refund->refund_date)->addDays(3)->toDateString(),
                'type' => 'full',
                'payment_gateway' => $paymentGateway,
                'payment_method' => $paymentMethod,
                'charge_id' => Charge::where('name', $paymentGateway)->value('id'),
            ]);

            $transaction = Transaction::create([
                'company_id' => $companyId,
                'branch_id' => $firstTask->agent->branch_id,
                'entity_id' => $firstTask->company_id,
                'entity_type' => 'company',
                'transaction_type' => 'credit',
                'amount' =>  $invoice->amount,
                'description' => 'Refund Invoice: ' . $invoice->invoice_number . ' Generated',
                'invoice_id' => $invoice->id,
                'reference_type' => 'Invoice',
                'transaction_date' => $invoice->invoice_date,
            ]);

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
                    'branch_id' => $firstTask->agent->branch_id,
                    'account_id' => $clientAccount->id,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Invoice created for (Assets): ' . $invoice->client->full_name,
                    'debit' => $invoicePrice,
                    'credit' => 0,
                    'name' => $clientAccount->name,
                    'type' => 'receivable',
                    'currency' => $firstTask->currency ?? 'KWD',
                    'exchange_rate' => $firstTask->exchange_rate ?? 1.00,
                    'amount' => $invoicePrice,
                ]);
            }

            $bookingAccountName = ucfirst($firstTask->type) . ' Booking Revenue';
            $detailsAccount = Account::where('name', 'like', '%' . $bookingAccountName . '%')
                ->where('company_id', $companyId)
                ->first();

            if ($detailsAccount) {
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'company_id' => $companyId,
                    'branch_id' => $firstTask->agent->branch_id,
                    'account_id' => $detailsAccount->id,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Partial Invoice reversal for (Income): ' . $refund->originalInvoice?->invoice_number,
                    'debit' => 0,
                    'credit' => $invoicePrice,
                    'name' => $detailsAccount->name,
                    'type' => 'payable',
                    'currency' => $firstTask->currency ?? 'KWD',
                    'exchange_rate' => $firstTask->exchange_rate ?? 1.00,
                    'amount' => $invoicePrice,
                ]);
            }

            $agent = $firstTask->agent;
            if ($agent && in_array(strtolower($agent->agentType?->name), ['commission', 'type-a'], true)) {
                $commissionRate = (float)($agent->commission ?? 0);
                $commissionAmount = $refund->new_task_profit * $commissionRate;

                $directExpense = Account::where('name', 'LIKE', '%Direct Expenses%')
                    ->where('company_id', $companyId)
                    ->where('root_id', 5)
                    ->first();

                $commissionExpense = Account::firstOrCreate(
                    [
                        'name' => 'Commissions Expense (Agents)',
                        'company_id' => $companyId,
                        'root_id' => $directExpense->root_id,
                    ],
                    [
                        'parent_id' => $directExpense->id,
                        'branch_id' => $task->agent->branch_id,
                        'root_id' => $directExpense->root_id,
                        'code' => $directExpense->code + 1,
                        'account_type' => 'asset',
                        'report_type' => 'balance sheet',
                        'level' => $directExpense->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                    ]
                );

                $indirectExpense = Account::where('name', 'LIKE', '%Accrued Expenses%')
                    ->where('company_id', $companyId)
                    ->where('root_id', 2)
                    ->first();

                $commissionLiability = Account::firstOrCreate(
                    [
                        'name' => 'Commission (Agents)',
                        'company_id' => $companyId,
                        'root_id' => $indirectExpense->root_id,
                    ],
                    [
                        'parent_id' => $indirectExpense->id,
                        'branch_id' => $task->agent->branch_id,
                        'root_id' => $indirectExpense->root_id,
                        'code' => $indirectExpense->code + 1,
                        'account_type' => 'liability',
                        'report_type' => 'balance sheet',
                        'level' => $indirectExpense->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                    ]
                );

                JournalEntry::create([
                    'transaction_date' => $refund->refund_date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $companyId,
                    'branch_id' => $task->agent->branch_id,
                    'account_id' => $commissionExpense->id,
                    'description' => 'Refund Commission - Agent gets ' . ($commissionRate * 100) . '% of refund fee (Assets): ' . $commissionExpense->name,
                    'debit' => $commissionAmount,
                    'credit' => 0,
                    'voucher_number' => $refund->id,
                    'name' => $commissionExpense->name,
                    'type' => 'refund',
                ]);

                JournalEntry::create([
                    'transaction_date' => $refund->refund_date,
                    'transaction_id' => $transaction->id,
                    'company_id' => $companyId,
                    'branch_id' => $task->agent->branch_id,
                    'account_id' => $commissionLiability->id,
                    'description' => 'Refund Commission - Agent gets ' . ($commissionRate * 100) . '% of refund fee (Liabilities): ' . $commissionLiability->name,
                    'debit' => 0,
                    'credit' => $commissionAmount,
                    'voucher_number' => $refund->id,
                    'name' => $commissionLiability->name,
                    'type' => 'refund',
                ]);
            }

            Log::info('Invoice created successfully from multi-task refund', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoiceNumber,
                'refund_id' => $refund->id,
                'amount' => $invoicePrice,
            ]);

            return response()->json([
                'success' => true,
                'invoiceId' => $invoice->id,
                'invoiceNumber' => $invoiceNumber,
                'message' => 'Invoice created successfully from refund',
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to create invoice from refund: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create invoice from refund.'], 500);
        }
    }
}
