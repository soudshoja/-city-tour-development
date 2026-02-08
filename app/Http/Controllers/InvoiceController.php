<?php

namespace App\Http\Controllers;

use App\Enums\ChargeType;
use App\Enums\InvoicePaymentType;
use App\Http\Traits\NotificationTrait;
use App\Models\Account;
use App\Models\Agent;
use App\Models\AgentCharge;
use Illuminate\Support\Facades\Auth;
use App\Models\Client;
use App\Models\Branch;
use App\Models\Charge;
use App\Models\Invoice;
use App\Models\InvoicePartial;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Company;
use App\Models\Country;
use App\Models\JournalEntry;
use App\Models\InvoiceDetail;
use App\Models\Task;
use App\Models\PaymentMethod;
use Exception;
use Illuminate\Http\Request;
use App\Models\InvoiceSequence;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierSurcharge;
use App\Models\SupplierCompany;
use App\Models\User;
use App\Models\Credit;
use App\Models\InvoiceReceipt;
use App\Models\Setting;
use App\Models\Refund;
use App\Services\ChargeService;
use App\Services\PaymentApplicationService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    use NotificationTrait;

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Invoice::class);

        $user = Auth::user();
        $companyId = getCompanyId($user);

        $companiesId = [];
        $agents = collect();

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $companiesId[] = $companyId;
                $agents = Agent::whereHas('branch', fn($query) => $query->where('company_id', $companyId))
                    ->with(['branch:id,company_id', 'branch.company:id'])
                    ->get();
            } else {
                $companiesId = Company::pluck('id')->toArray();
                $agents = Agent::with(['branch:id,company_id', 'branch.company:id'])->get();
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $companiesId[] = $companyId;
            $agents = Agent::whereHas('branch', fn($query) => $query->where('company_id', $companyId))
                ->with(['branch:id,company_id', 'branch.company:id'])
                ->get();
        } elseif ($user->role_id == Role::BRANCH) {
            $companiesId[] = $companyId;
            $agents = Agent::where('branch_id', $user->branch->id)
                ->with(['branch:id,company_id', 'branch.company:id'])
                ->get();
        } elseif ($user->role_id == Role::AGENT) {
            $companiesId[] = $companyId;
            $agents = Agent::where('id', $user->agent->id)
                ->with(['branch:id,company_id', 'branch.company:id'])
                ->get();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $companiesId[] = $companyId;
            $agents = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))
                ->with(['branch:id,company_id', 'branch.company:id,name'])
                ->get();
        } else {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $agentIds = $agents->pluck('id');
        $sortBy = in_array(request('sortBy'), ['created_at', 'invoice_date']) ? request('sortBy') : 'created_at';
        $sortOrder = in_array(request('sortOrder'), ['asc', 'desc']) ? request('sortOrder') : 'desc';

        $invoices = Invoice::with([
            'agent.branch',
            'invoiceDetails.task.supplier',
            'client'
        ])->whereIn('agent_id', $agentIds)
            ->whereHas('agent.branch', fn($q) => $q->whereIn('company_id', $companiesId));

        if ($request->has('search')) {
            $search = $request->input('search');
            $invoices = $invoices->where(function ($query) use ($search) {
                $query->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('status', "$search")
                    ->orWhere('currency', 'like', "%{$search}%")
                    ->orWhere('payment_type', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhere('sub_amount', 'like', "%{$search}%")
                    ->orWhere('tax', 'like', "%{$search}%")
                    ->orWhere('invoice_date', 'like', "%{$search}%")
                    ->orWhere('due_date', 'like', "%{$search}%")
                    ->orWhere('paid_date', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('agent', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $from = Carbon::parse($request->input('from_date'))->startOfDay();
            $to = Carbon::parse($request->input('to_date'))->endOfDay();
            $dateField = $request->input('date_field', 'created_at');

            if (in_array($dateField, ['created_at', 'invoice_date'])) {
                $invoices->whereBetween($dateField, [$from, $to]);
            }
        }

        $filteredInvoices = $invoices->get();
        $totalNet = $filteredInvoices->flatMap->invoiceDetails->sum('supplier_price');
        $totalSales = $filteredInvoices->sum('amount');

        $invoices = $invoices->orderBy($sortBy, $sortOrder)
            ->paginate(20)
            ->withQueryString();

        foreach ($invoices as $invoice) {
            $invoice->service_charges = $invoice->invoicePartials()->sum('service_charge');
            $invoice->client_pay = $invoice->amount + $invoice->service_charges;
        }

        return view('invoice.index', compact('invoices', 'totalNet', 'totalSales', 'companyId'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($user->role_id == Role::ADMIN && !$companyId) {
            return redirect()->back()->with('error', 'Please select a company from the sidebar first.');
        }

        $taskIds = $request->query('task_ids', '');
        $taskIdsArray = [];

        if (!empty($taskIds)) {
            $taskIdsArray = is_string($taskIds) ? explode(',', $taskIds) : $taskIds;

            foreach ($taskIdsArray as $taskId) {
                $task = Task::find($taskId);

                if (!$task) {
                    return Redirect::route('tasks.index')->with('error', 'Task not found!');
                }

                if ($task->invoiceDetail) {
                    return Redirect::route('tasks.index')->with('error', 'One or more selected tasks are already invoiced');
                }

                if (!$task->is_complete) {
                    return Redirect::route('tasks.index')->with('error', 'Task does not have full information!');
                }
            }
        }

        $taskIdsArray = array_map('intval', Arr::flatten($taskIdsArray));
        if (count($taskIdsArray) !== count(Arr::flatten($taskIdsArray, 1))) {
            throw new InvalidArgumentException('Nested arrays may not be passed to whereIn method.');
        }

        $tasks = Task::with('supplier', 'agent.branch', 'invoiceDetail.invoice', 'flightDetails.countryFrom', 'flightDetails.countryTo', 'hotelDetails.hotel')
            ->where('enabled', true);

        $selectedTasks = (clone $tasks)->whereIn('id', $taskIdsArray)->get();

        $blockedSuppliers = ['jazeera airways'];
        foreach ($selectedTasks as $task) {
            $supplierName = strtolower($task->supplier->name ?? '');
            $isBlockedStatus = in_array($task->status, ['confirmed', 'void'], true);

            if (in_array($supplierName, $blockedSuppliers, true) && $isBlockedStatus) {
                return back()->with('error', "You cannot create an invoice for {$task->status} tasks from {$task->supplier->name}");
            }
        }

        foreach ($selectedTasks as $task) {
            if ($task->invoiceDetail) {
                return Redirect::route('invoice.edit', [
                    'companyId' => $task->company_id,
                    'invoiceNumber' => $task->invoiceDetail->invoice->invoice_number
                ]);
            }
        }

        $selectedTasks = $selectedTasks->map(function ($task) {
            $task->agent_name = $task->agent->name ?? null;
            $task->branch_name = $task->agent->branch->name ?? null;
            $task->supplier_name = $task->supplier->name ?? null;
            return $task;
        });

        if ($selectedTasks->count() > 0) {
            $firstTask = $selectedTasks->first();
            $taskCompanyId = $firstTask->agent->branch->company_id ?? null;

            if (!$user->role_id == Role::ADMIN && $taskCompanyId && $taskCompanyId != $companyId) {
                return redirect()->back()->with('error', 'Unauthorized access to this task.');
            }

            if ($user->role_id == Role::ADMIN && $taskCompanyId) {
                $companyId = $taskCompanyId;
            }
        }

        $selectedCompany = $companyId ? Company::find($companyId) : null;
        $agents = collect();
        $branches = collect();
        $clients = collect();
        $agentsId = [];

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $company = Company::with('branches.agents')->find($companyId);
                $agents = $company->branches->flatMap->agents;
                $branches = $company->branches;
                $agentsId = $agents->pluck('id')->toArray();

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

        $tasks = $agentId
            ? (clone $tasks)->whereIn('agent_id', $agentId)->get()->filter(function ($task) {
                return !$task->invoiceDetail;
            })->map(function ($task) {
                $task->agent_name = $task->agent->name ?? null;
                $task->branch_name = $task->agent->branch->name ?? null;
                $task->supplier_name = $task->supplier->name ?? null;
                $task->quantity = 1;
                return $task;
            })
            : collect();

        $todayDate = Carbon::now()->format('Y-m-d');
        $appUrl = config('app.url');

        $invoiceExpireDefault = Setting::where('key', 'invoice_expiry_days')
            ->where('company_id', $companyId)
            ->first();

        $invoiceExpireDefault = $invoiceExpireDefault
            ? date('Y-m-d', strtotime('+' . $invoiceExpireDefault->value . ' days'))
            : date('Y-m-d', strtotime('+5 days'));

        if (!$companyId) {
            return redirect()->back()->with('error', 'Unable to determine company for invoice creation.');
        }

        $invoiceSequence = InvoiceSequence::firstOrCreate(
            ['company_id' => $companyId],
            ['current_sequence' => 1]
        );
        $currentSequence = $invoiceSequence->current_sequence;
        $invoiceNumber = $this->generateInvoiceNumber($currentSequence);

        $countries = Country::all();

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
        ));
    }

    public function edit(Request $request, int $companyId, string $invoiceNumber)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        if ($user->role_id == Role::ACCOUNTANT) {
            return $this->accountantEdit($companyId, $invoiceNumber);
        }

        $company = Company::find($companyId);

        if (!$company) {
            return redirect()->back()->with('error', 'Company not found.');
        }

        $branches = $company->branches;
        $agents = $branches->pluck('agents')->flatten();
        $agentsId = $agents->pluck('id');

        $clients = Client::where(function ($query) use ($agentsId) {
            $query->whereIn('agent_id', $agentsId)
                ->orWhereHas('agents', function ($q) use ($agentsId) {
                    $q->whereIn('agent_id', $agentsId);
                });
        })->get();

        foreach ($clients as $client) {
            $credit = Credit::getTotalCreditsByClient($client->id);
            $client->total_credit = $credit;
        }

        $invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->whereHas('agent.branch.company', function ($q) use ($companyId) {
                $q->where('id', $companyId);
            })
            ->with('agent.branch.company', 'client', 'invoiceDetails.task')
            ->first();

        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }

        if ($invoice->status === 'paid') {
            return redirect()->route('invoices.index')->with(['success' => 'Invoice paid successfully!']);
        }

        if ($invoice->status === 'paid by refund') {
            return redirect()->route('invoices.index')->withErrors(['error' => 'The selected invoice cannot be edited']);
        }

        if ($invoice->originalRefunds->isNotEmpty()) {
            return redirect()->route('invoices.index')->withErrors(['error' => 'The selected invoice cannot be edited']);
        }

        $invoiceDetails = $invoice->invoiceDetails;
        $agentId = $invoice->agent_id;
        $clientId = $invoice->client_id;
        $tasks = Task::where('agent_id', $agentId)
            ->whereDoesntHave('invoiceDetail')
            ->with(['supplier', 'agent.branch', 'client'])
            ->get();
        $selectedTasks = $invoice->invoiceDetails
            ->filter(fn($invoiceDetail) => $invoiceDetail->task)
            ->map(function ($invoiceDetail) use ($invoice) {
                $task = $invoiceDetail->task;
                $task->agent_name = optional($task->agent)->name;
                $task->supplier_name = optional($task->supplier)->name;
                $task->branch_name = optional(optional($task->agent)->branch)->name;
                $task->task_price = $invoiceDetail->task_price;
                $task->invprice = (float) $invoice->amount;
                return $task;
            });

        $selectedAgent = $invoice->agent;
        $selectedClient = $invoice->client;

        $paymentGateways = Charge::with(['methods' => function ($query) use ($invoice) {
            $query->where('is_active', true);
        }])->where('is_active', true)->get();

        $invoiceGateways = Charge::where('is_active', true)
            ->where('can_generate_link', true)
            ->get();
        $invoiceCharges = Charge::where('company_id', $invoice->agent->branch->company_id)
            ->where('is_active', true)
            ->where('can_charge_invoice', true)
            ->get();

        $invoiceDate = $invoice->invoice_date;
        $invprice = $invoice->amount;
        $dueDate = $invoice->due_date;

        // Calculate gateway fees for each gateway and its methods
        foreach ($paymentGateways as $gateway) {
            $companyMethods = $gateway->methods->where('company_id', $companyId);

            if ($companyMethods->isNotEmpty()) {
                // Gateway has payment methods - calculate fee for each method
                foreach ($companyMethods as $method) {
                    try {
                        $result = ChargeService::calculate($invprice, $companyId, $method->id, $gateway->name);
                        $method->fee = $result['gatewayFee'] ?? 0;
                    } catch (Exception $e) {
                        Log::error('getFee exception for method', [
                            'gateway' => $gateway->name,
                            'message' => $e->getMessage(),
                            'paymentMethod' => $method->id,
                            'company_id' => $companyId,
                        ]);
                        $method->fee = 0;
                    }
                }
            } else {
                $result = ChargeService::calculate($invprice, $companyId, null, $gateway->name);
                $gateway->fee = $result['gatewayFee'] ?? 0;
            }
        }

        // Create a flat collection of all payment methods with their fees for the frontend
        // Only include methods for the current company
        $paymentMethods = $paymentGateways->pluck('methods')->flatten()->filter(function ($method) use ($companyId) {
            return $method->company_id == $companyId || $method->company_id === null;
        });

        $appUrl = config('app.url');

        // Check if the credit has been used for this invoice
        $creditUsed = Credit::where('client_id', $invoice->client_id)
            ->where('invoice_id', $invoice->id)
            ->first();

        $invoiceExpireDefault = Setting::where('key', 'invoice_expiry_days')->first();
        $invoiceExpireDefault = $invoiceExpireDefault ? date('Y-m-d', strtotime('+' . $invoiceExpireDefault->value . ' days')) : date('Y-m-d', strtotime('+5 days'));

        $can_import = Charge::where('company_id', $companyId)
            ->where('can_import', true)
            ->get();

        $receiptVoucher = InvoiceReceipt::with('transaction')
            ->where('type', 'import')
            ->where('status', 'approved')
            ->where('is_used', false)
            ->get();

        $companyIdForPartials = $invoice->agent->branch->company_id;
        $unpaidPartial = InvoicePartial::with(['paymentMethod', 'charge'])
            ->where('invoice_id', $invoice->id)
            ->where('status', 'unpaid')
            ->get();

        // Get available payments for client credit with payment selection
        $availablePayments = Credit::getAvailablePaymentsForClient($invoice->client_id);

        $refund = null;
        $refundNumber = $request->query('refund_number');
        if ($refundNumber) {
            $refund = Refund::with('refundDetails')
            ->where('refund_number', $refundNumber)
            ->first();

            if ($refund) {
                $refundDetailsMap = $refund->refundDetails->keyBy('task_id');

                $selectedTasks = $selectedTasks->map(function ($task) use ($refundDetailsMap) {
                    if ($refundDetailsMap->has($task->id)) {
                        $task->total = $refundDetailsMap[$task->id]->original_task_cost;
                    } 

                    return $task;
                });
            }
        }

        return view('invoice.edit', compact(
            'clients',
            'invoice',
            'agents',
            'branches',
            'agentId',
            'clientId',
            'tasks',
            'company',
            'invoiceNumber',
            'selectedTasks',
            'selectedAgent',
            'selectedClient',
            'paymentGateways',
            'invoiceGateways',
            'invoiceCharges',
            'paymentMethods',
            'invoiceDate',
            'invprice',
            'dueDate',
            'appUrl',
            'creditUsed',
            'invoiceExpireDefault',
            'companyId',
            'can_import',
            'receiptVoucher',
            'unpaidPartial',
            'companyIdForPartials',
            'availablePayments',
            'refund',
        ));
    }

    public function accountantEdit($companyId, $invoiceNumber)
    {
        Gate::authorize('accountantEdit', Invoice::class);

        $invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->whereHas('agent.branch.company', function ($q) use ($companyId) {
                $q->where('id', $companyId);
            })
            ->with('client', 'agent')
            ->first();

        // Get all clients, agents, and countries for dropdowns
        $clients = Client::all();
        $agents = Agent::with('branch.company')->get();
        $countries = Country::all();
        $charges = Charge::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        $paymentMethods = PaymentMethod::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        $invoicePaymentTypes = InvoicePaymentType::labels();

        $clientCredit = Credit::getTotalCreditsByClient($invoice->client->id);
        $isCreditDeducted = Credit::where('client_id', $invoice->client_id)->where('invoice_id', $invoice->id)->exists();

        session()->forget('shortage_info');

        if ($invoice->payment_type == InvoicePaymentType::CREDIT->value && $clientCredit < $invoice->amount) {

            if ($isCreditDeducted) {
                $shortage = $clientCredit;
            } else {
                $shortage = $invoice->amount - $clientCredit;
            }

            session(['shortage_info' => [
                'available_credit' => $clientCredit,
                'required_amount' => $invoice->amount,
                'shortage_amount' => $shortage,
                'client_id' => $invoice->client->id,
                'invoice_id' => $invoice->id,
            ]]);
        }

        return view('invoice.accountant.edit', compact(
            'invoice',
            'clients',
            'agents',
            'countries',
            'charges',
            'paymentMethods',
            'invoicePaymentTypes',
            'clientCredit'
        ));
    }

    public function updatePaymentType(Request $request)
    {
        Log::info('Starting to update Payment Type');

        try {
            $invoice = Invoice::where('id', $request->invoice_id)
                ->where('status', 'unpaid')
                ->first();
            if (!$invoice) {
                return redirect()->back()->with('error', 'Invoice not found');
            }

            $invoice->payment_type = null;
            $invoice->save();

            $invoicePartials = InvoicePartial::where('invoice_id', $request->invoice_id)->get();

            if ($invoicePartials->isNotEmpty()) {
                foreach ($invoicePartials as $partial) {
                    Log::info('Deleting InvoicePartial', ['invoice_partial_id' => $partial->id]);
                    $partial->delete();
                }
                Log::info('Payment type changed, all related partials deleted for invoice ID: ' . $invoice->id);
            } else {
                Log::info('Payment type changed, no related invoice partial found for invoice ID: ' . $invoice->id);
            }

            return redirect()->back()->with('success', 'Payment Type changed successfully');
        } catch (Exception $e) {
            Log::error('Failed to change payment type');
            return redirect()->back()->with('error', 'Failed to change Payment Type');
        }
    }

    public function updatePartialGateway(Request $request)
    {
        Log::info('Starting to change the payment method for unpaid Partial/Split Invoice', [
            'data' => $request->all(),
        ]);

        $request->validate([
            'invoice_id' => 'required|int',
            'invoice_number' => 'required|string',
            'invoice_partial_id' => 'required|int',
            'gateway' => 'required|string',
            'method' => 'nullable|int',
        ]);

        $invoicePartial = InvoicePartial::where('id', $request->invoice_partial_id)->first();
        if (!$invoicePartial) {
            Log::warning('Invoice Partial not found for ID: ' . $request->invoice_partial_id);
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice partial not found.'
            ], 404);
        }

        $charge = Charge::where('name', $request->gateway)->first();
        if (!$charge) {
            Log::warning('Charge not found');
            return response()->json([
                'status' => 'error',
                'message' => 'Charge not found.'
            ], 404);
        }

        $method = $request->method;
        if ($request->gateway != 'MyFatoorah') {
            $method = null;
        }

        try {
            DB::beginTransaction();

            $companyId = $invoicePartial->invoice?->agent?->branch?->company_id;
            $newFee = 0;
            if ($companyId) {
                $result = ChargeService::calculate(
                    (float) $invoicePartial->amount,
                    $companyId,
                    $method,
                    $request->gateway
                );
                $newFee = $result['accountingFee'] ?? 0;
            }

            $invoicePartial->update([
                'charge_id' => $charge->id,
                'payment_gateway' => $request->gateway,
                'payment_method' => $method,
                'gateway_fee' => $newFee,
                'updated_at' => now(),
            ]);

            // Recalculate profit since gateway fee changed
            if ($invoicePartial->invoice) {
                $this->recalculateProfitForInvoice($invoicePartial->invoice);
            }

            DB::commit();
        } catch (Exception $e) {
            Log::error('Failed to update invoice with new payment gateway', [
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);

            DB::rollBack();

            return redirect()->back()->with('error', 'Payment Method updated successfully');
        }

        return redirect()->back()->with('success', 'Payment Method updated successfully');
    }

    public function updatePaymentGateway(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoiceId' => 'required',
            'gateway' => 'required|string',
            'method' => 'nullable',
            'amount' => 'required',
            'invoiceNumber' => 'required|string'
        ]);

        $invoice = Invoice::findOrFail($validated['invoiceId']);

        $invoice = Invoice::where('invoice_number', $validated['invoiceNumber'])->with('agent.branch.company', 'client', 'invoiceDetails.task')->first();
        $companyId = $invoice->agent->branch->company_id;

        $gatewayFee = 0;

        try {
            $gatewayFee = ChargeService::calculate($validated['amount'], $companyId, $validated['method'] ?? null, $validated['gateway']);
        } catch (Exception $e) {
            Log::error('getFee exception in updatePaymentGateway', [
                'gateway' => $validated['gateway'],
                'message' => $e->getMessage(),
                'paymentMethod' => $validated['method'] ?? null,
                'company_id' => $companyId,
            ]);
            $gatewayFee = ['gatewayFee' => 0, 'gatewayFee' => 0];
        }

        if ($invoice) {
            Log::info('Updating payment gateway for invoice', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'new_gateway' => $validated['gateway'],
                'new_method' => $validated['method'] ?? null,
                'new_amount' => $validated['amount'],
                'gatewayFee' => $gatewayFee['gatewayFee'] ?? 0,
            ]);

            $invoice->update([
                'payment_gateway' => $validated['gateway'],
                'payment_type' => 'full',
            ]);
        }

        $invoicePartial = InvoicePartial::where('invoice_id', $invoice->id)->first();

        if ($invoicePartial) {
            $invoicePartial->update([
                'payment_gateway' => $validated['gateway'],
                'type' => 'full',
                'charge_id' => Charge::where('name', $validated['gateway'])->value('id'),
                'payment_method' => $validated['method'] ?? null,
                'service_charge' => $gatewayFee['gatewayFee'] ?? 0,
                'gateway_fee' => $gatewayFee['accountingFee'] ?? 0,
                'amount' => $invoice->amount,
            ]);

            // Recalculate profit for all details since gateway fee changed
            $this->recalculateProfitForInvoice($invoice);
        } else {
            return response()->json(['message' => 'Invoice partial not found.'], 404);
        }

        return response()->json(['message' => 'Payment method updated successfully!', 'invoice' => $invoicePartial]);
    }

    public function savePartial(Request $request): JsonResponse
    {
        Log::info('Starting to save payment of the invoice', $request->all());
        
        $request->validate([
            'invoiceId' => 'required',
            'date' => 'nullable',
            'clientId' => 'required',
            'amount' => 'required',
            'type' => 'required|string',
            'invoiceNumber' => 'required|string',
            'gateway' => 'required',
            'method' => 'nullable|string',
            'external_url' => 'nullable|url',
            'invoice_charge' => 'nullable|numeric|min:0',
            'companyId' => 'required',
        ]);

        $client = Client::find($request->input('clientId'));
        $balanceCredit = Credit::getTotalCreditsByClient($client->id);
        if ($request->boolean('credit', false)) {
            if ($request->input('amount') > $balanceCredit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client credit is not enough!',
                ]);
            }
        }

        return DB::transaction(function () use ($request) {
            $invoiceId = $request->input('invoiceId');
            $invoiceNumber = $request->input('invoiceNumber');
            $clientId = $request->input('clientId');
            $type = $request->input('type');
            $date = $request->input('date');
            $amount = $request->input('amount');
            $gateway = $request->input('gateway');
            $method = $request->input('method') ?? null;
            $credit = $request->input('credit', false); // Default to false if not provided
            $externalUrl = $request->input('external_url');
            $invoiceCharge = $request->input('invoice_charge', 0);
            $companyId = $request->input('companyId');

            $invoice = Invoice::where('invoice_number', $invoiceNumber)
                ->whereHas('agent.branch.company', function ($q) use ($companyId) {
                    $q->where('id', $companyId);
                })
                ->with('agent.branch.company', 'client', 'invoiceDetails.task')
                ->first();

            Log::info('Invoice query result', [
                'invoiceNumber' => $invoiceNumber,
                'companyId'     => $companyId,
                'invoice'       => $invoice ? $invoice->toArray() : null,
            ]);

            $charge = Charge::where('name', $gateway)->first();

            // Update invoice with external URL only if the gateway supports URLs
            if ($externalUrl && $charge && $charge->has_url) {
                $invoice->update(['external_url' => $externalUrl]);
            }

            // Update invoice charge
            if ($invoiceCharge !== null) {
                $invoice->invoice_charge = $invoiceCharge;
                $invoice->amount = $invoice->sub_amount + $invoiceCharge;
                $invoice->save();
            }

            $isCash = strcasecmp($type ?? '', 'cash') === 0 || strcasecmp($gateway ?? '', 'cash') === 0;

            // Handle new payment types: cash
            if ($isCash) {
                $gateway = 'Cash';
                $status = 'unpaid';
            }

            $gatewayFee = 0;
            $gatewayFee = ChargeService::calculate($amount, $companyId, $method, $gateway);

            $status = 'unpaid';

            try {
                switch ($gateway) {
                    case 'Tabby':
                        $status = 'paid';
                        break;
                    case 'Credit':
                        $status = 'paid';
                        $credit = true;
                        break;
                    default:
                        $status = 'unpaid';
                }

                $invoicePartial = InvoicePartial::create([
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'client_id' => $clientId,
                    'service_charge' => $credit ? 0 : ($gatewayFee['gatewayFee'] ?? 0),
                    'gateway_fee' => $credit ? 0 : ($gatewayFee['accountingFee'] ?? 0),
                    'amount' => $amount,
                    'status' => $status,
                    'expiry_date' => $date,
                    'type' => $type,
                    'payment_gateway' => $gateway,
                    'payment_method' => $method,
                    'charge_id' => Charge::where('name', $gateway)->value('id'),
                ]);

                $appliedPayments = []; // Track applied payments for COA

                if ($credit) {
                    $paymentAllocations = $request->input('payment_allocations', []);

                    if (!empty($paymentAllocations)) {
                        // Use PaymentApplicationService to link to specific payments
                        try {
                            $paymentService = app(PaymentApplicationService::class);
                            $result = $paymentService->linkPaymentsToInvoicePartial(
                                $invoice,
                                $invoicePartial,
                                $paymentAllocations
                            );

                            // Collect applied payments for COA creation
                            if ($result['success'] && !empty($result['applied_payments'])) {
                                $appliedPayments = $result['applied_payments'];
                            }

                            Log::info('Payment allocations applied via PaymentApplicationService', [
                                'invoice_id' => $invoice->id,
                                'invoice_partial_id' => $invoicePartial->id,
                                'allocations' => $paymentAllocations,
                                'applied_payments' => $appliedPayments,
                            ]);
                        } catch (Exception $e) {
                            Log::error('Failed to apply payment allocations: ' . $e->getMessage());
                            throw new \Exception('Failed to apply payment allocations: ' . $e->getMessage());
                        }
                    } else {
                        // Fallback: create credit record without linking to specific payment (legacy behavior)
                        try {
                            $creditRecord = Credit::create([
                                'company_id'  => $invoice->agent?->branch?->company_id,
                                'branch_id'   => $invoice->agent?->branch_id,
                                'client_id'   => $invoice->client_id,
                                'invoice_id'  => $invoice->id,
                                'invoice_partial_id'  => $invoicePartial->id,
                                'type'        => Credit::INVOICE,
                                'description' => 'Payment for ' . $invoice->invoice_number,
                                'amount'      => -$amount,
                                'gateway_fee' => 0, // Legacy credit usage — no source payment linked
                            ]);

                            // Build applied payments for COA (legacy - no specific voucher)
                            $appliedPayments[] = [
                                'credit_id' => $creditRecord->id,
                                'payment_id' => null,
                                'refund_id' => null,
                                'voucher_number' => 'Client Credit',
                                'amount_applied' => $amount,
                                'invoice_partial_id' => $invoicePartial->id,
                            ];
                        } catch (Exception $e) {
                            Log::error('Failed to create Credit: ' . $e->getMessage());
                            throw new \Exception('Failed to create credit record: ' . $e->getMessage());
                        }
                    }
                }

                $invoice->payment_type = $type;

                // Auto-payment logic: if charge has is_auto_paid = true, automatically mark as paid
                if ($charge && $charge->is_auto_paid) {
                    $invoice->status = 'paid';
                    $invoice->paid_date = now();
                } else {
                    $invoicePartial->status = $credit ? 'paid' : 'unpaid';
                    if ($credit) {
                        $invoice->paid_date = now();
                    }
                }

                $invoice->is_client_credit = $type === 'credit' ? true : false;
                $hasUnpaid = $invoice->invoicePartials()->where('status', 'unpaid')->exists();
                $invoice->status = $hasUnpaid ? 'unpaid' : 'paid';

                if (in_array($type, ['partial', 'split'])) {
                    $hasPaid = $invoice->invoicePartials()->where('status', 'paid')->exists();

                    if ($hasPaid && $hasUnpaid) {
                        $invoice->status = 'partial';
                        Log::info('Invoice marked as PARTIAL due to split payments', [
                            'invoice_id' => $invoice->id,
                            'has_paid'   => $hasPaid,
                            'has_unpaid' => $hasUnpaid,
                        ]);
                    } elseif ($hasPaid && !$hasUnpaid) {
                        $invoice->status = 'paid';
                    } else {
                        $invoice->status = 'unpaid';
                    }
                }

                $invoice->save();

                $transaction = Transaction::where('invoice_id', $invoice->id)
                    ->where('reference_type', 'Invoice')
                    ->first();

                if (!$transaction) {
                    $tasksId = $invoice->invoiceDetails->pluck('task_id')->toArray();
                    $tasks = Task::with(['invoiceDetail' => function ($q) use ($invoice) {
                        $q->where('invoice_id', $invoice->id);
                    }, 'agent'])
                        ->whereIn('id', $tasksId)
                        ->get();

                    if ($tasks->isEmpty()) {
                        throw new \Exception('No tasks found for this invoice to create a transaction.');
                    }

                    $transaction = Transaction::create([
                        'company_id' => $tasks[0]->company_id,
                        'branch_id' => $tasks[0]->agent->branch_id,
                        'entity_id' => $tasks[0]->company_id,
                        'entity_type' => 'company',
                        'transaction_type' => 'credit',
                        'amount' =>  $invoice->amount,
                        'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
                        'invoice_id' => $invoice->id,
                        'reference_type' => 'Invoice',
                        'transaction_date' => $invoice->invoice_date,
                    ]);

                    foreach ($tasks as $task) {
                        $invoiceDetail = $task->invoiceDetail ?: $invoice->invoiceDetails->firstWhere('task_id', $task->id);
                        Log::info('Preparing to add journal entry', [
                            'task_id' => $task->id ?? null,
                            'invoice_id' => $invoice->id,
                            'invoice_detail_id' => $invoiceDetail->id ?? null,
                            'transaction_id' => $transaction->id ?? null,
                            'client_name' => $invoice->client->full_name ?? null,
                            'task' => $task,
                        ]);

                        $response = $this->addJournalEntry(
                            $task,
                            $invoice->id,
                            $invoiceDetail->id,
                            $transaction->id,
                            $invoice->client->full_name,
                        );
                        $response = json_decode($response->getContent(), true);

                        Log::info('Journal entry response', ['response' => $response]);

                        if (!$response['success']) {
                            throw new Exception('Failed to create journal entry: ' . ($response['message'] ?? 'Unknown error'));
                        }
                    }
                } else {
                    Log::info('Reusing existing transaction for invoice', [
                        'invoice_id' => $invoice->id,
                        'transaction_id' => $transaction->id,
                    ]);

                    // Recalculate profit with updated gateway fees from new partial
                    $this->recalculateProfitForInvoice($invoice);
                }

                // STEP 2: CREDIT PAYMENT COA
                if ($credit && !empty($appliedPayments)) {
                    $totalCreditApplied = array_sum(array_column($appliedPayments, 'amount_applied'));
                    $this->createCreditPaymentCOA($invoice, $appliedPayments, $totalCreditApplied);
                }

                // STEP 3: For Cash - Create Receipt Voucher
                if ($isCash) {
                    $receiptVoucher = new ReceiptVoucherController();
                    $rvResult = $receiptVoucher->createReceiptVoucher($invoice, $invoicePartial, $request);

                    if (!$rvResult['ok']) {
                        throw new \Exception($rvResult['message'] ?? 'Failed to create receipt voucher');
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Invoice Partial created successfully!',
                    'invoiceId' => $invoice->id,
                ]);
            } catch (Exception $e) {
                if (isset($invoice)) {
                    $invoice->payment_type = null;
                    $invoice->status = 'unpaid';
                    $invoice->is_client_credit = false;
                    $invoice->save();
                }

                Log::error('Failed to create Invoice Partial or Transaction/Journal Entries: ' . $e->getMessage());
                throw new \Exception('Failed to create Invoice Partial or Transaction/Journal Entries: ' . $e->getMessage());
            }
        });
    }

    public function removePartial(Request $request)
    {
        $request->validate([
            'invoiceId' => 'required',
            'invoiceNumber' => 'required|string',
        ]);

        $invoiceId = $request->input('invoiceId');
        $invoiceNumber = $request->input('invoiceNumber');

        try {
            // Find the invoice partial to be deleted
            $invoicePartial = InvoicePartial::where('invoice_id', $invoiceId)
                ->first();

            // Check if the partial exists
            if (!$invoicePartial) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice partial not found!',
                ]);
            }

            // Delete the invoice partial
            $invoicePartial->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invoice partial removed successfully!',
                'invoiceId' => $invoiceId,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to remove InvoicePartial: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove invoice partial!',
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|integer',
            'tasks.*.description' => 'required|string',
            'tasks.*.invprice' => 'required|numeric',
            'tasks.*.supplier_id' => 'required|integer',
            'tasks.*.client_id' => 'required|integer',
            'tasks.*.agent_id' => 'required|integer',
            'tasks.*.total' => 'required|numeric',
            'label' => 'nullable|string',
            'invdate' => 'required|date',
            'duedate' => 'nullable|date',
            'subTotal' => 'required|numeric',
            'clientId' => 'required|integer',
            'agentId' => 'required|integer',
            'invoiceNumber' => 'required|string',
            'currency' => 'required|string',
            'payment_id' => 'nullable|integer',
        ]);

        $tasks = $request->input('tasks');
        $duedate = $request->input('duedate');
        $invdate = $request->input('invdate');
        $amount = $request->input('subTotal');
        $clientId = $request->input(key: 'clientId');
        $agentId =  $request->input(key: 'agentId');
        $invoiceNumber = $request->input(key: 'invoiceNumber');
        $currency = $request->input('currency');


        $agent = Agent::where('id', $agentId)->first();
        $companyId = $agent && $agent->branch && $agent->branch->company ? $agent->branch->company->id : null;
        $branchId = $agent ? $agent->branch_id : null;


        if (!$agent || !$companyId || !$branchId) {

            Log::error('Some of this data is missing', [
                'agent' => $agent,
                'companyId' => $companyId,
                'branchId' => $branchId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong',
            ]);
        }

        try {
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'agent_id' => $agentId,
                'client_id' => $clientId,
                'sub_amount' => $amount,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'unpaid',
                'invoice_date' => $invdate,
                'due_date' => $duedate,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create invoice: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something Went Wrong',
            ]);
        }

        if (!empty($tasks)) {
            foreach ($tasks as $task) {

                $selectedtask = Task::where('id', operator: $task['id'])->first();
                $supplier = Supplier::where('id', operator: $task['supplier_id'])->first();
                $client = Client::where('id', operator: $task['client_id'])->first();
                $agent = Agent::where('id', operator: $task['agent_id'])->first();

                if (!$selectedtask || !$supplier || !$client || !$agent) {

                    Log::error('Failed to find task, supplier, client, or agent: ' . $task['description']);

                    return response()->json('Something went wrong', 404);
                }

                try {
                    $invoiceDetail =  InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'task_id' => $task['id'],
                        'task_description' => $task['description'],
                        'task_remark' => $task['remark'] ?? null,
                        'client_notes' => $task['note'] ?? null,
                        'task_price' =>  $task['invprice'],
                        'supplier_price' => $selectedtask->total,
                        'markup_price' => $task['invprice'] - $selectedtask->total,
                        'profit' => $task['invprice'] - $selectedtask->total,
                        'paid' => false,
                    ]);
                } catch (Exception $e) {
                    $invoice->delete();
                    Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                    return response()->json('Something Went Wrong', 500);
                }
            }
        }

        $invoiceSequence = InvoiceSequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
        $currentSequence = $invoiceSequence->current_sequence;
        $invoiceNumber = $this->generateInvoiceNumber($currentSequence);
        $invoiceSequence->current_sequence++;
        $invoiceSequence->save();

        return response()->json([
            'success' => true,
            'message' => 'Invoice created successfully!',
            'invoiceId' => $invoice->id,
        ]);
    }

    public function addJournalEntry(
        $task,
        $invoiceId,
        $invoiceDetailId,
        $transactionId,
        $clientName,
    ): JsonResponse {
        Log::info('addJournalEntry method called', [
            'task_id' => $task->id ?? null,
            'invoice_id' => $invoiceId,
        ]);

        $invoice = Invoice::find($invoiceId);

        if (!$invoice) {
            Log::error('Invoice not found', ['invoice_id' => $invoiceId]);
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found!',
            ]);
        }

        // ENTRY 1: DEBIT Asset (Receivable)
        try {
            $accountReceivable = Account::where('name', 'Accounts Receivable')
                ->where('company_id', $task->company_id)
                ->first();

            $clientAccount = Account::where('name', 'Clients')
                ->where('company_id', $task->company_id)
                ->where('parent_id', optional($accountReceivable)->id)
                ->first();

            if ($clientAccount) {
                JournalEntry::create([
                    'transaction_id' => $transactionId,
                    'branch_id' => $task->agent->branch_id ?? null,
                    'company_id' => $task->company_id ?? null,
                    'account_id' => $clientAccount->id,
                    'task_id' => $task->id ?? null,
                    'agent_id' => $task->agent_id ?? $invoice->agent_id,
                    'invoice_id' => $invoiceId,
                    'invoice_detail_id' => $invoiceDetailId,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Invoice created for (Assets): ' . $clientName,
                    'debit' => $task->invoiceDetail->task_price,
                    'credit' => 0,
                    'balance' => $clientAccount->balance ?? 0,
                    'name' => $clientAccount->name,
                    'type' => 'receivable',
                    'currency' => $task->currency ?? 'KWD',
                    'exchange_rate' => $task->exchange_rate ?? 1.00,
                    'amount' => $task->invoiceDetail->task_price,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Client Asset Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoiceId]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create client asset entry',
            ]);
        }

        // ENTRY 2: CREDIT Income (Booking Revenue)
        try {
            $bookingAccountName = ucfirst($task->type) . ' Booking Revenue';
            $detailsAccount = Account::where('name', 'like', '%' . $bookingAccountName . '%')
                ->where('company_id', $task->company_id)
                ->first();

            if (!$detailsAccount) {
                Log::info("Booking revenue account '{$bookingAccountName}' not found. Creating it now...");

                $directIncomeParent = Account::where('name', 'like', '%Direct Income%')
                    ->where('company_id', $task->company_id)
                    ->first();

                $lastRevenue = Account::where('parent_id', $directIncomeParent->id)
                    ->where('company_id', $task->company_id)
                    ->orderByDesc('code')
                    ->first();

                $lastCode = (int)($lastRevenue?->code ?? 4110);
                $nextCode = $lastCode + 5;

                $detailsAccount = Account::create([
                    'code' => str_pad($nextCode, 4, '0', STR_PAD_LEFT),
                    'name' => $bookingAccountName,
                    'company_id' => $task->company_id,
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
                    'currency' => $task->currency ?? 'KWD',
                ]);

                Log::info("Auto-created new booking revenue account '{$bookingAccountName}' ({$detailsAccount->code}) for company {$task->company_id}");
            }

            JournalEntry::create([
                'transaction_id' => $transactionId,
                'branch_id' => $task->agent->branch_id,
                'company_id' => $task->company_id,
                'account_id' => $detailsAccount->id,
                'task_id' => $task->id ?? null,
                'agent_id' => $task->agent_id ?? $invoice->agent_id,
                'invoice_id' => $invoiceId,
                'invoice_detail_id' => $invoiceDetailId,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'Invoice created for (Income): ' . $task->reference,
                'debit' => 0,
                'credit' => $task->invoiceDetail->task_price,
                'balance' => $detailsAccount->balance ?? 0,
                'name' => $detailsAccount->name,
                'type' => 'payable',
                'currency' => $task->currency ?? 'KWD',
                'exchange_rate' => $task->exchange_rate ?? 1.00,
                'amount' => $task->invoiceDetail->task_price,
            ]);
        } catch (\Exception $e) {
            Log::error('Income Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoiceId]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create income entry',
            ]);
        }

        // ENTRY 3 & 4: Profit (ALL types) + Commission (types 2, 3, 4 only)
        try {
            $agent = $task->agent;

            if (!$agent) {
                Log::error('Agent not found for task', ['task_id' => $task->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Agent not found for task',
                ]);
            }

            $companyId = $task->company_id ?? $agent->branch?->company_id;

            // ── Profit calculation for ALL agent types ──
            $selling = (float) ($task->invoiceDetail->task_price ?? 0);
            $supplier = (float) ($task->total ?? 0);
            $markup = $selling - $supplier;

            $settings = AgentCharge::getForAgent($agent->id, $companyId);
            $totalAccountingFee = $this->calculateTotalAccountingFee($invoice, $companyId);
            $taskCount = $invoice->invoiceDetails->count();
            $gatewayChargePerTask = $taskCount > 0 ? round($totalAccountingFee / $taskCount, 3) : 0;
            // $supplierSurcharge = $this->getSupplierSurchargeForTask($task, $companyId);
            $totalExtraCharge = $gatewayChargePerTask; // + $supplierSurcharge;
            $agentChargeDeduction = $settings->calculateAgentChargeDeduction($totalExtraCharge);

            $profit = round($markup - $agentChargeDeduction, 3);

            // ── Commission ONLY for types 2, 3, 4 ──
            $commission = 0;
            if (in_array($agent->type_id, [2, 3, 4])) {
                $rate = (float) ($agent->commission ?? 0.15);
                $commission = round($profit * $rate, 3);
            }

            // ── Save profit + commission for ALL agent types ──
            $invoiceDetail = InvoiceDetail::find($task->invoiceDetail->id ?? null);
            if ($invoiceDetail) {
                $invoiceDetail->profit = $profit;
                $invoiceDetail->commission = $commission;
                $invoiceDetail->save();
            }

            Log::info('Profit & commission calculated', [
                'agent_id' => $agent->id,
                'agent_type' => $agent->type_id,
                'markup' => $markup,
                'gateway_charge' => $gatewayChargePerTask,
                // 'supplier_surcharge' => $supplierSurcharge,
                'charge_bearer' => $settings->charge_bearer,
                'agent_deduction' => $agentChargeDeduction,
                'profit' => $profit,
                'commission_rate' => $agent->commission ?? 0.15,
                'commission' => $commission,
            ]);

            // ── COA entries only when commission != 0 ──
            if ($commission != 0) {
                $commissionExpenseAccount = Account::where('name', 'like', 'Commissions Expense (Agents)%')
                    ->where('company_id', $companyId)
                    ->first();

                $commissionLiabilityAccount = Account::where('name', 'like', 'Commissions (Agents)%')
                    ->where('company_id', $companyId)
                    ->first();

                $absCommission = abs($commission);

                if ($commissionExpenseAccount) {
                    JournalEntry::create([
                        'transaction_id' => $transactionId,
                        'branch_id' => $task->agent->branch_id ?? null,
                        'company_id' => $companyId,
                        'account_id' => $commissionExpenseAccount->id,
                        'task_id' => $task->id ?? null,
                        'agent_id' => $task->agent_id ?? $invoice->agent_id,
                        'invoice_id' => $invoiceId,
                        'invoice_detail_id' => $invoiceDetailId,
                        'transaction_date' => $invoice->invoice_date,
                        'description' => 'Agents Commissions for (Expenses): ' . $agent->name,
                        'debit'  => $commission > 0 ? $absCommission : 0,
                        'credit' => $commission < 0 ? $absCommission : 0,
                        'balance' => $commissionExpenseAccount->balance ?? 0,
                        'name' => $commissionExpenseAccount->name,
                        'type' => 'receivable',
                        'currency' => $task->currency ?? 'KWD',
                        'exchange_rate' => $task->exchange_rate ?? 1.00,
                        'amount' => $commission,
                    ]);
                }

                if ($commissionLiabilityAccount) {
                    JournalEntry::create([
                        'transaction_id' => $transactionId,
                        'branch_id' => $task->agent->branch_id ?? null,
                        'company_id' => $companyId,
                        'account_id' => $commissionLiabilityAccount->id,
                        'task_id' => $task->id ?? null,
                        'agent_id' => $task->agent_id ?? $invoice->agent_id,
                        'invoice_id' => $invoiceId,
                        'invoice_detail_id' => $invoiceDetailId,
                        'transaction_date' => $invoice->invoice_date,
                        'description' => 'Agents Commissions for (Liabilities): ' . $agent->name,
                        'debit'  => $commission < 0 ? $absCommission : 0,
                        'credit' => $commission > 0 ? $absCommission : 0,
                        'balance' => $commissionLiabilityAccount->balance ?? 0,
                        'name' => $commissionLiabilityAccount->name,
                        'type' => 'payable',
                        'currency' => $task->currency ?? 'KWD',
                        'exchange_rate' => $task->exchange_rate ?? 1.00,
                        'amount' => $commission,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Profit/Commission Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoiceId]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create profit/commission entries',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Journal entries created successfully!',
        ]);
    }

    /**
     * Calculate total accountingFee from all paid partials
     * Uses ChargeService to get exact fee (no rounding)
     */
    private function calculateTotalAccountingFee(Invoice $invoice, int $companyId): float
    {
        // Non-credit partials — stored gateway_fee
        $partialFees = (float) InvoicePartial::where('invoice_id', $invoice->id)
            ->whereNotNull('payment_gateway')
            ->whereNotIn('payment_gateway', ['Credit', 'Cash'])
            ->sum('gateway_fee');

        // Credit usage — proportional fees
        $creditFees = (float) Credit::where('invoice_id', $invoice->id)
            ->where('amount', '<', 0)
            ->sum('gateway_fee');

        return round($partialFees + abs($creditFees), 3);
    }

    /**
     * Recalculate profit and commission for all details on an invoice
     * Called when gateway changes affect the accounting fee
     */
    public function recalculateProfitForInvoice(Invoice $invoice): void
    {
        $invoice->load(['invoiceDetails.task', 'agent.branch']);

        $agent = $invoice->agent;
        if (!$agent) return;

        $companyId = $agent->branch?->company_id;
        if (!$companyId) return;

        $settings = AgentCharge::getForAgent($agent->id, $companyId);
        $totalAccountingFee = $this->calculateTotalAccountingFee($invoice, $companyId);
        $taskCount = $invoice->invoiceDetails->count();
        $gatewayChargePerTask = $taskCount > 0 ? round($totalAccountingFee / $taskCount, 3) : 0;

        foreach ($invoice->invoiceDetails as $detail) {
            $markup = (float) $detail->task_price - (float) $detail->supplier_price;

            // $supplierSurcharge = $task ? $this->getSupplierSurchargeForTask($task, $companyId) : 0;
            $totalExtraCharge = $gatewayChargePerTask; // + $supplierSurcharge;
            $agentDeduction = $settings->calculateAgentChargeDeduction($totalExtraCharge);

            $profit = round($markup - $agentDeduction, 3);

            $oldCommission = (float) $detail->commission;
            $commission = 0;
            if (in_array($agent->type_id, [2, 3, 4])) {
                $rate = (float) ($agent->commission ?? 0.15);
                $commission = round($profit * $rate, 3);
            }

            $detail->profit = $profit;
            $detail->commission = $commission;
            $detail->save();

            // Update commission journal entries if changed
            $commissionDiff = round($commission - $oldCommission, 3);
            if (abs($commissionDiff) > 0.001) {
                $commissionEntries = JournalEntry::where('invoice_id', $invoice->id)
                    ->where('invoice_detail_id', $detail->id)
                    ->where('description', 'LIKE', '%Agents Commissions%')
                    ->get();

                foreach ($commissionEntries as $entry) {
                    $account = Account::find($entry->account_id);

                    if ($entry->debit > 0) {
                        $entry->debit = $commission;
                        if ($account) {
                            $account->actual_balance += $commissionDiff;
                            $account->save();
                            $entry->balance = $account->actual_balance;
                        }
                        $entry->save();
                    } elseif ($entry->credit > 0) {
                        $entry->credit = $commission;
                        if ($account) {
                            $account->actual_balance += $commissionDiff;
                            $account->save();
                            $entry->balance = $account->actual_balance;
                        }
                        $entry->save();
                    }
                }

                Log::info('Commission journal entries updated', [
                    'invoice_id' => $invoice->id,
                    'detail_id' => $detail->id,
                    'old_commission' => $oldCommission,
                    'new_commission' => $commission,
                    'diff' => $commissionDiff,
                ]);
            }
        }

        Log::info('Profit recalculated after gateway change', [
            'invoice_id' => $invoice->id,
            'total_accounting_fee' => $totalAccountingFee,
            'fee_per_task' => $gatewayChargePerTask,
        ]);
    }

    /**
     * Get supplier surcharge for a task from supplier_surcharges table
     */
    private function getSupplierSurchargeForTask($task, int $companyId): float
    {
        if (!$task || !$task->supplier_id) {
            return 0;
        }

        $supplierCompany = SupplierCompany::where('supplier_id', $task->supplier_id)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (!$supplierCompany) {
            return 0;
        }

        $totalSurcharge = 0;
        $surcharges = SupplierSurcharge::with('references')
            ->where('supplier_company_id', $supplierCompany->id)
            ->get();

        foreach ($surcharges as $surcharge) {
            if ($surcharge->charge_mode === 'task') {
                // Check if surcharge applies to task's status
                if ($surcharge->canChargeForStatus($task->status)) {
                    $totalSurcharge += $surcharge->amount;
                }
            } elseif ($surcharge->charge_mode === 'reference') {
                foreach ($surcharge->references as $ref) {
                    if ($task->reference === $ref->reference) {
                        if ($surcharge->charge_behavior === 'single' && $ref->is_charged) {
                            continue;
                        }
                        $totalSurcharge += $surcharge->amount;
                        break;
                    }
                }
            }
        }

        return (float) $totalSurcharge;
    }

    protected function createCreditPaymentCOA(Invoice $invoice, array $appliedPayments, float $totalAmount): ?Transaction
    {
        try {
            $companyId = $invoice->agent?->branch?->company_id;
            $branchId = $invoice->agent?->branch_id;

            if (!$companyId) {
                Log::warning('[CREDIT PAYMENT COA] Company ID not found', [
                    'invoice_id' => $invoice->id,
                ]);
                return null;
            }

            $liabilityAccount = null;

            $liabilities = Account::where('company_id', $companyId)
                ->where('name', 'like', 'Liabilities%')
                ->whereNull('parent_id')
                ->first();

            if ($liabilities) {
                $advances = Account::where('company_id', $companyId)
                    ->where('name', 'Advances')
                    ->where('parent_id', $liabilities->id)
                    ->first();

                if ($advances) {
                    $clientAdvance = Account::where('company_id', $companyId)
                        ->where('name', 'Client')
                        ->where('parent_id', $advances->id)
                        ->first();

                    if ($clientAdvance) {
                        $liabilityAccount = Account::where('company_id', $companyId)
                            ->where('name', 'Payment Gateway')
                            ->where('parent_id', $clientAdvance->id)
                            ->first();
                    }
                }
            }

            if (!$liabilityAccount) {
                $liabilityAccount = Account::where('company_id', $companyId)
                    ->where('name', 'Payment Gateway')
                    ->whereHas('parent', fn($q) => $q->where('name', 'Client'))
                    ->first();
            }

            $receivableAccount = null;

            $accountsReceivable = Account::where('company_id', $companyId)
                ->where('name', 'Accounts Receivable')
                ->first();

            if ($accountsReceivable) {
                $receivableAccount = Account::where('company_id', $companyId)
                    ->where('name', 'Clients')
                    ->where('parent_id', $accountsReceivable->id)
                    ->first();
            }

            if (!$receivableAccount) {
                $receivableAccount = Account::where('company_id', $companyId)
                    ->where('name', 'Clients')
                    ->whereHas('parent', fn($q) => $q->where('name', 'Accounts Receivable'))
                    ->first();
            }

            if (!$liabilityAccount || !$receivableAccount) {
                Log::warning('[CREDIT PAYMENT COA] Required accounts not found', [
                    'company_id' => $companyId,
                    'liability_found' => $liabilityAccount ? true : false,
                    'receivable_found' => $receivableAccount ? true : false,
                ]);
                return null;
            }

            $voucherList = implode(', ', array_column($appliedPayments, 'voucher_number'));

            $transaction = Transaction::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'entity_id' => $invoice->client_id,
                'entity_type' => 'Client',
                'transaction_type' => 'debit',
                'amount' => $totalAmount,
                'description' => "Credit Payment for {$invoice->invoice_number}",
                'invoice_id' => $invoice->id,
                'reference_type' => 'Payment',
                'reference_number' => $invoice->invoice_number,
                'transaction_date' => now(),
            ]);

            Log::info('[CREDIT PAYMENT COA] Created Transaction', [
                'transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
                'total_amount' => $totalAmount,
                'vouchers_used' => $voucherList,
            ]);

            foreach ($appliedPayments as $payment) {
                $voucherNumber = $payment['voucher_number'] ?? 'Client Credit';
                $amountApplied = $payment['amount_applied'] ?? 0;
                $invoicePartialId = $payment['invoice_partial_id'] ?? null;

                if ($amountApplied <= 0) continue;

                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $branchId,
                    'company_id' => $companyId,
                    'account_id' => $liabilityAccount->id,
                    'invoice_id' => $invoice->id,
                    'invoice_partial_id' => $invoicePartialId,
                    'agent_id' => $invoice->agent_id,
                    'transaction_date' => now(),
                    'description' => "Apply Client Credit from {$voucherNumber}",
                    'debit' => $amountApplied,
                    'credit' => 0,
                    'balance' => $liabilityAccount->actual_balance ?? 0,
                    'name' => $liabilityAccount->name,
                    'type' => 'payable',
                    'currency' => $invoice->currency ?? 'KWD',
                ]);

                Log::info('[CREDIT PAYMENT COA] Created DEBIT entry', [
                    'voucher' => $voucherNumber,
                    'debit' => $amountApplied,
                ]);
            }

            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'branch_id' => $branchId,
                'company_id' => $companyId,
                'account_id' => $receivableAccount->id,
                'invoice_id' => $invoice->id,
                'invoice_partial_id' => null,
                'agent_id' => $invoice->agent_id,
                'transaction_date' => now(),
                'description' => "Invoice {$invoice->invoice_number} paid via Client Credit",
                'debit' => 0,
                'credit' => $totalAmount,
                'balance' => $receivableAccount->actual_balance ?? 0,
                'name' => $receivableAccount->name,
                'type' => 'receivable',
                'currency' => $invoice->currency ?? 'KWD',
            ]);

            Log::info('[CREDIT PAYMENT COA] Created CREDIT entry', [
                'credit' => $totalAmount,
            ]);

            return $transaction;
        } catch (\Exception $e) {
            Log::error('[CREDIT PAYMENT COA] Failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Create journal entries for cash and credit payment types
     */
    public function createPaymentJournalEntries($invoice, $invoicePartial, $amount, $paymentType)
    {
        try {
            $companyId = $invoice->agent->branch->company_id;
            $branchId = $invoice->agent->branch_id;
            $clientName = $invoice->client->full_name;

            // Create transaction for the payment
            $transaction = Transaction::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'entity_id' => $invoice->client_id,
                'entity_type' => 'Client',
                'transaction_type' => 'debit',
                'amount' => $amount,
                'description' => ucfirst($paymentType) . ' payment for invoice: ' . $invoice->invoice_number,
                'invoice_id' => $invoice->id,
                'reference_type' => 'Payment',
                'transaction_date' => $invoice->invoice_date,
            ]);

            // Find required accounts
            $receivableAccount = Account::where('name', 'Accounts Receivable')
                ->where('company_id', $companyId)
                ->first();

            $clientAccount = Account::where('name', 'Clients')
                ->where('company_id', $companyId)
                ->where('parent_id', $receivableAccount->id ?? null)
                ->first();

            if (!$clientAccount) {
                throw new Exception('Client receivable account not found');
            }

            if ($paymentType === 'cash') {
                // For cash payment:
                // Only debit the client (client owes us money)
                // Invoice remains unpaid until receipt voucher is processed

                // Debit client receivable (client owes money for cash payment)
                JournalEntry::create([
                    'transaction_id' => $transaction->id,
                    'branch_id' => $branchId,
                    'company_id' => $companyId,
                    'account_id' => $clientAccount->id,
                    'invoice_id' => $invoice->id,
                    'agent_id' => $task->agent_id ?? $invoice->agent_id,
                    'transaction_date' => now(),
                    'description' => 'Cash payment obligation for client: ' . $clientName,
                    'debit' => $amount,
                    'credit' => 0,
                    'balance' => $clientAccount->actual_balance + $amount,
                    'name' => $clientName,
                    'type' => 'receivable',
                    'voucher_number' => 'CSH-' . now()->timestamp,
                    'type_reference_id' => $clientAccount->id,
                ]);

                // Update account balance
                $clientAccount->actual_balance += $amount;
                $clientAccount->save();
            }

            return ['status' => 'success'];
        } catch (Exception $e) {
            Log::error('Error creating payment journal entries: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to create payment journal entries: ' . $e->getMessage()
            ];
        }
    }

    public function clientAdd(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'nullable|string|max:15',    // Optional phone field
        ]);

        // Create a new client record
        try {
            $agent = Agent::where('email', $request->get('agent_email'))->first();

            Client::create([
                'first_name' => $request->get('name'),
                'email' => $request->get('email'),
                'status' => $request->get('status'),
                'phone' => preg_replace('/\s+/', '', $request->get('phone')),
                'address' => $request->get('address'),
                'passport_no' => $request->get('passport_no'),
                'old_passport_no' => $request->get('passport_no'),
                'agent_id' => $agent->id,
            ]);

            // Redirect to the clients list with a success message
            return response()->json('Client add successfully!');
        } catch (Exception $e) {
            Log::error('Failed to create Client: ' . $e->getMessage());
            return response()->json('Client creation failed!');
        }
    }

    public function generateInvoiceNumber($sequence)
    {
        $year = now()->year;
        return sprintf('INV-%s-%05d', $year, $sequence);
    }

    public function link(Request $request)
    {
        $user = Auth::user();
        $companyId = getCompanyId($user);

        $agents = Agent::with('branch');

        if ($user->role_id == Role::ADMIN) {
            if ($companyId) {
                $agents = $agents->whereHas('branch', fn($q) => $q->where('company_id', $companyId))->get();
            } else {
                $agents = $agents->get();
            }
        } elseif ($user->role_id == Role::COMPANY) {
            $agents = $agents->whereHas('branch', fn($q) => $q->where('company_id', $companyId))->get();
        } elseif ($user->role_id == Role::BRANCH) {
            $agents = $agents->where('branch_id', $user->branch->id)->get();
        } elseif ($user->role_id == Role::AGENT) {
            $agents = $agents->where('id', $user->agent->id)->get();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $agents = $agents->where('branch_id', $user->accountant->branch_id)->get();
        } else {
            return abort(403, 'Unauthorized action.');
        }

        $agentIds = $agents->pluck('id');
        $branches = $agents->pluck('branch')->unique('id') ?? collect();

        $invoices = Invoice::with([
            'agent.branch',
            'invoiceDetails.task.supplier',
            'invoicePartials',
            'client'
        ])
            ->whereIn('agent_id', $agentIds)
            ->whereHas('invoiceDetails.task.supplier');

        if ($request->has('search')) {
            $search = $request->input('search');
            $invoices = $invoices->where(function ($query) use ($search) {
                $searchTerm = '%' . $search . '%';

                $query->where('invoice_number', 'like', $searchTerm)
                    ->orWhere('payment_type', 'like', $searchTerm)
                    ->orWhere('status', $search)
                    ->orWhereHas('client', function ($q) use ($searchTerm) {
                        $q->where('first_name', 'like', $searchTerm)
                            ->orWhere('middle_name', 'like', $searchTerm)
                            ->orWhere('last_name', 'like', $searchTerm)
                            ->orWhere('email', 'like', $searchTerm);
                    });
            });
        }

        $invoices = $invoices->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $clients = Client::whereIn('agent_id', $agentIds)->get();
        $tasks = Task::whereIn('agent_id', $agentIds)->get();
        $suppliers = Supplier::all();
        $types = Task::distinct()->pluck('type');
        $countries = Country::all();

        return view('invoice.link', compact(
            'invoices',
            'types',
            'suppliers',
            'branches',
            'agents',
            'clients',
            'tasks',
            'countries',
            'companyId'
        ));
    }

    /**
     * Display the specified resource.
     */

    public function proforma(int $companyId, string $invoiceNumber)
    {
        $user = Auth::user();

        $invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->with('agent.branch.company', 'client', 'invoiceDetails.task.supplier')
            ->first();

        if (!$invoice) {
            if (Auth::user()) {
                return redirect()->route('invoices.index')->with('error', 'Invoice not found!');
            }
            return abort(404);
        }

        // Check authorization - similar to other invoice methods
        $hasAccess = false;
        if ($user->role_id == Role::ADMIN) {
            $hasAccess = true;
        } elseif ($user->role_id == Role::COMPANY) {
            $hasAccess = $invoice->agent->branch->company_id == $user->company->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $hasAccess = $invoice->agent->branch_id == $user->branch->id;
        } elseif ($user->role_id == Role::AGENT) {
            $hasAccess = $invoice->agent_id == $user->agent->id;
        }

        if (!$hasAccess) {
            if (Auth::user()) {
                return redirect()->route('invoices.index')->with('error', 'Unauthorized access.');
            }
            return abort(403);
        }

        $invoiceDetails = $invoice->invoiceDetails;
        $company = $invoice->agent->branch->company;

        return view('invoice.proforma', compact(
            'invoice',
            'invoiceDetails',
            'company',
        ));
    }

    public function proformaGeneratePdf(int $companyId, string $invoiceNumber)
    {
        $invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->with('agent.branch.company', 'client', 'invoiceDetails.task.supplier')
            ->first();

        if (!$invoice) {
            if (Auth::user()) {
                return redirect()->route('invoices.index')->with('error', 'Invoice not found!');
            }
            return abort(404);
        }

        $invoiceDetails = $invoice->invoiceDetails;
        $company = $invoice->agent->branch->company;

        $pdf = Pdf::loadView('invoice.proforma-pdf', compact('invoice', 'invoiceDetails', 'company'));

        return $pdf->download("Proforma_Invoice_{$invoiceNumber}.pdf");
    }

    public function show(int $companyId, string $invoiceNumber)
    {
        $invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->whereHas('agent.branch.company', function ($q) use ($companyId) {
                $q->where('id', $companyId);
            })
            ->with('agent.branch.company', 'client', 'invoiceDetails')
            ->first();

        if (!$invoice) {
            if (Auth::user()) {
                return redirect()->route('invoices.index')->with('error', 'Invoice not found!');
            }
            return abort(404);
        }

        $invoicePartials = InvoicePartial::where('invoice_number', $invoiceNumber)
            ->with('client', 'invoice', 'payment')
            ->get();

        if ($invoicePartials->isEmpty()) {
            if (Auth::user()) {
                return redirect()->route('invoices.index')->with('error', 'No invoice partials found for this invoice!');
            }

            return abort(404);
        }

        $totalGatewayFee = ['gatewayFee' => 0, 'finalAmount' => 0, 'paid_by' => 'Company', 'charge_type' => 'Percent'];

        $paidServiceCharge = $invoicePartials->where('status', 'paid')->sum('service_charge');
        $totalGatewayFee['gatewayFee'] += $paidServiceCharge;

        $canGenerateLink = false;
        foreach ($invoice->invoicePartials as $partial) {
            if ($partial->charge_id) {
                $canGenerateLink = $partial->charge ? $partial->charge->can_generate_link : false;
                break;
            }
        }

        foreach ($invoicePartials as $partial) {
            if ($partial->status !== 'paid') {
                $gatewayFee = [];
                try {
                    $gatewayFee = ChargeService::calculate(
                        $partial->amount,
                        $companyId,
                        $partial->payment_method ?? null,
                        $partial->payment_gateway
                    );
                } catch (\Exception $e) {
                    Log::error('ChargeService getFee exception in show', [
                        'message' => $e->getMessage(),
                        'gateway' => $partial->payment_gateway,
                        'company_id' => $companyId,
                    ]);
                    $gatewayFee = ['gatewayFee' => 0, 'gatewayFee' => 0, 'paid_by' => 'Company', 'charge_type' => 'Percent'];
                }
                $partial->service_charge = $gatewayFee['gatewayFee'] ?? 0.00;
                $partial->save();
                $partial->final_amount = $partial->amount + $partial->service_charge;
                $chargePayer = $gatewayFee['paid_by'] ?? 'Company';

                if ($chargePayer !== 'Company') {
                    $totalGatewayFee['gatewayFee'] += $partial->service_charge;
                    $totalGatewayFee['paid_by'] = $chargePayer;
                    $totalGatewayFee['charge_type'] = $gatewayFee['charge_type'] ?? 'Percent';
                }
            }
        }

        $totalGatewayFee['gatewayFee'] += $invoice->invoice_charge ?? 0;

        $totalGatewayFee['finalAmount'] = $invoice->sub_amount + $invoice->tax + $totalGatewayFee['gatewayFee'];
        $paidPartials = $invoicePartials->where('status', 'paid');
        $invoiceDetails = $invoice->invoiceDetails;
        $company = $invoice->agent->branch->company;

        $checkUtilizeCredit = Credit::where('invoice_id', $invoice->id)
            ->where('company_id', $companyId)
            ->where('type', 'Invoice')
            ->orderBy('id', 'asc')
            ->get();

        if ($invoice->refund) {
            return view('invoice.show-refund', compact('invoice', 'invoicePartials', 'companyId', 'totalGatewayFee', 'paidPartials'));
        }

        return view('invoice.show', compact(
            'invoice',
            'invoiceDetails',
            'invoicePartials',
            'canGenerateLink',
            'paidPartials',
            'company',
            'checkUtilizeCredit',
            'totalGatewayFee',
            'companyId',
        ));
    }

    public function showArabic($companyId, $invoiceNumber)
    {
        $invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->whereHas('agent.branch.company', function ($q) use ($companyId) {
                $q->where('id', $companyId);
            })
            ->with('agent.branch.company', 'client', 'invoiceDetails')
            ->first();

        if (!$invoice) {
            if (Auth::user()) {
                return redirect()->route('invoices.index')->with('error', 'Invoice not found!');
            }
            return abort(404);
        }

        if ($invoice->status === 'paid by refund') {
            return redirect()->route('invoices.index')->withErrors(['error' => 'This invoice has already been settled through a refund']);
        }

        $invoicePartials = InvoicePartial::where('invoice_number', $invoiceNumber)
            ->with('client', 'invoice', 'payment')
            ->get();

        if ($invoicePartials->isEmpty()) {
            if (Auth::user()) {
                return redirect()->route('invoices.index')->with('error', 'No invoice partials found for this invoice!');
            }
            return abort(404);
        }

        $totalGatewayFee = ['gatewayFee' => 0, 'finalAmount' => 0, 'paid_by' => 'Company', 'charge_type' => 'Percent'];

        $paidServiceCharge = $invoicePartials->where('status', 'paid')->sum('service_charge');
        $totalGatewayFee['gatewayFee'] += $paidServiceCharge;

        $canGenerateLink = false;
        foreach ($invoice->invoicePartials as $partial) {
            if ($partial->charge_id) {
                $canGenerateLink = $partial->charge ? $partial->charge->can_generate_link : false;
                break;
            }
        }

        foreach ($invoicePartials as $partial) {
            if ($partial->status !== 'paid') {
                $gatewayFee = [];
                try {
                    $gatewayFee = ChargeService::calculate(
                        $partial->amount,
                        $companyId,
                        $partial->payment_method ?? null,
                        $partial->payment_gateway
                    );
                } catch (Exception $e) {
                    Log::error('ChargeService getFee exception in showArabic', [
                        'message' => $e->getMessage(),
                        'gateway' => $partial->payment_gateway,
                        'company_id' => $companyId,
                    ]);
                    $gatewayFee = ['gatewayFee' => 0, 'gatewayFee' => 0, 'paid_by' => 'Company', 'charge_type' => 'Percent'];
                }
                $partial->service_charge = $gatewayFee['gatewayFee'];
                $partial->save();
                $partial->final_amount = $partial->amount + $partial->service_charge;
                $chargePayer = $gatewayFee['paid_by'] ?? 'Company';

                if ($chargePayer !== 'Company') {
                    $totalGatewayFee['gatewayFee'] += $partial->service_charge;
                    $totalGatewayFee['paid_by'] = $chargePayer;
                    $totalGatewayFee['charge_type'] = $gatewayFee['charge_type'] ?? 'Percent';
                }
            }
        }

        $totalGatewayFee['gatewayFee'] += $invoice->invoice_charge ?? 0;
        $totalGatewayFee['finalAmount'] = $invoice->sub_amount + $invoice->tax + $totalGatewayFee['gatewayFee'];
        $paidPartials = $invoicePartials->where('status', 'paid');
        $invoiceDetails = $invoice->invoiceDetails;
        $company = $invoice->agent->branch->company;

        $checkUtilizeCredit = Credit::where('invoice_id', $invoice->id)
            ->where('company_id', $companyId)
            ->where('type', 'Invoice')
            ->orderBy('id', 'asc')
            ->get();

        // Render the Arabic view (make sure to translate it)
        return view('invoice.show-arabic', compact(
            'invoice',
            'invoiceDetails',
            'invoicePartials',
            'paidPartials',
            'company',
            'checkUtilizeCredit',
            'totalGatewayFee',
            'companyId',
            'canGenerateLink',
        ));
    }

    public function generatePdf(string $invoiceNumber)
    {

        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails')->first();
        Log::info('invoice', ['invoice' => $invoice]);
        $invoicePartials = InvoicePartial::where('invoice_number', $invoiceNumber)->with('client', 'invoice')->get();
        $invoiceDetails = $invoice->invoiceDetails;

        $paymentGateway = $invoicePartials->first()?->payment_gateway ?? 'tap';

        $pdf = Pdf::loadView('invoice.pdf', compact('invoice', 'invoiceDetails', 'invoicePartials', 'paymentGateway'));

        return $pdf->download("Invoice_{$invoiceNumber}.pdf");
    }

    public function split(string $invoiceNumber, int $clientId, int $partialId)
    {
        // Retrieve the invoice based on the invoice number
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails')->first();
        $invoicePartial = InvoicePartial::where('id', $partialId)
            ->where('invoice_number', $invoiceNumber)
            ->with('client', 'invoice')
            ->first();

        // Check if the invoice exists
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }

        $invoicePartial->expiry_date = \Carbon\Carbon::parse($invoicePartial->expiry_date);
        $invoiceDetails = $invoice->invoiceDetails;

        $gatewayFee = [];
        $canGenerateLink = $invoicePartial->charge ? $invoicePartial->charge->can_generate_link : false;

        if ($invoicePartial->status !== 'paid' && $canGenerateLink) {
            try {
                $paymentGateway = $invoicePartial->payment_gateway ?? 'Tap';
                $paymentMethod = $invoicePartial->payment_method;
                $companyId = $invoice->agent->branch->company_id;

                // ✅ One unified call for ALL gateways
                $gatewayFee = ChargeService::calculate(
                    $invoicePartial->amount,
                    $companyId,
                    $paymentMethod,
                    $paymentGateway
                );
            } catch (\Exception $e) {
                Log::error('ChargeService exception on split page', [
                    'message' => $e->getMessage(),
                    'partial_id' => $partialId
                ]);
                $gatewayFee = ['gatewayFee' => 0, 'paid_by' => 'Company'];
            }
            $invoicePartial->service_charge = ($gatewayFee['paid_by'] === 'Company') ? 0 : $gatewayFee['gatewayFee'];
            $invoicePartial->save();
            $invoicePartial->final_amount = $invoicePartial->amount + $invoicePartial->service_charge;
        } else {
            $invoicePartial->final_amount = $invoicePartial->amount;
            $gatewayFee['paid_by'] = ($invoicePartial->service_charge > 0) ? 'Client' : 'Company';
        }

        $checkUtilizeCredit = Credit::where('invoice_id', $invoice->id)
            ->where('invoice_partial_id', $invoicePartial->id)
            ->where('client_id', $invoice->client_id)
            ->orderBy('id', 'asc')
            ->get();

        $checkUtilizeCreditPartial = Credit::where('invoice_id', $invoice->id)
            ->where('invoice_partial_id', $invoicePartial->id)
            ->where('client_id', $invoice->client_id)
            ->where('type', 'Invoice')
            ->orderBy('id', 'asc')
            ->get();

        return view('invoice.split', compact(
            'invoice',
            'invoiceDetails',
            'invoicePartial',
            'checkUtilizeCredit',
            'checkUtilizeCreditPartial',
            'gatewayFee',
            'canGenerateLink'
        ));
    }

    public function splitarabic(string $invoiceNumber, int $clientId, int $partialId)
    {
        // Retrieve the invoice based on the invoice number
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->with('agent.branch.company', 'client', 'invoiceDetails')->first();
        $invoicePartial = InvoicePartial::where('id', $partialId)->where('invoice_number', $invoiceNumber)->where('client_id', $clientId)->with('client', 'invoice')->first();

        // Check if the invoice exists
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }

        $invoicePartial->expiry_date = \Carbon\Carbon::parse($invoicePartial->expiry_date);
        $invoiceDetails = $invoice->invoiceDetails;

        $gatewayFee = [];
        $canGenerateLink = $invoicePartial->charge ? $invoicePartial->charge->can_generate_link : false;

        if ($invoicePartial->status !== 'paid' && $canGenerateLink) {
            try {
                $paymentGateway = $invoicePartial->payment_gateway ?? 'Tap';
                $paymentMethod = $invoicePartial->payment_method;
                $companyId = $invoice->agent->branch->company_id;

                // ✅ One unified call for ALL gateways
                $gatewayFee = ChargeService::calculate(
                    $invoicePartial->amount,
                    $companyId,
                    $paymentMethod,
                    $paymentGateway
                );
            } catch (\Exception $e) {
                Log::error('ChargeService exception on split page', [
                    'message' => $e->getMessage(),
                    'partial_id' => $partialId
                ]);
                $gatewayFee = ['gatewayFee' => 0, 'paid_by' => 'Company'];
            }
            $invoicePartial->service_charge = ($gatewayFee['paid_by'] === 'Company') ? 0 : $gatewayFee['gatewayFee'];
            $invoicePartial->save();
            $invoicePartial->final_amount = $invoicePartial->amount + $invoicePartial->service_charge;
        } else {
            $invoicePartial->final_amount = $invoicePartial->amount;
            $gatewayFee['paid_by'] = ($invoicePartial->service_charge > 0) ? 'Client' : 'Company';
        }

        $checkUtilizeCredit = Credit::where('invoice_id', $invoice->id)
            ->where('invoice_partial_id', $invoicePartial->id)
            ->where('client_id', $invoice->client_id)
            ->orderBy('id', 'asc')
            ->get();

        $checkUtilizeCreditPartial = Credit::where('invoice_id', $invoice->id)
            ->where('invoice_partial_id', $invoicePartial->id)
            ->where('client_id', $invoice->client_id)
            ->where('type', 'Invoice')
            ->orderBy('id', 'asc')
            ->get();

        return view('invoice.split-arabic', compact(
            'invoice',
            'invoiceDetails',
            'invoicePartial',
            'checkUtilizeCredit',
            'checkUtilizeCreditPartial',
            'gatewayFee',
            'canGenerateLink'
        ));
    }

    public function sendInvoice(string $invoiceNumber)
    {

        // Retrieve the invoice based on the invoice number
        $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

        // Check if the invoice exists
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }


        // Fetch the invoice details as a list
        $invoiceDetails = InvoiceDetail::where('invoice_number', $invoiceNumber)->get();
        // Retrieve the transaction related to the invoice
        $transaction = Transaction::where('invoice_id', $invoice->id)->first();

        return view('invoice.clientInvoice', compact('invoice', 'invoiceDetails', 'transaction'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateTaskPrice(Request $request)
    {
        $request->validate([
            'task_id' => 'required|integer',
            'new_price' => 'required|numeric|min:0.01',
        ]);

        $taskId = $request->input('task_id');
        $newPrice = $request->input('new_price');

        $invoiceDetail = InvoiceDetail::where('task_id', $taskId)->first();
        if (!$invoiceDetail) {
            return response()->json(['success' => false, 'message' => 'Invoice detail not found.']);
        }

        $agent = $invoiceDetail->task->agent;

        $oldPrice = $invoiceDetail->task_price;
        $invoiceDetail->task_price = $newPrice;
        $invoiceDetail->markup_price = $newPrice - $invoiceDetail->supplier_price;
        $invoiceDetail->save();

        $journalEntries = JournalEntry::where('invoice_detail_id', $invoiceDetail->id)->get();
        foreach ($journalEntries as $entry) {
            // ...e        $journalEntries = \App\Models\JournalEntry::where('invoice_detail_id', $invoiceDetail->id)->get();
            foreach ($journalEntries as $entry) {
                if (str_contains($entry->description, 'Invoice created for (Assets)')) {
                    $entry->debit = $newPrice;
                    $entry->credit = 0;
                    $entry->amount = $newPrice;
                } elseif (str_contains($entry->description, 'Invoice created for (Income)')) {
                    $entry->debit = 0;
                    $entry->credit = $newPrice;
                    $entry->amount = $newPrice;
                } elseif (str_contains($entry->description, 'Agents Commissions for (Expenses)')) {
                    $commission = ($agent->commission ?? 0.15) * max(0, $newPrice - $invoiceDetail->supplier_price);
                    $entry->debit = $commission;
                    $entry->credit = 0;
                    $entry->amount = $commission;
                } elseif (str_contains($entry->description, 'Agents Commissions for (Liabilities)')) {
                    $commission = ($agent->commission ?? 0.15) * max(0, $newPrice - $invoiceDetail->supplier_price);
                    $entry->debit = 0;
                    $entry->credit = $commission;
                    $entry->amount = $commission;
                }
                $entry->save();
            }
        }

        $newTotal = $invoiceDetail->invoice->invoiceDetails()->sum('task_price');
        $transaction = Transaction::where('invoice_id', $invoiceDetail->invoice_id)->first();
        if ($transaction) {
            $transaction->amount = $newTotal;
            $transaction->save();
        }

        $invoice = $invoiceDetail->invoice;
        if ($invoice) {
            $invoice->amount = $newTotal;
            $invoice->sub_amount = $newTotal;
            $invoice->save();

            foreach ($invoice->invoicePartials as $partial) {
                $partial->amount = $newTotal;
                $partial->save();
            }
        }

        if ($invoiceDetail->invoice && $invoiceDetail->invoice->payment_type === 'cash') {
            $cashEntry = JournalEntry::where('invoice_id', $invoiceDetail->invoice->id)
                ->where('description', 'like', '%Cash payment obligation for client%')
                ->first();

            if ($cashEntry) {
                $cashEntry->debit  = $newTotal;
                $cashEntry->credit = 0;
                $cashEntry->amount = $newTotal;
                $cashEntry->save();
            }
        }

        return response()->json(['success' => true]);
    }

    public function updateDate(Request $request, $companyId, $invoiceNumber)
    {
        $request->validate([
            'invdate' => 'required|date',
        ]);

        $request->merge([
            'invoice_date' => $request->invdate,
            'company_id' => $companyId,
            'invoice_number' => $invoiceNumber,
        ]);

        $response = $this->updateDateProcess($request);

        if (isset($response['error'])) {
            return redirect()->back()->withErrors(['error' => $response['error']]);
        }

        return redirect()->back()->with('success', 'Invoice date, transaction date, and journal entry date updated!');
    }

    public function updateAmount(Request $request, $companyId, $invoiceNumber)
    {
        $request->validate([
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*' => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($request, $companyId, $invoiceNumber) {
            $invoice = Invoice::with(['invoiceDetails.task', 'agent', 'agent.branch', 'transactions.journalEntries'])
                ->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId))
                ->where('invoice_number', $invoiceNumber)
                ->firstOrFail();

            $transactionToReverse = $invoice->transactions()
                ->where('description', 'LIKE', 'Invoice reversal for%')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$transactionToReverse) {
                $transactionToReverse = $invoice->transactions()->first();
            }

            $oldAmount = $invoice->amount;
            $reversalTransaction = Transaction::create([
                'description' => 'Invoice reversal for: ' . $invoice->invoice_number . ' (Old Amount: ' . $oldAmount . ')',
                'invoice_id' => $invoice->id,
                'entity_id' => $transactionToReverse->entity_id,
                'entity_type' => $transactionToReverse->entity_type,
                'transaction_date' => $transactionToReverse->transaction_date,
                'reference_type' => 'Invoice',
                'transaction_type' => $transactionToReverse->transaction_type === 'debit' ? 'credit' : 'debit',
                'amount' => 0.00,
            ]);

            foreach ($transactionToReverse->journalEntries as $entry) {
                JournalEntry::create([
                    'transaction_id' => $reversalTransaction->id,
                    'account_id' => $entry->account_id,
                    'description' => $entry->description,
                    'debit' => $entry->credit,
                    'credit' => $entry->debit,
                    'company_id' => $entry->company_id,
                    'branch_id' => $entry->branch_id,
                    'invoice_id' => $entry->invoice_id,
                    'agent_id' => $invoice->agent_id,
                    'invoice_detail_id' => $entry->invoice_detail_id,
                    'transaction_date' => $entry->transaction_date,
                    'type' => $entry->type,
                    'task_id' => $entry->task_id,
                    'name' => $entry->name,
                ]);
            }

            $taskUpdates = $request->input('tasks', []);
            $newAmount = 0;
            $updatedDetails = collect();

            foreach ($invoice->invoiceDetails as $detail) {
                $newTaskAmount = $taskUpdates[$detail->task_id] ?? $detail->task_price;
                $newAmount += $newTaskAmount;

                $detail->task_price = $newTaskAmount;
                $detail->markup_price = $newTaskAmount - $detail->supplier_price;
                $detail->save();
                $updatedDetails->push($detail);

                foreach ($invoice->invoicePartials as $partial) {
                    $partial->amount = $newTaskAmount;
                    $partial->save();
                }
            }

            $invoice->amount = $newAmount;
            $invoice->sub_amount = $newAmount;
            $invoice->save();

            $correctedTransaction = Transaction::create([
                'date' => now(),
                'description' => 'Invoice: ' . $invoice->invoice_number . ' (New Amount: ' . $newAmount . ')',
                'invoice_id' => $invoice->id,
                'entity_id' => $transactionToReverse->entity_id,
                'entity_type' => $transactionToReverse->entity_type,
                'transaction_date' => $transactionToReverse->transaction_date,
                'reference_type' => 'Invoice',
                'transaction_type' => $transactionToReverse->transaction_type,
                'amount' => $newAmount,
            ]);

            foreach ($transactionToReverse->journalEntries as $entry) {
                $relevantDetail = $updatedDetails->firstWhere('id', $entry->invoice_detail_id);
                if (!$relevantDetail) continue;

                $taskSpecificAmount = $relevantDetail->task_price;
                $newDebit = 0;
                $newCredit = 0;
                $agent = $invoice->agent;

                // ── Profit for ALL agent types ──
                $markup = $taskSpecificAmount - $relevantDetail->supplier_price;
                $agentCompanyId = $agent->branch->company_id ?? $companyId;

                $settings = AgentCharge::getForAgent($agent->id, $agentCompanyId);
                $totalAccountingFee = $this->calculateTotalAccountingFee($invoice, $agentCompanyId);
                $taskCount = $invoice->invoiceDetails->count();
                $gatewayChargePerTask = $taskCount > 0 ? round($totalAccountingFee / $taskCount, 3) : 0;

                $task = $relevantDetail->task;
                // $supplierSurcharge = $task ? $this->getSupplierSurchargeForTask($task, $agentCompanyId) : 0;
                $totalExtraCharge = $gatewayChargePerTask; // + $supplierSurcharge;
                $agentDeduction = $settings->calculateAgentChargeDeduction($totalExtraCharge);

                $profit = round($markup - $agentDeduction, 3);

                // Commission ONLY for types 2, 3, 4
                $commission = 0;
                if (in_array($agent->type_id, [2, 3, 4])) {
                    $rate = (float) ($agent->commission ?? 0.15);
                    $commission = round($profit * $rate, 3);
                }

                $relevantDetail->profit = $profit;
                $relevantDetail->commission = $commission;
                $relevantDetail->save();

                if (str_contains($entry->description, 'Invoice created for (Assets)')) {
                    $newDebit = $taskSpecificAmount;
                } else if (str_contains($entry->description, 'Invoice created for (Income)')) {
                    $newCredit = $taskSpecificAmount;
                } else if (str_contains($entry->description, 'Agents Commissions for (Expenses)')) {
                    $absCommission = abs($commission);
                    $newDebit  = $commission > 0 ? $absCommission : 0;
                    $newCredit = $commission < 0 ? $absCommission : 0;
                } else if (str_contains($entry->description, 'Agents Commissions for (Liabilities)')) {
                    $absCommission = abs($commission);
                    $newDebit  = $commission < 0 ? $absCommission : 0;
                    $newCredit = $commission > 0 ? $absCommission : 0;
                }

                if ($newDebit != 0 || $newCredit != 0) {
                    JournalEntry::create([
                        'transaction_id' => $correctedTransaction->id,
                        'account_id' => $entry->account_id,
                        'description' => $entry->description,
                        'debit' => $newDebit,
                        'credit' => $newCredit,
                        'entity_id' => $entry->entity_id ?? null,
                        'entity_type' => $entry->entity_type ?? null,
                        'amount' => $newAmount,
                        'company_id' => $entry->company_id,
                        'branch_id' => $entry->branch_id,
                        'invoice_id' => $entry->invoice_id,
                        'agent_id' => $invoice->agent_id,
                        'invoice_detail_id' => $entry->invoice_detail_id,
                        'transaction_date' => $entry->transaction_date,
                        'type' => $entry->type,
                        'task_id' => $entry->task_id,
                        'name' => $entry->name,
                    ]);
                }
            }

            return back()->with('success', "Invoice updated from {$oldAmount} to {$newAmount}. Ledgers adjusted.");
        });
    }

    public function update(Request $request)
    {
        $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|integer',
            'tasks.*.description' => 'required|string',
            'tasks.*.invprice' => 'required|numeric',
            'tasks.*.supplier_id' => 'required|integer',
            'tasks.*.client_id' => 'required|integer',
            'tasks.*.agent_id' => 'required|integer',
            'invdate' => 'required|date',
            'duedate' => 'required|date',
            'subTotal' => 'required|numeric',
            'clientId' => 'required|integer',
            'agentId' => 'required|integer',
            'invoiceNumber' => 'required|string',
            'currency' => 'required|string',
        ]);

        $tasks = $request->input('tasks');
        $duedate = $request->input('duedate');
        $invdate = $request->input('invdate');
        $amount = $request->input('subTotal');
        $clientId = $request->input('clientId');
        $agentId = $request->input('agentId');
        $invoiceNumber = $request->input('invoiceNumber');
        $currency = $request->input('currency');

        $agent = Agent::where('id', $agentId)->first();
        $companyId = $agent && $agent->branch && $agent->branch->company ? $agent->branch->company->id : null;
        $branchId = $agent ? $agent->branch_id : null;

        try {
            // 🔹 Find the existing invoice
            $invoice = Invoice::where('invoice_number', $invoiceNumber)->first();

            if (!$invoice) {
                return response()->json(['error' => 'Invoice not found.'], 404);
            }

            // 🔹 Delete related records before updating
            InvoiceDetail::where('invoice_id', $invoice->id)->delete();
            Transaction::where('invoice_id', $invoice->id)->delete();
            JournalEntry::where('invoice_id', $invoice->id)->delete();

            // 🔹 Update invoice
            $invoice->update([
                'agent_id' => $agentId,
                'client_id' => $clientId,
                'sub_amount' => $amount,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'unpaid',
                'invoice_date' => $invdate,
                'due_date' => $duedate,
            ]);

            // 🔹 Re-insert related records
            foreach ($tasks as $task) {
                try {
                    $selectedtask = Task::where('id', $task['id'])->first();
                    $supplier = Supplier::where('id', $task['supplier_id'])->first();
                    $client = Client::where('id', $task['client_id'])->first();
                    $agent = Agent::where('id', $task['agent_id'])->first();

                    // Create new InvoiceDetail
                    $invoiceDetail = InvoiceDetail::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoiceNumber,
                        'task_id' => $task['id'],
                        'task_description' => $task['description'],
                        'task_remark' => $task['remark'] ?? null,
                        'client_notes' => $task['note'] ?? null,
                        'task_price' =>  $task['invprice'],
                        'supplier_price' => $selectedtask->total,
                        'markup_price' => $task['invprice'] - $selectedtask->total,
                        'paid' => false,
                    ]);

                    // Create a new Transaction
                    $transaction = Transaction::create([
                        'branch_id' => $branchId,
                        'entity_id' => $companyId,
                        'entity_type' => 'company',
                        'transaction_type' => 'credit',
                        'amount' =>  $task['invprice'],
                        'description' => 'Invoice: ' . $invoiceNumber . ' Updated',
                        'invoice_id' => $invoice->id,
                        'reference_type' => 'Invoice',
                    ]);

                    // Update General Ledger Entries
                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $branchId,
                        'company_id' => $companyId,
                        'invoice_id' =>  $invoice->id,
                        'agent_id' => $task->agent_id ?? $invoice->agent_id,
                        'account_id' =>  $supplier->id, // Example: assign supplier account
                        'invoiceDetail_id' =>  $invoiceDetail->id,
                        'transaction_date' => Carbon::now(),
                        'description' => 'Updated Payment: ' . $supplier->name,
                        'debit' => $selectedtask->total,
                        'credit' => 0,
                        'balance' => $selectedtask->total,
                        'name' => $supplier->name,
                        'type' => 'payable',
                    ]);

                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $branchId,
                        'company_id' => $companyId,
                        'invoice_id' =>  $invoice->id,
                        'agent_id' => $task->agent_id ?? $invoice->agent_id,
                        'account_id' =>  $client->id, // Example: assign client account
                        'invoiceDetail_id' =>  $invoiceDetail->id,
                        'transaction_date' => Carbon::now(),
                        'description' => 'Updated Payment received from: ' . $client->full_name,
                        'debit' => 0,
                        'credit' => $task['invprice'],
                        'balance' => $task['invprice'],
                        'name' =>  $client->full_name,
                        'type' => 'receivable',
                    ]);

                    // Update Task Status
                    $selectedtask->status = 'Assigned';
                    $selectedtask->save();
                } catch (Exception $e) {
                    Log::error('Failed to update InvoiceDetails: ' . $e->getMessage());
                    return response()->json('Failed to update InvoiceDetails for task: ' . $task['description'], 500);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully!',
                'invoiceId' => $invoice->id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update invoice: ' . $e->getMessage());
            return response()->json('Invoice update failed!', 500);
        }
    }

    public function addTask(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'task_id' => 'required|exists:tasks,id',
            'task_price' => 'required|numeric|min:0',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        if ($invoice->status === 'paid' || !empty($invoice->payment_type)) {
            return response()->json(['message' => 'Cannot add tasks to a paid or processing invoice.'], 403);
        }

        $task = Task::findOrFail($request->task_id);

        InvoiceDetail::create([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'task_id' => $task->id,
            'task_description' => $task->reference,
            'task_price' =>  $request->task_price,
            'supplier_price' => $task->total,
            'markup_price' => $request->task_price - $task->total,
            'total' => $request->task_price,
            'paid' => false,
        ]);

        $invoice->recalculateTotal();

        return response()->json(['message' => 'Task added successfully!', 'invoice_total' => $invoice->amount]);
    }

    /**
     * Remove a task from an existing unpaid invoice.
     */
    public function removeTask(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'task_id' => 'required|exists:tasks,id',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        if ($invoice->status === 'paid' || !empty($invoice->payment_type)) {
            return response()->json(['message' => 'Cannot remove tasks from a paid or processing invoice.'], 403);
        }

        $invoiceDetail = InvoiceDetail::where('invoice_id', $invoice->id)
            ->where('task_id', $request->task_id)
            ->firstOrFail();

        $invoiceDetail->delete();

        $invoice->recalculateTotal();

        return response()->json(['message' => 'Task removed successfully!', 'invoice_total' => $invoice->amount]);
    }

    public function delete(Request $request, string $id)
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found!');
        }

        try {
            InvoiceDetail::where('invoice_id', $invoice->id)->delete();
            InvoicePartial::where('invoice_id', $invoice->id)->delete();
            JournalEntry::where('invoice_id', $invoice->id)->delete();
            Transaction::where('invoice_id', $invoice->id)->delete();

            $invoice->delete();

            return redirect()->route('invoices.index')->with('status', 'Invoice deleted successfully!');
        } catch (Exception $error) {
            logger('Failed to delete invoice: ' . $error->getMessage());
            return redirect()->back()->with('error', 'Failed to delete invoice!');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function updateStatus(Request $request, Invoice $invoice)
    {

        $request->validate([
            'status' => 'required|string',
        ]);

        $invoice->status = $request->input('status');
        $invoice->save();

        return redirect()->route('invoice.index')->with('status', 'Invoice status updated successfully!');
    }

    public function showDetails(int $companyId, string $invoiceNumber)
    {
        $invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->whereHas('agent.branch.company', function ($q) use ($companyId) {
                $q->where('id', $companyId);
            })
            ->with('agent', 'client', 'invoiceDetails', 'invoiceDetails.task.paymentMethod', 'invoicePartials')
            ->first();

        if (! $invoice) {
            abort(404, 'Invoice not found.');
        }
        $company = Company::find($companyId);

        $taskIds = $invoice->invoiceDetails->pluck('task_id')->filter()->toArray();

        $journalEntries = JournalEntry::where(function ($q) use ($invoice, $taskIds) {
                $q->where('invoice_id', $invoice->id)
                  ->orWhereIn('task_id', $taskIds);
            })
            ->get();

        $journalEntries = app(JournalEntryController::class)->getJournalEntries($journalEntries);

        return view('invoice.details', compact('invoice', 'company', 'journalEntries'));
    }

    public function getTaskInvoiceStatus($taskId)
    {
        $task = Task::find($taskId);

        if (!$task) {
            return response()->json(['error' => 'Task not found!'], 404);
        }

        $invoiceDetail = InvoiceDetail::where('task_id', $taskId)->first();

        if (!$invoiceDetail) {
            return response()->json(['error' => 'Invoice detail not found!'], 404);
        }

        return response()->json(['status' => $invoiceDetail->paid]);
    }

    public function createInvoiceLinkWithClientCredit(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|integer',
            'selected_option' => 'required|string',
            'payment_gateway' => 'nullable|string',
            'payment_method' => 'nullable|string',
        ]);

        $invoiceId = $request->input('invoice_id');
        $option = $request->input('selected_option');
        $gateway = $request->input('payment_gateway');
        $method = $request->input('payment_method');

        $invoice = Invoice::find($invoiceId);

        if (!$invoice || !$invoice->client) {
            logger('Invoice or client not found', ['invoiceId' => $invoiceId]);
            return redirect()->back()->with('error', 'Something went wrong!');
        }

        $client = $invoice->client;
        $agent = $invoice->agent;
        $amount = $invoice->amount;
        $balanceCredit = Credit::getTotalCreditsByClient($client->id);
        $balance = $amount - ($balanceCredit);

        if ($balanceCredit <= 0) {
            return redirect()->back()->with('error', 'Client has no available credit balance.');
        }
        if ($balance > 0) {
            $typePayment = 'split';
        } elseif ($balance == 0) {
            $typePayment = 'full';
        }

        if ($option === 'use_credit') {
            try {
                if ($typePayment === 'full') {
                    $invoicePartial = InvoicePartial::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'client_id' => $client->id,
                        'agent_id' => $agent->id,
                        'amount' => $amount,
                        'status' => 'paid',
                        'type' => $typePayment,
                        'payment_gateway' => 'Credit',
                        'service_charge' => 0,
                    ]);

                    // Save the invoice type
                    $invoice->status = 'paid';
                    $invoice->payment_type = 'full';
                    $invoice->is_client_credit = 1;
                    $invoice->save();
                }

                if ($typePayment === 'split') {
                    $invoicePartial = InvoicePartial::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'client_id' => $client->id,
                        'agent_id' => $agent->id,
                        'amount' => $balance,
                        'status' => 'unpaid',
                        'type' => $typePayment,
                        'payment_gateway' => $gateway,
                        'payment_method' => $method ?? null,
                        'service_charge' => 0,
                        'charge_id' => Charge::where('name', $gateway)->value('id'),
                    ]);

                    //2nd partial for credit utilization
                    $invoicePartialCredit = InvoicePartial::create([
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'client_id' => $client->id,
                        'agent_id' => $agent->id,
                        'amount' => $balanceCredit,
                        'status' => 'paid',
                        'type' => $typePayment,
                        'payment_gateway' => 'Credit',
                        'service_charge' => 0,
                    ]);

                    // Save the invoice type
                    $invoice->status = 'partial';
                    $invoice->payment_type = 'split';
                    $invoice->is_client_credit = 1;
                    $invoice->save();

                    $creditSubmit = Credit::create([
                        'company_id'  => $invoice->client->agent->branch->company_id,
                        'client_id'   => $invoice->client->id,
                        'invoice_id'  => $invoice->id,
                        'invoice_partial_id'  => $invoicePartialCredit->id,
                        'type'        => 'Invoice',
                        'description' => 'Payment for ' . $invoice->invoice_number,
                        'amount'      => - ($balanceCredit),
                    ]);
                }

                // Record the transaction and journal entries
                $invoiceDetail = InvoiceDetail::where('invoice_id', $invoice->id)->first();
                $tasksId = $invoice->invoiceDetails->pluck('task_id')->toArray();

                $tasks = Task::with('invoiceDetail', 'agent')->whereIn('id', $tasksId)->get();

                DB::beginTransaction();
                try {
                    $transaction = Transaction::create([
                        'company_id' => $tasks[0]->company_id,
                        'branch_id' => $tasks[0]->agent->branch_id,
                        'entity_id' => $tasks[0]->company_id,
                        'entity_type' => 'company',
                        'transaction_type' => 'credit',
                        'amount' =>  $invoice->amount,
                        'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
                        'invoice_id' => $invoice->id,
                        'reference_type' => 'Invoice',
                        'transaction_date' => $invoice->invoice_date,
                    ]);
                } catch (Exception $e) {

                    DB::rollBack();

                    Log::error('Failed to create Transactions: ' . $e->getMessage());
                    return response()->json('Something Went Wrong', 500);
                }
                DB::commit();


                DB::beginTransaction();

                foreach ($tasks as $task) {
                    Log::info('Preparing to add journal entry', [
                        'task_id' => $task->id ?? null,
                        'invoice_id' => $invoice->id,
                        'invoice_detail_id' => $invoiceDetail->id ?? null,
                        'transaction_id' => $transaction->id ?? null,
                        'client_name' => $invoice->client->full_name ?? null,
                        'task' => $task,
                    ]);

                    $response = $this->addJournalEntry(
                        $task,
                        $invoice->id,
                        $invoiceDetail->id,
                        $transaction->id,
                        $invoice->client->full_name,
                    );

                    if ($response['status'] == 'error') {
                        DB::rollBack();
                        Log::error('Journal entry creation failed', ['response' => $response]);
                        return response()->json($response['message'], 500);
                    }
                }

                DB::commit();

                return redirect()->route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])->with('success', 'Client credit applied. Invoice link created successfully!');
            } catch (Exception $e) {
                logger('Failed to pay invoice by credit: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Failed to pay invoice by credit.');
            }
        }

        if ($option === 'generate_yes') {
            if (!$gateway) {
                return redirect()->back()->with('error', 'Payment gateway is required.');
            }

            try {
                // Create new invoice
                $newinvoice = Invoice::create([
                    'invoice_number' => $invoice->invoice_number . '-TC-' . now()->format('Yis'),
                    'agent_id' => $invoice->client->agent->id,
                    'client_id' => $invoice->client->id,
                    'sub_amount' => $balance,
                    'amount' => $balance,
                    'currency' => 'KWD',
                    'status' => 'unpaid',
                    'is_client_credit' => 2,
                    'payment_type' => 'full',
                    'invoice_date' => now(),
                    'due_date' => now(),
                ]);

                // Create invoice detail
                $newInvoiceDetail = InvoiceDetail::create([
                    'invoice_id' => $newinvoice->id,
                    'invoice_number' => $newinvoice->invoice_number,
                    'task_id' => $invoice->invoiceDetails->pluck('task_id')->first(),
                    'task_description' => 'Topup Client Credit',
                    'task_price' => $balance,
                    'paid' => false,
                ]);

                // Create invoice partial
                $invoicePartial = InvoicePartial::firstOrCreate(
                    ['invoice_id' => $newinvoice->id],
                    [
                        'invoice_number' => $newinvoice->invoice_number,
                        'client_id' => $newinvoice->client_id,
                        'agent_id' => $newinvoice->agent_id,
                        'amount' => $balance,
                        'status' => 'unpaid',
                        'type' => 'full',
                        'payment_gateway' => $gateway,
                        'payment_method' => $method ?? null,
                        'service_charge' => 0,
                        'charge_id' => Charge::where('name', $gateway)->value('id'),
                    ]
                );

                // Create payment link
                $paymentRequest = new Request([
                    'client_id' => $newinvoice->client_id,
                    'agent_id' => $newinvoice->agent_id,
                    'invoice_id' => $newinvoice->id,
                    'amount' => $balance,
                    'type' => 'full',
                    'payment_gateway' => $gateway,
                    'payment_method' => $method ?? null,
                    'notes' => 'Payment link created for invoice: ' . $newinvoice->invoice_number . ' for topup credit of: ' . $balance,
                ]);

                $paymentController = new PaymentController();
                $response = $paymentController->paymentStoreLinkProcess($paymentRequest);

                if ($response['status'] === 'error') {
                    $invoicePartial->delete();
                    return redirect()->back()->with('error', 'Failed to create payment link.');
                }

                // Create transaction & journal entry for the NEW invoice
                $tasksId = $invoice->invoiceDetails->pluck('task_id')->toArray();
                $tasks = Task::with('invoiceDetail', 'agent')->whereIn('id', $tasksId)->get();

                DB::beginTransaction();
                $transaction = Transaction::create([
                    'company_id' => $tasks[0]->company_id,
                    'branch_id' => $tasks[0]->agent->branch_id,
                    'entity_id' => $tasks[0]->company_id,
                    'entity_type' => 'company',
                    'transaction_type' => 'credit',
                    'amount' => $newinvoice->amount,
                    'description' => 'Invoice: ' . $newinvoice->invoice_number . ' Generated',
                    'invoice_id' => $newinvoice->id,
                    'reference_type' => 'Invoice',
                    'transaction_date' => $invoice->invoice_date,
                ]);

                // Add journal entries
                foreach ($tasks as $task) {
                    Log::info('Preparing to add journal entry', [
                        'task_id' => $task->id ?? null,
                        'invoice_id' => $newinvoice->id,
                        'invoice_detail_id' => $newInvoiceDetail->id ?? null,
                        'transaction_id' => $transaction->id ?? null,
                        'client_name' => $newinvoice->client->full_name ?? null,
                    ]);

                    $journalResponse = $this->addJournalEntry(
                        $task,
                        $newinvoice->id,
                        $newInvoiceDetail->id,
                        $transaction->id,
                        $newinvoice->client->full_name
                    );

                    if ($journalResponse['status'] === 'error') {
                        DB::rollBack();
                        Log::error('Journal entry creation failed', ['response' => $journalResponse]);
                        return response()->json($journalResponse['message'], 500);
                    }
                }

                DB::commit();

                $payment = $response['data'];
                return redirect()->route('payment.link.show', $payment->id)
                    ->with('status', 'Invoice link created successfully!');
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Failed to create invoice/payment link: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Something went wrong!');
            }
        }

        if ($option === 'generate_no') {
            if (!$gateway) {
                return redirect()->back()->with('error', 'Payment gateway is required.');
            }

            DB::beginTransaction();
            try {
                $invoice->status = 'paid';
                $invoice->paid_date = Carbon::now();
                $invoice->is_client_credit = 1;
                $invoice->payment_type = 'full';
                $invoice->save();

                InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $client->id,
                    'agent_id' => $agent->id,
                    'amount' => $balance,
                    'status' => 'paid',
                    'type' => 'full',
                    'payment_gateway' => 'Credit',
                    'service_charge' => 0,
                ]);

                $invoiceDetails = InvoiceDetail::where('invoice_id', $invoice->id)->get();
                $tasksId = $invoiceDetails->pluck('task_id')->toArray();
                $invoiceDetail = $invoiceDetails->first(); // For use later
                $tasks = Task::with('invoiceDetail', 'agent')->whereIn('id', $tasksId)->get();

                $transaction = Transaction::create([
                    'company_id' => $tasks[0]->company_id ?? null,
                    'branch_id' => $tasks[0]->agent->branch_id ?? null,
                    'entity_id' => $tasks[0]->company_id ?? null,
                    'entity_type' => 'company',
                    'transaction_type' => 'credit',
                    'amount' => $invoice->amount,
                    'description' => 'Invoice: ' . $invoice->invoice_number . ' Generated',
                    'invoice_id' => $invoice->id,
                    'reference_type' => 'Invoice',
                    'transaction_date' => $invoice->invoice_date,
                ]);

                foreach ($tasks as $task) {
                    Log::info('Preparing to add journal entry for insufficient funds', [
                        'task_id' => $task->id,
                        'invoice_id' => $invoice->id,
                        'invoice_detail_id' => $invoiceDetail->id ?? null,
                        'transaction_id' => $transaction->id,
                        'client_name' => $invoice->client->full_name ?? null,
                        'task' => $task,
                    ]);

                    $response = $this->addJournalEntry(
                        $task,
                        $invoice->id,
                        $invoiceDetail->id,
                        $transaction->id,
                        $invoice->client->full_name ?? null
                    );

                    if ($response['status'] === 'error') {
                        DB::rollBack();
                        Log::error('Journal entry creation failed', ['response' => $response]);
                        return response()->json($response['message'], 500);
                    }
                }

                Log::info('Processing credit deduction for client: ' . $invoice->client_id . ' for invoice ' . $invoice->id);

                $clientCredit = Credit::where('client_id', $invoice->client_id)->first();

                if ($clientCredit) {
                    $currentCredit = $clientCredit->amount;
                    $creditUsed = min($currentCredit, $invoice->amount);
                    $creditApplied = -$creditUsed;
                    $remainingDue = $invoice->amount - $creditUsed;

                    $insuffientCredit = Credit::create([
                        'company_id' => $invoice->client->agent->branch->company_id,
                        'client_id' => $invoice->client->id,
                        'invoice_id' => $invoice->id,
                        'type' => 'Invoice',
                        'description' => 'Payment for ' . $invoice->invoice_number . '. Insufficient credit of ' . $remainingDue,
                        'amount' => $creditApplied,
                    ]);

                    Log::info('Client credit successfully deducted.', [
                        'client_id' => $invoice->client_id,
                        'invoice_amount' => $invoice->amount,
                        'credit_amount' => $clientCredit->amount,
                        'credit_applied' => $creditApplied,
                    ]);
                } else {
                    Log::error('Client credit failed to deduct', [
                        'client_id' => $invoice->client_id,
                        'invoice_amount' => $invoice->amount,
                        'credit_amount' => $clientCredit->amount,
                    ]);
                }

                DB::commit();

                return redirect()->route('invoice.show', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number])->with('success', 'Invoice paid successfully!');
            } catch (Exception $e) {
                DB::rollBack();
                logger('Failed to process invoice/payment: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Something went wrong!');
            }
        }

        return redirect()->back()->with('error', 'Invalid option selected.');
    }

    public function createInvoiceWithLoss(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|integer',
            'payment_gateway' => 'required|string',
        ]);
    }

    public function accountantUpdate(Request $request)
    {

        $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'invoice_id' => 'required|integer|exists:invoices,id',
            'invoice_charge' => 'nullable',
            'amount' => 'nullable|numeric',
            'invoice_details' => 'required|array',
            'invoice_details.*' => 'required|array',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'paid_date' => 'nullable|date_format:Y-m-d\TH:i',
            'payment_type' => 'nullable|string|in:full,partial,split,credit,cash',
            'status' => 'nullable|string|in:paid,unpaid',
            'client_id' => 'nullable|integer|exists:clients,id',
            'agent_id' => 'nullable|integer|exists:agents,id',
        ]);


        $isPaid = $request->input('is_paid', true);

        $invoice = Invoice::find($request->invoice_id);

        $success = [];
        $error = [];

        if ($request->has('status') && $request->status !== $invoice->status) {
            $invoice->status = $request->status;
            $invoice->save();
            $success[] = 'Invoice status updated successfully.';
        }

        if ($request->filled('agent_id') && $request->agent_id != $invoice->agent_id) {
            $response = $this->updateAgentProcess(new Request([
                'invoice_id' => $invoice->id,
                'new_agent_id' => $request->agent_id,
            ]));
        }

        if ($request->filled('invoice_details')) {

            $originalDetails = $invoice->invoiceDetails;
            $updatingDetails = null;

            $requestInvoiceDetails = $request->input('invoice_details');
            foreach ($originalDetails as $detail) {
                if ($requestInvoiceDetails[$detail->task_id]['amount'] != $detail->task_price) {
                    $updatingDetails[$detail->task_id] = $requestInvoiceDetails[$detail->task_id]['amount'];
                }
            }

            if (!empty($updatingDetails)) {
                $responseUpdateAmount = $this->updateDetailsAmount(
                    new Request([
                        'tasks' => $updatingDetails,
                        'company_id' => $request->company_id,
                        'invoice_number' => $invoice->invoice_number,
                    ]),
                );

                if ($responseUpdateAmount->getStatusCode() !== 200) {
                    Log::error('Failed to update invoice details', [
                        'invoice_id' => $invoice->id,
                        'response' => $responseUpdateAmount->getContent(),
                    ]);
                }

                $responseData = $responseUpdateAmount->getData();

                if (isset($responseData->error)) {
                    $error[] = $responseData->error;
                }

                if (isset($responseData->success)) {
                    $success[] = $responseData->success;
                    $transactionId = $responseData->transaction_id ?? null;
                }
            }
        }

        $invoice = $invoice->fresh();

        if (empty($updatingDetails) && ($invoice->invoice_charge !== $request->invoice_charge || $invoice->amount !== $request->amount)) {
            if ($request->amount != bcadd($invoice->sub_amount, $request->invoice_charge ?? 0, 3)) {

                Log::error('Invoice amount mismatch', [
                    'invoice_id' => $invoice->id,
                    'expected_amount' => bcadd($invoice->sub_amount, $request->invoice_charge ?? 0, 3),
                    'provided_amount' => $request->amount,
                ]);

                $error[] = 'The total amount does not match the sum of sub amount and invoice charge.';
            }

            if ($request->invoice_charge !== bcsub($request->amount, $invoice->sub_amount, 3)) {

                Log::error('Invoice charge mismatch', [
                    'invoice_id' => $invoice->id,
                    'expected_charge' => bcsub($invoice->amount, $invoice->sub_amount, 2),
                    'provided_charge' => $request->invoice_charge,
                ]);

                $error[] = 'The invoice charge does not match the difference between total amount and sub amount.';
            }

            if (empty($error)) {

                $invoice->invoice_charge = $request->invoice_charge ?? 0;
                $invoice->amount = $request->amount ?? 0;
                $invoice->save();

                $success[] = 'Invoice amounts updated successfully.';
            }

            // $invoice->refresh();

            $responseUpdateAmount = $this->updateDetailsAmount(
                new Request([
                    'tasks' => $updatingDetails,
                    'company_id' => $request->company_id,
                    'invoice_number' => $invoice->invoice_number,
                ]),
            );

            if ($responseUpdateAmount->getStatusCode() !== 200) {
                Log::error('Failed to update invoice details', [
                    'invoice_id' => $invoice->id,
                    'response' => $responseUpdateAmount->getContent(),
                ]);
            }

            $responseData = $responseUpdateAmount->getData();

            if (isset($responseData->error)) {
                $error[] = $responseData->error;
            }

            if (isset($responseData->success)) {
                $success[] = $responseData->success;
                $transactionId = $responseData->transaction_id ?? null;
            }
        }

        if ($invoice->invoice_date !== $request->invoice_date) {
            $response = $this->updateDateProcess(new Request([
                'company_id' => $request->company_id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $request->invoice_date,
            ]));

            if (isset($response['error'])) {
                $error[] = $response['error'];
            }

            if (isset($response['success'])) {
                $success[] = $response['success'];
            }
        }

        if ($invoice->due_date !== $request->due_date) {
            $invoice->due_date = $request->due_date;
            $invoice->save();
            $success[] = 'Due date updated successfully.';
        }

        $paidDate = date_format(date_create($request->paid_date), 'Y-m-d H:i:s');

        if ($invoice->paid_date !== $paidDate) {
            $invoice->paid_date = $paidDate;
            $invoice->save();
            $success[] = 'Paid date updated successfully.';
        }

        if ($request->filled('payment_type') && $invoice->payment_type !== $request->payment_type) {
            $paymentTypeChangeResult = $this->handlePaymentTypeChange($invoice, $request->payment_type, $isPaid);

            if (isset($paymentTypeChangeResult['error'])) {
                $error[] = $paymentTypeChangeResult['error'];
            }

            if (isset($paymentTypeChangeResult['success'])) {
                $success[] = $paymentTypeChangeResult['success'];
            }

            if (isset($paymentTypeChangeResult['shortage_info'])) {
                session(['shortage_info' => $paymentTypeChangeResult['shortage_info']]);
                $success[] = 'Payment type changed successfully. Note: Client has insufficient credit balance.';
            }
        }

        if ($request->filled('client_id') && $invoice->client_id != $request->client_id) {

            $responseClientChange = $this->changeInvoiceClientProcess(new Request([
                'invoice_id' => $invoice->id,
                'new_client_id' => $request->client_id,
                'old_client_id' => $invoice->client_id,
            ]));

            if (isset($responseClientChange['error'])) {
                $error[] = $responseClientChange['error'];
            }

            if (isset($responseClientChange['success'])) {
                $success[] = $responseClientChange['success'];
            }
        }

        $invoice->refresh();

        $invoicePaidByOtherClientCredit = Credit::where('invoice_id', $invoice->id)
            ->where('amount', '<', 0)
            ->where('client_id', '!=', $invoice->client_id)
            ->get();

        if ($invoicePaidByOtherClientCredit->isNotEmpty()) {
            foreach ($invoicePaidByOtherClientCredit as $creditRecord) {
                Log::info('Refunded credit to client ' . $creditRecord->client->full_name . ' for invoice ' . $invoice->invoice_number);

                $existingRefund = Credit::where('invoice_id', $invoice->id)
                    ->where('client_id', $creditRecord->client_id)
                    ->where('type', Credit::INVOICE_REFUND)
                    ->where('amount', abs($creditRecord->amount));

                if ($creditRecord->invoice_partial_id !== null) {
                    $existingRefund = $existingRefund->where('invoice_partial_id', $creditRecord->invoice_partial_id);
                }

                $existingRefund = $existingRefund->first();

                if ($existingRefund) {
                    Log::info('Refund credit record already exists for client ' . $creditRecord->client->full_name . ' for invoice ' . $invoice->invoice_number);
                    continue;
                }

                $data = [
                    'company_id' => $creditRecord->company_id,
                    'client_id' => $creditRecord->client_id,
                    'invoice_id' => $invoice->id,
                    'type' => 'Invoice Refund',
                    'description' => 'Refund for invoice ' . $invoice->invoice_number . ' due to client change.',
                    'amount' => abs($creditRecord->amount),
                ];

                if ($creditRecord->invoice_partial_id !== null) {
                    $data['invoice_partial_id'] = $creditRecord->invoice_partial_id;
                }

                try {
                    Credit::create($data);
                } catch (Exception $e) {
                    Log::error('Failed to create refund credit record for client ' . $creditRecord->client->full_name . ' for invoice ' . $invoice->invoice_number . ': ' . $e->getMessage());
                    continue;
                }
            }
        }


        $return = redirect()->back();

        if ($success) $return = $return->with('success', 'Invoice updated successfully!')->with('data_success', $success);

        if ($error) $return = $return->with('error', 'There is some issue')->with('data', $error);

        return $return;
    }

    private function updateDetailsAmount(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'invoice_number' => 'required|string|exists:invoices,invoice_number',
            'tasks' => 'nullable|array',
            'tasks.*' => 'nullable|numeric|min:0',
            // 'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::find(29);

        $whoIsUser = '';

        if ($user->role_id == Role::ADMIN) {
            $whoIsUser = 'Admin';
        } else if ($user->role_id == Role::COMPANY) {
            $whoIsUser = 'Company admin ' . $user->company->name;
        } else if ($user->role_id == Role::BRANCH) {
            $whoIsUser = 'Branch admin ' . $user->branch->name;
        } else if ($user->role_id == Role::AGENT) {
            $whoIsUser = 'Agent ' . $user->agent->name;
        } else if ($user->role_id == Role::ACCOUNTANT) {
            $whoIsUser = 'Accountant ' . $user->accountant->name;
        } else {
            return response()->json(['error' => 'User role not recognized.'], 403);
        }

        Log::info('User ' . $user->name . ' (' . $whoIsUser . ') is attempting to update invoice details.', [
            'user_id' => $user->id,
            'invoice_number' => $request->invoice_number,
            'company_id' => $request->company_id,
            'tasks' => $request->tasks,
        ]);

        $companyId = $request->input('company_id');
        $invoiceNumber = $request->input('invoice_number');
        $transactionToReverse = null;

        $invoice = Invoice::with(['invoiceDetails.task', 'agent', 'agent.branch', 'transactions.journalEntries'])
            ->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId))
            ->where('invoice_number', $invoiceNumber)
            ->firstOrFail();

        if ($invoice->type == 'split') {
        }

        try {
            DB::transaction(function () use ($request, $companyId, $invoiceNumber, $whoIsUser, &$transactionToReverse, &$invoice) {

                $transactionToReverse = $invoice->transactions()->orderBy('id', 'desc')->first();

                if (!$transactionToReverse) {
                    $transactionToReverse = Transaction::where('invoice_id', $invoice->id)
                        ->where('description', 'LIKE', 'Invoice reversal for%')
                        ->orderBy('created_at', 'desc')
                        ->first();
                }

                $oldAmount = $transactionToReverse->amount;
                $reversalTransaction = Transaction::create([
                    'company_id' => $transactionToReverse->company_id,
                    'branch_id' => $transactionToReverse->branch_id,
                    'description' => 'Invoice reversal for: ' . $invoice->invoice_number . ' (Old Amount: ' . $oldAmount . ') by ' . $whoIsUser,
                    'invoice_id' => $invoice->id,
                    'entity_id' => $transactionToReverse->entity_id,
                    'entity_type' => $transactionToReverse->entity_type,
                    'transaction_date' => $transactionToReverse->transaction_date,
                    'reference_type' => 'Invoice',
                    'transaction_type' => $transactionToReverse->transaction_type === 'debit' ? 'credit' : 'debit',
                    'amount' => 0.00,
                ]);

                foreach ($transactionToReverse->journalEntries as $entry) {

                    $description = $entry->description;
                    if (!str_contains($description, 'reversal by')) {
                        $description = $entry->description . ' reversal by ' . $whoIsUser;
                    }

                    JournalEntry::create([
                        'transaction_id' => $reversalTransaction->id,
                        'account_id' => $entry->account_id,
                        'description' => $description,
                        'debit' => $entry->credit,
                        'credit' => $entry->debit,
                        'company_id' => $entry->company_id,
                        'branch_id' => $entry->branch_id,
                        'invoice_id' => $entry->invoice_id,
                        'agent_id' => $invoice->agent_id,
                        'invoice_detail_id' => $entry->invoice_detail_id,
                        'transaction_date' => $entry->transaction_date,
                        'type' => $entry->type,
                        'task_id' => $entry->task_id,
                        'name' => $entry->name,
                    ]);
                }

                $taskUpdates = $request->input('tasks', []);
                $newAmount = 0;
                $updatedDetails = collect();

                foreach ($invoice->invoiceDetails as $detail) {
                    $newTaskAmount = $taskUpdates[$detail->task_id] ?? $detail->task_price;
                    $newAmount += $newTaskAmount;

                    $detail->task_price = $newTaskAmount;
                    $detail->markup_price = $newTaskAmount - $detail->supplier_price;
                    $detail->save();
                    $updatedDetails->push($detail);

                    foreach ($invoice->invoicePartials as $partial) {
                        $partial->amount = $newTaskAmount;
                        $partial->save();
                    }
                }

                $invoice->sub_amount = $newAmount;
                $invoice->amount = $newAmount + ($invoice->invoice_charge ?? 0);
                $invoice->save();

                $correctedTransaction = Transaction::create([
                    'company_id' => $transactionToReverse->company_id,
                    'branch_id' => $transactionToReverse->branch_id,
                    'date' => now(),
                    'description' => 'Invoice: ' . $invoice->invoice_number . ' (New Amount: ' . $invoice->amount . ') by ' . $whoIsUser,
                    'invoice_id' => $invoice->id,
                    'entity_id' => $transactionToReverse->entity_id,
                    'entity_type' => $transactionToReverse->entity_type,
                    'transaction_date' => $transactionToReverse->transaction_date,
                    'reference_type' => 'Invoice',
                    'transaction_type' => $transactionToReverse->transaction_type,
                    'amount' => $invoice->amount,
                ]);

                $agent = $invoice->agent;
                $agentCompanyId = $agent->branch->company_id ?? $companyId;

                foreach ($transactionToReverse->journalEntries as $entry) {
                    $relevantDetail = $updatedDetails->firstWhere('id', $entry->invoice_detail_id);
                    if ($relevantDetail && !str_contains($entry->description, JournalEntry::ADDITIONAL_INVOICE_CHARGE)) {
                        $taskSpecificAmount = $relevantDetail->task_price;
                        $newDebit = 0;
                        $newCredit = 0;

                        // ── Profit for ALL agent types ──
                        $markup = $taskSpecificAmount - $relevantDetail->supplier_price;

                        $settings = AgentCharge::getForAgent($agent->id, $agentCompanyId);
                        $totalAccountingFee = $this->calculateTotalAccountingFee($invoice, $agentCompanyId);
                        $taskCount = $invoice->invoiceDetails->count();
                        $gatewayChargePerTask = $taskCount > 0 ? round($totalAccountingFee / $taskCount, 3) : 0;

                        $task = $relevantDetail->task;
                        // $supplierSurcharge = $task ? $this->getSupplierSurchargeForTask($task, $agentCompanyId) : 0;
                        $totalExtraCharge = $gatewayChargePerTask; // + $supplierSurcharge;
                        $agentDeduction = $settings->calculateAgentChargeDeduction($totalExtraCharge);

                        $profit = round($markup - $agentDeduction, 3);

                        // Commission ONLY for types 2, 3, 4
                        $commission = 0;
                        if (in_array($agent->type_id, [2, 3, 4])) {
                            $rate = (float) ($agent->commission ?? 0.15);
                            $commission = round($profit * $rate, 3);
                        }

                        $relevantDetail->profit = $profit;
                        $relevantDetail->commission = $commission;
                        $relevantDetail->save();

                        if (str_contains($entry->description, 'Invoice created for (Assets)')) {
                            $newDebit = $taskSpecificAmount;
                        } else if (str_contains($entry->description, 'Invoice created for (Income)')) {
                            $newCredit = $taskSpecificAmount;
                        } else if (str_contains($entry->description, 'Agents Commissions for (Expenses)')) {
                            $absCommission = abs($commission);
                            $newDebit  = $commission > 0 ? $absCommission : 0;
                            $newCredit = $commission < 0 ? $absCommission : 0;
                        } else if (str_contains($entry->description, 'Agents Commissions for (Liabilities)')) {
                            $absCommission = abs($commission);
                            $newDebit = $commission < 0 ? $absCommission : 0;
                            $newCredit = $commission > 0 ? $absCommission : 0;
                        }

                        if ($newDebit != 0 || $newCredit != 0) {

                            $description = $entry->description;

                            JournalEntry::create([
                                'transaction_id' => $correctedTransaction->id,
                                'account_id' => $entry->account_id,
                                'description' => $description,
                                'debit' => $newDebit,
                                'credit' => $newCredit,
                                'entity_id' => $entry->entity_id ?? null,
                                'entity_type' => $entry->entity_type ?? null,
                                'amount' => $invoice->amount,
                                'company_id' => $entry->company_id,
                                'branch_id' => $entry->branch_id,
                                'invoice_id' => $entry->invoice_id,
                                'agent_id' => $invoice->agent_id,
                                'invoice_detail_id' => $entry->invoice_detail_id,
                                'transaction_date' => $entry->transaction_date,
                                'type' => $entry->type,
                                'task_id' => $entry->task_id,
                                'name' => $entry->name,
                            ]);
                        }
                    } else {
                        $newDebit = 0;
                        $newCredit = 0;
                        $invoiceChargeCommission = 0;

                        if (in_array($agent->type_id, [2, 3, 4])) {
                            $invoiceChargeCommission = ($invoice->invoice_charge ?? 0) * ($agent->commission ?? 0.15);
                        }

                        if (str_contains($entry->description, 'Invoice created for (Assets)')) {
                            $newDebit = $invoice->invoice_charge;
                        } else if (str_contains($entry->description, 'Invoice created for (Income)')) {
                            $newCredit = $invoice->invoice_charge;
                        } else if (str_contains($entry->description, 'Agents Commissions for (Expenses)')) {
                            $absComm = abs($invoiceChargeCommission);
                            $newDebit  = $invoiceChargeCommission > 0 ? $absComm : 0;
                            $newCredit = $invoiceChargeCommission < 0 ? $absComm : 0;
                        } else if (str_contains($entry->description, 'Agents Commissions for (Liabilities)')) {
                            $absComm = abs($invoiceChargeCommission);
                            $newDebit  = $invoiceChargeCommission < 0 ? $absComm : 0;
                            $newCredit = $invoiceChargeCommission > 0 ? $absComm : 0;
                        }

                        if ($newDebit != 0 || $newCredit != 0) {
                            JournalEntry::create([
                                'transaction_id' => $correctedTransaction->id,
                                'account_id' => $entry->account_id,
                                'description' => $entry->description,
                                'debit' => $newDebit,
                                'credit' => $newCredit,
                                'entity_id' => $entry->entity_id ?? null,
                                'entity_type' => $entry->entity_type ?? null,
                                'amount' => $invoice->invoice_charge,
                                'company_id' => $entry->company_id,
                                'branch_id' => $entry->branch_id,
                                'invoice_id' => $entry->invoice_id,
                                'agent_id' => $invoice->agent_id,
                                'invoice_detail_id' => $entry->invoice_detail_id,
                                'transaction_date' => $entry->transaction_date,
                                'type' => $entry->type,
                                'task_id' => $entry->task_id,
                                'name' => $entry->name,
                            ]);
                        }
                    }
                }

                $journalEntriesOfInvoiceCharge = $transactionToReverse->journalEntries()->where('description', 'LIKE', '%' . JournalEntry::ADDITIONAL_INVOICE_CHARGE . '%')->get();

                if ($journalEntriesOfInvoiceCharge->isEmpty() && $invoice->invoice_charge > 0) {
                    $this->addInvoiceChargeJournalEntries($invoice, $correctedTransaction);
                    $this->agentCommissionForInvoiceCharge($invoice, $invoice->invoice_charge, 'Invoice charge');
                }
            });

            $invoice = Invoice::where('invoice_number', $invoiceNumber)->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId))->first();

            Log::info('Invoice details updated successfully', [
                'company_id' => $companyId,
                'invoice_number' => $invoiceNumber,
                'new_amount' => $invoice->amount,
                'tasks' => $request->input('tasks', []),
                'transaction_id' => $transactionToReverse->id ?? null,
            ]);

            return response()->json([
                'success' => 'Invoice updated successfully',
                'invoice_total' => $invoice->amount,
                'transaction_id' => $transactionToReverse->id ?? null
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to update invoice details: ' . $e->getMessage(), [
                'company_id' => $companyId,
                'invoice_number' => $invoiceNumber,
                'tasks' => $request->input('tasks', []),
            ]);
            return response()->json(['error' => 'Failed to update invoice details.'], 500);
        }

        Log::alert('Invoice details maybe not updated as expected because it goes outside of try-catch block', [
            'company_id' => $companyId,
            'invoice_number' => $invoiceNumber,
            'tasks' => $request->input('tasks', []),
        ]);

        return response()->json(['error' => 'No changes detected or invoice not found.'], 400);
    }

    public function updateDateProcess(Request $request): array
    {
        $request->validate([
            'invoice_date' => 'required|date',
            'company_id' => 'required|integer|exists:companies,id',
            'invoice_number' => 'required|string|exists:invoices,invoice_number',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $invoice = Invoice::whereHas('agent.branch', function ($q) use ($request) {
                    $q->where('company_id', $request->company_id);
                })->where('invoice_number', $request->invoice_number)->firstOrFail();

                $invoice->invoice_date = $request->invoice_date;
                $invoice->save();

                $transactions = Transaction::where('invoice_id', $invoice->id)->get();

                foreach ($transactions as $transaction) {
                    $transaction->transaction_date = $request->invoice_date;
                    $transaction->save();
                }
                JournalEntry::where('invoice_id', $invoice->id)->update(['transaction_date' => $request->invoice_date]);
            });
        } catch (Exception $e) {
            Log::error('Failed to update invoice date: ' . $e->getMessage(), [
                'company_id' => $request->company_id,
                'invoice_number' => $request->invoice_number,
            ]);
            return [
                'error' => 'Failed to update invoice date. Please try again later.',
            ];
        }

        return [
            'success' => 'Invoice date updated successfully.',
        ];
    }

    private function addInvoiceChargeJournalEntries(Invoice $invoice, Transaction $transaction): array
    {
        $agent = $invoice->agent;

        if (!$agent) {
            Log::error('Agent not found for invoice charge journal entry', ['invoice_id' => $invoice->id]);
            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
        }

        $companyId = $agent->branch->company_id ?? null;

        if (!$companyId) {
            Log::error('Company ID not found for invoice charge journal entry', ['invoice_id' => $invoice->id]);
            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
        }

        try {
            DB::transaction(function () use ($invoice, $transaction, $companyId, $agent) {
                try {
                    $detailsAccount = Account::where('name', 'like', 'Commission & Service Fee Income%')
                        ->where('company_id', $companyId)
                        ->first();

                    if (!$detailsAccount) {

                        $incomeAccount = Account::where('name', 'Income')
                            ->where('company_id', $companyId)
                            ->first();

                        if (!$incomeAccount) {
                            Log::error('Income account not found for company', ['company_id' => $companyId]);
                            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
                        }

                        $directIncomeAccount = Account::where('name', 'Direct Income')
                            ->where('company_id', $companyId)
                            ->where('parent_id', $incomeAccount->id)
                            ->first();

                        if (!$directIncomeAccount) {
                            Log::error('Direct Income account not found for company', ['company_id' => $companyId]);
                            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
                        }

                        $detailsAccount = Account::create([
                            'name' => 'Commision & Service Fee Income',
                            'level' => $directIncomeAccount->level + 1,
                            'parent_id' => $directIncomeAccount->id,
                            'root_id' => $incomeAccount->id,
                            'report_type' => Account::REPORT_TYPES['PROFIT_LOSS'],
                        ]);
                    }

                    JournalEntry::create([
                        'transaction_id' => $transaction->id,
                        'branch_id' => $agent->branch_id,
                        'company_id' => $companyId,
                        'account_id' => $detailsAccount->id,
                        'agent_id' => $agent->id,
                        'invoice_id' => $invoice->id,
                        'transaction_date' => $invoice->invoice_date,
                        'description' => 'Invoice created for (Income): ' . $invoice->invoice_number,
                        'debit' => 0,
                        'credit' => $invoice->invoice_charge,
                        'balance' => $detailsAccount->balance ?? 0,
                        'name' => $detailsAccount->name . ' - ' . JournalEntry::ADDITIONAL_INVOICE_CHARGE,
                        'type' => 'payable',
                        'currency' => $invoice->currency ?? 'KWD',
                        'amount' => $invoice->invoice_charge,
                    ]);
                } catch (Exception $e) {
                    Log::error('Income Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);

                    return [
                        'status' => 'error',
                        'message' => 'Failed to create income entry',
                    ];
                }


                // Client account (Asset)
                try {
                    if ($invoice->is_client_credit === 1) {
                        $liabilities = Account::where('name', 'like', 'Liabilities%')
                            ->where('company_id', $companyId)
                            ->first();

                        $advances = Account::where('name', 'Advances')
                            ->where('company_id', $companyId)
                            ->where('parent_id', optional($liabilities)->id)
                            ->first();

                        $clientAdvance = Account::where('name', 'Client')
                            ->where('company_id', $companyId)
                            ->where('parent_id', optional($advances)->id)
                            ->where('root_id', optional($liabilities)->id)
                            ->first();

                        if ($clientAdvance) {
                            JournalEntry::create([
                                'transaction_id' => $transaction->id,
                                'branch_id' => $agent->branch_id,
                                'company_id' => $companyId,
                                'account_id' => $clientAdvance->id,
                                'agent_id'       => $agent->id,
                                'invoice_id' => $invoice->id,
                                'transaction_date' => $invoice->invoice_date,
                                'description' => 'Invoice created for (Assets): ' . $invoice->client->full_name,
                                'debit' => $invoice->invoice_charge,
                                'credit' => 0,
                                'balance' => $clientAdvance->balance ?? 0,
                                'name' => $clientAdvance->name . ' - ' . JournalEntry::ADDITIONAL_INVOICE_CHARGE,
                                'type' => 'receivable',
                                'currency' => $invoice->currency ?? 'USD',
                                'amount' => $invoice->invoice_charge,
                            ]);
                        }
                    } else {
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
                                'branch_id' => $task->agent->branch_id ?? null,
                                'company_id' => $task->company_id ?? null,
                                'account_id' => $clientAccount->id,
                                'task_id' => $task->id ?? null,
                                'agent_id' => $task->agent_id ?? $invoice->agent_id,
                                'invoice_id' => $invoice->id,
                                'transaction_date' => $invoice->invoice_date,
                                'description' => 'Invoice created for (Assets): ' . $invoice->client->full_name,
                                'debit' => $invoice->invoice_charge,
                                'credit' => 0,
                                'balance' => $clientAccount->balance ?? 0,
                                'name' => $clientAccount->name . ' - ' . JournalEntry::ADDITIONAL_INVOICE_CHARGE,
                                'type' => 'receivable',
                                'currency' => $task->currency ?? 'USD',
                                'amount' => $invoice->invoice_charge,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Client Asset Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
                    return [
                        'status' => 'error',
                        'message' => 'Failed to create client asset entry',
                    ];
                }
            });
        } catch (Exception $e) {
            Log::error('Journal Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);

            return [
                'status' => 'error',
                'message' => 'Failed to create journal entries',
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Journal entries created successfully'
        ];
    }

    private function agentCommissionForInvoiceCharge(
        Invoice $invoice,
        float $newAmount,
        ?string $additionalDesc
    ): array {

        $agent = $invoice->agent;

        if (!$agent) {
            Log::error('Agent commission calculation failed: Invoice has no associated agent', ['invoice_id' => $invoice->id]);
            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
        }

        $companyId = $agent->branch->company_id;

        if (!$companyId) {
            Log::error('Agent commission calculation failed: Agent does not belong to a company', ['agent_id' => $agent->id]);
            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
        }

        $transaction = $invoice->transactions()->first();

        if (!$transaction) {
            Log::error('Agent commission calculation failed: Invoice has no associated transaction', ['invoice_id' => $invoice->id]);
            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
        }

        $transactionId = $transaction->id;

        // Commission Entries (Expense + Liability)
        try {
            if (in_array($agent->type_id, [2, 3, 4])) {
                $rate = (float) ($agent->commission ?? 0.15);
                $commission = round($rate * $newAmount, 3);

                if ($commission != 0) {
                    $absCommission = abs($commission);

                    $commissionExpenses = Account::where('name', 'like', 'Commissions Expense (Agents)%')
                        ->where('company_id', $companyId)
                        ->first();

                    $accruedCommissions = Account::where('name', 'like', 'Commissions (Agents)%')
                        ->where('company_id', $companyId)
                        ->first();

                    // EXPENSE: DEBIT if positive, CREDIT if negative
                    if ($commissionExpenses) {
                        JournalEntry::create([
                            'transaction_id' => $transactionId,
                            'branch_id' => $agent->branch_id ?? null,
                            'company_id' => $companyId,
                            'account_id' => $commissionExpenses->id,
                            'task_id' => null,
                            'agent_id' => $invoice->agent_id,
                            'invoice_id' => $invoice->id,
                            'transaction_date' => $invoice->invoice_date,
                            'description' => $additionalDesc . 'Agents Commissions for (Expenses): ' . $agent->name,
                            'debit'  => $commission > 0 ? $absCommission : 0,
                            'credit' => $commission < 0 ? $absCommission : 0,
                            'balance' => $commissionExpenses->balance ?? 0,
                            'name' => $commissionExpenses->name . ' - ' . JournalEntry::ADDITIONAL_INVOICE_CHARGE,
                            'type' => 'receivable',
                            'currency' => 'KWD',
                            'exchange_rate' => 1.00,
                            'amount' => $absCommission,
                        ]);
                    }

                    // LIABILITY: CREDIT if positive, DEBIT if negative
                    if ($accruedCommissions) {
                        JournalEntry::create([
                            'transaction_id' => $transactionId,
                            'branch_id' => $agent->branch_id ?? null,
                            'company_id' => $companyId,
                            'account_id' => $accruedCommissions->id,
                            'task_id' => null,
                            'agent_id' => $invoice->agent_id,
                            'invoice_id' => $invoice->id,
                            'transaction_date' => $invoice->invoice_date,
                            'description' => $additionalDesc . 'Agents Commissions for (Liabilities): ' . $agent->name,
                            'debit'  => $commission < 0 ? $absCommission : 0,
                            'credit' => $commission > 0 ? $absCommission : 0,
                            'balance' => $accruedCommissions->balance ?? 0,
                            'name' => $accruedCommissions->name . ' - ' . JournalEntry::ADDITIONAL_INVOICE_CHARGE,
                            'type' => 'payable',
                            'currency' => 'KWD',
                            'exchange_rate' => 1.00,
                            'amount' => $absCommission,
                        ]);
                    }
                }
            }
            return ['status' => 'success'];
        } catch (\Exception $e) {
            Log::error('Commission Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
            throw new \Exception('Failed to create commission entries: ' . $e->getMessage());
        }
    }

    private function handlePaymentTypeChange(Invoice $invoice, string $newPaymentType): array
    {
        $currentPaymentType = $invoice->payment_type;
        if ($invoice->status !== 'paid') {
            return ['error' => 'Payment type can only be changed for paid invoices.'];
        }

        $invoicePartials = $invoice->invoicePartials;

        foreach ($invoicePartials as $partial) {
            if ($partial->payment_gateway && $partial->payment_gateway !== 'Credit' && $partial->payment_gateway !== 'cash') {
                $charge = Charge::where('name', $partial->payment_gateway)->first();
                if ($charge && $charge->can_generate_link) {
                    return ['error' => 'Cannot change payment type for invoices paid through external payment gateways (MyFatoorah, Tap, etc.).'];
                }
            }
        }

        if ($currentPaymentType === $newPaymentType) {
            return ['error' => 'Payment type is already set to ' . ucfirst($newPaymentType) . '.'];
        }

        if (!in_array($currentPaymentType, ['credit', 'cash', 'full']) || !in_array($newPaymentType, ['credit', 'cash', 'full'])) {
            return ['error' => 'Currently only changes for Credit, Cash, and Full payment types are supported.'];
        }

        if ($currentPaymentType === 'credit' && $newPaymentType === 'cash') {
            return $this->changeCreditToCash($invoice);
        } elseif ($currentPaymentType === 'cash' && $newPaymentType === 'credit') {
            return $this->changeCashToCredit($invoice);
        } else if ($currentPaymentType === 'full' && $newPaymentType === 'credit') {
            return $this->changeFullToCredit($invoice);
        } else if ($currentPaymentType === 'credit' && $newPaymentType === 'full') {
            return $this->changeCreditToFull($invoice);
        }

        return ['error' => 'Unsupported payment type change.'];
    }

    private function changeCreditToCash(Invoice $invoice): array
    {
        try {
            DB::transaction(function () use ($invoice) {
                $creditPartial = $invoice->invoicePartials()
                    ->where('payment_gateway', 'Credit')
                    ->where('status', 'paid')
                    ->first();

                if (!$creditPartial) {
                    throw new Exception('No credit payment found for this invoice.');
                }

                $creditRecord = Credit::where('invoice_id', $invoice->id)
                    ->where('invoice_partial_id', $creditPartial->id)
                    ->where('amount', '<', 0)
                    ->first();

                if (!$creditRecord) {
                    throw new Exception('No credit deduction record found for this invoice.');
                }

                Credit::create([
                    'company_id' => $creditRecord->company_id,
                    'client_id' => $creditRecord->client_id,
                    'invoice_id' => $invoice->id,
                    'type' => 'Invoice Refund',
                    'description' => 'Invoice refund from changing payment type from Credit to Cash for invoice: ' . $invoice->invoice_number,
                    'amount' => abs($creditRecord->amount),
                ]);

                $creditPartial->delete();

                InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $invoice->client_id,
                    'service_charge' => 0,
                    'amount' => $invoice->amount,
                    'status' => 'paid',
                    'expiry_date' => $invoice->due_date,
                    'type' => 'cash',
                    'payment_gateway' => 'Cash',
                    'payment_method' => null,
                ]);

                $invoice->payment_type = 'cash';
                $invoice->is_client_credit = false;
                $invoice->save();

                Log::info('Successfully changed payment type from credit to cash', [
                    'invoice_id' => $invoice->id,
                    'refunded_amount' => abs($creditRecord->amount),
                ]);
            });

            return ['success' => ['Payment type successfully changed from Credit to Cash. Amount has been refunded to client credit balance.']];
        } catch (Exception $e) {
            Log::error('Failed to change payment type from credit to cash: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
            return ['error' => 'Failed to change payment type: ' . $e->getMessage()];
        }
    }


    private function changeCashToCredit(Invoice $invoice): array
    {
        try {
            $client = $invoice->client;
            $currentCredit = Credit::getTotalCreditsByClient($client->id);
            $invoiceAmount = $invoice->amount;

            $conversionResult = $this->processInvoiceToCreditConversion($invoice, $invoiceAmount, 'Cash');

            if ($currentCredit < $invoiceAmount) {
                $shortage = $invoiceAmount - $currentCredit;
                return [
                    'success' => $conversionResult['success'],
                    'shortage_info' => [
                        'available_credit' => $currentCredit,
                        'required_amount' => $invoiceAmount,
                        'shortage_amount' => $shortage,
                        'client_id' => $client->id,
                        'invoice_id' => $invoice->id,
                    ]
                ];
            }

            return $conversionResult;
        } catch (Exception $e) {
            Log::error('Failed to change payment type from cash to credit: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
            return ['error' => 'Failed to change payment type: ' . $e->getMessage()];
        }
    }

    private function changeFullToCredit(Invoice $invoice): array
    {
        try {
            $client = $invoice->client;
            $currentCredit = Credit::getTotalCreditsByClient($client->id);
            $invoiceAmount = $invoice->amount;

            $conversionResult = $this->processInvoiceToCreditConversion($invoice, $invoiceAmount, 'Full');

            if ($currentCredit < $invoiceAmount) {
                $shortage = $invoiceAmount - $currentCredit;
                return [
                    'success' => $conversionResult['success'],
                    'shortage_info' => [
                        'available_credit' => $currentCredit,
                        'required_amount' => $invoiceAmount,
                        'shortage_amount' => $shortage,
                        'client_id' => $client->id,
                        'invoice_id' => $invoice->id,
                    ]
                ];
            }

            return $conversionResult;
        } catch (Exception $e) {
            Log::error('Failed to change payment type from full to credit: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
            return ['error' => 'Failed to change payment type: ' . $e->getMessage()];
        }
    }

    private function changeCreditToFull($invoice): array
    {
        try {
            DB::transaction(function () use ($invoice) {
                $creditPartial = $invoice->invoicePartials()
                    ->where('payment_gateway', InvoicePaymentType::CREDIT)
                    ->where('status', 'paid')
                    ->first();

                if (!$creditPartial) {
                    throw new Exception('No credit payment found for this invoice.');
                }

                $creditRecord = Credit::where('invoice_id', $invoice->id)
                    ->where('invoice_partial_id', $creditPartial->id)
                    ->where('amount', '<', 0)
                    ->first();

                if (!$creditRecord) {
                    throw new Exception('No credit deduction record found for this invoice.');
                }

                Credit::create([
                    'company_id' => $creditRecord->company_id,
                    'client_id' => $creditRecord->client_id,
                    'invoice_id' => $invoice->id,
                    'invoice_partial_id' => $creditPartial->id,
                    'type' => 'Invoice Refund',
                    'description' => 'Invoice refund from changing payment type from Credit to Full for invoice: ' . $invoice->invoice_number,
                    'amount' => abs($creditRecord->amount),
                ]);

                $creditPartial->delete();

                InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $invoice->client_id,
                    'service_charge' => 0,
                    'amount' => $invoice->amount,
                    'status' => 'paid',
                    'expiry_date' => $invoice->due_date,
                    'type' => 'full',
                    'payment_gateway' => 'Full',
                    'payment_method' => null,
                ]);

                $invoice->payment_type = 'full';
                $invoice->is_client_credit = false;
                $invoice->status = 'paid';
                $invoice->save();

                Log::info('Successfully changed payment type from credit to full', [
                    'invoice_id' => $invoice->id,
                    'refunded_amount' => abs($creditRecord->amount),
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to change payment type from credit to full: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
            return ['error' => 'Failed to change payment type: ' . $e->getMessage()];
        }

        return [
            'success' => 'Payment type successfully changed from Credit to Full.'
        ];
    }

    private function processInvoiceToCreditConversion(Invoice $invoice, float $amount, string $oldType): array
    {
        try {
            DB::transaction(function () use ($invoice, $amount, $oldType) {
                $cashPartial = $invoice->invoicePartials()
                    ->where('payment_gateway', $oldType)
                    ->where('status', 'paid')
                    ->first();

                if ($cashPartial) {
                    $cashPartial->delete();
                }

                $creditPartial = InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $invoice->client_id,
                    'service_charge' => 0,
                    'amount' => $amount,
                    'status' => 'paid',
                    'expiry_date' => $invoice->due_date,
                    'type' => 'credit',
                    'payment_gateway' => 'Credit',
                    'payment_method' => null,
                ]);

                Credit::create([
                    'company_id' => $invoice->agent->branch->company_id,
                    'client_id' => $invoice->client_id,
                    'invoice_id' => $invoice->id,
                    'invoice_partial_id' => $creditPartial->id,
                    'type' => 'Invoice',
                    'description' => 'Payment for ' . $invoice->invoice_number . ' (changed from ' . $oldType . ' to Credit)',
                    'amount' => -$amount,
                ]);

                $invoice->payment_type = 'credit';
                $invoice->is_client_credit = true;
                $invoice->save();

                Log::info('Successfully changed payment type from ' . $oldType . ' to credit', [
                    'invoice_id' => $invoice->id,
                    'deducted_amount' => $amount,
                ]);
            });

            return ['success' => ['Payment type successfully changed from ' . $oldType . ' to Credit.']];
        } catch (Exception $e) {
            Log::error('Failed to process ' . $oldType . ' to credit conversion: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
            throw $e;
        }
    }

    public function createPaymentLinkForShortage(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'shortage_amount' => 'required|numeric|min:0.01',
            'client_id' => 'required|exists:clients,id',
            'payment_gateway' => 'required|string',
            'payment_method' => 'nullable|exists:payment_methods,id',
        ]);

        try {
            $invoice = Invoice::findOrFail($request->invoice_id);
            $client = Client::findOrFail($request->client_id);
            $shortageAmount = $request->shortage_amount;
            $gateway = $request->payment_gateway;
            $paymentMethodId = $request->payment_method;

            $paymentRequest = new Request([
                'client_id' => $request->client_id,
                'company_id' => $invoice->agent->branch->company_id,
                'agent_id' => $invoice->agent_id,
                'invoice_id' => $request->invoice_id,
                'amount' => $shortageAmount,
                'type' => 'full',
                'payment_gateway' => $gateway,
                'payment_method' => $paymentMethodId,
                'notes' => 'Payment link for credit shortage - Invoice: ' . $invoice->invoice_number,
            ]);

            $paymentController = new PaymentController();
            $response = $paymentController->paymentStoreLinkProcess($paymentRequest);

            if ($response['status'] === 'success') {
                return redirect()->back()->with('success', 'Payment link created successfully for the credit shortage amount.');
            } else {
                return redirect()->back()->with('error', 'Failed to create payment link: ' . ($response['message'] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
            Log::error('Failed to create payment link for shortage: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create payment link for shortage.');
        }
    }

    private function changeInvoiceClientProcess(Request $request)
    {

        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'old_client_id' => 'required|exists:clients,id',
            'new_client_id' => 'required|exists:clients,id',
        ]);

        $invoice = Invoice::find($request->invoice_id);

        if ($invoice->client_id !== $request->old_client_id) {
            return ['error' => 'The old client does not match the invoice client.'];
        }

        $invoicePartial = $invoice->invoicePartials()
            ->where('client_id', $request->old_client_id)
            ->where('status', 'paid')
            ->first();

        if (!$invoicePartial) {
            throw new Exception('No paid invoice partial found for this invoice.');
        }


        $oldClient = Client::find($request->old_client_id);
        $newClient = Client::find($request->new_client_id);

        try {
            DB::transaction(function () use ($invoice, $invoicePartial, $request, $oldClient, $newClient) {

                if ($invoice->payment_type == InvoicePaymentType::CREDIT->value) {
                    $creditRecord = Credit::where('invoice_id', $invoice->id)
                        ->where('invoice_partial_id', $invoicePartial->id)
                        ->where('amount', '<', 0)
                        ->first();

                    if (!$creditRecord) {
                        throw new Exception('No credit deduction record found for this invoice.');
                    }


                    Credit::create([
                        'company_id' => $creditRecord->company_id,
                        'client_id' => $creditRecord->client_id,
                        'invoice_id' => $invoice->id,
                        'invoice_partial_id' => $invoicePartial->id,
                        'type' => 'Invoice Refund',
                        'description' => 'Invoice refund from changing invoice client from ' . $oldClient->full_name . ' to ' . $newClient->full_name . ' for invoice: ' . $invoice->invoice_number,
                        'amount' => abs($creditRecord->amount),
                    ]);

                    Credit::create([
                        'company_id' => $creditRecord->company_id,
                        'client_id' => $request->new_client_id,
                        'invoice_id' => $invoice->id,
                        'invoice_partial_id' => $invoicePartial->id,
                        'type' => 'Invoice',
                        'description' => 'Payment for ' . $invoice->invoice_number . ' (changed client from ' . $oldClient->full_name . ' to ' . $newClient->full_name . ')',
                        'amount' => $creditRecord->amount,
                    ]);
                }

                $invoicePartial->client_id = $request->new_client_id;
                $invoicePartial->save();

                Log::info('Changing invoice client from ' . $oldClient->full_name . ' to ' . $newClient->full_name, [
                    'invoice_id' => $invoice->id,
                    'old_client_id' => $request->old_client_id,
                    'new_client_id' => $request->new_client_id,
                ]);

                $invoice->client_id = $request->new_client_id;
                $invoice->save();
            });
        } catch (Exception $e) {
            Log::error('Failed to change invoice client: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
            return ['error' => 'Failed to change invoice client: ' . $e->getMessage()];
        }

        return ['success' => 'Invoice client successfully changed.'];
    }

    private function updateAgentProcess(Request $request)
    {

        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'new_agent_id' => 'required|exists:agents,id',
        ]);

        $invoice = Invoice::find($request->invoice_id);
        $oldAgent = $invoice->agent;
        $newAgent = Agent::find($request->new_agent_id);

        try {
            DB::transaction(function () use ($invoice, $oldAgent, $newAgent) {

                Log::info('Changing invoice agent from ' . $oldAgent->id . ' to ' . $newAgent->id, [
                    'invoice_id' => $invoice->id,
                ]);
                $invoice->agent_id = $newAgent->id;
                $invoice->save();
            });
        } catch (Exception $e) {
            Log::error('Failed to change invoice agent: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);
            return ['error' => 'Failed to change invoice agent: ' . $e->getMessage()];
        }

        return ['success' => 'Invoice agent successfully changed.'];
    }

    private function getInvoiceNumberGenerated($companyId): string
    {
        $invoiceSequence = InvoiceSequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
        $currentSequence = $invoiceSequence->current_sequence;
        $invoiceNumber = $this->generateInvoiceNumber($currentSequence);
        $invoiceSequence->current_sequence++;
        $invoiceSequence->save();

        return $invoiceNumber;
    }

    public function autoGenerateInvoice(Task $task, Payment $payment): array
    {

        Log::info('Starting Auto Invoice Generation', [
            'task_id' => $task->id,
            'payment_id' => $payment->id,
        ]);


        try {
            $invoice = DB::transaction(function () use ($task, $payment) {

                $invoice = Invoice::create([
                    'invoice_number' => $this->getInvoiceNumberGenerated($task->company_id),
                    'agent_id' => $task->agent_id,
                    'client_id' => $task->client_id,
                    'company_id' => $task->company_id,
                    'sub_amount' => $payment->amount,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => 'paid',
                    'payment_type' => 'full',
                    'paid_date' => $payment->payment_date,
                    'is_client_credit' => false,
                    'invoice_date' => $task->supplier_pay_date,
                    'due_date' => $task->supplier_pay_date,
                ]);

                $invoiceDetail = InvoiceDetail::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'task_id' => $task->id,
                    'task_price' => $task->total,
                    'task_description' => $task->description,
                    'task_remark' => $task->remark,
                    'client_notes' => $task->notes,
                    'task_price' => $payment->amount,
                    'supplier_price' => $task->total,
                    'markup_price' => $payment->amount - $payment->service_charge - $task->total,
                    'paid' => true,
                ]);

                $charge = Charge::where('name', $payment->payment_gateway)->first();

                // Calculate gateway_fee for this payment
                $autoChargeResult = ChargeService::calculate(
                    (float) $payment->amount,
                    $task->company_id,
                    $payment->payment_method_id,
                    $payment->payment_gateway
                );

                $invoicePartial = InvoicePartial::create([
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_id' => $invoice->client_id,
                    'service_charge' => $payment->service_charge,
                    'gateway_fee' => $autoChargeResult['accountingFee'] ?? 0,
                    'amount' => $payment->amount,
                    'status' => 'paid',
                    'expiry_date' => $invoice->due_date,
                    'type' => 'full',
                    'payment_gateway' => $payment->payment_gateway,
                    'payment_method' => $payment->payment_method_id,
                    'payment_id' => $payment->id,
                    'charge_id' => $charge ? $charge->id : null,
                ]);

                $payment->invoice_id = $invoice->id;
                $payment->save();

                $transaction = Transaction::create([
                    'company_id' => $task->company_id,
                    'branch_id' => $task->agent->branch_id,
                    'entity_id' => $task->company_id,
                    'entity_type' => 'company',
                    'transaction_type' => 'credit',
                    'amount' => $invoice->amount,
                    'description' => 'Invoice: ' . $invoice->invoice_number . ' - Auto Generated from Payment',
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'reference_type' => 'Invoice',
                    'transaction_date' => $invoice->invoice_date,
                ]);

                $invoice->refresh();
                $invoiceDetail->refresh();
                $transaction->refresh();

                // Reload task with invoiceDetail relationship for addJournalEntry
                $task->load('invoiceDetail');

                $response = $this->addJournalEntry(
                    $task,
                    $invoice->id,
                    $invoiceDetail->id,
                    $transaction->id,
                    $invoice->client->full_name,
                );

                $responseData = json_decode($response->getContent(), true);

                if ($responseData['success'] == false) {
                    throw new Exception($responseData['message']);
                }

                Log::info('Auto Invoice Generation - Transaction and Journal Entries created', [
                    'invoice_id' => $invoice->id,
                    'transaction_id' => $transaction->id,
                    'payment_id' => $payment->id,
                ]);

                return $invoice;
            });
        } catch (Exception $e) {

            Log::error('Auto Invoice Generation Failed: ' . $e->getMessage(), [
                'task_id' => $task->id,
                'payment_id' => $payment->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Auto invoice generation failed. Please try again later.'
            ];
        }

        $url = route('invoice.show', ['companyId' => $task->company_id, 'invoiceNumber' => $invoice->invoice_number]);
        $agentMessage = "An invoice (" . $invoice->invoice_number . ") for " . $task->supplier->name . "'s task with reference of " . $task->reference . " for client " . $task->client->full_name . " has been automatically generated and PAID.\n\n" . $url;
        $clientMessage = "Dear " . $task->client->full_name . ",\n\nYour invoice (" . $invoice->invoice_number . ") for the task with reference of " . $task->reference . " has been generated and PAID.\n\n" . $url;

        $this->storeNotification([
            'user_id' => $task->agent->user_id,
            'title' => 'Invoice Generated',
            'message' => $agentMessage,
        ]);

        // (new ResayilController())->message(
        //     phone : $task->agent->phone_number,
        //     country_code : $task->agent->country_code,
        //     message : $agentMessage,
        //     isDummyNumber: env('AUTO_INVOICE_WHATSAPP_DUMMY', true)
        // );

        $agentPhoneNumber = env('AUTO_INVOICE_WHATSAPP_DUMMY', true) ? env('PHONE_LOCAL', '+60193058463') : $task->agent->country_code . $task->agent->phone_number;
        $clientPhoneNumber = env('AUTO_INVOICE_WHATSAPP_DUMMY', true) ? env('PHONE_LOCAL', '+60193058463') : $task->client->country_code . $task->client->phone;

        $n8nResponse = Http::post(env('N8N_WEBHOOK_TEST_URL'), [
            'success' => true,
            // 'agent' => [
            //     'phone_number' => $agentPhoneNumber,
            //     'message' => $message,
            // ],
            'client' => [
                'phone_number' => $clientPhoneNumber,
                'name' => $task->client->full_name,
                'message' => $clientMessage,
            ],
            'invoice' => [
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->amount,
                'currency' => $invoice->currency,
                'status' => $invoice->status,
                'url' => $url,
            ],
            'task' => [
                'reference' => $task->reference,
                'description' => $task->description,
                'hotel_voucher' => route('tasks.pdf.hotel',  $task->id),
            ],
        ]);

        Log::info('N8N Webhook Response', [
            'status' => $n8nResponse->status(),
            'body' => $n8nResponse->body(),
        ]);


        Log::info('Auto Invoice Generated Successfully', [
            'task_id' => $task->id,
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id ?? null,
        ]);

        return [
            'success' => true,
            'message' => 'Invoice generated successfully.',
            'invoice_id' => $invoice->id ?? null,
        ];
    }

    /**
     * Send invoice details via email
     */
    public function sendInvoiceEmail(Request $request, int $companyId, string $invoiceNumber)
    {
        $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'required|email',
            'send_to_agent' => 'nullable|boolean',
            'send_to_client' => 'nullable|boolean',
            'custom_emails' => 'nullable|string',
        ]);

        $invoice = Invoice::where('invoice_number', $invoiceNumber)
            ->whereHas('agent.branch.company', function ($q) use ($companyId) {
                $q->where('id', $companyId);
            })
            ->with(['client', 'agent.branch.company', 'invoiceDetails.task.supplier', 'invoicePartials'])
            ->first();

        if (!$invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found'
            ], 404);
        }

        $recipients = [];
        $sentTo = [];

        if ($request->boolean('send_to_agent') && $invoice->agent && $invoice->agent->email) {
            $recipients[] = $invoice->agent->email;
            $sentTo[] = "Agent ({$invoice->agent->name})";
        }

        if ($request->boolean('send_to_client') && $invoice->client && $invoice->client->email) {
            $recipients[] = $invoice->client->email;
            $sentTo[] = "Client ({$invoice->client->full_name})";
        }

        if ($request->filled('custom_emails')) {
            $customEmails = array_map('trim', explode(',', $request->custom_emails));
            foreach ($customEmails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                    $sentTo[] = $email;
                }
            }
        }

        $recipients = array_unique($recipients);

        if (empty($recipients)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid email recipients provided'
            ], 400);
        }

        try {
            $mailable = new \App\Mail\InvoiceMail($invoice->id);

            // if (app()->environment('local')) {
            //     $localEmail = env('EMAIL_LOCAL', 'it@alphia.net');

            //     \Illuminate\Support\Facades\Mail::to($localEmail)->send($mailable);

            //     Log::info('Invoice email sent to LOCAL override', [
            //         'invoice_number' => $invoiceNumber,
            //         'original_recipients' => $recipients,
            //         'actual_recipient' => $localEmail,
            //         'sent_by' => Auth::user()->id ?? null,
            //     ]);

            //     return response()->json([
            //         'success' => true,
            //         'message' => 'Invoice sent successfully to: ' . implode(', ', $sentTo),
            //         'recipients_count' => count($recipients)
            //     ]);
            // }

            foreach ($recipients as $recipient) {
                \Illuminate\Support\Facades\Mail::to($recipient)->send($mailable);
            }

            Log::info('Invoice email sent successfully', [
                'invoice_number' => $invoiceNumber,
                'recipients' => $recipients,
                'sent_by' => Auth::user()->id ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice sent successfully to: ' . implode(', ', $sentTo),
                'recipients_count' => count($recipients)
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send invoice email', [
                'invoice_number' => $invoiceNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available payments for a client that can be used to pay invoices
     * AJAX endpoint for loading payments when Credit gateway is selected
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAvailablePayments(Request $request): JsonResponse
    {
        Log::info('[INVOICE] getAvailablePayments - Request', [
            'client_id' => $request->input('client_id'),
            'user_id' => Auth::id(),
        ]);

        $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
        ]);

        $clientId = $request->input('client_id');
        $availablePayments = Credit::getAvailablePaymentsForClient($clientId);

        $response = [
            'success' => true,
            'payments' => array_map(function ($item) {
                return [
                    'credit_id' => $item['credit_id'],
                    'source_type' => $item['source_type'],
                    'reference_number' => $item['reference_number'],
                    'available_balance' => $item['available_balance'],
                    'date' => $item['date']?->format('d M Y'),
                    'payment_id' => $item['payment']->id ?? null,
                    'refund_id' => $item['refund_id'] ?? null,
                ];
            }, $availablePayments),
            'total_available' => array_sum(array_column($availablePayments, 'available_balance')),
        ];

        Log::info('[INVOICE] getAvailablePayments - Response', [
            'client_id' => $clientId,
            'payment_count' => count($availablePayments),
            'total_available' => $response['total_available'],
        ]);

        return response()->json($response);
    }

    /**
     * Apply selected payments to an invoice (credit payment with payment selection)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function applyPaymentsToInvoice(Request $request): JsonResponse
    {
        Log::info('[INVOICE] applyPaymentToInvoice - Raw Request', [
            'all' => $request->all(),
            'user_id' => Auth::id(),
        ]);

        $request->validate([
            'invoice_id' => 'required|integer|exists:invoices,id',
            'payment_allocations' => 'required|array|min:1',
            'payment_allocations.*.credit_id' => 'required|integer|exists:credits,id',
            'payment_allocations.*.amount' => 'required|numeric|min:0.001',
            'payment_mode' => 'required|in:full,partial,split',
            'other_gateway' => 'nullable|string',
            'other_method' => 'nullable|string',
            'charge_id' => 'nullable|integer',
        ]);

        Log::info('[INVOICE] applyPaymentToInvoice - Validation passed');

        $service = new PaymentApplicationService();

        $options = [];
        if ($request->input('payment_mode') === 'split') {
            $options = [
                'other_gateway' => $request->input('other_gateway'),
                'other_method' => $request->input('other_method'),
                'charge_id' => $request->input('charge_id'),
            ];
        }

        $result = $service->applyPaymentsToInvoice(
            $request->input('invoice_id'),
            $request->input('payment_allocations'),
            $request->input('payment_mode', 'full'),
            $options
        );

        Log::info('[INVOICE] applyPaymentToInvoice - Response', $result);

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 422);
        }
    }

    /**
     * Validate payment selection before applying
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validatePaymentSelection(Request $request): JsonResponse
    {
        $request->validate([
            'required_amount' => 'required|numeric|min:0.001',
            'payment_allocations' => 'required|array|min:1',
            'payment_allocations.*.credit_id' => 'required|integer|exists:credits,id',
            'payment_allocations.*.amount' => 'required|numeric|min:0.001',
        ]);

        $service = new PaymentApplicationService();

        $result = $service->validatePaymentSelection(
            $request->input('payment_allocations'),
            $request->input('required_amount')
        );

        return response()->json($result);
    }

    /**
     * Get payment history for an invoice (which payments paid this invoice)
     * 
     * @param int $invoiceId
     * @return JsonResponse
     */
    public function getInvoicePaymentHistory(int $invoiceId): JsonResponse
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $service = new PaymentApplicationService();
        $applications = $service->getPaymentHistoryForInvoice($invoiceId);

        return response()->json([
            'success' => true,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->amount,
                'status' => $invoice->status,
            ],
            'payment_applications' => $applications->map(function ($app) {
                return [
                    'id' => $app->id,
                    'payment_id' => $app->payment_id,
                    'voucher_number' => $app->payment?->voucher_number,
                    'amount' => $app->amount,
                    'applied_at' => $app->applied_at?->format('Y-m-d H:i:s'),
                    'applied_by' => $app->appliedBy?->name,
                ];
            }),
            'total_paid' => $applications->sum('amount'),
        ]);
    }
}
