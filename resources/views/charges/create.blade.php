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
                        <input type="text" name="type" id="type" value="{{ old('type') }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <!-- Amount -->
                    <div>
                        <label for="amount" class="block text-gray-700 font-semibold mb-2">Amount (KWD)</label>
                        <input type="number" step="0.01" default="0.10" name="amount" id="amount"
                            value="{{ old('amount') }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    @if ($coaPaymentGateway->isNotEmpty())
                        <!-- COA for Payment Gateway Fee -->
                        <div>
                            <label for="acc_fee_name" class="block text-gray-700 font-semibold mb-2">COA (Expenses) for
                                Payment Gateway Fee</label>
                            <input list="paymentGatewayOptions" type="text" name="acc_fee_name" id="acc_fee_name"
                                value="{{ old('acc_fee_name') }}" required
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
                        </div>
                    @endif

                    @if ($coaPaymentGatewayBankAcc->isNotEmpty())
                        <!-- COA for Bank Account under Payment Gateway Fee -->
                        <div>
                            <label for="acc_bank_fee_name" class="block text-gray-700 font-semibold mb-2">COA (Assets)
                                for
                                Bank Account for the selected Payment Gateway</label>
                            <input list="paymentGatewayBankAccOptions" type="text" name="acc_bank_fee_name"
                                id="acc_bank_fee_name" value="{{ old('acc_bank_fee_name') }}" required
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
                        </div>
                    @endif

                    @if ($coaBankAccount->isNotEmpty())
                        <!-- COA for Bank Account -->
                        <div>
                            <label for="acc_bank_name" class="block text-gray-700 font-semibold mb-2">COA (Assets) for
                                Debited Bank Account</label>
                            <input list="bankAccountOptions" type="text" name="acc_bank_name" id="acc_bank_name"
                                value="{{ old('acc_bank_name') }}" required
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
                    @endif


                </div>

                <!-- Hidden input fields for COA IDs -->
                <input type="hidden" name="acc_fee_id" id="acc_fee_id" value="{{ old('acc_fee_id') }}">
                <input type="hidden" name="acc_bank_id" id="acc_bank_id" value="{{ old('acc_bank_id') }}">
                <input type="hidden" name="acc_fee_bank_id" id="acc_fee_bank_id" value="{{ old('acc_fee_bank_id') }}">
                <!-- Submit Button -->
                <div class="mt-4">
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
