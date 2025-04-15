<?php

namespace App\Http\Controllers;

use App\AIService;
use App\Http\Traits\Converter;
use App\Http\Traits\NotificationTrait;
use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\Item;
use App\Models\Agent;
use App\Models\TaskFlightDetail;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TasksImport;
use App\Models\Airline;
use App\Models\Client;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Branch;
use App\Models\Room;
use App\Models\TaskHotelDetail;
use App\Services\TextFileProcessor;
use Barryvdh\DomPDF\Facade\Pdf;
use ConvertApi\ConvertApi;
use Exception;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Models\Suppliers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\SupplierCompany;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

// use Carbon\Carbon;





class TaskController extends Controller
{
    use NotificationTrait, Converter;


    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'type' => 'required|string',
            'supplier_id' => 'required|exists:suppliers,id',
            'reference' => 'required|string',
            'price' => 'nullable|numeric',
            'total' => 'nullable|numeric',
            'tax' => 'nullable|numeric',
            'client_name' => 'nullable|string',
            'agent_id' => 'nullable|exists:agents,id',
            'client_id' => 'nullable|exists:clients,id',
            'additional_info' => 'nullable|string',
            'enabled' => 'required|boolean',
            'task_hotel_details' => 'required_if:task_flight_details,null|array|nullable',
            'task_flight_details' => 'required_if:task_hotel_details,null|array|nullable',
        ]);

        $existingTask = Task::where('reference', $validatedData['reference'])
            ->where('supplier_id', $validatedData['supplier_id'])
            ->where('company_id', auth()->user()->company->id ?? null)
            ->first();
        
        if ($existingTask) {
            return response()->json([
                'status' => 'error',
                'message' => 'Task with this reference already exists.',
            ], 422);
        }

        DB::beginTransaction(); 

        try {
            
            $taskData = array_merge($validatedData, [
                'company_id' => auth()->user()->company->id ?? null,
            ]);
            $task = Task::create($taskData);
            if ($task->type === 'hotel' && $request->has('task_hotel_details')) {
                $this->saveHotelDetails($request->task_hotel_details, $task->id);
            } elseif ($task->type === 'flight' && $request->has('task_flight_details')) {
                $this->saveFlightDetails($request->task_flight_details, $task->id);
            } else {
                throw new Exception('Invalid task type or missing details.');
            }


            $supplierCompany = SupplierCompany::with('account')
                ->where('supplier_id', $task->supplier_id)
                ->where('company_id', $task->company_id)
                ->first();

            if (!$supplierCompany) {
                throw new Exception('Supplier company not activated or not found.');
            }

            if (!$supplierCompany->account) {
                throw new Exception('Supplier account not found.');
            }

            $receivableAccount = Account::where('name', 'like', '%Receivable%')
                ->where('company_id', $task->company_id)
                ->first();

            $payableFallback = Account::where('name', 'Accounts Payable')
                ->where('company_id', $task->company_id)
                ->first();

            if (!$receivableAccount) {
                throw new Exception('Receivable account not found.');
            }

            $payableAccountId = $supplierCompany->account->id ?? $payableFallback->id;

            if (!$payableAccountId) {
                throw new Exception('No valid payable account found.');
            }

            $transaction = Transaction::create([
                'branch_id' => $task->agent->branch_id ?? null,
                'company_id' => $task->company_id,
                'entity_id' => $task->company_id,
                'entity_type' => 'company',
                'transaction_type' => 'credit',
                'amount' => $task->total,
                'date' => Carbon::now(),
                'description' => 'Task created: ' . $task->reference,
                'reference_type' => 'Payment',
                'task_id' => $task->id,
            ]);

            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'company_id' => $task->company_id,
                'branch_id' => auth()->user()->branch->id ?? null,
                'account_id' => $payableAccountId,
                'task_id' => $task->id,
                'transaction_date' => Carbon::now(),
                'description' => 'Records Payable to: ' . $supplierCompany->supplier->name,
                'name' => $supplierCompany->supplier->name,
                'debit' => 0,
                'credit' => $task->total,
                'balance' => $task->total,
                'type' => 'payable',
            ]);

            JournalEntry::create([
                'transaction_id' => $transaction->id,
                'company_id' => $task->company_id,
                'branch_id' => auth()->user()->branch->id ?? null,
                'account_id' => $receivableAccount->id,
                'task_id' => $task->id,
                'transaction_date' => Carbon::now(),
                'description' => 'Records Direct Expenses',
                'name' => $task->client_name ?? 'N/A',
                'debit' => $task->total,
                'credit' => 0,
                'balance' => $task->total,
                'type' => 'receivable',
            ]);

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

    public function index()
    {
        $user = Auth::user();
        $agent = null;
        $taskCount = 0;
        $clients = collect();
        $agents = collect();
        $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')->orderBy('id', 'desc');
        $queueTasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')
            ->withoutGlobalScope('enabled')
            ->where('enabled', false)
            ->orderBy('id', 'desc');

        if ($user->role_id == Role::ADMIN) {
            $tasks = $tasks->orderBy('created_at', 'desc')->get();
            $clients = Client::all();
            $agents = Agent::all();
            $queueTasks = $queueTasks->get();
        } elseif ($user->role_id == Role::COMPANY) {

            $branches = Branch::where('company_id', $user->company->id)->get();
            $agents = Agent::with('branch')->whereIn('branch_id', $branches->pluck('id'))->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
            $tasks = $tasks->where('company_id', $user->company->id)->get();
            $queueTasks = $queueTasks->where('company_id', $user->company->id)->get();
        } elseif ($user->role_id == Role::BRANCH) {
            $agents = Agent::with('branch')->where('branch_id', $user->branch_id)->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
            $tasks = $tasks->whereIn('agent_id', $agentsId)->where('company_id', $user->company_id)->get();
            $queueTasks = $queueTasks->where('company_id', $user->company_id)->get();
        } elseif ($user->role_id == Role::AGENT) {

            $clients = Client::where('agent_id', $user->agent->id)->get();
            $tasks = $tasks->where('agent_id', $user->agent->id)->get();
            $queueTasks = $queueTasks->where('agent_id', $user->agent->id)->get();
        } else {
            return redirect()->back()->with('error', 'User not authorized to view tasks.');
        }
        $processTask = $tasks->toArray();
        $processTask = array_map(function ($row) {

            $row = (array) $row;
            $hasNull = false;

            foreach ($row as $key => $value) {
                if ($value === null) {
                    $hasNull = true;
                    break;
                }
            }

            if ($hasNull) {
                $row['is_complete'] = false;
            } else {
                $row['is_complete'] = true;
            }

            return $row;
        }, $processTask);

        $taskCount = $tasks->count();
        $types = Task::distinct()->pluck('type');
        $suppliers = Supplier::whereHas('companies', function ($query) use ($user) {
            $query->where('company_id', $user->company->id);
        })->get();

        $importedTask = Cache::get('imported_task');

        if ($user->hasAnyRole('admin', 'company')) {

            $branches = $user->role_id == Role::ADMIN ? Branch::all() : Branch::where('company_id', $user->company_id)->get();

            // dd($agents);
            return view('tasks.index', compact('tasks', 'agent', 'taskCount', 'agents', 'clients', 'suppliers', 'branches', 'types', 'queueTasks', 'processTask'));
        }
        return view('tasks.index', compact('tasks', 'agent', 'taskCount', 'agents', 'clients', 'suppliers', 'types', 'queueTasks', 'processTask'));
    }

    public function voucher($id = null)
    {
        $user = Auth::user();
        $agent = null;
        $taskCount = 0;
        $clients = collect();
        $agents = collect();

        if ($user->role_id == Role::ADMIN) {

            $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')->get(); // Retrieve all tasks for admin
            $taskCount = Task::count(); // Total task count for admin
            $clients = Client::all();
            $agents = Agent::all();
        } elseif ($user->role_id == Role::COMPANY) {

            $agents = Agent::with(['branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            }])->get();

            $clients = Client::whereIn('agent_id', $agents->pluck('id'))->get();

            // Get all agents for this company
            $agentIds = $agents->pluck('id'); // Get all agents for this company
            $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')->whereIn('agent_id', $agentIds)->get(); // Retrieve tasks for the company’s agents
            $taskCount = Task::whereIn('agent_id', $agentIds)->count(); // Task count for the company

        } elseif ($user->role_id == Role::AGENT) {

            if ($id) {
                $agent = Agent::with('branch')->find($id);
                if ($agent) {
                    $tasks = Task::with('agent.branch', 'client')->where('agent_id', $agent->id)->get(); // Retrieve tasks for a specific agent
                    $taskCount = Task::where('agent_id', $agent->id)->count(); // Task count for the specific agent
                } else {
                    return redirect()->back()->with('error', 'Agent not found.');
                }
            } else {
                $agent = $user->agent;
                if ($agent) {
                    $tasks = Task::with('agent.branch', 'client')->where('agent_id', $agent->id)->get(); // Retrieve tasks for the logged-in agent
                    $taskCount = Task::where('agent_id', $agent->id)->count(); // Task count for the logged-in agent
                } else {
                    return redirect()->back()->with('error', 'Agent not found.');
                }
            }

            $companyId = $agent->branch->company_id;
            $agents = Agent::with(['branch', 'clients'])->where('branch_id', $agent->branch_id)->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
        }

        $tasks = $tasks ?? collect(); // Ensure $tasks is not null

        $suppliers = Supplier::all();
        // dd($tasks, $agent, $agents, $taskCount);
        return view('tasks.tasksVoucher', compact('tasks', 'agent', 'taskCount', 'agents', 'clients', 'suppliers')); // Pass the tasks and task count to the view
    }
    public function toggleStatus(Request $request, Task $task)
    {
        $task->enabled = $request->is_enabled;
        $task->save();

        return response()->json(['success' => true]);
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
            'client_id' => 'required',
            'agent_id' => 'required',
            'supplier_id' => 'required',
            'status' => 'required',
            'total' => 'required',
        ]);

        // Find the task
        $task = Task::findOrFail($id);
        $prevClientName = $task->client_name;

        $client = Client::findOrFail($request->client_id);
        // If the request is an AJAX request, handle inline editing
        if ($request->ajax()) {
            try {
                $field = key($request->all()); // Get the field being updated
                $value = $request->input($field);

                // Update the specific field
                $task->update([$field => $value]);

                return response()->json(['success' => true], 200);  // Ensure a 200 OK response with JSON format
            } catch (Exception $e) {

                return response()->json(['success' => false, 'message' => $e->getMessage()], 500); // Return error response with status 500
            }
        } else {

            try {
                $task->update($request->only(['client_id', 'agent_id', 'supplier_id', 'total', 'status']));
                $task->client_name = $client->name;
                $task->save();
                
                $transaction = Transaction::with('journalEntries')->where('description', 'like', '%'. $task->reference . '%')->first();
                
            } catch (Exception $e) {
                return redirect()->back()->with('error', 'Task update failed.');
            }

            if ($transaction) {
                try {
                    $transaction->journalEntries->each(function ($journalEntry) use ($client, $prevClientName) {
                        if ($journalEntry->name == $prevClientName) {
                            $journalEntry->name = $client->name;
                            $journalEntry->update();
                        }
                    });
                } catch (Exception $e) {
                    return redirect()->back()->with('error', 'Task update failed.');
                }
            }
            return redirect()->back()->with('success', 'Task updated successfully.');
        }
    }

    public function upload(Request $request)
    {
        $request->validate([
            'task_file' => 'required|mimes:pdf,txt',
        ]);

        $file = $request->file('task_file')->store('public/tasks');
        if ($file) {
            $content = $this->extractTaskFromFile($file);
        } else {

            $response = [
                'status' => 'error',
                'message' => 'File upload failed.'
            ];
        }

        $openai = new OpenAiController(new AIService);
        $response = $openai->flightOrHotel($content);

        if ($response['status'] == 'error') {
            return redirect()->back()->with('error', $response['message']);
        }

        if ($response['data'] == 'flight') {
            $response = $openai->extractFlightData($content);
        } else {
            $response = $openai->extractHotelData($content);
        }

        if ($response['status'] == 'error') {
            return redirect()->back()->with('error', $response['message']);
        }

        $request = new Request($response['data']);

        $request['enabled'] = true;

        $supplier = Supplier::where('name', 'like', $response['data']['supplier_name'])->first();

        $request['supplier_id'] =  $supplier->id;

        $response = $this->store($request);

        $response = json_decode($response->getContent(), true);

        if ($response['status'] == 'error') {
            return redirect()->back()->with('error', $response['message']);
        }

        logger('imported task: ', $response['data']);
        $existingTask = Cache::get('imported_task');

        if ($existingTask) {
            Cache::forget('imported_task');
        }

        Cache::put('imported_task', $response['data'], now()->addHour(1));
        return redirect()->back()->with($response['status'], $response['message'])->with('importedTask', $response['data'] ?? null);
    }

    public function extractTaskFromFile($file)
    {
        $file = storage_path('app/' . $file);

        if (File::extension($file) == 'pdf') {
            $contents = $this->pdfToText($file);
        } else {
            $contents = File::get($file);
        }

        return $contents;
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
    public function saveFlightDetails(array $data, int $taskId)
    {

        try {

            $airline = isset($data['airline_name']) ? Airline::where('name', 'like', '%' . $data['airline_name'] . '%')->first() : null;
            $countryFrom = isset($data['departure_from']) ? Country::where('name', 'like', '%' . $data['departure_from'] . '%')->first() : null;
            $countryTo = isset($data['departure_from']) ? Country::where('name', 'like', '%' . $data['arrive_to'] . '%')->first() : null;


            $flightDetails = [
                'farebase' => isset($data['farebase']) ? (float) $data['farebase'] : null,
                'departure_time' => $data['departure_time'] ?? null,
                'country_id_from' => $countryFrom->id ?? null,
                'airport_from' => $data['airport_from'] ?? null,
                'terminal_from' => $data['terminal_from'] ?? null,
                'arrival_time' => $data['arrival_time'] ?? null,
                'country_id_to' => $countryTo->id ?? null,
                'airport_to' => $data['airport_to'] ?? null,
                'terminal_to' => $data['terminal_to'] ?? null,
                'airline_id' => $airline->id ?? null,
                'flight_number' => $data['flight_number'] ?? null,
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
            $hotel = Hotel::where('name', 'like', '%' . $data['hotel_name'] . '%')->first();

            // $hotelCountry = Country::where('name', 'like', '%' . $data['hotel_country'] . '%')->first();

            if (!$hotel) {
                $hotel = Hotel::create([
                    'name' => $data['hotel_name'],
                    'address' => $data['hotel_address'] ?? null,
                    'city' => $data['hotel_city'] ?? null,
                    'state' => $data['hotel_state'] ?? null,
                    'country' => $data['hotel_country'] ?? null,
                    'zip' => $data['hotel_zip'] ?? null,
                ]);
            }

            $hotelDetails = [
                'hotel_id' => $hotel->id,
                'booking_time' => $data['booking_time'] ?? null,
                'check_in' => $data['check_in'] ?? null,
                'check_out' => $data['check_out'] ?? null,
                'room_number' => $data['room_number'] ?? null,
                'room_type' => $data['room_type'] ?? null,
                'room_amount' => $data['room_amount'] ?? null,
                'room_details' => $data['room_details'] ?? null,
                'rate' => $data['rate'] ?? null,
                'task_id' => $taskId
            ];

            TaskHotelDetail::create($hotelDetails);

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

    private function processSingleReservation($reservation, $agentId = null, $companyId)
    {
        $clientName = $reservation['service']['passengers'][0]['firstName'] ? $reservation['service']['passengers'][0]['firstName'] . ' ' . $reservation['service']['passengers'][0]['lastName'] : null;
        $hotel = $reservation['service']['hotel'] ?? null;
        $serviceDates = $reservation['service']['serviceDates'] ?? null;
        $prices = $reservation['service']['prices'] ?? null;

        $cancellationPolicy = [];

        if (isset($reservation['service']['cancellationPolicy'])) {
            logger('Cancellation Policy: ', $reservation['service']['cancellationPolicy']);
            foreach ($reservation['service']['cancellationPolicy']['policies'] as $policy) {
                $cancellationPolicy[] = [
                    'type' => $policy['type'],
                    'charge' => $policy['charge'] !== null ? $policy['charge']['value'] : null,
                ];
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
        // $hotelDB = Hotel::where('name', 'like', '%' . $hotel['name'] . '%')->first();

        // if (!$hotelDB) {
        //     try {

        //         $hotelDB = Hotel::create([
        //             'name' => $hotel['name'] ?? null,
        //             'address' => $hotel['address'] ?? null,
        //             'city' => $hotel['city'] ?? null,
        //             'state' => $hotel['state'] ?? null,
        //             'country' => $hotel['countryId'] ?? null,
        //             'zip' => $hotel['zip'] ?? null,
        //         ]);
        //     } catch (Exception $e) {
        //         Log::channel('magic_holidays')->error('Error creating hotel: ' . $e->getMessage(), [
        //             'hotel' => $hotel,
        //         ]);

        //         return [
        //             'status' => 'error',
        //             'message' => 'Error creating hotel: ' . $e->getMessage(),
        //         ];
        //     }

        //     Log::channel('magic_holidays')->info('Hotel created: ' . $hotelDB->id, [
        //         'hotel' => $hotel,
        //     ]);
        // }

        if (!$reservation['service']['rooms']) {
            Log::channel('magic_holidays')->warning('No rooms data found for reservation: ' . ($reservation['id'] ?? 'Unknown'));
            return; // Skip this reservation if no rooms are found
        }

        foreach ($reservation['service']['rooms'] as $room) {
            $enabled = true; // Assume enabled by default


            $taskData = [
                'client_id' => null,
                'agent_id' => $agentId,
                'company_id' => $companyId,
                'type' => 'hotel',
                'status' => $reservation['service']['status'] ?? null,
                'client_name' => $clientName,
                'reference' => (string)$reservation['id'] ?? null,
                'duration' => $serviceDates['duration'] ?? null,
                'payment_type' => $reservation['service']['payment']['type'] ?? null,
                'price' => $prices['issue']['selling']['value'] ?? null,
                'tax' => null,
                'surcharge' => null,
                'total' => $prices['total']['selling']['value'] ?? null,
                'cancellation_policy' => json_encode($cancellationPolicy) ?? null,
                'additional_info' => $reservation['service']['hotel']['name'] . ' - ' . $clientName,
                'supplier_id' => $supplierId,
                'venue' => $hotel['name'] ?? null,
                'invoice_price' => null,
                'voucher_status' => null,
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

            $response = $this->store($request);

            $response = json_decode($response->getContent(), true);
            logger('Task created: ', $response);
            
            if($response['status'] == 'error') {
                Log::channel('magic_holidays')->error('Error creating task: ' . $response['message']);
                return [
                    'status' => 'error',
                    'message' => $response['message'],
                ];
            }

            $task = Task::with('hotelDetails')->find($response['data']['id']);
            
            if (!$task) {
                Log::channel('magic_holidays')->error('Task not found after creation: ' . $response['data']['id']);
                return [
                    'status' => 'error',
                    'message' => 'Task not found after creation',
                ];
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

                return [
                    'status' => 'error',
                    'message' => 'Error creating room: ' . $e->getMessage(),
                ];
            }


            Log::channel('magic_holidays')->info('Task created for reservation: ' . ($reservation['id'] ?? 'Unknown') . ', Room: ' . ($room['id'] ?? 'Unknown'));

            return [
                'status' => 'success',
                'message' => 'Task created successfully',
            ];
        }
    }

    public function supplierTaskForAgent(Request $request)
    {
        $request->validate([
            'agent_id' => 'required',
            'supplier_ref' => 'required',
            'supplier_id' => 'required|exists:suppliers,id',
        ]);

        $user = Auth::user();
        $agent = Agent::findOrFail($request->agent_id);

        if ($user->role_id == Role::COMPANY) {
            $companyId = $user->company->id;
        } elseif ($user->role_id == Role::BRANCH) {
            $companyId = $user->branch->company->id;
        } elseif ($user->role_id == Role::AGENT) {
            $companyId = $user->agent->branch->company->id;
        } else {
            return redirect()->back()->with('error', 'User not authorized to create task');
        }

        if (!$agent) {
            return redirect()->back()->with('error', 'Agent not found');
        }

        $supplier = Supplier::findOrFail($request->supplier_id);
        $supplierController = new SupplierController();

        switch ($supplier->name) {
            case 'Magic Holiday':
                $response = $supplierController->getMagicHoliday($request->supplier_ref);
                $response = json_decode($response->getContent(), true);

                Log::channel('magic_holidays')->info('Magic Holiday response: ', $response);

                if (isset($response['status']) && $response['status'] == 'error') {
                    return redirect()->back()->with('error', $response['message']);
                }
                $data = $response['data'];

                if (isset($data['_embedded'])) { // Check if it's a list
                    foreach ($data['_embedded']['reservation'] as $reservation) {
                        $response = $this->processSingleReservation($reservation, $agent->id, $companyId);

                        if ($response['status'] == 'error') {
                            return redirect()->back()->with('error', $response['message']);
                        }

                        $supplierController->magicReserveWebhook($reservation['id']);
                    }
                } else {

                    $response = $this->processSingleReservation($data, $agent->id, $companyId);

                    if ($response['status'] == 'error') {
                        return redirect()->back()->with('error', $response['message']);
                    }

                    $supplierController->magicReserveWebhook($data['id']);
                }

                return redirect()->back()->with('success', 'Magic Holiday task received successfully');
            default:
                return redirect()->back()->with('error', 'Cannot Get Task From Supplier');
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

                    logger('TBO Task Client: ' . $client->name . ' created');

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
        $task = Task::with('flightDetails', 'flightDetails.countryFrom', 'flightDetails.countryTo')->findOrFail($taskId);
        $flight = $task->flightDetails;

        $companyLogoPath = public_path('images/CityLogo.png');
        $companyLogoData = base64_encode(file_get_contents($companyLogoPath));
        $companyLogoSrc = 'data:image/png;base64,' . $companyLogoData;

        return view('tasks.pdfView.flight-view', compact('task', 'flight', 'companyLogoSrc'));
    }

    public function flightPdfDownload($taskId)
    {
        $task = Task::with('flightDetails', 'flightDetails.countryFrom', 'flightDetails.countryTo')->findOrFail($taskId);
        $flight = $task->flightDetails;

        $companyLogoPath = public_path('images/CityLogo.png');
        $companyLogoData = base64_encode(file_get_contents($companyLogoPath));
        $companyLogoSrc = 'data:image/png;base64,' . $companyLogoData;

        $pdf = Pdf::loadView('tasks.pdf.flight', compact('task', 'flight', 'companyLogoSrc'));

        return $pdf->download('flight.pdf');
    }

    public function hotelPdf($taskId)
    {
        $task = Task::with('hotelDetails', 'hotelDetails.hotel', 'hotelDetails.room', 'hotelDetails.hotel.country')->findOrFail($taskId);
        $hotelDetails = $task->hotelDetails;

        $companyLogoPath = public_path('images/CityLogo.png');
        $companyLogoData = base64_encode(file_get_contents($companyLogoPath));
        $companyLogoSrc = 'data:image/png;base64,' . $companyLogoData;

        return view('tasks.pdfView.hotel-view', compact('task', 'hotelDetails', 'companyLogoSrc'));
    }


    public function hotelPdfDownload($taskId)
    {
        $task = Task::with('hotelDetails', 'hotelDetails.hotel', 'hotelDetails.room', 'hotelDetails.hotel.country')->findOrFail($taskId);
        $hotelDetails = $task->hotelDetails;

        $companyLogoPath = public_path('images/CityLogo.png');
        $companyLogoData = base64_encode(file_get_contents($companyLogoPath));
        $companyLogoSrc = 'data:image/png;base64,' . $companyLogoData;

        $pdf = Pdf::loadView('tasks.pdf.hotel', compact('task', 'hotelDetails', 'companyLogoSrc'));

        return $pdf->download('hotel.pdf');
    }
}
