<x-app-layout>
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-4">Refunds List ({{ $totalRefunds }})</h1>

        <table class="min-w-full bg-white shadow-md rounded-lg overflow-hidden">
            <thead class="bg-gray-200 text-gray-700 font-semibold">
                <tr>
                    <th class="px-4 py-2">Invoice Number</th>
                    <th class="px-4 py-2">Amount</th>
                    <th class="px-4 py-2">Reason</th>
                    <th class="px-4 py-2">Method</th>
                    <th class="px-4 py-2">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($refunds as $refund)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $refund->invoice_number }}</td>
                        <td class="px-4 py-2">{{ number_format($refund->amount, 2) }}</td>
                        <td class="px-4 py-2">{{ $refund->reason }}</td>
                        <td class="px-4 py-2 capitalize">{{ $refund->method }}</td>
                        <td class="px-4 py-2">{{ $refund->date }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-gray-500">No refunds found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
