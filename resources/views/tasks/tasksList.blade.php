<x-app-layout>

    @include('tasks.tasksjs')

    @if($importedTask = session('importedTask'))
    <div
        x-show="importModal"
        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50 z-20">
        <div
            @click.away="importModal = false"
            class="bg-white rounded-md border-2 justify-center align-middles p-4 w-80">
            <form action="{{ route('tasks.update', $importedTask->id)}}" method="post" class="inline-flex flex-col gap-2">
                @csrf
                @method('PUT')
                <input type="text" name="" id="" class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $importedTask->reference }}" readonly>
                <input type="text" name="" id="" class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $importedTask->additional_info }} - {{ $importedTask->venue }}" readonly>
                <input type="text" name="" id="" class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $importedTask->supplier->name }}" readonly>
                <input type="text" name="" id="" class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $importedTask->price }}" readonly>
                <input type="text" name="" id="" class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full" value="{{ $importedTask->type }}" readonly>
                <select name="client_id" id="agent_id" class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full">
                    @foreach($clients as $client)
                    <option value="{{ $client->id }}" {{!$importedTask->client ?? $client->id == $importedTask->client->id ? 'selected' : ''}}>{{ $client->name }}</option>
                    @endforeach
                </select>
                <select name="agent_id" id="agent_id" class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full">
                    @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" {{@$importedTask->agent ?? $agent->id == $importedTask->agent_id ? 'selected' : ''}}>{{ $agent->name }}</option>
                    @endforeach
                </select>
                <select name="supplier_id" id="supplier_id" class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full">
                    @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" {{!$supplier->id == $importedTask->supplier_id ? 'selected' : ''}}>{{ $supplier->name }}</option>
                    @endforeach
                </select>
                <x-primary-button type="submit" class="w-full mt-4"> Update </x-primary-button>
            </form>
        </div>
    </div>
    @endif


    <!-- page title -->
    <div class="flex justify-between items-center gap-5 my-3 ">


        <div class="flex items-center gap-5 ">
            <h2 class="text-3xl font-bold">Tasks List</h2>
            <!-- total task number -->
            <div data-tooltip="number of tasks" class="relative w-12 h-12 flex items-center justify-center DarkBGcolor dark:!bg-gray-700 dark:!hover:bg-gray-600 rounded-full shadow-sm">
                <span class="text-xl font-bold text-white">{{ $taskCount }}</span>
            </div>
        </div>
        <!-- add new task & refresh page -->
        <div class="flex items-center gap-5">
            <div data-tooltip="Reload" class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-full shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                </svg>
            </div>

            <!-- add task icon -->
            <div class="relative w-12 h-12 flex items-center justify-center btn-success dark:bg-green-700 rounded-full shadow-sm" data-tooltip="upload task">
                <form id="uploadTaskForm" action="{{ route('tasksupload.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input id="pdfInput" type="file" accept=".pdf" name="task_file" class="hidden" onchange="uploadTask()" />
                    <button id="uploadTaskButton" type="button" class="flex items-center justify-center w-12 h-12" onclick="document.getElementById('pdfInput').click();">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                            <path fill="#fff" d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>


    </div>
    <!-- ./page title -->

    <!-- page content -->
    <div class="tableCon">
        <div class="content-70">
            <div class="panel BoxShadow rounded-lg">

                <!-- search & filter buttons -->
                <div class="flex flex-col sm:flex-row justify-between p-2 gap-3">
                    <!--  search icon -->
                    <div class="relative w-full">
                        <!-- Search Input -->
                        <input type="text" placeholder="Find fast and search here..." class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider" id="searchInput">

                        <!-- Search Button with SVG Icon -->
                        <button data-tooltip="start searching" type="button" class="DarkBGcolor dark:!bg-gray-700 dark:!hover:bg-gray-600 absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1"
                            id="searchButton">
                            <svg class="mx-auto" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="11.5" cy="11.5" r="9.5" stroke="#fff" stroke-width="1.5" opacity="0.5" class="dark:stroke-gray-300"></circle>
                                <path d="M18.5 18.5L22 22" stroke="#fff" stroke-width="1.5" stroke-linecap="round" class="dark:stroke-gray-300"></path>
                            </svg>
                        </button>
                    </div>
                    <!-- ./search icon -->

                    <!-- filter & export buttons -->
                    <div class="flex lg:flex-col md:flex-row gap-5 w-full justify-end hidden md:flex">
                        <!-- customize -->
                        <button class="flex px-5 py-3 gap-3 city-light-yellow rounded-lg shadow-sm items-center">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path fill="#333333" d="M10 19h4v-2h-4zm-4-6h12v-2H6zM3 5v2h18V5z" />
                            </svg>
                            <span class="text-sm dark:text-black">Customize</span>
                        </button>
                        <!-- ./customize -->

                        <!-- filter -->
                        <button id="toggleFilters" class="flex px-5 py-3 gap-2 city-light-yellow rounded-lg shadow-sm items-center">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                                <path fill="#333333" d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                            </svg>


                            <span class="text-sm dark:text-black">Filter</span>
                        </button>
                        <!-- ./filter -->

                        <!-- export -->
                        <button class="flex px-5 py-3 gap-3 city-light-yellow rounded-lg shadow-sm items-center">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path fill="#333333" d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1" />
                            </svg>
                            <span class="text-sm dark:text-black">Export</span>
                        </button>
                        <!-- ./export -->

                    </div>
                    <!-- ./filter & export buttons -->

                </div>
                <!-- ./search & filter buttons -->




                <!-- Table -->
                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>

                    <div class="dataTable-container h-max">
                        <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" id="selectAll" class="text-gray-300 hidden">
                                            <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </label>
                                    </th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Actions</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Client Name</th>

                                    @if(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Branch Name</th>

                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Agent Name</th>
                                    @endif
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Type</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Price</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Status</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Supplier</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tasks as $task)
                                <tr
                                    data-price="{{ $task->price }}" data-supplier-id="{{ $task->supplier->id }}"
                                    data-branch-id="{{ $task->branch_id }}" data-agent-id="{{ $task->agent_id }}" data-status="{{ $task->status }}" data-type="{{ $task->type }}" data-client-id="{{ $task->client_id }}" class="taskRow">
                                    <td>
                                        <label class="custom-checkbox" data-tooltip="select task">
                                            <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox text-gray-900 dark:text-gray-300" value="{{ $task->id }}" {{ $task->invoiceDetail ? 'disabled' : '' }}>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </label>
                                    </td>
                                    <td class="p-3 text-sm">
                                        <a data-tooltip="see task" href="javascript:void(0);" class="viewTask text-blue-600 dark:text-blue-300 hover:underline" data-task-id="{{ $task->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                                <g fill="none" stroke="currentColor" stroke-width="1">
                                                    <path d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z" opacity=".5" />
                                                    <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z" />
                                                </g>
                                            </svg>
                                        </a>
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $task->client_name }}</td>
                                    @if(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
                                    <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $task->branch_name }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $task->agent_name }}</td>
                                    @endif
                                    <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $task->type }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $task->price }}</td>
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
                            </tbody>
                        </table>

                    </div>



                    <!-- pagination -->
                    <div class="dataTable-bottom justify-center">
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
                                <!-- Dynamic page numbers will be injected here -->
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
                    </div>
                    <!-- ./pagination -->
                </div>
                <!-- ./Table  -->

            </div>
        </div>


        <!-- right -->
        <!-- Task Details Container -->
        <div class="content-30 hidden" id="showRightDiv">
            <div id="taskDetails" class="panel w-full xl:mt-0 rounded-lg h-auto"></div>
            <div id="filterstBox" class="panel w-full xl:mt-0 rounded-lg h-auto"><!-- opened filters div -->

                <!-- Filters Header -->
                <div class="flex justify-between items-center gap-5 mb-5">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Filters</h1>
                    <div class="filter-badge flex items-center gap-3 DarkBGcolor  dark:bg-gray-700 dark:hover:bg-gray-600 rounded-full px-4 py-2 shadow">
                        <span class="font-semibold">0 applied</span>
                        <button id="clearFilters" data-tooltip="Clear Filters" class="transition">
                            &times;
                        </button>
                    </div>
                </div>
                <!-- Selected Filters -->
                <div class="w-full mb-5">
                    <div class="flex justify-between">
                        <div class="flex flex-wrap gap-4">

                            <!-- Selected Status -->
                            <div id="selected-statuses" class="flex flex-wrap gap-2">
                                <!-- Selected statuses will appear here dynamically -->
                            </div>

                            <!-- Selected Types -->
                            <div id="selected-types" class="flex flex-wrap gap-2">
                                <!-- Selected types will appear here dynamically -->
                            </div>

                            <!-- Selected Suppliers -->
                            <div id="selected-suppliers" class="flex flex-wrap gap-2">
                                <!-- Selected suppliers will be displayed here dynamically -->
                            </div>

                            <!-- Selected Agents -->
                            <div id="selected-agents" class="flex flex-wrap gap-2">
                                <!-- Selected agents will be displayed here dynamically -->
                            </div>

                            <!-- Selected Branches -->
                            <div id="selected-branches" class="flex flex-wrap gap-2">
                                <!-- Selected branches will be displayed here dynamically -->
                            </div>

                        </div>
                    </div>
                </div>
                <!--./ Selected Filters -->

                <!-- Filters Container -->
                <div class="flex flex-col gap-5">
                    <!-- Filter Options -->
                    <div class="w-full flex gap-5">
                        <!-- Filters Container -->
                        <div class="w-full gap-5 space-y-5">

                            <!-- Filter by Price -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-5 shadow-md hover:shadow-lg">
                                <div class="flex items-center">
                                    <input type="range" min="1" max="1000" value="500" id="priceRange"
                                        class="w-full h-2 bg-gray-200 dark:bg-gray-600 rounded-lg appearance-none">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        <span id="ShowTaskFilters" class="font-medium text-gray-800 dark:text-gray-100">0</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Filter by Status -->
                            <div class="flex items-center gap-4 bg-white px-4 py-2 rounded-lg shadow-md hover:shadow-lg">
                                <!-- Left Icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16">
                                    <path fill="#000000" fill-rule="evenodd" d="M15.941 7.033a8 8 0 0 1-14.784 5.112a.75.75 0 1 1 1.283-.778a6.5 6.5 0 1 0 8.922-8.93a.75.75 0 0 1 .776-1.284a8 8 0 0 1 3.803 5.88M9 1a1 1 0 1 1-2 0a1 1 0 0 1 2 0M2.804 5a1 1 0 1 0-1.732-1a1 1 0 0 0 1.732 1M1 7a1 1 0 1 1 0 2a1 1 0 0 1 0-2m4-4.196a1 1 0 1 0-1-1.732a1 1 0 0 0 1 1.732" clip-rule="evenodd" />
                                </svg>

                                <div class="flex-1 relative">
                                    <select name="status_id" id="status_id" class="w-full appearance-none bg-transparent border-none
                                         outline-none cursor-pointer pl-2 pr-8 focus:outline-none focus:ring-0">
                                        <option value="">Select Status</option>
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

                            <!-- Filter by Type -->
                            <div class="flex items-center gap-4 bg-white px-4 py-2 rounded-lg shadow-md hover:shadow-lg">
                                <!-- Left Icon -->
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14 11C14 10.4477 14.4477 10 15 10C15.5523 10 16 10.4477 16 11V13C16 13.5523 15.5523 14 15 14C14.4477 14 14 13.5523 14 13V11Z" stroke="currentColor" stroke-width="1.5"></path>
                                    <path d="M14.0079 19.0029L13.2579 19.0007V19.0007L14.0079 19.0029ZM14.0137 17L14.7637 17.0022V17H14.0137ZM3.14958 18.8284L2.61991 19.3594H2.61991L3.14958 18.8284ZM3.14958 5.17157L2.61991 4.64058L2.61991 4.64058L3.14958 5.17157ZM2.95308 10.2537L2.58741 10.9085H2.58741L2.95308 10.2537ZM2.01058 8.98947L1.26124 8.95797L2.01058 8.98947ZM2.95308 13.7463L2.58741 13.0915L2.58741 13.0915L2.95308 13.7463ZM2.01058 15.0105L2.75992 14.979L2.01058 15.0105ZM21.0469 10.2537L21.4126 10.9085L21.0469 10.2537ZM21.9894 8.98947L22.7388 8.95797V8.95797L21.9894 8.98947ZM20.8504 5.17157L21.3801 4.64058L21.3801 4.64058L20.8504 5.17157ZM21.0469 13.7463L20.6812 14.4012V14.4012L21.0469 13.7463ZM21.9894 15.0105L22.7388 15.042V15.042L21.9894 15.0105ZM20.8504 18.8284L21.3801 19.3594L21.3801 19.3594L20.8504 18.8284ZM21.9437 14.332L22.5981 13.9656L22.5981 13.9656L21.9437 14.332ZM21.9437 9.66803L22.5981 10.0344L22.5981 10.0344L21.9437 9.66803ZM2.05634 14.332L1.4019 13.9656L1.4019 13.9656L2.05634 14.332ZM2.05634 9.66802L2.71079 9.30168L2.71078 9.30168L2.05634 9.66802ZM14.0137 7H14.7637L14.7637 6.99782L14.0137 7ZM14.0064 4.49855L13.2564 4.50073V4.50073L14.0064 4.49855ZM16.5278 4.0189L16.5471 3.26915L16.5278 4.0189ZM17.0336 19.9642L17.0653 20.7135H17.0653L17.0336 19.9642ZM13.8595 19.8541L13.3299 19.323L13.3299 19.323L13.8595 19.8541ZM14.7579 19.0051L14.7637 17.0022L13.2637 16.9978L13.2579 19.0007L14.7579 19.0051ZM15.0162 16.75C15.1574 16.75 15.2687 16.8637 15.2687 17H16.7687C16.7687 16.0317 15.9823 15.25 15.0162 15.25V16.75ZM15.0162 15.25C14.0501 15.25 13.2637 16.0317 13.2637 17H14.7637C14.7637 16.8637 14.875 16.75 15.0162 16.75V15.25ZM9.99502 4.75H13.5052V3.25H9.99502V4.75ZM13.0079 19.25H9.99502V20.75H13.0079V19.25ZM9.99502 19.25C8.08355 19.25 6.72521 19.2484 5.69469 19.1102C4.68554 18.9749 4.10384 18.721 3.67925 18.2974L2.61991 19.3594C3.3698 20.1074 4.32051 20.4393 5.4953 20.5969C6.64871 20.7516 8.12585 20.75 9.99502 20.75V19.25ZM9.99502 3.25C8.12585 3.25 6.64871 3.24841 5.4953 3.4031C4.32051 3.56066 3.3698 3.89255 2.61991 4.64058L3.67925 5.70256C4.10384 5.27902 4.68554 5.02513 5.69469 4.88979C6.72521 4.75159 8.08355 4.75 9.99502 4.75V3.25ZM2.58741 10.9085C2.97311 11.1239 3.23007 11.533 3.23007 12H4.73007C4.73007 10.9664 4.1586 10.0678 3.31876 9.59884L2.58741 10.9085ZM2.75992 9.02097C2.83795 7.16494 3.09146 6.28889 3.67925 5.70256L2.61991 4.64058C1.59036 5.66758 1.34012 7.08185 1.26124 8.95797L2.75992 9.02097ZM3.23007 12C3.23007 12.467 2.97311 12.8761 2.58741 13.0915L3.31876 14.4012C4.1586 13.9322 4.73007 13.0336 4.73007 12H3.23007ZM1.26124 15.042C1.34012 16.9182 1.59036 18.3324 2.61991 19.3594L3.67925 18.2974C3.09146 17.7111 2.83795 16.8351 2.75992 14.979L1.26124 15.042ZM20.7699 12C20.7699 11.533 21.0269 11.1239 21.4126 10.9085L20.6812 9.59884C19.8414 10.0678 19.2699 10.9664 19.2699 12H20.7699ZM22.7388 8.95797C22.6599 7.08185 22.4096 5.66758 21.3801 4.64058L20.3207 5.70256C20.9085 6.28889 21.1621 7.16494 21.2401 9.02097L22.7388 8.95797ZM21.4126 13.0915C21.0269 12.8761 20.7699 12.467 20.7699 12H19.2699C19.2699 13.0336 19.8414 13.9322 20.6812 14.4012L21.4126 13.0915ZM21.2401 14.979C21.1621 16.8351 20.9085 17.7111 20.3207 18.2974L21.3801 19.3594C22.4096 18.3324 22.6599 16.9182 22.7388 15.042L21.2401 14.979ZM20.6812 14.4012C20.9652 14.5597 21.1507 14.6636 21.2761 14.7427C21.3379 14.7817 21.3653 14.8024 21.3735 14.8093C21.388 14.8213 21.3375 14.7846 21.2892 14.6983L22.5981 13.9656C22.5153 13.8177 22.4043 13.7154 22.3304 13.6542C22.2503 13.5878 22.1613 13.5276 22.0764 13.4741C21.9087 13.3683 21.6804 13.2411 21.4126 13.0915L20.6812 14.4012ZM22.7388 15.042C22.746 14.8706 22.7541 14.6937 22.7476 14.5458C22.741 14.3959 22.7178 14.1795 22.5981 13.9656L21.2892 14.6983C21.2386 14.6079 21.2461 14.5457 21.249 14.6117C21.2503 14.6404 21.2505 14.6822 21.2488 14.7464C21.2472 14.8104 21.244 14.8847 21.2401 14.979L22.7388 15.042ZM21.4126 10.9085C21.6804 10.7589 21.9087 10.6317 22.0764 10.5259C22.1613 10.4724 22.2503 10.4122 22.3304 10.3458C22.4043 10.2846 22.5153 10.1823 22.5981 10.0344L21.2892 9.30168C21.3375 9.21543 21.388 9.17871 21.3735 9.19072C21.3653 9.19756 21.3379 9.21832 21.2761 9.25725C21.1507 9.33637 20.9652 9.44028 20.6812 9.59884L21.4126 10.9085ZM21.2401 9.02097C21.244 9.11528 21.2472 9.18961 21.2488 9.25357C21.2505 9.31779 21.2503 9.35964 21.249 9.38827C21.2461 9.45428 21.2386 9.39206 21.2892 9.30169L22.5981 10.0344C22.7178 9.82054 22.741 9.60408 22.7476 9.45419C22.7541 9.30634 22.746 9.12945 22.7388 8.95797L21.2401 9.02097ZM2.58741 13.0915C2.31959 13.2411 2.0913 13.3683 1.92358 13.4741C1.83872 13.5276 1.74971 13.5878 1.66957 13.6542C1.59566 13.7154 1.48474 13.8177 1.4019 13.9656L2.71078 14.6983C2.6625 14.7846 2.61198 14.8213 2.62648 14.8093C2.63474 14.8024 2.66215 14.7817 2.72387 14.7427C2.84929 14.6636 3.03482 14.5597 3.31876 14.4012L2.58741 13.0915ZM2.75992 14.979C2.75595 14.8847 2.75285 14.8104 2.7512 14.7464C2.74954 14.6822 2.74973 14.6404 2.75099 14.6117C2.75389 14.5457 2.76137 14.6079 2.71078 14.6983L1.4019 13.9656C1.28221 14.1795 1.25903 14.3959 1.25244 14.5458C1.24593 14.6937 1.25403 14.8706 1.26124 15.042L2.75992 14.979ZM3.31876 9.59884C3.03482 9.44028 2.84929 9.33637 2.72386 9.25725C2.66214 9.21832 2.63474 9.19756 2.62648 9.19072C2.61198 9.17871 2.66251 9.21543 2.71079 9.30168L1.4019 10.0344C1.48473 10.1823 1.59565 10.2846 1.66956 10.3458C1.74971 10.4122 1.83872 10.4724 1.92357 10.5259C2.0913 10.6317 2.31959 10.7589 2.58741 10.9085L3.31876 9.59884ZM1.26124 8.95797C1.25403 9.12945 1.24593 9.30634 1.25244 9.45419C1.25903 9.60408 1.28221 9.82054 1.4019 10.0344L2.71078 9.30168C2.76137 9.39206 2.75389 9.45428 2.75099 9.38827C2.74973 9.35964 2.74954 9.31779 2.7512 9.25357C2.75285 9.18961 2.75595 9.11528 2.75992 9.02097L1.26124 8.95797ZM14.7637 6.99782L14.7564 4.49637L13.2564 4.50073L13.2637 7.00218L14.7637 6.99782ZM15.0162 7.25C14.875 7.25 14.7637 7.13631 14.7637 7H13.2637C13.2637 7.96826 14.0501 8.75 15.0162 8.75V7.25ZM15.2687 7C15.2687 7.13631 15.1574 7.25 15.0162 7.25V8.75C15.9823 8.75 16.7687 7.96826 16.7687 7H15.2687ZM15.2687 4.51618V7H16.7687V4.51618H15.2687ZM16.5084 4.76865C18.6966 4.82509 19.6778 5.06124 20.3208 5.70256L21.3801 4.64058C20.2676 3.53084 18.6939 3.32452 16.5471 3.26915L16.5084 4.76865ZM16.7687 4.51618C16.7687 4.656 16.6534 4.77239 16.5084 4.76865L16.5471 3.26915C15.8429 3.25099 15.2687 3.81835 15.2687 4.51618H16.7687ZM13.5052 4.75C13.3698 4.75 13.2568 4.64027 13.2564 4.50073L14.7564 4.49637C14.7544 3.80569 14.1931 3.25 13.5052 3.25V4.75ZM17.0653 20.7135C18.9399 20.6343 20.353 20.384 21.3801 19.3594L20.3208 18.2974C19.7336 18.8831 18.8563 19.1365 17.002 19.2148L17.0653 20.7135ZM15.2687 17V18.9765H16.7687V17H15.2687ZM13.2579 19.0007C13.2575 19.121 13.2572 19.2136 13.255 19.2926C13.2528 19.3721 13.249 19.4192 13.245 19.4481C13.2411 19.4764 13.2396 19.4669 13.2513 19.4387C13.2654 19.4045 13.2911 19.3617 13.3299 19.323L14.389 20.3852C14.6246 20.1502 14.701 19.8709 14.7311 19.6521C14.7582 19.4548 14.7573 19.219 14.7579 19.0051L13.2579 19.0007ZM13.0079 20.75C13.2218 20.75 13.4576 20.7516 13.6549 20.7251C13.8739 20.6957 14.1534 20.6201 14.389 20.3852L13.3299 19.323C13.3687 19.2843 13.4116 19.2587 13.4458 19.2447C13.4741 19.2331 13.4836 19.2346 13.4553 19.2384C13.4264 19.2423 13.3792 19.246 13.2998 19.248C13.2208 19.25 13.1282 19.25 13.0079 19.25V20.75ZM17.002 19.2148C16.8812 19.2199 16.7889 19.2238 16.7101 19.225C16.631 19.2262 16.5849 19.2244 16.5575 19.2217C16.5309 19.2191 16.5426 19.2175 16.5734 19.2292C16.6103 19.2433 16.6536 19.2685 16.6917 19.305L15.6536 20.3878C15.8978 20.6219 16.183 20.6921 16.4108 20.7145C16.6127 20.7344 16.8518 20.7225 17.0653 20.7135L17.002 19.2148ZM15.2687 18.9765C15.2687 19.1953 15.267 19.4374 15.295 19.6397C15.3263 19.8655 15.407 20.1514 15.6536 20.3878L16.6917 19.305C16.7313 19.343 16.7584 19.3863 16.7737 19.4221C16.7863 19.4516 16.7848 19.4622 16.7808 19.4337C16.7768 19.4046 16.7729 19.3566 16.7708 19.2753C16.7687 19.1945 16.7687 19.0997 16.7687 18.9765H15.2687Z" fill="currentColor"></path>
                                </svg>

                                <!-- Select Dropdown -->
                                <div class="flex-1 relative">
                                    <select name="type_id" id="type_id" class="w-full appearance-none bg-transparent border-none
                                         outline-none cursor-pointer pl-2 pr-8 focus:outline-none focus:ring-0">
                                        <option value="" class="">Select Type</option>
                                        @foreach($types as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>

                                </div>
                            </div>


                            <!-- Filter by Supplier -->
                            <div class="flex items-center gap-4 bg-white px-4 py-2 rounded-lg shadow-md hover:shadow-lg">
                                <!-- Left Icon -->
                                <svg class="w-5 h-5 text-gray-700" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <g fill="none">
                                        <path stroke="currentColor" d="M9 6a3 3 0 1 0 6 0a3 3 0 0 0-6 0Zm-4.562 7.902a3 3 0 1 0 3 5.195a3 3 0 0 0-3-5.196Zm15.124 0a2.999 2.999 0 1 1-2.998 5.194a2.999 2.999 0 0 1-2.998-5.194Z"></path>
                                        <path fill="currentColor" fill-rule="evenodd" d="M9.003 6.125a3 3 0 0 1 .175-1.143a8.5 8.5 0 0 0-5.031 4.766a8.5 8.5 0 0 0-.502 4.817a3 3 0 0 1 .902-.723a7.5 7.5 0 0 1 4.456-7.717m5.994 0a7.5 7.5 0 0 1 4.456 7.717q.055.028.11.06c.3.174.568.398.792.663a8.5 8.5 0 0 0-5.533-9.583a3 3 0 0 1 .175 1.143m2.536 13.328a3 3 0 0 1-1.078-.42a7.5 7.5 0 0 1-8.91 0l-.107.065a3 3 0 0 1-.971.355a8.5 8.5 0 0 0 11.066 0" clip-rule="evenodd"></path>
                                    </g>
                                </svg>

                                <!-- Select Dropdown -->
                                <div class="flex-1 relative">
                                    <select name="supplier_id" id="supplier_id" class="w-full appearance-none bg-transparent border-none
                                         outline-none cursor-pointer pl-2 pr-8 focus:outline-none focus:ring-0">
                                        <option value="" class="">Select Supplier</option>
                                        @foreach($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Filter by Agent -->
                            <div class="flex items-center gap-4 bg-white px-4 py-2 rounded-lg shadow-md hover:shadow-lg">
                                <!-- Left Icon -->
                                <svg class="w-5 h-5 text-gray-700" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <g fill="none">
                                        <path stroke="currentColor" d="M9 6a3 3 0 1 0 6 0a3 3 0 0 0-6 0Z" />
                                    </g>
                                </svg>

                                <!-- Select Dropdown -->
                                <div class="flex-1 relative">
                                    <select name="agent_id" id="agent_id" class="w-full appearance-none bg-transparent border-none
                                         outline-none cursor-pointer pl-2 pr-8 focus:outline-none focus:ring-0">
                                        <option value="" class="">Select Agent</option>
                                        @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <!-- Filter by Branch -->
                            <div class="flex items-center gap-4 bg-white px-4 py-2 rounded-lg shadow-md hover:shadow-lg">
                                <!-- Left Icon -->
                                <svg class="w-5 h-5 text-gray-700" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <g fill="none">
                                        <path stroke="currentColor" d="M9 6a3 3 0 1 0 6 0a3 3 0 0 0-6 0Zm-4.562 7.902a3 3 0 1 0 3 5.195a3 3 0 0 0-3-5.196Zm15.124 0a2.999 2.999 0 1 1-2.998 5.194a2.999 2.999 0 0 1 2.998-5.194Z"></path>
                                        <path fill="currentColor" fill-rule="evenodd" d="M9.003 6.125a3 3 0 0 1 .175-1.143a8.5 8.5 0 0 0-5.031 4.766a8.5 8.5 0 0 0-.502 4.817a3 3 0 0 1 .902-.723a7.5 7.5 0 0 1 4.456-7.717m5.994 0a7.5 7.5 0 0 1 4.456 7.717q.055.028.11.06c.3.174.568.398.792.663a8.5 8.5 0 0 0-5.533-9.583a3 3 0 0 1 .175 1.143m2.536 13.328a3 3 0 0 1-1.078-.42a7.5 7.5 0 0 1-8.91 0l-.107.065a3 3 0 0 1-.971.355a8.5 8.5 0 0 0 11.066 0" clip-rule="evenodd"></path>
                                    </g>
                                </svg>

                                <!-- Select Dropdown -->
                                <div class="flex-1 relative">
                                    <select name="branch_id" id="branch_id" class="w-full appearance-none bg-transparent border-none
                                         outline-none cursor-pointer pl-2 pr-8 focus:outline-none focus:ring-0">
                                        <option value="" class="">Select Branch</option>
                                        @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>



                <!-- ./opened filters  div -->
            </div>
        </div>



        <!-- ./right -->
    </div>
    <!--./page content-->


    <!-- Floating Actions div-->
    <div>
        <div id="floatingActions" class="hidden flex justify-between gap-5 fixed CuzPostion bg-[#f6f8fa] shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)]  rounded-lg w-auto h-auto z-50 p-3">

            <div class="flex justify-between gap-5 items-center h-full">
                <button id="createInvoiceBtn" class="flex px-5 py-3 gap-3 btn-success hover:bg-[#00ab5599] rounded-lg shadow-sm items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#ffffff" d="M2 12c0-2.8 1.6-5.2 4-6.3V3.5C2.5 4.8 0 8.1 0 12s2.5 7.2 6 8.5v-2.2c-2.4-1.1-4-3.5-4-6.3m13-9c-5 0-9 4-9 9s4 9 9 9s9-4 9-9s-4-9-9-9m5 10h-4v4h-2v-4h-4v-2h4V7h2v4h4z" />
                    </svg>
                    <span class="text-sm">Create Invoice</span>
                </button>
                <button class="flex px-5 py-3 gap-3 btn-danger hover:bg-[#e7515aa8] rounded-lg shadow-sm items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#ffffff" d="M12 2c5.53 0 10 4.47 10 10s-4.47 10-10 10S2 17.53 2 12S6.47 2 12 2m5 5h-2.5l-1-1h-3l-1 1H7v2h10zM9 18h6a1 1 0 0 0 1-1v-7H8v7a1 1 0 0 0 1 1" />
                    </svg>
                    <span class="text-sm">Delete</span>
                </button>
            </div>
            <div id="closeFloatingActions" class="flex cursor-pointer items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 12 12">
                    <path fill="#E53935" d="M1.757 10.243a6.001 6.001 0 1 1 8.488-8.486a6.001 6.001 0 0 1-8.488 8.486M6 4.763l-2-2L2.763 4l2 2l-2 2L4 9.237l2-2l2 2L9.237 8l-2-2l2-2L8 2.763Z" />
                </svg>
            </div>
        </div>

    </div>

    <!-- ./Floating Actions div -->



    <!-- select all & create invoice script -->
    <script>
        const floatingActions = document.getElementById("floatingActions");
        const closeFloatingActions = document.getElementById("closeFloatingActions");
        const selectAllCheckbox = document.getElementById("selectAll");
        const rowCheckboxes = document.querySelectorAll(".rowCheckbox");
        const createInvoiceBtn = document.getElementById("createInvoiceBtn");


        // Select/Deselect all checkboxes
        selectAllCheckbox.addEventListener("change", function() {
            rowCheckboxes.forEach(checkbox => checkbox.checked = selectAllCheckbox.checked);
            toggleCreateInvoiceButton(); // Update button state
        });

        // Toggle "Create Invoice" button based on selected checkboxes
        const toggleCreateInvoiceButton = () => {
            const isAnySelected = Array.from(rowCheckboxes).some(checkbox => checkbox.checked);
            createInvoiceBtn.disabled = !isAnySelected;
        };
        // Add change event to each row checkbox
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener("change", function() {
                // Update the "Select All" checkbox state
                const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;

                // Update button state
                toggleCreateInvoiceButton();

                // Show or hide the floating div based on any checkbox selection
                const isAnyChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
                if (isAnyChecked) {
                    floatingActions.classList.remove("hidden");
                } else {
                    floatingActions.classList.add("hidden");
                }
            });
        });

        // Initialize button state on page load
        toggleCreateInvoiceButton();

        // Gather selected task IDs and submit them
        createInvoiceBtn.addEventListener("click", function() {
            const selectedTaskIds = Array.from(rowCheckboxes)
                .filter(checkbox => checkbox.checked)
                .map(checkbox => checkbox.value);

            if (selectedTaskIds.length === 0) {
                alert("No tasks selected!");
                return;
            }

            // Example: Redirect to the batch invoice creation route
            const url = "{{ route('invoice.create') }}?task_ids=" + selectedTaskIds.join(",");
            window.location.href = url;
        });

        // Close the floating div when the "X" button is clicked
        closeFloatingActions.addEventListener("click", function() {
            floatingActions.classList.add("hidden");
        });
    </script>

    <!-- table pagination script -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const rowsPerPage = 10;
            const table = document.getElementById("myTable");
            const rows = Array.from(table.querySelector("tbody").rows);
            const paginationContainer = document.querySelector(".dataTable-bottom");
            const paginationList = document.querySelector(".dataTable-pagination-list");
            const prevPageButton = document.getElementById("prevPage");
            const nextPageButton = document.getElementById("nextPage");
            let currentPage = 1;

            function filterRows() {
                return rows.filter((row) => row.style.display !== "none");
            }

            function updatePagination(visibleRows) {
                const totalPages = Math.ceil(visibleRows.length / rowsPerPage);

                paginationContainer.style.display = visibleRows.length > rowsPerPage ? "flex" : "none";

                paginationList.querySelectorAll("li.page-number").forEach((el) => el.remove());

                if (totalPages > 1) {
                    for (let i = 1; i <= totalPages; i++) {
                        const li = document.createElement("li");
                        li.className = `page-number ${i === currentPage ? "active" : ""}`;
                        li.innerHTML = `<a href="#" data-page="${i}">${i}</a>`;
                        paginationList.insertBefore(li, nextPageButton);
                    }
                }
            }

            function showPage(page, visibleRows) {
                const start = (page - 1) * rowsPerPage;
                const end = start + rowsPerPage;

                rows.forEach((row) => (row.style.display = "none"));

                visibleRows.slice(start, end).forEach((row) => (row.style.display = ""));

                currentPage = page;
                updatePagination(visibleRows);
            }

            document.addEventListener("filterUpdated", function() {
                const visibleRows = filterRows();
                updatePagination(visibleRows);
                if (visibleRows.length > 0) {
                    showPage(1, visibleRows);
                }
            });

            const visibleRows = filterRows();
            updatePagination(visibleRows);
            showPage(1, visibleRows);
        });
    </script>


</x-app-layout>