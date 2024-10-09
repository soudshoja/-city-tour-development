<x-app-layout>
    <div class="container mx-auto p-5">
        <!-- Company Info Section -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-2xl font-semibold mb-4">{{ $company->name }}</h2>
            <p><strong>Email:</strong> {{ $company->email }}</p>
            <p><strong>Phone:</strong> {{ $company->phone }}</p>
            <p><strong>Address:</strong> {{ $company->address }}</p>
            <p><strong>Nationality:</strong> {{ $company->nationality }}</p>
        </div>

        <!-- Agents Table Section -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h3 class="text-xl font-semibold mb-4 flex justify-between items-center cursor-pointer" onclick="toggleAgentsSection()">
                Agents Overview
                <div class="flex space-x-2">
                <x-primary-button  id="printPage" onclick="addAgent()"><i class="fas fa-add" title="Add Agent"></i></x-primary-button>
                <span id="agentsToggleIcon" class="text-gray-500">▼</span>
                </div>
            </h3>
            <div id="agentsContent" class="hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto border-collapse">
                        <thead>
                            <tr>
                                <th class="border px-4 py-2">Agent Name</th>
                                <th class="border px-4 py-2">Email</th>
                                <th class="border px-4 py-2">Actions</th>
                            </tr>
                        </thead>
                    <tbody>
                        @foreach ($company->agents as $agent)
                            <tr>
                                <td class="border px-4 py-2">{{ $agent->name }}</td>
                                <td class="border px-4 py-2">{{ $agent->email }}</td>
                                <td class="border px-4 py-2 flex space-x-2">
                                    <button onclick="openAgentDetails({{ $agent->id }}, 'tasks')" class="text-indigo-500">
                                        <i class="fas fa-tasks" title="View Tasks"></i>
                                    </button>
                                    <button onclick="openAgentDetails({{ $agent->id }}, 'clients')" class="text-indigo-500">
                                        <i class="fas fa-users" title="View Clients"></i>
                                    </button>
                                    <button onclick="openAgentDetails({{ $agent->id }}, 'invoices')" class="text-indigo-500">
                                        <i class="fas fa-file-invoice" title="View Invoices"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr id="details-{{ $agent->id }}" class="hidden">
                                <td colspan="3" class="border px-4 py-2">
                                    <div id="detailsContent-{{ $agent->id }}" class="mt-4 bg-white border border-gray-200 rounded-lg p-4">
                                        <!-- Details will be populated here based on JavaScript logic -->
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    
        <!-- Add New Section -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h3 class="text-xl font-semibold mb-4 flex justify-between items-center cursor-pointer" onclick="toggleAddSection()">
                Add New Agency
                <span id="addToggleIcon" class="text-gray-500">▼</span>
            </h3>
            <div id="addContent" class="hidden">
            <div class="w-full lg:w-3/5 p-4 md:p-8 flex items-center justify-center">
                <div class="w-full">
                    <h2 class="text-2xl md:text-3xl font-semibold text-gray-700 dark:text-gray-200 text-center mb-6">
                        Register New B2B Company</h2>

                    <form method="POST" action="{{ route('companies.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @csrf

                        <!-- Name Field -->
                        <div class="mb-4">
                            <label for="name"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Name</label>
                            <input id="name" name="name" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Name" />
                        </div>

                        <!-- Email Address -->
                        <div class="mb-4">
                            <label for="email" :value="__('Email')"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Email</label>
                            <input id="email" type="email" name="email" :value="old('email')" required
                                autocomplete="email"
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Email" />
                        </div>

                        <!-- Code Field -->
                        <div class="mb-4">
                            <label for="code"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Code</label>
                            <input id="code" name="code" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Code" />
                        </div>

                        <!-- Nationality Field -->
                        <div class="mb-4">
                            <label for="nationality"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Nationality</label>
                            <select id="nationality" name="nationality" required
                                class="block appearance-none w-full bg-white dark:bg-gray-700 border border-gray-400 hover:border-gray-500 px-4 py-2 pr-8 rounded leading-tight focus:outline-none focus:shadow-outline">
                                <option value="Malaysia">Malaysia</option>
                                <option value="Kuwait">Kuwait</option>
                                <option value="Indonesia">Indonesia</option>
                            </select>
                        </div>

                        <!-- Code Field -->
                        <div class="mb-4">
                            <label for="phone"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Contact</label>
                            <input id="phone" name="phone" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Contact" />
                        </div>

                          <!-- Code Field -->
                         <div class="mb-4">
                            <label for="address"
                                class="block text-gray-700 dark:text-gray-300 text-sm font-bold mb-2">Address</label>
                            <input id="address" name="address" type="text" required
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                placeholder="Company Address" />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-center">
                            <button type="submit"
                                class="p-2 btn btn-gradient !mt-6 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                                Register & Invite Company
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            </div>
      </div>
    <div class="mt-4">
            <button class="bg-blue-500 text-white px-4 py-2 rounded" onclick="addAgent()">Add Agent</button>
            <button class="bg-green-500 text-white px-4 py-2 rounded" onclick="inviteAgency()">Invite Agency</button>
            <button class="bg-gray-300 text-black px-4 py-2 rounded" onclick="configureSettings()">Configurations</button>
        </div>
  </div>

  <div id="addAgentModal" class="fixed z-10 inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 backdrop-blur-sm hidden">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 relative">
        
        <!-- Close Button (Top Right) -->
        <button onclick="closeAddAgentModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Modal Title -->
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 text-center">Register New Agent</h2>

        <!-- Modal Form -->
        <form method="POST" action="{{ route('agents.store') }}" class="space-y-4">
            @csrf

            <!-- Name Field -->
            <div class="space-y-1">
                <label for="name" class="block text-sm font-semibold text-gray-700">Name</label>
                <input id="name" name="name" type="text" required class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Agent Name" />
            </div>

            <!-- Email Field -->
            <div class="space-y-1">
                <label for="email" class="block text-sm font-semibold text-gray-700">Email</label>
                <input id="email" name="email" type="email" required class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Agent Email" />
            </div>

            <!-- Phone Number Field -->
            <div class="space-y-1">
                <label for="phone_number" class="block text-sm font-semibold text-gray-700">Phone Number</label>
                <input id="phone_number" name="phone_number" type="text" required class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" placeholder="Phone Number" />
            </div>

            <!-- Type Field -->
            <div class="space-y-1">
                <label for="type" class="block text-sm font-semibold text-gray-700">Type</label>
                <select id="type" name="type" required class="w-full p-2 border rounded-md text-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    <option value="staff">Staff</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <input type="hidden" name="company_id" value="{{ $company->id }}" />
            <!-- Submit Button -->
            <div class="flex space-x-2">
                <button type="submit"  class="p-2 btn btn-gradient !mt-6 w-full border-0 uppercase shadow-[0_10px_20px_-10px_rgba(67,97,238,0.44)]">
                    Register Agent
                </button>
            </div>
        </form>
    </div>
