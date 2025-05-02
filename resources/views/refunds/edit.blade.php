<x-app-layout>
    <div class="container mx-auto p-6">
        <h1 class="text-3xl font-bold text-gray-700 mb-6">Edit Refund #{{ $refund->refund_number }}</h1>

        <div class="bg-white shadow-md rounded-lg p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Invoice Info -->
                <div class="bg-gradient-to-br from-blue-100 to-white shadow-md rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-purple-200 pb-2">Task Info
                    </h3>
                    <p class="mb-2"><strong>Task Ref Number:</strong> {{ $refund->task->reference }}</p>
                    <p class="mb-2"><strong>Info:</strong> {{ $refund->task->additional_info }}</p>
                    <p class="mb-2"><strong>Date:</strong> {{ $refund->date }}</p>
                    <p class="mb-2"><strong>Total Refund:</strong> KWD{{ number_format($refund->task->total, 2) }}</p>
                    <p class="mb-2">
                        <strong>Status:</strong>
                        <span
                            class="badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium
                                {{ $refund->status === 'completed' ? 'badge-outline-success' : '' }}
                                {{ $refund->status === 'processed' ? 'badge-outline-assigned' : '' }}
                                {{ $refund->status === 'approved' ? 'badge-outline-success' : '' }}
                                {{ $refund->status === 'declined' ? 'badge-outline-danger' : '' }}
                                {{ $refund->status === 'pending' ? 'badge-outline-warning' : '' }}
                                {{ $refund->status === null ? 'badge-outline-danger' : '' }}">
                            {{ $refund->status === null ? 'Not Set' : ucwords($refund->status) }}

                        </span>

                    </p>
                </div>

                <!-- Client Info -->
                <div class="bg-gradient-to-br from-blue-100 to-white shadow-md rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-purple-200 pb-2">Client Info
                    </h3>
                    <p class="mb-2"><strong>Name:</strong> {{ $refund->task->client->name ?? 'N/A' }}</p>
                    <p class="mb-2"><strong>Email:</strong> {{ $refund->task->client->email ?? 'N/A' }}</p>
                    <br>
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b border-purple-200 pb-2">Agent Info</h3>
                    <p class="mb-2"><strong>Name:</strong> {{ $refund->agent->name ?? 'N/A' }}</p>
                    <p class="mb-2"><strong>Email:</strong> {{ $refund->agent->email ?? 'N/A' }}</p>
                </div>
            </div>


            <hr class="my-6">

            <form action="{{ route('refunds.update', ['task' => $refund->task_id, 'refund' => $refund->id]) }}"
                method="POST" class="bg-white p-6 rounded-lg shadow">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <div>
                        <label for="refund_number" class="block text-gray-700 font-semibold mb-2">Refund Number</label>
                        <input type="text" name="refund_number" id="refund_number"
                            value="{{ old('refund_number', $refund->refund_number) }}" readonly
                            class="w-full px-4 py-2 border border-gray-300 bg-gray-200 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        @error('refund_number')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                    <div>
                        <label for="status" class="block text-gray-700 font-semibold mb-2">Status</label>
                        <input type="text" name="status" id="status"
                            value="{{ old('status', ucfirst($refund->status)) }}" readonly
                            class="w-full px-4 py-2 border border-gray-300 bg-gray-200 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        @error('status')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>
                    <div>
                        <label for="date" class="block text-gray-700 font-semibold mb-2">Date</label>
                        <input type="date" name="date" id="date"
                            value="{{ old('date', $refund->date ?? now()->toDateString()) }}" readonly
                            class="w-full px-4 py-2 border border-gray-300 bg-gray-200 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                        @error('date')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Refund Method (readonly with grey background) -->
                    <div>
                        <label for="method" class="block text-gray-700 font-semibold mb-2">Refund Method</label>
                        <select name="method" id="method" readonly
                            class="w-full px-4 py-2 border border-gray-300 bg-gray-200 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                            <option value="">Select</option>
                            <option value="Cash" {{ old('method', $refund->method) == 'Cash' ? 'selected' : '' }}>
                                Cash</option>
                            <option value="Bank" {{ old('method', $refund->method) == 'Bank' ? 'selected' : '' }}>
                                Bank</option>
                            <option value="Online" {{ old('method', $refund->method) == 'Online' ? 'selected' : '' }}>
                                Online
                            </option>
                        </select>
                        @error('method')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- COA (Assets) Account (readonly with grey background) -->
                    <div>
                        <label for="account_name" class="block text-gray-700 font-semibold mb-2">COA (Assets)
                            Account</label>
                        <input list="accountList" type="text" name="account_name" id="account_name"
                            value="{{ old('account_name', $refund->account->name ?? '') }}" readonly
                            class="w-full px-4 py-2 border border-gray-300 bg-gray-200 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300"
                            oninput="setAccountId(this)">
                        <datalist id="accountList">
                            @foreach ($coaAccounts as $account)
                                <option value="{{ $account->name }}" data-id="{{ $account->id }}"></option>
                            @endforeach
                        </datalist>
                        <input type="hidden" name="account_id" id="account_id"
                            value="{{ old('account_id', $refund->account_id ?? '') }}">
                        @error('account_id')
                            <span class="text-red-500 text-sm">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Reference -->
                    <div>
                        <label for="reference" class="block text-gray-700 font-semibold mb-2">Reference</label>
                        <input type="text" name="reference" id="reference"
                            value="{{ old('reference', $refund->reference ?? '') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>
                </div>

                <!-- Grouped Fields -->
                <div class="mt-8 border border-gray-300 rounded-lg p-6 bg-gray-50">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Airline Nett Fare -->
                        <div>
                            <label for="airline_nett_fare" class="block text-gray-700 font-semibold mb-2">Airline
                                Nett
                                Fare</label>
                            <input type="number" step="0.01" name="airline_nett_fare" id="airline_nett_fare"
                                value="{{ old('airline_nett_fare', $refund->airline_nett_fare ?? '') }}" readonly
                                class="w-full px-4 py-2 border border-gray-300 bg-gray-200 rounded-lg">
                        </div>

                        <!-- Airline Refund Charge -->
                        <div>
                            <label for="refund_airline_charge" class="block text-gray-700 font-semibold mb-2">Airline
                                Refund
                                Charge</label>
                            <input type="number" step="0.01" name="refund_airline_charge" id="refund_airline_charge"
                                value="{{ old('refund_airline_charge', $refund->refund_airline_charge ?? '') }}"
                                readonly class="w-full px-4 py-2 border border-gray-300 bg-gray-200 rounded-lg">
                        </div>

                        <!-- Original Profit -->
                        <div>
                            <label for="original_task_profit" class="block text-gray-700 font-semibold mb-2">Original
                                Task
                                Profit</label>
                            <input type="number" step="0.01" name="original_task_profit"
                                id="original_task_profit"
                                value="{{ old('original_task_profit', $refund->original_task_profit ?? '') }}"
                                readonly class="w-full px-4 py-2 border border-gray-300 bg-gray-200 rounded-lg">
                        </div>

                        <!-- Total Service Charge -->
                        <div>
                            <label for="service_charge" class="block text-gray-700 font-semibold mb-2">Service
                                Charge
                                Amount (*New Profit)</label>
                            <input type="number" step="0.01" name="service_charge" id="service_charge"
                                value="{{ old('service_charge', $refund->service_charge ?? '') }}" readonly
                                class="w-full px-4 py-2 border border-gray-300 bg-gray-200 rounded-lg">
                        </div>

                        <!-- Total Refund -->
                        <div>
                            <label for="total_nett_refund" class="block text-gray-700 font-semibold mb-2">Total
                                Nett
                                Refund
                                Amount</label>
                            <input type="number" step="0.01" name="total_nett_refund" id="total_nett_refund"
                                value="{{ old('total_nett_refund', $refund->total_nett_refund ?? '') }}" readonly
                                class="w-full px-4 py-2 border border-gray-300 bg-gray-200 rounded-lg">
                        </div>
                    </div>
                </div>


                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                    <div>
                        <label for="remarks" class="block text-gray-700 font-semibold mb-2">Remarks</label>
                        <input type="text" name="remarks" id="remarks"
                            value="{{ old('remarks', $refund->remarks ?? '') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>

                    <div>
                        <label for="remarks_internal" class="block text-gray-700 font-semibold mb-2">Internal
                            Remarks</label>
                        <input type="text" name="remarks_internal" id="remarks_internal"
                            value="{{ old('remarks_internal', $refund->remarks_internal ?? '') }}"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">
                    </div>
                </div>

                <div class="mt-6">
                    <label for="reason" class="block text-gray-700 font-semibold mb-2">Reason</label>
                    <textarea name="reason" id="reason" rows="3"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-300">{{ old('reason', $refund->reason ?? '') }}</textarea>
                    @error('reason')
                        <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mt-6 flex justify-between px-4">
                    <!-- Left side: Cancel button -->
                    <a href="{{ url('/refunds/') }}"
                        class="btn btn-secondary px-6 py-2 w-40 rounded-lg text-center bg-gray-200 hover:bg-gray-300 text-gray-700">
                        Cancel
                    </a>


                    <div class="flex gap-4">
                        <button type="reset" class="btn btn-warning px-6 py-2 w-40 rounded-lg">Reset</button>
                        <button id="save-paymentvoucher-btn" type="submit"
                            class="btn btn-success px-6 py-2 w-40 rounded-lg flex items-center justify-center gap-2">
                            <span id="iconSavePaymentVoucher" class="mr-2 inline-block">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
                                </svg>
                            </span>
                            <span id="textSavePaymentVoucher">Save</span>
                        </button>

                    </div>

                </div>

                @if ($errors->any())
                    <div class="mt-4 text-red-500 text-sm">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </form>


        </div>
    </div>

    <script>
        function setAccountId(input) {
            const datalist = document.getElementById('accountList');
            const option = [...datalist.options].find(opt => opt.value === input.value);
            if (option) {
                document.getElementById('account_id').value = option.dataset.id;
            }
        }
    </script>
</x-app-layout>
