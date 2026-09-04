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
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Country;
use App\Models\Setting;
use App\Models\Agent;
use App\Models\Supplier;
use App\Models\Branch;
use App\Services\Accounting\AccountingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
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
use App\Services\Accounting\PostingSeam;
use App\Services\Accounting\RefundPostingService;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Refund::class);

        $user = Auth::user();

        $companyId = getCompanyId($user);
        $refundClients = collect();

        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');

        $allowedSortFields = ['refund_number', 'created_at', 'client_name'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }

        $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $refundsQuery = Refund::with(['refundDetails.task.client', 'refundDetails.task.agent', 'originalInvoice', 'invoice']);
        $filterAgents = collect();

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $company = Company::with('branches.agents.refundClients')->find($companyId);
                $agents = $company->branches->pluck('agents')->flatten();
                $refundClients = $agents->pluck('refundClients')->flatten();
                $refundsQuery->where('company_id', $companyId);
                $filterAgents = $agents;
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $agents = $user->company->branches->pluck('agents')->flatten();
            $refundClients = $agents->pluck('refundClients')->flatten();
            $refundsQuery->where('company_id', $companyId);
            $filterAgents = $agents;
        } elseif ($user->role_id == Role::BRANCH) {
            $refundClients = $user->branch->agents->pluck('refundClients')->flatten();
            $refundsQuery->where('branch_id', $user->branch->id);
            $filterAgents = $user->branch->agents;
        } elseif ($user->role_id == Role::AGENT) {
            $refundClients = $user->agent->refundClients;
            $refundsQuery->where('agent_id', $user->agent->id);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $refundClients = $user->accountant->branch->agents->pluck('refundClients')->flatten();
            $refundsQuery->where('branch_id', $user->accountant->branch_id);
            $filterAgents = $user->accountant->branch->agents;
        }

        // W4.U §b — "Agent-scoped list: refund index filterable by agent" (w4-brief.md §4). Only
        // meaningful for a non-agent viewer (an AGENT role is already hard-scoped to their own
        // refunds above); further constrained to an agent already inside this viewer's own
        // company/branch scope above, never an arbitrary id from the query string.
        if ($request->filled('agent_id') && $user->role_id != Role::AGENT) {
            $refundsQuery->where('agent_id', (int) $request->input('agent_id'));
        }

        $totalRefunds = $refundsQuery->count();

        if ($sortField === 'client_name') {
            $allRefunds = $refundsQuery->orderBy('created_at', 'desc')->get();

            $sorted = $allRefunds->sortBy(function ($refund) {
                return strtolower($refund->refundDetails->pluck('client.full_name')->first() ?? '');
            }, SORT_REGULAR, $sortDirection === 'desc')->values();

            $page = $request->input('page', 1);
            $perPage = 20;
            $refunds = new \Illuminate\Pagination\LengthAwarePaginator(
                $sorted->forPage($page, $perPage),
                $sorted->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $refunds = $refundsQuery->orderBy($sortField, $sortDirection)->paginate(20)->withQueryString();
        }

        $totalRefundClients = $refundClients->count();

        return view('refunds.index', compact(
            'refunds',
            'totalRefunds',
            'refundClients',
            'totalRefundClients',
            'filterAgents',
        ));
    }

    public function generateRefundNumber($sequence)
    {
        $year = now()->year;
        return sprintf('RF-%s-%05d', $year, $sequence);
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        $selectedCompany = $companyId ? Company::find($companyId) : null;
        $agents = collect();
        $branches = collect();
        $clients = collect();
        $agentsId = [];

        $taskIds = $request->query('task_ids', '');
        $taskIdsArray = is_string($taskIds) ? explode(',', $taskIds) : $taskIds;

        if (empty($taskIdsArray)) {
            return redirect()->back()->withErrors(['error' => 'No tasks selected for refund.']);
        }

        $tasksForValidation = Task::with([
            'agent.branch.company',
            'client',
            'supplier',
            'invoiceDetail.invoice',
            'originalTask.invoiceDetail.invoice',
        ])->whereIn('id', $taskIdsArray)->get();

        if ($tasksForValidation->isEmpty()) {
            return back()->withErrors(['error' => 'No valid tasks found for refund.']);
        }

        $refundedTaskIds = RefundDetail::whereIn('task_id', $tasksForValidation->pluck('id'))->pluck('task_id')->unique();
        if ($refundedTaskIds->isNotEmpty()) {
            $refundedTaskRefs = $tasksForValidation->whereIn('id', $refundedTaskIds)->pluck('reference')->join(', ');
            return redirect()->back()->withErrors([
                'error' => "Refund already exists for task(s): {$refundedTaskRefs}. You cannot create a new refund for these tasks."
            ]);
        }

        $invoiceIds = collect();

        foreach ($tasksForValidation as $task) {
            $isRefundStatus = strtolower($task->status) === 'refund';
            $invoiceDetail = $isRefundStatus ? $task->originalTask?->invoiceDetail : $task->invoiceDetail;
            $invoice = $invoiceDetail?->invoice;

            if ($invoice) {
                $invoiceIds->push($invoice->id);
            }

            if ($isRefundStatus) {
                if (!$task->originalTask) {
                    Log::error('Refund task ' . $task->reference . ' does not have an original task linked');
                    return redirect()->back()->withErrors([
                        'error' => "Refund task {$task->reference} must be linked to an original task before processing."
                    ]);
                }

                if (!$invoiceDetail || !$invoice) {
                    Log::error('Original task for refund task ' . $task->reference . ' has not been invoiced');
                    return redirect()->back()->withErrors([
                        'error' => "The original task for {$task->reference} has not been invoiced yet. Cannot process refund."
                    ]);
                }
            } else {
                if (!$invoiceDetail || !$invoice) {
                    Log::error('Task for ' . $task->reference . ' has not yet been invoiced or invoice details are missing');
                    return redirect()->back()->withErrors([
                        'error' => "Task for {$task->reference} has not been invoiced yet or invoice details are missing."
                    ]);
                }

                $invoicePaymentStatus = strtolower($invoice->status ?? '');
                if (!in_array($invoicePaymentStatus, ['paid', 'unpaid', 'partial', 'partial refund'])) {
                    Log::error('Invoice status of task ' . $task->reference . ' is ' . $invoicePaymentStatus . ' which is not valid for refund processing.');
                    return redirect()->back()->withErrors([
                        'error' => 'Invoice with payment status of ' . $invoicePaymentStatus . ' cannot be processed for refund yet. Sorry for the inconvenience.'
                    ]);
                }
            }

            if (($task->agent->agent_type_id ?? 1) != 1 && ($task->agent->commission ?? 0) <= 0) {
                return redirect()->back()->withErrors([
                    'error' => "The agent for task {$task->reference} does not have a valid commission to process a refund. Please set a valid commission for the agent."
                ]);
            }
        }

        if ($invoiceIds->unique()->count() > 1) {
            // W4.U §b — "Cross-invoice selection allowed in the picker" (w4-brief.md §4 process
            // decisions), but only when the engine is ON for this company AND every resolved
            // invoice is already paid/partial-refund — see storeRefundBatch()'s own docblock for
            // why the batch path is scoped that way. Everything else (engine OFF, or any invoice
            // not yet fully paid) keeps the pre-existing single-invoice-only restriction.
            $batchCompanyId = $tasksForValidation->first()->agent->branch->company_id ?? null;
            $batchEngineOn = $batchCompanyId !== null && app(PostingSeam::class)->isEnabledFor((int) $batchCompanyId);
            $allInvoicesPaidLike = $tasksForValidation->every(function ($t) {
                $inv = strtolower($t->status) === 'refund'
                    ? $t->originalTask?->invoiceDetail?->invoice
                    : $t->invoiceDetail?->invoice;

                return in_array(strtolower($inv?->status ?? ''), ['paid', 'partial refund'], true);
            });

            if (! $batchEngineOn || ! $allInvoicesPaidLike) {
                return redirect()->back()->withErrors([
                    'error' => 'Refund cannot include tasks from different original invoices. Please process each invoice refund separately.'
                ]);
            }
        }

        $firstTaskForValidation = $tasksForValidation->first();
        $isRefundStatus = strtolower($firstTaskForValidation->status) === 'refund';
        $firstInvoice = $isRefundStatus
            ? $firstTaskForValidation->originalTask?->invoiceDetail?->invoice
            : $firstTaskForValidation->invoiceDetail?->invoice;
        $isPaidInvoice = in_array(strtolower($firstInvoice?->status ?? ''), ['paid', 'partial refund']);

        $selectedTasks = Task::with([
            'supplier',
            'agent.branch.company',
            'client',
            'invoiceDetail.invoice',
            'flightDetails.countryFrom',
            'flightDetails.countryTo',
            'hotelDetails.hotel',
            'visaDetails',
            'insuranceDetails',
            'originalTask.invoiceDetail.invoice',
            'originalTask.flightDetails.countryFrom',
            'originalTask.flightDetails.countryTo',
            'originalTask.hotelDetails.hotel',
            'originalTask.visaDetails',
            'originalTask.insuranceDetails',
        ])
            ->whereIn('id', $taskIdsArray)
            ->get();

        $selectedTasks = $selectedTasks->map(function ($task) use ($tasksForValidation) {
            $validationTask = $tasksForValidation->firstWhere('id', $task->id);

            $isRefundStatus = strtolower($task->status) === 'refund';

            $invoiceDetail = $isRefundStatus
                ? $validationTask?->originalTask?->invoiceDetail
                : $validationTask?->invoiceDetail;

            $sourceTask = $isRefundStatus
                ? $validationTask?->originalTask
                : $validationTask;

            $task->agent_name = $task->agent->name ?? null;
            $task->branch_name = $task->agent->branch->name ?? null;
            $task->supplier_name = $task->supplier->name ?? null;
            $task->client_name = $task->client->full_name ?? null;

            //Refund Calculation
            // Original Task Profit (markup price)
            $originalTaskProfit = floatval($invoiceDetail?->markup_price ?? 0);

            // Supplier Charge (Original Task Cost - Refund Task Cost)
            $originalTaskCost = floatval($sourceTask?->total ?? 0);
            $refundTaskCost = floatval($task->total);
            $supplierCharge = $originalTaskCost - $refundTaskCost;

            // Task Price for Refund = Original Profit + Supplier Charge
            $taskPriceForRefund = $originalTaskProfit + $supplierCharge;

            $task->original_task_total = $task->total;

            $task->total = $taskPriceForRefund;

            $task->refund_original_profit = $originalTaskProfit;
            $task->refund_supplier_charge = $supplierCharge;
            $task->refund_original_invoice_price = floatval($invoiceDetail?->task_price ?? 0);
            $task->refund_original_supplier_cost = floatval($invoiceDetail?->supplier_price ?? 0);

            return $task;
        });

        $firstTask = $selectedTasks->first();
        $taskCompanyId = $firstTask->agent->branch->company_id ?? null;

        if ($user->role_id == Role::ADMIN && $taskCompanyId) {
            $companyId = $taskCompanyId;
        }

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $company = Company::with('branches.agents')->find($companyId);
                $agents = $company->branches->flatMap->agents;
                $branches = $company->branches;
                $agentsId = $agents->pluck('id')->toArray();
                $selectedCompany = $company;

                $suppliers = Supplier::whereHas('companies', function ($query) use ($companyId) {
                    $query->where('company_id', $companyId)->where('is_active', true);
                })->with('companies')->get();
            } else {
                $agents = Agent::with('branch.company')->get();
                $branches = Branch::all();
                $agentsId = $agents->pluck('id')->toArray();

                $suppliers = Supplier::with(['companies' => function ($query) {
                    $query->where('is_active', true);
                }])->get();
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $company = Company::with('branches.agents')->find($companyId);
            $agents = $company->branches->flatMap->agents;
            $branches = $company->branches;
            $selectedCompany = $company;
            $agentsId = $agents->pluck('id')->toArray();

            $suppliers = Supplier::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->where('is_active', true);
            })->with('companies')->get();
        } elseif ($user->role_id == Role::BRANCH) {
            $agents = Agent::where('branch_id', $user->branch->id)->get();
            $agentsId = $agents->pluck('id')->toArray();
            $branches = Branch::where('company_id', $companyId)->get();
            $selectedCompany = $user->branch->company;

            $suppliers = Supplier::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->where('is_active', true);
            })->with('companies')->get();
        } elseif ($user->role_id == Role::AGENT) {
            $agent = $user->agent;
            $agents = Agent::where('id', $agent->id)->get();
            $agentsId = [$agent->id];
            $branches = Branch::where('company_id', $companyId)->get();
            $selectedCompany = $agent->branch->company;

            $suppliers = Supplier::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->where('is_active', true);
            })->with('companies')->get();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $agents = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))->get();
            $agentsId = $agents->pluck('id')->toArray();
            $branches = Branch::where('company_id', $companyId)->get();
            $selectedCompany = Company::find($companyId);

            $suppliers = Supplier::whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->where('is_active', true);
            })->with('companies')->get();
        } else {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        if ($user->role_id == Role::ADMIN && $companyId) {
            $clients = Client::where(function ($query) use ($agentsId) {
                $query->whereIn('agent_id', $agentsId)
                    ->orWhereHas('agents', function ($q) use ($agentsId) {
                        $q->whereIn('agent_id', $agentsId);
                    });
            })->get();
        } elseif ($user->role_id == Role::ADMIN) {
            $clients = Client::all();
        } else {
            $clients = Client::where(function ($query) use ($agentsId) {
                $query->whereIn('agent_id', $agentsId)
                    ->orWhereHas('agents', function ($q) use ($agentsId) {
                        $q->whereIn('agent_id', $agentsId);
                    });
            })->get();
        }

        if ($selectedTasks->count() > 0) {
            $clientIds = $selectedTasks->pluck('client_id')->unique();
            $agentIds = $selectedTasks->pluck('agent_id')->unique();
            $selectedAgent = Agent::find($agentIds->first());
            $selectedClient = $clientIds->count() >= 1 ? Client::find($clientIds->first()) : null;
        } else {
            $selectedAgent = null;
            $selectedClient = null;
        }

        $agentId = $selectedAgent ? $selectedAgent->id : $agentsId;
        $agentId = Arr::flatten((array) $agentId);
        $clientId = $selectedClient ? $selectedClient->id : null;

        $tasks = collect();

        $todayDate = Carbon::now()->format('Y-m-d');
        $appUrl = config('app.url');
        $countries = Country::all();

        $invoiceExpireDefault = Setting::where('key', 'invoice_expiry_days')
            ->where('company_id', $companyId)
            ->first();

        $invoiceExpireDefault = $invoiceExpireDefault
            ? date('Y-m-d', strtotime('+' . $invoiceExpireDefault->value . ' days'))
            : date('Y-m-d', strtotime('+5 days'));

        $invoiceSequence = InvoiceSequence::firstOrCreate(
            ['company_id' => $companyId],
            ['current_sequence' => 1]
        );
        $invoiceNumber = app(InvoiceController::class)->generateInvoiceNumber($invoiceSequence->current_sequence);

        $refundSequence = RefundSequence::firstOrCreate(
            ['company_id' => $companyId],
            ['current_sequence' => 1]
        );
        $refundNumber = $this->generateRefundNumber($refundSequence->current_sequence);

        if ($isPaidInvoice) {
            $selectedTasks->transform(function ($task) use ($tasksForValidation) {
                $validationTask = $tasksForValidation->firstWhere('id', $task->id);
                $isRefundStatus = strtolower($task->status) === 'refund';

                $sourceTask = $isRefundStatus ? $validationTask?->originalTask : $validationTask;
                $invoiceDetail = $isRefundStatus
                    ? $validationTask?->originalTask?->invoiceDetail
                    : $validationTask?->invoiceDetail;

                $task->computed_source_task = $sourceTask;
                $task->computed_invoice_detail = $invoiceDetail;
                $task->computed_invoice = $invoiceDetail?->invoice;

                return $task;
            });

            $uniqueClients = $selectedTasks->pluck('client')->filter()->unique('id')->values();

            // W4.U §b — batch-grouping preview: one group per carrying invoice. A single-invoice
            // selection (the common case) produces exactly one group; the view only renders the
            // preview banner when there is more than one.
            $invoiceGroups = $selectedTasks
                ->groupBy(fn ($t) => $t->computed_invoice?->id)
                ->map(fn ($tasksForInvoice) => [
                    'invoice' => $tasksForInvoice->first()->computed_invoice,
                    'tasks' => $tasksForInvoice,
                ])
                ->values();

            // W4.U verify-fix (HIGH): candidate invoices for disposition='apply' — open (not
            // fully paid/refunded) invoices belonging to this refund's own client(s), excluding
            // every invoice this refund is itself reversing (validateAppliedInvoiceId() enforces
            // the same rule server-side regardless of what this list renders).
            $openInvoiceClientIds = $uniqueClients->pluck('id')->filter()->all();
            $excludedInvoiceIdsForApply = $invoiceIds->unique()->all();
            $openInvoices = empty($openInvoiceClientIds) ? collect() : Invoice::whereIn('client_id', $openInvoiceClientIds)
                ->whereIn('status', ['unpaid', 'partial', 'partial refund'])
                ->whereNotIn('id', $excludedInvoiceIdsForApply)
                ->orderByDesc('id')
                ->get(['id', 'invoice_number', 'status', 'amount', 'client_id']);

            return view('refunds.create-multi', [
                'tasks' => $selectedTasks,
                'refundNumber' => $refundNumber,
                'uniqueClients' => $uniqueClients,
                'invoiceGroups' => $invoiceGroups,
                'openInvoices' => $openInvoices,
            ]);
        }

        return view('invoice.create', compact(
            'clients',
            'agents',
            'branches',
            'agentId',
            'clientId',
            'tasks',
            'suppliers',
            'invoiceNumber',
            'selectedTasks',
            'selectedAgent',
            'selectedClient',
            'selectedCompany',
            'todayDate',
            'appUrl',
            'companyId',
            'invoiceExpireDefault',
            'countries'
        ))->with([
            'isRefund' => true,
            'refundNumber' => $refundNumber,
            'firstInvoice' => $firstInvoice,
            'isPaidInvoice' => $isPaidInvoice,
        ]);
    }

    /**
     * W4.R bundled fix (w4-brief.md §5 "RefundPolicy full abilities ... enforce them in every
     * RefundController mutating action" — ct-refund-map.md §6: store() was entirely unauthorized
     * before this fix).
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Refund::class);

        $validatedData = $request->validate([
            'date' => ['required', 'date'],
            'method' => ['nullable', 'in:Bank,Cash,Online,Credit'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'remarks' => ['nullable', 'string'],
            'remarks_internal' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
            // W4.U §b — per-case disposition override (w4-brief.md §4f); falls back to the
            // company's invoice_overpay_cancel_policy default when omitted (RefundPostingService).
            'disposition' => ['nullable', 'in:credit,refund_out,apply'],
            // W4.U verify-fix (HIGH): the target invoice for disposition='apply'
            // (w4-brief.md §4f "apply to open invoice") — RefundPostingService::postDisposition()
            // throws without it (RefundPostingService.php ~L831). Cross-checked against the
            // refund's own client + carrying invoice(s) in validateAppliedInvoiceId() below (an
            // `exists:invoices,id` rule alone cannot express "belongs to this client and is
            // actually open").
            'applied_invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            // W4.U verify-fix (LOW): w4-brief.md §4e "(i) always: Dr 5125 / Cr airline payable —
            // when an airline commission clawback amount is entered" — the field the screen was
            // missing entirely (RefundPostingService::postClawback() already supports it).
            'airline_clawback_amount' => ['nullable', 'numeric', 'min:0'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.task_id' => ['required', 'exists:tasks,id'],
            'tasks.*.original_invoice_price' => ['required', 'numeric'],
            'tasks.*.original_task_cost' => ['required', 'numeric'],
            'tasks.*.original_task_profit' => ['required', 'numeric'],
            'tasks.*.refund_fee_to_client' => ['required', 'numeric'],
            'tasks.*.supplier_charge' => ['required', 'numeric'],
            // W4.U §b — editable "supplier net" override (w4-brief.md §4 process decisions);
            // nullable so RefundPostingService::supplierRefundAmount()'s own default (cost -
            // penalty) applies when the operator leaves it blank.
            'tasks.*.supplier_refund_amount' => ['nullable', 'numeric'],
            'tasks.*.new_task_profit' => ['required', 'numeric'],
            'tasks.*.total_refund_to_client' => ['required', 'numeric'],
            'tasks.*.remarks' => ['nullable', 'string'],
            'tasks.*.payment_gateway_option' => ['nullable', 'string'],
            'tasks.*.payment_method' => ['nullable', 'numeric'],
        ]);

        // W4.U §b — "Cross-invoice selection allowed in the picker; the system emits ONE refund
        // document per carrying invoice, grouped by refund_batch_id ... all posted in one DB
        // transaction" (w4-brief.md §4 process decisions). Detect a genuine multi-invoice batch
        // BEFORE the pre-existing single-refund creation path below runs, and hand it to a
        // dedicated, additive method — the single-invoice path (the overwhelming common case, and
        // the one every pre-existing test targets) is left completely untouched below.
        $getInvoiceForGrouping = function ($task) {
            if ($task->status === 'refund' && $task->originalTask) {
                return $task->originalTask->invoiceDetail?->invoice;
            }

            return $task->invoiceDetail?->invoice;
        };
        $tasksForGrouping = collect($validatedData['tasks'])->map(
            fn ($taskData) => Task::with(['invoiceDetail.invoice', 'originalTask.invoiceDetail.invoice'])->findOrFail($taskData['task_id'])
        );
        $distinctInvoiceIds = $tasksForGrouping->map(fn ($t) => $getInvoiceForGrouping($t)?->id)->filter()->unique();

        // W4.U verify-fix (HIGH): validated BEFORE branching into the batch path so both the
        // single-invoice and multi-invoice-batch entry points are covered by one check (see
        // validateAppliedInvoiceId()'s own docblock).
        $appliedInvoiceError = $this->validateAppliedInvoiceId(
            $validatedData['disposition'] ?? null,
            $validatedData['applied_invoice_id'] ?? null,
            $tasksForGrouping->pluck('client_id')->filter()->unique()->all(),
            $distinctInvoiceIds->all()
        );
        if ($appliedInvoiceError !== null) {
            return redirect()->back()->withErrors(['error' => $appliedInvoiceError])->withInput();
        }

        if ($distinctInvoiceIds->count() > 1) {
            return $this->storeRefundBatch($validatedData, $tasksForGrouping, $getInvoiceForGrouping);
        }

        DB::beginTransaction();
        try {
            // ← Changed: Load invoiceDetail directly
            $firstTask = Task::with(['agent.branch', 'invoiceDetail.invoice', 'originalTask.invoiceDetail.invoice'])->findOrFail($validatedData['tasks'][0]['task_id']);

            $getInvoice = function ($task) {
                if ($task->status === 'refund' && $task->originalTask) {
                    // Refund task - get invoice from original task
                    return $task->originalTask->invoiceDetail?->invoice;
                } else {
                    // Non-refund task - get invoice directly
                    return $task->invoiceDetail?->invoice;
                }
            };

            $firstInvoice = $getInvoice($firstTask);

            $refundSequence = RefundSequence::firstOrCreate(['company_id' => $firstTask->company_id], ['current_sequence' => 1]);
            $refundNumber = $this->generateRefundNumber($refundSequence->current_sequence);
            $refundSequence->increment('current_sequence');

            $totalRefundAmount = 0;
            $totalRefundCharge = 0;
            $totalNettRefund  = 0;

            // W4.U verify-fix (MEDIUM): fee_schedule.{type}.override/amount/percent
            // (SettingController::storeAccountingSettings()) were persisted but never read by any
            // posting logic — this is the read side. Mutates $validatedData['tasks'] in place so
            // the RefundDetail-creation loop below (which iterates the same array) picks up the
            // resolved figures automatically; applying it here (before the totals are summed)
            // keeps refunds.total_refund_amount/total_nett_refund consistent with the per-detail
            // rows instead of drifting from whatever the client originally submitted.
            foreach ($validatedData['tasks'] as $idx => $taskData) {
                $taskForFee = Task::find($taskData['task_id']);
                $validatedData['tasks'][$idx] = $taskData = $this->applyRefundFeeSchedule(
                    $taskData,
                    (int) $firstTask->company_id,
                    (string) ($taskForFee->type ?? '')
                );

                $totalRefundAmount += $taskData['refund_fee_to_client'];
                $totalRefundCharge += $taskData['supplier_charge'];
                $totalNettRefund  += $taskData['total_refund_to_client'];
            }

            // W4.R verify-fix (finding #5, MEDIUM): w4-brief.md §4 "Statuses: draft -> approved ->
            // posted -> completed | rejected" is the real ON-path workflow, gated by
            // RefundPolicy::approve()/complete(). A prior build still called handlePaidRefund()
            // (which posts immediately via RefundPostingService::post() on the ON path) from
            // inside THIS method for the paid/partial-refund case — the single most common case at
            // cutover (w4-brief.md Traps) — so the draft/approve gate was never actually reached.
            // Engine ON now stops at 'draft' here; posting only happens via the explicit
            // approve() -> completeProcess() actions. Engine OFF is completely unchanged (legacy
            // synchronous posting, byte parity).
            $engineOn = app(PostingSeam::class)->isEnabledFor((int) $firstTask->company_id);

            $refund = Refund::create([
                'refund_number' => $refundNumber,
                'company_id' => $firstTask->company_id,
                'branch_id' => $firstTask->agent->branch_id,
                'agent_id' => $firstTask->agent_id,
                'invoice_id' => $firstInvoice->id,
                'method' => $validatedData['method'] ?? null,
                'disposition' => $validatedData['disposition'] ?? null,
                // W4.U verify-fix (HIGH/LOW): applied_invoice_id validated above via
                // validateAppliedInvoiceId(); airline_clawback_amount feeds
                // RefundPostingService::postClawback() (w4-brief.md §4e).
                'applied_invoice_id' => $validatedData['applied_invoice_id'] ?? null,
                'airline_clawback_amount' => $validatedData['airline_clawback_amount'] ?? null,
                'remarks' => $validatedData['remarks'] ?? null,
                'remarks_internal' => $validatedData['remarks_internal'] ?? null,
                'reason' => $validatedData['reason'] ?? null,
                'total_refund_amount' => $totalRefundAmount,
                'total_refund_charge' => $totalRefundCharge,
                'total_nett_refund' => $totalNettRefund,
                'status' => $engineOn ? Refund::STATUS_DRAFT : 'processed',
                'refund_date' => $validatedData['date'] ?? now(),
                'created_by' => Auth::user()->id,
            ]);

            foreach ($validatedData['tasks'] as $taskData) {
                // ← Changed: Load invoiceDetail directly
                $task = Task::with(['client', 'agent.branch', 'invoiceDetail.invoice', 'originalTask.invoiceDetail.invoice'])->findOrFail($taskData['task_id']);

                $invoice = $getInvoice($task);
                $paymentStatus = $invoice->status;

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
                    'supplier_refund_amount' => $taskData['supplier_refund_amount'] ?? null,
                    'new_task_profit' => $taskData['new_task_profit'],
                    'total_refund_to_client' => $taskData['total_refund_to_client'],
                    'remarks' => $taskData['remarks'] ?? null,
                ]);
            }

            $invoiceData = null;

            if (in_array($paymentStatus, ['paid', 'partial refund'])) {
                if ($engineOn) {
                    // Left at STATUS_DRAFT (set above) — RefundPolicy::approve() then complete()
                    // are now the only ON-path entry points that actually post via
                    // RefundPostingService (see comment above $engineOn's own assignment).
                    AccountingLog::event('refund_store_draft_deferred', ['refund_id' => $refund->id, 'company_id' => $refund->company_id]);
                } else {
                    $this->handlePaidRefund($refund);
                }
            } elseif ($paymentStatus === 'unpaid') {
                // W4.R verify-fix round 3 (finding #1, HIGH): w4-brief.md §4 process decisions
                // "Unpaid-invoice refund: NO new invoice. The CRN reduces the open item on the
                // SAME carrying invoice ... createRefundInvoiceUnpaid()/createRefundInvoicePartial()
                // are retired on the ON path (legacy closure on OFF)." Same draft-deferral as the
                // paid/partial-refund branch above (see the $engineOn comment there):
                // RefundPostingService::post() is invoice-payment-status-agnostic -- its CRN leg
                // reverses the sale by idempotency key, which reduces AR on the SAME carrying
                // invoice regardless of whether that invoice happens to be paid, partial, or
                // unpaid, and its disposition leg (f) settles whatever `total_refund_to_client`
                // nets out to (which can be zero or negative-owed-by-client, handled generically).
                // No new Invoice/InvoiceDetail row is ever created on this path; posting only
                // happens via the explicit approve() -> completeProcess() actions.
                if ($engineOn) {
                    AccountingLog::event('refund_store_draft_deferred', ['refund_id' => $refund->id, 'company_id' => $refund->company_id]);
                } else {
                    $invoice = $refund->originalInvoice;

                    $refundedTaskIds = $refund->refundDetails
                        ->map(
                            fn($d) => strtolower($d->task?->status) === 'refund'
                                ? $d->task->original_task_id
                                : $d->task_id
                        )
                        ->filter()
                        ->toArray();

                    $remainingTaskTotal = $invoice->invoiceDetails()
                        ->when(!empty($refundedTaskIds), fn($q) => $q->whereNotIn('task_id', $refundedTaskIds))
                        ->sum('task_price');

                    // Deduct any payments already made (edge case: partial payments on an "unpaid" invoice)
                    $totalPaid = $invoice->invoicePartials()->where('status', 'paid')->sum('amount');
                    $availableCredit = max(0.0, $totalPaid - $refund->total_refund_amount);
                    $totalToCollect = max(0.0, $refund->total_refund_amount + $remainingTaskTotal - $availableCredit);

                    Log::info("Unpaid refund invoice calculation", [
                        'refund_id' => $refund->id,
                        'refund_charge' => $refund->total_refund_amount,
                        'remaining_unrefunded_tasks' => $remainingTaskTotal,
                        'total_paid' => $totalPaid,
                        'available_credit' => $availableCredit,
                        'total_to_collect' => $totalToCollect,
                    ]);
                    $unpaidInvoiceResponse = $this->handleUnpaidInvoice($refund, $request, $totalToCollect);
                    $invoiceData = $unpaidInvoiceResponse->getData(true);
                }
            } elseif ($paymentStatus === 'partial') {
                // Same draft-deferral rationale as the 'unpaid' branch immediately above.
                if ($engineOn) {
                    AccountingLog::event('refund_store_draft_deferred', ['refund_id' => $refund->id, 'company_id' => $refund->company_id]);
                } else {
                    $refund->unsetRelation('refundDetails');
                    $refund->load(['refundDetails.task.agent', 'refundDetails.task.client', 'originalInvoice.invoicePartials', 'originalInvoice.invoiceDetails']);
                    Log::info("Refund {$refund->refund_number} reloaded refundDetails with tasks: " . $refund->refundDetails->pluck('task_id')->join(', '));
                    $invoiceData = $this->handlePartialRefund($refund);
                }
            }

            DB::commit();

            if ($request->expectsJson() || $request->wantsJson()) {
                // Paid refunds or partial refunds with no new invoice → redirect to refunds index
                if (in_array($paymentStatus, ['paid', 'partial refund']) || $invoiceData === null) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Refund processed successfully! Credit issued to client.',
                        'refund_number' => $refundNumber,
                        'redirect' => route('refunds.index'),
                    ]);
                }

                // New refund invoice created — redirect to edit page so agent sets payment type
                return response()->json([
                    'success' => true,
                    'message' => 'Refund processed successfully!',
                    'refund_number' => $refundNumber,
                    'invoiceNumber' => $invoiceData['invoiceNumber'],
                    'invoiceId' => $invoiceData['invoiceId'],
                    'redirect' => route('invoice.edit', [
                        'companyId' => $firstTask->company_id,
                        'invoiceNumber' => $invoiceData['invoiceNumber'],
                    ]),
                ]);
            }
            return redirect()->route('refunds.index')->with('success', 'Refund processed successfully!');
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Refund processing failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Refund failed: ' . $e->getMessage()]);
        }
    }

    /**
     * W4.U verify-fix (HIGH): w4-brief.md §4f's third disposition path, "apply to an open
     * invoice", needs a concrete target — RefundPostingService::postDisposition() throws when
     * disposition='apply' and refunds.applied_invoice_id is null (RefundPostingService.php
     * ~L831). Neither the `applied_invoice_id` field nor any way to pick it existed anywhere in
     * store()/update() or the two views before this fix, so selecting "apply" through the screen
     * failed 100% of the time. This is the shared server-side guard for both entry points (called
     * from store() before it branches into the single-invoice vs. batch path, and from update()):
     * confirms the picked invoice exists, belongs to one of THIS refund's own clients, is
     * genuinely open (not fully paid — crediting an already-settled invoice makes no sense), and
     * is not one of the invoice(s) this refund itself is reversing (applying a refund back onto
     * the very invoice being refunded is circular). Returns null when valid (or when disposition
     * isn't 'apply' at all — the field is irrelevant to the other two dispositions), else a
     * user-facing error string.
     */
    private function validateAppliedInvoiceId(
        ?string $disposition,
        mixed $appliedInvoiceId,
        array $clientIds,
        array $excludeInvoiceIds
    ): ?string {
        if ($disposition !== Refund::DISPOSITION_APPLY) {
            return null;
        }

        if (empty($appliedInvoiceId)) {
            return 'Disposition "Apply to another open invoice" requires selecting the target invoice.';
        }

        $invoice = Invoice::find($appliedInvoiceId);

        if ($invoice === null) {
            return 'The selected invoice to apply the refund against could not be found.';
        }

        if (in_array((int) $invoice->id, array_map('intval', $excludeInvoiceIds), true)) {
            return 'The invoice being refunded cannot also be the invoice the credit is applied to.';
        }

        if (! in_array((int) $invoice->client_id, array_map('intval', $clientIds), true)) {
            return "The selected invoice does not belong to this refund's client.";
        }

        if (! in_array(strtolower((string) $invoice->status), ['unpaid', 'partial', 'partial refund'], true)) {
            return 'The selected invoice is not open (it must be unpaid or partially paid) to apply a refund credit against it.';
        }

        return null;
    }

    /**
     * W4.U verify-fix (MEDIUM): `fee_schedule.{type}.override` / `.amount` / `.percent`
     * (SettingController::storeAccountingSettings(), the "Accounting" settings tab) were
     * persisted but read back only by SettingController::getAccountingSettings() itself — no
     * posting/business logic anywhere ever consumed them, so the setting could never honour the
     * next posting as w4-brief.md's W4.U decision requires. This is the read side.
     *
     * 'free' forces the fee to zero (staff cannot charge a fee the company has waived for that
     * service type, regardless of what was typed into the screen). Otherwise, once the company
     * has configured a nonzero percent or flat amount for the service type, THAT figure becomes
     * the authoritative fee (percent takes priority over a flat amount when both are set). A
     * company that has configured neither (the shipped default: amount=0, percent=0,
     * override='needs_approval') keeps today's behaviour unchanged — whatever the staff member
     * typed is honoured verbatim, so this fix cannot regress any existing refund flow for a
     * company that has never touched the Accounting settings tab.
     *
     * `total_refund_to_client` is adjusted by the same delta so the client-net figure stays
     * internally consistent (w4-brief.md §4: "client net = original_invoice_price − penalty
     * recharge − fee" — silently changing the fee without adjusting the net would corrupt the
     * exact quantity RefundPostingService::postDisposition() posts to the client's ledger).
     * Idempotent: re-applying to an already-resolved $taskData is a no-op (the resolved fee
     * recomputes identically, so the delta is zero the second time).
     */
    private function applyRefundFeeSchedule(array $taskData, int $companyId, string $serviceType): array
    {
        if ($serviceType === '') {
            return $taskData;
        }

        $submittedFee = round((float) ($taskData['refund_fee_to_client'] ?? 0), 3);
        $override = (string) Setting::getByKey($companyId, "accounting.refund.fee_schedule.{$serviceType}.override", 'needs_approval');

        if ($override === 'free') {
            $resolvedFee = 0.0;
        } else {
            $percent = (float) Setting::getByKey($companyId, "accounting.refund.fee_schedule.{$serviceType}.percent", 0);
            $amount = (float) Setting::getByKey($companyId, "accounting.refund.fee_schedule.{$serviceType}.amount", 0);
            $sellAmount = (float) ($taskData['original_invoice_price'] ?? 0);

            if ($percent > 0) {
                $resolvedFee = round($sellAmount * $percent / 100, 3);
            } elseif ($amount > 0) {
                $resolvedFee = round($amount, 3);
            } else {
                $resolvedFee = $submittedFee;
            }
        }

        $delta = round($resolvedFee - $submittedFee, 3);

        if ($delta === 0.0) {
            return $taskData;
        }

        $taskData['refund_fee_to_client'] = $resolvedFee;
        $taskData['total_refund_to_client'] = round((float) ($taskData['total_refund_to_client'] ?? 0) - $delta, 3);

        return $taskData;
    }

    /**
     * W4.U §b — one Refund document PER carrying invoice, sharing a single refund_batch_id, all
     * created in ONE DB transaction (w4-brief.md §4 process decisions: "the system emits ONE
     * refund document per carrying invoice, grouped by refund_batch_id ... all posted in one DB
     * transaction"). Reached only when store()'s own grouping detects tasks spanning more than
     * one invoice — the single-invoice path above is completely untouched by this method.
     *
     * Engine-ON only: this is a genuinely new document-grouping capability with no legacy
     * analogue to fall back to (the pre-existing "different invoices" restriction is KEPT,
     * unchanged, on the OFF path — see the guard below). Restricted to invoices already
     * paid/partial-refund — RefundPostingService's draft-deferred flow is what actually posts
     * these once approved; the legacy unpaid/partial re-invoice branches are not something this
     * batch path attempts to orchestrate across several invoices at once.
     *
     * Every created Refund is left at STATUS_DRAFT — posting only ever happens via the existing
     * approve() -> completeProcess() actions, per RefundPostingService::post()'s own status guard.
     */
    private function storeRefundBatch(array $validatedData, \Illuminate\Support\Collection $loadedTasks, \Closure $getInvoice): RedirectResponse
    {
        $firstTask = $loadedTasks->first();
        $companyId = (int) $firstTask->company_id;
        $engineOn = app(PostingSeam::class)->isEnabledFor($companyId);

        if (! $engineOn) {
            return back()->withErrors([
                'error' => 'Refund cannot include tasks from different original invoices. Please process each invoice refund separately.',
            ]);
        }

        $groups = $loadedTasks->groupBy(fn ($t) => $getInvoice($t)?->id);

        foreach ($groups as $invoiceId => $tasksInGroup) {
            $invoice = $getInvoice($tasksInGroup->first());

            if ($invoiceId === null || $invoice === null) {
                return back()->withErrors(['error' => 'Every task in a refund batch must resolve to an invoice.']);
            }

            if (! in_array(strtolower($invoice->status ?? ''), ['paid', 'partial refund'], true)) {
                return back()->withErrors([
                    'error' => "Invoice #{$invoice->invoice_number} is not fully paid — cross-invoice refund batches only support paid invoices. Process it separately.",
                ]);
            }
        }

        $tasksByData = collect($validatedData['tasks'])->keyBy(fn ($t) => (int) $t['task_id']);

        DB::beginTransaction();
        try {
            $createdRefunds = collect();

            foreach ($groups as $invoiceId => $tasksInGroup) {
                $invoice = $getInvoice($tasksInGroup->first());
                $groupLeadTask = $tasksInGroup->first();

                $refundSequence = RefundSequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
                $refundNumber = $this->generateRefundNumber($refundSequence->current_sequence);
                $refundSequence->increment('current_sequence');

                $totals = ['fee' => 0.0, 'charge' => 0.0, 'nett' => 0.0];
                $detailRows = [];

                foreach ($tasksInGroup as $task) {
                    $taskData = $tasksByData->get((int) $task->id);
                    if ($taskData === null) {
                        continue;
                    }

                    // W4.U verify-fix (MEDIUM) — see applyRefundFeeSchedule()'s own docblock.
                    $taskData = $this->applyRefundFeeSchedule($taskData, $companyId, (string) ($task->type ?? ''));

                    $totals['fee'] += (float) $taskData['refund_fee_to_client'];
                    $totals['charge'] += (float) $taskData['supplier_charge'];
                    $totals['nett'] += (float) $taskData['total_refund_to_client'];
                    $detailRows[] = ['task' => $task, 'data' => $taskData];
                }

                $refund = Refund::create([
                    'refund_number' => $refundNumber,
                    'company_id' => $companyId,
                    'branch_id' => $groupLeadTask->agent->branch_id,
                    'agent_id' => $groupLeadTask->agent_id,
                    'invoice_id' => $invoice->id,
                    'method' => $validatedData['method'] ?? null,
                    'disposition' => $validatedData['disposition'] ?? null,
                    // W4.U verify-fix (HIGH/LOW) — same fields as the single-invoice path above,
                    // applied identically to every refund this batch creates (matching the
                    // pre-existing convention for method/disposition on this same call).
                    'applied_invoice_id' => $validatedData['applied_invoice_id'] ?? null,
                    'airline_clawback_amount' => $validatedData['airline_clawback_amount'] ?? null,
                    'remarks' => $validatedData['remarks'] ?? null,
                    'remarks_internal' => $validatedData['remarks_internal'] ?? null,
                    'reason' => $validatedData['reason'] ?? null,
                    'total_refund_amount' => $totals['fee'],
                    'total_refund_charge' => $totals['charge'],
                    'total_nett_refund' => $totals['nett'],
                    'status' => Refund::STATUS_DRAFT,
                    'refund_date' => $validatedData['date'] ?? now(),
                    'created_by' => Auth::user()->id,
                ]);

                foreach ($detailRows as $row) {
                    RefundDetail::create([
                        'refund_id' => $refund->id,
                        'task_id' => $row['task']->id,
                        'client_id' => $row['task']->client_id,
                        'task_description' => $row['task']->reference,
                        'original_invoice_price' => $row['data']['original_invoice_price'],
                        'original_task_cost' => $row['data']['original_task_cost'],
                        'original_task_profit' => $row['data']['original_task_profit'],
                        'refund_fee_to_client' => $row['data']['refund_fee_to_client'],
                        'supplier_charge' => $row['data']['supplier_charge'],
                        'supplier_refund_amount' => $row['data']['supplier_refund_amount'] ?? null,
                        'new_task_profit' => $row['data']['new_task_profit'],
                        'total_refund_to_client' => $row['data']['total_refund_to_client'],
                        'remarks' => $row['data']['remarks'] ?? null,
                    ]);
                }

                $createdRefunds->push($refund);
                AccountingLog::event('refund_store_draft_deferred', ['refund_id' => $refund->id, 'company_id' => $refund->company_id, 'batch' => true]);
            }

            // refund_batch_id anchors on the FIRST created refund's own id — a lightweight batch
            // key needing no separate sequence/model (w4-brief.md §4: "grouped by refund_batch_id
            // (nullable column)").
            $batchId = $createdRefunds->first()->id;
            Refund::whereIn('id', $createdRefunds->pluck('id'))->update(['refund_batch_id' => $batchId]);

            DB::commit();

            return redirect()->route('refunds.index')->with(
                'success',
                'Refund batch created: '.$createdRefunds->pluck('refund_number')->join(', ').'.'
            );
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Refund batch creation failed: '.$e->getMessage());

            return back()->withErrors(['error' => 'Refund batch failed: '.$e->getMessage()]);
        }
    }

    /**
     * W4.R bundled fix (w4-brief.md §5 "kill the one-sided JE in RefundController::
     * handlePaidRefund"; ct-refund-map.md §3 BUG-C1 — the pre-existing body writes ONE debit-only
     * JournalEntry line (no offsetting credit at all) via raw `JournalEntry::create()`, plus
     * silently overwrites `refunds.method` to 'Credit' regardless of what was actually chosen
     * (w4-brief.md Traps: "the ON path must honour it"). Engine ON routes through
     * {@see RefundPostingService::post()} instead (a real balanced document set); engine OFF keeps
     * the pre-existing body completely unchanged, including its `method` overwrite, for OFF-path
     * parity (w4-brief.md Traps: "OFF path parity keeps the legacy overwrite").
     */
    public function handlePaidRefund(Refund $refund, ?float $overrideAmount = null)
    {
        if (app(PostingSeam::class)->isEnabledFor((int) $refund->company_id)) {
            app(RefundPostingService::class)->post($refund, Auth::id());

            return;
        }

        $firstDetail = $refund->refundDetails()->with('task.agent', 'task.client')->firstOrFail();
        $task = $firstDetail->task;
        $agent = $task->agent;
        $refundAmount = $overrideAmount ?? $refund->total_nett_refund;

        $refund->update([
            'method' => 'Credit',
        ]);

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
                'name' => 'Commissions (Agents)',
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
        Log::info('[HANDLEUNPAIDINVOICE] reached here with data', [
            'data' => $request->all()
        ]);

        $refund->load(['refundDetails.task.agent', 'refundDetails.task.client', 'originalInvoice', 'invoice']);
        $refundCharge = $overrideAmount ?? $refund->total_nett_refund;

        $unpaidInvoiceResponse = $this->createRefundInvoiceUnpaid(
            $refund,
            $refundCharge,
        );

        $data = $unpaidInvoiceResponse->getData(true);
        $refund->update(['refund_invoice_id' => $data['invoiceId'] ?? null]);

        // Return the response so it can be used by the caller
        return $unpaidInvoiceResponse;
    }

    private function handlePartialRefund(Refund $refund): ?array
    {
        Log::info("Refund {$refund->refund_number} refundDetails tasks loaded: " . $refund->refundDetails->pluck('task_id')->join(', '));

        $invoice = $refund->originalInvoice;
        $totalPaid = $invoice->invoicePartials()->where('status', 'paid')->sum('amount');
        $refundCharge = $refund->total_refund_amount;

        $refundedTaskIds = $refund->refundDetails->map(fn($detail) => $detail->task?->originalTask?->id ?? $detail->task_id)->filter()->toArray();
        Log::info("Refund {$refund->refund_number} refundDetails original task IDs: " . implode(', ', $refundedTaskIds));
        if (empty($refundedTaskIds)) {
            Log::warning("Refund {$refund->refund_number} has no valid refundDetails task_id; assuming no tasks refunded yet.");
        }

        $remainingTaskTotal = $invoice->invoiceDetails()
            ->when(!empty($refundedTaskIds), fn($q) => $q->whereNotIn('task_id', $refundedTaskIds))
            ->sum('task_price');

        Log::info("Partial refund for {$invoice->invoice_number}: Paid={$totalPaid}, Charge={$refundCharge}, RemainingTasks={$remainingTaskTotal}");

        // Case 1 — Paid < Refund Charge: agent still owes the shortfall plus all remaining tasks
        if ($totalPaid < $refundCharge) {
            $amountOwed = $remainingTaskTotal + ($refundCharge - $totalPaid);
            $response = $this->handleUnpaidInvoice($refund, request(), $amountOwed);
            Log::info("Refund {$refund->refund_number} handled as unpaid (collect balance). AmountOwed={$amountOwed}");
            return $response->getData(true);
        }

        // Case 2 — Paid > Refund Charge
        if ($totalPaid > $refundCharge) {
            $availableAfterRefund = $totalPaid - $refundCharge;

            // Sub-case A: credit available doesn't cover remaining tasks — collect the shortfall
            if ($availableAfterRefund < $remainingTaskTotal) {
                $amountOwed = $remainingTaskTotal - $availableAfterRefund;

                $this->handleRefundCOA($refund, $amountOwed, $refundCharge);
                $response = $this->createRefundInvoicePartial($refund, $amountOwed);

                Log::info("Refund {$refund->refund_number} requires collection of {$amountOwed}. AvailableCredit={$availableAfterRefund}, RemainingTasks={$remainingTaskTotal}");
                return $response->getData(true);
            }

            // Sub-case B: excess credit after covering remaining tasks — refund to client
            $creditAmount = $availableAfterRefund - $remainingTaskTotal;

            $invoice->update(['status' => InvoiceStatus::REFUNDED->value]);
            $refund->update(['total_nett_refund' => $creditAmount]);
            $this->handlePaidRefund($refund, $creditAmount);

            Log::info("Refund {$refund->refund_number} credited {$creditAmount} to client after refunding invoice.");
            return null;
        }

        // Case 3 — Paid == Refund Charge exactly: remaining tasks still owe their full amount
        if ($remainingTaskTotal > 0) {
            $response = $this->handleUnpaidInvoice($refund, request(), $remainingTaskTotal);
            Log::info("Refund {$refund->refund_number} balanced on refund charge; collecting remaining tasks={$remainingTaskTotal}.");
            return $response->getData(true);
        }

        // Case 4 — Everything balanced perfectly
        $refund->update(['status' => 'completed']);
        Log::info("Refund {$refund->refund_number} completed, balanced perfectly.");
        return null;
    }

    /**
     * W4.R verify-fix round 3 (finding #1, HIGH): this method's raw `Transaction::create()`/
     * `JournalEntry::create()` writes, via `Account::where('name', 'like', ...)` lookups, are
     * legacy OFF-path-only behaviour (w4-brief.md hard rule: "never resolve accounts by name in
     * NEW/ON-path code"). It is reached ONLY from {@see handlePartialRefund()}'s Case-2-sub-case-A,
     * which is itself now reached ONLY from `store()`'s/`update()`'s `'partial'` branch when the
     * engine is OFF (see those methods' own `$engineOn` gates above `handlePartialRefund()`'s only
     * call sites) — engine ON defers the whole refund to `draft` before ever calling
     * `handlePartialRefund()`, so this method is structurally unreachable on the ON path. The
     * explicit guard below is a second, defence-in-depth check (never trust a single call-site
     * gate for a raw-ledger-write method) and makes the "OFF-only" requirement grep-provable: the
     * entire legacy body lives inside `$legacy`, unchanged from `git show HEAD`, for OFF-path byte
     * parity.
     */
    private function handleRefundCOA(Refund $refund, float $amountOwed, float $refundCharge)
    {
        if (app(PostingSeam::class)->isEnabledFor((int) $refund->company_id)) {
            throw new \RuntimeException(
                "RefundController::handleRefundCOA(): refund #{$refund->id}'s company has the posting "
                .'engine ON -- this legacy raw-write path must never be reached on the ON path '
                .'(w4-brief.md §4: engine ON routes unpaid/partial refunds through '
                .'RefundPostingService instead, never this method). This indicates a caller '
                .'regression, not a normal runtime condition.'
            );
        }

        $legacy = function () use ($refund, $amountOwed, $refundCharge) {
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
        };

        $legacy();
    }

    /**
     * W4.R bundled fix (w4-brief.md §5 "refunds.show route -> auth OR signed URL"). Mirrors
     * InvoiceController's own isPublicInvoiceRequest()/authorizeStaffInvoiceAccess() split
     * exactly (see that controller's own docblocks): the SIGNED '.public' route (validated by
     * the `signed` route middleware before this method ever runs) needs no further authorization
     * check here; the authenticated internal route is gated by RefundPolicy::view().
     */
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

        $isPublicRefundRequest = $this->isPublicRefundRequest();

        if (! $isPublicRefundRequest) {
            Gate::authorize('view', $refund);
        }

        // W4.U §b — the staff-only console block (status timeline, linked-docs panel, disposition/
        // method info) never renders on the public/signed voucher variant, so none of this is
        // computed for that request at all (defense in depth, not merely a template @if).
        $linkedDocuments = null;
        $batchSiblings = collect();
        $accountingSettings = [];

        if (! $isPublicRefundRequest) {
            $linkedDocuments = $this->resolveLinkedDocuments($refund);

            if ($refund->refund_batch_id !== null) {
                $batchSiblings = Refund::where('refund_batch_id', $refund->refund_batch_id)
                    ->where('id', '!=', $refund->id)
                    ->with('originalInvoice')
                    ->get();
            }

            $accountingSettings = [
                'invoice_overpay_cancel_policy' => Setting::getByKey((int) $companyId, 'accounting.refund.invoice_overpay_cancel_policy', 'credit'),
                'refund_send_on_post' => filter_var(Setting::getByKey((int) $companyId, 'accounting.refund.refund_send_on_post', 'true'), FILTER_VALIDATE_BOOLEAN),
                'agent_unearn_notice' => filter_var(Setting::getByKey((int) $companyId, 'accounting.refund.agent_unearn_notice', 'true'), FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return view('refunds.show', compact('refund', 'isPublicRefundRequest', 'linkedDocuments', 'batchSiblings', 'accountingSettings'));
    }

    /**
     * W4.U §b "Linked-docs panel ... resolve these via the refund's doc_id/idempotency-key links
     * the engine already creates in W4.R, not by re-deriving them from descriptions." Mirrors
     * {@see \App\Services\Accounting\RefundPostingService}'s own idempotency-key conventions
     * EXACTLY (structural lookup only, never `description`/`LIKE`) -- read-only, posts nothing.
     *
     * @return array{crn: array<int,Transaction>, recharge: ?Transaction,
     *               supplier_credit: array<int,Transaction>, commission_unearn: array<int,Transaction>,
     *               clawback: ?Transaction, disposition: ?Transaction}
     */
    private function resolveLinkedDocuments(Refund $refund): array
    {
        $companyId = (int) $refund->company_id;
        $byKey = fn (string $key) => Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('company_id', $companyId)
            ->where('idempotency_key', $key)
            ->first();
        $reversalOf = fn (?Transaction $original) => $original === null ? null : Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('reversal_of_transaction_id', $original->id)
            ->first();

        $crn = [];
        $commissionUnearn = [];
        $supplierCredit = [];

        foreach ($refund->refundDetails as $detail) {
            $invoiceDetail = $detail->task?->invoiceDetail;

            if ($invoiceDetail !== null) {
                $sale = $byKey('invoice-detail:'.$invoiceDetail->id.':sale');
                $crnDoc = $sale !== null
                    ? $reversalOf($sale)
                    : $byKey('refund:'.$refund->id.':crn-legacy:'.$detail->id);
                if ($crnDoc !== null) {
                    $crn[] = $crnDoc;
                }

                $commissionOriginal = $byKey('invoice-detail:'.$invoiceDetail->id.':agent-commission');
                $unearnDoc = $reversalOf($commissionOriginal);
                if ($unearnDoc !== null) {
                    $commissionUnearn[] = $unearnDoc;
                }
            }

            $supplierCreditDoc = $byKey('refund:'.$refund->id.':supplier-credit:'.$detail->id);
            if ($supplierCreditDoc !== null) {
                $supplierCredit[] = $supplierCreditDoc;
            }
        }

        return [
            'crn' => $crn,
            'recharge' => $byKey('refund:'.$refund->id.':recharge'),
            'supplier_credit' => $supplierCredit,
            'commission_unearn' => $commissionUnearn,
            'clawback' => $byKey('refund:'.$refund->id.':clawback'),
            'disposition' => $byKey('refund:'.$refund->id.':disposition'),
        ];
    }

    /** See show()'s own docblock. Mirrors InvoiceController::isPublicInvoiceRequest() exactly. */
    private function isPublicRefundRequest(): bool
    {
        return str_ends_with((string) request()->route()?->getName(), '.public');
    }

    public function edit(Refund $refund)
    {
        $refund->load([
            'refundDetails.task.invoiceDetail.invoice',
            'refundDetails.task.originalTask.invoiceDetail.invoice',
            'refundDetails.task.originalTask.flightDetails',
            'refundDetails.task.originalTask.hotelDetails.hotel',
            'refundDetails.task.originalTask.visaDetails',
            'refundDetails.task.originalTask.insuranceDetails',
            'refundDetails.task.flightDetails',
            'refundDetails.task.hotelDetails.hotel',
            'refundDetails.task.visaDetails',
            'refundDetails.task.insuranceDetails',
            'refundDetails.task.agent.branch.company',
            'refundDetails.task.client',
        ]);

        if ($refund->refundDetails->isEmpty()) {
            return back()->withErrors(['error' => 'No refund details found for this refund.']);
        }

        // Process each refund detail to add computed properties
        $refund->refundDetails->transform(function ($detail) {
            $task = $detail->task;
            $isRefundStatus = strtolower($task->status) === 'refund';

            $sourceTask = $isRefundStatus ? $task->originalTask : $task;
            $invoiceDetail = $isRefundStatus ? $task->originalTask?->invoiceDetail : $task->invoiceDetail;

            // Attach computed properties to the detail
            $detail->computed_source_task = $sourceTask;
            $detail->computed_invoice_detail = $invoiceDetail;
            $detail->computed_invoice = $invoiceDetail?->invoice;
            $detail->computed_invoice_status = $invoiceDetail?->invoice?->status;
            $detail->is_refund_status = $isRefundStatus;

            return $detail;
        });

        $firstDetail = $refund->refundDetails->first();
        $firstTask = $firstDetail->task;
        $firstInvoice = $firstDetail->computed_invoice;
        $isPaidInvoice = in_array(strtolower($firstInvoice?->status ?? ''), ['paid', 'partial refund', 'refunded']);

        $paymentGateways = Charge::where('company_id', $firstTask->agent->branch->company_id)
            ->where('is_active', true)->get();

        $paymentMethods = PaymentMethod::where('company_id', $firstTask->agent->branch->company_id)
            ->where('is_active', true)->get();

        // W4.U verify-fix (HIGH): same candidate-invoice list as create() — see the docblock on
        // that method's own $openInvoices for the exclusion rules.
        $refundClientIds = $refund->refundDetails->pluck('client_id')->filter()->unique()->all();
        $excludedInvoiceIdsForApply = [(int) $refund->invoice_id];
        $openInvoices = empty($refundClientIds) ? collect() : Invoice::whereIn('client_id', $refundClientIds)
            ->whereIn('status', ['unpaid', 'partial', 'partial refund'])
            ->whereNotIn('id', $excludedInvoiceIdsForApply)
            ->orderByDesc('id')
            ->get(['id', 'invoice_number', 'status', 'amount', 'client_id']);

        return view('refunds.edit', [
            'refund' => $refund,
            'firstTask' => $firstTask,
            'firstInvoice' => $firstInvoice,
            'isPaidInvoice' => $isPaidInvoice,
            'paymentGateways' => $paymentGateways,
            'paymentMethods' => $paymentMethods,
            'openInvoices' => $openInvoices,
        ]);
    }

    /**
     * W4.R bundled fix — RefundPolicy::update() enforced (was entirely unauthorized before this
     * fix, ct-refund-map.md §6). Note RefundPolicy::update() itself refuses once
     * `$refund->status !== 'draft'` — the `status === 'completed'` short-circuit immediately below
     * predates this fix and is kept for its distinct, more specific user-facing message; the
     * policy check is the actual enforcement boundary now.
     *
     * W4.R verify-fix round 2 (finding A, HIGH): the 'paid' branch below no longer calls
     * handlePaidRefund() directly on the ON path — see the comment on that branch — because doing
     * so made every ON-path call to this method on a paid-invoice refund unconditionally fail
     * (RefundPolicy::update()'s own status gate and RefundPostingService::post()'s status guard
     * were mutually exclusive).
     */
    public function update(Request $request, Task $task, Refund $refund)
    {
        Gate::authorize('update', $refund);

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
            // W4.U §b — same per-case disposition override as store() (w4-brief.md §4f).
            'disposition' => ['nullable', 'in:credit,refund_out,apply'],
            // W4.U verify-fix (HIGH/LOW) — same fields, same rules, as store() above.
            'applied_invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'airline_clawback_amount' => ['nullable', 'numeric', 'min:0'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.task_id' => ['required', 'exists:tasks,id'],
            'tasks.*.original_invoice_price' => ['required', 'numeric'],
            'tasks.*.original_task_cost' => ['required', 'numeric'],
            'tasks.*.original_task_profit' => ['required', 'numeric'],
            'tasks.*.refund_fee_to_client' => ['required', 'numeric'],
            'tasks.*.supplier_charge' => ['required', 'numeric'],
            'tasks.*.supplier_refund_amount' => ['nullable', 'numeric'],
            'tasks.*.new_task_profit' => ['required', 'numeric'],
            'tasks.*.total_refund_to_client' => ['required', 'numeric'],
            'tasks.*.remarks' => ['nullable', 'string'],
        ]);

        // W4.U verify-fix (HIGH): same guard as store() — see validateAppliedInvoiceId()'s own
        // docblock. $refund->invoice_id is the single carrying invoice this refund already
        // targets (update() never re-groups across invoices the way store() does).
        $appliedInvoiceError = $this->validateAppliedInvoiceId(
            $validatedData['disposition'] ?? $refund->disposition,
            $validatedData['applied_invoice_id'] ?? $refund->applied_invoice_id,
            Task::whereIn('id', collect($validatedData['tasks'])->pluck('task_id'))->pluck('client_id')->filter()->unique()->all(),
            [(int) $refund->invoice_id]
        );
        if ($appliedInvoiceError !== null) {
            return redirect()->back()->withErrors(['error' => $appliedInvoiceError])->withInput();
        }

        DB::beginTransaction();
        try {
            $this->cleanupExistingRefundRecords($refund);

            $companyId = (int) $refund->company_id;

            // W4.U verify-fix (MEDIUM) — see applyRefundFeeSchedule()'s own docblock. Applied
            // BEFORE the totals below are summed so refunds.total_refund_amount/total_nett_refund
            // stay consistent with the resolved per-detail figures.
            foreach ($validatedData['tasks'] as $idx => $taskData) {
                $taskForFee = Task::find($taskData['task_id']);
                $validatedData['tasks'][$idx] = $this->applyRefundFeeSchedule(
                    $taskData,
                    $companyId,
                    (string) ($taskForFee->type ?? '')
                );
            }

            $totalRefundAmount = collect($validatedData['tasks'])->sum('refund_fee_to_client');
            $totalRefundCharge = collect($validatedData['tasks'])->sum('supplier_charge');
            $totalNettRefund = collect($validatedData['tasks'])->sum('total_refund_to_client');

            $refund->update([
                'refund_date' => $validatedData['date'],
                'method' => $validatedData['method'] ?? null,
                'disposition' => $validatedData['disposition'] ?? $refund->disposition,
                'applied_invoice_id' => array_key_exists('applied_invoice_id', $validatedData) ? $validatedData['applied_invoice_id'] : $refund->applied_invoice_id,
                'airline_clawback_amount' => array_key_exists('airline_clawback_amount', $validatedData) ? $validatedData['airline_clawback_amount'] : $refund->airline_clawback_amount,
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
                        'supplier_refund_amount' => $taskData['supplier_refund_amount'] ?? null,
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
                        'supplier_refund_amount' => $taskData['supplier_refund_amount'] ?? null,
                        'new_task_profit' => $taskData['new_task_profit'],
                        'total_refund_to_client' => $taskData['total_refund_to_client'],
                        'remarks' => $taskData['remarks'] ?? null,
                    ]);
                }
            }

            $invoice = $refund->originalInvoice;
            $paymentStatus = strtolower($invoice?->status ?? 'unpaid');

            // W4.R verify-fix round 2 (finding A, HIGH): this branch used to call
            // handlePaidRefund() unconditionally, which — on the ON path — calls
            // RefundPostingService::post() directly. RefundPolicy::update() only ever permits
            // reaching this method while $refund->status is still 'draft' (see
            // RefundPolicy::MUTABLE_STATUSES), but post()'s own status guard refuses anything not
            // yet 'approved'/'posted'/'completed'/'processed' — so every ON-path call to update()
            // on a paid-invoice refund threw and rolled back, 100% of the time (not a narrow edge
            // case: RefundPolicy::update() structurally never allows any other status here).
            // Fixed the same way store() was fixed above (see that method's own comment): on the
            // ON path, leave the refund at 'draft' and defer posting entirely to the explicit
            // approve() -> completeProcess() actions; only the OFF path still posts synchronously
            // here, unchanged, for legacy byte parity.
            $engineOn = app(PostingSeam::class)->isEnabledFor((int) $refund->company_id);

            if ($paymentStatus === 'paid') {
                if ($engineOn) {
                    AccountingLog::event('refund_update_draft_deferred', ['refund_id' => $refund->id, 'company_id' => $refund->company_id]);
                } else {
                    $this->handlePaidRefund($refund);
                }
            } elseif ($paymentStatus === 'unpaid') {
                // W4.R verify-fix round 3 (finding #1, HIGH) — same draft-deferral rationale as
                // the 'paid' branch and store()'s own 'unpaid'/'partial' branches above: engine ON
                // never calls the legacy re-invoice path here (w4-brief.md §4: "NO new invoice ...
                // retired on the ON path").
                if ($engineOn) {
                    AccountingLog::event('refund_update_draft_deferred', ['refund_id' => $refund->id, 'company_id' => $refund->company_id]);
                } else {
                    $refundedTaskIds = $refund->refundDetails
                        ->map(fn($d) => $d->task?->originalTask?->id ?? $d->task_id)
                        ->filter()
                        ->toArray();

                    $remainingTaskTotal = $invoice?->invoiceDetails()
                        ->when(!empty($refundedTaskIds), fn($q) => $q->whereNotIn('task_id', $refundedTaskIds))
                        ->sum('task_price');

                    $totalToCollect = $refund->total_nett_refund + $remainingTaskTotal;
                    $this->handleUnpaidInvoice($refund, $request, $totalToCollect);
                }
            } elseif ($paymentStatus === 'partial') {
                if ($engineOn) {
                    AccountingLog::event('refund_update_draft_deferred', ['refund_id' => $refund->id, 'company_id' => $refund->company_id]);
                } else {
                    $this->handlePartialRefund($refund);
                }
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

    /**
     * W4.R (w4-brief.md §4 "Statuses: draft -> approved -> posted -> completed | rejected" +
     * §5 bundled fix "RefundPolicy full abilities ... enforce them"). draft -> approved. Gated by
     * RefundPolicy::approve() (refuses once the refund has left the mutable/draft state, and by
     * role otherwise).
     */
    public function approve(Refund $refund)
    {
        Gate::authorize('approve', $refund);

        $statusBefore = $refund->status;

        $refund->forceFill([
            'status' => Refund::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ])->save();

        // P2.5.F writer (b): "every Gate::authorize guarded mutation in accounting controllers ...
        // write before/after." This action is also one of the 15 accounting.* events, but is
        // handled here directly (with a real before/after status pair) rather than through
        // AccountingLog::event()'s generic path — see that class's own docblock for why.
        AccountingLog::write(
            action: 'approve',
            companyId: (int) $refund->company_id,
            subjectType: 'refund',
            subjectId: (int) $refund->id,
            before: ['status' => $statusBefore],
            after: ['status' => Refund::STATUS_APPROVED],
            actorId: Auth::id(),
        );

        Log::info('accounting.refund_approved', ['refund_id' => $refund->id, 'user_id' => Auth::id()]);

        return redirect()->route('refunds.index')->with('success', 'Refund approved.');
    }

    /**
     * draft -> rejected (void the draft — never delete, w4-brief.md §4 process decisions:
     * "rejected = void the draft, never delete"). Gated by RefundPolicy::reject().
     */
    public function reject(Refund $refund)
    {
        Gate::authorize('reject', $refund);

        $statusBefore = $refund->status;

        $refund->forceFill([
            'status' => Refund::STATUS_REJECTED,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
        ])->save();

        AccountingLog::write(
            action: 'reject',
            companyId: (int) $refund->company_id,
            subjectType: 'refund',
            subjectId: (int) $refund->id,
            before: ['status' => $statusBefore],
            after: ['status' => Refund::STATUS_REJECTED],
            actorId: Auth::id(),
        );

        Log::info('accounting.refund_rejected', ['refund_id' => $refund->id, 'user_id' => Auth::id()]);

        return redirect()->route('refunds.index')->with('success', 'Refund rejected.');
    }

    /**
     * Void a completed/processed refund. Admin + accountant only.
     */
    public function void(Refund $refund): RedirectResponse
    {
        $user = Auth::user();
        if (!$user || !$user->hasAnyRole(['admin', 'accountant'])) {
            abort(403, 'Only admin or accountant can void refunds.');
        }

        // Tenant isolation (merge 2026-09-04): route-model binding on {refund} carries no company
        // scope. Same-company or unscoped admin, mirroring PaymentController::assertSameCompanyOrUnscopedAdmin().
        $actingCompanyId = getCompanyId($user);
        abort_unless(
            ((int) $user->role_id === Role::ADMIN && ! $actingCompanyId)
                || (int) $refund->company_id === (int) $actingCompanyId,
            403,
            'Unauthorized action.'
        );

        // Engine guard (merge 2026-09-04): this method hard-deletes LEGACY journal rows. Once the
        // posting engine owns this company's ledger, a void must be a reversal document
        // (RefundPostingService), never a delete — refuse rather than corrupt.
        abort_if(
            app(\App\Services\Accounting\PostingSeam::class)->isEnabledFor((int) $refund->company_id),
            409,
            'Void is not available for a company on the posting engine; use Reject or a reversal.'
        );

        if ($refund->status === 'voided') {
            return back()->with('error', 'Refund is already voided.');
        }

        $totalUsed = abs((float) Credit::where('refund_id', $refund->id)
            ->where('type', Credit::INVOICE)
            ->sum('amount'));

        if ($totalUsed > 0) {
            return back()->with(
                'error',
                "Cannot void: KWD " . number_format($totalUsed, 3) .
                " of this refund's credit has been applied to invoices. " .
                "Detach the credit from those invoices first, then retry."
            );
        }

        try {
            DB::beginTransaction();

            $jeCount = JournalEntry::where('type', 'refund')
                ->where('voucher_number', (string) $refund->id)
                ->delete();

            $creditCount = Credit::where('refund_id', $refund->id)->delete();

            $invoice = $refund->originalInvoice;
            if ($invoice && in_array($invoice->status, [InvoiceStatus::REFUNDED->value, InvoiceStatus::PARTIAL_REFUND->value])) {
                $otherActiveRefunds = Refund::where('invoice_id', $invoice->id)
                    ->where('id', '!=', $refund->id)
                    ->where('status', '!=', 'voided')
                    ->count();
                if ($otherActiveRefunds === 0) {
                    $invoice->update(['status' => InvoiceStatus::PAID->value]);
                }
            }

            $refund->refundDetails()->delete();
            $refund->update(['status' => 'voided', 'updated_by' => $user->id]);
            $refund->delete();

            DB::commit();

            Log::info('Refund voided', [
                'refund_id' => $refund->id,
                'refund_number' => $refund->refund_number,
                'voided_by' => $user->id,
                'journal_entries_reversed' => $jeCount,
                'credits_removed' => $creditCount,
            ]);

            return redirect()->route('refunds.index')->with(
                'status',
                "Refund {$refund->refund_number} voided. Reversed {$jeCount} journal entries and {$creditCount} credit row(s)."
            );
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Refund void failed', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Void failed: ' . $e->getMessage());
        }
    }

    /**
     * W4.R (w4-brief.md §4 "posted" step + §5 bundled fix "duplicate profit JE in completeProcess()
     * ... do NOT carry"). Gated by RefundPolicy::complete() first — this method was entirely
     * unauthorized before this fix (ct-refund-map.md §6). Engine ON: every posting this method
     * used to hand-roll per detail (a raw Transaction + a two-leg "Adjusted Profit" JE pair +
     * a Credit row, none of it balanced against the ORIGINAL sale/supplier/commission documents —
     * exactly the "duplicate profit JE" the brief names) is replaced entirely by
     * {@see RefundPostingService::post()}, which composes the full (a)-(f) document set in one
     * transaction. Engine OFF: the pre-existing body runs completely unchanged (byte parity).
     */
    public function completeProcess(Refund $refund)
    {
        Gate::authorize('complete', $refund);

        Log::info('Hit completeProcess()', ['refund_id' => $refund->id]);

        if (app(PostingSeam::class)->isEnabledFor((int) $refund->company_id)) {
            try {
                app(RefundPostingService::class)->post($refund, Auth::id());

                Log::info('Refund posted via RefundPostingService', ['refund_id' => $refund->id]);

                return redirect()->route('refunds.index')->with('success', 'Refund processed successfully.');
            } catch (\Throwable $e) {
                Log::error('RefundPostingService::post() failed', [
                    'refund_id' => $refund->id,
                    'error_message' => $e->getMessage(),
                ]);

                return redirect()->route('refunds.index')->with('error', 'Refund processing failed.');
            }
        }

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

    /**
     * W4.R bundled fix (w4-brief.md §5 "completeRefundClient authorize"; ct-refund-map.md §6 —
     * this method never authorized at all before this fix). RefundClient itself is now read-only
     * (App\Models\RefundClient — "Fold refund_clients into the refund doc ... remove write
     * paths"): the mutation this method used to perform is retired, not merely gated, so it is
     * authorized first (a caller without 'complete' still gets a clean 403, never a 500 from the
     * model's own read-only guard) and then refused with a clear, actionable message rather than
     * throwing RefundClient's RuntimeException up to the user.
     */
    public function completeRefundClient($refundClientId)
    {
        $refundClient = RefundClient::find($refundClientId);

        if (!$refundClient) {
            return redirect()->back()->withErrors(['error' => 'Refund Client not found.']);
        }

        Gate::authorize('complete', $refundClient);

        return redirect()->route('refunds.index')->withErrors([
            'error' => 'This legacy refund-client workflow has been retired (folded into the Refund '
                .'document, W4.R). Process this refund through the normal Refund screen instead.',
        ]);
    }

    /**
     * W4.R bundled fix. ct-refund-map.md §8 — "deleteRefundClient() credit reversal without
     * ledger": the pre-existing body mutated `client->credit` directly with NO ledger entry at
     * all, then hard-deleted the row. RefundClient is now read-only (see its own docblock); this
     * retired mutation is refused with a clear message instead of being ported forward.
     */
    public function deleteRefundClient($refundClientId)
    {
        $refundClient = RefundClient::find($refundClientId);

        if (!$refundClient) {
            return redirect()->back()->withErrors(['error' => 'Refund Client not found.']);
        }

        Gate::authorize('delete', $refundClient);

        return redirect()->route('refunds.index')->withErrors([
            'error' => 'This legacy refund-client workflow has been retired (folded into the Refund '
                .'document, W4.R). No ledger-safe deletion path exists for it any more.',
        ]);
    }

    public function createRefundInvoiceUnpaid(
        Refund $refund,
        float $invoicePrice,
    ): JsonResponse {
        Log::info('[REFUNDINVOICEUNPAID] Starting to create refund invoice', [
            'refund' => $refund,
            'invPrice' => $invoicePrice,
        ]);

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
                'payment_type' => null,
            ]);

            foreach ($refund->refundDetails as $detail) {
                $task = $detail->task;

                // Use original invoice detail prices (marked-up client price and supplier cost)
                $originalInvoiceDetail = $task->originalTask?->invoiceDetail;
                $taskPrice = $originalInvoiceDetail?->task_price ?? $detail->total_refund_to_client;
                $supplierPrice = $originalInvoiceDetail?->supplier_price ?? $detail->refund_fee_to_client;
                $markupPrice = $originalInvoiceDetail?->markup_price ?? $detail->new_task_profit;

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

            // Add non-refunded tasks from the original invoice so they appear on the new invoice edit page
            if ($refund->originalInvoice) {
                $refundedOriginalTaskIds = $refund->refundDetails
                    ->map(fn($d) => strtolower($d->task?->status) === 'refund'
                        ? $d->task->original_task_id
                        : $d->task_id)
                    ->filter()
                    ->unique()
                    ->toArray();

                $remainingOriginalDetails = $refund->originalInvoice->invoiceDetails()
                    ->when(!empty($refundedOriginalTaskIds), fn($q) => $q->whereNotIn('task_id', $refundedOriginalTaskIds))
                    ->get();

                foreach ($remainingOriginalDetails as $remainingDetail) {
                    InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'task_id' => $remainingDetail->task_id,
                        'task_description' => $remainingDetail->task_description,
                        'task_remark' => 'Carried over from invoice ' . $refund->originalInvoice->invoice_number,
                        'task_price' => $remainingDetail->task_price,
                        'supplier_price' => $remainingDetail->supplier_price,
                        'markup_price' => $remainingDetail->markup_price,
                        'created_by' => $user->id,
                    ]);
                }
            }

            $refund->update(['refund_invoice_id' => $invoice->id]);
            if ($refund->originalInvoice) {
                $refund->originalInvoice->update(['status' => InvoiceStatus::PAID_BY_REFUND->value]);
            }

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
                            'name' => 'Commissions (Agents)',
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
                                'name' => 'Commissions (Agents)',
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
                'payment_type' => null,
            ]);

            foreach ($refund->refundDetails as $detail) {
                $task = $detail->task;

                // Use original invoice detail prices (marked-up client price and supplier cost)
                $originalInvoiceDetail = $task->originalTask?->invoiceDetail;
                $taskPrice = $originalInvoiceDetail?->task_price ?? $detail->total_refund_to_client;
                $supplierPrice = $originalInvoiceDetail?->supplier_price ?? $detail->refund_fee_to_client;
                $markupPrice = $originalInvoiceDetail?->markup_price ?? $detail->new_task_profit;

                InvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'task_id' => $task->id,
                    'task_description' => $task->reference,
                    'task_remark' => 'Partial refund adjustment for invoice ' . $refund->originalInvoice?->invoice_number,
                    'task_price' => $taskPrice,
                    'supplier_price' => $supplierPrice,
                    'markup_price' => $markupPrice,
                    'created_by' => $user->id,
                ]);
            }

            // Add non-refunded tasks from the original invoice so they appear on the new invoice edit page
            if ($refund->originalInvoice) {
                $refundedOriginalTaskIds = $refund->refundDetails
                    ->map(fn($d) => strtolower($d->task?->status) === 'refund'
                        ? $d->task->original_task_id
                        : $d->task_id)
                    ->filter()
                    ->unique()
                    ->toArray();

                $remainingOriginalDetails = $refund->originalInvoice->invoiceDetails()
                    ->when(!empty($refundedOriginalTaskIds), fn($q) => $q->whereNotIn('task_id', $refundedOriginalTaskIds))
                    ->get();

                foreach ($remainingOriginalDetails as $remainingDetail) {
                    InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'task_id' => $remainingDetail->task_id,
                        'task_description' => $remainingDetail->task_description,
                        'task_remark' => 'Carried over from invoice ' . $refund->originalInvoice->invoice_number,
                        'task_price' => $remainingDetail->task_price,
                        'supplier_price' => $remainingDetail->supplier_price,
                        'markup_price' => $remainingDetail->markup_price,
                        'created_by' => $user->id,
                    ]);
                }
            }

            $refund->update(['refund_invoice_id' => $invoice->id]);
            if ($refund->originalInvoice) {
                $refund->originalInvoice->update(['status' => InvoiceStatus::PAID_BY_REFUND->value]);
            }

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
                        'name' => 'Commissions (Agents)',
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

    public function getEligibleTasks(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        $tasksQuery = Task::with([
            'client',
            'agent.branch',
            'originalTask.invoiceDetail.invoice'
        ])
            ->whereIn('status', ['issued', 'reissued'])
            ->whereHas('client')
            ->whereHas('agent')
            ->whereHas('invoiceDetail.invoice', function ($q) {
                $q->whereIn('status', ['paid', 'unpaid', 'partial', 'partial refund']);
            })
            ->whereDoesntHave('refundDetail');

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $tasksQuery->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId));
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $tasksQuery->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId));
        } elseif ($user->role_id == Role::BRANCH) {
            $tasksQuery->whereHas('agent', fn($q) => $q->where('branch_id', $user->branch->id));
        } elseif ($user->role_id == Role::AGENT) {
            $tasksQuery->where('agent_id', $user->agent->id);
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $tasksQuery->whereHas('agent', fn($q) => $q->where('branch_id', $user->accountant->branch_id));
        }

        $tasks = $tasksQuery->orderBy('created_at', 'desc')->limit(100)->get();

        $formatted = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'reference' => $task->reference,
                'type' => $task->type,
                'status' => $task->status,
                'client_name' => $task->client->full_name ?? 'N/A',
                'invoice_number' => $task->invoiceDetail?->invoice?->invoice_number ?? 'N/A',
                'invoice_status' => $task->invoiceDetail?->invoice?->status ?? 'N/A',
                'amount' => $task->invoiceDetail?->task_price ?? 0,
            ];
        });

        return response()->json([
            'tasks' => $formatted,
        ]);
    }
}
