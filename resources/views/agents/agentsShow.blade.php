<x-app-layout>
    <div class="container mx-auto p-6">
        <!-- Agent Info Section -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
    <h3 class="text-2xl font-bold text-gray-700 mb-4">Agent Detail</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <p><strong>Name:</strong> {{ $agent->name }}</p>
            <p><strong>Email:</strong> {{ $agent->email }}</p>
        </div>
        <div>
            <p><strong>Phone:</strong> {{ $agent->phone_number }}</p>
            <p><strong>Company:</strong> {{ $agent->company->name }}</p>
        </div>
        <div>
            <p><strong>Type:</strong> {{ $agent->type }}</p>
        </div>
    </div>
    <div class="mt-6 text-right">
        <a href="{{ route('agents.edit', $agent->id) }}" class="bg-blue-500 text-white py-2 px-4 rounded-lg shadow hover:bg-blue-600 transition duration-200">Update Details</a>
    </div>
   </div>


        <!-- Pending Tasks Section -->
        <div class="bg-white shadow rounded-lg p-2 mb-2">
            <h4 class="text-xl font-semibold mb-1 flex justify-between items-center cursor-pointer" onclick="toggleTasksSection()">
                Task Overview
                <span id="tasksToggleIcon" class="text-gray-500">▼</span>
            </h4>
            <div id="tasksContent" class="hidden">
            @if($tasks->isEmpty())
             <p class="text-gray-600">No Tasks for this agent.</p>
                    @else
                        <table class="min-w-full bg-white border border-gray-300 mt-4">
                            <thead>
                                <tr>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Task Name</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Task Date</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Status</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Client</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tasks as $task)
                                    <tr>
                                        <td class="py-4 px-6 border-b">{{ $task->description }}</td>
                                        <td class="py-4 px-6 border-b">{{ $task->created_at->format('Y-m-d') }}</td>
                                        <td class="py-4 px-6 border-b">{{ $task->status }}</td>
                                        <td class="py-4 px-6 border-b">{{ $task->client->name }}</td>
                                        <td class="py-4 px-6 border-b">
                                            <a href="#" class="text-indigo-500">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-4">
                            {{ $tasks->appends(['section' => 'tasks'])->links() }}
                        </div>
                    @endif

                    <div class="mt-2">
                        <a href="/tasks/{{ $agent->id }}" class="text-blue-500 text-xs underline hover:text-blue-700">
                            See Full Tasks
                        </a>
                    </div>
            </div>

    </div>
    <div class="bg-white shadow rounded-lg p-2 mb-2">
            <h4 class="text-xl font-semibold mb-1 flex justify-between items-center cursor-pointer" onclick="toggleInvoicesSection()">
                Invoices Overview
                <span id="invoicesToggleIcon" class="text-gray-500">▼</span>
            </h4>
            <div id="invoicesContent" class="hidden">
            @if($invoices->isEmpty())
            <p class="text-gray-600">No invoices for this agent.</p>
                @else
                    <table class="min-w-full bg-white border border-gray-300 mt-4">
                        <thead>
                            <tr>
                                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">invoice Number</th>
                                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">invoice Date</th>
                                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Status</th>
                                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Client</th>
                                <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td class="py-4 px-6 border-b">{{ $invoice->invoice_number }}</td>
                                    <td class="py-4 px-6 border-b">{{ $invoice->created_at->format('Y-m-d') }}</td>
                                    <td class="py-4 px-6 border-b">{{ $invoice->status }}</td>
                                    <td class="py-4 px-6 border-b">{{ $invoice->client->name }}</td>
                                    <td class="py-4 px-6 border-b">
                                        <a href="#" class="text-indigo-500">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $invoices->appends(['section' => 'invoices'])->links() }}
                    </div>
                @endif
                <div class="mt-2">
                    <a href="/invoice/{{ $agent->id }}" class="text-blue-500 text-xs underline hover:text-blue-700">
                        See Full Invoices
                    </a>
                </div>
            </div>
    </div>

    <div class="bg-white shadow rounded-lg p-2 mb-2">
            <h4 class="text-xl font-semibold mb-1 flex justify-between items-center cursor-pointer" onclick="toggleClientsSection()">
                Clients Overview
                <span id="clientsToggleIcon" class="text-gray-500">▼</span>
            </h4>
            <div id="clientsContent" class="hidden">
            @if($clients->isEmpty())
                <p class="text-gray-600">No clients for this agent.</p>
                    @else
                        <table class="min-w-full bg-white border border-gray-300 mt-4">
                            <thead>
                                <tr>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">client Name</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Email</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Phone</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Address</th>
                                    <th class="py-3 px-6 text-left font-semibold text-gray-600 border-b">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clients as $client)
                                    <tr>
                                        <td class="py-4 px-6 border-b">{{ $client->name }}</td>
                                        <td class="py-4 px-6 border-b">{{ $client->email }}</td>
                                        <td class="py-4 px-6 border-b">{{ $client->phone }}</td>
                                        <td class="py-4 px-6 border-b">{{ $client->address }}</td>
                                        <td class="py-4 px-6 border-b">
                                            <a href="#" class="text-indigo-500">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-4">
                            {{ $clients->appends(['section' => 'clients'])->links() }}
                        </div>
                    @endif
                <div class="mt-2">
                    <a href="/clients/list/{{ $agent->id }}" class="text-blue-500 text-xs underline hover:text-blue-700">
                        See Full Clients
                    </a>
                </div>
           </div>
    </div>

    <script>
            function toggleTasksSection() {
             const tasksContent = document.getElementById('tasksContent');
             const toggleIcon = document.getElementById('tasksToggleIcon');

            // Toggle visibility of the agents section
            if (tasksContent.classList.contains('hidden')) {
                tasksContent.classList.remove('hidden');
                toggleIcon.innerText = '▲'; // Change icon to up arrow when expanded
            } else {
                tasksContent.classList.add('hidden');
                toggleIcon.innerText = '▼'; // Change icon to down arrow when collapsed
            }
        }

        function toggleInvoicesSection() {
             const invoicesContent = document.getElementById('invoicesContent');
             const toggleIcon = document.getElementById('ivoicesToggleIcon');

            // Toggle visibility of the agents section
            if (invoicesContent.classList.contains('hidden')) {
                invoicesContent.classList.remove('hidden');
                toggleIcon.innerText = '▲'; // Change icon to up arrow when expanded
            } else {
                invoicesContent.classList.add('hidden');
                toggleIcon.innerText = '▼'; // Change icon to down arrow when collapsed
            }
        }      
        
        function toggleClientsSection() {
             const clientsContent = document.getElementById('clientsContent');
             const toggleIcon = document.getElementById('clientsToggleIcon');

            // Toggle visibility of the agents section
            if (clientsContent.classList.contains('hidden')) {
                clientsContent.classList.remove('hidden');
                toggleIcon.innerText = '▲'; // Change icon to up arrow when expanded
            } else {
                clientsContent.classList.add('hidden');
                toggleIcon.innerText = '▼'; // Change icon to down arrow when collapsed
            }
        }

        // AJAX Pagination for Tasks
        document.addEventListener('click', function(e) {
                // Check if the clicked element is a pagination link
                if (e.target.closest('.pagination a')) {
                    e.preventDefault();

                    // Get the URL from the pagination link
                    let url = e.target.closest('.pagination a').getAttribute('href');
                    
                    // Find the closest section (tasks, invoices, or clients) by checking a data attribute
                    let section = e.target.closest('.pagination').getAttribute('data-section');

                    // Fetch paginated data for the correct section
                    fetchPaginatedData(url, section);
                }
            });

            function fetchPaginatedData(url, section) {

                url = url.replace('%7D', '');
                $.ajax({
                    url: url,
                    data: { section: section },
                    success: function(response) {
                        // Update the relevant section content based on the section name
                        if (section === 'tasks') {
                            document.getElementById('tasksContent').innerHTML = response;
                        } else if (section === 'invoices') {
                            document.getElementById('invoicesContent').innerHTML = response;
                        } else if (section === 'clients') {
                            document.getElementById('clientsContent').innerHTML = response;
                        }
                    },
                    error: function() {
                        console.error('Failed to load the paginated content.');
                    }
                });
            }

    </script>
</x-app-layout>
