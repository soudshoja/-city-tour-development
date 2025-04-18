<table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-md">
    <thead class="bg-gray-100 text-gray-700">
        <tr class="border-b border-gray-300">
            <th class="text-center px-4 py-3 text-left text-base font-semibold">Transaction ID</th>
            <th class="text-center px-4 py-3 text-left text-base font-semibold">Description</th>
            <th class="text-center px-4 py-3 text-left text-base font-semibold">Date</th>
            <th class="text-center px-4 py-3 text-left text-base font-semibold">Action</th>
        </tr>
    </thead>
    <tbody class="text-gray-700">
        @foreach($transactions as $transaction)
            <tr class="hover:bg-gray-50 transition-all">
                <td class="text-center border-b border-gray-200 px-4 py-3 text-base font-medium">
                    {{ $transaction->id }}
                </td>
                <td class="text-center border-b border-gray-200 px-4 py-3 text-base">
                    {{ $transaction->description }}
                </td>
                <td class="text-center border-b border-gray-200 px-4 py-3 text-base">
                    {{ $transaction->created_at }}
                </td>
                <td class="text-center border-b border-gray-200 px-4 py-3 text-base">
                    <a href="{{ route('journal-entries.index', $transaction->id) }}" class="text-blue-500 hover:underline">
                        View Ledger
                    </a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
