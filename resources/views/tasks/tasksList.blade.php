<x-app-layout>




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
            <div data-tooltip="number of tasks" class="relative w-12 h-12 flex items-center justify-center DarkBCcolor dark:!bg-gray-700 dark:!hover:bg-gray-600 rounded-full shadow-sm">
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
        <div class="content-70" id="taskListContainer">
            <!-- Table  -->
            <div class="panel BoxShadow rounded-lg">
                <div class="flex flex-col sm:flex-row justify-between p-2 gap-3">
                    <!--  search icon -->
                    <div class="relative w-full">
                        <!-- Search Input -->
                        <input type="text" placeholder="Find fast and search here..." class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider" id="searchInput">

                        <!-- Search Button with SVG Icon -->
                        <button type="button" class="btn DarkBCcolor dark:!bg-gray-700 dark:!hover:bg-gray-600 absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1"
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
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                                <path fill="#333333" d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                            </svg>
                            <span class="text-sm dark:text-black">Customize</span>
                        </button>
                        <!-- ./customize -->

                        <!-- filter -->
                        <button class="flex px-5 py-3 gap-2 city-light-yellow rounded-lg shadow-sm items-center">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path fill="#333333" d="M10 19h4v-2h-4zm-4-6h12v-2H6zM3 5v2h18V5z" />
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

                <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                    <div class="dataTable-top"></div>
                    <!-- table -->
                    <div class="dataTable-container h-max">
                        <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" id="selectAll" class="text-gray-500 hidden">
                                            <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </label>
                                    </th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Actions</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Client Name</th>

                                    @if(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Branch Name</th>

                                    <th class="p-3 text-left text-md font-bold text-gray-500">Agent Name</th>
                                    @endif
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Type</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Price</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Status</th>
                                    <th class="p-3 text-left text-md font-bold text-gray-500">Supplier</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tasks as $task)
                                <tr>
                                    <td>
                                        <label class="custom-checkbox">
                                            <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox text-gray-500" value="{{ $task->id }}" {{ $task->invoiceDetail ? 'disabled' : '' }}>
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" class="checkbox-svg">
                                                <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                            </svg>
                                        </label>
                                    </td>
                                    <td class="p-3 text-sm">
                                        <a href="javascript:void(0);" class="viewTask text-blue-500 hover:underline" data-task-id="{{ $task->id }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                                <g fill="none" stroke="currentColor" stroke-width="1">
                                                    <path d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z" opacity=".5" />
                                                    <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z" />
                                                </g>
                                            </svg>
                                        </a>
                                    </td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->client_name }}</td>
                                    @if(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->branch_name }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->agent_name }}</td>
                                    @endif
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->type }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->price }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->status }}</td>
                                    <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->supplier->name }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>
                    <!-- ./table -->


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
            </div>
            <!-- ./Table  -->

        </div>
        <!-- right -->
        <div class="content-30 hidden" id="taskDetailsContainer">
            <!-- display task details here-->
            <div id="taskDetails" class="panel w-full xl:mt-0 rounded-lg h-auto"></div> <!-- display task details here-->
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




    <!-- table pagination script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rowsPerPage = 10; // Number of rows per page
            const table = document.getElementById('myTable');
            const rows = Array.from(table.querySelector('tbody').rows); // Get all rows
            const paginationContainer = document.querySelector('.dataTable-pagination-list'); // Target pagination container
            let currentPage = 1;
            const totalPages = Math.ceil(rows.length / rowsPerPage); // Calculate total pages

            // Function to create pagination
            function createPagination() {
                // Remove existing page numbers
                Array.from(paginationContainer.querySelectorAll('li.page-number')).forEach((el) => el.remove());

                // Create and add page numbers dynamically
                for (let i = 1; i <= totalPages; i++) {
                    const li = document.createElement('li');
                    li.className = `page-number ${i === currentPage ? 'active' : ''}`;
                    li.innerHTML = `<a href="#" data-page="${i}">${i}</a>`;

                    const nextPageElement = paginationContainer.querySelector('#nextPage');

                    // Insert before #nextPage if it exists, otherwise append
                    if (nextPageElement) {
                        paginationContainer.insertBefore(li, nextPageElement);
                    } else {
                        paginationContainer.appendChild(li);
                    }
                }
            }

            // Function to show rows for the current page
            function showPage(page) {
                const start = (page - 1) * rowsPerPage;
                const end = start + rowsPerPage;

                // Show rows for the current page, hide others
                rows.forEach((row, index) => {
                    row.style.display = index >= start && index < end ? '' : 'none';
                });

                currentPage = page; // Update current page
                createPagination(); // Recreate pagination numbers
            }

            // Function to handle page number click
            function handlePageChange(e) {
                e.preventDefault();
                const page = parseInt(e.target.dataset.page, 10);
                if (page && page !== currentPage) {
                    showPage(page);
                }
            }

            // Event listener for previous button
            const prevPageButton = document.getElementById('prevPage');
            if (prevPageButton) {
                prevPageButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (currentPage > 1) {
                        showPage(currentPage - 1);
                    }
                });
            }

            // Event listener for next button
            const nextPageButton = document.getElementById('nextPage');
            if (nextPageButton) {
                nextPageButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (currentPage < totalPages) {
                        showPage(currentPage + 1);
                    }
                });
            }

            // Event listener for page numbers
            paginationContainer.addEventListener('click', (e) => {
                if (e.target.tagName === 'A' && e.target.dataset.page) {
                    handlePageChange(e);
                }
            });

            // Initialize pagination
            if (totalPages > 1) {
                createPagination();
                showPage(1); // Show the first page initially
            }
        });
    </script>


    <!-- show task details script -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const viewTaskLinks = document.querySelectorAll(".viewTask");
            const taskDetailsDiv = document.getElementById("taskDetails");
            const taskListContainer = document.getElementById("taskListContainer"); // content-70
            const taskDetailsContainer = document.getElementById("taskDetailsContainer"); // content-30

            // Track the currently opened task ID
            let currentlyOpenTaskId = null;

            viewTaskLinks.forEach(link => {
                link.addEventListener("click", function(event) {
                    event.preventDefault();

                    const taskId = this.getAttribute("data-task-id");
                    console.log("Fetching details for Task ID:", taskId); // Debugging log

                    // Toggle close if the same task is clicked
                    if (currentlyOpenTaskId === taskId) {
                        console.log("Closing task details..."); // Debugging log

                        // Reset styles to make content-70 full width
                        taskListContainer.classList.remove("show-details"); // Remove class
                        taskDetailsContainer.classList.add("hidden"); // Hide details
                        taskDetailsDiv.innerHTML = ""; // Clear details content
                        currentlyOpenTaskId = null; // Reset tracking variable
                        return; // Stop execution
                    }

                    // Update the currently open task ID
                    currentlyOpenTaskId = taskId;

                    // Fetch task details
                    fetch(`/tasks/${taskId}`)
                        .then(response => {
                            console.log("Response Status:", response.status); // Debugging log
                            if (!response.ok) {
                                throw new Error('Failed to fetch task details. Status: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log("Fetched Data:", data); // Debugging log

                            // Ensure valid data
                            if (data && data.client_name) {
                                // Populate task details
                                taskDetailsDiv.innerHTML = `
                            <h3 class='text-lg font-bold mb-2'>Task Details</h3>
                            <div class='flex flex-col rounded-md border border-[#e0e6ed]'>
                                <div class='border-b px-4 py-4 hover:bg-gray-200'>
                                    <p><strong>Client Name:</strong> ${data.client_name}</p>
                                </div>
                                <div class='border-b px-4 py-4 hover:bg-gray-200'>
                                    <p><strong>Agent Name:</strong> ${data.agent_name || 'N/A'}</p>
                                </div>
                                <div class='border-b px-4 py-4 hover:bg-gray-200'>
                                    <p><strong>Type:</strong> ${data.type}</p>
                                </div>
                                <div class='border-b px-4 py-4 hover:bg-gray-200'>
                                    <p><strong>Price:</strong> $${data.price}</p>
                                </div>
                                <div class='border-b px-4 py-4 hover:bg-gray-200'>
                                    <p><strong>Status:</strong> ${data.status}</p>
                                </div>
                                <div class='border-b px-4 py-4 hover:bg-gray-200'>
                                    <p><strong>Supplier:</strong> ${data.supplier.name}</p>
                                </div>
                            </div>
                        `;
                                // Show task details and adjust styles
                                taskListContainer.classList.add("show-details"); // Shrink content-70
                                taskDetailsContainer.classList.remove("hidden"); // Show details
                            } else {
                                console.warn("Invalid Data:", data); // Debugging log
                                taskDetailsDiv.innerHTML = "<p class='text-red-500'>Invalid task data received.</p>";
                                taskDetailsContainer.classList.remove("hidden");
                            }
                        })
                        .catch(error => {
                            console.error("Error fetching task details:", error);
                            taskDetailsDiv.innerHTML = "<p class='text-red-500'>Failed to load task details.</p>";
                            taskDetailsContainer.classList.remove("hidden");
                        });
                });
            });
        });
    </script>





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




</x-app-layout>