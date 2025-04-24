<x-app-layout>
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Refund Invoice #{{ $invoice->invoice_number }}</h1>


        <div class="bg-white shadow-md rounded-lg p-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

            <!-- Invoice Info -->
            <div class="bg-gradient-to-br from-blue-100 to-blue-200 shadow-md rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-indigo-300 pb-2">Invoice Info</h3>
                <p class="mb-2"><span class="font-semibold text-gray-700">Invoice Number:</span> {{ $invoice->invoice_number }}</p>
                <p class="mb-2"><span class="font-semibold text-gray-700">Paid Date:</span> {{ $invoice->paid_date }}</p>
                <p class="mb-2"><span class="font-semibold text-gray-700">Amount:</span> KWD{{ number_format($invoice->amount, 2) }}</p>
                <p class="mb-2">
                    <span class="font-semibold text-gray-700">Status:</span>
                    <span class="inline-block px-2 py-1 rounded text-sm font-medium 
                        {{ $invoice->status == 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                        {{ ucfirst($invoice->status) }}
                    </span>
                </p>
            </div>

            <!-- Client Info -->
            <div class="bg-gradient-to-br from-blue-100 to-blue-200 shadow-md rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-purple-300 pb-2">Client Info</h3>
                <p class="mb-2"><span class="font-semibold text-gray-700">Name:</span> {{ $invoice->client->name ?? 'N/A' }}</p>
                <p class="mb-2"><span class="font-semibold text-gray-700">Email:</span> {{ $invoice->client->email ?? 'N/A' }}</p>
            </div>

            </div>


            <hr><br>
            <form action="{{ route('invoices.refunds.store', $invoice->id) }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-50">

                <div>
                    <label for="nettprice" class="block text-gray-700 font-semibold mb-2">Nett Price</label>
                    <input type="number" step="0.01" name="nettprice" id="nettprice" value="{{ number_format($tasks->supplier_price,2) }}" readonly
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 focus:outline-none">
                </div>

                <div>
                    <label for="refundcharges" class="block text-gray-700 font-semibold mb-2">Refund Charges Airlines</label>
                    <input type="number" step="0.01" name="refundcharges" id="refundcharges" value="10.00" readonly
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 focus:outline-none">
                </div>

                <div>
                    <label for="originaltaskprice" class="block text-gray-700 font-semibold mb-2">Original Task Price</label>
                    <input type="number" step="0.01" name="originaltaskprice" id="originaltaskprice" value="{{ number_format($tasks->task_price,2) }}" readonly
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 focus:outline-none">
                </div>


                    <div>
                        <label for="newprofit" class="block text-gray-700 font-semibold mb-2">New Profit for Agent</label>
                        <input type="number" step="0.01" name="newprofit" id="newprofit"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>
                    
                <div>
                    <label for="refundamount" class="block text-gray-700 font-semibold mb-2">Refund to Client</label>
                    <input type="number" step="0.01" name="refundamount" id="refundamount" readonly
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 focus:outline-none">
                </div>

                    <!-- Refund Date -->
                    <div>
                        <label for="date" class="block text-gray-700 font-semibold mb-2">Refund Date</label>
                        <input type="date" name="date" id="date"
                            value="{{ old('date', now()->toDateString()) }}" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <!-- Refund Method -->
                    <div>
                        <label for="method" class="block text-gray-700 font-semibold mb-2">Refund Method</label>
                        <select name="method" id="method" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                            <option value="Bank" {{ old('method') === 'Bank' ? 'selected' : '' }}>Bank</option>
                            <option value="Cash" {{ old('method') === 'Cash' ? 'selected' : '' }}>Cash</option>
                            <option value="Online" {{ old('method') === 'Online' ? 'selected' : '' }}>Online</option>
                        </select>
                    </div>



                    <!-- COA Account (Assets - Refunded From) -->
                    <div>
                        <label for="account_name" class="block text-gray-700 font-semibold mb-2">COA (Assets) Account Refunded From</label>
                        <input list="accountOptions" type="text" name="account_name" id="account_name"
                            value="{{ old('account_name') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">

                        <datalist id="accountOptions">
                            @foreach ($coaAccounts as $account)
                                <option value="{{ $account->name }}" data-id="{{ $account->id }}"></option>
                            @endforeach
                        </datalist>

                        @if ($coaAccounts->isEmpty())
                            <div class="mt-2 text-sm text-red-500">
                                No accounts available. Please configure COA first.
                            </div>
                        @endif
                    </div>

                    <!-- Refund Reason -->
                    <div>
                        <label for="reason" class="block text-gray-700 font-semibold mb-2">Reason</label>
                        <textarea name="reason" id="reason" rows="3" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">{{ old('reason') }}</textarea>
                    </div>

                </div>

                <input type="hidden" name="account_id" id="account_id" value="{{ old('account_id') }}">


                <div class="mt-6 flex justify-between px-4">
                        <!-- Left side: Cancel button -->
                        <a href="{{ url('/invoices/refund/list') }}"
                            class="btn btn-secondary px-6 py-2 w-40 rounded-lg text-center bg-gray-200 hover:bg-gray-300 text-gray-700">
                            Cancel
                        </a>

                        <!-- Right side: Save and Reset -->
                        <div class="flex gap-4">
                            <button type="reset" class="btn btn-warning px-6 py-2 w-40 rounded-lg">Reset</button>
                            <button id="save-refund-btn" type="submit"
                                class="btn btn-success px-6 py-2 w-40 rounded-lg flex items-center justify-center gap-2">
                                <span id="iconSaveRefund" class="mr-2 inline-block">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
                                    </svg>
                                </span>
                                <span id="textSubmitRefund">Submit Refund</span>
                            </button>

                        </div>
                    </div>



            </form>

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
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('account_name');
            const hidden = document.getElementById('account_id');
            const datalist = document.getElementById('accountOptions');

            if (input && hidden && datalist) {
                const options = datalist.querySelectorAll('option');

                function updateHiddenValue() {
                    const match = Array.from(options).find(option => option.value === input.value);
                    hidden.value = match ? match.getAttribute('data-id') : '';
                }

                input.addEventListener('input', updateHiddenValue);
                input.addEventListener('change', updateHiddenValue);
            }
        });
    </script>
</x-app-layout>
