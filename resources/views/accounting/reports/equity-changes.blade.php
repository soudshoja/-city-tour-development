@php
    $ties = $statement['checks']['ties_to_next_year_opening'];
@endphp
<x-app-layout>
    <div class="my-3">
        <!-- Page heading -->
        <div class="flex justify-between items-center gap-5 mb-6 flex-wrap">
            <div class="flex items-center space-x-4">
                <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center">
                    <a href="javascript:history.back()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                            <path fill="#FFC107" fill-rule="evenodd" d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                        </svg>
                    </a>
                </div>
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold dark:text-white">Statement of Changes in Equity</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $company->name ?? '' }} &middot; fiscal year {{ $year }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <form method="GET" action="{{ route('accounting.reports.equity-changes') }}" class="flex items-center gap-2">
                    <label class="text-sm text-gray-600 dark:text-gray-300" for="equity-year">Fiscal year</label>
                    <select id="equity-year" name="year" onchange="this.form.submit()"
                            class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        @foreach (range((int) now()->format('Y'), (int) now()->format('Y') - 6) as $y)
                            <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                        @endforeach
                    </select>
                </form>

                <a href="{{ route('accounting.reports.equity-changes.export', ['year' => $year]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-800 text-white hover:bg-slate-700">
                    Export CSV
                </a>
            </div>
        </div>

        <!-- Summary strip -->
        <div class="grid gap-3 mb-6" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Opening equity</div>
                <div class="text-lg font-bold dark:text-white">{{ number_format($statement['opening_equity_total'], 3) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Net profit for the year</div>
                <div class="text-lg font-bold dark:text-white">{{ number_format($statement['net_profit'], 3) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Dividends paid this year</div>
                <div class="text-lg font-bold dark:text-white">{{ number_format($statement['dividends_paid_this_year'], 3) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Closing equity</div>
                <div class="text-lg font-bold dark:text-white">{{ number_format($statement['closing_equity_total'], 3) }}</div>
            </div>
        </div>

        <!-- Roll-forward table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow overflow-x-auto mb-6">
            <table class="min-w-full table-auto border-collapse text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Component</th>
                        <th class="px-4 py-3 text-left">Code</th>
                        <th class="px-4 py-3 text-right">Opening</th>
                        <th class="px-4 py-3 text-right">Movement</th>
                        <th class="px-4 py-3 text-right">Closing</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($statement['components'] as $key => $component)
                        <tr class="text-gray-800 dark:text-gray-100">
                            <td class="px-4 py-3 font-medium">{{ $component['name'] }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $component['code'] }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($component['opening'], 3) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format($component['movement'], 3) }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format($component['closing'], 3) }}</td>
                        </tr>
                        @if ($key === 'retained_earnings')
                            <tr class="text-gray-500 dark:text-gray-400 text-xs">
                                <td class="px-4 py-2" colspan="2">&nbsp;&nbsp;&#8627; net profit + dividends folded in (pro-forma, as if closed today)</td>
                                <td class="px-4 py-2 text-right"></td>
                                <td class="px-4 py-2 text-right">{{ number_format($statement['net_profit'] - $statement['dividends_paid_this_year'], 3) }}</td>
                                <td class="px-4 py-2 text-right"></td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-700/40 font-semibold text-gray-800 dark:text-gray-100">
                    <tr>
                        <td class="px-4 py-3" colspan="2">Total equity</td>
                        <td class="px-4 py-3 text-right">{{ number_format($statement['opening_equity_total'], 3) }}</td>
                        <td class="px-4 py-3 text-right"></td>
                        <td class="px-4 py-3 text-right">{{ number_format($statement['closing_equity_total'], 3) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Reconciliation check -->
        <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
            <h3 class="text-sm font-semibold dark:text-white mb-3">Reconciliation</h3>
            <div class="flex items-center gap-3 flex-wrap text-sm">
                <span @class([
                    'px-3 py-1 rounded-full font-medium',
                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $ties,
                    'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => !$ties,
                ])>
                    {{ $ties ? 'Ties to next-year opening balance' : 'Not yet closed — pending year-end close' }}
                </span>
                <span class="text-gray-500 dark:text-gray-400">
                    Next-year opening total: {{ number_format($statement['checks']['next_year_opening_total'], 3) }}
                    (difference {{ number_format($statement['checks']['difference'], 3) }})
                </span>
            </div>
            @unless ($ties)
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                    This year has not been closed (Accounting &rarr; Period Control &rarr; Close fiscal year). The figures above are a pro-forma projection of what equity will read once closed; the real ledger will not reflect the year's net profit until then.
                </p>
            @endunless
        </div>
    </div>
</x-app-layout>
