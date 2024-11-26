<x-app-layout>
    <script>
        window.taskCount = @json($taskCount);
    </script>

    <!-- Notification Container -->
    <div id="notification" class="fixed bottom-5 right-5 z-50 hidden bg-green-500 text-white p-3 rounded-lg shadow-lg">
        <span id="notificationMessage"></span>
    </div>
    <!--./Notification Container -->


    <div>
        <!-- Breadcrumbs -->
        <x-breadcrumbs :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Tasks List']
]" />

        <!-- ./Breadcrumbs -->
        <!-- Controls Section -->
        <div
            class="flex flex-col md:flex-row items-center justify-between p-3 bg-white dark:bg-gray-800 shadow rounded-lg space-y-3 md:space-y-0 text-gray-700 dark:text-gray-300">

            <!-- left side -->
            <div
                class="flex items-start md:items-center border border-gray-300 rounded-lg p-2 space-y-3 md:space-y-0 md:space-x-3">
                <!-- left side -->
                <div class="flex gap-2 mr-2">

                    <a
                        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700">
                        <span class="text-black dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Total
                            Tasks </span>


                    </a>
                    <a
                        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-info-light dark:bg-gray-700"><span
                            id="TasksData"></span>
                    </a>
                </div>


            </div>


            <!-- right side -->
            <div class="flex items-center gap-3 space-y-3 md:space-y-0 md:space-x-2">

            <button id="createInvoiceBtn" class="badge bg-success shadow-md dark:group-hover:bg-transparent whitespace-nowrap">
                Create Invoice for Selected Tasks
            </button>

                <!-- Search Box -->
                <div class="mt07 relative flex items-center h-12">
                    <input id="searchInput" type="text" placeholder="Search"
                        class="w-full h-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-300">
                    <svg class="w-5 h-5 text-gray-500 absolute left-3 top-1/2 transform -translate-y-1/2"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35M9.5 17A7.5 7.5 0 109.5 2a7.5 7.5 0 000 15z" />
                    </svg>
                </div>


                <!-- Upload Task Button -->
                <div class="relative flex items-center h-12">
                    <form id="uploadTaskForm" action="{{ route('tasksupload.import') }}" method="POST"
                        enctype="multipart/form-data" class="inline-flex">
                        @csrf
                        <input id="pdfInput" type="file" accept=".pdf" name="task_file" class="hidden"
                            onchange="uploadTask()" />

                        <button id="uploadTaskButton" type="button"
                            onclick="document.getElementById('pdfInput').click();"
                            class="h-full flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-700 focus:outline-none">
                            <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Upload Task</span>
                        </button>
                    </form>
                </div>
                <!-- ./Upload Task Button -->

                <script>
                    function uploadTask() {
                        console.log(document.getElementById('loadingScreen'));
                        document.getElementById('loadingScreen').style.display = 'block';
                        // Check if a file has been selected
                        const fileInput = document.getElementById('pdfInput');
                        if (fileInput.files.length > 0) {
                            // Submit the form once a file is selected
                            document.getElementById('uploadTaskForm').submit();
                        }
                    }
                </script>

            </div>



        </div>
        <!-- ./Controls Section -->


        <!-- Table Section -->
        <div class="mt-5 overflow-x-auto bg-white shadow rounded-lg">
            <div class="max-h-[35rem] overflow-y-auto custom-scrollbar">
                <table class="AgentTable CityMobileTable w-full">
                    <thead class="sticky top-0 z-10">
                        <tr>
                            <!-- select all icon -->
                            <th class="px-4 py-2">
                                <svg id="selectAllSVG" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg" class="dark:fill-white">
                                    <path
                                        d="M8.0374 14.1437C7.78266 14.2711 7.47314 14.1602 7.35714 13.9001L3.16447 4.49844C2.49741 3.00261 3.97865 1.45104 5.36641 2.19197L11.2701 5.344C11.7293 5.58915 12.2697 5.58915 12.7289 5.344L18.6326 2.19197C20.0204 1.45104 21.5016 3.00261 20.8346 4.49844L19.2629 8.02275C19.0743 8.44563 18.7448 8.78997 18.3307 8.99704L8.0374 14.1437Z"
                                        fill="#1C274C" class="dark:fill-white" />
                                    <path opacity="0.5"
                                        d="M8.6095 15.5342C8.37019 15.6538 8.26749 15.9407 8.37646 16.185L10.5271 21.0076C11.1174 22.3314 12.8818 22.3314 13.4722 21.0076L17.4401 12.1099C17.6313 11.6812 17.1797 11.2491 16.7598 11.459L8.6095 15.5342Z"
                                        fill="#1C274C" class="dark:fill-gray-400" />
                                </svg>

                                <input type="checkbox" id="selectAll" class="form-checkbox CheckBoxColor hidden">
                            </th>
                            <th class="px-4 py-2">Status</th>
                            <!-- Table Headers: Tasks Name and Agent Name -->
                            <th class="px-4 py-2 cursor-pointer" id="tasksNameHeader">
                                <div class="inline-flex items-center">
                                    <svg id="sortIcon" class="mr-1 w-5 h-5" viewBox="0 0 24 24" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="dark:fill-white">
                                        <path d="M13 7L3 7" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round"
                                            class="dark:stroke-white" />
                                        <path d="M10 12H3" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round"
                                            class="dark:stroke-white" />
                                        <path d="M8 17H3" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round"
                                            class="dark:stroke-white" />
                                        <path
                                            d="M11.3161 16.6922C11.1461 17.07 11.3145 17.514 11.6922 17.6839C12.07 17.8539 12.514 17.6855 12.6839 17.3078L11.3161 16.6922ZM16.5 7L17.1839 6.69223C17.0628 6.42309 16.7951 6.25 16.5 6.25C16.2049 6.25 15.9372 6.42309 15.8161 6.69223L16.5 7ZM20.3161 17.3078C20.486 17.6855 20.93 17.8539 21.3078 17.6839C21.6855 17.514 21.8539 17.07 21.6839 16.6922L20.3161 17.3078ZM19.3636 13.3636L20.0476 13.0559L19.3636 13.3636ZM13.6364 12.6136C13.2222 12.6136 12.8864 12.9494 12.8864 13.3636C12.8864 13.7779 13.2222 14.1136 13.6364 14.1136V12.6136ZM12.6839 17.3078L17.1839 7.30777L15.8161 6.69223L11.3161 16.6922L12.6839 17.3078ZM21.6839 16.6922L20.0476 13.0559L18.6797 13.6714L20.3161 17.3078L21.6839 16.6922ZM20.0476 13.0559L17.1839 6.69223L15.8161 7.30777L18.6797 13.6714L20.0476 13.0559ZM19.3636 12.6136H13.6364V14.1136H19.3636V12.6136Z"
                                            fill="#1C274C" class="dark:fill-white" />
                                    </svg>
                                    <span>Tasks Name</span>
                                </div>
                            </th>
                            <th class="px-4 py-2">Invoice</th>
                            <th class="px-4 py-2">Invoice Status</th>
                            <th class="px-4 py-2">Client Name</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Net Price</th>
                            <th class="px-4 py-2">Surcharge</th>
                            <th class="px-4 py-2">Tax</th>
                            <th class="px-4 py-2">Total</th>
                            <th class="px-4 py-2">Agent Name</th>
                            <th class="px-4 py-2">Supplier Name</th>
                            <th class="px-4 py-2">Reference</th>

                            <th class="px-4 py-2">Actions</th>
                            <th class="px-4 py-2">Payment</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-300">
                        @foreach($tasks as $task)
                        <tr>
                            <td class="px-4 py-2">
                                <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox" value="{{ $task->id }}" {{ $task->invoiceDetail ? 'disabled' : '' }}>
                            </td>
                            <td class="px-4 py-2 editable-cell" contenteditable="true" data-id="{{ $task->id }}"
                                data-field="status">
                                {{ $task->status }}
                            </td>
                            <td class="px-4 py-2">{{ $task->additional_info }} - {{ $task->venue }}</td>
                            <td class="px-4 py-2">{{ $task->invoiceDetail ? $task->invoiceDetail->invoice->invoice_number : 'N\A'}}</td>
                            <td class="px-4 py-2">{{ $task->invoiceDetail ? $task->invoiceDetail->invoice->status : 'N\A'}}</td>
                            <td class="px-4 py-2 editable-cell" contenteditable="true" data-id="{{ $task->id }}"
                                data-field="client_name">{{ $task->client_name }}</td>
                            <td class="px-4 py-2 editable-cell" contenteditable="true" data-id="{{ $task->id }}"
                                data-field="type">{{ $task->type }}</td>
                            <td class="px-4 py-2 editable-cell" contenteditable="true" data-id="{{ $task->id }}"
                                data-field="price">
                                {{ $task->price }}
                            </td>
                            <td class="px-4 py-2 editable-cell" contenteditable="true" data-id="{{ $task->id }}"
                                data-field="surcharge">
                                {{ $task->surcharge }}
                            </td>
                            <td class="px-4 py-2 editable-cell" contenteditable="true" data-id="{{ $task->id }}"
                                data-field="tax">
                                {{ $task->tax }}
                            </td>
                            <td class="px-4 py-2" data-id="{{ $task->id }}" data-field="total">
                                {{ $task->total }}
                            </td>

                            <td class="px-4 py-2">{{ $task->agent->name }}</td>
                            <td class="px-4 py-2">{{ $task->supplier->name }}</td>
                            <td class="px-4 py-2">{{ $task->reference }}</td>
                            <td class="px-4 py-2">

                                <a href="javascript:void(0);" onclick="ShowTask({{ $task->id }})">
                                    <span
                                        class="badge bg-dark shadow-md dark:group-hover:bg-transparent whitespace-nowrap">
                                        See Details
                                    </span>
                                </a>


                            </td>
                            <td class="px-4 py-2">
                                <!-- payment link -->
                                <a href="{{ route('invoice.create', ['task_ids' => $task->id]) }}">
                                    <span
                                        class="badge bg-success shadow-md dark:group-hover:bg-transparent whitespace-nowrap">Create
                                        Invoice
                                    </span>
                                </a>
                            </td>

                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div><!-- ./Table Section -->

    </div> <!-- ./p-3 -->

    <!-- Task Modal -->
    @include('tasks.singleTask')

    <script>
        const selectAllCheckbox = document.getElementById("selectAll");
    const rowCheckboxes = document.querySelectorAll(".rowCheckbox");
    const createInvoiceBtn = document.getElementById("createInvoiceBtn");

  // Select/Deselect all checkboxes
  selectAllCheckbox.addEventListener("change", function () {
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
        checkbox.addEventListener("change", function () {
            // Update the "Select All" checkbox state
            const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;

            // Update button state
            toggleCreateInvoiceButton();
        });
    });

    // Initialize button state on page load
    toggleCreateInvoiceButton();

    // Gather selected task IDs and submit them
    createInvoiceBtn.addEventListener("click", function () {
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
    </script>
</x-app-layout>