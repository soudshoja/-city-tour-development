<?php

namespace App\Http\Controllers;
// testing
use App\AI\AIManager;
use App\Http\Traits\Converter;
use App\Http\Traits\CurrencyExchangeTrait;
use App\Http\Traits\NotificationTrait;
use Illuminate\Http\Request;
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
use App\Models\Branch;
use App\Models\Room;
use App\Models\TaskHotelDetail;
use App\Models\TaskInsuranceDetail;
use App\Models\TaskVisaDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\SupplierCompany;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use Illuminate\Support\Facades\Date;
use App\Models\FileUpload;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use iio\libmergepdf\Merger;
use iio\libmergepdf\Driver\Fpdi2Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

// use Carbon\Carbon;

class TaskController extends Controller
{
    use NotificationTrait, Converter, CurrencyExchangeTrait;

    public function index(Request $request)
    {
        $user = Auth::user();

        $defaultColumns = ['reference', 'bill-to', 'passenger-name', 'agent-name', 'price', 'status', 'info'];

        if ($user->role_id === Role::AGENT) {
            $defaultColumns = array_filter($defaultColumns, fn($col) => $col !== 'agent-name');
        }

        $visibleColumns = session('visible_task_columns', $defaultColumns);

        $sortBy = $request->query('sortBy', 'created_at');
        $sortOrder = $request->query('sortOrder', 'desc');

        $sortableColumns = ['issued_date', 'created_at'];
        if (!in_array($sortBy, $sortableColumns)) {
            $sortBy = 'created_at';
        }

        $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice', 'refundDetail', 'originalTask', 'linkedTask');
        $paymentMethod = Account::where('parent_id', 39)->get();

        if ($search = $request->query('q')) {

            $tasks = $tasks->where(function ($query) use ($search) {
                $query->where('reference', 'like', '%' . $search . '%')
                    ->orWhere('client_name', 'like', '%' . $search . '%')
                    ->orWhere('ticket_number', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhere('gds_reference', 'like', '%' . $search . '%')
                    ->orWhere('airline_reference', 'like', '%' . $search . '%')
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('agent', function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%')
                            ->orWhere('amadeus_id', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('phone_number', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('supplier', function ($q) use ($search) {
                        $q->where('name', 'like', '%' . $search . '%');
                    });
            });
        }
        if ($request->filled('status')) {
            $statuses = (array) $request->input('status');
            $tasks = $tasks->whereIn('status', $statuses);
        }
        $countries = Country::all();
        $suppliers = Supplier::with('companies');

        if ($user->role_id == Role::ADMIN) {
            $tasks = $tasks;
            $clients = Client::all();
            $agents = Agent::all();
        } elseif ($user->role_id == Role::COMPANY) {

            $branches = Branch::where('company_id', $user->company->id)->get();
            $agents = Agent::with('branch')->whereIn('branch_id', $branches->pluck('id'))->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
            $tasks = $tasks->where('company_id', $user->company->id);

            // $suppliers = Supplier::whereHas('companies', function ($query) use ($user) {
            //     $query->where('company_id', $user->company->id)->where('is_active', true);
            // })->get();

            $suppliers = $suppliers->whereHas('companies', function ($query) use ($user) {
                $query->where('company_id', $user->company->id); 
            });

        } elseif ($user->role_id == Role::BRANCH) {
            $agents = Agent::with('branch')->where('branch_id', $user->branch_id)->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
            $tasks = $tasks->whereIn('agent_id', $agentsId)->where('company_id', $user->company_id);

            $suppliers = $suppliers->whereHas('companies', function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            });

        } elseif ($user->role_id == Role::AGENT) {

            $agents = Agent::with('branch')->where('id', $user->agent->id)->get();
            $clients = Client::where('agent_id', $user->agent->id)->get();

            // Get tasks assigned to this agent OR unassigned tasks in the same company
            $tasks = $tasks->where(function ($query) use ($user) {
                $query->where('agent_id', $user->agent->id)
                    ->orWhere(function ($subQuery) use ($user) {
                        $subQuery->whereNull('agent_id')
                            ->where('company_id', $user->agent->branch->company_id);
                    });
            })->where('company_id', $user->agent->branch->company_id);

            $companyId = $user->agent->branch->company_id;
            $suppliers = $suppliers->whereHas('companies', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
        
        } else {
            return redirect()->back()->with('error', 'User not authorized to view tasks.');
        }

        $suppliers = $suppliers->whereHas('companies', function ($query){  
            $query->where('is_active', true);
        })->get();

        $taskCount = $tasks->count();
        $tasks = $tasks->orderBy($sortBy, $sortOrder)
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
            'suppliers',
            'types',
            'countries',
            'paymentMethod',
            'visibleColumns',
            // 'searchTask'
        ));
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
            foreach ($taskIds as $id) {
                $task = Task::find($id);
                if ($task) {
                    if ($clientId) {
                        $task->client_id = $clientId;
                        $client = Client::find($clientId);
                        if ($client) {
                            $task->client_name = trim($client->first_name . ' ' . ($client->middle_name ?? '') . ' ' . ($client->last_name ?? ''));
                        }
                    }
                    if ($agentId) $task->agent_id = $agentId;
                    if ($paymentMethodId) $task->payment_method_account_id = $paymentMethodId;
                    
                    if ($task->is_complete && $task->agent && $task->client) {
                        $journalEntries = JournalEntry::where('task_id', $task->id)->exists();
                        if (!$journalEntries) {
                            try {
                                $this->processTaskFinancial($task);
                                $task->enabled = true;
                            } catch (\Exception $e) {
                                Log::error('Failed to process task financial: ' . $e->getMessage());
                            }
                        }
                        if($task->status === 'issued') $task->enabled = true;
                    }
                    $task->save();
                }
            }
        });

        return response()->json(['success' => true]);
    }

