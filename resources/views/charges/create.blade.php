<x-app-layout>
    <div class="container mx-auto p-6">
        <!-- Page Title -->
        <h1 class="text-3xl font-bold text-gray-700 mb-6">New Charges</h1>

        <!-- Edit Form -->
        <div class="bg-white shadow-md rounded-lg p-8">
            <form action="{{ route('charges.store') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Charge Name -->
                    <div>
                        <label for="name" class="block text-gray-700 font-semibold mb-2">Charge Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-gray-700 font-semibold mb-2">Description</label>
                        <input type="text" name="description" id="description" value="{{ old('description') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <!-- Type -->
                    <div>
                        <label for="type" class="block text-gray-700 font-semibold mb-2">Type</label>
                        <select name="type" id="type" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                            <option value="Payment Gateway" selected>Payment Gateway</option>
                        </select>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label for="amount" class="block text-gray-700 font-semibold mb-2">Amount (KWD)</label>
                        <input type="number" step="0.01" name="amount" id="amount"
                            value="{{ old('amount', '0.25') }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>
                    <!-- Charges Type -->
                    <div>
                        <label for="charge_type" class="block text-gray-700 font-semibold mb-2">Charges Type</label>
                        <select name="charge_type" id="charge_type" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                            <option value="Flat Rate" selected>Flat Rate</option>
                            <option value="Percent" selected>Percent</option>
                        </select>
                    </div>
                    <!-- Paid By -->
                    <div>
                        <label for="paid_by" class="block text-gray-700 font-semibold mb-2">Paid By</label>
                        <select name="paid_by" id="paid_by" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                            <option value="Client" selected>Client</option>
                            <option value="Company" selected>Company</option>
                        </select>
                    </div>

                    {{-- <!-- COA for Payment Gateway Fee -->
                    <div>
                        <label for="acc_fee_name" class="block text-gray-700 font-semibold mb-2">COA (Expenses) for
                            Payment Gateway Fee</label>
                        <input list="paymentGatewayOptions" type="text" name="acc_fee_name" id="acc_fee_name"
                            value="{{ old('acc_fee_name') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">

                        <datalist id="paymentGatewayOptions">
                            @foreach ($coaPaymentGateway as $account)
                                <option value="{{ $account->name }}" data-id="{{ $account->id }}"></option>
                                <!-- Accessing individual model's id -->
                            @endforeach
                        </datalist>

                        @if ($coaPaymentGateway->isEmpty())
                            <div class="mt-2 text-sm text-red-500">
                                No available records found. Please add via COA page.
                            </div>
                        @endif
                    </div> --}}



                    <!-- COA for Bank Account under Payment Gateway Fee -->
                    {{-- <div>
                        <label for="acc_bank_fee_name" class="block text-gray-700 font-semibold mb-2">COA (Assets)
                            for
                            Bank Account for the selected Payment Gateway</label>
                        <input list="paymentGatewayBankAccOptions" type="text" name="acc_bank_fee_name"
                            id="acc_bank_fee_name" value="{{ old('acc_bank_fee_name') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">

                        <datalist id="paymentGatewayBankAccOptions">
                            @foreach ($coaPaymentGatewayBankAcc as $account)
                                <option value="{{ $account->name }}" data-id="{{ $account->id }}"></option>
                                <!-- Accessing individual model's id -->
                            @endforeach
                        </datalist>

                        @if ($coaPaymentGatewayBankAcc->isEmpty())
                            <div class="mt-2 text-sm text-red-500">
                                No available records found. Please add via COA page.
                            </div>
                        @endif
                    </div> --}}



                    <!-- COA for Bank Account -->
                    <div>
                        <label for="acc_bank_name" class="block text-gray-700 font-semibold mb-2">COA (Assets) for
                            Debited Bank Account</label>
                        <input list="bankAccountOptions" type="text" name="acc_bank_name" id="acc_bank_name"
                            value="{{ old('acc_bank_name') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">

                        <datalist id="bankAccountOptions">
                            @foreach ($coaBankAccount as $account)
                                <option value="{{ $account->name }}" data-id="{{ $account->id }}"></option>
                                <!-- Accessing individual model's id -->
                            @endforeach
                        </datalist>

                        @if ($coaBankAccount->isEmpty())
                            <div class="mt-2 text-sm text-red-500">
                                No available records found. Please add via COA page.
                            </div>
                        @endif
                    </div>



                </div>

                <!-- Hidden input fields for COA IDs -->
                <input type="hidden" name="acc_fee_id" id="acc_fee_id" value="{{ old('acc_fee_id') }}">
                <input type="hidden" name="acc_bank_id" id="acc_bank_id" value="{{ old('acc_bank_id') }}">
                <input type="hidden" name="acc_fee_bank_id" id="acc_fee_bank_id" value="{{ old('acc_fee_bank_id') }}">
                <!-- Submit Button -->
                <div class="mt-4 flex space-x-4">
                    <a href="{{ route('charges.index') }}"
                        class="w-full text-center px-4 py-2 bg-gray-300 text-gray-800 font-semibold rounded-lg focus:outline-none focus:ring focus:ring-gray-100 focus:border-gray-300">
                        Cancel
                    </a>

                    <button type="submit"
                        class="w-full px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        Create Charge
                    </button>
                </div>
            </form>

            <!-- Error Message Display -->
            @if ($errors->any())
                <div class="mt-4 text-red-500">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Bind the datalist inputs with hidden fields for IDs
            function bindDatalist(inputId, datalistId, hiddenId) {
                const input = document.getElementById(inputId);
                const hidden = document.getElementById(hiddenId);
                const datalist = document.getElementById(datalistId);

                if (!input || !hidden || !datalist) return; // Ensure elements exist

                // Find all the options in the datalist
                const options = datalist.querySelectorAll('option');

                // Function to update hidden input when a match is found
                function updateHiddenValue() {
                    const match = Array.from(options).find(option => option.value === input.value);
                    hidden.value = match ? match.getAttribute('data-id') :
                        ''; // If match found, update hidden input value
                }

                // Add event listeners to input to trigger hidden value update
                input.addEventListener('input', updateHiddenValue);
                input.addEventListener('change', updateHiddenValue);
            }

            // Initialize both bindings for the existing fields and the new field
            bindDatalist('acc_fee_name', 'paymentGatewayOptions', 'acc_fee_id');
            bindDatalist('acc_bank_name', 'bankAccountOptions', 'acc_bank_id');
            bindDatalist('acc_bank_fee_name', 'paymentGatewayBankAccOptions', 'acc_fee_bank_id');
        });
    </script>



</x-app-layout>
