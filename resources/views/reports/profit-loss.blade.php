<x-app-layout>
    <div class="max-w-6xl mx-auto px-6 py-8">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800">Profit &amp; Loss Report</h2>
            <p class="text-sm text-gray-500 mt-1">Net profit/loss for the selected period, with a detailed account breakdown below</p>
            <p class="text-sm text-gray-600 mt-2">Period: <span class="font-medium">{{ $periodFrom }}</span> &rarr; <span class="font-medium">{{ $periodTo }}</span></p>
        </div>

        {{-- CT-A6-2 transition banner (same markup as the balance sheet and creditors screens). --}}
        @if($transitionBanner ?? null)
            <div class="mb-6 bg-amber-50 border border-amber-200 rounded-lg p-4">
                <h3 class="font-semibold text-amber-900">&#9888; Ledger in transition</h3>
                <p class="text-sm text-amber-800 mt-1">
                    The posting engine is on for this company, but {{ number_format($transitionBanner['legacy_lines']) }} legacy-sourced
                    journal line(s) still exist company-wide (Dr {{ number_format($transitionBanner['legacy_debit'], 3) }} /
                    Cr {{ number_format($transitionBanner['legacy_credit'], 3) }}, diff {{ number_format($transitionBanner['legacy_diff'], 3) }}).
                    This profit and loss shows ENGINE rows only. The legacy figures above are company-wide context, not part of the totals on this screen.
                </p>
            </div>
        @endif

        {{--
            LP5a: two filters, not one. The month picker is the original control and stays the
            default; the date range is the addition, and it WINS when submitted (the controller
            treats any date_from/date_to as range mode). They are separate forms on purpose so
            each submits only its own fields — a single form would send an empty month alongside a
            filled range on every submit.
        --}}
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label for="month" class="block text-sm font-medium text-gray-700">Month</label>
                    <input type="month" name="month" id="month" value="{{ $month }}"
                        class="border border-gray-300 rounded px-4 py-2 shadow-sm focus:ring focus:ring-blue-300" required>
                </div>
                <input type="hidden" name="year" value="{{ $year }}">
                <button type="submit"
                    class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">Filter Month</button>
            </form>
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700">From</label>
                    <input type="date" name="date_from" id="date_from" value="{{ $dateFrom }}"
                        class="border border-gray-300 rounded px-4 py-2 shadow-sm focus:ring focus:ring-blue-300" required>
                </div>
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700">To</label>
                    <input type="date" name="date_to" id="date_to" value="{{ $dateTo }}"
                        class="border border-gray-300 rounded px-4 py-2 shadow-sm focus:ring focus:ring-blue-300" required>
                </div>
                <button type="submit"
                    class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">Filter Date Range</button>
            </form>
        </div>

        <div class="bg-white shadow rounded-lg p-6 mb-10">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">📈 Yearly Profit / Loss Graph ({{ $year }})</h3>
            <form method="GET" class="mb-4 flex flex-wrap items-center gap-2 text-sm">
                {{-- LP5a: carry whichever period the page is currently showing, so changing the
                     chart's year does not silently reset a date range back to a single month. --}}
                @if ($usingDateRange)
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                @else
                    <input type="hidden" name="month" value="{{ $month }}">
                @endif
                <label for="year" class="whitespace-nowrap font-medium text-gray-600">Filter Chart By Year:</label>
                <select name="year" id="year" onchange="this.form.submit()"
                    class="border rounded px-2 py-1 text-sm h-8 w-24 focus:outline-none focus:ring-1 focus:ring-blue-300">
                    @foreach (range(now()->year - 5, now()->year) as $y)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </form>
            <canvas id="profitLossChart" height="100"></canvas>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-xl font-semibold text-green-700 mb-4">🟢 Incomes</h3>
                    <table class="w-full text-sm">
                        <tbody>
                        @foreach ($incomeAccounts as $acc)
                            <tr class="border-b">
                                <td class="p-2 font-medium">{{ $acc['account']->name }}</td>
                                <td class="p-2 text-right text-green-600">{{ number_format($acc['amount'], 3) }}</td>
                            </tr>
                            @foreach ($acc['children'] as $child)
                                <tr class="text-xs text-gray-600">
                                    <td class="pl-6 py-1">↳ {{ $child['account']->name }}</td>
                                    <td class="p-2 text-right">{{ number_format($child['amount'], 3) }}</td>
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
                                <td class="p-2 text-right text-red-600">{{ number_format($acc['amount'], 3) }}</td>
                            </tr>
                            @foreach ($acc['children'] as $child)
                                <tr class="text-xs text-gray-600">
                                    <td class="pl-6 py-1">↳ {{ $child['account']->name }}</td>
                                    <td class="p-2 text-right">{{ number_format($child['amount'], 3) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @php
                // LP5a: totals come from ProfitLossService, not from re-summing the rendered rows.
                // The rows are FILTERED for display (zero-movement sections are dropped), so a
                // view-side sum is not guaranteed to equal the figure the parity harness compares
                // against. KWD is a 3-decimal currency; these are shown at full precision.
                $netProfit = $totals['net_income'];
                $isProfit = $netProfit >= 0;
            @endphp

            <div class="mt-8 border-t pt-4 text-base">
                <div class="flex justify-between mb-2">
                    <span class="text-gray-700 font-medium">Total Income:</span>
                    <span class="text-green-600 font-semibold">{{ number_format($totals['income'], 3) }} KWD</span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="text-gray-700 font-medium">Total Expenses:</span>
                    <span class="text-red-600 font-semibold">{{ number_format($totals['expense'], 3) }} KWD</span>
                </div>
                <div class="mt-4 text-white font-bold text-lg text-center py-3 rounded-lg shadow
                    {{ $isProfit ? 'bg-green-500' : 'bg-red-500' }}">
                    {{ $isProfit ? 'Net Profit:' : 'Net Loss:' }}
                    {{ number_format($netProfit, 3) }} KWD
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
                    label: 'Monthly Net',
                    data: @json($monthlyProfits),
                    backgroundColor: @json($monthlyProfitsColors),
                    borderRadius: 6
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return value + ' KWD';
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = context.parsed.y;
                                return (value >= 0 ? 'Profit: ' : 'Loss: ') + Math.abs(value) + ' KWD';
                            }
                        }
                    }
                }
            }
        });
    </script>
</x-app-layout>