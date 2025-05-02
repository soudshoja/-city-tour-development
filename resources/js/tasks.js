let currentPage = 1;
const rowsPerPage = 10;
const dataTableBottom = document.querySelector(".dataTable-bottom");
const paginationList = document.querySelector(".dataTable-pagination-list");
const prevPageButton = document.getElementById("prevPage");
const nextPageButton = document.getElementById("nextPage");

const table = document.getElementById("myTable");
const rows = Array.from(table.querySelector("tbody").rows);
const totalPages = Math.ceil(rows.length / rowsPerPage);

const toggleFiltersButton = document.getElementById("toggleFilters");
const taskDetailsDiv = document.getElementById("taskDetails");
const showRightDiv = document.getElementById("showRightDiv");
let currentlyDisplayed = null;

// toggleFiltersButton.addEventListener("click", function () {
//     if (currentlyDisplayed === "filters") {
//         hideSidebar();
//         return;
//     }

//     currentlyDisplayed = "filters";
//     showSidebar("filters");
// });

// Show Task Details

const viewTaskLinks = document.querySelectorAll(".viewTask");
viewTaskLinks.forEach((link) => {
    link.addEventListener("click", function (event) {
        event.preventDefault();

        const taskId = this.getAttribute("data-task-id");
        const url = this.getAttribute("data-task-url");

        toggleTasksDetails(taskId, url);
        // If the same task is clicked again, hide the details
    });
});

