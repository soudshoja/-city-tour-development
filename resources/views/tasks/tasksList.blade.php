<x-app-layout>

    <!-- page wrapper -->
    <div class="mx-auto">


        <!-- page title -->
        <div class="flex justify-between items-center gap-5 my-3 ">


            <div class="flex items-center gap-5 ">
                <h2 class="text-3xl font-bold">Tasks List</h2>
                <!-- total task number -->
                <div class="relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                    <span class="text-xl font-bold text-slate-700">{{ $taskCount }}</span>
                </div>
            </div>
            <!-- add new task & refresh page -->
            <div class="flex items-center gap-5">
                <div class="relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                        <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                    </svg>
                </div>

                <!-- add invoice icon -->
                <div class="relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#333333" d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </div>
            </div>


        </div>
        <!-- ./page title -->


        <!-- actions -->
        <div class="w-full justify-between flex flex-col gap-5 mt-5 md:flex-row">
            <div class="w-[70%]">
                <!-- Table  -->
                <div class="panel oxShadow rounded-lg">
                    <!--  search icon -->
                    <div class="relative">
                        <!-- Search Input -->
                        <input type="text" placeholder="Find fast in tasks table..." class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider" id="searchInput">

                        <!-- Search Button with SVG Icon -->
                        <button type="button" class="btn DarkBCcolor absolute inset-y-0 m-auto flex h-9 w-9 items-center justify-center rounded-full p-0 right-1"
                            id="searchButton">
                            <svg class="mx-auto" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="11.5" cy="11.5" r="9.5" stroke="#fff" stroke-width="1.5" opacity="0.5"></circle>
                                <path d="M18.5 18.5L22 22" stroke="#fff" stroke-width="1.5" stroke-linecap="round"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- ./search icon -->
                    <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                        <div class="dataTable-top"></div>
                        <!-- table -->
                        <div class="dataTable-container h-max">
                            <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                                <thead>
                                    <tr>
                                        <th>
                                            <label class="custom-checkbox">
                                                <input type="checkbox" id="selectAll" class="form-checkbox hidden">
                                                <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                    <rect width="18" height="18" x="3" y="3" fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                                </svg>
                                            </label>
                                        </th>

                                        <th class="p-3 text-left text-md font-bold text-gray-500">Actions</th>

                                        <th class="p-3 text-left text-md font-bold text-gray-500">Client Name</th>
                                        @if(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
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

                                        <!-- checkbox -->
                                        <td>
                                            <label class="custom-checkbox">
                                                <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox" value="{{ $task->id }}" {{ $task->invoiceDetail ? 'disabled' : '' }}>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                                    <rect width="18" height="18" x="3" y="3" fill="none" stroke="#333333" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" rx="4" />
                                                </svg>
                                            </label>

                                        </td>
                                        <td class="p-3 text-sm">
                                            <a href="javascript:void(0);" id="viewTask" class="text-blue-500 hover:underline">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                                    <path fill="#1e40af" d="M17.994 20.79q-.16.062-.303-.043q-.145-.105-.181-.276q-.031-.16.049-.31t.239-.217q1.027-.348 1.657-1.217t.63-1.958t-.62-1.957t-1.648-1.218q-.165-.067-.242-.217t-.046-.31q.036-.17.18-.276q.145-.105.304-.043q1.321.396 2.139 1.508q.817 1.111.817 2.513t-.817 2.514t-2.158 1.507m-2.525-.05q-.48-.161-.899-.417t-.774-.611t-.621-.794t-.408-.937q-.061-.16.034-.295t.26-.17q.16-.031.298.061t.205.252q.125.377.322.704t.48.61q.263.263.593.47t.682.331q.159.068.248.208q.09.14.04.3q-.056.165-.178.258q-.122.092-.282.03m.747-2.49q-.106.056-.215.013t-.109-.175V15.45q0-.13.109-.174q.108-.043.214.013l2.01 1.319q.106.055.106.161t-.106.162zm-3.154-2.266q-.166-.036-.261-.17q-.095-.135-.034-.295q.143-.48.408-.908q.266-.428.621-.784t.794-.621q.437-.265.937-.427q.16-.062.291.03q.132.093.169.258q.03.16-.059.31q-.09.15-.25.217q-.37.125-.7.322q-.33.198-.612.48q-.283.283-.48.6q-.197.318-.322.695q-.068.159-.205.242t-.297.052M10.902 21q-.348 0-.576-.229t-.29-.571l-.263-2.092q-.479-.145-1.035-.454q-.557-.31-.948-.664l-1.915.824q-.317.14-.644.03t-.504-.415L3.648 15.57q-.177-.305-.104-.638t.348-.546l1.672-1.25q-.045-.272-.073-.559q-.03-.288-.03-.559q0-.252.03-.53q.028-.278.073-.626l-1.672-1.25q-.275-.213-.338-.555t.113-.648l1.06-1.8q.177-.287.504-.406t.644.021l1.896.804q.448-.373.97-.673q.52-.3 1.013-.464l.283-2.092q.061-.342.318-.571T10.96 3h2.08q.349 0 .605.229q.257.229.319.571l.263 2.112q.575.202 1.016.463t.909.654l1.992-.804q.318-.14.645-.021t.503.406l1.06 1.819q.177.306.104.638t-.348.547l-1.216.911q-.17.14-.36.136q-.188-.005-.347-.176q-.16-.171-.148-.38t.182-.347l1.225-.908l-.994-1.7l-2.552 1.07q-.454-.499-1.193-.935q-.74-.435-1.4-.577L13 4h-1.994l-.312 2.689q-.756.161-1.39.52q-.633.358-1.26.985L5.55 7.15l-.994 1.7l2.169 1.62q-.125.336-.175.73t-.05.82q0 .38.05.755t.156.73l-2.15 1.645l.994 1.7l2.475-1.05q.483.483 1.009.82q.526.338 1.139.544q.044.907.324 1.731t.74 1.515q.123.184.013.387t-.348.203m1.071-11.5q-1.046 0-1.773.724T9.473 12q0 .467.16.89t.479.777q.16.183.366.206q.207.023.384-.136q.177-.154.181-.355t-.154-.347q-.208-.2-.312-.47T10.473 12q0-.625.438-1.063t1.062-.437q.289 0 .565.116q.276.117.476.324q.146.148.338.134q.192-.015.346-.191q.154-.177.134-.381t-.198-.364q-.311-.3-.753-.469t-.908-.169" />
                                                </svg>
                                            </a>
                                        </td>
                                        <td class="p-3 text-sm font-semibold text-gray-500">{{ $task->client_name }}</td>
                                        @if(Auth()->user()->role_id ==\App\Models\Role::COMPANY)
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
            <div class="w-[30%]">

                <div class="flex flex-col md:flex-row justify-center text-center gap-5">
                    <!-- customize -->
                    <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 32 32">
                            <path fill="#333333" d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                        </svg>
                        <span class="text-sm">Customize</span>
                    </button>
                    <!-- ./customize -->

                    <!-- filter -->
                    <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                            <path fill="#333333" d="M10 19h4v-2h-4zm-4-6h12v-2H6zM3 5v2h18V5z" />
                        </svg>
                        <span class="text-sm">Filter</span>
                    </button>
                    <!-- ./filter -->

                    <!-- export -->
                    <button class="flex px-5 py-3 gap-3 bg-white hover:bg-gray-300 rounded-lg shadow-sm items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                            <path fill="#333333" d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1" />
                        </svg>
                        <span class="text-sm">Export</span>
                    </button>
                    <!-- ./export -->
                </div>
                <div class="mt-5 ">
                    <!-- display task details here-->
                    <div id="taskDetails" class="panel w-full xl:mt-0  rounded-lg h-96"></div>
                    <!-- display task details here-->

                </div>
            </div>
            <!-- ./right -->
        </div>
        <!--./actions-->

        <!-- page content -->
        <div class="flex flex-col gap-2.5 xl:flex-row mt-5">




        </div>
        <!-- ./page content -->


    </div>
    <!-- ./page wrapper -->

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
        // show task details
        document.addEventListener("DOMContentLoaded", function() {
            // Get references to the elements
            const viewTaskLink = document.getElementById("viewTask");
            const taskDetailsDiv = document.getElementById("taskDetails");

            // Check if the elements exist
            if (viewTaskLink && taskDetailsDiv) {
                // Add event listener to the 'View' link
                viewTaskLink.addEventListener("click", function(event) {
                    event.preventDefault(); // Prevent default link behavior
                    console.log("View Task clicked!"); // Debug log
                    taskDetailsDiv.textContent =
                        "This is some dummy task detail text shown when you click the link.";
                });
            } else {
                console.error("One or more elements were not found. Check your IDs.");
            }
        });
    </script>

    <!-- search script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const table = document.getElementById('myTable');
            const rows = Array.from(table.querySelector('tbody').rows); // Get all rows

            // Function to filter rows based on search input
            function filterTable() {
                const query = searchInput.value.toLowerCase(); // Get the search query
                rows.forEach(row => {
                    const cells = Array.from(row.cells); // Get all cells in the row
                    const rowText = cells.map(cell => cell.textContent.toLowerCase()).join(' '); // Combine text from all cells
                    if (rowText.includes(query)) {
                        row.style.display = ''; // Show row if it matches the query
                    } else {
                        row.style.display = 'none'; // Hide row if it doesn't match
                    }
                });
            }

            // Event listener for the search input
            searchInput.addEventListener('input', filterTable);
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