<x-app-layout>
    <div class="container mx-auto p-6">
        <!-- Agent Info Section -->
        <div class="bg-white shadow-md rounded-lg p-8 mb-8">
            <h1 class="text-3xl font-bold text-gray-700 mb-6">Agent Travel Detail</h1>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">Name</h2>
                    <p class="text-gray-800">{{ $agent->name }}</p>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">Email</h2>
                    <p class="text-gray-800">{{ $agent->email }}</p>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">Phone Number</h2>
                    <p class="text-gray-800">{{ $agent->phone_number }}</p>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">Company</h2>
                    <p class="text-gray-800">{{ $agent->company->name }}</p>
                </div>
                <div>
                    <h2 class="text-lg font-semibold text-gray-600">Type</h2>
                    <p class="text-gray-800 capitalize">{{ $agent->type }}</p>
                </div>
            </div>

            <div class="mt-8 text-right">
                <a href="{{ route('agents.edit', $agent->id) }}" class="bg-blue-500 text-white py-2 px-6 rounded-lg shadow hover:bg-blue-600 transition duration-200">Update Details</a>
            </div>
        </div>

        <!-- Pending Tasks Section -->
        <div class="bg-white shadow-md rounded-lg p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-700 mb-4">Pending Tasks</h2>

            @if($pendingTasks->isEmpty())
                <p class="text-gray-600">No pending tasks for this agent.</p>
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
                        @foreach($pendingTasks as $task)
                            <tr>
                                <td class="py-4 px-6 border-b">{{ $task->description }}</td>
                                <td class="py-4 px-6 border-b">{{ $task->created_at }}</td>
                                <td class="py-4 px-6 border-b">{{ $task->status }}</td>
                                <td class="py-4 px-6 border-b">{{ $task->client->name }}</td>
                                <td class="py-4 px-6 border-b">

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
