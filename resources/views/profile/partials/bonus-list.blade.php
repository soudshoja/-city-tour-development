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
        <div class="">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Bonus Records
            </h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                Records of bonus that assigned to you by company
            </p>
        </div>
    </header>

    @php
    $filterMonth = (int) request('filter_month', now()->month);
    $filterYear = (int) request('filter_year', now()->year);

    $filteredBonuses = $filteredBonuses->filter(function($bonus) use ($filterMonth, $filterYear) {
    return $bonus->created_at->month === $filterMonth
    && $bonus->created_at->year === $filterYear;
    });
    @endphp

    <div class="flex items-start justify-between mb-10">

        <div class="flex flex-col">
            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                Total bonus earned in {{ $filterBonus->format('F Y') }}
            </span>
            <p class="text-lg font-bold text-blue-600 dark:text-blue-400 mt-1">
                {{ number_format($filteredBonuses->sum('amount'), 3) }} KWD
            </p>
        </div>

        <div class="flex items-center gap-2">
            <form method="GET" id="bonusFilterForm" action="{{ route('profile.edit') }}"
                class="flex items-center gap-1 bg-white/60 dark:bg-gray-800/40 px-4 py-2 rounded-full shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">

                <input type="hidden" name="tab" value="Bonus">

                {{-- Month --}}
                <div x-data="{
                open: false,
                selected: {{ request('filter_month', now()->month) }},
                months: ['January','February','March','April','May','June','July','August','September','October','November','December']
            }" class="relative">
                    <input type="hidden" name="filter_month" x-model="selected">
                    <button type="button" @click="open = !open" @click.outside="open = false"
                        class="text-sm text-gray-700 dark:text-gray-100 cursor-pointer">
                        <span x-text="months[selected - 1]"></span>
                    </button>
                    <div x-show="open" x-cloak
                        class="absolute top-8 left-0 z-[9999] bg-white dark:bg-gray-800 rounded-xl shadow-lg ring-1 ring-gray-100 dark:ring-gray-700 py-2 min-w-[140px]">
                        <template x-for="(month, index) in months" :key="index">
                            <button type="button"
                                @click="selected = index + 1; open = false"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-blue-50 dark:hover:bg-gray-700 transition"
                                :class="selected === index + 1 ? 'text-blue-600 font-semibold bg-blue-50 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-200'"
                                x-text="month">
                            </button>
                        </template>
                    </div>
                </div>

                <span class="text-gray-400 text-sm">/</span>

                {{-- Year --}}
                <div x-data="{
                open: false,
                selected: {{ request('filter_year', now()->year) }},
                years: {{ json_encode(range(now()->year, now()->year - 5)) }}
            }" class="relative">
                    <input type="hidden" name="filter_year" x-model="selected">
                    <button type="button" @click="open = !open" @click.outside="open = false"
                        class="text-sm text-gray-700 dark:text-gray-100 cursor-pointer">
                        <span x-text="selected"></span>
                    </button>
                    <div x-show="open" x-cloak
                        class="absolute top-8 left-0 z-[9999] bg-white dark:bg-gray-800 rounded-xl shadow-lg ring-1 ring-gray-100 dark:ring-gray-700 py-2 min-w-[90px]">
                        <template x-for="year in years" :key="year">
                            <button type="button"
                                @click="selected = year; open = false"
                                class="w-full text-left px-4 py-2 text-sm hover:bg-blue-50 dark:hover:bg-gray-700 transition"
                                :class="selected === year ? 'text-blue-600 font-semibold bg-blue-50 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-200'"
                                x-text="year">
                            </button>
                        </template>
                    </div>
                </div>
            </form>

            {{-- Filter Button --}}
            <button type="submit" form="bonusFilterForm"
                class="w-8 h-8 inline-flex items-center justify-center rounded-full bg-blue-600 text-white hover:bg-blue-700 transition shadow-sm"
                title="Filter">
                <i class="fas fa-filter text-sm"></i>
            </button>

            {{-- Reset Button --}}
            @if(request('filter_month') || request('filter_year'))
            <a href="{{ route('profile.edit', ['tab' => 'Bonus']) }}"
                class="w-8 h-8 inline-flex items-center justify-center rounded-full bg-gray-600 text-white hover:bg-gray-700 transition shadow-sm"
                title="Reset">
                <i class="fas fa-rotate-left text-sm"></i>
            </a>
            @endif
        </div>

    </div>

    <div class="flex-1 overflow-y-auto custom-scrollbar rounded-lg bg-white/90 dark:bg-gray-900/80 backdrop-blur-sm ring-1 ring-gray-100 dark:ring-gray-800">
        <table class="w-full text-sm">
            <thead class="sticky top-0 z-10 bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-900/90">
                <tr class="py-2 px-4 text-left font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide text-[11px]">
                    <th class="">Payment Ref</th>
                    <th class="">Description</th>
                    <th class="">Amount</th>
                    <th class="">Assigned By</th>
                    <th class="">Created At</th>
                </tr>
            </thead>
            <tbody>
                @forelse($filteredBonuses as $bonus)
                @php
                $created_by = \App\Models\User::find($bonus->created_by)?->name ?? 'N/A'
                @endphp
                <tr class="transition-all duration-200 hover:bg-blue-100 dark:hover:bg-blue-700">
                    <td class="py-2 px-4 text-gray-800 dark:text-gray-300 font-medium">{{ $bonus->transaction?->reference_number }}</td>
                    <td class="py-2 px-4 text-gray-600 dark:text-gray-300">{{ $bonus->transaction?->description }}</td>
                    <td class="py-2 px-4 font-semibold text-gray-900 dark:text-gray-100">{{ number_format($bonus->amount, 3) }}</td>
                    <td class="py-2 px-4 font-semibold text-gray-900 dark:text-gray-100">{{ $created_by }}</td>
                    <td class="py-2 px-4 font-semibold text-gray-900 dark:text-gray-100">{{ $bonus->created_at->format('d M Y') }}</td>
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
    <div class="flex justify-center mt-6 mb-3">
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Last updated:
            <span class="text-blue-600 dark:text-blue-400">
                {{ now()->format('jS M Y') }}
            </span>
        </p>
    </div>
</section>