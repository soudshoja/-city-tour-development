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
use App\Models\TaskHotelDetail;
use App\Services\TextFileProcessor;
use ConvertApi\ConvertApi;
use Exception;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Models\Suppliers;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;



class TaskController extends Controller
{
    use NotificationTrait, Converter;

    public function index()
    {
        if (!auth()->user()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $id = $user->id;
        $agent = null;
        $taskCount = 0;
        $clients = collect();
        $agents = collect();
        $tasks = collect();

        if ($user->role_id == Role::ADMIN) {
            $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')->get();
            $taskCount = Task::count();
            $clients = Client::all();
            $agents = Agent::all();
        } elseif ($user->role_id == Role::COMPANY) {
            $agents = Agent::with(['branch' => function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            }])->get();
            $clients = Client::whereIn('agent_id', $agents->pluck('id'))->get();
            $agentIds = $agents->pluck('id');
            $tasks = Task::with('agent.branch', 'client', 'invoiceDetail.invoice')->whereIn('agent_id', $agentIds)->get();
            $taskCount = Task::whereIn('agent_id', $agentIds)->count();
        } elseif ($user->role_id == Role::AGENT) {
            if ($id) {
                $agent = Agent::with('branch')->find($id);
                if ($agent) {
                    $tasks = Task::with('agent.branch', 'client')->where('agent_id', $agent->id)->get();
                    $taskCount = Task::where('agent_id', $agent->id)->count();
                } else {
                    return redirect()->back()->with('error', 'Agent not found.');
                }
            } else {
                $agent = $user->agent;
                if ($agent) {
                    $tasks = Task::with('agent.branch', 'client')->where('agent_id', $agent->id)->get();
                    $taskCount = Task::where('agent_id', $agent->id)->count();
                } else {
                    return redirect()->back()->with('error', 'Agent not found.');
                }
            }
            $companyId = $agent->branch->company_id;
            $agents = Agent::with(['branch', 'clients'])->where('branch_id', $agent->branch_id)->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
        }

        $suppliers = Supplier::all();

        $branches = $user->role_id == Role::ADMIN ? Branch::all() : Branch::where('company_id', $user->company->id)->get();

        // Fetch distinct task types
        $types = Task::distinct()->pluck('type');
        // Return the view with the required data
        return view('tasks.index', compact('tasks', 'agent', 'taskCount', 'agents', 'clients', 'suppliers', 'branches', 'types'));
    }

