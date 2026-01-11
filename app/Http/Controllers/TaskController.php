<?php

namespace App\Http\Controllers;
// testing
use Exception;
use App\AI\AIManager;
use App\Http\Traits\Converter;
use App\Http\Traits\CurrencyExchangeTrait;
use App\Http\Traits\NotificationTrait;
use App\View\Components\AppLayout;
use App\Models\Task;
use App\Models\Agent;
use App\Models\TaskFlightDetail;
use App\Models\Airline;
use App\Models\Client;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Branch;
use App\Models\Room;
use App\Models\TaskHotelDetail;
use App\Models\TaskInsuranceDetail;
use App\Models\TaskVisaDetail;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\SupplierCompany;
use App\Models\SupplierSurcharge;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Payment;
use App\Models\FileUpload;
use App\Models\SystemLog;
use App\Models\AutoBilling;
use App\Models\HotelBooking;
use App\Models\TBO;
use App\Models\Wallet;
use App\Models\SupplierSurchargeReference;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use iio\libmergepdf\Merger;
use iio\libmergepdf\Driver\Fpdi2Driver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use SebastianBergmann\Type\TrueType;

class TaskController extends Controller
{
    use NotificationTrait, Converter, CurrencyExchangeTrait;

    public function getTasks(Request $request) : JsonResponse
    {
        Gate::authorize('viewAny', Task::class);

        $request->validate([
            'filter' => 'nullable|array'
        ]);

        $filter = $request->filter;

        try {
            $taskQuery = Task::query();
            
            if (!empty($filter) && is_array($filter)) {
                $taskQuery->where(function ($query) use ($filter) {
                    foreach($filter as $field => $value) {
                        if (!empty($value)) {
                            $query->where($field, 'like', '%' . $value . '%');
                        }
                    }
                });
            }
            
            $taskQuery->orderBy('supplier_pay_date', 'desc');

        } catch (Exception $e) {
            Log::info('Error building task query', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error building task query',
            ], 400);
        }

        $tasksTotal = $taskQuery->count();

