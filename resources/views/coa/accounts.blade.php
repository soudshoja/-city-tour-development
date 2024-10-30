<x-app-layout>


    <script>
    document.addEventListener('DOMContentLoaded', function() {
        calculateTotals();
    });

    function filterAccounts(query) {
        // Get all list items (the account categories in the sidebar)
        const items = document.querySelectorAll('#coa-list > li');

        // Convert the query to lowercase to make the search case-insensitive
        const lowerCaseQuery = query.toLowerCase();

        // Loop through each list item (account category)
        items.forEach(item => {
            // Get the category name (the span inside the button)
            const accountName = item.querySelector('span').textContent.toLowerCase();

            // Check if the account name includes the query string
            if (accountName.includes(lowerCaseQuery)) {
                item.style.display = 'block'; // Show the item if it matches
            } else {
                item.style.display = 'none'; // Hide the item if it doesn't match
            }
        });
    }

    // Function for received  and spented payments

    function calculateTotals() {
        // Calculate total received from the payments array
        const totalReceived = payments.reduce((sum, payment) => sum + payment.amount, 0);

        // Calculate total spent from the accountsPayable array
        const totalSpent = accountsPayable.reduce((sum, payable) => sum + payable.amount, 0);

        // Calculate the available balance
        const availableBalance = totalReceived - totalSpent;

        // Display the totals in the respective elements by their IDs
        document.getElementById('total-received').textContent = `$${totalReceived.toFixed(2)}`;
        document.getElementById('total-spent').textContent = `$${totalSpent.toFixed(2)}`;

        // Display the available balance in the wallet balance section
        document.getElementById('wallet-balance').textContent = `$${availableBalance.toFixed(2)}`;
    }
    </script>

    <!-- Breadcrumbs -->
    <x-breadcrumbs :breadcrumbs="[
    ['label' => 'Dashboard', 'url' => route('dashboard')],
    ['label' => 'Chart of Account']
]" />

    <!-- ./Breadcrumbs -->


    @if(isset($error))
    <div class="alert alert-danger">{{ $error }}</div>
    @else
    <!-- Display your content as usual -->
    @endif



    <div class="font-sans leading-normal tracking-normal flex flex-shrink-0">

        <div class="container mx-auto py-6 flex">
            <div class="w-1/2">

                <div class="flex justify-between">
                    <h1 class="text-2xl font-bold mb-4">Chart of Accounts</h1>
                    <!-- Toggle Form Button -->
                    <button onclick="toggleForm()"
                        class="flex items-center px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 dark:bg-gray-700 dark:hover:bg-gray-600 focus:outline-none"><svg
                            class="w-5 h-5 mr-2 text-white dark:text-gray-300" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Add New</button>
                </div>
                <ul class="list-none">
                    @foreach($accounts as $account)
                    @include('coa.partials.account', ['account' => $account])
                    @endforeach
                </ul>
            </div>

            <div class="w-1/2 pl-6">
                <!-- Chart Container -->
                <div id="chartContainer">
                    @include('coa.partials.chart')
                </div>

                <!-- Form Container (initially hidden) -->
                <div id="formContainer" class="hidden bg-white shadow-md rounded-lg p-4">
                    <!-- Selected Account Display -->
                    <div id="selectedAccount" class="mb-4 p-2 bg-gray-100 border border-gray-300 rounded-md hidden">
                        <span class="font-medium text-gray-700">Add Account to </span>
                        <span id="accountNameDisplay" class="text-gray-900">None</span>
                    </div>

                    <form id="itemForm" action="{{ route('coa.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <!-- Account Info Section -->
                        <fieldset class="mb-3 border rounded-lg bg-gray-50 p-3">
                            <legend class="text-base font-semibold mb-2 text-gray-700">Account Info</legend>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <input id="parent_id" name="parent_id" class="form-checkbox CheckBoxColor hidden">
                                <input id="acc_name" name="acc_name" class="form-checkbox CheckBoxColor hidden">
                                <div>
                                    <label for="account_name" class="text-xs font-medium text-gray-700">Account
                                        Name</label>
                                    <input type="text" id="account_name" name="account_name"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-opacity-50 text-sm"
                                        required>
                                </div>
                                <div>
                                    <label for="balance" class="text-xs font-medium text-gray-700">Balance</label>
                                    <input type="number" id="balance" name="balance"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-opacity-50 text-sm">
                                </div>
                            </div>
                            <div class="mt-2">
                                <label for="account_description" class="text-xs font-medium text-gray-700">Account
                                    Description</label>
                                <textarea id="account_description" name="account_description"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-opacity-50 text-sm"
                                    rows="2" required></textarea>
                            </div>
                            <div class="mt-2">
                                <label for="client_or_supplier" class="text-xs font-medium text-gray-700">Select Client
                                    or Supplier</label>
                            </div>
                        </fieldset>

                        <!-- Transaction Details Section -->
                        <fieldset class="mb-3 border rounded-lg bg-gray-50 p-3">
                            <legend class="text-base font-semibold mb-2 text-gray-700">Transaction Details</legend>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label for="currency" class="text-xs font-medium text-gray-700">Currency</label>
                                    <select id="currency" name="currency"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-opacity-50 text-sm">
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                        <option value="GBP">GBP</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="transaction_type" class="text-xs font-medium text-gray-700">Type</label>
                                    <select id="transaction_type" name="transaction_type"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-opacity-50 text-sm">
                                        <option value="debit">Debit</option>
                                        <option value="credit">Credit</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label for="group" class="text-xs font-medium text-gray-700">Group</label>
                                    <select id="group" name="group"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-opacity-50 text-sm">
                                        <option value="">-- Select Group --</option>
                                        <option value="group1">Group 1</option>
                                        <option value="group2">Group 2</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="branch" class="text-xs font-medium text-gray-700">Branch</label>
                                    <select id="branch" name="branch"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-opacity-50 text-sm">
                                        <option value="">-- Select Branch --</option>
                                        <option value="branch1">Branch 1</option>
                                        <option value="branch2">Branch 2</option>
                                    </select>
                                </div>
                            </div>
                        </fieldset>

                        <!-- Other Details Section -->
                        <fieldset class="mb-3 border rounded-lg bg-gray-50 p-3">
                            <legend class="text-base font-semibold mb-2 text-gray-700">Other Details</legend>
                            <div class="mb-2">
                                <label for="documents" class="text-xs font-medium text-gray-700">Upload
                                    Documents</label>
                                <input type="file" id="documents" name="documents[]"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring focus:ring-opacity-50 text-sm"
                                    multiple>
                            </div>
                        </fieldset>

                        <button type="submit"
                            class="px-4 py-2 bg-gray-200 text-white rounded hover:bg-gray-700 text-sm">Add
                            Account</button>
                    </form>
                </div>


            </div>

            <script>
            function toggleForm() {
                const formContainer = document.getElementById('formContainer');
                const chartContainer = document.getElementById('chartContainer');

                // Toggle visibility
                if (formContainer.classList.contains('hidden')) {
                    formContainer.classList.remove('hidden');
                    chartContainer.classList.add('hidden');
                } else {
                    formContainer.classList.add('hidden');
                    chartContainer.classList.remove('hidden');
                }
            }
            </script>



            <script>
            function showForm(accountId, accountName) {
                document.getElementById('parent_id').value = accountId;
                document.getElementById('selectedAccount').classList.remove('hidden');
                document.getElementById('accountNameDisplay').innerText = accountName;

                document.getElementById('formContainer').classList.remove('hidden');

                document.getElementById('acc_name').value = accountName;
                document.getElementById('formTitle').innerText = `Add Item under ${accountName}`;
                // Clear previous values
                document.getElementById('itemName').value = '';

            }

            function toggleChildren(element) {
                const childrenList = element.nextElementSibling; // Get the sibling <ul> (children)
                if (childrenList) {
                    childrenList.classList.toggle('hidden'); // Toggle the hidden class
                }
            }

            function updateSelectedAccount(accountName) {
                const selectedAccountDiv = document.getElementById('selectedAccount');
                const accountNameDisplay = document.getElementById('accountNameDisplay');

                if (accountName) {
                    accountNameDisplay.textContent = accountName;
                    selectedAccountDiv.classList.remove('hidden');
                } else {
                    accountNameDisplay.textContent = 'None';
                    selectedAccountDiv.classList.add('hidden');
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Attach click event to account items
                document.querySelectorAll('.account-item').forEach(item => {
                    item.addEventListener('click', function(e) {
                        e.stopPropagation(); // Prevent event bubbling
                        const accountId = this.dataset.id;
                        const accountName = this.dataset.name;
                        showForm(accountId, accountName);
                    });
                });
            });
            </script>




            <style>
            body {
                font-family: 'Arial', sans-serif;
            }

            .modal {
                position: fixed;
                z-index: 1;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0, 0, 0, 0.5);
            }

            .modal-content {
                background-color: #ffffff;
                margin: 15% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                border-radius: 8px;
            }

            .close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
            }

            .close:hover,
            .close:focus {
                color: #000;
                text-decoration: none;
                cursor: pointer;
            }



            p,
            li {
                font-size: 1.125rem;
                /* Bigger font size for paragraphs */
                line-height: 1.5;
                color: #333;
                /* Darker text for readability */
            }

            .bg-gray-100 {
                background-color: #f7f9fc;
                /* Light background for the main layout */
            }

            .bg-white {
                background-color: #ffffff;
            }

            .bg-green-500 {
                background-color: #4CAF50;
                /* Green color for buttons */
            }

            .bg-green-500:hover {
                background-color: #45a049;
                /* Darker green on hover */
            }

            .p-2 {
                padding: 0.5rem;
            }

            .p-6 {
                padding: 1.5rem;
            }

            .border {
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .text-sm {
                font-size: 0.875rem;
            }

            .details {
                display: none;
                margin-top: 0.5rem;
            }

            .cursor-pointer {
                cursor: pointer;
            }

            .space-y-4>*+* {
                margin-top: 1rem;
            }

            /* Additional styling for lists and buttons */
            ul {
                list-style-type: none;
                padding-left: 0;
            }

            li {
                padding: 0.5rem;
                transition: background-color 0.2s ease;
            }

            /* li:hover {
        background-color: #e0e0e0;
        /* Light gray on hover */


            */ button {
                padding: 0.5rem 1rem;
                border-radius: 4px;
                border: none;
                color: white;
                font-size: 1rem;
                /* Button font size */
                cursor: pointer;
            }

            .bg-gray-200 {
                background-color: #d1d5db;
                /* Highlight color for selected items */
            }
            </style>

</x-app-layout>