@props([
'label' => '',
'name' => '',
'items' => '[]',
'preselected' => '[]',
'allLabel' => 'All',
'placeholder' => 'Select items'
])

<div x-data="multiPicker({
        items: @js($items),
        preselected: @js($preselected),
        allLabel: '{{ $allLabel }}',
        placeholder: '{{ $placeholder }}'
    })"
    class="relative w-full">

    @if($label)
    <label class="text-xs font-semibold text-gray-700 mb-1 block">{{ $label }}</label>
    @endif

    <button type="button" @click="open = !open"
        class="w-full text-left border rounded-md px-3 py-2 text-sm bg-white focus:ring-2 focus:ring-blue-300 flex justify-between items-center">
        <span x-text="summary()"></span>
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="open" x-transition @click.outside="open=false"
        class="absolute mt-1 w-full bg-white border rounded-md shadow-lg z-50">
        <div class="p-2 border-b flex gap-2 items-center">
            <input x-model="q" type="text" :placeholder="`Search ${placeholder.toLowerCase()}…`"
                class="w-full h-9 px-2 border rounded-md text-sm">
            <button type="button" class="text-xs px-2 py-1 rounded border" @click="toggleAll()"
                x-text="allSelected ? 'Clear all' : 'Select all'"></button>
        </div>
        <div class="max-h-56 overflow-auto py-1">
            <template x-for="i in filtered()" :key="i.id">
                <label class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" class="rounded border-gray-300"
                        :value="i.id"
                        :checked="selected.includes(i.id)"
                        @change="toggle(i.id)">
                    <span class="text-sm" x-text="i.name"></span>
                </label>
            </template>
            <div class="px-3 py-2 text-xs text-gray-500" x-show="filtered().length===0">No matches</div>
        </div>
        <div class="px-3 py-2 border-t text-xs text-gray-600 text-right">
            <button type="button" class="text-blue-600 hover:underline" @click="open=false">Done</button>
        </div>
    </div>

    <template x-for="id in selected" :key="`${name}-hid-${id}`">
        <input type="hidden" :name="`${name}[]`" :value="id">
    </template>
</div>