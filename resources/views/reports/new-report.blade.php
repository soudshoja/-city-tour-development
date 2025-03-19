<x-app-layout>
    <h1 class="text-center mb-2 font-semibold text-xl">Accounts Payable & Receivable Report</h1>

    <div class="flex justify-center items-center bg-gray-100">
        <form method="GET" action="{{ route('reports.new-report') }}"
            class="p-6 my-2 w-full md:w-full lg:w-full flex flex-col gap-4 bg-white rounded shadow">

            <!-- Input Fields Section -->
            <div class="grid grid-cols-12 gap-4">
                <div class="flex flex-col col-span-6 lg:col-span-3">
                    <label for="start_date" class="font-medium text-sm mb-1">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" value="{{ $startDate }}"
                        class="border rounded px-2 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-3">
                    <label for="end_date" class="font-medium text-sm mb-1">End Date:</label>
                    <input type="date" name="end_date" id="end_date" value="{{ $endDate }}"
                        class="border rounded px-2 py-2 focus:outline-none focus:ring focus:ring-blue-300">
                </div>
                <div class="flex flex-col col-span-6 lg:col-span-3">
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
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ $supplierId == $supplier->id ? 'selected' : '' }}>
                                {{ ucfirst($supplier->name) }}
                            </option>
                        @endforeach
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
                </div>
            </div>

        </form>
    </div>


    <div class="p-4 bg-white rounded shadow">
        <header class="p-3 flex flex-col gap-2">

            <h2 class="flex justify-start"><strong>Outstanding Balances</strong></h2>
            <div class="flex gap-2">
                <div class="border w-full p-2 rounded">
                    <h3>Accounts Payable</h3>
                    <p><strong>Outstanding Balance: {{ number_format($payableBalance, 2) }}</strong></p>
                </div>
                <div class="border w-full p-2 rounded">
                    <h3>Accounts Receivable</h3>
                    <p><strong>Outstanding Balance: {{ number_format($receivableBalance, 2) }}</strong></p>
                </div>
            </div>
        </header>
        <div class="p-3 border shadow rounded">
            <h2 class="font-bold">Accounts Payable Transactions <span class="font-normal">(Account ID:
                    {{ $accountPayable->code ?? 'CI12300' }})</span></h2>
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
                        @php
                            $totalDebit = 0;
                            $totalCredit = 0;
                        @endphp
                        @foreach ($payableTransactions as $transaction)
                            @php
                                $totalDebit += $transaction->debit;
                                $totalCredit += $transaction->credit;
                                $totalAll = $totalDebit - $totalCredit;
                            @endphp
                            <tr>
                                <td style="padding: 8px; border: 1px solid #ddd;">{{ $transaction->transaction_date }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">{{ $transaction->description }}</td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($transaction->debit, 2) }}</td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ $transaction->credit > 0 ? '-' : '' }}{{ number_format($transaction->credit, 2) }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($totalAll, 2) }}
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
        <div class="p-3 mt-4 border shadow">
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
                        @php
                            $totalDebit = 0;
                            $totalCredit = 0;
                        @endphp
                        @foreach ($receivableTransactions as $transaction)
                            @php
                                $totalDebit += $transaction->debit;
                                $totalCredit += $transaction->credit;
                                $totalAll = $totalDebit - $totalCredit;
                            @endphp
                            <tr>
                                <td style="padding: 8px; border: 1px solid #ddd;">{{ $transaction->transaction_date }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">{{ $transaction->description }}</td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($transaction->debit, 2) }}</td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ $transaction->credit > 0 ? '-' : '' }}{{ number_format($transaction->credit, 2) }}
                                </td>
                                <td style="padding: 8px; border: 1px solid #ddd;">
                                    {{ number_format($totalAll, 2) }}
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
    </div>
</x-app-layout>
