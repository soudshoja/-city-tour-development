<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Todo;



class ToDoListController extends Controller
{
public function index()
{
    $todos = Todo::all(); // Fetch all to-do items
    return view('todolist.index', compact('todos'));
}
 public function store(Request $request)
    {
        // Validate the input fields
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // Create a new task in the database
        Todo::create([
            'title' => $request->title,
            'description' => $request->description,
            'completed' => false, // Default to incomplete
        ]);

        // Redirect back with a success message
        return redirect()->route('todolist.index')->with('success', 'Task created successfully!');
    }

public function show($id)
{
    $todo = Todo::findOrFail($id); // Fetch a single to-do item by ID
    return view('todolist.show', compact('todo'));
}

public function edit($id)
{
    $todo = Todo::findOrFail($id); // Fetch a single to-do item by ID
    return view('todolist.edit', compact('todo'));
}


}