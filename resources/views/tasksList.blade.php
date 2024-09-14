<x-app-layout>
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Task List</h1>
    
    <div class="overflow-x-auto">
   
         <table class="min-w-full bg-white border border-gray-300">
            <thead>
                <tr class="bg-gray-100">
                    <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Agent Email</th>
                    <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Task</th>
                    <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Status</th>
                    <th class="text-left py-3 px-4 uppercase font-semibold text-sm">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tasks as $task)
                    <tr class="border-t border-gray-300">
                        <td class="py-3 px-4">{{ $task->agent_email }}</td>
                        <td class="py-3 px-4">{{ $task->description }}</td>
                        <td class="py-3 px-4">
                            @if($task->status == 'completed')
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-sm">Completed</span>
                            @elseif($task->status == 'pending')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">Pending</span>
                            @elseif($task->status == 'inprogress')
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">In Progress</span>
                            @else
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-sm">Overdue</span>
                            @endif
                        </td>
                        Actions like edit or delete
                        <td class="py-3 px-4">
                            <a href="#" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                            <a href="#" class="ml-4 text-red-600 hover:text-red-900">Delete</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table> 
    </div>
</div>
</x-app-layout>
