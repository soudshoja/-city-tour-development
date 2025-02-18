<div class="tableCon">
    <div class="content-70">
        <div class="panel BoxShadow rounded-lg">

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

                    <!-- <button id="toggleFilters" class="flex px-3 py-2 gap-2 city-light-yellow rounded-lg shadow-sm items-center text-xs md:text-sm">
                        <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                            <path fill="#333333" d="M30 8h-4.1c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2v2h14.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30zm-9 4c-1.7 0-3-1.3-3-3s1.3-3 3-3s3 1.3 3 3s-1.3 3-3 3M2 24h4.1c.5 2.3 2.5 4 4.9 4s4.4-1.7 4.9-4H30v-2H15.9c-.5-2.3-2.5-4-4.9-4s-4.4 1.7-4.9 4H2zm9-4c1.7 0 3 1.3 3 3s-1.3 3-3 3s-3-1.3-3-3s1.3-3 3-3" />
                        </svg>
                        <span class="text-xs md:text-sm dark:text-black">Filters</span>
                    </button> -->

                    <!-- <button class="flex px-3 py-2 gap-2 city-light-yellow rounded-lg shadow-sm items-center text-xs md:text-sm">
                        <svg class="w-4 h-4 md:w-5 md:h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path fill="#333333" d="M8.71 7.71L11 5.41V15a1 1 0 0 0 2 0V5.41l2.29 2.3a1 1 0 0 0 1.42 0a1 1 0 0 0 0-1.42l-4-4a1 1 0 0 0-.33-.21a1 1 0 0 0-.76 0a1 1 0 0 0-.33.21l-4 4a1 1 0 1 0 1.42 1.42M21 14a1 1 0 0 0-1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4a1 1 0 0 0-2 0v4a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-4a1 1 0 0 0-1-1" />
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
                                <!-- <th>
                                    <label class="custom-checkbox">
                                        <input type="checkbox" id="selectAll" class="text-gray-300 hidden">
                                        <svg id="selectAllSVG" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" class="checkbox-svg">
                                            <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" rx="4" />
                                        </svg>
                                    </label>
                                </th> -->
                                <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Actions</th>
                                <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Name</th>
                                <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Email</th>
                                <th class="p-3 text-left text-md font-bold text-gray-900 dark:text-gray-300">Phone</th>

                            </tr>
                        </thead>
                        <tbody>
                            @if ($clients->isEmpty())
                            <tr>
                                <td colspan="7" class="text-center p-3 text-sm font-semibold text-gray-500 ">No data for now.... Create new!</td>
                            </tr>
                            @else
                            @foreach ($clients as $client)
                            <tr
                                data-name="{{ $client->name }}" data-email="{{ $client->email }}"
                                data-phone="{{ $client->phone }}" data-agent-id="{{ $client->agent_id }}" data-client-id="{{ $client ? $client->id : null }}" class="taskRow">
                                <!-- <td>
                                    <label class="custom-checkbox" data-tooltip="select client">
                                        <input type="checkbox" class="form-checkbox CheckBoxColor rowCheckbox text-gray-900 dark:text-gray-300" data-id="{{ $client->id }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" class="checkbox-svg">
                                            <rect width="18" height="18" x="3" y="3" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1" rx="4" />
                                        </svg>
                                    </label>
                                </td> -->
                                <td class="p-3 text-sm flex gap-3">

                                    <a href="javascript:void(0);"
                                        class="viewClient text-blue-600 dark:text-blue-300"
                                        data-id="{{ $client->id }}"
                                        data-name="{{ $client->name }}"
                                        data-email="{{ $client->email }}"
                                        data-phone="{{ $client->phone }}"
                                        data-tooltip="see Client">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                            <g fill="none" stroke="currentColor" stroke-width="1">
                                                <path d="M3.275 15.296C2.425 14.192 2 13.639 2 12c0-1.64.425-2.191 1.275-3.296C4.972 6.5 7.818 4 12 4s7.028 2.5 8.725 4.704C21.575 9.81 22 10.361 22 12c0 1.64-.425 2.191-1.275 3.296C19.028 17.5 16.182 20 12 20s-7.028-2.5-8.725-4.704Z" opacity=".5" />
                                                <path d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z" />
                                            </g>
                                        </svg>
                                    </a>
                                    <!-- <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                                        <path fill="none" stroke="#e11d48" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 12H8m-6 0c0 5.523 4.477 10 10 10s10-4.477 10-10S17.523 2 12 2M4.649 5.079q.207-.22.427-.428M7.947 2.73q.273-.122.553-.229M2.732 7.942q-.124.275-.232.558" color="#e11d48" />
                                    </svg> -->
                                </td>


                                <td class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300 cursor-pointer">
                                    <a href="{{ route('clients.show' , ['id' => $client->id ] )}}" class="block">{{ $client->name }}</a>
                                </td>
                                <td class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $client->email ? $client->email : 'N/A' }}
                                </td>
                                <td class=" p-3 text-sm font-semibold text-gray-900 dark:text-gray-300">{{ $client->phone ? $client->phone : 'N/A' }}
                                </td>
                            </tr>
                            @endforeach
                            @endif
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
        </div>
    </div>


    <!-- right -->
    <!-- Client Details Container -->
    <div class="w-[30%] hidden" id="showClientRightDiv">
        <div id="clientDetails" class="panel w-full xl:mt-0 rounded-lg h-auto">
        </div>
            <!-- Client details will be rendered here -->
                     <!-- Form to add new client -->
        <div class="panel w-full xl:mt-0 rounded-lg h-auto">
            <button type="button" id="openClientModalButton"
                class="w-full inline-flex items-center justify-center text-sm text-black font-semibold
                        city-light-yellow hover:text-[#004c9e] py-4 rounded-full shadow city-light-yellow">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="#004c9e"
                    xmlns="http://www.w3.org/2000/svg">
                    <circle cx="10" cy="6" r="4" fill="#004c9e" />
                    <path
                        d="M18 17.5C18 19.9853 18 22 10 22C2 22 2 19.9853 2 17.5C2 15.0147 5.58172 13 10 13C14.4183 13 18 15.0147 18 17.5Z"
                        fill="#004c9e" />
                    <path d="M21 10H19M19 10H17M19 10L19 8M19 10L19 12"
                        stroke="#004c9e" stroke-width="1.5" stroke-linecap="round" />
                </svg><span class="pl-5">Add Family/Group</span>
            </button>
          </div>
          <input id="parentId" type="hidden" name="parentId" />
          <input id="childId" type="hidden" name="childId" />

          <div class="panel w-full xl:mt-0 rounded-lg h-auto">
            <h2 class="text-lg font-bold">Belongs To</h2>
                <ul id="par-client-list">
                    <!-- Sub-clients will be listed here dynamically -->
                </ul>
        </div>

          <div class="panel w-full xl:mt-0 rounded-lg h-auto">
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
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                            class="h-6 w-6">
                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                        </svg>
                                    </button>
                                </div>
                                <!-- ./Modal Header -->

                                <!-- Tabs -->
                                <div class="border-b flex justify-center">
                                    <button class="tab-button px-4 py-2 text-blue-500 border-b-2 border-blue-500" id="selectTabButton">Select Client</button>
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
                            <select id="relation" name="relation" class="border border-gray-200 dark:border-gray-600 p-2 rounded-md"></select>
                            <button onclick="updateClientGroup()" 
                            class="p-2 rounded-md bg-black text-white">Update</button>
            </div>


        </div>
    </div>
    <!-- ./edit agent details modal -->
</div>

<script>
    let clients = @json($clients);
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
                <h3 class="text-lg font-bold mb-4">Client Details</h3>
                <p><strong>ID:</strong> ${clientId}</p>
                <p><strong>Name:</strong> ${clientName}</p>
                <p><strong>Email:</strong> ${clientEmail}</p>
                <p><strong>Phone:</strong> ${clientPhone}</p>
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
        const fetchUrl = `/clients/${parentClientId}/subclients`; 


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
                const fetchUrl = `/clients/${childClientId}/parclients`; 


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
           const parentClientId =  document.getElementById("parentId").value;

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

                const fetchUrl = `/clients/${id}/getDetails`; 

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
                    const updateUrl = `/clients/${id}/update-group`; // Adjust the route if needed
                    const csrfToken = "{{ csrf_token() }}"; // Laravel CSRF token for security
                    const relation = document.querySelector("select[name='relation']").value; // Get selected relation
                    let selectedId = document.getElementById("selectedId").value;
                    const parentClientId =  document.getElementById("parentId").value;

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
        try {
            const response = await fetch('/clients/group/remove', {
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

  
</script>