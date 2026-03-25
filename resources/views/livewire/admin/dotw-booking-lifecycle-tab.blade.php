<div wire:poll.30000ms>

    {{-- Page Header --}}
    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Booking Lifecycle</h2>

    {{-- Filter Row --}}
    <div class="flex flex-wrap gap-3 mb-4">
        {{-- Status filter --}}
        <select wire:model.live="filterStatus"
            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Statuses</option>
            <option value="prebooked">Prebooked</option>
            <option value="pending_payment">Pending Payment</option>
            <option value="confirmed">Confirmed</option>
            <option value="failed">Failed</option>
            <option value="cancelled">Cancelled</option>
            <option value="expired">Expired</option>
        </select>

        {{-- Date from --}}
        <input type="date" wire:model.live="filterDateFrom"
            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">

        {{-- Date to --}}
        <input type="date" wire:model.live="filterDateTo"
            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-200 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-500">

        <button wire:click="resetFilters"
            class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 underline">
            Reset
        </button>
    </div>

    {{-- Booking Table --}}
    <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Booking</th>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Hotel</th>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Dates</th>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Track</th>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Status</th>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Lifecycle</th>
                    <th class="px-4 py-2 text-left text-gray-500 dark:text-gray-400 font-medium">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($bookings as $booking)
                    {{-- Main row --}}
                    <tr wire:click="toggleRow({{ $booking->id }})"
                        class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <td class="px-4 py-3 font-mono text-gray-600 dark:text-gray-400">
                            {{ Str::limit($booking->prebook_key, 16) }}
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 max-w-[150px] truncate">
                            {{ $booking->hotel_name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ $booking->check_in?->format('d M') }} – {{ $booking->check_out?->format('d M Y') }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                {{ $booking->track === 'b2b' ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' : '' }}
                                {{ $booking->track === 'b2b_gateway' ? 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300' : '' }}
                                {{ $booking->track === 'b2c' ? 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300' : '' }}
                            ">{{ $booking->track }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium
                                {{ in_array($booking->status, ['confirmed']) ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : '' }}
                                {{ in_array($booking->status, ['failed', 'expired']) ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300' : '' }}
                                {{ in_array($booking->status, ['prebooked', 'pending_payment', 'confirming']) ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300' : '' }}
                                {{ in_array($booking->status, ['cancelled', 'cancellation_pending']) ? 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' : '' }}
                            ">{{ str_replace('_', ' ', $booking->status) }}</span>
                        </td>
                        {{-- Horizontal stepper --}}
                        <td class="px-4 py-3">
                            @php $stages = $this->lifecycleStages($booking); @endphp
                            <div class="flex items-center gap-0.5">
                                @foreach($stages as $i => $stage)
                                    {{-- Stage dot --}}
                                    <div class="flex items-center">
                                        <div class="w-5 h-5 rounded-full flex items-center justify-center text-white text-[9px] font-bold flex-shrink-0
                                            {{ $stage['failed'] && $stage['reached'] ? 'bg-red-500' : '' }}
                                            {{ $stage['reached'] && !$stage['failed'] ? 'bg-blue-500' : '' }}
                                            {{ !$stage['reached'] ? 'bg-gray-200 dark:bg-gray-700' : '' }}"
                                            title="{{ $stage['label'] }}{{ $stage['timestamp'] ? ': ' . $stage['timestamp'] : '' }}">
                                            {{ $i + 1 }}
                                        </div>
                                        {{-- Connector line (not after last stage) --}}
                                        @if(!$loop->last)
                                            <div class="w-3 h-0.5
                                                {{ $stage['reached'] && !$stage['failed'] ? 'bg-blue-300' : 'bg-gray-200 dark:bg-gray-700' }}">
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-400 dark:text-gray-500 whitespace-nowrap">
                            {{ $booking->created_at->format('Y-m-d H:i') }}
                        </td>
                    </tr>

                    {{-- Expanded detail row --}}
                    @if($expandedRow === $booking->id)
                    <tr class="bg-gray-50 dark:bg-gray-800/60">
                        <td colspan="7" class="px-6 py-4">
                            <div class="flex flex-wrap gap-6">
                                @foreach($stages as $stage)
                                    @if($stage['reached'])
                                    <div class="flex items-start gap-2">
                                        {{-- Circle indicator --}}
                                        <div class="w-3 h-3 rounded-full mt-1 flex-shrink-0
                                            {{ $stage['failed'] ? 'bg-red-500' : 'bg-blue-500' }}">
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold
                                                {{ $stage['failed'] ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400' }}">
                                                {{ $stage['label'] }}
                                            </p>
                                            @if($stage['timestamp'])
                                                <p class="text-xs text-gray-400 dark:text-gray-500">{{ $stage['timestamp'] }}</p>
                                            @else
                                                <p class="text-xs text-gray-300 dark:text-gray-600">—</p>
                                            @endif
                                        </div>
                                    </div>
                                    @endif
                                @endforeach

                                {{-- Extra context: confirmation number, agent phone, prebook key --}}
                                <div class="ml-auto text-right text-xs text-gray-400 dark:text-gray-500 space-y-0.5">
                                    @if($booking->confirmation_no)
                                        <p>Confirmation: <span class="font-mono text-gray-600 dark:text-gray-300">{{ $booking->confirmation_no }}</span></p>
                                    @endif
                                    @if($booking->agent_phone)
                                        <p>Agent: {{ $booking->agent_phone }}</p>
                                    @endif
                                    @if($booking->cancellation_deadline)
                                        <p>Cancel Deadline: {{ $booking->cancellation_deadline->format('Y-m-d') }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">No bookings found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $bookings->links() }}
    </div>

</div>
