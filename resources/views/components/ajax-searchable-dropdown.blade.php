@props([
'name' => 'dropdown',
'selectedId' => '',
'selectedName' => '',
'taskId' => '',
'ajaxUrl' => '',
'placeholder' => 'Select an option',
'label' => null,
'responseKey' => 'tasks',
])
<div
    x-data="ajaxSearchableDropdown({
        selectedId: '{{ $selectedId ?? '' }}',
        selectedName: '{{ $selectedName ?? '' }}',
        name: '{{ $name ?? 'dropdown' }}',
        placeholder: '{{ $placeholder ?? 'Select an option' }}',
        taskId: '{{ $taskId }}',
        ajaxUrl: '{{ $ajaxUrl }}',
        responseKey: '{{ $responseKey ?? 'tasks' }}',
    })"
    x-init="init()"
    class="w-full">
    <div class="relative">
        @if ($label ?? false)
        <label class="block mb-1 text-sm font-medium text-gray-700">{{ $label }}</label>
        @endif

        <button type="button"
        @click="open = !open; if(open) { $nextTick(() => focusSearch($refs)) }"
        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm text-left bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors">
            <span class="truncate block w-full" :class="selectedName ? 'text-gray-900' : 'text-gray-400'" x-text="selectedName || placeholder"></span>
        </button>

        <input type="hidden" name="{{ $name }}" :value="selectedId">

        <div x-cloak x-show="open" @click.away="open = false"
            class="absolute bg-white z-10 border border-gray-300 w-full max-h-48 overflow-y-auto rounded-lg shadow-lg mt-1">
            <div class="px-2 py-2">
                <input type="text"
                    x-ref="searchInput"
                    x-model="search"
                    @input="debouncedSearch"
                    :placeholder="placeholder"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-black focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
            </div>

            <div x-show="loading" class="px-3 py-2 text-sm text-gray-500 text-center">
                Loading...
            </div>

            <template x-if="!loading">
                <div>
                    <div x-show="filtered.length === 0" class="px-3 py-2 text-sm text-gray-500 text-center">
                        No results found
                    </div>

                    <template x-for="option in filtered" :key="option.id">
                        <div @click="select(option)"
                            class="px-3 py-2 hover:bg-gray-100 cursor-pointer text-sm"
                            x-html="highlightMatch(option.name)">
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
