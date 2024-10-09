<x-app-layout>
    <div class="mt-5 panel">
    <div class="mb-5 flex flex-col md:flex-row justify-between items-center w-full space-y-4 md:space-y-0">
        <h3 class="text-2xl font-bold text-gray-700 mb-4">Agent Clients Detail</h3>
        <a href="{{ route('agentsshow.show', ['id' => $agent->id]) }}" class="text-blue-500 text-xs underline hover:text-blue-700">
            Back to Agent Overview
        </a>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p><strong>Name:</strong> {{ $agent->name }}</p>
                        <p><strong>Email:</strong> {{ $agent->email }}</p>
                    </div>
                    <div>
                        <p><strong>Phone:</strong> {{ $agent->phone_number }}</p>
                        <p><strong>Company:</strong> {{ $agent->company->name }}</p>
                    </div>
                    <div>
                        <p><strong>Type:</strong> {{ $agent->type }}</p>
                    </div>
                </div>

            <!-- Search input on the right -->
            <div class="w-full md:w-auto">
                <input type="text" placeholder="Search..."
                    class="w-full md:w-auto pr-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder:text-gray-500" />
            </div>
        </div>
    </div>

    <div class="mt-5 panel">
        <div class="overflow-x-auto">
        @if($clients->isEmpty())
         <p class="text-gray-600">No clients for this agent.</p>
            @else
                <table class="min-w-full bg-white border border-gray-300 mt-4">
                    <thead>
                        <tr>
                            <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">client Name</th>
                            <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Email</th>
                            <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Phone</th>
                            <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Address</th>
                            <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clients as $client)
                            <tr>
                                <td class="py-4 px-6 border-b">{{ $client->name }}</td>
                                <td class="py-4 px-6 border-b">{{ $client->email }}</td>
                                <td class="py-4 px-6 border-b">{{ $client->phone }}</td>
                                <td class="py-4 px-6 border-b">{{ $client->address }}</td>
                                <td class="py-4 px-6 border-b">
                                    <ul class="py-1">
                                        <li><a href="{{ route('clients.show', $client->id) }}"
                                                class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">View
                                                Client</a></li>

                                    </ul>

                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $clients->appends(['section' => 'clients'])->links() }}
                </div>
            @endif
         </div>
    </div>

          
</x-app-layout>