    public function voucher($id = null)
    {
        if (!auth()->user()) {
            return redirect()->route('login');
        }

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


    public function show($id)
    {
        $task = Task::with(['agent.branch', 'client', 'flightDetails.countryFrom',  'flightDetails.countryTo', 'hotelDetails.hotel','supplier'])->find($id);

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        if($task->flightDetails){
            $task['description'] = $task->flightDetails->countryFrom->name . ' ---> ' . $task->flightDetails->countryTo->name;
        } elseif($task->hotelDetails){
            $task['description'] = $task->hotelDetails->hotel->name . '/' . $task->hotelDetails->hotel->country->name;
        } else {
            $task['description'] = 'No description';
        }
        
        // Return the task data as JSON for the modal to load dynamically
        return response()->json($task, 200);
    }

    // edit and update tasks
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
        ]);

        // Find the task
        $task = Task::findOrFail($id);
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
                $task->update($request->only(['client_id', 'agent_id', 'supplier_id']));
                $task->client_name = $client->name;
                $task->save();
                return redirect()->back()->with('success', 'Task updated successfully.');
            } catch (Exception $e) {
                return redirect()->back()->with('error', 'Task update failed.');
            }
        }
    }


    public function upload(Request $request)
    {
        $request->validate([
            'task_file' => 'required|mimes:pdf',
        ]);

        $file = $request->file('task_file')->store('public/tasks');

        if ($file) {
            $response = $this->extractTaskFromFile($file);
        } else {
            $response = [
                'status' => 'error',
                'message' => 'File upload failed.'
            ];
        }

        // Excel::import(new TasksImport, $request->file('excel_file'));

        return redirect()->back()->with($response['status'], $response['message'])->with('importedTask', $response['data'] ?? null);
    }

    public function extractTaskFromFile($file)
    {
        $file = storage_path('app/' . $file);

        $contents = $this->pdfToText($file);

        // Prepare the OpenAI request
        $openai = new OpenAiController(new AIService);
        $response = $openai->flightOrHotel($contents);

        if ($response['status'] == 'error') {
            return redirect()->back()->with('error', 'File upload failed.');
        }

        if ($response['data'] == 'flight') {
            $response = $openai->extractFlightData($contents);
        } else {
            $response = $openai->extractHotelData($contents);
        }

        return response()->back()->with($response['status'], $response['message'])->with('importedTask', $response['data'] ?? null);
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
    public function getAgentTask($agentId)
    {
        // get tasks that doesnt have invoice only
        $tasks = Task::whereDoesntHave('invoiceDetail')->where('agent_id', $agentId)->get();

        return response()->json($tasks);
    }

    /**
     * Save tasks to the database
     * 
     * @param array $data
     * 
     * @return array contains status, message and data of task id
     * 
     */
    function saveTasks($data)
    {
        logger('Data: ', $data);
        $task = $data;

        if (auth()->user()->role_id == Role::COMPANY) {
            $companyId = auth()->user()->company->id;
        } else if (auth()->user()->role_id == Role::BRANCH) {
            $companyId = auth()->user()->branch->company_id;
        } else if (auth()->user()->role_id == Role::AGENT) {
            $companyId = auth()->user()->agent->branch->company_id;
        } else {

            return [
                'status' => 'error',
                'message' => 'User not authorized to create task',
            ];
        }

        $agent = (isset($task['agent_name']) && $task['agent_name'] !== null) ?
            Agent::where('name', 'like', '%' . $task['agent_name'] . '%')->first() ??
            Agent::with(['branch' => function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            }])->first() : Agent::with(['branch' => function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            }])->first();


        $client = (isset($task['client_name']) && $task['client_name'] !== null) ? Client::where('name', 'like', '%' . $task['client_name'] . '%')->first() : null;

        if ($task['supplier_name'] === null) {
            throw new Exception('Supplier name is not found');
        }

        $supplier = Supplier::where('name', 'like', '%' . $task['supplier_name'] . '%')->first();

        if (!$supplier) {
            return [
                'status' => 'error',
                'message' => 'Supplier not found',
            ];
        }
        logger('tasks: ', $task);
        logger('agent: ', $agent->toArray());

        $client ? logger('client: ', $client->toArray()) : logger('client dont exist');

        $taskData = [
            'additional_info' => $task['additional_info'] ?? null,
            'status' => $task['status'] ? strtolower($task['status']) : null,
            'client_name' => $client->name ?? null,
            'price' => isset($task['price']) ? $task['price'] : null,
            'surcharge' => isset($task['surcharge']) ? $task['surcharge'] : null,
            'total' => isset($task['total']) ? $task['total'] : null,
            'tax' => isset($task['tax']) ? $task['tax'] : null,
            'reference' => $task['reference'] ?? null,
            'type' => $task['type'] ? strtoupper($task['type']) : null,
            'agent_id' => $agent->id,
            'client_id' => $client->id ?? null,
            'supplier_id' => $supplier->id,
            'cancellation_policy' => $task['cancellation_policy'] ?? null,
            'venue' => $task['venue'] ?? null,
        ];

        try {
            $taskCreated = Task::create($taskData);

            logger('Task created: ', $taskCreated->get()->toArray());


            if (isset($data['task_flight_details'])) {
                $this->saveFlightDetails($data, $taskCreated->id);
            }

            if (isset($data['task_hotel_details'])) {
                $this->saveHotelDetails($data, $taskCreated->id);
            }
        } catch (Exception $e) {
            throw $e;
        }
        return [
            'status' => 'success',
            'message' => 'Task created successfully',
            'data' => $taskCreated,
        ];
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

            $data = $data['task_flight_details'];

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
            $data = $data['task_hotel_details'];

            $hotel = Hotel::where('name', 'like', '%' . $data['hotel_name'] . '%')->first();

            $hotelCountry = Country::where('name', 'like', '%' . $data['hotel_country'] . '%')->first();

            if (!$hotel) {
                $hotel = Hotel::create([
                    'name' => $data['hotel_name'],
                    'address' => $data['hotel_address'] ?? null,
                    'city' => $data['hotel_city'] ?? null,
                    'state' => $data['hotel_state'] ?? null,
                    'country_id' => $hotelCountry->id ?? null,
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
}
