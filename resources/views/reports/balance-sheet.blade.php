<x-app-layout>
    <div class="container mx-auto p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-gray-100">Balance Sheet</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    <span class="font-semibold">{{ $company->name }}</span> | As of <span class="font-semibold">{{ \Carbon\Carbon::parse($asOf)->format('M d, Y') }}</span>
                </p>
            </div>
        </div>

        @if($transitionBanner)
            <div class="mb-6 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                <h3 class="font-semibold text-amber-900 dark:text-amber-100">⚠ Ledger in transition</h3>
                <p class="text-sm text-amber-800 dark:text-amber-200 mt-1">
                    The posting engine is on for this company, but {{ number_format($transitionBanner['legacy_lines']) }} legacy-sourced
                    journal line(s) still exist (Dr {{ number_format($transitionBanner['legacy_debit'], 3) }} /
                    Cr {{ number_format($transitionBanner['legacy_credit'], 3) }}, diff {{ number_format($transitionBanner['legacy_diff'], 3) }}).
                    This balance sheet reads engine rows only — the legacy diff above is not reflected here.
                </p>
            </div>
        @endif

        <div class="mb-6">
            @if($sheet['totals']['is_balanced'])
                <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4">
                    <h3 class="font-semibold text-emerald-900 dark:text-emerald-100">✓ BALANCED</h3>
                    <p class="text-sm text-emerald-800 dark:text-emerald-200 mt-1">Assets = Liabilities + Equity = <span class="font-mono font-bold">{{ number_format($sheet['totals']['assets'], 3) }} KWD</span></p>
                </div>
            @else
                <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <h3 class="font-semibold text-red-900 dark:text-red-100">✗ OUT OF BALANCE</h3>
                    <p class="text-sm text-red-800 dark:text-red-200 mt-1">Difference: <span class="font-mono font-bold">{{ number_format($sheet['totals']['difference'], 3) }} KWD</span></p>
                    <p class="text-xs text-red-700 dark:text-red-300 mt-1">Assets: {{ number_format($sheet['totals']['assets'], 3) }} | Liabilities + Equity: {{ number_format($sheet['totals']['liabilities_and_equity'], 3) }}</p>
                </div>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 mb-6">
            <form method="GET" action="{{ route('accounting.reports.balance-sheet') }}" class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[150px]">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">As of</label>
                    <input type="date" class="w-full h-10 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3" name="as_of" value="{{ $asOf }}">
                </div>
                <button type="submit" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition">Generate</button>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
                <div class="bg-gray-100 dark:bg-gray-900/50 px-4 py-3 font-bold text-gray-900 dark:text-gray-100">ASSETS</div>
                @foreach($sheet['sections']['Assets']['groups'] as $group)
                    <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-900/30">{{ $group['parent']->name ?? 'Ungrouped' }}</div>
                    @foreach($group['accounts'] as $account)
                        <div class="flex justify-between px-4 py-2 text-sm border-t border-gray-100 dark:border-gray-700">
                            <span class="text-gray-700 dark:text-gray-300">{{ $account->code }} {{ $account->name }}</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($account->balance, 3) }}</span>
                        </div>
                    @endforeach
                @endforeach
                <div class="flex justify-between px-4 py-3 font-bold bg-gray-800 dark:bg-gray-900 text-white">
                    <span>TOTAL ASSETS</span>
                    <span>{{ number_format($sheet['totals']['assets'], 3) }}</span>
                </div>
            </div>

            <div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden mb-6">
                    <div class="bg-gray-100 dark:bg-gray-900/50 px-4 py-3 font-bold text-gray-900 dark:text-gray-100">LIABILITIES</div>
                    @foreach($sheet['sections']['Liabilities']['groups'] as $group)
                        <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-900/30">{{ $group['parent']->name ?? 'Ungrouped' }}</div>
                        @foreach($group['accounts'] as $account)
                            <div class="flex justify-between px-4 py-2 text-sm border-t border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-gray-300">{{ $account->code }} {{ $account->name }}</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($account->balance, 3) }}</span>
                            </div>
                        @endforeach
                    @endforeach
                    <div class="flex justify-between px-4 py-3 font-bold bg-gray-800 dark:bg-gray-900 text-white">
                        <span>TOTAL LIABILITIES</span>
                        <span>{{ number_format($sheet['totals']['liabilities'], 3) }}</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
                    <div class="bg-gray-100 dark:bg-gray-900/50 px-4 py-3 font-bold text-gray-900 dark:text-gray-100">EQUITY</div>
                    @foreach($sheet['sections']['Equity']['groups'] as $group)
                        <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-900/30">{{ $group['parent']->name ?? 'Ungrouped' }}</div>
                        @foreach($group['accounts'] as $account)
                            <div class="flex justify-between px-4 py-2 text-sm border-t border-gray-100 dark:border-gray-700">
                                <span class="text-gray-700 dark:text-gray-300">{{ $account->code ?: '' }} {{ $account->name }}</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($account->balance, 3) }}</span>
                            </div>
                        @endforeach
                    @endforeach
                    <div class="flex justify-between px-4 py-3 font-bold bg-gray-800 dark:bg-gray-900 text-white">
                        <span>TOTAL EQUITY</span>
                        <span>{{ number_format($sheet['totals']['equity'], 3) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
