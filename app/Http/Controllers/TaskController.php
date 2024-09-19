<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TasksImport;

class TaskController extends Controller
{
    public function index()
    {
        $tasks = Task::all();

        return view('tasksList', compact('tasks'));
    }

    public function upload()
    {
        $tasks = Task::all();

        return view('tasksUpload', compact('tasks'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx',
        ]);

        Excel::import(new TasksImport, $request->file('excel_file'));

        return redirect()->back()->with('success', 'Tasks imported successfully.');
    }

}
