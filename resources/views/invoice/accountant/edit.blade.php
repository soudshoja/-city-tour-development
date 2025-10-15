<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">
            {{ __('Edit Invoice') }} - {{ $invoice->invoice_number }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">

                    <!-- Status Badge -->
                    <div class="mb-6">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            {{ $invoice->status == 'paid' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                               ($invoice->status == 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200') }}">
                            {{ ucfirst($invoice->status) }}
                        </span>
                    </div>

                    <form method="POST" action="{{ route('invoice.accountant.update') }}" class="space-y-8">
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                        <input type="hidden" name="company_id" value="{{ auth()->user()->accountant->branch->company_id }}">

                        <!-- Basic Information -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Basic Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                                <!-- Invoice Number -->
                                <div>
                                    <label for="invoice_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Invoice Number
                                    </label>
                                    <input id="invoice_number"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="text"
                                        name="invoice_number"
                                        value="{{ old('invoice_number', $invoice->invoice_number) }}"
                                        required />
                                    @error('invoice_number')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Client -->
                                <div>
                                    <label for="client_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Client
                                    </label>
                                    <x-searchable-dropdown
                                        name="client_id"
                                        id="client_id"
                                        :selectedName="$invoice->client ? $invoice->client->full_name . ' - ' . $invoice->client->phone : null"
                                        :selectedId="$invoice->client ? $invoice->client_id : null"
                                        :items="$clients->map(fn($client) => ['id' => $client->id, 'name' => $client->full_name . ' - ' . $client->phone])"
                                        placeholder="Select Client" />
                                    @error('client_id')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Agent -->
                                <div>
                                    <label for="agent_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Agent
                                    </label>
                                    <x-searchable-dropdown
                                        name="agent_id"
                                        :selectedId="$invoice->agent_id"
                                        :selectedName="$invoice->agent ? $invoice->agent->name : null"
                                        id="agent_id"
                                        :items="$agents->map(fn($agent) => ['id' => $agent->id, 'name' => $agent->name])"
                                        placeholder="Select Agent" />
                                    @error('agent_id')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Currency -->
                                <div>
                                    <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Currency
                                    </label>
                                    <input class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="text" name="currency" value="{{ old('currency', $invoice->currency) }}" />
                                    @error('currency')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Status -->
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Status
                                    </label>
                                    <select id="status"
                                        name="status"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                                        <option value="pending" {{ old('status', $invoice->status) == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="paid" {{ old('status', $invoice->status) == 'unpaid' ? 'selected' : '' }}>Paid</option>
                                    </select>
                                    @error('status')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Label -->
                                <div>
                                    <label for="label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Label
                                    </label>
                                    <input id="label"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="text"
                                        name="label"
                                        value="{{ old('label', $invoice->label) }}" />
                                    @error('label')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Financial Information -->
                        <div class="grid grid-cols-1 gap-3 items-left bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Financial Information</h3>
                            <div class="grid grid-cols-1 gap-4">
                                @if($invoice->invoiceDetails->isNotEmpty())
                                <div class="grid grid-cols-1 gap-4">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Invoice Details
                                    </label>
                                    <div class="flex gap-3">
                                        @foreach($invoice->invoiceDetails as $key => $detail)
                                        <div class="p-2 bg-gray-100 dark:bg-gray-600 rounded border border-gray-300 dark:border-gray-500">
                                            @php
                                            $task = $detail->task;
                                            @endphp
                                            <p>
                                                {{ $task->reference}}
                                            </p>
                                            <div>
                                                <ul>
                                                    <li>{{ $task->status }}</li>
                                                    <li>{{ $task->passenger_name }}</li>
                                                    <li>{{ $task->issued_by ?? $task->company->name }}</li>
                                                </ul>
                                            </div>
                                            <div>
                                                <input type="number"
                                                    oninput="updateTotalAmount(this)"
                                                    onblur="formatToThreeDecimals(this)"
                                                    step="0.001"
                                                    name="invoice_details[{{ $detail->task_id }}][amount]"
                                                    value="{{ number_format(old('invoice_details.' . $detail->id . '.amount', $detail->task_price),3) }}"
                                                    class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white" placeholder="Amount" />
                                                @error('invoice_details.' . $detail->id . '.amount')
                                                <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Invoice Charge -->
                                <div>
                                    <label for="invoice_charge" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Invoice Charge
                                    </label>
                                    <input id="invoice_charge"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="number"
                                        step="0.001"
                                        name="invoice_charge"
                                        oninput="updateTotalAmount(this)"
                                        onblur="formatToThreeDecimals(this)"
                                        value="{{ number_format(old('invoice_charge', $invoice->invoice_charge), 3) }}" />
                                    @error('invoice_charge')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Amount -->
                                <div>
                                    <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Total Amount
                                    </label>
                                    <input id="amount"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="number"
                                        step="0.001"
                                        name="amount"
                                        oninput="updateInvoiceCharge(this)"
                                        onblur="formatToThreeDecimals(this)"
                                        value="{{ number_format(old('amount', $invoice->amount),3) }}"
                                        required />
                                    @error('amount')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Tax -->
                                <div>
                                    <label for="tax" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Tax
                                    </label>
                                    <input id="tax"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="number"
                                        step="0.001"
                                        name="tax"
                                        value="{{ old('tax', $invoice->tax) }}" />
                                    @error('tax')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                            </div>
                        </div>

                        <!-- Dates -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Date Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                                <!-- Invoice Date -->
                                <div>
                                    <label for="invoice_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Invoice Date
                                    </label>
                                    <input id="invoice_date"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="date"
                                        name="invoice_date"
                                        value="{{ old('invoice_date', $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d') : '') }}" />
                                    @error('invoice_date')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Due Date -->
                                <div>
                                    <label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Due Date
                                    </label>
                                    <input id="due_date"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="date"
                                        name="due_date"
                                        value="{{ old('due_date', $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('Y-m-d') : '') }}" />
                                    @error('due_date')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Paid Date -->
                                <div>
                                    <label for="paid_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Paid Date
                                    </label>
                                    <input id="paid_date"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="date"
                                        name="paid_date"
                                        value="{{ old('paid_date', $invoice->paid_date ? \Carbon\Carbon::parse($invoice->paid_date)->format('Y-m-d') : '') }}" />
                                    @error('paid_date')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Payment Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <div class="flex gap-2 items-end">
                                    <div class="w-full">
                                        <label for="payment_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Payment Type
                                            @if($invoice->status === 'paid')
                                                <span class="text-xs text-gray-500">(Current: {{ ucfirst($invoice->payment_type ?? 'None') }})</span>
                                            @endif
                                        </label>
                                        <select name="payment_type" id="payment_type" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                                            @foreach($invoicePaymentTypes as $key => $type)
                                            <option value="{{ $key }}" 
                                                {{ old('payment_type', $invoice->payment_type) == $key ? 'selected' : '' }}
                                                data-payment-type="{{ $key }}">
                                                {{ ucfirst($type) }}
                                            </option>
                                            @endforeach
                                        </select>
                                        @error('payment_type')
                                        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                        @enderror
                                        
                                        <!-- Payment Type Change Warning -->
                                        <div id="payment-type-warning" class="hidden mt-2 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                            <div class="flex">
                                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                </svg>
                                                <div class="ml-3">
                                                    <p class="text-sm text-yellow-800" id="payment-warning-text">
                                                        <!-- Warning text will be inserted here -->
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Payment Type Restrictions Info -->
                                        @if($invoice->status === 'paid')
                                        <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                            <div class="flex">
                                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                </svg>
                                                <div class="ml-3">
                                                    <p class="text-sm text-blue-800">
                                                        <strong>Payment Type Change Rules:</strong><br>
                                                        • Only Credit ↔ Cash changes are supported<br>
                                                        • External gateway payments (MyFatoorah, Tap, etc.) cannot be changed<br>
                                                        • Credit changes require sufficient client balance
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                    @if(strtolower(old('payment_type', $invoice->payment_type)) == '')
                                    <x-searchable-dropdown
                                        name="client_credit_id"
                                        id="client_credit_id"
                                        :items="$clients->map(fn($client) => ['id' => $client->id, 'name' => $client->full_name . ' - ' . $client->phone . ' (' . $client->total_credit . ')' ])"
                                        selectedName="$invoice->client->fullname . ' - ' . $invoice->client->phone"
                                        selectedId="$invoice->client_id"
                                        placeholder="Select Client for Credit" />
                                    @endif
                                </div>

                                <div>
                                    <label for="external_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        External URL
                                    </label>
                                    <input id="external_url"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="url"
                                        name="external_url"
                                        value="{{ old('external_url', $invoice->external_url) }}" />
                                    @error('external_url')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-600">
                            <a href="{{ route('invoices.index') }}"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Invoices
                            </a>

                            <button type="submit"
                                class="inline-flex items-center px-6 py-3 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Update Invoice
                            </button>
                        </div>
                    </form>

                    <!-- Credit Shortage Payment Link Section -->
                    @if(session('shortage_info'))
                    <div class="mt-8 p-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                        <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-4">
                            <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                            Payment Type Changed - Credit Shortage Detected
                        </h3>
                        
                        <div class="mb-4 p-3 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded">
                            <p class="text-green-800 dark:text-green-200 text-sm">
                                ✓ Payment type has been successfully changed to Credit. The client's credit balance will go negative.
                            </p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Available Credit</h4>
                                <p class="text-lg font-semibold text-green-600 dark:text-green-400">
                                    {{ number_format(session('shortage_info')['available_credit'], 3) }} {{ $invoice->currency }}
                                </p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Invoice Amount</h4>
                                <p class="text-lg font-semibold text-blue-600 dark:text-blue-400">
                                    {{ number_format(session('shortage_info')['required_amount'], 3) }} {{ $invoice->currency }}
                                </p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border">
                                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Credit Shortage</h4>
                                <p class="text-lg font-semibold text-red-600 dark:text-red-400">
                                    {{ number_format(session('shortage_info')['shortage_amount'], 3) }} {{ $invoice->currency }}
                                </p>
                            </div>
                        </div>

                        <div class="mb-4 p-3 bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 rounded">
                            <p class="text-yellow-800 dark:text-yellow-200 text-sm">
                                <strong>Optional:</strong> You can create a payment link for the shortage amount to help the client top up their credit balance.
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <form method="POST" action="{{ route('invoice.accountant.create.payment.link.shortage') }}" class="flex-1">
                                @csrf
                                <input type="hidden" name="invoice_id" value="{{ session('shortage_info')['invoice_id'] }}">
                                <input type="hidden" name="client_id" value="{{ session('shortage_info')['client_id'] }}">
                                <input type="hidden" name="shortage_amount" value="{{ session('shortage_info')['shortage_amount'] }}">
                                
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <div class="flex-1">
                                        <label for="payment_gateway" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Payment Gateway
                                        </label>
                                        <select name="payment_gateway" id="payment_gateway" required
                                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                            onchange="togglePaymentMethods()">
                                            <option value="">Select Gateway</option>
                                            @foreach($charges->where('can_generate_link', true) as $charge)
                                            <option value="{{ $charge->name }}" data-gateway="{{ strtolower($charge->name) }}">{{ $charge->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <div class="flex-1" id="payment_method_section" style="display: none;">
                                        <label for="payment_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Payment Method
                                        </label>
                                        <select name="payment_method" id="payment_method"
                                            class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                                            <option value="">Select Method</option>
                                            @foreach($paymentMethods as $method)
                                            <option value="{{ $method->id }}" 
                                                data-type="{{ strtolower($method->type) }}"
                                                data-charge-id="{{ $method->charge_id }}">
                                                {{ $method->english_name ?? $method->arabic_name }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <div class="flex items-end">
                                        <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                            Create Payment Link
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <div class="flex items-end">
                                <a href="{{ route('invoice.accountant.edit', ['companyId' => $invoice->agent->branch->company_id, 'invoiceNumber' => $invoice->invoice_number]) }}"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Skip Payment Link
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Timestamp Information -->
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-600">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-500 dark:text-gray-400">
                            <div>
                                <strong>Created:</strong> {{ $invoice->created_at ? $invoice->created_at->format('M d, Y H:i') : 'N/A' }}
                            </div>
                            <div>
                                <strong>Last Updated:</strong> {{ $invoice->updated_at ? $invoice->updated_at->format('M d, Y H:i') : 'N/A' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const totalAmountInput = document.getElementById('amount');
        const invoiceChargeInput = document.getElementById('invoice_charge');
        const paymentTypeSelect = document.getElementById('payment_type');
        const paymentWarning = document.getElementById('payment-type-warning');
        const paymentWarningText = document.getElementById('payment-warning-text');

        // Store original payment type
        const originalPaymentType = '{{ $invoice->payment_type }}';
        const invoiceStatus = '{{ $invoice->status }}';
        const clientCredit = parseFloat('{{ $clientCredit }}');
        const invoiceAmount = parseFloat('{{ $invoice->amount }}');

        function formatToThreeDecimals(inputElement) {
            let value = parseFloat(inputElement.value) || 0;
            inputElement.value = value.toFixed(3);
        }

        function calculateTotal() {
            let invoiceChargeValue = parseFloat(invoiceChargeInput.value) || 0;

            let detailsTotal = 0;
            const detailInputs = document.querySelectorAll('input[name^="invoice_details"]');
            detailInputs.forEach(input => {
                detailsTotal += parseFloat(input.value) || 0;
            });

            let finalTotal = detailsTotal + invoiceChargeValue;
            totalAmountInput.value = finalTotal.toFixed(3);
        }

        function updateTotalAmount(inputElement) {
            calculateTotal(); // Calculate immediately without debounce
        }

        // Simple form submission handler 
        document.querySelector('form').addEventListener('submit', function(e) {
            // Ensure final calculation before submission
            calculateTotal();
        });

        function updateInvoiceCharge(inputElement) {
            let changedValue = parseFloat(inputElement.value) || 0;
            let invoiceChargeInput = document.getElementById('invoice_charge');

            let detailsTotal = 0;
            const detailInputs = document.querySelectorAll('input[name^="invoice_details"]');
            detailInputs.forEach(input => {
                detailsTotal += parseFloat(input.value) || 0;
            });

            let finalTotal = changedValue - detailsTotal;

            invoiceChargeInput.value = finalTotal.toFixed(3);
        }

        // Payment type change validation
        function handlePaymentTypeChange() {
            const selectedPaymentType = paymentTypeSelect.value;
            
            // Hide warning by default
            paymentWarning.classList.add('hidden');
            
            // Only show warnings for paid invoices
            if (invoiceStatus !== 'paid') {
                return;
            }

            // If no change, hide warning
            if (selectedPaymentType === originalPaymentType) {
                return;
            }

            // Validate payment type changes
            if (originalPaymentType === 'credit' && selectedPaymentType === 'cash') {
                paymentWarningText.innerHTML = 'Changing from Credit to Cash will refund the amount back to client\'s credit balance.';
                paymentWarning.classList.remove('hidden');
            } else if (originalPaymentType === 'cash' && selectedPaymentType === 'credit') {
                if (clientCredit < invoiceAmount) {
                    const shortage = invoiceAmount - clientCredit;
                    paymentWarningText.innerHTML = `Insufficient client credit! Available: ${clientCredit.toFixed(3)}, Required: ${invoiceAmount.toFixed(3)}, Shortage: ${shortage.toFixed(3)}`;
                    paymentWarning.classList.remove('hidden');
                } else {
                    paymentWarningText.innerHTML = 'Changing from Cash to Credit will deduct the amount from client\'s credit balance.';
                    paymentWarning.classList.remove('hidden');
                }
            } else if (!['credit', 'cash'].includes(originalPaymentType) || !['credit', 'cash'].includes(selectedPaymentType)) {
                paymentWarningText.innerHTML = 'Only changes between Credit and Cash payment types are currently supported.';
                paymentWarning.classList.remove('hidden');
            }
        }

        // Add event listener for payment type changes
        if (paymentTypeSelect) {
            paymentTypeSelect.addEventListener('change', handlePaymentTypeChange);
            // Run validation on page load
            handlePaymentTypeChange();
        }

        // Payment method visibility toggle for shortage payment link
        function togglePaymentMethods() {
            const gatewaySelect = document.getElementById('payment_gateway');
            const methodSection = document.getElementById('payment_method_section');
            const methodSelect = document.getElementById('payment_method');
            
            if (!gatewaySelect || !methodSection || !methodSelect) return;
            
            const selectedGateway = gatewaySelect.value.toLowerCase();
            const requiresMethod = ['myfatoorah', 'hesabe'].includes(selectedGateway);
            
            if (requiresMethod) {
                methodSection.style.display = 'block';
                methodSelect.setAttribute('required', 'required');
                
                // Filter payment methods by gateway
                const allOptions = methodSelect.querySelectorAll('option[data-type]');
                allOptions.forEach(option => {
                    const optionType = option.getAttribute('data-type');
                    if (optionType === selectedGateway) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                });
                
                // Reset selection
                methodSelect.value = '';
            } else {
                methodSection.style.display = 'none';
                methodSelect.removeAttribute('required');
                methodSelect.value = '';
            }
        }
    </script>
</x-app-layout>