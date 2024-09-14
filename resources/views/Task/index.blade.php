@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Task List</h1>
    
    <div class="overflow-x-auto">
    <table class="min-w-full bg-white">
          <thead>
              <tr class="w-full border-b border-gray-300">
                  <th class="py-2 px-4">Agent</th>
                  <th class="py-2 px-4">Task Name</th>
                  <th class="py-2 px-4">Status</th>
                  <th class="py-2 px-4">Due Date</th>
                  <th class="py-2 px-4">Actions</th>
              </tr>
          </thead>
          <tbody>
              <!-- Manually displaying tasks -->
              <tr class="border-t border-gray-300">
                  <td class="py-3 px-4">Agent 1</td>
                  <td class="py-3 px-4">Book flight tickets</td>
                  <td class="py-3 px-4 text-yellow-500">Pending</td>
                  <td class="py-3 px-4">2024-09-15</td>
                  <td class="py-3 px-4">
                      <button class="bg-blue-500 text-white py-1 px-3 rounded">Edit</button>
                      <button class="bg-red-500 text-white py-1 px-3 rounded">Delete</button>
                  </td>
              </tr>

              <tr class="border-t border-gray-300">
                  <td class="py-3 px-4">Agent 2</td>
                  <td class="py-3 px-4">Arrange accommodation</td>
                  <td class="py-3 px-4 text-blue-500">In Progress</td>
                  <td class="py-3 px-4">2024-09-18</td>
                  <td class="py-3 px-4">
                      <button class="bg-blue-500 text-white py-1 px-3 rounded">Edit</button>
                      <button class="bg-red-500 text-white py-1 px-3 rounded">Delete</button>
                  </td>
              </tr>

              <tr class="border-t border-gray-300">
                  <td class="py-3 px-4">Agent 3</td>
                  <td class="py-3 px-4">Submit visa application</td>
                  <td class="py-3 px-4 text-green-500">Completed</td>
                  <td class="py-3 px-4">2024-09-12</td>
                  <td class="py-3 px-4">
                      <button class="bg-blue-500 text-white py-1 px-3 rounded">Edit</button>
                      <button class="bg-red-500 text-white py-1 px-3 rounded">Delete</button>
                  </td>
              </tr>

              <!-- Additional manual task rows can be added here -->
          </tbody>
      </table>
        <!-- <table class="min-w-full bg-white border border-gray-300">
            <thead>
                <tr class="bg-gray-100">
                    <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Agent Name</th>
                    <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Task</th>
                    <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Status</th>
                    <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Date</th>
                    <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tasks as $task)
                    <tr class="border-t border-gray-300">
                        <td class="py-3 px-4">{{ $task->agent->name }}</td>
                        <td class="py-3 px-4">{{ $task->name }}</td>

                         Colorful status based on task status 
                        <td class="py-3 px-4">
                            @if($task->status == 'completed')
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-sm">Completed</span>
                            @elseif($task->status == 'pending')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">Pending</span>
                            @elseif($task->status == 'in_progress')
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">In Progress</span>
                            @else
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-sm">Overdue</span>
                            @endif
                        </td>

                         Task date
                        <td class="py-3 px-4">{{ $task->due_date->format('M d, Y') }}</td>

                        Actions like edit or delete
                        <td class="py-3 px-4">
                            <a href="#" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            <a href="#" class="ml-4 text-red-600 hover:text-red-900">Delete</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table> -->
    </div>
</div>
@endsection
