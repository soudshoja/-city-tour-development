<x-app-layout>
    <div class="container mx-auto p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-gray-100">General Ledger</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    <span class="font-semibold">{{ $company->name }}</span> —
                    <span class="font-mono">{{ $ledger['account']->code }}</span> {{ $ledger['account']->name }}
                    | Period: <span class="font-semibold">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</span>
                </p>
            </div>
        </div>

        {{-- CT-A6-2 transition banner: engine on, legacy rows still exist on this company's ledger. --}}
        @if($transitionBanner)
            <div class="mb-6 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                <h3 class="font-semibold text-amber-900 dark:text-amber-100">⚠ Ledger in transition</h3>
                <p class="text-sm text-amber-800 dark:text-amber-200 mt-1">
                    The posting engine is on for this company, but {{ number_format($transitionBanner['legacy_lines']) }} legacy-sourced
                    journal line(s) still exist company-wide (Dr {{ number_format($transitionBanner['legacy_debit'], 3) }} /
                    Cr {{ number_format($transitionBanner['legacy_credit'], 3) }}, diff {{ number_format($transitionBanner['legacy_diff'], 3) }}).
                    This screen shows ENGINE rows only for this account — the legacy figures above are company-wide context, not this account's own legacy balance.
                </p>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 mb-6">
            <form method="GET" action="{{ route('accounting.reports.general-ledger') }}" id="filterForm">
                <input type="hidden" name="account_id" value="{{ $ledger['account']->id }}">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Account</label>
                        <select class="w-full h-10 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3" name="account_id" onchange="this.form.submit()">
                            @foreach($accounts as $acct)
                                <option value="{{ $acct->id }}" @selected($acct->id === $ledger['account']->id)>{{ $acct->code }} — {{ $acct->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">From Date</label>
                        <input type="date" class="w-full h-10 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3" name="date_from" value="{{ $dateFrom }}">
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">To Date</label>
                        <input type="date" class="w-full h-10 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3" name="date_to" value="{{ $dateTo }}">
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition">Generate</button>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wider">Opening Balance</p>
                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($ledger['opening_balance'], 3) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wider">Debit / Credit (period)</p>
                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($ledger['totals']['debit'], 3) }} / {{ number_format($ledger['totals']['credit'], 3) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wider">Closing Balance</p>
                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($ledger['totals']['closing_balance'], 3) }}</p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Document</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Description</th>
                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Party ref</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Debit</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Credit</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($ledger['lines'] as $line)
                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                <td class="px-3 py-3 text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($line->entry_date)->format('Y-m-d') }}</td>
                                <td class="px-3 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $line->doc_type }}{{ $line->sub_type ? '/'.$line->sub_type : '' }}
                                    @if($line->reference_number)
                                        <span class="text-xs text-gray-500">{{ $line->reference_number }}</span>
                                    @endif
                                    @if($line->idempotency_key)
                                        <span class="block text-xs text-gray-400 font-mono">{{ $line->idempotency_key }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-gray-700 dark:text-gray-300">{{ $line->description }}{{ $line->voucher_number ? ' ('.$line->voucher_number.')' : '' }}</td>
                                <td class="px-3 py-3 text-gray-500 dark:text-gray-400">{{ $line->party_ref ?? '—' }}</td>
                                <td class="px-3 py-3 text-right {{ $line->debit > 0 ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-gray-400' }}">{{ $line->debit > 0 ? number_format($line->debit, 3) : '—' }}</td>
                                <td class="px-3 py-3 text-right {{ $line->credit > 0 ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : 'text-gray-400' }}">{{ $line->credit > 0 ? number_format($line->credit, 3) : '—' }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format($line->running_balance, 3) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No journal lines in this period.</td>
                            </tr>
                        @endforelse
                        <tr class="bg-gray-800 dark:bg-gray-900">
                            <td colspan="4" class="px-3 py-3 font-bold text-white">CLOSING BALANCE</td>
                            <td class="px-3 py-3 text-right font-bold text-white">{{ number_format($ledger['totals']['debit'], 3) }}</td>
                            <td class="px-3 py-3 text-right font-bold text-white">{{ number_format($ledger['totals']['credit'], 3) }}</td>
                            <td class="px-3 py-3 text-right font-bold text-white">{{ number_format($ledger['totals']['closing_balance'], 3) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="p-4">
                {{ $ledger['lines']->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
