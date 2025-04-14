<x-app-layout>
    <h1 class="text-center mb-2 font-semibold text-xl">Accounts Payable & Receivable Report</h1>

    <div class="flex justify-center items-center bg-gray-100">
        <form method="GET" action="{{ route('reports.new-report') }}"
            class="p-6 my-2 w-full md:w-full lg:w-full flex flex-col gap-4 bg-white rounded shadow">

            <!-- Input Fields Section -->
            <div class="grid grid-cols-12 gap-4">
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="start_date" class="font-medium text-sm mb-1">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDate }}"
                        class="border rounded px-2 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="end_date" class="font-medium text-sm mb-1">End Date:</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDate }}"
                        class="border rounded px-2 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-2">
                    <label for="branch_id" class="font-medium text-sm mb-1">Filter by Branch:</label>
                    <select name="branch_id" id="branch_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <option value="">All Branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ $branchId == $branch->id ? 'selected' : '' }}>
                                {{ ucfirst($branch->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-3">
                    <label for="supplier_id" class="font-medium text-sm mb-1">Filter by Supplier:</label>
                    <select name="supplier_id" id="supplier_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $supplierRec)
                            <option value="{{ $supplierRec->supplier->id }}" {{ $supplierId == $supplierRec->supplier->id ? 'selected' : '' }}>
                                {{ ucfirst($supplierRec->supplier->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @php
                    $selectedType = request()->input('type_id', 'All');
                @endphp
                <div class="flex flex-col col-span-6 lg:col-span-3">
                    <label for="type_id" class="font-medium text-sm mb-1">Filter by Type:</label>
                    <select name="type_id" id="type_id"
                        class="border rounded px-7 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                        <option value="all" {{ $selectedType == 'all' ? 'selected' : '' }}>All Payable & Receivable
                        </option>
                        <option value="payable" {{ $selectedType == 'payable' ? 'selected' : '' }}>Payable only
                        </option>
                        <option value="receivable" {{ $selectedType == 'receivable' ? 'selected' : '' }}>Receivable
                            only</option>
                    </select>
                </div>
            </div>

            <!-- Button Section (Centered) -->
            <div class="flex justify-center">
                <x-primary-button type="submit" class="w-6/12 md:w-6/12 lg:w-4/12 flex justify-center">
                    Filter
                </x-primary-button>
            </div>
            <div class="flex gap-2">
                <div class="border w-full p-2 rounded">

                    @if ($startDate && $endDate)
                        <p>Report for the period: {{ $startDate }} to {{ $endDate }}</p>
                    @elseif (!$startDate && !$endDate)
                        <p>Showing all transactions (no date filter applied).</p>
                    @endif

                    @if ($branchId)
                        <p>Filtered by Branch: {{ \App\Models\Branch::find($branchId)->name ?? 'Unknown Branch' }}</p>
                    @endif
                    @if ($supplierId)
                        <p>Filtered by Supplier:
                            {{ \App\Models\Supplier::find($supplierId)->name ?? 'Unknown Supplier' }}
                        </p>
                    @endif
                    @if ($selectedType)
                        <p>Filtered by Type: {{ ucfirst($selectedType) }}</p>
                    @endif


                </div>
            </div>

        </form>
    </div>


    <div class="p-4 bg-white rounded shadow">
        {{-- <header class="p-3 flex flex-col gap-2">
        </header> --}}
        <div id="account_payable"
            class="{{ $selectedType == 'payable' ? '' : ($selectedType == 'receivable' ? 'hidden' : '') }} p-3 mt-4 border shadow">
            <h2 class="font-bold">Accounts Payable Transactions <span class="font-normal">(Account ID:
                    {{ $accountPayable->code ?? 'CI12300' }})</span></h2>

            @php
                $totalDebitPayable = 0;
                $totalCreditPayable = 0;
                $totalAllPayable = 0;
                $totalDebitReceivable = 0;
                $totalCreditReceivable = 0;
                $totalAllReceivable = 0;
            @endphp

            @if ($payableTransactions->isNotEmpty())
                <table border="1" style="border-collapse: collapse; width: 100%; text-align: left;">
                    <thead>
                        <tr>
                            <th style="width:220px; style="padding: 8px; border: 1px solid #ddd;">Transaction Date</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">Description</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Debit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Credit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payableTransactions as $transaction)
                            @php
                                $totalDebitPayable += $transaction->debit;
                                $totalCreditPayable += $transaction->credit;
                                $totalAllPayable = $totalDebitPayable - $totalCreditPayable;
                            @endphp
                            <tr>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ $transaction->transaction_date }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <p>{{ $transaction->description }}</p>
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($transaction->debit, 2) }}</td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($transaction->credit, 2) }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($totalAllPayable, 2) }}
                                    {{-- {{ number_format($transaction->balance, 2) }} --}}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-red-500">No Accounts Payable transactions found for the selected period.</p>
            @endif
        </div>
        <div id="account_receivable"
            class="{{ $selectedType == 'receivable' ? '' : ($selectedType == 'payable' ? 'hidden' : '') }} p-3 mt-4 border shadow">
            <h2 class="font-bold">Accounts Receivable Transactions <span class="font-normal">(Account ID:
                    {{ $accountReceivable->code ?? 'CI12301' }})</span></h2>
            @if ($receivableTransactions->isNotEmpty())
                <table border="1" style="border-collapse: collapse; width: 100%; text-align: left;">
                    <thead>
                        <tr>
                            <th style="width:220px; style="padding: 8px; border: 1px solid #ddd;">Transaction Date</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">Description</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Debit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Credit</th>
                            <th style="width:160px; padding: 8px; border: 1px solid #ddd;">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($receivableTransactions as $transaction)
                            @php
                                $totalDebitReceivable += $transaction->debit;
                                $totalCreditReceivable += $transaction->credit;
                                $totalAllReceivable = $totalDebitReceivable - $totalCreditReceivable;
                            @endphp

                            <tr>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ $transaction->transaction_date }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    <p>{{ $transaction->description }}</p>
                                    <p><small>Ref:
                                            {{ $transaction->type_reference_id ?? $transaction->invoice->invoice_number }}
                                            @if ($transaction->invoice && $transaction->invoice->invoice_number)
                                                <a target="_blank"
                                                    href="{{ route('invoice.show', ['invoiceNumber' => $transaction->invoice->invoice_number]) }}"
                                                    class="text-blue-500 ml-0">
                                                    🔍
                                                </a>
                                            @endif
                                        </small>
                                    </p>
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($transaction->debit, 2) }}</td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($transaction->credit, 2) }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($totalAllReceivable, 2) }}
                                    {{-- {{ number_format($transaction->balance, 2) }} --}}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-red-500">No Accounts Receivable transactions found for the selected period.</p>
            @endif
        </div>

        <div class="p-3 mt-4 border shadow">
            <h2 class="flex justify-start">
                <h2 class="font-bold">Outstanding Balances <span class="font-normal">(Account ID:
                        {{ $accountReceivable->code ?? 'CI12301' }})</span></h2>
            </h2>
            <div class="flex gap-2">
                <div class="border w-full p-2 rounded">
                    <h3>Accounts Payable</h3>
                    <p><strong>Outstanding Balance: {{ number_format($totalAllPayable, 2) }}</strong></p>
                </div>
                <div class="border w-full p-2 rounded">
                    <h3>Accounts Receivable</h3>
                    <p><strong>Outstanding Balance: {{ number_format($totalAllReceivable, 2) }}</strong></p>
                </div>
            </div>
        </div>
    </div>
    <script>
        function filterType() {
            let type = document.getElementById('type_id').value;
            let payableDiv = document.getElementById('account_payable');
            let receivableDiv = document.getElementById('account_receivable');

            // Hide both initially
            payableDiv.classList.add('hidden');
            receivableDiv.classList.add('hidden');

            // Show based on selection
            if (type === 'payable') {
                payableDiv.classList.remove('hidden');
            } else if (type === 'receivable') {
                receivableDiv.classList.remove('hidden');
            } else {
                payableDiv.classList.remove('hidden');
                receivableDiv.classList.remove('hidden');
            }
        }
    </script>
</x-app-layout>
