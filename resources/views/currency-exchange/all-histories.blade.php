<x-app-layout>
    <div class="p-4">
        <h2 class="text-xl font-bold mb-4 text-center">All Exchange Rate History</h2>
        @foreach($currencyExchanges as $exchange)
            <div class="mb-4 border rounded shadow" x-data="{ open: false }">
                <!-- Dropdown Header -->
                <button 
                    @click="open = !open" 
                    class="w-full flex justify-between items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-left"
                >
                    <span class="font-semibold">
                        {{ $exchange->company->name ?? '-' }}:
                        {{ $exchange->base_currency }} → {{ $exchange->exchange_currency }}
                    </span>
                    <svg :class="{ 'rotate-180': open }" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Dropdown Content -->
                <div x-show="open" x-transition class="p-4">
                    <table class="min-w-full bg-white mb-2 border text-center">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 border text-center">Date</th>
                                <th class="px-4 py-2 border text-center">Old Rate</th>
                                <th class="px-4 py-2 border text-center">New Rate</th>
                                <th class="px-4 py-2 border text-center">Method</th>
                                <th class="px-4 py-2 border text-center">Changed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($exchange->histories->sortByDesc('changed_at') as $history)
                            <tr>
                                <td class="px-4 py-2 border text-center">{{ $history->changed_at }}</td>
                                <td class="px-4 py-2 border text-center">{{ $history->old_rate }}</td>
                                <td class="px-4 py-2 border text-center">{{ $history->new_rate }}</td>
                                <td class="px-4 py-2 border text-center">{{ ucfirst($history->method) }}</td>
                                <td class="px-4 py-2 border text-center">{{ $history->user ? $history->user->name : 'System' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-gray-400 border py-2">
                                    No history records found.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