    /**
     * Save the user’s task-list column visibility settings in session
     */
    public function saveColumnPrefs(Request $request)
    {
        $validated = $request->validate([
            'columns' => 'required|array'
        ]);

        $request->session()->put('visible_task_columns', $validated['columns']);

        return response()->json(['success', 'message' => 'Column preferences saved.']);
    }

    public function store(Request $request) : JsonResponse
    {
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
            'gds_reference' => 'nullable|string',
            'airline_reference' => 'nullable|string',
            'created_by' => 'nullable|string',
            'issued_by' => 'nullable|string',
            'status' => 'required|string',
            'supplier_status' => 'nullable|string',
            'price' => 'nullable|numeric',
            'exchange_currency' => 'nullable|string',
            'original_price' => 'nullable|numeric',
            'original_currency' => 'nullable|string',
            'total' => 'nullable|numeric',
            'original_tax' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
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

        if($request->exchange_currency !== 'KWD'){
            $request->merge([
                'exchange_currency' => 'KWD',
                'original_currency' => $request->exchange_currency,
                'original_price' => $request->total > $request->price ? $request->total : $request->price,
            ]);
        }

        $amadeus = Supplier::where('name', 'Amadeus')->first();

        $exceptionConvert = [];

        if($amadeus) $exceptionConvert[] = $amadeus->id;

        if ( !in_array($request->supplier_id, $exceptionConvert) && $request->original_currency && $request->original_price && !$request->is_exchanged) {

            $companyId = $request->company_id;
            $originalCurrency = $request->original_currency;
            $exchangeCurrency = $request->exchange_currency;
            $originalPrice = $request->original_price;

            try {
                $convertResponse = $this->convert(
                    $companyId,
                    $originalCurrency,
                    $exchangeCurrency,
                    $originalPrice
                );

                if($convertResponse['status'] === 'error' || $convertResponse['exchange_rate'] === null) {
                    $currencyExchangeController = new CurrencyExchangeController();


                    $currencyExchangeResponse = $currencyExchangeController->storeProcess(new Request([
                        'company_id' => $companyId,
                        'base_currency' => $originalCurrency,
                        'exchange_currency' => $exchangeCurrency,
                        'is_manual' => false,
                    ]));

                    if(!$currencyExchangeResponse instanceof JsonResponse){
                        Log::error('Response from updateJournalPaymentMethod is not a JsonResponse', [
                            'expected_type' => JsonResponse::class,
                            'actual_type' => is_object($currencyExchangeResponse) ? get_class($currencyExchangeResponse) : gettype($currencyExchangeResponse)
                        ]);

                        throw new Exception('Failed to update payment method journal entries.');
                    }

                    $currencyExchangeResponse = $currencyExchangeResponse->getData(true);

                    if($currencyExchangeResponse['status'] === 'error') {
                        Log::error('Failed to create currency exchange', [
                            'error' => $currencyExchangeResponse['message'],
                            'company_id' => $companyId,
                            'base_currency' => $originalCurrency,
                            'exchange_currency' => $exchangeCurrency,
                        ]);

                        return response()->json([
                            'status' => 'error',
                            'message' => 'Failed to create currency exchange: ' . $currencyExchangeResponse['message'],
                        ], 500);
                    }

                    $convertResponse = $this->convert(
                        $companyId,
                        $originalCurrency,
                        $exchangeCurrency,
                        $originalPrice
                    );

                    if($convertResponse['status'] === 'error') {
                        Log::error('Failed to convert currency after creating exchange rate', [
                            'error' => $convertResponse['message'],
                            'company_id' => $companyId,
                            'original_currency' => $originalCurrency,
                            'exchange_currency' => $exchangeCurrency,
                            'original_price' => $originalPrice
                        ]);

                        return response()->json([
                            'status' => 'error',
                            'message' => 'Failed to convert currency after creating exchange rate: ' . $convertResponse['message'],
                        ], 500);
                    }
                }

                $price = $convertResponse['converted_amount'];
                $exchangeRate = $convertResponse['exchange_rate'];

                $request->merge([
                    'price' => $price,
                    'total' => $price,
                    'exchange_rate' => $exchangeRate,
                ]);

                $map = ['tax' => 'original_tax', 'surcharge' => 'original_surcharge'];
                foreach ($map as $dst => $src) {
                    $base = $request->input($src, $request->input($dst));
                    if ($base === null || $base === '') {
                        continue;
                    }
            
                    $resp = $this->convert($companyId, $originalCurrency, $exchangeCurrency, $base);
            
                    if (($resp['status'] ?? 'success') !== 'error' && isset($resp['converted_amount'])) {
                        $request->merge([$dst => round($resp['converted_amount'], 3)]);
                    }
                }
            } catch (Exception $e) {
                Log::error('Currency conversion failed: ' . $e->getMessage(), [
                    'original_currency' => $request->original_currency,
                    'exchange_currency' => $request->exchange_currency,
                    'original_price' => $request->original_price
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Currency conversion failed: ' . $e->getMessage(),
                ], 500);
            }
        }

        $queryChkExistTask = Task::query();
        $queryChkExistTask->where('reference', $request->reference)
            ->where('company_id', $request->company_id)
            ->where('client_id', $request->client_id)
            ->where('client_name', $request->client_name) // same reference name but different client name considered as different task
            ->where('status', $request->status);

        if ($request->supplier_id) {
            $queryChkExistTask->where('supplier_id', $request->supplier_id);
        }

        $existingTask = null;
        if ($request->type === 'hotel') {
            $hotelName = data_get($request->task_hotel_details, '0.hotel_name');
            $roomType  = data_get($request->task_hotel_details, '0.room_type');
            $checkIn   = data_get($request->task_hotel_details, '0.check_in');
            $checkOut  = data_get($request->task_hotel_details, '0.check_out');

            $checkIn  = $checkIn  ? Carbon::parse($checkIn)->toDateString()  : null;
            $checkOut = $checkOut ? Carbon::parse($checkOut)->toDateString() : null;

            $existingTask = (clone $queryChkExistTask)
                ->whereHas('hotelDetails', function ($q) use ($checkIn, $checkOut, $hotelName, $roomType) {
                    if (!empty($hotelName)) {
                        $q->whereHas('hotel', function ($qh) use ($hotelName) {
                            $qh->where('name', 'LIKE', $hotelName);
                        });
                    }
                    if (!empty($checkIn)) {
                        $q->whereDate('check_in', $checkIn);
                    }
                    if (!empty($checkOut)) {
                        $q->whereDate('check_out', $checkOut);
                    }
                    if (!empty($roomType)) {
                        $q->where('room_type', $roomType);
                    }   
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
        }

        if ($existingTask) {
            if ($existingTask->total != $request->total && $existingTask->status == 'issued' && $existingTask->supplier->name === 'Jazeera Airways') {
                Log::warning('This reference has already existed for task: ' . $existingTask->reference . '. Proceeding for Reissued task.');

                $newTaskTotal = (float)$request->total - (float)$existingTask->total;

                $request->merge([
                    'total' => $newTaskTotal,
                    'status' => 'reissued',
                ]);
            } elseif ($existingTask->gds_reference == null || $existingTask->airline_reference == null) {
                $existingTask->gds_reference = $request->gds_reference;
                $existingTask->airline_reference = $request->airline_reference;
                $existingTask->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Task updated with GDS and Airline reference.',
                    'data' => $existingTask,
                ], 200);
            } else {

                $existingTask->issued_date = $request->issued_date;
                $existingTask->save();
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

        if(strtolower($supplierName) == 'jazeera airways' || strtolower($supplierName) == 'fly dubai' || strtolower($supplierName) == 'vfs') {
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

        // Handle original task for non-issued statuses
        if (in_array($request->status, ['reissued', 'refund', 'void', 'emd'])) {
            $originalTask = Task::where('reference', $request->reference)
                ->where('company_id', $request->company_id)
                ->where('status', 'issued')
                ->first();

            if ($originalTask) {
                $request->merge(['original_task_id' => $originalTask->id]);
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

            if ($task->type === 'hotel' && $request->has('task_hotel_details') && !empty($request->task_hotel_details)) {
                $this->saveHotelDetails($request->task_hotel_details, $task->id);
            } elseif ($task->type === 'flight' && $request->has('task_flight_details') && !empty($request->task_flight_details)) {
                $this->saveFlightDetails($request->task_flight_details, $task->id);
            } elseif ($task->type === 'insurance' && $request->has('task_insurance_details') && !empty($request->task_insurance_details)) {
                $this->saveInsuranceDetails($request->task_insurance_details, $task->id);
            } elseif ($task->type === 'visa' && $request->has('task_visa_details') && !empty($request->task_visa_details)) {
                $this->saveVisaDetails($request->task_visa_details, $task->id);
            }
           
            // Set enabled status: task must be complete AND have an agent assigned
            if($task->is_complete && $task->agent_id && $task->client) {
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
            $shouldProcessFinancials = $offline || $task->is_complete ||
            ($task->status === 'void' && $task->original_task_id);
            
            if ($shouldProcessFinancials) {
                $reason = $task->is_complete ? 'complete task' : 'void task with original_task_id';
                Log::info("Processing financial transactions for {$reason}: " . $task->reference . ' (agent_id: ' . ($task->agent_id ?? 'none') . ')');
                $this->processTaskFinancial($task);
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

    private function triggerCheckTaskEvent(Task $task, string $reason = 'manual_trigger'){
        // Trigger the check status event for the task
        event(new \App\Events\CheckConfirmedOrIssuedTask($task, $reason));
        
        Log::info("Triggered CheckConfirmedOrIssuedTask event", [
            'task_id' => $task->id,
            'reference' => $task->reference,
            'status' => $task->status,
            'reason' => $reason
        ]);
    }

    /**
     * Get or create a currency-specific child account for the supplier
     */
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

    /**
     * Get branch ID for task financial processing
     * Returns agent's branch_id if agent exists, otherwise returns company's main branch_id
     */
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

    /**
     * Process all financial transactions for a task
     */
    public function processTaskFinancial(Task $task)
    {
        if(!($task->status == 'issued' || $task->status == 'reissued' || $task->status == 'void' || $task->status == 'refund' || $task->status == 'emd')) {
            Log::info('Skipping financial processing for task: ' . $task->reference . ' - status: ' . $task->status);
            return;
        }
        Log::info('Processing financial for task: ' . $task->reference);
        
        // Special handling for void tasks: they should process even if incomplete
        // as long as they have an original_task_id to reference
        if (!$task->is_complete) {
            if ($task->status === 'void' && $task->original_task_id) {
                Log::info('Allowing incomplete void task to process financials: ' . $task->reference . ' (has original_task_id: ' . $task->original_task_id . ')');
            } else {
                throw new Exception('Task is not complete. Missing required fields: ' . $this->getMissingFields($task));
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
        if($task->type == 'hotel' && !$isJazeera) {
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

    /**
     * Get missing required fields with custom error messages
     */
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
            if (empty($task->$column)) {
                // Use custom message if available, otherwise use default format
                $message = $fieldMessages[$column] ?? ucfirst(str_replace('_', ' ', $column)) . ' is required';
                $missingFields[] = $message;
            }
        }

        return implode(', ', $missingFields);
    }

    /**
     * Process issued task financials
     */
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

    /**
     * Process void task financials
     */
    private function processVoidTask(Task $task, $branchId)
    {
        Log::info('Check for invoice created for this task.');

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
            $this->ReverseUnpaidVoidedTask($originalTask);
        }
    }

    /**
     * Process refund task financials
     */
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

        if ($task->type == 'flight' ) {
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
            'description' => 'Refund Task - Supplier cost return (Expenses): ' . $supplierCost->name,
            'debit' => 0,
            'credit' => $task->total, // Always use converted amount for expense account
            'name' => $supplier->name,
            'type' => 'refund',
        ]);
    }

    public function toggleStatus(Request $request, Task $task)
    {
        $task->enabled = $request->is_enabled;

        if ($task->enabled) {
            if (!$task->is_complete) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task is not complete. Missing required fields: ' . $this->getMissingFields($task)
                ], 400);
            }

            if(!$task->agent_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task must have an agent assigned to be enabled.'
                ], 400);
            }

            if($task->client == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task must have a client assigned to be enabled.'
                ], 400);
            }

            $journalEntries = JournalEntry::where('task_id', $task->id)->exists();

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

    public function voidTask(Task $task, Task $issuedTask, Payment $payment)
    {
        $client = Client::find($payment->client_id);

        if (!$client) {
            throw new \Exception("Client not found for payment ID: {$payment->id}");
            Log::warning("Client not found for payment [{$payment->id}] during void refund.");
        }

        $oldCredit = $client->credit;

        $client->credit += $payment->amount;
        $client->save();

        Log::info("Void for task [{$task->reference}]: Client credit before = {$oldCredit}, after = {$client->credit}");

        // Use task's issued_date as transaction_date
        $transactionDate = $task->supplier_pay_date ? Carbon::parse($task->supplier_pay_date) : Carbon::now();

        $voidTransaction = Transaction::create([
            'branch_id'        => $client->agent->branch_id,
            'company_id'       => $client->agent->branch->company_id,
            'entity_id'        => $client->id,
            'entity_type'      => 'client',
            'transaction_type' => 'debit',
            'amount'           => $payment->amount,
            'description'      => 'Void task: ' . $task->reference,
            'reference_type'   => 'Refund',
            'reference_number' => $payment->voucher_number,
            'transaction_date' => $transactionDate,
        ]);

        if (!$voidTransaction) {
            throw new \Exception("Failed to create refund transaction.");
        }

        $entries = JournalEntry::whereHas('invoiceDetail', function ($query) use ($task) {
            $query->where('task_description', $task->reference);
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

        Log::info('Voided task refunded and reversed: ' . $task->reference);

        DB::commit();
        return response()->json([
            'status' => 'success',
            'message' => 'Paid void task reversal journal completed.',
            'data' => $issuedTask,
        ], 201);
    }

    public function show($id)
    {
        $task = Task::with(['agent.branch', 'client', 'flightDetails.countryFrom',  'flightDetails.countryTo', 'hotelDetails.hotel', 'supplier'])->withoutGlobalScope('enabled')->findOrFail($id);

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        if ($task->flightDetails) {
            $task['country_from'] = $task->flightDetails->countryFrom->name;
            $task['country_to'] = $task->flightDetails->countryTo->name;
            $task['description'] = $task['country_from'] . ' ---> ' . $task['country_to'];
        } elseif ($task->hotelDetails) {
            $task['hotel_name'] = $task->hotelDetails->hotel->name;
            $task['hotel_country'] = $task->hotelDetails->hotel->country;
            $task['description'] = $task['hotel_name'] . '/' . $task['hotel_country'];
        } else {
            $task['description'] = 'No description';
        }


        // Return the task data as JSON for the modal to load dynamically
        return response()->json($task, 200);
    }

    public function edit($id)
    {
        // Include both 'agent' and 'client' in the query
        $task = Task::with(['agent', 'client'])->findOrFail($id);
        return view('tasks.update', compact('task'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
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
        ], [
            'supplier_id.required' => 'Please select a supplier',
            'status.required' => 'Please select a status',
            'total.required' => 'Please enter the total amount',
        ]);

        if (strtolower($request->status) !== 'issued' && strtolower($request->status) !== 'confirmed' && !$request->filled('original_task_id')) {
            return back()->withErrors(['original_task_id' => 'Task must be linked to an original task'])->withInput();
        }

        DB::beginTransaction();

        try {
            $task = Task::findOrFail($id);
            $oldPaymentMethod = $task->payment_method_account_id;

            Log::info('Before task detail update: agent_id: ' . $task->agent_id . ', client_id: ' . $task->client_id);
            Log::info('Incoming Request: agent_id: ' . $request->agent_id . ', client_id: ' . $request->client_id);

            $prevClientName = $task->client_name;
            $prevAgentId = $task->agent_id;
            $wasEnabled = JournalEntry::where('task_id', $task->id)->exists();

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
                $data['client_name'] = trim(implode(' ', array_filter([$client->first_name ?? '', $client->middle_name ?? '', $client->last_name ?? ''])));
            }

            if ($request->filled('agent_id')) {
                $agent = Agent::findOrFail($request->agent_id);
                $data['agent_id'] = $agent->id;
                $data['agent_name'] = $agent->name;
            }

            $task->update($data);
            Log::info('After task detail update: agent_id: ' . $task->agent_id . ', client_id: ' . $task->client_id);

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

                if($response->getData(true)['status'] !== 'success') {
                    Log::error('Failed to update payment method journal entries', [
                        'task_id' => $task->id,
                        'error_message' => $response->getData()->message
                    ]);
                    throw new Exception('Failed to update payment method journal entries: ' . $response->getData()->message);
                }
            }

            $clientChanged = $task->wasChanged('client_id');
            $agentWasAssigned = !$prevAgentId && $task->agent_id;
            $agentWasChanged = $prevAgentId && $task->agent_id && $prevAgentId !== $task->agent_id;

            // Update enabled status: task must be complete AND have an agent assigned
            $shouldBeEnabled = $task->is_complete && $task->agent_id;

            if ($shouldBeEnabled && !$wasEnabled) {
                $task->enabled = true;
                $task->save();
                // Process financials if not already processed
                if (!JournalEntry::where('task_id', $task->id)->exists()) {
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

            $transaction = Transaction::with('journalEntries')
                ->where('description', 'like', '%' . $task->reference . '%')
                ->first();

            if ($transaction) {
                $transaction->amount = $task->total;
                $transaction->save();
            
                foreach ($transaction->journalEntries as $entry) {
                    if ($entry->debit > 0) {
                        $entry->debit   = $task->total;
                        $entry->balance = $task->total;
                    } else {
                        $entry->credit  = $task->total;
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
                        $journalEntry->name = $client->first_name;
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

        if(!$company) {
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
            'batch_names'   => ['nullable','array'],
            'batch_names.*' => [ 'nullable','string','max:120',
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
                        ->get(['file_name','source_files','user_id']);

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
                Log::error('TBO batch merge failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
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

            if($existingFileUpload->exists()) {
                Log::info("File {$fileName} already exists for supplier {$supplier->name}, in company {$company->name}. Skipping upload.");

                $userUpload = $existingFileUpload->first()->user;

                if ($userUpload->id !== $user->id) {
                
                    if($userUpload->company !== null){
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

        if($success){
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

    public function fileToTask() {}
    /**
     * Get all tasks for a specific agent
     * @param $agentId
     * @return array
     */
    public function getTasks($agentId)
    {
        // get tasks that doesnt have invoice only
        $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')->whereDoesntHave('invoiceDetail')->where('agent_id', $agentId)->get();

        return response()->json($tasks);
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
                'task_id' => $taskId
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
                try {
                    $hotel = Hotel::create([
                        'name' => $data['hotel_name'],
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to create hotel: ' . $e->getMessage());
                    throw new Exception('Failed to create hotel: ' . $e->getMessage());
                }
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

            $agentInDB = Agent::where('name', $agent['name'])
                ->orWhere('email', 'like', $agent['email'])
                ->orWhere('phone_number', 'like', $agent['telephone'])
                ->first();

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

        if (isset($reservation['service']['cancellationPolicy'])) {
            //logger('Cancellation Policy: ', $reservation['service']['cancellationPolicy']);

            foreach ($reservation['service']['cancellationPolicy']['policies'] as $policy) {
                $cancellationPolicy[] = [
                    'type' => $policy['type'],
                    'charge' => $policy['charge'] !== null ? $policy['charge']['value'] : null,
                ];
            }

            $cancellationDate = $reservation['service']['cancellationPolicy']['date'];

            if ($cancellationDate) {
                $cancellationDate = Carbon::parse($cancellationDate)->toDateTimeString();

                if (Date::now()->greaterThanOrEqualTo($cancellationDate)) {
                    $status = 'issued';
                } else {
                    $status = 'confirmed';
                }
            } else {
                throw new Exception('Cancellation date not found in reservation data');
            }
        } else {
            throw new Exception('Cancellation policy not found in reservation data');
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

            $taskData = [
                'client_id' => null,
                'agent_id' => $agentId,
                'company_id' => $companyId,
                'type' => 'hotel',
                'status' => $status,
                'supplier_status' => $reservation['service']['status'],
                'client_name' => $clientName,
                'reference' => (string)$reservation['id'] ?? null,
                'duration' => $serviceDates['duration'] ?? null,
                'payment_type' => $reservation['service']['payment']['type'] ?? null,
                'price' => $prices['issue']['selling']['value'] ?? null,
                'tax' => 0.00,
                'surcharge' => 0.00,
                'total' => $prices['total']['selling']['value'] ?? null,
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
                    'room_type' => $room['type'] ?? null,
                    'room_amount' => count($room['passengers'] ?? []),
                    'room_details' => json_encode($room) ?? null,
                    'rate' => $price['issue']['selling']['value'] ?? null,
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
            $request->merge([
                'company_id' => $companyId,
            ]);

            $existingTask = Task::where('reference', $taskData['reference'])
                ->where('agent_id', $taskData['agent_id'])
                ->where('supplier_id', $taskData['supplier_id'])
                ->first();

            if ($existingTask) {
                if ($existingTask->supplier_status !== $taskData['supplier_status']) {
                    $existingTask->supplier_status = $taskData['supplier_status'];
                    $existingTask->status = $taskData['status'];
                    $existingTask->save();
                    Log::channel('magic_holidays')->info('Updated existing task: ' . $existingTask->reference);

                    $processResult['success'][] = [
                        'reference' => $existingTask->reference,
                        'message' => 'Task already exists, updated status',
                    ];

                    continue; // Skip creating a new task if it already exists but update the status
                } else {
                    Log::channel('magic_holidays')->info('Existing task already exists: ' . $existingTask->reference);

                    $processResult['success'][] = [
                        'reference' => $existingTask->reference,
                        'message' => 'Task already exists, no changes made',
                    ];

                    continue; // Skip creating a new task if it already exists
                }
            } else {
                $response = $this->store($request);
            }

            $response = json_decode($response->getContent(), true);
            logger('Task created: ', $response);

            if ($response['status'] == 'error') {
                Log::channel('magic_holidays')->error('Error creating task: ' . $response['message']);

                $processResult['failed'][] = [
                    'reference' => $taskData['reference'],
                    'message' => 'Error creating task: ' . $response['message'],
                ];
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

        switch ($supplier->name) {
            case 'Magic Holiday':

                if (!$request->supplier_ref) {
                    return redirect()->back()->with('error', 'Supplier reference is required for Magic Holiday');
                }

                $response = $supplierController->getMagicHoliday($request->supplier_ref);
                
                if(!$response instanceof \Illuminate\Http\JsonResponse){
                    Log::channel('magic_holidays')->error('Invalid response from Magic Holiday API',[
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
                        return redirect()->back()->with('error', $response['message']);
                    }

                    $supplierController->magicReserveWebhook($data['id']);
                }

                return redirect()->back()->with('success', 'Magic Holiday task received successfully');

            default:
                $responses = $this->upload($request);
                // Artisan::call('app:process-files', [], null, true);
                $redirectResponse = redirect()->back();

                foreach($responses as $response) {
                    if ($response['status'] == 'success') {
                        $redirectResponse = $redirectResponse->with('success', $response['message']); 
                    }

                    if($response['status'] == 'error') {
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
        $invoiceTask = Task::with('hotelDetails.hotel', 'hotelDetails.room', 'hotelDetails.hotel.country', 'agent', 'client')->findOrFail($taskId);

        if ($invoiceTask->reference) {
            $tasks = Task::with(['agent','client'])
                ->where('reference', $invoiceTask->reference)
                ->get();
    
            if ($tasks->isEmpty()) {
                $tasks = collect([$invoiceTask]);
            }
        } else {
            $tasks = collect([$invoiceTask]);
        }

        $hotelDetails = $invoiceTask->hotelDetails()->get();

        return view('tasks.pdf.hotel', compact('tasks', 'hotelDetails'));
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

    public function ReverseUnpaidVoidedTask(Task $originalTask)
    {

        $liabilities = Account::where('name', 'like', '%Liabilities%')
            ->where('company_id', $originalTask->company_id)
            ->first();

        $expenses = Account::where('name', 'like', '%Expenses%')
            ->where('company_id', $originalTask->company_id)
            ->first();

        $supplier = Supplier::find($originalTask->supplier_id);
        $supplierCompany = SupplierCompany::where('supplier_id', $originalTask->supplier_id)
            ->where('company_id', $originalTask->company_id)
            ->first();

        $supplierPayable = Account::where('name', $supplier->name)
            ->where('company_id', $originalTask->company_id)
            ->where('root_id', $liabilities->id)
            ->first();

        $companyIssuedBy = $originalTask->issued_by;

        if (!$companyIssuedBy) {
            Log::error('Company issued by not found for task ID: ' . $originalTask->id);
            throw new Exception('Company issued by not found.');
        }

        $issuedByAccount = Account::where('name', $companyIssuedBy)
            ->where('company_id', $originalTask->company_id)
            ->where('root_id', $liabilities->id)
            ->first();

        if (!$issuedByAccount) {
            Log::error('Issued by account not found for task ID: ' . $originalTask->id);
            throw new Exception('Issued by account not found.');
        }
        $supplierCost = Account::where('name', $supplier->name)
            ->where('company_id', $originalTask->company_id)
            ->where('root_id', $expenses->id)
            ->first();

        if (!$supplierPayable || !$supplierCost) {
            Log::error('Missing required accounts for reversal.', [
                'payable' => $supplierPayable,
                'cost' => $supplierCost
            ]);
            throw new Exception('Missing required accounts for reversal.');
        }

        Log::info('Recording reversal journal & transaction for task ID: ' . $originalTask->id);

        // Use task's issued_date as transaction_date
        $transactionDate = $originalTask->supplier_pay_date ? Carbon::parse($originalTask->supplier_date) : Carbon::now();

        $transaction = Transaction::create([
            'branch_id' => $originalTask->agent->branch_id,
            'company_id' => $originalTask->company_id,
            'entity_id' => $originalTask->company_id,
            'entity_type' => 'company',
            'transaction_type' => 'debit',
            'amount' => $originalTask->total,
            'task_id' => $originalTask->id,
            'description' => 'Void reversal for: ' . $originalTask->reference,
            'reference_type' => 'Payment',
            'transaction_date' => $transactionDate,
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $originalTask->company_id,
            'branch_id' => $originalTask->agent->branch_id,
            'account_id' => $supplierCost->id,
            'task_id' => $originalTask->id,
            'transaction_date' => $transactionDate,
            'description' => 'Reversal: Cancelled Cost from ' . $supplierCompany->supplier->name,
            'name' => $supplierCompany->supplier->name,
            'debit' => 0,
            'credit' => $originalTask->total,
            'balance' => $originalTask->total,
            'type' => 'payable',
        ]);

        JournalEntry::create([
            'transaction_id' => $transaction->id,
            'company_id' => $originalTask->company_id,
            'branch_id' => $originalTask->agent->branch_id,
            'account_id' => $issuedByAccount->id,
            'task_id' => $originalTask->id,
            'transaction_date' => $transactionDate,
            'description' => 'Reversal: Cancelled Payable to ' . $supplierCompany->supplier->name,
            'name' => $supplierCompany->supplier->name,
            'debit' => $originalTask->total,
            'credit' => 0,
            'balance' => $originalTask->total,
            'type' => 'payable',
        ]);

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
    
    public function updateJournalPaymentMethod(Task $task, int $payment_method_account_id) : JsonResponse
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

        $journalEntries = JournalEntry::where('task_id', $task->id)
            ->where('branch_id', $branchId)
            ->whereHas('account' , function ($query) use ($liabilities) {
                $query->where('root_id', $liabilities->id);
            })
            ->get();

        if ($journalEntries->isEmpty()) {
            Log::error('No existing journal entries found for task ID: ' . $task->id . ' with liabilities root ID: ' . $liabilities->id);
            return response()->json([
                'status' => 'error',
                'message' => 'No existing journal entries found for this task.',
            ], 404);
        }

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

        $journalEntriesWithCreditorsChild = $journalEntries->filter(function ($journalEntry) use ($creditorsAccount) {
            $account = $journalEntry->account;
            return $account && $account->parent_id === $creditorsAccount->id;
        });

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

        Log::info('Found ' . $journalEntries->count() . ' journal entries for task ID: ' . $task->id);

        try{
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
                'transaction_date' => $task->issued_date,
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
                'transaction_date' => $task->issued_date,
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

    public function handleTaskFromEmail(Request $request) : JsonResponse {

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

}
