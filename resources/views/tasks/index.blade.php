<x-app-layout>
    <style>
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
            right: 1rem;
            top: -1rem;
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
    </style>

    <div class="flex justify-between items-center gap-5 my-3 ">
        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Tasks List</h2>
            <div data-tooltip="number of tasks"
                class="relative w-12 h-12 flex items-center justify-center DarkBGcolor dark:!bg-gray-700 dark:!hover:bg-gray-600 rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $taskCount }}</span>
            </div>
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
            <div x-cloak x-show="addTaskModal"
                class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-20">
                <div @click.away="addTaskModal = false" class="bg-white rounded shadow w-96">
                    <div class="p-4 flex justify-between items-center">
                        <span class="text-lg font-semibold">Add Task For Specific Supplier</span>

                        <button type="button"
                            @click="addTaskModal = false"
                            class="text-gray-500 hover:text-red-600 p-1 rounded focus:outline-none">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                                class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <hr>
                      <form id="agent-supplier-task" action="{{ route('tasks.agent.upload') }}"
                        class="p-4 flex flex-col gap-2" method="POST" enctype="multipart/form-data">
                        @csrf
                        @unlessrole('agent')
                        <div x-data="searchableDropdownAgent()" x-init="init()" class="w-full">
                            <div class="relative">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium">Select an Agent:</label>
                                    <button type="button"
                                        @click="open = !open"
                                        class="w-full border border-gray-300 dark:border-gray-600 p-2 rounded-full text-base text-left bg-white text-black min-h-[42px]">
                                        <span x-text="selectedAgent === '' ? 'Select Agent' : selectedAgent"></span>
                                    </button>
                                </div>

                                <input type="hidden" name="agent_id" :value="selectedId">

                                <div x-show="open" @click.away="open = false"
                                    class="absolute bg-white z-10 border w-full max-h-48 rounded shadow">
                                    <div class="px-2 py-2">
                                        <input type="text"
                                            x-model="search"
                                            @input="filterOptions"
                                            placeholder="Search Agent Name"
                                            class="w-full border border-gray-300 rounded-full px-2 py-1 text-sm text-black">
                                    </div>

                                    <template x-for="option in filtered.slice(0, 5)" :key="option.id">
                                        <div @click="select(option)"
                                            class="p-2 hover:bg-gray-100 cursor-pointer text-sm"
                                            x-html="highlightMatch(option.name)">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        @else
                        <input type="hidden" name="agent_id" id="agent_id_task_modal" value="{{ Auth()->user()->agent->id }}">
                        @endunlessrole

                        <div x-data="searchableDropdownSupplier()" x-init="init()" class="w-full">
                            <div class="relative">
                                <div class="mb-4">
                                    <label class="block mb-1 text-sm font-medium">Select a Supplier:</label>
                                    <button type="button"
                                        @click="open = !open"
                                        class="w-full border border-gray-300 dark:border-gray-600 p-2 rounded-full text-base text-left bg-white text-black">
                                        <span x-text="selectedSupplier === '' ? 'Select Supplier' : selectedSupplier"></span>
                                    </button>
                                </div>

                                <input type="hidden" name="supplier_id" :value="selectedId">

                                <div x-show="open" @click.away="open = false"
                                    class="absolute bg-white z-10 border w-full max-h-48 rounded shadow">

                                    <div class="px-2 py-2">
                                        <input type="text"
                                            x-model="search"
                                            @input="filterOptions"
                                            placeholder="Search Supplier Name"
                                            class="w-full border border-gray-300 rounded-full px-2 py-1 text-sm text-black">
                                    </div>

                                    <template x-for="option in filtered.slice(0, 5)" :key="option.id">
                                        <div @click="select(option)"
                                            class="p-2 hover:bg-gray-100 cursor-pointer text-sm"
                                            x-html="highlightMatch(option.name)">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div id="form-task-container" class="mb-2" data-company-id="{{ $companyId }}">
                        </div>
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

    <div class="tableCon">
        <div class="content-70">
            <div class="p-2 bg-white dark:bg-gray-700 rounded-lg shadow-md">
                <div class="customResponsiveClass flex flex-col md:flex-row justify-between p-2 gap-3">
                    <div class="relative w-full">
                        <input type="text" placeholder="Find fast and search here..."
                            class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                            id="searchInput">

                        <button data-tooltip="start searching" type="button"
                            class="DarkBGcolor dark:!bg-gray-700 dark:!hover:bg-gray-600 absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1"
                            id="searchButton">
                            <svg class="mx-auto" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <circle cx="11.5" cy="11.5" r="9.5" stroke="#fff" stroke-width="1.5"
                                    opacity="0.5" class="dark:stroke-gray-300"></circle>
                                <path d="M18.5 18.5L22 22" stroke="#fff" stroke-width="1.5" stroke-linecap="round"
                                    class="dark:stroke-gray-300"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="flex customCenter gap-5 w-full justify-end">
                    </div>
                </div>

                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <div x-data="{ shown: 10 }">
                        <div class="dataTable-container h-max">
                            <div x-data="{
                                        showUploadForm: false, showManualForm: false, showFileInput: false,
                                        modalTaskId: null,
                                        modalClientName: '',
                                        modalAgentName: '',
                                        modalAgentId: '',
                                        modalBranchName: '',
                                        chooseMethodModal: false,
                                        showUploadForm: false,
                                        showManualForm: false,
                                        openChoose(taskId, clientName, agentName, agentId, branchName) {
                                            this.modalTaskId = taskId;
                                            this.modalClientName = clientName;
                                            this.modalAgentName = agentName;
                                            this.modalAgentId = agentId;
                                            this.modalBranchName = branchName;
                                            this.chooseMethodModal = true;
                                        },
                                        openUpload() {
                                            this.chooseMethodModal = false;
                                            this.showUploadForm = true;
                                        },
                                        openManualForm(taskId, clientName, agentName, agentId, branchName) {
                                            this.modalTaskId = taskId;
                                            this.modalClientName = clientName;
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
                                        }
                                    }"
                                x-cloak>
                                <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                                    <thead>
                                        <tr>
                                            @can('create', 'App\Models\Invoice')
                                            <th>
                                                <label class="custom-checkbox">
                                                    <input type="checkbox" id="selectAll" class="text-gray-300 hidden">
                                                    <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20"
                                                        height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                        <rect width="18" height="18" x="3" y="3" fill="none"
                                                            stroke="currentColor" stroke-linecap="round"
                                                            stroke-linejoin="round" stroke-width="1" rx="4" />
                                                    </svg>
                                                </label>
                                            </th>
                                            @endcan
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">
                                                Actions</th>
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">
                                                Enable/Disable</th>
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">
                                                Reference</th>
                                                <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">GDS Reference</th>
                                                <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Amadeus Reference</th>
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Created By</th>
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Issued By</th>
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Client
                                                Name</th>
                                            @if (Auth()->user()->role_id == \App\Models\Role::COMPANY)
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">
                                                Branch Name</th>
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">
                                                Agent Name</th>
                                            @endif
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Date</th>
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Type
                                            </th>
                                            @can('viewPrice', 'App\Models\Task')
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Price
                                            </th>
                                            @endcan
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Status
                                            </th>
                                            <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">
                                                Supplier</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if ($tasks->isEmpty())
                                        <tr>
                                            <td colspan="10" class="text-center p-5 text-gray-500 dark:text-gray-300">No
                                                tasks found</td>
                                        </tr>
                                        @else
                                        @foreach ($tasks as $key => $task)
                                        <tr x-show="{{ $key }} < shown" x-cloak data-price="{{ $task->price }}"
                                            data-supplier-id="{{ $task->supplier->id }}"
                                            data-branch-id="{{ $task->agent ? $task->agent->branch->id : null }}"
                                            data-agent-id="{{ $task->agent_id }}" data-status="{{ $task->status }}"
                                            data-type="{{ $task->type }}"
                                            data-client-id="{{ $task->client ? $task->client->id : null }}"
                                            data-task-id="{{ $task->id }}" class="taskRow">
                                            @can('create', 'App\Models\Invoice')
                                            <td>
                                                <label class="custom-checkbox"
                                                    data-tooltip="{{ !$task->enabled ? 'Task info is not enabled' : 'Select task' }}">

                                                    @if ($task->status !== 'refund')
                                                    <input type="checkbox"
                                                        class="form-checkbox CheckBoxColor rowCheckbox text-gray-900 dark:text-gray-300"
                                                        value="{{ $task->id }}"
                                                        data-status="{{ $task->status }}"
                                                        {{ $task->invoiceDetail || !$task->enabled || $task->linkedTask ? 'disabled' : '' }}>
                                                    @else
                                                    <input type="checkbox"
                                                        class="form-checkbox CheckBoxColor rowCheckbox text-gray-900 dark:text-gray-300"
                                                        value="{{ $task->id }}"
                                                        data-status="{{ $task->status }}"
                                                        {{ $task->refundDetail || !$task->is_complete ? 'disabled' : '' }}>
                                                    @endif


                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18"
                                                        height="18" viewBox="0 0 24 24"
                                                        class="checkbox-svg checkbox-border">
                                                        <rect width="18" height="18" x="3" y="3"
                                                            fill="none" stroke="currentColor"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1" rx="4" />
                                                    </svg>
                                                </label>
                                            </td>
                                            @endcan
                                            <td class="p-3 text-sm flex gap-3 justify-center">
                                                <a data-tooltip="see task" href="javascript:void(0);"
                                                    class="viewTask text-blue-600 dark:text-blue-300 font-medium hover:text-[#ffb958] hover:font-bold active-text"
                                                    data-task-id="{{ $task->id }}"
                                                    data-task-url="{{ route('tasks.show', $task->id) }}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="22"
                                                        height="22" viewBox="0 0 24 24" fill="currentColor">
                                                        <path
                                                            d="M12 4c-4.182 0-7.028 2.5-8.725 4.704C2.425 9.81 2 10.361 2 12s.425 2.191 1.275 3.296C4.972 17.5 7.818 20 12 20s7.028-2.5 8.725-4.704C21.575 14.191 22 13.64 22 12s-.425-2.19-1.275-3.296C19.028 6.5 16.182 4 12 4zm0 10a2.5 2.5 0 110-5 2.5 2.5 0 010 5z" />
                                                    </svg>
                                                </a>

                                                <div x-data="{ editTaskModal_{{ $task->id }}: false }">
                                                    <a data-tooltip="edit task" href="javascript:void(0);"
                                                        @click="editTaskModal_{{ $task->id }} = true">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="18"
                                                            height="18" viewBox="0 0 24 24">
                                                            <path fill="none" stroke="currentColor"
                                                                stroke-width="1.5"
                                                                d="M3 17l-2 4l4-2l14-14l-2-2L3 17Z" />
                                                        </svg>
                                                    </a>
                                                    <div x-show="editTaskModal_{{ $task->id }}" x-cloak
                                                        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-20">

                                                        <form id="edit-task-form-{{ $task->id }}"
                                                            action="{{ route('tasks.update', $task->id) }}"
                                                            method="post"
                                                            class="inline-flex flex-col gap-4 items-center">
                                                            <div @click.away="editTaskModal_{{ $task->id }} = false"
                                                                class="bg-white rounded-md border-2 w-full sm:w-120">
                                                                <!-- Responsive modal width -->
                                                                <div class="flex justify-between p-4">
                                                                    <p class="font-semibold text-lg">
                                                                        Update the following information if needed
                                                                    </p>
                                                                    <button type="button"
                                                                        @click="editTaskModal_{{ $task->id }} = false"
                                                                        class="text-red-500 font-bold">
                                                                        &times;
                                                                    </button>
                                                                </div>
                                                                <hr>
                                                                @csrf
                                                                @method('PUT')
                                                                <div class="p-4 inline-flex flex-col gap-4">

                                                                    <!-- Reference Field -->
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="reference"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Reference:</label>
                                                                        <input type="text"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 bg-gray-200"
                                                                            value="{{ $task->reference }}" readonly>
                                                                    </div>

                                                                    <!-- Status Field -->
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="status"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Status:</label>
                                                                        @if ($task->status === 'refund')
                                                                        <select name="status" id="status"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 text-base"
                                                                            disabled>
                                                                            <option value="refund" selected>Refund
                                                                            </option>
                                                                        </select>
                                                                        <input type="hidden" name="status"
                                                                            value="refund">
                                                                        @else
                                                                        <select name="status" id="status"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 text-base">
                                                                            <option value="">Set Status
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
                                                                        <input type="hidden" name="status"
                                                                            value="Refund">
                                                                        @endif

                                                                    </div>

                                                                    <!-- Additional Info and Venue -->
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="additional_info"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Additional
                                                                            Info:</label>
                                                                        <input type="text"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 bg-gray-200"
                                                                            value="{{ $task->additional_info }} - {{ $task->venue }}"
                                                                            readonly>
                                                                    </div>

                                                                    <!-- Supplier Name -->
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="supplier"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Supplier:</label>
                                                                        <input type="text"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 bg-gray-200"
                                                                            value="{{ $task->supplier->name }}"
                                                                            readonly>
                                                                    </div>

                                                                    <!-- Price -->
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="price"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Price:</label>
                                                                        <input type="text"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 bg-gray-200"
                                                                            value="{{ $task->price }}" readonly>
                                                                    </div>

                                                                    <!-- Tax -->
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="tax"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Tax:</label>
                                                                        <input type="text"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 bg-gray-200"
                                                                            value="{{ $task->tax }}"
                                                                            placeholder="Tax" readonly>
                                                                    </div>

                                                                    <!-- Surcharge -->
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="surcharge"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Surcharge:</label>
                                                                        <input type="text"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 bg-gray-200"
                                                                            value="{{ $task->surcharge }}"
                                                                            placeholder="Surcharge" readonly>
                                                                    </div>

                                                                    <!-- Total -->
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="total"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Total:</label>
                                                                        <input type="text" name="total"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3"
                                                                            value="{{ $task->total }}"
                                                                            placeholder="Total">
                                                                    </div>

                                                                    <!-- Task Type -->
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="type"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Task
                                                                            Type:</label>
                                                                        <input type="text"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 bg-gray-200"
                                                                            value="{{ $task->type }}" readonly>
                                                                    </div>

                                                                    <!-- Client Selection -->
                                                                    <div x-data="searchableDropdownClient()" class="flex items-center gap-4">
                                                                        <label for="client_id" class="w-2/4 sm:w-1/3 text-left text-base">Client:</label>

                                                                        <div class="relative w-2/4 sm:w-2/3">
                                                                            <button type="button"
                                                                                @click="open = !open"
                                                                                class="client-select w-full border border-gray-300 dark:border-gray-600 p-2 rounded-md text-base text-left bg-white text-black min-h-[42px]">
                                                                                <span x-text="selectedName || 'Choose Client'"></span>
                                                                            </button>

                                                                            <input type="hidden" name="client_id" :value="selectedId">

                                                                            <div x-show="open" @click.away="open = false"
                                                                                class="absolute bg-white z-10 border w-full max-h-48 rounded shadow mt-1">

                                                                                <!-- Search bar inside dropdown -->
                                                                                <div class="px-2 py-2">
                                                                                    <input type="text"
                                                                                        x-model="search"
                                                                                        @input="filterOptions"
                                                                                        placeholder="Search Client Name"
                                                                                        class="w-full border border-gray-300 rounded-full px-2 py-1 text-sm text-black" />
                                                                                </div>

                                                                                <!-- Dropdown results with highlighting -->
                                                                                <template x-for="option in filtered.slice(0, 5)" :key="option.id">
                                                                                    <div @click="select(option)"
                                                                                        class="p-2 hover:bg-gray-100 cursor-pointer text-sm"
                                                                                        x-html="highlightMatch(option.name)">
                                                                                    </div>
                                                                                </template>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Agent Selection (Role-based) -->
                                                                    @unlessrole('agent')
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="agent_id"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Agent:</label>
                                                                        <select disabled
                                                                            id="agent_id_select_{{ $task->id }}"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 text-base">
                                                                            <option value=""
                                                                                {{ empty($task->agent?->id) ? 'selected' : '' }}>
                                                                                Choose Agent</option>
                                                                            @foreach ($agents as $agent)
                                                                            <option value="{{ $agent->id }}"
                                                                                {{ $task->agent && $task->agent->id === $agent->id ? 'selected' : '' }}>
                                                                                {{ $agent->name }}
                                                                            </option>
                                                                            @endforeach
                                                                        </select>

                                                                        <input type="hidden" name="agent_id"
                                                                            id="agent_id_hidden_{{ $task->id }}"
                                                                            value="{{ $task->agent->id ?? '' }}">

                                                                    </div>
                                                                    @else
                                                                    <input type="hidden" name="agent_id"
                                                                        id="agent_id_{{ $task->id }}"
                                                                        value="{{ Auth()->user()->agent->id }}">
                                                                    @endunlessrole

                                                                    <!-- Supplier Selection -->
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="supplier_id"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Supplier:</label>
                                                                        <select disabled name="supplier_id"
                                                                            id="supplier_id_{{ $task->id }}"
                                                                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 text-base">
                                                                            @foreach ($suppliers as $supplier)
                                                                            <option value="{{ $supplier->id }}"
                                                                                {{ $task->supplier ? ($task->supplier->id === $supplier->id ? 'selected' : '') : '' }}>
                                                                                {{ $supplier->name }}
                                                                            </option>
                                                                            @endforeach
                                                                        </select>
                                                                        <input type="hidden" name="supplier_id"
                                                                            id="supplier_id_{{ $task->id }}"
                                                                            value="{{ $task->supplier->id }}">
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="flex space-x-4 mt-2">
                                                                <!-- Update Button -->
                                                                <x-primary-button type="submit"
                                                                    class="w-[200px] justify-center px-12 py-10 text-lg"
                                                                    form="edit-task-form-{{ $task->id }}">
                                                                    Update
                                                                </x-primary-button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                                <label class="switch">
                                                    <input type="checkbox" class="toggle-task-status"
                                                        data-task-id="{{ $task->id }}"
                                                        {{ $task->enabled ? 'checked' : '' }}>
                                                    <span class="slider round"></span>
                                                </label>
                                            </td>
                                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                                {{ $task->reference }}
                                            </td>
                                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                                {{ $task->gds_reference }}
                                            </td>
                                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                                {{ $task->airline_reference }}
                                            </td>
                                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                                {{ $task->created_by ?? 'Not Set' }}
                                            </td>
                                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                                {{ $task->issued_by ?? 'Not Set' }}
                                            </td>
                                            <td
                                                class="p-3 flex justify-between gap-2 text-sm font-semibold text-gray-900 dark:text-gray-300 relative">
                                                <p class="{{ $task->client ?? 'no-client' }}">
                                                    <button
                                                        @click="openManualForm({{ $task->id }}, '{{ $task->client_name ?? '' }}', '{{ $task->agent->name ?? 'Not Set' }}', '{{ $task->agent->id ?? 'Null' }}', '{{ $task->agent->branch->name ?? 'Not Set' }}')">
                                                        {{ $task->client_name ?? 'Not Set' }}
                                                    </button>
                                                </p>
                                                @if ($task->client)
                                                <div data-tooltip="Client Linked">
                                                    <svg width="24" height="24" viewBox="0 0 24 24"
                                                        fill="none" xmlns="http://www.w3.org/2000/svg"
                                                        class="fill-green-500">
                                                        <path fill-rule="evenodd" clip-rule="evenodd"
                                                            d="M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12ZM16.0303 8.96967C16.3232 9.26256 16.3232 9.73744 16.0303 10.0303L11.0303 15.0303C10.7374 15.3232 10.2626 15.3232 9.96967 15.0303L7.96967 13.0303C7.67678 12.7374 7.67678 12.2626 7.96967 11.9697C8.26256 11.6768 8.73744 11.6768 9.03033 11.9697L10.5 13.4393L12.7348 11.2045L14.9697 8.96967C15.2626 8.67678 15.7374 8.67678 16.0303 8.96967Z"
                                                            fill="" />
                                                    </svg>
                                                </div>
                                                @endif
                                            </td>

                                            @if (Auth()->user()->role_id == \App\Models\Role::COMPANY)
                                            <td class="p-3 text-sm font-semibold text-gray-500">
                                                {{ $task->agent->branch->name ?? 'Not Set' }}
                                            </td>
                                            <td class="p-3 text-sm font-semibold text-gray-500">
                                                {{ $task->agent->name ?? 'Not Set' }}
                                            </td>
                                            @endif
                                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                                {{ $task->type }}
                                            </td>
                                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
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
                                            @can('viewPrice', 'App\Models\Task')
                                            <td class="p-3 text-sm font-semibold DarkBTextcolor dark:text-gray-300">
                                                {{ $task->total }}
                                            </td>
                                            @endcan
                                            <td>
                                                <span
                                                    class="badge badge-outline-success whitespace-nowrap px-2 py-1 rounded text-sm font-medium"
                                                    @if ($task->status === 'reissued' && $task->originalTask) data-tooltip-left="Reissued from {{ $task->originalTask->flightDetails->ticket_number }}" @endif>
                                                    {{ $task->status === null ? 'Not Set' : ucwords($task->status) }}
                                                </span>
                                            </td>
                                            <td class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                                {{ $task->supplier->name }}
                                            </td>

                                        </tr>

                                        @endforeach
                                        @endif
                                    </tbody>

                                    <!-- Choose Method -->
                                    <div x-show="chooseMethodModal" @click.away="closeAll()" x-transition class="fixed inset-0 z-50 bg-gray-700 bg-opacity-60 backdrop-blur-sm flex items-center justify-center">
                                        <div
                                            @click.stop
                                            class="bg-white rounded-lg p-6 w-full max-w-xl shadow-lg">
                                            <div class="flex items-center justify-between mb-10">
                                                <h2 class="text-xl font-bold text-gray-800">How would you like to create the client?</h2>
                                                <button @click="closeAll()" class="text-gray-400 hover:text-red-500 text-2xl leading-none">
                                                    &times;
                                                </button>
                                            </div>
                                            <div class="flex flex-col sm:flex-row gap-4 mb-8">
                                                <button id="upload-passport-btn"
                                                    @click="openUpload()"
                                                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-full">
                                                    Upload Passport
                                                </button>
                                                <button id="fill-form-btn"
                                                    @click="openManualForm()"
                                                    class="flex-1 bg-gray-800 hover:bg-gray-900 text-white py-2 rounded-full">
                                                    Fill Form
                                                </button>
                                            </div>
                                            <div class="mt-6 flex justify-center">
                                                <button
                                                    @click="closeAll()"
                                                    type="button"
                                                    class="w-32 bg-gray-300 hover:bg-gray-400 font-semibold py-2 rounded-full text-sm transition duration-150">
                                                    Cancel
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Upload Passport -->
                                    <div x-show="showUploadForm" x-transition class="fixed inset-0 z-50 bg-gray-700 bg-opacity-60 flex items-center justify-center">
                                        <div @click.stop class="bg-white rounded-lg p-6 w-full max-w-sm shadow-xl">
                                            <div class="flex items-start justify-between mb-6">
                                                <div>
                                                    <h2 class="text-xl font-bold text-gray-800">Upload Passport</h2>
                                                    <p class="text-gray-500 italic text-xs mt-1">Please choose appropriate file to proceed</p>
                                                </div>
                                                <button @click="closeAll()" class="text-gray-400 hover:text-red-500 text-2xl leading-none ml-4">
                                                    &times;
                                                </button>
                                            </div>

                                            <!--                                             <input type="hidden" name="task_id" :value="modalTaskId"> -->
                                            <div id="passport">
                                                <input type="file" id="passport-upload-input" accept="image/*,application/pdf"
                                                    class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg cursor-pointer dark:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                    hidden>
                                                <div id="file-preview-container" class="mt-4"></div> <!-- For image preview -->
                                                <div id="upload-status" class="mt-2 text-sm text-gray-600"></div> <!-- For upload status -->
                                                <div id="passport-details" class="mt-4 text-sm text-gray-800"></div> <!-- For displaying extracted details -->
                                            </div>

                                            <div class="flex justify-between mt-10">
                                                <button
                                                    @click="closeAll()"
                                                    type="button"
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
                                    <div x-show="showManualForm" x-transition class="fixed inset-0 z-50 bg-gray-700 bg-opacity-60 flex items-center justify-center">
                                        <div @click.stop class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                                            <!-- Header with title and close button -->
                                            <div class="flex items-center justify-between mb-2">
                                                <h2 class="text-xl font-bold text-gray-800">Client Registration</h2>
                                                <button @click="closeAll()" class="text-gray-400 hover:text-red-500 text-2xl leading-none">&times;</button>
                                            </div>

                                            <!-- Subtitle -->
                                            <p class="text-gray-600 italic text-xs mb-6">Please fill in the required client information to register</p>

                                            <!-- Form -->
                                            <form action="{{ route('clients.store') }}" method="POST" id="client-formTask" class="space-y-4">
                                                @csrf
                                                <input type="hidden" name="task_id" :value="modalTaskId">
                                                <input type="hidden" name="agent_id" :value="modalAgentId">
                                                <!-- Name -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Client's Name</label>
                                                    <input type="text" name="name" id="nameTask" :value="modalClientName" placeholder="Client's name"
                                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                        required>
                                                </div>

                                                <!-- Email + DOB -->
                                                <div class="flex gap-4">
                                                    <div class="w-2/3">
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                                        <input type="email" name="email" id="emailTask" placeholder="Client's email"
                                                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div class="w-1/3">
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                                                        <input type="date" name="date_of_birthTask"
                                                            class="w-full text-gray-700 border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                </div>

                                                <!-- Phone -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                                    <div class="flex gap-2">
                                                        <div class="relative w-40">
                                                            <select name="dial_code" id="dial_codeTask"
                                                                class="w-full h-full text-sm px-3 py-2 pr-8 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 rounded-md appearance-none">
                                                                @foreach (\App\Models\Country::all() as $country)
                                                                <option value="{{ $country->dialing_code }}">
                                                                    {{ $country->dialing_code }} ({{ $country->name }})
                                                                </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <input type="text" name="phone"
                                                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                            id="phoneTask" placeholder="Client's phone number" required>
                                                    </div>
                                                </div>

                                                <!-- Passport + Civil -->
                                                <div class="flex gap-4">
                                                    <div class="w-1/2">
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Passport Number</label>
                                                        <input type="text" name="passport" id="passport_noTask"
                                                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div class="w-1/2">
                                                        <label class="block text-sm font-medium text-gray-700 mb-1">Civil Number</label>
                                                        <input type="text" name="civil_no" id="civil_noTask"
                                                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                </div>
                                                <div
                                                    id="upload-passport-container"
                                                    class="my-2 border-2 border-dashed border-gray-400 rounded-md w-full w-full flex flex-col justify-center gap-2 items-center p-2 min-h-20 max-h-48"
                                                    ondrop="dropHandler(event);"
                                                    ondragover="dragOverHandler(event);">
                                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M18 10L13 10" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" />
                                                        <path d="M10 3H16.5C16.9644 3 17.1966 3 17.3916 3.02567C18.7378 3.2029 19.7971 4.26222 19.9743 5.60842C20 5.80337 20 6.03558 20 6.5" stroke="#1C274C" stroke-width="1.5" />
                                                        <path d="M2 6.94975C2 6.06722 2 5.62595 2.06935 5.25839C2.37464 3.64031 3.64031 2.37464 5.25839 2.06935C5.62595 2 6.06722 2 6.94975 2C7.33642 2 7.52976 2 7.71557 2.01738C8.51665 2.09229 9.27652 2.40704 9.89594 2.92051C10.0396 3.03961 10.1763 3.17633 10.4497 3.44975L11 4C11.8158 4.81578 12.2237 5.22367 12.7121 5.49543C12.9804 5.64471 13.2651 5.7626 13.5604 5.84678C14.0979 6 14.6747 6 15.8284 6H16.2021C18.8345 6 20.1506 6 21.0062 6.76946C21.0849 6.84024 21.1598 6.91514 21.2305 6.99383C22 7.84935 22 9.16554 22 11.7979V14C22 17.7712 22 19.6569 20.8284 20.8284C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.8284C2 19.6569 2 17.7712 2 14V6.94975Z" stroke="#1C274C" stroke-width="1.5" />
                                                    </svg>
                                                    <input type="file" name="file" id="file-task-passport" class="hidden" accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf">
                                                    <p id="task-passport-file-name">
                                                        You can drag and drop a file here
                                                    </p>
                                                    <label for="file-task-passport" class="bg-black text-white font-semibold p-2 rounded-md border-2 border-black hover:border-2 hover:border-cyan-500">
                                                        Upload File
                                                    </label>
                                                </div>
                                                <div class="my-2">
                                                    <button
                                                        id="task-passport-process-btn"
                                                        class="w-full bg-gray-300 text-gray-500 font-semibold py-2 rounded-full text-sm transition duration-150 cursor-not-allowed"
                                                        disabled>
                                                        Process File
                                                    </button>
                                                </div>
                                                <!-- Address -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                                    <input type="text" name="address" id="addressTask"
                                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                        placeholder="Client's address">
                                                </div>

                                                <!-- Agent Name -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Agent's Name</label>
                                                    <input type="text" name="agent_name" id="agent_idTask" :value="modalAgentName"
                                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                        required readonly>
                                                </div>

                                                <!-- Buttons -->
                                                <div class="flex justify-between pt-4 mt-4">
                                                    <button type="button" @click="closeAll()"
                                                        class="w-32 bg-gray-300 hover:bg-gray-400 font-semibold py-2 rounded-full text-sm transition duration-150">
                                                        Cancel
                                                    </button>
                                                    <button type="submit"
                                                        class="w-32 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded-full text-sm transition duration-150">
                                                        Register Client
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                </table>
                                <div id="loadMoreWrapper" class="text-center my-4" x-show="shown < {{ count($tasks) }}" x-cloak>
                                    <button @click="shown += 10"
                                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                        Load More
                                    </button>
                                </div>
                                <p id="noTasksFound" class="flex flex-col items-center justify-center py-6 text-center text-gray-500 text-sm gap-2 hidden">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9.75 9.75a.75.75 0 011.5 0v4.5a.75.75 0 01-1.5 0v-4.5zm3 0a.75.75 0 011.5 0v4.5a.75.75 0 01-1.5 0v-4.5zM12 21a9 9 0 100-18 9 9 0 000 18z" />
                                    </svg>
                                    <span>No tasks found matching your search</span>
                                </p>
                            </div>
                        </div>

                        <div id="floatingActions"
                            class="hidden flex justify-between gap-5 fixed CuzPostion bg-[#f6f8fa] dark:bg-gray-800 shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] dark:shadow-[0_0_4px_2px_rgb(255_255_255_/_10%)] rounded-lg w-auto h-auto z-50 p-3">

                            <div class="flex justify-between gap-5 items-center h-full">
                                <button id="createInvoiceBtn" data-route="{{ route('invoices.create') }}"
                                    class="flex px-5 py-3 gap-3 btn-success hover:bg-[#8b0000c2] rounded-lg shadow-sm items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                        viewBox="0 0 24 24">
                                        <path fill="#ffffff"
                                            d="M2 12c0-2.8 1.6-5.2 4-6.3V3.5C2.5 4.8 0 8.1 0 12s2.5 7.2 6 8.5v-2.2c-2.4-1.1-4-3.5-4-6.3m13-9c-5 0-9 4-9 9s4 9 9 9s9-4 9-9s-4-9-9-9m5 10h-4v4h-2v-4h-4v-2h4V7h2v4h4z" />
                                    </svg>
                                    <span id="createInvoiceBtnText" class="text-sm">Create Invoice</span>
                                </button>
                            </div>
                            <div id="closeTaskFloatingActions" class="flex cursor-pointer items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                    viewBox="0 0 12 12">
                                    <path fill="#E53935"
                                        d="M1.757 10.243a6.001 6.001 0 1 1 8.488-8.486a6.001 6.001 0 0 1-8.488 8.486M6 4.763l-2-2L2.763 4l2 2l-2 2L4 9.237l2-2l2 2L9.237 8l-2-2l2-2L8 2.763Z" />
                                </svg>
                            </div>
                        </div>



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

                        <!-- <div class="dataTable-bottom justify-center">
                        <nav class="dataTable-pagination">
                            <ul class="dataTable-pagination-list flex gap-2 mt-4">
                                <li class="pager" id="prevPage">
                                    <a href="#">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                            <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path opacity="0.5" d="M16.9998 19L10.9998 12L16.9998 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                </li>
                                <li class="pager" id="nextPage">
                                    <a href="#">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5">
                                            <path d="M11 19L17 12L11 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                            <path opacity="0.5" d="M6.99976 19L12.9998 12L6.99976 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div> -->
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
    document.addEventListener("DOMContentLoaded", function() {

        const viewTaskLinks = document.querySelectorAll(".viewTask");
        viewTaskLinks.forEach(link => {
            link.addEventListener("click", function() {
                viewTaskLinks.forEach(l => l.classList.remove("text-[#ebc186]", "font-bold"));
                this.classList.add("text-[#ebc186]", "font-bold");
            });
        });

        document.querySelectorAll('.rowCheckbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const taskRow = this.closest('.taskRow');
                const taskId = taskRow.getAttribute('data-task-id');
                const taskStatus = taskRow.getAttribute('data-status');

                const floatingActions = document.getElementById('floatingActions');
                const createInvoiceBtn = document.getElementById('createInvoiceBtn');
                const createInvoiceBtnText = document.getElementById('createInvoiceBtnText');

                if (this.checked) {
                    const isRefund = taskStatus === 'refund';

                    if (isRefund) {
                        // Uncheck all others if refund
                        document.querySelectorAll('.rowCheckbox').forEach(cb => {
                            if (cb !== this) {
                                cb.checked = false;
                            }
                        });
                    } else {
                        // If not refund, uncheck all refund checkboxes
                        document.querySelectorAll('.rowCheckbox').forEach(cb => {
                            if (cb !== this && cb.dataset.status === 'refund') {
                                cb.checked = false;
                            }
                        });
                    }

                    floatingActions.classList.remove('hidden');

                    if (isRefund) {
                        createInvoiceBtnText.innerText = 'Proceed Refund';
                        createInvoiceBtn.setAttribute('data-route',
                            `/refunds/${taskId}/create`);

                        // Set button background to red
                        createInvoiceBtn.classList.remove('btn-success');
                        createInvoiceBtn.classList.add('btn-danger');
                    } else {
                        createInvoiceBtnText.innerText = 'Create Invoice';
                        createInvoiceBtn.setAttribute('data-route',
                            `/invoices/create?task=${taskId}`);

                        // Set button background to green
                        createInvoiceBtn.classList.remove('btn-danger');
                        createInvoiceBtn.classList.add('btn-success');
                    }

                    createInvoiceBtn.setAttribute('data-task-id', taskId);
                    createInvoiceBtn.setAttribute('data-task-status', taskStatus);

                } else {
                    const anyChecked = Array.from(document.querySelectorAll('.rowCheckbox'))
                        .some(cb => cb.checked);
                    if (!anyChecked) {
                        floatingActions.classList.add('hidden');
                    }
                }

            });
        });

        // Button click handler
        document.getElementById('createInvoiceBtn').addEventListener('click', function() {
            const route = this.getAttribute('data-route');
            const taskStatus = this.getAttribute('data-task-status');

            if (taskStatus === 'refund') {
                window.location.href = route;
            } else {
                // For invoice, optionally append multiple task IDs if multiple selected
                const selectedTaskIds = Array.from(document.querySelectorAll('.rowCheckbox:checked'))
                    .map(cb => cb.value);

                if (selectedTaskIds.length > 0) {
                    window.location.href = `/invoices/create?task_ids=${selectedTaskIds.join(',')}`;
                } else {
                    window.location.href = route;
                }
            }
        });


        // Handle create invoice button click
        document.getElementById('createInvoiceBtn').addEventListener('click', function() {
            const taskId = this.getAttribute('data-task-id');
            console.log('Creating invoice for task ID:', taskId);
            // Redirect or open modal logic
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
            let companyIdData = formTaskContainer.getAttribute('data-company-id');
            let tboTaskUrl = "{!! route('tasks.get-tbo', ['companyId' => '__companyId__']) !!}".replace('__companyId__', companyIdData);

            formTaskContainer.innerHTML = '';

            if (supplier.name === 'Magic Holiday') {
                let input = document.createElement('input');
                input.type = 'text';
                input.name = 'supplier_ref';
                input.placeholder = 'Reference';
                input.classList.add('input', 'w-full', 'mt-2', 'rounded-lg', 'border',
                    'border-gray-300', 'dark:border-gray-700', 'dark:bg-gray-800',
                    'dark:text-gray-300', 'p-3');
                formTaskContainer.appendChild(input);
            } else if (supplier.name === 'TBO Holiday') {
                let input = document.createElement('input');
                input.type = 'text';
                input.name = 'supplier_ref';
                input.placeholder = 'Coming Soon...';
                input.classList.add('input', 'w-full', 'mt-2', 'rounded-lg', 'border',
                    'border-gray-300', 'dark:border-gray-700', 'dark:bg-gray-800',
                    'dark:text-gray-300', 'p-3', 'disabled:opacity-75', 'disabled:cursor-not-allowed');
                input.disabled = true;
                formTaskContainer.appendChild(input);
            } else if (supplier.name === 'Amadeus') {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.name = 'task_file';
                fileInput.id = 'amadeus-upload-task';
                fileInput.classList.add('bg-white', 'dark:bg-dark', 'p-2', 'shadow-md', 'rounded-md',
                    'w-full', 'mt-2');
                formTaskContainer.appendChild(fileInput);
            } else {
                let div = document.createElement('div');
                div.classList.add('text-red-500', 'text-sm', 'font-semibold', 'mt-2');
                div.innerHTML = 'API not available for this supplier';
                formTaskContainer.appendChild(div);
            }
        });

        // Toggle task status
        document.querySelectorAll('.toggle-task-status').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const taskId = this.getAttribute('data-task-id');
                const isEnabled = this.checked;

                fetch(`/tasks/${taskId}/toggle-status`, {
                        method: 'POST',
                        headers: {
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
                            // this.checked = !isEnabled;
                        }
                    })
                    .catch(error => console.error('Error:', error))
                    .finally(() => {
                        window.location.reload();
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
                }
            }));
        });
    });

    function loadClient() {
        console.log("loadClient() triggered");

        const clientOption = $("#client-option");
        if (clientOption.length) {
            console.log("#client-option found, showing...");
            clientOption.show();
        } else {
            console.warn("#client-option not found!");
        }

        $('#upload-passport-btn').on('click', function() {
            console.log("#upload-passport-btn clicked");
            const input = $('#passport-upload-input');
            if (input.length) {
                console.log("#passport-upload-input found, triggering click");
                input.click();
            } else {
                console.error("#passport-upload-input not found!");
            }
        });

        $('#fill-form-btn').on('click', function() {
            console.log("#fill-form-btn clicked");
            const chatClientForm = document.getElementById('chatClientForm');
            if (chatClientForm) {
                chatClientForm.value = "new";
            } else {
                console.warn("#chatClientForm not found");
            }

            $("#create-client").show();
            $("#client-option").hide();
        });

        $('#submit-passport-upload').on('click', function() {
            console.log("#submit-passport-upload clicked");
            $('#passport-upload-input').click();
        });
    }

    $(document).ready(() => {
        console.log("DOM ready, initializing loadClient()");
        loadClient();
    });

    $('#passport-upload-input').on('change', function(event) {
        console.log("#passport-upload-input changed");
        const file = event.target.files[0];
        console.log("Selected file:", file);

        if (!file) {
            console.warn("No file selected");
            $('#upload-status').text('No file selected.');
            return;
        }

        const previewContainer = document.getElementById('file-preview-container');
        if (previewContainer) {
            previewContainer.innerHTML = '';

            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.width = 100;
            img.height = 100;
            img.alt = "Uploaded File Preview";
            img.className = "rounded shadow";
            previewContainer.appendChild(img);
            console.log("File preview added");
        } else {
            console.warn("#file-preview-container not found");
        }

        if (typeof passport !== 'undefined') {
            passport.show();
        } else {
            console.warn("passport element is undefined");
        }

        const formData = new FormData();
        formData.append('file', file);
        console.log("Sending file to OCR endpoint");

        fetch("{{ route('chat.handleFileUpload') }}", {
                method: "POST",
                body: formData,
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content")
                }
            })
            .then(response => {
                console.log("OCR response received");
                return response.json();
            })
            .then(response => {
                console.log("OCR response parsed:", response);

                if (response.success && response.data) {
                    const client = response.data;
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

                    if (client.date_of_birth) {
                        const dob = client.date_of_birth.replace(/\//g, '-');
                        const dobInput = document.querySelector('input[name="date_of_birthTask"]');
                        if (dobInput) dobInput.value = dob;
                    }

                    if (typeof passport !== 'undefined') passport.hide();
                    $("#create-client").show();
                } else {
                    const msg = response.message || 'Unknown error';
                    $('#upload-status').text('Upload failed: ' + msg);
                    console.error("OCR failed:", msg);
                }
            })
            .catch(error => {
                console.error("Upload error:", error);
                $('#upload-status').text('Error uploading file. Please try again.');
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
        file.files = e.dataTransfer.files;
        fileName.textContent = e.dataTransfer.files[0].name;
    }

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
        button.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white', 'font-semibold', 'py-2', 'rounded-full', 'text-sm', 'transition', 'duration-150');
        button.disabled = false;
    }
</script>
<!-- Searchable Dropdown -->
<script>
    function searchableDropdownClient() {
        return {
            open: false,
            search: '',
            selectedId: '',
            selectedName: @json(optional($task ?? null)->client->name ?? ''), // Fallback to an empty string if no task is found
            all: @json($clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name])),
            filtered: [],
            init() {
                this.filtered = [...this.all];
            },
            filterOptions() {
                const term = this.search.toLowerCase();
                this.filtered = this.all.filter(c => c.name.toLowerCase().includes(term));
            },
            select(option) {
                this.selectedId = option.id;
                this.selectedName = option.name;
                this.search = '';
                this.open = false;
            },
            highlightMatch(name) {
                if (!this.search) return name;
                const regex = new RegExp(`(${this.search})`, 'gi');
                return name.replace(regex, '<mark class="bg-blue-200">$1</mark>')
            }
        }
    }

    function searchableDropdownAgent() {
        return {
            open: false,
            search: '',
            selectedId: '',
            selectedAgent: @json(optional($agent ?? null)->name ?? ''),
            all: @json(optional($agents ?? collect())->map(fn($a) => ['id' => $a->id, 'name' => $a->name])),
            filtered: [],
            init() {
                this.filtered = [...this.all];
                this.selectedId = '';
                this.selectedAgent = ''; // Ensure it's reset when there's no agent
            },
            filterOptions() {
                const term = this.search.toLowerCase();
                this.filtered = this.all.filter(a => a.name.toLowerCase().includes(term));
            },
            select(option) {
                this.selectedId = option.id;
                this.selectedAgent = option.name;
                this.search = '';
                this.open = false;
            },
            highlightMatch(name) {
                if (!this.search) return name;
                const regex = new RegExp(`(${this.search})`, 'gi');
                return name.replace(regex, '<mark class="bg-blue-200">$1</mark>');
            }
        };
    }

    function searchableDropdownSupplier() {
    return {
        open: false,
        search: '',
        selectedId: '',
        selectedSupplier: '',
        all: @json($suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name])),
        filtered: [],
        init() {
            this.filtered = [...this.all];
            this.$watch('selectedSupplier', (newValue) => {
                this.triggerSupplierChange(newValue);
            });
        },
        filterOptions() {
            const term = this.search.toLowerCase();
            this.filtered = this.all.filter(s => s.name.toLowerCase().includes(term));
        },
        select(option) {
            this.selectedId = option.id;
            this.selectedSupplier = option.name;
            this.search = '';
            this.open = false;
        },
        highlightMatch(name) {
            if (!this.search) return name;
            const regex = new RegExp(`(${this.search})`, 'gi');
            return name.replace(regex, '<mark class="bg-blue-200">$1</mark>');
        },
        triggerSupplierChange(supplierName) {
            const formTaskContainer = document.getElementById('form-task-container');
            if (!formTaskContainer) return;

            const companyIdData = formTaskContainer.getAttribute('data-company-id');
            const tboTaskUrl = "{!! route('tasks.get-tbo', ['companyId' => '__companyId__']) !!}".replace('__companyId__', companyIdData);

            formTaskContainer.innerHTML = '';

            if (supplierName === 'Magic Holiday') {
                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'supplier_ref';
                input.placeholder = 'Reference';
                input.classList.add('input', 'w-full', 'mt-2', 'rounded-lg', 'border',
                    'border-gray-300', 'dark:border-gray-700', 'dark:bg-gray-800',
                    'dark:text-gray-300', 'p-3');
                formTaskContainer.appendChild(input);
            } else if (supplierName === 'TBO Holiday') {
                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'supplier_ref';
                input.placeholder = 'Coming Soon...';
                input.disabled = true;
                input.classList.add('input', 'w-full', 'mt-2', 'rounded-lg', 'border',
                    'border-gray-300', 'dark:border-gray-700', 'dark:bg-gray-800',
                    'dark:text-gray-300', 'p-3', 'disabled:opacity-75', 'disabled:cursor-not-allowed');
                formTaskContainer.appendChild(input);
            } else if (supplierName === 'Amadeus') {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.name = 'task_file';
                fileInput.id = 'amadeus-upload-task';
                fileInput.classList.add('bg-white', 'dark:bg-dark', 'p-2', 'shadow-md', 'rounded-md', 'w-full', 'mt-2');
                formTaskContainer.appendChild(fileInput);
            } else if (supplierName !== '') {
                const div = document.createElement('div');
                div.classList.add('text-red-500', 'text-sm', 'font-semibold', 'mt-2');
                div.innerHTML = 'API not available for this supplier';
                formTaskContainer.appendChild(div);
            }
        }
    }
}

</script>
