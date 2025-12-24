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
                <div x-data="{ openFilters: false }" class="mb-4 p-2">
                    <div class="flex items-center gap-3 md:flex-nowrap">
                        <x-search
                            :action="route('payment.link.index')"
                            searchParam="q"
                            placeholder="Quick search for payments" />

                        <div class="shrink-0 flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-700 whitespace-nowrap">Select a date:</span>
                            <input type="text"
                                id="payment-date-range"
                                class="border-gray-300 rounded-full shadow-sm focus:ring-blue-500 focus:border-blue-500 px-4 py-2 text-sm cursor-pointer"
                                style="min-width: 240px;"
                                placeholder="Choose date range">
                        </div>

                        <button @click="openFilters = !openFilters"
                            class="shrink-0 inline-flex items-center gap-2 rounded-full bg-amber-100 px-4 py-2 text-sm text-amber-800 ring-1 ring-amber-200 hover:bg-amber-200 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path d="M4 6h16M7 12h10M10 18h4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Filters
                            @if (!empty($filters))
                            <span class="ml-1 rounded-full bg-blue-600 px-2 py-0.5 text-xs font-semibold text-white">
                                {{ collect($filters)->filter()->count() }}
                            </span>
                            @endif
                        </button>
                    </div>

                    <!-- Hidden form to submit date filters -->
                    <form id="date-filter-form" action="{{ route('payment.link.index') }}" method="GET" class="hidden">
                        <input type="hidden" name="q" value="{{ request('q') }}" />
                        <input type="hidden" name="filter[date_from]" id="date_from" value="{{ data_get($filters, 'date_from') }}">
                        <input type="hidden" name="filter[date_to]" id="date_to" value="{{ data_get($filters, 'date_to') }}">
                        @foreach(request()->except(['filter', 'q']) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach
                        @foreach(request('filter', []) as $filterKey => $filterValue)
                        @if(!in_array($filterKey, ['date_from', 'date_to']))
                        <input type="hidden" name="filter[{{ $filterKey }}]" value="{{ $filterValue }}">
                        @endif
                        @endforeach
                    </form>

                    <div x-show="openFilters" x-cloak x-transition
                        class="mt-3 rounded-xl border border-gray-200 bg-gray-50/70 shadow-sm">
                        <div class="flex items-center justify-between gap-2 border-b border-dashed border-gray-200 px-4 py-3">
                            <span class="text-sm font-semibold text-gray-700">Filter payments</span>
                            <button @click="openFilters = false" class="rounded-full px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-200 hover:text-gray-700 transition">
                                Hide
                            </button>
                        </div>
                        <form action="{{ route('payment.link.index') }}" method="GET" class="px-4 pt-4">
                            <input type="hidden" name="q" value="{{ request('q') }}" />
                            <input type="hidden" name="filter[date_from]" value="{{ data_get($filters, 'date_from') }}">
                            <input type="hidden" name="filter[date_to]" value="{{ data_get($filters, 'date_to') }}">

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                                <x-searchable-dropdown
                                    name="filter[client_id]"
                                    :items="$clients->map(fn($c) => [
                                        'id' => $c->id, 
                                        'name' => $c->full_name . ' - ' . $c->phone
                                    ])"
                                    :placeholder="'Select clients'"
                                    :selectedName="optional($clients->firstWhere('id', data_get($filters,'client_id')))->name"
                                    label="Client" />

                                <x-searchable-dropdown
                                    name="filter[agent_id]"
                                    :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])"
                                    :placeholder="'Select agents'"
                                    :selectedName="optional($agents->firstWhere('id', data_get($filters,'agent_id')))->name"
                                    label="Agent" />

                                <x-searchable-dropdown
                                    name="filter[created_by]"
                                    :items="$users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])"
                                    :placeholder="'Select users'"
                                    :selectedName="optional($users->firstWhere('id', data_get($filters,'created_by')))->name"
                                    label="Created By" />

                                <x-searchable-dropdown
                                    name="filter[payment_gateway]"
                                    :items="$paymentGateways->map(fn($g) => ['id' => $g->name, 'name' => $g->name])"
                                    :placeholder="'Select gateways'"
                                    :selectedName="data_get($filters,'payment_gateway')"
                                    label="Payment Gateway" />


                                <!-- dynamic payment methods from javascript -->

                                <x-searchable-dropdown
                                    name="filter[status]"
                                    :items="collect($status)->map(fn($s) => ['id' => $s, 'name' => ucfirst($s)])"
                                    :placeholder="'Select status'"
                                    :selectedName="data_get($filters,'status') ? ucfirst(data_get($filters,'status')) : null"
                                    label="Status" />
                            </div>
                            <div class="sticky bottom-0 -mx-4 mt-4 flex items-center justify-end gap-2 border-t border-gray-200 bg-white/80 px-4 py-3 backdrop-blur">
                                <a href="{{ route('payment.link.index', array_filter(['q' => request('q'), 'clear' => 1])) }}"
                                    class="rounded-full bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                                    Clear
                                </a>
                                <button type="submit"
                                    class="rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 shadow-sm">
                                    Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                @if ($payments->isEmpty())
                <p class="text-gray-500">No payment links found.</p>
                @else
                <div class="overflow-x-auto relative z-0">
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
                                <th class="p-3 text-left font-medium whitespace-nowrap">Client Pay</th>
                                <th class="p-3 text-left font-medium whitespace-nowrap">Created At</th>
                                <th class="p-3 text-left font-medium whitespace-nowrap">Created By</th>
                                <th class="p-3 text-left font-medium whitespace-nowrap">Reference</th>
                                <th class="p-3 text-left font-medium whitespace-nowrap">Status</th>
                                <th class="p-3 text-left font-medium whitespace-nowrap sticky right-0 bg-gray-100 shadow-[-2px_0_4px_rgba(0,0,0,0.1)]">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payments as $payment)
                            @php
                            $paymentUrl = route('payment.show', $payment->id);
                            @endphp
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <a href="{{ $paymentUrl }}" target="_blank"
                                        class="text-blue-500 hover:underline text-sm font-semibold">{{ $payment->voucher_number }}</a>
                                </td>
                                <td class="px-3 py-2 text-sm break-words max-w-[350px] font-semibold">
                                    {{ $payment->client ? $payment->client->full_name : 'N/A' }}
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
                                    {{ $method ? "$gateway - $method" : $gateway }}
                                </td>
                                <td class="px-3 py-2 text-sm break-words max-w-[350px]">
                                    {{ $payment->notes ?? 'No Notes' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm font-semibold">
                                    {{ number_format($payment->amount,3) }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm font-semibold">
                                    {{ number_format($payment->amount + $payment->service_charge,3) }}
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
                                    $payment_reference = $payment->myFatoorahPayment ? $payment->myFatoorahPayment->invoice_ref : $payment->payment_reference;
                                    if($payment_reference === null) {
                                    $payment_reference = 'N/A';
                                    }

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
                                <td class="px-3 py-2 whitespace-nowrap relative text-sm sticky right-0 bg-white shadow-[-2px_0_4px_rgba(0,0,0,0.1)]">
                                    <div x-data="{ open: false, editPaymentLink: false }" @keydown.escape.window="open = false; editPaymentLink = false" class="relative flex items-center justify-center h-full">
                                        <button @click="open = !open" x-ref="button" @click.outside="open = false" class="p-1 rounded hover:bg-gray-100">
                                            <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 13a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 20a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" />

                                            </svg>
                                        </button>
                                        <template x-teleport="body">
                                            <div x-cloak x-show="open" x-transition x-anchor.bottom-start.offset.5="$refs.button" class="absolute w-34 rounded-md bg-white shadow-lg border border-gray-200">
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
                                                <a href="{{ route('payment.link.show', [ 'companyId' => $payment->agent->branch->company_id, 'voucherNumber' => $payment->voucher_number, ]); }}" target="_blank" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <svg class="h-4 w-4 mr-1 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    View Invoice
                                                </a>
                                                <form action="{{ route('payment.link.payment.activation', $payment->id) }}" method="POST" class="block">
                                                    @csrf
                                                    @if ($payment->status !== 'completed' && !$payment->is_disabled)
                                                    <div class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <button class="flex items-center gap-2 w-full">
                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                class="h-5 w-5 mr-2 text-purple-500"
                                                                fill="none" viewBox="0 0 24 24"
                                                                stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M12 11c-1.657 0-3 1.343-3 3v3h6v-3c0-1.657-1.343-3-3-3z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 11V7a5 5 0 00-10 0v4" />
                                                                <rect x="5" y="11" width="14" height="10" rx="2" ry="2" />
                                                            </svg>
                                                            Disable Link
                                                        </button>
                                                    </div>
                                                    @elseif ($payment->status !== 'completed' && $payment->is_disabled)
                                                    <div class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <button class="flex items-center gap-2 w-full">
                                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                                class="h-5 w-5 mr-2 text-purple-500"
                                                                fill="none" viewBox="0 0 24 24"
                                                                stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 8a5 5 0 10-10 0v1" />
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M17 8v-2" />
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M12 11c-1.657 0-3 1.343-3 3v3h6v-3c0-1.657-1.343-3-3-3z" />
                                                                <rect x="5" y="11" width="14" height="10" rx="2" ry="2" />
                                                            </svg>
                                                            Enable Link
                                                        </button>
                                                    </div>
                                                    @endif
                                                </form>
                                                @if ($payment->status === 'pending')
                                                <div class="border-t border-gray-200 my-1"></div>
                                                <button @click="editPaymentLink = true; open = false" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-blue-600 hover:bg-blue-50">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path d="M12 20h9M15 3l6 6-9 9H6v-6l9-9z" />
                                                    </svg>
                                                    Edit
                                                </button>
                                                <form action="{{ route('payment.link.delete', $payment->id) }}" method="POST" class="block">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </form>
                                                @endif
                                            </div>
                                        </template>
                                        <template x-teleport="body">
                                            <div x-cloak x-show="editPaymentLink" class="fixed inset-0 z-10 flex items-center justify-center bg-gray-500 bg-opacity-50">
                                                <div
                                                    class="bg-white p-6 rounded shadow-lg w-full max-w-md relative">
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
                                                            <x-searchable-dropdown name="agent_id"
                                                                :items="$agents->map(
                                                                        fn($a) => [
                                                                            'id' => $a->id,
                                                                            'name' => $a->name,
                                                                        ],
                                                                    )" :placeholder="$agentPlaceholder"
                                                                :selectedName="$selectedAgent ? $selectedAgent->name : null" label="Agent" />
                                                        </div>
                                                        @else
                                                        <div class="mb-4">
                                                            <input type="hidden" name="agent_id" value="{{ auth()->user()->agent->id }}">
                                                        </div>
                                                        @endunlessrole

                                                        @php
                                                        $selectedClient = \App\Models\Client::find($payment->client_id);
                                                        $clientPlaceholder = $selectedClient ? $selectedClient->full_name : 'Select a Client';
                                                        @endphp
                                                        <div class="mb-4">
                                                            <x-searchable-dropdown name="client_id"
                                                                :items="$clients->map(
                                                                        fn($c) => [
                                                                            'id' => $c->id,
                                                                            'name' => $c->name . ' - ' . $c->phone
                                                                        ],
                                                                    )" :placeholder="$clientPlaceholder"
                                                                :selectedName="$selectedClient ? $selectedClient->full_name : null" label="Client" />
                                                            <input type="hidden" name="client_id_fallback" value="{{ $selectedClient ? $selectedClient->id : '' }}">
                                                        </div>

                                                        <label for="phone_{{ $payment->client_id }}" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                                        @php
                                                        $client = \App\Models\Client::find($payment->client_id);
                                                        $placeholder = $client ? $client->country_code : 'Select Dial Code';
                                                        @endphp
                                                        <div class="flex gap-4 mb-4">
                                                            <div class="w-2/5">
                                                                <x-searchable-dropdown name="dial_code"
                                                                    :items="\App\Models\Country::all()->map(
                                                                            fn($country) => [
                                                                                'id' => $country->dialing_code,
                                                                                'name' => $country->dialing_code . ' ' . $country->name,
                                                                            ],
                                                                        )" :placeholder="$placeholder"
                                                                    :selectedName="$client ? $client->country_code : null" :showAllOnOpen="true" />
                                                                <input type="hidden" name="dial_code_fallback" value="{{ $client ? $client->country_code : '' }}">
                                                            </div>

                                                            <div class="w-3/5">
                                                                <input type="text" name="phone" id="phone_{{ $payment->client_id }}" value="{{ $client ? $client->phone : '' }}"
                                                                    placeholder="Phone Number" class="form-input w-full border rounded px-3 py-2" required />
                                                            </div>
                                                        </div>
                                                        @if ($payment->payment_gateway == 'Multi')
                                                            @php
                                                            $prefill = session('prefill_data');
                                                            $selectedGateway = $prefill['payment_gateway'] ?? old('payment_gateway');
                                                            @endphp
                                                           <div class="mb-4">
                                                                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                                                                <div class="flex flex-wrap gap-8">
                                                                    @php
                                                                        // Get existing payment method GROUP IDs from pivot table
                                                                        $existingGroupIds = $payment->availablePaymentMethodGroups 
                                                                            ? $payment->availablePaymentMethodGroups->pluck('id')->toArray() 
                                                                            : [];
                                                                    @endphp
                                                                    @foreach ($paymentMethodChose as $chose)
                                                                    <div class="flex items-center gap-4">
                                                                        <div class="flex">
                                                                            <input 
                                                                                type="checkbox" 
                                                                                name="payment_method_groups[]" 
                                                                                value="{{ $chose->paymentMethodGroup->id }}" 
                                                                                id="edit_payment_method_group_{{ $payment->id }}_{{ $chose->paymentMethodGroup->id }}"
                                                                                {{ in_array($chose->paymentMethodGroup->id, old('payment_method_groups', $existingGroupIds)) ? 'checked' : '' }}
                                                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                                            <label for="edit_payment_method_group_{{ $payment->id }}_{{ $chose->paymentMethodGroup->id }}" class="ml-2 text-sm text-gray-700">
                                                                                {{ $chose->paymentMethodGroup->name }}
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @else
                                                            <div class="mb-4" x-data="{ 
                                                                selectedGateway: '{{ $payment->payment_gateway ?? '' }}', 
                                                                selectedMethod: '{{ $payment->selected_method ?? '' }}',
                                                                gatewaysWithMethods: @js($paymentGateways->filter(fn($g) => $g->methods->isNotEmpty())->pluck('name')->toArray()),
                                                                hasMethod() {
                                                                    return this.gatewaysWithMethods.includes(this.selectedGateway);
                                                                }
                                                            }">
                                                                <div :class="hasMethod() ? 'grid grid-cols-1 md:grid-cols-2 gap-6 items-start' : 'block'">
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
                                                                            @if($gateway->methods)
                                                                            <template>
                                                                                <div>
                                                                                    <label for="payment_method_{{ $gateway->id }}">
                                                                                        {{ $gateway->name }} Methods
                                                                                    </label>
                                                                                    <select name="payment_method_{{ $gateway->id }}" id="payment_method_{{ $gateway->id }}"
                                                                                        class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                                        x-show="selectedGateway === '{{ $gateway->name }}'"
                                                                                        x-model="selectedMethod">
                                                                                        <option value="" disabled>Select Method</option>
                                                                                        @foreach ($gateway->methods as $method)
                                                                                        <option value="{{ $method->id }}"
                                                                                            @if ($payment->payment_method_id === $method->id) selected @endif>
                                                                                            {{ $method->english_name }}
                                                                                        </option>
                                                                                        @endforeach
                                                                                </div>
                                                                            </template>
                                                                            @endif
                                                                            @endforeach
                                                                        </select>
                                                                    </div>

                                                                    @foreach($paymentGateways as $gateway)
                                                                    @if($gateway->methods)
                                                                    <template x-if="selectedGateway === '{{ $gateway->name }}'">
                                                                        <div x-cloak>
                                                                            <label for="payment_method_id" class="block text-sm font-medium text-gray-700">{{ $gateway->name }} Methods</label>
                                                                            <select name="payment_method_id" id="payment_method"
                                                                                class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                                                <option value="" disabled>Select Method</option>
                                                                                @foreach ($gateway->methods as $method)
                                                                                <option value="{{ $method->id }}"
                                                                                    @if ($payment->payment_method_id === $method->id) selected @endif>
                                                                                    {{ $method->english_name }}
                                                                                </option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                    </template>
                                                                    @endif
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                        
                                                        <div class="mb-4">
                                                            <label for="amount" class="block text-sm font-medium text-gray-700">Amount</label>
                                                            <input type="text" name="amount" id="amount" value="{{ $payment->amount }}"
                                                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                        </div>

                                                        <!-- Language -->
                                                        <div class="mb-4">
                                                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Invoice Language</label>
                                                            <div x-data="{ language: '{{ $payment->language ?? 'EN' }}' }" class="inline-flex rounded-lg border border-gray-300 p-1 bg-gray-100">
                                                                <input type="hidden" name="language" :value="language">

                                                                <button type="button"
                                                                    @click="language = 'EN'"
                                                                    :class="language === 'EN' 
                                                                        ? 'bg-white text-gray-900 shadow-sm' 
                                                                        : 'text-gray-500 hover:text-gray-700'"
                                                                    class="flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all">
                                                                    <span>🇬🇧</span> English
                                                                </button>

                                                                <button type="button"
                                                                    @click="language = 'ARB'"
                                                                    :class="language === 'ARB' 
                                                                        ? 'bg-white text-gray-900 shadow-sm' 
                                                                        : 'text-gray-500 hover:text-gray-700'"
                                                                    class="flex items-center gap-2 px-4 py-2 rounded-md text-sm font-medium transition-all">
                                                                    <span>🇸🇦</span> العربية
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div class="flex justify-between space-x-4">
                                                            <button type="button" @click="editPaymentLink = false"
                                                                class="rounded-full shadow-md border border-gray-200 hover:bg-gray-400 px-4 py-2">Cancel</button>
                                                            <button type="submit"
                                                                class="rounded-full shadow-md border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 px-4 py-2">Update</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <!-- Pagination Links -->
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

                <x-pagination :data="$payments" />

            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const dateFromInput = document.getElementById('date_from');
                    const dateToInput = document.getElementById('date_to');
                    const dateFilterForm = document.getElementById('date-filter-form');
                    const dateFrom = dateFromInput ? dateFromInput.value : '';
                    const dateTo = dateToInput ? dateToInput.value : '';

                    const dateRangeInput = document.getElementById('payment-date-range');
                    if (dateRangeInput) {
                        const fp = flatpickr("#payment-date-range", {
                            mode: "range",
                            dateFormat: "Y-m-d",
                            defaultDate: (dateFrom && dateTo) ? [dateFrom, dateTo] : null,
                            onChange: function(selectedDates, dateStr, instance) {
                                if (selectedDates.length === 2) {
                                    dateFromInput.value = instance.formatDate(selectedDates[0], 'Y-m-d');
                                    dateToInput.value = instance.formatDate(selectedDates[1], 'Y-m-d');

                                    // Auto-submit the form when date range is selected
                                    setTimeout(() => {
                                        dateFilterForm.submit();
                                    }, 100);
                                } else if (selectedDates.length === 0) {
                                    // Clear dates and submit when cleared
                                    dateFromInput.value = '';
                                    dateToInput.value = '';
                                    setTimeout(() => {
                                        dateFilterForm.submit();
                                    }, 100);
                                }
                            }
                        });
                    }
                });
            </script>
</x-app-layout>