<x-app-layout>
    <div class="container mx-auto p-4">
        <h1 class="text-center mb-2 font-semibold text-xl">Accounts Reconciliation Report</h1>
        <div class="flex justify-center items-center bg-gray-100">
            <div class="w-full max-w-screen-xl p-6 my-2">
                {{-- Filter Form --}}
                <form method="GET" id="filterForm" action="{{ route('reports.acc-reconcile') }}"
                    class="mb-4 flex flex-wrap gap-4 items-end">

                    <div class="flex-1 min-w-[200px]">
                        <label for="from" class="block text-sm font-medium">Date From:</label>
                        <input type="date" id="from" name="from" value="{{ old('from', $from) }}"
                            class="border border-gray-300 rounded w-full px-2 py-1 h-10" required />
                    </div>

                    <div class="flex-1 min-w-[200px]">
                        <label for="to" class="block text-sm font-medium">Date To:</label>
                        <input type="date" id="to" name="to" value="{{ old('to', $to) }}"
                            class="border border-gray-300 rounded w-full px-2 py-1 h-10" required />
                    </div>

                    <div class="flex-1 min-w-[250px]">
                        <label for="supplier" class="block text-sm font-medium">Supplier:</label>
                        <input type="text" id="supplier" name="supplier" value="{{ old('supplier', $supplier) }}"
                            class="border border-gray-300 rounded w-full px-2 py-1 h-10" list="supplierList"
                            placeholder="Search supplier name..." />
                        <datalist id="supplierList">
                            @foreach ($suppliers as $sup)
                                <option value="{{ $sup->name }}">
                                    {{ $sup->name }}
                                </option>
                            @endforeach
                        </datalist>
                    </div>

                    <div class="flex-1 min-w-[150px]">
                        <label for="reconciled" class="block text-sm font-medium">Reconciled:</label>
                        <select id="reconciled" name="reconciled"
                            class="border border-gray-300 rounded w-full px-2 py-1 h-10">
                            <option value="both"
                                {{ old('reconciled', $reconciled ?? 'both') == 'both' ? 'selected' : '' }}>All</option>
                            <option value="yes"
                                {{ old('reconciled', $reconciled ?? '') == 'yes' ? 'selected' : '' }}>Reconciled
                            </option>
                            <option value="no" {{ old('reconciled', $reconciled ?? '') == 'no' ? 'selected' : '' }}>
                                No Reconciled</option>
                        </select>
                    </div>

                    <div class="flex-none">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Filter</button>
                        <button type="button" class="bg-gray-300 px-4 py-2 rounded"
                            onclick="resetSupplierAndSubmit()">Reset</button>
                    </div>
                </form>


                {{-- Summary Info Row --}}
                <div class="flex gap-2">
                    <div class="border w-full p-4 rounded bg-white text text-gray-700">
                        @if ($from && $to)
                            <p><strong>Report Period:</strong> {{ $from }} to {{ $to }}</p>
                        @elseif (!$from && !$to)
                            <p><strong>Note:</strong> Showing all transactions (no date filter applied).</p>
                        @endif

                        @if ($supplier)
                            <p><strong>Filtered by Supplier:</strong>
                                {{ \App\Models\Supplier::where('name', $supplier)->value('name') ?? 'Unknown Supplier' }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if (session('error'))
            <div class="text-red-600 mb-4">{{ session('error') }}</div>
        @endif

        <div class="p-4 bg-white">
            @if ($transactions->isEmpty())
                <p>No transactions found for the selected criteria.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white text-sm">
                        <thead>
                            <tr class="bg-gray-100 text-left text-sm font-medium text-gray-600">
                                <th class="py-2 px-4">Date</th>
                                <th class="py-2 px-4">Account</th>
                                <th class="py-2 px-4">Supplier</th>
                                <th class="py-2 px-4">Description</th>
                                <th class="py-2 px-4 text-right">Debit (KWD)</th>
                                <th class="py-2 px-4 text-right">Credit (KWD)</th>
                                <th class="px-4 py-2 text-center">
                                    Reconciled
                                    <input type="checkbox" id="select-all" onclick="toggleSelectAll(this)"
                                        class="ml-2 align-middle">
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $trx_amount = 0;
                            @endphp
                            @foreach ($transactions as $tx)
                                <tr class="border-t text-sm">
                                    <td class="py-2 px-4">
                                        {{ \Carbon\Carbon::parse($tx->transaction_date)->format('Y-m-d') }}</td>
                                    <td class="py-2 px-4">{{ $tx->account->code }} - {{ $tx->account->name }}</td>
                                    <td class="py-2 px-4">{{ $tx->supplier->name ?? ($tx->name ?? 'N/A') }}</td>
                                    <td class="py-2 px-4">{{ $tx->description ?? '-' }}</td>
                                    <td class="py-2 px-4 text-right">{{ number_format($tx->debit, 2) }}</td>
                                    <td class="py-2 px-4 text-right">{{ number_format($tx->credit, 2) }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @if ($tx->reconciled)
                                            Yes
                                        @else
                                            No
                                            <input type="checkbox" name="reconcile_ids[]" value="{{ $tx->id }}"
                                                class="reconcile-checkbox ml-2 align-middle"
                                                data-account-id="{{ $tx->account->id }}"
                                                data-remarks="{{ $tx->description ?? '' }}"
                                                data-credit="{{ $tx->debit ?? 0 }}"
                                                data-debit="{{ $tx->credit ?? 0 }}">
                                        @endif
                                    </td>
                                </tr>
                                @php
                                    $trx_amount = $trx_amount + $tx->credit;
                                @endphp
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <form id="bank-payment-form" method="POST" action="{{ route('bank-payments.store') }}">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ auth()->user()->company->id }}">
                    <input type="hidden" name="branch_id" value="{{ auth()->user()->branch->id }}">
                    <input type="hidden" name="docdate" value="{{ now()->format('Y-m-d') }}">
                    <input type="hidden" name="bankpaymentref" value="PV-{{ now()->timestamp }}">
                    <input type="hidden" name="bankpaymenttype" value="PaymentByDate">
                    <input type="hidden" name="pay_to"
                        value="{{ $transactions->first()->account->name ?? 'N/A' }}">
                    <input type="hidden" name="remarks_create" value="Auto reconciliation payment.">
                    <input type="hidden" name="internal_remarks" value="">
                    <input type="hidden" name="remarks_fl" value="">
                    <input type="hidden" id="form-items" name="items" />
                    <input type="hidden" name="amount" value="{{ $trx_amount ?? 0 }}" id="amount">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded mt-10">Submit for
                        Reconcile</button>
                </form>
            @endif

        </div>

        <script>
            function resetSupplierAndSubmit() {
                const form = document.getElementById('filterForm');
                const supplierInput = document.getElementById('supplier');
                if (form && supplierInput) {
                    supplierInput.value = '';
                    form.submit();
                }
            }

            function toggleSelectAll(source) {
                const checkboxes = document.querySelectorAll('.reconcile-checkbox');
                checkboxes.forEach(cb => cb.checked = source.checked);
            }

            document.getElementById('bank-payment-form').addEventListener('submit', function(e) {
                const form = this;
                const selectedCheckboxes = document.querySelectorAll('.reconcile-checkbox:checked');

                if (selectedCheckboxes.length === 0) {
                    alert('Please select at least one transaction to reconcile.');
                    e.preventDefault();
                    return;
                }

                // Show confirmation dialog
                const confirmed = confirm('Are you sure you want to proceed with reconciliation?');
                if (!confirmed) {
                    e.preventDefault(); // Cancel form submission if user clicks Cancel
                    return;
                }

                // Proceed to build hidden inputs and append them as before
                const container = document.createElement('div');
                container.id = 'dynamic-items-container';

                let totalCredit = 0;
                const grouped = {};

                selectedCheckboxes.forEach(checkbox => {
                    const accountId = checkbox.getAttribute('data-account-id');
                    const remarks = checkbox.getAttribute('data-remarks') || '';
                    const dataCredit = parseFloat(checkbox.getAttribute('data-credit') || 0);
                    const dataDebit = parseFloat(checkbox.getAttribute('data-debit') || 0);

                    if (!grouped[accountId]) {
                        grouped[accountId] = {
                            account_id: accountId,
                            remarks: remarks,
                            credit: 0,
                            debit: 0,
                            journal_entry_ids: [],
                        };
                    }

                    grouped[accountId].credit += dataCredit;
                    grouped[accountId].debit += dataDebit;
                    grouped[accountId].journal_entry_ids.push(checkbox.value);
                    totalCredit += dataCredit;
                });

                let index = 0;
                for (const [accountId, data] of Object.entries(grouped)) {
                    const fields = {
                        account_id: data.account_id,
                        remarks: data.remarks,
                        credit: data.credit,
                        debit: Math.abs(data.credit - data.debit).toFixed(2),
                        cheque_date: '{{ now()->format('Y-m-d') }}',
                        exchange_rate: 1,
                        currency: 'KWD',
                        transaction_id: data.journal_entry_ids.join(','),
                    };

                    for (const [key, value] of Object.entries(fields)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `items[${index}][${key}]`;
                        input.value = value;
                        container.appendChild(input);
                    }

                    index++;
                }

                document.getElementById('amount').value = totalCredit.toFixed(2);
                form.appendChild(container);
            });
        </script>

    </div>
</x-app-layout>
