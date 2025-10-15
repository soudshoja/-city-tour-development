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

                        <!-- Payment Information -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Payment Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Payment Type -->
                                <div class="flex gap-2 items-end">
                                    <div class="w-full">
                                        <label for="payment_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Payment Type
                                        </label>
                                        <select name="payment_type" id="payment_type" class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                                            @foreach($invoicePaymentTypes as $key => $type)
                                            <option value="{{ $key }}" {{ old('payment_type', $invoice->payment_type) == $key ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                                            @endforeach
                                        </select>
                                        @error('payment_type')
                                        <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                        @enderror
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

                                <!-- External URL -->
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

                        <!-- Bank Information -->
                        <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Bank Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                                <!-- Account Number -->
                                <div>
                                    <label for="account_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Account Number
                                    </label>
                                    <input id="account_number"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="text"
                                        name="account_number"
                                        value="{{ old('account_number', $invoice->account_number) }}" />
                                    @error('account_number')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Bank Name -->
                                <div>
                                    <label for="bank_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Bank Name
                                    </label>
                                    <input id="bank_name"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="text"
                                        name="bank_name"
                                        value="{{ old('bank_name', $invoice->bank_name) }}" />
                                    @error('bank_name')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- SWIFT Number -->
                                <div>
                                    <label for="swift_no" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        SWIFT Number
                                    </label>
                                    <input id="swift_no"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="text"
                                        name="swift_no"
                                        value="{{ old('swift_no', $invoice->swift_no) }}" />
                                    @error('swift_no')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- IBAN Number -->
                                <div>
                                    <label for="iban_no" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        IBAN Number
                                    </label>
                                    <input id="iban_no"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                        type="text"
                                        name="iban_no"
                                        value="{{ old('iban_no', $invoice->iban_no) }}" />
                                    @error('iban_no')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- Country -->
                                <div>
                                    <label for="country_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Country
                                    </label>
                                    <select id="country_id"
                                        name="country_id"
                                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white">
                                        <option value="">Select Country</option>
                                        @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ old('country_id', $invoice->country_id) == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                        @endforeach
                                    </select>
                                    @error('country_id')
                                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
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

            // console.log('Changed Value:', changedValue);
            // console.log('Details Total:', detailsTotal);
            // console.log('Final Total:', finalTotal);

            invoiceChargeInput.value = finalTotal.toFixed(3);
        }
    </script>
</x-app-layout>