</div>


  <div id="taskModal" class="fixed z-10 inset-0 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="bg-white rounded-lg shadow-lg max-w-lg w-full">
            <div class="px-4 py-2 border-b">
                <h3 class="text-xl font-semibold">Add New Task</h3>
            </div>
            <div class="p-4">
                <label for="taskDescription" class="block text-sm font-bold mb-2">Task Description</label>
                <input id="taskDescription" type="text" class="border w-full px-3 py-2 rounded mb-4" placeholder="Task description">

                <label for="clientName" class="block text-sm font-bold mb-2">Client</label>
                <input id="clientName" type="text" class="border w-full px-3 py-2 rounded mb-4" placeholder="Client name">

                <div class="text-right">
                    <button class="bg-blue-500 text-white px-4 py-2 rounded mr-2" onclick="saveTask()">Save Task</button>
                    <button class="bg-gray-300 text-black px-4 py-2 rounded" onclick="closeModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

    <script>
    function openAgentDetails(agentId, type) {
        const detailsContainer = document.getElementById(`detailsContent-${agentId}`);
        const detailsRow = document.getElementById(`details-${agentId}`);
        
        // Hide all details first
        const allDetails = document.querySelectorAll('[id^="detailsContent-"]');
        allDetails.forEach(detail => detail.innerHTML = ''); // Clear previous content

    // Check if the detailsRow is already visible
    if (!detailsRow.classList.contains('hidden')) {
        // If visible, hide it and clear content
        detailsRow.classList.add('hidden');
        return; // Exit the function early
    }

    // If not visible, show it
    detailsRow.classList.remove('hidden');
        
        let agentData = @json($company->agents->keyBy('id'));
        // Fetching data based on agentId
        let agentName = agentData[agentId]?.name;
        let agentTasks = agentData[agentId]?.tasks || [];
        let agentClients = agentData[agentId]?.tasks.map(task => task.client).filter(client => client) || [];
        let agentInvoices = agentData[agentId]?.invoices || [];

        if (type === 'tasks') {
                // Populate tasks details in a table
                let tasksContent = `    <h3 class="text-lg font-semibold mb-2">Tasks for ${agentName}</h3>
                                        <div class="flex justify-between mb-4">
                                            <div></div> <!-- Placeholder for left side (if needed) -->
                                            <div>
                                                <button class="bg-green-500 text-white px-4 py-2 rounded mr-2" onclick="addNewTask()">Add Task</button>
                                                <button class="bg-green-500 text-white px-4 py-2 rounded mr-2" onclick="uploadTask()">Upload Task</button>
                                                <button class="bg-blue-500 text-white px-4 py-2 rounded" onclick="exportTasksToCSV()">Export CSV</button>
                                                <button class="bg-gray-500  text-white px-4 py-2 rounded" onclick="closeDetails(${agentId})" aria-label="Close">
                                                <i class="fas fa-times"></i> Close
                                               </button>
                                            </div>
                                        </div>
                                    <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left">Task Description</th>
                                            <th class="px-4 py-2 text-left">Client</th>
                                            <th class="border px-4 py-2">Status</th>
                                            <th class="border px-4 py-2">Task Date</th>
                                            <th class="border px-4 py-2">Delay (Days)</th>
                                           <th class="px-4 py-2 text-left">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                if (agentTasks.length) {
                    agentTasks.forEach(task => {
                            // Parse task created_at date to a Date object
                            const createdAtDate = new Date(task.created_at);

                            const formatDate = (date) => {
                                const year = date.getFullYear();
                                const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed, so +1
                                const day = String(date.getDate()).padStart(2, '0');
                                const hours = String(date.getHours()).padStart(2, '0');
                                const minutes = String(date.getMinutes()).padStart(2, '0');

                                return `${year}-${month}-${day} ${hours}:${minutes}`;
                            };

                            // Format the date
                            const formattedDate = formatDate(createdAtDate);

                            const currentDate = new Date();

                            // Calculate delay in days
                            const delayInMilliseconds = currentDate - createdAtDate;  // Difference in milliseconds
                            const delayInDays = Math.round(delayInMilliseconds / (1000 * 60 * 60 * 24));  // Convert to days

                            // Apply conditional class if the delay is more than 3 days
                            const delayClass = delayInDays > 3 ? 'text-red-600 font-semibold' : '';
                        tasksContent += `<tr>
                                            <td class="border px-4 py-2">${task.description}</td>
                                            <td class="border px-4 py-2">${task.client ? task.client.name : 'N/A'}</td>
                                            <td class="border px-4 py-2">${task.status}</td>
                                            <td class="border px-4 py-2">${formattedDate}</td>
                                            <td class="border px-4 py-2 ${delayClass}">${delayInDays} days</td>
                                            <td class="border px-4 py-2">
                                                <button class="text-indigo-500" onclick="performTaskAction(${task.id})">Remind</button>
                                            </td>
                                        </tr>`;
                    });
                } else {
                    tasksContent += `<tr><td colspan="3" class="border px-4 py-2 text-center">No tasks found for this agent.</td></tr>`;
                }
                tasksContent += `</tbody></table>`;
                detailsContainer.innerHTML = tasksContent;
            } else if (type === 'clients') {
                // Populate clients details in a table
                let clientsContent = `<h3 class="text-lg font-semibold mb-2">Clients for ${agentName}</h3>
                                        <div class="flex justify-between mb-4">
                                            <div></div> <!-- Placeholder for left side (if needed) -->
                                            <div>
                                                <button class="bg-green-500 text-white px-4 py-2 rounded mr-2" onclick="addNewTask()">Add Client</button>
                                                <button class="bg-green-500 text-white px-4 py-2 rounded mr-2" onclick="uploadTask()">Upload Client</button>
                                                <button class="bg-blue-500 text-white px-4 py-2 rounded" onclick="exportTasksToCSV()">Export CSV</button>
                                                <button class="bg-gray-500  text-white px-4 py-2 rounded" onclick="closeDetails(${agentId})" aria-label="Close">
                                                <i class="fas fa-times"></i> Close
                                               </button>
                                            </div>
                                        </div>
                                    <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left">Client Name</th>
                                            <th class="px-4 py-2 text-left">Email</th>
                                            <th class="px-4 py-2 text-left">Phone</th>
                                            <th class="px-4 py-2 text-left">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                if (agentClients.length) {
                    agentClients.forEach(client => {
                        clientsContent += `<tr>
                                            <td class="border px-4 py-2">${client.name}</td>
                                            <td class="border px-4 py-2">${client.email}</td>
                                            <td class="border px-4 py-2">${client.phone}</td>
                                            <td class="border px-4 py-2">
                                                <button class="text-indigo-500" onclick="performClientAction(${client.id})">View</button>
                                            </td>
                                        </tr>`;
                    });
                } else {
                    clientsContent += `<tr><td colspan="3" class="border px-4 py-2 text-center">No clients found for this agent.</td></tr>`;
                }
                clientsContent += `</tbody></table>`;
                detailsContainer.innerHTML = clientsContent;
            } else if (type === 'invoices') {
                // Populate invoices details in a table
                let invoicesContent = `<h3 class="text-lg font-semibold mb-2">Invoices for ${agentName}</h3>
                                      <div class="flex justify-between mb-4">
                                            <div></div> <!-- Placeholder for left side (if needed) -->
                                            <div>
                                                <button class="bg-blue-500 text-white px-4 py-2 rounded" onclick="exportTasksToCSV()">Export CSV</button>
                                                <button class="bg-gray-500  text-white px-4 py-2 rounded" onclick="closeDetails(${agentId})" aria-label="Close">
                                                <i class="fas fa-times"></i> Close
                                               </button>
                                            </div>
                                        </div>
                                    <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left">Invoice Number</th>
                                            <th class="px-4 py-2 text-left">Status</th>
                                            <th class="px-4 py-2 text-left">Created At</th>
                                            <th class="px-4 py-2 text-left">Currency</th>
                                            <th class="px-4 py-2 text-left">Amount</th>
                                            <th class="px-4 py-2 text-left">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                if (agentInvoices.length) {
                    agentInvoices.forEach(invoice => {

                        const createdAtDate = new Date(invoice.created_at);

                        const formatDate = (date) => {
                            const year = date.getFullYear();
                            const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed, so +1
                            const day = String(date.getDate()).padStart(2, '0');
                            const hours = String(date.getHours()).padStart(2, '0');
                            const minutes = String(date.getMinutes()).padStart(2, '0');

                            return `${year}-${month}-${day} ${hours}:${minutes}`;
                        };

                        // Format the date
                        const formattedDate = formatDate(createdAtDate);

                        invoicesContent += `<tr>
                                            <td class="border px-4 py-2">${invoice.invoice_number}</td>
                                            <td class="border px-4 py-2">${invoice.status}</td>
                                            <td class="border px-4 py-2">${formattedDate}</td>
                                            <td class="border px-4 py-2">${invoice.currency}</td>
                                            <td class="border px-4 py-2">${invoice.amount}</td>
                                            <td class="border px-4 py-2">
                                                <button class="text-indigo-500" onclick="performInvoiceAction(${invoice.id})">View</button>
                                            </td>
                                        </tr>`;
                    });
                } else {
                    invoicesContent += `<tr><td colspan="3" class="border px-4 py-2 text-center">No invoices found for this agent.</td></tr>`;
                }
                invoicesContent += `</tbody></table>`;
                detailsContainer.innerHTML = invoicesContent;
            }

    }

    function toggleAgentsSection() {
    const agentsContent = document.getElementById('agentsContent');
    const toggleIcon = document.getElementById('agentsToggleIcon');

            // Toggle visibility of the agents section
            if (agentsContent.classList.contains('hidden')) {
                agentsContent.classList.remove('hidden');
                toggleIcon.innerText = '▲'; // Change icon to up arrow when expanded
            } else {
                agentsContent.classList.add('hidden');
                toggleIcon.innerText = '▼'; // Change icon to down arrow when collapsed
            }
        }

        function toggleAddSection() {
         const addContent = document.getElementById('addContent');
         const toggleIcon = document.getElementById('addToggleIcon');

            // Toggle visibility of the agents section
            if (addContent.classList.contains('hidden')) {
                addContent.classList.remove('hidden');
                toggleIcon.innerText = '▲'; // Change icon to up arrow when expanded
            } else {
                addContent.classList.add('hidden');
                toggleIcon.innerText = '▼'; // Change icon to down arrow when collapsed
            }
        }

        function toggleTaskSection() {
                const taskSection = document.getElementById('task-section'); // Assuming you have an id for the task section
                if (taskSection.style.display === "none") {
                    taskSection.style.display = "block";
                } else {
                    taskSection.style.display = "none";
                }
            }

        function addNewTask() {
        // Show the modal when the "Add Task" button is clicked
        document.getElementById('taskModal').classList.remove('hidden');
    }

    function closeModal() {
        // Hide the modal when "Cancel" is clicked
        document.getElementById('taskModal').classList.add('hidden');
    }

    function saveTask() {
        // Logic to save the new task goes here (e.g., API call, form submission)
        const taskDescription = document.getElementById('taskDescription').value;
        const clientName = document.getElementById('clientName').value;

        if (taskDescription && clientName) {
            // Example: You can send an AJAX request to save the task
            console.log('Saving task:', { taskDescription, clientName });

            // Close the modal after saving
            closeModal();
        } else {
            alert('Please fill in all fields');
        }
    }

    function closeDetails(agentId) {
    const detailsRow = document.getElementById(`details-${agentId}`);
    if (!detailsRow.classList.contains('hidden')) {
        detailsRow.classList.add('hidden');
    }
   }

// Sample functions for the buttons (implement these based on your logic)
function addAgent() {
    // Logic to add an agent
    document.getElementById('addAgentModal').classList.remove('hidden');
}

function  closeAddAgentModal() {
        // Hide the modal when "Cancel" is clicked
        document.getElementById('addAgentModal').classList.add('hidden');
    }


function inviteAgency() {
    // Logic to invite an agency
    console.log('Invite Agency button clicked');
}

function configureSettings() {
    // Logic for configurations
    console.log('Configurations button clicked');
}
    </script>
</x-app-layout>
