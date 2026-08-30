<div class="max-w-[1600px] mx-auto px-4 py-6 space-y-4" x-data="{ columnsOpen: false, presetsOpen: false }" @click.outside="columnsOpen = false; presetsOpen = false">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Accounting Log Center</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Every posting, approval, and setting change in the accounting module, in one searchable trail.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="exportCsv"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v13m0 0-4-4m4 4 4-4M4 19h16"/></svg>
                Export CSV
            </button>
        </div>
    </div>

    {{-- New-entries banner: count-only poll, never auto-loads. aria-live so a screen-reader user
         hears the count change without needing to re-scan the page. --}}
    <div aria-live="polite">
        @if($newEntryCount > 0)
            <div wire:transition class="flex items-center justify-between gap-3 rounded-md border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/50 px-4 py-2.5 text-sm">
                <span class="text-blue-800 dark:text-blue-200">{{ $newEntryCount }} new {{ Str::plural('entry', $newEntryCount) }} since this page loaded.</span>
                <button type="button" wire:click="loadNewEntries" class="font-medium text-blue-700 dark:text-blue-300 hover:underline shrink-0">Load new entries</button>
            </div>
        @endif
    </div>
    <div wire:poll.30s.visible="pollForNewEntries"></div>

    {{-- Search + quick actions --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-3 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[220px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="{{ $this->hasActiveFilter() ? 'Search within the filtered results…' : 'Search everything: subject, actor, action, reason, document contents…' }}"
                    class="w-full pl-9 pr-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            <button type="button" wire:click="$toggle('filtersOpen')"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-md border {{ $filtersOpen ? 'border-blue-500 text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40' : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900' }} hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M6 12h12M10 19h4"/></svg>
                Filters
                @if($this->hasActiveFilter())<span class="inline-flex items-center justify-center w-4 h-4 text-[10px] font-semibold rounded-full bg-blue-600 text-white">•</span>@endif
            </button>

            <div class="relative">
                <button type="button" @click="columnsOpen = !columnsOpen" class="px-3 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-700">Columns</button>
                <div x-show="columnsOpen" x-transition.opacity.duration.100ms x-cloak class="absolute right-0 z-20 mt-1 w-48 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg p-2 space-y-1">
                    @foreach(\App\Http\Livewire\Accounting\AuditLogIndex::AVAILABLE_COLUMNS as $key => $label)
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200 px-1.5 py-1 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                            <input type="checkbox" wire:model.live="visibleColumns" value="{{ $key }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="relative">
                <button type="button" @click="presetsOpen = !presetsOpen" class="px-3 py-2 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-700">Presets</button>
                <div x-show="presetsOpen" x-transition.opacity.duration.100ms x-cloak class="absolute right-0 z-20 mt-1 w-64 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg p-3 space-y-2">
                    @forelse($presets as $preset)
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <button type="button" wire:click="loadPreset({{ $preset->id }})" class="text-left text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 truncate">{{ $preset->name }}</button>
                            <button type="button" wire:click="deletePreset({{ $preset->id }})" wire:confirm="Delete this saved filter?" class="text-gray-400 hover:text-red-600 shrink-0" aria-label="Delete preset">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No saved presets yet.</p>
                    @endforelse
                    <div class="pt-2 border-t border-gray-100 dark:border-gray-700 flex gap-1.5">
                        <input type="text" wire:model="presetName" placeholder="Name this filter set" class="flex-1 min-w-0 px-2 py-1.5 text-sm rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900" />
                        <button type="button" wire:click="savePreset" class="px-2.5 py-1.5 text-sm font-medium rounded bg-blue-600 text-white hover:bg-blue-700 shrink-0">Save</button>
                    </div>
                </div>
            </div>

            @if($this->hasActiveFilter() || $search !== '')
                <button type="button" wire:click="resetFilters" class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Clear all</button>
            @endif
        </div>

        {{-- Active filter chips --}}
        @if($this->hasActiveFilter())
            <div class="flex flex-wrap gap-1.5">
                @foreach(['actorTypes' => 'Actor', 'actions' => 'Action', 'subjectTypes' => 'Subject'] as $prop => $label)
                    @foreach($this->{$prop} as $value)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $label }}: {{ $value }}</span>
                    @endforeach
                @endforeach
                @foreach(['postingPeriod' => 'Period', 'transactionId' => 'Document', 'docType' => 'Doc type', 'accountCode' => 'Account', 'reason' => 'Reason', 'route' => 'Route', 'ip' => 'IP', 'changedField' => 'Changed field'] as $prop => $label)
                    @if($this->{$prop} !== '')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $label }}: {{ $this->{$prop} }}</span>
                    @endif
                @endforeach
                @if($branchId !== '')
                    @php $branchName = $branchOptions->firstWhere('id', (int) $branchId)?->name ?? $branchId; @endphp
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">Branch: {{ $branchName }}</span>
                @endif
                @foreach(['clientIds' => ['Client', $clientOptions ?? collect()], 'agentIds' => ['Agent', $agentOptions ?? collect()], 'supplierIds' => ['Supplier', $supplierOptions ?? collect()]] as $prop => [$label, $options])
                    @foreach($this->{$prop} as $id)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $label }}: {{ $options->firstWhere('id', (int) $id)?->name ?? $options->firstWhere('id', (int) $id)?->full_name ?? $id }}</span>
                    @endforeach
                @endforeach
                @if($dateFrom !== '' || $dateTo !== '')
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $dateFrom ?: '…' }} → {{ $dateTo ?: '…' }}</span>
                @endif
                <span class="text-xs text-gray-400 self-center">— search is scoped to these</span>
            </div>
        @endif

        {{-- Filter panel --}}
        <div x-show="$wire.filtersOpen" x-cloak x-transition class="border-t border-gray-100 dark:border-gray-700 pt-3 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

            <fieldset class="space-y-2">
                <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">When</legend>
                <select wire:model.live="datePreset" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                    <option value="">Custom range</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="7d">Last 7 days</option>
                    <option value="month">This month</option>
                </select>
                <div class="flex gap-2">
                    <input type="date" wire:model.live="dateFrom" class="w-1/2 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                    <input type="date" wire:model.live="dateTo" class="w-1/2 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                </div>
                <input type="text" wire:model.live.debounce.300ms="postingPeriod" placeholder="Posting period (YYYY-MM)" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">Who</legend>
                <div class="flex gap-3 text-sm text-gray-600 dark:text-gray-300">
                    @foreach(['user' => 'User', 'system' => 'System', 'webhook' => 'Webhook'] as $value => $label)
                        <label class="flex items-center gap-1.5"><input type="checkbox" wire:model.live="actorTypes" value="{{ $value }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" /> {{ $label }}</label>
                    @endforeach
                </div>
                <select wire:model.live="actorIds" multiple size="4" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                    @foreach($actorOptions as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">What happened</legend>
                <select wire:model.live="actions" multiple size="4" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                    @foreach(\App\Http\Livewire\Accounting\AuditLogIndex::KNOWN_ACTIONS as $action)
                        <option value="{{ $action }}">{{ $action }}</option>
                    @endforeach
                </select>
                <select wire:model.live="subjectTypes" multiple size="3" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900">
                    @foreach(\App\Http\Livewire\Accounting\AuditLogIndex::KNOWN_SUBJECT_TYPES as $type)
                        <option value="{{ $type }}">{{ str_replace('_', ' ', $type) }}</option>
                    @endforeach
                </select>
                <input type="text" wire:model.live.debounce.300ms="subjectNumber" placeholder="Subject number (invoice/refund no.)" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">Linked document</legend>
                <input type="text" wire:model.live.debounce.300ms="transactionId" placeholder="Transaction id or reference no." class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                <div class="flex gap-2">
                    <input type="text" wire:model.live.debounce.300ms="docType" placeholder="Doc type" class="w-1/2 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                    <input type="text" wire:model.live.debounce.300ms="subType" placeholder="Sub type" class="w-1/2 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                </div>
                <input type="text" wire:model.live.debounce.300ms="accountCode" placeholder="Account code" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                <div class="flex gap-2">
                    <input type="number" step="0.001" wire:model.live.debounce.300ms="amountMin" placeholder="Amount ≥" class="w-1/2 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                    <input type="number" step="0.001" wire:model.live.debounce.300ms="amountMax" placeholder="Amount ≤" class="w-1/2 text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                </div>
                <select wire:model.live="branchId" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" aria-label="Branch">
                    <option value="">All branches</option>
                    @foreach($branchOptions as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">Parties</legend>
                <div>
                    <label class="text-xs text-gray-400" for="audit-log-client-filter">Client</label>
                    <select id="audit-log-client-filter" wire:model.live="clientIds" multiple size="3" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" aria-label="Client">
                        @foreach($clientOptions as $client)
                            <option value="{{ $client->id }}">{{ $client->full_name ?? $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-400" for="audit-log-agent-filter">Agent</label>
                    <select id="audit-log-agent-filter" wire:model.live="agentIds" multiple size="3" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" aria-label="Agent">
                        @foreach($agentOptions as $agent)
                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-400" for="audit-log-supplier-filter">Supplier</label>
                    <select id="audit-log-supplier-filter" wire:model.live="supplierIds" multiple size="3" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" aria-label="Supplier">
                        @foreach($supplierOptions as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
            </fieldset>

            <fieldset class="space-y-2 md:col-span-2">
                <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">Content</legend>
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" wire:model.live.debounce.300ms="reason" placeholder="Reason contains…" class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                    <input type="text" wire:model.live.debounce.300ms="changedField" placeholder="Changed field (e.g. status)" class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                    <input type="text" wire:model.live.debounce.300ms="route" placeholder="Route name contains…" class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                    <input type="text" wire:model.live.debounce.300ms="ip" placeholder="IP address" class="text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                </div>
            </fieldset>

            @if($this->isAdmin())
                <fieldset class="space-y-2">
                    <legend class="text-xs font-semibold uppercase tracking-wide text-gray-400">Company (admin)</legend>
                    <input type="text" wire:model.live.debounce.300ms="companyIdOverride" placeholder="Company id" class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                </fieldset>
            @endif
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden relative">
        <div wire:loading.class="opacity-50" wire:target="search,actorTypes,actorIds,actions,subjectTypes,subjectNumber,transactionId,docType,subType,accountCode,branchId,clientIds,agentIds,supplierIds,amountMin,amountMax,postingPeriod,datePreset,dateFrom,dateTo,reason,route,ip,changedField,sortBy,resetFilters,loadPreset,loadNewEntries" class="transition-opacity duration-150">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 sticky top-0 z-10">
                        <tr>
                            @foreach($visibleColumns as $col)
                                @php $sortable = in_array($col, ['created_at','action','posting_period']); @endphp
                                <th scope="col"
                                    @if($sortable) aria-sort="{{ $sortField === $col ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}" @endif
                                    class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide whitespace-nowrap">
                                    @if($sortable)
                                        {{-- Real button, not a clickable <th>: keyboard- and screen-reader-operable sort control. --}}
                                        <button type="button" wire:click="sortBy('{{ $col }}')"
                                            class="inline-flex items-center gap-1 -mx-1 px-1 py-0.5 rounded hover:text-gray-700 dark:hover:text-gray-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500 focus-visible:outline-offset-1">
                                            {{ \App\Http\Livewire\Accounting\AuditLogIndex::AVAILABLE_COLUMNS[$col] }}
                                            @if($sortField === $col)
                                                <span class="text-blue-500" aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                            @endif
                                        </button>
                                    @else
                                        {{ \App\Http\Livewire\Accounting\AuditLogIndex::AVAILABLE_COLUMNS[$col] }}
                                    @endif
                                </th>
                            @endforeach
                            <th class="px-3 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                        @forelse($entries as $entry)
                            {{-- Keyboard- and screen-reader-operable expand/collapse: a bare <tr
                                 wire:click> (the prior version) is invisible to keyboard and AT
                                 users — a <tr> is not natively focusable or activatable. --}}
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500 focus-visible:-outline-offset-2"
                                wire:click="toggleRow({{ $entry->id }})"
                                wire:key="row-{{ $entry->id }}"
                                tabindex="0" role="button"
                                aria-expanded="{{ $expandedRow === $entry->id ? 'true' : 'false' }}"
                                aria-label="{{ $expandedRow === $entry->id ? 'Collapse' : 'Expand' }} details for {{ $entry->action }} entry"
                                x-on:keydown.enter="$wire.toggleRow({{ $entry->id }})" x-on:keydown.space.prevent="$wire.toggleRow({{ $entry->id }})">
                                @foreach($visibleColumns as $col)
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-700 dark:text-gray-200">
                                        @switch($col)
                                            @case('created_at')
                                                <span class="tabular-nums text-gray-500 dark:text-gray-400">{{ optional($entry->created_at)->format('Y-m-d H:i:s') }}</span>
                                                @break
                                            @case('action')
                                                <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $entry->action }}</span>
                                                @break
                                            @case('subject')
                                                @if($entry->subject_type)
                                                    <span class="text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', $entry->subject_type) }}</span>
                                                    @if($entry->subjectUrl())
                                                        <a href="{{ $entry->subjectUrl() }}" wire:click.stop class="ml-1 text-blue-600 dark:text-blue-400 hover:underline tabular-nums">#{{ $entry->subject_id }}</a>
                                                    @elseif($entry->subject_id)
                                                        <span class="ml-1 tabular-nums">#{{ $entry->subject_id }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @endif
                                                @break
                                            @case('actor')
                                                {{ optional($entry->actor)->name ?? ucfirst($entry->actor_type) }}
                                                @break
                                            @case('transaction')
                                                @if($entry->transactionUrl())
                                                    <a href="{{ $entry->transactionUrl() }}" wire:click.stop class="text-blue-600 dark:text-blue-400 hover:underline tabular-nums">#{{ $entry->transaction_id }}</a>
                                                @elseif($entry->transaction_id)
                                                    <span class="tabular-nums">#{{ $entry->transaction_id }}</span>
                                                @else
                                                    <span class="text-gray-300 dark:text-gray-600">—</span>
                                                @endif
                                                @break
                                            @case('posting_period')
                                                <span class="tabular-nums">{{ $entry->posting_period ?? '—' }}</span>
                                                @break
                                            @case('reason')
                                                <span class="block max-w-xs truncate text-gray-500 dark:text-gray-400">{{ $entry->reason ?? '—' }}</span>
                                                @break
                                            @case('route')
                                                <span class="text-gray-400 text-xs">{{ $entry->route ?? '—' }}</span>
                                                @break
                                            @case('ip')
                                                <span class="text-gray-400 text-xs tabular-nums">{{ $entry->ip ?? '—' }}</span>
                                                @break
                                        @endswitch
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 text-gray-400">
                                    <svg class="w-4 h-4 transition-transform {{ $expandedRow === $entry->id ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
                                </td>
                            </tr>
                            @if($expandedRow === $entry->id)
                                <tr class="bg-gray-50 dark:bg-gray-900/40">
                                    <td colspan="{{ count($visibleColumns) + 1 }}" class="px-4 py-3">
                                        @php
                                            $before = $entry->before ?? [];
                                            $after = $entry->after ?? [];
                                            $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
                                        @endphp
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-mono">
                                            <div>
                                                <p class="uppercase tracking-wide text-gray-400 mb-1 font-sans font-semibold">Before</p>
                                                <dl class="space-y-0.5">
                                                    @forelse($keys as $key)
                                                        @php $changed = ($before[$key] ?? null) !== ($after[$key] ?? null); @endphp
                                                        <div class="flex gap-2 {{ $changed ? 'bg-amber-50 dark:bg-amber-950/30' : '' }} px-1 py-0.5 rounded">
                                                            <dt class="text-gray-400 shrink-0">{{ $key }}:</dt>
                                                            <dd class="text-gray-700 dark:text-gray-300 break-all">{{ json_encode($before[$key] ?? null) }}</dd>
                                                        </div>
                                                    @empty
                                                        <span class="text-gray-400 font-sans">No before state recorded.</span>
                                                    @endforelse
                                                </dl>
                                            </div>
                                            <div>
                                                <p class="uppercase tracking-wide text-gray-400 mb-1 font-sans font-semibold">After</p>
                                                <dl class="space-y-0.5">
                                                    @forelse($keys as $key)
                                                        @php $changed = ($before[$key] ?? null) !== ($after[$key] ?? null); @endphp
                                                        <div class="flex gap-2 {{ $changed ? 'bg-emerald-50 dark:bg-emerald-950/30' : '' }} px-1 py-0.5 rounded">
                                                            <dt class="text-gray-400 shrink-0">{{ $key }}:</dt>
                                                            <dd class="text-gray-700 dark:text-gray-300 break-all">{{ json_encode($after[$key] ?? null) }}</dd>
                                                        </div>
                                                    @empty
                                                        <span class="text-gray-400 font-sans">No after state recorded.</span>
                                                    @endforelse
                                                </dl>
                                            </div>
                                        </div>
                                        @if($entry->reason)
                                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 font-sans"><span class="font-semibold">Reason:</span> {{ $entry->reason }}</p>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ count($visibleColumns) + 1 }}" class="px-4 py-12 text-center">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No audit entries match these filters.</p>
                                    @if($this->hasActiveFilter() || $search !== '')
                                        <button type="button" wire:click="resetFilters" class="mt-2 text-sm text-blue-600 dark:text-blue-400 hover:underline">Clear filters and search again</button>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        {{ $entries->links() }}
    </div>
</div>