        $tasks = $taskQuery->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'tasks' => $tasks,
                'total' => $tasksTotal,
            ],
        ]);
        
    }

    public function index(Request $request): View | RedirectResponse
    {
        Gate::authorize('viewAny', Task::class);
        $user = Auth::user();

        $defaultColumns = ['reference', 'bill-to', 'passenger-name', 'agent-name', 'price', 'status', 'info'];
        if ($user->role_id === Role::AGENT) {
            $defaultColumns = ['reference', 'bill-to', 'passenger-name', 'price', 'status', 'info'];
        }

        $visibleColumns = session('visible_task_columns', $defaultColumns);

        $sortBy = $request->query('sortBy', 'created_at');
        $sortOrder = $request->query('sortOrder', 'desc');
        $sortableColumns = ['supplier_pay_date', 'created_at'];
        if (!in_array($sortBy, $sortableColumns)) {
            $sortBy = 'created_at';
        }

        $query = Task::with('agent.branch', 'client', 'invoiceDetail.invoice', 'refundDetail', 'originalTask', 'linkedTask');
        $suppliers = Supplier::with('companies');
        $companyId = null;

        if ($user->role_id == Role::ADMIN) {
            $companyId = 1;
            $clients = Client::all();
            $agents = Agent::all();
            $fullClients = Client::all();
            $suppliers = $suppliers->get();
        } elseif ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
            $branches = Branch::where('company_id', $user->company->id)->get();
            $agents = Agent::with('branch')->whereIn('branch_id', $branches->pluck('id'))->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
            $fullClients = Client::where(function ($q) use ($agentsId) {
                $q->whereIn('agent_id', $agentsId)
                    ->orWhereHas('agents', fn($qq) => $qq->whereIn('agent_id', $agentsId));
            })->get();

            $query->where('company_id', $user->company->id);
            $suppliers = $suppliers->activeForCompany($user->company->id)->get();
        } elseif ($user->role_id == Role::BRANCH) {
            $agents = Agent::with('branch')->where('branch_id', $user->branch_id)->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
            $fullClients = Client::where(function ($q) use ($agentsId) {
                $q->whereIn('agent_id', $agentsId)
                    ->orWhereHas('agents', fn($qq) => $qq->whereIn('agent_id', $agentsId));
            })->get();

            $query->whereIn('agent_id', $agentsId)->where('company_id', $user->company_id);
            $suppliers = $suppliers->activeForCompany($user->company_id)->get();
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = $user->agent->branch->company->id;
            $agents = Agent::with('branch')->where('id', $user->agent->id)->get();
            $clients = Client::where('agent_id', $user->agent->id)->get();
            $fullClients = Client::where(function ($q) use ($user) {
                $q->where('agent_id', $user->agent->id)
                    ->orWhereHas('agents', fn($qq) => $qq->where('agent_id', $user->agent->id));
            })->get();

            $query->where(function ($q) use ($user) {
                $q->where('agent_id', $user->agent->id)
                    ->orWhere(function ($sub) use ($user) {
                        $sub->whereNull('agent_id')
                            ->where('company_id', $user->agent->branch->company_id);
                    });
            })->where('company_id', $user->agent->branch->company_id);

            $suppliers = $suppliers->whereHas('companies', fn($q) => $q->where('supplier_companies.is_active', 1))->get();
        } elseif ($user->role_id == Role::ACCOUNTANT) {
            $companyId = $user->accountant->branch->company->id;
            $company = Company::findOrFail($companyId);
            $agents = collect();
            foreach ($company->branches as $branch) {
                $agents = $agents->merge($branch->agents);
            }
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
            $fullClients = Client::where(function ($q) use ($agentsId) {
                $q->whereIn('agent_id', $agentsId)
                    ->orWhereHas('agents', fn($qq) => $qq->whereIn('agent_id', $agentsId));
            })->get();

            $query->where('company_id', $companyId);
            $suppliers = $suppliers->activeForCompany($companyId)->get();
        } else {
            return redirect()->back()->with('error', 'User not authorized to view tasks.');
        }

        $confirmedIssuedTask = (clone $query)->where('status', 'confirmed')
            ->whereDoesntHave('invoiceDetail')
            ->whereHas('linkedTask', function ($q) {
                $q->where('status', 'issued');
            })->pluck('id')->toArray();

        // filter out the confirmed tasks from the query
        $query->whereNotIn('id', $confirmedIssuedTask);


        if (!$companyId) {
            return redirect()->back()->with('error', 'Company not found for the user.');
        }

        $liabilities = Account::where('name', 'Liabilities')
            ->where('company_id', $companyId)
            ->first();

        if (!$liabilities) {
            Log::error('Liabilities account not found for company ID: ' . $companyId);
            return redirect()->back()->with('error', 'Liabilities account not found. Please contact the administrator.');
        }

        $creditorsAccount = Account::where('name', 'Creditors')
            ->where('company_id', $companyId)
            ->where('root_id', $liabilities->id)
            ->first();

        if (!$creditorsAccount) {
            Log::error('Creditors account not found for company ID: ' . $companyId);
            return redirect()->back()->with('error', 'Creditors account not found. Please contact the administrator.');
        }

        $listOfCreditors = $creditorsAccount->children()->get()
            ->mapToGroups(function ($account) {
                $group = stripos($account->name, 'Como') !== false ? 'Como Travel' : 'City Travelers';

                return [
                    $group => [
                        'id' => $account->id,
                        'name' => $account->name,
                        'parent_id' => $account->parent_id,
                        'company_id' => $account->company_id,
                        'code' => $account->code,
                    ],
                ];
            })
            ->toArray();

        $paymentMethod = Account::where('parent_id', 39)->get();
        if ($search = $request->query('q')) {
            $searchTerm = '%' . strtolower($search) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('reference', 'LIKE', $searchTerm)
                    ->orWhere('passenger_name', 'LIKE', $searchTerm)
                    ->orWhere('gds_reference', 'LIKE', $searchTerm)
                    ->orWhereHas('client', function ($qq) use ($searchTerm) {
                        $qq->where('first_name', 'LIKE', $searchTerm)
                            ->orWhere('middle_name', 'LIKE', $searchTerm)
                            ->orWhere('last_name', 'LIKE', $searchTerm)
                            ->orWhere('email', 'LIKE', $searchTerm)
                            ->orWhere('phone', 'LIKE', $searchTerm)
                            ->orWhere('civil_no', 'LIKE', $searchTerm);
                    })
                    ->orWhereHas('agent', fn($qq) => $qq->where('name', 'LIKE', $searchTerm));
            });
        }

        $showVoid = $request->boolean('show_void', false);
        $statuses = (array) $request->input('status', $request->input('status[]', []));

        if (!$showVoid) {
            if (empty($statuses)) {
                $query->where('status', '!=', 'void');
            } else {
                $query->whereIn('status', $statuses);
            }
        } elseif (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        if (!$request->has('invoiced')) {
            return redirect()->route('tasks.index', array_merge($request->all(), [
                'invoiced' => 0,
                'view_type' => 'invoice',
            ]));
        }

        if ($request->has('invoiced')) {
            if ($request->input('invoiced') == '1') {
                $query->whereHas('invoiceDetail');
            } elseif ($request->input('invoiced') == '0') {
                $amadeusId = Supplier::where('name', 'Amadeus')->value('id');
                $jazeeraId = Supplier::where('name', 'Jazeera Airways')->value('id');

                $query->whereDoesntHave('invoiceDetail')->where(function ($q) use ($amadeusId, $jazeeraId) {
                    $q->whereNotIn('supplier_id', [$amadeusId, $jazeeraId])
                        ->orWhere(function ($q2) use ($amadeusId) {
                            $q2->where('supplier_id', $amadeusId)
                                ->where(function ($q3) use ($amadeusId) {
                                    $q3->where('status', '!=', 'issued')
                                        ->orWhereRaw("
                                                NOT EXISTS (
                                                    SELECT 1 FROM tasks t2
                                                    WHERE t2.reference = tasks.reference
                                                    AND t2.supplier_id = ?
                                                    AND t2.status = 'void'
                                                    AND t2.deleted_at IS NULL
                                                )
                                        ", [$amadeusId]);
                                });
                        })
                        ->orWhere(function ($q2) use ($jazeeraId) {
                            $q2->where('supplier_id', $jazeeraId)
                                ->where(function ($q3) use ($jazeeraId) {
                                    $q3->where('status', '!=', 'confirmed')
                                        ->orWhereRaw("
                                                NOT EXISTS (
                                                    SELECT 1 FROM tasks t2
                                                    WHERE t2.reference = tasks.reference
                                                    AND t2.supplier_id = ?
                                                    AND t2.status = 'issued'
                                                    AND t2.deleted_at IS NULL
                                                )
                                            ", [$jazeeraId]);
                                });
                        });
                });
            }
        }

        $filterable = [
            'reference',
            'bill-to',
            'passenger-name',
            'agent_name',
            'supplier',
            'created-at',
            'supplier_pay_date',
            'cancellation-deadline',
            'type',
            'amadeus-reference',
            'created-by',
            'issued-by',
            'branch-name',
            'invoice',
        ];

        foreach ($filterable as $field) {
            switch ($field) {
                case 'reference':
                    if ($request->filled('reference')) {
                        $references = (array) $request->input('reference');
                        $query->where(function ($q) use ($references) {
                            foreach ($references as $ref) {
                                $q->orWhere('reference', 'like', '%' . $ref . '%');
                            }
                        });
                    }
                    break;
                case 'bill-to':
                    $param = 'bill-to';
                    if ($request->filled($param)) {
                        $billTos = (array) $request->input($param);
                        $query->whereHas('client', function ($q) use ($billTos) {
                            foreach ($billTos as $billTo) {
                                $q->orWhere('first_name', 'like', '%' . $billTo . '%')
                                    ->orWhere('last_name', 'like', '%' . $billTo . '%')
                                    ->orWhere('phone', 'like', '%' . $billTo . '%');
                            }
                        });
                    }
                    break;
                case 'passenger-name':
                    $param = 'passenger-name';
                    if ($request->filled($param)) {
                        $names = (array) $request->input($param);
                        $query->where(function ($q) use ($names) {
                            foreach ($names as $name) {
                                $q->orWhere('passenger_name', 'like', '%' . $name . '%');
                            }
                        });
                    }
                    break;
                case 'agent_name':
                    if ($request->filled('agent_name')) {
                        $agentFilters = (array) $request->input('agent_name');
                        $query->whereHas('agent', function ($q) use ($agentFilters) {
                            $q->whereIn('name', $agentFilters);
                        });
                    }
                    break;
                case 'supplier':
                    $supplierFilters = (array) $request->input('supplier', $request->input('supplier[]', []));
                    if (!empty($supplierFilters)) {
                        $query->whereHas('supplier', function ($q) use ($supplierFilters) {
                            $q->where(function ($subQ) use ($supplierFilters) {
                                foreach ($supplierFilters as $supplier) {
                                    $subQ->orWhere('name', 'like', '%' . $supplier . '%');
                                }
                            });
                        });
                    }
                    break;
                case 'created-at':
                    $from = $request->input('created-at_from');
                    $to = $request->input('created-at_to');
                    if ($from) $query->whereDate('created_at', '>=', $from);
                    if ($to)   $query->whereDate('created_at', '<=', $to);
                    if ($request->filled('created-at')) {
                        $query->whereDate('created_at', $request->input('created-at'));
                    }
                    break;
                case 'supplier_pay_date':
                    $from = $request->input('supplier_pay_date_from');
                    $to = $request->input('supplier_pay_date_to');
                    if ($from) $query->whereDate('supplier_pay_date', '>=', $from);
                    if ($to)   $query->whereDate('supplier_pay_date', '<=', $to);
                    if ($request->filled('supplier_pay_date')) {
                        $query->whereDate('supplier_pay_date', $request->input('supplier_pay_date'));
                    }
                    break;
                case 'cancellation-deadline':
                    if ($request->filled('cancellation-deadline')) {
                        $query->whereDate('cancellation_deadline', $request->input('cancellation-deadline'));
                    }
                    break;
                case 'type':
                    if ($request->filled('type')) {
                        $query->where('type', $request->input('type'));
                    }
                    break;
                case 'amadeus-reference':
                    if ($request->filled('amadeus-reference')) {
                        $refs = (array) $request->input('amadeus-reference');
                        $query->where(function ($q) use ($refs) {
                            foreach ($refs as $ref) {
                                $q->orWhere('airline_reference', 'like', '%' . $ref . '%');
                            }
                        });
                    }
                    break;
                case 'created-by':
                    if ($request->filled('created-by')) {
                        $createdBys = (array) $request->input('created-by');
                        $query->where(function ($q) use ($createdBys) {
                            foreach ($createdBys as $createdBy) {
                                $q->orWhere('created_by', 'like', '%' . $createdBy . '%');
                            }
                        });
                    }
                    break;
                case 'issued-by':
                    if ($request->filled('issued-by')) {
                        $issuedBys = (array) $request->input('issued-by');
                        $query->where(function ($q) use ($issuedBys) {
                            foreach ($issuedBys as $issuedBy) {
                                $q->orWhere('issued_by', 'like', '%' . $issuedBy . '%');
                            }
                        });
                    }
                    break;
                case 'branch-name':
                    if ($request->filled('branch-name')) {
                        $branches = (array) $request->input('branch-name');
                        $query->whereHas('agent.branch', function ($q) use ($branches) {
                            foreach ($branches as $branch) {
                                $q->orWhere('name', 'like', '%' . $branch . '%');
                            }
                        });
                    }
                    break;
                case 'invoice':
                    if ($request->filled('invoice')) {
                        $invoices = (array) $request->input('invoice');
                        $query->whereHas('invoiceDetail', function ($q) use ($invoices) {
                            foreach ($invoices as $invoice) {
                                $q->orWhere('invoice_number', 'like', '%' . $invoice . '%');
                            }
                        });
                    }
                    break;
                default:
                    if ($request->filled($field)) {
                        $values = (array) $request->input($field);
                        $query->where(function ($q) use ($field, $values) {
                            foreach ($values as $value) {
                                $q->orWhere($field, 'like', '%' . $value . '%');
                            }
                        });
                    }
            }
        }

        $countries = Country::all();
        $hotels = Hotel::all();
        $currencyExchange = (new AppLayout())->currencySidebar();
        $currencies = $currencyExchange['currencies'];

        $possibleTypes = [
            'hotel' => 'Hotel',
            'flight' => 'Flight',
            'visa' => 'Visa',
            'insurance' => 'Insurance',
            'tour' => 'Tour',
            'cruise' => 'Cruise',
            'car' => 'Car',
            'rail' => 'Rail',
            'esim' => 'Esim',
            'event' => 'Event',
            'lounge' => 'Lounge',
            'ferry' => 'Ferry',
        ];

        $allTypes = [];
        foreach ($possibleTypes as $key => $label) {
            if ($suppliers->where("has_$key", 1)->count() > 0) {
                $allTypes[$key] = $label;
            }
        }

        $taskCount = (clone $query)->count();
        $tasks = $query->orderBy($sortBy, $sortOrder)
            ->orderBy('id', $sortOrder)
            ->paginate(20)
            ->withQueryString();

        $types = Task::distinct()->pluck('type');
        $importedTask = Cache::get('imported_task');

        return view('tasks.index', compact(
            'tasks',
            'taskCount',
            'agents',
            'clients',
            'fullClients',
            'suppliers',
            'types',
            'countries',
            'hotels',
            'paymentMethod',
            'visibleColumns',
            'allTypes',
            'defaultColumns',
            'currencies',
            'listOfCreditors'
        ));
    }

    public function saveColumnPrefs(Request $request)
    {
        $validated = $request->validate([
            'columns' => 'required|array'
        ]);

        session(['visible_task_columns' => $validated['columns']]);

        return response()->json(['success' => true, 'message' => 'Column preferences saved.']);
    }

    public function bulkUpdate(Request $request)
    {
        $taskIds = json_decode($request->input('task_ids'), true);
        $clientId = $request->input('bulk_client_id');
        $agentId = $request->input('bulk_agent_id');
        $paymentMethodId = $request->input('bulk_payment_method_id');

        if (!$taskIds || !is_array($taskIds)) {
            return response()->json(['success' => false, 'message' => 'No tasks selected.']);
        }

        DB::transaction(function () use ($taskIds, $clientId, $agentId, $paymentMethodId) {
            $client = $clientId ? Client::find($clientId) : null;
            foreach ($taskIds as $id) {
                $task = Task::find($id);
                if ($task) {
                    if ($clientId) {
                        $task->client_id = $clientId;
                        $task->client_name = $client->full_name;
                    }
                    if ($agentId) $task->agent_id = $agentId;
                    if ($paymentMethodId) $task->payment_method_account_id = $paymentMethodId;
                    if ($task->is_complete && $task->agent && $task->client) {
                        $shouldEnable = false;
                        if ($task->status === 'void') {
                            $hasJournal = JournalEntry::where('task_id', $task->original_task_id)
                                ->whereHas('transaction', function ($q) {
                                    $q->whereRaw('LOWER(description) LIKE ?', ['%void%']);
                                })
                                ->exists();
                        } else {
                            $hasJournal = JournalEntry::where('task_id', $task->id)->exists();
                        }

                        if ($hasJournal) {
                            $shouldEnable = true;
                        } else {
                            try {
                                $this->processTaskFinancial($task);
                                $shouldEnable = true;
                            } catch (\Exception $e) {
                                $shouldEnable = false;
                                Log::error('Failed to process task financial: ' . $e->getMessage());
                            }
                        }
                        $task->enabled = $shouldEnable;
                    }
                    $task->save();
                }

                $linkedTasks = Task::where('original_task_id', $task->id)->get();
                foreach ($linkedTasks as $linkTask) {
                    if ($client) {
                        $linkTask->client_id = $client->id;
                        $linkTask->client_name = $client->full_name;
                    }
                    if ($agentId) $linkTask->agent_id = $agentId;
                    if ($linkTask->is_complete && $linkTask->agent_id && $linkTask->client_id) {
                        $shouldEnable = false;
                        if ($linkTask->status === 'void') {
                            $hasJournal = JournalEntry::where('task_id', $linkTask->original_task_id)
                                ->whereHas('transaction', function ($q) {
                                    $q->whereRaw('LOWER(description) LIKE ?', ['%void%']);
                                })
                                ->exists();
                        } else {
                            $hasJournal = JournalEntry::where('task_id', $linkTask->id)->exists();
                        }

                        if ($hasJournal) {
                            $shouldEnable = true;
                        } else {
                            try {
                                $this->processTaskFinancial($linkTask);
                                $shouldEnable = true;
                            } catch (\Throwable $e) {
                                $shouldEnable = false;
                                Log::error('Failed to process linked task financial', ['task_id' => $linkTask->id, 'err' => $e->getMessage()]);
                            }
                        }
                        $linkTask->enabled = $shouldEnable;
                    }
                    $linkTask->save();
                }
            }
        });

        return response()->json(['success' => true]);
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('Store task request', ['request' => $request->all()]);
        $request->validate([
            'reference' => 'required|string',
            'status' => 'required',
            'company_id' => 'required|exists:companies,id',
        ]);

        // Basic validation - most fields are now nullable except company_id
        $request->validate([
            'type' => 'nullable|string',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'reference' => 'required|string',
            'original_reference' => 'nullable|string',
            'gds_reference' => 'nullable|string',
            'airline_reference' => 'nullable|string',
            'created_by' => 'nullable|string',
            'issued_by' => 'nullable|string',
            'iata_number' => 'nullable|string',
            'status' => 'required|string',
            'supplier_status' => 'nullable|string',
            'price' => 'nullable|numeric',
            'exchange_currency' => 'nullable|string',
            'original_price' => 'nullable|numeric',
            'original_currency' => 'nullable|string',
            'total' => 'nullable|numeric',
            'original_tax' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'original_total' => 'nullable|numeric',
            'original_surcharge' => 'nullable|numeric',
            'surcharge' => 'nullable|numeric',
            'penalty_fee' => 'nullable|numeric',
            'client_name' => 'nullable|string',
            'agent_id' => 'nullable',
            'client_id' => 'nullable|exists:clients,id',
            'additional_info' => 'nullable|string',
            'taxes_record' => 'nullable|string',
            'enabled' => 'nullable|boolean',
            'refund_date' => 'nullable|date',
            'ticket_number' => 'nullable|string',
            'original_ticket_number' => 'nullable|string',
            'refund_charge' => 'nullable|numeric',
            'task_hotel_details' => 'nullable|array',
            'task_flight_details' => 'nullable|array',
            'task_insurance_details' => 'nullable|array',
            'task_visa_details' => 'nullable|array',
            'file_name' => 'nullable|string',
            'issued_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:now',
            'supplier_pay_date' => 'nullable|date',
        ]);

        if ($request->exchange_currency !== 'KWD') {
            $request->merge([
                'exchange_currency' => 'KWD',
                'original_currency' => $request->exchange_currency,
            ]);
        }

        $amadeus = Supplier::where('name', 'Amadeus')->first();
        $exceptionConvert = [];
        if ($amadeus) $exceptionConvert[] = $amadeus->id;

        $isExchanged = filter_var($request->input('is_exchanged', false), FILTER_VALIDATE_BOOLEAN);
        $originalCurrency = $request->input('original_currency');
        $exchangeCurrency = $request->input('exchange_currency');

        $emptyOrZero = function ($v) {
            return $v === null || $v === '' || round((float)$v, 3) === 0.0;
        };

        $needPriceConversion = !$isExchanged && $request->filled('original_price') && $emptyOrZero($request->input('price'));
        $needTotalConversion = $request->filled('original_total') && $emptyOrZero($request->input('total'));
        $needTaxConversion = $request->filled('original_tax') && $emptyOrZero($request->input('tax'));
        $needSurchargeConversion = $request->filled('original_surcharge') && $emptyOrZero($request->input('surcharge'));

        $shouldConvert = !in_array($request->supplier_id, $exceptionConvert ?? [], true) && $request->filled('original_currency')
            && $request->filled('exchange_currency') && ($needPriceConversion || $needTotalConversion || $needTaxConversion || $needSurchargeConversion);

        if ($shouldConvert) {
            $companyId = $request->company_id;

            $ensureConvert = function (float $amount) use ($companyId, $originalCurrency, $exchangeCurrency) {
                $response = $this->convert($companyId, $originalCurrency, $exchangeCurrency, $amount);

                if (($response['status'] ?? 'success') === 'error' || empty($response['exchange_rate'])) {
                    $currencyExchangeController = new CurrencyExchangeController();
                    $currencyExchangeResponse = $currencyExchangeController->storeProcess(new Request([
                        'company_id' => $companyId,
                        'base_currency' => $originalCurrency,
                        'exchange_currency' => $exchangeCurrency,
                        'is_manual' => false,
                    ]));

                    if (!$currencyExchangeResponse instanceof JsonResponse) {
                        throw new \RuntimeException('Exchange-rate bootstrap failed.');
                    }
                    $data = $currencyExchangeResponse->getData(true);
                    if (($data['status'] ?? 'error') === 'error') {
                        throw new \RuntimeException('Failed to create exchange rate: ' . $data['message']);
                    }

                    $response = $this->convert($companyId, $originalCurrency, $exchangeCurrency, $amount);
                    if (($response['status'] ?? 'success') === 'error' || empty($response['exchange_rate'])) {
                        throw new \RuntimeException('Conversion failed after creating exchange rate.');
                    }
                }

                return [$response['converted_amount'], $response['exchange_rate']];
            };

            try {
                if ($needPriceConversion) {
                    [$price, $rate] = $ensureConvert((float)$request->input('original_price'));
                    $request->merge([
                        'price' => $price,
                        'exchange_rate' => $rate,
                    ]);
                }
                if ($needTotalConversion) {
                    [$total, $rate] = $ensureConvert((float)$request->input('original_total'));
                    $request->merge([
                        'total' => $total,
                        'exchange_rate' => $request->input('exchange_rate', null) ?: $rate,
                    ]);
                }
                if ($needTaxConversion) {
                    [$amount, $rate] = $ensureConvert((float)$request->input('original_tax'));
                    $request->merge(['tax' => round($amount, 3)]);
                    if (!$request->has('exchange_rate')) {
                        $request->merge(['exchange_rate' => $rate]);
                    }
                }
                if ($needSurchargeConversion) {
                    [$amount, $rate] = $ensureConvert((float)$request->input('original_surcharge'));
                    $request->merge(['surcharge' => round($amount, 3)]);
                    if (!$request->has('exchange_rate')) {
                        $request->merge(['exchange_rate' => $rate]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Currency conversion failed: ' . $e->getMessage(), [
                    'original_currency' => $originalCurrency,
                    'exchange_currency' => $exchangeCurrency,
                    'original_price' => $request->input('original_price'),
                    'original_total' => $request->input('original_total'),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Currency conversion failed: ' . $e->getMessage(),
                ], 500);
            }
        }

        if (
            $emptyOrZero($request->input('price')) && $emptyOrZero($request->input('tax')) && $emptyOrZero($request->input('surcharge')) && !$emptyOrZero($request->input('total')) &&
            $request->input('exchange_currency') === 'KWD'
        ) {
            $request->merge([
                'price' => $request->input('total'),
            ]);
        }

        if (strtolower($request->input('status')) === 'emd') {
            $request->merge(['status' => 'issued']);
        }

        $queryChkExistTask = Task::query();
        $queryChkExistTask->where('reference', $request->reference)
            ->where('company_id', $request->company_id)
            ->when(
                in_array(strtolower($request->supplier_name), ['jazeera airways', 'fly dubai']),
                fn($q) => $q->where('supplier_status', $request->supplier_status),
                fn($q) => $q->where('supplier_status', $request->supplier_status)
                    ->where('status', $request->status)
            )
            ->when($request->filled('client_name'), fn($q) => $q->where('passenger_name', trim($request->client_name)))
            ->when($request->filled('supplier_id'), fn($q) => $q->where('supplier_id', $request->supplier_id));

        if ($request->type === 'hotel') {
            $hotelName = data_get($request->task_hotel_details, '0.hotel_name');
            $roomType  = data_get($request->task_hotel_details, '0.room_type');
            $checkIn   = data_get($request->task_hotel_details, '0.check_in');
            $checkOut  = data_get($request->task_hotel_details, '0.check_out');

            $checkIn  = $checkIn  ? Carbon::parse($checkIn)->toDateString()  : null;
            $checkOut = $checkOut ? Carbon::parse($checkOut)->toDateString() : null;

            $existingTask = (clone $queryChkExistTask)
                ->whereHas('hotelDetails', function ($q) use ($checkIn, $checkOut, $hotelName, $roomType) {
                    if ($hotelName) $q->whereHas('hotel', fn($qh) => $qh->where('name', 'LIKE', $hotelName));
                    if ($checkIn) $q->whereDate('check_in', $checkIn);
                    if ($checkOut) $q->whereDate('check_out', $checkOut);
                    if ($roomType) $q->where('room_type', $roomType);
                })->first();
            Log::info('Existing hotel task check', [
                'existing_task_id' => optional($existingTask)->id,
                'hotel_name'       => $hotelName,
                'room_type'        => $roomType,
                'check_in'         => $checkIn,
                'check_out'        => $checkOut,
            ]);
        } else {
            $existingTask = (clone $queryChkExistTask)->first();

            Log::info('Existing non-hotel task check', [
                'existing_task_id' => optional($existingTask)->id,
                'task' => $existingTask ? $existingTask->toArray() : null,
            ]);
        }

        if ($existingTask) {
            if (
                $existingTask->status === 'issued' && in_array($existingTask->supplier->name, ['Jazeera Airways', 'Fly Dubai'])
                && (float)$existingTask->total !== (float)$request->total
            ) {
                Log::warning('This reference has already existed for task: ' . $existingTask->reference . '. Proceeding for Reissued task.');

                $newTaskTotal = (float)$request->total - (float)$existingTask->total;
                Log::info('Deducted total for reissued task: ' . $newTaskTotal . ' from ' . $request->total . ' - ' . $existingTask->total);
                $request->merge([
                    'total' => $newTaskTotal,
                    'status' => 'reissued',
                ]);

                $existsReissue = Task::query()
                    ->where('company_id', $request->company_id)
                    ->where('reference',  $request->reference)
                    ->when($request->filled('supplier_id'), fn($q) => $q->where('supplier_id', $request->supplier_id))
                    ->when($request->filled('client_name'), fn($q) => $q->where('passenger_name', $request->client_name))
                    ->where('status', 'reissued')
                    ->where('original_task_id', $existingTask->id)
                    ->where('total', $newTaskTotal)
                    ->first();

                if ($existsReissue) {
                    Log::info('Idempotent reissue hit: returning existing task', [
                        'action'            => 'reissue_return_existing',
                        'existing_task_id'  => $existsReissue->id,
                        'original_task_id'  => $existsReissue->original_task_id,
                    ]);

                    return response()->json([
                        'status'  => 'success',
                        'message' => 'Existing reissued task returned.',
                        'data'    => $existsReissue,
                    ], 200);
                }
            } elseif (is_null($existingTask->gds_reference) || is_null($existingTask->airline_reference)) {
                $existingTask->fill([
                    'gds_reference'     => $request->gds_reference,
                    'airline_reference' => $request->airline_reference,
                ])->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Task updated with GDS and Airline reference.',
                    'data' => $existingTask,
                ], 200);
            } else {
                $existingTask->issued_date = $request->issued_date;
                $existingTask->save();

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Existing task updated.',
                    'data'    => $existingTask,
                ], 200);
            }

            /*  return response()->json([
                'status' => 'error',
                'message' => 'Task with this reference already exists.',
            ], 422); */
        }

        $amadeusId = Supplier::where('name', 'Amadeus')->value('id');

        if ($request->supplier_id !== $amadeusId) {

            Log::info("remove GDS and Airline references for non-Amadeus tasks", [
                'supplier_id' => $request->supplier_id,
                'gds_reference' => $request->gds_reference,
                'airline_reference' => $request->airline_reference
            ]);

            // Use merge() to update the input array so $request->all() reflects changes
            $request->merge([
                'gds_reference' => null,
                'airline_reference' => null
            ]);

            Log::info("GDS and Airline references removed for non-Amadeus supplier", [
                'supplier_id' => $request->supplier_id,
                'updated_gds_reference' => $request->gds_reference,
                'updated_airline_reference' => $request->airline_reference
            ]);
        }

        $supplierName = Supplier::where('id', $request->supplier_id)->value('name');
        if (strtolower($supplierName) !== 'amadeus') {
            $request->merge([
                'created_by' => null,
                'issued_by'  => null,
            ]);
        }

        $companyName = Company::where('id', $request->company_id)->value('name');
        if (strtolower($companyName) === 'test ojeen' && strtolower($request->status) === 'confirmed') {
            $request->merge(['status' => 'issued']);
        }

        if (strtolower($supplierName) == 'jazeera airways' || strtolower($supplierName) == 'fly dubai' || strtolower($supplierName) == 'vfs') {
            if ($request->status == 'confirmed') {
                $status = 'issued';
            } elseif ($request->status == 'on hold') {
                $status = 'confirmed';
            } else {
                $status = $request->status;
            }
            $request->merge(['status' => $status]);
        }

        // Automatically set expiry date for "confirmed" tasks if not provided
        if ($request->status === 'confirmed' && !$request->expiry_date) {
            // Set default expiry to 48 hours from now for confirmed tasks
            $request->merge(['expiry_date' => Carbon::now()->addHours(48)]);

            Log::info("Auto-set expiry date for confirmed task", [
                'reference' => $request->reference,
                'expiry_date' => $request->expiry_date,
                'company_id' => $request->company_id
            ]);
        }

        // Set default values for nullable fields using merge()
        $request->merge([
            'penalty_fee' => $request->penalty_fee ?? 0,
            'passenger_name' => $request->client_name ?? null,
            'tax' => $request->tax ?? 0,
            'enabled' => $request->enabled ?? false
        ]);

        // Handle original task for non-issued statuses (reissued, refund, void, emd -> issued/reissued)
        if (in_array($request->status, ['reissued', 'refund', 'void', 'emd'])) {
            $originalTask = Task::where('reference', $request->original_reference)
                ->orWhere('reference', $request->reference)
                ->where('passenger_name', $request->passenger_name)
                ->where('company_id', $request->company_id)
                ->whereIn('status', ['issued', 'reissued'])
                ->first();
            if ($originalTask) {
                $request->merge(['original_task_id' => $originalTask->id]);
            }
        }

        // Handle linking issued tasks to their confirmed task (issued -> confirmed)
        if ($request->status === 'issued') {
            $passengerName = $request->client_name ?? $request->passenger_name;
            $confirmedTask = Task::where('reference', $request->reference)
                ->where('company_id', $request->company_id)
                ->where('status', 'confirmed')
                ->where('passenger_name', $passengerName)
                ->first();
            
            if ($confirmedTask) {
                $request->merge(['original_task_id' => $confirmedTask->id]);
                Log::info('[TASK] Linked issued task to confirmed task', [
                    'issued_reference' => $request->reference,
                    'passenger_name' => $passengerName,
                    'confirmed_task_id' => $confirmedTask->id,
                ]);
            }
        }

        if ($request->file_name) {
            $fileUpload = FileUpload::where([
                'file_name' => $request->file_name,
                'company_id' => $request->company_id,
            ])->first();

            if ($fileUpload && $fileUpload->user_id) {
                Log::info("FileUpload found for {$request->file_name}", [
                    'file_upload_id' => $fileUpload->id,
                    'user_id' => $fileUpload->user_id,
                    'supplier_id' => $fileUpload->supplier_id
                ]);

                $agent = Agent::where('user_id', $fileUpload->user_id)->first();

                if ($agent) {
                    $request->merge(['agent_id' => $agent->id]);
                    Log::info("Assigned agent_id from file uploader", [
                        'file_name' => $request->file_name,
                        'user_id' => $fileUpload->user_id,
                        'agent_id' => $agent->id,
                        'reason' => 'File uploader is an agent'
                    ]);
                } else {
                    Log::info("File uploader is not an agent", [
                        'file_name' => $request->file_name,
                        'user_id' => $fileUpload->user_id,
                        'user_type' => 'admin_or_company'
                    ]);
                }
            } else {
                Log::warning("FileUpload not found or no user_id for file: {$request->file_name}");
            }
        }

        DB::beginTransaction();

        try {
            Log::debug('Task Data:', $request->all());

            $issuedDate            = $request->input('issued_date');
            $cancellationDeadline  = $request->input('cancellation_deadline');
            $task_type             = $request->input('service.type') ?? $request->input('type');

            $supplier_pay_date = $issuedDate;

            if ($task_type === 'hotel' && $cancellationDeadline) {
                if ($cancellationDeadline <= $issuedDate) {
                    $supplier_pay_date = $issuedDate;
                } elseif ($cancellationDeadline > $issuedDate) {
                    $supplier_pay_date = $cancellationDeadline;
                }
            }

            $data = $request->all();
            $data['supplier_pay_date'] = $supplier_pay_date;

            $task = Task::create($data);

            if ($task->supplier_id) {
                $supplierCompany = SupplierCompany::where('supplier_id', $task->supplier_id)
                    ->where('company_id', $task->company_id)
                    ->first();

                if ($supplierCompany) {
                    $totalSurcharge = 0;

                    $surcharges = SupplierSurcharge::with('references')
                        ->where('supplier_company_id', $supplierCompany->id)
                        ->get();

                    foreach ($surcharges as $surcharge) {
                        Log::info('Processing surcharge', [
                            'mode' => $surcharge->charge_mode,
                            'amount' => $surcharge->amount,
                            'reference_count' => $surcharge->references->count(),
                        ]);

                        if ($surcharge->charge_mode === 'task') {
                            if ($surcharge->canChargeForStatus($task->status)) {
                                Log::info('Adding task surcharge', [
                                    'amount' => $surcharge->amount,
                                    'status' => $task->status,
                                ]);
                                $totalSurcharge += $surcharge->amount;
                            }
                        }

                        if ($surcharge->charge_mode === 'reference') {
                            $response = SupplierSurchargeReference::createSurchargeRecord($task, $surcharge);
                            Log::info('Successfully created surcharge reference record');

                            if (!$response) {
                                Log::error('Failed to create reference surcharge record');
                            }

                            Log::info('Checking reference', [
                                'ref_id' => $response->id,
                                'ref_value' => $response->reference,
                                'is_charged' => $response->is_charged,
                                'behavior' => $surcharge->charge_behavior,
                            ]);

                            $canCharge = $response->canBeCharged($surcharge->charge_behavior);

                            if ($canCharge) {
                                $totalSurcharge += $surcharge->amount;
                                Log::info('Added reference surcharge', [
                                    'ref_value' => $response->reference,
                                    'amount' => $surcharge->amount,
                                    'total' => $totalSurcharge,
                                ]);

                                if ($surcharge->charge_behavior === 'single') {
                                    $response->markAsCharged();
                                    Log::info('Marked reference as charged', [
                                        'ref_value' => $response->reference,
                                    ]);
                                }
                            } else {
                                Log::info('Skipped reference surcharge (already charged)', [
                                    'ref_value' => $response->reference,
                                ]);
                            }
                        }
                    }

                    Log::info('Total surcharge computed', [
                        'task_id' => $task->id,
                        'total' => $totalSurcharge,
                    ]);

                    if ($totalSurcharge > 0) {
                        try {
                            $task->update([
                                'supplier_surcharge' => $totalSurcharge,
                            ]);

                            Log::info('Updated task with total surcharge', [
                                'task_id' => $task->id,
                                'total_surcharge' => $task->supplier_surcharge,
                            ]);
                        } catch (Exception $e) {
                            Log::error('Failed to update task with total surcharge', [
                                'task_id' => $task->id,
                                'error' => $e->getMessage(),
                            ]);
                        }

                    }
                }
            }

            // Auto-link task with active AutoBilling rule if matching created_by / issued_by / agent_id
            $matchedRule = AutoBilling::where('company_id', $task->company_id)
                ->where('is_active', true)
                ->where(function ($q) use ($task) {
                    $q->where('created_by', $task->created_by)
                        ->orWhere('issued_by', $task->issued_by)
                        ->orWhere('agent_id', $task->agent_id);
                })
                ->get()
                ->first(
                    fn($r) => (!$r->created_by || $r->created_by === $task->created_by) &&
                        (!$r->issued_by || $r->issued_by  === $task->issued_by) &&
                        (!$r->agent_id || $r->agent_id === $task->agent_id)
                );

            if ($matchedRule) {
                $task->update(['client_id' => $matchedRule->client_id]);
                Log::info("[Task Store] Auto-linked task {$task->id} with AutoBilling rule #{$matchedRule->id} (Client ID {$matchedRule->client_id})");
            } else {
                Log::info("[Task Store] No AutoBilling rule matched for task {$task->id} (created_by: {$task->created_by}, issued_by: {$task->issued_by}, agent_id: {$task->agent_id})");
            }

            if ($task->type === 'hotel' && $request->has('task_hotel_details') && !empty($request->task_hotel_details)) {
                $this->saveHotelDetails($request->task_hotel_details, $task->id);
            } elseif ($task->type === 'flight' && $request->has('task_flight_details') && !empty($request->task_flight_details)) {
                $this->saveFlightDetails($request->task_flight_details, $task->id);
            } elseif ($task->type === 'insurance' && $request->has('task_insurance_details') && !empty($request->task_insurance_details)) {
                $this->saveInsuranceDetails($request->task_insurance_details, $task->id);
            } elseif ($task->type === 'visa' && $request->has('task_visa_details') && !empty($request->task_visa_details)) {
                $this->saveVisaDetails($request->task_visa_details, $task->id);
            }

            $supplierMagicHoliday = Supplier::where('name', 'Magic Holiday')->first();

            if ($task->client_ref && $supplierMagicHoliday && $task->supplier_id == $supplierMagicHoliday->id) {

                $hotelBooking = HotelBooking::where('client_ref', $task->client_ref)->first();

                if ($hotelBooking) {
                    $payment = $hotelBooking->payment;

                    $task->is_n8n_booking = true;

                    if ($payment) {
                        $task->enabled = true;
                        $task->client_id = $payment->client_id;
                        $task->client_name = $payment->client->full_name;
                        $task->agent_id = $payment->agent_id;
                        $generateInvoiceResponse = app(InvoiceController::class)->autoGenerateInvoice($task, $payment);
                        Log::info('Auto-generated invoice for n8n hotel booking task: ' . $task->reference, $generateInvoiceResponse);
                    } else {
                        Log::warning("MagicHoliday task: No payment found for client_ref {$task->client_ref}");
                        $task->enabled = false;
                        $task->agent_id = $task->agent_id ?? null;
                    }

                    $task->save();
                } else {
                    Log::warning('No HotelBooking found for Magic Holiday task with client_ref: ' . $task->client_ref);
                }
            }

            $supplierTBO = Supplier::where('name', 'LIKE', '%TBO%')->orWhere('name', 'TBO Holiday')->first();
            
            if ($task->booking_reference && $supplierTBO && $task->supplier_id == $supplierTBO->id) {

                $tboBooking = TBO::where('booking_reference_id', $task->booking_reference)
                    ->orWhere('confirmation_no', $task->reference)
                    ->first();

                if ($tboBooking && $tboBooking->hotel_booking_id) {
                    $hotelBooking = HotelBooking::find($tboBooking->hotel_booking_id);
                    
                    if ($hotelBooking && $hotelBooking->payment_id) {
                        $payment = Payment::find($hotelBooking->payment_id);
                        
                        if ($payment) {
                            $task->is_n8n_booking = true;
                            $task->enabled = true;
                            
                            $generateInvoiceResponse = app(InvoiceController::class)->autoGenerateInvoice($task, $payment);
                            Log::info('Auto-generated invoice for TBO hotel booking task: ' . $task->reference, $generateInvoiceResponse);
                            
                            $task->save();
                        } else {
                            Log::warning("TBO task: No payment found for TBO booking {$tboBooking->id}");
                        }
                    } else {
                        Log::warning("TBO task: No hotel booking or payment found for TBO booking {$tboBooking->id}");
                    }
                } else {
                    Log::warning('No TBO booking found for task with reference: ' . $task->reference);
                }
            }

            // Set enabled status: task must be complete AND have an agent assigned
            if ($task->is_complete && $task->agent_id && $task->client) {
                $task->enabled = true;
                $task->save();
                Log::info('Task enabled for complete task with agent: ' . $task->reference);
            } else {
                $task->enabled = false;
                $task->save();
                Log::info('Task disabled - reason: ' . (!$task->is_complete ? 'incomplete' : 'no agent assigned') . ' - task: ' . $task->reference);
            }

            $task->loadMissing('supplier');

            $offline = ($task->type === 'hotel' && $task->supplier_id)
                ? ! (bool) data_get($task, 'supplier.is_online', true)
                : false;

            // Process financial transactions immediately if task is complete (regardless of agent assignment)
            // This ensures company liability to supplier is tracked immediately
            // Special case: Void tasks should ALWAYS process financials if they have an original_task_id
            $isZeroTotalSupplier = (str_contains($supplierName, 'trendy travel') || str_contains($supplierName, 'alam al raya travel')) && empty((float) $task->total);
            $shouldProcessFinancials = ($offline && $task->is_complete || $task->status !== 'confirmed' || ($task->status == 'void' && $task->original_task_id)) && !$isZeroTotalSupplier;

            if ($shouldProcessFinancials) {
                $supplierName = strtolower(optional($task->supplier)->name ?? '');

                $reason = $task->is_complete ? 'complete task' : 'void task with original_task_id';
                Log::info("Processing financial transactions for {$reason}: " . $task->reference . ' (agent_id: ' . ($task->agent_id ?? 'none') . ')');
                $this->processTaskFinancial($task);
            } else {
                Log::warning('Financial processing skipped for task: ' . $task->reference . ' - reason: ' . ($offline ? 'incomplete' : 'not offline supplier') . ' - status: ' . $task->status);
            }

            $issuedBy = $task->issued_by;
            $iataNumber = $task->iata_number;

            $wallet = Wallet::where('iata_number', $task->iata_number)->first();

            $liabilities = Account::where('name', 'like', '%Liabilities%')
                ->value('id');
            Log::info('Liabilities ID', ['id' => $liabilities]);

            $accountPayable = Account::where('name', 'like', '%Accounts Payable%')
                ->where('parent_id', $liabilities)
                ->value('id');
            Log::info('accountPayable ID', ['id' => $accountPayable]);

            $creditors = Account::where('name', 'like', '%Creditors%')
                ->where('root_id', $liabilities)
                ->where('parent_id', $accountPayable)
                ->value('id');
            Log::info('creditors ID', ['id' => $creditors]);

            if ($iataNumber) {
                Log::info('IATA Number existed. Starting to automate');

                if ($task->supplier_id == '2') {
                    if ($issuedBy == 'KWIKT211N' && $iataNumber == '42230215') {
                        Log::info('Issued By City Travelers: ', [
                            'issued_by' => $issuedBy,
                            'iata_number' => $iataNumber,
                        ]);

                        $payment_method_account_id = Account::where('name', 'like', '%City Travelers (EasyPay)%')
                            ->where('root_id', $liabilities)
                            ->where('parent_id', $creditors)
                            ->value('id');

                        $task->update([
                            'payment_method_account_id' => $payment_method_account_id
                        ]);

                        $response = $this->updateJournalPaymentMethod($task, $payment_method_account_id);
                        Log::info('response', [
                            'data' => $response
                        ]);
                        if (!$response instanceof JsonResponse) {
                            Log::error('Response from updateJournalPaymentMethod is not a JsonResponse', [
                                'task_id' => $task->id,
                                'expected_type' => JsonResponse::class,
                                'actual_type' => is_object($response) ? get_class($response) : gettype($response)
                            ]);

                            throw new Exception('Failed to update payment method journal entries');
                        }

                        if ($response->getData(true)['status'] !== 'success') {
                            Log::error('Failed to update payment method journal entries', [
                                'task_id' => $task->id,
                                'error_message' => $response->getData()->message
                            ]);

                            throw new Exception('Failed to update payment method journal entries: ' . $response->getData()->message);
                        }

                        $wallet = Wallet::where('iata_number', $iataNumber)
                            ->latest('created_at')
                            ->first();

                        if ($wallet) {
                            $openingBalance = $wallet->closing_balance ?? $wallet->wallet_balance;
                        }

                        $closingBalance = $openingBalance - $task->total;

                        Wallet::create([
                            'iata_number'     => $iataNumber,
                            'currency'        => $task->exchange_currency ?? 'KWD',
                            'opening_balance' => $openingBalance,
                            'task_amount'     => $task->total,
                            'closing_balance' => $closingBalance,
                        ]);

                        Log::info("Wallet record created for task ID {$task->id}", [
                            'opening_balance' => $openingBalance,
                            'task_amount' => $task->total,
                            'closing_balance' => $closingBalance
                        ]);
                    } elseif ($issuedBy == 'KWIKT2843') {
                        Log::info('Issued By Como Travel: ', [
                            'issued_by' => $issuedBy,
                            'iata_number' => $iataNumber,
                        ]);

                        $payment_method_account_id = Account::where('name', 'like', '%Como Travel & Tourism%')->value('id');

                        $response = $this->updateJournalPaymentMethod($task, $payment_method_account_id);
                        Log::info('response', [
                            'data' => $response
                        ]);
                        if (!$response instanceof JsonResponse) {
                            Log::error('Response from updateJournalPaymentMethod is not a JsonResponse', [
                                'task_id' => $task->id,
                                'expected_type' => JsonResponse::class,
                                'actual_type' => is_object($response) ? get_class($response) : gettype($response)
                            ]);

                            throw new Exception('Failed to update payment method journal entries');
                        }

                        if ($response->getData(true)['status'] !== 'success') {
                            Log::error('Failed to update payment method journal entries', [
                                'task_id' => $task->id,
                                'error_message' => $response->getData()->message
                            ]);

                            throw new Exception('Failed to update payment method journal entries: ' . $response->getData()->message);
                        }
                    }
                } elseif ($task->supplier_id == '29' || $task->supplier_id == '38' || $task->supplier_id == '39') {
                    Log::info('NDC Suppliers detected. Starting the process to automate payment method into using IATA City Travelers (EasyPay)');

                    $payment_method_account_id = Account::where('name', 'like', '%City Travelers (EasyPay)%')
                        ->where('root_id', $liabilities)
                        ->where('parent_id', $creditors)
                        ->value('id');

                    $task->update([
                        'payment_method_account_id' => $payment_method_account_id
                    ]);

                    $response = $this->updateJournalPaymentMethod($task, $payment_method_account_id);
                    Log::info('response', [
                        'data' => $response
                    ]);
                    if (!$response instanceof JsonResponse) {
                        Log::error('Response from updateJournalPaymentMethod is not a JsonResponse', [
                            'task_id' => $task->id,
                            'expected_type' => JsonResponse::class,
                            'actual_type' => is_object($response) ? get_class($response) : gettype($response)
                        ]);

                        throw new Exception('Failed to update payment method journal entries');
                    }

                    if ($response->getData(true)['status'] !== 'success') {
                        Log::error('Failed to update payment method journal entries', [
                            'task_id' => $task->id,
                            'error_message' => $response->getData()->message
                        ]);

                        throw new Exception('Failed to update payment method journal entries: ' . $response->getData()->message);
                    }

                    $wallet = Wallet::where('iata_number', $iataNumber)
                        ->latest('created_at')
                        ->first();

                    if ($wallet) {
                        $openingBalance = $wallet->closing_balance ?? $wallet->wallet_balance;
                    }

                    $closingBalance = $openingBalance - $task->total;

                    Wallet::create([
                        'iata_number'     => $iataNumber,
                        'currency'        => $task->exchange_currency ?? 'KWD',
                        'opening_balance' => $openingBalance,
                        'task_amount'     => $task->total,
                        'closing_balance' => $closingBalance,
                    ]);

                    Log::info("Wallet record created for task ID {$task->id}", [
                        'opening_balance' => $openingBalance,
                        'task_amount' => $task->total,
                        'closing_balance' => $closingBalance
                    ]);
                }

                $company = Company::find($task->company_id);

                $this->storeNotification([
                    'user_id' => $company->user_id,
                    'title' => 'IATA City Travelers (EasyPay) successfully deducted',
                    'message' => 'IATA City Travelers (EasyPay) balance has deducted KWD ' . $task->total . ' for task ID: ' . $task->id,
                ]);
            } else {
                Log::info('No IATA wallet detected. Skipping the automation');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Task created successfully.',
                'data' => $task,
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Task creation failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Task creation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function storeManualHotel(Request $request)
    {
        $request->validate([
            'reference' => 'required|string',
            'company_id' => 'required|integer',
            'supplier_id' => 'required|integer',
            'client_id' => 'nullable|integer',
            'client_name' => 'nullable|string',
            'original_currency' => 'nullable|string',
            'original_total' => 'nullable|numeric',
            'original_price' => 'nullable|numeric',
            'total' => 'required|numeric',
            'price' => 'required|numeric',
            'issued_date' => 'required|date',
            'additional_info' => 'nullable|string',
            'hotel_id' => 'required|integer',
            'check_in' => 'required|date',
            'check_out' => 'required|date',
            'room_name' => 'required|string',
            'passengers' => 'array',
            'passengers.*' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $passengers = array_values(array_filter(
                (array) $request->input('passengers', []),
                fn($v) => trim((string)$v) !== ''
            ));
            $firstPassenger = $passengers[0] ?? null;

            $agentId = null;
            if (Auth::user()->role_id == Role::AGENT) {
                $agentId = Auth::user()->agent->id;
            }

            $checkIn = Carbon::parse($request->input('check_in'))->toDateString();
            $checkOut = Carbon::parse($request->input('check_out'))->toDateString();
            $roomType = $request->input('room_name');

            $existQuery = Task::query()
                ->where('reference', $request->reference)
                ->where('company_id', $request->company_id)
                ->where('status', 'issued')
                ->where('supplier_status', 'issued')
                ->when($request->filled('client_name'), fn($q) => $q->where('passenger_name', trim($request->client_name)))
                ->when($request->filled('supplier_id'), fn($q) => $q->where('supplier_id', $request->supplier_id))
                ->whereHas('hotelDetails', function ($q) use ($request, $checkIn, $checkOut, $roomType) {
                    $q->where('hotel_id', $request->input('hotel_id'))
                        ->whereDate('check_in',  $checkIn)
                        ->whereDate('check_out', $checkOut);
                    if ($roomType) $q->where('room_type', $roomType);
                });

            $existingTask = $existQuery->first();

            Log::info('Manual hotel existing task check', [
                'existing_task_id' => optional($existingTask)->id,
                'hotel_id' => $request->input('hotel_id'),
                'room_type' => $roomType,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
            ]);

            if ($existingTask) {
                DB::rollBack();
                return redirect()->back()->withErrors(['error' => 'Task already created for this booking information.'])->withInput();
            }

            $origCurrency = $request->input('original_currency');

            $task = Task::create([
                'type' => 'hotel',
                'reference' => $request->input('reference'),
                'company_id' => $request->input('company_id'),
                'status' => 'issued',
                'supplier_status' => 'issued',
                'issued_date' => $request->input('issued_date'),
                'supplier_pay_date' => $request->input('issued_date'),
                'supplier_id' => $request->input('supplier_id'),
                'client_id' => $request->input('client_id'),
                'client_name' => $request->input('client_name'),
                'passenger_name' => $firstPassenger,
                'agent_id' => $agentId,
                'original_currency' => $origCurrency ?? null,
                'exchange_currency' => 'KWD',
                'original_price' => $origCurrency !== 'KWD' ? $request->input('original_price') : null,
                'original_total' => $origCurrency !== 'KWD' ? $request->input('original_total') : null,
                'price' => $request->input('price'),
                'total' => $request->input('total'),
                'additional_info' => $request->input('additional_info'),
            ]);

            $roomDetails = ([
                'name' => $request->input('room_name') ?? null,
                'board' => null,
                'boardBasis' => null,
                'info' => null,
                'type' => null,
                'passengers' => $passengers ?: null,
            ]);

            TaskHotelDetail::create([
                'task_id' => $task->id,
                'hotel_id' => $request->input('hotel_id'),
                'check_in' => $request->input('check_in'),
                'check_out' => $request->input('check_out'),
                'room_type' => $request->input('room_name'),
                'room_details' => $roomDetails ? json_encode($roomDetails) : null,
            ]);

            if ($task->is_complete && $task->agent && $task->client) {
                $task->enabled = true;
            } else {
                $task->enabled = false;
            }
            $task->save();

            $task->loadMissing('supplier');
            $offline = ($task->type === 'hotel' && $task->supplier_id) ? !(bool) data_get($task, 'supplier.is_online', true) : false;
            $shouldProcessFinancials = $offline && $task->is_complete;

            if ($shouldProcessFinancials) {
                Log::info("Processing financial transactions for complete task: " . $task->reference . ' (agent_id: ' . ($task->agent_id ?? 'none') . ')');
                $this->processTaskFinancial($task);
            } else {
                Log::warning('Financial processing skipped (task not complete): ' . $task->reference);
            }

            DB::commit();

            return redirect()->back()->with('success', 'Manual hotel task created successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Manual Task creation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->withErrors(['error' => 'Task creation failed: ' . $e->getMessage()])->withInput();
        }
    }

    private function triggerCheckTaskEvent(Task $task, string $reason = 'manual_trigger')
    {
        // Trigger the check status event for the task
        event(new \App\Events\CheckConfirmedOrIssuedTask($task, $reason));

        Log::info("Triggered CheckConfirmedOrIssuedTask event", [
            'task_id' => $task->id,
            'reference' => $task->reference,
            'status' => $task->status,
            'reason' => $reason
        ]);
    }

    private function getOrCreateCurrencySpecificAccount(Task $task, $supplierPayableAccount, $currency, $branchId)
    {
        $supplier = Supplier::find($task->supplier_id);
        $accountName = $supplier->name . ' (' . $currency . ')';

        // Check if the currency-specific account already exists
        $currencySpecificAccount = Account::where('name', $accountName)
            ->where('company_id', $task->company_id)
            ->where('parent_id', $supplierPayableAccount->id)
            ->where('currency', $currency)
            ->first();

        if (!$currencySpecificAccount) {
            Log::info('Creating new currency-specific account: ' . $accountName);

            // Get the next available code
            $code = 2151;
            $lastChildAccount = Account::where('company_id', $task->company_id)
                ->where('parent_id', $supplierPayableAccount->id)
                ->orderBy('code', 'desc')
                ->first();

            if ($lastChildAccount) {
                $code = $lastChildAccount->code + 1;
            }

            try {
                $currencySpecificAccount = Account::create([
                    'name' => $accountName,
                    'parent_id' => $supplierPayableAccount->id,
                    'company_id' => $task->company_id,
                    'branch_id' => $branchId,
                    'root_id' => $supplierPayableAccount->root_id,
                    'code' => $code,
                    'account_type' => 'liability',
                    'report_type' => 'balance sheet',
                    'level' => $supplierPayableAccount->level + 1,
                    'is_group' => 0,
                    'disabled' => 0,
                    'actual_balance' => 0.00,
                    'budget_balance' => 0.00,
                    'variance' => 0.00,
                    'currency' => $currency,
                ]);

                Log::info('Created currency-specific account: ' . $accountName, [
                    'account_id' => $currencySpecificAccount->id,
                    'currency' => $currency,
                    'parent_id' => $supplierPayableAccount->id
                ]);
            } catch (Exception $e) {
                Log::error('Failed to create currency-specific account: ' . $e->getMessage(), [
                    'task_reference' => $task->reference,
                    'account_name' => $accountName,
                    'currency' => $currency,
                    'supplier_payable_id' => $supplierPayableAccount->id,
                    'exception' => $e->getMessage()
                ]);
                throw new Exception('Failed to create currency-specific account: ' . $e->getMessage());
            }
        }

        return $currencySpecificAccount;
    }

    private function getTaskBranchId(Task $task)
    {
        if ($task->agent && $task->agent->branch_id) {
            return $task->agent->branch_id;
        }

        // Get company's main branch if no agent
        $company = \App\Models\Company::find($task->company_id);
        if (!$company) {
            throw new Exception('Company not found for task: ' . $task->reference);
        }

        $mainBranch = $company->getMainBranch();
        return $mainBranch->id;
    }

    public function processTaskFinancial(Task $task)
    {
        if (!in_array($task->status, ['issued', 'reissued', 'void', 'refund', 'emd'], true)) {
            Log::info('Skipping financial processing for task: ' . $task->reference . ' - status: ' . $task->status);
            return;
        }
        Log::info('Processing financial for task: ' . $task->reference);

        // Special handling for void tasks: they should process even if incomplete
        // as long as they have an original_task_id to reference
        if ($task->status === 'void') {
            if (!$task->original_task_id) {
                Log::error('Cannot process financial for void task without original_task_id: ' . $task->reference, [
                    'is_complete' => $task->is_complete,
                    'status' => $task->status,
                    'original_task_id' => $task->original_task_id
                ]);

                throw new Exception('Cannot process financial for void task without original_task_id: ' . $task->reference);
            }
        } else {
            if (!$task->is_complete) {

                //get missing field that caused incomplete task from getMissingFields method
                $missingFields = $this->getMissingFields($task);

                Log::error('Cannot process financial for incomplete task: ' . $task->reference, [
                    'is_complete' => $task->is_complete,
                    'status' => $task->status,
                    'original_task_id' => $task->original_task_id,
                    'missing_fields' => $missingFields
                ]);

                throw new Exception('Cannot process financial for incomplete task: ' . $task->reference);
            }
        }

        // Get branch_id - from agent if exists, otherwise from company's main branch
        $branchId = $this->getTaskBranchId($task);

        $supplierCompany = SupplierCompany::where('supplier_id', $task->supplier_id)
            ->where('company_id', $task->company_id)
            ->first();

        if (!$supplierCompany) {
            throw new Exception('Supplier company not activated or not found.');
        }

        $liabilities = Account::where('name', 'like', '%Liabilities%')
            ->where('company_id', $task->company_id)
            ->first();

        $expenses = Account::where('name', 'like', '%Expenses%')
            ->where('company_id', $task->company_id)
            ->first();

        if (!$liabilities || !$expenses) {
            throw new Exception('Liabilities or Expenses account not found.');
        }

        $receivableAccount = Account::where('name', 'like', '%Receivable%')
            ->where('company_id', $task->company_id)
            ->first();

        Log::info('Receivable Account: ', ['account' => $receivableAccount]);

        if (!$receivableAccount) {
            throw new Exception('Receivable account not found.');
        }

        $supplier = Supplier::find($task->supplier_id);
        $supplierPayable = Account::where('name', $supplier->name)
            ->where('company_id', $task->company_id)
            ->where('root_id', $liabilities->id)
            ->first();

        $supplierCost = Account::where('name', $supplier->name)
            ->where('company_id', $task->company_id)
            ->where('root_id', $expenses->id)
            ->first();

        $issuedByAccount = null;

        Log::info('Supplier Payable Account: ', ['account' => $supplierPayable]);

        if (in_array($task->type, ['flight', 'visa'])) {
            Log::info('Processing flight task financial for: ' . $task->reference);
            $companyIssuedBy = $task->issued_by ?? 'Not Issued';

            Log::info('Issued by value determination', [
                'original_issued_by' => $task->issued_by,
                'final_company_issued_by' => $companyIssuedBy,
                'is_null_issued_by' => is_null($task->issued_by)
            ]);

            $issuedByAccount = Account::where('name', $companyIssuedBy)
                ->where('company_id', $task->company_id)
                ->where('root_id', $liabilities->id)
                ->where('parent_id', $supplierPayable->id)
                ->first();

            Log::info('Issued By Account: ', ['account' => $issuedByAccount]);

            if (!$issuedByAccount) {
                Log::info('Creating new issued by account for: ' . $companyIssuedBy . ' (was null: ' . (is_null($task->issued_by) ? 'yes' : 'no') . ')');
                $code = 2151;
                $lastIssuedByAccount = Account::where('company_id', $task->company_id)
                    ->where('root_id', $liabilities->id)
                    ->where('parent_id', $supplierPayable->id)
                    ->orderBy('code', 'desc')
                    ->first();

                if ($lastIssuedByAccount) {
                    $code = $lastIssuedByAccount->code + 1;
                }

                try {
                    $issuedByAccount = Account::create([
                        'name' => $companyIssuedBy,
                        'parent_id' => $supplierPayable->id,
                        'company_id' => $task->company_id,
                        'branch_id' => $branchId,
                        'root_id' => $liabilities->id,
                        'code' => $code,
                        'account_type' => 'liability',
                        'report_type' => 'balance sheet',
                        'level' => $supplierPayable->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                    ]);

                    Log::info('New issued by account created for task: ' . $task->reference, [
                        'issuedByAccount' => $issuedByAccount,
                        'account_id' => $issuedByAccount ? $issuedByAccount->id : 'null',
                        'account_name' => $issuedByAccount ? $issuedByAccount->name : 'null'
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to create issued by account: ' . $e->getMessage(), [
                        'task_reference' => $task->reference,
                        'company_issued_by' => $companyIssuedBy,
                        'supplier_payable_id' => $supplierPayable->id,
                        'exception' => $e->getMessage()
                    ]);
                    throw new Exception('Failed to create issued by account: ' . $e->getMessage());
                }
            }

            // Final validation that we have a valid issued by account for flight tasks
            if (!$issuedByAccount) {
                Log::error('Flight task still does not have issued by account after creation attempt', [
                    'task_reference' => $task->reference,
                    'issued_by' => $task->issued_by,
                    'company_issued_by' => $companyIssuedBy ?? 'undefined'
                ]);
                throw new Exception('Failed to create or find issued by account for flight task.');
            }
        }

        $jazeera = Supplier::where('name', 'Jazeera Airways')->first();

        $isJazeera = $jazeera !== null ? $task->supplier_id == $jazeera->id : false;

        $currencySpecificAccount = null;
        if ($task->type == 'hotel' && !$isJazeera) {
            if ($jazeera ? $task->supplier_id == $jazeera->id : false) {
                Log::info('Processing hotel task for Jazeera Airways - using supplier payable account directly: ' . $task->reference);
            }
            if ($task->original_currency && $task->original_currency !== 'KWD') {
                // Create or find the original currency child account under supplier payable
                Log::info('Processing hotel task with original currency: ' . $task->original_currency . ' for task: ' . $task->reference);
                $currencySpecificAccount = $this->getOrCreateCurrencySpecificAccount(
                    $task,
                    $supplierPayable,
                    $task->original_currency,
                    $branchId
                );

                Log::info('Original currency account for hotel task: ', [
                    'account' => $currencySpecificAccount,
                    'currency' => $task->original_currency,
                    'original_price' => $task->original_price
                ]);
            } else {
                // Even for KWD, create a KWD-specific child account for consistency
                Log::info('Processing hotel task with KWD currency for task: ' . $task->reference);
                $currencySpecificAccount = $this->getOrCreateCurrencySpecificAccount(
                    $task,
                    $supplierPayable,
                    'KWD',
                    $branchId
                );

                Log::info('KWD currency account for hotel task: ', [
                    'account' => $currencySpecificAccount,
                    'currency' => 'KWD',
                    'amount' => $task->total
                ]);
            }
        }

        if (!$supplierCost || !$supplierPayable) {
            Log::error('Supplier cost or payable account not found for task: ' . $task->reference);
            throw new Exception('Supplier account not found.');
        }

        Log::info('Processing task financials for: ' . $task->reference, [
            'supplierCost' => $supplierCost,
            'supplierPayable' => $supplierPayable,
            'issuedByAccount' => $issuedByAccount,
            'supplierCompany' => $supplierCompany,
        ]);

        // Additional validation: For flight tasks, we must have an issuedByAccount to avoid using parent account
        if ($task->type == 'flight' && !$issuedByAccount) {
            Log::error('Flight task missing issued by account - this should not happen!', [
                'task_reference' => $task->reference,
                'issued_by' => $task->issued_by,
                'supplier_payable_has_children' => $supplierPayable->children()->exists()
            ]);
            throw new Exception('Flight task must have a valid issued by account to avoid using parent account.');
        }

        // Process based on status
        switch (strtolower($task->status)) {
            case 'issued':
                Log::info('Processing issued task financial for: ' . $task->reference);
                $this->processIssuedTask($task, $supplierCost, $supplierPayable, $issuedByAccount, $supplierCompany, $branchId, $currencySpecificAccount);
                break;
            case 'reissued':
                Log::info('Processing reissued task financial for: ' . $task->reference);
                $this->processIssuedTask($task, $supplierCost, $supplierPayable, $issuedByAccount, $supplierCompany, $branchId, $currencySpecificAccount);
                break;
            case 'emd':
                Log::info('Processing EMD task financial for: ' . $task->reference);
                $this->processIssuedTask($task, $supplierCost, $supplierPayable, $issuedByAccount, $supplierCompany, $branchId, $currencySpecificAccount);
                break;
            case 'void':
                Log::info('Processing void task financial for: ' . $task->reference);
                $this->processVoidTask($task, $branchId);
                break;
            case 'refund':
                Log::info('Processing refund task financial for: ' . $task->reference);
                $this->processRefundTask($task, $branchId);
                break;
            default:
                Log::error('Task status not recognized for financial processing: ' . $task->status);
                throw new Exception('Task status not recognized for financial processing: ' . $task->status);
        }
    }

    private function getMissingFields(Task $task): string
    {
        $missingFields = [];

        // Define custom messages for each required field
        $fieldMessages = [
            'client_id' => 'Please update the client',
            'company_id' => 'Company information is missing',
            'supplier_id' => 'Supplier must be assigned to this task',
            'type' => 'Task type (flight/hotel) must be specified',
            'status' => 'Task status is required',
            'client_name' => 'Client name is required',
            'reference' => 'Reference number is mandatory',
            'total' => 'Total amount must be specified',
        ];

        foreach ($task->getRequiredColumns() as $column) {
            if (empty($task->$column) && $task->$column != 0 && $task->$column != '0') {
                // Use custom message if available, otherwise use default format
                $message = $fieldMessages[$column] ?? ucfirst(str_replace('_', ' ', $column)) . ' is required';
                $missingFields[] = $message;
            }
        }

        return implode(', ', $missingFields);
    }

    private function processIssuedTask(Task $task, $supplierCost, $supplierPayable, $issuedByAccount, $supplierCompany, $branchId, $currencySpecificAccount = null)
    {
        // Use task's issued_date as transaction_date
        $transactionDate = $task->supplier_pay_date ? Carbon::parse($task->supplier_pay_date) : Carbon::now();

        $transaction = Transaction::create([
            'branch_id' => $branchId,
            'company_id' => $task->company_id,
            'entity_id' => $task->company_id,
            'entity_type' => 'company',
            'transaction_type' => 'credit',
            'amount' => $task->total,
            'description' => 'Task created: ' . $task->reference,
            'reference_type' => 'Payment',
            'transaction_date' => $transactionDate,
        ]);

        if (!$transaction) {
            throw new Exception('Transaction creation failed.');
        }

        // Create expense journal entry (debit supplier cost)
        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $supplierCost->id,
            'task_id' => $task->id,
            'agent_id' => $task->agent_id,
            'transaction_date' => $transactionDate,
            'description' => 'Task from supplier (Expenses): ' . $supplierCompany->supplier->name,
            'name' => $supplierCompany->supplier->name,
            'debit' => $task->total,
            'credit' => 0,
            'balance' => $task->total,
            'type' => 'payable',
        ]);

        // Create liability journal entry - determine which account to use
        $liabilityAccountId = null;
        $liabilityAmount = $task->total;
        $liabilityDescription = 'Records Payable to (Liabilities): ' . $supplierCompany->supplier->name;
        $originalCurrency = null;
        $originalAmount = null;

        // Priority order for liability account selection:
        // 1. Currency-specific account for hotel tasks (both original currency and KWD)
        // 2. Issued by account for flight tasks  
        // 3. Default supplier payable account

        if ($currencySpecificAccount && $task->type == 'hotel') {
            // Hotel task with currency-specific account
            $liabilityAccountId = $currencySpecificAccount->id;

            if ($task->original_currency && $task->original_currency !== 'KWD') {
                // Original currency task - but use converted amount for accounting balance
                $liabilityAmount = $task->total; // Use converted amount to match expense entry
                $liabilityDescription = 'Records Payable to (Liabilities) in ' . $task->original_currency . ': ' . $supplierCompany->supplier->name;
                $originalCurrency = $task->original_currency;
                $originalAmount = $task->original_price;

                Log::info('Using original currency account for liability entry', [
                    'task_reference' => $task->reference,
                    'original_currency' => $task->original_currency,
                    'original_price' => $task->original_price,
                    'converted_amount' => $task->total,
                    'liability_account_id' => $liabilityAccountId,
                    'liability_amount' => $liabilityAmount,
                    'note' => 'Using converted amount for accounting balance'
                ]);
            } else {
                // KWD currency task with currency-specific account
                $liabilityDescription = 'Records Payable to (Liabilities) in KWD: ' . $supplierCompany->supplier->name;

                Log::info('Using KWD currency-specific account for liability entry', [
                    'task_reference' => $task->reference,
                    'currency' => 'KWD',
                    'liability_account_id' => $liabilityAccountId,
                    'liability_amount' => $liabilityAmount
                ]);
            }
        } elseif ($issuedByAccount && in_array($task->type, ['flight', 'visa'])) {
            // Flight/visa task with issued by account
            $liabilityAccountId = $issuedByAccount->id;

            Log::info('Using issued by account for flight/visa liability entry', [
                'task_reference' => $task->reference,
                'issued_by' => $task->issued_by,
                'liability_account_id' => $liabilityAccountId,
                'liability_amount' => $liabilityAmount
            ]);
        } else {
            // Default to supplier payable account
            $liabilityAccountId = $supplierPayable->id;

            Log::info('Using default supplier payable account for liability entry', [
                'task_reference' => $task->reference,
                'task_type' => $task->type,
                'liability_account_id' => $liabilityAccountId,
                'liability_amount' => $liabilityAmount
            ]);
        }

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $liabilityAccountId,
            'task_id' => $task->id,
            'agent_id' => $task->agent_id,
            'transaction_date' => $transactionDate,
            'description' => $liabilityDescription,
            'name' => $supplierCompany->supplier->name,
            'debit' => 0,
            'credit' => $liabilityAmount,
            'balance' => $liabilityAmount,
            'type' => 'payable',
            'original_currency' => $originalCurrency,
            'original_amount' => $originalAmount,
        ]);
    }

    private function processVoidTask(Task $task, $branchId)
    {
        Log::info('Tasks: ', ['task' => $task->toArray()]);

        $originalTask = Task::find($task->original_task_id);
        if (!$originalTask) {
            throw new Exception('Original task not found for void processing.');
        }

        $payment = Payment::whereHas('partials.invoice.invoiceDetails', function ($query) use ($originalTask) {
            $query->where('task_id', $originalTask->id);
        })
            ->whereHas('partials', function ($query) {
                $query->where('status', 'paid');
            })
            ->first();

        if ($payment && $payment->client_id) {
            Log::info('Invoice is already paid. Processing paid void reversal.');
            $this->voidTask($task, $originalTask, $payment);
        } else {
            Log::info('Invoice for the void task is not paid nor found. Processing unpaid void reversal.');
            $this->ReverseUnpaidVoidedTask($task, $originalTask);
        }
    }

    private function processRefundTask(Task $task, $branchId)
    {
        // Get the same accounts as in processTaskFinancial for consistency
        $liabilities = Account::where('name', 'like', '%Liabilities%')
            ->where('company_id', $task->company_id)
            ->first();

        $expenses = Account::where('name', 'like', '%Expenses%')
            ->where('company_id', $task->company_id)
            ->first();

        if (!$liabilities || !$expenses) {
            throw new Exception('Liabilities or Expenses account not found.');
        }

        $supplier = Supplier::find($task->supplier_id);

        $supplierPayable = Account::where('name', $supplier->name)
            ->where('company_id', $task->company_id)
            ->where('root_id', $liabilities->id)
            ->first();

        $supplierCost = Account::where('name', $supplier->name)
            ->where('company_id', $task->company_id)
            ->where('root_id', $expenses->id)
            ->first();

        if (!$supplierCost || !$supplierPayable) {
            throw new Exception('Supplier accounts not found for refund processing.');
        }

        // For flight tasks, use the same issued_by logic as in processTaskFinancial
        $issuedByAccount = null;
        $payableAccountToUse = $supplierPayable;
        $currencySpecificAccount = null;

        // Handle currency-specific accounts for hotel refund tasks (excluding Jazeera Airways)
        $jazeera = Supplier::where('name', 'Jazeera Airways')->first();

        $isJazeera = $jazeera !== null ? $task->supplier_id == $jazeera->id : false;
        if ($task->type == 'hotel' && !$isJazeera) {
            if ($task->original_currency && $task->original_currency !== 'KWD') {
                // Look for original currency account
                Log::info('Processing hotel refund task with original currency: ' . $task->original_currency . ' for task: ' . $task->reference);
                $currencySpecificAccount = Account::where('name', $supplier->name . ' (' . $task->original_currency . ')')
                    ->where('company_id', $task->company_id)
                    ->where('parent_id', $supplierPayable->id)
                    ->where('currency', $task->original_currency)
                    ->first();
            } else {
                // Look for KWD currency account
                Log::info('Processing hotel refund task with KWD currency for task: ' . $task->reference);
                $currencySpecificAccount = Account::where('name', $supplier->name . ' (KWD)')
                    ->where('company_id', $task->company_id)
                    ->where('parent_id', $supplierPayable->id)
                    ->where('currency', 'KWD')
                    ->first();
            }

            if ($currencySpecificAccount) {
                $payableAccountToUse = $currencySpecificAccount;
                Log::info('Using existing currency-specific account for refund: ' . $currencySpecificAccount->name);
            } else {
                Log::warning('Currency-specific account not found for refund task: ' . $task->reference .
                    ' - falling back to main supplier account');
            }
        }

        if ($task->type == 'flight') {
            Log::info('Processing flight refund task financial for: ' . $task->reference);
            $companyIssuedBy = $task->issued_by ?? 'Not Issued';

            Log::info('Refund - Issued by value determination', [
                'original_issued_by' => $task->issued_by,
                'final_company_issued_by' => $companyIssuedBy,
                'is_null_issued_by' => is_null($task->issued_by)
            ]);

            $issuedByAccount = Account::where('name', $companyIssuedBy)
                ->where('company_id', $task->company_id)
                ->where('root_id', $liabilities->id)
                ->where('parent_id', $supplierPayable->id)
                ->first();

            Log::info('Refund - Issued By Account lookup: ', ['account' => $issuedByAccount]);

            if (!$issuedByAccount) {
                Log::info('Refund - Creating new issued by account for: ' . $companyIssuedBy . ' (was null: ' . (is_null($task->issued_by) ? 'yes' : 'no') . ')');
                $code = 2151;
                $lastIssuedByAccount = Account::where('company_id', $task->company_id)
                    ->where('root_id', $liabilities->id)
                    ->where('parent_id', $supplierPayable->id)
                    ->orderBy('code', 'desc')
                    ->first();

                if ($lastIssuedByAccount) {
                    $code = $lastIssuedByAccount->code + 1;
                }

                try {
                    $issuedByAccount = Account::create([
                        'name' => $companyIssuedBy,
                        'parent_id' => $supplierPayable->id,
                        'company_id' => $task->company_id,
                        'branch_id' => $branchId,
                        'root_id' => $liabilities->id,
                        'code' => $code,
                        'account_type' => 'liability',
                        'report_type' => 'balance sheet',
                        'level' => $supplierPayable->level + 1,
                        'is_group' => 0,
                        'disabled' => 0,
                        'actual_balance' => 0.00,
                        'budget_balance' => 0.00,
                        'variance' => 0.00,
                        'currency' => 'KWD',
                    ]);

                    Log::info('Refund - New issued by account created for task: ' . $task->reference, [
                        'issuedByAccount' => $issuedByAccount,
                        'account_id' => $issuedByAccount ? $issuedByAccount->id : 'null',
                        'account_name' => $issuedByAccount ? $issuedByAccount->name : 'null'
                    ]);
                } catch (Exception $e) {
                    Log::error('Refund - Failed to create issued by account: ' . $e->getMessage(), [
                        'task_reference' => $task->reference,
                        'company_issued_by' => $companyIssuedBy,
                        'supplier_payable_id' => $supplierPayable->id,
                        'exception' => $e->getMessage()
                    ]);
                    throw new Exception('Failed to create issued by account for refund: ' . $e->getMessage());
                }
            }

            // Use the issued by account for flight tasks
            if ($issuedByAccount) {
                $payableAccountToUse = $issuedByAccount;
            } else {
                Log::error('Refund - Flight task missing issued by account', [
                    'task_reference' => $task->reference,
                    'issued_by' => $task->issued_by,
                    'company_issued_by' => $companyIssuedBy
                ]);
                throw new Exception('Failed to create or find issued by account for flight refund task.');
            }
        }

        Log::info('Processing refund task with correct accounts', [
            'task_reference' => $task->reference,
            'supplier_name' => $supplier->name,
            'payable_account' => $payableAccountToUse->name,
            'payable_account_id' => $payableAccountToUse->id,
            'expense_account' => $supplierCost->name,
            'expense_account_id' => $supplierCost->id,
            'is_flight_task' => $task->type == 'flight',
            'issued_by' => $task->issued_by
        ]);

        // Use task's issued_date as transaction_date
        $transactionDate = $task->supplier_pay_date ? Carbon::parse($task->supplier_pay_date) : Carbon::now();

        // Create Transaction Record
        $transaction = Transaction::create([
            'entity_id' => $task->company_id,
            'entity_type' => 'company',
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'transaction_type' => 'debit',
            'amount' => $task->total,
            'description' => 'Refund Task: ' . $task->reference,
            'reference_type' => 'Refund',
            'name' => $task->client_name,
            'transaction_date' => $transactionDate,
        ]);

        if (!$transaction) {
            throw new Exception('Refund Transaction creation failed.');
        }

        // Create journal entries using the correct accounts
        $refundAmount = $task->total; // Always use converted KWD amount for accounting balance
        $originalCurrency = null;
        $originalAmount = null;

        // If this is a hotel task with currency-specific account, store original currency info
        if ($task->type == 'hotel' && $currencySpecificAccount) {
            if ($task->original_currency && $task->original_currency !== 'KWD') {
                // Original currency refund - but use converted amount for accounting balance
                $originalCurrency = $task->original_currency;
                $originalAmount = $task->original_price;

                Log::info('Using original currency info for refund with converted amount', [
                    'task_reference' => $task->reference,
                    'original_currency' => $originalCurrency,
                    'original_amount' => $originalAmount,
                    'converted_amount' => $task->total,
                    'note' => 'Using converted amount for accounting balance'
                ]);
            } else {
                // KWD currency refund with currency-specific account
                Log::info('Using KWD currency-specific account for refund', [
                    'task_reference' => $task->reference,
                    'currency' => 'KWD',
                    'amount' => $task->total
                ]);
            }
        }

        JournalEntry::create([
            'transaction_date' => $transactionDate,
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $payableAccountToUse->id,
            'task_id' => $task->id,
            'agent_id' => $task->agent_id,
            'description' => 'Refund Task - Supplier refunds us (Liabilities): ' . $payableAccountToUse->name,
            'debit' => $refundAmount, // Now always uses converted amount
            'credit' => 0,
            'name' => $supplier->name,
            'type' => 'refund',
            'original_currency' => $originalCurrency,
            'original_amount' => $originalAmount,
        ]);

        JournalEntry::create([
            'transaction_date' => $transactionDate,
            'transaction_id' => $transaction->id,
            'company_id' => $task->company_id,
            'branch_id' => $branchId,
            'account_id' => $supplierCost->id,
            'task_id' => $task->id,
            'agent_id' => $task->agent_id,
            'description' => 'Refund Task - Supplier cost return (Expenses): ' . $supplierCost->name,
            'debit' => 0,
            'credit' => $task->total, // Always use converted amount for expense account
            'name' => $supplier->name,
            'type' => 'refund',
        ]);
    }

    private function revertFinancialsForTask(Task $task): void
    {
        Log::info('Reverting financials for task: ' . $task->reference);

        $journalEntries = JournalEntry::where('task_id', $task->id)
            ->whereHas('transaction', function ($q) use ($task) {
                $q->where('description', 'like', '%' . $task->reference . '%');
            })
            ->get();

        if ($journalEntries->isNotEmpty()) {
            $transactionIds = $journalEntries->pluck('transaction_id')->filter()->unique();
            JournalEntry::whereIn('id', $journalEntries->pluck('id'))->delete();

            if ($transactionIds->isNotEmpty()) {
                Transaction::whereIn('id', $transactionIds)
                    ->where('description', 'like', '%' . $task->reference . '%')
                    ->delete();
            }
            Log::info("Reverted {$journalEntries->count()} journal entries and {$transactionIds->count()} transactions for task: {$task->reference}");
        }
    }

    private function revertFinancialsForVoid(Task $voidTask): void
    {
        if (!$voidTask->original_task_id) {
            Log::warning('revertFinancialsForVoid called without original_task_id', [
                'void_task_id' => $voidTask->id,
                'reference'    => $voidTask->reference,
            ]);
            return;
        }

        $originalTask  = Task::find($voidTask->original_task_id);

        Log::info('Reverting ONLY void financials applied to original task', [
            'original_task_id' => $originalTask->id,
            'void_task_id' => $voidTask->id,
            'void_reference' => $voidTask->reference,
        ]);

        // Delete journal entries on the original that belong to void transactions
        $journalEntries = JournalEntry::where('task_id', $originalTask->id)
            ->whereHas('transaction', function ($q) use ($originalTask) {
                $q->where('description', 'like', '%void%' . $originalTask->reference . '%');
            })
            ->get();

        if ($journalEntries->isNotEmpty()) {
            $transactionIds = $journalEntries->pluck('transaction_id')->filter()->unique();
            JournalEntry::whereIn('id', $journalEntries->pluck('id'))->delete();

            if ($transactionIds->isNotEmpty()) {
                Transaction::whereIn('id', $transactionIds)
                    ->where('description', 'like', '%void%' . $originalTask->reference . '%')
                    ->delete();
            }

            Log::info("Reverted {$journalEntries->count()} void journal entries and {$transactionIds->count()} transactions for original task: {$originalTask->reference}");
        } else {
            Log::info("No void financials found to revert for task: {$originalTask->reference}");
        }
    }

    public function toggleStatus(Request $request, Task $task)
    {
        $task->enabled = $request->is_enabled;

        if ($task->enabled) {
            if ($task->status !== 'issued' && $task->status !== 'confirmed' && !$task->original_task_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task must be linked to an original task before enabling.'
                ], 400);
            }

            if (!$task->is_complete) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task is not complete. Missing required fields: ' . $this->getMissingFields($task)
                ], 400);
            }

            if (!$task->agent_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task must have an agent assigned to be enabled.'
                ], 400);
            }

            if ($task->client == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task must have a client assigned to be enabled.'
                ], 400);
            }

            if ($task->supplier_pay_date == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task must have an issued date before it can be enabled.'
                ], 400);
            }

            if ($task->status === 'void') {
                $journalEntries = JournalEntry::where('task_id', $task->original_task_id)
                    ->whereHas('transaction', function ($q) {
                        $q->whereRaw('LOWER(description) LIKE ?', ['%void%']);
                    })
                    ->exists();
            } else {
                $journalEntries = JournalEntry::where('task_id', $task->id)->exists();
            }

            if (!$journalEntries) {
                try {
                    $this->processTaskFinancial($task);
                } catch (Exception $e) {
                    Log::error('Failed to process task financial: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to enable task: ' . $e->getMessage()
                    ], 500);
                }
            }
        }

        $task->save();

        return response()->json(['success' => true]);
    }

    public function voidTask(Task $voidTask, Task $issuedTask, Payment $payment)
    {
        $client = Client::find($payment->client_id);

        if (!$client) {
            throw new \Exception("Client not found for payment ID: {$payment->id}");
            Log::warning("Client not found for payment [{$payment->id}] during void refund.");
        }

        $oldCredit = Credit::getTotalCreditsByClient($client->id);

        DB::beginTransaction();
        try {
            $voidCreditData = [
                'company_id'  => $client->agent->branch->company->id,
                'client_id'   => $client->id,
                'type'        => 'Void',
                'description' => 'Void for task:' . $voidTask->reference,
                'amount'      => $payment->amount,
            ];

            Log::info('Creating Credit record:', $voidCreditData);
            Credit::create($voidCreditData);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create Credit record', [
                'data'  => $voidCreditData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        DB::commit();

        $afterCredit = Credit::getTotalCreditsByClient($client->id);
        Log::info("Void for task {$voidTask->reference}: Client credit before = {$oldCredit}, after = {$afterCredit}");

        // Use task's issued_date as transaction_date
        $transactionDate = $voidTask->supplier_pay_date ? Carbon::parse($voidTask->supplier_pay_date) : Carbon::now();

        $voidTransaction = Transaction::create([
            'branch_id'        => $client->agent->branch_id,
            'company_id'       => $client->agent->branch->company_id,
            'entity_id'        => $client->id,
            'entity_type'      => 'client',
            'transaction_type' => 'debit',
            'amount'           => $payment->amount,
            'description'      => 'Void task: ' . $issuedTask->reference,
            'reference_type'   => 'Refund',
            'reference_number' => $payment->voucher_number,
            'transaction_date' => $transactionDate,
        ]);

        if (!$voidTransaction) {
            throw new \Exception("Failed to create refund transaction.");
        }

        $entries = JournalEntry::whereHas('invoiceDetail', function ($query) use ($issuedTask) {
            $query->where('task_description', $issuedTask->reference);
        })->get();

        foreach ($entries as $entry) {
            JournalEntry::create([
                'transaction_id'   => $voidTransaction->id,
                'company_id'       => $entry->company_id,
                'branch_id'        => $entry->branch_id,
                'account_id'       => $entry->account_id,
                'task_id'          => $issuedTask->id,
                'transaction_date' => $transactionDate,
                'description'      => 'Void: ' . $entry->description,
                'debit'            => $entry->credit,
                'credit'           => $entry->debit,
                'balance'          => ($entry->balance ?? 0) * -1,
                'type'             => $entry->type,
                'name'             => $entry->name,
                'voucher_number'   => $entry->voucher_number,
            ]);
        }

        Log::info('Voided task refunded and reversed', [
            'void_task'     => $voidTask->reference,
            'original_task' => $issuedTask->reference,
        ]);

        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Paid void task reversal journal completed.',
            'data' => $issuedTask,
        ], 201);
    }

    public function show($id)
    {
        $task = Task::with([
            'agent.branch',
            'client',
            'flightDetails.countryFrom',
            'flightDetails.countryTo',
            'hotelDetails.hotel',
            'insuranceDetails',
            'visaDetails',
            'supplier'
        ])->withoutGlobalScope('enabled')->findOrFail($id);

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        if ($task->flightDetails) {
            $task['country_from'] = $task->flightDetails->countryFrom?->name;
            $task['country_to'] = $task->flightDetails->countryTo?->name;
            $task['description'] = $task['country_from'] . ' ---> ' . $task['country_to'];
        } elseif ($task->hotelDetails) {
            $task['hotel_name'] = $task->hotelDetails->hotel?->name;
            $task['hotel_country'] = $task->hotelDetails->hotel?->country;
            $task['description'] = $task['hotel_name'] . '/' . $task['hotel_country'];
        } else {
            $task['description'] = 'No description';
        }

        // Convert relationship names to snake_case for frontend consistency
        // and wrap single items in arrays for frontend iteration
        $taskArray = $task->toArray();

        // Convert single objects to arrays for frontend
        if (isset($taskArray['flight_details']) && $taskArray['flight_details']) {
            $taskArray['flight_details'] = [$taskArray['flight_details']];
        } else {
            $taskArray['flight_details'] = [];
        }

        if (isset($taskArray['hotel_details']) && $taskArray['hotel_details']) {
            $taskArray['hotel_details'] = [$taskArray['hotel_details']];
        } else {
            $taskArray['hotel_details'] = [];
        }

        if (isset($taskArray['visa_details']) && $taskArray['visa_details']) {
            $taskArray['visa_details'] = [$taskArray['visa_details']];
        } else {
            $taskArray['visa_details'] = [];
        }

        if (isset($taskArray['insurance_details']) && $taskArray['insurance_details']) {
            $taskArray['insurance_details'] = [$taskArray['insurance_details']];
        } else {
            $taskArray['insurance_details'] = [];
        }

        // Return the task data as JSON for the modal to load dynamically
        return response()->json($taskArray, 200);
    }

    public function edit($id)
    {
        // Include both 'agent' and 'client' in the query
        $task = Task::with(['agent', 'client'])->findOrFail($id);
        return view('tasks.update', compact('task'));
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $rules = [
            'reference' => 'nullable|string',
            'status' => 'required',
            'price' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'surcharge' => 'nullable|numeric',
            'total' => 'required|numeric',
            'payment_method_account_id' => 'nullable|string',
            'agent_id' => 'nullable',
            'client_id' => 'nullable|exists:clients,id',
            'supplier_id' => 'required',
            'original_task_id' => 'nullable|exists:tasks,id',
            'supplier_pay_date' => $task->supplier_pay_date ? 'sometimes|date' : 'required|date', // we show 'issued date' in the form label
        ];
        $messages = [
            'supplier_id.required' => 'Please select a supplier',
            'status.required' => 'Please select a status',
            'total.required' => 'Please enter the total amount',
            'supplier_pay_date.required' => 'Issued date is required',
        ];
        $request->validate($rules, $messages);

        DB::beginTransaction();

        try {
            $oldPaymentMethod = $task->payment_method_account_id;
            $oldStatus = $task->status;
            $oldSupplierPayDate = $task->supplier_pay_date ? Carbon::parse($task->supplier_pay_date) : null;

            Log::info('Before task detail update: agent_id: ' . $task->agent_id . ', client_id: ' . $task->client_id . ', status: ' . $task->status);
            Log::info('Incoming Request: agent_id: ' . $request->agent_id . ', client_id: ' . $request->client_id);

            $prevClientName = $task->client_name;
            $prevAgentId = $task->agent_id;
            $processedThisRequest = false;

            $data = $request->only([
                'reference',
                'status',
                'price',
                'tax',
                'surcharge',
                'total',
                'agent_id',
                'supplier_id',
                'original_task_id',
                'payment_method_account_id',
            ]);

            if ($request->filled('client_id')) {
                $client = Client::findOrFail($request->client_id);
                $data['client_id'] = $client->id;
                $data['client_name'] = $client->full_name;
            }

            if ($request->filled('agent_id')) {
                $agent = Agent::findOrFail($request->agent_id);
                $data['agent_id'] = $agent->id;
                $data['agent_name'] = $agent->name;
            }
            if ($request->has('supplier_pay_date')) {
                $data['supplier_pay_date'] = $request->input('supplier_pay_date');
            }

            $task->update($data);
            Log::info('After task detail update: agent_id: ' . $task->agent_id . ', client_id: ' . $task->client_id . ', status: ' . $task->status);

            $matchedRule = AutoBilling::where('company_id', $task->company_id)
                ->where('is_active', true)
                ->where(function ($q) use ($task) {
                    $q->where('created_by', $task->created_by)
                        ->orWhere('issued_by', $task->issued_by)
                        ->orWhere('agent_id', $task->agent_id);
                })
                ->get()
                ->first(
                    fn($r) => (!$r->created_by || $r->created_by === $task->created_by) &&
                        (!$r->issued_by || $r->issued_by === $task->issued_by) &&
                        (!$r->agent_id || $r->agent_id === $task->agent_id)
                );

            if ($matchedRule) {
                $task->update([
                    'client_id' => $matchedRule->client_id,
                    'client_name' => optional($matchedRule->client)->full_name,
                ]);
                Log::info("[Task Update] Auto-linked task {$task->id} with AutoBilling rule #{$matchedRule->id} (Client ID {$matchedRule->client_id})");
            } else {
                Log::info("[Task Update] No AutoBilling rule matched for task {$task->id} (created_by: {$task->created_by}, issued_by: {$task->issued_by}, agent_id: {$task->agent_id})");
            }

            $newSupplierPayDate = $task->supplier_pay_date ? Carbon::parse($task->supplier_pay_date) : null;
            if ($oldSupplierPayDate !== $newSupplierPayDate) {
                $journalEntries = JournalEntry::with('transaction')
                    ->where('task_id', $task->id)
                    ->whereHas('transaction', function ($q) use ($task) {
                        $q->where('description', 'like', '%' . $task->reference . '%');
                    })
                    ->get();

                foreach ($journalEntries as $je) {
                    $je->transaction_date = $newSupplierPayDate;
                    $je->save();

                    if ($je->transaction) {
                        $je->transaction->transaction_date = $newSupplierPayDate;
                        $je->transaction->save();
                    }
                }
            }

            if ($request->filled('payment_method_account_id') && $request->payment_method_account_id != $oldPaymentMethod) {
                $response = $this->updateJournalPaymentMethod($task, $request->payment_method_account_id);

                if (!$response instanceof JsonResponse) {
                    Log::error('Response from updateJournalPaymentMethod is not a JsonResponse', [
                        'task_id' => $task->id,
                        'expected_type' => JsonResponse::class,
                        'actual_type' => is_object($response) ? get_class($response) : gettype($response)
                    ]);

                    throw new Exception('Failed to update payment method journal entries.');
                }

                if ($response->getData(true)['status'] !== 'success') {
                    Log::error('Failed to update payment method journal entries', [
                        'task_id' => $task->id,
                        'error_message' => $response->getData()->message
                    ]);
                    throw new Exception('Failed to update payment method journal entries: ' . $response->getData()->message);
                }
            }

            if ($oldStatus !== $task->status) {
                if ($task->status === 'confirmed') {
                    Log::info('Confirmed status: reverting and skipping COA creation: ' . $task->reference);
                    $this->revertFinancialsForTask($task);
                    $processedThisRequest = true;
                } else {
                    if (in_array($task->status, ['issued', 'reissued', 'emd', 'void', 'refund'], true)) {
                        if ($oldStatus === 'void') {
                            $this->revertFinancialsForVoid($task);
                        } else {
                            $this->revertFinancialsForTask($task);
                        }
                        $this->processTaskFinancial($task);
                        $processedThisRequest = true;
                    }
                }
            }

            $clientChanged = $task->wasChanged('client_id');
            $agentWasAssigned = !$prevAgentId && $task->agent_id;
            $agentWasChanged = $prevAgentId && $task->agent_id && $prevAgentId !== $task->agent_id;

            if ($task->status === 'void') {
                $wasEnabled = JournalEntry::where('task_id', $task->original_task_id)
                    ->whereHas('transaction', function ($q) {
                        $q->whereRaw('LOWER(description) LIKE ?', ['%void%']);
                    })
                    ->exists();
            } else {
                $wasEnabled = JournalEntry::where('task_id', $task->id)->exists();
            }

            // Update enabled status: task must be complete AND have an agent assigned
            $shouldBeEnabled = $task->is_complete && $task->agent_id && $task->client;
            if ($shouldBeEnabled) {
                if ($task->status !== 'issued' && $task->status !== 'confirmed' && !$task->original_task_id) {
                    DB::rollBack();
                    return back()->withErrors(['original_task_id' => 'Task must be linked to an original task'])->withInput();
                }
            }

            if (!$processedThisRequest && $shouldBeEnabled && !$wasEnabled) {
                $task->enabled = true;
                $task->save();
                // Process financials if not already processed

                if ($task->status === 'void') {
                    $hasJournal = JournalEntry::where('task_id', $task->original_task_id)
                        ->whereHas('transaction', function ($q) {
                            $q->whereRaw('LOWER(description) LIKE ?', ['%void%']);
                        })
                        ->exists();
                } else {
                    $hasJournal = JournalEntry::where('task_id', $task->id)->exists();
                }
                if (!$hasJournal) {
                    Log::info('Processing financial transactions for newly enabled task: ' . $task->reference);
                    $this->processTaskFinancial($task);
                }
            } elseif (!$shouldBeEnabled && $wasEnabled) {
                $task->enabled = false;
                $task->save();
            } else {
                $task->enabled = $shouldBeEnabled;
                $task->save();
            }

            // If agent was assigned or changed, update branch_id in existing journal entries
            if (($agentWasAssigned || $agentWasChanged) && $task->agent_id) {
                Log::info('Agent assignment/change detected for task: ' . $task->reference .
                    ' (prev: ' . ($prevAgentId ?? 'none') . ', new: ' . $task->agent_id . ')');
                $this->updateJournalEntriesBranch($task);
            }

            $journalEntries = JournalEntry::with('transaction')
                ->where('task_id', $id)
                ->whereHas('transaction', function ($q) use ($task) {
                    $q->where('description', 'like', '%' . $task->reference . '%');
                })
                ->get();

            $transaction = null;

            if ($journalEntries->isNotEmpty()) {
                foreach ($journalEntries as $entry) {
                    if ($entry->transaction) {
                        $transaction = $entry->transaction;
                        $transaction->amount = $task->total;
                        $transaction->save();
                    }

                    if ($entry->debit > 0) {
                        $entry->debit = $task->total;
                        $entry->balance = $task->total;
                    } else {
                        $entry->credit = $task->total;
                        $entry->balance = $task->total;
                    }
                    if (isset($entry->amount)) {
                        $entry->amount = $task->total;
                    }
                    $entry->save();
                }
            }

            if (isset($client) && $transaction) {
                $transaction->journalEntries->each(function ($journalEntry) use ($client, $prevClientName) {
                    if ($journalEntry->name === $prevClientName) {
                        $journalEntry->name = $client->full_name;
                        $journalEntry->save();
                    }
                });
            }

            if (strtolower($task->status) === 'issued' && ($agentWasChanged || $clientChanged)) {
                $children = Task::where('original_task_id', $task->id)->get();

                foreach ($children as $child) {
                    if ($agentWasChanged)  $child->agent_id  = $task->agent_id;
                    if ($clientChanged) $child->client_id = $task->client_id;
                    $child->save();

                    if ($agentWasChanged) {
                        $this->updateJournalEntriesBranch($child);
                    }
                }
            }

            DB::commit();
            return redirect()->back()->with('success', 'Task updated successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Task update failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Task update failed: ' . $e->getMessage());
        }
    }

    public function updateAdminFinancial(Request $request, Task $task)
    {
        $request->validate([
            'price' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'surcharge' => 'nullable|numeric',
            'total' => 'required|numeric',
            'remarks' => 'required|string|min:10',
        ]);

        DB::beginTransaction();
        try {
            $oldTotal = $task->total;
            $newTotal = $request->total;

            $task->price = $request->price;
            $task->tax = $request->tax;
            $task->surcharge = $request->surcharge;
            $task->total = $newTotal;
            $task->save();

            $journalEntries = JournalEntry::with('transaction')
                ->where('task_id', $task->id)
                ->whereHas('transaction', function ($q) use ($task) {
                    $q->where('description', 'like', '%' . $task->reference . '%');
                })->get();

            foreach ($journalEntries as $entry) {
                $beforeTxAmt = $entry->transaction ? $entry->transaction->amount : null;
                $beforeDebit = $entry->debit ?? 0;
                $beforeCredit = $entry->credit ?? 0;
                if ($entry->transaction) {
                    $entry->transaction->amount = $newTotal;
                    $entry->transaction->save();
                }

                if ($beforeTxAmt === null || abs($beforeTxAmt - $newTotal) >= 0.0005) {
                    SystemLog::create([
                        'user_id' => Auth::user()->id,
                        'model' => 'task',
                        'current_value' => $beforeTxAmt ?? 'null',
                        'new_value' => $newTotal,
                        'remarks' => "Transaction #{$entry->transaction->id} amount changed | " . $request->remarks,
                    ]);
                }

                if (($entry->debit ?? 0) > 0) {
                    $entry->debit = $newTotal;
                    $entry->credit = 0;
                    $entry->balance = $newTotal;
                } else {
                    $entry->credit = $newTotal;
                    $entry->debit = 0;
                    $entry->balance = $newTotal;
                }

                if (isset($entry->amount)) {
                    $entry->amount = $newTotal;
                }
                $entry->save();

                if (abs($beforeDebit - $entry->debit) >= 0.0005) {
                    SystemLog::create([
                        'user_id' => Auth::user()->id,
                        'model' => 'task',
                        'current_value' => $beforeDebit,
                        'new_value' => $entry->debit,
                        'remarks' => "JE #{$entry->id} debit updated | " . $request->remarks,
                    ]);
                }

                if (abs($beforeCredit - $entry->credit) >= 0.0005) {
                    SystemLog::create([
                        'user_id' => Auth::user()->id,
                        'model' => 'task',
                        'current_value' => $beforeCredit,
                        'new_value' => $entry->credit,
                        'remarks' => "JE #{$entry->id} credit updated | " . $request->remarks,
                    ]);
                }
            }

            $invoiceDetail = $task->invoiceDetail;
            if ($invoiceDetail) {
                $selling = $invoiceDetail->task_price ?? 0;
                $beforeSupplier = $invoiceDetail->supplier_price ?? 0;
                $beforeMarkup = $invoiceDetail->markup_price ?? 0;
                $invoiceDetail->supplier_price = $newTotal;
                $invoiceDetail->markup_price = $selling - $newTotal;
                $invoiceDetail->save();

                if (abs($beforeSupplier - $invoiceDetail->supplier_price) >= 0.0005) {
                    SystemLog::create([
                        'user_id' => Auth::user()->id,
                        'model' => 'task',
                        'current_value' => $beforeSupplier,
                        'new_value' => $invoiceDetail->supplier_price,
                        'remarks' => "InvoiceDetail #{$invoiceDetail->id} supplier_price updated | " . $request->remarks,
                    ]);
                }

                if (abs($beforeMarkup - $invoiceDetail->markup_price) >= 0.0005) {
                    SystemLog::create([
                        'user_id' => Auth::user()->id,
                        'model' => 'task',
                        'current_value' => $beforeMarkup,
                        'new_value' => $invoiceDetail->markup_price,
                        'remarks' => "InvoiceDetail #{$invoiceDetail->id} markup_price updated | " . $request->remarks,
                    ]);
                }
            }

            $isPaid = InvoiceDetail::where('task_id', $task->id)
                ->whereHas('invoice', fn($q) => $q->where('status', 'paid'))
                ->exists();

            if ($isPaid) {
                $this->recalculateCommissionForTask($task, $newTotal);
            }

            SystemLog::create([
                'user_id' => Auth::user()->id,
                'model' => 'task',
                'current_value' => $oldTotal,
                'new_value' => $newTotal,
                'remarks' => $request->remarks,
            ]);

            DB::commit();
            return back()->with('success', 'Task financials and related records were updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Admin amount adjust failed', ['task_id' => $task->id, 'err' => $e->getMessage()]);
            return back()->with('error', 'Adjustment failed: ' . $e->getMessage());
        }
    }

    protected function recalculateCommissionForTask(Task $task, float $newSupplierAmount): void
    {
        $agent = $task->agent;
        if (!$agent) return;
        if (!in_array((int) $agent->type_id, [2, 3], true)) return;

        $invoiceDetail = $task->invoiceDetail;
        if (!$invoiceDetail) return;

        $selling = $invoiceDetail->task_price ?? 0;
        $supplier = $newSupplierAmount ?? 0;
        $rate = $agent->commission ?? 0.15;
        $markup = $selling - $supplier;
        $commission = $rate * $markup;

        $commissionLiabilityAcc = Account::where('name', 'Commissions (Agents)')
            ->where('company_id', $task->company_id)
            ->first();

        $commissionExpenseAcc = Account::where('name', 'like', '%Commissions Expense (Agents)%')
            ->where('company_id', $task->company_id)
            ->first();

        if (!$commissionLiabilityAcc || !$commissionExpenseAcc) return;

        $entriesLiability = JournalEntry::with('transaction')
            ->where('invoice_detail_id', $invoiceDetail->id)
            ->where('account_id', $commissionLiabilityAcc->id)
            ->get();

        $entriesExpense = JournalEntry::with('transaction')
            ->where('invoice_detail_id', $invoiceDetail->id)
            ->where('account_id', $commissionExpenseAcc->id)
            ->get();

        if ($entriesLiability->isEmpty() && $entriesExpense->isEmpty()) return;

        DB::transaction(function () use ($entriesLiability, $entriesExpense, $commission) {
            // Update agents (liability) entries: set CREDIT
            foreach ($entriesLiability as $je) {
                $beforeDebit  = $je->debit ?? 0;
                $beforeCredit = $je->credit ?? 0;
                $beforeBalance = $je->balance ?? 0;
                $beforeTxAmt = $je->transaction ? $je->transaction->amount : null;

                $je->debit = 0;
                $je->credit = $commission;
                $je->balance = $commission;
                if (isset($je->amount)) $je->amount = $commission;
                $je->save();

                if ($je->transaction) {
                    $je->transaction->amount = $commission;
                    $je->transaction->save();

                    if (abs($beforeTxAmt - $je->transaction->amount) >= 0.0005) {
                        SystemLog::create([
                            'user_id' => Auth::user()->id,
                            'model' => 'task',
                            'current_value' => $beforeTxAmt ?? 'null',
                            'new_value' => $je->transaction->amount,
                            'remarks' => "Commission liability TX #{$je->transaction->id} amount updated",
                        ]);
                    }
                }

                if (abs($beforeCredit - $je->credit) >= 0.0005) {
                    SystemLog::create([
                        'user_id' => Auth::id(),
                        'model' => 'task',
                        'current_value' => $beforeCredit,
                        'new_value' => $je->credit,
                        'remarks' => "Commission liability JE #{$je->id} credit updated",
                    ]);
                }
                if (abs($beforeDebit - $je->debit) >= 0.0005) {
                    SystemLog::create([
                        'user_id' => Auth::user()->id,
                        'model' => 'task',
                        'current_value' => $beforeDebit,
                        'new_value' => $je->debit,
                        'remarks' => "Commission liability JE #{$je->id} debit updated",
                    ]);
                }
                if (abs($beforeBalance - $je->balance) >= 0.0005) {
                    SystemLog::create([
                        'user_id' => Auth::user()->id,
                        'model' => 'task',
                        'current_value' => $beforeBalance,
                        'new_value' => $je->balance,
                        'remarks' => "Commission liability JE #{$je->id} balance updated",
                    ]);
                }
            }

            // Update expense entries: set DEBIT
            foreach ($entriesExpense as $je) {
                $beforeDebit = $je->debit ?? 0;
                $beforeCredit = $je->credit ?? 0;
                $beforeBalance = $je->balance ?? 0;
                $beforeTxAmt = $je->transaction ? $je->transaction->amount : null;

                $je->credit = 0;
                $je->debit = $commission;
                $je->balance = $commission;
                if (isset($je->amount)) $je->amount = $commission;
                $je->save();

                if ($je->transaction) {
                    $je->transaction->amount = $commission;
                    $je->transaction->save();

                    if (abs($beforeTxAmt - (float)$je->transaction->amount) >= 0.0005) {
                        SystemLog::create([
                            'user_id' => Auth::user()->id,
                            'model' => 'task',
                            'current_value' => $beforeTxAmt ?? 'null',
                            'new_value' => $je->transaction->amount,
                            'remarks' => "Commission expense TX #{$je->transaction->id} amount updated",
                        ]);
                    }
                }

                if (abs($beforeDebit - $je->debit) >= 0.0005) {
                    SystemLog::create([
                        'user_id' => Auth::user()->id,
                        'model' => 'task',
                        'current_value' => $beforeDebit,
                        'new_value' => $je->debit,
                        'remarks' => "Commission expense JE #{$je->id} debit updated",
                    ]);
                }
                if (abs($beforeCredit - $je->credit) >= 0.0005) {
                    SystemLog::create([
                        'user_id' => Auth::user()->id,
                        'model' => 'task',
                        'current_value' => $beforeCredit,
                        'new_value' => $je->credit,
                        'remarks' => "Commission expense JE #{$je->id} credit updated",
                    ]);
                }
                if (abs($beforeBalance - $je->balance) >= 0.0005) {
                    SystemLog::create([
                        'user_id' => Auth::user()->id,
                        'model' => 'task',
                        'current_value' => $beforeBalance,
                        'new_value' => $je->balance,
                        'remarks' => "Commission expense JE #{$je->id} balance updated",
                    ]);
                }
            }
        });
    }

    public function upload(Request $request)
    {
        $user = Auth::user();

        if ($user->role_id == Role::COMPANY) {
            $company = $user->company;
        } elseif ($user->role_id == Role::BRANCH) {
            $company = $user->branch->company;
        } elseif ($user->role_id == Role::AGENT) {
            $company = $user->agent->branch->company;
        } else {
            return redirect()->back()->with('error', 'User not authorized to upload tasks.');
        }

        if (!$company) {
            Log::error("Company not found for user ID: {$user->id}");
            return redirect()->back()->with('error', 'Something went wrong.');
        }

        $request->validate([
            'agent_id' => 'nullable|exists:agents,id',
            'supplier_id' => 'required|exists:suppliers,id',
        ]);

        $supplier = Supplier::find($request->supplier_id);
        $isMergeSupplier = $supplier->isMergeSupplier();

        $request->validate([
            'task_file'     => [Rule::requiredIf(!$isMergeSupplier), 'array'],
            'task_file.*'   => ['mimes:pdf,txt'],
            'batches'       => [Rule::requiredIf($isMergeSupplier), 'array', 'min:1'],
            'batches.*'     => ['array'],
            'batches.*.*'   => ['file', 'mimes:pdf'],
            'batch_names'   => ['nullable', 'array'],
            'batch_names.*' => [
                'nullable',
                'string',
                'max:120',
                function ($attribute, $value, $fail) use ($supplier, $company) {
                    if (!is_string($value) || trim($value) === '') return;
                    $candidate = $this->sanitizePdfName($value);
                    if (!$candidate) return;

                    $exists = FileUpload::where([
                        'supplier_id' => $supplier->id,
                        'company_id'  => $company->id,
                        'file_name'   => $candidate,
                    ])->exists();

                    if ($exists) {
                        $batchNo = 1;
                        if (preg_match('/\.(\d+)$/', $attribute, $m)) $batchNo = ((int)$m[1]) + 1;
                        $fail("Merged file name for Batch {$batchNo} is already used for this supplier. Choose a different name.");
                    }
                },
            ],
        ]);

        $files = $request->file('task_file');
        $companyName = strtolower(preg_replace('/\s+/', '_', $company->name));
        $supplierName = strtolower(preg_replace('/\s+/', '_', $supplier->name));

        $filePath = storage_path("app/{$companyName}/{$supplierName}/files_unprocessed");

        if (!File::isDirectory($filePath)) {
            Log::error("Source directory {$filePath} not found.");
            File::makeDirectory($filePath, 0755, true, true);
            Log::info("Created source directory: {$filePath}, please ensure files are pushed here.");
        }

        if ($isMergeSupplier) {
            try {
                $allMessages = [];
                $allData = [];
                $hasError = false;
                $batchIndex = 0;

                foreach ($request->file('batches') as $batchFiles) {
                    $batchIndex++;
                    $successFiles = [];
                    $failedFiles  = [];
                    $reasons = [];

                    $names = array_map(fn($f) => $f->getClientOriginalName(), $batchFiles);

                    $matches = FileUpload::with('user')
                        ->where('supplier_id', $supplier->id)
                        ->where('company_id', $company->id)
                        ->where(function ($q) use ($names) {
                            foreach ($names as $n) {
                                $q->orWhere('file_name', $n)
                                    ->orWhereJsonContains('source_files', $n);
                            }
                        })
                        ->get(['file_name', 'source_files', 'user_id']);

                    foreach ($matches as $match) {
                        $matchUser = $match->user;
                        $message = ($matchUser && $matchUser->id === $user->id)
                            ? 'File has already been uploaded by you'
                            : (($matchUser && $matchUser->company !== null)
                                ? 'File has been uploaded by your admin. Please contact them to resolve this issue.'
                                : ($matchUser
                                    ? "File has been uploaded by another user : {$matchUser->name}. Please contact them to resolve this issue."
                                    : 'File has already been uploaded.'));

                        if (!empty($match->file_name) && in_array($match->file_name, $names, true)) {
                            $reasons[$match->file_name] = $message;
                        }
                        $arr = is_array($match->source_files) ? $match->source_files : (json_decode($match->source_files, true) ?: []);
                        foreach ($arr as $n) {
                            if (in_array($n, $names, true)) $reasons[$n] = $message;
                        }
                    }

                    $duplicates = array_values(array_intersect($names, array_keys($reasons)));
                    if ($duplicates) {
                        $hasError = true;
                        $allMessages[] = "Batch {$batchIndex} failed.";
                        foreach ($duplicates as $n) {
                            $allData[] = ['file_name' => $n, 'message' => $reasons[$n]];
                        }
                        continue;
                    }

                    $merger = new Merger(new Fpdi2Driver());
                    foreach ($batchFiles as $file) {
                        try {
                            $merger->addFile($file->getRealPath());
                            $successFiles[] = $file->getClientOriginalName();
                        } catch (\Throwable $e) {
                            $failedFiles[] = $file->getClientOriginalName();
                        }
                    }

                    if ($failedFiles) {
                        $hasError = true;
                        $allMessages[] = "Batch {$batchIndex} failed. Failed files: " . implode(', ', $failedFiles);
                        foreach ($failedFiles as $f) $allData[] = ['file_name' => $f];
                        continue;
                    }

                    $mergedBytes = null;
                    $mergedName  = null;

                    if (count($batchFiles) === 1) {
                        $only = $batchFiles[0];
                        $mergedBytes = file_get_contents($only->getRealPath());
                        $mergedName  = $only->getClientOriginalName();
                        if (!preg_match('/\.pdf$/i', $mergedName)) {
                            $mergedName .= '.pdf';
                        }
                    } else {
                        $mergedBytes = $merger->merge();

                        $customBase  = $request->input("batch_names." . ($batchIndex - 1));
                        $customName  = $this->sanitizePdfName($customBase);

                        if ($customName) {
                            $mergedName = $customName;
                        } else {
                            $mergePrefixMap = [
                                'TBO Air' => 'TBOAir',
                                'TBO Car'  => 'TBOCar',
                                'TBO Holiday' => 'TBOHol',
                                'DOTW' => 'DOTW',
                                'Rate Hawk' => 'RateH',
                                'Travel Collection' => 'TravC',
                                'Mamlakat Alasfar' => 'MAMLK',
                                'Como Travels' => 'COMO',
                                'Smile Holidays' => 'SMIL',
                                'Jnan Tours' => 'JNAN',
                                'World of Luxury' => 'WLUX',
                                'Heysam Group' => 'HEYS',
                                'DARINA HOLIDAYS' => 'DARIN',
                                'HOTEL TOURS' => 'HTOUR',
                                'Supreme Services' => 'SUPR',
                                'Blue Sky' => 'BSKY',
                                'Sky Rooms' => 'SKYR',
                                'Rezlive' => 'REZL',
                            ];
                            $prefix = $mergePrefixMap[$supplier->name] ?? preg_replace('/\s+/', '', $supplier->name);
                            $mergedName = sprintf('%s-%s-b%02d.pdf', $prefix, now()->format('ymdHi'), $batchIndex);
                        }
                    }

                    $mergedPath = "{$companyName}/{$supplierName}/files_unprocessed/{$mergedName}";
                    if (Storage::exists($mergedPath) || FileUpload::where([
                        'file_name'   => $mergedName,
                        'supplier_id' => $supplier->id,
                        'company_id'  => $company->id,
                    ])->exists()) {
                        $base = preg_replace('/\.pdf$/i', '', $mergedName);
                        $mergedName = $base . '-' . now()->format('ymdHi') . '.pdf';
                        $mergedPath = "{$companyName}/{$supplierName}/files_unprocessed/{$mergedName}";
                    }
                    Storage::put($mergedPath, $mergedBytes);

                    FileUpload::create([
                        'file_name'        => $mergedName,
                        'destination_path' => Storage::path($mergedPath),
                        'user_id'          => $user->id,
                        'supplier_id'      => $supplier->id,
                        'company_id'       => $company->id,
                        'status'           => 'pending',
                        'source_files'     => $successFiles,
                    ]);

                    if (count($successFiles) === 1) {
                        $allMessages[] = "Batch {$batchIndex} uploaded single PDF: " . $successFiles[0];
                    } else {
                        $allMessages[] = "Batch {$batchIndex} merged successfully. Uploaded files: " . implode(', ', $successFiles);
                    }
                    foreach ($successFiles as $f) $allData[] = $f;
                }

                return [[
                    'status'  => $hasError ? 'error' : 'success',
                    'message' => implode(' | ', $allMessages),
                    'data'    => $allData,
                ]];
            } catch (\Throwable $e) {
                Log::error('TBO batch merge failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                return [[
                    'status'  => 'error',
                    'message' => 'Failed to merge TBO PDFs.',
                    'data'    => [$e->getMessage()],
                ]];
            }
        }

        $error = false;
        $errorFilesWithMessage = [];

        $success = false;
        $successFiles = [];

        foreach ($files as $file) {
            $errorFile = [];
            $fileName = $file->getClientOriginalName();

            $existingFileUpload = FileUpload::where([
                'file_name' => $fileName,
                'supplier_id' => $supplier->id,
                'company_id' => $company->id,
            ]);

            if ($existingFileUpload->exists()) {
                Log::info("File {$fileName} already exists for supplier {$supplier->name}, in company {$company->name}. Skipping upload.");

                $userUpload = $existingFileUpload->first()->user;

                if ($userUpload->id !== $user->id) {

                    if ($userUpload->company !== null) {
                        $message = "File has been uploaded by your admin. Please contact them to resolve this issue.";
                    } else {
                        $message = "File has been uploaded by another user : {$userUpload->name}. Please contact them to resolve this issue.";
                    }

                    Log::info("File {$fileName} already uploaded by another user: {$userUpload->name}.");

                    $errorFile['file_name'] = $fileName;
                    $errorFile['message'] = $message;

                    $errorFilesWithMessage[] = $errorFile;
                } else {
                    Log::info("File {$fileName} already uploaded by the same user: {$user->name}.");

                    $errorFile['file_name'] = $fileName;
                    $errorFile['message'] = "File has already been uploaded by you";

                    $errorFilesWithMessage[] = $errorFile;
                }
                $error = true;
                continue;
            }

            $file->move($filePath, $fileName);

            Log::info("Uploading file: " . $file->getClientOriginalName() . " to: " . $filePath);

            try {
                FileUpload::create([
                    'file_name' => $file->getClientOriginalName(),
                    'destination_path' => $filePath . '/' . $file->getClientOriginalName(),
                    'user_id' => $user->id,
                    'supplier_id' => $supplier->id,
                    'company_id' => $company->id,
                    'status' => 'pending',
                ]);
            } catch (Exception $e) {
                Log::error("Failed to create file upload record for {$fileName}: " . $e->getMessage());
                $errorFilesWithMessage['file_name'] = $fileName;
                $errorFilesWithMessage['message'] = "Something went wrong";
                $error = true;
                continue;
            }

            $successFiles[] = $file->getClientOriginalName();
            $success = true;
        }

        $response = [];
        if ($error) {
            Log::error("Some files failed to upload: ");

            $data = [];

            foreach ($errorFilesWithMessage as $fileError) {
                $data[] = [
                    'file_name' => $fileError['file_name'],
                    'message' => $fileError['message'],
                ];
            }

            $response[] = [
                'status' => 'error',
                'message' => 'Some files failed to upload.',
                'data' => $data,
            ];
        }

        if ($success) {
            Log::info("Files uploaded successfully: " . implode(', ', $successFiles));

            $response[] = [
                'status' => 'success',
                'message' => 'Files uploaded successfully: ' . implode(', ', $successFiles),
                'data' => $successFiles,
            ];
        }

        return $response;
    }

    private function sanitizePdfName(?string $name): ?string
    {
        if (!$name) return null;

        $name = preg_replace('/[^\w\s\.\-]+/u', '', $name);
        $name = preg_replace('/\s+/', '_', trim($name));
        $name = ltrim($name, '._');

        if ($name === '') return null;
        return preg_replace('/\.pdf$/i', '', $name) . '.pdf';
    }

    public function exportCsv()
    {

        // Fetch all agents data
        $tasks = Task::with('agent')->get();

        // Create a CSV file in memory
        $csvFileName = 'tasks.csv';
        $handle = fopen('php://output', 'w');

        // Set headers for the response
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $csvFileName . '"');

        // Add CSV header
        fputcsv($handle, ['Agent Name', 'Agent Email', 'Task', 'Type', 'Status']);

        // Add company data to CSV
        foreach ($tasks as $task) {
            fputcsv($handle, [
                $task->agent->name,
                $task->agent->email,
                $task->description,
                $task->task_type,
                $task->status
            ]);
        }

        fclose($handle);
        exit();
    }

    /**
     * Save flight details to the database
     * 
     * @param array $data
     * @param int $taskId
     * 
     * @return void 
     *
     */
    public function saveFlightDetails($data, int $taskId)
    {
        try {
            // Handle both single flight detail object and array of flight details
            if (isset($data[0]) && is_array($data[0])) {
                // Multiple flight segments - array of flight detail objects
                foreach ($data as $flightData) {
                    $this->createSingleFlightDetail($flightData, $taskId);
                }
            } else {
                // Single flight detail object
                $this->createSingleFlightDetail($data, $taskId);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Create a single flight detail record
     * 
     * @param array $data
     * @param int $taskId
     * 
     * @return void
     */
    private function createSingleFlightDetail(array $data, int $taskId)
    {
        Log::info('Creating flight detail', ['data' => $data, 'task_id' => $taskId]);
        try {
            $airline = isset($data['airline_name']) ? Airline::where('name', 'like', '%' . $data['airline_name'] . '%')->first() : null;

            // Handle both 'departure_from'/'arrive_to' and 'country_id_from'/'country_id_to' fields
            $countryFrom = null;
            $countryTo = null;

            if (isset($data['departure_from'])) {
                $countryFrom = Country::where('name', 'like', '%' . $data['departure_from'] . '%')->first();
            } elseif (isset($data['country_id_from'])) {
                $countryFrom = is_numeric($data['country_id_from'])
                    ? Country::find($data['country_id_from'])
                    : Country::where('name', 'like', '%' . $data['country_id_from'] . '%')->first();
            }

            if (isset($data['arrive_to'])) {
                $countryTo = Country::where('name', 'like', '%' . $data['arrive_to'] . '%')->first();
            } elseif (isset($data['country_id_to'])) {
                $countryTo = is_numeric($data['country_id_to'])
                    ? Country::find($data['country_id_to'])
                    : Country::where('name', 'like', '%' . $data['country_id_to'] . '%')->first();
            }

            // Handle airline_id field - could be airline name or ID
            $airlineId = null;
            if (isset($data['airline_id'])) {
                if (is_numeric($data['airline_id'])) {
                    $airlineId = $data['airline_id'];
                } else {
                    $airlineFromId = Airline::where('name', 'like', '%' . $data['airline_id'] . '%')->first();
                    $airlineId = $airlineFromId ? $airlineFromId->id : null;
                }
            } elseif ($airline) {
                $airlineId = $airline->id;
            }

            $flightDetails = [
                'farebase' => isset($data['farebase']) ? (float) $data['farebase'] : null,
                'departure_time' => $data['departure_time'] ?? null,
                'country_id_from' => $countryFrom ? $countryFrom->id : null,
                'airport_from' => $data['airport_from'] ?? null,
                'terminal_from' => $data['terminal_from'] ?? null,
                'arrival_time' => $data['arrival_time'] ?? null,
                'duration_time' => $data['duration_time'] ?? null,
                'country_id_to' => $countryTo ? $countryTo->id : null,
                'airport_to' => $data['airport_to'] ?? null,
                'terminal_to' => $data['terminal_to'] ?? null,
                'airline_id' => $airlineId,
                'flight_number' => $data['flight_number'] ?? null,
                'ticket_number' => $data['ticket_number'] ?? null,
                'class_type' => $data['class_type'] ?? null,
                'baggage_allowed' => $data['baggage_allowed'] ?? null,
                'equipment' => $data['equipment'] ?? null,
                'flight_meal' => $data['flight_meal'] ?? null,
                'seat_no' => $data['seat_no'] ?? null,
                'task_id' => $taskId,
                'is_ancillary' => !empty($data['is_ancillary']) ? 1 : 0, // <-- Add this line

            ];

            TaskFlightDetail::create($flightDetails);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Save hotel details to the database
     * 
     * @param array $data
     * @param int $taskId
     * 
     * @return void
     */
    public function saveHotelDetails(array $data, int $taskId)
    {
        try {
            // Handle both single hotel detail object and array of hotel details
            if (isset($data[0]) && is_array($data[0])) {
                // Multiple hotel details - array of hotel detail objects
                foreach ($data as $hotelData) {
                    $this->createSingleHotelDetail($hotelData, $taskId);
                }
            } else {
                // Single hotel detail object
                $this->createSingleHotelDetail($data, $taskId);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function createSingleHotelDetail(array $data, int $taskId)
    {
        try {
            $hotel = isset($data['hotel_name']) ? Hotel::where('name', 'like', '%' . $data['hotel_name'] . '%')->first() : null;

            if (!$hotel) {

                Log::info('Creating new hotel: ' . $data['hotel_name']);

                try {
                    $hotel = Hotel::create([
                        'name' => $data['hotel_name'],
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to create hotel: ' . $e->getMessage());
                    throw new Exception('Failed to create hotel: ' . $e->getMessage());
                }

                Log::info('Created hotel with ID: ' . $hotel->id);
            }

            $roomNumber = $data['room_number'] ?? null;
            if (is_array($roomNumber)) {
                $roomNumber = implode(', ', array_filter($roomNumber));
            }

            $roomDetails = $data['room_details'] ?? null;
            if (is_array($roomDetails)) {
                $roomDetails = implode(', ', array_filter($roomDetails));
            }
            
            $hotelDetails = [
                'hotel_id' => $hotel->id,
                'check_in' => isset($data['check_in']) ? Carbon::parse($data['check_in']) : null,
                'check_out' => isset($data['check_out']) ? Carbon::parse($data['check_out']) : null,
                'city' => $data['city'] ?? null,
                'room_type' => $data['room_type'] ?? null,
                'room_number' => $data['room_number'] ?? null,
                'room_details' => $data['room_details'] ?? null,
                'meal_type' => $data['meal_type'] ?? null,
                'adults' => isset($data['adults']) ? (int) $data['adults'] : null,
                'children' => isset($data['children']) ? (int) $data['children'] : null,
                'task_id' => $taskId
            ];

            TaskHotelDetail::create($hotelDetails);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Save insurance details to the database
     * 
     * @param array $data
     * @param int $taskId
     * 
     * @return void
     */
    public function saveInsuranceDetails(array $data, int $taskId)
    {
        try {
            if (isset($data[0]) && is_array($data[0])) {
                foreach ($data as $insuranceData) {
                    $this->createSingleInsuranceDetail($insuranceData, $taskId);
                }
            } else {
                $this->createSingleInsuranceDetail($data, $taskId);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function createSingleInsuranceDetail(array $data, int $taskId)
    {
        try {
            $insuranceDetails = [
                'date' => $data['date'] ?? null,
                'paid_leaves' => $data['paid_leaves'] ?? null,
                'document_reference' => $data['document_reference'] ?? null,
                'insurance_type' => $data['insurance_type'] ?? null,
                'destination' => $data['destination'] ?? null,
                'plan_type' => $data['plan_type'] ?? null,
                'duration' => $data['duration'] ?? null,
                'package' => $data['package'] ?? null,
                'task_id' => $taskId
            ];

            TaskInsuranceDetail::create($insuranceDetails);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function saveVisaDetails(array $data, int $taskId)
    {
        try {
            if (isset($data[0]) && is_array($data[0])) {
                foreach ($data as $visaData) {
                    $this->createSingleVisaDetail($visaData, $taskId);
                }
            } else {
                $this->createSingleVisaDetail($data, $taskId);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function createSingleVisaDetail(array $data, int $taskId)
    {
        try {
            $visaDetails = [
                'visa_type' => $data['visa_type'] ?? null,
                'application_number' => $data['application_number'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'number_of_entries' => $data['number_of_entries'] ?? null,
                'stay_duration' => $data['stay_duration'] ?? null,
                'issuing_country' => $data['issuing_country'] ?? null,
                'task_id' => $taskId
            ];

            TaskVisaDetail::create($visaDetails);
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function queue()
    {

        $queueTasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')
            ->withoutGlobalScope('enabled')
            ->where('enabled', false)
            ->orderBy('id', 'desc');

        $user = Auth::user();

        if ($user->role_id == Role::COMPANY) {
            $queueTasks = $queueTasks->get();
        } else if ($user->role_id == Role::BRANCH) {
            $queueTasks = $queueTasks->where('agent_id', $user->branch->agents->pluck('id'))->get();
        } else if ($user->role_id == Role::AGENT) {
            $queueTasks = $queueTasks->where('agent_id', $user->agent->id)->get();
        } else {
            return redirect()->back()->with('error', 'User not authorized to view tasks.');
        }

        return view('tasks.queue', compact('queueTasks'));
    }

    public function supplierTask($id)
    {
        $user = Auth::user();

        if (!$user->role_id == Role::COMPANY) {
            return redirect()->back()->with('error', 'User is not a company');
        }

        $supplier = Supplier::findOrFail($id);
        $supplierController = new SupplierController();
        $companyId = $user->company->id;


        if (!$supplier) {
            return redirect()->back()->with('error', 'Does not have task from supplier');
        }

        if ($supplier->name == 'Magic Holiday') {

            $response = $supplierController->getMagicHoliday();

            $data = json_decode($response->getContent(), true);

            Log::channel('magic_holidays')->info('Magic Holiday response: ', $data);

            if (isset($data['error'])) {
                Log::channel('magic_holidays')->error('Error getting task from supplier: ' . $data['error']);
                return redirect()->back()->with('error', 'Something went wrong');
            }

            if (isset($data['status']) && $data['status'] == 'error') {
                Log::channel('magic_holidays')->error('Error getting task from supplier: ' . $data['detail']);
                return redirect()->back()->with('error', 'Something went wrong');
            }

            $data = $data['data'];
            Log::info('Data dari Magic Holiday: ', $data);
            if (isset($data['_embedded'])) { // Check if it's a list
                foreach ($data['_embedded']['reservation'] as $reservation) {
                    $response = $this->processSingleReservation($reservation, null, $companyId);

                    if ($response['status'] == 'error') {
                        return redirect()->back()->with('error', $response['message']);
                    }
                }
            } else {
                $response = $this->processSingleReservation($data, null, $companyId);

                if ($response['status'] == 'error') {
                    return redirect()->back()->with('error', $response['message']);
                }
            }

            return redirect()->back()->with('success', 'Magic Holiday task received successfully');
        }

        return redirect()->back()->with('error', 'Does not have task from supplier');
    }

    public function processSingleReservation($reservation, $agentId = null, $companyId)
    {
        $clientName = $reservation['service']['passengers'][0]['firstName'] ? $reservation['service']['passengers'][0]['firstName'] . ' ' . $reservation['service']['passengers'][0]['lastName'] : null;
        $hotel = $reservation['service']['hotel'] ?? null;
        $serviceDates = $reservation['service']['serviceDates'] ?? null;
        $prices = $reservation['service']['prices'] ?? null;
        $status = 'issued'; // Default status
        $pnr = $reservation['service']['pnr'] ?? null;
        $clientRef = $reservation['clientRef'] ?? null;

        $cancellationPolicy = [];

        if ($agentId === null) {
            $agent = $reservation['agent'];

            if (!$agent) {
                Log::channel('magic_holidays')->error('Agent not found in reservation data for reservation ID: ' . ($reservation['id'] ?? 'Unknown'));
                return [
                    'status' => 'error',
                    'message' => 'Something Went Wrong',
                ];
            }

            if ($clientRef && str_contains(strtolower($clientRef), 'pb-')) {
                $agentInDB = Agent::where('name', 'AI Agent')
                    ->whereHas('branch', function ($query) use ($companyId) {
                        $query->where('company_id', $companyId);
                    })->first();
            } else {
                $agentInDB = Agent::where('name', $agent['name'])
                    ->orWhere('email', 'like', $agent['email'])
                    ->orWhere('phone_number', 'like', $agent['telephone'])
                    ->first();
            }


            if ($agentInDB) {
                $agentId = $agentInDB->id;
            } else {
                Log::channel('magic_holidays')->error('Agent ' . $agent['name'] . ' not found in database');

                return [
                    'status' => 'error',
                    'message' => 'Agent ' . $agent['name'] . ' not found in database. Please create the agent first.',
                ];
            }
        }

        $supplierStatus = $reservation['service']['status'];

        switch ($supplierStatus) {
            case 'OK':
                $status = 'issued';
                break;
            case 'AM':
                $status = 'reissued';
                break;
            case 'RQ':
                $status = 'confirmed';
                break;
            case 'XX':
                $status = 'void';
                break;
            case 'XP':
                $status = 'void';
                break;
            default:
                $status = 'confirmed';
                break;
        }

        $cancellationDate = null;

        if (isset($reservation['service']['cancellationPolicy'])) {

            foreach ($reservation['service']['cancellationPolicy']['policies'] as $policy) {
                $cancellationPolicy[] = [
                    'type' => $policy['type'],
                    'charge' => $policy['charge'] !== null ? $policy['charge']['value'] : null,
                ];
            }

            $cancellationDate = $reservation['service']['cancellationPolicy']['date'];
        }

        if ($cancellationDate && ($supplierStatus == 'OK' || $supplierStatus == 'RQ')) {
            $cancellationDate = Carbon::parse($cancellationDate)->toDateTimeString();

            if (Date::now()->greaterThanOrEqualTo($cancellationDate)) {
                $status = 'issued';
            } else {
                $status = 'confirmed';
            }
        }

        $cancellationPolicy = json_encode($cancellationPolicy);
        $supplier = Supplier::where('name', 'Magic Holiday')->first();

        if (!$supplier) {
            Log::channel('magic_holidays')->error('Supplier not found: Magic Holiday');
            return [
                'status' => 'error',
                'message' => 'Something Went Wrong',
            ];
        }

        $supplierId = $supplier->id;

        if (!$reservation['service']['rooms']) {
            Log::channel('magic_holidays')->warning('No rooms data found for reservation: ' . ($reservation['id'] ?? 'Unknown'));
            return; // Skip this reservation if no rooms are found
        }


        $processResult = [];
        $processResult['success'] = [];
        $processResult['failed'] = [];

        foreach ($reservation['service']['rooms'] as $room) {
            $enabled = true; // Assume enabled by default

            if ($reservation['service']['status'] ?? null) {
                $statusMagicTask = $reservation['service']['status'] == 'OK' ? 'issued' : 'confirmed'; //not used for now

            } else { // but we still throw an exception if status is not found
                throw new Exception('Status not found');
            }

            $total = $prices['total']['selling']['value'] ?? null;

            $isRefund = $status == 'reissued'  && $supplierStatus == 'AM' && $total <= 0;

            if ($isRefund) {
                $status = 'refund';
                $total = $prices['issue']['selling']['value'] ?? null;
            }

            $taskData = [
                'client_id' => null,
                'agent_id' => $agentId,
                'company_id' => $companyId,
                'type' => 'hotel',
                'status' => $status,
                'supplier_status' => $supplierStatus,
                'client_name' => $clientName,
                'client_ref' => $reservation['clientRef'] ?? null,
                'reference' => (string)$reservation['id'] ?? null,
                'duration' => $serviceDates['duration'] ?? null,
                'payment_type' => $reservation['service']['payment']['type'] ?? null,
                'price' => $prices['issue']['selling']['value'] ?? null,
                'tax' => 0.00,
                'surcharge' => 0.00,
                'total' => $total,
                'cancellation_policy' => json_encode($cancellationPolicy) ?? null,
                'cancellation_deadline' => $cancellationDate ?? null,
                'additional_info' => $reservation['service']['hotel']['name'] . ' - ' . $clientName,
                'supplier_id' => $supplierId,
                'venue' => $hotel['name'] ?? null,
                'invoice_price' => null,
                'voucher_status' => null,
                'refund_date' => null,
                'issued_date' => Carbon::parse($reservation['added']['time'])->toDateTimeString() ?? null,
                'task_hotel_details' => [
                    'hotel_name' => $hotel['name'],
                    'hotel_country' => $hotel['countryId'],
                    'room_reference' => $room['id'] ?? null,
                    'booking_time' =>  Carbon::parse($reservation['added']['time'])->toDateTimeString() ?? null,
                    'check_in' => Carbon::parse($serviceDates['startDate'])->toDateTimeString() ?? null,
                    'check_out' => Carbon::parse($serviceDates['endDate'])->toDateTimeString() ?? null,
                    'room_reference' => (string) $room['id'] ?? null,
                    'room_number' => $room['number'] ?? null,
                    'room_type' => $room['name'] ?? null,
                    'room_amount' => count($room['passengers'] ?? []),
                    'room_details' => json_encode($room) ?? null,
                    'rate' => $prices['issue']['selling']['value'] ?? null,
                    'meal_type' => $room['board'] ?? null,
                    'is_refundable' => strpos(strtolower($room['info'] ?? ''), 'non-refundable') === false,
                ],
            ];

            foreach ($taskData as $key => $value) {
                if ($value === null) {
                    $enabled = false;
                    Log::channel('magic_holidays')->warning("Missing required field: $key for reservation: " . ($reservation['id'] ?? 'Unknown'));
                    break;
                }
            }
            $taskData['enabled'] = $enabled;
            Log::channel('magic_holidays')->info('Creating Task Initiate');

            $request = new Request($taskData);

            $existingTask = Task::where('reference', $taskData['reference'])
                ->where('agent_id', $taskData['agent_id'])
                ->where('supplier_id', $taskData['supplier_id'])
                ->first();

            $response = $this->store($request);

            $response = json_decode($response->getContent(), true) ?? [];
            logger('Task created: ', $response);

            if ($response['status'] == 'error') {
                Log::channel('magic_holidays')->error('Error creating task: ' . $response['message']);

                $processResult['failed'][] = [
                    'reference' => $taskData['reference'],
                    'message' => 'Error creating task: ' . $response['message'],
                ];
                continue;
            }

            $task = Task::with('hotelDetails')->find($response['data']['id']);

            if (!$task) {
                Log::channel('magic_holidays')->error('Task not found after creation: ' . $response['data']['id']);
                $processResult['failed'][] = [
                    'reference' => $taskData['reference'],
                    'message' => 'Task not found after creation',
                ];
                continue; // Skip to the next room if task creation failed
            }

            $passengers = $reservation['service']['passengers'] ?? null;

            $adultCount = 0;
            $childCount = 0;

            foreach ($room['passengers'] as $passengerId) {
                $passenger = collect($passengers)->where('paxId', $passengerId)->first();

                if (!$passenger) {
                    continue;
                }

                if ($passenger['type'] == 'adult') {
                    $adultCount++;
                } elseif ($passenger['type'] == 'child') {
                    $childCount++;
                } else {
                    logger('Unknown passenger type: ' . $passenger['type']);
                    continue;
                }
            }

            try {
                $room = Room::create([
                    'task_hotel_details_id' => $task->hotelDetails->id,
                    'name' => $room['name'] ?? null,
                    'reference' => (string)$room['id'] ?? null,
                    'adult_count' => $adultCount,
                    'child_count' => $childCount,
                ]);
            } catch (Exception $e) {
                $task->delete();

                Log::channel('magic_holidays')->error('Error creating room: ' . $e->getMessage(), [
                    'reservation' => $reservation,
                    'room' => $room,
                ]);

                $processResult['failed'][] = [
                    'reference' => $taskData['reference'],
                    'message' => 'Error creating room: ' . $e->getMessage(),
                ];
                continue; // Skip to the next room if room creation failed
            }


            Log::channel('magic_holidays')->info('Task created for reservation: ' . ($reservation['id'] ?? 'Unknown') . ', Room: ' . ($room['id'] ?? 'Unknown'));

            $processResult['success'][] = [
                'reference' => $taskData['reference'],
                'message' => 'Task created successfully',
                'room_id' => $room->id,
            ];
        }


        if (count($processResult['success']) > 0) {
            Log::channel('magic_holidays')->info('Successfully processed reservation: ' . ($reservation['id'] ?? 'Unknown'));
        }

        if (count($processResult['failed']) > 0) {
            Log::channel('magic_holidays')->error('Failed to process reservation: ' . ($reservation['id'] ?? 'Unknown'));
        }

        return [
            'status' => count($processResult['failed']) > 0 ? 'error' : 'success',
            'message' => count($processResult['failed']) > 0 ? 'Some tasks failed to process' : 'All tasks processed successfully',
            'data' => $processResult,
        ];
    }

    public function supplierTaskForAgent(Request $request)
    {
        $request->validate([
            'supplier_ref' => 'nullable',
            'task_file' => 'nullable|array',
            'task_file.*' => 'mimes:pdf,txt',
            'supplier_id' => 'required|exists:suppliers,id',
            'batches.*'     => ['array'],
        ]);

        $supplier = Supplier::findOrFail($request->supplier_id);
        $supplierController = new SupplierController();

        // if($supplier->name !== 'Magic Holiday'){
        //     $request->validate([
        //         'agent_id' => 'required|exists:agents,id',
        //     ], [
        //         'agent_id.required' => 'Please select an agent',
        //     ]);
        // }

        $user = Auth::user();
        $agentId = null;
        if ($request->agent_id) {
            $agent = Agent::findOrFail($request->agent_id);

            if (!$agent) {
                return redirect()->back()->with('error', 'Agent not found');
            }

            $agentId = $agent->id;
        }

        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company->id;
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = $user->agent->branch->company->id;
        } else {
            return redirect()->back()->with('error', 'User not authorized to create task');
        }

        $request->merge([
            'company_id' => $companyId,
        ]);
        if ($supplier->is_manual && $supplier->has_hotel) {
            return $this->storeManualHotel($request);
        }

        switch ($supplier->name) {
            case 'Magic Holiday':

                if (!$request->supplier_ref && !$request->has('batches')) {
                    return redirect()->back()->with('error', 'Please provide either a supplier reference or upload a task file.');
                }

                if ($request->supplier_ref) {
                    $response = $supplierController->getMagicHoliday($request->supplier_ref);

                    if (!$response instanceof \Illuminate\Http\JsonResponse) {
                        Log::channel('magic_holidays')->error('Invalid response from Magic Holiday API', [
                            'supplier_ref' => $request->supplier_ref,
                            'expected_type' => 'Illuminate\Http\JsonResponse',
                            'actual_type' => get_class($response)
                        ]);

                        return redirect()->back()->with('error', 'Something went wrong in fetching data from Magic Holiday API');
                    }

                    $responseData = $response->getData(true);

                    Log::channel('magic_holidays')->info('Magic Holiday response: ', $responseData);

                    if (isset($responseData['status']) && $responseData['status'] == 'error') {
                        return redirect()->back()->with('error', $responseData['message']);
                    }

                    $data = $responseData['data'];

                    if (isset($data['_embedded'])) { // Check if it's a list
                        foreach ($data['_embedded']['reservation'] as $reservation) {
                            $response = $this->processSingleReservation($reservation, $agentId, $companyId);

                            if ($response['status'] == 'error') {
                                return redirect()->back()->with('error', $response['message']);
                            }

                            $supplierController->magicReserveWebhook($reservation['id']);
                        }
                    } else {
                        $response = $this->processSingleReservation($data, $agentId, $companyId);

                        if ($response['status'] == 'error') {
                            return redirect()->back()->with('error', $response['message'])->with('data', $response['data']['failed']);
                        }

                        $supplierController->magicReserveWebhook($data['id']);
                    }

                    return redirect()->back()->with('success', 'Magic Holiday task received successfully');
                }

                if ($request->has('batches')) {
                    $responses = $this->upload($request, $agentId, $companyId);
                    // Artisan::call('app:process-files', [], null, true);
                    $redirectResponse = redirect()->back();

                    foreach ($responses as $response) {
                        if ($response['status'] == 'success') {
                            $redirectResponse = $redirectResponse->with('success', $response['message']);
                        }

                        if ($response['status'] == 'error') {
                            $redirectResponse = $redirectResponse->with('error', $response['message'])->with('data', $response['data']);
                        }
                    }

                    return $redirectResponse;
                }
            default:
                $responses = $this->upload($request);
                // Artisan::call('app:process-files', [], null, true);
                $redirectResponse = redirect()->back();

                foreach ($responses as $response) {
                    if ($response['status'] == 'success') {
                        $redirectResponse = $redirectResponse->with('success', $response['message']);
                    }

                    if ($response['status'] == 'error') {
                        $redirectResponse = $redirectResponse->with('error', $response['message'])->with('data', $response['data']);
                    }
                }

                return $redirectResponse;
        }
    }

    public function getTboTask($companyId)
    {
        logger('TBO task is running');
        $tboController = new TBOController();

        $bookingDetailsToday = $tboController->bookingDetailByDate(
            new Request([
                'startDate' => date('Y-m-d', strtotime('-60 days')),
                'endDate' => date('Y-m-d')
            ])
        );

        if (isset($bookingDetailsToday['error'])) {
            logger('TBO Task Error: ' . $bookingDetailsToday['error']);
            return;
        }


        logger('TBO Task: ', $bookingDetailsToday);

        foreach ($bookingDetailsToday as $booking) {
            // $agent = Agent::where('tbo_reference', $booking['ClientReferenceNumber'])->first();

            // if (!$agent) {
            //     logger('TBO Task Error: Client Reference Number does not register with any agent. Client Reference Number: ' . $booking['ClientReferenceNumber']);
            //     return;
            // }

            $supplier = Supplier::where('name', 'TBO Holiday')->first();

            $existingTask = Task::where(['reference' => $booking['ConfirmationNo'], 'supplier_id' => $supplier->id])
                ->withoutGlobalScope('enabled')->first();

            if ($existingTask) {
                logger('TBO Task Error: Task already exists');
                return redirect()->back()->with('error', 'Task ' . $existingTask->reference . ' already exists');
            }

            $checkInDate = new \DateTime($booking['CheckInDate']);
            $checkOutDate = new \DateTime($booking['CheckOutDate']);
            $interval = $checkInDate->diff($checkOutDate);
            $hours = $interval->days * 24 + $interval->h;

            $details = $tboController->bookingDetail(
                new Request([
                    'confirmationNumber' => $booking['ConfirmationNo']
                ])
            );

            logger('TBO Task Details: ', $details);

            if (!isset($details['Rooms'])) {
                logger('TBO Task Error: No rooms found');
                return;
            }

            if (count($details['Rooms']) < 1) {
                logger('TBO Task Error: No rooms found');
                return;
            }

            foreach ($details['Rooms'] as $room) {

                if (!isset($room['CustomerDetails'])) {
                    logger('TBO Task Error: No customer details found');
                    return;
                }

                if (count($room['CustomerDetails']) < 1) {
                    logger('TBO Task Error: No customer details found');
                    return;
                }

                foreach ($room['CustomerDetails'][0]['CustomerNames'] as $key => $customer) {
                    $client = Client::updateOrCreate([
                        'name' => $customer['FirstName'] . ' ' . $customer['LastName'],
                    ]);

                    if (!$client) {
                        logger('TBO Task Error: Client failed to create');
                        return;
                    }

                    logger('TBO Task Client: ' . $client->first_name . ' created');

                    if ($key == 0) {
                        $leaderCustomer = $client;

                        logger('TBO Task : Leader Customer: ' . $leaderCustomer->name);
                    }
                }
                try {
                    $task = Task::create([
                        'client_id' => $client->id,
                        'agent_id' => null,
                        'company_id' => $companyId,
                        'type' => 'hotel',
                        'status' => strtolower($booking['BookingStatus']),
                        'client_name' => $leaderCustomer->name,
                        'reference' => $booking['ConfirmationNo'],
                        'duration' => $hours,
                        'payment_type ' => null,
                        'price' => $room['TotalFare'],
                        'tax' => $room['TotalTax'],
                        'surcharge' => null,
                        'total' => $room['TotalFare'],
                        'cancellation_policy' => json_encode($room['CancelPolicies']),
                        'additional_info' => null,
                        'supplier_id' => $supplier->id,
                        'venue' =>  $details['HotelDetails']['City'],
                        'invoice_price' => null,
                        'voucher_status' => (string)$details['VoucherStatus'],
                        'refund_date' => null,

                    ]);
                } catch (Exception $e) {
                    logger('TBO Task Error: ' . $e->getMessage());
                    return redirect()->back()->with('error', 'Task failed to create');
                }

                try {
                    $hotelRating = 0.0;

                    switch ($details['HotelDetails']['Rating']) {
                        case 'OneStar':
                            $hotelRating = 1.0;
                            break;
                        case 'TwoStar':
                            $hotelRating = 2.0;
                            break;
                        case 'ThreeStar':
                            $hotelRating = 3.0;
                            break;
                        case 'FourStar':
                            $hotelRating = 4.0;
                            break;
                        case 'All':
                            $hotelRating = 5.0;
                            break;
                        default:
                            $hotelRating = 0.0;
                            break;
                    }

                    $taskHotelDetails = TaskHotelDetail::create([
                        'task_id' => $task->id,
                        'hotel_id' => 1,
                        'booking_time' => Date('Y-m-d H:i:s', strtotime($booking['BookingDate'])),
                        'check_In' => Date('Y-m-d H:i:s', strtotime($booking['CheckInDate'])),
                        'check_out' => Date('Y-m-d H:i:s', strtotime($booking['CheckOutDate'])),
                        'room_amount' => 1,
                        'room_type' => json_encode($room['Name']),
                        'room_details' => $room['Inclusion'],
                        'room_promotion' => $room['RoomPromotion'] ?? null,
                        'rate' => $hotelRating,
                        'meal_type' => $room['MealType'],
                        'is_refundable' => $room['IsRefundable'],
                        'supplements' => isset($room['Supplements']) ? json_encode($room['Supplements']) : null,
                    ]);

                    logger('task with id: ' . $task->id . ' and task hotel details with id: ' . $taskHotelDetails->id . ' has been created');
                } catch (Exception $e) {
                    logger('TBO Task Error: ' . $e->getMessage());
                    $task->delete();
                    return redirect()->back()->with('error', 'Task Details failed to create');
                }
            }
        }

        logger('TBO task is done');

        return redirect()->back()->with('success', 'TBO task received successfully');
    }

    public function flightPdf($taskId)
    {
        $invoiceTask = Task::with(['flightDetails.countryFrom', 'flightDetails.countryTo', 'agent', 'client'])->findOrFail($taskId);

        if ($invoiceTask->gds_reference) {
            $tasks = Task::with(['flightDetails.countryFrom', 'flightDetails.countryTo', 'agent', 'client'])->where('gds_reference', $invoiceTask->gds_reference)->get();

            Log::info("flightPdf: loaded tasks for GDS {$invoiceTask->gds_reference}", [
                'count' => $tasks->count(),
                'ids'   => $tasks->pluck('id')->toArray()
            ]);

            if ($tasks->isEmpty()) {
                Log::warning("no tasks for gds_reference={$invoiceTask->gds_reference}, falling back to invoiceTask only");
                $tasks = collect([$invoiceTask]);
            }
        } else {
            Log::warning("invoiceTask task {$taskId} has no gds_reference, falling back to invoiceTask only");
            $tasks = collect([$invoiceTask]);
        }

        $flights = $invoiceTask->flightDetails()->get();
        $agent  = $invoiceTask->agent;

        return view('tasks.pdf.flight', compact('tasks', 'flights'));
    }

    public function hotelPdf($taskId)
    {
        $invoiceTask = Task::with('company', 'hotelDetails.room', 'hotelDetails.hotel.country', 'agent', 'client')->findOrFail($taskId);
        $tasks = $invoiceTask->reference ? Task::with(['agent', 'client', 'company'])->where('reference', $invoiceTask->reference)->get() : collect([$invoiceTask]);

        if ($tasks->isEmpty()) {
            $tasks = collect([$invoiceTask]);
        }

        $hotelDetail = $invoiceTask->hotelDetails;

        $task = $tasks->first();
        $company = $task->company;
        $policies = [];
        if ($task->cancellation_policy) {
            $decoded = @json_decode($task->cancellation_policy, true);
            if (is_string($decoded)) $decoded = @json_decode($decoded, true);
            if (is_array($decoded)) $policies = $decoded;
        }

        $boardLabels = [
            'RO' => 'Room Only',
            'SC' => 'Self-Catering',
            'BB' => 'Bed & Breakfast',
            'HB' => 'Half Board',
            'FB' => 'Full Board',
            'AI' => 'All Inclusive',
            'RD' => 'Room Description',
        ];

        return view('tasks.pdf.hotel', compact('tasks', 'company', 'hotelDetail', 'policies', 'boardLabels'));
    }

    public function receiptPdf($taskId)
    {
        $task = Task::with('invoiceDetail', 'invoiceDetail.task', 'invoiceDetail.invoice', 'invoiceDetail.invoice.payment')->findOrFail($taskId);
        $invoiceDetail = $task->invoiceDetail;

        return view('tasks.pdfView.receipt-view', compact('task', 'invoiceDetail'));
    }

    public function receiptPdfDownload($taskId)
    {
        $task = Task::with('invoiceDetail', 'invoiceDetail.task', 'invoiceDetail.invoice', 'invoiceDetail.invoice.payment')->findOrFail($taskId);
        $invoiceDetail = $task->invoiceDetail;

        $pdf = Pdf::loadView('tasks.pdf.receipt', compact('task', 'invoiceDetail'));

        return $pdf->download('receipt.pdf');
    }

    public function ReverseUnpaidVoidedTask(Task $voidTask, Task $originalTask)
    {
        Log::info('Recording reversal journal & transaction for task ID: ' . $originalTask->id);

        // Use task's issued_date as transaction_date
        $transactionDate = $originalTask->supplier_pay_date ? Carbon::parse($originalTask->supplier_date) : Carbon::now();

        $journalEntries = JournalEntry::where('task_id', $originalTask->id)->get();
        $branchIdFromJournal = $journalEntries->first()?->branch_id;

        $transaction = Transaction::create([
            'branch_id' => $originalTask->agent->branch_id ?? $branchIdFromJournal,
            'company_id' => $originalTask->company_id,
            'name' => $originalTask->client->full_name ?? null,
            'entity_id' => $originalTask->company_id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => $originalTask->total,
            'description' => 'Void reversal: ' . $originalTask->reference,
            'reference_type' => 'Payment',
            'transaction_date' => $transactionDate,
        ]);

        foreach ($journalEntries as $entry) {
            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'company_id' => $entry->company_id,
                'branch_id' => $entry->branch_id,
                'account_id' => $entry->account_id,
                'task_id' => $voidTask->id,
                'transaction_date' => $transactionDate,
                'description' => 'Reversal: ' . $entry->description,
                'name' => $entry->name,
                'debit' => $entry->credit,
                'credit' => $entry->debit,
                'balance' => $entry->balance,
                'type' => $entry->type,
            ]);
        }

        // JournalEntry::create([
        //     'transaction_id' => $transaction->id,
        //     'company_id' => $originalTask->company_id,
        //     'branch_id' => $originalTask->agent->branch_id,
        //     'account_id' => $supplierCost->id,
        //     'task_id' => $originalTask->id,
        //     'transaction_date' => $transactionDate,
        //     'description' => 'Reversal: Cancelled Cost from ' . $supplierCompany->supplier->name,
        //     'name' => $supplierCompany->supplier->name,
        //     'debit' => 0,
        //     'credit' => $originalTask->total,
        //     'balance' => $originalTask->total,
        //     'type' => 'payable',
        // ]);

        // JournalEntry::create([
        //     'transaction_id' => $transaction->id,
        //     'company_id' => $originalTask->company_id,
        //     'branch_id' => $originalTask->agent->branch_id,
        //     'account_id' => $issuedByAccount->id,
        //     'task_id' => $originalTask->id,
        //     'transaction_date' => $transactionDate,
        //     'description' => 'Reversal: Cancelled Payable to ' . $supplierCompany->supplier->name,
        //     'name' => $supplierCompany->supplier->name,
        //     'debit' => $originalTask->total,
        //     'credit' => 0,
        //     'balance' => $originalTask->total,
        //     'type' => 'payable',
        // ]);

        Log::info('Void reversal journal completed for task: ' . $originalTask->reference);
        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Unpaid void task reversal journal completed.',
            'data' => $originalTask,
        ], 201);
    }

    /**
     * Update branch_id in all journal entries for a task when agent is assigned/changed
     */
    private function updateJournalEntriesBranch(Task $task)
    {
        if (!$task->agent_id) {
            Log::warning('Cannot update journal entries branch - no agent assigned to task: ' . $task->reference);
            return;
        }

        $agent = Agent::find($task->agent_id);
        if (!$agent || !$agent->branch_id) {
            Log::warning('Cannot update journal entries branch - agent has no branch: ' . $task->reference);
            return;
        }

        $newBranchId = $agent->branch_id;

        // Find all transactions related to this task
        $transactions = Transaction::where('description', 'like', '%' . $task->reference . '%')->get();

        foreach ($transactions as $transaction) {
            $oldBranchId = $transaction->branch_id;

            // Update transaction branch_id
            $transaction->update(['branch_id' => $newBranchId]);

            // Update all journal entries for this transaction
            $updatedEntries = JournalEntry::where('transaction_id', $transaction->id)
                ->update(['branch_id' => $newBranchId]);

            Log::info('Updated journal entries for task agent assignment', [
                'task_reference' => $task->reference,
                'transaction_id' => $transaction->id,
                'old_branch_id' => $oldBranchId,
                'new_branch_id' => $newBranchId,
                'updated_entries_count' => $updatedEntries
            ]);
        }
    }

    public function clientPassport(Request $request)
    {
        if ($request->hasFile('file')) {
            try {
                $file = $request->file('file');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads', $fileName, 'public');

                $fullFilePath = storage_path('app/public/' . $filePath);

                Log::info('Processing passport file with AI:', [
                    'fileName' => $fileName,
                    'filePath' => $fullFilePath
                ]);

                $aiManager = new AIManager();
                $response = $aiManager->extractPassportData($fullFilePath, $fileName);

                Log::info('AI passport extraction response:', ['response' => $response]);

                if ($response['status'] === 'success') {
                    $passportData = $response['data'];

                    return response()->json([
                        'success' => true,
                        'message' => 'Passport data extracted successfully using AI!',
                        'data' => $passportData,
                    ], 200);
                } else {
                    Log::error('AI passport extraction failed: ' . $response['message']);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to extract passport data using AI: ' . $response['message'],
                        'errors' => $response['message'],
                    ], 400);
                }
            } catch (Exception $e) {
                Log::error('Failed to process passport with AI: ' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Error processing passport with AI',
                    'errors' => $e->getMessage(),
                ], 400);
            }
        } else {
            Log::error('No file uploaded for passport processing');
            return response()->json([
                'success' => false,
                'message' => 'Error processing passport',
                'errors' => 'No file uploaded.',
            ], 400);
        }
    }

    public function destroy($id)
    {
        $response = $this->destroyProcess($id);

        $response = json_decode($response->getContent(), true);

        if ($response['status'] === 'success') {
            return redirect()->back()->with('success', $response['message']);
        } else {
            return redirect()->back()->with('error', $response['message'])->with('data', $response['data'] ?? null);
        }
    }

    public function destroyProcess($id)
    {
        Gate::authorize('destroy', Task::class);

        // Check if user is super admin (admin role)
        $user = Auth::user();

        if ($user->role_id != Role::ADMIN) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Only super admin can delete tasks.',
            ], 403);
        }

        $task = Task::findOrFail($id);

        DB::beginTransaction();

        try {
            Log::info("Starting soft delete process for task: {$task->reference} (ID: {$id})");

            // 1. Soft delete journal entries related to the task
            $journalEntries = JournalEntry::where('task_id', $id)->get();
            if ($journalEntries->isNotEmpty()) {
                foreach ($journalEntries as $journalEntry) {
                    // Get transaction ID before soft deleting journal entry
                    $transactionId = $journalEntry->transaction_id;

                    // Soft delete journal entry
                    $journalEntry->delete();

                    // Soft delete associated transaction if it exists
                    if ($transactionId) {
                        $transaction = Transaction::find($transactionId);
                        if ($transaction) {
                            $transaction->delete();
                        }
                    }
                }
                Log::info("Soft deleted " . $journalEntries->count() . " journal entries and their transactions for task: {$task->reference}");
            }

            // 2. Soft delete invoice details related to the task
            $invoiceDetails = InvoiceDetail::where('task_id', $id)->get();
            $invoiceIds = [];

            if ($invoiceDetails->isNotEmpty()) {
                $invoiceIds = $invoiceDetails->pluck('invoice_id')->unique()->toArray();

                foreach ($invoiceDetails as $invoiceDetail) {
                    $invoiceDetail->delete();
                }
                Log::info("Soft deleted " . $invoiceDetails->count() . " invoice details for task: {$task->reference}");
            }

            // 3. Soft delete payments related to task invoices
            if (!empty($invoiceIds)) {
                $payments = Payment::whereIn('invoice_id', $invoiceIds)->get();
                if ($payments->isNotEmpty()) {
                    foreach ($payments as $payment) {
                        $payment->delete();
                    }
                    Log::info("Soft deleted " . $payments->count() . " payments for task: {$task->reference}");
                }

                // 4. Soft delete transactions related to task invoices
                $invoiceTransactions = Transaction::whereIn('invoice_id', $invoiceIds)->get();
                if ($invoiceTransactions->isNotEmpty()) {
                    foreach ($invoiceTransactions as $transaction) {
                        $transaction->delete();
                    }
                    Log::info("Soft deleted " . $invoiceTransactions->count() . " invoice transactions for task: {$task->reference}");
                }

                // 5. Soft delete invoices themselves
                $invoices = Invoice::whereIn('id', $invoiceIds)->get();
                if ($invoices->isNotEmpty()) {
                    foreach ($invoices as $invoice) {
                        $invoice->delete();
                    }
                    Log::info("Soft deleted " . $invoices->count() . " invoices for task: {$task->reference}");
                }
            }

            // 6. Soft delete task flight details
            $flightDetails = TaskFlightDetail::where('task_id', $id)->get();
            if ($flightDetails->isNotEmpty()) {
                foreach ($flightDetails as $flightDetail) {
                    $flightDetail->delete();
                }
                Log::info("Soft deleted " . $flightDetails->count() . " flight details for task: {$task->reference}");
            }

            // 7. Soft delete task hotel details
            $hotelDetails = TaskHotelDetail::where('task_id', $id)->get();
            if ($hotelDetails->isNotEmpty()) {
                foreach ($hotelDetails as $hotelDetail) {
                    $hotelDetail->delete();
                }
                Log::info("Soft deleted " . $hotelDetails->count() . " hotel details for task: {$task->reference}");
            }

            // 8. Finally, soft delete the task itself
            $task->delete();
            Log::info("Soft deleted task: {$task->reference} (ID: {$id})");

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Task '{$task->reference}' and all related data have been soft deleted successfully.",
                'data' => [
                    'task_id' => $id,
                    'task_reference' => $task->reference,
                    'deleted_at' => now()->toISOString()
                ]
            ], 200);
        } catch (Exception $e) {
            DB::rollback();
            Log::error("Error during task soft delete: " . $e->getMessage(), [
                'task_id' => $id,
                'task_reference' => $task->reference ?? 'Unknown',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete task: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateJournalPaymentMethod(Task $task, int $payment_method_account_id): JsonResponse
    {
        Log::info('Task ID: ' . $task->id . '. Updating journal entries for payment method account ID: ' . $payment_method_account_id);

        $paymentMethodAccount = Account::find($payment_method_account_id);

        if (!$paymentMethodAccount) {
            Log::error('Payment method account not found for ID: ' . $payment_method_account_id);
            return response()->json([
                'status' => 'error',
                'message' => 'Payment method account not found.',
            ], 404);
        }

        Log::info('Payment method account found: ' . $paymentMethodAccount->name . ' (ID: ' . $payment_method_account_id . ')');

        $supplier = Supplier::find($task->supplier_id);
        $branchId = $this->getTaskBranchId($task);

        $liabilities = Account::where('name', 'like', '%Liabilities%')
            ->where('company_id', $task->company_id)
            ->first();

        if (!$liabilities) {
            Log::error('Liabilities account not found for company ID: ' . $task->company_id);
            return response()->json([
                'status' => 'error',
                'message' => 'Liabilities account not found for company ID: ' . $task->company_id,
            ], 404);
        }

        // now edit payment method doesn't need to have journal entries in liabilities to be edited

        // $journalEntries = JournalEntry::where('task_id', $task->id)
        //     ->where('branch_id', $branchId)
        //     ->whereHas('account', function ($query) use ($liabilities) {
        //         $query->where('root_id', $liabilities->id);
        //     })
        //     ->get();

        // if ($journalEntries->isEmpty()) {
        //     Log::error('No existing journal entries found for task ID: ' . $task->id . ' with liabilities root ID: ' . $liabilities->id);
        //     return response()->json([
        //         'status' => 'error',
        //         'message' => 'No existing journal entries found for this task.',
        //     ], 404);
        // }

        $creditorsAccount = Account::where('name', 'Creditors')
            ->where('company_id', $task->company_id)
            ->where('root_id', $liabilities->id)
            ->first();

        if (!$creditorsAccount) {
            Log::error('Creditors account not found for company ID: ' . $task->company_id);
            return response()->json([
                'status' => 'error',
                'message' => 'Creditors account not found for company ID: ' . $task->company_id,
            ], 404);
        }

        $journalEntriesWithCreditorsChild = JournalEntry::where('task_id', $task->id)
            ->where('branch_id', $branchId)
            ->whereHas('account', function ($query) use ($creditorsAccount) {
                $query->where('parent_id', $creditorsAccount->id);
            })
            ->get();

        if ($journalEntriesWithCreditorsChild->isNotEmpty()) {
            Log::info('Found ' . $journalEntriesWithCreditorsChild->count() . ' journal entries attached to child accounts of Creditors account for task ID: ' . $task->id);

            //reverse the journal entries of the child accounts of Creditors
            foreach ($journalEntriesWithCreditorsChild as $journalEntry) {
                // Check if this journal entry has already been reversed
                $existingReversed = JournalEntry::where('task_id', $task->id)
                    ->where('account_id', $journalEntry->account_id)
                    ->where('description', 'like', 'Reversed: %')
                    ->where('description', 'like', '%' . $journalEntry->description . '%')
                    ->first();

                if ($existingReversed) {
                    Log::info('Journal entry ID: ' . $journalEntry->id . ' has already been reversed for task ID: ' . $task->id . '. Skipping reversal.');
                    continue;
                }

                // Check if the sum of debit and credit entries for this account equals zero
                $totalDebit = JournalEntry::where('task_id', $task->id)
                    ->where('account_id', $journalEntry->account_id)
                    ->sum('debit');

                $totalCredit = JournalEntry::where('task_id', $task->id)
                    ->where('account_id', $journalEntry->account_id)
                    ->sum('credit');

                if ($totalDebit == $totalCredit) {
                    Log::info('Journal entries for account ID: ' . $journalEntry->account_id . ' are already balanced (debit=' . $totalDebit . ', credit=' . $totalCredit . ') for task ID: ' . $task->id . '. Skipping reversal.');
                    continue;
                }

                $reversedJournalEntry = $journalEntry->replicate();
                $reversedJournalEntry->description = 'Reversed: ' . $journalEntry->description;
                $reversedJournalEntry->debit = $journalEntry->credit;
                $reversedJournalEntry->credit = $journalEntry->debit;
                $reversedJournalEntry->balance = -$journalEntry->balance;
                $reversedJournalEntry->save();

                Log::info('Reversed journal entry ID: ' . $journalEntry->id . ' for task ID: ' . $task->id);
            }
        } else {
            Log::info('No journal entries found attached to child accounts of Creditors account for task ID: ' . $task->id);
        }


        try {
            $transaction = Transaction::create([
                'branch_id' => $branchId,
                'company_id' => $task->company_id,
                'entity_id' => $task->company_id,
                'entity_type' => 'company',
                'transaction_type' => 'credit',
                'amount' => $task->total,
                'name' => $paymentMethodAccount->name,
                'description' => 'Update payment account for: ' . $task->reference,
                'reference_type' => 'Payment',
                'transaction_date' => $task->supplier_pay_date ?? $task->issued_date ?? $task->created_at,
            ]);

            Log::info('Created new transaction for task ID: ' . $task->id . ' with ID: ' . $transaction->id);

            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'company_id' => $task->company_id,
                'branch_id' => $branchId,
                'account_id' => $payment_method_account_id,
                'task_id' => $task->id,
                'debit' => 0,
                'credit' => $task->total,
                'balance' => $task->total,
                'transaction_date' => $task->supplier_pay_date ?? $task->issued_date ?? $task->created_at,
                'description' => 'Update For Whom to Pay: ' . $task->reference,
                'name' => $paymentMethodAccount->name,
                'type' => 'payable',
            ]);

            Log::info('Created journal entry for task ID: ' . $task->id . ' with transaction ID: ' . $transaction->id . ' and payment method account ID: ' . $payment_method_account_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Journal entries updated successfully.',
                'data' => [
                    'task_id' => $task->id,
                    'transaction_id' => $transaction->id,
                    'payment_method_account_id' => $payment_method_account_id,
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to create transaction or journal entry for task ID: ' . $task->id . '. Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create transaction or journal entry: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function handleTaskFromEmail(Request $request): JsonResponse
    {

        $request->validate([
            'email' => 'required|email',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt|max:2048',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'File received successfully.',
            'data' => [
                'email' => $request->email,
                'file_name' => $request->file->getClientOriginalName(),
                'file_size' => $request->file->getSize(),
            ]
        ], 200);
    }

    public function findAgent(Request $request) 
    {
        $phoneNumber = $request->input('data.fromNumber');

        if (!$phoneNumber) {
            return response()->json([
                'status' => 'error',
                'message' => 'Phone number is required',
            ]);
        }

        $agent = Agent::where('phone_number', $phoneNumber)->first();

        if ($agent) {
            return response()->json([
                'status' => 'success',
                'message' => "Phone number is within the database. Agent detected: " . $agent->name,
            ], 200);
        }
        
        Log::info("The phone number is not within the database. Supplier agent detected: " . $phoneNumber);

        return response()->json(array_merge(
            $request->all(),
                [
                    'status' => 'error',
                    'message' => 'Phone number is not within the database. Supplier agent detected',
                ]
        ), 200);
    }

    public function automationSupplier(Request $request) 
    {
        $request->validate([
            'phone_number' => 'required',
            'group_id' => 'required|string',
            'file_name' => 'required|string',
            'file' => 'required',
        ]);

        $phoneNumber = $request->phone_number;

        if ($phoneNumber) {
            $agent = Agent::where('phone_number', $phoneNumber)->first();

            if (!$agent) {
                Log::info("No agent found with the given phone number: " . $phoneNumber);
            }
        }

        $groupId = explode('@', $request->group_id)[0];

        $supplierCompany = SupplierCompany::where('group_id', $groupId)->first();
        if (!$supplierCompany) {
            Log::info("No supplier company found within the system database with group ID: " . $groupId);

            return response()->json([
                'status' => 'error', 
                'message' => 'No supplier found with such group ID', 
            ], 200);
        }

        Log::info("Received " . $groupId . " for group ID. Found supplier company record with the ID: " . $supplierCompany->id);

        $mergeableSupplier = [
            'Smile Holidays',
            'Heysam Group',
            'World Of Luxury',
        ];

        $supplierName = $supplierCompany->supplier->name;
        $isMergeableSupplier = in_array($supplierName, $mergeableSupplier);

        try {
            //Storing the file
            $storeTheFile = $this->fileStorage($request, $supplierCompany);

            $storeFileData = $storeTheFile->getData(true);

            if ($storeFileData['status'] === 'error') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to store the file: ' . $storeFileData['message'],
                ], 200);
            }
        
            $fileResponseData = $storeTheFile->getData(true);
            $supplierFilePath = $fileResponseData['supplier_file_path'];
            $filePath = $fileResponseData['file_path'];
            $userId = $agent ? $agent->user_id : 1;

            FileUpload::create([
                'file_name' => $request->file_name,
                'destination_path' => $filePath,
                'user_id' => $userId,
                'company_id' => $supplierCompany->company_id,
                'supplier_id' => $supplierCompany->supplier_id,
                'status' => $isMergeableSupplier ? 'pending' : 'completed',
                'source_files' => 'n8n',
            ]);

            Log::info('Successfully created file upload record for : ' . $request->file_name);

            if ($isMergeableSupplier) {
                $mergedFile = $this->mergingFiles($supplierCompany, $supplierFilePath, $filePath, $userId);   

                $mergedFileData = $mergedFile->getData(true);

                if ($mergedFileData['status'] === 'error') {
                    return response()->json([
                        'status' => 'warning',
                        'message' => $mergedFileData['message'],
                        'group_id' => $request->group_id,
                        'file_name' => $request->file_name,
                        'failed_files' => $mergedFileData['failed_files'] ?? [],
                    ], 200);
                }

                if ($mergedFileData['status'] === 'waiting') {
                    return response()->json([
                        'status' => 'success',
                        'message' => $mergedFileData['message'],
                        'group_id' => $request->group_id,
                        'supplier_name' => $supplierName,
                        'file_name' => $request->file_name,
                        'pending_count' => $mergedFileData['pending_count'] ?? 1,
                    ], 200);
                }

                if ($mergedFileData['status'] === 'success') {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Successfully merging files. Merged file named as ' . $mergedFileData['merged_file_name'],
                        'group_id' => $request->group_id,
                        'file_name' => $request->file_name,
                    ], 200);
                }
            }
         
            return response()->json([
                'status' => 'success',
                'message' => 'File stored successfully',
                'group_id' => $request->group_id,
                'file_name' => $request->file_name,
            ], 200); 

        } catch (Exception $e) {
            Log::info('Failed to merge files: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to merge files: ' . $e->getMessage(),
            ], 200);
        }
    }

    public function fileStorage(Request $request, $supplierCompany)
    {
        Log::info('Storing file for supplier company: ' . $supplierCompany->supplier->name);
        
        try {
            $pdf = $request->file('file');
            
            $companyName = strtolower(preg_replace('/[^A-Za-z0-9_\-]/', '_', $supplierCompany->company->name));
            $supplierName = strtolower(preg_replace('/[^A-Za-z0-9_\-]/', '_', $supplierCompany->supplier->name));
            
            $currentDate = now()->format('d-m-Y');                

            $supplierFilePath = storage_path("app/{$companyName}/{$supplierName}");
            $filePath = $supplierFilePath . '/resayil/' . $currentDate;

            if (!File::isDirectory($filePath)) {
                Log::error("Source directory: " . $filePath . " does not exist. Creating directory...");
                File::makeDirectory($filePath, 0755, true);
                Log::info("Created source directory: " . $filePath . ", please ensure files are uploaded correctly");
            }

            $pdf->move($filePath, $request->file_name);
            Log::info("Uploading file " . $request->file_name . " to " . $filePath);

            return response()->json([
                'status' => 'success', 
                'message' => 'File '. $request->file_name . ' stored successfully into ' . $filePath,
                'supplier_id' => $supplierCompany->supplier_id,
                'company_id' => $supplierCompany->company_id,
                'file_name' => $request->file_name,
                'supplier_file_path' => $supplierFilePath,
                'file_path' => $filePath,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to store file: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to store file: ' . $e->getMessage(),
            ], 200);
        }
    }

    public function mergingFiles($supplierCompany, $supplierFilePath, $filePath, $userId) 
    {
        $supplierPrefixMap = [
            'Smile Holidays' => 'SMIL',
            'Heysam Group' => 'HEYG',
            'World Of Luxury' => 'WLUX',
        ];

        if (File::isDirectory($filePath)) {

            $files = File::files($filePath);

            if (count($files) > 0) {
                Log::info('Found ' . count($files) . ' files in this directory, proceeding to merge process');

                try {
                    $fileRecords = FileUpload::where('supplier_id', $supplierCompany->supplier_id)
                        ->where('company_id', $supplierCompany->company_id)
                        ->where('user_id', $userId)
                        ->where('status', 'pending')
                        ->where('destination_path', $filePath) 
                        ->whereNull('merged_file_name')
                        ->get();

                    if ($fileRecords->count() > 0) {
                        Log::info('Found ' . $fileRecords->count() . ' file records that are not merged yet', [
                            'file_ids' => $fileRecords->pluck('id')->toArray(),
                            'file_names' => $fileRecords->pluck('file_name')->toArray(),
                        ]);

                        $merger = new Merger(new Fpdi2Driver());
                        $successFiles = [];
                        $successFileIds = [];  
                        $failedFiles = [];

                        foreach ($fileRecords as $fileRecord) {
                            $fullPath = $fileRecord->destination_path . '/' . $fileRecord->file_name;
                            
                            if (File::exists($fullPath)) {
                                try {
                                    $merger->addFile($fullPath);
                                    $successFiles[] = $fileRecord->file_name;
                                    $successFileIds[] = $fileRecord->id; 
                                    Log::info('Added file to merger: ' . $fileRecord->file_name);
                                } catch (\Throwable $e) {
                                    $failedFiles[] = $fileRecord->file_name;
                                    Log::error('Failed to add file to merger: ' . $fileRecord->file_name . ' - ' . $e->getMessage());
                                }
                            } else {
                                $failedFiles[] = $fileRecord->file_name;
                                Log::warning('File does not exist on disk: ' . $fullPath);
                            }
                        }

                        if (count($successFiles) < 2) {
                            Log::warning('Not enough valid files to merge. Need at least 2, got ' . count($successFiles));
                            return response()->json([
                                'status' => 'waiting',
                                'message' => 'Waiting for more files. Currently ' . $fileRecords->count() . ' file(s) pending',
                                'pending_count' => $fileRecords->count(),
                                'failed_files' => $failedFiles,
                            ], 200);
                        }

                        $prefix = $supplierPrefixMap[$supplierCompany->supplier->name];
                        $fileIds = implode('_', $successFileIds); 
                        $mergedFileName = sprintf('%s_%s_%s.pdf', $prefix, $fileIds, now()->format('ymdHis'));

                        $mergedBytes = $merger->merge();

                        $unprocessedPath = $supplierFilePath . '/files_unprocessed';
                        if (!File::isDirectory($unprocessedPath)) {
                            File::makeDirectory($unprocessedPath, 0755, true);
                        }
                        $mergedFilePath = $unprocessedPath . '/' . $mergedFileName;
                        File::put($mergedFilePath, $mergedBytes);                       

                        Log::info('Successfully merged ' . count($successFiles) . ' files into: ' . $mergedFileName);

                        foreach ($fileRecords as $fileRecord) {
                            if (in_array($fileRecord->file_name, $successFiles)) {
                                $fileRecord->update([
                                    'merged_file_name' => $mergedFileName,
                                    'status' => 'completed',
                                ]);
                            }
                        }

                        FileUpload::create([
                            'file_name' => $mergedFileName,
                            'destination_path' => $mergedFilePath,
                            'user_id' => $userId,
                            'company_id' => $supplierCompany->company_id,
                            'supplier_id' => $supplierCompany->supplier_id,
                            'status' => 'completed',
                            'source_files' => $successFiles,
                        ]);

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Successfully merged ' . count($successFiles) . ' files',
                            'merged_file_name' => $mergedFileName,
                            'source_files' => $successFiles,
                        ], 200);
                    } else {
                        Log::info('No unmerged file records found');
                    }
                } catch (Exception $e) {
                    Log::error('Failed to merge files: ' . $e->getMessage());
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Failed to merge files: ' . $e->getMessage(),
                    ], 200);
                }
            } else {
                Log::info('No files found in this directory to merge');
                return response()->json([
                    'status' => 'error',
                    'message' => 'No files found in this directory to merge',
                ], 200);
            }
        } else {
            Log::info('Directory does not exist: ' . $filePath);
            return response()->json([
                'status' => 'error',
                'message' => 'Directory does not exist: ' . $filePath,
            ], 200);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Merge supplier file processed',
        ], 200);
        
    }

    public function switchInvoiceTask(Request $request, Task $task): RedirectResponse
    {
        $user = Auth::user();

        Log::info('[TASK] Switch invoice task request', [
            'user_id' => $user->id,
            'user_role' => $user->role_id,
            'task_id' => $task->id,
            'task_reference' => $task->reference,
            'task_status' => $task->status,
            'original_task_id' => $task->original_task_id,
        ]);

        if (!$task->originalTask) {
            Log::warning('[TASK] Switch invoice failed - no original task', [
                'task_id' => $task->id,
            ]);
            return back()->with('error', 'This task does not have an original task linked.');
        }

        if ($task->status !== 'issued') {
            Log::warning('[TASK] Switch invoice failed - task not issued', [
                'task_id' => $task->id,
                'task_status' => $task->status,
            ]);
            return back()->with('error', 'Only issued tasks can be switched.');
        }

        if ($task->originalTask->status !== 'confirmed') {
            Log::warning('[TASK] Switch invoice failed - original task not confirmed', [
                'task_id' => $task->id,
                'original_task_id' => $task->originalTask->id,
                'original_task_status' => $task->originalTask->status,
            ]);
            return back()->with('error', 'Original task must be in confirmed status.');
        }

        $originalTask = $task->originalTask;
        $invoiceDetail = $originalTask->invoiceDetail;

        if (!$invoiceDetail) {
            Log::warning('[TASK] Switch invoice failed - no invoice on original task', [
                'task_id' => $task->id,
                'original_task_id' => $originalTask->id,
            ]);
            return back()->with('error', 'Original task does not have an invoice.');
        }

        if ($task->invoiceDetail) {
            Log::warning('[TASK] Switch invoice failed - task already has invoice', [
                'task_id' => $task->id,
                'existing_invoice_detail_id' => $task->invoiceDetail->id,
            ]);
            return back()->with('error', 'This task already has an invoice linked.');
        }

        try {
            DB::beginTransaction();

            $invoice = $invoiceDetail->invoice;
            $oldSupplierPrice = $invoiceDetail->supplier_price ?? 0;
            $newSupplierPrice = $task->total ?? 0;
            $taskPrice = $invoiceDetail->task_price ?? 0;
            $oldMarkup = $invoiceDetail->markup_price ?? 0;
            $newMarkup = $taskPrice - $newSupplierPrice;
            $oldProfit = $taskPrice - $oldSupplierPrice;
            $newProfit = $newMarkup;
            $isPaidInvoice = $invoice->status === 'paid';

            Log::info('[TASK] Switch invoice - updating invoice detail', [
                'invoice_detail_id' => $invoiceDetail->id,
                'invoice_number' => $invoice->invoice_number ?? null,
                'invoice_status' => $invoice->status,
                'old_task_id' => $originalTask->id,
                'new_task_id' => $task->id,
                'task_price' => $taskPrice,
                'old_supplier_price' => $oldSupplierPrice,
                'new_supplier_price' => $newSupplierPrice,
                'old_markup' => $oldMarkup,
                'new_markup' => $newMarkup,
                'old_profit' => $oldProfit,
                'new_profit' => $newProfit,
                'profit_change' => $newProfit - $oldProfit,
                'is_loss' => $newProfit < 0,
            ]);

            // Update InvoiceDetail with new task_id, supplier_price, and markup_price
            $invoiceDetail->update([
                'task_id' => $task->id,
                'supplier_price' => $newSupplierPrice,
                'markup_price' => $newMarkup,
            ]);

            // Update JournalEntry task_id if this invoice has payments
            $journalEntriesUpdated = 0;
            if ($isPaidInvoice) {
                $journalEntries = JournalEntry::where('invoice_detail_id', $invoiceDetail->id)
                    ->where('task_id', $originalTask->id)
                    ->get();

                foreach ($journalEntries as $journalEntry) {
                    Log::info('[TASK] Switch invoice - updating journal entry task_id', [
                        'journal_entry_id' => $journalEntry->id,
                        'old_task_id' => $journalEntry->task_id,
                        'new_task_id' => $task->id,
                    ]);

                    $journalEntry->update(['task_id' => $task->id]);
                    $journalEntriesUpdated++;
                }
            }

            DB::commit();

            Log::info('[TASK] Switch invoice success', [
                'invoice_detail_id' => $invoiceDetail->id,
                'invoice_number' => $invoice->invoice_number ?? null,
                'old_task_id' => $originalTask->id,
                'new_task_id' => $task->id,
                'user_id' => $user->id,
                'journal_entries_updated' => $journalEntriesUpdated,
                'profit_impact' => $newProfit - $oldProfit,
            ]);

            return back()->with('success', 'Invoice has been switched to the issued task successfully.');
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('[TASK] Switch invoice failed - exception', [
                'task_id' => $task->id,
                'original_task_id' => $originalTask->id,
                'invoice_detail_id' => $invoiceDetail->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to switch invoice: ' . $e->getMessage());
        }
    }
}
