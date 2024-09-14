<x-app-layout>
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Agent List</h1>
        
        <table class="min-w-full bg-white border border-gray-300">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b">Name</th>
                    <th class="py-2 px-4 border-b">Email</th>
                    <th class="py-2 px-4 border-b">Phone Number</th>
                    <th class="py-2 px-4 border-b">Type</th>
                    <th class="py-2 px-4 border-b">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($agents as $agent)
                    <tr>
                        <td class="py-2 px-4 border-b">{{ $agent->name }}</td>
                        <td class="py-2 px-4 border-b">{{ $agent->email }}</td>
                        <td class="py-2 px-4 border-b">{{ $agent->phone_number }}</td>
                        <td class="py-2 px-4 border-b">{{ $agent->type }}</td>
                        <td class="py-2 px-4 border-b">
                            <a href="{{ route('agentsshow.show', $agent->id) }}" class="bg-blue-500 text-white py-1 px-2 rounded">Show</a>
                            <a href="{{ route('tasks.index', $agent->id) }}" class="bg-green-500 text-white py-1 px-2 rounded">See Task</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
