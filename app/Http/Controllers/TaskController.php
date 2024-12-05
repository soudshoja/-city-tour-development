<?php

namespace App\Http\Controllers;

use App\Http\Traits\Converter;
use App\Http\Traits\NotificationTrait;
use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\Item;
use App\Models\Agent;
use App\Models\TaskFlightDetail;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TasksImport;
use App\Models\Client;
use App\Models\Role;
use App\Models\Supplier;
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
//// tset
class TaskController extends Controller
{
    use NotificationTrait, Converter;

    public function index($id = null)
    {
        if(!auth()->user()){
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
            
            $agents = Agent::with(['branch'=> function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            }])->get();

            $clients = Client::whereIn('agent_id', $agents->pluck('id'))->get();

            // Get all agents for this company
            $agentIds = $agents->pluck('id'); // Get all agents for this company
            $tasks = Task::with('agent.branch', 'client','invoiceDetail.invoice')->whereIn('agent_id', $agentIds)->get(); // Retrieve tasks for the company’s agents
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
            $agents = Agent::with(['branch','clients'])->where('branch_id', $agent->branch_id)->get();
            $agentsId = $agents->pluck('id');
            $clients = Client::whereIn('agent_id', $agentsId)->get();
        } 

        $tasks = $tasks ?? collect(); // Ensure $tasks is not null
        
        $suppliers = Supplier::all();
        // dd($tasks, $agent, $agents, $taskCount);
        return view('tasks.tasksList', compact('tasks', 'agent', 'taskCount', 'agents', 'clients', 'suppliers')); // Pass the tasks and task count to the view
    }

    public function show($id)
    {
        // Retrieve the task with related agent and client data
        $task = Task::with(['agent', 'client', 'flightDetails', 'supplier'])->find($id);

        // Check if task exists
        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        // Return the task data as JSON for the modal to load dynamically
        return response()->json(['task' => $task], 200);
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


    public function import(Request $request)
    {
    
        $request->validate([
            'task_file' => 'required|mimes:pdf',
        ]);

        $file = $request->file('task_file')->store('tasks');

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
        $file = storage_path('app/public/' . $file);

        $contents = $this->pdfToText($file);

        // Prepare the OpenAI request
        $openai = new OpenAiController();
        $response = $openai->flightOrHotel($contents);

        if ($response['status'] == 'error') {
            return redirect()->back()->with('error', 'File upload failed.');
        }

        if ($response['data'] == 'flight') {
            $response = $openai->extractFlightData($contents);
        } else {
            $response = $openai->extractHotelData($contents);
        }

        return $response;
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
    public function getAgentTask($agentId){
        // get tasks that doesnt have invoice only
        $tasks = Task::whereDoesntHave('invoiceDetail')->where('agent_id', $agentId)->get();

        return response()->json($tasks);
    }
}
