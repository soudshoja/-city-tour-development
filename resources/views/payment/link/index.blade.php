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
        <div class="flex justify-between items-center mb-4 p-2">
            <h2 class="text-xl font-semibold">Payment Links</h2>
            <a href="{{ route('payment.link.create') }}" class="bg-blue-600 hover:bg-blue-700 rounded-full shadow-md text-white px-4 py-2">Create Payment Link</a>
        </div>

        @if ($payments->isEmpty())
        <p class="text-gray-500">No payment links found.</p>
        @else
        <table class="min-w-full bg-white border border-gray-200 rounded shadow">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-2 text-left">Invoice Link</th>
                    <th class="px-4 py-2 text-left">Client</th>
                    <th class="px-4 py-2 text-left">Agent</th>
                    <th class="px-4 py-2 text-left">Notes</th>
                    <th class="px-4 py-2 text-left">Amount</th>
                    <th class="px-4 py-2 text-left">Created At</th>
                    <th class="px-4 py-2 text-left">Created By</th>
                    <th class="px-4 py-2 text-left">Reference</th>
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
                        $payment_reference = $payment->payment_reference ? ($payment->invoice_ref ? $payment->payment_reference . '/' . $payment->invoice_ref : $payment->payment_reference) : 'N/A';
                        $isTrimmed = strlen($payment_reference) > 15;
                        $trimmedValue = \Illuminate\Support\Str::limit($payment_reference, 15);
                        @endphp

                        @if ($isTrimmed)
                        <span x-data="{ showFullData: false }">
                            <span x-show="!showFullData" @click="showFullData = !showFullData" class="cursor-pointer hover:text-purple-700" data-tooltip-left="Click to expand">
                                {{ $trimmedValue }}
                            </span>

                            <span x-show="showFullData" @click="showFullData = !showFullData" class="cursor-pointer hover:text-purple-500">
                                {{ $payment_reference }}
                            </span>
                        </span>
                        @else
                        <span>{{ $payment_reference }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        @php
                        $statusColors = [
                        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-600',
                        'completed' => 'bg-green-100 text-green-800 border-green-600',
                        'failed' => 'bg-red-100 text-red-800 border-red-600',
                        'cancelled' => 'bg-gray-100 text-gray-600 border-gray-600',
                        ];
                        $status = strtolower($payment->status);
                        $colorClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800 border-gray-600';
                        @endphp
                        <span class="inline-block px-4 py-2 rounded-full font-semibold text-center {{ $colorClass }} border-2 transition-all duration-200 ease-in-out transform hover:scale-105 hover:shadow-lg">
                            {{ ucfirst($payment->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-2">
                        @if ($payment->status !== 'completed')
                        <div class="flex flex-col space-y-2">

                            @if ($payment->invoice)
                            <form action="{{ route('resayil.share-payment-link') }}" method="POST" target="" class="inline">
                                @csrf
                                <input type="hidden" name="client_id" value="{{ $payment->client_id }}">
                                <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                <input type="hidden" name="voucher_number" value="{{ $payment->voucher_number }}">

                                <button type="submit"
                                    class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition font-medium transition-all duration-200 ease-in-out transform hover:scale-105"
                                    data-tooltip="Send Link To Customer">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                </button>
                            </form>

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
                                    class="inline-flex items-center justify-center px-4 py-2 bg-blue-100 text-blue-700 rounded-full text-sm shadow-md hover:bg-blue-200 transition font-medium transition-all duration-200 ease-in-out transform hover:scale-105">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                    </svg>
                                    Send Link
                                </button>
                            </form>
                            @endif


                            <button onclick="copyToClipboard('{{ $paymentUrl }}')"
                                class="inline-flex items-center justify-center px-4 py-2 bg-yellow-100 text-gray-700 rounded-full text-sm shadow-md hover:bg-yellow-200 transition font-medium transition-all duration-200 ease-in-out transform hover:scale-105">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 16h8M8 12h8m-6 8h6a2 2 0 002-2V7a2 2 0 00-2-2H9m-2 0H7a2 2 0 00-2 2v12a2 2 0 002 2h2V5z" />
                                </svg>
                                Copy Link
                            </button>

                            <a href="{{ $paymentUrl }}" target="_blank"
                                class="inline-flex text-center items-center px-4 py-2 bg-green-100 text-green-700 rounded-full text-sm shadow-md hover:bg-green-200 transition font-medium transition-all duration-200 ease-in-out transform hover:scale-105">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4" />
                                </svg>
                                View Invoice
                            </a>
                        </div>
                        @else
                        <span class="inline-block px-3 py-2 text-center bg-gray-200 hover:bg-gray-300 text-gray-600 rounded-full text-sm shadow-md transition-all duration-200 ease-in-out transform hover:scale-105">
                            Payment has been made
                        </span>
                        @endif
                    </td>
                    @if ($payment->status === 'pending')
                    <td class="px-4 py-2 relative">
                        <div x-data="{ editPaymentLink: false }">
                            <button @click="editPaymentLink = true" class="flex items-center gap-2 p-2 border-2 border-transparent rounded-full hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 ease-in-out transform hover:scale-105" data-tooltip-left="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" class="text-blue-500 hover:text-blue-700" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 20h9M15 3l6 6-9 9H6v-6l9-9z"></path>
                                </svg>
                            </button>

                            <div x-cloak x-show="editPaymentLink"
                                class="fixed inset-0 z-10 bg-gray-500 bg-opacity-50 flex items-center justify-center">
                                <div class="bg-white p-6 rounded shadow-lg w-full max-w-md relative">
                                    <div class="flex items-center justify-between mb-6">
                                        <div>
                                            <h2 class="text-xl font-bold text-gray-800">Edit Payment Link Details</h2>
                                            <p class="text-gray-600 italic text-xs mt-1">Please update the payment link details to ensure accurate information</p>
                                        </div>
                                        <button @click="editPaymentLink = false" class="absolute top-2 right-2 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                            &times;
                                        </button>
                                    </div>
                                    <form action="{{ route('payment.link.update', $payment->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                       @unlessrole('agent')
    @php
        $selectedAgent = \App\Models\Agent::find($payment->agent_id);
        $agentPlaceholder = $selectedAgent ? $selectedAgent->name : 'Select an Agent';
    @endphp

    <div class="mb-4">
        <x-searchable-dropdown
            name="agent_id"
            :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])"
            :placeholder="$agentPlaceholder"
            :selectedName="$selectedAgent ? $selectedAgent->name : null"
            label="Agent" />

        {{-- Hidden input to ensure value is submitted if dropdown is untouched --}}
        <input type="hidden" name="agent_id_fallback" value="{{ $selectedAgent ? $selectedAgent->id : '' }}">
    </div>
@else
    <input type="hidden" name="agent_id" value="{{ auth()->user()->id }}">
@endunlessrole


                                        @php
                                        $selectedClient = \App\Models\Client::find($payment->client_id);
                                        $clientPlaceholder = $selectedClient ? $selectedClient->name : 'Select a Client';
                                        @endphp
                                        <div class="mb-4">
                                            <x-searchable-dropdown
                                                name="client_id"
                                                :items="$clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name])"
                                                :placeholder="$clientPlaceholder"
                                                :selectedName="$selectedClient ? $selectedClient->name : null"
                                                label="Client" />

                                            <input type="hidden" name="client_id_fallback" value="{{ $selectedClient ? $selectedClient->id : '' }}">
                                        </div>

                                        <label for="phone_{{ $payment->client_id }}" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                        @php
                                        $client = \App\Models\Client::find($payment->client_id);
                                        $placeholder = $client ? $client->country_code : 'Select Dial Code';
                                        @endphp
                                        <div class="flex gap-4 mb-4">
                                            <div class="w-2/5">
                                                <x-searchable-dropdown
                                                    name="dial_code"
                                                    :items="\App\Models\Country::all()->map(fn($country) => [
                                                        'id' => $country->dialing_code,
                                                        'name' => $country->dialing_code . ' ' . $country->name
                                                    ])"
                                                    :placeholder="$placeholder"
                                                    :selectedName="$client ? $client->country_code : null"
                                                    :showAllOnOpen="true" />

                                                <input type="hidden" name="dial_code_fallback" value="{{ $client ? $client->country_code : '' }}">
                                            </div>

                                            <div class="w-3/5">
                                                <input
                                                    type="text"
                                                    name="phone"
                                                    id="phone_{{ $payment->client_id }}"
                                                    value="{{ $client ? $client->phone : '' }}"
                                                    placeholder="Phone Number"
                                                    class="form-input w-full border rounded px-3 py-2"
                                                    required />
                                            </div>
                                        </div>

                                        <div class="mb-4" x-data="{ selectedGateway: '{{ $payment->payment_gateway ?? '' }}', selectedMethod: '{{ $payment->paymentMethod ? $payment->paymentMethod->id : '' }}' }">
                                            <div :class="selectedGateway === 'MyFatoorah' ? 'grid grid-cols-1 md:grid-cols-2 gap-6 items-start' : 'block'">
                                                <div>
                                                    <label for="payment-gateway" class="block text-sm font-medium text-gray-700">Payment Gateway</label>
                                                    <select name="payment_gateway" id="payment_gateway"
                                                        class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        x-model="selectedGateway">
                                                        <option value="" disabled>Select Payment Gateway</option>
                                                        @foreach ($paymentGateways as $gateway)
                                                        <option value="{{ $gateway->name }}"
                                                            @if ($payment->payment_gateway === $gateway->name) selected @endif>
                                                            {{ $gateway->name }}
                                                        </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <template x-if="selectedGateway === 'MyFatoorah'">
                                                    <div>
                                                        <label for="payment-method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                                                        <select name="payment_method_id" id="payment_method_id"
                                                            class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            x-model="selectedMethod">
                                                            <option value="" disabled>Select Method</option>
                                                            @foreach ($paymentMethods as $method)
                                                            <option value="{{ $method->id }}"
                                                                @if ($payment->payment_method_id === $method->id) selected @endif>
                                                                {{ $method->english_name }}
                                                            </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                                            <input type="text" name="amount" id="amount" value="{{ $payment->amount }}"
                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div class="flex justify-between space-x-4">
                                            <button type="button" @click="editPaymentLink = false" class="rounded-full shadow-md border border-gray-200 hover:bg-gray-400 px-4 py-2">Cancel</button>
                                            <button type="submit" class="rounded-full shadow-md border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 px-4 py-2">Update</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <form action="" method="POST" class="inline-block">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="flex items-center justify-center p-2 border-2 border-transparent rounded-full hover:border-red-500 hover:bg-red-50 transition-all duration-200 ease-in-out transform hover:scale-105" data-tooltip-left="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" class="text-red-500 hover:text-red-700" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 7H5V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3z"></path>
                                    <path d="M10 11v6m4-6v6"></path>
                                    <path d="M5 7v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7H5z"></path>
                                </svg>
                            </button>
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