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
    public function index()
    {

        $user = Auth::user();
        if ($user->role == 'admin') {
            // Admin can see all trips and tasks
            $trips = Item::with('tasks.agent.company', 'tasks.client')->get(); // Assuming Trip is related to Task
        } elseif ($user->role == 'company') {
            // Company can only see trips with tasks under their agents
            $agents = Agent::where('company_id', $user->company->id)->pluck('id');
            $trips = Item::with(['tasks' => function($query) use ($agents) {
                        $query->whereIn('agent_id', $agents);
                    }, 'tasks.agent.company', 'tasks.client'])->get();
        } elseif ($user->role == 'agent') {
            // Agent can see only their tasks
            $trips = Item::with(['tasks' => function($query) use ($user) {
                        $query->where('agent_id', $user->agent->id);
                    }, 'tasks.agent.company', 'tasks.client'])->get();
        }
        return view('tasks.tasksList', compact('trips'));
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
