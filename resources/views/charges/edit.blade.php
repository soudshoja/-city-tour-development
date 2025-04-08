<x-app-layout>
    <div class="container mx-auto p-6">
        <!-- Page Title -->
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Edit Charges Details</h1>

        <!-- Edit Form -->
        <div class="bg-white shadow-md rounded-lg p-8">
            <form method="POST" action="{{ route('charges.update', $charge->id) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Charge Name -->
                    <div>
                        <label for="name" class="block text-gray-700 font-semibold mb-2">Charge Name</label>
                        <input type="text" name="name" id="name" value="{{ $charge->name }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-gray-700 font-semibold mb-2">Description</label>
                        <input type="text" name="description" id="description" value="{{ $charge->description }}"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <!-- Type -->
                    <div>
                        <label for="type" class="block text-gray-700 font-semibold mb-2">Type</label>
                        <input type="text" name="type" id="type" value="{{ $charge->type }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <!-- Amount -->
                    <div>
                        <label for="amount" class="block text-gray-700 font-semibold mb-2">Amount (KWD)</label>
                        <input type="number" name="amount" id="amount" value="{{ $charge->amount }}" required
                            min="0.01" step="0.01"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>
                    <!-- COA for Payment Gateway Fee -->
                    @if ($coaPaymentGateway->isNotEmpty())
                        <div>
                            <label for="acc_fee_name" class="block text-gray-700 font-semibold mb-2">
                                COA (Expenses) for Payment Gateway
                                Fee
                            </label>
                            <input list="paymentGatewayOptions" type="text" name="acc_fee_name" id="acc_fee_name"
                                value="{{ old('acc_fee_name', isset($accFee) && $accFee ? $accFee->name : '') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">

                            <datalist id="paymentGatewayOptions">
                                @foreach ($coaPaymentGateway as $account)
                                    @if ($account && is_object($account))
                                        <option value="{{ $account->name }}" data-id="{{ $account->id }}"></option>
                                    @endif
                                @endforeach

                            </datalist>

                            <!-- Show a message if no options are available -->
                            @if ($coaPaymentGateway->isEmpty())
                                <div class="mt-2 text-sm text-red-500">
                                    No available records found. Please add via COA page.
                                </div>
                            @endif
                        </div>


                        <!-- Hidden input field to store the ID of the selected COA for Payment Gateway Fee -->
                        <input type="hidden" name="acc_fee_id" id="acc_fee_id"
                            value="{{ old('acc_fee_id', $charge->acc_fee_id ?? '') }}">

                    @endif

                    <!-- COA for Bank Account -->
                    @if ($coaPaymentGatewayBankAcc->isNotEmpty())
                        <div>
                            <label for="acc_bank_fee_name" class="block text-gray-700 font-semibold mb-2">
                                COA (Assets) for Bank Account for the selected Payment Gateway
                            </label>
                            <input list="paymentGatewayBankAccOptions" type="text" name="acc_bank_fee_name"
                                id="acc_bank_fee_name"
                                value="{{ old('acc_bank_fee_name', isset($accBankFee) && $accBankFee ? $accBankFee->name : '') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">

                            <datalist id="paymentGatewayBankAccOptions">
                                @foreach ($coaPaymentGatewayBankAcc as $account)
                                    <option value="{{ $account->name }}" data-id="{{ $account->id }}"></option>
                                @endforeach
                            </datalist>

                            <!-- Show a message if no options are available -->
                            @if ($coaPaymentGatewayBankAcc->isEmpty())
                                <div class="mt-2 text-sm text-red-500">
                                    No available records found. Please add via COA page.
                                </div>
                            @endif
                        </div>


                        <!-- Hidden input field to store the ID of the selected COA for Bank Account -->
                        <input type="hidden" name="acc_fee_bank_id" id="acc_fee_bank_id"
                            value="{{ old('acc_fee_bank_id', $charge->acc_fee_bank_id ?? '') }}">
                    @endif



                    <!-- COA for Bank Fee -->
                    @if ($coaBankAccount->isNotEmpty())
                        <div>
                            <label for="acc_bank_name" class="block text-gray-700 font-semibold mb-2">COA (Assets) for
                                Debited
                                Bank
                                Account</label>
                            <input list="bankAccountOptions" type="text" name="acc_bank_name" id="acc_bank_name"
                                value="{{ old('acc_bank_name', isset($accBank) && $accBank ? $accBank->name : '') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">

                            <datalist id="bankAccountOptions">
                                @foreach ($coaBankAccount as $account)
                                    <option value="{{ $account->name }}" data-id="{{ $account->id }}"></option>
                                @endforeach
                            </datalist>

                            @if ($coaBankAccount->isEmpty())
                                <div class="mt-2 text-sm text-red-500">
                                    No available records found. Please add via COA page.
                                </div>
                            @endif
                        </div>

                        <!-- Hidden input field for COA Bank Fee ID -->
                        <input type="hidden" name="acc_bank_id" id="acc_bank_id"
                            value="{{ old('acc_bank_id', $charge->acc_bank_id ?? '') }}">
                    @endif


                </div>

                <!-- Submit Button -->
                <div class="text-right">
                    <button type="submit"
                        class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition">
                        Update Charge
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to update hidden fields based on the selected name
            function updateHiddenField(inputId, datalistId, hiddenId) {
                const input = document.getElementById(inputId);
                const hidden = document.getElementById(hiddenId);
                const options = document.querySelectorAll(`#${datalistId} option`);

                // If a value is pre-selected, populate the hidden field
                if (input.value) {
                    const selectedOption = [...options].find(option => option.value === input.value);
                    if (selectedOption) {
                        hidden.value = selectedOption.dataset.id;
                    }
                }

                // Listen for user input or changes and update the hidden field
                input.addEventListener('input', function() {
                    const selectedName = input.value;
                    hidden.value = ''; // Reset hidden field initially

                    options.forEach(option => {
                        if (option.value === selectedName) {
                            hidden.value = option.dataset
                                .id; // Update hidden field to matching option's ID
                        }
                    });
                });

                input.addEventListener('change', function() {
                    const selectedName = input.value;
                    hidden.value = ''; // Reset hidden field initially

                    options.forEach(option => {
                        if (option.value === selectedName) {
                            hidden.value = option.dataset
                                .id; // Update hidden field to matching option's ID
                        }
                    });
                });
            }

            // Initialize the update for all fields
            updateHiddenField('acc_fee_name', 'paymentGatewayOptions', 'acc_fee_id');
            updateHiddenField('acc_bank_fee_name', 'paymentGatewayBankAccOptions', 'acc_fee_bank_id');
            updateHiddenField('acc_bank_name', 'bankAccountOptions', 'acc_bank_id');

        });
    </script>
</x-app-layout>
