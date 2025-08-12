<x-app-layout>
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
                    <td class="py-4 px-6 border-b">{{ $client->first_name }}</td>
                    <td class="py-4 px-6 border-b">{{ $client->email }}</td>
                    <td class="py-4 px-6 border-b">{{ $client->phone }}</td>
                    <td class="py-4 px-6 border-b">{{ $client->address }}</td>
                    <td class="py-4 px-6 border-b">
                        <a href="#" class="text-indigo-500">View</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">
        {{ $clients->appends(['section' => 'clients'])->links() }}
    </div>
@endif
</x-app-layout>