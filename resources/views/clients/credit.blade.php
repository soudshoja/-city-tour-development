<x-app-layout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 dark:from-slate-950 dark:to-slate-900">
        <div class="container mx-auto px-4 py-8">
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Client Credit Ledger</h1>
                        <p class="text-lg text-gray-600 dark:text-slate-300">Client: {{ $client->name }}</p>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 px-8">
                <div class="bg-white dark:bg-slate-800 rounded-md shadow border border-green-100 dark:border-green-900/30 overflow-hidden">
                    <div class="p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-green-700 dark:text-green-300 uppercase tracking-wide">Total In</p>
                                <p class="text-2xl font-bold text-green-700 dark:text-green-300 mt-2">{{ number_format($totalIn, 2) }}</p>
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1">KWD</p>
                            </div>
                            <div class="bg-green-100 dark:bg-green-900/30 rounded-full p-2">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 px-4 py-2">
                        <div class="flex items-center text-green-600 dark:bg-green-900/20">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span class="text-sm font-medium">Refunds & Top-ups</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-md shadow border border-red-100 dark:border-red-900/30 overflow-hidden">
                    <div class="p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-red-600 dark:text-red-300 uppercase tracking-wide">Total Out</p>
                                <p class="text-2xl font-bold text-red-700 dark:text-red-300 mt-2">{{ number_format(abs($totalOut), 2) }}</p>
                                <p class="text-xs text-red-500 dark:text-red-400 mt-1">KWD</p>
                            </div>
                            <div class="bg-red-100 dark:bg-red-900/30 rounded-full p-2">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-red-50 dark:bg-red-900/20 px-4 py-2">
                        <div class="flex items-center text-red-600 dark:text-red-300">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                            </svg>
                            <span class="text-sm font-medium">Invoices & Charges</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-md shadow border border-blue-100 dark:border-blue-900/30 overflow-hidden">
                    <div class="p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-300 uppercase tracking-wide">Net Balance</p>
                                <p class="text-2xl font-bold mt-2 {{ $netBalance >= 0 ? 'text-blue-700 dark:text-blue-300' : 'text-red-700 dark:text-red-300' }}">
                                    {{ number_format($netBalance, 2) }}
                                </p>
                                <p class="text-xs mt-1 {{ $netBalance >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400' }}">KWD</p>
                            </div>
                            <div class="bg-blue-100 dark:bg-blue-900/30 rounded-full p-2">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 px-4 py-2">
                        <div class="flex items-center text-blue-700 dark:text-blue-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                            <span class="text-xs font-medium">Current Balance</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-900/60 rounded-2xl shadow-lg overflow-hidden border border-gray-200 dark:border-slate-700">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Transaction History</h3>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">{{ $credits->total() }} total transactions</p>
                </div>
                
                @if($credits->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-slate-800">
                                <tr class="text-center text-sm font-medium text-gray-700 dark:text-slate-300 uppercase tracking-wider">
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Debit (KWD)</th>
                                    <th>Credit (KWD)</th>
                                    <th>Balance (KWD)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-300 dark:divide-slate-700">
                                @php $runningBalance = 0; @endphp
                                @foreach($credits as $credit)
                                    @php $runningBalance += $credit->amount; @endphp
                                    <tr class="text-center">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $credit->created_at->format('d M Y') }}</div>
                                            <div class="text-sm text-gray-500 dark:text-slate-400">{{ $credit->created_at->format('h:i A') }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $t = strtolower($credit->type ?? '');
                                            $map = [
                                                'invoice' => ['bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                                            'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586l6 6V19a2 2 0 01-2 2z'],
                                                'topup'   => ['bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                                            'M12 6v12m6-6H6'],
                                                'refund'  => ['bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300',
                                                            'M4 4v5h.6m15.4 2A8 8 0 004.6 9H9m11 11v-5h-.6m0 0a8 8 0 01-15.4-2H15'],
                                            ];
                                            [$typeClass, $typeIcon] = $map[$t] ?? ['bg-gray-100 text-gray-800 dark:bg-slate-800/70 dark:text-slate-200','M9 12h6m-6 4h6'];
                                        @endphp
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $typeClass }}">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $typeIcon }}"></path>
                                                </svg>
                                                {{ ucfirst($credit->type) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900 dark:text-slate-100">{{ $credit->description }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($credit->amount < 0)
                                                <span class="text-sm font-semibold text-red-600 dark:text-red-400">
                                                    {{ number_format(abs($credit->amount), 2) }}
                                                </span>
                                            @else
                                                <span class="text-sm text-black dark:text-slate-400">0.00</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if($credit->amount >= 0)
                                                <span class="text-sm font-semibold text-green-600 dark:text-green-400">
                                                    {{ number_format($credit->amount, 2) }}
                                                </span>
                                            @else
                                                <span class="text-sm text-black dark:text-slate-400">0.00</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-sm font-bold {{ $runningBalance >= 0 ? 'text-gray-800 dark:text-slate-200' : 'text-red-700 dark:text-red-300' }}">
                                                {{ number_format($runningBalance, 2) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-slate-700">
                        <x-pagination :data="$credits" />
                    </div>
                @else
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-slate-100">No transactions found</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Try adjusting your filters or date range.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
