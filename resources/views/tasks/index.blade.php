<x-app-layout>

    <head>
        <!-- filepath: c:\laravel\city-tour\resources\views\tasks\index.blade.php -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <meta name="csrf-token" content="{{ csrf_token() }}">
    </head>
    <style>
        #myTable>thead>tr>th:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            background-color: #f9fafb;
        }

        .dark #myTable>thead>tr>th:first-child {
            background-color: #374151;
        }

        #myTable>tbody>tr>td:first-child {
            position: -webkit-sticky;
            position: sticky;
            left: 0;
            z-index: 1;
            background-color: inherit;
            transition: background-color 0.2s;
        }

        #myTable>thead>tr>th:first-child,
        #myTable>tbody>tr>td:first-child {
            box-shadow: 5px 0 5px -5px rgba(0, 0, 0, 0.1);
        }

        .dark #myTable>thead>tr>th:first-child,
        .dark #myTable>tbody>tr>td:first-child {
            box-shadow: 5px 0 5px -5px rgba(255, 255, 255, 0.1);
        }

        .no-client {
            color: red;
            /* position: relative; */
        }

        .no-client:hover::after {
            content: 'This Task Not Link To Client CRM';
            position: absolute;
            background-color: red;
            color: white;
            padding: 5px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            right: 0.8rem;
            top: -2.2rem;
            z-index: 5;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        /* Custom slider */
        .slider {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
            background-color: #ccc;
            /* Default background */
            border-radius: 20px;
            transition: 0.3s;
            cursor: pointer;
        }

        .slider::before {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            border-radius: 50%;
            transition: 0.3s;
        }

        /* When the checkbox is checked */
        input:checked+.slider {
            background-color: #ffb958;
            /* Custom enabled color */
        }


        input:checked+.slider::before {
            transform: translateX(20px);
            background-color: #fff;
            /* Ensure contrast */
        }

        /* Rounded slider style */
        .slider.round {
            border-radius: 20px;
        }

        .slider.round::before {
            border-radius: 50%;
        }

        .filter-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .filter-modal.active {
            display: flex;
        }

        .filter-modal-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .filter-row {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 16px;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background-color: #f9fafb;
        }

        .filter-row select,
        .filter-row input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-row select {
            flex: 1;
            min-width: 150px;
        }

        .filter-row input[type="text"],
        input[type="number"],
        input[type="date"] {
            flex: 1;
            min-width: 150px;
        }

        .remove-filter-btn {
            background-color: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: background-color 0.2s;
        }

        .remove-filter-btn:hover {
            background-color: #dc2626;
        }

        .add-filter-btn {
            background-color: #10b981;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s;
        }

        .add-filter-btn:hover {
            background-color: #059669;
        }

        .active-filters {
            margin-top: 16px;
            padding: 16px;
            background-color: #f3f4f6;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .active-filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #3b82f6;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin: 4px 8px 4px 0;
        }

        .active-filter-tag .remove-tag {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 16px;
            padding: 0;
            margin-left: 4px;
        }

        .active-filter-tag .remove-tag:hover {
            color: #fecaca;
        }

        .filter-modal-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .filter-modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }

        .close-modal-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            padding: 4px;
        }

        .close-modal-btn:hover {
            color: #374151;
        }

        .filter-modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .apply-filters-btn {
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .apply-filters-btn:hover {
            background-color: #2563eb;
        }

        .clear-all-filters-btn {
            background-color: #6b7280;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .clear-all-filters-btn:hover {
            background-color: #4b5563;
        }

        .column-hidden {
            display: none !important;
        }

        .task-row {
            cursor: pointer;
            transition: background-color 0.2s;
            background-color: #ffffff;
        }

        .dark .task-row {
            background-color: #1f2937;
        }

        .task-row.not-selectable {
            cursor: default;
        }

        .task-row.selected {
            background-color: #dbeafe;
        }

        .dark .task-row.selected {
            background-color: #1e40af;
        }

        .task-row.not-selectable:hover {
            background-color: #f3f4f6;
        }

        .dark .task-row.not-selectable:hover {
            background-color: #2d3748;
        }

        .peer:checked+span {
            background-color: #ffb958;
        }

        .peer:checked+span>span {
            transform: translateX(20px);
        }

        @media (max-width: 640px) {
            .filter-modal-content {
                width: 95vw;
                max-width: none;
                padding: 16px;
            }

            .filter-modal-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .filter-row {
                position: relative;
                flex-direction: column;
                align-items: stretch;
            }

            .filter-row input,
            .value-input {
                width: 90% !important;
            }

            .column-select {
                width: 100% !important;
            }

            .filter-row .remove-filter-btn {
                position: absolute;
                top: 8px;
                right: 8px;
                align-self: unset;
                margin-top: 60px;
            }

            .filter-modal-footer {
                flex-direction: row !important;
                flex-wrap: wrap;
                gap: 4px;
            }

            .filter-modal-footer .flex {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 4px;
            }

            .add-filter-btn,
            .clear-all-filters-btn,
            .apply-filters-btn {
                flex: 1 1 auto;
                width: auto;
                min-width: 100px;
            }
        }

        @media (hover: none) {
            .group:hover .group-hover\:block {
                display: none;
            }

            .group:focus .group-focus\:block {
                display: block;
            }
        }
    </style>

    <div class="flex justify-between items-center gap-5 my-3 ">
        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Tasks List</h2>
            <div data-tooltip="number of tasks"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor dark:!bg-gray-700 dark:!hover:bg-gray-600 rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $taskCount }}</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div data-tooltip-left="Reload"
                class="rotate refresh-icon w-10 h-10 relative flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                    <path fill="currentColor"
                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor"
                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                        opacity=".5" />
                </svg>
            </div>
            <div x-data="{ addTaskModal: false, manualFormWide: false }" class="flex items-center gap-5">
                <div @click="addTaskModal = true; manualFormWide = false"
                    class="p-2 text-center bg-white rounded-full shadow group hover:bg-black dark:hover:bg-gray-600 dark:bg-gray-700 cursor-pointer"
                    data-tooltip-left="Add Task">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                        class="stroke-black dark:stroke-gray-300 group-hover:stroke-white group-focus:stroke-white">
                        <path d="M15 12L12 12M12 12L9 12M12 12L12 9M12 12L12 15" stroke="" stroke-width="1.5"
                            stroke-linecap="round" />
                        <path
                            d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7"
                            stroke="" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </div>
                <div x-cloak x-show="addTaskModal" x-init="$watch('addTaskModal', value => {
                        if (!value) {
                            manualFormWide = false;
                            $nextTick(() => {
                                if (typeof window.__resetTaskForm === 'function') {
                                    window.__resetTaskForm();
                                }

                                const formTaskContainer = document.getElementById('form-task-container');
                                if (formTaskContainer) {
                                    formTaskContainer.innerHTML = '';
                                }
                                window.dispatchEvent(new CustomEvent('reset-dropdowns'));
                            });
                        }
                    })"
                    class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-20">
                    <div @click.away="addTaskModal = false; manualFormWide = false" class="bg-white rounded shadow min-w-96"
                        @modal:wide.window="manualFormWide = true" @modal:normal.window="manualFormWide = false"
                        :class="{ 'w-full max-w-96': !manualFormWide }">
                        <div class="p-4 flex justify-between items-center">
                            <span class="text-lg font-semibold">Add Task For Specific Supplier</span>

                            <button type="button" @click="addTaskModal = false"
                                class="text-gray-500 hover:text-red-600 p-1 rounded focus:outline-none">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                    stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <hr>
                        <form id="agent-supplier-task" action="{{ route('tasks.agent.upload') }}"
                            class="p-4 flex flex-col" method="POST" enctype="multipart/form-data">
                                @csrf

                            <div class="mb-3 z-10">
                                <x-searchable-dropdown name="supplier_id" :items="$suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name])" placeholder="Select Supplier"
                                    label="Select a Supplier" />
                            </div>
                            <div class="flex-1 min-h-0" :class="manualFormWide ? 'overflow-y-auto max-h-[calc(90vh-224px)]' : 'overflow-visible max-h-none'">
                                <!-- Hidden native select (logic only) -->
                                <select id="select-supplier-task" class="hidden">
                                    @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" data-supplier='{{ json_encode($supplier) }}'>
                                        {{ $supplier->name }}
                                    </option>
                                    @endforeach
                                </select>
                                <div id="template-hotel-dropdown" class="hidden min-w-0">
                                    <script type="application/json" id="hotel-items-json">
                                        {!! $hotels->map(fn($h) => ['id' => $h->id, 'name' => $h->name])->values()->toJson() !!}
                                    </script>
                                    <div class="relative sd" data-sd="hotel">
                                        <button type="button" class="sd-btn w-full border border-gray-300 p-2 rounded text-left bg-white">
                                            <span class="sd-text text-gray-400">Select Hotel</span>
                                        </button>
                                        <div class="sd-menu absolute z-10 mt-1 w-full max-h-60 overflow-auto bg-white border rounded shadow hidden">
                                            <div class="p-2">
                                                <input class="sd-search w-full border rounded px-2 py-1" placeholder="Search hotel">
                                            </div>
                                            <div class="sd-list py-1"></div>
                                        </div>
                                        <input type="hidden" id="selected-hotel" name="hotel_id">
                                    </div>
                                </div>
                                <div id="template-client-dropdown" class="hidden min-w-0">
                                    <script type="application/json" id="client-items-json">
                                        {!! $fullClients->map(fn($c) => ['id' => $c->id, 'name' => $c->full_name])->values()->toJson() !!}
                                    </script>
                                    <div class="relative sd" data-sd="client">
                                        <button type="button" class="sd-btn w-full border border-gray-300 p-2 rounded text-left bg-white">
                                            <span class="sd-text text-gray-400">Select Client</span>
                                        </button>
                                        <div class="sd-menu absolute z-10 mt-1 w-full max-h-60 overflow-auto bg-white border rounded shadow hidden">
                                            <div class="p-2">
                                                <input class="sd-search w-full border rounded px-2 py-1" placeholder="Search client">
                                            </div>
                                            <div class="sd-list py-1"></div>
                                        </div>
                                        <input type="hidden" id="selected-client" name="client_id">
                                    </div>
                                </div>
                                <div id="template-currency-dropdown" class="hidden min-w-0">
                                    <script type="application/json" id="currency-items-json">
                                        {!! $currencies->map(fn($c) => ['id' => $c->iso_code, 'name' => $c->name.' ('.$c->iso_code.')'])->values()->toJson() !!}
                                    </script>
                                    <div class="relative sd" data-sd="currency">
                                        <button type="button" class="sd-btn w-full border border-gray-300 p-2 rounded text-left bg-white">
                                            <span class="sd-text text-gray-400">Select Currency</span>
                                        </button>
                                        <div class="sd-menu absolute z-15 mt-1 w-full max-h-60 overflow-auto bg-white border rounded shadow hidden">
                                            <div class="p-2">
                                                <input class="sd-search w-full border rounded px-2 py-1" placeholder="Search currency">
                                            </div>
                                            <div class="sd-list py-1"></div>
                                        </div>
                                        <input type="hidden" id="selected-currency" value="KWD">
                                    </div>
                                </div>
                                <div id="form-task-container" class="mb-3"></div>
                            </div>

                            @unlessrole('agent')
                            <!-- <div class="mb-4">
                                <x-searchable-dropdown name="agent_id" :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])" placeholder="Select Agent"
                                    label="Select an Agent" />
                            </div> -->
                            @else
                            <input type="hidden" name="agent_id" value="{{ Auth()->user()->agent->id }}">
                            @endunlessrole
                        </form>
                        <hr class="shrink-0">
                        <div class="p-4 flex justify-between items-center bg-white z-5">
                            <button @click="addTaskModal = false; manualFormWide = false"
                                class="rounded-full shadow-sm px-4 py-2 text-red-500 border border-white-100 bg-white hover:bg-gray-100 transition">
                                Cancel
                            </button>

                            <x-primary-button type="submit" form="agent-supplier-task"
                                class="rounded-full shadow-md px-6 py-2 text-white bg-black hover:bg-gray-800 transition">
                                Submit
                            </x-primary-button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tableCon">
        <div class="content-70">
            <div class="panel oxShadow rounded-lg">
                <div class="customResponsiveClass flex flex-col md:flex-row justify-between p-2 gap-3">
                    <x-search
                        :action="route('tasks.index')"
                        searchParam="q"
                        placeholder="Quick search for tasks" />
                    <!-- Place this beside the Filters button -->
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" id="showVoidCheckbox"
                            class="sr-only peer"
                            {{ request('show_void') == '1' ? 'checked' : '' }}
                            onchange="window.location='{{ request()->fullUrlWithQuery(['show_void' => '1']) }}'; if(!this.checked) window.location='{{ request()->fullUrlWithQuery(['show_void' => null]) }}';">
                        <span class="w-10 h-5 bg-gray-300 rounded-full relative transition peer-checked:bg-orange-400">
                            <span class="absolute left-1 top-1 w-3 h-3 bg-white rounded-full transition peer-checked:translate-x-5"></span>
                        </span>
                        <span class="ml-2 text-xs md:text-sm font-medium text-gray-700 dark:text-black">Show Void Tasks</span>
                    </label>
                    <button type="button" id="toggleFilters"
                        class="flex px-3 py-2 gap-2 w-full h-10 md:w-auto justify-center city-light-yellow rounded-full shadow-sm items-center text-xs md:text-sm">
                        <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 32 32">
                            <path fill="#333333"
                                d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3-3-3-3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3-3-3-3s-3-1.3-3-3" />
                        </svg>
                        <span class="text-xs md:text-sm dark:text-black">Filters</span>
                    </button>

                    <!-- Modal for Advanced Filters -->
                    <div id="filterModal" class="filter-modal">
                        <div class="filter-modal-content">
                            <div class="filter-modal-header">
                                <div class="relative w-full">
                                    <h3>Advanced Filters</h3>
                                </div>
                                <div class="flex customCenter justify-end">
                                    <button id="closeFilterModal" class="close-modal-btn">&times;</button>
                                </div>
                            </div>
                            <div id="filterContainer">
                                <!-- Filter rows will be dynamically added here -->
                            </div>
                            <div class="filter-modal-footer">
                                <div class="flex gap-3">
                                    <button id="addFilterRow" class="add-filter-btn">Add Filter</button>
                                </div>
                                <div class="flex gap-3">
                                    <button id="applyFilters" class="apply-filters-btn">Apply Filters</button>
                                </div>
                                <div class="flex gap-3">
                                    <button id="clearAllActiveFilters2"
                                        class="clear-all-filters-btn">
                                        Clear All
                                    </button>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="relative">
                        <button type="button" id="customizeColumnsBtn"
                            class="flex px-3 py-2 w-full h-10 md:w-auto DarkBGcolor dark:!bg-blue-700 dark:!hover:bg-blue-600 rounded-full shadow-sm items-center text-xs text-white font-semibold md:text-sm">
                            <span>Customize columns</span>
                        </button>
                        <div id="columnDropdownContent"
                            class="hidden absolute z-50 mt-2 right-0 w-60 max-h-80 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg p-3 space-y-2">
                            <div class="flex justify-between items-center mb-1">
                                <h4 class="font-semibold text-sm text-gray-700">Visible Columns</h4>
                                <button type="button" id="clearAllColumns" class="text-xs text-red-600 hover:underline focus:outline-none">
                                    Clear All
                                </button>
                            </div>
                            <hr class="border-gray-300 mb-2" />
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-reference" class="column-checkbox accent-blue-600 rounded-md w-4 h-4" checked>
                                    <label for="col-reference" class="text-sm text-gray-700">Reference</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-bill-to" class="column-checkbox accent-blue-600 rounded-md w-4 h-4" checked>
                                    <label for="col-bill-to" class="text-sm text-gray-700">Bill To</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-passenger-name" class="column-checkbox accent-blue-600 rounded-md w-4 h-4" checked>
                                    <label for="col-passenger-name" class="text-sm text-gray-700">Passenger Name</label>
                                </div>
                                @if (Auth()->user()->role_id != \App\Models\Role::AGENT)
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-agent-name" class="column-checkbox accent-blue-600 rounded-md w-4 h-4" checked>
                                    <label for="col-agent-name" class="text-sm text-gray-700">Agent Name</label>
                                </div>
                                @endif
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-price" class="column-checkbox accent-blue-600 rounded-md w-4 h-4" checked>
                                    <label for="col-price" class="text-sm text-gray-700">Price</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-status" class="column-checkbox accent-blue-600 rounded-md w-4 h-4" checked>
                                    <label for="col-status" class="text-sm text-gray-700">Status</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-supplier" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-supplier" class="text-sm text-gray-700">Supplier</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-supplier-pay-date" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-supplier-pay-date" class="text-sm text-gray-700">Issued Date</label>
                                </div>
                                @if(Auth()->user()->role_id == \App\Models\Role::COMPANY)
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-cancellation-deadline" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-cancellation-deadline" class="text-sm text-gray-700">Cancellation Deadline</label>
                                </div>
                                @endif
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-created-at" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-created-at" class="text-sm text-gray-700">Created Date</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-info" class="column-checkbox accent-blue-600 rounded-md w-4 h-4" checked>
                                    <label for="col-info" class="text-sm text-gray-700">Info</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-type" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-type" class="text-sm text-gray-700">Type</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-gds-reference" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-gds-reference" class="text-sm text-gray-700">GDS Reference</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-amadeus-reference" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-amadeus-reference" class="text-sm text-gray-700">Amadeus Reference</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-created-by" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-created-by" class="text-sm text-gray-700">Created By</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-issued-by" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-issued-by" class="text-sm text-gray-700">Issued By</label>
                                </div>
                                @if (Auth()->user()->role_id == \App\Models\Role::COMPANY)
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-branch-name" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-branch-name" class="text-sm text-gray-700">Branch Name</label>
                                </div>
                                @endif
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-invoice" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-invoice" class="text-sm text-gray-700">Invoice</label>
                                </div>
                                @if (Auth()->user()->role_id == \App\Models\Role::ADMIN || Auth()->user()->role_id == \App\Models\Role::COMPANY)
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-file-name" class="column-checkbox accent-blue-600 rounded-md w-4 h-4">
                                    <label for="col-file-name" class="text-sm text-gray-700">File Name</label>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div id="activeFiltersContainer" class="active-filters">
                    <div class="bg-white shadow-lg rounded-2xl border border-gray-200 p-4 transition-all duration-300">
                        <!-- Header -->
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-base font-bold text-gray-800 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18m-9 5h9" />
                                </svg>
                                Active Filters
                            </h4>

                            <div class="flex gap-3">
                                <button id="editActiveFilters"
                                    class="text-sm font-medium text-green-600 hover:bg-green-100 px-3 py-1 rounded-lg transition-all">
                                    ✏️ Modify
                                </button>
                                <button id="clearAllActiveFilters"
                                    class="text-sm font-medium text-red-600 hover:bg-red-100 px-3 py-1 rounded-lg transition-all">
                                    🗑️ Clear All
                                </button>
                            </div>
                        </div>

                        <!-- Active Filter Tags -->
                        <div id="activeFiltersList" class="flex flex-wrap gap-2">

                        </div>
                    </div>
                </div>

                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <div class="w-full flex m-2 ">
                        @php
                        $invoiced = request()->has('invoiced') ? request('invoiced') : '0';
                        // Set viewType to 'invoice' ONLY for Un Invoiced tab, otherwise keep the current value
                        $viewType = $invoiced == '0'
                        ? request()->input('view_type', 'invoice')
                        : request()->input('view_type', '');
                        @endphp
                        <form method="GET" action="{{ route('tasks.index') }}" class="flex gap-0 w-full">
                            @foreach(request()->except(['invoiced', 'page', 'view_type']) as $key => $value)
                            @if(is_array($value))
                            @foreach($value as $v)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                            @endforeach
                            @else
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                            @endforeach
                            <input type="hidden" name="view_type" value="{{ $viewType }}">
                            <button type="submit" name="invoiced" value="0"
                                class="w-full text-center py-1 rounded-l-lg font-bold text-lg transition
                                    {{ $invoiced == '0' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-blue-100' }}">
                                Un Invoiced
                            </button>
                            <button type="submit" name="invoiced" value="1"
                                class="w-full text-center py-1 rounded-r-lg font-bold text-lg transition
                                    {{ $invoiced == '1' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-blue-100' }}">
                                Invoiced
                            </button>
                        </form>
                    </div>
                    <div x-data="{ shown: 15 }">
                        <div class="dataTable-container h-max">
                            <div class="table-container">
                                <div x-data="{
                                    showUploadForm: false,
                                    showManualForm: false,
                                    showFileInput: false,
                                    modalTaskId: null,
                                    modalClientName: '',
                                    modalPassengerName: '',
                                    modalAgentName: '',
                                    modalAgentId: '',
                                    modalBranchName: '',
                                    chooseMethodModal: false,
                                    showUploadForm: false,
                                    showManualForm: false,
                                    showBulkEditModal: false,
                                    selectedTasks: [],
                                    originalInvoiceRoute: '{{ route('invoices.create') }}',
                                    openManualForm(taskId, clientName, passengerName, agentName, agentId, branchName) {
                                        this.modalTaskId = taskId;
                                        this.modalClientName = clientName;
                                        this.modalPassengerName = passengerName;
                                        this.modalAgentName = agentName;
                                        this.modalAgentId = agentId;
                                        this.modalBranchName = branchName;
                                        this.chooseMethodModal = false;
                                        this.showManualForm = true;
                                    },
                                    closeAll() {
                                        this.chooseMethodModal = false;
                                        this.showUploadForm = false;
                                        this.showManualForm = false;
                                        window.dispatchEvent(new CustomEvent('reset-dropdowns'));
                                    },
                                    submitBulkEdit() {
                                        const form = document.getElementById('bulk-edit-form');
                                        const formData = new FormData(form);
                                        formData.append('task_ids', JSON.stringify(this.selectedTasks));

                                        fetch('{{ route('tasks.bulkUpdate') }}', {
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                'Accept': 'application/json'
                                            },
                                            body: formData
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                alert('Tasks updated successfully!');
                                                this.showBulkEditModal = false;
                                                window.location.reload();
                                            } else {
                                                alert(data.message || 'Failed to update tasks.');
                                            }
                                        })
                                        .catch(error => {
                                            alert('Error updating tasks.');
                                            console.error(error);
                                        });
                                    },
                                    toggleTaskSelection(taskId) {
                                        const taskRow = document.querySelector(`[data-task-id='${taskId}']`);
                                        const taskStatus = taskRow?.getAttribute('data-status');
                                        const isRefund = taskStatus === 'refund';
                                        
                                        const index = this.selectedTasks.indexOf(taskId);
                                        
                                        if (index > -1) {
                                            // Remove if already selected
                                            this.selectedTasks.splice(index, 1);
                                        } else {
                                            // Add to selection
                                            if (isRefund) {
                                                // If selecting a refund task, clear all non-refund selections
                                                this.selectedTasks = this.selectedTasks.filter(id => {
                                                    const row = document.querySelector(`[data-task-id='${id}']`);
                                                    return row?.getAttribute('data-status') === 'refund';
                                                });
                                                this.selectedTasks.push(taskId);
                                            } else {
                                                // If selecting a non-refund task, clear all refund selections
                                                this.selectedTasks = this.selectedTasks.filter(id => {
                                                    const row = document.querySelector(`[data-task-id='${id}']`);
                                                    return row?.getAttribute('data-status') !== 'refund';
                                                });
                                                this.selectedTasks.push(taskId);
                                            }
                                        }
                                        
                                        window.selectedTasksGlobal = [...this.selectedTasks];
                                        this.updateFloatingActions();
                                    },
                                    updateFloatingActions() {
                                        const floating = document.getElementById('floatingActions');
                                        const createBtn = document.getElementById('createInvoiceBtn');
                                        const createBtnText = document.getElementById('createInvoiceBtnText');

                                        const resetButton = () => {
                                            createBtn?.classList.add('hidden');
                                            createBtn?.setAttribute('disabled', 'disabled');
                                            createBtnText.innerText = 'Create Invoice';
                                            createBtn?.classList.remove('bg-red-500','hover:bg-red-600','text-white');
                                            createBtn?.classList.add('btn-success','hover:bg-green-600');
                                            createBtn?.setAttribute('data-route', this.originalInvoiceRoute);
                                            createBtn?.removeAttribute('data-task-status'); 
                                        };

                                        if (this.selectedTasks.length === 0) {
                                            floating?.classList.add('hidden');
                                            resetButton();
                                            return;
                                        }

                                        const selected = this.selectedTasks.map(id => {
                                            const row = document.querySelector(`[data-task-id='${id}']`);
                                            return {
                                                id,
                                                agent_id: row?.getAttribute('data-agent-id'),
                                                enabled: row?.getAttribute('data-enabled') === 'true',
                                                status: row?.getAttribute('data-status'),
                                                refundDetail: row?.getAttribute('data-refund-detail') === 'true',
                                                is_complete: row?.getAttribute('data-is-complete') === 'true',
                                                invoiceDetail: row?.getAttribute('data-invoice-detail') === 'true',
                                            };
                                        });

                                        const anyRefund = selected.some(t => t.status === 'refund');
                                        const canProceedRefund =
                                            this.selectedTasks.length === 1 &&
                                            anyRefund &&
                                            !!selected[0].agent_id &&
                                            selected[0].enabled &&
                                            !selected[0].refundDetail &&
                                            selected[0].is_complete;

                                        const allCanCreateInvoice = !anyRefund && selected.every(t => this.canCreateInvoice(t));
                                        const showBulk = this.selectedTasks.length > 1;
                                        const shouldShow = canProceedRefund || allCanCreateInvoice || showBulk;

                                        if (!shouldShow) {
                                            floating?.classList.add('hidden');
                                            resetButton();
                                            return;
                                        }

                                        floating?.classList.remove('hidden');

                                        if (canProceedRefund) {
                                            createBtn?.classList.remove('hidden');
                                            createBtn?.removeAttribute('disabled');
                                            createBtnText.innerText = 'Proceed Refund';
                                            createBtn?.classList.remove('btn-success','hover:bg-green-600');
                                            createBtn?.classList.add('bg-red-500','hover:bg-red-600','text-white');
                                            createBtn?.setAttribute('data-route', `/refunds/${this.selectedTasks[0]}/create`);
                                            createBtn?.setAttribute('data-task-status', 'refund');
                                        } else if (allCanCreateInvoice) {
                                            createBtn?.classList.remove('hidden');
                                            createBtn?.removeAttribute('disabled');
                                            createBtnText.innerText = 'Create Invoice';
                                            createBtn?.classList.remove('bg-red-500','hover:bg-red-600','text-white');
                                            createBtn?.classList.add('btn-success','hover:bg-green-600');
                                            createBtn?.setAttribute('data-route', this.originalInvoiceRoute);
                                            createBtn?.setAttribute('data-task-status', 'invoice');
                                        } else {
                                            resetButton();
                                        }
                                    },
                                    canCreateInvoice(task) {
                                        if (!task.agent_id) return false;
                                        return task.enabled && (
                                            (task.status === 'refund' && !task.refundDetail && task.is_complete) ||
                                            (task.status !== 'refund' && !task.invoiceDetail)
                                        );
                                    },
                                    clearSelectedTasks() {
                                        this.selectedTasks = [];
                                        window.selectedTasksGlobal = [];
                                        this.updateFloatingActions();
                                    }
                                }" x-init="window.selectedTasksGlobal = selectedTasks" x-cloak>


                                    <table id="myTable" class="whitespace-nowrap dataTable-table">
                                        <thead>
                                            <tr>
                                                <th data-column="actions">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Actions</span>
                                                </th>
                                                <th data-column="reference">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Reference</span>
                                                </th>
                                                <th data-column="bill-to">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Bill To</span>
                                                </th>
                                                <th data-column="passenger-name">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Passenger Name</span>
                                                </th>
                                                @if (Auth()->user()->role_id != \App\Models\Role::AGENT)
                                                <th data-column="agent-name">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Agent Name</span>
                                                </th>
                                                @endif
                                                @can('viewPrice', 'App\Models\Task')
                                                <th data-column="price">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Price</span>
                                                </th>
                                                @endcan
                                                <th data-column="status">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Status</span>
                                                </th>
                                                <th data-column="supplier">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Supplier</span>
                                                </th>
                                                <th data-column="supplier-pay-date" class="column-hidden">
                                                    <a href="{{ request()->fullUrlWithQuery([
                                                                'sortBy' => 'supplier_pay_date',
                                                                'sortOrder' => (request('sortBy') === 'supplier_pay_date' && request('sortOrder') === 'asc') ? 'desc' : 'asc'
                                                            ]) }}"
                                                        class="inline-flex w-full items-center justify-center gap-2 text-md font-bold text-gray-900 dark:text-gray-300 hover:text-gray-700 dark:hover:text-gray-100 cursor-pointer transition-all duration-200">
                                                        Issued Date
                                                        @if(request('sortBy') !== 'supplier_pay_date')
                                                        <svg class="w-4 h-4 opacity-70 hover:opacity-100 transform hover:scale-110 transition-all duration-200"
                                                            fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                            <path stroke-width="2" d="M6 9l6-6 6 6M6 15l6 6 6-6" />
                                                        </svg>
                                                        @else
                                                        <svg class="w-3 h-3 transform hover:scale-110 transition-all duration-200"
                                                            fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                                                            @if(request('sortOrder', 'desc') === 'asc')
                                                            <path stroke-width="3" d="m26.71 10.29-10-10a1 1 0 0 0-1.41 0l-10 10 1.41 1.41L15 3.41V32h2V3.41l8.29 8.29z" />
                                                            @else
                                                            <path stroke-width="3" d="M26.29 20.29 18 28.59V0h-2v28.59l-8.29-8.3-1.42 1.42 10 10a1 1 0 0 0 1.41 0l10-10z" />
                                                            @endif
                                                        </svg>
                                                        @endif
                                                    </a>
                                                </th>
                                                <th data-column="created-at" class="column-hidden">
                                                    <a href="{{ request()->fullUrlWithQuery([
                                                                    'sortBy' => 'created_at',
                                                                    'sortOrder' => (request('sortBy') === 'created_at' && request('sortOrder') === 'asc') ? 'desc' : 'asc'
                                                                ]) }}"
                                                        class="flex items-center gap-2 text-left text-md font-bold text-gray-900 dark:text-gray-300 hover:text-gray-700 dark:hover:text-gray-100 cursor-pointer transition-all duration-200">
                                                        Created Date
                                                        @if(request('sortBy') !== 'created_at')
                                                        <svg class="w-4 h-4 opacity-70 hover:opacity-100 transform hover:scale-110 transition-all duration-200"
                                                            fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                                            <path stroke-width="2" d="M6 9l6-6 6 6M6 15l6 6 6-6" />
                                                        </svg>
                                                        @else
                                                        <svg class="w-3 h-3 transform hover:scale-110 transition-all duration-200"
                                                            fill="none" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                                                            @if(request('sortOrder') === 'asc')
                                                            <path stroke-width="3" d="m26.71 10.29-10-10a1 1 0 0 0-1.41 0l-10 10 1.41 1.41L15 3.41V32h2V3.41l8.29 8.29z" />
                                                            @else
                                                            <path stroke-width="3" d="M26.29 20.29 18 28.59V0h-2v28.59l-8.29-8.3-1.42 1.42 10 10a1 1 0 0 0 1.41 0l10-10z" />
                                                            @endif
                                                        </svg>
                                                        @endif
                                                    </a>
                                                </th>
                                                @if(Auth()->user()->role_id == \App\Models\Role::COMPANY)
                                                <th data-column="cancellation-deadline" class="column-hidden">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Cancellation Deadline</span>
                                                </th>
                                                @endif
                                                <th data-column="info">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Info</span>
                                                </th>
                                                <th data-column="type" class="column-hidden">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Type</span>
                                                </th>
                                                <th data-column="gds-reference" class="column-hidden">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">GDS Reference</span>
                                                </th>
                                                <th data-column="amadeus-reference" class="column-hidden">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Amadeus Reference</span>
                                                </th>
                                                <th data-column="created-by" class="column-hidden">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Created By</span>
                                                </th>
                                                <th data-column="issued-by" class="column-hidden">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Issued By</span>
                                                </th>
                                                @if (Auth()->user()->role_id == \App\Models\Role::COMPANY)
                                                <th data-column="branch-name" class="column-hidden">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Branch Name</span>
                                                </th>
                                                @endif
                                                <th data-column="invoice" class="column-hidden">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">Invoice</span>
                                                </th>
                                                <th data-column="file-name" class="column-hidden">
                                                    <span class="text-left text-md font-bold text-gray-900 dark:text-gray-300">File Name</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody id="myTableBody">
                                            @if ($tasks->isEmpty())
                                            <tr>
                                                <td colspan="17" class="text-center p-5 text-gray-500 dark:text-gray-300">No tasks found</td>
                                            </tr>
                                            @else
                                            @foreach ($tasks as $key => $task)
                                            @php
                                            $isSelectable = $task->status !== 'refund' ? !$task->invoiceDetail && $task->enabled && $task->agent_id
                                            : !$task->refundDetail && $task->is_complete && $task->agent_id;
                                            @endphp
                                            <tr class="taskRow task-row 
                                                {{ ($task->invoiceDetail || $task->refundDetail) ? '!cursor-not-allowed' : 'cursor-pointer' }}"
                                                @click="{{ (!$task->invoiceDetail && !$task->refundDetail) ? "toggleTaskSelection($task->id)" : '' }}"
                                                x-show="{{ $key }} < shown" x-cloak
                                                :class="selectedTasks.includes({{ $task->id }}) ? 'selected' : ''"
                                                data-agent-id="{{ $task->agent_id }}"
                                                data-status="{{ $task->status }}"
                                                data-task-id="{{ $task->id }}"
                                                data-enabled="{{ $task->enabled ? 'true' : 'false' }}"
                                                data-invoice-detail="{{ $task->invoiceDetail ? 'true' : 'false' }}"
                                                data-refund-detail="{{ $task->refundDetail ? 'true' : 'false' }}"
                                                data-is-complete="{{ $task->is_complete ? 'true' : 'false' }}">

                                                <td data-column="actions" class="p-3 text-sm">
                                                    <div class="flex items-center justify-center h-full min-h-[40px]">
                                                        @if (!$isSelectable)
                                                        @php
                                                        $reasons = [];
                                                        if (!$task->enabled) $reasons[] = 'Task is currently disabled';
                                                        if (!$task->agent_id) $reasons[] = 'Agent not selected';
                                                        if ($task->invoiceDetail) $reasons[] = 'Invoice already created';
                                                        if ($task->status === 'refund' && $task->refundDetail) $reasons[] = 'Refund already processed';
                                                        if ($task->status === 'refund' && !$task->is_complete) $reasons[] = 'Refund not complete';
                                                        if (!in_array($task->status, ['issued', 'confirmed']) && !$task->original_task_id) $reasons[] = 'No original task link';
                                                        $tooltipText = implode(', ', $reasons);
                                                        @endphp
                                                        <div class="relative group cursor-default">
                                                            <svg class="w-5 h-5 text-gray-400 hover:text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M18 10A8 8 0 1 1 2 10a8 8 0 0 1 16 0zm-9-1V7h2v2H9zm0 2h2v4H9v-4z" clip-rule="evenodd" />
                                                            </svg>
                                                            <div class="absolute top-1/2 left-full -translate-y-1/2 w-[170px] text-xs bg-black text-white rounded px-3 py-2 z-20 hidden group-hover:block text-left whitespace-normal shadow-lg">
                                                                {{ $tooltipText }}
                                                            </div>
                                                        </div>
                                                        @endif
                                                        <div class="flex items-center justify-center h-full mr-2">
                                                            <label class="switch m-0" @click.stop>
                                                                <input type="checkbox" class="toggle-task-status"
                                                                    data-task-id="{{ $task->id }}"
                                                                    {{ $task->enabled ? 'checked' : '' }}>
                                                                <span class="slider round"></span>
                                                            </label>
                                                        </div>
                                                        <div x-data="{ open: false, editOpen: false }" @keydown.escape.window="open = false; editOpen = false" class="relative flex items-center justify-center h-full">
                                                            <button @click.stop="open = !open" x-ref="button"
                                                                class="p-2 rounded-full bg-gray-100 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none flex items-center justify-center">
                                                                <svg class="w-5 h-5 text-gray-700 dark:text-white" fill="currentColor" viewBox="0 0 24 24">
                                                                    <circle cx="5" cy="12" r="2" />
                                                                    <circle cx="12" cy="12" r="2" />
                                                                    <circle cx="19" cy="12" r="2" />
                                                                </svg>
                                                            </button>
                                                            <template x-teleport="body">
                                                                <div x-show="open" @click.away="open = false" x-anchor.bottom-start.offset.5="$refs.button"
                                                                    x-cloak class="absolute z-[9999] w-32 rounded-md bg-white shadow-lg border border-gray-200">
                                                                    <ul class="py-1 text-sm text-gray-700 dark:text-gray-200">
                                                                        <li>
                                                                            <a href="javascript:void(0);"
                                                                                class="flex items-center px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-800"
                                                                                @click="$dispatch('view-task', { id: {{ $task->id }} }); $store.dropdown.closeAll()">
                                                                                <svg class="w-4 h-4 mr-2 text-blue-800" fill="currentColor" viewBox="0 0 24 24">
                                                                                    <path d="M12 4c-4.182 0-7.028 2.5-8.725 4.704C2.425 9.81 2 10.361 2 12s.425 2.191 1.275 3.296C4.972 17.5 7.818 20 12 20s7.028-2.5 8.725-4.704C21.575 14.191 22 13.64 22 12s-.425-2.19-1.275-3.296C19.028 6.5 16.182 4 12 4zm0 10a2.5 2.5 0 110-5 2.5 2.5 0 010 5z" />
                                                                                </svg>
                                                                                View Task
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a href="javascript:void(0);" @click.stop="editOpen = true; open = false" class="flex items-center px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-800">
                                                                                <svg class="w-4 h-4 mr-2 text-blue-800" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                                                    <path d="M3 17l-2 4l4-2l14-14l-2-2L3 17Z" />
                                                                                </svg>
                                                                                Edit Task
                                                                            </a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </template>
                                                            @php
                                                            $isInvoicedAndPaid = \App\Models\InvoiceDetail::where('task_id', $task->id)
                                                                ->whereHas('invoice', fn($q) => $q->where('status', 'paid'))
                                                                ->exists();
                                                            @endphp
                                                            <template x-teleport="body">
                                                                <div x-show="editOpen" x-cloak x-data="{ readOnly: {{ $isInvoicedAndPaid ? 'true' : 'false' }} }"
                                                                    class="fixed inset-0 z-[10000] flex items-center justify-center bg-gray-800 bg-opacity-50">
                                                                    <form id="edit-task-form-{{ $task->id }}"
                                                                        action="{{ route('tasks.update', $task->id) }}"
                                                                        method="post"
                                                                        class="inline-flex flex-col gap-4 items-center">
                                                                        <div @click.away="editOpen = false" class="w-full sm:max-w-screen-sm mx-4 bg-white rounded-md border p-6 relative overflow-y-auto max-h-[90vh]">
                                                                            <div class="flex items-start justify-between mb-2">
                                                                                <div>
                                                                                    <h2 class="text-xl font-bold text-gray-800">Edit Task Details</h2>
                                                                                    <p class="text-gray-600 italic text-xs mt-1">Please update the task details to ensure accurate information</p>
                                                                                </div>
                                                                                <button @click="editOpen = false" type="button"
                                                                                    class="absolute top-2 right-2 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                                                                    &times;
                                                                                </button>
                                                                            </div>
                                                                            @csrf
                                                                            @method('PUT')
                                                                            <fieldset :disabled="readOnly" :class="readOnly ? 'opacity-80' : ''">
                                                                                <div class="flex flex-col gap-6">
                                                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                                                        <div class="flex-1">
                                                                                            <label for="reference" class="block text-sm font-medium text-gray-700">Reference</label>
                                                                                            <input type="text"
                                                                                                class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-base"
                                                                                                name="reference"
                                                                                                value="{{ $task->reference }}">
                                                                                        </div>
                                                                                        <div class="flex-1">
                                                                                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                                                                                            @if ($task->status === 'refund')
                                                                                            <select name="status" id="status"
                                                                                                class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-base"
                                                                                                disabled>
                                                                                                <option value="refund"
                                                                                                    selected>Refund
                                                                                                </option>
                                                                                            </select>
                                                                                            <input type="hidden" name="status" value="refund">
                                                                                            @else
                                                                                            <select name="status"
                                                                                                id="status_{{ $task->id }}"
                                                                                                class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-base">
                                                                                                <option value="">Set
                                                                                                    Status
                                                                                                </option>
                                                                                                <option value="confirmed"
                                                                                                    {{ $task->status === 'confirmed' ? 'selected' : '' }}>
                                                                                                    Confirmed
                                                                                                </option>
                                                                                                <option value="issued"
                                                                                                    {{ $task->status === 'issued' ? 'selected' : '' }}>
                                                                                                    Issued
                                                                                                </option>
                                                                                                <option value="reissued"
                                                                                                    {{ $task->status === 'reissued' ? 'selected' : '' }}>
                                                                                                    Reissued
                                                                                                </option>
                                                                                                <option value="refund"
                                                                                                    {{ $task->status === 'refund' ? 'selected' : '' }}>
                                                                                                    Refund
                                                                                                </option>
                                                                                                <option value="void"
                                                                                                    {{ $task->status === 'void' ? 'selected' : '' }}>
                                                                                                    Void
                                                                                                </option>
                                                                                                <option value="emd"
                                                                                                    {{ $task->status === 'emd' ? 'selected' : '' }}>
                                                                                                    Emd
                                                                                                </option>
                                                                                            </select>
                                                                                            @endif
                                                                                        </div>
                                                                                    </div>

                                                                                    @if (strtolower($task->status) !== 'issued' && strtolower($task->status) !== 'confirmed'|| $task->status == null)
                                                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                                                        <div class="flex-1">
                                                                                            @php
                                                                                            $originalTasks = \App\Models\Task::with('client')
                                                                                            ->where('status', 'issued')
                                                                                            ->where(function ($query) use ($task) {
                                                                                            $query->where('reference', $task->reference)
                                                                                            ->orWhere('passenger_name', $task->passenger_name);
                                                                                            })
                                                                                            ->get();
                                                                                            $selectedOriginalTask = $originalTasks->firstWhere('id', $task->original_task_id);
                                                                                            $taskPlaceholder = $selectedOriginalTask
                                                                                            ? $selectedOriginalTask->reference . ' - ' . ($selectedOriginalTask->client->full_name ?? $selectedOriginalTask->client_name)
                                                                                            : 'Select Original Task';
                                                                                            @endphp

                                                                                            <label for="original_task_id" class="block text-sm font-medium text-gray-700">Original Task</label>
                                                                                            <x-searchable-dropdown
                                                                                                    name="original_task_id"
                                                                                                    :items="$originalTasks->map(fn($t) => [
                                                                                                    'id' => $t->id,
                                                                                                    'name' => $t->reference . ' - ' . ($t->client->full_name ?? $t->client_name)
                                                                                                ])->values()"
                                                                                                :selectedId="$task->original_task_id"
                                                                                                :selectedName="$selectedOriginalTask
                                                                                                ? $selectedOriginalTask->reference . ' - ' . ($selectedOriginalTask->client->full_name ?? $selectedOriginalTask->client_name)
                                                                                                : null"
                                                                                                :placeholder="$taskPlaceholder" />
                                                                                        </div>
                                                                                    </div>
                                                                                    @endif

                                                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                                                        <div class="flex-1">
                                                                                            <label for="supplier" class="block text-sm font-medium text-gray-700">Supplier</label>
                                                                                            <input type="text"
                                                                                                class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full bg-gray-200"
                                                                                                value="{{ $task->supplier ? $task->supplier->name : '' }}"
                                                                                                readonly>
                                                                                            <input type="hidden"
                                                                                                name="supplier_id"
                                                                                                id="supplier_id_{{ $task->id }}"
                                                                                                value="{{ $task->supplier ? $task->supplier->id : '' }}">

                                                                                        </div>
                                                                                        <div class="flex-1">
                                                                                            <label for="type" class="block text-sm font-medium text-gray-700">Task Type</label>
                                                                                            <input type="text"
                                                                                                class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full bg-gray-200"
                                                                                                value="{{ ucfirst($task->type) }}"
                                                                                                readonly>
                                                                                        </div>
                                                                                    </div>

                                                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                                                        @php
                                                                                        $selectedClient = \App\Models\Client::find($task->client_id);
                                                                                        $clientPlaceholder = $selectedClient
                                                                                        ? $selectedClient->full_name . ' - ' . $selectedClient->phone
                                                                                        : 'Select a Client';
                                                                                        @endphp
                                                                                        <div class="flex-1 min-w-0">
                                                                                            <label for="client_id" class="block text-sm font-medium text-gray-700">Client</label>
                                                                                            <div class="w-full">
                                                                                                <x-searchable-dropdown
                                                                                                        name="client_id"
                                                                                                        :items="$fullClients->map(fn($c) => [
                                                                                                        'id' => $c->id, 
                                                                                                        'name' => $c->full_name . ' - ' . $c->phone
                                                                                                    ])"
                                                                                                    :selectedId="$task->client_id"
                                                                                                    :selectedName="$selectedClient ? $selectedClient->full_name . ' - ' . $selectedClient->phone : null"
                                                                                                    placeholder="Select Client" />
                                                                                            </div>
                                                                                        </div>

                                                                                        <!-- Agent Selection (Role-based) -->
                                                                                        <div class="flex-1">
                                                                                            <label for="agent_id" class="block text-sm font-medium text-gray-700">Agent</label>
                                                                                            <select
                                                                                                id="agent_id_select_{{ $task->id }}"
                                                                                                name="agent_id"
                                                                                                class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-base">
                                                                                                <option value=""> Choose Agent</option>
                                                                                                @foreach ($agents as $agent)
                                                                                                <option value="{{ $agent->id }}"
                                                                                                    {{ $task->agent && $task->agent->id === $agent->id ? 'selected' : '' }}>
                                                                                                    {{ $agent->name }}
                                                                                                </option>
                                                                                                @endforeach
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>

                                                                                    <div x-data="{
                                                                                            rawPrice: '{{ $task->price ?? 0 }}',
                                                                                            rawTax: '{{ $task->tax ?? 0 }}',
                                                                                            rawSurcharge: '{{ $task->surcharge ?? 0 }}',
                                                                                            total: 0,
                                                                                            parseNum(v) {
                                                                                            if (!v) return 0;
                                                                                            const num = parseFloat(String(v).replace(/,/g,'').trim());
                                                                                            return isNaN(num) ? 0 : num;
                                                                                            }
                                                                                        }"
                                                                                        x-effect="total = +(parseNum(rawPrice) + parseNum(rawTax) + parseNum(rawSurcharge)).toFixed(3)"
                                                                                        class="flex flex-wrap gap-4">
                                                                                        <div class="flex-1 min-w-[150px]">
                                                                                            <label class="block text-sm font-medium text-gray-700">Price</label>
                                                                                            <input type="text" name="price" x-model="rawPrice"
                                                                                                class="border border-gray-300 p-2 rounded-md w-full">
                                                                                        </div>
                                                                                        <div class="flex-1 min-w-[150px]">
                                                                                            <label class="block text-sm font-medium text-gray-700">Tax</label>
                                                                                            <input type="text" name="tax" x-model="rawTax"
                                                                                                class="border border-gray-300 p-2 rounded-md w-full">
                                                                                        </div>
                                                                                        <div class="flex-1 min-w-[150px]">
                                                                                            <label class="block text-sm font-medium text-gray-700">Surcharge</label>
                                                                                            <input type="text" name="surcharge" x-model="rawSurcharge"
                                                                                                class="border border-gray-300 p-2 rounded-md w-full">
                                                                                        </div>
                                                                                        <div class="flex-1 min-w-[150px]">
                                                                                            <label class="block text-sm font-medium text-gray-700">Total</label>
                                                                                            <input type="text" name="total" :value="total" readonly
                                                                                                class="border border-gray-300 p-2 rounded-md w-full">
                                                                                        </div>
                                                                                    </div>
                                                                                    <!-- Payment Method -->
                                                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                                                        <div class="flex-1">
                                                                                            <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                                                                                            <select name="payment_method_account_id" id="payment_method_account_id_{{ $task->id }}"
                                                                                                class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full">
                                                                                                <option value="">Select Payment Method</option>
                                                                                                @foreach($paymentMethod as $method)
                                                                                                    <option value="{{ $method->id }}" {{ $task->payment_method_account_id == $method->id ? 'selected' : ''}}>{{ $method->name }}</option>
                                                                                                @endforeach
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="flex flex-col sm:flex-row gap-4">
                                                                                        <div class="flex-1">
                                                                                            <label for="additional_info"
                                                                                                class="block text-sm font-medium text-gray-700">Additional Info</label>
                                                                                            <textarea rows="3" readonly
                                                                                                class="border border-gray-300 dark:border-gray-600 p-3 rounded-md bg-gray-200 w-full resize-none">{{ $task->additional_info }} - {{ $task->venue }}
                                                                                            </textarea>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </fieldset>
                                                                            <div class="mt-6 flex flex-col sm:flex-row justify-between gap-4">
                                                                                <button type="button" @click="editOpen = false"
                                                                                    class="px-6 py-2 text-gray-700 font-semibold rounded-full bg-gray-200 hover:bg-gray-300 transition">
                                                                                    Cancel
                                                                                </button>
                                                                                <button type="submit"
                                                                                    :disabled="readOnly" :class="readOnly ? 'cursor-not-allowed opacity-60' : ''"
                                                                                    :title="readOnly ? 'This task is invoiced and cannot be edited' : ''"
                                                                                    class="w-full sm:w-auto px-6 py-2 text-white font-semibold rounded-full bg-blue-600 hover:bg-blue-700 transition"
                                                                                    form="edit-task-form-{{ $task->id }}">
                                                                                    Update
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </template>
                                                        </div>
                                                        @can('destroy', App\Models\Task::class)
                                                        <form action="{{ route('tasks.destroy', $task->id) }}" method="POST">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="group" @click.stop>
                                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="stroke-black dark:stroke-gray-300 group-hover:stroke-red-500">
                                                                    <path d="M20.5001 6H3.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                                                    <path d="M18.8332 8.5L18.3732 15.3991C18.1962 18.054 18.1077 19.3815 17.2427 20.1907C16.3777 21 15.0473 21 12.3865 21H11.6132C8.95235 21 7.62195 21 6.75694 20.1907C5.89194 19.3815 5.80344 18.054 5.62644 15.3991L5.1665 8.5" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                                                    <path d="M9.5 11L10 16" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                                                    <path d="M14.5 11L14 16" stroke="" stroke-width="1.5" stroke-linecap="round" />
                                                                    <path d="M6.5 6C6.55588 6 6.58382 6 6.60915 5.99936C7.43259 5.97849 8.15902 5.45491 8.43922 4.68032C8.44784 4.65649 8.45667 4.62999 8.47434 4.57697L8.57143 4.28571C8.65431 4.03708 8.69575 3.91276 8.75071 3.8072C8.97001 3.38607 9.37574 3.09364 9.84461 3.01877C9.96213 3 10.0932 3 10.3553 3H13.6447C13.9068 3 14.0379 3 14.1554 3.01877C14.6243 3.09364 15.03 3.38607 15.2493 3.8072C15.3043 3.91276 15.3457 4.03708 15.4286 4.28571L15.5257 4.57697C15.5433 4.62992 15.5522 4.65651 15.5608 4.68032C15.841 5.45491 16.5674 5.97849 17.3909 5.99936C17.4162 6 17.4441 6 17.5 6" stroke="" stroke-width="1.5" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                        @endcan
                                                    </div>
                                                </td>
                                                <td data-column="reference" class="p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->reference }}
                                                </td>
                                                <td data-column="bill-to" class="p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300 ">
                                                    @if ($task->client)
                                                    <p>{{ $task->client->full_name }}</p>
                                                    <p>{{ $task->client->phone ?? 'No phone' }}</p>
                                                    @else
                                                    <p class="{{ $task->client ?? 'no-client relative' }}">
                                                        <button
                                                            @click.stop="openManualForm({{ $task->id }}, '{{ $task->client_name ?? '' }}', '{{ $task->passenger_name ?? '' }}' ,'{{ $task->agent->name ?? 'Not Set' }}', '{{ $task->agent->id ?? 'Null' }}', '{{ $task->agent->branch->name ?? 'Not Set' }}')"
                                                            {{ $task->client !== null ? 'disabled' : '' }}>
                                                            {{ $task->client->full_name ?? $task->client_name !== '' ? $task->client_name : 'Not Set' }}
                                                        </button>
                                                    </p>
                                                    @endif
                                                </td>
                                                <td data-column="passenger-name" class="p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    <div class="relative group max-w-[180px] mx-auto">
                                                        <div class="truncate cursor-default">
                                                            {{ $task->passenger_name ?? 'Not Set' }}
                                                        </div>
                                                        @if ($task->passenger_name)
                                                        <div class="absolute z-10 hidden group-hover:block bg-gray-500 text-white text-xs rounded py-1 px-2 left-1/2 -translate-x-1/2 mt-1 shadow-lg">
                                                            {{ $task->passenger_name }}
                                                        </div>
                                                        @endif
                                                    </div>
                                                </td>
                                                @if (Auth()->user()->role_id != \App\Models\Role::AGENT)
                                                <td data-column="agent-name" class="p-3 text-sm text-center font-semibold text-gray-500">
                                                    {{ $task->agent->name ?? 'Not Set' }}
                                                </td>
                                                @endif
                                                @can('viewPrice', 'App\Models\Task')
                                                <td data-column="price" class="p-3 text-sm text-center font-semibold DarkBTextcolor dark:text-gray-300">
                                                    {{ $task->total ?? '-' }}
                                                </td>
                                                @endcan
                                                <td data-column="status" class="text-center">
                                                    <span
                                                        class="badge badge-outline-success whitespace-nowrap px-2 py-1 rounded text-sm font-medium"
                                                        @if ($task->status === 'reissued' && $task->originalTask) data-tooltip-left="Reissued from {{ $task->originalTask->flightDetails->ticket_number }}" @endif>
                                                        {{ $task->status === null ? 'Not Set' : ucwords($task->status) }}
                                                    </span>
                                                </td>
                                                <td data-column="supplier" class="p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->supplier->name }}
                                                </td>
                                                <td data-column="supplier-pay-date" class="p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->supplier_pay_date ? \Carbon\Carbon::parse($task->supplier_pay_date)->format('d-m-Y') : 'Not Set' }}
                                                </td>
                                                <td data-column="created-at" class="column-hidden p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->created_at }}
                                                </td>
                                                @if(Auth()->user()->role_id == \App\Models\Role::COMPANY)
                                                <td data-column="cancellation-deadline" class="column-hidden p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->cancellation_deadline ?  \Carbon\Carbon::parse($task->cancellation_deadline)->format('d-m-Y') : 'Not Set' }}
                                                </td>
                                                @endif
                                                <td data-column="info" class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                                    @if ($task->type === 'flight')
                                                    @php
                                                    $flight = $task->flightDetails;
                                                    $isFlightDataEmpty = !$flight || (!$flight->departure_time && !$flight->arrival_time && !$flight->airport_from && !$flight->airport_to);
                                                    @endphp
                                                    @if ($isFlightDataEmpty)
                                                    <div class="text-gray-500 text-sm">Flight info not available</div>
                                                    @else
                                                    <div class="flex justify-between items-center gap-4 text-center text-sm">
                                                        <div class="flex flex-col items-center">
                                                            <span class="font-bold text-base">
                                                                {{ $task->flightDetails ? \Carbon\Carbon::parse($task->flightDetails->departure_time)->format('H:i') : 'N/A'}}
                                                            </span>
                                                            <span class="text-gray-600 text-sm">
                                                                {{ $task->flightDetails->airport_from ?? 'N/A' }}
                                                            </span>
                                                        </div>
                                                        <div class="text-blue-700 text-lg"> ✈ </div>
                                                        <div class="flex flex-col items-center">
                                                            <span class="font-bold text-base">
                                                                {{$task->flightDetails ? \Carbon\Carbon::parse($task->flightDetails->arrival_time)->format('H:i') : 'N/A'}}
                                                            </span>
                                                            <span class="text-gray-600 text-sm">
                                                                {{ $task->flightDetails->airport_to ?? 'N/A' }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    @endif
                                                    @elseif ($task->type === 'hotel')
                                                    @php
                                                    $hotelDetails = $task->hotelDetails;
                                                    $hotel = $hotelDetails?->hotel;
                                                    $isHotelDataEmpty = !$hotelDetails || (!$hotel?->name && !$hotelDetails->check_in && !$hotelDetails->check_out);
                                                    @endphp
                                                    @if ($isHotelDataEmpty)
                                                    <div class="text-gray-500 text-sm">Hotel info not available</div>
                                                    @else
                                                    <div class="flex items-start gap-2 text-sm text-left">
                                                        <div class="pt-1">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path d="M8 21V7a1 1 0 011-1h6a1 1 0 011 1v14M3 21v-4a1 1 0 011-1h4a1 1 0 011 1v4m10 0v-6a1 1 0 011-1h2a1 1 0 011 1v6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                            </svg>
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <div class="relative max-w-[180px]" data-tooltip-left="{{ $task->hotelDetails->hotel->name ?? '-' }}">
                                                                <div class="truncate">{{ $task->hotelDetails->hotel->name ?? 'N/A' }}</div>
                                                            </div>
                                                            <div class="text-sm text-gray-500 whitespace-nowrap">
                                                                {{ $task->hotelDetails->check_in ?? 'N/A' }} - {{ $task->hotelDetails->check_out ?? 'N/A' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @endif
                                                    @else
                                                    <div class="text-sm text-gray-700 whitespace-pre-line break-words leading-tight">
                                                        {{ $task->additional_info ?? '-' }}
                                                    </div>
                                                    @endif
                                                </td>
                                                <td data-column="type" class="column-hidden p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->type }}
                                                </td>
                                                <td data-column="gds-reference" class="column-hidden p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->gds_reference ?? 'Not Available' }}
                                                </td>
                                                <td data-column="amadeus-reference" class="column-hidden p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->airline_reference ?? 'Not Available' }}
                                                </td>
                                                <td data-column="created-by" class="column-hidden p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->created_by ?? 'Not Set' }}
                                                </td>
                                                <td data-column="issued-by" class="column-hidden p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->issued_by ?? 'Not Set' }}
                                                </td>
                                                @if (Auth()->user()->role_id == \App\Models\Role::COMPANY)
                                                <td data-column="branch-name" class="column-hidden p-3 text-sm text-center font-semibold text-gray-500">
                                                    {{ $task->agent->branch->name ?? 'Not Set' }}
                                                </td>
                                                @endif
                                                <td data-column="invoice" class="column-hidden p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    @if ($task->invoiceDetail)
                                                    <a target="_blank"
                                                        href="{{ route('invoice.show', ['companyId' => $task->company_id, 'invoiceNumber' => $task->invoiceDetail->invoice_number]) }}">
                                                        <span
                                                            data-invoice-number="{{ $task->invoiceDetail->invoice_number }}"
                                                            class="badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium badge-outline-success">
                                                            {{ $task->invoiceDetail->invoice_number }}
                                                        </span>
                                                    </a>
                                                    @else
                                                    <span
                                                        class="badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium badge-outline-danger">
                                                        Not Yet
                                                    </span>
                                                    @endif
                                                </td>
                                                @if (Auth()->user()->role_id == \App\Models\Role::ADMIN || Auth()->user()->role_id == \App\Models\Role::COMPANY)
                                                <td data-column="file-name" class="column-hidden flex p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    @if(!empty($task->file_name))
                                                    <p> {{ basename($task->file_name) ?? 'No Files' }} </p>
                                                    <div @click.stop="navigator.clipboard.writeText('{{ basename($task->file_name) }}')" class="ml-2 text-black hover:text-blue-500 transition-colors flex items-center gap-1"
                                                        data-tooltip-left="Copy filename">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                    @else
                                                    <p>No Files</p>
                                                    @endif
                                                </td>
                                                @endif
                                            </tr>
                                            @endforeach
                                            @endif
                                        </tbody>

                                        <!-- Upload Passport -->
                                        <div x-show="showUploadForm" x-transition x-cloak
                                            class="fixed inset-0 z-50 bg-gray-700 bg-opacity-60 flex items-center justify-center">
                                            <div class="bg-white rounded-lg p-6 w-full max-w-sm shadow-xl">
                                                <div class="flex items-start justify-between mb-6">
                                                    <div>
                                                        <h2 class="text-xl font-bold text-gray-800">Upload Passport</h2>
                                                        <p class="text-gray-500 italic text-xs mt-1">Please choose
                                                            appropriate file to proceed</p>
                                                    </div>
                                                    <button @click="closeAll()"
                                                        class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">
                                                        &times;
                                                    </button>
                                                </div>

                                                <!--                                             <input type="hidden" name="task_id" :value="modalTaskId"> -->
                                                <div id="passport">
                                                    <input type="file" id="passport-upload-input"
                                                        accept="image/*,application/pdf"
                                                        class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg cursor-pointer dark:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                        hidden>
                                                    <div id="file-preview-container" class="mt-4"></div>
                                                    <!-- For image preview -->
                                                    <div id="upload-status" class="mt-2 text-sm text-gray-600"></div>
                                                    <!-- For upload status -->
                                                    <div id="passport-details" class="mt-4 text-sm text-gray-800"></div>
                                                    <!-- For displaying extracted details -->
                                                </div>

                                                <div class="flex justify-between mt-10">
                                                    <button @click="closeAll()" type="button"
                                                        class="w-32 bg-gray-300 hover:bg-gray-400 font-semibold py-2 rounded-full text-sm transition duration-150">
                                                        Cancel
                                                    </button>

                                                    <button id="submit-passport-upload"
                                                        @click="showFileInput = !showFileInput"
                                                        class="w-32 flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-3 rounded-full shadow-sm transition-all duration-150">
                                                        Upload
                                                    </button>

                                                </div>
                                            </div>
                                        </div>

                                        <!-- Manual Fill Form -->
                                        <div x-show="showManualForm" x-cloak
                                            class="fixed inset-0 z-50 bg-gray-700 bg-opacity-60 flex items-center justify-center px-2">
                                            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 h-[90vh] sm:overflow-visible overflow-y-auto transition-all duration-300">
                                                <!-- Header with title and close button -->
                                                <div class="flex items-center justify-between mb-2">
                                                    <h2 class="text-xl font-bold text-gray-800">Client Registration</h2>
                                                    <button @click="closeAll()"
                                                        class="text-gray-400 hover:text-red-500 text-2xl leading-none">&times;</button>
                                                </div>

                                                <!-- Subtitle -->
                                                <p class="text-gray-600 italic text-xs mb-6">Please fill in the required
                                                    client information to register</p>

                                                <!-- Form -->
                                                <form action="{{ route('clients.store') }}" method="POST"
                                                    id="client-formTask" class="space-y-4">
                                                    @csrf
                                                    <input type="hidden" name="task_id" :value="modalTaskId">
                                                    <input type="hidden" name="agent_id" :value="modalAgentId">
                                                    <!-- Name -->
                                                    <div id="upload-passport-container"
                                                        class="my-2 border-2 border-dashed border-gray-400 rounded-md w-full w-full flex flex-col justify-center gap-2 items-center p-2 min-h-20 max-h-48"
                                                        ondrop="dropHandler(event);" ondragover="dragOverHandler(event);">
                                                        <svg width="24" height="24" viewBox="0 0 24 24"
                                                            fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path d="M18 10L13 10" stroke="#1C274C" stroke-width="1.5"
                                                                stroke-linecap="round" />
                                                            <path
                                                                d="M10 3H16.5C16.9644 3 17.1966 3 17.3916 3.02567C18.7378 3.2029 19.7971 4.26222 19.9743 5.60842C20 5.80337 20 6.03558 20 6.5"
                                                                stroke="#1C274C" stroke-width="1.5" />
                                                            <path
                                                                d="M2 6.94975C2 6.06722 2 5.62595 2.06935 5.25839C2.37464 3.64031 3.64031 2.37464 5.25839 2.06935C5.62595 2 6.06722 2 6.94975 2C7.33642 2 7.52976 2 7.71557 2.01738C8.51665 2.09229 9.27652 2.40704 9.89594 2.92051C10.0396 3.03961 10.1763 3.17633 10.4497 3.44975L11 4C11.8158 4.81578 12.2237 5.22367 12.7121 5.49543C12.9804 5.64471 13.2651 5.7626 13.5604 5.84678C14.0979 6 14.6747 6 15.8284 6H16.2021C18.8345 6 20.1506 6 21.0062 6.76946C21.0849 6.84024 21.1598 6.91514 21.2305 6.99383C22 7.84935 22 9.16554 22 11.7979V14C22 17.7712 22 19.6569 20.8284 20.8284C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.8284C2 19.6569 2 17.7712 2 14V6.94975Z"
                                                                stroke="#1C274C" stroke-width="1.5" />
                                                        </svg>
                                                        <input type="file" name="file" id="file-task-passport"
                                                            class="hidden"
                                                            accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf">
                                                        <p id="task-passport-file-name">
                                                            You can drag and drop a file here
                                                        </p>
                                                        <label for="file-task-passport"
                                                            class="bg-black text-white font-semibold p-2 rounded-md border-2 border-black hover:border-2 hover:border-cyan-500">
                                                            Upload File
                                                        </label>
                                                    </div>
                                                    <div class="my-2">
                                                        <button id="task-passport-process-btn"
                                                            class="w-full bg-gray-300 text-gray-500 font-semibold py-2 rounded-full text-sm transition duration-150 cursor-not-allowed"
                                                            disabled>
                                                            Process File
                                                        </button>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label
                                                            class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                                        <input type="text" name="first_name" id="nameTask"
                                                            :value="modalClientName" placeholder="Client's First Name"
                                                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                            required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label
                                                            class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                                        <input type="text" name="middle_name" id="middleNameTask"
                                                            placeholder="Client's Middle Name"
                                                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label
                                                            class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                                        <input type="text" name="last_name" id="lastNameTask"
                                                            placeholder="Client's Last Name"
                                                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label
                                                            class="block text-sm font-medium text-gray-700 mb-1">Passenger's Name</label>
                                                        <input type="text" name="passenger_name" id="passengerName"
                                                            :value="modalPassengerName" placeholder="Passengers's name"
                                                            class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-100 text-gray-500 focus:outline-none focus:ring-0 focus:border-gray-300 cursor-not-allowed"
                                                            disabled>
                                                    </div>

                                                    <!-- Email + DOB -->
                                                    <div class="flex gap-4 mb-3">
                                                        <div class="w-1/2">
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                                            <input type="email" name="email" id="emailTask"
                                                                placeholder="Client's email"
                                                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                        </div>
                                                        <div class="w-1/2">
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-1">Date
                                                                of Birth</label>
                                                            <input type="date" name="date_of_birth" id="date_of_birthTask"
                                                                class="w-full text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                        </div>
                                                    </div>

                                                    <!-- Phone -->
                                                    <div class="mb-3">
                                                        <label
                                                            class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                                        <div class="flex gap-2">
                                                            <div class="relative w-40">
                                                                <x-searchable-dropdown name="dial_code" :items="\App\Models\Country::all()->map(
                                                                    fn($country) => [
                                                                        'id' => $country->dialing_code,
                                                                        'name' =>
                                                                            $country->dialing_code . ' ' . $country->name,
                                                                    ],
                                                                )"
                                                                    placeholder=" Search Dial Code" :showAllOnOpen="true" />
                                                            </div>
                                                            <input type="text" name="phone"
                                                                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                                id="phoneTask" placeholder="Client's phone number"
                                                                required>
                                                        </div>
                                                    </div>

                                                    <!-- Passport + Civil -->
                                                    <div class="flex gap-4 mb-3">
                                                        <div class="w-1/2">
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-1">Passport
                                                                Number</label>
                                                            <input type="text" name="passport_no" id="passport_noTask"
                                                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                        </div>
                                                        <div class="w-1/2">
                                                            <label
                                                                class="block text-sm font-medium text-gray-700 mb-1">Civil
                                                                Number</label>
                                                            <input type="text" name="civil_no" id="civil_noTask"
                                                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                        </div>
                                                    </div>
                                                    <!-- Address -->
                                                    <div class="mb-3">
                                                        <label
                                                            class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                                        <input type="text" name="address" id="addressTask"
                                                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                            placeholder="Client's address">
                                                    </div>

                                                    <!-- Agent Name -->
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Agent's Name</label>
                                                        <x-searchable-dropdown name="agent_id"
                                                            :items="$agents" placeholder="Search Agent"
                                                            :showAllOnOpen="true" />
                                                    </div>

                                                    <!-- Buttons -->
                                                    <div class="flex justify-between gap-3 pt-4 mt-4">
                                                        <button type="button" @click="closeAll()"
                                                            class="w-[45%] sm:w-32 bg-gray-300 hover:bg-gray-400 font-semibold py-3 sm:py-2 rounded-full text-sm transition duration-150">
                                                            Cancel
                                                        </button>
                                                        <button type="submit"
                                                            class="w-[45%] sm:w-32 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 sm:py-2 rounded-full text-sm transition duration-150">
                                                            Register Client
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </table>
                                    <div x-show="showBulkEditModal" x-transition x-cloak
                                        class="fixed inset-0 z-30 flex items-center justify-center bg-gray-800" style="background-color: rgba(31, 41, 55, 0.7);">
                                        <div class="bg-white rounded-md border p-6 w-full max-w-md relative overflow-y-auto max-h-[90vh]">
                                            <div class="flex items-start justify-between mb-2">
                                                <div>
                                                    <h2 class="text-xl font-bold text-gray-800">Bulk Edit Tasks</h2>
                                                    <p class="text-gray-600 italic text-xs mt-1">Update Client, Agent, or Payment Method for selected tasks</p>
                                                </div>
                                                <button @click="showBulkEditModal = false"
                                                    class="absolute top-2 right-2 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                                    &times;
                                                </button>
                                            </div>
                                            <form id="bulk-edit-form" @submit.prevent="submitBulkEdit" class="flex flex-col gap-6">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                                                    <x-searchable-dropdown name="bulk_client_id"
                                                        :items="$clients->map(fn($c) => ['id' => $c->id, 'name' => $c->full_name . ' - ' . $c->phone])"
                                                        placeholder="Select Client" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Agent</label>
                                                    <x-searchable-dropdown name="bulk_agent_id"
                                                        :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])"
                                                        placeholder="Select Agent" />
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                                    <x-searchable-dropdown name="bulk_payment_method_id"
                                                        :items="$paymentMethod->map(fn($m) => ['id' => $m->id, 'name' => $m->name])"
                                                        placeholder="Select Payment Method" />
                                                </div>
                                                <div class="mt-6 flex flex-col sm:flex-row justify-between gap-4">
                                                    <button type="button"
                                                        @click="showBulkEditModal = false"
                                                        class="px-6 py-2 text-gray-700 font-semibold rounded-full bg-gray-200 hover:bg-gray-300 transition">
                                                        Cancel
                                                    </button>
                                                    <button type="submit"
                                                        class="w-full sm:w-auto px-6 py-2 text-white font-semibold rounded-full bg-blue-600 hover:bg-blue-700 transition">
                                                        Update Tasks
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <div id="floatingActions"
                                        class="hidden flex justify-between gap-5 fixed CuzPostion bg-[#f6f8fa] dark:bg-gray-800 shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] dark:shadow-[0_0_4px_2px_rgb(255_255_255_/_10%)] rounded-lg w-auto h-auto z-10 p-3">
                                        <div class="flex justify-between gap-5 items-center h-full">
                                            <button id="createInvoiceBtn" data-route="{{ route('invoices.create') }}" type="button"
                                                class="flex px-5 py-3 gap-3 btn-success hover:bg-green-600 rounded-lg shadow-sm items-center transition-colors duration-200">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                    viewBox="0 0 24 24">
                                                    <path fill="#ffffff"
                                                        d="M2 12c0-2.8 1.6-5.2 4-6.3V3.5C2.5 4.8 0 8.1 0 12s2.5 7.2 6 8.5v-2.2c-2.4-1.1-4-3.5-4-6.3m13-9c-5 0-9 4-9 9s4 9 9 9s9-4 9-9s-4-9-9-9m5 10h-4v4h-2v-4h-4v-2h4V7h2v4h4z" />
                                                </svg>
                                                <span id="createInvoiceBtnText" class="text-sm">Create Invoice</span>
                                            </button>
                                        </div>
                                        <div class="flex justify-between gap-5 items-center h-full">
                                            <button type="button"
                                                x-show="selectedTasks.length > 1"
                                                @click="showBulkEditModal = true"
                                                class="flex px-5 py-3 gap-3 bg-yellow-500 hover:bg-yellow-600 rounded-lg shadow-sm items-center transition-colors duration-200">

                                                <span class="text-sm text-white">Bulk Edit</span>
                                            </button>
                                        </div>
                                        <div id="closeTaskFloatingActions" @click="clearSelectedTasks()"
                                            class="flex cursor-pointer items-center justify-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                viewBox="0 0 12 12">
                                                <path fill="#E53935"
                                                    d="M1.757 10.243a6.001 6.001 0 1 1 8.488-8.486a6.001 6.001 0 0 1-8.488 8.486M6 4.763l-2-2L2.763 4l2 2l-2 2L4 9.237l2-2l2 2L9.237 8l-2-2l2-2L8 2.763Z" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- <div id="loadMoreWrapper" class="text-center my-4"
                                x-show="shown < {{ count($tasks) }}" x-cloak>
                                <button @click="shown += 10"
                                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                    Load More
                                </button>
                            </div> -->
                        </div>

                        <x-pagination :data="$tasks->appends(request()->query())" />

                        <div id="taskInvoicePlaceholder"
                            class="hidden fixed inset-0 z-30 bg-gray-800 bg-opacity-50 flex justify-center items-center">
                            <div id="invoiceModalContent">
                                <div id="invoiceModalBody" class="rounded-t-md bg-white">
                                </div>
                                <div id="invoiceFooter"
                                    class="inline-flex justify-center bg-white w-full p-3 rounded-b-md">
                                    <x-primary-button class="font-bold text-lg">Edit</x-primary-button>
                                </div>
                            </div>
                        </div>
                        <div id="taskRefundPlaceholder"
                            class="hidden fixed inset-0 z-30 bg-gray-800 bg-opacity-50 flex justify-center items-center">
                            <div id="refundModalContent">
                                <div id="refundModalBody" class="rounded-t-md bg-white">
                                </div>
                                <div id="refundFooter"
                                    class="inline-flex justify-center bg-white w-full p-3 rounded-b-md">
                                    <x-primary-button class="font-bold text-lg">Edit</x-primary-button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-30 hidden" id="showRightDiv">
                <div id="taskDetails" class="panel w-full xl:mt-0 rounded-lg h-auto"></div>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    window.allTaskTypes = @json($allTypes ?? []);

    window.companySuppliers = @json($suppliers->pluck('name')->all());
    window.SUPPLIERS = @json(
        $suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'has_hotel' => $s->has_hotel])
    );

    document.addEventListener('alpine:init', () => {
        Alpine.store('dropdown', {
            openId: null,

            toggle(id) {
                this.openId = this.openId === id ? null : id;
            },
            isOpen(id) {
                return this.openId === id;
            },
            closeAll() {
                this.openId = null;
            }
        });
    });

    document.addEventListener("DOMContentLoaded", function() {
        const customizeBtn = document.getElementById('customizeColumnsBtn');
        const dropdown = document.getElementById('columnDropdownContent');
        const clearBtn = document.getElementById('clearAllColumns');
        const checkboxes = dropdown.querySelectorAll('.column-checkbox');

        const defaultColumns = @json($defaultColumns ?? []);
        let visibleColumns = JSON.parse(localStorage.getItem("visibleColumns"));

        if (!Array.isArray(visibleColumns) || visibleColumns.length === 0) {
            visibleColumns = @json($visibleColumns ?? $defaultColumns);
            localStorage.setItem("visibleColumns", JSON.stringify(visibleColumns));
        }

        console.log("Default columns from backend:", defaultColumns);
        console.log("Visible columns being applied:", visibleColumns);

        function updateColumnVisibility() {
            const checkboxes = document.querySelectorAll('.column-checkbox');
            checkboxes.forEach(checkbox => {
                const columnName = checkbox.id.replace('col-', '');
                const isVisible = visibleColumns.includes(columnName);

                checkbox.checked = isVisible;

                document.querySelectorAll(`[data-column="${columnName}"]`)
                    .forEach(column => {
                        column.classList.toggle('column-hidden', !isVisible);
                    });
            });
        }

        updateColumnVisibility();

        localStorage.setItem("visibleColumns", JSON.stringify(visibleColumns));

        function saveColumnPreferences() {
            const currentlyVisible = Array.from(checkboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.id.replace('col-', ''));

            fetch("{{ route('tasks.columns.save') }}", {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                    },
                    body: JSON.stringify({
                        columns: currentlyVisible
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Failed to save column preferences.');
                    }
                })
                .catch(error => console.error('Error saving column preferences:', error));
        }

        customizeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !customizeBtn.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const checkedBoxes = Array.from(checkboxes).filter(cb => cb.checked);
                if (checkedBoxes.length === 0) {
                    checkbox.checked = true;
                    alert("At least one column must remain visible.");
                } else {
                    const columnName = checkbox.id.replace('col-', '');
                    const columns = document.querySelectorAll(`[data-column="${columnName}"]`);
                    columns.forEach(column => {
                        column.classList.toggle('column-hidden', !checkbox.checked);
                    });
                    saveColumnPreferences();
                }
            });
        });

        clearBtn.addEventListener('click', function() {
            const checkedBoxes = Array.from(checkboxes).filter(cb => cb.checked);
            if (checkedBoxes.length <= 1) {
                alert("At least one column must remain visible.");
                return;
            }
            checkedBoxes.forEach((checkbox, index) => {
                if (index > 0) {
                    checkbox.checked = false;
                    const columnName = checkbox.id.replace('col-', '');
                    const columns = document.querySelectorAll(`[data-column="${columnName}"]`);
                    columns.forEach(column => column.classList.add('column-hidden'));
                }
            });
            saveColumnPreferences();
        });

        const createInvoiceBtn = document.getElementById('createInvoiceBtn');
        if (createInvoiceBtn) {
            createInvoiceBtn.replaceWith(createInvoiceBtn.cloneNode(true)); // Remove all previous listeners
        }

        document.getElementById('createInvoiceBtn')?.addEventListener('click', function() {
            const selectedTasks = window.selectedTasksGlobal ?? [];

            console.log('Selected tasks:', selectedTasks);

            if (selectedTasks.length > 0) {
                const taskStatus = this.getAttribute('data-task-status');
                if (taskStatus === 'refund') {
                    window.location.href = this.getAttribute('data-route');
                } else {
                    const url = `/invoices/create?task_ids=${selectedTasks.join(',')}`;
                    window.location.href = url;
                }
            } else {
                alert('No task selected.');
            }
        });

        // Add event listeners to close modals when clicked outside or on close buttons
        document.getElementById('taskInvoicePlaceholder').addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.add('hidden');
            }
        });

        document.getElementById('taskRefundPlaceholder').addEventListener('click', function(event) {
            if (event.target === this) {
                this.classList.add('hidden');
            }
        });

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const modalInvoice = document.getElementById('taskInvoicePlaceholder');
            const invoiceBody = document.getElementById('invoiceModalBody');
            const modalRefund = document.getElementById('taskRefundPlaceholder');
            const refundBody = document.getElementById('refundModalBody');

            if (modalInvoice && invoiceBody && !invoiceBody.contains(event.target) && !event.target
                .closest('.invoiceModal')) {
                modalInvoice.classList.add('hidden');
            }

            if (modalRefund && refundBody && !refundBody.contains(event.target) && !event.target
                .closest('.refundModal')) {
                modalRefund.classList.add('hidden');
            }
        });

        document.getElementById('select-supplier-task')?.addEventListener('change', function() {
            let selectedSupplier = this.options[this.selectedIndex].getAttribute('data-supplier');
            let supplier = JSON.parse(selectedSupplier);
            let formTaskContainer = document.getElementById('form-task-container');
            const form = document.getElementById('agent-supplier-task');
            formTaskContainer.innerHTML = '';
            const isHotel = (supplier?.has_hotel == 1 || supplier?.has_hotel == '1') && supplier.name != 'Amadeus';

            function setFormSubmitHandler(handler) {
                // remove any previous handler
                if (form._currentSubmitHandler) {
                    form.removeEventListener('submit', form._currentSubmitHandler);
                }
                form._currentSubmitHandler = handler || null;
                if (handler) form.addEventListener('submit', handler);
            }

            console.log('Selected Supplier:', supplier);
            setFormSubmitHandler(null);

            if ((supplier?.is_manual == 1 || supplier?.is_manual == '1') && isHotel) {
                const hotelTemplate = document.getElementById('template-hotel-dropdown').innerHTML;
                const clientTemplate = document.getElementById('template-client-dropdown')?.innerHTML;
                const currencyTemplate = document.getElementById('template-currency-dropdown')?.innerHTML;
                const html = `
                    <div class="border rounded-md p-4 bg-white space-y-4">
                        <p class="font-medium text-gray-800">Manual hotel booking</p>
                        <input type="hidden" name="type" value="hotel">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Hotel name</label>
                                ${hotelTemplate}
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Room name</label>
                                <input id="mh-room" name="room_name" type="text" required class="w-full border rounded px-2 py-2 bg-white" placeholder="e.g. Deluxe King">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="col-span-1">
                                <label class="block text-xs text-gray-600 mb-1">Client</label>
                                ${clientTemplate}
                            </div>
                            <div class="col-span-1">
                                <label class="block text-xs text-gray-600 mb-1">Reference</label>
                                <input id="mh-ref" name="reference" type="text" required class="w-full border rounded px-2 py-2 bg-white">
                            </div>
                        </div>
                        <div class="grid grid-cols-1">
                            <div class="col-span-1">
                                <label class="block text-xs text-gray-600 mb-1">Issued date</label>
                                <input id="mh-issued-date" name="issued_date" type="date" required class="w-full border rounded px-2 py-2 bg-white">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Check-in</label>
                                <input id="mh-checkin" name="check_in" type="date" required class="w-full border rounded px-2 py-2 bg-white">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Check-out</label>
                                <input id="mh-checkout" name="check_out" type="date" required class="w-full border rounded px-2 py-2 bg-white">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Total nights</label>
                                <input id="mh-nights" type="number" class="w-full border rounded px-2 py-1 bg-gray-50" readonly>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Price per night</label>
                                <input id="mh-price-orig" type="number" step="0.001" required class="w-full border rounded px-2 py-2 bg-white" placeholder="0.000">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Currency</label>
                                ${currencyTemplate}
                            </div>
                            <div>
                                <label id="mh-total-orig-label" class="block text-xs text-gray-600 mb-1">Total (original)</label>
                                <input id="mh-total-orig" type="text" class="w-full border rounded px-2 py-1 bg-gray-50" readonly>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div id="mh-price-kwd-wrap" class="hidden">
                                <label class="block text-xs text-gray-600 mb-1">Price per night (KWD)</label>
                                <input id="mh-price-kwd" type="text" class="w-full border rounded px-2 py-1 bg-gray-50" readonly>
                            </div>
                            <div id="mh-total-kwd-wrap" class="hidden">
                                <label class="block text-xs text-gray-600 mb-1">Total (KWD)</label>
                                <input id="mh-total-kwd" type="text" class="w-full border rounded px-2 py-1 bg-gray-50" readonly>
                            </div>
                            <div class="hidden md:block"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Adults</label>
                                <input id="mh-adults" type="number" min="0" value="1" class="w-full border rounded px-2 py-2 bg-white">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Children</label>
                                <input id="mh-children" type="number" min="0" value="0" class="w-full border rounded px-2 py-2 bg-white">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Total pax</label>
                                <input id="mh-total-pax" type="number" class="w-full border rounded px-2 py-1 bg-gray-50" readonly>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Passengers</label>
                            <div id="mh-passengers" class="space-y-2">
                                <div class="flex gap-2">
                                    <input type="text" class="w-full border rounded px-2 py-1 mh-passenger" placeholder="Passenger name">
                                    <button type="button" class="px-2 border rounded add">+ Add</button>
                                </div>
                            </div>
                            <p id="mh-passenger-hint" class="text-xs text-gray-500 mt-1"></p>
                        </div>
                    </div>
                `;
                formTaskContainer.innerHTML = html;
                window.dispatchEvent(new Event('modal:wide'));

                function initSearchDropdown(root, items, evtName) {
                    const btn   = root.querySelector('.sd-btn');
                    const text  = root.querySelector('.sd-text');
                    const menu  = root.querySelector('.sd-menu');
                    const search= root.querySelector('.sd-search');
                    const list  = root.querySelector('.sd-list');
                    const hid   = root.querySelector('input[type="hidden"]');

                    function render(filter='') {
                        const f = String(filter).trim().toLowerCase();
                        const data = f ? items.filter(i => String(i.name ?? '').toLowerCase().includes(f) || String(i.id).toLowerCase().includes(f)) : items;

                        list.innerHTML = data.length ? '' : '<div class="p-2 text-sm text-gray-400">No results</div>';
                        data.forEach(i => {
                            const div = document.createElement('div');
                            div.className = 'p-2 hover:bg-gray-100 cursor-pointer text-sm';
                            div.textContent = i.name;
                            div.dataset.id = i.id;
                            list.appendChild(div);
                        });
                    }
                    function open() {
                        document.querySelectorAll('.sd-menu').forEach(m => m.classList.add('hidden'));
                        menu.classList.remove('hidden');
                        render('');
                        search.value = '';
                        setTimeout(() => search.focus(), 0);
                    }
                    function close() {
                        menu.classList.add('hidden');
                    }

                    btn.addEventListener('click', (e)=>{
                        e.stopPropagation();
                        menu.classList.contains('hidden') ? open() : close();
                    });
                    document.addEventListener('click', (e)=>{ if(!root.contains(e.target)) close(); });
                    search.addEventListener('input', ()=>render(search.value));
                    list.addEventListener('click', (e)=>{
                        const row = e.target.closest('[data-id]'); if(!row) return;
                        hid.value = row.dataset.id;
                        text.textContent = row.textContent.trim();
                        text.classList.remove('text-gray-400');
                        root.dispatchEvent(
                            new CustomEvent(evtName, { detail: { id: hid.value, label: text.textContent }, bubbles: true })
                            );
                        close();
                    });

                    // if hidden has a value, show it
                    if (hid.value) {
                        const found = items.find(i=>i.id==hid.value);
                        if (found) { text.textContent = found.name; text.classList.remove('text-gray-400'); }
                    }
                }

                const hotelItems = JSON.parse(formTaskContainer.querySelector('#hotel-items-json')?.textContent || '[]');
                const clientItems = JSON.parse(formTaskContainer.querySelector('#client-items-json')?.textContent || '[]');
                const currencyItems = JSON.parse(formTaskContainer.querySelector('#currency-items-json')?.textContent || '[]');

                initSearchDropdown(formTaskContainer.querySelector('[data-sd="hotel"]'), hotelItems, 'manual:hotel-changed');
                initSearchDropdown(formTaskContainer.querySelector('[data-sd="client"]'), clientItems, 'manual:client-changed');
                initSearchDropdown(formTaskContainer.querySelector('[data-sd="currency"]'), currencyItems, 'manual:currency-changed');

                const priceOrig = formTaskContainer.querySelector('#mh-price-orig');
                const totalOrig = formTaskContainer.querySelector('#mh-total-orig');
                const currencyHidden = formTaskContainer.querySelector('#selected-currency');
                const priceKwd = formTaskContainer.querySelector('#mh-price-kwd');
                const priceKwdWrap = formTaskContainer.querySelector('#mh-price-kwd-wrap');
                const totalOrigLabel = formTaskContainer.querySelector('#mh-total-orig-label');
                const totalKwd = formTaskContainer.querySelector('#mh-total-kwd');
                const totalKwdWrap = formTaskContainer.querySelector('#mh-total-kwd-wrap');
                const checkIn = formTaskContainer.querySelector('#mh-checkin');
                const checkOut = formTaskContainer.querySelector('#mh-checkout');
                const nights = formTaskContainer.querySelector('#mh-nights');
                const paxWrap = formTaskContainer.querySelector('#mh-passengers');
                const adultsEl = formTaskContainer.querySelector('#mh-adults');
                const childrenEl = formTaskContainer.querySelector('#mh-children');
                const totalPaxEl = formTaskContainer.querySelector('#mh-total-pax');
                const paxHint = formTaskContainer.querySelector('#mh-passenger-hint');

                function calcNights() {
                    const ci = new Date(checkIn.value);
                    const co = new Date(checkOut.value);
                    if (isNaN(ci) || isNaN(co) || co <= ci) { nights.value = 0; return 0; }
                    const n = Math.round((co - ci) / (1000*60*60*24));
                    nights.value = n;
                    return n;
                }

                function paxLimit() {
                    const total = (+adultsEl.value || 0) + (+childrenEl.value || 0);
                    totalPaxEl.value = total;
                    const current = paxWrap.querySelectorAll('input.mh-passenger').length;
                    const addBtn  = paxWrap.querySelector('button.add');
                    addBtn.disabled = current >= total && total > 0;
                    addBtn.classList.toggle('opacity-50', addBtn.disabled);
                    paxHint.textContent = total ? `Max ${total} passenger name(s)` : '';
                }

                async function convertCurrency(amount, from, to='KWD') {
                    if (!amount || from === to) return { converted: +amount, rate: 1 };
                    try {
                        const res = await fetch(`{{ route('exchange.convert') }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                            },
                            body: JSON.stringify({
                                from_currency: String(from).toUpperCase(),
                                to_currency: String(to).toUpperCase(),
                                amount: +amount
                            })
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            return { converted: +data.converted_amount, rate: +data.exchange_rate };
                        }
                        if (data.created) {
                            return { converted: null, rate: null, created: true, message: data.message };
                        }
                    } catch (e) {}
                    return { converted: null, rate: null };
                }

                async function recompute() {
                    const price = parseFloat(priceOrig.value) || 0;
                    const currency = (currencyHidden?.value || 'KWD').toUpperCase();
                    const nights = calcNights();
                    const total = +(price * nights).toFixed(3);

                    totalOrigLabel.textContent = `Total (${currency})`;
                    totalOrig.value = total ? `${total.toFixed(3)}` : '';

                    const showConverted = currency !== 'KWD';
                    priceKwdWrap.classList.toggle('hidden', !showConverted);
                    totalKwdWrap.classList.toggle('hidden', !showConverted);

                    if (showConverted) {
                        const conv1 = await convertCurrency(price, currency, 'KWD');
                        priceKwd.value = conv1.converted != null ? conv1.converted.toFixed(3) : '';
                        const convTotal = await convertCurrency(total, currency, 'KWD');
                        totalKwd.value = convTotal.converted != null ? convTotal.converted.toFixed(3) : '';
                    } else {
                        priceKwd.value = '';
                        totalKwd.value = total ? total.toFixed(3) : '';
                    }
                }

                formTaskContainer.addEventListener('manual:currency-changed', recompute);
                formTaskContainer.addEventListener('manual:client-changed', (e)=>{
                    const first = paxWrap.querySelector('input.mh-passenger');
                    if (first && e.detail?.label) first.value = e.detail.label;
                });

                [priceOrig, checkIn, checkOut].forEach(el => el.addEventListener('change', recompute));
                priceOrig.addEventListener('input', recompute);
                adultsEl.addEventListener('input', paxLimit);
                childrenEl.addEventListener('input', paxLimit);
                paxLimit();

                paxWrap.addEventListener('click', (e) => {
                    if (!e.target.classList.contains('add')) return;
                    const total = +totalPaxEl.value || 0;
                    const current = paxWrap.querySelectorAll('input.mh-passenger').length;
                    if (total && current >= total) return;

                    const row = document.createElement('div');
                    row.className = 'flex gap-2';
                    row.innerHTML = `
                        <input type="text" class="w-full border rounded px-2 py-1 mh-passenger" placeholder="Passenger name">
                        <button type="button" class="px-2 border rounded rm">✕</button>`;
                    paxWrap.appendChild(row);
                    paxLimit();
                });

                paxWrap.addEventListener('click', (e) => {
                    if (!e.target.classList.contains('rm')) return;
                    e.target.parentElement.remove();
                    paxLimit();
                });

                setFormSubmitHandler((e) => {
                    const hotelId = (formTaskContainer.querySelector('#selected-hotel')?.value || '').trim();
                    const clientId = (formTaskContainer.querySelector('#selected-client')?.value || '').trim();
                    const paxNames = Array.from(paxWrap.querySelectorAll('input.mh-passenger')).map(i => i.value.trim()).filter(Boolean);
                    const totalPax = +totalPaxEl.value || 0;

                    if (!hotelId) {
                        e.preventDefault(); alert('Hotel are required.'); return;
                    }
                    if (!clientId) {
                        e.preventDefault(); alert('Client is required.'); return;
                    }

                    const currency = (currencyHidden?.value || 'KWD').toUpperCase();
                    const perNight = parseFloat(priceOrig.value) || 0;
                    const night = calcNights();
                    const originalTotal = +(perNight * night).toFixed(3);

                    Array.from(form.querySelectorAll('input[data-synth="1"]')).forEach(el => el.remove());

                    const addHidden = (name, val) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = (typeof val === 'object') ? JSON.stringify(val) : String(val);
                        input.setAttribute('data-synth','1');
                        form.appendChild(input);
                    };

                    addHidden('additional_info', `Price per night: ${perNight.toFixed(4)} ${currency}`);
                    if (paxNames.length) {
                        addHidden('client_name', paxNames[0]);
                        paxNames.forEach((p, i) => addHidden(`passengers[${i}]`, p));
                    }
                    if (currency === 'KWD') {
                        addHidden('exchange_currency', 'KWD');
                        addHidden('price',  +originalTotal.toFixed(3));
                        addHidden('total',  +originalTotal.toFixed(3));
                    } else {
                        addHidden('original_currency', currency);
                        addHidden('original_price', +originalTotal.toFixed(3));
                        addHidden('original_total', +originalTotal.toFixed(3));
                        addHidden('exchange_currency', 'KWD');

                        const totalKwdEl = formTaskContainer.querySelector('#mh-total-kwd');
                        let totalKwd = parseFloat(totalKwdEl?.value);
                        addHidden('price', +(+totalKwd).toFixed(3));
                        addHidden('total', +(+totalKwd).toFixed(3));
                    }
                });
                return;
            } else if (supplier.name === 'Magic Holiday') {
                const modeWrap = document.createElement('div');
                modeWrap.className = 'mb-2';
                modeWrap.innerHTML = `
                <label class="block text-sm font-medium text-gray-800 mb-1">Upload Method</label>
                <div class="inline-flex gap-3">
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="mh_mode" value="ref" checked>
                        <span class="text-sm">By reference</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="radio" name="mh_mode" value="batch">
                        <span class="text-sm">From file</span>
                    </label>
                </div>`;
                formTaskContainer.appendChild(modeWrap);

                const content = document.createElement('div');
                formTaskContainer.appendChild(content);

                function buildBatchesUI(formTaskContainer) {
                    const batches = [];
                    let active = 0;

                    const toolbar = document.createElement('div');
                    toolbar.className = 'sticky top-0 bg-white px-0 pt-1 pb-2';
                    toolbar.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-800 leading-5">Upload by batches</p>
                            <p class="text-xs text-gray-500">Select your files. Each batch will be merged.</p>
                        </div>
                        <div class="flex items-center">
                            <button type="button" id="add-batch" class="p-2 inline-flex items-center rounded-md border border-gray-300 text-xs font-medium hover:bg-gray-50">
                            Add Batch
                            </button>
                        </div>
                    </div>`;
                    formTaskContainer.appendChild(toolbar);

                    const carousel = document.createElement('div');
                    carousel.className = 'relative';
                    carousel.innerHTML = `
                    <div id="batch-viewport" class="overflow-hidden">
                        <div id="batch-track" class="flex transition-transform duration-300 ease-out"></div>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <div class="inline-flex gap-1">
                            <button type="button" id="prev" class="rounded-md border px-2 py-1 text-xs hover:bg-gray-50">‹ Prev</button>
                            <button type="button" id="next" class="rounded-md border px-2 py-1 text-xs hover:bg-gray-50">Next ›</button>
                        </div>
                        <div id="dots" class="flex items-center gap-1"></div>
                    </div>`;
                    formTaskContainer.appendChild(carousel);

                    const track = carousel.querySelector('#batch-track');
                    const dots = carousel.querySelector('#dots');
                    const addBtn = toolbar.querySelector('#add-batch');
                    const prevBtn = carousel.querySelector('#prev');
                    const nextBtn = carousel.querySelector('#next');

                    function goTo(i) {
                        if (!batches.length) return;
                        active = Math.max(0, Math.min(i, batches.length - 1));
                        track.style.transform = `translateX(-${active*100}%)`;
                        renderDots();
                    }

                    function renderDots() {
                        dots.innerHTML = '';
                        batches.forEach((_, i) => {
                            const dot = document.createElement('button');
                            dot.type = 'button';
                            dot.className = `w-2.5 h-2.5 rounded-full ${i===active?'bg-gray-900':'bg-gray-300'}`;
                            dot.addEventListener('click', () => goTo(i));
                            dots.appendChild(dot);
                        });
                        prevBtn.disabled = active === 0;
                        nextBtn.disabled = active === batches.length - 1;
                        prevBtn.classList.toggle('opacity-50', prevBtn.disabled);
                        nextBtn.classList.toggle('opacity-50', nextBtn.disabled);
                    }

                    function addBatch() {
                        const batchIndex = batches.length;
                        batches.push([]);

                        const slide = document.createElement('div');
                        slide.className = 'w-full shrink-0';
                        slide.innerHTML = `
                        <div class="border rounded-md p-3 bg-white">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold">Batch #${batchIndex+1}</span>
                                    <span class="text-xs text-gray-500"><span class="count">0</span> files</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="clear text-xs text-red-500 hover:text-red-600">Clear</button>
                                    <button type="button" class="remove text-xs text-gray-500 hover:text-gray-700">Remove</button>
                                </div>
                            </div>
                            <label class="block text-xs text-gray-600 mb-1 name-label">Merged file name (optional)</label>
                            <input type="text" class="name-input w-full border rounded px-2 py-1 text-sm mb-2" placeholder="e.g. TBO_0001.pdf (only for 2+ files)" />
                            <div class="drop flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-md text-center cursor-pointer bg-white hover:bg-gray-50 transition text-sm text-gray-500 mb-2 p-4">
                                <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 64 64" fill="none" stroke="#5d5d5d" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 48H16C9.37258 48 4 42.6274 4 36C4 29.7926 8.79161 24.6465 14.9268 24.0438C17.3056 16.5436 24.2807 11 32.5 11C42.165 11 50 18.835 50 28.5C50 29.6813 49.8904 30.8323 49.6816 31.9425C55.0597 33.3639 59 38.2443 59 44C59 50.6274 53.6274 56 47 56H44"/>
                                    <path d="M32 38V20" />
                                    <path d="M24 28L32 20L40 28" />
                                </svg>
                                <p class="font-medium text-gray-700 mt-1">Click or drag PDF(s) here to upload</p>
                                <p class="text-xs text-gray-500">Multiple PDFs supported</p>
                            </div>
                            <input type="file" class="file hidden" accept="application/pdf" multiple />
                            <div class="files hidden text-sm text-gray-700 border border-gray-200 rounded p-2 bg-white max-h-[160px] overflow-y-auto"></div>
                            <p class="hint text-xs mt-2"></p>
                        </div>`;
                        const drop = slide.querySelector('.drop');
                        const fileInput = slide.querySelector('.file');
                        const filesBox = slide.querySelector('.files');
                        const countEl = slide.querySelector('.count');
                        const hint = slide.querySelector('.hint');
                        const nameInput = slide.querySelector('.name-input');
                        const nameLabel = slide.querySelector('.name-label');

                        function updateUI() {
                            const count = batches[batchIndex].length;
                            countEl.textContent = count;
                            filesBox.innerHTML = '';
                            if (!count) {
                                filesBox.classList.add('hidden');
                            } else {
                                filesBox.classList.remove('hidden');
                                batches[batchIndex].forEach((f, i) => {
                                    const row = document.createElement('div');
                                    row.className = 'bg-gray-100 rounded px-3 py-1 mb-1 flex items-center justify-between';
                                    row.innerHTML = `<span class="truncate text-xs max-w-[220px]">${f.name}</span><button type="button" class="rm text-xs text-red-500 hover:text-red-600">✕</button>`;
                                    row.querySelector('.rm').addEventListener('click', () => {
                                        batches[batchIndex].splice(i, 1);
                                        updateUI();
                                    });
                                    filesBox.appendChild(row);
                                });
                            }
                            if (count >= 2) {
                                hint.textContent = 'Ready to merge';
                                hint.className = 'hint text-xs mt-2 text-green-600';
                            } else if (count === 1) {
                                hint.textContent = 'Ready: single file (original name will be used)';
                                hint.className = 'hint text-xs mt-2 text-green-600';
                            } else {
                                hint.textContent = 'Add at least 1 PDF to this batch';
                                hint.className = 'hint text-xs mt-2 text-amber-600';
                            }
                            const showName = count >= 2;
                            nameInput.classList.toggle('hidden', !showName);
                            nameLabel.classList.toggle('hidden', !showName);
                            nameInput.disabled = !showName;
                        }

                        ['dragenter', 'dragover'].forEach(evt => drop.addEventListener(evt, e => {
                            e.preventDefault();
                            e.stopPropagation();
                            drop.classList.add('border-blue-400', 'bg-blue-50');
                        }));
                        ['dragleave', 'drop'].forEach(evt => drop.addEventListener(evt, e => {
                            e.preventDefault();
                            e.stopPropagation();
                            drop.classList.remove('border-blue-400', 'bg-blue-50');
                        }));

                        drop.addEventListener('click', () => fileInput.click());
                        drop.addEventListener('drop', e => {
                            const newFiles = Array.from(e.dataTransfer.files).filter(f => f.type === 'application/pdf');
                            batches[batchIndex].push(...newFiles);
                            updateUI();
                        });
                        fileInput.addEventListener('change', function() {
                            const picked = Array.from(this.files).filter(f => f.type === 'application/pdf');
                            batches[batchIndex].push(...picked);
                            this.value = '';
                            updateUI();
                        });

                        slide.querySelector('.clear').addEventListener('click', () => {
                            batches[batchIndex] = [];
                            updateUI();
                        });
                        slide.querySelector('.remove').addEventListener('click', () => {
                            const wasActive = active === batchIndex;
                            batches.splice(batchIndex, 1);
                            slide.remove();
                            Array.from(track.children).forEach((c, i) => {
                                const label = c.querySelector('span.font-semibold');
                                if (label) label.textContent = `Batch #${i+1}`;
                            });
                            if (!batches.length) active = 0;
                            else if (wasActive && active > 0) active -= 1;
                            goTo(active);
                        });

                        track.appendChild(slide);
                        const syncWidths = () => {
                            const vw = carousel.querySelector('#batch-viewport').clientWidth;
                            slide.style.width = vw + 'px';
                            Array.from(track.children).forEach(s => s.style.width = vw + 'px');
                        };
                        syncWidths();
                        window.addEventListener('resize', syncWidths);
                        goTo(batches.length - 1);
                        updateUI();
                    }

                    addBtn.addEventListener('click', addBatch);
                    prevBtn.addEventListener('click', () => goTo(active - 1));
                    nextBtn.addEventListener('click', () => goTo(active + 1));
                    addBatch();

                    // submit handler for batches
                    const onSubmit = (e) => {
                        // ensure NO supplier_ref is sent in batch mode
                        Array.from(form.querySelectorAll('input[name="supplier_ref"]')).forEach(el => el.remove());
                        Array.from(form.querySelectorAll('input[data-synth="1"]')).forEach(el => el.remove());
                        const invalid = [];
                        const slides = Array.from(track.children);

                        batches.forEach((files, i) => {
                            if (files.length < 1) invalid.push(i + 1);
                            const dt = new DataTransfer();
                            files.forEach(f => dt.items.add(f));

                            const hidden = document.createElement('input');
                            hidden.type = 'file';
                            hidden.multiple = true;
                            hidden.name = `batches[${i}][]`;
                            hidden.files = dt.files;
                            hidden.setAttribute('data-synth', '1');
                            hidden.style.display = 'none';
                            form.appendChild(hidden);

                            const nameHidden = document.createElement('input');
                            nameHidden.type = 'hidden';
                            nameHidden.name = `batch_names[${i}]`;
                            nameHidden.value = (slides[i]?.querySelector('.name-input')?.value || '').trim();
                            nameHidden.setAttribute('data-synth', '1');
                            form.appendChild(nameHidden);
                        });

                        if (!batches.length || invalid.length) {
                            e.preventDefault();
                            alert(!batches.length ?
                                'Please add at least one batch (min 1 PDF).' :
                                `Each batch must have at least 1 PDF.\nCheck batch(es): ${invalid.join(', ')}.`);
                        }
                    };

                    return {
                        attach() {
                            form.removeEventListener('submit', form._mhBatchHandler || (() => {}));
                            form._mhBatchHandler = onSubmit;
                            form.addEventListener('submit', onSubmit);
                        },
                        detach() {
                            form.removeEventListener('submit', form._mhBatchHandler || (() => {}));
                            Array.from(form.querySelectorAll('input[data-synth="1"]')).forEach(el => el.remove());
                        }
                    };
                }

                let batchesApi = null;

                function renderRef() {
                    content.innerHTML = '';
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = 'supplier_ref';
                    input.placeholder = 'Reference';
                    input.className = 'input w-full mt-2 rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 p-3';
                    content.appendChild(input);
                    if (batchesApi) {
                        batchesApi.detach();
                        batchesApi = null;
                    }
                }

                function renderBatch() {
                    content.innerHTML = '';
                    batchesApi = buildBatchesUI(content);
                    batchesApi.attach();
                }

                renderRef();
                modeWrap.querySelectorAll('input[name="mh_mode"]').forEach(r => {
                    r.addEventListener('change', (e) => e.target.value === 'ref' ? renderRef() : renderBatch());
                });

                window.dispatchEvent(new Event('modal:normal'));
                return;
            } else if (supplier.name == 'TBO Car' || supplier.name == 'TBO Air' || isHotel) {
                const batches = [];
                let active = 0;

                const toolbar = document.createElement('div');
                toolbar.className = 'sticky top-0 bg-white px-0 pt-1 pb-2';
                toolbar.innerHTML = `
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-800 leading-5">Upload by batches</p>
                            <p class="text-xs text-gray-500">Select your files. Each batch will be merged.</p>
                        </div>
                        <div class="flex items-center">
                            <button type="button" id="add-batch" class="p-2 inline-flex items-center rounded-md border border-gray-300 text-xs font-medium hover:bg-gray-50">
                                Add Batch
                            </button>
                        </div>
                    </div>
                `;
                formTaskContainer.appendChild(toolbar);

                const carousel = document.createElement('div');
                carousel.className = 'relative';
                carousel.innerHTML = `
                    <div id="batch-viewport" class="overflow-hidden">
                    <div id="batch-track" class="flex transition-transform duration-300 ease-out"></div>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                    <div class="inline-flex gap-1">
                        <button type="button" id="prev"
                        class="rounded-md border px-2 py-1 text-xs hover:bg-gray-50">‹ Prev</button>
                        <button type="button" id="next"
                        class="rounded-md border px-2 py-1 text-xs hover:bg-gray-50">Next ›</button>
                    </div>
                    <div id="dots" class="flex items-center gap-1"></div>
                    </div>
                `;
                formTaskContainer.appendChild(carousel);

                const track = carousel.querySelector('#batch-track');
                const dots = carousel.querySelector('#dots');
                const addBtn = toolbar.querySelector('#add-batch');
                const prevBtn = carousel.querySelector('#prev');
                const nextBtn = carousel.querySelector('#next');

                function goTo(i) {
                    if (batches.length === 0) return;
                    active = Math.max(0, Math.min(i, batches.length - 1));
                    track.style.transform = `translateX(-${active * 100}%)`;
                    renderDots();
                }

                function renderDots() {
                    dots.innerHTML = '';
                    batches.forEach((_, i) => {
                        const dot = document.createElement('button');
                        dot.type = 'button';
                        dot.className = `w-2.5 h-2.5 rounded-full ${i===active?'bg-gray-900':'bg-gray-300'}`
                        dot.addEventListener('click', () => goTo(i));
                        dots.appendChild(dot);
                    });
                    prevBtn.disabled = active === 0;
                    nextBtn.disabled = active === batches.length - 1;
                    prevBtn.classList.toggle('opacity-50', prevBtn.disabled);
                    nextBtn.classList.toggle('opacity-50', nextBtn.disabled);
                }

                function addBatch() {
                    const batchIndex = batches.length;
                    batches.push([]);

                    const slide = document.createElement('div');
                    slide.className = 'w-full shrink-0';
                    slide.innerHTML = `
                        <div class="border rounded-md p-3 bg-white">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold">Batch #${batchIndex + 1}</span>
                                    <span class="text-xs text-gray-500"><span class="count">0</span> files</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="clear text-xs text-red-500 hover:text-red-600">Clear</button>
                                    <button type="button" class="remove text-xs text-gray-500 hover:text-gray-700">Remove</button>
                                </div>
                            </div>
                            <label class="block text-xs text-gray-600 mb-1 name-label">Merged file name (optional)</label>
                            <input type="text" class="name-input w-full border rounded px-2 py-1 text-sm mb-2"
                                placeholder="e.g. TBO_0001.pdf (only for 2+ files)" />
                            <div class="drop flex flex-col items-center justify-center border-2 border-dashed border-gray-300
                                        rounded-md text-center cursor-pointer bg-white hover:bg-gray-50 transition
                                        text-sm text-gray-500 mb-2 p-4">
                                <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 64 64" fill="none" stroke="#5d5d5d" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 48H16C9.37258 48 4 42.6274 4 36C4 29.7926 8.79161 24.6465 14.9268 24.0438C17.3056 16.5436 24.2807 11 32.5 11C42.165 11 50 18.835 50 28.5C50 29.6813 49.8904 30.8323 49.6816 31.9425C55.0597 33.3639 59 38.2443 59 44C59 50.6274 53.6274 56 47 56H44"/>
                                    <path d="M32 38V20" />
                                    <path d="M24 28L32 20L40 28" />
                                </svg>
                                <p class="font-medium text-gray-700 mt-1">Click or drag PDF(s) here to upload</p>
                                <p class="text-xs text-gray-500">Multiple PDFs supported</p>
                            </div>
                            <input type="file" class="file hidden" accept="application/pdf" multiple />
                            <div class="files hidden text-sm text-gray-700 border border-gray-200 rounded p-2 bg-white max-h-[160px] overflow-y-auto">
                            </div>
                            <p class="hint text-xs mt-2"></p>
                        </div>
                    `;

                    const drop = slide.querySelector('.drop');
                    const fileInput = slide.querySelector('.file');
                    const filesBox = slide.querySelector('.files');
                    const countEl = slide.querySelector('.count');
                    const hint = slide.querySelector('.hint');
                    const nameInput = slide.querySelector('.name-input');
                    const nameLabel = slide.querySelector('.name-label');

                    function updateUI() {
                        const count = batches[batchIndex].length;
                        countEl.textContent = batches[batchIndex].length;
                        filesBox.innerHTML = '';
                        if (batches[batchIndex].length === 0) {
                            filesBox.classList.add('hidden');
                        } else {
                            filesBox.classList.remove('hidden');
                            batches[batchIndex].forEach((f, i) => {
                                const row = document.createElement('div');
                                row.className = 'bg-gray-100 rounded px-3 py-1 mb-1 flex items-center justify-between';
                                row.innerHTML = `
                                    <span class="truncate text-xs max-w-[220px]">${f.name}</span>
                                    <button type="button" class="rm text-xs text-red-500 hover:text-red-600">✕</button>
                                `;
                                row.querySelector('.rm').addEventListener('click', () => {
                                    batches[batchIndex].splice(i, 1);
                                    updateUI();
                                });
                                filesBox.appendChild(row);
                            });
                        }
                        if (batches[batchIndex].length >= 2) {
                            hint.textContent = 'Ready to merge';
                            hint.className = 'hint text-xs mt-2 text-green-600';
                        } else {
                            hint.textContent = 'Need at least 2 PDFs in this batch';
                            hint.className = 'hint text-xs mt-2 text-amber-600';
                        }
                        if (count >= 1) {
                            hint.textContent = count === 1 ?
                                'Ready: single file (original name will be used)' :
                                'Ready to merge (you may set a custom merged name)';
                            hint.className = 'hint text-xs mt-2 text-green-600';
                        } else {
                            hint.textContent = 'Add at least 1 PDF to this batch';
                            hint.className = 'hint text-xs mt-2 text-amber-600';
                        }
                        const showName = count >= 2;
                        nameInput.classList.toggle('hidden', !showName);
                        nameLabel.classList.toggle('hidden', !showName);
                        nameInput.disabled = !showName;
                    }

                    ['dragenter', 'dragover'].forEach(evt =>
                        drop.addEventListener(evt, (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            drop.classList.add('border-blue-400', 'bg-blue-50');
                        })
                    );
                    ['dragleave', 'drop'].forEach(evt =>
                        drop.addEventListener(evt, (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            drop.classList.remove('border-blue-400', 'bg-blue-50');
                        })
                    );

                    drop.addEventListener('click', () => fileInput.click());
                    drop.addEventListener('drop', (e) => {
                        const newFiles = Array.from(e.dataTransfer.files).filter(f => f.type === 'application/pdf');
                        batches[batchIndex].push(...newFiles);
                        updateUI();
                    });
                    fileInput.addEventListener('change', function() {
                        const picked = Array.from(this.files).filter(f => f.type === 'application/pdf');
                        batches[batchIndex].push(...picked);
                        this.value = '';
                        updateUI();
                    });

                    slide.querySelector('.clear').addEventListener('click', () => {
                        batches[batchIndex] = [];
                        updateUI();
                    });

                    slide.querySelector('.remove').addEventListener('click', () => {
                        const wasActive = active === batchIndex;
                        batches.splice(batchIndex, 1);
                        slide.remove();
                        Array.from(track.children).forEach((c, i) => {
                            const label = c.querySelector('span.font-semibold');
                            if (label) label.textContent = `Batch #${i + 1}`;
                        });
                        if (batches.length === 0) {
                            active = 0;
                        } else if (wasActive && active > 0) {
                            active -= 1;
                        }
                        goTo(active);
                    });

                    track.appendChild(slide);
                    const syncWidths = () => {
                        const vw = carousel.querySelector('#batch-viewport').clientWidth;
                        slide.style.width = vw + 'px';
                        Array.from(track.children).forEach(s => s.style.width = vw + 'px');
                    };
                    syncWidths();
                    window.addEventListener('resize', syncWidths);
                    goTo(batches.length - 1);
                    updateUI();
                }

                addBtn.addEventListener('click', addBatch);
                prevBtn.addEventListener('click', () => goTo(active - 1));
                nextBtn.addEventListener('click', () => goTo(active + 1));

                addBatch();

                const onSubmit = (e) => {
                    Array.from(form.querySelectorAll('input[data-synth="1"]')).forEach(el => el.remove());
                    const invalid = [];
                    const slides = Array.from(track.children);

                    batches.forEach((files, i) => {
                        if (files.length < 1) invalid.push(i + 1);

                        const dt = new DataTransfer();
                        files.forEach(f => dt.items.add(f));

                        const hidden = document.createElement('input');
                        hidden.type = 'file';
                        hidden.multiple = true;
                        hidden.name = `batches[${i}][]`;
                        hidden.files = dt.files;
                        hidden.setAttribute('data-synth', '1');
                        hidden.style.display = 'none';
                        form.appendChild(hidden);

                        const nameHidden = document.createElement('input');
                        nameHidden.type = 'hidden';
                        nameHidden.name = `batch_names[${i}]`;
                        nameHidden.value = (slides[i]?.querySelector('.name-input')?.value || '').trim();
                        nameHidden.setAttribute('data-synth', '1');
                        form.appendChild(nameHidden);
                    });

                    if (batches.length === 0) {
                        e.preventDefault();
                        alert('Please add at least one batch (min 1 PDF).');
                        return;
                    }
                    if (invalid.length) {
                        e.preventDefault();
                        alert(`Each batch must have at least 1 PDF.\nCheck batch(es): ${invalid.join(', ')}.`);
                        return;
                    }
                };

                form.removeEventListener('submit', form._tboSubmitHandler || (() => {}));
                form._tboSubmitHandler = onSubmit;
                form.addEventListener('submit', onSubmit);

                window.dispatchEvent(new Event('modal:normal'));
                return;
            } else {
                const customFiles = [];

                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.multiple = true;
                fileInput.classList.add('hidden');

                const dropZone = document.createElement('div');
                dropZone.classList.add('flex', 'flex-col', 'items-center', 'justify-center', 'border-2', 'border-dashed', 'border-gray-300', 'rounded-md', 'text-center', 'cursor-pointer', 'bg-white', 'hover:bg-gray-50', 'transition', 'duration-150', 'ease-in-out', 'text-sm', 'text-gray-500', 'mb-2', 'p-3', 'sm:p-4');
                dropZone.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 64 64" fill="none" stroke="#5d5d5d" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 48H16C9.37258 48 4 42.6274 4 36C4 29.7926 8.79161 24.6465 14.9268 24.0438C17.3056 16.5436 24.2807 11 32.5 11C42.165 11 50 18.835 50 28.5C50 29.6813 49.8904 30.8323 49.6816 31.9425C55.0597 33.3639 59 38.2443 59 44C59 50.6274 53.6274 56 47 56H44"/>
                    <path d="M32 38V20" />
                    <path d="M24 28L32 20L40 28" />
                    </svg>
                    <p class="font-medium text-gray-700 mt-1">Click or drag files here to upload</p>
                    <p class="text-xs text-gray-500">Multiple files supported</p>
                `;

                const fileListDisplay = document.createElement('div');
                fileListDisplay.id = 'file-list';
                fileListDisplay.classList.add('text-sm', 'text-gray-700', 'border', 'border-gray-200', 'rounded', 'p-2', 'bg-white', 'max-h-[250px]', 'overflow-y-auto', 'hidden');

                ['dragenter', 'dragover'].forEach(eventName => {
                    dropZone.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropZone.classList.add('border-blue-400', 'bg-blue-50');
                    });
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropZone.classList.remove('border-blue-400', 'bg-blue-50');
                    });
                });

                dropZone.addEventListener('drop', (e) => {
                    const droppedFiles = Array.from(e.dataTransfer.files);
                    customFiles.push(...droppedFiles);
                    renderFileList();
                });

                dropZone.addEventListener('click', () => fileInput.click());

                fileInput.addEventListener('change', function() {
                    customFiles.push(...Array.from(this.files));
                    renderFileList();
                    this.value = '';

                    window.__resetTaskForm = function() {
                        if (typeof customFiles !== 'undefined') {
                            customFiles.length = 0;
                        }
                    };
                });

                function renderFileList() {
                    fileListDisplay.innerHTML = '';

                    if (customFiles.length === 0) {
                        fileListDisplay.classList.add('hidden');
                        return;
                    }

                    fileListDisplay.classList.remove('hidden');
                    const wrapper = document.createElement('div');
                    wrapper.classList.add('grid', 'grid-cols-2', 'gap-2', 'overflow-y-auto', 'max-h-[200px]', 'pr-1');

                    customFiles.forEach((file, index) => {
                        const container = document.createElement('div');
                        container.classList.add('bg-gray-100', 'rounded', 'px-3', 'py-1', 'flex', 'items-center', 'justify-between');

                        const name = document.createElement('span');
                        name.textContent = file.name;
                        name.classList.add('truncate', 'text-xs', 'max-w-[120px]');

                        const removeBtn = document.createElement('button');
                        removeBtn.textContent = '✕';
                        removeBtn.classList.add('text-red-400', 'hover:text-red-600', 'text-xs', 'ml-2');
                        removeBtn.addEventListener('click', () => {
                            customFiles.splice(index, 1);
                            renderFileList();
                        });
                        container.appendChild(name);
                        container.appendChild(removeBtn);
                        wrapper.appendChild(container);
                    });
                    fileListDisplay.appendChild(wrapper);
                }

                const form = document.getElementById('agent-supplier-task');
                form.addEventListener('submit', function() {
                    const oldHiddenInput = document.getElementById('task-upload');
                    if (oldHiddenInput) oldHiddenInput.remove();

                    const dataTransfer = new DataTransfer();
                    customFiles.forEach(file => dataTransfer.items.add(file));

                    const hiddenFileInput = document.createElement('input');
                    hiddenFileInput.type = 'file';
                    hiddenFileInput.name = 'task_file[]';
                    hiddenFileInput.multiple = true;
                    hiddenFileInput.id = 'task-upload';
                    hiddenFileInput.files = dataTransfer.files;

                    hiddenFileInput.style.display = 'none';
                    form.appendChild(hiddenFileInput);
                });

                formTaskContainer.appendChild(dropZone);
                formTaskContainer.appendChild(fileInput);
                formTaskContainer.appendChild(fileListDisplay);
                window.dispatchEvent(new Event('modal:normal'));
            }
        });

        // Toggle task status
        document.querySelectorAll('.toggle-task-status').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const taskId = this.getAttribute('data-task-id');
                const isEnabled = this.checked;
                const url = "{{ route('tasks.toggleStatus', ':taskId') }}".replace(':taskId',
                    taskId);

                fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector(
                                'meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            is_enabled: isEnabled
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Task status updated successfully');

                            // Find the task row using its data-task-id
                            const taskRow = document.querySelector(
                                `.taskRow[data-task-id="${taskId}"]`);
                            if (!taskRow) return;

                            // Update the task row's data-status to reflect the new status
                            taskRow.setAttribute('data-status', data.task_status);


                        } else {
                            alert(data.message || 'Failed to update task status');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    }).finally(() => {
                        // Re-enable the checkbox after the request completes
                        location.reload(); // Reload the page to reflect changes
                    });

            });
        });

        document.querySelectorAll('.client-select').forEach(select => {
            select.addEventListener('change', function() {
                const clientId = this.value;
                const taskId = this.dataset.taskId;
                const agentSelect = document.getElementById(`agent_id_select_${taskId}`);
                const agentHidden = document.getElementById(`agent_id_hidden_${taskId}`);
                const getAgentUrl = "{{ route('clients.get-agent', ':clientId') }}".replace(':clientId',
                    clientId);

                if (clientId) {
                    fetch(getAgentUrl)
                        .then(response => response.json())
                        .then(data => {
                            if (data.agent) {
                                agentSelect.value = data.agent.id;
                                agentHidden.value = data.agent.id;
                            } else {
                                agentSelect.value = '';
                                agentHidden.value = '';
                                alert('No agent assigned to this client.');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            agentSelect.value = '';
                            agentHidden.value = '';
                        });
                } else {
                    agentSelect.value = '';
                    agentHidden.value = '';
                }
            });
        });

        document.addEventListener("alpine:init", () => {
            Alpine.data("clientModal", () => ({
                showUploadField: false,

                toggleUpload() {
                    this.showUploadField = !this.showUploadField;
                },


            }));
        });
    });

    const clientForm = document.getElementById("client-formTask");

    const file = document.getElementById('file-task-passport');
    const fileName = document.getElementById('task-passport-file-name');
    const taskPassportProcessBtn = document.getElementById('task-passport-process-btn');

    if (file && fileName && taskPassportProcessBtn) {
        file.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        taskPassportProcessBtn.addEventListener('click', (e) => {
            e.preventDefault();
            processFileWithAI();
        });
    } else {
        console.warn("Required elements not found: file, fileName, or taskPassportProcessBtn");
    }

    file.addEventListener('change', (e) => {
        fileName.textContent = e.target.files[0].name;
        file.innerHTML = '';
        let img = document.createElement('img');
        img.src = URL.createObjectURL(e.target.files[0]);
        console.log(img.src);
        img.width = 100;
        img.height = 100;
        file.appendChild(img);

        enableButton(taskPassportProcessBtn);
    });

    dropHandler = (e) => {
        e.preventDefault();

        const droppedFile = e.dataTransfer.files[0];
        if (!droppedFile) return;

        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(droppedFile);
        file.files = dataTransfer.files;

        fileName.textContent = droppedFile.name;

        if (droppedFile.type.startsWith('image/')) {
            file.innerHTML = '';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(droppedFile);
            img.width = 100;
            img.height = 100;
            file.appendChild(img);
        }

        enableButton(taskPassportProcessBtn);
    };

    dragOverHandler = (e) => {
        console.log('File in drop area');
        e.preventDefault();
    }

    function processFileWithAI() {
        const fileInput = document.getElementById('file-task-passport');
        const processBtn = document.getElementById('task-passport-process-btn');
        if (fileInput.files.length === 0) {
            alert('Please upload a file first.');
            return;
        }

        // Show loading indication and disable button
        processBtn.disabled = true;
        processBtn.textContent = 'Processing...';
        processBtn.classList.add('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);

        fetch("{{ route('tasks.upload.passport') }}", {
                method: "POST",
                body: formData,
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const client = data.data;
                    console.log("Extracted client data:", client);

                    const nameInput = document.getElementById('nameTask');
                    if (nameInput) nameInput.value = client.name || '';

                    const middleNameInput = document.getElementById('middleNameTask');
                    if (middleNameInput) middleNameInput.value = client.middle_name || '';

                    const lastNameInput = document.getElementById('lastNameTask');
                    if (lastNameInput) lastNameInput.value = client.last_name || '';

                    const passportInput = document.getElementById('passport_noTask');
                    if (passportInput) passportInput.value = client.passport_no || '';

                    const civilInput = document.getElementById('civil_noTask');
                    if (civilInput) civilInput.value = client.civil_no || '';

                    const addressInput = document.getElementById('addressTask');
                    if (addressInput) addressInput.value = client.address || '';

                    const dobInput = document.querySelector('input[name="date_of_birthTask"]');
                    if (dobInput && client.date_of_birth) {
                        dobInput.value = client.date_of_birth.replace(/\//g, '-');
                    }
                    // Handle the response data as needed
                } else {
                    alert('Error processing file: ' + data.message);
                    console.error('Error:', data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the file.');
            })
            .finally(() => {
                // Restore button state
                processBtn.disabled = false;
                processBtn.textContent = 'Process File';
                processBtn.classList.remove('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');
            });
    }

    function disableButton(button) {
        console.log('Disabling button:', button);
        if (!button.classList.contains('cursor-not-allowed') && !button.classList.contains('opacity-50')) {
            button.classList.add('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');
        }
        button.disabled = true;
    }

    function enableButton(button) {
        console.log('Enabling button:', button);
        if (button.classList.contains('cursor-not-allowed') || button.classList.contains('opacity-50')) {
            button.classList.remove('cursor-not-allowed', 'opacity-50', 'bg-gray-300', 'text-gray-500');
        }
        button.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white', 'font-semibold', 'py-2', 'rounded-full',
            'text-sm', 'transition', 'duration-150');
        button.disabled = false;
    }
</script>

<script>
    const filterConfig = {
        columns: {
            reference: {
                label: "Reference",
                type: "text"
            },
            "bill-to": {
                label: "Bill To",
                type: "text"
            },
            "passenger-name": {
                label: "Passenger Name",
                type: "text"
            },
            agent_name: {
                label: "Agent Name",
                type: "text"
            },
            status: {
                label: "Status",
                type: "select",
                options: ["-- Select --", "issued", "refund", "reissued", "void", "ticketed", "confirmed", "emd"]
            },
            supplier: {
                label: "Supplier",
                type: "searchable",
                options: window.companySuppliers || []
            },
            "created-at": {
                label: "Created Date",
                type: "date-range"
            },
            "supplier_pay_date": {
                label: "Issued Date",
                type: "date-range"
            },
            "cancellation-deadline": {
                label: "Cancellation Deadline",
                type: "date"
            },
            type: {
                label: "Type",
                type: "select",
                options: ["-- Select --"].concat(
                    Object.entries(window.allTaskTypes || {}).map(([key, label]) => label)
                )
            },
            "amadeus-reference": {
                label: "Amadeus Reference",
                type: "text"
            },
            "created-by": {
                label: "Created By",
                type: "text"
            },
            "issued-by": {
                label: "Issued By",
                type: "text"
            },
            "branch-name": {
                label: "Branch Name",
                type: "text"
            },
            invoice: {
                label: "Invoice",
                type: "text"
            },
        }
    };

    let filterRows = [];

    // function renderFilterRows() {
    //     const container = document.getElementById('filterContainer');
    //     container.innerHTML = '';
    //     filterRows.forEach((row, idx) => {
    //         const col = filterConfig.columns[row.column];
    //         let inputHtml = '';
    //         if (col.type === 'text') {
    //             inputHtml = `<input type="text" class="value-input" value="${row.value || ''}" placeholder="Enter value" data-idx="${idx}">`;
    //         } else if (col.type === 'select') {
    //             inputHtml = `<select class="value-input" data-idx="${idx}">${col.options.map(opt =>
    //                 `<option value="${opt}" ${row.value === opt ? 'selected' : ''}>${opt}</option>`
    //             ).join('')}</select>`;
    //         } else if (col.type === 'searchable') {
    //             inputHtml = `<input type="text" class="value-input" list="datalist-${row.column}-${idx}" value="${row.value || ''}" placeholder="Search..." data-idx="${idx}">
    //                 <datalist id="datalist-${row.column}-${idx}">
    //                     ${col.options.map(opt => `<option value="${opt}"></option>`).join('')}
    //                 </datalist>`;
    //         } else if (col.type === 'date') {
    //             inputHtml = `<input type="date" class="value-input" value="${row.value || ''}" data-idx="${idx}">`;
    //         } else if (col.type === 'date-range') {
    //             const [start, end] = (row.value || '').split(' to ');
    //             inputHtml = `
    //                 <input type="date" class="value-input" value="${start || ''}" data-idx="${idx}" data-part="start">
    //                 to
    //                 <input type="date" class="value-input" value="${end || ''}" data-idx="${idx}" data-part="end">
    //             `;
    //         }
    //         container.innerHTML += `
    //             <div class="filter-row">
    //                 <select class="column-select" data-idx="${idx}">
    //                     ${Object.entries(filterConfig.columns).map(([key, c]) =>
    //                         `<option value="${key}" ${row.column === key ? 'selected' : ''}>${c.label}</option>`
    //                     ).join('')}
    //                 </select>
    //                 ${inputHtml}
    //                 <button type="button" class="remove-filter-btn" data-idx="${idx}">&times;</button>
    //             </div>
    //         `;
    //     });
    // }
    function renderActiveFilters() {
        const filters = getActiveFiltersFromURL();
        const container = document.getElementById('activeFiltersContainer');
        const list = document.getElementById('activeFiltersList');
        list.innerHTML = '';
        if (filters.length === 0) {
            list.innerHTML = `<span class="text-gray-400 text-sm">No active filters</span>`;
            return;
        }
        container.style.display = '';
        filters.forEach(f => {
            const tag = document.createElement('div');
            tag.className = 'active-filter-tag';
            tag.innerHTML = `
            <span>${f.label}: <b>${f.value}</b></span>
            <button class="remove-tag" data-key="${f.key}" data-value="${f.value}" title="Remove filter">&times;</button>
        `;
            list.appendChild(tag);
        });
    }
    // Open modal
    document.getElementById('toggleFilters').onclick = () => {
        document.getElementById('filterModal').classList.add('active');
        if (filterRows.length === 0) {
            filterRows.push({
                column: Object.keys(filterConfig.columns)[0],
                value: ''
            });
        }
        renderFilterRows();
    };
    // Close modal
    document.getElementById('closeFilterModal').onclick = () => {
        document.getElementById('filterModal').classList.remove('active');
    };
    // Add filter row
    document.getElementById('addFilterRow').onclick = () => {
        filterRows.push({
            column: Object.keys(filterConfig.columns)[0],
            value: ''
        });
        renderFilterRows();
    };

    document.getElementById('editActiveFilters').addEventListener('click', function() {
        const params = new URLSearchParams(window.location.search);
        filterRows = [];

        // Handle date-range fields
        Object.entries(filterConfig.columns).forEach(([key, col]) => {
            if (col.type === 'date-range') {
                const from = params.get(`${key}_from`);
                const to = params.get(`${key}_to`);
                if (from || to) {
                    filterRows.push({
                        column: key,
                        value: {
                            from: from || '',
                            to: to || ''
                        }
                    });
                }
            }
        });

        // Handle status[] and status (avoid duplicates)
        const statusValues = [
            ...params.getAll('status[]'),
            ...params.getAll('status'),
            ...Array.from(params.keys())
            .filter(k => k.startsWith('status['))
            .map(k => params.get(k))
        ].filter((v, i, arr) => arr.indexOf(v) === i); // Remove duplicates

        statusValues.forEach(val => {
            filterRows.push({
                column: 'status',
                value: val
            });
        });

        // Handle other filters (skip status/status[])
        for (const [key, value] of params.entries()) {
            if (
                Object.keys(filterConfig.columns).includes(key) &&
                filterConfig.columns[key].type !== 'date-range' &&
                key !== 'status' && key !== 'status[]'
            ) {
                filterRows.push({
                    column: key,
                    value
                });
            }
        }

        if (filterRows.length === 0) {
            filterRows.push({
                column: Object.keys(filterConfig.columns)[0],
                value: ''
            });
        }
        renderFilterRows();
        document.getElementById('filterModal').classList.add('active');
    });
    // Remove row or update value
    document.getElementById('filterContainer').addEventListener('input', function(e) {
        const idx = +e.target.dataset.idx;
        const row = filterRows[idx];
        const col = filterConfig.columns[row.column];
        if (col && col.type === 'date-range') {
            if (typeof row.value !== 'object' || !row.value) row.value = {
                from: '',
                to: ''
            };
            if (e.target.dataset.range === 'from') row.value.from = e.target.value;
            if (e.target.dataset.range === 'to') row.value.to = e.target.value;
        } else if (e.target.classList.contains('value-input')) {
            filterRows[idx].value = e.target.value;
        }
    });
    document.getElementById('filterContainer').addEventListener('change', function(e) {
        const idx = +e.target.dataset.idx;
        if (e.target.classList.contains('column-select')) {
            filterRows[idx].column = e.target.value;
            filterRows[idx].value = '';
            renderFilterRows();
        }
    });
    document.getElementById('filterContainer').addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-filter-btn')) {
            const idx = +e.target.dataset.idx;
            filterRows.splice(idx, 1);
            renderFilterRows();

            // Auto-apply filters after removing a row
            const params = new URLSearchParams(window.location.search);
            // Remove old filter params
            for (const key of Array.from(params.keys())) {
                if (
                    Object.keys(filterConfig.columns).includes(key) ||
                    key === 'status' ||
                    key === 'status[]' ||
                    key.startsWith('status[') // <-- Add this line!
                ) params.delete(key);
                if (key.endsWith('_from') || key.endsWith('_to')) params.delete(key);
            }
            // Always append as status[]
            const statusValues = filterRows.filter(row => row.column === 'status').map(row => row.value);
            statusValues.forEach(val => params.append('status[]', val));
            filterRows.forEach(row => {
                if (!row.value || row.column === 'status') return;
                const col = filterConfig.columns[row.column];
                if (col && col.type === 'date-range') {
                    if (row.value.from) params.append(`${row.column}_from`, row.value.from);
                    if (row.value.to) params.append(`${row.column}_to`, row.value.to);
                } else {
                    params.append(`${row.column}[]`, row.value);
                }
            });
            resetPagination(params);
            window.location = `{{ route('tasks.index') }}?${params.toString()}`;
        }
    });
    // Apply filters
    document.getElementById('applyFilters').onclick = () => {
        const params = new URLSearchParams(window.location.search);
        // Remove old filter params
        for (const key of Array.from(params.keys())) {
            if (Object.keys(filterConfig.columns).includes(key) || key === 'status' || key === 'status[]') params.delete(key);
            if (key.endsWith('_from') || key.endsWith('_to')) params.delete(key);
        }
        // Always append as status[]
        const statusValues = filterRows.filter(row => row.column === 'status').map(row => row.value);
        statusValues.forEach(val => params.append('status[]', val));
        filterRows.forEach(row => {
            if (!row.value || row.column === 'status') return;
            const col = filterConfig.columns[row.column];
            if (col && col.type === 'date-range') {
                if (row.value.from) params.append(`${row.column}_from`, row.value.from);
                if (row.value.to) params.append(`${row.column}_to`, row.value.to);
            } else {
                params.append(`${row.column}[]`, row.value);
            }
        });
        resetPagination(params);
        window.location = `{{ route('tasks.index') }}?${params.toString()}`;
    };

    function resetPagination(params) {
        params.delete('page');
    }

    function getActiveFiltersFromURL() {
        const params = new URLSearchParams(window.location.search);
        const filters = [];
        // Handle date-range fields
        Object.entries(filterConfig.columns).forEach(([key, col]) => {
            if (col.type === 'date-range') {
                const from = params.get(`${key}_from`);
                const to = params.get(`${key}_to`);
                if (from || to) {
                    let value = '';
                    if (from && to) value = `${from} to ${to}`;
                    else if (from) value = `from ${from}`;
                    else if (to) value = `to ${to}`;
                    filters.push({
                        key,
                        label: col.label,
                        value
                    });
                }
            }
        });

        // Collect all status values (avoid duplicates)
        const statusValues = [
            ...params.getAll('status[]'),
            ...params.getAll('status'),
            ...Array.from(params.keys())
            .filter(k => k.startsWith('status['))
            .map(k => params.get(k))
        ].filter((v, i, arr) => arr.indexOf(v) === i);

        statusValues.forEach(val => {
            filters.push({
                key: 'status',
                label: filterConfig.columns['status'].label,
                value: val
            });
        });

        // Handle other filters (including array filters)
        Object.entries(filterConfig.columns).forEach(([key, col]) => {
            if (col.type !== 'date-range' && key !== 'status') {
                // Get all values for this key (array or single)
                const values = [
                    ...params.getAll(`${key}[]`),
                    ...params.getAll(key)
                ].filter((v, i, arr) => arr.indexOf(v) === i);

                values.forEach(val => {
                    if (val !== '') {
                        filters.push({
                            key,
                            label: col.label,
                            value: val
                        });
                    }
                });
            }
        });

        return filters;
    }

    function renderFilterRows() {
        const container = document.getElementById('filterContainer');
        container.innerHTML = '';
        filterRows.forEach((row, idx) => {
            const col = filterConfig.columns[row.column];
            let inputHtml = '';
            if (col.type === 'text') {
                inputHtml = `<input type="text" class="value-input" value="${row.value || ''}" placeholder="Enter value" data-idx="${idx}">`;
            } else if (col.type === 'select') {
                inputHtml = `<select class="value-input" data-idx="${idx}">${col.options.map(opt =>
                `<option value="${opt}" ${row.value === opt ? 'selected' : ''}>${opt}</option>`
            ).join('')}</select>`;
            } else if (col.type === 'searchable') {
                inputHtml = `<input type="text" class="value-input" list="datalist-${row.column}-${idx}" value="${row.value || ''}" placeholder="Search..." data-idx="${idx}">
                <datalist id="datalist-${row.column}-${idx}">
                    ${col.options.map(opt => `<option value="${opt}"></option>`).join('')}
                </datalist>`;
            } else if (col.type === 'date') {
                inputHtml = `<input type="date" class="value-input" value="${row.value || ''}" data-idx="${idx}">`;
            } else if (col.type === 'date-range') {
                inputHtml = `
                <input type="text" id="tasks-date-range-${idx}" class="value-input" placeholder="Select date range" style="width: 90%;" data-idx="${idx}" />
            `;
            } else if (col.type === 'multi-text') {
                inputHtml = `<input type="text" class="value-input" value="${row.value || ''}" placeholder="Enter a Ref" data-idx="${idx}">`;
            }
            container.innerHTML += `
            <div class="filter-row">
                <select class="column-select" data-idx="${idx}">
                    ${Object.entries(filterConfig.columns).map(([key, c]) =>
                        `<option value="${key}" ${row.column === key ? 'selected' : ''}>${c.label}</option>`
                    ).join('')}
                </select>
                ${inputHtml}
                <button type="button" class="remove-filter-btn" data-idx="${idx}">&times;</button>
            </div>
        `;
        });

        // Initialize Flatpickr for all date-range inputs
        filterRows.forEach((row, idx) => {
            const col = filterConfig.columns[row.column];
            if (col.type === 'date-range') {
                const input = document.getElementById(`tasks-date-range-${idx}`);
                if (input) {
                    flatpickr(input, {
                        mode: "range",
                        dateFormat: "Y-m-d",
                        defaultDate: row.value && row.value.from ? [row.value.from, row.value.to] : undefined,
                        onChange: function(selectedDates, dateStr) {
                            // Save value as {from, to}
                            const dates = dateStr.split(' to ');
                            filterRows[idx].value = {
                                from: dates[0] || '',
                                to: dates[1] || ''
                            };
                        }
                    });
                }
            }
        });
    }
    document.addEventListener("DOMContentLoaded", function() {
        flatpickr("#tasks-date-range", {
            mode: "range",
            dateFormat: "Y-m-d",
            // Optionally set default dates or other options
        });
    });
    // Remove individual filter
    document.getElementById('activeFiltersList').addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-tag')) {
            const key = e.target.getAttribute('data-key');
            const value = e.target.getAttribute('data-value');
            const params = new URLSearchParams(window.location.search);

            // Handle date-range fields
            if (filterConfig.columns[key] && filterConfig.columns[key].type === 'date-range') {
                params.delete(`${key}_from`);
                params.delete(`${key}_to`);
            } else if (key === 'status') {
                // Remove all status[] and status[n] with this value
                ['status[]', 'status'].forEach(k => {
                    const values = params.getAll(k);
                    params.delete(k);
                    values.forEach(v => {
                        if (v !== value) params.append(k, v);
                    });
                });
                // Also handle status[0], status[1], etc.
                Array.from(params.keys())
                    .filter(k => k.startsWith('status['))
                    .forEach(k => {
                        if (params.get(k) === value) {
                            params.delete(k);
                        }
                    });
            } else {
                // Remove only the selected value for array filters
                const arrKey = `${key}[]`;
                const values = params.getAll(arrKey).length ? params.getAll(arrKey) : params.getAll(key);
                params.delete(arrKey);
                params.delete(key);
                values.forEach(v => {
                    if (v !== value) params.append(arrKey, v);
                });
            }
            resetPagination(params);
            window.location = `{{ route('tasks.index') }}?${params.toString()}`;
        }
    });
    document.getElementById('clearAllActiveFilters').addEventListener('click', function() {
        const params = new URLSearchParams(window.location.search);
        Object.keys(filterConfig.columns).forEach(key => params.delete(key));
        params.delete('status');
        params.delete('status[]');
        window.location = `{{ route('tasks.index') }}`;
    });
    document.getElementById('clearAllActiveFilters2').addEventListener('click', function() {
        const params = new URLSearchParams(window.location.search);
        Object.keys(filterConfig.columns).forEach(key => params.delete(key));
        params.delete('status');
        params.delete('status[]');
        window.location = `{{ route('tasks.index') }}`;
    });
    // Render on page load
    renderActiveFilters();
</script>