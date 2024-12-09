<x-app-layout>
@if($invoices->isEmpty())
    <p class="text-gray-600">No invoices for this agent.</p>
@else
    <table class="min-w-full bg-white border border-gray-300 mt-4">
        <thead>
            <tr>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">invoice Number</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">invoice Date</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Status</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Client</th>
                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
                <tr>
                    <td class="py-4 px-6 border-b">{{ $invoice->invoice_number }}</td>
                    <td class="py-4 px-6 border-b">{{ $invoice->created_at->format('Y-m-d') }}</td>
                    <td class="py-4 px-6 border-b">{{ $invoice->status }}</td>
                    <td class="py-4 px-6 border-b">{{ $invoice->client->name }}</td>
                    <td class="py-4 px-6 border-b">
                        <a href="#" class="text-indigo-500">View</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">
        {{ $invoices->appends(['section' => 'invoices'])->links() }}
    </div>
@endif
</x-app-layout>