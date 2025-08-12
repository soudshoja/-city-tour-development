<x-app-layout>

    <head>
        <meta name="csrf-token" content="{{ csrf_token() }}">
    </head>
    <style>
        #myTable > thead > tr > th:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            background-color: #f9fafb;
        }

        #myTable > tbody > tr > td:first-child {
            position: -webkit-sticky;
            position: sticky;
            left: 0;
            z-index: 1;
            background-color: inherit;
            transition: background-color 0.2s;
        }

        #myTable > thead > tr > th:first-child,
        #myTable > tbody > tr > td:first-child {
            box-shadow: 5px 0 5px -5px rgba(0, 0, 0, 0.1);
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

        .task-row.not-selectable {
            cursor: default;
        }

        .task-row.selected {
            background-color: #dbeafe;
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
            <div x-data="{ addTaskModal: false }" class="flex items-center gap-5">
                <div @click="addTaskModal = true"
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
                    <div @click.away="addTaskModal = false" class="bg-white rounded shadow w-96">
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

                            <div class="mb-3">
                                <x-searchable-dropdown name="supplier_id" :items="$suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name])" placeholder="Select Supplier"
                                    label="Select a Supplier" />
                            </div>
                            <!-- Hidden native select (logic only) -->
                            <select id="select-supplier-task" class="hidden">
                                @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" data-supplier='{{ json_encode($supplier) }}'>
                                    {{ $supplier->name }}
                                </option>
                                @endforeach
                            </select>
                            <div id="form-task-container" class="mb-3"></div>

                            @unlessrole('agent')
                            <!-- <div class="mb-4">
                                <x-searchable-dropdown name="agent_id" :items="$agents->map(fn($a) => ['id' => $a->id, 'name' => $a->name])" placeholder="Select Agent"
                                    label="Select an Agent" />
                            </div> -->
                            @else
                            <input type="hidden" name="agent_id" value="{{ Auth()->user()->agent->id }}">
                            @endunlessrole
                        </form>

                        <hr>
                        <div class="p-4 flex justify-between items-center">
                            <button @click="addTaskModal = false"
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
                        placeholder="Quick search for tasks"
                    />
                    <button type="button" id="toggleFilters"
                        class="flex px-3 py-2 gap-2 w-full h-10 md:w-auto justify-center city-light-yellow rounded-full shadow-sm items-center text-xs md:text-sm">
                        <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 32 32">
                            <path fill="#333333"
                                d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3-3-3-3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3-3-3-3s-3-1.3-3-3" />
                        </svg>
                        <span class="text-xs md:text-sm dark:text-black">Filters</span>
                    </button>
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
                                    <input type="checkbox" id="col-issue-date" class="column-checkbox accent-blue-600 rounded-md w-4 h-4" checked>
                                    <label for="col-issue-date" class="text-sm text-gray-700">Issue Date</label>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="col-created-at" class="column-checkbox accent-blue-600 rounded-md w-4 h-4" checked>
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
                            </div>
                        </div>
                    </div>
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
                                    <button id="addFilterRow" class="add-filter-btn">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19">
                                            </line>
                                            <line x1="5" y1="12" x2="19" y2="12">
                                            </line>
                                        </svg>
                                        Add Filter
                                    </button>
                                </div>
                                <div class="flex gap-3">
                                    <button id="clearAllFilters" class="clear-all-filters-btn">Clear All</button>
                                    <button id="applyFilters" class="apply-filters-btn">Apply Filters</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="activeFiltersContainer" class="active-filters" style="display: none;">
                    <div class="flex justify-between items-center mb-3">
                        <h4 class="text-sm font-semibold text-gray-700">Active Filters:</h4>
                        <button id="clearAllActiveFilters" class="text-xs text-red-600 hover:text-red-800 underline">
                            Clear All
                        </button>
                    </div>
                    <div id="activeFiltersList" class="flex flex-wrap">
                        <!-- Active filter tags will be inserted here -->
                    </div>
                </div>

                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <div x-data="{ shown: 10 }">
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
                                                <th data-column="issue-date">
                                                    <a href="{{ request()->fullUrlWithQuery([
                                                                'sortBy' => 'issued_date',
                                                                'sortOrder' => (request('sortBy') === 'issued_date' && request('sortOrder') === 'asc') ? 'desc' : 'asc'
                                                            ]) }}"
                                                        class="flex items-center gap-2 text-left text-md font-bold text-gray-900 dark:text-gray-300 hover:text-gray-700 dark:hover:text-gray-100 cursor-pointer transition-all duration-200">
                                                        Issued Date
                                                        @if(request('sortBy') !== 'issued_date')
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
                                                <th data-column="created-at">
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
                                                                class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 focus:outline-none flex items-center justify-center">
                                                                <svg class="w-5 h-5 text-gray-700" fill="currentColor" viewBox="0 0 24 24">
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
                                                            <template x-teleport="body">
                                                                <div x-show="editOpen" x-cloak class="fixed inset-0 z-[10000] flex items-center justify-center bg-gray-800 bg-opacity-50">
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
                                                                            <div class="flex flex-col gap-6">
                                                                                <div class="flex flex-col sm:flex-row gap-4">
                                                                                    <div class="flex-1">
                                                                                        <label for="reference"
                                                                                            class="block text-sm font-medium text-gray-700">Reference</label>
                                                                                        <input type="text"
                                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-base"
                                                                                            name="reference"
                                                                                            value="{{ $task->reference }}">
                                                                                    </div>
                                                                                    <div class="flex-1">
                                                                                        <label for="status"
                                                                                            class="block text-sm font-medium text-gray-700">Status</label>
                                                                                        @if ($task->status === 'refund')
                                                                                        <select name="status"
                                                                                            id="status"
                                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-base"
                                                                                            disabled>
                                                                                            <option value="refund"
                                                                                                selected>Refund
                                                                                            </option>
                                                                                        </select>
                                                                                        <input type="hidden"
                                                                                            name="status" value="refund">
                                                                                        @else
                                                                                        <select name="status"
                                                                                            id="status"
                                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-base">
                                                                                            <option value="">Set
                                                                                                Status
                                                                                            </option>
                                                                                            <option value="Confirmed"
                                                                                                {{ $task->status === 'confirmed' ? 'selected' : '' }}>
                                                                                                Confirmed
                                                                                            </option>
                                                                                            <option value="Issued"
                                                                                                {{ $task->status === 'issued' ? 'selected' : '' }}>
                                                                                                Issued
                                                                                            </option>
                                                                                            <option value="Reissued"
                                                                                                {{ $task->status === 'reissued' ? 'selected' : '' }}>
                                                                                                Reissued
                                                                                            </option>
                                                                                            <option value="Refund"
                                                                                                {{ $task->status === 'refund' ? 'selected' : '' }}>
                                                                                                Refund
                                                                                            </option>
                                                                                            <option value="Void"
                                                                                                {{ $task->status === 'void' ? 'selected' : '' }}>
                                                                                                Void
                                                                                            </option>
                                                                                        </select>
                                                                                        @endif


                                                                                        @if ($task->status === 'refund')
                                                                                        <input type="hidden"
                                                                                            name="status" value="Refund">
                                                                                        @endif

                                                                                    </div>
                                                                                </div>

                                                                                @if (strtolower($task->status) !== 'issued' && strtolower($task->status) !== 'confirmed'|| $task->status == null)
                                                                                <div class="flex flex-col sm:flex-row gap-4">
                                                                                    <div class="flex-1">
                                                                                        @php
                                                                                        $originalTasks = \App\Models\Task::with('client')
                                                                                        ->where('status', 'issued')
                                                                                        ->where('reference', $task->reference)
                                                                                        ->get();
                                                                                        $selectedOriginalTask = $originalTasks->firstWhere('id', $task->original_task_id);
                                                                                        $taskPlaceholder = $selectedOriginalTask
                                                                                        ? $selectedOriginalTask->reference . ' - ' . ($selectedOriginalTask->client->name ?? $selectedOriginalTask->client_name)
                                                                                        : 'Select Original Task';
                                                                                        @endphp

                                                                                        <label for="original_task_id" class="block text-sm font-medium text-gray-700">Original Task</label>
                                                                                        <x-searchable-dropdown
                                                                                            name="original_task_id"
                                                                                            :items="$originalTasks->map(fn($t) => [
                                                                                                'id' => $t->id,
                                                                                                'name' => $t->reference . ' - ' . ($t->client->name ?? $t->client_name)
                                                                                            ])->values()"
                                                                                            :selectedId="$task->original_task_id"
                                                                                            :selectedName="$selectedOriginalTask
                                                                                                ? $selectedOriginalTask->reference . ' - ' . ($selectedOriginalTask->client->name ?? $selectedOriginalTask->client_name)
                                                                                                : null"
                                                                                            :placeholder="$taskPlaceholder" />
                                                                                    </div>
                                                                                </div>
                                                                                @endif

                                                                                <div class="flex flex-col sm:flex-row gap-4">
                                                                                    <!-- Supplier Name -->
                                                                                    <div class="flex-1">
                                                                                        <label for="supplier"
                                                                                            class="block text-sm font-medium text-gray-700">Supplier</label>
                                                                                        <input type="text"
                                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full bg-gray-200"
                                                                                            value="{{ $task->supplier ? $task->supplier->name : '' }}"
                                                                                            readonly>
                                                                                        <input type="hidden"
                                                                                            name="supplier_id"
                                                                                            id="supplier_id_{{ $task->id }}"
                                                                                            value="{{ $task->supplier ? $task->supplier->id : '' }}">

                                                                                    </div>

                                                                                    <!-- Task Type -->
                                                                                    <div class="flex-1">
                                                                                        <label for="type"
                                                                                            class="block text-sm font-medium text-gray-700">Task
                                                                                            Type</label>
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
                                                                                    ? $selectedClient->name . ' - ' . $selectedClient->phone
                                                                                    : 'Select a Client';
                                                                                    @endphp
                                                                                    <div class="flex-1">
                                                                                        <label for="client_id"
                                                                                            class="block text-sm font-medium text-gray-700">Client</label>
                                                                                        <div class="w-full">
                                                                                            <x-searchable-dropdown
                                                                                                name="client_id"
                                                                                                :items="$clients->map(fn($c) => [
                                                                                                'id'   => $c->id,
                                                                                                'name' => $c->name . ' - ' . $c->phone
                                                                                            ])"
                                                                                                :selectedId="$task->client_id"
                                                                                                :selectedName="$selectedClient ? $selectedClient->name . ' - '  .           $selectedClient->phone : null"
                                                                                                :placeholder="$clientPlaceholder" />
                                                                                        </div>
                                                                                    </div>

                                                                                    <!-- Agent Selection (Role-based) -->
                                                                                    <div class="flex-1">
                                                                                        <label for="agent_id"
                                                                                            class="block text-sm font-medium text-gray-700">Agent</label>
                                                                                        <select
                                                                                            id="agent_id_select_{{ $task->id }}"
                                                                                            name="agent_id"
                                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-base">
                                                                                            <option value=""> Choose
                                                                                                Agent</option>
                                                                                            @foreach ($agents as $agent)
                                                                                            <option
                                                                                                value="{{ $agent->id }}"
                                                                                                {{ $task->agent && $task->agent->id === $agent->id ? 'selected' : '' }}>
                                                                                                {{ $agent->name }}
                                                                                            </option>
                                                                                            @endforeach
                                                                                        </select>
                                                                                    </div>

                                                                                </div>

                                                                                <div class="flex flex-wrap gap-4">
                                                                                    <!-- Price -->
                                                                                    <div class="flex-1 min-w-[150px]">
                                                                                        <label for="price"
                                                                                            class="block text-sm font-medium text-gray-700">Price</label>
                                                                                        <input type="text"
                                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full"
                                                                                            name="price"
                                                                                            placeholder="Price"
                                                                                            value="{{ $task->price }}"
                                                                                            {{$task->task_price_changeable ? '' : 'readonly'}}>
                                                                                    </div>

                                                                                    <!-- Tax -->
                                                                                    <div class="flex-1 min-w-[150px]">
                                                                                        <label for="tax"
                                                                                            class="block text-sm font-medium text-gray-700">Tax</label>
                                                                                        <input type="text"
                                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full"
                                                                                            name="tax"
                                                                                            value="{{ $task->tax }}"
                                                                                            placeholder="Tax">
                                                                                    </div>

                                                                                    <!-- Surcharge -->
                                                                                    <div class="flex-1 min-w-[150px]">
                                                                                        <label for="surcharge"
                                                                                            class="block text-sm font-medium text-gray-700">Surcharge</label>
                                                                                        <input type="text"
                                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full"
                                                                                            name="surcharge"
                                                                                            value="{{ $task->surcharge }}"
                                                                                            placeholder="Surcharge">
                                                                                    </div>

                                                                                    <!-- Total -->
                                                                                    <div class="flex-1 min-w-[150px]">
                                                                                        <label for="total"
                                                                                            class="block text-sm font-medium text-gray-700">Total</label>
                                                                                        <input type="text" name="total"
                                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full"
                                                                                            value="{{ $task->total }}"
                                                                                            placeholder="Total"
                                                                                            {{$task->task_price_changeable ? '' : 'readonly'}}>
                                                                                    </div>
                                                                                </div>
                                                                                <!-- Payment Method -->
                                                                                <div class="flex flex-col sm:flex-row gap-4">
                                                                                    <div class="flex-1">
                                                                                        <label for="payment_method" class="block text-sm font-medium text-gray-700">Payment Method</label>
                                                                                        <select name="payment_method_account_id" id="payment_method_account_id"
                                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full">
                                                                                            <option value="">Select Payment Method</option>
                                                                                            @foreach($paymentMethod as $method)
                                                                                            <option value="{{ $method->id }}" {{ $task->payment_method_account_id == $method->id ? 'selected' : ''}}>{{ $method->name }}</option>
                                                                                            @endforeach
                                                                                        </select>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="flex flex-col sm:flex-row gap-4">
                                                                                    <!-- Additional Info and Venue -->
                                                                                    <div class="flex-1">
                                                                                        <label for="additional_info"
                                                                                            class="block text-sm font-medium text-gray-700">Additional
                                                                                            Info</label>
                                                                                        <textarea rows="3" readonly
                                                                                            class="border border-gray-300 dark:border-gray-600 p-3 rounded-md bg-gray-200 w-full resize-none">{{ $task->additional_info }} - {{ $task->venue }}
                                                                                        </textarea>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="mt-6 flex flex-col sm:flex-row justify-between gap-4">
                                                                                <button type="button" @click="editOpen = false"
                                                                                    class="px-6 py-2 text-gray-700 font-semibold rounded-full bg-gray-200 hover:bg-gray-300 transition">
                                                                                    Cancel
                                                                                </button>
                                                                                <button type="submit"
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
                                                    <p>{{ $task->client->name }}</p>
                                                    <p>{{ $task->client->phone ?? 'No phone' }}</p>
                                                    @else
                                                    <p class="{{ $task->client ?? 'no-client relative' }}">
                                                        <button
                                                            @click.stop="openManualForm({{ $task->id }}, '{{ $task->client_name ?? '' }}', '{{ $task->passenger_name ?? '' }}' ,'{{ $task->agent->name ?? 'Not Set' }}', '{{ $task->agent->id ?? 'Null' }}', '{{ $task->agent->branch->name ?? 'Not Set' }}')"
                                                            {{ $task->client !== null ? 'disabled' : '' }}>
                                                            {{ $task->client->name ?? $task->client_name !== '' ? $task->client_name : 'Not Set' }}
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
                                                <td data-column="issue-date" class="p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->formatted_date ?? 'Not Set' }}
                                                </td>
                                                <td data-column="created-at" class="p-3 text-sm text-center font-semibold text-gray-900 dark:text-gray-300">
                                                    {{ $task->created_at ?  \Carbon\Carbon::parse($task->created_at)->format('d-m-Y H:i') : 'Not Set' }}
                                                </td>
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
                                                        <div class="flex flex-col truncate">
                                                            <div class="truncate max-w-[140px]" title="{{ $task->hotelDetails->hotel->name ?? '-' }}">
                                                                {{ $task->hotelDetails->hotel->name ?? 'N/A' }}
                                                            </div>
                                                            <div class="text-sm text-gray-500 whitespace-nowrap">
                                                                {{ $task->hotelDetails->check_in ?? 'N/A' }} - {{ $task->hotelDetails->check_out ?? 'N/A' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @endif
                                                    @else
                                                    <div>{{ $task->additional_info ?? '-' }}</div>
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
                                                        href="{{ route('invoice.show', $task->invoiceDetail->invoice_number) }}">
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
                                                        <input type="text" name="middle_name" id=""
                                                            placeholder="Client's Middle Name"
                                                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label
                                                            class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                                        <input type="text" name="last_name" id=""
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
                                                            <input type="date" name="date_of_birthTask"
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
                                                            <input type="text" name="passport" id="passport_noTask"
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
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Agent's
                                                            Name</label>
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
                                                        :items="$clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name])"
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
                            <div id="loadMoreWrapper" class="text-center my-4"
                                x-show="shown < {{ count($tasks) }}" x-cloak>
                                <button @click="shown += 10"
                                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                    Load More
                                </button>
                            </div>
                            <!-- <p id="noTasksFound"
                                class="flex flex-col items-center justify-center py-6 text-center text-gray-500 text-sm gap-2 hidden">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor"
                                    stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9.75 9.75a.75.75 0 011.5 0v4.5a.75.75 0 01-1.5 0v-4.5zm3 0a.75.75 0 011.5 0v4.5a.75.75 0 01-1.5 0v-4.5zM12 21a9 9 0 100-18 9 9 0 000 18z" />
                                </svg>
                                <span>No tasks found matching your search</span>
                            </p> -->
                            <!-- Pagination Links -->
                        </div>

                        <x-pagination :data="$tasks" />

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
@vite('resources/js/tasks.js')

<script>
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

        // An array of column names from the session, passed from the controller
        const visibleColumns = @json($visibleColumns ?? []);

        function updateColumnVisibility() {
            checkboxes.forEach(checkbox => {
                const columnName = checkbox.id.replace('col-', '');
                const isVisible = visibleColumns.includes(columnName);
                checkbox.checked = isVisible;

                const columns = document.querySelectorAll(`[data-column="${columnName}"]`);
                columns.forEach(column => {
                    column.classList.toggle('column-hidden', !isVisible);
                });
            });
        }

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

        updateColumnVisibility();

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

            formTaskContainer.innerHTML = '';

            console.log('Selected Supplier:', supplier);
            if (supplier.name == 'Magic Holiday') {
                let p = document.createElement('p');
                p.classList.add('text-blue-400', 'text-sm', 'mb-2');
                p.innerHTML = "You don't need to choose the agent for Magic Holiday, it will be automatically assigned.";
                formTaskContainer.appendChild(p);

                let input = document.createElement('input');
                input.type = 'text';
                input.name = 'supplier_ref';
                input.placeholder = 'Reference';
                input.classList.add('input', 'w-full', 'mt-1', 'rounded-lg', 'border',
                    'border-gray-300', 'dark:border-gray-700', 'dark:bg-gray-800',
                    'dark:text-gray-300', 'p-3', 'mb-1');
                formTaskContainer.appendChild(input);
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

                if (clientId) {
                    fetch(`/clients/${clientId}/agent`)
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