<x-app-layout>
    <div>
        @if (session('success'))
        <div class="bg-green-500 text-white p-4 rounded mb-4">
            {{ session('success') }}
        </div>
        @elseif (session('error'))
        <div class="bg-red-500 text-white p-4 rounded mb-4">
            {{ session('error') }}
        </div>
        @endif




        <!-- Breadcrumbs -->
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span>Invoices list</span>
            </li>
        </ul>
        <!-- ./Breadcrumbs -->

        <!-- Controls Section -->
        <div
            class="flex flex-col md:flex-row items-center justify-between p-3 bg-white dark:bg-gray-800 shadow rounded-lg space-y-3 md:space-y-0">
            <!-- left side -->
            <div
                class="flex items-start md:items-center border border-gray-300 rounded-lg p-2 space-y-3 md:space-y-0 md:space-x-3">
                <div class="flex gap-2">
                    <a
                        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700">
                        <span class="text-black dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Total
                            Invoices</span>
                    </a>
                    <a
                        class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-info-light dark:bg-gray-700">
                        <span id="totalInvoices"></span>
                    </a>
                </div>
            </div>

            <!-- right side -->
            <div class="flex items-center gap-3 space-y-3 md:space-y-0 md:space-x-2">
                <!-- Search Box -->
                <div class="mt07 relative flex items-center h-12">
                    <input id="searchInput" type="text" placeholder="Search"
                        class="w-full h-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-300">
                    <svg class="w-5 h-5 text-gray-500 dark:text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35M9.5 17A7.5 7.5 0 109.5 2a7.5 7.5 0 000 15z" />
                    </svg>
                </div>

                <!-- Add Invoice Button -->
                <button type="button" onclick="openAddInvoiceModal()"
                    class="h-full flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none">
                    <svg class="w-5 h-5 mr-2 text-white dark:text-gray-300" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Invoice
                </button>
            </div>
        </div>
        <!-- ./Controls Section -->

        <!-- Add Invoice Modal -->
        <div id="addInvoiceModal" class="fixed z-10 inset-0 flex items-center justify-center backdrop-blur-sm hidden">
            <!-- Modal Background -->
            <div id="modalBackground" class="absolute inset-0 bg-opacity-50" onclick="closeAddInvoiceModal()"></div>

            <!-- Modal Content -->
            <div class="bg-white rounded-lg shadow-lg max-w-md w-full relative z-20">
                <div class="rounded-t-lg h-32 bg-cover bg-center"
                    style="background-image: url('{{ asset('images/CreateInvoicePic.svg') }}');">
                    <!-- Close Button (Top Right) -->
                    <button onclick="closeAddInvoiceModal()" type="button"
                        class="absolute top-4 right-4 text-black hover:text-gray-700 rounded-lg bg-[#004B99]">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <!-- Modal Title -->
                <div class="bg-gray-100 p-2 mb-4 text-center">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Create New
                        Invoice</h2>
                    <p class="text-sm text-gray-500 mb-4">Generate a new invoice and share it with clients.</p>
                </div>
                <div class="p-5">

                    <!-- Modal Form -->
                    <form method="POST" action="{{ route('invoice.store') }}">
                        @csrf

                        <!-- Client Field -->
                        <div x-data="searchableClientDropdown()" class="flex flex-col mb-4 relative">
                            <label for="clientId" class="block text-gray-700 text-sm font-bold mb-2">Client</label>
                            <div class="relative w-full">
                                <!-- Searchable Input Field -->
                                <input type="text" x-model="searchQuery" @input="filterClients"
                                    placeholder="Search for a client..."
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline">

                                <!-- Dropdown List -->
                                <div class="absolute left-0 mt-1 w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg z-10"
                                    x-show="filteredClients.length > 0 && searchQuery.length > 0">
                                    <ul>
                                        <template x-for="client in filteredClients" :key="client.id">
                                            <li @click="selectClient(client)"
                                                class="px-4 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                                <span x-text="client.name"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                            <input type="hidden" id="clientId" name="clientId" x-model="selectedClientId">
                        </div>

                        <!-- Task Field -->
                        <div x-data="searchableTaskDropdown()" class="flex flex-col mb-4 relative">
                            <label for="taskId" class="block text-gray-700 text-sm font-bold mb-2">Task</label>
                            <div class="relative w-full">
                                <!-- Searchable Input Field -->
                                <input type="text" x-model="searchTaskQuery" @input="filterTasks"
                                    placeholder="Search for a task..."
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline">

                                <!-- Dropdown List -->
                                <div class="absolute left-0 mt-1 w-full bg-white dark:bg-gray-800 rounded-lg shadow-lg z-10"
                                    x-show="filteredTasks.length > 0 && searchTaskQuery.length > 0">
                                    <ul>
                                        <template x-for="task in filteredTasks" :key="task.id">
                                            <li @click="selectTask(task)"
                                                class="px-4 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300">
                                                <span x-text="task.description"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                            <input type="hidden" id="taskId" name="taskId" x-model="selectedTaskId">
                        </div>
                        <!-- Amount Field -->
                        <div class="flex space-x-4 mb-4">
                            <!-- Amount Field -->
                            <div class="flex-1">
                                <label for="amount" class="block text-gray-700 text-sm font-bold mb-2">Amount</label>
                                <input id="amount" name="amount" type="number" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    placeholder="Invoice Amount" />
                            </div>

                            <!-- Currency Field -->
                            <div class="w-1/4">
                                <label for="currency"
                                    class="block text-gray-700 text-sm font-bold mb-2">Currency</label>
                                <select id="currency" name="currency" required
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 dark:text-gray-300 dark:bg-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="GBP">GBP</option>
                                    <!-- Add more currency options as needed -->
                                </select>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-center mt-4">
                            <button type="submit"
                                class="h-full px-8 py-2 bg-black text-white rounded-lg hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none">
                                Create Invoice
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
        <!-- ./Add Invoice Modal -->



        <!-- Table Section -->
        <div class="mt-5 overflow-x-auto bg-white shadow rounded-lg">
            <div class="max-h-96 overflow-y-auto custom-scrollbar">
                <table class="InvoiceTable CityMobileTable w-full">
                    <thead class="sticky top-0">
                        <tr>
                            <th class="px-4 py-2">Invoice Number</th>
                            <th class="px-4 py-2">Agent name</th>
                            <th class="px-4 py-2">Client name</th>
                            <th class="px-4 py-2">Amount</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-300">
                        @foreach ($invoices as $invoice)
                        <tr>
                            <td class="px-4 py-2">{{ $invoice->invoice_number }}</td>
                            <td class="px-4 py-2">{{ $invoice->agent->name }}</td>
                            <td class="px-4 py-2">{{ $invoice->client->name }}</td>
                            <td class="px-4 py-2">{{ $invoice->amount }}</td>
                            <td class="px-4 py-2">{{ $invoice->status }}</td>
                            <td class="px-4 py-2">
                                <a href="javascript:void(0);"
                                    onclick="openInvoiceModal('{{ $invoice->invoice_number }}')"
                                    class="text-sm font-medium text-blue-600 hover:underline">View</a>
                                <!-- View Invoice Modal -->
                                <div id="viewInvoiceModal"
                                    class="fixed z-10 inset-0 flex items-center justify-center backdrop-blur-sm hidden">
                                    <div class="relative">
                                        <!-- Modal Content -->
                                        <div id="invoiceContent" class="p-6">
                                            <!-- Invoice content will be loaded here dynamically -->
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ route('invoice.edit', ['invoiceNumber' => $invoice->invoice_number]) }}"
                                class="text-sm font-medium text-blue-600 hover:underline">
                                Edit
                                </a>
                            </td>





                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <!-- ./Table Section -->

        <div class="mt-4">
            {{ $invoices->links() }}
            <!-- Pagination links -->
        </div>
    </div>

