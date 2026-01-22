<x-app-layout>
    <div>
        <div class="flex justify-between items-center gap-5 my-3">
            <div class="flex items-center gap-5">
                <h2 class="text-3xl font-bold">Exchange Rate History</h2>
                <div data-tooltip="Total currency pairs"
                    class="relative w-12 h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                    <span class="text-lg font-bold text-white">{{ $currencyExchanges->count() }}</span>
                </div>
            </div>
            <a href="{{ route('exchange.index') }}" class="btn btn-outline-primary flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Exchange Rates
            </a>
        </div>

        <div class="bg-white rounded-lg shadow dark:bg-gray-800 p-4">
            @if($currencyExchanges->isEmpty())
            <div class="flex flex-col items-center justify-center py-12 text-gray-500">
                <svg class="w-16 h-16 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-lg font-medium">No exchange rate history found</p>
                <p class="text-sm text-gray-400 mt-1">History will appear here when exchange rates are updated</p>
            </div>
            @else
            <div class="space-y-3">
                @foreach($currencyExchanges as $exchange)
                <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden" x-data="{ open: false }">
                    <button 
                        @click="open = !open" 
                        class="w-full flex justify-between items-center px-5 py-4 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                    >
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-1.5 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 rounded-lg font-semibold text-sm">
                                    {{ $exchange->base_currency }}
                                </span>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                                <span class="px-3 py-1.5 bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded-lg font-semibold text-sm">
                                    {{ $exchange->exchange_currency }}
                                </span>
                            </div>
                            <div class="hidden sm:flex items-center gap-2 text-gray-500 dark:text-gray-400">
                                <span class="text-sm">Current:</span>
                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ number_format($exchange->exchange_rate, 6) }}</span>
                            </div>
                            <span class="px-2 py-1 bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 rounded text-xs font-medium">
                                {{ $exchange->histories->count() }} {{ Str::plural('change', $exchange->histories->count()) }}
                            </span>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            @if($exchange->is_manual)
                                <span class="hidden sm:inline-flex badge whitespace-nowrap px-2 py-1 rounded text-xs font-medium badge-outline-primary">
                                    Manual
                                </span>
                            @else
                                <span class="hidden sm:inline-flex badge whitespace-nowrap px-2 py-1 rounded text-xs font-medium badge-outline-success">
                                    Auto
                                </span>
                            @endif

                            <svg :class="{ 'rotate-180': open }" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </button>

                    <div x-show="open" x-collapse x-cloak>
                        <div class="p-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                            @if($exchange->histories->isEmpty())
                            <div class="text-center py-8 text-gray-400">
                                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p class="text-sm">No history records for this currency pair</p>
                            </div>
                            @else
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            <th class="px-4 py-3 bg-gray-50 dark:bg-gray-700 rounded-l-lg">Date & Time</th>
                                            <th class="px-4 py-3 bg-gray-50 dark:bg-gray-700">Old Rate</th>
                                            <th class="px-4 py-3 bg-gray-50 dark:bg-gray-700">New Rate</th>
                                            <th class="px-4 py-3 bg-gray-50 dark:bg-gray-700">Change</th>
                                            <th class="px-4 py-3 bg-gray-50 dark:bg-gray-700">Method</th>
                                            <th class="px-4 py-3 bg-gray-50 dark:bg-gray-700 rounded-r-lg">Changed By</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        @foreach($exchange->histories->sortByDesc('changed_at') as $history)
                                        @php
                                            $changePercent = $history->old_rate > 0 ? (($history->new_rate - $history->old_rate) / $history->old_rate) * 100 : 0;
                                            $isIncrease = $history->new_rate > $history->old_rate;
                                        @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                            <td class="px-4 py-3">
                                                <div class="flex flex-col">
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ \Carbon\Carbon::parse($history->changed_at)->format('d M Y') }}
                                                    </span>
                                                    <span class="text-xs text-gray-400">
                                                        {{ \Carbon\Carbon::parse($history->changed_at)->format('H:i:s') }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                                    {{ number_format($history->old_rate, 6) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                                    {{ number_format($history->new_rate, 6) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-1">
                                                    @if($isIncrease)
                                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                                    </svg>
                                                    <span class="text-sm font-medium text-green-600">
                                                        +{{ number_format(abs($changePercent), 2) }}%
                                                    </span>
                                                    @else
                                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                                    </svg>
                                                    <span class="text-sm font-medium text-red-600">
                                                        -{{ number_format(abs($changePercent), 2) }}%
                                                    </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($history->method === 'manual')
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 rounded text-xs font-medium">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                                    </svg>
                                                    Manual
                                                </span>
                                                @else
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded text-xs font-medium">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                    </svg>
                                                    Auto
                                                </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-7 h-7 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">
                                                            {{ $history->user ? strtoupper(substr($history->user->name, 0, 1)) : 'S' }}
                                                        </span>
                                                    </div>
                                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                                        {{ $history->user ? $history->user->name : 'System' }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</x-app-layout>