<div
    x-data="searchableDropdown({ 
        items: {{ $items ?? '[]' }}, 
        selectedId: '{{ $selectedId ?? '' }}', 
        selectedName: '{{ $selectedName ?? '' }}',
        name: '{{ $name ?? 'dropdown' }}',
        placeholder: '{{ $placeholder ?? 'Select an option' }}'
    })"
    x-init="init()"
    class="w-full">
    <div class="relative">
        @if ($label)
        <label class="block mb-1 text-sm font-medium">{{ $label }}</label>
        @endif

        <button type="button"
            @click="focusSearch($refs)"
            class="w-full border border-gray-300 dark:border-gray-600 p-2 rounded text-base text-left bg-white text-black">
            <span x-text="selectedName || placeholder"></span>
        </button>


        <input type="hidden" name="{{ $name }}" :value="selectedId">

        <div x-show="open" @click.away="open = false"
            class="absolute bg-white z-10 border w-full max-h-48 overflow-y-auto rounded shadow mt-1">
            <div class="px-2 py-2">
                <input type="text"
                    x-ref="searchInput"
                    x-model="search"
                    @input="filterOptions"
                    :placeholder="placeholder"
                    class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-black">

            </div>

            <template x-for="option in filtered.slice(0, {{ $maxResults ?? 10 }})" :key="option.id">
                <div @click="select(option)"
                    class="p-2 hover:bg-gray-100 cursor-pointer text-sm"
                    x-html="highlightMatch(option.name)">
                </div>
            </template>
        </div>
    </div>
</div>