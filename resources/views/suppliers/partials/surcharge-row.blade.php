<div x-data="{ chargeMode: '{{ $surcharge->charge_mode }}' }"
    class="border border-gray-200 dark:border-gray-600 rounded-lg p-3 mb-2 bg-white dark:bg-gray-800 shadow-sm surcharge-row-wrapper"
    data-surcharge-id="{{ $surcharge->id }}">
    <div class="flex items-center gap-3">
        <input type="hidden" name="surcharge_id[{{ $pivotId }}][]" value="{{ $surcharge->id }}">
        <input type="text" name="surcharge_label[{{ $pivotId }}][{{ $surcharge->id }}]"
            value="{{ $surcharge->label }}"
            placeholder="Label"
            class="flex-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
        <input type="number" name="surcharge_amount[{{ $pivotId }}][{{ $surcharge->id }}]"
            value="{{ $surcharge->amount }}" min="0" step="0.001" placeholder="Amount"
            class="w-32 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md px-3 py-1.5 text-sm text-right focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition">
        <button type="button" class="text-gray-400 hover:text-red-500" onclick="removeSurchargeRow(this)" title="Remove">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="mt-2 flex items-center flex-wrap gap-x-3 gap-y-1 text-sm mt-8">
        <label class="text-gray-700 dark:text-gray-300 whitespace-nowrap">Charge Mode:</label>
        <select name="charge_mode[{{ $pivotId }}][{{ $surcharge->id }}]"
            x-model="chargeMode"
            class="min-w-[8rem] border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md px-1.5 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
            <option value="task">Task-wise</option>
            <option value="reference">Reference-wise</option>
        </select>
    </div>

    <div x-show="chargeMode === 'task'" x-cloak class="mt-4 border-t pt-3">
        <div class="flex flex-wrap items-center justify-between">
            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2 md:mb-0">Task Rules</h4>
            <div class="flex flex-wrap items-center gap-3 rounded-md px-3 py-1.5">
                @foreach(['issued','reissued','confirmed','refund','void'] as $status)
                <label class="flex items-center text-xs gap-1 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                    <input type="checkbox" value="1" name="is_{{ $status }}[{{ $pivotId }}][{{ $surcharge->id }}]"
                        {{ $surcharge->{'is_'.$status} ? 'checked' : '' }}
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    {{ ucfirst($status) }}
                </label>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between mt-4 border-t pt-3 reference-section" x-show="chargeMode === 'reference'" x-cloak>
        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 mr-3">Reference Rules</h4>
        <div class="flex items-center gap-2" id="reference-list-{{ $surcharge->id }}">
            <select name="charge_behavior[{{ $surcharge->id }}][]"
                class="min-w-[9rem] border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-md px-2 py-1 text-xs focus:ring-1 focus:ring-blue-400 focus:border-blue-400">
                <option value="single" {{ $surcharge->charge_behavior === 'single' ? 'selected' : '' }}>Charge Once</option>
                <option value="repetitive" {{ $surcharge->charge_behavior === 'repetitive' ? 'selected' : '' }}>Charge Repeatedly</option>
            </select>
        </div>
    </div>
</div>