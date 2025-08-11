<x-app-layout>
    <div class="min-h-screen flex flex-col">
        <div class="flex-1 pb-16">
            <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
                <li>
                    <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
                </li>
                <li class="before:content-['/'] before:mr-1 ">
                    <span>Payment Links</span>
                </li>
            </ul>
            <div class="p-2 bg-white rounded shadow">
                <div class="flex justify-between items-center mb-2 p-2">
                    <h2 class="text-xl font-semibold">Payment Links</h2>
                    <a href="{{ route('payment.link.create') }}"
                        class="bg-blue-600 hover:bg-blue-700 rounded-full shadow-md text-white px-4 py-2">Create/Import Payment
                        Link</a>
                </div>
                <div class="flex justify-between items-center mb-4 p-2">
                    <form action="{{ route('payment.link.index') }}" method="GET" class="flex items-center gap-2 w-1/2">
                        <div class="relative w-full">
                            <input type="text" name="q" value="{{ request('q') }}" id="searchInput" placeholder=" "
                                class="block py-2.2 w-full text-sm text-gray-900 bg-transparent border-b-2 border-gray-300 appearance-none
                                    dark:text-white dark:border-gray-600 dark:focus:border-blue-500 focus:outline-none focus:ring-0
                                    focus:border-blue-600 peer rounded-full" />
                            <label for="searchInput" class="absolute text-md text-gray-500 dark:text-gray-400 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0]
                                    bg-white dark:bg-gray-900 px-2 peer-focus:text-blue-600 peer-focus:dark:text-blue-500
                                    peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2
                                    peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 start-1">
                                Quick search for payments
                            </label>
                        </div>
                        <button type="submit" id="searchButton" data-tooltip="Search"
                            class="relative group bg-blue-200 hover:bg-blue-600 text-black hover:text-white
                                w-9 h-8 flex items-center justify-center rounded-full transition duration-300 ring-offset-2">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <circle cx="11.5" cy="11.5" r="9.5"
                                        stroke="currentColor" stroke-width="1.5" opacity="0.5" />
                                <path d="M18.5 18.5L22 22"
                                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
                        </button>
                        <button type="button" data-tooltip="Reset" onclick="window.location='{{ route('payment.link.index') }}'" id="resetButton"
                            class="relative group bg-red-200 hover:bg-red-500 text-black hover:text-white
                                w-9 h-8 flex items-center justify-center rounded-full
                                {{ request('q') ? 'opacity-100 scale-100 pointer-events-auto' : 'opacity-0 scale-95 pointer-events-none' }}
                                transition-all duration-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </form>
                </div>

                @if ($payments->isEmpty())
                    <p class="text-gray-500">No payment links found.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-gray-200">
                            <thead class="bg-gray-100 sticky top-0 z-10">
                                <tr>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Invoice Link</th>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Client</th>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Client Contact</th>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Agent</th>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Payment Type</th>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Notes</th>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Amount</th>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Created At</th>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Created By</th>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Reference</th>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Status</th>
                                    <th class="p-3 text-left font-medium whitespace-nowrap">Actions</th>
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
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            <a href="{{ $paymentUrl }}" target="_blank"
                                                class="text-blue-500 hover:underline text-sm font-semibold">{{ $payment->voucher_number }}</a>
                                        </td>
                                        <td class="px-3 py-2 text-sm break-words max-w-[350px] font-semibold">
                                            {{ $payment->client ? $payment->client->name : 'N/A' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm font-semibold">
                                            {{ $payment->client ? $payment->client->country_code . $payment->client->phone : 'N/A' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm font-semibold">
                                            {{ $payment->agent ? $payment->agent->name : 'N/A' }}
                                        </td>
                                        <td class="px-3 py-2 break-words text-sm">
                                            @php
                                                $gateway = $payment->payment_gateway ?? 'N/A';
                                                $method = $payment->paymentMethod->english_name ?? null;
                                            @endphp
                                            {{ $gateway === 'MyFatoorah' && $method ? "$gateway - $method" : $gateway }}
                                        </td>
                                        <td class="px-3 py-2 text-sm break-words max-w-[350px]">
                                            {{ $payment->notes ?? 'No Notes' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm font-semibold">
                                            {{ $payment->amount }}
                                        </td>
                                        @if (auth()->user()->role->name === 'admin' || auth()->user()->role->name === 'company')
                                            <td class="px-3 py-2 whitespace-nowrap text-sm">
                                                {{ $payment->created_at->format('d-m-Y H:i:s') }}
                                            </td>
                                        @else
                                            <td class="px-3 py-2 text-sm break-words max-w-[200px]">
                                                {{ $payment->created_at->format('D d M Y') }}
                                            </td>
                                        @endif
                                        <td class="px-3 py-2 whitespace-nowrap text-sm">
                                            {{ $payment->createdBy ? $payment->createdBy->name : 'N/A' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm font-semibold">
                                            @php
                                                $payment_reference = $payment->payment_reference
                                                    ? ($payment->invoice_ref
                                                        ? $payment->payment_reference . '/' . $payment->invoice_ref
                                                        : $payment->payment_reference)
                                                    : 'N/A';
                                                $isTrimmed = strlen($payment_reference) > 15;
                                                $trimmedValue = \Illuminate\Support\Str::limit($payment_reference, 15);
                                            @endphp

                                            @if ($isTrimmed)
                                                <span x-data="{ showFullData: false }">
                                                    <span x-show="!showFullData" @click="showFullData = !showFullData"
                                                        class="cursor-pointer hover:text-purple-700"
                                                        data-tooltip-left="Click to expand">
                                                        {{ $trimmedValue }}
                                                    </span>

                                                    <span x-show="showFullData" @click="showFullData = !showFullData"
                                                        class="cursor-pointer hover:text-purple-500">
                                                        {{ $payment_reference }}
                                                    </span>
                                                </span>
                                            @else
                                                <span>{{ $payment_reference }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm">
                                            @php
                                                $statusColors = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-600',
                                                    'completed' => 'bg-green-100 text-green-800 border-green-600',
                                                    'failed' => 'bg-red-100 text-red-800 border-red-600',
                                                    'cancelled' => 'bg-gray-100 text-gray-600 border-gray-600',
                                                ];
                                                $status = strtolower($payment->status);
                                                $colorClass =
                                                    $statusColors[$status] ??
                                                    'bg-gray-100 text-gray-800 border-gray-600';
                                            @endphp
                                            <span
                                                class="inline-block px-4 py-2 rounded-full font-semibold text-center {{ $colorClass }} border-2 transition-all duration-200 ease-in-out transform hover:scale-105 hover:shadow-lg">
                                                {{ ucfirst($payment->status) }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap relative text-sm">
                                            <div x-data="{ open: false, editPaymentLink: false }" class="relative inline-block text-left">
                                                <button @click="open = !open" @click.outside="open = false" class="p-1 rounded hover:bg-gray-100">
                                                    <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 13a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 20a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" />
                                                    </svg>
                                                </button>
                                                <div x-cloak x-show="open" x-transition class="absolute right-[-20px] mt-2 w-46 bg-white border border-gray-200 rounded-md shadow-lg z-50">
                                                    <form action="{{ route('resayil.share-payment-link') }}" method="POST" class="block">
                                                        @csrf
                                                        <input type="hidden" name="client_id" value="{{ $payment->client_id }}">
                                                        <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                                        <input type="hidden" name="voucher_number" value="{{ $payment->voucher_number }}">
                                                        <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <svg class="h-5 w-5 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                                        </svg>
                                                        Send Link
                                                        </button>
                                                    </form>
                                                    <button onclick="copyToClipboard('{{ $paymentUrl }}')" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <svg class="h-5 w-5 mr-2 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M8 16h8M8 12h8m-6 8h6a2 2 0 002-2V7a2 2 0 00-2-2H9m-2 0H7a2 2 0 00-2 2v12a2 2 0 002 2h2V5z" />
                                                        </svg>
                                                        Copy Link
                                                    </button>
                                                    <a href="{{ $paymentUrl }}" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <svg class="h-4 w-4 mr-1 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                        View Invoice
                                                    </a>

                                                    @if ($payment->status === 'pending')
                                                    <div class="border-t border-gray-200 my-1"></div>
                                                    <button @click="editPaymentLink = true; open = false" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-blue-600 hover:bg-blue-50">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path d="M12 20h9M15 3l6 6-9 9H6v-6l9-9z"/>
                                                        </svg>
                                                        Edit    
                                                    </button>
                                                    <form action="{{ route('payment.link.delete', $payment->id) }}" method="POST" class="block">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" 
                                                                class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                        Delete
                                                        </button>
                                                    </form>
                                                    @endif
                                                </div>
                                                <div x-cloak x-transition x-show="editPaymentLink" class="fixed inset-0 z-10 bg-gray-500 bg-opacity-50 flex items-center justify-center">
                                                    <div
                                                        class="bg-white p-6 rounded shadow-lg w-full max-w-md relative">
                                                        <div class="flex items-center justify-between mb-6">
                                                            <div>
                                                                <h2 class="text-xl font-bold text-gray-800">Edit
                                                                    Payment
                                                                    Link Details</h2>
                                                                <p class="text-gray-600 italic text-xs mt-1">Please
                                                                    update
                                                                    the payment link details to ensure accurate
                                                                    information
                                                                </p>
                                                            </div>
                                                            <button @click="editPaymentLink = false"
                                                                class="absolute top-2 right-2 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                                                &times;
                                                            </button>
                                                        </div>
                                                        <form
                                                            action="{{ route('payment.link.update', $payment->id) }}"
                                                            method="POST">
                                                            @csrf
                                                            @method('PUT')
                                                            @unlessrole('agent')
                                                                @php
                                                                    $selectedAgent = \App\Models\Agent::find(
                                                                        $payment->agent_id,
                                                                    );
                                                                    $agentPlaceholder = $selectedAgent
                                                                        ? $selectedAgent->name
                                                                        : 'Select an Agent';
                                                                @endphp

                                                                <div class="mb-4">
                                                                    <x-searchable-dropdown name="agent_id"
                                                                        :items="$agents->map(
                                                                            fn($a) => [
                                                                                'id' => $a->id,
                                                                                'name' => $a->name,
                                                                            ],
                                                                        )" :placeholder="$agentPlaceholder"
                                                                        :selectedName="$selectedAgent
                                                                            ? $selectedAgent->name
                                                                            : null" label="Agent" />
                                                                </div>
                                                            @else
                                                                <div class="mb-4">
                                                                    <input type="hidden" name="agent_id"
                                                                        value="{{ auth()->user()->agent->id }}">
                                                                </div>
                                                            @endunlessrole

                                                            @php
                                                                $selectedClient = \App\Models\Client::find(
                                                                    $payment->client_id,
                                                                );
                                                                $clientPlaceholder = $selectedClient
                                                                    ? $selectedClient->name
                                                                    : 'Select a Client';
                                                            @endphp
                                                            <div class="mb-4">
                                                                <x-searchable-dropdown name="client_id"
                                                                    :items="$clients->map(
                                                                        fn($c) => [
                                                                            'id' => $c->id,
                                                                            'name' => $c->name,
                                                                        ],
                                                                    )" :placeholder="$clientPlaceholder"
                                                                    :selectedName="$selectedClient
                                                                        ? $selectedClient->name
                                                                        : null" label="Client" />

                                                                <input type="hidden" name="client_id_fallback"
                                                                    value="{{ $selectedClient ? $selectedClient->id : '' }}">
                                                            </div>

                                                            <label for="phone_{{ $payment->client_id }}"
                                                                class="block text-sm font-medium text-gray-700">Phone
                                                                Number</label>
                                                            @php
                                                                $client = \App\Models\Client::find(
                                                                    $payment->client_id,
                                                                );
                                                                $placeholder = $client
                                                                    ? $client->country_code
                                                                    : 'Select Dial Code';
                                                            @endphp
                                                            <div class="flex gap-4 mb-4">
                                                                <div class="w-2/5">
                                                                    <x-searchable-dropdown name="dial_code"
                                                                        :items="\App\Models\Country::all()->map(
                                                                            fn($country) => [
                                                                                'id' => $country->dialing_code,
                                                                                'name' =>
                                                                                    $country->dialing_code .
                                                                                    ' ' .
                                                                                    $country->name,
                                                                            ],
                                                                        )" :placeholder="$placeholder"
                                                                        :selectedName="$client
                                                                            ? $client->country_code
                                                                            : null" :showAllOnOpen="true" />

                                                                    <input type="hidden"
                                                                        name="dial_code_fallback"
                                                                        value="{{ $client ? $client->country_code : '' }}">
                                                                </div>

                                                                <div class="w-3/5">
                                                                    <input type="text" name="phone"
                                                                        id="phone_{{ $payment->client_id }}"
                                                                        value="{{ $client ? $client->phone : '' }}"
                                                                        placeholder="Phone Number"
                                                                        class="form-input w-full border rounded px-3 py-2"
                                                                        required />
                                                                </div>
                                                            </div>

                                                            <div class="mb-4" x-data="{ selectedGateway: '{{ $payment->payment_gateway ?? '' }}', selectedMethod: '{{ $payment->paymentMethod ? $payment->paymentMethod->id : '' }}' }">
                                                                <div
                                                                    :class="selectedGateway === 'MyFatoorah' ?
                                                                        'grid grid-cols-1 md:grid-cols-2 gap-6 items-start' :
                                                                        'block'">
                                                                    <div>
                                                                        <label for="payment-gateway"
                                                                            class="block text-sm font-medium text-gray-700">Payment
                                                                            Gateway</label>
                                                                        <select name="payment_gateway"
                                                                            id="payment_gateway"
                                                                            class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                            x-model="selectedGateway">
                                                                            <option value="" disabled>Select
                                                                                Payment
                                                                                Gateway</option>
                                                                            @foreach ($paymentGateways as $gateway)
                                                                                <option
                                                                                    value="{{ $gateway->name }}"
                                                                                    @if ($payment->payment_gateway === $gateway->name) selected @endif>
                                                                                    {{ $gateway->name }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>

                                                                    <template
                                                                        x-if="selectedGateway === 'MyFatoorah'">
                                                                        <div>
                                                                            <label for="payment-method"
                                                                                class="block text-sm font-medium text-gray-700">Payment
                                                                                Method</label>
                                                                            <select name="payment_method_id"
                                                                                id="payment_method_id"
                                                                                class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                                x-model="selectedMethod">
                                                                                <option value="" disabled>
                                                                                    Select
                                                                                    Method</option>
                                                                                @foreach ($paymentMethods as $method)
                                                                                    <option
                                                                                        value="{{ $method->id }}"
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
                                                                <label for="amount"
                                                                    class="block text-sm font-medium text-gray-700">Amount</label>
                                                                <input type="text" name="amount"
                                                                    id="amount" value="{{ $payment->amount }}"
                                                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                            </div>
                                                            <div class="flex justify-between space-x-4">
                                                                <button type="button"
                                                                    @click="editPaymentLink = false"
                                                                    class="rounded-full shadow-md border border-gray-200 hover:bg-gray-400 px-4 py-2">Cancel</button>
                                                                <button type="submit"
                                                                    class="rounded-full shadow-md border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 px-4 py-2">Update</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <!-- Pagination Links -->
                        <div class="dataTable-bottom justify-center my-3">
                            @if ($payments->hasPages())
                            <nav class="dataTable-pagination">
                                <ul class="dataTable-pagination-list flex gap-2 mt-4">
                                    {{-- Previous Page Link --}}
                                    @if ($payments->onFirstPage())
                                    <li class="pager disabled">
                                        <span class="px-3 py-2 text-gray-400 cursor-not-allowed">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                                <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                        </span>
                                    </li>
                                    @else
                                    <li class="pager">
                                        <a href="{{ $payments->previousPageUrl() }}" class="px-3 py-2 text-blue-600 hover:text-blue-800">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                                <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                        </a>
                                    </li>
                                    @endif

                                    @php
                                    $currentPage = $payments->currentPage();
                                    $lastPage = $payments->lastPage();
                                    $maxPagesToShow = 10;

                                    if ($lastPage <= $maxPagesToShow) {
                                        // Show all pages if total pages <=10
                                        $startPage=1;
                                        $endPage=$lastPage;
                                        } else {
                                        // Calculate dynamic range
                                        $halfRange=floor($maxPagesToShow / 2);

                                        if ($currentPage <=$halfRange) {
                                        // Near the beginning
                                        $startPage=1;
                                        $endPage=$maxPagesToShow;
                                        } elseif ($currentPage> $lastPage - $halfRange) {
                                        // Near the end
                                        $startPage = $lastPage - $maxPagesToShow + 1;
                                        $endPage = $lastPage;
                                        } else {
                                        // In the middle
                                        $startPage = $currentPage - $halfRange;
                                        $endPage = $currentPage + $halfRange - 1;
                                        }
                                        }
                                        @endphp

                                        @if ($lastPage > $maxPagesToShow && $startPage > 1)
                                        <li class="pager">
                                            <a href="{{ $payments->url(max(1, $startPage - $maxPagesToShow)) }}" class="px-3 py-2 text-blue-600 hover:text-blue-800" title="Previous {{ $maxPagesToShow }} pages">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                                    <path d="M18 17L12 11L18 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    <path opacity="0.5" d="M12 17L6 11L12 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                </svg>
                                            </a>
                                        </li>
                                        @endif

                                        @if ($startPage > 1)
                                        <li class="pager">
                                            <a href="{{ $payments->url(1) }}" class="px-3 py-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded">1</a>
                                        </li>
                                        @if ($startPage > 2)
                                        <li class="pager disabled">
                                            <span class="px-3 py-2 text-gray-400">...</span>
                                        </li>
                                        @endif
                                        @endif

                                        @for ($page = $startPage; $page <= $endPage; $page++)
                                            @if ($page==$currentPage)
                                            <li class="pager active">
                                            <span class="px-3 py-2 bg-blue-600 text-white rounded">{{ $page }}</span>
                                            </li>
                                            @else
                                            <li class="pager">
                                                <a href="{{ $payments->url($page) }}" class="px-3 py-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded">{{ $page }}</a>
                                            </li>
                                            @endif
                                            @endfor

                                            @if ($endPage < $lastPage)
                                                @if ($endPage < $lastPage - 1)
                                                <li class="pager disabled">
                                                <span class="px-3 py-2 text-gray-400">...</span>
                                                </li>
                                                @endif
                                                <li class="pager">
                                                    <a href="{{ $payments->url($lastPage) }}" class="px-3 py-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded">{{ $lastPage }}</a>
                                                </li>
                                                @endif

                                                @if ($lastPage > $maxPagesToShow && $endPage < $lastPage)
                                                    <li class="pager">
                                                    <a href="{{ $payments->url(min($lastPage, $endPage + $maxPagesToShow)) }}" class="px-3 py-2 text-blue-600 hover:text-blue-800" title="Next {{ $maxPagesToShow }} pages">
                                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                                            <path d="M6 17L12 11L6 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path opacity="0.5" d="M12 17L18 11L12 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                        </svg>
                                                    </a>
                                                    </li>
                                                    @endif

                                                    @if ($payments->hasMorePages())
                                                    <li class="pager">
                                                        <a href="{{ $payments->nextPageUrl() }}" class="px-3 py-2 text-blue-600 hover:text-blue-800">
                                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                                                <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            </svg>
                                                        </a>
                                                    </li>
                                                    @else
                                                    <li class="pager disabled">
                                                        <span class="px-3 py-2 text-gray-400 cursor-not-allowed">
                                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                                                <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                                <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            </svg>
                                                        </span>
                                                    </li>
                                                    @endif
                                </ul>
                            </nav>

                            {{-- Page Info --}}
                            <div class="text-center mt-3 text-sm text-gray-600 dark:text-gray-400">
                                Showing {{ $payments->firstItem() }} to {{ $payments->lastItem() }} of {{ $payments->total() }} results
                            </div>
                            @endif
                        </div>
                    <div>
                @endif
            </div>
            <script>
                function copyToClipboard(text) {
                    navigator.clipboard.writeText(text).then(function() {
                        const toast = document.createElement('div');
                        toast.textContent = 'Link copied to clipboard!';
                        toast.className =
                            'alert alert-success fixed mt-5 top-1 right-4 bg-green-500 text-white p-4 rounded shadow-lg';
                        toast.innerHTML = `
                    <span class="mr-4">${toast.textContent}</span>
                    <button type="button" class="text-white font-bold" aria-label="Close" onclick="this.parentElement.remove()">
                        &times;
                    </button>
                `;
                        document.body.appendChild(toast);

                        setTimeout(() => {
                            toast.style.opacity = '0';
                            setTimeout(() => {
                                toast.remove();
                            }, 300);
                        }, 2500);
                    }).catch(function(err) {
                        console.error('Copy failed:', err);
                        alert('Could not copy. Please try again.');
                    });
                }
            </script>
        </div>

    </div>
</x-app-layout>
