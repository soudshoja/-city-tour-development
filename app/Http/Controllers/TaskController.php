<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use App\Models\Item;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TasksImport;
use App\Models\Agent;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class TaskController extends Controller
{
public function index($id = null)
{
    $user = Auth::user();
    $agent = null;
    $taskCount = 0;

    if ($user->role == 'admin') {
        $tasks = Task::with('agent.company', 'client')->paginate(6);
        $taskCount = Task::count(); // Total task count for admin
    } 
    elseif ($user->role == 'company') {
        $agents = Agent::where('company_id', $user->company->id)->pluck('id');
        $tasks = Task::with('agent.company', 'client')->whereIn('agent_id', $agents)->paginate(6);
        $taskCount = Task::whereIn('agent_id', $agents)->count(); // Task count for the company
    } 
    elseif ($user->role == 'agent') {
        if ($id) {
            $agent = Agent::find($id);
            if ($agent) {
                $tasks = Task::with('agent.company', 'client')->where('agent_id', $agent->id)->paginate(6);
                $taskCount = Task::where('agent_id', $agent->id)->count(); // Task count for a specific agent
            } else {
                return redirect()->back()->with('error', 'Agent not found.');
            }
        } else {
            $agent = $user->agent;
            if ($agent) {
                $tasks = Task::with('agent.company', 'client')->where('agent_id', $agent->id)->paginate(6);
                $taskCount = Task::where('agent_id', $agent->id)->count(); // Task count for the logged-in agent
            } else {
                return redirect()->back()->with('error', 'Agent not found.');
            }
        }
    } else {
        return redirect()->back()->with('error', 'Unauthorized access.');
    }

    $tasks = $tasks ?? collect();

    return view('tasks.tasksList', compact('tasks', 'agent', 'taskCount')); // Pass task count to the view
}

public function show($id)
{
    $task = Task::with('agent.company', 'client')->findOrFail($id);

    return view('tasks.singleTask', compact('task'));
}


       

    public function upload()
    {
        $tasks = Task::all();

        return view('tasks.tasksUpload', compact('tasks'));
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