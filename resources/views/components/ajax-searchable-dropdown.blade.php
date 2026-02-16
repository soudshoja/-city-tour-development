@props([
'name' => 'dropdown',
'selectedId' => '',
'selectedName' => '',
'dataId' => '',
'ajaxUrl' => '',
'placeholder' => 'Select an option',
'label' => null,
'columns' => [],
'displayColumn' => 'name',
'mode' => 'dropdown',
])

@php
    $normalizedColumns = collect($columns)->map(function ($col) {
        if (is_string($col)) {
            return ['key' => $col, 'label' => ucfirst(str_replace('_', ' ', $col))];
        }
        return $col;
    })->values()->toArray();
@endphp

@pushOnce('styles')
@vite(['resources/css/component/ajax-searchable.css'])
@endPushOnce

<div
    x-data="ajaxSearchableDropdown({
        selectedId: '{{ $selectedId ?? '' }}',
        selectedName: '{{ $selectedName ?? '' }}',
        name: '{{ $name ?? 'dropdown' }}',
        placeholder: '{{ $placeholder ?? 'Select an option' }}',
        dataId: '{{ $dataId }}',
        ajaxUrl: '{{ $ajaxUrl }}',
        columns: {{ Js::from($normalizedColumns) }},
        displayColumn: '{{ $displayColumn }}',
        mode: '{{ $mode }}',
    })"
    class="ajax-searchable">
    <div class="ajax-searchable-wrapper">
        @if ($label ?? false)
        <label class="ajax-searchable-label">{{ $label }}</label>
        @endif

        <button type="button"
        @click="toggleOpen()"
        class="ajax-searchable-trigger">
            <span class="ajax-searchable-trigger-text" :class="selectedName ? 'text-gray-900' : 'text-gray-400'" x-text="selectedName || placeholder"></span>
        </button>

        <input type="hidden" :name="name" :value="selectedId">

        <template x-if="mode === 'dropdown'">
            <div x-cloak x-show="open" @click.away="open = false"
                class="ajax-searchable-dropdown-panel">
                <div class="ajax-searchable-search-wrapper">
                    <input type="text"
                        x-ref="searchInput"
                        x-model="search"
                        @input="debouncedSearch"
                        placeholder="Search..."
                        class="ajax-searchable-search-input">
                </div>

                <div x-show="loading" class="ajax-searchable-loading">
                    Loading...
                </div>

                <template x-if="!loading">
                    <div>
                        <div x-show="filtered.length === 0" class="ajax-searchable-empty">
                            No results found
                        </div>

                        <template x-for="(option, index) in filtered" :key="option.id + '-' + index">
                            <div @click="select(option)"
                                class="ajax-searchable-option"
                                x-html="renderOption(option)">
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="mode === 'modal'">
            <div x-cloak x-show="open"
                class="ajax-searchable-modal-backdrop"
                @click.self="open = false"
                @keydown.escape.window="open = false">
                <div class="ajax-searchable-modal">

                    <div class="ajax-searchable-modal-header">
                        <h3 class="ajax-searchable-modal-title" x-text="placeholder"></h3>
                        <button @click="open = false" type="button" class="ajax-searchable-modal-close">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="ajax-searchable-modal-search">
                        <input type="text"
                            x-ref="modalSearchInput"
                            x-model="search"
                            @input="debouncedSearch"
                            placeholder="Search..."
                            class="ajax-searchable-modal-search-input">
                    </div>

                    <div class="ajax-searchable-modal-results">
                        <div x-show="loading" class="ajax-searchable-modal-loading">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span>Loading...</span>
                        </div>

                        <template x-if="!loading">
                            <div>
                                <div x-show="filtered.length === 0" class="ajax-searchable-modal-empty">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <p>No results found</p>
                                </div>

                                <template x-for="(option, index) in filtered" :key="option.id + '-' + index">
                                    <div @click="select(option)"
                                        class="ajax-searchable-modal-option"
                                        x-html="renderOption(option)">
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
