<x-app-layout>
    <div class="flex justify-between items-center gap-5 my-3">
        <div class="flex items-center gap-5">
            <h2 class="text-2xl md:text-3xl font-bold">Lock Management</h2>
            <div data-tooltip="Financial record locking"
                class="relative w-10 h-10 md:w-12 md:h-12 flex items-center justify-center DarkBGcolor rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white">
                    <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3zm0 10c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>
                </svg>
            </div>
        </div>
        <div class="flex items-center gap-3" x-data="{ showBulkLock: false }">
            <button @click="showBulkLock = true" data-tooltip-left="Bulk lock by date"
                class="relative w-10 h-10 md:w-12 md:h-12 flex items-center justify-center bg-red-500 hover:bg-red-600 rounded-full shadow-sm cursor-pointer transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white">
                    <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"/>
                </svg>
            </button>

            {{-- Bulk Lock Modal --}}
            <div x-cloak x-show="showBulkLock" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                <div @click.away="showBulkLock = false" class="bg-white rounded-xl w-full max-w-lg shadow-xl">
                    <div class="p-4 border-b flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-800">Bulk Lock by Date</h3>
                            <p class="text-sm text-gray-500 mt-0.5">Lock all records on or before a specific date.</p>
                        </div>
                        <button type="button" @click="showBulkLock = false" class="text-gray-400 hover:text-red-500 text-2xl leading-none">&times;</button>
                    </div>
                    <form action="{{ route('lock-management.lock-by-period') }}" method="POST"
                        x-data="{ hasInvoices: true }"
                        onsubmit="return confirm('Are you sure? This will lock ALL matching records before the selected date.')">
                        @csrf
                        <div class="p-5 space-y-5">
                            {{-- Date --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Lock all records on or before *</label>
                                <input type="date" name="lock_before_date" required
                                    value="{{ old('lock_before_date', now()->subMonth()->endOfMonth()->format('Y-m-d')) }}"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-xs text-gray-400 mt-1">Tip: Set to end of last month to close the previous period.</p>
                            </div>

                            {{-- Record Types --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Record types to lock *</label>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach($recordTypes as $key => $config)
                                        <label class="flex items-center gap-2 p-2.5 rounded-lg border bg-white cursor-pointer hover:bg-gray-50 transition-colors">
                                            <input type="checkbox" name="record_types[]" value="{{ $key }}" checked
                                                class="form-checkbox h-4 w-4 text-blue-600 rounded"
                                                @if($key === 'invoices') x-model="hasInvoices" @endif>
                                            <span class="text-sm text-gray-700">{{ $config['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Invoice Status Filter --}}
                            <div x-show="hasInvoices" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Invoice status filter:</label>
                                <div class="flex flex-wrap gap-3">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="lock_status[]" value="paid" checked class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                        <span class="text-sm text-gray-700">Paid</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="lock_status[]" value="unpaid" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                        <span class="text-sm text-gray-700">Unpaid</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="lock_status[]" value="partial" class="form-checkbox h-4 w-4 text-blue-600 rounded">
                                        <span class="text-sm text-gray-700">Partial</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 border-t bg-gray-50 flex justify-end gap-2 rounded-b-xl">
                            <button type="button" @click="showBulkLock = false" class="px-4 py-2 text-sm rounded-lg border hover:bg-gray-100">Cancel</button>
                            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.89 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"/>
                                </svg>
                                Lock Records
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Overview Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
        @foreach($stats as $key => $stat)
            <div class="rounded-lg p-4 shadow-sm bg-{{ $stat['color'] }}-50 border border-{{ $stat['color'] }}-200">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-{{ $stat['color'] }}-600 uppercase tracking-wide">{{ $stat['label'] }}</span>
                    @if($stat['percentage'] == 100)
                        <span class="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-medium">✓ Closed</span>
                    @endif
                </div>
                <div class="text-2xl font-bold text-{{ $stat['color'] }}-700">{{ number_format($stat['total']) }}</div>
                <div class="flex items-center gap-3 mt-1 text-xs">
                    <span class="text-red-600">🔒 {{ $stat['locked'] }}</span>
                    <span class="text-green-600">🔓 {{ $stat['unlocked'] }}</span>
                </div>
                <div class="mt-2 w-full bg-gray-200 rounded-full h-1.5">
                    <div class="bg-{{ $stat['color'] }}-500 h-1.5 rounded-full transition-all" style="width: {{ $stat['percentage'] }}%"></div>
                </div>
                <div class="text-right text-xs text-gray-400 mt-0.5">{{ $stat['percentage'] }}% locked</div>
            </div>
        @endforeach
    </div>

    <div class="panel rounded-lg">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-gray-800">Monthly Period Closing</h2>
            <p class="text-sm text-gray-500 mt-1">Lock or unlock all financial records for a specific month. Use the <strong>Bulk Lock</strong> button above to lock by date range.</p>
        </div>

        <div class="space-y-3">
            @forelse($monthlySummary as $monthKey => $month)
                @php
                    $monthDate = \Carbon\Carbon::parse($monthKey . '-01');
                    $isFullyLocked = $month['unlocked'] == 0 && $month['total'] > 0;
                    $isPartiallyLocked = $month['locked'] > 0 && $month['unlocked'] > 0;
                    $percentage = $month['total'] > 0 ? round(($month['locked'] / $month['total']) * 100) : 0;
                @endphp
                <div x-data="{ expanded: false }" 
                        class="border rounded-lg {{ $isFullyLocked ? 'border-green-200 bg-green-50/30' : ($isPartiallyLocked ? 'border-amber-200 bg-amber-50/30' : 'border-gray-200 bg-white') }}">
                    
                    {{-- Month Header --}}
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 p-4 cursor-pointer" @click="expanded = !expanded">
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="flex-shrink-0 w-14 h-14 rounded-lg flex flex-col items-center justify-center {{ $isFullyLocked ? 'bg-green-100 text-green-700' : ($isPartiallyLocked ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700') }}">
                                <span class="text-xs font-medium uppercase">{{ $monthDate->format('M') }}</span>
                                <span class="text-lg font-bold leading-none">{{ $monthDate->format('Y') }}</span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-semibold text-gray-800">{{ $monthDate->format('F Y') }}</h4>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 mt-1">
                                    <span>{{ $month['total'] }} total records</span>
                                    <span class="text-red-600 font-medium">{{ $month['locked'] }} locked</span>
                                    <span class="text-gray-400">{{ $month['unlocked'] }} unlocked</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 w-32 hidden md:block">
                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                    <span>Locked</span>
                                    <span class="font-medium">{{ $percentage }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="{{ $isFullyLocked ? 'bg-green-500' : ($isPartiallyLocked ? 'bg-amber-500' : 'bg-gray-300') }} h-2 rounded-full transition-all" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>

                            @if($isFullyLocked)
                                <span class="inline-flex items-center gap-1 px-3 py-2 text-xs font-medium rounded-lg bg-green-100 text-green-700">
                                    ✓ Closed
                                </span>
                            @endif

                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Expanded Details --}}
                    <div x-show="expanded" x-cloak x-collapse class="border-t px-4 pb-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mt-4">
                            @foreach($month['types'] as $typeKey => $typeData)
                                <div class="flex items-center justify-between p-3 rounded-lg bg-white border">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700">{{ $typeData['label'] }}</span>
                                        <div class="flex items-center gap-2 text-xs text-gray-500 mt-0.5">
                                            <span>{{ $typeData['total'] }} total</span>
                                            <span class="text-red-500">{{ $typeData['locked'] }} locked</span>
                                            <span class="text-green-500">{{ $typeData['unlocked'] }} open</span>
                                        </div>
                                    </div>
                                    @if($typeData['unlocked'] == 0 && $typeData['total'] > 0)
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">✓</span>
                                    @elseif($typeData['unlocked'] > 0)
                                        <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">{{ $typeData['unlocked'] }} open</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        {{-- Month Actions --}}
                        <div class="flex flex-wrap items-center gap-2 mt-4 pt-3 border-t">
                            @if($month['unlocked'] > 0)
                                <form action="{{ route('lock-management.lock-by-month') }}" method="POST"
                                    onsubmit="return confirm('Lock ALL {{ $month['unlocked'] }} unlocked records for {{ $monthDate->format('F Y') }}?')">
                                    @csrf
                                    <input type="hidden" name="month" value="{{ $monthKey }}">
                                    @foreach(array_keys($month['types']) as $tk)
                                        <input type="hidden" name="record_types[]" value="{{ $tk }}">
                                    @endforeach
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-medium rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"/>
                                        </svg>
                                        Lock All ({{ $month['unlocked'] }})
                                    </button>
                                </form>
                            @endif

                            @if($month['locked'] > 0)
                                <div x-data="{ showUnlock: false }">
                                    <button @click="showUnlock = true" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-medium rounded-lg border border-amber-300 text-amber-700 bg-amber-50 hover:bg-amber-100 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                                        </svg>
                                        Unlock ({{ $month['locked'] }})
                                    </button>

                                    <div x-cloak x-show="showUnlock" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
                                        <div @click.away="showUnlock = false" class="bg-white rounded-xl w-full max-w-md shadow-xl">
                                            <div class="p-4 border-b">
                                                <h3 class="font-semibold text-gray-800">Unlock {{ $monthDate->format('F Y') }}</h3>
                                                <p class="text-sm text-gray-500 mt-1">This will unlock {{ $month['locked'] }} record(s) across all types.</p>
                                            </div>
                                            <form action="{{ route('lock-management.unlock-by-month') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="month" value="{{ $monthKey }}">
                                                @foreach(array_keys($month['types']) as $tk)
                                                    <input type="hidden" name="record_types[]" value="{{ $tk }}">
                                                @endforeach
                                                <div class="p-4">
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for unlocking *</label>
                                                    <textarea name="reason" rows="3" required placeholder="e.g., Need to correct entries for reconciliation..."
                                                        class="w-full border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                                                </div>
                                                <div class="p-4 border-t bg-gray-50 flex justify-end gap-2 rounded-b-xl">
                                                    <button type="button" @click="showUnlock = false" class="px-4 py-2 text-sm rounded-lg border hover:bg-gray-100">Cancel</button>
                                                    <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-amber-600 text-white hover:bg-amber-700">Unlock</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <p class="text-lg font-medium">No records found</p>
                    <p class="text-sm">Financial records will appear here once created.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if(session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                alert('{{ session('success') }}');
            });
        </script>
    @endif
</x-app-layout>
