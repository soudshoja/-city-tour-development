<div class="tableCon">
    <div class="content-70">
        <div class="panel BoxShadow rounded-lg">

            <x-search action="{{ route('clients.index') }}" searchParam='search' placeholder="Quick search for clients" />

            <div class="dataTable-wrapper dataTable-loading no-footer fixed-columns">
                <div class="dataTable-top"></div>

                <div class="dataTable-container h-max">
                    <table id="myTable" class="table-hover whitespace-nowrap dataTable-table">
                        <thead>
                            <tr>
                                <!-- <th>
                                    <label class="custom-checkbox">
                                        <input type="checkbox" id="selectAll" class="text-gray-300 hidden">
                                        <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                            <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" rx="4" />
                                        </svg>
                                    </label>
                                </th> -->
                                <th
                                    class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300 text-center">
                                    Actions
                                </th>
                                <th
                                    class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300 text-center">
                                    Client's Name
                                </th>
                                <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300 text-center">
                                    Created
                                </th>
                                <th
                                    class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300 text-center">
                                    Credit (KWD)
                                </th>
                                <th
                                    class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300 text-center">
                                    Email</th>
                                <th
                                    class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300 text-center">
                                    Phone</th>
                                <th
                                    class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300 text-center">
                                    Agent's Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($clients->isEmpty())
                            <tr>
                                <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-500 ">No
                                    data for now.... Create new!</td>
                            </tr>
                            @else
                            @foreach ($clients as $client)
                            <tr data-name="{{ $client->first_name }}" data-email="{{ $client->email }}"
                                data-phone="{{ $client->phone }}" data-agent-id="{{ $client->agent_id }}"
                                data-client-id="{{ $client ? $client->id : null }}" class="taskRow">
                                <!-- <td>
                                    <label class="custom-checkbox" data-tooltip="select client">
                                        <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox text-gray-900 dark:text-gray-300" data-id="{{ $client->id }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" class="checkbox-svg">
                                            <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" rx="4" />
                                        </svg>
                                    </label>
                                </td> -->
                                <td class="p-3 text-sm text-center">
                                    <a href="javascript:void(0);"
                                        class="viewClient inline-flex items-center justify-center mx-auto text-blue-600 dark:text-blue-300"
                                        data-id="{{ $client->id }}" data-name="{{ $client->first_name }}"
                                        data-email="{{ $client->email }}" data-phone="{{ $client->phone }}"
                                        data-tooltip="see Client">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                            viewBox="0 0 24 24">
                                            <g fill="none" stroke="currentColor" stroke-width="1">
                                                <path
                                                    d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z"
                                                    opacity=".5" />
                                                <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z" />
                                            </g>
                                        </svg>
                                    </a>
                                </td>

                                <!-- <td
                                            class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300 cursor-pointer"> -->
                                <td
                                    class=" p-3 text-sm font-semibold text-blue-600 dark:text-gray-300 text-center">
                                    <a href="{{ route('clients.show', ['id' => $client->id]) }}"
                                        class="block">
                                        <p>{{ $client->first_name }}</p>
                                        <p> {{ $client->middle_name ? $client->middle_name : '' }}
                                            {{ $client->last_name ? $client->last_name : '' }}
                                        </p>
                                    </a>
                                </td>
                                <td>
                                    {{ date('d M Y', strtotime($client->created_at)) }}
                                </td>

                                {{-- <td
                                            class="p-3 text-sm font-semibold text-gray-900 dark:text-gray-300 text-center">
                                            <a href="javascript:void(0);"
                                                class="clientCreditLink text-blue-600 font-bold"
                                                data-client-id="{{ $client->id }}">
                                {{ $client->credit ? number_format($client->credit, 2) : 'N/A' }}
                                </a>
                                </td> --}}
                                <td class="p-3 text-sm font-semibold text-center">
                                    @php
                                    $totalCredit = \App\Models\Credit::getTotalCreditsByClient($client->id);
                                    $creditColor = $totalCredit >= 0 ? 'text-green-600' : 'text-red-600';
                                    @endphp
                                    <a href="javascript:void(0);"
                                        class="clientCreditLink font-bold {{ $creditColor }}"
                                        data-client-id="{{ $client->id }}">
                                        {{ number_format($totalCredit, 2) }}
                                    </a>
                                </td>

                                <td
                                    class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300 text-center">
                                    {{ $client->email ? $client->email : 'N/A' }}
                                </td>
                                <td
                                    class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300 text-center">
                                    {{ $client->phone ? $client->phone : 'N/A' }}
                                </td>
                                <td
                                    class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300 text-center">
                                    {{ $client->agent ? $client->agent->name : 'N/A' }}
                                </td>
                            </tr>
                            @endforeach
                            @endif
                        </tbody>
                    </table>

                </div>

                <x-pagination :data="$clients" />

            </div>
        </div>
    </div>


    <!-- right -->
    <!-- Client Details Container -->
    <div class="lg:w-[40%] md:w-[30%] sm:w-[100%] hidden" id="showClientRightDiv">
        <div id="clientDetails" class="mt-0 mb-0 my-5 w-full p-5 bg-opacity-50 bg-white dark:bg-gray-800  m-0">
        </div>
        <!-- Client details will be rendered here -->
        <!-- Form to add new client -->
        <div class="mt-0 mb-0 my-5 w-full p-5 bg-opacity-50 bg-white dark:bg-gray-800">
            <button type="button" id="openClientModalButton"
                class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
                        city-light-yellow hover:text-[#004c9e] py-4 rounded-full shadow city-light-yellow">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="#004c9e" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="10" cy="6" r="4" fill="#004c9e" />
                    <path
                        d="M18 17.5C18 19.9853 18 22 10 22C2 22 2 19.9853 2 17.5C2 15.0147 5.58172 13 10 13C14.4183 13 18 15.0147 18 17.5Z"
                        fill="#004c9e" />
                    <path d="M21 10H19M19 10H17M19 10L19 8M19 10L19 12" stroke="#004c9e" stroke-width="1.5"
                        stroke-linecap="round" />
                </svg><span class="pl-5">Add Family/Group</span>
            </button>
        </div>
        <input id="parentId" type="hidden" name="parentId" />
        <input id="childId" type="hidden" name="childId" />

        <div class="mt-0 mb-0 my-5 w-full p-5 bg-opacity-50 bg-white dark:bg-gray-800">
            <h2 class="text-lg font-bold">Belongs To</h2>
            <ul id="par-client-list">
                <!-- Sub-clients will be listed here dynamically -->
            </ul>
        </div>

        <div class="mt-0 mb-0 my-5 w-full p-5 bg-opacity-50 bg-white dark:bg-gray-800">
            <h2 class="text-lg font-bold">Child Group</h2>
            <ul id="sub-client-list">
                <!-- Sub-clients will be listed here dynamically -->
            </ul>
        </div>
    </div>
    <!-- ./right -->
</div>

<!-- Clients Modal -->
<div id="clientModal" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden ">
    <div class="bg-white border rounded-lg shadow-lg  w-3/4 md:w-1/2 mb-10">
        <!-- Modal Header -->
        <div class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3">
            <h5 class="text-lg font-bold">Client Management</h5>
            <button type="button" class="text-white-dark hover:text-dark" id="closeClientModalButton">
                <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                    stroke-linejoin="round" class="h-6 w-6">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <!-- ./Modal Header -->

        <!-- Tabs -->
        <div class="border-b flex justify-center">
            <button class="tab-button px-4 py-2 text-blue-500 border-b-2 border-blue-500" id="selectTabButton">Select
                Client</button>
        </div>
        <!-- ./Tabs -->

        <!-- Tab Content -->
        <div id="selectTab" class="p-6">
            <!-- Search Box -->
            <div class="relative mb-4">
                <input type="text" placeholder="Search Client..."
                    class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                    id="clientSearchInput">
            </div>
            <!-- ./Search Box -->

            <!-- List of Clients -->
            <ul id="clientList"
                class="shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] border rounded-lg mb-4 max-h-60 overflow-y-auto custom-scrollbar">
                <!-- Dynamic list items go here -->
            </ul>
            <!-- ./List of Clients -->
        </div>
    </div>
</div>

<!-- edit Agent details modal -->
<div id="editClientModal"
    class="fixed z-10 inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 backdrop-blur-sm hidden">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 relative">

        <!-- Close Button (Top Right) -->
        <button onclick="closeClientEditModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Modal Title -->
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 text-center">Define Relationship </h2>

        <div class="body p-4">
            <div class="grid gap-4">
                <div id="clientDetails2" class="panel w-full xl:mt-0 rounded-lg h-auto">
                </div>
                <input id="selectedId" type="hidden" name="selectedId" />
                <label for="relation" class="w-full text-sm font-semibold">Relationship</label>
                <select id="relation" name="relation"
                    class="border border-gray-200 dark:border-gray-600 p-2 rounded-md"></select>
                <button onclick="updateClientGroup()" class="p-2 rounded-md bg-black text-white">Update</button>
            </div>


        </div>
    </div>
    <!-- ./edit agent details modal -->
</div>


<!-- Credit Details Modal -->
<div id="creditDetailsModal"
    class="fixed inset-0 z-50 hidden bg-gray-800 bg-opacity-50 flex items-center justify-center px-4"
    onclick="closeModalOnOutsideClick(event)">
    <div class="bg-white dark:bg-gray-800 rounded-lg w-full max-w-3xl relative max-h-[90vh]"
        onclick="event.stopPropagation();">
        <div class="flex justify-between items-center p-4 border-b">
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-200">Credit Transaction Details</h2>
            <div class="flex items-center gap-3">
                <a id="openLedgerBtn" href="#" target="_blank" rel="noopener"
                    class="hidden inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-md bg-blue-50 text-blue-700 border border-blue-100
                            hover:bg-blue-100 hover:text-blue-800 dark:bg-slate-700 dark:text-slate-100 dark:border-slate-600 dark:hover:bg-slate-600">
                    View Full Ledger
                </a>
                <button id="closeModal" class="text-gray-500 hover:text-red-500 text-2xl leading-none">&times;</button>
            </div>
        </div>

        <div class="p-4">
            <form id="creditFilterForm" class="flex flex-wrap gap-4 mb-4">
                <input type="date" name="from" id="filterFromDate"
                    class="border p-2 rounded w-full sm:w-auto">
                <input type="date" name="to" id="filterToDate" class="border p-2 rounded w-full sm:w-auto">
                <input type="hidden" id="modalClientId" name="client_id">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                    Filter
                </button>
            </form>

            <!-- Scrollable Table -->
            <div class="overflow-y-auto max-h-[350px]">
                <table class="w-full text-m text-left border-collapse">
                    <thead class="sticky top-0 bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="p-2 border-b">Date</th>
                            <th class="p-2 border-b">Type</th>
                            <th class="p-2 border-b">Description</th>
                            <th class="p-2 border-b text-right">Amount (KWD)</th>
                        </tr>
                    </thead>
                    <tbody id="creditDetailsBody">
                        <!-- Rows will be populated dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    resetSearchButton = document.getElementById("resetSearch");

    if (resetSearchButton) {
        resetSearchButton.addEventListener("click", function() {
            window.location.href = "{{ route('clients.index') }}"; // Redirect to the clients index route
        });
    }

    let clients = @json($fullClients);
    const viewClientLinks = document.querySelectorAll(".viewClient");
    const showClientRightDiv = document.getElementById("showClientRightDiv"); // Correct element ID
    const clientDetailsDiv = document.getElementById("clientDetails");
    const relationships = [
        "Father", "Mother", "Driver", "Maid", "Son", "Daughter", "Husband", "Wife", "Brother", "Sister",
        "Grandfather", "Grandmother", "Uncle", "Aunt", "Nephew", "Niece", "Cousin", "Guardian", "Employer",
        "Employee", "Manager", "Supervisor", "Client", "Customer", "Supplier", "Partner", "Friend", "Neighbor",
        "Doctor", "Teacher", "Lawyer", "Counselor", "Patient", "Student", "Coach", "Tutor", "Admin",
        "Receptionist", "Colleague", "Accountant", "Consultant", "Investor", "Banker"
    ];


    // Get the select element
    const relationSelect = document.getElementById("relation");

    // Populate the select dropdown
    relationships.forEach(relation => {
        let option = document.createElement("option");
        option.value = relation.toLowerCase(); // Use lowercase values
        option.textContent = relation; // Display text
        relationSelect.appendChild(option);
    });


    renderClientList(clients);

    viewClientLinks.forEach((link) => {

        link.addEventListener("click", function(event) {
            event.preventDefault();

            // Extract client data from the clicked link
            const clientId = this.getAttribute("data-id");
            document.getElementById("parentId").value = clientId;
            const clientName = this.getAttribute("data-name");
            const clientEmail = this.getAttribute("data-email");
            const clientPhone = this.getAttribute("data-phone");
            // Populate the sidebar with client details
            clientDetailsDiv.innerHTML = `
            <div class="bg-white dark:bg-gray-700 rounded-xl shadow-xl p-6 space-y-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center font-bold text-lg">
                        ${clientName.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800 dark:text-white">${clientName}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-300">Client ID: <span class="font-medium">${clientId}</span></p>
                    </div>
                </div>

                <div class="border-t border-gray-200 dark:border-gray-600 pt-4">
                    <div class="text-sm text-gray-700 dark:text-gray-200 space-y-2">
                        <div class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-300">Email:</span>
                            <a href="mailto:${clientEmail}" class="text-blue-600 dark:text-blue-400 hover:underline">${clientEmail}</a>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-semibold text-gray-600 dark:text-gray-300">Phone:</span>
                            <a href="tel:${clientPhone}" class="text-blue-600 dark:text-blue-400 hover:underline">${clientPhone}</a>
                        </div>
                    </div>
                </div>
            </div>
        `;

            fetchSubClients(clientId);
            fetchParClients(clientId);
            if (showClientRightDiv.classList.contains("hidden")) {

                showClientRightDiv.classList.remove("hidden");

            } else {

                showClientRightDiv.classList.add("hidden");

            }


        });
    });

    async function fetchSubClients(parentClientId) {
        const fetchUrl = "{{ route('clients.sub', ':parentClientId') }}".replace(':parentClientId', parentClientId);


        try {
            const response = await fetch(fetchUrl, {
                method: "GET",
                headers: {
                    "Accept": "application/json", // Expecting JSON response
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const data = await response.json(); // Parse response as JSON
            updateSubClientList(data);
        } catch (error) {
            console.error("Error fetching sub-clients:", error);
        }
    }


    function updateSubClientList(subClients) {
        const subClientList = document.getElementById("sub-client-list");
        subClientList.innerHTML = ""; // Clear existing list

        if (subClients.length === 0) {
            subClientList.innerHTML = "<li>No sub-clients found.</li>";
            return;
        }

        subClients.forEach(client => {
            const listItem = document.createElement("li");
            listItem.textContent = `${client.client.name} - ${client.relation}`;
            listItem.classList.add("border", "p-2", "rounded-md", "mb-2");

            subClientList.appendChild(listItem);
        });
    }

    async function fetchParClients(childClientId) {
        const fetchUrl = "{{ route('clients.parent', ':childClientId') }}".replace(':childClientId', childClientId);

        try {
            const response = await fetch(fetchUrl, {
                method: "GET",
                headers: {
                    "Accept": "application/json", // Expecting JSON response
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const data = await response.json(); // Parse response as JSON
            updateParClientList(data);
        } catch (error) {
            console.error("Error fetching par-clients:", error);
        }
    }

    function updateParClientList(parClients) {
        const parClientList = document.getElementById("par-client-list");
        parClientList.innerHTML = ""; // Clear existing list

        if (parClients.length === 0) {
            parClientList.innerHTML = "<li>No parent-clients found.</li>";
            return;
        }

        parClients.forEach(client => {
            const listItem = document.createElement("li");
            listItem.textContent = `${client.client.name} - ${client.client.email}`;
            listItem.classList.add("border", "p-2", "rounded-md", "mb-2");

            parClientList.appendChild(listItem);
        });
    }


    document.getElementById("openClientModalButton").onclick = openClientModal;
    document.getElementById("closeClientModalButton").onclick = closeClientModal;
    document.getElementById('clientSearchInput').addEventListener('input', filterClients);

    function openClientModal() {
        const modal = document.getElementById("clientModal");
        modal.classList.remove("hidden");
    }

    // Close Client Modal
    function closeClientModal() {
        const modal = document.getElementById("clientModal");
        modal.classList.add("hidden");
    }

    function renderClientList(clientData) {
        const clientList = document.getElementById('clientList');
        clientList.innerHTML = '';
        clientData.forEach(client => {
            const li = document.createElement('li');
            li.className = 'cursor-pointer p-2 hover:bg-gray-100 text-gray-800';
            li.innerText = `${client.name} - ${client.email}`;
            li.onclick = () => addGroup(client.id);
            clientList.appendChild(li);
        });
    }

    function filterClients() {
        const searchValue = document.getElementById('clientSearchInput').value.toLowerCase();
        const filteredClients = clients.filter(client =>
            client.name.toLowerCase().includes(searchValue) || client.email.toLowerCase().includes(searchValue)
        );
        renderClientList(filteredClients);
    }

    async function addGroup(childClientId) {
        const groupUrl = "{{ route('clients.group.add') }}";
        const csrfToken = "{{ csrf_token() }}";
        const parentClientId = document.getElementById("parentId").value;

        try {
            const response = await fetch(groupUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    parent_client_id: parentClientId,
                    child_client_id: childClientId,
                }),
            });

            const data = await response.json();

            if (response.ok) {
                console.log("Client added to group successfully", data);
            } else {
                console.error("Failed to add client to group", data);
            }

        } catch (error) {
            console.error("Error adding client to group:", error);
        }
        fetchSubClients(parentClientId);
        closeClientModal();
        openClientEditModal(childClientId);
    }


    function openClientEditModal(clientId) {
        const modal = document.getElementById("editClientModal");
        modal.classList.remove("hidden"); // Show the modal

        // Update hidden input field with selected client ID
        document.getElementById("selectedId").value = clientId;

        // Fetch client details and update modal content
        fetchClientDetails(clientId);
    }

    // Function to fetch and update client details in modal
    async function fetchClientDetails(id) {

        const fetchUrl = "{{ route('clients.details', ':id') }}".replace(':id', id);

        try {
            const response = await fetch(fetchUrl, {
                method: "GET",
                headers: {
                    "Accept": "application/json", // Expecting JSON response
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const data = await response.json(); // Parse response as JSON
            updateSubClient(data);
        } catch (error) {
            console.error("Error fetching sub-clients:", error);
            alert("Failed to fetch sub-clients. Please try again.");
        }

    }


    function updateSubClient(client) {
        const subClient = document.getElementById("clientDetails2");
        subClient.innerHTML = ""; // Clear existing list


        subClient.innerHTML = `
                            <h3 class="text-lg font-bold mb-4">Client Details</h3>
                            <p><strong>Name:</strong> ${client.name}</p>
                            <p><strong>Email:</strong> ${client.email}</p>
                            <p><strong>Phone:</strong> ${client.phone}</p>
                        `;

    }

    async function updateClientGroup() {
        const id = document.getElementById("parentId").value;
        const updateUrl = "{{ route('clients.group.update', ':id') }}".replace(':id', id);
        const csrfToken = "{{ csrf_token() }}"; // Laravel CSRF token for security
        const relation = document.querySelector("select[name='relation']").value; // Get selected relation
        let selectedId = document.getElementById("selectedId").value;
        const parentClientId = document.getElementById("parentId").value;

        // Ensure selectedId is an integer
        selectedId = parseInt(selectedId, 10);

        // Log the data before sending
        console.log({
            relation: relation,
            selectedId: selectedId
        });

        try {
            const response = await fetch(updateUrl, {
                method: "PUT",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken,
                },
                body: JSON.stringify({
                    relation: relation,
                    selectedId: selectedId,
                }),
            });

            const data = await response.json();

            if (response.ok) {
                console.log("Client group updated successfully", data);
                closeClientEditModal(); // Close modal if applicable
                fetchSubClients(parentClientId);
            } else {
                console.error("Failed to update client group", data);
            }
        } catch (error) {
            console.error("Error updating client group:", error);
        }
    }



    function closeClientEditModal() {
        document.getElementById("editClientModal").classList.add("hidden");
    }



    async function removeGroup(parentClientId, childClientId) {

        const removeUrl = "{{ route('clients.group.remove') }}";

        try {
            const response = await fetch(removeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    parent_client_id: parentClientId,
                    child_client_id: childClientId
                })
            });

            const data = await response.json();

            if (response.ok) {
                alert('Client removed from the group successfully!');
                console.log('Success:', data);
            } else {
                alert('Error: ' + data.message);
                console.error('Error:', data);
            }
        } catch (error) {
            console.error('Network Error:', error);
            alert('Network error occurred!');
        }
    }


    //credit details modal
    document.querySelectorAll('.clientCreditLink').forEach(link => {
        link.addEventListener('click', function() {
            const clientId = this.dataset.clientId;
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

            const toDateString = date => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            const from = toDateString(firstDay);
            const to = toDateString(lastDay);

            document.getElementById('filterFromDate').value = from;
            document.getElementById('filterToDate').value = to;
            document.getElementById('modalClientId').value = clientId;

            const ledgerBtn = document.getElementById('openLedgerBtn');
            const ledgerUrlTemplate = "{{ route('clients.credits', ':clientId') }}";
            if (ledgerBtn) {
                ledgerBtn.href = ledgerUrlTemplate.replace(':clientId', clientId);
                ledgerBtn.classList.remove('hidden');
            }

            fetchCredits(clientId, from, to);
            document.getElementById('creditDetailsModal').classList.remove('hidden');
        });
    });


    document.getElementById('closeModal').addEventListener('click', function() {
        document.getElementById('creditDetailsModal').classList.add('hidden');
    });

    document.getElementById('creditFilterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const clientId = document.getElementById('modalClientId').value;
        const from = document.getElementById('filterFromDate').value;
        const to = document.getElementById('filterToDate').value;
        fetchCredits(clientId, from, to);
    });

    function fetchCredits(clientId, from, to) {
        fetch(`/credits/filter?client_id=${clientId}&from=${from}&to=${to}`)
            .then(response => response.json())
            .then(data => {
                const body = document.getElementById('creditDetailsBody');
                body.innerHTML = '';

                if (data.length === 0) {
                    body.innerHTML =
                        `<tr><td colspan="4" class="p-2 text-center text-gray-500">No records found</td></tr>`;
                    return;
                }

                data.forEach(credit => {
                    body.innerHTML += `
                    <tr>
                        <td class="p-2">${credit.date}</td>
                        <td class="p-2">${credit.type ?? '-'}</td>
                        <td class="p-2">${credit.description ?? '-'}</td>
                        <td class="p-2 text-right font-semibold ${credit.amount >= 0 ? 'text-green-600' : 'text-red-600'}">
                            ${parseFloat(credit.amount).toFixed(2)}
                        </td>
                    </tr>
                `;
                });
            });
    }

    function closeModalOnOutsideClick(event) {
        const modal = document.getElementById('creditDetailsModal');
        modal.classList.add('hidden');
    }

    document.getElementById('closeModal').addEventListener('click', () => {
        document.getElementById('creditDetailsModal').classList.add('hidden');
    });
</script>