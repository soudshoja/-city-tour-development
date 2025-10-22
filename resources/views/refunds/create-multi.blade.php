<x-app-layout>
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold text-gray-700 mb-6">
            Refund for {{ $tasks->count() }} Task{{ $tasks->count() > 1 ? 's' : '' }}
        </h1>
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <p><strong>Invoice #:</strong> {{ $invoice->invoice_number ?? 'N/A' }}</p>
            <p><strong>Status:</strong>
                <span class="{{ $invoice->status === 'paid' ? 'text-green-600' : ($invoice->status === 'unpaid' ? 'text-red-600' : 'text-yellow-600') }}">
                    {{ ucfirst($invoice->status) }}
                </span>
            </p>
        </div>
        @if($tasksPaid->isNotEmpty())
            <h2 class="text-2xl font-semibold text-green-700 mb-4">Paid Tasks</h2>
            @foreach($tasksPaid as $task)
                @include('refunds.partial.paid-invoice', ['task' => $task, 'invoicePaid' => true])
            @endforeach
        @endif
        @if($tasksUnpaid->isNotEmpty())
            <h2 class="text-2xl font-semibold text-red-700 mb-4 mt-8">Unpaid Tasks</h2>
            @foreach($tasksUnpaid as $task)
                @include('refunds.partial.unpaid-invoice', ['task' => $task, 'invoicePaid' => false])
            @endforeach
        @endif
        <div class="bg-gray-100 p-6 mt-10 rounded-lg border border-gray-300">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Refund Summary</h3>
            <div class="grid md:grid-cols-3 gap-4">
                <div>
                    <label class="font-semibold text-gray-700">Total Refund to Client</label>
                    <input id="totalRefund" readonly class="w-full border border-gray-300 rounded-lg bg-green-50 text-green-700 font-semibold px-3 py-2" value="0.00">
                </div>
                <div>
                    <label class="font-semibold text-gray-700">Total Supplier Charges</label>
                    <input id="totalCharge" readonly class="w-full border border-gray-300 rounded-lg bg-red-50 text-red-700 font-semibold px-3 py-2" value="0.00">
                </div>
                <div>
                    <label class="font-semibold text-gray-700">Net Refund</label>
                    <input id="netRefund" readonly class="w-full border border-gray-300 rounded-lg bg-blue-50 text-blue-700 font-semibold px-3 py-2" value="0.00">
                </div>
            </div>
        </div>
        <div class="mt-8 flex justify-end">
            <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold">
                Submit Refund
            </button>
        </div>
    </div>
    <script>
        document.addEventListener('input', function() {
            let totalRefund = 0, totalCharge = 0;
            document.querySelectorAll('input[name*="[total_nett_refund]"]').forEach(i => totalRefund += parseFloat(i.value) || 0);
            document.querySelectorAll('input[name*="[refund_airline_charge]"]').forEach(i => totalCharge += parseFloat(i.value) || 0);
            document.getElementById('totalRefund').value = totalRefund.toFixed(2);
            document.getElementById('totalCharge').value = totalCharge.toFixed(2);
            document.getElementById('netRefund').value = (totalRefund - totalCharge).toFixed(2);
        });
    </script>
</x-app-layout>
