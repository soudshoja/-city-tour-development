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
        
        // Check if the agent exists
        $agent = Agent::find($id); // This will get the full agent record
    
        if (!$agent) {
            return redirect()->back()->with('error', 'Agent not found.'); // Handle the case where the agent does not exist
        }
    
        // Continue with your logic based on user roles
        if ($user->role == 'admin') {
            // Admin can see all trips and tasks
            $tasks = Task::with('agent.company', 'client')->where('agent_id', $id)->paginate(6);
        } elseif ($user->role == 'company') {
            // Company can only see trips with tasks under their agents
            $agents = Agent::where('company_id', $user->company->id)->pluck('id');
            $tasks = Task::with('agent.company', 'client')->where('agent_id', $id)->paginate(6);
        } elseif ($user->role == 'agent') {
            // Agent can see their tasks
            $tasks = Task::with('agent.company', 'client')->where('agent_id', $agent->id)->paginate(6);
        }
    
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
