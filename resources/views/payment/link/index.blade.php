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
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left">Link</th>
                    <th class="px-4 py-2 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-2">
                        <a href="{{ $payment->voucher_number }}" target="_blank" class="text-blue-500 hover:underline">{{ $payment->voucher_number }}</a>
                    </td>
                    <td class="px-4 py-2"> {{ $payment->client ? $payment->client->name : 'N/A' }} </td>
                    <td class="px-4 py-2"> {{ $payment->agent ? $payment->agent->name : 'N/A' }} </td>
                    <td class="px-4 py-2">{{ $payment->notes ?? 'No Notes' }}</td>
                    <td class="px-4 py-2">{{ $payment->amount }}</td>
                    <td class="px-4 py-2">{{ $payment->created_at->format('Y-m-d H:i:s') }}</td>
                    <td class="px-4 py-2">
                        {{ $payment->status }}
                    </td>
                    <td class="px-4 py-2">
                        @if($payment->invoice)
                        <a href="{{ route('invoice.show', $payment->invoice->invoice_number) }}" target="_blank" class="text-blue-500 hover:underline font-medium">
                            Send Link To Customer
                        </a>
                        @else
                        <a href="{{ route('payment.link.share', $payment->id) }}" target="_blank" class="text-blue-500 hover:underline font-medium">
                            Send Link To Customer
                        </a>
                        @endif
                        <a href="{{ route('payment.link.show', $payment->id) }}" target="_blank" class="text-blue-500 hover:underline font-medium ml-4">
                            View Link in PDF
                        </a>
                    </td>
                    <td class="px-4 py-2 relative">
                        <div x-data="{ editPaymentLink: false }">
                            <button @click="editPaymentLink = true" class="text-blue-500 hover:underline">Edit</button>
                            <div 
                                x-cloak 
                                x-show="editPaymentLink" 
                                class="fixed inset-0 z-10 bg-gray-500 bg-opacity-50 flex items-center justify-center">
                                <div class="bg-white p-6 rounded shadow-lg w-full max-w-md">
                                    <form 
                                        action="{{ route('payment.link.update', $payment->id) }}" 
                                        method="POST">
                                        @csrf
                                        @method('PUT')
                                        @unlessrole('agent')
                                        <div class="mb-4">
                                            <label for="agent_id" class="block text-sm font-medium text-gray-700">Agent</label>
                                            <select 
                                                name="agent_id" 
                                                id="agent_id" 
                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                @foreach ($agents as $agent)
                                                <option 
                                                    value="{{ $agent->id }}" 
                                                    {{ $payment->agent_id == $agent->id ? 'selected' : '' }}>
                                                    {{ $agent->name }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @else
                                        <input type="hidden" name="agent_id" value="{{ auth()->user()->id }}">
                                        @endunlessrole
                                        <div class="mb-4">
                                            <label for="client_id" class="block text-sm font-medium text-gray-700">Client</label>
                                            <select 
                                                name="client_id" 
                                                id="client_id" 
                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                @foreach ($clients as $client)
                                                <option 
                                                    value="{{ $client->id }}" 
                                                    {{ $payment->client_id == $client->id ? 'selected' : '' }}>
                                                    {{ $client->name }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-4">
                                            <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                                            <input 
                                                type="text" 
                                                name="amount" 
                                                id="amount" 
                                                value="{{ $payment->amount }}" 
                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div class="flex justify-end space-x-4">
                                            <button 
                                                @click="editPaymentLink = false" 
                                                class="text-red-500 hover:underline" 
                                                type="button">
                                                Cancel
                                            </button>
                                            <button 
                                                type="submit" 
                                                class="btn btn-primary">
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
</x-app-layout>