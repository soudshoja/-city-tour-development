<div wire:poll.30000ms="refreshMetrics">
    {{-- Section heading --}}
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-6">DOTW Module Overview</h2>
    <p class="text-xs text-gray-400 dark:text-gray-500 -mt-4 mb-6">Refreshes every 30 seconds</p>

    {{-- Stats cards row (grid-cols-2 on md, grid-cols-4 on lg) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        {{-- Card 1: Total Bookings — blue --}}
        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800">
            <p class="text-xs text-gray-500 dark:text-gray-400">Total Bookings</p>
            <div wire:loading.remove wire:target="refreshMetrics">
                <h3 class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ $totalBookings }}</h3>
            </div>
            <div wire:loading wire:target="refreshMetrics" class="animate-pulse mt-1">
                <div class="h-8 bg-blue-200 dark:bg-blue-800 rounded w-16"></div>
            </div>
        </div>

        {{-- Card 2: Bookings Today — green --}}
        <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-100 dark:border-green-800">
            <p class="text-xs text-gray-500 dark:text-gray-400">Bookings Today</p>
            <div wire:loading.remove wire:target="refreshMetrics">
                <h3 class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ $bookingsToday }}</h3>
            </div>
            <div wire:loading wire:target="refreshMetrics" class="animate-pulse mt-1">
                <div class="h-8 bg-green-200 dark:bg-green-800 rounded w-16"></div>
            </div>
        </div>

        {{-- Card 3: Errors Today — red --}}
        <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-100 dark:border-red-800">
            <p class="text-xs text-gray-500 dark:text-gray-400">Errors Today</p>
            <div wire:loading.remove wire:target="refreshMetrics">
                <h3 class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">{{ $errorsToday }}</h3>
            </div>
            <div wire:loading wire:target="refreshMetrics" class="animate-pulse mt-1">
                <div class="h-8 bg-red-200 dark:bg-red-800 rounded w-16"></div>
            </div>
        </div>

        {{-- Card 4: Active Prebooks — yellow --}}
        <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-100 dark:border-yellow-800">
            <p class="text-xs text-gray-500 dark:text-gray-400">Active Prebooks</p>
            <div wire:loading.remove wire:target="refreshMetrics">
                <h3 class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">{{ $activePrebooks }}</h3>
            </div>
            <div wire:loading wire:target="refreshMetrics" class="animate-pulse mt-1">
                <div class="h-8 bg-yellow-200 dark:bg-yellow-800 rounded w-16"></div>
            </div>
        </div>
    </div>

    {{-- Charts row (grid-cols-1 on md, grid-cols-2 on lg) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Bookings – Last 14 Days</h4>
            <div id="dotw-bookings-trend-chart"></div>
        </div>
        <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">API Operations – Last 7 Days</h4>
            <div id="dotw-operations-chart"></div>
        </div>
    </div>

    {{-- Recent API Calls table --}}
    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Recent API Calls (Last 25)</h4>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">ID</th>
                        <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Company</th>
                        <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Message ID</th>
                        <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Operation</th>
                        <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Response</th>
                        <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($recentLogs as $log)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $log['id'] }}</td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $log['company_id'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400 font-mono">{{ Str::limit($log['message_id'], 20) }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                {{ $log['operation_type'] === 'book' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : '' }}
                                {{ $log['operation_type'] === 'search' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' : '' }}
                                {{ in_array($log['operation_type'], ['rates', 'block']) ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' : '' }}
                            ">{{ $log['operation_type'] }}</span>
                        </td>
                        <td class="px-4 py-2">
                            @if($log['has_empty_response'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">
                                    {{-- Heroicons: exclamation-circle --}}
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Empty
                                </span>
                            @else
                                <span class="text-green-600 dark:text-green-400">OK</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-400 dark:text-gray-500">{{ $log['created_at'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400 dark:text-gray-500">No API calls recorded yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Charts JavaScript initialization --}}
    @script
    <script>
        let trendChart = null;
        let opsChart = null;

        function initCharts() {
            const isDark = document.documentElement.classList.contains('dark');
            const textColor = isDark ? '#9ca3af' : '#6b7280';

            // Destroy existing charts before re-init (prevents duplication on poll)
            if (trendChart) { trendChart.destroy(); trendChart = null; }
            if (opsChart) { opsChart.destroy(); opsChart = null; }

            const trendEl = document.getElementById('dotw-bookings-trend-chart');
            const opsEl = document.getElementById('dotw-operations-chart');
            if (!trendEl || !opsEl) return;

            trendChart = new ApexCharts(trendEl, {
                chart: { type: 'line', height: 220, toolbar: { show: false }, background: 'transparent' },
                series: [{ name: 'Bookings', data: @json($bookingTrendCounts) }],
                xaxis: { categories: @json($bookingTrendDates), labels: { style: { colors: textColor, fontSize: '10px' } } },
                yaxis: { labels: { style: { colors: textColor } } },
                colors: ['#3b82f6'],
                stroke: { curve: 'smooth', width: 2 },
                grid: { borderColor: isDark ? '#374151' : '#e5e7eb' },
                theme: { mode: isDark ? 'dark' : 'light' },
            });
            trendChart.render();

            opsChart = new ApexCharts(opsEl, {
                chart: { type: 'bar', height: 220, toolbar: { show: false }, background: 'transparent' },
                series: [{ name: 'Calls', data: @json($operationCounts) }],
                xaxis: { categories: @json($operationLabels), labels: { style: { colors: textColor } } },
                yaxis: { labels: { style: { colors: textColor } } },
                colors: ['#f59e0b'],
                plotOptions: { bar: { borderRadius: 4 } },
                grid: { borderColor: isDark ? '#374151' : '#e5e7eb' },
                theme: { mode: isDark ? 'dark' : 'light' },
            });
            opsChart.render();
        }

        // Initial render
        initCharts();

        // Re-render after every Livewire update (poll refresh)
        $wire.on('dashboardMetricsUpdated', () => {
            initCharts();
        });
    </script>
    @endscript
</div>
