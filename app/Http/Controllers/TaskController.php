<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\Item;
use App\Models\Agent;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TasksImport;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Models\Suppliers;
class TaskController extends Controller
{
public function index($id = null)
{
    $user = Auth::user();
    $agent = null;
    $taskCount = 0;

    if ($user->role == 'admin') {
        $tasks = Task::with('agent.company', 'client')->get(); // Retrieve all tasks for admin
        $taskCount = Task::count(); // Total task count for admin
    } 
    elseif ($user->role == 'company') {
        $agents = Agent::all();
   
         // Get all agents for this company
        $agentIds = $agents->pluck('id'); // Get all agents for this company
        $tasks = Task::with('agent.company', 'client')->whereIn('agent_id', $agentIds)->get(); // Retrieve tasks for the company’s agents
        $taskCount = Task::whereIn('agent_id', $agentIds)->count(); // Task count for the company
    } 
    elseif ($user->role == 'agent') {
        if ($id) {
            $agent = Agent::find($id);
            if ($agent) {
                $tasks = Task::with('agent.company', 'client')->where('agent_id', $agent->id)->get(); // Retrieve tasks for a specific agent
                $taskCount = Task::where('agent_id', $agent->id)->count(); // Task count for the specific agent
            } else {
                return redirect()->back()->with('error', 'Agent not found.');
            }
        } else {
            $agent = $user->agent;
            if ($agent) {
                $tasks = Task::with('agent.company', 'client')->where('agent_id', $agent->id)->get(); // Retrieve tasks for the logged-in agent
                $taskCount = Task::where('agent_id', $agent->id)->count(); // Task count for the logged-in agent
            } else {
                return redirect()->back()->with('error', 'Agent not found.');
            }
        }
    } else {
        return redirect()->back()->with('error', 'Unauthorized access.');
    }

    $tasks = $tasks ?? collect(); // Ensure $tasks is not null

    // dd($tasks, $agent, $agents, $taskCount);
    return view('tasks.tasksList', compact('tasks', 'agent','agents', 'taskCount')); // Pass the tasks and task count to the view
}


public function show($id)
{
    $task = Task::with('agent.company', 'client')->findOrFail($id);
    return view('tasks.singleTask', compact('task'));
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
    // Validate the form fields

    // Find the task
    $task = Task::findOrFail($id);

    // Update task data
    $task->update($request->only(['status', 'type', 'tax', 'price', 'client_name', 'agent_id']));

    // Redirect back with success message
    return redirect()->route('tasks.index')->with('success', 'Task updated successfully!');
}




   

public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx',
        ]);

        Excel::import(new TasksImport, $request->file('excel_file'));

        return redirect()->back()->with('success', 'Tasks imported successfully.');
    }

public function getTaskbyItemId($itemId)
    {
        $tasks = Task::where('item_id', $itemId)->get();

        if (!$tasks) {
            return response()->json([
                'message' => 'Task not found'
            ], 404);
        }

        return response()->json([
            'tasks' => $tasks
        ], 200);
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


}