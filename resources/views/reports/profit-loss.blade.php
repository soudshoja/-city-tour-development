<x-app-layout>
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800">📊 Profit & Loss Report</h2>
            <p class="text-sm text-gray-500 mt-1">View profit/loss graph and detailed list by selected month</p>
        </div>

        <div class="flex flex-col md:flex-row items-center justify-between gap-4 mb-8">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700">Month</label>
                    <input type="month" name="month" id="month" value="{{ $month }}"
                        class="border border-gray-300 rounded px-4 py-2 shadow-sm focus:ring focus:ring-blue-300" required>
                </div>
                <button type="submit"
                    class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">Filter</button>
            </form>
        </div>

        <div class="bg-white shadow rounded-lg p-6 mb-10">
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Net Profit / Loss ({{ \Carbon\Carbon::parse($month)->format('F Y') }})</h3>
            <canvas id="profitLossChart" height="100"></canvas>
        </div>

        @php
            $totalIncome = 0;
            $totalExpense = 0;
        @endphp

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-xl font-semibold text-green-700 mb-4">🟢 Incomes</h3>
                    <table class="w-full text-sm">
                        <tbody>
                        @foreach ($incomeAccounts as $acc)
                            <tr class="border-b">
                                <td class="p-2 font-medium">{{ $acc['account']->name }}</td>
                                <td class="p-2 text-right text-green-600">{{ number_format($acc['amount'], 2) }}</td>
                            </tr>
                            @php
                                $totalIncome += $acc['amount'];
                            @endphp
                            @foreach ($acc['children'] as $child)
                                <tr class="text-xs text-gray-600">
                                    <td class="pl-6 py-1">↳ {{ $child['account']->name }}</td>
                                    <td class="p-2 text-right">{{ number_format($child['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div>
                    <h3 class="text-xl font-semibold text-red-700 mb-4">🔴 Expenses</h3>
                    <table class="w-full text-sm">
                        <tbody>
                        @foreach ($expenseAccounts as $acc)
                            <tr class="border-b">
                                <td class="p-2 font-medium">{{ $acc['account']->name }}</td>
                                <td class="p-2 text-right text-red-600">{{ number_format(abs($acc['amount']), 2) }}</td>
                            </tr>
                            @php
                                $totalExpense += abs($acc['amount']);
                            @endphp
                            @foreach ($acc['children'] as $child)
                                <tr class="text-xs text-gray-600">
                                    <td class="pl-6 py-1">↳ {{ $child['account']->name }}</td>
                                    <td class="p-2 text-right">{{ number_format(abs($child['amount']), 2) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @php
                $netProfit = $totalIncome - abs($totalExpense);
                $isProfit = $netProfit >= 0;
            @endphp

            <div class="mt-8 border-t pt-4 text-base">
                <div class="flex justify-between mb-2">
                    <span class="text-gray-700 font-medium">Total Income:</span>
                    <span class="text-green-600 font-semibold">{{ number_format($totalIncome, 2) }} KWD</span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="text-gray-700 font-medium">Total Expenses:</span>
                    <span class="text-red-600 font-semibold">{{ number_format(abs($totalExpense), 2) }} KWD</span>
                </div>
                <div class="mt-4 text-white font-bold text-lg text-center py-3 rounded-lg shadow
                    {{ $isProfit ? 'bg-green-500' : 'bg-red-500' }}">
                    {{ $isProfit ? 'Net Profit:' : 'Net Loss:' }}
                    {{ number_format($netProfit, 2) }} KWD
                </div>
            </div>
        </div>
    </div>
    <script>
        const ctx = document.getElementById('profitLossChart');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($monthlyLabels),
                datasets: [{
                    label: 'Net Profit/Loss (KWD)',
                    data: @json($monthlyProfits),
                    backgroundColor: @json($monthlyProfitsColors),
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return value + ' KWD';
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
</x-app-layout>