</x-app-layout>

<script>
    function openAddInvoiceModal() {
        document.getElementById('addInvoiceModal').classList.remove('hidden');
    }

    function closeAddInvoiceModal() {
        document.getElementById('addInvoiceModal').classList.add('hidden');
    }

    document.addEventListener("DOMContentLoaded", function() {
        // Update total invoices count
        const totalInvoices = @json($totalInvoices);
        document.getElementById("totalInvoices").innerText = totalInvoices;

        // Search functionality
        const searchInput = document.getElementById("searchInput");
        const tableRows = document.querySelectorAll(".InvoiceTable tbody tr");

        searchInput.addEventListener("input", function() {
            const query = searchInput.value.toLowerCase();

            tableRows.forEach(row => {
                const cells = row.querySelectorAll("td");
                let rowContainsQuery = false;

                cells.forEach(cell => {
                    if (cell.innerText.toLowerCase().includes(query)) {
                        rowContainsQuery = true;
                    }
                });

                if (rowContainsQuery) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });
    });

    function searchableClientDropdown() {
        return {
            searchQuery: '',
            selectedClientId: null,
            clients: @json($clients),
            filteredClients: [],

            filterClients() {
                if (this.searchQuery.trim() === '') {
                    this.filteredClients = [];
                    return;
                }

                const query = this.searchQuery.toLowerCase();
                this.filteredClients = this.clients.filter(client =>
                    client.name.toLowerCase().includes(query)
                );
            },

            selectClient(client) {
                this.selectedClientId = client.id;
                this.searchQuery = client.name;
                this.filteredClients = [];
            }
        };
    }

    function searchableTaskDropdown() {
        return {
            searchTaskQuery: '',
            selectedTaskId: null,
            tasks: @json($tasks),
            filteredTasks: [],

            filterTasks() {
                if (this.searchTaskQuery.trim() === '') {
                    this.filteredTasks = [];
                    return;
                }

                const query = this.searchTaskQuery.toLowerCase();
                this.filteredTasks = this.tasks.filter(task =>
                    task.description.toLowerCase().includes(query)
                );
            },

            selectTask(task) {
                this.selectedTaskId = task.id;
                this.searchTaskQuery = task.description;
                this.filteredTasks = [];
            }
        };
    }

    function openInvoiceModal(invoiceNumber) {
        const modal = document.getElementById('viewInvoiceModal');
        const contentDiv = document.getElementById('invoiceContent');

        // Clear previous content
        contentDiv.innerHTML = '';

        // Open the modal
        modal.classList.remove('hidden');
        url = "{{ route('invoice.show', ['invoiceNumber' => ':invoiceNumber']) }}"
            .replace(':invoiceNumber', invoiceNumber);

        // Fetch the invoice details
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                contentDiv.innerHTML = data;
            })
            .catch(error => {
                console.error('Error fetching invoice details:', error);
                contentDiv.innerHTML =
                    '<p class="text-center text-red-500">Failed to load invoice details.</p>';
            });
    }

    function closeViewInvoiceModal() {
        const modal = document.getElementById('viewInvoiceModal');
        modal.classList.add('hidden');
    }

    function copyToClipboard(url) {

        navigator.clipboard.writeText(url).then(function() {
            showToast('Invoice URL copied to clipboard');
        }, function() {
            showToast('Failed to copy invoice URL');
        });
    }

    // Close the modal when clicking outside of the content area
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('viewInvoiceModal');
        const modalContent = modal.querySelector('.bg-white');
        if (event.target === modal && !modalContent.contains(event.target)) {
            closeViewInvoiceModal();
        }
    });
</script>