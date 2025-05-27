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
    <!-- @if ($queueTasks->isNotEmpty())
<div class="flex flex-col gap-5 w-full">
        <h2 class="text-3xl font-bold">Queue</h2>
        <div class="flex flex-col gap-2">
            @foreach ($queueTasks->take(3) as $task)
<div class="p-2 bg-white dark:bg-gray-700 rounded-md shadow-md mb-2">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ $task->reference }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-300">{{ $task->agent->name ?? 'No Agent Set' }}</p>
                    </div> -->
    <!-- <div>
                        <a href="javascript:void(0);" class="text-blue-500 dark:text-blue-400" @click="editTaskModal = true">View</a>
                    </div> -->
    <!-- </div>
            </div>
@endforeach
            @if ($queueTasks->count() > 3)
<div class="p-2 rounded-md mb-2 bg-gradient-to-b from-white min-h-10">
                <div class="flex justify-between items-center">
                </div>
            </div>
@endif
        </div>
        <a class="font-semibold hover:text-blue-600" href="{{ route('tasks.queue') }}">View All</a>
    </div>
@endif -->

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
                        Add Task For Specific Supplier
                    </div>
                    <hr>
                    <form id="agent-supplier-task" action="{{ route('tasks.agent.upload') }}"
                        class="p-4 flex flex-col gap-2" method="POST" enctype="multipart/form-data">
                        @csrf
                        @unlessrole('agent')
                            <select name="agent_id" id="task-agent-id"
                                class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-black">
                                <option value="" class="">Select Agent</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}" data-client="{{ $agent }}">
                                        {{ $agent->name }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="agent_id" id="agent_id_task_modal"
                                value="{{ Auth()->user()->agent->id }}">
                        @endunlessrole
                        <select name="supplier_id" id="select-supplier-task"
                            class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-black">
                            <option value="">Select Supplier</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" data-supplier="{{ $supplier }}">
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        <div id="form-task-container" class="mt-2" data-company-id="{{ $companyId }}">

                        </div>
                    </form>
                    <hr>
                    <div class="p-4 flex justify-between items-center">
                        <button @click="addTaskModal = false" class="text-red-500">Cancel</button>
                        <x-primary-button type="submit" form="agent-supplier-task">Submit</x-primary-button>
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
                    <div class="dataTable-container h-max">
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
                                        Enable/Disable</th> <!-- New column header -->
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">
                                        Reference</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">GDS
                                        Office Id</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Client
                                        Name</th>
                                    @if (Auth()->user()->role_id == \App\Models\Role::COMPANY)
                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">
                                            Branch Name</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">
                                            Agent Name</th>
                                    @endif
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Type
                                    </th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">
                                        Billing</th>
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
                                        <tr data-price="{{ $task->price }}"
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
                                                                    <div class="flex items-center gap-4">
                                                                        <label for="client_id"
                                                                            class="w-2/4 sm:w-1/3 text-left text-base">Client:</label>
                                                                        <select name="client_id"
                                                                            id="tasks_client_id_{{ $task->id }}"
                                                                            data-task-id="{{ $task->id }}"
                                                                            class="client-select border border-gray-300 dark:border-gray-600 p-2 rounded-md w-2/4 sm:w-2/3 text-base">
                                                                            <option value="">Choose Client
                                                                            </option>
                                                                            @foreach ($clients as $client)
                                                                                <option value="{{ $client->id }}"
                                                                                    {{ $task->client && $task->client->id === $client->id ? 'selected' : '' }}>
                                                                                    {{ $client->name }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
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

                                            <!-- New column with switch button -->
                                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                                {{ $task->reference }}</td>
                                            <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                                {{ $task->gds_office_id ?? 'Not Set' }}
                                            </td>
                                            <td
                                                class="p-3 flex justify-between gap-2 text-sm font-semibold text-gray-900 dark:text-gray-300 relative">
                                                <p class="{{ $task->client ?? 'no-client' }}">
                                                    {{ $task->client_name ?? 'Not Set' }}
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
                        </table>

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



    });
</script>
