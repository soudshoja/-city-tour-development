<x-app-layout>
    <div class="container mx-auto p-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-gray-100">Trial Balance Report</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    <span class="font-semibold">{{ $company->name }}</span> | Period: <span class="font-semibold">{{ \Carbon\Carbon::parse($dateFrom)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('M d, Y') }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('reports.trial-balance.pdf', request()->query()) }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-red-600 hover:bg-red-700 text-white transition" target="_blank">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    PDF
                </a>
                <a href="{{ route('reports.trial-balance.export', request()->query()) }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-green-600 hover:bg-green-700 text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    CSV
                </a>
                <button onclick="window.print()" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z" />
                    </svg>
                    Print
                </button>
            </div>
        </div>

        <!-- Balance Status Alert -->
        <div class="mb-6">
            @if($trialBalance['totals']['is_balanced'])
                <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <div class="text-emerald-600 dark:text-emerald-400 mt-0.5">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-emerald-900 dark:text-emerald-100">✓ BALANCED</h3>
                            <p class="text-sm text-emerald-800 dark:text-emerald-200 mt-1">Total Debits = Total Credits = <span class="font-mono font-bold">{{ number_format($trialBalance['totals']['debit'], 3) }} KWD</span></p>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <div class="text-red-600 dark:text-red-400 mt-0.5">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-red-900 dark:text-red-100">✗ OUT OF BALANCE</h3>
                            <p class="text-sm text-red-800 dark:text-red-200 mt-1">Difference: <span class="font-mono font-bold">{{ number_format($trialBalance['totals']['difference'], 3) }} KWD</span></p>
                            <p class="text-xs text-red-700 dark:text-red-300 mt-1">Total Debits: {{ number_format($trialBalance['totals']['debit'], 3) }} | Total Credits: {{ number_format($trialBalance['totals']['credit'], 3) }}</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 mb-6">
            <form method="GET" action="{{ route('reports.trial-balance') }}" id="filterForm">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">From Date</label>
                        <input type="date" class="w-full h-10 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3" id="date_from" name="date_from" value="{{ $dateFrom }}">
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">To Date</label>
                        <input type="date" class="w-full h-10 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3" id="date_to" name="date_to" value="{{ $dateTo }}">
                    </div>
                    @if($branches->count() > 0)
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Branch</label>
                        <select class="w-full h-10 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3" id="branch_id" name="branch_id">
                            <option value="">All Branches</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(!empty($filters['branch_id']) && $filters['branch_id'] == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="show_zero" name="show_zero" value="1" @checked($showZero) class="w-4 h-4 rounded border-gray-300">
                        <label for="show_zero" class="text-sm text-gray-700 dark:text-gray-300">Show Zero Balances</label>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            Generate
                        </button>
                        <a href="{{ route('reports.trial-balance') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Trial Balance Table -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Code</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Account Name</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Opening Balance</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Debit</th>
                            <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Credit</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Closing Balance</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($trialBalance['grouped'] as $rootName => $group)
                            <!-- Root Category Header -->
                            <tr class="bg-gray-100 dark:bg-gray-800">
                                <td colspan="3" class="px-4 py-3 font-bold text-gray-900 dark:text-gray-100">{{ strtoupper($rootName) }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format($group['subtotal_debit'], 3) }}</td>
                                <td class="px-3 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format($group['subtotal_credit'], 3) }}</td>
                                <td colspan="2"></td>
                            </tr>

                            @forelse($group['accounts'] as $account)
                                @php
                                    $isDebitNormal = in_array($account->root_name, ['Assets', 'Expenses']);
                                    $openingNet = $isDebitNormal
                                        ? $account->opening_debit - $account->opening_credit
                                        : $account->opening_credit - $account->opening_debit;
                                    $openingLabel = $openingNet >= 0
                                        ? ($isDebitNormal ? 'Dr' : 'Cr')
                                        : ($isDebitNormal ? 'Cr' : 'Dr');

                                    $closingNet = $account->closing_balance;
                                    $closingLabel = $closingNet >= 0
                                        ? ($isDebitNormal ? 'Dr' : 'Cr')
                                        : ($isDebitNormal ? 'Cr' : 'Dr');
                                @endphp
                                <tr class="bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">{{ $account->code }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $account->name }}</td>
                                    <td class="px-3 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                        @if(abs($openingNet) > 0.001)
                                            {{ number_format(abs($openingNet), 3) }}
                                            <span class="text-xs text-gray-500">{{ $openingLabel }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-right font-semibold {{ $account->total_debit > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">
                                        {{ $account->total_debit > 0 ? number_format($account->total_debit, 3) : '—' }}
                                    </td>
                                    <td class="px-3 py-3 text-right font-semibold {{ $account->total_credit > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                        {{ $account->total_credit > 0 ? number_format($account->total_credit, 3) : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold {{ abs($closingNet) > 0.001 ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400' }}">
                                        @if(abs($closingNet) > 0.001)
                                            {{ number_format(abs($closingNet), 3) }}
                                            <span class="text-xs text-gray-500">{{ $closingLabel }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ route('journal-entries.show', $account->id) }}" class="inline-flex items-center justify-center w-8 h-8 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/30 rounded-md transition" title="View Ledger">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-3 text-center text-gray-500">No accounts in this category</td>
                                </tr>
                            @endforelse
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500">
                                        <svg class="w-12 h-12 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <p class="text-lg font-medium">No data available</p>
                                        <p class="text-sm">Try adjusting your filters</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse

                        <!-- Grand Total Row -->
                        @if(count($trialBalance['grouped']) > 0)
                            <tr class="bg-gray-800 dark:bg-gray-900">
                                <td colspan="3" class="px-4 py-3 font-bold text-white">GRAND TOTAL</td>
                                <td class="px-3 py-3 text-right font-bold text-white">{{ number_format($trialBalance['totals']['debit'], 3) }}</td>
                                <td class="px-3 py-3 text-right font-bold text-white">{{ number_format($trialBalance['totals']['credit'], 3) }}</td>
                                <td colspan="2"></td>
                            </tr>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <td colspan="3" class="px-4 py-3 text-right font-bold text-gray-900 dark:text-gray-100">Difference:</td>
                                <td colspan="2" class="px-4 py-3 text-right">
                                    @if($trialBalance['totals']['is_balanced'])
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">0.000</span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">{{ number_format($trialBalance['totals']['difference'], 3) }}</span>
                                    @endif
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Unbalanced Transactions Section -->
        @if($unbalancedTransactions->count() > 0)
            <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden border-l-4 border-amber-500">
                <div class="bg-amber-50 dark:bg-amber-900/30 px-4 py-3 border-b border-amber-200 dark:border-amber-800">
                    <h3 class="flex items-center gap-2 font-bold text-amber-900 dark:text-amber-100">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        Unbalanced Transactions Detected ({{ $unbalancedTransactions->count() }})
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Reference</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Debit</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Credit</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Imbalance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @php
                                $netImbalance = $unbalancedTransactions->sum('signed_imbalance');
                                $totalExcessDebit = $unbalancedTransactions->where('signed_imbalance', '>', 0)->sum('signed_imbalance');
                                $totalExcessCredit = abs($unbalancedTransactions->where('signed_imbalance', '<', 0)->sum('signed_imbalance'));
                            @endphp
                            @foreach($unbalancedTransactions as $txn)
                                <tr class="bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors">
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $txn->transaction_date ? \Carbon\Carbon::parse($txn->transaction_date)->format('M d, Y') : 'N/A' }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $txn->name }}</td>
                                    <td class="px-4 py-3 font-mono text-gray-600 dark:text-gray-400">{{ $txn->reference_number ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format($txn->total_debit, 3) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format($txn->total_credit, 3) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if($txn->signed_imbalance > 0)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">+{{ number_format($txn->imbalance, 3) }} Dr</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">+{{ number_format($txn->imbalance, 3) }} Cr</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 dark:bg-gray-900/70 border-t-2 border-gray-300 dark:border-gray-600">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right font-bold text-xs uppercase text-gray-600 dark:text-gray-300">Total Excess Debit:</td>
                                <td colspan="3" class="px-4 py-3 text-right font-bold text-red-700 dark:text-red-400">{{ number_format($totalExcessDebit, 3) }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right font-bold text-xs uppercase text-gray-600 dark:text-gray-300">Total Excess Credit:</td>
                                <td colspan="3" class="px-4 py-3 text-right font-bold text-amber-700 dark:text-amber-400">{{ number_format($totalExcessCredit, 3) }}</td>
                            </tr>
                            <tr class="border-t-2 border-gray-400 dark:border-gray-500">
                                <td colspan="3" class="px-4 py-3 text-right font-bold text-xs uppercase text-gray-700 dark:text-gray-200">Net Imbalance (Debit − Credit):</td>
                                <td colspan="3" class="px-4 py-3 text-right">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold {{ $netImbalance >= 0 ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300' }}">
                                        {{ number_format(abs($netImbalance), 3) }} {{ $netImbalance >= 0 ? 'Dr' : 'Cr' }}
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @else
            <div class="mt-6 bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <div class="text-blue-600 dark:text-blue-400 mt-0.5">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11-1a1 1 0 11-2 0 1 1 0 012 0zm3 1a1 1 0 100-2 1 1 0 000 2zm2-1a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-blue-900 dark:text-blue-100">No unbalanced transactions detected. ✓</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <style>
        @media print {
            .no-print { display: none !important; }
            body { background-color: white; }
        }
    </style>
</x-app-layout>
<style>
    @media print {
        .btn, form, .card-body > .row:first-child { display: none; }
        .table { font-size: 0.85rem; }
        .print-hide { display: none; }
    }

    .trial-balance-table tbody tr:hover {
        background-color: #f5f5f5;
    }

    .font-monospace {
        font-family: 'Courier New', monospace;
    }
</style>