function toggleTasksDetails(taskId, url) {
    if (currentlyDisplayed === `task-${taskId}`) {
        hideSidebar();
        return;
    }

    showSidebar("details");

    fetch(url)
        .then((response) => {
            if (!response.ok) {
                throw new Error(
                    `Failed to fetch task details: ${response.status}`
                );
            }
            return response.json();
        })
        .then((data) => {
            // console.log('data : ' ,data)

            if (data && data.client_name) {
                data.type === "flight"
                    ? `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                    <path fill="#1e40af" fill-rule="evenodd"
                                        d="m14.014 17l-.006 2.003c-.001.47-.002.705-.149.851s-.382.146-.854.146h-3.01c-3.78 0-5.67 0-6.845-1.172c-.81-.806-1.061-1.951-1.14-3.817c-.015-.37-.023-.556.046-.679c.07-.123.345-.277.897-.586a1.999 1.999 0 0 0 0-3.492c-.552-.308-.828-.463-.897-.586s-.061-.308-.045-.679c.078-1.866.33-3.01 1.139-3.817C4.324 4 6.214 4 9.995 4h3.51a.5.5 0 0 1 .501.499L14.014 7c0 .552.449 1 1.002 1v2c-.553 0-1.002.448-1.002 1v2c0 .552.449 1 1.002 1v2c-.553 0-1.002.448-1.002 1"
                                        clip-rule="evenodd" />
                                    <path fill="#1e40af"
                                        d="M15.017 16c.553 0 1.002.448 1.002 1v1.976c0 .482 0 .723.155.87c.154.148.39.138.863.118c1.863-.079 3.007-.331 3.814-1.136c.809-.806 1.06-1.952 1.139-3.818c.015-.37.023-.555-.046-.678c-.069-.124-.345-.278-.897-.586a1.999 1.999 0 0 1 0-3.492c.552-.309.828-.463.897-.586c.07-.124.061-.309.046-.679c-.079-1.866-.33-3.011-1.14-3.818c-.877-.875-2.154-1.096-4.322-1.152a.497.497 0 0 0-.509.497V7c0 .552-.449 1-1.002 1v2a1 1 0 0 1 1.002 1v2c0 .552-.449 1-1.002 1z"
                                        opacity=".5" />
                                </svg>`
                    : `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                    <path fill="#1e40af"
                                        d="M17 19h2v-8h-6v8h2v-6h2zM3 19V4a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v5h2v10h1v2H2v-2zm4-8v2h2v-2zm0 4v2h2v-2zm0-8v2h2V7z" />
                                </svg>`;

                data.type === "flight"
                    ? `${data.country_from} ------->> ${data.country_to}`
                    : data.hotel_name || "Hotel Name/ City";

                // Populate task details
                console.log("data : ", data);

                taskDetailsDiv.innerHTML = `
                               <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                                                <style>
                       .task-container {
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #ffffff;
    border-radius: 12px;
    font-family: "Arial", sans-serif;
    box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
}

/* Supplier Name - Unique Design */
.supplier-name {
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    color: #0047ab;
    background: rgba(0, 71, 171, 0.1);
    padding: 10px;
    border-radius: 8px;
}

/* Section Styling */
.section {
    padding: 15px;
    margin: 15px 0;
    border-radius: 8px;
    background: #f8f9fa;
    border-left: 5px solid #0047ab;
}

/* Unique Backgrounds for Each Section */

.pricing.hotel-details.flight-details.general-info.status {
    background: #fff3cd;
}


/* Icons in Blue */
.blue-icon {
    color: #0047ab;
}

/* Flexbox for Better Alignment */
.info-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.info-row p {
    flex: 1;
    min-width: 45%;
    margin: 5px 0;
}

/* Status Label Styling */
.status-label {
    font-weight: bold;
    padding: 5px 12px;
    border-radius: 5px;
    background: #0047ab;
    color: white;
}

</style>
   <div class="task-container">
    <div class="header">
        <h3 class="supplier-name">
            <i class="fas fa-warehouse"></i> ${data.supplier?.name || "N/A"}
        </h3>
    </div>

    <div class="section status">
        <h4><i class="fas fa-info-circle blue-icon"></i> Status: <span class="status-label">${
            data.status
        }</span></h4>
        <div class="info-row">
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Branch:</strong> ${
                data.agent?.branch?.name || "N/A"
            }</p>
            <p><i class="fas fa-user-tie blue-icon"></i> <strong>Agent:</strong> ${
                data.agent?.name || "N/A"
            }</p>
            <p><i class="fas fa-user blue-icon"></i> <strong>Client:</strong> 
            ${
                data.client_name !== undefined && data.client_name !== null
                    ? data.client_name
                    : "N/A"
            }</p>

        </div>
    </div>

    <div class="section general-info">
        <h4><i class="fas fa-info-circle blue-icon"></i> General Information</h4>
        <div class="info-row">
            <p><i class="fas fa-hashtag blue-icon"></i> <strong>Reference:</strong> ${
                data.reference || "N/A"
            }</p>
            <p><i class="fas fa-tag blue-icon"></i> <strong>Type:</strong> ${
                data.type
            }</p>
        </div>
    </div>

    ${
        data.type === "flight"
            ? `
    <div class="section flight-details">
        <h4><i class="fas fa-plane blue-icon"></i> Flight Details</h4>
        <div class="info-row">
            <p><i class="fas fa-plane-departure blue-icon"></i> ${
                data.country_from
            } <i class="fas fa-plane blue-icon"></i> ${data.country_to}</p>
            <p><i class="fas fa-plane-departure blue-icon"></i> <strong>Departure:</strong> ${
                data.flight_details?.airport_from || "N/A"
            } - ${data.flight_details?.departure_time || "N/A"}</p>
            <p><i class="fas fa-plane-arrival blue-icon"></i> <strong>Arrival:</strong> ${
                data.flight_details?.airport_to || "N/A"
            } - ${data.flight_details?.arrival_time || "N/A"}</p>
            <p><i class="fas fa-ticket-alt blue-icon"></i> <strong>Flight:</strong> ${
                data.flight_details?.flight_number || "N/A"
            } - ${data.flight_details?.class_type || "N/A"}</p>
            <p><i class="fas fa-ticket-alt blue-icon"></i> <strong>Ticket Number:</strong> ${
                data.ticket_number || "N/A"
            }</p>
            <p><i class="fas fa-suitcase blue-icon"></i> <strong>Baggage:</strong> ${
                data.flight_details?.baggage_allowed || "N/A"
            }</p>
        </div>
    </div>`
            : `
    <div class="section hotel-details">
        <h4><i class="fas fa-hotel blue-icon"></i> Hotel Details</h4>
        <div class="info-row">
            <p><i class="fas fa-building blue-icon"></i> <strong>Hotel:</strong> ${
                data.hotel_name || "N/A"
            }</p>
            <p><i class="fas fa-map-marker-alt blue-icon"></i> <strong>Location:</strong> ${
                data.hotel_details?.hotel?.address || "N/A"
            }, ${data.hotel_details?.hotel?.city || "N/A"}</p>
            <p><i class="fas fa-globe blue-icon"></i> <strong>Country:</strong> ${
                data.hotel_country || "N/A"
            }</p>
            <p><i class="fas fa-calendar-check blue-icon"></i> <strong>Check-in:</strong> ${
                data.hotel_details?.check_in || "N/A"
            }</p>
            <p><i class="fas fa-calendar-times blue-icon"></i> <strong>Check-out:</strong> ${
                data.hotel_details?.check_out || "N/A"
            }</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Rating:</strong> ${
                data.hotel_details?.hotel?.rating || "N/A"
            }</p>
            <p><i class="fas fa-star blue-icon"></i> <strong>Room Reference:</strong> ${
                data.hotel_details?.hotel?.room_reference || "N/A"
            }</p>
            <p><i class="fas fa-bed blue-icon"></i> <strong>Room:</strong> ${
                data.hotel_details?.room_type || "N/A"
            } - ${data.hotel_details?.room_number || "N/A"}</p>
        </div>
    </div>`
    }

    <div class="section pricing">
        <h4><i class="fas fa-coins blue-icon"></i> Pricing Details</h4>
        <div class="info-row">
            <p><i class="fas fa-money-bill blue-icon"></i> <strong>Price:</strong> ${
                data.price || "N/A"
            }</p>
            <p><i class="fas fa-percentage blue-icon"></i> <strong>Tax:</strong> ${
                data.tax || "N/A"
            }</p>
            <p><i class="fas fa-calculator blue-icon"></i> <strong>Total:</strong> ${
                data.total || "N/A"
            }</p>
        </div>
    </div>
</div>

`;

                taskDetailsDiv.style.display = "block";
                showRightDiv.classList.remove("hidden");
                currentlyDisplayed = `task-${taskId}`;
            } else {
                console.warn("Invalid Data:", data);
            }
        })
        .catch((error) => {
            console.error("Error fetching task details:", error);
        });
}

