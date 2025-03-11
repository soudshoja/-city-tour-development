<x-app-layout>
    @if($queueTasks->isNotEmpty())
    <div class="flex flex-col gap-5 w-full">
        <h2 class="text-3xl font-bold">Queue</h2>
        <div class="flex flex-col gap-2">
            @foreach($queueTasks->take(3) as $task)
            <div class="p-2 bg-white dark:bg-gray-700 rounded-md shadow-md mb-2">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ $task->reference }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-300">{{ $task->agent->name ?? 'No Agent Set'}}</p>
                    </div>
                    <!-- <div>
                        <a href="javascript:void(0);" class="text-blue-500 dark:text-blue-400" @click="importTaskModal = true">View</a>
                    </div> -->
                </div>
            </div>
            @endforeach
            @if($queueTasks->count() > 3)
            <div class="p-2 rounded-md mb-2 bg-gradient-to-b from-white min-h-10">
                <div class="flex justify-between items-center">
                </div>
            </div>
            @endif
        </div>
        <a class="font-semibold hover:text-blue-600" href="{{ route('tasks.queue') }}">View All</a>
    </div>
    @endif

    <div
        class="flex justify-between items-center gap-5 my-3 ">



        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Tasks List</h2>
            <div data-tooltip="number of tasks" class="relative w-12 h-12 flex items-center justify-center DarkBGcolor dark:!bg-gray-700 dark:!hover:bg-gray-600 rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $taskCount }}</span>
            </div>
        </div>
        <div
            x-data="{ addTaskModal: false }"
            class="flex items-center gap-5">
            <div
                @click="addTaskModal = true"
                class="p-2 text-center bg-white rounded-full shadow group hover:bg-black dark:hover:bg-gray-600 dark:bg-gray-700 cursor-pointer" data-tooltip="Add Task By Supplier">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="stroke-black dark:stroke-gray-300 group-hover:stroke-white group-focus:stroke-white">
                    <path d="M15 12L12 12M12 12L9 12M12 12L12 9M12 12L12 15" stroke="" stroke-width="1.5" stroke-linecap="round" />
                    <path d="M7 3.33782C8.47087 2.48697 10.1786 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 10.1786 2.48697 8.47087 3.33782 7" stroke="" stroke-width="1.5" stroke-linecap="round" />
                </svg>

            </div>
            <div
                x-cloak
                x-show="addTaskModal"
                class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-20">
                <div
                    @click.away="addTaskModal = false"
                    class="bg-white rounded shadow">
                    <div class="p-4 flex justify-between items-center">
                        Add Task For Specific Supplier
                    </div>
                    <hr>
                    @csrf
                    @method('PUT')
                    <div class="p-4 inline-flex flex-col gap-2">
                        <input type="text" name="" id="" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $importedTask->reference }}" readonly>
                        <input type="text" name="" id="" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $importedTask->additional_info }} - {{ $importedTask->venue }}" readonly>
                        <input type="text" name="" id="" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $importedTask->supplier->name }}" readonly>
                        <input type="text" name="" id="" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $importedTask->price }}" readonly>
                        <input type="text" name="" id="" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $importedTask->type }}" readonly>
                        <select name="client_id" id="agent_id" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full">
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{!$importedTask->client ?? $client->id == $importedTask->client->id ? 'selected' : ''}}>{{ $client->name }}</option>
                            @endforeach
                        </select>
                        <select name="agent_id" id="agent_id" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full">
                            @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" {{@$importedTask->agent ?? $agent->id == $importedTask->agent_id ? 'selected' : ''}}>{{ $agent->name }}</option>
                            @endforeach
                        </select>
                        @else
                        <input type="hidden" name="agent_id" id="agent_id" value="{{ Auth()->user()->agent->id }}">
                        @endunlessrole
                        <select name="supplier_id" id="select-supplier-task" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full text-black">
                            <option value="">Select Supplier</option>
                            @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{!$supplier->id == $importedTask->supplier_id ? 'selected' : ''}}>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        <div id="form-task-container" class="mt-2" data-company-id="{{ auth()->user()->company->id }}">

                        </div>
                        </form>
                        <hr>
                        <div class="p-4 flex justify-between items-center">
                            <button @click="addTaskModal = false" class="text-red-500">Cancel</button>
                            <x-primary-button type="submit" form="agent-supplier-task">Submit</x-primary-button>
                        </div>
                    </div>
                </div>
                <!-- <div data-tooltip="Reload" class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                </svg>
            </div> -->

                <!-- <button class="rounded-md shadow-md p-2 bg-blue-600 hover:bg-blue-400 text-white font-semibold" type="submit" form="uploadTaskForm">submit</button> -->
                <div class="">
                    <form id="uploadTaskForm" action="{{ route('tasks.upload') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <button id="upload-task-submit" class="group cursor-pointer" type="submit" data-tooltip-left="Click the icon to submit">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="stroke-black dark:stroke-gray-300 group-hover:stroke-blue-500 group-focus:stroke-blue-500">
                                <path d="M18 10L13 10" stroke-width="1.5" stroke-linecap="round" />
                                <path d="M10 3H16.5C16.9644 3 17.1966 3 17.3916 3.02567C18.7378 3.2029 19.7971 4.26222 19.9743 5.60842C20 5.80337 20 6.03558 20 6.5" stroke-width="1.5" />
                                <path d="M2 6.94975C2 6.06722 2 5.62595 2.06935 5.25839C2.37464 3.64031 3.64031 2.37464 5.25839 2.06935C5.62595 2 6.06722 2 6.94975 2C7.33642 2 7.52976 2 7.71557 2.01738C8.51665 2.09229 9.27652 2.40704 9.89594 2.92051C10.0396 3.03961 10.1763 3.17633 10.4497 3.44975L11 4C11.8158 4.81578 12.2237 5.22367 12.7121 5.49543C12.9804 5.64471 13.2651 5.7626 13.5604 5.84678C14.0979 6 14.6747 6 15.8284 6H16.2021C18.8345 6 20.1506 6 21.0062 6.76946C21.0849 6.84024 21.1598 6.91514 21.2305 6.99383C22 7.84935 22 9.16554 22 11.7979V14C22 17.7712 22 19.6569 20.8284 20.8284C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.8284C2 19.6569 2 17.7712 2 14V6.94975Z" stroke-width="1.5" />
                            </svg>
                        </button>
                        <input class="bg-white dark:bg-dark p-2 shadow-md rounded-md" type="file" name="task_file" id="upload-task">
                    </form>
                </div>
            </div>


        </div>

        <div class="tableCon">
            <div class="content-70">
                <div class="p-2 bg-white dark:bg-gray-700 rounded-lg shadow-md">
                    <div class="customResponsiveClass flex flex-col md:flex-row justify-between p-2 gap-3">
                        <div class="relative w-full">
                            <input type="text" placeholder="Find fast and search here..." class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider" id="searchInput">

                            <button data-tooltip="start searching" type="button" class="DarkBGcolor dark:!bg-gray-700 dark:!hover:bg-gray-600 absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1"
                                id="searchButton">
                                <svg class="mx-auto" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="11.5" cy="11.5" r="9.5" stroke="#fff" stroke-width="1.5" opacity="0.5" class="dark:stroke-gray-300"></circle>
                                    <path d="M18.5 18.5L22 22" stroke="#fff" stroke-width="1.5" stroke-linecap="round" class="dark:stroke-gray-300"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="flex customCenter gap-5 w-full justify-end">
                            <!-- <button class="flex px-3 py-2 gap-2 city-light-yellow rounded-lg shadow-sm items-center text-xs md:text-sm">
                            <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path fill="#333333" d="M10 19h4v-2h-4zm-4-6h12v-2H6zM3 5v2h18V5z" />
                            </svg>
                            <span class="dark:text-black">Customize</span>
                        </button> -->

                            <button id="toggleFilters" class="flex px-3 py-2 gap-2 city-light-yellow rounded-lg shadow-sm items-center text-xs md:text-sm">
                                <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                                    <path fill="#333333" d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                                </svg>
                                <span class="text-xs md:text-sm dark:text-black">Filters</span>
                            </button>

                            <!-- <button class="flex px-3 py-2 gap-2 city-light-yellow rounded-lg shadow-sm items-center text-xs md:text-sm">
                            <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewbox="0 0 24 24">
                                <path fill="#333333" d="m8.71 7.71l11 5.41v15a1 1 0 0 0 2 0v5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42m21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1h5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1" />
                            </svg>
                            <span class="text-xs md:text-sm dark:text-black">Export</span>
                        </button> -->
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
                                                <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                    <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" rx="4" />
                                                </svg>
                                            </label>
                                        </th>
                                        @endcan
                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Actions</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Task Id</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Client Name</th>

                                        @if(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Branch Name</th>

                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Agent Name</th>
                                        @endif
                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Type</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Billing</th>
                                        @can('viewPrice', 'App\Models\Task')
                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Price</th>
                                        @endcan
                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Status</th>
                                        <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Supplier</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if($tasks->isEmpty())
                                    <tr>
                                        <td colspan="9" class="text-center p-5 text-gray-500 dark:text-gray-300">No tasks found</td>
                                    </tr>
                                    @else
                                    @foreach($tasks as $task)
                                    <tr
                                        data-price="{{ $task->price }}" data-supplier-id="{{ $task->supplier->id }}"
                                        data-branch-id="{{ $task->agent->branch->id }}" data-agent-id="{{ $task->agent_id }}" data-status="{{ $task->status }}" data-type="{{ $task->type }}" data-client-id="{{ $task->client ? $task->client->id : null }}" data-task-id="{{ $task->id }}" class="taskRow">
                                        @can('create', 'App\Models\Invoice')
                                        <td>
                                            <label class="custom-checkbox" data-tooltip="select task">
                                                <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox text-gray-900 dark:text-gray-300" value="{{ $task->id }}" {{ $task->invoiceDetail ? 'disabled' : '' }}>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" class="checkbox-svg">
                                                    <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" rx="4" />
                                                </svg>
                                            </label>
                                        </td>
                                        @endcan
                                        <td class="p-3 text-sm flex gap-3 justify-center">
                                            <a data-tooltip="see task" href="javascript:void(0);" class="viewTask text-blue-600 dark:text-blue-300" data-task-id="{{ $task->id }}" data-task-url="{{ route('tasks.show', $task->id) }}">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                                    <g fill="none" stroke="currentColor" stroke-width="1">
                                                        <path d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z" opacity=".5" />
                                                        <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z" />
                                                    </g>
                                                </svg>
                                            </a>
                                            <div x-data="{ importTaskModal_{{ $task->id }}: false }">
                                                <a data-tooltip="edit task" href="javascript:void(0);" @click="importTaskModal_{{ $task->id }} = true">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                                        <path fill="none" stroke="currentColor" stroke-width="1.5" d="M3 17l-2 4l4-2l14-14l-2-2L3 17Z" />
                                                    </svg>
                                                </a>
                                                <div
                                                    x-show="importTaskModal_{{ $task->id }}"
                                                    x-cloak
                                                    class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-20">
                                                    <form id="imported-task-form" action="{{ route('tasks.update', $task->id)}}" method="post" class="inline-flex flex-col gap-2 items-center">
                                                        <div
                                                            @click.away="importTaskModal_{{ $task->id }}  = false"
                                                            class="bg-white rounded-md border-2w-80">
                                                            <div class="flex justify-between p-4">
                                                                <p class="font-semibold">
                                                                    Update the following information if needed
                                                                </p>
                                                                <button
                                                                    type="button"
                                                                    @click="importTaskModal_{{ $task->id }} = false"
                                                                    class="text-red-500 font-bold">
                                                                    &times;
                                                                </button>
                                                            </div>
                                                            <hr>
                                                            @csrf
                                                            @method('PUT')
                                                            <div class="p-4 inline-flex flex-col gap-2">
                                                                <input type="text" name="" id="" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $task->reference }}" readonly>
                                                                <input type="text" name="" id="" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $task->additional_info }} - {{ $task->venue }}" readonly>
                                                                <input type="text" name="" id="" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $task->supplier->name }}" readonly>
                                                                <input type="text" name="" id="" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $task->price }}" readonly>
                                                                <input type="text" name="" id="" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $task->type }}" readonly>
                                                                <select name="client_id" id="agent_id" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full">
                                                                    @foreach($clients as $client)
                                                                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <select name="agent_id" id="agent_id" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full">
                                                                    @foreach($agents as $agent)
                                                                    <option value="{{ $agent->id }}" {{ $task->agent ?? $agent->id == $task->agent_id ? 'selected' : ''}}>{{ $agent->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                                <select name="supplier_id" id="supplier_id" class="border border-gray-300 dark:border-gray-600 p-2 rounded-md w-full">
                                                                    @foreach($suppliers as $supplier)
                                                                    <option value="{{ $supplier->id }}" {{ $supplier->id == $task->supplier_id ? 'selected' : ''}}>{{ $supplier->name }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <x-primary-button type="submit" class="min-w-72 mt-4 justify-center" form="imported-task-form"> Update </x-primary-button>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $task->reference }}</td>
                                        <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $task->client ? $task->client->name : 'Not Set' }}</td>
                                        @if(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
                                        <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->agent->branch->name ?? 'Not Set'}}</td>
                                        <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->agent->name }}</td>
                                        @endif
                                        <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $task->type }}</td>
                                        <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">
                                            @if($task->invoiceDetail)
                                            <span
                                                data-invoice-number="{{ $task->invoiceDetail->invoice_number }}"
                                                class="invoiceModal badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium badge-outline-success">
                                                {{ $task->invoiceDetail->invoice_number }}
                                            </span>
                                            @else
                                            <span class="badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium badge-outline-danger">
                                                Not Yet
                                            </span>
                                            @endif
                                        </td>
                                        @can('viewPrice', 'App\Models\Task')
                                        <td class="p-3 text-sm font-semibold DarkBTextcolor dark:text-gray-300">{{ $task->price }}</td>
                                        @endcan
                                        <td>
                                            <span class="badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium
                                        {{ $task->status === 'Completed' ? 'badge-outline-success' : '' }}
                                        {{ $task->status === 'Assigned' ? 'badge-outline-assigned' : '' }}
                                        {{ $task->status === 'Booked' ? 'badge-outline-booked' : '' }}

                                            {{ $task->status === 'Pending' ? 'badge-outline-danger' : '' }}

                                            {{ $task->status === 'Confirmed' ? 'badge-outline-primary' : '' }}
                                            {{ $task->status === 'Cancelled' ? 'badge-outline-danger' : '' }}
                                            {{ $task->status === 'Hold' ? 'badge-outline-danger' : '' }}">
                                                {{ $task->status }}
                                            </span>
                                        </td>

                                        <td class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $task->supplier->name }}
                                        </td>
                                    </tr>
                                    @endforeach
                                    @endif
                                </tbody>
                            </table>

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
                <div id="filterBox" class="panel w-full xl:mt-0 rounded-lg h-auto ">

                    <div class="flex justify-between items-center gap-5 mb-5 FiltersHeader">
                        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Filters</h1>
                        <div class="filter-badge flex items-center gap-3 DarkBGcolor  
                           FilltersAppliedPy FilltersAppliedPx  dark:bg-gray-700 dark:hover:bg-gray-600 rounded-full px-4 py-2 shadow">
                            <span class="font-semibold">0 applied</span>
                            <button id="clearFilters" data-tooltip="Clear Filters" class="transition">
                                &times;
                            </button>
                        </div>
                    </div>

                    <div class="w-full mb-5">
                        <div class="flex justify-between">
                            <div class="flex flex-wrap gap-4">

                                <div id="selected-statuses" class="flex flex-wrap gap-2">
                                    <!-- Selected statuses will appear here dynamically -->
                                </div>

                                <div id="selected-types" class="flex flex-wrap gap-2">
                                    <!-- Selected types will appear here dynamically -->
                                </div>

                                <div id="selected-suppliers" class="flex flex-wrap gap-2">
                                    <!-- Selected suppliers will be displayed here dynamically -->
                                </div>

                                <div id="selected-agents" class="flex flex-wrap gap-2">
                                    <!-- Selected agents will be displayed here dynamically -->
                                </div>

                                <div id="selected-branches" class="flex flex-wrap gap-2">
                                    <!-- Selected branches will be displayed here dynamically -->
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-5">
                        <div class="w-full flex gap-5">
                            <div class="w-full gap-5 space-y-8">

                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-5 FilltersAppliedPx FilltersAppliedPy shadow-md hover:shadow-lg">
                                    <div class="flex items-center">
                                        <input data-tooltip="filter by price" type="range" min="1" max="1000" value="500" id="priceRange"
                                            class="w-full h-2 bg-gray-200 dark:bg-gray-600 rounded-lg appearance-none">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            <span id="ShowTaskFilters" class="font-medium text-gray-800 dark:text-gray-100">0</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex gap-4 items-center">
                                    <div data-tooltip="Status" class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 12 12" class="icon">
                                            <path fill-rule="evenodd" d="M6 10a4 4 0 1 0 0-8a4 4 0 0 0 0 8m0 2A6 6 0 1 0 6 0a6 6 0 0 0 0 12" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="bg-white flex-1 relative rounded-lg shadow-md hover:shadow-lg">
                                        <select name="status_id" id="status_id" class="selectize w-full appearance-none bg-transparent
                                         outline-none cursor-pointer focus:outline-none focus:ring-0">
                                            <option selected value="" class="">Select Status</option>
                                            <option value="Completed">Completed</option>
                                            <option value="Assigned">Assigned</option>
                                            <option value="Booked">Booked</option>
                                            <option value="Pending">Pending</option>
                                            <option value="Confirmed">Confirmed</option>
                                            <option value="Cancelled">Cancelled</option>
                                            <option value="Hold">Hold</option>
                                        </select>

                                    </div>

                                </div>

                                <div class="flex gap-4 items-center">
                                    <div data-tooltip="Type" class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                            <path d="M7.5 20q-1.45 0-2.475-1.025T4 16.5t1.025-2.475T7.5 13h11q1.45 0 2.475 1.025T22 16.5t-1.025 2.475T18.5 20zm-2-9q-1.45 0-2.475-1.025T2 7.5t1.025-2.475T5.5 4h11q1.45 0 2.475 1.025T20 7.5t-1.025 2.475T16.5 11z"></path>
                                        </svg>
                                    </div>

                                    <div class="bg-white flex-1 relative rounded-lg shadow-md hover:shadow-lg">
                                        <select name="type_id" id="type_id" class="selectize w-full appearance-none bg-transparent
                                         outline-none cursor-pointer focus:outline-none focus:ring-0">
                                            <option selected value="" class="">Select Type</option>
                                            @foreach($types as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="flex gap-4 items-center">
                                    <div data-tooltip="Supplier" class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                            <path d="M16.923 15.02q-.154-.59-.6-1.1q-.446-.512-1.135-.766l-6.992-2.62q-.136-.05-.27-.061t-.307-.012H7v-2.34q0-.385.177-.742q.177-.358.5-.575l4.885-3.479q.224-.159.458-.229q.234-.069.478-.069t.49.07t.45.228l4.885 3.479q.323.217.5.575T20 8.12v6.898zM14.5 8.441q.162 0 .283-.12q.12-.122.12-.284t-.12-.282q-.121-.122-.283-.122t-.283.122q-.12.121-.12.282t.12.283q.121.121.283.121m-2 0q.162 0 .283-.12q.12-.122.12-.284t-.12-.282q-.121-.122-.283-.122t-.283.122q-.12.121-.12.282t.12.283q.121.121.283.121m2 2q.162 0 .283-.12q.12-.122.12-.284t-.12-.282q-.121-.122-.283-.122t-.283.122q-.12.121-.12.282t.12.283q.121.121.283.121m-2 0q.162 0 .283-.12q.12-.122.12-.284t-.12-.282q-.121-.122-.283-.122t-.283.122q-.12.121-.12.282t.12.283q.121.121.283.121m1.01 11.23q.198.055.481.048q.284-.006.48-.06L21 19.5q0-.696-.475-1.136q-.475-.441-1.179-.441h-5.158q-.498 0-1.02-.06q-.524-.061-.977-.22l-1.572-.526q-.161-.056-.236-.211t-.025-.315q.05-.139.202-.21q.152-.072.313-.016l1.433.502q.408.146.893.217q.486.07 1.053.07h1.202q.283 0 .453-.162t.17-.456q0-.388-.309-.809q-.308-.421-.716-.565l-6.021-2.21q-.137-.042-.273-.074q-.137-.032-.292-.032H6.385v6.737zM2.384 19.922q0 .46.308.768q.309.309.769.309h.846q.46 0 .768-.309q.309-.308.309-.768v-6q0-.46-.309-.768q-.309-.309-.768-.309h-.846q-.46 0-.769.309q-.308.309-.308.768z" />
                                        </svg>
                                    </div>

                                    <div class="bg-white flex-1 relative rounded-lg shadow-md hover:shadow-lg">
                                        <select name="supplier_id" id="supplier_id" class="selectize w-full appearance-none bg-transparent
                                         outline-none cursor-pointer focus:outline-none focus:ring-0">
                                            <option selected value="" class="">Select Supplier</option>
                                            @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="flex gap-4 items-center">
                                    <div data-tooltip="Agent" class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="16" viewBox="0 0 640 512">
                                            <path d="M96 224c35.3 0 64-28.7 64-64s-28.7-64-64-64s-64 28.7-64 64s28.7 64 64 64m448 0c35.3 0 64-28.7 64-64s-28.7-64-64-64s-64 28.7-64 64s28.7 64 64 64m32 32h-64c-17.6 0-33.5 7.1-45.1 18.6c40.3 22.1 68.9 62 75.1 109.4h66c17.7 0 32-14.3 32-32v-32c0-35.3-28.7-64-64-64m-256 0c61.9 0 112-50.1 112-112S381.9 32 320 32S208 82.1 208 144s50.1 112 112 112m76.8 32h-8.3c-20.8 10-43.9 16-68.5 16s-47.6-6-68.5-16h-8.3C179.6 288 128 339.6 128 403.2V432c0 26.5 21.5 48 48 48h288c26.5 0 48-21.5 48-48v-28.8c0-63.6-51.6-115.2-115.2-115.2m-223.7-13.4C161.5 263.1 145.6 256 128 256H64c-35.3 0-64 28.7-64 64v32c0 17.7 14.3 32 32 32h65.9c6.3-47.4 34.9-87.3 75.2-109.4" />
                                        </svg>
                                    </div>

                                    <div class="bg-white flex-1 relative rounded-lg shadow-md hover:shadow-lg">
                                        <select name="agent_id" id="agent_id" class="selectize w-full appearance-none bg-transparent
                                         outline-none cursor-pointer focus:outline-none focus:ring-0">
                                            <option selected value="" class="">Select Agent</option>
                                            @foreach($agents as $agent)
                                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                @role('Company')
                                <div class="flex gap-4 items-center">
                                    <div data-tooltip="Branch" class="p-3 bg-white dark:bg-gray-700 rounded-full shadow-md hover:bg-gray-300/50 dark:hover:bg-gray-700/50 flex cursor-pointer items-center justify-center transition-all duration-200">
                                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" d="M3.464 3.464C2 4.93 2 7.286 2 12s0 7.071 1.464 8.535C4.93 22 7.286 22 12 22s7.071 0 8.535-1.465C22 19.072 22 16.714 22 12s0-7.071-1.465-8.536C19.072 2 16.714 2 12 2S4.929 2 3.464 3.464M8.03 5.97a.75.75 0 0 1 0 1.06l-.22.22H8c1.68 0 3.155.872 4 2.187a4.75 4.75 0 0 1 4-2.187h.19l-.22-.22a.75.75 0 0 1 1.06-1.06l1.5 1.5a.75.75 0 0 1 0 1.06l-1.5 1.5a.75.75 0 1 1-1.06-1.06l.22-.22H16A3.25 3.25 0 0 0 12.75 12v6a.75.75 0 0 1-1.5 0v-6A3.25 3.25 0 0 0 8 8.75h-.19l.22.22a.75.75 0 1 1-1.06 1.06l-1.5-1.5a.75.75 0 0 1 0-1.06l1.5-1.5a.75.75 0 0 1 1.06 0" clip-rule="evenodd" />
                                        </svg>
                                    </div>

                                    <div class="bg-white flex-1 relative rounded-lg shadow-md hover:shadow-lg">
                                        <select name="branch_id" id="branch_id" class="selectize w-full appearance-none bg-transparent outline-none cursor-pointer focus:outline-none focus:ring-0">
                                            <option selected value="" class="">Select Branch</option>
                                            @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                @endrole

                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div id="floatingActions" class="hidden flex justify-between gap-5 fixed CuzPostion bg-[#f6f8fa] dark:bg-gray-800 shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] dark:shadow-[0_0_4px_2px_rgb(255_255_255_/_10%)] rounded-lg w-auto h-auto z-50 p-3">

                <div class="flex justify-between gap-5 items-center h-full">
                    <button id="createInvoiceBtn" data-route="{{ route('invoice.create') }}" class="flex px-5 py-3 gap-3 btn-success hover:bg-[#00ab5599] rounded-lg shadow-sm items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                            <path fill="#ffffff" d="M2 12c0-2.8 1.6-5.2 4-6.3V3.5C2.5 4.8 0 8.1 0 12s2.5 7.2 6 8.5v-2.2c-2.4-1.1-4-3.5-4-6.3m13-9c-5 0-9 4-9 9s4 9 9 9s9-4 9-9s-4-9-9-9m5 10h-4v4h-2v-4h-4v-2h4V7h2v4h4z" />
                        </svg>
                        <span class="text-sm">Create Invoice</span>
                    </button>
                    <!-- <button class="flex px-5 py-3 gap-3 btn-danger hover:bg-[#e7515aa8] rounded-lg shadow-sm items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#ffffff" d="M12 2c5.53 0 10 4.47 10 10s-4.47 10-10 10S2 17.53 2 12S6.47 2 12 2m5 5h-2.5l-1-1h-3l-1 1H7v2h10zM9 18h6a1 1 0 0 0 1-1v-7H8v7a1 1 0 0 0 1 1" />
                    </svg>
                    <span class="text-sm">Delete</span>
                </button> -->
                </div>
                <div id="closeTaskFloatingActions" class="flex cursor-pointer items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 12 12">
                        <path fill="#E53935" d="M1.757 10.243a6.001 6.001 0 1 1 8.488-8.486a6.001 6.001 0 0 1-8.488 8.486M6 4.763l-2-2L2.763 4l2 2l-2 2L4 9.237l2-2l2 2L9.237 8l-2-2l2-2L8 2.763Z" />
                    </svg>
                </div>
            </div>
        </div>
        <div id="taskInvoicePlaceholder" class="hidden fixed inset-0 z-30 bg-gray-800 bg-opacity-50 flex justify-center items-center">
            <div id="invoiceModalContent">
                <div id="invoiceModalBody" class="rounded-t-md bg-white">
                </div>
                <div id="invoiceFooter" class="inline-flex justify-center bg-white w-full p-3 rounded-b-md">
                    <x-primary-button class="font-bold text-lg">Edit</x-primary-button>
                </div>
            </div>
        </div>

        @vite('resources/js/tasks.js')
        <script>
            let invoicesModal = document.querySelectorAll('.invoiceModal');

            invoicesModal.forEach(invoice => {
                invoice.addEventListener('click', function() {

                    let invoiceNumber = invoice.getAttribute('data-invoice-number');
                    let url = `{{ route('invoice.show', '__invoiceNumber__') }}`.replace('__invoiceNumber__', invoiceNumber);

                    let modalInvoice = document.getElementById('taskInvoicePlaceholder');
                    let invoiceBody = document.getElementById('invoiceModalBody');

                    fetch(url)
                        .then(response => response.text())
                        .then(data => {
                            invoiceBody.innerHTML = data;
                            modalInvoice.classList.remove('hidden');

                            let invoiceFooter = document.getElementById('invoiceFooter');
                            invoiceFooter.querySelector('button').addEventListener('click', function() {
                                window.location.href = `{{ route('invoice.edit', '__invoiceNumber__') }}`.replace('__invoiceNumber__', invoiceNumber);
                            });
                        })
                });
            });

            document.addEventListener('click', function(event) {
                let modalInvoice = document.getElementById('taskInvoicePlaceholder');
                let invoiceBody = document.getElementById('invoiceModalBody');

                if (!invoiceBody.contains(event.target) && !event.target.closest('.invoiceModal')) {
                    modalInvoice.classList.add('hidden');
                }
            });

            document.getElementById('upload-task').addEventListener('change', function() {
                submitBtn = document.querySelector('#upload-task-submit');
                console.log(submitBtn);
                submitBtn.focus();
            });

            document.getElementById('select-supplier-task').addEventListener('change', function() {
                let selectedSupplier = this.options[this.selectedIndex].getAttribute('data-supplier');
                let supplier = JSON.parse(selectedSupplier);
                let formTaskContainer = document.getElementById('form-task-container');
                let companyIdData = formTaskContainer.getAttribute('data-company-id');
                let tboTaskUrl = "{!! route('tasks.get-tbo', ['companyId' => '__companyId__']) !!}".replace('__companyId__', companyIdData);

                formTaskContainer.innerHTML = '';
                console.log(supplier.name);
                console.log(supplier.name == 'Magic Holiday');
                if (supplier.name === 'Magic Holiday') {
                    let input = document.createElement('input');
                    input.type = 'text';
                    input.name = 'supplier_ref';
                    input.placeholder = 'Reference';
                    input.classList.add('input', 'w-full', 'mt-2', 'rounded-lg', 'border', 'border-gray-300', 'dark:border-gray-700', 'dark:bg-gray-800', 'dark:text-gray-300', 'p-3');
                    formTaskContainer.appendChild(input);
                } else if (supplier.name === 'TBO Holiday') {
                    document.getElementById('task-agent-id').classList.add('hidden');
                    let a = document.createElement('a');
                    a.href = tboTaskUrl;
                    a.innerHTML = 'Import Task';
                    a.classList.add('bg-blue-500', 'text-white', 'rounded-lg', 'p-2', 'text-center', 'w-full', 'font-semibold');
                    a.innerHTML = 'Import Task';

                    formTaskContainer.appendChild(a);
                } else {
                    let div = document.createElement('div');
                    div.classList.add('text-red-500', 'text-sm', 'font-semibold', 'mt-2');
                    div.innerHTML = 'API not available for this supplier';
                    formTaskContainer.appendChild(div);
                }

            });
        </script>
</x-app-layout>