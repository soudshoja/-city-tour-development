@props([
    'label' => '',
    'name' => '',
    'items' => [],
    'preselected' => [],
    'allLabel' => 'All',
    'placeholder' => 'Search...',
])

<div
    x-data="multiPicker({
        items: @js($items),
        preselected: @js($preselected),
        allLabel: '{{ $allLabel }}',
        placeholder: '{{ $placeholder }}'
    })"
    {{ $attributes->merge(['class' => 'relative']) }}>
    @if($label)
    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}</label>
    @endif

    <button type="button" @click="open = !open"
        class="w-full h-10 text-left border border-gray-300 dark:border-gray-600 rounded-md px-3 text-sm bg-white dark:bg-gray-900 focus:ring-2 focus:ring-blue-300 flex justify-between items-center">
        <span x-text="summary()" class="truncate text-black-700 dark:text-gray-300"></span>
        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="open" x-transition @click.outside="open = false" x-cloak
        class="absolute mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg z-50">
        <div class="p-2 border-b border-gray-200 dark:border-gray-700 flex gap-2 items-center">
            <input x-model="q" type="text" :placeholder="placeholder"
                class="w-full h-9 px-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-900" @click.stop>
            <button type="button" class="text-xs px-2 py-1.5 rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 whitespace-nowrap"
                @click="toggleAll()" x-text="allSelected ? 'Clear all' : 'Select all'"></button>
        </div>
        <div class="max-h-56 overflow-auto py-1">
            <template x-for="item in filtered().sort((a, b) => {
                const aSelected = selected.includes(a.id) ? 0 : 1;
                const bSelected = selected.includes(b.id) ? 0 : 1;
                return aSelected - bSelected;
            })" :key="'{{ $name }}-' + item.id">
                <label class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                    <input type="checkbox" class="rounded border-gray-300 text-blue-600"
                        :value="item.id" :checked="selected.includes(item.id)" @change="toggle(item.id)">
                    <span class="text-sm text-gray-700 dark:text-gray-300 truncate" x-text="item.name"></span>
                </label>
            </template>
            <div class="px-3 py-2 text-xs text-gray-500" x-show="filtered().length === 0">No items found</div>
        </div>
        <div class="px-3 py-2 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <span class="text-xs text-gray-600" x-text="selected.length + ' selected'"></span>
            <button type="button" class="text-xs text-blue-600 hover:underline font-medium" @click="open = false">Done</button>
        </div>
    </div>

    <template x-for="id in selected" :key="'{{ $name }}-hidden-' + id">
        <input type="hidden" name="{{ $name }}[]" :value="id">
    </template>
</div>