<!-- Filter tasks script -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const toggleFiltersButton = document.getElementById("toggleFilters"); // Button to toggle filters
        const filterBox = document.getElementById("filterstBox"); // Filter container
        const taskDetailsDiv = document.getElementById("taskDetails"); // Task details container
        const showRightDiv = document.getElementById("showRightDiv"); // Parent container for both

        // Initially hide both filters and task details
        filterBox.style.display = "none";
        taskDetailsDiv.style.display = "none";

        // Track what is currently displayed (filters or task details)
        let currentlyDisplayed = null;

        // Toggle Filters
        if (toggleFiltersButton && filterBox) {
            toggleFiltersButton.addEventListener("click", function() {
                if (currentlyDisplayed === "filters") {
                    filterBox.style.display = "none"; // Hide filters
                    showRightDiv.classList.add("hidden"); // Hide container if nothing else is open
                    currentlyDisplayed = null;
                } else {
                    filterBox.style.display = "block"; // Show filters
                    taskDetailsDiv.style.display = "none"; // Ensure task details are hidden
                    showRightDiv.classList.remove("hidden"); // Show the container
                    currentlyDisplayed = "filters";
                }
            });
        }

        // Show Task Details
        const viewTaskLinks = document.querySelectorAll(".viewTask");
        viewTaskLinks.forEach((link) => {
            link.addEventListener("click", function(event) {
                event.preventDefault();

                const taskId = this.getAttribute("data-task-id");

                // If the same task is clicked again, hide the details
                if (currentlyDisplayed === `task-${taskId}`) {
                    taskDetailsDiv.innerHTML = ""; // Clear task details
                    taskDetailsDiv.style.display = "none"; // Hide task details
                    showRightDiv.classList.add("hidden"); // Hide container
                    currentlyDisplayed = null;
                    return;
                }

                // Fetch and display task details
                fetch(`/tasks/${taskId}`)
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error(`Failed to fetch task details: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log("Fetched Data:", data); // Debugging log

                        // Ensure valid data
                        if (data && data.client_name) {
                            const taskIcon = data.type === 'Flight' ?
                                `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                    <path fill="#1e40af" fill-rule="evenodd"
                                        d="m14.014 17l-.006 2.003c-.001.47-.002.705-.149.851s-.382.146-.854.146h-3.01c-3.78 0-5.67 0-6.845-1.172c-.81-.806-1.061-1.951-1.14-3.817c-.015-.37-.023-.556.046-.679c.07-.123.345-.277.897-.586a1.999 1.999 0 0 0 0-3.492c-.552-.308-.828-.463-.897-.586s-.061-.308-.045-.679c.078-1.866.33-3.01 1.139-3.817C4.324 4 6.214 4 9.995 4h3.51a.5.5 0 0 1 .501.499L14.014 7c0 .552.449 1 1.002 1v2c-.553 0-1.002.448-1.002 1v2c0 .552.449 1 1.002 1v2c-.553 0-1.002.448-1.002 1"
                                        clip-rule="evenodd" />
                                    <path fill="#1e40af"
                                        d="M15.017 16c.553 0 1.002.448 1.002 1v1.976c0 .482 0 .723.155.87c.154.148.39.138.863.118c1.863-.079 3.007-.331 3.814-1.136c.809-.806 1.06-1.952 1.139-3.818c.015-.37.023-.555-.046-.678c-.069-.124-.345-.278-.897-.586a1.999 1.999 0 0 1 0-3.492c.552-.309.828-.463.897-.586c.07-.124.061-.309.046-.679c-.079-1.866-.33-3.011-1.14-3.818c-.877-.875-2.154-1.096-4.322-1.152a.497.497 0 0 0-.509.497V7c0 .552-.449 1-1.002 1v2a1 1 0 0 1 1.002 1v2c0 .552-.449 1-1.002 1z"
                                        opacity=".5" />
                                </svg>` :
                                `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                    <path fill="#1e40af"
                                        d="M17 19h2v-8h-6v8h2v-6h2zM3 19V4a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v5h2v10h1v2H2v-2zm4-8v2h2v-2zm0 4v2h2v-2zm0-8v2h2V7z" />
                                </svg>`;

                            const taskDescription = data.type === 'Flight' ? 'Kuala Lumpur ------->> Landon' : 'Hotel Name/ London';
                            // Populate task details
                            taskDetailsDiv.innerHTML = `
                                <div class="justify-center flex items-center mb-5">
                                    <span class="text-center px-5 py-3 w-full text-lg badge bg-[#b1c0db] dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 shadow-md dark:group-hover:bg-transparent rounded-lg text-black dark:text-gray-300">
                                        ${data.supplier.name}
                                    </span>
                                </div>
                                <div class="p-4 justify-between flex items-center gap-5">
                                    <div class='flex gap-2'>
                                        <h3 class='text-lg font-bold mb-2'>Task Details</h3>
                                    </div>
                                    <p><span class="badge whitespace-nowrap px-2 py-1 rounded text-sm font-medium
                                        ${data.status === 'Completed' ? 'badge-outline-success' : ''}
                                        ${data.status === 'Assigned' ? 'badge-outline-assigned' : ''}
                                        ${data.status === 'Booked' ? 'badge-outline-booked' : ''}
                                        ${data.status === 'Pending' ? 'badge-outline-danger' : ''}
                                        ${data.status === 'Confirmed' ? 'badge-outline-primary' : ''}
                                        ${data.status === 'Cancelled' ? 'badge-outline-danger' : ''}
                                        ${data.status === 'Hold' ? 'badge-outline-danger' : ''}">
                                        <strong>${data.status}</strong>
                                    </span></p>
                                </div>
                                <div class='flex flex-col'>
                                    <div class="p-4 justify-between flex items-center gap-5">
                                        <h3 class='text-md font-bold mb-2'>${data.client_name}</h3>
                                        <p>${data.price} - <span class='text-[#1e40af]'>KWD</span></p>
                                    </div>
                                    <!-- flight details -->
                                    <div class="p-4 justify-between flex items-center gap-5">
                                        <div class="flex gap-2 items-center">
                                            ${taskIcon}
                                            <p>${data.type}</p>
                                        </div>
                                        <div>${taskDescription}</div>
                                    </div>
                                    <!-- ./flight details -->
                                    <span class='border-b-2 border-[#b1c0db] mb-5'></span>
                                    <div class='border border-gray-200 rounded-md hover:bg-gray-200 flex items-center'>
                                        <p class='p-3 rounded-l-md bg-[#b1c0db] dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 w-24'>Agent:</p>
                                        <p class="ml-2 flex-1">${data.agent_name || 'Agent name'}</p>
                                    </div>
                                    <div class='mt-3 border border-gray-200 rounded-md hover:bg-gray-200 flex items-center'>
                                        <p class='p-3 rounded-l-md bg-[#b1c0db] dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 w-24'>Branch:</p>
                                        <p class="ml-2 flex-1">${data.agent_name || 'Branch name'}</p>
                                    </div>
                                </div>
                            `;
                            // Show task details and adjust styles
                            taskDetailsDiv.style.display = "block"; // Show task details
                            filterBox.style.display = "none"; // Hide filters
                            showRightDiv.classList.remove("hidden"); // Show the container
                            currentlyDisplayed = `task-${taskId}`;
                        } else {
                            console.warn("Invalid Data:", data); // Debugging log
                            taskDetailsDiv.innerHTML = "<p class='text-red-500'>Invalid task data received.</p>";
                            showRightDiv.classList.remove("hidden");
                        }
                    })
                    .catch((error) => {
                        console.error("Error fetching task details:", error);
                    });
            });
        });

        const filters = {
            price: {
                element: document.getElementById("priceRange"),
                value: NaN,
            },
            supplier: {
                element: document.getElementById("supplier_id"),
                selected: new Set(),
            },
            branch: {
                element: document.getElementById("branch_id"),
                selected: new Set(),
            },
            agent: {
                element: document.getElementById("agent_id"),
                selected: new Set(),
            },
            status: {
                element: document.getElementById("status_id"),
                selected: new Set(),
            },
            type: {
                element: document.getElementById("type_id"),
                selected: new Set(),
            },
        };

        const filterContainers = {
            supplier: document.getElementById("selected-suppliers"),
            branch: document.getElementById("selected-branches"),
            agent: document.getElementById("selected-agents"),
            status: document.getElementById("selected-statuses"),
            type: document.getElementById("selected-types"),
        };

        const tableBody = document.querySelector("#myTable tbody");
        const tableRows = Array.from(tableBody.querySelectorAll("tr"));

        function updateFilterCount() {
            const activeFilters = Object.keys(filters).filter((key) => {
                if (key === "price") return !isNaN(filters.price.value);
                return filters[key].selected.size > 0;
            });

            const filterBadge = document.querySelector(".filter-badge span");
            if (filterBadge) filterBadge.textContent = `${activeFilters.length} applied`;
        }

        function filterTable() {
            let visibleRows = 0;

            tableRows.forEach((row) => {
                const rowPrice = parseFloat(row.getAttribute("data-price"));
                const rowSupplier = row.getAttribute("data-supplier-id");
                const rowBranch = row.getAttribute("data-branch-id");
                const rowAgent = row.getAttribute("data-agent-id");
                const rowStatus = row.getAttribute("data-status");
                const rowType = row.getAttribute("data-type");

                const matchesPrice = isNaN(filters.price.value) || rowPrice <= filters.price.value;
                const matchesSupplier = filters.supplier.selected.size === 0 || filters.supplier.selected.has(rowSupplier);
                const matchesBranch = filters.branch.selected.size === 0 || filters.branch.selected.has(rowBranch);
                const matchesAgent = filters.agent.selected.size === 0 || filters.agent.selected.has(rowAgent);
                const matchesStatus = filters.status.selected.size === 0 || filters.status.selected.has(rowStatus);
                const matchesType = filters.type.selected.size === 0 || filters.type.selected.has(rowType);

                if (matchesPrice && matchesSupplier && matchesBranch && matchesAgent && matchesStatus && matchesType) {
                    row.style.display = "";
                    visibleRows++;
                } else {
                    row.style.display = "none";
                }
            });

            const noDataMessage = document.getElementById("no-data-message");
            if (visibleRows === 0) {
                if (!noDataMessage) {
                    const messageRow = document.createElement("tr");
                    messageRow.id = "no-data-message";
                    messageRow.innerHTML = `<td colspan="8" class="text-center text-gray-500 py-4">No data for the selected criteria</td>`;
                    tableBody.appendChild(messageRow);
                }
            } else if (noDataMessage) {
                noDataMessage.remove();
            }

            document.dispatchEvent(new CustomEvent("filterUpdated")); // Notify pagination script
        }

        function handleDropdownChange(filter, container, idAttr) {
            const selectedOption = filter.element.options[filter.element.selectedIndex];
            const id = selectedOption.value;
            const name = selectedOption.text;

            if (id && !document.getElementById(`${idAttr}-${id}`)) {
                const tag = createFilterTag(id, name, idAttr);
                container.appendChild(tag);
                filter.selected.add(id);
                updateFilterCount();
                filterTable();
            }

            filter.element.value = "";
        }

        filters.price.element.addEventListener("input", function() {
            filters.price.value = parseFloat(this.value) || NaN;
            document.getElementById("ShowTaskFilters").textContent = this.value;
            updateFilterCount();
            filterTable();
        });

        Object.keys(filters).forEach((key) => {
            if (key !== "price") {
                filters[key].element.addEventListener("change", () =>
                    handleDropdownChange(filters[key], filterContainers[key], key)
                );
            }
        });

        function createFilterTag(id, name, type) {
            const tag = document.createElement("div");
            tag.id = `${type}-${id}`;
            tag.className = "bg-[#5f77c6] text-white text-sm px-3 py-1 rounded-lg flex items-center justify-between";
            tag.innerHTML = `<span>${name}</span> <button class="ml-2 text-white" onclick="removeFilter('${id}', '${type}')">&times;</button>`;
            return tag;
        }

        window.removeFilter = function(id, type) {
            const tag = document.getElementById(`${type}-${id}`);
            if (tag) {
                tag.remove();
                filters[type].selected.delete(id);
                updateFilterCount();
                filterTable();
            }
        };

        document.getElementById("clearFilters").addEventListener("click", function() {
            Object.keys(filters).forEach((key) => {
                if (key === "price") {
                    filters.price.value = NaN;
                    filters.price.element.value = filters.price.element.min;
                } else {
                    filters[key].selected.clear();
                    filterContainers[key].innerHTML = "";
                }
            });

            updateFilterCount();
            filterTable();
        });

        filterTable();
    });


    document.addEventListener("DOMContentLoaded", function() {
        const taskListContainer = document.querySelector(".content-70"); // Main task list container
        const showRightDiv = document.getElementById("showRightDiv"); // Sidebar container
        const filterBox = document.getElementById("filterstBox");
        const taskDetailsDiv = document.getElementById("taskDetails");

        // Function to show details or filters
        function showSidebar(contentId) {
            // Add the 'shrink' class to the main task list container
            taskListContainer.classList.add("shrink");

            // Show the sidebar container
            showRightDiv.classList.add("visible");

            // Show the requested content (either filters or task details)
            if (contentId === "filters") {
                filterBox.style.display = "block";
                taskDetailsDiv.style.display = "none";
            } else if (contentId === "details") {
                filterBox.style.display = "none";
                taskDetailsDiv.style.display = "block";
            }
        }

        // Function to hide the sidebar
        function hideSidebar() {
            // Remove the 'shrink' class from the main task list container
            taskListContainer.classList.remove("shrink");

            // Hide the sidebar container
            showRightDiv.classList.remove("visible");

            // Hide both filters and task details
            filterBox.style.display = "none";
            taskDetailsDiv.style.display = "none";
        }

        // Example: Hook this up with existing toggling logic
        document.getElementById("toggleFilters").addEventListener("click", function() {
            if (filterBox.style.display === "block") {
                hideSidebar();
            } else {
                showSidebar("filters");
            }
        });

        document.querySelectorAll(".viewTask").forEach((link) => {
            link.addEventListener("click", function() {
                const taskId = this.getAttribute("data-task-id");
                showSidebar("details");
                // Additional logic to fetch and display task details goes here
            });
        });
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