
<x-app-layout>
@if($tasks->isEmpty())
    <p class="text-gray-600">No Tasks for this agent.</p>
@else
    <table class="min-w-full bg-white border border-gray-300 mt-4">
        <thead>
            <tr>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Task Name</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Task Date</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Status</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Client</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tasks as $task)
                <tr>
                    <td class="py-4 px-6 border-b">{{ $task->description }}</td>
                    <td class="py-4 px-6 border-b">{{ $task->created_at->format('Y-m-d') }}</td>
                    <td class="py-4 px-6 border-b">{{ $task->status }}</td>
                    <td class="py-4 px-6 border-b">{{ $task->client->name }}</td>
                    <td class="py-4 px-6 border-b">
                        <a href="#" class="text-indigo-500">View</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">
        {{ $tasks->appends(['section' => 'tasks'])->links() }}
    </div>
@endif
</x-app-layout>