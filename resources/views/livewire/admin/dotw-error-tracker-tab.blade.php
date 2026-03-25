<div wire:poll.30000ms>

    {{-- Heading --}}
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Error Tracker</h2>

    {{-- Filter row --}}
    <div class="flex flex-wrap gap-3 mb-4">
        {{-- Error type filter --}}
        <select wire:model.live="filterErrorType"
            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Errors</option>
            <option value="booking_failed">Booking Failed</option>
            <option value="empty_response">Empty DOTW Response</option>
        </select>

        {{-- Company filter (super-admin only) --}}
        @if($isSuperAdmin)
        <input type="text" wire:model.live="filterCompanyId" placeholder="Company ID"
            class="w-32 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @endif

        {{-- Agent filter --}}
        <input type="text" wire:model.live="filterAgent" placeholder="Agent phone"
            class="w-36 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">

        {{-- Date filters --}}
        <input type="date" wire:model.live="filterDateFrom"
            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <input type="date" wire:model.live="filterDateTo"
            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">

        <button wire:click="resetFilters" class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 underline">Reset</button>
    </div>

    {{-- Error table --}}
    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Type</th>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Company</th>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Agent</th>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Operation / Detail</th>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Status</th>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Time</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($errors as $error)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    <td class="px-4 py-2.5">
                        @if($error->error_type === 'booking_failed')
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                Booking Failed
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Empty Response
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $error->company_id ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ $error->agent_phone ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">
                        @if($error->operation_type)
                            <span class="font-medium">{{ strtoupper($error->operation_type) }}</span>
                            @if($error->detail)
                                <span class="text-gray-400 ml-1">— {{ Str::limit($error->detail, 30) }}</span>
                            @endif
                        @else
                            {{ Str::limit($error->detail, 40) }}
                        @endif
                    </td>
                    <td class="px-4 py-2.5">
                        @if($error->status)
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-300">
                                {{ str_replace('_', ' ', $error->status) }}
                            </span>
                        @else
                            <span class="text-orange-500 dark:text-orange-400 text-xs font-medium">Investigate</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-gray-400 dark:text-gray-500 whitespace-nowrap">
                        {{ $error->created_at instanceof \Carbon\Carbon ? $error->created_at->format('Y-m-d H:i') : $error->created_at }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">No errors found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $errors->links() }}
    </div>

</div>
