<x-app-layout>
    <div class="my-3">

        <div class="flex items-center space-x-4 mb-6">
            <div class="p-3 DarkBGcolor rounded-full shadow-md flex items-center justify-center">
                <a href="{{ route('accounting.fixed-assets.index') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 42 42">
                        <path fill="#FFC107" fill-rule="evenodd" d="M27.066 1L7 21.068l19.568 19.569l4.934-4.933l-14.637-14.636L32 5.933z" />
                    </svg>
                </a>
            </div>
            <div>
                <h2 class="text-2xl md:text-3xl font-bold dark:text-white">Run depreciation</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Preview a month before posting — the preview works even while the engine is disabled.</p>
            </div>
        </div>

        @unless ($engineEnabled)
            <div class="mb-6 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 px-4 py-3 text-sm">
                <strong>Engine disabled</strong> for this company — the preview below is still accurate, but posting is a logged no-op until the engine is enabled.
            </div>
        @endunless

        @if (session('run_result'))
            @php($runResult = session('run_result'))
            <div class="mb-6 rounded-lg border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm">
                Last run: {{ $runResult['posted'] }} posted, {{ $runResult['skipped'] }} skipped.
                @if (!empty($runResult['blocked']))
                    <div class="mt-2 text-amber-700 dark:text-amber-300">
                        Blocked: {{ implode(' · ', $runResult['blocked']) }}
                    </div>
                @endif
            </div>
        @endif

        <!-- Month picker -->
        <form method="GET" action="{{ route('accounting.fixed-assets.depreciate') }}"
              class="bg-white dark:bg-gray-800 rounded-lg BoxShadow p-4 mb-6 flex flex-wrap items-end gap-4">
            <div>
                <label for="year" class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Year</label>
                <input type="number" id="year" name="year" min="2000" max="2100" value="{{ $year }}"
                       class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm w-28">
            </div>
            <div>
                <label for="month" class="block text-xs uppercase tracking-wide text-gray-400 mb-1">Month</label>
                <select id="month" name="month"
                        class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}" @selected($month === $m)>{{ \Illuminate\Support\Carbon::create(2000, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-800 text-white hover:bg-slate-700">
                Preview
            </button>
        </form>

        @if ($preview !== null)
            <div class="bg-white dark:bg-gray-800 rounded-lg BoxShadow overflow-x-auto mb-6">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between flex-wrap gap-3">
                    <h3 class="font-semibold dark:text-white">
                        Preview for {{ \Illuminate\Support\Carbon::create($year, $month, 1)->format('F Y') }}
                        <span class="text-xs font-normal text-gray-400">({{ $preview['dry_run'] ? 'dry run — nothing posted yet' : 'posted' }})</span>
                    </h3>

                    @if (! $preview['dry_run'])
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $preview['posted'] }} posted &middot; {{ $preview['skipped'] }} skipped</span>
                    @else
                        <form method="POST" action="{{ route('accounting.fixed-assets.depreciate.run') }}">
                            @csrf
                            <input type="hidden" name="year" value="{{ $year }}">
                            <input type="hidden" name="month" value="{{ $month }}">
                            <button type="submit"
                                    class="px-4 py-2 rounded-lg text-sm font-medium bg-slate-800 text-white hover:bg-slate-700"
                                    onclick="return confirm('Post depreciation for {{ \Illuminate\Support\Carbon::create($year, $month, 1)->format('F Y') }}? This posts {{ count($preview['lines']) }} document(s).');">
                                {{ $engineEnabled ? 'Post this run' : 'Post (engine disabled — nothing will post)' }}
                            </button>
                        </form>
                    @endif
                </div>

                @if (! empty($preview['blocked']))
                    <div class="px-4 py-3 text-sm text-amber-700 dark:text-amber-300 border-b border-gray-100 dark:border-gray-700">
                        Blocked: {{ implode(' · ', $preview['blocked']) }}
                    </div>
                @endif

                <table class="min-w-full table-auto border-collapse text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-200 text-xs uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-3 text-left">Asset</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($preview['lines'] as $line)
                            <tr class="text-gray-800 dark:text-gray-100">
                                <td class="px-4 py-3">
                                    <a href="{{ route('accounting.fixed-assets.show', $line['fixed_asset_id']) }}" class="hover:underline">Asset #{{ $line['fixed_asset_id'] }}</a>
                                </td>
                                <td class="px-4 py-3 text-right font-mono">{{ number_format($line['amount'], 3) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-8 text-center text-gray-400">Nothing due for this month.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
