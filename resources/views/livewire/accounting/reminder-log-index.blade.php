<div class="max-w-[1400px] mx-auto px-4 py-6 space-y-4" x-data="{ filtersOpen: false }">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Reminder log</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Every reminder generated or sent, across every kind and channel.</p>
        </div>
        <a href="{{ url('/settings') }}#accounting" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
            Reminder settings
        </a>
    </div>

    @if($resentId)
        <div wire:transition class="rounded-md border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/50 px-4 py-2.5 text-sm text-emerald-800 dark:text-emerald-200" aria-live="polite">
            Reminder #{{ $resentId }} was reset to pending and will be picked up on the next send run.
        </div>
    @endif

    {{-- Search + filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[220px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Search: client, agent, invoice number, task reference, error…"
                    aria-label="Search reminders"
                    class="w-full pl-9 pr-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            <button type="button" wire:click="$toggle('filtersOpen')" x-on:click="filtersOpen = !filtersOpen"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M6 12h12M10 19h4"/></svg>
                Filters
            </button>

            @if($kinds !== [] || $statuses !== [] || $targetTypes !== [] || $channel !== '' || $dateFrom !== '' || $dateTo !== '')
                <button type="button" wire:click="resetFilters" class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Clear all</button>
            @endif
        </div>

        <div x-show="filtersOpen" x-cloak x-transition class="border-t border-gray-100 dark:border-gray-700 pt-3 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <fieldset class="space-y-1.5">
                <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">Kind</legend>
                @foreach(\App\Http\Livewire\Accounting\ReminderLogIndex::KINDS as $kind)
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="kinds" value="{{ $kind }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        {{ str_replace('_', ' ', $kind) }}
                    </label>
                @endforeach
            </fieldset>

            <fieldset class="space-y-1.5">
                <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">Status</legend>
                @foreach(\App\Http\Livewire\Accounting\ReminderLogIndex::STATUSES as $status)
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="statuses" value="{{ $status }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        {{ ucfirst($status) }}
                    </label>
                @endforeach
            </fieldset>

            <fieldset class="space-y-1.5">
                <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">Target &amp; channel</legend>
                @foreach(\App\Http\Livewire\Accounting\ReminderLogIndex::TARGET_TYPES as $type)
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="targetTypes" value="{{ $type }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        {{ ucfirst($type) }}
                    </label>
                @endforeach
                <select wire:model.live="channel" class="w-full mt-1 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                    <option value="">Any channel</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="email">Email</option>
                    <option value="both">Both</option>
                </select>
            </fieldset>

            <fieldset class="space-y-1.5">
                <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">Scheduled between</legend>
                <input type="date" wire:model.live="dateFrom" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" aria-label="From date" />
                <input type="date" wire:model.live="dateTo" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" aria-label="To date" />
            </fieldset>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2.5 cursor-pointer select-none" wire:click="sortBy('id')">ID</th>
                        <th class="px-4 py-2.5">Kind</th>
                        <th class="px-4 py-2.5">Target</th>
                        <th class="px-4 py-2.5">Channel</th>
                        <th class="px-4 py-2.5 cursor-pointer select-none" wire:click="sortBy('status')">Status</th>
                        <th class="px-4 py-2.5 cursor-pointer select-none" wire:click="sortBy('scheduled_at')">Scheduled</th>
                        <th class="px-4 py-2.5">Sent</th>
                        <th class="px-4 py-2.5">Error</th>
                        @if($this->canManage())
                            <th class="px-4 py-2.5 text-right">Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($entries as $entry)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400 tabular-nums">#{{ $entry->id }}</td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-200">{{ $entry->reminder_kind ? str_replace('_', ' ', $entry->reminder_kind) : '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-200">
                                <span class="capitalize">{{ $entry->target_type }}</span>
                                <span class="text-gray-400 dark:text-gray-500">
                                    @if($entry->invoice)#{{ $entry->invoice->invoice_number }}
                                    @elseif($entry->payment)#{{ $entry->payment->voucher_number }}
                                    @elseif($entry->task)#{{ $entry->task->reference }}
                                    @elseif($entry->client){{ $entry->client->full_name ?? $entry->client->name ?? '' }}
                                    @endif
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ ucfirst($entry->channel ?? 'whatsapp') }}</td>
                            <td class="px-4 py-2.5">
                                @php
                                    $statusStyles = [
                                        'sent' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
                                        'pending' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
                                        'failed' => 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300',
                                        'cancelled' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusStyles[$entry->status] ?? $statusStyles['cancelled'] }}">
                                    {{ ucfirst($entry->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ optional($entry->scheduled_at)->format('M d, Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ optional($entry->sent_at)->format('M d, Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400 max-w-[220px] truncate" title="{{ $entry->error_message }}">{{ $entry->error_message ?: '—' }}</td>
                            @if($this->canManage())
                                <td class="px-4 py-2.5 text-right">
                                    @if(in_array($entry->status, ['failed', 'cancelled']))
                                        <button type="button" wire:click="resend({{ $entry->id }})" wire:confirm="Resend reminder #{{ $entry->id }}?"
                                            class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                            Resend
                                        </button>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->canManage() ? 9 : 8 }}" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">No reminders match these filters</p>
                                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">Reminders appear here once the generator runs, or a staff member creates one manually.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($entries->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $entries->links() }}
            </div>
        @endif
    </div>
</div>
