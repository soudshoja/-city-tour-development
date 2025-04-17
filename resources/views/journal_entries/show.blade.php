<x-app-layout>
    <div class="p-2 bg-white rounded shadow">
        <table class="table-auto w-full border-collapse border border-gray-300">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border border-gray-300 px-4 py-2">Transaction ID</th>
                    <th class="border border-gray-300 px-4 py-2">Date/Time</th>
                    <th class="border border-gray-300 px-4 py-2">Description</th>
                    <th class="border border-gray-300 px-4 py-2">Account</th>
                    <th class="border border-gray-300 px-4 py-2">Debit</th>
                    <th class="border border-gray-300 px-4 py-2">Credit</th>
                    <th class="border border-gray-300 px-4 py-2">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($journalEntries as $entry)
                    <tr>
                        <td class="border border-gray-300 px-4 py-2">{{ $entry->transaction->id }}</td>
                        <td class="border border-gray-300 px-4 py-2">{{ $entry->created_at }}</td>
                        <td class="border border-gray-300 px-4 py-2">{{ $entry->description }}</td>
                        <td class="border border-gray-300 px-4 py-2">{{ $entry->account->name }}</td>
                        <td class="border border-gray-300 px-4 py-2">{{ $entry->debit }}</td>
                        <td class="border border-gray-300 px-4 py-2">{{ $entry->credit }}</td>
                        <td class="border border-gray-300 px-4 py-2">{{ $entry->running_balance }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>