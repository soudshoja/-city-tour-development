<x-app-layout>
    <div class="flex justify-between p-2 bg-white rounded shadow mb-2">
        Profit Agent
        <div class="flex items-center gap-4 mt-2">
           
        </div>
    </div>
    <div class="p-2 bg-white rounded shadow mb-2">
        @foreach($agents as $agent)
        <div class="w-full flex justify-between cursor-pointer hover:bg-gray-100" onclick="toggleInvoices({{ $agent->id }})">
            <p> {{ $agent->name }} </p>
            <p class="text-green-600"> {{ $agent->profit }} </p>
        </div>
        <div id="invoices-{{ $agent->id }}" class="hidden ml-4">
            @foreach($agent->invoices as $invoice)
            <div class="p-4 border rounded">
                <p>Invoice ID: {{ $invoice->id }}</p>
                <p class="font-semibold">Details:</p>
                <ul>
                    @foreach($invoice->invoiceDetails as $detail)
                    <div class="p-2 flex justify-between shadow">
                        <p>
                            {{ $detail->task_description}}
                        </p>
                        <div class="flex justify-around w-120">
                            <p>
                                Task Price:
                                {{ $detail->task_price }}
                            </p>
                            <p>
                                Task Cost:
                                {{ $detail->supplier_price}}
                            </p>
                            <p>
                                Markup Price:
                                {{ $detail->markup_price}}
                            </p>
                        </div>
                    </div>
                    @endforeach
                </ul>
            </div>
            @endforeach
        </div>
        @endforeach
    </div>

    <script>
        function toggleInvoices(agentId) {
            const invoicesDiv = document.getElementById(`invoices-${agentId}`);
            if (invoicesDiv.classList.contains('hidden')) {
                invoicesDiv.classList.remove('hidden');
            } else {
                invoicesDiv.classList.add('hidden');
            }
        }
    </script>
</x-app-layout>