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
    
    // Initialize agent variable as null by default
    $agent = null;

    // If the user is an admin
    if ($user->role == 'admin') {
        // Admin can see all tasks
        $tasks = Task::with('agent.company', 'client')->paginate(6);
    } 
    // If the user is a company
    elseif ($user->role == 'company') {
        // Get all agents under the logged-in company
        $agents = Agent::where('company_id', $user->company->id)->pluck('id');

        // Get all tasks for those agents
        $tasks = Task::with('agent.company', 'client')->whereIn('agent_id', $agents)->paginate(6);
    } 
    // If the user is an agent
    elseif ($user->role == 'agent') {
        if ($id) {
            // If $id is provided, find the agent
            $agent = Agent::find($id);
            if ($agent) {
                $tasks = Task::with('agent.company', 'client')->where('agent_id', $agent->id)->paginate(6);
            } else {
                return redirect()->back()->with('error', 'Agent not found.');
            }
        } else {
            // If $id is not provided, use the logged-in agent
            $agent = $user->agent;
            if ($agent) {
                $tasks = Task::with('agent.company', 'client')->where('agent_id', $agent->id)->paginate(6);
            } else {
                return redirect()->back()->with('error', 'Agent not found.');
            }
        }
    } 
    else {
        return redirect()->back()->with('error', 'Unauthorized access.');
    }

    // In case no tasks are set, initialize tasks as empty to prevent undefined errors
    $tasks = $tasks ?? collect();

    return view('tasks.tasksList', compact('tasks', 'agent'));
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