<x-app-layout>
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Edit Refund #{{ $refund->refund_number }}</h1>

        <div class="bg-white shadow-md rounded-lg p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Invoice Info -->
                <div class="bg-gradient-to-br from-blue-100 to-white shadow-md rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-purple-200 pb-2">Task Info
                    </h3>
                    <p class="mb-2"><strong>Task Reference No:</strong> {{ $refund->task->reference }}</p>
                    <p class="mb-2"><strong>Info:</strong> {{ $refund->task->additional_info }}</p>
                    <p class="mb-2"><strong>Ticket Number:</strong> {{ $refund->task->ticket_number }}</p>
                    <p class="mb-2"><strong>Refund Date:</strong> {{ $refund->date }}</p>
                    <p class="mb-2"><strong>Refund Amount:</strong> KWD{{ number_format($refund->task->total, 2) }}
                    </p>
                    <p class="mb-2">
                        <strong>Status:</strong>
                        <span
                            class="badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium
                                {{ $refund->status === 'completed' ? 'badge-outline-success' : '' }}
                                {{ $refund->status === 'processed' ? 'badge-outline-assigned' : '' }}
                                {{ $refund->status === 'approved' ? 'badge-outline-success' : '' }}
                                {{ $refund->status === 'declined' ? 'badge-outline-danger' : '' }}
                                {{ $refund->status === 'pending' ? 'badge-outline-warning' : '' }}
                                {{ $refund->status === null ? 'badge-outline-danger' : '' }}">
                            {{ $refund->status === null ? 'Not Set' : ucwords($refund->status) }}

                        </span>

                    </p>
                </div>

                <!-- Client Info -->
                <div class="bg-gradient-to-br from-blue-100 to-white shadow-md rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-purple-200 pb-2">Client Info
                    </h3>
                    <p class="mb-2"><strong>Name:</strong> {{ $refund->task->client->first_name ?? 'N/A' }}</p>
                    <p class="mb-2"><strong>Email:</strong> {{ $refund->task->client->email ?? 'N/A' }}</p>
                    <br>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-purple-200 pb-2">Agent Info</h3>
                    <p class="mb-2"><strong>Name:</strong> {{ $refund->agent->name ?? 'N/A' }}</p>
                    <p class="mb-2"><strong>Email:</strong> {{ $refund->agent->email ?? 'N/A' }}</p>
                </div>
            </div>
            <div class="mb-6 rounded-lg p-4 {{ $invoicePaid ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                <div>
                    <div class="font-semibold {{ $invoicePaid ? 'text-green-700' : 'text-red-800' }}">Invoice Status: {{ $invoicePaid ? 'Paid' : 'Unpaid' }}</div>
                    @if(!$invoicePaid)
                        <div class="text-sm mt-1 text-red-900">
                            <span class="inline-block mt-1 rounded bg-white px-2 py-1 border border-red-300">
                                <span class="font-semibold">Total Refund to Client</span>
                                =
                                <span class="underline">Original Task Profit</span>
                                +
                                <span class="underline">Refund Task Supplier Charges</span>
                                +
                                <span class="underline">New Profit</span>
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            <hr class="my-6">
           
            @if($invoicePaid)

            @include('refunds.partial.paid-invoice')

            @else

            @include('refunds.partial.unpaid-invoice')

            @endif
        </div>
    </div>

    <script>
        function setAccountId(input) {
            const datalist = document.getElementById('accountList');
            const option = [...datalist.options].find(opt => opt.value === input.value);
            if (option) {
                document.getElementById('account_id').value = option.dataset.id;
            }
        }
    </script>
</x-app-layout>
