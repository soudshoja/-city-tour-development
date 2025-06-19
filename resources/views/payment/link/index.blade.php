<x-app-layout>
    <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
        <li>
            <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
        </li>
        <li class="before:content-['/'] before:mr-1 ">
            <span>Payment Links</span>
        </li>
    </ul>
    <div class="p-2 bg-white rounded shadow">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Payment Links</h2>
            <a href="{{ route('payment.link.create') }}" class="btn btn-primary">Create Payment Link</a>
        </div>

        @if ($payments->isEmpty())
            <p class="text-gray-500">No payment links found.</p>
        @else
            <table class="min-w-full bg-white border border-gray-200 rounded shadow">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-2 text-left">Link</th>
                        <th class="px-4 py-2 text-left">Client</th>
                        <th class="px-4 py-2 text-left">Agent</th>
                        <th class="px-4 py-2 text-left">Notes</th>
                        <th class="px-4 py-2 text-left">Amount</th>
                        <th class="px-4 py-2 text-left">Created At</th>
                        <th class="px-4 py-2 text-left">Created By</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Link</th>
                        <th class="px-4 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payments as $payment)
                        @php
                            $paymentUrl = route('payment.link.show', [
                                'voucherNumber' => $payment->voucher_number,
                            ]);
                        @endphp
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-2">
                                <a href="{{ $paymentUrl }}" target="_blank"
                                    class="text-blue-500 hover:underline">{{ $payment->voucher_number }}</a>
                            </td>
                            <td class="px-4 py-2"> {{ $payment->client ? $payment->client->name : 'N/A' }} </td>
                            <td class="px-4 py-2"> {{ $payment->agent ? $payment->agent->name : 'N/A' }} </td>
                            <td class="px-4 py-2">{{ $payment->notes ?? 'No Notes' }}</td>
                            <td class="px-4 py-2">{{ $payment->amount }}</td>
                            @if (auth()->user()->role->name === 'admin' || auth()->user()->role->name === 'company')
                                <td class="px-4 py-2">{{ $payment->created_at->format('Y-m-d H:i:s') }}</td>
                            @else
                                <td class="px-4 py-2">{{ $payment->created_at->format('D d M Y') }}</td>
                            @endif
                            <td class="px-4 py-2">
                                {{ $payment->createdBy ? $payment->createdBy->name : 'N/A' }}
                            </td>
                            <td class="px-4 py-2">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'failed' => 'bg-red-100 text-red-800',
                                        'cancelled' => 'bg-gray-100 text-gray-600',
                                    ];
                                    $status = strtolower($payment->status);
                                    $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                                @endphp
                                <span class="inline-block px-3 py-1 rounded font-semibold {{ $colorClass }}">
                                    {{ ucfirst($payment->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                @if ($payment->status !== 'completed')
                                    <div class="flex flex-col space-y-2">

                                        @if ($payment->invoice)
                                            <a href="{{ route('invoice.show', $payment->invoice->invoice_number) }}"
                                                target="_blank"
                                                class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition font-medium">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                                </svg>
                                                Send Link To Customer
                                            </a>
                                        @else
                                            <form action="{{ route('resayil.share-payment-link') }}" method="POST"
                                                target="" class="inline">
                                                @csrf
                                                <input type="hidden" name="client_id"
                                                    value="{{ $payment->client_id }}">
                                                <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                                <input type="hidden" name="voucher_number"
                                                    value="{{ $payment->voucher_number }}">
                                                <button type="submit"
                                                    class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition font-medium">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1"
                                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                                    </svg>
                                                    Send Link To Customer
                                                </button>
                                            </form>
                                        @endif


                                        <button onclick="copyToClipboard('{{ $paymentUrl }}')"
                                            class="inline-flex items-center px-3 py-1 bg-yellow-100 text-gray-700 rounded hover:bg-gray-200 transition font-medium">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 16h8M8 12h8m-6 8h6a2 2 0 002-2V7a2 2 0 00-2-2H9m-2 0H7a2 2 0 00-2 2v12a2 2 0 002 2h2V5z" />
                                            </svg>
                                            Copy Link to Clipboard
                                        </button>

                                        <a href="{{ $paymentUrl }}" target="_blank"
                                            class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200 transition font-medium">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 4v16m8-8H4" />
                                            </svg>
                                            View Link in PDF
                                        </a>
                                    </div>
                                @else
                                    <span class="inline-block px-3 py-1 bg-gray-100 text-gray-600 rounded font-medium">
                                        Payment has been made
                                    </span>
                                @endif
                            </td>
                            @if ($payment->status === 'pending')
                                <td class="px-4 py-2 relative">
                                    <div x-data="{ editPaymentLink: false }">
                                        <button @click="editPaymentLink = true"
                                            class="text-blue-500 hover:underline">Edit</button>
                                        <div x-cloak x-show="editPaymentLink"
                                            class="fixed inset-0 z-10 bg-gray-500 bg-opacity-50 flex items-center justify-center">
                                            <div class="bg-white p-6 rounded shadow-lg w-full max-w-md">
                                                <form action="{{ route('payment.link.update', $payment->id) }}"
                                                    method="POST">
                                                    @csrf
                                                    @method('PUT')
                                                    @unlessrole('agent')
                                                        <div class="mb-4">
                                                            <label for="agent_id"
                                                                class="block text-sm font-medium text-gray-700">Agent</label>
                                                            <select name="agent_id" id="agent_id"
                                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                                @foreach ($agents as $agent)
                                                                    <option value="{{ $agent->id }}"
                                                                        {{ $payment->agent_id == $agent->id ? 'selected' : '' }}>
                                                                        {{ $agent->name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    @else
                                                        <input type="hidden" name="agent_id"
                                                            value="{{ auth()->user()->id }}">
                                                    @endunlessrole
                                                    <div class="mb-4">
                                                        <label for="client_id"
                                                            class="block text-sm font-medium text-gray-700">Client</label>
                                                        <select name="client_id" id="client_id"
                                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                            @foreach ($clients as $client)
                                                                <option value="{{ $client->id }}"
                                                                    {{ $payment->client_id == $client->id ? 'selected' : '' }}>
                                                                    {{ $client->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="mb-4">
                                                        <label for="amount"
                                                            class="block text-sm font-medium text-gray-700">Amount</label>
                                                        <input type="text" name="amount" id="amount"
                                                            value="{{ $payment->amount }}"
                                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div class="flex justify-end space-x-4">
                                                        <button @click="editPaymentLink = false"
                                                            class="text-red-500 hover:underline" type="button">
                                                            Cancel
                                                        </button>
                                                        <button type="submit" class="btn btn-primary">
                                                            Update
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <form action="" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:underline">Delete</button>
                                    </form>
                                </td>
                            @else
                                <td class="px-4 py-2 text-gray-400">N/A</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                    </tr>
                </tfoot>
            </table>
        @endif

    </div>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Link copied to clipboard!');
            }, function(err) {
                alert('Failed to copy: ', err);
            });
        }
    </script>
</x-app-layout>