const taskListContainer = document.querySelector(".content-70"); // Main task list container

function showSidebar(contentId) {
    taskListContainer.classList.add("shrink");

    showRightDiv.classList.add("visible");

    // Show the requested content (either filters or task details)
    if (contentId === "filters") {
        taskDetailsDiv.style.display = "none";
    } else if (contentId === "details") {
        taskDetailsDiv.style.display = "block";
    }
}

function hideSidebar() {
    currentlyDisplayed = null;

    taskListContainer.classList.remove("shrink");

    showRightDiv.classList.remove("visible");

    taskDetailsDiv.style.display = "none";
}

// const visibleRows = filterRows();
// updatePagination(visibleRows);
// showPageParam(1, visibleRows);

// Function to create pagination
function createPagination() {
    // Remove existing page numbers
    Array.from(paginationList.querySelectorAll("li.page-number")).forEach(
        (el) => el.remove()
    );

    // Create and add page numbers dynamically
    for (let i = 1; i <= totalPages; i++) {
        const li = document.createElement("li");
        li.className = `page-number ${i === currentPage ? "active" : ""}`;
        li.innerHTML = `<a href="#" data-page="${i}">${i}</a>`;

        const nextPageElement = paginationList.querySelector("#nextPage");

        // Insert before #nextPage if it exists, otherwise append
        if (nextPageElement) {
            paginationList.insertBefore(li, nextPageElement);
        } else {
            paginationList.appendChild(li);
        }
    }
}

const floatingActions = document.getElementById("floatingActions");
const closeTaskFloatingActions = document.getElementById(
    "closeTaskFloatingActions"
);
const selectAllCheckbox = document.getElementById("selectAll");
const rowCheckboxes = document.querySelectorAll(".rowCheckbox");
const createInvoiceBtn = document.getElementById("createInvoiceBtn");

selectAllCheckbox.addEventListener("change", function () {
    rowCheckboxes.forEach(
        (checkbox) => (checkbox.checked = selectAllCheckbox.checked)
    );
    toggleCreateInvoiceButton(); // Update button state
});

const toggleCreateInvoiceButton = () => {
    const isAnySelected = Array.from(rowCheckboxes).some(
        (checkbox) => checkbox.checked
    );
    createInvoiceBtn.disabled = !isAnySelected;
};

rowCheckboxes.forEach((checkbox) => {
    checkbox.addEventListener("change", function () {
        // Update the "Select All" checkbox state
        const allChecked = Array.from(rowCheckboxes).every((cb) => cb.checked);
        selectAllCheckbox.checked = allChecked;

        // Update button state
        toggleCreateInvoiceButton();

        // Show or hide the floating div based on any checkbox selection
        const isAnyChecked = Array.from(rowCheckboxes).some((cb) => cb.checked);
        if (isAnyChecked) {
            floatingActions.classList.remove("hidden");
        } else {
            floatingActions.classList.add("hidden");
        }
    });
});

toggleCreateInvoiceButton();

createInvoiceBtn.addEventListener("click", function () {
    const selectedTaskIds = Array.from(rowCheckboxes)
        .filter((checkbox) => checkbox.checked)
        .map((checkbox) => checkbox.value);

    if (selectedTaskIds.length === 0) {
        alert("No tasks selected!");
        return;
    }

    console.log(selectedTaskIds);
    const route = this.getAttribute("data-route");
    const url = route + "?task_ids=" + selectedTaskIds.join(",");

    window.location.href = url;
});

// Close the floating div when the "X" button is clicked
closeTaskFloatingActions.addEventListener("click", function () {
    floatingActions.classList.add("hidden");
});
