
<style>
     select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: none !important;
        }
</style>
<section>
    <header class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Bonus Records
        </h2>

        <form method="GET" action="{{ route('profile.edit') }}"
        class="flex items-center gap-3 bg-white/60 dark:bg-gray-800/40 backdrop-blur-md px-4 py-2 rounded-full shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">

            <input type="hidden" name="tab" value="{{ request('tab', 'Bonus') }}">

            <div class="relative">
                <select name="filter_month" onchange="this.form.submit()"
                    class="appearance-none text-sm bg-transparent border-none focus:ring-0 dark:text-gray-100 text-gray-700 cursor-pointer pr-5">
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" {{ request('filter_month', now()->month) == $m ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <span class="text-gray-400 text-sm">/</span>

            <div class="relative">
                <select name="filter_year" onchange="this.form.submit()"
                    class="appearance-none text-sm bg-transparent border-none focus:ring-0 dark:text-gray-100 text-gray-700 cursor-pointer pr-5">
                    @foreach(range(now()->year, now()->year - 5) as $y)
                        <option value="{{ $y }}" {{ request('filter_year', now()->year) == $y ? 'selected' : '' }}>
                            {{ $y }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>

    </header>

    <div class="mb-4 flex items-center justify-between">
        <span class="text-md font-medium text-gray-600 dark:text-gray-400">Total Bonus</span>
        <div class="text-right leading-tight">
            <p class="text-lg font-bold text-blue-600 dark:text-blue-400">
                {{ number_format($hasBonus->sum('amount'), 2) }} KWD
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Last updated:
                <span class="text-blue-600 dark:text-blue-400">{{ now()->format('jS M Y') }}</span>
            </p>
        </div>
    </div>

    <div class="flex justify-center mt-6 mb-3">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Showing bonus records for:
            <span class="text-blue-600 dark:text-blue-400">
                {{ $filterBonus->format('F Y') }}
            </span>
        </p>
    </div>

    <div class="flex-1 overflow-y-auto custom-scrollbar rounded-lg bg-white/90 dark:bg-gray-900/80 backdrop-blur-sm ring-1 ring-gray-100 dark:ring-gray-800">
        <table class="w-full text-sm">
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900/90">
                <tr>
                    <th class="py-2 px-4 text-left font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Payment Ref</th>
                    <th class="py-2 px-4 text-left font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Description</th>
                    <th class="py-2 px-4 text-right font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Amount</th>
                    <th class="py-2 px-4 text-right font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">Created At</th>
                </tr>
            </thead>
            <tbody>
                @forelse($hasBonus as $bonus)
                <tr class="transition-all duration-200 hover:bg-blue-100 dark:hover:bg-blue-700">
                    <td class="py-2 px-4 text-gray-800 dark:text-gray-300 font-medium">{{ $bonus->transaction?->reference_number }}</td>
                    <td class="py-2 px-4 text-gray-600 dark:text-gray-300">{{ $bonus->transaction?->description }}</td>
                    <td class="py-2 px-4 text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format($bonus->amount, 2) }}</td>
                    <td class="py-2 px-4 text-right font-semibold text-gray-900 dark:text-gray-100">{{ $bonus->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="text-center py-4 text-gray-500 dark:text-gray-400 italic">
                        No bonus record found for this month.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</section>