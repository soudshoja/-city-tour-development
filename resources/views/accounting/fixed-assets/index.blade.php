<x-app-layout>
    <div class="my-3">

        <!-- Page heading -->
        <div class="flex justify-between items-center gap-5 mb-6 flex-wrap">
            <div class="flex items-center space-x-4">
                <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center">
                    <a href="javascript:history.back()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                            <path fill="#FFC107" fill-rule="evenodd" d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                        </svg>
                    </a>
                </div>
                <div>
                    <h2 class="text-2xl md:text-3xl font-bold dark:text-white">Fixed Assets</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Register, straight-line schedule, disposal — every value below is derived, never cached.</p>
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <a href="{{ route('accounting.fixed-assets.depreciate') }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Run depreciation
                </a>
                @if ($canManage)
                    <a href="{{ route('accounting.fixed-assets.create') }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-800 text-white hover:bg-slate-700">
                        Register asset
                    </a>
                @endif
            </div>
        </div>

        @unless ($engineEnabled)
            <div class="mb-6 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 px-4 py-3 text-sm">
                <strong>Engine disabled</strong> for this company — the register still works (create, edit, view), but capitalise, disposal, and depreciation runs will not post anything until the accounting engine is enabled.
            </div>
        @endunless

        <!-- Filters -->
        <form method="GET" action="{{ route('accounting.fixed-assets.index') }}"
              class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4 mb-6 flex flex-wrap items-end gap-4">
            <div>
                <label for="filter-class" class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Class</label>
                <select id="filter-class" name="asset_class"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm min-w-[12rem]">
                    <option value="">All classes</option>
                    @foreach ($classes as $key => $classConfig)
                        <option value="{{ $key }}" @selected($filterClass === $key)>{{ $classConfig['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-status" class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Status</label>
                <select id="filter-status" name="status"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm min-w-[10rem]">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption }}" @selected($filterStatus === $statusOption)>{{ ucwords(str_replace('_', ' ', $statusOption)) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-800 text-white hover:bg-slate-700">
                Apply
            </button>
            @if ($filterClass || $filterStatus)
                <a href="{{ route('accounting.fixed-assets.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">Clear</a>
            @endif
        </form>

        <!-- Totals -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                <div class="text-xs uppercase tracking-wide text-gray-400">Assets</div>
                <div class="text-xl font-semibold dark:text-white">{{ $totals['count'] }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                <div class="text-xs uppercase tracking-wide text-gray-400">Cost</div>
                <div class="text-xl font-semibold dark:text-white">{{ number_format($totals['cost'], 3) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                <div class="text-xs uppercase tracking-wide text-gray-400">Accumulated depreciation</div>
                <div class="text-xl font-semibold dark:text-white">{{ number_format($totals['accumulated'], 3) }}</div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4">
                <div class="text-xs uppercase tracking-wide text-gray-400">Net book value</div>
                <div class="text-xl font-semibold dark:text-white">{{ number_format($totals['nbv'], 3) }}</div>
            </div>
        </div>

        <!-- Register table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow overflow-x-auto">
            <table class="min-w-full table-auto border-collapse text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Asset</th>
                        <th class="px-4 py-3 text-left">Class</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Cost</th>
                        <th class="px-4 py-3 text-right">Accumulated</th>
                        <th class="px-4 py-3 text-right">NBV</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($items as $row)
                        @php($asset = $row['asset'])
                        <tr class="text-gray-800 dark:text-gray-100">
                            <td class="px-4 py-3">
                                <a href="{{ route('accounting.fixed-assets.show', $asset) }}" class="font-medium hover:underline">{{ $asset->name }}</a>
                                @if ($asset->code)
                                    <div class="text-xs text-gray-400">{{ $asset->code }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['class_label'] }}</td>
                            <td class="px-4 py-3">
                                @php($status = $asset->status)
                                <span @class([
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold',
                                    'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200' => $status === 'draft',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $status === 'active',
                                    'bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300' => $status === 'fully_depreciated',
                                    'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => $status === 'disposed',
                                ])>
                                    {{ ucwords(str_replace('_', ' ', $status)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($row['cost'], 3) }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($row['accumulated'], 3) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold">{{ number_format($row['nbv'], 3) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('accounting.fixed-assets.show', $asset) }}" class="text-sm text-slate-700 dark:text-slate-300 hover:underline">View</a>
                                @if ($canManage)
                                    <span class="text-gray-300 dark:text-gray-600">·</span>
                                    <a href="{{ route('accounting.fixed-assets.edit', $asset) }}" class="text-sm text-slate-700 dark:text-slate-300 hover:underline">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">No fixed assets match this filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
