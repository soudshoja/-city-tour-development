<div class="max-w-[1400px] mx-auto px-4 py-6 space-y-4">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Purpose Mapping</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Every purpose code the posting engine can resolve, and the account it currently maps to.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($gapCount > 0)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-md border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                    {{ $gapCount }} {{ Str::plural('gap', $gapCount) }} need{{ $gapCount === 1 ? 's' : '' }} attention
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold rounded-md border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    Every purpose code is mapped
                </span>
            @endif
        </div>
    </div>

    {{-- Flash --}}
    @if($flashMessage !== '')
        <div wire:transition
            class="rounded-md border px-4 py-2.5 text-sm {{ $flashType === 'success' ? 'border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300' : 'border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/40 text-rose-800 dark:text-rose-300' }}">
            {{ $flashMessage }}
        </div>
    @endif

    @if($companyId === null)
        <div class="rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 text-sm text-gray-500 dark:text-gray-400">
            Could not resolve a company for this account.
        </div>
    @else
        {{-- Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="text-left px-4 py-2.5">Purpose code</th>
                            <th class="text-left px-4 py-2.5">Status</th>
                            <th class="text-left px-4 py-2.5">Mapped account</th>
                            <th class="text-right px-4 py-2.5">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($rows as $row)
                            @php
                                $isGap = !$row['mapped'];
                                $isInvalid = $row['mapped'] && ($row['invalidLeaf'] || $row['disabledAccount']);
                                $target = $row['purposeCode'].'|'.($row['serviceType'] ?? '');
                                $isRepairing = $repairTarget !== null && $repairTarget[0] === $row['purposeCode'] && $repairTarget[1] === $row['serviceType'];
                            @endphp
                            <tr class="{{ $isInvalid ? 'bg-rose-50/60 dark:bg-rose-950/20' : ($isGap ? 'bg-amber-50/60 dark:bg-amber-950/20' : '') }}">
                                <td class="px-4 py-3 align-top">
                                    <div class="font-mono text-xs font-semibold text-gray-800 dark:text-gray-100">{{ $row['purposeCode'] }}</div>
                                    @if($row['serviceType'] !== null)
                                        <div class="text-xs text-gray-400 mt-0.5">service_type: {{ $row['serviceType'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    @if($isInvalid)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full border border-rose-300 dark:border-rose-700 bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300">
                                            {{ $row['disabledAccount'] ? 'Mapped to disabled account' : 'Mapped to non-leaf' }}
                                        </span>
                                    @elseif($isGap)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-full border border-amber-300 dark:border-amber-700 bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">
                                            Unmapped
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                            Mapped
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top">
                                    @if($row['account'])
                                        <div class="text-gray-800 dark:text-gray-100">{{ $row['account']->name }}</div>
                                        <div class="text-xs text-gray-400 font-mono">{{ $row['account']->code }}</div>
                                    @else
                                        <span class="text-gray-400 italic">No account mapped</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 align-top text-right">
                                    @if($isRepairing)
                                        <button type="button" wire:click="cancelRepair" class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Cancel</button>
                                    @else
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" wire:click="startRepair('{{ $row['purposeCode'] }}', {{ $row['serviceType'] !== null ? "'".$row['serviceType']."'" : 'null' }})"
                                                class="px-2.5 py-1.5 text-xs font-medium rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                                {{ $row['mapped'] ? 'Remap' : 'Map to existing account' }}
                                            </button>
                                            @if($isGap && $row['creatable'])
                                                <button type="button" wire:click="createLeaf('{{ $row['purposeCode'] }}')" wire:confirm="Create the decided system leaf for {{ $row['purposeCode'] }} and map it now?"
                                                    class="px-2.5 py-1.5 text-xs font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                                                    Create leaf
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @if($isRepairing)
                                <tr>
                                    <td colspan="4" class="px-4 py-3 bg-gray-50 dark:bg-gray-900/40 border-t border-gray-100 dark:border-gray-700">
                                        <div class="max-w-md space-y-2">
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-400">Search accounts by name or code</label>
                                            <input type="text" wire:model.live.debounce.300ms="accountSearch" placeholder="e.g. Suspense, 2110…"
                                                class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900" />
                                            <ul class="divide-y divide-gray-100 dark:divide-gray-700 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 max-h-56 overflow-y-auto">
                                                @forelse($repairCandidates as $candidate)
                                                    <li>
                                                        <button type="button" wire:click="mapToAccount({{ $candidate->id }})"
                                                            class="w-full flex items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-blue-50 dark:hover:bg-blue-950/40 transition-colors">
                                                            <span class="text-gray-800 dark:text-gray-100">{{ $candidate->name }}</span>
                                                            <span class="text-xs font-mono text-gray-400">{{ $candidate->code }}</span>
                                                        </button>
                                                    </li>
                                                @empty
                                                    <li class="px-3 py-3 text-sm text-gray-400 italic">No matching leaf accounts.</li>
                                                @endforelse
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Anchor purpose codes: informational only, not repairable from this screen (see
             component docblock — resolveAnchor()'s target is a GROUP, not a leaf, and seeding one
             is explicitly out of this build's scope). --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Anchor purpose codes</h2>
            <p class="text-xs text-gray-400 mt-0.5 mb-3">These name a group a per-party leaf mints under, not a direct posting target. Shown for visibility only — not repairable from this screen.</p>
            <div class="flex flex-wrap gap-2">
                @foreach($anchorRows as $anchor)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-mono rounded-full border {{ $anchor['mapped'] ? 'border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300' : 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300' }}">
                        {{ $anchor['purposeCode'] }}
                        @if($anchor['mapped'])
                            — {{ $anchor['account']->name }}
                        @else
                            — not mapped
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
