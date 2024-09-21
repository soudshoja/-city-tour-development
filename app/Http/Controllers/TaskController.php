<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
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
        $tasks = Task::all();

        return view('tasks.tasksList', compact('tasks'));
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

    public function showCreate($id){
        return view('task.create', compact('id'));
    }

    public function create(Request $request)
    {

        try {
            $task = new Task();
            $task->item_id = $request->item_id;
            $task->description = $request->description;
            $task->reference = $request->reference;
            $task->agent_email = Agent::where('user_id', Auth::id())->first()->email;
            $task->task_type = $request->task_type;
            $task->save();
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Failed to create task: ' . $e->getMessage());
        }

        
        return Redirect::route('items.show', $request->item_id)->with('success', 'Task created successfully');

    }

    public function getTaskbyItemId($itemId)
    {
        $tasks = Task::where('item_id', $itemId)->get();
        
        if(!$tasks) {
            return response()->json([
                'message' => 'Task not found'
            ], 404);
        }

        return response()->json([
            'tasks' => $tasks
        ], 200);
    }


}
