<x-app-layout>
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Account Summary</h1>

    <h2 class="text-xl font-semibold mt-6 mb-2">Accounts</h2>
    <table class="min-w-full border border-gray-300">
        <thead class="bg-gray-200">
            <tr>
                <th class="border px-4 py-2">Account Name</th>
                <th class="border px-4 py-2">Balance</th>
                <th class="border px-4 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($accounts as $account)
                <tr>
                    <td class="border px-4 py-2">{{ $account->name }}</td>
                    <td class="border px-4 py-2">${{ number_format($account->balance, 2) }}</td>
                    <td class="border px-4 py-2">
                        <a href="{{ route('clients.show', ['id' => $account->company_id]) }}" class="text-blue-500 hover:underline">View Clients</a>
                        <span> | </span>
                        <a href="" class="text-blue-500 hover:underline">View Suppliers</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 class="text-xl font-semibold mt-6 mb-2">Accounts Receivable</h2>
    <ul class="list-disc list-inside">
        @foreach ($clients as $client)
            <li>
                <a href="{{ route('clients.show', ['id' => $client->id]) }}" class="text-blue-500 hover:underline">{{ $client->first_name }}</a>
            </li>
        @endforeach
    </ul>

    <h2 class="text-xl font-semibold mt-6 mb-2">Accounts Payable</h2>
    <ul class="list-disc list-inside">
        @foreach ($suppliers as $supplier)
            <li>
                <a href="{{ route('clients.show', ['id' => $supplier->id]) }}" class="text-blue-500 hover:underline">{{ $supplier->name }}</a>
            </li>
        @endforeach
    </ul>
</div>
</x-app-layout>