<x-app-layout>
    <h1 class="text-center mb-2 font-semibold text-xl">Accounts Payable & Receivable Report</h1>

    <form method="GET" action="{{ route('reports.new-report') }}" class="p-6 my-2 w-fit flex flex-col gap-2 bg-white rounded shadow">
        <div class="flex gap-2">
            <div>
                <label for="start_date">Start Date:</label>
                <input type="date" name="start_date" id="start_date" value="{{ $startDate }}">
            </div>
            <div>
                <label for="end_date">End Date:</label>
                <input type="date" name="end_date" id="end_date" value="{{ $endDate }}">
            </div>
        </div>
        <x-primary-button type="submit" class="flex justify-center">Filter</x-primary-button>
    </form>

    <div class="p-4 bg-white rounded shadow">

        <div class="py-2 my-2">
            @if ($startDate && $endDate)
            <p>Report for the period: {{ $startDate }} to {{ $endDate }}</p>
            @elseif (!$startDate && !$endDate)
            <p>Showing all transactions (no date filter applied).</p>
            @endif
        </div>

        <div class="p-3 border shadow rounded">
            <h2 class="font-bold">Accounts Payable Transactions <span class="font-normal">(Account ID: {{ $accountPayable->code ?? 'CI12300'}})</span></h2>
            @if ($payableTransactions->isNotEmpty())
            <table border="1" style="border-collapse: collapse; width: 100%; text-align: left;">
                <thead>
                    <tr>
                        <th style="padding: 8px; border: 1px solid #ddd;">Transaction Date</th>
                        <th style="padding: 8px; border: 1px solid #ddd;">Description</th>
                        <th style="padding: 8px; border: 1px solid #ddd;">Debit</th>
                        <th style="padding: 8px; border: 1px solid #ddd;">Credit</th>
                        <th style="padding: 8px; border: 1px solid #ddd;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payableTransactions as $transaction)
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;">{{ $transaction->transaction_date }}</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">{{ $transaction->description }}</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">{{ number_format($transaction->debit, 2) }}</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">{{ number_format($transaction->credit, 2) }}</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">{{ number_format($transaction->balance, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-red-500">No Accounts Payable transactions found for the selected period.</p>
            @endif
        </div>
        <div class="p-3 mt-4 border shadow">
            <h2 class="font-bold">Accounts Receivable Transactions <span class="font-normal">(Account ID: {{ $accountReceivable->code ?? 'CI12301'}})</span></h2>
            @if ($receivableTransactions->isNotEmpty())
            <table border="1" style="border-collapse: collapse; width: 100%; text-align: left;">
                <thead>
                    <tr>
                        <th style="padding: 8px; border: 1px solid #ddd;">Transaction Date</th>
                        <th style="padding: 8px; border: 1px solid #ddd;">Description</th>
                        <th style="padding: 8px; border: 1px solid #ddd;">Debit</th>
                        <th style="padding: 8px; border: 1px solid #ddd;">Credit</th>
                        <th style="padding: 8px; border: 1px solid #ddd;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($receivableTransactions as $transaction)
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;">{{ $transaction->transaction_date }}</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">{{ $transaction->description }}</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">{{ number_format($transaction->debit, 2) }}</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">{{ number_format($transaction->credit, 2) }}</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">{{ number_format($transaction->balance, 2) }}</td>
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