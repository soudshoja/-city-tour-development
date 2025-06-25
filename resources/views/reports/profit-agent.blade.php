<x-app-layout>
    <div class="p-2 bg-gray-100 dark:bg-gray-800 rounded shadow mb-2 text-center text-xl font-semibold dark:text-gray-50">
        Profit Agent
    </div>
    <div class="p-4 bg-white dark:bg-gray-900 rounded shadow space-y-4">
        @if($agents->isEmpty())
        <div class="p-6 bg-white dark:bg-gray-800 rounded shadow text-center">
            <p class="text-lg text-gray-700 dark:text-gray-300">No agents found.</p>
        </div>
        @else
        @foreach($agents as $agent)
        <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
            <div class="flex items-center justify-between cursor-pointer transition-all duration-200 transform hover:scale-[1.01] hover:shadow-md hover:bg-gray-100 dark:hover:bg-gray-800 p-4 rounded transition-all" onclick="toggleInvoices({{ $agent->id }})">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-lg text-gray-800 dark:text-white">{{ $agent->name }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="bg-green-50 text-green-700 dark:bg-green-900 dark:text-green-200 px-2 py-0.5 rounded text-md font-bold">
                        {{ $agent->profit }} KWD
                    </span>
                    <svg id="arrow-{{ $agent->id }}" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </div>

            <div id="invoices-{{ $agent->id }}" class="hidden mt-3 space-y-4">
                @foreach($agent->invoices as $invoice)
                <div class="p-4 bg-gray-50 dark:bg-gray-800 border dark:border-gray-700 rounded-md shadow-sm">
                    <div class="flex justify-between items-start mb-2">
                        <p class="text-base text-gray-700 dark:text-gray-300 font-medium">Invoice ID: {{ $invoice->invoice_number }}</p>
                        <details class="text-sm text-gray-600 dark:text-gray-400">
                            <summary class="cursor-pointer hover:underline text-right">Transactions</summary>
                            <div class="pl-4 mt-1 space-y-1">
                                @foreach($invoice->transactions as $transaction)
                                <div class="flex items-center justify-between gap-2">
                                    <a href="{{ route('journal-entries.index', $transaction->id) }}" target="_blank" class="text-blue-600 dark:text-blue-400 text-sm hover:underline">
                                        Transaction ID: {{ $transaction->id }}
                                    </a>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $transaction->created_at }}</span>
                                </div>
                                @endforeach
                            </div>
                        </details>
                    </div>
                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-gray-600 dark:text-gray-400">Details:</p>
                        @foreach($invoice->invoiceDetails as $detail)
                        <div class="p-3 bg-white dark:bg-gray-900 rounded border dark:border-gray-700 flex justify-between items-center shadow-sm">
                            <p class="text-base text-gray-700 dark:text-gray-300 w-1/3">{{ $detail->task_description }}</p>
                            <div class="flex justify-between w-2/3 text-sm text-gray-600 dark:text-gray-400 space-x-4">
                                <p>Task Price: <span class="font-medium text-gray-800 dark:text-gray-100">{{ $detail->task_price }} KWD</span></p>
                                <p>Task Cost: <span class="font-medium text-gray-800 dark:text-gray-100">{{ $detail->supplier_price }} KWD</span></p>
                                <p>Markup: <span class="font-medium text-gray-800 dark:text-gray-100">{{ $detail->markup_price }} KWD</span></p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
        @endif
    </div>

    <script>
        function toggleInvoices(agentId) {
            const invoicesDiv = document.getElementById(`invoices-${agentId}`);
            const arrowIcon = document.getElementById(`arrow-${agentId}`);

            if (invoicesDiv.classList.contains('hidden')) {
                invoicesDiv.classList.remove('hidden');
                arrowIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />';
            } else {
                invoicesDiv.classList.add('hidden');
                arrowIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />';
            }
        }
    </script>
</x-app-layout>