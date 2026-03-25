<x-app-layout>
    <div class="container mx-auto p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-gray-100">Tasks Report</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    @if($dateFrom && $dateTo)
                        Date Range: <span class="font-semibold">{{ \Carbon\Carbon::parse($dateFrom)->format('d-m-Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d-m-Y') }}</span>
                    @else
                        <span class="font-semibold">All Time</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-xs font-medium uppercase tracking-wide">Total Tasks</p>
                        <p class="text-3xl font-bold mt-1">{{ number_format($totalTasks) }}</p>
                    </div>
                    <div class="bg-white/30 rounded-full p-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-300 rounded-xl shadow-lg p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-emerald-100 text-xs font-medium uppercase tracking-wide">Total Debit</p>
                        <p class="text-3xl font-bold mt-1">{{ number_format($totalDebit, 3) }} <span class="text-base font-semibold">KWD</span></p>
                    </div>
                    <div class="bg-white/30 rounded-full p-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-rose-500 to-rose-300 rounded-xl shadow-lg p-5 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-rose-100 text-xs font-medium uppercase tracking-wide">Total Credit</p>
                        <p class="text-3xl font-bold mt-1">{{ number_format($totalCredit, 3) }} <span class="text-base font-semibold">KWD</span></p>
                    </div>
                    <div class="bg-white/30 rounded-full p-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-4 mb-6">
            <form method="POST" action="{{ route('reports.tasks') }}" id="filterForm">
                @csrf
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-2">Quick Date Filter</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['this_week' => 'This Week', 'this_month' => 'This Month', 'this_year' => 'This Year', 'january' => 'January', 'february' => 'February', 'march' => 'March', 'april' => 'April', 'may' => 'May', 'june' => 'June', 'july' => 'July', 'august' => 'August', 'september' => 'September', 'october' => 'October', 'november' => 'November', 'december' => 'December'] as $preset => $label)
                            <button type="button" onclick="setDatePreset('{{ $preset }}')"
                                class="preset-btn px-3 py-1.5 text-xs rounded-md transition {{ $datePreset === $preset ? 'bg-blue-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" name="date_preset" id="date_preset" value="{{ $datePreset ?? '' }}">
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Date Range</label>
                        <input type="text" id="date-range"
                            class="w-full h-10 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 cursor-pointer"
                            placeholder="Select date range" autocomplete="off" readonly />
                        <input type="hidden" name="date_from" id="date_from" value="{{ $dateFrom ?? '' }}">
                        <input type="hidden" name="date_to" id="date_to" value="{{ $dateTo ?? '' }}">
                    </div>

                    <x-multi-picker 
                        label="Suppliers"
                        name="supplier_ids"
                        :items="$suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->toArray()"
                        :preselected="collect(request('supplier_ids', []))->map(fn($v) => (int)$v)->all()"
                        allLabel="All Suppliers"
                        placeholder="Search suppliers..."
                        class="flex-1 min-w-[180px]"
                    />

                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Travel Date</label>
                        <input type="text" id="departure-date-range"
                            class="w-full h-10 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-sm px-3 cursor-pointer"
                            placeholder="Departure / Check-in" autocomplete="off" readonly />
                        <input type="hidden" name="travel_from" id="travel_from" value="{{ $travelFrom ?? '' }}">
                        <input type="hidden" name="travel_to" id="travel_to" value="{{ $travelTo ?? '' }}">
                    </div>

                    <x-multi-picker 
                        label="Statuses"
                        name="statuses"
                        :items="collect($availableStatuses)->map(fn($s) => ['id' => $s, 'name' => $s === 'payment_voucher' ? 'Payment Voucher' : ucfirst($s)])->toArray()"
                        :preselected="$statuses"
                        allLabel="All Statuses"
                        placeholder="Search statuses..."
                        class="flex-1 min-w-[180px]"
                    />

                    <x-multi-picker 
                        label="Issued By"
                        name="issued_by"
                        :items="collect($availableIssuedBy)->map(fn($i) => ['id' => $i, 'name' => $i])->toArray()"
                        :preselected="$issuedBy"
                        allLabel="All Issuers"
                        placeholder="Search issuers..."
                        class="flex-1 min-w-[180px]"
                    />

                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                            Filter
                        </button>
                        <a href="{{ route('reports.tasks') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </a>
                        <button type="submit" formaction="{{ route('reports.tasks.pdf') }}" formtarget="_blank" 
                            class="inline-flex items-center gap-2 h-10 px-4 rounded-md text-sm font-medium bg-purple-600 hover:bg-purple-700 text-white transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            PDF
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Reference</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Original Reference</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Passenger Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Supplier</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Debit</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Credit</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @php $runningBalance = 0; @endphp
                        @forelse($tasks as $item)
                        @php $runningBalance = $runningBalance + $item->debit - $item->credit; @endphp
                        <tr class="bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors {{ $item->type === 'transaction' ? 'bg-purple-50 dark:bg-purple-900/20' : '' }}">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $item->reference }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $item->original_reference ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $item->passenger_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $item->supplier_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">
                                {{ $item->date ? \Carbon\Carbon::parse($item->date)->format('d-m-Y') : '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $statusColors = [
                                        'issued' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
                                        'reissued' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                        'void' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                        'refund' => 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                                        'confirmed' => 'bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300',
                                        'void' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
                                        'payment_voucher' => 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                                    ];
                                    $statusColor = $statusColors[$item->status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                    {{ $item->status === 'payment_voucher' ? 'Payment' : ucfirst($item->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ $item->debit > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">
                                {{ $item->debit > 0 ? number_format($item->debit, 3) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ $item->credit > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                {{ $item->credit > 0 ? number_format($item->credit, 3) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right font-semibold {{ $runningBalance > 0 ? 'text-rose-600 dark:text-rose-400' : ($runningBalance < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400') }}">
                                {{ number_format($runningBalance, 3) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-500">
                                    <svg class="w-12 h-12 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <p class="text-lg font-medium">No tasks found</p>
                                    <p class="text-sm">Try adjusting your filters</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                <x-pagination :data="$tasks" />
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <script>
        function setDatePreset(preset) {
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
            document.getElementById('date_preset').value = preset;
            document.getElementById('filterForm').submit();
        }

        function clearPreset() {
            document.getElementById('date_preset').value = '';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const fromDate = document.getElementById('date_from').value;
            const toDate = document.getElementById('date_to').value;

            flatpickr("#date-range", {
                mode: "range",
                dateFormat: "Y-m-d",
                defaultDate: (fromDate && toDate) ? [fromDate, toDate] : null,
                showMonths: 1,
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        document.getElementById('date_from').value = instance.formatDate(selectedDates[0], "Y-m-d");
                        document.getElementById('date_to').value = instance.formatDate(selectedDates[1], "Y-m-d");
                        clearPreset();
                    }
                },
                onReady: function(selectedDates, dateStr, instance) {
                    if (fromDate && toDate) {
                        instance.element.value = fromDate + ' to ' + toDate;
                    }
                }
            });

            const travelFrom = document.getElementById('travel_from').value;
            const travelTo = document.getElementById('travel_to').value;

            flatpickr("#departure-date-range", {
                mode: "range",
                dateFormat: "Y-m-d",
                defaultDate: (travelFrom && travelTo) ? [travelFrom, travelTo] : null,
                showMonths: 1,
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        document.getElementById('travel_from').value = instance.formatDate(selectedDates[0], "Y-m-d");
                        document.getElementById('travel_to').value = instance.formatDate(selectedDates[1], "Y-m-d");
                    }
                },
                onReady: function(selectedDates, dateStr, instance) {
                    if (travelFrom && travelTo) {
                        instance.element.value = travelFrom + ' to ' + travelTo;
                    }
                }
            });
        });
    </script>
</x-app-layout>