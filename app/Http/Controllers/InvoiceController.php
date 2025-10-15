<?php

namespace App\Http\Controllers;

use App\Enums\InvoicePaymentType;
use App\Http\Traits\NotificationTrait;
use App\Models\Account;
use App\Models\Agent;
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
use App\Models\User;
use App\Models\Credit;
use App\Models\InvoiceReceipt;
use App\Models\Setting;
use App\Services\ChargeService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redirect;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    use NotificationTrait;

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Invoice::class);

        $user = Auth::user();
        $companiesId = [];
        $agents = collect();

        if ($user->role_id == Role::ADMIN) {
            return abort(403, 'Unauthorized action.');
        } else if ($user->role_id == Role::COMPANY) {
            $companiesId[] = $user->company->id;
            $agents = Agent::whereHas('branch', fn($query) => $query->where('company_id', $user->company->id))
                ->with(['branch:id,company_id', 'branch.company:id'])
                ->get();
        } else if ($user->role_id == Role::BRANCH) {
            $companiesId[] = $user->branch->company_id;
            $agents = Agent::where('branch_id', $user->branch->id)
                ->with(['branch:id,company_id', 'branch.company:id'])
                ->get();
        } else if ($user->role_id == Role::AGENT) {
            $companiesId[] = $user->agent->branch->company_id;
            $agents = Agent::where('id', $user->agent->id)
                ->with(['branch:id,company_id', 'branch.company:id'])
                ->get();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $companyId = $user->accountant->branch->company_id;

            if ($companyId) {
                $agents = Agent::whereHas('branch', fn($q) => $q->where('company_id', $companyId))
                    ->with(['branch:id,company_id', 'branch.company:id,name'])
                    ->get();
                $companiesId[] = $companyId;
            }
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
        $totalInvoices = $filteredInvoices->count();

        $invoices = $invoices->orderBy($sortBy, $sortOrder) // 👈 Use dynamic sorting
            ->paginate(20)
            ->withQueryString();

        return view('invoice.index', compact('invoices', 'totalInvoices', 'totalNet', 'totalSales'));
    }

    public function salelist()
    {
        $user = Auth::user();

        // Ensure that the user is a company
        if ($user->role_id !== Role::COMPANY) {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        // Get all agents under the company
        $agents = Agent::with(['branch' => function ($query) use ($user) {
            $query->where('branch_id', $user->company->branch->id);
        }])->pluck('id');

        // Get invoices related to those agents
        $invoices = Invoice::where('status', 'paid')->with('agent.branch', 'client')->whereIn('agent_id', $agents)->paginate(10);

        // Get clients related to the agents
        $clients = Client::whereIn('agent_id', $agents)->get();

        // Get tasks related to the agents
        $tasks = Task::whereIn('agent_id', $agents)->get();

        $totalInvoices = $invoices->total();

        return view('invoice.salelist', compact('invoices', 'clients', 'tasks', 'totalInvoices'));
    }

    public function create(Request $request)
    {
        if (auth()->user()->role_id == Role::ADMIN) {
            return view('invoice.maintenance'); // Show the maintenance page
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

                if (!$task->is_complete) {
                    return Redirect::route('tasks.index')->with('error', 'Task does not have full information!');
                }
            }
        }

        $taskIdsArray = array_map('intval', Arr::flatten($taskIdsArray));
        if (count($taskIdsArray) !== count(Arr::flatten($taskIdsArray, 1))) {
            throw new InvalidArgumentException('Nested arrays may not be passed to whereIn method.');
        }

        $tasks = Task::with('supplier', 'agent.branch', 'invoiceDetail.invoice', 'flightDetails.countryFrom', 'flightDetails.countryTo', 'hotelDetails.hotel')->where('enabled', true);
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
                return Redirect::route('invoice.edit', ['companyId' => $task->company_id, 'invoiceNumber' => $task->invoiceDetail->invoice->invoice_number]);
            }

            // if ($task->flightDetails && (!isset($task->flightDetails->country_id_to) || !isset($task->flightDetails->country_id_from))) {
            //     return redirect()->back()->with('error', 'The task record is missing important flight data.');
            // }

            // if ($task->hotelDetails && !isset($task->hotelDetails->hotel)) {
            //     return redirect()->back()->with('error', 'The task record is missing important hotel data.');
            // }
        }

        $selectedTasks = $selectedTasks->map(function ($task) {
            $task->agent_name = $task->agent->name ?? null;
            $task->branch_name = $task->agent->branch->name ?? null;
            $task->supplier_name = $task->supplier->name ?? null;
            return $task;
        });

        $user = $request->input('user_id') ? User::find($request->input('user_id')) : Auth::user();
        $selectedCompany = null;
        $agents = collect();
        $clients = collect();
        $agentsId = [];
        if ($user->role_id == Role::ADMIN) {
            $agents = Agent::all();
            $clients = Client::all();
            $branches = Branch::all();
            $companies = Company::all();
        } else {
            // Get all agent IDs for the current user context
            if ($user->role_id == Role::COMPANY) {
                $company = Company::with('branches.agents')->find($user->company->id);
                $agents = $company->branches->flatMap->agents;
                $branches = $company->branches;
                $selectedCompany = $company;
            } elseif ($user->role_id == Role::BRANCH) {
                $agents = Agent::where('branch_id', $user->branch->id)->get();
                $agentsId = $agents->pluck('id')->toArray();
                $branches = Branch::where('company_id', $user->branch->company_id)->get();
                $selectedCompany = $user->branch->company;
            } elseif ($user->role_id == Role::AGENT) {
                $agent = $user->agent;
                $agents = Agent::where('id', $agent->id)->get();
                $agentsId = [$agent->id];
                $branches = Branch::where('company_id', $agent->branch->company_id)->get();
                $selectedCompany = $agent->branch->company;
            }
        }
        $clients = Client::where(function ($query) use ($agentsId) {
            $query->whereIn('agent_id', $agentsId)
                ->orWhereHas('agents', function ($q) use ($agentsId) {
                    $q->whereIn('agent_id', $agentsId);
                });
        })->get();
        if ($selectedTasks->count() > 0) {
            $clientIds = $selectedTasks->pluck('client_id')->unique();
            $agentIds =  $selectedTasks->pluck('agent_id')->unique();
            $selectedAgent = Agent::find($agentIds->first());
            $selectedClient = $clientIds->count() >= 1 ? Client::find($clientIds->first()) : null;
        } else {
            $selectedAgent = null;
            $selectedClient = null;
        }

        $suppliers = Supplier::all();

        if ($user->role_id == Role::ADMIN) {
            $agentId = Agent::pluck('id');
            $suppliers = Supplier::with(['companies' => function ($query) {
                $query->where('is_active', true);
            }])->get();
        } elseif ($user->role_id == Role::COMPANY) {
            $agentId = $user->company->branches->flatMap->agents->pluck('id');
            $companyId = $user->company->id;
            $agents = Agent::with('branch')->whereIn('branch_id', $branches->pluck('id'))->get();
            $agentsId = $agents->pluck('id')->toArray();
            $suppliers = Supplier::whereHas('companies', function ($query) use ($user) {
                $query->where('company_id', $user->company->id)->where('is_active', true);
            })->with('companies')->get();
        } elseif ($user->role_id == Role::BRANCH) {
            $agentId = $user->branch->agents->pluck('id');
            $companyId = $user->branch->company->id;
            $agents = Agent::with('branch')->where('branch_id', $user->branch_id)->get();
            $suppliers = Supplier::whereHas('companies', function ($query) use ($user) {
                $query->where('company_id', $user->branch->company_id)->where('is_active', true);
            })->with('companies')->get();
        } elseif ($user->role_id == Role::AGENT) {
            $agentId = (array)$user->agent->id;
            $companyId = $user->agent->branch->company->id;
            $suppliers = Supplier::whereHas('companies', function ($query) use ($user) {
                $query->where('company_id', $user->agent->branch->company_id)->where('is_active', true);
            })->with('companies')->get();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $agentId = $user->accountant->branch->flatMap->agents->pluck('id');
        } else {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $clients = Client::where(function ($query) use ($agentsId) {
            $query->whereIn('agent_id', $agentsId)
                ->orWhereHas('agents', function ($q) use ($agentsId) {
                    $q->whereIn('agent_id', $agentsId);
                });
        })->get();

        $agentId = $selectedAgent ? $selectedAgent->id : $agentId;
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
        $invoiceExpireDefault = Setting::where('key', 'invoice_expiry_days')->first();

        $invoiceExpireDefault = $invoiceExpireDefault ? date('Y-m-d', strtotime('+' . $invoiceExpireDefault->value . ' days')) : date('Y-m-d', strtotime('+5 days'));

        $invoiceSequence = InvoiceSequence::firstOrCreate(['company_id' => $companyId], ['current_sequence' => 1]);
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

    public function edit(int $companyId, string $invoiceNumber)
    {
        $user = Auth::user();
        $agents = collect();
        $branches = collect();
        $clients = collect();

        if ($user->role_id == Role::ADMIN) {
            return view('invoice.maintenance'); // Show the maintenance page
        } elseif ($user->role_id == Role::COMPANY) {
            $company = $user->company;
            $branches = $company->branches;
            $agents = $branches->pluck('agents')->flatten();
            $agentsId = $agents->pluck('id');
            $companyId = $user->company->id;

        } elseif ($user->role_id == Role::AGENT) {
            $agent = $user->agent;
            $company = $agent->branch->company;
            $branches = $company->branches;
            $agents   = $branches->pluck('agents')->flatten();
            $agentsId = $agents->pluck('id')->toArray();
            $companyId = $user->agent->branch->company_id;

        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company_id;

        } elseif ($user->role_id == Role::ACCOUNTANT) {
            return $this->accountantEdit($companyId, $invoiceNumber);
        } else {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $clients = Client::where(function ($query) use ($agentsId) {
            $query->whereIn('agent_id', $agentsId)
                ->orWhereHas('agents', function ($q) use ($agentsId) {
                    $q->whereIn('agent_id', $agentsId);
                })->get();
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

        if ($invoice->status === 'paid') return redirect()->route('invoices.index')->with(['success' => 'Invoice paid successfully!']);

        if ($invoice->status === 'paid by refund') return redirect()->route('invoices.index')->withErrors(['error' => 'The selected invoice cannot be edited']);

        if ($invoice->refund) return redirect()->route('invoices.index')->withErrors(['error' => 'The selected invoice cannot be edited']);

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

        $paymentGateways = Charge::where('is_active', true)->get();
        $invoiceGateways = Charge::where('is_active', true)
            ->where('can_generate_link', true)
            ->get();
        $invoiceCharges = Charge::where('company_id', $invoice->agent->branch->company_id)
            ->where('is_active', true)
            ->where('can_charge_invoice', true)
            ->get();
        $paymentMethods = PaymentMethod::where('is_active', true)->get();
        $myFatoorahMethods = PaymentMethod::where('is_active', true)
            ->where('company_id', $invoice->agent->branch->company_id)
            ->where('type', 'myfatoorah')
            ->get();

        $uPaymentMethods = PaymentMethod::where('is_active', true)
            ->where('company_id', $invoice->agent->branch->company_id)
            ->where('type', 'upayment')
            ->get();

        $hesabeMethods = PaymentMethod::where('is_active', true)
            ->where('type', 'hesabe')
            ->get();
        $invoiceDate = $invoice->invoice_date;
        $invprice = $invoice->amount;
        $dueDate =  $invoice->due_date;

        foreach ($paymentGateways as $gateway) {
            // Only set self_charge to amount if both are null or self_charge is explicitly null
            // but don't override self_charge if it has a value (including 0)
            if (strtolower($gateway->name) === 'myfatoorah') {
                foreach ($myFatoorahMethods as $method) {
                    if ($method->company_id == $invoice->agent->branch->company_id && $method->type == 'myfatoorah') {
                        try {
                            $method->gateway_fee = ChargeService::FatoorahCharge($invprice, $method->id, $invoice->agent->branch->company_id)['fee'] ?? 0;
                        } catch (Exception $e) {
                            Log::error('FatoorahCharge exception', [
                                'message' => $e->getMessage(),
                                'paymentMethod' => $method->id,
                                'company_id' => $invoice->agent->branch->company_id,
                            ]);
                            $method->gateway_fee = 0;
                        }
                    }
                }
            } elseif (strtolower($gateway->name) === 'hesabe') {
                foreach ($hesabeMethods as $method) {
                    if ($method->company == $invoice->agent->branch->company && $method->type == 'hesabe') {
                        try {
                            $method->gateway_fee = ChargeService::HesabeCharge($invprice, $method->id, $invoice->agent->branch->company_id)['fee'] ?? 0;
                        } catch (Exception $e) {
                            Log::error('HesabeCharge exception', [
                                'message' => $e->getMessage(),
                                'paymentMethod' => $method->id,
                                'company_id' => $invoice->agent->branch->company_id,
                            ]);
                            $method->gateway_fee = 0;
                        }
                    }
                }
            } else if (strtolower($gateway->name) === 'tap') {
                $gateway->gateway_fee = ChargeService::TapCharge([
                    'amount' => $invprice,
                    'client_id' => $invoice->client_id,
                    'agent_id' => $invoice->agent_id,
                    'currency' => $invoice->currency
                ], $gateway->name)['fee'] ?? 0;
            } else if (strtolower($gateway->name) === 'upayment') {
                $uPaymentmethods = PaymentMethod::where('is_active', true)
                    ->where('company_id', $invoice->agent->branch->company_id)
                    ->where('type', 'upayment')
                    ->get();

                foreach ($uPaymentmethods as $method) {
                    $gateway->gateway_fee = ChargeService::UPaymentCharge(
                        $invprice,
                        $method->id,
                        $invoice->agent->branch->company_id
                    )['fee'] ?? 0;
                }
            } else {
                $gateway->gateway_fee = 0;
            }
        }

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
            'myFatoorahMethods',
            'uPaymentMethods',
            'hesabeMethods',
            'can_import',
            'receiptVoucher',
        ));
    }

    public function accountantEdit($companyId,$invoiceNumber)
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
        $clientCredit = $invoice->client ? Credit::getTotalCreditsByClient($invoice->client->id) : 0;

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

        if (strtolower($validated['gateway']) === 'myfatoorah' &&  strtolower($validated['gateway']) === 'hesabe' && $validated['method']) {
            try {
                $gatewayFee = ChargeService::FatoorahCharge($validated['amount'], $validated['method'], $companyId);
            } catch (\Exception $e) {
                Log::error('FatoorahCharge exception during partial save', [
                    'message' => $e->getMessage(),
                    'paymentMethod' => $validated['method'],
                    'company_id' => $companyId,
                ]);
                $gatewayFee = null;
            }
        } elseif (strtolower($validated['gateway']) === 'hesabe' && $validated['method']) {
            try {
                $gatewayFee = ChargeService::HesabeCharge($validated['amount'], $validated['method'], $companyId);
            } catch (Exception $e) {
                Log::error('HesabeCharge exception during partial save', [
                    'message' => $e->getMessage(),
                    'paymentMethod' => $validated['method'],
                    'company_id' => $companyId,
                ]);
                $gatewayFee = null;
            }
        } else if (strtolower($validated['gateway']) === 'tap') {
            $gatewayFee = ChargeService::TapCharge([
                'amount' => $validated['amount'],
                'client_id' => $invoice->client_id,
                'agent_id' => $invoice->agent_id,
                'currency' => $invoice->currency
            ], $validated['gateway']);
        } else if (strtolower($validated['gateway']) === 'upayment') {
            $uPaymentmethods = PaymentMethod::where('is_active', true)
                ->where('company_id', $companyId)
                ->where('type', 'upayment')
                ->get();

            if ($uPaymentmethods) {
                foreach ($uPaymentmethods as $method) {
                    $gatewayFee = ChargeService::UPaymentCharge(
                        $validated['amount'],
                        $method->id,
                        $companyId
                    )['fee'] ?? 0;
                }
            }
        }

        if ($invoice) {
            Log::info('Updating payment gateway for invoice', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'new_gateway' => $validated['gateway'],
                'new_method' => $validated['method'] ?? null,
                'new_amount' => $validated['amount'],
                'gateway_fee' => $gatewayFee['fee'] ?? 0,
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
                'service_charge' => $gatewayFee['fee'] ?? 0,
                'amount' => $invoice->amount,
            ]);
        } else {
            return response()->json(['message' => 'Invoice partial not found.'], 404);
        }

        return response()->json(['message' => 'Payment method updated successfully!', 'invoice' => $invoicePartial]);
    }

    public function savePartial(Request $request): JsonResponse
    {
        Log::info('invoice.partial.payload', $request->all());
        $request->validate([
            'invoiceId' => 'required',
            'date' => 'nullable',
            'clientId' => 'required',
            'amount' => 'required',
            'type' => 'required|string',
            'invoiceNumber' => 'required|string',
            'gateway' => 'required|string',
            'method' => 'nullable|string',
            // 'credit' => 'nullable|boolean',
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
                try {
                    $receiptVoucher = new ReceiptVoucherController();
                    return $receiptVoucher->autoGenerate($invoice, $request);

                    if (! $invoice instanceof \App\Models\Invoice) {
                        Log::error('Expected Invoice, got: '.(is_object($invoice) ? get_class($invoice) : gettype($invoice)));
                        return response()->json(['ok' => false, 'message' => 'Internal type mismatch'], 500);
                    }
                } catch (Exception $e) {
                    Log::error('Failed to auto generate the receipt voucer: ' . $e->getMessage());
                    throw new \Exception('Failed to auto generate the receipt voucer: ' . $e->getMessage());
                }
            }

            $gatewayFee = 0;

            if (strtolower($gateway) === 'myfatoorah' && $method) {
                try {
                    $gatewayFee = ChargeService::FatoorahCharge($amount, $method, $companyId);
                } catch (Exception $e) {
                    Log::error('FatoorahCharge exception during partial save', [
                        'message' => $e->getMessage(),
                        'paymentMethod' => $method,
                        'company_id' => $companyId,
                    ]);
                    $gatewayFee = null;
                }
            } elseif (strtolower($gateway) === 'hesabe' && $method) {
                try {
                    $gatewayFee = ChargeService::HesabeCharge($amount, $method, $companyId);
                } catch (Exception $e) {
                    Log::error('HesabeCharge exception during partial save', [
                        'message' => $e->getMessage(),
                        'paymentMethod' => $method,
                        'company_id' => $companyId,
                    ]);
                    $gatewayFee = null;
                }
            } else if (strtolower($gateway) === 'tap') {
                $gatewayFee = ChargeService::TapCharge([
                    'amount' => $amount,
                    'client_id' => $invoice->client_id,
                    'agent_id' => $invoice->agent_id,
                    'currency' => $invoice->currency
                ], $gateway);
            } else if (strtolower($gateway) === 'upayment') {
                $uPaymentmethods = PaymentMethod::where('is_active', true)
                    ->where('company_id', $companyId)
                    ->where('type', 'upayment')
                    ->get();

                if ($uPaymentmethods) {
                    foreach ($uPaymentmethods as $methods) {
                        $gatewayFee = ChargeService::UPaymentCharge(
                            $amount,
                            $methods->id,
                            $companyId
                        )['fee'] ?? 0;
                    }
                }
            }

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
                    'service_charge' => $credit ? 0 : ($gatewayFee['fee'] ?? 0),
                    'amount' => $amount,
                    'status' => $status,
                    'expiry_date' => $date,
                    'type' => $type,
                    'payment_gateway' => $gateway,
                    'payment_method' => $method,
                    'charge_id' => Charge::where('name', $gateway)->value('id'),
                ]);

                if ($credit) {
                    //insert credit record to reduce client's existing credit balance
                    try {
                        Credit::create([
                            'company_id'  => $invoicePartial->client->agent->branch->company_id,
                            'client_id'   => $invoicePartial->client->id,
                            'invoice_id'  => $invoice->id,
                            'invoice_partial_id'  => $invoicePartial->id,
                            'type'        => 'Invoice',
                            'description' => 'Payment for ' . $invoice->invoice_number,
                            'amount'      => - ($amount),
                        ]);
                    } catch (Exception $e) {
                        Log::error('Failed to create Credit: ' . $e->getMessage());
                        throw new \Exception('Failed to create credit record: ' . $e->getMessage());
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
                $invoice->save();

                $transaction = Transaction::where('invoice_id', $invoice->id)->first();

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
                        Log::info('Journal entry response', ['response' => $response]);
                        if ($response['status'] == 'error') {
                            throw new \Exception($response['message']);
                        }
                    }
                } else {
                    Log::info('Reusing existing transaction for invoice', [
                        'invoice_id' => $invoice->id,
                        'transaction_id' => $transaction->id,
                    ]);
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
                        'paid' => false,
                    ]);
                } catch (Exception $e) {
                    $invoice->delete();
                    Log::error('Failed to create InvoiceDetails: ' . $e->getMessage());
                    return response()->json('Something Went Wrong', 500);
                }

                // Log::info('filteredPayableChild', ['filteredPayableChild' => $payableAccount->children()]);
                // if ($payableAccount) {
                //     $filteredPayableChildAccount = $payableAccount->children()
                //         ->where('reference_id', $task['supplier_id']) // Filter by child reference_id
                //         ->first(); // Get the first matching child account
                //     Log::info('filteredPayableChildAccount', ['filteredPayableChildAccount' => $filteredPayableChildAccount]);
                //     $PayablechildAccountId = $filteredPayableChildAccount ? $filteredPayableChildAccount->id : null;
                // } else {
                //     $PayablechildAccountId = null; // Handle case when no parent account is found
                // }



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
    ) {
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

        // Client account (Asset)
        try {
            if ($invoice->is_client_credit === 1) {
                $liabilities = Account::where('name', 'like', 'Liabilities%')
                    ->where('company_id', $task->company_id)
                    ->first();

                $advances = Account::where('name', 'Advances')
                    ->where('company_id', $task->company_id)
                    ->where('parent_id', optional($liabilities)->id)
                    ->first();

                $clientAdvance = Account::where('name', 'Client')
                    ->where('company_id', $task->company_id)
                    ->where('parent_id', optional($advances)->id)
                    ->where('root_id', optional($liabilities)->id)
                    ->first();

                if ($clientAdvance) {
                    JournalEntry::create([
                        'transaction_id' => $transactionId,
                        'branch_id' => $task->agent->branch_id ?? null,
                        'company_id' => $task->company_id ?? null,
                        'account_id' => $clientAdvance->id,
                        'task_id' => $task->id ?? null,
                        'agent_id'       => $task->agent_id ?? $invoice->agent_id,
                        'invoice_id' => $invoiceId,
                        'invoice_detail_id' => $invoiceDetailId,
                        'transaction_date' => $invoice->invoice_date,
                        'description' => 'Invoice created for (Assets): ' . $clientName,
                        'debit' => $task->invoiceDetail->task_price,
                        'credit' => 0,
                        'balance' => $clientAdvance->balance ?? 0,
                        'name' => $clientAdvance->name,
                        'type' => 'receivable',
                        'currency' => $task->currency ?? 'USD',
                        'exchange_rate' => $task->exchange_rate ?? 1.00,
                        'amount' => $task->invoiceDetail->task_price,
                    ]);
                }
            } else {
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
                        'currency' => $task->currency ?? 'USD',
                        'exchange_rate' => $task->exchange_rate ?? 1.00,
                        'amount' => $task->invoiceDetail->task_price,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Client Asset Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoiceId]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create client asset entry',
            ]);
        }

        // Booking account (Income)
        try {
            $detailsAccount = Account::where('name', 'like', $task['type'] == 'flight' ? 'Flight Booking%' : '%Hotel Booking%')
                ->where('company_id', $task->company_id)
                ->first();

            if ($detailsAccount) {
                JournalEntry::create([
                    'transaction_id' => $transactionId,
                    'branch_id' => $task->agent->branch_id ?? null,
                    'company_id' => $task->company_id ?? null,
                    'account_id' => $detailsAccount->id,
                    'task_id' => $task->id ?? null,    
                    'agent_id' => $task->agent_id ?? $invoice->agent_id,
                    'invoice_id' => $invoiceId,
                    'invoice_detail_id' => $invoiceDetailId,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Invoice created for (Income): ' . $task['reference'],
                    'debit' => 0,
                    'credit' => $task->invoiceDetail->task_price,
                    'balance' => $detailsAccount->balance ?? 0,
                    'name' => $detailsAccount->name,
                    'type' => 'payable',
                    'currency' => $task->currency ?? 'USD',
                    'exchange_rate' => $task->exchange_rate ?? 1.00,
                    'amount' => $task->invoiceDetail->task_price,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Income Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoiceId]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create income entry',
            ]);
        }

        // Commission (Expense)
        try {

            $agent = $task->agent;

            if (!$agent) {
                Log::error('Agent not found for task', ['task_id' => $task->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Agent not found for task',
                ]);
            }

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
                    'transaction_id' => $transactionId,
                    'branch_id' => $task->agent->branch_id ?? null,
                    'company_id' => $task->company_id ?? null,
                    'account_id' => $commissionExpenses->id,
                    'task_id' => $task->id ?? null,    
                    'agent_id' => $task->agent_id ?? $invoice->agent_id,
                    'invoice_id' => $invoiceId,
                    'invoice_detail_id' => $invoiceDetailId,
                    'transaction_date' => $invoice->invoice_date,
                    'description' => 'Agents Commissions for (Expenses): ' . $task['agent']['name'],
                    'debit' => $commission,
                    'credit' => 0,
                    'balance' => $commissionExpenses->balance ?? 0,
                    'name' => $commissionExpenses->name,
                    'type' => 'receivable',
                    'currency' => $task->currency ?? 'USD',
                    'exchange_rate' => $task->exchange_rate ?? 1.00,
                    'amount' => $commission,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Commission Expense Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoiceId]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create commission expense entry',
            ]);
        }

        // Commission (Liability)
        try {
            $agent = $task->agent;

            if (in_array($agent->type_id, [2, 3])) {
                $selling = (float) ($task->invoiceDetail->task_price ?? 0);
                $supplier = (float) ($task->total ?? 0);
                $rate = (float) ($agent->commission ?? 0.15);
                $commission = $rate * ($selling - $supplier);

                $accruedCommissions = Account::where('name', 'like', 'Commissions (Agents)%')
                    ->where('company_id', $task->company_id)
                    ->first();

                if ($accruedCommissions) {
                    JournalEntry::create([
                        'transaction_id' => $transactionId,
                        'branch_id' => $task->agent->branch_id ?? null,
                        'company_id' => $task->company_id ?? null,
                        'account_id' => $accruedCommissions->id,
                        'task_id' => $task->id ?? null,    
                        'agent_id' => $task->agent_id ?? $invoice->agent_id,
                        'invoice_id' => $invoiceId,
                        'invoice_detail_id' => $task->invoiceDetail->id,
                        'transaction_date' => $invoice->invoice_date,
                        'description' => 'Agents Commissions for (Liabilities): ' . $task['agent']['name'],
                        'debit' => 0,
                        'credit' => $commission,
                        'balance' => $accruedCommissions->balance ?? 0,
                        'name' => $accruedCommissions->name,
                        'type' => 'payable',
                        'currency' => $task->currency ?? 'USD',
                        'exchange_rate' => $task->exchange_rate ?? 1.00,
                        'amount' => $commission,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Commission Liability Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoiceId]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create commission liability entry',
            ]);
        }

        return ['status' => 'success'];
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

        $agents = Agent::with('branch');

        // Gate::authorize('viewAny', Invoice::class);
        if ($user->role_id == Role::ADMIN) {
            $agents = $agents->get();
        } else if ($user->role_id == Role::COMPANY) {
            $agents = $agents->where('branch_id', $user->company->branches->pluck('id'))->get();
        } else if ($user->role_id == Role::BRANCH) {
            $agents = $agents->where('branch_id', $user->branch->id)->get();
        } else if ($user->role_id == Role::AGENT) {
            $agents = $agents->where('id', $user->agent->id)->get();
        } else if ($user->role_id == Role::ACCOUNTANT) {
            $agents = $agents->where('branch_id', $user->accountant->branch_id)->get();
        } else {
            return abort(403, 'Unauthorized action.');
        }

        $agentIds = $agents->pluck('id');
        $branches = $agents->pluck('branch')->unique('id') ?? collect();
        // $company = $agents->pluck('branch.company')->unique('id')->first();

        $invoices = Invoice::with([
            'agent.branch',
            'invoiceDetails.task.supplier',
            'invoicePartials',
            'client'
        ])
            ->whereIn('agent_id', $agentIds)
            ->whereHas('invoiceDetails.task.supplier'); // Only invoices with suppliers


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

        $invoices = $invoices->orderBy('created_at', 'desc')->paginate(20);

        // Get clients related to the agents
        $clients = Client::whereIn('agent_id', $agentIds)->get();

        // Get tasks related to the agents
        $tasks = Task::whereIn('agent_id', $agentIds)->get();
        $suppliers = Supplier::all();
        $types = Task::distinct()->pluck('type');
        $totalInvoices = $invoices->count();
        $countries = Country::all();

        return view('invoice.link', compact(
            'invoices',
            'types',
            'suppliers',
            'branches',
            'agents',
            'clients',
            'tasks',
            'totalInvoices',
            'countries'
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
            if (auth()->user()) {
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
            if (auth()->user()) {
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
            if (auth()->user()) {
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
            if (auth()->user()) {
                return redirect()->route('invoices.index')->with('error', 'Invoice not found!');
            }
            return abort(404);
        }

        if ($invoice->status === 'paid by refund') return redirect()->route('invoices.index')->withErrors(['error' => 'This invoice has already been settled through a refund']);

        $invoicePartials = InvoicePartial::where('invoice_number', $invoiceNumber)
            ->with('client', 'invoice', 'payment')
            ->get();

        if ($invoicePartials->isEmpty()) {
            if (auth()->user()) {
                return redirect()->route('invoices.index')->with('error', 'No invoice partials found for this invoice!');
            }

            return abort(404);
        }

        $totalGatewayFee = ['fee' => 0, 'finalAmount' => 0, 'paid_by' => 'Company', 'charge_type' => 'Percent'];

        $paidServiceCharge = $invoicePartials->where('status', 'paid')->sum('service_charge');
        $totalGatewayFee['fee'] += $paidServiceCharge;

        $canGenerateLink = false;
        foreach ($invoice->invoicePartials as $partial) {
            if ($partial->charge_id) {
                $canGenerateLink = $partial->charge ? $partial->charge->can_generate_link : false;
                break;
            }
        }

        if ($canGenerateLink) {
            foreach ($invoicePartials as $partial) {
                if ($partial->status !== 'paid') {
                    $gatewayFee = [];
                    try {
                        if (strtolower($partial->payment_gateway) === 'myfatoorah' && $partial->payment_method) {
                            $gatewayFee = ChargeService::FatoorahCharge($partial->amount, $partial->payment_method, $companyId);
                        } elseif (strtolower($partial->payment_gateway) === 'tap') {
                            $gatewayFee = ChargeService::TapCharge([
                                'amount'    => $partial->amount,
                                'client_id' => $invoice->client_id,
                                'agent_id'  => $invoice->agent_id,
                                'currency'  => $invoice->currency,
                            ], $partial->payment_gateway);
                        } elseif (strtolower($partial->payment_gateway) === 'upayment') {
                            $gatewayFee = ChargeService::UPaymentCharge($partial->amount, $partial->payment_method, $companyId);
                        } elseif (strtolower($partial->payment_gateway) === 'hesabe') {
                            $gatewayFee = ChargeService::HesabeCharge($partial->amount, $partial->payment_method, $companyId);
                        } elseif (strtolower($partial->payment_gateway) === 'upayment') {
                            $gatewayFee = ChargeService::UPaymentCharge($partial->amount, $partial->payment_method, $companyId);
                        }
                    } catch (\Exception $e) {
                        Log::error('ChargeService exception', [
                            'message' => $e->getMessage(),
                            'gateway' => $partial->payment_gateway,
                            'company_id' => $companyId,
                        ]);
                    }
                    $partial->service_charge = $gatewayFee['fee'] ?? 0.00;
                    $partial->save();
                    $partial->final_amount = $partial->amount + $partial->service_charge;
                    $chargePayer = $gatewayFee['paid_by'] ?? 'Company';

                    if ($chargePayer !== 'Company') {
                        $totalGatewayFee['fee'] += $partial->service_charge;
                        $totalGatewayFee['paid_by'] = $chargePayer;
                        $totalGatewayFee['charge_type'] = $gatewayFee['charge_type'] ?? 'Percent';
                    }
                }
            }
        }

        $totalGatewayFee['fee'] += $invoice->invoice_charge ?? 0;

        $totalGatewayFee['finalAmount'] = $invoice->sub_amount + $invoice->tax + $totalGatewayFee['fee'];
        $paidPartials = $invoicePartials->where('status', 'paid');
        $invoiceDetails = $invoice->invoiceDetails;
        $company = $invoice->agent->branch->company;

        $checkUtilizeCredit = Credit::where('invoice_id', $invoice->id)
            ->where('company_id', $companyId)
            ->where('type', 'Invoice')
            ->orderBy('id', 'asc')
            ->get();

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
            if (auth()->user()) {
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
            if (auth()->user()) {
                return redirect()->route('invoices.index')->with('error', 'No invoice partials found for this invoice!');
            }
            return abort(404);
        }

        $totalGatewayFee = ['fee' => 0, 'finalAmount' => 0, 'paid_by' => 'Company', 'charge_type' => 'Percent'];

        $paidServiceCharge = $invoicePartials->where('status', 'paid')->sum('service_charge');
        $totalGatewayFee['fee'] += $paidServiceCharge;

        $canGenerateLink = false;
        foreach ($invoice->invoicePartials as $partial) {
            if ($partial->charge_id) {
                $canGenerateLink = $partial->charge ? $partial->charge->can_generate_link : false;
                break;
            }
        }

        if ($canGenerateLink) {
            foreach ($invoicePartials as $partial) {
                if ($partial->status !== 'paid') {
                    $gatewayFee = [];
                    try {
                        if (strtolower($partial->payment_gateway) === 'myfatoorah' && $partial->payment_method) {
                            $gatewayFee = ChargeService::FatoorahCharge($partial->amount, $partial->payment_method, $companyId);
                        } else if (strtolower($partial->payment_gateway) === 'tap') {
                            $gatewayFee = ChargeService::TapCharge([
                                'amount'    => $partial->amount,
                                'client_id' => $invoice->client_id,
                                'agent_id'  => $invoice->agent_id,
                                'currency'  => $invoice->currency,
                            ], $partial->payment_gateway);
                        } else if (strtolower($partial->payment_gateway) === 'upayment') {
                            $gatewayFee = ChargeService::UPaymentCharge($partial->amount, $partial->payment_method, $companyId);
                        } else if (strtolower($partial->payment_gateway) === 'hesabe') {
                            $gatewayFee = ChargeService::HesabeCharge($partial->amount, $partial->payment_method, $companyId);
                        }
                    } catch (Exception $e) {
                        Log::error('ChargeService exception', [
                            'message' => $e->getMessage(),
                            'gateway' => $partial->payment_gateway,
                            'company_id' => $companyId,
                        ]);
                    }
                    $partial->service_charge = $gatewayFee['fee'];
                    $partial->save();
                    $partial->final_amount = $partial->amount + $partial->service_charge;
                    $chargePayer = $gatewayFee['paid_by'] ?? 'Company';

                    if ($chargePayer !== 'Company') {
                        $totalGatewayFee['fee'] += $partial->service_charge;
                        $totalGatewayFee['paid_by'] = $chargePayer;
                        $totalGatewayFee['charge_type'] = $gatewayFee['charge_type'] ?? 'Percent';
                    }
                }
            }
        }

        $totalGatewayFee['fee'] += $invoice->invoice_charge ?? 0;
        $totalGatewayFee['finalAmount'] = $invoice->sub_amount + $invoice->tax + $totalGatewayFee['fee'];
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

                if (strtolower($paymentGateway) === 'myfatoorah' && $paymentMethod) {
                    $gatewayFee = ChargeService::FatoorahCharge($invoicePartial->amount, $paymentMethod, $companyId);
                } else if (strtolower($paymentGateway) === 'tap') {
                    $gatewayFee = ChargeService::TapCharge([
                        'amount'    => $invoicePartial->amount,
                        'client_id' => $invoice->client_id,
                        'agent_id'  => $invoice->agent_id,
                        'currency'  => $invoice->currency,
                    ], $paymentGateway);
                } else if (strtolower($paymentGateway) === 'upayment') {
                    $gatewayFee = ChargeService::UPaymentCharge($invoicePartial->amount, $paymentMethod, $companyId);
                } else if (strtolower($paymentGateway) === 'hesabe') {
                    $gatewayFee = ChargeService::HesabeCharge($invoicePartial->amount, $paymentMethod, $companyId);
                }
            } catch (\Exception $e) {
                Log::error('ChargeService exception on split page', [
                    'message' => $e->getMessage(),
                    'partial_id' => $partialId
                ]);
                $gatewayFee = ['fee' => 0, 'paid_by' => 'Company'];
            }
            $invoicePartial->service_charge = ($gatewayFee['paid_by'] === 'Company') ? 0 : $gatewayFee['fee'];
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

                if (strtolower($paymentGateway) === 'myfatoorah' && $paymentMethod) {
                    $gatewayFee = ChargeService::FatoorahCharge($invoicePartial->amount, $paymentMethod, $companyId);
                } else if (strtolower($paymentGateway) === 'tap') {
                    $gatewayFee = ChargeService::TapCharge([
                        'amount'    => $invoicePartial->amount,
                        'client_id' => $invoice->client_id,
                        'agent_id'  => $invoice->agent_id,
                        'currency'  => $invoice->currency,
                    ], $paymentGateway);
                } else if (strtolower($paymentGateway) === 'upayment') {
                    $gatewayFee = ChargeService::UPaymentCharge($invoicePartial->amount, $paymentMethod, $companyId);
                } else if (strtolower($paymentGateway) === 'hesabe') {
                    $gatewayFee = ChargeService::HesabeCharge($invoicePartial->amount, $paymentMethod, $companyId);
                }
            } catch (\Exception $e) {
                Log::error('ChargeService exception on split page', [
                    'message' => $e->getMessage(),
                    'partial_id' => $partialId
                ]);
                $gatewayFee = ['fee' => 0, 'paid_by' => 'Company'];
            }
            $invoicePartial->service_charge = ($gatewayFee['paid_by'] === 'Company') ? 0 : $gatewayFee['fee'];
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

        if(isset($response['error'])) {
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
                    'agent_id' => $task->agent_id ?? $invoice->agent_id,
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
                $taskSpecificAmount = $relevantDetail->task_price;
                $newDebit = 0;
                $newCredit = 0;
                $commission = 0;
                $agent = $invoice->agent;
                if (in_array($agent->type_id, [2, 3])) {
                    $rate = (float) ($agent->commission ?? 0.15);
                    $commission = $rate * ($taskSpecificAmount - $relevantDetail->supplier_price);
                }

                if (str_contains($entry->description, 'Invoice created for (Assets)')) {
                    $newDebit = $taskSpecificAmount;
                } else if (str_contains($entry->description, 'Invoice created for (Income)')) {
                    $newCredit = $taskSpecificAmount;
                } else if (str_contains($entry->description, 'Agents Commissions for (Expenses)')) {
                    $newDebit = $commission;
                } else if (str_contains($entry->description, 'Agents Commissions for (Liabilities)')) {
                    $newCredit = $commission;
                }

                if ($newDebit > 0 || $newCredit > 0) {
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
                        'agent_id' => $task->agent_id ?? $invoice->agent_id,
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
            ->with('agent', 'client', 'invoiceDetails', 'invoiceDetails.task', 'invoicePartials')
            ->first();

        $company = Company::find($companyId);

        $journalEntries = JournalEntry::where('invoice_id', $invoice->id)->get();
        if (!$journalEntries) {
            return response()->json(['message' => 'Journal entry not found'], 404);
        }
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

    public function accountantUpdate(Request $request){

        $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'invoice_id' => 'required|integer|exists:invoices,id',
            'invoice_charge' => 'nullable',
            'amount' => 'nullable|numeric',
            'invoice_details' => 'required|array',
            'invoice_details.*' => 'required|array',
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'paid_date' => 'nullable|date',
            'payment_type' => 'nullable|string|in:full,partial,split,credit,cash',
        ]);

        $invoice = Invoice::find($request->invoice_id);

        $success = [];
        $error = [];
        $transactionId = null;

        if($request->filled('invoice_details')){

            $originalDetails = $invoice->invoiceDetails;
            $updatingDetails = null;

            $requestInvoiceDetails = $request->input('invoice_details');
            foreach($originalDetails as $detail){
                if($requestInvoiceDetails[$detail->task_id]['amount'] != $detail->task_price){
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

        $transactionId = $invoice->transactions()->latest()->value('id');

        $invoice = $invoice->fresh();

        if( empty($updatingDetails) && ( $invoice->invoice_charge !== $request->invoice_charge || $invoice->amount !== $request->amount)){
            if($request->amount != bcadd($invoice->sub_amount, $request->invoice_charge ?? 0, 3)){
                
                Log::error('Invoice amount mismatch', [
                    'invoice_id' => $invoice->id,
                    'expected_amount' => bcadd($invoice->sub_amount, $request->invoice_charge ?? 0, 3),
                    'provided_amount' => $request->amount,
                ]);

                $error[] = 'The total amount does not match the sum of sub amount and invoice charge.';
            }
            
            if($request->invoice_charge !== bcsub($request->amount, $invoice->sub_amount, 3)){
                
                Log::error('Invoice charge mismatch', [
                    'invoice_id' => $invoice->id,
                    'expected_charge' => bcsub($invoice->amount, $invoice->sub_amount, 2),
                    'provided_charge' => $request->invoice_charge,
                ]);

                $error[] = 'The invoice charge does not match the difference between total amount and sub amount.';
            }

            if(empty($error)){

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

        if($invoice->invoice_date !== $request->invoice_date){
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

        if($invoice->due_date !== $request->due_date){
            $invoice->due_date = $request->due_date;
            $invoice->save();
            $success[] = 'Due date updated successfully.';
        }

        $paidDate = date_format(date_create($request->paid_date), 'Y-m-d H:i:s');

        if($invoice->paid_date !== $paidDate){
            $invoice->paid_date = $paidDate;
            $invoice->save();
            $success[] = 'Paid date updated successfully.';
        }

        if($request->filled('payment_type') && $invoice->payment_type !== $request->payment_type){
            $paymentTypeChangeResult = $this->handlePaymentTypeChange($invoice, $request->payment_type);
            
            if(isset($paymentTypeChangeResult['error'])){
                $error[] = $paymentTypeChangeResult['error'];
            }
            
            if(isset($paymentTypeChangeResult['success'])){
                $success = array_merge($success, $paymentTypeChangeResult['success']);
            }
            
            if(isset($paymentTypeChangeResult['shortage_info'])){
                session(['shortage_info' => $paymentTypeChangeResult['shortage_info']]);
                $success[] = 'Payment type changed successfully. Note: Client has insufficient credit balance.';
            }
        }

        $return = redirect()->back();

        if($success) $return = $return->with('success', 'Invoice updated successfully!')->with('data_success', $success);

        if($error) $return = $return->with('error', 'There is some issue')->with('data', $error);

        return $return;
    }

    private function updateSubAmount($subAmount){

    }

    // private function addInvoiceChargeJournalEntries($invoiceNumber, $user, $companyId, $newInvoiceCharge){
    
    //     $whoIsUser = '';

    //     if($user->role_id == Role::ADMIN){
    //         $whoIsUser = 'Admin';
    //     } else if($user->role_id == Role::COMPANY) {
    //         $whoIsUser = 'Company admin ' . $user->company->name;
    //     } else if ($user->role_id == Role::BRANCH) {
    //         $whoIsUser = 'Branch admin ' . $user->branch->name;
    //     } else if ($user->role_id == Role::AGENT) {
    //         $whoIsUser = 'Agent ' . $user->agent->name;
    //     } else if ($user->role_id == Role::ACCOUNTANT) {
    //         $whoIsUser = 'Accountant ' . $user->accountant->name;
    //     }  else {
    //         return response()->json(['error' => 'User role not recognized.'], 403);
    //     }

    //     $invoice = Invoice::where('invoice_number', $invoiceNumber)
    //         ->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId))
    //         ->first();

    //     if (!$invoice) {
    //         Log::error('Invoice not found for updateInvoiceCharge', [
    //             'invoice_number' => $invoiceNumber,
    //             'company_id' => $companyId,
    //         ]);
    //         return response()->json(['error' => 'Invoice not found.'], 404);
    //     }

    //     $transactionToAdd = $invoice->transactions()
    //         ->where('description', 'LIKE', 'Invoice reversal for%')
    //         ->orderBy('created_at', 'desc')
    //         ->first();

    //     if (!$transactionToAdd) {
    //         $transactionToAdd = $invoice->transactions()->first();
    //     }

    //     $response = $this->agentCommission($invoice, null, $invoice->invoice_charge, 'Invoice charge');
            
    // }

    private function updateDetailsAmount(Request $request): JsonResponse {
        $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'invoice_number' => 'required|string|exists:invoices,invoice_number',
            'tasks' => 'nullable|array',
            'tasks.*' => 'nullable|numeric|min:0',
            // 'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = User::find(29);

        $whoIsUser = '';

        if($user->role_id == Role::ADMIN){
            $whoIsUser = 'Admin';
        } else if($user->role_id == Role::COMPANY) {
            $whoIsUser = 'Company admin ' . $user->company->name;
        } else if ($user->role_id == Role::BRANCH) {
            $whoIsUser = 'Branch admin ' . $user->branch->name;
        } else if ($user->role_id == Role::AGENT) {
            $whoIsUser = 'Agent ' . $user->agent->name;
        } else if ($user->role_id == Role::ACCOUNTANT) {
            $whoIsUser = 'Accountant ' . $user->accountant->name;
        }  else {
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

        try{
            DB::transaction(function () use ($request, $companyId, $invoiceNumber, $whoIsUser, &$transactionToReverse) {
                $invoice = Invoice::with(['invoiceDetails.task', 'agent', 'agent.branch', 'transactions.journalEntries'])
                    ->whereHas('agent.branch', fn($q) => $q->where('company_id', $companyId))
                    ->where('invoice_number', $invoiceNumber)
                    ->firstOrFail();

                $transactionToReverse = $invoice->transactions()->orderBy('id','desc')->first();

                if (!$transactionToReverse && $transactionToReverse->isEmpty()) {
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
                    if(!str_contains($description, 'reversal by')){
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
    
                foreach ($transactionToReverse->journalEntries as $entry) {
                    $relevantDetail = $updatedDetails->firstWhere('id', $entry->invoice_detail_id);
                    if($relevantDetail && !str_contains($entry->description, JournalEntry::ADDITIONAL_INVOICE_CHARGE)){
                        $taskSpecificAmount = $relevantDetail->task_price;
                        $newDebit = 0;
                        $newCredit = 0;
                        $commission = 0;
                        $agent = $invoice->agent;
    
                        if (in_array($agent->type_id, [2, 3])) {
                            $rate = (float) ($agent->commission ?? 0.15);
                            $commission = $rate * ($taskSpecificAmount - $relevantDetail->supplier_price);
                        }
    
                        if(!str_contains($entry->description, JournalEntry::ADDITIONAL_INVOICE_CHARGE)){
                            if (str_contains($entry->description, 'Invoice created for (Assets)')) {
                                $newDebit = $taskSpecificAmount;
                            } else if (str_contains($entry->description, 'Invoice created for (Income)')) {
                                $newCredit = $taskSpecificAmount;
                            } else if (str_contains($entry->description, 'Agents Commissions for (Expenses)')) {
                                $newDebit = $commission;
                            } else if (str_contains($entry->description, 'Agents Commissions for (Liabilities)')) {
                                $newCredit = $commission;
                            } 
                        }
        
                        if ($newDebit > 0 || $newCredit > 0) {

                            $description = $entry->description;

                            // if(!str_contains($description, 'correction by')){
                            //     $description = $entry->description . ' correction by ' . $whoIsUser;
                            // }

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
                                'agent_id' => $task->agent_id ?? $invoice->agent_id,
                                'invoice_detail_id' => $entry->invoice_detail_id,
                                'transaction_date' => $entry->transaction_date,
                                'type' => $entry->type,
                                'task_id' => $entry->task_id,
                                'name' => $entry->name,
                            ]);
                        }
                    } else {
                        if (str_contains($entry->description, 'Invoice created for (Assets)')) {
                            $newDebit = $invoice->invoice_charge;
                        } else if (str_contains($entry->description, 'Invoice created for (Income)')) {
                            $newCredit = $invoice->invoice_charge;
                        } else if (str_contains($entry->description, 'Agents Commissions for (Expenses)')) {
                            $newDebit = $invoice->invoice_charge * ($agent->commission ?? 0.15);
                        } else if (str_contains($entry->description, 'Agents Commissions for (Liabilities)')) {
                            $newCredit = $invoice->invoice_charge * ($agent->commission ?? 0.15);
                        }



                    }
                }

                $journalEntriesOfInvoiceCharge = $transactionToReverse->journalEntries()->where('description', 'LIKE', '%' . JournalEntry::ADDITIONAL_INVOICE_CHARGE . '%')->get();

                if($journalEntriesOfInvoiceCharge->isEmpty() && $invoice->invoice_charge > 0){
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

    public function updateDateProcess(Request $request) : array
    {
        $request->validate([
            'invoice_date' => 'required|date',
            'company_id' => 'required|integer|exists:companies,id',
            'invoice_number' => 'required|string|exists:invoices,invoice_number',
        ]);

        try{
            DB::transaction(function() use ($request) {
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

    private function addInvoiceChargeJournalEntries(Invoice $invoice, Transaction $transaction): array {
        $agent = $invoice->agent;

        if(!$agent){
            Log::error('Agent not found for invoice charge journal entry', ['invoice_id' => $invoice->id]);
            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
        }

        $companyId = $agent->branch->company_id ?? null;

        if(!$companyId){
            Log::error('Company ID not found for invoice charge journal entry', ['invoice_id' => $invoice->id]);
            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
        }
      
        try{
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

        if(!$agent){
            Log::error('Agent commission calculation failed: Invoice has no associated agent', ['invoice_id' => $invoice->id]);
            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
        }

        $companyId = $agent->branch->company_id;

        if(!$companyId){
            Log::error('Agent commission calculation failed: Agent does not belong to a company', ['agent_id' => $agent->id]);
            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
        }

        $transaction = $invoice->transactions()->first();

        if(!$transaction){
            Log::error('Agent commission calculation failed: Invoice has no associated transaction', ['invoice_id' => $invoice->id]);
            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
        }

        $transactionId = $transaction->id;



        try {
            DB::transaction(function () use (
                $agent,
                $transactionId,
                $invoice,
                $newAmount,
                $companyId,
                $additionalDesc,
            ) {

                $additionalDesc = $additionalDesc ? $additionalDesc . ' - ' : '';
                // Commission Expense Entry
                try {
                    if (in_array($agent->type_id, [2, 3])) {
                        $rate = (float) ($agent->commission ?? 0.15);
                        $commission = $rate * $newAmount;

                        $commissionExpenses = Account::where('name', 'like', 'Commissions Expense (Agents)%')
                            ->where('company_id', $companyId)
                            ->first();

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
                                'debit' => $commission,
                                'credit' => 0,
                                'balance' => $commissionExpenses->balance ?? 0,
                                'name' => $commissionExpenses->name . ' - ' . JournalEntry::ADDITIONAL_INVOICE_CHARGE,
                                'type' => 'receivable',
                                'currency' => 'USD',
                                'exchange_rate' => 1.00,
                                'amount' => $commission,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Commission Expense Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
                    throw new \Exception('Failed to create commission expense entry: ' . $e->getMessage());
                }

                // Commission Liability Entry
                try {
                    if (in_array($agent->type_id, [2, 3])) {
                        $rate = (float) ($agent->commission ?? 0.15);
                        $commission = $rate * $newAmount;

                        $accruedCommissions = Account::where('name', 'like', 'Commissions (Agents)%')
                            ->where('company_id', $companyId)
                            ->first();
                        

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
                                'debit' => 0,
                                'credit' => $commission,
                                'balance' => $accruedCommissions->balance ?? 0,
                                'name' => $accruedCommissions->name . ' - ' . JournalEntry::ADDITIONAL_INVOICE_CHARGE,
                                'type' => 'payable',
                                'currency' => 'USD',
                                'exchange_rate' => 1.00,
                                'amount' => $commission,
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Commission Liability Entry Error: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
                    throw new Exception('Failed to create commission liability entry: ' . $e->getMessage());
                }
            });

            return ['status' => 'success'];

        } catch (Exception $e) {
            Log::error('Agent commission transaction failed: ' . $e->getMessage(), ['invoice_id' => $invoice->id]);
            return ['status' => 'error', 'message' => 'Something went wrong. Please try again later.'];
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

        if (!in_array($currentPaymentType, ['credit', 'cash']) || !in_array($newPaymentType, ['credit', 'cash'])) {
            return ['error' => 'Currently only changes between Credit and Cash payment types are supported.'];
        }

        if ($currentPaymentType === 'credit' && $newPaymentType === 'cash') {
            return $this->changeCreditToCash($invoice);
        } elseif ($currentPaymentType === 'cash' && $newPaymentType === 'credit') {
            return $this->changeCashToCredit($invoice);
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
                    'type' => 'Refund',
                    'description' => 'Refund from changing payment type from Credit to Cash for invoice: ' . $invoice->invoice_number,
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

            $conversionResult = $this->processCashToCreditConversion($invoice, $invoiceAmount);
            
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

    private function processCashToCreditConversion(Invoice $invoice, float $amount): array
    {
        try {
            DB::transaction(function () use ($invoice, $amount) {
                $cashPartial = $invoice->invoicePartials()
                    ->where('payment_gateway', 'Cash')
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
                    'description' => 'Payment for ' . $invoice->invoice_number . ' (changed from Cash to Credit)',
                    'amount' => -$amount,
                ]);

                $invoice->payment_type = 'credit';
                $invoice->is_client_credit = true;
                $invoice->save();

                Log::info('Successfully changed payment type from cash to credit', [
                    'invoice_id' => $invoice->id,
                    'deducted_amount' => $amount,
                ]);
            });

            return ['success' => ['Payment type successfully changed from Cash to Credit.']];

        } catch (Exception $e) {
            Log::error('Failed to process cash to credit conversion: ' . $e->getMessage(), [
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

            $newInvoice = Invoice::create([
                'invoice_number' => $invoice->invoice_number . '-SH-' . now()->format('Yis'),
                'agent_id' => $invoice->agent_id,
                'client_id' => $client->id,
                'sub_amount' => $shortageAmount,
                'amount' => $shortageAmount,
                'currency' => $invoice->currency,
                'status' => 'unpaid',
                'payment_type' => 'full',
                'invoice_date' => now(),
                'due_date' => now()->addDays(7),
            ]);

            InvoiceDetail::create([
                'invoice_id' => $newInvoice->id,
                'invoice_number' => $newInvoice->invoice_number,
                'task_id' => $invoice->invoiceDetails->first()->task_id,
                'task_description' => 'Credit shortage payment for invoice: ' . $invoice->invoice_number,
                'task_price' => $shortageAmount,
                'paid' => false,
            ]);

            $charge = Charge::where('name', $gateway)->first();
            $invoicePartial = InvoicePartial::create([
                'invoice_id' => $newInvoice->id,
                'invoice_number' => $newInvoice->invoice_number,
                'client_id' => $client->id,
                'service_charge' => 0,
                'amount' => $shortageAmount,
                'status' => 'unpaid',
                'expiry_date' => now()->addDays(7),
                'type' => 'full',
                'payment_gateway' => $gateway,
                'payment_method' => $paymentMethodId,
                'charge_id' => $charge ? $charge->id : null,
            ]);

            $paymentRequest = new Request([
                'client_id' => $newInvoice->client_id,
                'agent_id' => $newInvoice->agent_id,
                'invoice_id' => $newInvoice->id,
                'amount' => $shortageAmount,
                'type' => 'full',
                'payment_gateway' => $gateway,
                'payment_method' => $paymentMethodId,
                'notes' => 'Payment link for credit shortage - Invoice: ' . $invoice->invoice_number,
            ]);

            $paymentController = new PaymentController();
            $response = $paymentController->paymentStoreLinkProcess($paymentRequest);

            if ($response['status'] === 'success') {
                session()->forget('shortage_info');
                
                return redirect()->back()->with('success', 'Payment link created successfully for the credit shortage amount.');
            } else {
                return redirect()->back()->with('error', 'Failed to create payment link: ' . ($response['message'] ?? 'Unknown error'));
            }

        } catch (Exception $e) {
            Log::error('Failed to create payment link for shortage: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create payment link for shortage.');
        }
    }

}
